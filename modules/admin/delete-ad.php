<?php
// modules/admin/delete-ad.php
require_once '../../config/config.php';
require_once '../../config/database.php';


// session_start(); removed as it's handled in config.php
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'organizador'])) {
    header('Location: ../auth/login.php');
    exit();
}

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = intval($_GET['id']);
    $db = getDB();
    
    // Get image to delete file
    $stmt = $db->prepare("SELECT imagen_url FROM anuncios WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($ad = $res->fetch_assoc()) {
        $filepath = '../../' . $ad['imagen_url'];
        if (file_exists($filepath)) {
            unlink($filepath);
        }
    }
    $stmt->close();

    $stmt = $db->prepare("DELETE FROM anuncios WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
}

header('Location: ads.php');
exit();
?>
