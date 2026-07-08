<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
if (!Auth::hasRole(['super_admin','admin','hall_manager'])) { Helper::flash('error','Admin access required.'); Helper::redirect(BASE_URL.'/index.php'); }
$db = Database::getInstance();
$id = (int)($_GET['id'] ?? 0);
$confirm = $_GET['confirm'] ?? '';

$br = $db->fetchOne("SELECT * FROM branches WHERE id=?", [$id]);
if (!$br) { Helper::flash('error','Branch not found.'); Helper::redirect(BASE_URL.'/modules/branches/index.php'); }

$bk_cnt = $db->fetchOne("SELECT COUNT(*) as cnt FROM bookings WHERE branch_id=?", [$id])['cnt'];
$halls_cnt = $db->fetchOne("SELECT COUNT(*) as cnt FROM halls WHERE branch_id=?", [$id])['cnt'];
$users_cnt = $db->fetchOne("SELECT COUNT(*) as cnt FROM users WHERE branch_id=?", [$id])['cnt'];

// If has data and no confirmation, show warning page
if (($bk_cnt > 0 || $halls_cnt > 0 || $users_cnt > 0) && !$confirm) {
    $pageTitle = 'Delete Branch';
    $breadcrumbs = [['label'=>'Branches','url'=>BASE_URL.'/modules/branches/index.php'],['label'=>'Delete']];
    require_once ROOT_PATH . '/includes/header.php';
    ?>
    <div class="vp-page-header">
      <h1 class="vp-page-title">⚠️ Delete Branch</h1>
    </div>
    
    <div class="card vp-card" style="max-width:600px;border-left:4px solid #dc2626;">
      <div class="card-body p-4">
        <h3 class="mb-3">Delete "<?= Helper::sanitize($br['name']) ?>"?</h3>
        <p style="color:#6b7280;">This branch has associated data that will be deleted:</p>
        
        <div class="alert" style="background:#fef2f2;border:1px solid #fee2e2;border-radius:8px;padding:1rem;margin:1rem 0;">
          <?php if ($halls_cnt > 0): ?>
            <div style="margin-bottom:.5rem;">🏛️ <strong><?= $halls_cnt ?> hall(s)</strong></div>
          <?php endif; ?>
          <?php if ($users_cnt > 0): ?>
            <div style="margin-bottom:.5rem;">👤 <strong><?= $users_cnt ?> user(s)</strong></div>
          <?php endif; ?>
          <?php if ($bk_cnt > 0): ?>
            <div>📅 <strong><?= $bk_cnt ?> booking(s)</strong></div>
          <?php endif; ?>
        </div>
        
        <p style="color:#9ca3af;font-size:.9rem;">This action cannot be undone. All associated records will be permanently deleted.</p>
        
        <div class="d-flex gap-2 mt-4">
          <a href="<?= BASE_URL ?>/modules/branches/delete.php?id=<?= $id ?>&confirm=yes" class="btn btn-danger" style="background:#dc2626;border-color:#dc2626;">Delete Branch & Data</a>
          <a href="<?= BASE_URL ?>/modules/branches/index.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </div>
    </div>
    
    <?php require_once ROOT_PATH . '/includes/footer.php';
    exit;
}

// Delete the branch and cascade delete related data
if ($confirm === 'yes') {
    Logger::log('delete','branches',$id,$br['name'],'name:'.$br['name'],null,'Branch deleted with cascade');
    $db->execute("DELETE FROM bookings WHERE branch_id=?", [$id]);
    $db->execute("DELETE FROM halls WHERE branch_id=?", [$id]);
    $db->execute("DELETE FROM rooms WHERE branch_id=?", [$id]);
    $db->execute("DELETE FROM packages WHERE branch_id=?", [$id]);
    $db->execute("DELETE FROM users WHERE branch_id=?", [$id]);
    $db->execute("DELETE FROM branches WHERE id=?", [$id]);
    Helper::flash('success','Branch deleted successfully.');
    Helper::redirect(BASE_URL.'/modules/branches/index.php');
} else {
    // No related data, just delete
    Logger::log('delete','branches',$id,$br['name'],'name:'.$br['name'],null,'Branch deleted');
    $db->execute("DELETE FROM branches WHERE id=?", [$id]);
    Helper::flash('success','Branch deleted successfully.');
    Helper::redirect(BASE_URL.'/modules/branches/index.php');
}
