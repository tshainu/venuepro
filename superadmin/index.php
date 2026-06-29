<?php
require_once __DIR__ . '/../core/bootstrap.php';
if (empty($_SESSION['sa_logged_in'])) {
    header('Location: ' . BASE_URL . '/superadmin/login.php');
    exit;
}
$db = Database::getInstance();

// Stats
$total      = $db->fetchOne("SELECT COUNT(*) as c FROM sa_businesses")['c'] ?? 0;
$active     = $db->fetchOne("SELECT COUNT(*) as c FROM sa_businesses WHERE status='active'")['c'] ?? 0;
$pending    = $db->fetchOne("SELECT COUNT(*) as c FROM sa_businesses WHERE status='pending'")['c'] ?? 0;
$suspended  = $db->fetchOne("SELECT COUNT(*) as c FROM sa_businesses WHERE status='suspended'")['c'] ?? 0;
$trial      = $db->fetchOne("SELECT COUNT(*) as c FROM sa_businesses WHERE plan='trial'")['c'] ?? 0;
$enterprise = $db->fetchOne("SELECT COUNT(*) as c FROM sa_businesses WHERE plan='enterprise'")['c'] ?? 0;

// Filter
$search    = trim($_GET['search'] ?? '');
$fStatus   = $_GET['status'] ?? '';
$fPlan     = $_GET['plan'] ?? '';
$where = "WHERE 1=1";
$params = [];
if ($search) { $where .= " AND (business_name LIKE ? OR owner_name LIKE ? OR email LIKE ? OR city LIKE ?)"; $s="%$search%"; $params=array_merge($params,[$s,$s,$s,$s]); }
if ($fStatus) { $where .= " AND status=?"; $params[] = $fStatus; }
if ($fPlan)   { $where .= " AND plan=?";   $params[] = $fPlan; }

$businesses = $db->fetchAll("SELECT * FROM sa_businesses $where ORDER BY created_at DESC", $params);

$cu = Auth::currentUser();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Super Admin — VenuePro</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta21/dist/css/tabler.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{font-family:'Inter',sans-serif;}
body{background:#f0f2f7;min-height:100vh;}

/* ── TOP NAV ── */
.sa-nav{
  background:linear-gradient(135deg,#08111f 0%,#0c1830 100%);
  padding:.9rem 2rem; display:flex; align-items:center; justify-content:space-between;
  position:sticky;top:0;z-index:100;
  box-shadow:0 2px 20px rgba(0,0,0,.3);
  border-bottom:1px solid rgba(201,168,76,.2);
}
.sa-nav-brand{display:flex;align-items:center;gap:.75rem;}
.sa-nav-brand .icon{width:38px;height:38px;background:linear-gradient(135deg,#c9a84c,#e8c96a);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;box-shadow:0 4px 12px rgba(201,168,76,.3);}
.sa-nav-brand h1{color:#fff;font-size:1.1rem;font-weight:800;margin:0;}
.sa-nav-brand span{color:rgba(255,255,255,.4);font-size:.75rem;display:block;font-weight:500;}
.sa-nav-right{display:flex;align-items:center;gap:1rem;}
.sa-nav-user{display:flex;align-items:center;gap:.6rem;color:rgba(255,255,255,.8);font-size:.85rem;}
.sa-nav-user .avatar{width:34px;height:34px;background:linear-gradient(135deg,#c9a84c,#e8c96a);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#08111f;font-weight:800;font-size:.85rem;}
.btn-sa-logout{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);color:rgba(255,255,255,.7);border-radius:8px;padding:.4rem .9rem;font-size:.8rem;text-decoration:none;transition:all .2s;}
.btn-sa-logout:hover{background:rgba(239,68,68,.2);border-color:rgba(239,68,68,.4);color:#fca5a5;}
.btn-add-biz{background:linear-gradient(135deg,#c9a84c,#e8c96a);border:none;color:#08111f;border-radius:8px;padding:.45rem 1rem;font-size:.82rem;font-weight:700;text-decoration:none;transition:all .2s;cursor:pointer;}
.btn-add-biz:hover{transform:translateY(-1px);box-shadow:0 4px 16px rgba(201,168,76,.3);color:#08111f;}

/* ── PAGE BODY ── */
.sa-body{max-width:1400px;margin:0 auto;padding:2rem 1.5rem;}

/* ── STATS ── */
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;margin-bottom:2rem;}
.stat-card{background:#fff;border-radius:16px;padding:1.25rem 1.4rem;box-shadow:0 2px 12px rgba(0,0,0,.06);border:1px solid rgba(0,0,0,.05);transition:transform .2s;}
.stat-card:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(0,0,0,.1);}
.stat-card .label{font-size:.72rem;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.5rem;}
.stat-card .value{font-size:2rem;font-weight:800;line-height:1;margin-bottom:.3rem;}
.stat-card .sub{font-size:.75rem;color:#64748b;}
.stat-active .value{color:#10b981;}
.stat-pending .value{color:#f59e0b;}
.stat-suspended .value{color:#ef4444;}
.stat-total .value{color:#3b82f6;}
.stat-trial .value{color:#8b5cf6;}
.stat-enterprise .value{color:#c9a84c;}

/* ── TOOLBAR ── */
.sa-toolbar{display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;margin-bottom:1.5rem;}
.sa-search{flex:1;min-width:220px;position:relative;}
.sa-search input{width:100%;padding:.6rem 1rem .6rem 2.5rem;border:1px solid #e2e8f0;border-radius:10px;font-size:.875rem;background:#fff;outline:none;transition:all .2s;}
.sa-search input:focus{border-color:#c9a84c;box-shadow:0 0 0 3px rgba(201,168,76,.12);}
.sa-search .ico{position:absolute;left:.75rem;top:50%;transform:translateY(-50%);color:#94a3b8;}
.sa-select{padding:.6rem .9rem;border:1px solid #e2e8f0;border-radius:10px;font-size:.85rem;background:#fff;outline:none;cursor:pointer;color:#374151;}
.sa-select:focus{border-color:#c9a84c;}
.sa-count{color:#64748b;font-size:.85rem;margin-left:auto;}

/* ── BUSINESS CARDS ── */
.biz-list{display:flex;flex-direction:column;gap:1rem;}
.biz-card{
  background:#fff;border-radius:16px;padding:1.25rem 1.5rem;
  box-shadow:0 2px 12px rgba(0,0,0,.06);border:1px solid rgba(0,0,0,.05);
  display:flex;align-items:center;gap:1.25rem;
  transition:all .2s;position:relative;overflow:hidden;
}
.biz-card:hover{box-shadow:0 6px 24px rgba(0,0,0,.1);transform:translateY(-1px);}
.biz-card::before{content:'';position:absolute;left:0;top:0;bottom:0;width:4px;}
.biz-card.status-active::before{background:linear-gradient(180deg,#10b981,#059669);}
.biz-card.status-pending::before{background:linear-gradient(180deg,#f59e0b,#d97706);}
.biz-card.status-suspended::before{background:linear-gradient(180deg,#ef4444,#dc2626);}
.biz-card.status-cancelled::before{background:linear-gradient(180deg,#94a3b8,#64748b);}

.biz-avatar{width:52px;height:52px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;font-weight:800;flex-shrink:0;color:#fff;}
.plan-trial      .biz-avatar{background:linear-gradient(135deg,#8b5cf6,#7c3aed);}
.plan-basic    .biz-avatar{background:linear-gradient(135deg,#3b82f6,#2563eb);}
.plan-professional .biz-avatar{background:linear-gradient(135deg,#10b981,#059669);}
.plan-enterprise .biz-avatar{background:linear-gradient(135deg,#c9a84c,#b8860b);}

.biz-info{flex:1;min-width:0;}
.biz-name{font-size:1rem;font-weight:700;color:#1e293b;margin-bottom:.2rem;}
.biz-owner{font-size:.82rem;color:#64748b;margin-bottom:.4rem;}
.biz-meta{display:flex;flex-wrap:wrap;gap:.6rem;align-items:center;}
.biz-meta-item{display:flex;align-items:center;gap:.3rem;font-size:.78rem;color:#64748b;}
.biz-meta-item svg{color:#94a3b8;}

.biz-badges{display:flex;gap:.5rem;flex-shrink:0;flex-wrap:wrap;align-items:center;}
.badge-plan{padding:.25rem .7rem;border-radius:20px;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;}
.badge-plan.trial      {background:#f3f0ff;color:#7c3aed;}
.badge-plan.basic    {background:#eff6ff;color:#2563eb;}
.badge-plan.professional{background:#f0fdf4;color:#059669;}
.badge-plan.enterprise {background:#fefce8;color:#b8860b;border:1px solid #fde68a;}
.badge-status{padding:.25rem .7rem;border-radius:20px;font-size:.72rem;font-weight:700;}
.badge-status.active   {background:#d1fae5;color:#065f46;}
.badge-status.pending  {background:#fef3c7;color:#92400e;}
.badge-status.suspended{background:#fee2e2;color:#991b1b;}
.badge-status.cancelled{background:#f1f5f9;color:#475569;}

.biz-actions{display:flex;gap:.5rem;flex-shrink:0;}
.btn-icon{width:34px;height:34px;border-radius:8px;border:1px solid #e2e8f0;background:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s;text-decoration:none;color:#64748b;}
.btn-icon:hover{background:#f8fafc;border-color:#c9a84c;color:#c9a84c;}
.btn-icon.danger:hover{background:#fee2e2;border-color:#ef4444;color:#ef4444;}

.biz-dates{font-size:.72rem;color:#94a3b8;margin-top:.35rem;}
.biz-creds{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.45rem;align-items:center;}
.cred-item{display:inline-flex;align-items:center;gap:.3rem;font-size:.75rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:.2rem .55rem;color:#475569;font-family:monospace;}
.cred-item strong{color:#1e293b;font-weight:700;}
.cred-pass{position:relative;}
.btn-reveal{background:none;border:none;cursor:pointer;padding:0 0 0 .25rem;font-size:.8rem;line-height:1;opacity:.6;}
.btn-reveal:hover{opacity:1;}
.cred-divider{margin:.75rem 0 .5rem;font-size:.72rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.06em;border-top:1px dashed #e2e8f0;padding-top:.75rem;}

/* ── EMPTY ── */
.empty-state{text-align:center;padding:4rem 2rem;color:#94a3b8;}
.empty-state .icon{font-size:3rem;margin-bottom:1rem;}
.empty-state p{font-size:.9rem;}

/* ── MODAL ── */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);z-index:1000;align-items:center;justify-content:center;}
.modal-overlay.open{display:flex;}
.modal-box{background:#fff;border-radius:20px;width:100%;max-width:580px;max-height:90vh;overflow-y:auto;box-shadow:0 24px 64px rgba(0,0,0,.3);}
.modal-header{padding:1.5rem 1.75rem 1rem;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;}
.modal-header h3{font-size:1.1rem;font-weight:700;color:#1e293b;margin:0;}
.modal-close{background:none;border:none;cursor:pointer;color:#94a3b8;font-size:1.3rem;line-height:1;}
.modal-body{padding:1.5rem 1.75rem;}
.modal-footer{padding:1rem 1.75rem 1.5rem;display:flex;gap:.75rem;justify-content:flex-end;border-top:1px solid #f1f5f9;}
.form-group{margin-bottom:1rem;}
.field-error{display:none;color:#ef4444;font-size:.75rem;margin-top:.3rem;font-weight:500;}
.form-group label{display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:.4rem;}
.form-group input,.form-group select,.form-group textarea{width:100%;padding:.6rem .9rem;border:1px solid #e2e8f0;border-radius:8px;font-size:.875rem;outline:none;transition:border .2s;font-family:inherit;}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{border-color:#c9a84c;box-shadow:0 0 0 3px rgba(201,168,76,.1);}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem;}
.btn-primary-sa{background:linear-gradient(135deg,#c9a84c,#e8c96a);border:none;color:#08111f;border-radius:8px;padding:.6rem 1.4rem;font-weight:700;font-size:.875rem;cursor:pointer;}
.btn-secondary-sa{background:#f1f5f9;border:1px solid #e2e8f0;color:#475569;border-radius:8px;padding:.6rem 1.4rem;font-weight:600;font-size:.875rem;cursor:pointer;}

@media(max-width:768px){
  .sa-nav{padding:.75rem 1rem;}
  .sa-body{padding:1rem;}
  .biz-card{flex-wrap:wrap;}
  .form-row{grid-template-columns:1fr;}
  .stats-grid{grid-template-columns:repeat(3,1fr);}
}
</style>
</head>
<body>

<!-- NAV -->
<nav class="sa-nav">
  <div class="sa-nav-brand">
    <div class="icon">🛡️</div>
    <div>
      <h1>Super Admin</h1>
      <span>VenuePro Control Panel</span>
    </div>
  </div>
  <div class="sa-nav-right">
    <div class="sa-nav-user">
      <div class="avatar"><?= strtoupper(substr($cu['name'],0,1)) ?></div>
      <span><?= htmlspecialchars($cu['name']) ?></span>
    </div>
    <a href="<?= BASE_URL ?>/index.php" class="btn-sa-logout">← Dashboard</a>
    <a href="<?= BASE_URL ?>/superadmin/logout.php" class="btn-sa-logout">Logout</a>
  </div>
</nav>

<div class="sa-body">

<?php if (!empty($_SESSION['sa_error'])): ?>
<div style="background:#fee2e2;border:1px solid #fca5a5;color:#991b1b;padding:.9rem 1.2rem;border-radius:10px;margin-bottom:1.2rem;font-size:.875rem;display:flex;align-items:flex-start;gap:.6rem;">
  <span style="font-size:1.1rem;margin-top:1px;">⚠️</span>
  <span><?= $_SESSION['sa_error'] ?></span>
</div>
<?php unset($_SESSION['sa_error']); endif; ?>

<?php if (!empty($_SESSION['sa_success'])): ?>
<div style="background:#dcfce7;border:1px solid #86efac;color:#166534;padding:.9rem 1.2rem;border-radius:10px;margin-bottom:1.2rem;font-size:.875rem;display:flex;align-items:flex-start;gap:.6rem;">
  <span style="font-size:1.1rem;margin-top:1px;">✅</span>
  <span><?= $_SESSION['sa_success'] ?></span>
</div>
<?php unset($_SESSION['sa_success']); endif; ?>

  <!-- STATS -->
  <div class="stats-grid">
    <div class="stat-card stat-total">
      <div class="label">Total Businesses</div>
      <div class="value"><?= $total ?></div>
      <div class="sub">All registered</div>
    </div>
    <div class="stat-card stat-active">
      <div class="label">Active</div>
      <div class="value"><?= $active ?></div>
      <div class="sub">Running live</div>
    </div>
    <div class="stat-card stat-pending">
      <div class="label">Pending</div>
      <div class="value"><?= $pending ?></div>
      <div class="sub">Awaiting setup</div>
    </div>
    <div class="stat-card stat-suspended">
      <div class="label">Suspended</div>
      <div class="value"><?= $suspended ?></div>
      <div class="sub">Access blocked</div>
    </div>
    <div class="stat-card stat-trial">
      <div class="label">On Trial</div>
      <div class="value"><?= $trial ?></div>
      <div class="sub">Free trial</div>
    </div>
    <div class="stat-card stat-enterprise">
      <div class="label">Enterprise</div>
      <div class="value"><?= $enterprise ?></div>
      <div class="sub">Top tier clients</div>
    </div>
  </div>

  <!-- TOOLBAR -->
  <form method="GET" action="">
  <div class="sa-toolbar">
    <div class="sa-search">
      <span class="ico">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      </span>
      <input type="text" name="search" placeholder="Search business, owner, email, city…" value="<?= htmlspecialchars($search) ?>">
    </div>
    <select name="status" class="sa-select" onchange="this.form.submit()">
      <option value="">All Status</option>
      <option value="active"    <?= $fStatus==='active'    ?'selected':'' ?>>Active</option>
      <option value="pending"   <?= $fStatus==='pending'   ?'selected':'' ?>>Pending</option>
      <option value="suspended" <?= $fStatus==='suspended' ?'selected':'' ?>>Suspended</option>
      <option value="cancelled" <?= $fStatus==='cancelled' ?'selected':'' ?>>Cancelled</option>
    </select>
    <select name="plan" class="sa-select" onchange="this.form.submit()">
      <option value="">All Plans</option>
      <option value="trial"        <?= $fPlan==='trial'        ?'selected':'' ?>>Trial</option>
      <option value="basic"      <?= $fPlan==='basic'      ?'selected':'' ?>>Starter</option>
      <option value="professional" <?= $fPlan==='professional' ?'selected':'' ?>>Professional</option>
      <option value="enterprise"   <?= $fPlan==='enterprise'   ?'selected':'' ?>>Enterprise</option>
    </select>
    <button type="submit" class="btn-add-biz" style="background:#f1f5f9;color:#374151;border:1px solid #e2e8f0;">Search</button>
    <span class="sa-count"><?= count($businesses) ?> business<?= count($businesses)!=1?'es':'' ?></span>
    <button type="button" class="btn-add-biz" onclick="openModal()">+ Add Business</button>
  </div>
  </form>

  <!-- BUSINESS CARDS -->
  <div class="biz-list">
    <?php if (empty($businesses)): ?>
    <div class="empty-state">
      <div class="icon">🏢</div>
      <p>No businesses found. Add your first client!</p>
    </div>
    <?php else: ?>
    <?php foreach ($businesses as $b):
      $initials = strtoupper(implode('', array_map(fn($w)=>$w[0], array_slice(explode(' ', $b['business_name']), 0, 2))));
      $planColors = ['trial'=>'#8b5cf6','basic'=>'#3b82f6','professional'=>'#10b981','enterprise'=>'#c9a84c'];
      $planColor = $planColors[$b['plan']] ?? '#64748b';
    ?>
    <div class="biz-card status-<?= $b['status'] ?> plan-<?= $b['plan'] ?>">
      <div class="biz-avatar"><?= $initials ?></div>

      <div class="biz-info">
        <div class="biz-name"><?= htmlspecialchars($b['business_name']) ?></div>
        <div class="biz-owner">👤 <?= htmlspecialchars($b['owner_name']) ?></div>
        <div class="biz-meta">
          <span class="biz-meta-item">
            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            <?= htmlspecialchars($b['email']) ?>
          </span>
          <?php if ($b['phone']): ?>
          <span class="biz-meta-item">
            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            <?= htmlspecialchars($b['phone']) ?>
          </span>
          <?php endif; ?>
          <?php if ($b['city']): ?>
          <span class="biz-meta-item">
            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <?= htmlspecialchars($b['city']) ?>, <?= htmlspecialchars($b['country']) ?>
          </span>
          <?php endif; ?>
          <span class="biz-meta-item">
            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            Max <?= $b['max_users'] ?> users · <?= $b['max_branches'] ?> branch<?= $b['max_branches']>1?'es':'' ?>
          </span>
        </div>
        <div class="biz-dates">
          Joined: <?= date('d M Y', strtotime($b['created_at'])) ?>
          <?php if (!empty($b['subscription_ends_at'])): ?>
           · Subscription: <?= date('d M Y', strtotime($b['subscription_ends_at'])) ?>
          <?php endif; ?>
          <?php if ($b['plan']==='trial' && !empty($b['trial_ends_at'])): ?>
           · Trial ends: <?= date('d M Y', strtotime($b['trial_ends_at'])) ?>
          <?php endif; ?>
        </div>
        <?php if (!empty($b['admin_user_id'])): ?>
        <div class="biz-creds">
          <span class="cred-item" title="User ID"><svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg> <strong><?= htmlspecialchars($b['admin_user_id']) ?></strong></span>
          <span class="cred-item" title="Username"><svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> <?= htmlspecialchars($b['admin_username']) ?></span>
          <span class="cred-item cred-pass" title="Password"><svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg> <span class="pass-hidden">••••••••</span><span class="pass-visible" style="display:none"><?= htmlspecialchars($b['admin_password_plain']) ?></span><button type="button" class="btn-reveal" onclick="togglePass(this)" title="Show/hide">👁</button></span>
        </div>
        <?php endif; ?>
      </div>

      <div class="biz-badges">
        <span class="badge-plan <?= $b['plan'] ?>"><?= ucfirst($b['plan']) ?></span>
        <span class="badge-status <?= $b['status'] ?>"><?= ucfirst($b['status']) ?></span>
      </div>

      <div class="biz-actions">
        <button type="button" class="btn-icon" title="Edit" onclick="openEditModal(<?= $b['id'] ?>)">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        </a>
        <?php if ($b['status']==='active'): ?>
        <a href="action.php?id=<?= $b['id'] ?>&act=suspend" class="btn-icon danger" title="Suspend" onclick="return confirm('Suspend this business?')">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
        </a>
        <?php else: ?>
        <a href="action.php?id=<?= $b['id'] ?>&act=activate" class="btn-icon" title="Activate" onclick="return confirm('Activate this business?')">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        </a>
        <?php endif; ?>
        <a href="action.php?id=<?= $b['id'] ?>&act=delete" class="btn-icon danger" title="Delete" onclick="return confirm('Permanently delete this business?')">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
        </a>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>

</div>

<!-- ADD MODAL -->
<div class="modal-overlay" id="addModal">
  <div class="modal-box">
    <div class="modal-header">
      <h3>Add New Business</h3>
      <button class="modal-close" onclick="closeModal()">×</button>
    </div>
    <form method="POST" action="create.php" id="createBizForm" onsubmit="return validateBizForm()">
    <div class="modal-body">
      <div class="form-row">
        <div class="form-group">
          <label>Business Name *</label>
          <input type="text" name="business_name" required placeholder="Royal Events Hall">
        </div>
        <div class="form-group">
          <label>Owner Name *</label>
          <input type="text" name="owner_name" required placeholder="Full name">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Email *</label>
          <input type="email" name="email" id="biz_email" required placeholder="owner@business.lk" oninput="clearFieldError(this)">
          <div class="field-error" id="err_email"></div>
        </div>
        <div class="form-group">
          <label>Phone *</label>
          <input type="text" name="phone" id="biz_phone" required placeholder="+94 77 000 0000" oninput="clearFieldError(this)">
          <div class="field-error" id="err_phone"></div>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>City</label>
          <input type="text" name="city" placeholder="Colombo">
        </div>
        <div class="form-group">
          <label>Country</label>
          <input type="text" name="country" value="Sri Lanka">
        </div>
      </div>
      <div class="form-group">
        <label>Address</label>
        <textarea name="address" rows="2" placeholder="Street address…"></textarea>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Plan</label>
          <select name="plan">
            <option value="trial">Trial</option>
            <option value="basic">Starter</option>
            <option value="professional">Professional</option>
            <option value="enterprise">Enterprise</option>
          </select>
        </div>
        <div class="form-group">
          <label>Status</label>
          <select name="status">
            <option value="pending">Pending</option>
            <option value="active">Active</option>
            <option value="suspended">Suspended</option>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Max Users</label>
          <input type="number" name="max_users" value="5" min="1">
        </div>
        <div class="form-group">
          <label>Max Branches</label>
          <input type="number" name="max_branches" value="1" min="1">
        </div>
      </div>
      <div class="form-group">
        <label>Notes</label>
        <textarea name="notes" rows="2" placeholder="Internal notes…"></textarea>
      </div>

      <div class="cred-divider">Admin Login Credentials</div>
      <div class="form-row">
        <div class="form-group">
          <label>User ID <span style="font-weight:400;color:#94a3b8">(auto-generated)</span></label>
          <div style="display:flex;gap:.5rem;align-items:center;">
            <input type="text" name="admin_user_id" id="field_user_id" placeholder="e.g. D842" style="text-transform:uppercase;font-family:monospace;" maxlength="10">
            <button type="button" onclick="genUserId()" style="white-space:nowrap;padding:.6rem .75rem;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:8px;cursor:pointer;font-size:.8rem;color:#374151;">↺ Gen</button>
          </div>
        </div>
        <div class="form-group">
          <label>Username</label>
          <input type="text" name="admin_username" id="field_username" value="admin" placeholder="admin">
        </div>
      </div>
      <div class="form-group">
        <label>Password <span style="font-weight:400;color:#94a3b8">(auto-generated)</span></label>
        <div style="display:flex;gap:.5rem;align-items:center;">
          <input type="text" name="admin_password" id="field_password" placeholder="e.g. blu4021" style="font-family:monospace;flex:1;">
          <button type="button" onclick="genPassword()" style="white-space:nowrap;padding:.6rem .75rem;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:8px;cursor:pointer;font-size:.8rem;color:#374151;">↺ Gen</button>
        </div>
        <div style="font-size:.75rem;color:#94a3b8;margin-top:.3rem;">Format: 3 colour letters + 4 digits (e.g. <em>blu4021</em>)</div>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn-secondary-sa" onclick="closeModal()">Cancel</button>
      <button type="submit" class="btn-primary-sa">Create Business</button>
    </div>
    </form>
  </div>
</div>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="editModal">
  <div class="modal-box">
    <div class="modal-header">
      <h3>Edit Business</h3>
      <button class="modal-close" onclick="closeEditModal()">×</button>
    </div>
    <form method="POST" action="edit.php" id="editForm">
    <input type="hidden" name="id" id="edit_id">
    <div class="modal-body">
      <div class="form-row">
        <div class="form-group">
          <label>Business Name *</label>
          <input type="text" name="business_name" id="edit_business_name" required>
        </div>
        <div class="form-group">
          <label>Owner Name *</label>
          <input type="text" name="owner_name" id="edit_owner_name" required>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Email *</label>
          <input type="email" name="email" id="edit_email" required>
        </div>
        <div class="form-group">
          <label>Phone</label>
          <input type="text" name="phone" id="edit_phone">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>City</label>
          <input type="text" name="city" id="edit_city">
        </div>
        <div class="form-group">
          <label>Country</label>
          <input type="text" name="country" id="edit_country">
        </div>
      </div>
      <div class="form-group">
        <label>Address</label>
        <textarea name="address" id="edit_address" rows="2"></textarea>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Plan</label>
          <select name="plan" id="edit_plan">
            <option value="trial">Trial</option>
            <option value="basic">Starter</option>
            <option value="professional">Professional</option>
            <option value="enterprise">Enterprise</option>
          </select>
        </div>
        <div class="form-group">
          <label>Status</label>
          <select name="status" id="edit_status">
            <option value="pending">Pending</option>
            <option value="active">Active</option>
            <option value="suspended">Suspended</option>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Max Users</label>
          <input type="number" name="max_users" id="edit_max_users" min="1">
        </div>
        <div class="form-group">
          <label>Max Branches</label>
          <input type="number" name="max_branches" id="edit_max_branches" min="1">
        </div>
      </div>
      <div class="form-group">
        <label>Notes</label>
        <textarea name="notes" id="edit_notes" rows="2"></textarea>
      </div>

      <div class="cred-divider">Admin Credentials</div>
      <div class="form-row">
        <div class="form-group">
          <label>User ID <span style="font-weight:400;color:#94a3b8">(read-only)</span></label>
          <input type="text" id="edit_admin_user_id" readonly style="background:#f8fafc;font-family:monospace;color:#64748b;">
        </div>
        <div class="form-group">
          <label>Username</label>
          <input type="text" name="admin_username" id="edit_admin_username">
        </div>
      </div>
      <div class="form-group">
        <label>New Password <span style="font-weight:400;color:#94a3b8">(leave blank to keep current)</span></label>
        <div style="display:flex;gap:.5rem;align-items:center;">
          <input type="text" name="admin_password_new" id="edit_admin_password_new" placeholder="Leave blank to keep" style="font-family:monospace;flex:1;">
          <button type="button" onclick="genEditPassword()" style="white-space:nowrap;padding:.6rem .75rem;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:8px;cursor:pointer;font-size:.8rem;color:#374151;">↺ Gen</button>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn-secondary-sa" onclick="closeEditModal()">Cancel</button>
      <button type="submit" class="btn-primary-sa">Save Changes</button>
    </div>
    </form>
  </div>
</div>

<script>
// ── Form validation ──
function validateBizForm(){
  let valid = true;

  const emailEl = document.getElementById('biz_email');
  const phoneEl = document.getElementById('biz_phone');

  // Email
  const emailVal = emailEl.value.trim();
  const emailRx = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (!emailRx.test(emailVal)) {
    showFieldError(emailEl, 'err_email', 'Enter a valid email address.');
    valid = false;
  } else {
    clearFieldError(emailEl);
  }

  // Phone — allow +, digits, spaces, dashes, parens; min 7 digits
  const phoneVal = phoneEl.value.trim();
  const digits = phoneVal.replace(/\D/g, '');
  const phoneRx = /^[+\d][\d\s\-().]{5,19}$/;
  if (!phoneRx.test(phoneVal) || digits.length < 7) {
    showFieldError(phoneEl, 'err_phone', 'Enter a valid phone number (min 7 digits).');
    valid = false;
  } else {
    clearFieldError(phoneEl);
  }

  return valid;
}

function showFieldError(el, errId, msg){
  el.style.borderColor = '#ef4444';
  const errEl = document.getElementById(errId);
  if(errEl){ errEl.textContent = msg; errEl.style.display = 'block'; }
}

function clearFieldError(el){
  el.style.borderColor = '';
  const errId = 'err_' + (el.name === 'email' ? 'email' : 'phone');
  const errEl = document.getElementById(errId);
  if(errEl){ errEl.textContent = ''; errEl.style.display = 'none'; }
}

// ── Modal ──
function openModal(){
  genUserId();
  genPassword();
  document.getElementById('addModal').classList.add('open');
}
function closeModal(){ document.getElementById('addModal').classList.remove('open'); }
document.getElementById('addModal').addEventListener('click', function(e){ if(e.target===this) closeModal(); });

// ── Auto-search ──
document.querySelector('.sa-search input').addEventListener('keydown', function(e){
  if(e.key==='Enter') this.closest('form').submit();
});

// ── Credential generators ──
const COLORS = ['red','blu','grn','ylw','org','pnk','cyn','vlt','brn','gry','sky','ros','crl','lav','tan'];

function genUserId(){
  const letters = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
  const uid = letters[Math.floor(Math.random()*letters.length)]
            + String(Math.floor(100 + Math.random()*900));
  document.getElementById('field_user_id').value = uid;
}

function genPassword(){
  const color = COLORS[Math.floor(Math.random()*COLORS.length)];
  const digits = String(Math.floor(1000 + Math.random()*9000));
  document.getElementById('field_password').value = color + digits;
}

// ── Edit modal ──
function openEditModal(id){
  fetch('edit.php?id=' + id)
    .then(r => r.json())
    .then(b => {
      document.getElementById('edit_id').value             = b.id;
      document.getElementById('edit_business_name').value  = b.business_name || '';
      document.getElementById('edit_owner_name').value     = b.owner_name    || '';
      document.getElementById('edit_email').value          = b.email         || '';
      document.getElementById('edit_phone').value          = b.phone         || '';
      document.getElementById('edit_city').value           = b.city          || '';
      document.getElementById('edit_country').value        = b.country       || 'Sri Lanka';
      document.getElementById('edit_address').value        = b.address       || '';
      document.getElementById('edit_plan').value           = b.plan          || 'trial';
      document.getElementById('edit_status').value         = b.status        || 'pending';
      document.getElementById('edit_max_users').value      = b.max_users     || 5;
      document.getElementById('edit_max_branches').value   = b.max_branches  || 1;
      document.getElementById('edit_notes').value          = b.notes         || '';
      document.getElementById('edit_admin_user_id').value  = b.admin_user_id || '';
      document.getElementById('edit_admin_username').value = b.admin_username || '';
      document.getElementById('edit_admin_password_new').value = '';
      document.getElementById('editModal').classList.add('open');
    })
    .catch(() => alert('Failed to load business data.'));
}
function closeEditModal(){ document.getElementById('editModal').classList.remove('open'); }
document.getElementById('editModal').addEventListener('click', function(e){ if(e.target===this) closeEditModal(); });

function genEditPassword(){
  const color = COLORS[Math.floor(Math.random()*COLORS.length)];
  const digits = String(Math.floor(1000 + Math.random()*9000));
  document.getElementById('edit_admin_password_new').value = color + digits;
}

// ── Toggle password visibility on cards ──
function togglePass(btn){
  const cred = btn.closest('.cred-pass');
  const hidden = cred.querySelector('.pass-hidden');
  const visible = cred.querySelector('.pass-visible');
  if(hidden.style.display === 'none'){
    hidden.style.display = '';
    visible.style.display = 'none';
    btn.textContent = '👁';
  } else {
    hidden.style.display = 'none';
    visible.style.display = '';
    btn.textContent = '🙈';
  }
}
</script>
</body>
</html>
