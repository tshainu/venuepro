<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
$db = Database::getInstance();
$cu = Auth::currentUser();
$id = (int)($_GET['id'] ?? 0);

$p = $db->fetchOne(
    "SELECT p.*, c.name as customer_name, c.mobile as customer_phone,
            c.email as customer_email, c.address as customer_address,
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

$app_name    = Helper::getSetting('company_name', $p['branch_id']) ?? APP_NAME;
$inv_color   = Helper::getSetting('invoice_color', $p['branch_id']) ?? '#1a3c6e';
$cur         = Helper::getSetting('currency_symbol', $p['branch_id']) ?? 'Rs.';
$inv_logo    = Helper::getSetting('company_logo', $p['branch_id']);
$show_logo   = Helper::getSetting('invoice_show_logo', $p['branch_id']) ?? '1';
$co_address  = Helper::getSetting('company_address', $p['branch_id']) ?? '';
$co_phone    = Helper::getSetting('company_phone', $p['branch_id']) ?? '';
$co_email    = Helper::getSetting('company_email', $p['branch_id']) ?? '';
$logo_path   = ($show_logo === '1' && $inv_logo) ? BASE_URL.'/uploads/'.$inv_logo : null;

$pageTitle = 'Payment: '.$p['payment_ref'];
$breadcrumbs = [['label'=>'Payments','url'=>BASE_URL.'/modules/payments/index.php'],['label'=>$p['payment_ref']]];
require_once ROOT_PATH . '/includes/header.php';
?>
<style>
/* ===== PRINT STYLES ===== */
@media print {
  .d-print-none,
  .vp-page-header, .vp-topbar, .vp-sidebar, .vp-topbar-breadcrumb,
  .vp-topbar-right, .vp-topbar-branch, .vp-topbar-user,
  .sidebar, .navbar, .topbar, .footer-bar,
  .col-lg-4, #sidebar,
  .rcpt-header-gradient { display:none !important; }

  * { -webkit-print-color-adjust:exact !important; print-color-adjust:exact !important; }
  html, body { background:#fff !important; margin:0 !important; padding:0 !important; font-size:10pt; color:#1e293b; }
  .receipt-print-card { box-shadow:none !important; border:none !important; border-radius:0 !important; margin:0 !important; padding:0 !important; overflow:visible !important; }
  .col-lg-7 { width:100% !important; flex:0 0 100% !important; max-width:100% !important; padding:0 !important; margin:0 !important; }
  .row { display:block !important; margin:0 !important; }
  .container, .container-fluid { padding:0 !important; margin:0 !important; }
  a { color:inherit !important; text-decoration:none !important; }
  .card { border:none !important; box-shadow:none !important; }

  /* Show print-only block */
  .print-only { display:block !important; }
}
.print-only { display:none; }

/* Screen header gradient */
.rcpt-header-gradient {
  background: linear-gradient(135deg, <?= htmlspecialchars($inv_color) ?> 0%, <?= htmlspecialchars($inv_color) ?>cc 60%, <?= htmlspecialchars($inv_color) ?>99 100%);
  border-radius: 14px 14px 0 0;
  padding: 2rem 2rem 1.5rem;
  color: #fff;
}
.rcpt-badge {
  display:inline-block;
  padding:.3rem .8rem;
  border-radius:999px;
  font-size:.72rem;
  font-weight:700;
  letter-spacing:.05em;
  text-transform:uppercase;
  background:rgba(255,255,255,.18);
  color:#fff;
}
.rcpt-divider { border:none; border-top:1px solid rgba(255,255,255,.18); margin:1rem 0; }
.detail-row { display:flex; justify-content:space-between; padding:.35rem 0; font-size:.95rem; border-bottom:1px solid #f1f5f9; }
.detail-row:last-child { border-bottom:none; }
</style>

<!-- ===== SCREEN PAGE HEADER ===== -->
<div class="vp-page-header d-print-none">
  <div class="d-flex align-items-center justify-content-between">
    <div>
      <h1 class="vp-page-title"><?= Helper::sanitize($p['payment_ref']) ?></h1>
      <div class="text-secondary"><?= Helper::sanitize($p['customer_name']) ?> · <?= Helper::formatDate($p['payment_date']) ?></div>
    </div>
    <div class="d-flex gap-2">
      <?php if ($p['booking_id']): ?>
        <a href="<?= BASE_URL ?>/modules/bookings/view.php?id=<?= $p['booking_id'] ?>" class="btn btn-vp-outline">View Booking</a>
      <?php endif; ?>
      <?php if ($p['invoice_id']): ?>
        <a href="<?= BASE_URL ?>/modules/invoices/view.php?id=<?= $p['invoice_id'] ?>" class="btn btn-vp-outline">View Invoice</a>
      <?php endif; ?>
      <button onclick="window.print()" class="btn btn-vp-primary">🖨 Print Receipt</button>
    </div>
  </div>
</div>

<div class="row justify-content-center">
  <div class="col-lg-7">
    <div class="card vp-card receipt-print-card" style="overflow:hidden;">

      <!-- ===== SCREEN GRADIENT HEADER (hidden on print) ===== -->
      <div class="rcpt-header-gradient d-print-none">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="rcpt-badge mb-2">Payment Receipt</div>
            <h4 class="mb-0 text-white"><?= Helper::sanitize($p['payment_ref']) ?></h4>
            <div style="opacity:.8;font-size:.85rem;margin-top:.25rem;"><?= Helper::formatDate($p['payment_date']) ?></div>
          </div>
          <div class="text-end">
            <div style="font-size:1.7rem;font-weight:800;letter-spacing:-.02em;"><?= $cur ?> <?= number_format((float)$p['amount'],2) ?></div>
            <div style="opacity:.8;font-size:.85rem;">Amount Received</div>
          </div>
        </div>
      </div>

      <!-- ===== PRINT-ONLY HEADER ===== -->
      <div class="print-only" style="margin:0 0 16px 0;padding:0;">
        <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:14px;">
          <tr>
            <td style="vertical-align:middle;">
              <?php if ($logo_path): ?>
                <img src="<?= $logo_path ?>" alt="Logo" style="max-height:60px;max-width:180px;margin-bottom:4px;"><br>
              <?php endif; ?>
              <span style="font-size:15pt;font-weight:800;color:<?= htmlspecialchars($inv_color) ?>;"><?= Helper::sanitize($app_name) ?></span><br>
              <?php if ($p['branch_name']): ?><span style="font-size:9pt;color:#555;"><?= Helper::sanitize($p['branch_name']) ?></span><br><?php endif; ?>
              <?php if ($co_address): ?><span style="font-size:8.5pt;color:#666;"><?= nl2br(Helper::sanitize($co_address)) ?></span><br><?php endif; ?>
              <?php if ($co_phone): ?><span style="font-size:8.5pt;color:#666;">Tel: <?= Helper::sanitize($co_phone) ?></span>&nbsp;&nbsp;<?php endif; ?>
              <?php if ($co_email): ?><span style="font-size:8.5pt;color:#666;"><?= Helper::sanitize($co_email) ?></span><?php endif; ?>
            </td>
            <td style="vertical-align:top;text-align:right;width:200px;">
              <div style="font-size:13pt;font-weight:800;color:<?= htmlspecialchars($inv_color) ?>;letter-spacing:.04em;">PAYMENT RECEIPT</div>
              <div style="font-size:10pt;font-weight:700;margin-top:4px;"><?= Helper::sanitize($p['payment_ref']) ?></div>
              <div style="font-size:9pt;color:#555;"><?= Helper::formatDate($p['payment_date']) ?></div>
            </td>
          </tr>
        </table>
        <hr style="border:none;border-top:2px solid <?= htmlspecialchars($inv_color) ?>;margin:0 0 12px 0;">
      </div>

      <!-- ===== CARD BODY ===== -->
      <div class="card-body" style="padding:1.5rem;">

        <!-- Parties -->
        <div class="row mb-3">
          <div class="col-6">
            <div class="text-secondary small text-uppercase fw-semibold mb-1" style="font-size:.7rem;letter-spacing:.06em;">Received From</div>
            <div class="fw-bold"><?= Helper::sanitize($p['customer_name']) ?></div>
            <?php if ($p['customer_phone']): ?><div class="text-secondary small"><?= Helper::sanitize($p['customer_phone']) ?></div><?php endif; ?>
            <?php if ($p['customer_email']): ?><div class="text-secondary small"><?= Helper::sanitize($p['customer_email']) ?></div><?php endif; ?>
            <?php if ($p['customer_address']): ?><div class="text-secondary small"><?= nl2br(Helper::sanitize($p['customer_address'])) ?></div><?php endif; ?>
          </div>
          <div class="col-6 text-end">
            <div class="text-secondary small text-uppercase fw-semibold mb-1" style="font-size:.7rem;letter-spacing:.06em;">Payment Method</div>
            <div class="fw-bold"><?= ucfirst(str_replace('_',' ',$p['payment_method'])) ?></div>
            <?php if ($p['bank_name']): ?><div class="text-secondary small"><?= Helper::sanitize($p['bank_name']) ?></div><?php endif; ?>
            <?php if ($p['reference_number']): ?><div class="text-secondary small">Ref: <?= Helper::sanitize($p['reference_number']) ?></div><?php endif; ?>
          </div>
        </div>

        <!-- References -->
        <?php if ($p['booking_ref'] || $p['invoice_number']): ?>
        <div class="mb-3 p-3 bg-light rounded">
          <?php if ($p['booking_ref']): ?>
          <div class="detail-row">
            <span class="text-secondary">Booking Ref</span>
            <span class="fw-semibold"><?= Helper::sanitize($p['booking_ref']) ?></span>
          </div>
          <?php endif; ?>
          <?php if ($p['invoice_number']): ?>
          <div class="detail-row">
            <span class="text-secondary">Invoice No.</span>
            <span class="fw-semibold"><?= Helper::sanitize($p['invoice_number']) ?></span>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Amount box -->
        <div class="text-center my-4 p-4 rounded" style="background:#f0fdf4;border:2px solid #bbf7d0;">
          <div class="text-secondary small text-uppercase fw-semibold" style="letter-spacing:.08em;font-size:.75rem;">Amount Received</div>
          <div style="font-size:2.2rem;font-weight:800;color:#16a34a;margin-top:.25rem;"><?= $cur ?> <?= number_format((float)$p['amount'],2) ?></div>
        </div>

        <!-- Notes -->
        <?php if ($p['notes']): ?>
        <div class="mb-3">
          <div class="text-secondary small text-uppercase fw-semibold mb-1" style="font-size:.7rem;letter-spacing:.06em;">Notes</div>
          <div><?= nl2br(Helper::sanitize($p['notes'])) ?></div>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="text-center mt-4 pt-3 border-top text-secondary small">
          <p class="mb-0">Received by: <strong><?= Helper::sanitize($p['received_by_name']??'—') ?></strong></p>
          <p class="mb-0">Generated: <?= date('d M Y, h:i A') ?></p>
          <p class="mb-1"><?= Helper::sanitize($app_name) ?> — Thank you for your payment!</p>
        </div>

      </div><!-- /card-body -->
    </div><!-- /card -->
  </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
