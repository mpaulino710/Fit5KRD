<?php
// modules/admin/crear-promocion.php
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
    
    $codigo = strtoupper(trim($_POST['codigo']));
    $porcentaje = floatval($_POST['porcentaje']);
    $cantidad = intval($_POST['cantidad']);
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin = $_POST['fecha_fin'];
    $id_evento = !empty($_POST['id_evento']) ? intval($_POST['id_evento']) : NULL;

    if (empty($codigo) || $porcentaje <= 0 || $cantidad <= 0 || empty($fecha_inicio) || empty($fecha_fin)) {
        $response['message'] = "Por favor completa todos los campos obligatorios.";
    } elseif ($porcentaje > 100) {
        $response['message'] = "El porcentaje no puede ser mayor a 100.";
    } else {
        // Verificar si existe
        $stmt = $db->prepare("SELECT id FROM promociones WHERE codigo = ?");
        $stmt->bind_param("s", $codigo);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $response['message'] = "El código ya existe.";
        } else {
            // Insertar
            $query = "INSERT INTO promociones (codigo, porcentaje, cantidad, fecha_inicio, fecha_fin, id_evento, estado)
                      VALUES (?, ?, ?, ?, ?, ?, 'activo')";
            $stmt = $db->prepare($query);
            $stmt->bind_param("sdissi", $codigo, $porcentaje, $cantidad, $fecha_inicio, $fecha_fin, $id_evento);
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = "Promoción creada exitosamente.";
                $response['redirect'] = "promociones.php";
            } else {
                $response['message'] = "Error al crear: " . $db->error;
            }
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
        <h1><i class="fas fa-plus-circle"></i> Nueva Promoción</h1>
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
                <h3>Detalles del Código</h3>
                <div class="row">
                    <div class="form-group col-md-6">
                        <label for="codigo">Código Promocional *</label>
                        <input type="text" id="codigo" name="codigo" class="form-control" required 
                               placeholder="Ej: VERANO2026" style="text-transform: uppercase;">
                        <small class="form-text text-muted">Se guardará en mayúsculas.</small>
                    </div>
                    
                    <div class="form-group col-md-6">
                        <label for="porcentaje">Porcentaje de Descuento (%) *</label>
                        <input type="number" id="porcentaje" name="porcentaje" class="form-control" 
                               required min="1" max="100" step="0.1" placeholder="Ej: 15">
                    </div>
                </div>

                <div class="row">
                    <div class="form-group col-md-4">
                        <label for="cantidad">Cantidad Total de Usos *</label>
                        <input type="number" id="cantidad" name="cantidad" class="form-control" 
                               required min="1" placeholder="Ej: 100">
                    </div>

                    <div class="form-group col-md-4">
                        <label for="fecha_inicio">Fecha Inicio *</label>
                        <input type="datetime-local" id="fecha_inicio" name="fecha_inicio" class="form-control" required>
                    </div>

                    <div class="form-group col-md-4">
                        <label for="fecha_fin">Fecha Fin *</label>
                        <input type="datetime-local" id="fecha_fin" name="fecha_fin" class="form-control" required>
                    </div>
                </div>

                <div class="row">
                    <div class="form-group col-md-12">
                        <label for="id_evento">Evento (Opcional)</label>
                        <select id="id_evento" name="id_evento" class="form-control">
                            <option value="">-- Aplica a todos los eventos --</option>
                            <?php foreach ($eventos as $ev): ?>
                                <option value="<?php echo $ev['id']; ?>">
                                    <?php echo htmlspecialchars($ev['nombre']) . " (" . date('d/m/Y', strtotime($ev['fecha_evento'])) . ")"; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Si seleccionas un evento, el código solo funcionará para ese evento.</small>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="admin-btn admin-btn-primary">
                    <i class="fas fa-save"></i> Guardar Promoción
                </button>
            </div>
        </form>
    </div>
</div>

<?php include '../../includes/admin-footer.php'; ?>
