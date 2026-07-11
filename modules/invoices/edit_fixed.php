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
$customers = $db->fetchAll("SELECT id,name,mobile as phone FROM customers WHERE id = ? LIMIT 1", [$inv['customer_id']]);
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
    $branch_id    = $cu['branch_id'] ?: (int)($_POST['branch_id'] ?? $inv['branch_id']);
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
        $total   = $subtotal + $tax_total - $discount;
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

        // Sync booking financials
        if ($inv['booking_id']) {
            $inv_totals = $db->fetchOne(
                "SELECT COALESCE(SUM(total),0) as total_sum, COALESCE(SUM(tax_amount),0) as tax_sum, COALESCE(SUM(discount_amount),0) as disc_sum
                 FROM invoices WHERE booking_id=? AND status NOT IN ('cancelled')",
                [$inv['booking_id']]
            );
            if ($inv_totals) {
                $db->execute(
                    "UPDATE bookings SET total_amount=?, tax_amount=?, discount_amount=?, final_amount=?, balance_amount=final_amount - paid_amount WHERE id=?",
                    [$inv_totals['total_sum'], $inv_totals['tax_sum'], $inv_totals['disc_sum'], $inv_totals['total_sum'], $inv['booking_id']]
                );
                $db->execute("UPDATE bookings SET balance_amount = final_amount - paid_amount WHERE id=?", [$inv['booking_id']]);
            }
        }

        Helper::flash('success','Invoice updated.');
        $back = $_POST['back_url'] ?? '';
        Helper::redirect($back ?: BASE_URL.'/modules/invoices/view.php?id='.$id);
    }
}

$def_terms = Helper::getSetting('invoice_terms') ?? "Payment due within 7 days of invoice date.\nBank: Peoples Bank | A/C: 0012345678 | VenuePro Lanka";
$display_items = isset($_POST['items']) ? $_POST['items'] : $existing_items;

$pageTitle = 'Edit Invoice: ' . $inv['invoice_number'];
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

<style>
/* ── Modern Invoice Edit ───────────────────────── */
.evt-hero {
  background: linear-gradient(130deg,#08111f 0%,#0f1f40 40%,#162d5a 100%);
  border-radius: 20px; padding: 1.6rem 2rem; margin-bottom:1.5rem;
  border:1px solid rgba(201,168,76,.18); position:relative; overflow:hidden;
  box-shadow:0 12px 40px rgba(8,17,31,.3);
}
.evt-hero::before {
  content:''; position:absolute; top:-60px; right:-40px;
  width:240px; height:240px; border-radius:50%;
  background:radial-gradient(circle,rgba(201,168,76,.18) 0%,transparent 70%);
  pointer-events:none;
}
.evt-hero-title  { color:#fff; font-size:1.5rem; font-weight:800; letter-spacing:-.03em; display:flex; align-items:center; gap:.6rem; position:relative;z-index:2; }
.evt-hero-sub    { color:rgba(255,255,255,.45); font-size:.8rem; margin-top:.25rem; position:relative;z-index:2; }

.evt-card {
  background:#fff; border-radius:16px; border:1px solid #edf0f8;
  box-shadow:0 2px 14px rgba(12,26,53,.07); margin-bottom:1.2rem; overflow:visible;
  position:relative; z-index:1;
}
.evt-card-head {
  padding:.95rem 1.4rem; border-bottom:1px solid #f1f4fa;
  display:flex; align-items:center; gap:.7rem;
  background: linear-gradient(90deg,#fafbff,#fff);
}
.evt-card-icon {
  width:36px; height:36px; border-radius:10px;
  background:linear-gradient(135deg,#0c1a35,#1a3060);
  display:flex; align-items:center; justify-content:center; flex-shrink:0;
}
.evt-card-title { font-size:.88rem; font-weight:800; color:#0c1a35; }
.evt-card-subtitle { font-size:.7rem; color:#9ca3af; margin-top:.05rem; }
.evt-card-body { padding:1.3rem 1.4rem; }

.evt-summary {
  background:#fff; border-radius:16px; border:1px solid #edf0f8;
  box-shadow:0 4px 20px rgba(12,26,53,.09); position:sticky; top:80px;
  overflow:hidden;
}
.evt-summary-head {
  background:linear-gradient(135deg,#0c1a35,#1a3060);
  padding:1rem 1.3rem;
}
.evt-summary-title { color:#fff; font-size:.88rem; font-weight:800; }
.evt-summary-body  { padding:1.1rem 1.3rem; }
.evt-sum-row {
  display:flex; align-items:center; justify-content:space-between;
  padding:.5rem 0; border-bottom:1px solid #f5f7fc; font-size:.8rem;
}
.evt-sum-label { color:#6b7280; font-weight:500; }
.evt-sum-value { color:#0c1a35; font-weight:700; }
.evt-sum-total {
  background:linear-gradient(135deg,#fdf5e0,#fffbf0);
  border:1px solid rgba(201,168,76,.3);
  border-radius:10px; padding:.9rem 1rem; margin-top:.8rem;
  display:flex; align-items:center; justify-content:space-between;
}
.evt-sum-total-label { font-size:.8rem; font-weight:700; color:#92640c; }
.evt-sum-total-val   { font-size:1.3rem; font-weight:900; color:#0c1a35; letter-spacing:-.03em; }

.form-label { font-size:.78rem; font-weight:700; color:#374151; margin-bottom:.35rem; }
.form-control, .form-select {
  border-radius:9px; border:1.5px solid #e5e7eb; font-size:.83rem;
  padding:.55rem .9rem; transition:border-color .15s, box-shadow .15s;
}
.form-control:focus, .form-select:focus {
  border-color:#c9a84c; box-shadow:0 0 0 3px rgba(201,168,76,.12);
}
.form-required::after { content:' *'; color:#dc2626; }

.btn-update-invoice {
  background:linear-gradient(135deg,#c9a84c,#e8c96a);
  border:none; color:#fff; border-radius:12px; padding:.75rem 2rem;
  font-size:.9rem; font-weight:800; letter-spacing:.02em;
  box-shadow:0 4px 18px rgba(201,168,76,.4); transition:all .18s; width:100%;
  display:flex; align-items:center; justify-content:center; gap:.5rem;
}
.btn-update-invoice:hover { transform:translateY(-2px); box-shadow:0 8px 28px rgba(201,168,76,.5); }
</style>

<!-- Hero -->
<div class="evt-hero">
  <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
    <div>
      <div class="evt-hero-title">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#c9a84c" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
        Edit Invoice: <?= Helper::sanitize($inv['invoice_number']) ?>
      </div>
      <div class="evt-hero-sub">Modify invoice details and line items</div>
    </div>
    <a href="<?= htmlspecialchars($back_url) ?>" class="btn btn-sm" style="background:rgba(255,255,255,.15);color:#fff;border:1.5px solid rgba(255,255,255,.35);border-radius:8px;font-weight:600;padding:.42rem .9rem;backdrop-filter:blur(4px);">← Back</a>
  </div>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger d-flex align-items-center gap-2 mb-3" style="border-radius:12px;">
  <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
  <div><ul class="mb-0 ps-3"><?php foreach($errors as $e): ?><li><?= Helper::sanitize($e) ?></li><?php endforeach; ?></ul></div>
</div>
<?php endif; ?>

<form method="post" id="invoice-form">
  <input type="hidden" name="back_url" value="<?= htmlspecialchars($back_url) ?>">
  <?php if ($inv['booking_id']): ?>
  <input type="hidden" name="booking_id" value="<?= $inv['booking_id'] ?>">
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-lg-8">
      <!-- Section 1: Details -->
      <div class="evt-card">
        <div class="evt-card-head">
          <div class="evt-card-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#c9a84c" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          </div>
          <div>
            <div class="evt-card-title">Invoice Details</div>
            <div class="evt-card-subtitle">Customer and basic information</div>
          </div>
        </div>
        <div class="evt-card-body">
          <?php if ($inv['booking_id']): ?>
          <div class="alert alert-info py-2 mb-3" style="border-radius:10px;font-size:.85rem;">
            Booking Reference: <strong><?= Helper::sanitize($inv['booking_ref']) ?></strong>
          </div>
          <?php endif; ?>
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label form-required">Customer</label>
              <select name="customer_id" class="form-select" required>
                <option value="">— Select Customer —</option>
                <?php foreach ($customers as $c): ?>
                <option value="<?= $c['id'] ?>" <?= (($_POST['customer_id']??$inv['customer_id'])==$c['id'])?'selected':'' ?>><?= Helper::sanitize($c['name']) ?> (<?= Helper::sanitize($c['phone']) ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Invoice Type</label>
              <select name="invoice_type" class="form-select">
                <?php foreach (['advance','interim','final'] as $t): ?>
                <option value="<?= $t ?>" <?= (($_POST['invoice_type']??$inv['invoice_type'])===$t)?'selected':'' ?>><?= ucfirst($t) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label form-required">Invoice Date</label>
              <input type="date" name="invoice_date" class="form-control" value="<?= $_POST['invoice_date']??$inv['invoice_date'] ?>" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Due Date</label>
              <input type="date" name="due_date" class="form-control" value="<?= $_POST['due_date']??$inv['due_date'] ?>">
            </div>
            <div class="col-md-4">
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

      <!-- Section 2: Line Items -->
      <div class="evt-card">
        <div class="evt-card-head">
          <div class="evt-card-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#c9a84c" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
          </div>
          <div>
            <div class="evt-card-title">Line Items</div>
            <div class="evt-card-subtitle">Services and charges breakdown</div>
          </div>
          <button type="button" class="btn btn-sm btn-outline-primary ms-auto" id="add-item">+ Add Line</button>
        </div>
        <div class="evt-card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm mb-0">
              <thead class="bg-light">
                <tr>
                  <th class="ps-3">Description</th>
                  <th style="width:90px">Qty</th>
                  <th style="width:130px">Unit Price</th>
                  <th style="width:90px">Tax %</th>
                  <th style="width:120px">Total</th>
                  <th style="width:40px"></th>
                </tr>
              </thead>
              <tbody id="items-body">
                <?php foreach ($display_items as $k => $it): ?>
                <tr class="item-row">
                  <td class="ps-3"><input type="text" name="items[<?= $k ?>][description]" class="form-control form-control-sm item-desc" value="<?= Helper::sanitize($it['description']??'') ?>" required></td>
                  <td><input type="number" name="items[<?= $k ?>][quantity]" class="form-control form-control-sm item-qty" min="1" step="0.01" value="<?= $it['quantity']??1 ?>"></td>
                  <td><input type="number" name="items[<?= $k ?>][unit_price]" class="form-control form-control-sm item-price" step="0.01" min="0" value="<?= $it['unit_price']??0 ?>"></td>
                  <td><input type="number" name="items[<?= $k ?>][tax_percent]" class="form-control form-control-sm item-tax" step="0.01" min="0" max="100" value="<?= $it['tax_percent']??0 ?>"></td>
                  <td class="item-total fw-bold align-middle text-vp-navy"><?= Helper::formatCurrency(($it['quantity']??1)*($it['unit_price']??0)) ?></td>
                  <td class="pe-3"><button type="button" class="btn btn-sm btn-ghost-danger remove-item">✕</button></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Section 3: Notes & Terms -->
      <div class="evt-card">
        <div class="evt-card-head">
          <div class="evt-card-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#c9a84c" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
          </div>
          <div>
            <div class="evt-card-title">Notes & Terms</div>
            <div class="evt-card-subtitle">Additional information and payment terms</div>
          </div>
        </div>
        <div class="evt-card-body">
          <div class="mb-3">
            <label class="form-label">Internal Notes</label>
            <textarea name="notes" class="form-control" rows="2" placeholder="Notes for internal use..."><?= Helper::sanitize($_POST['notes']??$inv['notes']) ?></textarea>
          </div>
          <div>
            <label class="form-label">Terms & Conditions</label>
            <textarea name="terms" class="form-control" rows="3"><?= Helper::sanitize($_POST['terms']??$inv['terms']??$def_terms) ?></textarea>
          </div>
        </div>
      </div>
    </div>

    <!-- Right Column: Summary -->
    <div class="col-lg-4">
      <div class="evt-summary">
        <div class="evt-summary-head">
          <div class="evt-summary-title">Invoice Summary</div>
        </div>
        <div class="evt-summary-body">
          <?php if ($inv['paid_amount'] > 0): ?>
          <div class="alert alert-warning py-2 mb-3" style="border-radius:10px;font-size:.8rem;">
            <strong>Rs. <?= number_format($inv['paid_amount'],2) ?></strong> already paid.
          </div>
          <?php endif; ?>

          <div class="evt-sum-row">
            <span class="evt-sum-label">Subtotal</span>
            <span class="evt-sum-value" id="sum-sub">Rs. 0.00</span>
          </div>
          <div class="evt-sum-row">
            <span class="evt-sum-label">Tax Amount</span>
            <span class="evt-sum-value" id="sum-tax">Rs. 0.00</span>
          </div>
          <div class="mt-3">
            <label class="form-label">Discount Amount</label>
            <input type="number" name="discount_amount" id="discount_input" class="form-control" step="0.01" min="0" value="<?= $_POST['discount_amount']??$inv['discount_amount'] ?>">
          </div>

          <div class="evt-sum-total">
            <span class="evt-sum-total-label">Total Amount</span>
            <span class="evt-sum-total-val" id="sum-total">Rs. 0.00</span>
          </div>

          <div class="mt-4 d-grid gap-2">
            <button type="submit" class="btn-update-invoice">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
              Save Changes
            </button>
            <a href="<?= htmlspecialchars($back_url) ?>" class="btn btn-outline-secondary" style="border-radius:12px;padding:.7rem;">Cancel</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</form>

<script>
const rowTpl = () => {
    const idx = Date.now(); // Unique index for new rows
    return `<tr class="item-row">
        <td class="ps-3"><input type="text" name="items[${idx}][description]" class="form-control form-control-sm item-desc" required></td>
        <td><input type="number" name="items[${idx}][quantity]" class="form-control form-control-sm item-qty" min="1" step="0.01" value="1"></td>
        <td><input type="number" name="items[${idx}][unit_price]" class="form-control form-control-sm item-price" step="0.01" min="0" value="0"></td>
        <td><input type="number" name="items[${idx}][tax_percent]" class="form-control form-control-sm item-tax" step="0.01" min="0" max="100" value="0"></td>
        <td class="item-total fw-bold align-middle text-vp-navy">Rs. 0.00</td>
        <td class="pe-3"><button type="button" class="btn btn-sm btn-ghost-danger remove-item">✕</button></td>
    </tr>`;
};

document.getElementById('add-item').addEventListener('click', () => {
    document.getElementById('items-body').insertAdjacentHTML('beforeend', rowTpl());
    bindRow(document.querySelector('#items-body tr:last-child'));
    recalc();
});

document.querySelectorAll('.item-row').forEach(bindRow);

function bindRow(row) {
    row.querySelectorAll('.item-qty, .item-price, .item-tax').forEach(el => el.addEventListener('input', recalc));
    row.querySelector('.remove-item').addEventListener('click', function() {
        this.closest('tr').remove();
        recalc();
    });
}

document.getElementById('discount_input').addEventListener('input', recalc);

function recalc() {
    let sub = 0, tax = 0;
    document.querySelectorAll('.item-row').forEach(row => {
        const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
        const price = parseFloat(row.querySelector('.item-price').value) || 0;
        const taxp = parseFloat(row.querySelector('.item-tax').value) || 0;
        const line = qty * price;
        sub += line;
        tax += line * taxp / 100;
        row.querySelector('.item-total').textContent = 'Rs. ' + line.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    });
    const disc = parseFloat(document.getElementById('discount_input').value) || 0;
    document.getElementById('sum-sub').textContent = 'Rs. ' + sub.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('sum-tax').textContent = 'Rs. ' + tax.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('sum-total').textContent = 'Rs. ' + Math.max(0, sub + tax - disc).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

recalc();
</script>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
