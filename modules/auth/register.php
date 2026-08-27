<?php
// Archivo: modules/auth/register.php
session_start(); // Para control de intentos

if (isset($_SESSION['user_id'])) {
    header('Location: /modules/public/home.php');
    exit();
}

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/recaptcha.php';
require_once '../../includes/SMTPMailer.php';

$db = getDB();
$error = '';
$success = '';

// Configurar intentos de registro por sesión
if (!isset($_SESSION['register_attempts'])) {
    $_SESSION['register_attempts'] = 0;
    $_SESSION['register_blocked_until'] = 0;
}

// Verificar si está bloqueado por muchos intentos
if ($_SESSION['register_blocked_until'] > time()) {
    $blocked_time = $_SESSION['register_blocked_until'] - time();
    $error = "Demasiados intentos fallidos. Por favor, espera " . ceil($blocked_time/60) . " minutos.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    // 1. Validar honeypot (campo trampa para bots)
    if (!empty($_POST['website'])) {
        $error = 'Solicitud inválida detectada.';
        error_log("Honeypot activado - IP: " . $_SERVER['REMOTE_ADDR']);
    }
    
    // 2. Validar reCAPTCHA si no hay error previo
    if (empty($error) && (!isset($_POST['recaptcha_token']) || empty($_POST['recaptcha_token']))) {
        $error = 'Error de verificación de seguridad. Recarga la página.';
    }
    
    if (empty($error)) {
        $recaptchaResult = validateRecaptcha(RECAPTCHA_SECRET_KEY, $_POST['recaptcha_token']);
        
        if (!isRecaptchaValid($recaptchaResult)) {
            $error = 'No se pudo verificar que no seas un robot. Por favor, intenta nuevamente.';
            $_SESSION['register_attempts']++;
            
            // Bloquear después de 5 intentos fallidos
            if ($_SESSION['register_attempts'] >= 5) {
                $_SESSION['register_blocked_until'] = time() + 1800; // 30 minutos
                $error .= ' Tu IP ha sido temporalmente bloqueada por seguridad.';
            }
        }
    }
    
    // 3. Validar campos del formulario
    if (empty($error)) {
        $nombre = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $cedula = trim($_POST['cedula'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? null;
        $genero = $_POST['genero'] ?? '';
        $telefono = trim($_POST['telefono'] ?? '');
        
        // Validaciones básicas
        $errors = [];
        
        if (empty($nombre) || strlen($nombre) < 2) {
            $errors[] = 'El nombre debe tener al menos 2 caracteres.';
        }
        
        if (empty($apellido) || strlen($apellido) < 2) {
            $errors[] = 'El apellido debe tener al menos 2 caracteres.';
        }

        if (empty($cedula)) {
            $errors[] = 'La cédula es obligatoria.';
        }
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'El email no tiene un formato válido.';
        }
        
        if (empty($password) || strlen($password) < 8) {
            $errors[] = 'La contraseña debe tener al menos 8 caracteres.';
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'La contraseña debe contener al menos una mayúscula.';
        } elseif (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'La contraseña debe contener al menos un número.';
        }
        
        if ($password !== $confirm_password) {
            $errors[] = 'Las contraseñas no coinciden.';
        }
        
        // Validar fecha de nacimiento si se proporciona
        if (!empty($fecha_nacimiento)) {
            $fecha_nac = DateTime::createFromFormat('Y-m-d', $fecha_nacimiento);
            $hoy = new DateTime();
            $edad = $hoy->diff($fecha_nac)->y;
            
            if (!$fecha_nac || $fecha_nac > $hoy) {
                $errors[] = 'Fecha de nacimiento inválida.';
            } elseif ($edad < 13) {
                $errors[] = 'Debes tener al menos 13 años para registrarte.';
            }
        }
        
        if (!empty($errors)) {
            $error = implode('<br>', $errors);
        } else {
            // Verificar si el email ya existe
            $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();
            
            if ($stmt->num_rows > 0) {
                $error = 'Este email ya está registrado. ¿Has olvidado tu contraseña?';
            } else {
                // Verificar si la cédula ya existe
                $stmt = $db->prepare("SELECT id FROM usuarios WHERE cedula = ?");
                $stmt->bind_param("s", $cedula);
                $stmt->execute();
                $stmt->store_result();

                if ($stmt->num_rows > 0) {
                    $error = 'Esta cédula ya está registrada.';
                } else {
                    // Hash de la contraseña

                $hashed_password = password_hash($password . PEPPER, PASSWORD_DEFAULT);
                
                // Insertar usuario
                $stmt = $db->prepare("INSERT INTO usuarios (nombre, apellido, cedula, email, password, fecha_nacimiento, genero, telefono, tipo_usuario, estado) 
                                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'corredor', 'activo')");
                
                // Si fecha_nacimiento está vacío, usar NULL
                $fecha_nacimiento = empty($fecha_nacimiento) ? null : $fecha_nacimiento;
                $genero = empty($genero) ? null : $genero;
                $telefono = empty($telefono) ? null : $telefono;
                
                $stmt->bind_param("ssssssss", $nombre, $apellido, $cedula, $email, $hashed_password, $fecha_nacimiento, $genero, $telefono);
                
                if ($stmt->execute()) {
                    $user_id = $stmt->insert_id;

                    // Resetear contador de intentos en éxito
                    $_SESSION['register_attempts'] = 0;
                    $_SESSION['register_blocked_until'] = 0;
                    
                    // Iniciar sesión automáticamente
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['user_name'] = $nombre . ' ' . $apellido;
                    $_SESSION['user_email'] = $email;
                    $_SESSION['user_type'] = 'corredor';
                    $_SESSION['logged_in'] = true;

                    // Actualizar estado de acceso en DB (Opcional, pero consistente con login)
                    $current_time = date('Y-m-d H:i:s');
                    $update_stmt = $db->prepare("UPDATE usuarios SET ultimo_acceso = ? WHERE id = ?");
                    if ($update_stmt) {
                        $update_stmt->bind_param("si", $current_time, $user_id);
                        $update_stmt->execute();
                        $update_stmt->close();
                    }
                    
                    $success = '¡Registro exitoso! Iniciando sesión...';
                    
                    // Enviar correo de bienvenida
                    $mailer = new SMTPMailer();
                    $subject = "¡Bienvenido a " . SITE_NAME . "!";
                    $message = "<h2>Hola $nombre,</h2>";
                    $message .= "<p>¡Gracias por unirte a " . SITE_NAME . "!</p>";
                    $message .= "<p>Tu cuenta ha sido creada exitosamente. Ahora puedes inscribirte a nuestros eventos y llevar un registro de tus actividades.</p>";
                    $message .= "<p><a href='" . SITE_URL . "/modules/auth/login.php'>Iniciar Sesión</a></p>";
                    $message .= "<p>Saludos,<br>Equipo " . SITE_NAME . "</p>";
                    $message .= "<p>Instagram: @fit5krd</p>";
                    $message .= "<p>Youtube: @fit5krd</p>";
                    if (!$mailer->send($email, $subject, $message)) {
                        error_log("Failed to send welcome email to $email");
                    }

                    // Redirigir después de 2 segundos al panel
                    echo '<meta http-equiv="refresh" content="2;url=/modules/public/home.php">';
                } else {
                    $error = 'Error al registrar el usuario. Por favor, intenta nuevamente.';
                    error_log("Error en registro MySQL: " . $stmt->error);
                }
                }
            }
            $stmt->close();
        }
    }
}

// Limpiar intentos si han pasado más de 1 hora
if ($_SESSION['register_blocked_until'] > 0 && $_SESSION['register_blocked_until'] < time()) {
    $_SESSION['register_attempts'] = 0;
    $_SESSION['register_blocked_until'] = 0;
}

include '../../includes/header.php';
?>

<div class="auth-wrapper">
    <div class="form-container auth-container register-container">
        <div class="auth-header">
            <h2 class="auth-title">
                Regístrate en Fit5K
            </h2>
            <p class="auth-subtitle">Únete a nuestra comunidad de corredores</p>
        </div>
        
        <?php if ($error): ?>
        <div class="auth-alert auth-alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <div>
                <strong>Error:</strong><br><?php echo $error; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="auth-alert auth-alert-success">
            <i class="fas fa-check-circle"></i>
            <div>
                <strong>¡Éxito!</strong><br><?php echo $success; ?>
                <p style="margin-top: 0.5rem; font-size: 0.9em; margin-bottom: 0;">Redirigiendo a tu perfil en 2 segundos...</p>
            </div>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="registerForm">
            <!-- Campo honeypot para bots -->
            <div style="position: absolute; left: -9999px; opacity: 0;">
                <input type="text" name="website" id="website" tabindex="-1" autocomplete="off" placeholder="No llenar este campo">
            </div>
            
            <!-- Token reCAPTCHA oculto -->
            <input type="hidden" name="recaptcha_token" id="recaptcha_token">
            
            <div class="auth-grid">
                <div class="auth-form-group">
                    <label for="nombre" class="auth-label">Nombre *</label>
                    <input type="text" id="nombre" name="nombre" class="auth-input auth-input-simple" required 
                           value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : ''; ?>"
                           minlength="2" maxlength="100">
                    <small class="auth-helper-text">Mínimo 2 caracteres</small>
                </div>
                
                <div class="auth-form-group">
                    <label for="apellido" class="auth-label">Apellido *</label>
                    <input type="text" id="apellido" name="apellido" class="auth-input auth-input-simple" required
                           value="<?php echo isset($_POST['apellido']) ? htmlspecialchars($_POST['apellido']) : ''; ?>"
                           minlength="2" maxlength="100">
                    <small class="auth-helper-text">Mínimo 2 caracteres</small>
                </div>
            </div>
            
            <div class="auth-form-group">
                <label for="cedula" class="auth-label">Cédula *</label>
                <input type="text" id="cedula" name="cedula" class="auth-input auth-input-simple" required
                       value="<?php echo isset($_POST['cedula']) ? htmlspecialchars($_POST['cedula']) : ''; ?>"
                       minlength="5" maxlength="20">
                <small class="auth-helper-text">Identificación personal (sin guiones)</small>
            </div>
            
            <div class="auth-form-group">
                <label for="email" class="auth-label">Email *</label>
                <input type="email" id="email" name="email" class="auth-input auth-input-simple" required
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                       maxlength="255">
                <small class="auth-helper-text">Usaremos este email para comunicarnos contigo</small>
            </div>
            
            <div class="auth-grid">
                <div class="auth-form-group">
                    <label for="password" class="auth-label">Contraseña *</label>
                    <div style="position: relative;">
                        <input type="password" id="password" name="password" class="auth-input auth-input-simple" required 
                               minlength="8" pattern="^(?=.*[A-Z])(?=.*\d).+$" title="Mínimo 8 caracteres, una mayúscula y un número" style="padding-right: 40px; width: 100%; box-sizing: border-box;">
                        <span class="toggle-password" data-target="password" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #666; z-index: 10;" title="Ver contraseña">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                    <small class="auth-helper-text">Mínimo 8 caracteres, una mayúscula y un número</small>
                </div>
                
                <div class="auth-form-group">
                    <label for="confirm_password" class="auth-label">Confirmar Contraseña *</label>
                    <div style="position: relative;">
                        <input type="password" id="confirm_password" name="confirm_password" class="auth-input auth-input-simple" required minlength="8" style="padding-right: 40px; width: 100%; box-sizing: border-box;">
                        <span class="toggle-password" data-target="confirm_password" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #666; z-index: 10;" title="Ver contraseña">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                    <small class="auth-helper-text">Repite tu contraseña</small>
                </div>
            </div>
            
            <div class="auth-form-group">
                <label for="fecha_nacimiento" class="auth-label">Fecha de Nacimiento</label>
                <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" class="auth-input auth-input-simple"
                       value="<?php echo isset($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : ''; ?>"
                       >
                <small class="auth-helper-text">Debes tener al menos 13 años</small>
            </div>
            
            <div class="auth-grid">
                <div class="auth-form-group">
                    <label for="genero" class="auth-label">Género</label>
                    <select id="genero" name="genero" class="auth-input auth-input-simple" style="padding-top: 0.8rem; padding-bottom: 0.8rem;">
                        <option value="">Seleccionar</option>
                        <option value="M" <?php echo (isset($_POST['genero']) && $_POST['genero'] == 'M') ? 'selected' : ''; ?>>Masculino</option>
                        <option value="F" <?php echo (isset($_POST['genero']) && $_POST['genero'] == 'F') ? 'selected' : ''; ?>>Femenino</option>
                        <option value="O" <?php echo (isset($_POST['genero']) && $_POST['genero'] == 'O') ? 'selected' : ''; ?>>Otro</option>
                    </select>
                </div>
                
                <div class="auth-form-group">
                    <label for="telefono" class="auth-label">Teléfono</label>
                    <input type="tel" id="telefono" name="telefono" class="auth-input auth-input-simple"
                           value="<?php echo isset($_POST['telefono']) ? htmlspecialchars($_POST['telefono']) : ''; ?>"
                           pattern="[0-9+\-\s()]{7,20}" title="Formato de teléfono válido">
                    <small class="auth-helper-text">Opcional - Ej: +1 809 123 4567</small>
                </div>
            </div>
            
            <!-- Información de seguridad -->
            <div class="auth-disclaimer">
                <div class="auth-disclaimer-header">
                    <span style="color: #4CAF50;">✓</span>
                    <span>Protegido por reCAPTCHA</span>
                </div>
                <p style="margin: 0;">
                    Este sitio está protegido por reCAPTCHA y se aplican la 
                    <a href="https://policies.google.com/privacy" target="_blank" style="color: var(--primary-color);">Política de Privacidad</a> y 
                    <a href="https://policies.google.com/terms" target="_blank" style="color: var(--primary-color);">Términos de Servicio</a> de Google.
                </p>
            </div>
            
            <div class="auth-btn-container">
                <button type="submit" class="btn btn-primary auth-btn" id="submitBtn">
                    <span id="submitText">Registrarse</span>
                    <span id="loadingSpinner" style="display: none;">Procesando...</span>
                </button>
            </div>
        </form>
        
        <div class="auth-footer">
            <p>¿Ya tienes una cuenta? <a href="login.php" style="font-weight: 500;">Inicia sesión aquí</a></p>
        </div>
    </div>
</div>

<!-- Flatpickr CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
    /* Ajustes para Flatpickr en móvil */
    .flatpickr-calendar {
        max-width: 90%;
        margin: 0 auto;
    }
    .flatpickr-current-month .flatpickr-monthDropdown-months {
        font-weight: 500;
    }
    /* Estilo para que parezca input nativo */
    .auth-input-simple[readonly] {
        background-color: white;
        cursor: pointer;
    }
</style>

<!-- Script reCAPTCHA v3 -->
<script src="https://www.google.com/recaptcha/api.js?render=<?php echo RECAPTCHA_SITE_KEY; ?>"></script>
<!-- Flatpickr JS -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/es.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar Flatpickr
    flatpickr("#fecha_nacimiento", {
        locale: "es",
        dateFormat: "Y-m-d",
        disableMobile: true, // Forzar calendario custom en móviles
        maxDate: new Date().fp_incr(-365 * 13), // 13 años atrás
        defaultDate: null,
        yearSelectorType: 'dropdown', // Dropdown para años (clave para lo que pide el user)
        changeMonth: true,
        changeYear: true,
        onReady: function(d, i, fp) {
            // Asegurar que el input sea readonly para evitar teclado nativo
            fp.input.setAttribute('readonly', 'readonly');
        }
    });

    const form = document.getElementById('registerForm');
    const submitBtn = document.getElementById('submitBtn');
    const submitText = document.getElementById('submitText');
    const loadingSpinner = document.getElementById('loadingSpinner');
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Deshabilitar botón y mostrar loading
        submitBtn.disabled = true;
        submitText.style.display = 'none';
        loadingSpinner.style.display = 'inline';
        
        // Obtener token reCAPTCHA
        grecaptcha.ready(function() {
            grecaptcha.execute('<?php echo RECAPTCHA_SITE_KEY; ?>', {
                action: 'register'
            }).then(function(token) {
                // Colocar token en el campo oculto
                document.getElementById('recaptcha_token').value = token;
                
                // Validar contraseñas antes de enviar
                const password = document.getElementById('password').value;
                const confirmPassword = document.getElementById('confirm_password').value;
                
                if (password !== confirmPassword) {
                    alert('Las contraseñas no coinciden. Por favor, verifica.');
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
    
    // Validación en tiempo real de contraseñas
    const passwordField = document.getElementById('password');
    const confirmField = document.getElementById('confirm_password');
    
    function validatePasswords() {
        if (passwordField.value && confirmField.value) {
            if (passwordField.value !== confirmField.value) {
                confirmField.setCustomValidity('Las contraseñas no coinciden');
            } else {
                confirmField.setCustomValidity('');
            }
        }
    }
    
    passwordField.addEventListener('input', validatePasswords);
    confirmField.addEventListener('input', validatePasswords);
    
    // Validación de edad (se mantiene como respaldo aunque Flatpickr limite)
    const birthDateField = document.getElementById('fecha_nacimiento');
    
    function validateAge() {
        if (!birthDateField.value) {
            birthDateField.setCustomValidity('');
            return;
        }
        
        const parts = birthDateField.value.split('-');
        const birthDate = new Date(parts[0], parts[1] - 1, parts[2]);
        const today = new Date();
        
        let age = today.getFullYear() - birthDate.getFullYear();
        const m = today.getMonth() - birthDate.getMonth();
        
        if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
            age--;
        }
        
        if (age < 13) {
            birthDateField.setCustomValidity('Debes tener al menos 13 años para registrarte.');
        } else {
            birthDateField.setCustomValidity('');
        }
    }
    
    birthDateField.addEventListener('change', validateAge);

    // Toggle password visibility
    document.querySelectorAll('.toggle-password').forEach(function(toggle) {
        toggle.addEventListener('click', function () {
            const targetId = this.getAttribute('data-target');
            const passwordInput = document.getElementById(targetId);
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            const icon = this.querySelector('i');
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
        });
    });
});
</script>

<?php include '../../includes/footer.php'; ?>