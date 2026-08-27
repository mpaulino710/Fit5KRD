<?php
// modules/admin/update-payment.php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/SMTPMailer.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'organizador'])) {
    header('Location: ../auth/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDB();
    $pago_id = intval($_POST['id']);
    $action = $_POST['action'];
    
    $nuevo_estado = '';
    if ($action === 'completar') $nuevo_estado = 'completado';
    elseif ($action === 'reembolsar') $nuevo_estado = 'reembolsado';
    elseif ($action === 'fallido') $nuevo_estado = 'fallido';
    
    if ($nuevo_estado) {
        // Obtener datos del pago para el correo y log
        $query = "SELECT 
                    p.*, 
                    i.id as inscription_id,
                    i.numero_corredor,
                    u.nombre as usuario_nombre, 
                    u.email as usuario_email,
                    e.nombre as evento_nombre,
                    e.fecha_evento,
                    e.ubicacion
                  FROM pagos p
                  JOIN inscripciones i ON p.id_inscripcion = i.id
                  JOIN usuarios u ON i.id_usuario = u.id
                  JOIN eventos e ON i.id_evento = e.id
                  WHERE p.id = ?";
                  
        $stmt = $db->prepare($query);
        $stmt->bind_param("i", $pago_id);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($res->num_rows > 0) {
            $data = $res->fetch_assoc();
            $stmt->close();
            
            // Actualizar estado
            $stmt = $db->prepare("UPDATE pagos SET estado = ?, fecha_pago = NOW() WHERE id = ?");
            $stmt->bind_param("si", $nuevo_estado, $pago_id);
            
            if ($stmt->execute()) {
                // Si es completado, actualizar inscripción y ENVIAR CORREO
                if ($nuevo_estado === 'completado') {
                    $db->query("UPDATE inscripciones SET estado = 'confirmada' WHERE id = " . $data['inscription_id']);
                    
                    // Enviar Correo
                    $mailer = new SMTPMailer();
                    $subject = "Pago Completado - " . $data['evento_nombre'];
                    
                    // Generar QR Data (JSON)
                    $qrData = json_encode([
                        'id' => $data['inscription_id'],
                        'e' => $data['evento_nombre'],
                        'c' => $data['usuario_nombre'],
                        'd' => $data['numero_corredor'] ?: 'Pendiente'
                    ]);
                    $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($qrData);
                    
                    $message = "
                    <html>
                    <head>
                        <style>
                            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                            .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
                            .header { background-color: #007bff; color: white; padding: 15px; text-align: center; border-radius: 5px 5px 0 0; }
                            .content { padding: 20px; text-align: center; }
                            .details { text-align: left; margin: 20px 0; background-color: #f9f9f9; padding: 15px; border-radius: 5px; }
                            .footer { margin-top: 20px; text-align: center; font-size: 0.8em; color: #777; }
                            .qr-code { margin: 20px 0; }
                            .qr-code img { border: 1px solid #ddd; padding: 5px; border-radius: 5px; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h2>¡Pago Completado!</h2>
                            </div>
                            <div class='content'>
                                <p>Hola <strong>{$data['usuario_nombre']}</strong>,</p>
                                <p>Tu pago para el evento <strong>{$data['evento_nombre']}</strong> ha sido procesado exitosamente.</p>
                                
                                <div class='qr-code'>
                                    <p>Aquí tienes tu código de acceso:</p>
                                    <img src='{$qrUrl}' alt='Código QR de Acceso'>
                                </div>
                                
                                <div class='details'>
                                    <p><strong>Evento:</strong> {$data['evento_nombre']}</p>
                                    <p><strong>Fecha:</strong> " . date('d/m/Y h:i A', strtotime($data['fecha_evento'])) . "</p>
                                    <p><strong>Ubicación:</strong> {$data['ubicacion']}</p>
                                    <p><strong>Monto Pagado:</strong> $" . number_format($data['monto'], 2) . "</p>
                                    <p><strong>Número de Corredor:</strong> " . ($data['numero_corredor'] ?: 'Pendiente asignación') . "</p>
                                </div>
                                
                                <p>Por favor, presenta el código QR adjunto al llegar al evento.</p>
                            </div>
                            <div class='footer'>
                                <p>Este es un correo automático, por favor no respondas a este mensaje.</p>
                                <p>&copy; " . date('Y') . " " . SITE_NAME . ". Todos los derechos reservados.</p>
                            </div>
                        </div>
                    </body>
                    </html>
                    ";
                    
                    $mailer->send($data['usuario_email'], $subject, $message);
                }
                
                // Log
                $log_desc = "Actualizó pago #{$pago_id} a estado: {$nuevo_estado} desde detalles";
                $db->query("INSERT INTO logs_sistema (id_usuario, accion, modulo, descripcion) VALUES ({$_SESSION['user_id']}, 'actualizar_pago', 'pagos', '$log_desc')");
            }
            $stmt->close();
        }
    }
}

header('Location: view-payment.php?id=' . $pago_id);
exit();
?>
