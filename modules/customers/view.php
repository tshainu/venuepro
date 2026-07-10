<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
$db = Database::getInstance();
$id = (int)($_GET['id'] ?? 0);
$c = $db->fetchOne("SELECT * FROM customers WHERE id=?", [$id]);
if (!$c) { Helper::flash('error','Not found.'); Helper::redirect(BASE_URL.'/modules/customers/index.php'); }
$bookings = $db->fetchAll("SELECT b.*, h.name as hall_name FROM bookings b LEFT JOIN halls h ON b.hall_id=h.id WHERE b.customer_id=? ORDER BY b.event_date DESC", [$id]);
$pageTitle = $c['name'];
$breadcrumbs=[['label'=>'Customers','url'=>BASE_URL.'/modules/customers/index.php'],['label'=>$c['name']]];
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="vp-page-header d-print-none">
  <div class="row align-items-center">
    <div class="col"><h1 class="vp-page-title"><?= Helper::sanitize($c['name']) ?></h1></div>
    <div class="col-auto ms-auto d-flex gap-2">
      <a href="<?= BASE_URL ?>/modules/bookings/create.php?customer_id=<?= $id ?>" class="btn btn-vp-gold">+ New Booking</a>
      <a href="<?= BASE_URL ?>/modules/customers/edit.php?id=<?= $id ?>" class="btn btn-vp-gold">Edit</a>
    </div>
  </div>
</div>
<div class="row g-3">
  <div class="col-md-4">
    <div class="card vp-card">
      <div class="card-header"><h3 class="card-title">Customer Details</h3></div>
      <div class="card-body">
        <dl class="row">
          <dt class="col-5">Name</dt><dd class="col-7"><?= Helper::sanitize($c['name']) ?></dd>
          <?php if ($c['bride_name']): ?><dt class="col-5">Bride</dt><dd class="col-7"><?= Helper::sanitize($c['bride_name']) ?></dd><?php endif; ?>
          <?php if ($c['groom_name']): ?><dt class="col-5">Groom</dt><dd class="col-7"><?= Helper::sanitize($c['groom_name']) ?></dd><?php endif; ?>
          <dt class="col-5">NIC</dt><dd class="col-7"><?= Helper::sanitize($c['nic'] ?? '-') ?></dd>
          <dt class="col-5">Mobile</dt><dd class="col-7"><?= Helper::sanitize($c['mobile']) ?></dd>
          <?php if ($c['mobile2']): ?><dt class="col-5">Mobile 2</dt><dd class="col-7"><?= Helper::sanitize($c['mobile2']) ?></dd><?php endif; ?>
          <dt class="col-5">Email</dt><dd class="col-7"><?= Helper::sanitize($c['email'] ?? '-') ?></dd>
          <dt class="col-5">City</dt><dd class="col-7"><?= Helper::sanitize($c['city'] ?? '-') ?></dd>
          <dt class="col-5">Address</dt><dd class="col-7"><?= nl2br(Helper::sanitize($c['address'] ?? '-')) ?></dd>
          <dt class="col-5">Added</dt><dd class="col-7"><?= Helper::formatDate($c['created_at']) ?></dd>
        </dl>
        <?php if ($c['notes']): ?>
        <hr><h6>Notes</h6><p class="text-muted small"><?= nl2br(Helper::sanitize($c['notes'])) ?></p>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-md-8">
    <div class="card vp-card">
      <div class="card-header"><h3 class="card-title">Booking History</h3></div>
      <div class="table-responsive">
        <table class="table table-vcenter vp-table">
          <thead><tr><th>Ref</th><th>Hall</th><th>Event Type</th><th>Event Date</th><th>Amount</th><th>Balance</th><th>Status</th></tr></thead>
          <tbody>
            <?php if ($bookings): foreach ($bookings as $bk): ?>
            <tr>
              <td><a href="<?= BASE_URL ?>/modules/bookings/view.php?id=<?= $bk['id'] ?>"><?= $bk['booking_ref'] ?></a></td>
              <td><?= Helper::sanitize($bk['hall_name'] ?? '-') ?></td>
              <td><?= Helper::sanitize($bk['event_type'] ?? '-') ?></td>
              <td><?= Helper::formatDate($bk['event_date']) ?></td>
              <td><?= Helper::formatCurrency($bk['final_amount']) ?></td>
              <td class="<?= $bk['balance_amount'] > 0 ? 'text-danger' : 'text-success' ?>"><?= Helper::formatCurrency($bk['balance_amount']) ?></td>
              <td><?= Helper::statusBadge($bk['status']) ?></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="7" class="text-center text-muted">No bookings yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
