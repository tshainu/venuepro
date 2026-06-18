<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
$db = Database::getInstance();
$cu = Auth::currentUser();

$search    = trim($_GET['search'] ?? '');
$method    = $_GET['method'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to   = $_GET['date_to'] ?? '';
$page = max(1,(int)($_GET['page'] ?? 1));

$where = ['1=1']; $params = [];
if ($cu['branch_id']) { $where[] = 'p.branch_id=?'; $params[] = $cu['branch_id']; }
if ($search)    { $where[] = '(p.payment_ref LIKE ? OR c.name LIKE ? OR p.reference_number LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($method)    { $where[] = 'p.payment_method=?'; $params[] = $method; }
if ($date_from) { $where[] = 'p.payment_date >= ?'; $params[] = $date_from; }
if ($date_to)   { $where[] = 'p.payment_date <= ?'; $params[] = $date_to; }

$wstr         = implode(' AND ', $where);
$total        = $db->fetchOne("SELECT COUNT(*) as cnt FROM payments p LEFT JOIN customers c ON p.customer_id=c.id WHERE $wstr", $params)['cnt'];
$total_amount = $db->fetchOne("SELECT SUM(p.amount) as total FROM payments p LEFT JOIN customers c ON p.customer_id=c.id WHERE $wstr", $params)['total'] ?? 0;
$pg           = Helper::paginate($total, $page);

$payments = $db->fetchAll(
    "SELECT p.*, c.name as customer_name, b.booking_ref, i.invoice_number, u.name as received_by_name
     FROM payments p
     LEFT JOIN customers c ON p.customer_id=c.id
     LEFT JOIN bookings b ON p.booking_id=b.id
     LEFT JOIN invoices i ON p.invoice_id=i.id
     LEFT JOIN users u ON p.received_by=u.id
     WHERE $wstr ORDER BY p.payment_date DESC, p.created_at DESC LIMIT ? OFFSET ?",
    array_merge($params, [$pg['per_page'], $pg['offset']])
);

$pageTitle  = 'Payments';
$breadcrumbs = [['label' => 'Payments']];
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="vp-page-header">
  <div>
    <h1 class="vp-page-title">💳 <?= Lang::t('payments') ?></h1>
    <div class="vp-page-sub"><?= $total ?> transactions</div>
  </div>
  <a href="<?= BASE_URL ?>/modules/payments/create.php" class="btn btn-vp-gold">
    <svg xmlns="http://www.w3.org/2000/svg" class="icon me-1" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14"/><path d="M5 12l14 0"/></svg>
    Record Payment
  </a>
</div>

<!-- KPI Summary -->
<div class="row g-3 mb-3">
  <div class="col-md-4">
    <div class="card vp-card kpi-card kpi-green">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="kpi-icon">💰</div>
        <div>
          <div class="kpi-val"><?= Helper::formatCurrency($total_amount) ?></div>
          <div class="kpi-lbl">Total Received</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card vp-card kpi-card kpi-navy">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="kpi-icon">📊</div>
        <div>
          <div class="kpi-val"><?= $total ?></div>
          <div class="kpi-lbl">Total Transactions</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Filter Bar -->
<div class="vp-filter-bar mb-3">
  <form method="get" class="d-flex gap-2 flex-wrap align-items-end">
    <input type="text" name="search" class="form-control" placeholder="Ref / Customer / Bank ref..." value="<?= Helper::sanitize($search) ?>" style="max-width:240px;">
    <select name="method" class="form-select" style="max-width:160px;">
      <option value="">All Methods</option>
      <?php foreach (['cash','bank_transfer','card','cheque','online'] as $m): ?>
      <option value="<?= $m ?>" <?= $method==$m?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$m)) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="date" name="date_from" class="form-control" value="<?= $date_from ?>" style="max-width:148px;">
    <input type="date" name="date_to"   class="form-control" value="<?= $date_to ?>"   style="max-width:148px;">
    <button class="btn btn-vp-primary">Filter</button>
    <a href="<?= BASE_URL ?>/modules/payments/index.php" class="btn btn-vp-outline">Clear</a>
  </form>
</div>

<div class="card vp-card">
  <div class="table-responsive">
    <table class="table table-vcenter vp-table mb-0">
      <thead>
        <tr>
          <th>Ref</th>
          <th>Date</th>
          <th>Customer</th>
          <th>Booking</th>
          <th>Invoice</th>
          <th>Method</th>
          <th>Bank Ref</th>
          <th>Amount</th>
          <th>Received By</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($payments): foreach ($payments as $p): ?>
        <tr>
          <td><a href="<?= BASE_URL ?>/modules/payments/view.php?id=<?= $p['id'] ?>" class="vp-ref"><?= Helper::sanitize($p['payment_ref']) ?></a></td>
          <td><?= Helper::formatDate($p['payment_date']) ?></td>
          <td class="fw-600"><?= Helper::sanitize($p['customer_name']) ?></td>
          <td><?= $p['booking_ref'] ? '<a href="'.BASE_URL.'/modules/bookings/view.php?id='.$p['booking_id'].'" class="vp-ref">'.Helper::sanitize($p['booking_ref']).'</a>' : '—' ?></td>
          <td><?= $p['invoice_number'] ? '<a href="'.BASE_URL.'/modules/invoices/view.php?id='.$p['invoice_id'].'" class="vp-ref">'.Helper::sanitize($p['invoice_number']).'</a>' : '—' ?></td>
          <td><span class="vp-badge badge-confirmed" style="background:#dbeafe;color:#1e40af;"><?= ucfirst(str_replace('_',' ',$p['payment_method'])) ?></span></td>
          <td><?= $p['reference_number'] ? Helper::sanitize($p['reference_number']) : '—' ?></td>
          <td class="fw-700 text-success"><?= Helper::formatCurrency($p['amount']) ?></td>
          <td><?= Helper::sanitize($p['received_by_name']??'—') ?></td>
          <td><a href="<?= BASE_URL ?>/modules/payments/view.php?id=<?= $p['id'] ?>" class="btn btn-vp-outline btn-sm">View</a></td>
        </tr>
        <?php endforeach; else: ?>
        <tr>
          <td colspan="10">
            <div class="empty-state"><div class="empty-icon">💳</div><div class="empty-text">No payments found.</div></div>
          </td>
        </tr>
        <?php endif; ?>
      </tbody>
      <?php if ($payments): ?>
      <tfoot>
        <tr style="background:#f8f9fc;">
          <td colspan="7" class="text-end fw-700 text-vp-navy" style="font-size:.78rem;">PAGE TOTAL</td>
          <td class="fw-800 text-success"><?= Helper::formatCurrency(array_sum(array_column($payments,'amount'))) ?></td>
          <td colspan="2"></td>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>
  <?php if ($pg['pages'] > 1): ?>
  <div class="card-footer d-flex align-items-center">
    <p class="m-0 text-secondary" style="font-size:.8rem;">Showing <?= count($payments) ?> of <?= $total ?></p>
    <ul class="pagination ms-auto mb-0">
      <?php for ($i=1;$i<=$pg['pages'];$i++): ?>
      <li class="page-item <?= $i==$page?'active':'' ?>"><a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&method=<?= $method ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>"><?= $i ?></a></li>
      <?php endfor; ?>
    </ul>
  </div>
  <?php endif; ?>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
