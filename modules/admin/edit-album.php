<?php
// modules/admin/edit-album.php
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

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: gallery.php');
    exit();
}

$album_id = intval($_GET['id']);

// Obtener detalles del álbum
$stmt = $db->prepare("SELECT a.*, e.nombre as evento_nombre FROM albumes a JOIN eventos e ON a.id_evento = e.id WHERE a.id = ?");
$stmt->bind_param("i", $album_id);
$stmt->execute();
$album = $stmt->get_result()->fetch_assoc();

if (!$album) {
    header('Location: gallery.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];
    
    $titulo = trim($_POST['titulo']);
    $descripcion = trim($_POST['descripcion']);
    $estado = $_POST['estado'];
    
    if (empty($titulo)) {
        $response['message'] = 'El título del álbum es obligatorio.';
    } else {
        $stmt = $db->prepare("UPDATE albumes SET titulo = ?, descripcion = ?, estado = ? WHERE id = ?");
        $stmt->bind_param("sssi", $titulo, $descripcion, $estado, $album_id);
        
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Álbum actualizado correctamente.';
            $response['redirect'] = 'gallery.php';
        } else {
            $response['message'] = 'Error actualizando el álbum: ' . $db->error;
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
        <h1><i class="fas fa-edit"></i> Editar Álbum</h1>
        <div class="admin-header-actions">
            <a href="manage-album.php?id=<?php echo $album_id; ?>" class="admin-btn admin-btn-primary">
                <i class="fas fa-camera"></i> Gestionar Fotos
            </a>
            <a href="gallery.php" class="admin-btn admin-btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver a Galería
            </a>
        </div>
    </div>

    <?php if ($error): ?>
    <div class="admin-alert admin-alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="admin-alert admin-alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <div class="admin-card" style="max-width: 600px; margin: 0 auto;">
        <form method="POST" action="" class="admin-form">
            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label>Evento Relacionado</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($album['evento_nombre']); ?>" disabled>
                <small class="text-muted">El evento relacionado no se puede cambiar.</small>
            </div>
            
            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label for="titulo">Título del Álbum *</label>
                <input type="text" name="titulo" id="titulo" class="form-control" required value="<?php echo htmlspecialchars($album['titulo']); ?>">
            </div>
            
            <div class="form-group" style="margin-bottom: 2rem;">
                <label for="descripcion">Descripción</label>
                <textarea name="descripcion" id="descripcion" class="form-control" rows="3"><?php echo htmlspecialchars($album['descripcion']); ?></textarea>
            </div>

            <div class="form-group" style="margin-bottom: 2rem;">
                <label for="estado">Estado</label>
                <select name="estado" id="estado" class="form-control">
                    <option value="publico" <?php echo $album['estado'] === 'publico' ? 'selected' : ''; ?>>Público</option>
                    <option value="privado" <?php echo $album['estado'] === 'privado' ? 'selected' : ''; ?>>Privado</option>
                </select>
            </div>
            
            <button type="submit" class="admin-btn admin-btn-primary" style="width: 100%;">
                <i class="fas fa-save"></i> Guardar Cambios
            </button>
        </form>
    </div>
</div>

<?php include '../../includes/admin-footer.php'; ?>
