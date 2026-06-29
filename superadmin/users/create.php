<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();

// Only super admin can access
if (!Auth::isSuperAdmin()) {
    Helper::redirect(BASE_URL . '/index.php');
}

$error = '';
$success = '';

$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $user_id  = strtoupper(trim($_POST['user_id'] ?? ''));
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $phone    = trim($_POST['phone'] ?? '');
    $role_id  = (int)($_POST['role_id'] ?? 0);
    $branch_id = (int)($_POST['branch_id'] ?? 0);

    // Validate
    if (!$name) $error = 'Name is required.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Invalid email.';
    elseif (!$user_id) $error = 'User ID is required.';
    elseif (!$username) $error = 'Username is required.';
    elseif (strlen($password) < 8) $error = 'Password must be at least 8 characters.';
    elseif (!$role_id) $error = 'Please select a role.';

    if (!$error) {
        // Check duplicates
        $dup = $db->fetchOne("SELECT id FROM users WHERE email = ? OR user_id = ? OR username = ? LIMIT 1", [$email, $user_id, $username]);
        if ($dup) {
            $error = 'Email, User ID, or Username already exists.';
        }
    }

    if (!$error) {
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        try {
            $db->execute(
                "INSERT INTO users (name, email, user_id, username, password, phone, role_id, branch_id, is_active, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())",
                [$name, $email, $user_id, $username, $hashed, $phone, $role_id, $branch_id ?: null]
            );
            $success = "User <strong>$name</strong> created successfully.";
            // Reset form
            $_POST = [];
        } catch (Exception $e) {
            $error = 'Error creating user: ' . $e->getMessage();
        }
    }
}

$roles = $db->fetchAll("SELECT id, name FROM roles WHERE slug != 'super_admin' ORDER BY id");
$branches = $db->fetchAll("SELECT id, name FROM branches ORDER BY name");
$cu = Auth::currentUser();

$pageTitle = 'Create User';
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
.form-card { background: #fff; border-radius: 12px; padding: 2rem; box-shadow: 0 2px 12px rgba(0,0,0,.06); border: 1px solid #edf0f8; }
.form-group { margin-bottom: 1.5rem; }
.form-label { display: block; font-size: .85rem; font-weight: 700; color: #374151; margin-bottom: .5rem; text-transform: uppercase; letter-spacing: .02em; }
.form-control { width: 100%; padding: .6rem .9rem; border: 1.5px solid #e5e7eb; border-radius: 8px; font-size: .9rem; transition: all .2s; }
.form-control:focus { border-color: #c9a84c; box-shadow: 0 0 0 3px rgba(201,168,76,.1); outline: none; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
.form-row-full { grid-column: 1 / -1; }
.btn-submit { background: linear-gradient(135deg, #c9a84c, #e8c96a); color: #0c1a35; font-weight: 800; padding: .75rem 2rem; border: none; border-radius: 8px; cursor: pointer; transition: all .2s; font-size: .95rem; }
.btn-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(201,168,76,.3); }
.btn-secondary { background: #f3f4f6; color: #374151; font-weight: 700; padding: .75rem 2rem; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; transition: all .2s; display: inline-block; }
.btn-secondary:hover { background: #e5e7eb; }
.alert { padding: 1rem 1.2rem; border-radius: 8px; font-size: .9rem; font-weight: 600; margin-bottom: 1.5rem; }
.alert-success { background: #dcfce7; border: 1px solid #86efac; color: #166534; }
.alert-error { background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; }
</style>

<div class="container-xl py-4">
  <div class="row mb-4">
    <div class="col-12">
      <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem;">
        <div>
          <h1 style="margin: 0; font-size: 1.75rem; font-weight: 800; color: #0c1a35;">Create New User</h1>
          <p style="margin: .5rem 0 0; color: #6b7280; font-size: .9rem;">Add a new user to the system</p>
        </div>
        <a href="<?= BASE_URL ?>/superadmin/users/index.php" class="btn-secondary">Back to Users</a>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-lg-8">
      <div class="form-card">
        <?php if ($success): ?>
          <div class="alert alert-success">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:middle;margin-right:8px;"><path d="M20 6L9 17l-5-5"/></svg>
            <?= $success ?>
          </div>
        <?php endif; ?>

        <?php if ($error): ?>
          <div class="alert alert-error">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:middle;margin-right:8px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <?= htmlspecialchars($error) ?>
          </div>
        <?php endif; ?>

        <form action="" method="POST" autocomplete="off">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Full Name</label>
              <input type="text" name="name" class="form-control" placeholder="John Doe" required
                     value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Email Address</label>
              <input type="email" name="email" class="form-control" placeholder="john@example.com" required
                     value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">User ID</label>
              <input type="text" name="user_id" class="form-control" placeholder="A001" required
                     style="text-transform: uppercase;" maxlength="10"
                     value="<?= htmlspecialchars(strtoupper($_POST['user_id'] ?? '')) ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Username</label>
              <input type="text" name="username" class="form-control" placeholder="john.doe" required
                     value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Password</label>
              <input type="password" name="password" class="form-control" placeholder="Min. 8 characters" required minlength="8">
            </div>
            <div class="form-group">
              <label class="form-label">Phone (Optional)</label>
              <input type="tel" name="phone" class="form-control" placeholder="+94 123 456 7890"
                     value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Role</label>
              <select name="role_id" class="form-control" required>
                <option value="">Select a role...</option>
                <?php foreach ($roles as $r): ?>
                  <option value="<?= $r['id'] ?>" <?= ($_POST['role_id'] ?? 0) == $r['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($r['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Branch (Optional)</label>
              <select name="branch_id" class="form-control">
                <option value="">All Branches</option>
                <?php foreach ($branches as $b): ?>
                  <option value="<?= $b['id'] ?>" <?= ($_POST['branch_id'] ?? 0) == $b['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($b['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div style="display: flex; gap: 1rem; margin-top: 2rem;">
            <button type="submit" class="btn-submit">Create User</button>
            <a href="<?= BASE_URL ?>/superadmin/users/index.php" class="btn-secondary">Cancel</a>
          </div>
        </form>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="form-card" style="background: #f0fdf4; border-color: #86efac;">
        <h3 style="margin: 0 0 1rem; font-size: 1rem; font-weight: 700; color: #166534;">Password Requirements</h3>
        <ul style="margin: 0; padding: 0 0 0 1.2rem; font-size: .85rem; color: #166534; line-height: 1.8;">
          <li>At least 8 characters</li>
          <li>Avoid common words</li>
          <li>Mix of upper, lower, numbers & symbols recommended</li>
        </ul>
      </div>
      <div class="form-card" style="background: #eff6ff; border-color: #3b82f6; margin-top: 1.5rem;">
        <h3 style="margin: 0 0 1rem; font-size: 1rem; font-weight: 700; color: #1e40af;">Role Guide</h3>
        <ul style="margin: 0; padding: 0 0 0 1.2rem; font-size: .85rem; color: #1e40af; line-height: 1.8;">
          <li><strong>Hall Manager:</strong> Full booking & admin access</li>
          <li><strong>Reception:</strong> Create bookings & inquiries</li>
          <li><strong>Accountant:</strong> View payments & reports</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
