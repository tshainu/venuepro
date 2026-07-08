<?php
require_once __DIR__ . '/../core/bootstrap.php';
if (empty($_SESSION['sa_logged_in'])) { header('Location: ' . BASE_URL . '/vpsa/login.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/vpsa/'); exit;
}

$db = Database::getInstance()->getConnection();
$id = (int)($_POST['id'] ?? 0);
if (!$id) { header('Location: ' . BASE_URL . '/vpsa/'); exit; }

$business_name = trim($_POST['business_name'] ?? '');
$owner_name    = trim($_POST['owner_name'] ?? '');
$email         = trim($_POST['email'] ?? '');
$phone         = trim($_POST['phone'] ?? '');
$address       = trim($_POST['address'] ?? '');
$city          = trim($_POST['city'] ?? '');
$country       = trim($_POST['country'] ?? '');
$plan          = trim($_POST['plan'] ?? 'starter');
$status        = trim($_POST['status'] ?? 'active');
$max_users     = (int)($_POST['max_users'] ?? 5);
$max_branches  = (int)($_POST['max_branches'] ?? 1);
$notes         = trim($_POST['notes'] ?? '');

if (!$business_name || !$email) {
    $_SESSION['sa_error'] = 'Business name and email are required.';
    header('Location: ' . BASE_URL . '/vpsa/'); exit;
}

$stmt = $db->prepare("
    UPDATE sa_businesses
    SET business_name=?, owner_name=?, email=?, phone=?, address=?, city=?, country=?,
        plan=?, status=?, max_users=?, max_branches=?, notes=?
    WHERE id=?
");
$stmt->execute([$business_name, $owner_name, $email, $phone, $address, $city, $country,
                $plan, $status, $max_users, $max_branches, $notes, $id]);

$_SESSION['sa_success'] = "Business updated successfully.";
header('Location: ' . BASE_URL . '/vpsa/');
exit;
