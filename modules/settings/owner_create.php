<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();

// Only Super Admin can create owners
if (!Auth::isSuperAdmin()) {
    Helper::flash('error', 'Only Super Admin can create business owners.');
    Helper::redirect(BASE_URL . '/index.php');
}

$db = Database::getInstance();
$cu = Auth::currentUser();
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $business_name = trim($_POST['business_name'] ?? '');
    $owner_name    = trim($_POST['owner_name'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $mobile        = trim($_POST['mobile'] ?? '');
    $password      = trim($_POST['password'] ?? '');
    $password_conf = trim($_POST['password_confirm'] ?? '');
    $address       = trim($_POST['address'] ?? '');
    
    // Validation
    if (!$business_name) $errors[] = 'Business name is required.';
    if (!$owner_name) $errors[] = 'Owner name is required.';
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (!$mobile || !preg_match('/^07\d{8}$/', $mobile)) $errors[] = 'Valid mobile (07XXXXXXXX) is required.';
    if (!$password || strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $password_conf) $errors[] = 'Passwords do not match.';
    
    // Check if email already exists
    if (!$errors) {
        $existing = $db->fetchOne("SELECT id FROM users WHERE email=?", [$email]);
        if ($existing) $errors[] = 'Email already registered.';
    }
    
    if (!$errors) {
        try {
            // Start transaction
            $db->execute("START TRANSACTION");
            
            // Create branch for this owner
            $branch_id = $db->insert(
                "INSERT INTO branches (name, address, is_active) VALUES (?, ?, 1)",
                [$business_name, $address]
            );
            
            // Get Owner role ID
            $owner_role = $db->fetchOne("SELECT id FROM roles WHERE slug='owner'");
            if (!$owner_role) {
                throw new Exception('Owner role not found.');
            }
            
            // Create owner user
            $password_hash = password_hash($password, PASSWORD_BCRYPT);
            $user_id = $db->insert(
                "INSERT INTO users (name, email, mobile, password, role_id, branch_id, is_active, created_by) 
                 VALUES (?, ?, ?, ?, ?, ?, 1, ?)",
                [$owner_name, $email, $mobile, $password_hash, $owner_role['id'], $branch_id, $cu['id']]
            );
            
            // Log the action
            Logger::log('create', 'owners', $user_id, $owner_name, null, 
                       'business:' . $business_name . ' email:' . $email, 
                       'Owner account created by Super Admin');
            
            // Commit transaction
            $db->execute("COMMIT");
            
            $success = true;
            Helper::flash('success', "Business owner '$owner_name' created successfully for '$business_name'.");
            Helper::redirect(BASE_URL . '/modules/settings/index.php?tab=owners');
            
        } catch (Exception $e) {
            $db->execute("ROLLBACK");
            $errors[] = 'Error creating owner: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Create Business Owner';
$breadcrumbs = [
    ['label' => 'Settings', 'url' => BASE_URL . '/modules/settings/index.php'],
    ['label' => 'Create Owner']
];
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="vp-page-header">
    <h1 class="vp-page-title">👨‍💼 Create Business Owner</h1>
    <p class="vp-page-sub">Register a new business owner account</p>
</div>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card vp-card">
            <div class="card-header">
                <h3 class="card-title">Business Owner Registration</h3>
            </div>
            <div class="card-body">
                <?php if ($errors): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach($errors as $e): ?>
                        <li><?= Helper::sanitize($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="row g-3 mb-3">
                        <div class="col-12">
                            <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.8rem;">
                              <div style="width:4px;height:16px;background:linear-gradient(180deg,#c9a84c,#e8c96a);border-radius:2px;"></div>
                              <span style="font-size:.72rem;font-weight:800;color:#6b7280;text-transform:uppercase;letter-spacing:.08em;">Business Information</span>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-700">Business Name <span class="text-danger">*</span></label>
                            <input type="text" name="business_name" class="form-control" required 
                                   value="<?= Helper::sanitize($_POST['business_name'] ?? '') ?>"
                                   placeholder="e.g. Star Halls & Events">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-700">Address</label>
                            <textarea name="address" class="form-control" rows="2" 
                                      placeholder="Business address..."><?= Helper::sanitize($_POST['address'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12">
                            <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.8rem;">
                              <div style="width:4px;height:16px;background:linear-gradient(180deg,#c9a84c,#e8c96a);border-radius:2px;"></div>
                              <span style="font-size:.72rem;font-weight:800;color:#6b7280;text-transform:uppercase;letter-spacing:.08em;">Owner Information</span>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-700">Owner Name <span class="text-danger">*</span></label>
                            <input type="text" name="owner_name" class="form-control" required 
                                   value="<?= Helper::sanitize($_POST['owner_name'] ?? '') ?>"
                                   placeholder="Full name of business owner">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-700">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" required 
                                   value="<?= Helper::sanitize($_POST['email'] ?? '') ?>"
                                   placeholder="owner@example.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-700">Mobile <span class="text-danger">*</span></label>
                            <input type="tel" name="mobile" class="form-control" required 
                                   value="<?= Helper::sanitize($_POST['mobile'] ?? '') ?>"
                                   placeholder="07XXXXXXXX">
                            <small class="text-muted">Format: 07XXXXXXXX</small>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12">
                            <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.8rem;">
                              <div style="width:4px;height:16px;background:linear-gradient(180deg,#c9a84c,#e8c96a);border-radius:2px;"></div>
                              <span style="font-size:.72rem;font-weight:800;color:#6b7280;text-transform:uppercase;letter-spacing:.08em;">Login Credentials</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-700">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" required 
                                   placeholder="Minimum 6 characters">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-700">Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" name="password_confirm" class="form-control" required 
                                   placeholder="Confirm password">
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-vp-gold">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
                            Create Owner Account
                        </button>
                        <a href="<?= BASE_URL ?>/modules/settings/index.php?tab=owners" class="btn btn-outline-secondary">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
