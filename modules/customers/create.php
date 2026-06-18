<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
$db = Database::getInstance();
$cu = Auth::currentUser();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name'] ?? '');
    $bride_name  = trim($_POST['bride_name'] ?? '');
    $groom_name  = trim($_POST['groom_name'] ?? '');
    $nic         = trim($_POST['nic'] ?? '');
    $address     = trim($_POST['address'] ?? '');
    $city        = trim($_POST['city'] ?? '');
    $mobile      = trim($_POST['mobile'] ?? '');
    $mobile2     = trim($_POST['mobile2'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $notes       = trim($_POST['notes'] ?? '');
    $branch_id   = $cu['branch_id'] ?? 1;

    if (!$name)   $errors[] = 'Customer name is required.';
    if (!$mobile) $errors[] = 'Mobile number is required.';

    if (!$errors) {
        $db->insert(
            "INSERT INTO customers (branch_id,name,bride_name,groom_name,nic,address,city,mobile,mobile2,email,notes,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
            [$branch_id,$name,$bride_name,$groom_name,$nic,$address,$city,$mobile,$mobile2,$email,$notes,$cu['id']]
        );
        Helper::flash('success', 'Customer added successfully.');
        Helper::redirect(BASE_URL.'/modules/customers/index.php');
    }
}

$pageTitle = 'Add Customer';
$breadcrumbs = [['label'=>'Customers','url'=>BASE_URL.'/modules/customers/index.php'],['label'=>'Add Customer']];
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="page-header"><div class="row align-items-center"><div class="col"><h1 class="vp-page-title">Add Customer</h1></div></div></div>
<div class="card vp-card"><div class="card-body">
  <?php if ($errors): foreach ($errors as $e): ?><div class="alert alert-danger"><?= Helper::sanitize($e) ?></div><?php endforeach; endif; ?>
  <form method="POST">
    <div class="row g-3">
      <div class="col-12"><h5 class="text-secondary">Personal Information</h5></div>
      <div class="col-md-4"><label class="form-label">Full Name *</label>
        <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"></div>
      <div class="col-md-4"><label class="form-label">NIC Number</label>
        <input type="text" name="nic" class="form-control" value="<?= htmlspecialchars($_POST['nic'] ?? '') ?>"></div>
      <div class="col-md-4"><label class="form-label">City</label>
        <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($_POST['city'] ?? '') ?>"></div>
      <div class="col-12"><h5 class="text-secondary mt-2">Wedding Details</h5></div>
      <div class="col-md-6"><label class="form-label">Bride Name</label>
        <input type="text" name="bride_name" class="form-control" value="<?= htmlspecialchars($_POST['bride_name'] ?? '') ?>"></div>
      <div class="col-md-6"><label class="form-label">Groom Name</label>
        <input type="text" name="groom_name" class="form-control" value="<?= htmlspecialchars($_POST['groom_name'] ?? '') ?>"></div>
      <div class="col-12"><h5 class="text-secondary mt-2">Contact Information</h5></div>
      <div class="col-md-4"><label class="form-label">Mobile *</label>
        <input type="tel" name="mobile" class="form-control" required value="<?= htmlspecialchars($_POST['mobile'] ?? '') ?>"></div>
      <div class="col-md-4"><label class="form-label">Mobile 2</label>
        <input type="tel" name="mobile2" class="form-control" value="<?= htmlspecialchars($_POST['mobile2'] ?? '') ?>"></div>
      <div class="col-md-4"><label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"></div>
      <div class="col-12"><label class="form-label">Address</label>
        <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea></div>
      <div class="col-12"><label class="form-label">Notes</label>
        <textarea name="notes" class="form-control" rows="2"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea></div>
      <div class="col-12">
        <button type="submit" class="btn btn-primary me-2">Save Customer</button>
        <a href="<?= BASE_URL ?>/modules/customers/index.php" class="btn btn-vp-primary">Cancel</a>
      </div>
    </div>
  </form>
</div></div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
