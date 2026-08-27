<?php
// Archivo: config/config.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configurar zona horaria por defecto
date_default_timezone_set('America/Santo_Domingo');


define('SITE_NAME', 'Fit5Krd');
define('SITE_URL', 'https://fit5krd.com');
define('ADMIN_EMAIL', 'fit5krd@pasohas.com');

// Configuración de la base de datos
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'zumba');
define('DB_PASS', 'Zumba5K');
define('DB_NAME', 'fit5k_db');

// Configuración de seguridad
define('PEPPER', 'fit5k_secret_pepper_2024');
// reCAPTCHA v3
define('RECAPTCHA_SITE_KEY', '6LcpKlAsAAAAAOSMT8SnFDDrpC84Bx5CveE29J9g');
define('RECAPTCHA_SECRET_KEY', '6LcpKlAsAAAAACPgV0NNXQD_-V3ClOf1_dDIMA5L');
define('RECAPTCHA_SCORE_THRESHOLD', 0.5); // Umbral de puntuación (0.0-1.0)

// Configuración SMTP
define('SMTP_HOST', 'mail.pasohas.com');
define('SMTP_PORT', 465); // 587 para TLS, 465 para SSL
define('SMTP_USER', 'fit5krd@pasohas.com');
define('SMTP_PASS', 'Fit2026#@5k');
define('SMTP_FROM_EMAIL', 'fit5krd@pasohas.com');
define('SMTP_FROM_NAME', 'Fit5K Soporte');
