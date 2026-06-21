<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
$db = Database::getInstance();
$cu = Auth::currentUser();

$search    = trim($_GET['search'] ?? '');
$method    = $_GET['method'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to   = $_GET['date_to'] ?? '';
$page = max(1,(int)($_GET['page'] ?? 1));

$where = ['1=1']; $params = [];
if ($cu['branch_id']) { $where[] = 'p.branch_id=?'; $params[] = $cu['branch_id']; }
if ($search)    { $where[] = '(p.payment_ref LIKE ? OR c.name LIKE ? OR p.reference_number LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($method)    { $where[] = 'p.payment_method=?'; $params[] = $method; }
if ($date_from) { $where[] = 'p.payment_date >= ?'; $params[] = $date_from; }
if ($date_to)   { $where[] = 'p.payment_date <= ?'; $params[] = $date_to; }

$wstr         = implode(' AND ', $where);
$total        = $db->fetchOne("SELECT COUNT(*) as cnt FROM payments p LEFT JOIN customers c ON p.customer_id=c.id WHERE $wstr", $params)['cnt'];
$total_amount = $db->fetchOne("SELECT SUM(p.amount) as total FROM payments p LEFT JOIN customers c ON p.customer_id=c.id WHERE $wstr", $params)['total'] ?? 0;

// Pending balances — confirmed/tentative bookings with balance > 0
$pb_cond = $cu['branch_id'] ? "AND b.branch_id=".(int)$cu['branch_id'] : "";
$pending_balances = $db->fetchAll(
    "SELECT b.id, b.booking_ref, b.event_date, b.event_type, b.final_amount,
            b.paid_amount, b.balance_amount, b.status,
            c.name as customer_name, c.mobile as customer_phone, h.name as hall_name
     FROM bookings b
     LEFT JOIN customers c ON b.customer_id=c.id
     LEFT JOIN halls h ON b.hall_id=h.id
     WHERE b.balance_amount > 0
       AND b.status IN ('confirmed','tentative','completed')
       $pb_cond
     ORDER BY b.event_date ASC LIMIT 50"
);

// Method breakdown (unfiltered by method for KPI purposes)
$method_params = array_filter([$cu['branch_id'] ?? null]);
$method_cond = $cu['branch_id'] ? 'WHERE p.branch_id=?' : '';
$method_breakdown = $db->fetchAll("SELECT p.payment_method, COUNT(*) as cnt, SUM(p.amount) as total FROM payments p $method_cond GROUP BY p.payment_method ORDER BY total DESC", $cu['branch_id'] ? [$cu['branch_id']] : []);

$pg = Helper::paginate($total, $page);
$payments = $db->fetchAll(
    "SELECT p.*, c.name as customer_name, b.booking_ref, i.invoice_number, u.name as received_by_name
     FROM payments p
     LEFT JOIN customers c ON p.customer_id=c.id
     LEFT JOIN bookings b ON p.booking_id=b.id
     LEFT JOIN invoices i ON p.invoice_id=i.id
     LEFT JOIN users u ON p.received_by=u.id
     WHERE $wstr ORDER BY p.payment_date DESC, p.created_at DESC LIMIT ? OFFSET ?",
    array_merge($params, [$pg['per_page'], $pg['offset']])
);

$customers = $db->fetchAll("SELECT id,name,mobile as phone FROM customers ORDER BY name");

$pageTitle  = 'Payments';
$breadcrumbs = [['label' => 'Payments']];
require_once ROOT_PATH . '/includes/header.php';
?>
<style>
/* ── Payment modal ─────────────────────────────────────────── */
.vp-modal-overlay{position:fixed;inset:0;background:rgba(10,20,45,.55);backdrop-filter:blur(3px);z-index:1050;display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .22s;}
.vp-modal-overlay.show{opacity:1;pointer-events:all;}
.vp-modal{background:#fff;border-radius:18px;padding:2rem 2rem 1.6rem;width:100%;max-width:520px;max-height:90vh;overflow-y:auto;transform:translateY(28px) scale(.97);transition:transform .22s;box-shadow:0 24px 60px rgba(10,20,45,.22);}
.vp-modal-overlay.show .vp-modal{transform:translateY(0) scale(1);}
.vp-modal-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.4rem;}
.vp-modal-title{font-size:1.1rem;font-weight:800;color:#0c1a35;letter-spacing:-.02em;}
.vp-modal-close{background:none;border:none;cursor:pointer;color:#9ca3af;padding:.25rem;border-radius:8px;line-height:1;transition:color .15s;}
.vp-modal-close:hover{color:#0c1a35;}
/* KPI */
.kpi-card{border-radius:16px;overflow:hidden;}
.kpi-green .card-body{background:linear-gradient(135deg,#ecfdf5,#d1fae5);}
.kpi-navy  .card-body{background:linear-gradient(135deg,#eef1f8,#dde3f0);}
.kpi-gold  .card-body{background:linear-gradient(135deg,#fdf5e0,#fde68a44);}
.kpi-icon{font-size:2rem;line-height:1;}
.kpi-val{font-size:1.45rem;font-weight:800;color:#0c1a35;letter-spacing:-.03em;}
.kpi-lbl{font-size:.68rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.08em;margin-top:.2rem;}
/* method strip */
.vp-method-strip{display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:1rem;}
.vp-method-chip{display:inline-flex;align-items:center;gap:.35rem;padding:.28rem .85rem;border-radius:99px;font-size:.72rem;font-weight:700;text-decoration:none;border:1.5px solid #e2e8f0;color:#374151;background:#fff;transition:all .15s;}
.vp-method-chip:hover{border-color:#c9a84c;color:#92640c;}
.vp-method-chip.active{background:#0c1a35;color:#fff;border-color:#0c1a35;}
.vp-method-chip .chip-count{background:rgba(255,255,255,.22);border-radius:99px;padding:.05rem .45rem;font-size:.65rem;}
.vp-method-chip:not(.active) .chip-count{background:#f1f5f9;color:#6b7280;}
</style>

<div class="vp-page-header">
  <div>
    <h1 class="vp-page-title">💳 <?= Lang::t('payments') ?></h1>
    <div class="vp-page-sub"><?= number_format($total) ?> transactions</div>
  </div>
  <button type="button" class="btn btn-vp-gold" onclick="openPayModal()">
    <svg xmlns="http://www.w3.org/2000/svg" class="icon me-1" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14"/><path d="M5 12l14 0"/></svg>
    Make Payment
  </button>
</div>

<!-- ═══ Pending Balances Panel ═══ -->
<?php if ($pending_balances): ?>
<div class="card vp-card mb-3" style="border-left:4px solid #d97706;">
  <div class="card-header d-flex align-items-center justify-content-between" style="cursor:pointer;background:#fffbeb;" onclick="this.nextElementSibling.classList.toggle('d-none')">
    <div class="d-flex align-items-center gap-2">
      <span style="font-size:1rem;">💰</span>
      <span class="fw-800" style="color:#d97706;">Pending Balances</span>
      <span class="badge" style="background:#d97706;color:#fff;border-radius:99px;font-size:.7rem;padding:.2rem .65rem;"><?= count($pending_balances) ?></span>
    </div>
    <span style="font-size:.75rem;color:#6b7280;font-weight:600;">Confirmed/Tentative bookings with outstanding balance ▾</span>
  </div>
  <div class="table-responsive">
    <table class="table table-vcenter vp-table mb-0" style="font-size:.82rem;">
      <thead style="background:#fffbeb;">
        <tr>
          <th>Booking</th><th>Customer</th><th>Phone</th><th>Hall</th>
          <th>Event Date</th><th>Status</th>
          <th class="text-end">Total</th><th class="text-end">Paid</th>
          <th class="text-end">Balance</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pending_balances as $pb): ?>
        <tr>
          <td><a href="<?= BASE_URL ?>/modules/bookings/view.php?id=<?= $pb['id'] ?>" class="vp-ref"><?= Helper::sanitize($pb['booking_ref']) ?></a></td>
          <td class="fw-600"><?= Helper::sanitize($pb['customer_name']) ?></td>
          <td style="font-size:.78rem;color:#6b7280;"><?= Helper::sanitize($pb['customer_phone']??'—') ?></td>
          <td style="color:#6b7280;"><?= Helper::sanitize($pb['hall_name']??'—') ?></td>
          <td><?= Helper::formatDate($pb['event_date']) ?></td>
          <td><?= Helper::statusBadge($pb['status']) ?></td>
          <td class="text-end fw-700"><?= Helper::formatCurrency($pb['final_amount']) ?></td>
          <td class="text-end text-success"><?= Helper::formatCurrency($pb['paid_amount']) ?></td>
          <td class="text-end fw-800" style="color:#d97706;"><?= Helper::formatCurrency($pb['balance_amount']) ?></td>
          <td>
            <a href="<?= BASE_URL ?>/modules/payments/create.php?booking_id=<?= $pb['id'] ?>" class="btn btn-sm" style="background:#d97706;color:#fff;border-radius:8px;font-size:.72rem;font-weight:700;padding:.25rem .75rem;">
              Make Payment
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot style="background:#fffbeb;">
        <tr>
          <td colspan="8" class="text-end fw-700" style="font-size:.75rem;color:#6b7280;">TOTAL OUTSTANDING</td>
          <td class="text-end fw-800" style="color:#d97706;"><?= Helper::formatCurrency(array_sum(array_column($pending_balances,'balance_amount'))) ?></td>
          <td></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- KPI Summary -->
<div class="row g-3 mb-3">
  <div class="col-md-4">
    <div class="card vp-card kpi-card kpi-green">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="kpi-icon">💰</div>
        <div>
          <div class="kpi-val"><?= Helper::formatCurrency($total_amount) ?></div>
          <div class="kpi-lbl">Total Received</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card vp-card kpi-card kpi-navy">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="kpi-icon">🧾</div>
        <div>
          <div class="kpi-val"><?= number_format($total) ?></div>
          <div class="kpi-lbl">Total Transactions</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card vp-card kpi-card kpi-gold">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="kpi-icon">📈</div>
        <div>
          <div class="kpi-val"><?= $total > 0 ? Helper::formatCurrency($total_amount / $total) : 'Rs. 0' ?></div>
          <div class="kpi-lbl">Avg per Transaction</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Method Strip -->
<div class="vp-method-strip">
  <a href="?search=<?= urlencode($search) ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>"
     class="vp-method-chip <?= !$method ? 'active' : '' ?>">
    All Methods <span class="chip-count"><?= number_format($total) ?></span>
  </a>
  <?php
  $methodLabels = ['cash'=>'Cash','bank_transfer'=>'Bank Transfer','card'=>'Card','cheque'=>'Cheque','online'=>'Online'];
  foreach ($method_breakdown as $mb):
    $mk = $mb['payment_method'];
    $ml = $methodLabels[$mk] ?? ucfirst(str_replace('_',' ',$mk));
  ?>
  <a href="?method=<?= $mk ?>&search=<?= urlencode($search) ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>"
     class="vp-method-chip <?= $method===$mk ? 'active' : '' ?>">
    <?= $ml ?> <span class="chip-count"><?= $mb['cnt'] ?></span>
  </a>
  <?php endforeach; ?>
</div>

<!-- Filter Bar -->
<div class="vp-filter-bar mb-3">
  <form method="get" class="d-flex gap-2 flex-wrap align-items-end">
    <input type="text" name="search" class="form-control" placeholder="Ref / Customer / Bank ref..." value="<?= Helper::sanitize($search) ?>" style="max-width:240px;">
    <select name="method" class="form-select" style="max-width:160px;">
      <option value="">All Methods</option>
      <?php foreach ($methodLabels as $k=>$v): ?>
      <option value="<?= $k ?>" <?= $method===$k?'selected':'' ?>><?= $v ?></option>
      <?php endforeach; ?>
    </select>
    <input type="date" name="date_from" class="form-control" value="<?= $date_from ?>" style="max-width:148px;">
    <input type="date" name="date_to"   class="form-control" value="<?= $date_to ?>"   style="max-width:148px;">
    <button class="btn btn-vp-primary">Filter</button>
    <a href="<?= BASE_URL ?>/modules/payments/index.php" class="btn btn-vp-outline">Clear</a>
  </form>
</div>

<div class="card vp-card">
  <div class="table-responsive">
    <table class="table table-vcenter vp-table mb-0">
      <thead>
        <tr>
          <th>Ref</th>
          <th>Date</th>
          <th>Customer</th>
          <th>Booking</th>
          <th>Invoice</th>
          <th>Method</th>
          <th>Bank Ref</th>
          <th>Amount</th>
          <th>Received By</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($payments): foreach ($payments as $p): ?>
        <tr>
          <td><a href="<?= BASE_URL ?>/modules/payments/view.php?id=<?= $p['id'] ?>" class="vp-ref"><?= Helper::sanitize($p['payment_ref']) ?></a></td>
          <td><?= Helper::formatDate($p['payment_date']) ?></td>
          <td class="fw-600"><?= Helper::sanitize($p['customer_name']) ?></td>
          <td><?= $p['booking_ref'] ? '<a href="'.BASE_URL.'/modules/bookings/view.php?id='.$p['booking_id'].'" class="vp-ref">'.Helper::sanitize($p['booking_ref']).'</a>' : '—' ?></td>
          <td><?= $p['invoice_number'] ? '<a href="'.BASE_URL.'/modules/invoices/view.php?id='.$p['invoice_id'].'" class="vp-ref">'.Helper::sanitize($p['invoice_number']).'</a>' : '—' ?></td>
          <td><span class="vp-badge badge-confirmed" style="background:#dbeafe;color:#1e40af;"><?= ucfirst(str_replace('_',' ',$p['payment_method'])) ?></span></td>
          <td><?= $p['reference_number'] ? Helper::sanitize($p['reference_number']) : '—' ?></td>
          <td class="fw-700 text-success"><?= Helper::formatCurrency($p['amount']) ?></td>
          <td><?= Helper::sanitize($p['received_by_name']??'—') ?></td>
          <td><a href="<?= BASE_URL ?>/modules/payments/view.php?id=<?= $p['id'] ?>" class="btn btn-vp-outline btn-sm">View</a></td>
        </tr>
        <?php endforeach; else: ?>
        <tr>
          <td colspan="10">
            <div class="empty-state"><div class="empty-icon">💳</div><div class="empty-text">No payments found.</div></div>
          </td>
        </tr>
        <?php endif; ?>
      </tbody>
      <?php if ($payments): ?>
      <tfoot>
        <tr style="background:#f8f9fc;">
          <td colspan="7" class="text-end fw-700 text-vp-navy" style="font-size:.78rem;">PAGE TOTAL</td>
          <td class="fw-800 text-success"><?= Helper::formatCurrency(array_sum(array_column($payments,'amount'))) ?></td>
          <td colspan="2"></td>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>
  <?php if ($pg['pages'] > 1): ?>
  <div class="card-footer d-flex align-items-center">
    <p class="m-0 text-secondary" style="font-size:.8rem;">Showing <?= count($payments) ?> of <?= $total ?></p>
    <ul class="pagination ms-auto mb-0">
      <?php for ($i=1;$i<=$pg['pages'];$i++): ?>
      <li class="page-item <?= $i==$page?'active':'' ?>"><a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&method=<?= $method ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>"><?= $i ?></a></li>
      <?php endfor; ?>
    </ul>
  </div>
  <?php endif; ?>
</div>

<!-- Make Payment Modal -->
<div class="vp-modal-overlay" id="payModal" onclick="if(event.target===this)closePayModal()">
  <div class="vp-modal">
    <div class="vp-modal-head">
      <div class="vp-modal-title">💳 Make Payment</div>
      <button class="vp-modal-close" onclick="closePayModal()">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>
    <p style="font-size:.8rem;color:#6b7280;margin-bottom:1.2rem;">Fill in the quick details below — you'll be taken to the full form to confirm and save.</p>

    <div class="mb-3">
      <label class="form-label required">Customer</label>
      <select id="pm_customer" class="form-select">
        <option value="">— Select Customer —</option>
        <?php foreach ($customers as $c): ?>
        <option value="<?= $c['id'] ?>"><?= Helper::sanitize($c['name']) ?> (<?= Helper::sanitize($c['phone']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="row mb-3">
      <div class="col-7">
        <label class="form-label required">Amount (Rs.)</label>
        <input type="number" id="pm_amount" class="form-control" step="0.01" min="0.01" placeholder="0.00">
      </div>
      <div class="col-5">
        <label class="form-label required">Date</label>
        <input type="date" id="pm_date" class="form-control" value="<?= date('Y-m-d') ?>">
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label required">Payment Method</label>
      <select id="pm_method" class="form-select">
        <option value="cash">Cash</option>
        <option value="bank_transfer">Bank Transfer</option>
        <option value="card">Card</option>
        <option value="cheque">Cheque</option>
        <option value="online">Online</option>
      </select>
    </div>

    <div class="mb-4">
      <label class="form-label">Reference / Cheque #</label>
      <input type="text" id="pm_ref" class="form-control" placeholder="Optional bank / cheque ref">
    </div>

    <div class="d-flex gap-2">
      <button class="btn btn-vp-gold flex-fill" onclick="submitPayModal()">Continue to Form →</button>
      <button class="btn btn-vp-outline" onclick="closePayModal()">Cancel</button>
    </div>
  </div>
</div>

<script>
function openPayModal()  { document.getElementById('payModal').classList.add('show'); }
function closePayModal() { document.getElementById('payModal').classList.remove('show'); }
document.addEventListener('keydown', e => { if (e.key==='Escape') closePayModal(); });

function submitPayModal() {
  const customer = document.getElementById('pm_customer').value;
  const amount   = document.getElementById('pm_amount').value;
  const date     = document.getElementById('pm_date').value;
  const method   = document.getElementById('pm_method').value;
  const ref      = document.getElementById('pm_ref').value;

  if (!customer) { alert('Please select a customer.'); return; }
  if (!amount || parseFloat(amount) <= 0) { alert('Please enter a valid amount.'); return; }
  if (!date) { alert('Please select a payment date.'); return; }

  const params = new URLSearchParams({
    prefill_customer: customer,
    prefill_amount:   amount,
    prefill_date:     date,
    prefill_method:   method,
    prefill_ref:      ref
  });
  window.location.href = '<?= BASE_URL ?>/modules/payments/create.php?' + params.toString();
}
</script>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
