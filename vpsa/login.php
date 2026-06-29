<?php
require_once __DIR__ . '/../core/bootstrap.php';

// If already logged in as super admin, go to panel
if (isset($_SESSION['sa_logged_in']) && $_SESSION['sa_logged_in'] === true) {
    header('Location: ' . BASE_URL . '/vpsa/');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id  = trim($_POST['user_id'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $db = Database::getInstance();
    $user = $db->fetchOne(
        "SELECT u.*, r.slug as role_slug FROM users u
         LEFT JOIN roles r ON u.role_id = r.id
         WHERE u.user_id = ? AND r.slug = 'super_admin' AND u.is_active = 1",
        [$user_id]
    );

    if ($user && password_verify($password, $user['password'])) {
        // Set SA-specific session keys
        $_SESSION['sa_logged_in']  = true;
        $_SESSION['sa_user_id']    = $user['id'];
        $_SESSION['sa_user_name']  = $user['name'];
        $_SESSION['sa_user_uid']   = $user['user_id'];
        $_SESSION['user_id']       = $user['id'];
        $_SESSION['user_name']     = $user['name'];
        $_SESSION['user_email']    = $user['email'];
        $_SESSION['user_role']     = $user['role_slug'];
        $_SESSION['user_role_id']  = $user['role_id'];
        $_SESSION['branch_id']     = $user['branch_id'];
        $_SESSION['language']      = $user['language'] ?? 'en';

        $db->execute("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);
        header('Location: ' . BASE_URL . '/vpsa/');
        exit;
    } else {
        $error = 'Invalid User ID or password.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Super Admin — VenuePro</title>
  <link rel="shortcut icon" type="image/png" href="<?= BASE_URL ?>/assets/images/favicon.png"/>
  <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/images/favicon.png"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #08111f 0%, #0c1830 50%, #0f1f3d 100%);
      min-height: 100vh;
      display: flex; align-items: center; justify-content: center;
    }
    .wrap { width: 100%; max-width: 400px; padding: 1.5rem; }
    .brand { text-align: center; margin-bottom: 2rem; }
    .brand .icon { display: block; margin-bottom: 1rem; }
    .brand .icon img { max-height: 80px; max-width: 200px; width: 100%; object-fit: contain; }
    .brand h1 { color: #fff; font-size: 1.5rem; font-weight: 800; }
    .brand p { color: rgba(255,255,255,.45); font-size: .82rem; margin-top: .3rem; }
    .card {
      background: rgba(255,255,255,.05);
      backdrop-filter: blur(20px);
      border: 1px solid rgba(255,255,255,.1);
      border-radius: 20px;
      padding: 2rem;
      box-shadow: 0 24px 64px rgba(0,0,0,.5);
    }
    .card h2 {
      color: #fff; font-size: 1.1rem; font-weight: 700;
      text-align: center; margin-bottom: 1.5rem;
      display: flex; align-items: center; justify-content: center; gap: .5rem;
    }
    .badge-sa {
      background: rgba(201,168,76,.2); color: #e8c96a;
      border: 1px solid rgba(201,168,76,.3);
      border-radius: 6px; font-size: .7rem; padding: .15rem .5rem;
      font-weight: 700; letter-spacing: .05em;
    }
    label { display: block; color: rgba(255,255,255,.6); font-size: .75rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; margin-bottom: .4rem; }
    .mb3 { margin-bottom: 1.1rem; }
    input {
      width: 100%;
      background: rgba(255,255,255,.07);
      border: 1px solid rgba(255,255,255,.12);
      border-radius: 10px;
      color: #fff;
      padding: .65rem 1rem;
      font-size: .9rem;
      font-family: inherit;
      transition: all .2s;
    }
    input:focus {
      outline: none;
      border-color: #c9a84c;
      background: rgba(255,255,255,.1);
      box-shadow: 0 0 0 3px rgba(201,168,76,.2);
    }
    input::placeholder { color: rgba(255,255,255,.3); }
    .btn {
      width: 100%;
      background: linear-gradient(135deg, #c9a84c, #e8c96a);
      border: none; border-radius: 10px;
      color: #08111f; font-weight: 700; font-size: .95rem;
      padding: .75rem; cursor: pointer;
      box-shadow: 0 4px 16px rgba(201,168,76,.3);
      transition: all .2s; margin-top: .5rem;
      font-family: inherit;
    }
    .btn:hover { transform: translateY(-1px); box-shadow: 0 8px 24px rgba(201,168,76,.45); }
    .error {
      background: rgba(239,68,68,.15);
      border: 1px solid rgba(239,68,68,.3);
      color: #fca5a5; border-radius: 10px;
      padding: .75rem 1rem; font-size: .85rem;
      margin-bottom: 1rem;
    }
    .footer { text-align: center; color: rgba(255,255,255,.2); font-size: .75rem; margin-top: 1.5rem; }
  </style>
</head>
<body>
<div class="wrap">
  <div class="brand">
    <div class="icon"><img src="<?= BASE_URL ?>/assets/images/sidebar-logo.png" alt="VenuePro"></div>
    <p>Super Admin Access</p>
  </div>

  <div class="card">
    <h2>Sign In <span class="badge-sa">SUPER ADMIN</span></h2>

    <?php if ($error): ?>
    <div class="error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
      <div class="mb3">
        <label>User ID</label>
        <input type="text" name="user_id" placeholder="e.g. SAROOT" required
               value="<?= htmlspecialchars($_POST['user_id'] ?? '') ?>">
      </div>
      <div class="mb3">
        <label>Password</label>
        <input type="password" name="password" placeholder="Password" required autocomplete="new-password">
      </div>
      <button type="submit" class="btn">Access Panel →</button>
    </form>
  </div>

  <div class="footer">&copy; <?= date('Y') ?> VenuePro · AxisXNOR (PVT) Ltd</div>
</div>
</body>
</html>
