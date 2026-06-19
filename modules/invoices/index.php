<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
$db = Database::getInstance();
$cu = Auth::currentUser();

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$type   = $_GET['invoice_type'] ?? '';
$page   = max(1,(int)($_GET['page'] ?? 1));

$where = ['1=1']; $params = [];
if ($cu['branch_id']) { $where[] = 'i.branch_id=?'; $params[] = $cu['branch_id']; }
if ($search) { $where[] = '(i.invoice_number LIKE ? OR c.name LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($status) { $where[] = 'i.status=?'; $params[] = $status; }
if ($type)   { $where[] = 'i.invoice_type=?'; $params[] = $type; }

$wstr  = implode(' AND ', $where);
$total = $db->fetchOne("SELECT COUNT(*) as cnt FROM invoices i LEFT JOIN customers c ON i.customer_id=c.id WHERE $wstr", $params)['cnt'];
$pg    = Helper::paginate($total, $page);
$invoices = $db->fetchAll(
    "SELECT i.*, c.name as customer_name, b.booking_ref
     FROM invoices i
     LEFT JOIN customers c ON i.customer_id=c.id
     LEFT JOIN bookings b ON i.booking_id=b.id
     WHERE $wstr ORDER BY i.created_at DESC LIMIT ? OFFSET ?",
    array_merge($params, [$pg['per_page'], $pg['offset']])
);

// Status totals
$statusStats = $db->fetchAll("SELECT i.status, COUNT(*) as cnt, COALESCE(SUM(i.total),0) as total, COALESCE(SUM(i.balance),0) as balance FROM invoices i WHERE 1 " . ($cu['branch_id'] ? "AND i.branch_id=".(int)$cu['branch_id'] : "") . " GROUP BY i.status");
$stMap = []; foreach ($statusStats as $s) $stMap[$s['status']] = $s;

// Unpaid bookings (balance > 0, not cancelled, no active unpaid invoice)
$bk_cond = $cu['branch_id'] ? "AND b.branch_id=".(int)$cu['branch_id'] : "";
$unpaid_bookings = $db->fetchAll(
    "SELECT b.id, b.booking_ref, b.event_date, b.event_type, b.final_amount, b.advance_amount, b.balance_amount,
            c.name as customer_name, h.name as hall_name
     FROM bookings b
     LEFT JOIN customers c ON b.customer_id=c.id
     LEFT JOIN halls h ON b.hall_id=h.id
     WHERE b.balance_amount > 0
       AND b.status NOT IN ('cancelled')
       AND NOT EXISTS (
           SELECT 1 FROM invoices i
           WHERE i.booking_id=b.id AND i.status NOT IN ('cancelled')
       )
       $bk_cond
     ORDER BY b.event_date ASC LIMIT 50"
);

$customers = $db->fetchAll("SELECT id,name FROM customers ORDER BY name");
$branches  = $db->fetchAll("SELECT id,name FROM branches WHERE is_active=1");

$pageTitle  = 'Invoices';
$breadcrumbs = [['label' => 'Invoices']];
require_once ROOT_PATH . '/includes/header.php';
?>

<style>
.vp-stat-strip { display:flex; gap:.75rem; flex-wrap:wrap; margin-bottom:1.25rem; }
.vp-stat-chip {
  display:flex; align-items:center; gap:.6rem;
  background:#fff; border:1.5px solid #edf0f8; border-radius:12px;
  padding:.6rem 1rem; flex:1; min-width:140px;
  cursor:pointer; transition:all .15s; text-decoration:none;
}
.vp-stat-chip:hover { border-color:#0c1a35; transform:translateY(-1px); box-shadow:0 4px 14px rgba(12,26,53,.1); }
.vp-stat-chip.active { border-color:var(--sc-color,#0c1a35); background:var(--sc-bg,#f0f3fa); }
.vp-stat-dot { width:10px; height:10px; border-radius:50%; background:var(--sc-color,#0c1a35); flex-shrink:0; }
.vp-stat-val { font-size:1.05rem; font-weight:800; color:#0c1a35; }
.vp-stat-lbl { font-size:.68rem; color:#9ca3af; font-weight:600; text-transform:uppercase; letter-spacing:.05em; }
.vp-ref-chip {
  display:inline-flex; align-items:center;
  background:#f0f3fa; border-radius:6px; padding:.2rem .55rem;
  font-size:.78rem; font-weight:700; color:#0c1a35;
  text-decoration:none; transition:background .15s;
}
.vp-ref-chip:hover { background:#e0e7f5; }

/* Modal */
.vp-modal-overlay {
  display:none; position:fixed; inset:0; z-index:1060;
  background:rgba(12,26,53,.55); backdrop-filter:blur(3px);
  align-items:center; justify-content:center;
}
.vp-modal-overlay.show { display:flex; }
.vp-modal {
  background:#fff; border-radius:20px;
  width:100%; max-width:540px; margin:1rem;
  box-shadow:0 24px 80px rgba(12,26,53,.28); overflow:hidden;
  animation:modalIn .25s cubic-bezier(.34,1.56,.64,1);
}
@keyframes modalIn {
  from { transform:scale(.93) translateY(20px); opacity:0; }
  to   { transform:scale(1) translateY(0); opacity:1; }
}
.vp-modal-header {
  display:flex; align-items:center; justify-content:space-between;
  padding:1.4rem 1.6rem 1.2rem;
  background:linear-gradient(130deg,#0c1a35,#1a3060);
  border-bottom:2px solid rgba(201,168,76,.2);
}
.vp-modal-title { font-size:1rem; font-weight:800; color:#fff; }
.vp-modal-subtitle { font-size:.72rem; color:rgba(255,255,255,.55); margin-top:.15rem; }
.vp-modal-close {
  width:30px; height:30px; border-radius:50%;
  background:rgba(255,255,255,.12); border:none; color:#fff;
  cursor:pointer; display:flex; align-items:center; justify-content:center;
  font-size:1.1rem; transition:background .15s;
}
.vp-modal-close:hover { background:rgba(255,255,255,.25); }
.vp-modal-body { padding:1.6rem; }
.vp-modal-footer {
  padding:1rem 1.6rem; background:#f8f9fc;
  border-top:1px solid #edf0f8;
  display:flex; align-items:center; justify-content:flex-end; gap:.6rem;
}
.vp-field-label {
  font-size:.73rem; font-weight:700; color:#374151;
  text-transform:uppercase; letter-spacing:.05em; margin-bottom:.35rem;
}
.vp-field-label.required::after { content:' *'; color:#dc2626; }
</style>

<div class="vp-page-header">
  <div>
    <h1 class="vp-page-title">🧾 <?= Lang::t('invoices') ?></h1>
    <div class="vp-page-sub"><?= $total ?> invoice<?= $total!==1?'s':'' ?></div>
  </div>
  <button type="button" class="btn btn-vp-gold" onclick="openInvModal()">
    <svg xmlns="http://www.w3.org/2000/svg" class="icon me-1" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5l0 14"/><path d="M5 12l14 0"/></svg>
    New Invoice
  </button>
</div>

<!-- ═══ Unpaid Bookings Panel ═══ -->
<?php if ($unpaid_bookings): ?>
<div class="card vp-card mb-3" style="border-left:4px solid #dc2626;">
  <div class="card-header d-flex align-items-center justify-content-between" style="cursor:pointer;background:#fef2f2;" onclick="this.nextElementSibling.classList.toggle('d-none')">
    <div class="d-flex align-items-center gap-2">
      <span style="font-size:1rem;">⚠️</span>
      <span class="fw-800" style="color:#dc2626;">Bookings Without Invoice</span>
      <span class="badge" style="background:#dc2626;color:#fff;border-radius:99px;font-size:.7rem;padding:.2rem .65rem;"><?= count($unpaid_bookings) ?></span>
    </div>
    <span style="font-size:.75rem;color:#6b7280;font-weight:600;">These bookings have a balance but no invoice yet — click to expand ▾</span>
  </div>
  <div class="table-responsive">
    <table class="table table-vcenter vp-table mb-0" style="font-size:.82rem;">
      <thead style="background:#fef2f2;">
        <tr>
          <th>Booking</th><th>Customer</th><th>Hall</th><th>Event Date</th>
          <th>Event Type</th><th class="text-end">Total</th>
          <th class="text-end">Advance Paid</th><th class="text-end">Balance Due</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($unpaid_bookings as $ub): ?>
        <tr>
          <td><a href="<?= BASE_URL ?>/modules/bookings/view.php?id=<?= $ub['id'] ?>" class="vp-ref-chip"><?= Helper::sanitize($ub['booking_ref']) ?></a></td>
          <td class="fw-600"><?= Helper::sanitize($ub['customer_name']) ?></td>
          <td style="color:#6b7280;"><?= Helper::sanitize($ub['hall_name']??'—') ?></td>
          <td><?= Helper::formatDate($ub['event_date']) ?></td>
          <td><?= Helper::sanitize($ub['event_type']??'—') ?></td>
          <td class="text-end fw-700"><?= Helper::formatCurrency($ub['final_amount']) ?></td>
          <td class="text-end text-success"><?= Helper::formatCurrency($ub['advance_amount']) ?></td>
          <td class="text-end fw-800 text-danger"><?= Helper::formatCurrency($ub['balance_amount']) ?></td>
          <td>
            <a href="<?= BASE_URL ?>/modules/invoices/create.php?booking_id=<?= $ub['id'] ?>" class="btn btn-sm" style="background:#dc2626;color:#fff;border-radius:8px;font-size:.72rem;font-weight:700;padding:.25rem .75rem;">
              Create Invoice
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot style="background:#fef2f2;">
        <tr>
          <td colspan="7" class="text-end fw-700" style="font-size:.75rem;color:#6b7280;">TOTAL OUTSTANDING</td>
          <td class="text-end fw-800 text-danger"><?= Helper::formatCurrency(array_sum(array_column($unpaid_bookings,'balance_amount'))) ?></td>
          <td></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Status strip -->
<div class="vp-stat-strip">
  <?php
  $chips = [
    ['key'=>'draft',    'label'=>'Draft',    'color'=>'#6b7280','bg'=>'#f3f4f6'],
    ['key'=>'sent',     'label'=>'Sent',     'color'=>'#2563eb','bg'=>'#eff6ff'],
    ['key'=>'partial',  'label'=>'Partial',  'color'=>'#d97706','bg'=>'#fffbeb'],
    ['key'=>'paid',     'label'=>'Paid',     'color'=>'#059669','bg'=>'#ecfdf5'],
    ['key'=>'overdue',  'label'=>'Overdue',  'color'=>'#dc2626','bg'=>'#fef2f2'],
    ['key'=>'cancelled','label'=>'Cancelled','color'=>'#9ca3af','bg'=>'#f9fafb'],
  ];
  foreach ($chips as $chip):
    $cnt = $stMap[$chip['key']]['cnt'] ?? 0;
    $bal = $stMap[$chip['key']]['balance'] ?? 0;
  ?>
  <a href="?status=<?= $chip['key'] ?>&search=<?= urlencode($search) ?>&invoice_type=<?= $type ?>"
     class="vp-stat-chip <?= $status===$chip['key']?'active':'' ?>"
     style="--sc-color:<?= $chip['color'] ?>;--sc-bg:<?= $chip['bg'] ?>">
    <div class="vp-stat-dot"></div>
    <div>
      <div class="vp-stat-val"><?= $cnt ?></div>
      <div class="vp-stat-lbl"><?= $chip['label'] ?></div>
    </div>
    <?php if ($bal > 0): ?>
    <div style="margin-left:auto;font-size:.67rem;color:#9ca3af;font-weight:600;"><?= Helper::formatCurrency($bal) ?></div>
    <?php endif; ?>
  </a>
  <?php endforeach; ?>
</div>

<!-- Filter Bar -->
<div class="vp-filter-bar mb-3">
  <form method="get" class="d-flex gap-2 flex-wrap align-items-end">
    <input type="text" name="search" class="form-control" placeholder="Invoice # / Customer..." value="<?= Helper::sanitize($search) ?>" style="max-width:240px;">
    <select name="status" class="form-select" style="max-width:140px;">
      <option value="">All Status</option>
      <?php foreach (['draft','sent','paid','partial','overdue','cancelled'] as $s): ?>
      <option value="<?= $s ?>" <?= $status==$s?'selected':'' ?>><?= ucfirst($s) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="invoice_type" class="form-select" style="max-width:130px;">
      <option value="">All Types</option>
      <?php foreach (['advance','interim','final'] as $t): ?>
      <option value="<?= $t ?>" <?= $type==$t?'selected':'' ?>><?= ucfirst($t) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-vp-primary">Filter</button>
    <a href="<?= BASE_URL ?>/modules/invoices/index.php" class="btn btn-vp-outline">Clear</a>
  </form>
</div>

<div class="card vp-card">
  <div class="table-responsive">
    <table class="table table-vcenter vp-table mb-0">
      <thead>
        <tr>
          <th>Invoice #</th><th>Customer</th><th>Booking</th>
          <th>Date</th><th>Due</th><th>Type</th>
          <th>Total</th><th>Balance</th><th>Status</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($invoices): foreach ($invoices as $inv): ?>
        <tr>
          <td><a href="<?= BASE_URL ?>/modules/invoices/view.php?id=<?= $inv['id'] ?>" class="vp-ref-chip"><?= Helper::sanitize($inv['invoice_number']) ?></a></td>
          <td class="fw-600"><?= Helper::sanitize($inv['customer_name']) ?></td>
          <td>
            <?php if ($inv['booking_ref']): ?>
            <a href="<?= BASE_URL ?>/modules/bookings/view.php?id=<?= $inv['booking_id'] ?>" class="vp-ref-chip"><?= Helper::sanitize($inv['booking_ref']) ?></a>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td><?= Helper::formatDate($inv['invoice_date']) ?></td>
          <td><?= Helper::formatDate($inv['due_date']) ?></td>
          <td><?= Helper::statusBadge($inv['invoice_type']) ?></td>
          <td class="fw-700"><?= Helper::formatCurrency($inv['total']) ?></td>
          <td class="<?= $inv['balance']>0?'text-danger fw-700':'' ?>"><?= Helper::formatCurrency($inv['balance']) ?></td>
          <td><?= Helper::statusBadge($inv['status']) ?></td>
          <td>
            <a href="<?= BASE_URL ?>/modules/invoices/view.php?id=<?= $inv['id'] ?>" class="btn btn-vp-outline btn-sm">View</a>
            <a href="<?= BASE_URL ?>/modules/invoices/pdf.php?id=<?= $inv['id'] ?>" class="btn btn-vp-primary btn-sm" target="_blank">PDF</a>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr>
          <td colspan="10">
            <div class="empty-state"><div class="empty-icon">🧾</div><div class="empty-text">No invoices found.</div></div>
          </td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pg['pages'] > 1): ?>
  <div class="card-footer d-flex align-items-center">
    <p class="m-0 text-secondary" style="font-size:.8rem;">Showing <?= count($invoices) ?> of <?= $total ?></p>
    <ul class="pagination ms-auto mb-0">
      <?php for ($i=1;$i<=$pg['pages'];$i++): ?>
      <li class="page-item <?= $i==$page?'active':'' ?>"><a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>&invoice_type=<?= $type ?>"><?= $i ?></a></li>
      <?php endfor; ?>
    </ul>
  </div>
  <?php endif; ?>
</div>

<!-- ═══════ QUICK NEW INVOICE MODAL ═══════ -->
<div class="vp-modal-overlay" id="invModal" onclick="if(event.target===this)closeInvModal()">
  <div class="vp-modal">
    <div class="vp-modal-header">
      <div>
        <div class="vp-modal-title">🧾 New Invoice</div>
        <div class="vp-modal-subtitle">Redirects to full invoice builder</div>
      </div>
      <button class="vp-modal-close" onclick="closeInvModal()">×</button>
    </div>
    <div class="vp-modal-body">
      <div class="mb-3">
        <div class="vp-field-label required">Customer</div>
        <select id="im_customer" class="form-select" required>
          <option value="">— Select customer —</option>
          <?php foreach ($customers as $c): ?>
          <option value="<?= $c['id'] ?>"><?= Helper::sanitize($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="mb-3">
        <div class="vp-field-label">Invoice Type</div>
        <div class="d-flex gap-2">
          <?php foreach (['advance'=>'Advance','interim'=>'Interim','final'=>'Final'] as $val=>$lbl): ?>
          <label class="d-flex align-items-center gap-1 flex-1" style="cursor:pointer;background:#f6f8fd;border:1.5px solid #edf0f8;border-radius:10px;padding:.5rem .75rem;">
            <input type="radio" name="im_type" value="<?= $val ?>" <?= $val==='advance'?'checked':'' ?>> <?= $lbl ?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="row g-2 mb-3">
        <div class="col-6">
          <div class="vp-field-label">Invoice Date</div>
          <input type="date" id="im_date" class="form-control" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="col-6">
          <div class="vp-field-label">Due Date</div>
          <input type="date" id="im_due" class="form-control" value="<?= date('Y-m-d', strtotime('+7 days')) ?>">
        </div>
      </div>
      <div class="alert alert-info" style="font-size:.8rem;background:#eff6ff;color:#1e40af;border:none;border-radius:10px;padding:.75rem 1rem;">
        After clicking Continue, you'll add line items in the full invoice builder.
      </div>
    </div>
    <div class="vp-modal-footer">
      <button type="button" class="btn btn-vp-outline" onclick="closeInvModal()">Cancel</button>
      <button type="button" class="btn btn-vp-gold" onclick="goCreateInv()">Continue to Builder →</button>
    </div>
  </div>
</div>

<script>
function openInvModal() { document.getElementById('invModal').classList.add('show'); }
function closeInvModal() { document.getElementById('invModal').classList.remove('show'); }
function goCreateInv() {
  var cust = document.getElementById('im_customer').value;
  if (!cust) { document.getElementById('im_customer').focus(); return; }
  var type = document.querySelector('input[name="im_type"]:checked').value;
  var date = document.getElementById('im_date').value;
  var due  = document.getElementById('im_due').value;
  window.location.href = '<?= BASE_URL ?>/modules/invoices/create.php?prefill_customer='+cust+'&prefill_type='+type+'&prefill_date='+date+'&prefill_due='+due;
}
document.addEventListener('keydown', function(e){ if(e.key==='Escape'){ closeInvModal(); } });
</script>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
