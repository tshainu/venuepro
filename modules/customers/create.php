<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
$db = Database::getInstance();
$cu = Auth::currentUser();
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name'] ?? '');
    $nic         = trim($_POST['nic'] ?? '');
    $address     = trim($_POST['address'] ?? '');
    $city        = trim($_POST['city'] ?? '');
    $mobile      = trim($_POST['mobile'] ?? '');
    $mobile2     = trim($_POST['mobile2'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $notes       = trim($_POST['notes'] ?? '');
    $branch_id   = $cu['branch_id'] ?? 1;

    if (!$name)   $errors[] = 'Customer name is required.';
    if (!$mobile) $errors[] = 'Mobile number is required.';
    elseif (!preg_match('/^\d{10}$/', $mobile)) $errors[] = 'Mobile number must be exactly 10 digits.';

    if (!$errors) {
        $db->insert(
            "INSERT INTO customers (branch_id,name,nic,address,city,mobile,mobile2,email,notes,created_by) VALUES (?,?,?,?,?,?,?,?,?,?)",
            [$branch_id,$name,$nic,$address,$city,$mobile,$mobile2,$email,$notes,$cu['id']]
        );
        Helper::flash('success', 'Customer added successfully.');
        Helper::redirect(BASE_URL.'/modules/customers/index.php');
    }
}

$pageTitle = 'Add Customer';
$breadcrumbs = [['label'=>'Customers','url'=>BASE_URL.'/modules/customers/index.php'],['label'=>'Add Customer']];
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
/* ── Add Customer Page ─────────────────────────────────── */
.cust-create-hero {
  background: linear-gradient(130deg,#08111f 0%,#0f1f40 40%,#162d5a 100%);
  border-radius: 20px; padding: 1.8rem 2rem 2rem; margin-bottom:1.8rem;
  border:1px solid rgba(201,168,76,.18); position:relative; overflow:hidden;
  box-shadow:0 12px 48px rgba(8,17,31,.35);
}
.cust-create-hero::before {
  content:''; position:absolute; top:-70px; right:-50px;
  width:280px; height:280px; border-radius:50%;
  background:radial-gradient(circle,rgba(201,168,76,.2) 0%,transparent 70%);
  pointer-events:none;
}
.cust-create-hero::after {
  content:''; position:absolute; bottom:-80px; left:20%;
  width:220px; height:220px; border-radius:50%;
  background:radial-gradient(circle,rgba(100,149,237,.08) 0%,transparent 70%);
  pointer-events:none;
}
.cust-hero-inner { position:relative; z-index:2; }
.cust-hero-avatar {
  width:64px; height:64px; border-radius:18px;
  background:linear-gradient(135deg,rgba(201,168,76,.3),rgba(201,168,76,.1));
  border:2px solid rgba(201,168,76,.4);
  display:flex; align-items:center; justify-content:center;
  font-size:1.8rem; flex-shrink:0; margin-bottom:1rem;
}
.cust-hero-title { color:#fff; font-size:1.7rem; font-weight:800; letter-spacing:-.03em; }
.cust-hero-sub   { color:rgba(255,255,255,.45); font-size:.82rem; margin-top:.3rem; max-width:520px; line-height:1.6; }
.cust-hero-pills { display:flex; gap:.6rem; flex-wrap:wrap; margin-top:1rem; }
.cust-hero-pill  {
  background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.15);
  border-radius:20px; padding:.3rem .9rem; font-size:.72rem; font-weight:600; color:rgba(255,255,255,.7);
  display:flex; align-items:center; gap:.35rem;
}

/* Section cards */
.cust-section {
  background:#fff; border-radius:18px;
  border:1px solid #edf0f8;
  box-shadow:0 2px 16px rgba(12,26,53,.07);
  margin-bottom:1.3rem; overflow:hidden;
}
.cust-section-head {
  padding:1.1rem 1.6rem; border-bottom:1px solid #f1f4fa;
  display:flex; align-items:center; gap:.9rem;
  background:linear-gradient(90deg,#fafbff 0%,#fff 100%);
}
.cust-section-icon {
  width:42px; height:42px; border-radius:12px; flex-shrink:0;
  display:flex; align-items:center; justify-content:center; font-size:1.2rem;
}
.cust-section-title { font-size:.92rem; font-weight:800; color:#0c1a35; }
.cust-section-sub   { font-size:.72rem; color:#9ca3af; margin-top:.08rem; }
.cust-section-body  { padding:1.5rem 1.6rem; }

/* Form styles */
.form-label { font-size:.78rem; font-weight:700; color:#374151; margin-bottom:.35rem; }
.form-label.required::after { content:' *'; color:#dc2626; }
.form-control, .form-select {
  border-radius:10px; border:1.5px solid #e5e7eb;
  font-size:.85rem; padding:.58rem 1rem;
  transition:border-color .15s, box-shadow .15s, background .15s;
}
.form-control:focus, .form-select:focus {
  border-color:#c9a84c; box-shadow:0 0 0 3px rgba(201,168,76,.12); background:#fffdf5;
}
.form-control::placeholder { color:#c4c9d6; }
.input-with-icon { position:relative; }
.input-with-icon .form-control { padding-left:2.6rem; }
.input-with-icon .input-prefix {
  position:absolute; left:.8rem; top:50%; transform:translateY(-50%);
  color:#9ca3af; display:flex; align-items:center;
}
.form-hint { font-size:.7rem; color:#9ca3af; margin-top:.28rem; }
.form-hint.good { color:#059669; }

/* Live avatar preview */
.live-preview-card {
  background:linear-gradient(135deg,#0c1a35,#1a3060);
  border-radius:16px; padding:1.4rem; text-align:center; position:sticky; top:80px;
  box-shadow:0 8px 32px rgba(12,26,53,.25);
}
.lp-avatar {
  width:70px; height:70px; border-radius:50%; margin:0 auto .9rem;
  background:linear-gradient(135deg,#c9a84c,#e8c96a);
  display:flex; align-items:center; justify-content:center;
  font-size:1.8rem; font-weight:900; color:#fff;
  box-shadow:0 6px 20px rgba(201,168,76,.4);
}
.lp-name  { color:#fff; font-size:1rem; font-weight:800; }
.lp-meta  { color:rgba(255,255,255,.5); font-size:.75rem; margin-top:.2rem; }
.lp-pills { margin-top:.9rem; display:flex; flex-direction:column; gap:.5rem; }
.lp-pill  { background:rgba(255,255,255,.07); border:1px solid rgba(255,255,255,.1); border-radius:8px; padding:.45rem .7rem; text-align:left; font-size:.74rem; color:rgba(255,255,255,.65); display:flex; align-items:center; gap:.5rem; }
.lp-pill strong { color:#fff; }
.lp-pill.empty  { opacity:.3; font-style:italic; }

/* Wedding section special */
.wedding-section {
  background:linear-gradient(135deg,#fdf9ee,#fffbf5);
  border:1px solid rgba(201,168,76,.25);
  border-radius:14px; padding:1.3rem 1.4rem; margin-top:.3rem;
}
.wedding-section-title {
  font-size:.72rem; font-weight:800; color:#92640c;
  text-transform:uppercase; letter-spacing:.08em; margin-bottom:1rem;
  display:flex; align-items:center; gap:.4rem;
}

/* Submit zone */
.cust-submit-zone {
  background:#fff; border-radius:16px; border:1px solid #edf0f8;
  box-shadow:0 2px 14px rgba(12,26,53,.07); padding:1.3rem 1.5rem;
  display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem;
}
.btn-save-cust {
  background:linear-gradient(135deg,#c9a84c,#e8c96a);
  border:none; color:#fff; border-radius:12px; padding:.72rem 2.2rem;
  font-size:.9rem; font-weight:800; letter-spacing:.02em;
  box-shadow:0 4px 18px rgba(201,168,76,.4); transition:all .18s;
  display:flex; align-items:center; gap:.5rem;
}
.btn-save-cust:hover { transform:translateY(-2px); box-shadow:0 8px 28px rgba(201,168,76,.5); }
.btn-cancel-cust {
  border:1.5px solid #e5e7eb; background:none; border-radius:10px;
  padding:.65rem 1.5rem; font-size:.85rem; font-weight:700; color:#6b7280;
  transition:all .15s; text-decoration:none; display:inline-flex; align-items:center; gap:.4rem;
}
.btn-cancel-cust:hover { border-color:#9ca3af; color:#374151; background:#f9fafb; }

/* Progress bar */
.cust-progress { display:flex; align-items:center; gap:.4rem; margin-bottom:1.5rem; }
.cust-prog-step { flex:1; height:4px; border-radius:2px; background:#edf0f8; transition:background .3s; }
.cust-prog-step.filled { background:linear-gradient(90deg,#c9a84c,#e8c96a); }

@media(max-width:992px){
  .live-preview-card { position:static; margin-top:1rem; }
}
</style>

<!-- Hero -->
<div class="cust-create-hero">
  <div class="cust-hero-inner">
    <div class="cust-hero-avatar">👤</div>
    <div class="cust-hero-title">Add New Customer</div>
    <div class="cust-hero-sub">Register a new customer to VenuePro. You can search and select them when creating event bookings.</div>
    <div class="cust-hero-pills">
      <div class="cust-hero-pill">
        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12l5 5L20 7"/></svg>
        Fast Booking Lookup
      </div>
      <div class="cust-hero-pill">
        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12l5 5L20 7"/></svg>
        Full Booking History
      </div>
    </div>
  </div>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger d-flex align-items-start gap-2 mb-3" style="border-radius:12px;">
  <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" style="flex-shrink:0;margin-top:2px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
  <div><ul class="mb-0 ps-3"><?php foreach($errors as $e): ?><li><?= Helper::sanitize($e) ?></li><?php endforeach; ?></ul></div>
</div>
<?php endif; ?>

<!-- Progress bar (visual) -->
<div class="cust-progress mb-4">
  <div class="cust-prog-step filled"></div>
  <div class="cust-prog-step filled"></div>
  <div class="cust-prog-step filled"></div>
  <div class="cust-prog-step" id="prog4"></div>
</div>

<form method="POST" id="cust-form">
<div class="row g-3">

  <!-- ── MAIN FORM ──────────────────────────────────── -->
  <div class="col-lg-8">

    <!-- Personal Info -->
    <div class="cust-section">
      <div class="cust-section-head">
        <div class="cust-section-icon" style="background:#eff6ff;">👤</div>
        <div>
          <div class="cust-section-title">Personal Information</div>
          <div class="cust-section-sub">Basic identity details of the customer</div>
        </div>
      </div>
      <div class="cust-section-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label required">Full Name</label>
            <div class="input-with-icon">
              <span class="input-prefix">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              </span>
              <input type="text" name="name" id="f_name" class="form-control" required placeholder="e.g. Kasun Perera" value="<?= htmlspecialchars($_POST['name']??'') ?>" oninput="updatePreview()">
            </div>
            <div class="form-hint">This name appears on all bookings and documents</div>
          </div>
          <div class="col-md-3">
            <label class="form-label">NIC Number</label>
            <input type="text" name="nic" id="f_nic" class="form-control" placeholder="e.g. 982345678V" value="<?= htmlspecialchars($_POST['nic']??'') ?>" oninput="updatePreview()">
            <div class="form-hint">National ID card number</div>
          </div>
          <div class="col-md-3">
            <label class="form-label">City</label>
            <div class="input-with-icon">
              <span class="input-prefix">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
              </span>
              <input type="text" name="city" id="f_city" class="form-control" placeholder="e.g. Colombo" value="<?= htmlspecialchars($_POST['city']??'') ?>" oninput="updatePreview()">
            </div>
          </div>
          <div class="col-12">
            <label class="form-label">Address</label>
            <textarea name="address" id="f_address" class="form-control" rows="2" placeholder="Full postal address..."><?= htmlspecialchars($_POST['address']??'') ?></textarea>
          </div>
        </div>
      </div>
    </div>

    <!-- Contact Info -->
    <div class="cust-section">
      <div class="cust-section-head">
        <div class="cust-section-icon" style="background:#f0fdf4;">📞</div>
        <div>
          <div class="cust-section-title">Contact Information</div>
          <div class="cust-section-sub">How do we reach this customer?</div>
        </div>
      </div>
      <div class="cust-section-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label required">Primary Mobile</label>
            <div class="input-group">
              <span class="input-group-text" style="border-radius:10px 0 0 10px;border:1.5px solid #e5e7eb;border-right:none;background:#f8fafc;font-size:.82rem;color:#6b7280;white-space:nowrap;">🇱🇰 +94</span>
              <input type="tel" name="mobile" id="f_mobile" class="form-control" required placeholder="07X XXX XXXX" value="<?= htmlspecialchars($_POST['mobile']??'') ?>" style="border-radius:0 10px 10px 0;" pattern="\d{10}" maxlength="10" oninput="this.value=this.value.replace(/\D/g,'');updatePreview()" title="Mobile must be exactly 10 digits">
            </div>
            <div class="form-hint">WhatsApp-compatible number preferred</div>
          </div>
          <div class="col-md-4">
            <label class="form-label">Secondary Mobile</label>
            <div class="input-group">
              <span class="input-group-text" style="border-radius:10px 0 0 10px;border:1.5px solid #e5e7eb;border-right:none;background:#f8fafc;font-size:.82rem;color:#6b7280;white-space:nowrap;">🇱🇰 +94</span>
              <input type="tel" name="mobile2" class="form-control" placeholder="Alternate number" value="<?= htmlspecialchars($_POST['mobile2']??'') ?>" style="border-radius:0 10px 10px 0;">
            </div>
            <div class="form-hint">Family member / backup contact</div>
          </div>
          <div class="col-md-4">
            <label class="form-label">Email Address</label>
            <div class="input-with-icon">
              <span class="input-prefix">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,12 2,6"/></svg>
              </span>
              <input type="email" name="email" id="f_email" class="form-control" placeholder="email@example.com" value="<?= htmlspecialchars($_POST['email']??'') ?>" oninput="updatePreview()">
            </div>
            <div class="form-hint">Used for invoices and confirmations</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Notes -->
    <div class="cust-section">
      <div class="cust-section-head">
        <div class="cust-section-icon" style="background:#f5f3ff;">📝</div>
        <div>
          <div class="cust-section-title">Notes</div>
          <div class="cust-section-sub">Internal notes about this customer</div>
        </div>
        <div class="ms-auto">
          <span style="background:#f5f3ff;border:1px solid #ede9fe;border-radius:20px;padding:.2rem .75rem;font-size:.68rem;font-weight:700;color:#7c3aed;">INTERNAL</span>
        </div>
      </div>
      <div class="cust-section-body">
        <textarea name="notes" class="form-control" rows="3" placeholder="Any special notes, preferences, or history about this customer that the team should know..."><?= htmlspecialchars($_POST['notes']??'') ?></textarea>
        <div class="form-hint">Not visible to customers. Staff reference only.</div>
      </div>
    </div>

    <!-- Submit Zone -->
    <div class="cust-submit-zone">
      <div>
        <div style="font-size:.82rem;font-weight:700;color:#374151;">Ready to save?</div>
        <div style="font-size:.72rem;color:#9ca3af;margin-top:.1rem;">Customer will be immediately available for bookings</div>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <a href="<?= BASE_URL ?>/modules/customers/index.php" class="btn-cancel-cust">
          <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
          Back
        </a>
        <button type="submit" class="btn-save-cust">
          <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12l5 5L20 7"/></svg>
          Save Customer
        </button>
      </div>
    </div>

  </div><!-- /col-lg-8 -->

  <!-- ── LIVE PREVIEW ──────────────────────────────── -->
  <div class="col-lg-4">
    <div class="live-preview-card">
      <div style="font-size:.65rem;font-weight:800;color:rgba(201,168,76,.7);text-transform:uppercase;letter-spacing:.1em;margin-bottom:1rem;">Live Preview</div>
      <div class="lp-avatar" id="lp-avatar">?</div>
      <div class="lp-name" id="lp-name">Customer Name</div>
      <div class="lp-meta" id="lp-meta">Fill in the form →</div>
      <div class="lp-pills">
        <div class="lp-pill empty" id="lp-mobile">
          <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.09 9.9a19.79 19.79 0 01-3.07-8.67A2 2 0 012 .18h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.91 7.91a16 16 0 006.29 6.29l1.28-1.29a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
          Mobile not set
        </div>
        <div class="lp-pill empty" id="lp-email">
          <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,12 2,6"/></svg>
          Email not set
        </div>
        <div class="lp-pill empty" id="lp-nic">
          <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M7 9h4M7 13h2"/></svg>
          NIC not set
        </div>
      </div>

      <div style="margin-top:1.2rem;padding-top:1rem;border-top:1px solid rgba(255,255,255,.08);">
        <div style="font-size:.65rem;font-weight:700;color:rgba(255,255,255,.3);text-transform:uppercase;letter-spacing:.08em;margin-bottom:.6rem;">Checklist</div>
        <div style="display:flex;flex-direction:column;gap:.4rem;">
          <div class="lp-check" id="chk-name" style="font-size:.75rem;color:rgba(255,255,255,.4);display:flex;align-items:center;gap:.45rem;">
            <span style="width:16px;height:16px;border-radius:50%;background:rgba(255,255,255,.1);display:flex;align-items:center;justify-content:center;font-size:.7rem;">✕</span> Name required
          </div>
          <div class="lp-check" id="chk-mobile" style="font-size:.75rem;color:rgba(255,255,255,.4);display:flex;align-items:center;gap:.45rem;">
            <span style="width:16px;height:16px;border-radius:50%;background:rgba(255,255,255,.1);display:flex;align-items:center;justify-content:center;font-size:.7rem;">✕</span> Mobile required
          </div>
          <div class="lp-check" id="chk-opt" style="font-size:.75rem;color:rgba(255,255,255,.4);display:flex;align-items:center;gap:.45rem;">
            <span style="width:16px;height:16px;border-radius:50%;background:rgba(255,255,255,.1);display:flex;align-items:center;justify-content:center;font-size:.7rem;">○</span> Optional fields
          </div>
        </div>
      </div>
    </div>
  </div>

</div>
</form>

<script>
function updatePreview() {
  var name   = document.getElementById('f_name').value.trim();
  var mobile = document.getElementById('f_mobile').value.trim();
  var email  = document.getElementById('f_email').value.trim();
  var nic    = document.getElementById('f_nic').value.trim();
  var city   = document.getElementById('f_city').value.trim();
  // Avatar
  var avatarEl = document.getElementById('lp-avatar');
  var nameEl   = document.getElementById('lp-name');
  var metaEl   = document.getElementById('lp-meta');
  avatarEl.textContent = name ? name.charAt(0).toUpperCase() : '?';
  nameEl.textContent   = name || 'Customer Name';
  metaEl.textContent   = city ? '📍 ' + city : (mobile ? mobile : 'Fill in the form →');

  // Pills
  setPill('lp-mobile', mobile, '📞 '+mobile, '📞 Mobile not set');
  setPill('lp-email',  email,  '✉ '+email,   '✉ Email not set');
  setPill('lp-nic',    nic,    '🪪 '+nic,     '🪪 NIC not set');

  // Checklist
  setCheck('chk-name',   !!name,   'Name ✓', 'Name required');
  setCheck('chk-mobile', !!mobile, 'Mobile ✓','Mobile required');
  var hasOpt = !!(email || nic || city);
  setCheck('chk-opt', hasOpt, 'Optional fields filled', 'Optional fields', true);

  // Progress bar
  var prog4 = document.getElementById('prog4');
  if (name && mobile) {
    prog4.style.background = 'linear-gradient(90deg,#c9a84c,#e8c96a)';
  } else {
    prog4.style.background = '#edf0f8';
  }
}

function setPill(id, val, filled, empty) {
  var el = document.getElementById(id);
  if (!el) return;
  if (val) {
    el.classList.remove('empty');
    el.querySelector ? (el.querySelector('span')||el).textContent = filled : el.textContent = filled;
    // just update text nodes
    el.childNodes.forEach(function(n){ if(n.nodeType===3) n.textContent = ' '+val; });
  } else {
    el.classList.add('empty');
  }
}

function setCheck(id, ok, okText, failText, optional) {
  var el = document.getElementById(id);
  if (!el) return;
  var dot = el.querySelector('span');
  if (ok) {
    el.style.color = 'rgba(110,231,183,0.9)';
    dot.style.background = 'rgba(5,150,105,.5)';
    dot.textContent = '✓';
    el.childNodes.forEach(function(n){ if(n.nodeType===3){ n.textContent = ' '+okText; } });
  } else {
    el.style.color = optional ? 'rgba(255,255,255,.3)' : 'rgba(255,255,255,.4)';
    dot.style.background = optional ? 'rgba(255,255,255,.08)' : 'rgba(255,255,255,.1)';
    dot.textContent = optional ? '○' : '✕';
    el.childNodes.forEach(function(n){ if(n.nodeType===3){ n.textContent = ' '+failText; } });
  }
}

// Init
updatePreview();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
