<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
if (!Auth::hasRole(['super_admin','hall_manager'])) { Helper::flash('error',Lang::t('access_denied')); Helper::redirect(BASE_URL.'/modules/packages/index.php'); }
$db = Database::getInstance();
$cu = Auth::currentUser();
$branches = $db->fetchAll("SELECT id,name FROM branches WHERE is_active=1");
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name       = trim($_POST['name'] ?? '');
    $branch_id  = (int)($_POST['branch_id'] ?? $cu['branch_id']);
    $description= trim($_POST['description'] ?? '');
    $price      = (float)($_POST['price'] ?? 0);
    $items      = $_POST['items'] ?? [];

    if (!$name) $errors[] = 'Package name required.';
    if (!$errors) {
        $pid = $db->insert("INSERT INTO packages (branch_id,name,description,price) VALUES (?,?,?,?)", [$branch_id,$name,$description,$price]);
        foreach ($items as $it) {
            $iname = trim($it['name'] ?? '');
            $qty   = (int)($it['qty'] ?? 1);
            if ($iname) $db->insert("INSERT INTO package_items (package_id,item_name,quantity) VALUES (?,?,?)", [$pid,$iname,$qty]);
        }
        Helper::flash('success','Package created.');
        Helper::redirect(BASE_URL.'/modules/packages/index.php');
    }
}

$pageTitle = 'Add Package';
$breadcrumbs = [['label'=>'Packages','url'=>BASE_URL.'/modules/packages/index.php'],['label'=>'Add Package']];
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="page-header"><div class="row"><div class="col"><h1 class="vp-page-title">Add Package</h1></div></div></div>
<div class="card vp-card"><div class="card-body">
  <?php if ($errors): foreach ($errors as $e): ?><div class="alert alert-danger"><?= Helper::sanitize($e) ?></div><?php endforeach; endif; ?>
  <form method="POST">
    <div class="row g-3">
      <div class="col-md-6"><label class="form-label">Package Name *</label>
        <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"></div>
      <div class="col-md-3"><label class="form-label">Price (Rs.)</label>
        <input type="number" name="price" class="form-control" step="0.01" value="<?= htmlspecialchars($_POST['price'] ?? '0') ?>"></div>
      <div class="col-md-3"><label class="form-label">Branch</label>
        <select name="branch_id" class="form-select">
          <?php foreach ($branches as $br): ?>
          <option value="<?= $br['id'] ?>" <?= (($_POST['branch_id'] ?? $cu['branch_id']) == $br['id']) ? 'selected' : '' ?>><?= Helper::sanitize($br['name']) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="col-12"><label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea></div>

      <div class="col-12">
        <h5 class="text-secondary">Package Items</h5>
        <div id="items-container">
          <?php
          $existingItems = $_POST['items'] ?? [['name'=>'','qty'=>1]];
          foreach ($existingItems as $i => $it):
          ?>
          <div class="row g-2 mb-2 item-row">
            <div class="col-md-8">
              <input type="text" name="items[<?= $i ?>][name]" class="form-control" placeholder="Item name (e.g. 500 Chairs, Basic Decoration)" value="<?= htmlspecialchars($it['name'] ?? '') ?>">
            </div>
            <div class="col-md-2">
              <input type="number" name="items[<?= $i ?>][qty]" class="form-control" placeholder="Qty" min="1" value="<?= (int)($it['qty'] ?? 1) ?>">
            </div>
            <div class="col-md-2">
              <button type="button" class="btn btn-outline-danger btn-sm remove-item">Remove</button>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <button type="button" id="add-item" class="btn btn-sm btn-outline-secondary mt-2">+ Add Item</button>
      </div>

      <div class="col-12">
        <button type="submit" class="btn btn-primary me-2">Save Package</button>
        <a href="<?= BASE_URL ?>/modules/packages/index.php" class="btn btn-vp-primary">Cancel</a>
      </div>
    </div>
  </form>
</div></div>
<script>
var itemIdx = <?= count($_POST['items'] ?? [['']]) ?>;
document.getElementById('add-item').addEventListener('click', function() {
    var c = document.getElementById('items-container');
    var row = document.createElement('div');
    row.className = 'row g-2 mb-2 item-row';
    row.innerHTML = '<div class="col-md-8"><input type="text" name="items['+itemIdx+'][name]" class="form-control" placeholder="Item name"></div><div class="col-md-2"><input type="number" name="items['+itemIdx+'][qty]" class="form-control" placeholder="Qty" min="1" value="1"></div><div class="col-md-2"><button type="button" class="btn btn-outline-danger btn-sm remove-item">Remove</button></div>';
    c.appendChild(row);
    itemIdx++;
});
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('remove-item')) {
        e.target.closest('.item-row').remove();
    }
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
