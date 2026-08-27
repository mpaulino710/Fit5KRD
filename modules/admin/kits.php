<?php
// modules/admin/kits.php
require_once '../../config/config.php';
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'organizador'])) {
    header('Location: ../auth/login.php');
    exit();
}

$db = getDB();

// Variables para filtros y paginación
if (isset($_GET['evento'])) {
    $filtro_evento = intval($_GET['evento']);
} else {
    $prox_evento_query = "SELECT id FROM eventos WHERE fecha_evento >= CURDATE() ORDER BY fecha_evento ASC LIMIT 1";
    $prox_evento_result = $db->query($prox_evento_query);
    if ($prox_evento_result && $prox_evento_result->num_rows > 0) {
        $filtro_evento = intval($prox_evento_result->fetch_assoc()['id']);
    } else {
        $filtro_evento = 0;
    }
}
$filtro_estado_kit = isset($_GET['estado_kit']) ? $_GET['estado_kit'] : '';
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$por_pagina = 15;
$offset = ($pagina - 1) * $por_pagina;

// Construir consulta base - Solo mostrar pagos completados y estados válidos ('confirmada' o 'pendiente')
$query = "SELECT SQL_CALC_FOUND_ROWS
          i.*,
          u.nombre as usuario_nombre,
          u.apellido as usuario_apellido,
          u.email as usuario_email,
          e.nombre as evento_nombre,
          e.fecha_evento,
          p.estado as estado_pago
          FROM inscripciones i
          LEFT JOIN usuarios u ON i.id_usuario = u.id
          JOIN eventos e ON i.id_evento = e.id
          JOIN pagos p ON p.id_inscripcion = i.id
          WHERE i.estado != 'cancelada' AND p.estado = 'completado'";

$params = [];
$types = '';

if ($filtro_evento) {
    $query .= " AND i.id_evento = ?";
    $params[] = $filtro_evento;
    $types .= 'i';
}

if ($filtro_estado_kit !== '') {
    $query .= " AND i.kit_entregado = ?";
    $params[] = intval($filtro_estado_kit);
    $types .= 'i';
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
$eventos_query = "SELECT id, nombre FROM eventos ORDER BY fecha_evento DESC";
$eventos = $db->query($eventos_query)->fetch_all(MYSQLI_ASSOC);

// Obtener estadísticas
$stats_query = "SELECT
    COUNT(*) as total,
    SUM(CASE WHEN kit_entregado = 1 THEN 1 ELSE 0 END) as entregados,
    SUM(CASE WHEN kit_entregado = 0 THEN 1 ELSE 0 END) as pendientes
FROM inscripciones i
JOIN pagos p ON p.id_inscripcion = i.id
WHERE i.estado != 'cancelada' AND p.estado = 'completado'";

$stats = $db->query($stats_query)->fetch_assoc();

include '../../includes/admin-header.php';
?>

<div class="admin-main">
    <!-- Header -->
    <div class="admin-header">
        <h1><i class="fas fa-box-open"></i> Entrega de Kits</h1>
        <div class="admin-header-actions">
        </div>
    </div>

    <div id="notification-area"></div>

    <!-- Estadísticas -->
    <div class="admin-stats">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-clipboard-list"></i>
            </div>
            <div class="stat-number"><?php echo number_format($stats['total']); ?></div>
            <div class="stat-label">Kits Totales</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon stat-icon-success">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-number" id="stats-entregados"><?php echo number_format($stats['entregados']); ?></div>
            <div class="stat-label">Entregados</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon stat-icon-warning">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-number" id="stats-pendientes"><?php echo number_format($stats['pendientes']); ?></div>
            <div class="stat-label">Pendientes</div>
        </div>
    </div>

    <!-- Filtros y Búsqueda -->
    <div class="admin-filters">
        <form method="GET" action="">
            <div class="filter-row">
                <div class="form-group">
                    <label for="busqueda"><i class="fas fa-search"></i> Buscar</label>
                    <input type="text" id="busqueda" name="busqueda" class="form-control"
                           placeholder="Nombre, email o n° corredor"
                           value="<?php echo htmlspecialchars($busqueda); ?>">
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

                <div class="form-group">
                    <label for="estado_kit"><i class="fas fa-box"></i> Estado Kit</label>
                    <select id="estado_kit" name="estado_kit" class="form-control">
                        <option value="">Todos</option>
                        <option value="1" <?php echo $filtro_estado_kit === '1' ? 'selected' : ''; ?>>Entregados</option>
                        <option value="0" <?php echo $filtro_estado_kit === '0' ? 'selected' : ''; ?>>Pendientes</option>
                    </select>
                </div>

                <div>
                    <button type="submit" class="admin-btn admin-btn-primary">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                    <a href="kits.php" class="admin-btn admin-btn-secondary">
                        <i class="fas fa-redo"></i> Limpiar
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Tabla de Kits -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h2><i class="fas fa-list"></i> Lista para Entrega de Kits</h2>
            <span class="badge badge-primary">
                <?php echo number_format($total_resultados); ?> registros
            </span>
        </div>

        <?php if (!empty($inscripciones)): ?>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Participante</th>
                        <th>Evento / Talla</th>
                        <th>N° Corredor</th>
                        <th>Estado Pago</th>
                        <th>Estado Kit</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inscripciones as $inscripcion):
                        $is_entregado = (int)$inscripcion['kit_entregado'] === 1;
                    ?>
                    <tr id="row-<?php echo $inscripcion['id']; ?>">
                        <td><strong>#<?php echo $inscripcion['id']; ?></strong></td>
                        <td>
                            <div class="user-info">
                                <div>
                                    <?php 
                                        $nombre_mostrar = $inscripcion['usuario_nombre'] ? ($inscripcion['usuario_nombre'] . ' ' . $inscripcion['usuario_apellido']) : ($inscripcion['nombre'] . ' ' . $inscripcion['apellido']);
                                        $email_mostrar = $inscripcion['usuario_email'] ?: $inscripcion['email'];
                                    ?>
                                    <strong><?php echo htmlspecialchars($nombre_mostrar); ?></strong><br>
                                    <small class="user-email"><?php echo htmlspecialchars($email_mostrar); ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($inscripcion['evento_nombre']); ?></strong><br>
                            <?php if (!empty($inscripcion['talla_camiseta'])): ?>
                                <span class="badge badge-secondary">Talla: <?php echo htmlspecialchars($inscripcion['talla_camiseta']); ?></span>
                            <?php else: ?>
                                <span class="text-muted"><small>No especificada</small></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($inscripcion['numero_corredor']): ?>
                            <span class="badge badge-primary runner-number">
                                <?php echo htmlspecialchars($inscripcion['numero_corredor']); ?>
                            </span>
                            <?php else: ?>
                            <span class="badge badge-secondary">S/N</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-success">
                                <i class="fas fa-check"></i> Pagado
                            </span>
                        </td>
                        <td id="status-cell-<?php echo $inscripcion['id']; ?>">
                            <?php if ($is_entregado): ?>
                            <span class="badge badge-success">
                                <i class="fas fa-box-open"></i> Entregado
                            </span>
                            <?php else: ?>
                            <span class="badge badge-warning">
                                <i class="fas fa-clock"></i> Pendiente
                            </span>
                            <?php endif; ?>
                        </td>
                        <td id="action-cell-<?php echo $inscripcion['id']; ?>">
                            <?php if (!$is_entregado): ?>
                            <button onclick="marcarKit(<?php echo $inscripcion['id']; ?>)" class="admin-btn admin-btn-primary action-btn" title="Marcar como entregado">
                                <i class="fas fa-check-circle"></i> Entregar
                            </button>
                            <?php else: ?>
                                <?php if ($inscripcion['fecha_entrega_kit']): ?>
                                <small class="text-muted">Entregado el <?php echo date('d/m/Y', strtotime($inscripcion['fecha_entrega_kit'])); ?></small>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        <?php if ($total_paginas > 1): ?>
        <div class="admin-pagination">
            <?php
            $inicio = max(1, $pagina - 2);
            $fin = min($total_paginas, $pagina + 2);

            for ($i = $inicio; $i <= $fin; $i++):
            ?>
            <a href="?pagina=<?php echo $i; ?>&estado_kit=<?php echo $filtro_estado_kit; ?>&evento=<?php echo $filtro_evento; ?>&busqueda=<?php echo urlencode($busqueda); ?>"
               class="pagination-link <?php echo $i == $pagina ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-box-open"></i>
            <h3>No se encontraron registros para entrega</h3>
            <p>No hay kits pendientes que coincidan con tu búsqueda</p>
            <a href="kits.php" class="admin-btn admin-btn-primary">
                <i class="fas fa-redo"></i> Ver todos
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function showAlert(message, type = 'success') {
    const area = document.getElementById('notification-area');
    area.innerHTML = `
        <div class="admin-alert admin-alert-${type}">
            <i class="fas fa-${type === 'success' ? 'check' : 'exclamation'}-circle"></i>
            <span>${message}</span>
        </div>
    `;
    setTimeout(() => { area.innerHTML = ''; }, 5000);
}

function marcarKit(id) {
    if (!confirm('¿Estás seguro de marcar este kit como entregado? Se enviará un correo al participante.')) return;

    fetch('process-kit.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=marcar_entregado&inscripcion_id=' + id
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message);
            
            // Update UI
            document.getElementById('status-cell-' + id).innerHTML = `
                <span class="badge badge-success">
                    <i class="fas fa-box-open"></i> Entregado
                </span>
            `;
            
            const today = new Date().toLocaleDateString('es-ES');
            document.getElementById('action-cell-' + id).innerHTML = `
                <small class="text-muted">Entregado recientemente</small>
            `;

            // Update stats
            let elEntregados = document.getElementById('stats-entregados');
            let elPendientes = document.getElementById('stats-pendientes');
            if (elEntregados && elPendientes) {
                elEntregados.innerText = parseInt(elEntregados.innerText.replace(/,/g, '')) + 1;
                elPendientes.innerText = Math.max(0, parseInt(elPendientes.innerText.replace(/,/g, '')) - 1);
            }
        } else {
            showAlert(data.message || 'Error al procesar la solicitud', 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Error de conexión al servidor', 'danger');
    });
}
</script>

<?php include '../../includes/admin-footer.php'; ?>
