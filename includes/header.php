<?php
// Archivo: includes/header.php
// Usar __DIR__ para obtener el directorio actual del archivo
$root_dir = dirname(__DIR__); // Sube un nivel desde includes/

// Verificar si el usuario está logueado y cargar su foto si no está en sesión
if (isset($_SESSION['user_id']) && !isset($_SESSION['user_photo'])) {
    // Cargar configuración de base de datos usando ruta absoluta
    require_once $root_dir . '/config/database.php';
    $db = getDB();
    
    // Solo intentar obtener la foto si tenemos conexión a BD
    if ($db) {
        $stmt = $db->prepare("SELECT foto_perfil FROM usuarios WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result) {
                $user_data = $result->fetch_assoc();
                $_SESSION['user_photo'] = $user_data['foto_perfil'] ?? null;
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME . (isset($page_title) ? ' | ' . $page_title : ''); ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Estilos temporales para el container hasta que se cargue el CSS completo */
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }
        
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            font-family: 'Poppins', sans-serif;
            margin: 0;
            background: #f8f9fa;
        }
        
        main {
            flex: 1;
            padding: 2rem 0;
        }
    </style>
</head>
<body>
    <?php include 'navigation.php'; ?>
    <main class="container">