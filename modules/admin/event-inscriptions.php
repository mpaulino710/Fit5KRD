<?php
// modules/admin/event-inscriptions.php
require_once '../../config/config.php';
require_once '../../config/database.php';


// session_start(); removed as it's handled in config.php
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'organizador'])) {
    header('Location: ../auth/login.php');
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: events.php');
    exit();
}

$event_id = intval($_GET['id']);
$db = getDB();

// Obtener detalles del evento
$query_event = "SELECT * FROM eventos WHERE id = ?";
$stmt = $db->prepare($query_event);
$stmt->bind_param("i", $event_id);
$stmt->execute();
$evento = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$evento) {
    header('Location: events.php');
    exit();
}

// Filtros
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$por_pagina = 15;
$offset = ($pagina - 1) * $por_pagina;

// Consulta de inscripciones
$query = "SELECT SQL_CALC_FOUND_ROWS
          i.*,
          u.nombre as usuario_nombre,
          u.apellido as usuario_apellido,
          u.email as usuario_email,
          u.telefono as usuario_telefono
          FROM inscripciones i
          LEFT JOIN usuarios u ON i.id_usuario = u.id
          WHERE i.id_evento = ?";

$params = [$event_id];
$types = 'i';

if ($filtro_estado) {
    $query .= " AND i.estado = ?";
    $params[] = $filtro_estado;
    $types .= 's';
}

if ($busqueda) {
    $query .= " AND (u.nombre LIKE ? OR u.apellido LIKE ? OR u.email LIKE ? OR i.numero_corredor LIKE ? OR i.nombre LIKE ? OR i.apellido LIKE ?)";
    $busqueda_like = "%$busqueda%";
    $params[] = $busqueda_like;
    $params[] = $busqueda_like;
    $params[] = $busqueda_like;
    $params[] = $busqueda_like;
    $params[] = $busqueda_like;
    $params[] = $busqueda_like;
    $types .= 'ssssss';
}

$query .= " ORDER BY i.fecha_inscripcion DESC LIMIT ? OFFSET ?";
$params[] = $por_pagina;
$params[] = $offset;
$types .= 'ii';

$stmt = $db->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$inscripciones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$total_resultados = $db->query("SELECT FOUND_ROWS()")->fetch_row()[0];
$total_paginas = ceil($total_resultados / $por_pagina);
$stmt->close();

// Estadísticas del evento
$stats_query = "SELECT
    COUNT(*) as total,
    SUM(CASE WHEN estado = 'confirmada' THEN 1 ELSE 0 END) as confirmadas,
    SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
    SUM(CASE WHEN estado = 'cancelada' THEN 1 ELSE 0 END) as canceladas
FROM inscripciones WHERE id_evento = $event_id";
$stats = $db->query($stats_query)->fetch_assoc();

// Procesar cambio de estado
$success = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cambiar_estado') {
    $inscripcion_id = intval($_POST['inscripcion_id']);
    $nuevo_estado = $_POST['nuevo_estado'];
    
    $stmt = $db->prepare("UPDATE inscripciones SET estado = ? WHERE id = ?");
    $stmt->bind_param("si", $nuevo_estado, $inscripcion_id);
    
    if ($stmt->execute()) {
        $success = 'Estado actualizado correctamente.';
        // Recargar
        echo "<meta http-equiv='refresh' content='1'>";
    } else {
        $error = 'Error al actualizar: ' . $db->error;
    }
    $stmt->close();
}

include '../../includes/admin-header.php';
?>

<div class="admin-main">
    <div class="admin-header">
        <div>
            <h1 style="margin-bottom: 0.5rem;"><i class="fas fa-users"></i> Inscripciones: <?php echo htmlspecialchars($evento['nombre']); ?></h1>
            <p style="color: var(--text-light); margin: 0;">
                <i class="fas fa-calendar"></i> <?php echo date('d/m/Y H:i', strtotime($evento['fecha_evento'])); ?> | 
                <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($evento['ubicacion']); ?> |
                <span class="badge badge-info"><?php echo ucfirst($evento['tipo_evento']); ?></span>
            </p>
        </div>
        <div class="admin-header-actions">
            <a href="events.php" class="admin-btn admin-btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver a Eventos
            </a>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="admin-stats">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-number"><?php echo $stats['total']; ?> / <?php echo $evento['cupo_maximo']; ?></div>
            <div class="stat-label">Inscritos / Cupo</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon-success">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-number"><?php echo $stats['confirmadas']; ?></div>
            <div class="stat-label">Confirmados</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon-warning">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-number"><?php echo $stats['pendientes']; ?></div>
            <div class="stat-label">Pendientes</div>
        </div>
    </div>

    <?php if ($success): ?>
    <div class="admin-alert admin-alert-success"><i class="fas fa-check"></i> <?php echo $success; ?></div>
    <?php endif; ?>

    <!-- Filtros -->
    <div class="admin-filters">
        <form method="GET" action="">
            <input type="hidden" name="id" value="<?php echo $event_id; ?>">
            <div class="filter-row">
                <div class="form-group">
                    <label for="busqueda">Buscar</label>
                    <input type="text" name="busqueda" class="form-control" placeholder="Nombre, email..." value="<?php echo htmlspecialchars($busqueda); ?>">
                </div>
                <div class="form-group">
                    <label for="estado">Estado</label>
                    <select name="estado" class="form-control">
                        <option value="">Todos</option>
                        <option value="confirmada" <?php echo $filtro_estado == 'confirmada' ? 'selected' : ''; ?>>Confirmada</option>
                        <option value="pendiente" <?php echo $filtro_estado == 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                        <option value="cancelada" <?php echo $filtro_estado == 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                    </select>
                </div>
                <div>
                    <button type="submit" class="admin-btn admin-btn-primary">Filtrar</button>
                    <a href="event-inscriptions.php?id=<?php echo $event_id; ?>" class="admin-btn admin-btn-secondary">Limpiar</a>
                </div>
            </div>
        </form>
    </div>

    <!-- Tabla -->
    <div class="admin-card">
        <?php if (!empty($inscripciones)): ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th># Corredor</th>
                    <th>Participante</th>
                    <th>Categoría</th>
                    <th>Talla</th>
                    <th>Fecha Registro</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($inscripciones as $inscripcion): ?>
                <tr>
                    <td>
                        <?php if ($inscripcion['numero_corredor']): ?>
                        <span class="badge badge-primary"><?php echo $inscripcion['numero_corredor']; ?></span>
                        <?php else: ?>
                        <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php 
                            $nombre_mostrar = $inscripcion['usuario_nombre'] ? ($inscripcion['usuario_nombre'] . ' ' . $inscripcion['usuario_apellido']) : ($inscripcion['nombre'] . ' ' . $inscripcion['apellido']);
                            $email_mostrar = $inscripcion['usuario_email'] ? $inscripcion['usuario_email'] : 'Invitado';
                            $telefono_mostrar = $inscripcion['usuario_telefono'] ? $inscripcion['usuario_telefono'] : '-';
                        ?>
                        <strong><?php echo htmlspecialchars($nombre_mostrar); ?></strong><br>
                        <small><?php echo htmlspecialchars($email_mostrar); ?></small><br>
                        <small><?php echo htmlspecialchars($telefono_mostrar); ?></small>
                    </td>
                    <td><?php echo ucfirst($inscripcion['categoria']); ?></td>
                    <td><?php echo $inscripcion['talla_camiseta'] ? $inscripcion['talla_camiseta'] : '-'; ?></td>
                    <td><?php echo date('d/m/Y H:i', strtotime($inscripcion['fecha_inscripcion'])); ?></td>
                    <td>
                        <form method="POST" action="" style="display:inline;">
                            <input type="hidden" name="action" value="cambiar_estado">
                            <input type="hidden" name="inscripcion_id" value="<?php echo $inscripcion['id']; ?>">
                            <select name="nuevo_estado" class="form-control form-control-sm" onchange="if(confirm('¿Cambiar estado?')) this.form.submit()" style="width: auto; display: inline-block;">
                                <option value="confirmada" <?php echo $inscripcion['estado'] == 'confirmada' ? 'selected' : ''; ?>>Confirmada</option>
                                <option value="pendiente" <?php echo $inscripcion['estado'] == 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                                <option value="cancelada" <?php echo $inscripcion['estado'] == 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                            </select>
                        </form>
                    </td>
                    <td>
                        <a href="view-inscription.php?id=<?php echo $inscripcion['id']; ?>" class="admin-btn admin-btn-outline btn-sm"><i class="fas fa-eye"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Paginación Simple -->
        <?php if ($total_paginas > 1): ?>
        <div class="admin-pagination">
             <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                <a href="?id=<?php echo $event_id; ?>&pagina=<?php echo $i; ?>" class="pagination-link <?php echo $i == $pagina ? 'active' : ''; ?>"><?php echo $i; ?></a>
             <?php endfor; ?>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div style="text-align: center; padding: 2rem;">
            <p>No se encontraron inscripciones para este evento.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Exportar datos -->
    <?php $export_type = 'inscriptions'; include '../../includes/export-buttons.php'; ?>
</div>

<?php include '../../includes/admin-footer.php'; ?>
