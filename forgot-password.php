<?php
require_once __DIR__ . '/core/bootstrap.php';

if (Auth::isLoggedIn()) {
    Helper::redirect(BASE_URL . '/index.php');
}

$success     = '';
$error       = '';
$sentEmail   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email   = trim($_POST['email'] ?? '');
    $user_id = strtoupper(trim($_POST['user_id'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (empty($user_id)) {
        $error = 'Please enter your User ID.';
    } else {
        $db   = Database::getInstance();
        $user = $db->fetchOne(
            "SELECT id, name, email, user_id FROM users WHERE email = ? AND user_id = ? AND is_active = 1 LIMIT 1",
            [$email, $user_id]
        );

        if (!$user) {
            $error = 'No account found matching that User ID and email address.';
        } else {
            // Clean old tokens
            $db->execute("DELETE FROM password_resets WHERE user_id = ?", [$user['id']]);

            $token     = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $db->execute(
                "INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)",
                [$user['id'], $token, $expiresAt]
            );

            $resetLink = BASE_URL . '/reset-password.php?token=' . $token;

            require_once ROOT_PATH . '/core/Mailer.php';
            Mailer::sendPasswordReset($user['email'], $user['name'], $resetLink);

            $sentEmail = htmlspecialchars($email);
            $success   = 'sent';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
  <title>Forgot Password — <?= APP_NAME ?></title>
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
      font-size: .88rem; font-weight: 600; margin-bottom: .75rem; text-align: center; line-height: 1.5;
    }
    .alert-success strong { color: #fff; }
    .alert-spam {
      background: rgba(220,38,38,.12); border: 1px solid rgba(220,38,38,.3);
      color: #fca5a5; border-radius: 10px; padding: .7rem 1rem;
      font-size: .8rem; font-weight: 600; margin-bottom: 1rem; text-align: center;
      display: flex; align-items: center; justify-content: center; gap: .4rem;
    }
    .alert-error {
      background: rgba(220,38,38,.15); border: 1px solid rgba(220,38,38,.3);
      color: #fca5a5; border-radius: 10px; padding: .9rem 1rem;
      font-size: .85rem; font-weight: 600; margin-bottom: 1rem; text-align: center;
    }
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
      <div class="card-title">Forgot Password</div>

      <?php if ($success === 'sent'): ?>
        <div class="alert-success">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:middle;margin-right:4px;"><path d="M20 6L9 17l-5-5"/></svg>
          Reset link sent to <strong><?= $sentEmail ?></strong>.<br>Check your inbox.
        </div>
        <div class="alert-spam">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          Check your <strong>Spam</strong> folder if you don't see it in Inbox.
        </div>
        <div class="back-link">
          <a href="<?= BASE_URL ?>/venuepro">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
            Back to Login
          </a>
        </div>

      <?php else: ?>
        <div class="card-sub">Enter your User ID and email to receive a reset link.</div>

        <?php if ($error): ?>
          <div class="alert-error">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:middle;margin-right:4px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <?= htmlspecialchars($error) ?>
          </div>
        <?php endif; ?>

        <form action="" method="POST" autocomplete="off">
          <div class="mb-3">
            <label class="form-label">User ID</label>
            <input type="text" name="user_id" class="form-control"
                   placeholder="e.g. A001" required
                   value="<?= htmlspecialchars(strtoupper($_POST['user_id'] ?? '')) ?>"
                   style="text-transform:uppercase;">
          </div>
          <div class="mb-3">
            <label class="form-label">Email Address</label>
            <input type="email" name="email" class="form-control"
                   placeholder="your@email.com" required
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
          </div>
          <button type="submit" class="btn-reset">Send Reset Link</button>
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
</body>
</html>
