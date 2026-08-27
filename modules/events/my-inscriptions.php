<?php
// modules/events/my-inscriptions.php
require_once '../../config/config.php';
require_once '../../config/database.php';

// session_start(); // Asumimos que config.php o similar maneja esto, pero aseguramos verificación
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$db = getDB();
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Obtener inscripciones del usuario
$inscripciones_query = "SELECT i.*, e.nombre as evento_nombre, e.fecha_evento, e.ubicacion, e.imagen_url,
                       p.estado as estado_pago
                       FROM inscripciones i 
                       JOIN eventos e ON i.id_evento = e.id 
                       LEFT JOIN pagos p ON p.id_inscripcion = i.id
                       WHERE i.id_usuario = ? 
                       ORDER BY i.fecha_inscripcion DESC";
$stmt = $db->prepare($inscripciones_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$inscripciones_result = $stmt->get_result();
$inscripciones = $inscripciones_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

include '../../includes/header.php';
?>

<div class="container">
    <h1 class="text-center mb-4">
        <i class="fas fa-running"></i> Mis Inscripciones
    </h1>
    
    <div class="profile-container" style="display: block;"> <!-- Usamos display:block para que ocupe ancho completo sin sidebar -->
        <div class="profile-content" style="width: 100%;">
            <?php if (!empty($inscripciones)): ?>
                <div class="inscriptions-list">
                    <?php foreach ($inscripciones as $inscripcion): ?>
                    <div class="inscription-card">
                        <div class="inscription-header">
                            <div class="inscription-event-info">
                                <h3 class="inscription-event-title">
                                    <?php echo htmlspecialchars($inscripcion['evento_nombre']); ?>
                                </h3>
                                <div class="inscription-event-details">
                                    <span class="event-date">
                                        <i class="fas fa-calendar-alt"></i>
                                        <?php echo date('d/m/Y h:i A', strtotime($inscripcion['fecha_evento'])); ?>
                                    </span>
                                    <span class="event-location">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <?php echo htmlspecialchars($inscripcion['ubicacion']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="inscription-status">
                                <?php
                                $status_config = [
                                    'confirmada' => ['color' => 'success', 'icon' => 'check-circle', 'text' => 'Confirmada'],
                                    'pendiente' => ['color' => 'warning', 'icon' => 'clock', 'text' => 'Pendiente'],
                                    'cancelada' => ['color' => 'danger', 'icon' => 'times-circle', 'text' => 'Cancelada'],
                                    'ausente' => ['color' => 'secondary', 'icon' => 'user-slash', 'text' => 'Ausente']
                                ];
                                $status = $status_config[$inscripcion['estado']] ?? $status_config['pendiente'];
                                ?>
                                <span class="badge badge-<?php echo $status['color']; ?>">
                                    <i class="fas fa-<?php echo $status['icon']; ?>"></i>
                                    <?php echo $status['text']; ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="inscription-body">
                            <div class="inscription-details">
                                <div class="detail-item">
                                    <i class="fas fa-hashtag"></i>
                                    <strong>Número de corredor:</strong>
                                    <span><?php echo $inscripcion['numero_corredor'] ?: 'Pendiente'; ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-tag"></i>
                                    <strong>Categoría:</strong>
                                    <span><?php echo ucfirst($inscripcion['categoria']); ?></span>
                                </div>
                                <?php if (!empty($inscripcion['modalidad'])): ?>
                                <div class="detail-item">
                                    <i class="fas fa-route"></i>
                                    <strong>Distancia:</strong>
                                    <span><?php echo htmlspecialchars($inscripcion['modalidad']); ?></span>
                                </div>
                                <?php endif; ?>
                                <div class="detail-item">
                                    <i class="fas fa-calendar-day"></i>
                                    <strong>Fecha inscripción:</strong>
                                    <span><?php echo date('d/m/Y h:i A', strtotime($inscripcion['fecha_inscripcion'])); ?></span>
                                </div>
                                <?php if ($inscripcion['estado_pago']): ?>
                                <div class="detail-item">
                                    <i class="fas fa-credit-card"></i>
                                    <strong>Estado pago:</strong>
                                    <span class="badge badge-<?php echo $inscripcion['estado_pago'] === 'completado' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($inscripcion['estado_pago']); ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="inscription-actions">
                                <a href="/modules/events/view.php?id=<?php echo $inscripcion['id_evento']; ?>" 
                                   class="btn btn-outline btn-sm">
                                    <i class="fas fa-eye"></i> Ver Evento
                                </a>
                                <?php if ($inscripcion['estado_pago'] === 'completado'): ?>
                                    <button class="btn btn-primary btn-sm">
                                        <i class="fas fa-qrcode"></i> Ver QR
                                    </button>
                                <?php else: ?>
                                    <span class="badge badge-warning">
                                        <i class="fas fa-clock"></i> Pendiente de confirmación de pago
                                    </span>
                                <?php endif; ?>
                                <?php if (isset($inscripcion['asistencia']) && $inscripcion['asistencia'] == 1): ?>
                                    <a href="/modules/events/generate-certificate.php?id=<?php echo $inscripcion['id']; ?>&download=1" 
                                       class="btn btn-warning btn-sm" target="_blank">
                                        <i class="fas fa-certificate"></i> Certificado
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-calendar-times"></i>
                    </div>
                    <h3>No tienes inscripciones</h3>
                    <p>¡Aún no te has inscrito en ningún evento! Explora nuestros eventos disponibles.</p>
                    <a href="/modules/events/index.php" class="btn btn-primary">
                        <i class="fas fa-running"></i> Explorar Eventos
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
