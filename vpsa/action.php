<?php
require_once __DIR__ . '/../core/bootstrap.php';
if (empty($_SESSION['sa_logged_in'])) { header('Location: ' . BASE_URL . '/vpsa/login.php'); exit; }

$db  = Database::getInstance()->getConnection();
$act = $_GET['act'] ?? '';
$id  = (int)($_GET['id'] ?? 0);

if (!$id || !in_array($act, ['suspend', 'activate', 'delete'])) {
    header('Location: ' . BASE_URL . '/vpsa/');
    exit;
}

try {
    if ($act === 'delete') {
        $stmt = $db->prepare("DELETE FROM sa_businesses WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['sa_success'] = 'Business deleted.';
    } elseif ($act === 'suspend') {
        $stmt = $db->prepare("UPDATE sa_businesses SET status='suspended' WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['sa_success'] = 'Business suspended.';
    } elseif ($act === 'activate') {
        $stmt = $db->prepare("UPDATE sa_businesses SET status='active' WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['sa_success'] = 'Business activated.';
    }
} catch (Exception $e) {
    $_SESSION['sa_error'] = 'Action failed: ' . $e->getMessage();
}

header('Location: ' . BASE_URL . '/vpsa/');
exit;
