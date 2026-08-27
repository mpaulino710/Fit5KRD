<?php
// modules/auth/login.php
session_start(); // Para control de intentos

if (isset($_SESSION['user_id'])) {
    header('Location: /modules/public/home.php');
    exit();
}

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/recaptcha.php'; // Incluir reCAPTCHA

$db = getDB();
$error = '';
$success = '';

// Definir función auxiliar para registrar intentos fallidos en la base de datos
function registrarIntentoFallido($db, $ip) {
    $stmt = $db->prepare("SELECT intentos FROM bloqueos_ip WHERE ip_address = ?");
    $stmt->bind_param("s", $ip);
    $stmt->execute();
    $res = $stmt->get_result();
    $intentos = 1;
    $bloqueado_hasta = null;

    if ($res->num_rows === 1) {
        $row = $res->fetch_assoc();
        $intentos = $row['intentos'] + 1;
        if ($intentos >= 5) {
            $bloqueado_hasta = date('Y-m-d H:i:s', time() + 86400); // 24 horas
        }
        $stmt_update = $db->prepare("UPDATE bloqueos_ip SET intentos = ?, bloqueado_hasta = ? WHERE ip_address = ?");
        $stmt_update->bind_param("iss", $intentos, $bloqueado_hasta, $ip);
        $stmt_update->execute();
        $stmt_update->close();
    } else {
        $stmt_insert = $db->prepare("INSERT INTO bloqueos_ip (ip_address, intentos, bloqueado_hasta) VALUES (?, ?, ?)");
        $stmt_insert->bind_param("sis", $ip, $intentos, $bloqueado_hasta);
        $stmt_insert->execute();
        $stmt_insert->close();
    }
    $stmt->close();
    return ['intentos' => $intentos, 'bloqueado_hasta' => $bloqueado_hasta];
}

$ip = $_SERVER['REMOTE_ADDR'];
$login_attempts = 0;
$is_blocked = false;
$blocked_until_ts = 0;

// Verificar estado de intentos y bloqueo en la BD
$stmt = $db->prepare("SELECT intentos, bloqueado_hasta FROM bloqueos_ip WHERE ip_address = ?");
$stmt->bind_param("s", $ip);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 1) {
    $row = $res->fetch_assoc();
    $login_attempts = $row['intentos'];
    if ($row['bloqueado_hasta']) {
        $blocked_until_ts = strtotime($row['bloqueado_hasta']);
        if ($blocked_until_ts > time()) {
            $is_blocked = true;
        } else {
            // El bloqueo ha expirado, limpiar la BD
            $stmt_reset = $db->prepare("UPDATE bloqueos_ip SET intentos = 0, bloqueado_hasta = NULL WHERE ip_address = ?");
            $stmt_reset->bind_param("s", $ip);
            $stmt_reset->execute();
            $stmt_reset->close();
            $login_attempts = 0;
        }
    }
}
$stmt->close();

if ($is_blocked) {
    $blocked_time = $blocked_until_ts - time();
    if ($blocked_time > 3600) {
        $error = "Demasiados intentos fallidos. Tu IP está bloqueada. Por favor, espera " . ceil($blocked_time/3600) . " horas.";
    } else {
        $error = "Demasiados intentos fallidos. Tu IP está bloqueada. Por favor, espera " . ceil($blocked_time/60) . " minutos.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    // 1. Validar honeypot (campo trampa para bots)
    if (!empty($_POST['website'])) {
        $error = 'Solicitud inválida detectada.';
        error_log("Honeypot activado en login - IP: " . $_SERVER['REMOTE_ADDR']);
        $resultado = registrarIntentoFallido($db, $ip);
        $login_attempts = $resultado['intentos'];
        if ($login_attempts >= 5) {
            $error .= ' Tu IP ha sido temporalmente bloqueada por seguridad.';
        }
    }
    
    // 2. Validar reCAPTCHA si no hay error previo
    if (empty($error) && (!isset($_POST['recaptcha_token']) || empty($_POST['recaptcha_token']))) {
        $error = 'Error de verificación de seguridad. Recarga la página.';
        $resultado = registrarIntentoFallido($db, $ip);
        $login_attempts = $resultado['intentos'];
        if ($login_attempts >= 5) {
            $error .= ' Tu IP ha sido temporalmente bloqueada por seguridad.';
        }
    }
    
    if (empty($error)) {
        $recaptchaResult = validateRecaptcha(RECAPTCHA_SECRET_KEY, $_POST['recaptcha_token']);
        
        if (!isRecaptchaValid($recaptchaResult)) {
            $error = 'No se pudo verificar que no seas un robot. Por favor, intenta nuevamente.';
            $resultado = registrarIntentoFallido($db, $ip);
            $login_attempts = $resultado['intentos'];
            
            // Bloquear después de 5 intentos fallidos
            if ($login_attempts >= 5) {
                $error .= ' Tu IP ha sido bloqueada por 24 horas por seguridad.';
            }
        }
    }
    
    // 3. Validar credenciales si reCAPTCHA es válido
    if (empty($error)) {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            $error = 'Por favor, ingresa email y contraseña';
            $resultado = registrarIntentoFallido($db, $ip);
            $login_attempts = $resultado['intentos'];
            if ($login_attempts >= 5) {
                $error .= ' Tu IP ha sido bloqueada por 24 horas por seguridad.';
            }
        } else {
            // Buscar usuario
            $stmt = $db->prepare("SELECT id, nombre, apellido, password, tipo_usuario, estado FROM usuarios WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                // Verificar contraseña
                if (password_verify($password . PEPPER, $user['password'])) {
                    if ($user['estado'] === 'activo') {
                        // Resetear contador de intentos en éxito (eliminar de la BD)
                        $stmt_reset = $db->prepare("DELETE FROM bloqueos_ip WHERE ip_address = ?");
                        $stmt_reset->bind_param("s", $ip);
                        $stmt_reset->execute();
                        $stmt_reset->close();
                        
                        $login_attempts = 0;
                        $_SESSION['login_attempts'] = 0;
                        $_SESSION['login_blocked_until'] = 0;
                        
                        // Actualizar último acceso
                        $current_time = date('Y-m-d H:i:s');
                        $stmt = $db->prepare("UPDATE usuarios SET ultimo_acceso = ? WHERE id = ?");
                        $stmt->bind_param("si", $current_time, $user['id']);
                        $stmt->execute();
                        $stmt->close();
                        
                        // Iniciar sesión
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['nombre'] . ' ' . $user['apellido'];
                        $_SESSION['user_email'] = $email;
                        $_SESSION['user_type'] = $user['tipo_usuario'];
                        $_SESSION['logged_in'] = true;
                        
                        // Registrar login exitoso
                        error_log("Login exitoso: $email - IP: " . $_SERVER['REMOTE_ADDR']);
                        
                        // Redireccionar según tipo de usuario
                        if (in_array($user['tipo_usuario'], ['admin', 'organizador'])) {
                            header('Location: ../admin/dashboard.php');
                        } else {
                            header('Location: ../public/home.php');
                        }
                        exit();
                    } else {
                        $error = 'Tu cuenta está inactiva. Contacta al administrador.';
                        $resultado = registrarIntentoFallido($db, $ip);
                        $login_attempts = $resultado['intentos'];
                        if ($login_attempts >= 5) {
                            $error .= ' Tu IP ha sido bloqueada por 24 horas por seguridad.';
                        }
                    }
                } else {
                    $error = 'Credenciales incorrectas';
                    $resultado = registrarIntentoFallido($db, $ip);
                    $login_attempts = $resultado['intentos'];
                    
                    // Bloquear después de 5 intentos fallidos
                    if ($login_attempts >= 5) {
                        $error .= ' Tu IP ha sido bloqueada por 24 horas por seguridad.';
                        
                        // Registrar intentos sospechosos
                        error_log("Bloqueo de IP por múltiples intentos fallidos: " . $_SERVER['REMOTE_ADDR'] . " - Email: $email");
                    }
                }
            } else {
                // No revelar si el usuario existe o no (security through obscurity)
                $error = 'Credenciales incorrectas';
                $resultado = registrarIntentoFallido($db, $ip);
                $login_attempts = $resultado['intentos'];
                if ($login_attempts >= 5) {
                    $error .= ' Tu IP ha sido bloqueada por 24 horas por seguridad.';
                }
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
                <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
            </h2>
            <p class="auth-subtitle">Bienvenido de nuevo a Fit5K</p>
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
        
        <!-- Mostrar advertencia si hay intentos fallidos -->
        <?php if ($login_attempts >= 3): ?>
        <div class="auth-alert auth-alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <span>
                <strong>Advertencia:</strong> Has tenido <?php echo $login_attempts; ?> intentos fallidos. 
                Después de 5 intentos, tu IP será bloqueada temporalmente por 24 horas.
            </span>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="loginForm">
            <!-- Campo honeypot para bots -->
            <div style="position: absolute; left: -9999px; opacity: 0;">
                <input type="text" name="website" id="website" tabindex="-1" autocomplete="off" placeholder="No llenar este campo">
            </div>
            
            <!-- Token reCAPTCHA oculto -->
            <input type="hidden" name="recaptcha_token" id="recaptcha_token">
            
            <div class="auth-form-group">
                <label for="email" class="auth-label">
                    <i class="fas fa-envelope"></i> Email *
                </label>
                <input type="email" id="email" name="email" class="auth-input" required
                       placeholder="ejemplo@correo.com"
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                       autocomplete="email"
                       <?php echo ($login_attempts >= 5) ? 'disabled' : ''; ?>>
                <?php if ($login_attempts >= 5): ?>
                <small class="auth-helper-text text-danger">
                    <i class="fas fa-lock"></i> Campo bloqueado temporalmente
                </small>
                <?php endif; ?>
            </div>
            
            <div class="auth-form-group">
                <label for="password" class="auth-label">
                    <i class="fas fa-lock"></i> Contraseña *
                </label>
                <div style="position: relative;">
                    <input type="password" id="password" name="password" class="auth-input" required
                           placeholder="Ingresa tu contraseña"
                           autocomplete="current-password"
                           style="padding-right: 40px;"
                           <?php echo ($login_attempts >= 5) ? 'disabled' : ''; ?>>
                    <span id="togglePassword" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #666; z-index: 10;" title="Ver contraseña">
                        <i class="fas fa-eye"></i>
                    </span>
                </div>
                <div class="auth-forgot-password">
                    <a href="forgot-password.php">
                        <i class="fas fa-key"></i> ¿Olvidaste tu contraseña?
                    </a>
                </div>
            </div>
            
            <!-- Información de seguridad -->
            <div class="auth-disclaimer">
                <div class="auth-disclaimer-header">
                    <i class="fas fa-shield-alt" style="color: #4CAF50;"></i>
                    <span>Protegido por reCAPTCHA</span>
                </div>
                <p style="margin: 0;">
                    Este sitio está protegido por reCAPTCHA y se aplican la 
                    <a href="https://policies.google.com/privacy" target="_blank" style="color: var(--primary-color);">Política de Privacidad</a> y 
                    <a href="https://policies.google.com/terms" target="_blank" style="color: var(--primary-color);">Términos de Servicio</a> de Google.
                </p>
            </div>
            
            <div class="auth-btn-container">
                <button type="submit" class="btn btn-primary auth-btn" id="submitBtn"
                        <?php echo ($login_attempts >= 5) ? 'disabled' : ''; ?>>
                    <i class="fas fa-sign-in-alt"></i> 
                    <span id="submitText">Iniciar Sesión</span>
                    <span id="loadingSpinner" style="display: none;">
                        <i class="fas fa-spinner fa-spin"></i> Verificando...
                    </span>
                </button>
                <?php if ($login_attempts >= 5): ?>
                <small class="auth-helper-text text-danger text-center">
                    <i class="fas fa-ban"></i> Acceso bloqueado temporalmente por seguridad
                </small>
                <?php endif; ?>
            </div>
        </form>
        
        <div class="auth-footer">
            <p>¿No tienes una cuenta? <a href="register.php" style="font-weight: 500;">Regístrate aquí</a></p>
        </div>
    </div>
</div>

<!-- Script reCAPTCHA v3 -->
<script src="https://www.google.com/recaptcha/api.js?render=<?php echo RECAPTCHA_SITE_KEY; ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('loginForm');
    const submitBtn = document.getElementById('submitBtn');
    const submitText = document.getElementById('submitText');
    const loadingSpinner = document.getElementById('loadingSpinner');
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Verificar si está bloqueado
        if (submitBtn.disabled) {
            return;
        }
        
        // Deshabilitar botón y mostrar loading
        submitBtn.disabled = true;
        submitText.style.display = 'none';
        loadingSpinner.style.display = 'inline';
        
        // Obtener token reCAPTCHA
        grecaptcha.ready(function() {
            grecaptcha.execute('<?php echo RECAPTCHA_SITE_KEY; ?>', {
                action: 'login'
            }).then(function(token) {
                // Colocar token en el campo oculto
                document.getElementById('recaptcha_token').value = token;
                
                // Validar campos requeridos
                const email = document.getElementById('email').value;
                const password = document.getElementById('password').value;
                
                if (!email || !password) {
                    alert('Por favor, completa todos los campos requeridos.');
                    submitBtn.disabled = false;
                    submitText.style.display = 'inline';
                    loadingSpinner.style.display = 'none';
                    return;
                }
                
                // Enviar formulario
                form.submit();
                
            }).catch(function(error) {
                console.error('Error reCAPTCHA:', error);
                alert('Error de verificación de seguridad. Por favor, recarga la página e intenta nuevamente.');
                
                // Rehabilitar botón
                submitBtn.disabled = false;
                submitText.style.display = 'inline';
                loadingSpinner.style.display = 'none';
            });
        });
    });
    
    // Toggle password visibility
    const togglePassword = document.getElementById('togglePassword');
    if (togglePassword) {
        togglePassword.addEventListener('click', function () {
            const passwordInput = document.getElementById('password');
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            const icon = this.querySelector('i');
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
        });
    }

    // Prevenir copiar/pegar en campos de contraseña (opcional)
    document.getElementById('password').addEventListener('paste', function(e) {
        e.preventDefault();
        return false;
    });
    
    // Prevenir autocompletado malicioso
    document.getElementById('email').addEventListener('input', function() {
        this.value = this.value.toLowerCase();
    });
});
</script>

<?php include '../../includes/footer.php'; ?>