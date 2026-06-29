<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
$db = Database::getInstance();
$cu = Auth::currentUser();
$id = (int)($_GET['id'] ?? 0);

$inv = $db->fetchOne(
    "SELECT i.*, c.name as customer_name, c.mobile as customer_phone, c.email as customer_email, c.address as customer_address,
            br.name as branch_name, br.address as branch_address, br.phone as branch_phone, br.email as branch_email,
            b.booking_ref
     FROM invoices i
     LEFT JOIN customers c ON i.customer_id=c.id
     LEFT JOIN branches br ON i.branch_id=br.id
     LEFT JOIN bookings b ON i.booking_id=b.id
     WHERE i.id=?", [$id]
);
if (!$inv) die('Invoice not found.');
if ($cu['branch_id'] && $inv['branch_id'] != $cu['branch_id']) die('Access denied.');

$items = $db->fetchAll("SELECT * FROM invoice_items WHERE invoice_id=?", [$id]);

// --- Company / branding settings ---
$app_name      = Helper::getSetting('company_name', $inv['branch_id']) ?? Helper::getSetting('company_name') ?? APP_NAME;
$co_tagline    = Helper::getSetting('company_tagline', $inv['branch_id']) ?? '';
$co_address    = Helper::getSetting('company_address', $inv['branch_id']) ?? $inv['branch_address'] ?? '';
$co_phone      = Helper::getSetting('company_phone', $inv['branch_id']) ?? $inv['branch_phone'] ?? '';
$co_email      = Helper::getSetting('company_email', $inv['branch_id']) ?? $inv['branch_email'] ?? '';
$co_website    = Helper::getSetting('company_website', $inv['branch_id']) ?? '';
$cur           = Helper::getSetting('currency_symbol', $inv['branch_id']) ?? 'Rs.';

$inv_color     = Helper::getSetting('invoice_color', $inv['branch_id']) ?? '#1a3c6e';
$inv_fsize     = (int)(Helper::getSetting('invoice_font_size', $inv['branch_id']) ?? 10);
$inv_logo      = Helper::getSetting('company_logo', $inv['branch_id']);
$inv_show_logo = Helper::getSetting('invoice_show_logo', $inv['branch_id']) ?? '1';

// --- Bank details ---
$bank_name   = Helper::getSetting('bank_name', $inv['branch_id']) ?? '';
$bank_acc_nm = Helper::getSetting('bank_account_name', $inv['branch_id']) ?? '';
$bank_acc_no = Helper::getSetting('bank_account_number', $inv['branch_id']) ?? '';
$bank_branch = Helper::getSetting('bank_branch', $inv['branch_id']) ?? '';
$has_bank    = ($bank_name || $bank_acc_no);

// --- Terms ---
$inv_terms   = $inv['terms'] ?: (Helper::getSetting('invoice_terms', $inv['branch_id']) ?? '');

// helper
$logo_path = ($inv_show_logo === '1' && $inv_logo && file_exists(ROOT_PATH.'/uploads/'.$inv_logo)) ? ROOT_PATH.'/uploads/'.$inv_logo : null;
function money($v, $cur) { return $cur.' '.number_format((float)$v, 2); }

// Status label / color
$status_map = [
  'paid'    => ['PAID', '#16a34a', '#dcfce7'],
  'partial' => ['PARTIALLY PAID', '#d97706', '#fef3c7'],
  'sent'    => ['UNPAID', '#dc2626', '#fee2e2'],
  'overdue' => ['OVERDUE', '#dc2626', '#fee2e2'],
  'draft'   => ['DRAFT', '#64748b', '#f1f5f9'],
  'cancelled' => ['CANCELLED', '#475569', '#e2e8f0'],
];
$st = $status_map[$inv['status']] ?? ['UNPAID', '#dc2626', '#fee2e2'];

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: 'dejavusans', sans-serif; font-size: <?= $inv_fsize ?>pt; color: #1e293b; line-height: 1.45; }

  .brand-color { color: <?= htmlspecialchars($inv_color) ?>; }

  /* ===== HEADER ===== */
  .doc-header { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
  .doc-header td { vertical-align: top; padding: 0; }
  .logo-cell { width: 58%; }
  .logo-cell img { max-height: 64px; max-width: 200px; }
  .company-name { font-size: 17pt; font-weight: bold; color: <?= htmlspecialchars($inv_color) ?>; line-height: 1.1; }
  .company-tagline { font-size: 8pt; color: #64748b; margin-top: 2px; }
  .company-meta { font-size: 8pt; color: #475569; margin-top: 6px; line-height: 1.5; }

  .invoice-cell { width: 42%; text-align: right; }
  .invoice-word { font-size: 26pt; font-weight: bold; color: <?= htmlspecialchars($inv_color) ?>; letter-spacing: 1px; line-height: 1; }
  .invoice-meta { margin-top: 10px; font-size: 8.5pt; color: #475569; }
  .invoice-meta table { width: 100%; border-collapse: collapse; }
  .invoice-meta td { padding: 1.5px 0; text-align: right; }
  .invoice-meta .lbl { color: #94a3b8; padding-right: 8px; }
  .invoice-meta .val { font-weight: bold; color: #1e293b; }

  .status-chip {
    padding: 3px 10px;
    font-size: 8.5pt; font-weight: bold; letter-spacing: 0.5px;
    color: <?= $st[1] ?>; background: <?= $st[2] ?>; border: 1px solid <?= $st[1] ?>;
    margin-top: 6px;
  }

  .rule { height: 3px; background: <?= htmlspecialchars($inv_color) ?>; margin: 8px 0 12px; }

  /* ===== PARTIES ===== */
  .parties { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
  .parties td { vertical-align: top; width: 50%; padding: 0; }
  .party-box { padding-right: 18px; }
  .party-label { font-size: 7.5pt; font-weight: bold; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px; }
  .party-name { font-size: 11pt; font-weight: bold; color: #0f172a; margin-bottom: 2px; }
  .party-detail { font-size: 8.5pt; color: #475569; line-height: 1.5; }

  /* ===== ITEMS TABLE ===== */
  table.items { width: 100%; border-collapse: collapse; margin-bottom: 0; }
  table.items thead th {
    background: <?= htmlspecialchars($inv_color) ?>; color: #fff;
    font-size: 8pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px;
    padding: 9px 10px; text-align: left;
  }
  table.items thead th.r { text-align: right; }
  table.items thead th.c { text-align: center; }
  table.items tbody td {
    padding: 9px 10px; font-size: <?= $inv_fsize ?>pt; color: #334155;
    border-bottom: 1px solid #e2e8f0;
  }
  table.items tbody td.r { text-align: right; }
  table.items tbody td.c { text-align: center; }
  table.items tbody tr:nth-child(even) td { background: #f8fafc; }
  .item-desc { font-weight: 600; color: #1e293b; }

  /* ===== TOTALS ===== */
  .totals-wrap { width: 100%; border-collapse: collapse; margin-top: 10px; }
  .totals-wrap td { vertical-align: top; }
  .totals-left { width: 52%; padding-right: 20px; }
  .totals-right { width: 48%; }

  .totals-table { width: 100%; border-collapse: collapse; }
  .totals-table td { padding: 6px 12px; font-size: 9pt; }
  .totals-table td.lbl { color: #64748b; text-align: left; }
  .totals-table td.val { text-align: right; font-weight: bold; color: #1e293b; }
  .totals-table tr.sub td { border-bottom: 1px solid #e2e8f0; }
  .totals-table tr.grand td {
    background: <?= htmlspecialchars($inv_color) ?>; color: #fff;
    font-size: 11pt; font-weight: bold; padding: 11px 12px;
  }
  .totals-table tr.grand td.lbl { color: #fff; }
  .totals-table tr.paid td { color: #16a34a; }
  .totals-table tr.balance td {
    background: #fef2f2; color: #b91c1c; font-weight: bold;
    font-size: 10pt; padding: 9px 12px; border-top: 1px solid #fecaca;
  }

  /* ===== BANK / NOTES BOX ===== */
  .info-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 11px 13px; margin-bottom: 10px; }
  .info-box-title { font-size: 7.5pt; font-weight: bold; color: <?= htmlspecialchars($inv_color) ?>; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 6px; }
  .info-box-row { font-size: 8.5pt; color: #475569; line-height: 1.6; }
  .info-box-row strong { color: #1e293b; }

  /* ===== TERMS / FOOTER ===== */
  .terms-sec { margin-top: 12px; padding-top: 8px; border-top: 1px solid #e2e8f0; }
  .terms-title { font-size: 7.5pt; font-weight: bold; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px; }
  .terms-text { font-size: 8pt; color: #64748b; line-height: 1.5; }

  .signature { width: 100%; border-collapse: collapse; margin-top: 18px; }
  .signature td { width: 50%; vertical-align: bottom; padding-top: 20px; }
  .sig-line { border-top: 1px solid #94a3b8; padding-top: 5px; font-size: 8pt; color: #64748b; width: 70%; }
  .sig-right { text-align: right; }
  .sig-right .sig-line { margin-left: auto; }

  .thanks { text-align: center; margin-top: 14px; font-size: 9pt; font-weight: bold; color: <?= htmlspecialchars($inv_color) ?>; }
  .page-foot { text-align: center; margin-top: 4px; font-size: 7pt; color: #cbd5e1; }
</style>
</head>
<body>

<!-- HEADER -->
<table class="doc-header">
  <tr>
    <td class="logo-cell">
      <?php if ($logo_path): ?>
        <img src="<?= $logo_path ?>" alt="Logo"><br>
      <?php endif; ?>
      <div class="company-name"><?= htmlspecialchars($app_name) ?></div>
      <?php if ($co_tagline): ?><div class="company-tagline"><?= htmlspecialchars($co_tagline) ?></div><?php endif; ?>
      <div class="company-meta">
        <?php if ($co_address): ?><?= nl2br(htmlspecialchars($co_address)) ?><br><?php endif; ?>
        <?php if ($co_phone): ?>Tel: <?= htmlspecialchars($co_phone) ?> &nbsp; <?php endif; ?>
        <?php if ($co_email): ?>Email: <?= htmlspecialchars($co_email) ?><?php endif; ?>
        <?php if ($co_website): ?><br><?= htmlspecialchars($co_website) ?><?php endif; ?>
      </div>
    </td>
    <td class="invoice-cell">
      <div class="invoice-word">INVOICE</div>
      <div class="invoice-meta">
        <table>
          <tr><td class="lbl">Invoice No</td><td class="val"><?= htmlspecialchars($inv['invoice_number']) ?></td></tr>
          <tr><td class="lbl">Date</td><td class="val"><?= date('d M Y', strtotime($inv['invoice_date'])) ?></td></tr>
          <?php if ($inv['due_date']): ?><tr><td class="lbl">Due Date</td><td class="val"><?= date('d M Y', strtotime($inv['due_date'])) ?></td></tr><?php endif; ?>
          <?php if ($inv['booking_ref']): ?><tr><td class="lbl">Booking Ref</td><td class="val"><?= htmlspecialchars($inv['booking_ref']) ?></td></tr><?php endif; ?>
        </table>
      </div>
      <span class="status-chip"><?= $st[0] ?></span>
    </td>
  </tr>
</table>

<div class="rule"></div>

<!-- PARTIES -->
<table class="parties">
  <tr>
    <td>
      <div class="party-box">
        <div class="party-label">Bill To</div>
        <div class="party-name"><?= htmlspecialchars($inv['customer_name']) ?></div>
        <div class="party-detail">
          <?php if ($inv['customer_phone']): ?><?= htmlspecialchars($inv['customer_phone']) ?><br><?php endif; ?>
          <?php if ($inv['customer_email']): ?><?= htmlspecialchars($inv['customer_email']) ?><br><?php endif; ?>
          <?php if ($inv['customer_address']): ?><?= nl2br(htmlspecialchars($inv['customer_address'])) ?><?php endif; ?>
        </div>
      </div>
    </td>
    <td>
      <div class="party-box" style="padding-right:0;">
        <div class="party-label">Branch / Venue</div>
        <div class="party-name"><?= htmlspecialchars($inv['branch_name']) ?></div>
        <div class="party-detail">
          <?php if ($inv['branch_address']): ?><?= nl2br(htmlspecialchars($inv['branch_address'])) ?><br><?php endif; ?>
          <?php if ($inv['branch_phone']): ?>Tel: <?= htmlspecialchars($inv['branch_phone']) ?><?php endif; ?>
        </div>
      </div>
    </td>
  </tr>
</table>

<!-- ITEMS -->
<table class="items">
  <thead>
    <tr>
      <th class="c" width="6%">#</th>
      <th width="44%">Description</th>
      <th class="c" width="10%">Qty</th>
      <th class="r" width="16%">Unit Price</th>
      <th class="c" width="9%">Tax</th>
      <th class="r" width="15%">Amount</th>
    </tr>
  </thead>
  <tbody>
    <?php $n = 1; foreach ($items as $it): ?>
    <tr>
      <td class="c"><?= $n ?></td>
      <td class="item-desc"><?= htmlspecialchars($it['description']) ?></td>
      <td class="c"><?= (int)$it['quantity'] ?></td>
      <td class="r"><?= money($it['unit_price'], $cur) ?></td>
      <td class="c"><?= rtrim(rtrim(number_format($it['tax_percent'],1),'0'),'.') ?>%</td>
      <td class="r"><?= money($it['total'], $cur) ?></td>
    </tr>
    <?php $n++; endforeach; ?>
    <?php if (empty($items)): ?>
    <tr><td colspan="6" class="c" style="color:#94a3b8;padding:18px;">No line items</td></tr>
    <?php endif; ?>
  </tbody>
</table>

<!-- TOTALS + INFO -->
<table class="totals-wrap">
  <tr>
    <td class="totals-left">
      <?php if ($has_bank): ?>
      <div class="info-box">
        <div class="info-box-title">Bank Details</div>
        <div class="info-box-row">
          <?php if ($bank_name): ?><strong><?= htmlspecialchars($bank_name) ?></strong><br><?php endif; ?>
          <?php if ($bank_acc_nm): ?>A/C Name: <?= htmlspecialchars($bank_acc_nm) ?><br><?php endif; ?>
          <?php if ($bank_acc_no): ?>A/C No: <strong><?= htmlspecialchars($bank_acc_no) ?></strong><br><?php endif; ?>
          <?php if ($bank_branch): ?>Branch: <?= htmlspecialchars($bank_branch) ?><?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
      <?php if ($inv['notes']): ?>
      <div class="info-box">
        <div class="info-box-title">Notes</div>
        <div class="info-box-row"><?= nl2br(htmlspecialchars($inv['notes'])) ?></div>
      </div>
      <?php endif; ?>
    </td>
    <td class="totals-right">
      <table class="totals-table">
        <tr class="sub"><td class="lbl">Subtotal</td><td class="val"><?= money($inv['subtotal'], $cur) ?></td></tr>
        <?php if ($inv['discount_amount'] > 0): ?>
        <tr class="sub"><td class="lbl">Discount</td><td class="val" style="color:#dc2626;">- <?= money($inv['discount_amount'], $cur) ?></td></tr>
        <?php endif; ?>
        <?php if ($inv['tax_amount'] > 0): ?>
        <tr class="sub"><td class="lbl">Tax</td><td class="val"><?= money($inv['tax_amount'], $cur) ?></td></tr>
        <?php endif; ?>
        <tr class="grand"><td class="lbl">TOTAL</td><td class="val"><?= money($inv['total'], $cur) ?></td></tr>
        <?php if ($inv['paid_amount'] > 0): ?>
        <tr class="paid"><td class="lbl">Paid</td><td class="val">- <?= money($inv['paid_amount'], $cur) ?></td></tr>
        <?php endif; ?>
        <?php if ($inv['balance'] > 0): ?>
        <tr class="balance"><td class="lbl">BALANCE DUE</td><td class="val"><?= money($inv['balance'], $cur) ?></td></tr>
        <?php endif; ?>
      </table>
    </td>
  </tr>
</table>

<!-- TERMS -->
<?php if ($inv_terms): ?>
<div class="terms-sec">
  <div class="terms-title">Terms &amp; Conditions</div>
  <div class="terms-text"><?= nl2br(htmlspecialchars($inv_terms)) ?></div>
</div>
<?php endif; ?>

<!-- SIGNATURE -->
<table class="signature">
  <tr>
    <td><div class="sig-line">Customer Signature</div></td>
    <td class="sig-right"><div class="sig-line">For <?= htmlspecialchars($app_name) ?></div></td>
  </tr>
</table>

<div class="thanks">Thank you for your business!</div>
<div class="page-foot">Generated on <?= date('d M Y, h:i A') ?> &middot; <?= htmlspecialchars($inv['invoice_number']) ?></div>

</body>
</html>
<?php
$html = ob_get_clean();

// --- Load invoice layout settings ---
$inv_size        = Helper::getSetting('invoice_paper_size', $inv['branch_id']) ?? 'A4';
$inv_margin_top  = (int)(Helper::getSetting('invoice_margin_top',    $inv['branch_id']) ?? 10);
$inv_margin_bot  = (int)(Helper::getSetting('invoice_margin_bottom', $inv['branch_id']) ?? 10);
$inv_margin_left = (int)(Helper::getSetting('invoice_margin_left',   $inv['branch_id']) ?? 10);
$inv_margin_right= (int)(Helper::getSetting('invoice_margin_right',  $inv['branch_id']) ?? 10);

if ($inv_size === 'custom') {
    $cw = (int)(Helper::getSetting('invoice_paper_width',  $inv['branch_id']) ?? 210);
    $ch = (int)(Helper::getSetting('invoice_paper_height', $inv['branch_id']) ?? 297);
    $orient = Helper::getSetting('invoice_paper_orientation', $inv['branch_id']) ?? 'P';
    $mpdf_format = [$cw, $ch];
} elseif ($inv_size === '80mm') {
    // 80mm Receipt Format
    $mpdf_format  = [80, 200];
    $orient       = 'P';
    $inv_margin_left  = 3;
    $inv_margin_right = 3;
    $inv_margin_top   = 5;
    $inv_margin_bot   = 5;
    
    // Use receipt-optimized HTML
    ob_end_clean();
    ob_start();
    ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; }
  body { font-family: 'dejavusans'; font-size: 9pt; color: #333; text-align: center; }
  
  .header { margin-bottom: 8px; line-height: 1.2; }
  .header h1 { font-size: 12pt; font-weight: bold; margin: 4px 0; }
  .header p { font-size: 8pt; margin: 1px 0; color: #555; }
  
  .bill-info { 
    font-size: 8pt; 
    margin: 6px 0;
    border-top: 1px dashed #999;
    border-bottom: 1px dashed #999;
    padding: 3px 0;
  }
  .bill-info div { margin: 2px 0; }
  
  table.items { 
    width: 100%; 
    font-size: 8pt;
    margin: 8px 0;
    border-collapse: collapse;
  }
  table.items thead { border-top: 1px dashed #999; border-bottom: 1px dashed #999; }
  table.items th { 
    padding: 2px;
    text-align: left;
    font-weight: bold;
    font-size: 8pt;
  }
  table.items td { 
    padding: 2px;
    text-align: left;
  }
  table.items td.qty { text-align: center; }
  table.items td.price { text-align: right; }
  table.items td.amt { text-align: right; font-weight: bold; }
  
  .summary { 
    margin: 6px 0;
    font-size: 8pt;
    border-top: 1px dashed #999;
    border-bottom: 1px dashed #999;
    padding: 4px 0;
  }
  .summary-row { 
    display: flex;
    justify-content: space-between;
    margin: 2px 0;
    padding: 1px 0;
  }
  .summary-row.total { 
    font-weight: bold; 
    font-size: 9pt;
    border-top: 1px solid #999;
    border-bottom: 1px solid #999;
    padding: 3px 0;
    margin: 3px 0;
  }
  .summary-row.balance {
    font-weight: bold;
    color: #c00;
  }
  
  .footer { 
    margin-top: 8px;
    font-size: 8pt;
    text-align: center;
    color: #666;
  }
  
  .unpaid-stamp {
    position: relative;
    width: 60px;
    height: 60px;
    margin: 5px auto;
  }
  .unpaid-stamp .banner {
    background: <?= htmlspecialchars($inv_color) ?>;
    color: white;
    transform: rotate(-45deg);
    position: absolute;
    width: 100px;
    text-align: center;
    font-weight: bold;
    font-size: 10pt;
    top: 10px;
    left: -20px;
  }
</style>
</head>
<body>

<?php if ($inv['status'] !== 'paid'): ?>
<div class="unpaid-stamp">
  <div class="banner">Unpaid</div>
</div>
<?php endif; ?>

<div class="header">
  <h1><?= htmlspecialchars($app_name) ?></h1>
  <?php if ($inv['branch_address']): ?><p><?= htmlspecialchars($inv['branch_address']) ?></p><?php endif; ?>
  <?php if ($inv['branch_phone']): ?><p>PHONE: <?= htmlspecialchars($inv['branch_phone']) ?></p><?php endif; ?>
  <?php if ($inv['branch_email']): ?><p><?= htmlspecialchars($inv['branch_email']) ?></p><?php endif; ?>
</div>

<div class="bill-info">
  <div><strong>Bill No:</strong> <?= htmlspecialchars($inv['invoice_number']) ?> <strong>Date:</strong> <?= date('d-M-Y', strtotime($inv['invoice_date'])) ?></div>
  <?php if ($inv['customer_name']): ?><div><strong><?= htmlspecialchars($inv['customer_name']) ?></strong></div><?php endif; ?>
</div>

<table class="items">
  <thead>
    <tr>
      <th width="5%">SN</th>
      <th width="45%">Item</th>
      <th width="15%" class="qty">Qty</th>
      <th width="18%" class="price">Price</th>
      <th width="17%" class="amt">Amt</th>
    </tr>
  </thead>
  <tbody>
    <?php $sn = 1; foreach ($items as $it): ?>
    <tr>
      <td><?= $sn ?></td>
      <td><?= htmlspecialchars($it['description']) ?></td>
      <td class="qty"><?= (int)$it['quantity'] ?></td>
      <td class="price"><?= number_format($it['unit_price'], 0) ?></td>
      <td class="amt"><?= number_format($it['total'], 0) ?></td>
    </tr>
    <?php $sn++; endforeach; ?>
  </tbody>
</table>

<div class="summary">
  <div class="summary-row"><span>Subtotal</span><span><?= number_format($inv['subtotal'], 0) ?></span></div>
  
  <?php 
  // Group items by tax rate
  $tax_groups = [];
  foreach ($items as $it) {
    $rate = (int)$it['tax_percent'];
    if (!isset($tax_groups[$rate])) {
      $tax_groups[$rate] = 0;
    }
    $tax_groups[$rate] += ($it['total'] * $it['tax_percent'] / 100);
  }
  ksort($tax_groups);
  foreach ($tax_groups as $rate => $amount):
  ?>
  <div class="summary-row"><span>Tax at <?= $rate ?>%</span><span><?= number_format($amount, 0) ?></span></div>
  <?php endforeach; ?>
  
  <div class="summary-row total">
    <span>TOTAL</span>
    <span>₹ <?= number_format($inv['total'], 0) ?></span>
  </div>
  
  <?php if ($inv['paid_amount'] > 0): ?>
  <div class="summary-row"><span>Paid</span><span><?= number_format($inv['paid_amount'], 0) ?></span></div>
  <?php endif; ?>
  
  <?php if ($inv['balance'] > 0): ?>
  <div class="summary-row balance"><span>BALANCE DUE</span><span>₹ <?= number_format($inv['balance'], 0) ?></span></div>
  <?php endif; ?>
</div>

<div class="footer">
  Thank You
  <?php if ($inv['booking_ref']): ?><br>Booking: <?= htmlspecialchars($inv['booking_ref']) ?><?php endif; ?>
  <br><small><?= date('d M Y, h:i A') ?></small>
</div>

</body>
</html>
    <?php
    $html = ob_get_clean();
} elseif ($inv_size === 'A5') {
    $mpdf_format = 'A5';
    $orient      = 'P';
} else {
    $mpdf_format = 'A4';
    $orient      = 'P';
}

$mpdf = new \Mpdf\Mpdf([
    'mode'          => 'utf-8',
    'format'        => $mpdf_format,
    'orientation'   => $orient,
    'margin_top'    => $inv_margin_top,
    'margin_bottom' => $inv_margin_bot,
    'margin_left'   => $inv_margin_left,
    'margin_right'  => $inv_margin_right,
    'tempDir'       => ROOT_PATH.'/tmp/mpdf',
]);
$mpdf->SetTitle($inv['invoice_number']);
$mpdf->WriteHTML($html);
$mpdf->Output($inv['invoice_number'].'.pdf','D');
