<?php
// modules/admin/view-payment.php
require_once '../../config/config.php';
require_once '../../config/database.php';


// session_start(); removed as it's handled in config.php
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'organizador'])) {
    header('Location: ../auth/login.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: payments.php');
    exit();
}

$db = getDB();
$pago_id = intval($_GET['id']);

// Obtener información del pago
$query = "SELECT
            p.*,
            i.numero_corredor,
            i.estado as inscripcion_estado,
            u.nombre as usuario_nombre,
            u.apellido as usuario_apellido,
            u.email as usuario_email,
            u.telefono as usuario_telefono,
            e.nombre as evento_nombre,
            e.fecha_evento,
            e.ubicacion as evento_ubicacion,
            e.precio as evento_precio,
            e.modalidades as evento_modalidades,
            i.modalidad as inscripcion_modalidad
          FROM pagos p
          JOIN inscripciones i ON p.id_inscripcion = i.id
          JOIN usuarios u ON i.id_usuario = u.id
          JOIN eventos e ON i.id_evento = e.id
          WHERE p.id = ?";

$stmt = $db->prepare($query);
$stmt->bind_param("i", $pago_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: payments.php');
    exit();
}

$pago = $result->fetch_assoc();
$stmt->close();

// Obtener historial del pago si existe
$query_historial = "SELECT * FROM logs_sistema
                    WHERE modulo = 'pagos' AND descripcion LIKE ?
                    ORDER BY fecha DESC";
$stmt_historial = $db->prepare($query_historial);
$search_term = "%pago #{$pago_id}%";
$stmt_historial->bind_param("s", $search_term);
$stmt_historial->execute();
$historial = $stmt_historial->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_historial->close();

// --- Obtener Invitados y Precios ---
$invitados = [];
$query_invitados = "SELECT nombre, apellido, email, modality FROM inscripciones WHERE parent_id = ?";
// Nota: 'modality' column might be 'modalidad' based on previous context. Let's check view.php usage.
// In view.php: `SELECT * FROM inscripciones WHERE parent_id = ?`
// In register.php insert: `modalidad` column.
$query_invitados = "SELECT nombre, apellido, email, modalidad FROM inscripciones WHERE parent_id = ?";
$stmt_inv = $db->prepare($query_invitados);
$stmt_inv->bind_param("i", $pago['id_inscripcion']);
$stmt_inv->execute();
$result_inv = $stmt_inv->get_result();
while ($inv = $result_inv->fetch_assoc()) {
    $invitados[] = $inv;
}
$stmt_inv->close();

// Helper Precios
$modalidades_data = !empty($pago['evento_modalidades']) ? json_decode($pago['evento_modalidades'], true) : [];
$evento_precio_base = floatval($pago['evento_precio']);

function getPrice($mod_name, $base_price, $mods_data) {
    if ($mod_name && !empty($mods_data)) {
        foreach ($mods_data as $m) {
            $mName = is_array($m) ? ($m['nombre'] ?? '') : $m;
            $mPrice = is_array($m) ? ($m['precio'] ?? $base_price) : $base_price;
            if ($mName === $mod_name) {
                return floatval($mPrice);
            }
        }
    }
    return $base_price;
}

// Prepare items for breakdown
$breakdown = [];
// Main User
$breakdown[] = [
    'nombre' => $pago['usuario_nombre'] . ' ' . $pago['usuario_apellido'],
    'tipo' => 'Principal',
    'modalidad' => $pago['inscripcion_modalidad'] ?: 'General',
    'precio' => getPrice($pago['inscripcion_modalidad'], $evento_precio_base, $modalidades_data)
];

// Guests
foreach ($invitados as $inv) {
    $guest_name = $inv['nombre'] . ' ' . $inv['apellido'];
    if (empty(trim($guest_name))) $guest_name = 'Invitado'; // Fallback
    
    $breakdown[] = [
        'nombre' => $guest_name,
        'tipo' => 'Invitado',
        'modalidad' => $inv['modalidad'] ?: 'General',
        'precio' => getPrice($inv['modalidad'], $evento_precio_base, $modalidades_data)
    ];
}

include '../../includes/admin-header.php';
?>

<div class="admin-main">
    <!-- Header -->
    <div class="admin-header">
        <div>
            <h1><i class="fas fa-credit-card"></i> Detalles del Pago</h1>
            <p class="payment-subtitle">
                ID: #<?php echo $pago['id']; ?> •
                Transacción: <?php echo $pago['transaccion_id'] ?: 'No disponible'; ?>
            </p>
        </div>
        <div class="admin-header-actions">
            <a href="payments.php" class="admin-btn admin-btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
            <button onclick="printPayment()" class="admin-btn admin-btn-success">
                <i class="fas fa-print"></i> Imprimir Recibo
            </button>
            <?php if ($pago['estado'] === 'pendiente'): ?>
            <a href="process-payment.php?id=<?php echo $pago_id; ?>" class="admin-btn admin-btn-primary">
                <i class="fas fa-check"></i> Procesar Pago
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Información principal -->
    <div class="dashboard-grid">
        <!-- Resumen del Pago -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h2><i class="fas fa-file-invoice-dollar"></i> Resumen del Pago</h2>
                <?php
                $estado_colors = [
                    'completado' => 'success',
                    'pendiente' => 'warning',
                    'fallido' => 'danger',
                    'reembolsado' => 'secondary'
                ];
                $color = $estado_colors[$pago['estado']] ?? 'secondary';
                ?>
                <span class="badge badge-<?php echo $color; ?>">
                    <?php echo ucfirst($pago['estado']); ?>
                </span>
            </div>

            <div class="payment-summary-card">
                <div class="payment-amount-main">
                    $<?php echo number_format($pago['monto'], 2); ?>
                </div>

                <div class="payment-details">
                    <div class="detail-row">
                        <span class="detail-label">Método de Pago:</span>
                        <span class="detail-value">
                            <span class="badge badge-info">
                                <?php echo ucfirst($pago['metodo_pago']); ?>
                            </span>
                        </span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Fecha del Pago:</span>
                        <span class="detail-value">
                            <?php echo $pago['fecha_pago'] ? date('d/m/Y H:i', strtotime($pago['fecha_pago'])) : 'Pendiente'; ?>
                        </span>
                    </div>

                    <?php if ($pago['fecha_vencimiento']): ?>
                    <div class="detail-row">
                        <span class="detail-label">Vencimiento:</span>
                        <span class="detail-value">
                            <?php echo date('d/m/Y', strtotime($pago['fecha_vencimiento'])); ?>
                            <?php if (strtotime($pago['fecha_vencimiento']) < time()): ?>
                            <span class="badge badge-danger">Vencido</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php endif; ?>

                    <?php if ($pago['transaccion_id']): ?>
                    <div class="detail-row">
                        <span class="detail-label">ID Transacción:</span>
                        <span class="detail-value">
                            <code><?php echo $pago['transaccion_id']; ?></code>
                        </span>
                    </div>
                    <?php endif; ?>

                    <?php if ($pago['referencia_pago']): ?>
                    <div class="detail-row">
                        <span class="detail-label">Referencia:</span>
                        <span class="detail-value">
                            <code><?php echo $pago['referencia_pago']; ?></code>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Breakdown Table -->
            <div class="details-section">
                <h4><i class="fas fa-list-ul"></i> Detalle del Pago</h4>
                <div class="table-responsive" style="margin-top: 1rem;">
                    <table class="table table-sm table-borderless" style="background: var(--bg-light); border-radius: 8px;">
                        <thead style="border-bottom: 1px solid #ddd;">
                            <tr>
                                <th style="padding: 0.8rem;">Participante</th>
                                <th style="padding: 0.8rem;">Tipo</th>
                                <th style="padding: 0.8rem;">Modalidad</th>
                                <th style="text-align: right; padding: 0.8rem;">Importe</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_calc = 0;
                            foreach ($breakdown as $item): 
                                $total_calc += $item['precio'];
                            ?>
                            <tr>
                                <td style="padding: 0.5rem 0.8rem;">
                                    <strong><?php echo htmlspecialchars($item['nombre']); ?></strong>
                                </td>
                                <td style="padding: 0.5rem 0.8rem;">
                                    <span class="badge badge-<?php echo $item['tipo'] === 'Principal' ? 'primary' : 'secondary'; ?>" style="font-size: 0.75rem;">
                                        <?php echo $item['tipo']; ?>
                                    </span>
                                </td>
                                <td style="padding: 0.5rem 0.8rem;">
                                    <?php echo htmlspecialchars($item['modalidad']); ?>
                                </td>
                                <td style="text-align: right; padding: 0.5rem 0.8rem;">
                                    $<?php echo number_format($item['precio'], 2); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <!-- Total Line -->
                            <tr style="border-top: 2px solid #ddd;">
                                <td colspan="3" style="text-align: right; padding: 0.8rem; font-weight: bold;">Total Calculado:</td>
                                <td style="text-align: right; padding: 0.8rem; font-weight: bold; color: var(--primary-color);">
                                    $<?php echo number_format($total_calc, 2); ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <?php if (abs($total_calc - $pago['monto']) > 0.01): ?>
                    <small class="text-muted d-block mt-2" style="font-style: italic;">
                        <i class="fas fa-info-circle"></i> Nota: El monto registrado del pago ($<?php echo number_format($pago['monto'], 2); ?>) difiere del cálculo actual ($<?php echo number_format($total_calc, 2); ?>). Esto puede deberse a descuentos aplicados o cambios de precio posteriores.
                    </small>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Detalles adicionales -->
            <?php if ($pago['detalles']): ?>
            <div class="details-section">
                <h4><i class="fas fa-sticky-note"></i> Detalles Adicionales</h4>
                <div class="payment-notes">
                    <?php echo nl2br(htmlspecialchars($pago['detalles'])); ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Acciones del pago -->
            <div class="payment-actions">
                <form method="POST" action="update-payment.php" class="action-form">
                    <input type="hidden" name="id" value="<?php echo $pago_id; ?>">

                    <div class="action-grid">
                        <?php if ($pago['estado'] === 'pendiente'): ?>
                        <input type="hidden" name="action" value="completar">
                        <button type="submit" class="admin-btn admin-btn-success"
                                onclick="return confirm('¿Marcar este pago como completado?');">
                            <i class="fas fa-check-circle"></i> Marcar como Completado
                        </button>
                        <?php endif; ?>

                        <?php if ($pago['estado'] === 'completado'): ?>
                        <input type="hidden" name="action" value="reembolsar">
                        <button type="submit" class="admin-btn admin-btn-warning"
                                onclick="return confirm('¿Registrar reembolso de este pago?');">
                            <i class="fas fa-undo-alt"></i> Registrar Reembolso
                        </button>
                        <?php endif; ?>

                        <?php if ($pago['estado'] !== 'fallido'): ?>
                        <input type="hidden" name="action" value="fallido">
                        <button type="submit" class="admin-btn admin-btn-danger"
                                onclick="return confirm('¿Marcar este pago como fallido?');">
                            <i class="fas fa-times-circle"></i> Marcar como Fallido
                        </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Información relacionada -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h2><i class="fas fa-link"></i> Información Relacionada</h2>
            </div>

            <!-- Información del Usuario -->
            <div class="related-section">
                <h3><i class="fas fa-user"></i> Información del Usuario</h3>
                <div class="user-info-card">
                    <div class="user-avatar-medium">
                        <?php echo strtoupper(substr($pago['usuario_nombre'], 0, 1) . substr($pago['usuario_apellido'], 0, 1)); ?>
                    </div>
                    <div class="user-details">
                        <h4><?php echo htmlspecialchars($pago['usuario_nombre'] . ' ' . $pago['usuario_apellido']); ?></h4>
                        <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($pago['usuario_email']); ?></p>
                        <p><i class="fas fa-phone"></i> <?php echo $pago['usuario_telefono'] ?: 'No registrado'; ?></p>
                    </div>
                </div>
                <div class="user-actions">
                    <a href="../profile/view.php?email=<?php echo urlencode($pago['usuario_email']); ?>"
                       class="admin-btn admin-btn-outline btn-sm">
                        <i class="fas fa-eye"></i> Ver Perfil
                    </a>
                    <a href="mailto:<?php echo htmlspecialchars($pago['usuario_email']); ?>"
                       class="admin-btn admin-btn-secondary btn-sm">
                        <i class="fas fa-envelope"></i> Contactar
                    </a>
                </div>
            </div>

            <!-- Información del Evento -->
            <div class="related-section">
                <h3><i class="fas fa-calendar-alt"></i> Información del Evento</h3>
                <div class="event-info-card">
                    <h4><?php echo htmlspecialchars($pago['evento_nombre']); ?></h4>
                    <p><i class="fas fa-calendar"></i> <?php echo date('d/m/Y H:i', strtotime($pago['fecha_evento'])); ?></p>
                    <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($pago['evento_ubicacion']); ?></p>
                    <p><i class="fas fa-tag"></i> Precio: $<?php echo number_format($pago['evento_precio'], 2); ?></p>
                </div>
                <div class="event-actions">
                    <a href="../events/view.php?id=<?php echo $pago['id_evento']; ?>"
                       class="admin-btn admin-btn-outline btn-sm">
                        <i class="fas fa-eye"></i> Ver Evento
                    </a>
                </div>
            </div>

            <!-- Información de la Inscripción -->
            <div class="related-section">
                <h3><i class="fas fa-running"></i> Información de la Inscripción</h3>
                <div class="inscription-info-card">
                    <p><strong>Número Corredor:</strong>
                        <?php echo $pago['numero_corredor'] ?: 'Pendiente'; ?>
                    </p>
                    <p><strong>Estado Inscripción:</strong>
                        <span class="badge badge-<?php echo $pago['inscripcion_estado'] === 'confirmada' ? 'success' : 'warning'; ?>">
                            <?php echo ucfirst($pago['inscripcion_estado']); ?>
                        </span>
                    </p>
                </div>
                <div class="inscription-actions">
                    <a href="view-inscription.php?id=<?php echo $pago['id_inscripcion']; ?>"
                       class="admin-btn admin-btn-outline btn-sm">
                        <i class="fas fa-eye"></i> Ver Inscripción
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Historial del Pago -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h2><i class="fas fa-history"></i> Historial del Pago</h2>
        </div>

        <?php if (!empty($historial)): ?>
        <div class="timeline">
            <?php foreach ($historial as $log): ?>
            <div class="timeline-item">
                <div class="timeline-date">
                    <?php echo date('d/m/Y H:i', strtotime($log['fecha'])); ?>
                </div>
                <div class="timeline-content">
                    <h4><i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($log['accion']); ?></h4>
                    <p><?php echo htmlspecialchars($log['descripcion']); ?></p>
                    <?php if ($log['ip_address']): ?>
                    <small>IP: <?php echo $log['ip_address']; ?></small>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-history"></i>
            <h3>No hay historial registrado</h3>
            <p>Este pago aún no tiene actividad registrada</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Documentación -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h2><i class="fas fa-paperclip"></i> Documentación</h2>
        </div>

        <div class="documentation-grid">
            <div class="document-item">
                <i class="fas fa-file-invoice"></i>
                <h4>Recibo de Pago</h4>
                <p>Documento oficial del pago</p>
                <button onclick="generateReceipt()" class="admin-btn admin-btn-outline btn-sm">
                    <i class="fas fa-download"></i> Generar Recibo
                </button>
            </div>

            <div class="document-item">
                <i class="fas fa-file-contract"></i>
                <h4>Comprobante</h4>
                <p>Comprobante de transacción</p>
                <button onclick="generateProof()" class="admin-btn admin-btn-outline btn-sm">
                    <i class="fas fa-download"></i> Generar Comprobante
                </button>
            </div>

            <div class="document-item">
                <i class="fas fa-chart-bar"></i>
                <h4>Reporte</h4>
                <p>Reporte detallado del pago</p>
                <button onclick="generateReport()" class="admin-btn admin-btn-outline btn-sm">
                    <i class="fas fa-download"></i> Generar Reporte
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function printPayment() {
    window.print();
}

function generateReceipt() {
    window.location.href = 'generate-receipt.php?id=<?php echo $pago_id; ?>';
}

function generateProof() {
    window.location.href = 'generate-proof.php?id=<?php echo $pago_id; ?>';
}

function generateReport() {
    window.location.href = 'generate-payment-report.php?id=<?php echo $pago_id; ?>';
}
</script>

<?php include '../../includes/admin-footer.php'; ?>
