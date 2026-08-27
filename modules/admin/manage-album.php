<?php
// modules/admin/manage-album.php
require_once '../../config/config.php';
require_once '../../config/database.php';


// session_start(); removed as it's handled in config.php
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'organizador'])) {
    header('Location: ../auth/login.php');
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: gallery.php');
    exit();
}

$album_id = intval($_GET['id']);
$db = getDB();
$msg = '';
$error = '';

// Obtener info del álbum
$stmt = $db->prepare("SELECT a.*, e.nombre as evento_nombre FROM albumes a JOIN eventos e ON a.id_evento = e.id WHERE a.id = ?");
$stmt->bind_param("i", $album_id);
$stmt->execute();
$album = $stmt->get_result()->fetch_assoc();

if (!$album) {
    header('Location: gallery.php');
    exit();
}

// Procesar Subida de Fotos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['fotos'])) {
    $upload_dir = "../../uploads/gallery/$album_id/";
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $uploaded_count = 0;
    $errors = [];
    
    // Reordenar el array de $_FILES
    $files = $_FILES['fotos'];
    $count = count($files['name']);
    
    for ($i = 0; $i < $count; $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_OK) {
            $tmp_name = $files['tmp_name'][$i];
            $name = basename($files['name'][$i]);
            $size = $files['size'][$i];
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            
            // 1. Validar tamaño (Max 32MB)
            $max_size = 32 * 1024 * 1024; // 32MB
            if ($size > $max_size) {
                $errors[] = "$name excede el tamaño máximo permitido de 32MB";
                continue;
            }

            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $new_name = uniqid() . '.' . $ext;
                $target = $upload_dir . $new_name;
                
                // 2. Redimensionar si es necesario (Max 1080x1080)
                list($width, $height) = getimagesize($tmp_name);
                $max_dim = 1080;
                
                if ($width > $max_dim || $height > $max_dim) {
                    $ratio = $width / $height;
                    if ($width > $height) {
                        $new_width = $max_dim;
                        $new_height = $max_dim / $ratio;
                    } else {
                        $new_height = $max_dim;
                        $new_width = $max_dim * $ratio;
                    }
                    
                    $src = null;
                    $src = null;
                    $exif = null;

                    switch($ext) {
                        case 'jpg':
                        case 'jpeg': 
                            $src = imagecreatefromjpeg($tmp_name); 
                            // Read EXIF data for orientation
                            if (function_exists('exif_read_data')) {
                                $exif = @exif_read_data($tmp_name);
                            }
                            break;
                        case 'png': $src = imagecreatefrompng($tmp_name); break;
                        case 'webp': $src = imagecreatefromwebp($tmp_name); break;
                    }
                    
                    // Handle Orientation
                    if ($src && $exif && !empty($exif['Orientation'])) {
                        switch ($exif['Orientation']) {
                            case 3:
                                $src = imagerotate($src, 180, 0);
                                break;
                            case 6:
                                $src = imagerotate($src, -90, 0);
                                // Adjust dimensions after rotation if 90/270 degrees
                                $temp = $width;
                                $width = $height;
                                $height = $temp;
                                break;
                            case 8:
                                $src = imagerotate($src, 90, 0);
                                // Adjust dimensions after rotation if 90/270 degrees
                                $temp = $width;
                                $width = $height;
                                $height = $temp;
                                break;
                        }
                    }
                    
                    // Recalculate dimensions for resize based on potentially weird rotated dimensions
                    // Need to check if we still need to resize after rotation
                    if ($width > $max_dim || $height > $max_dim) {
                         $ratio = $width / $height;
                        if ($width > $height) {
                            $new_width = $max_dim;
                            $new_height = $max_dim / $ratio;
                        } else {
                            $new_height = $max_dim;
                            $new_width = $max_dim * $ratio;
                        }
                    } else {
                        // If it fits after rotation, just use current dimensions
                        $new_width = $width;
                        $new_height = $height;
                    }
                    
                    if ($src) {
                        $dst = imagecreatetruecolor($new_width, $new_height);
                        
                        // Mantener transparencia
                        if ($ext == 'png' || $ext == 'webp') {
                            imagealphablending($dst, false);
                            imagesavealpha($dst, true);
                            $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
                            imagefilledrectangle($dst, 0, 0, $new_width, $new_height, $transparent);
                        }
                        
                        imagecopyresampled($dst, $src, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
                        
                        // Guardar la imagen redimensionada directamente al destino
                        $saved = false;
                        switch($ext) {
                            case 'jpg':
                            case 'jpeg': $saved = imagejpeg($dst, $target, 90); break;
                            case 'png': $saved = imagepng($dst, $target); break;
                            case 'webp': $saved = imagewebp($dst, $target, 90); break;
                        }
                        
                        imagedestroy($src);
                        imagedestroy($dst);
                        
                        if ($saved) {
                             $db_url = "uploads/gallery/$album_id/$new_name";
                             $db->query("INSERT INTO fotos (id_album, url_foto) VALUES ($album_id, '$db_url')");
                             $uploaded_count++;
                        } else {
                             $errors[] = "Error al guardar la imagen redimensionada $name";
                        }
                    } else {
                        $errors[] = "Error al procesar la imagen $name";
                    }
                } else {
                    // Si no necesita resize, mover el archivo original
                    if (move_uploaded_file($tmp_name, $target)) {
                        $db_url = "uploads/gallery/$album_id/$new_name";
                        $db->query("INSERT INTO fotos (id_album, url_foto) VALUES ($album_id, '$db_url')");
                        $uploaded_count++;
                    } else {
                        $errors[] = "Error moviendo $name";
                    }
                }
            } else {
                $errors[] = "$name tiene formato inválido";
            }
        }
    }
    
    if ($uploaded_count > 0) {
        $msg = "Se subieron $uploaded_count fotos correctamente.";
    }
    if (!empty($errors)) {
        $error = implode(", ", $errors);
    }
}

// Procesar Eliminación de Foto
if (isset($_GET['delete_photo'])) {
    $photo_id = intval($_GET['delete_photo']);
    $query = "SELECT url_foto FROM fotos WHERE id = $photo_id AND id_album = $album_id";
    $photo_res = $db->query($query);
    if ($photo_res && $photo_row = $photo_res->fetch_assoc()) {
        $path = '../../' . $photo_row['url_foto'];
        if (file_exists($path)) unlink($path);
        
        $db->query("DELETE FROM fotos WHERE id = $photo_id");
        header("Location: manage-album.php?id=$album_id&msg=photo_deleted");
        exit();
    }
}

// Obtener fotos
$fotos = $db->query("SELECT * FROM fotos WHERE id_album = $album_id ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);

include '../../includes/admin-header.php';
?>

<div class="admin-main">
    <div class="admin-header">
        <div>
            <h1 style="margin-bottom: 0.5rem;"><i class="fas fa-camera-retro"></i> <?php echo htmlspecialchars($album['titulo']); ?></h1>
            <p style="margin:0; color:var(--text-light);">Evento: <?php echo htmlspecialchars($album['evento_nombre']); ?></p>
        </div>
        <div class="admin-header-actions">
            <a href="edit-album.php?id=<?php echo $album_id; ?>" class="admin-btn admin-btn-info">
                <i class="fas fa-edit"></i> Editar Detalles
            </a>
            <a href="gallery.php" class="admin-btn admin-btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <?php if ($msg || isset($_GET['msg'])): ?>
    <div class="admin-alert admin-alert-success">
        <?php echo $msg ? $msg : 'Acción realizada correctamente.'; ?>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="admin-alert admin-alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Zona de Subida -->
    <div class="admin-card" style="margin-bottom: 2rem;">
        <h3><i class="fas fa-cloud-upload-alt"></i> Subir Fotos</h3>
        <form method="POST" action="" enctype="multipart/form-data" class="upload-form" style="border: 2px dashed #ccc; padding: 2rem; text-align: center; border-radius: 8px;">
            <input type="file" name="fotos[]" id="fotos" multiple accept="image/*" style="display: none;" onchange="this.form.submit()">
            <label for="fotos" class="admin-btn admin-btn-primary" style="cursor: pointer; display: inline-block; margin-bottom: 1rem;">
                <i class="fas fa-plus"></i> Seleccionar Fotos
            </label>
            <p class="text-muted">O arrastra y suelta tus imágenes aquí (Funcionalidad básica de click)</p>
            <small>Formatos: JPG, PNG, WEBP</small>
        </form>
    </div>

    <!-- Grid de Fotos -->
    <div class="admin-card">
        <h3><i class="fas fa-images"></i> Fotos del Álbum (<?php echo count($fotos); ?>)</h3>
        
        <?php if (!empty($fotos)): ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px; margin-top: 1rem;">
            <?php foreach ($fotos as $foto): ?>
            <div style="position: relative; group: 1; border: 1px solid #eee; border-radius: 4px; overflow: hidden;">
                <img src="../../<?php echo $foto['url_foto']; ?>" style="width: 100%; height: 150px; object-fit: cover; display: block;">
                <a href="?id=<?php echo $album_id; ?>&delete_photo=<?php echo $foto['id']; ?>" 
                   onclick="return confirm('¿Borrar esta foto?')"
                   style="position: absolute; top: 5px; right: 5px; background: rgba(255,0,0,0.8); color: white; width: 25px; height: 25px; border-radius: 50%; display: flex; align-items: center; justify-content: center; text-decoration: none;">
                    <i class="fas fa-times"></i>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="text-muted text-center py-4">Aún no hay fotos en este álbum.</p>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/admin-footer.php'; ?>
