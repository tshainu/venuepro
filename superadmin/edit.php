<?php
require_once __DIR__ . '/../core/bootstrap.php';
if (empty($_SESSION['sa_logged_in'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance()->getConnection();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if (!$id) {
    echo json_encode(['error' => 'No ID']);
    exit;
}

// ── GET: return business JSON for modal ──
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare("SELECT * FROM sa_businesses WHERE id = ?");
    $stmt->execute([$id]);
    $biz = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$biz) { echo json_encode(['error' => 'Not found']); exit; }
    header('Content-Type: application/json');
    echo json_encode($biz);
    exit;
}

// ── POST: update ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

    // credentials (optional update)
    $admin_username     = trim($_POST['admin_username']     ?? '');
    $admin_password_new = trim($_POST['admin_password_new'] ?? '');

    if (!$business_name || !$email) {
        $_SESSION['sa_error'] = 'Business Name and Email are required.';
        header('Location: ' . BASE_URL . '/vpsa/');
        exit;
    }

    if ($plan === 'starter') $plan = 'basic';

    try {
        $db->beginTransaction();

        $stmt = $db->prepare("
            UPDATE sa_businesses
            SET business_name=?, owner_name=?, email=?, phone=?, address=?, city=?,
                country=?, plan=?, status=?, max_users=?, max_branches=?, notes=?
            WHERE id=?
        ");
        $stmt->execute([
            $business_name, $owner_name, $email, $phone,
            $address, $city, $country, $plan, $status,
            $max_users, $max_branches, $notes, $id
        ]);

        // Update username if changed
        if ($admin_username) {
            // get current user_id for this biz
            $bizRow = $db->prepare("SELECT admin_user_id, admin_password_plain FROM sa_businesses WHERE id=?");
            $bizRow->execute([$id]);
            $biz = $bizRow->fetch(PDO::FETCH_ASSOC);

            $db->prepare("UPDATE sa_businesses SET admin_username=? WHERE id=?")->execute([$admin_username, $id]);
            if ($biz['admin_user_id']) {
                $db->prepare("UPDATE users SET username=? WHERE user_id=?")->execute([$admin_username, $biz['admin_user_id']]);
            }
        }

        // Update password if provided
        if ($admin_password_new) {
            $bizRow = $db->prepare("SELECT admin_user_id FROM sa_businesses WHERE id=?");
            $bizRow->execute([$id]);
            $biz = $bizRow->fetch(PDO::FETCH_ASSOC);

            $db->prepare("UPDATE sa_businesses SET admin_password_plain=? WHERE id=?")->execute([$admin_password_new, $id]);
            if ($biz['admin_user_id']) {
                $hashed = password_hash($admin_password_new, PASSWORD_DEFAULT);
                $db->prepare("UPDATE users SET password=? WHERE user_id=?")->execute([$hashed, $biz['admin_user_id']]);
            }
        }

        $db->commit();
        $_SESSION['sa_success'] = "Business updated successfully.";
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $_SESSION['sa_error'] = 'DB error: ' . $e->getMessage();
    }

    header('Location: ' . BASE_URL . '/vpsa/');
    exit;
}
