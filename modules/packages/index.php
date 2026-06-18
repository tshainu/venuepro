<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
$db = Database::getInstance();
$cu = Auth::currentUser();
$bid = $cu['branch_id'];

$packages = $db->fetchAll(
    "SELECT p.*, b.name as branch_name FROM packages p LEFT JOIN branches b ON p.branch_id=b.id WHERE 1 " . ($bid ? "AND p.branch_id=?" : "") . " ORDER BY p.name",
    $bid ? [$bid] : []
);

$pageTitle  = Lang::t('packages');
$breadcrumbs = [['label' => Lang::t('packages')]];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="vp-page-header">
  <div>
    <h1 class="vp-page-title">📦 <?= Lang::t('packages') ?></h1>
    <div class="vp-page-sub"><?= count($packages) ?> packages available</div>
  </div>
  <?php if (Auth::hasRole(['super_admin','hall_manager'])): ?>
  <a href="<?= BASE_URL ?>/modules/packages/create.php" class="btn btn-vp-gold">
    <svg xmlns="http://www.w3.org/2000/svg" class="icon me-1" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14"/><path d="M5 12l14 0"/></svg>
    Add Package
  </a>
  <?php endif; ?>
</div>

<?php if ($packages): ?>
<div class="row row-cards g-3">
  <?php foreach ($packages as $p):
    $items = $db->fetchAll("SELECT * FROM package_items WHERE package_id=?", [$p['id']]);
  ?>
  <div class="col-md-6 col-lg-4">
    <div class="card vp-card h-100">
      <div class="card-header" style="background:linear-gradient(135deg,var(--vp-navy),var(--vp-navy-3));border-radius:14px 14px 0 0;padding:1.1rem 1.4rem;">
        <div>
          <div class="fw-800 text-white" style="font-size:.95rem;"><?= Helper::sanitize($p['name']) ?></div>
          <div style="font-size:.7rem;color:rgba(255,255,255,.5);"><?= Helper::sanitize($p['branch_name'] ?? 'All Branches') ?></div>
        </div>
        <div class="ms-auto">
          <span style="font-size:1.1rem;font-weight:800;color:var(--vp-gold-lt);"><?= Helper::formatCurrency($p['price']) ?></span>
        </div>
      </div>
      <div class="card-body" style="padding:1.1rem 1.4rem;">
        <?php if ($p['description']): ?>
        <p class="text-secondary mb-2" style="font-size:.78rem;"><?= Helper::sanitize($p['description']) ?></p>
        <?php endif; ?>
        <?php if ($items): ?>
        <ul class="list-unstyled mb-0">
          <?php foreach ($items as $it): ?>
          <li class="mb-1 d-flex align-items-start gap-1" style="font-size:.8rem;">
            <span style="color:var(--vp-green);font-weight:700;margin-top:1px;">✓</span>
            <span><?= Helper::sanitize($it['item_name']) ?><?= $it['quantity'] > 1 ? ' <span style="color:#9ca3af;">×'.$it['quantity'].'</span>' : '' ?></span>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>
      <?php if (Auth::hasRole(['super_admin','hall_manager'])): ?>
      <div class="card-footer d-flex gap-2">
        <a href="<?= BASE_URL ?>/modules/packages/edit.php?id=<?= $p['id'] ?>" class="btn btn-vp-primary btn-sm">Edit</a>
        <a href="<?= BASE_URL ?>/modules/packages/delete.php?id=<?= $p['id'] ?>" class="btn btn-vp-danger btn-sm" onclick="return confirm('Delete this package?')">Delete</a>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php else: ?>
<div class="card vp-card">
  <div class="card-body">
    <div class="empty-state">
      <div class="empty-icon">📦</div>
      <div class="empty-text">No packages created yet.</div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
