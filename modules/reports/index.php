<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
if (!Auth::hasRole(['super_admin','hall_manager','accountant'])) { Helper::flash('error','Access denied.'); Helper::redirect(BASE_URL.'/index.php'); }
$db = Database::getInstance();
$cu = Auth::currentUser();

// Date range filter
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to   = $_GET['date_to']   ?? date('Y-m-d');
$branch_id = $cu['branch_id'] ?? (int)($_GET['branch_id'] ?? 0);

$b_cond_bk  = $branch_id ? "AND b.branch_id=$branch_id"  : "";
$b_cond_pay = $branch_id ? "AND p.branch_id=$branch_id" : "";
$b_cond_inv = $branch_id ? "AND i.branch_id=$branch_id" : "";
$b_cond     = $b_cond_bk; // default alias

// Stats for date range
$total_bookings   = $db->fetchOne("SELECT COUNT(*) as cnt FROM bookings b WHERE b.event_date BETWEEN ? AND ? $b_cond_bk", [$date_from,$date_to])['cnt'];
$confirmed_bk     = $db->fetchOne("SELECT COUNT(*) as cnt FROM bookings b WHERE b.status='confirmed' AND b.event_date BETWEEN ? AND ? $b_cond_bk", [$date_from,$date_to])['cnt'];
$total_revenue    = $db->fetchOne("SELECT SUM(p.amount) as total FROM payments p WHERE p.payment_date BETWEEN ? AND ? $b_cond_pay", [$date_from,$date_to])['total'] ?? 0;
$outstanding      = $db->fetchOne("SELECT SUM(b.balance_amount) as total FROM bookings b WHERE b.status IN ('confirmed','tentative') AND b.balance_amount>0 $b_cond_bk")['total'] ?? 0;
$total_invoiced   = $db->fetchOne("SELECT SUM(i.total) as total FROM invoices i WHERE i.invoice_date BETWEEN ? AND ? $b_cond_inv AND i.status NOT IN ('cancelled')", [$date_from,$date_to])['total'] ?? 0;

// Monthly revenue (last 12 months)
$monthly_revenue = $db->fetchAll(
    "SELECT DATE_FORMAT(p.payment_date,'%Y-%m') as month, SUM(p.amount) as total FROM payments p
     WHERE p.payment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) $b_cond_pay
     GROUP BY month ORDER BY month ASC"
);

// Bookings by status
$status_counts = $db->fetchAll(
    "SELECT b.status, COUNT(*) as cnt FROM bookings b WHERE b.event_date BETWEEN ? AND ? $b_cond_bk GROUP BY b.status",
    [$date_from, $date_to]
);

// Bookings by event type
$event_type_counts = $db->fetchAll(
    "SELECT b.event_type, COUNT(*) as cnt FROM bookings b WHERE b.event_date BETWEEN ? AND ? AND b.event_type IS NOT NULL AND b.event_type!='' $b_cond_bk GROUP BY b.event_type ORDER BY cnt DESC LIMIT 10",
    [$date_from, $date_to]
);

// Top customers
$top_customers = $db->fetchAll(
    "SELECT c.name, COUNT(b.id) as bookings, SUM(b.final_amount) as total
     FROM bookings b LEFT JOIN customers c ON b.customer_id=c.id
     WHERE b.event_date BETWEEN ? AND ? $b_cond_bk
     GROUP BY b.customer_id ORDER BY total DESC LIMIT 10",
    [$date_from, $date_to]
);

// Recent payments
$recent_payments = $db->fetchAll(
    "SELECT p.*, c.name as customer_name, b.booking_ref
     FROM payments p LEFT JOIN customers c ON p.customer_id=c.id LEFT JOIN bookings b ON p.booking_id=b.id
     WHERE p.payment_date BETWEEN ? AND ? $b_cond_pay
     ORDER BY p.payment_date DESC LIMIT 15",
    [$date_from, $date_to]
);

$branches = $db->fetchAll("SELECT id,name FROM branches WHERE is_active=1");

$pageTitle = 'Reports';
$breadcrumbs = [['label'=>'Reports']];
require_once ROOT_PATH . '/includes/header.php';
?>
<style>
/* Reports — vp-kpi cards (mirror dashboard) */
.vp-kpi {
  background:#fff; border-radius:16px; padding:1.4rem 1.5rem;
  position:relative; overflow:hidden;
  box-shadow:0 2px 16px rgba(12,26,53,.09); border:1px solid #edf0f8;
  transition:transform .2s, box-shadow .2s; display:block; color:inherit; text-decoration:none;
}
.vp-kpi:hover { transform:translateY(-4px); box-shadow:0 12px 32px rgba(12,26,53,.15); }
.vp-kpi-icon {
  width:50px; height:50px; border-radius:14px;
  display:flex; align-items:center; justify-content:center;
  font-size:1.35rem; margin-bottom:1rem; flex-shrink:0;
}
.vp-kpi-val  { font-size:1.7rem; font-weight:800; color:#0c1a35; letter-spacing:-.04em; line-height:1; }
.vp-kpi-lbl  { font-size:.7rem; font-weight:700; color:#9ca3af; text-transform:uppercase; letter-spacing:.07em; margin-top:.3rem; }
.vp-kpi-footer { margin-top:.9rem; padding-top:.75rem; border-top:1px solid #f1f4fa; display:flex; align-items:center; justify-content:space-between; }
.vp-kpi-trend  { font-size:.74rem; font-weight:600; }
.vp-kpi-link   { font-size:.72rem; font-weight:700; color:#9ca3af; }
.vp-kpi::before { content:''; position:absolute; left:0; top:0; bottom:0; width:4px; border-radius:16px 0 0 16px; }
.vp-kpi-navy::before  { background:linear-gradient(180deg,#0c1a35,#1a3060); }
.vp-kpi-gold::before  { background:linear-gradient(180deg,#c9a84c,#e8c96a); }
.vp-kpi-green::before { background:linear-gradient(180deg,#059669,#34d399); }
.vp-kpi-red::before   { background:linear-gradient(180deg,#dc2626,#f87171); }
.vp-kpi-navy  .vp-kpi-icon { background:#eef1f8; color:#0c1a35; }
.vp-kpi-gold  .vp-kpi-icon { background:#fdf5e0; color:#92640c; }
.vp-kpi-green .vp-kpi-icon { background:#ecfdf5; color:#059669; }
.vp-kpi-red   .vp-kpi-icon { background:#fef2f2; color:#dc2626; }
</style>
<div class="vp-page-header d-print-none">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
    <h1 class="vp-page-title">📊 <?= Lang::t('reports') ?></h1>
    <form method="get" class="d-flex gap-2 flex-wrap align-items-end">
      <input type="date" name="date_from" class="form-control" value="<?= $date_from ?>" style="max-width:150px;">
      <span class="align-self-center">to</span>
      <input type="date" name="date_to" class="form-control" value="<?= $date_to ?>" style="max-width:150px;">
      <?php if (Auth::isSuperAdmin()): ?>
      <select name="branch_id" class="form-select" style="max-width:160px;">
        <option value="">All Branches</option>
        <?php foreach ($branches as $b): ?><option value="<?= $b['id'] ?>" <?= $branch_id==$b['id']?'selected':'' ?>><?= Helper::sanitize($b['name']) ?></option><?php endforeach; ?>
      </select>
      <?php endif; ?>
      <button class="btn btn-vp-primary">Apply</button>
    </form>
  </div>
</div>

<!-- Summary KPIs -->
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="vp-kpi vp-kpi-navy" style="cursor:default;">
      <div class="vp-kpi-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 3v4a1 1 0 001 1h4"/><path d="M17 21H7a2 2 0 01-2-2V5a2 2 0 012-2h7l5 5v11a2 2 0 01-2 2z"/></svg>
      </div>
      <div class="vp-kpi-val"><?= number_format($total_bookings) ?></div>
      <div class="vp-kpi-lbl">Total Bookings</div>
      <div class="vp-kpi-footer">
        <span class="vp-kpi-trend" style="color:#9ca3af;font-size:.7rem;"><?= date('d M', strtotime($date_from)) ?> – <?= date('d M', strtotime($date_to)) ?></span>
        <span class="vp-kpi-link" style="color:#9ca3af;">Selected range</span>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="vp-kpi vp-kpi-green" style="cursor:default;">
      <div class="vp-kpi-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12l5 5L20 7"/></svg>
      </div>
      <div class="vp-kpi-val"><?= number_format($confirmed_bk) ?></div>
      <div class="vp-kpi-lbl">Confirmed</div>
      <div class="vp-kpi-footer">
        <span class="vp-kpi-trend" style="color:#059669;"><?= $total_bookings > 0 ? round($confirmed_bk/$total_bookings*100) : 0 ?>% of total</span>
        <span class="vp-kpi-link" style="color:#9ca3af;">Bookings</span>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="vp-kpi vp-kpi-gold" style="cursor:default;">
      <div class="vp-kpi-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
      </div>
      <div class="vp-kpi-val" style="font-size:1.25rem;"><?= Helper::formatCurrency($total_revenue) ?></div>
      <div class="vp-kpi-lbl">Revenue Collected</div>
      <div class="vp-kpi-footer">
        <span class="vp-kpi-trend" style="color:#9ca3af;font-size:.68rem;">Invoiced: <?= Helper::formatCurrency($total_invoiced) ?></span>
        <span class="vp-kpi-link" style="color:#9ca3af;">Payments</span>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="vp-kpi vp-kpi-red" style="cursor:default;">
      <div class="vp-kpi-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 9v4M12 17h.01"/><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
      </div>
      <div class="vp-kpi-val" style="font-size:1.25rem;"><?= Helper::formatCurrency($outstanding) ?></div>
      <div class="vp-kpi-lbl">Outstanding Balance</div>
      <div class="vp-kpi-footer">
        <span class="vp-kpi-trend" style="color:#dc2626;">Pending collection</span>
        <span class="vp-kpi-link" style="color:#9ca3af;">All active</span>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <!-- Monthly Revenue Chart -->
  <div class="col-lg-8 mb-3">
    <div class="card vp-card h-100">
      <div class="card-header"><h3 class="card-title">Monthly Revenue (Last 12 Months)</h3></div>
      <div class="card-body"><div id="revenue-chart" style="height:280px"></div></div>
    </div>
  </div>

  <!-- Bookings by Status -->
  <div class="col-lg-4 mb-3">
    <div class="card vp-card h-100">
      <div class="card-header"><h3 class="card-title">Bookings by Status</h3></div>
      <div class="card-body"><div id="status-chart" style="height:280px"></div></div>
    </div>
  </div>
</div>

<div class="row">
  <!-- Event Types -->
  <div class="col-lg-6 mb-3">
    <div class="card vp-card">
      <div class="card-header"><h3 class="card-title">Bookings by Event Type</h3></div>
      <div class="table-responsive">
        <table class="table table-vcenter vp-table mb-0">
          <thead><tr><th>Event Type</th><th class="text-end">Count</th></tr></thead>
          <tbody>
            <?php foreach ($event_type_counts as $et): ?>
            <tr>
              <td><?= Helper::sanitize($et['event_type']) ?></td>
              <td class="text-end">
                <div class="d-flex align-items-center gap-2 justify-content-end">
                  <div class="progress w-50" style="height:6px">
                    <div class="progress-bar" style="width:<?= ($total_bookings > 0 ? round($et['cnt']/$total_bookings*100) : 0) ?>%;background:var(--vp-gold)"></div>
                  </div>
                  <span class="fw-bold"><?= $et['cnt'] ?></span>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$event_type_counts): ?>
            <tr><td colspan="2"><div class="empty-state"><div class="empty-icon">📭</div><div class="empty-text">No event data in range.</div></div></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Top Customers -->
  <div class="col-lg-6 mb-3">
    <div class="card vp-card">
      <div class="card-header"><h3 class="card-title">Top Customers</h3></div>
      <div class="table-responsive">
        <table class="table table-vcenter vp-table mb-0">
          <thead><tr><th>Customer</th><th class="text-end">Bookings</th><th class="text-end">Total Value</th></tr></thead>
          <tbody>
            <?php foreach ($top_customers as $tc): ?>
            <tr>
              <td><?= Helper::sanitize($tc['name']) ?></td>
              <td class="text-end"><?= $tc['bookings'] ?></td>
              <td class="text-end fw-bold" style="color:var(--vp-gold)"><?= Helper::formatCurrency($tc['total']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$top_customers): ?>
            <tr><td colspan="3"><div class="empty-state"><div class="empty-icon">👥</div><div class="empty-text">No customer data in range.</div></div></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Recent Payments -->
<div class="card vp-card mb-3">
  <div class="card-header d-flex align-items-center justify-content-between">
    <h3 class="card-title mb-0">Recent Payments (<?= $date_from ?> to <?= $date_to ?>)</h3>
    <span class="fw-bold text-success"><?= Helper::formatCurrency($total_revenue) ?> total</span>
  </div>
  <div class="table-responsive">
    <table class="table table-vcenter vp-table mb-0">
      <thead><tr><th>Ref</th><th>Date</th><th>Customer</th><th>Booking</th><th>Method</th><th class="text-end">Amount</th></tr></thead>
      <tbody>
        <?php foreach ($recent_payments as $p): ?>
        <tr>
          <td><a href="<?= BASE_URL ?>/modules/payments/view.php?id=<?= $p['id'] ?>" class="vp-ref"><?= Helper::sanitize($p['payment_ref']) ?></a></td>
          <td><?= Helper::formatDate($p['payment_date']) ?></td>
          <td><?= Helper::sanitize($p['customer_name']) ?></td>
          <td><?= $p['booking_ref'] ? '<span class="vp-ref">'.Helper::sanitize($p['booking_ref']).'</span>' : '—' ?></td>
          <td><?= ucfirst(str_replace('_',' ',$p['payment_method'])) ?></td>
          <td class="text-end fw-bold" style="color:var(--vp-gold)"><?= Helper::formatCurrency($p['amount']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$recent_payments): ?>
        <tr><td colspan="6"><div class="empty-state"><div class="empty-icon">💳</div><div class="empty-text">No payments in this date range.</div></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.44.0/dist/apexcharts.min.js"></script>
<script>
// Revenue Chart
const months = <?= json_encode(array_column($monthly_revenue,'month')) ?>;
const revenues = <?= json_encode(array_map(fn($r)=>(float)$r['total'], $monthly_revenue)) ?>;
new ApexCharts(document.getElementById('revenue-chart'), {
  chart: { type: 'bar', height: 280, toolbar: { show: false }, background: 'transparent' },
  series: [{ name: 'Revenue (Rs.)', data: revenues }],
  xaxis: { categories: months, labels: { style: { colors: '#8899aa' } } },
  colors: ['#c9a84c'],
  fill: { type: 'gradient', gradient: { shade: 'light', type: 'vertical', gradientToColors: ['#0f1f3d'], stops: [0, 100] } },
  yaxis: { labels: { formatter: v => 'Rs. '+v.toLocaleString(), style: { colors: '#8899aa' } } },
  tooltip: { y: { formatter: v => 'Rs. '+v.toLocaleString() }, theme: 'dark' },
  grid: { borderColor: '#1e3155' },
  dataLabels: { enabled: false }
}).render();

// Status Chart
const statusData = <?= json_encode($status_counts) ?>;
new ApexCharts(document.getElementById('status-chart'), {
  chart: { type: 'donut', height: 280, background: 'transparent' },
  series: statusData.map(s => parseInt(s.cnt)),
  labels: statusData.map(s => s.status.charAt(0).toUpperCase()+s.status.slice(1)),
  colors: ['#8899aa','#c9a84c','#2fb344','#0f1f3d','#d63939'],
  legend: { position: 'bottom', labels: { colors: '#8899aa' } },
  tooltip: { theme: 'dark' },
  dataLabels: { style: { colors: ['#fff'] } }
}).render();
</script>
<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
