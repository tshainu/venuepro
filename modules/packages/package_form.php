<?php
// Shared form for create.php and edit.php
// Available vars: $pkg (edit only), $existing_items (edit only), $branches, $errors, $cu, $return
$isEdit   = isset($pkg);
$formTitle = $isEdit ? 'Edit Package' : 'Add Package';

// Pre-fill from POST (validation fail) or existing record
$fName   = htmlspecialchars($_POST['name']        ?? ($pkg['name']        ?? ''));
$fPrice  = htmlspecialchars($_POST['price']        ?? ($pkg['price']       ?? '0'));
$fDesc   = htmlspecialchars($_POST['description']  ?? ($pkg['description'] ?? ''));
$fBranch = (int)($_POST['branch_id'] ?? ($pkg['branch_id'] ?? $cu['branch_id']));

// Items: POST takes priority, then existing DB items, then one empty row
if (isset($_POST['items'])) {
    $formItems = $_POST['items'];
} elseif ($isEdit) {
    $formItems = array_map(fn($it) => [
        'name'  => $it['item_name'],
        'qty'   => $it['quantity'],
        'unit'  => $it['unit'] ?? '',
        'notes' => $it['notes'] ?? '',
    ], $existing_items);
} else {
    $formItems = [];
}

// Predefined quick-pick items grouped by category
$quickPick = [
    '🪑 Seating' => [
        ['Chairs',           'chairs',  'pcs'],
        ['Tables',           'tables',  'pcs'],
        ['VIP Sofa Set',     'sofa',    'set'],
        ['Banquet Tables',   'banquet', 'pcs'],
        ['Throne Chairs',    'throne',  'pcs'],
    ],
    '🌸 Décor' => [
        ['Stage Decoration',    'stage',   'set'],
        ['Floral Arrangement',  'flowers', 'set'],
        ['Backdrop',            'backdrop','pcs'],
        ['Table Centerpieces',  'center',  'pcs'],
        ['Aisle Decoration',    'aisle',   'set'],
        ['Fairy Lights',        'lights',  'set'],
        ['Balloon Arch',        'balloons','pcs'],
    ],
    '🍽️ Catering' => [
        ['Buffet Lunch',        'buffet',    'pax'],
        ['Buffet Dinner',       'dinner',    'pax'],
        ['Cocktail Snacks',     'cocktail',  'pax'],
        ['Wedding Cake',        'cake',      'pcs'],
        ['Soft Drinks',         'drinks',    'pax'],
        ['Waiter Service',      'waiters',   'persons'],
    ],
    '🎵 A/V & Entertainment' => [
        ['Sound System',        'sound',   'set'],
        ['DJ / Music',          'dj',      'hrs'],
        ['Microphones',         'mics',    'pcs'],
        ['LED Screen',          'screen',  'pcs'],
        ['Projector',           'projector','pcs'],
        ['Lighting Rig',        'rig',     'set'],
        ['Live Band',           'band',    'hrs'],
    ],
    '📸 Photography' => [
        ['Photography',         'photo',   'hrs'],
        ['Videography',         'video',   'hrs'],
        ['Photo Booth',         'booth',   'hrs'],
        ['Drone Coverage',      'drone',   'hrs'],
        ['Album (Printed)',      'album',   'pcs'],
    ],
    '🚗 Services' => [
        ['Valet Parking',       'valet',   'hrs'],
        ['Bridal Car',          'car',     'pcs'],
        ['Security Staff',      'security','persons'],
        ['Event Coordinator',   'coord',   'persons'],
        ['Generator Backup',    'gen',     'hrs'],
        ['Air Conditioning',    'ac',      'set'],
    ],
];
?>

<style>
.pkg-section { border-radius: 14px; border: 1px solid #e8eaf0; box-shadow: 0 2px 8px rgba(30,42,74,.05); margin-bottom: 1.25rem; overflow: hidden; }
.pkg-section-hd { background: linear-gradient(135deg,#f8f9fb,#fff); border-bottom: 1px solid #e8eaf0; padding: .85rem 1.25rem; display: flex; align-items: center; justify-content: space-between; }
.pkg-section-hd h3 { font-size: .88rem; font-weight: 800; color: var(--vp-navy); margin: 0; }
.pkg-section-body { padding: 1.25rem; }

/* Quick pick */
.qp-cats { display: flex; gap: .5rem; flex-wrap: wrap; margin-bottom: 1rem; }
.qp-cat-btn { font-size: .75rem; font-weight: 700; padding: .35rem .85rem; border-radius: 20px; border: 1.5px solid #e0e3ee; background: #fff !important; color: var(--vp-navy) !important; cursor: pointer; transition: all .15s; }
.qp-cat-btn:hover, .qp-cat-btn.active { background: var(--vp-navy) !important; color: #fff !important; border-color: var(--vp-navy) !important; }
.qp-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: .5rem; }
.qp-chip { display: flex; align-items: center; gap: .5rem; padding: .5rem .75rem; border-radius: 10px; border: 1.5px solid #e0e3ee; background: #fff; cursor: pointer; font-size: .8rem; font-weight: 600; color: #374151; transition: all .15s; user-select: none; }
.qp-chip:hover { border-color: var(--vp-gold); background: #fffbf0; color: var(--vp-navy); }
.qp-chip.selected { border-color: var(--vp-gold); background: linear-gradient(135deg,#fff8e1,#fffbf0); color: var(--vp-navy); }
.qp-chip .chip-check { width: 18px; height: 18px; border-radius: 5px; border: 2px solid #d1d5db; background: #fff; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: .7rem; transition: all .15s; }
.qp-chip.selected .chip-check { background: var(--vp-gold); border-color: var(--vp-gold); color: #fff; }

/* Items table */
.items-table { width: 100%; border-collapse: collapse; }
.items-table th { font-size: .75rem; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: .04em; padding: .4rem .5rem; border-bottom: 2px solid #f0f2f5; }
.items-table td { padding: .4rem .4rem; vertical-align: middle; }
.items-table tr.item-row:hover td { background: #fafbfc; }
.item-drag-handle { cursor: grab; color: #d1d5db; font-size: 1rem; padding: 0 .3rem; }
.item-drag-handle:hover { color: #9ca3af; }
.btn-remove-item { width: 28px; height: 28px; border-radius: 7px; border: 1.5px solid #fecaca; background: #fff; color: #ef4444; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 1rem; transition: all .15s; }
.btn-remove-item:hover { background: #fef2f2; }

/* Summary sidebar */
.pkg-summary { position: sticky; top: 80px; }
.summary-card { border-radius: 14px; border: 1px solid #e8eaf0; box-shadow: 0 4px 16px rgba(30,42,74,.08); overflow: hidden; }
.summary-hd { background: linear-gradient(135deg, var(--vp-navy), #1a3060); padding: 1.1rem 1.3rem; }
.summary-hd .pkg-name { font-size: 1rem; font-weight: 800; color: #fff; }
.summary-hd .pkg-price { font-size: 1.4rem; font-weight: 900; color: var(--vp-gold-lt,#ffd966); margin-top: .2rem; }
.summary-body { padding: 1.1rem 1.3rem; }
.summary-item { display: flex; align-items: flex-start; gap: .5rem; padding: .35rem 0; border-bottom: 1px solid #f0f2f5; font-size: .8rem; }
.summary-item:last-child { border-bottom: none; }
.summary-item .si-check { color: var(--vp-gold,#c9a227); font-weight: 800; flex-shrink: 0; margin-top: 1px; }
.summary-item .si-name { color: #374151; font-weight: 600; flex: 1; }
.summary-item .si-qty { color: #9ca3af; font-size: .72rem; white-space: nowrap; }
.summary-empty { text-align: center; color: #d1d5db; font-size: .82rem; padding: 1.5rem 0; }

/* sticky footer */
.pkg-sticky-footer { position: fixed; bottom: 0; left: 0; right: 0; background: #fff; border-top: 1px solid #e8e8e8; padding: .85rem 1.5rem; z-index: 200; display: flex; align-items: center; gap: .75rem; }
</style>

<div class="vp-page-header d-print-none">
  <div>
    <h1 class="vp-page-title"><?= $formTitle ?></h1>
    <div class="vp-page-sub"><?= $isEdit ? 'Update package details and included items' : 'Define package name, price and what\'s included' ?></div>
  </div>
</div>

<?php if ($errors): foreach ($errors as $e): ?>
<div class="alert alert-danger"><?= Helper::sanitize($e) ?></div>
<?php endforeach; endif; ?>

<form method="POST" id="pkgForm">
<input type="hidden" name="return" value="<?= htmlspecialchars($return) ?>">

<div class="row g-4">
<div class="col-lg-8">

  <!-- Basic Info -->
  <div class="pkg-section">
    <div class="pkg-section-hd"><h3>📦 Package Details</h3></div>
    <div class="pkg-section-body">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label fw-700">Package Name <span class="text-danger">*</span></label>
          <input type="text" name="name" id="pkgName" class="form-control" required value="<?= $fName ?>" placeholder="e.g. Gold Wedding Package" oninput="updateSummary()">
        </div>
        <div class="col-md-3">
          <label class="form-label fw-700">Price (Rs.)</label>
          <input type="number" name="price" id="pkgPrice" class="form-control" step="1" min="0" value="<?= $fPrice ?>" oninput="updateSummary()">
        </div>
        <div class="col-md-3">
          <label class="form-label fw-700">Branch</label>
          <select name="branch_id" class="form-select">
            <?php foreach ($branches as $br): ?>
            <option value="<?= $br['id'] ?>" <?= $fBranch==$br['id']?'selected':'' ?>><?= Helper::sanitize($br['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12">
          <label class="form-label fw-700">Description</label>
          <textarea name="description" class="form-control" rows="2" placeholder="Brief description of this package..."><?= $fDesc ?></textarea>
        </div>
      </div>
    </div>
  </div>

  <!-- Quick Pick -->
  <div class="pkg-section">
    <div class="pkg-section-hd">
      <h3>⚡ Quick Add — Common Items</h3>
      <span style="font-size:.75rem;color:#9ca3af;">Click to add to package</span>
    </div>
    <div class="pkg-section-body">
      <!-- Category tabs -->
      <div class="qp-cats" id="qpCats">
        <?php $first = true; foreach ($quickPick as $cat => $catItems): ?>
        <button type="button" class="qp-cat-btn <?= $first?'active':'' ?>" data-cat="<?= htmlspecialchars($cat) ?>"><?= $cat ?></button>
        <?php $first = false; endforeach; ?>
      </div>
      <!-- Item chips per category -->
      <?php $first = true; foreach ($quickPick as $cat => $catItems): ?>
      <div class="qp-grid" data-cat-grid="<?= htmlspecialchars($cat) ?>" style="<?= $first?'':'display:none' ?>">
        <?php foreach ($catItems as [$label, $key, $unit]): ?>
        <div class="qp-chip" data-label="<?= htmlspecialchars($label) ?>" data-unit="<?= htmlspecialchars($unit) ?>" onclick="toggleQuickItem(this)">
          <span class="chip-check">✓</span>
          <span><?= $label ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php $first = false; endforeach; ?>
    </div>
  </div>

  <!-- Items Table -->
  <div class="pkg-section">
    <div class="pkg-section-hd">
      <h3>📋 Package Includes</h3>
      <button type="button" class="btn btn-sm btn-vp-gold" onclick="addItemRow()">
        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M12 5v14M5 12h14"/></svg>
        Add Custom Item
      </button>
    </div>
    <div class="pkg-section-body p-0">
      <table class="items-table">
        <thead>
          <tr>
            <th style="width:30px;"></th>
            <th>Item / Service</th>
            <th style="width:80px;">Qty</th>
            <th style="width:100px;">Unit</th>
            <th>Notes</th>
            <th style="width:36px;"></th>
          </tr>
        </thead>
        <tbody id="itemsBody">
          <?php foreach ($formItems as $i => $it): ?>
          <tr class="item-row" data-idx="<?= $i ?>">
            <td><span class="item-drag-handle">⠿</span></td>
            <td><input type="text" name="items[<?= $i ?>][name]" class="form-control form-control-sm item-name" value="<?= htmlspecialchars($it['name'] ?? '') ?>" placeholder="Item name" oninput="updateSummary()" required></td>
            <td><input type="number" name="items[<?= $i ?>][qty]" class="form-control form-control-sm item-qty" min="1" value="<?= (int)($it['qty'] ?? 1) ?>" oninput="updateSummary()"></td>
            <td><input type="text" name="items[<?= $i ?>][unit]" class="form-control form-control-sm item-unit" value="<?= htmlspecialchars($it['unit'] ?? '') ?>" placeholder="pcs / pax…"></td>
            <td><input type="text" name="items[<?= $i ?>][notes]" class="form-control form-control-sm" value="<?= htmlspecialchars($it['notes'] ?? '') ?>" placeholder="Optional note"></td>
            <td><button type="button" class="btn-remove-item" onclick="removeRow(this)" title="Remove">×</button></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div id="emptyItems" class="summary-empty" style="<?= count($formItems)?'display:none':'' ?>">
        No items added yet. Use Quick Add above or click "Add Custom Item".
      </div>
    </div>
  </div>

</div><!-- /col-lg-8 -->

<!-- Summary Sidebar -->
<div class="col-lg-4">
  <div class="pkg-summary">
    <div class="summary-card">
      <div class="summary-hd">
        <div class="pkg-name" id="sumName"><?= $fName ?: 'Package Name' ?></div>
        <div class="pkg-price" id="sumPrice">Rs. <?= number_format((float)$fPrice) ?></div>
      </div>
      <div class="summary-body">
        <div style="font-size:.72rem;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.5rem;">What's Included</div>
        <div id="sumItems">
          <?php if ($formItems): foreach ($formItems as $it): if (!trim($it['name']??'')) continue; ?>
          <div class="summary-item">
            <span class="si-check">✓</span>
            <span class="si-name"><?= htmlspecialchars($it['name']) ?></span>
            <span class="si-qty"><?= (int)($it['qty']??1) ?> <?= htmlspecialchars($it['unit']??'') ?></span>
          </div>
          <?php endforeach; else: ?>
          <div class="summary-empty">Items you add will appear here</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

</div><!-- /row -->

<div class="pkg-sticky-footer">
  <button type="submit" class="btn btn-vp-gold px-4">
    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
    <?= $isEdit ? 'Update Package' : 'Save Package' ?>
  </button>
  <a href="<?= $return==='settings' ? BASE_URL.'/modules/settings/index.php?tab=packages' : BASE_URL.'/modules/packages/index.php' ?>" class="btn btn-ghost-secondary">Cancel</a>
  <span id="itemCount" style="font-size:.78rem;color:#9ca3af;margin-left:auto;"></span>
</div>
<div style="height:70px;"></div>
</form>

<script>
var itemIdx = <?= count($formItems) ?>;

// ---- Quick Pick ----
document.querySelectorAll('.qp-cat-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.qp-cat-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('[data-cat-grid]').forEach(g => g.style.display = 'none');
    this.classList.add('active');
    document.querySelector('[data-cat-grid="'+this.dataset.cat+'"]').style.display = 'grid';
  });
});

// Mark chips that match existing items
document.querySelectorAll('.item-name').forEach(inp => markChipForName(inp.value));

function markChipForName(name) {
  document.querySelectorAll('.qp-chip').forEach(chip => {
    if (chip.dataset.label.toLowerCase() === name.trim().toLowerCase()) chip.classList.add('selected');
  });
}

function toggleQuickItem(chip) {
  var label = chip.dataset.label;
  var unit  = chip.dataset.unit;

  // If already in list — remove it
  var existing = [...document.querySelectorAll('.item-name')].find(i => i.value.trim().toLowerCase() === label.toLowerCase());
  if (existing) {
    existing.closest('tr').remove();
    chip.classList.remove('selected');
    updateSummary();
    return;
  }

  chip.classList.add('selected');
  addItemRow(label, 1, unit);
}

function addItemRow(name, qty, unit) {
  name = name || ''; qty = qty || 1; unit = unit || '';
  var tbody = document.getElementById('itemsBody');
  var tr = document.createElement('tr');
  tr.className = 'item-row';
  tr.dataset.idx = itemIdx;
  tr.innerHTML =
    '<td><span class="item-drag-handle">⠿</span></td>' +
    '<td><input type="text" name="items['+itemIdx+'][name]" class="form-control form-control-sm item-name" value="'+escHtml(name)+'" placeholder="Item name" oninput="updateSummary();syncChip(this)" required></td>' +
    '<td><input type="number" name="items['+itemIdx+'][qty]" class="form-control form-control-sm item-qty" min="1" value="'+qty+'" oninput="updateSummary()"></td>' +
    '<td><input type="text" name="items['+itemIdx+'][unit]" class="form-control form-control-sm item-unit" value="'+escHtml(unit)+'" placeholder="pcs / pax…"></td>' +
    '<td><input type="text" name="items['+itemIdx+'][notes]" class="form-control form-control-sm" placeholder="Optional note"></td>' +
    '<td><button type="button" class="btn-remove-item" onclick="removeRow(this)" title="Remove">×</button></td>';
  tbody.appendChild(tr);
  itemIdx++;
  updateSummary();
  if (name) markChipForName(name);
  // Focus the name field if empty
  if (!name) tr.querySelector('.item-name').focus();
}

function removeRow(btn) {
  var row  = btn.closest('tr');
  var name = row.querySelector('.item-name').value.trim().toLowerCase();
  // Deselect chip
  document.querySelectorAll('.qp-chip').forEach(chip => {
    if (chip.dataset.label.toLowerCase() === name) chip.classList.remove('selected');
  });
  row.remove();
  updateSummary();
}

function syncChip(inp) {
  // When user types in name field, deselect all chips then re-check
  document.querySelectorAll('.qp-chip').forEach(chip => {
    var inList = [...document.querySelectorAll('.item-name')].some(i => i.value.trim().toLowerCase() === chip.dataset.label.toLowerCase());
    chip.classList.toggle('selected', inList);
  });
}

function updateSummary() {
  // Name & price
  var name  = document.getElementById('pkgName').value.trim() || 'Package Name';
  var price = parseFloat(document.getElementById('pkgPrice').value) || 0;
  document.getElementById('sumName').textContent  = name;
  document.getElementById('sumPrice').textContent = 'Rs. ' + price.toLocaleString();

  // Items
  var rows  = document.querySelectorAll('#itemsBody .item-row');
  var html  = '';
  var count = 0;
  rows.forEach(row => {
    var n = row.querySelector('.item-name').value.trim();
    var q = row.querySelector('.item-qty').value;
    var u = row.querySelector('.item-unit').value.trim();
    if (n) {
      html += '<div class="summary-item"><span class="si-check">✓</span><span class="si-name">'+escHtml(n)+'</span><span class="si-qty">'+q+(u?' '+u:'')+'</span></div>';
      count++;
    }
  });
  document.getElementById('sumItems').innerHTML = html || '<div class="summary-empty">Items you add will appear here</div>';
  document.getElementById('emptyItems').style.display = count ? 'none' : '';
  document.getElementById('itemCount').textContent = count ? count + ' item' + (count>1?'s':'') + ' included' : '';
}

function escHtml(str) {
  return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Init
updateSummary();
</script>
