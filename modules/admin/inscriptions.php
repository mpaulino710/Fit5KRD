<?php
// modules/admin/inscriptions.php
require_once '../../config/config.php';
require_once '../../config/database.php';


// session_start(); removed as it's handled in config.php
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'organizador'])) {
    header('Location: ../auth/login.php');
    exit();
}

$db = getDB();

// Variables para filtros y paginación
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';

// Obtener el evento activo por defecto (el último creado de los activos)
$default_evento_id = 0;
$active_event_result = $db->query("SELECT id FROM eventos WHERE estado = 'activo' ORDER BY fecha_creacion DESC LIMIT 1");
if ($active_event_result && $active_event_result->num_rows > 0) {
    $default_evento_id = intval($active_event_result->fetch_assoc()['id']);
}
$filtro_evento = isset($_GET['evento']) ? intval($_GET['evento']) : $default_evento_id;
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$por_pagina = 15;
$offset = ($pagina - 1) * $por_pagina;

// Construir consulta base
$query = "SELECT SQL_CALC_FOUND_ROWS
          i.*,
          u.nombre as usuario_nombre,
          u.apellido as usuario_apellido,
          u.email as usuario_email,
          u.telefono as usuario_telefono,
          e.nombre as evento_nombre,
          e.fecha_evento,
          e.ubicacion as evento_ubicacion,
          e.distancia as evento_distancia,
          i_main.id_usuario as parent_usuario_id,
          u_main.nombre as parent_usuario_nombre,
          u_main.apellido as parent_usuario_apellido,
          p.estado as estado_pago
          FROM inscripciones i
          LEFT JOIN usuarios u ON i.id_usuario = u.id
          JOIN eventos e ON i.id_evento = e.id
          LEFT JOIN inscripciones i_main ON i.parent_id = i_main.id
          LEFT JOIN usuarios u_main ON i_main.id_usuario = u_main.id
          LEFT JOIN pagos p ON p.id_inscripcion = i.id
          WHERE 1=1";

$params = [];
$types = '';

if ($filtro_estado) {
    $query .= " AND i.estado = ?";
    $params[] = $filtro_estado;
    $types .= 's';
}

if ($filtro_evento) {
    $query .= " AND i.id_evento = ?";
    $params[] = $filtro_evento;
    $types .= 'i';
}

if ($busqueda) {
    $query .= " AND (u.nombre LIKE ? OR u.apellido LIKE ? OR u.email LIKE ? OR e.nombre LIKE ? OR i.numero_corredor LIKE ? OR i.nombre LIKE ? OR i.apellido LIKE ? OR u_main.nombre LIKE ? OR u_main.apellido LIKE ?)";
    $busqueda_like = "%$busqueda%";
    $params[] = $busqueda_like;
    $params[] = $busqueda_like;
    $params[] = $busqueda_like;
    $params[] = $busqueda_like;
    $params[] = $busqueda_like;
    $params[] = $busqueda_like;
    $params[] = $busqueda_like;
    $params[] = $busqueda_like;
    $params[] = $busqueda_like;
    $types .= 'sssssssss';
}

$query .= " ORDER BY i.fecha_inscripcion DESC LIMIT ? OFFSET ?";
$params[] = $por_pagina;
$params[] = $offset;
$types .= 'ii';

// Preparar consulta
$stmt = $db->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$inscripciones = $result->fetch_all(MYSQLI_ASSOC);

// Obtener total de resultados
$total_resultados = $db->query("SELECT FOUND_ROWS()")->fetch_row()[0];
$total_paginas = ceil($total_resultados / $por_pagina);

$stmt->close();

// Obtener lista de eventos para filtro
$eventos_query = "SELECT id, nombre FROM eventos WHERE fecha_evento > NOW() ORDER BY fecha_evento DESC";
$eventos = $db->query($eventos_query)->fetch_all(MYSQLI_ASSOC);

// Obtener estadísticas
$stats_query = "SELECT
    COUNT(*) as total,
    SUM(CASE WHEN estado = 'confirmada' THEN 1 ELSE 0 END) as confirmadas,
    SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
    SUM(CASE WHEN estado = 'cancelada' THEN 1 ELSE 0 END) as canceladas,
    SUM(CASE WHEN estado = 'ausente' THEN 1 ELSE 0 END) as ausentes
FROM inscripciones";

$stats = $db->query($stats_query)->fetch_assoc();

// Procesar acciones
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $inscripcion_id = $_POST['inscripcion_id'] ?? 0;

    if ($action === 'cambiar_estado' && $inscripcion_id) {
        $nuevo_estado = $_POST['nuevo_estado'];

        $stmt = $db->prepare("UPDATE inscripciones SET estado = ? WHERE id = ?");
        $stmt->bind_param("si", $nuevo_estado, $inscripcion_id);

        if ($stmt->execute()) {
            $success = 'Estado de la inscripción actualizado correctamente';
        } else {
            $error = 'Error al actualizar el estado';
        }
        $stmt->close();
    }

    elseif ($action === 'eliminar_inscripcion' && $inscripcion_id) {
        $stmt = $db->prepare("DELETE FROM inscripciones WHERE id = ?");
        $stmt->bind_param("i", $inscripcion_id);

        if ($stmt->execute()) {
            $success = 'Inscripción eliminada correctamente';
        } else {
            $error = 'Error al eliminar la inscripción';
        }
        $stmt->close();
    }

    // Recargar la página para mostrar cambios
    header("Location: inscriptions.php?page=$pagina" .
           ($filtro_estado ? "&estado=$filtro_estado" : "") .
           ("&evento=$filtro_evento") .
           ($busqueda ? "&busqueda=$busqueda" : ""));
    exit();
}

include '../../includes/admin-header.php';
?>

<div class="admin-main">
    <!-- Header -->
    <div class="admin-header">
        <h1><i class="fas fa-running"></i> Gestión de Inscripciones</h1>
        <div class="admin-header-actions">
        </div>
    </div>

    <!-- Notificaciones -->
    <?php if ($error): ?>
    <div class="admin-alert admin-alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <span><?php echo $error; ?></span>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="admin-alert admin-alert-success">
        <i class="fas fa-check-circle"></i>
        <span><?php echo $success; ?></span>
    </div>
    <?php endif; ?>

    <!-- Estadísticas -->
    <div class="admin-stats">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-running"></i>
            </div>
            <div class="stat-number"><?php echo number_format($stats['total']); ?></div>
            <div class="stat-label">Total Inscripciones</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon stat-icon-success">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-number"><?php echo number_format($stats['confirmadas']); ?></div>
            <div class="stat-label">Confirmadas</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon stat-icon-warning">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-number"><?php echo number_format($stats['pendientes']); ?></div>
            <div class="stat-label">Pendientes</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon stat-icon-danger">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="stat-number"><?php echo number_format($stats['canceladas']); ?></div>
            <div class="stat-label">Canceladas</div>
        </div>
    </div>

    <!-- Filtros y Búsqueda -->
    <div class="admin-filters">
        <form method="GET" action="">
            <div class="filter-row">
                <div class="form-group">
                    <label for="busqueda"><i class="fas fa-search"></i> Buscar</label>
                    <input type="text" id="busqueda" name="busqueda" class="form-control"
                           placeholder="Nombre, email o número de corredor"
                           value="<?php echo htmlspecialchars($busqueda); ?>">
                </div>

                <div class="form-group">
                    <label for="estado"><i class="fas fa-filter"></i> Estado</label>
                    <select id="estado" name="estado" class="form-control">
                        <option value="">Todos los estados</option>
                        <option value="confirmada" <?php echo $filtro_estado === 'confirmada' ? 'selected' : ''; ?>>Confirmada</option>
                        <option value="pendiente" <?php echo $filtro_estado === 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                        <option value="cancelada" <?php echo $filtro_estado === 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                        <option value="ausente" <?php echo $filtro_estado === 'ausente' ? 'selected' : ''; ?>>Ausente</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="evento"><i class="fas fa-calendar-alt"></i> Evento</label>
                    <select id="evento" name="evento" class="form-control">
                        <option value="0">Todos los eventos</option>
                        <?php foreach ($eventos as $evento): ?>
                        <option value="<?php echo $evento['id']; ?>" <?php echo $filtro_evento == $evento['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($evento['nombre']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <button type="submit" class="admin-btn admin-btn-primary">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                    <a href="inscriptions.php" class="admin-btn admin-btn-secondary">
                        <i class="fas fa-redo"></i> Limpiar
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Tabla de Inscripciones -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h2><i class="fas fa-list"></i> Lista de Inscripciones</h2>
            <span class="badge badge-primary">
                <?php echo number_format($total_resultados); ?> inscripciones encontradas
            </span>
        </div>

        <?php if (!empty($inscripciones)): ?>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Corredor</th>
                        <th>Evento</th>
                        <th>Fecha Inscripción</th>
                        <th>Talla</th>
                        <th>N° Corredor</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inscripciones as $inscripcion):
                        $fecha_inscripcion = strtotime($inscripcion['fecha_inscripcion']);
                        $fecha_evento = strtotime($inscripcion['fecha_evento']);

                        $estado_colors = [
                            'confirmada' => 'success',
                            'pendiente' => 'warning',
                            'cancelada' => 'danger',
                            'ausente' => 'secondary'
                        ];
                        $color = $estado_colors[$inscripcion['estado']] ?? 'secondary';
                    ?>
                    <tr>
                        <td><strong>#<?php echo $inscripcion['id']; ?></strong></td>
                        <td>
                            <div class="user-info">
                                <div class="user-avatar-small">
                                    <?php 
                                        $nombre_mostrar = $inscripcion['usuario_nombre'] ? ($inscripcion['usuario_nombre'] . ' ' . $inscripcion['usuario_apellido']) : ($inscripcion['nombre'] . ' ' . $inscripcion['apellido']);
                                        
                                        if ($inscripcion['usuario_email']) {
                                            $email_mostrar = $inscripcion['usuario_email'];
                                        } else {
                                            if ($inscripcion['parent_usuario_nombre']) {
                                                $email_mostrar = 'Invitado -> ' . $inscripcion['parent_usuario_nombre'] . ' ' . $inscripcion['parent_usuario_apellido'];
                                            } else {
                                                $email_mostrar = 'Invitado';
                                            }
                                        }
                                        
                                        // Asegurar iniciales incluso si nombre es empty
                                        $iniciales = 'INV';
                                        if (strlen($nombre_mostrar) >= 2) {
                                            $parts = explode(' ', trim($nombre_mostrar));
                                            if (count($parts) >= 2) {
                                                $iniciales = strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
                                            } else {
                                                $iniciales = strtoupper(substr($parts[0], 0, 2));
                                            }
                                        } elseif (strlen($nombre_mostrar) == 1) {
                                            $iniciales = strtoupper($nombre_mostrar);
                                        }
                                    
                                        echo $iniciales; 
                                    ?>
                                </div>
                                <div>
                                    <strong><?php echo htmlspecialchars($nombre_mostrar); ?></strong><br>
                                    <small class="user-email"><?php echo htmlspecialchars($email_mostrar); ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($inscripcion['evento_nombre']); ?></strong><br>
                            <small class="event-location"><?php echo htmlspecialchars($inscripcion['evento_ubicacion']); ?></small><br>
                            <small class="event-time"><?php echo date('d/m/Y H:i', $fecha_evento); ?></small>
                        </td>
                        <td>
                            <div class="registration-date"><?php echo date('d/m/Y', $fecha_inscripcion); ?></div>
                            <small class="registration-days"><?php echo date('h:i A', $fecha_inscripcion); ?></small>
                        </td>
                        <td>
                            <?php if (!empty($inscripcion['talla_camiseta'])): ?>
                                <strong><?php echo htmlspecialchars($inscripcion['talla_camiseta']); ?></strong>
                            <?php else: ?>
                                <span class="text-muted"><small>N/A</small></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($inscripcion['numero_corredor']): ?>
                            <span class="badge badge-primary runner-number">
                                <?php echo $inscripcion['numero_corredor']; ?>
                            </span>
                            <?php else: ?>
                            <span class="badge badge-secondary">Pendiente</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo $color; ?>">
                                <?php echo ucfirst($inscripcion['estado']); ?>
                            </span>

                            <form method="POST" action="" class="status-form">
                                <input type="hidden" name="action" value="cambiar_estado">
                                <input type="hidden" name="inscripcion_id" value="<?php echo $inscripcion['id']; ?>">
                                <select name="nuevo_estado" class="form-control form-control-sm"
                                        onchange="if(confirm('¿Cambiar estado de esta inscripción?')) this.form.submit()">
                                    <option value="confirmada" <?php echo $inscripcion['estado'] == 'confirmada' ? 'selected' : ''; ?>>Confirmada</option>
                                    <option value="pendiente" <?php echo $inscripcion['estado'] == 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                                    <option value="cancelada" <?php echo $inscripcion['estado'] == 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                                    <option value="ausente" <?php echo $inscripcion['estado'] == 'ausente' ? 'selected' : ''; ?>>Ausente</option>
                                </select>
                            </form>
                        </td>
                        <td>
                            <div class="table-actions">
                                <a href="view-inscription.php?id=<?php echo $inscripcion['id']; ?>"
                                   class="admin-btn admin-btn-outline action-btn" title="Ver detalles">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if (isset($inscripcion['estado_pago']) && $inscripcion['estado_pago'] === 'completado'): 
                                    $qrData = [
                                        'evento' => $inscripcion['evento_nombre'],
                                        'fecha' => date('d/m/Y h:i A', strtotime($inscripcion['fecha_evento'])),
                                        'ubicacion' => $inscripcion['evento_ubicacion'],
                                        'corredor' => $nombre_mostrar,
                                        'dorsal' => $inscripcion['numero_corredor'] ?: 'Pendiente',
                                        'ticket_id' => $inscripcion['id']
                                    ];
                                    
                                    // Asegurar que todos los datos sean UTF-8 válido para evitar que json_encode falle
                                    $qrDataSafe = [];
                                    foreach ($qrData as $k => $v) {
                                        $v_str = (string)$v;
                                        if (!mb_check_encoding($v_str, 'UTF-8')) {
                                            $v_str = mb_convert_encoding($v_str, 'UTF-8', 'ISO-8859-1');
                                        }
                                        $qrDataSafe[$k] = $v_str;
                                    }
                                    
                                    $jsonAttr = htmlspecialchars(json_encode($qrDataSafe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
                                ?>
                                <button type="button" class="admin-btn admin-btn-primary action-btn" title="Ver QR" onclick="showQR(<?php echo $jsonAttr; ?>)">
                                    <i class="fas fa-qrcode"></i>
                                </button>
                                <?php endif; ?>
                                
                                <?php
                                // Preparar datos del dorsal
                                $dorsalData = [
                                    'runner_id' => $inscripcion['id'],
                                    'event_id' => $inscripcion['id_evento'],
                                    'runner_number' => $inscripcion['numero_corredor'] ?: 'PENDIENTE',
                                    'name' => mb_strtoupper($nombre_mostrar, 'UTF-8'),
                                    'category' => $inscripcion['modalidad'] ?: ($inscripcion['categoria'] ?: ((isset($inscripcion['evento_distancia']) && $inscripcion['evento_distancia'] ? 'Corre ' . floatval($inscripcion['evento_distancia']) . 'K' : 'General'))),
                                    'phone' => $inscripcion['telefono'] ?: ($inscripcion['usuario_telefono'] ?: 'N/A'),
                                    'emergency_contact' => $inscripcion['contacto_emergencia'] ?: 'N/A',
                                    'emergency_phone' => $inscripcion['telefono_emergencia'] ?: ''
                                ];
                                
                                $dorsalDataSafe = [];
                                foreach ($dorsalData as $k => $v) {
                                    $v_str = (string)$v;
                                    if (!mb_check_encoding($v_str, 'UTF-8')) {
                                        $v_str = mb_convert_encoding($v_str, 'UTF-8', 'ISO-8859-1');
                                    }
                                    $dorsalDataSafe[$k] = $v_str;
                                }
                                
                                $dorsalJsonAttr = htmlspecialchars(json_encode($dorsalDataSafe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
                                ?>
                                <button type="button" class="admin-btn admin-btn-success action-btn" title="Descargar Dorsal" onclick="downloadDorsal(<?php echo $dorsalJsonAttr; ?>)">
                                    <i class="fas fa-id-badge"></i>
                                </button>

                                <?php if (isset($inscripcion['asistencia']) && $inscripcion['asistencia'] == 1): ?>
                                <a href="generate-certificate.php?id=<?php echo $inscripcion['id']; ?>&download=1"
                                   class="admin-btn admin-btn-warning action-btn" title="Descargar Certificado" target="_blank">
                                    <i class="fas fa-certificate"></i>
                                </a>
                                <?php endif; ?>

                                <a href="edit-inscription.php?id=<?php echo $inscripcion['id']; ?>"
                                   class="admin-btn admin-btn-secondary action-btn" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form method="POST" action="" class="action-form"
                                      onsubmit="return confirm('¿Estás seguro de eliminar esta inscripción?');">
                                    <input type="hidden" name="action" value="eliminar_inscripcion">
                                    <input type="hidden" name="inscripcion_id" value="<?php echo $inscripcion['id']; ?>">
                                    <button type="submit" class="admin-btn admin-btn-danger action-btn" title="Eliminar">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        <?php if ($total_paginas > 1): ?>
        <div class="admin-pagination">
            <?php if ($pagina > 1): ?>
            <a href="?pagina=<?php echo $pagina - 1; ?>&estado=<?php echo $filtro_estado; ?>&evento=<?php echo $filtro_evento; ?>&busqueda=<?php echo urlencode($busqueda); ?>"
               class="pagination-link">
                <i class="fas fa-chevron-left"></i>
            </a>
            <?php endif; ?>

            <?php
            $inicio = max(1, $pagina - 2);
            $fin = min($total_paginas, $pagina + 2);

            for ($i = $inicio; $i <= $fin; $i++):
            ?>
            <a href="?pagina=<?php echo $i; ?>&estado=<?php echo $filtro_estado; ?>&evento=<?php echo $filtro_evento; ?>&busqueda=<?php echo urlencode($busqueda); ?>"
               class="pagination-link <?php echo $i == $pagina ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>

            <?php if ($pagina < $total_paginas): ?>
            <a href="?pagina=<?php echo $pagina + 1; ?>&estado=<?php echo $filtro_estado; ?>&evento=<?php echo $filtro_evento; ?>&busqueda=<?php echo urlencode($busqueda); ?>"
               class="pagination-link">
                <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="table-footer">
            Mostrando <?php echo count($inscripciones); ?> de <?php echo $total_resultados; ?> inscripciones
        </div>

        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-running"></i>
            <h3>No se encontraron inscripciones</h3>
            <p>No hay inscripciones que coincidan con tu búsqueda</p>
            <a href="inscriptions.php" class="admin-btn admin-btn-primary">
                <i class="fas fa-redo"></i> Ver todas las inscripciones
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Exportar datos -->
    <?php $export_type = 'inscriptions'; include '../../includes/export-buttons.php'; ?>
</div>

<script>
// Auto-ocultar notificaciones después de 5 segundos
setTimeout(() => {
    document.querySelectorAll('.admin-alert').forEach(notification => {
        notification.style.display = 'none';
    });
}, 5000);

// Exportar inscripciones
function exportInscripciones() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'csv');

    window.location.href = 'export_inscriptions.php?' + params.toString();
}

// Búsqueda con Enter
document.getElementById('busqueda')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        this.form.submit();
    }
});
</script>

<!-- Estilos para Modal QR -->
<style>
    .qr-modal {
        display: none; 
        position: fixed; 
        z-index: 1000; 
        left: 0;
        top: 0;
        width: 100%; 
        height: 100%; 
        background-color: rgba(0,0,0,0.5); 
        backdrop-filter: blur(5px);
    }
    .qr-modal-content {
        background-color: #fefefe;
        margin: 5% auto; 
        padding: 0;
        border: none;
        width: 90%;
        max-width: 450px;
        border-radius: 12px;
        position: relative;
        animation: slideIn 0.3s;
        box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        overflow: hidden;
    }
    .qr-modal-header {
        background: var(--primary-color, #007bff);
        color: white;
        padding: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .qr-modal-header h2 {
        margin: 0;
        font-size: 1.2rem;
        color: white;
    }
    .qr-modal-body {
        padding: 25px;
        text-align: center;
    }
    .qr-modal-footer {
        padding: 20px;
        background: #f8f9fa;
        text-align: center;
        display: flex;
        gap: 15px;
        justify-content: center;
        border-top: 1px solid #eee;
    }
    .close-modal {
        color: white;
        font-size: 24px;
        font-weight: bold;
        cursor: pointer;
        opacity: 0.8;
        transition: opacity 0.2s;
        background: none;
        border: none;
        padding: 0;
    }
    .close-modal:hover {
        opacity: 1;
    }
    #qrcode {
        margin: 20px auto;
        display: flex;
        justify-content: center;
        padding: 15px;
        background: white;
        border-radius: 8px;
        border: 1px solid #eee;
    }
    .ticket-info {
        text-align: left;
        margin-bottom: 20px;
        border-left: 4px solid var(--primary-color, #007bff);
        padding: 15px;
        background: #f8f9fa;
        border-radius: 4px;
    }
    .ticket-info p {
        margin: 8px 0;
        font-size: 0.95rem;
        display: flex;
        align-items: flex-start;
        color: #333;
    }
    .ticket-info i {
        color: var(--primary-color, #007bff);
        width: 25px;
        margin-top: 3px;
    }
    .ticket-label {
        font-weight: 600;
        color: #333;
        margin-right: 5px;
    }
    @keyframes slideIn {
        from {top: -50px; opacity: 0;}
        to {top: 0; opacity: 1;}
    }
</style>

<!-- Modal QR -->
<div id="qrModal" class="qr-modal">
    <div class="qr-modal-content" id="printableArea">
        <div class="qr-modal-header">
            <h2 id="modalEventTitle">Ticket de Evento</h2>
            <button class="close-modal" onclick="closeQRModal()">&times;</button>
        </div>
        <div class="qr-modal-body">
            <div class="ticket-info">
                <p><i class="fas fa-running"></i> <span><span class="ticket-label">Corredor:</span> <span id="modalRunner"></span></span></p>
                <p><i class="fas fa-hashtag"></i> <span><span class="ticket-label">Dorsal:</span> <span id="modalDorsal"></span></span></p>
                <p><i class="fas fa-calendar"></i> <span><span class="ticket-label">Fecha:</span> <span id="modalDate"></span></span></p>
                <p><i class="fas fa-map-marker-alt"></i> <span><span class="ticket-label">Lugar:</span> <span id="modalLocation"></span></span></p>
            </div>
            
            <div id="qrcode"></div>
            <p class="text-muted small">Presenta este código al ingresar al evento.</p>
        </div>
        <div class="qr-modal-footer" data-html2canvas-ignore="true">
            <button class="btn btn-secondary" onclick="closeQRModal()">Cerrar</button>
            <button class="btn btn-primary" onclick="downloadPDF()">
                <i class="fas fa-file-pdf"></i> Descargar PDF
            </button>
        </div>
    </div>
</div>

<!-- Librerías para QR y PDF -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<script>
    let currentTicketData = null;

    function showQR(data) {
        currentTicketData = data;
        
        document.getElementById('modalEventTitle').textContent = data.evento;
        document.getElementById('modalRunner').textContent = data.corredor;
        document.getElementById('modalDorsal').textContent = data.dorsal;
        document.getElementById('modalDate').textContent = data.fecha;
        document.getElementById('modalLocation').textContent = data.ubicacion;
        
        const qrContainer = document.getElementById('qrcode');
        qrContainer.innerHTML = '';
        
        // Limpiar acentos y caracteres especiales solo para el contenido del QR
        // Esto evita el bug "code length overflow" de qrcode.js con caracteres extendidos
        const cleanName = data.corredor 
            ? data.corredor.normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/[^\x00-\x7F]/g, "") 
            : "";

        const qrContent = JSON.stringify({
            id: data.ticket_id,
            c: cleanName,
            d: data.dorsal
        });

        
        new QRCode(qrContainer, {
            text: qrContent,
            width: 180,
            height: 180,
            colorDark : "#000000",
            colorLight : "#ffffff",
            correctLevel : QRCode.CorrectLevel.L
        });
        
        document.getElementById('qrModal').style.display = 'block';
    }
    
    function closeQRModal() {
        document.getElementById('qrModal').style.display = 'none';
    }
    
    function downloadPDF() {
        const btn = document.querySelector('.qr-modal-footer .btn-primary');
        const originalText = btn ? btn.innerHTML : '';
        if (btn) {
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generando...';
            btn.disabled = true;
        }

        try {
            // Verificar si jsPDF está expuesto de globalmente por html2pdf.js
            let jsPdfConstructor = window.jspdf ? window.jspdf.jsPDF : window.jsPDF;
            
            if (!jsPdfConstructor) {
                throw new Error("jsPDF no expuesto globalmente. Usando ventana de impresion de respaldo.");
            }

            const doc = new jsPdfConstructor({
                unit: 'mm',
                format: 'a5',
                orientation: 'portrait'
            });

            // Fondo blanco indispensable
            doc.setFillColor(255, 255, 255);
            doc.rect(0, 0, 148, 210, "F");

            // Cabecera del ticket
            doc.setTextColor(51, 51, 51);
            doc.setFontSize(22);
            doc.text("Ticket de Evento", 15, 25);
            
            // Subtitulo (Evento)
            doc.setTextColor(85, 85, 85);
            doc.setFontSize(14);
            const evtName = currentTicketData.evento.length > 45 ? currentTicketData.evento.substring(0, 42) + "..." : currentTicketData.evento;
            doc.text(evtName, 15, 33);
            
            // Linea divisora
            doc.setDrawColor(0, 123, 255);
            doc.setLineWidth(1);
            doc.line(15, 40, 133, 40);

            // Container Gris
            doc.setFillColor(248, 249, 250);
            doc.setDrawColor(238, 238, 238);
            doc.roundedRect(15, 48, 118, 55, 2, 2, 'FD');

            doc.setTextColor(0, 0, 0);
            doc.setFontSize(12);
            
            // Inyectar pares clave-valor
            doc.setFont("helvetica", "bold");
            doc.text("Corredor:", 20, 58);
            doc.setFont("helvetica", "normal");
            doc.text(currentTicketData.corredor || '', 45, 58);
            
            doc.setFont("helvetica", "bold");
            doc.text("Dorsal:", 20, 68);
            doc.setFont("helvetica", "normal");
            const dorsalStr = currentTicketData.dorsal != null ? currentTicketData.dorsal.toString() : '';
            doc.text(dorsalStr, 45, 68);
            
            doc.setFont("helvetica", "bold");
            doc.text("Fecha:", 20, 78);
            doc.setFont("helvetica", "normal");
            doc.text(currentTicketData.fecha || '', 45, 78);
            
            doc.setFont("helvetica", "bold");
            doc.text("Lugar:", 20, 88);
            doc.setFont("helvetica", "normal");
            let locText = currentTicketData.ubicacion || '';
            if (locText.length > 35) locText = locText.substring(0, 32) + "...";
            doc.text(locText, 45, 88);

            // Rescatar QR
            const qrcodeElement = document.getElementById('qrcode');
            let qrImageSrc = '';
            const qrCanvas = qrcodeElement.querySelector('canvas');
            if (qrCanvas) {
                qrImageSrc = qrCanvas.toDataURL("image/jpeg", 1.0);
            } else {
                const qrImg = qrcodeElement.querySelector('img');
                if (qrImg) qrImageSrc = qrImg.src;
            }

            if (qrImageSrc && qrImageSrc.indexOf('data:image') === 0) {
                const imgFormat = qrImageSrc.indexOf('image/png') > -1 ? 'PNG' : 'JPEG';
                // Centramos QR en X: (148 - 60) / 2 = 44
                doc.addImage(qrImageSrc, imgFormat, 44, 112, 60, 60);
            }

            // Pie de página
            doc.setTextColor(102, 102, 102);
            doc.setFontSize(10);
            doc.text("Presenta este código al ingresar al evento.", 74, 185, { align: "center" });

            doc.save(`Ticket-${currentTicketData.evento.replace(/\s+/g, '-')}.pdf`);

            if (btn) {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }

        } catch (e) {
            console.error("No se pudo construir vector PDF manual: ", e);
            
            const qrcodeElement = document.getElementById('qrcode');
            let qrImageSrc = '';
            const qrCanvas = qrcodeElement.querySelector('canvas');
            if (qrCanvas) qrImageSrc = qrCanvas.toDataURL("image/png");

            // Recurso defensivo: Forzar Print Nativo de SO
            const win = window.open('', '_blank');
            const htmlContent = `
                <html>
                    <head>
                        <title>Ticket de Evento - ${currentTicketData.evento}</title>
                        <style>body { font-family: Arial, sans-serif; padding: 20px; }</style>
                    </head>
                    <body>
                        <div style="max-width: 400px; margin: 0 auto; border: 1px solid #ccc; padding: 20px; border-radius: 8px;">
                            <h2 style="color: #333; margin-top: 0;">Ticket de Evento</h2>
                            <h3 style="color: #666;">${currentTicketData.evento}</h3>
                            <hr style="border:1px solid #007bff; margin-bottom: 20px;" />
                            <p><strong>Corredor:</strong> ${currentTicketData.corredor}</p>
                            <p><strong>Dorsal:</strong> ${currentTicketData.dorsal}</p>
                            <p><strong>Fecha:</strong> ${currentTicketData.fecha}</p>
                            <p><strong>Lugar:</strong> ${currentTicketData.ubicacion}</p>
                            <div style="text-align: center; margin-top: 30px;">
                                ${qrImageSrc ? `<img src="${qrImageSrc}" style="width: 180px; height: 180px;" />` : ''}
                                <p style="color: #666; font-size: 12px; margin-top: 15px;">Guarda este ticket para presentarlo el día del evento.</p>
                            </div>
                        </div>
                        <script>setTimeout(() => { window.print(); window.close(); }, 500);<\/script>
                    </body>
                </html>
            `;
            win.document.write(htmlContent);
            win.document.close();
            
            if (btn) {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }
    }
    
    // Descarga de Dorsal Personalizado
    function downloadDorsal(data) {
        const dorsalBtn = event ? event.currentTarget : null;
        const originalHtml = dorsalBtn ? dorsalBtn.innerHTML : null;
        if (dorsalBtn) {
            dorsalBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            dorsalBtn.disabled = true;
        }

        const img = new Image();
        img.onload = function() {
            try {
                generateDorsalCanvas(img, data);
            } catch (e) {
                console.error("Error al generar el dorsal:", e);
                alert("Hubo un error al generar el dorsal.");
            } finally {
                if (dorsalBtn) {
                    dorsalBtn.innerHTML = originalHtml;
                    dorsalBtn.disabled = false;
                }
            }
        };
        img.onerror = function() {
            alert("Error al cargar la plantilla del dorsal.");
            if (dorsalBtn) {
                dorsalBtn.innerHTML = originalHtml;
                dorsalBtn.disabled = false;
            }
        };
        img.src = '../../assets/images/dorsal_template.jpg';
    }

    function generateDorsalCanvas(bgImg, data) {
        const canvas = document.createElement('canvas');
        canvas.width = 1024;
        canvas.height = 682;
        const ctx = canvas.getContext('2d');
        
        // Dibujar el fondo original
        ctx.drawImage(bgImg, 0, 0, 1024, 682);
        
        // Helper para dibujar rectángulos redondeados (para no sobreescribir los bordes de los contenedores)
        function drawRoundedRect(c, x, y, w, h, r, fillStyle) {
            c.fillStyle = fillStyle;
            c.beginPath();
            if (c.roundRect) {
                c.roundRect(x, y, w, h, r);
            } else {
                c.moveTo(x + r, y);
                c.arcTo(x + w, y, x + w, y + h, r);
                c.arcTo(x + w, y + h, x, y + h, r);
                c.arcTo(x, y + h, x, y, r);
                c.arcTo(x, y, x + w, y, r);
            }
            c.closePath();
            c.fill();
        }

        // 1. Limpiar el QR y sus datos viejos (caja de la izquierda, usando un rectángulo redondeado para limpiar
        // por completo los textos superiores sin tocar ni cortar el borde morado)
        drawRoundedRect(ctx, 24, 224, 232, 342, 18, "#ffffff");
        
        // 2. Limpiar el número de corredor viejo (centro)
        ctx.fillStyle = "#ffffff";
        ctx.fillRect(285, 265, 700, 195);
        
        // 3. Limpiar el banner de nombre viejo (abajo centro)
        ctx.fillStyle = "#ffffff";
        ctx.fillRect(290, 508, 600, 94);
        
        // 4. Generar nuevo QR Code (1/3 más pequeño: de 180px a 120px de ancho)
        const tempDiv = document.createElement('div');
        const qrUrl = `https://fit5krd.com/modules/admin/attendance.php?eventid=${data.event_id}&runner=${data.runner_number}`;
        
        new QRCode(tempDiv, {
            text: qrUrl,
            width: 120,
            height: 120,
            colorDark: "#000000",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.L
        });
        
        // Obtener el canvas de la librería QRCode y dibujarlo centrado en la caja izquierda
        const qrCanvas = tempDiv.querySelector('canvas');
        if (qrCanvas) {
            ctx.drawImage(qrCanvas, 80, 245, 120, 120);
        }
        
        // 5. Dibujar datos del participante
        const fields = [
            { label: 'NOMBRE', value: data.name, emoji: '👤' },
            { label: 'CATEGORÍA', value: data.category, emoji: '🏃' },
            { label: 'TELÉFONO', value: data.phone, emoji: '📞' }
        ];
        
        let currentY = 395;
        fields.forEach(field => {
            // Dibujar ícono emoji
            ctx.font = "14px Arial, sans-serif";
            ctx.textAlign = "left";
            ctx.fillStyle = "#4a0e4e";
            ctx.fillText(field.emoji, 35, currentY + 4);
            
            // Dibujar etiqueta (Label)
            ctx.font = "bold 9px sans-serif";
            ctx.fillStyle = "#8b0051";
            ctx.fillText(field.label, 54, currentY - 2);
            
            // Dibujar valor
            ctx.font = "12px sans-serif";
            ctx.fillStyle = "#1a1a1a";
            
            let valStr = field.value || 'N/A';
            if (valStr.length > 22) {
                valStr = valStr.substring(0, 19) + '...';
            }
            ctx.fillText(valStr, 54, currentY + 10);
            
            currentY += 30; // Espacio entre líneas (30px para mayor espacio)
        });

        // Dibujar Contacto de Emergencia por separado para poner el teléfono debajo
        ctx.font = "14px Arial, sans-serif";
        ctx.textAlign = "left";
        ctx.fillStyle = "#4a0e4e";
        ctx.fillText('🚨', 35, currentY + 4);
        
        ctx.font = "bold 9px sans-serif";
        ctx.fillStyle = "#8b0051";
        ctx.fillText('CONTACTO DE EMERGENCIA', 54, currentY - 2);
        
        ctx.font = "12px sans-serif";
        ctx.fillStyle = "#1a1a1a";
        let emgName = data.emergency_contact || 'N/A';
        if (emgName.length > 22) {
            emgName = emgName.substring(0, 19) + '...';
        }
        ctx.fillText(emgName, 54, currentY + 10);
        
        if (data.emergency_phone) {
            ctx.font = "11px sans-serif";
            ctx.fillStyle = "#555555";
            ctx.fillText(data.emergency_phone, 54, currentY + 22);
        }
        
        // 7. Dibujar Número de Corredor
        ctx.font = "bold 142px Impact, Arial Black, Arial, sans-serif";
        ctx.fillStyle = "#0c0c0c";
        ctx.textAlign = "center";
        ctx.fillText(data.runner_number, 635, 420);
        
        // 8. Dibujar Banner de Nombre (degradado redondeado con el nombre blanco encima)
        const bannerX = 330;
        const bannerY = 518;
        const bannerW = 600;
        const bannerH = 75;
        const bannerRadius = 15;
        
        const gradient = ctx.createLinearGradient(bannerX, 0, bannerX + bannerW, 0);
        gradient.addColorStop(0, '#77005b');
        gradient.addColorStop(0.5, '#ab005c');
        gradient.addColorStop(1, '#df0059');
        
        drawRoundedRect(ctx, bannerX, bannerY, bannerW, bannerH, bannerRadius, gradient);
        
        // Dibujar nombre
        ctx.font = "bold 38px sans-serif";
        ctx.fillStyle = "#ffffff";
        ctx.textAlign = "center";
        
        let cleanName = data.name || '';
        if (cleanName.length > 25) {
            ctx.font = "bold 30px sans-serif";
        }
        ctx.fillText(cleanName, bannerX + bannerW / 2, bannerY + bannerH / 2 + 12);
        
        // 9. Descargar la imagen resultante
        const dataURL = canvas.toDataURL('image/jpeg', 0.95);
        const link = document.createElement('a');
        
        const safeName = (data.name || '').normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/\s+/g, '-');
        link.download = `Dorsal-${data.runner_number}-${safeName}.jpg`;
        link.href = dataURL;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    window.onclick = function(event) {
        const modal = document.getElementById('qrModal');
        if (event.target == modal) {
            closeQRModal();
        }
    }
</script>

<?php include '../../includes/admin-footer.php'; ?>
