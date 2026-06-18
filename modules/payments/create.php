<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
$db = Database::getInstance();
$cu = Auth::currentUser();

$booking_id = (int)($_GET['booking_id'] ?? 0);
$invoice_id = (int)($_GET['invoice_id'] ?? 0);
$booking = null; $invoice = null; $prefill_customer = null;

if ($booking_id) {
    $booking = $db->fetchOne("SELECT b.*,c.name as customer_name FROM bookings b LEFT JOIN customers c ON b.customer_id=c.id WHERE b.id=?", [$booking_id]);
}
if ($invoice_id) {
    $invoice = $db->fetchOne("SELECT i.*,c.name as customer_name FROM invoices i LEFT JOIN customers c ON i.customer_id=c.id WHERE i.id=?", [$invoice_id]);
    if ($invoice && !$booking_id) $booking_id = $invoice['booking_id'];
}

$customers = $db->fetchAll("SELECT id,name,mobile as phone FROM customers ORDER BY name");
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branch_id       = $cu['branch_id'] ?? (int)($_POST['branch_id'] ?? 1);
    $customer_id     = (int)($_POST['customer_id'] ?? 0);
    $booking_id      = (int)($_POST['booking_id'] ?? 0) ?: null;
    $invoice_id      = (int)($_POST['invoice_id'] ?? 0) ?: null;
    $payment_method  = $_POST['payment_method'] ?? 'cash';
    $amount          = (float)($_POST['amount'] ?? 0);
    $payment_date    = trim($_POST['payment_date'] ?? '');
    $reference_number= trim($_POST['reference_number'] ?? '');
    $bank_name       = trim($_POST['bank_name'] ?? '');
    $notes           = trim($_POST['notes'] ?? '');

    if (!$customer_id) $errors[] = 'Customer required.';
    if ($amount <= 0)  $errors[] = 'Amount must be greater than 0.';
    if (!$payment_date) $errors[] = 'Payment date required.';

    if (!$errors) {
        $pay_ref = Helper::generateRef('PAY','payments','payment_ref');
        $db->insert(
            "INSERT INTO payments (payment_ref,booking_id,invoice_id,customer_id,branch_id,payment_method,amount,payment_date,reference_number,bank_name,notes,received_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
            [$pay_ref,$booking_id,$invoice_id,$customer_id,$branch_id,$payment_method,$amount,$payment_date,$reference_number,$bank_name,$notes,$cu['id']]
        );

        // Update booking paid_amount and balance
        if ($booking_id) {
            $db->execute("UPDATE bookings SET paid_amount=paid_amount+?, balance_amount=balance_amount-? WHERE id=?", [$amount,$amount,$booking_id]);
        }
        // Update invoice paid_amount and balance
        if ($invoice_id) {
            $db->execute("UPDATE invoices SET paid_amount=paid_amount+?, balance=balance-? WHERE id=?", [$amount,$amount,$invoice_id]);
            // Auto mark as paid if balance <= 0
            $inv_check = $db->fetchOne("SELECT balance,total FROM invoices WHERE id=?", [$invoice_id]);
            if ($inv_check && $inv_check['balance'] <= 0) {
                $db->execute("UPDATE invoices SET status='paid',balance=0 WHERE id=?", [$invoice_id]);
            } elseif ($inv_check && $inv_check['paid_amount'] > 0) {
                $db->execute("UPDATE invoices SET status='partial' WHERE id=? AND status NOT IN ('paid','cancelled')", [$invoice_id]);
            }
        }

        Helper::flash('success',"Payment $pay_ref recorded — ".Helper::formatCurrency($amount));
        if ($booking_id) Helper::redirect(BASE_URL.'/modules/bookings/view.php?id='.$booking_id);
        elseif ($invoice_id) Helper::redirect(BASE_URL.'/modules/invoices/view.php?id='.$invoice_id);
        else Helper::redirect(BASE_URL.'/modules/payments/index.php');
    }
}

$pageTitle = 'Record Payment';
$breadcrumbs = [['label'=>'Payments','url'=>BASE_URL.'/modules/payments/index.php'],['label'=>'New']];
require_once ROOT_PATH . '/includes/header.php';
?>
<div class="vp-page-header d-print-none">
  <div class="d-flex align-items-center"><h1 class="vp-page-title">Record Payment</h1></div>
</div>
<div class="row justify-content-center">
  <div class="col-lg-7">
    <form method="post" class="card vp-card">
      <div class="card-header"><h3 class="card-title">Payment Details</h3></div>
      <div class="card-body">
        <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e): ?><li><?= Helper::sanitize($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

        <?php if ($booking): ?>
        <div class="alert alert-info mb-3">
          Booking: <strong><?= Helper::sanitize($booking['booking_ref']??'') ?></strong> · <?= Helper::sanitize($booking['customer_name']) ?>
          <?php if ($booking['balance_amount']>0): ?> · <span class="text-danger">Balance: <?= Helper::formatCurrency($booking['balance_amount']) ?></span><?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if ($invoice): ?>
        <div class="alert alert-warning mb-3">
          Invoice: <strong><?= Helper::sanitize($invoice['invoice_number']) ?></strong> · Balance: <strong><?= Helper::formatCurrency($invoice['balance']) ?></strong>
        </div>
        <?php endif; ?>

        <input type="hidden" name="booking_id" value="<?= $booking_id ?? ($_POST['booking_id']??'') ?>">
        <input type="hidden" name="invoice_id" value="<?= $invoice_id ?? ($_POST['invoice_id']??'') ?>">

        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label required">Customer</label>
            <select name="customer_id" class="form-select" required>
              <option value="">— Select —</option>
              <?php foreach ($customers as $c): ?>
              <option value="<?= $c['id'] ?>" <?= (($booking['customer_id']??$invoice['customer_id']??$_POST['customer_id']??'')==$c['id'])?'selected':'' ?>><?= Helper::sanitize($c['name']) ?> (<?= Helper::sanitize($c['phone']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label required">Payment Date</label>
            <input type="date" name="payment_date" class="form-control" value="<?= $_POST['payment_date']??date('Y-m-d') ?>" required>
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label required">Amount (Rs.)</label>
            <input type="number" name="amount" class="form-control" step="0.01" min="0.01"
              value="<?= $_POST['amount']??($invoice['balance']??($booking['balance_amount']??'')) ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label required">Payment Method</label>
            <select name="payment_method" class="form-select" id="pay_method">
              <?php foreach (['cash'=>'Cash','bank_transfer'=>'Bank Transfer','card'=>'Card','cheque'=>'Cheque','online'=>'Online'] as $k=>$v): ?>
              <option value="<?= $k ?>" <?= ($_POST['payment_method']??'cash')===$k?'selected':'' ?>><?= $v ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div id="bank-fields" style="display:none">
          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label">Bank Name</label>
              <input type="text" name="bank_name" class="form-control" value="<?= Helper::sanitize($_POST['bank_name']??'') ?>" placeholder="e.g. Peoples Bank">
            </div>
            <div class="col-md-6">
              <label class="form-label">Reference / Cheque #</label>
              <input type="text" name="reference_number" class="form-control" value="<?= Helper::sanitize($_POST['reference_number']??'') ?>">
            </div>
          </div>
        </div>
        <div id="card-fields" style="display:none">
          <div class="mb-3">
            <label class="form-label">Transaction Reference</label>
            <input type="text" name="reference_number" class="form-control" value="<?= Helper::sanitize($_POST['reference_number']??'') ?>">
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label">Notes</label>
          <textarea name="notes" class="form-control" rows="2"><?= Helper::sanitize($_POST['notes']??'') ?></textarea>
        </div>
      </div>
      <div class="card-footer d-flex gap-2">
        <button type="submit" class="btn btn-vp-gold">Save Payment</button>
        <a href="<?= $booking_id ? BASE_URL.'/modules/bookings/view.php?id='.$booking_id : BASE_URL.'/modules/payments/index.php' ?>" class="btn btn-vp-outline">Cancel</a>
      </div>
    </form>
  </div>
</div>
<script>
const pm = document.getElementById('pay_method');
function toggleFields(){
  const v = pm.value;
  document.getElementById('bank-fields').style.display = ['bank_transfer','cheque'].includes(v) ? '' : 'none';
  document.getElementById('card-fields').style.display  = v==='card' ? '' : 'none';
}
pm.addEventListener('change', toggleFields);
toggleFields();
</script>
<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
