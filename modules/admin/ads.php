<?php
// modules/admin/ads.php
require_once '../../config/config.php';
require_once '../../config/database.php';


// session_start(); removed as it's handled in config.php
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'organizador'])) {
    header('Location: ../auth/login.php');
    exit();
}

$db = getDB();

// Handle Status Toggle (Quick Action)
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $id = intval($_GET['toggle']);
    $stmt = $db->prepare("UPDATE anuncios SET estado = CASE WHEN estado = 'activo' THEN 'inactivo' ELSE 'activo' END WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header('Location: ads.php');
    exit();
}

// Fetch Ads
$query = "SELECT * FROM anuncios ORDER BY creado_en DESC";
$result = $db->query($query);
$anuncios = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

include '../../includes/admin-header.php';
?>

<div class="admin-main">
    <div class="admin-header">
        <div>
            <h1><i class="fas fa-ad"></i> Gestión de Anuncios y Promociones</h1>
            <p>Administra los banners que aparecen en el carrusel del home.</p>
        </div>
        <div class="admin-header-actions">
            <a href="create-ad.php" class="admin-btn admin-btn-primary">
                <i class="fas fa-plus"></i> Nuevo Anuncio
            </a>
        </div>
    </div>

    <!-- Stats Row (Optional) -->
    <div class="admin-stats">
        <?php
        $active = 0;
        $total = count($anuncios);
        $total_clicks = 0;
        foreach($anuncios as $a) {
            if($a['estado'] == 'activo') $active++;
            $total_clicks += $a['clicks'];
        }
        ?>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-images"></i></div>
            <div class="stat-number"><?php echo $total; ?></div>
            <div class="stat-label">Total Anuncios</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon-success"><i class="fas fa-check-circle"></i></div>
            <div class="stat-number"><?php echo $active; ?></div>
            <div class="stat-label">Activos</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon-warning"><i class="fas fa-mouse-pointer"></i></div>
            <div class="stat-number"><?php echo number_format($total_clicks); ?></div>
            <div class="stat-label">Total Clicks</div>
        </div>
    </div>

    <div class="admin-card">
        <?php if (!empty($anuncios)): ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Imagen</th>
                    <th>Título</th>
                    <th>Periodo</th>
                    <th>Clicks</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($anuncios as $ad): ?>
                <tr>
                    <td>
                        <img src="../../<?php echo htmlspecialchars($ad['imagen_url']); ?>" alt="Ad" style="height: 50px; border-radius: 4px; object-fit: cover;">
                    </td>
                    <td>
                        <strong><?php echo htmlspecialchars($ad['titulo']); ?></strong><br>
                        <small style="color: var(--text-light);"><?php echo htmlspecialchars($ad['enlace_url']); ?></small>
                    </td>
                    <td>
                        <small>
                            Del: <?php echo date('d/m/Y', strtotime($ad['fecha_inicio'])); ?><br>
                            Al: <?php echo $ad['fecha_fin'] ? date('d/m/Y', strtotime($ad['fecha_fin'])) : 'Indefinido'; ?>
                        </small>
                    </td>
                    <td><?php echo number_format($ad['clicks']); ?></td>
                    <td>
                        <a href="ads.php?toggle=<?php echo $ad['id']; ?>" class="badge badge-<?php echo $ad['estado'] === 'activo' ? 'success' : 'secondary'; ?>" style="text-decoration: none; cursor: pointer;">
                            <?php echo ucfirst($ad['estado']); ?>
                        </a>
                    </td>
                    <td>
                        <div class="table-actions">
                            <a href="edit-ad.php?id=<?php echo $ad['id']; ?>" class="admin-btn admin-btn-secondary action-btn" title="Editar">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="delete-ad.php?id=<?php echo $ad['id']; ?>" class="admin-btn admin-btn-danger action-btn" title="Eliminar" onclick="return confirm('¿Estás seguro de eliminar este anuncio?');">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-ad"></i>
            <h3>No hay anuncios creados</h3>
            <p>Comienza creando un anuncio promocional para el home.</p>
            <a href="create-ad.php" class="admin-btn admin-btn-primary">Crear Anuncio</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/admin-footer.php'; ?>
