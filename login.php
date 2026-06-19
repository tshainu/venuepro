<?php
require_once __DIR__ . '/core/bootstrap.php';

if (Auth::isLoggedIn()) {
    Helper::redirect(BASE_URL . '/index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id  = trim($_POST['user_id'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $auth = new Auth();
    if ($auth->loginWithUserId($user_id, $email, $password)) {
        Helper::redirect(BASE_URL . '/index.php');
    } else {
        $error = 'Invalid User ID, email or password.';
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
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    * { font-family: 'Inter', sans-serif; }
    body {
      background: linear-gradient(135deg, #08111f 0%, #0c1830 50%, #0f1f3d 100%);
      min-height: 100vh;
      display: flex; align-items: center; justify-content: center;
    }
    .login-wrap {
      width: 100%; max-width: 420px; padding: 1.5rem;
    }
    .login-brand {
      text-align: center; margin-bottom: 2rem;
    }
    .login-brand .logo-icon {
      width: 64px; height: 64px;
      background: linear-gradient(135deg, #c9a84c, #e8c96a);
      border-radius: 18px;
      display: inline-flex; align-items: center; justify-content: center;
      font-size: 28px; margin-bottom: 1rem;
      box-shadow: 0 8px 32px rgba(201,168,76,.35);
    }
    .login-brand h1 { color: #fff; font-size: 1.6rem; font-weight: 800; margin: 0; }
    .login-brand p { color: rgba(255,255,255,.5); font-size: .85rem; margin: .3rem 0 0; }
    .login-card {
      background: rgba(255,255,255,.04);
      backdrop-filter: blur(20px);
      border: 1px solid rgba(255,255,255,.1);
      border-radius: 20px;
      padding: 2rem;
      box-shadow: 0 24px 64px rgba(0,0,0,.4);
    }
    .login-card h2 { color: #fff; font-size: 1.25rem; font-weight: 700; margin-bottom: 1.5rem; text-align: center; }
    .form-label { color: rgba(255,255,255,.7); font-size: .8rem; font-weight: 600; letter-spacing: .03em; text-transform: uppercase; margin-bottom: .4rem; }
    .form-control {
      background: rgba(255,255,255,.07);
      border: 1px solid rgba(255,255,255,.12);
      border-radius: 10px;
      color: #fff;
      padding: .65rem 1rem;
      font-size: .9rem;
      transition: all .2s;
    }
    .form-control:focus {
      background: rgba(255,255,255,.1);
      border-color: #c9a84c;
      box-shadow: 0 0 0 3px rgba(201,168,76,.2);
      color: #fff;
      outline: none;
    }
    .form-control::placeholder { color: rgba(255,255,255,.3); }
    .input-icon-wrap { position: relative; }
    .input-icon-wrap .input-icon {
      position: absolute; left: .85rem; top: 50%; transform: translateY(-50%);
      color: rgba(255,255,255,.35); pointer-events: none;
    }
    .input-icon-wrap .form-control { padding-left: 2.5rem; }
    .btn-login {
      background: linear-gradient(135deg, #c9a84c, #e8c96a);
      border: none; border-radius: 10px; color: #08111f;
      font-weight: 700; font-size: .95rem;
      padding: .75rem; width: 100%;
      cursor: pointer; transition: all .2s;
      box-shadow: 0 4px 16px rgba(201,168,76,.3);
      margin-top: .5rem;
    }
    .btn-login:hover { transform: translateY(-1px); box-shadow: 0 8px 24px rgba(201,168,76,.4); }
    .alert-error {
      background: rgba(239,68,68,.15); border: 1px solid rgba(239,68,68,.3);
      color: #fca5a5; border-radius: 10px; padding: .75rem 1rem;
      font-size: .85rem; margin-bottom: 1rem;
    }
    .hint-badge {
      background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.08);
      border-radius: 8px; padding: .6rem 1rem; margin-top: 1rem;
      color: rgba(255,255,255,.4); font-size: .78rem; text-align: center;
    }
    .hint-badge code { color: #c9a84c; background: none; font-size: .78rem; }
    .footer-text { text-align: center; color: rgba(255,255,255,.25); font-size: .78rem; margin-top: 1.5rem; }
    .divider { height: 1px; background: rgba(255,255,255,.08); margin: 1.2rem 0; }
    .sa-link { text-align: center; margin-top: 1rem; }
    .sa-link a {
      color: #c9a84c; font-size: .82rem; text-decoration: none; font-weight: 600;
      letter-spacing: .02em;
    }
    .sa-link a:hover { color: #e8c96a; }
  </style>
</head>
<body>
<div class="login-wrap">
  <div class="login-brand">
    <div class="logo-icon">🏛️</div>
    <h1>VenuePro Lanka</h1>
    <p>Wedding Hall & Event Venue Management</p>
  </div>

  <div class="login-card">
    <h2>Sign In</h2>

    <?php if ($error): ?>
    <div class="alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form action="" method="POST" autocomplete="off">
      <div class="mb-3">
        <label class="form-label">User ID</label>
        <div class="input-icon-wrap">
          <span class="input-icon">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          </span>
          <input type="text" name="user_id" class="form-control" placeholder="e.g. SA001" required
                 value="<?= htmlspecialchars($_POST['user_id'] ?? '') ?>">
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label">Email Address</label>
        <div class="input-icon-wrap">
          <span class="input-icon">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          </span>
          <input type="email" name="email" class="form-control" placeholder="your@email.com" required
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label">Password</label>
        <div class="input-icon-wrap">
          <span class="input-icon">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          </span>
          <input type="password" name="password" class="form-control" placeholder="Password" required autocomplete="off">
        </div>
      </div>

      <button type="submit" class="btn-login">Sign In →</button>
    </form>

    <div class="hint-badge">
      Default: <code>SA001</code> · <code>admin@venuepro.lk</code> · <code>password</code>
    </div>

    <div class="divider"></div>
    <div class="sa-link">
      <a href="<?= BASE_URL ?>/superadmin/">🛡️ Super Admin Panel</a>
    </div>
  </div>

  <div class="footer-text">&copy; <?= date('Y') ?> <?= APP_NAME ?> · AxisXNOR (PVT) Ltd</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta21/dist/js/tabler.min.js"></script>
</body>
</html>
