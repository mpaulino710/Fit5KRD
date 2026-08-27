<?php
// modules/admin/view-inscription.php
require_once '../../config/config.php';
require_once '../../config/database.php';

// Remover session_start() ya que config.php ya lo inicia
// session_start(); // <- Esta línea debe removerse
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'organizador'])) {
    header('Location: ../auth/login.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: inscriptions.php');
    exit();
}

$db = getDB();
$inscripcion_id = intval($_GET['id']);

// Obtener información de la inscripción
$query = "SELECT
            i.*,
            u.nombre as usuario_nombre,
            u.apellido as usuario_apellido,
            u.email as usuario_email,
            u.telefono as usuario_telefono,
            u.fecha_nacimiento as usuario_fecha_nacimiento,
            u.genero as usuario_genero,
            e.nombre as evento_nombre,
            e.fecha_evento,
            e.ubicacion as evento_ubicacion,
            e.distancia as evento_distancia,
            e.precio as evento_precio,
            e.categoria as evento_categoria,
            p.monto as pago_monto,
            p.metodo_pago as pago_metodo,
            p.estado as pago_estado,
            p.fecha_pago as pago_fecha,
            p.transaccion_id as pago_transaccion
          FROM inscripciones i
          JOIN usuarios u ON i.id_usuario = u.id
          JOIN eventos e ON i.id_evento = e.id
          LEFT JOIN pagos p ON p.id_inscripcion = i.id
          WHERE i.id = ?";

$stmt = $db->prepare($query);
$stmt->bind_param("i", $inscripcion_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: inscriptions.php');
    exit();
}

$inscripcion = $result->fetch_assoc();
$stmt->close();

// Obtener información adicional del evento - CORREGIDO
$query_evento = "SELECT
    COUNT(*) as total_inscritos,
    (SELECT cupo_maximo FROM eventos WHERE id = ?) as cupo_maximo,
    (SELECT cupo_disponible FROM eventos WHERE id = ?) as cupo_disponible
    FROM inscripciones WHERE id_evento = ? AND estado = 'confirmada'";

$stmt_evento = $db->prepare($query_evento);
$stmt_evento->bind_param("iii", 
    $inscripcion['id_evento'],
    $inscripcion['id_evento'],
    $inscripcion['id_evento']
);
$stmt_evento->execute();
$result_evento = $stmt_evento->get_result();

if ($result_evento->num_rows > 0) {
    $evento_info = $result_evento->fetch_assoc();
} else {
    // Datos por defecto si no hay inscripciones
    $evento_info = [
        'total_inscritos' => 0,
        'cupo_maximo' => 0,
        'cupo_disponible' => 0
    ];
}
$stmt_evento->close();

// Calcular edad si tiene fecha de nacimiento
$edad = null;
if ($inscripcion['usuario_fecha_nacimiento']) {
    $fecha_nacimiento = new DateTime($inscripcion['usuario_fecha_nacimiento']);
    $hoy = new DateTime();
    $edad = $hoy->diff($fecha_nacimiento)->y;
}

include '../../includes/admin-header.php';
?>

<div class="admin-main">
    <!-- Header -->
    <div class="admin-header">
        <div>
            <h1><i class="fas fa-running"></i> Detalles de Inscripción</h1>
            <p class="inscription-subtitle">
                ID: #<?php echo $inscripcion['id']; ?> •
                Número de corredor: <?php echo $inscripcion['numero_corredor'] ?: 'Pendiente'; ?>
            </p>
        </div>
        <div class="admin-header-actions">
            <a href="inscriptions.php" class="admin-btn admin-btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
            <a href="edit-inscription.php?id=<?php echo $inscripcion_id; ?>" class="admin-btn admin-btn-primary">
                <i class="fas fa-edit"></i> Editar
            </a>
            <button onclick="printInscription()" class="admin-btn admin-btn-success">
                <i class="fas fa-print"></i> Imprimir
            </button>
        </div>
    </div>

    <!-- Información principal -->
    <div class="dashboard-grid">
        <!-- Información del Corredor -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h2><i class="fas fa-user"></i> Información del Corredor</h2>
                <span class="badge badge-primary">
                    <?php echo strtoupper($inscripcion['categoria']); ?>
                </span>
            </div>
            <div class="user-details-grid">
                <div class="user-avatar-large">
                    <?php echo strtoupper(substr($inscripcion['usuario_nombre'], 0, 1) . substr($inscripcion['usuario_apellido'], 0, 1)); ?>
                </div>
                <div class="user-info-details">
                    <h3><?php echo htmlspecialchars($inscripcion['usuario_nombre'] . ' ' . $inscripcion['usuario_apellido']); ?></h3>
                    <div class="user-contact">
                        <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($inscripcion['usuario_email']); ?></p>
                        <p><i class="fas fa-phone"></i> <?php echo $inscripcion['usuario_telefono'] ?: 'No registrado'; ?></p>
                    </div>
                </div>
            </div>

            <div class="details-section">
                <h4><i class="fas fa-info-circle"></i> Datos Personales</h4>
                <div class="details-grid">
                    <div class="detail-item">
                        <span class="detail-label">Fecha de Nacimiento:</span>
                        <span class="detail-value">
                            <?php echo $inscripcion['usuario_fecha_nacimiento'] ? date('d/m/Y', strtotime($inscripcion['usuario_fecha_nacimiento'])) : 'No registrada'; ?>
                            <?php if ($edad): ?> (<?php echo $edad; ?> años)<?php endif; ?>
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Género:</span>
                        <span class="detail-value">
                            <?php echo match($inscripcion['usuario_genero']) {
                                'M' => 'Masculino',
                                'F' => 'Femenino',
                                'O' => 'Otro',
                                default => 'No especificado'
                            }; ?>
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Categoría:</span>
                        <span class="detail-value">
                            <?php echo ucfirst($inscripcion['categoria']); ?>
                        </span>
                    </div>
                </div>
            </div>

            <?php if ($inscripcion['notas_medicas']): ?>
            <div class="details-section">
                <h4><i class="fas fa-heartbeat"></i> Notas Médicas</h4>
                <div class="medical-notes">
                    <?php echo nl2br(htmlspecialchars($inscripcion['notas_medicas'])); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Información del Evento -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h2><i class="fas fa-calendar-alt"></i> Información del Evento</h2>
                <?php
                $estado_colors = [
                    'confirmada' => 'success',
                    'pendiente' => 'warning',
                    'cancelada' => 'danger',
                    'ausente' => 'secondary'
                ];
                $color = $estado_colors[$inscripcion['estado']] ?? 'secondary';
                ?>
                <span class="badge badge-<?php echo $color; ?>">
                    <?php echo ucfirst($inscripcion['estado']); ?>
                </span>
            </div>

            <div class="event-details-card">
                <h3><?php echo htmlspecialchars($inscripcion['evento_nombre']); ?></h3>
                <div class="event-info">
                    <p><i class="fas fa-calendar"></i>
                        <strong>Fecha:</strong> <?php echo date('d/m/Y H:i', strtotime($inscripcion['fecha_evento'])); ?>
                    </p>
                    <p><i class="fas fa-map-marker-alt"></i>
                        <strong>Ubicación:</strong> <?php echo htmlspecialchars($inscripcion['evento_ubicacion']); ?>
                    </p>
                    <p><i class="fas fa-ruler"></i>
                        <strong>Distancia:</strong> <?php echo $inscripcion['evento_distancia']; ?> km
                    </p>
                    <p><i class="fas fa-tag"></i>
                        <strong>Categoría:</strong> <?php echo ucfirst(str_replace('_', ' ', $inscripcion['evento_categoria'])); ?>
                    </p>
                </div>

                <div class="event-stats">
                    <div class="stat-box">
                        <div class="stat-number"><?php echo $evento_info['total_inscritos']; ?></div>
                        <div class="stat-label">Inscritos</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number"><?php echo $evento_info['cupo_disponible']; ?></div>
                        <div class="stat-label">Cupos Disponibles</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number"><?php echo $evento_info['cupo_maximo']; ?></div>
                        <div class="stat-label">Cupo Total</div>
                    </div>
                </div>
            </div>

            <div class="details-section">
                <h4><i class="fas fa-t-shirt"></i> Kit del Corredor</h4>
                <div class="details-grid">
                    <div class="detail-item">
                        <span class="detail-label">Talla de Camiseta:</span>
                        <span class="detail-value">
                            <?php echo $inscripcion['talla_camiseta'] ?: 'No especificada'; ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="details-section">
                <h4><i class="fas fa-phone-alt"></i> Contacto de Emergencia</h4>
                <div class="details-grid">
                    <div class="detail-item">
                        <span class="detail-label">Nombre:</span>
                        <span class="detail-value">
                            <?php echo $inscripcion['contacto_emergencia'] ?: 'No registrado'; ?>
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Teléfono:</span>
                        <span class="detail-value">
                            <?php echo $inscripcion['telefono_emergencia'] ?: 'No registrado'; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Información de Pago -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h2><i class="fas fa-credit-card"></i> Información de Pago</h2>
            <?php if ($inscripcion['pago_estado']):
                $pago_colors = [
                    'completado' => 'success',
                    'pendiente' => 'warning',
                    'fallido' => 'danger',
                    'reembolsado' => 'secondary'
                ];
                $pago_color = $pago_colors[$inscripcion['pago_estado']] ?? 'secondary';
            ?>
            <span class="badge badge-<?php echo $pago_color; ?>">
                Pago <?php echo ucfirst($inscripcion['pago_estado']); ?>
            </span>
            <?php else: ?>
            <span class="badge badge-secondary">Sin pago registrado</span>
            <?php endif; ?>
        </div>

        <div class="payment-details-grid">
            <?php if ($inscripcion['pago_monto']): ?>
            <div class="payment-summary">
                <div class="payment-amount-large">
                    $<?php echo number_format($inscripcion['pago_monto'], 2); ?>
                </div>
                <div class="payment-info">
                    <p><strong>Método:</strong> <?php echo ucfirst($inscripcion['pago_metodo']); ?></p>
                    <?php if ($inscripcion['pago_fecha']): ?>
                    <p><strong>Fecha:</strong> <?php echo date('d/m/Y H:i', strtotime($inscripcion['pago_fecha'])); ?></p>
                    <?php endif; ?>
                    <?php if ($inscripcion['pago_transaccion']): ?>
                    <p><strong>ID Transacción:</strong> <?php echo $inscripcion['pago_transaccion']; ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="empty-payment">
                <i class="fas fa-money-bill-wave"></i>
                <h3>Sin pago registrado</h3>
                <p>Este evento podría ser gratuito o el pago está pendiente</p>
                <?php if ($inscripcion['evento_precio'] > 0): ?>
                <a href="process-payment.php?inscription_id=<?php echo $inscripcion_id; ?>" class="admin-btn admin-btn-success">
                    <i class="fas fa-plus-circle"></i> Registrar Pago
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Historial de la Inscripción -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h2><i class="fas fa-history"></i> Historial</h2>
        </div>

        <div class="timeline">
            <div class="timeline-item">
                <div class="timeline-date">
                    <?php echo date('d/m/Y H:i', strtotime($inscripcion['fecha_inscripcion'])); ?>
                </div>
                <div class="timeline-content">
                    <h4><i class="fas fa-user-plus"></i> Inscripción Registrada</h4>
                    <p>El usuario se inscribió en el evento</p>
                </div>
            </div>

            <?php if ($inscripcion['pago_fecha']): ?>
            <div class="timeline-item">
                <div class="timeline-date">
                    <?php echo date('d/m/Y H:i', strtotime($inscripcion['pago_fecha'])); ?>
                </div>
                <div class="timeline-content">
                    <h4><i class="fas fa-credit-card"></i> Pago <?php echo $inscripcion['pago_estado'] === 'completado' ? 'Completado' : 'Registrado'; ?></h4>
                    <p>Pago de $<?php echo number_format($inscripcion['pago_monto'], 2); ?> por <?php echo $inscripcion['pago_metodo']; ?></p>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($inscripcion['tiempo_final']): ?>
            <div class="timeline-item">
                <div class="timeline-date">
                    <?php echo date('d/m/Y H:i', strtotime($inscripcion['fecha_evento'])); ?>
                </div>
                <div class="timeline-content">
                    <h4><i class="fas fa-flag-checkered"></i> Evento Completado</h4>
                    <p>Tiempo final: <?php echo $inscripcion['tiempo_final']; ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Acciones -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h2><i class="fas fa-cogs"></i> Acciones</h2>
        </div>

        <div class="action-buttons-grid">
            <a href="edit-inscription.php?id=<?php echo $inscripcion_id; ?>" class="admin-btn admin-btn-primary">
                <i class="fas fa-edit"></i> Editar Inscripción
            </a>

            <?php if ($inscripcion['estado'] !== 'cancelada'): ?>
            <form method="POST" action="update-inscription.php" class="action-form">
                <input type="hidden" name="id" value="<?php echo $inscripcion_id; ?>">
                <input type="hidden" name="action" value="cancelar">
                <button type="submit" class="admin-btn admin-btn-danger"
                        onclick="return confirm('¿Estás seguro de cancelar esta inscripción?');">
                    <i class="fas fa-times"></i> Cancelar Inscripción
                </button>
            </form>
            <?php endif; ?>

            <?php if (!$inscripcion['pago_monto'] && $inscripcion['evento_precio'] > 0): ?>
            <a href="process-payment.php?inscription_id=<?php echo $inscripcion_id; ?>" class="admin-btn admin-btn-success">
                <i class="fas fa-credit-card"></i> Registrar Pago
            </a>
            <?php endif; ?>

            <?php if (isset($inscripcion['asistencia']) && $inscripcion['asistencia'] == 1): ?>
            <a href="generate-certificate.php?id=<?php echo $inscripcion_id; ?>&download=1" class="admin-btn admin-btn-warning">
                <i class="fas fa-certificate"></i> Descargar Certificado
            </a>
            <?php endif; ?>

            <button onclick="printInscription()" class="admin-btn admin-btn-secondary">
                <i class="fas fa-print"></i> Imprimir Detalles
            </button>
        </div>
    </div>
</div>

<script>
function printInscription() {
    window.print();
}

// Auto-refrescar cada minuto para ver actualizaciones
setTimeout(() => {
    window.location.reload();
}, 60000); // 1 minuto
</script>

<?php include '../../includes/admin-footer.php'; ?>
