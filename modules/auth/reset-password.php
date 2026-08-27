<?php
// modules/auth/reset-password.php
session_start();

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/recaptcha.php';

$db = getDB();
$error = '';
$success = '';
$validToken = false;
$token = $_GET['token'] ?? '';

// Validar token al cargar
if (empty($token)) {
    $error = 'Token inválido o no proporcionado.';
} else {
    // Buscar token válido y no expirado
    $stmt = $db->prepare("SELECT id, email FROM password_resets WHERE token = ? AND expires_at > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $validToken = true;
        $resetRequest = $result->fetch_assoc();
    } else {
        $error = 'El enlace ha expirado o no es válido. Solaricita uno nuevo.';
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error) && $validToken) {
    // reCAPTCHA
    if (!isset($_POST['recaptcha_token']) || empty($_POST['recaptcha_token'])) {
        $error = 'Error de seguridad. Recarga la página.';
    } else {
        $recaptchaResult = validateRecaptcha(RECAPTCHA_SECRET_KEY, $_POST['recaptcha_token']);
        if (!isRecaptchaValid($recaptchaResult)) {
            $error = 'Verificación de robot fallida.';
        }
    }

    if (empty($error)) {
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validaciones de contraseña
        if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $error = 'La contraseña debe tener 8+ caracteres, una mayúscula y un número.';
        } elseif ($password !== $confirm_password) {
            $error = 'Las contraseñas no coinciden.';
        } else {
            // Actualizar contraseña
            $hashed_password = password_hash($password . PEPPER, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE usuarios SET password = ? WHERE email = ?");
            $stmt->bind_param("ss", $hashed_password, $resetRequest['email']);
            
            if ($stmt->execute()) {
                // Eliminar token usado
                $db->query("DELETE FROM password_resets WHERE email = '" . $db->real_escape_string($resetRequest['email']) . "'");
                
                $success = '¡Contraseña actualizada! Redirigiendo al login...';
                echo '<meta http-equiv="refresh" content="3;url=login.php">';
            } else {
                $error = 'Error al actualizar. Intenta nuevamente.';
            }
            $stmt->close();
        }
    }
}

include '../../includes/header.php';
?>

<div class="auth-wrapper">
    <div class="form-container auth-container">
        <div class="auth-header">
            <h2 class="auth-title">
                <i class="fas fa-key"></i> Nueva Contraseña
            </h2>
            <p class="auth-subtitle">Ingresa tu nueva contraseña</p>
        </div>
        
        <?php if ($error): ?>
        <div class="auth-alert auth-alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo $error; ?></span>
        </div>
        <div class="auth-footer">
            <p><a href="forgot-password.php" class="text-secondary">Solicitar nuevo enlace</a></p>
        </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="auth-alert auth-alert-success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo $success; ?></span>
        </div>
        <?php endif; ?>

        <?php if ($validToken && empty($success)): ?>
        <form method="POST" action="" id="resetForm">
            <input type="hidden" name="recaptcha_token" id="recaptcha_token">
            
            <div class="auth-form-group">
                <label for="password" class="auth-label">Nueva Contraseña</label>
                <input type="password" id="password" name="password" class="auth-input" required 
                       minlength="8" placeholder="Mínimo 8 caracteres">
            </div>
            
            <div class="auth-form-group">
                <label for="confirm_password" class="auth-label">Confirmar Contraseña</label>
                <input type="password" id="confirm_password" name="confirm_password" class="auth-input" required
                       placeholder="Repite la contraseña">
            </div>
            
            <div class="auth-btn-container">
                <button type="submit" class="btn btn-primary auth-btn" id="submitBtn">
                    <i class="fas fa-save"></i> 
                    <span id="submitText">Guardar Contraseña</span>
                    <span id="loadingSpinner" style="display: none;">
                        <i class="fas fa-spinner fa-spin"></i> Procesando...
                    </span>
                </button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($validToken && empty($success)): ?>
<script src="https://www.google.com/recaptcha/api.js?render=<?php echo RECAPTCHA_SITE_KEY; ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('resetForm');
    const submitBtn = document.getElementById('submitBtn');
    const submitText = document.getElementById('submitText');
    const loadingSpinner = document.getElementById('loadingSpinner');
    
    // Validar coincidencia de contraseñas en tiempo real
    const password = document.getElementById('password');
    const confirm = document.getElementById('confirm_password');
    
    function validateMatch() {
        if(confirm.value && password.value !== confirm.value) {
            confirm.setCustomValidity('Las contraseñas no coinciden');
        } else {
            confirm.setCustomValidity('');
        }
    }
    
    password.addEventListener('change', validateMatch);
    confirm.addEventListener('keyup', validateMatch);

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (submitBtn.disabled) return;
        
        if (password.value !== confirm.value) {
            alert('Las contraseñas no coinciden');
            return;
        }

        submitBtn.disabled = true;
        submitText.style.display = 'none';
        loadingSpinner.style.display = 'inline';
        
        grecaptcha.ready(function() {
            grecaptcha.execute('<?php echo RECAPTCHA_SITE_KEY; ?>', {
                action: 'reset_password'
            }).then(function(token) {
                document.getElementById('recaptcha_token').value = token;
                form.submit();
            }).catch(function(error) {
                console.error('Error reCAPTCHA:', error);
                alert('Error de seguridad. Recarga la página.');
                submitBtn.disabled = false;
                submitText.style.display = 'inline';
                loadingSpinner.style.display = 'none';
            });
        });
    });
});
</script>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
