<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
if (!Auth::isSuperAdmin()) { Helper::flash('error','Super Admin only.'); Helper::redirect(BASE_URL.'/index.php'); }
$db = Database::getInstance();

$page   = max(1,(int)($_GET['page'] ?? 1));
$search = trim($_GET['search'] ?? '');
$where  = ['1=1']; $params = [];
if ($search) { $where[] = '(u.name LIKE ? OR u.email LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
$wstr  = implode(' AND ', $where);
$total = $db->fetchOne("SELECT COUNT(*) as cnt FROM users u WHERE $wstr", $params)['cnt'];
$pg    = Helper::paginate($total, $page);
$users = $db->fetchAll(
    "SELECT u.*, r.name as role_name, b.name as branch_name FROM users u LEFT JOIN roles r ON u.role_id=r.id LEFT JOIN branches b ON u.branch_id=b.id WHERE $wstr ORDER BY u.name LIMIT ? OFFSET ?",
    array_merge($params, [$pg['per_page'], $pg['offset']])
);

$pageTitle  = 'Users';
$breadcrumbs = [['label' => 'Users']];
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="vp-page-header">
  <div>
    <h1 class="vp-page-title">👤 <?= Lang::t('users') ?></h1>
    <div class="vp-page-sub"><?= $total ?> system users</div>
  </div>
  <a href="<?= BASE_URL ?>/modules/users/create.php" class="btn btn-vp-gold">
    <svg xmlns="http://www.w3.org/2000/svg" class="icon me-1" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14"/><path d="M5 12l14 0"/></svg>
    New User
  </a>
</div>

<!-- Filter Bar -->
<div class="vp-filter-bar mb-3">
  <form method="get" class="d-flex gap-2 flex-wrap align-items-end">
    <input type="text" name="search" class="form-control" placeholder="Name / Email..." value="<?= Helper::sanitize($search) ?>" style="max-width:280px;">
    <button class="btn btn-vp-primary">Search</button>
    <?php if ($search): ?><a href="<?= BASE_URL ?>/modules/users/index.php" class="btn btn-vp-outline">Clear</a><?php endif; ?>
  </form>
</div>

<div class="card vp-card">
  <div class="table-responsive">
    <table class="table table-vcenter vp-table mb-0">
      <thead>
        <tr><th>User</th><th>Email</th><th>Role</th><th>Branch</th><th>Last Login</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php if ($users): foreach ($users as $u): ?>
        <tr>
          <td>
            <div class="d-flex align-items-center gap-2">
              <span class="vp-avatar vp-avatar-sm"><?= strtoupper(substr($u['name'],0,1)) ?></span>
              <span class="fw-600"><?= Helper::sanitize($u['name']) ?></span>
            </div>
          </td>
          <td><?= Helper::sanitize($u['email']) ?></td>
          <td><span class="vp-badge badge-inquiry"><?= Helper::sanitize($u['role_name']??'—') ?></span></td>
          <td><?= Helper::sanitize($u['branch_name']??'All Branches') ?></td>
          <td style="font-size:.78rem;"><?= $u['last_login'] ? Helper::formatDateTime($u['last_login']) : '<span class="text-secondary">Never</span>' ?></td>
          <td><?= $u['is_active'] ? '<span class="vp-badge badge-active">Active</span>' : '<span class="vp-badge badge-inactive">Inactive</span>' ?></td>
          <td>
            <a href="<?= BASE_URL ?>/modules/users/edit.php?id=<?= $u['id'] ?>" class="btn btn-vp-primary btn-sm">Edit</a>
            <?php if ($u['id'] != Auth::currentUser()['id']): ?>
            <a href="<?= BASE_URL ?>/modules/users/delete.php?id=<?= $u['id'] ?>" class="btn btn-vp-danger btn-sm" onclick="return confirm('Delete user <?= Helper::sanitize($u['name']) ?>?')">Delete</a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr>
          <td colspan="7">
            <div class="empty-state"><div class="empty-icon">👤</div><div class="empty-text">No users found.</div></div>
          </td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pg['pages'] > 1): ?>
  <div class="card-footer d-flex align-items-center">
    <p class="m-0 text-secondary" style="font-size:.8rem;">Showing <?= count($users) ?> of <?= $total ?></p>
    <ul class="pagination ms-auto mb-0">
      <?php for ($i=1;$i<=$pg['pages'];$i++): ?>
      <li class="page-item <?= $i==$page?'active':'' ?>"><a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a></li>
      <?php endfor; ?>
    </ul>
  </div>
  <?php endif; ?>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
