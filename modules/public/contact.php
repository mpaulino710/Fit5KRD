<?php
// modules/public/contact.php
require_once '../../config/config.php';
require_once '../../config/database.php';

$db = getDB();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $email = trim($_POST['email']);
    $telefono = trim($_POST['telefono']);
    $asunto = trim($_POST['asunto']);
    $mensaje = trim($_POST['mensaje']);
    
    // Validaciones
    if (empty($nombre) || empty($email) || empty($asunto) || empty($mensaje)) {
        $error = 'Por favor, completa todos los campos obligatorios';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Por favor, ingresa un email válido';
    } else {
        // En producción, aquí enviarías el email
        // Por ahora, solo guardamos en base de datos
        $stmt = $db->prepare("INSERT INTO contactos (nombre, email, telefono, asunto, mensaje, fecha) 
                             VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("sssss", $nombre, $email, $telefono, $asunto, $mensaje);
        
        if ($stmt->execute()) {
            $success = '¡Mensaje enviado correctamente! Nos pondremos en contacto contigo pronto.';
            
            // Limpiar formulario
            $_POST = [];
        } else {
            $error = 'Error al enviar el mensaje. Por favor, intenta nuevamente.';
        }
        $stmt->close();

    }
}
?>

<?php include '../../includes/header.php'; ?>
<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />


<div class="container">
    <!-- Hero Section -->
    <div style="
        background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        color: white;
        padding: 4rem 2rem;
        border-radius: var(--border-radius);
        text-align: center;
        margin-bottom: 3rem;
    ">
        <h1 style="font-size: 3rem; margin-bottom: 1rem;">
            <i class="fas fa-envelope"></i> Contacto
        </h1>
        <p style="font-size: 1.2rem; max-width: 800px; margin: 0 auto;">
            ¿Tienes preguntas, sugerencias o necesitas ayuda? Estamos aquí para ayudarte.
        </p>
    </div>
    
    <div class="contact-main-grid">
        <!-- Formulario de Contacto -->
        <div>
            <div style="
                background: white;
                border-radius: var(--border-radius);
                padding: 2.5rem;
                box-shadow: var(--box-shadow);
            ">
                <h2 style="color: var(--primary-color); margin-bottom: 1.5rem;">
                    <i class="fas fa-paper-plane"></i> Envíanos un Mensaje
                </h2>
                
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
                
                <form method="POST" action="">
                    <div class="form-group" style="margin-bottom: 1.5rem;">
                        <label for="nombre">
                            <i class="fas fa-user"></i> Nombre Completo *
                        </label>
                        <input type="text" id="nombre" name="nombre" class="form-control" required
                               placeholder="Tu nombre"
                               value="<?php echo isset($_POST['nombre']) ? htmlspecialchars($_POST['nombre']) : ''; ?>">
                    </div>
                    
                    <div class="contact-form-row">
                        <div class="form-group">
                            <label for="email">
                                <i class="fas fa-envelope"></i> Email *
                            </label>
                            <input type="email" id="email" name="email" class="form-control" required
                                   placeholder="ejemplo@correo.com"
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="telefono">
                                <i class="fas fa-phone"></i> Teléfono
                            </label>
                            <input type="tel" id="telefono" name="telefono" class="form-control"
                                   placeholder="+1 (123) 456-7890"
                                   value="<?php echo isset($_POST['telefono']) ? htmlspecialchars($_POST['telefono']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 1.5rem;">
                        <label for="asunto">
                            <i class="fas fa-tag"></i> Asunto *
                        </label>
                        <select id="asunto" name="asunto" class="form-control" required>
                            <option value="">Selecciona un asunto</option>
                            <option value="informacion" <?php echo (isset($_POST['asunto']) && $_POST['asunto'] === 'informacion') ? 'selected' : ''; ?>>Información general</option>
                            <option value="inscripcion" <?php echo (isset($_POST['asunto']) && $_POST['asunto'] === 'inscripcion') ? 'selected' : ''; ?>>Problemas con inscripción</option>
                            <option value="evento" <?php echo (isset($_POST['asunto']) && $_POST['asunto'] === 'evento') ? 'selected' : ''; ?>>Consulta sobre evento</option>
                            <option value="tecnico" <?php echo (isset($_POST['asunto']) && $_POST['asunto'] === 'tecnico') ? 'selected' : ''; ?>>Problema técnico</option>
                            <option value="sugerencia" <?php echo (isset($_POST['asunto']) && $_POST['asunto'] === 'sugerencia') ? 'selected' : ''; ?>>Sugerencia</option>
                            <option value="organizador" <?php echo (isset($_POST['asunto']) && $_POST['asunto'] === 'organizador') ? 'selected' : ''; ?>>Ser organizador</option>
                            <option value="otro" <?php echo (isset($_POST['asunto']) && $_POST['asunto'] === 'otro') ? 'selected' : ''; ?>>Otro</option>
                        </select>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 1.5rem;">
                        <label for="mensaje">
                            <i class="fas fa-comment"></i> Mensaje *
                        </label>
                        <textarea id="mensaje" name="mensaje" class="form-control" rows="6" required
                                  placeholder="Escribe tu mensaje aquí..."><?php echo isset($_POST['mensaje']) ? htmlspecialchars($_POST['mensaje']) : ''; ?></textarea>
                        <div style="display: flex; justify-content: space-between; margin-top: 0.5rem;">
                            <small style="color: var(--text-light);">
                                * Campos obligatorios
                            </small>
                            <small style="color: var(--text-light);" id="charCount">
                                0/1000 caracteres
                            </small>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 1rem;">
                        <i class="fas fa-paper-plane"></i> Enviar Mensaje
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Información de Contacto -->
        <div>
            <div style="margin-bottom: 2rem;">
                
                
                <div style="
                    background: white;
                    border-radius: var(--border-radius);
                    padding: 2rem;
                    box-shadow: var(--box-shadow);
                    margin-bottom: 1.5rem;
                ">
                <h2 style="color: var(--primary-color); margin-bottom: 1.5rem;">
                    <i class="fas fa-info-circle"></i> Información de Contacto
                </h2>
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem;">
                        <div style="
                            background: var(--primary-light);
                            color: white;
                            width: 50px;
                            height: 50px;
                            border-radius: 50%;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            font-size: 1.2rem;
                        ">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div>
                            <h3 style="color: var(--text-dark); margin-bottom: 0.3rem;">Dirección</h3>
                            <p style="color: var(--text-light); margin: 0;">
                                Santiago de los Caballeros<br>
                                República Dominicana
                            </p>
                        </div>
                    </div>
                    
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem;">
                        <div style="
                            background: var(--primary-light);
                            color: white;
                            width: 50px;
                            height: 50px;
                            border-radius: 50%;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            font-size: 1.2rem;
                        ">
                            <i class="fas fa-phone"></i>
                        </div>
                        <div>
                            <h3 style="color: var(--text-dark); margin-bottom: 0.3rem;">Teléfono</h3>
                            <p style="color: var(--text-light); margin: 0;">
                                +1 (829) 923-0124<br>
                                Lunes a Viernes, 9:00 - 18:00
                            </p>
                        </div>
                    </div>
                    
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div style="
                            background: var(--primary-light);
                            color: white;
                            width: 50px;
                            height: 50px;
                            border-radius: 50%;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            font-size: 1.2rem;
                        ">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div>
                            <h3 style="color: var(--text-dark); margin-bottom: 0.3rem;">Email</h3>
                            <p style="color: var(--text-light); margin: 0;">
                                fit5krd@pasohas.com<br>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Preguntas Frecuentes -->
            <div>
                
                
                <div style="
                    background: white;
                    border-radius: var(--border-radius);
                    padding: 2rem;
                    box-shadow: var(--box-shadow);
                ">
                <h2 style="color: var(--primary-color); margin-bottom: 1.5rem;">
                    <i class="fas fa-question-circle"></i> Preguntas Frecuentes
                </h2>
                    <div class="faq-item" style="margin-bottom: 1rem; border-bottom: 1px solid #eee; padding-bottom: 1rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; cursor: pointer;">
                            <h3 style="color: var(--text-dark); margin: 0; font-size: 1rem;">
                                ¿Cómo me registro en un evento?
                            </h3>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer" style="display: none; margin-top: 0.5rem;">
                            <p style="color: var(--text-light); font-size: 0.9rem; margin: 0;">
                                Solo necesitas crear una cuenta, buscar el evento que te interese 
                                y hacer clic en "Inscribirse". El proceso es completamente online.
                            </p>
                        </div>
                    </div>
                    
                    <div class="faq-item" style="margin-bottom: 1rem; border-bottom: 1px solid #eee; padding-bottom: 1rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; cursor: pointer;">
                            <h3 style="color: var(--text-dark); margin: 0; font-size: 1rem;">
                                ¿Puedo cancelar mi inscripción?
                            </h3>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer" style="display: none; margin-top: 0.5rem;">
                            <p style="color: var(--text-light); font-size: 0.9rem; margin: 0;">
                                Sí, puedes cancelar tu inscripción previo al pago del evento desde la sección "Mis
                                Inscripciones" en tu perfil. Si ya has realizado el pago y no puedes asistir no contamos con politica de
                                devolución , sin embargo puedes pasar tu cupo a otra persona para lo cual debes de contactar al
                                administrador del evento para hacer los ajustes.
                            </p>
                        </div>
                    </div>
                    
                    <div class="faq-item" style="margin-bottom: 1rem; border-bottom: 1px solid #eee; padding-bottom: 1rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; cursor: pointer;">
                            <h3 style="color: var(--text-dark); margin: 0; font-size: 1rem;">
                                ¿Cómo organizo un evento en Fit5K?
                            </h3>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer" style="display: none; margin-top: 0.5rem;">
                            <p style="color: var(--text-light); font-size: 0.9rem; margin: 0;">
                                Contacta con nuestro equipo a través del formulario seleccionando 
                                "Ser organizador" en el asunto. Te guiaremos en todo el proceso.
                            </p>
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div style="display: flex; justify-content: space-between; align-items: center; cursor: pointer;">
                            <h3 style="color: var(--text-dark); margin: 0; font-size: 1rem;">
                                ¿Qué métodos de pago aceptan?
                            </h3>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer" style="display: none; margin-top: 0.5rem;">
                            <p style="color: var(--text-light); font-size: 0.9rem; margin: 0;">
                                Aceptamos transferencias bancarias, depósitos a
                                cuenta y en efectivo. Todos los métodos de pago previo a la recepción de kit de participación o del evento
                                según aplique. Para sitauciones extraordinarias contactar al administrador del evento.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Mapa -->
    <div style="
        background: white;
        border-radius: var(--border-radius);
        padding: 2rem;
        box-shadow: var(--box-shadow);
        margin-bottom: 3rem;
    ">
        <h2 style="color: var(--primary-color); margin-bottom: 1.5rem;">
            <i class="fas fa-map-marked-alt"></i> Nuestra Ubicación
        </h2>
        <div id="map" style="
            height: 400px;
            width: 100%;
            border-radius: var(--border-radius);
            box-shadow: inset 0 0 10px rgba(0,0,0,0.1);
            z-index: 1;
        "></div>
    </div>
    
    <!-- Redes Sociales -->
    <div style="text-align: center;">
        <h2 style="color: var(--primary-color); margin-bottom: 1.5rem;">
            <i class="fas fa-hashtag"></i> Síguenos en Redes Sociales
        </h2>
        <p style="color: var(--text-light); max-width: 600px; margin: 0 auto 2rem;">
            Mantente al día con las últimas noticias, eventos y consejos de running
        </p>
        <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
            <!--
            <a href="#" style="
                display: inline-flex;
                align-items: center;
                gap: 10px;
                background: #3b5998;
                color: white;
                padding: 1rem 2rem;
                border-radius: var(--border-radius);
                text-decoration: none;
                font-weight: 500;
            ">
                <i class="fab fa-facebook-f"></i> Facebook
            </a>
            <a href="#" style="
                display: inline-flex;
                align-items: center;
                gap: 10px;
                background: #1da1f2;
                color: white;
                padding: 1rem 2rem;
                border-radius: var(--border-radius);
                text-decoration: none;
                font-weight: 500;
            ">
                <i class="fab fa-twitter"></i> Twitter
            </a>
            -->
            <a href="https://www.instagram.com/fit5krd?igsh=MWhtOXJqZzV5cXdqaw==" style="
                display: inline-flex;
                align-items: center;
                gap: 10px;
                background: #e4405f;
                color: white;
                padding: 1rem 2rem;
                border-radius: var(--border-radius);
                text-decoration: none;
                font-weight: 500;
            ">
                <i class="fab fa-instagram"></i> Instagram
            </a>
            <a href="https://www.youtube.com/@fit5krd" style="
                display: inline-flex;
                align-items: center;
                gap: 10px;
                background: #ff0000;
                color: white;
                padding: 1rem 2rem;
                border-radius: var(--border-radius);
                text-decoration: none;
                font-weight: 500;
            ">
                <i class="fab fa-youtube"></i> YouTube
            </a>
        </div>
    </div>
</div>

<script>
// Contador de caracteres para el mensaje
const mensajeInput = document.getElementById('mensaje');
const charCount = document.getElementById('charCount');

if (mensajeInput && charCount) {
    mensajeInput.addEventListener('input', function() {
        const length = this.value.length;
        charCount.textContent = `${length}/1000 caracteres`;
        
        if (length > 1000) {
            charCount.style.color = 'var(--danger)';
        } else if (length > 800) {
            charCount.style.color = 'var(--warning)';
        } else {
            charCount.style.color = 'var(--text-light)';
        }
    });
    
    // Inicializar contador
    charCount.textContent = `${mensajeInput.value.length}/1000 caracteres`;
}

// FAQ Accordion
document.querySelectorAll('.faq-item').forEach(item => {
    const question = item.querySelector('.faq-item > div');
    const answer = item.querySelector('.faq-answer');
    const icon = item.querySelector('i');
    
    question.addEventListener('click', () => {
        // Cerrar otros FAQ abiertos
        document.querySelectorAll('.faq-item').forEach(otherItem => {
            if (otherItem !== item) {
                const otherAnswer = otherItem.querySelector('.faq-answer');
                const otherIcon = otherItem.querySelector('i');
                otherAnswer.style.display = 'none';
                otherIcon.className = 'fas fa-chevron-down';
            }
        });
        
        // Alternar el actual
        const isVisible = answer.style.display === 'block';
        answer.style.display = isVisible ? 'none' : 'block';
        icon.className = isVisible ? 'fas fa-chevron-down' : 'fas fa-chevron-up';
    });
});

// Validación del formulario
const form = document.querySelector('form');
if (form) {
    form.addEventListener('submit', function(e) {
        const mensaje = document.getElementById('mensaje');
        if (mensaje.value.length > 1000) {
            e.preventDefault();
            alert('El mensaje no puede exceder los 1000 caracteres.');
            mensaje.focus();
        }
    });
}
</script>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Coordenadas de CEDAB (Por defecto)
    const lat = 19.468151936614376;
    const lng = -70.65850180036688;
    
    if (document.getElementById('map')) {
        var map = L.map('map').setView([lat, lng], 13);

        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        }).addTo(map);

        var marker = L.marker([lat, lng]).addTo(map);
        marker.bindPopup("<b>Fit5K</b><br>Nuestra Ubicación.").openPopup();
    }
});
</script>

<?php include '../../includes/footer.php'; ?>