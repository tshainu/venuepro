<?php
require_once __DIR__ . '/core/bootstrap.php';

if (Auth::isLoggedIn() && !empty($_SESSION['user_id'])) {
    // Verify session is genuine (not a ghost after logout)
    $db = Database::getInstance();
    $stillExists = $db->fetchOne("SELECT id FROM users WHERE id = ? AND is_active = 1", [$_SESSION['user_id']]);
    if ($stillExists) {
        if (Auth::hasRole(['super_admin'])) {
            Helper::redirect(BASE_URL . '/vpsa/');
        } else {
            Helper::redirect(BASE_URL . '/index.php');
        }
    } else {
        // Ghost session — wipe and show login
        $_SESSION = [];
        session_destroy();
    }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id  = trim($_POST['user_id'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $auth = new Auth();
    if ($auth->loginWithUserId($user_id, $username, $password)) {
        if (Auth::hasRole(['super_admin'])) {
            Helper::redirect(BASE_URL . '/vpsa/');
        } else {
            Helper::redirect(BASE_URL . '/index.php');
        }
    } else {
        $error = 'Invalid User ID, Username or Password.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
  <title>Login — <?= APP_NAME ?></title>
  <link rel="shortcut icon" type="image/png" href="<?= BASE_URL ?>/assets/images/favicon.png"/>
  <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/images/favicon.png"/>
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
      margin-bottom: 1rem;
      display: block;
    }
    .login-brand .logo-icon img {
      max-height: 80px; max-width: 200px; width: 100%; object-fit: contain;
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
  </style>
</head>
<body>
<div class="login-wrap">
  <div class="login-brand">
    <div class="logo-icon"><img src="<?= BASE_URL ?>/assets/images/sidebar-logo.png" alt="VenuePro"></div>
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
        <label class="form-label">Username</label>
        <div class="input-icon-wrap">
          <span class="input-icon">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          </span>
          <input type="text" name="username" class="form-control" placeholder="e.g. admin" required
                 value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
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
    <div style="text-align:center;margin-top:1rem;">
      <a href="<?= BASE_URL ?>/forgot-password.php" style="color:rgba(255,255,255,.4);font-size:.82rem;font-weight:600;text-decoration:none;transition:color .15s;" onmouseover="this.style.color='#c9a84c'" onmouseout="this.style.color='rgba(255,255,255,.4)'">Forgot password?</a>
    </div>

  </div>

  <div class="footer-text">&copy; <?= date('Y') ?> <?= APP_NAME ?> · AxisXNOR (PVT) Ltd</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta21/dist/js/tabler.min.js"></script>
</body>
</html>
