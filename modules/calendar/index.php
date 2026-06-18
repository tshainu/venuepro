<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
$db = Database::getInstance();
$cu = Auth::currentUser();
$bid = $cu['branch_id'];

$halls = $db->fetchAll("SELECT id, name FROM halls WHERE is_active=1 " . ($bid ? "AND branch_id=?" : ""), $bid ? [$bid] : []);

// Stats for header
$todayEvents = $db->fetchOne(
    "SELECT COUNT(*) as c FROM bookings WHERE event_date = CURDATE() AND status IN ('confirmed','tentative')" . ($bid ? " AND branch_id=?" : ""),
    $bid ? [$bid] : []
)['c'] ?? 0;
$thisMonthEvents = $db->fetchOne(
    "SELECT COUNT(*) as c FROM bookings WHERE MONTH(event_date)=MONTH(CURDATE()) AND YEAR(event_date)=YEAR(CURDATE()) AND status IN ('confirmed','tentative','completed')" . ($bid ? " AND branch_id=?" : ""),
    $bid ? [$bid] : []
)['c'] ?? 0;
$upcomingEvents = $db->fetchOne(
    "SELECT COUNT(*) as c FROM bookings WHERE event_date >= CURDATE() AND status IN ('confirmed','tentative')" . ($bid ? " AND branch_id=?" : ""),
    $bid ? [$bid] : []
)['c'] ?? 0;

$pageTitle = 'Calendar';
$breadcrumbs = [['label' => 'Calendar']];
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
/* ── Calendar Page Styles ──────────────────────────────── */
.cal-page-hero {
  background: linear-gradient(130deg,#08111f 0%,#0f1f40 40%,#162d5a 100%);
  border-radius: 20px;
  padding: 1.8rem 2rem;
  margin-bottom: 1.5rem;
  position: relative; overflow: hidden;
  border: 1px solid rgba(201,168,76,.18);
  box-shadow: 0 12px 40px rgba(8,17,31,.3);
}
.cal-page-hero::before {
  content:''; position:absolute; top:-80px; right:-60px;
  width:280px; height:280px; border-radius:50%;
  background: radial-gradient(circle, rgba(201,168,76,.18) 0%, transparent 70%);
  pointer-events:none;
}
.cal-hero-title {
  color:#fff; font-size:1.7rem; font-weight:800; letter-spacing:-.03em;
  display:flex; align-items:center; gap:.7rem;
}
.cal-hero-sub { color:rgba(255,255,255,.45); font-size:.8rem; margin-top:.3rem; }
.cal-hero-stats { display:flex; gap:2rem; flex-wrap:wrap; }
.cal-hero-stat { text-align:center; }
.cal-hero-stat-val { font-size:1.6rem; font-weight:800; color:#fff; line-height:1; }
.cal-hero-stat-val.gold { color:#e8c96a; }
.cal-hero-stat-lbl { font-size:.65rem; font-weight:700; color:rgba(255,255,255,.4); text-transform:uppercase; letter-spacing:.08em; margin-top:.2rem; }

.cal-filter-bar {
  background:#fff;
  border-radius:14px;
  border: 1px solid #edf0f8;
  box-shadow: 0 2px 10px rgba(12,26,53,.06);
  padding: .9rem 1.2rem;
  display:flex; align-items:center; flex-wrap:wrap; gap:.8rem;
  margin-bottom:1.2rem;
}
.cal-filter-label {
  font-size:.72rem; font-weight:700; color:#6b7280;
  text-transform:uppercase; letter-spacing:.07em;
}
.cal-legend { display:flex; align-items:center; gap:1.2rem; flex-wrap:wrap; margin-left:auto; }
.cal-legend-item { display:flex; align-items:center; gap:.4rem; font-size:.73rem; font-weight:600; color:#6b7280; }
.cal-legend-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; }

.cal-card {
  background:#fff; border-radius:18px;
  border: 1px solid #edf0f8;
  box-shadow: 0 4px 24px rgba(12,26,53,.08);
  overflow:hidden;
}
.cal-card-header {
  padding: 1.1rem 1.5rem;
  border-bottom: 1px solid #f1f4fa;
  display:flex; align-items:center; justify-content:space-between;
  flex-wrap:wrap; gap:.7rem;
}
.cal-card-title { font-size:.9rem; font-weight:800; color:#0c1a35; }
.cal-card-sub   { font-size:.72rem; color:#9ca3af; }
.cal-card-body  { padding: 1.2rem; }

/* FullCalendar Overrides */
#calendar .fc {
  font-family: 'Inter', system-ui, sans-serif;
}
#calendar .fc-toolbar-title {
  font-size: 1.1rem !important; font-weight: 800 !important; color: #0c1a35 !important;
}
#calendar .fc-button {
  background: #f1f4fa !important; color: #374151 !important;
  border: 1.5px solid #e5e7eb !important; border-radius: 9px !important;
  font-size: .78rem !important; font-weight: 700 !important;
  padding: .35rem .8rem !important; box-shadow: none !important;
  transition: all .15s !important;
}
#calendar .fc-button:hover { background: #0c1a35 !important; color: #fff !important; border-color: #0c1a35 !important; }
#calendar .fc-button-active,
#calendar .fc-button-primary:not(:disabled).fc-button-active {
  background: #0c1a35 !important; color: #fff !important; border-color: #0c1a35 !important;
}
#calendar .fc-today-button { background: #c9a84c !important; color: #fff !important; border-color: #c9a84c !important; }
#calendar .fc-daygrid-day-number {
  font-size: .78rem; font-weight: 700; color: #374151; padding: .3rem .5rem;
}
#calendar .fc-day-today .fc-daygrid-day-number {
  background: linear-gradient(135deg,#c9a84c,#e8c96a);
  color: #fff !important; border-radius: 50%; width: 26px; height: 26px;
  display: flex; align-items: center; justify-content: center;
  margin: .25rem;
}
#calendar .fc-day-today { background: rgba(201,168,76,.05) !important; }
#calendar .fc-col-header-cell-cushion {
  font-size: .72rem; font-weight: 800; color: #6b7280; text-transform: uppercase; letter-spacing: .06em;
  padding: .5rem 0;
}
#calendar .fc-event {
  border-radius: 6px !important; border: none !important;
  font-size: .71rem !important; font-weight: 700 !important;
  padding: 2px 7px !important; cursor: pointer !important;
  box-shadow: 0 2px 6px rgba(0,0,0,.15) !important;
  transition: transform .1s, box-shadow .1s !important;
}
#calendar .fc-event:hover {
  transform: translateY(-1px) !important;
  box-shadow: 0 4px 12px rgba(0,0,0,.25) !important;
}
#calendar .fc-daygrid-day:hover { background: #fafbff !important; cursor: pointer; }
#calendar .fc-daygrid-day.fc-day-past { opacity: .7; }
#calendar .fc-list-event:hover td { background: #fafbff !important; cursor: pointer; }

/* Day click ripple */
#calendar .fc-daygrid-day {
  transition: background .15s;
  position: relative;
}

/* Popover for event tooltip */
.cal-tooltip {
  position:fixed; background:#0c1a35; color:#fff; padding:.7rem 1rem;
  border-radius:10px; font-size:.78rem; pointer-events:none;
  box-shadow:0 8px 32px rgba(0,0,0,.35); z-index:9999; max-width:240px;
  border-left: 3px solid #c9a84c;
  transition:opacity .15s;
}
.cal-tooltip-title { font-weight:700; margin-bottom:.3rem; }
.cal-tooltip-row   { color:rgba(255,255,255,.65); font-size:.72rem; line-height:1.6; }

@media(max-width:768px){
  .cal-hero-stats { gap:1.2rem; }
  .cal-hero-stat-val { font-size:1.2rem; }
}
</style>

<!-- Hero Banner -->
<div class="cal-page-hero">
  <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:1rem;position:relative;z-index:2;">
    <div>
      <div class="cal-hero-title">
        <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#c9a84c" stroke-width="2">
          <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>
        </svg>
        Event Calendar
      </div>
      <div class="cal-hero-sub">Click any date to create a new booking · Click an event to view details</div>
    </div>
    <div class="cal-hero-stats">
      <div class="cal-hero-stat">
        <div class="cal-hero-stat-val gold"><?= $todayEvents ?></div>
        <div class="cal-hero-stat-lbl">Today</div>
      </div>
      <div style="width:1px;background:rgba(255,255,255,.1);align-self:stretch;"></div>
      <div class="cal-hero-stat">
        <div class="cal-hero-stat-val"><?= $thisMonthEvents ?></div>
        <div class="cal-hero-stat-lbl">This Month</div>
      </div>
      <div style="width:1px;background:rgba(255,255,255,.1);align-self:stretch;"></div>
      <div class="cal-hero-stat">
        <div class="cal-hero-stat-val gold"><?= $upcomingEvents ?></div>
        <div class="cal-hero-stat-lbl">Upcoming</div>
      </div>
    </div>
  </div>
</div>

<!-- Filter + Legend Bar -->
<div class="cal-filter-bar">
  <span class="cal-filter-label">Hall</span>
  <select id="filter-hall" class="form-select form-select-sm" style="max-width:180px;">
    <option value="">All Halls</option>
    <?php foreach ($halls as $h): ?>
    <option value="<?= $h['id'] ?>"><?= Helper::sanitize($h['name']) ?></option>
    <?php endforeach; ?>
  </select>

  <span class="cal-filter-label" style="margin-left:.5rem;">Status</span>
  <select id="filter-status" class="form-select form-select-sm" style="max-width:160px;">
    <option value="">All Status</option>
    <option value="inquiry">Inquiry</option>
    <option value="tentative">Tentative</option>
    <option value="confirmed">Confirmed</option>
    <option value="completed">Completed</option>
    <option value="cancelled">Cancelled</option>
  </select>

  <a href="<?= BASE_URL ?>/modules/bookings/create.php" class="btn btn-vp-gold btn-sm ms-2" style="border-radius:9px;">
    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="me-1"><path d="M12 5v14M5 12h14"/></svg>
    New Booking
  </a>

  <div class="cal-legend">
    <div class="cal-legend-item"><div class="cal-legend-dot" style="background:#059669"></div>Confirmed</div>
    <div class="cal-legend-item"><div class="cal-legend-dot" style="background:#d97706"></div>Tentative</div>
    <div class="cal-legend-item"><div class="cal-legend-dot" style="background:#7c3aed"></div>Inquiry</div>
    <div class="cal-legend-item"><div class="cal-legend-dot" style="background:#2563eb"></div>Completed</div>
    <div class="cal-legend-item"><div class="cal-legend-dot" style="background:#dc2626"></div>Cancelled</div>
  </div>
</div>

<!-- Calendar Card -->
<div class="cal-card">
  <div class="cal-card-body">
    <div id="calendar"></div>
  </div>
</div>

<!-- Tooltip -->
<div class="cal-tooltip" id="calTooltip" style="opacity:0;display:none;"></div>

<!-- FullCalendar -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var calendarEl = document.getElementById('calendar');
  var tooltip = document.getElementById('calTooltip');

  var calendar = new FullCalendar.Calendar(calendarEl, {
    initialView: 'dayGridMonth',
    headerToolbar: {
      left:   'prev,next today',
      center: 'title',
      right:  'dayGridMonth,timeGridWeek,listWeek'
    },
    height: 700,
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
      failure: function() { console.error('Could not load calendar events.'); }
    }],

    // Click on date → go to create booking with date pre-filled
    dateClick: function(info) {
      var date = info.dateStr;
      window.location = '<?= BASE_URL ?>/modules/bookings/create.php?date=' + date;
    },

    // Click on event → go to booking detail
    eventClick: function(info) {
      info.jsEvent.preventDefault();
      window.location = '<?= BASE_URL ?>/modules/bookings/view.php?id=' + info.event.id;
    },

    // Style events by status
    eventDidMount: function(info) {
      var s = info.event.extendedProps.status;
      var colors = {
        confirmed: {bg:'#059669', text:'#fff'},
        tentative: {bg:'#d97706', text:'#fff'},
        inquiry:   {bg:'#7c3aed', text:'#fff'},
        cancelled: {bg:'#dc2626', text:'#fff'},
        completed: {bg:'#2563eb', text:'#fff'},
      };
      var c = colors[s] || {bg:'#6b7280', text:'#fff'};
      info.el.style.background      = c.bg;
      info.el.style.borderColor     = c.bg;
      info.el.style.color           = c.text;
      info.el.style.boxShadow       = '0 2px 8px '+c.bg+'55';

      // Tooltip on hover
      info.el.addEventListener('mouseenter', function(e) {
        var ep = info.event.extendedProps;
        tooltip.innerHTML =
          '<div class="cal-tooltip-title">'+info.event.title.split('—')[0].trim()+'</div>'+
          '<div class="cal-tooltip-row">📅 '+info.event.startStr+'</div>'+
          '<div class="cal-tooltip-row">🏛 '+(ep.hall||'—')+'</div>'+
          '<div class="cal-tooltip-row">👥 '+(ep.guest_count||'—')+' guests</div>'+
          '<div class="cal-tooltip-row" style="margin-top:.3rem;"><span style="background:'+c.bg+';padding:2px 8px;border-radius:20px;font-size:.65rem;">'+s+'</span></div>';
        tooltip.style.display = 'block';
        setTimeout(function(){ tooltip.style.opacity = '1'; }, 10);
        positionTooltip(e);
      });
      info.el.addEventListener('mousemove', positionTooltip);
      info.el.addEventListener('mouseleave', function() {
        tooltip.style.opacity = '0';
        setTimeout(function(){ tooltip.style.display='none'; }, 160);
      });
    }
  });

  calendar.render();

  function positionTooltip(e) {
    var x = e.clientX + 14, y = e.clientY - 10;
    var tw = tooltip.offsetWidth || 240;
    if (x + tw > window.innerWidth) x = e.clientX - tw - 10;
    tooltip.style.left = x + 'px';
    tooltip.style.top  = y + 'px';
  }

  document.getElementById('filter-hall').addEventListener('change',   function(){ calendar.refetchEvents(); });
  document.getElementById('filter-status').addEventListener('change', function(){ calendar.refetchEvents(); });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
