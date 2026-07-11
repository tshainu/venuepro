<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();

if (!Auth::hasRole(['super_admin','admin','owner','general_manager','hall_manager','manager'])) {
    Helper::flash('error', Lang::t('access_denied'));
    Helper::redirect(BASE_URL . '/index.php');
}

$db = Database::getInstance();
$cu = Auth::currentUser();
$branch_id = $cu['branch_id'];
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    Helper::redirect(BASE_URL . '/modules/users/index.php');
}

// Modified query to allow fetching users across accessible branches for super_admin/admin/owner
if (Auth::hasRole(["super_admin", "admin", "owner"])) {
    $user = $db->fetchOne(
        "SELECT u.*, r.name as role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = ?",
        [$id]
    );
} else {
    $user = $db->fetchOne(
        "SELECT u.*, r.name as role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = ? AND u.branch_id = ?",
        [$id, $branch_id]
    );
}

if (!$user) {
    Helper::flash('error', 'User not found or access denied.');
    Helper::redirect(BASE_URL . '/modules/users/index.php');
}

$creator_user_id = $db->fetchOne("SELECT user_id FROM users WHERE id = ?", [$cu['id']])['user_id'] ?? '';
$current_business_id = $db->fetchOne("SELECT id FROM sa_businesses WHERE admin_user_id = ?", [$creator_user_id])['id'] ?? null;

$all_branches = $db->fetchAll("SELECT id, name FROM branches WHERE is_active = 1 AND business_id = ? ORDER BY name", [$current_business_id]);

$accessible_branches = [];
if (Auth::hasRole(["super_admin", "admin", "owner"])) {
    $accessible_branches = $all_branches;
} else {
    foreach ($all_branches as $b) {
        if ($b["id"] == $branch_id) {
            $accessible_branches[] = $b;
            break;
        }
    }
}

// Fetch currently assigned branches for the user being edited
$current_user_branches_rows = $db->fetchAll("SELECT branch_id FROM user_accessible_branches WHERE user_id = ?", [$id]);
$current_user_branch_ids = array_column($current_user_branches_rows, 'branch_id');
// Also include the legacy branch_id if it's set and not in the new table
if ($user['branch_id'] && !in_array($user['branch_id'], $current_user_branch_ids)) {
    $current_user_branch_ids[] = $user['branch_id'];
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = trim($_POST['name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $user_id   = strtoupper(trim($_POST['user_id'] ?? ''));
    $username  = trim($_POST['username'] ?? '');
    $password  = $_POST['password'] ?? '';
    $phone     = trim($_POST['phone'] ?? '');
    $role_id   = (int)($_POST['role_id'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (!$name) $errors[] = 'Full Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
    if (!$user_id) $errors[] = 'User ID is required.';
    if (!$username) $errors[] = 'Username is required.';
    if ($password && strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if (!$role_id) $errors[] = 'Please select a role.';

    if (!$errors) {
        // Check if email is taken by another user
        $dup_email = $db->fetchOne(
            "SELECT id FROM users WHERE id != ? AND email = ? LIMIT 1",
            [$id, $email]
        );
        if ($dup_email) {
            $errors[] = 'Email address is already in use by another account.';
        }
        // Check if username is taken by another user
        $dup_username = $db->fetchOne(
            "SELECT id FROM users WHERE id != ? AND username = ? LIMIT 1",
            [$id, $username]
        );
        if ($dup_username) {
            $errors[] = 'Username is already in use by another account.';
        }
    }

    $selected_branches = $_POST["accessible_branches"] ?? [];
    if (!is_array($selected_branches)) {
        $selected_branches = [];
    }
    $selected_branches = array_map("intval", $selected_branches);

    $valid_selected_branches = [];
    $accessible_branch_ids = array_column($accessible_branches, 'id');
    foreach ($selected_branches as $s_bid) {
        if (in_array($s_bid, $accessible_branch_ids)) {
            $valid_selected_branches[] = $s_bid;
        }
    }
    if (empty($valid_selected_branches)) {
        $errors[] = 'Please select at least one accessible branch.';
    }

    if (!$errors) {
        $updates = ["name = ?", "email = ?", "user_id = ?", "username = ?", "phone = ?", "role_id = ?", "is_active = ?"];
        $params = [$name, $email, $user_id, $username, $phone, $role_id, $is_active];
        
        if ($password) {
            $updates[] = "password = ?";
            $params[] = password_hash($password, PASSWORD_BCRYPT);
        }
        
        $params[] = $id;
        $sql = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = ?";
        
        try {
            $db->execute($sql, $params);
            
            // Update user_accessible_branches
            $db->execute("DELETE FROM user_accessible_branches WHERE user_id = ?", [$id]);
            foreach ($valid_selected_branches as $b_id) {
                $db->execute("INSERT INTO user_accessible_branches (user_id, branch_id) VALUES (?, ?)", [$id, $b_id]);
            }

            Logger::log('edit','users',$id,$user['username'],'name:'.$user['name'].' role:'.$user['role_name'],'name:'.$name,'User updated');
            Helper::flash('success', "Staff member updated successfully.");
            Helper::redirect(BASE_URL . '/modules/users/index.php');
        } catch (Exception $e) {
            $errors[] = 'Error updating user.';
        }
    }
}

if (!$_POST) {
    $_POST = $user;
}

$roles = $db->fetchAll("SELECT id, name FROM roles WHERE slug IN ('receptionist', 'accountant', 'hall_manager', 'manager', 'general_manager') ORDER BY id");
$branch = $db->fetchOne("SELECT name FROM branches WHERE id = ?", [$branch_id]);

$pageTitle = 'Edit Staff Member';
$breadcrumbs = [['label'=>'Staff Management','url'=>BASE_URL.'/modules/users/index.php'],['label'=>'Edit Staff']];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="vp-page-header d-print-none">
  <div class="row align-items-center">
    <div class="col">
      <h1 class="vp-page-title">Edit Staff Member</h1>

    </div>
  </div>
</div>

<div class="card vp-card">
  <div class="card-body">
    <?php if ($errors): foreach ($errors as $e): ?>
      <div class="alert alert-danger alert-dismissible" role="alert">
        <div><?= $e ?></div>
        <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
      </div>
    <?php endforeach; endif; ?>

    <form action="" method="POST" autocomplete="off">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Full Name *</label>
          <input type="text" name="name" class="form-control" required
                 value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Email Address *</label>
          <input type="email" name="email" class="form-control" required
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>

        <div class="col-md-6">
          <label class="form-label">User ID *</label>
          <input type="text" name="user_id" class="form-control" required style="text-transform: uppercase;" maxlength="10"
                 value="<?= htmlspecialchars(strtoupper($_POST['user_id'] ?? '')) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Username *</label>
          <input type="text" name="username" class="form-control" required
                 value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        </div>

        <div class="col-md-6">
          <label class="form-label">New Password</label>
          <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current" minlength="8">
        </div>
        <div class="col-md-6">
          <label class="form-label">Phone Number</label>
          <input type="tel" name="phone" class="form-control"
                 value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
        </div>

        <div class="col-md-6">
          <label class="form-label">Role *</label>
          <select name="role_id" class="form-select" required>
            <?php foreach ($roles as $r): ?>
              <option value="<?= $r['id'] ?>" <?= ($_POST['role_id'] ?? 0) == $r['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($r['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <?php if (Auth::hasRole(["super_admin", "admin", "owner"]) && count($accessible_branches) > 0): ?>
        <div class="col-12">
          <label class="form-label">Accessible Branches *</label>
          <div class="d-flex flex-wrap gap-3">
            <?php foreach ($accessible_branches as $b): ?>
              <?php $is_checked = in_array($b["id"], $current_user_branch_ids) ? 'checked' : ''; ?>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="accessible_branches[]" value="<?= $b["id"] ?>" id="branch_<?= $b["id"] ?>" <?= $is_checked ?>>
                <label class="form-check-label" for="branch_<?= $b["id"] ?>">
                  <?= Helper::sanitize($b["name"]) ?>
                </label>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <div class="col-md-6">
          <label class="form-label">Account Status</label>
          <div class="mt-2">
            <label class="form-check form-switch">
              <input class="form-check-input" type="checkbox" name="is_active" value="1" <?= ($_POST['is_active'] ?? 1) ? 'checked' : '' ?>>
              <span class="form-check-label">Active / Enabled</span>
            </label>
          </div>
        </div>

        <div class="col-12 mt-4">
          <button type="submit" class="btn btn-primary me-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="icon me-1" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12l5 5L20 7"/></svg>
            Update Staff Member
          </button>
          <a href="<?= BASE_URL ?>/modules/users/index.php" class="btn btn-ghost-secondary">Cancel</a>
        </div>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
