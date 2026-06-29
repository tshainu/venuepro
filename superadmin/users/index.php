<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();

if (!Auth::isSuperAdmin()) {
    Helper::redirect(BASE_URL . '/index.php');
}

$db = Database::getInstance();

// Handle delete
if ($_GET['action'] === 'delete' && $_GET['id']) {
    $id = (int)$_GET['id'];
    if ($id !== 1) { // Prevent deleting super admin
        $db->execute("DELETE FROM users WHERE id = ?", [$id]);
        Helper::redirect(BASE_URL . '/superadmin/users/index.php');
    }
}

// Fetch users
$users = $db->fetchAll(
    "SELECT u.id, u.name, u.email, u.user_id, u.username, u.phone, u.role_id, u.branch_id, u.is_active, u.created_at, r.name as role_name, b.name as branch_name
     FROM users u
     LEFT JOIN roles r ON u.role_id = r.id
     LEFT JOIN branches b ON u.branch_id = b.id
     ORDER BY u.created_at DESC"
);

$pageTitle = 'Manage Users';
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
.user-table { background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,.06); border: 1px solid #edf0f8; }
.user-table table { width: 100%; border-collapse: collapse; }
.user-table th { background: #f9fafb; padding: .9rem 1.2rem; text-align: left; font-size: .8rem; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: .02em; border-bottom: 1.5px solid #e5e7eb; }
.user-table td { padding: .9rem 1.2rem; border-bottom: 1px solid #f3f4f6; font-size: .9rem; }
.user-table tbody tr:hover { background: #f9fafb; }
.user-badge { display: inline-flex; align-items: center; gap: .5rem; background: #eff6ff; border: 1px solid #3b82f6; color: #1e40af; padding: .4rem .8rem; border-radius: 6px; font-size: .8rem; font-weight: 600; }
.user-badge.admin { background: #fef3c7; border-color: #f59e0b; color: #b45309; }
.user-badge.staff { background: #dbeafe; border-color: #2563eb; color: #1e40af; }
.status-active { color: #059669; font-weight: 700; }
.status-inactive { color: #dc2626; font-weight: 700; }
.btn-sm { padding: .4rem .8rem; border: none; border-radius: 6px; cursor: pointer; font-size: .8rem; font-weight: 600; transition: all .2s; text-decoration: none; display: inline-block; }
.btn-edit { background: #dbeafe; color: #1e40af; }
.btn-edit:hover { background: #bfdbfe; }
.btn-delete { background: #fee2e2; color: #991b1b; }
.btn-delete:hover { background: #fecaca; }
.btn-toggle { background: #f3f4f6; color: #374151; }
.btn-toggle:hover { background: #e5e7eb; }
.action-cell { display: flex; gap: .5rem; flex-wrap: wrap; min-width: 180px; }
</style>

<div class="container-xl py-4">
  <div class="row mb-4">
    <div class="col-12">
      <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem;">
        <div>
          <h1 style="margin: 0; font-size: 1.75rem; font-weight: 800; color: #0c1a35;">Manage Users</h1>
          <p style="margin: .5rem 0 0; color: #6b7280; font-size: .9rem;">Create, edit, and manage system users</p>
        </div>
        <a href="<?= BASE_URL ?>/superadmin/users/create.php" class="btn btn-primary">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align: middle; margin-right: 6px;"><path d="M12 5v14M5 12h14"/></svg>
          Create User
        </a>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-12">
      <div class="user-table">
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>User ID</th>
              <th>Role</th>
              <th>Branch</th>
              <th>Status</th>
              <th>Created</th>
              <th style="text-align: center;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u): ?>
              <tr>
                <td>
                  <div style="font-weight: 600; color: #1f2937;"><?= htmlspecialchars($u['name']) ?></div>
                  <div style="font-size: .8rem; color: #9ca3af;">@<?= htmlspecialchars($u['username']) ?></div>
                </td>
                <td style="font-size: .85rem;"><?= htmlspecialchars($u['email']) ?></td>
                <td><code style="background: #f3f4f6; padding: .2rem .6rem; border-radius: 4px; font-size: .8rem; color: #374151;"><?= htmlspecialchars($u['user_id']) ?></code></td>
                <td>
                  <span class="user-badge <?= strpos($u['role_name'], 'Admin') !== false ? 'admin' : 'staff' ?>">
                    <?= htmlspecialchars($u['role_name']) ?>
                  </span>
                </td>
                <td style="color: #6b7280;"><?= htmlspecialchars($u['branch_name'] ?? '—') ?></td>
                <td class="<?= $u['is_active'] ? 'status-active' : 'status-inactive' ?>">
                  <?= $u['is_active'] ? '✓ Active' : '✗ Inactive' ?>
                </td>
                <td style="color: #9ca3af; font-size: .85rem;"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                <td style="text-align: center;">
                  <div class="action-cell" style="justify-content: center;">
                    <a href="<?= BASE_URL ?>/superadmin/users/edit.php?id=<?= $u['id'] ?>" class="btn-sm btn-edit">Edit</a>
                    <?php if ($u['id'] !== 1): ?>
                      <a href="?action=delete&id=<?= $u['id'] ?>" class="btn-sm btn-delete" onclick="return confirm('Delete this user?')">Delete</a>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php if (!$users): ?>
        <div style="text-align: center; padding: 3rem 2rem; background: #fff; border-radius: 12px; color: #9ca3af;">
          <div style="font-size: 2.5rem; margin-bottom: 1rem; opacity: .3;">👥</div>
          <div style="font-size: 1rem; font-weight: 600;">No users found</div>
          <a href="<?= BASE_URL ?>/superadmin/users/create.php" style="color: #c9a84c; text-decoration: none; font-weight: 700; margin-top: .5rem; display: inline-block;">Create the first user →</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
