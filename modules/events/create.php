<?php
// modules/events/create.php
require_once '../../config/config.php';
require_once '../../config/database.php';

session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'organizador'])) {
    header('Location: ../auth/login.php');
    exit();
}

$db = getDB();
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $fecha_evento = $_POST['fecha_evento'];
    $ubicacion = trim($_POST['ubicacion']);
    $distancia = $_POST['distancia'];
    $cupo_maximo = $_POST['cupo_maximo'];
    $precio = $_POST['precio'];
    
    // Validaciones
    if (empty($nombre) || empty($fecha_evento) || empty($ubicacion) || empty($distancia)) {
        $error = 'Por favor, completa todos los campos obligatorios';
    } elseif ($cupo_maximo <= 0) {
        $error = 'El cupo máximo debe ser mayor a 0';
    } elseif ($distancia <= 0) {
        $error = 'La distancia debe ser mayor a 0';
    } else {
        // Preparar datos
        $cupo_disponible = $cupo_maximo;
        $estado = 'activo';
        $precio = floatval($precio);
        
        // Subir imagen si se proporcionó
        $imagen_url = '';
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $file_type = $_FILES['imagen']['type'];
            
            if (in_array($file_type, $allowed_types)) {
                $upload_dir = '../../assets/images/events/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_extension = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
                $filename = 'event_' . time() . '_' . uniqid() . '.' . $file_extension;
                $upload_path = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['imagen']['tmp_name'], $upload_path)) {
                    $imagen_url = 'assets/images/events/' . $filename;
                }
            }
        }
        
        // Insertar evento
        $stmt = $db->prepare("INSERT INTO eventos (nombre, descripcion, fecha_evento, ubicacion, distancia, cupo_maximo, cupo_disponible, precio, imagen_url, estado, id_organizador) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssdiiiiss", $nombre, $descripcion, $fecha_evento, $ubicacion, $distancia, $cupo_maximo, $cupo_disponible, $precio, $imagen_url, $estado, $user_id);
        
        if ($stmt->execute()) {
            $event_id = $stmt->insert_id;
            $success = 'Evento creado exitosamente';
            header('refresh:2;url=view.php?id=' . $event_id);
        } else {
            $error = 'Error al crear el evento: ' . $db->error;
        }
        $stmt->close();
    }
}

include '../../includes/header.php';
?>

<div class="container">
    <div style="max-width: 800px; margin: 0 auto;">
        <h1 style="color: var(--primary-color); margin-bottom: 2rem;">
            <i class="fas fa-plus-circle"></i> Crear Nuevo Evento
        </h1>
        
        <?php if ($error): ?>
        <div style="
            background: #ffebee;
            color: #c62828;
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        ">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo $error; ?></span>
        </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div style="
            background: #e8f5e9;
            color: #2e7d32;
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        ">
            <i class="fas fa-check-circle"></i>
            <span><?php echo $success; ?></span>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="" enctype="multipart/form-data" class="form-container">
            <div class="form-group">
                <label for="nombre">Nombre del Evento *</label>
                <input type="text" id="nombre" name="nombre" class="form-control" required
                       placeholder="Ej: Maratón Ciudad 5K 2024"
                       value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="descripcion">Descripción</label>
                <textarea id="descripcion" name="descripcion" class="form-control" rows="4"
                          placeholder="Describe el evento, incluye información importante para los participantes..."><?php echo isset($_POST['descripcion']) ? htmlspecialchars($_POST['descripcion']) : ''; ?></textarea>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div class="form-group">
                    <label for="fecha_evento">Fecha y Hora del Evento *</label>
                    <input type="datetime-local" id="fecha_evento" name="fecha_evento" class="form-control" required
                           value="<?php echo isset($_POST['fecha_evento']) ? $_POST['fecha_evento'] : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="ubicacion">Ubicación *</label>
                    <input type="text" id="ubicacion" name="ubicacion" class="form-control" required
                           placeholder="Ej: Parque Central, Ciudad"
                           value="<?php echo isset($_POST['ubicacion']) ? htmlspecialchars($_POST['ubicacion']) : ''; ?>">
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div class="form-group">
                    <label for="distancia">Distancia (km) *</label>
                    <input type="number" id="distancia" name="distancia" class="form-control" required min="0" step="0.1"
                           value="<?php echo isset($_POST['distancia']) ? $_POST['distancia'] : '5'; ?>">
                </div>
                
                <div class="form-group">
                    <label for="cupo_maximo">Cupo Máximo *</label>
                    <input type="number" id="cupo_maximo" name="cupo_maximo" class="form-control" required min="1"
                           value="<?php echo isset($_POST['cupo_maximo']) ? $_POST['cupo_maximo'] : '100'; ?>">
                </div>
                
                <div class="form-group">
                    <label for="precio">Precio ($)</label>
                    <input type="number" id="precio" name="precio" class="form-control" min="0" step="0.01"
                           value="<?php echo isset($_POST['precio']) ? $_POST['precio'] : '0'; ?>">
                    <small style="color: var(--text-light);">0 = Gratuito</small>
                </div>
            </div>
            
            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label for="imagen">Imagen del Evento</label>
                <input type="file" id="imagen" name="imagen" class="form-control" accept="image/*">
                <small style="color: var(--text-light);">Formatos aceptados: JPG, PNG, GIF (Máx. 2MB)</small>
                
                <div id="imagePreview" style="margin-top: 1rem; display: none;">
                    <img id="previewImage" src="#" alt="Preview" style="max-width: 200px; max-height: 200px; border-radius: var(--border-radius);">
                </div>
            </div>
            
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Cancelar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Crear Evento
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Preview de imagen
document.getElementById('imagen').addEventListener('change', function(e) {
    const file = e.target.files[0];
    const preview = document.getElementById('previewImage');
    const previewContainer = document.getElementById('imagePreview');
    
    if (file) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            preview.src = e.target.result;
            previewContainer.style.display = 'block';
        }
        
        reader.readAsDataURL(file);
    } else {
        previewContainer.style.display = 'none';
    }
});

// Validación de fecha futura
document.getElementById('fecha_evento').addEventListener('change', function() {
    const selectedDate = new Date(this.value);
    const now = new Date();
    
    if (selectedDate < now) {
        alert('La fecha del evento debe ser futura');
        this.value = '';
    }
});

// Auto-generar nombre de archivo
document.getElementById('nombre').addEventListener('blur', function() {
    const nombre = this.value;
    const slug = nombre.toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
    
    // Podemos usar el slug para algo si es necesario
});
</script>

<?php include '../../includes/footer.php'; ?>