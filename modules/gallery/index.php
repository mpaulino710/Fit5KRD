<?php
// modules/gallery/index.php
require_once '../../config/config.php';
require_once '../../config/database.php';

session_start();
// Proteger acceso: Solo usuarios registrados
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

$db = getDB();

// Obtener álbumes activos
$query = "SELECT a.*, e.nombre as evento_nombre, e.fecha_evento,
          (SELECT url_foto FROM fotos f WHERE f.id_album = a.id ORDER BY id ASC LIMIT 1) as cover_photo,
          (SELECT COUNT(*) FROM fotos f WHERE f.id_album = a.id) as total_fotos
          FROM albumes a 
          JOIN eventos e ON a.id_evento = e.id
          WHERE a.estado = 'publico'
          ORDER BY e.fecha_evento DESC";

$result = $db->query($query);
$albumes = $result->fetch_all(MYSQLI_ASSOC);

include '../../includes/header.php';
?>

<div class="container">
    <div class="section-header text-center" style="margin-bottom: 3rem;">
        <h1 class="section-title">Galería de Eventos</h1>
        <p class="section-description">Revive los mejores momentos de nuestras actividades.</p>
    </div>

    <?php if (empty($albumes)): ?>
        <div style="text-align: center; padding: 4rem; background: var(--bg-light); border-radius: var(--border-radius);">
            <i class="fas fa-images" style="font-size: 3rem; color: var(--text-light); margin-bottom: 1rem;"></i>
            <h3>Aún no tenemos fotos publicadas</h3>
            <p>Vuelve pronto para ver los recuerdos de nuestros eventos.</p>
        </div>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 2rem;">
            <?php foreach ($albumes as $album): 
                $cover = $album['cover_photo'] ? '../../' . $album['cover_photo'] : '../../assets/img/default-album.jpg';
                // Si no hay cover y no hay asset default, usar un placeholder de color
                $bg_style = $album['cover_photo'] 
                    ? "background-image: url('$cover');" 
                    : "background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));";
            ?>
            <div class="album-card" style="background: white; border-radius: var(--border-radius); overflow: hidden; box-shadow: var(--box-shadow); transition: transform 0.3s ease;">
                <a href="view.php?id=<?php echo $album['id']; ?>" style="text-decoration: none; color: inherit; display: block;">
                    <div style="height: 200px; background-size: cover; background-position: center; <?php echo $bg_style; ?> position: relative;">
                        <div style="position: absolute; top: 10px; right: 10px; background: rgba(0,0,0,0.6); color: white; padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.8rem;">
                            <i class="fas fa-camera"></i> <?php echo $album['total_fotos']; ?>
                        </div>
                    </div>
                    <div style="padding: 1.5rem;">
                        <h3 style="margin: 0 0 0.5rem 0; font-size: 1.2rem; color: var(--text-dark); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            <?php echo htmlspecialchars($album['titulo']); ?>
                        </h3>
                        <p style="margin: 0; color: var(--text-light); font-size: 0.9rem;">
                            <i class="fas fa-calendar-alt"></i> <?php echo date('d/m/Y', strtotime($album['fecha_evento'])); ?>
                        </p>
                        <p style="margin: 0.5rem 0 0 0; font-size: 0.9rem; color: var(--primary-color);">
                            <?php echo htmlspecialchars($album['evento_nombre']); ?>
                        </p>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.album-card:hover {
    transform: translateY(-5px);
}
</style>

<?php include '../../includes/footer.php'; ?>
