<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
$db = Database::getInstance();
$id = (int)($_GET['id'] ?? 0);
$c = $db->fetchOne("SELECT * FROM customers WHERE id=?", [$id]);
if (!$c) { Helper::flash('error','Not found.'); Helper::redirect(BASE_URL.'/modules/customers/index.php'); }
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name=$_POST['name']??''; $bride_name=$_POST['bride_name']??''; $groom_name=$_POST['groom_name']??'';
    $nic=$_POST['nic']??''; $address=$_POST['address']??''; $city=$_POST['city']??'';
    $mobile=$_POST['mobile']??''; $mobile2=$_POST['mobile2']??''; $email=$_POST['email']??''; $notes=$_POST['notes']??'';
    if (!$name) $errors[] = 'Name required.';
    if (!$mobile) $errors[] = 'Mobile required.';
    elseif (!preg_match('/^\d{10}$/', $mobile)) $errors[] = 'Mobile number must be exactly 10 digits.';
    if (!$errors) {
        Logger::log('edit','customers',$id,$c['name'],'name:'.$c['name'].' mobile:'.$c['mobile'],'name:'.$name.' mobile:'.$mobile,'Customer updated');
        $db->execute(
            "UPDATE customers SET name=?,bride_name=?,groom_name=?,nic=?,address=?,city=?,mobile=?,mobile2=?,email=?,notes=?,updated_at=NOW() WHERE id=?",
            [$name,$bride_name,$groom_name,$nic,$address,$city,$mobile,$mobile2,$email,$notes,$id]
        );
        Helper::flash('success','Customer updated.');
        Helper::redirect(BASE_URL.'/modules/customers/view.php?id='.$id);
    }
}

$pageTitle = 'Edit Customer';
$breadcrumbs = [['label'=>'Customers','url'=>BASE_URL.'/modules/customers/index.php'],['label'=>$c['name'],'url'=>BASE_URL.'/modules/customers/view.php?id='.$id],['label'=>'Edit']];
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="page-header"><div class="row"><div class="col"><h1 class="vp-page-title">Edit Customer</h1></div></div></div>
<div class="card vp-card"><div class="card-body">
  <?php if ($errors): foreach ($errors as $e): ?><div class="alert alert-danger"><?= Helper::sanitize($e) ?></div><?php endforeach; endif; ?>
  <form method="POST">
    <div class="row g-3">
      <div class="col-md-4"><label class="form-label">Full Name *</label>
        <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($_POST['name'] ?? $c['name']) ?>"></div>
      <div class="col-md-4"><label class="form-label">NIC</label>
        <input type="text" name="nic" class="form-control" value="<?= htmlspecialchars($_POST['nic'] ?? $c['nic']) ?>"></div>
      <div class="col-md-4"><label class="form-label">City</label>
        <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($_POST['city'] ?? $c['city']) ?>"></div>
      <div class="col-md-6"><label class="form-label">Bride Name</label>
        <input type="text" name="bride_name" class="form-control" value="<?= htmlspecialchars($_POST['bride_name'] ?? $c['bride_name']) ?>"></div>
      <div class="col-md-6"><label class="form-label">Groom Name</label>
        <input type="text" name="groom_name" class="form-control" value="<?= htmlspecialchars($_POST['groom_name'] ?? $c['groom_name']) ?>"></div>
      <div class="col-md-4"><label class="form-label">Mobile *</label>
        <input type="tel" name="mobile" class="form-control" required value="<?= htmlspecialchars($_POST['mobile'] ?? $c['mobile']) ?>"></div>
      <div class="col-md-4"><label class="form-label">Mobile 2</label>
        <input type="tel" name="mobile2" class="form-control" value="<?= htmlspecialchars($_POST['mobile2'] ?? $c['mobile2']) ?>"></div>
      <div class="col-md-4"><label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? $c['email']) ?>"></div>
      <div class="col-12"><label class="form-label">Address</label>
        <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($_POST['address'] ?? $c['address']) ?></textarea></div>
      <div class="col-12"><label class="form-label">Notes</label>
        <textarea name="notes" class="form-control" rows="2"><?= htmlspecialchars($_POST['notes'] ?? $c['notes']) ?></textarea></div>
      <div class="col-12">
        <button type="submit" class="btn btn-primary me-2">Update</button>
        <a href="<?= BASE_URL ?>/modules/customers/view.php?id=<?= $id ?>" class="btn btn-vp-primary">Cancel</a>
      </div>
    </div>
  </form>
</div></div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
