<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
if (!Auth::hasRole(['super_admin','admin','hall_manager','accountant'])) {
    Helper::flash('error','Access denied.');
    Helper::redirect(BASE_URL.'/index.php');
}
$db = Database::getInstance();
$cu = Auth::currentUser();

$date_from   = $_GET['date_from'] ?? date('Y-m-01');
$date_to     = $_GET['date_to']   ?? date('Y-m-d');
$branch_id   = $cu['branch_id']   ?? (int)($_GET['branch_id'] ?? 0);
$active_tab  = $_GET['tab']       ?? 'revenue';

$bfBk  = $branch_id ? "AND b.branch_id=$branch_id"  : "";
$bfPay = $branch_id ? "AND p.branch_id=$branch_id"  : "";
$bfInv = $branch_id ? "AND i.branch_id=$branch_id"  : "";

// ── KPIs ──────────────────────────────────────────────────────
$kpi_revenue    = (float)($db->fetchOne("SELECT SUM(p.amount) as v FROM payments p WHERE p.payment_date BETWEEN ? AND ? $bfPay", [$date_from,$date_to])['v'] ?? 0);
$kpi_invoiced   = (float)($db->fetchOne("SELECT SUM(i.total) as v FROM invoices i WHERE i.invoice_date BETWEEN ? AND ? $bfInv AND i.status NOT IN ('cancelled')", [$date_from,$date_to])['v'] ?? 0);
$kpi_outstanding= (float)($db->fetchOne("SELECT SUM(b.balance_amount) as v FROM bookings b WHERE b.status IN ('confirmed','booked') AND b.balance_amount>0 $bfBk")['v'] ?? 0);
$kpi_bookings   = (int)($db->fetchOne("SELECT COUNT(*) as v FROM bookings b WHERE b.event_date BETWEEN ? AND ? $bfBk", [$date_from,$date_to])['v'] ?? 0);
$kpi_confirmed  = (int)($db->fetchOne("SELECT COUNT(*) as v FROM bookings b WHERE b.status='confirmed' AND b.event_date BETWEEN ? AND ? $bfBk", [$date_from,$date_to])['v'] ?? 0);
$kpi_customers  = (int)($db->fetchOne("SELECT COUNT(DISTINCT b.customer_id) as v FROM bookings b WHERE b.event_date BETWEEN ? AND ? $bfBk", [$date_from,$date_to])['v'] ?? 0);
$kpi_new_cust   = (int)($db->fetchOne("SELECT COUNT(*) as v FROM customers WHERE created_at BETWEEN ? AND ?", [$date_from.' 00:00:00', $date_to.' 23:59:59'])['v'] ?? 0);

// ── TAB: Revenue ──────────────────────────────────────────────
$revenue_detail = $db->fetchAll(
    "SELECT p.payment_ref, p.payment_date, c.name as customer_name, c.mobile,
            b.booking_ref, h.name as hall_name, p.payment_method, p.amount, p.notes
     FROM payments p
     LEFT JOIN customers c ON p.customer_id=c.id
     LEFT JOIN bookings b ON p.booking_id=b.id
     LEFT JOIN halls h ON b.hall_id=h.id
     WHERE p.payment_date BETWEEN ? AND ? $bfPay
     ORDER BY p.payment_date DESC",
    [$date_from, $date_to]
);
$revenue_by_method = $db->fetchAll(
    "SELECT p.payment_method, COUNT(*) as cnt, SUM(p.amount) as total
     FROM payments p WHERE p.payment_date BETWEEN ? AND ? $bfPay
     GROUP BY p.payment_method ORDER BY total DESC",
    [$date_from, $date_to]
);
$revenue_monthly = $db->fetchAll(
    "SELECT DATE_FORMAT(p.payment_date,'%Y-%m') as ym, SUM(p.amount) as total, COUNT(*) as cnt
     FROM payments p WHERE p.payment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) $bfPay
     GROUP BY ym ORDER BY ym ASC"
);

// ── TAB: Bookings ─────────────────────────────────────────────
$bookings_detail = $db->fetchAll(
    "SELECT b.booking_ref, b.event_date, b.event_type, b.status,
            c.name as customer_name, c.mobile,
            h.name as hall_name, p.name as package_name,
            b.final_amount, b.paid_amount, b.balance_amount
     FROM bookings b
     LEFT JOIN customers c ON b.customer_id=c.id
     LEFT JOIN halls h ON b.hall_id=h.id
     LEFT JOIN packages p ON b.package_id=p.id
     WHERE b.event_date BETWEEN ? AND ? $bfBk
     ORDER BY b.event_date ASC",
    [$date_from, $date_to]
);
$bookings_by_status = $db->fetchAll(
    "SELECT b.status, COUNT(*) as cnt, SUM(b.final_amount) as total
     FROM bookings b WHERE b.event_date BETWEEN ? AND ? $bfBk
     GROUP BY b.status ORDER BY cnt DESC",
    [$date_from, $date_to]
);
$bookings_by_hall = $db->fetchAll(
    "SELECT h.name as hall, COUNT(b.id) as cnt, SUM(b.final_amount) as total
     FROM bookings b LEFT JOIN halls h ON b.hall_id=h.id
     WHERE b.event_date BETWEEN ? AND ? $bfBk
     GROUP BY b.hall_id ORDER BY cnt DESC",
    [$date_from, $date_to]
);

// ── TAB: Invoices ─────────────────────────────────────────────
$invoices_detail = $db->fetchAll(
    "SELECT i.invoice_number, i.invoice_date, i.due_date, i.invoice_type, i.status,
            c.name as customer_name, c.mobile,
            b.booking_ref, h.name as hall_name,
            i.subtotal, i.discount_amount, i.tax_amount, i.total, i.paid_amount, i.balance
     FROM invoices i
     LEFT JOIN customers c ON i.customer_id=c.id
     LEFT JOIN bookings b ON i.booking_id=b.id
     LEFT JOIN halls h ON b.hall_id=h.id
     WHERE i.invoice_date BETWEEN ? AND ? $bfInv
       AND i.status NOT IN ('cancelled')
     ORDER BY i.invoice_date DESC",
    [$date_from, $date_to]
);
$invoices_by_status = $db->fetchAll(
    "SELECT i.status, COUNT(*) as cnt, SUM(i.total) as total
     FROM invoices i WHERE i.invoice_date BETWEEN ? AND ? $bfInv
     GROUP BY i.status ORDER BY total DESC",
    [$date_from, $date_to]
);

// ── TAB: Outstanding ─────────────────────────────────────────
$outstanding_list = $db->fetchAll(
    "SELECT b.booking_ref, b.event_date, b.status,
            c.name as customer_name, c.mobile,
            h.name as hall_name,
            b.final_amount, b.paid_amount, b.balance_amount,
            MAX(i.due_date) as due_date
     FROM bookings b
     LEFT JOIN customers c ON b.customer_id=c.id
     LEFT JOIN halls h ON b.hall_id=h.id
     LEFT JOIN invoices i ON i.booking_id=b.id AND i.status NOT IN ('cancelled','paid')
     WHERE b.balance_amount > 0 AND b.status NOT IN ('cancelled','completed') $bfBk
     GROUP BY b.id
     ORDER BY b.event_date ASC"
);

// ── TAB: Customers ────────────────────────────────────────────
$customers_detail = $db->fetchAll(
    "SELECT c.name, c.mobile, c.email,
            COUNT(b.id) as bookings,
            SUM(b.final_amount) as total_value,
            SUM(b.paid_amount) as paid,
            SUM(b.balance_amount) as balance,
            MIN(b.event_date) as first_event,
            MAX(b.event_date) as last_event
     FROM customers c
     LEFT JOIN bookings b ON b.customer_id=c.id AND b.event_date BETWEEN ? AND ? $bfBk
     WHERE b.id IS NOT NULL
     GROUP BY c.id
     ORDER BY total_value DESC",
    [$date_from, $date_to]
);

$branches = $db->fetchAll("SELECT id,name FROM branches WHERE is_active=1");

$pageTitle  = 'Reports';
$breadcrumbs = [['label'=>'Reports']];
require_once ROOT_PATH . '/includes/header.php';
?>

<style>
.rpt-tab-bar { display:flex; gap:.3rem; background:#f1f5f9; border-radius:14px; padding:.3rem; width:fit-content; margin-bottom:1.5rem; flex-wrap:wrap; }
.rpt-tab { padding:.45rem 1.1rem; border:none; background:none; border-radius:10px; font-size:.8rem; font-weight:700; color:#6b7280; cursor:pointer; transition:all .18s; white-space:nowrap; }
.rpt-tab.active { background:#fff; color:#0c1a35; box-shadow:0 2px 8px rgba(12,26,53,.1); }
.rpt-pane { display:none; } .rpt-pane.active { display:block; }

.kpi-row { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:.75rem; margin-bottom:1.5rem; }
.kpi-box { background:#fff; border-radius:14px; padding:1.1rem 1.2rem; border:1px solid #edf0f8; box-shadow:0 2px 10px rgba(12,26,53,.07); position:relative; overflow:hidden; }
.kpi-box::before { content:''; position:absolute; left:0; top:0; bottom:0; width:4px; border-radius:14px 0 0 14px; }
.kpi-box.navy::before { background:linear-gradient(#0c1a35,#1a3060); }
.kpi-box.gold::before { background:linear-gradient(#c9a84c,#e8c96a); }
.kpi-box.green::before { background:linear-gradient(#059669,#34d399); }
.kpi-box.red::before { background:linear-gradient(#dc2626,#f87171); }
.kpi-box.purple::before { background:linear-gradient(#7c3aed,#a78bfa); }
.kpi-val { font-size:1.5rem; font-weight:800; color:#0c1a35; line-height:1.1; letter-spacing:-.03em; }
.kpi-lbl { font-size:.68rem; font-weight:700; color:#9ca3af; text-transform:uppercase; letter-spacing:.07em; margin-top:.25rem; }
.kpi-sub { font-size:.7rem; color:#9ca3af; margin-top:.35rem; }

.rpt-section { background:#fff; border-radius:14px; border:1px solid #edf0f8; box-shadow:0 2px 10px rgba(12,26,53,.06); margin-bottom:1rem; overflow:hidden; }
.rpt-section-hd { padding:.85rem 1.1rem; border-bottom:1px solid #edf0f8; display:flex; align-items:center; justify-content:space-between; background:#fafbff; }
.rpt-section-title { font-size:.85rem; font-weight:800; color:#0c1a35; margin:0; }
.rpt-section-total { font-size:.82rem; font-weight:700; color:#c9a84c; }

.rpt-table { width:100%; border-collapse:collapse; font-size:.78rem; }
.rpt-table thead th { padding:.6rem .85rem; background:#f8f9fc; color:#6b7280; font-weight:700; font-size:.7rem; text-transform:uppercase; letter-spacing:.05em; border-bottom:2px solid #edf0f8; white-space:nowrap; }
.rpt-table tbody td { padding:.6rem .85rem; border-bottom:1px solid #f1f4fa; color:#1f2937; vertical-align:middle; }
.rpt-table tbody tr:last-child td { border-bottom:none; }
.rpt-table tbody tr:hover td { background:#fafbff; }
.rpt-table tfoot td { padding:.65rem .85rem; background:#f8f9fc; font-weight:800; color:#0c1a35; border-top:2px solid #edf0f8; }
.rpt-table .num { text-align:right; font-variant-numeric:tabular-nums; }
.rpt-table .amt { text-align:right; font-weight:700; color:#c9a84c; font-variant-numeric:tabular-nums; }
.rpt-table .bal { text-align:right; font-weight:700; color:#dc2626; font-variant-numeric:tabular-nums; }
.rpt-table .ref { font-family:monospace; font-size:.74rem; color:#0c1a35; font-weight:700; background:#eef1f8; padding:.15rem .4rem; border-radius:5px; }

.bar-cell { display:flex; align-items:center; gap:.5rem; min-width:140px; }
.bar-track { flex:1; height:7px; background:#f1f4fa; border-radius:99px; overflow:hidden; }
.bar-fill { height:100%; border-radius:99px; background:var(--vp-gold,#c9a84c); }

.export-bar { display:flex; gap:.5rem; margin-bottom:1rem; flex-wrap:wrap; }
.export-btn { display:inline-flex; align-items:center; gap:.35rem; padding:.38rem .9rem; border-radius:9px; font-size:.75rem; font-weight:700; border:1.5px solid #e2e8f0; background:#fff; color:#374151; cursor:pointer; text-decoration:none; transition:all .15s; }
.export-btn:hover { border-color:#c9a84c; color:#92640c; background:#fdf5e0; }

.empty-rpt { padding:2.5rem 1rem; text-align:center; color:#9ca3af; font-size:.82rem; }
.empty-rpt-icon { font-size:1.8rem; margin-bottom:.4rem; }

@media print {
  .vp-page-header,.rpt-tab-bar,.export-bar,nav,.navbar,.sidebar,#sidebar,.d-print-none,.btn,footer { display:none!important; }
  .rpt-pane { display:block!important; page-break-before:always; }
  .rpt-pane:first-of-type { page-break-before:auto; }
  .rpt-section { box-shadow:none!important; border:1px solid #ddd!important; break-inside:avoid; margin-bottom:.8rem!important; }
  body { background:#fff!important; }
  @page { margin:1.5cm; }
}
</style>

<!-- Page header -->
<div class="vp-page-header d-print-none">
  <div>
    <h1 class="vp-page-title">Reports</h1>
    <div class="vp-page-sub"><?= date('d M Y',strtotime($date_from)) ?> — <?= date('d M Y',strtotime($date_to)) ?></div>
  </div>
  <form method="get" class="d-flex gap-2 flex-wrap align-items-end">
    <input type="hidden" name="tab" value="<?= Helper::sanitize($active_tab) ?>">
    <input type="date" name="date_from" class="form-control" value="<?= $date_from ?>" style="max-width:150px;">
    <span class="align-self-center text-muted fw-600">to</span>
    <input type="date" name="date_to" class="form-control" value="<?= $date_to ?>" style="max-width:150px;">
    <?php if (Auth::isSuperAdmin()): ?>
    <select name="branch_id" class="form-select" style="max-width:160px;">
      <option value="">All Branches</option>
      <?php foreach ($branches as $br): ?>
      <option value="<?= $br['id'] ?>" <?= $branch_id==$br['id']?'selected':'' ?>><?= Helper::sanitize($br['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php endif; ?>
    <button class="btn btn-vp-primary btn-sm">Apply</button>
  </form>
</div>

<!-- KPIs -->
<div class="kpi-row">
  <div class="kpi-box gold">
    <div class="kpi-val"><?= Helper::formatCurrency($kpi_revenue) ?></div>
    <div class="kpi-lbl">Revenue Collected</div>
    <div class="kpi-sub">Invoiced: <?= Helper::formatCurrency($kpi_invoiced) ?></div>
  </div>
  <div class="kpi-box red">
    <div class="kpi-val"><?= Helper::formatCurrency($kpi_outstanding) ?></div>
    <div class="kpi-lbl">Outstanding Balance</div>
    <div class="kpi-sub">Active bookings only</div>
  </div>
  <div class="kpi-box navy">
    <div class="kpi-val"><?= number_format($kpi_bookings) ?></div>
    <div class="kpi-lbl">Total Bookings</div>
    <div class="kpi-sub"><?= $kpi_confirmed ?> confirmed (<?= $kpi_bookings>0?round($kpi_confirmed/$kpi_bookings*100):0 ?>%)</div>
  </div>
  <div class="kpi-box green">
    <div class="kpi-val"><?= number_format($kpi_customers) ?></div>
    <div class="kpi-lbl">Active Customers</div>
    <div class="kpi-sub">+<?= $kpi_new_cust ?> new in period</div>
  </div>
</div>

<!-- Tabs -->
<div class="rpt-tab-bar d-print-none">
  <button class="rpt-tab <?= $active_tab==='revenue'?'active':'' ?>"     onclick="rptTab('revenue')">Revenue</button>
  <button class="rpt-tab <?= $active_tab==='bookings'?'active':'' ?>"    onclick="rptTab('bookings')">Bookings</button>
  <button class="rpt-tab <?= $active_tab==='invoices'?'active':'' ?>"    onclick="rptTab('invoices')">Invoices</button>
  <button class="rpt-tab <?= $active_tab==='outstanding'?'active':'' ?>" onclick="rptTab('outstanding')">Outstanding</button>
  <button class="rpt-tab <?= $active_tab==='customers'?'active':'' ?>"   onclick="rptTab('customers')">Customers</button>
</div>

<!-- ═══ TAB: REVENUE ═══ -->
<div class="rpt-pane <?= $active_tab==='revenue'?'active':'' ?>" id="rpt-revenue">
  <div class="export-bar d-print-none">
    <a class="export-btn" href="<?= BASE_URL ?>/modules/reports/export.php?type=revenue&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&branch_id=<?= $branch_id ?>">↓ Export CSV</a>
    <button class="export-btn" onclick="window.print()">🖨 Print</button>
  </div>

  <!-- Summary by method -->
  <div class="rpt-section mb-3">
    <div class="rpt-section-hd">
      <h4 class="rpt-section-title">Summary by Payment Method</h4>
      <span class="rpt-section-total"><?= Helper::formatCurrency($kpi_revenue) ?></span>
    </div>
    <table class="rpt-table">
      <thead><tr><th>Method</th><th class="num">Transactions</th><th class="num">Total</th><th>Share</th></tr></thead>
      <tbody>
        <?php foreach ($revenue_by_method as $rm):
          $pct = $kpi_revenue>0 ? round($rm['total']/$kpi_revenue*100) : 0; ?>
        <tr>
          <td style="font-weight:600;"><?= ucwords(str_replace('_',' ',$rm['payment_method'])) ?></td>
          <td class="num"><?= $rm['cnt'] ?></td>
          <td class="amt"><?= Helper::formatCurrency($rm['total']) ?></td>
          <td>
            <div class="bar-cell">
              <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%"></div></div>
              <span style="font-size:.72rem;color:#6b7280;min-width:30px;"><?= $pct ?>%</span>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$revenue_by_method): ?>
        <tr><td colspan="4"><div class="empty-rpt"><div class="empty-rpt-icon">💳</div>No payments in this period.</div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Monthly trend -->
  <div class="rpt-section mb-3">
    <div class="rpt-section-hd"><h4 class="rpt-section-title">Monthly Revenue — Last 12 Months</h4></div>
    <table class="rpt-table">
      <thead><tr><th>Month</th><th class="num">Payments</th><th class="num">Total Collected</th></tr></thead>
      <tbody>
        <?php foreach ($revenue_monthly as $rm): ?>
        <tr>
          <td><?= date('M Y',strtotime($rm['ym'].'-01')) ?></td>
          <td class="num"><?= $rm['cnt'] ?></td>
          <td class="amt"><?= Helper::formatCurrency($rm['total']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$revenue_monthly): ?>
        <tr><td colspan="3"><div class="empty-rpt"><div class="empty-rpt-icon">📊</div>No data.</div></td></tr>
        <?php endif; ?>
      </tbody>
      <?php if ($revenue_monthly): ?>
      <tfoot><tr>
        <td>TOTAL</td>
        <td class="num"><?= array_sum(array_column($revenue_monthly,'cnt')) ?></td>
        <td class="amt"><?= Helper::formatCurrency(array_sum(array_column($revenue_monthly,'total'))) ?></td>
      </tr></tfoot>
      <?php endif; ?>
    </table>
  </div>

  <!-- Detailed payments -->
  <div class="rpt-section">
    <div class="rpt-section-hd">
      <h4 class="rpt-section-title">Payment Transactions (<?= count($revenue_detail) ?>)</h4>
      <span class="rpt-section-total"><?= Helper::formatCurrency($kpi_revenue) ?></span>
    </div>
    <div style="overflow-x:auto;">
      <table class="rpt-table">
        <thead><tr><th>Ref</th><th>Date</th><th>Customer</th><th>Booking</th><th>Hall</th><th>Method</th><th class="num">Amount</th></tr></thead>
        <tbody>
          <?php foreach ($revenue_detail as $p): ?>
          <tr>
            <td><span class="ref"><?= Helper::sanitize($p['payment_ref']) ?></span></td>
            <td><?= date('d M Y',strtotime($p['payment_date'])) ?></td>
            <td>
              <div style="font-weight:600;"><?= Helper::sanitize($p['customer_name']) ?></div>
              <div style="font-size:.7rem;color:#9ca3af;"><?= Helper::sanitize($p['mobile']) ?></div>
            </td>
            <td><?= $p['booking_ref'] ? '<span class="ref">'.Helper::sanitize($p['booking_ref']).'</span>' : '—' ?></td>
            <td style="font-size:.75rem;"><?= Helper::sanitize($p['hall_name'] ?? '—') ?></td>
            <td><?= ucwords(str_replace('_',' ',$p['payment_method'])) ?></td>
            <td class="amt"><?= Helper::formatCurrency($p['amount']) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$revenue_detail): ?>
          <tr><td colspan="7"><div class="empty-rpt"><div class="empty-rpt-icon">💳</div>No payments in this range.</div></td></tr>
          <?php endif; ?>
        </tbody>
        <?php if ($revenue_detail): ?>
        <tfoot><tr>
          <td colspan="6">TOTAL</td>
          <td class="amt"><?= Helper::formatCurrency(array_sum(array_column($revenue_detail,'amount'))) ?></td>
        </tr></tfoot>
        <?php endif; ?>
      </table>
    </div>
  </div>
</div>

<!-- ═══ TAB: BOOKINGS ═══ -->
<div class="rpt-pane <?= $active_tab==='bookings'?'active':'' ?>" id="rpt-bookings">
  <div class="export-bar d-print-none">
    <a class="export-btn" href="<?= BASE_URL ?>/modules/reports/export.php?type=bookings&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&branch_id=<?= $branch_id ?>">↓ Export CSV</a>
    <button class="export-btn" onclick="window.print()">🖨 Print</button>
  </div>

  <div class="row g-3 mb-3">
    <!-- By status -->
    <div class="col-md-6">
      <div class="rpt-section h-100">
        <div class="rpt-section-hd"><h4 class="rpt-section-title">By Status</h4></div>
        <table class="rpt-table">
          <thead><tr><th>Status</th><th class="num">Count</th><th class="num">Value</th><th>Share</th></tr></thead>
          <tbody>
            <?php foreach ($bookings_by_status as $bs):
              $pct = $kpi_bookings>0 ? round($bs['cnt']/$kpi_bookings*100) : 0; ?>
            <tr>
              <td><?= Helper::statusBadge($bs['status']) ?></td>
              <td class="num"><?= $bs['cnt'] ?></td>
              <td class="amt"><?= Helper::formatCurrency($bs['total']) ?></td>
              <td>
                <div class="bar-cell">
                  <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%"></div></div>
                  <span style="font-size:.72rem;color:#6b7280;min-width:30px;"><?= $pct ?>%</span>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <!-- By hall -->
    <div class="col-md-6">
      <div class="rpt-section h-100">
        <div class="rpt-section-hd"><h4 class="rpt-section-title">By Hall</h4></div>
        <table class="rpt-table">
          <thead><tr><th>Hall</th><th class="num">Bookings</th><th class="num">Total Value</th></tr></thead>
          <tbody>
            <?php foreach ($bookings_by_hall as $bh): ?>
            <tr>
              <td style="font-weight:600;"><?= Helper::sanitize($bh['hall'] ?? 'N/A') ?></td>
              <td class="num"><?= $bh['cnt'] ?></td>
              <td class="amt"><?= Helper::formatCurrency($bh['total']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$bookings_by_hall): ?><tr><td colspan="3"><div class="empty-rpt">No data.</div></td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Detailed bookings -->
  <div class="rpt-section">
    <div class="rpt-section-hd">
      <h4 class="rpt-section-title">All Bookings in Period (<?= count($bookings_detail) ?>)</h4>
      <span class="rpt-section-total"><?= Helper::formatCurrency(array_sum(array_column($bookings_detail,'final_amount'))) ?></span>
    </div>
    <div style="overflow-x:auto;">
      <table class="rpt-table">
        <thead><tr><th>Ref</th><th>Event Date</th><th>Customer</th><th>Hall</th><th>Package</th><th>Type</th><th>Status</th><th class="num">Amount</th><th class="num">Paid</th><th class="num">Balance</th></tr></thead>
        <tbody>
          <?php foreach ($bookings_detail as $bk): ?>
          <tr>
            <td><a href="<?= BASE_URL ?>/modules/bookings/view.php?id=<?= $bk['id'] ?>" class="ref"><?= Helper::sanitize($bk['booking_ref']) ?></a></td>
            <td style="white-space:nowrap;"><?= date('d M Y',strtotime($bk['event_date'])) ?></td>
            <td>
              <div style="font-weight:600;"><?= Helper::sanitize($bk['customer_name']) ?></div>
              <div style="font-size:.7rem;color:#9ca3af;"><?= Helper::sanitize($bk['mobile']) ?></div>
            </td>
            <td style="font-size:.75rem;"><?= Helper::sanitize($bk['hall_name'] ?? '—') ?></td>
            <td style="font-size:.75rem;"><?= Helper::sanitize($bk['package_name'] ?? '—') ?></td>
            <td style="font-size:.75rem;"><?= Helper::sanitize($bk['event_type'] ?? '—') ?></td>
            <td><?= Helper::statusBadge($bk['status']) ?></td>
            <td class="amt"><?= Helper::formatCurrency($bk['final_amount']) ?></td>
            <td class="num" style="color:#059669;font-weight:600;"><?= Helper::formatCurrency($bk['paid_amount']) ?></td>
            <td class="<?= $bk['balance_amount']>0?'bal':'num' ?>"><?= Helper::formatCurrency($bk['balance_amount']) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$bookings_detail): ?>
          <tr><td colspan="10"><div class="empty-rpt"><div class="empty-rpt-icon">📅</div>No bookings in this range.</div></td></tr>
          <?php endif; ?>
        </tbody>
        <?php if ($bookings_detail): ?>
        <tfoot><tr>
          <td colspan="7">TOTAL (<?= count($bookings_detail) ?>)</td>
          <td class="amt"><?= Helper::formatCurrency(array_sum(array_column($bookings_detail,'final_amount'))) ?></td>
          <td class="num" style="color:#059669;font-weight:700;"><?= Helper::formatCurrency(array_sum(array_column($bookings_detail,'paid_amount'))) ?></td>
          <td class="bal"><?= Helper::formatCurrency(array_sum(array_column($bookings_detail,'balance_amount'))) ?></td>
        </tr></tfoot>
        <?php endif; ?>
      </table>
    </div>
  </div>
</div>

<!-- ═══ TAB: INVOICES ═══ -->
<div class="rpt-pane <?= $active_tab==='invoices'?'active':'' ?>" id="rpt-invoices">
  <div class="export-bar d-print-none">
    <a class="export-btn" href="<?= BASE_URL ?>/modules/reports/export.php?type=invoices&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&branch_id=<?= $branch_id ?>">↓ Export CSV</a>
    <button class="export-btn" onclick="window.print()">🖨 Print</button>
  </div>

  <!-- Summary by status -->
  <div class="rpt-section mb-3">
    <div class="rpt-section-hd">
      <h4 class="rpt-section-title">Summary by Status</h4>
      <span class="rpt-section-total"><?= Helper::formatCurrency($kpi_invoiced) ?></span>
    </div>
    <table class="rpt-table">
      <thead><tr><th>Status</th><th class="num">Count</th><th class="num">Total</th></tr></thead>
      <tbody>
        <?php foreach ($invoices_by_status as $is): ?>
        <tr>
          <td><?= Helper::statusBadge($is['status']) ?></td>
          <td class="num"><?= $is['cnt'] ?></td>
          <td class="amt"><?= Helper::formatCurrency($is['total']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$invoices_by_status): ?><tr><td colspan="3"><div class="empty-rpt">No invoices in range.</div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Detailed invoices -->
  <div class="rpt-section">
    <div class="rpt-section-hd">
      <h4 class="rpt-section-title">All Invoices in Period (<?= count($invoices_detail) ?>)</h4>
      <span class="rpt-section-total"><?= Helper::formatCurrency(array_sum(array_column($invoices_detail,'total'))) ?></span>
    </div>
    <div style="overflow-x:auto;">
      <table class="rpt-table">
        <thead><tr><th>Invoice #</th><th>Date</th><th>Due</th><th>Customer</th><th>Booking</th><th>Type</th><th>Status</th><th class="num">Subtotal</th><th class="num">Tax</th><th class="num">Disc</th><th class="num">Total</th><th class="num">Paid</th><th class="num">Balance</th></tr></thead>
        <tbody>
          <?php foreach ($invoices_detail as $inv): ?>
          <tr>
            <td><a href="<?= BASE_URL ?>/modules/invoices/view.php?id=<?= $inv['id'] ?>" class="ref"><?= Helper::sanitize($inv['invoice_number']) ?></a></td>
            <td style="white-space:nowrap;"><?= date('d M Y',strtotime($inv['invoice_date'])) ?></td>
            <td style="white-space:nowrap;font-size:.73rem;<?= ($inv['due_date']&&$inv['due_date']<date('Y-m-d')&&$inv['status']!='paid')?'color:#dc2626;font-weight:700;':'' ?>">
              <?= $inv['due_date'] ? date('d M Y',strtotime($inv['due_date'])) : '—' ?>
            </td>
            <td>
              <div style="font-weight:600;"><?= Helper::sanitize($inv['customer_name']) ?></div>
              <div style="font-size:.7rem;color:#9ca3af;"><?= Helper::sanitize($inv['mobile']) ?></div>
            </td>
            <td><?= $inv['booking_ref'] ? '<span class="ref">'.Helper::sanitize($inv['booking_ref']).'</span>' : '—' ?></td>
            <td style="font-size:.75rem;"><?= ucfirst($inv['invoice_type']) ?></td>
            <td><?= Helper::statusBadge($inv['status']) ?></td>
            <td class="num"><?= Helper::formatCurrency($inv['subtotal']) ?></td>
            <td class="num" style="font-size:.72rem;"><?= Helper::formatCurrency($inv['tax_amount']) ?></td>
            <td class="num" style="font-size:.72rem;color:#059669;"><?= $inv['discount_amount']>0?Helper::formatCurrency($inv['discount_amount']):'—' ?></td>
            <td class="amt"><?= Helper::formatCurrency($inv['total']) ?></td>
            <td class="num" style="color:#059669;font-weight:600;"><?= Helper::formatCurrency($inv['paid_amount']) ?></td>
            <td class="<?= $inv['balance']>0?'bal':'num' ?>"><?= Helper::formatCurrency($inv['balance']) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$invoices_detail): ?>
          <tr><td colspan="13"><div class="empty-rpt"><div class="empty-rpt-icon">🧾</div>No invoices in this range.</div></td></tr>
          <?php endif; ?>
        </tbody>
        <?php if ($invoices_detail): ?>
        <tfoot><tr>
          <td colspan="7">TOTAL (<?= count($invoices_detail) ?>)</td>
          <td class="num"><?= Helper::formatCurrency(array_sum(array_column($invoices_detail,'subtotal'))) ?></td>
          <td class="num"><?= Helper::formatCurrency(array_sum(array_column($invoices_detail,'tax_amount'))) ?></td>
          <td class="num" style="color:#059669;"><?= Helper::formatCurrency(array_sum(array_column($invoices_detail,'discount_amount'))) ?></td>
          <td class="amt"><?= Helper::formatCurrency(array_sum(array_column($invoices_detail,'total'))) ?></td>
          <td class="num" style="color:#059669;font-weight:700;"><?= Helper::formatCurrency(array_sum(array_column($invoices_detail,'paid_amount'))) ?></td>
          <td class="bal"><?= Helper::formatCurrency(array_sum(array_column($invoices_detail,'balance'))) ?></td>
        </tr></tfoot>
        <?php endif; ?>
      </table>
    </div>
  </div>
</div>

<!-- ═══ TAB: OUTSTANDING ═══ -->
<div class="rpt-pane <?= $active_tab==='outstanding'?'active':'' ?>" id="rpt-outstanding">
  <div class="export-bar d-print-none">
    <a class="export-btn" href="<?= BASE_URL ?>/modules/reports/export.php?type=outstanding&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&branch_id=<?= $branch_id ?>">↓ Export CSV</a>
    <button class="export-btn" onclick="window.print()">🖨 Print</button>
    <a class="export-btn" style="color:#25d366;border-color:#25d366;"
       href="https://wa.me/?text=<?= urlencode('VenuePro Outstanding Balance Report: Rs.'.number_format($kpi_outstanding,2).' pending across '.count($outstanding_list).' bookings as of '.date('d M Y').'.') ?>"
       target="_blank">📲 WhatsApp</a>
  </div>

  <div class="rpt-section">
    <div class="rpt-section-hd">
      <h4 class="rpt-section-title">Outstanding Balances (<?= count($outstanding_list) ?> bookings)</h4>
      <span class="rpt-section-total" style="color:#dc2626;"><?= Helper::formatCurrency($kpi_outstanding) ?></span>
    </div>
    <div style="overflow-x:auto;">
      <table class="rpt-table">
        <thead><tr><th>Booking</th><th>Event Date</th><th>Customer</th><th>Hall</th><th>Status</th><th>Due Date</th><th class="num">Total</th><th class="num">Paid</th><th class="num">Balance</th></tr></thead>
        <tbody>
          <?php foreach ($outstanding_list as $ol):
            $overdue = $ol['due_date'] && $ol['due_date'] < date('Y-m-d');
          ?>
          <tr <?= $overdue ? 'style="background:#fff5f5;"' : '' ?>>
            <td><a href="<?= BASE_URL ?>/modules/bookings/view.php?id=<?= $ol['id'] ?>" class="ref"><?= Helper::sanitize($ol['booking_ref']) ?></a></td>
            <td style="white-space:nowrap;"><?= date('d M Y',strtotime($ol['event_date'])) ?></td>
            <td>
              <div style="font-weight:600;"><?= Helper::sanitize($ol['customer_name']) ?></div>
              <div style="font-size:.7rem;color:#9ca3af;"><?= Helper::sanitize($ol['mobile']) ?></div>
            </td>
            <td style="font-size:.75rem;"><?= Helper::sanitize($ol['hall_name'] ?? '—') ?></td>
            <td><?= Helper::statusBadge($ol['status']) ?></td>
            <td style="font-size:.75rem;<?= $overdue?'color:#dc2626;font-weight:700;':'' ?>">
              <?= $ol['due_date'] ? date('d M Y',strtotime($ol['due_date'])) : '—' ?>
              <?= $overdue ? '<span style="font-size:.65rem;background:#fee2e2;color:#dc2626;padding:.1rem .35rem;border-radius:4px;margin-left:.3rem;">OVERDUE</span>' : '' ?>
            </td>
            <td class="amt"><?= Helper::formatCurrency($ol['final_amount']) ?></td>
            <td class="num" style="color:#059669;font-weight:600;"><?= Helper::formatCurrency($ol['paid_amount']) ?></td>
            <td class="bal"><?= Helper::formatCurrency($ol['balance_amount']) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$outstanding_list): ?>
          <tr><td colspan="9"><div class="empty-rpt"><div class="empty-rpt-icon">✅</div>No outstanding balances. All clear!</div></td></tr>
          <?php endif; ?>
        </tbody>
        <?php if ($outstanding_list): ?>
        <tfoot><tr>
          <td colspan="6">TOTAL (<?= count($outstanding_list) ?>)</td>
          <td class="amt"><?= Helper::formatCurrency(array_sum(array_column($outstanding_list,'final_amount'))) ?></td>
          <td class="num" style="color:#059669;font-weight:700;"><?= Helper::formatCurrency(array_sum(array_column($outstanding_list,'paid_amount'))) ?></td>
          <td class="bal"><?= Helper::formatCurrency(array_sum(array_column($outstanding_list,'balance_amount'))) ?></td>
        </tr></tfoot>
        <?php endif; ?>
      </table>
    </div>
  </div>
</div>

<!-- ═══ TAB: CUSTOMERS ═══ -->
<div class="rpt-pane <?= $active_tab==='customers'?'active':'' ?>" id="rpt-customers">
  <div class="export-bar d-print-none">
    <a class="export-btn" href="<?= BASE_URL ?>/modules/reports/export.php?type=customers&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>&branch_id=<?= $branch_id ?>">↓ Export CSV</a>
    <button class="export-btn" onclick="window.print()">🖨 Print</button>
  </div>

  <div class="rpt-section">
    <div class="rpt-section-hd">
      <h4 class="rpt-section-title">Customers with Bookings in Period (<?= count($customers_detail) ?>)</h4>
      <span style="font-size:.75rem;color:#9ca3af;">+<?= $kpi_new_cust ?> new customers</span>
    </div>
    <div style="overflow-x:auto;">
      <table class="rpt-table">
        <thead><tr><th>#</th><th>Customer</th><th>Mobile</th><th>Email</th><th class="num">Bookings</th><th class="num">Total Value</th><th class="num">Paid</th><th class="num">Balance</th><th>First Event</th><th>Last Event</th></tr></thead>
        <tbody>
          <?php foreach ($customers_detail as $i=>$cd): ?>
          <tr>
            <td style="color:#9ca3af;font-weight:700;font-size:.76rem;"><?= $i+1 ?></td>
            <td style="font-weight:700;"><?= Helper::sanitize($cd['name']) ?></td>
            <td style="font-size:.75rem;"><?= Helper::sanitize($cd['mobile']) ?></td>
            <td style="font-size:.73rem;color:#6b7280;"><?= Helper::sanitize($cd['email'] ?? '—') ?></td>
            <td class="num"><?= $cd['bookings'] ?></td>
            <td class="amt"><?= Helper::formatCurrency($cd['total_value']) ?></td>
            <td class="num" style="color:#059669;font-weight:600;"><?= Helper::formatCurrency($cd['paid']) ?></td>
            <td class="<?= $cd['balance']>0?'bal':'num' ?>"><?= Helper::formatCurrency($cd['balance']) ?></td>
            <td style="font-size:.75rem;"><?= $cd['first_event'] ? date('d M Y',strtotime($cd['first_event'])) : '—' ?></td>
            <td style="font-size:.75rem;"><?= $cd['last_event'] ? date('d M Y',strtotime($cd['last_event'])) : '—' ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$customers_detail): ?>
          <tr><td colspan="10"><div class="empty-rpt"><div class="empty-rpt-icon">👥</div>No customers with bookings in this range.</div></td></tr>
          <?php endif; ?>
        </tbody>
        <?php if ($customers_detail): ?>
        <tfoot><tr>
          <td colspan="4">TOTAL</td>
          <td class="num"><?= array_sum(array_column($customers_detail,'bookings')) ?></td>
          <td class="amt"><?= Helper::formatCurrency(array_sum(array_column($customers_detail,'total_value'))) ?></td>
          <td class="num" style="color:#059669;font-weight:700;"><?= Helper::formatCurrency(array_sum(array_column($customers_detail,'paid'))) ?></td>
          <td class="bal"><?= Helper::formatCurrency(array_sum(array_column($customers_detail,'balance'))) ?></td>
          <td colspan="2"></td>
        </tr></tfoot>
        <?php endif; ?>
      </table>
    </div>
  </div>
</div>

<script>
function rptTab(name) {
  document.querySelectorAll('.rpt-tab').forEach(b => b.classList.remove('active'));
  document.querySelectorAll('.rpt-pane').forEach(p => p.classList.remove('active'));
  document.querySelector('.rpt-tab[onclick="rptTab(\''+name+'\')"]').classList.add('active');
  document.getElementById('rpt-'+name).classList.add('active');
  const u = new URL(window.location.href); u.searchParams.set('tab', name); history.replaceState(null,'',u);
}
</script>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
