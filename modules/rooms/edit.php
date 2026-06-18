<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
if (!Auth::hasRole(['super_admin','hall_manager'])) { Helper::flash('error', Lang::t('access_denied')); Helper::redirect(($_GET['return']??'')==='settings' ? BASE_URL.'/modules/settings/index.php?tab=rooms' : BASE_URL.'/modules/rooms/index.php'); }
$db = Database::getInstance();
$id = (int)($_GET['id'] ?? 0);
$room = $db->fetchOne("SELECT * FROM rooms WHERE id=?", [$id]);
if (!$room) { Helper::flash('error', 'Room not found.'); Helper::redirect(($_GET['return']??'')==='settings' ? BASE_URL.'/modules/settings/index.php?tab=rooms' : BASE_URL.'/modules/rooms/index.php'); }
$branches   = $db->fetchAll("SELECT id,name FROM branches WHERE is_active=1");
$room_types = $db->fetchAll("SELECT id,name FROM room_types ORDER BY name");
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branch_id    = (int)($_POST['branch_id'] ?? $room['branch_id']);
    $room_type_id = (int)($_POST['room_type_id'] ?? 0);
    $room_number  = trim($_POST['room_number'] ?? '');
    $name         = trim($_POST['name'] ?? '');
    $capacity     = (int)($_POST['capacity'] ?? 1);
    $rate         = (float)($_POST['rate_per_night'] ?? 0);
    $description  = trim($_POST['description'] ?? '');
    $status       = $_POST['status'] ?? 'available';
    $is_active    = isset($_POST['is_active']) ? 1 : 0;
    if (!$room_number || !$name) $errors[] = 'Room number and name are required.';
    if (!$errors) {
        $db->execute(
            "UPDATE rooms SET branch_id=?,room_type_id=?,room_number=?,name=?,capacity=?,rate_per_night=?,description=?,status=?,is_active=? WHERE id=?",
            [$branch_id, $room_type_id ?: null, $room_number, $name, $capacity, $rate, $description, $status, $is_active, $id]
        );
        Helper::flash('success', 'Room updated.');
        Helper::redirect(($_GET['return']??'')==='settings' ? BASE_URL.'/modules/settings/index.php?tab=rooms' : BASE_URL.'/modules/rooms/index.php');
    }
}

$pageTitle = 'Edit Room';
$breadcrumbs = [['label'=>'Rooms','url'=>BASE_URL.'/modules/rooms/index.php'],['label'=>'Edit']];
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="page-header"><div class="row"><div class="col"><h1 class="vp-page-title">Edit Room</h1></div></div></div>
<div class="card vp-card"><div class="card-body">
  <?php if ($errors): foreach ($errors as $e): ?><div class="alert alert-danger"><?= Helper::sanitize($e) ?></div><?php endforeach; endif; ?>
  <form method="POST" id="mainForm">
    <div class="row g-3">
      <div class="col-md-4"><label class="form-label">Room Number *</label>
        <input type="text" name="room_number" class="form-control" required value="<?= htmlspecialchars($_POST['room_number'] ?? $room['room_number']) ?>"></div>
      <div class="col-md-4"><label class="form-label">Room Name *</label>
        <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($_POST['name'] ?? $room['name']) ?>"></div>
      <div class="col-md-4"><label class="form-label">Room Type</label>
        <select name="room_type_id" class="form-select">
          <option value="">-- Select --</option>
          <?php foreach ($room_types as $rt): ?>
          <option value="<?= $rt['id'] ?>" <?= (($_POST['room_type_id'] ?? $room['room_type_id']) == $rt['id']) ? 'selected' : '' ?>><?= Helper::sanitize($rt['name']) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="col-md-3"><label class="form-label">Capacity</label>
        <input type="number" name="capacity" class="form-control" min="1" value="<?= (int)($_POST['capacity'] ?? $room['capacity']) ?>"></div>
      <div class="col-md-3"><label class="form-label">Rate/Night (Rs.)</label>
        <input type="number" name="rate_per_night" class="form-control" step="0.01" value="<?= htmlspecialchars($_POST['rate_per_night'] ?? $room['rate_per_night']) ?>"></div>
      <div class="col-md-3"><label class="form-label">Status</label>
        <select name="status" class="form-select">
          <?php foreach (['available','reserved','occupied','maintenance'] as $s): ?>
          <option value="<?= $s ?>" <?= (($_POST['status'] ?? $room['status']) === $s) ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="col-md-3"><label class="form-label">Branch</label>
        <select name="branch_id" class="form-select">
          <?php foreach ($branches as $br): ?>
          <option value="<?= $br['id'] ?>" <?= (($_POST['branch_id'] ?? $room['branch_id']) == $br['id']) ? 'selected' : '' ?>><?= Helper::sanitize($br['name']) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="col-12"><label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($_POST['description'] ?? $room['description']) ?></textarea></div>
      <div class="col-12">
        <button type="submit" class="btn btn-primary me-2">Update Room</button>
        <a href="<?= BASE_URL ?>/modules/rooms/index.php" class="btn btn-vp-primary">Cancel</a>
      </div>
    </div>
  </form>
</div></div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
