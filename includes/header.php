<!doctype html>
<html lang="<?= Lang::current() ?>">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
  <meta http-equiv="X-UA-Compatible" content="ie=edge"/>
  <title><?= isset($pageTitle) ? Helper::sanitize($pageTitle) . ' — ' : '' ?><?= APP_NAME ?></title>
  <link rel="shortcut icon" href="<?= BASE_URL ?>/assets/images/favicon.png"/>
  <!-- Tabler CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta21/dist/css/tabler.min.css">
  <!-- VenuePro Theme -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
  <!-- Multilingual fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Sans+Sinhala:wght@400;600&family=Noto+Sans+Tamil:wght@400;600&display=swap" rel="stylesheet">
</head>
<body class="antialiased">
<div class="wrapper">
  <!-- Sidebar -->
  <aside class="navbar navbar-vertical navbar-expand-lg" data-bs-theme="dark">
    <div class="container-fluid">
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-menu">
        <span class="navbar-toggler-icon"></span>
      </button>
      <!-- Brand -->
      <h1 class="navbar-brand navbar-brand-autodark">
        <a href="<?= BASE_URL ?>/index.php" class="vp-brand">
          <span class="vp-brand-icon">🏛️</span>
          <span class="vp-brand-text">VenuePro Lanka<span class="vp-brand-sub">Event Management</span></span>
        </a>
      </h1>

      <div class="collapse navbar-collapse" id="sidebar-menu">
        <ul class="navbar-nav pt-lg-3">
          <?php $cu = Auth::currentUser(); ?>

          <li class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'index.php' && strpos($_SERVER['PHP_SELF'],'/modules') === false ? 'active' : '' ?>">
            <a class="nav-link" href="<?= BASE_URL ?>/index.php">
              <span class="nav-link-icon d-md-none d-lg-inline-block">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l-2 0l9 -9l9 9l-2 0"/><path d="M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-7"/><path d="M9 21v-6a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v6"/></svg>
              </span>
              <span class="nav-link-title"><?= Lang::t('dashboard') ?></span>
            </a>
          </li>

          <li class="nav-item <?= strpos($_SERVER['PHP_SELF'], '/calendar') !== false ? 'active' : '' ?>">
            <a class="nav-link" href="<?= BASE_URL ?>/modules/calendar/index.php">
              <span class="nav-link-icon d-md-none d-lg-inline-block">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><rect x="4" y="5" width="16" height="16" rx="2"/><line x1="16" y1="3" x2="16" y2="7"/><line x1="8" y1="3" x2="8" y2="7"/><line x1="4" y1="11" x2="20" y2="11"/></svg>
              </span>
              <span class="nav-link-title"><?= Lang::t('calendar') ?></span>
            </a>
          </li>

          <li class="nav-item <?= strpos($_SERVER['PHP_SELF'], '/bookings') !== false ? 'active' : '' ?>">
            <a class="nav-link" href="<?= BASE_URL ?>/modules/bookings/index.php">
              <span class="nav-link-icon d-md-none d-lg-inline-block">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M14 3v4a1 1 0 0 0 1 1h4"/><path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z"/></svg>
              </span>
              <span class="nav-link-title"><?= Lang::t('bookings') ?></span>
            </a>
          </li>

          <li class="nav-item <?= strpos($_SERVER['PHP_SELF'], '/customers') !== false ? 'active' : '' ?>">
            <a class="nav-link" href="<?= BASE_URL ?>/modules/customers/index.php">
              <span class="nav-link-icon d-md-none d-lg-inline-block">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><circle cx="12" cy="7" r="4"/><path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2"/></svg>
              </span>
              <span class="nav-link-title"><?= Lang::t('customers') ?></span>
            </a>
          </li>

          <!-- Venue Setup -->
          <li class="nav-section">Venue</li>

          <li class="nav-item <?= strpos($_SERVER['PHP_SELF'], '/halls') !== false ? 'active' : '' ?>">
            <a class="nav-link" href="<?= BASE_URL ?>/modules/halls/index.php">
              <span class="nav-link-icon d-md-none d-lg-inline-block">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 21l18 0"/><path d="M3 7l9 -4l9 4"/><path d="M4 21v-12.5l8 -4l8 4v12.5"/></svg>
              </span>
              <span class="nav-link-title"><?= Lang::t('halls') ?></span>
            </a>
          </li>

          <li class="nav-item <?= strpos($_SERVER['PHP_SELF'], '/rooms') !== false ? 'active' : '' ?>">
            <a class="nav-link" href="<?= BASE_URL ?>/modules/rooms/index.php">
              <span class="nav-link-icon d-md-none d-lg-inline-block">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M7 12h10"/><rect x="3" y="5" width="18" height="14" rx="2"/></svg>
              </span>
              <span class="nav-link-title"><?= Lang::t('rooms') ?></span>
            </a>
          </li>

          <!-- Finance -->
          <li class="nav-section">Finance</li>

          <li class="nav-item <?= strpos($_SERVER['PHP_SELF'], '/packages') !== false ? 'active' : '' ?>">
            <a class="nav-link" href="<?= BASE_URL ?>/modules/packages/index.php">
              <span class="nav-link-icon d-md-none d-lg-inline-block">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><polyline points="12 3 20 7.5 20 16.5 12 21 4 16.5 4 7.5 12 3"/><line x1="12" y1="12" x2="20" y2="7.5"/><line x1="12" y1="12" x2="12" y2="21"/><line x1="12" y1="12" x2="4" y2="7.5"/></svg>
              </span>
              <span class="nav-link-title"><?= Lang::t('packages') ?></span>
            </a>
          </li>

          <li class="nav-item <?= strpos($_SERVER['PHP_SELF'], '/addons') !== false ? 'active' : '' ?>">
            <a class="nav-link" href="<?= BASE_URL ?>/modules/addons/index.php">
              <span class="nav-link-icon d-md-none d-lg-inline-block">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="M20 12h2"/><path d="M2 12h2"/></svg>
              </span>
              <span class="nav-link-title"><?= Lang::t('addons') ?></span>
            </a>
          </li>

          <li class="nav-item <?= strpos($_SERVER['PHP_SELF'], '/quotations') !== false ? 'active' : '' ?>">
            <a class="nav-link" href="<?= BASE_URL ?>/modules/quotations/index.php">
              <span class="nav-link-icon d-md-none d-lg-inline-block">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 5h-2a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-12a2 2 0 0 0 -2 -2h-2"/><rect x="9" y="3" width="6" height="4" rx="2"/><line x1="9" y1="12" x2="15" y2="12"/><line x1="9" y1="16" x2="12" y2="16"/></svg>
              </span>
              <span class="nav-link-title"><?= Lang::t('quotations') ?></span>
            </a>
          </li>

          <li class="nav-item <?= strpos($_SERVER['PHP_SELF'], '/invoices') !== false ? 'active' : '' ?>">
            <a class="nav-link" href="<?= BASE_URL ?>/modules/invoices/index.php">
              <span class="nav-link-icon d-md-none d-lg-inline-block">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 21v-16a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v16l-3 -2l-2 2l-2 -2l-2 2l-2 -2l-3 2"/><path d="M14 8h-2.5a1.5 1.5 0 0 0 0 3h1a1.5 1.5 0 0 1 0 3h-2.5"/><path d="M12 8v1m0 6v1"/></svg>
              </span>
              <span class="nav-link-title"><?= Lang::t('invoices') ?></span>
            </a>
          </li>

          <li class="nav-item <?= strpos($_SERVER['PHP_SELF'], '/payments') !== false ? 'active' : '' ?>">
            <a class="nav-link" href="<?= BASE_URL ?>/modules/payments/index.php">
              <span class="nav-link-icon d-md-none d-lg-inline-block">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10l18 0"/></svg>
              </span>
              <span class="nav-link-title"><?= Lang::t('payments') ?></span>
            </a>
          </li>

          <li class="nav-item <?= strpos($_SERVER['PHP_SELF'], '/reports') !== false ? 'active' : '' ?>">
            <a class="nav-link" href="<?= BASE_URL ?>/modules/reports/index.php">
              <span class="nav-link-icon d-md-none d-lg-inline-block">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M8 5h-2a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h5.697"/><path d="M18 14v4h4"/><path d="M18 11v-4a2 2 0 0 0 -2 -2h-2"/><rect x="10" y="3" width="4" height="4" rx="1"/><circle cx="18" cy="18" r="4"/><path d="M8 9l2 0"/><path d="M8 13l4 0"/></svg>
              </span>
              <span class="nav-link-title"><?= Lang::t('reports') ?></span>
            </a>
          </li>

          <?php if (Auth::isSuperAdmin()): ?>
          <!-- Admin -->
          <li class="nav-section">Administration</li>

          <li class="nav-item <?= strpos($_SERVER['PHP_SELF'], '/users') !== false ? 'active' : '' ?>">
            <a class="nav-link" href="<?= BASE_URL ?>/modules/users/index.php">
              <span class="nav-link-icon d-md-none d-lg-inline-block">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><circle cx="9" cy="7" r="4"/><path d="M3 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/><path d="M21 21v-2a4 4 0 0 0 -3 -3.85"/></svg>
              </span>
              <span class="nav-link-title"><?= Lang::t('users') ?></span>
            </a>
          </li>

          <li class="nav-item <?= strpos($_SERVER['PHP_SELF'], '/branches') !== false ? 'active' : '' ?>">
            <a class="nav-link" href="<?= BASE_URL ?>/modules/branches/index.php">
              <span class="nav-link-icon d-md-none d-lg-inline-block">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M6 3v12"/><path d="M18 9a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/><path d="M6 21a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/><path d="M15 6h-9"/><path d="M6 15h9"/></svg>
              </span>
              <span class="nav-link-title"><?= Lang::t('branches') ?></span>
            </a>
          </li>

          <li class="nav-item <?= strpos($_SERVER['PHP_SELF'], '/settings') !== false ? 'active' : '' ?>">
            <a class="nav-link" href="<?= BASE_URL ?>/modules/settings/index.php">
              <span class="nav-link-icon d-md-none d-lg-inline-block">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10.325 4.317c.426 -1.756 2.924 -1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543 -.94 3.31 .826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756 .426 1.756 2.924 0 3.35a1.724 1.724 0 0 0 -1.066 2.573c.94 1.543 -.826 3.31 -2.37 2.37a1.724 1.724 0 0 0 -2.572 1.065c-.426 1.756 -2.924 1.756 -3.35 0a1.724 1.724 0 0 0 -2.573 -1.066c-1.543 .94 -3.31 -.826 -2.37 -2.37a1.724 1.724 0 0 0 -1.065 -2.572c-1.756 -.426 -1.756 -2.924 0 -3.35a1.724 1.724 0 0 0 1.066 -2.573c-.94 -1.543 .826 -3.31 2.37 -2.37c1 .608 2.296 .07 2.572 -1.065z"/><circle cx="12" cy="12" r="3"/></svg>
              </span>
              <span class="nav-link-title"><?= Lang::t('settings') ?></span>
            </a>
          </li>
          <?php endif; ?>

        </ul>

        <!-- Bottom user strip -->
        <div class="mt-auto">
          <ul class="navbar-nav mb-1">
            <li class="nav-item">
              <a class="nav-link" href="<?= BASE_URL ?>/modules/auth/lang_switch.php">
                <span class="nav-link-icon d-md-none d-lg-inline-block" style="font-size:1rem;">🌐</span>
                <span class="nav-link-title"><?= strtoupper(Lang::current()) ?> Language</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="<?= BASE_URL ?>/modules/auth/logout.php">
                <span class="nav-link-icon d-md-none d-lg-inline-block">
                  <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M14 8v-2a2 2 0 0 0 -2 -2h-7a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h7a2 2 0 0 0 2 -2v-2"/><path d="M7 12h14l-3 -3m0 6l3 -3"/></svg>
                </span>
                <span class="nav-link-title"><?= Lang::t('logout') ?></span>
              </a>
            </li>
          </ul>
          <div class="vp-sidebar-user">
            <div class="vp-avatar">
              <?= strtoupper(substr($cu['name'], 0, 1)) ?>
            </div>
            <div>
              <div class="vp-uname"><?= Helper::sanitize($cu['name']) ?></div>
              <div class="vp-urole"><?= ucfirst(str_replace('_', ' ', $cu['role'])) ?></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </aside>

  <!-- Main content -->
  <div class="page-wrapper">
    <!-- Topbar -->
    <div class="navbar-expand-md">
      <div class="collapse navbar-collapse" id="navbar-menu">
        <div class="navbar navbar-light">
          <div class="container-xl">
            <div class="me-3 d-none d-md-flex">
              <?php if (isset($breadcrumbs) && $breadcrumbs): ?>
              <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/index.php"><?= Lang::t('dashboard') ?></a></li>
                <?php foreach ($breadcrumbs as $bc): ?>
                  <?php if (isset($bc['url'])): ?>
                  <li class="breadcrumb-item"><a href="<?= $bc['url'] ?>"><?= Helper::sanitize($bc['label']) ?></a></li>
                  <?php else: ?>
                  <li class="breadcrumb-item active"><?= Helper::sanitize($bc['label']) ?></li>
                  <?php endif; ?>
                <?php endforeach; ?>
              </ol>
              <?php endif; ?>
            </div>
            <div class="ms-auto d-flex align-items-center gap-2">
              <span class="badge" style="background:var(--vp-gold-dim);color:var(--vp-navy);font-size:.72rem;padding:.3rem .7rem;border-radius:20px;font-weight:700;">
                🏢 <?= Helper::sanitize($cu['branch_name'] ?? 'Main Branch') ?>
              </span>
              <div class="nav-item dropdown">
                <a href="#" class="nav-link d-flex lh-1 text-reset p-0 align-items-center gap-2" data-bs-toggle="dropdown">
                  <span class="vp-avatar vp-avatar-sm" style="width:32px;height:32px;font-size:.75rem;">
                    <?= strtoupper(substr($cu['name'], 0, 1)) ?>
                  </span>
                  <div class="d-none d-xl-block">
                    <div style="font-size:.82rem;font-weight:600;color:#1f2937;"><?= Helper::sanitize($cu['name']) ?></div>
                    <div style="font-size:.68rem;color:#9ca3af;"><?= ucfirst(str_replace('_',' ',$cu['role'])) ?></div>
                  </div>
                </a>
                <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                  <a href="<?= BASE_URL ?>/modules/auth/logout.php" class="dropdown-item text-danger"><?= Lang::t('logout') ?></a>
                </div>
              </div>
            </div>
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
