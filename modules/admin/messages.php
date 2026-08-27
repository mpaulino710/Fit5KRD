<?php
// modules/admin/messages.php
require_once '../../config/config.php';
require_once '../../config/database.php';

// Verificar autenticación
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'organizador'])) {
    header('Location: ../auth/login.php');
    exit();
}

$db = getDB();

// Variables para filtros y paginación
$filtro_tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';
$filtro_leido = isset($_GET['leido']) ? $_GET['leido'] : '';
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$por_pagina = 15;
$offset = ($pagina - 1) * $por_pagina;

// Construir consulta base para notificaciones
$query = "SELECT SQL_CALC_FOUND_ROWS 
          n.*,
          u.nombre as usuario_nombre,
          u.apellido as usuario_apellido,
          u.email as usuario_email
          FROM notificaciones n
          LEFT JOIN usuarios u ON n.id_usuario = u.id
          WHERE 1=1";

$params = [];
$types = '';

if ($filtro_tipo) {
    $query .= " AND n.tipo = ?";
    $params[] = $filtro_tipo;
    $types .= 's';
}

if ($filtro_leido !== '') {
    $query .= " AND n.leido = ?";
    $params[] = intval($filtro_leido);
    $types .= 'i';
}

if ($busqueda) {
    $query .= " AND (n.titulo LIKE ? OR n.mensaje LIKE ? OR u.nombre LIKE ? OR u.apellido LIKE ?)";
    $busqueda_like = "%$busqueda%";
    $params[] = $busqueda_like;
    $params[] = $busqueda_like;
    $params[] = $busqueda_like;
    $params[] = $busqueda_like;
    $types .= 'ssss';
}

$query .= " ORDER BY n.fecha_creacion DESC LIMIT ? OFFSET ?";
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
$mensajes = $result->fetch_all(MYSQLI_ASSOC);

// Obtener total de resultados
$total_resultados = $db->query("SELECT FOUND_ROWS()")->fetch_row()[0];
$total_paginas = ceil($total_resultados / $por_pagina);

$stmt->close();

// Obtener estadísticas
$stats_query = "SELECT
    COUNT(*) as total,
    SUM(CASE WHEN leido = 0 THEN 1 ELSE 0 END) as no_leidos,
    SUM(CASE WHEN leido = 1 THEN 1 ELSE 0 END) as leidos,
    SUM(CASE WHEN tipo = 'info' THEN 1 ELSE 0 END) as info,
    SUM(CASE WHEN tipo = 'success' THEN 1 ELSE 0 END) as success,
    SUM(CASE WHEN tipo = 'warning' THEN 1 ELSE 0 END) as warning,
    SUM(CASE WHEN tipo = 'error' THEN 1 ELSE 0 END) as error,
    SUM(CASE WHEN tipo = 'evento' THEN 1 ELSE 0 END) as eventos
FROM notificaciones";

$stats = $db->query($stats_query)->fetch_assoc();

// Procesar acciones
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $notificacion_id = $_POST['notificacion_id'] ?? 0;
    
    if ($action === 'marcar_leida' && $notificacion_id) {
        $stmt = $db->prepare("UPDATE notificaciones SET leido = 1, fecha_lectura = NOW() WHERE id = ?");
        $stmt->bind_param("i", $notificacion_id);
        
        if ($stmt->execute()) {
            $success = 'Notificación marcada como leída';
        } else {
            $error = 'Error al marcar la notificación';
        }
        $stmt->close();
    }
    
    elseif ($action === 'marcar_no_leida' && $notificacion_id) {
        $stmt = $db->prepare("UPDATE notificaciones SET leido = 0, fecha_lectura = NULL WHERE id = ?");
        $stmt->bind_param("i", $notificacion_id);
        
        if ($stmt->execute()) {
            $success = 'Notificación marcada como no leída';
        } else {
            $error = 'Error al actualizar la notificación';
        }
        $stmt->close();
    }
    
    elseif ($action === 'eliminar_notificacion' && $notificacion_id) {
        $stmt = $db->prepare("DELETE FROM notificaciones WHERE id = ?");
        $stmt->bind_param("i", $notificacion_id);
        
        if ($stmt->execute()) {
            $success = 'Notificación eliminada correctamente';
        } else {
            $error = 'Error al eliminar la notificación';
        }
        $stmt->close();
    }
    
    // Recargar la página para mostrar cambios
    header("Location: messages.php?pagina=$pagina" . 
           ($filtro_tipo ? "&tipo=$filtro_tipo" : "") . 
           ($filtro_leido !== '' ? "&leido=$filtro_leido" : "") . 
           ($busqueda ? "&busqueda=$busqueda" : ""));
    exit();
}

include '../../includes/admin-header.php';
?>

<div class="admin-main">
    <!-- Header -->
    <div class="admin-header">
        <h1><i class="fas fa-bell"></i> Notificaciones del Sistema</h1>
        <div class="admin-header-actions">
            <button onclick="marcarTodasLeidas()" class="admin-btn admin-btn-primary">
                <i class="fas fa-check-double"></i> Marcar Todas Leídas
            </button>
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
                <i class="fas fa-bell"></i>
            </div>
            <div class="stat-number"><?php echo number_format($stats['total']); ?></div>
            <div class="stat-label">Total Notificaciones</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon stat-icon-warning">
                <i class="fas fa-envelope-open-text"></i>
            </div>
            <div class="stat-number"><?php echo number_format($stats['no_leidos']); ?></div>
            <div class="stat-label">No Leídas</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon stat-icon-success">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-number"><?php echo number_format($stats['leidos']); ?></div>
            <div class="stat-label">Leídas</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon stat-icon-info">
                <i class="fas fa-info-circle"></i>
            </div>
            <div class="stat-number"><?php echo number_format($stats['info']); ?></div>
            <div class="stat-label">Informativas</div>
        </div>
    </div>

    <!-- Filtros y Búsqueda -->
    <div class="admin-filters">
        <form method="GET" action="">
            <div class="filter-row">
                <div class="form-group">
                    <label for="busqueda"><i class="fas fa-search"></i> Buscar</label>
                    <input type="text" id="busqueda" name="busqueda" class="form-control"
                           placeholder="Título, mensaje o usuario"
                           value="<?php echo htmlspecialchars($busqueda); ?>">
                </div>
                
                <div class="form-group">
                    <label for="tipo"><i class="fas fa-tag"></i> Tipo</label>
                    <select id="tipo" name="tipo" class="form-control">
                        <option value="">Todos los tipos</option>
                        <option value="info" <?php echo $filtro_tipo === 'info' ? 'selected' : ''; ?>>Info</option>
                        <option value="success" <?php echo $filtro_tipo === 'success' ? 'selected' : ''; ?>>Success</option>
                        <option value="warning" <?php echo $filtro_tipo === 'warning' ? 'selected' : ''; ?>>Warning</option>
                        <option value="error" <?php echo $filtro_tipo === 'error' ? 'selected' : ''; ?>>Error</option>
                        <option value="evento" <?php echo $filtro_tipo === 'evento' ? 'selected' : ''; ?>>Evento</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="leido"><i class="fas fa-eye"></i> Estado</label>
                    <select id="leido" name="leido" class="form-control">
                        <option value="">Todos</option>
                        <option value="0" <?php echo $filtro_leido === '0' ? 'selected' : ''; ?>>No leídas</option>
                        <option value="1" <?php echo $filtro_leido === '1' ? 'selected' : ''; ?>>Leídas</option>
                    </select>
                </div>
                
                <div>
                    <button type="submit" class="admin-btn admin-btn-primary">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                    <a href="messages.php" class="admin-btn admin-btn-secondary">
                        <i class="fas fa-redo"></i> Limpiar
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Tabla de Notificaciones -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h2><i class="fas fa-list"></i> Lista de Notificaciones</h2>
            <span class="badge badge-primary">
                <?php echo number_format($total_resultados); ?> notificaciones encontradas
            </span>
        </div>
        
        <?php if (!empty($mensajes)): ?>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuario</th>
                        <th>Título</th>
                        <th>Tipo</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mensajes as $notificacion): 
                        $fecha_creacion = strtotime($notificacion['fecha_creacion']);
                        $fecha_lectura = $notificacion['fecha_lectura'] ? strtotime($notificacion['fecha_lectura']) : null;
                        
                        $tipo_colors = [
                            'info' => 'info',
                            'success' => 'success',
                            'warning' => 'warning',
                            'error' => 'danger',
                            'evento' => 'primary'
                        ];
                        $tipo_color = $tipo_colors[$notificacion['tipo']] ?? 'secondary';
                    ?>
                    <tr>
                        <td><strong>#<?php echo $notificacion['id']; ?></strong></td>
                        <td>
                            <?php if ($notificacion['id_usuario']): ?>
                            <div class="user-info">
                                <div class="user-avatar-small">
                                    <?php echo strtoupper(substr($notificacion['usuario_nombre'], 0, 1) . substr($notificacion['usuario_apellido'], 0, 1)); ?>
                                </div>
                                <div>
                                    <strong><?php echo htmlspecialchars($notificacion['usuario_nombre'] . ' ' . $notificacion['usuario_apellido']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($notificacion['usuario_email']); ?></small>
                                </div>
                            </div>
                            <?php else: ?>
                            <span class="badge badge-secondary">Sistema</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars(substr($notificacion['titulo'], 0, 40)); ?></strong>
                            <?php if (strlen($notificacion['titulo']) > 40): ?>...<?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo $tipo_color; ?>">
                                <?php echo ucfirst($notificacion['tipo']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="message-date"><?php echo date('d/m/Y', $fecha_creacion); ?></div>
                            <small class="message-time"><?php echo date('h:i A', $fecha_creacion); ?></small>
                            <?php if ($fecha_lectura): ?>
                            <br>
                            <small class="text-success">
                                <i class="fas fa-check"></i> Leída: <?php echo date('d/m/Y', $fecha_lectura); ?>
                            </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($notificacion['leido']): ?>
                            <span class="badge badge-success">
                                <i class="fas fa-check"></i> Leída
                            </span>
                            <?php else: ?>
                            <span class="badge badge-warning">
                                <i class="fas fa-clock"></i> No leída
                            </span>
                            <?php endif; ?>
                            
                            <form method="POST" action="" class="status-form">
                                <input type="hidden" name="notificacion_id" value="<?php echo $notificacion['id']; ?>">
                                <?php if ($notificacion['leido']): ?>
                                <input type="hidden" name="action" value="marcar_no_leida">
                                <button type="submit" class="admin-btn admin-btn-warning btn-sm btn-block">
                                    <i class="fas fa-undo"></i> Marcar no leída
                                </button>
                                <?php else: ?>
                                <input type="hidden" name="action" value="marcar_leida">
                                <button type="submit" class="admin-btn admin-btn-success btn-sm btn-block">
                                    <i class="fas fa-check"></i> Marcar leída
                                </button>
                                <?php endif; ?>
                            </form>
                        </td>
                        <td>
                            <div class="table-actions">
                                <button type="button" onclick="viewNotification(<?php echo $notificacion['id']; ?>)" 
                                        class="admin-btn admin-btn-outline action-btn" title="Ver notificación">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <form method="POST" action="" class="action-form"
                                      onsubmit="return confirm('¿Estás seguro de eliminar esta notificación?');">
                                    <input type="hidden" name="action" value="eliminar_notificacion">
                                    <input type="hidden" name="notificacion_id" value="<?php echo $notificacion['id']; ?>">
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
            <a href="?pagina=<?php echo $pagina - 1; ?>&tipo=<?php echo $filtro_tipo; ?>&leido=<?php echo $filtro_leido; ?>&busqueda=<?php echo urlencode($busqueda); ?>"
               class="pagination-link">
                <i class="fas fa-chevron-left"></i>
            </a>
            <?php endif; ?>

            <?php
            $inicio = max(1, $pagina - 2);
            $fin = min($total_paginas, $pagina + 2);

            for ($i = $inicio; $i <= $fin; $i++):
            ?>
            <a href="?pagina=<?php echo $i; ?>&tipo=<?php echo $filtro_tipo; ?>&leido=<?php echo $filtro_leido; ?>&busqueda=<?php echo urlencode($busqueda); ?>"
               class="pagination-link <?php echo $i == $pagina ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>

            <?php if ($pagina < $total_paginas): ?>
            <a href="?pagina=<?php echo $pagina + 1; ?>&tipo=<?php echo $filtro_tipo; ?>&leido=<?php echo $filtro_leido; ?>&busqueda=<?php echo urlencode($busqueda); ?>"
               class="pagination-link">
                <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="table-footer">
            Mostrando <?php echo count($mensajes); ?> de <?php echo $total_resultados; ?> notificaciones
        </div>

        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-bell-slash"></i>
            <h3>No se encontraron notificaciones</h3>
            <p>No hay notificaciones que coincidan con tu búsqueda</p>
            <a href="messages.php" class="admin-btn admin-btn-primary">
                <i class="fas fa-redo"></i> Ver todas las notificaciones
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal para ver notificación -->
<div id="notificationModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-bell"></i> Detalles de la Notificación</h2>
            <button type="button" class="close-modal" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body" id="notificationContent">
            <!-- El contenido se carga dinámicamente aquí -->
        </div>
        <div class="modal-footer">
            <button type="button" class="admin-btn admin-btn-secondary" onclick="closeModal()">
                <i class="fas fa-times"></i> Cerrar
            </button>
        </div>
    </div>
</div>

<style>
/* Estilos para modales */
.modal {
    display: none;
    position: fixed;
    z-index: 1001;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
}

.modal-content {
    background-color: var(--admin-card-bg);
    margin: 5% auto;
    padding: 0;
    border-radius: 8px;
    width: 80%;
    max-width: 800px;
    max-height: 80vh;
    overflow-y: auto;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid var(--admin-border);
}

.modal-header h2 {
    margin: 0;
    color: var(--admin-primary);
}

.close-modal {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: var(--admin-text-light);
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.close-modal:hover {
    color: var(--admin-danger);
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    padding: 1.5rem;
    border-top: 1px solid var(--admin-border);
}

/* Estilos específicos para notificaciones */
.notification-detail {
    margin-bottom: 1.5rem;
}

.notification-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.notification-sender {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.notification-title {
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--admin-text);
    margin-bottom: 0.5rem;
}

.notification-body {
    background: var(--admin-bg);
    padding: 1.5rem;
    border-radius: 8px;
    white-space: pre-wrap;
    line-height: 1.6;
}

.notification-footer {
    margin-top: 1.5rem;
    padding-top: 1rem;
    border-top: 1px solid var(--admin-border);
    color: var(--admin-text-light);
    font-size: 0.9rem;
}

/* Responsive para modales */
@media (max-width: 768px) {
    .modal-content {
        width: 95%;
        margin: 10% auto;
    }
    
    .modal-header {
        padding: 1rem;
    }
    
    .modal-body {
        padding: 1rem;
    }
    
    .modal-footer {
        padding: 1rem;
        flex-direction: column;
    }
}
</style>

<script>
// Auto-ocultar notificaciones después de 5 segundos
setTimeout(() => {
    document.querySelectorAll('.admin-alert').forEach(notification => {
        notification.style.display = 'none';
    });
}, 5000);

// Búsqueda con Enter
document.getElementById('busqueda')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        this.form.submit();
    }
});

// Funciones para modales
function viewNotification(notificationId) {
    // Cargar detalles de la notificación via AJAX
    fetch(`get_notification.php?id=${notificationId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const notification = data.notification;
                const modalContent = document.getElementById('notificationContent');
                
                // Formatear fecha
                const fechaCreacion = new Date(notification.fecha_creacion);
                const fechaCreacionFormateada = fechaCreacion.toLocaleDateString('es-ES', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
                
                let fechaLecturaFormateada = 'No leída';
                if (notification.fecha_lectura) {
                    const fechaLectura = new Date(notification.fecha_lectura);
                    fechaLecturaFormateada = fechaLectura.toLocaleDateString('es-ES', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                }
                
                // Determinar color del tipo
                const tipoColors = {
                    'info': 'info',
                    'success': 'success',
                    'warning': 'warning',
                    'error': 'danger',
                    'evento': 'primary'
                };
                const tipoColor = tipoColors[notification.tipo] || 'secondary';
                
                // Construir HTML de la notificación
                let html = `
                    <div class="notification-detail">
                        <div class="notification-header">
                            <div class="notification-sender">
                                <div class="user-avatar-medium">
                                    ${notification.usuario_nombre ? 
                                        notification.usuario_nombre.charAt(0).toUpperCase() + 
                                        notification.usuario_apellido.charAt(0).toUpperCase() :
                                        'S'
                                    }
                                </div>
                                <div>
                                    <h3>${notification.usuario_nombre ? 
                                        `${notification.usuario_nombre} ${notification.usuario_apellido}` : 
                                        'Sistema'
                                    }</h3>
                                    ${notification.usuario_email ? `<p><i class="fas fa-envelope"></i> ${notification.usuario_email}</p>` : ''}
                                </div>
                            </div>
                            <span class="badge badge-${tipoColor}">
                                ${notification.tipo.charAt(0).toUpperCase() + notification.tipo.slice(1)}
                            </span>
                        </div>
                        
                        <div class="notification-title">
                            <i class="fas fa-tag"></i> ${notification.titulo}
                        </div>
                        
                        <div class="notification-body">
                            ${notification.mensaje.replace(/\n/g, '<br>')}
                        </div>
                        
                        <div class="notification-footer">
                            <p><strong>Creada:</strong> ${fechaCreacionFormateada}</p>
                            <p><strong>Leída:</strong> ${fechaLecturaFormateada}</p>
                            <p><strong>Estado:</strong> ${notification.leido ? 
                                '<span class="badge badge-success">Leída</span>' : 
                                '<span class="badge badge-warning">No leída</span>'
                            }</p>
                        </div>
                    </div>
                `;
                
                modalContent.innerHTML = html;
                document.getElementById('notificationModal').style.display = 'block';
                
                // Marcar como leída automáticamente si no lo está
                if (!notification.leido) {
                    markAsRead(notificationId);
                }
            } else {
                alert('Error al cargar la notificación: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al cargar la notificación');
        });
}

function closeModal() {
    document.getElementById('notificationModal').style.display = 'none';
}

function markAsRead(notificationId) {
    const formData = new FormData();
    formData.append('action', 'marcar_leida');
    formData.append('notificacion_id', notificationId);
    
    fetch('messages.php', {
        method: 'POST',
        body: formData
    }).then(() => {
        // Recargar la página para actualizar el estado
        window.location.reload();
    });
}

function marcarTodasLeidas() {
    if (confirm('¿Marcar todas las notificaciones como leídas?')) {
        const formData = new FormData();
        formData.append('action', 'marcar_todas_leidas');
        
        fetch('mark_all_read.php', {
            method: 'POST',
            body: formData
        }).then(response => response.json())
          .then(data => {
              if (data.success) {
                  alert(data.message);
                  window.location.reload();
              } else {
                  alert('Error: ' + data.error);
              }
          })
          .catch(error => {
              console.error('Error:', error);
              alert('Error al procesar la solicitud');
          });
    }
}

// Cerrar modal al hacer clic fuera
window.addEventListener('click', function(event) {
    const notificationModal = document.getElementById('notificationModal');
    if (event.target === notificationModal) {
        closeModal();
    }
});
</script>

<?php include '../../includes/admin-footer.php'; ?>