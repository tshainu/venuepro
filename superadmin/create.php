<?php
require_once __DIR__ . '/../core/bootstrap.php';
Auth::check();
if (!Auth::isSuperAdmin()) { Helper::flash('error','Access denied.'); Helper::redirect(BASE_URL.'/index.php'); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /superadmin/');
    exit;
}

$name     = trim($_POST['name'] ?? '');
$email    = trim($_POST['email'] ?? '');
$phone    = trim($_POST['phone'] ?? '');
$address  = trim($_POST['address'] ?? '');
$city     = trim($_POST['city'] ?? '');
$plan     = trim($_POST['plan'] ?? 'starter');
$status   = trim($_POST['status'] ?? 'active');

if (!$name || !$email) {
    $_SESSION['sa_error'] = 'Name and Email are required.';
    header('Location: /superadmin/');
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name)) . '-' . substr(uniqid(), -4);

    $stmt = $db->prepare("
        INSERT INTO sa_businesses (name, slug, email, phone, address, city, plan, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$name, $slug, $email, $phone, $address, $city, $plan, $status]);

    $_SESSION['sa_success'] = "Business \"$name\" created successfully.";
} catch (Exception $e) {
    $_SESSION['sa_error'] = 'DB error: ' . $e->getMessage();
}

header('Location: /superadmin/');
exit;
