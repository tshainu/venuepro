<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
$db = Database::getInstance();
$cu = Auth::currentUser();

$search     = trim($_GET['search'] ?? '');
$status     = $_GET['status'] ?? '';
$source     = $_GET['source'] ?? '';
$page       = max(1,(int)($_GET['page'] ?? 1));

$where = ['1=1']; $params = [];
if ($cu['branch_id']) { $where[] = 'i.branch_id=?'; $params[] = $cu['branch_id']; }
if ($search) { $where[] = '(i.name LIKE ? OR i.mobile LIKE ? OR i.inquiry_ref LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($status) { $where[] = 'i.status=?'; $params[] = $status; }
if ($source) { $where[] = 'i.source=?'; $params[] = $source; }

$wstr  = implode(' AND ', $where);
$total = $db->fetchOne("SELECT COUNT(*) as cnt FROM inquiries i WHERE $wstr", $params)['cnt'];
$pg    = Helper::paginate($total, $page, 20);

$inquiries = $db->fetchAll(
    "SELECT i.*, h.name as hall_name, u.name as assigned_name
     FROM inquiries i
     LEFT JOIN halls h ON i.hall_id=h.id
     LEFT JOIN users u ON i.assigned_to=u.id
     WHERE $wstr ORDER BY i.created_at DESC LIMIT ? OFFSET ?",
    array_merge($params, [$pg['per_page'], $pg['offset']])
);

// Status counts
$statusStats = $db->fetchAll(
    "SELECT status, COUNT(*) as cnt FROM inquiries i WHERE 1 " .
    ($cu['branch_id'] ? "AND i.branch_id=".(int)$cu['branch_id'] : "") .
    " GROUP BY status"
);
$stMap = []; foreach ($statusStats as $s) $stMap[$s['status']] = (int)$s['cnt'];
$totalAll = array_sum($stMap);

$halls = $db->fetchAll("SELECT id,name FROM halls WHERE is_active=1".($cu['branch_id']?" AND branch_id=".(int)$cu['branch_id']:"")." ORDER BY name");
$users = $db->fetchAll("SELECT id,name FROM users WHERE is_active=1 ORDER BY name");
$branches = $db->fetchAll("SELECT id,name FROM branches WHERE is_active=1");

$pageTitle   = 'Inquiries';
$breadcrumbs = [['label' => 'Inquiries']];
require_once ROOT_PATH . '/includes/header.php';

// Handle create via modal AJAX
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_action']??'')==='' ) {
    // handled in create.php
}
?>
<style>
/* ── Inquiry page ───────────────────────────────────── */
.inq-stat-strip { display:flex; gap:.6rem; flex-wrap:wrap; margin-bottom:1.2rem; }
.inq-chip {
  display:flex; align-items:center; gap:.55rem;
  background:#fff; border:1.5px solid #edf0f8; border-radius:12px;
  padding:.55rem .9rem; flex:1; min-width:120px; cursor:pointer;
  text-decoration:none; transition:all .15s; color:inherit;
}
.inq-chip:hover { border-color:var(--ic,#0c1a35); transform:translateY(-1px); box-shadow:0 4px 14px rgba(12,26,53,.1); }
.inq-chip.active { border-color:var(--ic,#0c1a35); background:var(--ibg,#f0f3fa); }
.inq-chip-dot { width:9px; height:9px; border-radius:50%; background:var(--ic,#6b7280); flex-shrink:0; }
.inq-chip-val { font-size:1rem; font-weight:800; color:#0c1a35; line-height:1; }
.inq-chip-lbl { font-size:.65rem; color:#9ca3af; font-weight:700; text-transform:uppercase; letter-spacing:.05em; margin-top:.1rem; }
/* Source pill */
.src-pill { display:inline-flex; align-items:center; gap:.3rem; font-size:.7rem; font-weight:700; padding:.18rem .55rem; border-radius:99px; }
.src-walkin   { background:#ecfdf5; color:#065f46; }
.src-phone    { background:#eff6ff; color:#1e40af; }
.src-whatsapp { background:#f0fdf4; color:#15803d; }
.src-website  { background:#fdf4ff; color:#7e22ce; }
.src-referral { background:#fff7ed; color:#c2410c; }
/* Status badge */
.inq-status { font-size:.7rem; font-weight:700; padding:.22rem .6rem; border-radius:7px; display:inline-block; }
.s-new       { background:#eff6ff; color:#1d4ed8; }
.s-contacted { background:#fefce8; color:#92400e; }
.s-quoted    { background:#fdf4ff; color:#7e22ce; }
.s-converted { background:#ecfdf5; color:#065f46; }
.s-lost      { background:#fef2f2; color:#991b1b; }
/* Follow-up badge */
.fu-badge { font-size:.67rem; font-weight:700; padding:.15rem .5rem; border-radius:6px; }
/* Modal */
.vp-modal-overlay{display:none;position:fixed;inset:0;z-index:1060;background:rgba(12,26,53,.55);backdrop-filter:blur(3px);align-items:center;justify-content:center;}
.vp-modal-overlay.show{display:flex;}
.vp-modal{background:#fff;border-radius:20px;width:100%;max-width:560px;margin:1rem;box-shadow:0 24px 80px rgba(12,26,53,.28);overflow:hidden;animation:modalIn .25s cubic-bezier(.34,1.56,.64,1);}
@keyframes modalIn{from{transform:scale(.93) translateY(20px);opacity:0;}to{transform:scale(1) translateY(0);opacity:1;}}
.vp-modal-header{display:flex;align-items:center;justify-content:space-between;padding:1.3rem 1.5rem;background:linear-gradient(130deg,#0c1a35,#1a3060);border-bottom:2px solid rgba(201,168,76,.2);}
.vp-modal-title{font-size:1rem;font-weight:800;color:#fff;}
.vp-modal-sub{font-size:.72rem;color:rgba(255,255,255,.5);margin-top:.1rem;}
.vp-modal-close{width:30px;height:30px;border-radius:50%;background:rgba(255,255,255,.12);border:none;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1.1rem;}
.vp-modal-close:hover{background:rgba(255,255,255,.22);}
.vp-modal-body{padding:1.4rem 1.5rem;max-height:70vh;overflow-y:auto;}
.vp-modal-footer{padding:.9rem 1.5rem;background:#f8f9fc;border-top:1px solid #edf0f8;display:flex;gap:.5rem;justify-content:flex-end;}
.fl{font-size:.73rem;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.3rem;}
.fl.req::after{content:' *';color:#dc2626;}
</style>

<div class="vp-page-header">
  <div>
    <h1 class="vp-page-title">📋 Inquiries</h1>
    <div class="vp-page-sub"><?= $totalAll ?> total inquiries</div>
  </div>
  <button type="button" class="btn btn-vp-gold" onclick="openInqModal()">
    <svg xmlns="http://www.w3.org/2000/svg" class="icon me-1" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
    New Inquiry
  </button>
</div>

<!-- Status strip -->
<div class="inq-stat-strip">
  <?php
  $chips = [
    ['key'=>'',          'label'=>'All',       'color'=>'#0c1a35','bg'=>'#f0f3fa'],
    ['key'=>'new',       'label'=>'New',       'color'=>'#2563eb','bg'=>'#eff6ff'],
    ['key'=>'contacted', 'label'=>'Contacted', 'color'=>'#d97706','bg'=>'#fefce8'],
    ['key'=>'quoted',    'label'=>'Quoted',    'color'=>'#7c3aed','bg'=>'#fdf4ff'],
    ['key'=>'converted', 'label'=>'Converted', 'color'=>'#059669','bg'=>'#ecfdf5'],
    ['key'=>'lost',      'label'=>'Lost',      'color'=>'#dc2626','bg'=>'#fef2f2'],
  ];
  foreach ($chips as $c):
    $cnt = $c['key'] === '' ? $totalAll : ($stMap[$c['key']] ?? 0);
  ?>
  <a href="?status=<?= $c['key'] ?>&search=<?= urlencode($search) ?>&source=<?= $source ?>"
     class="inq-chip <?= $status===$c['key']?'active':'' ?>"
     style="--ic:<?= $c['color'] ?>;--ibg:<?= $c['bg'] ?>">
    <div class="inq-chip-dot"></div>
    <div>
      <div class="inq-chip-val"><?= $cnt ?></div>
      <div class="inq-chip-lbl"><?= $c['label'] ?></div>
    </div>
  </a>
  <?php endforeach; ?>
</div>

<!-- Filter bar -->
<div class="vp-filter-bar mb-3">
  <form method="get" class="d-flex gap-2 flex-wrap align-items-end">
    <input type="text" name="search" class="form-control" placeholder="Name / Mobile / Ref..." value="<?= Helper::sanitize($search) ?>" style="max-width:220px;">
    <select name="status" class="form-select" style="max-width:140px;">
      <option value="">All Status</option>
      <?php foreach (['new','contacted','quoted','converted','lost'] as $s): ?>
      <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="source" class="form-select" style="max-width:150px;">
      <option value="">All Sources</option>
      <?php foreach (['walk-in','phone','whatsapp','website','referral'] as $src): ?>
      <option value="<?= $src ?>" <?= $source===$src?'selected':'' ?>><?= ucfirst($src) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-vp-primary">Filter</button>
    <a href="<?= BASE_URL ?>/modules/inquiries/index.php" class="btn btn-vp-outline">Clear</a>
  </form>
</div>

<div class="card vp-card">
  <div class="table-responsive">
    <table class="table table-vcenter vp-table mb-0">
      <thead>
        <tr>
          <th>Ref</th><th>Name</th><th>Mobile</th><th>Event</th>
          <th>Date</th><th>Hall</th><th>Source</th><th>Status</th>
          <th>Follow-up</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($inquiries): foreach ($inquiries as $inq):
          $fuClass = '';
          if ($inq['follow_up_date']) {
            $fuDiff = (strtotime($inq['follow_up_date']) - strtotime(date('Y-m-d'))) / 86400;
            $fuClass = $fuDiff < 0 ? 'background:#fef2f2;color:#dc2626;' : ($fuDiff <= 1 ? 'background:#fefce8;color:#d97706;' : 'background:#f0fdf4;color:#059669;');
          }
        ?>
        <tr>
          <td><a href="<?= BASE_URL ?>/modules/inquiries/view.php?id=<?= $inq['id'] ?>" class="vp-ref-chip"><?= Helper::sanitize($inq['inquiry_ref']) ?></a></td>
          <td class="fw-600"><?= Helper::sanitize($inq['name']) ?></td>
          <td><?= Helper::sanitize($inq['mobile']) ?></td>
          <td><?= Helper::sanitize($inq['event_type']??'—') ?></td>
          <td><?= $inq['event_date'] ? Helper::formatDate($inq['event_date']) : '—' ?></td>
          <td><?= Helper::sanitize($inq['hall_name']??'—') ?></td>
          <td>
            <?php if ($inq['source']): $sc = str_replace('-','',$inq['source']); ?>
            <span class="src-pill src-<?= $sc ?>"><?= ucfirst($inq['source']) ?></span>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td>
            <?php
            $sCls = ['new'=>'s-new','contacted'=>'s-contacted','quoted'=>'s-quoted','converted'=>'s-converted','lost'=>'s-lost'];
            $sLbl = ['new'=>'New','contacted'=>'Contacted','quoted'=>'Quoted','converted'=>'Booking!','lost'=>'Lost'];
            ?>
            <span class="inq-status <?= $sCls[$inq['status']]??'s-new' ?>"><?= $sLbl[$inq['status']]??ucfirst($inq['status']) ?></span>
          </td>
          <td>
            <?php if ($inq['follow_up_date']): ?>
            <span class="fu-badge" style="<?= $fuClass ?>"><?= Helper::formatDate($inq['follow_up_date']) ?></span>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td>
            <a href="<?= BASE_URL ?>/modules/inquiries/view.php?id=<?= $inq['id'] ?>" class="btn btn-vp-outline btn-sm">View</a>
            <?php if ($inq['status'] !== 'converted'): ?>
            <a href="<?= BASE_URL ?>/modules/bookings/create.php?inquiry_id=<?= $inq['id'] ?>" class="btn btn-vp-gold btn-sm">→ Book</a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr>
          <td colspan="10">
            <div class="empty-state">
              <div class="empty-icon">📋</div>
              <div class="empty-text">No inquiries found. Start by clicking "New Inquiry".</div>
            </div>
          </td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pg['pages'] > 1): ?>
  <div class="card-footer d-flex align-items-center">
    <p class="m-0 text-secondary" style="font-size:.8rem;">Showing <?= count($inquiries) ?> of <?= $total ?></p>
    <ul class="pagination ms-auto mb-0">
      <?php for ($i=1;$i<=$pg['pages'];$i++): ?>
      <li class="page-item <?= $i==$page?'active':'' ?>"><a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>&source=<?= $source ?>"><?= $i ?></a></li>
      <?php endfor; ?>
    </ul>
  </div>
  <?php endif; ?>
</div>

<!-- ═══ NEW INQUIRY MODAL ═══ -->
<div class="vp-modal-overlay" id="inqModal" onclick="if(event.target===this)closeInqModal()">
  <div class="vp-modal">
    <div class="vp-modal-header">
      <div>
        <div class="vp-modal-title">📋 New Inquiry</div>
        <div class="vp-modal-sub">Capture lead details quickly</div>
      </div>
      <button class="vp-modal-close" onclick="closeInqModal()">×</button>
    </div>
    <form method="post" action="<?= BASE_URL ?>/modules/inquiries/create.php">
    <div class="vp-modal-body">
      <div class="row g-3">
        <div class="col-md-6">
          <div class="fl req">Full Name</div>
          <input type="text" name="name" class="form-control" placeholder="Customer name" required>
        </div>
        <div class="col-md-6">
          <div class="fl req">Mobile</div>
          <input type="tel" name="mobile" class="form-control" placeholder="07X XXX XXXX" required>
        </div>
        <div class="col-md-6">
          <div class="fl">Email</div>
          <input type="email" name="email" class="form-control" placeholder="optional">
        </div>
        <div class="col-md-6">
          <div class="fl">Event Type</div>
          <select name="event_type" class="form-select">
            <option value="">— Select —</option>
            <?php foreach (['Wedding','Engagement','Wedding Reception','Birthday','Corporate','Conference','Get Together','Other'] as $et): ?>
            <option value="<?= $et ?>"><?= $et ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <div class="fl">Expected Event Date</div>
          <input type="date" name="event_date" class="form-control">
        </div>
        <div class="col-md-6">
          <div class="fl">Expected Guests</div>
          <input type="number" name="guest_count" class="form-control" min="0" placeholder="e.g. 200">
        </div>
        <div class="col-md-6">
          <div class="fl">Preferred Hall</div>
          <select name="hall_id" class="form-select">
            <option value="">— Any —</option>
            <?php foreach ($halls as $h): ?>
            <option value="<?= $h['id'] ?>"><?= Helper::sanitize($h['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <div class="fl">Source</div>
          <select name="source" class="form-select">
            <option value="">— How did they find us? —</option>
            <?php foreach (['walk-in','phone','whatsapp','website','referral'] as $src): ?>
            <option value="<?= $src ?>"><?= ucfirst($src) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <div class="fl">Follow-up Date</div>
          <input type="date" name="follow_up_date" class="form-control" value="<?= date('Y-m-d', strtotime('+2 days')) ?>">
        </div>
        <div class="col-12">
          <div class="fl">Notes</div>
          <textarea name="notes" class="form-control" rows="2" placeholder="Any special requirements or notes..."></textarea>
        </div>
      </div>
    </div>
    <div class="vp-modal-footer">
      <button type="button" class="btn btn-vp-outline" onclick="closeInqModal()">Cancel</button>
      <button type="submit" class="btn btn-vp-gold">Save Inquiry</button>
    </div>
    </form>
  </div>
</div>

<script>
function openInqModal()  { document.getElementById('inqModal').classList.add('show'); }
function closeInqModal() { document.getElementById('inqModal').classList.remove('show'); }
document.addEventListener('keydown', e => { if (e.key==='Escape') closeInqModal(); });
</script>
<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
