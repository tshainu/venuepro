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

// Load expense
$expense = $db->fetchOne("SELECT * FROM expenses WHERE id = ?", [$id]);
if (!$expense || !in_array($expense['branch_id'], $branch_ids)) {
    header('Location: ' . BASE_URL . '/modules/expenses/index.php');
    exit;
}

// Accessible branches for dropdown
$branches = [];
if (!empty($branch_ids)) {
    $in = implode(',', array_map('intval', $branch_ids));
    $branches = $db->fetchAll("SELECT id, name FROM branches WHERE id IN ($in) ORDER BY name");
}

$categories = $db->fetchAll("SELECT id, name FROM expense_categories ORDER BY name");

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branch_id        = (int)$_POST['branch_id'];
    $category_id      = (int)$_POST['category_id'];
    $title            = trim($_POST['title'] ?? '');
    $description      = trim($_POST['description'] ?? '');
    $amount           = (float)$_POST['amount'];
    $expense_date     = $_POST['expense_date'] ?? '';
    $payment_method   = $_POST['payment_method'] ?? 'cash';
    $reference_number = trim($_POST['reference_number'] ?? '');
    $status           = $_POST['status'] ?? 'approved';

    if (!in_array($branch_id, $branch_ids)) $errors[] = 'Invalid branch selected.';
    if (!$category_id)  $errors[] = 'Please select a category.';
    if (!$title)        $errors[] = 'Title is required.';
    if ($amount <= 0)   $errors[] = 'Amount must be greater than 0.';
    if (!$expense_date) $errors[] = 'Expense date is required.';

    if (!$errors) {
        $db->execute(
            "UPDATE expenses SET branch_id=?, category_id=?, title=?, description=?, amount=?, expense_date=?, payment_method=?, reference_number=?, status=?, updated_at=NOW() WHERE id=?",
            [$branch_id, $category_id, $title, $description, $amount, $expense_date, $payment_method, $reference_number, $status, $id]
        );
        Helper::logActivity('expense_updated', "Expense {$expense['expense_ref']} updated — $title");
        header('Location: ' . BASE_URL . '/modules/expenses/view.php?id=' . $id . '&success=updated');
        exit;
    }
    // Re-populate from POST on error
    $expense = array_merge($expense, $_POST);
}

$pageTitle   = 'Edit Expense';
$breadcrumbs = [
    ['label' => 'Expenses', 'url' => BASE_URL . '/modules/expenses/index.php'],
    ['label' => $expense['expense_ref'], 'url' => BASE_URL . '/modules/expenses/view.php?id=' . $id],
    ['label' => 'Edit']
];
require_once ROOT_PATH . '/includes/header.php';
?>
<div class="vp-page-header">
  <div>
    <h1 class="vp-page-title">✏️ Edit Expense</h1>
    <div class="vp-page-sub"><?= Helper::sanitize($expense['expense_ref']) ?></div>
  </div>
  <a href="<?= BASE_URL ?>/modules/expenses/view.php?id=<?= $id ?>" class="btn btn-vp-outline">← Back</a>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger">
  <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= Helper::sanitize($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-lg-8">
    <div class="card vp-card">
      <div class="card-header"><h3 class="card-title">Expense Details</h3></div>
      <div class="card-body">
        <form method="post">
          <div class="row g-3">

            <div class="col-md-6">
              <label class="form-label fw-700">Branch <span class="text-danger">*</span></label>
              <select name="branch_id" class="form-select" required>
                <option value="">Select Branch</option>
                <?php foreach ($branches as $br): ?>
                <option value="<?= $br['id'] ?>" <?= $expense['branch_id']==$br['id']?'selected':'' ?>>
                  <?= Helper::sanitize($br['name']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label fw-700">Category <span class="text-danger">*</span></label>
              <select name="category_id" class="form-select" required>
                <option value="">Select Category</option>
                <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $expense['category_id']==$c['id']?'selected':'' ?>>
                  <?= Helper::sanitize($c['name']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-12">
              <label class="form-label fw-700">Expense Title <span class="text-danger">*</span></label>
              <input type="text" name="title" class="form-control" value="<?= Helper::sanitize($expense['title']) ?>" required>
            </div>

            <div class="col-12">
              <label class="form-label fw-700">Description</label>
              <textarea name="description" class="form-control" rows="3"><?= Helper::sanitize($expense['description'] ?? '') ?></textarea>
            </div>

            <div class="col-md-4">
              <label class="form-label fw-700">Amount (Rs.) <span class="text-danger">*</span></label>
              <input type="number" name="amount" class="form-control" step="0.01" min="0.01"
                     value="<?= $expense['amount'] ?>" required>
            </div>

            <div class="col-md-4">
              <label class="form-label fw-700">Expense Date <span class="text-danger">*</span></label>
              <input type="date" name="expense_date" class="form-control" value="<?= $expense['expense_date'] ?>" required>
            </div>

            <div class="col-md-4">
              <label class="form-label fw-700">Status</label>
              <select name="status" class="form-select">
                <option value="approved" <?= $expense['status']==='approved'?'selected':'' ?>>Approved</option>
                <option value="pending"  <?= $expense['status']==='pending'?'selected':'' ?>>Pending</option>
                <option value="rejected" <?= $expense['status']==='rejected'?'selected':'' ?>>Rejected</option>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label fw-700">Payment Method</label>
              <select name="payment_method" class="form-select">
                <?php foreach (['cash'=>'Cash','bank_transfer'=>'Bank Transfer','card'=>'Card','cheque'=>'Cheque','online'=>'Online'] as $k=>$v): ?>
                <option value="<?= $k ?>" <?= $expense['payment_method']===$k?'selected':'' ?>><?= $v ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label fw-700">Reference Number</label>
              <input type="text" name="reference_number" class="form-control"
                     value="<?= Helper::sanitize($expense['reference_number'] ?? '') ?>">
            </div>
          </div>

          <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-vp-gold">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon me-1" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
              Update Expense
            </button>
            <a href="<?= BASE_URL ?>/modules/expenses/view.php?id=<?= $id ?>" class="btn btn-vp-outline">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card vp-card" style="border-left:4px solid #dc2626;">
      <div class="card-body">
        <div style="font-size:.7rem;font-weight:700;color:#6b7280;text-transform:uppercase;margin-bottom:.5rem;">Current Record</div>
        <div class="fw-800" style="font-size:1.3rem;color:#dc2626;"><?= Helper::formatCurrency($expense['amount']) ?></div>
        <div style="font-size:.82rem;color:#6b7280;margin-top:.25rem;"><?= Helper::formatDate($expense['expense_date']) ?></div>
        <div style="font-size:.82rem;color:#374151;margin-top:.5rem;"><?= Helper::sanitize($expense['expense_ref']) ?></div>
      </div>
    </div>
  </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
