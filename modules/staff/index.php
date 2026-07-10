<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();

// Only Owner and above can access staff management
if (!Auth::hasPrivilege('view_staff')) {
    Helper::flash('error', 'Access denied.');
    Helper::redirect(BASE_URL . '/index.php');
}

$db = Database::getInstance();
$cu = Auth::currentUser();

// Get staff list for owner's branch
$staff_query = "SELECT u.*, r.name as role_name FROM users u 
                LEFT JOIN roles r ON r.id = u.role_id 
                WHERE u.branch_id = ? AND u.role_id != (SELECT id FROM roles WHERE slug='owner')
                ORDER BY u.name";
$staff = $db->fetchAll($staff_query, [$cu['branch_id']]);

$pageTitle = 'Staff Management';
$breadcrumbs = [['label' => 'Staff Management']];
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="vp-page-header">
    <div>
        <h1 class="vp-page-title">👥 Staff Management</h1>
        <p class="vp-page-sub"><?= count($staff) ?> staff members</p>
    </div>
    <?php if (Auth::hasPrivilege('create_staff')): ?>
    <a href="<?= BASE_URL ?>/modules/staff/create.php" class="btn btn-vp-gold">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
        Add Staff Member
    </a>
    <?php endif; ?>
</div>

<div class="row">
    <div class="col-12">
        <div class="card vp-card">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Mobile</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($staff)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                <div style="font-size:2rem;margin-bottom:.5rem;">👥</div>
                                No staff members yet. <a href="<?= BASE_URL ?>/modules/staff/create.php">Add one now</a>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach($staff as $member): ?>
                            <tr>
                                <td>
                                    <strong><?= Helper::sanitize($member['name']) ?></strong>
                                </td>
                                <td><?= Helper::sanitize($member['email']) ?></td>
                                <td><?= Helper::sanitize($member['mobile']) ?></td>
                                <td>
                                    <span class="badge" style="background:<?= $member['role_id'] == 2 ? '#c9a84c' : '#6b7280' ?>">
                                        <?= Helper::sanitize($member['role_name'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($member['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('d M Y', strtotime($member['created_at'])) ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <?php if (Auth::hasPrivilege('edit_staff')): ?>
                                        <a href="<?= BASE_URL ?>/modules/staff/edit.php?id=<?= $member['id'] ?>" class="btn btn-outline-secondary" title="Edit">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        </a>
                                        <?php endif; ?>
                                        <?php if (Auth::hasPrivilege('delete_staff')): ?>
                                        <a href="<?= BASE_URL ?>/modules/staff/delete.php?id=<?= $member['id'] ?>" class="btn btn-outline-danger" title="Delete" onclick="return confirm('Delete this staff member?')">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
