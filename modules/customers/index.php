<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
$db = Database::getInstance();
$cu = Auth::currentUser();
$bid = $cu['branch_id'];

$search = trim($_GET['search'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));

$where  = "WHERE 1 ";
$params = [];
if ($bid)    { $where .= "AND c.branch_id=? "; $params[] = $bid; }
if ($search) { $where .= "AND (c.name LIKE ? OR c.mobile LIKE ? OR c.nic LIKE ? OR c.email LIKE ?) "; $s="%$search%"; $params=array_merge($params,[$s,$s,$s,$s]); }

$total = $db->fetchOne("SELECT COUNT(*) as cnt FROM customers c $where", $params)['cnt'] ?? 0;
$pag   = Helper::paginate($total, $page);
$customers = $db->fetchAll("SELECT c.* FROM customers c $where ORDER BY c.created_at DESC LIMIT {$pag['per_page']} OFFSET {$pag['offset']}", $params);

$pageTitle  = Lang::t('customers');
$breadcrumbs = [['label' => Lang::t('customers')]];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="vp-page-header">
  <div>
    <h1 class="vp-page-title">👥 <?= Lang::t('customers') ?></h1>
    <div class="vp-page-sub"><?= $total ?> customers registered</div>
  </div>
  <a href="<?= BASE_URL ?>/modules/customers/create.php" class="btn btn-vp-gold">
    <svg xmlns="http://www.w3.org/2000/svg" class="icon me-1" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14"/><path d="M5 12l14 0"/></svg>
    Add Customer
  </a>
</div>

<!-- Filter Bar -->
<div class="vp-filter-bar mb-3">
  <form method="GET" class="d-flex gap-2 flex-wrap align-items-end">
    <input type="text" name="search" class="form-control" placeholder="Search name, mobile, NIC, email..." value="<?= Helper::sanitize($search) ?>" style="max-width:280px;">
    <button type="submit" class="btn btn-vp-primary">Search</button>
    <?php if ($search): ?><a href="?" class="btn btn-vp-outline">Clear</a><?php endif; ?>
  </form>
</div>

<!-- Table -->
<div class="card vp-card">
  <div class="table-responsive">
    <table class="table table-vcenter vp-table mb-0">
      <thead>
        <tr>
          <th>Customer</th>
          <th>Couple</th>
          <th>Mobile</th>
          <th>NIC</th>
          <th>Email</th>
          <th>Bookings</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($customers): foreach ($customers as $c):
          $bkCount = $db->fetchOne("SELECT COUNT(*) as cnt FROM bookings WHERE customer_id=?", [$c['id']])['cnt'] ?? 0;
        ?>
        <tr>
          <td>
            <div class="d-flex align-items-center gap-2">
              <span class="vp-avatar vp-avatar-sm"><?= strtoupper(substr($c['name'],0,1)) ?></span>
              <a href="<?= BASE_URL ?>/modules/customers/view.php?id=<?= $c['id'] ?>" class="fw-700 text-vp-navy"><?= Helper::sanitize($c['name']) ?></a>
            </div>
          </td>
          <td><span style="font-size:.78rem;"><?= Helper::sanitize($c['bride_name'] ?? '') ?><?= ($c['bride_name'] && $c['groom_name']) ? ' & ' : '' ?><?= Helper::sanitize($c['groom_name'] ?? '') ?></span></td>
          <td class="fw-600"><?= Helper::sanitize($c['mobile']) ?></td>
          <td><?= Helper::sanitize($c['nic'] ?? '—') ?></td>
          <td><?= Helper::sanitize($c['email'] ?? '—') ?></td>
          <td>
            <span class="vp-badge badge-confirmed" style="padding:.2rem .55rem;">
              <?= $bkCount ?> bookings
            </span>
          </td>
          <td>
            <a href="<?= BASE_URL ?>/modules/customers/view.php?id=<?= $c['id'] ?>" class="btn btn-vp-outline btn-sm">View</a>
            <a href="<?= BASE_URL ?>/modules/customers/edit.php?id=<?= $c['id'] ?>" class="btn btn-vp-primary btn-sm">Edit</a>
            <a href="<?= BASE_URL ?>/modules/customers/delete.php?id=<?= $c['id'] ?>" class="btn btn-vp-danger btn-sm" onclick="return confirm('Delete this customer?')">Delete</a>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr>
          <td colspan="7">
            <div class="empty-state">
              <div class="empty-icon">👥</div>
              <div class="empty-text"><?= $search ? 'No customers match your search.' : 'No customers yet.' ?></div>
            </div>
          </td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pag['pages'] > 1): ?>
  <div class="card-footer d-flex align-items-center">
    <p class="m-0 text-secondary" style="font-size:.8rem;">Showing <strong><?= count($customers) ?></strong> of <strong><?= $total ?></strong></p>
    <ul class="pagination ms-auto mb-0">
      <?php for ($i=1; $i<=$pag['pages']; $i++): ?>
      <li class="page-item <?= $i==$page?'active':'' ?>">
        <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
      </li>
      <?php endfor; ?>
    </ul>
  </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
