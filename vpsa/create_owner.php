<?php
require_once __DIR__ . '/../core/bootstrap.php';
if (empty($_SESSION['sa_logged_in'])) { header('Location: ' . BASE_URL . '/vpsa/login.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/vpsa/'); exit;
}
$db = Database::getInstance()->getConnection();
$business_id = (int)($_POST['business_id'] ?? 0);
$username    = trim($_POST['username'] ?? 'admin');
$email       = trim($_POST['email'] ?? '');
$password    = trim($_POST['password'] ?? '');
if (!$business_id || !$email || !$password) {
    $_SESSION['sa_error'] = 'Business ID, email, and password are required.';
    header('Location: ' . BASE_URL . '/vpsa/'); exit;
}
function generateUserId($db) {
    $letters = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    do {
        $uid = $letters[random_int(0, strlen($letters)-1)] . str_pad(random_int(0, 999), 3, '0', STR_PAD_LEFT);
        $stmt = $db->prepare(\"SELECT COUNT(*) FROM users WHERE user_id = ?\");
        $stmt->execute([$uid]);
        $exists = $stmt->fetchColumn();
    } while ($exists);
    return $uid;
}
try {
    $stmt = $db->prepare(\"SELECT COUNT(*) FROM users WHERE email = ?\");
    $stmt->execute([$email]);
    if ($stmt->fetchColumn() > 0) {
        $_SESSION['sa_error'] = 'A user with this email already exists.';
        header('Location: ' . BASE_URL . '/vpsa/'); exit;
    }
    $stmt = $db->prepare(\"SELECT business_name FROM sa_businesses WHERE id = ?\");
    $stmt->execute([$business_id]);
    $biz = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$biz) throw new Exception('Business not found.');
    $stmt = $db->prepare(\"SELECT id FROM branches WHERE name = ? LIMIT 1\");
    $stmt->execute([$biz['business_name']]);
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$branch) throw new Exception('Branch not found for this business.');
    $branch_id = $branch['id'];
    $user_id = generateUserId($db);
    $hashed  = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare(\"
        INSERT INTO users (user_id, username, branch_id, role_id, name, email, password, is_active, created_at)
        VALUES (?, ?, ?, 6, 'Owner', ?, ?, 1, NOW())
    \");
    $stmt->execute([$user_id, $username, $branch_id, $email, $hashed]);
    $_SESSION['sa_success'] = \"Owner user created for <strong>{$biz['business_name']}</strong>.<br>
        <div style='margin-top:.6rem;background:rgba(201,168,76,.12);border:1px solid rgba(201,168,76,.3);border-radius:8px;padding:.75rem 1rem;font-family:monospace;font-size:.9rem;'>
        User ID: <strong>$user_id</strong><br>
        Username: <strong>$username</strong><br>
        Password: <strong>$password</strong>
        </div>\";
} catch (Exception $e) {
    $_SESSION['sa_error'] = 'Error: ' . $e->getMessage();
}
header('Location: ' . BASE_URL . '/vpsa/');
exit;
