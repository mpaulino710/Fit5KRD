<?php
// modules/api/track_ad_click.php
require_once '../../config/config.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = intval($_GET['id']);
    $db = getDB();
    
    // Increment clicks
    $stmt = $db->prepare("UPDATE anuncios SET clicks = clicks + 1 WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['status' => 'success']);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database error']);
    }
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid ID']);
}
?>
