<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
$db = Database::getInstance();
$cu = Auth::currentUser();

$id  = (int)($_GET['id'] ?? 0);
$inq = $db->fetchOne(
    "SELECT i.*, h.name as hall_name, b.name as branch_name, u.name as assigned_name, u2.name as creator_name
     FROM inquiries i
     LEFT JOIN halls h ON i.hall_id=h.id
     LEFT JOIN branches b ON i.branch_id=b.id
     LEFT JOIN users u ON i.assigned_to=u.id
     LEFT JOIN users u2 ON i.created_by=u2.id
     WHERE i.id=?", [$id]
);
if (!$inq) { Helper::flash('error','Inquiry not found.'); Helper::redirect(BASE_URL.'/modules/inquiries/index.php'); }

// Handle status update
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['_action']??'')==='update_status') {
    $newStatus = $_POST['status'] ?? $inq['status'];
    $fu = trim($_POST['follow_up_date'] ?? '') ?: null;
    $notes = trim($_POST['notes'] ?? $inq['notes']);

    $oldData = ['status' => $inq['status'], 'follow_up_date' => $inq['follow_up_date'], 'notes' => $inq['notes']];
    $db->execute("UPDATE inquiries SET status=?,follow_up_date=?,notes=?,updated_at=NOW() WHERE id=?", [$newStatus,$fu,$notes,$id]);
    $newData = ['status' => $newStatus, 'follow_up_date' => $fu, 'notes' => $notes];

    Logger::log('edit', 'inquiries', $id, $inq['inquiry_ref'], $oldData, $newData,
        "Updated inquiry {$inq['inquiry_ref']} status to $newStatus");

    Helper::flash('success','Inquiry updated.');
    Helper::redirect(BASE_URL.'/modules/inquiries/view.php?id='.$id);
}

$halls = $db->fetchAll("SELECT id,name FROM halls WHERE is_active=1 ORDER BY name");
$users = $db->fetchAll("SELECT id,name FROM users WHERE is_active=1 ORDER BY name");

$pageTitle   = 'Inquiry — '.$inq['inquiry_ref'];
$breadcrumbs = [['label'=>'Inquiries','url'=>BASE_URL.'/modules/inquiries/index.php'],['label'=>$inq['inquiry_ref']]];
require_once ROOT_PATH . '/includes/header.php';

$statusColors = ['new'=>'#2563eb','contacted'=>'#d97706','quoted'=>'#7c3aed','converted'=>'#059669','lost'=>'#dc2626'];
$sc = $statusColors[$inq['status']] ?? '#6b7280';
?>
<style>
.inq-view-hero{background:linear-gradient(130deg,#08111f,#0f1f40,#162d5a);border-radius:18px;padding:1.8rem 2rem;margin-bottom:1.5rem;border:1px solid rgba(201,168,76,.18);box-shadow:0 12px 40px rgba(8,17,31,.3);}
.inq-view-ref{font-size:1.6rem;font-weight:900;color:#fff;letter-spacing:-.04em;}
.inq-view-sub{font-size:.85rem;color:rgba(255,255,255,.5);margin-top:.25rem;}
.inq-meta-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:1rem;margin-top:1.5rem;}
.inq-meta-item{background:rgba(255,255,255,.06);border-radius:12px;padding:.8rem 1rem;border:1px solid rgba(255,255,255,.08);}
.inq-meta-lbl{font-size:.65rem;color:rgba(255,255,255,.4);font-weight:700;text-transform:uppercase;letter-spacing:.06em;}
.inq-meta-val{font-size:.9rem;font-weight:700;color:#fff;margin-top:.25rem;}
.status-pill{display:inline-block;padding:.3rem .85rem;border-radius:99px;font-size:.78rem;font-weight:800;color:#fff;background:var(--sc);}
.vp-info-card{background:#fff;border-radius:16px;border:1px solid #edf0f8;box-shadow:0 2px 14px rgba(12,26,53,.07);margin-bottom:1.2rem;overflow:hidden;}
.vp-info-card-head{padding:.9rem 1.3rem;border-bottom:1px solid #f1f4fa;background:linear-gradient(90deg,#fafbff,#fff);font-size:.85rem;font-weight:800;color:#0c1a35;}
.vp-info-card-body{padding:1.2rem 1.4rem;}
</style>

<!-- Hero -->
<div class="inq-view-hero">
  <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">
    <div>
      <div class="inq-view-ref"><?= Helper::sanitize($inq['inquiry_ref']) ?></div>
      <div class="inq-view-sub">Created <?= Helper::formatDate($inq['created_at']) ?> by <?= Helper::sanitize($inq['creator_name']??'System') ?></div>
      <div class="mt-2">
        <span class="status-pill" style="--sc:<?= $sc ?>"><?= ucfirst($inq['status']) ?></span>
      </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <?php if ($inq['status'] !== 'converted'): ?>
      <a href="<?= BASE_URL ?>/modules/bookings/create.php?inquiry_id=<?= $id ?>" class="btn btn-vp-gold">
        Convert → Booking
      </a>
      <?php else: ?>
      <span class="badge bg-success" style="font-size:.8rem;padding:.5rem 1rem;">✓ Converted to Booking</span>
      <?php endif; ?>
      <a href="<?= BASE_URL ?>/modules/inquiries/index.php" class="btn btn-vp-outline" style="color:#fff;border-color:rgba(255,255,255,.3);">← Back</a>
    </div>
  </div>
  <div class="inq-meta-grid">
    <div class="inq-meta-item"><div class="inq-meta-lbl">Name</div><div class="inq-meta-val"><?= Helper::sanitize($inq['name']) ?></div></div>
    <div class="inq-meta-item"><div class="inq-meta-lbl">Mobile</div><div class="inq-meta-val"><?= Helper::sanitize($inq['mobile']) ?></div></div>
    <div class="inq-meta-item"><div class="inq-meta-lbl">Event Type</div><div class="inq-meta-val"><?= Helper::sanitize($inq['event_type']??'—') ?></div></div>
    <div class="inq-meta-item"><div class="inq-meta-lbl">Event Date</div><div class="inq-meta-val"><?= $inq['event_date'] ? Helper::formatDate($inq['event_date']) : '—' ?></div></div>
    <div class="inq-meta-item"><div class="inq-meta-lbl">Guests</div><div class="inq-meta-val"><?= $inq['guest_count'] ?: '—' ?></div></div>
    <div class="inq-meta-item"><div class="inq-meta-lbl">Hall</div><div class="inq-meta-val"><?= Helper::sanitize($inq['hall_name']??'—') ?></div></div>
    <div class="inq-meta-item"><div class="inq-meta-lbl">Source</div><div class="inq-meta-val"><?= Helper::sanitize($inq['source']??'—') ?></div></div>
    <div class="inq-meta-item"><div class="inq-meta-lbl">Follow-up</div><div class="inq-meta-val"><?= $inq['follow_up_date'] ? Helper::formatDate($inq['follow_up_date']) : '—' ?></div></div>
  </div>
</div>

<div class="row">
  <div class="col-lg-8">
    <!-- Notes -->
    <div class="vp-info-card">
      <div class="vp-info-card-head">📝 Notes</div>
      <div class="vp-info-card-body">
        <?= $inq['notes'] ? nl2br(Helper::sanitize($inq['notes'])) : '<span style="color:#9ca3af;font-size:.85rem;">No notes recorded.</span>' ?>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <!-- Update form -->
    <div class="vp-info-card">
      <div class="vp-info-card-head">✏️ Update Status</div>
      <div class="vp-info-card-body">
        <form method="post">
          <input type="hidden" name="_action" value="update_status">
          <div class="mb-3">
            <label class="form-label" style="font-size:.78rem;font-weight:700;">Status</label>
            <select name="status" class="form-select">
              <?php foreach (['new','contacted','quoted','converted','lost'] as $s): ?>
              <option value="<?= $s ?>" <?= $inq['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label" style="font-size:.78rem;font-weight:700;">Follow-up Date</label>
            <input type="date" name="follow_up_date" class="form-control" value="<?= $inq['follow_up_date'] ?>">
          </div>
          <div class="mb-3">
            <label class="form-label" style="font-size:.78rem;font-weight:700;">Notes</label>
            <textarea name="notes" class="form-control" rows="3"><?= Helper::sanitize($inq['notes']??'') ?></textarea>
          </div>
          <button type="submit" class="btn btn-vp-gold w-100">Update</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
