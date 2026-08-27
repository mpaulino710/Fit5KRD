<?php
// modules/admin/process-payment.php
require_once '../../config/config.php';
require_once '../../config/database.php';


// session_start(); removed as it's handled in config.php
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'organizador'])) {
    header('Location: ../auth/login.php');
    exit();
}

$db = getDB();

// Determinar si es por ID de pago o ID de inscripción
if (isset($_GET['id'])) {
    // Es un ID de pago existente
    $pago_id = intval($_GET['id']);
    $mode = 'existing';

    $query = "SELECT
                p.*,
                i.numero_corredor,
                i.id_usuario,
                i.id_evento,
                u.nombre as usuario_nombre,
                u.apellido as usuario_apellido,
                u.email as usuario_email,
                e.nombre as evento_nombre,
                e.precio as evento_precio
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

    $inscripcion_id = $pago['id_inscripcion'];

} elseif (isset($_GET['inscription_id'])) {
    // Es un nuevo pago para una inscripción
    $inscripcion_id = intval($_GET['inscription_id']);
    $mode = 'new';

    $query = "SELECT
                i.*,
                u.nombre as usuario_nombre,
                u.apellido as usuario_apellido,
                u.email as usuario_email,
                e.nombre as evento_nombre,
                e.precio as evento_precio,
                p.id as pago_id,
                p.estado as pago_estado,
                p.monto as pago_monto
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

    // Verificar si ya existe un pago
    if ($inscripcion['pago_id']) {
        header('Location: view-payment.php?id=' . $inscripcion['pago_id']);
        exit();
    }

    $pago = null;
} else {
    header('Location: payments.php');
    exit();
}

// Procesar el pago
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $metodo_pago = $_POST['metodo_pago'];
    $monto = $_POST['monto'];
    $estado = $_POST['estado'];
    $transaccion_id = $_POST['transaccion_id'] ?? '';
    $referencia_pago = $_POST['referencia_pago'] ?? '';
    $detalles = $_POST['detalles'] ?? '';

    // Validaciones
    if (!is_numeric($monto) || $monto <= 0) {
        $error = 'El monto debe ser un número positivo';
    } else {
        if ($mode === 'existing') {
            // Actualizar pago existente
            $stmt = $db->prepare("UPDATE pagos SET
                metodo_pago = ?,
                monto = ?,
                estado = ?,
                transaccion_id = ?,
                referencia_pago = ?,
                detalles = ?,
                fecha_pago = NOW()
                WHERE id = ?");

            $stmt->bind_param("sdssssi",
                $metodo_pago,
                $monto,
                $estado,
                $transaccion_id,
                $referencia_pago,
                $detalles,
                $pago_id
            );

            if ($stmt->execute()) {
                $success = 'Pago actualizado correctamente';

                // Actualizar estado de la inscripción si el pago se completa
                // Actualizar estado de la inscripción si el pago se completa
                if ($estado === 'completado') {
                    $db->query("UPDATE inscripciones SET estado = 'confirmada' WHERE id = {$inscripcion_id}");
                    
                    // Enviar Correo
                    require_once '../../includes/SMTPMailer.php';
                    $mailer = new SMTPMailer();
                    $subject = "Pago Completado - " . $pago['evento_nombre'];
                    
                     // Generar QR Data (JSON)
                    $qrData = json_encode([
                        'id' => $inscripcion_id,
                        'e' => $pago['evento_nombre'],
                        'c' => $pago['usuario_nombre'],
                        'd' => $pago['numero_corredor'] ?: 'Pendiente'
                    ]);
                    $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($qrData);

                    $message_content = "
                        <p>Hola <strong>{$pago['usuario_nombre']}</strong>,</p>
                        <p>Tu pago para el evento <strong>{$pago['evento_nombre']}</strong> ha sido procesado exitosamente.</p>
                        
                        <div class='qr-code' style='text-align: center; margin: 20px 0;'>
                            <p>Aquí tienes tu código de acceso:</p>
                            <img src='{$qrUrl}' alt='Código QR de Acceso' style='border: 1px solid #ddd; padding: 5px; border-radius: 5px;'>
                        </div>
                        
                        <div class='details' style='background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                            <p><strong>Evento:</strong> {$pago['evento_nombre']}</p>
                            <p><strong>Fecha:</strong> " . date('d/m/Y h:i A', strtotime($pago['fecha_evento'] ?? 'now')) . "</p>
                            <p><strong>Monto Pagado:</strong> $" . number_format($monto, 2) . "</p>
                            <p><strong>Número de Corredor:</strong> " . ($pago['numero_corredor'] ?: 'Pendiente asignación') . "</p>
                        </div>
                        
                        <p>Por favor, presenta el código QR adjunto al llegar al evento.</p>
                    ";

                     $message = "
                    <html>
                    <head>
                        <style>
                            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                            .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
                            .header { background-color: #007bff; color: white; padding: 15px; text-align: center; border-radius: 5px 5px 0 0; }
                            .content { padding: 20px; }
                            .footer { margin-top: 20px; text-align: center; font-size: 0.8em; color: #777; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h2>¡Pago Completado!</h2>
                            </div>
                            <div class='content'>
                                $message_content
                            </div>
                            <div class='footer'>
                                <p>Este es un correo automático, por favor no respondas a este mensaje.</p>
                                <p>&copy; " . date('Y') . " " . SITE_NAME . ". Todos los derechos reservados.</p>
                            </div>
                        </div>
                    </body>
                    </html>
                    ";
                    
                    $mailer->send($pago['usuario_email'], $subject, $message);
                }

                // Registrar en logs
                $log_desc = "Actualizó pago #{$pago_id} a estado: {$estado}";
                $db->query("INSERT INTO logs_sistema (id_usuario, accion, modulo, descripcion)
                           VALUES ({$_SESSION['user_id']}, 'actualizar_pago', 'pagos', '$log_desc')");
            } else {
                $error = 'Error al actualizar el pago: ' . $stmt->error;
            }
            $stmt->close();

        } else {
            // Crear nuevo pago
            $stmt = $db->prepare("INSERT INTO pagos
                (id_inscripcion, metodo_pago, monto, estado, transaccion_id, referencia_pago, detalles, fecha_pago)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");

            $stmt->bind_param("isdssss",
                $inscripcion_id,
                $metodo_pago,
                $monto,
                $estado,
                $transaccion_id,
                $referencia_pago,
                $detalles
            );

            if ($stmt->execute()) {
                $pago_id = $stmt->insert_id;
                $success = 'Pago registrado correctamente';

                // Actualizar estado de la inscripción si el pago se completa
                if ($estado === 'completado') {
                    $db->query("UPDATE inscripciones SET estado = 'confirmada' WHERE id = {$inscripcion_id}");
                    
                    // Enviar Correo
                    require_once '../../includes/SMTPMailer.php';
                    $mailer = new SMTPMailer();
                    $subject = "Pago Completado - " . $inscripcion['evento_nombre'];
                    
                     // Generar QR Data (JSON)
                    $qrData = json_encode([
                        'id' => $inscripcion_id,
                        'e' => $inscripcion['evento_nombre'],
                        'c' => $inscripcion['usuario_nombre'],
                        'd' => $inscripcion['numero_corredor'] ?: 'Pendiente'
                    ]);
                    $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($qrData);

                    $message_content = "
                        <p>Hola <strong>{$inscripcion['usuario_nombre']}</strong>,</p>
                        <p>Tu pago para el evento <strong>{$inscripcion['evento_nombre']}</strong> ha sido procesado exitosamente.</p>
                        
                        <div class='qr-code' style='text-align: center; margin: 20px 0;'>
                            <p>Aquí tienes tu código de acceso:</p>
                            <img src='{$qrUrl}' alt='Código QR de Acceso' style='border: 1px solid #ddd; padding: 5px; border-radius: 5px;'>
                        </div>
                        
                        <div class='details' style='background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                            <p><strong>Evento:</strong> {$inscripcion['evento_nombre']}</p>
                            <p><strong>Monto Pagado:</strong> $" . number_format($monto, 2) . "</p>
                            <p><strong>Número de Corredor:</strong> " . ($inscripcion['numero_corredor'] ?: 'Pendiente asignación') . "</p>
                        </div>
                        
                        <p>Por favor, presenta el código QR adjunto al llegar al evento.</p>
                    ";

                     $message = "
                    <html>
                    <head>
                        <style>
                            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                            .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
                            .header { background-color: #007bff; color: white; padding: 15px; text-align: center; border-radius: 5px 5px 0 0; }
                            .content { padding: 20px; }
                            .footer { margin-top: 20px; text-align: center; font-size: 0.8em; color: #777; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h2>¡Pago Completado!</h2>
                            </div>
                            <div class='content'>
                                $message_content
                            </div>
                            <div class='footer'>
                                <p>Este es un correo automático, por favor no respondas a este mensaje.</p>
                                <p>&copy; " . date('Y') . " " . SITE_NAME . ". Todos los derechos reservados.</p>
                            </div>
                        </div>
                    </body>
                    </html>
                    ";
                    
                    $mailer->send($inscripcion['usuario_email'], $subject, $message);
                }

                // Registrar en logs
                $log_desc = "Registró nuevo pago #{$pago_id} para inscripción #{$inscripcion_id}";
                $db->query("INSERT INTO logs_sistema (id_usuario, accion, modulo, descripcion)
                           VALUES ({$_SESSION['user_id']}, 'registrar_pago', 'pagos', '$log_desc')");
            } else {
                $error = 'Error al registrar el pago: ' . $stmt->error;
            }
            $stmt->close();
        }

        if ($success && $mode === 'new') {
            // Redirigir al detalle del pago recién creado
            header('Location: view-payment.php?id=' . $pago_id);
            exit();
        }
    }
}

include '../../includes/admin-header.php';
?>

<div class="admin-main">
    <!-- Header -->
    <div class="admin-header">
        <div>
            <h1><i class="fas fa-credit-card"></i>
                <?php echo $mode === 'existing' ? 'Procesar Pago' : 'Registrar Nuevo Pago'; ?>
            </h1>
            <p class="payment-subtitle">
                <?php if ($mode === 'existing'): ?>
                ID Pago: #<?php echo $pago['id']; ?> •
                Estado actual: <?php echo ucfirst($pago['estado']); ?>
                <?php else: ?>
                Inscripción: #<?php echo $inscripcion_id; ?> •
                Corredor: <?php echo htmlspecialchars($inscripcion['usuario_nombre'] . ' ' . $inscripcion['usuario_apellido']); ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="admin-header-actions">
            <?php if ($mode === 'existing'): ?>
            <a href="view-payment.php?id=<?php echo $pago_id; ?>" class="admin-btn admin-btn-secondary">
                <i class="fas fa-eye"></i> Ver Detalles
            </a>
            <?php else: ?>
            <a href="view-inscription.php?id=<?php echo $inscripcion_id; ?>" class="admin-btn admin-btn-secondary">
                <i class="fas fa-eye"></i> Ver Inscripción
            </a>
            <?php endif; ?>
            <a href="payments.php" class="admin-btn admin-btn-outline">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <!-- Notificaciones -->
    <?php if ($error): ?>
    <div class="admin-alert admin-alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <span><?php echo $error; ?></span>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="admin-alert admin-alert-success">
        <i class="fas fa-check-circle"></i>
        <span><?php echo $success; ?></span>
    </div>
    <?php endif; ?>

    <!-- Resumen -->
    <div class="dashboard-grid">
        <!-- Información del Pago/Inscripción -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h2><i class="fas fa-info-circle"></i> Información</h2>
            </div>

            <div class="payment-info-card">
                <?php if ($mode === 'existing'): ?>
                <div class="info-item">
                    <span class="info-label">Corredor:</span>
                    <span class="info-value"><?php echo htmlspecialchars($pago['usuario_nombre'] . ' ' . $pago['usuario_apellido']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Evento:</span>
                    <span class="info-value"><?php echo htmlspecialchars($pago['evento_nombre']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Precio Evento:</span>
                    <span class="info-value">$<?php echo number_format($pago['evento_precio'], 2); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Número Corredor:</span>
                    <span class="info-value"><?php echo $pago['numero_corredor'] ?: 'Pendiente'; ?></span>
                </div>
                <?php else: ?>
                <div class="info-item">
                    <span class="info-label">Corredor:</span>
                    <span class="info-value"><?php echo htmlspecialchars($inscripcion['usuario_nombre'] . ' ' . $inscripcion['usuario_apellido']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Evento:</span>
                    <span class="info-value"><?php echo htmlspecialchars($inscripcion['evento_nombre']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Precio Evento:</span>
                    <span class="info-value">$<?php echo number_format($inscripcion['evento_precio'], 2); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Número Corredor:</span>
                    <span class="info-value"><?php echo $inscripcion['numero_corredor'] ?: 'Pendiente'; ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Formulario de Pago -->
        <div class="admin-card">
            <div class="admin-card-header">
                <h2><i class="fas fa-edit"></i> Datos del Pago</h2>
            </div>

            <form method="POST" action="" class="payment-form">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="metodo_pago"><i class="fas fa-credit-card"></i> Método de Pago *</label>
                        <select id="metodo_pago" name="metodo_pago" class="form-control" required>
                            <option value="">Seleccionar método</option>
                            <option value="tarjeta" <?php echo ($mode === 'existing' && $pago['metodo_pago'] === 'tarjeta') ? 'selected' : ''; ?>>Tarjeta</option>
                            <option value="transferencia" <?php echo ($mode === 'existing' && $pago['metodo_pago'] === 'transferencia') ? 'selected' : ''; ?>>Transferencia</option>
                            <option value="efectivo" <?php echo ($mode === 'existing' && $pago['metodo_pago'] === 'efectivo') ? 'selected' : ''; ?>>Efectivo</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="monto"><i class="fas fa-dollar-sign"></i> Monto ($) *</label>
                        <input type="number" id="monto" name="monto"
                               class="form-control" step="0.01" min="0" required
                               value="<?php echo $mode === 'existing' ? number_format($pago['monto'], 2) : number_format($inscripcion['evento_precio'], 2); ?>">
                    </div>

                    <div class="form-group">
                        <label for="estado"><i class="fas fa-filter"></i> Estado *</label>
                        <select id="estado" name="estado" class="form-control" required>
                            <option value="pendiente" <?php echo ($mode === 'existing' && $pago['estado'] === 'pendiente') ? 'selected' : ''; ?>>Pendiente</option>
                            <option value="completado" <?php echo ($mode === 'existing' && $pago['estado'] === 'completado') ? 'selected' : ''; ?>>Completado</option>
                            <option value="fallido" <?php echo ($mode === 'existing' && $pago['estado'] === 'fallido') ? 'selected' : ''; ?>>Fallido</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="transaccion_id"><i class="fas fa-id-card"></i> ID Transacción</label>
                        <input type="text" id="transaccion_id" name="transaccion_id"
                               class="form-control" placeholder="Ej: TXN123456"
                               value="<?php echo $mode === 'existing' ? htmlspecialchars($pago['transaccion_id'] ?? '') : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="referencia_pago"><i class="fas fa-hashtag"></i> Referencia</label>
                        <input type="text" id="referencia_pago" name="referencia_pago"
                               class="form-control" placeholder="Ej: REF789012"
                               value="<?php echo $mode === 'existing' ? htmlspecialchars($pago['referencia_pago'] ?? '') : ''; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="detalles"><i class="fas fa-sticky-note"></i> Detalles Adicionales</label>
                    <textarea id="detalles" name="detalles" class="form-control" rows="3"
                              placeholder="Observaciones, notas, etc."><?php echo $mode === 'existing' ? htmlspecialchars($pago['detalles'] ?? '') : ''; ?></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="admin-btn admin-btn-primary">
                        <i class="fas fa-save"></i>
                        <?php echo $mode === 'existing' ? 'Actualizar Pago' : 'Registrar Pago'; ?>
                    </button>
                    <?php if ($mode === 'existing'): ?>
                    <a href="view-payment.php?id=<?php echo $pago_id; ?>" class="admin-btn admin-btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                    <?php else: ?>
                    <a href="view-inscription.php?id=<?php echo $inscripcion_id; ?>" class="admin-btn admin-btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Instrucciones -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h2><i class="fas fa-question-circle"></i> Instrucciones</h2>
        </div>

        <div class="instructions-grid">
            <div class="instruction-item">
                <i class="fas fa-check-circle"></i>
                <h4>Completado</h4>
                <p>El pago se ha realizado exitosamente y se confirma la inscripción.</p>
            </div>

            <div class="instruction-item">
                <i class="fas fa-clock"></i>
                <h4>Pendiente</h4>
                <p>El pago está en proceso o pendiente de confirmación.</p>
            </div>

            <div class="instruction-item">
                <i class="fas fa-times-circle"></i>
                <h4>Fallido</h4>
                <p>El pago no se pudo completar por algún error o fue rechazado.</p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.payment-form');
    const montoInput = document.getElementById('monto');

    // Auto-completar ID de transacción si está vacío
    const transaccionInput = document.getElementById('transaccion_id');
    if (!transaccionInput.value) {
        transaccionInput.value = 'TXN' + Date.now().toString().substr(-8);
    }

    // Auto-completar referencia si está vacía
    const referenciaInput = document.getElementById('referencia_pago');
    if (!referenciaInput.value) {
        referenciaInput.value = 'REF' + Math.floor(Math.random() * 1000000).toString().padStart(6, '0');
    }

    // Validar monto positivo
    form.addEventListener('submit', function(e) {
        const monto = parseFloat(montoInput.value);

        if (monto <= 0) {
            e.preventDefault();
            alert('El monto debe ser mayor a cero');
            montoInput.focus();
            return false;
        }

        return true;
    });

    // Formatear monto automáticamente
    montoInput.addEventListener('blur', function() {
        const value = parseFloat(this.value);
        if (!isNaN(value)) {
            this.value = value.toFixed(2);
        }
    });
});
</script>

<?php include '../../includes/admin-footer.php'; ?>
