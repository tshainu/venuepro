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

$pageTitle = 'Invoice: '.$inv['invoice_number'];
$breadcrumbs = [['label'=>'Invoices','url'=>BASE_URL.'/modules/invoices/index.php'],['label'=>$inv['invoice_number']]];
require_once ROOT_PATH . '/includes/header.php';
?>
<div class="vp-page-header d-print-none">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
      <h1 class="vp-page-title"><?= Helper::sanitize($inv['invoice_number']) ?></h1>
      <div class="text-secondary"><?= Helper::sanitize($inv['customer_name']) ?> · <?= Helper::formatDate($inv['invoice_date']) ?></div>
    </div>
    <div class="d-flex gap-2">
      <a href="<?= BASE_URL ?>/modules/invoices/pdf.php?id=<?= $id ?>" class="btn btn-vp-primary" target="_blank">Download PDF</a>
      <a href="<?= BASE_URL ?>/modules/payments/create.php?invoice_id=<?= $id ?>&booking_id=<?= $inv['booking_id'] ?>" class="btn btn-vp-gold">+ Record Payment</a>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-lg-8">
    <div class="card vp-card mb-3">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h3 class="card-title"><?= Helper::sanitize($inv['invoice_number']) ?></h3>
        <div class="d-flex gap-2">
          <?= Helper::statusBadge($inv['invoice_type']) ?>
          <?= Helper::statusBadge($inv['status']) ?>
        </div>
      </div>
      <div class="card-body">
        <div class="row mb-4">
          <div class="col-md-6">
            <div class="text-secondary small fw-bold mb-1">FROM</div>
            <div class="fw-bold"><?= Helper::sanitize($inv['branch_name']) ?></div>
            <div class="text-secondary small"><?= nl2br(Helper::sanitize($inv['branch_address']??'')) ?></div>
          </div>
          <div class="col-md-6">
            <div class="text-secondary small fw-bold mb-1">BILL TO</div>
            <div class="fw-bold"><?= Helper::sanitize($inv['customer_name']) ?></div>
            <div class="text-secondary"><?= Helper::sanitize($inv['customer_phone']) ?></div>
            <?php if ($inv['customer_email']): ?><div class="text-secondary small"><?= Helper::sanitize($inv['customer_email']) ?></div><?php endif; ?>
          </div>
        </div>
        <div class="row mb-3">
          <div class="col-md-3"><div class="text-secondary small">Invoice Date</div><div><?= Helper::formatDate($inv['invoice_date']) ?></div></div>
          <div class="col-md-3"><div class="text-secondary small">Due Date</div><div class="<?= $inv['balance']>0&&$inv['due_date']<date('Y-m-d')?'text-danger fw-bold':'' ?>"><?= Helper::formatDate($inv['due_date']) ?></div></div>
          <?php if ($inv['booking_ref']): ?><div class="col-md-3"><div class="text-secondary small">Booking</div><div><a href="<?= BASE_URL ?>/modules/bookings/view.php?id=<?= $inv['booking_id'] ?>"><?= Helper::sanitize($inv['booking_ref']) ?></a></div></div><?php endif; ?>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead class="table-light"><tr><th>Description</th><th class="text-end">Qty</th><th class="text-end">Unit Price</th><th class="text-end">Tax %</th><th class="text-end">Total</th></tr></thead>
          <tbody>
            <?php foreach ($items as $it): ?>
            <tr>
              <td><?= Helper::sanitize($it['description']) ?></td>
              <td class="text-end"><?= $it['quantity'] ?></td>
              <td class="text-end"><?= Helper::formatCurrency($it['unit_price']) ?></td>
              <td class="text-end"><?= number_format($it['tax_percent'],1) ?>%</td>
              <td class="text-end fw-bold"><?= Helper::formatCurrency($it['total']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr><td colspan="4" class="text-end">Subtotal</td><td class="text-end fw-bold"><?= Helper::formatCurrency($inv['subtotal']) ?></td></tr>
            <tr><td colspan="4" class="text-end">Tax</td><td class="text-end"><?= Helper::formatCurrency($inv['tax_amount']) ?></td></tr>
            <?php if ($inv['discount_amount']>0): ?><tr><td colspan="4" class="text-end text-danger">Discount</td><td class="text-end text-danger">- <?= Helper::formatCurrency($inv['discount_amount']) ?></td></tr><?php endif; ?>
            <tr class="table-active"><td colspan="4" class="text-end fw-bold fs-5">TOTAL</td><td class="text-end fw-bold fs-5"><?= Helper::formatCurrency($inv['total']) ?></td></tr>
            <tr><td colspan="4" class="text-end text-success">Paid</td><td class="text-end text-success fw-bold"><?= Helper::formatCurrency($inv['paid_amount']) ?></td></tr>
            <tr><td colspan="4" class="text-end <?= $inv['balance']>0?'text-danger':'' ?> fw-bold">Balance Due</td><td class="text-end <?= $inv['balance']>0?'text-danger':'' ?> fw-bold"><?= Helper::formatCurrency($inv['balance']) ?></td></tr>
          </tfoot>
        </table>
      </div>
      <?php if ($inv['notes'] || $inv['terms']): ?>
      <div class="card-body border-top">
        <?php if ($inv['notes']): ?><div class="mb-2"><div class="text-secondary small fw-bold">NOTES</div><?= nl2br(Helper::sanitize($inv['notes'])) ?></div><?php endif; ?>
        <?php if ($inv['terms']): ?><div><div class="text-secondary small fw-bold">PAYMENT TERMS</div><?= nl2br(Helper::sanitize($inv['terms'])) ?></div><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Payments on this invoice -->
    <div class="card vp-card">
      <div class="card-header"><h3 class="card-title">Payments Against This Invoice</h3></div>
      <div class="table-responsive">
        <table class="table table-sm table-vcenter mb-0">
          <thead><tr><th>Ref</th><th>Date</th><th>Method</th><th>Amount</th><th>By</th></tr></thead>
          <tbody>
            <?php if ($payments): foreach ($payments as $p): ?>
            <tr>
              <td><?= Helper::sanitize($p['payment_ref']) ?></td>
              <td><?= Helper::formatDate($p['payment_date']) ?></td>
              <td><?= ucfirst(str_replace('_',' ',$p['payment_method'])) ?></td>
              <td class="text-success fw-bold"><?= Helper::formatCurrency($p['amount']) ?></td>
              <td><?= Helper::sanitize($p['received_by_name']??'—') ?></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="5" class="text-center text-secondary py-3">No payments recorded.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card vp-card mb-3">
      <div class="card-header"><h3 class="card-title">Financials</h3></div>
      <div class="card-body">
        <div class="d-flex justify-content-between mb-1"><span>Total</span><strong><?= Helper::formatCurrency($inv['total']) ?></strong></div>
        <div class="d-flex justify-content-between mb-1 text-success"><span>Paid</span><strong><?= Helper::formatCurrency($inv['paid_amount']) ?></strong></div>
        <hr>
        <div class="d-flex justify-content-between fw-bold <?= $inv['balance']>0?'text-danger':'' ?>">
          <span>Balance</span><strong><?= Helper::formatCurrency($inv['balance']) ?></strong>
        </div>
        <?php $pct = $inv['total']>0?min(100,($inv['paid_amount']/$inv['total'])*100):0; ?>
        <div class="progress mt-2" style="height:6px"><div class="progress-bar bg-<?= $pct>=100?'success':'warning' ?>" style="width:<?= $pct ?>%"></div></div>
        <div class="text-secondary small mt-1"><?= number_format($pct,1) ?>% paid</div>
      </div>
    </div>

    <div class="card vp-card">
      <div class="card-header"><h3 class="card-title">Change Status</h3></div>
      <div class="card-body">
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
  </div>
</div>
<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
