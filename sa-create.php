<?php
require_once __DIR__ . '/core/bootstrap.php';
if (empty($_SESSION['sa_logged_in'])) { header('Location: /sa-login.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /sa.php'); exit;
}

$business_name = trim($_POST['business_name'] ?? '');
$owner_name    = trim($_POST['owner_name']    ?? '');
$email         = trim($_POST['email']         ?? '');
$phone         = trim($_POST['phone']         ?? '');
$address       = trim($_POST['address']       ?? '');
$city          = trim($_POST['city']          ?? '');
$country       = trim($_POST['country']       ?? 'Sri Lanka');
$plan          = trim($_POST['plan']          ?? 'trial');
$status        = trim($_POST['status']        ?? 'pending');
$max_users     = (int)($_POST['max_users']    ?? 5);
$max_branches  = (int)($_POST['max_branches'] ?? 1);
$notes         = trim($_POST['notes']         ?? '');

if (!$business_name || !$email) {
    $_SESSION['sa_error'] = 'Business name and email are required.';
    header('Location: /sa.php'); exit;
}

// ── Generate user_id like M454 (letter + 3 digits, unique) ──
function generateUserId($db) {
    $letters = 'ABCDEFGHJKLMNPQRSTUVWXYZ'; // skip I, O to avoid confusion
    do {
        $uid = $letters[random_int(0, strlen($letters)-1)] . str_pad(random_int(0, 999), 3, '0', STR_PAD_LEFT);
        $exists = $db->query("SELECT COUNT(*) FROM users WHERE user_id = '$uid'")->fetchColumn();
    } while ($exists);
    return $uid;
}

// ── Generate password: 4 letters + 2 numbers, shuffled ──
function generatePassword() {
    $letters = 'abcdefghjkmnpqrstuvwxyz';
    $part = '';
    for ($i = 0; $i < 4; $i++) $part .= $letters[random_int(0, strlen($letters)-1)];
    $nums = str_pad(random_int(0, 99), 2, '0', STR_PAD_LEFT);
    $chars = str_split($part . $nums);
    shuffle($chars);
    return implode('', $chars);
}

try {
    $db = Database::getInstance()->getConnection();

    // Check email uniqueness
    $emailExists = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    $emailExists->execute([$email]);
    if ($emailExists->fetchColumn() > 0) {
        $_SESSION['sa_error'] = 'A user with this email already exists.';
        header('Location: /sa.php'); exit;
    }

    $db->beginTransaction();

    // 1. Create branch
    $stmt = $db->prepare("INSERT INTO branches (name, address, city, phone, email, is_active, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())");
    $stmt->execute([$business_name, $address, $city, $phone, $email]);
    $branch_id = $db->lastInsertId();

    // 2. Create sa_businesses record
    $stmt = $db->prepare("
        INSERT INTO sa_businesses (business_name, owner_name, email, phone, address, city, country, plan, status, max_users, max_branches, notes, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$business_name, $owner_name, $email, $phone, $address, $city, $country, $plan, $status, $max_users, $max_branches, $notes]);

    // 3. Generate credentials
    $user_id  = generateUserId($db);
    $rawPass  = generatePassword();
    $hashed   = password_hash($rawPass, PASSWORD_DEFAULT);

    // 4. Create admin user
    $stmt = $db->prepare("
        INSERT INTO users (user_id, branch_id, role_id, name, email, password, is_active, created_at)
        VALUES (?, ?, 5, 'admin', ?, ?, 1, NOW())
    ");
    $stmt->execute([$user_id, $branch_id, $email, $hashed]);

    $db->commit();

    $_SESSION['sa_success'] = "Business <strong>\"$business_name\"</strong> created.<br>
        <div style='margin-top:.6rem;background:rgba(201,168,76,.12);border:1px solid rgba(201,168,76,.3);border-radius:8px;padding:.75rem 1rem;font-family:monospace;font-size:.9rem;'>
        User ID: <strong>$user_id</strong><br>
        Username: <strong>admin</strong><br>
        Password: <strong>$rawPass</strong>
        </div>
        <small style='color:#94a3b8;'>Share these credentials with the business owner.</small>";

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    $_SESSION['sa_error'] = 'Error: ' . $e->getMessage();
}

header('Location: /sa.php');
exit;
