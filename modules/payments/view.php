<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
$db = Database::getInstance();
$cu = Auth::currentUser();
$id = (int)($_GET['id'] ?? 0);

$p = $db->fetchOne(
    "SELECT p.*, c.name as customer_name, c.mobile as customer_phone,
            b.booking_ref, i.invoice_number, br.name as branch_name,
            u.name as received_by_name
     FROM payments p
     LEFT JOIN customers c ON p.customer_id=c.id
     LEFT JOIN bookings b ON p.booking_id=b.id
     LEFT JOIN invoices i ON p.invoice_id=i.id
     LEFT JOIN branches br ON p.branch_id=br.id
     LEFT JOIN users u ON p.received_by=u.id
     WHERE p.id=?", [$id]
);
if (!$p) { Helper::flash('error','Payment not found.'); Helper::redirect(BASE_URL.'/modules/payments/index.php'); }
if ($cu['branch_id'] && $p['branch_id'] != $cu['branch_id']) { Helper::flash('error','Access denied.'); Helper::redirect(BASE_URL.'/modules/payments/index.php'); }

$app_name = Helper::getSetting('company_name') ?? APP_NAME;

$pageTitle = 'Payment: '.$p['payment_ref'];
$breadcrumbs = [['label'=>'Payments','url'=>BASE_URL.'/modules/payments/index.php'],['label'=>$p['payment_ref']]];
require_once ROOT_PATH . '/includes/header.php';
?>
<div class="vp-page-header d-print-none">
  <div class="d-flex align-items-center justify-content-between">
    <div>
      <h1 class="vp-page-title"><?= Helper::sanitize($p['payment_ref']) ?></h1>
      <div class="text-secondary"><?= Helper::sanitize($p['customer_name']) ?> · <?= Helper::formatDate($p['payment_date']) ?></div>
    </div>
    <div class="d-flex gap-2">
      <?php if ($p['booking_id']): ?><a href="<?= BASE_URL ?>/modules/bookings/view.php?id=<?= $p['booking_id'] ?>" class="btn btn-vp-outline">View Booking</a><?php endif; ?>
      <?php if ($p['invoice_id']): ?><a href="<?= BASE_URL ?>/modules/invoices/view.php?id=<?= $p['invoice_id'] ?>" class="btn btn-vp-outline">View Invoice</a><?php endif; ?>
      <button onclick="window.print()" class="btn btn-vp-primary">Print Receipt</button>
    </div>
  </div>
</div>

<div class="row justify-content-center">
  <div class="col-lg-7">
    <!-- Receipt card -->
    <div class="card vp-card" id="receipt-card">
      <div class="card-body">
        <!-- Receipt Header -->
        <div class="text-center mb-4">
          <h2 class="mb-0"><?= Helper::sanitize($app_name) ?></h1>
          <div class="text-secondary"><?= Helper::sanitize($p['branch_name']) ?></div>
          <div class="mt-2 border-top border-bottom py-2">
            <span class="fs-4 fw-bold text-success">PAYMENT RECEIPT</span>
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-6">
            <div class="text-secondary small">Receipt No.</div>
            <div class="fw-bold"><?= Helper::sanitize($p['payment_ref']) ?></div>
          </div>
          <div class="col-6 text-end">
            <div class="text-secondary small">Date</div>
            <div class="fw-bold"><?= Helper::formatDate($p['payment_date']) ?></div>
          </div>
        </div>

        <div class="mb-3 p-3 bg-light rounded">
          <div class="row">
            <div class="col-6">
              <div class="text-secondary small">Received From</div>
              <div class="fw-bold"><?= Helper::sanitize($p['customer_name']) ?></div>
              <div class="text-secondary small"><?= Helper::sanitize($p['customer_phone']) ?></div>
            </div>
            <div class="col-6 text-end">
              <div class="text-secondary small">Payment Method</div>
              <div class="fw-bold"><?= ucfirst(str_replace('_',' ',$p['payment_method'])) ?></div>
              <?php if ($p['bank_name']): ?><div class="text-secondary small"><?= Helper::sanitize($p['bank_name']) ?></div><?php endif; ?>
              <?php if ($p['reference_number']): ?><div class="text-secondary small">Ref: <?= Helper::sanitize($p['reference_number']) ?></div><?php endif; ?>
            </div>
          </div>
        </div>

        <?php if ($p['booking_ref'] || $p['invoice_number']): ?>
        <div class="mb-3">
          <?php if ($p['booking_ref']): ?><div class="d-flex justify-content-between"><span class="text-secondary">Booking</span><span><?= Helper::sanitize($p['booking_ref']) ?></span></div><?php endif; ?>
          <?php if ($p['invoice_number']): ?><div class="d-flex justify-content-between"><span class="text-secondary">Invoice</span><span><?= Helper::sanitize($p['invoice_number']) ?></span></div><?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="text-center my-4 p-3 border rounded">
          <div class="text-secondary small">AMOUNT RECEIVED</div>
          <div class="display-5 fw-bold text-success"><?= Helper::formatCurrency($p['amount']) ?></div>
        </div>

        <?php if ($p['notes']): ?><div class="mb-3"><div class="text-secondary small">Notes</div><div><?= nl2br(Helper::sanitize($p['notes'])) ?></div></div><?php endif; ?>

        <div class="text-center mt-4 pt-3 border-top text-secondary small">
          <p class="mb-0">Received by: <?= Helper::sanitize($p['received_by_name']??'—') ?></p>
          <p class="mb-0">Generated: <?= date('d M Y, h:i A') ?></p>
          <p class="mb-0"><?= Helper::sanitize($app_name) ?> — Thank you!</p>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
@media print {
  .navbar, .page-header, .d-print-none { display: none !important; }
  .page-wrapper { margin: 0 !important; }
  .page-body { padding: 0 !important; }
  #receipt-card { border: none !important; box-shadow: none !important; }
}
</style>
<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
