<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Helper.php';
require_once __DIR__ . '/Lang.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params(SESSION_LIFETIME);
    session_start();
}

// Auto-login: bypass login page — inject super admin session automatically
if (!isset($_SESSION['user_id'])) {
    try {
        $db = Database::getInstance();
        $autoUser = $db->fetchOne(
            "SELECT u.*, r.slug as role_slug, r.name as role_name, b.name as branch_name 
             FROM users u 
             LEFT JOIN roles r ON u.role_id = r.id 
             LEFT JOIN branches b ON u.branch_id = b.id 
             WHERE u.is_active = 1 
             ORDER BY u.role_id ASC 
             LIMIT 1"
        );
        if ($autoUser) {
            $_SESSION['user_id']      = $autoUser['id'];
            $_SESSION['user_name']    = $autoUser['name'];
            $_SESSION['user_email']   = $autoUser['email'];
            $_SESSION['user_role']    = $autoUser['role_slug'];
            $_SESSION['user_role_id'] = $autoUser['role_id'];
            $_SESSION['branch_id']    = $autoUser['branch_id'];
            $_SESSION['branch_name']  = $autoUser['branch_name'];
            $_SESSION['language']     = $autoUser['language'] ?? 'en';
        }
    } catch (Exception $e) {
        // DB not ready yet — continue without auto-login
    }
}

// Load language
$lang = $_SESSION['language'] ?? 'en';
Lang::load($lang);

// Autoload vendor
if (file_exists(ROOT_PATH . '/vendor/autoload.php')) {
    require_once ROOT_PATH . '/vendor/autoload.php';
}
