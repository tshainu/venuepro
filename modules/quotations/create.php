<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
$db = Database::getInstance();
$cu = Auth::currentUser();

$booking_id = (int)($_GET['booking_id'] ?? 0);
$booking    = null;
$prefill_items = [];

// Prefill from modal or GET params
$prefill_customer = (int)($_GET['prefill_customer'] ?? 0);
$prefill_valid    = trim($_GET['prefill_valid'] ?? '');

if ($booking_id) {
    $booking = $db->fetchOne(
        "SELECT b.*, c.name as customer_name, h.name as hall_name, p.name as package_name, p.price as package_price
         FROM bookings b LEFT JOIN customers c ON b.customer_id=c.id LEFT JOIN halls h ON b.hall_id=h.id LEFT JOIN packages p ON b.package_id=p.id
         WHERE b.id=?", [$booking_id]
    );
    if ($booking) {
        $prefill_customer = $prefill_customer ?: $booking['customer_id'];
        if ($booking['package_price']) $prefill_items[] = ['description'=>$booking['package_name'].' (Package)','quantity'=>1,'unit_price'=>$booking['package_price'],'tax_percent'=>0];
        $ba = $db->fetchAll("SELECT * FROM booking_addons WHERE booking_id=?", [$booking_id]);
        foreach ($ba as $a) $prefill_items[] = ['description'=>$a['name'],'quantity'=>$a['quantity'],'unit_price'=>$a['unit_price'],'tax_percent'=>$a['tax_percent']];
    }
}

$customers = $db->fetchAll("SELECT id,name,mobile as phone FROM customers ORDER BY name");
$branches  = $db->fetchAll("SELECT id,name FROM branches WHERE is_active=1");
$errors    = [];

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
    if (!$items)       $errors[] = 'At least one line item required.';

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

$pageTitle   = 'New Quotation';
$breadcrumbs = [['label'=>'Quotations','url'=>BASE_URL.'/modules/quotations/index.php'],['label'=>'New']];
require_once ROOT_PATH . '/includes/header.php';

// Calculate initial amounts for display
$init_sub = 0; $init_tax = 0;
$display_items = isset($_POST['items']) ? $_POST['items'] : ($prefill_items ?: []);
foreach ($display_items as $it) {
    $qty=(float)($it['quantity']??1); $up=(float)($it['unit_price']??0); $tp=(float)($it['tax_percent']??0);
    $line=$qty*$up; $init_sub+=$line; $init_tax+=$line*$tp/100;
}
$init_disc = (float)($_POST['discount_amount']??0);
$init_total = $init_sub + $init_tax - $init_disc;
?>
<!-- Tom Select -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>

<style>
/* ══ Quotation Builder ═══════════════════════════════════ */
.qt-layout { display:grid; grid-template-columns:1fr 320px; gap:1.5rem; align-items:start; }
@media(max-width:900px){ .qt-layout { grid-template-columns:1fr; } }

/* Header panel */
.qt-hero {
  background:linear-gradient(130deg,#08111f 0%,#0f1f40 50%,#162d5a 100%);
  border-radius:18px; padding:1.6rem 2rem; margin-bottom:1.5rem;
  border:1px solid rgba(201,168,76,.18); box-shadow:0 12px 40px rgba(8,17,31,.25);
  display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap;
}
.qt-hero-title { color:#fff; font-size:1.4rem; font-weight:900; letter-spacing:-.04em; }
.qt-hero-sub   { color:rgba(255,255,255,.45); font-size:.8rem; margin-top:.2rem; }
.qt-ref-badge  { background:rgba(201,168,76,.15); border:1px solid rgba(201,168,76,.3); border-radius:10px; padding:.5rem 1rem; color:#e8c96a; font-size:.85rem; font-weight:800; }

/* Sections */
.qt-section {
  background:#fff; border-radius:16px;
  border:1px solid #edf0f8;
  box-shadow:0 2px 12px rgba(12,26,53,.06);
  margin-bottom:1.2rem; overflow:hidden;
}
.qt-section-head {
  padding:.9rem 1.4rem; border-bottom:1px solid #f1f4fa;
  background:linear-gradient(90deg,#fafbff,#fff);
  display:flex; align-items:center; gap:.7rem;
}
.qt-section-icon {
  width:34px; height:34px; border-radius:9px;
  background:linear-gradient(135deg,#0c1a35,#1a3060);
  display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:.9rem;
}
.qt-section-title { font-size:.88rem; font-weight:800; color:#0c1a35; }
.qt-section-sub   { font-size:.7rem; color:#9ca3af; }
.qt-section-body  { padding:1.3rem 1.4rem; }

/* Tom Select */
.ts-wrapper { position:relative; z-index:100; }
.ts-dropdown { z-index:9999 !important; border-radius:12px !important; border:1.5px solid #e5e7eb !important; box-shadow:0 8px 32px rgba(12,26,53,.14) !important; overflow:hidden; }
.ts-dropdown .ts-dropdown-content { max-height:240px; }
.ts-dropdown .option { padding:.45rem .75rem !important; font-size:.82rem; }
.ts-control { border-radius:9px !important; border:1.5px solid #e5e7eb !important; padding:.55rem .9rem !important; font-size:.83rem !important; }
.ts-control:focus-within, .ts-wrapper.focus .ts-control { border-color:#c9a84c !important; box-shadow:0 0 0 3px rgba(201,168,76,.12) !important; }

/* Line items table */
.qt-items-table { width:100%; border-collapse:collapse; }
.qt-items-table thead th {
  font-size:.68rem; font-weight:800; color:#6b7280; text-transform:uppercase;
  letter-spacing:.06em; padding:.5rem .6rem; border-bottom:2px solid #f1f4fa; background:#fafbff;
}
.qt-items-table tbody td { padding:.4rem .5rem; vertical-align:middle; }
.qt-items-table tbody tr { border-bottom:1px solid #f8f9fc; }
.qt-items-table tbody tr:last-child { border-bottom:none; }
.qt-items-table .form-control { border-radius:8px; border:1.5px solid #e5e7eb; font-size:.82rem; padding:.38rem .6rem; }
.qt-items-table .form-control:focus { border-color:#c9a84c; box-shadow:0 0 0 2px rgba(201,168,76,.1); }
.item-total-cell { font-size:.82rem; font-weight:700; color:#0c1a35; white-space:nowrap; }
.qt-add-line-btn {
  display:flex; align-items:center; gap:.4rem; padding:.5rem 1rem;
  background:#f0f3fa; border:1.5px dashed #cbd5e1; border-radius:10px;
  font-size:.78rem; font-weight:700; color:#64748b; cursor:pointer;
  transition:all .15s; width:100%; justify-content:center; margin-top:.5rem;
  text-decoration:none;
}
.qt-add-line-btn:hover { background:#e8edf8; border-color:#94a3b8; color:#374151; }

/* Summary sidebar */
.qt-summary {
  background:#fff; border-radius:18px; border:1px solid #edf0f8;
  box-shadow:0 4px 24px rgba(12,26,53,.09); overflow:hidden; position:sticky; top:80px;
}
.qt-summary-head {
  padding:1.1rem 1.4rem; background:linear-gradient(130deg,#0c1a35,#1a3060);
  border-bottom:2px solid rgba(201,168,76,.2);
}
.qt-summary-title { font-size:.88rem; font-weight:800; color:#fff; }
.qt-summary-body  { padding:1.3rem 1.4rem; }
.qt-sum-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:.75rem; }
.qt-sum-lbl { font-size:.78rem; color:#6b7280; font-weight:600; }
.qt-sum-val { font-size:.85rem; font-weight:700; color:#0c1a35; }
.qt-sum-divider { border:none; border-top:2px solid #f1f4fa; margin:1rem 0; }
.qt-sum-total-lbl { font-size:.88rem; font-weight:800; color:#0c1a35; }
.qt-sum-total-val { font-size:1.5rem; font-weight:900; color:#c9a84c; letter-spacing:-.04em; }
.qt-summary-footer { padding:1rem 1.4rem; background:#f8f9fc; border-top:1px solid #edf0f8; display:flex; flex-direction:column; gap:.5rem; }

/* Status selector */
.qt-status-row { display:flex; gap:.5rem; }
.qt-status-btn {
  flex:1; padding:.45rem; border-radius:9px; border:1.5px solid #e5e7eb;
  background:#fff; font-size:.73rem; font-weight:700; color:#6b7280; cursor:pointer; text-align:center;
  transition:all .15s;
}
.qt-status-btn.active { border-color:#0c1a35; background:#0c1a35; color:#fff; }
.qt-status-btn:hover:not(.active) { border-color:#9ca3af; background:#f6f8fd; }

/* Remove row btn */
.rm-row-btn {
  background:none; border:none; color:#e5e7eb; cursor:pointer; padding:.2rem;
  border-radius:6px; font-size:1rem; line-height:1; transition:color .1s;
}
.rm-row-btn:hover { color:#dc2626; }

/* Error alert */
.qt-error { background:#fef2f2; border:1px solid #fecaca; border-radius:12px; padding:.85rem 1rem; margin-bottom:1rem; font-size:.82rem; color:#dc2626; }
</style>

<!-- Hero bar -->
<div class="qt-hero">
  <div>
    <div class="qt-hero-title">📄 New Quotation</div>
    <div class="qt-hero-sub">Build a professional quote for your customer</div>
    <?php if ($booking): ?>
    <div class="mt-2">
      <span style="background:rgba(201,168,76,.15);border:1px solid rgba(201,168,76,.3);border-radius:8px;padding:.3rem .75rem;color:#e8c96a;font-size:.78rem;font-weight:700;">
        Linked to <?= Helper::sanitize($booking['booking_ref']) ?> — <?= Helper::sanitize($booking['customer_name']) ?>
      </span>
    </div>
    <?php endif; ?>
  </div>
  <div style="display:flex;gap:.75rem;align-items:center;">
    <a href="<?= BASE_URL ?>/modules/quotations/index.php" class="btn btn-vp-outline" style="color:#fff;border-color:rgba(255,255,255,.25);font-size:.8rem;">← Back</a>
  </div>
</div>

<?php if ($errors): ?>
<div class="qt-error">
  <strong>Please fix the following:</strong>
  <ul class="mb-0 mt-1 ps-3"><?php foreach($errors as $e): ?><li><?= Helper::sanitize($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<form method="post" id="qt-form">
  <?php if ($booking): ?><input type="hidden" name="booking_id" value="<?= $booking['id'] ?>"><?php endif; ?>

  <div class="qt-layout">
    <!-- LEFT: Main form -->
    <div>

      <!-- Customer & Meta -->
      <div class="qt-section">
        <div class="qt-section-head">
          <div class="qt-section-icon">👤</div>
          <div>
            <div class="qt-section-title">Customer & Details</div>
            <div class="qt-section-sub">Who is this quotation for?</div>
          </div>
        </div>
        <div class="qt-section-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" style="font-size:.78rem;font-weight:700;color:#374151;">Customer <span style="color:#dc2626">*</span></label>
              <select name="customer_id" id="qt_customer" required>
                <option value="">— Search customer —</option>
                <?php foreach ($customers as $c): ?>
                <option value="<?= $c['id'] ?>" <?= (($booking['customer_id']??$_POST['customer_id']??$prefill_customer)==$c['id'])?'selected':'' ?>><?= Helper::sanitize($c['name']) ?> · <?= Helper::sanitize($c['phone']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label" style="font-size:.78rem;font-weight:700;color:#374151;">Valid Until</label>
              <input type="date" name="valid_until" class="form-control" value="<?= $_POST['valid_until']??($prefill_valid?:date('Y-m-d',strtotime('+7 days'))) ?>">
            </div>
            <div class="col-md-3">
              <?php if (Auth::isSuperAdmin()): ?>
              <label class="form-label" style="font-size:.78rem;font-weight:700;color:#374151;">Branch</label>
              <select name="branch_id" class="form-select" style="font-size:.83rem;">
                <?php foreach ($branches as $b): ?><option value="<?= $b['id'] ?>" <?= ($cu['branch_id']??$b['id'])==$b['id']?'selected':'' ?>><?= Helper::sanitize($b['name']) ?></option><?php endforeach; ?>
              </select>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Line Items -->
      <div class="qt-section">
        <div class="qt-section-head">
          <div class="qt-section-icon">📝</div>
          <div>
            <div class="qt-section-title">Line Items</div>
            <div class="qt-section-sub">Services, packages, add-ons</div>
          </div>
        </div>
        <div class="qt-section-body">
          <div class="table-responsive">
            <table class="qt-items-table">
              <thead>
                <tr>
                  <th style="min-width:220px">Description</th>
                  <th style="width:70px">Qty</th>
                  <th style="width:130px">Unit Price (Rs.)</th>
                  <th style="width:75px">Tax %</th>
                  <th style="width:110px">Total</th>
                  <th style="width:36px"></th>
                </tr>
              </thead>
              <tbody id="qt-items-body">
                <?php
                if (empty($display_items)) $display_items = [['description'=>'','quantity'=>1,'unit_price'=>0,'tax_percent'=>0]];
                foreach ($display_items as $idx => $it):
                  $lTotal = ((float)($it['quantity']??1)) * ((float)($it['unit_price']??0));
                ?>
                <tr class="qt-item-row">
                  <td><input type="text" name="items[][description]" class="form-control item-desc" placeholder="Description..." value="<?= Helper::sanitize($it['description']??'') ?>"></td>
                  <td><input type="number" name="items[][quantity]" class="form-control item-qty" min="1" step="0.01" value="<?= $it['quantity']??1 ?>"></td>
                  <td><input type="number" name="items[][unit_price]" class="form-control item-price" step="0.01" min="0" value="<?= $it['unit_price']??0 ?>"></td>
                  <td><input type="number" name="items[][tax_percent]" class="form-control item-tax" step="0.01" min="0" max="100" value="<?= $it['tax_percent']??0 ?>"></td>
                  <td class="item-total-cell">Rs. <?= number_format($lTotal, 2) ?></td>
                  <td><button type="button" class="rm-row-btn rm-item" title="Remove">✕</button></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <button type="button" class="qt-add-line-btn" id="qt-add-line">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
            Add Line Item
          </button>
        </div>
      </div>

      <!-- Notes & Terms -->
      <div class="qt-section">
        <div class="qt-section-head">
          <div class="qt-section-icon">📋</div>
          <div>
            <div class="qt-section-title">Notes & Terms</div>
            <div class="qt-section-sub">Internal notes and customer-facing terms</div>
          </div>
        </div>
        <div class="qt-section-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" style="font-size:.78rem;font-weight:700;color:#374151;">Notes (internal)</label>
              <textarea name="notes" class="form-control" rows="3" placeholder="Internal notes about this quotation..."><?= Helper::sanitize($_POST['notes']??'') ?></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label" style="font-size:.78rem;font-weight:700;color:#374151;">Terms & Conditions</label>
              <textarea name="terms" class="form-control" rows="3"><?= Helper::sanitize($_POST['terms']??$def_terms) ?></textarea>
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- RIGHT: Summary sidebar -->
    <div>
      <div class="qt-summary">
        <div class="qt-summary-head">
          <div class="qt-summary-title">💰 Quotation Summary</div>
        </div>
        <div class="qt-summary-body">
          <div class="qt-sum-row">
            <span class="qt-sum-lbl">Subtotal</span>
            <span class="qt-sum-val" id="qs-sub">Rs. <?= number_format($init_sub,2) ?></span>
          </div>
          <div class="qt-sum-row">
            <span class="qt-sum-lbl">Tax</span>
            <span class="qt-sum-val" id="qs-tax">Rs. <?= number_format($init_tax,2) ?></span>
          </div>
          <div class="qt-sum-row" style="align-items:flex-start;">
            <span class="qt-sum-lbl" style="padding-top:.4rem;">Discount (Rs.)</span>
            <div style="width:120px;">
              <input type="number" name="discount_amount" id="qt-discount" class="form-control text-end" step="0.01" min="0" value="<?= $_POST['discount_amount']??0 ?>" style="font-size:.85rem;font-weight:700;">
            </div>
          </div>
          <hr class="qt-sum-divider">
          <div class="qt-sum-row">
            <span class="qt-sum-total-lbl">Total</span>
            <span class="qt-sum-total-val" id="qs-total">Rs. <?= number_format($init_total,2) ?></span>
          </div>
          <hr class="qt-sum-divider">

          <!-- Status -->
          <div style="margin-bottom:.5rem;">
            <div style="font-size:.72rem;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.5rem;">Status</div>
            <div class="qt-status-row">
              <?php foreach (['draft'=>'Draft','sent'=>'Sent'] as $sv=>$sl): ?>
              <label class="qt-status-btn <?= ($_POST['status']??'draft')===$sv?'active':'' ?>">
                <input type="radio" name="status" value="<?= $sv ?>" style="display:none" <?= ($_POST['status']??'draft')===$sv?'checked':'' ?>>
                <?= $sl ?>
              </label>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <div class="qt-summary-footer">
          <button type="submit" class="btn btn-vp-gold w-100" style="font-size:.9rem;font-weight:800;padding:.75rem;">
            <svg xmlns="http://www.w3.org/2000/svg" class="icon me-1" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 13l4 4L19 7"/></svg>
            Create Quotation
          </button>
          <a href="<?= BASE_URL ?>/modules/quotations/index.php" class="btn btn-vp-outline w-100" style="font-size:.82rem;">Cancel</a>
        </div>
      </div>
    </div>
  </div>
</form>

<script>
// Tom Select for customer
new TomSelect('#qt_customer', {
  placeholder: 'Search by name or phone...',
  searchField: ['text'],
  maxOptions: 300,
  plugins: ['dropdown_input'],
  dropdownParent: 'body',
  render: {
    option: function(data, escape) {
      var parts = data.text.split('·');
      var name = parts[0]?parts[0].trim():data.text;
      var phone = parts[1]?parts[1].trim():'';
      return '<div style="display:flex;align-items:center;gap:.6rem;padding:.4rem .6rem;">' +
        '<div style="width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,#0c1a35,#1a3060);display:flex;align-items:center;justify-content:center;color:#fff;font-size:.73rem;font-weight:800;flex-shrink:0;">' + escape(name.charAt(0).toUpperCase()) + '</div>' +
        '<div><div style="font-size:.82rem;font-weight:700;color:#0c1a35;">' + escape(name) + '</div>' +
        (phone ? '<div style="font-size:.7rem;color:#6b7280;">' + escape(phone) + '</div>' : '') + '</div></div>';
    }
  }
});

// Status radio buttons styling
document.querySelectorAll('.qt-status-btn').forEach(function(label) {
  label.addEventListener('click', function() {
    document.querySelectorAll('.qt-status-btn').forEach(l => l.classList.remove('active'));
    this.classList.add('active');
  });
});

// Line item management
const itemTpl = () => `<tr class="qt-item-row">
  <td><input type="text" name="items[][description]" class="form-control item-desc" placeholder="Description..."></td>
  <td><input type="number" name="items[][quantity]" class="form-control item-qty" min="1" step="0.01" value="1"></td>
  <td><input type="number" name="items[][unit_price]" class="form-control item-price" step="0.01" min="0" value="0"></td>
  <td><input type="number" name="items[][tax_percent]" class="form-control item-tax" step="0.01" min="0" max="100" value="0"></td>
  <td class="item-total-cell">Rs. 0.00</td>
  <td><button type="button" class="rm-row-btn rm-item" title="Remove">✕</button></td>
</tr>`;

document.getElementById('qt-add-line').addEventListener('click', function() {
  const body = document.getElementById('qt-items-body');
  body.insertAdjacentHTML('beforeend', itemTpl());
  bindRow(body.querySelector('tr:last-child'));
  recalc();
  body.querySelector('tr:last-child .item-desc').focus();
});

document.querySelectorAll('.qt-item-row').forEach(bindRow);

function bindRow(row) {
  row.querySelectorAll('.item-qty,.item-price,.item-tax').forEach(el => el.addEventListener('input', recalc));
  row.querySelector('.rm-item').addEventListener('click', function() {
    if (document.querySelectorAll('.qt-item-row').length > 1) {
      this.closest('tr').remove(); recalc();
    } else {
      this.closest('tr').querySelectorAll('input').forEach(i => { i.value = i.classList.contains('item-qty') ? 1 : 0; });
      recalc();
    }
  });
}

document.getElementById('qt-discount').addEventListener('input', recalc);

function recalc() {
  let sub = 0, tax = 0;
  document.querySelectorAll('.qt-item-row').forEach(row => {
    const qty   = parseFloat(row.querySelector('.item-qty').value) || 0;
    const price = parseFloat(row.querySelector('.item-price').value) || 0;
    const taxp  = parseFloat(row.querySelector('.item-tax').value) || 0;
    const line  = qty * price;
    sub += line; tax += line * taxp / 100;
    row.querySelector('.item-total-cell').textContent = 'Rs. ' + line.toLocaleString('en-LK', {minimumFractionDigits:2, maximumFractionDigits:2});
  });
  const disc = parseFloat(document.getElementById('qt-discount').value) || 0;
  const total = Math.max(0, sub + tax - disc);
  document.getElementById('qs-sub').textContent   = 'Rs. ' + sub.toLocaleString('en-LK', {minimumFractionDigits:2});
  document.getElementById('qs-tax').textContent   = 'Rs. ' + tax.toLocaleString('en-LK', {minimumFractionDigits:2});
  document.getElementById('qs-total').textContent = 'Rs. ' + total.toLocaleString('en-LK', {minimumFractionDigits:2});
}
recalc();
</script>
<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
