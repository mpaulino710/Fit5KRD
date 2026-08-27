<?php
// modules/admin/process-kit.php
header('Content-Type: application/json');
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../includes/SMTPMailer.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'organizador'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $inscripcion_id = intval($_POST['inscripcion_id'] ?? 0);

    if ($action === 'marcar_entregado' && $inscripcion_id > 0) {
        $db = getDB();

        // Obtener la información necesaria para el correo
        $query = "SELECT 
                    i.*,
                    u.nombre as usuario_nombre,
                    u.apellido as usuario_apellido,
                    u.email as usuario_email,
                    e.nombre as evento_nombre
                  FROM inscripciones i
                  LEFT JOIN usuarios u ON i.id_usuario = u.id
                  JOIN eventos e ON i.id_evento = e.id
                  WHERE i.id = ?";
                  
        $stmt = $db->prepare($query);
        $stmt->bind_param("i", $inscripcion_id);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows > 0) {
            $data = $res->fetch_assoc();
            $stmt->close();

            // Actualizar estado del kit
            $stmt_update = $db->prepare("UPDATE inscripciones SET kit_entregado = 1, fecha_entrega_kit = NOW() WHERE id = ?");
            $stmt_update->bind_param("i", $inscripcion_id);
            
            if ($stmt_update->execute()) {
                // Enviar Correo
                $email_to = $data['usuario_email'] ?: $data['email'];
                $nombre_mostrar = $data['usuario_nombre'] ? ($data['usuario_nombre'] . ' ' . $data['usuario_apellido']) : ($data['nombre'] . ' ' . $data['apellido']);
                
                if ($email_to) {
                    $mailer = new SMTPMailer();
                    $subject = "¡Kit Entregado! - " . $data['evento_nombre'];
                    
                    $message = "
                    <html>
                    <head>
                        <style>
                            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                            .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
                            .header { background-color: #28a745; color: white; padding: 15px; text-align: center; border-radius: 5px 5px 0 0; }
                            .content { padding: 20px; text-align: center; }
                            .details { text-align: left; margin: 20px 0; background-color: #f9f9f9; padding: 15px; border-radius: 5px; border-left: 4px solid #28a745; }
                            .footer { margin-top: 20px; text-align: center; font-size: 0.8em; color: #777; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h2>¡Kit Entregado Exitosamente!</h2>
                            </div>
                            <div class='content'>
                                <p>Hola <strong>{$nombre_mostrar}</strong>,</p>
                                <p>Te confirmamos de manera oficial que tu kit para el evento <strong>{$data['evento_nombre']}</strong> te ha sido entregado.</p>
                                
                                <div class='details'>
                                    <p><strong>Evento:</strong> {$data['evento_nombre']}</p>
                                    <p><strong>Dorsal / N° Corredor:</strong> " . ($data['numero_corredor'] ?: 'N/A') . "</p>
                                    <p><strong>Talla Camiseta:</strong> " . ($data['talla_camiseta'] ?: 'No especificada') . "</p>
                                    <p><strong>Fecha de Entrega:</strong> " . date('d/m/Y h:i A') . "</p>
                                </div>
                                <p>¡Te esperamos en la línea de meta, mucho éxito!</p>
                            </div>
                            <div class='footer'>
                                <p>Este es un correo automático, por favor no respondas a este mensaje.</p>
                                <p>&copy; " . date('Y') . " " . SITE_NAME . ". Todos los derechos reservados.</p>
                            </div>
                        </div>
                    </body>
                    </html>
                    ";
                    
                    $mailer->send($email_to, $subject, $message);
                }

                // Log del sistema (opcional pero recomendado)
                $db->query("INSERT INTO logs_sistema (id_usuario, accion, modulo, descripcion) VALUES ({$_SESSION['user_id']}, 'entregar_kit', 'inscripciones', 'Kit entregado para inscripción #$inscripcion_id')");

                echo json_encode(['success' => true, 'message' => 'Kit marcado como entregado y correo enviado.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al actualizar base de datos.']);
            }
            $stmt_update->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Inscripción no encontrada.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Petición inválida.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
}
?>
