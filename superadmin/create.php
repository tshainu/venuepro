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

// Admin credentials
$admin_user_id  = strtoupper(trim($_POST['admin_user_id'] ?? ''));
$admin_username = trim($_POST['admin_username'] ?? 'admin');
$admin_password = trim($_POST['admin_password'] ?? '');

if (!$business_name || !$email) {
    $_SESSION['sa_error'] = 'Business Name and Email are required.';
    header('Location: ' . BASE_URL . '/vpsa/');
    exit;
}

// Map 'starter' -> 'basic' (enum compatibility)
if ($plan === 'starter') $plan = 'basic';

try {
    $db = Database::getInstance()->getConnection();

    // ── Validate email not already used ──────────────────────────────────
    $emailCheck = $db->prepare("SELECT id FROM users WHERE email = ?");
    $emailCheck->execute([$email]);
    if ($emailCheck->fetch()) {
        $_SESSION['sa_error'] = "Email \"$email\" is already registered to another business. Use a unique email for each business admin.";
        header('Location: ' . BASE_URL . '/vpsa/');
        exit;
    }

    // ── Generate unique user_id ───────────────────────────────────────────
    if (!$admin_user_id) {
        do {
            $admin_user_id = strtoupper(chr(rand(65, 90)) . rand(100, 999));
            $uidCheck = $db->prepare("SELECT id FROM users WHERE user_id = ?");
            $uidCheck->execute([$admin_user_id]);
        } while ($uidCheck->fetch());
    } else {
        // Check uniqueness of provided user_id
        $uidCheck = $db->prepare("SELECT id FROM users WHERE user_id = ?");
        $uidCheck->execute([$admin_user_id]);
        if ($uidCheck->fetch()) {
            $_SESSION['sa_error'] = "User ID \"$admin_user_id\" is already taken. Choose a different one.";
            header('Location: ' . BASE_URL . '/vpsa/');
            exit;
        }
    }

    // ── Generate unique username ──────────────────────────────────────────
    $baseUsername = $admin_username ?: 'admin';
    $finalUsername = $baseUsername;
    $unCheck = $db->prepare("SELECT id FROM users WHERE username = ?");
    $unCheck->execute([$finalUsername]);
    if ($unCheck->fetch()) {
        // Append suffix to make it unique
        $suffix = 2;
        do {
            $finalUsername = $baseUsername . $suffix;
            $unCheck->execute([$finalUsername]);
            $suffix++;
        } while ($unCheck->fetch());
    }
    $admin_username = $finalUsername;

    // ── Auto-generate password if blank ──────────────────────────────────
    if (!$admin_password) {
        $colors = ['red','blu','grn','ylw','org','pnk','cyn','vlt','brn','gry'];
        $admin_password = $colors[array_rand($colors)] . rand(1000, 9999);
    }

    // ── Get hall_manager role ─────────────────────────────────────────────
    $roleStmt = $db->prepare("SELECT id FROM roles WHERE slug = 'hall_manager' LIMIT 1");
    $roleStmt->execute();
    $role = $roleStmt->fetch(PDO::FETCH_ASSOC);
    $role_id = $role ? $role['id'] : 2;

    $db->beginTransaction();

    // ── 1. Insert business record ─────────────────────────────────────────
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

    // ── 2. Auto-create a default branch for this business ─────────────────
    $branchStmt = $db->prepare("
        INSERT INTO branches (business_id, name, address, city, phone, email, is_active)
        VALUES (?, ?, ?, ?, ?, ?, 1)
    ");
    $branchStmt->execute([
        $business_db_id,
        $business_name . ' - Main Branch',
        $address ?: null,
        $city ?: null,
        $phone ?: null,
        $email
    ]);
    $branch_id = $db->lastInsertId();

    // ── 3. Create admin user linked to that branch ────────────────────────
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

    // ── 4. Link user back to business record ──────────────────────────────
    $db->prepare("UPDATE sa_businesses SET business_id = ? WHERE id = ?")->execute([$user_db_id, $business_db_id]);

    $db->commit();

    $_SESSION['sa_success'] = "Business <strong>\"$business_name\"</strong> created successfully!<br>
        <strong>Login credentials →</strong>
        User ID: <code>$admin_user_id</code> &nbsp;|&nbsp;
        Username: <code>$admin_username</code> &nbsp;|&nbsp;
        Password: <code>$admin_password</code><br>
        <small class='text-muted'>Branch \"$business_name - Main Branch\" auto-created.</small>";

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    $_SESSION['sa_error'] = 'Failed to create business: ' . $e->getMessage();
}

header('Location: ' . BASE_URL . '/vpsa/');
exit;
