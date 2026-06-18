<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
if (!Auth::hasRole(['super_admin','hall_manager'])) { Helper::flash('error','Access denied.'); Helper::redirect(BASE_URL.'/modules/addons/index.php'); }
$db = Database::getInstance();
$cu = Auth::currentUser();

$categories = $db->fetchAll("SELECT * FROM addon_categories ORDER BY name");
$branches = $db->fetchAll("SELECT id,name FROM branches WHERE is_active=1");
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0) ?: null;
    $branch_id   = $cu['branch_id'] ?? ((int)($_POST['branch_id'] ?? 0) ?: null);
    $description = trim($_POST['description'] ?? '');
    $price       = (float)($_POST['price'] ?? 0);
    $unit        = trim($_POST['unit'] ?? 'per event');
    $tax_percent = (float)($_POST['tax_percent'] ?? 0);
    $is_available= isset($_POST['is_available']) ? 1 : 0;

    if (!$name) $errors[] = 'Name is required.';
    if ($price < 0) $errors[] = 'Price cannot be negative.';

    if (!$errors) {
        $db->insert(
            "INSERT INTO addons (branch_id,category_id,name,description,price,unit,tax_percent,is_available) VALUES (?,?,?,?,?,?,?,?)",
            [$branch_id, $category_id, $name, $description, $price, $unit, $tax_percent, $is_available]
        );
        Helper::flash('success', "Add-on '$name' created.");
        Helper::redirect(BASE_URL.'/modules/addons/index.php');
    }
}

$pageTitle = 'New Add-on';
$breadcrumbs = [['label'=>'Add-ons','url'=>BASE_URL.'/modules/addons/index.php'],['label'=>'New']];
require_once ROOT_PATH . '/includes/header.php';
?>
<div class="vp-page-header d-print-none">
  <div class="d-flex align-items-center">
    <div><h1 class="vp-page-title">New Add-on</h1></div>
  </div>
</div>
<div class="row justify-content-center">
  <div class="col-lg-7">
    <form method="post" class="card vp-card">
      <div class="card-header"><h3 class="card-title">Add-on Details</h3></div>
      <div class="card-body">
        <?php if ($errors): ?>
        <div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e): ?><li><?= Helper::sanitize($e) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <div class="mb-3">
          <label class="form-label required">Name</label>
          <input type="text" name="name" class="form-control" value="<?= Helper::sanitize($_POST['name']??'') ?>" required>
        </div>

        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">Category</label>
            <select name="category_id" class="form-select">
              <option value="">— None —</option>
              <?php foreach ($categories as $c): ?>
              <option value="<?= $c['id'] ?>" <?= ($_POST['category_id']??'')==$c['id']?'selected':'' ?>><?= Helper::sanitize($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php if (Auth::isSuperAdmin()): ?>
          <div class="col-md-6">
            <label class="form-label">Branch</label>
            <select name="branch_id" class="form-select">
              <option value="">All Branches</option>
              <?php foreach ($branches as $b): ?>
              <option value="<?= $b['id'] ?>" <?= ($_POST['branch_id']??'')==$b['id']?'selected':'' ?>><?= Helper::sanitize($b['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>
        </div>

        <div class="mb-3">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="2"><?= Helper::sanitize($_POST['description']??'') ?></textarea>
        </div>

        <div class="row mb-3">
          <div class="col-md-4">
            <label class="form-label required">Price (Rs.)</label>
            <input type="number" name="price" class="form-control" step="0.01" min="0" value="<?= $_POST['price']??'0' ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Unit</label>
            <input type="text" name="unit" class="form-control" placeholder="per event" value="<?= Helper::sanitize($_POST['unit']??'per event') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Tax %</label>
            <input type="number" name="tax_percent" class="form-control" step="0.01" min="0" max="100" value="<?= $_POST['tax_percent']??'0' ?>">
          </div>
        </div>

        <div class="mb-3">
          <label class="form-check">
            <input type="checkbox" name="is_available" class="form-check-input" <?= (!isset($_POST['name']) || isset($_POST['is_available'])) ? 'checked' : '' ?>>
            <span class="form-check-label">Available for booking</span>
          </label>
        </div>
      </div>
      <div class="card-footer d-flex gap-2">
        <button type="submit" class="btn btn-vp-gold">Save Add-on</button>
        <a href="<?= BASE_URL ?>/modules/addons/index.php" class="btn btn-vp-outline">Cancel</a>
      </div>
    </form>
  </div>
</div>
<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
