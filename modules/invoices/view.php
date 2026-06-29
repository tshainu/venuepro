<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
$db = Database::getInstance();
$cu = Auth::currentUser();
$id = (int)($_GET['id'] ?? 0);

$inv = $db->fetchOne(
    "SELECT i.*, c.name as customer_name, c.mobile as customer_phone, c.email as customer_email, c.address as customer_address,
            br.name as branch_name, br.address as branch_address, br.phone as branch_phone,
            b.booking_ref
     FROM invoices i
     LEFT JOIN customers c ON i.customer_id=c.id
     LEFT JOIN branches br ON i.branch_id=br.id
     LEFT JOIN bookings b ON i.booking_id=b.id
     WHERE i.id=?", [$id]
);
if (!$inv) { Helper::flash('error','Invoice not found.'); Helper::redirect(BASE_URL.'/modules/invoices/index.php'); }
if ($cu['branch_id'] && $inv['branch_id'] != $cu['branch_id']) { Helper::flash('error','Access denied.'); Helper::redirect(BASE_URL.'/modules/invoices/index.php'); }

// Status change
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['change_status'])) {
    $ns = $_POST['new_status']??'';
    if (in_array($ns,['draft','sent','paid','partial','overdue','cancelled'])) {
        if ($ns==='paid') { $db->execute("UPDATE invoices SET status=?,paid_amount=total,balance=0 WHERE id=?",[$ns,$id]); }
        else { $db->execute("UPDATE invoices SET status=? WHERE id=?",[$ns,$id]); }
        Helper::flash('success','Invoice status updated.');
        Helper::redirect(BASE_URL.'/modules/invoices/view.php?id='.$id);
    }
}

$items = $db->fetchAll("SELECT * FROM invoice_items WHERE invoice_id=?", [$id]);
$payments = $db->fetchAll(
    "SELECT p.*, u.name as received_by_name FROM payments p LEFT JOIN users u ON p.received_by=u.id WHERE p.invoice_id=? ORDER BY p.payment_date DESC", [$id]
);

// Get invoice paper size setting
$inv_paper_size = Helper::getSetting('invoice_paper_size', $inv['branch_id']) ?? 'A4';

$statusColors = ['draft'=>'secondary','sent'=>'info','paid'=>'success','partial'=>'warning','overdue'=>'danger','cancelled'=>'dark'];
$typeColors   = ['advance'=>'purple','interim'=>'blue','final'=>'green'];

$pageTitle = 'Invoice: '.$inv['invoice_number'];
$breadcrumbs = [['label'=>'Invoices','url'=>BASE_URL.'/modules/invoices/index.php'],['label'=>$inv['invoice_number']]];
require_once ROOT_PATH . '/includes/header.php';
?>
<style>
@media print {
  .d-print-none { display:none !important; }
  .vp-page-header,.sidebar,.navbar,.topbar,.footer-bar { display:none !important; }
  .invoice-print-card { box-shadow:none !important; border:none !important; }
  body { background:#fff !important; }
  .col-lg-4 { display:none !important; }
  .col-lg-8 { width:100% !important; flex:0 0 100% !important; max-width:100% !important; }
  .print-only { display:block !important; }
  a { color:inherit !important; text-decoration:none !important; }
}
.print-only { display:none; }
.inv-header-gradient {
  background: linear-gradient(135deg, #1a3c5e 0%, #2d6a9f 60%, #1e5a8e 100%);
  border-radius: 14px 14px 0 0;
  padding: 2rem 2rem 1.5rem;
  color: #fff;
}
.inv-badge {
  display:inline-block;
  padding:.3rem .8rem;
  border-radius:999px;
  font-size:.72rem;
  font-weight:700;
  letter-spacing:.05em;
  text-transform:uppercase;
}
.inv-divider { border:none; border-top:1px solid rgba(255,255,255,.18); margin:1rem 0; }
.items-table th { background:#f1f5f9; font-size:.72rem; text-transform:uppercase; letter-spacing:.06em; color:#64748b; font-weight:700; padding:.6rem 1rem; }
.items-table td { padding:.75rem 1rem; vertical-align:middle; }
.items-table tbody tr:hover { background:#f8fafc; }
.totals-section { background:#f8fafc; border-radius:12px; padding:1.2rem 1.5rem; }
.totals-row { display:flex; justify-content:space-between; padding:.35rem 0; font-size:.95rem; }
.totals-row.grand { font-size:1.15rem; font-weight:800; border-top:2px solid #e2e8f0; padding-top:.75rem; margin-top:.25rem; }
.payment-pill { display:inline-flex; align-items:center; gap:.4rem; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:.4rem .8rem; font-size:.82rem; }
.sidebar-card { border-radius:14px; border:1px solid #e9ecef; overflow:hidden; }
.sidebar-card .card-header { background:linear-gradient(135deg,#f8fafc,#f1f5f9); border-bottom:1px solid #e9ecef; font-weight:700; font-size:.85rem; text-transform:uppercase; letter-spacing:.05em; color:#64748b; padding:.9rem 1.2rem; }
</style>

<div class="vp-page-header d-print-none">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
      <h1 class="vp-page-title"><?= Helper::sanitize($inv['invoice_number']) ?></h1>
      <div class="text-secondary"><?= Helper::sanitize($inv['customer_name']) ?> · <?= Helper::formatDate($inv['invoice_date']) ?></div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <?php if ($inv_paper_size === '80mm'): ?>
      <a href="<?= BASE_URL ?>/modules/invoices/pdf.php?id=<?= $id ?>" class="btn btn-vp-outline d-print-none" target="_blank">🖨 Print Receipt</a>
      <?php else: ?>
      <button onclick="window.print()" class="btn btn-vp-outline d-print-none">🖨 Print</button>
      <?php endif; ?>
      <a href="<?= BASE_URL ?>/modules/invoices/pdf.php?id=<?= $id ?>" class="btn btn-vp-primary" target="_blank">⬇ Download PDF</a>
      <?php if ($inv['balance'] > 0): ?>
      <a href="<?= BASE_URL ?>/modules/payments/create.php?invoice_id=<?= $id ?>&booking_id=<?= $inv['booking_id'] ?>" class="btn btn-vp-gold">+ Make Payment</a>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-lg-8">
    <div class="card vp-card invoice-print-card mb-3" style="border-radius:14px;overflow:hidden;">

      <!-- Gradient Header -->
      <div class="inv-header-gradient">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
          <div>
            <div style="font-size:1.6rem;font-weight:800;letter-spacing:-.02em;color:#fff;"><?= Helper::sanitize($inv['invoice_number']) ?></div>
            <div style="opacity:.75;font-size:.85rem;margin-top:.2rem;">
              Issued <?= Helper::formatDate($inv['invoice_date']) ?>
              <?php if ($inv['due_date']): ?> · Due <?= Helper::formatDate($inv['due_date']) ?><?php endif; ?>
            </div>
          </div>
          <div class="d-flex gap-2 flex-wrap align-items-center">
            <?php
            $sColor = $statusColors[$inv['status']] ?? 'secondary';
            $tColor = $typeColors[$inv['invoice_type']] ?? 'primary';
            ?>
            <span class="inv-badge" style="background:rgba(255,255,255,.18);color:#fff;border:1px solid rgba(255,255,255,.3);"><?= ucfirst($inv['invoice_type']) ?></span>
            <span class="inv-badge" style="background:rgba(255,255,255,.25);color:#fff;border:1px solid rgba(255,255,255,.4);"><?= strtoupper($inv['status']) ?></span>
          </div>
        </div>
        <hr class="inv-divider">
        <div class="row">
          <div class="col-md-6">
            <div style="font-size:.68rem;font-weight:700;opacity:.65;text-transform:uppercase;letter-spacing:.08em;margin-bottom:.4rem;">From</div>
            <div style="font-weight:700;font-size:1rem;"><?= Helper::sanitize($inv['branch_name']) ?></div>
            <?php if ($inv['branch_address']): ?>
            <div style="opacity:.75;font-size:.82rem;"><?= nl2br(Helper::sanitize($inv['branch_address'])) ?></div>
            <?php endif; ?>
            <?php if ($inv['branch_phone']): ?><div style="opacity:.65;font-size:.8rem;">📞 <?= Helper::sanitize($inv['branch_phone']) ?></div><?php endif; ?>
          </div>
          <div class="col-md-6">
            <div style="font-size:.68rem;font-weight:700;opacity:.65;text-transform:uppercase;letter-spacing:.08em;margin-bottom:.4rem;">Bill To</div>
            <div style="font-weight:700;font-size:1rem;"><?= Helper::sanitize($inv['customer_name']) ?></div>
            <?php if ($inv['customer_phone']): ?><div style="opacity:.75;font-size:.82rem;">📞 <?= Helper::sanitize($inv['customer_phone']) ?></div><?php endif; ?>
            <?php if ($inv['customer_email']): ?><div style="opacity:.65;font-size:.8rem;">✉ <?= Helper::sanitize($inv['customer_email']) ?></div><?php endif; ?>
            <?php if ($inv['customer_address']): ?><div style="opacity:.65;font-size:.8rem;"><?= nl2br(Helper::sanitize($inv['customer_address'])) ?></div><?php endif; ?>
          </div>
        </div>
        <?php if ($inv['booking_ref']): ?>
        <div style="margin-top:.8rem;font-size:.8rem;opacity:.7;">
          Booking: <a href="<?= BASE_URL ?>/modules/bookings/view.php?id=<?= $inv['booking_id'] ?>" style="color:#fff;text-decoration:underline;"><?= Helper::sanitize($inv['booking_ref']) ?></a>
        </div>
        <?php endif; ?>
      </div>

      <!-- Line Items -->
      <div class="table-responsive">
        <table class="table items-table mb-0">
          <thead>
            <tr>
              <th>Description</th>
              <th class="text-center" style="width:70px;">Qty</th>
              <th class="text-end" style="width:130px;">Unit Price</th>
              <th class="text-center" style="width:80px;">Tax %</th>
              <th class="text-end" style="width:130px;">Total</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($items as $it): ?>
            <tr>
              <td><?= Helper::sanitize($it['description']) ?></td>
              <td class="text-center"><?= $it['quantity'] ?></td>
              <td class="text-end"><?= Helper::formatCurrency($it['unit_price']) ?></td>
              <td class="text-center"><?= number_format($it['tax_percent'],1) ?>%</td>
              <td class="text-end fw-bold"><?= Helper::formatCurrency($it['total']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Totals -->
      <div class="card-body pt-0 pb-3">
        <div class="row justify-content-end">
          <div class="col-md-5 col-lg-5">
            <div class="totals-section">
              <div class="totals-row"><span class="text-secondary">Subtotal</span><span><?= Helper::formatCurrency($inv['subtotal']) ?></span></div>
              <div class="totals-row"><span class="text-secondary">Tax</span><span><?= Helper::formatCurrency($inv['tax_amount']) ?></span></div>
              <?php if ($inv['discount_amount']>0): ?>
              <div class="totals-row text-danger"><span>Discount</span><span>– <?= Helper::formatCurrency($inv['discount_amount']) ?></span></div>
              <?php endif; ?>
              <div class="totals-row grand"><span>Total</span><span><?= Helper::formatCurrency($inv['total']) ?></span></div>
              <div class="totals-row text-success"><span>Paid</span><span><?= Helper::formatCurrency($inv['paid_amount']) ?></span></div>
              <div class="totals-row <?= $inv['balance']>0?'text-danger':'' ?> fw-bold"><span>Balance Due</span><span><?= Helper::formatCurrency($inv['balance']) ?></span></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Notes & Terms -->
      <?php if ($inv['notes'] || $inv['terms']): ?>
      <div class="card-body border-top" style="background:#fafbfc;">
        <?php if ($inv['notes']): ?>
        <div class="mb-3">
          <div class="text-secondary small fw-bold mb-1" style="text-transform:uppercase;letter-spacing:.06em;">Notes</div>
          <div style="font-size:.9rem;"><?= nl2br(Helper::sanitize($inv['notes'])) ?></div>
        </div>
        <?php endif; ?>
        <?php if ($inv['terms']): ?>
        <div>
          <div class="text-secondary small fw-bold mb-1" style="text-transform:uppercase;letter-spacing:.06em;">Payment Terms</div>
          <div style="font-size:.85rem;color:#64748b;"><?= nl2br(Helper::sanitize($inv['terms'])) ?></div>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Payments Table -->
    <div class="card vp-card d-print-none">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0">Payment History</h3>
        <?php if ($inv['balance'] > 0): ?>
        <a href="<?= BASE_URL ?>/modules/payments/create.php?invoice_id=<?= $id ?>&booking_id=<?= $inv['booking_id'] ?>" class="btn btn-sm btn-vp-gold">+ Make Payment</a>
        <?php endif; ?>
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-vcenter mb-0">
          <thead><tr><th>Reference</th><th>Date</th><th>Method</th><th>Amount</th><th>Received By</th></tr></thead>
          <tbody>
            <?php if ($payments): foreach ($payments as $p): ?>
            <tr>
              <td><span class="payment-pill">✓ <?= Helper::sanitize($p['payment_ref']) ?></span></td>
              <td><?= Helper::formatDate($p['payment_date']) ?></td>
              <td><?= ucfirst(str_replace('_',' ',$p['payment_method'])) ?></td>
              <td class="text-success fw-bold"><?= Helper::formatCurrency($p['amount']) ?></td>
              <td><?= Helper::sanitize($p['received_by_name']??'—') ?></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="5" class="text-center text-secondary py-3">No payments recorded yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Sidebar -->
  <div class="col-lg-4 d-print-none">
    <!-- Financials -->
    <div class="sidebar-card mb-3">
      <div class="card-header">Financials</div>
      <div class="card-body p-3">
        <div class="d-flex justify-content-between mb-2"><span class="text-secondary">Total</span><strong><?= Helper::formatCurrency($inv['total']) ?></strong></div>
        <div class="d-flex justify-content-between mb-2 text-success"><span>Paid</span><strong><?= Helper::formatCurrency($inv['paid_amount']) ?></strong></div>
        <hr class="my-2">
        <div class="d-flex justify-content-between fw-bold <?= $inv['balance']>0?'text-danger':'' ?>">
          <span>Balance</span><strong><?= Helper::formatCurrency($inv['balance']) ?></strong>
        </div>
        <?php $pct = $inv['total']>0?min(100,($inv['paid_amount']/$inv['total'])*100):0; ?>
        <div class="progress mt-2" style="height:8px;border-radius:4px;">
          <div class="progress-bar bg-<?= $pct>=100?'success':'warning' ?>" style="width:<?= $pct ?>%"></div>
        </div>
        <div class="text-secondary small mt-1"><?= number_format($pct,1) ?>% paid</div>

        <?php if ($inv['balance'] > 0): ?>
        <a href="<?= BASE_URL ?>/modules/payments/create.php?invoice_id=<?= $id ?>&booking_id=<?= $inv['booking_id'] ?>" class="btn btn-vp-gold w-100 mt-3">+ Make Payment</a>
        <?php else: ?>
        <div class="text-center text-success fw-bold mt-3 p-2 rounded" style="background:#f0fdf4;border:1px solid #bbf7d0;">✓ Fully Paid</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Change Status -->
    <div class="sidebar-card mb-3">
      <div class="card-header">Update Status</div>
      <div class="card-body p-3">
        <form method="post">
          <input type="hidden" name="change_status" value="1">
          <select name="new_status" class="form-select mb-2">
            <?php foreach (['draft','sent','paid','partial','overdue','cancelled'] as $s): ?>
            <option value="<?= $s ?>" <?= $inv['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-secondary w-100">Update</button>
        </form>
      </div>
    </div>

    <!-- Actions -->
    <div class="sidebar-card">
      <div class="card-header">Actions</div>
      <div class="card-body p-3 d-flex flex-column gap-2">
        <a href="<?= BASE_URL ?>/modules/invoices/pdf.php?id=<?= $id ?>" target="_blank" class="btn btn-vp-primary w-100">⬇ Download PDF</a>
        <?php if ($inv_paper_size === '80mm'): ?>
        <a href="<?= BASE_URL ?>/modules/invoices/pdf.php?id=<?= $id ?>" target="_blank" class="btn btn-vp-outline w-100">🖨 Print Receipt</a>
        <?php else: ?>
        <button onclick="window.print()" class="btn btn-vp-outline w-100">🖨 Print Invoice</button>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/modules/invoices/index.php" class="btn btn-outline-secondary w-100">← Back to Invoices</a>
      </div>
    </div>
  </div>
</div>
<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
