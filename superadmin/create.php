<?php
require_once __DIR__ . '/../core/bootstrap.php';
if (empty($_SESSION['sa_logged_in'])) { header('Location: ' . BASE_URL . '/vpsa'); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/vpsa/');
    exit;
}

$business_name = trim($_POST['business_name'] ?? '');
$owner_name    = trim($_POST['owner_name'] ?? '');
$email         = trim($_POST['email'] ?? '');
$phone         = trim($_POST['phone'] ?? '');
$address       = trim($_POST['address'] ?? '');
$city          = trim($_POST['city'] ?? '');
$country       = trim($_POST['country'] ?? 'Sri Lanka');
$plan          = trim($_POST['plan'] ?? 'trial');
$status        = trim($_POST['status'] ?? 'pending');
$max_users     = (int)($_POST['max_users'] ?? 5);
$max_branches  = (int)($_POST['max_branches'] ?? 1);
$notes         = trim($_POST['notes'] ?? '');

if (!$business_name || !$email) {
    $_SESSION['sa_error'] = 'Business Name and Email are required.';
    header('Location: ' . BASE_URL . '/vpsa/');
    exit;
}

// map 'starter' to 'basic' since enum uses 'basic'
if ($plan === 'starter') $plan = 'basic';

try {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("
        INSERT INTO sa_businesses
            (business_name, owner_name, email, phone, address, city, country, plan, status, max_users, max_branches, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $business_name, $owner_name, $email, $phone,
        $address, $city, $country, $plan, $status,
        $max_users, $max_branches, $notes
    ]);

    $_SESSION['sa_success'] = "Business \"$business_name\" created successfully.";
} catch (Exception $e) {
    $_SESSION['sa_error'] = 'DB error: ' . $e->getMessage();
}

header('Location: ' . BASE_URL . '/vpsa/');
exit;
