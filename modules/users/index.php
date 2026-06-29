<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();

if (!Auth::hasRole(['hall_manager'])) {
    Helper::redirect(BASE_URL . '/index.php');
}

$db = Database::getInstance();
$cu = Auth::currentUser();
$branch_id = $cu['branch_id'];

if (!$branch_id) {
    Helper::redirect(BASE_URL . '/index.php');
}

// Handle delete
if ($_GET['action'] === 'delete' && $_GET['id']) {
    $id = (int)$_GET['id'];
    $userCheck = $db->fetchOne("SELECT branch_id FROM users WHERE id = ?", [$id]);
    if ($userCheck && $userCheck['branch_id'] == $branch_id) {
        $db->execute("DELETE FROM users WHERE id = ?", [$id]);
    }
    Helper::redirect(BASE_URL . '/modules/users/index.php');
}

// Fetch staff members for this branch only
$staff = $db->fetchAll(
    "SELECT u.id, u.name, u.email, u.user_id, u.username, u.phone, u.is_active, u.created_at, r.name as role_name
     FROM users u
     LEFT JOIN roles r ON u.role_id = r.id
     WHERE u.branch_id = ? AND u.role_id != (SELECT id FROM roles WHERE slug = 'super_admin')
     ORDER BY u.created_at DESC",
    [$branch_id]
);

$branch = $db->fetchOne("SELECT name FROM branches WHERE id = ?", [$branch_id]);

$pageTitle = 'Manage Staff';
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
.staff-table { background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,.06); border: 1px solid #edf0f8; }
.staff-table table { width: 100%; border-collapse: collapse; }
.staff-table th { background: #f9fafb; padding: .9rem 1.2rem; text-align: left; font-size: .8rem; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: .02em; border-bottom: 1.5px solid #e5e7eb; }
.staff-table td { padding: .9rem 1.2rem; border-bottom: 1px solid #f3f4f6; font-size: .9rem; }
.staff-table tbody tr:hover { background: #f9fafb; }
.role-badge { display: inline-flex; align-items: center; gap: .5rem; background: #dbeafe; border: 1px solid #3b82f6; color: #1e40af; padding: .4rem .8rem; border-radius: 6px; font-size: .8rem; font-weight: 600; }
.status-active { color: #059669; font-weight: 700; }
.status-inactive { color: #dc2626; font-weight: 700; }
.btn-sm { padding: .4rem .8rem; border: none; border-radius: 6px; cursor: pointer; font-size: .8rem; font-weight: 600; transition: all .2s; text-decoration: none; display: inline-block; }
.btn-edit { background: #dbeafe; color: #1e40af; }
.btn-edit:hover { background: #bfdbfe; }
.btn-delete { background: #fee2e2; color: #991b1b; }
.btn-delete:hover { background: #fecaca; }
.action-cell { display: flex; gap: .5rem; flex-wrap: wrap; min-width: 180px; }
</style>

<div class="container-xl py-4">
  <div class="row mb-4">
    <div class="col-12">
      <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem;">
        <div>
          <h1 style="margin: 0; font-size: 1.75rem; font-weight: 800; color: #0c1a35;">Manage Staff</h1>
          <p style="margin: .5rem 0 0; color: #6b7280; font-size: .9rem;"><?= htmlspecialchars($branch['name']) ?></p>
        </div>
        <a href="<?= BASE_URL ?>/modules/users/create.php" class="btn btn-primary">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align: middle; margin-right: 6px;"><path d="M12 5v14M5 12h14"/></svg>
          Add Staff Member
        </a>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-12">
      <div class="staff-table">
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>User ID</th>
              <th>Role</th>
              <th>Status</th>
              <th>Created</th>
              <th style="text-align: center;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($staff as $s): ?>
              <tr>
                <td>
                  <div style="font-weight: 600; color: #1f2937;"><?= htmlspecialchars($s['name']) ?></div>
                  <div style="font-size: .8rem; color: #9ca3af;">@<?= htmlspecialchars($s['username']) ?></div>
                </td>
                <td style="font-size: .85rem;"><?= htmlspecialchars($s['email']) ?></td>
                <td><code style="background: #f3f4f6; padding: .2rem .6rem; border-radius: 4px; font-size: .8rem;"><?= htmlspecialchars($s['user_id']) ?></code></td>
                <td><span class="role-badge"><?= htmlspecialchars($s['role_name']) ?></span></td>
                <td class="<?= $s['is_active'] ? 'status-active' : 'status-inactive' ?>">
                  <?= $s['is_active'] ? '✓ Active' : '✗ Inactive' ?>
                </td>
                <td style="color: #9ca3af; font-size: .85rem;"><?= date('M d, Y', strtotime($s['created_at'])) ?></td>
                <td style="text-align: center;">
                  <div class="action-cell" style="justify-content: center;">
                    <a href="<?= BASE_URL ?>/modules/users/edit.php?id=<?= $s['id'] ?>" class="btn-sm btn-edit">Edit</a>
                    <a href="?action=delete&id=<?= $s['id'] ?>" class="btn-sm btn-delete" onclick="return confirm('Delete this staff member?')">Delete</a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php if (!$staff): ?>
        <div style="text-align: center; padding: 3rem 2rem; background: #fff; border-radius: 12px; color: #9ca3af;">
          <div style="font-size: 2.5rem; margin-bottom: 1rem; opacity: .3;">👥</div>
          <div style="font-size: 1rem; font-weight: 600;">No staff members yet</div>
          <a href="<?= BASE_URL ?>/modules/users/create.php" style="color: #c9a84c; text-decoration: none; font-weight: 700; margin-top: .5rem; display: inline-block;">Add the first staff member →</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
