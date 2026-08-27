<?php
// Archivo: index.php
require_once 'config/config.php';
require_once 'config/database.php';

// Redireccionar a la página de inicio
header('Location: modules/public/home.php');
exit();
?>