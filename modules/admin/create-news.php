<?php
// modules/admin/create-news.php
require_once '../../config/config.php';
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'organizador'])) {
    header('Location: ../auth/login.php');
    exit();
}

$db = getDB();
$error = '';
$success = '';

// YouTube ID Extractor helper
function extractYouTubeId($url) {
    if (empty($url)) return null;
    $pattern = '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/';
    if (preg_match($pattern, trim($url), $matches)) {
        return $matches[1];
    }
    return null;
}

// Slug helper
function generateSlug($string, $db) {
    $slug = mb_strtolower(trim($string), 'UTF-8');
    $slug = preg_replace('/[^\p{L}\p{N}]+/u', '-', $slug);
    $slug = trim($slug, '-');
    if (empty($slug)) {
        $slug = 'noticia-' . time();
    }
    
    // Check uniqueness
    $original_slug = $slug;
    $count = 1;
    while (true) {
        $stmt = $db->prepare("SELECT id FROM noticias WHERE slug = ?");
        $stmt->bind_param("s", $slug);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) {
            $stmt->close();
            break;
        }
        $stmt->close();
        $slug = $original_slug . '-' . $count;
        $count++;
    }
    return $slug;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo'] ?? '');
    $resumen = trim($_POST['resumen'] ?? '');
    $contenido = trim($_POST['contenido'] ?? '');
    $video_url = trim($_POST['video_url'] ?? '');
    $estado = $_POST['estado'] ?? 'publicado';
    $autor_id = $_SESSION['user_id'];

    if (empty($titulo)) {
        $error = 'El título de la noticia es obligatorio.';
    } elseif (empty($contenido)) {
        $error = 'El contenido principal de la noticia es obligatorio.';
    } else {
        $slug = generateSlug($titulo, $db);
        $youtube_id = extractYouTubeId($video_url);
        $imagen_destacada = null;

        // Process File Upload
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
                    $imagen_destacada = 'uploads/news/' . $newFileName;
                } else {
                    $error = 'Hubo un error al mover la imagen al directorio de cargas.';
                }
            } else {
                $error = 'Formato de imagen no permitido. Usa JPG, PNG, WEBP o GIF.';
            }
        }

        $posicion_imagen = trim($_POST['posicion_imagen'] ?? 'center 50%');
        if (empty($posicion_imagen)) {
            $posicion_imagen = 'center 50%';
        }

        if (empty($error)) {
            $fecha_publicacion = ($estado === 'publicado') ? date('Y-m-d H:i:s') : null;
            $stmt = $db->prepare("INSERT INTO noticias (titulo, slug, resumen, contenido, imagen_destacada, posicion_imagen, video_url, youtube_id, estado, autor_id, fecha_publicacion) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssssis", $titulo, $slug, $resumen, $contenido, $imagen_destacada, $posicion_imagen, $video_url, $youtube_id, $estado, $autor_id, $fecha_publicacion);

            if ($stmt->execute()) {
                $stmt->close();
                header('Location: news.php?created=1');
                exit();
            } else {
                $error = 'Error al guardar la noticia en la base de datos: ' . $db->error;
            }
        }
    }
}

$page_title = "Nueva Noticia";
include '../../includes/admin-header.php';
?>

<div class="admin-main">
    <div class="admin-header">
        <div>
            <h1><i class="fas fa-plus-circle"></i> Publicar Nueva Noticia / Artículo</h1>
            <p>Completa la información del artículo y añade opcionalmente un video de YouTube.</p>
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

    <form method="POST" action="create-news.php" enctype="multipart/form-data">
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
            
            <!-- Left Column: Main Content -->
            <div class="admin-card">
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label for="titulo" style="display: block; font-weight: 600; margin-bottom: 0.5rem;">Título del Artículo <span style="color: red;">*</span></label>
                    <input type="text" id="titulo" name="titulo" class="form-control" placeholder="Ej: Gran Maratón 5K Fit2026: Todo lo que necesitas saber" required style="width: 100%; padding: 0.75rem; border: 1px solid #ccc; border-radius: 6px; font-size: 1rem;">
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label for="resumen" style="display: block; font-weight: 600; margin-bottom: 0.5rem;">Resumen o Introducción Corta</label>
                    <textarea id="resumen" name="resumen" rows="3" class="form-control" placeholder="Breve descripción que se mostrará en la tarjeta o vista previa..." style="width: 100%; padding: 0.75rem; border: 1px solid #ccc; border-radius: 6px; font-size: 0.95rem;"></textarea>
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label for="contenido" style="display: block; font-weight: 600; margin-bottom: 0.5rem;">Contenido Principal <span style="color: red;">*</span></label>
                    <textarea id="contenido" name="contenido" rows="12" class="form-control" placeholder="Escribe aquí el cuerpo del artículo o noticia..." required style="width: 100%; padding: 0.75rem; border: 1px solid #ccc; border-radius: 6px; font-size: 1rem; font-family: inherit;"></textarea>
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
                            <option value="publicado">Publicado</option>
                            <option value="borrador">Borrador</option>
                            <option value="archivado">Archivado</option>
                        </select>
                    </div>

                    <button type="submit" class="admin-btn admin-btn-primary" style="width: 100%; padding: 0.75rem; justify-content: center;">
                        <i class="fas fa-save"></i> Guardar Noticia
                    </button>
                </div>

                <!-- YouTube Integration Card -->
                <div class="admin-card">
                    <h3 style="margin-top: 0; font-size: 1.1rem; border-bottom: 1px solid #eee; padding-bottom: 0.5rem; color: #cc0000;">
                        <i class="fab fa-youtube"></i> Video de YouTube
                    </h3>
                    
                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label for="video_url" style="display: block; font-weight: 500; font-size: 0.9rem; margin-bottom: 0.5rem;">Enlace o URL de YouTube</label>
                        <input type="url" id="video_url" name="video_url" class="form-control" placeholder="https://www.youtube.com/watch?v=..." style="width: 100%; padding: 0.6rem; border: 1px solid #ccc; border-radius: 6px; font-size: 0.9rem;" oninput="updateYouTubePreview(this.value)">
                        <small style="color: #6c757d; font-size: 0.8rem; display: block; margin-top: 0.3rem;">Soporta links de youtube.com y youtu.be</small>
                    </div>

                    <!-- YouTube Preview Container -->
                    <div id="yt-preview-container" style="display: none; margin-top: 1rem; border-radius: 6px; overflow: hidden; background: #000; position: relative; padding-top: 56.25%;">
                        <iframe id="yt-preview-iframe" src="" style="position: absolute; top:0; left:0; width:100%; height:100%; border:0;" allowfullscreen></iframe>
                    </div>
                </div>

                <!-- Featured Image Card -->
                <div class="admin-card">
                    <h3 style="margin-top: 0; font-size: 1.1rem; border-bottom: 1px solid #eee; padding-bottom: 0.5rem;"><i class="fas fa-image"></i> Imagen Destacada</h3>
                    
                    <div class="form-group">
                        <input type="file" id="imagen_destacada" name="imagen_destacada" accept="image/*" class="form-control" style="width: 100%;" onchange="previewImage(this)">
                        <small style="color: #6c757d; font-size: 0.8rem; display: block; margin-top: 0.3rem;">Formatos: JPG, PNG, WEBP. Máx 5MB.</small>
                    </div>

                    <!-- Hidden input to store chosen object-position -->
                    <input type="hidden" id="posicion_imagen" name="posicion_imagen" value="center 50%">

                    <!-- Image Preview & Crop Controller -->
                    <div id="image-preview-container" style="margin-top: 1.2rem; display: none;">
                        <div style="font-size: 0.85rem; font-weight: 600; color: #2a004a; margin-bottom: 0.4rem; display: flex; align-items: center; justify-content: space-between;">
                            <span><i class="fas fa-eye"></i> Vista previa en Tarjeta del Home</span>
                            <span id="pos-badge" style="background: #f3e9fd; color: #6a0dad; padding: 2px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: 600;">50% (Centro)</span>
                        </div>

                        <!-- Simulated Home Card Container (190px height) -->
                        <div style="height: 190px; width: 100%; border-radius: 10px; overflow: hidden; background: #2a004a; position: relative; border: 2px dashed #9c27b0; box-shadow: 0 4px 10px rgba(0,0,0,0.08);">
                            <img id="image-preview" src="#" alt="Previsualización" style="width: 100%; height: 100%; object-fit: cover; object-position: center 50%;">
                        </div>

                        <!-- Focal Point Controls -->
                        <div style="margin-top: 1rem; background: #fdfafc; padding: 0.8rem; border-radius: 8px; border: 1px solid #f0e6fa;">
                            <label for="posicion_y_slider" style="display: block; font-size: 0.82rem; font-weight: 600; color: #444; margin-bottom: 0.3rem;">
                                <i class="fas fa-arrows-alt-v" style="color: #6a0dad;"></i> Ajustar Altura / Encuadre Vertical:
                            </label>
                            
                            <input type="range" id="posicion_y_slider" min="0" max="100" value="50" style="width: 100%; margin: 0.3rem 0; accent-color: #6a0dad; cursor: pointer;" oninput="updateImagePosition(this.value)">
                            
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
    } else {
        container.style.display = 'none';
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
