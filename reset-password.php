<?php
require_once __DIR__ . '/core/bootstrap.php';

// Already logged in → dashboard
if (Auth::isLoggedIn()) {
    Helper::redirect(BASE_URL . '/index.php');
}

$token   = trim($_GET['token'] ?? '');
$success = '';
$error   = '';
$validToken = false;
$resetUser  = null;

$db = Database::getInstance();

// Validate token
if ($token) {
    $row = $db->fetchOne(
        "SELECT pr.*, u.id as uid, u.name, u.email
         FROM password_resets pr
         JOIN users u ON pr.user_id = u.id
         WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > NOW()
         LIMIT 1",
        [$token]
    );
    if ($row) {
        $validToken = true;
        $resetUser  = $row;
    } else {
        $error = 'This reset link is invalid or has expired. Please request a new one.';
    }
}

if (!$token) {
    Helper::redirect(BASE_URL . '/forgot-password.php');
}

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $db->execute("UPDATE users SET password = ? WHERE id = ?", [$hashed, $resetUser['uid']]);
        $db->execute("UPDATE password_resets SET used = 1 WHERE token = ?", [$token]);
        $success = 'Your password has been reset successfully. You can now log in.';
        $validToken = false; // hide form
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
  <title>Reset Password — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta21/dist/css/tabler.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    * { font-family: 'Inter', sans-serif; }
    body {
      background: linear-gradient(135deg, #08111f 0%, #0c1830 50%, #0f1f3d 100%);
      min-height: 100vh;
      display: flex; align-items: center; justify-content: center;
    }
    .login-wrap { width: 100%; max-width: 420px; padding: 1.5rem; }
    .login-brand { text-align: center; margin-bottom: 2rem; }
    .logo-icon {
      width: 64px; height: 64px;
      background: linear-gradient(135deg, #c9a84c, #e8c96a);
      border-radius: 16px; display: inline-flex;
      align-items: center; justify-content: center;
      font-size: 28px; margin-bottom: .75rem;
      box-shadow: 0 8px 24px rgba(201,168,76,.35);
    }
    .brand-name { color: #fff; font-size: 1.6rem; font-weight: 800; letter-spacing: -.04em; display: block; }
    .brand-sub  { color: rgba(255,255,255,.35); font-size: .8rem; margin-top: .2rem; }
    .login-card {
      background: rgba(255,255,255,.04);
      border: 1px solid rgba(255,255,255,.1);
      border-radius: 20px; padding: 2rem;
      backdrop-filter: blur(12px);
    }
    .card-title { color: #fff; font-size: 1.15rem; font-weight: 800; margin: 0 0 .4rem; text-align: center; }
    .card-sub   { color: rgba(255,255,255,.4); font-size: .82rem; text-align: center; margin-bottom: 1.5rem; line-height: 1.5; }
    .form-label { color: rgba(255,255,255,.7); font-size: .8rem; font-weight: 600; letter-spacing: .03em; text-transform: uppercase; margin-bottom: .4rem; }
    .form-control {
      background: rgba(255,255,255,.07); border: 1.5px solid rgba(255,255,255,.12);
      color: #fff; border-radius: 10px; padding: .7rem 1rem;
      font-size: .92rem; transition: all .2s; width: 100%; box-sizing: border-box;
    }
    .form-control:focus {
      background: rgba(255,255,255,.1); border-color: #c9a84c;
      box-shadow: 0 0 0 3px rgba(201,168,76,.18); color: #fff; outline: none;
    }
    .form-control::placeholder { color: rgba(255,255,255,.3); }
    .input-wrap { position: relative; }
    .toggle-pw {
      position: absolute; right: .85rem; top: 50%; transform: translateY(-50%);
      background: none; border: none; cursor: pointer; color: rgba(255,255,255,.4);
      padding: 0; display: flex; align-items: center;
    }
    .toggle-pw:hover { color: #c9a84c; }
    .input-wrap .form-control { padding-right: 2.8rem; }
    .pw-strength { height: 3px; border-radius: 2px; margin-top: .4rem; background: #1e2d4a; transition: all .3s; }
    .pw-strength-bar { height: 100%; border-radius: 2px; transition: all .3s; width: 0; }
    .pw-hint { font-size: .72rem; color: rgba(255,255,255,.35); margin-top: .3rem; }
    .btn-reset {
      width: 100%; background: linear-gradient(135deg, #c9a84c, #e8c96a);
      color: #0c1a35; font-weight: 800; font-size: .95rem;
      border: none; border-radius: 10px; padding: .8rem;
      cursor: pointer; transition: all .2s; margin-top: .5rem;
    }
    .btn-reset:hover { transform: translateY(-1px); box-shadow: 0 8px 24px rgba(201,168,76,.4); }
    .back-link { text-align: center; margin-top: 1.2rem; }
    .back-link a {
      color: rgba(255,255,255,.45); font-size: .82rem; text-decoration: none;
      font-weight: 600; transition: color .15s;
      display: inline-flex; align-items: center; gap: .35rem;
    }
    .back-link a:hover { color: #c9a84c; }
    .alert-success {
      background: rgba(5,150,105,.15); border: 1px solid rgba(5,150,105,.3);
      color: #6ee7b7; border-radius: 10px; padding: .9rem 1rem;
      font-size: .85rem; font-weight: 600; margin-bottom: 1rem; text-align: center;
    }
    .alert-error {
      background: rgba(220,38,38,.15); border: 1px solid rgba(220,38,38,.3);
      color: #fca5a5; border-radius: 10px; padding: .9rem 1rem;
      font-size: .85rem; font-weight: 600; margin-bottom: 1rem; text-align: center;
    }
    .user-badge {
      display: flex; align-items: center; gap: .6rem;
      background: rgba(201,168,76,.08); border: 1px solid rgba(201,168,76,.2);
      border-radius: 10px; padding: .6rem .9rem; margin-bottom: 1.4rem;
    }
    .user-badge-avatar {
      width: 32px; height: 32px; border-radius: 8px;
      background: linear-gradient(135deg,#c9a84c,#e8c96a);
      display: flex; align-items: center; justify-content: center;
      font-size: 14px; font-weight: 800; color: #0c1a35; flex-shrink: 0;
    }
    .user-badge-name { color: #e8c96a; font-size: .83rem; font-weight: 700; }
    .user-badge-email { color: rgba(255,255,255,.4); font-size: .74rem; }
    .footer-text { text-align: center; color: rgba(255,255,255,.2); font-size: .78rem; margin-top: 1.5rem; }
  </style>
</head>
<body>
  <div class="login-wrap">
    <div class="login-brand">
      <div class="logo-icon">🏛️</div>
      <span class="brand-name"><?= APP_NAME ?></span>
      <div class="brand-sub">Venue Management System</div>
    </div>

    <div class="login-card">
      <div class="card-title">Set New Password</div>

      <?php if ($success): ?>
        <div class="alert-success">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:middle;margin-right:6px;"><path d="M20 6L9 17l-5-5"/></svg>
          <?= htmlspecialchars($success) ?>
        </div>
        <div class="back-link" style="margin-top:.5rem;">
          <a href="<?= BASE_URL ?>/venuepro">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
            Go to Login
          </a>
        </div>

      <?php elseif (!$validToken): ?>
        <div class="alert-error">
          <?= htmlspecialchars($error ?: 'Invalid or expired reset link.') ?>
        </div>
        <div class="back-link">
          <a href="<?= BASE_URL ?>/forgot-password.php">
            Request a new reset link →
          </a>
        </div>

      <?php else: ?>
        <?php if ($error): ?>
          <div class="alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card-sub">Choose a strong new password for your account.</div>

        <!-- User badge -->
        <div class="user-badge">
          <div class="user-badge-avatar"><?= strtoupper(substr($resetUser['name'], 0, 1)) ?></div>
          <div>
            <div class="user-badge-name"><?= htmlspecialchars($resetUser['name']) ?></div>
            <div class="user-badge-email"><?= htmlspecialchars($resetUser['email']) ?></div>
          </div>
        </div>

        <form action="?token=<?= urlencode($token) ?>" method="POST" autocomplete="off">
          <div class="mb-3">
            <label class="form-label">New Password</label>
            <div class="input-wrap">
              <input type="password" name="password" id="pw" class="form-control"
                     placeholder="Min. 8 characters" required minlength="8"
                     oninput="checkStrength(this.value)">
              <button type="button" class="toggle-pw" onclick="togglePw('pw',this)">
                <svg id="eye-pw" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
            </div>
            <div class="pw-strength"><div class="pw-strength-bar" id="pw-bar"></div></div>
            <div class="pw-hint" id="pw-hint">Enter a password</div>
          </div>
          <div class="mb-3">
            <label class="form-label">Confirm Password</label>
            <div class="input-wrap">
              <input type="password" name="confirm_password" id="pw2" class="form-control"
                     placeholder="Repeat password" required>
              <button type="button" class="toggle-pw" onclick="togglePw('pw2',this)">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
            </div>
          </div>
          <button type="submit" class="btn-reset">Update Password</button>
        </form>

        <div class="back-link">
          <a href="<?= BASE_URL ?>/venuepro">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
            Back to Login
          </a>
        </div>
      <?php endif; ?>
    </div>

    <div class="footer-text">&copy; <?= date('Y') ?> <?= APP_NAME ?> · AxisXNOR (PVT) Ltd</div>
  </div>

  <script>
  function togglePw(id, btn) {
    var inp = document.getElementById(id);
    inp.type = inp.type === 'password' ? 'text' : 'password';
  }
  function checkStrength(pw) {
    var bar  = document.getElementById('pw-bar');
    var hint = document.getElementById('pw-hint');
    var score = 0;
    if (pw.length >= 8)  score++;
    if (pw.length >= 12) score++;
    if (/[A-Z]/.test(pw)) score++;
    if (/[0-9]/.test(pw)) score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;
    var map = [
      {w:'0%',   c:'#dc2626', t:'Too short'},
      {w:'25%',  c:'#dc2626', t:'Weak'},
      {w:'50%',  c:'#d97706', t:'Fair'},
      {w:'75%',  c:'#2563eb', t:'Good'},
      {w:'90%',  c:'#059669', t:'Strong'},
      {w:'100%', c:'#059669', t:'Very strong'},
    ];
    var m = map[Math.min(score, 5)];
    bar.style.width = m.w; bar.style.background = m.c;
    hint.textContent = m.t; hint.style.color = m.c;
  }
  </script>
</body>
</html>
