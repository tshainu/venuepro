<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
if (!Auth::isSuperAdmin()) { Helper::flash('error','Super Admin only.'); Helper::redirect(BASE_URL.'/index.php'); }
$db = Database::getInstance();
$cu = Auth::currentUser();

$branches = $db->fetchAll("SELECT id,name FROM branches WHERE is_active=1");
$sel_branch = (int)($_GET['branch_id'] ?? ($cu['branch_id'] ?? $branches[0]['id'] ?? 1));

// Load all settings for selected branch
$settings_rows = $db->fetchAll("SELECT setting_key, setting_value FROM settings WHERE branch_id=? OR branch_id IS NULL", [$sel_branch]);
$settings = [];
foreach ($settings_rows as $s) $settings[$s['setting_key']] = $s['setting_value'];

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    Helper::redirect(BASE_URL.'/modules/settings/index.php?branch_id='.$sel_branch);
}

$pageTitle = 'Settings';
$breadcrumbs = [['label'=>'Settings']];
require_once ROOT_PATH . '/includes/header.php';
?>
<div class="vp-page-header d-print-none">
  <div class="d-flex align-items-center justify-content-between">
    <h1 class="vp-page-title"><?= Lang::t('settings') ?></h1>
    <?php if (count($branches) > 1): ?>
    <form method="get" class="d-flex gap-2">
      <select name="branch_id" class="form-select" style="max-width:200px;" onchange="this.form.submit()">
        <?php foreach ($branches as $b): ?><option value="<?= $b['id'] ?>" <?= $sel_branch==$b['id']?'selected':'' ?>><?= Helper::sanitize($b['name']) ?></option><?php endforeach; ?>
      </select>
    </form>
    <?php endif; ?>
  </div>
</div>

<form method="post">
<div class="row">
  <div class="col-lg-8">

    <!-- Company Info -->
    <div class="card vp-card mb-3">
      <div class="card-header"><h3 class="card-title">Company Information</h3></div>
      <div class="card-body">
        <div class="mb-3"><label class="form-label">Company Name</label><input type="text" name="settings[company_name]" class="form-control" value="<?= Helper::sanitize($settings['company_name']??APP_NAME) ?>"></div>
        <div class="mb-3"><label class="form-label">Tagline</label><input type="text" name="settings[company_tagline]" class="form-control" value="<?= Helper::sanitize($settings['company_tagline']??'') ?>" placeholder="e.g. Your Dream Wedding, Our Expertise"></div>
        <div class="mb-3"><label class="form-label">Address</label><textarea name="settings[company_address]" class="form-control" rows="2"><?= Helper::sanitize($settings['company_address']??'') ?></textarea></div>
        <div class="row mb-3">
          <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="settings[company_phone]" class="form-control" value="<?= Helper::sanitize($settings['company_phone']??'') ?>"></div>
          <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="settings[company_email]" class="form-control" value="<?= Helper::sanitize($settings['company_email']??'') ?>"></div>
        </div>
        <div class="mb-3"><label class="form-label">Website</label><input type="text" name="settings[company_website]" class="form-control" value="<?= Helper::sanitize($settings['company_website']??'') ?>" placeholder="https://"></div>
      </div>
    </div>

    <!-- Invoice / Quotation -->
    <div class="card vp-card mb-3">
      <div class="card-header"><h3 class="card-title">Invoice &amp; Quotation</h3></div>
      <div class="card-body">
        <div class="mb-3"><label class="form-label">Invoice Terms &amp; Conditions</label><textarea name="settings[invoice_terms]" class="form-control" rows="3"><?= Helper::sanitize($settings['invoice_terms']??'') ?></textarea></div>
        <div class="mb-3"><label class="form-label">Quotation Terms &amp; Conditions</label><textarea name="settings[quotation_terms]" class="form-control" rows="3"><?= Helper::sanitize($settings['quotation_terms']??'') ?></textarea></div>
        <div class="row mb-3">
          <div class="col-md-6"><label class="form-label">Default Tax % (VAT/NBT)</label><input type="number" name="settings[default_tax_percent]" class="form-control" step="0.01" min="0" max="100" value="<?= $settings['default_tax_percent']??'0' ?>"></div>
          <div class="col-md-6"><label class="form-label">Invoice Due Days</label><input type="number" name="settings[invoice_due_days]" class="form-control" min="0" value="<?= $settings['invoice_due_days']??'7' ?>"></div>
        </div>
      </div>
    </div>

    <!-- Bank Details -->
    <div class="card vp-card mb-3">
      <div class="card-header"><h3 class="card-title">Bank Details (for Invoices)</h3></div>
      <div class="card-body">
        <div class="row mb-3">
          <div class="col-md-6"><label class="form-label">Bank Name</label><input type="text" name="settings[bank_name]" class="form-control" value="<?= Helper::sanitize($settings['bank_name']??'') ?>"></div>
          <div class="col-md-6"><label class="form-label">Account Name</label><input type="text" name="settings[bank_account_name]" class="form-control" value="<?= Helper::sanitize($settings['bank_account_name']??'') ?>"></div>
        </div>
        <div class="row mb-3">
          <div class="col-md-6"><label class="form-label">Account Number</label><input type="text" name="settings[bank_account_number]" class="form-control" value="<?= Helper::sanitize($settings['bank_account_number']??'') ?>"></div>
          <div class="col-md-6"><label class="form-label">Branch</label><input type="text" name="settings[bank_branch]" class="form-control" value="<?= Helper::sanitize($settings['bank_branch']??'') ?>"></div>
        </div>
      </div>
    </div>

    <!-- Notifications -->
    <div class="card vp-card mb-3">
      <div class="card-header"><h3 class="card-title">System</h3></div>
      <div class="card-body">
        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">Currency Symbol</label>
            <input type="text" name="settings[currency_symbol]" class="form-control" value="<?= Helper::sanitize($settings['currency_symbol']??'Rs.') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Date Format</label>
            <select name="settings[date_format]" class="form-select">
              <option value="d M Y" <?= ($settings['date_format']??'d M Y')==='d M Y'?'selected':'' ?>>25 Jun 2026</option>
              <option value="d/m/Y" <?= ($settings['date_format']??'')==='d/m/Y'?'selected':'' ?>>25/06/2026</option>
              <option value="Y-m-d" <?= ($settings['date_format']??'')==='Y-m-d'?'selected':'' ?>>2026-06-25</option>
            </select>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<div class="sticky-footer d-print-none" style="position:fixed;bottom:0;left:0;right:0;background:#fff;border-top:1px solid #e8e8e8;padding:12px 24px;z-index:100">
  <button type="submit" class="btn btn-vp-gold">Save Settings</button>
  <a href="<?= BASE_URL ?>/index.php" class="btn btn-ghost-secondary ms-2">Cancel</a>
</div>
<div style="height:70px"></div>
</form>
<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
