<?php
// modules/public/news.php
require_once '../../config/config.php';
require_once '../../config/database.php';

$db = getDB();

$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$filter = isset($_GET['filter']) ? trim($_GET['filter']) : 'all';

// Build SQL query
$query = "SELECT n.*, u.nombre as autor_nombre FROM noticias n LEFT JOIN usuarios u ON n.autor_id = u.id WHERE n.estado = 'publicado'";

if (!empty($search)) {
    $search_escaped = $db->real_escape_string($search);
    $query .= " AND (n.titulo LIKE '%$search_escaped%' OR n.resumen LIKE '%$search_escaped%' OR n.contenido LIKE '%$search_escaped%')";
}

if ($filter === 'video') {
    $query .= " AND n.youtube_id IS NOT NULL AND n.youtube_id != ''";
} elseif ($filter === 'article') {
    $query .= " AND (n.youtube_id IS NULL OR n.youtube_id = '')";
}

$query .= " ORDER BY n.fecha_publicacion DESC, n.creado_en DESC";
$result = $db->query($query);
$noticias = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

$page_title = "Noticias y Novedades";
include '../../includes/header.php';
?>

<div class="news-page-container" style="padding-top: 1rem; padding-bottom: 3rem;">
    <!-- Hero Banner -->
    <div style="background: linear-gradient(135deg, #2a004a 0%, #6a0dad 100%); border-radius: 16px; padding: 2.5rem 2rem; color: white; margin-bottom: 2rem; text-align: center; box-shadow: 0 10px 25px rgba(42,0,74,0.15);">
        <h1 style="font-size: 2.2rem; font-weight: 700; margin: 0 0 0.8rem 0;">
            <i class="fas fa-newspaper" style="color: #ffcc80;"></i> Noticias & Blog Fit5K
        </h1>
        <p style="font-size: 1.1rem; opacity: 0.9; max-width: 650px; margin: 0 auto 1.5rem auto;">
            Descubre los últimos artículos sobre salud, entrenamiento, cobertura de maratones y lanzamientos de la comunidad Fit5K.
        </p>

        <!-- Search Form -->
        <form method="GET" action="news.php" style="max-width: 550px; margin: 0 auto; display: flex; gap: 8px;">
            <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
            <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Buscar noticias, eventos o consejos..." style="flex: 1; padding: 0.85rem 1.2rem; border-radius: 30px; border: none; font-size: 0.95rem; outline: none; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
            <button type="submit" class="btn btn-primary" style="border-radius: 30px; padding: 0.85rem 1.5rem; background: #ffcc80; color: #2a004a; font-weight: 600; border: none;">
                <i class="fas fa-search"></i> Buscar
            </button>
        </form>
    </div>

    <!-- Filter Buttons -->
    <div style="display: flex; justify-content: center; gap: 10px; margin-bottom: 2rem; flex-wrap: wrap;">
        <a href="news.php?filter=all<?php echo !empty($search) ? '&q='.urlencode($search) : ''; ?>" class="btn <?php echo $filter === 'all' ? 'btn-primary' : 'btn-outline'; ?>" style="border-radius: 20px; padding: 0.5rem 1.2rem; font-size: 0.9rem;">
            <i class="fas fa-th-large"></i> Todos los Artículos
        </a>
        <a href="news.php?filter=video<?php echo !empty($search) ? '&q='.urlencode($search) : ''; ?>" class="btn <?php echo $filter === 'video' ? 'btn-primary' : 'btn-outline'; ?>" style="border-radius: 20px; padding: 0.5rem 1.2rem; font-size: 0.9rem;">
            <i class="fab fa-youtube" style="color: #ff0000;"></i> Con Video de YouTube
        </a>
        <a href="news.php?filter=article<?php echo !empty($search) ? '&q='.urlencode($search) : ''; ?>" class="btn <?php echo $filter === 'article' ? 'btn-primary' : 'btn-outline'; ?>" style="border-radius: 20px; padding: 0.5rem 1.2rem; font-size: 0.9rem;">
            <i class="fas fa-file-alt"></i> Solo Lectura
        </a>
    </div>

    <!-- News Grid -->
    <?php if (!empty($noticias)): ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.8rem;">
            <?php foreach ($noticias as $noticia): ?>
            <div class="news-card-item" style="background: white; border-radius: 14px; overflow: hidden; box-shadow: 0 5px 20px rgba(0,0,0,0.06); transition: all 0.3s ease; display: flex; flex-direction: column;">
                
                <div style="position: relative; height: 210px; overflow: hidden; background: #1a0033;">
                    <?php if (!empty($noticia['youtube_id'])): ?>
                        <iframe src="https://www.youtube.com/embed/<?php echo htmlspecialchars($noticia['youtube_id']); ?>" style="width: 100%; height: 100%; border: 0;" allowfullscreen></iframe>
                    <?php elseif (!empty($noticia['imagen_destacada'])): ?>
                        <img src="../../<?php echo htmlspecialchars($noticia['imagen_destacada']); ?>" alt="<?php echo htmlspecialchars($noticia['titulo']); ?>" style="width: 100%; height: 100%; object-fit: cover; object-position: <?php echo !empty($noticia['posicion_imagen']) ? htmlspecialchars($noticia['posicion_imagen']) : 'center center'; ?>;">
                    <?php else: ?>
                        <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: #ffcc80;">
                            <i class="fas fa-newspaper fa-4x"></i>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($noticia['youtube_id'])): ?>
                        <span style="position: absolute; top: 12px; right: 12px; background: #ff0000; color: white; padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; display: flex; align-items: center; gap: 4px; pointer-events: none; box-shadow: 0 2px 6px rgba(0,0,0,0.3);">
                            <i class="fab fa-youtube"></i> Video
                        </span>
                    <?php endif; ?>
                </div>

                <div style="padding: 1.4rem; display: flex; flex-direction: column; flex: 1;">
                    <div style="font-size: 0.8rem; color: #888; margin-bottom: 0.6rem; display: flex; align-items: center; justify-content: space-between;">
                        <span><i class="far fa-calendar-alt"></i> <?php echo date('d/m/Y', strtotime($noticia['creado_en'])); ?></span>
                        <span><i class="far fa-eye"></i> <?php echo number_format($noticia['visitas']); ?> vistas</span>
                    </div>

                    <h2 style="font-size: 1.25rem; font-weight: 600; margin: 0 0 0.7rem 0; line-height: 1.4;">
                        <a href="view-news.php?slug=<?php echo htmlspecialchars($noticia['slug']); ?>" style="color: #2a004a; text-decoration: none;">
                            <?php echo htmlspecialchars($noticia['titulo']); ?>
                        </a>
                    </h2>

                    <?php if (!empty($noticia['resumen'])): ?>
                        <p style="color: #555; font-size: 0.92rem; line-height: 1.5; margin: 0 0 1.2rem 0; flex: 1; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
                            <?php echo htmlspecialchars($noticia['resumen']); ?>
                        </p>
                    <?php endif; ?>

                    <div style="margin-top: auto; padding-top: 0.8rem; border-top: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center;">
                        <a href="view-news.php?slug=<?php echo htmlspecialchars($noticia['slug']); ?>" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.85rem; border-radius: 8px;">
                            Leer Completo <i class="fas fa-chevron-right" style="font-size: 0.75rem;"></i>
                        </a>
                        <small style="color: #888; font-size: 0.8rem;">
                            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($noticia['autor_nombre'] ?? 'Fit5K Soporte'); ?>
                        </small>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 4rem 1rem; background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
            <i class="fas fa-newspaper" style="font-size: 3.5rem; color: #ccc; margin-bottom: 1rem;"></i>
            <h3 style="color: #2a004a;">No se encontraron noticias</h3>
            <p style="color: #666;">No hay artículos disponibles que coincidan con tu búsqueda o filtro seleccionado.</p>
            <a href="news.php" class="btn btn-outline" style="margin-top: 1rem; border-radius: 20px;">
                Ver Todas las Noticias
            </a>
        </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>
