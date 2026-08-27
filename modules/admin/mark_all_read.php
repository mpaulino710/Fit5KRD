<?php
// modules/admin/mark_all_read.php
require_once '../../config/config.php';
require_once '../../config/database.php';

// Verificar autenticación
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'organizador'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'marcar_todas_leidas') {
    $result = $db->query("UPDATE notificaciones SET leido = 1, fecha_lectura = NOW() WHERE leido = 0");

    if ($result) {
        $affected_rows = $db->affected_rows;
        echo json_encode([
            'success' => true,
            'message' => "{$affected_rows} notificaciones marcadas como leídas"
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Error al actualizar las notificaciones'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Acción no válida'
    ]);
}
