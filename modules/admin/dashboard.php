<?php
// Archivo: modules/admin/dashboard.php
require_once '../../config/config.php';
require_once '../../config/database.php';

// Verificar autenticación y permisos de administrador
if (session_status() === PHP_SESSION_NONE) {

// session_start(); removed as it's handled in config.php
}

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'organizador'])) {
    header('Location: ../auth/login.php');
    exit();
}

$db = getDB();

// Obtener estadísticas
$stats = [];

// Total usuarios
$query = "SELECT COUNT(*) as total FROM usuarios WHERE estado != 'inactivo'";
$stats['usuarios'] = $db->query($query)->fetch_assoc()['total'];

// Total eventos
$query = "SELECT COUNT(*) as total FROM eventos";
$stats['eventos'] = $db->query($query)->fetch_assoc()['total'];

// Total inscripciones
$query = "SELECT COUNT(*) as total FROM inscripciones WHERE estado = 'confirmada'";
$stats['inscripciones'] = $db->query($query)->fetch_assoc()['total'];

// Ingresos totales
$query = "SELECT SUM(p.monto) as total 
          FROM pagos p 
          JOIN inscripciones i ON p.id_inscripcion = i.id 
          WHERE p.estado = 'completado'";
$result = $db->query($query);
$stats['ingresos'] = $result->fetch_assoc()['total'] ?? 0;

// Eventos próximos
$query = "SELECT * FROM eventos 
          WHERE fecha_evento > NOW() 
          AND estado = 'activo' 
          ORDER BY fecha_evento ASC 
          LIMIT 5";
$eventos_proximos = $db->query($query)->fetch_all(MYSQLI_ASSOC);

// Usuarios recientes
$query = "SELECT id, nombre, apellido, email, fecha_registro 
          FROM usuarios 
          WHERE estado != 'inactivo'
          ORDER BY fecha_registro DESC 
          LIMIT 5";
$usuarios_recientes = $db->query($query)->fetch_all(MYSQLI_ASSOC);

// Eventos recientes creados
$query = "SELECT * FROM eventos 
          ORDER BY fecha_creacion DESC 
          LIMIT 5";
$eventos_recientes = $db->query($query)->fetch_all(MYSQLI_ASSOC);

// Inscripciones pendientes
$query = "SELECT COUNT(*) as total FROM inscripciones WHERE estado = 'pendiente'";
$inscripciones_pendientes = $db->query($query)->fetch_assoc()['total'];

// Mensajes nuevos de contacto
$query = "SELECT COUNT(*) as total FROM contactos WHERE estado = 'nuevo' OR estado IS NULL";
$mensajes_nuevos = $db->query($query)->fetch_assoc()['total'];

include '../../includes/admin-header.php';
?>

<div class="admin-main">
    <!-- Header -->
    <div class="admin-header">
        <h1>
            <i class="fas fa-tachometer-alt"></i> 
            <?php echo $_SESSION['user_type'] === 'organizador' ? 'Panel de Organización' : 'Panel de Administración'; ?>
        </h1>
        <div class="admin-header-actions">
            <span class="welcome-message">
                Bienvenido, <?php echo htmlspecialchars($_SESSION['user_name']); ?>
            </span>
        </div>
    </div>

    <!-- Estadísticas principales -->
    <div class="admin-stats">
        <?php if ($_SESSION['user_type'] === 'admin'): ?>
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-number"><?php echo number_format($stats['usuarios']); ?></div>
            <div class="stat-label">Usuarios Registrados</div>
            <a href="users.php" class="stat-link">
                <i class="fas fa-eye"></i> Ver todos
            </a>
        </div>
        <?php endif; ?>
        
        <div class="stat-card">
            <div class="stat-icon stat-icon-success">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div class="stat-number"><?php echo number_format($stats['eventos']); ?></div>
            <div class="stat-label">Eventos Creados</div>
            <a href="events.php" class="stat-link stat-link-success">
                <i class="fas fa-cog"></i> Gestionar
            </a>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon stat-icon-warning">
                <i class="fas fa-running"></i>
            </div>
            <div class="stat-number"><?php echo number_format($stats['inscripciones']); ?></div>
            <div class="stat-label">Inscripciones Activas</div>
            <a href="inscriptions.php" class="stat-link stat-link-warning">
                <i class="fas fa-list"></i> Ver inscripciones
            </a>
        </div>
        
        <?php if ($_SESSION['user_type'] === 'admin'): ?>
        <div class="stat-card">
            <div class="stat-icon stat-icon-secondary">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="stat-number">$<?php echo number_format($stats['ingresos'], 2); ?></div>
            <div class="stat-label">Ingresos Totales</div>
            <a href="reports.php" class="stat-link stat-link-secondary">
                <i class="fas fa-chart-bar"></i> Ver reportes
            </a>
        </div>
        <?php endif; ?>
        
        <div class="stat-card">
            <div class="stat-icon stat-icon-danger">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-number"><?php echo number_format($inscripciones_pendientes); ?></div>
            <div class="stat-label">Pendientes</div>
            <a href="inscriptions.php?estado=pendiente" class="stat-link stat-link-danger">
                <i class="fas fa-exclamation-circle"></i> Revisar
            </a>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon stat-icon-info">
                <i class="fas fa-envelope"></i>
            </div>
            <div class="stat-number"><?php echo number_format($mensajes_nuevos); ?></div>
            <div class="stat-label">Mensajes Nuevos</div>
            <a href="contacts.php?estado=nuevo" class="stat-link stat-link-info">
                <i class="fas fa-inbox"></i> Ver mensajes
            </a>
        </div>
    </div>

    <!-- Contenido principal en dos columnas -->
    <div class="dashboard-grid">
        <!-- Eventos Próximos -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h2><i class="fas fa-running"></i> Próximos Eventos</h2>
                <a href="events.php" class="card-header-link">
                    <i class="fas fa-plus"></i> Ver todos
                </a>
            </div>
            
            <?php if (!empty($eventos_proximos)): ?>
                <div class="table-responsive">
                    <table class="admin-table dashboard-table">
                        <thead>
                            <tr>
                                <th>Evento</th>
                                <th>Fecha</th>
                                <th>Cupos</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($eventos_proximos as $evento): 
                                $fecha_evento = strtotime($evento['fecha_evento']);
                                $dias_restantes = ceil(($fecha_evento - time()) / (60 * 60 * 24));
                            ?>
                            <tr>
                                <td>
                                    <strong class="event-name"><?php echo htmlspecialchars(substr($evento['nombre'], 0, 30)); ?></strong>
                                    <?php if (strlen($evento['nombre']) > 30): ?>...<?php endif; ?>
                                    <br>
                                    <small class="event-location">
                                        <?php echo htmlspecialchars($evento['ubicacion']); ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="event-date"><?php echo date('d/m/Y', $fecha_evento); ?></div>
                                    <small class="event-time">
                                        <?php echo date('h:i A', $fecha_evento); ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="capacity-bar">
                                        <div class="capacity-fill" style="width: <?php echo min(100, ($evento['cupo_maximo'] - $evento['cupo_disponible']) / $evento['cupo_maximo'] * 100); ?>%;"></div>
                                    </div>
                                    <div class="capacity-info">
                                        <span class="capacity-count"><?php echo $evento['cupo_maximo'] - $evento['cupo_disponible']; ?>/<?php echo $evento['cupo_maximo']; ?></span>
                                        <small class="days-remaining"><?php echo $dias_restantes; ?> días restantes</small>
                                    </div>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <a href="../events/view.php?id=<?php echo $evento['id']; ?>" 
                                           class="admin-btn admin-btn-outline action-btn" title="Ver">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit-event.php?id=<?php echo $evento['id']; ?>" 
                                           class="admin-btn admin-btn-secondary action-btn" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <h3>No hay eventos próximos</h3>
                    <p>Crea un nuevo evento para comenzar</p>
                    <a href="create-event.php" class="admin-btn admin-btn-primary">
                        <i class="fas fa-plus"></i> Crear Evento
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Usuarios Recientes -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h2><i class="fas fa-user-plus"></i> Usuarios Recientes</h2>
                <a href="users.php" class="card-header-link">
                    <i class="fas fa-plus"></i> Ver todos
                </a>
            </div>
            
            <?php if (!empty($usuarios_recientes)): ?>
                <div class="table-responsive">
                    <table class="admin-table dashboard-table">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Email</th>
                                <th>Registro</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios_recientes as $usuario): 
                                $fecha_registro = strtotime($usuario['fecha_registro']);
                                $dias_transcurridos = floor((time() - $fecha_registro) / (60 * 60 * 24));
                            ?>
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <div class="user-avatar-small">
                                            <?php echo strtoupper(substr($usuario['nombre'], 0, 1) . substr($usuario['apellido'], 0, 1)); ?>
                                        </div>
                                        <div class="user-name">
                                            <strong><?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']); ?></strong>
                                        </div>
                                    </div>
                                </td>
                                <td class="user-email"><?php echo htmlspecialchars($usuario['email']); ?></td>
                                <td>
                                    <div class="registration-date"><?php echo date('d/m/Y', $fecha_registro); ?></div>
                                    <small class="registration-days">Hace <?php echo $dias_transcurridos; ?> días</small>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <a href="users.php?action=view&id=<?php echo $usuario['id']; ?>" 
                                           class="admin-btn admin-btn-outline action-btn" title="Ver">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit-user.php?id=<?php echo $usuario['id']; ?>" 
                                           class="admin-btn admin-btn-secondary action-btn" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-users-slash"></i>
                    <h3>No hay usuarios registrados</h3>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Eventos Recientes -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h2><i class="fas fa-calendar-plus"></i> Eventos Recientemente Creados</h2>
            <a href="events.php" class="card-header-link">
                <i class="fas fa-plus"></i> Ver todos
            </a>
        </div>
        
        <?php if (!empty($eventos_recientes)): ?>
            <div class="table-responsive">
                <table class="admin-table dashboard-table">
                    <thead>
                        <tr>
                            <th>Evento</th>
                            <th>Fecha Evento</th>
                            <th>Fecha Creación</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($eventos_recientes as $evento): 
                            $fecha_evento = strtotime($evento['fecha_evento']);
                            $fecha_creacion = strtotime($evento['fecha_creacion']);
                            $estado_badge = '';
                            switch($evento['estado']) {
                                case 'activo': $estado_badge = 'badge-success'; break;
                                case 'cancelado': $estado_badge = 'badge-danger'; break;
                                case 'completado': $estado_badge = 'badge-info'; break;
                                case 'pendiente': $estado_badge = 'badge-warning'; break;
                                default: $estado_badge = 'badge-secondary';
                            }
                        ?>
                        <tr>
                            <td>
                                <strong class="event-name"><?php echo htmlspecialchars(substr($evento['nombre'], 0, 40)); ?></strong>
                                <?php if (strlen($evento['nombre']) > 40): ?>...<?php endif; ?>
                                <br>
                                <small class="event-details">
                                    <?php echo $evento['distancia']; ?> km • $<?php echo number_format($evento['precio'], 2); ?>
                                </small>
                            </td>
                            <td>
                                <div class="event-date"><?php echo date('d/m/Y', $fecha_evento); ?></div>
                                <small class="event-time">
                                    <?php echo date('h:i A', $fecha_evento); ?>
                                </small>
                            </td>
                            <td>
                                <div class="event-date"><?php echo date('d/m/Y', $fecha_creacion); ?></div>
                                <small class="event-time">
                                    <?php echo date('h:i A', $fecha_creacion); ?>
                                </small>
                            </td>
                            <td>
                                <span class="badge <?php echo $estado_badge; ?>">
                                    <?php echo ucfirst($evento['estado']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <a href="../events/view.php?id=<?php echo $evento['id']; ?>" 
                                       class="admin-btn admin-btn-outline action-btn" title="Ver">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit-event.php?id=<?php echo $evento['id']; ?>" 
                                       class="admin-btn admin-btn-secondary action-btn" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <h3>No hay eventos creados</h3>
            </div>
        <?php endif; ?>
    </div>

    <!-- Acciones Rápidas -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h2><i class="fas fa-bolt"></i> Acciones Rápidas</h2>
        </div>
        <div class="quick-actions-grid">
            <a href="create-event.php" class="quick-action-btn admin-btn-primary">
                <i class="fas fa-plus-circle"></i>
                <span>Crear Nuevo Evento</span>
            </a>
            
            <?php if ($_SESSION['user_type'] === 'admin'): ?>
            <a href="reports.php" class="quick-action-btn admin-btn-success">
                <i class="fas fa-chart-pie"></i>
                <span>Generar Reportes</span>
            </a>
            <?php endif; ?>
            
            <a href="inscriptions.php" class="quick-action-btn admin-btn-secondary">
                <i class="fas fa-user-check"></i>
                <span>Gestionar Inscripciones</span>
            </a>
            
            <?php if ($_SESSION['user_type'] === 'admin'): ?>
            <a href="users.php?action=create" class="quick-action-btn admin-btn-warning">
                <i class="fas fa-user-plus"></i>
                <span>Crear Usuario</span>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Resumen del Sistema -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h2><i class="fas fa-info-circle"></i> Resumen del Sistema</h2>
        </div>
        <div class="system-summary-grid">
            <div class="summary-card">
                <h3 class="summary-title">
                    <i class="fas fa-server"></i> Estado del Sistema
                </h3>
                <ul class="summary-list">
                    <li>
                        <i class="fas fa-check-circle status-icon success"></i>
                        <span>Base de datos: Conectada</span>
                    </li>
                    <li>
                        <i class="fas fa-check-circle status-icon success"></i>
                        <span>Sesiones: Activas</span>
                    </li>
                    <li>
                        <i class="fas fa-check-circle status-icon success"></i>
                        <span>Carga del servidor: Normal</span>
                    </li>
                    <li>
                        <i class="fas fa-check-circle status-icon success"></i>
                        <span>Última actualización: Hoy</span>
                    </li>
                </ul>
            </div>
            
            <div class="summary-card">
                <h3 class="summary-title">
                    <i class="fas fa-chart-line"></i> Actividad Reciente
                </h3>
                <ul class="summary-list">
                    <li>
                        <i class="fas fa-user-plus status-icon primary"></i>
                        <span>Nuevos usuarios hoy: 3</span>
                    </li>
                    <li>
                        <i class="fas fa-running status-icon success"></i>
                        <span>Inscripciones hoy: 12</span>
                    </li>
                    <li>
                        <i class="fas fa-calendar-plus status-icon warning"></i>
                        <span>Eventos creados hoy: 1</span>
                    </li>
                    <li>
                        <i class="fas fa-dollar-sign status-icon info"></i>
                        <span>Ingresos hoy: $245.50</span>
                    </li>
                </ul>
            </div>
            
            <div class="summary-card">
                <h3 class="summary-title">
                    <i class="fas fa-bell"></i> Recordatorios
                </h3>
                <ul class="summary-list">
                    <li>
                        <i class="fas fa-exclamation-triangle status-icon warning"></i>
                        <span>2 eventos próximos en 3 días</span>
                    </li>
                    <li>
                        <i class="fas fa-envelope status-icon primary"></i>
                        <span><?php echo $mensajes_nuevos; ?> mensajes sin leer</span>
                    </li>
                    <li>
                        <i class="fas fa-clock status-icon danger"></i>
                        <span><?php echo $inscripciones_pendientes; ?> inscripciones pendientes</span>
                    </li>
                    <li>
                        <i class="fas fa-sync-alt status-icon info"></i>
                        <span>Último backup: Ayer</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-refrescar el dashboard cada 5 minutos
setTimeout(() => {
    window.location.reload();
}, 300000); // 5 minutos

// Inicializar tooltips si se usan
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar tooltips si hay alguna librería
    if (typeof tippy !== 'undefined') {
        tippy('[title]', {
            placement: 'top',
            arrow: true,
            animation: 'scale'
        });
    }
});
</script>

<?php include '../../includes/admin-footer.php'; ?>