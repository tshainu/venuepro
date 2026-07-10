<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();

if (!Auth::hasRole(['super_admin','admin','owner','general_manager','hall_manager','manager'])) {
    Helper::redirect(BASE_URL . '/index.php');
}

$db = Database::getInstance();
$cu = Auth::currentUser();
$branch_id = $cu['branch_id'];

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    Helper::redirect(BASE_URL . '/modules/users/index.php');
}

// Check if user exists and if the current user has permission to edit them
if (Auth::isSuperAdmin()) {
    $user = $db->fetchOne("SELECT u.*, r.name as role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = ?", [$id]);
} else {
    // Get creator's business info
    $creator_user_id = $db->fetchOne("SELECT user_id FROM users WHERE id = ?", [$cu['id']])['user_id'] ?? '';
    $user = $db->fetchOne(
        "SELECT u.*, r.name as role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = ? AND u.user_id = ?",
        [$id, $creator_user_id]
    );
}

if (!$user) {
    Helper::redirect(BASE_URL . '/modules/users/index.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $user_id_val  = strtoupper(trim($_POST['user_id'] ?? ''));
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $phone    = trim($_POST['phone'] ?? '');
    $role_id  = (int)($_POST['role_id'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $selected_branches = $_POST['branch_ids'] ?? [];

    if (!$name) $error = 'Name is required.';
    elseif (empty($selected_branches)) $error = 'Please select at least one branch.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Invalid email.';
    elseif (!$user_id_val) $error = 'User ID is required.';
    elseif (!$username) $error = 'Username is required.';
    elseif ($password && strlen($password) < 8) $error = 'Password must be at least 8 characters.';
    elseif (!$role_id) $error = 'Please select a role.';

    if (!$error) {
        $dup = $db->fetchOne(
            "SELECT id FROM users WHERE id != ? AND (email = ? OR username = ?) LIMIT 1",
            [$id, $email, $username]
        );
        if ($dup) {
            $error = 'Email or Username already exists.';
        }
    }

    if (!$error) {
        try {
            $db->beginTransaction();
            $updates = ["name = ?", "email = ?", "user_id = ?", "username = ?", "phone = ?", "role_id = ?", "is_active = ?", "branch_id = ?", "updated_at = NOW()"];
            $params = [$name, $email, $user_id_val, $username, $phone, $role_id, $is_active, $selected_branches[0]];
            if ($password) {
                $updates[] = "password = ?";
                $params[] = password_hash($password, PASSWORD_BCRYPT);
            }
            $params[] = $id;
            $sql = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = ?";
            $db->execute($sql, $params);

            // Update branches
            $db->execute("DELETE FROM user_branches WHERE user_id = ?", [$id]);
            foreach ($selected_branches as $bid) {
                $db->execute("INSERT INTO user_branches (user_id, branch_id) VALUES (?, ?)", [$id, $bid]);
            }

            $db->commit();
            Logger::log('edit','users',$id,$user['username'],'name:'.$user['name'].' role:'.$user['role_name'],'name:'.$name,'User updated');
            $success = "Staff member updated successfully.";
            $user = $db->fetchOne(
                "SELECT u.*, r.name as role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = ?",
                [$id]
            );
            $_POST = [];
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $error = 'Error updating user: ' . $e->getMessage();
        }
    }
}

if (!$_POST) {
    $_POST = $user;
}

$roles = $db->fetchAll("SELECT id, name FROM roles WHERE slug IN ('reception', 'accountant', 'hall_manager', 'manager', 'receptionist', 'staff') ORDER BY id");

// FIX: Get ALL branches for the business, not just the creator's accessible branches
$creator_user_id = $db->fetchOne("SELECT user_id FROM users WHERE id = ?", [$cu['id']])['user_id'] ?? '';
$user_uid_to_check = $_SESSION['user_uid'] ?? $creator_user_id;
$biz_info = $db->fetchOne("SELECT id FROM sa_businesses WHERE admin_user_id = ?", [$user_uid_to_check]);
$sa_biz_id = $biz_info ? (int)$biz_info['id'] : 0;

if (Auth::isSuperAdmin()) {
    $branches = $db->fetchAll("SELECT id, name FROM branches WHERE is_active = 1");
} elseif ($sa_biz_id) {
    $branches = $db->fetchAll("SELECT id, name FROM branches WHERE is_active = 1 AND business_id = ?", [$sa_biz_id]);
} else {
    $accessible_branches = Auth::getAccessibleBranches();
    if (!empty($accessible_branches)) {
        $placeholders = implode(',', array_fill(0, count($accessible_branches), '?'));
        $branches = $db->fetchAll("SELECT id, name FROM branches WHERE id IN ($placeholders)", $accessible_branches);
    } else {
        $branches = [];
    }
}

// Get current user's branches
$user_branch_ids = array_column($db->fetchAll("SELECT branch_id FROM user_branches WHERE user_id = ?", [$id]), 'branch_id');
$branch = $db->fetchOne("SELECT name FROM branches WHERE id = ?", [$branch_id]);
$business = $db->fetchOne("SELECT business_name FROM sa_businesses WHERE admin_user_id = ?", [$creator_user_id]);
$business_name = $business['business_name'] ?? $branch['name'] ?? 'Business';

$pageTitle = 'Edit Staff Member';
require_once __DIR__ . '/../../includes/header.php';
?>
<style>
.form-card { background: #fff; border-radius: 12px; padding: 2rem; box-shadow: 0 2px 12px rgba(0,0,0,.06); border: 1px solid #edf0f8; }
.form-group { margin-bottom: 1.5rem; }
.form-label { display: block; font-size: .85rem; font-weight: 700; color: #374151; margin-bottom: .5rem; text-transform: uppercase; letter-spacing: .02em; }
.form-control { width: 100%; padding: .6rem .9rem; border: 1.5px solid #e5e7eb; border-radius: 8px; font-size: .9rem; transition: all .2s; }
.form-control:focus { border-color: #c9a84c; box-shadow: 0 0 0 3px rgba(201,168,76,.1); outline: none; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
.btn-submit { background: linear-gradient(135deg, #c9a84c, #e8c96a); color: #0c1a35; font-weight: 800; padding: .75rem 2rem; border: none; border-radius: 8px; cursor: pointer; transition: all .2s; font-size: .95rem; }
.btn-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(201,168,76,.3); }
.btn-secondary { background: #f3f4f6; color: #374151; font-weight: 700; padding: .75rem 2rem; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; transition: all .2s; display: inline-block; }
.btn-secondary:hover { background: #e5e7eb; }
.alert { padding: 1rem 1.2rem; border-radius: 8px; font-size: .9rem; font-weight: 600; margin-bottom: 1.5rem; }
.alert-success { background: #dcfce7; border: 1px solid #86efac; color: #166534; }
.alert-error { background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; }
.checkbox-group { display: flex; align-items: center; gap: .5rem; font-size: .9rem; font-weight: 600; color: #374151; cursor: pointer; }
.checkbox-group input { width: 18px; height: 18px; cursor: pointer; }
</style>
<div class="container-xl py-4">
  <div class="row mb-4">
    <div class="col-12">
      <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem;">
        <div>
          <h1 style="margin: 0; font-size: 1.75rem; font-weight: 800; color: #0c1a35;">Edit Staff Member</h1>
          <p style="margin: .5rem 0 0; color: #6b7280; font-size: .9rem;">Business: <strong><?= htmlspecialchars($business_name) ?></strong></p>
        </div>
        <a href="<?= BASE_URL ?>/modules/users/index.php" class="btn-secondary">Back</a>
      </div>
    </div>
  </div>
  <div class="row">
    <div class="col-lg-8">
      <div class="form-card">
        <?php if ($success): ?>
          <div class="alert alert-success">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:middle;margin-right:8px;"><path d="M20 6L9 17l-5-5"/></svg>
            <?= htmlspecialchars($success) ?>
          </div>
        <?php endif; ?>
        <?php if ($error): ?>
          <div class="alert alert-error">
            <?= htmlspecialchars($error) ?>
          </div>
        <?php endif; ?>
        <form action="" method="POST" autocomplete="off">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Full Name</label>
              <input type="text" name="name" class="form-control" required
                     value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Email Address</label>
              <input type="email" name="email" class="form-control" required
                     value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">User ID</label>
              <input type="text" name="user_id" class="form-control" required style="text-transform: uppercase;" maxlength="10"
                     value="<?= htmlspecialchars(strtoupper($_POST['user_id'] ?? '')) ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Username</label>
              <input type="text" name="username" class="form-control" required
                     value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">New Password (Leave blank to keep current)</label>
              <input type="password" name="password" class="form-control" placeholder="Min. 8 characters" minlength="8">
            </div>
            <div class="form-group">
              <label class="form-label">Phone</label>
              <input type="tel" name="phone" class="form-control"
                     value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Role</label>
            <select name="role_id" class="form-control" required>
              <?php foreach ($roles as $r): ?>
                <option value="<?= $r['id'] ?>" <?= ($_POST['role_id'] ?? 0) == $r['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($r['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Accessible Branches</label>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; background: #f9fafb; padding: 1rem; border-radius: 8px; border: 1.5px solid #e5e7eb;">
              <?php foreach ($branches as $b): ?>
                <label style="display: flex; align-items: center; gap: .5rem; font-size: .85rem; cursor: pointer;">
                  <input type="checkbox" name="branch_ids[]" value="<?= $b['id'] ?>" 
                         <?= in_array($b['id'], $user_branch_ids) ? 'checked' : '' ?>>
                  <?= htmlspecialchars($b['name']) ?>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="form-group" style="margin-top: 2rem;">
            <div class="checkbox-group">
              <input type="checkbox" id="is_active" name="is_active" value="1" <?= ($_POST['is_active'] ?? 1) ? 'checked' : '' ?>>
              <label for="is_active">Staff Member is Active</label>
            </div>
          </div>
          <div style="display: flex; gap: 1rem; margin-top: 2rem;">
            <button type="submit" class="btn-submit">Update Staff Member</button>
            <a href="<?= BASE_URL ?>/modules/users/index.php" class="btn-secondary">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
