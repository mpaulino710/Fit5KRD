<?php
// includes/admin-header.php
// Header específico para el panel de administración

// Usar __DIR__ para obtener el directorio actual del archivo
$root_dir = dirname(__DIR__); // Sube un nivel desde includes/
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME . ' Admin' . (isset($page_title) ? ' | ' . $page_title : ' | Gestión de Eventos'); ?></title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/print.css">
    <style>
        /* Estilos temporales hasta que se cargue el CSS completo */
        .admin-body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            background: #f8f9fa;
        }

        .admin-container {
            display: flex;
            min-height: 100vh;
        }

        .admin-sidebar {
            width: 250px;
            background: #2a004a;
            color: white;
            position: fixed;
            height: 100vh;
        }

        .admin-main {
            flex: 1;
            margin-left: 100px;
            padding: 1rem 2rem;
        }
    </style>
</head>
<body class="admin-body">
    <?php include 'admin-sidebar.php'; ?>
    <div class="admin-container">
        <div class="admin-main">
