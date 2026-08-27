<?php
// modules/gallery/view.php
require_once '../../config/config.php';
require_once '../../config/database.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$album_id = intval($_GET['id']);
$db = getDB();

$stmt = $db->prepare("SELECT a.*, e.nombre as evento_nombre, e.fecha_evento 
                      FROM albumes a JOIN eventos e ON a.id_evento = e.id 
                      WHERE a.id = ? AND a.estado = 'publico'");
$stmt->bind_param("i", $album_id);
$stmt->execute();
$album = $stmt->get_result()->fetch_assoc();

if (!$album) {
    header('Location: index.php');
    exit();
}

$fotos = $db->query("SELECT * FROM fotos WHERE id_album = $album_id ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);

include '../../includes/header.php';
?>

<div class="container">
    <div style="margin-bottom: 2rem;">
        <a href="index.php" style="color: var(--primary-color); text-decoration: none;">
            <i class="fas fa-arrow-left"></i> Volver a Galerías
        </a>
    </div>

    <div class="section-header text-center" style="margin-bottom: 3rem;">
        <h1 class="section-title"><?php echo htmlspecialchars($album['titulo']); ?></h1>
        <p class="section-description">
            <i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($album['fecha_evento'])); ?> • 
            <?php echo htmlspecialchars($album['evento_nombre']); ?>
        </p>
        <?php if ($album['descripcion']): ?>
            <p style="max-width: 800px; margin: 1rem auto; color: var(--text-light);">
                <?php echo nl2br(htmlspecialchars($album['descripcion'])); ?>
            </p>
        <?php endif; ?>
    </div>

    <?php if (empty($fotos)): ?>
        <div class="text-center p-5">
            <p>Este álbum aún está vacío.</p>
        </div>
    <?php else: ?>
        <div class="gallery-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 1rem;">
            <?php foreach ($fotos as $index => $foto): ?>
            <div class="gallery-item" style="cursor: pointer; overflow: hidden; border-radius: 8px; height: 250px;" 
                 onclick="openLightbox(<?php echo $index; ?>)">
                <img src="../../<?php echo $foto['url_foto']; ?>" alt="Foto" 
                     style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s ease;">
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Simple Lightbox Modal -->
<div id="lightbox" style="
    display: none; 
    position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; 
    overflow: auto; background-color: rgba(0,0,0,0.9);
">
    <span style="position: absolute; top: 15px; right: 35px; color: #f1f1f1; font-size: 40px; font-weight: bold; cursor: pointer;" onclick="closeLightbox()">&times;</span>
    
    <div style="display: flex; align-items: center; justify-content: center; height: 100%;">
        <a href="#" onclick="changeSlide(-1); return false;" style="color: white; font-size: 2rem; padding: 20px; text-decoration: none;">&#10094;</a>
        <img class="lightbox-content" id="lightbox-img" style="max-width: 90%; max-height: 90vh;">
        <a href="#" onclick="changeSlide(1); return false;" style="color: white; font-size: 2rem; padding: 20px; text-decoration: none;">&#10095;</a>
    </div>
</div>

<script>
const photos = <?php echo json_encode(array_column($fotos, 'url_foto')); ?>;
let currentIndex = 0;
const lightbox = document.getElementById('lightbox');
const lightboxImg = document.getElementById('lightbox-img');

function openLightbox(index) {
    currentIndex = index;
    updateLightbox();
    lightbox.style.display = "block";
    document.body.style.overflow = "hidden"; // Disable scroll
}

function closeLightbox() {
    lightbox.style.display = "none";
    document.body.style.overflow = "auto";
}

function changeSlide(n) {
    currentIndex += n;
    if (currentIndex >= photos.length) currentIndex = 0;
    if (currentIndex < 0) currentIndex = photos.length - 1;
    updateLightbox();
}

function updateLightbox() {
    lightboxImg.src = '../../' + photos[currentIndex];
}

// Close on escape key
document.addEventListener('keydown', function(event) {
    if (event.key === "Escape") closeLightbox();
    if (event.key === "ArrowRight") changeSlide(1);
    if (event.key === "ArrowLeft") changeSlide(-1);
});
</script>

<style>
.gallery-item:hover img {
    transform: scale(1.05);
}
</style>

<?php include '../../includes/footer.php'; ?>
