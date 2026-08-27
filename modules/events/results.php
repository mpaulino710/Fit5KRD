<?php
// modules/events/results.php
require_once '../../config/config.php';
require_once '../../config/database.php';

$db = getDB();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$event_id = intval($_GET['id']);

// Obtener información del evento
$query = "SELECT * FROM eventos WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $event_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: index.php');
    exit();
}

$evento = $result->fetch_assoc();
$stmt->close();

// Verificar si el evento ya pasó
$event_date = strtotime($evento['fecha_evento']);
$now = time();

if ($event_date > $now && $evento['estado'] === 'activo') {
    // Redireccionar a la página del evento si aún no ha ocurrido
    header('Location: view.php?id=' . $event_id);
    exit();
}

// Obtener resultados
$query = "SELECT i.*, u.nombre, u.apellido, u.genero, 
          TIMEDIFF(i.tiempo_final, '00:00:00') as tiempo_formatted
          FROM inscripciones i
          JOIN usuarios u ON i.id_usuario = u.id
          WHERE i.id_evento = ? 
          AND i.estado = 'confirmada'
          AND i.tiempo_final IS NOT NULL
          ORDER BY i.tiempo_final ASC";
$stmt = $db->prepare($query);
$stmt->bind_param("i", $event_id);
$stmt->execute();
$resultados = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Obtener estadísticas
$total_participantes = count($resultados);
$hombres = 0;
$mujeres = 0;

foreach ($resultados as $resultado) {
    if ($resultado['genero'] === 'M') $hombres++;
    if ($resultado['genero'] === 'F') $mujeres++;
}

include '../../includes/header.php';
?>

<div class="container">
    <!-- Breadcrumb -->
    <div style="margin-bottom: 2rem;">
        <a href="view.php?id=<?php echo $event_id; ?>" style="color: var(--primary-color); text-decoration: none;">
            <i class="fas fa-arrow-left"></i> Volver al evento
        </a>
    </div>
    
    <div style="
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        color: white;
        border-radius: var(--border-radius);
        padding: 3rem 2rem;
        margin-bottom: 2rem;
        text-align: center;
    ">
        <h1 style="margin-bottom: 0.5rem; font-size: 2.5rem;">
            <i class="fas fa-flag-checkered"></i> Resultados
        </h1>
        <h2 style="margin-bottom: 1rem; opacity: 0.9;">
            <?php echo htmlspecialchars($evento['nombre']); ?>
        </h2>
        <p style="font-size: 1.1rem; opacity: 0.8;">
            <i class="fas fa-calendar-alt"></i> 
            <?php echo date('d/m/Y', strtotime($evento['fecha_evento'])); ?> 
            | 
            <i class="fas fa-route"></i> 
            <?php echo $evento['distancia']; ?> km
        </p>
    </div>
    
    <?php if ($total_participantes > 0): ?>
    
    <!-- Estadísticas -->
    <div style="
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    ">
        <div style="
            background: white;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            text-align: center;
        ">
            <div style="
                background: var(--primary-light);
                color: white;
                width: 60px;
                height: 60px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 1rem;
                font-size: 1.5rem;
            ">
                <i class="fas fa-users"></i>
            </div>
            <h3 style="color: var(--text-dark); font-size: 2rem; margin-bottom: 0.5rem;">
                <?php echo $total_participantes; ?>
            </h3>
            <p style="color: var(--text-light);">Finalistas</p>
        </div>
        
        <div style="
            background: white;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            text-align: center;
        ">
            <div style="
                background: #2196f3;
                color: white;
                width: 60px;
                height: 60px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 1rem;
                font-size: 1.5rem;
            ">
                <i class="fas fa-male"></i>
            </div>
            <h3 style="color: var(--text-dark); font-size: 2rem; margin-bottom: 0.5rem;">
                <?php echo $hombres; ?>
            </h3>
            <p style="color: var(--text-light);">Hombres</p>
        </div>
        
        <div style="
            background: white;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            text-align: center;
        ">
            <div style="
                background: #e91e63;
                color: white;
                width: 60px;
                height: 60px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 1rem;
                font-size: 1.5rem;
            ">
                <i class="fas fa-female"></i>
            </div>
            <h3 style="color: var(--text-dark); font-size: 2rem; margin-bottom: 0.5rem;">
                <?php echo $mujeres; ?>
            </h3>
            <p style="color: var(--text-light);">Mujeres</p>
        </div>
        
        <div style="
            background: white;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            text-align: center;
        ">
            <div style="
                background: var(--success);
                color: white;
                width: 60px;
                height: 60px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 1rem;
                font-size: 1.5rem;
            ">
                <i class="fas fa-trophy"></i>
            </div>
            <h3 style="color: var(--text-dark); font-size: 2rem; margin-bottom: 0.5rem;">
                <?php echo $total_participantes > 0 ? formatTime($resultados[0]['tiempo_formatted']) : '--:--'; ?>
            </h3>
            <p style="color: var(--text-light);">Mejor tiempo</p>
        </div>
    </div>
    
    <!-- Tabla de Resultados -->
    <div style="
        background: white;
        border-radius: var(--border-radius);
        padding: 2rem;
        box-shadow: var(--box-shadow);
        margin-bottom: 2rem;
    ">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h2 style="color: var(--primary-color);">
                <i class="fas fa-list-ol"></i> Clasificación General
            </h2>
            <div>
                <button onclick="exportResults()" class="btn btn-primary">
                    <i class="fas fa-download"></i> Exportar Resultados
                </button>
            </div>
        </div>
        
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: var(--primary-color); color: white;">
                        <th style="padding: 1rem; text-align: center;">Posición</th>
                        <th style="padding: 1rem; text-align: left;">Corredor</th>
                        <th style="padding: 1rem; text-align: left;">Número</th>
                        <th style="padding: 1rem; text-align: left;">Categoría</th>
                        <th style="padding: 1rem; text-align: left;">Tiempo</th>
                        <th style="padding: 1rem; text-align: left;">Género</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($resultados as $index => $resultado): 
                        $position = $index + 1;
                        $row_class = '';
                        
                        // Estilos para podio
                        if ($position === 1) {
                            $row_class = 'first-place';
                            $medal_icon = '<i class="fas fa-trophy" style="color: #FFD700;"></i>';
                        } elseif ($position === 2) {
                            $row_class = 'second-place';
                            $medal_icon = '<i class="fas fa-medal" style="color: #C0C0C0;"></i>';
                        } elseif ($position === 3) {
                            $row_class = 'third-place';
                            $medal_icon = '<i class="fas fa-medal" style="color: #CD7F32;"></i>';
                        } else {
                            $medal_icon = $position;
                        }
                    ?>
                    <tr class="<?php echo $row_class; ?>" style="border-bottom: 1px solid #eee;">
                        <td style="padding: 1rem; text-align: center; font-weight: bold;">
                            <?php echo $medal_icon; ?>
                        </td>
                        <td style="padding: 1rem;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="
                                    width: 40px;
                                    height: 40px;
                                    background: var(--primary-light);
                                    border-radius: 50%;
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                    color: white;
                                    font-weight: bold;
                                ">
                                    <?php echo strtoupper(substr($resultado['nombre'], 0, 1) . substr($resultado['apellido'], 0, 1)); ?>
                                </div>
                                <div>
                                    <div style="font-weight: 500;"><?php echo htmlspecialchars($resultado['nombre'] . ' ' . $resultado['apellido']); ?></div>
                                    <div style="font-size: 0.9rem; color: var(--text-light);">
                                        #<?php echo $resultado['id']; ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td style="padding: 1rem;">
                            <span style="
                                background: var(--bg-light);
                                padding: 0.3rem 0.8rem;
                                border-radius: 20px;
                                font-family: monospace;
                                font-weight: bold;
                            ">
                                <?php echo htmlspecialchars($resultado['numero_corredor']); ?>
                            </span>
                        </td>
                        <td style="padding: 1rem;">
                            <?php 
                            $categoria_labels = [
                                'principiante' => ['label' => 'Principiante', 'color' => '#4CAF50'],
                                'intermedio' => ['label' => 'Intermedio', 'color' => '#2196F3'],
                                'avanzado' => ['label' => 'Avanzado', 'color' => '#FF9800'],
                                'elite' => ['label' => 'Élite', 'color' => '#F44336']
                            ];
                            $cat = $categoria_labels[$resultado['categoria']] ?? ['label' => 'No especificada', 'color' => '#9E9E9E'];
                            ?>
                            <span style="
                                background: <?php echo $cat['color']; ?>;
                                color: white;
                                padding: 0.3rem 0.8rem;
                                border-radius: 20px;
                                font-size: 0.9rem;
                            ">
                                <?php echo $cat['label']; ?>
                            </span>
                        </td>
                        <td style="padding: 1rem; font-family: monospace; font-weight: bold; font-size: 1.1rem;">
                            <?php echo formatTime($resultado['tiempo_formatted']); ?>
                        </td>
                        <td style="padding: 1rem;">
                            <?php 
                            $gender_icon = $resultado['genero'] === 'M' ? 'fa-male' : ($resultado['genero'] === 'F' ? 'fa-female' : 'fa-user');
                            $gender_color = $resultado['genero'] === 'M' ? '#2196f3' : ($resultado['genero'] === 'F' ? '#e91e63' : '#9c27b0');
                            ?>
                            <i class="fas <?php echo $gender_icon; ?>" style="color: <?php echo $gender_color; ?>; font-size: 1.2rem;"></i>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($total_participantes === 0): ?>
        <div style="text-align: center; padding: 3rem; color: var(--text-light);">
            <i class="fas fa-clock" style="font-size: 3rem; margin-bottom: 1rem;"></i>
            <p style="font-size: 1.1rem;">Los resultados aún no están disponibles</p>
            <p>Vuelve más tarde para ver los tiempos oficiales</p>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Gráfico de Distribución -->
    <div style="
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 2rem;
        margin-bottom: 2rem;
    ">
        <div style="
            background: white;
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: var(--box-shadow);
        ">
            <h3 style="color: var(--primary-color); margin-bottom: 1.5rem;">
                <i class="fas fa-chart-pie"></i> Distribución por Categoría
            </h3>
            <div id="categoryChart" style="height: 300px;"></div>
        </div>
        
        <div style="
            background: white;
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: var(--box-shadow);
        ">
            <h3 style="color: var(--primary-color); margin-bottom: 1.5rem;">
                <i class="fas fa-chart-bar"></i> Tiempos por Género
            </h3>
            <div id="genderChart" style="height: 300px;"></div>
        </div>
    </div>
    
    <?php else: ?>
    
    <div style="
        background: white;
        border-radius: var(--border-radius);
        padding: 4rem 2rem;
        box-shadow: var(--box-shadow);
        text-align: center;
    ">
        <i class="fas fa-clock" style="font-size: 4rem; color: var(--text-light); margin-bottom: 1.5rem;"></i>
        <h2 style="color: var(--text-dark); margin-bottom: 1rem;">Resultados Pendientes</h2>
        <p style="color: var(--text-light); max-width: 600px; margin: 0 auto 2rem;">
            Los resultados oficiales de <?php echo htmlspecialchars($evento['nombre']); ?> aún no han sido publicados.
            Por favor, vuelve más tarde para ver los tiempos de los participantes.
        </p>
        <a href="view.php?id=<?php echo $event_id; ?>" class="btn btn-primary">
            <i class="fas fa-arrow-left"></i> Volver al Evento
        </a>
    </div>
    
    <?php endif; ?>
</div>

<style>
.first-place {
    background: linear-gradient(90deg, rgba(255, 215, 0, 0.1), rgba(255, 215, 0, 0.05));
    border-left: 4px solid #FFD700;
}

.second-place {
    background: linear-gradient(90deg, rgba(192, 192, 192, 0.1), rgba(192, 192, 192, 0.05));
    border-left: 4px solid #C0C0C0;
}

.third-place {
    background: linear-gradient(90deg, rgba(205, 127, 50, 0.1), rgba(205, 127, 50, 0.05));
    border-left: 4px solid #CD7F32;
}

/* Estilos para gráficos */
.chart-container {
    position: relative;
    height: 300px;
}

.chart-legend {
    display: flex;
    justify-content: center;
    gap: 1rem;
    margin-top: 1rem;
    flex-wrap: wrap;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
}

.legend-color {
    width: 15px;
    height: 15px;
    border-radius: 3px;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Función para formatear tiempo
function formatTime(timeString) {
    if (!timeString) return '--:--';
    
    // timeString está en formato HH:MM:SS o HH:MM:SS.microseconds
    const parts = timeString.split(':');
    if (parts.length < 3) return timeString;
    
    const hours = parseInt(parts[0]);
    const minutes = parseInt(parts[1]);
    const seconds = Math.floor(parseFloat(parts[2]));
    
    if (hours > 0) {
        return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    } else {
        return `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    }
}

// Exportar resultados
function exportResults() {
    const table = document.querySelector('table');
    const rows = table.querySelectorAll('tr');
    let csv = [];
    
    rows.forEach(row => {
        const cells = row.querySelectorAll('th, td');
        const rowData = [];
        
        cells.forEach((cell, index) => {
            // Saltar la columna de íconos
            if (index === 0) {
                // Extraer solo el número de posición
                const text = cell.textContent.trim();
                const position = text.match(/\d+/);
                rowData.push(position ? position[0] : text);
            } else {
                // Limpiar el texto (remover HTML)
                const text = cell.textContent.replace(/[ \n]+/g, ' ').trim();
                rowData.push(text);
            }
        });
        
        csv.push(rowData.join(','));
    });
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'resultados_' + <?php echo $event_id; ?> + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

// Gráficos
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($total_participantes > 0): ?>
    
    // Datos para gráficos
    const categories = {
        'principiante': 0,
        'intermedio': 0,
        'avanzado': 0,
        'elite': 0
    };
    
    const genderTimes = {
        'M': [],
        'F': []
    };
    
    <?php foreach ($resultados as $resultado): ?>
        <?php if (isset($categories[$resultado['categoria']])): ?>
            categories['<?php echo $resultado['categoria']; ?>']++;
        <?php endif; ?>
        
        <?php if ($resultado['genero'] === 'M' || $resultado['genero'] === 'F'): ?>
            genderTimes['<?php echo $resultado['genero']; ?>'].push(
                '<?php echo $resultado['tiempo_formatted']; ?>'
            );
        <?php endif; ?>
    <?php endforeach; ?>
    
    // Convertir tiempos a segundos
    function timeToSeconds(timeStr) {
        const parts = timeStr.split(':');
        if (parts.length !== 3) return 0;
        
        const hours = parseInt(parts[0]);
        const minutes = parseInt(parts[1]);
        const seconds = parseFloat(parts[2]);
        
        return hours * 3600 + minutes * 60 + seconds;
    }
    
    // Calcular tiempos promedio
    function calculateAverage(times) {
        if (times.length === 0) return 0;
        
        const totalSeconds = times.reduce((sum, time) => sum + timeToSeconds(time), 0);
        return totalSeconds / times.length;
    }
    
    const avgMaleTime = calculateAverage(genderTimes.M);
    const avgFemaleTime = calculateAverage(genderTimes.F);
    
    // Gráfico de categorías
    const categoryCtx = document.createElement('canvas');
    document.getElementById('categoryChart').appendChild(categoryCtx);
    
    new Chart(categoryCtx.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['Principiante', 'Intermedio', 'Avanzado', 'Élite'],
            datasets: [{
                data: [
                    categories.principiante,
                    categories.intermedio,
                    categories.avanzado,
                    categories.elite
                ],
                backgroundColor: [
                    '#4CAF50',
                    '#2196F3',
                    '#FF9800',
                    '#F44336'
                ],
                borderWidth: 2,
                borderColor: 'white'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        usePointStyle: true
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((value / total) * 100);
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
    
    // Gráfico de tiempos por género
    const genderCtx = document.createElement('canvas');
    document.getElementById('genderChart').appendChild(genderCtx);
    
    // Formatear segundos a MM:SS
    function formatSeconds(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    }
    
    new Chart(genderCtx.getContext('2d'), {
        type: 'bar',
        data: {
            labels: ['Hombres', 'Mujeres'],
            datasets: [{
                label: 'Tiempo promedio',
                data: [avgMaleTime, avgFemaleTime],
                backgroundColor: [
                    'rgba(33, 150, 243, 0.7)',
                    'rgba(233, 30, 99, 0.7)'
                ],
                borderColor: [
                    'rgb(33, 150, 243)',
                    'rgb(233, 30, 99)'
                ],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Tiempo (segundos)'
                    },
                    ticks: {
                        callback: function(value) {
                            return formatSeconds(value);
                        }
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `Tiempo promedio: ${formatSeconds(context.raw)}`;
                        }
                    }
                }
            }
        }
    });
    
    <?php endif; ?>
});
</script>

<?php 
// Función PHP para formatear tiempo
function formatTime($timeString) {
    if (!$timeString) return '--:--';
    
    $parts = explode(':', $timeString);
    if (count($parts) < 3) return $timeString;
    
    $hours = intval($parts[0]);
    $minutes = intval($parts[1]);
    $seconds = floor(floatval($parts[2]));
    
    if ($hours > 0) {
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    } else {
        return sprintf('%02d:%02d', $minutes, $seconds);
    }
}
?>

<?php include '../../includes/footer.php'; ?>