<?php
require_once __DIR__ . '/core/bootstrap.php';
Auth::requireSuperAdmin();

$db = Database::getInstance()->getConnection();
$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: /sa.php');
    exit;
}

// Handle POST update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city    = trim($_POST['city'] ?? '');
    $plan    = trim($_POST['plan'] ?? 'starter');
    $status  = trim($_POST['status'] ?? 'active');

    if (!$name || !$email) {
        $_SESSION['sa_error'] = 'Name and Email are required.';
        header("Location: /sa-edit.php?id=$id");
        exit;
    }

    $stmt = $db->prepare("
        UPDATE sa_businesses
        SET name=?, email=?, phone=?, address=?, city=?, plan=?, status=?
        WHERE id=?
    ");
    $stmt->execute([$name, $email, $phone, $address, $city, $plan, $status, $id]);
    $_SESSION['sa_success'] = "Business updated successfully.";
    header('Location: /sa.php');
    exit;
}

// Fetch business
$stmt = $db->prepare("SELECT * FROM sa_businesses WHERE id = ?");
$stmt->execute([$id]);
$biz = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$biz) {
    header('Location: /sa.php');
    exit;
}

$pageTitle = 'Edit Business';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Business — VenuePro Super Admin</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<style>
  :root { --bg: #0f1117; --card: #1a1d27; --border: #2a2d3e; --accent: #6c63ff; --text: #e2e8f0; --muted: #8892a4; }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { background: var(--bg); color: var(--text); font-family: 'Inter', sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
  .wrap { width: 100%; max-width: 640px; padding: 2rem 1rem; }
  .card { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 2rem; }
  h2 { font-size: 1.4rem; margin-bottom: 1.5rem; }
  .form-group { margin-bottom: 1.2rem; }
  label { display: block; font-size: .8rem; color: var(--muted); margin-bottom: .4rem; text-transform: uppercase; letter-spacing: .05em; }
  input, select { width: 100%; background: #0f1117; border: 1px solid var(--border); color: var(--text); padding: .65rem 1rem; border-radius: 8px; font-size: .95rem; outline: none; transition: border .2s; }
  input:focus, select:focus { border-color: var(--accent); }
  .row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
  .actions { display: flex; gap: 1rem; margin-top: 1.5rem; }
  .btn { padding: .7rem 1.6rem; border-radius: 8px; border: none; font-size: .95rem; cursor: pointer; font-weight: 600; }
  .btn-primary { background: var(--accent); color: #fff; }
  .btn-primary:hover { background: #5a52e0; }
  .btn-cancel { background: var(--border); color: var(--text); text-decoration: none; display: flex; align-items: center; }
  .btn-cancel:hover { background: #3a3d50; }
  .alert { padding: .8rem 1.2rem; border-radius: 8px; margin-bottom: 1.2rem; font-size: .9rem; }
  .alert-error { background: rgba(239,68,68,.15); border: 1px solid rgba(239,68,68,.3); color: #f87171; }
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h2>Edit Business</h2>

    <?php if (!empty($_SESSION['sa_error'])): ?>
      <div class="alert alert-error"><?= htmlspecialchars($_SESSION['sa_error']) ?></div>
      <?php unset($_SESSION['sa_error']); ?>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label>Business Name *</label>
        <input type="text" name="name" value="<?= htmlspecialchars($biz['name']) ?>" required>
      </div>
      <div class="row">
        <div class="form-group">
          <label>Email *</label>
          <input type="email" name="email" value="<?= htmlspecialchars($biz['email']) ?>" required>
        </div>
        <div class="form-group">
          <label>Phone</label>
          <input type="text" name="phone" value="<?= htmlspecialchars($biz['phone'] ?? '') ?>">
        </div>
      </div>
      <div class="form-group">
        <label>Address</label>
        <input type="text" name="address" value="<?= htmlspecialchars($biz['address'] ?? '') ?>">
      </div>
      <div class="row">
        <div class="form-group">
          <label>City</label>
          <input type="text" name="city" value="<?= htmlspecialchars($biz['city'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Plan</label>
          <select name="plan">
            <?php foreach (['starter','professional','enterprise'] as $p): ?>
              <option value="<?= $p ?>" <?= $biz['plan'] === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label>Status</label>
        <select name="status">
          <option value="active"    <?= $biz['status'] === 'active'    ? 'selected' : '' ?>>Active</option>
          <option value="suspended" <?= $biz['status'] === 'suspended' ? 'selected' : '' ?>>Suspended</option>
          <option value="inactive"  <?= $biz['status'] === 'inactive'  ? 'selected' : '' ?>>Inactive</option>
        </select>
      </div>
      <div class="actions">
        <button type="submit" class="btn btn-primary">Save Changes</button>
        <a href="/sa.php" class="btn btn-cancel">Cancel</a>
      </div>
    </form>
  </div>
</div>
</body>
</html>
