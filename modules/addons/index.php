<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
$db = Database::getInstance();
$cu = Auth::currentUser();

$search     = trim($_GET['search'] ?? '');
$cat_filter = (int)($_GET['category_id'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));

$where = ['1=1']; $params = [];
if ($cu['branch_id']) { $where[] = '(a.branch_id=? OR a.branch_id IS NULL)'; $params[] = $cu['branch_id']; }
if ($search)      { $where[] = 'a.name LIKE ?'; $params[] = "%$search%"; }
if ($cat_filter)  { $where[] = 'a.category_id=?'; $params[] = $cat_filter; }

$wstr  = implode(' AND ', $where);
$total = $db->fetchOne("SELECT COUNT(*) as cnt FROM addons a WHERE $wstr", $params)['cnt'];
$pg    = Helper::paginate($total, $page);

$addons = $db->fetchAll(
    "SELECT a.*, c.name as category_name, b.name as branch_name
     FROM addons a
     LEFT JOIN addon_categories c ON a.category_id=c.id
     LEFT JOIN branches b ON a.branch_id=b.id
     WHERE $wstr ORDER BY a.name ASC LIMIT ? OFFSET ?",
    array_merge($params, [$pg['per_page'], $pg['offset']])
);
$categories = $db->fetchAll("SELECT * FROM addon_categories ORDER BY name");

$pageTitle  = 'Add-ons';
$breadcrumbs = [['label' => 'Add-ons']];
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="vp-page-header">
  <div>
    <h1 class="vp-page-title">✨ <?= Lang::t('addons') ?></h1>
    <div class="vp-page-sub"><?= $total ?> add-ons available</div>
  </div>
  <?php if (Auth::hasRole(['super_admin','hall_manager'])): ?>
  <a href="<?= BASE_URL ?>/modules/addons/create.php" class="btn btn-vp-gold">
    <svg xmlns="http://www.w3.org/2000/svg" class="icon me-1" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14"/><path d="M5 12l14 0"/></svg>
    New Add-on
  </a>
  <?php endif; ?>
</div>

<!-- Filter Bar -->
<div class="vp-filter-bar mb-3">
  <form method="get" class="d-flex gap-2 flex-wrap align-items-end">
    <input type="text" name="search" class="form-control" placeholder="Search add-ons..." value="<?= Helper::sanitize($search) ?>" style="max-width:240px;">
    <select name="category_id" class="form-select" style="max-width:180px;">
      <option value="">All Categories</option>
      <?php foreach ($categories as $c): ?>
      <option value="<?= $c['id'] ?>" <?= $cat_filter==$c['id']?'selected':'' ?>><?= Helper::sanitize($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-vp-primary">Filter</button>
    <a href="<?= BASE_URL ?>/modules/addons/index.php" class="btn btn-vp-outline">Clear</a>
  </form>
</div>

<div class="card vp-card">
  <div class="table-responsive">
    <table class="table table-vcenter vp-table mb-0">
      <thead>
        <tr>
          <th>Name</th>
          <th>Category</th>
          <th>Branch</th>
          <th>Price</th>
          <th>Unit</th>
          <th>Tax %</th>
          <th>Status</th>
          <?php if (Auth::hasRole(['super_admin','hall_manager'])): ?><th>Actions</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php if ($addons): foreach ($addons as $a): ?>
        <tr>
          <td>
            <div class="fw-700"><?= Helper::sanitize($a['name']) ?></div>
            <?php if ($a['description']): ?><div class="text-secondary" style="font-size:.75rem;"><?= Helper::sanitize(substr($a['description'],0,60)) ?></div><?php endif; ?>
          </td>
          <td><?= Helper::sanitize($a['category_name'] ?? '—') ?></td>
          <td><?= Helper::sanitize($a['branch_name'] ?? 'All Branches') ?></td>
          <td class="fw-600"><?= Helper::formatCurrency($a['price']) ?></td>
          <td><span class="text-secondary"><?= Helper::sanitize($a['unit']) ?></span></td>
          <td><?= number_format($a['tax_percent'],1) ?>%</td>
          <td><?= $a['is_available'] ? '<span class="vp-badge badge-active">Available</span>' : '<span class="vp-badge badge-inactive">Unavailable</span>' ?></td>
          <?php if (Auth::hasRole(['super_admin','hall_manager'])): ?>
          <td>
            <a href="<?= BASE_URL ?>/modules/addons/edit.php?id=<?= $a['id'] ?>" class="btn btn-vp-primary btn-sm">Edit</a>
            <a href="<?= BASE_URL ?>/modules/addons/delete.php?id=<?= $a['id'] ?>" class="btn btn-vp-danger btn-sm" onclick="return confirm('Delete this add-on?')">Delete</a>
          </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; else: ?>
        <tr>
          <td colspan="8">
            <div class="empty-state">
              <div class="empty-icon">✨</div>
              <div class="empty-text">No add-ons found.</div>
            </div>
          </td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pg['pages'] > 1): ?>
  <div class="card-footer d-flex align-items-center">
    <p class="m-0 text-secondary" style="font-size:.8rem;">Showing <?= count($addons) ?> of <?= $total ?></p>
    <ul class="pagination ms-auto mb-0">
      <?php for ($i=1;$i<=$pg['pages'];$i++): ?>
      <li class="page-item <?= $i==$page?'active':'' ?>">
        <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category_id=<?= $cat_filter ?>"><?= $i ?></a>
      </li>
      <?php endfor; ?>
    </ul>
  </div>
  <?php endif; ?>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
