<?php
// modules/admin/contacts.php
require_once '../../config/config.php';
require_once '../../config/database.php';

// Verificar autenticación
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'organizador'])) {
    header('Location: ../auth/login.php');
    exit();
}

$db = getDB();

// Verificar y corregir la estructura de la tabla 'contactos' si es necesario
// Esto soluciona el error "Data truncated for column 'estado'"
try {
    $check_column = $db->query("SHOW COLUMNS FROM contactos LIKE 'estado'");
    if ($check_column && $check_column->num_rows > 0) {
        $column_info = $check_column->fetch_assoc();
        // Si es enum o varchar pequeño, lo cambiamos a VARCHAR(50)
        // La columna Type puede ser "enum('nuevo','pendiente')" o "varchar(10)" etc.
        // strpos devuelve false si no encuentra la cadena, por lo que usamos !== false para verificar si la encuentra
        if (strpos(strtolower($column_info['Type']), 'enum') !== false || 
            (strpos(strtolower($column_info['Type']), 'varchar') !== false && preg_match('/varchar\((\d+)\)/', strtolower($column_info['Type']), $matches) && $matches[1] < 20)) {
            $db->query("ALTER TABLE contactos MODIFY COLUMN estado VARCHAR(50) DEFAULT 'nuevo'");
        }
    } else {
        // Si la columna no existe (caso raro si ya hay datos), la creamos
        $db->query("ALTER TABLE contactos ADD COLUMN estado VARCHAR(50) DEFAULT 'nuevo'");
    }
} catch (Exception $e) {
    // Ignorar errores silenciosamente en producción, o loguear
    error_log("Error al verificar esquema de contactos: " . $e->getMessage());
}

// Variables para filtros y paginación
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
$pagina = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$por_pagina = 15;
$offset = ($pagina - 1) * $por_pagina;

// Construir consulta base
$query = "SELECT SQL_CALC_FOUND_ROWS * FROM contactos WHERE 1=1";
$params = [];
$types = '';

if ($filtro_estado) {
    if ($filtro_estado === 'nuevo') {
        $query .= " AND (estado = 'nuevo' OR estado IS NULL)"; // Asumimos NULL como nuevo por si acaso
    } else {
        $query .= " AND estado = ?";
        $params[] = $filtro_estado;
        $types .= 's';
    }
}

if ($busqueda) {
    $query .= " AND (nombre LIKE ? OR email LIKE ? OR asunto LIKE ? OR mensaje LIKE ?)";
    $busqueda_like = "%$busqueda%";
    $params[] = $busqueda_like;
    $params[] = $busqueda_like;
    $params[] = $busqueda_like;
    $params[] = $busqueda_like;
    $types .= 'ssss';
}

$query .= " ORDER BY fecha DESC LIMIT ? OFFSET ?";
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
$contactos = $result->fetch_all(MYSQLI_ASSOC);

// Obtener total de resultados
$total_resultados = $db->query("SELECT FOUND_ROWS()")->fetch_row()[0];
$total_paginas = ceil($total_resultados / $por_pagina);

$stmt->close();

// Procesar acciones POST
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $contacto_id = $_POST['contacto_id'] ?? 0;
    
    if ($action === 'marcar_leido' && $contacto_id) {
        $stmt = $db->prepare("UPDATE contactos SET estado = 'leido' WHERE id = ?");
        $stmt->bind_param("i", $contacto_id);
        if ($stmt->execute()) $success = 'Mensaje marcado como leído.';
        else $error = 'Error al actualizar.';
        $stmt->close();
    }
    elseif ($action === 'eliminar' && $contacto_id) {
        $stmt = $db->prepare("DELETE FROM contactos WHERE id = ?");
        $stmt->bind_param("i", $contacto_id);
        if ($stmt->execute()) $success = 'Mensaje eliminado.';
        else $error = 'Error al eliminar.';
        $stmt->close();
    }
    
    if ($success || $error) {
        // Redireccionar para evitar reenvío de formulario
        $redirect_url = "contacts.php?pagina=$pagina" . 
                        ($filtro_estado ? "&estado=$filtro_estado" : "") . 
                        ($busqueda ? "&busqueda=$busqueda" : "");
        echo "<script>window.location.href='$redirect_url';</script>";
        exit;
    }
}

include '../../includes/admin-header.php';
?>

<div class="admin-main">
    <div class="admin-header">
        <h1><i class="fas fa-envelope"></i> Mensajes de Contacto</h1>
    </div>

    <?php if ($error): ?>
    <div class="admin-alert admin-alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
    <div class="admin-alert admin-alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <!-- Filtros -->
    <div class="admin-filters">
        <form method="GET" action="">
            <div class="filter-row">
                <div class="form-group">
                    <label>Buscar</label>
                    <input type="text" name="busqueda" class="form-control" value="<?php echo htmlspecialchars($busqueda); ?>" placeholder="Nombre, email, asunto...">
                </div>
                <div class="form-group">
                    <label>Estado</label>
                    <select name="estado" class="form-control">
                        <option value="">Todos</option>
                        <option value="nuevo" <?php echo $filtro_estado === 'nuevo' ? 'selected' : ''; ?>>Nuevos</option>
                        <option value="leido" <?php echo $filtro_estado === 'leido' ? 'selected' : ''; ?>>Leídos</option>
                    </select>
                </div>
                <div>
                    <button type="submit" class="admin-btn admin-btn-primary"><i class="fas fa-search"></i> Filtrar</button>
                    <a href="contacts.php" class="admin-btn admin-btn-secondary"><i class="fas fa-redo"></i> Limpiar</a>
                </div>
            </div>
        </form>
    </div>

    <!-- Tabla -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h2><i class="fas fa-list"></i> Lista de Mensajes (<?php echo $total_resultados; ?>)</h2>
        </div>
        
        <?php if (!empty($contactos)): ?>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Remitente</th>
                        <th>Asunto</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($contactos as $contacto): 
                        $estado_class = ($contacto['estado'] === 'nuevo' || $contacto['estado'] === null) ? 'badge-warning' : 'badge-success';
                        $estado_text = ($contacto['estado'] === 'nuevo' || $contacto['estado'] === null) ? 'Nuevo' : 'Leído';
                    ?>
                    <tr>
                        <td><?php echo date('d/m/Y h:i A', strtotime($contacto['fecha'])); ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($contacto['nombre']); ?></strong><br>
                            <small><?php echo htmlspecialchars($contacto['email']); ?></small><br>
                            <small><?php echo htmlspecialchars($contacto['telefono']); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($contacto['asunto']); ?></td>
                        <td><span class="badge <?php echo $estado_class; ?>"><?php echo $estado_text; ?></span></td>
                        <td>
                            <div class="table-actions">
                                <button type="button" onclick="viewContact(<?php echo htmlspecialchars(json_encode($contacto)); ?>)" class="admin-btn admin-btn-outline action-btn" title="Ver">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <?php if ($contacto['estado'] === 'nuevo' || $contacto['estado'] === null): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('¿Marcar como leído?');">
                                    <input type="hidden" name="action" value="marcar_leido">
                                    <input type="hidden" name="contacto_id" value="<?php echo $contacto['id']; ?>">
                                    <button type="submit" class="admin-btn admin-btn-success action-btn" title="Marcar como leído">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar mensaje?');">
                                    <input type="hidden" name="action" value="eliminar">
                                    <input type="hidden" name="contacto_id" value="<?php echo $contacto['id']; ?>">
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
            <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
            <a href="?pagina=<?php echo $i; ?>&estado=<?php echo $filtro_estado; ?>&busqueda=<?php echo urlencode($busqueda); ?>" class="pagination-link <?php echo $i == $pagina ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        
        <?php else: ?>
        <p class="text-center p-4">No hay mensajes.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Modal -->
<div id="contactModal" class="modal" style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.5);">
    <div class="modal-content" style="background-color:#fff; margin:10% auto; padding:20px; border:1px solid #888; width:80%; max-width:600px; border-radius:8px;">
        <span onclick="closeModal()" class="close" style="color:#aaa; float:right; font-size:28px; font-weight:bold; cursor:pointer;">&times;</span>
        <h2 id="modalSubject"></h2>
        <div id="modalBody"></div>
    </div>
</div>

<script>
function viewContact(contacto) {
    document.getElementById('modalSubject').innerText = contacto.asunto;
    const body = `
        <p><strong>De:</strong> ${contacto.nombre} (${contacto.email})</p>
        <p><strong>Teléfono:</strong> ${contacto.telefono}</p>
        <p><strong>Fecha:</strong> ${contacto.fecha}</p>
        <hr>
        <p style="white-space: pre-wrap;">${contacto.mensaje}</p>
    `;
    document.getElementById('modalBody').innerHTML = body;
    document.getElementById('contactModal').style.display = 'block';
}

function closeModal() {
    document.getElementById('contactModal').style.display = 'none';
}

window.onclick = function(event) {
    if (event.target == document.getElementById('contactModal')) {
        closeModal();
    }
}
</script>

<?php include '../../includes/admin-footer.php'; ?>
