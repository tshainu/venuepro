<?php
require_once __DIR__ . '/core/bootstrap.php';
Auth::check();

$db = Database::getInstance();
$cu = Auth::currentUser();
$bid = $cu['branch_id'];

$bFilter  = $bid ? "AND b.branch_id = $bid" : "";

// ── KPIs ──────────────────────────────────────────────────────────────────
$totalBookings = $db->fetchOne("SELECT COUNT(*) as c FROM bookings b WHERE 1 $bFilter")['c'] ?? 0;

$upcomingEvents = $db->fetchOne(
    "SELECT COUNT(*) as c FROM bookings b WHERE b.event_date >= CURDATE() AND b.status IN ('confirmed','tentative') " . ($bid ? "AND b.branch_id=?" : ""),
    $bid ? [$bid] : []
)['c'] ?? 0;

$monthlyRevenue = $db->fetchOne(
    "SELECT COALESCE(SUM(amount),0) as total FROM payments p WHERE MONTH(p.payment_date)=MONTH(CURDATE()) AND YEAR(p.payment_date)=YEAR(CURDATE()) " . ($bid ? "AND p.branch_id=?" : ""),
    $bid ? [$bid] : []
)['total'] ?? 0;

$pendingBalance = $db->fetchOne(
    "SELECT COALESCE(SUM(balance_amount),0) as total FROM bookings b WHERE b.balance_amount > 0 AND b.status NOT IN ('cancelled','completed') " . ($bid ? "AND b.branch_id=?" : ""),
    $bid ? [$bid] : []
)['total'] ?? 0;

$thisMonthBk = $db->fetchOne(
    "SELECT COUNT(*) as c FROM bookings b WHERE MONTH(b.created_at)=MONTH(CURDATE()) AND YEAR(b.created_at)=YEAR(CURDATE()) $bFilter"
)['c'] ?? 0;
$lastMonthBk = $db->fetchOne(
    "SELECT COUNT(*) as c FROM bookings b WHERE MONTH(b.created_at)=MONTH(DATE_SUB(CURDATE(),INTERVAL 1 MONTH)) AND YEAR(b.created_at)=YEAR(DATE_SUB(CURDATE(),INTERVAL 1 MONTH)) $bFilter"
)['c'] ?? 0;
$bkGrowth = $lastMonthBk > 0 ? round((($thisMonthBk - $lastMonthBk) / $lastMonthBk) * 100) : 0;

$totalCustomers = $db->fetchOne("SELECT COUNT(*) as c FROM customers" . ($bid ? " WHERE branch_id=$bid" : ""))['c'] ?? 0;

// Charts
$revenueChart = $db->fetchAll(
    "SELECT DATE_FORMAT(payment_date,'%b %Y') as month, COALESCE(SUM(amount),0) as total
     FROM payments p
     WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) " . ($bid ? "AND p.branch_id=?" : "") . "
     GROUP BY YEAR(payment_date), MONTH(payment_date) ORDER BY payment_date ASC",
    $bid ? [$bid] : []
);
$statusData = $db->fetchAll("SELECT status, COUNT(*) as cnt FROM bookings b WHERE 1 $bFilter GROUP BY status");

// Tables
$recentBookings = $db->fetchAll(
    "SELECT b.*, c.name as customer_name, h.name as hall_name
     FROM bookings b
     LEFT JOIN customers c ON b.customer_id = c.id
     LEFT JOIN halls h ON b.hall_id = h.id
     WHERE 1 $bFilter ORDER BY b.created_at DESC LIMIT 6"
);
$upcomingList = $db->fetchAll(
    "SELECT b.*, c.name as customer_name, h.name as hall_name
     FROM bookings b
     LEFT JOIN customers c ON b.customer_id = c.id
     LEFT JOIN halls h ON b.hall_id = h.id
     WHERE b.event_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)
     AND b.status IN ('confirmed','tentative') $bFilter
     ORDER BY b.event_date ASC LIMIT 6"
);

$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';

$hour  = (int)date('H');
$greet = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
?>

<!-- Dashboard Hero -->
<div class="dash-hero mb-4">
  <div class="hero-gold-line"></div>
  <div class="row align-items-center" style="position:relative;z-index:1">
    <div class="col">
      <div class="greet"><?= $greet ?>, welcome back</div>
      <div class="title"><?= Helper::sanitize($cu['name']) ?></div>
      <div class="subtitle">
        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-top:-2px;opacity:.55"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
        &nbsp;<?= date('l, d F Y') ?> &nbsp;·&nbsp;
        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-top:-2px;opacity:.55"><path d="M3 21l18 0"/><path d="M3 7l9-4 9 4"/><path d="M4 21V8.5l8-4 8 4V21"/></svg>
        &nbsp;<?= Helper::sanitize($cu['branch_name'] ?? 'All Branches') ?>
      </div>
    </div>
    <div class="col-auto d-flex gap-2 flex-wrap">
      <a href="<?= BASE_URL ?>/modules/bookings/index.php" class="btn btn-sm" style="background:rgba(255,255,255,.1);color:rgba(255,255,255,.9);border:1.5px solid rgba(255,255,255,.22);border-radius:8px;font-weight:600;font-size:.8rem;backdrop-filter:blur(4px);">
        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="me-1"><path d="M14 3v4a1 1 0 0 0 1 1h4"/><path d="M17 21H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7l5 5v11a2 2 0 0 1-2 2z"/></svg>
        All Bookings
      </a>
      <a href="<?= BASE_URL ?>/modules/bookings/create.php" class="btn btn-vp-gold btn-sm">
        <svg xmlns="http://www.w3.org/2000/svg" class="icon me-1" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
        New Booking
      </a>
    </div>
  </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="card vp-card kpi-card kpi-navy h-100">
      <div class="card-body">
        <div class="d-flex align-items-start justify-content-between mb-3">
          <div class="kpi-icon">🏛️</div>
          <?php if ($bkGrowth != 0): ?>
          <span class="kpi-chip" style="background:rgba(255,255,255,.15);color:#fff;"><?= $bkGrowth>0?'↑':'↓' ?> <?= abs($bkGrowth) ?>%</span>
          <?php endif; ?>
        </div>
        <div class="kpi-val"><?= number_format($totalBookings) ?></div>
        <div class="kpi-lbl mt-1">Total Bookings</div>
        <a href="<?= BASE_URL ?>/modules/bookings/index.php" class="kpi-link mt-2 d-block">View all →</a>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card vp-card kpi-card kpi-gold h-100">
      <div class="card-body">
        <div class="d-flex align-items-start justify-content-between mb-3">
          <div class="kpi-icon">💰</div>
          <span class="kpi-chip" style="background:rgba(255,255,255,.2);color:#fff;"><?= date('M') ?></span>
        </div>
        <div class="kpi-val" style="font-size:1.4rem;"><?= Helper::formatCurrency($monthlyRevenue) ?></div>
        <div class="kpi-lbl mt-1">Monthly Revenue</div>
        <a href="<?= BASE_URL ?>/modules/reports/index.php" class="kpi-link mt-2 d-block">Reports →</a>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card vp-card kpi-card kpi-green h-100">
      <div class="card-body">
        <div class="d-flex align-items-start justify-content-between mb-3">
          <div class="kpi-icon">🎉</div>
          <span class="kpi-chip" style="background:rgba(255,255,255,.2);color:#fff;">Next 30d</span>
        </div>
        <div class="kpi-val"><?= number_format($upcomingEvents) ?></div>
        <div class="kpi-lbl mt-1">Upcoming Events</div>
        <a href="<?= BASE_URL ?>/modules/calendar/index.php" class="kpi-link mt-2 d-block">Calendar →</a>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card vp-card kpi-card kpi-red h-100">
      <div class="card-body">
        <div class="d-flex align-items-start justify-content-between mb-3">
          <div class="kpi-icon">⚠️</div>
          <span class="kpi-chip" style="background:rgba(255,255,255,.2);color:#fff;">Due</span>
        </div>
        <div class="kpi-val" style="font-size:1.4rem;"><?= Helper::formatCurrency($pendingBalance) ?></div>
        <div class="kpi-lbl mt-1">Outstanding Balance</div>
        <a href="<?= BASE_URL ?>/modules/payments/index.php" class="kpi-link mt-2 d-block">Payments →</a>
      </div>
    </div>
  </div>
</div>

<!-- Quick Actions -->
<div class="vp-section-label">Quick Actions</div>
<div class="row g-2 mb-4">
  <?php
  $actions = [
    ['href'=>BASE_URL.'/modules/bookings/create.php',  'icon'=>'📋', 'bg'=>'#eff6ff', 'label'=>'New Booking'],
    ['href'=>BASE_URL.'/modules/customers/create.php', 'icon'=>'👤', 'bg'=>'#f0fdf4', 'label'=>'Add Customer'],
    ['href'=>BASE_URL.'/modules/quotations/create.php','icon'=>'📄', 'bg'=>'#fefce8', 'label'=>'Quotation'],
    ['href'=>BASE_URL.'/modules/invoices/create.php',  'icon'=>'🧾', 'bg'=>'#fff7ed', 'label'=>'Invoice'],
    ['href'=>BASE_URL.'/modules/payments/create.php',  'icon'=>'💳', 'bg'=>'#fdf4ff', 'label'=>'Payment'],
    ['href'=>BASE_URL.'/modules/reports/index.php',    'icon'=>'📊', 'bg'=>'#f0fdfa', 'label'=>'Reports'],
  ];
  foreach ($actions as $a):
  ?>
  <div class="col-4 col-md-2">
    <a href="<?= $a['href'] ?>" class="vp-quick-action w-100 d-flex flex-column align-items-center">
      <div class="qa-icon" style="background:<?= $a['bg'] ?>"><?= $a['icon'] ?></div>
      <span><?= $a['label'] ?></span>
    </a>
  </div>
  <?php endforeach; ?>
</div>

<!-- Charts Row -->
<div class="row g-3 mb-4">
  <div class="col-lg-8">
    <div class="card vp-card h-100">
      <div class="card-header d-flex align-items-center">
        <h3 class="card-title">Revenue Trend</h3>
        <span class="ms-2 text-secondary" style="font-size:.72rem;">Last 6 months</span>
        <a href="<?= BASE_URL ?>/modules/reports/index.php" class="ms-auto btn btn-vp-outline btn-sm" style="font-size:.72rem;">Full Report</a>
      </div>
      <div class="card-body pt-2">
        <div id="chart-revenue"></div>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card vp-card h-100">
      <div class="card-header">
        <h3 class="card-title">Booking Status</h3>
      </div>
      <div class="card-body pt-2">
        <div id="chart-status"></div>
        <div class="mt-3">
          <?php
          $statusMap = [];
          foreach ($statusData as $s) $statusMap[$s['status']] = $s['cnt'];
          $sList = [
            ['inquiry','#7c3aed','Inquiry'],
            ['tentative','#d97706','Tentative'],
            ['confirmed','#059669','Confirmed'],
            ['completed','#2563eb','Completed'],
            ['cancelled','#dc2626','Cancelled'],
          ];
          foreach ($sList as [$key,$color,$label]):
            $cnt = $statusMap[$key] ?? 0; if (!$cnt) continue;
          ?>
          <div class="d-flex align-items-center mb-1" style="font-size:.78rem;">
            <span style="color:<?= $color ?>;font-size:.65rem;">●</span>
            <span class="ms-1 text-secondary"><?= $label ?></span>
            <span class="ms-auto fw-600"><?= $cnt ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Tables Row -->
<div class="row g-3">
  <!-- Upcoming Events -->
  <div class="col-lg-6">
    <div class="card vp-card h-100">
      <div class="card-header d-flex align-items-center">
        <h3 class="card-title"><span style="color:var(--vp-green)">●</span> Upcoming Events
          <span class="ms-1 text-secondary" style="font-size:.72rem;">(Next 14 days)</span>
        </h3>
        <a href="<?= BASE_URL ?>/modules/calendar/index.php" class="ms-auto btn btn-vp-outline btn-sm" style="font-size:.72rem;">Calendar</a>
      </div>
      <?php if ($upcomingList): ?>
      <div class="table-responsive">
        <table class="table vp-table mb-0">
          <thead>
            <tr><th>Date</th><th>Customer</th><th>Event</th><th>Status</th></tr>
          </thead>
          <tbody>
            <?php foreach ($upcomingList as $ev): ?>
            <tr>
              <td>
                <div class="fw-700 text-vp-navy" style="font-size:.8rem;"><?= date('d M', strtotime($ev['event_date'])) ?></div>
                <div class="text-secondary" style="font-size:.7rem;"><?= date('D', strtotime($ev['event_date'])) ?></div>
              </td>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <span class="vp-avatar vp-avatar-sm"><?= strtoupper(substr($ev['customer_name'],0,1)) ?></span>
                  <span style="font-size:.82rem;"><?= Helper::sanitize($ev['customer_name']) ?></span>
                </div>
              </td>
              <td style="font-size:.8rem;"><?= Helper::sanitize($ev['event_type'] ?? '—') ?></td>
              <td><?= Helper::statusBadge($ev['status']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="card-body">
        <div class="empty-state">
          <div class="empty-icon">📅</div>
          <div class="empty-text">No upcoming events in next 14 days</div>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Recent Bookings -->
  <div class="col-lg-6">
    <div class="card vp-card h-100">
      <div class="card-header d-flex align-items-center">
        <h3 class="card-title"><span style="color:var(--vp-blue)">●</span> Recent Bookings</h3>
        <a href="<?= BASE_URL ?>/modules/bookings/index.php" class="ms-auto btn btn-vp-outline btn-sm" style="font-size:.72rem;">View All</a>
      </div>
      <?php if ($recentBookings): ?>
      <div class="table-responsive">
        <table class="table vp-table mb-0">
          <thead>
            <tr><th>Ref</th><th>Customer</th><th>Amount</th><th>Status</th></tr>
          </thead>
          <tbody>
            <?php foreach ($recentBookings as $bk): ?>
            <tr>
              <td>
                <a href="<?= BASE_URL ?>/modules/bookings/view.php?id=<?= $bk['id'] ?>" class="vp-ref"><?= Helper::sanitize($bk['booking_ref']) ?></a>
                <div class="text-secondary" style="font-size:.7rem;"><?= date('d M Y', strtotime($bk['event_date'])) ?></div>
              </td>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <span class="vp-avatar vp-avatar-sm"><?= strtoupper(substr($bk['customer_name'],0,1)) ?></span>
                  <span style="font-size:.82rem;"><?= Helper::sanitize($bk['customer_name']) ?></span>
                </div>
              </td>
              <td class="fw-600" style="font-size:.82rem;"><?= Helper::formatCurrency($bk['final_amount']) ?></td>
              <td><?= Helper::statusBadge($bk['status']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="card-body">
        <div class="empty-state">
          <div class="empty-icon">📋</div>
          <div class="empty-text">No bookings yet</div>
          <a href="<?= BASE_URL ?>/modules/bookings/create.php" class="btn btn-vp-gold btn-sm mt-2">Create First Booking</a>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Charts JS -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
(function(){
  var labels  = <?= json_encode(array_column($revenueChart,'month')) ?>;
  var data    = <?= json_encode(array_map('floatval', array_column($revenueChart,'total'))) ?>;

  new ApexCharts(document.getElementById('chart-revenue'), {
    chart: { type:'area', height:220, toolbar:{show:false}, fontFamily:'inherit' },
    series: [{ name:'Revenue (Rs.)', data: data.length ? data : [0] }],
    xaxis: { categories: labels.length ? labels : ['—'], labels:{style:{fontSize:'11px'}} },
    yaxis: { labels:{ formatter:function(v){ return 'Rs.'+Number(v).toLocaleString(); }, style:{fontSize:'10px'} } },
    colors: ['#c9a84c'],
    fill: { type:'gradient', gradient:{shadeIntensity:1, opacityFrom:.35, opacityTo:.02, stops:[0,90]} },
    stroke: { curve:'smooth', width:3 },
    grid: { borderColor:'#f0f0f0', strokeDashArray:4 },
    tooltip: { y:{ formatter:function(v){ return 'Rs. '+Number(v).toLocaleString(); } } },
    markers: { size:4, colors:['#c9a84c'], strokeWidth:2, hover:{size:6} },
  }).render();

  var sLabels = <?= json_encode(array_column($statusData,'status')) ?>;
  var sCounts = <?= json_encode(array_map('intval', array_column($statusData,'cnt'))) ?>;
  var sColors = { inquiry:'#7c3aed', tentative:'#d97706', confirmed:'#059669', completed:'#2563eb', cancelled:'#dc2626' };
  var colors  = sLabels.map(function(l){ return sColors[l] || '#94a3b8'; });

  new ApexCharts(document.getElementById('chart-status'), {
    chart: { type:'donut', height:180, fontFamily:'inherit' },
    series: sCounts.length ? sCounts : [1],
    labels: sLabels.length ? sLabels : ['No data'],
    colors: colors.length ? colors : ['#e5e7eb'],
    legend: { show:false },
    plotOptions:{ pie:{ donut:{ size:'65%', labels:{ show:true, total:{ show:true, label:'Total', formatter:function(w){ return w.globals.seriesTotals.reduce(function(a,b){return a+b;},0); } } } } } },
    dataLabels:{ enabled:false },
    stroke:{ width:2 },
  }).render();
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
