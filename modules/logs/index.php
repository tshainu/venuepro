<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();

// Only hall_manager (and super_admin) can see logs. Manager role is explicitly excluded.
if (!Auth::hasRole(['super_admin', 'admin', 'hall_manager'])) {
    Helper::flash('error', 'Access denied.');
    Helper::redirect(BASE_URL . '/index.php');
}

$db = Database::getInstance();
$cu = Auth::currentUser();

// ── Filters ────────────────────────────────────────────────────────────────
$tab      = $_GET['tab']    ?? 'all';   // all | edited | deleted
$module   = $_GET['module'] ?? '';
$dateFrom = $_GET['from']   ?? '';
$dateTo   = $_GET['to']     ?? '';
$search   = trim($_GET['q'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 50;

// Map tab → action filter
$actionFilter = match($tab) {
    'edited'  => 'edit',
    'deleted' => 'delete',
    default   => null
};

// Build query
$where  = [];
$params = [];

// Branch scoping — hall_manager sees only their branch; super_admin sees all
if ($cu['branch_id'] && !Auth::isSuperAdmin()) {
    $where[]  = 'branch_id = ?';
    $params[] = $cu['branch_id'];
}

if ($actionFilter) {
    $where[]  = 'action = ?';
    $params[] = $actionFilter;
}
if ($module) {
    $where[]  = 'module = ?';
    $params[] = $module;
}
if ($dateFrom) {
    $where[]  = 'DATE(created_at) >= ?';
    $params[] = $dateFrom;
}
if ($dateTo) {
    $where[]  = 'DATE(created_at) <= ?';
    $params[] = $dateTo;
}
if ($search) {
    $where[]  = '(record_ref LIKE ? OR description LIKE ? OR user_name LIKE ?)';
    $s = "%$search%";
    $params[] = $s; $params[] = $s; $params[] = $s;
}

$whereSQL = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Count
$total = $db->fetchOne("SELECT COUNT(*) as c FROM activity_logs $whereSQL", $params)['c'] ?? 0;
$pages = max(1, (int)ceil($total / $perPage));
$page  = min($page, $pages);
$offset= ($page - 1) * $perPage;

// Fetch
$logs = $db->fetchAll(
    "SELECT * FROM activity_logs $whereSQL ORDER BY created_at DESC LIMIT $perPage OFFSET $offset",
    $params
);

// Distinct modules for filter dropdown
$modules = $db->fetchAll(
    "SELECT DISTINCT module FROM activity_logs" .
    ($cu['branch_id'] && !Auth::isSuperAdmin() ? " WHERE branch_id={$cu['branch_id']}" : "") .
    " ORDER BY module"
);

// Tab counts
$countBase = $cu['branch_id'] && !Auth::isSuperAdmin() ? "WHERE branch_id={$cu['branch_id']}" : "";
$cAll    = $db->fetchOne("SELECT COUNT(*) c FROM activity_logs $countBase")['c'] ?? 0;
$cEdit   = $db->fetchOne("SELECT COUNT(*) c FROM activity_logs " . ($countBase ? "$countBase AND" : "WHERE") . " action='edit'")['c'] ?? 0;
$cDelete = $db->fetchOne("SELECT COUNT(*) c FROM activity_logs " . ($countBase ? "$countBase AND" : "WHERE") . " action='delete'")['c'] ?? 0;

$pageTitle  = 'Activity Logs';
$breadcrumbs = [['label' => 'Activity Logs']];
require_once __DIR__ . '/../../includes/header.php';

function logRowClass(string $action): string {
    return match($action) {
        'delete' => 'log-row-delete',
        'edit'   => 'log-row-edit',
        default  => ''
    };
}
function actionBadge(string $action): string {
    return match($action) {
        'create' => '<span class="log-badge log-badge-create">Create</span>',
        'edit'   => '<span class="log-badge log-badge-edit">Edit</span>',
        'delete' => '<span class="log-badge log-badge-delete">Delete</span>',
        default  => '<span class="log-badge">' . htmlspecialchars($action) . '</span>',
    };
}
function buildQuery(array $overrides = []): string {
    $base = [
        'tab'    => $_GET['tab']    ?? 'all',
        'module' => $_GET['module'] ?? '',
        'from'   => $_GET['from']   ?? '',
        'to'     => $_GET['to']     ?? '',
        'q'      => $_GET['q']      ?? '',
        'page'   => $_GET['page']   ?? 1,
    ];
    return '?' . http_build_query(array_merge($base, $overrides));
}
?>

<style>
/* ── Logs page ───────────────────────────── */
.logs-card { background:#fff; border-radius:12px; box-shadow:0 2px 12px rgba(0,0,0,.06); border:1px solid #edf0f8; overflow:hidden; }

/* tabs */
.log-tabs { display:flex; gap:0; border-bottom:2px solid #edf0f8; background:#fafbfd; padding:0 1.5rem; }
.log-tab {
  display:flex; align-items:center; gap:.45rem;
  padding:.75rem 1.25rem;
  font-size:.83rem; font-weight:700; text-decoration:none;
  color:#6b7280; border-bottom:2px solid transparent; margin-bottom:-2px;
  transition:all .15s;
}
.log-tab:hover { color:#0c1a35; }
.log-tab.active { color:#0c1a35; border-bottom-color:#c9a84c; }
.log-tab-count { font-size:.72rem; background:#e5e7eb; color:#374151; border-radius:20px; padding:.08rem .45rem; font-weight:800; }
.log-tab.active .log-tab-count { background:#c9a84c20; color:#0c1a35; }

/* filters */
.log-filters { display:flex; gap:.75rem; align-items:flex-end; flex-wrap:wrap; padding:1.1rem 1.5rem; border-bottom:1px solid #f3f4f6; }
.log-filter-group { display:flex; flex-direction:column; gap:.3rem; }
.log-filter-label { font-size:.72rem; font-weight:700; color:#9ca3af; text-transform:uppercase; letter-spacing:.05em; }
.log-filter-control { padding:.45rem .75rem; border:1.5px solid #e5e7eb; border-radius:8px; font-size:.83rem; background:#fff; }
.log-filter-control:focus { border-color:#c9a84c; outline:none; }
.log-filter-btn { padding:.47rem 1.1rem; border-radius:8px; border:none; cursor:pointer; font-size:.83rem; font-weight:700; }
.log-filter-btn.primary { background:#0c1a35; color:#fff; }
.log-filter-btn.reset { background:#f3f4f6; color:#374151; text-decoration:none; display:inline-flex; align-items:center; }

/* table */
.log-table { width:100%; border-collapse:collapse; }
.log-table th { padding:.7rem 1rem; text-align:left; font-size:.72rem; font-weight:800; text-transform:uppercase; letter-spacing:.07em; color:#9ca3af; background:#fafbfd; border-bottom:1px solid #f0f1f5; white-space:nowrap; }
.log-table td { padding:.7rem 1rem; font-size:.83rem; border-bottom:1px solid #f7f8fa; vertical-align:middle; }
.log-table tr:last-child td { border-bottom:none; }
.log-table tr:hover td { background:#fafbfe; }

/* row colors */
.log-row-delete td { background:#fff5f5 !important; }
.log-row-delete:hover td { background:#fee8e8 !important; }
.log-row-edit td { background:#fffbec !important; }
.log-row-edit:hover td { background:#fff5d6 !important; }

/* badges */
.log-badge { display:inline-block; padding:.18rem .6rem; border-radius:6px; font-size:.72rem; font-weight:800; text-transform:uppercase; letter-spacing:.04em; }
.log-badge-create { background:#dcfce7; color:#166534; }
.log-badge-edit   { background:#fef9c3; color:#854d0e; }
.log-badge-delete { background:#fee2e2; color:#991b1b; }

/* module chip */
.log-module { display:inline-block; padding:.15rem .55rem; border-radius:20px; font-size:.72rem; font-weight:700; background:#e0e7ff; color:#3730a3; text-transform:capitalize; }

/* diff button */
.log-diff-btn { padding:.25rem .65rem; border-radius:6px; border:1.5px solid #e5e7eb; background:#fff; font-size:.75rem; font-weight:700; color:#374151; cursor:pointer; transition:all .15s; }
.log-diff-btn:hover { border-color:#c9a84c; color:#0c1a35; }

/* diff modal */
.diff-modal-backdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:2000; align-items:center; justify-content:center; }
.diff-modal-backdrop.open { display:flex; }
.diff-modal { background:#fff; border-radius:14px; width:90%; max-width:720px; max-height:85vh; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 20px 60px rgba(0,0,0,.25); }
.diff-modal-head { padding:1.1rem 1.4rem; border-bottom:1px solid #f0f1f5; display:flex; align-items:center; justify-content:space-between; }
.diff-modal-head h3 { margin:0; font-size:1rem; font-weight:800; color:#0c1a35; }
.diff-modal-close { background:none; border:none; font-size:1.4rem; cursor:pointer; color:#9ca3af; line-height:1; }
.diff-modal-body { overflow-y:auto; padding:1.2rem 1.4rem; flex:1; }
.diff-cols { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
.diff-col-label { font-size:.72rem; font-weight:800; text-transform:uppercase; letter-spacing:.06em; margin-bottom:.6rem; }
.diff-old-label { color:#991b1b; }
.diff-new-label { color:#166534; }
.diff-field { margin-bottom:.55rem; }
.diff-field-key { font-size:.72rem; font-weight:700; color:#9ca3af; margin-bottom:.15rem; }
.diff-field-val { font-size:.83rem; background:#f9fafb; border-radius:6px; padding:.35rem .6rem; word-break:break-all; color:#1f2937; }
.diff-field-val.changed { background:#fff3cd; color:#92400e; font-weight:600; }

/* empty */
.log-empty { text-align:center; padding:3rem 1rem; color:#9ca3af; }
.log-empty svg { opacity:.3; margin-bottom:.75rem; }

/* pagination */
.log-pager { display:flex; gap:.4rem; align-items:center; flex-wrap:wrap; }
.log-pager a, .log-pager span { padding:.38rem .75rem; border-radius:7px; font-size:.8rem; font-weight:700; text-decoration:none; border:1.5px solid #e5e7eb; color:#374151; }
.log-pager a:hover { border-color:#c9a84c; color:#0c1a35; }
.log-pager .current-p { background:#0c1a35; color:#fff; border-color:#0c1a35; }
.log-pager .dots { border:none; color:#9ca3af; }
</style>

<div class="container-xl py-4">

  <!-- Header -->
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h1 style="margin:0;font-size:1.75rem;font-weight:800;color:#0c1a35;">Activity Logs</h1>
      <p style="margin:.4rem 0 0;color:#6b7280;font-size:.88rem;">Full audit trail — who did what and when.</p>
    </div>
  </div>

  <div class="logs-card">

    <!-- Tabs -->
    <div class="log-tabs">
      <a href="<?= BASE_URL ?>/modules/logs/index.php<?= buildQuery(['tab'=>'all','page'=>1]) ?>" class="log-tab <?= $tab==='all'?'active':'' ?>">
        All
        <span class="log-tab-count"><?= number_format($cAll) ?></span>
      </a>
      <a href="<?= BASE_URL ?>/modules/logs/index.php<?= buildQuery(['tab'=>'edited','page'=>1]) ?>" class="log-tab <?= $tab==='edited'?'active':'' ?>">
        Edited
        <span class="log-tab-count" style="background:#fef9c3;color:#854d0e;"><?= number_format($cEdit) ?></span>
      </a>
      <a href="<?= BASE_URL ?>/modules/logs/index.php<?= buildQuery(['tab'=>'deleted','page'=>1]) ?>" class="log-tab <?= $tab==='deleted'?'active':'' ?>">
        Deleted
        <span class="log-tab-count" style="background:#fee2e2;color:#991b1b;"><?= number_format($cDelete) ?></span>
      </a>
    </div>

    <!-- Filters -->
    <form method="GET" action="">
      <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
      <div class="log-filters">
        <div class="log-filter-group">
          <label class="log-filter-label">Search</label>
          <input type="text" name="q" class="log-filter-control" placeholder="Ref, description, user…" value="<?= htmlspecialchars($search) ?>" style="width:200px;">
        </div>
        <div class="log-filter-group">
          <label class="log-filter-label">Module</label>
          <select name="module" class="log-filter-control">
            <option value="">All modules</option>
            <?php foreach ($modules as $m): ?>
              <option value="<?= htmlspecialchars($m['module']) ?>" <?= $module===$m['module']?'selected':'' ?>>
                <?= ucfirst(htmlspecialchars($m['module'])) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="log-filter-group">
          <label class="log-filter-label">From</label>
          <input type="date" name="from" class="log-filter-control" value="<?= htmlspecialchars($dateFrom) ?>">
        </div>
        <div class="log-filter-group">
          <label class="log-filter-label">To</label>
          <input type="date" name="to" class="log-filter-control" value="<?= htmlspecialchars($dateTo) ?>">
        </div>
        <div style="display:flex;gap:.5rem;align-items:flex-end;">
          <button type="submit" class="log-filter-btn primary">Filter</button>
          <a href="<?= BASE_URL ?>/modules/logs/index.php?tab=<?= htmlspecialchars($tab) ?>" class="log-filter-btn reset">Reset</a>
        </div>
      </div>
    </form>

    <!-- Table -->
    <?php if ($logs): ?>
    <div style="overflow-x:auto;">
      <table class="log-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Time</th>
            <th>Action</th>
            <th>Module</th>
            <th>Reference</th>
            <th>Description</th>
            <th>User</th>
            <th>Data</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($logs as $log): ?>
          <tr class="<?= logRowClass($log['action']) ?>">
            <td style="color:#9ca3af;font-size:.78rem;"><?= $log['id'] ?></td>
            <td style="white-space:nowrap;color:#6b7280;font-size:.78rem;">
              <?= date('d M Y', strtotime($log['created_at'])) ?><br>
              <span style="color:#9ca3af;"><?= date('H:i:s', strtotime($log['created_at'])) ?></span>
            </td>
            <td><?= actionBadge($log['action']) ?></td>
            <td><span class="log-module"><?= htmlspecialchars($log['module']) ?></span></td>
            <td style="font-weight:700;font-size:.83rem;color:#0c1a35;">
              <?= $log['record_ref'] ? htmlspecialchars($log['record_ref']) : ($log['record_id'] ? '#'.$log['record_id'] : '—') ?>
            </td>
            <td style="color:#374151;font-size:.82rem;max-width:260px;">
              <?= htmlspecialchars($log['description'] ?? '') ?>
            </td>
            <td style="white-space:nowrap;font-size:.82rem;">
              <span style="font-weight:700;color:#1f2937;"><?= htmlspecialchars($log['user_name']) ?></span>
            </td>
            <td>
              <?php if ($log['old_data'] || $log['new_data']): ?>
              <button class="log-diff-btn" onclick="showDiff(<?= $log['id'] ?>, this)">View</button>
              <script>
              document.addEventListener('DOMContentLoaded',function(){});
              (function(){
                window.__logData = window.__logData || {};
                window.__logData[<?= $log['id'] ?>] = {
                  ref: <?= json_encode($log['record_ref'] ?? ('#'.$log['record_id'])) ?>,
                  action: <?= json_encode($log['action']) ?>,
                  module: <?= json_encode($log['module']) ?>,
                  old: <?= $log['old_data'] ?? 'null' ?>,
                  new: <?= $log['new_data'] ?? 'null' ?>
                };
              })();
              </script>
              <?php else: ?>
              <span style="color:#d1d5db;">—</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination + summary -->
    <div style="display:flex;align-items:center;justify-content:space-between;padding:1rem 1.5rem;border-top:1px solid #f3f4f6;flex-wrap:wrap;gap:.75rem;">
      <div style="font-size:.8rem;color:#9ca3af;">
        Showing <?= number_format(($page-1)*$perPage+1) ?>–<?= number_format(min($page*$perPage,$total)) ?> of <?= number_format($total) ?>
      </div>
      <?php if ($pages > 1): ?>
      <div class="log-pager">
        <?php if ($page > 1): ?>
          <a href="<?= BASE_URL ?>/modules/logs/index.php<?= buildQuery(['page'=>$page-1]) ?>">‹</a>
        <?php endif; ?>
        <?php
        $range = range(max(1,$page-2), min($pages,$page+2));
        if (!in_array(1,$range)) { echo '<a href="'.BASE_URL.'/modules/logs/index.php'.buildQuery(['page'=>1]).'">1</a><span class="dots">…</span>'; }
        foreach ($range as $p) {
          if ($p === $page) echo '<span class="current-p">'.$p.'</span>';
          else echo '<a href="'.BASE_URL.'/modules/logs/index.php'.buildQuery(['page'=>$p]).'">'.$p.'</a>';
        }
        if (!in_array($pages,$range) && $pages>1) { echo '<span class="dots">…</span><a href="'.BASE_URL.'/modules/logs/index.php'.buildQuery(['page'=>$pages]).'">'.$pages.'</a>'; }
        ?>
        <?php if ($page < $pages): ?>
          <a href="<?= BASE_URL ?>/modules/logs/index.php<?= buildQuery(['page'=>$page+1]) ?>">›</a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

    <?php else: ?>
    <div class="log-empty">
      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="1.5"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="2"/><path d="M9 12h6M9 16h4"/></svg>
      <p style="margin:.5rem 0 0;font-weight:700;">No log entries found</p>
      <p style="font-size:.82rem;margin:.25rem 0 0;">Activity will appear here as actions are taken.</p>
    </div>
    <?php endif; ?>

  </div><!-- /logs-card -->
</div>

<!-- Diff Modal -->
<div class="diff-modal-backdrop" id="diffBackdrop" onclick="if(event.target===this)closeDiff()">
  <div class="diff-modal">
    <div class="diff-modal-head">
      <h3 id="diffTitle">Change Details</h3>
      <button class="diff-modal-close" onclick="closeDiff()">×</button>
    </div>
    <div class="diff-modal-body" id="diffBody"></div>
  </div>
</div>

<script>
function showDiff(id, btn) {
  const d = window.__logData && window.__logData[id];
  if (!d) return;

  document.getElementById('diffTitle').textContent =
    d.module.charAt(0).toUpperCase() + d.module.slice(1) + ' — ' + d.action + (d.ref ? ' ['+d.ref+']' : '');

  let html = '';

  if (d.action === 'delete' && d.old) {
    html += '<div class="diff-col-label diff-old-label">Deleted Record</div>';
    html += renderFields(d.old);
  } else if (d.action === 'create' && d.new) {
    html += '<div class="diff-col-label diff-new-label">Created Record</div>';
    html += renderFields(d.new);
  } else if (d.old || d.new) {
    const oldD = d.old || {};
    const newD = d.new || {};
    const keys = [...new Set([...Object.keys(oldD), ...Object.keys(newD)])];
    html += '<div class="diff-cols">';
    html += '<div><div class="diff-col-label diff-old-label">Before</div>' + renderFields(oldD, newD, keys) + '</div>';
    html += '<div><div class="diff-col-label diff-new-label">After</div>'  + renderFields(newD, oldD, keys) + '</div>';
    html += '</div>';
  } else {
    html = '<p style="color:#9ca3af;">No data recorded.</p>';
  }

  document.getElementById('diffBody').innerHTML = html;
  document.getElementById('diffBackdrop').classList.add('open');
}

function renderFields(data, compare, keys) {
  if (!keys) keys = Object.keys(data);
  if (!keys.length) return '<p style="color:#9ca3af;font-size:.83rem;">No fields.</p>';
  let out = '';
  keys.forEach(k => {
    const val  = data[k]  !== undefined ? String(data[k])  : '—';
    const cVal = compare && compare[k] !== undefined ? String(compare[k]) : null;
    const changed = compare && cVal !== null && cVal !== val;
    out += '<div class="diff-field">';
    out += '<div class="diff-field-key">' + escHtml(k) + '</div>';
    out += '<div class="diff-field-val' + (changed ? ' changed' : '') + '">' + escHtml(val) + '</div>';
    out += '</div>';
  });
  return out;
}

function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function closeDiff() {
  document.getElementById('diffBackdrop').classList.remove('open');
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDiff(); });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
