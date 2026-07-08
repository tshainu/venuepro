<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();

// Only Owner and above can create staff
if (!Auth::hasPrivilege('create_staff')) {
    Helper::flash('error', 'Access denied.');
    Helper::redirect(BASE_URL . '/index.php');
}

$db = Database::getInstance();
$cu = Auth::currentUser();
$errors = [];

// Get available roles (exclude Super Admin and Owner)
$roles = $db->fetchAll(
    "SELECT * FROM roles WHERE slug NOT IN ('super_admin', 'owner') ORDER BY name"
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name          = trim($_POST['name'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $mobile        = trim($_POST['mobile'] ?? '');
    $role_id       = (int)($_POST['role_id'] ?? 0);
    $password      = trim($_POST['password'] ?? '');
    $password_conf = trim($_POST['password_confirm'] ?? '');
    $is_active     = isset($_POST['is_active']) ? 1 : 0;
    
    // Validation
    if (!$name) $errors[] = 'Name is required.';
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (!$mobile || !preg_match('/^07\d{8}$/', $mobile)) $errors[] = 'Valid mobile (07XXXXXXXX) is required.';
    if (!$role_id) $errors[] = 'Role is required.';
    if (!$password || strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $password_conf) $errors[] = 'Passwords do not match.';
    
    // Check if email already exists
    if (!$errors) {
        $existing = $db->fetchOne("SELECT id FROM users WHERE email=?", [$email]);
        if ($existing) $errors[] = 'Email already registered.';
    }
    
    if (!$errors) {
        try {
            $password_hash = password_hash($password, PASSWORD_BCRYPT);
            $user_id = $db->insert(
                "INSERT INTO users (name, email, mobile, password, role_id, branch_id, is_active, created_by) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$name, $email, $mobile, $password_hash, $role_id, $cu['branch_id'], $is_active, $cu['id']]
            );
            
            Logger::log('create', 'staff', $user_id, $name, null, 'role_id:' . $role_id, 'Staff member created');
            
            Helper::flash('success', "Staff member '$name' created successfully.");
            Helper::redirect(BASE_URL . '/modules/staff/index.php');
            
        } catch (Exception $e) {
            $errors[] = 'Error creating staff: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Add Staff Member';
$breadcrumbs = [
    ['label' => 'Staff Management', 'url' => BASE_URL . '/modules/staff/index.php'],
    ['label' => 'Add Staff']
];
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="vp-page-header">
    <div class="d-flex align-items-center gap-3">
        <div class="vp-page-icon" style="background: linear-gradient(135deg, #8a5e0c, #c9a84c); width: 56px; height: 56px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; box-shadow: 0 4px 14px rgba(201,168,76,.35);">
            👤
        </div>
        <div>
            <h1 class="vp-page-title" style="margin: 0;">Add Staff Member</h1>
            <p style="margin: 0.3rem 0 0; color: rgba(12,26,53,.6); font-size: 0.85rem;">Create a new staff member account</p>
        </div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <!-- Error Messages -->
        <?php if ($errors): ?>
        <div class="alert alert-danger" style="border-left: 4px solid #dc2626; background: #fef2f2; border-radius: 10px; margin-bottom: 1.5rem;">
            <div style="display: flex; gap: 0.75rem; align-items: flex-start;">
                <svg style="width: 20px; height: 20px; flex-shrink: 0; margin-top: 2px; color: #dc2626;" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
                <div>
                    <h5 style="margin: 0 0 0.5rem; color: #991b1b; font-weight: 700;">Please fix the following errors:</h5>
                    <ul style="margin: 0; padding-left: 1.25rem; color: #7f1d1d;">
                        <?php foreach($errors as $e): ?>
                        <li style="margin-bottom: 0.25rem;"><?= Helper::sanitize($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Main Form Card -->
        <div class="vp-form-section">
            <div class="vp-form-section-title">
                <svg style="width: 18px; height: 18px; margin-right: 0.25rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Personal Information
            </div>

            <form method="POST">
                <!-- Full Name -->
                <div class="mb-4">
                    <label class="form-label" style="font-weight: 700; color: #1f2937;">Full Name <span style="color: #dc2626;">*</span></label>
                    <input type="text" name="name" class="form-control" required 
                           value="<?= Helper::sanitize($_POST['name'] ?? '') ?>"
                           placeholder="Enter staff member's full name"
                           style="border-radius: 10px; padding: 0.6rem 0.95rem; font-size: 0.85rem;">
                    <small style="color: #6b7280; display: block; margin-top: 0.35rem;">Full name as it should appear in the system</small>
                </div>

                <!-- Email & Mobile Row -->
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label" style="font-weight: 700; color: #1f2937;">Email <span style="color: #dc2626;">*</span></label>
                        <input type="email" name="email" class="form-control" required 
                               value="<?= Helper::sanitize($_POST['email'] ?? '') ?>"
                               placeholder="staff@example.com"
                               style="border-radius: 10px; padding: 0.6rem 0.95rem; font-size: 0.85rem;">
                        <small style="color: #6b7280; display: block; margin-top: 0.35rem;">Used for login and notifications</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" style="font-weight: 700; color: #1f2937;">Mobile <span style="color: #dc2626;">*</span></label>
                        <input type="tel" name="mobile" class="form-control" required 
                               value="<?= Helper::sanitize($_POST['mobile'] ?? '') ?>"
                               placeholder="07XXXXXXXX"
                               style="border-radius: 10px; padding: 0.6rem 0.95rem; font-size: 0.85rem;">
                        <small style="color: #6b7280; display: block; margin-top: 0.35rem;">Sri Lanka format (07XXXXXXXX)</small>
                    </div>
                </div>

                <!-- Role Selection with Descriptions -->
                <div class="mb-4">
                    <label class="form-label" style="font-weight: 700; color: #1f2937;">Role <span style="color: #dc2626;">*</span></label>
                    <select name="role_id" class="form-select" required 
                            style="border-radius: 10px; padding: 0.6rem 0.95rem; font-size: 0.85rem; border: 1.5px solid #dde3ef;">
                        <option value="">-- Select a Role --</option>
                        <?php foreach($roles as $role): ?>
                        <option value="<?= $role['id'] ?>" <?= ($_POST['role_id'] ?? 0) == $role['id'] ? 'selected' : '' ?>>
                            <?= Helper::sanitize($role['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <!-- Role Descriptions Card -->
                    <div style="background: linear-gradient(135deg, rgba(12,26,53,.03) 0%, rgba(201,168,76,.05) 100%); border-left: 4px solid #c9a84c; border-radius: 8px; padding: 1rem; margin-top: 0.75rem;">
                        <p style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; margin-bottom: 0.75rem;">Role Descriptions:</p>
                        <div style="display: grid; gap: 0.6rem; font-size: 0.8rem; color: #374151;">
                            <div style="display: flex; gap: 0.5rem;">
                                <span style="color: #c9a84c; font-weight: 700; min-width: 20px;">🔑</span>
                                <div><strong>General Manager:</strong> Full access to all features except branch management</div>
                            </div>
                            <div style="display: flex; gap: 0.5rem;">
                                <span style="color: #c9a84c; font-weight: 700; min-width: 20px;">📋</span>
                                <div><strong>Hall Manager:</strong> Manage bookings and customers for assigned halls</div>
                            </div>
                            <div style="display: flex; gap: 0.5rem;">
                                <span style="color: #c9a84c; font-weight: 700; min-width: 20px;">💰</span>
                                <div><strong>Accountant:</strong> View reports and manage invoices/payments</div>
                            </div>
                            <div style="display: flex; gap: 0.5rem;">
                                <span style="color: #c9a84c; font-weight: 700; min-width: 20px;">📞</span>
                                <div><strong>Receptionist:</strong> Create bookings and manage customer inquiries</div>
                            </div>
                            <div style="display: flex; gap: 0.5rem;">
                                <span style="color: #c9a84c; font-weight: 700; min-width: 20px;">👁️</span>
                                <div><strong>Staff:</strong> View-only access to bookings and customers</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Password Section -->
                <div style="background: #f9fafb; border-radius: 10px; padding: 1.2rem; margin-bottom: 1.5rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
                        <svg style="width: 18px; height: 18px; color: #c9a84c;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                        <h5 style="margin: 0; font-weight: 700; color: #1f2937; font-size: 0.9rem;">Login Credentials</h5>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" style="font-weight: 700; color: #1f2937;">Password <span style="color: #dc2626;">*</span></label>
                            <input type="password" name="password" class="form-control" required 
                                   placeholder="Minimum 6 characters"
                                   style="border-radius: 10px; padding: 0.6rem 0.95rem; font-size: 0.85rem;">
                            <small style="color: #6b7280; display: block; margin-top: 0.35rem;">At least 6 characters recommended</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" style="font-weight: 700; color: #1f2937;">Confirm Password <span style="color: #dc2626;">*</span></label>
                            <input type="password" name="password_confirm" class="form-control" required 
                                   placeholder="Re-enter password"
                                   style="border-radius: 10px; padding: 0.6rem 0.95rem; font-size: 0.85rem;">
                            <small style="color: #6b7280; display: block; margin-top: 0.35rem;">Must match the password above</small>
                        </div>
                    </div>
                </div>

                <!-- Status Section -->
                <div class="mb-4">
                    <label class="form-check" style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="checkbox" name="is_active" class="form-check-input" checked 
                               style="width: 18px; height: 18px; border-radius: 4px; cursor: pointer;">
                        <span class="form-check-label" style="margin: 0; font-weight: 600; color: #374151; cursor: pointer;">
                            Account Active
                        </span>
                    </label>
                    <small style="color: #6b7280; display: block; margin-top: 0.35rem; margin-left: 1.75rem;">Uncheck to create an inactive account</small>
                </div>

                <!-- Action Buttons -->
                <div style="display: flex; gap: 0.75rem; margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid #e5e7eb;">
                    <button type="submit" class="btn" style="background: linear-gradient(135deg, #8a5e0c, #c9a84c, #e8c96a); color: #fff; border: none; font-weight: 700; font-size: 0.85rem; border-radius: 10px; padding: 0.6rem 1.5rem; transition: all 0.18s; box-shadow: 0 3px 12px rgba(201,168,76,.4); display: flex; align-items: center; gap: 0.5rem;">
                        <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Create Staff Member
                    </button>
                    <a href="<?= BASE_URL ?>/modules/staff/index.php" class="btn" style="background: #fff; color: #374151; border: 1.5px solid #d1d9e6; font-weight: 600; font-size: 0.85rem; border-radius: 10px; padding: 0.6rem 1.5rem; transition: all 0.18s; text-decoration: none;">
                        Cancel
                    </a>
                </div>
            </form>
        </div>

        <!-- Info Box -->
        <div style="background: linear-gradient(135deg, rgba(37,99,235,.08) 0%, rgba(59,130,246,.04) 100%); border-left: 4px solid #2563eb; border-radius: 10px; padding: 1rem; margin-top: 1.5rem;">
            <div style="display: flex; gap: 0.75rem;">
                <svg style="width: 20px; height: 20px; flex-shrink: 0; color: #2563eb; margin-top: 2px;" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
                <div style="font-size: 0.8rem; color: #1e40af;">
                    <strong>Tip:</strong> Staff members can log in using their email address. Make sure to provide them with their login credentials securely.
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
