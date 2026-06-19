<?php
define('APP_NAME', 'VenuePro');
define('APP_VERSION', '1.0.0');

// Dynamic BASE_URL — respects Railway/nginx X-Forwarded-Proto
$_scheme = 'http';
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    $_scheme = 'https';
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
    $_scheme = $_SERVER['HTTP_X_FORWARDED_PROTO']; // Railway proxy sets this
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') {
    $_scheme = 'https';
}
$_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('BASE_URL', $_scheme . '://' . $_host);

define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_PATH', ROOT_PATH . '/uploads/');
define('UPLOAD_URL', BASE_URL . '/uploads/');

// Database — reads Railway env vars
define('DB_HOST',    getenv('MYSQLHOST')    ?: getenv('DB_HOST')    ?: 'localhost');
define('DB_NAME',    getenv('MYSQLDATABASE')?: getenv('DB_NAME')    ?: 'venuepro');
define('DB_USER',    getenv('MYSQLUSER')    ?: getenv('DB_USER')    ?: 'venuepro');
define('DB_PASS',    getenv('MYSQLPASSWORD')?: getenv('DB_PASS')    ?: 'VenuePro@2026');
define('DB_PORT',    getenv('MYSQLPORT')    ?: 3306);
define('DB_CHARSET', 'utf8mb4');

// Session
define('SESSION_NAME',     'venuepro_session');
define('SESSION_LIFETIME', 7200);

// mPDF temp path
define('MPDF_TEMP', ROOT_PATH . '/tmp/mpdf/');

// Default pagination
define('PER_PAGE', 20);

// Timezone
date_default_timezone_set('Asia/Colombo');
