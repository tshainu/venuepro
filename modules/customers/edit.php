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
    elseif (!preg_match('/^07\d{8}$/', $mobile)) $errors[] = 'Mobile number must be 10 digits and start with 07 (e.g. 0771234567).';
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
        <input type="tel" name="mobile" id="edit_mobile" class="form-control" required
          value="<?= htmlspecialchars($_POST['mobile'] ?? $c['mobile']) ?>"
          placeholder="07XXXXXXXX" pattern="07[0-9]{8}" maxlength="10" inputmode="numeric"
          oninput="validateEditMobile(this)" onkeydown="blockEditMobileKey(event,this)"
          title="Must be 10 digits starting with 07">
        <div class="form-hint" id="edit-mobile-hint" style="font-size:.75rem;margin-top:.25rem;">Must be 10 digits starting with 07</div>
      </div>
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
<script>
function blockEditMobileKey(e, el) {
  var allowed = [8,9,27,37,38,39,40,46,35,36];
  if (allowed.indexOf(e.keyCode) !== -1) return;
  if ((e.ctrlKey||e.metaKey) && [65,67,86,88].indexOf(e.keyCode) !== -1) return;
  if (e.key && !/^[0-9]$/.test(e.key)) { e.preventDefault(); return; }
  if (el.value.replace(/\D/g,'').length >= 10) { e.preventDefault(); return; }
  var cur = el.value.replace(/\D/g,'');
  if (cur.length === 0 && e.key !== '0') { e.preventDefault(); setEditMobileHint('\u2715 Must start with 07','#dc2626'); return; }
  if (cur.length === 1 && cur[0] === '0' && e.key !== '7') { e.preventDefault(); setEditMobileHint('\u2715 Must start with 07','#dc2626'); return; }
}
function validateEditMobile(el) {
  var v = el.value.replace(/\D/g,'');
  if (v.length > 10) v = v.substring(0,10);
  el.value = v;
  if (v.length === 0) {
    el.classList.remove('is-invalid','is-valid');
    setEditMobileHint('Must be 10 digits starting with 07','');
  } else if (!v.startsWith('07')) {
    el.classList.add('is-invalid'); el.classList.remove('is-valid');
    setEditMobileHint('\u2715 Must start with 07 (e.g. 0771234567)','#dc2626');
  } else if (v.length === 10) {
    el.classList.add('is-valid'); el.classList.remove('is-invalid');
    setEditMobileHint('\u2713 Valid mobile number','#059669');
  } else {
    el.classList.remove('is-invalid','is-valid');
    setEditMobileHint((10-v.length)+' more digit(s) needed','#d97706');
  }
}
function setEditMobileHint(msg, color) {
  var h = document.getElementById('edit-mobile-hint');
  if (h) { h.textContent = msg; h.style.color = color || '#9ca3af'; }
}
// Validate pre-filled value on load
var em = document.getElementById('edit_mobile');
if (em && em.value) validateEditMobile(em);
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
