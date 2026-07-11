<?php
require_once __DIR__ . '/core/bootstrap.php';
Auth::check();

$db = Database::getInstance();
$cu = Auth::currentUser();

// ── Get owner's business branches from session ────────────────────────────
$userUid = $_SESSION['user_uid'] ?? '';
$business_id = null;
$biz = $db->fetchOne("SELECT id, business_name FROM sa_businesses WHERE admin_user_id = ? LIMIT 1", [$userUid]);
if ($biz) $business_id = $biz['id'];

// Use accessible_branches from session (already loaded by Auth)
$sessionBranches = $cu['accessible_branches'] ?? [];
$bizBranches = $sessionBranches;

// If session branches empty, load directly from DB
if (empty($bizBranches) && $business_id) {
    $bizBranches = $db->fetchAll(
        "SELECT id, name FROM branches WHERE business_id = ? AND is_active = 1",
        [$business_id]
    );
}

$branchIds = array_column($bizBranches, 'id');
$branchIn  = $branchIds ? implode(',', array_map('intval', $branchIds)) : '0';

// ── KPIs ──────────────────────────────────────────────────────────────────
$totalRevenue = $db->fetchOne(
    "SELECT COALESCE(SUM(amount),0) as t FROM payments WHERE branch_id IN ($branchIn)"
)['t'] ?? 0;

$thisMonthRevenue = $db->fetchOne(
    "SELECT COALESCE(SUM(amount),0) as t FROM payments
     WHERE branch_id IN ($branchIn)
     AND MONTH(payment_date)=MONTH(CURDATE()) AND YEAR(payment_date)=YEAR(CURDATE())"
)['t'] ?? 0;

$lastMonthRevenue = $db->fetchOne(
    "SELECT COALESCE(SUM(amount),0) as t FROM payments
     WHERE branch_id IN ($branchIn)
     AND MONTH(payment_date)=MONTH(DATE_SUB(CURDATE(),INTERVAL 1 MONTH))
     AND YEAR(payment_date)=YEAR(DATE_SUB(CURDATE(),INTERVAL 1 MONTH))"
)['t'] ?? 0;

$revenueGrowth = $lastMonthRevenue > 0
    ? round((($thisMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100)
    : ($thisMonthRevenue > 0 ? 100 : 0);

$totalOutstanding = $db->fetchOne(
    "SELECT COALESCE(SUM(balance_amount),0) as t FROM bookings
     WHERE branch_id IN ($branchIn) AND balance_amount > 0 AND status NOT IN ('cancelled','completed')"
)['t'] ?? 0;

$totalBookings = $db->fetchOne(
    "SELECT COUNT(*) as c FROM bookings WHERE branch_id IN ($branchIn)"
)['c'] ?? 0;

$activeBookings = $db->fetchOne(
    "SELECT COUNT(*) as c FROM bookings
     WHERE branch_id IN ($branchIn) AND status IN ('confirmed','tentative','booked')"
)['c'] ?? 0;

$upcomingEvents = $db->fetchOne(
    "SELECT COUNT(*) as c FROM bookings
     WHERE branch_id IN ($branchIn) AND event_date >= CURDATE()
     AND status IN ('confirmed','tentative','booked')"
)['c'] ?? 0;

$todayEvents = $db->fetchOne(
    "SELECT COUNT(*) as c FROM bookings
     WHERE branch_id IN ($branchIn) AND event_date = CURDATE()
     AND status IN ('confirmed','tentative','booked')"
)['c'] ?? 0;

$totalCustomers = $db->fetchOne(
    "SELECT COUNT(*) as c FROM customers WHERE branch_id IN ($branchIn)"
)['c'] ?? 0;

$cancelledBookings = $db->fetchOne(
    "SELECT COUNT(*) as c FROM bookings
     WHERE branch_id IN ($branchIn) AND status = 'cancelled'"
)['c'] ?? 0;

// ── Hall Performance ──────────────────────────────────────────────────────
$hallPerformance = $db->fetchAll(
    "SELECT h.id, h.name as hall_name, br.name as branch_name,
            COUNT(b.id) as total_bookings,
            COALESCE(SUM(b.final_amount),0) as total_revenue,
            COALESCE(SUM(b.paid_amount),0) as total_paid,
            COALESCE(SUM(b.balance_amount),0) as outstanding,
            COUNT(CASE WHEN b.event_date >= CURDATE() AND b.status IN ('confirmed','tentative','booked') THEN 1 END) as upcoming
     FROM halls h
     LEFT JOIN branches br ON h.branch_id = br.id
     LEFT JOIN bookings b ON b.hall_id = h.id
     WHERE h.branch_id IN ($branchIn)
     GROUP BY h.id, h.name, br.name
     ORDER BY total_revenue DESC"
);

// ── Branch Performance ────────────────────────────────────────────────────
$branchPerformance = $db->fetchAll(
    "SELECT br.id, br.name as branch_name,
            COUNT(b.id) as total_bookings,
            COALESCE(SUM(b.final_amount),0) as total_revenue,
            COALESCE(SUM(b.paid_amount),0) as total_paid,
            COALESCE(SUM(b.balance_amount),0) as outstanding
     FROM branches br
     LEFT JOIN bookings b ON b.branch_id = br.id
     WHERE br.id IN ($branchIn)
     GROUP BY br.id, br.name
     ORDER BY total_revenue DESC"
);

// ── Monthly Revenue (last 6 months) ──────────────────────────────────────
$monthlyRevChart = $db->fetchAll(
    "SELECT DATE_FORMAT(payment_date,'%b %Y') as label,
            DATE_FORMAT(payment_date,'%Y-%m') as ym,
            COALESCE(SUM(amount),0) as revenue
     FROM payments
     WHERE branch_id IN ($branchIn)
     AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
     GROUP BY ym, label ORDER BY ym ASC"
);

// ── Branch-wise monthly revenue (last 6 months) ───────────────────────────
$branchMonthlyRev = [];
foreach ($bizBranches as $br) {
    $rows = $db->fetchAll(
        "SELECT DATE_FORMAT(payment_date,'%Y-%m') as ym,
                COALESCE(SUM(amount),0) as revenue
         FROM payments
         WHERE branch_id = ?
         AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
         GROUP BY ym ORDER BY ym ASC",
        [$br['id']]
    );
    $branchMonthlyRev[$br['id']] = [
        'name' => $br['name'],
        'data' => array_column($rows, 'revenue', 'ym'),
    ];
}

// ── Per-branch KPI breakdown ──────────────────────────────────────────────
$branchKpi = [];
foreach ($bizBranches as $br) {
    $bid = (int)$br['id'];
    $branchKpi[$bid] = [
        'name'        => $br['name'],
        'revenue'     => (float)($db->fetchOne("SELECT COALESCE(SUM(amount),0) as t FROM payments WHERE branch_id=?", [$bid])['t'] ?? 0),
        'bookings'    => (int)($db->fetchOne("SELECT COUNT(*) as c FROM bookings WHERE branch_id=?", [$bid])['c'] ?? 0),
        'active'      => (int)($db->fetchOne("SELECT COUNT(*) as c FROM bookings WHERE branch_id=? AND status IN ('confirmed','tentative','booked')", [$bid])['c'] ?? 0),
        'upcoming'    => (int)($db->fetchOne("SELECT COUNT(*) as c FROM bookings WHERE branch_id=? AND event_date >= CURDATE() AND status IN ('confirmed','tentative','booked')", [$bid])['c'] ?? 0),
        'outstanding' => (float)($db->fetchOne("SELECT COALESCE(SUM(balance_amount),0) as t FROM bookings WHERE branch_id=? AND balance_amount>0 AND status NOT IN ('cancelled','completed')", [$bid])['t'] ?? 0),
        'customers'   => (int)($db->fetchOne("SELECT COUNT(*) as c FROM customers WHERE branch_id=?", [$bid])['c'] ?? 0),
    ];
}

// ── Booking Status Breakdown ──────────────────────────────────────────────
$statusBreakdown = $db->fetchAll(
    "SELECT status, COUNT(*) as cnt FROM bookings
     WHERE branch_id IN ($branchIn) GROUP BY status"
);

// ── Outstanding Bookings ──────────────────────────────────────────────────
$outstandingList = $db->fetchAll(
    "SELECT b.booking_ref, b.event_date, b.balance_amount, b.final_amount,
            c.name as customer_name, c.mobile as customer_phone,
            h.name as hall_name, br.name as branch_name, b.status
     FROM bookings b
     LEFT JOIN customers c ON b.customer_id = c.id
     LEFT JOIN halls h ON b.hall_id = h.id
     LEFT JOIN branches br ON b.branch_id = br.id
     WHERE b.branch_id IN ($branchIn) AND b.balance_amount > 0
     AND b.status NOT IN ('cancelled','completed')
     ORDER BY b.balance_amount DESC LIMIT 10"
);

// ── Upcoming Events (next 30 days) ────────────────────────────────────────
$upcomingList = $db->fetchAll(
    "SELECT b.booking_ref, b.event_date, b.event_time, b.event_type,
            b.final_amount, b.paid_amount, b.balance_amount, b.status,
            c.name as customer_name, h.name as hall_name, br.name as branch_name
     FROM bookings b
     LEFT JOIN customers c ON b.customer_id = c.id
     LEFT JOIN halls h ON b.hall_id = h.id
     LEFT JOIN branches br ON b.branch_id = br.id
     WHERE b.branch_id IN ($branchIn)
     AND b.event_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
     AND b.status IN ('confirmed','tentative','booked')
     ORDER BY b.event_date ASC LIMIT 10"
);

// ── Recent Payments ───────────────────────────────────────────────────────
$recentPayments = $db->fetchAll(
    "SELECT p.amount, p.payment_date, p.payment_method, p.notes,
            b.booking_ref, c.name as customer_name, br.name as branch_name
     FROM payments p
     LEFT JOIN bookings b ON p.booking_id = b.id
     LEFT JOIN customers c ON b.customer_id = c.id
     LEFT JOIN branches br ON p.branch_id = br.id
     WHERE p.branch_id IN ($branchIn)
     ORDER BY p.payment_date DESC, p.id DESC LIMIT 8"
);

$pageTitle = 'Owner Dashboard';
require_once __DIR__ . '/includes/header.php';

$hour  = (int)date('H');
$greet = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');

// Prepare chart data
$chartLabels  = array_column($monthlyRevChart, 'label');
$chartRevenue = array_column($monthlyRevChart, 'revenue');

$statusMap = [];
foreach ($statusBreakdown as $s) $statusMap[$s['status']] = (int)$s['cnt'];
?>

<style>
/* ── OWNER DASHBOARD ──────────────────────────────────────────────── */
:root {
  --od-navy: #0c1a35;
  --od-gold: #c9a84c;
  --od-gold-lt: #e8c96a;
  --od-green: #059669;
  --od-red: #dc2626;
  --od-blue: #2563eb;
  --od-purple: #7c3aed;
}

/* Hero */
.od-hero {
  background: linear-gradient(135deg, #060e1f 0%, #0c1a35 40%, #152a55 70%, #0a1628 100%);
  border-radius: 20px;
  padding: 2.2rem 2.5rem 0;
  position: relative; overflow: hidden;
  box-shadow: 0 20px 60px rgba(6,14,31,.5);
  border: 1px solid rgba(201,168,76,.2);
  margin-bottom: 1.75rem;
}
.od-hero::before {
  content:''; position:absolute; top:-100px; right:-80px;
  width:420px; height:420px; border-radius:50%;
  background: radial-gradient(circle, rgba(201,168,76,.18) 0%, rgba(201,168,76,.04) 50%, transparent 70%);
  animation: heroOrb 7s ease-in-out infinite alternate;
}
.od-hero::after {
  content:''; position:absolute; bottom:-80px; left:25%;
  width:300px; height:300px; border-radius:50%;
  background: radial-gradient(circle, rgba(37,99,235,.12) 0%, transparent 70%);
  animation: heroOrb2 9s ease-in-out infinite alternate;
}
@keyframes heroOrb  { from{transform:scale(1) translate(0,0)} to{transform:scale(1.15) translate(20px,-20px)} }
@keyframes heroOrb2 { from{transform:scale(1)} to{transform:scale(1.25) translate(-15px,25px)} }

.od-hero-top {
  display:flex; align-items:flex-start; justify-content:space-between;
  flex-wrap:wrap; gap:1rem; position:relative; z-index:2;
}
.od-hero-badge {
  background: rgba(201,168,76,.15); border: 1px solid rgba(201,168,76,.35);
  color: var(--od-gold-lt); font-size:.62rem; font-weight:700;
  letter-spacing:.1em; text-transform:uppercase;
  padding:.25rem .75rem; border-radius:20px; display:inline-flex; align-items:center; gap:.4rem;
  margin-bottom:.5rem;
}
.od-hero-name {
  color:#fff; font-size:2.1rem; font-weight:800;
  letter-spacing:-.04em; line-height:1.1;
  text-shadow: 0 2px 24px rgba(0,0,0,.4);
}
.od-hero-biz {
  color: rgba(201,168,76,.8); font-size:.82rem; font-weight:600;
  margin-top:.35rem; display:flex; align-items:center; gap:.4rem;
}
.od-hero-actions { display:flex; gap:.6rem; flex-wrap:wrap; padding-top:.2rem; }
.od-hero-btn {
  background:rgba(255,255,255,.1); color:rgba(255,255,255,.9);
  border:1.5px solid rgba(255,255,255,.2); border-radius:9px;
  font-size:.8rem; font-weight:600; padding:.45rem 1rem;
  text-decoration:none; display:flex; align-items:center; gap:.4rem;
  backdrop-filter:blur(6px); transition:all .18s;
}
.od-hero-btn:hover { background:rgba(201,168,76,.25); border-color:rgba(201,168,76,.5); color:#fff; }
.od-hero-btn-gold {
  background: linear-gradient(135deg, var(--od-gold), var(--od-gold-lt));
  color: #0c1a35; border:none; font-weight:700;
}
.od-hero-btn-gold:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(201,168,76,.4); color:#0c1a35; }

/* Hero stat strip */
.od-hero-stats {
  display:grid; grid-template-columns:repeat(5,1fr);
  border-top:1px solid rgba(255,255,255,.08);
  margin-top:1.8rem; position:relative; z-index:2;
}
.od-hero-stat {
  padding:1.1rem .5rem; text-align:center; position:relative;
  transition:background .18s; cursor:default;
}
.od-hero-stat:not(:last-child)::after {
  content:''; position:absolute; right:0; top:20%; bottom:20%;
  width:1px; background:rgba(255,255,255,.1);
}
.od-hero-stat:hover { background:rgba(255,255,255,.04); border-radius:8px; }
.od-hs-val {
  font-size:1.55rem; font-weight:800; color:#fff; line-height:1;
  letter-spacing:-.03em;
}
.od-hs-val.gold { color: var(--od-gold-lt); }
.od-hs-val.green { color: #6ee7b7; }
.od-hs-val.red { color: #fca5a5; }
.od-hs-lbl {
  font-size:.62rem; font-weight:600; color:rgba(255,255,255,.38);
  text-transform:uppercase; letter-spacing:.07em; margin-top:.3rem;
}
.od-hs-chip {
  display:inline-block; font-size:.58rem; font-weight:700;
  padding:.12rem .4rem; border-radius:20px; margin-top:.25rem;
}
.chip-up   { background:rgba(5,150,105,.25); color:#6ee7b7; }
.chip-down { background:rgba(220,38,38,.25);  color:#fca5a5; }
.chip-neu  { background:rgba(255,255,255,.1);  color:rgba(255,255,255,.5); }

/* KPI Cards */
.od-kpi {
  background:#fff; border-radius:16px;
  padding:1.3rem 1.4rem; position:relative; overflow:hidden;
  box-shadow:0 2px 16px rgba(12,26,53,.08);
  border:1px solid #edf0f8;
  transition: transform .2s, box-shadow .2s;
}
.od-kpi:hover { transform:translateY(-3px); box-shadow:0 10px 28px rgba(12,26,53,.14); }
.od-kpi::before {
  content:''; position:absolute; left:0; top:0; bottom:0;
  width:4px; border-radius:16px 0 0 16px;
}
.od-kpi-navy::before  { background:linear-gradient(180deg,#0c1a35,#1a3060); }
.od-kpi-gold::before  { background:linear-gradient(180deg,#c9a84c,#e8c96a); }
.od-kpi-green::before { background:linear-gradient(180deg,#059669,#34d399); }
.od-kpi-red::before   { background:linear-gradient(180deg,#dc2626,#f87171); }
.od-kpi-blue::before  { background:linear-gradient(180deg,#2563eb,#60a5fa); }
.od-kpi-purple::before{ background:linear-gradient(180deg,#7c3aed,#a78bfa); }
.od-kpi-icon {
  width:48px; height:48px; border-radius:12px;
  display:flex; align-items:center; justify-content:center;
  font-size:1.3rem; margin-bottom:.85rem;
}
.od-kpi-val {
  font-size:1.6rem; font-weight:800; color:#0c1a35;
  letter-spacing:-.04em; line-height:1;
}
.od-kpi-lbl {
  font-size:.68rem; font-weight:700; color:#9ca3af;
  text-transform:uppercase; letter-spacing:.07em; margin-top:.25rem;
}
.od-kpi-branch-list {
  margin-top:.6rem; padding-top:.5rem;
  border-top:1px solid #f1f4fa;
  display:flex; flex-direction:column; gap:.25rem;
}
.od-kpi-branch-row {
  display:flex; justify-content:space-between; align-items:center;
  font-size:.7rem; color:#6b7280;
}
.od-kpi-branch-row span:first-child { font-weight:500; color:#374151; max-width:60%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.od-kpi-branch-row span:last-child { font-weight:600; color:#1e293b; }
.od-hs-branch-list {
  margin-top:.5rem;
  display:flex; flex-direction:column; gap:.2rem;
}
.od-hs-branch-row {
  display:flex; align-items:center; gap:.35rem;
  font-size:.65rem; color:rgba(255,255,255,.65);
}
.od-hs-branch-dot {
  width:6px; height:6px; border-radius:50%;
  background:rgba(201,168,76,.8); flex-shrink:0;
}
.od-hs-branch-name { flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.od-hs-branch-val { font-weight:600; color:rgba(255,255,255,.9); white-space:nowrap; }
.od-kpi-footer {
  margin-top:.8rem; padding-top:.65rem;
  border-top:1px solid #f1f4fa;
  display:flex; align-items:center; justify-content:space-between;
}
.od-kpi-trend { font-size:.72rem; font-weight:600; }
.od-kpi-sub   { font-size:.7rem; color:#9ca3af; }

/* Section header */
.od-sh {
  display:flex; align-items:center; justify-content:space-between;
  margin-bottom:.9rem;
}
.od-sh-title {
  font-size:.72rem; font-weight:800; color:#0c1a35;
  text-transform:uppercase; letter-spacing:.1em;
  display:flex; align-items:center; gap:.5rem;
}
.od-sh-title::before {
  content:''; width:3px; height:14px;
  background:linear-gradient(180deg,#c9a84c,#e8c96a);
  border-radius:2px; display:block;
}
.od-sh-link {
  font-size:.72rem; font-weight:700; color:#9ca3af;
  text-decoration:none; padding:.28rem .65rem;
  border-radius:7px; border:1.5px solid #edf0f8;
  transition:all .15s;
}
.od-sh-link:hover { color:#0c1a35; border-color:#0c1a35; background:#f6f8fd; }

/* Hall performance card */
.od-hall-card {
  background:#fff; border-radius:14px;
  border:1px solid #edf0f8;
  box-shadow:0 2px 12px rgba(12,26,53,.07);
  padding:1.1rem 1.3rem;
  transition: transform .18s, box-shadow .18s;
}
.od-hall-card:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(12,26,53,.12); }
.od-hall-name { font-size:.9rem; font-weight:800; color:#0c1a35; }
.od-hall-branch { font-size:.68rem; color:#9ca3af; font-weight:600; margin-top:.1rem; }
.od-hall-stats {
  display:grid; grid-template-columns:repeat(2,1fr); gap:.5rem;
  margin-top:.9rem;
}
.od-hall-stat { text-align:center; }
.od-hall-stat-val { font-size:1.1rem; font-weight:800; color:#0c1a35; line-height:1; }
.od-hall-stat-lbl { font-size:.6rem; color:#9ca3af; font-weight:600; text-transform:uppercase; letter-spacing:.05em; }
.od-hall-bar-wrap { margin-top:.85rem; }
.od-hall-bar-lbl { display:flex; justify-content:space-between; font-size:.65rem; color:#9ca3af; margin-bottom:.3rem; }
.od-hall-bar { height:6px; background:#f1f4fa; border-radius:3px; overflow:hidden; }
.od-hall-bar-fill { height:100%; border-radius:3px; background:linear-gradient(90deg,#c9a84c,#e8c96a); transition:width .6s ease; }

/* Outstanding table */
.od-table { width:100%; border-collapse:collapse; font-size:.8rem; }
.od-table th {
  font-size:.65rem; font-weight:700; color:#9ca3af;
  text-transform:uppercase; letter-spacing:.07em;
  padding:.6rem .9rem; border-bottom:2px solid #f1f4fa;
  background:#fafbff; text-align:left;
}
.od-table td {
  padding:.7rem .9rem; border-bottom:1px solid #f5f7fc;
  color:#374151; vertical-align:middle;
}
.od-table tr:last-child td { border-bottom:none; }
.od-table tr:hover td { background:#fafbff; }
.od-table .amount { font-weight:700; font-size:.85rem; }
.od-table .red-amt { color:#dc2626; }
.od-table .green-amt { color:#059669; }

/* Status badge */
.od-badge {
  display:inline-block; font-size:.6rem; font-weight:700;
  padding:.2rem .55rem; border-radius:20px; text-transform:uppercase; letter-spacing:.05em;
}
.od-badge-confirmed { background:#dcfce7; color:#166534; }
.od-badge-booked    { background:#dbeafe; color:#1e40af; }
.od-badge-tentative { background:#fef9c3; color:#854d0e; }
.od-badge-cancelled { background:#fee2e2; color:#991b1b; }
.od-badge-completed { background:#f0fdf4; color:#166534; }

/* Payment feed */
.od-pay-item {
  display:flex; align-items:center; gap:.9rem;
  padding:.75rem 1.2rem; border-bottom:1px solid #f5f7fc;
  transition:background .15s;
}
.od-pay-item:hover { background:#fafbff; }
.od-pay-item:last-child { border-bottom:none; }
.od-pay-dot {
  width:36px; height:36px; border-radius:50%; flex-shrink:0;
  background:linear-gradient(135deg,#059669,#34d399);
  display:flex; align-items:center; justify-content:center;
  color:#fff; font-size:.9rem;
}
.od-pay-body { flex:1; min-width:0; }
.od-pay-name { font-size:.82rem; font-weight:700; color:#1f2937; }
.od-pay-meta { font-size:.68rem; color:#9ca3af; margin-top:.1rem; }
.od-pay-amt  { font-size:.95rem; font-weight:800; color:#059669; white-space:nowrap; }

/* Chart card */
.od-chart-card {
  background:#fff; border-radius:16px;
  box-shadow:0 2px 16px rgba(12,26,53,.08);
  border:1px solid #edf0f8; overflow:hidden;
}
.od-chart-header {
  padding:1rem 1.4rem; border-bottom:1px solid #f1f4fa;
  display:flex; align-items:center; justify-content:space-between;
}
.od-chart-title { font-size:.88rem; font-weight:800; color:#0c1a35; margin:0; }
.od-chart-sub   { font-size:.68rem; color:#9ca3af; margin-top:.1rem; }
.od-chart-body  { padding:1.1rem 1rem 1rem; }

/* Quick actions */
.od-qa-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:.7rem; }
.od-qa {
  display:flex; flex-direction:column; align-items:center; gap:.45rem;
  padding:1rem .5rem; border-radius:14px;
  border:2px solid #edf0f8; background:#fff;
  text-decoration:none; color:#374151;
  font-size:.7rem; font-weight:700; text-align:center;
  transition:all .2s; box-shadow:0 1px 4px rgba(12,26,53,.05);
  position:relative; overflow:hidden;
}
.od-qa::after {
  content:''; position:absolute; inset:0;
  background:linear-gradient(135deg,#0c1a35,#1a3060);
  opacity:0; transition:opacity .2s; z-index:0;
}
.od-qa:hover::after { opacity:1; }
.od-qa:hover { transform:translateY(-3px); box-shadow:0 10px 28px rgba(12,26,53,.2); color:#fff; }
.od-qa-icon {
  width:44px; height:44px; border-radius:12px;
  display:flex; align-items:center; justify-content:center;
  font-size:1.25rem; position:relative; z-index:2;
}
.od-qa span { position:relative; z-index:2; }
.od-qa:hover .od-qa-icon { background:rgba(255,255,255,.15) !important; color:#fff !important; }

@media(max-width:992px) {
  .od-hero-stats { grid-template-columns:repeat(3,1fr); }
  .od-qa-grid { grid-template-columns:repeat(2,1fr); }
}
@media(max-width:768px) {
  .od-hero-stats { grid-template-columns:repeat(2,1fr); }
  .od-hero-name { font-size:1.5rem; }
}
</style>

<!-- ═══════ HERO ═══════ -->
<div class="od-hero mb-0">
  <div class="od-hero-top">
    <div>
      <div class="od-hero-badge">
        <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
        Business Owner
      </div>
      <div class="od-hero-name"><?= Helper::sanitize($cu['name']) ?></div>
      <div class="od-hero-biz">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M3 7l9-4 9 4M4 21V7.5L12 4l8 3.5V21"/></svg>
        <?= Helper::sanitize($biz['business_name'] ?? 'Your Business') ?>
        &nbsp;·&nbsp; <?= count($bizBranches) ?> Branch<?= count($bizBranches) !== 1 ? 'es' : '' ?>
      </div>
    </div>
    <div class="od-hero-actions">
      <a href="<?= BASE_URL ?>/modules/bookings/create.php" class="od-hero-btn od-hero-btn-gold">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        New Booking
      </a>
      <a href="<?= BASE_URL ?>/modules/reports/index.php" class="od-hero-btn">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>
        Reports
      </a>
      <a href="<?= BASE_URL ?>/modules/invoices/index.php" class="od-hero-btn">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        Invoices
      </a>
    </div>
  </div>

  <!-- Hero stat strip -->
  <div class="od-hero-stats">
    <div class="od-hero-stat">
      <div class="od-hs-val gold">Rs. <?= number_format($totalRevenue/1000,0) ?>K</div>
      <div class="od-hs-lbl">Total Revenue</div>
      <?php if (count($branchKpi) > 1): ?>
      <div class="od-hs-branch-list">
        <?php foreach ($branchKpi as $bkpi): ?>
        <div class="od-hs-branch-row">
          <span class="od-hs-branch-dot"></span>
          <span class="od-hs-branch-name"><?= htmlspecialchars($bkpi['name']) ?></span>
          <span class="od-hs-branch-val">Rs. <?= number_format($bkpi['revenue']/1000,0) ?>K</span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <div class="od-hero-stat">
      <div class="od-hs-val"><?= $totalBookings ?></div>
      <div class="od-hs-lbl">Total Bookings</div>
      <?php if (count($branchKpi) > 1): ?>
      <div class="od-hs-branch-list">
        <?php foreach ($branchKpi as $bkpi): ?>
        <div class="od-hs-branch-row">
          <span class="od-hs-branch-dot"></span>
          <span class="od-hs-branch-name"><?= htmlspecialchars($bkpi['name']) ?></span>
          <span class="od-hs-branch-val"><?= $bkpi['bookings'] ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <div class="od-hero-stat">
      <div class="od-hs-val"><?= $upcomingEvents ?></div>
      <div class="od-hs-lbl">Upcoming Events</div>
      <?php if (count($branchKpi) > 1): ?>
      <div class="od-hs-branch-list">
        <?php foreach ($branchKpi as $bkpi): ?>
        <div class="od-hs-branch-row">
          <span class="od-hs-branch-dot"></span>
          <span class="od-hs-branch-name"><?= htmlspecialchars($bkpi['name']) ?></span>
          <span class="od-hs-branch-val"><?= $bkpi['upcoming'] ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <div class="od-hero-stat">
      <div class="od-hs-val red">Rs. <?= number_format($totalOutstanding/1000,0) ?>K</div>
      <div class="od-hs-lbl">Outstanding</div>
      <?php if (count($branchKpi) > 1): ?>
      <div class="od-hs-branch-list">
        <?php foreach ($branchKpi as $bkpi): ?>
        <div class="od-hs-branch-row">
          <span class="od-hs-branch-dot" style="background:#dc2626;"></span>
          <span class="od-hs-branch-name"><?= htmlspecialchars($bkpi['name']) ?></span>
          <span class="od-hs-branch-val" style="color:#dc2626;">Rs. <?= number_format($bkpi['outstanding']/1000,0) ?>K</span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <div class="od-hero-stat">
      <div class="od-hs-val"><?= $totalCustomers ?></div>
      <div class="od-hs-lbl">Customers</div>
      <?php if (count($branchKpi) > 1): ?>
      <div class="od-hs-branch-list">
        <?php foreach ($branchKpi as $bkpi): ?>
        <div class="od-hs-branch-row">
          <span class="od-hs-branch-dot"></span>
          <span class="od-hs-branch-name"><?= htmlspecialchars($bkpi['name']) ?></span>
          <span class="od-hs-branch-val"><?= $bkpi['customers'] ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ═══════ KPI CARDS ═══════ -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-4 col-xl-2">
    <div class="od-kpi od-kpi-gold">
      <div class="od-kpi-icon" style="background:#fef9e7;">💰</div>
      <div class="od-kpi-val">Rs. <?= number_format($thisMonthRevenue) ?></div>
      <div class="od-kpi-lbl">This Month Revenue</div>
      <div class="od-kpi-footer">
        <span class="od-kpi-trend <?= $revenueGrowth >= 0 ? 'text-success' : 'text-danger' ?>">
          <?= $revenueGrowth >= 0 ? '▲' : '▼' ?> <?= abs($revenueGrowth) ?>% vs last month
        </span>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="od-kpi od-kpi-navy">
      <div class="od-kpi-icon" style="background:#eef2ff;">📅</div>
      <div class="od-kpi-val"><?= $activeBookings ?></div>
      <div class="od-kpi-lbl">Active Bookings</div>
      <?php if (count($branchKpi) > 1): ?>
      <div class="od-kpi-branch-list">
        <?php foreach ($branchKpi as $bkpi): ?>
        <div class="od-kpi-branch-row"><span><?= htmlspecialchars($bkpi['name']) ?></span><span><?= $bkpi['active'] ?></span></div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="od-kpi-footer"><span class="od-kpi-sub"><?= $todayEvents ?> event<?= $todayEvents !== 1 ? 's' : '' ?> today</span></div>
      <?php endif; ?>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="od-kpi od-kpi-blue">
      <div class="od-kpi-icon" style="background:#eff6ff;">🔮</div>
      <div class="od-kpi-val"><?= $upcomingEvents ?></div>
      <div class="od-kpi-lbl">Upcoming (30 days)</div>
      <?php if (count($branchKpi) > 1): ?>
      <div class="od-kpi-branch-list">
        <?php foreach ($branchKpi as $bkpi): ?>
        <div class="od-kpi-branch-row"><span><?= htmlspecialchars($bkpi['name']) ?></span><span><?= $bkpi['upcoming'] ?></span></div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="od-kpi-footer"><a href="<?= BASE_URL ?>/modules/bookings/index.php" class="od-kpi-sub text-decoration-none" style="color:#2563eb;">View all →</a></div>
      <?php endif; ?>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="od-kpi od-kpi-red">
      <div class="od-kpi-icon" style="background:#fff1f2;">⚠️</div>
      <div class="od-kpi-val">Rs. <?= number_format($totalOutstanding) ?></div>
      <div class="od-kpi-lbl">Total Outstanding</div>
      <?php if (count($branchKpi) > 1): ?>
      <div class="od-kpi-branch-list">
        <?php foreach ($branchKpi as $bkpi): ?>
        <div class="od-kpi-branch-row"><span><?= htmlspecialchars($bkpi['name']) ?></span><span style="color:#dc2626;">Rs. <?= number_format($bkpi['outstanding']/1000,0) ?>K</span></div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="od-kpi-footer"><span class="od-kpi-sub"><?= count($outstandingList) ?> booking<?= count($outstandingList) !== 1 ? 's' : '' ?> pending</span></div>
      <?php endif; ?>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="od-kpi od-kpi-green">
      <div class="od-kpi-icon" style="background:#f0fdf4;">👥</div>
      <div class="od-kpi-val"><?= $totalCustomers ?></div>
      <div class="od-kpi-lbl">Total Customers</div>
      <?php if (count($branchKpi) > 1): ?>
      <div class="od-kpi-branch-list">
        <?php foreach ($branchKpi as $bkpi): ?>
        <div class="od-kpi-branch-row"><span><?= htmlspecialchars($bkpi['name']) ?></span><span><?= $bkpi['customers'] ?></span></div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="od-kpi-footer"><a href="<?= BASE_URL ?>/modules/customers/index.php" class="od-kpi-sub text-decoration-none" style="color:#059669;">View all →</a></div>
      <?php endif; ?>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="od-kpi od-kpi-purple">
      <div class="od-kpi-icon" style="background:#f5f3ff;">🏛️</div>
      <div class="od-kpi-val"><?= count($hallPerformance) ?></div>
      <div class="od-kpi-lbl">Active Halls</div>
      <div class="od-kpi-footer">
        <span class="od-kpi-sub"><?= count($bizBranches) ?> branch<?= count($bizBranches) !== 1 ? 'es' : '' ?></span>
      </div>
    </div>
  </div>
</div>

<!-- ═══════ CHARTS ROW ═══════ -->
<div class="row g-3 mb-4">
  <!-- Revenue Chart -->
  <div class="col-lg-8">
    <div class="od-chart-card">
      <div class="od-chart-header">
        <div>
          <div class="od-chart-title">Revenue Trend</div>
          <div class="od-chart-sub">Branch-wise monthly collections — last 6 months</div>
        </div>
        <!-- Branch legend -->
        <div class="d-flex gap-3 flex-wrap" id="branchLegend"></div>
      </div>
      <div class="od-chart-body">
        <canvas id="revenueChart" height="90"></canvas>
      </div>
    </div>
  </div>

  <!-- Booking Status Donut -->
  <div class="col-lg-4">
    <div class="od-chart-card h-100">
      <div class="od-chart-header">
        <div>
          <div class="od-chart-title">Booking Status</div>
          <div class="od-chart-sub">All time breakdown</div>
        </div>
      </div>
      <div class="od-chart-body d-flex flex-column align-items-center">
        <canvas id="statusChart" height="160" style="max-width:200px;"></canvas>
        <div class="mt-3 w-100">
          <?php
          $statusColors = ['confirmed'=>'#059669','booked'=>'#2563eb','tentative'=>'#d97706','cancelled'=>'#dc2626','completed'=>'#6b7280'];
          foreach ($statusBreakdown as $s):
            $col = $statusColors[$s['status']] ?? '#9ca3af';
          ?>
          <div class="d-flex align-items-center justify-content-between mb-1" style="font-size:.75rem;">
            <div class="d-flex align-items-center gap-2">
              <div style="width:10px;height:10px;border-radius:50%;background:<?= $col ?>;flex-shrink:0;"></div>
              <span style="font-weight:600;color:#374151;"><?= ucfirst($s['status']) ?></span>
            </div>
            <span style="font-weight:700;color:#0c1a35;"><?= $s['cnt'] ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ═══════ QUICK ACTIONS ═══════ -->
<div class="mb-4">
  <div class="od-sh"><div class="od-sh-title">Quick Actions</div></div>
  <div class="od-qa-grid">
    <a href="<?= BASE_URL ?>/modules/bookings/create.php" class="od-qa">
      <div class="od-qa-icon" style="background:#eef2ff;color:#2563eb;">📅</div>
      <span>New Booking</span>
    </a>
    <a href="<?= BASE_URL ?>/modules/customers/create.php" class="od-qa">
      <div class="od-qa-icon" style="background:#f0fdf4;color:#059669;">👤</div>
      <span>Add Customer</span>
    </a>
    <a href="<?= BASE_URL ?>/modules/invoices/create.php" class="od-qa">
      <div class="od-qa-icon" style="background:#fef9e7;color:#c9a84c;">🧾</div>
      <span>New Invoice</span>
    </a>
    <a href="<?= BASE_URL ?>/modules/payments/create.php" class="od-qa">
      <div class="od-qa-icon" style="background:#fff1f2;color:#dc2626;">💳</div>
      <span>Record Payment</span>
    </a>
  </div>
</div>

<!-- ═══════ HALL PERFORMANCE ═══════ -->
<div class="mb-4">
  <div class="od-sh">
    <div class="od-sh-title">Hall Performance</div>
    <a href="<?= BASE_URL ?>/modules/settings/index.php?tab=halls" class="od-sh-link">Manage Halls</a>
  </div>
  <div class="row g-3">
    <?php foreach ($hallPerformance as $hall):
      $paidPct = $hall['total_revenue'] > 0 ? min(100, round($hall['total_paid'] / $hall['total_revenue'] * 100)) : 0;
    ?>
    <div class="col-sm-6 col-xl-3">
      <div class="od-hall-card">
        <div class="d-flex align-items-start justify-content-between">
          <div>
            <div class="od-hall-name"><?= Helper::sanitize($hall['hall_name']) ?></div>
            <div class="od-hall-branch">
              <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:inline;"><path d="M3 21h18M3 7l9-4 9 4M4 21V7.5L12 4l8 3.5V21"/></svg>
              <?= Helper::sanitize($hall['branch_name']) ?>
            </div>
          </div>
          <span class="od-badge od-badge-confirmed"><?= $hall['upcoming'] ?> upcoming</span>
        </div>
        <div class="od-hall-stats">
          <div class="od-hall-stat">
            <div class="od-hall-stat-val"><?= $hall['total_bookings'] ?></div>
            <div class="od-hall-stat-lbl">Bookings</div>
          </div>
          <div class="od-hall-stat">
            <div class="od-hall-stat-val" style="color:#0c1a35;font-size:.95rem;">Rs. <?= number_format($hall['total_revenue']/1000,0) ?>K</div>
            <div class="od-hall-stat-lbl">Revenue</div>
          </div>
          <div class="od-hall-stat">
            <div class="od-hall-stat-val" style="color:#059669;font-size:.95rem;">Rs. <?= number_format($hall['total_paid']/1000,0) ?>K</div>
            <div class="od-hall-stat-lbl">Collected</div>
          </div>
          <div class="od-hall-stat">
            <div class="od-hall-stat-val" style="color:#dc2626;font-size:.95rem;">Rs. <?= number_format($hall['outstanding']/1000,0) ?>K</div>
            <div class="od-hall-stat-lbl">Outstanding</div>
          </div>
        </div>
        <div class="od-hall-bar-wrap">
          <div class="od-hall-bar-lbl">
            <span>Collection Rate</span>
            <span><?= $paidPct ?>%</span>
          </div>
          <div class="od-hall-bar">
            <div class="od-hall-bar-fill" style="width:<?= $paidPct ?>%;"></div>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($hallPerformance)): ?>
    <div class="col-12"><div class="text-center text-muted py-4" style="font-size:.85rem;">No halls found. <a href="<?= BASE_URL ?>/modules/halls/create.php">Add a hall</a></div></div>
    <?php endif; ?>
  </div>
</div>

<!-- ═══════ OUTSTANDING + UPCOMING ═══════ -->
<div class="row g-3 mb-4">
  <!-- Outstanding Balances -->
  <div class="col-lg-7">
    <div class="od-chart-card">
      <div class="od-chart-header">
        <div>
          <div class="od-chart-title">⚠️ Outstanding Balances</div>
          <div class="od-chart-sub">Active bookings with pending payments</div>
        </div>
        <a href="<?= BASE_URL ?>/modules/bookings/index.php" class="od-sh-link">View All</a>
      </div>
      <?php if ($outstandingList): ?>
      <div style="overflow-x:auto;">
        <table class="od-table">
          <thead>
            <tr>
              <th>Booking</th>
              <th>Customer</th>
              <th>Hall</th>
              <th>Event Date</th>
              <th>Outstanding</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($outstandingList as $ob): ?>
            <tr>
              <td><a href="<?= BASE_URL ?>/modules/bookings/view.php?id=<?= $ob['booking_ref'] ?>" style="font-weight:700;color:#0c1a35;text-decoration:none;"><?= Helper::sanitize($ob['booking_ref']) ?></a></td>
              <td>
                <div style="font-weight:600;color:#1f2937;"><?= Helper::sanitize($ob['customer_name']) ?></div>
                <div style="font-size:.65rem;color:#9ca3af;"><?= Helper::sanitize($ob['customer_phone']) ?></div>
              </td>
              <td>
                <div style="font-weight:600;"><?= Helper::sanitize($ob['hall_name']) ?></div>
                <div style="font-size:.65rem;color:#9ca3af;"><?= Helper::sanitize($ob['branch_name']) ?></div>
              </td>
              <td><?= date('d M Y', strtotime($ob['event_date'])) ?></td>
              <td class="amount red-amt">Rs. <?= number_format($ob['balance_amount']) ?></td>
              <td><span class="od-badge od-badge-<?= $ob['status'] ?>"><?= ucfirst($ob['status']) ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="text-center text-muted py-4" style="font-size:.85rem;">
        <div style="font-size:2rem;margin-bottom:.5rem;">✅</div>
        No outstanding balances
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Recent Payments -->
  <div class="col-lg-5">
    <div class="od-chart-card h-100">
      <div class="od-chart-header">
        <div>
          <div class="od-chart-title">💳 Recent Payments</div>
          <div class="od-chart-sub">Latest collections</div>
        </div>
        <a href="<?= BASE_URL ?>/modules/payments/index.php" class="od-sh-link">View All</a>
      </div>
      <?php if ($recentPayments): ?>
      <?php foreach ($recentPayments as $pay): ?>
      <div class="od-pay-item">
        <div class="od-pay-dot">💰</div>
        <div class="od-pay-body">
          <div class="od-pay-name"><?= Helper::sanitize($pay['customer_name'] ?? 'N/A') ?></div>
          <div class="od-pay-meta">
            <?= Helper::sanitize($pay['booking_ref'] ?? '') ?>
            · <?= date('d M', strtotime($pay['payment_date'])) ?>
            · <?= ucfirst($pay['payment_method'] ?? '') ?>
          </div>
        </div>
        <div class="od-pay-amt">Rs. <?= number_format($pay['amount']) ?></div>
      </div>
      <?php endforeach; ?>
      <?php else: ?>
      <div class="text-center text-muted py-4" style="font-size:.85rem;">No payments recorded yet.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ═══════ UPCOMING EVENTS ═══════ -->
<div class="mb-4">
  <div class="od-sh">
    <div class="od-sh-title">Upcoming Events (Next 30 Days)</div>
    <a href="<?= BASE_URL ?>/modules/bookings/index.php" class="od-sh-link">View All</a>
  </div>
  <div class="od-chart-card">
    <?php if ($upcomingList): ?>
    <div style="overflow-x:auto;">
      <table class="od-table">
        <thead>
          <tr>
            <th>Booking Ref</th>
            <th>Customer</th>
            <th>Hall / Branch</th>
            <th>Event Date</th>
            <th>Type</th>
            <th>Total</th>
            <th>Balance</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($upcomingList as $ev): ?>
          <tr>
            <td><a href="<?= BASE_URL ?>/modules/bookings/view.php?ref=<?= urlencode($ev['booking_ref']) ?>" style="font-weight:700;color:#0c1a35;text-decoration:none;"><?= Helper::sanitize($ev['booking_ref']) ?></a></td>
            <td style="font-weight:600;"><?= Helper::sanitize($ev['customer_name']) ?></td>
            <td>
              <div style="font-weight:600;"><?= Helper::sanitize($ev['hall_name']) ?></div>
              <div style="font-size:.65rem;color:#9ca3af;"><?= Helper::sanitize($ev['branch_name']) ?></div>
            </td>
            <td>
              <div style="font-weight:700;"><?= date('d M Y', strtotime($ev['event_date'])) ?></div>
              <?php if ($ev['event_time']): ?><div style="font-size:.65rem;color:#9ca3af;"><?= date('g:i A', strtotime($ev['event_time'])) ?></div><?php endif; ?>
            </td>
            <td><?= Helper::sanitize(ucfirst($ev['event_type'] ?? '')) ?></td>
            <td class="amount">Rs. <?= number_format($ev['final_amount']) ?></td>
            <td class="amount <?= $ev['balance_amount'] > 0 ? 'red-amt' : 'green-amt' ?>">
              <?= $ev['balance_amount'] > 0 ? 'Rs. '.number_format($ev['balance_amount']) : '✓ Paid' ?>
            </td>
            <td><span class="od-badge od-badge-<?= $ev['status'] ?>"><?= ucfirst($ev['status']) ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="text-center text-muted py-5" style="font-size:.85rem;">
      <div style="font-size:2.5rem;margin-bottom:.5rem;">📅</div>
      No upcoming events in the next 30 days
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ═══════ BRANCH SUMMARY ═══════ -->
<?php if (count($branchPerformance) > 1): ?>
<div class="mb-4">
  <div class="od-sh">
    <div class="od-sh-title">Branch Summary</div>
    <a href="<?= BASE_URL ?>/modules/branches/index.php" class="od-sh-link">Manage Branches</a>
  </div>
  <div class="row g-3">
    <?php foreach ($branchPerformance as $br):
      $brPct = $br['total_revenue'] > 0 ? min(100, round($br['total_paid'] / $br['total_revenue'] * 100)) : 0;
    ?>
    <div class="col-md-6 col-xl-4">
      <div class="od-hall-card">
        <div class="d-flex align-items-center gap-2 mb-2">
          <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#0c1a35,#1a3060);display:flex;align-items:center;justify-content:center;color:#c9a84c;font-size:1rem;flex-shrink:0;">🏢</div>
          <div>
            <div class="od-hall-name"><?= Helper::sanitize($br['branch_name']) ?></div>
            <div class="od-hall-branch"><?= $br['total_bookings'] ?> total bookings</div>
          </div>
        </div>
        <div class="od-hall-stats">
          <div class="od-hall-stat">
            <div class="od-hall-stat-val" style="color:#0c1a35;">Rs. <?= number_format($br['total_revenue']/1000,0) ?>K</div>
            <div class="od-hall-stat-lbl">Revenue</div>
          </div>
          <div class="od-hall-stat">
            <div class="od-hall-stat-val" style="color:#059669;">Rs. <?= number_format($br['total_paid']/1000,0) ?>K</div>
            <div class="od-hall-stat-lbl">Collected</div>
          </div>
          <div class="od-hall-stat">
            <div class="od-hall-stat-val" style="color:#dc2626;">Rs. <?= number_format($br['outstanding']/1000,0) ?>K</div>
            <div class="od-hall-stat-lbl">Outstanding</div>
          </div>
          <div class="od-hall-stat">
            <div class="od-hall-stat-val"><?= $brPct ?>%</div>
            <div class="od-hall-stat-lbl">Collected</div>
          </div>
        </div>
        <div class="od-hall-bar-wrap">
          <div class="od-hall-bar"><div class="od-hall-bar-fill" style="width:<?= $brPct ?>%;"></div></div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// ── Branch-wise Revenue Chart ─────────────────────────────────────────────
const revLabels = <?= json_encode($chartLabels) ?>;
const branchRevData = <?= json_encode(array_values($branchMonthlyRev)) ?>;
const branchColors = ['#c9a84c','#2563eb','#059669','#d97706','#7c3aed','#dc2626','#0891b2'];

// Build datasets: one per branch, filling 0 for missing months
const revDatasets = branchRevData.map((br, i) => ({
  label: br.name,
  data: revLabels.map((lbl, idx) => {
    // match by index against the sorted ym keys
    const ymKeys = Object.keys(br.data);
    return ymKeys[idx] !== undefined ? parseFloat(br.data[ymKeys[idx]]) : 0;
  }),
  backgroundColor: branchColors[i % branchColors.length] + 'bb',
  borderColor: branchColors[i % branchColors.length],
  borderWidth: 2,
  borderRadius: 6,
  borderSkipped: false,
}));

new Chart(document.getElementById('revenueChart'), {
  type: 'bar',
  data: { labels: revLabels, datasets: revDatasets },
  options: {
    responsive: true,
    plugins: {
      legend: { display: false },
      tooltip: {
        callbacks: {
          label: ctx => ctx.dataset.label + ': Rs. ' + ctx.parsed.y.toLocaleString()
        }
      }
    },
    scales: {
      y: {
        beginAtZero: true,
        grid: { color: 'rgba(0,0,0,.05)' },
        ticks: {
          callback: v => 'Rs. ' + (v >= 1000 ? (v/1000).toFixed(0)+'K' : v),
          font: { size: 11 }
        }
      },
      x: { grid: { display: false }, ticks: { font: { size: 11 } } }
    }
  }
});

// Build legend
const legendEl = document.getElementById('branchLegend');
branchRevData.forEach((br, i) => {
  const dot = document.createElement('span');
  dot.style.cssText = 'display:inline-flex;align-items:center;gap:5px;font-size:.75rem;color:#374151;font-weight:500;';
  dot.innerHTML = '<span style="width:10px;height:10px;border-radius:50%;background:' + branchColors[i % branchColors.length] + ';display:inline-block;"></span>' + br.name;
  legendEl.appendChild(dot);
});

// Status Donut
const statusLabels = <?= json_encode(array_column($statusBreakdown, 'status')) ?>;
const statusData   = <?= json_encode(array_map(fn($s) => (int)$s['cnt'], $statusBreakdown)) ?>;
const statusColors = { confirmed:'#059669', booked:'#2563eb', tentative:'#d97706', cancelled:'#dc2626', completed:'#6b7280' };
const sCols = statusLabels.map(l => statusColors[l] || '#9ca3af');

new Chart(document.getElementById('statusChart'), {
  type: 'doughnut',
  data: {
    labels: statusLabels.map(l => l.charAt(0).toUpperCase() + l.slice(1)),
    datasets: [{ data: statusData, backgroundColor: sCols, borderWidth: 2, borderColor: '#fff' }]
  },
  options: {
    responsive: true,
    cutout: '68%',
    plugins: {
      legend: { display: false },
      tooltip: { callbacks: { label: ctx => ctx.label + ': ' + ctx.parsed } }
    }
  }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
