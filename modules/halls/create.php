<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
if (!Auth::hasRole(['super_admin','admin','hall_manager','manager'])) { Helper::flash('error', Lang::t('access_denied')); Helper::redirect(BASE_URL.'/modules/halls/index.php'); }

$db = Database::getInstance();
$cu = Auth::currentUser();
$branches = $db->fetchAll("SELECT id, name FROM branches WHERE is_active=1");
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name'] ?? '');
    $branch_id   = (int)($_POST['branch_id'] ?? $cu['branch_id']);
    $capacity    = (int)($_POST['capacity'] ?? 0);
    $price       = (float)($_POST['price_per_day'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $facilities  = trim($_POST['facilities'] ?? '');
    $is_active   = isset($_POST['is_active']) ? 1 : 0;

    if (!$name) $errors[] = 'Hall name is required.';

    // Image upload
    $image = null;
    if (!empty($_FILES['image']['name'])) {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','webp'])) {
            $image = uniqid('hall_') . '.' . $ext;
            move_uploaded_file($_FILES['image']['tmp_name'], UPLOAD_PATH . 'halls/' . $image);
        }
    }

    if (!$errors) {
        $newId = $db->insert(
            "INSERT INTO halls (branch_id, name, description, capacity, price_per_day, facilities, image, is_active) VALUES (?,?,?,?,?,?,?,?)",
            [$branch_id, $name, $description, $capacity, $price, $facilities, $image, $is_active]
        );
        Logger::log('create','halls',$newId,$name,null,'name:'.$name.' capacity:'.$capacity,'Hall created');
        Helper::flash('success', 'Hall added successfully.');
        Helper::redirect(($_GET['return']??'')==='settings' ? BASE_URL.'/modules/settings/index.php?tab=halls' : BASE_URL.'/modules/halls/index.php');
    }
}

$pageTitle = 'Add Hall';
$breadcrumbs = [['label'=>'Halls','url'=>BASE_URL.'/modules/halls/index.php'],['label'=>'Add Hall']];
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="vp-page-header d-print-none">
  <div class="row align-items-center">
    <div class="col"><h1 class="vp-page-title">Add Hall</h1></div>
  </div>
</div>
<div class="card vp-card">
  <div class="card-body">
    <?php if ($errors): foreach ($errors as $e): ?>
    <div class="alert alert-danger"><?= Helper::sanitize($e) ?></div>
    <?php endforeach; endif; ?>
    <form method="POST" id="mainForm" enctype="multipart/form-data">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Hall Name *</label>
          <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Branch *</label>
          <select name="branch_id" class="form-select" required>
            <?php foreach ($branches as $br): ?>
            <option value="<?= $br['id'] ?>" <?= (($_POST['branch_id'] ?? $cu['branch_id']) == $br['id']) ? 'selected' : '' ?>><?= Helper::sanitize($br['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Capacity (guests)</label>
          <input type="number" name="capacity" class="form-control" min="0" value="<?= (int)($_POST['capacity'] ?? 0) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Price per Day (Rs.)</label>
          <input type="number" name="price_per_day" class="form-control" step="0.01" min="0" value="<?= htmlspecialchars($_POST['price_per_day'] ?? '0') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Status</label>
          <div class="mt-2">
            <label class="form-check form-switch">
              <input class="form-check-input" type="checkbox" name="is_active" value="1" <?= (!isset($_POST['is_active']) || $_POST['is_active']) ? 'checked' : '' ?>>
              <span class="form-check-label">Active</span>
            </label>
          </div>
        </div>
        <div class="col-12">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
        </div>
        <div class="col-12">
          <label class="form-label">Facilities</label>
          <textarea name="facilities" class="form-control" rows="2" placeholder="Air conditioning, Parking, Generator..."><?= htmlspecialchars($_POST['facilities'] ?? '') ?></textarea>
        </div>
        <div class="col-md-6">
          <label class="form-label">Hall Image</label>
          <input type="file" name="image" class="form-control" accept="image/*">
        </div>
        <div class="col-12">
          <button type="submit" class="btn btn-primary me-2">Save Hall</button>
          <a href="<?= ($_GET['return']??'')==='settings' ? BASE_URL.'/modules/settings/index.php?tab=halls' : BASE_URL.'/modules/halls/index.php' ?>">Cancel</a>
        </div>
      </div>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
