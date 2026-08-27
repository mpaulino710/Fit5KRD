<?php
// modules/admin/create-album.php
require_once '../../config/config.php';
require_once '../../config/database.php';


// session_start(); removed as it's handled in config.php
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'organizador'])) {
    header('Location: ../auth/login.php');
    exit();
}

$db = getDB();
$error = '';

// Obtener eventos completados que NO tengan album todavía
// O podemos permitir múltiples albumes, pero simplifiquemos a uno por evento por ahora
$query = "SELECT id, nombre, fecha_evento FROM eventos 
          WHERE estado = 'completado' 
          AND id NOT IN (SELECT id_evento FROM albumes)
          ORDER BY fecha_evento DESC";
$eventos = $db->query($query)->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $evento_id = intval($_POST['evento_id']);
    $titulo = trim($_POST['titulo']);
    $descripcion = trim($_POST['descripcion']);
    
    if (empty($titulo) || $evento_id <= 0) {
        $error = 'Por favor selecciona un evento y ponle un título al álbum.';
    } else {
        $stmt = $db->prepare("INSERT INTO albumes (id_evento, titulo, descripcion, estado) VALUES (?, ?, ?, 'publico')");
        $stmt->bind_param("iss", $evento_id, $titulo, $descripcion);
        
        if ($stmt->execute()) {
            $album_id = $stmt->insert_id;
            // Compuesto carpeta del album
            $album_path = '../../uploads/gallery/' . $album_id;
            if (!file_exists($album_path)) {
                mkdir($album_path, 0755, true);
            }
            
            ob_clean();
            echo json_encode(['success' => true, 'redirect' => "manage-album.php?id=$album_id"]);
            exit();
        } else {
            $error = 'Error creando el álbum: ' . $db->error;
        }
    }
    
    ob_clean();
    echo json_encode(['success' => false, 'message' => $error]);
    exit();
}

include '../../includes/admin-header.php';
?>

<div class="admin-main">
    <div class="admin-header">
        <h1><i class="fas fa-plus-circle"></i> Nuevo Álbum</h1>
        <div class="admin-header-actions">
            <a href="gallery.php" class="admin-btn admin-btn-secondary">
                <i class="fas fa-arrow-left"></i> Cancelar
            </a>
        </div>
    </div>

    <?php if ($error): ?>
    <div class="admin-alert admin-alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="admin-card" style="max-width: 600px; margin: 0 auto;">
        <?php if (empty($eventos)): ?>
            <div style="text-align: center; padding: 2rem;">
                <i class="fas fa-exclamation-circle" style="font-size: 2rem; color: var(--warning); margin-bottom: 1rem;"></i>
                <p>No hay eventos completados disponibles para crear álbumes.</p>
                <p><small>Asegúrate de marcar los eventos pasados como "Completado".</small></p>
                <a href="events.php" class="admin-btn admin-btn-primary">Ir a Eventos</a>
            </div>
        <?php else: ?>
        <form method="POST" action="" class="admin-form">
            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label for="evento_id">Seleccionar Evento Completado *</label>
                <select name="evento_id" id="evento_id" class="form-control" required onchange="updateTitle()">
                    <option value="">-- Selecciona un evento --</option>
                    <?php foreach ($eventos as $ev): ?>
                    <option value="<?php echo $ev['id']; ?>" data-name="<?php echo htmlspecialchars($ev['nombre']); ?>">
                        <?php echo htmlspecialchars($ev['nombre']); ?> (<?php echo date('d/m/Y', strtotime($ev['fecha_evento'])); ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label for="titulo">Título del Álbum *</label>
                <input type="text" name="titulo" id="titulo" class="form-control" required placeholder="Ej: Fotos Carrera 5K 2024">
            </div>
            
            <div class="form-group" style="margin-bottom: 2rem;">
                <label for="descripcion">Descripción</label>
                <textarea name="descripcion" id="descripcion" class="form-control" rows="3"></textarea>
            </div>
            
            <button type="submit" class="admin-btn admin-btn-primary" style="width: 100%;">
                Crear y Subir Fotos <i class="fas fa-arrow-right"></i>
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>

<script>
function updateTitle() {
    const select = document.getElementById('evento_id');
    const titleInput = document.getElementById('titulo');
    const selectedOption = select.options[select.selectedIndex];
    
    if (selectedOption.value && !titleInput.value) {
        titleInput.value = 'Galería: ' + selectedOption.dataset.name;
    }
}
</script>

<?php include '../../includes/admin-footer.php'; ?>
