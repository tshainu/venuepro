<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
$db = Database::getInstance();
$cu = Auth::currentUser();

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$page   = max(1,(int)($_GET['page'] ?? 1));

$where = ['1=1']; $params = [];
if ($cu['branch_id']) { $where[] = 'q.branch_id=?'; $params[] = $cu['branch_id']; }
if ($search) { $where[] = '(q.quotation_ref LIKE ? OR c.name LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($status) { $where[] = 'q.status=?'; $params[] = $status; }

$wstr  = implode(' AND ', $where);
$total = $db->fetchOne("SELECT COUNT(*) as cnt FROM quotations q LEFT JOIN customers c ON q.customer_id=c.id WHERE $wstr", $params)['cnt'];
$pg    = Helper::paginate($total, $page);
$quotes = $db->fetchAll(
    "SELECT q.*, c.name as customer_name, br.name as branch_name
     FROM quotations q
     LEFT JOIN customers c ON q.customer_id=c.id
     LEFT JOIN branches br ON q.branch_id=br.id
     WHERE $wstr ORDER BY q.created_at DESC LIMIT ? OFFSET ?",
    array_merge($params, [$pg['per_page'], $pg['offset']])
);

$pageTitle  = 'Quotations';
$breadcrumbs = [['label' => 'Quotations']];
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="vp-page-header">
  <div>
    <h1 class="vp-page-title">📄 <?= Lang::t('quotations') ?></h1>
    <div class="vp-page-sub"><?= $total ?> quotations</div>
  </div>
  <a href="<?= BASE_URL ?>/modules/quotations/create.php" class="btn btn-vp-gold">
    <svg xmlns="http://www.w3.org/2000/svg" class="icon me-1" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14"/><path d="M5 12l14 0"/></svg>
    New Quotation
  </a>
</div>

<!-- Filter Bar -->
<div class="vp-filter-bar mb-3">
  <form method="get" class="d-flex gap-2 flex-wrap align-items-end">
    <input type="text" name="search" class="form-control" placeholder="Ref / Customer..." value="<?= Helper::sanitize($search) ?>" style="max-width:240px;">
    <select name="status" class="form-select" style="max-width:160px;">
      <option value="">All Status</option>
      <?php foreach (['draft','sent','accepted','rejected','expired'] as $s): ?>
      <option value="<?= $s ?>" <?= $status==$s?'selected':'' ?>><?= ucfirst($s) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-vp-primary">Filter</button>
    <a href="<?= BASE_URL ?>/modules/quotations/index.php" class="btn btn-vp-outline">Clear</a>
  </form>
</div>

<div class="card vp-card">
  <div class="table-responsive">
    <table class="table table-vcenter vp-table mb-0">
      <thead>
        <tr><th>Ref</th><th>Customer</th><th>Branch</th><th>Valid Until</th><th>Total</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php if ($quotes): foreach ($quotes as $q): ?>
        <tr>
          <td><a href="<?= BASE_URL ?>/modules/quotations/view.php?id=<?= $q['id'] ?>" class="vp-ref"><?= Helper::sanitize($q['quotation_ref']) ?></a></td>
          <td class="fw-600"><?= Helper::sanitize($q['customer_name']) ?></td>
          <td><?= Helper::sanitize($q['branch_name']) ?></td>
          <td><?= Helper::formatDate($q['valid_until']) ?></td>
          <td class="fw-700"><?= Helper::formatCurrency($q['total']) ?></td>
          <td><?= Helper::statusBadge($q['status']) ?></td>
          <td>
            <a href="<?= BASE_URL ?>/modules/quotations/view.php?id=<?= $q['id'] ?>" class="btn btn-vp-outline btn-sm">View</a>
            <a href="<?= BASE_URL ?>/modules/quotations/pdf.php?id=<?= $q['id'] ?>" class="btn btn-vp-primary btn-sm" target="_blank">PDF</a>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr>
          <td colspan="7">
            <div class="empty-state"><div class="empty-icon">📄</div><div class="empty-text">No quotations found.</div></div>
          </td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pg['pages'] > 1): ?>
  <div class="card-footer d-flex align-items-center">
    <p class="m-0 text-secondary" style="font-size:.8rem;">Showing <?= count($quotes) ?> of <?= $total ?></p>
    <ul class="pagination ms-auto mb-0">
      <?php for ($i=1;$i<=$pg['pages'];$i++): ?>
      <li class="page-item <?= $i==$page?'active':'' ?>"><a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>"><?= $i ?></a></li>
      <?php endfor; ?>
    </ul>
  </div>
  <?php endif; ?>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
