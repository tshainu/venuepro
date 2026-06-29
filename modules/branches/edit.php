<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
if (!Auth::hasRole(['super_admin','admin','hall_manager'])) { Helper::flash('error','Super Admin only.'); Helper::redirect(BASE_URL.'/index.php'); }
$db = Database::getInstance();
$id = (int)($_GET['id'] ?? 0);
$br = $db->fetchOne("SELECT * FROM branches WHERE id=?", [$id]);
if (!$br) { Helper::flash('error','Branch not found.'); Helper::redirect(BASE_URL.'/modules/branches/index.php'); }
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = trim($_POST['name'] ?? '');
    $address   = trim($_POST['address'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    if (!$name) $errors[] = 'Name required.';
    if (!$errors) {
        Logger::log('edit','branches',$id,$br['name'],'name:'.$br['name'],'name:'.$name,'Branch updated');
        $db->execute("UPDATE branches SET name=?,address=?,phone=?,email=?,is_active=? WHERE id=?", [$name,$address,$phone,$email,$is_active,$id]);
        Helper::flash('success','Branch updated.');
        Helper::redirect(BASE_URL.'/modules/branches/index.php');
    }
} else { $_POST = $br; $_POST['is_active'] = $br['is_active'] ? 'on' : ''; }

$pageTitle = 'Edit Branch';
$breadcrumbs = [['label'=>'Branches','url'=>BASE_URL.'/modules/branches/index.php'],['label'=>'Edit']];
require_once ROOT_PATH . '/includes/header.php';
?>
<div class="vp-page-header d-print-none"><h1 class="vp-page-title">Edit Branch</h1></div>
<div class="row justify-content-center">
  <div class="col-lg-6">
    <form method="post" class="card vp-card">
      <div class="card-header"><h3 class="card-title">Edit: <?= Helper::sanitize($br['name']) ?></h3></div>
      <div class="card-body">
        <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e): ?><li><?= Helper::sanitize($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <div class="mb-3"><label class="form-label required">Branch Name</label><input type="text" name="name" class="form-control" value="<?= Helper::sanitize($_POST['name']??'') ?>" required></div>
        <div class="mb-3"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="2"><?= Helper::sanitize($_POST['address']??'') ?></textarea></div>
        <div class="row mb-3">
          <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="<?= Helper::sanitize($_POST['phone']??'') ?>"></div>
          <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= Helper::sanitize($_POST['email']??'') ?>"></div>
        </div>
        <label class="form-check"><input type="checkbox" name="is_active" class="form-check-input" <?= isset($_POST['is_active'])&&$_POST['is_active']?'checked':'' ?>><span class="form-check-label">Active</span></label>
      </div>
      <div class="card-footer d-flex gap-2">
        <button type="submit" class="btn btn-vp-gold">Update Branch</button>
        <a href="<?= BASE_URL ?>/modules/branches/index.php" class="btn btn-vp-outline">Cancel</a>
      </div>
    </form>
  </div>
</div>
<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
