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

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  body { font-family: 'dejavusans'; font-size: <?= (int)$inv_fsize ?>pt; color: #222; }
  .header { background: <?= htmlspecialchars($inv_color) ?>; color: white; padding: 18px 20px; margin-bottom: 20px; }
  .header h1 { margin: 0; font-size: 18pt; }
  .header p { margin: 2px 0 0; font-size: 9pt; opacity: 0.85; }
  .inv-title { font-size: 22pt; color: <?= htmlspecialchars($inv_color) ?>; font-weight: bold; }
  table.items { width: 100%; border-collapse: collapse; margin-top: 12px; }
  table.items thead th { background: <?= htmlspecialchars($inv_color) ?>; color: white; padding: 8px; font-size: 9pt; }
  table.items tbody tr:nth-child(even) { background: #f7f9fc; }
  table.items tbody td { padding: 7px 8px; border-bottom: 1px solid #e8ecf0; font-size: <?= (int)$inv_fsize ?>pt; }
  table.items tfoot td { padding: 6px 8px; border-top: 1px solid #ccc; font-size: <?= (int)$inv_fsize ?>pt; }
  .total-row { background: <?= htmlspecialchars($inv_color) ?>; color: white; font-weight: bold; font-size: <?= (int)$inv_fsize+2 ?>pt; }
  .text-right { text-align: right; }
  .paid-row { background: #d1fae5; color: #065f46; }
  .balance-row { background: #fee2e2; color: #991b1b; font-weight: bold; }
  .footer { margin-top: 25px; font-size: 8pt; color: #aaa; text-align: center; border-top: 1px solid #eee; padding-top: 8px; }
  .badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 8pt; font-weight: bold; text-transform: uppercase; }
  .stamp-paid { border: 3px solid #22c55e; color: #22c55e; padding: 4px 12px; font-size: 14pt; font-weight: bold; transform: rotate(-15deg); display: inline-block; border-radius: 4px; }
</style>
</head>
<body>
<div class="header">
  <h1><?= htmlspecialchars($app_name) ?></h1>
  <p><?= htmlspecialchars($inv['branch_name']) ?><?= $inv['branch_address'] ? ' · '.$inv['branch_address'] : '' ?><?= $inv['branch_phone'] ? ' · '.$inv['branch_phone'] : '' ?></p>
</div>

<table width="100%"><tr>
  <td width="50%" valign="top">
    <div style="color:#666;font-size:8pt;text-transform:uppercase;font-weight:bold;margin-bottom:4px">Bill To</div>
    <strong><?= htmlspecialchars($inv['customer_name']) ?></strong><br>
    <?= htmlspecialchars($inv['customer_phone']) ?>
    <?= $inv['customer_email'] ? '<br>'.htmlspecialchars($inv['customer_email']) : '' ?>
    <?= $inv['customer_address'] ? '<br>'.nl2br(htmlspecialchars($inv['customer_address'])) : '' ?>
  </td>
  <td width="50%" align="right" valign="top">
    <div class="inv-title">INVOICE</div>
    <div style="font-size:12pt;font-weight:bold;color:#1a3c6e;"><?= htmlspecialchars($inv['invoice_number']) ?></div>
    <div style="margin-top:6px;font-size:9pt;">
      <table align="right" style="font-size:9pt">
        <tr><td style="color:#666;padding-right:10px">Type:</td><td><strong><?= ucfirst($inv['invoice_type']) ?></strong></td></tr>
        <tr><td style="color:#666;padding-right:10px">Date:</td><td><?= date('d M Y',strtotime($inv['invoice_date'])) ?></td></tr>
        <?= $inv['due_date'] ? '<tr><td style="color:#666;padding-right:10px">Due:</td><td><strong>'.date('d M Y',strtotime($inv['due_date'])).'</strong></td></tr>' : '' ?>
        <?= $inv['booking_ref'] ? '<tr><td style="color:#666;padding-right:10px">Booking:</td><td>'.$inv['booking_ref'].'</td></tr>' : '' ?>
      </table>
    </div>
    <?php if ($inv['status']==='paid'): ?><div style="margin-top:10px"><span class="stamp-paid">PAID</span></div><?php endif; ?>
  </td>
</tr></table>

<table class="items" style="margin-top:20px">
  <thead>
    <tr>
      <th>Description</th>
      <th class="text-right" width="60">Qty</th>
      <th class="text-right" width="100">Unit Price</th>
      <th class="text-right" width="60">Tax %</th>
      <th class="text-right" width="110">Total</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($items as $it): ?>
    <tr>
      <td><?= htmlspecialchars($it['description']) ?></td>
      <td class="text-right"><?= $it['quantity'] ?></td>
      <td class="text-right">Rs. <?= number_format($it['unit_price'],2) ?></td>
      <td class="text-right"><?= number_format($it['tax_percent'],1) ?>%</td>
      <td class="text-right">Rs. <?= number_format($it['total'],2) ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr><td colspan="4" class="text-right">Subtotal</td><td class="text-right">Rs. <?= number_format($inv['subtotal'],2) ?></td></tr>
    <tr><td colspan="4" class="text-right">Tax</td><td class="text-right">Rs. <?= number_format($inv['tax_amount'],2) ?></td></tr>
    <?php if ($inv['discount_amount']>0): ?><tr><td colspan="4" class="text-right" style="color:#c00">Discount</td><td class="text-right" style="color:#c00">- Rs. <?= number_format($inv['discount_amount'],2) ?></td></tr><?php endif; ?>
    <tr class="total-row"><td colspan="4" class="text-right" style="font-size:11pt">TOTAL</td><td class="text-right" style="font-size:11pt">Rs. <?= number_format($inv['total'],2) ?></td></tr>
    <?php if ($inv['paid_amount']>0): ?><tr class="paid-row"><td colspan="4" class="text-right">Paid</td><td class="text-right">Rs. <?= number_format($inv['paid_amount'],2) ?></td></tr><?php endif; ?>
    <?php if ($inv['balance']>0): ?><tr class="balance-row"><td colspan="4" class="text-right">BALANCE DUE</td><td class="text-right">Rs. <?= number_format($inv['balance'],2) ?></td></tr><?php endif; ?>
  </tfoot>
</table>

<?php if ($inv['notes']): ?>
<div style="margin-top:15px"><div style="color:#666;font-size:8pt;text-transform:uppercase;font-weight:bold;margin-bottom:3px">Notes</div><div style="font-size:9pt"><?= nl2br(htmlspecialchars($inv['notes'])) ?></div></div>
<?php endif; ?>
<?php if ($inv['terms']): ?>
<div style="margin-top:10px"><div style="color:#666;font-size:8pt;text-transform:uppercase;font-weight:bold;margin-bottom:3px">Payment Terms</div><div style="font-size:9pt"><?= nl2br(htmlspecialchars($inv['terms'])) ?></div></div>
<?php endif; ?>

<div class="footer">Generated by <?= htmlspecialchars($app_name) ?> · <?= date('d M Y, h:i A') ?></div>
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
    $mpdf_format  = [80, 200];   // narrow receipt
    $orient       = 'P';
    $inv_margin_left  = max(3, $inv_margin_left);
    $inv_margin_right = max(3, $inv_margin_right);
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
