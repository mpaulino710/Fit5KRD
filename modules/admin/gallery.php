<?php
// modules/admin/gallery.php
require_once '../../config/config.php';
require_once '../../config/database.php';


// session_start(); removed as it's handled in config.php
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'organizador'])) {
    header('Location: ../auth/login.php');
    exit();
}

$db = getDB();

// Obtener álbumes con info del evento y conteo de fotos
$query = "SELECT a.*, e.nombre as evento_nombre, 
          (SELECT COUNT(*) FROM fotos f WHERE f.id_album = a.id) as total_fotos
          FROM albumes a
          JOIN eventos e ON a.id_evento = e.id
          ORDER BY a.fecha_creacion DESC";

$result = $db->query($query);
$albumes = $result->fetch_all(MYSQLI_ASSOC);

// Eliminar álbum
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $album_id = intval($_GET['delete']);
    // Las fotos se eliminan en cascada por la FK, pero debemos borrar archivos físicos
    // Obtenemos las fotos antes de borrar
    $fotos_query = "SELECT url_foto FROM fotos WHERE id_album = $album_id";
    $fotos = $db->query($fotos_query)->fetch_all(MYSQLI_ASSOC);
    
    foreach ($fotos as $foto) {
        $path = '../../' . $foto['url_foto'];
        if (file_exists($path)) {
            unlink($path);
        }
    }
    
    // Borrar directorio del album si está vacío
    $album_dir = '../../uploads/gallery/' . $album_id;
    if (is_dir($album_dir)) {
        rmdir($album_dir);
    }
    
    $db->query("DELETE FROM albumes WHERE id = $album_id");
    header('Location: gallery.php?msg=deleted');
    exit();
}

include '../../includes/admin-header.php';
?>

<div class="admin-main">
    <div class="admin-header">
        <h1><i class="fas fa-images"></i> Galería de Fotos</h1>
        <div class="admin-header-actions">
            <a href="create-album.php" class="admin-btn admin-btn-primary">
                <i class="fas fa-plus"></i> Nuevo Álbum
            </a>
            <a href="dashboard.php" class="admin-btn admin-btn-outline">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
    <div class="admin-alert admin-alert-success">
        <i class="fas fa-check-circle"></i> Álbum eliminado correctamente.
    </div>
    <?php endif; ?>

    <div class="admin-card">
        <?php if (!empty($albumes)): ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Álbum</th>
                    <th>Evento Relacionado</th>
                    <th>Fotos</th>
                    <th>Estado</th>
                    <th>Fecha Creación</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($albumes as $album): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($album['titulo']); ?></strong>
                    </td>
                    <td><?php echo htmlspecialchars($album['evento_nombre']); ?></td>
                    <td>
                        <span class="badge badge-info"><?php echo $album['total_fotos']; ?> fotos</span>
                    </td>
                    <td>
                        <?php 
                        $badge = $album['estado'] === 'publico' ? 'success' : 'secondary'; 
                        echo "<span class='badge badge-$badge'>" . ucfirst($album['estado']) . "</span>";
                        ?>
                    </td>
                    <td><?php echo date('d/m/Y', strtotime($album['fecha_creacion'])); ?></td>
                    <td>
                        <div class="actions">
                            <a href="manage-album.php?id=<?php echo $album['id']; ?>" class="admin-btn admin-btn-primary" title="Gestionar Fotos">
                                <i class="fas fa-camera"></i>
                            </a>
                            <a href="edit-album.php?id=<?php echo $album['id']; ?>" class="admin-btn admin-btn-info" title="Editar Detalles">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="?delete=<?php echo $album['id']; ?>" class="admin-btn admin-btn-danger" 
                               onclick="return confirm('¿Seguro que deseas eliminar este álbum y todas sus fotos?');" title="Eliminar">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div style="text-align: center; padding: 3rem;">
            <i class="fas fa-images" style="font-size: 3rem; color: var(--text-light); margin-bottom: 1rem;"></i>
            <h3>No hay álbumes creados</h3>
            <p style="color: var(--text-light); margin-bottom: 1.5rem;">Crea un álbum desde un evento completado para empezar.</p>
            <a href="create-album.php" class="admin-btn admin-btn-primary">
                <i class="fas fa-plus"></i> Crear Primer Álbum
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/admin-footer.php'; ?>
