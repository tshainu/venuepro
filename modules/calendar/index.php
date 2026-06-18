<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
$db = Database::getInstance();
$cu = Auth::currentUser();
$bid = $cu['branch_id'];

$halls = $db->fetchAll("SELECT id, name FROM halls WHERE 1 " . ($bid ? "AND branch_id=?" : ""), $bid ? [$bid] : []);

$pageTitle = Lang::t('calendar');
$breadcrumbs = [['label' => Lang::t('calendar')]];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="vp-page-header">
  <div>
    <h1 class="vp-page-title">📅 <?= Lang::t('calendar') ?></h1>
    <div class="vp-page-sub">Visual booking overview across all halls</div>
  </div>
  <a href="<?= BASE_URL ?>/modules/bookings/create.php" class="btn btn-vp-gold">
    <svg xmlns="http://www.w3.org/2000/svg" class="icon me-1" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14"/><path d="M5 12l14 0"/></svg>
    <?= Lang::t('new_booking') ?>
  </a>
</div>

<!-- Filter Bar -->
<div class="vp-filter-bar d-flex flex-wrap gap-3 align-items-center mb-3">
  <div class="d-flex align-items-center gap-2">
    <label class="form-label mb-0 fw-600 text-vp-navy" style="white-space:nowrap;">Hall</label>
    <select id="filter-hall" class="form-select form-select-sm" style="min-width:160px;">
      <option value="">All Halls</option>
      <?php foreach ($halls as $h): ?>
      <option value="<?= $h['id'] ?>"><?= Helper::sanitize($h['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="d-flex align-items-center gap-2">
    <label class="form-label mb-0 fw-600 text-vp-navy" style="white-space:nowrap;">Status</label>
    <select id="filter-status" class="form-select form-select-sm" style="min-width:140px;">
      <option value="">All Status</option>
      <option value="inquiry">Inquiry</option>
      <option value="tentative">Tentative</option>
      <option value="confirmed">Confirmed</option>
      <option value="completed">Completed</option>
      <option value="cancelled">Cancelled</option>
    </select>
  </div>
  <div class="ms-auto d-flex gap-2 flex-wrap">
    <span class="vp-badge badge-confirmed">Confirmed</span>
    <span class="vp-badge badge-tentative">Tentative</span>
    <span class="vp-badge badge-inquiry">Inquiry</span>
    <span class="vp-badge badge-completed">Completed</span>
    <span class="vp-badge badge-cancelled">Cancelled</span>
  </div>
</div>

<!-- Calendar Card -->
<div class="card vp-card">
  <div class="card-body" style="padding:1.25rem;">
    <div id="calendar"></div>
  </div>
</div>

<!-- FullCalendar -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var calendarEl = document.getElementById('calendar');
  var calendar = new FullCalendar.Calendar(calendarEl, {
    initialView: 'dayGridMonth',
    headerToolbar: {
      left: 'prev,next today',
      center: 'title',
      right: 'dayGridMonth,timeGridWeek,listWeek'
    },
    height: 680,
    eventSources: [{
      url: '<?= BASE_URL ?>/modules/calendar/events.php',
      method: 'GET',
      extraParams: function() {
        return {
          hall_id:   document.getElementById('filter-hall').value,
          status:    document.getElementById('filter-status').value,
          branch_id: <?= (int)($bid ?? 0) ?>
        };
      },
      failure: function() { alert('Could not load calendar events.'); }
    }],
    eventClick: function(info) {
      window.location = '<?= BASE_URL ?>/modules/bookings/view.php?id=' + info.event.id;
    },
    eventDidMount: function(info) {
      var s = info.event.extendedProps.status;
      var colors = {
        confirmed:'#059669', tentative:'#d97706',
        inquiry:'#7c3aed', cancelled:'#dc2626', completed:'#2563eb'
      };
      if (colors[s]) {
        info.el.style.background = colors[s];
        info.el.style.borderColor = colors[s];
      }
      info.el.setAttribute('title', info.event.title + ' [' + (s||'') + ']');
    }
  });
  calendar.render();

  document.getElementById('filter-hall').addEventListener('change',   function(){ calendar.refetchEvents(); });
  document.getElementById('filter-status').addEventListener('change', function(){ calendar.refetchEvents(); });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
