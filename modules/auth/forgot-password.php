<?php
// modules/auth/forgot-password.php
session_start();

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/recaptcha.php';
require_once '../../includes/SMTPMailer.php';

$db = getDB();
$error = '';
$success = '';

// Configurar intentos
if (!isset($_SESSION['forgot_attempts'])) {
    $_SESSION['forgot_attempts'] = 0;
    $_SESSION['forgot_blocked_until'] = 0;
}

if ($_SESSION['forgot_blocked_until'] > time()) {
    $blocked_time = $_SESSION['forgot_blocked_until'] - time();
    $error = "Demasiados intentos. Espera " . ceil($blocked_time/60) . " minutos.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
     // Honeypot
     if (!empty($_POST['website'])) {
        $error = 'Solicitud inválida.';
        $_SESSION['forgot_attempts']++;
    }

    // reCAPTCHA
    if (empty($error) && (!isset($_POST['recaptcha_token']) || empty($_POST['recaptcha_token']))) {
        $error = 'Error de seguridad. Recarga la página.';
    }

    if (empty($error)) {
        $recaptchaResult = validateRecaptcha(RECAPTCHA_SECRET_KEY, $_POST['recaptcha_token']);
        if (!isRecaptchaValid($recaptchaResult)) {
            $error = 'Verificación de robot fallida.';
            $_SESSION['forgot_attempts']++;
        }
    }

    if (empty($error)) {
        $email = trim($_POST['email'] ?? '');
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Ingresa un email válido.';
        } else {
            // Verificar si el email existe
            $stmt = $db->prepare("SELECT id, nombre FROM usuarios WHERE email = ? AND estado = 'activo'");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                // Generar token
                $token = bin2hex(random_bytes(32));
                $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Guardar token
                $stmt = $db->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $email, $token, $expires_at);
                
                if ($stmt->execute()) {
                    // Enviar email
                    $resetLink = SITE_URL . '/modules/auth/reset-password.php?token=' . $token;
                    $subject = 'Recuperar Contraseña - ' . SITE_NAME;
                    $message = "Hola {$user['nombre']},\n\n";
                    $message .= "Has solicitado restablecer tu contraseña. Haz clic en el siguiente enlace:\n\n";
                    $message .= $resetLink . "\n\n";
                    $message .= "Este enlace expira en 1 hora.\n";
                    $message .= "Si no solicitaste esto, ignora este correo.\n\n";
                    $message .= "Saludos,\nEquipo " . SITE_NAME;
                    
                    $message .= "Saludos,\nEquipo " . SITE_NAME;
                    
                    // Envio SMTP
                    $mailer = new SMTPMailer();
                    if ($mailer->send($email, $subject, nl2br($message))) {
                        $success = 'Si el email existe, recibirás las instrucciones.';
                    } else {
                        $error = 'Error al enviar el correo. Verifica los logs o la configuración SMTP.';
                        error_log("SMTP send failed to $email");
                    }
                } else {
                    $error = 'Ocurrió un error. Intenta más tarde.';
                    error_log("DB insert failed: " . $stmt->error);
                }
            } else {
                // Mensaje genérico por seguridad
                $success = 'Si el email existe, recibirás las instrucciones.';
                // Simular tiempo de espera para evitar enumeración
                sleep(1); 
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
                <i class="fas fa-unlock-alt"></i> Recuperar Contraseña
            </h2>
            <p class="auth-subtitle">Te enviaremos un enlace para restablecerla</p>
        </div>
        
        <?php if ($error): ?>
        <div class="auth-alert auth-alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo $error; ?></span>
        </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="auth-alert auth-alert-success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo $success; ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" action="" id="forgotForm">
            <div style="position: absolute; left: -9999px; opacity: 0;">
                <input type="text" name="website" tabindex="-1" autocomplete="off" placeholder="No llenar">
            </div>
            
            <input type="hidden" name="recaptcha_token" id="recaptcha_token">
            
            <div class="auth-form-group">
                <label for="email" class="auth-label">
                    <i class="fas fa-envelope"></i> Email Registrado
                </label>
                <input type="email" id="email" name="email" class="auth-input" required
                       placeholder="ejemplo@correo.com"
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>
            
            <div class="auth-btn-container">
                <button type="submit" class="btn btn-primary auth-btn" id="submitBtn">
                    <i class="fas fa-paper-plane"></i> 
                    <span id="submitText">Enviar Enlace</span>
                    <span id="loadingSpinner" style="display: none;">
                        <i class="fas fa-spinner fa-spin"></i> Enviando...
                    </span>
                </button>
            </div>
            
            <div class="auth-footer">
                <p><a href="login.php" class="text-secondary"><i class="fas fa-arrow-left"></i> Volver al Login</a></p>
            </div>
        </form>
    </div>
</div>

<script src="https://www.google.com/recaptcha/api.js?render=<?php echo RECAPTCHA_SITE_KEY; ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('forgotForm');
    const submitBtn = document.getElementById('submitBtn');
    const submitText = document.getElementById('submitText');
    const loadingSpinner = document.getElementById('loadingSpinner');
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (submitBtn.disabled) return;
        
        submitBtn.disabled = true;
        submitText.style.display = 'none';
        loadingSpinner.style.display = 'inline';
        
        grecaptcha.ready(function() {
            grecaptcha.execute('<?php echo RECAPTCHA_SITE_KEY; ?>', {
                action: 'forgot_password'
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

<?php include '../../includes/footer.php'; ?>
