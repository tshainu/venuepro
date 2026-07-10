<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
$db = Database::getInstance();
$id = (int)($_GET['id'] ?? 0);
$hall = $db->fetchOne("SELECT h.*, b.name as branch_name FROM halls h LEFT JOIN branches b ON h.branch_id=b.id WHERE h.id=?", [$id]);
if (!$hall) { Helper::flash('error', 'Not found.'); Helper::redirect(BASE_URL.'/modules/halls/index.php'); }

$upcomingBookings = $db->fetchAll(
    "SELECT b.*, c.name as customer_name FROM bookings b LEFT JOIN customers c ON b.customer_id=c.id WHERE b.hall_id=? AND b.event_date >= CURDATE() ORDER BY b.event_date ASC LIMIT 10",
    [$id]
);

$pageTitle = $hall['name'];
$breadcrumbs = [['label'=>'Halls','url'=>BASE_URL.'/modules/halls/index.php'],['label'=>$hall['name']]];
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="vp-page-header d-print-none">
  <div class="row align-items-center">
    <div class="col"><h1 class="vp-page-title"><?= Helper::sanitize($hall['name']) ?></h1></div>
    <div class="col-auto ms-auto">
      <a href="<?= BASE_URL ?>/modules/halls/edit.php?id=<?= $hall['id'] ?>" class="btn btn-vp-gold">Edit</a>
    </div>
  </div>
</div>
<div class="row g-3">
  <div class="col-md-4">
    <div class="card vp-card">
      <div class="card-body">
        <?php if ($hall['image']): ?>
        <img src="<?= BASE_URL ?>/uploads/halls/<?= $hall['image'] ?>" class="img-fluid rounded mb-3">
        <?php endif; ?>
        <dl class="row">
          <dt class="col-6">Branch</dt><dd class="col-6"><?= Helper::sanitize($hall['branch_name']) ?></dd>
          <dt class="col-6">Capacity</dt><dd class="col-6"><?= number_format($hall['capacity']) ?> guests</dd>
          <dt class="col-6">Price/Day</dt><dd class="col-6"><?= Helper::formatCurrency($hall['price_per_day']) ?></dd>
          <dt class="col-6">Status</dt><dd class="col-6"><?= $hall['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>' ?></dd>
        </dl>
        <?php if ($hall['facilities']): ?>
        <hr><h6>Facilities</h6><p class="text-muted small"><?= nl2br(Helper::sanitize($hall['facilities'])) ?></p>
        <?php endif; ?>
        <?php if ($hall['description']): ?>
        <hr><h6>Description</h6><p class="text-muted small"><?= nl2br(Helper::sanitize($hall['description'])) ?></p>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-md-8">
    <div class="card vp-card">
      <div class="card-header"><h3 class="card-title">Upcoming Bookings</h3></div>
      <div class="table-responsive">
        <table class="table table-vcenter vp-table">
          <thead><tr><th>Booking Ref</th><th>Customer</th><th>Event Date</th><th>Type</th><th>Status</th></tr></thead>
          <tbody>
            <?php if ($upcomingBookings): foreach ($upcomingBookings as $bk): ?>
            <tr>
              <td><a href="<?= BASE_URL ?>/modules/bookings/view.php?id=<?= $bk['id'] ?>"><?= $bk['booking_ref'] ?></a></td>
              <td><?= Helper::sanitize($bk['customer_name']) ?></td>
              <td><?= Helper::formatDate($bk['event_date']) ?></td>
              <td><?= Helper::sanitize($bk['event_type'] ?? '-') ?></td>
              <td><?= Helper::statusBadge($bk['status']) ?></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="5" class="text-center text-muted">No upcoming bookings.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
