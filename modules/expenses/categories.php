<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();

// Only owner, general_manager, admin, super_admin can manage categories
if (Auth::hasRole(['hall_manager'])) {
    header('Location: ' . BASE_URL . '/modules/expenses/index.php');
    exit;
}

$db = Database::getInstance();
$cu = Auth::currentUser();

// Get business_id for this user
$user_uid  = $_SESSION['user_uid'] ?? '';
$biz_info  = $user_uid ? $db->fetchOne("SELECT id FROM sa_businesses WHERE admin_user_id = ?", [$user_uid]) : null;
$biz_id    = $biz_info ? (int)$biz_info['id'] : 0;
// Fallback: get business_id from the user's accessible branch
if (!$biz_id) {
    $accessible = Auth::getAccessibleBranches();
    $first_branch_id = !empty($accessible) ? $accessible[0]['id'] : ($cu['branch_id'] ?? 0);
    if ($first_branch_id) {
        $br = $db->fetchOne("SELECT business_id FROM branches WHERE id = ?", [$first_branch_id]);
        $biz_id = $br ? (int)$br['business_id'] : 0;
    }
}

$errors  = [];
$success = '';

// ── Handle POST actions ───────────────────────────────────────────────────────
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ADD
    if ($action === 'add') {
        $name  = trim($_POST['name'] ?? '');
        $color = trim($_POST['color'] ?? '#6366f1');
        if (!$name) {
            $errors[] = 'Category name is required.';
        } elseif ($db->fetchOne("SELECT id FROM expense_categories WHERE name = ?", [$name])) {
            $errors[] = 'A category with this name already exists.';
        } else {
            $db->execute(
                "INSERT INTO expense_categories (business_id, name, color, is_active, created_at) VALUES (?, ?, ?, 1, NOW())",
                [$biz_id, $name, $color]
            );
            Logger::log('create', 'expense_categories', null, $name, null, ['name' => $name], "Expense category '$name' created");
            $success = "Category \"$name\" added successfully.";
        }
    }

    // EDIT
    if ($action === 'edit') {
        $id    = (int)($_POST['id'] ?? 0);
        $name  = trim($_POST['name'] ?? '');
        $color = trim($_POST['color'] ?? '#6366f1');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        if (!$id) {
            $errors[] = 'Invalid category.';
        } elseif (!$name) {
            $errors[] = 'Category name is required.';
        } elseif ($db->fetchOne("SELECT id FROM expense_categories WHERE name = ? AND id != ?", [$name, $id])) {
            $errors[] = 'Another category with this name already exists.';
        } else {
            $old = $db->fetchOne("SELECT * FROM expense_categories WHERE id = ?", [$id]);
            $db->execute(
                "UPDATE expense_categories SET name = ?, color = ?, is_active = ?, updated_at = NOW() WHERE id = ?",
                [$name, $color, $is_active, $id]
            );
            Logger::log('edit', 'expense_categories', $id, $name, $old, ['name' => $name, 'is_active' => $is_active], "Expense category '$name' updated");
            $success = "Category \"$name\" updated successfully.";
        }
    }

    // DELETE
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) {
            $errors[] = 'Invalid category.';
        } else {
            $cat = $db->fetchOne("SELECT * FROM expense_categories WHERE id = ?", [$id]);
            if (!$cat) {
                $errors[] = 'Category not found.';
            } else {
                // Check if any expenses use this category
                $usage = (int)($db->fetchOne("SELECT COUNT(*) as c FROM expenses WHERE category_id = ?", [$id])['c'] ?? 0);
                if ($usage > 0) {
                    $errors[] = "Cannot delete \"{$cat['name']}\" — it is used by $usage expense record(s). Reassign those expenses first.";
                } else {
                    $db->execute("DELETE FROM expense_categories WHERE id = ?", [$id]);
                    Logger::log('delete', 'expense_categories', $id, $cat['name'], $cat, null, "Expense category '{$cat['name']}' deleted");
                    $success = "Category \"{$cat['name']}\" deleted.";
                }
            }
        }
    }
}

// ── Load all categories with usage count ─────────────────────────────────────
$cat_where = $biz_id ? "WHERE ec.business_id = $biz_id" : '';
$categories = $db->fetchAll(
    "SELECT ec.*, COUNT(e.id) as usage_count
     FROM expense_categories ec
     LEFT JOIN expenses e ON e.category_id = ec.id
     $cat_where
     GROUP BY ec.id
     ORDER BY ec.name ASC"
);

// Category being edited (from GET)
$edit_id  = (int)($_GET['edit'] ?? 0);
$edit_cat = $edit_id ? $db->fetchOne("SELECT * FROM expense_categories WHERE id = ?", [$edit_id]) : null;

$pageTitle   = 'Expense Categories';
$breadcrumbs = [
    ['label' => 'Expenses', 'url' => BASE_URL . '/modules/expenses/index.php'],
    ['label' => 'Categories']
];
require_once ROOT_PATH . '/includes/header.php';
?>

<style>
.cat-card { background:#fff; border-radius:14px; border:1.5px solid #e5e7eb; padding:1.1rem 1.3rem; display:flex; align-items:center; gap:1rem; transition:box-shadow .15s; }
.cat-card:hover { box-shadow:0 4px 18px rgba(0,0,0,.07); }
.cat-dot { width:38px; height:38px; border-radius:50%; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:1.1rem; }
.cat-name { font-weight:700; font-size:.95rem; color:#0c1a35; }
.cat-usage { font-size:.75rem; color:#9ca3af; margin-top:.1rem; }
.cat-inactive { opacity:.5; }
.add-form-card { background:#f8fafc; border:2px dashed #c9a84c; border-radius:14px; padding:1.3rem 1.5rem; margin-bottom:1.5rem; }
.edit-inline { background:#fffbeb; border:2px solid #c9a84c; border-radius:14px; padding:1.1rem 1.3rem; margin-bottom:.75rem; }
</style>

<div class="vp-page-header">
  <div>
    <h1 class="vp-page-title">📂 Expense Categories</h1>
    <div class="vp-page-sub"><?= count($categories) ?> categories configured</div>
  </div>
  <a href="<?= BASE_URL ?>/modules/expenses/index.php" class="btn btn-vp-outline">← Back to Expenses</a>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger mb-3">
  <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= Helper::sanitize($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success mb-3"><?= Helper::sanitize($success) ?></div>
<?php endif; ?>

<div class="row g-4">

  <!-- Left: Category list -->
  <div class="col-lg-8">

    <!-- Add new category form -->
    <div class="add-form-card mb-4">
      <h4 style="font-size:.9rem;font-weight:800;color:#0c1a35;margin-bottom:1rem;">➕ Add New Category</h4>
      <form method="post" class="row g-2 align-items-end">
        <input type="hidden" name="action" value="add">
        <div class="col-md-6">
          <label class="form-label fw-700" style="font-size:.82rem;">Category Name <span class="text-danger">*</span></label>
          <input type="text" name="name" class="form-control form-control-sm" placeholder="e.g. Staff Salary" required
                 value="<?= ($action==='add' && $errors) ? Helper::sanitize($_POST['name']??'') : '' ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label fw-700" style="font-size:.82rem;">Color</label>
          <input type="color" name="color" class="form-control form-control-sm form-control-color w-100"
                 value="<?= ($action==='add' && $errors) ? Helper::sanitize($_POST['color']??'#6366f1') : '#6366f1' ?>">
        </div>
        <div class="col-md-3">
          <button type="submit" class="btn btn-vp-gold btn-sm w-100">Add Category</button>
        </div>
      </form>
    </div>

    <!-- Category list -->
    <?php if (!$categories): ?>
    <div class="card vp-card">
      <div class="card-body text-center py-5">
        <div style="font-size:2.5rem;margin-bottom:.5rem;">📂</div>
        <div style="font-weight:700;color:#6b7280;">No categories yet. Add one above.</div>
      </div>
    </div>
    <?php else: ?>

    <div class="d-flex flex-column gap-2">
      <?php foreach ($categories as $cat): ?>

      <?php if ($edit_id === (int)$cat['id']): ?>
      <!-- Inline edit form -->
      <div class="edit-inline">
        <form method="post" class="row g-2 align-items-end">
          <input type="hidden" name="action" value="edit">
          <input type="hidden" name="id" value="<?= $cat['id'] ?>">
          <div class="col-md-5">
            <label class="form-label fw-700" style="font-size:.82rem;">Category Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control form-control-sm" required
                   value="<?= Helper::sanitize(($action==='edit' && $errors) ? ($_POST['name']??'') : $cat['name']) ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label fw-700" style="font-size:.82rem;">Color</label>
            <input type="color" name="color" class="form-control form-control-sm form-control-color w-100"
                   value="<?= Helper::sanitize(($action==='edit' && $errors) ? ($_POST['color']??$cat['color']) : ($cat['color']??'#6366f1')) ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label fw-700" style="font-size:.82rem;">Active</label>
            <div class="form-check mt-1">
              <input class="form-check-input" type="checkbox" name="is_active" id="ia_<?= $cat['id'] ?>"
                     <?= ($cat['is_active'] ?? 1) ? 'checked' : '' ?>>
              <label class="form-check-label" for="ia_<?= $cat['id'] ?>" style="font-size:.82rem;">Active</label>
            </div>
          </div>
          <div class="col-md-3 d-flex gap-1">
            <button type="submit" class="btn btn-vp-gold btn-sm flex-fill">Save</button>
            <a href="<?= BASE_URL ?>/modules/expenses/categories.php" class="btn btn-vp-outline btn-sm flex-fill">Cancel</a>
          </div>
        </form>
      </div>

      <?php else: ?>
      <!-- Normal display row -->
      <div class="cat-card <?= !($cat['is_active']??1) ? 'cat-inactive' : '' ?>">
        <div class="cat-dot" style="background:<?= Helper::sanitize($cat['color'] ?? '#6366f1') ?>22;">
          <span style="font-size:1rem;">📂</span>
        </div>
        <div class="flex-fill">
          <div class="cat-name"><?= Helper::sanitize($cat['name']) ?></div>
          <div class="cat-usage">
            <?= $cat['usage_count'] ?> expense<?= $cat['usage_count'] != 1 ? 's' : '' ?>
            <?php if (!($cat['is_active']??1)): ?>
            &nbsp;<span style="background:#fee2e2;color:#dc2626;border-radius:99px;padding:.05rem .4rem;font-size:.68rem;font-weight:700;">Inactive</span>
            <?php endif; ?>
          </div>
        </div>
        <div style="width:14px;height:14px;border-radius:50%;background:<?= Helper::sanitize($cat['color'] ?? '#6366f1') ?>;flex-shrink:0;"></div>
        <div class="d-flex gap-1">
          <a href="?edit=<?= $cat['id'] ?>" class="btn btn-vp-outline btn-sm" title="Edit">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Edit
          </a>
          <?php if ($cat['usage_count'] == 0): ?>
          <form method="post" style="display:inline;" onsubmit="return confirm('Delete category \'<?= addslashes($cat['name']) ?>\'? This cannot be undone.');">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $cat['id'] ?>">
            <button type="submit" class="btn btn-sm" style="background:#fee2e2;color:#dc2626;border:1px solid #fca5a5;" title="Delete">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
              Del
            </button>
          </form>
          <?php else: ?>
          <button class="btn btn-sm" style="background:#f1f5f9;color:#9ca3af;border:1px solid #e5e7eb;cursor:not-allowed;" title="Cannot delete — in use">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
            Del
          </button>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Right: Info panel -->
  <div class="col-lg-4">
    <div class="card vp-card" style="border-left:4px solid #c9a84c;">
      <div class="card-body">
        <h4 style="font-size:.9rem;font-weight:800;color:#0c1a35;margin-bottom:.75rem;">💡 About Categories</h4>
        <ul style="font-size:.82rem;color:#6b7280;padding-left:1.2rem;line-height:1.9;">
          <li>Categories help organise and filter expenses.</li>
          <li>Each expense must belong to one category.</li>
          <li>Categories with existing expenses <strong>cannot be deleted</strong> — reassign those expenses first.</li>
          <li>Inactive categories are hidden from the Add Expense form.</li>
          <li>Color is used as a visual tag in the expenses list.</li>
        </ul>
      </div>
    </div>

    <div class="card vp-card mt-3">
      <div class="card-body">
        <h4 style="font-size:.9rem;font-weight:800;color:#0c1a35;margin-bottom:.75rem;">📊 Usage Summary</h4>
        <?php foreach ($categories as $cat): ?>
        <div class="d-flex align-items-center justify-content-between mb-2">
          <div class="d-flex align-items-center gap-2">
            <div style="width:10px;height:10px;border-radius:50%;background:<?= Helper::sanitize($cat['color']??'#6366f1') ?>;flex-shrink:0;"></div>
            <span style="font-size:.8rem;font-weight:600;color:#374151;"><?= Helper::sanitize($cat['name']) ?></span>
          </div>
          <span style="font-size:.78rem;font-weight:700;color:#6b7280;"><?= $cat['usage_count'] ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
