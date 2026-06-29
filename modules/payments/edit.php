<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
$db = Database::getInstance();
$cu = Auth::currentUser();
$id = (int)($_GET['id'] ?? 0);

$p = $db->fetchOne(
    "SELECT p.*, c.name as customer_name, b.booking_ref, i.invoice_number
     FROM payments p
     LEFT JOIN customers c ON p.customer_id=c.id
     LEFT JOIN bookings b ON p.booking_id=b.id
     LEFT JOIN invoices i ON p.invoice_id=i.id
     WHERE p.id=?", [$id]
);
if (!$p) { Helper::flash('error','Payment not found.'); Helper::redirect(BASE_URL.'/modules/payments/index.php'); }
if ($cu['branch_id'] && $p['branch_id'] != $cu['branch_id']) { Helper::flash('error','Access denied.'); Helper::redirect(BASE_URL.'/modules/payments/index.php'); }

$customers = $db->fetchAll("SELECT id,name,mobile as phone FROM customers ORDER BY name");
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id      = (int)($_POST['customer_id'] ?? 0);
    $payment_method   = $_POST['payment_method'] ?? 'cash';
    $amount           = (float)($_POST['amount'] ?? 0);
    $payment_date     = trim($_POST['payment_date'] ?? '');
    $reference_number = trim($_POST['reference_number'] ?? '');
    $bank_name        = trim($_POST['bank_name'] ?? '');
    $notes            = trim($_POST['notes'] ?? '');

    if (!$customer_id)  $errors[] = 'Customer required.';
    if ($amount <= 0)   $errors[] = 'Amount must be greater than 0.';
    if (!$payment_date) $errors[] = 'Payment date required.';

    if (!$errors) {
        Logger::log('edit','payments',$id,$p['payment_ref'],'amount:'.$p['amount'].' method:'.$p['payment_method'],'amount:'.$amount.' method:'.$payment_method,'Payment updated');
        $db->execute(
            "UPDATE payments SET customer_id=?,payment_method=?,amount=?,payment_date=?,reference_number=?,bank_name=?,notes=? WHERE id=?",
            [$customer_id,$payment_method,$amount,$payment_date,$reference_number,$bank_name,$notes,$id]
        );

        $booking_id = $p['booking_id'];
        $invoice_id = $p['invoice_id'];

        // Recalculate booking totals from SUM
        if ($booking_id) {
            $db->execute("UPDATE bookings SET paid_amount=(SELECT COALESCE(SUM(amount),0) FROM payments WHERE booking_id=bookings.id), balance_amount=final_amount-(SELECT COALESCE(SUM(amount),0) FROM payments WHERE booking_id=bookings.id) WHERE id=?", [$booking_id]);
        }
        // Recalculate invoice totals from SUM
        if ($invoice_id) {
            $db->execute("UPDATE invoices SET paid_amount=(SELECT COALESCE(SUM(amount),0) FROM payments WHERE invoice_id=invoices.id), balance=total-(SELECT COALESCE(SUM(amount),0) FROM payments WHERE invoice_id=invoices.id) WHERE id=?", [$invoice_id]);
        }
        // Recalc invoice status
        $inv_ids = [];
        if ($invoice_id) $inv_ids[] = $invoice_id;
        if ($booking_id) {
            foreach ($db->fetchAll("SELECT id FROM invoices WHERE booking_id=? AND status NOT IN ('cancelled')", [$booking_id]) as $bi) {
                if (!in_array($bi['id'], $inv_ids)) $inv_ids[] = $bi['id'];
            }
        }
        foreach ($inv_ids as $chk_id) {
            $ic = $db->fetchOne("SELECT balance, paid_amount FROM invoices WHERE id=?", [$chk_id]);
            if (!$ic) continue;
            if ($ic['balance'] <= 0)        $db->execute("UPDATE invoices SET status='paid', balance=0 WHERE id=?", [$chk_id]);
            elseif ($ic['paid_amount'] > 0) $db->execute("UPDATE invoices SET status='partial' WHERE id=? AND status NOT IN ('paid','cancelled')", [$chk_id]);
        }

        Helper::flash('success', 'Payment updated.');
        if ($booking_id) Helper::redirect(BASE_URL.'/modules/bookings/view.php?id='.$booking_id);
        elseif ($invoice_id) Helper::redirect(BASE_URL.'/modules/invoices/view.php?id='.$invoice_id);
        else Helper::redirect(BASE_URL.'/modules/payments/view.php?id='.$id);
    }
}

$pageTitle  = 'Edit Payment: '.$p['payment_ref'];
$breadcrumbs = [['label'=>'Payments','url'=>BASE_URL.'/modules/payments/index.php'],['label'=>$p['payment_ref']]];
require_once ROOT_PATH . '/includes/header.php';
?>
<div class="vp-page-header d-print-none">
  <div class="d-flex align-items-center justify-content-between">
    <div>
      <h1 class="vp-page-title">Edit Payment</h1>
      <div class="text-secondary"><?= Helper::sanitize($p['payment_ref']) ?></div>
    </div>
    <a href="<?= $p['booking_id'] ? BASE_URL.'/modules/bookings/view.php?id='.$p['booking_id'] : BASE_URL.'/modules/payments/index.php' ?>" class="btn btn-vp-outline">Cancel</a>
  </div>
</div>

<div class="row justify-content-center">
  <div class="col-lg-7">
    <form method="post" class="card vp-card">
      <div class="card-header" style="background:linear-gradient(135deg,#1a3c5e,#2d6a9f);border-radius:12px 12px 0 0;">
        <h3 class="card-title text-white mb-0">Payment Details</h3>
      </div>
      <div class="card-body">
        <?php if ($errors): ?>
        <div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e): ?><li><?= Helper::sanitize($e) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <?php if ($p['booking_ref']): ?>
        <div class="alert alert-info mb-3">📋 Booking: <strong><?= Helper::sanitize($p['booking_ref']) ?></strong></div>
        <?php endif; ?>
        <?php if ($p['invoice_number']): ?>
        <div class="alert alert-warning mb-3">🧾 Invoice: <strong><?= Helper::sanitize($p['invoice_number']) ?></strong></div>
        <?php endif; ?>

        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label required">Customer</label>
            <select name="customer_id" class="form-select" required>
              <option value="">— Select —</option>
              <?php foreach ($customers as $c): ?>
              <option value="<?= $c['id'] ?>" <?= (($_POST['customer_id']??$p['customer_id'])==$c['id'])?'selected':'' ?>><?= Helper::sanitize($c['name']) ?> (<?= Helper::sanitize($c['phone']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label required">Payment Date</label>
            <input type="date" name="payment_date" class="form-control" value="<?= $_POST['payment_date'] ?? $p['payment_date'] ?>" required>
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label required">Amount</label>
            <input type="number" name="amount" class="form-control" step="0.01" min="0.01" value="<?= $_POST['amount'] ?? $p['amount'] ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label required">Payment Method</label>
            <select name="payment_method" class="form-select" id="pay_method">
              <?php foreach (['cash'=>'Cash','bank_transfer'=>'Bank Transfer','card'=>'Card','cheque'=>'Cheque','online'=>'Online'] as $k=>$v): ?>
              <option value="<?= $k ?>" <?= (($_POST['payment_method']??$p['payment_method'])===$k)?'selected':'' ?>><?= $v ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div id="bank-fields" style="display:none;">
          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label">Bank Name</label>
              <input type="text" name="bank_name" class="form-control" value="<?= Helper::sanitize($_POST['bank_name'] ?? $p['bank_name'] ?? '') ?>" placeholder="e.g. Peoples Bank">
            </div>
            <div class="col-md-6">
              <label class="form-label">Reference / Cheque #</label>
              <input type="text" name="reference_number" class="form-control" value="<?= Helper::sanitize($_POST['reference_number'] ?? $p['reference_number'] ?? '') ?>">
            </div>
          </div>
        </div>
        <div id="card-fields" style="display:none;">
          <div class="mb-3">
            <label class="form-label">Transaction Reference</label>
            <input type="text" name="reference_number" class="form-control" value="<?= Helper::sanitize($_POST['reference_number'] ?? $p['reference_number'] ?? '') ?>">
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label">Notes</label>
          <textarea name="notes" class="form-control" rows="2"><?= Helper::sanitize($_POST['notes'] ?? $p['notes'] ?? '') ?></textarea>
        </div>
      </div>
      <div class="card-footer d-flex gap-2">
        <button type="submit" class="btn btn-vp-gold">Save Changes</button>
        <a href="<?= $p['booking_id'] ? BASE_URL.'/modules/bookings/view.php?id='.$p['booking_id'] : BASE_URL.'/modules/payments/view.php?id='.$id ?>" class="btn btn-vp-outline">Cancel</a>
      </div>
    </form>
  </div>
</div>
<script>
const pm = document.getElementById('pay_method');
function toggleFields() {
  const v = pm.value;
  document.getElementById('bank-fields').style.display = ['bank_transfer','cheque'].includes(v) ? '' : 'none';
  document.getElementById('card-fields').style.display  = v === 'card' ? '' : 'none';
}
pm.addEventListener('change', toggleFields);
toggleFields();
</script>
<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
