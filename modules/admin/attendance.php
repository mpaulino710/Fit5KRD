<?php
// Archivo: modules/admin/attendance.php
require_once '../../config/config.php';
require_once '../../config/database.php';

// Verificar autenticación y permisos de administrador
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'organizador'])) {
    header('Location: ../auth/login.php');
    exit();
}

$db = getDB();

// Verificación y actualización de la base de datos para la asistencia
try {
    $result = $db->query("SHOW COLUMNS FROM inscripciones LIKE 'asistencia'");
    if ($result && $result->num_rows == 0) {
        $db->query("ALTER TABLE inscripciones ADD asistencia TINYINT(1) DEFAULT 0");
        $db->query("ALTER TABLE inscripciones ADD fecha_asistencia DATETIME NULL");
    }
} catch (Exception $e) {
    // Si falla, el script continuará, pero podría haber un error SQL más adelante.
}

$event_id = isset($_GET['eventid']) ? intval($_GET['eventid']) : 0;
$runner_num = isset($_GET['runner']) ? trim($_GET['runner']) : '';

$status = null;
$message = '';
$runner_data = null;

if ($event_id > 0 && !empty($runner_num)) {
    // Buscar inscripción del corredor
    $query = "
        SELECT i.*, 
               e.nombre as event_name, 
               COALESCE(i.nombre, u.nombre) as runner_name,
               COALESCE(i.apellido, u.apellido) as runner_lastname
        FROM inscripciones i
        JOIN eventos e ON i.id_evento = e.id
        LEFT JOIN usuarios u ON i.id_usuario = u.id
        WHERE i.id_evento = ? AND i.numero_corredor = ?
    ";
    
    $stmt = $db->prepare($query);
    $stmt->bind_param("is", $event_id, $runner_num);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $runner_data = $result->fetch_assoc();
        
        if ($runner_data['asistencia'] == 1) {
            $status = 'warning';
            $message = 'El corredor ya ha registrado su asistencia.';
        } else {
            // Actualizar asistencia
            $update_stmt = $db->prepare("UPDATE inscripciones SET asistencia = 1, fecha_asistencia = NOW() WHERE id = ?");
            $update_stmt->bind_param("i", $runner_data['id']);
            if ($update_stmt->execute()) {
                $status = 'success';
                $message = 'Asistencia registrada correctamente.';
                $runner_data['asistencia'] = 1;
                $runner_data['fecha_asistencia'] = date('Y-m-d H:i:s');
            } else {
                $status = 'error';
                $message = 'Error al registrar la asistencia.';
            }
        }
    } else {
        $status = 'error';
        $message = 'Corredor no encontrado en este evento. Verifique el código QR.';
    }
} else if (isset($_GET['eventid']) || isset($_GET['runner'])) {
    $status = 'error';
    $message = 'Parámetros inválidos proporcionados.';
}

// Support for AJAX JSON response
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $runner_data
    ]);
    exit();
}

include '../../includes/admin-header.php';
?>

<div class="admin-main">
    <div class="admin-header">
        <h1>
            <i class="fas fa-qrcode"></i> Registro de Asistencia
        </h1>
        <div class="admin-header-actions">
            <a href="inscriptions.php" class="admin-btn admin-btn-outline">
                <i class="fas fa-arrow-left"></i> Volver a Inscripciones
            </a>
        </div>
    </div>

    <div class="admin-card attendance-card" style="max-width: 600px; margin: 0 auto; text-align: center;">
        <div class="admin-card-body" style="padding: 2rem;">
            <?php if ($status === 'success'): ?>
                <div class="attendance-icon" style="color: #28a745; font-size: 5rem; margin-bottom: 1rem;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h2 style="color: #28a745; margin-bottom: 0.5rem;">¡Asistencia Registrada!</h2>
                <p style="font-size: 1.1rem; color: #6c757d; margin-bottom: 2rem;"><?php echo htmlspecialchars($message); ?></p>
                
                <div class="runner-details" style="background: #f8f9fa; border-radius: 8px; padding: 1.5rem; text-align: left; margin-bottom: 2rem;">
                    <h3 style="margin-top: 0; margin-bottom: 1rem; border-bottom: 1px solid #dee2e6; padding-bottom: 0.5rem;">
                        <?php echo htmlspecialchars($runner_data['runner_name'] . ' ' . $runner_data['runner_lastname']); ?>
                    </h3>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <strong><i class="fas fa-hashtag"></i> Corredor:</strong><br>
                            <?php echo htmlspecialchars($runner_data['numero_corredor']); ?>
                        </div>
                        <div>
                            <strong><i class="fas fa-calendar-alt"></i> Evento:</strong><br>
                            <?php echo htmlspecialchars($runner_data['event_name']); ?>
                        </div>
                        <div>
                            <strong><i class="fas fa-layer-group"></i> Categoría:</strong><br>
                            <?php echo htmlspecialchars($runner_data['categoria'] ?? 'N/A'); ?>
                        </div>
                        <div>
                            <strong><i class="fas fa-tshirt"></i> Talla:</strong><br>
                            <?php echo htmlspecialchars($runner_data['talla_camiseta'] ?? 'N/A'); ?>
                        </div>
                        <div style="grid-column: span 2;">
                            <strong><i class="fas fa-clock"></i> Hora de registro:</strong><br>
                            <?php echo date('d/m/Y h:i A', strtotime($runner_data['fecha_asistencia'])); ?>
                        </div>
                    </div>
                </div>

            <?php elseif ($status === 'warning'): ?>
                <div class="attendance-icon" style="color: #ffc107; font-size: 5rem; margin-bottom: 1rem;">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h2 style="color: #ffc107; margin-bottom: 0.5rem;">Atención</h2>
                <p style="font-size: 1.1rem; color: #6c757d; margin-bottom: 2rem;"><?php echo htmlspecialchars($message); ?></p>
                
                <div class="runner-details" style="background: #fff3cd; border: 1px solid #ffeeba; border-radius: 8px; padding: 1.5rem; text-align: left; margin-bottom: 2rem;">
                    <h3 style="margin-top: 0; color: #856404;"><i class="fas fa-info-circle"></i> Datos del Corredor</h3>
                    <p style="margin-bottom: 0.5rem;"><strong>Nombre:</strong> <?php echo htmlspecialchars($runner_data['runner_name'] . ' ' . $runner_data['runner_lastname']); ?></p>
                    <p style="margin-bottom: 0.5rem;"><strong>Corredor:</strong> <?php echo htmlspecialchars($runner_data['numero_corredor']); ?></p>
                    <p style="margin-bottom: 0;"><strong>Registrado previamente el:</strong> <?php echo date('d/m/Y h:i A', strtotime($runner_data['fecha_asistencia'])); ?></p>
                </div>

            <?php elseif ($status === 'error'): ?>
                <div class="attendance-icon" style="color: #dc3545; font-size: 5rem; margin-bottom: 1rem;">
                    <i class="fas fa-times-circle"></i>
                </div>
                <h2 style="color: #dc3545; margin-bottom: 0.5rem;">Error</h2>
                <p style="font-size: 1.1rem; color: #6c757d; margin-bottom: 2rem;"><?php echo htmlspecialchars($message); ?></p>
                
            <?php else: ?>
                <div class="attendance-icon" style="color: #0d6efd; font-size: 5rem; margin-bottom: 1rem;">
                    <i class="fas fa-qrcode"></i>
                </div>
                <h2 style="margin-bottom: 0.5rem;">Listo para escanear</h2>
                <p style="font-size: 1.1rem; color: #6c757d; margin-bottom: 2rem;">Escanea el código QR de un participante para registrar su asistencia al evento.</p>
            <?php endif; ?>

            <a href="inscriptions.php" class="admin-btn admin-btn-primary" style="padding: 0.75rem 2rem; font-size: 1.1rem;">
                Volver
            </a>
        </div>
    </div>
</div>

<?php include '../../includes/admin-footer.php'; ?>
