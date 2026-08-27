<?php
// modules/admin/send-bulk-email.php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/SMTPMailer.php';

// Control de acceso para administradores y organizadores
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'organizador'])) {
    if (isset($_GET['action'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'No autorizado']);
        exit;
    }
    header('Location: ../auth/login.php');
    exit;
}

$db = getDB();

// --- AJAX Endpoint: get_recipients ---
if (isset($_GET['action']) && $_GET['action'] === 'get_recipients') {
    header('Content-Type: application/json');
    $type = $_GET['type'] ?? 'all';
    
    $query = "SELECT email, nombre, apellido FROM usuarios WHERE estado = 'activo'";
    if ($type === 'runners') {
        $query .= " AND tipo_usuario = 'corredor'";
    } elseif ($type === 'admins') {
        $query .= " AND (tipo_usuario = 'admin' OR tipo_usuario = 'organizador')";
    }
    
    $result = $db->query($query);
    if (!$result) {
        echo json_encode(['success' => false, 'error' => $db->error]);
        exit;
    }
    
    $recipients = $result->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['success' => true, 'recipients' => $recipients]);
    exit;
}

// --- AJAX Endpoint: send_single ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'send_single') {
    header('Content-Type: application/json');
    
    $email = trim($_POST['email'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $event_id = intval($_POST['event_id'] ?? 0);
    
    if (empty($email) || empty($subject) || empty($message)) {
        echo json_encode(['success' => false, 'error' => 'Parámetros incompletos.']);
        exit;
    }
    
    // Inyectar tarjeta promocional de evento
    $event_html = '';
    if ($event_id > 0) {
        $evt_stmt = $db->prepare("SELECT nombre, descripcion, fecha_evento, ubicacion, precio, imagen_url FROM eventos WHERE id = ?");
        $evt_stmt->bind_param("i", $event_id);
        $evt_stmt->execute();
        $evt_res = $evt_stmt->get_result();
        if ($evt_res->num_rows > 0) {
            $evt = $evt_res->fetch_assoc();
            
            $evt_name = htmlspecialchars($evt['nombre']);
            $evt_desc = htmlspecialchars($evt['descripcion'] ?? '');
            if (strlen($evt_desc) > 200) {
                $evt_desc = substr($evt_desc, 0, 197) . '...';
            }
            $evt_date = date('d/m/Y h:i A', strtotime($evt['fecha_evento']));
            $evt_loc = htmlspecialchars($evt['ubicacion']);
            $evt_price = floatval($evt['precio']) > 0 ? '$' . number_format($evt['precio'], 2) : 'Gratuito';
            
            $evt_img_html = '';
            if ($evt['imagen_url'] && $evt['imagen_url'] !== '0') {
                $evt_img_url = SITE_URL . '/' . $evt['imagen_url'];
                $evt_img_html = '<img src="' . $evt_img_url . '" alt="' . $evt_name . '" style="width: 100%; max-height: 200px; object-fit: cover;" />';
            }
            
            $evt_link = SITE_URL . '/modules/events/view.php?id=' . $event_id;
            
            $event_html = '
            <div style="margin-top: 30px; border: 1px solid #4b0082; border-radius: 8px; overflow: hidden; background-color: #fcfaff; font-family: Arial, sans-serif;">
                ' . $evt_img_html . '
                <div style="padding: 20px;">
                    <span style="background-color: #4b0082; color: white; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px;">Evento Destacado</span>
                    <h3 style="color: #4b0082; margin: 10px 0 5px 0; font-size: 18px;">' . $evt_name . '</h3>
                    <p style="color: #666666; font-size: 13px; margin: 0 0 15px 0; line-height: 1.4;">' . $evt_desc . '</p>
                    
                    <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 13px; color: #555555;">
                        <tr>
                            <td style="padding: 4px 0; font-weight: bold; width: 60px; vertical-align: top;">Fecha:</td>
                            <td style="padding: 4px 0;">' . $evt_date . '</td>
                        </tr>
                        <tr>
                            <td style="padding: 4px 0; font-weight: bold; vertical-align: top;">Lugar:</td>
                            <td style="padding: 4px 0;">' . $evt_loc . '</td>
                        </tr>
                        <tr>
                            <td style="padding: 4px 0; font-weight: bold; vertical-align: top;">Precio:</td>
                            <td style="padding: 4px 0; color: #4b0082; font-weight: bold;">' . $evt_price . '</td>
                        </tr>
                    </table>
                    
                    <div style="text-align: center; margin-top: 15px;">
                        <a href="' . $evt_link . '" style="background-color: #ffcc80; color: #4b0082; text-decoration: none; padding: 10px 20px; border-radius: 4px; font-weight: bold; display: inline-block; box-shadow: 0 2px 4px rgba(0,0,0,0.1); font-size: 14px;">Ver Detalles e Inscribirme</a>
                    </div>
                </div>
            </div>';
        }
        $evt_stmt->close();
    }
    
    // Plantilla general de correo HTML
    $full_message = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
    </head>
    <body style="margin: 0; padding: 0; background-color: #f4f4f4; font-family: Arial, sans-serif;">
        <table width="100%" bgcolor="#f4f4f4" cellpadding="0" cellspacing="0" border="0" style="padding: 20px 0;">
            <tr>
                <td align="center">
                    <table width="100%" max-width="600" style="max-width: 600px; width: 100%;" bgcolor="#ffffff" cellpadding="0" cellspacing="0" border="0" style="border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 8px rgba(0,0,0,0.05);">
                        <!-- Header -->
                        <tr>
                            <td bgcolor="#2a004a" align="center" style="padding: 25px 20px; border-bottom: 3px solid #ffcc80;">
                                <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: bold; letter-spacing: 1px;">Fit<span style="color: #ffcc80;">5K</span></h1>
                            </td>
                        </tr>
                        <!-- Body -->
                        <tr>
                            <td style="padding: 30px 25px; color: #333333; font-size: 15px; line-height: 1.6;">
                                <p style="margin-top: 0; font-weight: bold; font-size: 16px;">¡Hola, ' . htmlspecialchars($nombre . ' ' . $apellido) . '!</p>
                                ' . nl2br(htmlspecialchars($message)) . '
                                ' . $event_html . '
                            </td>
                        </tr>
                        <!-- Footer -->
                        <tr>
                            <td bgcolor="#f8f9fa" align="center" style="padding: 20px; border-top: 1px solid #eeeeee; font-size: 12px; color: #888888; line-height: 1.5;">
                                Este es un correo informativo enviado automáticamente a los usuarios registrados en Fit5K.<br>
                                &copy; 2026 Fit5K. Todos los derechos reservados.<br>
                                <a href="' . SITE_URL . '" style="color: #2a004a; text-decoration: underline;">Visitar Sitio Web</a>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>';
    
    $mailer = new SMTPMailer();
    $result = $mailer->send($email, $subject, $full_message);
    
    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Fallo al enviar correo a través del servidor SMTP.']);
    }
    exit;
}

// --- Obtener datos de eventos activos en PHP ---
$active_events_query = "SELECT id, nombre, descripcion, fecha_evento, ubicacion, precio, imagen_url FROM eventos WHERE estado = 'activo' ORDER BY fecha_creacion DESC";
$active_events_result = $db->query($active_events_query);
$active_events = [];
if ($active_events_result) {
    while ($row = $active_events_result->fetch_assoc()) {
        $active_events[] = $row;
    }
}

$page_title = 'Envío de Correo Masivo';
include '../../includes/admin-header.php';
?>

<div class="admin-main">
    <!-- Header -->
    <div class="admin-header">
        <h1><i class="fas fa-paper-plane"></i> Envío de Correo Masivo</h1>
        <div class="admin-header-actions">
            <a href="dashboard.php" class="admin-btn admin-btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver al Dashboard
            </a>
        </div>
    </div>

    <div class="dashboard-grid" style="grid-template-columns: 1.2fr 1fr; gap: 2rem; align-items: start;">
        <!-- Formulario -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h2><i class="fas fa-edit"></i> Configurar Mensaje</h2>
            </div>
            
            <form id="bulk-email-form" class="admin-form" style="max-width: 100%;">
                <div class="form-group">
                    <label for="destinatarios_tipo">Destinatarios *</label>
                    <select id="destinatarios_tipo" name="destinatarios_tipo" class="form-control">
                        <option value="all">Todos los usuarios activos</option>
                        <option value="runners">Solo corredores activos</option>
                        <option value="admins">Solo administradores y organizadores activos</option>
                    </select>
                    <small class="form-text text-muted">Se enviará el correo de manera individual a cada miembro activo del grupo seleccionado.</small>
                </div>

                <div class="form-group">
                    <label for="asunto">Asunto del Correo *</label>
                    <input type="text" id="asunto" name="asunto" class="form-control" required 
                           placeholder="Ej: ¡Únete a nuestro próximo desafío!">
                </div>

                <div class="form-group">
                    <label for="evento_id">Promocionar un Evento Activo</label>
                    <select id="evento_id" name="evento_id" class="form-control" onchange="updatePreview()">
                        <option value="0">Ninguno (No promocionar evento)</option>
                        <?php foreach ($active_events as $evt): ?>
                        <option value="<?php echo $evt['id']; ?>">
                            <?php echo htmlspecialchars($evt['nombre']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text text-muted">Si seleccionas un evento, se inyectará una tarjeta publicitaria estilizada con información del evento al final del mensaje.</small>
                </div>

                <div class="form-group">
                    <label for="mensaje">Cuerpo del Mensaje *</label>
                    <textarea id="mensaje" name="mensaje" class="form-control" rows="12" required 
                              placeholder="Escribe el cuerpo del correo aquí..." oninput="updatePreview()"></textarea>
                    <small class="form-text text-muted">Puedes redactar el contenido principal. Se inyectará el saludo con el nombre del usuario al inicio de forma automática.</small>
                </div>

                <div class="form-actions" style="margin-top: 1rem;">
                    <button type="submit" class="admin-btn admin-btn-primary" style="padding: 0.8rem 2rem; font-size: 1rem;">
                        <i class="fas fa-paper-plane"></i> Iniciar Envío Masivo
                    </button>
                </div>
            </form>
        </div>

        <!-- Vista Previa en Vivo -->
        <div class="admin-card" style="position: sticky; top: 20px;">
            <div class="admin-card-header">
                <h2><i class="fas fa-eye"></i> Vista Previa en Tiempo Real</h2>
            </div>
            
            <div class="email-preview-container" style="border: 1px solid #dee2e6; border-radius: 8px; overflow: hidden; background: #f8f9fa; min-height: 400px; box-shadow: inset 0 2px 4px rgba(0,0,0,0.05);">
                <!-- Header del email -->
                <div style="background: #2a004a; padding: 15px; text-align: center; border-bottom: 3px solid #ffcc80;">
                    <h4 style="color: white; margin: 0; font-family: 'Poppins', sans-serif; font-size: 1.2rem; letter-spacing: 0.5px;">Fit<span style="color: #ffcc80;">5K</span></h4>
                </div>
                <!-- Contenido del email -->
                <div style="padding: 20px; background: white; min-height: 250px; font-family: Arial, sans-serif; font-size: 14px; line-height: 1.5; color: #333;">
                    <p style="margin-top: 0; font-weight: bold; color: #000;">¡Hola, [Nombre del Usuario]!</p>
                    <div id="preview-body" style="white-space: pre-wrap; color: #444; margin-bottom: 20px;">Escribe el cuerpo del mensaje para ver una vista previa aquí...</div>
                    <div id="preview-event-card" style="display: none;"></div>
                </div>
                <!-- Footer del email -->
                <div style="background: #f8f9fa; padding: 15px; text-align: center; font-family: Arial, sans-serif; font-size: 11px; color: #888; border-top: 1px solid #eee;">
                    Este es un correo informativo enviado automáticamente a los usuarios registrados en Fit5K.<br>
                    &copy; 2026 Fit5K. Todos los derechos reservados.<br>
                    <a href="#" style="color: #2a004a; text-decoration: underline; pointer-events: none;">Visitar Sitio Web</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Progreso de Envío -->
<div id="progressModal" class="progress-modal-overlay" style="display: none; position: fixed; top:0; left:0; width:100%; height:100%; background: rgba(0,0,0,0.6); z-index: 10000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div class="progress-modal-content" style="background: white; border-radius: 12px; max-width: 550px; width: 90%; padding: 25px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); display: flex; flex-direction: column; gap: 15px;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 10px;">
            <h3 style="margin: 0; color: #2a004a;"><i class="fas fa-spinner fa-spin"></i> Enviando Correos...</h3>
            <span id="sending-badge" class="badge badge-warning">En proceso</span>
        </div>
        
        <!-- Barra de Progreso -->
        <div style="margin-top: 10px;">
            <div style="display: flex; justify-content: space-between; font-size: 0.9rem; color: #555; margin-bottom: 8px;">
                <span id="progress-text">Preparando destinatarios...</span>
                <span id="progress-percent" style="font-weight: 600;">0%</span>
            </div>
            <div class="progress-bar-container" style="width: 100%; height: 12px; background: #e9ecef; border-radius: 6px; overflow: hidden;">
                <div id="progress-bar" style="width: 0%; height: 100%; background: linear-gradient(90deg, #6a0dad, #4b0082); transition: width 0.2s ease; border-radius: 6px;"></div>
            </div>
        </div>

        <!-- Estadísticas Rápidas -->
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; text-align: center; margin-top: 5px;">
            <div style="background: #f8f9fa; padding: 10px; border-radius: 6px; border: 1px solid #eee;">
                <div id="total-count" style="font-size: 1.3rem; font-weight: 700; color: #333;">0</div>
                <div style="font-size: 0.75rem; color: #888; text-transform: uppercase;">Total</div>
            </div>
            <div style="background: #e8f5e9; padding: 10px; border-radius: 6px; border: 1px solid #c8e6c9;">
                <div id="success-count" style="font-size: 1.3rem; font-weight: 700; color: #2e7d32;">0</div>
                <div style="font-size: 0.75rem; color: #2e7d32; text-transform: uppercase;">Éxito</div>
            </div>
            <div style="background: #ffebee; padding: 10px; border-radius: 6px; border: 1px solid #ffcdd2;">
                <div id="failed-count" style="font-size: 1.3rem; font-weight: 700; color: #c62828;">0</div>
                <div style="font-size: 0.75rem; color: #c62828; text-transform: uppercase;">Fallidos</div>
            </div>
        </div>

        <!-- Log en tiempo real -->
        <div style="border: 1px solid #dee2e6; border-radius: 6px; background: #f8f9fa; height: 180px; overflow-y: auto; padding: 10px; font-family: monospace; font-size: 0.8rem; line-height: 1.4; color: #444;" id="realtime-log">
            <div style="color: #666;">Iniciando sesión de correo masivo...</div>
        </div>

        <!-- Acciones del Modal -->
        <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #eee; padding-top: 15px;">
            <button id="cancel-sending-btn" class="admin-btn admin-btn-danger" style="padding: 0.5rem 1.2rem; font-size: 0.9rem;" onclick="stopSending()">
                <i class="fas fa-ban"></i> Detener
            </button>
            <button id="close-progress-btn" class="admin-btn admin-btn-secondary" style="padding: 0.5rem 1.2rem; font-size: 0.9rem; display: none;" onclick="closeProgressModal()">
                <i class="fas fa-times"></i> Cerrar
            </button>
        </div>
    </div>
</div>

<script>
// Diccionario de eventos activos en JS para la vista previa
const activeEvents = <?php echo json_encode($active_events); ?>;
const siteUrl = "<?php echo SITE_URL; ?>";

// Actualiza la vista previa del correo en tiempo real
function updatePreview() {
    const message = document.getElementById('mensaje').value;
    const eventId = parseInt(document.getElementById('evento_id').value);
    
    // Cuerpo del mensaje
    const previewBody = document.getElementById('preview-body');
    if (message.trim() !== "") {
        previewBody.textContent = message;
    } else {
        previewBody.textContent = "Escribe el cuerpo del mensaje para ver una vista previa aquí...";
    }
    
    // Tarjeta del evento
    const previewEventCard = document.getElementById('preview-event-card');
    if (eventId > 0) {
        const evt = activeEvents.find(e => parseInt(e.id) === eventId);
        if (evt) {
            let imgHtml = "";
            if (evt.imagen_url && evt.imagen_url !== "0") {
                imgHtml = `<img src="${siteUrl}/${evt.imagen_url}" alt="${evt.nombre}" style="width: 100%; max-height: 150px; object-fit: cover;" />`;
            }
            
            let priceText = parseFloat(evt.precio) > 0 ? `$${parseFloat(evt.precio).toFixed(2)}` : 'Gratuito';
            let dateFormatted = new Date(evt.fecha_evento).toLocaleString('es-DO', { 
                day: '2-digit', month: '2-digit', year: 'numeric', 
                hour: '2-digit', minute: '2-digit', hour12: true 
            });
            
            previewEventCard.innerHTML = `
            <div style="margin-top: 20px; border: 1px solid #4b0082; border-radius: 8px; overflow: hidden; background-color: #fcfaff; font-family: Arial, sans-serif;">
                ${imgHtml}
                <div style="padding: 15px;">
                    <span style="background-color: #4b0082; color: white; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: bold; text-transform: uppercase;">Evento Destacado</span>
                    <h4 style="color: #4b0082; margin: 8px 0 4px 0; font-size: 16px;">${evt.nombre}</h4>
                    <p style="color: #666666; font-size: 12px; margin: 0 0 10px 0; line-height: 1.4;">${evt.descripcion ? evt.descripcion.substring(0, 150) + '...' : ''}</p>
                    
                    <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size: 12px; color: #555555;">
                        <tr>
                            <td style="padding: 2px 0; font-weight: bold; width: 50px;">Fecha:</td>
                            <td style="padding: 2px 0;">${dateFormatted}</td>
                        </tr>
                        <tr>
                            <td style="padding: 2px 0; font-weight: bold;">Lugar:</td>
                            <td style="padding: 2px 0;">${evt.ubicacion}</td>
                        </tr>
                        <tr>
                            <td style="padding: 2px 0; font-weight: bold;">Precio:</td>
                            <td style="padding: 2px 0; color: #4b0082; font-weight: bold;">${priceText}</td>
                        </tr>
                    </table>
                    
                    <div style="text-align: center; margin-top: 10px;">
                        <span style="background-color: #ffcc80; color: #4b0082; text-decoration: none; padding: 8px 16px; border-radius: 4px; font-weight: bold; display: inline-block; font-size: 12px; cursor: default;">Ver Detalles e Inscribirme</span>
                    </div>
                </div>
            </div>`;
            previewEventCard.style.display = 'block';
        }
    } else {
        previewEventCard.style.display = 'none';
        previewEventCard.innerHTML = '';
    }
}

// Inicialización de la vista previa al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    updatePreview();
});

// --- Lógica del Encolador de Envío de Correos (Queue Worker) ---
let isSending = false;
let stopRequested = false;
let recipientsQueue = [];
let currentIndex = 0;
let successCount = 0;
let failedCount = 0;
let totalRecipients = 0;

document.getElementById('bulk-email-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const subject = document.getElementById('asunto').value.trim();
    const message = document.getElementById('mensaje').value.trim();
    const type = document.getElementById('destinatarios_tipo').value;
    const eventId = parseInt(document.getElementById('evento_id').value);
    
    if (!subject || !message) {
        alert('Por favor, completa los campos requeridos (*).');
        return;
    }
    
    if (confirm('¿Estás seguro de que deseas iniciar el envío masivo? Esta acción no se puede deshacer.')) {
        startBulkSending(type, subject, message, eventId);
    }
});

function startBulkSending(type, subject, message, eventId) {
    // Inicializar variables de estado
    isSending = true;
    stopRequested = false;
    currentIndex = 0;
    successCount = 0;
    failedCount = 0;
    recipientsQueue = [];
    
    // Resetear elementos del modal
    document.getElementById('progress-bar').style.width = '0%';
    document.getElementById('progress-percent').textContent = '0%';
    document.getElementById('progress-text').textContent = 'Obteniendo lista de destinatarios...';
    document.getElementById('total-count').textContent = '0';
    document.getElementById('success-count').textContent = '0';
    document.getElementById('failed-count').textContent = '0';
    
    const logContainer = document.getElementById('realtime-log');
    logContainer.innerHTML = '<div style="color: #6a0dad;">[Info] Conectando con la base de datos...</div>';
    
    document.getElementById('sending-badge').className = 'badge badge-warning';
    document.getElementById('sending-badge').textContent = 'En proceso';
    
    document.getElementById('cancel-sending-btn').style.display = 'inline-flex';
    document.getElementById('close-progress-btn').style.display = 'none';
    
    // Mostrar modal
    document.getElementById('progressModal').style.display = 'flex';
    
    // Obtener los destinatarios
    fetch(`send-bulk-email.php?action=get_recipients&type=${type}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                recipientsQueue = data.recipients;
                totalRecipients = recipientsQueue.length;
                
                document.getElementById('total-count').textContent = totalRecipients;
                
                if (totalRecipients === 0) {
                    appendLog('[Aviso] No hay destinatarios activos en el grupo seleccionado.', 'warning');
                    finishSending(true);
                    return;
                }
                
                appendLog(`[Inicio] Se encontraron ${totalRecipients} destinatarios activos. Iniciando envíos...`, 'info');
                processNextEmail(subject, message, eventId);
            } else {
                appendLog(`[Error] Fallo al obtener destinatarios: ${data.error}`, 'danger');
                finishSending(false);
            }
        })
        .catch(err => {
            appendLog(`[Error de Conexión] ${err.message}`, 'danger');
            finishSending(false);
        });
}

function processNextEmail(subject, message, eventId) {
    if (stopRequested) {
        appendLog('[Detención] Envío masivo cancelado por el administrador.', 'danger');
        finishSending(false, true);
        return;
    }
    
    if (currentIndex >= totalRecipients) {
        appendLog('[Finalizado] Todos los correos han sido procesados.', 'success');
        finishSending(true);
        return;
    }
    
    const recipient = recipientsQueue[currentIndex];
    const email = recipient.email;
    const name = recipient.nombre;
    const lastname = recipient.apellido;
    
    document.getElementById('progress-text').textContent = `Enviando a ${email} (${currentIndex + 1} de ${totalRecipients})...`;
    
    // Armar FormData para el POST
    const formData = new FormData();
    formData.append('email', email);
    formData.append('nombre', name);
    formData.append('apellido', lastname);
    formData.append('subject', subject);
    formData.append('message', message);
    formData.append('event_id', eventId);
    
    fetch('send-bulk-email.php?action=send_single', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            successCount++;
            document.getElementById('success-count').textContent = successCount;
            appendLog(`✓ Enviado exitosamente a: ${name} ${lastname} (${email})`, 'success');
        } else {
            failedCount++;
            document.getElementById('failed-count').textContent = failedCount;
            appendLog(`✗ Error enviando a ${email}: ${data.error}`, 'danger');
        }
        
        // Actualizar progreso
        currentIndex++;
        updateProgressBar();
        
        // Procesar el siguiente correo
        setTimeout(() => {
            processNextEmail(subject, message, eventId);
        }, 100); // Pequeña pausa para no sobrecargar el servidor SMTP
    })
    .catch(err => {
        failedCount++;
        document.getElementById('failed-count').textContent = failedCount;
        appendLog(`✗ Error de red enviando a ${email}: ${err.message}`, 'danger');
        
        currentIndex++;
        updateProgressBar();
        
        setTimeout(() => {
            processNextEmail(subject, message, eventId);
        }, 100);
    });
}

function updateProgressBar() {
    const percent = Math.round((currentIndex / totalRecipients) * 100);
    document.getElementById('progress-bar').style.width = `${percent}%`;
    document.getElementById('progress-percent').textContent = `${percent}%`;
}

function appendLog(message, type = 'info') {
    const logContainer = document.getElementById('realtime-log');
    const logItem = document.createElement('div');
    
    let color = '#333';
    if (type === 'success') color = '#2e7d32';
    if (type === 'danger') color = '#c62828';
    if (type === 'warning') color = '#ef6c00';
    if (type === 'info') color = '#1565c0';
    
    logItem.style.color = color;
    logItem.textContent = message;
    
    logContainer.appendChild(logItem);
    logContainer.scrollTop = logContainer.scrollHeight;
}

function stopSending() {
    if (isSending) {
        if (confirm('¿Estás seguro de que deseas detener el envío? Los correos enviados hasta ahora ya han sido entregados.')) {
            stopRequested = true;
            document.getElementById('cancel-sending-btn').disabled = true;
        }
    }
}

function finishSending(completed = true, stopped = false) {
    isSending = false;
    
    const badge = document.getElementById('sending-badge');
    if (stopped) {
        badge.className = 'badge badge-danger';
        badge.textContent = 'Cancelado';
        document.getElementById('progress-text').textContent = 'Envío cancelado por el usuario.';
    } else if (completed && failedCount === 0) {
        badge.className = 'badge badge-success';
        badge.textContent = 'Completado';
        document.getElementById('progress-text').textContent = 'Envío completado exitosamente.';
    } else {
        badge.className = 'badge badge-info';
        badge.textContent = 'Terminado con errores';
        document.getElementById('progress-text').textContent = `Proceso finalizado. Éxitos: ${successCount}, Fallos: ${failedCount}`;
    }
    
    document.getElementById('cancel-sending-btn').style.display = 'none';
    document.getElementById('cancel-sending-btn').disabled = false;
    document.getElementById('close-progress-btn').style.display = 'inline-flex';
}

function closeProgressModal() {
    if (!isSending) {
        document.getElementById('progressModal').style.display = 'none';
    }
}
</script>

<?php include '../../includes/admin-footer.php'; ?>
