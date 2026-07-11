<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();

// Hall Managers and Managers (branch-level) can create staff for their branch
if (!Auth::hasRole(['super_admin','admin','owner','general_manager','hall_manager','manager'])) {
    Helper::flash('error', Lang::t('access_denied'));
    Helper::redirect(BASE_URL . '/index.php');
}

$db = Database::getInstance();
$cu = Auth::currentUser();
$current_user_branch_id = $cu["branch_id"];

// Get the current user's user_id (shared across business)
$creator_user_id = $db->fetchOne("SELECT user_id FROM users WHERE id = ?", [$cu['id']])['user_id'] ?? '';
$current_business_id = $db->fetchOne("SELECT id FROM sa_businesses WHERE admin_user_id = ?", [$creator_user_id])['id'] ?? null;

if (!$current_user_branch_id && !Auth::hasRole(['super_admin', 'admin', 'owner'])) {
    Helper::flash("error", "Please assign a branch to your account first.");
    Helper::redirect(BASE_URL . "/index.php");
}

$all_branches = $db->fetchAll("SELECT id, name FROM branches WHERE is_active = 1 AND business_id = ? ORDER BY name", [$current_business_id]);

// Determine which branches are accessible for selection
$accessible_branches = [];
if (Auth::hasRole(["super_admin", "admin", "owner"])) {
    // Super admin, admin, and owner can see all branches for their business
    $accessible_branches = $all_branches;
} else {
    // Other roles can only see their own branches
    $accessible_branch_ids = array_column($cu['accessible_branches'] ?? [], 'id');
    foreach ($all_branches as $b) {
        if (in_array($b["id"], $accessible_branch_ids) || $b["id"] == $current_user_branch_id) {
            $accessible_branches[] = $b;
        }
    }
}

// Determine the selected branch for the new user
    $selected_branches = $_POST["accessible_branches"] ?? [];
    if (!is_array($selected_branches)) {
        $selected_branches = [];
    }
    $selected_branches = array_map("intval", $selected_branches);

    // Validate selected branches against accessible branches
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

// If after all this, there are no accessible branches, something is wrong
if (empty($accessible_branches)) {
    Helper::flash("error", "No active branches available for assignment.");
    Helper::redirect(BASE_URL . "/index.php");
}



$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $phone    = trim($_POST['phone'] ?? '');
    $role_id  = (int)($_POST['role_id'] ?? 0);

    // Auto-generate a unique staff User ID (e.g. S001, S002...)
    $user_id = '';
    $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 1) ?: 'S');
    for ($attempt = 1; $attempt <= 999; $attempt++) {
        $candidate = $prefix . str_pad($attempt, 3, '0', STR_PAD_LEFT);
        $exists = $db->fetchOne("SELECT id FROM users WHERE user_id = ? LIMIT 1", [$candidate]);
        if (!$exists) { $user_id = $candidate; break; }
    }
    if (!$user_id) $user_id = strtoupper(substr(md5(uniqid()), 0, 6));

    // Validate
    if (!$name) $errors[] = 'Full Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
    if (!$username) $errors[] = 'Username is required.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if (!$role_id) $errors[] = 'Please select a role.';

    if (!$errors) {
        // Only allow creating specific roles
        $role = $db->fetchOne("SELECT slug FROM roles WHERE id = ? AND slug IN ('receptionist', 'accountant', 'hall_manager', 'manager', 'general_manager')", [$role_id]);
        if (!$role) {
            $errors[] = 'Invalid role selected.';
        }
    }

    if (!$errors) {
        // Check duplicates
        $dup = $db->fetchOne("SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1", [$email, $username]);
        if ($dup) {
            $errors[] = 'Email or Username already exists.';
        }
    }

    if (!$errors) {
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        try {
            $db->execute(
                "INSERT INTO users (name, email, user_id, username, password, phone, role_id, branch_id, is_active, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NULL, 1, NOW())",
                [$name, $email, $user_id, $username, $hashed, $phone, $role_id]
            );
            $new_uid = $db->fetchOne("SELECT LAST_INSERT_ID() as id")["id"] ?? null;
            Logger::log("create", "users", (int)$new_uid, $username, null,
                ["name"=>$name,"email"=>$email,"username"=>$username,"role_id"=>$role_id,"accessible_branches"=>$valid_selected_branches],
                "Created user $name ($username)");

            // Insert into user_accessible_branches
            foreach ($valid_selected_branches as $b_id) {
                $db->execute("INSERT INTO user_accessible_branches (user_id, branch_id) VALUES (?, ?)", [$new_uid, $b_id]);
            }
            
            Helper::flash('success', "Staff member <strong>$name</strong> created successfully.");
            Helper::redirect(BASE_URL . '/modules/users/index.php');
        } catch (Exception $e) {
            $errors[] = 'Error creating user.';
        }
    }
}

// Show staff roles
$roles = $db->fetchAll("SELECT id, name FROM roles WHERE slug IN ('receptionist', 'accountant', 'hall_manager', 'manager', 'general_manager') ORDER BY id");
    // $branch variable is no longer used for display in this context as multiple branches can be selected.

$pageTitle = 'Create Staff Member';
$breadcrumbs = [['label'=>'Staff Management','url'=>BASE_URL.'/modules/users/index.php'],['label'=>'Create Staff']];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="vp-page-header d-print-none">
  <div class="row align-items-center">
    <div class="col">
      <h1 class="vp-page-title">Create Staff Member</h1>
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
          <input type="text" name="name" class="form-control" placeholder="John Doe" required
                 value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Email Address *</label>
          <input type="email" name="email" class="form-control" placeholder="john@example.com" required
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        
        <div class="col-md-6">
          <label class="form-label">Username *</label>
          <input type="text" name="username" class="form-control" placeholder="john.doe" required
                 value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Phone Number</label>
          <input type="tel" name="phone" class="form-control" placeholder="+94..."
                 value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
        </div>

        <div class="col-md-6">
          <label class="form-label">Password *</label>
          <input type="password" name="password" class="form-control" placeholder="Min. 8 characters" required minlength="8">
          <div class="form-hint">Ensure the password is strong and secure.</div>
        </div>
        
        <div class="col-md-6">
          <label class="form-label">Role *</label>
          <select name="role_id" class="form-select" required>
            <option value="">Select a role...</option>
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
              <?php $is_checked = in_array($b["id"], (array)($_POST['accessible_branches'] ?? [])) ? 'checked' : ''; ?>
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

        <div class="col-12 mt-4">
          <button type="submit" class="btn btn-primary me-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="icon me-1" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
            Create Staff Member
          </button>
          <a href="<?= BASE_URL ?>/modules/users/index.php" class="btn btn-ghost-secondary">Cancel</a>
        </div>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
