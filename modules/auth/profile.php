<?php
// modules/auth/profile.php
require_once '../../config/config.php';  // Aquí ya se inicia sesión
require_once '../../config/database.php';

// REMOVER session_start() porque ya está en config.php
// session_start(); // <-- COMENTAR O ELIMINAR ESTA LÍNEA

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$db = getDB();
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';
$active_tab = $_GET['tab'] ?? 'profile'; // Default to profile tab

// Obtener datos del usuario
$stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Directorio para fotos de perfil - USAR RUTA ABSOLUTA
$upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/profiles/';

// Intentar crear directorio con permisos seguros
if (!file_exists($upload_dir)) {
    // Usar @ para suprimir warnings y manejarlo nosotros
    if (!@mkdir($upload_dir, 0755, true)) {
        // Si no se puede crear, usar directorio temporal o mostrar error
        $upload_dir = sys_get_temp_dir() . '/fit5k_profiles/';
        if (!file_exists($upload_dir)) {
            @mkdir($upload_dir, 0755, true);
        }
    }
}

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update_profile';
    
    if ($action === 'update_profile') {
        $nombre = trim($_POST['nombre']);
        $apellido = trim($_POST['apellido']);
        $cedula = trim($_POST['cedula']);
        $telefono = trim($_POST['telefono']);
        $fecha_nacimiento = $_POST['fecha_nacimiento'];
        $genero = $_POST['genero'];
        $direccion = trim($_POST['direccion']);
        
        // Procesar upload de foto de perfil
        $foto_perfil = $user['foto_perfil']; // Mantener la foto actual por defecto
        
        if (isset($_FILES['foto_perfil'])) {
            if ($_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['foto_perfil'];
                $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                $max_size = 5 * 1024 * 1024; // 5MB
                
                // Validar tipo de archivo
                if (!in_array($file['type'], $allowed_types)) {
                    $error = 'Formato de imagen no permitido. Use JPEG, PNG o GIF.';
                } 
                // Validar tamaño
                elseif ($file['size'] > $max_size) {
                    $error = 'La imagen es muy grande. Máximo 5MB.';
                } 
                else {
                    // Generar nombre único para el archivo
                    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = 'profile_' . $user_id . '_' . uniqid() . '.' . strtolower($file_extension);
                    $filepath = $upload_dir . $filename;
                    
                    // Intentar mover archivo
                    if (move_uploaded_file($file['tmp_name'], $filepath)) {
                        // Eliminar foto anterior si existe
                        if ($user['foto_perfil'] && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $user['foto_perfil'])) {
                            @unlink($_SERVER['DOCUMENT_ROOT'] . '/' . $user['foto_perfil']);
                        }
                        
                        $foto_perfil = 'uploads/profiles/' . $filename;
                    } else {
                        // Verificar si el directorio es escribible
                        if (!is_writable($upload_dir)) {
                            $error = 'No se tienen permisos para guardar la imagen en el servidor.';
                        } else {
                            $error = 'Error al subir la imagen. Intente nuevamente.';
                        }
                    }
                }
            } elseif ($_FILES['foto_perfil']['error'] !== UPLOAD_ERR_NO_FILE) {
                // Manejar errores de subida de PHP
                switch ($_FILES['foto_perfil']['error']) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        $error = 'El archivo es demasiado grande para el servidor. Límite: ' . ini_get('upload_max_filesize');
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $error = 'La subida del archivo se completó parcialmente.';
                        break;
                    case UPLOAD_ERR_NO_TMP_DIR:
                        $error = 'Falta la carpeta temporal en el servidor.';
                        break;
                    case UPLOAD_ERR_CANT_WRITE:
                        $error = 'No se pudo escribir el archivo en el disco.';
                        break;
                    default:
                        $error = 'Error desconocido al subir el archivo (Código: ' . $_FILES['foto_perfil']['error'] . ')';
                }
            }
        }
        
        // Validar unicidad de cédula si cambió
        if (empty($error) && $cedula !== ($user['cedula'] ?? '')) {
            $check = $db->prepare("SELECT id FROM usuarios WHERE cedula = ? AND id != ?");
            $check->bind_param("si", $cedula, $user_id);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $error = 'Esta cédula ya está registrada por otro usuario.';
            }
            $check->close();
        }

        // Solo actualizar si no hay errores
        if (empty($error)) {
            $stmt = $db->prepare("UPDATE usuarios SET nombre = ?, apellido = ?, cedula = ?, telefono = ?, fecha_nacimiento = ?, genero = ?, direccion = ?, foto_perfil = ? WHERE id = ?");
            $stmt->bind_param("ssssssssi", $nombre, $apellido, $cedula, $telefono, $fecha_nacimiento, $genero, $direccion, $foto_perfil, $user_id);
            
            if ($stmt->execute()) {
                $success = 'Perfil actualizado exitosamente';
                // Actualizar datos en sesión
                $_SESSION['user_name'] = $nombre . ' ' . $apellido;
                $_SESSION['user_photo'] = $foto_perfil;
                // Recargar datos del usuario
                $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();

            } else {
                $error = 'Error al actualizar el perfil: ' . $db->error;
            }
            $stmt->close();
        }
    }
    
    elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Verificar contraseña actual
        if (password_verify($current_password . PEPPER, $user['password'])) {
            if ($new_password === $confirm_password) {
                if (strlen($new_password) >= 6) {
                    $hashed_password = password_hash($new_password . PEPPER, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
                    $stmt->bind_param("si", $hashed_password, $user_id);
                    
                    if ($stmt->execute()) {
                        $success = 'Contraseña cambiada exitosamente';
                    } else {
                        $error = 'Error al cambiar la contraseña';
                    }
                    $stmt->close();
                } else {
                    $error = 'La nueva contraseña debe tener al menos 6 caracteres';
                }
            } else {
                $error = 'Las contraseñas nuevas no coinciden';
            }
        } else {
            $error = 'Contraseña actual incorrecta';
        }
    }
}



include '../../includes/header.php';
?>

<div class="container">
    <h1 class="text-center mb-4">
        <i class="fas fa-user-circle"></i> Mi Perfil
    </h1>
    
    <?php if ($error): ?>
    <div class="alert alert-danger fade-in">
        <i class="fas fa-exclamation-circle"></i>
        <span><?php echo $error; ?></span>
    </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
    <div class="alert alert-success fade-in">
        <i class="fas fa-check-circle"></i>
        <span><?php echo $success; ?></span>
    </div>
    <?php endif; ?>
    
    <div class="profile-container">
        <!-- Panel Lateral de Información -->
        <div class="profile-sidebar">
            <div class="profile-card">
                <div class="profile-avatar">
                    <?php if ($user['foto_perfil'] && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $user['foto_perfil'])): ?>
                        <img src="<?php echo '/' . $user['foto_perfil']; ?>" 
                             alt="<?php echo htmlspecialchars($user['nombre']); ?>"
                             class="profile-avatar-img">
                    <?php else: ?>
                        <div class="profile-avatar-default">
                            <?php echo strtoupper(substr($user['nombre'], 0, 1) . substr($user['apellido'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <h3 class="profile-name">
                    <?php echo htmlspecialchars($user['nombre'] . ' ' . $user['apellido']); ?>
                </h3>
                <p class="profile-email">
                    <?php echo htmlspecialchars($user['email']); ?>
                </p>
                
                <div class="profile-badge">
                    <?php echo ucfirst($user['tipo_usuario']); ?>
                </div>
                
                <div class="profile-stats">

                    <div class="stat-item">
                        <i class="fas fa-calendar-check"></i>
                        <span>Miembro desde <?php echo date('m/Y', strtotime($user['fecha_registro'])); ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Contenido Principal con Tabs -->
        <div class="profile-content">
            <div class="profile-tabs">
                <ul class="tab-list">
                    <li class="tab-item <?php echo $active_tab === 'profile' ? 'active' : ''; ?>">
                        <a href="?tab=profile" class="tab-link">
                            <i class="fas fa-user-edit"></i>
                            <span>Editar Perfil</span>
                        </a>
                    </li>
                    <li class="tab-item <?php echo $active_tab === 'password' ? 'active' : ''; ?>">
                        <a href="?tab=password" class="tab-link">
                            <i class="fas fa-key"></i>
                            <span>Cambiar Contraseña</span>
                        </a>
                    </li>

                </ul>
                
                <div class="tab-content">
                    <!-- Tab: Editar Perfil -->
                    <?php if ($active_tab === 'profile'): ?>
                    <div class="tab-pane active" id="profile-tab">
                        <h2 class="tab-title">
                            <i class="fas fa-user-edit"></i> Editar Información Personal
                        </h2>
                        
                        <form method="POST" action="" enctype="multipart/form-data" class="profile-form">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="form-section">
                                <h3 class="form-section-title">
                                    <i class="fas fa-camera"></i> Foto de Perfil
                                </h3>
                                <div class="avatar-upload">
                                    <div class="avatar-preview">
                                        <?php if ($user['foto_perfil'] && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $user['foto_perfil'])): ?>
                                            <img src="<?php echo '/' . $user['foto_perfil']; ?>" 
                                                 id="avatar-preview"
                                                 alt="Foto de perfil">
                                        <?php else: ?>
                                            <div class="avatar-preview-default" id="avatar-preview">
                                                <?php echo strtoupper(substr($user['nombre'], 0, 1) . substr($user['apellido'], 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="avatar-upload-controls">
                                        <label for="foto_perfil" class="btn btn-outline">
                                            <i class="fas fa-upload"></i> Subir Foto
                                        </label>
                                        <input type="file" 
                                               id="foto_perfil" 
                                               name="foto_perfil" 
                                               accept="image/*"
                                               class="d-none"
                                               onchange="previewAvatar(event)">
                                        <small class="text-muted d-block mt-2">
                                            Formatos: JPG, PNG, GIF. Procesamiento automático para optimizar tamaño.
                                        </small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-section">
                                <h3 class="form-section-title">
                                    <i class="fas fa-user"></i> Información Básica
                                </h3>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="nombre">Nombre *</label>
                                        <input type="text" 
                                               id="nombre" 
                                               name="nombre" 
                                               class="form-control" 
                                               required
                                               value="<?php echo htmlspecialchars($user['nombre']); ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="apellido">Apellido *</label>
                                        <input type="text" 
                                               id="apellido" 
                                               name="apellido" 
                                               class="form-control" 
                                               required
                                               value="<?php echo htmlspecialchars($user['apellido']); ?>">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="cedula">Cédula *</label>
                                    <input type="text" 
                                           id="cedula" 
                                           name="cedula" 
                                           class="form-control" 
                                           required
                                           value="<?php echo htmlspecialchars($user['cedula'] ?? ''); ?>"
                                           placeholder="Identificación personal">
                                </div>
                                
                                <div class="form-group">
                                    <label for="email">Email</label>
                                    <input type="email" 
                                           id="email" 
                                           class="form-control" 
                                           value="<?php echo htmlspecialchars($user['email']); ?>" 
                                           disabled
                                           style="background-color: #f5f5f5;">
                                    <small class="text-muted">El email no se puede modificar</small>
                                </div>
                            </div>
                            
                            <div class="form-section">
                                <h3 class="form-section-title">
                                    <i class="fas fa-info-circle"></i> Información Adicional
                                </h3>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="telefono">Teléfono</label>
                                        <input type="tel" 
                                               id="telefono" 
                                               name="telefono" 
                                               class="form-control"
                                               value="<?php echo htmlspecialchars($user['telefono'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="fecha_nacimiento">Fecha de Nacimiento</label>
                                        <input type="date" 
                                               id="fecha_nacimiento" 
                                               name="fecha_nacimiento" 
                                               class="form-control"
                                               value="<?php echo $user['fecha_nacimiento'] ?? ''; ?>">
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="genero">Género</label>
                                        <select id="genero" name="genero" class="form-control">
                                            <option value="">Seleccionar</option>
                                            <option value="M" <?php echo ($user['genero'] ?? '') === 'M' ? 'selected' : ''; ?>>Masculino</option>
                                            <option value="F" <?php echo ($user['genero'] ?? '') === 'F' ? 'selected' : ''; ?>>Femenino</option>
                                            <option value="O" <?php echo ($user['genero'] ?? '') === 'O' ? 'selected' : ''; ?>>Otro</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="direccion">Dirección</label>
                                    <textarea id="direccion" 
                                              name="direccion" 
                                              class="form-control" 
                                              rows="3"><?php echo htmlspecialchars($user['direccion'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary btn-large">
                                    <i class="fas fa-save"></i> Guardar Cambios
                                </button>
                                <a href="?tab=profile" class="btn btn-outline">Cancelar</a>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Tab: Cambiar Contraseña -->
                    <?php if ($active_tab === 'password'): ?>
                    <div class="tab-pane active" id="password-tab">
                        <h2 class="tab-title">
                            <i class="fas fa-key"></i> Cambiar Contraseña
                        </h2>
                        
                        <form method="POST" action="" class="password-form">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div class="form-section">
                                <div class="form-group">
                                    <label for="current_password">Contraseña Actual *</label>
                                    <input type="password" 
                                           id="current_password" 
                                           name="current_password" 
                                           class="form-control" 
                                           required
                                           placeholder="Ingresa tu contraseña actual">
                                    <small class="text-muted">Debes ingresar tu contraseña actual para continuar</small>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="new_password">Nueva Contraseña *</label>
                                        <input type="password" 
                                               id="new_password" 
                                               name="new_password" 
                                               class="form-control" 
                                               required
                                               placeholder="Mínimo 6 caracteres"
                                               minlength="6">
                                        <small class="text-muted">Mínimo 6 caracteres</small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="confirm_password">Confirmar Nueva Contraseña *</label>
                                        <input type="password" 
                                               id="confirm_password" 
                                               name="confirm_password" 
                                               class="form-control" 
                                               required
                                               placeholder="Repite la nueva contraseña">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary btn-large">
                                    <i class="fas fa-key"></i> Cambiar Contraseña
                                </button>
                                <a href="?tab=password" class="btn btn-outline">Cancelar</a>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>
                    

                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Preview de avatar
// Preview y Redimensionado de avatar
function previewAvatar(event) {
    const input = event.target;
    const preview = document.getElementById('avatar-preview');
    const MAX_WIDTH = 600;
    const MAX_HEIGHT = 600;
    const MAX_SIZE_MB = 10;
    const QUALITY = 0.8; // 80% calidad JPEG

    if (input.files && input.files[0]) {
        const file = input.files[0];

        // Validar tamaño inicial (antes de procesar) para evitar colgar el navegador con archivos gigantes
        if (file.size > MAX_SIZE_MB * 1024 * 1024) {
             alert(`La imagen es demasiado grande (${(file.size / 1024 / 1024).toFixed(2)} MB). Por favor elije una imagen menor a ${MAX_SIZE_MB}MB.`);
             input.value = ''; // Limpiar input
             return;
        }

        const reader = new FileReader();

        reader.onload = function(e) {
            const img = new Image();
            img.onload = function() {
                // Calcular nuevas dimensiones
                let width = img.width;
                let height = img.height;

                if (width > height) {
                    if (width > MAX_WIDTH) {
                        height *= MAX_WIDTH / width;
                        width = MAX_WIDTH;
                    }
                } else {
                    if (height > MAX_HEIGHT) {
                        width *= MAX_HEIGHT / height;
                        height = MAX_HEIGHT;
                    }
                }

                // Crear canvas para redimensionar
                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);

                // Convertir a blob (archivo) y luego reemplazar el input
                canvas.toBlob(function(blob) {
                    // Crear un nuevo File objeto con el blob
                    const newFile = new File([blob], "profile_resized.jpg", { type: "image/jpeg", lastModified: Date.now() });

                    // Usar DataTransfer para reemplazar el archivo en el input original
                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(newFile);
                    input.files = dataTransfer.files;

                    // Mostrar visualización
                    const resultUrl = canvas.toDataURL("image/jpeg", QUALITY);
                    
                    if (preview.tagName === 'IMG') {
                        preview.src = resultUrl;
                    } else {
                        const newImg = document.createElement('img');
                        newImg.id = 'avatar-preview';
                        newImg.src = resultUrl;
                        newImg.style.width = '100%';
                        newImg.style.height = '100%';
                        newImg.style.objectFit = 'cover';
                        
                        // Si el padre existe, reemplazar. Si no, solo agregar (precaución)
                        if(preview.parentNode) {
                            preview.parentNode.replaceChild(newImg, preview);
                        }
                    }
                    
                    console.log(`Imagen redimensionada: ${width}x${height}, ${(blob.size/1024).toFixed(2)} KB`);

                }, 'image/jpeg', QUALITY);
            };
            img.src = e.target.result;
        }
        reader.readAsDataURL(file);
    }
}

// Validación de contraseña en tiempo real
document.addEventListener('DOMContentLoaded', function() {
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    
    if (newPassword && confirmPassword) {
        function validatePasswords() {
            if (newPassword.value && confirmPassword.value) {
                if (newPassword.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity('Las contraseñas no coinciden');
                } else {
                    confirmPassword.setCustomValidity('');
                }
            }
        }
        
        newPassword.addEventListener('input', validatePasswords);
        confirmPassword.addEventListener('input', validatePasswords);
    }
});
</script>

<?php include '../../includes/footer.php'; ?>