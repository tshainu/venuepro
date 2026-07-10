<?php
require_once __DIR__ . '/../core/bootstrap.php';

// Clear only SA session keys
unset(
    $_SESSION['sa_logged_in'],
    $_SESSION['sa_user_id'],
    $_SESSION['sa_user_name'],
    $_SESSION['sa_user_uid'],
    $_SESSION['user_id'],
    $_SESSION['user_name'],
    $_SESSION['user_email'],
    $_SESSION['user_role'],
    $_SESSION['user_role_id'],
    $_SESSION['branch_id'],
    $_SESSION['language']
);

// Expire cookie
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

session_destroy();
header('Location: ' . BASE_URL . '/vpsa/login.php');
exit;
