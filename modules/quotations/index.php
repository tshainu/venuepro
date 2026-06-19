<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
$db = Database::getInstance();
$cu = Auth::currentUser();

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$page   = max(1,(int)($_GET['page'] ?? 1));

$where = ['1=1']; $params = [];
if ($cu['branch_id']) { $where[] = 'q.branch_id=?'; $params[] = $cu['branch_id']; }
if ($search) { $where[] = '(q.quotation_ref LIKE ? OR c.name LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($status) { $where[] = 'q.status=?'; $params[] = $status; }

$wstr  = implode(' AND ', $where);
$total = $db->fetchOne("SELECT COUNT(*) as cnt FROM quotations q LEFT JOIN customers c ON q.customer_id=c.id WHERE $wstr", $params)['cnt'];
$pg    = Helper::paginate($total, $page);
$quotes = $db->fetchAll(
    "SELECT q.*, c.name as customer_name, br.name as branch_name
     FROM quotations q
     LEFT JOIN customers c ON q.customer_id=c.id
     LEFT JOIN branches br ON q.branch_id=br.id
     WHERE $wstr ORDER BY q.created_at DESC LIMIT ? OFFSET ?",
    array_merge($params, [$pg['per_page'], $pg['offset']])
);

// Summary stats
$statsAll = $db->fetchAll("SELECT q.status, COUNT(*) as cnt, COALESCE(SUM(q.total),0) as total FROM quotations q WHERE 1 " . ($cu['branch_id'] ? "AND q.branch_id=".(int)$cu['branch_id'] : "") . " GROUP BY q.status");
$statMap = []; foreach ($statsAll as $s) $statMap[$s['status']] = $s;

$pageTitle  = 'Quotations';
$breadcrumbs = [['label' => 'Quotations']];
require_once ROOT_PATH . '/includes/header.php';
?>

<style>
/* Quotations modern styles */
.vp-stat-strip { display:flex; gap:.75rem; flex-wrap:wrap; margin-bottom:1.25rem; }
.vp-stat-chip {
  display:flex; align-items:center; gap:.6rem;
  background:#fff; border:1.5px solid #edf0f8; border-radius:12px;
  padding:.6rem 1rem; flex:1; min-width:150px;
  cursor:pointer; transition:all .15s; text-decoration:none;
}
.vp-stat-chip:hover { border-color:#0c1a35; transform:translateY(-1px); box-shadow:0 4px 14px rgba(12,26,53,.1); }
.vp-stat-chip.active { border-color:var(--sc-color,#0c1a35); background:var(--sc-bg,#f0f3fa); }
.vp-stat-dot { width:10px; height:10px; border-radius:50%; background:var(--sc-color,#0c1a35); flex-shrink:0; }
.vp-stat-val { font-size:1.1rem; font-weight:800; color:#0c1a35; }
.vp-stat-lbl { font-size:.68rem; color:#9ca3af; font-weight:600; text-transform:uppercase; letter-spacing:.05em; }
.vp-ref-chip {
  display:inline-flex; align-items:center; gap:.3rem;
  background:#f0f3fa; border-radius:6px; padding:.2rem .55rem;
  font-size:.78rem; font-weight:700; color:#0c1a35;
  text-decoration:none; transition:background .15s;
}
.vp-ref-chip:hover { background:#e0e7f5; color:#0c1a35; }

/* Modal (shared style, same as addons) */
.vp-modal-overlay {
  display:none; position:fixed; inset:0; z-index:1060;
  background:rgba(12,26,53,.55); backdrop-filter:blur(3px);
  align-items:center; justify-content:center;
}
.vp-modal-overlay.show { display:flex; }
.vp-modal {
  background:#fff; border-radius:20px;
  width:100%; max-width:580px; margin:1rem;
  box-shadow:0 24px 80px rgba(12,26,53,.28); overflow:hidden;
  animation:modalIn .25s cubic-bezier(.34,1.56,.64,1);
  max-height:90vh; overflow-y:auto;
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
  position:sticky; top:0; z-index:2;
}
.vp-modal-title { font-size:1rem; font-weight:800; color:#fff; }
.vp-modal-subtitle { font-size:.72rem; color:rgba(255,255,255,.55); margin-top:.15rem; }
.vp-modal-close {
  width:30px; height:30px; border-radius:50%;
  background:rgba(255,255,255,.12); border:none; color:#fff;
  cursor:pointer; display:flex; align-items:center; justify-content:center;
  font-size:1.1rem; line-height:1; transition:background .15s; flex-shrink:0;
}
.vp-modal-close:hover { background:rgba(255,255,255,.25); }
.vp-modal-body { padding:1.6rem; }
.vp-modal-footer {
  padding:1rem 1.6rem; background:#f8f9fc;
  border-top:1px solid #edf0f8;
  display:flex; align-items:center; justify-content:flex-end; gap:.6rem;
  position:sticky; bottom:0; z-index:2;
}
.vp-field-label {
  font-size:.73rem; font-weight:700; color:#374151;
  text-transform:uppercase; letter-spacing:.05em; margin-bottom:.35rem;
}
.vp-field-label.required::after { content:' *'; color:#dc2626; }
</style>

<div class="vp-page-header">
  <div>
    <h1 class="vp-page-title">📄 <?= Lang::t('quotations') ?></h1>
    <div class="vp-page-sub"><?= $total ?> quotation<?= $total!==1?'s':'' ?></div>
  </div>
  <button type="button" class="btn btn-vp-gold" onclick="openQuoteModal()">
    <svg xmlns="http://www.w3.org/2000/svg" class="icon me-1" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5l0 14"/><path d="M5 12l14 0"/></svg>
    New Quotation
  </button>
</div>

<!-- Status strip stats -->
<div class="vp-stat-strip">
  <?php
  $chips = [
    ['key'=>'draft',    'label'=>'Draft',    'color'=>'#6b7280','bg'=>'#f3f4f6'],
    ['key'=>'sent',     'label'=>'Sent',     'color'=>'#2563eb','bg'=>'#eff6ff'],
    ['key'=>'accepted', 'label'=>'Accepted', 'color'=>'#059669','bg'=>'#ecfdf5'],
    ['key'=>'rejected', 'label'=>'Rejected', 'color'=>'#dc2626','bg'=>'#fef2f2'],
    ['key'=>'expired',  'label'=>'Expired',  'color'=>'#d97706','bg'=>'#fffbeb'],
  ];
  foreach ($chips as $chip):
    $cnt = $statMap[$chip['key']]['cnt'] ?? 0;
    $tot = $statMap[$chip['key']]['total'] ?? 0;
  ?>
  <a href="?status=<?= $chip['key'] ?>&search=<?= urlencode($search) ?>"
     class="vp-stat-chip <?= $status===$chip['key']?'active':'' ?>"
     style="--sc-color:<?= $chip['color'] ?>;--sc-bg:<?= $chip['bg'] ?>">
    <div class="vp-stat-dot"></div>
    <div>
      <div class="vp-stat-val"><?= $cnt ?></div>
      <div class="vp-stat-lbl"><?= $chip['label'] ?></div>
    </div>
    <?php if ($tot > 0): ?>
    <div style="margin-left:auto;font-size:.7rem;color:#9ca3af;font-weight:600;"><?= Helper::formatCurrency($tot) ?></div>
    <?php endif; ?>
  </a>
  <?php endforeach; ?>
</div>

<!-- Filter Bar -->
<div class="vp-filter-bar mb-3">
  <form method="get" class="d-flex gap-2 flex-wrap align-items-end">
    <input type="text" name="search" class="form-control" placeholder="Ref / Customer..." value="<?= Helper::sanitize($search) ?>" style="max-width:240px;">
    <select name="status" class="form-select" style="max-width:160px;">
      <option value="">All Status</option>
      <?php foreach (['draft','sent','accepted','rejected','expired'] as $s): ?>
      <option value="<?= $s ?>" <?= $status==$s?'selected':'' ?>><?= ucfirst($s) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-vp-primary">Filter</button>
    <a href="<?= BASE_URL ?>/modules/quotations/index.php" class="btn btn-vp-outline">Clear</a>
  </form>
</div>

<div class="card vp-card">
  <div class="table-responsive">
    <table class="table table-vcenter vp-table mb-0">
      <thead>
        <tr><th>Ref</th><th>Customer</th><th>Branch</th><th>Valid Until</th><th>Total</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php if ($quotes): foreach ($quotes as $q): ?>
        <tr>
          <td><a href="<?= BASE_URL ?>/modules/quotations/view.php?id=<?= $q['id'] ?>" class="vp-ref-chip"><?= Helper::sanitize($q['quotation_ref']) ?></a></td>
          <td class="fw-600"><?= Helper::sanitize($q['customer_name']) ?></td>
          <td><?= Helper::sanitize($q['branch_name']) ?></td>
          <td><?= Helper::formatDate($q['valid_until']) ?></td>
          <td class="fw-700"><?= Helper::formatCurrency($q['total']) ?></td>
          <td><?= Helper::statusBadge($q['status']) ?></td>
          <td>
            <a href="<?= BASE_URL ?>/modules/quotations/view.php?id=<?= $q['id'] ?>" class="btn btn-vp-outline btn-sm">View</a>
            <a href="<?= BASE_URL ?>/modules/quotations/pdf.php?id=<?= $q['id'] ?>" class="btn btn-vp-primary btn-sm" target="_blank">PDF</a>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr>
          <td colspan="7">
            <div class="empty-state"><div class="empty-icon">📄</div><div class="empty-text">No quotations found.</div></div>
          </td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pg['pages'] > 1): ?>
  <div class="card-footer d-flex align-items-center">
    <p class="m-0 text-secondary" style="font-size:.8rem;">Showing <?= count($quotes) ?> of <?= $total ?></p>
    <ul class="pagination ms-auto mb-0">
      <?php for ($i=1;$i<=$pg['pages'];$i++): ?>
      <li class="page-item <?= $i==$page?'active':'' ?>"><a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>"><?= $i ?></a></li>
      <?php endfor; ?>
    </ul>
  </div>
  <?php endif; ?>
</div>

<!-- ═══════ QUICK NEW QUOTATION MODAL ═══════ -->
<?php
$customers = $db->fetchAll("SELECT id,name FROM customers ORDER BY name");
$branches2 = $db->fetchAll("SELECT id,name FROM branches WHERE is_active=1");
?>
<div class="vp-modal-overlay" id="quoteModal" onclick="if(event.target===this)closeQuoteModal()">
  <div class="vp-modal">
    <div class="vp-modal-header">
      <div>
        <div class="vp-modal-title">📄 New Quotation</div>
        <div class="vp-modal-subtitle">Redirects to full quotation builder</div>
      </div>
      <button class="vp-modal-close" onclick="closeQuoteModal()">×</button>
    </div>
    <div class="vp-modal-body">
      <div class="mb-3">
        <div class="vp-field-label required">Customer</div>
        <select id="qm_customer" class="form-select" required>
          <option value="">— Select customer —</option>
          <?php foreach ($customers as $c): ?>
          <option value="<?= $c['id'] ?>"><?= Helper::sanitize($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if (Auth::isSuperAdmin()): ?>
      <div class="mb-3">
        <div class="vp-field-label">Branch</div>
        <select id="qm_branch" class="form-select">
          <option value="">— Default —</option>
          <?php foreach ($branches2 as $b): ?>
          <option value="<?= $b['id'] ?>"><?= Helper::sanitize($b['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <div class="mb-3">
        <div class="vp-field-label">Valid Until</div>
        <input type="date" id="qm_valid" class="form-control" value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
      </div>
      <div class="alert alert-info" style="font-size:.8rem;background:#eff6ff;color:#1e40af;border:none;border-radius:10px;padding:.75rem 1rem;">
        <strong>Note:</strong> After clicking Create, you'll be taken to the full quotation form to add line items and details.
      </div>
    </div>
    <div class="vp-modal-footer">
      <button type="button" class="btn btn-vp-outline" onclick="closeQuoteModal()">Cancel</button>
      <button type="button" class="btn btn-vp-gold" onclick="goCreateQuote()">
        <svg xmlns="http://www.w3.org/2000/svg" class="icon me-1" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5l0 14"/><path d="M5 12l14 0"/></svg>
        Continue to Builder
      </button>
    </div>
  </div>
</div>

<script>
function openQuoteModal() { document.getElementById('quoteModal').classList.add('show'); }
function closeQuoteModal() { document.getElementById('quoteModal').classList.remove('show'); }
function goCreateQuote() {
  var cust = document.getElementById('qm_customer').value;
  if (!cust) { document.getElementById('qm_customer').focus(); return; }
  var branch = document.getElementById('qm_branch') ? document.getElementById('qm_branch').value : '';
  var valid  = document.getElementById('qm_valid').value;
  var url = '<?= BASE_URL ?>/modules/quotations/create.php?prefill_customer='+cust;
  if (branch) url += '&prefill_branch='+branch;
  if (valid)  url += '&prefill_valid='+encodeURIComponent(valid);
  window.location.href = url;
}
document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeQuoteModal(); });
</script>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
