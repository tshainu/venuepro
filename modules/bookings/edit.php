<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
$db = Database::getInstance();
$cu = Auth::currentUser();
$id = (int)($_GET['id'] ?? 0);

$bk = $db->fetchOne("SELECT * FROM bookings WHERE id=?", [$id]);
if (!$bk) { Helper::flash('error','Booking not found.'); Helper::redirect(BASE_URL.'/modules/bookings/index.php'); }
if ($cu['branch_id'] && $bk['branch_id'] != $cu['branch_id']) { Helper::flash('error','Access denied.'); Helper::redirect(BASE_URL.'/modules/bookings/index.php'); }
if (in_array($bk['status'],['completed','cancelled']) && !Auth::isSuperAdmin()) {
    Helper::flash('error','Cannot edit a '.$bk['status'].' booking.'); Helper::redirect(BASE_URL.'/modules/bookings/view.php?id='.$id);
}

$customers   = $db->fetchAll("SELECT id,name,mobile as phone FROM customers ORDER BY name");
$halls       = $db->fetchAll("SELECT id,name,capacity FROM halls WHERE is_active=1 ORDER BY name");
$packages    = $db->fetchAll("SELECT id,name,price FROM packages WHERE is_active=1 ORDER BY name");
$addons      = $db->fetchAll("SELECT id,name,price,unit,tax_percent FROM addons WHERE is_available=1 ORDER BY name");
$existing_addons = $db->fetchAll("SELECT * FROM booking_addons WHERE booking_id=?", [$id]);
$event_types = ['Wedding','Reception','Engagement','Birthday','Corporate','Conference','Other'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id    = (int)($_POST['customer_id'] ?? 0);
    $hall_id        = (int)($_POST['hall_id'] ?? 0) ?: null;
    $package_id     = (int)($_POST['package_id'] ?? 0) ?: null;
    $event_type     = trim($_POST['event_type'] ?? '');
    $event_date     = trim($_POST['event_date'] ?? '');
    $event_end_date = trim($_POST['event_end_date'] ?? '') ?: null;
    $event_time     = trim($_POST['event_time'] ?? '') ?: null;
    $event_end_time = trim($_POST['event_end_time'] ?? '') ?: null;
    $guest_count    = (int)($_POST['guest_count'] ?? 0);
    $status         = $_POST['status'] ?? $bk['status'];
    $notes          = trim($_POST['notes'] ?? '');
    $discount       = (float)($_POST['discount_amount'] ?? 0);

    if (!$customer_id) $errors[] = 'Customer is required.';
    if (!$event_date)  $errors[] = 'Event date is required.';

    if ($hall_id && $event_date && !$errors) {
        $conflict = $db->fetchOne(
            "SELECT id,booking_ref FROM bookings WHERE hall_id=? AND status NOT IN ('cancelled') AND event_date=? AND id!=?",
            [$hall_id, $event_date, $id]
        );
        if ($conflict) $errors[] = "Hall conflict: {$conflict['booking_ref']} already booked on $event_date.";
    }

    if (!$errors) {
        $subtotal = (float)($_POST['total_amount'] ?? $bk['total_amount']);
        $tax      = (float)($_POST['tax_amount'] ?? $bk['tax_amount']);
        $final    = $subtotal + $tax - $discount;

        $db->execute(
            "UPDATE bookings SET customer_id=?,hall_id=?,package_id=?,event_type=?,event_date=?,event_end_date=?,event_time=?,event_end_time=?,guest_count=?,status=?,notes=?,total_amount=?,discount_amount=?,tax_amount=?,final_amount=?,balance_amount=balance_amount+(? - final_amount) WHERE id=?",
            [$customer_id,$hall_id,$package_id,$event_type,$event_date,$event_end_date,$event_time,$event_end_time,$guest_count,$status,$notes,$subtotal,$discount,$tax,$final,$final,$id]
        );
        // Recalc balance
        $db->execute("UPDATE bookings SET balance_amount=final_amount-paid_amount WHERE id=?", [$id]);

        // Replace add-ons
        $db->execute("DELETE FROM booking_addons WHERE booking_id=?", [$id]);
        $addon_ids  = $_POST['addon_id'] ?? [];
        $addon_qtys = $_POST['addon_qty'] ?? [];
        foreach ($addon_ids as $i => $aid) {
            $aid = (int)$aid;
            if (!$aid) continue;
            $qty = max(1,(int)($addon_qtys[$i] ?? 1));
            $adrow = $db->fetchOne("SELECT * FROM addons WHERE id=?", [$aid]);
            if ($adrow) {
                $db->insert(
                    "INSERT INTO booking_addons (booking_id,addon_id,name,quantity,unit_price,tax_percent,total_price) VALUES (?,?,?,?,?,?,?)",
                    [$id, $aid, $adrow['name'], $qty, $adrow['price'], $adrow['tax_percent'], $adrow['price']*$qty]
                );
            }
        }

        Logger::log('edit', 'bookings', $id, $bk['booking_ref'],
            ['customer_id'=>$bk['customer_id'],'hall_id'=>$bk['hall_id'],'event_date'=>$bk['event_date'],'status'=>$bk['status'],'final_amount'=>$bk['final_amount']],
            ['customer_id'=>$customer_id,'hall_id'=>$hall_id,'event_date'=>$event_date,'status'=>$status,'final_amount'=>$final],
            "Edited booking {$bk['booking_ref']}");
        Helper::flash('success','Booking updated.');
        Helper::redirect(BASE_URL.'/modules/bookings/view.php?id='.$id);
    }
} else {
    $_POST = $bk;
}

$pageTitle = 'Edit Booking';
$breadcrumbs = [['label'=>'Bookings','url'=>BASE_URL.'/modules/bookings/index.php'],['label'=>$bk['booking_ref'],'url'=>BASE_URL.'/modules/bookings/view.php?id='.$id],['label'=>'Edit']];
require_once ROOT_PATH . '/includes/header.php';
?>
<div class="vp-page-header d-print-none">
  <div class="d-flex align-items-center">
    <h1 class="vp-page-title">Edit Booking: <?= Helper::sanitize($bk['booking_ref']) ?></h1>
  </div>
</div>

<form method="post" id="booking-form">
  <div class="row">
    <div class="col-lg-8">
      <div class="card vp-card mb-3">
        <div class="card-header"><h3 class="card-title">Event Details</h3></div>
        <div class="card-body">
          <?php if ($errors): ?>
          <div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e): ?><li><?= Helper::sanitize($e) ?></li><?php endforeach; ?></ul></div>
          <?php endif; ?>

          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label required">Customer</label>
              <select name="customer_id" class="form-select" required>
                <?php foreach ($customers as $c): ?>
                <option value="<?= $c['id'] ?>" <?= ($_POST['customer_id']??'')==$c['id']?'selected':'' ?>><?= Helper::sanitize($c['name']) ?> (<?= Helper::sanitize($c['phone']) ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Status</label>
              <select name="status" class="form-select">
                <?php foreach (['inquiry','booked','confirmed','completed','cancelled'] as $s): ?>
                <option value="<?= $s ?>" <?= ($_POST['status']??'')===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="row mb-3">
            <div class="col-md-4">
              <label class="form-label required">Event Date</label>
              <input type="date" name="event_date" class="form-control" value="<?= $_POST['event_date']??'' ?>" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">End Date</label>
              <input type="date" name="event_end_date" class="form-control" value="<?= $_POST['event_end_date']??'' ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Event Type</label>
              <select name="event_type" class="form-select">
                <option value="">— Select —</option>
                <?php foreach ($event_types as $et): ?>
                <option value="<?= $et ?>" <?= ($_POST['event_type']??'')===$et?'selected':'' ?>><?= $et ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="row mb-3">
            <div class="col-md-3"><label class="form-label">Start Time</label><input type="time" name="event_time" class="form-control" value="<?= $_POST['event_time']??'' ?>"></div>
            <div class="col-md-3"><label class="form-label">End Time</label><input type="time" name="event_end_time" class="form-control" value="<?= $_POST['event_end_time']??'' ?>"></div>
            <div class="col-md-3"><label class="form-label">Guest Count</label><input type="number" name="guest_count" class="form-control" min="0" value="<?= $_POST['guest_count']??'' ?>"></div>
          </div>

          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label">Hall</label>
              <select name="hall_id" class="form-select">
                <option value="">— Select Hall —</option>
                <?php foreach ($halls as $h): ?>
                <option value="<?= $h['id'] ?>" <?= ($_POST['hall_id']??'')==$h['id']?'selected':'' ?>><?= Helper::sanitize($h['name']) ?> (<?= number_format($h['capacity']) ?> guests)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Package</label>
              <select name="package_id" class="form-select" id="pkg_select">
                <option value="" data-price="0">— No Package —</option>
                <?php foreach ($packages as $p): ?>
                <option value="<?= $p['id'] ?>" data-price="<?= $p['price'] ?>" <?= ($_POST['package_id']??'')==$p['id']?'selected':'' ?>><?= Helper::sanitize($p['name']) ?> (<?= Helper::formatCurrency($p['price']) ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Notes</label>
            <textarea name="notes" class="form-control" rows="2"><?= Helper::sanitize($_POST['notes']??'') ?></textarea>
          </div>
        </div>
      </div>

      <!-- Add-ons -->
      <div class="card vp-card mb-3">
        <div class="card-header d-flex align-items-center justify-content-between">
          <h3 class="card-title mb-0">Add-ons</h3>
          <button type="button" class="btn btn-sm btn-outline-primary" id="add-addon-row">+ Add Row</button>
        </div>
        <div class="card-body p-0">
          <table class="table table-sm mb-0">
            <thead><tr><th>Add-on</th><th>Qty</th><th>Unit Price</th><th>Total</th><th></th></tr></thead>
            <tbody id="addon-rows">
              <?php
              $ea = $existing_addons;
              if (isset($_POST['addon_id'])) {
                  $ea = [];
                  foreach ($_POST['addon_id'] as $i => $aid) { $ea[] = ['addon_id'=>$aid,'quantity'=>$_POST['addon_qty'][$i]??1]; }
              }
              foreach ($ea as $ea_row): ?>
              <tr class="addon-row">
                <td>
                  <select name="addon_id[]" class="form-select form-select-sm addon-select">
                    <option value="">— Select —</option>
                    <?php foreach ($addons as $a): ?>
                    <option value="<?= $a['id'] ?>" data-price="<?= $a['price'] ?>" data-tax="<?= $a['tax_percent'] ?>" <?= ($ea_row['addon_id']??'')==$a['id']?'selected':'' ?>>
                      <?= Helper::sanitize($a['name']) ?> (<?= Helper::formatCurrency($a['price']) ?>/<?= $a['unit'] ?>)
                    </option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td style="width:80px"><input type="number" name="addon_qty[]" class="form-control form-control-sm addon-qty" min="1" value="<?= (int)($ea_row['quantity']??1) ?>"></td>
                <td class="addon-unit-price text-secondary">—</td>
                <td class="addon-total fw-bold">—</td>
                <td><button type="button" class="btn btn-sm btn-ghost-danger remove-addon-row">✕</button></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card sticky-top" style="top:80px">
        <div class="card-header"><h3 class="card-title">Summary</h3></div>
        <div class="card-body">
          <div class="mb-2" id="base-amount-row">
            <label class="form-label">Base Amount</label>
            <input type="number" id="base_amount_input" class="form-control" step="0.01" min="0" value="<?= number_format((float)($bk['total_amount'] ?? 0), 2, '.', '') ?>" placeholder="0.00">
            <small class="text-muted">Used when no package is selected</small>
          </div>
          <div class="d-flex justify-content-between mb-2"><span>Package</span><span id="sum-package">Rs. 0.00</span></div>
          <div class="d-flex justify-content-between mb-2"><span>Add-ons</span><span id="sum-addons">Rs. 0.00</span></div>
          <hr>
          <div class="d-flex justify-content-between mb-2"><span>Subtotal</span><span id="sum-subtotal">Rs. 0.00</span></div>
          <div class="d-flex justify-content-between mb-2"><span>Tax</span><span id="sum-tax">Rs. 0.00</span></div>
          <div class="mb-2">
            <label class="form-label">Discount</label>
            <input type="number" name="discount_amount" id="discount_input" class="form-control" step="0.01" min="0" value="<?= $_POST['discount_amount']??$bk['discount_amount'] ?>">
          </div>
          <hr>
          <div class="d-flex justify-content-between fw-bold fs-4"><span>Total</span><span id="sum-total">Rs. 0.00</span></div>
          <input type="hidden" name="total_amount" id="h-total">
          <input type="hidden" name="tax_amount" id="h-tax">
        </div>
        <div class="card-footer d-flex gap-2">
          <button type="submit" class="btn btn-primary w-100">Update Booking</button>
          <a href="<?= BASE_URL ?>/modules/bookings/view.php?id=<?= $id ?>" class="btn btn-vp-outline">Cancel</a>
        </div>
      </div>
    </div>
  </div>
</form>

<script>
const addonTemplate = `<tr class="addon-row">
  <td><select name="addon_id[]" class="form-select form-select-sm addon-select">
    <option value="">— Select —</option>
    <?php foreach ($addons as $a): echo '<option value="'.$a['id'].'" data-price="'.$a['price'].'" data-tax="'.$a['tax_percent'].'">'.htmlspecialchars($a['name']).' ('.Helper::formatCurrency($a['price']).'/'.$a['unit'].')</option>'; endforeach; ?>
  </select></td>
  <td style="width:80px"><input type="number" name="addon_qty[]" class="form-control form-control-sm addon-qty" min="1" value="1"></td>
  <td class="addon-unit-price text-secondary">—</td>
  <td class="addon-total fw-bold">—</td>
  <td><button type="button" class="btn btn-sm btn-ghost-danger remove-addon-row">✕</button></td>
</tr>`;
document.getElementById('add-addon-row').addEventListener('click', () => {
  document.getElementById('addon-rows').insertAdjacentHTML('beforeend', addonTemplate);
  bindRow(document.querySelector('#addon-rows tr:last-child')); recalc();
});
document.querySelectorAll('.addon-row').forEach(bindRow);
function bindRow(row) {
  row.querySelector('.addon-select').addEventListener('change', function(){
    const opt = this.options[this.selectedIndex];
    row.querySelector('.addon-unit-price').textContent = opt.dataset.price ? 'Rs. '+parseFloat(opt.dataset.price).toFixed(2) : '—'; recalc();
  });
  row.querySelector('.addon-qty').addEventListener('input', recalc);
  row.querySelector('.remove-addon-row').addEventListener('click', function(){ this.closest('tr').remove(); recalc(); });
  // Init display
  const sel = row.querySelector('.addon-select');
  const opt = sel.options[sel.selectedIndex];
  if (opt.dataset.price) row.querySelector('.addon-unit-price').textContent = 'Rs. '+parseFloat(opt.dataset.price).toFixed(2);
}
document.getElementById('pkg_select').addEventListener('change', recalc);
document.getElementById('discount_input').addEventListener('input', recalc);
document.getElementById('base_amount_input').addEventListener('input', recalc);
function recalc() {
  const pkgSelect = document.getElementById('pkg_select');
  const pkgOpt = pkgSelect.options[pkgSelect.selectedIndex];
  const pkgPrice = parseFloat(pkgOpt.dataset.price)||0;
  const baseAmount = parseFloat(document.getElementById('base_amount_input').value)||0;
  const effectivePkgPrice = pkgPrice > 0 ? pkgPrice : baseAmount;
  // Show/hide base amount row
  document.getElementById('base-amount-row').style.display = pkgPrice > 0 ? 'none' : '';
  document.getElementById('sum-package').textContent = 'Rs. '+(pkgPrice > 0 ? pkgPrice : 0).toFixed(2);
  let addonSub=0,addonTax=0;
  document.querySelectorAll('.addon-row').forEach(row=>{
    const sel=row.querySelector('.addon-select'); const opt=sel.options[sel.selectedIndex];
    const price=parseFloat(opt.dataset.price)||0; const tax=parseFloat(opt.dataset.tax)||0;
    const qty=parseInt(row.querySelector('.addon-qty').value)||1;
    const total=price*qty; addonSub+=total; addonTax+=total*tax/100;
    row.querySelector('.addon-total').textContent=total>0?'Rs. '+total.toFixed(2):'—';
  });
  const subtotal=effectivePkgPrice+addonSub, tax=addonTax, discount=parseFloat(document.getElementById('discount_input').value)||0;
  document.getElementById('sum-addons').textContent='Rs. '+addonSub.toFixed(2);
  document.getElementById('sum-subtotal').textContent='Rs. '+subtotal.toFixed(2);
  document.getElementById('sum-tax').textContent='Rs. '+tax.toFixed(2);
  document.getElementById('sum-total').textContent='Rs. '+Math.max(0,subtotal+tax-discount).toFixed(2);
  document.getElementById('h-total').value=subtotal.toFixed(2);
  document.getElementById('h-tax').value=tax.toFixed(2);
}
recalc();
</script>
<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
