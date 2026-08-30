<?php
// modules/public/view-news.php
require_once '../../config/config.php';
require_once '../../config/database.php';

$db = getDB();

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$noticia = null;

if (!empty($slug)) {
    $stmt = $db->prepare("SELECT n.*, u.nombre as autor_nombre FROM noticias n LEFT JOIN usuarios u ON n.autor_id = u.id WHERE n.slug = ? AND n.estado = 'publicado'");
    $stmt->bind_param("s", $slug);
    $stmt->execute();
    $noticia = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} elseif ($id > 0) {
    $stmt = $db->prepare("SELECT n.*, u.nombre as autor_nombre FROM noticias n LEFT JOIN usuarios u ON n.autor_id = u.id WHERE n.id = ? AND n.estado = 'publicado'");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $noticia = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$noticia) {
    header('Location: news.php');
    exit();
}

// Increment visit counter
$stmtUpdate = $db->prepare("UPDATE noticias SET visitas = visitas + 1 WHERE id = ?");
$stmtUpdate->bind_param("i", $noticia['id']);
$stmtUpdate->execute();
$stmtUpdate->close();

// Fetch Related / Recent Articles
$stmtRelated = $db->prepare("SELECT id, titulo, slug, imagen_destacada, posicion_imagen, youtube_id, fecha_publicacion, creado_en FROM noticias WHERE estado = 'publicado' AND id != ? ORDER BY fecha_publicacion DESC LIMIT 4");
$stmtRelated->bind_param("i", $noticia['id']);
$stmtRelated->execute();
$relacionadas = $stmtRelated->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtRelated->close();

// Page SEO Meta Title
$page_title = htmlspecialchars($noticia['titulo']);
include '../../includes/header.php';

$currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
?>

<div class="article-container" style="padding-top: 1.5rem; padding-bottom: 4rem;">
    <!-- Breadcrumb -->
    <div style="margin-bottom: 1.5rem; font-size: 0.9rem; color: #6c757d;">
        <a href="home.php" style="color: #6a0dad; text-decoration: none;">Inicio</a>
        <i class="fas fa-chevron-right" style="font-size: 0.75rem; margin: 0 8px; opacity: 0.6;"></i>
        <a href="news.php" style="color: #6a0dad; text-decoration: none;">Noticias</a>
        <i class="fas fa-chevron-right" style="font-size: 0.75rem; margin: 0 8px; opacity: 0.6;"></i>
        <span style="color: #333;"><?php echo htmlspecialchars($noticia['titulo']); ?></span>
    </div>

    <div style="display: grid; grid-template-columns: 1fr; gap: 2rem;">
        <?php if (!empty($relacionadas)): ?>
            <style>
                @media (min-width: 992px) {
                    .article-layout {
                        display: grid !important;
                        grid-template-columns: 2.8fr 1.2fr !important;
                        gap: 2.5rem !important;
                    }
                }
            </style>
        <?php endif; ?>

        <div class="article-layout">
            <!-- Main Content Area -->
            <div>
                <!-- Article Header -->
                <div style="margin-bottom: 1.8rem;">
                    <h1 style="font-size: 2.2rem; font-weight: 700; color: #2a004a; line-height: 1.3; margin: 0 0 1rem 0;">
                        <?php echo htmlspecialchars($noticia['titulo']); ?>
                    </h1>

                    <div style="display: flex; flex-wrap: wrap; items-center; gap: 1.2rem; color: #666; font-size: 0.9rem; padding-bottom: 1.2rem; border-bottom: 1px solid #eee;">
                        <span><i class="fas fa-user-circle" style="color: #6a0dad;"></i> <?php echo htmlspecialchars($noticia['autor_nombre'] ?? 'Fit5K Soporte'); ?></span>
                        <span><i class="far fa-calendar-alt" style="color: #6a0dad;"></i> <?php echo date('d \d\e F \d\e Y', strtotime($noticia['fecha_publicacion'] ?? $noticia['creado_en'])); ?></span>
                        <span><i class="far fa-eye" style="color: #6a0dad;"></i> <?php echo number_format($noticia['visitas'] + 1); ?> lecturas</span>
                    </div>
                </div>

                <!-- Video YouTube Embed -->
                <?php if (!empty($noticia['youtube_id'])): ?>
                    <div style="margin-bottom: 2rem; border-radius: 14px; overflow: hidden; box-shadow: 0 8px 25px rgba(0,0,0,0.15); background: #000; position: relative; padding-top: 56.25%;">
                        <iframe src="https://www.youtube.com/embed/<?php echo htmlspecialchars($noticia['youtube_id']); ?>?rel=0&autoplay=0" style="position: absolute; top:0; left:0; width:100%; height:100%; border:0;" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                    </div>
                <?php elseif (!empty($noticia['imagen_destacada'])): ?>
                    <div style="margin-bottom: 2rem; border-radius: 14px; overflow: hidden; box-shadow: 0 8px 20px rgba(0,0,0,0.08);">
                        <img src="../../<?php echo htmlspecialchars($noticia['imagen_destacada']); ?>" alt="<?php echo htmlspecialchars($noticia['titulo']); ?>" style="width: 100%; max-height: 480px; object-fit: cover; object-position: <?php echo !empty($noticia['posicion_imagen']) ? htmlspecialchars($noticia['posicion_imagen']) : 'center center'; ?>; display: block;">
                    </div>
                <?php endif; ?>

                <!-- Article Resumen Excerpt -->
                <?php if (!empty($noticia['resumen'])): ?>
                    <div style="background: #f3e9fd; border-left: 4px solid #6a0dad; padding: 1.2rem; border-radius: 0 10px 10px 0; margin-bottom: 2rem; font-size: 1.1rem; color: #4a154b; font-style: italic; line-height: 1.6;">
                        <?php echo htmlspecialchars($noticia['resumen']); ?>
                    </div>
                <?php endif; ?>

                <!-- Article Main Content (Rich Text Rendered HTML) -->
                <div class="article-body-content" style="font-size: 1.05rem; line-height: 1.8; color: #333; margin-bottom: 3rem;">
                    <?php echo $noticia['contenido']; ?>
                </div>

                <!-- Social Share Buttons -->
                <div style="background: #f8f9fa; border-radius: 12px; padding: 1.2rem 1.5rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; margin-top: 2rem; border: 1px solid #e9ecef;">
                    <span style="font-weight: 600; color: #2a004a;"><i class="fas fa-share-alt"></i> Compartir este artículo:</span>
                    <div style="display: flex; gap: 10px;">
                        <a href="https://api.whatsapp.com/send?text=<?php echo urlencode($noticia['titulo'] . ' ' . $currentUrl); ?>" target="_blank" class="btn" style="background: #25D366; color: white; border-radius: 20px; font-size: 0.85rem; padding: 0.4rem 1rem;">
                            <i class="fab fa-whatsapp"></i> WhatsApp
                        </a>
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($currentUrl); ?>" target="_blank" class="btn" style="background: #1877F2; color: white; border-radius: 20px; font-size: 0.85rem; padding: 0.4rem 1rem;">
                            <i class="fab fa-facebook"></i> Facebook
                        </a>
                        <button onclick="navigator.clipboard.writeText(window.location.href); alert('¡Enlace copiado al portapapeles!');" class="btn btn-outline" style="border-radius: 20px; font-size: 0.85rem; padding: 0.4rem 1rem;">
                            <i class="fas fa-link"></i> Copiar Enlace
                        </button>
                    </div>
                </div>
            </div>

            <!-- Sidebar Column -->
            <div>
                <!-- Related Articles Widget -->
                <?php if (!empty($relacionadas)): ?>
                <div style="background: white; border-radius: 14px; padding: 1.5rem; box-shadow: 0 4px 15px rgba(0,0,0,0.06); margin-bottom: 2rem;">
                    <h3 style="font-size: 1.15rem; font-weight: 600; color: #2a004a; margin-top: 0; margin-bottom: 1.2rem; border-bottom: 2px solid #f0e6fa; padding-bottom: 0.5rem;">
                        <i class="fas fa-fire" style="color: #ff9800;"></i> Noticias Relacionadas
                    </h3>

                    <div style="display: flex; flex-direction: column; gap: 1.2rem;">
                        <?php foreach ($relacionadas as $rel): ?>
                        <div style="display: flex; gap: 12px; align-items: center;">
                            <div style="width: 75px; height: 60px; border-radius: 8px; overflow: hidden; background: #2a004a; flex-shrink: 0; position: relative;">
                                <?php if (!empty($rel['imagen_destacada'])): ?>
                                    <img src="../../<?php echo htmlspecialchars($rel['imagen_destacada']); ?>" alt="Rel" style="width: 100%; height: 100%; object-fit: cover; object-position: <?php echo !empty($rel['posicion_imagen']) ? htmlspecialchars($rel['posicion_imagen']) : 'center center'; ?>;">
                                <?php elseif (!empty($rel['youtube_id'])): ?>
                                    <img src="https://img.youtube.com/vi/<?php echo htmlspecialchars($rel['youtube_id']); ?>/mqdefault.jpg" alt="YT Thumbnail" style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: #ffcc80;">
                                        <i class="fas fa-newspaper"></i>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div>
                                <h4 style="font-size: 0.92rem; font-weight: 600; margin: 0 0 4px 0; line-height: 1.3;">
                                    <a href="view-news.php?slug=<?php echo htmlspecialchars($rel['slug']); ?>" style="color: #2a004a; text-decoration: none;">
                                        <?php echo htmlspecialchars($rel['titulo']); ?>
                                    </a>
                                </h4>
                                <small style="color: #888; font-size: 0.78rem;">
                                    <i class="far fa-calendar-alt"></i> <?php echo date('d/m/Y', strtotime($rel['creado_en'])); ?>
                                </small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- YouTube Channel Promo Card -->
                <div style="background: linear-gradient(135deg, #cc0000 0%, #990000 100%); border-radius: 14px; padding: 1.5rem; color: white; text-align: center; box-shadow: 0 6px 20px rgba(204,0,0,0.2);">
                    <i class="fab fa-youtube fa-3x" style="margin-bottom: 0.8rem;"></i>
                    <h3 style="margin: 0 0 0.5rem 0; font-size: 1.2rem;">Canal Oficial Fit5K</h3>
                    <p style="font-size: 0.88rem; opacity: 0.9; margin: 0 0 1.2rem 0; line-height: 1.4;">
                        Suscríbete a nuestro canal de YouTube para coberturas completas de carreras, entrenamientos y entrevistas.
                    </p>
                    <a href="https://www.youtube.com/@fit5krd" target="_blank" class="btn" style="background: white; color: #cc0000; font-weight: 700; border-radius: 20px; padding: 0.5rem 1.5rem; text-decoration: none; display: inline-block;">
                        <i class="fab fa-youtube"></i> Suscribirme
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
