<?php
// profile.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config/db.php';

$user_id = (int)$_SESSION['user_id'];

// ── Fetch user basic info ─────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT id, first_name, middle_name, last_name, phone, email, role, created_at
    FROM users
    WHERE id = ? AND is_active = 1
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$full_name = trim($user['first_name'] . ' ' . ($user['middle_name'] ? $user['middle_name'] . ' ' : '') . $user['last_name']);
$member_since = date('F Y', strtotime($user['created_at']));

// ── Fetch all bookings (programs enrolled) ────────────────────────────────────
$stmt2 = $pdo->prepare("
    SELECT
        b.id,
        b.booking_reference,
        b.program,
        b.swimmer_name,
        b.swimmer_relation,
        b.is_for_self,
        b.payment_status,
        b.payment_amount,
        b.payment_method,
        b.status,
        b.created_at,
        b.start_date,
        b.end_date
    FROM bookings b
    WHERE b.booked_by_user_id = ?
      AND b.program != 'membership'
    ORDER BY b.created_at DESC
");
$stmt2->execute([$user_id]);
$bookings = $stmt2->fetchAll();

// ── Fetch membership ──────────────────────────────────────────────────────────
$stmt3 = $pdo->prepare("
    SELECT
        m.id,
        m.plan,
        m.full_name,
        m.start_date,
        m.end_date,
        m.duration_months,
        m.auto_renew,
        m.payment_amount,
        m.payment_method,
        m.payment_reference,
        m.status,
        m.created_at
    FROM memberships m
    WHERE m.user_id = ?
    ORDER BY m.created_at DESC
    LIMIT 1
");
$stmt3->execute([$user_id]);
$membership = $stmt3->fetch();

// ── Helpers ───────────────────────────────────────────────────────────────────
$program_labels = [
    'junior_development'  => 'Junior Development',
    'competitive_squad'   => 'Competitive Squad',
    'elite_coaching'      => 'Elite Coaching',
    'adult_fitness_swim'  => 'Adult Fitness Swim',
    'mental_conditioning' => 'Mental Conditioning',
    'masters_program'     => 'Masters Program',
];
$program_icons = [
    'junior_development'  => '🌊',
    'competitive_squad'   => '🏊',
    'elite_coaching'      => '🎯',
    'adult_fitness_swim'  => '💪',
    'mental_conditioning' => '🧠',
    'masters_program'     => '🥇',
];
$program_colors = [
    'junior_development'  => ['#00c8ff', 'rgba(0,200,255,.12)', 'rgba(0,200,255,.3)'],
    'competitive_squad'   => ['#ffc847', 'rgba(255,200,71,.12)', 'rgba(255,200,71,.3)'],
    'elite_coaching'      => ['#c8a8f8', 'rgba(200,168,248,.12)', 'rgba(200,168,248,.3)'],
    'adult_fitness_swim'  => ['#00e5b0', 'rgba(0,229,176,.12)', 'rgba(0,229,176,.3)'],
    'mental_conditioning' => ['#b57bee', 'rgba(181,123,238,.12)', 'rgba(181,123,238,.3)'],
    'masters_program'     => ['#e8b84b', 'rgba(232,184,75,.12)', 'rgba(232,184,75,.3)'],
];
$membership_colors = [
    'bronze'   => ['#cd7f32', 'rgba(205,127,50,.15)', '🥉'],
    'silver'   => ['#a8a9ad', 'rgba(168,169,173,.15)', '🥈'],
    'gold'     => ['#d4af37', 'rgba(212,175,55,.15)',  '🥇'],
    'platinum' => ['#e5e4e2', 'rgba(229,228,226,.12)', '💎'],
];

function status_pill(string $status): string {
    $map = [
        'pending'   => ['#ffc847', 'rgba(255,200,71,.15)',  'Pending'],
        'confirmed' => ['#00c8ff', 'rgba(0,200,255,.15)',   'Confirmed'],
        'active'    => ['#00e5b0', 'rgba(0,229,176,.15)',   'Active'],
        'paused'    => ['#b57bee', 'rgba(181,123,238,.15)', 'Paused'],
        'completed' => ['#6db8e8', 'rgba(109,184,232,.15)', 'Completed'],
        'cancelled' => ['#ff4d6d', 'rgba(255,77,109,.15)',  'Cancelled'],
        'expired'   => ['#ff4d6d', 'rgba(255,77,109,.15)',  'Expired'],
    ];
    [$color, $bg, $label] = $map[$status] ?? ['#a8a9ad', 'rgba(168,169,173,.15)', ucfirst($status)];
    return "<span style=\"display:inline-block;padding:.22rem .75rem;border-radius:100px;font-size:.68rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;background:{$bg};border:1px solid {$color};color:{$color};\">{$label}</span>";
}

function payment_pill(string $status): string {
    $map = [
        'paid'    => ['#00e5b0', 'rgba(0,229,176,.15)',  'Paid'],
        'pending' => ['#ffc847', 'rgba(255,200,71,.15)', 'Pending'],
        'unpaid'  => ['#ff4d6d', 'rgba(255,77,109,.15)', 'Unpaid'],
        'refunded'=> ['#b57bee', 'rgba(181,123,238,.15)','Refunded'],
        'waived'  => ['#6db8e8', 'rgba(109,184,232,.15)','Waived'],
    ];
    [$color, $bg, $label] = $map[$status] ?? ['#a8a9ad', 'rgba(168,169,173,.15)', ucfirst($status)];
    return "<span style=\"display:inline-block;padding:.22rem .75rem;border-radius:100px;font-size:.68rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;background:{$bg};border:1px solid {$color};color:{$color};\">{$label}</span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Profile — Darken Shadows Swimming Club</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700;900&family=Raleway:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
:root {
  --ocean-1:#e8f4fd; --ocean-2:#b8ddf5; --ocean-3:#6db8e8;
  --ocean-4:#2a8cc4; --ocean-5:#1565a0; --ocean-6:#0d3d6b; --ocean-7:#061e35;
  --white:#ffffff;
  --font-display:'Cinzel',serif;
  --font-body:'Raleway',sans-serif;
  --radius:14px; --radius-sm:8px;
  --transition:.25s cubic-bezier(.4,0,.2,1);
  --card-bg:rgba(13,61,107,.4);
  --card-border:rgba(109,184,232,.15);
}

*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html{scroll-behavior:smooth;}

body{
  font-family:var(--font-body);
  background:var(--ocean-7);
  color:var(--ocean-1);
  min-height:100vh;
  overflow-x:hidden;
}

body::before{
  content:'';position:fixed;inset:0;z-index:0;
  background:
    radial-gradient(ellipse 75% 55% at 15% 8%, rgba(42,140,196,.22) 0%, transparent 58%),
    radial-gradient(ellipse 55% 70% at 88% 90%, rgba(21,101,160,.2) 0%, transparent 55%),
    linear-gradient(160deg, var(--ocean-7) 0%, #0a2e47 50%, var(--ocean-7) 100%);
  pointer-events:none;
}
body::after{
  content:'';position:fixed;inset:0;z-index:0;
  background-image:
    linear-gradient(rgba(109,184,232,.03) 1px, transparent 1px),
    linear-gradient(90deg, rgba(109,184,232,.03) 1px, transparent 1px);
  background-size:48px 48px;
  pointer-events:none;
}

/* ── Nav ─────────────────────────────────────────────────────────────────── */
nav{
  position:sticky;top:0;z-index:100;
  display:flex;align-items:center;justify-content:space-between;
  padding:1rem 2.5rem;
  background:rgba(6,30,53,.88);
  backdrop-filter:blur(20px);
  border-bottom:1px solid rgba(109,184,232,.14);
}
.nav-logo{
  font-family:var(--font-display);font-size:1.05rem;font-weight:700;
  letter-spacing:.12em;color:var(--ocean-3);text-decoration:none;text-transform:uppercase;
}
.nav-links{display:flex;gap:1.8rem;align-items:center;}
.nav-links a{
  font-size:.8rem;font-weight:600;letter-spacing:.1em;text-transform:uppercase;
  color:rgba(184,221,245,.6);text-decoration:none;transition:color var(--transition);
}
.nav-links a:hover,.nav-links a.active{color:var(--ocean-2);}
.nav-user{font-size:.8rem;color:rgba(184,221,245,.5);}
.nav-user span{color:var(--ocean-2);font-weight:600;}

/* ── Page layout ──────────────────────────────────────────────────────────── */
.page{position:relative;z-index:1;max-width:1100px;margin:0 auto;padding:3rem 2rem 6rem;}

/* ── Profile header card ──────────────────────────────────────────────────── */
.profile-header{
  background:var(--card-bg);
  border:1px solid var(--card-border);
  border-radius:var(--radius);
  padding:2.5rem 2.5rem 2rem;
  display:grid;
  grid-template-columns:auto 1fr auto;
  gap:2rem;
  align-items:center;
  backdrop-filter:blur(12px);
  margin-bottom:2rem;
  position:relative;
  overflow:hidden;
  opacity:0;
  animation:fadeUp .6s .1s ease forwards;
}
.profile-header::before{
  content:'';position:absolute;top:0;left:0;right:0;height:3px;
  background:linear-gradient(90deg, var(--ocean-5), var(--ocean-3), var(--ocean-4));
}

.avatar{
  width:80px;height:80px;border-radius:50%;
  background:linear-gradient(135deg, var(--ocean-5), var(--ocean-4), var(--ocean-3));
  display:flex;align-items:center;justify-content:center;
  font-family:var(--font-display);font-size:1.8rem;font-weight:700;
  color:var(--white);flex-shrink:0;
  box-shadow:0 0 0 3px rgba(109,184,232,.25), 0 8px 24px rgba(0,0,0,.3);
}

.profile-info{}
.profile-name{
  font-family:var(--font-display);
  font-size:clamp(1.3rem,2.5vw,1.8rem);
  font-weight:700;color:var(--white);
  letter-spacing:.04em;margin-bottom:.35rem;
}
.profile-meta{
  display:flex;flex-wrap:wrap;gap:.5rem 1.4rem;
  font-size:.82rem;font-weight:400;color:var(--ocean-2);
}
.profile-meta span{display:flex;align-items:center;gap:.4rem;}
.profile-meta .dot{width:4px;height:4px;border-radius:50%;background:rgba(109,184,232,.4);}

.role-badge{
  align-self:flex-start;
  padding:.3rem 1rem;border-radius:100px;
  font-size:.68rem;font-weight:700;letter-spacing:.15em;text-transform:uppercase;
  border:1px solid rgba(109,184,232,.35);
  background:rgba(109,184,232,.1);
  color:var(--ocean-3);white-space:nowrap;
}
.role-badge.admin{border-color:rgba(255,200,71,.4);background:rgba(255,200,71,.1);color:#ffc847;}
.role-badge.coach{border-color:rgba(0,229,176,.4);background:rgba(0,229,176,.1);color:#00e5b0;}

/* ── Stats row ────────────────────────────────────────────────────────────── */
.stats-row{
  display:grid;grid-template-columns:repeat(4,1fr);
  gap:1rem;margin-bottom:2rem;
  opacity:0;animation:fadeUp .6s .2s ease forwards;
}
.stat-card{
  background:var(--card-bg);
  border:1px solid var(--card-border);
  border-radius:var(--radius-sm);
  padding:1.3rem 1.2rem;
  text-align:center;
  backdrop-filter:blur(10px);
  transition:border-color var(--transition),transform var(--transition);
}
.stat-card:hover{border-color:rgba(109,184,232,.35);transform:translateY(-3px);}
.stat-num{
  font-family:var(--font-display);font-size:2rem;font-weight:700;
  color:var(--ocean-3);line-height:1;margin-bottom:.3rem;
}
.stat-label{font-size:.7rem;font-weight:600;letter-spacing:.14em;text-transform:uppercase;color:rgba(184,221,245,.5);}

/* ── Section titles ───────────────────────────────────────────────────────── */
.section-head{
  display:flex;align-items:center;gap:.8rem;
  margin-bottom:1.2rem;margin-top:2.5rem;
}
.section-head h2{
  font-family:var(--font-display);font-size:1rem;font-weight:600;
  color:var(--ocean-2);letter-spacing:.12em;text-transform:uppercase;
}
.section-head .line{flex:1;height:1px;background:linear-gradient(90deg,rgba(109,184,232,.2),transparent);}

/* ── Membership card ──────────────────────────────────────────────────────── */
.membership-card{
  background:var(--card-bg);
  border:1px solid var(--card-border);
  border-radius:var(--radius);
  padding:1.8rem 2rem;
  backdrop-filter:blur(10px);
  display:grid;
  grid-template-columns:auto 1fr auto;
  gap:1.5rem;align-items:center;
  opacity:0;animation:fadeUp .6s .3s ease forwards;
  position:relative;overflow:hidden;
  transition:border-color var(--transition),box-shadow var(--transition);
}
.membership-card:hover{border-color:rgba(109,184,232,.3);box-shadow:0 12px 40px rgba(0,0,0,.3);}
.mem-tier-icon{font-size:3rem;line-height:1;}
.mem-info{}
.mem-plan{
  font-family:var(--font-display);font-size:1.2rem;font-weight:700;
  color:var(--white);letter-spacing:.06em;margin-bottom:.4rem;
}
.mem-details{
  display:flex;flex-wrap:wrap;gap:.4rem 1.2rem;
  font-size:.8rem;font-weight:400;color:var(--ocean-2);
}
.mem-details span{display:flex;align-items:center;gap:.35rem;}
.mem-right{text-align:right;}
.mem-amount{
  font-family:var(--font-display);font-size:1.4rem;font-weight:700;
  color:var(--ocean-3);margin-bottom:.5rem;
}

.no-membership{
  background:var(--card-bg);
  border:1px dashed rgba(109,184,232,.2);
  border-radius:var(--radius);
  padding:2.5rem;text-align:center;
  opacity:0;animation:fadeUp .6s .3s ease forwards;
}
.no-membership p{color:rgba(184,221,245,.55);font-size:.9rem;font-weight:300;margin-bottom:1.2rem;}

/* ── Program booking cards ────────────────────────────────────────────────── */
.bookings-grid{
  display:flex;flex-direction:column;gap:1rem;
}

.booking-card{
  background:var(--card-bg);
  border:1px solid var(--card-border);
  border-radius:var(--radius);
  padding:1.5rem 1.8rem;
  backdrop-filter:blur(10px);
  display:grid;
  grid-template-columns:auto 1fr auto;
  gap:1.2rem;align-items:center;
  opacity:0;
  animation:fadeUp .55s ease forwards;
  transition:border-color var(--transition),transform var(--transition),box-shadow var(--transition);
  position:relative;overflow:hidden;
}
.booking-card:hover{
  border-color:rgba(109,184,232,.3);
  transform:translateX(4px);
  box-shadow:0 8px 32px rgba(0,0,0,.3);
}
.booking-card::before{
  content:'';position:absolute;left:0;top:0;bottom:0;width:3px;
  background:var(--prog-color, var(--ocean-4));
  border-radius:var(--radius) 0 0 var(--radius);
}

.prog-icon-wrap{
  width:52px;height:52px;border-radius:50%;
  display:flex;align-items:center;justify-content:center;
  font-size:1.4rem;flex-shrink:0;
  background:var(--prog-bg, rgba(42,140,196,.15));
  border:1px solid var(--prog-border, rgba(42,140,196,.3));
}

.booking-info{}
.booking-program{
  font-family:var(--font-display);font-size:.95rem;font-weight:600;
  color:var(--white);letter-spacing:.05em;margin-bottom:.3rem;
}
.booking-sub{
  display:flex;flex-wrap:wrap;gap:.3rem 1rem;
  font-size:.78rem;font-weight:400;color:var(--ocean-2);
}
.booking-sub span{display:flex;align-items:center;gap:.3rem;}

.booking-right{
  display:flex;flex-direction:column;align-items:flex-end;gap:.5rem;
}
.booking-ref{
  font-family:var(--font-display);font-size:.7rem;
  letter-spacing:.1em;color:rgba(184,221,245,.35);
}
.booking-amount{
  font-size:.88rem;font-weight:600;color:var(--ocean-2);
}

.no-bookings{
  background:var(--card-bg);
  border:1px dashed rgba(109,184,232,.2);
  border-radius:var(--radius);
  padding:2.5rem;text-align:center;
  opacity:0;animation:fadeUp .6s .4s ease forwards;
}
.no-bookings p{color:rgba(184,221,245,.55);font-size:.9rem;font-weight:300;margin-bottom:1.2rem;}

/* ── User info section ────────────────────────────────────────────────────── */
.info-grid{
  display:grid;grid-template-columns:1fr 1fr;gap:1rem;
  opacity:0;animation:fadeUp .6s .35s ease forwards;
}
.info-card{
  background:var(--card-bg);
  border:1px solid var(--card-border);
  border-radius:var(--radius-sm);
  padding:1.3rem 1.4rem;
  backdrop-filter:blur(10px);
}
.info-label{
  font-size:.65rem;font-weight:700;letter-spacing:.18em;
  text-transform:uppercase;color:rgba(184,221,245,.4);
  margin-bottom:.4rem;
}
.info-value{
  font-size:.95rem;font-weight:500;color:var(--ocean-1);
}
.info-value.empty{color:rgba(184,221,245,.3);font-style:italic;font-size:.85rem;}

/* ── Buttons ──────────────────────────────────────────────────────────────── */
.btn{
  display:inline-flex;align-items:center;gap:.5rem;
  padding:.6rem 1.4rem;border-radius:100px;
  font-family:var(--font-body);font-size:.8rem;font-weight:600;
  letter-spacing:.08em;text-decoration:none;
  transition:all var(--transition);border:none;cursor:pointer;text-transform:uppercase;
}
.btn-primary{
  background:linear-gradient(135deg,var(--ocean-5),var(--ocean-4));
  color:var(--white);
  box-shadow:0 4px 16px rgba(42,140,196,.3);
}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(42,140,196,.45);}
.btn-outline{
  background:transparent;
  border:1px solid rgba(109,184,232,.35);
  color:var(--ocean-3);
}
.btn-outline:hover{background:rgba(109,184,232,.08);}

/* ── Animations ───────────────────────────────────────────────────────────── */
@keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}

/* ── Responsive ───────────────────────────────────────────────────────────── */
@media(max-width:768px){
  nav{padding:1rem 1.2rem;}
  .nav-links{gap:1rem;}
  .page{padding:2rem 1rem 4rem;}
  .profile-header{grid-template-columns:auto 1fr;gap:1rem;}
  .role-badge{grid-column:span 2;justify-self:start;}
  .stats-row{grid-template-columns:repeat(2,1fr);}
  .membership-card{grid-template-columns:auto 1fr;gap:1rem;}
  .mem-right{display:none;}
  .booking-card{grid-template-columns:auto 1fr;gap:1rem;}
  .booking-right{display:none;}
  .info-grid{grid-template-columns:1fr;}
}
@media(max-width:480px){
  .stats-row{grid-template-columns:1fr 1fr;}
  .nav-links a:not(:last-child){display:none;}
}
</style>
</head>
<body>

<!-- ── Nav ─────────────────────────────────────────────────────────────────── -->
<nav>
  <a href="index.php" class="nav-logo">Darken Shadows SC</a>
  <div class="nav-links">
    <a href="index.php">Dashboard</a>
    <a href="program.php">Programs</a>
    <a href="Facilities.php">Facilities</a>
    <a href="membership.php">Membership</a>
    <a href="profile.php" class="active">My Profile</a>
    <?php if (($user['role'] ?? '') === 'admin'): ?>
      <a href="admin.php" style="background:linear-gradient(135deg,rgba(255,200,71,.2),rgba(255,124,58,.15));border:1px solid rgba(255,200,71,.4);color:#ffc847;padding:.35rem 1rem;border-radius:100px;">⚙ Admin Panel</a>
    <?php endif; ?>
    <?php if (($user['role'] ?? '') === 'coach'): ?>
      <a href="coach.php" style="background:linear-gradient(135deg,rgba(255,200,71,.2),rgba(255,124,58,.15));border:1px solid rgba(255,200,71,.4);color:#ffc847;padding:.35rem 1rem;border-radius:100px;">⚙ Coach</a>
    <?php endif; ?>
    <a href="logout.php">Logout</a>
  </div>
  <div class="nav-user">Welcome, <span><?= htmlspecialchars($user['first_name']) ?></span></div>
</nav>

<!-- ── Page content ────────────────────────────────────────────────────────── -->
<div class="page">

  <!-- Profile Header -->
  <div class="profile-header">
    <div class="avatar"><?= strtoupper(mb_substr($user['first_name'], 0, 1) . mb_substr($user['last_name'], 0, 1)) ?></div>

    <div class="profile-info">
      <div class="profile-name"><?= htmlspecialchars($full_name) ?></div>
      <div class="profile-meta">
        <?php if ($user['email']): ?>
          <span>✉ <?= htmlspecialchars($user['email']) ?></span>
          <span class="dot"></span>
        <?php endif; ?>
        <span>📞 <?= htmlspecialchars($user['phone']) ?></span>
        <span class="dot"></span>
        <span>📅 Member since <?= $member_since ?></span>
      </div>
    </div>

    <div class="role-badge <?= htmlspecialchars($user['role'] ?? 'user') ?>">
      <?php
        $role = $user['role'] ?? 'user';
        $role_icons = ['admin' => '⚙ Admin', 'coach' => '🏊 Coach', 'user' => '👤 Member'];
        echo $role_icons[$role] ?? '👤 Member';
      ?>
    </div>
  </div>

  
  <!-- Stats Row -->
  <?php
    $total_bookings   = count($bookings);
    $active_bookings  = count(array_filter($bookings, fn($b) => in_array($b['status'], ['active','confirmed'])));
    $pending_bookings = count(array_filter($bookings, fn($b) => $b['status'] === 'pending'));
    $mem_status_label = $membership ? ucfirst($membership['status']) : 'None';
  ?>
  <div class="stats-row">
    <div class="stat-card">
      <div class="stat-num"><?= $total_bookings ?></div>
      <div class="stat-label">Total Enrollments</div>
    </div>
    <div class="stat-card">
      <div class="stat-num"><?= $active_bookings ?></div>
      <div class="stat-label">Active Programs</div>
    </div>
    <div class="stat-card">
      <div class="stat-num"><?= $pending_bookings ?></div>
      <div class="stat-label">Pending Review</div>
    </div>
    <div class="stat-card">
      <div class="stat-num" style="font-size:1.1rem;"><?= $membership ? ucfirst($membership['plan']) : '—' ?></div>
      <div class="stat-label">Membership Plan</div>
    </div>
  </div>

  <!-- Account Details -->
  <div class="section-head">
    <h2>Account Details</h2>
    <div class="line"></div>
  </div>
  <div class="info-grid">
    <div class="info-card">
      <div class="info-label">First Name</div>
      <div class="info-value"><?= htmlspecialchars($user['first_name']) ?></div>
    </div>
    <?php if ($user['middle_name']): ?>
    <div class="info-card">
      <div class="info-label">Middle Name</div>
      <div class="info-value"><?= htmlspecialchars($user['middle_name']) ?></div>
    </div>
    <?php endif; ?>
    <div class="info-card">
      <div class="info-label">Last Name</div>
      <div class="info-value"><?= htmlspecialchars($user['last_name']) ?></div>
    </div>
    <div class="info-card">
      <div class="info-label">Phone Number</div>
      <div class="info-value"><?= htmlspecialchars($user['phone']) ?></div>
    </div>
    <div class="info-card">
      <div class="info-label">Email Address</div>
      <div class="info-value <?= $user['email'] ? '' : 'empty' ?>">
        <?= $user['email'] ? htmlspecialchars($user['email']) : 'Not provided' ?>
      </div>
    </div>
    <div class="info-card">
      <div class="info-label">Member Since</div>
      <div class="info-value"><?= $member_since ?></div>
    </div>
    <div class="info-card">
      <div class="info-label">Account Role</div>
      <div class="info-value"><?= ucfirst($user['role'] ?? 'user') ?></div>
    </div>
    <div class="info-card">
      <div class="info-label">Account Status</div>
      <div class="info-value" style="color:#00e5b0;">Active</div>
    </div>
  </div>

  <!-- Membership -->
  <div class="section-head">
    <h2>Membership</h2>
    <div class="line"></div>
    <?php if (!$membership): ?>
      <a href="membership.php" class="btn btn-primary" style="font-size:.7rem;padding:.45rem 1rem;">+ Get Membership</a>
    <?php endif; ?>
  </div>

  <?php if ($membership):
    $mc = $membership_colors[$membership['plan']] ?? ['#6db8e8', 'rgba(109,184,232,.15)', '🏅'];
    [$m_color, $m_bg, $m_icon] = $mc;
  ?>
  <div class="membership-card" style="border-color:<?= $m_color ?>33;">
    <div class="mem-tier-icon"><?= $m_icon ?></div>
    <div class="mem-info">
      <div class="mem-plan"><?= ucfirst(htmlspecialchars($membership['plan'])) ?> Membership</div>
      <div class="mem-details">
        <span>📅 Started <?= date('d M Y', strtotime($membership['start_date'])) ?></span>
        <span>⏳ Expires <?= date('d M Y', strtotime($membership['end_date'])) ?></span>
        <span>📆 <?= $membership['duration_months'] ?> month<?= $membership['duration_months'] > 1 ? 's' : '' ?></span>
        <?php if ($membership['auto_renew']): ?>
          <span style="color:#00e5b0;">🔄 Auto-renew on</span>
        <?php endif; ?>
      </div>
      <div style="margin-top:.6rem;">
        <?= status_pill($membership['status']) ?>
        &nbsp;
        <?= payment_pill('paid') ?>
      </div>
    </div>
    <div class="mem-right">
      <div class="mem-amount" style="color:<?= $m_color ?>;">
        ₹<?= number_format($membership['payment_amount']) ?>
      </div>
      <a href="membership.php" class="btn btn-outline" style="font-size:.7rem;padding:.4rem .9rem;">Manage</a>
    </div>
  </div>

  <?php else: ?>
  <div class="no-membership">
    <p>You don't have an active membership yet. Join a plan to unlock full pool access and coaching benefits.</p>
    <a href="membership.php" class="btn btn-primary">View Membership Plans →</a>
  </div>
  <?php endif; ?>

  <!-- Program Enrollments -->
  <div class="section-head">
    <h2>Program Enrollments</h2>
    <div class="line"></div>
    <a href="program.php" class="btn btn-outline" style="font-size:.7rem;padding:.45rem 1rem;">Browse Programs</a>
  </div>

  <?php if (!empty($bookings)): ?>
  <div class="bookings-grid">
    <?php foreach ($bookings as $i => $b):
      $prog    = $b['program'];
      $pcolors = $program_colors[$prog] ?? ['#6db8e8', 'rgba(109,184,232,.12)', 'rgba(109,184,232,.3)'];
      [$p_color, $p_bg, $p_border] = $pcolors;
      $p_icon  = $program_icons[$prog] ?? '🏊';
      $p_label = $program_labels[$prog] ?? ucwords(str_replace('_', ' ', $prog));
      $delay   = 0.3 + ($i * 0.07);
    ?>
    <div class="booking-card"
         style="--prog-color:<?= $p_color ?>;--prog-bg:<?= $p_bg ?>;--prog-border:<?= $p_border ?>;animation-delay:<?= $delay ?>s;">
      <div class="prog-icon-wrap"><?= $p_icon ?></div>

      <div class="booking-info">
        <div class="booking-program"><?= htmlspecialchars($p_label) ?></div>
        <div class="booking-sub">
          <span>👤 <?= htmlspecialchars($b['swimmer_name']) ?></span>
          <?php if (!$b['is_for_self']): ?>
            <span style="color:rgba(184,221,245,.45);">(<?= htmlspecialchars($b['swimmer_relation']) ?>)</span>
          <?php endif; ?>
          <span>📅 <?= date('d M Y', strtotime($b['created_at'])) ?></span>
          <?php if ($b['payment_method']): ?>
            <span>💳 <?= ucwords(str_replace('_', ' ', $b['payment_method'])) ?></span>
          <?php endif; ?>
        </div>
        <div style="margin-top:.5rem;display:flex;gap:.4rem;flex-wrap:wrap;">
          <?= status_pill($b['status']) ?>
          <?= payment_pill($b['payment_status']) ?>
        </div>
      </div>

      <div class="booking-right">
        <div class="booking-ref"><?= htmlspecialchars($b['booking_reference']) ?></div>
        <?php if ($b['payment_amount']): ?>
          <div class="booking-amount">₹<?= number_format($b['payment_amount']) ?></div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php else: ?>
  <div class="no-bookings">
    <p>You haven't enrolled in any programs yet. Explore what Darken Shadows has to offer.</p>
    <a href="program.php" class="btn btn-primary">Explore Programs →</a>
  </div>
  <?php endif; ?>

</div><!-- /page -->

</body>
</html>