<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
$db = Database::getInstance();
$cu = Auth::currentUser();

$branches = $db->fetchAll("SELECT id,name FROM branches WHERE is_active=1");
$sel_branch = (int)($_GET['branch_id'] ?? ($cu['branch_id'] ?? $branches[0]['id'] ?? 1));
$active_tab = $_GET['tab'] ?? 'general';

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'save_settings') {
    if (!Auth::hasRole(['super_admin','admin','hall_manager','manager'])) { Helper::flash('error','Admin only.'); Helper::redirect(BASE_URL.'/modules/settings/index.php'); }
    
    // Handle logo upload
    if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === 0) {
        $file = $_FILES['company_logo'];
        $allowed = ['image/jpeg','image/png','image/webp'];
        if (!in_array($file['type'], $allowed)) {
            Helper::flash('error','Only JPG, PNG, WebP allowed.');
        } elseif ($file['size'] > 2*1024*1024) {
            Helper::flash('error','Logo must be under 2MB.');
        } else {
            $ext = match($file['type']) { 'image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp' };
            $filename = 'logo_'.$sel_branch.'_'.time().'.'.$ext;
            $upload_dir = ROOT_PATH.'/uploads';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            
            if (move_uploaded_file($file['tmp_name'], $upload_dir.'/'.$filename)) {
                // Delete old logo if exists
                $old = $db->fetchOne("SELECT setting_value FROM settings WHERE setting_key='company_logo' AND branch_id=?", [$sel_branch]);
                if ($old && file_exists($upload_dir.'/'.$old['setting_value'])) {
                    @unlink($upload_dir.'/'.$old['setting_value']);
                }
                // Save new logo
                $exists = $db->fetchOne("SELECT id FROM settings WHERE setting_key='company_logo' AND branch_id=?", [$sel_branch]);
                if ($exists) {
                    $db->execute("UPDATE settings SET setting_value=? WHERE setting_key='company_logo' AND branch_id=?", [$filename,$sel_branch]);
                } else {
                    $db->insert("INSERT INTO settings (setting_key,setting_value,branch_id) VALUES (?,?,?)", ['company_logo',$filename,$sel_branch]);
                }
                Helper::flash('success','Logo uploaded successfully.');
            }
        }
    }
    
    $data = $_POST['settings'] ?? [];
    foreach ($data as $key => $val) {
        $key = preg_replace('/[^a-z0-9_]/','',$key);
        $val = trim($val);
        $exists = $db->fetchOne("SELECT id FROM settings WHERE setting_key=? AND branch_id=?", [$key,$sel_branch]);
        if ($exists) {
            $db->execute("UPDATE settings SET setting_value=? WHERE setting_key=? AND branch_id=?", [$val,$key,$sel_branch]);
        } else {
            $db->insert("INSERT INTO settings (setting_key,setting_value,branch_id) VALUES (?,?,?)", [$key,$val,$sel_branch]);
        }
    }
    Helper::flash('success','Settings saved.');
    $redirect_tab = $_POST['_tab'] ?? 'general';
    Helper::redirect(BASE_URL.'/modules/settings/index.php?branch_id='.$sel_branch.'&tab='.urlencode($redirect_tab));
}

// Load settings
$settings_rows = $db->fetchAll("SELECT setting_key, setting_value FROM settings WHERE branch_id=? OR branch_id IS NULL", [$sel_branch]);
$settings = [];
foreach ($settings_rows as $s) $settings[$s['setting_key']] = $s['setting_value'];

// Load halls, rooms, packages
$bid = $cu['branch_id'];
$halls    = $db->fetchAll("SELECT h.*, b.name as branch_name FROM halls h LEFT JOIN branches b ON h.branch_id=b.id WHERE 1 ".($bid?"AND h.branch_id=?":"")." ORDER BY h.name", $bid?[$bid]:[]);
$rooms    = $db->fetchAll("SELECT r.*, rt.name as type_name, b.name as branch_name FROM rooms r LEFT JOIN room_types rt ON r.room_type_id=rt.id LEFT JOIN branches b ON r.branch_id=b.id WHERE 1 ".($bid?"AND r.branch_id=?":"")." ORDER BY r.room_number", $bid?[$bid]:[]);
$packages = $db->fetchAll("SELECT p.*, b.name as branch_name FROM packages p LEFT JOIN branches b ON p.branch_id=b.id WHERE 1 ".($bid?"AND p.branch_id=?":"")." ORDER BY p.name", $bid?[$bid]:[]);

$pageTitle   = 'Settings';
$breadcrumbs = [['label'=>'Settings']];
require_once ROOT_PATH . '/includes/header.php';
?>

<style>
.settings-tabs .nav-link { color: var(--vp-navy); font-weight: 600; font-size: .85rem; padding: .55rem 1.1rem; border-radius: 10px; transition: all .2s; }
.settings-tabs .nav-link:hover { background: rgba(30,42,74,.07); }
.settings-tabs .nav-link.active { background: var(--vp-navy); color: #fff !important; }
.settings-tabs .nav-link .tab-icon { font-size: 1rem; margin-right: .4rem; }
.settings-tabs { background: #fff; border-radius: 14px; padding: .5rem; border: 1px solid #e8eaf0; box-shadow: 0 2px 8px rgba(30,42,74,.06); margin-bottom: 1.5rem; }
.tab-pane { animation: fadeInUp .18s ease; }
@keyframes fadeInUp { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:translateY(0); } }
.settings-card { border-radius: 14px; border: 1px solid #e8eaf0; box-shadow: 0 2px 8px rgba(30,42,74,.06); margin-bottom: 1.2rem; }
.settings-card .card-header { background: linear-gradient(135deg,#f8f9fb,#fff); border-bottom: 1px solid #e8eaf0; border-radius: 14px 14px 0 0; padding: 1rem 1.4rem; }
.settings-card .card-header h3 { font-size: .9rem; font-weight: 700; color: var(--vp-navy); margin: 0; }
.vp-item-row { display: flex; align-items: center; padding: .75rem 1.1rem; border-bottom: 1px solid #f0f2f5; gap: .75rem; transition: background .15s; }
.vp-item-row:last-child { border-bottom: none; }
.vp-item-row:hover { background: #f8f9fb; }
.vp-item-icon { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }
.vp-item-info { flex: 1; min-width: 0; }
.vp-item-name { font-weight: 700; font-size: .88rem; color: var(--vp-navy); }
.vp-item-meta { font-size: .75rem; color: #9ca3af; margin-top: 1px; }
.vp-item-price { font-weight: 700; font-size: .9rem; color: var(--vp-gold); white-space: nowrap; }
.vp-item-actions { display: flex; gap: .4rem; }
.tab-add-btn { display: flex; align-items: center; gap: .4rem; font-weight: 700; font-size: .83rem; }
.empty-tab { text-align: center; padding: 2.5rem 1rem; color: #9ca3af; }
.empty-tab .empty-tab-icon { font-size: 2.2rem; margin-bottom: .5rem; }
.empty-tab .empty-tab-text { font-size: .88rem; }
</style>

<div class="vp-page-header d-print-none">
  <div class="d-flex align-items-center justify-content-between w-100">
    <div>
      <h1 class="vp-page-title">Settings</h1>
      <div class="vp-page-sub">Manage your venue configuration</div>
    </div>
    <?php if (count($branches) > 1): ?>
    <form method="get" class="d-flex gap-2 align-items-center">
      <input type="hidden" name="tab" value="<?= htmlspecialchars($active_tab) ?>">
      <select name="branch_id" class="form-select form-select-sm" style="max-width:180px;" onchange="this.form.submit()">
        <?php foreach ($branches as $b): ?>
        <option value="<?= $b['id'] ?>" <?= $sel_branch==$b['id']?'selected':'' ?>><?= Helper::sanitize($b['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
    <?php endif; ?>
  </div>
</div>

<!-- Tab Nav -->
<ul class="nav settings-tabs mb-0 flex-row flex-wrap gap-1" id="settingsTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link <?= $active_tab==='general'?'active':'' ?>" data-bs-toggle="tab" data-bs-target="#tab-general" type="button" role="tab">
      <span class="tab-icon">⚙️</span> General
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link <?= $active_tab==='halls'?'active':'' ?>" data-bs-toggle="tab" data-bs-target="#tab-halls" type="button" role="tab">
      <span class="tab-icon">🏛️</span> Halls
      <span class="badge bg-secondary ms-1" style="font-size:.68rem;"><?= count($halls) ?></span>
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link <?= $active_tab==='rooms'?'active':'' ?>" data-bs-toggle="tab" data-bs-target="#tab-rooms" type="button" role="tab">
      <span class="tab-icon">🛏️</span> Rooms
      <span class="badge bg-secondary ms-1" style="font-size:.68rem;"><?= count($rooms) ?></span>
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link <?= $active_tab==='packages'?'active':'' ?>" data-bs-toggle="tab" data-bs-target="#tab-packages" type="button" role="tab">
      <span class="tab-icon">📦</span> Packages
      <span class="badge bg-secondary ms-1" style="font-size:.68rem;"><?= count($packages) ?></span>
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link <?= $active_tab==='invoice'?'active':'' ?>" data-bs-toggle="tab" data-bs-target="#tab-invoice" type="button" role="tab">
      <span class="tab-icon">🧾</span> Invoice Layout
    </button>
  </li>
</ul>

<div class="tab-content mt-0" id="settingsTabContent">

  <!-- ===== TAB: GENERAL ===== -->
  <div class="tab-pane fade <?= $active_tab==='general'?'show active':'' ?>" id="tab-general" role="tabpanel">
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="_action" value="save_settings">
      <input type="hidden" name="_tab" value="general">
      <div class="row">
        <div class="col-lg-8">

          <div class="card settings-card">
            <div class="card-header"><h3>🏢 Company Information</h3></div>
            <div class="card-body p-4">
              <div class="mb-3">
                <label class="form-label">Company Logo</label>
                <div class="d-flex gap-3 align-items-start">
                  <div>
                    <?php $logo = $settings['company_logo'] ?? null; ?>
                    <div class="logo-preview" style="width:120px;height:120px;border:2px dashed #e8eaf0;border-radius:12px;display:flex;align-items:center;justify-content:center;background:#f8f9fb;overflow:hidden;margin-bottom:.8rem;">
                      <?php if ($logo && file_exists(ROOT_PATH.'/uploads/'.$logo)): ?>
                        <img src="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($logo) ?>" style="max-width:100%;max-height:100%;object-fit:contain;">
                      <?php else: ?>
                        <div style="text-align:center;color:#9ca3af;font-size:.85rem;">
                          <div style="font-size:2rem;margin-bottom:.2rem;">📷</div>
                          <div>No logo</div>
                        </div>
                      <?php endif; ?>
                    </div>
                    <input type="file" name="company_logo" id="logoUpload" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp" onchange="previewLogo(this)">
                    <div style="font-size:.75rem;color:#6b7280;margin-top:.5rem;">JPG, PNG, or WebP (max 2MB)</div>
                  </div>
                </div>
              </div>
              <div class="mb-3"><label class="form-label">Company Name</label>
                <input type="text" name="settings[company_name]" class="form-control" value="<?= Helper::sanitize($settings['company_name']??APP_NAME) ?>"></div>
              <div class="mb-3"><label class="form-label">Tagline</label>
                <input type="text" name="settings[company_tagline]" class="form-control" value="<?= Helper::sanitize($settings['company_tagline']??'') ?>" placeholder="Your Dream Wedding, Our Expertise"></div>
              <div class="mb-3"><label class="form-label">Address</label>
                <textarea name="settings[company_address]" class="form-control" rows="2"><?= Helper::sanitize($settings['company_address']??'') ?></textarea></div>
              <div class="row mb-3">
                <div class="col-md-6"><label class="form-label">Phone</label>
                  <input type="text" name="settings[company_phone]" class="form-control" value="<?= Helper::sanitize($settings['company_phone']??'') ?>"></div>
                <div class="col-md-6"><label class="form-label">Email</label>
                  <input type="email" name="settings[company_email]" class="form-control" value="<?= Helper::sanitize($settings['company_email']??'') ?>"></div>
              </div>
              <div class="mb-0"><label class="form-label">Website</label>
                <input type="text" name="settings[company_website]" class="form-control" value="<?= Helper::sanitize($settings['company_website']??'') ?>" placeholder="https://"></div>
            </div>
          </div>

          <div class="card settings-card">
            <div class="card-header"><h3>🧾 Invoice &amp; Quotation</h3></div>
            <div class="card-body p-4">
              <div class="mb-3"><label class="form-label">Invoice Terms &amp; Conditions</label>
                <textarea name="settings[invoice_terms]" class="form-control" rows="3"><?= Helper::sanitize($settings['invoice_terms']??'') ?></textarea></div>
              <div class="mb-3"><label class="form-label">Quotation Terms &amp; Conditions</label>
                <textarea name="settings[quotation_terms]" class="form-control" rows="3"><?= Helper::sanitize($settings['quotation_terms']??'') ?></textarea></div>
              <div class="row mb-0">
                <div class="col-md-6"><label class="form-label">Default Tax % (VAT/NBT)</label>
                  <input type="number" name="settings[default_tax_percent]" class="form-control" step="0.01" min="0" max="100" value="<?= $settings['default_tax_percent']??'0' ?>"></div>
                <div class="col-md-6"><label class="form-label">Invoice Due Days</label>
                  <input type="number" name="settings[invoice_due_days]" class="form-control" min="0" value="<?= $settings['invoice_due_days']??'7' ?>"></div>
              </div>
            </div>
          </div>

          <div class="card settings-card">
            <div class="card-header"><h3>🏦 Bank Details (for Invoices)</h3></div>
            <div class="card-body p-4">
              <div class="row mb-3">
                <div class="col-md-6"><label class="form-label">Bank Name</label>
                  <input type="text" name="settings[bank_name]" class="form-control" value="<?= Helper::sanitize($settings['bank_name']??'') ?>"></div>
                <div class="col-md-6"><label class="form-label">Account Name</label>
                  <input type="text" name="settings[bank_account_name]" class="form-control" value="<?= Helper::sanitize($settings['bank_account_name']??'') ?>"></div>
              </div>
              <div class="row mb-0">
                <div class="col-md-6"><label class="form-label">Account Number</label>
                  <input type="text" name="settings[bank_account_number]" class="form-control" value="<?= Helper::sanitize($settings['bank_account_number']??'') ?>"></div>
                <div class="col-md-6"><label class="form-label">Branch</label>
                  <input type="text" name="settings[bank_branch]" class="form-control" value="<?= Helper::sanitize($settings['bank_branch']??'') ?>"></div>
              </div>
            </div>
          </div>

          <div class="card settings-card">
            <div class="card-header"><h3>🌐 System</h3></div>
            <div class="card-body p-4">
              <div class="row mb-0">
                <div class="col-md-6"><label class="form-label">Currency Symbol</label>
                  <input type="text" name="settings[currency_symbol]" class="form-control" value="<?= Helper::sanitize($settings['currency_symbol']??'Rs.') ?>"></div>
                <div class="col-md-6"><label class="form-label">Date Format</label>
                  <select name="settings[date_format]" class="form-select">
                    <option value="d M Y" <?= ($settings['date_format']??'d M Y')==='d M Y'?'selected':'' ?>>25 Jun 2026</option>
                    <option value="d/m/Y" <?= ($settings['date_format']??'')==='d/m/Y'?'selected':'' ?>>25/06/2026</option>
                    <option value="Y-m-d" <?= ($settings['date_format']??'')==='Y-m-d'?'selected':'' ?>>2026-06-25</option>
                  </select>
                </div>
              </div>
            </div>
          </div>

        </div><!-- /col -->
      </div><!-- /row -->

      <div class="mt-4 pt-3" style="border-top:2px solid #e8eaf0;">
        <button type="submit" class="btn btn-vp-gold">Save Settings</button>
        <a href="<?= BASE_URL ?>/index.php" class="btn btn-ghost-secondary ms-2">Cancel</a>
      </div>
    </form>
  </div>

  <!-- ===== TAB: HALLS ===== -->
  <div class="tab-pane fade <?= $active_tab==='halls'?'show active':'' ?>" id="tab-halls" role="tabpanel">
    <div class="card settings-card">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h3>🏛️ Halls</h3>
        <?php if (Auth::hasRole(['super_admin','admin','hall_manager','manager'])): ?>
        <a href="<?= BASE_URL ?>/modules/halls/create.php?return=settings" class="btn btn-vp-gold btn-sm tab-add-btn">
          <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
          Add Hall
        </a>
        <?php endif; ?>
      </div>
      <?php if ($halls): ?>
      <div class="card-body p-0">
        <?php foreach ($halls as $h): ?>
        <div class="vp-item-row">
          <div class="vp-item-icon" style="background:<?= $h['is_active']?'rgba(30,42,74,.08)':'rgba(200,200,200,.15)' ?>;">
            <?php if ($h['image']): ?>
              <img src="<?= BASE_URL ?>/uploads/halls/<?= $h['image'] ?>" style="width:38px;height:38px;object-fit:cover;border-radius:10px;" alt="">
            <?php else: ?>
              🏛️
            <?php endif; ?>
          </div>
          <div class="vp-item-info">
            <div class="vp-item-name"><?= Helper::sanitize($h['name']) ?>
              <?php if (!$h['is_active']): ?><span class="badge bg-secondary ms-1" style="font-size:.65rem;">Inactive</span><?php endif; ?>
            </div>
            <div class="vp-item-meta">
              <?= Helper::sanitize($h['branch_name']) ?> &nbsp;·&nbsp;
              <?= number_format($h['capacity']) ?> guests &nbsp;·&nbsp;
              <?= $h['facilities'] ? Helper::sanitize(substr($h['facilities'],0,40)).(strlen($h['facilities'])>40?'…':'') : 'No facilities listed' ?>
            </div>
          </div>
          <div class="vp-item-price"><?= Helper::formatCurrency($h['price_per_day']) ?><span style="font-size:.7rem;font-weight:400;color:#9ca3af;">/day</span></div>
          <?php if (Auth::hasRole(['super_admin','admin','hall_manager','manager'])): ?>
          <div class="vp-item-actions">
            <a href="<?= BASE_URL ?>/modules/halls/edit.php?id=<?= $h['id'] ?>&return=settings" class="btn btn-vp-primary btn-sm">Edit</a>
            <a href="<?= BASE_URL ?>/modules/halls/delete.php?id=<?= $h['id'] ?>&return=settings" class="btn btn-vp-danger btn-sm" onclick="return confirm('Delete <?= addslashes($h['name']) ?>?')">Delete</a>
          </div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="card-body">
        <div class="empty-tab">
          <div class="empty-tab-icon">🏛️</div>
          <div class="empty-tab-text">No halls configured yet.</div>
          <?php if (Auth::hasRole(['super_admin','admin','hall_manager','manager'])): ?>
          <a href="<?= BASE_URL ?>/modules/halls/create.php?return=settings" class="btn btn-vp-gold btn-sm mt-3 tab-add-btn">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
            Add your first hall
          </a>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ===== TAB: ROOMS ===== -->
  <div class="tab-pane fade <?= $active_tab==='rooms'?'show active':'' ?>" id="tab-rooms" role="tabpanel">
    <div class="card settings-card">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h3>🛏️ Rooms</h3>
        <?php if (Auth::hasRole(['super_admin','admin','hall_manager','manager'])): ?>
        <a href="<?= BASE_URL ?>/modules/rooms/create.php?return=settings" class="btn btn-vp-gold btn-sm tab-add-btn">
          <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
          Add Room
        </a>
        <?php endif; ?>
      </div>
      <?php if ($rooms): ?>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-vcenter vp-table mb-0">
            <thead>
              <tr>
                <th style="padding-left:1.1rem;">Room No</th>
                <th>Name</th>
                <th>Type</th>
                <th>Capacity</th>
                <th>Rate / Night</th>
                <th>Status</th>
                <?php if (Auth::hasRole(['super_admin','admin','hall_manager','manager'])): ?><th></th><?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rooms as $r): ?>
              <tr>
                <td style="padding-left:1.1rem;"><span class="fw-700 text-vp-navy"><?= Helper::sanitize($r['room_number']) ?></span></td>
                <td><?= Helper::sanitize($r['name']) ?></td>
                <td><?= Helper::sanitize($r['type_name'] ?? '—') ?></td>
                <td><?= $r['capacity'] ?> pax</td>
                <td class="fw-600"><?= Helper::formatCurrency($r['rate_per_night']) ?></td>
                <td><?= Helper::statusBadge($r['status']) ?></td>
                <?php if (Auth::hasRole(['super_admin','admin','hall_manager','manager'])): ?>
                <td>
                  <div class="d-flex gap-1">
                    <a href="<?= BASE_URL ?>/modules/rooms/edit.php?id=<?= $r['id'] ?>&return=settings" class="btn btn-vp-primary btn-sm">Edit</a>
                    <a href="<?= BASE_URL ?>/modules/rooms/delete.php?id=<?= $r['id'] ?>&return=settings" class="btn btn-vp-danger btn-sm" onclick="return confirm('Delete this room?')">Delete</a>
                  </div>
                </td>
                <?php endif; ?>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php else: ?>
      <div class="card-body">
        <div class="empty-tab">
          <div class="empty-tab-icon">🛏️</div>
          <div class="empty-tab-text">No rooms configured yet.</div>
          <?php if (Auth::hasRole(['super_admin','admin','hall_manager','manager'])): ?>
          <a href="<?= BASE_URL ?>/modules/rooms/create.php?return=settings" class="btn btn-vp-gold btn-sm mt-3 tab-add-btn">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
            Add your first room
          </a>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ===== TAB: PACKAGES ===== -->
  <div class="tab-pane fade <?= $active_tab==='packages'?'show active':'' ?>" id="tab-packages" role="tabpanel">
    <div class="card settings-card">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h3>📦 Packages</h3>
        <?php if (Auth::hasRole(['super_admin','admin','hall_manager','manager'])): ?>
        <a href="<?= BASE_URL ?>/modules/packages/create.php?return=settings" class="btn btn-vp-gold btn-sm tab-add-btn">
          <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
          Add Package
        </a>
        <?php endif; ?>
      </div>
      <?php if ($packages): ?>
      <div class="card-body p-0">
        <?php foreach ($packages as $p):
          $items = $db->fetchAll("SELECT * FROM package_items WHERE package_id=?", [$p['id']]);
        ?>
        <div class="vp-item-row align-items-start">
          <div class="vp-item-icon" style="background:rgba(30,42,74,.08);margin-top:2px;">📦</div>
          <div class="vp-item-info">
            <div class="vp-item-name"><?= Helper::sanitize($p['name']) ?></div>
            <div class="vp-item-meta mb-1">
              <?= Helper::sanitize($p['branch_name'] ?? 'All Branches') ?>
              <?php if ($p['description']): ?> &nbsp;·&nbsp; <?= Helper::sanitize(substr($p['description'],0,50)) ?><?php endif; ?>
            </div>
            <?php if ($items): ?>
            <div class="d-flex flex-wrap gap-1">
              <?php foreach ($items as $it): ?>
              <span class="badge" style="background:#f0f2f5;color:#4b5563;font-weight:500;font-size:.7rem;border-radius:6px;padding:.2rem .55rem;">
                <?= Helper::sanitize($it['item_name']) ?><?= $it['quantity']>1?' ×'.$it['quantity']:'' ?>
              </span>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
          <div class="vp-item-price ms-2"><?= Helper::formatCurrency($p['price']) ?></div>
          <?php if (Auth::hasRole(['super_admin','admin','hall_manager','manager'])): ?>
          <div class="vp-item-actions">
            <a href="<?= BASE_URL ?>/modules/packages/edit.php?id=<?= $p['id'] ?>&return=settings" class="btn btn-vp-primary btn-sm">Edit</a>
            <a href="<?= BASE_URL ?>/modules/packages/delete.php?id=<?= $p['id'] ?>&return=settings" class="btn btn-vp-danger btn-sm" onclick="return confirm('Delete <?= addslashes($p['name']) ?>?')">Delete</a>
          </div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="card-body">
        <div class="empty-tab">
          <div class="empty-tab-icon">📦</div>
          <div class="empty-tab-text">No packages created yet.</div>
          <?php if (Auth::hasRole(['super_admin','admin','hall_manager','manager'])): ?>
          <a href="<?= BASE_URL ?>/modules/packages/create.php?return=settings" class="btn btn-vp-gold btn-sm mt-3 tab-add-btn">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
            Add your first package
          </a>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ===== TAB: INVOICE LAYOUT ===== -->
  <div class="tab-pane fade <?= $active_tab==='invoice'?'show active':'' ?>" id="tab-invoice" role="tabpanel">
    <form method="post">
      <input type="hidden" name="_action" value="save_settings">
      <input type="hidden" name="_tab" value="invoice">
      <div class="row">
        <div class="col-lg-8">

          <div class="card settings-card">
            <div class="card-header"><h3>🧾 Invoice Paper Size</h3></div>
            <div class="card-body p-4">
              <p class="text-secondary mb-3" style="font-size:.85rem;">Choose the paper format used when printing or generating PDF invoices.</p>

              <?php $invSize = $settings['invoice_paper_size'] ?? 'A4'; ?>
              <div class="row g-3 mb-4" id="paperSizePicker">
                <?php
                $sizes = [
                  '80mm'   => ['label'=>'80mm Receipt','desc'=>'Thermal receipt printer','icon'=>'🧾','w'=>'80mm','h'=>'~200mm'],
                  'A5'     => ['label'=>'A5','desc'=>'Half-page compact','icon'=>'📄','w'=>'148mm','h'=>'210mm'],
                  'A4'     => ['label'=>'A4','desc'=>'Standard letter size','icon'=>'📋','w'=>'210mm','h'=>'297mm'],
                  'custom' => ['label'=>'Custom','desc'=>'Set your own dimensions','icon'=>'✏️','w'=>'—','h'=>'—'],
                ];
                foreach ($sizes as $val => $sz):
                  $active = $invSize === $val;
                ?>
                <div class="col-6 col-md-3">
                  <label class="paper-size-card <?= $active?'selected':'' ?>" for="psize_<?= $val ?>">
                    <input type="radio" name="settings[invoice_paper_size]" id="psize_<?= $val ?>" value="<?= $val ?>" <?= $active?'checked':'' ?> class="d-none">
                    <div class="ps-icon"><?= $sz['icon'] ?></div>
                    <div class="ps-label"><?= $sz['label'] ?></div>
                    <div class="ps-desc"><?= $sz['desc'] ?></div>
                    <div class="ps-dims"><?= $sz['w'] ?> × <?= $sz['h'] ?></div>
                  </label>
                </div>
                <?php endforeach; ?>
              </div>

              <!-- Custom size fields -->
              <div id="customSizeFields" style="display:<?= $invSize==='custom'?'block':'none' ?>">
                <div class="card" style="background:#f8f9fb;border:1px solid #e8eaf0;border-radius:12px;">
                  <div class="card-body p-3">
                    <div class="row g-3">
                      <div class="col-md-4">
                        <label class="form-label fw-600">Width (mm)</label>
                        <input type="number" name="settings[invoice_paper_width]" class="form-control" min="50" max="400" value="<?= $settings['invoice_paper_width'] ?? 210 ?>" placeholder="210">
                      </div>
                      <div class="col-md-4">
                        <label class="form-label fw-600">Height (mm)</label>
                        <input type="number" name="settings[invoice_paper_height]" class="form-control" min="80" max="600" value="<?= $settings['invoice_paper_height'] ?? 297 ?>" placeholder="297">
                      </div>
                      <div class="col-md-4">
                        <label class="form-label fw-600">Orientation</label>
                        <select name="settings[invoice_paper_orientation]" class="form-select">
                          <option value="P" <?= ($settings['invoice_paper_orientation']??'P')==='P'?'selected':'' ?>>Portrait</option>
                          <option value="L" <?= ($settings['invoice_paper_orientation']??'')==='L'?'selected':'' ?>>Landscape</option>
                        </select>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="card settings-card">
            <div class="card-header"><h3>🎨 Invoice Style</h3></div>
            <div class="card-body p-4">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label fw-600">Primary Color</label>
                  <div class="d-flex gap-2 align-items-center">
                    <input type="color" name="settings[invoice_color]" class="form-control form-control-color" style="width:52px;height:38px;border-radius:8px;padding:2px;" value="<?= $settings['invoice_color'] ?? '#1a3c6e' ?>">
                    <input type="text" name="settings[invoice_color_hex]" class="form-control" value="<?= $settings['invoice_color'] ?? '#1a3c6e' ?>" placeholder="#1a3c6e" style="font-family:monospace;" id="invoiceColorHex">
                  </div>
                  <div class="form-text">Used for header, table headings, and totals row.</div>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-600">Show Logo on Invoice</label>
                  <select name="settings[invoice_show_logo]" class="form-select">
                    <option value="1" <?= ($settings['invoice_show_logo']??'1')==='1'?'selected':'' ?>>Yes — show logo</option>
                    <option value="0" <?= ($settings['invoice_show_logo']??'')==='0'?'selected':'' ?>>No — text only</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-600">Font Size</label>
                  <select name="settings[invoice_font_size]" class="form-select">
                    <option value="8" <?= ($settings['invoice_font_size']??'10')==='8'?'selected':'' ?>>Small (8pt)</option>
                    <option value="10" <?= ($settings['invoice_font_size']??'10')==='10'?'selected':'' ?>>Normal (10pt)</option>
                    <option value="12" <?= ($settings['invoice_font_size']??'')==='12'?'selected':'' ?>>Large (12pt)</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-600">Show Payment QR</label>
                  <select name="settings[invoice_show_qr]" class="form-select">
                    <option value="0" <?= ($settings['invoice_show_qr']??'0')==='0'?'selected':'' ?>>No</option>
                    <option value="1" <?= ($settings['invoice_show_qr']??'')==='1'?'selected':'' ?>>Yes</option>
                  </select>
                </div>
              </div>
            </div>
          </div>

          <div class="card settings-card">
            <div class="card-header"><h3>📐 Margins &amp; Padding</h3></div>
            <div class="card-body p-4">
              <div class="row g-3">
                <div class="col-6 col-md-3">
                  <label class="form-label fw-600">Top (mm)</label>
                  <input type="number" name="settings[invoice_margin_top]" class="form-control" min="0" max="50" value="<?= $settings['invoice_margin_top'] ?? 10 ?>">
                </div>
                <div class="col-6 col-md-3">
                  <label class="form-label fw-600">Bottom (mm)</label>
                  <input type="number" name="settings[invoice_margin_bottom]" class="form-control" min="0" max="50" value="<?= $settings['invoice_margin_bottom'] ?? 10 ?>">
                </div>
                <div class="col-6 col-md-3">
                  <label class="form-label fw-600">Left (mm)</label>
                  <input type="number" name="settings[invoice_margin_left]" class="form-control" min="0" max="50" value="<?= $settings['invoice_margin_left'] ?? 10 ?>">
                </div>
                <div class="col-6 col-md-3">
                  <label class="form-label fw-600">Right (mm)</label>
                  <input type="number" name="settings[invoice_margin_right]" class="form-control" min="0" max="50" value="<?= $settings['invoice_margin_right'] ?? 10 ?>">
                </div>
              </div>
            </div>
          </div>

        </div><!-- /col -->
      </div><!-- /row -->

      <div class="mt-4 pt-3" style="border-top:2px solid #e8eaf0;">
        <button type="submit" class="btn btn-vp-gold">Save Invoice Settings</button>
        <a href="<?= BASE_URL ?>/modules/settings/index.php?tab=invoice" class="btn btn-ghost-secondary ms-2">Cancel</a>
      </div>
    </form>
  </div>

</div><!-- /tab-content -->

<style>
.paper-size-card { display:flex; flex-direction:column; align-items:center; gap:.3rem; padding:1.1rem .8rem; border:2px solid #e8eaf0; border-radius:14px; cursor:pointer; transition:all .18s; text-align:center; background:#fff; width:100%; }
.paper-size-card:hover { border-color: var(--vp-navy); background:#f8f9fb; }
.paper-size-card.selected { border-color: var(--vp-navy); background:rgba(30,42,74,.06); box-shadow: 0 0 0 3px rgba(30,42,74,.1); }
.ps-icon { font-size:1.6rem; }
.ps-label { font-weight:700; font-size:.88rem; color:var(--vp-navy); }
.ps-desc { font-size:.72rem; color:#9ca3af; }
.ps-dims { font-size:.7rem; color:#6b7280; font-family:monospace; background:#f0f2f5; border-radius:4px; padding:1px 6px; margin-top:2px; }
</style>

<script>
// Persist active tab in URL without reload
document.querySelectorAll('#settingsTabs button[data-bs-toggle="tab"]').forEach(btn => {
  btn.addEventListener('shown.bs.tab', e => {
    const tab = e.target.getAttribute('data-bs-target').replace('#tab-','');
    const url = new URL(window.location);
    url.searchParams.set('tab', tab);
    window.history.replaceState({}, '', url);
  });
});

// Paper size picker
document.querySelectorAll('#paperSizePicker input[type=radio]').forEach(radio => {
  radio.addEventListener('change', function() {
    document.querySelectorAll('.paper-size-card').forEach(c => c.classList.remove('selected'));
    this.closest('.paper-size-card').classList.add('selected');
    document.getElementById('customSizeFields').style.display = this.value === 'custom' ? 'block' : 'none';
  });
});

// Sync color picker → hex input
const colorPicker = document.querySelector('input[name="settings[invoice_color]"]');
const colorHex    = document.getElementById('invoiceColorHex');
if (colorPicker && colorHex) {
  colorPicker.addEventListener('input', () => colorHex.value = colorPicker.value);
  colorHex.addEventListener('input', () => { if (/^#[0-9a-f]{6}$/i.test(colorHex.value)) colorPicker.value = colorHex.value; });
}

// Logo preview
function previewLogo(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => {
      document.querySelector('.logo-preview').innerHTML = '<img src="' + e.target.result + '" style="max-width:100%;max-height:100%;object-fit:contain;">';
    };
    reader.readAsDataURL(input.files[0]);
  }
}
</script>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
