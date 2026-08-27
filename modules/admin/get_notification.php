<?php
// modules/admin/get_notification.php
require_once '../../config/config.php';
require_once '../../config/database.php';

// Verificar autenticación
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'organizador'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

if (!isset($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'ID no especificado']);
    exit();
}

$db = getDB();
$notification_id = intval($_GET['id']);

$query = "SELECT
            n.*,
            u.nombre as usuario_nombre,
            u.apellido as usuario_apellido,
            u.email as usuario_email
          FROM notificaciones n
          LEFT JOIN usuarios u ON n.id_usuario = u.id
          WHERE n.id = ?";

$stmt = $db->prepare($query);
$stmt->bind_param("i", $notification_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Notificación no encontrada']);
    exit();
}

$notification = $result->fetch_assoc();
$stmt->close();

header('Content-Type: application/json');
echo json_encode(['success' => true, 'notification' => $notification]);
