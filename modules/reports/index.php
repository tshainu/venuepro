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
<div class="row row-cards mb-4">
  <div class="col-sm-6 col-lg-3">
    <div class="kpi-card kpi-navy">
      <div class="kpi-icon">📋</div>
      <div class="kpi-val"><?= $total_bookings ?></div>
      <div class="kpi-lbl">Total Bookings</div>
      <div class="kpi-chip"><?= $date_from ?> – <?= $date_to ?></div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="kpi-card kpi-green">
      <div class="kpi-icon">✅</div>
      <div class="kpi-val"><?= $confirmed_bk ?></div>
      <div class="kpi-lbl">Confirmed</div>
      <div class="kpi-chip"><?= $total_bookings > 0 ? round($confirmed_bk/$total_bookings*100) : 0 ?>% of total</div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="kpi-card kpi-gold">
      <div class="kpi-icon">💰</div>
      <div class="kpi-val"><?= Helper::formatCurrency($total_revenue) ?></div>
      <div class="kpi-lbl">Revenue Collected</div>
      <div class="kpi-chip">Invoiced: <?= Helper::formatCurrency($total_invoiced) ?></div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="kpi-card kpi-red">
      <div class="kpi-icon">⚠️</div>
      <div class="kpi-val"><?= Helper::formatCurrency($outstanding) ?></div>
      <div class="kpi-lbl">Outstanding Balance</div>
      <div class="kpi-chip">All active bookings</div>
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
