<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
$db = Database::getInstance();
$cu = Auth::currentUser();

$booking_id = (int)($_GET['booking_id'] ?? 0);
$booking = null;
$prefill_items = [];

// Prefill from modal
$prefill_customer = (int)($_GET['prefill_customer'] ?? 0);
$prefill_valid    = trim($_GET['prefill_valid'] ?? '');
if ($booking_id) {
    $booking = $db->fetchOne(
        "SELECT b.*, c.name as customer_name, h.name as hall_name, p.name as package_name, p.price as package_price
         FROM bookings b LEFT JOIN customers c ON b.customer_id=c.id LEFT JOIN halls h ON b.hall_id=h.id LEFT JOIN packages p ON b.package_id=p.id
         WHERE b.id=?", [$booking_id]
    );
    if ($booking) {
        if ($booking['package_price']) $prefill_items[] = ['description'=>$booking['package_name'].' (Package)','quantity'=>1,'unit_price'=>$booking['package_price'],'tax_percent'=>0];
        $ba = $db->fetchAll("SELECT * FROM booking_addons WHERE booking_id=?", [$booking_id]);
        foreach ($ba as $a) $prefill_items[] = ['description'=>$a['name'],'quantity'=>$a['quantity'],'unit_price'=>$a['unit_price'],'tax_percent'=>$a['tax_percent']];
    }
}

$customers = $db->fetchAll("SELECT id,name,mobile as phone FROM customers ORDER BY name");
$branches  = $db->fetchAll("SELECT id,name FROM branches WHERE is_active=1");
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = (int)($_POST['customer_id'] ?? 0);
    $branch_id   = $cu['branch_id'] ?? (int)($_POST['branch_id'] ?? 1);
    $booking_id  = (int)($_POST['booking_id'] ?? 0) ?: null;
    $valid_until = trim($_POST['valid_until'] ?? '');
    $status      = $_POST['status'] ?? 'draft';
    $notes       = trim($_POST['notes'] ?? '');
    $terms       = trim($_POST['terms'] ?? '');
    $discount    = (float)($_POST['discount_amount'] ?? 0);
    $items       = $_POST['items'] ?? [];

    if (!$customer_id) $errors[] = 'Customer required.';
    if (!$items) $errors[] = 'At least one line item required.';

    if (!$errors) {
        $subtotal = 0; $tax_total = 0;
        foreach ($items as $it) {
            $qty = (float)($it['quantity']??1); $up = (float)($it['unit_price']??0); $tp = (float)($it['tax_percent']??0);
            $line = $qty * $up; $subtotal += $line; $tax_total += $line * $tp / 100;
        }
        $total = $subtotal + $tax_total - $discount;
        $ref = Helper::generateRef('QT','quotations','quotation_ref');

        $qid = $db->insert(
            "INSERT INTO quotations (quotation_ref,booking_id,customer_id,branch_id,valid_until,status,subtotal,discount_amount,tax_amount,total,notes,terms,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [$ref,$booking_id,$customer_id,$branch_id,$valid_until?:null,$status,$subtotal,$discount,$tax_total,$total,$notes,$terms,$cu['id']]
        );
        foreach ($items as $it) {
            $desc = trim($it['description']??''); if (!$desc) continue;
            $qty=(float)($it['quantity']??1); $up=(float)($it['unit_price']??0); $tp=(float)($it['tax_percent']??0);
            $db->insert("INSERT INTO quotation_items (quotation_id,description,quantity,unit_price,tax_percent,total) VALUES (?,?,?,?,?,?)", [$qid,$desc,$qty,$up,$tp,$qty*$up]);
        }
        Helper::flash('success',"Quotation $ref created.");
        Helper::redirect(BASE_URL.'/modules/quotations/view.php?id='.$qid);
    }
}

$def_terms = Helper::getSetting('quotation_terms') ?? "This quotation is valid for 7 days.\nPayment terms: 50% advance, 50% on event day.";

$pageTitle = 'New Quotation';
$breadcrumbs = [['label'=>'Quotations','url'=>BASE_URL.'/modules/quotations/index.php'],['label'=>'New']];
require_once ROOT_PATH . '/includes/header.php';
?>
<div class="vp-page-header d-print-none">
  <div class="d-flex align-items-center"><h1 class="vp-page-title">New Quotation</h1></div>
</div>
<form method="post">
  <?php if ($booking): ?><input type="hidden" name="booking_id" value="<?= $booking['id'] ?>"><?php endif; ?>
<div class="row">
  <div class="col-lg-8">
    <div class="card vp-card mb-3">
      <div class="card-header"><h3 class="card-title">Quotation Details</h3></div>
      <div class="card-body">
        <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e): ?><li><?= Helper::sanitize($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <?php if ($booking): ?>
        <div class="alert alert-info mb-3">Linked to booking: <strong><?= Helper::sanitize($booking['booking_ref'] ?? '') ?></strong> — <?= Helper::sanitize($booking['customer_name']) ?></div>
        <?php endif; ?>

        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label required">Customer</label>
            <select name="customer_id" class="form-select" required>
              <option value="">— Select —</option>
              <?php foreach ($customers as $c): ?>
              <option value="<?= $c['id'] ?>" <?= (($booking['customer_id']??$_POST['customer_id']??$prefill_customer)==$c['id'])?'selected':'' ?>><?= Helper::sanitize($c['name']) ?> (<?= Helper::sanitize($c['phone']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php if (Auth::isSuperAdmin()): ?>
          <div class="col-md-6">
            <label class="form-label">Branch</label>
            <select name="branch_id" class="form-select">
              <?php foreach ($branches as $b): ?><option value="<?= $b['id'] ?>" <?= ($cu['branch_id']??$b['id'])==$b['id']?'selected':'' ?>><?= Helper::sanitize($b['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>
        </div>

        <div class="row mb-3">
          <div class="col-md-4">
            <label class="form-label">Valid Until</label>
            <input type="date" name="valid_until" class="form-control" value="<?= $_POST['valid_until']??($prefill_valid?:date('Y-m-d',strtotime('+7 days'))) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <?php foreach (['draft','sent'] as $s): ?><option value="<?= $s ?>" <?= ($_POST['status']??'draft')===$s?'selected':'' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
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
            <?php
            $display_items = isset($_POST['items']) ? $_POST['items'] : ($prefill_items ?: [['description'=>'','quantity'=>1,'unit_price'=>0,'tax_percent'=>0]]);
            foreach ($display_items as $it): ?>
            <tr class="item-row">
              <td><input type="text" name="items[][description]" class="form-control form-control-sm item-desc" value="<?= Helper::sanitize($it['description']??'') ?>"></td>
              <td><input type="number" name="items[][quantity]" class="form-control form-control-sm item-qty" min="1" step="0.01" value="<?= $it['quantity']??1 ?>"></td>
              <td><input type="number" name="items[][unit_price]" class="form-control form-control-sm item-price" step="0.01" min="0" value="<?= $it['unit_price']??0 ?>"></td>
              <td><input type="number" name="items[][tax_percent]" class="form-control form-control-sm item-tax" step="0.01" min="0" max="100" value="<?= $it['tax_percent']??0 ?>"></td>
              <td class="item-total fw-bold align-middle">Rs. 0.00</td>
              <td><button type="button" class="btn btn-sm btn-ghost-danger remove-item">✕</button></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card vp-card mb-3">
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label">Notes</label>
          <textarea name="notes" class="form-control" rows="2"><?= Helper::sanitize($_POST['notes']??'') ?></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Terms & Conditions</label>
          <textarea name="terms" class="form-control" rows="3"><?= Helper::sanitize($_POST['terms']??$def_terms) ?></textarea>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card sticky-top" style="top:80px">
      <div class="card-header"><h3 class="card-title">Summary</h3></div>
      <div class="card-body">
        <div class="d-flex justify-content-between mb-2"><span>Subtotal</span><span id="sum-sub">Rs. 0.00</span></div>
        <div class="d-flex justify-content-between mb-2"><span>Tax</span><span id="sum-tax">Rs. 0.00</span></div>
        <div class="mb-2">
          <label class="form-label">Discount (Rs.)</label>
          <input type="number" name="discount_amount" id="discount_input" class="form-control" step="0.01" min="0" value="<?= $_POST['discount_amount']??0 ?>">
        </div>
        <hr>
        <div class="d-flex justify-content-between fw-bold fs-5"><span>Total</span><span id="sum-total">Rs. 0.00</span></div>
      </div>
      <div class="card-footer"><button type="submit" class="btn btn-primary w-100">Create Quotation</button></div>
    </div>
  </div>
</div>
</form>

<script>
const rowTpl = `<tr class="item-row">
  <td><input type="text" name="items[][description]" class="form-control form-control-sm item-desc"></td>
  <td><input type="number" name="items[][quantity]" class="form-control form-control-sm item-qty" min="1" step="0.01" value="1"></td>
  <td><input type="number" name="items[][unit_price]" class="form-control form-control-sm item-price" step="0.01" min="0" value="0"></td>
  <td><input type="number" name="items[][tax_percent]" class="form-control form-control-sm item-tax" step="0.01" min="0" max="100" value="0"></td>
  <td class="item-total fw-bold align-middle">Rs. 0.00</td>
  <td><button type="button" class="btn btn-sm btn-ghost-danger remove-item">✕</button></td>
</tr>`;

document.getElementById('add-item').addEventListener('click',()=>{
  document.getElementById('items-body').insertAdjacentHTML('beforeend',rowTpl);
  bindItemRow(document.querySelector('#items-body tr:last-child')); recalc();
});
document.querySelectorAll('.item-row').forEach(bindItemRow);
function bindItemRow(row){
  row.querySelectorAll('.item-qty,.item-price,.item-tax').forEach(el=>el.addEventListener('input',recalc));
  row.querySelector('.remove-item').addEventListener('click',function(){this.closest('tr').remove();recalc();});
}
document.getElementById('discount_input').addEventListener('input',recalc);
function recalc(){
  let sub=0,tax=0;
  document.querySelectorAll('.item-row').forEach(row=>{
    const qty=parseFloat(row.querySelector('.item-qty').value)||0;
    const price=parseFloat(row.querySelector('.item-price').value)||0;
    const taxp=parseFloat(row.querySelector('.item-tax').value)||0;
    const line=qty*price; sub+=line; tax+=line*taxp/100;
    row.querySelector('.item-total').textContent='Rs. '+line.toFixed(2);
  });
  const disc=parseFloat(document.getElementById('discount_input').value)||0;
  document.getElementById('sum-sub').textContent='Rs. '+sub.toFixed(2);
  document.getElementById('sum-tax').textContent='Rs. '+tax.toFixed(2);
  document.getElementById('sum-total').textContent='Rs. '+Math.max(0,sub+tax-disc).toFixed(2);
}
recalc();
</script>
<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
