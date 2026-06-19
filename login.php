<?php
require_once __DIR__ . '/core/bootstrap.php';

// Already logged in
if (Auth::isLoggedIn()) {
    Helper::redirect(BASE_URL . '/index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $auth = new Auth();
    if ($auth->login($email, $password)) {
        Helper::redirect(BASE_URL . '/index.php');
    } else {
        $error = Lang::t('login_failed');
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
  <title>Login — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta21/dist/css/tabler.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
</head>
<body class="antialiased d-flex flex-column">
<div class="page page-center">
  <div class="container container-tight py-4">
    <div class="text-center mb-4">
      <a href="." class="navbar-brand navbar-brand-autodark">
        <h1 class="h2 text-primary fw-bold">&#127968; VenuePro Lanka</h1>
        <p class="text-muted small">Wedding Hall & Event Venue Management</p>
      </a>
    </div>
    <div class="card card-md">
      <div class="card-body">
        <h2 class="h2 text-center mb-4">Sign in to your account</h2>
        <?php if ($error): ?>
        <div class="alert alert-danger"><?= Helper::sanitize($error) ?></div>
        <?php endif; ?>
        <form action="" method="POST" autocomplete="off">
          <div class="mb-3">
            <label class="form-label">Email address</label>
            <input type="email" name="email" class="form-control" placeholder="your@email.com" required
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
          </div>
          <div class="mb-2">
            <label class="form-label">Password</label>
            <div class="input-group input-group-flat">
              <input type="password" name="password" class="form-control" placeholder="Password" required autocomplete="off">
            </div>
          </div>
          <div class="mb-3">
            <p class="text-muted small">Default: admin@venuepro.lk / password</p>
          </div>
          <div class="form-footer">
            <button type="submit" class="btn btn-primary w-100">Sign in</button>
          </div>
        </form>
      </div>
    </div>
    <div class="text-center text-secondary mt-3">
      &copy; <?= date('Y') ?> <?= APP_NAME ?> · AxisXNOR (PVT) Ltd
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta21/dist/js/tabler.min.js"></script>
</body>
</html>
