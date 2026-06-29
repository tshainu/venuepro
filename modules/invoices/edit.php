<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
$db = Database::getInstance();
$cu = Auth::currentUser();

$id = (int)($_GET['id'] ?? 0);
$inv = $db->fetchOne(
    "SELECT i.*, b.booking_ref, c.name as customer_name
     FROM invoices i
     LEFT JOIN bookings b ON i.booking_id = b.id
     LEFT JOIN customers c ON i.customer_id = c.id
     WHERE i.id = ?", [$id]
);
if (!$inv) { Helper::flash('error','Invoice not found.'); Helper::redirect(BASE_URL.'/modules/invoices/index.php'); }
if ($cu['branch_id'] && $inv['branch_id'] != $cu['branch_id']) { Helper::flash('error','Access denied.'); Helper::redirect(BASE_URL.'/modules/invoices/index.php'); }

$existing_items = $db->fetchAll("SELECT * FROM invoice_items WHERE invoice_id=? ORDER BY id", [$id]);
$customers = $db->fetchAll("SELECT id,name,mobile as phone FROM customers ORDER BY name");
$branches  = $db->fetchAll("SELECT id,name FROM branches WHERE is_active=1");
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Capture old state before any changes
    $old_items = $db->fetchAll("SELECT description,quantity,unit_price,tax_percent FROM invoice_items WHERE invoice_id=? ORDER BY id", [$id]);
    $old_snapshot = [
        'customer_id'     => $inv['customer_id'],
        'invoice_type'    => $inv['invoice_type'],
        'invoice_date'    => $inv['invoice_date'],
        'due_date'        => $inv['due_date'],
        'status'          => $inv['status'],
        'subtotal'        => $inv['subtotal'],
        'discount_amount' => $inv['discount_amount'],
        'tax_amount'      => $inv['tax_amount'],
        'total'           => $inv['total'],
        'notes'           => $inv['notes'],
        'items'           => $old_items,
    ];

    $customer_id  = (int)($_POST['customer_id'] ?? 0);
    $branch_id    = $cu['branch_id'] ?? (int)($_POST['branch_id'] ?? $inv['branch_id']);
    $invoice_type = $_POST['invoice_type'] ?? $inv['invoice_type'];
    $invoice_date = trim($_POST['invoice_date'] ?? '');
    $due_date     = trim($_POST['due_date'] ?? '') ?: null;
    $status       = $_POST['status'] ?? $inv['status'];
    $notes        = trim($_POST['notes'] ?? '');
    $terms        = trim($_POST['terms'] ?? '');
    $discount     = (float)($_POST['discount_amount'] ?? 0);
    $items        = $_POST['items'] ?? [];

    if (!$customer_id)  $errors[] = 'Customer required.';
    if (!$invoice_date) $errors[] = 'Invoice date required.';
    if (!$items)        $errors[] = 'At least one line item required.';

    if (!$errors) {
        $subtotal = 0; $tax_total = 0;
        foreach ($items as $it) {
            $qty=(float)($it['quantity']??1); $up=(float)($it['unit_price']??0); $tp=(float)($it['tax_percent']??0);
            $line=$qty*$up; $subtotal+=$line; $tax_total+=$line*$tp/100;
        }
        $total = $subtotal + $tax_total - $discount;
        $balance = max(0, $total - (float)$inv['paid_amount']);

        $db->execute(
            "UPDATE invoices SET customer_id=?,branch_id=?,invoice_type=?,invoice_date=?,due_date=?,status=?,subtotal=?,discount_amount=?,tax_amount=?,total=?,balance=?,notes=?,terms=? WHERE id=?",
            [$customer_id,$branch_id,$invoice_type,$invoice_date,$due_date,$status,$subtotal,$discount,$tax_total,$total,$balance,$notes,$terms,$id]
        );

        // Replace line items
        $db->execute("DELETE FROM invoice_items WHERE invoice_id=?", [$id]);
        foreach ($items as $it) {
            $desc=trim($it['description']??''); if(!$desc) continue;
            $qty=(float)($it['quantity']??1); $up=(float)($it['unit_price']??0); $tp=(float)($it['tax_percent']??0);
            $db->insert("INSERT INTO invoice_items (invoice_id,description,quantity,unit_price,tax_percent,total) VALUES (?,?,?,?,?,?)", [$id,$desc,$qty,$up,$tp,$qty*$up]);
        }

        // Sync booking financials
        if ($inv['booking_id']) {
            $inv_totals = $db->fetchOne(
                "SELECT COALESCE(SUM(total),0) as total_sum, COALESCE(SUM(tax_amount),0) as tax_sum, COALESCE(SUM(discount_amount),0) as disc_sum
                 FROM invoices WHERE booking_id=? AND status NOT IN ('cancelled')",
                [$inv['booking_id']]
            );
            $db->execute(
                "UPDATE bookings SET total_amount=?, tax_amount=?, discount_amount=?, final_amount=?, balance_amount=final_amount - paid_amount WHERE id=?",
                [$inv_totals['total_sum'], $inv_totals['tax_sum'], $inv_totals['disc_sum'], $inv_totals['total_sum'], $inv['booking_id']]
            );
            $db->execute("UPDATE bookings SET balance_amount = final_amount - paid_amount WHERE id=?", [$inv['booking_id']]);
        }

        Logger::log('edit', 'invoices', $id, $inv['invoice_number'], $old_snapshot, [
            'customer_id'     => $customer_id,
            'invoice_type'    => $invoice_type,
            'invoice_date'    => $invoice_date,
            'due_date'        => $due_date,
            'status'          => $status,
            'subtotal'        => $subtotal,
            'discount_amount' => $discount,
            'tax_amount'      => $tax_total,
            'total'           => $total,
            'notes'           => $notes,
        ], "Edited invoice {$inv['invoice_number']}");

        Helper::flash('success','Invoice updated.');
        // Redirect back to booking if came from there
        $back = $_POST['back_url'] ?? '';
        Helper::redirect($back ?: BASE_URL.'/modules/invoices/view.php?id='.$id);
    }
} else {
    // Seed POST from existing invoice for display
    $_POST = array_merge($_POST, $inv);
}

$def_terms = Helper::getSetting('invoice_terms') ?? "Payment due within 7 days of invoice date.\nBank: Peoples Bank | A/C: 0012345678 | VenuePro Lanka";
$display_items = isset($_POST['items']) ? $_POST['items'] : $existing_items;

$pageTitle = 'Edit Invoice';
$back_url  = $_GET['back'] ?? (BASE_URL.'/modules/invoices/view.php?id='.$id);
$breadcrumbs = [
    ['label'=>'Invoices','url'=>BASE_URL.'/modules/invoices/index.php'],
    ['label'=>$inv['invoice_number'],'url'=>BASE_URL.'/modules/invoices/view.php?id='.$id],
    ['label'=>'Edit']
];
if ($inv['booking_id']) {
    array_unshift($breadcrumbs, ['label'=>'Bookings','url'=>BASE_URL.'/modules/bookings/index.php']);
    array_splice($breadcrumbs, 1, 0, [['label'=>$inv['booking_ref'],'url'=>BASE_URL.'/modules/bookings/view.php?id='.$inv['booking_id']]]);
}
require_once ROOT_PATH . '/includes/header.php';
?>
<div class="vp-page-header d-print-none">
  <div class="d-flex align-items-center justify-content-between">
    <h1 class="vp-page-title">Edit Invoice: <?= Helper::sanitize($inv['invoice_number']) ?></h1>
    <a href="<?= htmlspecialchars($back_url) ?>" class="btn btn-vp-outline btn-sm">← Back</a>
  </div>
</div>

<form method="post">
  <input type="hidden" name="back_url" value="<?= htmlspecialchars($back_url) ?>">
  <?php if ($inv['booking_id']): ?>
  <input type="hidden" name="booking_id" value="<?= $inv['booking_id'] ?>">
  <?php endif; ?>

  <div class="row">
    <div class="col-lg-8">
      <div class="card vp-card mb-3">
        <div class="card-header"><h3 class="card-title">Invoice Details</h3></div>
        <div class="card-body">
          <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e): ?><li><?= Helper::sanitize($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
          <?php if ($inv['booking_id']): ?>
          <div class="alert alert-info mb-3">Booking: <strong><?= Helper::sanitize($inv['booking_ref']) ?></strong></div>
          <?php endif; ?>

          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label required">Customer</label>
              <select name="customer_id" class="form-select" required>
                <option value="">— Select —</option>
                <?php foreach ($customers as $c): ?>
                <option value="<?= $c['id'] ?>" <?= (($_POST['customer_id']??$inv['customer_id'])==$c['id'])?'selected':'' ?>><?= Helper::sanitize($c['name']) ?> (<?= Helper::sanitize($c['phone']) ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php if (Auth::isSuperAdmin()): ?>
            <div class="col-md-6">
              <label class="form-label">Branch</label>
              <select name="branch_id" class="form-select">
                <?php foreach ($branches as $b): ?><option value="<?= $b['id'] ?>" <?= ($inv['branch_id']==$b['id'])?'selected':'' ?>><?= Helper::sanitize($b['name']) ?></option><?php endforeach; ?>
              </select>
            </div>
            <?php endif; ?>
          </div>

          <div class="row mb-3">
            <div class="col-md-3">
              <label class="form-label">Type</label>
              <select name="invoice_type" class="form-select">
                <?php foreach (['advance','interim','final'] as $t): ?>
                <option value="<?= $t ?>" <?= (($_POST['invoice_type']??$inv['invoice_type'])===$t)?'selected':'' ?>><?= ucfirst($t) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label required">Invoice Date</label>
              <input type="date" name="invoice_date" class="form-control" value="<?= $_POST['invoice_date']??$inv['invoice_date'] ?>" required>
            </div>
            <div class="col-md-3">
              <label class="form-label">Due Date</label>
              <input type="date" name="due_date" class="form-control" value="<?= $_POST['due_date']??$inv['due_date'] ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Status</label>
              <select name="status" class="form-select">
                <?php foreach (['draft','sent','paid','partial','overdue','cancelled'] as $s): ?>
                <option value="<?= $s ?>" <?= (($_POST['status']??$inv['status'])===$s)?'selected':'' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>
      </div>

      <!-- Line Items -->
      <div class="card vp-card mb-3">
        <div class="card-header d-flex align-items-center justify-content-between">
          <h3 class="card-title mb-0">Line Items</h3>
          <button type="button" class="btn btn-sm btn-outline-primary" id="add-item">+ Add Line</button>
        </div>
        <div class="card-body p-0">
          <table class="table table-sm mb-0">
            <thead><tr><th>Description</th><th style="width:80px">Qty</th><th style="width:120px">Unit Price</th><th style="width:80px">Tax %</th><th style="width:110px">Total</th><th></th></tr></thead>
            <tbody id="items-body">
              <?php foreach ($display_items as $it): ?>
              <tr class="item-row">
                <td><input type="text" name="items[][description]" class="form-control form-control-sm item-desc" value="<?= Helper::sanitize($it['description']??'') ?>"></td>
                <td><input type="number" name="items[][quantity]" class="form-control form-control-sm item-qty" min="1" step="0.01" value="<?= $it['quantity']??1 ?>"></td>
                <td><input type="number" name="items[][unit_price]" class="form-control form-control-sm item-price" step="0.01" min="0" value="<?= $it['unit_price']??0 ?>"></td>
                <td><input type="number" name="items[][tax_percent]" class="form-control form-control-sm item-tax" step="0.01" min="0" max="100" value="<?= $it['tax_percent']??0 ?>"></td>
                <td class="item-total fw-bold align-middle"><?= Helper::formatCurrency(($it['quantity']??1)*($it['unit_price']??0)) ?></td>
                <td><button type="button" class="btn btn-sm btn-ghost-danger remove-item">✕</button></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="card vp-card mb-3">
        <div class="card-body">
          <div class="mb-3"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"><?= Helper::sanitize($_POST['notes']??$inv['notes']) ?></textarea></div>
          <div class="mb-3"><label class="form-label">Terms &amp; Conditions</label><textarea name="terms" class="form-control" rows="3"><?= Helper::sanitize($_POST['terms']??$inv['terms']??$def_terms) ?></textarea></div>
        </div>
      </div>
    </div>

    <!-- Summary sidebar -->
    <div class="col-lg-4">
      <div class="card sticky-top" style="top:80px">
        <div class="card-header"><h3 class="card-title">Summary</h3></div>
        <div class="card-body">
          <?php if ($inv['paid_amount'] > 0): ?>
          <div class="alert alert-warning py-2 mb-3" style="font-size:.8rem;">
            <strong>Rs. <?= number_format($inv['paid_amount'],2) ?></strong> already paid — only balance will change.
          </div>
          <?php endif; ?>
          <div class="d-flex justify-content-between mb-2"><span>Subtotal</span><span id="sum-sub">Rs. 0.00</span></div>
          <div class="d-flex justify-content-between mb-2"><span>Tax</span><span id="sum-tax">Rs. 0.00</span></div>
          <div class="mb-2">
            <label class="form-label">Discount</label>
            <input type="number" name="discount_amount" id="discount_input" class="form-control" step="0.01" min="0" value="<?= $_POST['discount_amount']??$inv['discount_amount'] ?>">
          </div>
          <hr>
          <div class="d-flex justify-content-between fw-bold fs-5"><span>Total</span><span id="sum-total">Rs. 0.00</span></div>
        </div>
        <div class="card-footer d-flex gap-2">
          <button type="submit" class="btn btn-primary w-100">Save Changes</button>
          <a href="<?= htmlspecialchars($back_url) ?>" class="btn btn-vp-outline">Cancel</a>
        </div>
      </div>
    </div>
  </div>
</form>

<script>
const rowTpl=`<tr class="item-row"><td><input type="text" name="items[][description]" class="form-control form-control-sm item-desc"></td><td><input type="number" name="items[][quantity]" class="form-control form-control-sm item-qty" min="1" step="0.01" value="1"></td><td><input type="number" name="items[][unit_price]" class="form-control form-control-sm item-price" step="0.01" min="0" value="0"></td><td><input type="number" name="items[][tax_percent]" class="form-control form-control-sm item-tax" step="0.01" min="0" max="100" value="0"></td><td class="item-total fw-bold align-middle">Rs. 0.00</td><td><button type="button" class="btn btn-sm btn-ghost-danger remove-item">✕</button></td></tr>`;
document.getElementById('add-item').addEventListener('click',()=>{document.getElementById('items-body').insertAdjacentHTML('beforeend',rowTpl);bindRow(document.querySelector('#items-body tr:last-child'));recalc();});
document.querySelectorAll('.item-row').forEach(bindRow);
function bindRow(r){r.querySelectorAll('.item-qty,.item-price,.item-tax').forEach(el=>el.addEventListener('input',recalc));r.querySelector('.remove-item').addEventListener('click',function(){this.closest('tr').remove();recalc();});}
document.getElementById('discount_input').addEventListener('input',recalc);
function recalc(){let sub=0,tax=0;document.querySelectorAll('.item-row').forEach(r=>{const qty=parseFloat(r.querySelector('.item-qty').value)||0,price=parseFloat(r.querySelector('.item-price').value)||0,taxp=parseFloat(r.querySelector('.item-tax').value)||0,line=qty*price;sub+=line;tax+=line*taxp/100;r.querySelector('.item-total').textContent='Rs. '+line.toFixed(2);});const disc=parseFloat(document.getElementById('discount_input').value)||0;document.getElementById('sum-sub').textContent='Rs. '+sub.toFixed(2);document.getElementById('sum-tax').textContent='Rs. '+tax.toFixed(2);document.getElementById('sum-total').textContent='Rs. '+Math.max(0,sub+tax-disc).toFixed(2);}
recalc();
</script>
<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
