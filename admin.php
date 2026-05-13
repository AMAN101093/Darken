<?php
// admin.php — Admin Dashboard
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=admin.php');
    exit;
}

require_once 'config/db.php';

// Role guard — admins only
$stmt = $pdo->prepare("SELECT first_name, last_name, role FROM users WHERE id = ? AND is_active = 1");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();

if (!$admin || $admin['role'] !== 'admin') {
    http_response_code(403);
    die('<!DOCTYPE html><html><head><title>Access Denied</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=Raleway:wght@400&display=swap" rel="stylesheet">
    <style>*{margin:0;padding:0;box-sizing:border-box;}body{background:#061e35;color:#b8ddf5;font-family:Raleway,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;flex-direction:column;gap:1.5rem;}h1{font-family:Cinzel,serif;color:#ff4d6d;font-size:2rem;}p{color:#6db8e8;}a{color:#6db8e8;}</style>
    </head><body><h1>403 — Access Denied</h1><p>You must be an admin to view this page.</p><a href="index.php">← Back to Dashboard</a></body></html>');
}

$admin_name = htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']);

// Quick stats
$total_users    = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();
$total_bookings = $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$pending_bookings = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'")->fetchColumn();
$active_members = $pdo->query("SELECT COUNT(*) FROM memberships WHERE status = 'active'")->fetchColumn();
$total_coaches  = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'coach' AND is_active = 1")->fetchColumn();
$total_admins   = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1")->fetchColumn();

// Recent activity — last 5 bookings
$recent = $pdo->query("
    SELECT b.booking_reference, b.program, b.swimmer_name, b.status, b.created_at,
           u.first_name, u.last_name
    FROM bookings b
    JOIN users u ON u.id = b.booked_by_user_id
    ORDER BY b.created_at DESC
    LIMIT 6
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard — Darken Shadows SC</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700;900&family=Raleway:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
:root {
  --ocean-1:#e8f4fd; --ocean-2:#b8ddf5; --ocean-3:#6db8e8;
  --ocean-4:#2a8cc4; --ocean-5:#1565a0; --ocean-6:#0d3d6b; --ocean-7:#061e35;
  --accent:#ffc847; --accent-2:#ff7c3a; --danger:#ff4d6d; --success:#00d68f;
  --font-display:'Cinzel',serif; --font-body:'Raleway',sans-serif;
  --radius:14px; --radius-sm:8px;
  --card-bg:rgba(13,61,107,.45); --card-border:rgba(109,184,232,.15);
  --transition:.25s cubic-bezier(.4,0,.2,1);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html{scroll-behavior:smooth;}
body{font-family:var(--font-body);background:var(--ocean-7);color:var(--ocean-1);min-height:100vh;overflow-x:hidden;}
body::before{content:'';position:fixed;inset:0;z-index:0;
  background:radial-gradient(ellipse 70% 55% at 10% 5%,rgba(255,200,71,.1) 0%,transparent 55%),
  radial-gradient(ellipse 60% 65% at 90% 92%,rgba(42,140,196,.18) 0%,transparent 55%),
  linear-gradient(160deg,var(--ocean-7) 0%,#092840 50%,var(--ocean-7) 100%);pointer-events:none;}
body::after{content:'';position:fixed;inset:0;z-index:0;
  background-image:radial-gradient(circle at 1px 1px,rgba(109,184,232,.04) 1px,transparent 0);
  background-size:36px 36px;pointer-events:none;}

/* ── Nav ── */
nav{position:sticky;top:0;z-index:100;display:flex;align-items:center;justify-content:space-between;
  padding:1rem 2.5rem;background:rgba(6,30,53,.9);backdrop-filter:blur(20px);
  border-bottom:1px solid rgba(255,200,71,.15);}
.nav-logo{font-family:var(--font-display);font-size:1rem;font-weight:700;letter-spacing:.14em;color:var(--accent);text-decoration:none;text-transform:uppercase;}
.nav-links{display:flex;gap:1.6rem;align-items:center;}
.nav-links a{font-size:.78rem;font-weight:600;letter-spacing:.1em;color:var(--ocean-2);text-decoration:none;text-transform:uppercase;transition:color var(--transition);}
.nav-links a:hover{color:var(--accent);}
.nav-badge{background:rgba(255,200,71,.15);border:1px solid rgba(255,200,71,.35);color:var(--accent);padding:.25rem .8rem;border-radius:100px;font-size:.66rem;font-weight:700;letter-spacing:.15em;text-transform:uppercase;}
.nav-user{display:flex;align-items:center;gap:.8rem;font-size:.8rem;color:rgba(184,221,245,.55);}
.nav-user span{color:var(--ocean-2);font-weight:600;}

/* ── Page ── */
.page{position:relative;z-index:1;max-width:1200px;margin:0 auto;padding:2.5rem 2rem 6rem;}

/* ── Hero strip ── */
.admin-hero{
  background:linear-gradient(135deg,rgba(255,200,71,.08) 0%,rgba(42,140,196,.1) 100%);
  border:1px solid rgba(255,200,71,.18);
  border-radius:var(--radius);
  padding:2rem 2.5rem;
  display:flex;align-items:center;justify-content:space-between;
  margin-bottom:2rem;
  opacity:0;animation:fadeUp .6s .1s ease forwards;
  position:relative;overflow:hidden;
}
.admin-hero::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;
  background:linear-gradient(90deg,var(--accent-2),var(--accent),transparent);}
.hero-text h1{font-family:var(--font-display);font-size:clamp(1.3rem,2.5vw,1.9rem);font-weight:700;color:var(--white);letter-spacing:.04em;margin-bottom:.35rem;}
.hero-text p{font-size:.88rem;color:var(--ocean-2);font-weight:300;}
.hero-right{display:flex;flex-direction:column;align-items:flex-end;gap:.4rem;}
.hero-time{font-family:var(--font-display);font-size:1.6rem;font-weight:700;color:var(--accent);line-height:1;}
.hero-date{font-size:.75rem;color:var(--ocean-3);letter-spacing:.08em;}

/* ── Stat cards row ── */
.stats-row{display:grid;grid-template-columns:repeat(6,1fr);gap:.9rem;margin-bottom:2rem;
  opacity:0;animation:fadeUp .6s .2s ease forwards;}
.stat-card{background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius-sm);
  padding:1.1rem 1rem;text-align:center;backdrop-filter:blur(10px);
  transition:border-color var(--transition),transform var(--transition);}
.stat-card:hover{border-color:rgba(109,184,232,.4);transform:translateY(-3px);}
.stat-num{font-family:var(--font-display);font-size:1.8rem;font-weight:700;color:var(--ocean-3);line-height:1;margin-bottom:.25rem;}
.stat-label{font-size:.62rem;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:rgba(184,221,245,.45);}
.stat-card.accent .stat-num{color:var(--accent);}
.stat-card.success .stat-num{color:var(--success);}
.stat-card.danger .stat-num{color:var(--danger);}

/* ── Main nav panels ── */
.panels-title{font-family:var(--font-display);font-size:.7rem;font-weight:600;letter-spacing:.22em;
  text-transform:uppercase;color:var(--ocean-3);margin-bottom:1rem;
  opacity:0;animation:fadeUp .6s .28s ease forwards;}
.panels-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:1.2rem;margin-bottom:2.5rem;
  opacity:0;animation:fadeUp .6s .32s ease forwards;}

.panel-card{
  background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius);
  padding:2rem 1.8rem;text-decoration:none;color:inherit;
  display:flex;flex-direction:column;gap:1rem;
  backdrop-filter:blur(12px);
  transition:border-color var(--transition),transform var(--transition),box-shadow var(--transition);
  position:relative;overflow:hidden;cursor:pointer;
}
.panel-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;
  background:var(--panel-accent,var(--ocean-4));opacity:.8;}
.panel-card::after{content:'';position:absolute;bottom:-60px;right:-40px;
  width:160px;height:160px;border-radius:50%;
  background:radial-gradient(circle,var(--panel-glow,rgba(42,140,196,.15)) 0%,transparent 70%);
  pointer-events:none;transition:transform var(--transition);}
.panel-card:hover{border-color:var(--panel-border,rgba(109,184,232,.45));
  transform:translateY(-6px);box-shadow:0 20px 50px rgba(0,0,0,.35);}
.panel-card:hover::after{transform:scale(1.4);}
.panel-card.disabled{opacity:.5;cursor:not-allowed;pointer-events:none;}

.panel-icon{font-size:2.2rem;width:60px;height:60px;border-radius:50%;
  display:flex;align-items:center;justify-content:center;
  background:var(--panel-icon-bg,rgba(42,140,196,.15));
  border:1px solid var(--panel-icon-border,rgba(109,184,232,.25));}
.panel-heading{font-family:var(--font-display);font-size:1.05rem;font-weight:700;
  color:var(--white);letter-spacing:.04em;}
.panel-sub{font-size:.82rem;font-weight:300;color:var(--ocean-2);line-height:1.6;}
.panel-tags{display:flex;flex-wrap:wrap;gap:.4rem;margin-top:.3rem;}
.panel-tag{font-size:.62rem;font-weight:600;letter-spacing:.1em;text-transform:uppercase;
  padding:.22rem .7rem;border-radius:100px;
  background:var(--panel-tag-bg,rgba(109,184,232,.1));
  border:1px solid var(--panel-tag-border,rgba(109,184,232,.2));
  color:var(--panel-tag-color,var(--ocean-3));}
.panel-cta{display:flex;align-items:center;gap:.5rem;margin-top:.5rem;
  font-size:.75rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;
  color:var(--panel-cta-color,var(--ocean-3));}
.panel-cta .arrow{transition:transform var(--transition);}
.panel-card:hover .panel-cta .arrow{transform:translateX(5px);}
.coming-soon-tag{position:absolute;top:1.1rem;right:1.2rem;
  background:rgba(255,200,71,.15);border:1px solid rgba(255,200,71,.35);
  color:var(--accent);font-size:.6rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;
  padding:.22rem .75rem;border-radius:100px;}

/* ── Recent activity ── */
.section-head{display:flex;align-items:center;gap:.8rem;margin-bottom:1rem;
  opacity:0;animation:fadeUp .6s .4s ease forwards;}
.section-head h2{font-family:var(--font-display);font-size:.78rem;font-weight:600;
  letter-spacing:.18em;text-transform:uppercase;color:var(--ocean-2);}
.section-head .line{flex:1;height:1px;background:linear-gradient(90deg,rgba(109,184,232,.2),transparent);}
.section-head a{font-size:.7rem;font-weight:600;letter-spacing:.1em;text-transform:uppercase;
  color:var(--ocean-4);text-decoration:none;transition:color var(--transition);}
.section-head a:hover{color:var(--ocean-3);}

.activity-table{
  background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius);
  overflow:hidden;backdrop-filter:blur(10px);
  opacity:0;animation:fadeUp .6s .45s ease forwards;
}
.activity-table table{width:100%;border-collapse:collapse;}
.activity-table thead tr{background:rgba(255,255,255,.04);border-bottom:1px solid rgba(109,184,232,.12);}
.activity-table th{padding:.75rem 1.2rem;font-size:.65rem;font-weight:700;letter-spacing:.18em;
  text-transform:uppercase;color:rgba(184,221,245,.45);text-align:left;}
.activity-table td{padding:.85rem 1.2rem;font-size:.83rem;font-weight:400;color:var(--ocean-1);
  border-bottom:1px solid rgba(109,184,232,.07);}
.activity-table tr:last-child td{border-bottom:none;}
.activity-table tr:hover td{background:rgba(255,255,255,.03);}
.prog-badge{display:inline-block;padding:.2rem .65rem;border-radius:100px;font-size:.65rem;
  font-weight:700;letter-spacing:.1em;text-transform:uppercase;
  background:rgba(109,184,232,.1);border:1px solid rgba(109,184,232,.2);color:var(--ocean-3);}
.status-dot{display:inline-flex;align-items:center;gap:.4rem;font-size:.78rem;font-weight:600;}
.status-dot::before{content:'';width:7px;height:7px;border-radius:50%;background:currentColor;flex-shrink:0;}
.status-dot.pending{color:#ffc847;}.status-dot.active,.status-dot.confirmed{color:#00d68f;}
.status-dot.cancelled{color:#ff4d6d;}.status-dot.completed{color:#6db8e8;}

/* ── Admin quick actions ── */
.quick-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:.8rem;margin-top:1.5rem;
  opacity:0;animation:fadeUp .6s .5s ease forwards;}
.quick-btn{background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius-sm);
  padding:1rem;text-align:center;text-decoration:none;color:var(--ocean-2);
  font-size:.75rem;font-weight:600;letter-spacing:.1em;text-transform:uppercase;
  transition:all var(--transition);backdrop-filter:blur(8px);}
.quick-btn:hover{border-color:rgba(109,184,232,.4);color:var(--ocean-1);transform:translateY(-2px);}
.quick-btn .qicon{font-size:1.4rem;display:block;margin-bottom:.45rem;}

@keyframes fadeUp{from{opacity:0;transform:translateY(18px)}to{opacity:1;transform:translateY(0)}}
@media(max-width:1024px){.stats-row{grid-template-columns:repeat(3,1fr);}.panels-grid{grid-template-columns:1fr 1fr;}}
@media(max-width:768px){nav{padding:1rem 1.2rem;}.page{padding:1.5rem 1rem 4rem;}
  .stats-row{grid-template-columns:repeat(2,1fr);}.panels-grid{grid-template-columns:1fr;}
  .quick-grid{grid-template-columns:repeat(2,1fr);}.admin-hero{flex-direction:column;gap:1rem;align-items:flex-start;}}
</style>
</head>
<body>

<nav>
  <a href="index.php" class="nav-logo">Darken Shadows SC</a>
  <div class="nav-links">
    <a href="index.php">Dashboard</a>
    <a href="database.php">Users</a>
    <a href="coach.php">Coaches</a>
    <span class="nav-badge">⚙ Admin Panel</span>
  </div>
  <div class="nav-user">
    <span><?= $admin_name ?></span>
    &nbsp;·&nbsp;
    <a href="logout.php" style="font-size:.78rem;color:rgba(184,221,245,.45);text-decoration:none;">Logout</a>
  </div>
</nav>

<div class="page">

  <!-- Hero strip -->
  <div class="admin-hero">
    <div class="hero-text">
      <h1>Welcome back, <?= htmlspecialchars($admin['first_name']) ?> ⚙</h1>
      <p>Darken Shadows SC — Administration Control Panel</p>
    </div>
    <div class="hero-right">
      <div class="hero-time" id="admin-clock">--:--</div>
      <div class="hero-date" id="admin-date"></div>
    </div>
  </div>
<?php if (($user['role'] ?? '') === 'admin'): ?>
  <div class="section-head" style="margin-top:0;">
    <h2>Admin Access</h2>
    <div class="line"></div>
  </div>
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:.5rem;opacity:0;animation:fadeUp .6s .22s ease forwards;">
    <a href="admin.php" style="display:flex;align-items:center;gap:.9rem;background:rgba(255,200,71,.07);border:1px solid rgba(255,200,71,.25);border-radius:var(--radius);padding:1.2rem 1.4rem;text-decoration:none;transition:all var(--transition);" onmouseover="this.style.borderColor='rgba(255,200,71,.5)'" onmouseout="this.style.borderColor='rgba(255,200,71,.25)'">
      <span style="font-size:1.6rem;">⚙</span>
      <div>
        <div style="font-family:var(--font-display);font-size:.85rem;font-weight:700;color:#ffc847;letter-spacing:.06em;">Admin Dashboard</div>
        <div style="font-size:.74rem;color:rgba(184,221,245,.5);margin-top:.15rem;">Overview & stats</div>
      </div>
    </a>
    <a href="database.php" style="display:flex;align-items:center;gap:.9rem;background:rgba(0,200,255,.06);border:1px solid rgba(0,200,255,.2);border-radius:var(--radius);padding:1.2rem 1.4rem;text-decoration:none;transition:all var(--transition);" onmouseover="this.style.borderColor='rgba(0,200,255,.45)'" onmouseout="this.style.borderColor='rgba(0,200,255,.2)'">
      <span style="font-size:1.6rem;">👥</span>
      <div>
        <div style="font-family:var(--font-display);font-size:.85rem;font-weight:700;color:#00c8ff;letter-spacing:.06em;">User Database</div>
        <div style="font-size:.74rem;color:rgba(184,221,245,.5);margin-top:.15rem;">Members & bookings</div>
      </div>
    </a>
    <a href="pending.php" style="display:flex;align-items:center;gap:.9rem;background:rgba(0,229,176,.06);border:1px solid rgba(0,229,176,.2);border-radius:var(--radius);padding:1.2rem 1.4rem;text-decoration:none;transition:all var(--transition);" onmouseover="this.style.borderColor='rgba(0,229,176,.45)'" onmouseout="this.style.borderColor='rgba(0,229,176,.2)'">
      <span style="font-size:1.6rem;">📋</span>
      <div>
        <div style="font-family:var(--font-display);font-size:.85rem;font-weight:700;color:#00e5b0;letter-spacing:.06em;">Enrollment Queue</div>
        <div style="font-size:.74rem;color:rgba(184,221,245,.5);margin-top:.15rem;">Approve enrollments</div>
      </div>
    </a>
  </div>
  <?php endif; ?>
  <?php if (($user['role'] ?? '') === 'admin'): ?>
  <div class="section-head" style="margin-top:0;">
    <h2>Admin Access</h2>
    <div class="line"></div>
  </div>
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:.5rem;opacity:0;animation:fadeUp .6s .22s ease forwards;">
    <a href="admin.php" style="display:flex;align-items:center;gap:.9rem;background:rgba(255,200,71,.07);border:1px solid rgba(255,200,71,.25);border-radius:var(--radius);padding:1.2rem 1.4rem;text-decoration:none;transition:all var(--transition);" onmouseover="this.style.borderColor='rgba(255,200,71,.5)'" onmouseout="this.style.borderColor='rgba(255,200,71,.25)'">
      <span style="font-size:1.6rem;">⚙</span>
      <div>
        <div style="font-family:var(--font-display);font-size:.85rem;font-weight:700;color:#ffc847;letter-spacing:.06em;">Admin Dashboard</div>
        <div style="font-size:.74rem;color:rgba(184,221,245,.5);margin-top:.15rem;">Overview & stats</div>
      </div>
    </a>
    <a href="database.php" style="display:flex;align-items:center;gap:.9rem;background:rgba(0,200,255,.06);border:1px solid rgba(0,200,255,.2);border-radius:var(--radius);padding:1.2rem 1.4rem;text-decoration:none;transition:all var(--transition);" onmouseover="this.style.borderColor='rgba(0,200,255,.45)'" onmouseout="this.style.borderColor='rgba(0,200,255,.2)'">
      <span style="font-size:1.6rem;">👥</span>
      <div>
        <div style="font-family:var(--font-display);font-size:.85rem;font-weight:700;color:#00c8ff;letter-spacing:.06em;">User Database</div>
        <div style="font-size:.74rem;color:rgba(184,221,245,.5);margin-top:.15rem;">Members & bookings</div>
      </div>
    </a>
    <a href="pending.php" style="display:flex;align-items:center;gap:.9rem;background:rgba(0,229,176,.06);border:1px solid rgba(0,229,176,.2);border-radius:var(--radius);padding:1.2rem 1.4rem;text-decoration:none;transition:all var(--transition);" onmouseover="this.style.borderColor='rgba(0,229,176,.45)'" onmouseout="this.style.borderColor='rgba(0,229,176,.2)'">
      <span style="font-size:1.6rem;">📋</span>
      <div>
        <div style="font-family:var(--font-display);font-size:.85rem;font-weight:700;color:#00e5b0;letter-spacing:.06em;">Enrollment Queue</div>
        <div style="font-size:.74rem;color:rgba(184,221,245,.5);margin-top:.15rem;">Approve enrollments</div>
      </div>
    </a>
  </div>
  <?php endif; ?>
  <!-- Stats row -->
  <div class="stats-row">
    <div class="stat-card accent">
      <div class="stat-num"><?= number_format($total_users) ?></div>
      <div class="stat-label">Total Members</div>
    </div>
    <div class="stat-card">
      <div class="stat-num"><?= number_format($total_bookings) ?></div>
      <div class="stat-label">All Bookings</div>
    </div>
    <div class="stat-card danger">
      <div class="stat-num"><?= number_format($pending_bookings) ?></div>
      <div class="stat-label">Pending</div>
    </div>
    <div class="stat-card success">
      <div class="stat-num"><?= number_format($active_members) ?></div>
      <div class="stat-label">Active Members</div>
    </div>
    <div class="stat-card">
      <div class="stat-num"><?= number_format($total_coaches) ?></div>
      <div class="stat-label">Coaches</div>
    </div>
    <div class="stat-card">
      <div class="stat-num"><?= number_format($total_admins) ?></div>
      <div class="stat-label">Admins</div>
    </div>
  </div>

  <!-- Panel navigation -->
  <p class="panels-title">Control Panels</p>
  <div class="panels-grid">

    <!-- Panel 1 — Users & Database -->
    <a class="panel-card" href="database.php"
       style="--panel-accent:linear-gradient(90deg,#2a8cc4,#00c8ff);
              --panel-border:rgba(0,200,255,.4);
              --panel-glow:rgba(0,200,255,.12);
              --panel-icon-bg:rgba(0,200,255,.12);
              --panel-icon-border:rgba(0,200,255,.25);
              --panel-tag-bg:rgba(0,200,255,.1);
              --panel-tag-border:rgba(0,200,255,.22);
              --panel-tag-color:#00c8ff;
              --panel-cta-color:#00c8ff;">
      <div class="panel-icon">👥</div>
      <div>
        <div class="panel-heading">Users & Database</div>
        <div class="panel-sub">View and manage all registered members. See enrollment history, membership status, program participation, payment records, and account details.</div>
      </div>
      <div class="panel-tags">
        <span class="panel-tag">Member Profiles</span>
        <span class="panel-tag">Bookings</span>
        <span class="panel-tag">Memberships</span>
        <span class="panel-tag">Payments</span>
      </div>
      <div class="panel-cta">Open Panel <span class="arrow">→</span></div>
    </a>

    <!-- Panel 2 — Coaches -->
    <a class="panel-card" href="coach.php"
       style="--panel-accent:linear-gradient(90deg,#00e5b0,#00b38a);
              --panel-border:rgba(0,229,176,.4);
              --panel-glow:rgba(0,229,176,.12);
              --panel-icon-bg:rgba(0,229,176,.12);
              --panel-icon-border:rgba(0,229,176,.25);
              --panel-tag-bg:rgba(0,229,176,.1);
              --panel-tag-border:rgba(0,229,176,.22);
              --panel-tag-color:#00e5b0;
              --panel-cta-color:#00e5b0;">
      <div class="panel-icon">🏊</div>
      <div>
        <div class="panel-heading">Coaching Staff</div>
        <div class="panel-sub">View all coaches, their assigned programs, swimmer rosters, and attendance logs. Promote or manage staff roles directly from this panel.</div>
      </div>
      <div class="panel-tags">
        <span class="panel-tag">Coach Profiles</span>
        <span class="panel-tag">Attendance</span>
        <span class="panel-tag">Assignments</span>
        <span class="panel-tag">Rosters</span>
      </div>
      <div class="panel-cta">Open Panel <span class="arrow">→</span></div>
    </a>

    <!-- Panel 3 — Broadcast (placeholder) -->
    <div class="panel-card disabled"
       style="--panel-accent:linear-gradient(90deg,#ffc847,#ff7c3a);
              --panel-border:rgba(255,200,71,.4);
              --panel-glow:rgba(255,200,71,.12);
              --panel-icon-bg:rgba(255,200,71,.12);
              --panel-icon-border:rgba(255,200,71,.25);
              --panel-tag-bg:rgba(255,200,71,.1);
              --panel-tag-border:rgba(255,200,71,.22);
              --panel-tag-color:#ffc847;
              --panel-cta-color:#ffc847;">
      <div class="coming-soon-tag">Coming Soon</div>
      <div class="panel-icon">📢</div>
      <div>
        <div class="panel-heading">Broadcast System</div>
        <div class="panel-sub">Club-wide announcements, targeted notifications to specific squads, and event alerts. Push comms to members, coaches, or the entire club.</div>
      </div>
      <div class="panel-tags">
        <span class="panel-tag">Announcements</span>
        <span class="panel-tag">Notifications</span>
        <span class="panel-tag">Events</span>
      </div>
      <div class="panel-cta">Coming Soon <span class="arrow">→</span></div>
    </div>

  </div>

  <!-- Recent activity -->
  <div class="section-head">
    <h2>Recent Enrollments</h2>
    <div class="line"></div>
    <a href="database.php">View all →</a>
  </div>

  <div class="activity-table">
    <table>
      <thead>
        <tr>
          <th>Reference</th>
          <th>Program</th>
          <th>Swimmer</th>
          <th>Booked By</th>
          <th>Status</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($recent)): ?>
        <tr><td colspan="6" style="text-align:center;color:rgba(184,221,245,.35);padding:2rem;">No bookings yet.</td></tr>
        <?php else: foreach ($recent as $r): ?>
        <tr>
          <td style="font-family:'Cinzel',serif;font-size:.72rem;letter-spacing:.08em;color:rgba(184,221,245,.5);">
            <?= htmlspecialchars($r['booking_reference']) ?>
          </td>
          <td><span class="prog-badge"><?= htmlspecialchars(ucwords(str_replace('_',' ',$r['program']))) ?></span></td>
          <td><?= htmlspecialchars($r['swimmer_name']) ?></td>
          <td><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></td>
          <td><span class="status-dot <?= htmlspecialchars($r['status']) ?>"><?= ucfirst($r['status']) ?></span></td>
          <td style="color:rgba(184,221,245,.45);font-size:.78rem;"><?= date('d M Y', strtotime($r['created_at'])) ?></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Quick actions -->
  <div class="section-head" style="margin-top:2rem;">
    <h2>Quick Actions</h2>
    <div class="line"></div>
  </div>
  <div class="quick-grid">
    <a href="pending.php?filter=pending" class="quick-btn"><span class="qicon">⏳</span>Pending Bookings</a>
    <a href="m_pending.php?filter=membership" class="quick-btn"><span class="qicon">🏅</span>Memberships</a>
    <a href="coach.php" class="quick-btn"><span class="qicon">🏊</span>Manage Coaches</a>
    <a href="index.php" class="quick-btn"><span class="qicon">🏠</span>Public Dashboard</a>
  </div>

</div>

<script>
function tick() {
  const now = new Date();
  document.getElementById('admin-clock').textContent =
    now.toLocaleTimeString('en-IN', {hour:'2-digit',minute:'2-digit'});
  document.getElementById('admin-date').textContent =
    now.toLocaleDateString('en-IN', {weekday:'long',day:'numeric',month:'long',year:'numeric'});
}
tick();
setInterval(tick, 1000);
</script>
</body>
</html>