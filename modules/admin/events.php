<?php
// modules/admin/events.php
require_once '../../config/config.php';
require_once '../../config/database.php';


// session_start(); removed as it's handled in config.php
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'organizador'])) {
    header('Location: ../auth/login.php');
    exit();
}

$db = getDB();

// Variables para filtros y paginación
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : 'todos';
$filtro_tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$por_pagina = 10;
$offset = ($pagina - 1) * $por_pagina;

// Construir consulta base
$columns = "e.*, u.nombre as organizador_nombre, 
          (SELECT COUNT(*) FROM inscripciones i WHERE i.id_evento = e.id AND i.estado = 'confirmada') as total_inscritos";
$from_where = "FROM eventos e 
          LEFT JOIN usuarios u ON e.id_organizador = u.id 
          WHERE 1=1";

$params = [];
$types = '';

// Aplicar filtros
if ($filtro_estado !== 'todos') {
    $from_where .= " AND e.estado = ?";
    $params[] = $filtro_estado;
    $types .= 's';
}

if (!empty($filtro_tipo)) {
    $from_where .= " AND e.tipo_evento = ?";
    $params[] = $filtro_tipo;
    $types .= 's';
}

if (!empty($busqueda)) {
    $from_where .= " AND (e.nombre LIKE ? OR e.ubicacion LIKE ? OR u.nombre LIKE ?)";
    $busqueda_like = "%$busqueda%";
    $params[] = $busqueda_like;
    $params[] = $busqueda_like;
    $params[] = $busqueda_like;
    $types .= 'sss';
}

// Obtener total para paginación
$query_count = "SELECT COUNT(*) as total " . $from_where;
$stmt_count = $db->prepare($query_count);
if (!empty($params)) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$result_count = $stmt_count->get_result();
$total_eventos = $result_count->fetch_assoc()['total'];
$total_paginas = ceil($total_eventos / $por_pagina);
$stmt_count->close();

// Agregar orden y límite
$query = "SELECT " . $columns . " " . $from_where . " ORDER BY e.fecha_evento DESC LIMIT ? OFFSET ?";
$params[] = $por_pagina;
$params[] = $offset;
$types .= 'ii';

// Ejecutar consulta principal
$stmt = $db->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$eventos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Obtener estadísticas
$query_stats = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN estado = 'activo' THEN 1 ELSE 0 END) as activos,
    SUM(CASE WHEN estado = 'cancelado' THEN 1 ELSE 0 END) as cancelados,
    SUM(CASE WHEN estado = 'completado' THEN 1 ELSE 0 END) as completados,
    SUM(cupo_maximo - cupo_disponible) as total_inscripciones
    FROM eventos";
$stats = $db->query($query_stats)->fetch_assoc();

include '../../includes/admin-header.php';
?>

<div class="admin-main">
    <!-- Header -->
    <div class="admin-header">
        <h1><i class="fas fa-calendar-alt"></i> Gestión de Eventos</h1>
        <div class="admin-header-actions">
            <a href="create-event.php" class="admin-btn admin-btn-primary">
                <i class="fas fa-plus"></i> Nuevo Evento
            </a>
            <a href="dashboard.php" class="admin-btn admin-btn-outline">
                <i class="fas fa-arrow-left"></i> Volver al Dashboard
            </a>
        </div>
    </div>

    <!-- Estadísticas -->
    <div class="admin-stats">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="stat-number"><?php echo number_format($stats['total']); ?></div>
            <div class="stat-label">Total de Eventos</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-running"></i>
            </div>
            <div class="stat-number"><?php echo number_format($stats['activos']); ?></div>
            <div class="stat-label">Eventos Activos</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-number"><?php echo number_format($stats['total_inscripciones']); ?></div>
            <div class="stat-label">Total Inscripciones</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-trophy"></i>
            </div>
            <div class="stat-number"><?php echo number_format($stats['completados']); ?></div>
            <div class="stat-label">Eventos Completados</div>
        </div>
    </div>

    <!-- Filtros y Búsqueda -->
    <div class="admin-filters">
        <form method="GET" action="">
            <div class="filter-row">
                <div class="form-group">
                    <label for="busqueda"><i class="fas fa-search"></i> Buscar</label>
                    <input type="text" id="busqueda" name="busqueda" class="form-control" 
                           placeholder="Buscar por nombre, ubicación o organizador..." 
                           value="<?php echo htmlspecialchars($busqueda); ?>">
                </div>
                
                <div class="form-group">
                    <label for="estado"><i class="fas fa-filter"></i> Estado</label>
                    <select id="estado" name="estado" class="form-control">
                        <option value="todos" <?php echo $filtro_estado === 'todos' ? 'selected' : ''; ?>>Todos los estados</option>
                        <option value="activo" <?php echo $filtro_estado === 'activo' ? 'selected' : ''; ?>>Activo</option>
                        <option value="cancelado" <?php echo $filtro_estado === 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                        <option value="completado" <?php echo $filtro_estado === 'completado' ? 'selected' : ''; ?>>Completado</option>
                        <option value="pendiente" <?php echo $filtro_estado === 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="tipo"><i class="fas fa-tags"></i> Tipo</label>
                    <select id="tipo" name="tipo" class="form-control">
                        <option value="">Todos los tipos</option>
                        <option value="carrera" <?php echo $filtro_tipo === 'carrera' ? 'selected' : ''; ?>>Carrera</option>
                        <option value="zumba" <?php echo $filtro_tipo === 'zumba' ? 'selected' : ''; ?>>Zumba</option>
                        <option value="caminata" <?php echo $filtro_tipo === 'caminata' ? 'selected' : ''; ?>>Caminata</option>
                        <option value="charla" <?php echo $filtro_tipo === 'charla' ? 'selected' : ''; ?>>Charla</option>
                        <option value="curso" <?php echo $filtro_tipo === 'curso' ? 'selected' : ''; ?>>Curso</option>
                        <option value="taller" <?php echo $filtro_tipo === 'taller' ? 'selected' : ''; ?>>Taller</option>
                    </select>
                </div>
                
                <div>
                    <button type="submit" class="admin-btn admin-btn-primary">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                    <a href="events.php" class="admin-btn admin-btn-secondary">
                        <i class="fas fa-redo"></i> Limpiar
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Tabla de Eventos -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h2><i class="fas fa-list"></i> Lista de Eventos</h2>
            <span class="badge badge-primary">
                <?php echo number_format($total_eventos); ?> eventos encontrados
            </span>
        </div>
        
        <?php if (!empty($eventos)): ?>
        <div style="overflow-x: auto;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Evento</th>
                        <th>Tipo</th>
                        <th>Fecha</th>
                        <th>Ubicación</th>
                        <th>Organizador</th>
                        <th>Inscritos</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($eventos as $evento): 
                        $fecha_evento = strtotime($evento['fecha_evento']);
                        $es_pasado = $fecha_evento < time();
                    ?>
                    <tr>
                        <td><strong>#<?php echo $evento['id']; ?></strong></td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <?php if ($evento['imagen_url']): ?>
                                <img src="../../<?php echo htmlspecialchars($evento['imagen_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($evento['nombre']); ?>"
                                     style="width: 40px; height: 40px; border-radius: 4px; object-fit: cover;">
                                <?php else: ?>
                                <div style="width: 40px; height: 40px; background: linear-gradient(135deg, var(--admin-primary), var(--admin-primary-light)); 
                                            border-radius: 4px; display: flex; align-items: center; justify-content: center; color: white;">
                                    <?php echo substr($evento['nombre'], 0, 2); ?>
                                </div>
                                <?php endif; ?>
                                <div>
                                    <strong><?php echo htmlspecialchars($evento['nombre']); ?></strong><br>
                                    <small style="color: var(--admin-text-light);">
                                        <?php echo $evento['distancia']; ?> km • $<?php echo number_format($evento['precio'], 2); ?>
                                    </small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-info" style="background-color: var(--primary-light);">
                                <?php echo ucfirst($evento['tipo_evento']); ?>
                            </span>
                        </td>
                        <td>
                            <?php echo date('d/m/Y', $fecha_evento); ?><br>
                            <small style="color: var(--admin-text-light);">
                                <?php echo date('h:i A', $fecha_evento); ?>
                            </small>
                        </td>
                        <td><?php echo htmlspecialchars($evento['ubicacion']); ?></td>
                        <td>
                            <?php if ($evento['organizador_nombre']): ?>
                                <?php echo htmlspecialchars($evento['organizador_nombre']); ?>
                            <?php else: ?>
                                <span style="color: var(--admin-text-lighter);">Sistema</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="text-align: center;">
                                <strong><?php echo $evento['total_inscritos']; ?></strong><br>
                                <small style="color: var(--admin-text-light);">
                                    de <?php echo $evento['cupo_maximo']; ?>
                                </small>
                            </div>
                        </td>
                        <td>
                            <?php 
                            $badge_class = '';
                            switch($evento['estado']) {
                                case 'activo': $badge_class = 'badge-success'; break;
                                case 'cancelado': $badge_class = 'badge-danger'; break;
                                case 'completado': $badge_class = 'badge-info'; break;
                                case 'pendiente': $badge_class = 'badge-warning'; break;
                                default: $badge_class = 'badge-secondary';
                            }
                            ?>
                            <span class="badge <?php echo $badge_class; ?>">
                                <?php echo ucfirst($evento['estado']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="actions">
                                <a href="view-event.php?id=<?php echo $evento['id']; ?>" 
                                   class="admin-btn admin-btn-outline" title="Ver" style="padding: 0.5rem;">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit-event.php?id=<?php echo $evento['id']; ?>" 
                                   class="admin-btn admin-btn-secondary" title="Editar" style="padding: 0.5rem;">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="event-inscriptions.php?id=<?php echo $evento['id']; ?>" 
                                   class="admin-btn admin-btn-primary" title="Inscripciones" style="padding: 0.5rem;">
                                    <i class="fas fa-users"></i>
                                </a>
                                <?php if ($evento['estado'] === 'activo'): ?>
                                <a href="cancel-event.php?id=<?php echo $evento['id']; ?>" 
                                   class="admin-btn admin-btn-danger" title="Cancelar" style="padding: 0.5rem;"
                                   onclick="return confirm('¿Estás seguro de cancelar este evento?');">
                                    <i class="fas fa-times"></i>
                                </a>
                                <?php endif; ?>
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
            <a href="?pagina=<?php echo $pagina - 1; ?>&estado=<?php echo $filtro_estado; ?>&tipo=<?php echo $filtro_tipo; ?>&busqueda=<?php echo urlencode($busqueda); ?>" 
               class="pagination-link">
                <i class="fas fa-chevron-left"></i>
            </a>
            <?php endif; ?>

            <?php 
            $inicio = max(1, $pagina - 2);
            $fin = min($total_paginas, $pagina + 2);
            
            for ($i = $inicio; $i <= $fin; $i++): 
            ?>
            <a href="?pagina=<?php echo $i; ?>&estado=<?php echo $filtro_estado; ?>&tipo=<?php echo $filtro_tipo; ?>&busqueda=<?php echo urlencode($busqueda); ?>" 
               class="pagination-link <?php echo $i == $pagina ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>

            <?php if ($pagina < $total_paginas): ?>
            <a href="?pagina=<?php echo $pagina + 1; ?>&estado=<?php echo $filtro_estado; ?>&tipo=<?php echo $filtro_tipo; ?>&busqueda=<?php echo urlencode($busqueda); ?>" 
               class="pagination-link">
                <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div style="text-align: center; padding: 3rem;">
            <i class="fas fa-calendar-times" style="font-size: 4rem; color: var(--admin-text-lighter); margin-bottom: 1rem;"></i>
            <h3 style="color: var(--admin-text-light); margin-bottom: 1rem;">No se encontraron eventos</h3>
            <p>No hay eventos que coincidan con tu búsqueda.</p>
            <a href="events.php" class="admin-btn admin-btn-primary mt-3">
                <i class="fas fa-redo"></i> Ver todos los eventos
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Exportar datos -->
    <?php $export_type = 'events'; include '../../includes/export-buttons.php'; ?>
</div>

<script>
// Selección múltiple para acciones masivas
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('selectAll');
    const eventCheckboxes = document.querySelectorAll('.event-checkbox');
    
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            eventCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
    }
    
    // Validar que al menos un checkbox esté seleccionado para acciones masivas
    document.querySelectorAll('.bulk-action-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            const checked = document.querySelectorAll('.event-checkbox:checked');
            if (checked.length === 0) {
                e.preventDefault();
                alert('Por favor, selecciona al menos un evento.');
            }
        });
    });
});

// Confirmación para acciones importantes
function confirmAction(action, eventId) {
    if (confirm(`¿Estás seguro de ${action} este evento?`)) {
        window.location.href = `action-event.php?id=${eventId}&action=${action}`;
    }
}
</script>

<?php include '../../includes/admin-footer.php'; ?>