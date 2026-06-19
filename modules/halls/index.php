<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
$db = Database::getInstance();
$cu = Auth::currentUser();
$bid = $cu['branch_id'];

$halls = $db->fetchAll(
    "SELECT h.*, b.name as branch_name FROM halls h LEFT JOIN branches b ON h.branch_id=b.id WHERE 1 " . ($bid ? "AND h.branch_id=?" : "") . " ORDER BY h.name",
    $bid ? [$bid] : []
);

$pageTitle  = Lang::t('halls');
$breadcrumbs = [['label' => 'Venue Setup'], ['label' => Lang::t('halls')]];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="vp-page-header">
  <div>
    <h1 class="vp-page-title">🏛️ <?= Lang::t('halls') ?></h1>
    <div class="vp-page-sub"><?= count($halls) ?> halls configured</div>
  </div>
  <?php if (Auth::hasRole(['super_admin','admin','hall_manager'])): ?>
  <a href="<?= BASE_URL ?>/modules/halls/create.php" class="btn btn-vp-gold">
    <svg xmlns="http://www.w3.org/2000/svg" class="icon me-1" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14"/><path d="M5 12l14 0"/></svg>
    Add Hall
  </a>
  <?php endif; ?>
</div>

<div class="card vp-card">
  <div class="table-responsive">
    <table class="table table-vcenter vp-table mb-0">
      <thead>
        <tr>
          <th>Hall Name</th>
          <th>Branch</th>
          <th>Capacity</th>
          <th>Price / Day</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($halls): foreach ($halls as $h): ?>
        <tr>
          <td>
            <div class="d-flex align-items-center gap-2">
              <?php if ($h['image']): ?>
              <span class="avatar avatar-sm" style="background-image:url(<?= BASE_URL ?>/uploads/halls/<?= $h['image'] ?>);border-radius:8px;"></span>
              <?php else: ?>
              <span class="vp-avatar vp-avatar-sm" style="border-radius:8px;">🏛</span>
              <?php endif; ?>
              <span class="fw-700"><?= Helper::sanitize($h['name']) ?></span>
            </div>
          </td>
          <td><?= Helper::sanitize($h['branch_name']) ?></td>
          <td><?= number_format($h['capacity']) ?> guests</td>
          <td class="fw-600"><?= Helper::formatCurrency($h['price_per_day']) ?></td>
          <td><?= $h['is_active'] ? '<span class="vp-badge badge-active">Active</span>' : '<span class="vp-badge badge-inactive">Inactive</span>' ?></td>
          <td>
            <a href="<?= BASE_URL ?>/modules/halls/view.php?id=<?= $h['id'] ?>" class="btn btn-vp-outline btn-sm">View</a>
            <?php if (Auth::hasRole(['super_admin','admin','hall_manager'])): ?>
            <a href="<?= BASE_URL ?>/modules/halls/edit.php?id=<?= $h['id'] ?>" class="btn btn-vp-primary btn-sm">Edit</a>
            <a href="<?= BASE_URL ?>/modules/halls/delete.php?id=<?= $h['id'] ?>" class="btn btn-vp-danger btn-sm" onclick="return confirm('Delete this hall?')">Delete</a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr>
          <td colspan="6">
            <div class="empty-state">
              <div class="empty-icon">🏛️</div>
              <div class="empty-text">No halls configured yet.</div>
            </div>
          </td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
