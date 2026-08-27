<?php
// modules/admin/news.php
require_once '../../config/config.php';
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'organizador'])) {
    header('Location: ../auth/login.php');
    exit();
}

$db = getDB();

// Handle Status Toggle (Quick Action)
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $id = intval($_GET['toggle']);
    $stmt = $db->prepare("UPDATE noticias SET estado = CASE WHEN estado = 'publicado' THEN 'borrador' ELSE 'publicado' END WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header('Location: news.php');
    exit();
}

// Fetch News
$query = "SELECT n.*, u.nombre as autor_nombre FROM noticias n LEFT JOIN usuarios u ON n.autor_id = u.id ORDER BY n.creado_en DESC";
$result = $db->query($query);
$noticias = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Calculate stats
$total = count($noticias);
$publicadas = 0;
$borradores = 0;
$total_visitas = 0;

foreach ($noticias as $n) {
    if ($n['estado'] === 'publicado') $publicadas++;
    if ($n['estado'] === 'borrador') $borradores++;
    $total_visitas += intval($n['visitas']);
}

$page_title = "Gestión de Noticias";
include '../../includes/admin-header.php';
?>

<div class="admin-main">
    <div class="admin-header">
        <div>
            <h1><i class="fas fa-newspaper"></i> Gestión de Noticias y Blog</h1>
            <p>Publica artículos, novedades y vincula videos de YouTube para la comunidad Fit5K.</p>
        </div>
        <div class="admin-header-actions">
            <a href="create-news.php" class="admin-btn admin-btn-primary">
                <i class="fas fa-plus"></i> Nueva Noticia
            </a>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="admin-stats">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-newspaper"></i></div>
            <div class="stat-number"><?php echo $total; ?></div>
            <div class="stat-label">Total Noticias</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon-success"><i class="fas fa-check-circle"></i></div>
            <div class="stat-number"><?php echo $publicadas; ?></div>
            <div class="stat-label">Publicadas</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon-warning"><i class="fas fa-edit"></i></div>
            <div class="stat-number"><?php echo $borradores; ?></div>
            <div class="stat-label">Borradores</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-eye"></i></div>
            <div class="stat-number"><?php echo number_format($total_visitas); ?></div>
            <div class="stat-label">Total Lecturas</div>
        </div>
    </div>

    <div class="admin-card">
        <?php if (!empty($noticias)): ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th style="width: 80px;">Multimedia</th>
                    <th>Título / Resumen</th>
                    <th>YouTube</th>
                    <th>Estado</th>
                    <th>Fecha</th>
                    <th>Visitas</th>
                    <th style="width: 120px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($noticias as $noticia): ?>
                <tr>
                    <td>
                        <?php if (!empty($noticia['imagen_destacada'])): ?>
                            <img src="../../<?php echo htmlspecialchars($noticia['imagen_destacada']); ?>" alt="Noticia" style="width: 65px; height: 50px; border-radius: 6px; object-fit: cover;">
                        <?php elseif (!empty($noticia['youtube_id'])): ?>
                            <img src="https://img.youtube.com/vi/<?php echo htmlspecialchars($noticia['youtube_id']); ?>/mqdefault.jpg" alt="YouTube Thumbnail" style="width: 65px; height: 50px; border-radius: 6px; object-fit: cover;">
                        <?php else: ?>
                            <div style="width: 65px; height: 50px; background: #e9ecef; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: #adb5bd;">
                                <i class="fas fa-image fa-lg"></i>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><?php echo htmlspecialchars($noticia['titulo']); ?></strong>
                        <?php if (!empty($noticia['resumen'])): ?>
                            <br><small style="color: #6c757d; font-size: 0.85rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                <?php echo htmlspecialchars($noticia['resumen']); ?>
                            </small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($noticia['youtube_id'])): ?>
                            <a href="<?php echo htmlspecialchars($noticia['video_url']); ?>" target="_blank" style="color: #ff0000; text-decoration: none; font-weight: 500;">
                                <i class="fab fa-youtube"></i> Ver Video
                            </a>
                        <?php else: ?>
                            <span style="color: #999; font-size: 0.85rem;">N/A</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="news.php?toggle=<?php echo $noticia['id']; ?>" class="badge badge-<?php echo $noticia['estado'] === 'publicado' ? 'success' : ($noticia['estado'] === 'borrador' ? 'warning' : 'secondary'); ?>" style="text-decoration: none; cursor: pointer;">
                            <?php echo ucfirst($noticia['estado']); ?>
                        </a>
                    </td>
                    <td>
                        <small style="color: #6c757d;">
                            <?php echo date('d/m/Y H:i', strtotime($noticia['creado_en'])); ?>
                        </small>
                    </td>
                    <td>
                        <span class="badge" style="background: #e9ecef; color: #495057;">
                            <i class="fas fa-eye"></i> <?php echo number_format($noticia['visitas']); ?>
                        </span>
                    </td>
                    <td>
                        <div class="table-actions">
                            <a href="edit-news.php?id=<?php echo $noticia['id']; ?>" class="admin-btn admin-btn-secondary action-btn" title="Editar">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="delete-news.php?id=<?php echo $noticia['id']; ?>" class="admin-btn admin-btn-danger action-btn" title="Eliminar" onclick="return confirm('¿Estás seguro de eliminar esta noticia?');">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state" style="text-align: center; padding: 3rem 1rem;">
            <i class="fas fa-newspaper" style="font-size: 3rem; color: #ccc; margin-bottom: 1rem;"></i>
            <h3>No hay noticias creadas</h3>
            <p>Comienza publicando la primera noticia o vinculando un video de YouTube para el blog.</p>
            <a href="create-news.php" class="admin-btn admin-btn-primary" style="margin-top: 1rem;">
                <i class="fas fa-plus"></i> Crear Noticias
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/admin-footer.php'; ?>
