<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
if (!Auth::hasRole(['super_admin','hall_manager'])) { Helper::flash('error',Lang::t('access_denied')); Helper::redirect(($_GET['return']??'')==='settings' ? BASE_URL.'/modules/settings/index.php?tab=packages' : BASE_URL.'/modules/packages/index.php'); }
$db = Database::getInstance();
$id = (int)($_GET['id'] ?? 0);
$pkg = $db->fetchOne("SELECT * FROM packages WHERE id=?", [$id]);
if (!$pkg) { Helper::flash('error','Not found.'); Helper::redirect(($_GET['return']??'')==='settings' ? BASE_URL.'/modules/settings/index.php?tab=packages' : BASE_URL.'/modules/packages/index.php'); }
$existing_items = $db->fetchAll("SELECT * FROM package_items WHERE package_id=?", [$id]);
$branches = $db->fetchAll("SELECT id,name FROM branches WHERE is_active=1");
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name=$_POST['name']??''; $branch_id=(int)($_POST['branch_id']??$pkg['branch_id']);
    $description=$_POST['description']??''; $price=(float)($_POST['price']??0);
    $items=$_POST['items']??[];
    if (!$name) $errors[] = 'Name required.';
    if (!$errors) {
        $db->execute("UPDATE packages SET branch_id=?,name=?,description=?,price=? WHERE id=?", [$branch_id,$name,$description,$price,$id]);
        $db->execute("DELETE FROM package_items WHERE package_id=?", [$id]);
        foreach ($items as $it) {
            $iname=trim($it['name']??''); $qty=(int)($it['qty']??1);
            if ($iname) $db->insert("INSERT INTO package_items (package_id,item_name,quantity) VALUES (?,?,?)", [$id,$iname,$qty]);
        }
        Helper::flash('success','Package updated.');
        Helper::redirect(($_GET['return']??'')==='settings' ? BASE_URL.'/modules/settings/index.php?tab=packages' : BASE_URL.'/modules/packages/index.php');
    }
}

$pageTitle = 'Edit Package';
$breadcrumbs = [['label'=>'Packages','url'=>BASE_URL.'/modules/packages/index.php'],['label'=>'Edit']];
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="page-header"><div class="row"><div class="col"><h1 class="vp-page-title">Edit Package</h1></div></div></div>
<div class="card vp-card"><div class="card-body">
  <?php if ($errors): foreach ($errors as $e): ?><div class="alert alert-danger"><?= Helper::sanitize($e) ?></div><?php endforeach; endif; ?>
  <form method="POST" id="mainForm">
    <div class="row g-3">
      <div class="col-md-6"><label class="form-label">Package Name *</label>
        <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($_POST['name'] ?? $pkg['name']) ?>"></div>
      <div class="col-md-3"><label class="form-label">Price (Rs.)</label>
        <input type="number" name="price" class="form-control" step="0.01" value="<?= htmlspecialchars($_POST['price'] ?? $pkg['price']) ?>"></div>
      <div class="col-md-3"><label class="form-label">Branch</label>
        <select name="branch_id" class="form-select">
          <?php foreach ($branches as $br): ?>
          <option value="<?= $br['id'] ?>" <?= (($_POST['branch_id'] ?? $pkg['branch_id']) == $br['id']) ? 'selected' : '' ?>><?= Helper::sanitize($br['name']) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="col-12"><label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($_POST['description'] ?? $pkg['description']) ?></textarea></div>
      <div class="col-12"><h5 class="text-secondary">Package Items</h5>
        <div id="items-container">
          <?php $showItems = isset($_POST['items']) ? $_POST['items'] : $existing_items;
          foreach ($showItems as $i => $it): $iname = $it['name'] ?? $it['item_name'] ?? ''; $qty = $it['qty'] ?? $it['quantity'] ?? 1; ?>
          <div class="row g-2 mb-2 item-row">
            <div class="col-md-8"><input type="text" name="items[<?= $i ?>][name]" class="form-control" value="<?= htmlspecialchars($iname) ?>"></div>
            <div class="col-md-2"><input type="number" name="items[<?= $i ?>][qty]" class="form-control" min="1" value="<?= (int)$qty ?>"></div>
            <div class="col-md-2"><button type="button" class="btn btn-outline-danger btn-sm remove-item">Remove</button></div>
          </div>
          <?php endforeach; ?>
        </div>
        <button type="button" id="add-item" class="btn btn-sm btn-outline-secondary mt-2">+ Add Item</button>
      </div>
      <div class="col-12">
        <button type="submit" class="btn btn-primary me-2">Update</button>
        <a href="<?= BASE_URL ?>/modules/packages/index.php" class="btn btn-vp-primary">Cancel</a>
      </div>
    </div>
  </form>
</div></div>
<script>
var itemIdx = <?= count(isset($_POST['items']) ? $_POST['items'] : $existing_items) ?>;
document.getElementById('add-item').addEventListener('click', function() {
    var c = document.getElementById('items-container');
    var row = document.createElement('div');
    row.className = 'row g-2 mb-2 item-row';
    row.innerHTML = '<div class="col-md-8"><input type="text" name="items['+itemIdx+'][name]" class="form-control" placeholder="Item name"></div><div class="col-md-2"><input type="number" name="items['+itemIdx+'][qty]" class="form-control" placeholder="Qty" min="1" value="1"></div><div class="col-md-2"><button type="button" class="btn btn-outline-danger btn-sm remove-item">Remove</button></div>';
    c.appendChild(row); itemIdx++;
});
document.addEventListener('click', function(e) { if (e.target.classList.contains('remove-item')) e.target.closest('.item-row').remove(); });
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
