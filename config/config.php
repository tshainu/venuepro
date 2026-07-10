<?php
define('APP_NAME', 'VenuePro');
define('APP_VERSION', '1.0.0');

$_scheme = 'http';
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    $_scheme = 'https';
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
    $_scheme = $_SERVER['HTTP_X_FORWARDED_PROTO'];
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') {
    $_scheme = 'https';
}
$_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('BASE_URL', $_scheme . '://' . $_host);

define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_PATH', ROOT_PATH . '/uploads/');
define('UPLOAD_URL', BASE_URL . '/uploads/');

define('DB_HOST',    'localhost');
define('DB_NAME',    'venuepro');
define('DB_USER',    'venuepro');
define('DB_PASS',    'VenuePro@2026');
define('DB_PORT',    3306);
define('DB_CHARSET', 'utf8mb4');

define('SESSION_NAME',     'venuepro_session');
define('SESSION_LIFETIME', 7200);
define('MPDF_TEMP', ROOT_PATH . '/tmp/mpdf/');
define('PER_PAGE', 20);
date_default_timezone_set('Asia/Colombo');
