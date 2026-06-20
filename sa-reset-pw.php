<?php
require_once __DIR__ . '/core/db.php';
session_start();

if (!isset($_SESSION['sa_logged_in']) || $_SESSION['sa_logged_in'] !== true) {
    header('Location: /sa-login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /sa.php');
    exit;
}

$user_id     = trim($_POST['user_id'] ?? '');
$new_password = trim($_POST['new_password'] ?? '');

// Validate
if (!$user_id || !$new_password) {
    $_SESSION['sa_error'] = 'Missing user ID or password.';
    header('Location: /sa.php');
    exit;
}

if (strlen($new_password) < 6) {
    $_SESSION['sa_error'] = 'Password must be at least 6 characters.';
    header('Location: /sa.php');
    exit;
}

// At least 2 digits
preg_match_all('/[0-9]/', $new_password, $digits);
if (count($digits[0]) < 2) {
    $_SESSION['sa_error'] = 'Password must contain at least 2 numbers.';
    header('Location: /sa.php');
    exit;
}

try {
    $hashed = password_hash($new_password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE user_id = ?');
    $stmt->execute([$hashed, $user_id]);

    if ($stmt->rowCount() === 0) {
        $_SESSION['sa_error'] = 'User not found or password unchanged.';
    } else {
        $_SESSION['sa_success'] = "Password reset successfully for user ID: {$user_id}";
    }
} catch (Exception $e) {
    $_SESSION['sa_error'] = 'DB error: ' . $e->getMessage();
}

header('Location: /sa.php');
exit;
