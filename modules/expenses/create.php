<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
$db = Database::getInstance();
$cu = Auth::currentUser();

// Branch-wise access control
$accessible_branch_ids = Auth::getAccessibleBranches();
$branch_ids = !empty($accessible_branch_ids) ? array_column($accessible_branch_ids, 'id') : [];
if (empty($branch_ids) && $cu['branch_id']) $branch_ids = [$cu['branch_id']];

// Accessible branches for dropdown
$branches = [];
if (!empty($branch_ids)) {
    $in = implode(',', array_map('intval', $branch_ids));
    $branches = $db->fetchAll("SELECT id, name FROM branches WHERE id IN ($in) ORDER BY name");
}

// Categories
$categories = $db->fetchAll("SELECT id, name FROM expense_categories ORDER BY name");

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branch_id      = (int)$_POST['branch_id'];
    $category_id    = (int)$_POST['category_id'];
    $title          = trim($_POST['title'] ?? '');
    $description    = trim($_POST['description'] ?? '');
    $amount         = (float)$_POST['amount'];
    $expense_date   = $_POST['expense_date'] ?? '';
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $reference_number = trim($_POST['reference_number'] ?? '');
    $status         = $_POST['status'] ?? 'approved';

    // Validate branch access
    if (!in_array($branch_id, $branch_ids)) $errors[] = 'Invalid branch selected.';
    if (!$category_id)   $errors[] = 'Please select a category.';
    if (!$title)         $errors[] = 'Title is required.';
    if ($amount <= 0)    $errors[] = 'Amount must be greater than 0.';
    if (!$expense_date)  $errors[] = 'Expense date is required.';

    if (!$errors) {
        // Generate expense ref: EXP-YYYY-NNNNN
        $year = date('Y');
        $last = $db->fetchOne("SELECT expense_ref FROM expenses WHERE expense_ref LIKE 'EXP-$year-%' ORDER BY id DESC LIMIT 1");
        if ($last) {
            $num = (int)substr($last['expense_ref'], -5) + 1;
        } else {
            $num = 1;
        }
        $expense_ref = 'EXP-' . $year . '-' . str_pad($num, 5, '0', STR_PAD_LEFT);

        $db->execute(
            "INSERT INTO expenses (expense_ref, branch_id, category_id, title, description, amount, expense_date, payment_method, reference_number, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$expense_ref, $branch_id, $category_id, $title, $description, $amount, $expense_date, $payment_method, $reference_number, $status, $cu['id']]
        );

        Helper::logActivity('expense_created', "Expense $expense_ref created — $title — " . Helper::formatCurrency($amount));
        header('Location: ' . BASE_URL . '/modules/expenses/index.php?success=created');
        exit;
    }
}

$pageTitle   = 'Add Expense';
$breadcrumbs = [['label' => 'Expenses', 'url' => BASE_URL . '/modules/expenses/index.php'], ['label' => 'Add Expense']];
require_once ROOT_PATH . '/includes/header.php';
?>
<div class="vp-page-header">
  <div>
    <h1 class="vp-page-title">💸 Add Expense</h1>
    <div class="vp-page-sub">Record a new expense entry</div>
  </div>
  <a href="<?= BASE_URL ?>/modules/expenses/index.php" class="btn btn-vp-outline">← Back to Expenses</a>
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
        <form method="post" id="expenseForm">
          <div class="row g-3">

            <!-- Branch -->
            <div class="col-md-6">
              <label class="form-label fw-700">Branch <span class="text-danger">*</span></label>
              <select name="branch_id" class="form-select" required>
                <option value="">Select Branch</option>
                <?php foreach ($branches as $br): ?>
                <option value="<?= $br['id'] ?>" <?= (isset($_POST['branch_id']) && $_POST['branch_id']==$br['id']) || (!isset($_POST['branch_id']) && count($branches)==1 && $br['id']==$branches[0]['id']) ? 'selected' : '' ?>>
                  <?= Helper::sanitize($br['name']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Category -->
            <div class="col-md-6">
              <label class="form-label fw-700">Category <span class="text-danger">*</span></label>
              <select name="category_id" class="form-select" required>
                <option value="">Select Category</option>
                <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>" <?= (isset($_POST['category_id']) && $_POST['category_id']==$c['id']) ? 'selected' : '' ?>>
                  <?= Helper::sanitize($c['name']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Title -->
            <div class="col-12">
              <label class="form-label fw-700">Expense Title <span class="text-danger">*</span></label>
              <input type="text" name="title" class="form-control" placeholder="e.g. Electricity Bill - July 2026"
                     value="<?= Helper::sanitize($_POST['title'] ?? '') ?>" required>
            </div>

            <!-- Description -->
            <div class="col-12">
              <label class="form-label fw-700">Description</label>
              <textarea name="description" class="form-control" rows="3" placeholder="Additional details..."><?= Helper::sanitize($_POST['description'] ?? '') ?></textarea>
            </div>

            <!-- Amount -->
            <div class="col-md-4">
              <label class="form-label fw-700">Amount (Rs.) <span class="text-danger">*</span></label>
              <input type="number" name="amount" class="form-control" placeholder="0.00" step="0.01" min="0.01"
                     value="<?= $_POST['amount'] ?? '' ?>" required>
            </div>

            <!-- Date -->
            <div class="col-md-4">
              <label class="form-label fw-700">Expense Date <span class="text-danger">*</span></label>
              <input type="date" name="expense_date" class="form-control"
                     value="<?= $_POST['expense_date'] ?? date('Y-m-d') ?>" required>
            </div>

            <!-- Status -->
            <div class="col-md-4">
              <label class="form-label fw-700">Status</label>
              <select name="status" class="form-select">
                <option value="approved" <?= (($_POST['status']??'approved')==='approved')?'selected':'' ?>>Approved</option>
                <option value="pending"  <?= (($_POST['status']??'')==='pending')?'selected':'' ?>>Pending</option>
                <option value="rejected" <?= (($_POST['status']??'')==='rejected')?'selected':'' ?>>Rejected</option>
              </select>
            </div>

            <!-- Payment Method -->
            <div class="col-md-6">
              <label class="form-label fw-700">Payment Method</label>
              <select name="payment_method" class="form-select">
                <?php foreach (['cash'=>'Cash','bank_transfer'=>'Bank Transfer','card'=>'Card','cheque'=>'Cheque','online'=>'Online'] as $k=>$v): ?>
                <option value="<?= $k ?>" <?= (($_POST['payment_method']??'cash')===$k)?'selected':'' ?>><?= $v ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Reference -->
            <div class="col-md-6">
              <label class="form-label fw-700">Reference Number</label>
              <input type="text" name="reference_number" class="form-control" placeholder="Bill no. / receipt no."
                     value="<?= Helper::sanitize($_POST['reference_number'] ?? '') ?>">
            </div>

          </div>

          <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-vp-gold">
              <svg xmlns="http://www.w3.org/2000/svg" class="icon me-1" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
              Save Expense
            </button>
            <a href="<?= BASE_URL ?>/modules/expenses/index.php" class="btn btn-vp-outline">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Sidebar tips -->
  <div class="col-lg-4">
    <div class="card vp-card" style="border-left:4px solid #c9a84c;">
      <div class="card-body">
        <h4 style="font-size:.9rem;font-weight:800;color:#0c1a35;margin-bottom:.75rem;">💡 Tips</h4>
        <ul style="font-size:.82rem;color:#6b7280;padding-left:1.2rem;line-height:1.8;">
          <li>Use a clear, descriptive title for easy searching later.</li>
          <li>Add a reference number (bill no., receipt no.) for audit trail.</li>
          <li>Set status to <strong>Pending</strong> if awaiting approval.</li>
          <li>Expenses are branch-specific and will appear in branch reports.</li>
        </ul>
      </div>
    </div>
    <div class="card vp-card mt-3" style="border-left:4px solid #dc2626;">
      <div class="card-body">
        <h4 style="font-size:.9rem;font-weight:800;color:#0c1a35;margin-bottom:.75rem;">📂 Categories</h4>
        <div class="d-flex flex-wrap gap-1">
          <?php foreach ($categories as $c): ?>
          <span style="background:#ede9fe;color:#5b21b6;border-radius:99px;padding:.15rem .65rem;font-size:.72rem;font-weight:600;"><?= Helper::sanitize($c['name']) ?></span>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
