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
            br.address as branch_address, br.phone as branch_phone,
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

$app_name   = Helper::getSetting('company_name',    $p['branch_id']) ?? APP_NAME;
$inv_color  = Helper::getSetting('invoice_color',   $p['branch_id']) ?? '#1a3c6e';
$cur        = Helper::getSetting('currency_symbol', $p['branch_id']) ?? 'Rs.';
$co_tagline = Helper::getSetting('company_tagline', $p['branch_id']) ?? '';
$co_address = Helper::getSetting('company_address', $p['branch_id']) ?? $p['branch_address'] ?? '';
$co_phone   = Helper::getSetting('company_phone',   $p['branch_id']) ?? $p['branch_phone'] ?? '';
$co_email   = Helper::getSetting('company_email',   $p['branch_id']) ?? '';
$co_website = Helper::getSetting('company_website', $p['branch_id']) ?? '';
$inv_logo   = Helper::getSetting('company_logo',    $p['branch_id']);
$show_logo  = Helper::getSetting('invoice_show_logo',$p['branch_id']) ?? '1';
$logo_path  = ($show_logo === '1' && $inv_logo) ? BASE_URL.'/uploads/'.$inv_logo : null;

$pageTitle  = 'Payment: '.$p['payment_ref'];
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
  .rcpt-gradient-header { display:none !important; }

  * { -webkit-print-color-adjust:exact !important; print-color-adjust:exact !important; }
  html, body { background:#fff !important; margin:0 !important; padding:0 !important; font-size:10pt; color:#1e293b; }
  .receipt-print-card { box-shadow:none !important; border:none !important; border-radius:0 !important; margin:0 !important; padding:0 !important; overflow:visible !important; }
  .col-lg-8 { width:100% !important; flex:0 0 100% !important; max-width:100% !important; padding:0 !important; margin:0 !important; }
  .row { display:block !important; margin:0 !important; }
  .container, .container-fluid { padding:0 !important; margin:0 !important; }
  a { color:inherit !important; text-decoration:none !important; }
  .card { border:none !important; box-shadow:none !important; }
  .print-only { display:block !important; }
  .rcpt-body { padding:0 !important; }
}
.print-only { display:none; }

/* Screen gradient header */
.rcpt-gradient-header {
  background: linear-gradient(135deg, <?= htmlspecialchars($inv_color) ?> 0%, <?= htmlspecialchars($inv_color) ?>cc 60%, <?= htmlspecialchars($inv_color) ?>99 100%);
  border-radius: 14px 14px 0 0;
  padding: 2rem 2rem 1.5rem;
  color: #fff;
}
.rcpt-badge {
  display:inline-block; padding:.3rem .8rem; border-radius:999px;
  font-size:.72rem; font-weight:700; letter-spacing:.05em; text-transform:uppercase;
  background:rgba(255,255,255,.18); color:#fff;
}
.rcpt-divider { border:none; border-top:1px solid rgba(255,255,255,.18); margin:1rem 0; }
.detail-row { display:flex; justify-content:space-between; align-items:center; padding:.4rem 0; border-bottom:1px solid #f1f5f9; font-size:.9rem; }
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

<div class="row">
  <div class="col-lg-8">
    <div class="card vp-card receipt-print-card" style="border-radius:14px;overflow:hidden;">

      <!-- ===== PRINT-ONLY HEADER ===== -->
      <div class="print-only" style="margin:0 0 16px 0;padding:0;">
        <table style="width:100%;border-collapse:collapse;">
          <tr>
            <td style="width:58%;vertical-align:top;padding:0;">
              <?php if ($logo_path): ?>
                <img src="<?= $logo_path ?>" alt="Logo" style="max-height:60px;max-width:180px;margin-bottom:6px;"><br>
              <?php endif; ?>
              <div style="font-size:16pt;font-weight:bold;color:<?= htmlspecialchars($inv_color) ?>;line-height:1.1;"><?= htmlspecialchars($app_name) ?></div>
              <?php if ($co_tagline): ?><div style="font-size:8pt;color:#64748b;margin-top:2px;"><?= htmlspecialchars($co_tagline) ?></div><?php endif; ?>
              <div style="font-size:8pt;color:#475569;margin-top:5px;line-height:1.5;">
                <?php if ($p['branch_name']): ?><?= htmlspecialchars($p['branch_name']) ?><br><?php endif; ?>
                <?php if ($co_address): ?><?= nl2br(htmlspecialchars($co_address)) ?><br><?php endif; ?>
                <?php if ($co_phone): ?>Tel: <?= htmlspecialchars($co_phone) ?><?php if ($co_email): ?> &nbsp;|&nbsp; <?php endif; ?><?php endif; ?>
                <?php if ($co_email): ?>Email: <?= htmlspecialchars($co_email) ?><?php endif; ?>
                <?php if ($co_website): ?><br><?= htmlspecialchars($co_website) ?><?php endif; ?>
              </div>
            </td>
            <td style="width:42%;vertical-align:top;text-align:right;padding:0;">
              <div style="font-size:24pt;font-weight:bold;color:<?= htmlspecialchars($inv_color) ?>;letter-spacing:1px;line-height:1;">PAYMENT RECEIPT</div>
              <table style="width:100%;border-collapse:collapse;margin-top:8px;">
                <tr><td style="font-size:8pt;color:#94a3b8;text-align:right;padding:1px 0;">Receipt No</td><td style="font-size:8pt;font-weight:bold;text-align:right;padding:1px 0 1px 8px;"><?= htmlspecialchars($p['payment_ref']) ?></td></tr>
                <tr><td style="font-size:8pt;color:#94a3b8;text-align:right;padding:1px 0;">Date</td><td style="font-size:8pt;font-weight:bold;text-align:right;padding:1px 0 1px 8px;"><?= date('d M Y', strtotime($p['payment_date'])) ?></td></tr>
                <?php if ($p['invoice_number']): ?><tr><td style="font-size:8pt;color:#94a3b8;text-align:right;padding:1px 0;">Invoice No</td><td style="font-size:8pt;font-weight:bold;text-align:right;padding:1px 0 1px 8px;"><?= htmlspecialchars($p['invoice_number']) ?></td></tr><?php endif; ?>
                <?php if ($p['booking_ref']): ?><tr><td style="font-size:8pt;color:#94a3b8;text-align:right;padding:1px 0;">Booking Ref</td><td style="font-size:8pt;font-weight:bold;text-align:right;padding:1px 0 1px 8px;"><?= htmlspecialchars($p['booking_ref']) ?></td></tr><?php endif; ?>
              </table>
            </td>
          </tr>
        </table>
        <!-- Color divider bar -->
        <div style="height:3px;background:<?= htmlspecialchars($inv_color) ?>;margin:10px 0 12px;"></div>
        <!-- Bill To -->
        <div style="margin-bottom:12px;">
          <div style="font-size:7pt;font-weight:bold;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;margin-bottom:3px;">Received From</div>
          <div style="font-size:11pt;font-weight:bold;color:#0f172a;"><?= htmlspecialchars($p['customer_name']) ?></div>
          <div style="font-size:8pt;color:#475569;line-height:1.5;margin-top:2px;">
            <?php if ($p['customer_phone']): ?><?= htmlspecialchars($p['customer_phone']) ?><br><?php endif; ?>
            <?php if ($p['customer_email']): ?><?= htmlspecialchars($p['customer_email']) ?><br><?php endif; ?>
            <?php if ($p['customer_address']): ?><?= nl2br(htmlspecialchars($p['customer_address'])) ?><?php endif; ?>
          </div>
        </div>
        <!-- Payment Details Table -->
        <table style="width:100%;border-collapse:collapse;margin-bottom:10px;">
          <thead>
            <tr style="background:<?= htmlspecialchars($inv_color) ?>;-webkit-print-color-adjust:exact;print-color-adjust:exact;">
              <th style="padding:7px 10px;font-size:8pt;color:#fff;text-align:left;font-weight:700;text-transform:uppercase;letter-spacing:.06em;">Description</th>
              <th style="padding:7px 10px;font-size:8pt;color:#fff;text-align:right;font-weight:700;text-transform:uppercase;letter-spacing:.06em;">Amount</th>
            </tr>
          </thead>
          <tbody>
            <tr style="background:#f8fafc;">
              <td style="padding:8px 10px;font-size:9pt;color:#1e293b;">
                Payment via <?= ucfirst(str_replace('_',' ',$p['payment_method'])) ?>
                <?php if ($p['bank_name']): ?> — <?= htmlspecialchars($p['bank_name']) ?><?php endif; ?>
                <?php if ($p['reference_number']): ?><br><span style="font-size:8pt;color:#64748b;">Ref: <?= htmlspecialchars($p['reference_number']) ?></span><?php endif; ?>
                <?php if ($p['notes']): ?><br><span style="font-size:8pt;color:#64748b;"><?= htmlspecialchars($p['notes']) ?></span><?php endif; ?>
              </td>
              <td style="padding:8px 10px;font-size:11pt;font-weight:800;text-align:right;color:#16a34a;"><?= $cur ?> <?= number_format((float)$p['amount'],2) ?></td>
            </tr>
          </tbody>
        </table>
        <!-- Total row -->
        <table style="width:100%;border-collapse:collapse;margin-bottom:14px;">
          <tr style="background:<?= htmlspecialchars($inv_color) ?>;-webkit-print-color-adjust:exact;print-color-adjust:exact;">
            <td style="padding:9px 10px;font-size:10pt;font-weight:800;color:#fff;text-transform:uppercase;letter-spacing:.04em;">Total Amount Received</td>
            <td style="padding:9px 10px;font-size:12pt;font-weight:800;color:#fff;text-align:right;"><?= $cur ?> <?= number_format((float)$p['amount'],2) ?></td>
          </tr>
        </table>
        <!-- Footer -->
        <div style="margin-top:16px;padding-top:10px;border-top:1px solid #e2e8f0;font-size:8pt;color:#64748b;text-align:center;">
          Received by: <strong><?= htmlspecialchars($p['received_by_name'] ?? '—') ?></strong> &nbsp;|&nbsp;
          Generated: <?= date('d M Y, h:i A') ?> &nbsp;|&nbsp;
          <?= htmlspecialchars($app_name) ?> — Thank you for your payment!
        </div>
      </div>

      <!-- ===== SCREEN GRADIENT HEADER ===== -->
      <div class="rcpt-gradient-header">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
          <div>
            <div class="rcpt-badge mb-2">Payment Receipt</div>
            <div style="font-size:1.6rem;font-weight:800;letter-spacing:-.02em;color:#fff;"><?= Helper::sanitize($p['payment_ref']) ?></div>
            <div style="opacity:.75;font-size:.85rem;margin-top:.2rem;"><?= Helper::formatDate($p['payment_date']) ?></div>
          </div>
          <div style="text-align:right;">
            <div style="font-size:1.8rem;font-weight:800;color:#fff;"><?= $cur ?> <?= number_format((float)$p['amount'],2) ?></div>
            <div style="opacity:.75;font-size:.82rem;">Amount Received</div>
          </div>
        </div>
        <hr class="rcpt-divider">
        <div class="row">
          <div class="col-md-6">
            <div style="font-size:.68rem;font-weight:700;opacity:.65;text-transform:uppercase;letter-spacing:.08em;margin-bottom:.4rem;">From</div>
            <div style="font-weight:700;font-size:1rem;"><?= Helper::sanitize($p['branch_name']) ?></div>
            <?php if ($co_address): ?><div style="opacity:.75;font-size:.82rem;"><?= nl2br(Helper::sanitize($co_address)) ?></div><?php endif; ?>
            <?php if ($co_phone): ?><div style="opacity:.65;font-size:.8rem;">📞 <?= Helper::sanitize($co_phone) ?></div><?php endif; ?>
          </div>
          <div class="col-md-6">
            <div style="font-size:.68rem;font-weight:700;opacity:.65;text-transform:uppercase;letter-spacing:.08em;margin-bottom:.4rem;">Received From</div>
            <div style="font-weight:700;font-size:1rem;"><?= Helper::sanitize($p['customer_name']) ?></div>
            <?php if ($p['customer_phone']): ?><div style="opacity:.75;font-size:.82rem;">📞 <?= Helper::sanitize($p['customer_phone']) ?></div><?php endif; ?>
            <?php if ($p['customer_email']): ?><div style="opacity:.65;font-size:.8rem;">✉ <?= Helper::sanitize($p['customer_email']) ?></div><?php endif; ?>
          </div>
        </div>
        <?php if ($p['booking_ref']): ?>
        <div style="margin-top:.8rem;font-size:.8rem;opacity:.7;">
          Booking: <?= Helper::sanitize($p['booking_ref']) ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- ===== SCREEN BODY ===== -->
      <div class="card-body rcpt-body">

        <!-- Payment detail rows -->
        <div class="mb-4 p-3 bg-light rounded">
          <?php if ($p['payment_method']): ?>
          <div class="detail-row">
            <span class="text-secondary">Payment Method</span>
            <span class="fw-semibold"><?= ucfirst(str_replace('_',' ',$p['payment_method'])) ?></span>
          </div>
          <?php endif; ?>
          <?php if ($p['bank_name']): ?>
          <div class="detail-row">
            <span class="text-secondary">Bank</span>
            <span><?= Helper::sanitize($p['bank_name']) ?></span>
          </div>
          <?php endif; ?>
          <?php if ($p['reference_number']): ?>
          <div class="detail-row">
            <span class="text-secondary">Reference No.</span>
            <span><?= Helper::sanitize($p['reference_number']) ?></span>
          </div>
          <?php endif; ?>
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

        <?php if ($p['notes']): ?>
        <div class="mb-3">
          <div class="text-secondary small text-uppercase fw-semibold mb-1" style="font-size:.7rem;letter-spacing:.06em;">Notes</div>
          <div><?= nl2br(Helper::sanitize($p['notes'])) ?></div>
        </div>
        <?php endif; ?>

        <!-- Amount -->
        <div class="text-center my-4 p-4 rounded" style="background:#f0fdf4;border:2px solid #bbf7d0;">
          <div class="text-secondary small text-uppercase fw-semibold" style="letter-spacing:.08em;font-size:.75rem;">Amount Received</div>
          <div style="font-size:2.4rem;font-weight:800;color:#16a34a;margin-top:.25rem;"><?= $cur ?> <?= number_format((float)$p['amount'],2) ?></div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-4 pt-3 border-top text-secondary small">
          <p class="mb-0">Received by: <strong><?= Helper::sanitize($p['received_by_name']??'—') ?></strong></p>
          <p class="mb-0">Generated: <?= date('d M Y, h:i A') ?></p>
          <p class="mb-1"><?= Helper::sanitize($app_name) ?> — Thank you for your payment!</p>
        </div>

      </div><!-- /card-body -->
    </div><!-- /card -->
  </div><!-- /col-lg-8 -->
</div><!-- /row -->

<?php if (!empty($_GET['autoprint'])): ?>
<script>window.addEventListener('load', function(){ setTimeout(function(){ window.print(); }, 600); });</script>
<?php endif; ?>
<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
