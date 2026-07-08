<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
if (!Auth::hasRole(['super_admin','admin','hall_manager','manager'])) { Helper::flash('error', Lang::t('access_denied')); Helper::redirect(($_GET['return']??'')==='settings' ? BASE_URL.'/modules/settings/index.php?tab=rooms' : BASE_URL.'/modules/rooms/index.php'); }
$db = Database::getInstance();
$cu = Auth::currentUser();
$branches   = $db->fetchAll("SELECT id,name FROM branches WHERE is_active=1");
$room_types = $db->fetchAll("SELECT id,name FROM room_types ORDER BY name");
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branch_id      = (int)($_POST['branch_id'] ?? $cu['branch_id']);
    $room_type_id   = (int)($_POST['room_type_id'] ?? 0);
    $room_number    = trim($_POST['room_number'] ?? '');
    $name           = trim($_POST['name'] ?? '');
    $capacity       = (int)($_POST['capacity'] ?? 1);
    $rate           = (float)($_POST['rate_per_night'] ?? 0);
    $description    = trim($_POST['description'] ?? '');
    $status         = $_POST['status'] ?? 'available';
    $is_active      = isset($_POST['is_active']) ? 1 : 0;

    if (!$room_number) $errors[] = 'Room number is required.';
    if (!$name)        $errors[] = 'Room name is required.';

    if (!$errors) {
        $newId = $db->insert(
            "INSERT INTO rooms (branch_id,room_type_id,room_number,name,capacity,rate_per_night,description,status,is_active) VALUES (?,?,?,?,?,?,?,?,?)",
            [$branch_id, $room_type_id ?: null, $room_number, $name, $capacity, $rate, $description, $status, $is_active]
        );
        Logger::log('create','rooms',$newId,$room_number,null,'room:'.$room_number.' name:'.$name,'Room created');
        Helper::flash('success', 'Room added.');
        Helper::redirect(($_GET['return']??'')==='settings' ? BASE_URL.'/modules/settings/index.php?tab=rooms' : BASE_URL.'/modules/rooms/index.php');
    }
}

$pageTitle = 'Add Room';
$breadcrumbs = [['label'=>'Rooms','url'=>BASE_URL.'/modules/rooms/index.php'],['label'=>'Add Room']];
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="page-header"><div class="row"><div class="col"><h1 class="vp-page-title">Add Room</h1></div></div></div>
<div class="card vp-card"><div class="card-body">
  <?php if ($errors): foreach ($errors as $e): ?><div class="alert alert-danger"><?= Helper::sanitize($e) ?></div><?php endforeach; endif; ?>
  <form method="POST" id="mainForm">
    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label">Room Number *</label>
        <input type="text" name="room_number" class="form-control" required value="<?= htmlspecialchars($_POST['room_number'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Room Name *</label>
        <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Room Type</label>
        <select name="room_type_id" class="form-select">
          <option value="">-- Select --</option>
          <?php foreach ($room_types as $rt): ?>
          <option value="<?= $rt['id'] ?>" <?= (($_POST['room_type_id'] ?? '') == $rt['id']) ? 'selected' : '' ?>><?= Helper::sanitize($rt['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Capacity</label>
        <input type="number" name="capacity" class="form-control" min="1" value="<?= (int)($_POST['capacity'] ?? 1) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Rate per Night (Rs.)</label>
        <input type="number" name="rate_per_night" class="form-control" step="0.01" min="0" value="<?= htmlspecialchars($_POST['rate_per_night'] ?? '0') ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
          <option value="available" <?= (($_POST['status'] ?? 'available') === 'available') ? 'selected' : '' ?>>Available</option>
          <option value="reserved" <?= (($_POST['status'] ?? '') === 'reserved') ? 'selected' : '' ?>>Reserved</option>
          <option value="occupied" <?= (($_POST['status'] ?? '') === 'occupied') ? 'selected' : '' ?>>Occupied</option>
          <option value="maintenance" <?= (($_POST['status'] ?? '') === 'maintenance') ? 'selected' : '' ?>>Maintenance</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Branch</label>
        <select name="branch_id" class="form-select">
          <?php foreach ($branches as $br): ?>
          <option value="<?= $br['id'] ?>" <?= (($_POST['branch_id'] ?? $cu['branch_id']) == $br['id']) ? 'selected' : '' ?>><?= Helper::sanitize($br['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
      </div>
      <div class="col-12">
        <button type="submit" class="btn btn-primary me-2">Save Room</button>
        <a href="<?= BASE_URL ?>/modules/rooms/index.php" class="btn btn-vp-primary">Cancel</a>
      </div>
    </div>
  </form>
</div></div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
