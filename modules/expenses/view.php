<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
$db = Database::getInstance();
$cu = Auth::currentUser();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . BASE_URL . '/modules/expenses/index.php'); exit; }

// Branch-wise access control
$accessible_branch_ids = Auth::getAccessibleBranches();
$branch_ids = !empty($accessible_branch_ids) ? array_column($accessible_branch_ids, 'id') : [];
if (empty($branch_ids) && $cu['branch_id']) $branch_ids = [$cu['branch_id']];

$expense = $db->fetchOne(
    "SELECT e.*, ec.name as category_name, br.name as branch_name, u.name as created_by_name
     FROM expenses e
     JOIN expense_categories ec ON e.category_id = ec.id
     JOIN branches br ON e.branch_id = br.id
     LEFT JOIN users u ON e.created_by = u.id
     WHERE e.id = ?",
    [$id]
);

if (!$expense || !in_array($expense['branch_id'], $branch_ids)) {
    header('Location: ' . BASE_URL . '/modules/expenses/index.php');
    exit;
}

$pageTitle   = $expense['expense_ref'];
$breadcrumbs = [
    ['label' => 'Expenses', 'url' => BASE_URL . '/modules/expenses/index.php'],
    ['label' => $expense['expense_ref']]
];
require_once ROOT_PATH . '/includes/header.php';
?>
<style>
.exp-view-card{border-radius:20px;overflow:hidden;box-shadow:0 4px 24px rgba(10,20,45,.08);}
.exp-view-header{background:linear-gradient(135deg,#0c1a35 0%,#1a3c6e 100%);color:#fff;padding:2rem;}
.exp-view-ref{font-size:.8rem;font-weight:700;color:#c9a84c;letter-spacing:.1em;text-transform:uppercase;}
.exp-view-title{font-size:1.6rem;font-weight:900;margin:.4rem 0;}
.exp-view-amount{font-size:2.2rem;font-weight:900;color:#c9a84c;}
.exp-detail-row{display:flex;justify-content:space-between;align-items:center;padding:.65rem 0;border-bottom:1px solid #f1f5f9;}
.exp-detail-row:last-child{border-bottom:none;}
.exp-detail-label{font-size:.75rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.06em;}
.exp-detail-value{font-size:.9rem;font-weight:600;color:#0c1a35;}
.exp-status-approved{background:#d1fae5;color:#065f46;border-radius:99px;padding:.2rem .8rem;font-size:.78rem;font-weight:700;}
.exp-status-pending{background:#fef3c7;color:#92400e;border-radius:99px;padding:.2rem .8rem;font-size:.78rem;font-weight:700;}
.exp-status-rejected{background:#fee2e2;color:#991b1b;border-radius:99px;padding:.2rem .8rem;font-size:.78rem;font-weight:700;}
</style>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success alert-dismissible fade show">
  <?= $_GET['success'] === 'updated' ? 'Expense updated successfully.' : 'Expense created successfully.' ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="vp-page-header">
  <div>
    <h1 class="vp-page-title">💸 Expense Details</h1>
    <div class="vp-page-sub"><?= Helper::sanitize($expense['expense_ref']) ?></div>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= BASE_URL ?>/modules/expenses/edit.php?id=<?= $id ?>" class="btn btn-vp-primary">Edit</a>
    <a href="<?= BASE_URL ?>/modules/expenses/index.php" class="btn btn-vp-outline">← Back</a>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-7">
    <div class="card exp-view-card">
      <!-- Header -->
      <div class="exp-view-header">
        <div class="exp-view-ref"><?= Helper::sanitize($expense['expense_ref']) ?></div>
        <div class="exp-view-title"><?= Helper::sanitize($expense['title']) ?></div>
        <div class="exp-view-amount"><?= Helper::formatCurrency($expense['amount']) ?></div>
        <div style="margin-top:.5rem;font-size:.85rem;color:#c5d3e8;"><?= Helper::sanitize($expense['branch_name']) ?> &nbsp;·&nbsp; <?= Helper::formatDate($expense['expense_date']) ?></div>
      </div>
      <!-- Details -->
      <div class="card-body">
        <div class="exp-detail-row">
          <span class="exp-detail-label">Category</span>
          <span class="exp-detail-value">
            <span style="background:#ede9fe;color:#5b21b6;border-radius:99px;padding:.15rem .65rem;font-size:.78rem;font-weight:700;"><?= Helper::sanitize($expense['category_name']) ?></span>
          </span>
        </div>
        <div class="exp-detail-row">
          <span class="exp-detail-label">Status</span>
          <span class="exp-status-<?= $expense['status'] ?>"><?= ucfirst($expense['status']) ?></span>
        </div>
        <div class="exp-detail-row">
          <span class="exp-detail-label">Payment Method</span>
          <span class="exp-detail-value"><?= ucfirst(str_replace('_', ' ', $expense['payment_method'])) ?></span>
        </div>
        <?php if ($expense['reference_number']): ?>
        <div class="exp-detail-row">
          <span class="exp-detail-label">Reference No.</span>
          <span class="exp-detail-value"><?= Helper::sanitize($expense['reference_number']) ?></span>
        </div>
        <?php endif; ?>
        <div class="exp-detail-row">
          <span class="exp-detail-label">Branch</span>
          <span class="exp-detail-value"><?= Helper::sanitize($expense['branch_name']) ?></span>
        </div>
        <div class="exp-detail-row">
          <span class="exp-detail-label">Added By</span>
          <span class="exp-detail-value"><?= Helper::sanitize($expense['created_by_name'] ?? '—') ?></span>
        </div>
        <div class="exp-detail-row">
          <span class="exp-detail-label">Created At</span>
          <span class="exp-detail-value"><?= Helper::formatDate($expense['created_at']) ?></span>
        </div>
        <?php if ($expense['description']): ?>
        <div class="mt-3">
          <div class="exp-detail-label mb-1">Description</div>
          <div style="background:#f8fafc;border-radius:10px;padding:.75rem 1rem;font-size:.88rem;color:#374151;line-height:1.6;">
            <?= nl2br(Helper::sanitize($expense['description'])) ?>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card vp-card" style="border-left:4px solid #dc2626;">
      <div class="card-body">
        <h4 style="font-size:.9rem;font-weight:800;color:#0c1a35;margin-bottom:1rem;">⚡ Quick Actions</h4>
        <div class="d-grid gap-2">
          <a href="<?= BASE_URL ?>/modules/expenses/edit.php?id=<?= $id ?>" class="btn btn-vp-primary">
            ✏️ Edit This Expense
          </a>
          <a href="<?= BASE_URL ?>/modules/expenses/create.php" class="btn btn-vp-gold">
            ➕ Add Another Expense
          </a>
          <a href="<?= BASE_URL ?>/modules/expenses/delete.php?id=<?= $id ?>" class="btn btn-danger"
             onclick="return confirm('Are you sure you want to delete this expense? This cannot be undone.')">
            🗑️ Delete Expense
          </a>
          <a href="<?= BASE_URL ?>/modules/expenses/index.php" class="btn btn-vp-outline">
            ← Back to Expenses
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
