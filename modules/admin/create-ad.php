<?php
// modules/admin/create-ad.php
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $titulo = trim($_POST['titulo']);
    $url = trim($_POST['url']);
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin = !empty($_POST['fecha_fin']) ? $_POST['fecha_fin'] : NULL;
    $estado = $_POST['estado'];

    // Image Upload
    $imagen_url = '';
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === 0) {
        $upload_dir = '../../assets/img/ads/';
        // Ensure dir exists
        if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $file_ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($file_ext, $allowed)) {
            $new_name = uniqid('ad_') . '.' . $file_ext;
            if (move_uploaded_file($_FILES['imagen']['tmp_name'], $upload_dir . $new_name)) {
                $imagen_url = 'assets/img/ads/' . $new_name;
            } else {
                $error = 'Error al subir la imagen.';
            }
        } else {
            $error = 'Formato de imagen no permitido.';
        }
    } else {
        $error = 'La imagen es obligatoria.';
    }

    if (empty($error)) {
        $stmt = $db->prepare("INSERT INTO anuncios (titulo, imagen_url, enlace_url, fecha_inicio, fecha_fin, estado) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $titulo, $imagen_url, $url, $fecha_inicio, $fecha_fin, $estado);
        
        if ($stmt->execute()) {
            ob_clean();
            echo json_encode(['success' => true, 'message' => 'Anuncio creado correctamente.', 'redirect' => 'ads.php']);
            exit();
        } else {
            $error = 'Error al guardar en la base de datos: ' . $stmt->error;
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
            <h1><i class="fas fa-plus"></i> Nuevo Anuncio</h1>
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
                    <input type="text" name="titulo" class="form-control" required>
                </div>
                <div class="form-group col-md-6">
                    <label>URL de Destino</label>
                    <input type="url" name="url" class="form-control" placeholder="https://..." required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group col-md-6">
                    <label>Imagen del Banner</label>
                    <input type="file" name="imagen" class="form-control" accept="image/*" required>
                    <small class="text-muted">Recomendado: 1200x400 px, Formatos: JPG, PNG, WEBP</small>
                </div>
                <div class="form-group col-md-6">
                    <label>Estado</label>
                    <select name="estado" class="form-control">
                        <option value="activo">Activo</option>
                        <option value="inactivo">Inactivo</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group col-md-6">
                    <label>Fecha Inicio</label>
                    <input type="date" name="fecha_inicio" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group col-md-6">
                    <label>Fecha Fin (Opcional)</label>
                    <input type="date" name="fecha_fin" class="form-control">
                    <small class="text-muted">Dejar vacío para mostrar indefinidamente</small>
                </div>
            </div>

            <div class="form-actions" style="margin-top: 2rem;">
                <button type="submit" class="admin-btn admin-btn-primary">Guadar Anuncio</button>
            </div>
        </form>
    </div>
</div>

<?php include '../../includes/admin-footer.php'; ?>
