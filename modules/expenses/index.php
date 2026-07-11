<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
$db = Database::getInstance();
$cu = Auth::currentUser();

// Branch-wise access control
$accessible_branch_ids = Auth::getAccessibleBranches();
$branch_ids = !empty($accessible_branch_ids) ? array_column($accessible_branch_ids, 'id') : [];
if (empty($branch_ids) && $cu['branch_id']) $branch_ids = [$cu['branch_id']];

// Filters
$search     = trim($_GET['search'] ?? '');
$cat_id     = (int)($_GET['category'] ?? 0);
$branch_flt = (int)($_GET['branch_id'] ?? 0);
$date_from  = $_GET['date_from'] ?? '';
$date_to    = $_GET['date_to']   ?? '';
$page       = max(1, (int)($_GET['page'] ?? 1));

$where = ['1=1']; $params = [];

// Branch restriction
if (!empty($branch_ids)) {
    $in = implode(',', array_fill(0, count($branch_ids), '?'));
    $where[] = "e.branch_id IN ($in)";
    $params = array_merge($params, $branch_ids);
} else {
    $where[] = '1=0';
}
if ($branch_flt && in_array($branch_flt, $branch_ids)) {
    $where[] = 'e.branch_id = ?'; $params[] = $branch_flt;
}
if ($cat_id)    { $where[] = 'e.category_id = ?'; $params[] = $cat_id; }
if ($search)    { $where[] = '(e.expense_ref LIKE ? OR e.title LIKE ? OR e.description LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($date_from) { $where[] = 'e.expense_date >= ?'; $params[] = $date_from; }
if ($date_to)   { $where[] = 'e.expense_date <= ?'; $params[] = $date_to; }

$wstr = implode(' AND ', $where);

$total        = $db->fetchOne("SELECT COUNT(*) as cnt FROM expenses e WHERE $wstr", $params)['cnt'];
$total_amount = $db->fetchOne("SELECT COALESCE(SUM(e.amount),0) as total FROM expenses e WHERE $wstr", $params)['total'];

// This month
$this_month_params = $params;
$this_month_where  = $wstr . " AND MONTH(e.expense_date)=MONTH(CURDATE()) AND YEAR(e.expense_date)=YEAR(CURDATE())";
$this_month_amount = $db->fetchOne("SELECT COALESCE(SUM(e.amount),0) as total FROM expenses e WHERE $this_month_where", $this_month_params)['total'];

// Category breakdown
$cat_breakdown = $db->fetchAll(
    "SELECT ec.name, COUNT(*) as cnt, SUM(e.amount) as total
     FROM expenses e
     JOIN expense_categories ec ON e.category_id = ec.id
     WHERE $wstr
     GROUP BY ec.id, ec.name ORDER BY total DESC",
    $params
);

// Branch breakdown (for KPI)
$branch_breakdown = $db->fetchAll(
    "SELECT br.name as branch_name, COUNT(*) as cnt, SUM(e.amount) as total
     FROM expenses e
     JOIN branches br ON e.branch_id = br.id
     WHERE $wstr
     GROUP BY br.id, br.name ORDER BY total DESC",
    $params
);

// Categories for filter
$categories = $db->fetchAll("SELECT id, name FROM expense_categories ORDER BY name");

// Branches for filter (only accessible)
$branches_for_filter = $accessible_branch_ids;

$pg = Helper::paginate($total, $page);
$expenses = $db->fetchAll(
    "SELECT e.*, ec.name as category_name, br.name as branch_name, u.name as created_by_name
     FROM expenses e
     JOIN expense_categories ec ON e.category_id = ec.id
     JOIN branches br ON e.branch_id = br.id
     LEFT JOIN users u ON e.created_by = u.id
     WHERE $wstr
     ORDER BY e.expense_date DESC, e.created_at DESC
     LIMIT ? OFFSET ?",
    array_merge($params, [$pg['per_page'], $pg['offset']])
);

$pageTitle   = 'Expenses';
$breadcrumbs = [['label' => 'Expenses']];
require_once ROOT_PATH . '/includes/header.php';
?>
<style>
.kpi-card{border-radius:16px;overflow:hidden;}
.kpi-red   .card-body{background:linear-gradient(135deg,#fef2f2,#fecaca);}
.kpi-navy  .card-body{background:linear-gradient(135deg,#0c1a35,#1a3c6e);}
.kpi-gold  .card-body{background:linear-gradient(135deg,#fdf5e0,#fde68a44);}
.kpi-orange .card-body{background:linear-gradient(135deg,#fff7ed,#fed7aa);}
.kpi-icon{font-size:2rem;line-height:1;}
.kpi-val{font-size:1.45rem;font-weight:800;color:#0c1a35;letter-spacing:-.03em;}
.kpi-lbl{font-size:.68rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.08em;margin-top:.2rem;}
.kpi-navy .kpi-val{color:#fff;}
.kpi-navy .kpi-lbl{color:#c5d3e8;}
.vp-cat-chip{display:inline-flex;align-items:center;gap:.35rem;padding:.28rem .85rem;border-radius:99px;font-size:.72rem;font-weight:700;text-decoration:none;border:1.5px solid #e2e8f0;color:#374151;background:#fff;transition:all .15s;}
.vp-cat-chip:hover{border-color:#c9a84c;color:#92640c;}
.vp-cat-chip.active{background:#0c1a35;color:#fff;border-color:#0c1a35;}
.vp-cat-chip .chip-count{background:rgba(255,255,255,.22);border-radius:99px;padding:.05rem .45rem;font-size:.65rem;}
.vp-cat-chip:not(.active) .chip-count{background:#f1f5f9;color:#6b7280;}
.exp-status-approved{background:#d1fae5;color:#065f46;border-radius:99px;padding:.15rem .65rem;font-size:.72rem;font-weight:700;}
.exp-status-pending{background:#fef3c7;color:#92400e;border-radius:99px;padding:.15rem .65rem;font-size:.72rem;font-weight:700;}
.exp-status-rejected{background:#fee2e2;color:#991b1b;border-radius:99px;padding:.15rem .65rem;font-size:.72rem;font-weight:700;}
</style>

<div class="vp-page-header">
  <div>
    <h1 class="vp-page-title">💸 Expenses</h1>
    <div class="vp-page-sub"><?= number_format($total) ?> expense records</div>
  </div>
  <a href="<?= BASE_URL ?>/modules/expenses/create.php" class="btn btn-vp-gold">
    <svg xmlns="http://www.w3.org/2000/svg" class="icon me-1" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5l0 14"/><path d="M5 12l14 0"/></svg>
    Add Expense
  </a>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-3">
  <div class="col-md-3">
    <div class="card vp-card kpi-card kpi-red">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="kpi-icon">💸</div>
        <div>
          <div class="kpi-val"><?= Helper::formatCurrency($total_amount) ?></div>
          <div class="kpi-lbl">Total Expenses</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card vp-card kpi-card kpi-orange">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="kpi-icon">📅</div>
        <div>
          <div class="kpi-val"><?= Helper::formatCurrency($this_month_amount) ?></div>
          <div class="kpi-lbl">This Month</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card vp-card kpi-card kpi-navy">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="kpi-icon">🧾</div>
        <div>
          <div class="kpi-val"><?= number_format($total) ?></div>
          <div class="kpi-lbl">Total Records</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card vp-card kpi-card kpi-gold">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="kpi-icon">📊</div>
        <div>
          <div class="kpi-val"><?= $total > 0 ? Helper::formatCurrency($total_amount / $total) : 'Rs. 0' ?></div>
          <div class="kpi-lbl">Avg per Expense</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Branch breakdown (only if multiple branches) -->
<?php if (count($branch_breakdown) > 1): ?>
<div class="row g-3 mb-3">
  <?php foreach ($branch_breakdown as $bb): ?>
  <div class="col-md-auto">
    <div class="card vp-card" style="border-left:4px solid #c9a84c;">
      <div class="card-body py-2 px-3">
        <div style="font-size:.7rem;font-weight:700;color:#6b7280;text-transform:uppercase;"><?= Helper::sanitize($bb['branch_name']) ?></div>
        <div style="font-size:1.1rem;font-weight:800;color:#0c1a35;"><?= Helper::formatCurrency($bb['total']) ?></div>
        <div style="font-size:.7rem;color:#9ca3af;"><?= $bb['cnt'] ?> records</div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Category Chips -->
<?php if ($cat_breakdown): ?>
<div class="d-flex flex-wrap gap-2 mb-3">
  <a href="?search=<?= urlencode($search) ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>"
     class="vp-cat-chip <?= !$cat_id ? 'active' : '' ?>">
    All Categories <span class="chip-count"><?= number_format($total) ?></span>
  </a>
  <?php foreach ($cat_breakdown as $cb): ?>
  <?php $cid = array_search($cb['name'], array_column($categories,'name')); $cid_val = $categories[$cid]['id'] ?? 0; ?>
  <a href="?category=<?= $cid_val ?>&search=<?= urlencode($search) ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>"
     class="vp-cat-chip <?= $cat_id && $categories[$cid]['id']==$cat_id ? 'active' : '' ?>">
    <?= Helper::sanitize($cb['name']) ?> <span class="chip-count"><?= $cb['cnt'] ?></span>
  </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Filter Bar -->
<div class="vp-filter-bar mb-3">
  <form method="get" class="d-flex gap-2 flex-wrap align-items-end">
    <input type="text" name="search" class="form-control" placeholder="Ref / Title / Description..." value="<?= Helper::sanitize($search) ?>" style="max-width:220px;">
    <select name="category" class="form-select" style="max-width:160px;">
      <option value="">All Categories</option>
      <?php foreach ($categories as $c): ?>
      <option value="<?= $c['id'] ?>" <?= $cat_id==$c['id']?'selected':'' ?>><?= Helper::sanitize($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if (count($branches_for_filter) > 1): ?>
    <select name="branch_id" class="form-select" style="max-width:180px;">
      <option value="">All Branches</option>
      <?php foreach ($branches_for_filter as $br): ?>
      <option value="<?= $br['id'] ?>" <?= $branch_flt==$br['id']?'selected':'' ?>><?= Helper::sanitize($br['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php endif; ?>
    <input type="date" name="date_from" class="form-control" value="<?= $date_from ?>" style="max-width:148px;">
    <input type="date" name="date_to"   class="form-control" value="<?= $date_to ?>"   style="max-width:148px;">
    <button class="btn btn-vp-primary">Filter</button>
    <a href="<?= BASE_URL ?>/modules/expenses/index.php" class="btn btn-vp-outline">Clear</a>
  </form>
</div>

<!-- Expenses Table -->
<div class="card vp-card">
  <div class="table-responsive">
    <table class="table table-vcenter vp-table mb-0">
      <thead>
        <tr>
          <th>Ref</th>
          <th>Date</th>
          <th>Title</th>
          <th>Category</th>
          <th>Branch</th>
          <th>Method</th>
          <th>Status</th>
          <th class="text-end">Amount</th>
          <th>Added By</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($expenses): foreach ($expenses as $exp): ?>
        <tr>
          <td><a href="<?= BASE_URL ?>/modules/expenses/view.php?id=<?= $exp['id'] ?>" class="vp-ref"><?= Helper::sanitize($exp['expense_ref']) ?></a></td>
          <td><?= Helper::formatDate($exp['expense_date']) ?></td>
          <td class="fw-600"><?= Helper::sanitize($exp['title']) ?></td>
          <td><span class="vp-badge" style="background:#ede9fe;color:#5b21b6;"><?= Helper::sanitize($exp['category_name']) ?></span></td>
          <td style="font-size:.8rem;color:#6b7280;"><?= Helper::sanitize($exp['branch_name']) ?></td>
          <td><?= ucfirst(str_replace('_',' ',$exp['payment_method'])) ?></td>
          <td><span class="exp-status-<?= $exp['status'] ?>"><?= ucfirst($exp['status']) ?></span></td>
          <td class="text-end fw-700" style="color:#dc2626;"><?= Helper::formatCurrency($exp['amount']) ?></td>
          <td style="font-size:.78rem;color:#6b7280;"><?= Helper::sanitize($exp['created_by_name'] ?? '—') ?></td>
          <td>
            <div class="d-flex gap-1">
              <a href="<?= BASE_URL ?>/modules/expenses/view.php?id=<?= $exp['id'] ?>" class="btn btn-vp-outline btn-sm">View</a>
              <a href="<?= BASE_URL ?>/modules/expenses/edit.php?id=<?= $exp['id'] ?>" class="btn btn-vp-primary btn-sm">Edit</a>
              <a href="<?= BASE_URL ?>/modules/expenses/delete.php?id=<?= $exp['id'] ?>" class="btn btn-sm btn-danger"
                 onclick="return confirm('Delete this expense?')">Del</a>
            </div>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="10">
          <div class="empty-state"><div class="empty-icon">💸</div><div class="empty-text">No expenses found.</div></div>
        </td></tr>
        <?php endif; ?>
      </tbody>
      <?php if ($expenses): ?>
      <tfoot>
        <tr style="background:#fef2f2;">
          <td colspan="7" class="text-end fw-700" style="font-size:.8rem;color:#6b7280;">TOTAL</td>
          <td class="text-end fw-800" style="color:#dc2626;"><?= Helper::formatCurrency($total_amount) ?></td>
          <td colspan="2"></td>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>

<!-- Pagination -->
<?php if ($pg['total_pages'] > 1): ?>
<nav class="mt-3">
  <ul class="pagination pagination-sm justify-content-center">
    <?php for ($i = 1; $i <= $pg['total_pages']; $i++): ?>
    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
      <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= $cat_id ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>"><?= $i ?></a>
    </li>
    <?php endfor; ?>
  </ul>
</nav>
<?php endif; ?>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
