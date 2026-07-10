<?php
require_once __DIR__ . '/../../core/bootstrap.php';

// Log before wiping session
if (!empty($_SESSION['user_id'])) {
    Logger::log('logout', 'users', (int)$_SESSION['user_id'], $_SESSION['user_username'] ?? '', null, null, "User " . ($_SESSION['user_name'] ?? '') . " logged out");
}

// Wipe all session data
$_SESSION = [];

// Expire the session cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

session_destroy();

// Start a clean session so login.php doesn't see stale data
session_start();
session_regenerate_id(true);
$_SESSION = [];

header('Location: ' . BASE_URL . '/login.php');
exit;
