<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
$db = Database::getInstance();
$cu = Auth::currentUser();

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$type   = $_GET['invoice_type'] ?? '';
$page   = max(1,(int)($_GET['page'] ?? 1));

$where = ['1=1']; $params = [];
if ($cu['branch_id']) { $where[] = 'i.branch_id=?'; $params[] = $cu['branch_id']; }
if ($search) { $where[] = '(i.invoice_number LIKE ? OR c.name LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($status) { $where[] = 'i.status=?'; $params[] = $status; }
if ($type)   { $where[] = 'i.invoice_type=?'; $params[] = $type; }

$wstr  = implode(' AND ', $where);
$total = $db->fetchOne("SELECT COUNT(*) as cnt FROM invoices i LEFT JOIN customers c ON i.customer_id=c.id WHERE $wstr", $params)['cnt'];
$pg    = Helper::paginate($total, $page);
$invoices = $db->fetchAll(
    "SELECT i.*, c.name as customer_name, b.booking_ref
     FROM invoices i
     LEFT JOIN customers c ON i.customer_id=c.id
     LEFT JOIN bookings b ON i.booking_id=b.id
     WHERE $wstr ORDER BY i.created_at DESC LIMIT ? OFFSET ?",
    array_merge($params, [$pg['per_page'], $pg['offset']])
);

$pageTitle  = 'Invoices';
$breadcrumbs = [['label' => 'Invoices']];
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="vp-page-header">
  <div>
    <h1 class="vp-page-title">🧾 <?= Lang::t('invoices') ?></h1>
    <div class="vp-page-sub"><?= $total ?> invoices</div>
  </div>
  <a href="<?= BASE_URL ?>/modules/invoices/create.php" class="btn btn-vp-gold">
    <svg xmlns="http://www.w3.org/2000/svg" class="icon me-1" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14"/><path d="M5 12l14 0"/></svg>
    New Invoice
  </a>
</div>

<!-- Filter Bar -->
<div class="vp-filter-bar mb-3">
  <form method="get" class="d-flex gap-2 flex-wrap align-items-end">
    <input type="text" name="search" class="form-control" placeholder="Invoice # / Customer..." value="<?= Helper::sanitize($search) ?>" style="max-width:240px;">
    <select name="status" class="form-select" style="max-width:140px;">
      <option value="">All Status</option>
      <?php foreach (['draft','sent','paid','partial','overdue','cancelled'] as $s): ?>
      <option value="<?= $s ?>" <?= $status==$s?'selected':'' ?>><?= ucfirst($s) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="invoice_type" class="form-select" style="max-width:130px;">
      <option value="">All Types</option>
      <?php foreach (['advance','interim','final'] as $t): ?>
      <option value="<?= $t ?>" <?= $type==$t?'selected':'' ?>><?= ucfirst($t) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-vp-primary">Filter</button>
    <a href="<?= BASE_URL ?>/modules/invoices/index.php" class="btn btn-vp-outline">Clear</a>
  </form>
</div>

<div class="card vp-card">
  <div class="table-responsive">
    <table class="table table-vcenter vp-table mb-0">
      <thead>
        <tr>
          <th>Invoice #</th>
          <th>Customer</th>
          <th>Booking</th>
          <th>Date</th>
          <th>Due</th>
          <th>Type</th>
          <th>Total</th>
          <th>Balance</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($invoices): foreach ($invoices as $inv): ?>
        <tr>
          <td><a href="<?= BASE_URL ?>/modules/invoices/view.php?id=<?= $inv['id'] ?>" class="vp-ref"><?= Helper::sanitize($inv['invoice_number']) ?></a></td>
          <td class="fw-600"><?= Helper::sanitize($inv['customer_name']) ?></td>
          <td>
            <?php if ($inv['booking_ref']): ?>
            <a href="<?= BASE_URL ?>/modules/bookings/view.php?id=<?= $inv['booking_id'] ?>" class="vp-ref"><?= Helper::sanitize($inv['booking_ref']) ?></a>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td><?= Helper::formatDate($inv['invoice_date']) ?></td>
          <td><?= Helper::formatDate($inv['due_date']) ?></td>
          <td><?= Helper::statusBadge($inv['invoice_type']) ?></td>
          <td class="fw-700"><?= Helper::formatCurrency($inv['total']) ?></td>
          <td class="<?= $inv['balance']>0?'text-danger fw-700':'' ?>"><?= Helper::formatCurrency($inv['balance']) ?></td>
          <td><?= Helper::statusBadge($inv['status']) ?></td>
          <td>
            <a href="<?= BASE_URL ?>/modules/invoices/view.php?id=<?= $inv['id'] ?>" class="btn btn-vp-outline btn-sm">View</a>
            <a href="<?= BASE_URL ?>/modules/invoices/pdf.php?id=<?= $inv['id'] ?>" class="btn btn-vp-primary btn-sm" target="_blank">PDF</a>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr>
          <td colspan="10">
            <div class="empty-state"><div class="empty-icon">🧾</div><div class="empty-text">No invoices found.</div></div>
          </td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pg['pages'] > 1): ?>
  <div class="card-footer d-flex align-items-center">
    <p class="m-0 text-secondary" style="font-size:.8rem;">Showing <?= count($invoices) ?> of <?= $total ?></p>
    <ul class="pagination ms-auto mb-0">
      <?php for ($i=1;$i<=$pg['pages'];$i++): ?>
      <li class="page-item <?= $i==$page?'active':'' ?>"><a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>&invoice_type=<?= $type ?>"><?= $i ?></a></li>
      <?php endfor; ?>
    </ul>
  </div>
  <?php endif; ?>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
