<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
$db = Database::getInstance();
$cu = Auth::currentUser();

$search    = trim($_GET['search'] ?? '');
$status    = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to   = $_GET['date_to'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));

$where = ['1=1']; $params = [];
if ($cu['branch_id']) { $where[] = 'b.branch_id=?'; $params[] = $cu['branch_id']; }
if ($search) { $where[] = '(b.booking_ref LIKE ? OR c.name LIKE ? OR c.mobile LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($status) { $where[] = 'b.status=?'; $params[] = $status; }
if ($date_from) { $where[] = 'b.event_date >= ?'; $params[] = $date_from; }
if ($date_to)   { $where[] = 'b.event_date <= ?'; $params[] = $date_to; }

$wstr  = implode(' AND ', $where);
$total = $db->fetchOne("SELECT COUNT(*) as cnt FROM bookings b LEFT JOIN customers c ON b.customer_id=c.id WHERE $wstr", $params)['cnt'];
$pg    = Helper::paginate($total, $page);

$bookings = $db->fetchAll(
    "SELECT b.*, c.name as customer_name, c.mobile as customer_phone, h.name as hall_name, br.name as branch_name
     FROM bookings b
     LEFT JOIN customers c ON b.customer_id=c.id
     LEFT JOIN halls h ON b.hall_id=h.id
     LEFT JOIN branches br ON b.branch_id=br.id
     WHERE $wstr ORDER BY b.event_date DESC LIMIT ? OFFSET ?",
    array_merge($params, [$pg['per_page'], $pg['offset']])
);

$pageTitle  = 'Bookings';
$breadcrumbs = [['label' => 'Bookings']];
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="vp-page-header">
  <div>
    <h1 class="vp-page-title">📋 <?= Lang::t('bookings') ?></h1>
    <div class="vp-page-sub"><?= $total ?> total bookings</div>
  </div>
  <a href="<?= BASE_URL ?>/modules/bookings/create.php" class="btn btn-vp-gold">
    <svg xmlns="http://www.w3.org/2000/svg" class="icon me-1" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14"/><path d="M5 12l14 0"/></svg>
    New Booking
  </a>
</div>

<!-- Filter Bar -->
<div class="vp-filter-bar mb-3">
  <form method="get" class="d-flex gap-2 flex-wrap align-items-end">
    <input type="text" name="search" class="form-control" placeholder="Ref / Customer / Phone..." value="<?= Helper::sanitize($search) ?>" style="max-width:220px;">
    <select name="status" class="form-select" style="max-width:150px;">
      <option value="">All Status</option>
      <?php foreach (['inquiry','booked','confirmed','completed','cancelled'] as $s): ?>
      <option value="<?= $s ?>" <?= $status==$s?'selected':'' ?>><?= ucfirst($s) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="date" name="date_from" class="form-control" value="<?= $date_from ?>" style="max-width:148px;" title="Event from">
    <input type="date" name="date_to"   class="form-control" value="<?= $date_to ?>"   style="max-width:148px;" title="Event to">
    <button class="btn btn-vp-primary">Filter</button>
    <a href="<?= BASE_URL ?>/modules/bookings/index.php" class="btn btn-vp-outline">Clear</a>
  </form>
</div>

<!-- Table -->
<div class="card vp-card">
  <div class="table-responsive">
    <table class="table table-vcenter vp-table mb-0">
      <thead>
        <tr>
          <th>Ref</th>
          <th>Customer</th>
          <th>Hall</th>
          <th>Event Date</th>
          <th>Type</th>
          <th>Guests</th>
          <th>Final Amt</th>
          <th>Paid / Due</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($bookings): foreach ($bookings as $bk): ?>
        <tr>
          <td><a href="<?= BASE_URL ?>/modules/bookings/view.php?id=<?= $bk['id'] ?>" class="vp-ref"><?= Helper::sanitize($bk['booking_ref']) ?></a></td>
          <td>
            <div class="fw-600"><?= Helper::sanitize($bk['customer_name']) ?></div>
            <div class="text-secondary" style="font-size:.75rem;"><?= Helper::sanitize($bk['customer_phone']) ?></div>
          </td>
          <td><?= Helper::sanitize($bk['hall_name'] ?? '—') ?></td>
          <td>
            <div class="fw-600"><?= Helper::formatDate($bk['event_date']) ?></div>
            <?php if ($bk['event_time']): ?><div class="text-secondary" style="font-size:.75rem;"><?= date('h:i A', strtotime($bk['event_time'])) ?></div><?php endif; ?>
          </td>
          <td><?= Helper::sanitize($bk['event_type'] ?? '—') ?></td>
          <td><?= number_format($bk['guest_count']) ?></td>
          <td class="fw-700"><?= Helper::formatCurrency($bk['final_amount']) ?></td>
          <td>
            <div class="text-success fw-600"><?= Helper::formatCurrency($bk['paid_amount']) ?></div>
            <?php if ($bk['balance_amount'] > 0): ?><div class="text-danger" style="font-size:.75rem;">Due: <?= Helper::formatCurrency($bk['balance_amount']) ?></div><?php endif; ?>
          </td>
          <td><?= Helper::statusBadge($bk['status']) ?></td>
          <td>
            <a href="<?= BASE_URL ?>/modules/bookings/view.php?id=<?= $bk['id'] ?>" class="btn btn-vp-outline btn-sm">View</a>
            <?php if (!in_array($bk['status'],['completed','cancelled'])): ?>
            <a href="<?= BASE_URL ?>/modules/bookings/edit.php?id=<?= $bk['id'] ?>" class="btn btn-vp-outline btn-sm">Edit</a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr>
          <td colspan="10">
            <div class="empty-state">
              <div class="empty-icon">📋</div>
              <div class="empty-text">No bookings found. Try adjusting your filters.</div>
            </div>
          </td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pg['pages'] > 1): ?>
  <div class="card-footer d-flex align-items-center">
    <p class="m-0 text-secondary" style="font-size:.8rem;">Showing <?= count($bookings) ?> of <?= $total ?></p>
    <ul class="pagination ms-auto mb-0">
      <?php for ($i=1;$i<=$pg['pages'];$i++): ?>
      <li class="page-item <?= $i==$page?'active':'' ?>">
        <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>"><?= $i ?></a>
      </li>
      <?php endfor; ?>
    </ul>
  </div>
  <?php endif; ?>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
