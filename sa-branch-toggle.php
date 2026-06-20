<?php
require_once __DIR__ . '/core/bootstrap.php';

if (empty($_SESSION['sa_logged_in'])) {
    header('Location: ' . BASE_URL . '/sa-login.php');
    exit;
}

$branch_id = (int)($_GET['branch_id'] ?? 0);
$state     = (int)($_GET['state'] ?? 0); // 1 = enable, 0 = disable

if (!$branch_id) {
    $_SESSION['sa_error'] = 'Invalid branch.';
    header('Location: ' . BASE_URL . '/sa.php');
    exit;
}

try {
    $db  = Database::getInstance();
    $pdo = $db->getConnection();

    $stmt = $pdo->prepare('UPDATE branches SET is_active = ? WHERE id = ?');
    $stmt->execute([$state ? 1 : 0, $branch_id]);

    $_SESSION['sa_success'] = $state
        ? 'Branch enabled — now visible in the client panel.'
        : 'Branch disabled — hidden from the client panel.';
} catch (Exception $e) {
    $_SESSION['sa_error'] = 'DB error: ' . $e->getMessage();
}

header('Location: ' . BASE_URL . '/sa.php');
exit;
