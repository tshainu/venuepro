<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
if (!Auth::hasRole(['super_admin','admin','owner'])) { Helper::flash('error','Access denied.'); Helper::redirect(BASE_URL.'/index.php'); }
$db = Database::getInstance();
$cu = Auth::currentUser();
// Fetch business info from sa_businesses using the current user's user_id
$user_uid = $_SESSION['user_uid'] ?? '';
$biz_info = $db->fetchOne("SELECT id, max_branches FROM sa_businesses WHERE admin_user_id = ?", [$user_uid]);
$max_branches = $biz_info ? (int)$biz_info['max_branches'] : 1;
$sa_biz_id    = $biz_info ? (int)$biz_info['id'] : 0;
// super_admin sees all; owner/admin sees all branches belonging to their business
if (Auth::isSuperAdmin()) {
    $branch_filter = '';
    $branch_params = [];
} elseif ($sa_biz_id) {
    $branch_filter = 'WHERE b.business_id = ?';
    $branch_params = [$sa_biz_id];
} else {
    // Fallback: show only the user's own branch
    $branch_filter = 'WHERE b.id = ?';
    $branch_params = [$cu['branch_id']];
}
$branches = $db->fetchAll(
    "SELECT b.*,
       (SELECT COUNT(*) FROM users WHERE branch_id=b.id) as user_count,
       (SELECT COUNT(*) FROM halls WHERE branch_id=b.id) as hall_count
     FROM branches b $branch_filter ORDER BY b.name",
    $branch_params
);
$pageTitle  = 'Branches';
$breadcrumbs = [['label' => 'Branches']];
require_once ROOT_PATH . '/includes/header.php';
?>
<div class="vp-page-header">
  <div>
    <h1 class="vp-page-title">🌿 <?= Lang::t('branches') ?></h1>
    <div class="vp-page-sub"><?= count($branches) ?> / <?= $max_branches ?> branches configured</div>
  </div>
  <?php if (count($branches) < $max_branches): ?>
  <a href="<?= BASE_URL ?>/modules/branches/create.php" class="btn btn-vp-gold">
    <svg xmlns="http://www.w3.org/2000/svg" class="icon me-1" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14"/><path d="M5 12l14 0"/></svg>
    New Branch
  </a>
  <?php else: ?>
  <button class="btn btn-vp-gold" disabled title="Branch limit reached. Contact Super Admin to increase your limit.">New Branch (Limit Reached)</button>
  <?php endif; ?>
</div>
<?php if ($branches): ?>
<div class="row g-3">
  <?php foreach ($branches as $br): ?>
  <div class="col-md-6 col-lg-4">
    <div class="card vp-card h-100">
      <div class="card-header">
        <div>
          <div class="fw-800 text-vp-navy" style="font-size:.95rem;"><?= Helper::sanitize($br['name']) ?></div>
          <?php if ($br['email']): ?><div class="text-secondary" style="font-size:.72rem;"><?= Helper::sanitize($br['email']) ?></div><?php endif; ?>
        </div>
        <?= $br['is_active'] ? '<span class="vp-badge badge-active">Active</span>' : '<span class="vp-badge badge-inactive">Inactive</span>' ?>
      </div>
      <div class="card-body">
        <?php if ($br['address']): ?>
        <div class="mb-2 text-secondary" style="font-size:.8rem;">
          📍 <?= nl2br(Helper::sanitize($br['address'])) ?>
        </div>
        <?php endif; ?>
        <?php if ($br['phone']): ?>
        <div class="mb-1 text-secondary" style="font-size:.8rem;">📞 <?= Helper::sanitize($br['phone']) ?></div>
        <?php endif; ?>
        <div class="mt-3 d-flex gap-3">
          <div class="text-center">
            <div class="fw-800 text-vp-navy" style="font-size:1.1rem;"><?= $br['user_count'] ?></div>
            <div class="text-secondary" style="font-size:.68rem;text-transform:uppercase;letter-spacing:.05em;">Users</div>
          </div>
          <div class="text-center">
            <div class="fw-800 text-vp-navy" style="font-size:1.1rem;"><?= $br['hall_count'] ?></div>
            <div class="text-secondary" style="font-size:.68rem;text-transform:uppercase;letter-spacing:.05em;">Halls</div>
          </div>
        </div>
      </div>
      <div class="card-footer d-flex gap-2">
        <a href="<?= BASE_URL ?>/modules/branches/edit.php?id=<?= $br['id'] ?>" class="btn btn-vp-primary btn-sm">Edit</a>
        <a href="<?= BASE_URL ?>/modules/branches/delete.php?id=<?= $br['id'] ?>" class="btn btn-vp-danger btn-sm" onclick="return confirm('Delete this branch?')">Delete</a>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php else: ?>
<div class="card vp-card">
  <div class="card-body">
    <div class="empty-state">
      <div class="empty-icon">🌿</div>
      <div class="empty-text">No branches yet. Create one to get started.</div>
    </div>
  </div>
</div>
<?php endif; ?>
<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
