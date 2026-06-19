<!doctype html>
<html lang="<?= Lang::current() ?>">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
  <meta http-equiv="X-UA-Compatible" content="ie=edge"/>
  <title><?= isset($pageTitle) ? Helper::sanitize($pageTitle) . ' — ' : '' ?><?= APP_NAME ?></title>
  <link rel="shortcut icon" href="<?= BASE_URL ?>/assets/images/favicon.png"/>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta21/dist/css/tabler.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Sans+Sinhala:wght@400;600&family=Noto+Sans+Tamil:wght@400;600&display=swap" rel="stylesheet">

  <style>
  /* ════════════════════════════════════════════════════
     SIDEBAR — Premium Navy + Gold
  ════════════════════════════════════════════════════ */
  :root {
    --sb-width: 252px;
    --sb-bg1:  #08111f;
    --sb-bg2:  #0c1830;
    --sb-bg3:  #0f1f3d;
    --gold:    #c9a84c;
    --gold-lt: #e8c96a;
    --gold-dim: rgba(201,168,76,.12);
  }

  /* override Tabler layout */
  .wrapper { display:flex; min-height:100vh; }
  .vp-sidebar {
    width: var(--sb-width);
    min-height: 100vh;
    background: linear-gradient(180deg, var(--sb-bg1) 0%, var(--sb-bg2) 50%, var(--sb-bg3) 100%);
    display: flex; flex-direction: column;
    flex-shrink: 0; position: fixed; left:0; top:0; bottom:0;
    z-index: 1000;
    border-right: 1px solid rgba(201,168,76,.14);
    box-shadow: 6px 0 40px rgba(0,0,0,.35);
    overflow-y: auto; overflow-x: hidden;
  }
  .vp-sidebar::-webkit-scrollbar { width:3px; }
  .vp-sidebar::-webkit-scrollbar-track { background:transparent; }
  .vp-sidebar::-webkit-scrollbar-thumb { background:rgba(201,168,76,.2); border-radius:3px; }

  /* push main content */
  .page-wrapper { margin-left: var(--sb-width) !important; }

  /* ── Logo ─────────────────────────────────────────── */
  .sb-logo {
    padding: 1.4rem 1.2rem 1.2rem;
    border-bottom: 1px solid rgba(255,255,255,.06);
    display: flex; align-items: center; gap: .8rem;
    text-decoration: none; flex-shrink: 0;
    position: relative;
  }
  .sb-logo::after {
    content:''; position:absolute; bottom:0; left:1.2rem; right:1.2rem; height:1px;
    background: linear-gradient(90deg, var(--gold), transparent);
    opacity:.3;
  }
  .sb-logo-icon {
    width: 44px; height: 44px; border-radius: 12px; flex-shrink:0;
    background: linear-gradient(135deg, #a07820, var(--gold), var(--gold-lt));
    display: flex; align-items: center; justify-content: center;
    font-size: 1.35rem;
    box-shadow: 0 4px 16px rgba(201,168,76,.5), 0 0 0 1px rgba(201,168,76,.3);
  }
  .sb-logo-text { line-height: 1.2; }
  .sb-logo-name {
    color: #fff; font-size: .95rem; font-weight: 800;
    letter-spacing: -.01em; display: block;
  }
  .sb-logo-sub {
    color: var(--gold); font-size: .6rem; font-weight: 700;
    letter-spacing: .1em; text-transform: uppercase; opacity: .8;
  }

  /* ── Nav body ─────────────────────────────────────── */
  .sb-nav { flex: 1; padding: .8rem 0 0; }

  /* Section label */
  .sb-section {
    display: flex; align-items: center; gap: .6rem;
    padding: 1.1rem 1.2rem .35rem;
    font-size: .58rem; font-weight: 800;
    color: rgba(201,168,76,.5);
    text-transform: uppercase; letter-spacing: .14em;
  }
  .sb-section::after {
    content:''; flex:1; height:1px;
    background: linear-gradient(90deg, rgba(201,168,76,.2), transparent);
  }

  /* Nav item */
  .sb-item {
    display: flex; align-items: center; gap: .7rem;
    padding: .55rem 1rem .55rem 1.1rem;
    margin: 2px .7rem;
    border-radius: 10px;
    text-decoration: none;
    color: rgba(255,255,255,.52);
    font-size: .82rem; font-weight: 500;
    transition: all .18s ease;
    position: relative; overflow: hidden;
  }
  .sb-item:hover {
    color: rgba(255,255,255,.88);
    background: rgba(255,255,255,.06);
    text-decoration: none;
  }
  /* active */
  .sb-item.active {
    color: #fff !important;
    background: linear-gradient(90deg, rgba(201,168,76,.16), rgba(201,168,76,.04)) !important;
    font-weight: 700;
  }
  .sb-item.active::before {
    content: '';
    position: absolute; left: 0; top: 0; bottom: 0;
    width: 3px; border-radius: 0 3px 3px 0;
    background: linear-gradient(180deg, var(--gold-lt), var(--gold));
  }
  /* icon box */
  .sb-icon {
    width: 34px; height: 34px; border-radius: 9px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    transition: all .18s;
    background: rgba(255,255,255,.05);
  }
  .sb-icon svg { width: 17px; height: 17px; stroke: rgba(255,255,255,.55); transition: stroke .18s; }
  .sb-item:hover .sb-icon { background: rgba(255,255,255,.09); }
  .sb-item:hover .sb-icon svg { stroke: rgba(255,255,255,.9); }
  .sb-item.active .sb-icon {
    background: linear-gradient(135deg, rgba(201,168,76,.25), rgba(201,168,76,.1));
    box-shadow: 0 2px 8px rgba(201,168,76,.2);
  }
  .sb-item.active .sb-icon svg { stroke: var(--gold-lt); }

  /* label */
  .sb-label { flex:1; }

  /* badge pill on item (e.g. today count) */
  .sb-pill {
    font-size: .6rem; font-weight: 800;
    background: var(--gold); color: #0c1a35;
    border-radius: 20px; padding: .1rem .45rem;
    flex-shrink: 0;
  }

  /* ── Bottom zone ──────────────────────────────────── */
  .sb-bottom {
    border-top: 1px solid rgba(255,255,255,.06);
    padding: .6rem 0;
    position: relative;
  }
  .sb-bottom::before {
    content:''; position:absolute; top:0; left:1.2rem; right:1.2rem; height:1px;
    background: linear-gradient(90deg, transparent, rgba(201,168,76,.2), transparent);
  }
  .sb-bottom-item {
    display: flex; align-items: center; gap: .65rem;
    padding: .48rem 1rem .48rem 1.1rem;
    margin: 1px .7rem;
    border-radius: 9px;
    text-decoration: none;
    color: rgba(255,255,255,.42);
    font-size: .78rem; font-weight: 500;
    transition: all .16s;
  }
  .sb-bottom-item:hover { color: rgba(255,255,255,.78); background: rgba(255,255,255,.05); }
  .sb-bottom-item svg { width:15px; height:15px; stroke: rgba(255,255,255,.4); flex-shrink:0; transition: stroke .16s; }
  .sb-bottom-item:hover svg { stroke: rgba(255,255,255,.75); }
  .sb-bottom-item.danger { color: rgba(239,68,68,.65); }
  .sb-bottom-item.danger:hover { color: #f87171; background: rgba(239,68,68,.08); }
  .sb-bottom-item.danger svg { stroke: rgba(239,68,68,.6); }
  .sb-bottom-item.danger:hover svg { stroke: #f87171; }

  /* ── User card ──────────────────────────────────── */
  .sb-user {
    display: flex; align-items: center; gap: .75rem;
    padding: .85rem 1.1rem;
    margin: .4rem .7rem .6rem;
    border-radius: 12px;
    background: rgba(255,255,255,.04);
    border: 1px solid rgba(255,255,255,.07);
  }
  .sb-user-avatar {
    width: 38px; height: 38px; border-radius: 50%;
    background: linear-gradient(135deg, #a07820, var(--gold));
    color: #0c1a35; font-size: .85rem; font-weight: 900;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 3px 10px rgba(201,168,76,.45);
  }
  .sb-user-name { color:#fff; font-size:.82rem; font-weight:700; line-height:1.2; }
  .sb-user-role { color: var(--gold); font-size:.62rem; font-weight:600; opacity:.8; }

  /* ── Topbar ────────────────────────────────────────── */
  .vp-topbar {
    height: 58px;
    background: #fff;
    border-bottom: 2px solid rgba(201,168,76,.12);
    box-shadow: 0 2px 12px rgba(12,26,53,.06);
    display: flex; align-items: center;
    padding: 0 1.75rem; gap: 1rem;
    position: sticky; top:0; z-index:100;
  }
  .vp-topbar-breadcrumb {
    display: flex; align-items: center; gap: .4rem;
    font-size: .8rem; flex:1;
  }
  .vp-topbar-breadcrumb a {
    color: #9ca3af; font-weight: 500; text-decoration: none;
    transition: color .15s;
  }
  .vp-topbar-breadcrumb a:hover { color: #0c1a35; }
  .vp-topbar-breadcrumb .sep { color: #d1d5db; font-size: .7rem; }
  .vp-topbar-breadcrumb .current { color: #1f2937; font-weight: 700; }

  .vp-topbar-right { display:flex; align-items:center; gap:.75rem; flex-shrink:0; }

  .vp-topbar-branch {
    display: flex; align-items: center; gap: .5rem;
    background: linear-gradient(135deg,#0c1a35,#1a3060);
    border: none;
    border-radius: 24px; padding: .42rem 1.1rem .42rem .75rem;
    font-size: .8rem; font-weight: 700; color: #fff;
    letter-spacing: .01em;
    box-shadow: 0 2px 10px rgba(12,26,53,.25);
  }
  .vp-topbar-branch .branch-company {
    color: #c9a84c; font-size: .68rem; font-weight: 600; letter-spacing: .04em; text-transform: uppercase;
  }
  .vp-topbar-branch .branch-sep { color: rgba(255,255,255,.3); }
  .vp-topbar-branch svg { width:15px; height:15px; stroke:#c9a84c; flex-shrink:0; }

  .vp-topbar-user {
    display: flex; align-items: center; gap: .55rem;
    background: #f6f8fd; border: 1.5px solid #e2e8f4;
    border-radius: 20px; padding: .3rem .9rem .3rem .4rem;
    cursor: pointer; text-decoration: none;
    transition: all .15s;
  }
  .vp-topbar-user:hover { border-color: #0c1a35; background: #f0f3fa; }
  .vp-topbar-avatar {
    width: 28px; height: 28px; border-radius: 50%;
    background: linear-gradient(135deg, #0c1a35, #1a3060);
    color: #fff; font-size: .7rem; font-weight: 800;
    display: flex; align-items: center; justify-content: center;
  }
  .vp-topbar-uname { font-size: .78rem; font-weight: 700; color: #1f2937; }
  .vp-topbar-urole { font-size: .64rem; color: #9ca3af; }

  /* mobile toggle */
  .sb-mobile-toggle {
    display:none; background:none; border:none; padding:.5rem;
    cursor:pointer; color:#0c1a35;
  }
  @media(max-width:991px) {
    .vp-sidebar { transform: translateX(-100%); transition: transform .25s ease; }
    .vp-sidebar.open { transform: translateX(0); }
    .page-wrapper { margin-left: 0 !important; }
    .sb-mobile-toggle { display:flex; align-items:center; }
    .sb-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:999; }
    .sb-overlay.show { display:block; }
  }
  </style>
</head>
<body class="antialiased">

<?php $cu = Auth::currentUser(); ?>
<?php
$p = $_SERVER['PHP_SELF'];
function sbActive(string $path, string $match, bool $exact = false): string {
  if ($exact) return (basename($path) === basename($match) && strpos($path, '/modules') === false) ? 'active' : '';
  return strpos($path, $match) !== false ? 'active' : '';
}
?>

<!-- Mobile overlay -->
<div class="sb-overlay" id="sbOverlay" onclick="closeSidebar()"></div>

<div class="wrapper">
  <!-- ═══════ SIDEBAR ═══════ -->
  <aside class="vp-sidebar" id="vp-sidebar">

    <!-- Logo -->
    <a href="<?= BASE_URL ?>/index.php" class="sb-logo">
      <div class="sb-logo-icon">🏛️</div>
      <div class="sb-logo-text">
        <span class="sb-logo-name">VenuePro</span>
        <span class="sb-logo-sub">Event Management</span>
      </div>
    </a>

    <!-- Nav -->
    <nav class="sb-nav">

      <!-- MAIN -->
      <a href="<?= BASE_URL ?>/index.php" class="sb-item <?= sbActive($p, 'index.php', true) ?>">
        <div class="sb-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 12L5 10M12 3L21 12H19V20H15V16H9V20H5V12"/><path d="M9 21V12H15V21"/>
          </svg>
        </div>
        <span class="sb-label">Dashboard</span>
      </a>

      <a href="<?= BASE_URL ?>/modules/calendar/index.php" class="sb-item <?= sbActive($p, '/calendar') ?>">
        <div class="sb-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>
          </svg>
        </div>
        <span class="sb-label">Calendar</span>
      </a>

      <a href="<?= BASE_URL ?>/modules/bookings/index.php" class="sb-item <?= sbActive($p, '/bookings') ?>">
        <div class="sb-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14 3v4a1 1 0 001 1h4"/><path d="M17 21H7a2 2 0 01-2-2V5a2 2 0 012-2h7l5 5v11a2 2 0 01-2 2z"/>
          </svg>
        </div>
        <span class="sb-label">Bookings</span>
      </a>

      <a href="<?= BASE_URL ?>/modules/customers/index.php" class="sb-item <?= sbActive($p, '/customers') ?>">
        <div class="sb-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="8" r="4"/><path d="M6 21v-2a4 4 0 014-4h4a4 4 0 014 4v2"/>
          </svg>
        </div>
        <span class="sb-label">Customers</span>
      </a>

      <!-- VENUE -->
      <div class="sb-section">Venue</div>

      <a href="<?= BASE_URL ?>/modules/halls/index.php" class="sb-item <?= sbActive($p, '/halls') ?>">
        <div class="sb-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 21h18M3 7l9-4 9 4M4 21V7.5L12 4l8 3.5V21"/><path d="M9 21v-6h6v6"/>
          </svg>
        </div>
        <span class="sb-label">Halls</span>
      </a>

      <a href="<?= BASE_URL ?>/modules/rooms/index.php" class="sb-item <?= sbActive($p, '/rooms') ?>">
        <div class="sb-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="2" y="5" width="20" height="14" rx="2"/><path d="M7 12h10"/>
          </svg>
        </div>
        <span class="sb-label">Rooms</span>
      </a>

      <!-- FINANCE -->
      <div class="sb-section">Finance</div>

      <a href="<?= BASE_URL ?>/modules/packages/index.php" class="sb-item <?= sbActive($p, '/packages') ?>">
        <div class="sb-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="12 3 20 7.5 20 16.5 12 21 4 16.5 4 7.5 12 3"/>
            <line x1="12" y1="12" x2="20" y2="7.5"/><line x1="12" y1="12" x2="12" y2="21"/><line x1="12" y1="12" x2="4" y2="7.5"/>
          </svg>
        </div>
        <span class="sb-label">Packages</span>
      </a>

      <a href="<?= BASE_URL ?>/modules/addons/index.php" class="sb-item <?= sbActive($p, '/addons') ?>">
        <div class="sb-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 2v4m0 12v4M4.93 4.93l2.83 2.83m8.48 8.48 2.83 2.83M2 12h4m12 0h4M4.93 19.07l2.83-2.83m8.48-8.48 2.83-2.83"/>
          </svg>
        </div>
        <span class="sb-label">Add-ons</span>
      </a>

      <a href="<?= BASE_URL ?>/modules/quotations/index.php" class="sb-item <?= sbActive($p, '/quotations') ?>">
        <div class="sb-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/>
            <rect x="9" y="3" width="6" height="4" rx="2"/><path d="M9 12h6M9 16h4"/>
          </svg>
        </div>
        <span class="sb-label">Quotations</span>
      </a>

      <a href="<?= BASE_URL ?>/modules/invoices/index.php" class="sb-item <?= sbActive($p, '/invoices') ?>">
        <div class="sb-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M5 21V5a2 2 0 012-2h10a2 2 0 012 2v16l-3-2-2 2-2-2-2 2-2-2-3 2"/>
            <path d="M14 8h-2.5a1.5 1.5 0 000 3h1a1.5 1.5 0 010 3H10M12 8v1m0 6v1"/>
          </svg>
        </div>
        <span class="sb-label">Invoices</span>
      </a>

      <a href="<?= BASE_URL ?>/modules/payments/index.php" class="sb-item <?= sbActive($p, '/payments') ?>">
        <div class="sb-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="2" y="6" width="20" height="13" rx="2"/><path d="M2 10h20"/>
          </svg>
        </div>
        <span class="sb-label">Payments</span>
      </a>

      <a href="<?= BASE_URL ?>/modules/reports/index.php" class="sb-item <?= sbActive($p, '/reports') ?>">
        <div class="sb-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 20V10M12 20V4M6 20v-6"/>
          </svg>
        </div>
        <span class="sb-label">Reports</span>
      </a>

      <?php if (Auth::isSuperAdmin()): ?>
      <!-- ADMIN -->
      <div class="sb-section">Administration</div>

      <a href="<?= BASE_URL ?>/modules/users/index.php" class="sb-item <?= sbActive($p, '/users') ?>">
        <div class="sb-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="9" cy="7" r="4"/><path d="M3 21v-2a4 4 0 014-4h4a4 4 0 014 4v2"/>
            <path d="M16 3.13a4 4 0 010 7.75M21 21v-2a4 4 0 00-3-3.85"/>
          </svg>
        </div>
        <span class="sb-label">Users</span>
      </a>

      <a href="<?= BASE_URL ?>/modules/branches/index.php" class="sb-item <?= sbActive($p, '/branches') ?>">
        <div class="sb-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M6 3v12M18 9a3 3 0 100-6 3 3 0 000 6zM6 21a3 3 0 100-6 3 3 0 000 6zM15 6H6M6 15h9"/>
          </svg>
        </div>
        <span class="sb-label">Branches</span>
      </a>

      <a href="<?= BASE_URL ?>/modules/settings/index.php" class="sb-item <?= sbActive($p, '/settings') ?>">
        <div class="sb-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="3"/>
            <path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/>
          </svg>
        </div>
        <span class="sb-label">Settings</span>
      </a>
      <?php endif; ?>

    </nav>

    <!-- Bottom -->
    <div class="sb-bottom">
      <!-- User card -->
      <div class="sb-user">
        <div class="sb-user-avatar"><?= strtoupper(substr($cu['name'], 0, 1)) ?></div>
        <div style="min-width:0;flex:1;">
          <div class="sb-user-name" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= Helper::sanitize($cu['name']) ?></div>
          <div class="sb-user-role"><?= ucfirst(str_replace('_', ' ', $cu['role'])) ?></div>
        </div>
      </div>

      <a href="<?= BASE_URL ?>/modules/auth/lang_switch.php" class="sb-bottom-item">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 010 20M12 2a15.3 15.3 0 000 20"/>
        </svg>
        <span><?= strtoupper(Lang::current()) ?> &mdash; Language</span>
      </a>

      <a href="<?= BASE_URL ?>/modules/auth/logout.php" class="sb-bottom-item danger">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/>
        </svg>
        <span>Log Out</span>
      </a>
    </div>

  </aside>

  <!-- ═══════ MAIN ═══════ -->
  <div class="page-wrapper">

    <!-- Topbar -->
    <div class="vp-topbar">
      <!-- Mobile toggle -->
      <button class="sb-mobile-toggle" onclick="openSidebar()">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#0c1a35" stroke-width="2.5" stroke-linecap="round">
          <path d="M3 6h18M3 12h18M3 18h18"/>
        </svg>
      </button>

      <!-- Breadcrumb -->
      <div class="vp-topbar-breadcrumb">
        <?php if (isset($breadcrumbs) && $breadcrumbs): ?>
          <a href="<?= BASE_URL ?>/index.php">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" style="margin-top:-2px"><path d="M3 12L5 10M12 3L21 12H19V20H15V16H9V20H5V12"/></svg>
          </a>
          <?php foreach ($breadcrumbs as $bc): ?>
            <span class="sep">›</span>
            <?php if (isset($bc['url'])): ?>
              <a href="<?= $bc['url'] ?>"><?= Helper::sanitize($bc['label']) ?></a>
            <?php else: ?>
              <span class="current"><?= Helper::sanitize($bc['label']) ?></span>
            <?php endif; ?>
          <?php endforeach; ?>
        <?php else: ?>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2.5" stroke-linecap="round" style="margin-top:-2px"><path d="M3 12L5 10M12 3L21 12H19V20H15V16H9V20H5V12"/></svg>
          <span class="sep">›</span>
          <span class="current"><?= isset($pageTitle) ? Helper::sanitize($pageTitle) : 'Dashboard' ?></span>
        <?php endif; ?>
      </div>

      <!-- Right side -->
      <div class="vp-topbar-right">
        <!-- Branch badge -->
        <div class="vp-topbar-branch">
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 21h18M3 7l9-4 9 4M4 21V7.5L12 4l8 3.5V21"/>
          </svg>
          <div>
            <div class="branch-company">VenuePro</div>
            <div><?= Helper::sanitize($cu['branch_name'] ?? 'All Branches') ?></div>
          </div>
        </div>

        <!-- User dropdown -->
        <div class="nav-item dropdown">
          <a href="#" class="vp-topbar-user" data-bs-toggle="dropdown">
            <div class="vp-topbar-avatar"><?= strtoupper(substr($cu['name'], 0, 1)) ?></div>
            <div class="d-none d-md-block">
              <div class="vp-topbar-uname"><?= Helper::sanitize($cu['name']) ?></div>
              <div class="vp-topbar-urole"><?= ucfirst(str_replace('_', ' ', $cu['role'])) ?></div>
            </div>
          </a>
          <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
            <a href="<?= BASE_URL ?>/modules/auth/logout.php" class="dropdown-item text-danger">Log Out</a>
          </div>
        </div>
      </div>
    </div>

    <div class="page-body">
      <div class="container-xl">
        <?php $flash = Helper::getFlash(); if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : ($flash['type'] === 'error' ? 'danger' : 'warning') ?> alert-dismissible mt-2" role="alert">
          <div><?= Helper::sanitize($flash['message']) ?></div>
          <a class="btn-close" data-bs-dismiss="alert" aria-label="close"></a>
        </div>
        <?php endif; ?>

<script>
function openSidebar()  { document.getElementById('vp-sidebar').classList.add('open'); document.getElementById('sbOverlay').classList.add('show'); }
function closeSidebar() { document.getElementById('vp-sidebar').classList.remove('open'); document.getElementById('sbOverlay').classList.remove('show'); }
</script>
