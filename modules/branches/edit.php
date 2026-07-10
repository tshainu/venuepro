<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
if (!Auth::hasRole(['super_admin','admin','hall_manager','owner'])) { Helper::flash('error','Admin access required.'); Helper::redirect(BASE_URL.'/index.php'); }
$db = Database::getInstance();
$id = (int)($_GET['id'] ?? 0);
	$br = $db->fetchOne("SELECT * FROM branches WHERE id=?", [$id]);
	if (!$br) { Helper::flash('error','Branch not found.'); Helper::redirect(BASE_URL.'/modules/branches/index.php'); }
	
	// Load branch-specific settings
	$settings_rows = $db->fetchAll("SELECT setting_key, setting_value FROM settings WHERE branch_id=?", [$id]);
	$settings = [];
	foreach ($settings_rows as $s) $settings[$s['setting_key']] = $s['setting_value'];
	
	$errors = [];
	
	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	    $name      = trim($_POST['name'] ?? '');
	    $address   = trim($_POST['address'] ?? '');
	    $phone     = trim($_POST['phone'] ?? '');
	    $email     = trim($_POST['email'] ?? '');
	    $is_active = isset($_POST['is_active']) ? 1 : 0;
	    
	    if (!$name) $errors[] = 'Name required.';
	    
	    if (!$errors) {
	        Logger::log('edit','branches',$id,$br['name'],'name:'.$br['name'],'name:'.$name,'Branch updated');
	        $db->execute("UPDATE branches SET name=?,address=?,phone=?,email=?,is_active=? WHERE id=?", [$name,$address,$phone,$email,$is_active,$id]);
	        
	        // Handle branch-specific settings
	        $msg_settings = $_POST['settings'] ?? [];
	        // Checkboxes handling
	        if (!isset($msg_settings['enable_birthday_messages'])) $msg_settings['enable_birthday_messages'] = '0';
	        if (!isset($msg_settings['enable_anniversary_messages'])) $msg_settings['enable_anniversary_messages'] = '0';
	        
	        foreach ($msg_settings as $key => $val) {
	            $key = preg_replace('/[^a-z0-9_]/','',$key);
	            $val = trim($val);
	            $exists = $db->fetchOne("SELECT id FROM settings WHERE setting_key=? AND branch_id=?", [$key, $id]);
	            if ($exists) {
	                $db->execute("UPDATE settings SET setting_value=? WHERE setting_key=? AND branch_id=?", [$val, $key, $id]);
	            } else {
	                $db->insert("INSERT INTO settings (setting_key,setting_value,branch_id,setting_group) VALUES (?,?,?,?)", [$key, $val, $id, 'messaging']);
	            }
	        }
	        
	        Helper::flash('success','Branch updated.');
	        Helper::redirect(BASE_URL.'/modules/branches/index.php');
	    }
	} else { 
	    $_POST = $br; 
	    $_POST['is_active'] = $br['is_active'] ? 'on' : ''; 
	    $_POST['settings'] = $settings;
	}

$pageTitle = 'Edit Branch';
$breadcrumbs = [['label'=>'Branches','url'=>BASE_URL.'/modules/branches/index.php'],['label'=>'Edit']];
require_once ROOT_PATH . '/includes/header.php';
?>
<div class="vp-page-header d-print-none"><h1 class="vp-page-title">Edit Branch</h1></div>
<div class="row justify-content-center">
  <div class="col-lg-6">
    <form method="post" class="card vp-card">
      <div class="card-header"><h3 class="card-title">Edit: <?= Helper::sanitize($br['name']) ?></h3></div>
      <div class="card-body">
        <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e): ?><li><?= Helper::sanitize($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <div class="mb-3"><label class="form-label required">Branch Name</label><input type="text" name="name" class="form-control" value="<?= Helper::sanitize($_POST['name']??'') ?>" required></div>
        <div class="mb-3"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="2"><?= Helper::sanitize($_POST['address']??'') ?></textarea></div>
        <div class="row mb-3">
          <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="<?= Helper::sanitize($_POST['phone']??'') ?>"></div>
          <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= Helper::sanitize($_POST['email']??'') ?>"></div>
        </div>
	        <label class="form-check mb-4"><input type="checkbox" name="is_active" class="form-check-input" <?= isset($_POST['is_active'])&&$_POST['is_active']?'checked':'' ?>><span class="form-check-label">Active</span></label>

	        <hr>
	        <h4 class="mb-3">💬 Messaging Settings (WhatsApp)</h4>
	        
	        <div class="mb-3">
	          <label class="form-label">WhatsApp API Status</label>
	          <select name="settings[whatsapp_enabled]" class="form-select">
	            <option value="1" <?= (($_POST['settings']['whatsapp_enabled']??$settings['whatsapp_enabled']??'0')==='1')?'selected':'' ?>>Enabled</option>
	            <option value="0" <?= (($_POST['settings']['whatsapp_enabled']??$settings['whatsapp_enabled']??'0')==='0')?'selected':'' ?>>Disabled</option>
	          </select>
	        </div>
	        
	        <div class="mb-3">
	          <label class="form-label">WhatsApp Business ID</label>
	          <input type="text" name="settings[whatsapp_business_id]" class="form-control" value="<?= Helper::sanitize($_POST['settings']['whatsapp_business_id']??$settings['whatsapp_business_id']??'') ?>" placeholder="Enter Business ID">
	        </div>
	        
	        <div class="mb-3">
	          <label class="form-label">WhatsApp Access Token</label>
	          <input type="password" name="settings[whatsapp_access_token]" class="form-control" value="<?= Helper::sanitize($_POST['settings']['whatsapp_access_token']??$settings['whatsapp_access_token']??'') ?>" placeholder="Enter Access Token">
	        </div>
	        
	        <div class="mb-3">
	          <label class="form-label">WhatsApp Phone Number ID</label>
	          <input type="text" name="settings[whatsapp_phone_id]" class="form-control" value="<?= Helper::sanitize($_POST['settings']['whatsapp_phone_id']??$settings['whatsapp_phone_id']??'') ?>" placeholder="Enter Phone Number ID">
	        </div>

	        <h4 class="mt-4 mb-3">🎂 Auto Messages</h4>
	        
	        <div class="mb-3">
	          <div class="form-check form-switch">
	            <input class="form-check-input" type="checkbox" name="settings[enable_birthday_messages]" id="enable_birthday_messages" value="1" <?= (($_POST['settings']['enable_birthday_messages']??$settings['enable_birthday_messages']??'0')==='1')?'checked':'' ?>>
	            <label class="form-check-label" for="enable_birthday_messages">Enable Auto Birthday Messages</label>
	          </div>
	        </div>
	        
	        <div class="mb-3">
	          <label class="form-label">Birthday Message Template</label>
	          <textarea name="settings[birthday_message_template]" class="form-control" rows="3" placeholder="Happy Birthday {name}! ..."><?= Helper::sanitize($_POST['settings']['birthday_message_template']??$settings['birthday_message_template']??'') ?></textarea>
	          <div class="form-text text-muted">Use {name} as a placeholder for the customer's name.</div>
	        </div>
	        
	        <hr>
	        
	        <div class="mb-3">
	          <div class="form-check form-switch">
	            <input class="form-check-input" type="checkbox" name="settings[enable_anniversary_messages]" id="enable_anniversary_messages" value="1" <?= (($_POST['settings']['enable_anniversary_messages']??$settings['enable_anniversary_messages']??'0')==='1')?'checked':'' ?>>
	            <label class="form-check-label" for="enable_anniversary_messages">Enable Auto Anniversary Messages</label>
	          </div>
	        </div>
	        
	        <div class="mb-3">
	          <label class="form-label">Anniversary Message Template</label>
	          <textarea name="settings[anniversary_message_template]" class="form-control" rows="3" placeholder="Happy Anniversary {name}! ..."><?= Helper::sanitize($_POST['settings']['anniversary_message_template']??$settings['anniversary_message_template']??'') ?></textarea>
	          <div class="form-text text-muted">Use {name} as a placeholder for the couple's names.</div>
	        </div>
	      </div>
	      <div class="card-footer d-flex gap-2">
        <button type="submit" class="btn btn-vp-gold">Update Branch</button>
        <a href="<?= BASE_URL ?>/modules/branches/index.php" class="btn btn-vp-outline">Cancel</a>
      </div>
    </form>
  </div>
</div>
<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
