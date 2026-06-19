<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
$db = Database::getInstance();
$cu = Auth::currentUser();

$customers   = $db->fetchAll("SELECT id,name,mobile,email,bride_name,groom_name FROM customers" . ($cu['branch_id'] ? " WHERE branch_id={$cu['branch_id']}" : "") . " ORDER BY name");
$halls       = $db->fetchAll("SELECT id,name,capacity,price_per_day FROM halls WHERE is_active=1" . ($cu['branch_id'] ? " AND branch_id={$cu['branch_id']}" : "") . " ORDER BY name");
$packages    = $db->fetchAll("SELECT id,name,price FROM packages WHERE is_active=1" . ($cu['branch_id'] ? " AND (branch_id={$cu['branch_id']} OR branch_id IS NULL)" : "") . " ORDER BY name");
$addons      = $db->fetchAll("SELECT id,name,price,unit,tax_percent FROM addons WHERE is_available=1" . ($cu['branch_id'] ? " AND (branch_id={$cu['branch_id']} OR branch_id IS NULL)" : "") . " ORDER BY name");
$branches    = $db->fetchAll("SELECT id,name FROM branches WHERE is_active=1");
$event_types = ['Wedding','Engagement','Wedding Reception','Birthday','Corporate','Conference','Get Together','Other'];
$errors = [];

// Pre-fill date from calendar click
$prefillDate = $_GET['date'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branch_id      = $cu['branch_id'] ?? (int)($_POST['branch_id'] ?? 1);
    $customer_id    = (int)($_POST['customer_id'] ?? 0);
    $hall_id        = (int)($_POST['hall_id'] ?? 0) ?: null;
    $package_id     = (int)($_POST['package_id'] ?? 0) ?: null;
    $event_type     = trim($_POST['event_type'] ?? '');
    $event_date     = trim($_POST['event_date'] ?? '');
    $event_end_date = trim($_POST['event_end_date'] ?? '') ?: null;
    $event_time     = trim($_POST['event_time'] ?? '') ?: null;
    $event_end_time = trim($_POST['event_end_time'] ?? '') ?: null;
    $guest_count    = (int)($_POST['guest_count'] ?? 0);
    $status         = $_POST['status'] ?? 'inquiry';
    $notes          = trim($_POST['notes'] ?? '');
    $discount       = (float)($_POST['discount_amount'] ?? 0);
    $groom_name     = trim($_POST['groom_name'] ?? '');
    $bride_name     = trim($_POST['bride_name'] ?? '');
    $hero_name      = trim($_POST['hero_name'] ?? '');

    if (!$customer_id) $errors[] = 'Customer is required.';
    if (!$event_date)  $errors[] = 'Event date is required.';
    if (!$event_type)  $errors[] = 'Event type is required.';

    // Conflict check
    if ($hall_id && $event_date && !$errors) {
        $conflict = $db->fetchOne(
            "SELECT id,booking_ref FROM bookings WHERE hall_id=? AND status NOT IN ('cancelled') AND event_date=?",
            [$hall_id, $event_date]
        );
        if ($conflict) $errors[] = "Hall conflict: {$conflict['booking_ref']} already booked on $event_date.";
    }

    if (!$errors) {
        $subtotal = (float)($_POST['total_amount'] ?? 0);
        $tax      = (float)($_POST['tax_amount'] ?? 0);
        $final    = $subtotal + $tax - $discount;
        $booking_ref = Helper::generateRef('BK', 'bookings', 'booking_ref');

        $booking_id = $db->insert(
            "INSERT INTO bookings (booking_ref,branch_id,customer_id,hall_id,package_id,event_type,bride_name,groom_name,hero_name,event_date,event_end_date,event_time,event_end_time,guest_count,status,notes,total_amount,discount_amount,tax_amount,final_amount,paid_amount,balance_amount,created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,?,?)",
            [$booking_ref,$branch_id,$customer_id,$hall_id,$package_id,$event_type,$bride_name,$groom_name,$hero_name,$event_date,$event_end_date,$event_time,$event_end_time,$guest_count,$status,$notes,$subtotal,$discount,$tax,$final,$final,$cu['id']]
        );

        // Save add-ons
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
                    [$booking_id,$aid,$adrow['name'],$qty,$adrow['price'],$adrow['tax_percent'],$adrow['price']*$qty]
                );
            }
        }

        Helper::flash('success', "Booking $booking_ref created successfully.");
        Helper::redirect(BASE_URL.'/modules/bookings/view.php?id='.$booking_id);
    }
}

$pageTitle = 'New Event';
$breadcrumbs = [['label'=>'Bookings','url'=>BASE_URL.'/modules/bookings/index.php'],['label'=>'New Event']];
require_once ROOT_PATH . '/includes/header.php';
?>

<!-- Tom Select -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>

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

/* Step wizard */
.evt-steps {
  display:flex; align-items:center; gap:0; margin-bottom:1.8rem; flex-wrap:nowrap;
}
.evt-step {
  display:flex; align-items:center; gap:.5rem; flex:1;
  position:relative;
}
.evt-step:not(:last-child)::after {
  content:''; position:absolute; right:-1px; top:50%; transform:translateY(-50%);
  width:calc(100% - 110px); height:2px;
  background: linear-gradient(90deg,#e8c96a,#e5e7eb);
  z-index:0; left:calc(50% + 22px);
}
.evt-step-num {
  width:38px; height:38px; border-radius:50%; flex-shrink:0; z-index:1;
  display:flex; align-items:center; justify-content:center;
  font-size:.82rem; font-weight:800; transition:all .2s;
}
.evt-step.active .evt-step-num  { background:linear-gradient(135deg,#c9a84c,#e8c96a); color:#fff; box-shadow:0 4px 14px rgba(201,168,76,.45); }
.evt-step.done   .evt-step-num  { background:#059669; color:#fff; }
.evt-step.pending .evt-step-num { background:#f1f4fa; color:#9ca3af; border:2px solid #e5e7eb; }
.evt-step-info { }
.evt-step-label { font-size:.72rem; font-weight:700; color:#0c1a35; }
.evt-step-sub   { font-size:.65rem; color:#9ca3af; }
.evt-step.pending .evt-step-label { color:#9ca3af; }

/* Section cards */
.evt-card {
  background:#fff; border-radius:16px;
  border:1px solid #edf0f8;
  box-shadow:0 2px 14px rgba(12,26,53,.07);
  margin-bottom:1.2rem; overflow:visible;
  position:relative; z-index:1;
}
/* Tom Select dropdown fix — must not be clipped */
.ts-wrapper { position:relative; z-index:100; }
.ts-dropdown { z-index:99999 !important; border-radius:12px !important; border:1.5px solid #e5e7eb !important; box-shadow:0 8px 32px rgba(12,26,53,.14) !important; overflow:hidden; }
.ts-dropdown .ts-dropdown-content { max-height:240px; }
.ts-dropdown .option { padding:.45rem .75rem !important; font-size:.82rem; }
.ts-control { border-radius:9px !important; border:1.5px solid #e5e7eb !important; padding:.55rem .9rem !important; font-size:.83rem !important; min-height:unset !important; }
.ts-control:focus-within, .ts-wrapper.focus .ts-control { border-color:#c9a84c !important; box-shadow:0 0 0 3px rgba(201,168,76,.12) !important; }
.ts-wrapper .ts-control input { font-size:.83rem !important; }
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

/* Dynamic name section */
.evt-names-panel {
  background: linear-gradient(135deg,#fdf9ee,#fffbf5);
  border:1px solid rgba(201,168,76,.2);
  border-radius:12px; padding:1.1rem 1.3rem; margin-top:.5rem;
  display:none;
}
.evt-names-panel.show { display:block; animation:fadeIn .25s ease; }
@keyframes fadeIn { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:translateY(0)} }
.evt-names-panel-title { font-size:.75rem; font-weight:800; color:#92640c; text-transform:uppercase; letter-spacing:.07em; margin-bottom:.9rem; display:flex; align-items:center; gap:.4rem; }

/* Customer card in selector */
.customer-card-preview {
  background:linear-gradient(135deg,#f0f7ff,#e8f0fe);
  border:1px solid #c7d9f5; border-radius:12px; padding:.9rem 1.1rem;
  display:none; margin-top:.7rem; align-items:center; gap:.9rem;
}
.customer-card-preview.show { display:flex; }
.cust-avatar {
  width:42px; height:42px; border-radius:50%; flex-shrink:0;
  background:linear-gradient(135deg,#0c1a35,#1a3060);
  display:flex; align-items:center; justify-content:center;
  font-size:1rem; color:#fff; font-weight:800;
}
.cust-info-name  { font-size:.85rem; font-weight:700; color:#0c1a35; }
.cust-info-meta  { font-size:.72rem; color:#6b7280; margin-top:.15rem; }
.cust-info-badge { display:inline-flex; align-items:center; gap:.25rem; font-size:.65rem; font-weight:700; padding:.15rem .5rem; border-radius:20px; background:#dcfce7; color:#059669; }

/* Add customer modal trigger */
.btn-add-cust-modal {
  border:2px dashed #c9a84c; color:#92640c; background:#fdf9ee;
  border-radius:10px; padding:.45rem 1rem; font-size:.78rem; font-weight:700;
  cursor:pointer; transition:all .15s; text-decoration:none; display:inline-flex; align-items:center; gap:.4rem;
}
.btn-add-cust-modal:hover { background:#fdf0c8; border-color:#a07820; color:#7a5210; }

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

/* Hall availability chip */
.hall-avail-chip {
  display:inline-flex; align-items:center; gap:.3rem;
  font-size:.7rem; font-weight:700; padding:.2rem .6rem; border-radius:20px; margin-top:.4rem;
}
.chip-avail     { background:#dcfce7; color:#059669; }
.chip-conflict  { background:#fee2e2; color:#dc2626; }

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
.input-icon-wrap { position:relative; }
.input-icon-wrap .form-control { padding-left:2.5rem; }
.input-icon-wrap .input-icon {
  position:absolute; left:.75rem; top:50%; transform:translateY(-50%);
  color:#9ca3af; width:16px; height:16px;
}
.addon-row-card {
  background:#f8fafc; border-radius:10px; border:1.5px solid #e5e7eb;
  padding:.7rem .9rem; display:flex; align-items:center; gap:.7rem; margin-bottom:.5rem;
}
.addon-row-card .addon-select { flex:1; }
.addon-row-card .addon-qty { width:70px; }

/* Submit button */
.btn-create-booking {
  background:linear-gradient(135deg,#c9a84c,#e8c96a);
  border:none; color:#fff; border-radius:12px; padding:.75rem 2rem;
  font-size:.9rem; font-weight:800; letter-spacing:.02em;
  box-shadow:0 4px 18px rgba(201,168,76,.4); transition:all .18s; width:100%;
  display:flex; align-items:center; justify-content:center; gap:.5rem;
}
.btn-create-booking:hover { transform:translateY(-2px); box-shadow:0 8px 28px rgba(201,168,76,.5); }

/* New Customer Modal */
.modal-header-vp { background:linear-gradient(135deg,#0c1a35,#1a3060); color:#fff; border:none; }
.modal-header-vp .btn-close { filter:invert(1); }
.modal-footer-vp { border-top:1px solid #f1f4fa; padding:1rem 1.4rem; }

@media(max-width:768px){
  .evt-type-grid { grid-template-columns:repeat(2,1fr); }
  .evt-steps { gap:.3rem; }
  .evt-step:not(:last-child)::after { display:none; }
}
</style>

<?php
$prefillDateVal = $prefillDate ?: ($_POST['event_date'] ?? '');
$evtType = $_POST['event_type'] ?? '';
?>

<!-- Hero -->
<div class="evt-hero">
  <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
    <div>
      <div class="evt-hero-title">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#c9a84c" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
        New Event Registration
      </div>
      <div class="evt-hero-sub">Fill in the details below to register a new event booking</div>
    </div>
    <?php if ($prefillDate): ?>
    <div class="evt-hero-date">
      📅 <?= date('l, d F Y', strtotime($prefillDate)) ?>
    </div>
    <?php endif; ?>
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

  <!-- ── LEFT COLUMN ─────────────────────────────── -->
  <div class="col-lg-8">

    <!-- STEP 1: Event Type -->
    <div class="evt-card">
      <div class="evt-card-head">
        <div class="evt-card-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#c9a84c" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        </div>
        <div>
          <div class="evt-card-title">Event Type</div>
          <div class="evt-card-subtitle">Select the type of event to register</div>
        </div>
      </div>
      <div class="evt-card-body">
        <div class="evt-type-grid">
          <?php
          $typeIcons = ['Wedding'=>'💍','Engagement'=>'💑','Wedding Reception'=>'🥂','Birthday'=>'🎂','Corporate'=>'🏢','Conference'=>'🎙️','Get Together'=>'🎊','Other'=>'📋'];
          foreach ($event_types as $et):
            $icon = $typeIcons[$et] ?? '📋';
          ?>
          <label class="evt-type-btn <?= $evtType===$et?'selected':'' ?>" onclick="selectEventType('<?= htmlspecialchars($et) ?>')">
            <input type="radio" name="event_type" value="<?= $et ?>" <?= $evtType===$et?'checked':'' ?>>
            <span class="evt-type-icon"><?= $icon ?></span>
            <span><?= $et ?></span>
          </label>
          <?php endforeach; ?>
        </div>

        <!-- Dynamic names panel -->
        <div class="evt-names-panel <?= in_array($evtType,['Wedding','Engagement','Wedding Reception'])?'show':'' ?>" id="wedding-names-panel">
          <div class="evt-names-panel-title">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            Couple Details <span style="font-weight:500;color:#b08030;">(optional)</span>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">👰 Bride's Name</label>
              <input type="text" name="bride_name" class="form-control" placeholder="Enter bride's full name" value="<?= htmlspecialchars($_POST['bride_name']??'') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">🤵 Groom's Name</label>
              <input type="text" name="groom_name" class="form-control" placeholder="Enter groom's full name" value="<?= htmlspecialchars($_POST['groom_name']??'') ?>">
            </div>
          </div>
        </div>

        <div class="evt-names-panel <?= !in_array($evtType,['Wedding','Engagement','Wedding Reception']) && $evtType ? 'show' : '' ?>" id="hero-name-panel">
          <div class="evt-names-panel-title">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            Event Honoree <span style="font-weight:500;color:#b08030;">(optional)</span>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" id="hero-name-label">🎉 Name</label>
              <input type="text" name="hero_name" class="form-control" placeholder="E.g. Birthday person's name" value="<?= htmlspecialchars($_POST['hero_name']??'') ?>">
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- STEP 2: Date & Venue -->
    <div class="evt-card">
      <div class="evt-card-head">
        <div class="evt-card-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#c9a84c" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
        </div>
        <div>
          <div class="evt-card-title">Date & Venue</div>
          <div class="evt-card-subtitle">When and where is the event?</div>
        </div>
      </div>
      <div class="evt-card-body">
        <div class="row g-3 mb-3">
          <div class="col-md-4">
            <label class="form-label form-required">Event Date</label>
            <div class="input-icon-wrap">
              <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
              <input type="date" name="event_date" id="event_date" class="form-control" value="<?= htmlspecialchars($prefillDateVal) ?>" required onchange="checkHallConflict()">
            </div>
          </div>
          <div class="col-md-4">
            <label class="form-label">End Date <span style="color:#9ca3af;font-weight:400;">(multi-day)</span></label>
            <input type="date" name="event_end_date" class="form-control" value="<?= htmlspecialchars($_POST['event_end_date']??'') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Guest Count</label>
            <div class="input-icon-wrap">
              <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
              <input type="number" name="guest_count" class="form-control" min="0" value="<?= intval($_POST['guest_count']??'') ?>" placeholder="e.g. 200">
            </div>
          </div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-3">
            <label class="form-label">Start Time</label>
            <input type="time" name="event_time" class="form-control" value="<?= htmlspecialchars($_POST['event_time']??'') ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">End Time</label>
            <input type="time" name="event_end_time" class="form-control" value="<?= htmlspecialchars($_POST['event_end_time']??'') ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <?php foreach (['inquiry'=>'Inquiry','tentative'=>'Tentative','confirmed'=>'Confirmed'] as $sv=>$sl): ?>
              <option value="<?= $sv ?>" <?= ($_POST['status']??'inquiry')===$sv?'selected':'' ?>><?= $sl ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php if (Auth::isSuperAdmin()): ?>
          <div class="col-md-3">
            <label class="form-label">Branch</label>
            <select name="branch_id" class="form-select">
              <?php foreach ($branches as $b): ?>
              <option value="<?= $b['id'] ?>" <?= ($cu['branch_id']??$b['id'])==$b['id']?'selected':'' ?>><?= Helper::sanitize($b['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>
        </div>

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Hall / Venue</label>
            <select name="hall_id" class="form-select" id="hall_select" onchange="checkHallConflict()">
              <option value="">— Select Hall —</option>
              <?php foreach ($halls as $h): ?>
              <option value="<?= $h['id'] ?>" data-capacity="<?= $h['capacity'] ?>" data-price="<?= $h['price_per_day'] ?>" <?= ($_POST['hall_id']??'')==$h['id']?'selected':'' ?>>
                <?= Helper::sanitize($h['name']) ?> (cap. <?= number_format($h['capacity']) ?>)
              </option>
              <?php endforeach; ?>
            </select>
            <div id="hall-avail-status"></div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Package</label>
            <select name="package_id" class="form-select" id="pkg_select">
              <option value="">— No Package —</option>
              <?php foreach ($packages as $p): ?>
              <option value="<?= $p['id'] ?>" data-price="<?= $p['price'] ?>" <?= ($_POST['package_id']??'')==$p['id']?'selected':'' ?>>
                <?= Helper::sanitize($p['name']) ?> (<?= Helper::formatCurrency($p['price']) ?>)
              </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
    </div>

    <!-- STEP 3: Customer -->
    <div class="evt-card">
      <div class="evt-card-head">
        <div class="evt-card-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#c9a84c" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        </div>
        <div>
          <div class="evt-card-title">Customer</div>
          <div class="evt-card-subtitle">Who is booking this event?</div>
        </div>
      </div>
      <div class="evt-card-body">
        <div class="row g-3 align-items-end">
          <div class="col">
            <label class="form-label form-required">Select Customer</label>
            <select name="customer_id" id="customer_select" required>
              <option value="">— Search or select customer —</option>
              <?php foreach ($customers as $c): ?>
              <option value="<?= $c['id'] ?>"
                data-phone="<?= htmlspecialchars($c['mobile']) ?>"
                data-email="<?= htmlspecialchars($c['email']??'') ?>"
                data-bride="<?= htmlspecialchars($c['bride_name']??'') ?>"
                data-groom="<?= htmlspecialchars($c['groom_name']??'') ?>"
                <?= ($_POST['customer_id']??'')==$c['id']?'selected':'' ?>>
                <?= Helper::sanitize($c['name']) ?> · <?= Helper::sanitize($c['mobile']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-auto">
            <button type="button" class="btn-add-cust-modal" onclick="openNewCustomerModal()">
              <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
              New Customer
            </button>
          </div>
        </div>

        <!-- Customer preview card -->
        <div class="customer-card-preview" id="cust-preview">
          <div class="cust-avatar" id="cust-avatar-init"></div>
          <div class="cust-info">
            <div class="cust-info-name" id="cust-preview-name"></div>
            <div class="cust-info-meta" id="cust-preview-meta"></div>
          </div>
          <div class="ms-auto"><span class="cust-info-badge">✓ Selected</span></div>
        </div>
      </div>
    </div>

    <!-- STEP 4: Add-ons -->
    <div class="evt-card">
      <div class="evt-card-head">
        <div class="evt-card-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#c9a84c" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
        </div>
        <div>
          <div class="evt-card-title">Add-on Services</div>
          <div class="evt-card-subtitle">Extras to include in this booking</div>
        </div>
        <button type="button" class="btn btn-sm ms-auto" onclick="addAddonRow()" style="background:#f1f4fa;border-radius:8px;font-size:.75rem;font-weight:700;color:#374151;border:1.5px solid #e5e7eb;">
          + Add Service
        </button>
      </div>
      <div class="evt-card-body" id="addon-rows-wrap">
        <?php
        $post_addon_ids  = $_POST['addon_id'] ?? [];
        $post_addon_qtys = $_POST['addon_qty'] ?? [];
        if (empty($post_addon_ids)):
        ?>
        <div class="addon-row-card" id="addon-empty-state">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="1.5"><path d="M12 5v14M5 12h14"/></svg>
          <span style="font-size:.8rem;color:#9ca3af;">No add-ons selected yet. Click "+ Add Service" to include extras.</span>
        </div>
        <?php else: ?>
        <?php foreach ($post_addon_ids as $i => $paid): ?>
        <?php include_once(''); // will be rendered by addAddonRow JS ?>
        <div class="addon-row-card">
          <select name="addon_id[]" class="form-select form-select-sm addon-select" onchange="recalc()">
            <option value="">— Select Service —</option>
            <?php foreach ($addons as $a): ?>
            <option value="<?= $a['id'] ?>" data-price="<?= $a['price'] ?>" data-tax="<?= $a['tax_percent'] ?>" <?= $paid==$a['id']?'selected':'' ?>>
              <?= Helper::sanitize($a['name']) ?> (<?= Helper::formatCurrency($a['price']) ?>/<?= $a['unit'] ?>)
            </option>
            <?php endforeach; ?>
          </select>
          <input type="number" name="addon_qty[]" class="form-control form-control-sm addon-qty" min="1" value="<?= (int)($post_addon_qtys[$i]??1) ?>" style="width:70px;" oninput="recalc()">
          <span class="addon-total-price" style="font-size:.8rem;font-weight:700;color:#0c1a35;width:90px;text-align:right;">—</span>
          <button type="button" onclick="this.closest('.addon-row-card').remove();recalc();" style="background:none;border:none;color:#dc2626;font-size:1.1rem;cursor:pointer;padding:0 .2rem;">×</button>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Notes -->
    <div class="evt-card">
      <div class="evt-card-head">
        <div class="evt-card-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#c9a84c" stroke-width="2"><path d="M14 3v4a1 1 0 001 1h4"/><path d="M17 21H7a2 2 0 01-2-2V5a2 2 0 012-2h7l5 5v11a2 2 0 01-2 2z"/><path d="M9 9h1M9 13h6M9 17h6"/></svg>
        </div>
        <div>
          <div class="evt-card-title">Additional Notes</div>
          <div class="evt-card-subtitle">Special requirements, requests</div>
        </div>
      </div>
      <div class="evt-card-body">
        <textarea name="notes" class="form-control" rows="3" placeholder="Any special requirements, dietary needs, decorations, or important notes about this event..."><?= htmlspecialchars($_POST['notes']??'') ?></textarea>
      </div>
    </div>

  </div><!-- /col-lg-8 -->

  <!-- ── RIGHT: SUMMARY ──────────────────────────── -->
  <div class="col-lg-4">
    <div class="evt-summary">
      <div class="evt-summary-head">
        <div class="evt-summary-title">Booking Summary</div>
        <div class="evt-summary-ref">Auto-assigned on save</div>
      </div>
      <div class="evt-summary-body">
        <div class="evt-sum-row">
          <span class="evt-sum-label">Event Type</span>
          <span class="evt-sum-value" id="sum-evt-type">—</span>
        </div>
        <div class="evt-sum-row">
          <span class="evt-sum-label">Date</span>
          <span class="evt-sum-value" id="sum-date">—</span>
        </div>
        <div class="evt-sum-row">
          <span class="evt-sum-label">Hall</span>
          <span class="evt-sum-value" id="sum-hall">—</span>
        </div>
        <div class="evt-sum-row">
          <span class="evt-sum-label">Hall Rate</span>
          <span class="evt-sum-value" id="sum-hall-price">—</span>
        </div>
        <div class="evt-sum-row">
          <span class="evt-sum-label">Package</span>
          <span class="evt-sum-value" id="sum-pkg">—</span>
        </div>
        <div class="evt-sum-row">
          <span class="evt-sum-label">Add-ons</span>
          <span class="evt-sum-value" id="sum-addons">Rs. 0.00</span>
        </div>
        <div class="evt-sum-row">
          <span class="evt-sum-label">Tax</span>
          <span class="evt-sum-value" id="sum-tax">Rs. 0.00</span>
        </div>
        <div class="evt-sum-row">
          <span class="evt-sum-label">Discount (Rs.)</span>
          <span class="evt-sum-value">
            <input type="number" name="discount_amount" id="discount_input" class="form-control form-control-sm" step="0.01" min="0" value="<?= $_POST['discount_amount']??'0' ?>" style="width:90px;text-align:right;" oninput="recalc()">
          </span>
        </div>

        <div class="evt-sum-total">
          <span class="evt-sum-total-label">TOTAL</span>
          <span class="evt-sum-total-val" id="sum-total">Rs. 0.00</span>
        </div>

        <input type="hidden" name="total_amount" id="h-total">
        <input type="hidden" name="tax_amount"   id="h-tax">

        <button type="submit" class="btn-create-booking mt-3">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12l5 5L20 7"/></svg>
          Create Booking
        </button>
        <a href="<?= BASE_URL ?>/modules/bookings/index.php" class="btn w-100 mt-2" style="border:1.5px solid #e5e7eb;border-radius:10px;font-size:.8rem;font-weight:700;color:#6b7280;">
          Cancel
        </a>
      </div>
    </div>
  </div>

</div><!-- /row -->
</form>

<!-- ══ NEW CUSTOMER MODAL ══════════════════════════════════════════════ -->
<div class="modal fade" id="newCustomerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content" style="border-radius:18px;border:none;overflow:hidden;box-shadow:0 24px 64px rgba(0,0,0,.2);">
      <div class="modal-header modal-header-vp">
        <div>
          <h5 class="modal-title" style="font-weight:800;margin:0;">Add New Customer</h5>
          <div style="font-size:.72rem;color:rgba(255,255,255,.5);margin-top:.15rem;">New customer will be saved and auto-selected</div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <div id="modal-errors"></div>
        <div class="row g-3">
          <div class="col-12">
            <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.8rem;">
              <div style="width:4px;height:16px;background:linear-gradient(180deg,#c9a84c,#e8c96a);border-radius:2px;"></div>
              <span style="font-size:.72rem;font-weight:800;color:#6b7280;text-transform:uppercase;letter-spacing:.08em;">Personal Information</span>
            </div>
          </div>
          <div class="col-md-5">
            <label class="form-label form-required">Full Name</label>
            <input type="text" id="nc_name" class="form-control" placeholder="e.g. Kasun Perera">
          </div>
          <div class="col-md-4">
            <label class="form-label">NIC Number</label>
            <input type="text" id="nc_nic" class="form-control" placeholder="e.g. 982345678V">
          </div>
          <div class="col-md-3">
            <label class="form-label">City</label>
            <input type="text" id="nc_city" class="form-control" placeholder="e.g. Colombo">
          </div>
          <div class="col-12">
            <div style="display:flex;align-items:center;gap:.5rem;margin:.3rem 0 .8rem;">
              <div style="width:4px;height:16px;background:linear-gradient(180deg,#c9a84c,#e8c96a);border-radius:2px;"></div>
              <span style="font-size:.72rem;font-weight:800;color:#6b7280;text-transform:uppercase;letter-spacing:.08em;">Contact Information</span>
            </div>
          </div>
          <div class="col-md-4">
            <label class="form-label form-required">Mobile</label>
            <div class="input-group">
              <span class="input-group-text" style="border-radius:9px 0 0 9px;border:1.5px solid #e5e7eb;border-right:none;background:#f8fafc;font-size:.8rem;color:#6b7280;">🇱🇰</span>
              <input type="tel" id="nc_mobile" class="form-control" placeholder="07X XXX XXXX" style="border-radius:0 9px 9px 0;">
            </div>
          </div>
          <div class="col-md-4">
            <label class="form-label">Mobile 2</label>
            <input type="tel" id="nc_mobile2" class="form-control" placeholder="Alternate number">
          </div>
          <div class="col-md-4">
            <label class="form-label">Email</label>
            <input type="email" id="nc_email" class="form-control" placeholder="email@example.com">
          </div>
          <div class="col-12">
            <label class="form-label">Address</label>
            <textarea id="nc_address" class="form-control" rows="2" placeholder="Full address..."></textarea>
          </div>
          <div class="col-12">
            <div style="background:linear-gradient(135deg,#fdf9ee,#fffbf5);border:1px solid rgba(201,168,76,.2);border-radius:12px;padding:1rem 1.2rem;margin-top:.5rem;">
              <div style="font-size:.72rem;font-weight:800;color:#92640c;text-transform:uppercase;letter-spacing:.07em;margin-bottom:.9rem;">💍 Wedding Details <span style="font-weight:500;color:#b08030;">(optional)</span></div>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">👰 Bride's Name</label>
                  <input type="text" id="nc_bride" class="form-control" placeholder="Enter bride's full name">
                </div>
                <div class="col-md-6">
                  <label class="form-label">🤵 Groom's Name</label>
                  <input type="text" id="nc_groom" class="form-control" placeholder="Enter groom's full name">
                </div>
              </div>
            </div>
          </div>
          <div class="col-12">
            <label class="form-label">Notes</label>
            <textarea id="nc_notes" class="form-control" rows="2" placeholder="Any important notes about this customer..."></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer modal-footer-vp">
        <button type="button" class="btn" data-bs-dismiss="modal" style="border:1.5px solid #e5e7eb;border-radius:9px;font-weight:700;color:#6b7280;">Cancel</button>
        <button type="button" class="btn-create-booking" style="width:auto;padding:.6rem 1.8rem;" onclick="saveNewCustomer()">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12l5 5L20 7"/></svg>
          Save Customer
        </button>
      </div>
    </div>
  </div>
</div>

<script>
// ── Event Type Logic ──────────────────────────────────────────
const weddingTypes = ['Wedding','Engagement','Wedding Reception'];

function selectEventType(type) {
  document.querySelectorAll('.evt-type-btn').forEach(btn => {
    const radio = btn.querySelector('input[type=radio]');
    btn.classList.toggle('selected', radio.value === type);
    if (radio.value === type) radio.checked = true;
  });

  var wPanel = document.getElementById('wedding-names-panel');
  var hPanel = document.getElementById('hero-name-panel');
  var heroLabel = document.getElementById('hero-name-label');

  if (weddingTypes.includes(type)) {
    wPanel.classList.add('show'); hPanel.classList.remove('show');
  } else if (type) {
    wPanel.classList.remove('show'); hPanel.classList.add('show');
    var labels = { Birthday:'🎂 Birthday Person', Corporate:'🏢 Company / Organizer', Conference:'🎙️ Speaker / Organizer', 'Get Together':'🎊 Organizer Name', Other:'🎉 Honoree Name' };
    heroLabel.textContent = labels[type] || '🎉 Name';
  } else {
    wPanel.classList.remove('show'); hPanel.classList.remove('show');
  }

  document.getElementById('sum-evt-type').textContent = type || '—';
  recalc();
}

// Init on load
var initType = '<?= htmlspecialchars($evtType) ?>';
if (initType) selectEventType(initType);

// ── Customer Preview ──────────────────────────────────────────
function showCustomerPreview(sel) {
  var opt = sel.options[sel.selectedIndex];
  var preview = document.getElementById('cust-preview');
  if (!opt.value) { preview.classList.remove('show'); return; }
  document.getElementById('cust-avatar-init').textContent = opt.text.trim().charAt(0).toUpperCase();
  document.getElementById('cust-preview-name').textContent = opt.text.split('·')[0].trim();
  var meta = opt.dataset.phone || '';
  if (opt.dataset.email) meta += ' · ' + opt.dataset.email;
  if (opt.dataset.bride) meta += ' | 👰 ' + opt.dataset.bride;
  if (opt.dataset.groom) meta += ' 🤵 ' + opt.dataset.groom;
  document.getElementById('cust-preview-meta').textContent = meta;
  preview.classList.add('show');
}
// Init customer preview
var cs = document.getElementById('customer_select');
if (cs && cs.value) showCustomerPreview(cs);

// ── Hall Availability ─────────────────────────────────────────
function checkHallConflict() {
  var hallId    = document.getElementById('hall_select').value;
  var date      = document.getElementById('event_date').value;
  var startTime = document.querySelector('[name=event_time]')?.value || '';
  var endTime   = document.querySelector('[name=event_end_time]')?.value || '';
  var statusEl  = document.getElementById('hall-avail-status');
  if (!hallId || !date) { statusEl.innerHTML = ''; return; }
  var url = '<?= BASE_URL ?>/modules/bookings/check_hall.php?hall_id='+hallId+'&date='+date;
  if (startTime) url += '&start_time='+encodeURIComponent(startTime);
  if (endTime)   url += '&end_time='+encodeURIComponent(endTime);
  fetch(url)
    .then(r=>r.json()).then(data=>{
      if (data.conflict) {
        statusEl.innerHTML = '<div class="hall-avail-chip chip-conflict">⚠ Conflict: '+data.ref+'</div>';
      } else {
        statusEl.innerHTML = '<div class="hall-avail-chip chip-avail">✓ Available on '+date+(startTime?' at '+startTime:'')+'</div>';
      }
    }).catch(()=>{ statusEl.innerHTML = ''; });
}

// ── Add-on Row ────────────────────────────────────────────────
var addonOptionsHtml = `<?php foreach ($addons as $a): ?><option value="<?= $a['id'] ?>" data-price="<?= $a['price'] ?>" data-tax="<?= $a['tax_percent'] ?>"><?= htmlspecialchars($a['name']) ?> (<?= Helper::formatCurrency($a['price']) ?>/<?= $a['unit'] ?>)</option><?php endforeach; ?>`;

function addAddonRow() {
  document.getElementById('addon-empty-state')?.remove();
  var wrap = document.getElementById('addon-rows-wrap');
  var row = document.createElement('div');
  row.className = 'addon-row-card';
  row.innerHTML = `
    <select name="addon_id[]" class="form-select form-select-sm addon-select" onchange="recalc()">
      <option value="">— Select Service —</option>${addonOptionsHtml}
    </select>
    <input type="number" name="addon_qty[]" class="form-control form-control-sm addon-qty" min="1" value="1" style="width:70px;" oninput="recalc()">
    <span class="addon-total-price" style="font-size:.8rem;font-weight:700;color:#0c1a35;width:90px;text-align:right;">—</span>
    <button type="button" onclick="this.closest('.addon-row-card').remove();recalc();" style="background:none;border:none;color:#dc2626;font-size:1.1rem;cursor:pointer;padding:0 .2rem;">×</button>`;
  wrap.appendChild(row);
  recalc();
}

// ── Recalc Summary ────────────────────────────────────────────
function recalc() {
  // Hall
  var hallSel = document.getElementById('hall_select');
  var hallOpt = hallSel.options[hallSel.selectedIndex];
  var hallPrice = parseFloat(hallOpt?.dataset.price) || 0;
  document.getElementById('sum-hall-price').textContent = hallPrice > 0 ? 'Rs. '+hallPrice.toFixed(2) : '—';

  // Package
  var pkgSel = document.getElementById('pkg_select');
  var pkgOpt = pkgSel.options[pkgSel.selectedIndex];
  var pkgPrice = parseFloat(pkgOpt?.dataset.price) || 0;
  document.getElementById('sum-pkg').textContent = pkgPrice > 0 ? 'Rs. '+pkgPrice.toFixed(2) : '—';

  // Add-ons
  var addonSub = 0, addonTax = 0;
  document.querySelectorAll('.addon-row-card').forEach(function(row) {
    var sel = row.querySelector('.addon-select');
    if (!sel) return;
    var opt = sel.options[sel.selectedIndex];
    var price = parseFloat(opt?.dataset.price) || 0;
    var tax   = parseFloat(opt?.dataset.tax)   || 0;
    var qty   = parseInt(row.querySelector('.addon-qty')?.value) || 1;
    var total = price * qty;
    addonSub += total;
    addonTax += total * tax / 100;
    var tp = row.querySelector('.addon-total-price');
    if (tp) tp.textContent = total > 0 ? 'Rs. '+total.toFixed(2) : '—';
  });

  var subtotal = hallPrice + pkgPrice + addonSub;
  var discount = parseFloat(document.getElementById('discount_input')?.value) || 0;
  var final    = Math.max(0, subtotal + addonTax - discount);

  document.getElementById('sum-addons').textContent = 'Rs. '+addonSub.toFixed(2);
  document.getElementById('sum-tax').textContent    = 'Rs. '+addonTax.toFixed(2);
  document.getElementById('sum-total').textContent  = 'Rs. '+final.toFixed(2);
  document.getElementById('h-total').value = subtotal.toFixed(2);
  document.getElementById('h-tax').value   = addonTax.toFixed(2);

  // Date summary
  var dateEl = document.getElementById('event_date');
  if (dateEl && dateEl.value) {
    var d = new Date(dateEl.value);
    document.getElementById('sum-date').textContent = d.toLocaleDateString('en-LK',{day:'numeric',month:'short',year:'numeric'});
  }

  // Hall name summary
  document.getElementById('sum-hall').textContent = hallOpt?.value ? hallOpt.text.split('(')[0].trim() : '—';
}

document.getElementById('pkg_select').addEventListener('change', recalc);
document.getElementById('event_date').addEventListener('change', recalc);
document.getElementById('hall_select').addEventListener('change', recalc);

// Also check conflict when times change
document.querySelector('[name=event_time]')?.addEventListener('change', checkHallConflict);
document.querySelector('[name=event_end_time]')?.addEventListener('change', checkHallConflict);

recalc();

// ── Tom Select: Customer search ───────────────────────────────
(function() {
  var ts = new TomSelect('#customer_select', {
    placeholder: 'Search by name or phone...',
    searchField: ['text'],
    maxOptions: 300,
    plugins: ['dropdown_input'],
    dropdownParent: 'body',
    onChange: function(val) {
      var sel = document.getElementById('customer_select');
      showCustomerPreview(sel);
    },
    render: {
      option: function(data, escape) {
        var parts = data.text.split('·');
        var name  = parts[0] ? parts[0].trim() : data.text;
        var phone = parts[1] ? parts[1].trim() : '';
        return '<div style="display:flex;align-items:center;gap:.6rem;padding:.4rem .6rem;">' +
          '<div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#0c1a35,#1a3060);display:flex;align-items:center;justify-content:center;color:#fff;font-size:.75rem;font-weight:800;flex-shrink:0;">' + escape(name.charAt(0).toUpperCase()) + '</div>' +
          '<div><div style="font-size:.82rem;font-weight:700;color:#0c1a35;">' + escape(name) + '</div>' +
          (phone ? '<div style="font-size:.7rem;color:#6b7280;">' + escape(phone) + '</div>' : '') +
          '</div></div>';
      },
      item: function(data, escape) {
        var parts = data.text.split('·');
        var name  = parts[0] ? parts[0].trim() : data.text;
        var phone = parts[1] ? parts[1].trim() : '';
        return '<div style="display:flex;align-items:center;gap:.4rem;">' +
          '<span style="font-size:.82rem;font-weight:700;color:#0c1a35;">' + escape(name) + '</span>' +
          (phone ? '<span style="font-size:.72rem;color:#6b7280;">· ' + escape(phone) + '</span>' : '') +
          '</div>';
      }
    }
  });

  window._tsInstance = ts;
})();

// ── New Customer Modal ────────────────────────────────────────
function openNewCustomerModal() {
  var modal = new bootstrap.Modal(document.getElementById('newCustomerModal'));
  modal.show();
}

async function saveNewCustomer() {
  var errDiv = document.getElementById('modal-errors');
  errDiv.innerHTML = '';
  var name   = document.getElementById('nc_name').value.trim();
  var mobile = document.getElementById('nc_mobile').value.trim();
  if (!name)   { errDiv.innerHTML = '<div class="alert alert-danger py-2">Name is required.</div>'; return; }
  if (!mobile) { errDiv.innerHTML = '<div class="alert alert-danger py-2">Mobile is required.</div>'; return; }

  var btn = event.target;
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';
  var resetBtn = () => {
    btn.disabled = false;
    btn.innerHTML = 'Save Customer';
  };

  try {
    var resp = await fetch('<?= BASE_URL ?>/modules/customers/create_ajax.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({
        name, mobile,
        mobile2:    document.getElementById('nc_mobile2').value.trim(),
        email:      document.getElementById('nc_email').value.trim(),
        nic:        document.getElementById('nc_nic').value.trim(),
        city:       document.getElementById('nc_city').value.trim(),
        address:    document.getElementById('nc_address').value.trim(),
        bride_name: document.getElementById('nc_bride').value.trim(),
        groom_name: document.getElementById('nc_groom').value.trim(),
        notes:      document.getElementById('nc_notes').value.trim(),
      })
    });

    var text = await resp.text();
    var data;
    try { data = JSON.parse(text); }
    catch(e) {
      resetBtn();
      errDiv.innerHTML = '<div class="alert alert-danger py-2">Server error. Response: ' + text.substring(0,200) + '</div>';
      return;
    }

    resetBtn();
    if (data.success) {
      var sel = document.getElementById('customer_select');
      // Add to native select for fallback
      var opt = document.createElement('option');
      opt.value = data.id;
      opt.text = data.name + ' · ' + data.mobile;
      opt.dataset.phone = data.mobile;
      opt.dataset.email = data.email || '';
      opt.dataset.bride = data.bride_name || '';
      opt.dataset.groom = data.groom_name || '';
      sel.add(opt);
      // Add to Tom Select if available
      if (window._tsInstance) {
        window._tsInstance.addOption({value: String(data.id), text: data.name + ' · ' + data.mobile});
        window._tsInstance.setValue(String(data.id));
      } else {
        sel.value = data.id;
      }
      showCustomerPreview(sel);
      bootstrap.Modal.getInstance(document.getElementById('newCustomerModal')).hide();
    } else {
      errDiv.innerHTML = '<div class="alert alert-danger py-2">' + (data.error || 'Failed to save customer.') + '</div>';
    }

  } catch(err) {
    resetBtn();
    errDiv.innerHTML = '<div class="alert alert-danger py-2">Request failed: ' + err.message + '</div>';
    console.error('saveNewCustomer:', err);
  }
}
</script>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
