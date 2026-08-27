<?php
// modules/admin/cancel-event.php
require_once '../../config/config.php';
require_once '../../config/database.php';


// session_start(); removed as it's handled in config.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'organizador'])) {
    header('Location: ../auth/login.php');
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: events.php?error=ID de evento inválido');
    exit();
}

$event_id = intval($_GET['id']);
$db = getDB();

// Verificar que el evento existe
$check_stmt = $db->prepare("SELECT id, nombre, estado FROM eventos WHERE id = ?");
$check_stmt->bind_param("i", $event_id);
$check_stmt->execute();
$result = $check_stmt->get_result();
$evento = $result->fetch_assoc();
$check_stmt->close();

if (!$evento) {
    header('Location: events.php?error=Evento no encontrado');
    exit();
}

if ($evento['estado'] === 'cancelado') {
     header('Location: events.php?error=El evento ya está cancelado');
     exit();
}

// Proceder a cancelar
$update_stmt = $db->prepare("UPDATE eventos SET estado = 'cancelado' WHERE id = ?");
$update_stmt->bind_param("i", $event_id);

if ($update_stmt->execute()) {
    // Audit Logging (Opcional, pero recomendado)
    // $log_stmt = $db->prepare("INSERT INTO logs_sistema (id_usuario, accion, modulo, descripcion) VALUES (?, 'cancelar_evento', 'admin', ?)");
    // $desc = "Canceló el evento: " . $evento['nombre'];
    // $log_stmt->bind_param("is", $_SESSION['user_id'], $desc);
    // $log_stmt->execute();

    header('Location: events.php?msg=Evento cancelado exitosamente');
} else {
    header('Location: events.php?error=Error al cancelar el evento: ' . $db->error);
}

$update_stmt->close();
?>
