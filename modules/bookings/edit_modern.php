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
        $db->execute("UPDATE bookings SET balance_amount=final_amount-paid_amount WHERE id=?", [$id]);

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

$pageTitle = 'Edit Booking: ' . $bk['booking_ref'];
$breadcrumbs = [['label'=>'Bookings','url'=>BASE_URL.'/modules/bookings/index.php'],['label'=>$bk['booking_ref'],'url'=>BASE_URL.'/modules/bookings/view.php?id='.$id],['label'=>'Edit']];
require_once ROOT_PATH . '/includes/header.php';
?>

<style>
/* ── Event Registration Page ───────────────────────── */
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
.evt-hero-date   { background:rgba(201,168,76,.15); border:1px solid rgba(201,168,76,.3); border-radius:10px; padding:.5rem 1rem; color:#e8c96a; font-size:.85rem; font-weight:700; position:relative;z-index:2; }

/* Section cards */
.evt-card {
  background:#fff; border-radius:16px;
  border:1px solid #edf0f8;
  box-shadow:0 2px 14px rgba(12,26,53,.07);
  margin-bottom:1.2rem; overflow:visible;
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

/* Event type selector */
.evt-type-grid {
  display:grid; grid-template-columns:repeat(4,1fr); gap:.7rem; margin-bottom:1rem;
}
.evt-type-btn {
  border:2px solid #edf0f8; border-radius:12px; padding:.7rem .5rem;
  background:#fff; cursor:pointer; text-align:center; transition:all .18s;
  font-size:.75rem; font-weight:700; color:#6b7280;
  display:flex; flex-direction:column; align-items:center; gap:.35rem;
}
.evt-type-btn:hover { border-color:#c9a84c; background:#fdf9ee; color:#92640c; transform:translateY(-1px); }
.evt-type-btn.selected { border-color:#c9a84c; background:linear-gradient(135deg,#fdf5e0,#fffbf0); color:#92640c; box-shadow:0 4px 14px rgba(201,168,76,.2); }
.evt-type-btn .evt-type-icon { font-size:1.4rem; }
.evt-type-btn input[type=radio] { display:none; }

/* Summary sidebar */
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
.evt-summary-ref   { color:rgba(201,168,76,.8); font-size:.7rem; margin-top:.15rem; }
.evt-summary-body  { padding:1.1rem 1.3rem; }
.evt-sum-row {
  display:flex; align-items:center; justify-content:space-between;
  padding:.5rem 0; border-bottom:1px solid #f5f7fc; font-size:.8rem;
}
.evt-sum-row:last-child { border-bottom:none; }
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

/* Form improvements */
.form-label { font-size:.78rem; font-weight:700; color:#374151; margin-bottom:.35rem; }
.form-control, .form-select {
  border-radius:9px; border:1.5px solid #e5e7eb; font-size:.83rem;
  padding:.55rem .9rem; transition:border-color .15s, box-shadow .15s;
}
.form-control:focus, .form-select:focus {
  border-color:#c9a84c; box-shadow:0 0 0 3px rgba(201,168,76,.12);
}
.form-required::after { content:' *'; color:#dc2626; }

.addon-row-card {
  background:#f8fafc; border-radius:10px; border:1.5px solid #e5e7eb;
  padding:.7rem .9rem; display:flex; align-items:center; gap:.7rem; margin-bottom:.5rem;
}
.addon-row-card .addon-select { flex:1; }
.addon-row-card .addon-qty { width:70px; }

/* Submit button */
.btn-update-booking {
  background:linear-gradient(135deg,#c9a84c,#e8c96a);
  border:none; color:#fff; border-radius:12px; padding:.75rem 2rem;
  font-size:.9rem; font-weight:800; letter-spacing:.02em;
  box-shadow:0 4px 18px rgba(201,168,76,.4); transition:all .18s; width:100%;
  display:flex; align-items:center; justify-content:center; gap:.5rem;
}
.btn-update-booking:hover { transform:translateY(-2px); box-shadow:0 8px 28px rgba(201,168,76,.5); }
</style>

<!-- Hero -->
<div class="evt-hero">
  <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
    <div>
      <div class="evt-hero-title">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#c9a84c" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        Edit Booking: <?= Helper::sanitize($bk['booking_ref']) ?>
      </div>
      <div class="evt-hero-sub">Modify the booking details and update the record</div>
    </div>
    <div class="evt-hero-date">
      📅 Created: <?= Helper::formatDate($bk['created_at']) ?>
    </div>
  </div>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger d-flex align-items-center gap-2 mb-3" style="border-radius:12px;">
  <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
  <div><ul class="mb-0 ps-3"><?php foreach($errors as $e): ?><li><?= Helper::sanitize($e) ?></li><?php endforeach; ?></ul></div>
</div>
<?php endif; ?>

<form method="post" id="booking-form">
<div class="row g-3">

  <!-- LEFT COLUMN -->
  <div class="col-lg-8">

    <!-- STEP 1: Event Type -->
    <div class="evt-card">
      <div class="evt-card-head">
        <div class="evt-card-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#c9a84c" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        </div>
        <div>
          <div class="evt-card-title">Event Type</div>
          <div class="evt-card-subtitle">Select the type of event</div>
        </div>
      </div>
      <div class="evt-card-body">
        <div class="evt-type-grid">
          <?php
          $typeIcons = ['Wedding'=>'💍','Reception'=>'🥂','Engagement'=>'💑','Birthday'=>'🎂','Corporate'=>'🏢','Conference'=>'🎙️','Other'=>'📋'];
          foreach ($event_types as $et):
            $icon = $typeIcons[$et] ?? '📋';
          ?>
          <label class="evt-type-btn <?= ($_POST['event_type']??'')==$et?'selected':'' ?>">
            <span class="evt-type-icon"><?= $icon ?></span>
            <span><?= $et ?></span>
            <input type="radio" name="event_type" value="<?= $et ?>" <?= ($_POST['event_type']??'')==$et?'checked':'' ?> required>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- STEP 2: Customer & Status -->
    <div class="evt-card">
      <div class="evt-card-head">
        <div class="evt-card-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#c9a84c" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        </div>
        <div>
          <div class="evt-card-title">Customer & Status</div>
          <div class="evt-card-subtitle">Client information and booking status</div>
        </div>
      </div>
      <div class="evt-card-body">
        <div class="row g-3">
          <div class="col-md-8">
            <label class="form-label form-required">Customer</label>
            <select name="customer_id" class="form-select" required>
              <option value="">— Select Customer —</option>
              <?php foreach ($customers as $c): ?>
              <option value="<?= $c['id'] ?>" <?= ($_POST['customer_id']??'')==$c['id']?'selected':'' ?>><?= Helper::sanitize($c['name']) ?> (<?= Helper::sanitize($c['phone']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Booking Status</label>
            <select name="status" class="form-select">
              <?php foreach (['inquiry','booked','confirmed','completed','cancelled'] as $s): ?>
              <option value="<?= $s ?>" <?= ($_POST['status']??'')===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
    </div>

    <!-- STEP 3: Venue & Package -->
    <div class="evt-card">
      <div class="evt-card-head">
        <div class="evt-card-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#c9a84c" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        </div>
        <div>
          <div class="evt-card-title">Venue & Package</div>
          <div class="evt-card-subtitle">Hall selection and service package</div>
        </div>
      </div>
      <div class="evt-card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Hall</label>
            <select name="hall_id" class="form-select">
              <option value="">— No Hall —</option>
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
      </div>
    </div>

    <!-- STEP 4: Schedule -->
    <div class="evt-card">
      <div class="evt-card-head">
        <div class="evt-card-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#c9a84c" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
        </div>
        <div>
          <div class="evt-card-title">Schedule & Timing</div>
          <div class="evt-card-subtitle">Event dates and times</div>
        </div>
      </div>
      <div class="evt-card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label form-required">Start Date</label>
            <input type="date" name="event_date" class="form-control" value="<?= $_POST['event_date']??'' ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">End Date</label>
            <input type="date" name="event_end_date" class="form-control" value="<?= $_POST['event_end_date']??'' ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Guest Count</label>
            <input type="number" name="guest_count" class="form-control" min="0" value="<?= $_POST['guest_count']??'' ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Start Time</label>
            <input type="time" name="event_time" class="form-control" value="<?= $_POST['event_time']??'' ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">End Time</label>
            <input type="time" name="event_end_time" class="form-control" value="<?= $_POST['event_end_time']??'' ?>">
          </div>
        </div>
      </div>
    </div>

    <!-- STEP 5: Add-ons -->
    <div class="evt-card">
      <div class="evt-card-head">
        <div class="evt-card-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#c9a84c" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
        </div>
        <div>
          <div class="evt-card-title">Add-on Services</div>
          <div class="evt-card-subtitle">Extra services and requirements</div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-primary ms-auto" id="add-addon-row">+ Add Service</button>
      </div>
      <div class="evt-card-body">
        <div id="addon-rows">
          <?php
          $ea = $existing_addons;
          if (isset($_POST['addon_id'])) {
              $ea = [];
              foreach ($_POST['addon_id'] as $i => $aid) { $ea[] = ['addon_id'=>$aid,'quantity'=>$_POST['addon_qty'][$i]??1]; }
          }
          foreach ($ea as $ea_row): ?>
          <div class="addon-row-card addon-row">
            <select name="addon_id[]" class="form-select form-select-sm addon-select">
              <option value="">— Select Service —</option>
              <?php foreach ($addons as $a): ?>
              <option value="<?= $a['id'] ?>" data-price="<?= $a['price'] ?>" data-tax="<?= $a['tax_percent'] ?>" <?= ($ea_row['addon_id']??'')==$a['id']?'selected':'' ?>>
                <?= Helper::sanitize($a['name']) ?> (<?= Helper::formatCurrency($a['price']) ?>/<?= $a['unit'] ?>)
              </option>
              <?php endforeach; ?>
            </select>
            <input type="number" name="addon_qty[]" class="form-control form-control-sm addon-qty" min="1" value="<?= (int)($ea_row['quantity']??1) ?>">
            <div class="addon-total fw-bold text-vp-navy" style="min-width:80px;text-align:right;">—</div>
            <button type="button" class="btn btn-sm btn-ghost-danger remove-addon-row">✕</button>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- STEP 6: Notes -->
    <div class="evt-card">
      <div class="evt-card-head">
        <div class="evt-card-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#c9a84c" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
        </div>
        <div>
          <div class="evt-card-title">Additional Notes</div>
          <div class="evt-card-subtitle">Internal notes or special requests</div>
        </div>
      </div>
      <div class="evt-card-body">
        <textarea name="notes" class="form-control" rows="3" placeholder="Enter any special instructions..."><?= Helper::sanitize($_POST['notes']??'') ?></textarea>
      </div>
    </div>

  </div>

  <!-- RIGHT COLUMN: Summary -->
  <div class="col-lg-4">
    <div class="evt-summary">
      <div class="evt-summary-head">
        <div class="evt-summary-title">Booking Summary</div>
        <div class="evt-summary-ref">REF: <?= Helper::sanitize($bk['booking_ref']) ?></div>
      </div>
      <div class="evt-summary-body">
        <div id="base-amount-row">
          <label class="form-label">Base Amount (Manual)</label>
          <input type="number" id="base_amount_input" class="form-control mb-3" step="0.01" min="0" value="<?= number_format((float)($bk['total_amount'] ?? 0), 2, '.', '') ?>" placeholder="0.00">
        </div>

        <div class="evt-sum-row">
          <span class="evt-sum-label">Package Price</span>
          <span class="evt-sum-value" id="sum-package">Rs. 0.00</span>
        </div>
        <div class="evt-sum-row">
          <span class="evt-sum-label">Add-ons Subtotal</span>
          <span class="evt-sum-value" id="sum-addons">Rs. 0.00</span>
        </div>
        <div class="evt-sum-row">
          <span class="evt-sum-label">Tax Amount</span>
          <span class="evt-sum-value" id="sum-tax">Rs. 0.00</span>
        </div>
        <div class="mt-3">
          <label class="form-label">Discount Amount</label>
          <input type="number" name="discount_amount" id="discount_input" class="form-control" step="0.01" min="0" value="<?= $_POST['discount_amount']??$bk['discount_amount'] ?>">
        </div>

        <div class="evt-sum-total">
          <span class="evt-sum-total-label">Final Total</span>
          <span class="evt-sum-total-val" id="sum-total">Rs. 0.00</span>
        </div>

        <input type="hidden" name="total_amount" id="h-total">
        <input type="hidden" name="tax_amount" id="h-tax">

        <div class="mt-4 d-grid gap-2">
          <button type="submit" class="btn-update-booking">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
            Update Booking
          </button>
          <a href="<?= BASE_URL ?>/modules/bookings/view.php?id=<?= $id ?>" class="btn btn-outline-secondary" style="border-radius:12px;padding:.7rem;">Cancel Changes</a>
        </div>
      </div>
    </div>
  </div>

</div>
</form>

<script>
const addonTemplate = `<div class="addon-row-card addon-row">
  <select name="addon_id[]" class="form-select form-select-sm addon-select">
    <option value="">— Select Service —</option>
    <?php foreach ($addons as $a): echo '<option value="'.$a['id'].'" data-price="'.$a['price'].'" data-tax="'.$a['tax_percent'].'">'.htmlspecialchars($a['name']).' ('.Helper::formatCurrency($a['price']).'/'.$a['unit'].')</option>'; endforeach; ?>
  </select>
  <input type="number" name="addon_qty[]" class="form-control form-control-sm addon-qty" min="1" value="1">
  <div class="addon-total fw-bold text-vp-navy" style="min-width:80px;text-align:right;">—</div>
  <button type="button" class="btn btn-sm btn-ghost-danger remove-addon-row">✕</button>
</div>`;

document.getElementById('add-addon-row').addEventListener('click', () => {
  document.getElementById('addon-rows').insertAdjacentHTML('beforeend', addonTemplate);
  bindRow(document.querySelector('#addon-rows .addon-row:last-child'));
  recalc();
});

document.querySelectorAll('.addon-row').forEach(bindRow);

function bindRow(row) {
  row.querySelector('.addon-select').addEventListener('change', recalc);
  row.querySelector('.addon-qty').addEventListener('input', recalc);
  row.querySelector('.remove-addon-row').addEventListener('click', function(){
    this.closest('.addon-row').remove();
    recalc();
  });
}

document.getElementById('pkg_select').addEventListener('change', recalc);
document.getElementById('discount_input').addEventListener('input', recalc);
document.getElementById('base_amount_input').addEventListener('input', recalc);

// Event type radio button selection UI
document.querySelectorAll('.evt-type-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.evt-type-btn').forEach(b => b.classList.remove('selected'));
    this.classList.add('selected');
  });
});

function recalc() {
  const pkgSelect = document.getElementById('pkg_select');
  const pkgOpt = pkgSelect.options[pkgSelect.selectedIndex];
  const pkgPrice = parseFloat(pkgOpt.dataset.price)||0;
  const baseAmount = parseFloat(document.getElementById('base_amount_input').value)||0;
  const effectivePkgPrice = pkgPrice > 0 ? pkgPrice : baseAmount;

  document.getElementById('base-amount-row').style.display = pkgPrice > 0 ? 'none' : '';
  document.getElementById('sum-package').textContent = 'Rs. '+(pkgPrice > 0 ? pkgPrice : 0).toFixed(2);

  let addonSub=0, addonTax=0;
  document.querySelectorAll('.addon-row').forEach(row=>{
    const sel=row.querySelector('.addon-select');
    const opt=sel.options[sel.selectedIndex];
    const price=parseFloat(opt.dataset.price)||0;
    const tax=parseFloat(opt.dataset.tax)||0;
    const qty=parseInt(row.querySelector('.addon-qty').value)||1;
    const total=price*qty;
    addonSub+=total;
    addonTax+=total*tax/100;
    row.querySelector('.addon-total').textContent=total>0?'Rs. '+total.toFixed(2):'—';
  });

  const subtotal = effectivePkgPrice + addonSub;
  const tax = addonTax;
  const discount = parseFloat(document.getElementById('discount_input').value)||0;
  const total = Math.max(0, subtotal + tax - discount);

  document.getElementById('sum-addons').textContent='Rs. '+addonSub.toFixed(2);
  document.getElementById('sum-tax').textContent='Rs. '+tax.toFixed(2);
  document.getElementById('sum-total').textContent='Rs. '+total.toFixed(2);

  document.getElementById('h-total').value=subtotal.toFixed(2);
  document.getElementById('h-tax').value=tax.toFixed(2);
}

recalc();
</script>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
