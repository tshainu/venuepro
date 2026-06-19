<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
if (!Auth::hasRole(['super_admin','admin','hall_manager','accountant'])) { Helper::flash('error','Access denied.'); Helper::redirect(BASE_URL.'/index.php'); }
$db = Database::getInstance();
$cu = Auth::currentUser();

// Date range filter
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to   = $_GET['date_to']   ?? date('Y-m-d');
$branch_id = $cu['branch_id'] ?? (int)($_GET['branch_id'] ?? 0);
$active_tab = $_GET['tab'] ?? 'revenue';

$b_cond_bk  = $branch_id ? "AND b.branch_id=$branch_id"  : "";
$b_cond_pay = $branch_id ? "AND p.branch_id=$branch_id"  : "";
$b_cond_inv = $branch_id ? "AND i.branch_id=$branch_id"  : "";

// ── Core KPIs ──────────────────────────────────────────────
$total_bookings   = $db->fetchOne("SELECT COUNT(*) as cnt FROM bookings b WHERE b.event_date BETWEEN ? AND ? $b_cond_bk", [$date_from,$date_to])['cnt'];
$confirmed_bk     = $db->fetchOne("SELECT COUNT(*) as cnt FROM bookings b WHERE b.status='confirmed' AND b.event_date BETWEEN ? AND ? $b_cond_bk", [$date_from,$date_to])['cnt'];
$total_revenue    = $db->fetchOne("SELECT SUM(p.amount) as total FROM payments p WHERE p.payment_date BETWEEN ? AND ? $b_cond_pay", [$date_from,$date_to])['total'] ?? 0;
$outstanding      = $db->fetchOne("SELECT SUM(b.balance_amount) as total FROM bookings b WHERE b.status IN ('confirmed','tentative') AND b.balance_amount>0 $b_cond_bk")['total'] ?? 0;
$total_invoiced   = $db->fetchOne("SELECT SUM(i.total) as total FROM invoices i WHERE i.invoice_date BETWEEN ? AND ? $b_cond_inv AND i.status NOT IN ('cancelled')", [$date_from,$date_to])['total'] ?? 0;
$total_customers  = $db->fetchOne("SELECT COUNT(DISTINCT b.customer_id) as cnt FROM bookings b WHERE b.event_date BETWEEN ? AND ? $b_cond_bk", [$date_from,$date_to])['cnt'];

// ── Revenue tab ─────────────────────────────────────────────
$monthly_revenue = $db->fetchAll(
    "SELECT DATE_FORMAT(p.payment_date,'%Y-%m') as month, SUM(p.amount) as total
     FROM payments p WHERE p.payment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) $b_cond_pay
     GROUP BY month ORDER BY month ASC"
);
$method_revenue = $db->fetchAll(
    "SELECT p.payment_method, COUNT(*) as cnt, SUM(p.amount) as total
     FROM payments p WHERE p.payment_date BETWEEN ? AND ? $b_cond_pay
     GROUP BY p.payment_method ORDER BY total DESC",
    [$date_from,$date_to]
);
$recent_payments = $db->fetchAll(
    "SELECT p.*, c.name as customer_name, b.booking_ref
     FROM payments p LEFT JOIN customers c ON p.customer_id=c.id LEFT JOIN bookings b ON p.booking_id=b.id
     WHERE p.payment_date BETWEEN ? AND ? $b_cond_pay ORDER BY p.payment_date DESC LIMIT 20",
    [$date_from,$date_to]
);

// ── Bookings tab ────────────────────────────────────────────
$status_counts = $db->fetchAll(
    "SELECT b.status, COUNT(*) as cnt FROM bookings b WHERE b.event_date BETWEEN ? AND ? $b_cond_bk GROUP BY b.status",
    [$date_from,$date_to]
);
$event_type_counts = $db->fetchAll(
    "SELECT b.event_type, COUNT(*) as cnt FROM bookings b WHERE b.event_date BETWEEN ? AND ? AND b.event_type IS NOT NULL AND b.event_type!='' $b_cond_bk GROUP BY b.event_type ORDER BY cnt DESC LIMIT 10",
    [$date_from,$date_to]
);
$monthly_bookings = $db->fetchAll(
    "SELECT DATE_FORMAT(b.event_date,'%Y-%m') as month, COUNT(*) as cnt
     FROM bookings b WHERE b.event_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) $b_cond_bk
     GROUP BY month ORDER BY month ASC"
);

// ── Customers tab ───────────────────────────────────────────
$top_customers = $db->fetchAll(
    "SELECT c.name, c.mobile, COUNT(b.id) as bookings, SUM(b.final_amount) as total, MAX(b.event_date) as last_event
     FROM bookings b LEFT JOIN customers c ON b.customer_id=c.id
     WHERE b.event_date BETWEEN ? AND ? $b_cond_bk
     GROUP BY b.customer_id ORDER BY total DESC LIMIT 15",
    [$date_from,$date_to]
);
$new_customers = $db->fetchOne(
    "SELECT COUNT(*) as cnt FROM customers c WHERE c.created_at BETWEEN ? AND ?",
    [$date_from.' 00:00:00', $date_to.' 23:59:59']
)['cnt'];

// ── Payments tab ────────────────────────────────────────────
$payment_daily = $db->fetchAll(
    "SELECT p.payment_date, SUM(p.amount) as total, COUNT(*) as cnt
     FROM payments p WHERE p.payment_date BETWEEN ? AND ? $b_cond_pay
     GROUP BY p.payment_date ORDER BY p.payment_date ASC",
    [$date_from,$date_to]
);

$branches = $db->fetchAll("SELECT id,name FROM branches WHERE is_active=1");

$pageTitle = 'Reports';
$breadcrumbs = [['label'=>'Reports']];
require_once ROOT_PATH . '/includes/header.php';
?>
<style>
/* ── vp-kpi ──────────────────────────────────────────── */
.vp-kpi{background:#fff;border-radius:16px;padding:1.4rem 1.5rem;position:relative;overflow:hidden;box-shadow:0 2px 16px rgba(12,26,53,.09);border:1px solid #edf0f8;transition:transform .2s,box-shadow .2s;display:block;color:inherit;text-decoration:none;}
.vp-kpi:hover{transform:translateY(-4px);box-shadow:0 12px 32px rgba(12,26,53,.15);}
.vp-kpi-icon{width:50px;height:50px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.35rem;margin-bottom:1rem;}
.vp-kpi-val{font-size:1.7rem;font-weight:800;color:#0c1a35;letter-spacing:-.04em;line-height:1;}
.vp-kpi-lbl{font-size:.7rem;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.07em;margin-top:.3rem;}
.vp-kpi-footer{margin-top:.9rem;padding-top:.75rem;border-top:1px solid #f1f4fa;display:flex;align-items:center;justify-content:space-between;}
.vp-kpi-trend{font-size:.74rem;font-weight:600;}
.vp-kpi-link{font-size:.72rem;font-weight:700;color:#9ca3af;}
.vp-kpi::before{content:'';position:absolute;left:0;top:0;bottom:0;width:4px;border-radius:16px 0 0 16px;}
.vp-kpi-navy::before{background:linear-gradient(180deg,#0c1a35,#1a3060);}
.vp-kpi-gold::before{background:linear-gradient(180deg,#c9a84c,#e8c96a);}
.vp-kpi-green::before{background:linear-gradient(180deg,#059669,#34d399);}
.vp-kpi-red::before{background:linear-gradient(180deg,#dc2626,#f87171);}
.vp-kpi-navy .vp-kpi-icon{background:#eef1f8;color:#0c1a35;}
.vp-kpi-gold .vp-kpi-icon{background:#fdf5e0;color:#92640c;}
.vp-kpi-green .vp-kpi-icon{background:#ecfdf5;color:#059669;}
.vp-kpi-red .vp-kpi-icon{background:#fef2f2;color:#dc2626;}
/* ── Tab nav ─────────────────────────────────────────── */
.vp-tab-nav{display:flex;gap:.25rem;background:#f1f5f9;border-radius:14px;padding:.35rem;margin-bottom:1.5rem;width:fit-content;}
.vp-tab-btn{padding:.5rem 1.2rem;border:none;background:none;border-radius:10px;font-size:.82rem;font-weight:700;color:#6b7280;cursor:pointer;transition:all .18s;}
.vp-tab-btn.active{background:#fff;color:#0c1a35;box-shadow:0 2px 8px rgba(12,26,53,.1);}
.vp-tab-pane{display:none;}.vp-tab-pane.active{display:block;}
/* ── Export bar ─────────────────────────────────────── */
.vp-export-bar{display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;margin-bottom:1.25rem;}
.vp-export-btn{display:inline-flex;align-items:center;gap:.4rem;padding:.42rem 1rem;border-radius:10px;font-size:.78rem;font-weight:700;border:1.5px solid #e2e8f0;background:#fff;color:#374151;cursor:pointer;text-decoration:none;transition:all .15s;}
.vp-export-btn:hover{border-color:#c9a84c;color:#92640c;background:#fdf5e0;}
.vp-export-btn.print-btn:hover{border-color:#0c1a35;color:#0c1a35;background:#eef1f8;}
/* print */
@media print{
  .vp-page-header,.vp-tab-nav,.vp-export-bar,.vp-filter-bar,
  nav,.navbar,#sidebar,.sidebar,.vp-modal-overlay,
  .d-print-none,.btn,footer { display:none!important; }
  .vp-tab-pane { display:block!important; page-break-before:always; }
  .vp-tab-pane:first-of-type { page-break-before:auto; }
  .card { break-inside:avoid; box-shadow:none!important; border:1px solid #ddd!important; margin-bottom:1rem!important; }
  #revenue-chart,#method-chart,#bookings-chart,#status-chart,#daily-chart { display:none!important; }
  body { background:#fff!important; }
  .vp-kpi { box-shadow:none!important; border:1px solid #ddd!important; break-inside:avoid; }
  a { color:#000!important; text-decoration:none!important; }
  @page { margin:1.5cm; }
}
</style>

<div class="vp-page-header d-print-none">
  <div>
    <h1 class="vp-page-title">📊 <?= Lang::t('reports') ?></h1>
    <div class="vp-page-sub"><?= date('d M Y', strtotime($date_from)) ?> — <?= date('d M Y', strtotime($date_to)) ?></div>
  </div>
  <form method="get" class="d-flex gap-2 flex-wrap align-items-end">
    <input type="hidden" name="tab" value="<?= Helper::sanitize($active_tab) ?>">
    <input type="date" name="date_from" class="form-control" value="<?= $date_from ?>" style="max-width:150px;">
    <span class="align-self-center text-muted">to</span>
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

<!-- Summary KPIs -->
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-2">
    <div class="vp-kpi vp-kpi-navy" style="cursor:default;">
      <div class="vp-kpi-icon"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg></div>
      <div class="vp-kpi-val"><?= number_format($total_bookings) ?></div>
      <div class="vp-kpi-lbl">Bookings</div>
      <div class="vp-kpi-footer"><span class="vp-kpi-trend" style="color:#6b7280;">Selected period</span></div>
    </div>
  </div>
  <div class="col-6 col-lg-2">
    <div class="vp-kpi vp-kpi-green" style="cursor:default;">
      <div class="vp-kpi-icon"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12l5 5L20 7"/></svg></div>
      <div class="vp-kpi-val"><?= number_format($confirmed_bk) ?></div>
      <div class="vp-kpi-lbl">Confirmed</div>
      <div class="vp-kpi-footer"><span class="vp-kpi-trend" style="color:#059669;"><?= $total_bookings>0?round($confirmed_bk/$total_bookings*100):0 ?>%</span></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="vp-kpi vp-kpi-gold" style="cursor:default;">
      <div class="vp-kpi-icon"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg></div>
      <div class="vp-kpi-val" style="font-size:1.2rem;"><?= Helper::formatCurrency($total_revenue) ?></div>
      <div class="vp-kpi-lbl">Revenue Collected</div>
      <div class="vp-kpi-footer"><span class="vp-kpi-trend" style="color:#9ca3af;font-size:.68rem;">Invoiced: <?= Helper::formatCurrency($total_invoiced) ?></span></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="vp-kpi vp-kpi-red" style="cursor:default;">
      <div class="vp-kpi-icon"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 9v4M12 17h.01"/><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg></div>
      <div class="vp-kpi-val" style="font-size:1.2rem;"><?= Helper::formatCurrency($outstanding) ?></div>
      <div class="vp-kpi-lbl">Outstanding</div>
      <div class="vp-kpi-footer"><span class="vp-kpi-trend" style="color:#dc2626;">Pending</span></div>
    </div>
  </div>
  <div class="col-6 col-lg-2">
    <div class="vp-kpi vp-kpi-navy" style="cursor:default;">
      <div class="vp-kpi-icon"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg></div>
      <div class="vp-kpi-val"><?= number_format($total_customers) ?></div>
      <div class="vp-kpi-lbl">Customers</div>
      <div class="vp-kpi-footer"><span class="vp-kpi-trend" style="color:#059669;">+<?= $new_customers ?> new</span></div>
    </div>
  </div>
</div>

<!-- Tab Navigation -->
<div class="vp-tab-nav d-print-none">
  <button class="vp-tab-btn <?= $active_tab==='revenue'?'active':'' ?>" onclick="switchTab('revenue')">📈 Revenue</button>
  <button class="vp-tab-btn <?= $active_tab==='bookings'?'active':'' ?>" onclick="switchTab('bookings')">📅 Bookings</button>
  <button class="vp-tab-btn <?= $active_tab==='customers'?'active':'' ?>" onclick="switchTab('customers')">👥 Customers</button>
  <button class="vp-tab-btn <?= $active_tab==='payments'?'active':'' ?>" onclick="switchTab('payments')">💳 Payments</button>
</div>

<!-- ═══════════════════════════════════════════════════════
     TAB: REVENUE
═══════════════════════════════════════════════════════ -->
<div class="vp-tab-pane <?= $active_tab==='revenue'?'active':'' ?>" id="tab-revenue">
  <div class="vp-export-bar d-print-none">
    <a href="<?= BASE_URL ?>/modules/reports/export.php?type=revenue&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&branch_id=<?= $branch_id ?>" class="vp-export-btn">
      <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      Export CSV
    </a>
    <button class="vp-export-btn print-btn" onclick="window.print()">
      <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
      Print
    </button>
    <button class="vp-export-btn" onclick="shareReport()">
      <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
      Share Link
    </button>
    <a href="https://wa.me/?text=<?= urlencode('VenuePro Revenue Report ('.$date_from.' to '.$date_to.'): Rs. '.number_format($total_revenue,2).' collected. '.($branch_id?'':'All branches.')) ?>" target="_blank" class="vp-export-btn" style="color:#25d366;border-color:#25d366;">
      <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
      WhatsApp
    </a>
  </div>

  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card vp-card">
        <div class="card-header"><h3 class="card-title">Monthly Revenue — Last 12 Months</h3></div>
        <div class="card-body"><div id="revenue-chart" style="height:280px"></div></div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card vp-card h-100">
        <div class="card-header"><h3 class="card-title">By Payment Method</h3></div>
        <div class="card-body">
          <div id="method-chart" style="height:220px"></div>
          <div class="mt-2">
            <?php foreach ($method_revenue as $mr): ?>
            <div class="d-flex justify-content-between align-items-center py-1" style="border-bottom:1px solid #f1f4fa;font-size:.8rem;">
              <span style="font-weight:600;"><?= ucfirst(str_replace('_',' ',$mr['payment_method'])) ?></span>
              <span style="color:var(--vp-gold);font-weight:700;"><?= Helper::formatCurrency($mr['total']) ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Recent Payments -->
  <div class="card vp-card mt-3">
    <div class="card-header d-flex align-items-center justify-content-between">
      <h3 class="card-title mb-0">Payments in Period</h3>
      <span class="fw-bold text-success"><?= Helper::formatCurrency($total_revenue) ?></span>
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
          <tr><td colspan="6"><div class="empty-state"><div class="empty-icon">💳</div><div class="empty-text">No payments in this range.</div></div></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     TAB: BOOKINGS
═══════════════════════════════════════════════════════ -->
<div class="vp-tab-pane <?= $active_tab==='bookings'?'active':'' ?>" id="tab-bookings">
  <div class="vp-export-bar d-print-none">
    <a href="<?= BASE_URL ?>/modules/reports/export.php?type=bookings&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&branch_id=<?= $branch_id ?>" class="vp-export-btn">
      <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      Export CSV
    </a>
    <button class="vp-export-btn print-btn" onclick="window.print()">
      <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
      Print
    </button>
  </div>

  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card vp-card">
        <div class="card-header"><h3 class="card-title">Monthly Bookings — Last 12 Months</h3></div>
        <div class="card-body"><div id="bookings-chart" style="height:280px"></div></div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card vp-card h-100">
        <div class="card-header"><h3 class="card-title">By Status</h3></div>
        <div class="card-body"><div id="status-chart" style="height:260px"></div></div>
      </div>
    </div>
  </div>

  <!-- Monthly Bookings Table (printable) -->
  <div class="card vp-card mt-3">
    <div class="card-header"><h3 class="card-title">Monthly Bookings — Last 12 Months</h3></div>
    <div class="table-responsive">
      <table class="table table-vcenter vp-table mb-0">
        <thead><tr><th>Month</th><th class="text-end">Bookings</th></tr></thead>
        <tbody>
          <?php foreach ($monthly_bookings as $mb): ?>
          <tr>
            <td><?= date('M Y', strtotime($mb['month'].'-01')) ?></td>
            <td class="text-end fw-bold"><?= number_format($mb['cnt']) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$monthly_bookings): ?>
          <tr><td colspan="2"><div class="empty-state"><div class="empty-icon">📭</div><div class="empty-text">No data.</div></div></td></tr>
          <?php endif; ?>
        </tbody>
        <?php if ($monthly_bookings): ?>
        <tfoot><tr style="background:#f8f9fc;"><td class="fw-700">TOTAL</td><td class="text-end fw-800"><?= number_format(array_sum(array_column($monthly_bookings,'cnt'))) ?></td></tr></tfoot>
        <?php endif; ?>
      </table>
    </div>
  </div>

  <!-- Status Summary Table -->
  <div class="card vp-card mt-3">
    <div class="card-header"><h3 class="card-title">Bookings by Status</h3></div>
    <div class="table-responsive">
      <table class="table table-vcenter vp-table mb-0">
        <thead><tr><th>Status</th><th class="text-end">Count</th><th class="text-end">Share</th></tr></thead>
        <tbody>
          <?php foreach ($status_counts as $sc): $pct=$total_bookings>0?round($sc['cnt']/$total_bookings*100):0; ?>
          <tr>
            <td><?= Helper::statusBadge($sc['status']) ?></td>
            <td class="text-end fw-bold"><?= $sc['cnt'] ?></td>
            <td class="text-end"><?= $pct ?>%</td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Event Types -->
  <div class="card vp-card mt-3">
    <div class="card-header"><h3 class="card-title">Bookings by Event Type</h3></div>
    <div class="table-responsive">
      <table class="table table-vcenter vp-table mb-0">
        <thead><tr><th>Event Type</th><th>Count</th><th>Share</th></tr></thead>
        <tbody>
          <?php foreach ($event_type_counts as $et): $pct = $total_bookings>0 ? round($et['cnt']/$total_bookings*100) : 0; ?>
          <tr>
            <td><?= Helper::sanitize($et['event_type']) ?></td>
            <td class="fw-bold"><?= $et['cnt'] ?></td>
            <td style="min-width:160px;">
              <div class="d-flex align-items-center gap-2">
                <div class="progress flex-fill" style="height:7px;border-radius:99px;">
                  <div class="progress-bar" style="width:<?= $pct ?>%;background:var(--vp-gold);border-radius:99px;"></div>
                </div>
                <span style="font-size:.78rem;color:#6b7280;min-width:32px;"><?= $pct ?>%</span>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$event_type_counts): ?>
          <tr><td colspan="3"><div class="empty-state"><div class="empty-icon">📭</div><div class="empty-text">No data in range.</div></div></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     TAB: CUSTOMERS
═══════════════════════════════════════════════════════ -->
<div class="vp-tab-pane <?= $active_tab==='customers'?'active':'' ?>" id="tab-customers">
  <div class="vp-export-bar d-print-none">
    <a href="<?= BASE_URL ?>/modules/reports/export.php?type=customers&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&branch_id=<?= $branch_id ?>" class="vp-export-btn">
      <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      Export CSV
    </a>
    <button class="vp-export-btn print-btn" onclick="window.print()">
      <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
      Print
    </button>
  </div>

  <div class="card vp-card">
    <div class="card-header d-flex align-items-center justify-content-between">
      <h3 class="card-title mb-0">Top Customers by Value</h3>
      <span class="badge" style="background:#ecfdf5;color:#059669;font-weight:700;font-size:.75rem;">+<?= $new_customers ?> new customers in period</span>
    </div>
    <div class="table-responsive">
      <table class="table table-vcenter vp-table mb-0">
        <thead><tr><th>#</th><th>Customer</th><th>Mobile</th><th class="text-end">Bookings</th><th class="text-end">Total Value</th><th>Last Event</th></tr></thead>
        <tbody>
          <?php foreach ($top_customers as $i=>$tc): ?>
          <tr>
            <td style="color:#9ca3af;font-weight:700;font-size:.8rem;"><?= $i+1 ?></td>
            <td class="fw-bold"><?= Helper::sanitize($tc['name']) ?></td>
            <td style="font-size:.8rem;color:#6b7280;"><?= Helper::sanitize($tc['mobile']) ?></td>
            <td class="text-end"><?= $tc['bookings'] ?></td>
            <td class="text-end fw-bold" style="color:var(--vp-gold)"><?= Helper::formatCurrency($tc['total']) ?></td>
            <td style="font-size:.8rem;"><?= Helper::formatDate($tc['last_event']) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$top_customers): ?>
          <tr><td colspan="6"><div class="empty-state"><div class="empty-icon">👥</div><div class="empty-text">No data in range.</div></div></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     TAB: PAYMENTS
═══════════════════════════════════════════════════════ -->
<div class="vp-tab-pane <?= $active_tab==='payments'?'active':'' ?>" id="tab-payments">
  <div class="vp-export-bar d-print-none">
    <a href="<?= BASE_URL ?>/modules/reports/export.php?type=payments&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&branch_id=<?= $branch_id ?>" class="vp-export-btn">
      <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      Export CSV
    </a>
    <button class="vp-export-btn print-btn" onclick="window.print()">
      <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
      Print
    </button>
    <a href="https://wa.me/?text=<?= urlencode('VenuePro Payments Report ('.$date_from.' to '.$date_to.'): Rs. '.number_format($total_revenue,2).' collected across '.count($recent_payments).' transactions.') ?>" target="_blank" class="vp-export-btn" style="color:#25d366;border-color:#25d366;">
      <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
      WhatsApp
    </a>
  </div>

  <div class="card vp-card">
    <div class="card-header"><h3 class="card-title">Daily Payment Totals</h3></div>
    <div class="card-body"><div id="daily-chart" style="height:260px"></div></div>
  </div>

  <div class="card vp-card mt-3">
    <div class="card-header d-flex align-items-center justify-content-between">
      <h3 class="card-title mb-0">All Payments in Period</h3>
      <span style="font-weight:700;color:var(--vp-gold)"><?= Helper::formatCurrency($total_revenue) ?></span>
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
          <tr><td colspan="6"><div class="empty-state"><div class="empty-icon">💳</div><div class="empty-text">No payments in this range.</div></div></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ── ApexCharts ───────────────────────────────────────────── -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.44.0/dist/apexcharts.min.js"></script>
<script>
// ── Revenue Line Chart ────────────────────────────────────────
const revMonths   = <?= json_encode(array_column($monthly_revenue,'month')) ?>;
const revAmounts  = <?= json_encode(array_map(fn($r)=>(float)$r['total'], $monthly_revenue)) ?>;
new ApexCharts(document.getElementById('revenue-chart'), {
  chart:{ type:'area', height:280, toolbar:{show:false}, background:'transparent' },
  series:[{ name:'Revenue (Rs.)', data:revAmounts }],
  xaxis:{ categories:revMonths, labels:{ style:{colors:'#8899aa'} } },
  colors:['#c9a84c'],
  stroke:{ curve:'smooth', width:2.5 },
  fill:{ type:'gradient', gradient:{ shade:'light', type:'vertical', opacityFrom:.18, opacityTo:.01 } },
  yaxis:{ labels:{ formatter:v=>'Rs. '+v.toLocaleString(), style:{colors:'#8899aa'} } },
  tooltip:{ y:{ formatter:v=>'Rs. '+v.toLocaleString() }, theme:'dark' },
  grid:{ borderColor:'#edf0f8' },
  dataLabels:{ enabled:false }
}).render();

// ── Method Donut ──────────────────────────────────────────────
const methodData = <?= json_encode($method_revenue) ?>;
if (methodData.length) {
  new ApexCharts(document.getElementById('method-chart'), {
    chart:{ type:'donut', height:220, background:'transparent' },
    series: methodData.map(m=>parseFloat(m.total)),
    labels: methodData.map(m=>m.payment_method.replace('_',' ').replace(/\b\w/g,l=>l.toUpperCase())),
    colors:['#c9a84c','#0c1a35','#059669','#3b82f6','#a855f7'],
    legend:{ position:'bottom', labels:{colors:'#6b7280'} },
    tooltip:{ y:{ formatter:v=>'Rs. '+v.toLocaleString() }, theme:'dark' },
    dataLabels:{ style:{colors:['#fff']} }
  }).render();
}

// ── Bookings Line Chart ───────────────────────────────────────
const bkMonths  = <?= json_encode(array_column($monthly_bookings,'month')) ?>;
const bkCounts  = <?= json_encode(array_map(fn($r)=>(int)$r['cnt'], $monthly_bookings)) ?>;
new ApexCharts(document.getElementById('bookings-chart'), {
  chart:{ type:'area', height:280, toolbar:{show:false}, background:'transparent' },
  series:[{ name:'Bookings', data:bkCounts }],
  xaxis:{ categories:bkMonths, labels:{ style:{colors:'#8899aa'} } },
  colors:['#0c1a35'],
  stroke:{ curve:'smooth', width:2.5 },
  fill:{ type:'gradient', gradient:{ shade:'light', type:'vertical', opacityFrom:.15, opacityTo:.01 } },
  yaxis:{ labels:{ style:{colors:'#8899aa'} } },
  tooltip:{ theme:'dark' },
  grid:{ borderColor:'#edf0f8' },
  dataLabels:{ enabled:false }
}).render();

// ── Status Donut ──────────────────────────────────────────────
const statusData = <?= json_encode($status_counts) ?>;
if (statusData.length) {
  new ApexCharts(document.getElementById('status-chart'), {
    chart:{ type:'donut', height:260, background:'transparent' },
    series: statusData.map(s=>parseInt(s.cnt)),
    labels: statusData.map(s=>s.status.charAt(0).toUpperCase()+s.status.slice(1)),
    colors:['#6b7280','#c9a84c','#059669','#0c1a35','#dc2626','#3b82f6'],
    legend:{ position:'bottom', labels:{colors:'#6b7280'} },
    tooltip:{ theme:'dark' },
    dataLabels:{ style:{colors:['#fff']} }
  }).render();
}

// ── Daily Payments Bar ────────────────────────────────────────
const dailyDates  = <?= json_encode(array_column($payment_daily,'payment_date')) ?>;
const dailyTotals = <?= json_encode(array_map(fn($r)=>(float)$r['total'], $payment_daily)) ?>;
if (dailyDates.length) {
  new ApexCharts(document.getElementById('daily-chart'), {
    chart:{ type:'bar', height:260, toolbar:{show:false}, background:'transparent' },
    series:[{ name:'Amount (Rs.)', data:dailyTotals }],
    xaxis:{ categories:dailyDates, labels:{ style:{colors:'#8899aa'}, rotate:-30 } },
    colors:['#c9a84c'],
    fill:{ type:'gradient', gradient:{ shade:'light', type:'vertical', gradientToColors:['#0c1a35'], stops:[0,100] } },
    yaxis:{ labels:{ formatter:v=>'Rs. '+v.toLocaleString(), style:{colors:'#8899aa'} } },
    tooltip:{ y:{ formatter:v=>'Rs. '+v.toLocaleString() }, theme:'dark' },
    grid:{ borderColor:'#edf0f8' },
    dataLabels:{ enabled:false },
    plotOptions:{ bar:{ borderRadius:4 } }
  }).render();
}

// ── Tab switching ─────────────────────────────────────────────
function switchTab(name) {
  document.querySelectorAll('.vp-tab-btn').forEach(b=>b.classList.remove('active'));
  document.querySelectorAll('.vp-tab-pane').forEach(p=>p.classList.remove('active'));
  document.querySelector('.vp-tab-btn[onclick="switchTab(\''+name+'\')"]').classList.add('active');
  document.getElementById('tab-'+name).classList.add('active');
  // update URL without reload
  const u = new URL(window.location.href); u.searchParams.set('tab',name); history.replaceState(null,'',u);
}

// ── Share link ─────────────────────────────────────────────────
function shareReport() {
  const url = window.location.href;
  if (navigator.clipboard) {
    navigator.clipboard.writeText(url).then(()=>{ alert('Link copied to clipboard!'); });
  } else {
    prompt('Copy this link:', url);
  }
}
</script>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
