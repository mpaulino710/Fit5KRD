<?php
// modules/admin/edit-user.php
require_once '../../config/config.php';
require_once '../../config/database.php';


// session_start(); removed as it's handled in config.php
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'organizador'])) {
    header('Location: ../auth/login.php');
    exit();
}

if ($_SESSION['user_type'] === 'organizador') {
    header('Location: dashboard.php');
    exit();
}

$db = getDB();
$error = '';
$success = '';

// Obtener ID del usuario
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($user_id <= 0) {
    header('Location: users.php');
    exit();
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];
    
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $tipo_usuario = $_POST['tipo_usuario'] ?? '';
    $estado = $_POST['estado'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validaciones básicas
    if (empty($nombre) || empty($apellido) || empty($email) || empty($tipo_usuario) || empty($estado)) {
        $response['message'] = 'Por favor completa todos los campos obligatorios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'El correo electrónico no es válido.';
    } else {
        // Verificar si el email ya existe para otro usuario
        $stmt_check = $db->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
        $stmt_check->bind_param("si", $email, $user_id);
        $stmt_check->execute();
        $stmt_check->store_result();
        
        if ($stmt_check->num_rows > 0) {
            $response['message'] = 'El correo electrónico ya está registrado por otro usuario.';
        } else {
            // Preparar actualización
            $query = "UPDATE usuarios SET nombre = ?, apellido = ?, email = ?, telefono = ?, tipo_usuario = ?, estado = ? WHERE id = ?";
            $params = [$nombre, $apellido, $email, $telefono, $tipo_usuario, $estado, $user_id];
            $types = "ssssssi";
            
            // Si se proporcionó una contraseña, actualizarla
            if (!empty($password)) {
                if ($password !== $confirm_password) {
                    $response['message'] = 'Las contraseñas no coinciden.';
                } elseif (strlen($password) < 6) {
                    $response['message'] = 'La contraseña debe tener al menos 6 caracteres.';
                } else {
                    $pepper = defined('PEPPER') ? PEPPER : '';
                    $password_hash = password_hash($password . $pepper, PASSWORD_DEFAULT);
                    $query = "UPDATE usuarios SET nombre = ?, apellido = ?, email = ?, telefono = ?, tipo_usuario = ?, estado = ?, password = ? WHERE id = ?";
                    $params = [$nombre, $apellido, $email, $telefono, $tipo_usuario, $estado, $password_hash, $user_id];
                    $types = "sssssssi";
                }
            }
            
            if (empty($response['message'])) {
                $stmt = $db->prepare($query);
                $stmt->bind_param($types, ...$params);
                
                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'Usuario actualizado correctamente.';
                    $response['redirect'] = 'users.php';
                } else {
                    $response['message'] = 'Error al actualizar el usuario: ' . $db->error;
                }
                $stmt->close();
            }
        }
        $stmt_check->close();
    }
    
    ob_clean();
    echo json_encode($response);
    exit();
}

// Obtener datos del usuario
$stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$usuario = $result->fetch_assoc();

if (!$usuario) {
    header('Location: users.php');
    exit();
}

$stmt->close();

include '../../includes/admin-header.php';
?>

<div class="admin-main">
    <div class="admin-header">
        <h1><i class="fas fa-user-edit"></i> Editar Usuario</h1>
        <div class="admin-header-actions">
            <a href="users.php" class="admin-btn admin-btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <!-- Mensajes de feedback -->
    <?php if ($error): ?>
    <div class="admin-alert admin-alert-danger">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
    </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
    <div class="admin-alert admin-alert-success">
        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
    </div>
    <?php endif; ?>

    <div class="admin-card">
        <div class="admin-card-header">
            <h3>Datos de <?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']); ?></h3>
            <span class="badge badge-primary">ID: <?php echo $usuario['id']; ?></span>
        </div>

        <form method="POST" action="" class="admin-form">
            <div class="form-section">
                <h3>Información Personal</h3>
                <div class="row">
                    <div class="form-group col-md-6">
                        <label for="nombre">Nombre *</label>
                        <input type="text" id="nombre" name="nombre" class="form-control" required
                               value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : htmlspecialchars($usuario['nombre']); ?>">
                    </div>
                    
                    <div class="form-group col-md-6">
                        <label for="apellido">Apellido *</label>
                        <input type="text" id="apellido" name="apellido" class="form-control" required
                               value="<?php echo isset($_POST['apellido']) ? htmlspecialchars($_POST['apellido']) : htmlspecialchars($usuario['apellido']); ?>">
                    </div>
                </div>
                
                <div class="row">
                    <div class="form-group col-md-6">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" class="form-control" required
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : htmlspecialchars($usuario['email']); ?>">
                    </div>
                    
                    <div class="form-group col-md-6">
                        <label for="telefono">Teléfono</label>
                        <input type="tel" id="telefono" name="telefono" class="form-control"
                               value="<?php echo isset($_POST['telefono']) ? htmlspecialchars($_POST['telefono']) : htmlspecialchars($usuario['telefono']); ?>">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Permisos y Estado</h3>
                <div class="row">
                    <div class="form-group col-md-6">
                        <label for="tipo_usuario">Rol del Usuario *</label>
                        <select id="tipo_usuario" name="tipo_usuario" class="form-control" <?php echo $user_id == $_SESSION['user_id'] ? 'disabled' : ''; ?>>
                            <?php
                            $roles = ['corredor' => 'Corredor', 'organizador' => 'Organizador', 'admin' => 'Administrador'];
                            $current_role = isset($_POST['tipo_usuario']) ? $_POST['tipo_usuario'] : $usuario['tipo_usuario'];
                            foreach ($roles as $value => $label): ?>
                                <option value="<?php echo $value; ?>" <?php echo $current_role === $value ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($user_id == $_SESSION['user_id']): ?>
                            <input type="hidden" name="tipo_usuario" value="<?php echo $usuario['tipo_usuario']; ?>">
                            <small class="form-text text-muted">No puedes cambiar tu propio rol.</small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group col-md-6">
                        <label for="estado">Estado *</label>
                        <select id="estado" name="estado" class="form-control" <?php echo $user_id == $_SESSION['user_id'] ? 'disabled' : ''; ?>>
                            <?php
                            $estados = ['activo' => 'Activo', 'inactivo' => 'Inactivo', 'suspendido' => 'Suspendido'];
                            $current_status = isset($_POST['estado']) ? $_POST['estado'] : $usuario['estado'];
                            foreach ($estados as $value => $label): ?>
                                <option value="<?php echo $value; ?>" <?php echo $current_status === $value ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($user_id == $_SESSION['user_id']): ?>
                            <input type="hidden" name="estado" value="<?php echo $usuario['estado']; ?>">
                            <small class="form-text text-muted">No puedes cambiar tu propio estado.</small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Seguridad</h3>
                <div class="alert alert-info" style="background: rgba(var(--admin-primary-rgb), 0.1); padding: 10px; border-radius: 4px; margin-bottom: 15px;">
                    <i class="fas fa-info-circle"></i> Completa estos campos <strong>solo si deseas cambiar la contraseña</strong> del usuario.
                </div>
                
                <div class="row">
                    <div class="form-group col-md-6">
                        <label for="password">Nueva Contraseña</label>
                        <input type="password" id="password" name="password" class="form-control" minlength="6">
                    </div>
                    
                    <div class="form-group col-md-6">
                        <label for="confirm_password">Confirmar Contraseña</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" minlength="6">
                    </div>
                </div>
            </div>

            <div class="form-actions" style="margin-top: 2rem;">
                <button type="submit" class="admin-btn admin-btn-primary" style="padding: 0.8rem 2rem;">
                    <i class="fas fa-save"></i> Guardar Cambios
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Validar confirmación de contraseña en el cliente
document.querySelector('form').addEventListener('submit', function(e) {
    const pass = document.getElementById('password').value;
    const confirm = document.getElementById('confirm_password').value;
    
    if (pass && pass !== confirm) {
        e.preventDefault();
        alert('Las contraseñas no coinciden.');
    }
});
</script>

<?php include '../../includes/admin-footer.php'; ?>
