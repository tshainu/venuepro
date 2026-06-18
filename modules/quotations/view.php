<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
$db = Database::getInstance();
$cu = Auth::currentUser();
$id = (int)($_GET['id'] ?? 0);
$q = $db->fetchOne(
    "SELECT q.*, c.name as customer_name, c.mobile as customer_phone, c.email as customer_email, c.address as customer_address,
            br.name as branch_name, br.address as branch_address, br.phone as branch_phone,
            bk.booking_ref
     FROM quotations q
     LEFT JOIN customers c ON q.customer_id=c.id
     LEFT JOIN branches br ON q.branch_id=br.id
     LEFT JOIN bookings bk ON q.booking_id=bk.id
     WHERE q.id=?", [$id]
);
if (!$q) { Helper::flash('error','Quotation not found.'); Helper::redirect(BASE_URL.'/modules/quotations/index.php'); }
if ($cu['branch_id'] && $q['branch_id'] != $cu['branch_id']) { Helper::flash('error','Access denied.'); Helper::redirect(BASE_URL.'/modules/quotations/index.php'); }

// Status update
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['change_status'])) {
    $ns = $_POST['new_status'] ?? '';
    if (in_array($ns,['draft','sent','accepted','rejected','expired'])) {
        $db->execute("UPDATE quotations SET status=? WHERE id=?",[$ns,$id]);
        Helper::flash('success','Status updated.');
        Helper::redirect(BASE_URL.'/modules/quotations/view.php?id='.$id);
    }
}

$items = $db->fetchAll("SELECT * FROM quotation_items WHERE quotation_id=?", [$id]);

$pageTitle = 'Quotation: '.$q['quotation_ref'];
$breadcrumbs = [['label'=>'Quotations','url'=>BASE_URL.'/modules/quotations/index.php'],['label'=>$q['quotation_ref']]];
require_once ROOT_PATH . '/includes/header.php';
?>
<div class="vp-page-header d-print-none">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
      <h1 class="vp-page-title"><?= Helper::sanitize($q['quotation_ref']) ?></h1>
      <div class="text-secondary"><?= Helper::sanitize($q['customer_name']) ?></div>
    </div>
    <div class="d-flex gap-2">
      <a href="<?= BASE_URL ?>/modules/quotations/pdf.php?id=<?= $id ?>" class="btn btn-vp-primary" target="_blank">Download PDF</a>
      <?php if ($q['booking_ref']): ?>
      <a href="<?= BASE_URL ?>/modules/bookings/view.php?id=<?= $q['booking_id'] ?>" class="btn btn-vp-outline">View Booking</a>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-lg-8">
    <div class="card vp-card mb-3">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h3 class="card-title"><?= Helper::sanitize($q['quotation_ref']) ?></h3>
        <?= Helper::statusBadge($q['status']) ?>
      </div>
      <div class="card-body">
        <div class="row mb-4">
          <div class="col-md-6">
            <div class="text-secondary small fw-bold mb-1">FROM</div>
            <div class="fw-bold"><?= Helper::sanitize($q['branch_name']) ?></div>
            <div class="text-secondary small"><?= nl2br(Helper::sanitize($q['branch_address']??'')) ?></div>
            <div class="text-secondary small"><?= Helper::sanitize($q['branch_phone']??'') ?></div>
          </div>
          <div class="col-md-6">
            <div class="text-secondary small fw-bold mb-1">TO</div>
            <div class="fw-bold"><?= Helper::sanitize($q['customer_name']) ?></div>
            <div class="text-secondary small"><?= Helper::sanitize($q['customer_phone']) ?></div>
            <?php if ($q['customer_email']): ?><div class="text-secondary small"><?= Helper::sanitize($q['customer_email']) ?></div><?php endif; ?>
            <?php if ($q['customer_address']): ?><div class="text-secondary small"><?= nl2br(Helper::sanitize($q['customer_address'])) ?></div><?php endif; ?>
          </div>
        </div>
        <div class="row mb-4">
          <div class="col-md-4"><div class="text-secondary small">Valid Until</div><div class="fw-bold"><?= Helper::formatDate($q['valid_until']) ?></div></div>
          <?php if ($q['booking_ref']): ?><div class="col-md-4"><div class="text-secondary small">Booking Ref</div><div><?= Helper::sanitize($q['booking_ref']) ?></div></div><?php endif; ?>
          <div class="col-md-4"><div class="text-secondary small">Created</div><div><?= Helper::formatDate($q['created_at']) ?></div></div>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead class="table-light"><tr><th>Description</th><th class="text-end">Qty</th><th class="text-end">Unit Price</th><th class="text-end">Tax %</th><th class="text-end">Total</th></tr></thead>
          <tbody>
            <?php foreach ($items as $it): ?>
            <tr>
              <td><?= Helper::sanitize($it['description']) ?></td>
              <td class="text-end"><?= $it['quantity'] ?></td>
              <td class="text-end"><?= Helper::formatCurrency($it['unit_price']) ?></td>
              <td class="text-end"><?= number_format($it['tax_percent'],1) ?>%</td>
              <td class="text-end fw-bold"><?= Helper::formatCurrency($it['total']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr><td colspan="4" class="text-end">Subtotal</td><td class="text-end fw-bold"><?= Helper::formatCurrency($q['subtotal']) ?></td></tr>
            <tr><td colspan="4" class="text-end">Tax</td><td class="text-end"><?= Helper::formatCurrency($q['tax_amount']) ?></td></tr>
            <?php if ($q['discount_amount']>0): ?><tr><td colspan="4" class="text-end text-danger">Discount</td><td class="text-end text-danger">- <?= Helper::formatCurrency($q['discount_amount']) ?></td></tr><?php endif; ?>
            <tr class="table-light"><td colspan="4" class="text-end fw-bold fs-5">Total</td><td class="text-end fw-bold fs-5"><?= Helper::formatCurrency($q['total']) ?></td></tr>
          </tfoot>
        </table>
      </div>
      <?php if ($q['notes'] || $q['terms']): ?>
      <div class="card-body border-top">
        <?php if ($q['notes']): ?><div class="mb-2"><div class="text-secondary small fw-bold">NOTES</div><?= nl2br(Helper::sanitize($q['notes'])) ?></div><?php endif; ?>
        <?php if ($q['terms']): ?><div><div class="text-secondary small fw-bold">TERMS</div><?= nl2br(Helper::sanitize($q['terms'])) ?></div><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card vp-card mb-3">
      <div class="card-header"><h3 class="card-title">Change Status</h3></div>
      <div class="card-body">
        <form method="post">
          <input type="hidden" name="change_status" value="1">
          <select name="new_status" class="form-select mb-2">
            <?php foreach (['draft','sent','accepted','rejected','expired'] as $s): ?>
            <option value="<?= $s ?>" <?= $q['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-secondary w-100">Update</button>
        </form>
      </div>
    </div>
    <div class="card vp-card">
      <div class="card-header"><h3 class="card-title">Summary</h3></div>
      <div class="card-body">
        <div class="d-flex justify-content-between mb-1"><span>Subtotal</span><strong><?= Helper::formatCurrency($q['subtotal']) ?></strong></div>
        <div class="d-flex justify-content-between mb-1"><span>Tax</span><strong><?= Helper::formatCurrency($q['tax_amount']) ?></strong></div>
        <?php if ($q['discount_amount']>0): ?><div class="d-flex justify-content-between mb-1 text-danger"><span>Discount</span><strong>- <?= Helper::formatCurrency($q['discount_amount']) ?></strong></div><?php endif; ?>
        <hr>
        <div class="d-flex justify-content-between fw-bold fs-5"><span>Total</span><strong><?= Helper::formatCurrency($q['total']) ?></strong></div>
      </div>
    </div>
  </div>
</div>
<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
