<?php
// Archivo: modules/admin/attendance-dashboard.php
require_once '../../config/config.php';
require_once '../../config/database.php';

// Verificar autenticación y permisos de administrador
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'organizador'])) {
    header('Location: ../auth/login.php');
    exit();
}

$db = getDB();

// Obtener eventos activos
$eventos = $db->query("SELECT id, nombre, fecha_evento FROM eventos WHERE estado = 'activo' ORDER BY fecha_evento ASC");

$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;

// Si no hay event_id, seleccionar el primero o el más cercano
if ($event_id === 0 && $eventos->num_rows > 0) {
    $first_event = $eventos->fetch_assoc();
    $event_id = $first_event['id'];
    $eventos->data_seek(0); // Regresar el cursor al inicio para el dropdown
}

// Obtener estadísticas y lista de asistentes si hay un evento seleccionado
$asistentes = [];
$pendientes = [];
$total_inscritos = 0;
$total_asistencias = 0;

if ($event_id > 0) {
    // Estadísticas
    $query_stats = "SELECT COUNT(id) as inscritos, SUM(asistencia) as asistencias FROM inscripciones WHERE id_evento = ? AND estado = 'confirmada'";
    $stmt_stats = $db->prepare($query_stats);
    $stmt_stats->bind_param("i", $event_id);
    $stmt_stats->execute();
    $stats_result = $stmt_stats->get_result()->fetch_assoc();
    $total_inscritos = $stats_result['inscritos'];
    $total_asistencias = $stats_result['asistencias'] ?? 0;

    // Lista de Asistidos
    $query = "
        SELECT i.numero_corredor, i.categoria, i.talla_camiseta, i.fecha_asistencia,
               COALESCE(i.nombre, u.nombre) as nombre,
               COALESCE(i.apellido, u.apellido) as apellido
        FROM inscripciones i
        LEFT JOIN usuarios u ON i.id_usuario = u.id
        WHERE i.id_evento = ? AND i.asistencia = 1
        ORDER BY i.fecha_asistencia DESC
    ";
    $stmt = $db->prepare($query);
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $asistentes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Lista de Pendientes (inscritos confirmados que aún no asisten)
    $query_pendientes = "
        SELECT i.numero_corredor, i.categoria, i.talla_camiseta,
               COALESCE(i.nombre, u.nombre) as nombre,
               COALESCE(i.apellido, u.apellido) as apellido
        FROM inscripciones i
        LEFT JOIN usuarios u ON i.id_usuario = u.id
        WHERE i.id_evento = ? AND i.estado = 'confirmada' AND (i.asistencia = 0 OR i.asistencia IS NULL)
        ORDER BY i.numero_corredor ASC
    ";
    $stmt_pendientes = $db->prepare($query_pendientes);
    $stmt_pendientes->bind_param("i", $event_id);
    $stmt_pendientes->execute();
    $pendientes = $stmt_pendientes->get_result()->fetch_all(MYSQLI_ASSOC);
}

include '../../includes/admin-header.php';
?>

<style>
    /* Optimizar el tamaño de las filas de las tablas para mostrar más registros */
    #attendanceTable th,
    #attendanceTable td,
    #pendingTable th,
    #pendingTable td {
        padding: 0.5rem 0.75rem;
        font-size: 0.9rem;
    }
    #attendanceTable td,
    #pendingTable td {
        vertical-align: middle;
        white-space: nowrap;
    }
    #attendanceTable td strong,
    #pendingTable td strong {
        font-weight: 600;
    }
    #attendanceTable td .badge,
    #pendingTable td .badge {
        padding: 0.2rem 0.5rem;
        font-size: 0.75rem;
    }
    #attendanceTable td .event-time,
    #pendingTable td .event-time {
        font-size: 0.85rem;
    }
    #attendanceTable td .event-details,
    #pendingTable td .event-details {
        font-size: 0.85rem;
    }
    .table-scroll-container {
        max-height: 520px;
        overflow-y: auto;
    }
</style>

<div class="admin-main">
    <div class="admin-header">
        <h1><i class="fas fa-clipboard-list"></i> Panel de Asistencia</h1>
    </div>

    <div class="dashboard-grid" style="grid-template-columns: 1fr 3fr; gap: 2rem;">
        
        <!-- Columna Izquierda: Escáner y Stats -->
        <div>
            <!-- Selección de Evento -->
            <div class="admin-card" style="margin-bottom: 1.5rem;">
                <div class="admin-card-header">
                    <h2><i class="fas fa-filter"></i> Seleccionar Evento</h2>
                </div>
                <div class="admin-card-body">
                    <form action="" method="GET" id="eventSelectForm">
                        <div class="form-group" style="margin-bottom: 0;">
                            <select name="event_id" class="form-control" onchange="document.getElementById('eventSelectForm').submit();">
                                <?php if ($eventos->num_rows == 0): ?>
                                    <option value="">No hay eventos activos</option>
                                <?php endif; ?>
                                <?php foreach($eventos as $ev): ?>
                                    <option value="<?php echo $ev['id']; ?>" <?php echo $event_id == $ev['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($ev['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Panel de Escáner -->
            <?php if ($event_id > 0): ?>
            <div class="admin-card" style="margin-bottom: 1.5rem;">
                <div class="admin-card-header" style="background-color: #0d6efd; color: white;">
                    <h2 style="color: white; margin: 0;"><i class="fas fa-qrcode"></i> Lector QR</h2>
                </div>
                <div class="admin-card-body">
                    <p style="font-size: 0.9rem; color: #6c757d; margin-bottom: 1rem;">
                        Asegúrate de que el cursor esté dentro del cuadro de texto antes de escanear con la pistola QR.
                    </p>
                    <div class="form-group" style="position: relative;">
                        <input type="text" id="scannerInput" class="form-control form-control-lg" placeholder="Escanea aquí..." autofocus autocomplete="off" style="padding-left: 2.5rem;">
                        <i class="fas fa-barcode" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #adb5bd;"></i>
                    </div>
                    <div id="scanStatus" style="display: none; padding: 1rem; border-radius: 5px; margin-top: 1rem; font-weight: bold; text-align: center;">
                        <!-- Mensaje de estado -->
                    </div>
                </div>
            </div>

            <!-- Estadísticas Básicas -->
            <div class="stat-card" style="margin-bottom: 0;">
                <div class="stat-icon stat-icon-success">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-number" id="attendanceCount"><?php echo number_format($total_asistencias); ?> <span style="font-size: 1rem; color: #6c757d;">/ <?php echo number_format($total_inscritos); ?></span></div>
                <div class="stat-label">Asistencias Confirmadas</div>
                <div class="capacity-bar" style="margin-top: 1rem;">
                    <?php $porcentaje = $total_inscritos > 0 ? ($total_asistencias / $total_inscritos) * 100 : 0; ?>
                    <div class="capacity-fill" id="attendanceProgress" style="width: <?php echo $porcentaje; ?>%; background: #28a745;"></div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Columna Derecha: Tabla de Asistencias -->
        <div>
            <div class="admin-card">
                <div class="admin-card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h2><i class="fas fa-list"></i> Asistentes Recientes <span class="badge badge-primary" id="recentCountBadge" style="margin-left: 0.5rem;"><?php echo count($asistentes); ?></span></h2>
                    <button class="admin-btn admin-btn-outline" onclick="location.reload();">
                        <i class="fas fa-sync-alt"></i> Actualizar
                    </button>
                </div>
                
                <?php if ($event_id > 0): ?>
                    <div class="table-responsive table-scroll-container">
                        <table class="admin-table" id="attendanceTable">
                            <thead>
                                <tr>
                                    <th># Corredor</th>
                                    <th>Nombre</th>
                                    <th>Categoría / Talla</th>
                                    <th>Hora de Registro</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($asistentes)): ?>
                                    <tr id="emptyRow">
                                        <td colspan="4" style="text-align: center; padding: 2rem;">
                                            No hay asistencias registradas para este evento aún.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($asistentes as $p): ?>
                                        <tr>
                                            <td><span class="badge badge-primary"><?php echo htmlspecialchars($p['numero_corredor']); ?></span></td>
                                            <td><strong><?php echo htmlspecialchars($p['nombre'] . ' ' . $p['apellido']); ?></strong></td>
                                            <td>
                                                <small class="event-details">
                                                    <?php echo htmlspecialchars(($p['categoria'] ?? 'N/A') . ' / ' . ($p['talla_camiseta'] ?? 'N/A')); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="event-time">
                                                    <i class="fas fa-clock"></i> <?php echo date('h:i:s A', strtotime($p['fecha_asistencia'])); ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <h3>Selecciona un evento</h3>
                        <p>Por favor selecciona un evento de la lista para ver o registrar asistencias.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tarjeta de Asistencias Pendientes -->
            <div class="admin-card" style="margin-top: 1.5rem;">
                <div class="admin-card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h2><i class="fas fa-user-clock"></i> Asistencias Pendientes <span class="badge badge-warning" id="pendingCountBadge" style="margin-left: 0.5rem;"><?php echo count($pendientes); ?></span></h2>
                </div>
                
                <?php if ($event_id > 0): ?>
                    <div class="table-responsive table-scroll-container">
                        <table class="admin-table" id="pendingTable">
                            <thead>
                                <tr>
                                    <th># Corredor</th>
                                    <th>Nombre</th>
                                    <th>Categoría / Talla</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($pendientes)): ?>
                                    <tr id="emptyPendingRow">
                                        <td colspan="4" style="text-align: center; padding: 2rem;">
                                            No hay asistencias pendientes para este evento.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($pendientes as $p): ?>
                                        <tr id="pending-row-<?php echo htmlspecialchars($p['numero_corredor']); ?>">
                                            <td><span class="badge badge-primary"><?php echo htmlspecialchars($p['numero_corredor']); ?></span></td>
                                            <td><strong><?php echo htmlspecialchars($p['nombre'] . ' ' . $p['apellido']); ?></strong></td>
                                            <td>
                                                <small class="event-details">
                                                    <?php echo htmlspecialchars(($p['categoria'] ?? 'N/A') . ' / ' . ($p['talla_camiseta'] ?? 'N/A')); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <button class="admin-btn admin-btn-success btn-sm register-manual-btn" 
                                                        style="padding: 0.2rem 0.5rem; font-size: 0.8rem; border-radius: 4px;"
                                                        onclick="processScan('<?php echo htmlspecialchars($p['numero_corredor']); ?>');">
                                                    <i class="fas fa-check"></i> Registrar
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <h3>Selecciona un evento</h3>
                        <p>Por favor selecciona un evento de la lista para ver o registrar asistencias.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<?php if ($event_id > 0): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const scannerInput = document.getElementById('scannerInput');
    const scanStatus = document.getElementById('scanStatus');
    const attendanceTableBody = document.querySelector('#attendanceTable tbody');
    let isProcessing = false;
    let autoRefreshTimer = null;

    // Mantener el enfoque en el input cada vez que se hace clic fuera
    document.addEventListener('click', function(e) {
        if(e.target.tagName !== 'SELECT' && e.target.tagName !== 'BUTTON' && e.target.tagName !== 'A') {
            scannerInput.focus();
        }
    });

    scannerInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            
            if (isProcessing) return;
            
            const urlOrText = this.value.trim();
            this.value = ''; // Limpiar el input inmediatamente
            
            if (urlOrText === '') return;
            
            processScan(urlOrText);
        }
    });

    function processScan(scannedData) {
        isProcessing = true;
        
        // El escáner usualmente lee la URL completa:
        // Ej: https://fit5krd.com/modules/admin/attendance.php?eventid=5&runner=RUN50187
        
        let targetUrl = '';
        
        try {
            // Intentamos parsearlo como URL
            const urlObj = new URL(scannedData);
            
            // Verificamos si contiene 'attendance.php'
            if (urlObj.pathname.includes('attendance.php')) {
                // Evitamos problemas de CORS y sesión cruzada usando una ruta relativa
                // al servidor y directorio actual en lugar del dominio absoluto del QR.
                const eventId = urlObj.searchParams.get('eventid') || '<?php echo $event_id; ?>';
                const runner = urlObj.searchParams.get('runner') || '';
                targetUrl = `attendance.php?eventid=${eventId}&runner=${runner}&ajax=1`;
            } else {
                showStatus('QR Inválido o no pertenece a la asistencia', 'danger');
                isProcessing = false;
                return;
            }
        } catch (e) {
            // Si no es una URL, puede que hayan escaneado solo el ID (ej RUN1001)
            // Intentar procesarlo si asumen el event_id actual
            if (scannedData.startsWith('RUN')) {
                targetUrl = `attendance.php?eventid=<?php echo $event_id; ?>&runner=${scannedData}&ajax=1`;
            } else {
                showStatus('Formato de QR no reconocido', 'danger');
                isProcessing = false;
                return;
            }
        }

        // Mostrar estado de carga
        showStatus('<i class="fas fa-spinner fa-spin"></i> Procesando...', 'info');

        // Hacer la petición
        fetch(targetUrl, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                showStatus(`<i class="fas fa-check-circle"></i> ${data.message}`, 'success');
                playSound('success');
                addRunnerToTable(data.data);
                updateStats(true);
                removeRunnerFromPending(data.data.numero_corredor);
            } else if (data.status === 'warning') {
                showStatus(`<i class="fas fa-exclamation-triangle"></i> ${data.message}`, 'warning');
                playSound('warning');
            } else {
                showStatus(`<i class="fas fa-times-circle"></i> ${data.message}`, 'danger');
                playSound('error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showStatus('<i class="fas fa-exclamation-circle"></i> Error de conexión', 'danger');
            playSound('error');
        })
        .finally(() => {
            isProcessing = false;
            scannerInput.focus();
        });
    }

    function showStatus(html, type) {
        scanStatus.innerHTML = html;
        scanStatus.style.display = 'block';
        
        // Colores
        scanStatus.style.backgroundColor = type === 'success' ? '#d4edda' : 
                                           type === 'warning' ? '#fff3cd' : 
                                           type === 'danger' ? '#f8d7da' : '#e2e3e5';
        scanStatus.style.color = type === 'success' ? '#155724' : 
                                 type === 'warning' ? '#856404' : 
                                 type === 'danger' ? '#721c24' : '#383d41';
        scanStatus.style.border = `1px solid ${
            type === 'success' ? '#c3e6cb' : 
            type === 'warning' ? '#ffeeba' : 
            type === 'danger' ? '#f5c6cb' : '#d6d8db'
        }`;

        // Ocultar después de 4 segundos
        clearTimeout(window.statusTimer);
        window.statusTimer = setTimeout(() => {
            scanStatus.style.display = 'none';
        }, 4000);
    }

    function addRunnerToTable(runner) {
        // Remover mensaje de "vacío" si existe
        const emptyRow = document.getElementById('emptyRow');
        if (emptyRow) {
            emptyRow.remove();
        }

        // Actualizar el contador de asistentes recientes
        const recentBadge = document.getElementById('recentCountBadge');
        if (recentBadge) {
            let count = parseInt(recentBadge.textContent) || 0;
            recentBadge.textContent = count + 1;
        }

        // Crear nueva fila
        const tr = document.createElement('tr');
        tr.style.backgroundColor = '#e8f5e9'; // Fondo verde claro para destacar
        
        // Formatear hora local
        const dateObj = new Date(runner.fecha_asistencia.replace(' ', 'T'));
        const timeString = dateObj.toLocaleTimeString('es-DO', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });

        tr.innerHTML = `
            <td><span class="badge badge-primary">${runner.numero_corredor}</span></td>
            <td><strong>${runner.runner_name} ${runner.runner_lastname}</strong></td>
            <td>
                <small class="event-details">
                    ${runner.categoria || 'N/A'} / ${runner.talla_camiseta || 'N/A'}
                </small>
            </td>
            <td>
                <div class="event-time">
                    <i class="fas fa-clock"></i> ${timeString}
                </div>
            </td>
        `;

        // Prepend it to the table body
        attendanceTableBody.insertBefore(tr, attendanceTableBody.firstChild);

        // Remover el resaltado después de unos segundos
        setTimeout(() => {
            tr.style.transition = 'background-color 2s';
            tr.style.backgroundColor = '';
        }, 3000);
    }

    function updateStats(increment) {
        if (!increment) return;
        
        const countSpan = document.getElementById('attendanceCount');
        const progressFill = document.getElementById('attendanceProgress');
        
        let currentText = countSpan.innerHTML;
        let parts = currentText.split('/');
        
        let present = parseInt(parts[0].replace(/,/g, '').trim());
        let total = parseInt(parts[1].replace(/,/g, '').replace(/<[^>]*>/g, '').trim());
        
        present++;
        
        countSpan.innerHTML = `${present.toLocaleString()} <span style="font-size: 1rem; color: #6c757d;">/ ${total.toLocaleString()}</span>`;
        
        if (total > 0) {
            progressFill.style.width = ((present / total) * 100) + '%';
        }
    }

    // Efectos de sonido opcionales (basados en web audio api)
    function playSound(type) {
        try {
            const context = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = context.createOscillator();
            const gainNode = context.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(context.destination);
            
            if (type === 'success') {
                oscillator.type = 'sine';
                oscillator.frequency.setValueAtTime(800, context.currentTime); // Beep alto
                oscillator.frequency.exponentialRampToValueAtTime(1200, context.currentTime + 0.1);
                gainNode.gain.setValueAtTime(0.1, context.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, context.currentTime + 0.1);
                oscillator.start();
                oscillator.stop(context.currentTime + 0.1);
            } else if (type === 'error' || type === 'warning') {
                oscillator.type = 'sawtooth';
                oscillator.frequency.setValueAtTime(300, context.currentTime); // Beep grave
                oscillator.frequency.exponentialRampToValueAtTime(150, context.currentTime + 0.2);
                gainNode.gain.setValueAtTime(0.1, context.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, context.currentTime + 0.2);
                oscillator.start();
                oscillator.stop(context.currentTime + 0.2);
            }
        } catch(e) {
            console.log("Audio no soportado");
        }
    }

    function removeRunnerFromPending(runnerNum) {
        const row = document.getElementById(`pending-row-${runnerNum}`);
        if (row) {
            row.remove();
            
            // Actualizar el contador de pendientes
            const pendingBadge = document.getElementById('pendingCountBadge');
            if (pendingBadge) {
                let count = parseInt(pendingBadge.textContent) || 0;
                if (count > 0) {
                    pendingBadge.textContent = count - 1;
                }
            }
        }
        
        // Verificar si la tabla de pendientes quedó vacía
        const pendingTableBody = document.querySelector('#pendingTable tbody');
        if (pendingTableBody && pendingTableBody.querySelectorAll('tr:not(#emptyPendingRow)').length === 0) {
            // Eliminar cualquier fila existente (por si acaso)
            pendingTableBody.innerHTML = '';
            
            const emptyTr = document.createElement('tr');
            emptyTr.id = 'emptyPendingRow';
            emptyTr.innerHTML = `
                <td colspan="4" style="text-align: center; padding: 2rem;">
                    No hay asistencias pendientes para este evento.
                </td>
            `;
            pendingTableBody.appendChild(emptyTr);
        }
    }

    // Exponer processScan globalmente para que sea invocable por los botones manuales
    window.processScan = processScan;
});
</script>
<?php endif; ?>

<?php include '../../includes/admin-footer.php'; ?>
