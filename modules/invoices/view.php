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

// Get invoice paper size + branding settings
$inv_paper_size = Helper::getSetting('invoice_paper_size', $inv['branch_id']) ?? 'A4';
$inv_color      = Helper::getSetting('invoice_color', $inv['branch_id']) ?? '#1a3c6e';
$cur            = Helper::getSetting('currency_symbol', $inv['branch_id']) ?? 'Rs.';
$app_name       = Helper::getSetting('company_name', $inv['branch_id']) ?? Helper::getSetting('company_name') ?? APP_NAME;
$co_tagline     = Helper::getSetting('company_tagline', $inv['branch_id']) ?? '';
$co_address     = Helper::getSetting('company_address', $inv['branch_id']) ?? $inv['branch_address'] ?? '';
$co_phone       = Helper::getSetting('company_phone', $inv['branch_id']) ?? $inv['branch_phone'] ?? '';
$co_email       = Helper::getSetting('company_email', $inv['branch_id']) ?? '';
$co_website     = Helper::getSetting('company_website', $inv['branch_id']) ?? '';
$inv_logo       = Helper::getSetting('company_logo', $inv['branch_id']);
$inv_show_logo  = Helper::getSetting('invoice_show_logo', $inv['branch_id']) ?? '1';
$inv_terms      = $inv['terms'] ?: (Helper::getSetting('invoice_terms', $inv['branch_id']) ?? '');
$bank_name      = Helper::getSetting('bank_name', $inv['branch_id']) ?? '';
$bank_acc_nm    = Helper::getSetting('bank_account_name', $inv['branch_id']) ?? '';
$bank_acc_no    = Helper::getSetting('bank_account_number', $inv['branch_id']) ?? '';
$bank_branch    = Helper::getSetting('bank_branch', $inv['branch_id']) ?? '';
$has_bank       = ($bank_name || $bank_acc_no);
$logo_path      = ($inv_show_logo === '1' && $inv_logo) ? BASE_URL.'/uploads/'.$inv_logo : null;

$status_map = [
  'paid'      => ['PAID',             '#16a34a', '#dcfce7'],
  'partial'   => ['PARTIALLY PAID',  '#d97706', '#fef3c7'],
  'sent'      => ['UNPAID',           '#dc2626', '#fee2e2'],
  'overdue'   => ['OVERDUE',          '#dc2626', '#fee2e2'],
  'draft'     => ['DRAFT',            '#64748b', '#f1f5f9'],
  'cancelled' => ['CANCELLED',        '#475569', '#e2e8f0'],
];
$st = $status_map[$inv['status']] ?? ['UNPAID', '#dc2626', '#fee2e2'];

$statusColors = ['draft'=>'secondary','sent'=>'info','paid'=>'success','partial'=>'warning','overdue'=>'danger','cancelled'=>'dark'];
$typeColors   = ['advance'=>'purple','interim'=>'blue','final'=>'green'];

$pageTitle = 'Invoice: '.$inv['invoice_number'];
$breadcrumbs = [['label'=>'Invoices','url'=>BASE_URL.'/modules/invoices/index.php'],['label'=>$inv['invoice_number']]];
require_once ROOT_PATH . '/includes/header.php';
?>
<style>
/* ===== PRINT STYLES ===== */
@media print {
  /* Hide ALL chrome — topbar, sidebar, nav, page header, gradient card header, screen-only totals */
  .d-print-none,
  .vp-page-header, .vp-topbar, .vp-sidebar, .vp-topbar-breadcrumb,
  .vp-topbar-right, .vp-topbar-branch, .vp-topbar-user,
  .sidebar, .navbar, .topbar, .footer-bar,
  .col-lg-4, #sidebar,
  .inv-header-gradient,
  .totals-section,
  .card-body.border-top { display:none !important; }

  /* Layout reset */
  * { -webkit-print-color-adjust:exact !important; print-color-adjust:exact !important; }
  html, body { background:#fff !important; margin:0 !important; padding:0 !important; font-size:10pt; color:#1e293b; }
  .invoice-print-card { box-shadow:none !important; border:none !important; border-radius:0 !important; margin:0 !important; padding:0 !important; overflow:visible !important; }
  .col-lg-8 { width:100% !important; flex:0 0 100% !important; max-width:100% !important; padding:0 !important; margin:0 !important; }
  .row { display:block !important; margin:0 !important; }
  .container, .container-fluid { padding:0 !important; margin:0 !important; }
  a { color:inherit !important; text-decoration:none !important; }
  .card { border:none !important; box-shadow:none !important; }

  /* Show print-only block */
  .print-only { display:block !important; }

  /* Items table */
  .items-table th { background:#f1f5f9 !important; }
  .table-responsive { overflow:visible !important; }
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
      <button onclick="window.print()" class="btn btn-vp-outline d-print-none"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>Print</button>
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

      <!-- PRINT-ONLY HEADER (hidden on screen, visible when printing) -->
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
                <?php if ($co_address): ?><?= nl2br(htmlspecialchars($co_address)) ?><br><?php endif; ?>
                <?php if ($co_phone): ?>Tel: <?= htmlspecialchars($co_phone) ?><?php if ($co_email): ?> &nbsp;|&nbsp; <?php endif; ?><?php endif; ?>
                <?php if ($co_email): ?>Email: <?= htmlspecialchars($co_email) ?><?php endif; ?>
                <?php if ($co_website): ?><br><?= htmlspecialchars($co_website) ?><?php endif; ?>
              </div>
            </td>
            <td style="width:42%;vertical-align:top;text-align:right;padding:0;">
              <div style="font-size:24pt;font-weight:bold;color:<?= htmlspecialchars($inv_color) ?>;letter-spacing:1px;line-height:1;">INVOICE</div>
              <table style="width:100%;border-collapse:collapse;margin-top:8px;">
                <tr><td style="font-size:8pt;color:#94a3b8;text-align:right;padding:1px 0;">Invoice No</td><td style="font-size:8pt;font-weight:bold;text-align:right;padding:1px 0 1px 8px;"><?= htmlspecialchars($inv['invoice_number']) ?></td></tr>
                <tr><td style="font-size:8pt;color:#94a3b8;text-align:right;padding:1px 0;">Date</td><td style="font-size:8pt;font-weight:bold;text-align:right;padding:1px 0 1px 8px;"><?= date('d M Y', strtotime($inv['invoice_date'])) ?></td></tr>
                <?php if ($inv['due_date']): ?><tr><td style="font-size:8pt;color:#94a3b8;text-align:right;padding:1px 0;">Due Date</td><td style="font-size:8pt;font-weight:bold;text-align:right;padding:1px 0 1px 8px;"><?= date('d M Y', strtotime($inv['due_date'])) ?></td></tr><?php endif; ?>
                <?php if ($inv['booking_ref']): ?><tr><td style="font-size:8pt;color:#94a3b8;text-align:right;padding:1px 0;">Booking Ref</td><td style="font-size:8pt;font-weight:bold;text-align:right;padding:1px 0 1px 8px;"><?= htmlspecialchars($inv['booking_ref']) ?></td></tr><?php endif; ?>
              </table>
              <div style="margin-top:6px;display:inline-block;padding:3px 10px;font-size:8pt;font-weight:bold;color:<?= $st[1] ?>;background:<?= $st[2] ?>;border:1px solid <?= $st[1] ?>;"><?= $st[0] ?></div>
            </td>
          </tr>
        </table>
        <div style="height:3px;background:<?= htmlspecialchars($inv_color) ?>;margin:10px 0 12px;"></div>
        <!-- Parties: Bill To only -->
        <div style="margin-bottom:12px;">
          <div style="font-size:7pt;font-weight:bold;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;margin-bottom:3px;">Bill To</div>
          <div style="font-size:11pt;font-weight:bold;color:#0f172a;"><?= htmlspecialchars($inv['customer_name']) ?></div>
          <div style="font-size:8pt;color:#475569;line-height:1.5;margin-top:2px;">
            <?php if ($inv['customer_phone']): ?><?= htmlspecialchars($inv['customer_phone']) ?><br><?php endif; ?>
            <?php if ($inv['customer_email']): ?><?= htmlspecialchars($inv['customer_email']) ?><br><?php endif; ?>
            <?php if ($inv['customer_address']): ?><?= nl2br(htmlspecialchars($inv['customer_address'])) ?><?php endif; ?>
          </div>
        </div>
      </div>

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

      <!-- PRINT-ONLY: Totals + Bank + Signature -->
      <div class="print-only" style="margin-top:10px;">
        <table style="width:100%;border-collapse:collapse;">
          <tr>
            <!-- Left: bank details + notes -->
            <td style="width:52%;vertical-align:top;padding-right:20px;">
              <?php if ($has_bank): ?>
              <div style="background:#f8fafc;border:1px solid #e2e8f0;padding:10px 12px;margin-bottom:8px;">
                <div style="font-size:7.5pt;font-weight:bold;color:<?= htmlspecialchars($inv_color) ?>;text-transform:uppercase;letter-spacing:0.8px;margin-bottom:5px;">Bank Details</div>
                <div style="font-size:8.5pt;color:#475569;line-height:1.6;">
                  <?php if ($bank_name): ?><strong style="color:#1e293b;"><?= htmlspecialchars($bank_name) ?></strong><br><?php endif; ?>
                  <?php if ($bank_acc_nm): ?>A/C Name: <?= htmlspecialchars($bank_acc_nm) ?><br><?php endif; ?>
                  <?php if ($bank_acc_no): ?>A/C No: <strong><?= htmlspecialchars($bank_acc_no) ?></strong><br><?php endif; ?>
                  <?php if ($bank_branch): ?>Branch: <?= htmlspecialchars($bank_branch) ?><?php endif; ?>
                </div>
              </div>
              <?php endif; ?>
              <?php if ($inv['notes']): ?>
              <div style="background:#f8fafc;border:1px solid #e2e8f0;padding:10px 12px;">
                <div style="font-size:7.5pt;font-weight:bold;color:<?= htmlspecialchars($inv_color) ?>;text-transform:uppercase;letter-spacing:0.8px;margin-bottom:5px;">Notes</div>
                <div style="font-size:8.5pt;color:#475569;"><?= nl2br(htmlspecialchars($inv['notes'])) ?></div>
              </div>
              <?php endif; ?>
            </td>
            <!-- Right: totals -->
            <td style="width:48%;vertical-align:top;">
              <table style="width:100%;border-collapse:collapse;">
                <tr><td style="padding:5px 10px;font-size:9pt;color:#64748b;">Subtotal</td><td style="padding:5px 10px;font-size:9pt;font-weight:bold;text-align:right;"><?= $cur ?> <?= number_format($inv['subtotal'],2) ?></td></tr>
                <?php if ($inv['discount_amount'] > 0): ?>
                <tr><td style="padding:5px 10px;font-size:9pt;color:#64748b;">Discount</td><td style="padding:5px 10px;font-size:9pt;font-weight:bold;text-align:right;color:#dc2626;">- <?= $cur ?> <?= number_format($inv['discount_amount'],2) ?></td></tr>
                <?php endif; ?>
                <?php if ($inv['tax_amount'] > 0): ?>
                <tr><td style="padding:5px 10px;font-size:9pt;color:#64748b;">Tax</td><td style="padding:5px 10px;font-size:9pt;font-weight:bold;text-align:right;"><?= $cur ?> <?= number_format($inv['tax_amount'],2) ?></td></tr>
                <?php endif; ?>
                <tr style="-webkit-print-color-adjust:exact;print-color-adjust:exact;">
                  <td style="padding:10px 12px;font-size:11pt;font-weight:bold;background:<?= htmlspecialchars($inv_color) ?>;color:#fff;">TOTAL</td>
                  <td style="padding:10px 12px;font-size:11pt;font-weight:bold;text-align:right;background:<?= htmlspecialchars($inv_color) ?>;color:#fff;"><?= $cur ?> <?= number_format($inv['total'],2) ?></td>
                </tr>
                <?php if ($inv['paid_amount'] > 0): ?>
                <tr><td style="padding:5px 10px;font-size:9pt;color:#16a34a;">Paid</td><td style="padding:5px 10px;font-size:9pt;font-weight:bold;text-align:right;color:#16a34a;">- <?= $cur ?> <?= number_format($inv['paid_amount'],2) ?></td></tr>
                <?php endif; ?>
                <?php if ($inv['balance'] > 0): ?>
                <tr style="-webkit-print-color-adjust:exact;print-color-adjust:exact;">
                  <td style="padding:8px 12px;font-size:10pt;font-weight:bold;background:#fef2f2;color:#b91c1c;border-top:1px solid #fecaca;">BALANCE DUE</td>
                  <td style="padding:8px 12px;font-size:10pt;font-weight:bold;text-align:right;background:#fef2f2;color:#b91c1c;border-top:1px solid #fecaca;"><?= $cur ?> <?= number_format($inv['balance'],2) ?></td>
                </tr>
                <?php endif; ?>
              </table>
            </td>
          </tr>
        </table>

        <?php if ($inv_terms): ?>
        <div style="margin-top:12px;padding:8px 12px;border-left:3px solid <?= htmlspecialchars($inv_color) ?>;background:#f8fafc;">
          <div style="font-size:7.5pt;font-weight:bold;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;margin-bottom:3px;">Terms &amp; Conditions</div>
          <div style="font-size:8pt;color:#64748b;line-height:1.5;"><?= nl2br(htmlspecialchars($inv_terms)) ?></div>
        </div>
        <?php endif; ?>

        <table style="width:100%;border-collapse:collapse;margin-top:22px;">
          <tr>
            <td style="width:50%;vertical-align:bottom;padding-top:20px;">
              <div style="border-top:1px solid #94a3b8;padding-top:4px;font-size:8pt;color:#64748b;width:70%;">Customer Signature</div>
            </td>
            <td style="width:50%;vertical-align:bottom;text-align:right;padding-top:20px;">
              <div style="border-top:1px solid #94a3b8;padding-top:4px;font-size:8pt;color:#64748b;width:70%;margin-left:auto;">For <?= htmlspecialchars($app_name) ?></div>
            </td>
          </tr>
        </table>
        <div style="text-align:center;margin-top:12px;font-size:8.5pt;font-weight:bold;color:<?= htmlspecialchars($inv_color) ?>;">Thank you for your business!</div>
        <div style="text-align:center;margin-top:3px;font-size:7pt;color:#cbd5e1;">Generated <?= date('d M Y, h:i A') ?> &middot; <?= htmlspecialchars($inv['invoice_number']) ?></div>
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
        <button onclick="window.print()" class="btn btn-vp-outline w-100"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>Print Invoice</button>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/modules/invoices/index.php" class="btn btn-outline-secondary w-100">← Back to Invoices</a>
      </div>
    </div>
  </div>
</div>
<?php if (!empty($_GET['autoprint'])): ?>
<script>window.addEventListener('load', function(){ setTimeout(function(){ window.print(); }, 600); });</script>
<?php endif; ?>
<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
