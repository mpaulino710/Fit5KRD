<?php
// modules/admin/edit-news.php
require_once '../../config/config.php';
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'organizador'])) {
    header('Location: ../auth/login.php');
    exit();
}

$db = getDB();
$error = '';
$success = '';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    header('Location: news.php');
    exit();
}

// Fetch existing news
$stmt = $db->prepare("SELECT * FROM noticias WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$noticia = $result->fetch_assoc();
$stmt->close();

if (!$noticia) {
    header('Location: news.php');
    exit();
}

// YouTube ID Extractor helper
function extractYouTubeId($url) {
    if (empty($url)) return null;
    $pattern = '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/';
    if (preg_match($pattern, trim($url), $matches)) {
        return $matches[1];
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo'] ?? '');
    $resumen = trim($_POST['resumen'] ?? '');
    $contenido = trim($_POST['contenido'] ?? '');
    $video_url = trim($_POST['video_url'] ?? '');
    $estado = $_POST['estado'] ?? 'publicado';
    $remove_image = isset($_POST['remove_image']) && $_POST['remove_image'] === '1';

    if (empty($titulo)) {
        $error = 'El título de la noticia es obligatorio.';
    } elseif (empty($contenido)) {
        $error = 'El contenido principal de la noticia es obligatorio.';
    } else {
        $youtube_id = extractYouTubeId($video_url);
        $imagen_destacada = $noticia['imagen_destacada'];

        if ($remove_image && !empty($imagen_destacada)) {
            if (file_exists('../../' . $imagen_destacada)) {
                @unlink('../../' . $imagen_destacada);
            }
            $imagen_destacada = null;
        }

        // Process New File Upload
        if (isset($_FILES['imagen_destacada']) && $_FILES['imagen_destacada']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['imagen_destacada']['tmp_name'];
            $fileName = $_FILES['imagen_destacada']['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

            if (in_array($fileExtension, $allowedExtensions)) {
                $newFileName = 'news_' . uniqid() . '.' . $fileExtension;
                $uploadFileDir = '../../uploads/news/';
                $dest_path = $uploadFileDir . $newFileName;

                if (!is_dir($uploadFileDir)) {
                    mkdir($uploadFileDir, 0777, true);
                }

                if (move_uploaded_file($fileTmpPath, $dest_path)) {
                    // Remove old image if replaced
                    if (!empty($noticia['imagen_destacada']) && file_exists('../../' . $noticia['imagen_destacada'])) {
                        @unlink('../../' . $noticia['imagen_destacada']);
                    }
                    $imagen_destacada = 'uploads/news/' . $newFileName;
                } else {
                    $error = 'Hubo un error al mover la nueva imagen.';
                }
            } else {
                $error = 'Formato de imagen no permitido. Usa JPG, PNG, WEBP o GIF.';
            }
        }

        $posicion_imagen = trim($_POST['posicion_imagen'] ?? ($noticia['posicion_imagen'] ?? 'center 50%'));
        if (empty($posicion_imagen)) {
            $posicion_imagen = 'center 50%';
        }

        if (empty($error)) {
            $fecha_publicacion = $noticia['fecha_publicacion'];
            if ($estado === 'publicado' && empty($fecha_publicacion)) {
                $fecha_publicacion = date('Y-m-d H:i:s');
            }

            $stmt = $db->prepare("UPDATE noticias SET titulo = ?, resumen = ?, contenido = ?, imagen_destacada = ?, posicion_imagen = ?, video_url = ?, youtube_id = ?, estado = ?, fecha_publicacion = ? WHERE id = ?");
            $stmt->bind_param("sssssssssi", $titulo, $resumen, $contenido, $imagen_destacada, $posicion_imagen, $video_url, $youtube_id, $estado, $fecha_publicacion, $id);

            if ($stmt->execute()) {
                $stmt->close();
                header('Location: news.php?updated=1');
                exit();
            } else {
                $error = 'Error al actualizar la noticia: ' . $db->error;
            }
        }
    }
}

$page_title = "Editar Noticia";
include '../../includes/admin-header.php';
?>

<div class="admin-main">
    <div class="admin-header">
        <div>
            <h1><i class="fas fa-edit"></i> Editar Noticia / Artículo</h1>
            <p>Modifica el contenido, actualiza la imagen destacada o cambia la URL del video de YouTube.</p>
        </div>
        <div class="admin-header-actions">
            <a href="news.php" class="admin-btn admin-btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver al listado
            </a>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger" style="background: #f8d7da; color: #842029; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid #f5c2c7;">
            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="edit-news.php?id=<?php echo $id; ?>" enctype="multipart/form-data">
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
            
            <!-- Left Column: Main Content -->
            <div class="admin-card">
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label for="titulo" style="display: block; font-weight: 600; margin-bottom: 0.5rem;">Título del Artículo <span style="color: red;">*</span></label>
                    <input type="text" id="titulo" name="titulo" class="form-control" value="<?php echo htmlspecialchars($noticia['titulo']); ?>" required style="width: 100%; padding: 0.75rem; border: 1px solid #ccc; border-radius: 6px; font-size: 1rem;">
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label for="resumen" style="display: block; font-weight: 600; margin-bottom: 0.5rem;">Resumen o Introducción Corta</label>
                    <textarea id="resumen" name="resumen" rows="3" class="form-control" style="width: 100%; padding: 0.75rem; border: 1px solid #ccc; border-radius: 6px; font-size: 0.95rem;"><?php echo htmlspecialchars($noticia['resumen']); ?></textarea>
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label for="contenido" style="display: block; font-weight: 600; margin-bottom: 0.5rem;">Contenido Principal <span style="color: red;">*</span></label>
                    <textarea id="contenido" name="contenido" rows="12" class="form-control" required style="width: 100%; padding: 0.75rem; border: 1px solid #ccc; border-radius: 6px; font-size: 1rem; font-family: inherit;"><?php echo htmlspecialchars($noticia['contenido']); ?></textarea>
                </div>
            </div>

            <!-- Right Column: Settings & Media -->
            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                <!-- Status & Publish Card -->
                <div class="admin-card">
                    <h3 style="margin-top: 0; font-size: 1.1rem; border-bottom: 1px solid #eee; padding-bottom: 0.5rem;"><i class="fas fa-cog"></i> Publicación</h3>
                    
                    <div class="form-group" style="margin-bottom: 1.2rem;">
                        <label for="estado" style="display: block; font-weight: 600; margin-bottom: 0.5rem;">Estado</label>
                        <select id="estado" name="estado" class="form-control" style="width: 100%; padding: 0.6rem; border: 1px solid #ccc; border-radius: 6px;">
                            <option value="publicado" <?php echo $noticia['estado'] === 'publicado' ? 'selected' : ''; ?>>Publicado</option>
                            <option value="borrador" <?php echo $noticia['estado'] === 'borrador' ? 'selected' : ''; ?>>Borrador</option>
                            <option value="archivado" <?php echo $noticia['estado'] === 'archivado' ? 'selected' : ''; ?>>Archivado</option>
                        </select>
                    </div>

                    <button type="submit" class="admin-btn admin-btn-primary" style="width: 100%; padding: 0.75rem; justify-content: center;">
                        <i class="fas fa-sync-alt"></i> Actualizar Noticia
                    </button>
                </div>

                <!-- YouTube Integration Card -->
                <div class="admin-card">
                    <h3 style="margin-top: 0; font-size: 1.1rem; border-bottom: 1px solid #eee; padding-bottom: 0.5rem; color: #cc0000;">
                        <i class="fab fa-youtube"></i> Video de YouTube
                    </h3>
                    
                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label for="video_url" style="display: block; font-weight: 500; font-size: 0.9rem; margin-bottom: 0.5rem;">Enlace o URL de YouTube</label>
                        <input type="url" id="video_url" name="video_url" class="form-control" value="<?php echo htmlspecialchars($noticia['video_url'] ?? ''); ?>" placeholder="https://www.youtube.com/watch?v=..." style="width: 100%; padding: 0.6rem; border: 1px solid #ccc; border-radius: 6px; font-size: 0.9rem;" oninput="updateYouTubePreview(this.value)">
                    </div>

                    <!-- YouTube Preview Container -->
                    <div id="yt-preview-container" style="display: <?php echo !empty($noticia['youtube_id']) ? 'block' : 'none'; ?>; margin-top: 1rem; border-radius: 6px; overflow: hidden; background: #000; position: relative; padding-top: 56.25%;">
                        <iframe id="yt-preview-iframe" src="<?php echo !empty($noticia['youtube_id']) ? 'https://www.youtube.com/embed/' . htmlspecialchars($noticia['youtube_id']) : ''; ?>" style="position: absolute; top:0; left:0; width:100%; height:100%; border:0;" allowfullscreen></iframe>
                    </div>
                </div>

                <!-- Featured Image Card -->
                <div class="admin-card">
                    <h3 style="margin-top: 0; font-size: 1.1rem; border-bottom: 1px solid #eee; padding-bottom: 0.5rem;"><i class="fas fa-image"></i> Imagen Destacada</h3>
                    
                    <?php 
                    $has_existing_img = !empty($noticia['imagen_destacada']);
                    $current_pos = $noticia['posicion_imagen'] ?? 'center 50%';
                    $initial_slider_val = 50;
                    if (preg_match('/(\d+)%/', $current_pos, $m)) {
                        $initial_slider_val = intval($m[1]);
                    } elseif (strpos($current_pos, 'top') !== false) {
                        $initial_slider_val = 0;
                    } elseif (strpos($current_pos, 'bottom') !== false) {
                        $initial_slider_val = 100;
                    }
                    ?>

                    <?php if ($has_existing_img): ?>
                        <div style="margin-bottom: 1rem;">
                            <label style="display: block; font-size: 0.85rem; color: #dc3545; cursor: pointer; margin-bottom: 0.8rem;">
                                <input type="checkbox" name="remove_image" value="1" onchange="toggleExistingImage(this)"> Eliminar imagen actual
                            </label>
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label style="display: block; font-size: 0.85rem; margin-bottom: 0.4rem; font-weight: 500;">
                            <?php echo $has_existing_img ? 'Reemplazar imagen:' : 'Subir imagen:'; ?>
                        </label>
                        <input type="file" id="imagen_destacada" name="imagen_destacada" accept="image/*" class="form-control" style="width: 100%;" onchange="previewImage(this)">
                    </div>

                    <!-- Hidden input to store chosen object-position -->
                    <input type="hidden" id="posicion_imagen" name="posicion_imagen" value="<?php echo htmlspecialchars($current_pos); ?>">

                    <!-- Image Preview & Crop Controller -->
                    <div id="image-preview-container" style="margin-top: 1.2rem; <?php echo $has_existing_img ? 'display: block;' : 'display: none;'; ?>">
                        <div style="font-size: 0.85rem; font-weight: 600; color: #2a004a; margin-bottom: 0.4rem; display: flex; align-items: center; justify-content: space-between;">
                            <span><i class="fas fa-eye"></i> Vista previa en Tarjeta del Home</span>
                            <span id="pos-badge" style="background: #f3e9fd; color: #6a0dad; padding: 2px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: 600;">
                                <?php echo $initial_slider_val; ?>% <?php echo ($initial_slider_val <= 15) ? '(Arriba)' : (($initial_slider_val >= 85) ? '(Abajo)' : '(Centro)'); ?>
                            </span>
                        </div>

                        <!-- Simulated Home Card Container (190px height) -->
                        <div style="height: 190px; width: 100%; border-radius: 10px; overflow: hidden; background: #2a004a; position: relative; border: 2px dashed #9c27b0; box-shadow: 0 4px 10px rgba(0,0,0,0.08);">
                            <img id="image-preview" src="<?php echo $has_existing_img ? '../../' . htmlspecialchars($noticia['imagen_destacada']) : '#'; ?>" alt="Previsualización" style="width: 100%; height: 100%; object-fit: cover; object-position: <?php echo htmlspecialchars($current_pos); ?>;">
                        </div>

                        <!-- Focal Point Controls -->
                        <div style="margin-top: 1rem; background: #fdfafc; padding: 0.8rem; border-radius: 8px; border: 1px solid #f0e6fa;">
                            <label for="posicion_y_slider" style="display: block; font-size: 0.82rem; font-weight: 600; color: #444; margin-bottom: 0.3rem;">
                                <i class="fas fa-arrows-alt-v" style="color: #6a0dad;"></i> Ajustar Altura / Encuadre Vertical:
                            </label>
                            
                            <input type="range" id="posicion_y_slider" min="0" max="100" value="<?php echo $initial_slider_val; ?>" style="width: 100%; margin: 0.3rem 0; accent-color: #6a0dad; cursor: pointer;" oninput="updateImagePosition(this.value)">
                            
                            <div style="display: flex; justify-content: space-between; font-size: 0.72rem; color: #777; margin-bottom: 0.6rem;">
                                <span>Arriba (0%)</span>
                                <span>Centro (50%)</span>
                                <span>Abajo (100%)</span>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 6px;">
                                <button type="button" class="admin-btn admin-btn-secondary" style="padding: 0.35rem 0.5rem; font-size: 0.78rem; justify-content: center;" onclick="setImagePosition(0)">
                                    ⬆️ Arriba
                                </button>
                                <button type="button" class="admin-btn admin-btn-secondary" style="padding: 0.35rem 0.5rem; font-size: 0.78rem; justify-content: center;" onclick="setImagePosition(50)">
                                    🎯 Centro
                                </button>
                                <button type="button" class="admin-btn admin-btn-secondary" style="padding: 0.35rem 0.5rem; font-size: 0.78rem; justify-content: center;" onclick="setImagePosition(100)">
                                    ⬇️ Abajo
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.2/tinymce.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    tinymce.init({
        selector: '#contenido',
        height: 420,
        language: 'es',
        plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table code help wordcount',
        toolbar: 'undo redo | blocks | bold italic forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | link image table | code preview',
        content_style: 'body { font-family: Poppins, Helvetica, Arial, sans-serif; font-size:14px }',
        setup: function(editor) {
            editor.on('change', function() {
                editor.save();
            });
        }
    });
});

function extractYTId(url) {
    if (!url) return null;
    const regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=)([^#\&\?]*).*/;
    const match = url.match(regExp);
    return (match && match[2].length === 11) ? match[2] : null;
}

function updateYouTubePreview(url) {
    const ytId = extractYTId(url);
    const container = document.getElementById('yt-preview-container');
    const iframe = document.getElementById('yt-preview-iframe');

    if (ytId) {
        iframe.src = 'https://www.youtube.com/embed/' + ytId;
        container.style.display = 'block';
    } else {
        iframe.src = '';
        container.style.display = 'none';
    }
}

function previewImage(input) {
    const preview = document.getElementById('image-preview');
    const container = document.getElementById('image-preview-container');

    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            container.style.display = 'block';
        }
        reader.readAsDataURL(input.files[0]);
    }
}

function toggleExistingImage(chk) {
    const container = document.getElementById('image-preview-container');
    const fileInput = document.getElementById('imagen_destacada');
    if (chk.checked && (!fileInput.files || !fileInput.files[0])) {
        container.style.display = 'none';
    } else {
        container.style.display = 'block';
    }
}

function updateImagePosition(val) {
    val = parseInt(val, 10);
    if (isNaN(val)) val = 50;
    
    const preview = document.getElementById('image-preview');
    const inputHidden = document.getElementById('posicion_imagen');
    const badge = document.getElementById('pos-badge');
    const slider = document.getElementById('posicion_y_slider');
    
    slider.value = val;
    inputHidden.value = 'center ' + val + '%';
    if (preview) {
        preview.style.objectPosition = 'center ' + val + '%';
    }
    
    let label = val + '%';
    if (val <= 15) label += ' (Arriba)';
    else if (val >= 85) label += ' (Abajo)';
    else if (val >= 40 && val <= 60) label += ' (Centro)';
    
    if (badge) badge.innerText = label;
}

function setImagePosition(val) {
    updateImagePosition(val);
}
</script>

<?php include '../../includes/admin-footer.php'; ?>
