<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
$db = Database::getInstance();
$cu = Auth::currentUser();
$id = (int)($_GET['id'] ?? 0);

$bk = $db->fetchOne(
    "SELECT b.*, c.name as customer_name, c.mobile as customer_phone, c.email as customer_email, c.address as customer_address,
            h.name as hall_name, p.name as package_name, p.price as package_price,
            br.name as branch_name, u.name as created_by_name
     FROM bookings b
     LEFT JOIN customers c ON b.customer_id=c.id
     LEFT JOIN halls h ON b.hall_id=h.id
     LEFT JOIN packages p ON b.package_id=p.id
     LEFT JOIN branches br ON b.branch_id=br.id
     LEFT JOIN users u ON b.created_by=u.id
     WHERE b.id=?", [$id]
);
if (!$bk) { Helper::flash('error','Booking not found.'); Helper::redirect(BASE_URL.'/modules/bookings/index.php'); }
if ($cu['branch_id'] && $bk['branch_id'] != $cu['branch_id']) { Helper::flash('error','Access denied.'); Helper::redirect(BASE_URL.'/modules/bookings/index.php'); }

// Status change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_status'])) {
    $new_status = $_POST['new_status'] ?? '';
    if (in_array($new_status, ['inquiry','booked','confirmed','completed','cancelled'])) {
        Logger::log('edit','bookings',$id,$bk['booking_ref'],'status:'.$bk['status'],'status:'.$new_status,'Booking status changed');
        $db->execute("UPDATE bookings SET status=? WHERE id=?", [$new_status, $id]);
        Helper::flash('success','Status updated to '.ucfirst($new_status).'.');
        Helper::redirect(BASE_URL.'/modules/bookings/view.php?id='.$id);
    }
}

$addon_rows = $db->fetchAll("SELECT * FROM booking_addons WHERE booking_id=?", [$id]);
$invoices   = $db->fetchAll("SELECT * FROM invoices WHERE booking_id=? ORDER BY created_at DESC", [$id]);
$payments   = $db->fetchAll(
    "SELECT p.*, u.name as received_by_name FROM payments p LEFT JOIN users u ON p.received_by=u.id WHERE p.booking_id=? ORDER BY p.payment_date DESC", [$id]
);

$pageTitle  = 'Booking: '.$bk['booking_ref'];
$breadcrumbs = [['label'=>'Bookings','url'=>BASE_URL.'/modules/bookings/index.php'],['label'=>$bk['booking_ref']]];
require_once ROOT_PATH . '/includes/header.php';
?>

<!-- Hero Header -->
<div class="vp-detail-header mb-3">
  <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
    <div>
      <div class="vp-ref-lg"><?= Helper::sanitize($bk['booking_ref']) ?></div>
      <h2><?= Helper::sanitize($bk['customer_name']) ?></h2>
      <div class="meta d-flex gap-3 flex-wrap">
        <span>📅 <?= Helper::formatDate($bk['event_date']) ?></span>
        <span>🏛️ <?= Helper::sanitize($bk['hall_name'] ?? '—') ?></span>
        <span>👥 <?= number_format($bk['guest_count']) ?> guests</span>
        <span>🎉 <?= Helper::sanitize($bk['event_type'] ?? '—') ?></span>
      </div>
    </div>
    <div class="d-flex gap-2 flex-wrap align-items-center">
      <?= Helper::statusBadge($bk['status']) ?>
      <?php if (!in_array($bk['status'],['completed','cancelled'])): ?>
      <a href="<?= BASE_URL ?>/modules/bookings/edit.php?id=<?= $id ?>" class="btn btn-sm" style="background:rgba(255,255,255,.15);color:#fff;border:1.5px solid rgba(255,255,255,.35);border-radius:8px;font-weight:600;padding:.42rem .9rem;backdrop-filter:blur(4px);">
        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="me-1"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        Edit
      </a>
      <?php endif; ?>
      <a href="<?= BASE_URL ?>/modules/invoices/create.php?booking_id=<?= $id ?>" class="btn btn-vp-gold btn-sm">+ Invoice</a>
      <a href="<?= BASE_URL ?>/modules/quotations/create.php?booking_id=<?= $id ?>" class="btn btn-sm" style="background:rgba(255,255,255,.15);color:#fff;border:1.5px solid rgba(255,255,255,.35);border-radius:8px;font-weight:600;padding:.42rem .9rem;backdrop-filter:blur(4px);">+ Quotation</a>
      <a href="<?= BASE_URL ?>/modules/payments/create.php?booking_id=<?= $id ?>" class="btn btn-sm" style="background:#059669;color:#fff;border:none;border-radius:8px;font-weight:600;padding:.42rem .9rem;">+ Payment</a>
    </div>
  </div>
</div>

<div class="row g-3">
  <!-- Left: Details, Add-ons, Invoices, Payments -->
  <div class="col-lg-8">

    <!-- Booking Details -->
    <div class="card vp-card mb-3">
      <div class="card-header">
        <h3 class="card-title">Booking Details</h3>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <?php
          $fields = [
            'Branch' => $bk['branch_name'],
            'Created By' => $bk['created_by_name'] ?? '—',
            'Event Date' => Helper::formatDate($bk['event_date']),
            'End Date' => $bk['event_end_date'] ? Helper::formatDate($bk['event_end_date']) : '—',
            'Time' => $bk['event_time'] ? date('h:i A',strtotime($bk['event_time'])).($bk['event_end_time']?' – '.date('h:i A',strtotime($bk['event_end_time'])):'') : '—',
            'Package' => ($bk['package_name'] ?? '—') . ($bk['package_price'] ? ' ('.Helper::formatCurrency($bk['package_price']).')' : ''),
          ];
          foreach ($fields as $lbl => $val):
          ?>
          <div class="col-md-4">
            <div class="vp-info-item">
              <div class="vp-info-label"><?= $lbl ?></div>
              <div class="vp-info-value"><?= Helper::sanitize(is_string($val) ? $val : (string)$val) ?></div>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if ($bk['notes']): ?>
          <div class="col-12">
            <div class="vp-info-item">
              <div class="vp-info-label">Notes</div>
              <div class="vp-info-value" style="font-weight:400;"><?= nl2br(Helper::sanitize($bk['notes'])) ?></div>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Add-ons -->
    <?php if ($addon_rows): ?>
    <div class="card vp-card mb-3">
      <div class="card-header"><h3 class="card-title">Add-ons</h3></div>
      <div class="table-responsive">
        <table class="table table-vcenter vp-table mb-0">
          <thead><tr><th>Name</th><th>Qty</th><th>Unit Price</th><th>Tax %</th><th>Total</th></tr></thead>
          <tbody>
            <?php foreach ($addon_rows as $a): ?>
            <tr>
              <td class="fw-600"><?= Helper::sanitize($a['name']) ?></td>
              <td><?= $a['quantity'] ?></td>
              <td><?= Helper::formatCurrency($a['unit_price']) ?></td>
              <td><?= number_format($a['tax_percent'],1) ?>%</td>
              <td class="fw-700"><?= Helper::formatCurrency($a['total_price']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- Invoices -->
    <div class="card vp-card mb-3">
      <div class="card-header">
        <h3 class="card-title">Invoices</h3>
        <a href="<?= BASE_URL ?>/modules/invoices/create.php?booking_id=<?= $id ?>" class="btn btn-vp-primary btn-sm ms-auto">+ Invoice</a>
      </div>
      <div class="table-responsive">
        <table class="table table-vcenter vp-table mb-0">
          <thead><tr><th>Invoice #</th><th>Date</th><th>Type</th><th>Total</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
            <?php if ($invoices): foreach ($invoices as $inv): ?>
            <tr>
              <td><a href="<?= BASE_URL ?>/modules/invoices/view.php?id=<?= $inv['id'] ?>" class="vp-ref"><?= Helper::sanitize($inv['invoice_number']) ?></a></td>
              <td><?= Helper::formatDate($inv['invoice_date']) ?></td>
              <td><?= Helper::statusBadge($inv['invoice_type']) ?></td>
              <td class="fw-700"><?= Helper::formatCurrency($inv['total']) ?></td>
              <td><?= Helper::statusBadge($inv['status']) ?></td>
              <td>
                <a href="<?= BASE_URL ?>/modules/invoices/view.php?id=<?= $inv['id'] ?>" class="btn btn-vp-outline btn-sm">View</a>
                <a href="<?= BASE_URL ?>/modules/invoices/edit.php?id=<?= $inv['id'] ?>&back=<?= urlencode(BASE_URL.'/modules/bookings/view.php?id='.$id) ?>" class="btn btn-vp-primary btn-sm">Edit</a>
                <a href="<?= BASE_URL ?>/modules/invoices/view.php?id=<?= $inv['id'] ?>&autoprint=1" target="_blank" class="btn btn-vp-outline btn-sm" title="Print Invoice"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg></a>
              </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="6" class="text-center text-secondary py-3">No invoices yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Payments -->
    <div class="card vp-card mb-3">
      <div class="card-header">
        <h3 class="card-title">Payments</h3>
        <a href="<?= BASE_URL ?>/modules/payments/create.php?booking_id=<?= $id ?>" class="btn btn-sm ms-auto" style="background:#059669;color:#fff;border:none;border-radius:8px;font-weight:600;padding:.35rem .85rem;">+ Payment</a>
      </div>
      <div class="table-responsive">
        <table class="table table-vcenter vp-table mb-0">
          <thead><tr><th>Ref</th><th>Date</th><th>Method</th><th>Amount</th><th>Received By</th><th>Actions</th></tr></thead>
          <tbody>
            <?php if ($payments): foreach ($payments as $p): ?>
            <tr>
              <td><a href="<?= BASE_URL ?>/modules/payments/view.php?id=<?= $p['id'] ?>" class="vp-ref"><?= Helper::sanitize($p['payment_ref']) ?></a></td>
              <td><?= Helper::formatDate($p['payment_date']) ?></td>
              <td><?= ucfirst(str_replace('_',' ',$p['payment_method'])) ?></td>
              <td class="fw-700 text-success"><?= Helper::formatCurrency($p['amount']) ?></td>
              <td><?= Helper::sanitize($p['received_by_name'] ?? '—') ?></td>
              <td class="d-flex gap-1">
                <a href="<?= BASE_URL ?>/modules/payments/view.php?id=<?= $p['id'] ?>" class="btn btn-vp-outline btn-sm">View</a>
                <a href="<?= BASE_URL ?>/modules/payments/edit.php?id=<?= $p['id'] ?>" class="btn btn-vp-outline btn-sm">Edit</a>
                <a href="<?= BASE_URL ?>/modules/payments/view.php?id=<?= $p['id'] ?>&autoprint=1" target="_blank" class="btn btn-vp-outline btn-sm" title="Print Receipt"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg></a>
              </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="6" class="text-center text-secondary py-3">No payments yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div><!-- /col-8 -->

  <!-- Right sidebar -->
  <div class="col-lg-4">

    <!-- Financials -->
    <div class="card vp-card mb-3">
      <div class="card-header"><h3 class="card-title">💰 Financials</h3></div>
      <div class="card-body">
        <?php
        $rows = [
          ['Subtotal','total_amount',''],
          ['Tax','tax_amount',''],
          ['Discount','discount_amount','text-danger'],
        ];
        foreach ($rows as [$lbl,$col,$cls]): ?>
        <div class="d-flex justify-content-between mb-2" style="font-size:.85rem;">
          <span class="text-secondary"><?= $lbl ?></span>
          <span class="fw-600 <?= $cls ?>"><?= $lbl==='Discount'?'− ':'' ?><?= Helper::formatCurrency($bk[$col]) ?></span>
        </div>
        <?php endforeach; ?>
        <hr style="border-color:#f0f2f7;margin:.5rem 0;">
        <div class="d-flex justify-content-between mb-2">
          <span class="fw-700 text-vp-navy">Final Amount</span>
          <span class="fw-800 text-vp-navy" style="font-size:1.05rem;"><?= Helper::formatCurrency($bk['final_amount']) ?></span>
        </div>
        <div class="d-flex justify-content-between mb-1" style="font-size:.85rem;">
          <span class="text-success fw-600">Paid</span>
          <span class="text-success fw-700"><?= Helper::formatCurrency($bk['paid_amount']) ?></span>
        </div>
        <div class="d-flex justify-content-between fw-700 <?= $bk['balance_amount'] > 0 ? 'text-danger' : 'text-success' ?>">
          <span>Balance Due</span>
          <span><?= Helper::formatCurrency($bk['balance_amount']) ?></span>
        </div>
        <?php
        $paid_pct = $bk['final_amount'] > 0 ? min(100,($bk['paid_amount']/$bk['final_amount'])*100) : 0;
        ?>
        <div class="progress mt-3" style="height:8px;border-radius:4px;">
          <div class="progress-bar" style="width:<?= $paid_pct ?>%;background:<?= $paid_pct>=100?'#059669':'#d97706' ?>;border-radius:4px;"></div>
        </div>
        <div class="text-secondary mt-1" style="font-size:.72rem;"><?= number_format($paid_pct,1) ?>% paid</div>
      </div>
    </div>

    <!-- Change Status -->
    <?php if (!in_array($bk['status'],['completed','cancelled']) || Auth::isSuperAdmin()): ?>
    <div class="card vp-card mb-3">
      <div class="card-header"><h3 class="card-title">Change Status</h3></div>
      <div class="card-body">
        <form method="post">
          <input type="hidden" name="change_status" value="1">
          <div class="mb-2">
            <select name="new_status" class="form-select">
              <?php foreach (['inquiry','booked','confirmed','completed','cancelled'] as $s): ?>
              <option value="<?= $s ?>" <?= $bk['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="btn btn-vp-primary w-100">Update Status</button>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <!-- Customer -->
    <div class="card vp-card">
      <div class="card-header"><h3 class="card-title">👤 Customer</h3></div>
      <div class="card-body">
        <div class="d-flex align-items-center gap-2 mb-2">
          <span class="vp-avatar vp-avatar-md"><?= strtoupper(substr($bk['customer_name'],0,1)) ?></span>
          <div>
            <div class="fw-700"><?= Helper::sanitize($bk['customer_name']) ?></div>
            <div class="text-secondary" style="font-size:.78rem;"><?= Helper::sanitize($bk['customer_phone']) ?></div>
          </div>
        </div>
        <?php if ($bk['customer_email']): ?><div class="text-secondary mb-1" style="font-size:.78rem;">✉️ <?= Helper::sanitize($bk['customer_email']) ?></div><?php endif; ?>
        <?php if ($bk['customer_address']): ?><div class="text-secondary mb-2" style="font-size:.78rem;">📍 <?= nl2br(Helper::sanitize($bk['customer_address'])) ?></div><?php endif; ?>
        <a href="<?= BASE_URL ?>/modules/customers/view.php?id=<?= $bk['customer_id'] ?>" class="btn btn-vp-outline btn-sm">View Customer →</a>
      </div>
    </div>

  </div><!-- /col-4 -->
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
