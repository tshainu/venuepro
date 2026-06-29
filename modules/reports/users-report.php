<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();

$db = Database::getInstance();
$cu = Auth::currentUser();

// Filter parameters
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';
$branch_filter = $_GET['branch'] ?? '';

// Super admin can see all, branch managers see their branch only
$branch_condition = '';
if (!Auth::isSuperAdmin()) {
    $branch_condition = "AND u.branch_id = " . $cu['branch_id'];
}

// Build query
$sql = "SELECT u.id, u.name, u.email, u.user_id, u.username, u.phone, u.role_id, u.branch_id, u.is_active, u.created_at, u.last_login, r.name as role_name, b.name as branch_name
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        LEFT JOIN branches b ON u.branch_id = b.id
        WHERE 1=1 $branch_condition";

$params = [];

if ($role_filter) {
    $sql .= " AND u.role_id = ?";
    $params[] = (int)$role_filter;
}

if ($status_filter !== '') {
    $sql .= " AND u.is_active = ?";
    $params[] = (int)$status_filter;
}

if ($branch_filter && Auth::isSuperAdmin()) {
    $sql .= " AND u.branch_id = ?";
    $params[] = (int)$branch_filter;
}

$sql .= " ORDER BY u.created_at DESC";

$users = $db->fetchAll($sql, $params);

// Get filter options
$roles = $db->fetchAll("SELECT id, name FROM roles WHERE slug != 'super_admin' ORDER BY name");
$branches = $db->fetchAll("SELECT id, name FROM branches ORDER BY name");

$pageTitle = 'Users Report';
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
.report-header { background: linear-gradient(135deg, #0a1628 0%, #162d5a 100%); border-radius: 16px; padding: 2rem; color: #fff; margin-bottom: 2rem; }
.report-title { font-size: 1.75rem; font-weight: 800; margin: 0 0 .5rem; }
.report-sub { color: rgba(255,255,255,.7); font-size: .9rem; margin: 0; }
.filter-bar { background: #fff; border: 1px solid #edf0f8; border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; display: flex; align-items: flex-end; gap: 1.5rem; flex-wrap: wrap; }
.filter-group { display: flex; flex-direction: column; gap: .4rem; min-width: 150px; }
.filter-label { font-size: .8rem; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: .02em; }
.filter-select { padding: .6rem .9rem; border: 1.5px solid #e5e7eb; border-radius: 8px; font-size: .9rem; }
.filter-btn { background: linear-gradient(135deg, #c9a84c, #e8c96a); color: #0c1a35; font-weight: 800; padding: .6rem 1.5rem; border: none; border-radius: 8px; cursor: pointer; transition: all .2s; }
.filter-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(201,168,76,.3); }
.clear-btn { background: #f3f4f6; color: #374151; font-weight: 700; padding: .6rem 1.5rem; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; display: inline-block; }
.clear-btn:hover { background: #e5e7eb; }
.table-card { background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,.06); border: 1px solid #edf0f8; }
.table-card table { width: 100%; border-collapse: collapse; }
.table-card th { background: #f9fafb; padding: .9rem 1.2rem; text-align: left; font-size: .8rem; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: .02em; border-bottom: 1.5px solid #e5e7eb; }
.table-card td { padding: .9rem 1.2rem; border-bottom: 1px solid #f3f4f6; font-size: .9rem; }
.table-card tbody tr:hover { background: #f9fafb; }
.role-badge { display: inline-flex; align-items: center; gap: .4rem; background: #eff6ff; border: 1px solid #3b82f6; color: #1e40af; padding: .35rem .75rem; border-radius: 6px; font-size: .8rem; font-weight: 600; }
.status-badge { padding: .3rem .7rem; border-radius: 6px; font-size: .8rem; font-weight: 700; }
.status-active { background: #dcfce7; color: #166534; }
.status-inactive { background: #fee2e2; color: #991b1b; }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
.stat-card { background: #fff; border-radius: 12px; padding: 1.5rem; border: 1px solid #edf0f8; text-align: center; }
.stat-number { font-size: 2rem; font-weight: 800; color: #0c1a35; line-height: 1; }
.stat-label { font-size: .85rem; color: #6b7280; margin-top: .5rem; text-transform: uppercase; letter-spacing: .02em; font-weight: 700; }
.stat-icon { font-size: 1.5rem; margin-bottom: .5rem; }
</style>

<div class="container-xl py-4">
  <div class="report-header">
    <h1 class="report-title">Users Report</h1>
    <p class="report-sub">View, filter, and analyze system users by role and status</p>
  </div>

  <!-- Stats -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-icon">👥</div>
      <div class="stat-number"><?= count($users) ?></div>
      <div class="stat-label">Total Users</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">✓</div>
      <div class="stat-number"><?= count(array_filter($users, fn($u) => $u['is_active'])) ?></div>
      <div class="stat-label">Active Users</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">✗</div>
      <div class="stat-number"><?= count(array_filter($users, fn($u) => !$u['is_active'])) ?></div>
      <div class="stat-label">Inactive Users</div>
    </div>
  </div>

  <!-- Filters -->
  <div class="filter-bar">
    <form action="" method="GET" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end; flex: 1;">
      <div class="filter-group">
        <label class="filter-label">Role</label>
        <select name="role" class="filter-select">
          <option value="">All Roles</option>
          <?php foreach ($roles as $r): ?>
            <option value="<?= $r['id'] ?>" <?= $role_filter == $r['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($r['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="filter-group">
        <label class="filter-label">Status</label>
        <select name="status" class="filter-select">
          <option value="">All Status</option>
          <option value="1" <?= $status_filter === '1' ? 'selected' : '' ?>>Active</option>
          <option value="0" <?= $status_filter === '0' ? 'selected' : '' ?>>Inactive</option>
        </select>
      </div>

      <?php if (Auth::isSuperAdmin()): ?>
        <div class="filter-group">
          <label class="filter-label">Branch</label>
          <select name="branch" class="filter-select">
            <option value="">All Branches</option>
            <?php foreach ($branches as $b): ?>
              <option value="<?= $b['id'] ?>" <?= $branch_filter == $b['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($b['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>

      <button type="submit" class="filter-btn">Filter</button>
      <a href="?<?= Auth::isSuperAdmin() ? '' : 'branch=' . $cu['branch_id'] ?>" class="clear-btn">Clear Filters</a>
    </form>
  </div>

  <!-- Table -->
  <div class="table-card">
    <table>
      <thead>
        <tr>
          <th>Name</th>
          <th>Email</th>
          <th>User ID</th>
          <th>Role</th>
          <?php if (Auth::isSuperAdmin()): ?>
            <th>Branch</th>
          <?php endif; ?>
          <th>Status</th>
          <th>Created</th>
          <th>Last Login</th>
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
            <td><code style="background: #f3f4f6; padding: .2rem .6rem; border-radius: 4px; font-size: .8rem;"><?= htmlspecialchars($u['user_id']) ?></code></td>
            <td><span class="role-badge"><?= htmlspecialchars($u['role_name']) ?></span></td>
            <?php if (Auth::isSuperAdmin()): ?>
              <td style="color: #6b7280;"><?= htmlspecialchars($u['branch_name'] ?? '—') ?></td>
            <?php endif; ?>
            <td>
              <span class="status-badge <?= $u['is_active'] ? 'status-active' : 'status-inactive' ?>">
                <?= $u['is_active'] ? '✓ Active' : '✗ Inactive' ?>
              </span>
            </td>
            <td style="color: #9ca3af; font-size: .85rem;"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
            <td style="color: #9ca3af; font-size: .85rem;">
              <?= $u['last_login'] ? date('M d, Y H:i', strtotime($u['last_login'])) : '—' ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <?php if (!$users): ?>
      <div style="text-align: center; padding: 3rem 2rem; color: #9ca3af;">
        <div style="font-size: 2rem; margin-bottom: 1rem; opacity: .3;">📊</div>
        <div style="font-size: 1rem; font-weight: 600;">No users found</div>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
