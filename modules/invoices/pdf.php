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
$app_name  = Helper::getSetting('company_name') ?? APP_NAME;
$inv_color = Helper::getSetting('invoice_color', $inv['branch_id']) ?? '#1a3c6e';
$inv_fsize = Helper::getSetting('invoice_font_size', $inv['branch_id']) ?? '10';
$inv_logo  = Helper::getSetting('company_logo', $inv['branch_id']);
$inv_show_logo = Helper::getSetting('invoice_show_logo', $inv['branch_id']) ?? '1';

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; }
  body { font-family: 'dejavusans'; font-size: <?= (int)$inv_fsize ?>pt; color: #222; line-height: 1.4; }
  
  /* HEADER SECTION */
  .header { 
    background: linear-gradient(135deg, <?= htmlspecialchars($inv_color) ?> 0%, #0d2f5a 100%);
    color: white; 
    padding: 20px; 
    margin: -20px -10px 0 -10px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    border-bottom: 3px solid <?= htmlspecialchars($inv_color) ?>;
  }
  .header-left { flex: 1; display: flex; gap: 15px; align-items: flex-start; }
  .logo { height: 70px; }
  .logo img { max-height: 70px; width: auto; }
  .company-info h1 { margin: 0; font-size: 16pt; font-weight: bold; }
  .company-info p { margin: 2px 0 0 0; font-size: 8pt; opacity: 0.9; }
  
  .header-right { text-align: right; }
  .inv-title { font-size: 24pt; font-weight: bold; margin-bottom: 8px; }
  .inv-details { font-size: 8pt; line-height: 1.6; }
  .inv-details strong { display: block; font-weight: bold; }
  
  /* MAIN CONTENT */
  .content { padding: 20px 0; }
  
  /* BILL TO & EVENT LOCATION */
  .bill-event { display: flex; gap: 30px; margin-bottom: 20px; width: 100%; }
  .bill-event > div { flex: 1; }
  .section-title { 
    font-size: 9pt; 
    font-weight: bold; 
    color: #333; 
    text-transform: uppercase; 
    margin-bottom: 6px; 
    border-bottom: 1px solid #ddd;
    padding-bottom: 3px;
  }
  .section-content { font-size: 9pt; line-height: 1.5; color: #555; }
  .section-content strong { display: block; font-weight: bold; color: #222; margin-bottom: 3px; }
  
  /* ITEMS TABLE */
  table.items { 
    width: 100%; 
    border-collapse: collapse; 
    margin: 20px 0;
    border: 1px solid #ddd;
  }
  table.items thead { background: <?= htmlspecialchars($inv_color) ?>; color: white; }
  table.items th { 
    padding: 10px 8px; 
    text-align: left; 
    font-size: 8pt; 
    font-weight: bold;
    border: 1px solid #bbb;
  }
  table.items th.text-right { text-align: right; }
  table.items td { 
    padding: 8px; 
    border: 1px solid #eee;
    font-size: <?= (int)$inv_fsize ?>pt;
  }
  table.items tbody tr:nth-child(odd) { background: #f9f9f9; }
  table.items tbody tr:nth-child(even) { background: #fff; }
  table.items td.text-right { text-align: right; }
  table.items td.text-center { text-align: center; }
  
  /* SUMMARY SECTION */
  .summary { 
    width: 100%; 
    margin: 20px 0; 
    display: flex;
    justify-content: flex-end;
  }
  .summary-box { width: 280px; }
  .summary-row { 
    display: flex; 
    justify-content: space-between; 
    padding: 6px 12px;
    border-bottom: 1px solid #ddd;
    font-size: 9pt;
  }
  .summary-row.total { 
    background: <?= htmlspecialchars($inv_color) ?>; 
    color: white; 
    font-weight: bold;
    font-size: 11pt;
    padding: 10px 12px;
    border: none;
  }
  .summary-row.balance { 
    background: #fee2e2; 
    color: #991b1b; 
    font-weight: bold;
    padding: 10px 12px;
  }
  .summary-row.paid { 
    background: #d1fae5; 
    color: #065f46;
    padding: 10px 12px;
  }
  
  /* NOTES & TERMS */
  .notes-terms { margin: 20px 0; }
  .notes-terms-box { 
    margin-bottom: 15px; 
    padding: 12px;
    background: #f9f9f9;
    border-left: 3px solid <?= htmlspecialchars($inv_color) ?>;
    font-size: 9pt;
  }
  .notes-terms-box-title { 
    font-weight: bold; 
    text-transform: uppercase;
    color: #333;
    margin-bottom: 5px;
    font-size: 8pt;
  }
  .notes-terms-box-content { color: #555; }
  
  /* PAYMENT INFO & SIGNATURE */
  .footer-section { 
    margin-top: 25px;
    display: flex;
    gap: 30px;
    font-size: 8pt;
  }
  .footer-box { flex: 1; }
  .footer-box-title { 
    font-weight: bold; 
    text-transform: uppercase;
    color: #333;
    margin-bottom: 8px;
    font-size: 8pt;
  }
  .footer-box-content { 
    color: #555; 
    line-height: 1.6;
    font-size: 8pt;
  }
  .signature-space { 
    border-top: 1px solid #999;
    margin-top: 30px;
    padding-top: 5px;
    text-align: right;
    font-size: 8pt;
    color: #666;
  }
  
  /* PAGE FOOTER */
  .page-footer { 
    margin-top: 30px; 
    padding-top: 15px;
    border-top: 1px solid #ddd;
    text-align: center; 
    font-size: 7pt; 
    color: #999;
  }
  
  .stamp-paid { 
    border: 3px solid #22c55e; 
    color: #22c55e; 
    padding: 4px 12px; 
    font-size: 14pt; 
    font-weight: bold; 
    transform: rotate(-15deg); 
    display: inline-block; 
    border-radius: 4px; 
  }
</style>
</head>
<body>

<!-- HEADER -->
<div class="header">
  <div class="header-left">
    <?php if ($inv_show_logo === '1' && $inv_logo && file_exists(ROOT_PATH.'/uploads/'.$inv_logo)): ?>
      <div class="logo">
        <img src="<?= ROOT_PATH.'/uploads/'.$inv_logo ?>" alt="Logo">
      </div>
    <?php endif; ?>
    <div class="company-info">
      <h1><?= htmlspecialchars($app_name) ?></h1>
      <p><?= htmlspecialchars($inv['branch_name']) ?></p>
      <?php if ($inv['branch_address']): ?><p><?= htmlspecialchars($inv['branch_address']) ?></p><?php endif; ?>
      <?php if ($inv['branch_phone']): ?><p><?= htmlspecialchars($inv['branch_phone']) ?></p><?php endif; ?>
      <?php if ($inv['branch_email']): ?><p><?= htmlspecialchars($inv['branch_email']) ?></p><?php endif; ?>
    </div>
  </div>
  
  <div class="header-right">
    <div class="inv-title">INVOICE</div>
    <div class="inv-details">
      <strong><?= htmlspecialchars($inv['invoice_number']) ?></strong>
      <div style="margin-top: 8px; font-size: 8pt;">
        <div>Invoice Date: <?= date('d M Y', strtotime($inv['invoice_date'])) ?></div>
        <?php if ($inv['due_date']): ?><div>Due Date: <?= date('d M Y', strtotime($inv['due_date'])) ?></div><?php endif; ?>
        <?php if ($inv['booking_ref']): ?><div>Booking Ref: <?= htmlspecialchars($inv['booking_ref']) ?></div><?php endif; ?>
      </div>
    </div>
    <?php if ($inv['status']==='paid'): ?><div style="margin-top: 15px;"><span class="stamp-paid">PAID</span></div><?php endif; ?>
  </div>
</div>

<!-- CONTENT -->
<div class="content">

<!-- BILL TO & EVENT LOCATION -->
<div class="bill-event">
  <div>
    <div class="section-title">Bill To</div>
    <div class="section-content">
      <strong><?= htmlspecialchars($inv['customer_name']) ?></strong>
      <?php if ($inv['customer_phone']): ?><div><?= htmlspecialchars($inv['customer_phone']) ?></div><?php endif; ?>
      <?php if ($inv['customer_email']): ?><div><?= htmlspecialchars($inv['customer_email']) ?></div><?php endif; ?>
      <?php if ($inv['customer_address']): ?><div><?= nl2br(htmlspecialchars($inv['customer_address'])) ?></div><?php endif; ?>
    </div>
  </div>
  
  <div>
    <div class="section-title">Booking Reference</div>
    <div class="section-content">
      <?php if ($inv['booking_ref']): ?>
        <strong><?= htmlspecialchars($inv['booking_ref']) ?></strong>
      <?php else: ?>
        <span style="color: #999;">—</span>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ITEMS TABLE -->
<table class="items">
  <thead>
    <tr>
      <th width="5%" class="text-center">#</th>
      <th>Description</th>
      <th width="12%" class="text-right">Qty</th>
      <th width="15%" class="text-right">Unit Price</th>
      <th width="10%" class="text-right">Tax %</th>
      <th width="15%" class="text-right">Amount</th>
    </tr>
  </thead>
  <tbody>
    <?php $line_no = 1; foreach ($items as $it): ?>
    <tr>
      <td class="text-center"><?= $line_no ?></td>
      <td><?= htmlspecialchars($it['description']) ?></td>
      <td class="text-right"><?= (int)$it['quantity'] ?></td>
      <td class="text-right">Rs. <?= number_format($it['unit_price'], 2) ?></td>
      <td class="text-right"><?= number_format($it['tax_percent'], 1) ?>%</td>
      <td class="text-right">Rs. <?= number_format($it['total'], 2) ?></td>
    </tr>
    <?php $line_no++; endforeach; ?>
  </tbody>
</table>

<!-- SUMMARY SECTION -->
<div class="summary">
  <div class="summary-box">
    <div class="summary-row">
      <span>Subtotal</span>
      <span>Rs. <?= number_format($inv['subtotal'], 2) ?></span>
    </div>
    <div class="summary-row">
      <span>Tax (VAT/NBT)</span>
      <span>Rs. <?= number_format($inv['tax_amount'], 2) ?></span>
    </div>
    <?php if ($inv['discount_amount'] > 0): ?>
    <div class="summary-row" style="color: #c00;">
      <span>Discount</span>
      <span>- Rs. <?= number_format($inv['discount_amount'], 2) ?></span>
    </div>
    <?php endif; ?>
    <div class="summary-row total">
      <span>TOTAL DUE</span>
      <span>Rs. <?= number_format($inv['total'], 2) ?></span>
    </div>
    <?php if ($inv['paid_amount'] > 0): ?>
    <div class="summary-row paid">
      <span>Paid</span>
      <span>Rs. <?= number_format($inv['paid_amount'], 2) ?></span>
    </div>
    <?php endif; ?>
    <?php if ($inv['balance'] > 0): ?>
    <div class="summary-row balance">
      <span>BALANCE DUE</span>
      <span>Rs. <?= number_format($inv['balance'], 2) ?></span>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- NOTES & PAYMENT TERMS -->
<?php if ($inv['notes'] || $inv['terms']): ?>
<div class="notes-terms">
  <?php if ($inv['notes']): ?>
  <div class="notes-terms-box">
    <div class="notes-terms-box-title">Notes</div>
    <div class="notes-terms-box-content"><?= nl2br(htmlspecialchars($inv['notes'])) ?></div>
  </div>
  <?php endif; ?>
  
  <?php if ($inv['terms']): ?>
  <div class="notes-terms-box">
    <div class="notes-terms-box-title">Payment Terms</div>
    <div class="notes-terms-box-content"><?= nl2br(htmlspecialchars($inv['terms'])) ?></div>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- PAYMENT INFO & SIGNATURE -->
<div class="footer-section">
  <div class="footer-box">
    <div class="footer-box-title">Payment Information</div>
    <div class="footer-box-content">
      <?php if ($inv['branch_email']): ?>
        <div><strong>Email:</strong> <?= htmlspecialchars($inv['branch_email']) ?></div>
      <?php endif; ?>
      <?php if ($inv['branch_phone']): ?>
        <div><strong>Phone:</strong> <?= htmlspecialchars($inv['branch_phone']) ?></div>
      <?php endif; ?>
    </div>
  </div>
  
  <div class="footer-box">
    <div class="footer-box-title">Authorized Signature</div>
    <div class="signature-space">_____________________<br><small>Name & Date</small></div>
  </div>
</div>

</div><!-- /.content -->

<!-- PAGE FOOTER -->
<div class="page-footer">
  Thank you for choosing our services! | Generated by <?= htmlspecialchars($app_name) ?> on <?= date('d M Y, h:i A') ?>
</div>

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
