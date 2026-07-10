<?php
// VenuePro Lanka - Configuration
// Copy this to config.php and fill in your values
define('APP_NAME', 'VenuePro');
define('APP_VERSION', '1.0.0');

// Dynamic BASE_URL — auto-detects from request host
$_scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$_host   = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
define('BASE_URL', $_scheme . '://' . $_host);

define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_PATH', ROOT_PATH . '/uploads/');
define('UPLOAD_URL', BASE_URL . '/uploads/');

// Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'venuepro');
define('DB_USER', 'venuepro');
define('DB_PASS', 'your_password_here');
define('DB_CHARSET', 'utf8mb4');

// Session
define('SESSION_NAME', 'venuepro_session');
define('SESSION_LIFETIME', 7200);

// mPDF temp path
define('MPDF_TEMP', ROOT_PATH . '/tmp/mpdf/');

// Default pagination
define('PER_PAGE', 20);

// Timezone
date_default_timezone_set('Asia/Colombo');
