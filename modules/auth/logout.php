<?php
// modules/auth/logout.php
session_start();

// Registrar logout para auditoría
if (isset($_SESSION['user_id'], $_SESSION['user_email'])) {
    $user_id = $_SESSION['user_id'];
    $user_email = $_SESSION['user_email'];
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'IP desconocida';
    
    error_log("Logout exitoso: Usuario $user_email (ID: $user_id) desde IP: $ip_address");
}

// Limpiar variables específicas de sesión
$session_keys = [
    'user_id', 'user_name', 'user_email', 'user_type', 'logged_in',
    'login_attempts', 'login_blocked_until', 'register_attempts', 'register_blocked_until'
];

foreach ($session_keys as $key) {
    if (isset($_SESSION[$key])) {
        unset($_SESSION[$key]);
    }
}

// Destruir la sesión
session_unset();

// Eliminar cookie de sesión
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 3600,
        $params["path"], 
        $params["domain"], 
        $params["secure"], 
        $params["httponly"]
    );
}

session_destroy();

// Prevenir cache
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Redireccionar con parámetro anti-cache
header('Location: ../public/home.php?logout=1&nocache=' . time());
exit();
?>