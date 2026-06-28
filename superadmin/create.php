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

// Admin credentials (from form, may be customized or auto-generated)
$admin_user_id  = strtoupper(trim($_POST['admin_user_id'] ?? ''));
$admin_username = trim($_POST['admin_username'] ?? 'admin');
$admin_password = trim($_POST['admin_password'] ?? '');

if (!$business_name || !$email) {
    $_SESSION['sa_error'] = 'Business Name and Email are required.';
    header('Location: ' . BASE_URL . '/vpsa/');
    exit;
}

// map 'starter' -> 'basic' (enum compatibility)
if ($plan === 'starter') $plan = 'basic';

try {
    $db = Database::getInstance()->getConnection();

    // Ensure user_id is unique
    if (!$admin_user_id) {
        do {
            $admin_user_id = strtoupper(substr(bin2hex(random_bytes(1)), 0, 1) . rand(100, 999));
        } while (false); // simplified; unique check below
    }

    // Ensure password has a value
    if (!$admin_password) {
        $colors = ['red','blu','grn','ylw','org','pnk','cyn','vlt','brn','gry'];
        $admin_password = $colors[array_rand($colors)] . rand(1000, 9999);
    }

    // Check user_id uniqueness
    $existing = $db->prepare("SELECT id FROM users WHERE user_id = ?");
    $existing->execute([$admin_user_id]);
    if ($existing->fetch()) {
        // Regenerate
        $admin_user_id = strtoupper(chr(rand(65,90)) . rand(100, 9999));
    }

    $db->beginTransaction();

    // Insert business
    $stmt = $db->prepare("
        INSERT INTO sa_businesses
            (business_name, owner_name, email, phone, address, city, country, plan, status,
             max_users, max_branches, notes, admin_user_id, admin_username, admin_password_plain)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $business_name, $owner_name, $email, $phone,
        $address, $city, $country, $plan, $status,
        $max_users, $max_branches, $notes,
        $admin_user_id, $admin_username, $admin_password
    ]);
    $business_db_id = $db->lastInsertId();

    // Get admin role id
    $roleStmt = $db->prepare("SELECT id FROM roles WHERE slug = 'super_admin' LIMIT 1");
    $roleStmt->execute();
    $role = $roleStmt->fetch(PDO::FETCH_ASSOC);
    $role_id = $role ? $role['id'] : 1;

    // Get or create a branch for this business
    // For now use branch_id = 1 (main branch) or null
    $branch_id = null;

    // Create admin user in users table
    $hashed = password_hash($admin_password, PASSWORD_DEFAULT);
    $userStmt = $db->prepare("
        INSERT INTO users (user_id, username, branch_id, role_id, name, email, password, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?, 1)
    ");
    $userStmt->execute([
        $admin_user_id, $admin_username, $branch_id,
        $role_id, $owner_name ?: $business_name, $email, $hashed
    ]);
    $user_db_id = $db->lastInsertId();

    // Link business to user
    $db->prepare("UPDATE sa_businesses SET business_id = ? WHERE id = ?")->execute([$user_db_id, $business_db_id]);

    $db->commit();

    $_SESSION['sa_success'] = "Business \"$business_name\" created. Admin: <strong>$admin_user_id</strong> / <strong>$admin_username</strong> / <strong>$admin_password</strong>";
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    $_SESSION['sa_error'] = 'DB error: ' . $e->getMessage();
}

header('Location: ' . BASE_URL . '/vpsa/');
exit;
