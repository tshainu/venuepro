<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
$db = Database::getInstance();
$cu = Auth::currentUser();
$id = (int)($_GET['id'] ?? 0);

$q = $db->fetchOne(
    "SELECT q.*, c.name as customer_name, c.mobile as customer_phone, c.email as customer_email, c.address as customer_address,
            br.name as branch_name, br.address as branch_address, br.phone as branch_phone, br.email as branch_email,
            bk.booking_ref
     FROM quotations q
     LEFT JOIN customers c ON q.customer_id=c.id
     LEFT JOIN branches br ON q.branch_id=br.id
     LEFT JOIN bookings bk ON q.booking_id=bk.id
     WHERE q.id=?", [$id]
);
if (!$q) die('Quotation not found.');
if ($cu['branch_id'] && $q['branch_id'] != $cu['branch_id']) die('Access denied.');

$items = $db->fetchAll("SELECT * FROM quotation_items WHERE quotation_id=?", [$id]);
$app_name = Helper::getSetting('company_name') ?? APP_NAME;

// Build HTML
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  body { font-family: 'dejavusans'; font-size: 10pt; color: #333; }
  .header { background: #1a56a0; color: white; padding: 20px; margin-bottom: 20px; }
  .header h1 { margin: 0; font-size: 20pt; }
  .header p { margin: 0; font-size: 9pt; opacity: 0.85; }
  .section { margin-bottom: 15px; }
  .label { color: #666; font-size: 8pt; text-transform: uppercase; font-weight: bold; margin-bottom: 3px; }
  table.items { width: 100%; border-collapse: collapse; margin-top: 10px; }
  table.items thead th { background: #1a56a0; color: white; padding: 8px; font-size: 9pt; }
  table.items tbody td { padding: 7px 8px; border-bottom: 1px solid #eee; font-size: 9pt; }
  table.items tfoot td { padding: 6px 8px; font-size: 9pt; }
  .total-row { background: #f5f5f5; font-weight: bold; font-size: 11pt; }
  .text-right { text-align: right; }
  .badge { padding: 3px 8px; border-radius: 4px; font-size: 8pt; font-weight: bold; text-transform: uppercase; }
  .badge-draft { background: #ccc; }
  .badge-sent { background: #bde0fe; color: #1a56a0; }
  .badge-accepted { background: #d1fae5; color: #065f46; }
  .footer { margin-top: 30px; font-size: 8pt; color: #999; text-align: center; border-top: 1px solid #eee; padding-top: 10px; }
  .info-grid td { padding: 3px 10px 3px 0; font-size: 9pt; vertical-align: top; }
  .info-label { color: #666; width: 35%; }
</style>
</head>
<body>
<div class="header">
  <h1><?= htmlspecialchars($app_name) ?></h1>
  <p><?= htmlspecialchars($q['branch_name']) ?><?= $q['branch_address'] ? ' · '.$q['branch_address'] : '' ?><?= $q['branch_phone'] ? ' · '.$q['branch_phone'] : '' ?></p>
</div>

<table width="100%"><tr>
  <td width="50%" valign="top">
    <div class="label">Quotation To</div>
    <strong><?= htmlspecialchars($q['customer_name']) ?></strong><br>
    <?= htmlspecialchars($q['customer_phone']) ?><?= $q['customer_email'] ? '<br>'.htmlspecialchars($q['customer_email']) : '' ?>
    <?= $q['customer_address'] ? '<br>'.nl2br(htmlspecialchars($q['customer_address'])) : '' ?>
  </td>
  <td width="50%" valign="top" align="right">
    <div style="font-size:16pt; font-weight:bold; color:#1a56a0;"><?= htmlspecialchars($q['quotation_ref']) ?></div>
    <div style="margin-top:5px">
      Status: <span class="badge badge-<?= $q['status'] ?>"><?= strtoupper($q['status']) ?></span><br>
      <?= $q['valid_until'] ? 'Valid Until: <strong>'.date('d M Y',strtotime($q['valid_until'])).'</strong>' : '' ?><br>
      <?= $q['booking_ref'] ? 'Booking Ref: '.$q['booking_ref'] : '' ?>
    </div>
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
    <tr><td colspan="4" class="text-right">Subtotal</td><td class="text-right">Rs. <?= number_format($q['subtotal'],2) ?></td></tr>
    <tr><td colspan="4" class="text-right">Tax</td><td class="text-right">Rs. <?= number_format($q['tax_amount'],2) ?></td></tr>
    <?php if ($q['discount_amount']>0): ?><tr><td colspan="4" class="text-right" style="color:#c00">Discount</td><td class="text-right" style="color:#c00">- Rs. <?= number_format($q['discount_amount'],2) ?></td></tr><?php endif; ?>
    <tr class="total-row"><td colspan="4" class="text-right" style="font-size:12pt">TOTAL</td><td class="text-right" style="font-size:12pt">Rs. <?= number_format($q['total'],2) ?></td></tr>
  </tfoot>
</table>

<?php if ($q['notes']): ?>
<div class="section" style="margin-top:20px">
  <div class="label">Notes</div>
  <div style="font-size:9pt"><?= nl2br(htmlspecialchars($q['notes'])) ?></div>
</div>
<?php endif; ?>
<?php if ($q['terms']): ?>
<div class="section" style="margin-top:10px">
  <div class="label">Terms &amp; Conditions</div>
  <div style="font-size:9pt"><?= nl2br(htmlspecialchars($q['terms'])) ?></div>
</div>
<?php endif; ?>

<div class="footer">
  Generated by <?= htmlspecialchars($app_name) ?> · <?= date('d M Y, h:i A') ?>
</div>
</body>
</html>
<?php
$html = ob_get_clean();

$mpdf = new \Mpdf\Mpdf([
    'mode'        => 'utf-8',
    'format'      => 'A4',
    'margin_top'  => 5,
    'margin_left' => 10,
    'margin_right'=> 10,
    'margin_bottom'=> 10,
    'tempDir'     => ROOT_PATH . '/tmp/mpdf',
]);
$mpdf->SetTitle($q['quotation_ref']);
$mpdf->WriteHTML($html);
$mpdf->Output($q['quotation_ref'].'.pdf', 'D');
