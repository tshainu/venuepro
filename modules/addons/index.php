<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
$db = Database::getInstance();
$cu = Auth::currentUser();

// Handle AJAX create
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'create_addon') {
    if (!Auth::isLoggedIn()) { echo json_encode(['ok'=>false,'error'=>'Unauthorized']); exit; }
    $name        = trim($_POST['name'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0) ?: null;
    $branch_id   = $cu['branch_id'] ?? ((int)($_POST['branch_id'] ?? 0) ?: null);
    $description = trim($_POST['description'] ?? '');
    $price       = (float)($_POST['price'] ?? 0);
    $unit        = trim($_POST['unit'] ?? 'per event');
    $tax_percent = (float)($_POST['tax_percent'] ?? 0);
    $is_available= isset($_POST['is_available']) ? 1 : 0;
    if (!$name) { echo json_encode(['ok'=>false,'error'=>'Name is required.']); exit; }
    $db->insert("INSERT INTO addons (branch_id,category_id,name,description,price,unit,tax_percent,is_available) VALUES (?,?,?,?,?,?,?,?)",
        [$branch_id,$category_id,$name,$description,$price,$unit,$tax_percent,$is_available]);
    echo json_encode(['ok'=>true]);
    exit;
}

$search     = trim($_GET['search'] ?? '');
$cat_filter = (int)($_GET['category_id'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));

$where = ['1=1']; $params = [];
if ($cu['branch_id']) { $where[] = '(a.branch_id=? OR a.branch_id IS NULL)'; $params[] = $cu['branch_id']; }
if ($search)      { $where[] = 'a.name LIKE ?'; $params[] = "%$search%"; }
if ($cat_filter)  { $where[] = 'a.category_id=?'; $params[] = $cat_filter; }

$wstr  = implode(' AND ', $where);
$total = $db->fetchOne("SELECT COUNT(*) as cnt FROM addons a WHERE $wstr", $params)['cnt'];
$pg    = Helper::paginate($total, $page, 20);

$addons = $db->fetchAll(
    "SELECT a.*, c.name as category_name, b.name as branch_name
     FROM addons a
     LEFT JOIN addon_categories c ON a.category_id=c.id
     LEFT JOIN branches b ON a.branch_id=b.id
     WHERE $wstr ORDER BY a.name ASC LIMIT ? OFFSET ?",
    array_merge($params, [$pg['per_page'], $pg['offset']])
);
$categories = $db->fetchAll("SELECT * FROM addon_categories ORDER BY name");
$branches   = $db->fetchAll("SELECT id,name FROM branches WHERE is_active=1");

$pageTitle  = 'Add-ons';
$breadcrumbs = [['label' => 'Add-ons']];
require_once ROOT_PATH . '/includes/header.php';
?>

<style>
/* ── Add-ons Modern Grid ─────────────────────────── */
.addon-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 1rem;
}
.addon-card {
  background: #fff;
  border: 1.5px solid #edf0f8;
  border-radius: 16px;
  padding: 1.3rem 1.4rem;
  position: relative;
  transition: all .2s;
  overflow: hidden;
}
.addon-card::before {
  content: '';
  position: absolute; left: 0; top: 0; bottom: 0; width: 4px;
  border-radius: 16px 0 0 16px;
  background: var(--ac-color, #c9a84c);
}
.addon-card:hover { transform: translateY(-3px); box-shadow: 0 10px 28px rgba(12,26,53,.12); }
.addon-card-header { display: flex; align-items: flex-start; justify-content: space-between; gap: .5rem; margin-bottom: .75rem; }
.addon-name { font-size: .95rem; font-weight: 800; color: #0c1a35; line-height: 1.2; }
.addon-cat-badge {
  font-size: .63rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em;
  background: #f1f4fa; color: #6b7280; border-radius: 20px; padding: .2rem .6rem;
  white-space: nowrap;
}
.addon-desc { font-size: .75rem; color: #9ca3af; margin-bottom: .85rem; line-height: 1.5; }
.addon-price-row { display: flex; align-items: center; justify-content: space-between; gap: .5rem; }
.addon-price { font-size: 1.15rem; font-weight: 800; color: #0c1a35; }
.addon-unit { font-size: .7rem; color: #9ca3af; font-weight: 600; }
.addon-meta { display: flex; align-items: center; gap: .5rem; margin-top: .7rem; }
.addon-avail { display: inline-flex; align-items: center; gap: .3rem; font-size: .7rem; font-weight: 700; border-radius: 20px; padding: .2rem .65rem; }
.addon-avail.yes { background: #ecfdf5; color: #059669; }
.addon-avail.no  { background: #fef2f2; color: #dc2626; }
.addon-actions { display: flex; gap: .4rem; margin-top: 1rem; padding-top: .75rem; border-top: 1px solid #f1f4fa; }

/* ── Modal ───────────────────────────────────────── */
.vp-modal-overlay {
  display: none; position: fixed; inset: 0; z-index: 1060;
  background: rgba(12,26,53,.55); backdrop-filter: blur(3px);
  align-items: center; justify-content: center;
}
.vp-modal-overlay.show { display: flex; }
.vp-modal {
  background: #fff; border-radius: 20px;
  width: 100%; max-width: 540px; margin: 1rem;
  box-shadow: 0 24px 80px rgba(12,26,53,.28);
  overflow: hidden;
  animation: modalIn .25s cubic-bezier(.34,1.56,.64,1);
}
@keyframes modalIn {
  from { transform: scale(.93) translateY(20px); opacity: 0; }
  to   { transform: scale(1)   translateY(0);    opacity: 1; }
}
.vp-modal-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 1.4rem 1.6rem 1.2rem;
  background: linear-gradient(130deg, #0c1a35, #1a3060);
  border-bottom: 2px solid rgba(201,168,76,.2);
}
.vp-modal-title { font-size: 1rem; font-weight: 800; color: #fff; }
.vp-modal-subtitle { font-size: .72rem; color: rgba(255,255,255,.55); margin-top: .15rem; }
.vp-modal-close {
  width: 30px; height: 30px; border-radius: 50%;
  background: rgba(255,255,255,.12); border: none; color: #fff;
  cursor: pointer; display: flex; align-items: center; justify-content: center;
  font-size: 1.1rem; line-height: 1; transition: background .15s;
}
.vp-modal-close:hover { background: rgba(255,255,255,.25); }
.vp-modal-body { padding: 1.6rem; }
.vp-modal-footer {
  padding: 1rem 1.6rem; background: #f8f9fc;
  border-top: 1px solid #edf0f8;
  display: flex; align-items: center; justify-content: flex-end; gap: .6rem;
}
.vp-field-label {
  font-size: .73rem; font-weight: 700; color: #374151;
  text-transform: uppercase; letter-spacing: .05em; margin-bottom: .35rem;
}
.vp-field-label.required::after { content: ' *'; color: #dc2626; }
</style>

<div class="vp-page-header">
  <div>
    <h1 class="vp-page-title">✨ <?= Lang::t('addons') ?></h1>
    <div class="vp-page-sub"><?= $total ?> add-on<?= $total !== 1 ? 's' : '' ?> available</div>
  </div>
  <?php if (Auth::hasRole(['super_admin','hall_manager'])): ?>
  <button type="button" class="btn btn-vp-gold" onclick="openAddonModal()">
    <svg xmlns="http://www.w3.org/2000/svg" class="icon me-1" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5l0 14"/><path d="M5 12l14 0"/></svg>
    New Add-on
  </button>
  <?php endif; ?>
</div>

<!-- Filter Bar -->
<div class="vp-filter-bar mb-4">
  <form method="get" class="d-flex gap-2 flex-wrap align-items-end">
    <input type="text" name="search" class="form-control" placeholder="Search add-ons..." value="<?= Helper::sanitize($search) ?>" style="max-width:240px;">
    <select name="category_id" class="form-select" style="max-width:180px;">
      <option value="">All Categories</option>
      <?php foreach ($categories as $c): ?>
      <option value="<?= $c['id'] ?>" <?= $cat_filter==$c['id']?'selected':'' ?>><?= Helper::sanitize($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-vp-primary">Filter</button>
    <a href="<?= BASE_URL ?>/modules/addons/index.php" class="btn btn-vp-outline">Clear</a>
  </form>
</div>

<?php
$accentColors = ['#3b82f6','#10b981','#f59e0b','#f97316','#a855f7','#06b6d4','#ec4899','#dc2626','#6366f1','#14b8a6'];
$ci = 0;
?>
<?php if ($addons): ?>
<div class="addon-grid">
  <?php foreach ($addons as $a):
    $color = $accentColors[$ci % count($accentColors)]; $ci++;
  ?>
  <div class="addon-card" style="--ac-color:<?= $color ?>">
    <div class="addon-card-header">
      <div class="addon-name"><?= Helper::sanitize($a['name']) ?></div>
      <?php if ($a['category_name']): ?>
      <span class="addon-cat-badge"><?= Helper::sanitize($a['category_name']) ?></span>
      <?php endif; ?>
    </div>
    <?php if ($a['description']): ?>
    <div class="addon-desc"><?= Helper::sanitize($a['description']) ?></div>
    <?php endif; ?>
    <div class="addon-price-row">
      <div>
        <div class="addon-price"><?= Helper::formatCurrency($a['price']) ?></div>
        <div class="addon-unit"><?= Helper::sanitize($a['unit']) ?></div>
      </div>
      <div style="text-align:right;">
        <?php if ($a['tax_percent'] > 0): ?>
        <div style="font-size:.7rem;color:#6b7280;">+<?= number_format($a['tax_percent'],1) ?>% tax</div>
        <?php endif; ?>
        <?php if ($a['branch_name']): ?>
        <div style="font-size:.67rem;color:#9ca3af;margin-top:.15rem;"><?= Helper::sanitize($a['branch_name']) ?></div>
        <?php else: ?>
        <div style="font-size:.67rem;color:#9ca3af;margin-top:.15rem;">All Branches</div>
        <?php endif; ?>
      </div>
    </div>
    <div class="addon-meta">
      <span class="addon-avail <?= $a['is_available'] ? 'yes' : 'no' ?>">
        <?= $a['is_available'] ? '● Available' : '○ Unavailable' ?>
      </span>
    </div>
    <?php if (Auth::hasRole(['super_admin','hall_manager'])): ?>
    <div class="addon-actions">
      <a href="<?= BASE_URL ?>/modules/addons/edit.php?id=<?= $a['id'] ?>" class="btn btn-vp-primary btn-sm flex-1" style="flex:1;text-align:center;">Edit</a>
      <a href="<?= BASE_URL ?>/modules/addons/delete.php?id=<?= $a['id'] ?>" class="btn btn-vp-danger btn-sm" onclick="return confirm('Delete this add-on?')">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
      </a>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>

<?php if ($pg['pages'] > 1): ?>
<div class="d-flex align-items-center mt-4">
  <p class="m-0 text-secondary" style="font-size:.8rem;">Showing <?= count($addons) ?> of <?= $total ?></p>
  <ul class="pagination ms-auto mb-0">
    <?php for ($i=1;$i<=$pg['pages'];$i++): ?>
    <li class="page-item <?= $i==$page?'active':'' ?>">
      <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category_id=<?= $cat_filter ?>"><?= $i ?></a>
    </li>
    <?php endfor; ?>
  </ul>
</div>
<?php endif; ?>

<?php else: ?>
<div class="empty-state" style="padding:4rem 2rem;">
  <div class="empty-icon">✨</div>
  <div class="empty-text">No add-ons found. Create one with the button above.</div>
</div>
<?php endif; ?>

<!-- ═══════ NEW ADD-ON MODAL ═══════ -->
<div class="vp-modal-overlay" id="addonModal" onclick="if(event.target===this)closeAddonModal()">
  <div class="vp-modal">
    <div class="vp-modal-header">
      <div>
        <div class="vp-modal-title">✨ New Add-on Service</div>
        <div class="vp-modal-subtitle">Add an optional service for bookings</div>
      </div>
      <button class="vp-modal-close" onclick="closeAddonModal()">×</button>
    </div>
    <form id="addonForm">
      <input type="hidden" name="_action" value="create_addon">
      <div class="vp-modal-body">
        <div id="addonAlert" class="alert alert-danger d-none mb-3"></div>

        <div class="mb-3">
          <div class="vp-field-label required">Name</div>
          <input type="text" name="name" id="addon_name" class="form-control" placeholder="e.g. Premium Floral Arrangement" required autofocus>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-7">
            <div class="vp-field-label">Category</div>
            <select name="category_id" class="form-select">
              <option value="">— None —</option>
              <?php foreach ($categories as $c): ?>
              <option value="<?= $c['id'] ?>"><?= Helper::sanitize($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php if (Auth::isSuperAdmin()): ?>
          <div class="col-5">
            <div class="vp-field-label">Branch</div>
            <select name="branch_id" class="form-select">
              <option value="">All Branches</option>
              <?php foreach ($branches as $b): ?>
              <option value="<?= $b['id'] ?>"><?= Helper::sanitize($b['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>
        </div>

        <div class="mb-3">
          <div class="vp-field-label">Description</div>
          <textarea name="description" class="form-control" rows="2" placeholder="Brief description..."></textarea>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-4">
            <div class="vp-field-label required">Price (Rs.)</div>
            <input type="number" name="price" class="form-control" step="0.01" min="0" value="0" required>
          </div>
          <div class="col-4">
            <div class="vp-field-label">Unit</div>
            <input type="text" name="unit" class="form-control" placeholder="per event" value="per event">
          </div>
          <div class="col-4">
            <div class="vp-field-label">Tax %</div>
            <input type="number" name="tax_percent" class="form-control" step="0.01" min="0" max="100" value="0">
          </div>
        </div>

        <div>
          <label class="d-flex align-items-center gap-2" style="cursor:pointer;">
            <input type="checkbox" name="is_available" class="form-check-input mt-0" checked>
            <span style="font-size:.83rem;font-weight:600;color:#374151;">Available for booking</span>
          </label>
        </div>
      </div>
      <div class="vp-modal-footer">
        <button type="button" class="btn btn-vp-outline" onclick="closeAddonModal()">Cancel</button>
        <button type="submit" class="btn btn-vp-gold" id="addonSaveBtn">
          <svg xmlns="http://www.w3.org/2000/svg" class="icon me-1" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 13l4 4L19 7"/></svg>
          Save Add-on
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function openAddonModal() {
  document.getElementById('addonModal').classList.add('show');
  document.getElementById('addon_name').focus();
}
function closeAddonModal() {
  document.getElementById('addonModal').classList.remove('show');
  document.getElementById('addonForm').reset();
  document.getElementById('addonAlert').classList.add('d-none');
}
document.getElementById('addonForm').addEventListener('submit', function(e) {
  e.preventDefault();
  var btn = document.getElementById('addonSaveBtn');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';
  var fd = new FormData(this);
  fetch('', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.ok) {
        closeAddonModal();
        window.location.reload();
      } else {
        var al = document.getElementById('addonAlert');
        al.textContent = data.error || 'Error saving add-on.';
        al.classList.remove('d-none');
        btn.disabled = false;
        btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="icon me-1" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 13l4 4L19 7"/></svg>Save Add-on';
      }
    })
    .catch(() => {
      btn.disabled = false;
      btn.innerHTML = 'Save Add-on';
    });
});
// ESC to close
document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeAddonModal(); });
</script>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
