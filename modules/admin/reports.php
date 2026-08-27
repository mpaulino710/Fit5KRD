<?php
// modules/admin/reports.php
require_once '../../config/config.php';
require_once '../../config/database.php';


// session_start(); removed as it's handled in config.php
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'organizador'])) {
    header('Location: ../auth/login.php');
    exit();
}

if ($_SESSION['user_type'] === 'organizador') {
    header('Location: dashboard.php');
    exit();
}

$db = getDB();

// Parámetros de fecha
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-01');
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d');
$tipo_reporte = isset($_GET['tipo_reporte']) ? $_GET['tipo_reporte'] : 'general';

// Estadísticas generales
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM usuarios WHERE estado = 'activo') as total_usuarios,
    (SELECT COUNT(*) FROM eventos) as total_eventos,
    (SELECT COUNT(*) FROM inscripciones WHERE estado = 'confirmada') as total_inscripciones,
    COALESCE((SELECT SUM(monto) FROM pagos WHERE estado = 'completado'), 0) as total_ingresos,
    (SELECT COUNT(*) FROM eventos WHERE fecha_evento >= CURDATE()) as eventos_proximos,
    (SELECT COUNT(*) FROM contactos WHERE estado = 'nuevo') as mensajes_nuevos";

$stats = $db->query($stats_query)->fetch_assoc();

// Reporte de eventos por mes
$eventos_mes_query = "SELECT 
    DATE_FORMAT(fecha_evento, '%Y-%m') as mes,
    COUNT(*) as total_eventos,
    SUM(CASE WHEN estado = 'completado' THEN 1 ELSE 0 END) as eventos_completados,
    SUM(CASE WHEN estado = 'cancelado' THEN 1 ELSE 0 END) as eventos_cancelados
FROM eventos
WHERE fecha_evento >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
GROUP BY DATE_FORMAT(fecha_evento, '%Y-%m')
ORDER BY mes DESC
LIMIT 6";

$eventos_mes = $db->query($eventos_mes_query)->fetch_all(MYSQLI_ASSOC);

// Usuarios nuevos por mes
$usuarios_mes_query = "SELECT 
    DATE_FORMAT(fecha_registro, '%Y-%m') as mes,
    COUNT(*) as nuevos_usuarios,
    SUM(CASE WHEN tipo_usuario = 'corredor' THEN 1 ELSE 0 END) as nuevos_corredores,
    SUM(CASE WHEN tipo_usuario = 'organizador' THEN 1 ELSE 0 END) as nuevos_organizadores
FROM usuarios
WHERE fecha_registro >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    AND estado = 'activo'
GROUP BY DATE_FORMAT(fecha_registro, '%Y-%m')
ORDER BY mes DESC
LIMIT 6";

$usuarios_mes = $db->query($usuarios_mes_query)->fetch_all(MYSQLI_ASSOC);

// Inscripciones por mes
$inscripciones_mes_query = "SELECT 
    DATE_FORMAT(fecha_inscripcion, '%Y-%m') as mes,
    COUNT(*) as total_inscripciones,
    SUM(CASE WHEN i.estado = 'confirmada' THEN 1 ELSE 0 END) as inscripciones_confirmadas
FROM inscripciones i
WHERE fecha_inscripcion >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
GROUP BY DATE_FORMAT(fecha_inscripcion, '%Y-%m')
ORDER BY mes DESC
LIMIT 6";

$inscripciones_mes = $db->query($inscripciones_mes_query)->fetch_all(MYSQLI_ASSOC);

// Ingresos por mes
$ingresos_mes_query = "SELECT 
    DATE_FORMAT(fecha_pago, '%Y-%m') as mes,
    COUNT(*) as total_pagos,
    COALESCE(SUM(monto), 0) as total_ingresos,
    COALESCE(AVG(monto), 0) as promedio_pago
FROM pagos
WHERE estado = 'completado' 
    AND fecha_pago >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
GROUP BY DATE_FORMAT(fecha_pago, '%Y-%m')
ORDER BY mes DESC
LIMIT 6";

$ingresos_mes = $db->query($ingresos_mes_query)->fetch_all(MYSQLI_ASSOC);

include '../../includes/admin-header.php';
?>

<div class="admin-main">
    <!-- Header -->
    <div class="admin-header">
        <h1><i class="fas fa-chart-bar"></i> Reportes y Estadísticas</h1>
        <div class="admin-header-actions">
            <button onclick="printReport()" class="admin-btn admin-btn-secondary">
                <i class="fas fa-print"></i> Imprimir
            </button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="admin-filters">
        <form method="GET" action="">
            <div class="filter-row">
                <div class="form-group">
                    <label for="tipo_reporte"><i class="fas fa-chart-pie"></i> Tipo de Reporte</label>
                    <select id="tipo_reporte" name="tipo_reporte" class="form-control">
                        <option value="general" <?php echo $tipo_reporte === 'general' ? 'selected' : ''; ?>>General</option>
                        <option value="usuarios" <?php echo $tipo_reporte === 'usuarios' ? 'selected' : ''; ?>>Usuarios</option>
                        <option value="eventos" <?php echo $tipo_reporte === 'eventos' ? 'selected' : ''; ?>>Eventos</option>
                        <option value="financiero" <?php echo $tipo_reporte === 'financiero' ? 'selected' : ''; ?>>Financiero</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="fecha_inicio"><i class="fas fa-calendar"></i> Fecha Inicio</label>
                    <input type="date" id="fecha_inicio" name="fecha_inicio" class="form-control" 
                           value="<?php echo $fecha_inicio; ?>">
                </div>
                
                <div class="form-group">
                    <label for="fecha_fin"><i class="fas fa-calendar"></i> Fecha Fin</label>
                    <input type="date" id="fecha_fin" name="fecha_fin" class="form-control" 
                           value="<?php echo $fecha_fin; ?>">
                </div>
                
                <div>
                    <button type="submit" class="admin-btn admin-btn-primary">
                        <i class="fas fa-filter"></i> Aplicar
                    </button>
                    <a href="reports.php" class="admin-btn admin-btn-secondary">
                        <i class="fas fa-redo"></i> Limpiar
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Estadísticas Principales -->
    <div class="admin-stats">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-number"><?php echo number_format($stats['total_usuarios']); ?></div>
            <div class="stat-label">Usuarios Activos</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div class="stat-number"><?php echo number_format($stats['total_eventos']); ?></div>
            <div class="stat-label">Eventos Totales</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-running"></i>
            </div>
            <div class="stat-number"><?php echo number_format($stats['total_inscripciones']); ?></div>
            <div class="stat-label">Inscripciones</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="stat-number">$<?php echo number_format($stats['total_ingresos'], 2); ?></div>
            <div class="stat-label">Ingresos Totales</div>
        </div>
    </div>

    <!-- Gráficos -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h2><i class="fas fa-chart-line"></i> Análisis de Tendencias (Últimos 6 meses)</h2>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 2rem; margin-top: 1.5rem;">
            <!-- Gráfico de Eventos -->
            <div>
                <h3 style="color: var(--admin-text); margin-bottom: 1rem;">
                    <i class="fas fa-chart-line"></i> Eventos por Mes
                </h3>
                <div class="chart-container" style="height: 250px;">
                    <canvas id="eventosChart"></canvas>
                </div>
            </div>
            
            <!-- Gráfico de Usuarios -->
            <div>
                <h3 style="color: var(--admin-text); margin-bottom: 1rem;">
                    <i class="fas fa-user-chart"></i> Usuarios Activos por Mes
                </h3>
                <div class="chart-container" style="height: 250px;">
                    <canvas id="usuariosChart"></canvas>
                </div>
            </div>
            
            <!-- Gráfico de Inscripciones -->
            <div>
                <h3 style="color: var(--admin-text); margin-bottom: 1rem;">
                    <i class="fas fa-running"></i> Inscripciones por Mes
                </h3>
                <div class="chart-container" style="height: 250px;">
                    <canvas id="inscripcionesChart"></canvas>
                </div>
            </div>
            
            <!-- Gráfico de Ingresos -->
            <div>
                <h3 style="color: var(--admin-text); margin-bottom: 1rem;">
                    <i class="fas fa-money-bill-wave"></i> Ingresos por Mes
                </h3>
                <div class="chart-container" style="height: 250px;">
                    <canvas id="ingresosChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Tablas Detalladas -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)); gap: 1.5rem; margin-top: 1.5rem;">
        
        <!-- Eventos por Mes -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3><i class="fas fa-calendar-alt"></i> Eventos por Mes</h3>
            </div>
            <div style="overflow-x: auto;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Mes</th>
                            <th>Total Eventos</th>
                            <th>Completados</th>
                            <th>Cancelados</th>
                            <th>Tasa de Éxito</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($eventos_mes as $mes): 
                            $tasa_exito = $mes['total_eventos'] > 0 ? 
                                round(($mes['eventos_completados'] / $mes['total_eventos']) * 100, 1) : 0;
                        ?>
                        <tr>
                            <td><?php echo date('M Y', strtotime($mes['mes'] . '-01')); ?></td>
                            <td><?php echo $mes['total_eventos']; ?></td>
                            <td><?php echo $mes['eventos_completados']; ?></td>
                            <td><?php echo $mes['eventos_cancelados']; ?></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <span><?php echo $tasa_exito; ?>%</span>
                                    <div style="flex: 1; background: #e0e0e0; border-radius: 10px; height: 8px;">
                                        <div style="
                                            width: <?php echo $tasa_exito; ?>%;
                                            height: 100%;
                                            background: <?php echo $tasa_exito >= 80 ? 'var(--admin-success)' : ($tasa_exito >= 60 ? 'var(--admin-warning)' : 'var(--admin-danger)'); ?>;
                                            border-radius: 10px;
                                        "></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Usuarios por Mes -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3><i class="fas fa-users"></i> Usuarios Activos por Mes</h3>
            </div>
            <div style="overflow-x: auto;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Mes</th>
                            <th>Nuevos Usuarios</th>
                            <th>Corredores</th>
                            <th>Organizadores</th>
                            <th>Crecimiento</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $usuarios_mes_reverse = array_reverse($usuarios_mes);
                        foreach ($usuarios_mes_reverse as $index => $mes):
                            $crecimiento = 0;
                            if ($index > 0) {
                                $mes_anterior = $usuarios_mes_reverse[$index - 1];
                                if ($mes_anterior['nuevos_usuarios'] > 0) {
                                    $crecimiento = (($mes['nuevos_usuarios'] - $mes_anterior['nuevos_usuarios']) / $mes_anterior['nuevos_usuarios']) * 100;
                                }
                            }
                        ?>
                        <tr>
                            <td><?php echo date('M Y', strtotime($mes['mes'] . '-01')); ?></td>
                            <td><?php echo $mes['nuevos_usuarios']; ?></td>
                            <td><?php echo $mes['nuevos_corredores']; ?></td>
                            <td><?php echo $mes['nuevos_organizadores']; ?></td>
                            <td>
                                <span style="color: <?php echo $crecimiento >= 0 ? 'var(--admin-success)' : 'var(--admin-danger)'; ?>;">
                                    <i class="fas fa-<?php echo $crecimiento >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                                    <?php echo abs(round($crecimiento, 1)); ?>%
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Inscripciones por Mes -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3><i class="fas fa-running"></i> Inscripciones por Mes</h3>
            </div>
            <div style="overflow-x: auto;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Mes</th>
                            <th>Total Inscripciones</th>
                            <th>Confirmadas</th>
                            <th>Tasa de Confirmación</th>
                            <th>Variación</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $inscripciones_mes_reverse = array_reverse($inscripciones_mes);
                        foreach ($inscripciones_mes_reverse as $index => $mes):
                            $tasa_confirmacion = $mes['total_inscripciones'] > 0 ? 
                                round(($mes['inscripciones_confirmadas'] / $mes['total_inscripciones']) * 100, 1) : 0;
                            
                            $variacion = 0;
                            if ($index > 0) {
                                $mes_anterior = $inscripciones_mes_reverse[$index - 1];
                                if ($mes_anterior['total_inscripciones'] > 0) {
                                    $variacion = (($mes['total_inscripciones'] - $mes_anterior['total_inscripciones']) / $mes_anterior['total_inscripciones']) * 100;
                                }
                            }
                        ?>
                        <tr>
                            <td><?php echo date('M Y', strtotime($mes['mes'] . '-01')); ?></td>
                            <td><?php echo $mes['total_inscripciones']; ?></td>
                            <td><?php echo $mes['inscripciones_confirmadas']; ?></td>
                            <td>
                                <span style="color: <?php echo $tasa_confirmacion >= 80 ? 'var(--admin-success)' : ($tasa_confirmacion >= 60 ? 'var(--admin-warning)' : 'var(--admin-danger)'); ?>;">
                                    <?php echo $tasa_confirmacion; ?>%
                                </span>
                            </td>
                            <td>
                                <span style="color: <?php echo $variacion >= 0 ? 'var(--admin-success)' : 'var(--admin-danger)'; ?>;">
                                    <i class="fas fa-<?php echo $variacion >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                                    <?php echo abs(round($variacion, 1)); ?>%
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Ingresos por Mes -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h3><i class="fas fa-dollar-sign"></i> Ingresos por Mes</h3>
            </div>
            <div style="overflow-x: auto;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Mes</th>
                            <th>Total Pagos</th>
                            <th>Ingresos Totales</th>
                            <th>Promedio por Pago</th>
                            <th>Crecimiento</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $ingresos_mes_reverse = array_reverse($ingresos_mes);
                        foreach ($ingresos_mes_reverse as $index => $mes):
                            $crecimiento = 0;
                            if ($index > 0) {
                                $mes_anterior = $ingresos_mes_reverse[$index - 1];
                                if ($mes_anterior['total_ingresos'] > 0) {
                                    $crecimiento = (($mes['total_ingresos'] - $mes_anterior['total_ingresos']) / $mes_anterior['total_ingresos']) * 100;
                                }
                            }
                        ?>
                        <tr>
                            <td><?php echo date('M Y', strtotime($mes['mes'] . '-01')); ?></td>
                            <td><?php echo $mes['total_pagos']; ?></td>
                            <td>$<?php echo number_format($mes['total_ingresos'], 2); ?></td>
                            <td>$<?php echo number_format($mes['promedio_pago'], 2); ?></td>
                            <td>
                                <span style="color: <?php echo $crecimiento >= 0 ? 'var(--admin-success)' : 'var(--admin-danger)'; ?>;">
                                    <i class="fas fa-<?php echo $crecimiento >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                                    <?php echo abs(round($crecimiento, 1)); ?>%
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Resumen General -->
    <div class="admin-card" style="margin-top: 1.5rem;">
        <div class="admin-card-header">
            <h2><i class="fas fa-info-circle"></i> Resumen General del Sistema</h2>
        </div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; padding: 1rem;">
            <div>
                <h3 style="color: var(--admin-text); margin-bottom: 1rem;">
                    <i class="fas fa-info-circle"></i> Información del Sistema
                </h3>
                <ul style="list-style: none; padding: 0;">
                    <li style="padding: 0.5rem 0; border-bottom: 1px solid var(--admin-border);">
                        <strong>Usuarios activos:</strong> 
                        <span style="float: right;"><?php echo $stats['total_usuarios']; ?></span>
                    </li>
                    <li style="padding: 0.5rem 0; border-bottom: 1px solid var(--admin-border);">
                        <strong>Próximos eventos:</strong> 
                        <span style="float: right;"><?php echo $stats['eventos_proximos']; ?></span>
                    </li>
                    <li style="padding: 0.5rem 0; border-bottom: 1px solid var(--admin-border);">
                        <strong>Mensajes nuevos:</strong> 
                        <span style="float: right;"><?php echo $stats['mensajes_nuevos']; ?></span>
                    </li>
                    <li style="padding: 0.5rem 0;">
                        <strong>Ingresos mensuales promedio:</strong> 
                        <span style="float: right;">
                            $<?php 
                            $ingresos_promedio = count($ingresos_mes) > 0 ? 
                                array_sum(array_column($ingresos_mes, 'total_ingresos')) / count($ingresos_mes) : 0;
                            echo number_format($ingresos_promedio, 2); 
                            ?>
                        </span>
                    </li>
                </ul>
            </div>
            
            <div>
                <h3 style="color: var(--admin-text); margin-bottom: 1rem;">
                    <i class="fas fa-chart-pie"></i> Distribución por Categorías
                </h3>
                <div class="chart-container" style="height: 200px;">
                    <canvas id="categoriasChart"></canvas>
                </div>
            </div>
            
            <div>
                <h3 style="color: var(--admin-text); margin-bottom: 1rem;">
                    <i class="fas fa-bullseye"></i> Objetivos del Mes
                </h3>
                <div style="background: var(--admin-bg); padding: 1rem; border-radius: 8px;">
                    <div style="margin-bottom: 1rem;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.3rem;">
                            <span>Nuevos usuarios activos</span>
                            <span>75%</span>
                        </div>
                        <div style="background: #e0e0e0; border-radius: 10px; height: 8px;">
                            <div style="width: 75%; height: 100%; background: var(--admin-success); border-radius: 10px;"></div>
                        </div>
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.3rem;">
                            <span>Inscripciones</span>
                            <span>60%</span>
                        </div>
                        <div style="background: #e0e0e0; border-radius: 10px; height: 8px;">
                            <div style="width: 60%; height: 100%; background: var(--admin-warning); border-radius: 10px;"></div>
                        </div>
                    </div>
                    <div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.3rem;">
                            <span>Ingresos</span>
                            <span>90%</span>
                        </div>
                        <div style="background: #e0e0e0; border-radius: 10px; height: 8px;">
                            <div style="width: 90%; height: 100%; background: var(--admin-info); border-radius: 10px;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Preparar datos para gráficos
const eventosData = {
    meses: <?php echo json_encode(array_map(function($mes) { 
        return date('M', strtotime($mes['mes'] . '-01')); 
    }, array_reverse($eventos_mes))); ?>,
    totales: <?php echo json_encode(array_map(function($mes) { 
        return $mes['total_eventos']; 
    }, array_reverse($eventos_mes))); ?>,
    completados: <?php echo json_encode(array_map(function($mes) { 
        return $mes['eventos_completados']; 
    }, array_reverse($eventos_mes))); ?>
};

const usuariosData = {
    meses: <?php echo json_encode(array_map(function($mes) { 
        return date('M', strtotime($mes['mes'] . '-01')); 
    }, array_reverse($usuarios_mes))); ?>,
    totales: <?php echo json_encode(array_map(function($mes) { 
        return $mes['nuevos_usuarios']; 
    }, array_reverse($usuarios_mes))); ?>,
    corredores: <?php echo json_encode(array_map(function($mes) { 
        return $mes['nuevos_corredores']; 
    }, array_reverse($usuarios_mes))); ?>
};

const inscripcionesData = {
    meses: <?php echo json_encode(array_map(function($mes) { 
        return date('M', strtotime($mes['mes'] . '-01')); 
    }, array_reverse($inscripciones_mes))); ?>,
    totales: <?php echo json_encode(array_map(function($mes) { 
        return $mes['total_inscripciones']; 
    }, array_reverse($inscripciones_mes))); ?>,
    confirmadas: <?php echo json_encode(array_map(function($mes) { 
        return $mes['inscripciones_confirmadas']; 
    }, array_reverse($inscripciones_mes))); ?>
};

const ingresosData = {
    meses: <?php echo json_encode(array_map(function($mes) { 
        return date('M', strtotime($mes['mes'] . '-01')); 
    }, array_reverse($ingresos_mes))); ?>,
    ingresos: <?php echo json_encode(array_map(function($mes) { 
        return $mes['total_ingresos']; 
    }, array_reverse($ingresos_mes))); ?>
};

// Inicializar gráficos
document.addEventListener('DOMContentLoaded', function() {
    // Gráfico de eventos
    const eventosCtx = document.getElementById('eventosChart').getContext('2d');
    new Chart(eventosCtx, {
        type: 'line',
        data: {
            labels: eventosData.meses,
            datasets: [{
                label: 'Total Eventos',
                data: eventosData.totales,
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                tension: 0.1
            }, {
                label: 'Eventos Completados',
                data: eventosData.completados,
                borderColor: 'rgb(54, 162, 235)',
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
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
    
    // Gráfico de usuarios
    const usuariosCtx = document.getElementById('usuariosChart').getContext('2d');
    new Chart(usuariosCtx, {
        type: 'bar',
        data: {
            labels: usuariosData.meses,
            datasets: [{
                label: 'Nuevos Usuarios Activos',
                data: usuariosData.totales,
                backgroundColor: 'rgba(153, 102, 255, 0.5)',
                borderColor: 'rgb(153, 102, 255)',
                borderWidth: 1
            }, {
                label: 'Nuevos Corredores',
                data: usuariosData.corredores,
                backgroundColor: 'rgba(255, 159, 64, 0.5)',
                borderColor: 'rgb(255, 159, 64)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
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
    
    // Gráfico de inscripciones
    const inscripcionesCtx = document.getElementById('inscripcionesChart').getContext('2d');
    new Chart(inscripcionesCtx, {
        type: 'bar',
        data: {
            labels: inscripcionesData.meses,
            datasets: [{
                label: 'Total Inscripciones',
                data: inscripcionesData.totales,
                backgroundColor: 'rgba(255, 99, 132, 0.5)',
                borderColor: 'rgb(255, 99, 132)',
                borderWidth: 1
            }, {
                label: 'Inscripciones Confirmadas',
                data: inscripcionesData.confirmadas,
                backgroundColor: 'rgba(75, 192, 192, 0.5)',
                borderColor: 'rgb(75, 192, 192)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
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
    
    // Gráfico de ingresos
    const ingresosCtx = document.getElementById('ingresosChart').getContext('2d');
    new Chart(ingresosCtx, {
        type: 'line',
        data: {
            labels: ingresosData.meses,
            datasets: [{
                label: 'Ingresos ($)',
                data: ingresosData.ingresos,
                borderColor: 'rgb(255, 205, 86)',
                backgroundColor: 'rgba(255, 205, 86, 0.2)',
                tension: 0.1,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `Ingresos: $${context.raw.toFixed(2)}`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Dólares ($)'
                    },
                    ticks: {
                        callback: function(value) {
                            return '$' + value;
                        }
                    }
                }
            }
        }
    });
    
    // Gráfico de categorías
    const categoriasCtx = document.getElementById('categoriasChart').getContext('2d');
    new Chart(categoriasCtx, {
        type: 'doughnut',
        data: {
            labels: ['5K', '10K', 'Media Maratón', 'Maratón', 'Trail', 'Otros'],
            datasets: [{
                data: [45, 20, 15, 10, 7, 3],
                backgroundColor: [
                    'rgb(255, 99, 132)',
                    'rgb(54, 162, 235)',
                    'rgb(255, 205, 86)',
                    'rgb(75, 192, 192)',
                    'rgb(153, 102, 255)',
                    'rgb(201, 203, 207)'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                }
            }
        }
    });
});

// Imprimir reporte
function printReport() {
    window.print();
}

// Validar fechas
document.getElementById('fecha_inicio').addEventListener('change', function() {
    const fechaFin = document.getElementById('fecha_fin');
    if (this.value > fechaFin.value) {
        fechaFin.value = this.value;
    }
});

document.getElementById('fecha_fin').addEventListener('change', function() {
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

@media print {
    .admin-sidebar,
    .admin-header,
    .admin-filters,
    .admin-header-actions,
    button,
    form {
        display: none !important;
    }
    
    .admin-main {
        margin-left: 0 !important;
        padding: 20px !important;
    }
    
    .admin-card {
        break-inside: avoid;
        margin-bottom: 20px;
    }
    
    .chart-container {
        height: 250px !important;
    }
}
</style>

<?php include '../../includes/admin-footer.php'; ?>