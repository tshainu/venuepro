<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
if (!Auth::isSuperAdmin()) { Helper::flash('error','Super Admin only.'); Helper::redirect(BASE_URL.'/index.php'); }
$db = Database::getInstance();
$id = (int)($_GET['id'] ?? 0);
$user = $db->fetchOne("SELECT * FROM users WHERE id=?", [$id]);
if (!$user) { Helper::flash('error','User not found.'); Helper::redirect(BASE_URL.'/modules/users/index.php'); }

$roles = $db->fetchAll("SELECT * FROM roles ORDER BY name");
$branches = $db->fetchAll("SELECT id,name FROM branches WHERE is_active=1");
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = trim($_POST['name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $role_id   = (int)($_POST['role_id'] ?? 0);
    $branch_id = (int)($_POST['branch_id'] ?? 0) ?: null;
    $language  = $_POST['language'] ?? 'en';
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (!$name) $errors[] = 'Name required.';
    if (!$email || !filter_var($email,FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';
    if ($password && strlen($password) < 6) $errors[] = 'Password min 6 chars.';
    if (!$role_id) $errors[] = 'Role required.';

    if (!$errors) {
        $exists = $db->fetchOne("SELECT id FROM users WHERE email=? AND id!=?", [$email,$id]);
        if ($exists) $errors[] = 'Email already used by another user.';
    }
    if (!$errors) {
        if ($password) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $db->execute("UPDATE users SET name=?,email=?,password=?,role_id=?,branch_id=?,language=?,is_active=? WHERE id=?", [$name,$email,$hash,$role_id,$branch_id,$language,$is_active,$id]);
        } else {
            $db->execute("UPDATE users SET name=?,email=?,role_id=?,branch_id=?,language=?,is_active=? WHERE id=?", [$name,$email,$role_id,$branch_id,$language,$is_active,$id]);
        }
        Helper::flash('success','User updated.');
        Helper::redirect(BASE_URL.'/modules/users/index.php');
    }
} else {
    $_POST = $user;
    $_POST['is_active'] = $user['is_active'] ? 'on' : '';
}

$pageTitle = 'Edit User';
$breadcrumbs = [['label'=>'Users','url'=>BASE_URL.'/modules/users/index.php'],['label'=>'Edit']];
require_once ROOT_PATH . '/includes/header.php';
?>
<div class="vp-page-header d-print-none"><h1 class="vp-page-title">Edit User: <?= Helper::sanitize($user['name']) ?></h1></div>
<div class="row justify-content-center">
  <div class="col-lg-6">
    <form method="post" class="card vp-card">
      <div class="card-header"><h3 class="card-title">Edit: <?= Helper::sanitize($user['name']) ?></h3></div>
      <div class="card-body">
        <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e): ?><li><?= Helper::sanitize($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <div class="mb-3"><label class="form-label required">Full Name</label><input type="text" name="name" class="form-control" value="<?= Helper::sanitize($_POST['name']??'') ?>" required></div>
        <div class="mb-3"><label class="form-label required">Email</label><input type="email" name="email" class="form-control" value="<?= Helper::sanitize($_POST['email']??'') ?>" required></div>
        <div class="mb-3"><label class="form-label">New Password <span class="text-secondary">(leave blank to keep)</span></label><input type="password" name="password" class="form-control" placeholder="Leave blank to keep current"></div>
        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label required">Role</label>
            <select name="role_id" class="form-select" required>
              <?php foreach ($roles as $r): ?><option value="<?= $r['id'] ?>" <?= ($_POST['role_id']??'')==$r['id']?'selected':'' ?>><?= Helper::sanitize($r['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Branch</label>
            <select name="branch_id" class="form-select">
              <option value="">All Branches</option>
              <?php foreach ($branches as $b): ?><option value="<?= $b['id'] ?>" <?= ($_POST['branch_id']??'')==$b['id']?'selected':'' ?>><?= Helper::sanitize($b['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">Language</label>
            <select name="language" class="form-select">
              <option value="en" <?= ($_POST['language']??'en')==='en'?'selected':'' ?>>English</option>
              <option value="si" <?= ($_POST['language']??'')==='si'?'selected':'' ?>>සිංහල</option>
              <option value="ta" <?= ($_POST['language']??'')==='ta'?'selected':'' ?>>தமிழ்</option>
            </select>
          </div>
          <div class="col-md-6 d-flex align-items-end">
            <label class="form-check mb-0">
              <input type="checkbox" name="is_active" class="form-check-input" <?= isset($_POST['is_active'])&&$_POST['is_active']?'checked':'' ?>>
              <span class="form-check-label">Active</span>
            </label>
          </div>
        </div>
      </div>
      <div class="card-footer d-flex gap-2">
        <button type="submit" class="btn btn-vp-gold">Update User</button>
        <a href="<?= BASE_URL ?>/modules/users/index.php" class="btn btn-vp-outline">Cancel</a>
      </div>
    </form>
  </div>
</div>
<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
