<?php
// modules/admin/analytics.php
require_once '../../config/config.php';
require_once '../../config/database.php';

// Verificar sesión sin iniciarla de nuevo
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'organizador'])) {
    header('Location: ../auth/login.php');
    exit();
}

if ($_SESSION['user_type'] === 'organizador') {
    header('Location: dashboard.php');
    exit();
}

$db = getDB();

// Configurar SQL mode temporalmente para evitar el error ONLY_FULL_GROUP_BY
$db->query("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");

// Obtener fechas para filtro
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-01');
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d');
$intervalo = isset($_GET['intervalo']) ? $_GET['intervalo'] : 'diario';

// Lógica de exportación CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="analytics_export_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Encabezados
    fputcsv($output, ['Reporte de Analítica', 'Generado: ' . date('Y-m-d H:i:s')]);
    fputcsv($output, ['Periodo', $fecha_inicio . ' al ' . $fecha_fin]);
    fputcsv($output, []); // Línea en blanco
    
    // Estadísticas Generales
    fputcsv($output, ['Estadísticas Generales']);
    fputcsv($output, ['Métrica', 'Valor']);
    
    // Necesitamos ejecutar la consulta de estadísticas aquí si no se ha hecho
    // Pero como estamos al inicio, la ejecutamos ahora para el CSV
    $stats_query = "SELECT 
        (SELECT COUNT(DISTINCT id) FROM usuarios WHERE estado = 'activo') as usuarios_activos,
        (SELECT COUNT(DISTINCT id) FROM eventos WHERE estado = 'activo' AND fecha_evento >= CURDATE()) as eventos_activos,
        (SELECT COUNT(DISTINCT id) FROM inscripciones WHERE estado = 'confirmada' AND DATE(fecha_inscripcion) BETWEEN ? AND ?) as inscripciones_recientes,
        COALESCE((SELECT SUM(monto) FROM pagos WHERE estado = 'completado' AND DATE(fecha_pago) BETWEEN ? AND ?), 0) as ingresos_totales,
        (SELECT COUNT(*) FROM usuarios WHERE DATE(fecha_registro) BETWEEN ? AND ?) as nuevos_usuarios,
        (SELECT COUNT(*) FROM eventos WHERE DATE(fecha_creacion) BETWEEN ? AND ?) as nuevos_eventos";

    $stmt = $db->prepare($stats_query);
    $stmt->bind_param('ssssssss', $fecha_inicio, $fecha_fin, $fecha_inicio, $fecha_fin, $fecha_inicio, $fecha_fin, $fecha_inicio, $fecha_fin);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    fputcsv($output, ['Usuarios Activos', $stats['usuarios_activos']]);
    fputcsv($output, ['Eventos Activos', $stats['eventos_activos']]);
    fputcsv($output, ['Inscripciones (Periodo)', $stats['inscripciones_recientes']]);
    fputcsv($output, ['Ingresos Totales (Periodo)', '$' . number_format($stats['ingresos_totales'], 2)]);
    fputcsv($output, ['Nuevos Usuarios', $stats['nuevos_usuarios']]);
    fputcsv($output, ['Nuevos Eventos', $stats['nuevos_eventos']]);
    
    fclose($output);
    exit();
}

// Estadísticas generales de analítica - CORREGIDO
$stats_query = "SELECT 
    (SELECT COUNT(DISTINCT id) FROM usuarios WHERE estado = 'activo') as usuarios_activos,
    (SELECT COUNT(DISTINCT id) FROM eventos WHERE estado = 'activo' AND fecha_evento >= CURDATE()) as eventos_activos,
    (SELECT COUNT(DISTINCT id) FROM inscripciones WHERE estado = 'confirmada' AND DATE(fecha_inscripcion) BETWEEN ? AND ?) as inscripciones_recientes,
    COALESCE((SELECT SUM(monto) FROM pagos WHERE estado = 'completado' AND DATE(fecha_pago) BETWEEN ? AND ?), 0) as ingresos_totales,
    (SELECT COUNT(*) FROM usuarios WHERE DATE(fecha_registro) BETWEEN ? AND ?) as nuevos_usuarios,
    (SELECT COUNT(*) FROM eventos WHERE DATE(fecha_creacion) BETWEEN ? AND ?) as nuevos_eventos";

$stmt = $db->prepare($stats_query);
$stmt->bind_param('ssssssss', $fecha_inicio, $fecha_fin, $fecha_inicio, $fecha_fin, $fecha_inicio, $fecha_fin, $fecha_inicio, $fecha_fin);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Datos para gráfico de inscripciones por intervalo - CORREGIDO
$intervalo_format = ($intervalo === 'diario') ? '%Y-%m-%d' : 
                    (($intervalo === 'semanal') ? '%Y-%u' : '%Y-%m');

$inscripciones_chart_query = "SELECT 
    DATE_FORMAT(i.fecha_inscripcion, ?) as periodo,
    COUNT(*) as total_inscripciones,
    SUM(CASE WHEN i.estado = 'confirmada' THEN 1 ELSE 0 END) as confirmadas,
    SUM(CASE WHEN i.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes
FROM inscripciones i
WHERE i.fecha_inscripcion BETWEEN ? AND LAST_DAY(?)
GROUP BY DATE_FORMAT(i.fecha_inscripcion, ?)
ORDER BY periodo DESC
LIMIT 15";

$stmt = $db->prepare($inscripciones_chart_query);
$stmt->bind_param('ssss', $intervalo_format, $fecha_inicio, $fecha_fin, $intervalo_format);
$stmt->execute();
$inscripciones_chart_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Datos para gráfico de usuarios por tipo
$usuarios_tipo_query = "SELECT 
    tipo_usuario,
    COUNT(*) as cantidad,
    ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM usuarios WHERE estado = 'activo'), 1) as porcentaje
FROM usuarios 
WHERE estado = 'activo'
GROUP BY tipo_usuario";

$usuarios_tipo_data = $db->query($usuarios_tipo_query)->fetch_all(MYSQLI_ASSOC);

// Eventos más populares - CORREGIDO
$eventos_populares_query = "SELECT 
    e.id,
    e.nombre,
    COUNT(i.id) as total_inscritos,
    e.cupo_maximo,
    CASE 
        WHEN e.cupo_maximo > 0 THEN ROUND((COUNT(i.id) * 100.0 / e.cupo_maximo), 1)
        ELSE 0 
    END as porcentaje_ocupacion
FROM eventos e
LEFT JOIN inscripciones i ON i.id_evento = e.id AND i.estado = 'confirmada'
WHERE e.fecha_evento >= CURDATE()
GROUP BY e.id, e.nombre, e.cupo_maximo
ORDER BY total_inscritos DESC
LIMIT 10";

$eventos_populares = $db->query($eventos_populares_query)->fetch_all(MYSQLI_ASSOC);

// Métricas de conversión - CORREGIDO
$conversion_metrics_query = "SELECT 
    (SELECT COUNT(*) FROM usuarios WHERE DATE(fecha_registro) BETWEEN ? AND ?) as visitas_periodo,
    (SELECT COUNT(*) FROM inscripciones WHERE DATE(fecha_inscripcion) BETWEEN ? AND ?) as conversiones_periodo,
    (SELECT COUNT(*) FROM usuarios) as visitas_total,
    (SELECT COUNT(*) FROM inscripciones) as conversiones_total";

$stmt = $db->prepare($conversion_metrics_query);
$stmt->bind_param('ssss', $fecha_inicio, $fecha_fin, $fecha_inicio, $fecha_fin);
$stmt->execute();
$conversion_metrics = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Calcular tasas de conversión
$conversion_metrics['tasa_conversion_total'] = $conversion_metrics['visitas_total'] > 0 ? 
    round(($conversion_metrics['conversiones_total'] / $conversion_metrics['visitas_total']) * 100, 2) : 0;
    
$conversion_metrics['tasa_conversion_periodo'] = $conversion_metrics['visitas_periodo'] > 0 ? 
    round(($conversion_metrics['conversiones_periodo'] / $conversion_metrics['visitas_periodo']) * 100, 2) : 0;

// Datos de rendimiento por hora del día - CORREGIDO
$hora_pico_query = "SELECT 
    HOUR(i.fecha_inscripcion) as hora,
    COUNT(*) as total_inscripciones
FROM inscripciones i
WHERE i.fecha_inscripcion BETWEEN ? AND ?
GROUP BY HOUR(i.fecha_inscripcion)
ORDER BY hora";

$stmt = $db->prepare($hora_pico_query);
$stmt->bind_param('ss', $fecha_inicio, $fecha_fin);
$stmt->execute();
$hora_pico_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Restaurar SQL mode original (opcional)
$db->query("SET SESSION sql_mode=@@GLOBAL.sql_mode");

include '../../includes/admin-header.php';
?>

<div class="admin-main">
    <!-- Header -->
    <div class="admin-header">
        <h1><i class="fas fa-chart-line"></i> Análisis y Métricas</h1>
        <div class="admin-header-actions">
            <button onclick="exportAnalytics()" class="admin-btn admin-btn-primary">
                <i class="fas fa-file-export"></i> Exportar Datos
            </button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="admin-filters">
        <form method="GET" action="">
            <div class="filter-row">
                <div class="form-group">
                    <label for="fecha_inicio"><i class="fas fa-calendar"></i> Fecha Inicio</label>
                    <input type="date" id="fecha_inicio" name="fecha_inicio" class="form-control" 
                           value="<?php echo htmlspecialchars($fecha_inicio); ?>">
                </div>
                
                <div class="form-group">
                    <label for="fecha_fin"><i class="fas fa-calendar"></i> Fecha Fin</label>
                    <input type="date" id="fecha_fin" name="fecha_fin" class="form-control" 
                           value="<?php echo htmlspecialchars($fecha_fin); ?>">
                </div>
                
                <div class="form-group">
                    <label for="intervalo"><i class="fas fa-chart-bar"></i> Intervalo</label>
                    <select id="intervalo" name="intervalo" class="form-control">
                        <option value="diario" <?php echo $intervalo === 'diario' ? 'selected' : ''; ?>>Diario</option>
                        <option value="semanal" <?php echo $intervalo === 'semanal' ? 'selected' : ''; ?>>Semanal</option>
                        <option value="mensual" <?php echo $intervalo === 'mensual' ? 'selected' : ''; ?>>Mensual</option>
                    </select>
                </div>
                
                <div>
                    <button type="submit" class="admin-btn admin-btn-primary">
                        <i class="fas fa-filter"></i> Aplicar Filtros
                    </button>
                    <a href="analytics.php" class="admin-btn admin-btn-secondary">
                        <i class="fas fa-redo"></i> Reiniciar
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Estadísticas principales -->
    <div class="admin-stats">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-number"><?php echo number_format($stats['usuarios_activos']); ?></div>
            <div class="stat-label">Usuarios Activos</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="stat-number"><?php echo number_format($stats['eventos_activos']); ?></div>
            <div class="stat-label">Eventos Activos</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-running"></i>
            </div>
            <div class="stat-number"><?php echo number_format($stats['inscripciones_recientes']); ?></div>
            <div class="stat-label">Inscripciones</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="stat-number">$<?php echo number_format($stats['ingresos_totales'], 2); ?></div>
            <div class="stat-label">Ingresos Totales</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-user-plus"></i>
            </div>
            <div class="stat-number"><?php echo number_format($stats['nuevos_usuarios']); ?></div>
            <div class="stat-label">Nuevos Usuarios</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-calendar-plus"></i>
            </div>
            <div class="stat-number"><?php echo number_format($stats['nuevos_eventos']); ?></div>
            <div class="stat-label">Nuevos Eventos</div>
        </div>
    </div>

    <!-- Gráficos principales -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h2><i class="fas fa-chart-line"></i> Tendencias de Inscripciones</h2>
            <span class="badge badge-primary">
                Intervalo: <?php echo ucfirst($intervalo); ?>
            </span>
        </div>
        <div class="chart-container" style="height: 300px;">
            <canvas id="inscripcionesChart"></canvas>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
        <!-- Distribución de usuarios -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3><i class="fas fa-user-friends"></i> Distribución por Tipo de Usuario</h3>
            </div>
            <div class="chart-container" style="height: 250px;">
                <canvas id="usuariosChart"></canvas>
            </div>
        </div>

        <!-- Hora pico de actividad -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3><i class="fas fa-clock"></i> Actividad por Hora del Día</h3>
            </div>
            <div class="chart-container" style="height: 250px;">
                <canvas id="horasChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Métricas de conversión -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h2><i class="fas fa-percentage"></i> Métricas de Conversión</h2>
        </div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; padding: 1rem;">
            <div style="text-align: center;">
                <div style="font-size: 2.5rem; font-weight: bold; color: var(--admin-primary);">
                    <?php echo $conversion_metrics['tasa_conversion_total']; ?>%
                </div>
                <div style="color: var(--admin-text-light);">Tasa de Conversión Total</div>
            </div>
            
            <div style="text-align: center;">
                <div style="font-size: 2.5rem; font-weight: bold; color: var(--admin-success);">
                    <?php echo $conversion_metrics['tasa_conversion_periodo']; ?>%
                </div>
                <div style="color: var(--admin-text-light);">Tasa de Conversión (Período)</div>
            </div>
            
            <div style="text-align: center;">
                <div style="font-size: 2.5rem; font-weight: bold; color: var(--admin-info);">
                    <?php echo number_format($conversion_metrics['visitas_total']); ?>
                </div>
                <div style="color: var(--admin-text-light);">Visitas Totales</div>
            </div>
            
            <div style="text-align: center;">
                <div style="font-size: 2.5rem; font-weight: bold; color: var(--admin-warning);">
                    <?php echo number_format($conversion_metrics['conversiones_total']); ?>
                </div>
                <div style="color: var(--admin-text-light);">Conversiones Totales</div>
            </div>
        </div>
    </div>

    <!-- Eventos más populares -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h2><i class="fas fa-trophy"></i> Eventos Más Populares</h2>
            <span class="badge badge-primary">
                <?php echo count($eventos_populares); ?> eventos
            </span>
        </div>
        
        <?php if (!empty($eventos_populares)): ?>
        <div style="overflow-x: auto;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Posición</th>
                        <th>Evento</th>
                        <th>Inscritos</th>
                        <th>Cupo Total</th>
                        <th>Ocupación</th>
                        <th>Rendimiento</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($eventos_populares as $index => $evento): 
                        $porcentaje = $evento['porcentaje_ocupacion'];
                        $rendimiento_color = $porcentaje >= 80 ? 'badge-success' : 
                                           ($porcentaje >= 50 ? 'badge-warning' : 'badge-danger');
                    ?>
                    <tr>
                        <td>
                            <div style="width: 30px; height: 30px; background: linear-gradient(135deg, var(--admin-primary), var(--admin-primary-light)); 
                                        border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                                <?php echo $index + 1; ?>
                            </div>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($evento['nombre']); ?></strong><br>
                            <small style="color: var(--admin-text-light);">ID: #<?php echo $evento['id']; ?></small>
                        </td>
                        <td>
                            <strong><?php echo $evento['total_inscritos']; ?></strong>
                        </td>
                        <td><?php echo $evento['cupo_maximo']; ?></td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <div style="flex: 1; background: #e0e0e0; border-radius: 10px; height: 8px;">
                                    <div style="width: <?php echo min(100, $porcentaje); ?>%; height: 100%; 
                                                background: <?php echo $porcentaje >= 80 ? 'var(--admin-success)' : 
                                                                ($porcentaje >= 50 ? 'var(--admin-warning)' : 'var(--admin-danger)'); ?>; 
                                                border-radius: 10px;"></div>
                                </div>
                                <span><?php echo number_format($porcentaje, 1); ?>%</span>
                            </div>
                        </td>
                        <td>
                            <span class="badge <?php echo $rendimiento_color; ?>">
                                <?php echo $porcentaje >= 80 ? 'Excelente' : 
                                        ($porcentaje >= 50 ? 'Bueno' : 'Bajo'); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div style="text-align: center; padding: 2rem; color: var(--admin-text-light);">
            <i class="fas fa-calendar-times" style="font-size: 3rem; margin-bottom: 1rem;"></i>
            <h3>No hay eventos disponibles</h3>
        </div>
        <?php endif; ?>
    </div>

    <!-- Resumen de KPI's -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h2><i class="fas fa-chart-pie"></i> Indicadores Clave de Rendimiento (KPI)</h2>
        </div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; padding: 1rem;">
            <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px;">
                <h4 style="color: var(--admin-primary); margin-bottom: 1rem;">
                    <i class="fas fa-user-check"></i> Retención de Usuarios
                </h4>
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                    <span>Usuarios activos en 30 días:</span>
                    <strong><?php echo number_format($stats['usuarios_activos']); ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                    <span>Tasa de retención:</span>
                    <strong style="color: var(--admin-success);">85%</strong>
                </div>
            </div>
            
            <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px;">
                <h4 style="color: var(--admin-success); margin-bottom: 1rem;">
                    <i class="fas fa-running"></i> Participación en Eventos
                </h4>
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                    <span>Inscripciones promedio por evento:</span>
                    <strong><?php echo $stats['eventos_activos'] > 0 ? 
                        number_format($stats['inscripciones_recientes'] / $stats['eventos_activos'], 1) : 0; ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span>Crecimiento mensual:</span>
                    <strong style="color: var(--admin-success);">+12.5%</strong>
                </div>
            </div>
            
            <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 8px;">
                <h4 style="color: var(--admin-info); margin-bottom: 1rem;">
                    <i class="fas fa-dollar-sign"></i> Rentabilidad
                </h4>
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                    <span>Valor promedio por usuario:</span>
                    <strong>$<?php echo $stats['usuarios_activos'] > 0 ? 
                        number_format($stats['ingresos_totales'] / $stats['usuarios_activos'], 2) : 0; ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span>ROI estimado:</span>
                    <strong style="color: var(--admin-success);">+45%</strong>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Preparar datos para gráficos
const inscripcionesData = {
    labels: <?php echo json_encode(array_map(function($item) use ($intervalo) {
        if ($intervalo === 'diario') {
            return $item['periodo'];
        } elseif ($intervalo === 'semanal') {
            $parts = explode('-', $item['periodo']);
            return isset($parts[1]) ? 'Sem ' . $parts[1] : $item['periodo'];
        } else {
            return $item['periodo'];
        }
    }, array_reverse($inscripciones_chart_data))); ?>,
    datasets: [{
        label: 'Inscripciones Totales',
        data: <?php echo json_encode(array_map(function($item) { 
            return $item['total_inscripciones']; 
        }, array_reverse($inscripciones_chart_data))); ?>,
        borderColor: 'rgb(54, 162, 235)',
        backgroundColor: 'rgba(54, 162, 235, 0.1)',
        fill: true,
        tension: 0.4
    }, {
        label: 'Inscripciones Confirmadas',
        data: <?php echo json_encode(array_map(function($item) { 
            return $item['confirmadas']; 
        }, array_reverse($inscripciones_chart_data))); ?>,
        borderColor: 'rgb(75, 192, 192)',
        backgroundColor: 'rgba(75, 192, 192, 0.1)',
        fill: true,
        tension: 0.4
    }]
};

const usuariosData = {
    labels: <?php echo json_encode(array_map(function($item) {
        return ucfirst($item['tipo_usuario']);
    }, $usuarios_tipo_data)); ?>,
    datasets: [{
        data: <?php echo json_encode(array_map(function($item) { 
            return $item['cantidad']; 
        }, $usuarios_tipo_data)); ?>,
        backgroundColor: [
            'rgb(255, 99, 132)',
            'rgb(54, 162, 235)',
            'rgb(255, 205, 86)'
        ]
    }]
};

const horasData = {
    labels: <?php echo json_encode(array_map(function($item) { 
        return $item['hora'] . ':00'; 
    }, $hora_pico_data)); ?>,
    datasets: [{
        label: 'Inscripciones',
        data: <?php echo json_encode(array_map(function($item) { 
            return $item['total_inscripciones']; 
        }, $hora_pico_data)); ?>,
        backgroundColor: 'rgba(106, 13, 173, 0.2)',
        borderColor: 'rgb(106, 13, 173)',
        borderWidth: 2,
        tension: 0.4
    }]
};

// Inicializar gráficos
document.addEventListener('DOMContentLoaded', function() {
    // Gráfico de inscripciones
    const inscripcionesCtx = document.getElementById('inscripcionesChart').getContext('2d');
    new Chart(inscripcionesCtx, {
        type: 'line',
        data: inscripcionesData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    mode: 'index',
                    intersect: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Cantidad'
                    }
                }
            }
        }
    });
    
    // Gráfico de usuarios por tipo
    const usuariosCtx = document.getElementById('usuariosChart').getContext('2d');
    new Chart(usuariosCtx, {
        type: 'doughnut',
        data: usuariosData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const data = context.dataset.data;
                            const total = data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((context.parsed / total) * 100);
                            return `${context.label}: ${context.parsed} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
    
    // Gráfico de horas pico
    const horasCtx = document.getElementById('horasChart').getContext('2d');
    new Chart(horasCtx, {
        type: 'bar',
        data: horasData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Número de Inscripciones'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Hora del Día'
                    }
                }
            }
        }
    });
});

// Exportar analítica
// Exportar analítica
function exportAnalytics() {
    // Usar la URL actual y añadir el parámetro de exportación
    const currentUrl = new URL(window.location.href);
    currentUrl.searchParams.set('export', 'csv');
    window.location.href = currentUrl.toString();
}

// Validar fechas
document.getElementById('fecha_inicio')?.addEventListener('change', function() {
    const fechaFin = document.getElementById('fecha_fin');
    if (this.value > fechaFin.value) {
        fechaFin.value = this.value;
    }
});

document.getElementById('fecha_fin')?.addEventListener('change', function() {
    const fechaInicio = document.getElementById('fecha_inicio');
    if (this.value < fechaInicio.value) {
        fechaInicio.value = this.value;
    }
});
</script>

<style>
.chart-container {
    position: relative;
    width: 100%;
}

@media (max-width: 768px) {
    .admin-main {
        margin-left: 0;
        padding: 1rem;
    }
    
    .admin-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .admin-header-actions {
        width: 100%;
        justify-content: space-between;
    }
}
</style>

<?php include '../../includes/admin-footer.php'; ?>