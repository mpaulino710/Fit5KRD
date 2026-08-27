<?php
// modules/admin/crear-oferta.php
require_once '../../config/config.php';
require_once '../../config/database.php';


// session_start(); removed as it's handled in config.php
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'organizador'])) {
    header('Location: ../auth/login.php');
    exit();
}

$db = getDB();
$error = '';
$success = '';

// Obtener eventos para el select
$eventos = [];
$res = $db->query("SELECT id, nombre, fecha_evento FROM eventos WHERE fecha_evento >= CURDATE() ORDER BY fecha_evento ASC");
if ($res) {
    $eventos = $res->fetch_all(MYSQLI_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];

    $nombre = trim($_POST['nombre']);
    $porcentaje = floatval($_POST['porcentaje']);
    $cantidad = !empty($_POST['cantidad']) ? intval($_POST['cantidad']) : 1000000; // Default high number if not specified
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin = $_POST['fecha_fin'];
    $id_evento = !empty($_POST['id_evento']) ? intval($_POST['id_evento']) : NULL;

    if (empty($nombre) || $porcentaje <= 0 || empty($fecha_inicio) || empty($fecha_fin)) {
        $response['message'] = "Por favor completa todos los campos obligatorios.";
    } elseif ($porcentaje > 100) {
        $response['message'] = "El porcentaje no puede ser mayor a 100.";
    } else {
        // Insertar
        $query = "INSERT INTO promociones (tipo, nombre, porcentaje, cantidad, fecha_inicio, fecha_fin, id_evento, estado)
                  VALUES ('oferta', ?, ?, ?, ?, ?, ?, 'activo')";
        $stmt = $db->prepare($query);
        $stmt->bind_param("sdisss", $nombre, $porcentaje, $cantidad, $fecha_inicio, $fecha_fin, $id_evento);
        
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = "Oferta creada exitosamente.";
            $response['redirect'] = "promociones.php";
        } else {
            $response['message'] = "Error al crear: " . $db->error;
        }
    }
    
    ob_clean();
    echo json_encode($response);
    exit;
}

include '../../includes/admin-header.php';
?>

<div class="admin-main">
    <div class="admin-header">
        <h1><i class="fas fa-plus-circle"></i> Nueva Oferta Temporal</h1>
        <div class="admin-header-actions">
            <a href="promociones.php" class="admin-btn admin-btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="admin-alert admin-alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="admin-alert admin-alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <div class="admin-card">
        <form method="POST" action="" class="admin-form">
            <div class="form-section">
                <h3>Detalles de la Oferta</h3>
                <p class="text-muted">Las ofertas temporales se aplican automáticamente a las inscripciones durante el periodo de vigencia.</p>
                <div class="row">
                    <div class="form-group col-md-6">
                        <label for="nombre">Nombre de la Oferta *</label>
                        <input type="text" id="nombre" name="nombre" class="form-control" required 
                               placeholder="Ej: Descuento Early Bird">
                    </div>
                    
                    <div class="form-group col-md-6">
                        <label for="porcentaje">Porcentaje de Descuento (%) *</label>
                        <input type="number" id="porcentaje" name="porcentaje" class="form-control" 
                               required min="1" max="100" step="0.1" placeholder="Ej: 15">
                    </div>
                </div>

                <div class="row">
                    <div class="form-group col-md-6">
                        <label for="fecha_inicio">Fecha Inicio *</label>
                        <input type="datetime-local" id="fecha_inicio" name="fecha_inicio" class="form-control" required>
                    </div>

                    <div class="form-group col-md-6">
                        <label for="fecha_fin">Fecha Fin *</label>
                        <input type="datetime-local" id="fecha_fin" name="fecha_fin" class="form-control" required>
                    </div>
                </div>

                <div class="row">
                    <div class="form-group col-md-6">
                        <label for="id_evento">Evento (Opcional)</label>
                        <select id="id_evento" name="id_evento" class="form-control">
                            <option value="">-- Aplica a todos los eventos --</option>
                            <?php foreach ($eventos as $ev): ?>
                                <option value="<?php echo $ev['id']; ?>">
                                    <?php echo htmlspecialchars($ev['nombre']) . " (" . date('d/m/Y', strtotime($ev['fecha_evento'])) . ")"; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Si seleccionas un evento, la oferta solo funcionará para ese evento.</small>
                    </div>

                    <div class="form-group col-md-6">
                        <label for="cantidad">Límite de usos (Opcional)</label>
                        <input type="number" id="cantidad" name="cantidad" class="form-control" 
                               min="1" placeholder="Dejar en blanco para ilimitado">
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="admin-btn admin-btn-primary">
                    <i class="fas fa-save"></i> Guardar Oferta
                </button>
            </div>
        </form>
    </div>
</div>

<?php include '../../includes/admin-footer.php'; ?>
