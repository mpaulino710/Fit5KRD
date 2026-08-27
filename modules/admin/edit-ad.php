<?php
// modules/admin/edit-ad.php
require_once '../../config/config.php';
require_once '../../config/database.php';


// session_start(); removed as it's handled in config.php
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'organizador'])) {
    header('Location: ../auth/login.php');
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ads.php');
    exit();
}

$ad_id = intval($_GET['id']);
$db = getDB();
$stmt = $db->prepare("SELECT * FROM anuncios WHERE id = ?");
$stmt->bind_param("i", $ad_id);
$stmt->execute();
$anuncio = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$anuncio) {
    header('Location: ads.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $titulo = trim($_POST['titulo']);
    $url = trim($_POST['url']);
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin = !empty($_POST['fecha_fin']) ? $_POST['fecha_fin'] : NULL;
    $estado = $_POST['estado'];

    // Image Upload (Optional on Edit)
    $imagen_url = $anuncio['imagen_url'];
    
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === 0) {
        $upload_dir = '../../assets/img/ads/';
        if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $file_ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($file_ext, $allowed)) {
            $new_name = uniqid('ad_') . '.' . $file_ext;
            if (move_uploaded_file($_FILES['imagen']['tmp_name'], $upload_dir . $new_name)) {
                $imagen_url = 'assets/img/ads/' . $new_name;
            } else {
                $error = 'Error al subir la nueva imagen.';
            }
        } else {
            $error = 'Formato de imagen no permitido.';
        }
    }

    if (empty($error)) {
        $stmt = $db->prepare("UPDATE anuncios SET titulo = ?, imagen_url = ?, enlace_url = ?, fecha_inicio = ?, fecha_fin = ?, estado = ? WHERE id = ?");
        $stmt->bind_param("ssssssi", $titulo, $imagen_url, $url, $fecha_inicio, $fecha_fin, $estado, $ad_id);
        
        if ($stmt->execute()) {
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Anuncio actualizado correctamente.', 'redirect' => 'ads.php']);
            exit();
        } else {
            $error = 'Error al actualizar: ' . $stmt->error;
        }
        $stmt->close();
    }
    
    ob_clean();
    echo json_encode(['success' => false, 'message' => $error]);
    exit();
}

include '../../includes/admin-header.php';
?>

<div class="admin-main">
    <div class="admin-header">
        <div>
            <h1><i class="fas fa-edit"></i> Editar Anuncio</h1>
        </div>
        <div class="admin-header-actions">
            <a href="ads.php" class="admin-btn admin-btn-secondary">
                <i class="fas fa-arrow-left"></i> Cancelar
            </a>
        </div>
    </div>

    <div class="admin-card">
        <?php if ($error): ?>
        <div class="admin-alert admin-alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
        <div class="admin-alert admin-alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data" class="admin-form">
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label>Título (Descripción interna)</label>
                    <input type="text" name="titulo" class="form-control" value="<?php echo htmlspecialchars($anuncio['titulo']); ?>" required>
                </div>
                <div class="form-group col-md-6">
                    <label>URL de Destino</label>
                    <input type="url" name="url" class="form-control" value="<?php echo htmlspecialchars($anuncio['enlace_url']); ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group col-md-6">
                    <label>Imagen del Banner</label>
                    <div style="margin-bottom: 10px;">
                        <img src="../../<?php echo htmlspecialchars($anuncio['imagen_url']); ?>" style="max-width: 100%; height: 100px; object-fit: cover; border-radius: 4px;">
                    </div>
                    <input type="file" name="imagen" class="form-control" accept="image/*">
                    <small class="text-muted">Subir solo si deseas reemplazar la imagen actual.</small>
                </div>
                <div class="form-group col-md-6">
                    <label>Estado</label>
                    <select name="estado" class="form-control">
                        <option value="activo" <?php echo $anuncio['estado'] === 'activo' ? 'selected' : ''; ?>>Activo</option>
                        <option value="inactivo" <?php echo $anuncio['estado'] === 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group col-md-6">
                    <label>Fecha Inicio</label>
                    <input type="date" name="fecha_inicio" class="form-control" value="<?php echo date('Y-m-d', strtotime($anuncio['fecha_inicio'])); ?>" required>
                </div>
                <div class="form-group col-md-6">
                    <label>Fecha Fin (Opcional)</label>
                    <input type="date" name="fecha_fin" class="form-control" value="<?php echo $anuncio['fecha_fin'] ? date('Y-m-d', strtotime($anuncio['fecha_fin'])) : ''; ?>">
                    <small class="text-muted">Dejar vacío para mostrar indefinidamente</small>
                </div>
            </div>

            <div class="form-actions" style="margin-top: 2rem;">
                <button type="submit" class="admin-btn admin-btn-primary">Actualizar Anuncio</button>
            </div>
        </form>
    </div>
</div>

<?php include '../../includes/admin-footer.php'; ?>
