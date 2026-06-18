<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
$db = Database::getInstance();
$cu = Auth::currentUser();
$bid = $cu['branch_id'];

$rooms = $db->fetchAll(
    "SELECT r.*, rt.name as type_name, b.name as branch_name FROM rooms r
     LEFT JOIN room_types rt ON r.room_type_id=rt.id
     LEFT JOIN branches b ON r.branch_id=b.id
     WHERE 1 " . ($bid ? "AND r.branch_id=?" : "") . " ORDER BY r.room_number",
    $bid ? [$bid] : []
);

$pageTitle  = Lang::t('rooms');
$breadcrumbs = [['label' => 'Venue Setup'], ['label' => Lang::t('rooms')]];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="vp-page-header">
  <div>
    <h1 class="vp-page-title">🛏️ <?= Lang::t('rooms') ?></h1>
    <div class="vp-page-sub"><?= count($rooms) ?> rooms configured</div>
  </div>
  <?php if (Auth::hasRole(['super_admin','hall_manager'])): ?>
  <a href="<?= BASE_URL ?>/modules/rooms/create.php" class="btn btn-vp-gold">
    <svg xmlns="http://www.w3.org/2000/svg" class="icon me-1" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14"/><path d="M5 12l14 0"/></svg>
    Add Room
  </a>
  <?php endif; ?>
</div>

<div class="card vp-card">
  <div class="table-responsive">
    <table class="table table-vcenter vp-table mb-0">
      <thead>
        <tr>
          <th>Room No</th>
          <th>Name</th>
          <th>Type</th>
          <th>Capacity</th>
          <th>Rate / Night</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($rooms): foreach ($rooms as $r): ?>
        <tr>
          <td><span class="fw-700 text-vp-navy"><?= Helper::sanitize($r['room_number']) ?></span></td>
          <td><?= Helper::sanitize($r['name']) ?></td>
          <td><?= Helper::sanitize($r['type_name'] ?? '—') ?></td>
          <td><?= $r['capacity'] ?> pax</td>
          <td class="fw-600"><?= Helper::formatCurrency($r['rate_per_night']) ?></td>
          <td><?= Helper::statusBadge($r['status']) ?></td>
          <td>
            <?php if (Auth::hasRole(['super_admin','hall_manager'])): ?>
            <a href="<?= BASE_URL ?>/modules/rooms/edit.php?id=<?= $r['id'] ?>" class="btn btn-vp-primary btn-sm">Edit</a>
            <a href="<?= BASE_URL ?>/modules/rooms/delete.php?id=<?= $r['id'] ?>" class="btn btn-vp-danger btn-sm" onclick="return confirm('Delete this room?')">Delete</a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr>
          <td colspan="7">
            <div class="empty-state">
              <div class="empty-icon">🛏️</div>
              <div class="empty-text">No rooms configured yet.</div>
            </div>
          </td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
