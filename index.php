<?php
require_once __DIR__ . '/core/bootstrap.php';
Auth::check();

$db = Database::getInstance();
$cu = Auth::currentUser();
$bid = $cu['branch_id'];
$bFilter = $bid ? "AND b.branch_id = $bid" : "";

// ── KPIs ────────────────────────────────────────────────────────────────
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

$todayEvents = $db->fetchOne(
    "SELECT COUNT(*) as c FROM bookings b WHERE b.event_date = CURDATE() AND b.status IN ('confirmed','tentative') " . ($bid ? "AND b.branch_id=?" : ""),
    $bid ? [$bid] : []
)['c'] ?? 0;

// ── Charts ───────────────────────────────────────────────────────────────
// Bookings chart data — fetch daily data for last 12 months (JS handles range filtering)
$bookingsChartData = $db->fetchAll(
    "SELECT DATE_FORMAT(event_date,'%Y-%m-%d') as ymd,
            DATE_FORMAT(event_date,'%d %b') as day_label,
            DATE_FORMAT(event_date,'%b %Y') as month_label,
            DATE_FORMAT(event_date,'%Y-%m') as ym,
            status,
            COUNT(*) as cnt
     FROM bookings b
     WHERE event_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
       AND event_date <= DATE_ADD(CURDATE(), INTERVAL 6 MONTH) " . ($bid ? "AND b.branch_id=?" : "") . "
     GROUP BY ymd, day_label, month_label, ym, status
     ORDER BY ymd ASC",
    $bid ? [$bid] : []
);
$statusData = $db->fetchAll("SELECT status, COUNT(*) as cnt FROM bookings b WHERE 1 $bFilter GROUP BY status");

// ── Tables ───────────────────────────────────────────────────────────────
$recentBookings = $db->fetchAll(
    "SELECT b.*, c.name as customer_name, h.name as hall_name
     FROM bookings b
     LEFT JOIN customers c ON b.customer_id = c.id
     LEFT JOIN halls h ON b.hall_id = h.id
     WHERE 1 $bFilter ORDER BY b.created_at DESC LIMIT 8"
);
$upcomingList = $db->fetchAll(
    "SELECT b.*, c.name as customer_name, h.name as hall_name
     FROM bookings b
     LEFT JOIN customers c ON b.customer_id = c.id
     LEFT JOIN halls h ON b.hall_id = h.id
     WHERE b.event_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
     AND b.status IN ('confirmed','tentative') $bFilter
     ORDER BY b.event_date ASC LIMIT 8"
);

$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';

$hour  = (int)date('H');
$greet = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
?>

<style>
/* ── HERO ─────────────────────────────────────────── */
.vp-hero {
  background: linear-gradient(130deg, #0a1628 0%, #0f1f40 35%, #162d5a 65%, #0c1e45 100%);
  border-radius: 20px;
  padding: 2.4rem 2.5rem 0;
  position: relative; overflow: hidden;
  box-shadow: 0 16px 56px rgba(10,22,40,.38);
  border: 1px solid rgba(201,168,76,.18);
  margin-bottom: 1.75rem;
}
/* animated gold orb */
.vp-hero::before {
  content:'';
  position:absolute; top:-80px; right:-60px;
  width:340px; height:340px; border-radius:50%;
  background: radial-gradient(circle, rgba(201,168,76,.22) 0%, rgba(201,168,76,.06) 45%, transparent 70%);
  animation: heroOrb 6s ease-in-out infinite alternate;
}
.vp-hero::after {
  content:'';
  position:absolute; bottom:-100px; left:30%;
  width:260px; height:260px; border-radius:50%;
  background: radial-gradient(circle, rgba(100,149,237,.1) 0%, transparent 70%);
  animation: heroOrb2 8s ease-in-out infinite alternate;
}
@keyframes heroOrb  { from{transform:scale(1) translate(0,0)} to{transform:scale(1.12) translate(15px,-15px)} }
@keyframes heroOrb2 { from{transform:scale(1)} to{transform:scale(1.2) translate(-10px,20px)} }

.vp-hero-top {
  display:flex; align-items:flex-start; justify-content:space-between;
  flex-wrap:wrap; gap:1rem;
  position:relative; z-index:2;
}
.vp-hero-greet {
  color: rgba(201,168,76,.9); font-size:.72rem; font-weight:700;
  letter-spacing:.1em; text-transform:uppercase; margin-bottom:.3rem;
}
.vp-hero-name {
  color:#fff; font-size:2rem; font-weight:800;
  letter-spacing:-.04em; line-height:1.1;
  text-shadow: 0 2px 20px rgba(0,0,0,.3);
}
.vp-hero-sub {
  color:rgba(255,255,255,.38); font-size:.78rem; margin-top:.4rem;
  display:flex; align-items:center; gap:.5rem;
}
.vp-hero-sub span { display:flex; align-items:center; gap:.3rem; }
.vp-hero-actions { display:flex; gap:.6rem; flex-wrap:wrap; padding-top:.2rem; }
.vp-hero-btn-glass {
  background:rgba(255,255,255,.1); color:rgba(255,255,255,.9);
  border:1.5px solid rgba(255,255,255,.2); border-radius:9px;
  font-size:.8rem; font-weight:600; padding:.45rem 1rem;
  text-decoration:none; display:flex; align-items:center; gap:.4rem;
  backdrop-filter:blur(6px); transition:all .18s;
}
.vp-hero-btn-glass:hover { background:rgba(255,255,255,.18); color:#fff; }

/* hero stat strip */
.vp-hero-stats {
  display:grid; grid-template-columns:repeat(4,1fr);
  border-top:1px solid rgba(255,255,255,.08);
  margin-top:1.8rem; position:relative; z-index:2;
}
.vp-hero-stat {
  padding:1.1rem .5rem; text-align:center; position:relative;
  transition: background .18s;
}
.vp-hero-stat:not(:last-child)::after {
  content:''; position:absolute; right:0; top:20%; bottom:20%;
  width:1px; background:rgba(255,255,255,.1);
}
.vp-hero-stat:hover { background:rgba(255,255,255,.04); border-radius:8px; }
.vp-hero-stat-val {
  font-size:1.6rem; font-weight:800; color:#fff; line-height:1;
  letter-spacing:-.03em;
}
.vp-hero-stat-val.gold { color: var(--vp-gold-lt, #e8c96a); }
.vp-hero-stat-lbl {
  font-size:.65rem; font-weight:600; color:rgba(255,255,255,.4);
  text-transform:uppercase; letter-spacing:.07em; margin-top:.3rem;
}
.vp-hero-stat-chip {
  display:inline-block; font-size:.6rem; font-weight:700;
  padding:.15rem .45rem; border-radius:20px; margin-top:.3rem;
}
.chip-up   { background:rgba(5,150,105,.25); color:#6ee7b7; }
.chip-down { background:rgba(220,38,38,.25);  color:#fca5a5; }
.chip-neu  { background:rgba(255,255,255,.1);  color:rgba(255,255,255,.5); }

/* ── KPI CARDS ────────────────────────────────────── */
.vp-kpi {
  background:#fff; border-radius:16px;
  padding:1.4rem 1.5rem; position:relative; overflow:hidden;
  box-shadow:0 2px 16px rgba(12,26,53,.09);
  border:1px solid #edf0f8;
  transition: transform .2s, box-shadow .2s;
  text-decoration:none; display:block; color:inherit;
}
.vp-kpi:hover { transform:translateY(-4px); box-shadow:0 12px 32px rgba(12,26,53,.15); }
.vp-kpi-icon {
  width:72px; height:72px; border-radius:14px;
  display:flex; align-items:center; justify-content:center;
  font-size:1.35rem; margin-bottom:1rem;
  flex-shrink:0; background:transparent;
}
.vp-kpi-icon img { width:72px; height:72px; object-fit:contain; }
.vp-kpi-val {
  font-size:1.7rem; font-weight:800; color:#0c1a35;
  letter-spacing:-.04em; line-height:1;
}
.vp-kpi-lbl {
  font-size:.7rem; font-weight:700; color:#9ca3af;
  text-transform:uppercase; letter-spacing:.07em; margin-top:.3rem;
}
.vp-kpi-footer {
  margin-top:.9rem; padding-top:.75rem;
  border-top:1px solid #f1f4fa;
  display:flex; align-items:center; justify-content:space-between;
}
.vp-kpi-trend { font-size:.74rem; font-weight:600; }
.vp-kpi-link  { font-size:.72rem; font-weight:700; color:#9ca3af; text-decoration:none; }
.vp-kpi-link:hover { color:#0c1a35; }
/* left accent bar */
.vp-kpi::before {
  content:''; position:absolute; left:0; top:0; bottom:0;
  width:4px; border-radius:16px 0 0 16px;
}
.vp-kpi-navy::before  { background:linear-gradient(180deg,#0c1a35,#1a3060); }
.vp-kpi-gold::before  { background:linear-gradient(180deg,#c9a84c,#e8c96a); }
.vp-kpi-green::before { background:linear-gradient(180deg,#059669,#34d399); }
.vp-kpi-red::before   { background:linear-gradient(180deg,#dc2626,#f87171); }

.vp-kpi-navy  .vp-kpi-icon,
.vp-kpi-gold  .vp-kpi-icon,
.vp-kpi-green .vp-kpi-icon,
.vp-kpi-red   .vp-kpi-icon { background:transparent; }

/* ── QUICK ACTIONS ────────────────────────────────── */
.vp-qa-grid {
  display:grid; grid-template-columns:repeat(6,1fr); gap:.7rem;
}
.vp-qa {
  display:flex; flex-direction:column; align-items:center; gap:.5rem;
  padding:1.1rem .5rem; border-radius:14px;
  border:2.5px solid #edf0f8; background:#fff;
  text-decoration:none; color:#374151;
  font-size:.72rem; font-weight:700; text-align:center;
  transition:all .2s; box-shadow:0 1px 4px rgba(12,26,53,.05);
  position:relative; overflow:hidden;
}
.vp-qa::after {
  content:''; position:absolute; inset:0;
  background:linear-gradient(135deg,#0c1a35,#1a3060);
  opacity:0; transition:opacity .2s;
}
.vp-qa:hover::after { opacity:1; }
.vp-qa:hover { transform:translateY(-3px); box-shadow:0 10px 28px rgba(12,26,53,.2); color:#fff; }
.vp-qa-icon {
  width:46px; height:46px; border-radius:13px;
  display:flex; align-items:center; justify-content:center;
  font-size:1.3rem; transition:background .2s;
  position:relative; z-index:1;
}
.vp-qa span { position:relative; z-index:1; }
.vp-qa:hover .vp-qa-icon { background:rgba(255,255,255,.15) !important; }

/* ── CHART CARD ───────────────────────────────────── */
.vp-chart-card {
  background:#fff; border-radius:16px;
  box-shadow:0 2px 16px rgba(12,26,53,.08);
  border:1px solid #edf0f8; overflow:hidden;
}
.vp-chart-header {
  padding:1.1rem 1.4rem; border-bottom:1px solid #f1f4fa;
  display:flex; align-items:center; justify-content:space-between;
}
.vp-chart-title {
  font-size:.9rem; font-weight:800; color:#0c1a35; margin:0;
}
.vp-chart-sub { font-size:.7rem; color:#9ca3af; margin-top:.1rem; }
.vp-chart-body { padding:1.2rem 1rem 1rem; }

/* ── TIMELINE ─────────────────────────────────────── */
.vp-timeline { list-style:none; padding:0; margin:0; }
.vp-timeline-item {
  display:flex; gap:1rem; padding:.85rem 1.4rem;
  border-bottom:1px solid #f5f7fc; position:relative;
  transition:background .15s;
}
.vp-timeline-item:hover { background:#fafbff; }
.vp-timeline-item:last-child { border-bottom:none; }
.vp-tl-date {
  flex-shrink:0; width:44px; text-align:center;
  background:linear-gradient(135deg,#0c1a35,#1a3060);
  border-radius:10px; padding:.5rem .3rem;
  display:flex; flex-direction:column; align-items:center; justify-content:center;
}
.vp-tl-day  { font-size:1.1rem; font-weight:800; color:#fff; line-height:1; }
.vp-tl-mon  { font-size:.58rem; font-weight:600; color:rgba(201,168,76,.9); text-transform:uppercase; letter-spacing:.06em; }
.vp-tl-body { flex:1; min-width:0; }
.vp-tl-name { font-size:.84rem; font-weight:700; color:#1f2937; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.vp-tl-meta { font-size:.72rem; color:#9ca3af; margin-top:.15rem; display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; }
.vp-tl-hall { color:#6b7280; font-weight:500; }

/* ── ACTIVITY FEED ────────────────────────────────── */
.vp-activity { list-style:none; padding:0; margin:0; }
.vp-activity-item {
  display:flex; align-items:flex-start; gap:.9rem;
  padding:.8rem 1.4rem; border-bottom:1px solid #f5f7fc;
  transition:background .15s;
}
.vp-activity-item:hover { background:#fafbff; }
.vp-activity-item:last-child { border-bottom:none; }
.vp-act-dot {
  width:34px; height:34px; border-radius:50%; flex-shrink:0;
  display:flex; align-items:center; justify-content:center; font-size:.95rem;
}
.vp-act-body { flex:1; min-width:0; }
.vp-act-text { font-size:.81rem; font-weight:600; color:#1f2937; line-height:1.4; }
.vp-act-text strong { color:#0c1a35; }
.vp-act-time { font-size:.68rem; color:#9ca3af; margin-top:.2rem; }

/* ── SECTION HEADER ───────────────────────────────── */
.vp-sh {
  display:flex; align-items:center; justify-content:space-between;
  margin-bottom:.85rem;
}
.vp-sh-title {
  font-size:.72rem; font-weight:800; color:#0c1a35;
  text-transform:uppercase; letter-spacing:.1em;
  display:flex; align-items:center; gap:.5rem;
}
.vp-sh-title::before {
  content:''; width:3px; height:14px;
  background:linear-gradient(180deg,#c9a84c,#e8c96a);
  border-radius:2px; display:block;
}
.vp-sh-link {
  font-size:.74rem; font-weight:700; color:#9ca3af;
  text-decoration:none; padding:.3rem .7rem;
  border-radius:7px; border:1.5px solid #edf0f8;
  transition:all .15s;
}
.vp-sh-link:hover { color:#0c1a35; border-color:#0c1a35; background:#f6f8fd; }

@media(max-width:768px){
  .vp-hero-stats { grid-template-columns:repeat(2,1fr); }
  .vp-qa-grid { grid-template-columns:repeat(3,1fr); }
  .vp-hero-name { font-size:1.4rem; }
}
</style>

<!-- ═══════ HERO ═══════ -->
<div class="vp-hero mb-0">
  <div class="vp-hero-top">
    <div>
      <div class="vp-hero-greet"><?= $greet ?>, welcome back</div>
      <div class="vp-hero-name"><?= Helper::sanitize($cu['name']) ?></div>
      <div class="vp-hero-sub">
        <span>
          <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
          <?= date('l, d F Y') ?>
        </span>
        <span style="opacity:.3">·</span>
        <span>
          <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21l18 0"/><path d="M3 7l9-4 9 4"/><path d="M4 21V8.5l8-4 8 4V21"/></svg>
          <?= Helper::sanitize($cu['branch_name'] ?? 'All Branches') ?>
        </span>
      </div>
    </div>
    <div class="vp-hero-actions">
      <a href="<?= BASE_URL ?>/modules/bookings/index.php" class="vp-hero-btn-glass">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 3v4a1 1 0 001 1h4"/><path d="M17 21H7a2 2 0 01-2-2V5a2 2 0 012-2h7l5 5v11a2 2 0 01-2 2z"/></svg>
        All Bookings
      </a>
      <a href="<?= BASE_URL ?>/modules/bookings/create.php" class="btn btn-vp-gold btn-sm" style="border-radius:9px;font-size:.8rem;padding:.48rem 1.1rem;">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="me-1"><path d="M12 5v14M5 12h14"/></svg>
        New Booking
      </a>
    </div>
  </div>

  <!-- Stat strip inside hero -->
  <div class="vp-hero-stats">
    <div class="vp-hero-stat">
      <div class="vp-hero-stat-val gold"><?= number_format($totalBookings) ?></div>
      <div class="vp-hero-stat-lbl">Total Bookings</div>
      <?php if ($bkGrowth != 0): ?>
      <div class="vp-hero-stat-chip <?= $bkGrowth > 0 ? 'chip-up' : 'chip-down' ?>">
        <?= $bkGrowth > 0 ? '↑' : '↓' ?> <?= abs($bkGrowth) ?>% vs last month
      </div>
      <?php endif; ?>
    </div>
    <div class="vp-hero-stat">
      <div class="vp-hero-stat-val"><?= Helper::formatCurrency($monthlyRevenue) ?></div>
      <div class="vp-hero-stat-lbl">Revenue <?= date('M Y') ?></div>
      <div class="vp-hero-stat-chip chip-neu">This month</div>
    </div>
    <div class="vp-hero-stat">
      <div class="vp-hero-stat-val gold"><?= number_format($upcomingEvents) ?></div>
      <div class="vp-hero-stat-lbl">Upcoming Events</div>
      <div class="vp-hero-stat-chip chip-neu">Next 30 days</div>
    </div>
    <div class="vp-hero-stat">
      <div class="vp-hero-stat-val"><?= number_format($totalCustomers) ?></div>
      <div class="vp-hero-stat-lbl">Total Customers</div>
      <?php if ($todayEvents > 0): ?>
      <div class="vp-hero-stat-chip chip-up">🎉 <?= $todayEvents ?> event<?= $todayEvents > 1 ? 's' : '' ?> today</div>
      <?php else: ?>
      <div class="vp-hero-stat-chip chip-neu">All branches</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ═══════ KPI CARDS ═══════ -->
<div class="row g-3 mb-4" style="margin-top:.875rem;">
  <div class="col-6 col-lg-3">
    <a href="<?= BASE_URL ?>/modules/bookings/index.php" class="vp-kpi vp-kpi-navy">
      <div class="vp-kpi-icon">
        <img src="<?= BASE_URL ?>/assets/img/icons/kpi-bookings.gif" alt="Bookings">
      </div>
      <div class="vp-kpi-val"><?= number_format($totalBookings) ?></div>
      <div class="vp-kpi-lbl">Total Bookings</div>
      <div class="vp-kpi-footer">
        <span class="vp-kpi-trend" style="color:<?= $bkGrowth >= 0 ? '#059669' : '#dc2626' ?>">
          <?= $bkGrowth >= 0 ? '↑' : '↓' ?> <?= abs($bkGrowth) ?>% this month
        </span>
        <span class="vp-kpi-link">View all →</span>
      </div>
    </a>
  </div>
  <div class="col-6 col-lg-3">
    <a href="<?= BASE_URL ?>/modules/reports/index.php" class="vp-kpi vp-kpi-gold">
      <div class="vp-kpi-icon">
        <img src="<?= BASE_URL ?>/assets/img/icons/kpi-revenue.gif" alt="Revenue">
      </div>
      <div class="vp-kpi-val" style="font-size:1.35rem;"><?= Helper::formatCurrency($monthlyRevenue) ?></div>
      <div class="vp-kpi-lbl">Monthly Revenue</div>
      <div class="vp-kpi-footer">
        <span class="vp-kpi-trend" style="color:#9ca3af;"><?= date('F Y') ?></span>
        <span class="vp-kpi-link">Reports →</span>
      </div>
    </a>
  </div>
  <div class="col-6 col-lg-3">
    <a href="<?= BASE_URL ?>/modules/calendar/index.php" class="vp-kpi vp-kpi-green">
      <div class="vp-kpi-icon">
        <img src="<?= BASE_URL ?>/assets/img/icons/kpi-events.gif" alt="Events">
      </div>
      <div class="vp-kpi-val"><?= number_format($upcomingEvents) ?></div>
      <div class="vp-kpi-lbl">Upcoming Events</div>
      <div class="vp-kpi-footer">
        <span class="vp-kpi-trend" style="color:#059669;">Next 30 days</span>
        <span class="vp-kpi-link">Calendar →</span>
      </div>
    </a>
  </div>
  <div class="col-6 col-lg-3">
    <a href="<?= BASE_URL ?>/modules/payments/index.php" class="vp-kpi vp-kpi-red">
      <div class="vp-kpi-icon">
        <img src="<?= BASE_URL ?>/assets/img/icons/kpi-pending.gif" alt="Pending">
      </div>
      <div class="vp-kpi-val" style="font-size:1.35rem;"><?= Helper::formatCurrency($pendingBalance) ?></div>
      <div class="vp-kpi-lbl">Outstanding Balance</div>
      <div class="vp-kpi-footer">
        <span class="vp-kpi-trend" style="color:#dc2626;">Pending collection</span>
        <span class="vp-kpi-link">Payments →</span>
      </div>
    </a>
  </div>
</div>

<!-- ═══════ QUICK ACTIONS ═══════ -->
<div class="vp-sh mb-3">
  <div class="vp-sh-title">Quick Actions</div>
</div>
<div class="vp-qa-grid mb-4">
  <?php
  $actions = [
    ['href' => BASE_URL.'/modules/bookings/create.php',   'icon' => '📋', 'bg' => '#eff6ff', 'border' => '#3b82f6', 'label' => 'New Booking'],
    ['href' => BASE_URL.'/modules/customers/create.php',  'icon' => '👤', 'bg' => '#f0fdf4', 'border' => '#10b981', 'label' => 'Add Customer'],
    ['href' => BASE_URL.'/modules/quotations/create.php', 'icon' => '📄', 'bg' => '#fefce8', 'border' => '#f59e0b', 'label' => 'Quotation'],
    ['href' => BASE_URL.'/modules/invoices/create.php',   'icon' => '🧾', 'bg' => '#fff7ed', 'border' => '#f97316', 'label' => 'Invoice'],
    ['href' => BASE_URL.'/modules/payments/create.php',   'icon' => '💳', 'bg' => '#fdf4ff', 'border' => '#a855f7', 'label' => 'Payment'],
    ['href' => BASE_URL.'/modules/reports/index.php',     'icon' => '📊', 'bg' => '#f0fdfa', 'border' => '#06b6d4', 'label' => 'Reports'],
  ];
  foreach ($actions as $a):
  ?>
  <a href="<?= $a['href'] ?>" class="vp-qa" style="border-color:<?= $a['border'] ?>cc;">
    <div class="vp-qa-icon" style="background:<?= $a['bg'] ?>;border:2px solid <?= $a['border'] ?>88;"><?= $a['icon'] ?></div>
    <span><?= $a['label'] ?></span>
  </a>
  <?php endforeach; ?>
</div>

<!-- ═══════ CHARTS ROW ═══════ -->
<div class="row g-3 mb-4">
  <!-- Bookings Status Bar Chart -->
  <div class="col-lg-8">
    <div class="vp-chart-card h-100">
      <div class="vp-chart-header">
        <div>
          <div class="vp-chart-title">Bookings Overview</div>
          <div class="vp-chart-sub" id="chart-bk-sub">By status over time</div>
        </div>
        <div class="d-flex align-items-center gap-2">
          <div class="btn-group btn-group-sm" id="bk-chart-toggle" role="group">
            <button type="button" class="btn bk-toggle-btn active" data-range="thismonth">This Month</button>
            <button type="button" class="btn bk-toggle-btn" data-range="1m">30 Days</button>
            <button type="button" class="btn bk-toggle-btn" data-range="3m">3 Mo</button>
            <button type="button" class="btn bk-toggle-btn" data-range="6m">6 Mo</button>
            <button type="button" class="btn bk-toggle-btn" data-range="year">Year</button>
          </div>
          <a href="<?= BASE_URL ?>/modules/reports/index.php" class="vp-sh-link">Report →</a>
        </div>
      </div>
      <div class="vp-chart-body">
        <div id="chart-bookings"></div>
      </div>
    </div>
  </div>
  <!-- Booking Status Donut -->
  <div class="col-lg-4">
    <div class="vp-chart-card h-100">
      <div class="vp-chart-header">
        <div>
          <div class="vp-chart-title">Booking Status</div>
          <div class="vp-chart-sub">Current distribution</div>
        </div>
      </div>
      <div class="vp-chart-body">
        <div id="chart-status"></div>
        <div class="mt-2">
          <?php
          $statusMap = [];
          foreach ($statusData as $s) $statusMap[$s['status']] = $s['cnt'];
          $sList = [
            ['inquiry',  '#7c3aed', 'Inquiry'],
            ['tentative','#d97706', 'Tentative'],
            ['confirmed','#059669', 'Confirmed'],
            ['completed','#2563eb', 'Completed'],
            ['cancelled','#dc2626', 'Cancelled'],
          ];
          foreach ($sList as [$key, $color, $label]):
            $cnt = $statusMap[$key] ?? 0;
            if (!$cnt) continue;
            $total = array_sum(array_column($statusData, 'cnt')) ?: 1;
            $pct = round(($cnt / $total) * 100);
          ?>
          <div class="d-flex align-items-center mb-2" style="gap:.6rem;">
            <span style="width:10px;height:10px;border-radius:50%;background:<?= $color ?>;flex-shrink:0;display:inline-block;"></span>
            <span style="font-size:.77rem;color:#6b7280;flex:1;"><?= $label ?></span>
            <span style="font-size:.77rem;font-weight:700;color:#1f2937;"><?= $cnt ?></span>
            <span style="font-size:.7rem;color:#9ca3af;width:32px;text-align:right;"><?= $pct ?>%</span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ═══════ TIMELINE + ACTIVITY ═══════ -->
<div class="row g-3">
  <!-- Upcoming Events Timeline -->
  <div class="col-lg-6">
    <div class="vp-chart-card h-100">
      <div class="vp-chart-header">
        <div>
          <div class="vp-chart-title">Upcoming Events</div>
          <div class="vp-chart-sub">Next 30 days</div>
        </div>
        <a href="<?= BASE_URL ?>/modules/calendar/index.php" class="vp-sh-link">Calendar →</a>
      </div>
      <?php if ($upcomingList): ?>
      <ul class="vp-timeline">
        <?php foreach ($upcomingList as $ev):
          $daysLeft = (int)((strtotime($ev['event_date']) - strtotime(date('Y-m-d'))) / 86400);
        ?>
        <li class="vp-timeline-item">
          <div class="vp-tl-date">
            <div class="vp-tl-day"><?= date('d', strtotime($ev['event_date'])) ?></div>
            <div class="vp-tl-mon"><?= date('M', strtotime($ev['event_date'])) ?></div>
          </div>
          <div class="vp-tl-body">
            <div class="vp-tl-name"><?= Helper::sanitize($ev['customer_name']) ?></div>
            <div class="vp-tl-meta">
              <span class="vp-tl-hall">
                <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21l18 0"/><path d="M3 7l9-4 9 4"/><path d="M4 21V8.5l8-4 8 4V21"/></svg>
                <?= Helper::sanitize($ev['hall_name'] ?? '—') ?>
              </span>
              <?php if ($ev['event_type']): ?><span>· <?= Helper::sanitize($ev['event_type']) ?></span><?php endif; ?>
            </div>
          </div>
          <div class="d-flex flex-column align-items-end gap-1">
            <?= Helper::statusBadge($ev['status']) ?>
            <span style="font-size:.65rem;color:<?= $daysLeft === 0 ? '#dc2626' : ($daysLeft <= 3 ? '#d97706' : '#9ca3af') ?>;font-weight:600;">
              <?= $daysLeft === 0 ? 'Today!' : ($daysLeft === 1 ? 'Tomorrow' : "in $daysLeft days") ?>
            </span>
          </div>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php else: ?>
      <div class="p-5 text-center">
        <div style="font-size:2.5rem;opacity:.3;">📅</div>
        <div style="font-size:.83rem;color:#9ca3af;margin-top:.5rem;">No upcoming events in next 30 days</div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Recent Bookings Activity Feed -->
  <div class="col-lg-6">
    <div class="vp-chart-card h-100">
      <div class="vp-chart-header">
        <div>
          <div class="vp-chart-title">Recent Bookings</div>
          <div class="vp-chart-sub">Latest activity</div>
        </div>
        <a href="<?= BASE_URL ?>/modules/bookings/index.php" class="vp-sh-link">View All →</a>
      </div>
      <?php if ($recentBookings): ?>
      <ul class="vp-activity">
        <?php foreach ($recentBookings as $bk):
          $statusColors = [
            'inquiry'   => ['bg' => '#ede9fe', 'icon' => '🔍'],
            'tentative' => ['bg' => '#fef9c3', 'icon' => '⏳'],
            'confirmed' => ['bg' => '#dcfce7', 'icon' => '✅'],
            'completed' => ['bg' => '#dbeafe', 'icon' => '🎉'],
            'cancelled' => ['bg' => '#fee2e2', 'icon' => '❌'],
          ];
          $sc = $statusColors[$bk['status']] ?? ['bg' => '#f1f5f9', 'icon' => '📋'];
        ?>
        <li class="vp-activity-item">
          <div class="vp-act-dot" style="background:<?= $sc['bg'] ?>"><?= $sc['icon'] ?></div>
          <div class="vp-act-body">
            <div class="vp-act-text">
              <strong><?= Helper::sanitize($bk['customer_name']) ?></strong>
              <span style="color:#6b7280;font-weight:400;"> · </span>
              <a href="<?= BASE_URL ?>/modules/bookings/view.php?id=<?= $bk['id'] ?>" class="vp-ref"><?= Helper::sanitize($bk['booking_ref']) ?></a>
            </div>
            <div class="vp-act-time">
              <?= Helper::sanitize($bk['hall_name'] ?? '—') ?> &nbsp;·&nbsp;
              <?= date('d M Y', strtotime($bk['event_date'])) ?> &nbsp;·&nbsp;
              <strong style="color:#1f2937;"><?= Helper::formatCurrency($bk['final_amount']) ?></strong>
            </div>
          </div>
          <div class="flex-shrink-0"><?= Helper::statusBadge($bk['status']) ?></div>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php else: ?>
      <div class="p-5 text-center">
        <div style="font-size:2.5rem;opacity:.3;">📋</div>
        <div style="font-size:.83rem;color:#9ca3af;margin-top:.5rem;">No bookings yet</div>
        <a href="<?= BASE_URL ?>/modules/bookings/create.php" class="btn btn-vp-gold btn-sm mt-3">Create First Booking</a>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Charts JS -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<style>
.bk-toggle-btn {
  border: 1.5px solid #e5e7eb; background: #fff; color: #6b7280;
  font-size: .72rem; font-weight: 700; padding: .3rem .65rem; border-radius: 7px;
  transition: all .15s; cursor: pointer;
}
.bk-toggle-btn.active { background: #0c1a35; color: #e8c96a; border-color: #0c1a35; }
.bk-toggle-btn:hover:not(.active) { background: #f1f4fa; color: #0c1a35; }
</style>
<script>
(function(){
  // ── Bookings chart data from PHP ───────────────────────────────────────
  var rawData = <?= json_encode($bookingsChartData) ?>;

  var statusDef = [
    { key:'confirmed',  label:'Confirmed',  color:'#059669' },
    { key:'tentative',  label:'Tentative',  color:'#d97706' },
    { key:'inquiry',    label:'Inquiry',    color:'#7c3aed' },
    { key:'completed',  label:'Completed',  color:'#2563eb' },
    { key:'cancelled',  label:'Cancelled',  color:'#dc2626' },
  ];

  // Build daily map: { 'YYYY-MM-DD': { day_label, month_label, ym, inquiry:0, ... } }
  var dayMap = {};
  rawData.forEach(function(row) {
    if (!dayMap[row.ymd]) dayMap[row.ymd] = { day_label: row.day_label, month_label: row.month_label, ym: row.ym, inquiry:0, tentative:0, confirmed:0, completed:0, cancelled:0 };
    if (dayMap[row.ymd][row.status] !== undefined) dayMap[row.ymd][row.status] = parseInt(row.cnt);
  });
  var allDays = Object.keys(dayMap).sort();

  // Build monthly map: aggregate from day map
  var monthMap = {};
  allDays.forEach(function(d) {
    var ym = dayMap[d].ym;
    if (!monthMap[ym]) monthMap[ym] = { label: dayMap[d].month_label, inquiry:0, tentative:0, confirmed:0, completed:0, cancelled:0 };
    statusDef.forEach(function(s){ monthMap[ym][s.key] += dayMap[d][s.key]; });
  });
  var allMonths = Object.keys(monthMap).sort();

  function todayStr() {
    var n = new Date();
    return n.getFullYear() + '-' + String(n.getMonth()+1).padStart(2,'0') + '-' + String(n.getDate()).padStart(2,'0');
  }
  function monthCutStr(monthsBack) {
    var n = new Date();
    var y = n.getFullYear(), m = n.getMonth() - monthsBack;
    while (m < 0) { m += 12; y--; }
    return y + '-' + String(m+1).padStart(2,'0');
  }

  var chartEl = document.getElementById('chart-bookings');
  var chartInst = null;

  function buildChart(range) {
    var useDaily = (range === '1m' || range === '3m' || range === 'thismonth');
    var labels, series, colWidth, rotateAngle;

    if (useDaily) {
      // Day-level: filter by date range
      var cutDate;
      if (range === 'thismonth') {
        // First day of current month
        var nm = new Date(); nm.setDate(1);
        cutDate = nm.getFullYear() + '-' + String(nm.getMonth()+1).padStart(2,'0') + '-01';
      } else if (range === '1m') {
        var n = new Date(); n.setDate(n.getDate() - 30);
        cutDate = n.getFullYear() + '-' + String(n.getMonth()+1).padStart(2,'0') + '-' + String(n.getDate()).padStart(2,'0');
      } else { // 3m
        var n3 = new Date(); n3.setDate(n3.getDate() - 90);
        cutDate = n3.getFullYear() + '-' + String(n3.getMonth()+1).padStart(2,'0') + '-' + String(n3.getDate()).padStart(2,'0');
      }
      // For thismonth: generate ALL days of current month even if no data
      var days;
      if (range === 'thismonth') {
        var today = new Date();
        var year = today.getFullYear(), month = today.getMonth()+1;
        var daysInMonth = new Date(year, month, 0).getDate();
        days = [];
        for (var d=1; d<=daysInMonth; d++) {
          days.push(year+'-'+String(month).padStart(2,'0')+'-'+String(d).padStart(2,'0'));
        }
      } else {
        days = allDays.filter(function(d){ return d >= cutDate; });
      }
      labels = days.map(function(d){
        if (range === 'thismonth') return parseInt(d.split('-')[2],10)+''; // just day number: 1,2,3...
        return dayMap[d] ? dayMap[d].day_label : d.slice(8); // fallback to day num
      });
      series = statusDef.map(function(s){
        return { name: s.label, data: days.map(function(d){ return (dayMap[d]&&dayMap[d][s.key]) ? dayMap[d][s.key] : 0; }) };
      });
      colWidth = days.length > 20 ? '70%' : '50%';
      rotateAngle = days.length > 15 ? -45 : 0;
    } else {
      // Month-level
      var cutMonth = range === '6m' ? monthCutStr(5) : (new Date().getFullYear() + '-01');
      var months = allMonths.filter(function(m){ return m >= cutMonth; });
      labels = months.map(function(m){ return monthMap[m] ? monthMap[m].label : m; });
      series = statusDef.map(function(s){
        return { name: s.label, data: months.map(function(m){ return monthMap[m] ? monthMap[m][s.key]||0 : 0; }) };
      });
      colWidth = '60%';
      rotateAngle = 0;
    }

    var colors = statusDef.map(function(s){ return s.color; });

    // Show empty state if no data
    if (!labels.length) {
      chartEl.innerHTML = '<div style="height:230px;display:flex;flex-direction:column;align-items:center;justify-content:center;color:#9ca3af;">' +
        '<div style="font-size:2rem;margin-bottom:.5rem;">📊</div>' +
        '<div style="font-size:.82rem;font-weight:600;">No booking data for this period</div></div>';
      return;
    }
    chartEl.innerHTML = '';

    var opts = {
      chart: {
        type: 'line', height: 240,
        toolbar: { show: false },
        fontFamily: 'Inter,system-ui,sans-serif',
        animations: { enabled: true, speed: 500 },
        dropShadow: { enabled: true, top: 2, left: 0, blur: 4, opacity: 0.08 }
      },
      series: series,
      xaxis: {
        categories: labels,
        labels: {
          style: { fontSize: '10px', fontWeight: 600, colors: '#9ca3af' },
          rotate: rotateAngle,
          hideOverlappingLabels: true,
          trim: false
        },
        axisBorder: { show: false },
        axisTicks: { show: false }
      },
      yaxis: {
        labels: {
          formatter: function(v){ return Number.isInteger(v) ? v : ''; },
          style: { fontSize: '10px', colors: '#9ca3af' }
        },
        min: 0,
        forceNiceScale: true,
        tickAmount: 4
      },
      colors: colors,
      stroke: { curve: 'smooth', width: 2.5 },
      markers: { size: 3, strokeWidth: 2, hover: { size: 5 } },
      fill: { type: 'gradient', gradient: { shade: 'light', type: 'vertical', opacityFrom: 0.18, opacityTo: 0.01 } },
      dataLabels: { enabled: false },
      legend: {
        position: 'top', fontSize: '11px', fontWeight: 600,
        markers: { width: 9, height: 9, radius: 9 },
        itemMargin: { horizontal: 8 }
      },
      grid: { borderColor: '#f1f4fa', strokeDashArray: 4, xaxis: { lines: { show: false } } },
      tooltip: { theme: 'light', shared: true, intersect: false,
        y: { formatter: function(v){ return v + ' bookings'; } }
      },
    };

    if (chartInst) {
      chartInst.destroy();
      chartInst = null;
    }
    chartInst = new ApexCharts(chartEl, opts);
    chartInst.render();
  }

  buildChart('thismonth');

  document.getElementById('bk-chart-toggle').addEventListener('click', function(e) {
    var btn = e.target.closest('.bk-toggle-btn');
    if (!btn) return;
    document.querySelectorAll('.bk-toggle-btn').forEach(function(b){ b.classList.remove('active'); });
    btn.classList.add('active');
    buildChart(btn.dataset.range);
  });

  // ── Donut status chart ─────────────────────────────────────
  var sLabels = <?= json_encode(array_column($statusData,'status')) ?>;
  var sCounts = <?= json_encode(array_map('intval', array_column($statusData,'cnt'))) ?>;
  var sColors = {inquiry:'#7c3aed',tentative:'#d97706',confirmed:'#059669',completed:'#2563eb',cancelled:'#dc2626'};
  var colors  = sLabels.map(function(l){ return sColors[l]||'#94a3b8'; });

  new ApexCharts(document.getElementById('chart-status'), {
    chart: { type:'donut', height:190, fontFamily:'Inter,system-ui,sans-serif', animations:{enabled:true,speed:600} },
    series: sCounts.length ? sCounts : [1],
    labels: sLabels.length ? sLabels.map(function(s){ return s.charAt(0).toUpperCase()+s.slice(1); }) : ['No data'],
    colors: colors.length ? colors : ['#e5e7eb'],
    legend: { show:false },
    plotOptions:{ pie:{ donut:{ size:'68%', labels:{ show:true,
      value:{ fontSize:'18px', fontWeight:'800', color:'#0c1a35', offsetY:4 },
      total:{ show:true, label:'Total', fontSize:'11px', fontWeight:'700', color:'#9ca3af',
        formatter:function(w){ return w.globals.seriesTotals.reduce(function(a,b){return a+b;},0); }
      }
    }}}},
    dataLabels:{ enabled:false },
    stroke:{ width:2, colors:['#fff'] },
    tooltip:{ theme:'light' },
  }).render();
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
