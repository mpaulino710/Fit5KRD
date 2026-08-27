<?php
// modules/admin/delete-news.php
require_once '../../config/config.php';
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['admin', 'organizador'])) {
    header('Location: ../auth/login.php');
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0) {
    $db = getDB();
    
    // Fetch image path to clean up
    $stmt = $db->prepare("SELECT imagen_destacada FROM noticias WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $noticia = $result->fetch_assoc();
    $stmt->close();

    if ($noticia) {
        if (!empty($noticia['imagen_destacada']) && file_exists('../../' . $noticia['imagen_destacada'])) {
            @unlink('../../' . $noticia['imagen_destacada']);
        }

        $stmtDelete = $db->prepare("DELETE FROM noticias WHERE id = ?");
        $stmtDelete->bind_param("i", $id);
        $stmtDelete->execute();
        $stmtDelete->close();
    }
}

header('Location: news.php?deleted=1');
exit();
