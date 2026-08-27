<?php
// modules/admin/promociones.php
require_once '../../config/config.php';
require_once '../../config/database.php';


// session_start(); removed as it's handled in config.php
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'organizador'])) {
    header('Location: ../auth/login.php');
    exit();
}

$db = getDB();

// Handle Delete
if (isset($_POST['delete_id'])) {
    $id = intval($_POST['delete_id']);
    $stmt = $db->prepare("DELETE FROM promociones WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $success = "Elemento eliminado correctamente.";
    } else {
        $error = "Error al eliminar: " . $db->error;
    }
}

// Handle Status Toggle
if (isset($_GET['toggle_status'])) {
    $id = intval($_GET['toggle_status']);
    $current = $_GET['current'];
    $new_status = ($current == 'activo') ? 'inactivo' : 'activo';
    $stmt = $db->prepare("UPDATE promociones SET estado = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $id);
    $stmt->execute();
    header('Location: promociones.php');
    exit();
}

// Fetch Cupones
$query_cupones = "SELECT p.*, e.nombre as evento_nombre 
          FROM promociones p 
          LEFT JOIN eventos e ON p.id_evento = e.id 
          WHERE p.tipo = 'cupon' OR p.tipo IS NULL
          ORDER BY p.created_at DESC";
$result_cupones = $db->query($query_cupones);

// Fetch Ofertas
$query_ofertas = "SELECT p.*, e.nombre as evento_nombre 
          FROM promociones p 
          LEFT JOIN eventos e ON p.id_evento = e.id 
          WHERE p.tipo = 'oferta'
          ORDER BY p.created_at DESC";
// Use try-catch or check if column exists to avoid fatal error if DB not updated?
// For now, assume DB is updated or user will update it.
$result_ofertas = $db->query($query_ofertas);

include '../../includes/admin-header.php';
?>

<div class="admin-main">
    <div class="admin-header">
        <h1><i class="fas fa-tags"></i> Gestión de Promociones</h1>
        
    </div>

    <?php if (isset($success)): ?>
        <div class="admin-alert admin-alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="admin-alert admin-alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <style>
        /* Estilos para Tabs */
        .tabs-container {
            margin-bottom: 2rem;
        }
        .tabs-header {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
            border-bottom: 2px solid #eee;
        }
        .tab-btn {
            padding: 1rem 2rem;
            font-size: 1.1rem;
            font-weight: 500;
            color: var(--admin-text-light, #666);
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: -2px;
        }
        .tab-btn:hover {
            color: var(--admin-primary, #007bff);
        }
        .tab-btn.active {
            color: var(--admin-primary, #007bff);
            border-bottom-color: var(--admin-primary, #007bff);
            font-weight: 600;
        }
        .tab-content {
            display: none;
            animation: fadeIn 0.3s ease;
        }
        .tab-content.active {
            display: block;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>

    <!-- Tabs Navigation -->
    <div class="tabs-container">
        <div class="tabs-header">
            <button class="tab-btn active" onclick="openTab(event, 'cupones')">
                <i class="fas fa-ticket-alt"></i> Códigos Promocionales
            </button>
            <button class="tab-btn" onclick="openTab(event, 'ofertas')">
                <i class="fas fa-percentage"></i> Ofertas Temporales
            </button>
        </div>

        <!-- Tab Content: Cupones -->
        <div id="cupones" class="tab-content active">
            <div style="display: flex; justify-content: flex-end; margin-bottom: 1rem;">
                <a href="crear-promocion.php" class="admin-btn admin-btn-primary">
                    <i class="fas fa-plus"></i> Nuevo Cupón
                </a>
            </div>
            <div class="admin-card">
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Descuento</th>
                                <th>Uso</th>
                                <th>Evento</th>
                                <th>Vigencia</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result_cupones && $result_cupones->num_rows > 0): ?>
                                <?php while ($row = $result_cupones->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($row['codigo']); ?></strong></td>
                                        <td><?php echo $row['porcentaje']; ?>%</td>
                                        <td>
                                            <?php echo $row['usados']; ?> / <?php echo $row['cantidad']; ?>
                                            <div style="background: #eee; height: 5px; width: 100px; border-radius: 3px; margin-top: 5px;">
                                                <div style="background: var(--admin-primary, #007bff); height: 100%; border-radius: 3px; width: <?php echo min(100, ($row['usados'] / max(1, $row['cantidad']) * 100)); ?>%;"></div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($row['evento_nombre']): ?>
                                                <span class="badge badge-info"><?php echo htmlspecialchars($row['evento_nombre']); ?></span>
                                            <?php else: ?>
                                                <span class="badge badge-success">Global</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small>
                                                Desde: <?php echo date('d/m/Y', strtotime($row['fecha_inicio'])); ?><br>
                                                Hasta: <?php echo date('d/m/Y', strtotime($row['fecha_fin'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo ($row['estado'] == 'activo') ? 'success' : 'danger'; ?>">
                                                <?php echo ucfirst($row['estado']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="?toggle_status=<?php echo $row['id']; ?>&current=<?php echo $row['estado']; ?>" 
                                                   class="btn-icon" title="<?php echo ($row['estado'] == 'activo') ? 'Desactivar' : 'Activar'; ?>">
                                                    <i class="fas fa-<?php echo ($row['estado'] == 'activo') ? 'ban' : 'check'; ?>"></i>
                                                </a>
                                                <form method="POST" action="" style="display: inline;" onsubmit="return confirm('¿Estás seguro de eliminar este cupón?');">
                                                    <input type="hidden" name="delete_id" value="<?php echo $row['id']; ?>">
                                                    <button type="submit" class="btn-icon delete" title="Eliminar">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 2rem;">
                                        <i class="fas fa-ticket-alt" style="font-size: 3rem; color: #ccc; margin-bottom: 1rem; display: block;"></i>
                                        No hay códigos promocionales creados.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tab Content: Ofertas -->
        <div id="ofertas" class="tab-content">
            <div style="display: flex; justify-content: flex-end; margin-bottom: 1rem;">
                <a href="crear-oferta.php" class="admin-btn admin-btn-primary">
                    <i class="fas fa-plus"></i> Nueva Oferta Temporal
                </a>
            </div>
            <div class="admin-card">
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Nombre Oferta</th>
                                <th>Descuento</th>
                                <th>Evento</th>
                                <th>Vigencia</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result_ofertas && $result_ofertas->num_rows > 0): ?>
                                <?php while ($row = $result_ofertas->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($row['nombre'] ?? 'Oferta sin nombre'); ?></strong></td>
                                        <td><?php echo $row['porcentaje']; ?>%</td>
                                        <td>
                                            <?php if ($row['evento_nombre']): ?>
                                                <span class="badge badge-info"><?php echo htmlspecialchars($row['evento_nombre']); ?></span>
                                            <?php else: ?>
                                                <span class="badge badge-success">Global</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small>
                                                Desde: <?php echo date('d/m/Y H:i', strtotime($row['fecha_inicio'])); ?><br>
                                                Hasta: <?php echo date('d/m/Y H:i', strtotime($row['fecha_fin'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo ($row['estado'] == 'activo') ? 'success' : 'danger'; ?>">
                                                <?php echo ucfirst($row['estado']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="?toggle_status=<?php echo $row['id']; ?>&current=<?php echo $row['estado']; ?>" 
                                                   class="btn-icon" title="<?php echo ($row['estado'] == 'activo') ? 'Desactivar' : 'Activar'; ?>">
                                                    <i class="fas fa-<?php echo ($row['estado'] == 'activo') ? 'ban' : 'check'; ?>"></i>
                                                </a>
                                                <form method="POST" action="" style="display: inline;" onsubmit="return confirm('¿Estás seguro de eliminar esta oferta?');">
                                                    <input type="hidden" name="delete_id" value="<?php echo $row['id']; ?>">
                                                    <button type="submit" class="btn-icon delete" title="Eliminar">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 2rem;">
                                        <i class="fas fa-percent" style="font-size: 3rem; color: #ccc; margin-bottom: 1rem; display: block;"></i>
                                        No hay ofertas temporales creadas.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Exportar datos -->
    <?php $export_type = 'promociones'; include '../../includes/export-buttons.php'; ?>

<script>
function openTab(evt, tabName) {
    // Hide all tab content
    document.querySelectorAll('.tab-content').forEach(content => {
        content.style.display = 'none';
        content.classList.remove('active');
    });
    
    // Deactivate all buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show current tab and activate button
    document.getElementById(tabName).style.display = 'block';
    setTimeout(() => {
        document.getElementById(tabName).classList.add('active');
    }, 10);
    
    evt.currentTarget.classList.add('active');
    
    // Save tab preference
    localStorage.setItem('activePromoTab', tabName);
}

// Restore tab on load
document.addEventListener('DOMContentLoaded', (event) => {
    const activeTab = localStorage.getItem('activePromoTab');
    if (activeTab) {
        const tabBtn = document.querySelector(`.tab-btn[onclick*="${activeTab}"]`);
        if (tabBtn) {
            tabBtn.click();
        }
    }
});
</script>

<?php include '../../includes/admin-footer.php'; ?>
