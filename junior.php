<?php
// Program_junior_development.php
session_start();
require_once 'config/db.php';

$errors      = [];
$success     = false;
$booking_ref = '';

// ── Helpers ───────────────────────────────────────────────────────────────────
function age_from_dob(string $dob): int {
    return (int)(new DateTime())->diff(new DateTime($dob))->y;
}

// ── POST handler ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php?redirect=Program_junior_development.php');
        exit;
    }

    $user_id     = (int)$_SESSION['user_id'];
    $is_for_self = (int)($_POST['is_for_self'] ?? 1);

    // ── Swimmer details — always from the form ────────────────────────────
    $swimmer_name      = trim($_POST['swimmer_name']      ?? '');
    $swimmer_dob       = trim($_POST['swimmer_dob']       ?? '');
    $swimmer_email     = trim($_POST['swimmer_email']     ?? '');
    $swimmer_phone     = trim($_POST['swimmer_phone']     ?? '');
    $emergency_contact = trim($_POST['emergency_contact'] ?? '');
    $medical_notes     = trim($_POST['medical_notes']     ?? '');
    $relation          = $is_for_self === 1 ? 'self' : trim($_POST['relation'] ?? '');

    // Validation
    if (!$swimmer_name)      $errors[] = "Swimmer's full name is required.";
    if (!$swimmer_dob)       $errors[] = "Swimmer's date of birth is required.";
    if ($is_for_self === 0 && !$relation)
                             $errors[] = "Your relationship to the swimmer is required.";
    if (!$emergency_contact) $errors[] = "Emergency contact is required.";

    // ── Age gate (6–14) ───────────────────────────────────────────────────
    if (empty($errors) && $swimmer_dob) {
        $age = age_from_dob($swimmer_dob);
        if ($age < 6 || $age > 14) {
            $errors[] = "Junior Development is for ages 6–14. The swimmer is {$age} years old.";
        }
    }

    // ── Program-specific fields ───────────────────────────────────────────
    $skill_level   = trim($_POST['skill_level']   ?? '');
    $sessions_week = max(1, min(7, (int)($_POST['sessions_week'] ?? 3)));
    $strokes_raw   = $_POST['strokes'] ?? [];
    $goals         = trim($_POST['goals'] ?? '');

    $allowed_strokes = ['freestyle', 'backstroke', 'breaststroke', 'butterfly'];
    $strokes = array_values(array_filter($strokes_raw, fn($s) => in_array($s, $allowed_strokes)));

    if (!$skill_level)   $errors[] = "Please select a skill level.";
    if (empty($strokes)) $errors[] = "Please select at least one stroke to focus on.";

    // ── Payment ───────────────────────────────────────────────────────────
    $payment_method    = trim($_POST['payment_method']    ?? '');
    $payment_amount    = 150.00;
    $payment_reference = trim($_POST['payment_reference'] ?? '');

    if (!$payment_method) $errors[] = "Payment method is required.";

    // ── Insert ────────────────────────────────────────────────────────────
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // 1 — program record
            $stmt = $pdo->prepare("
                INSERT INTO prog_junior_development
                    (user_id, is_for_self, relation,
                     swimmer_full_name, swimmer_dob, swimmer_email, swimmer_phone,
                     emergency_contact, medical_notes,
                     skill_level, sessions_per_week, strokes, goals,
                     created_at)
                VALUES
                    (?, ?, ?,
                     ?, ?, ?, ?,
                     ?, ?,
                     ?, ?, ?, ?,
                     NOW())
            ");
            $stmt->execute([
                $user_id, $is_for_self, $relation ?: null,
                $swimmer_name, $swimmer_dob, $swimmer_email ?: null, $swimmer_phone ?: null,
                $emergency_contact, $medical_notes ?: null,
                $skill_level, $sessions_week, implode(',', $strokes), $goals ?: null,
            ]);
            $program_record_id = (int)$pdo->lastInsertId();

            // 2 — master bookings row
            $stmt2 = $pdo->prepare("
                INSERT INTO bookings
                    (booked_by_user_id, is_for_self,
                     swimmer_name, swimmer_dob, swimmer_relation,
                     swimmer_phone, swimmer_email,
                     swimmer_emergency_contact, swimmer_medical_notes,
                     program, program_record_id,
                     payment_amount, payment_method, payment_reference, payment_status,
                     status, created_at)
                VALUES
                    (?, ?,
                     ?, ?, ?,
                     ?, ?,
                     ?, ?,
                     'junior_development', ?,
                     ?, ?, ?, 'pending',
                     'pending', NOW())
            ");
            $stmt2->execute([
                $user_id, $is_for_self,
                $swimmer_name, $swimmer_dob, $relation ?: 'self',
                $swimmer_phone ?: null, $swimmer_email ?: null,
                $emergency_contact, $medical_notes ?: null,
                $program_record_id,
                $payment_amount, $payment_method, $payment_reference ?: null,
            ]);

            $booking_id = (int)$pdo->lastInsertId();
            $ref = $pdo->prepare("SELECT booking_reference FROM bookings WHERE id = ?");
            $ref->execute([$booking_id]);
            $booking_ref = $ref->fetchColumn();

            $pdo->commit();
            $success = true;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Repopulate form on error
$p = $_POST;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Junior Development — Darken Shadows Swimming Club</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700;900&family=Raleway:ital,wght@0,300;0,400;0,500;0,600;1,300&display=swap" rel="stylesheet">
<style>
:root {
  --ocean-1: #e8f4fd; --ocean-2: #b8ddf5; --ocean-3: #6db8e8;
  --ocean-4: #2a8cc4; --ocean-5: #1565a0; --ocean-6: #0d3d6b; --ocean-7: #061e35;
  --accent: #00c8ff; --accent-2: #f0a500;
  --white: #ffffff; --error: #ff4d6d; --success: #00d68f;
  --font-display: 'Cinzel', serif; --font-body: 'Raleway', sans-serif;
  --radius: 12px; --radius-sm: 6px;
  --shadow: 0 8px 32px rgba(6,30,53,.45);
  --transition: .25s cubic-bezier(.4,0,.2,1);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }

body {
  font-family: var(--font-body);
  background: var(--ocean-7);
  color: var(--ocean-1);
  min-height: 100vh;
  overflow-x: hidden;
}

body::before {
  content: '';
  position: fixed; inset: 0;
  background:
    radial-gradient(ellipse 80% 60% at 20% 10%, rgba(42,140,196,.22) 0%, transparent 60%),
    radial-gradient(ellipse 60% 80% at 80% 90%, rgba(0,200,255,.12) 0%, transparent 55%),
    radial-gradient(ellipse 100% 100% at 50% 50%, var(--ocean-7) 40%, var(--ocean-6) 100%);
  z-index: 0; pointer-events: none;
}

/* ── Nav ─────────────────────────────────────────────────────────────────── */
nav {
  position: sticky; top: 0; z-index: 100;
  display: flex; align-items: center; justify-content: space-between;
  padding: 1rem 2.5rem;
  background: rgba(6,30,53,.82);
  backdrop-filter: blur(18px);
  border-bottom: 1px solid rgba(0,200,255,.15);
}
.nav-logo {
  font-family: var(--font-display); font-size: 1.1rem; font-weight: 700;
  letter-spacing: .12em; color: var(--accent); text-decoration: none; text-transform: uppercase;
}
.nav-links { display: flex; gap: 1.8rem; align-items: center; }
.nav-links a {
  font-size: .82rem; font-weight: 600; letter-spacing: .08em;
  color: var(--ocean-2); text-decoration: none; text-transform: uppercase;
  transition: color var(--transition);
}
.nav-links a:hover { color: var(--accent); }
.nav-cta {
  background: linear-gradient(135deg, var(--ocean-4), var(--accent));
  color: var(--white) !important;
  padding: .45rem 1.2rem; border-radius: 100px; font-size: .78rem !important;
}

/* ── Hero ────────────────────────────────────────────────────────────────── */
.hero {
  position: relative; z-index: 1;
  display: grid; grid-template-columns: 1fr 1fr;
  min-height: 520px; overflow: hidden;
}
.hero-content {
  padding: 5rem 3rem 4rem 5vw;
  display: flex; flex-direction: column; justify-content: center; gap: 1.4rem;
}
.hero-badge {
  display: inline-flex; align-items: center; gap: .55rem;
  background: rgba(0,200,255,.12); border: 1px solid rgba(0,200,255,.3);
  border-radius: 100px; padding: .35rem 1rem;
  font-size: .72rem; font-weight: 600; letter-spacing: .14em;
  color: var(--accent); text-transform: uppercase; width: fit-content;
}
.hero-badge::before {
  content: ''; width: 7px; height: 7px; border-radius: 50%;
  background: var(--accent); animation: pulse 2s ease-in-out infinite;
}
@keyframes pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(1.4)} }

.hero-title {
  font-family: var(--font-display); font-size: clamp(2.4rem,4vw,3.4rem);
  font-weight: 900; line-height: 1.1; color: var(--white); letter-spacing: .02em;
}
.hero-title span {
  display: block;
  background: linear-gradient(135deg, var(--accent), var(--ocean-3));
  -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
}
.hero-sub { font-size: 1rem; font-weight: 300; line-height: 1.7; color: var(--ocean-2); max-width: 440px; }
.hero-stats { display: flex; gap: 2rem; margin-top: .5rem; }
.stat { display: flex; flex-direction: column; gap: .2rem; }
.stat-num { font-family: var(--font-display); font-size: 1.9rem; font-weight: 700; color: var(--accent); line-height: 1; }
.stat-label { font-size: .72rem; font-weight: 500; letter-spacing: .1em; color: var(--ocean-3); text-transform: uppercase; }
.hero-actions { display: flex; gap: 1rem; flex-wrap: wrap; margin-top: .2rem; }
.hero-visual {
  position: relative; overflow: hidden;
  background: linear-gradient(135deg, var(--ocean-6) 0%, var(--ocean-5) 100%);
}
.hero-visual::after {
  content: ''; position: absolute; inset: 0;
  background:
    repeating-linear-gradient(0deg, transparent, transparent 38px, rgba(0,200,255,.05) 38px, rgba(0,200,255,.05) 40px),
    repeating-linear-gradient(90deg, transparent, transparent 38px, rgba(0,200,255,.05) 38px, rgba(0,200,255,.05) 40px);
}
.hero-icon-wrap { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; z-index: 2; }
.hero-icon-wrap svg { width: 220px; height: 220px; opacity: .18; filter: drop-shadow(0 0 40px var(--accent)); }

/* ── Sections ────────────────────────────────────────────────────────────── */
.section { position: relative; z-index: 1; padding: 4rem 5vw; max-width: 1200px; margin: 0 auto; }
.section-label { font-size: .72rem; font-weight: 600; letter-spacing: .2em; color: var(--accent); text-transform: uppercase; margin-bottom: .6rem; }
.section-title { font-family: var(--font-display); font-size: clamp(1.6rem,2.5vw,2.2rem); font-weight: 700; color: var(--white); line-height: 1.2; margin-bottom: 1rem; }
.section-divider { width: 60px; height: 2px; background: linear-gradient(90deg, var(--accent), transparent); margin-bottom: 1.5rem; }

/* ── Highlights ──────────────────────────────────────────────────────────── */
.highlights-grid { display: grid; grid-template-columns: repeat(auto-fit,minmax(200px,1fr)); gap: 1.2rem; margin-top: 2.5rem; }
.highlight-card {
  background: rgba(255,255,255,.04); border: 1px solid rgba(0,200,255,.12);
  border-radius: var(--radius); padding: 1.5rem 1.2rem; text-align: center;
  transition: transform var(--transition), border-color var(--transition), box-shadow var(--transition);
}
.highlight-card:hover { transform: translateY(-4px); border-color: rgba(0,200,255,.35); box-shadow: 0 12px 32px rgba(0,200,255,.1); }
.highlight-icon { font-size: 2rem; margin-bottom: .75rem; display: block; }
.highlight-title { font-family: var(--font-display); font-size: .85rem; font-weight: 600; color: var(--accent); letter-spacing: .1em; text-transform: uppercase; margin-bottom: .4rem; }
.highlight-desc { font-size: .82rem; font-weight: 300; color: var(--ocean-2); line-height: 1.6; }

/* ── Form ────────────────────────────────────────────────────────────────── */
.form-card { background: rgba(255,255,255,.04); border: 1px solid rgba(0,200,255,.15); border-radius: var(--radius); overflow: hidden; box-shadow: var(--shadow); }
.login-gate { text-align: center; padding: 4rem 2rem; }
.login-gate p { font-size: 1rem; color: var(--ocean-2); margin-bottom: 1.5rem; font-weight: 300; }
.form-section-head { background: rgba(0,200,255,.07); border-bottom: 1px solid rgba(0,200,255,.15); padding: 1rem 1.8rem; font-family: var(--font-display); font-size: .78rem; font-weight: 600; letter-spacing: .15em; color: var(--accent); text-transform: uppercase; }
.form-body { padding: 2rem 1.8rem; }

/* ── Who toggle ──────────────────────────────────────────────────────────── */
.toggle-group { display: flex; border-radius: var(--radius-sm); overflow: hidden; border: 1px solid rgba(0,200,255,.2); width: fit-content; margin-bottom: 2rem; }
.toggle-btn { padding: .65rem 1.6rem; font-family: var(--font-body); font-size: .85rem; font-weight: 600; letter-spacing: .06em; cursor: pointer; border: none; background: transparent; color: var(--ocean-2); transition: background var(--transition), color var(--transition); }
.toggle-btn.active { background: linear-gradient(135deg, var(--ocean-4), var(--ocean-5)); color: var(--white); }

/* ── Fields ──────────────────────────────────────────────────────────────── */
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.2rem; }
.form-group { display: flex; flex-direction: column; gap: .45rem; }
.form-group.span-2 { grid-column: span 2; }

label { font-size: .78rem; font-weight: 600; letter-spacing: .08em; color: var(--ocean-2); text-transform: uppercase; }
label .req { color: var(--accent); margin-left: 2px; }

input[type="text"], input[type="email"], input[type="tel"],
input[type="date"], input[type="number"], select, textarea {
  width: 100%; padding: .7rem 1rem;
  background: rgba(255,255,255,.05); border: 1px solid rgba(0,200,255,.2);
  border-radius: var(--radius-sm); font-family: var(--font-body);
  font-size: .9rem; font-weight: 400; color: var(--white);
  transition: border-color var(--transition), box-shadow var(--transition);
  outline: none; -webkit-appearance: none;
}
input:focus, select:focus, textarea:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(0,200,255,.15); }
select option { background: var(--ocean-6); }
textarea { resize: vertical; min-height: 90px; }

/* ── Strokes grid ────────────────────────────────────────────────────────── */
.strokes-grid { display: grid; grid-template-columns: repeat(2,1fr); gap: .6rem; }
.stroke-check {
  display: flex; align-items: center; gap: .65rem;
  background: rgba(255,255,255,.04); border: 1px solid rgba(0,200,255,.15);
  border-radius: var(--radius-sm); padding: .65rem .9rem;
  cursor: pointer; transition: border-color var(--transition), background var(--transition);
}
.stroke-check:has(input:checked) { border-color: var(--accent); background: rgba(0,200,255,.08); }
.stroke-check input[type="checkbox"] { width: 16px; height: 16px; accent-color: var(--accent); cursor: pointer; }
.stroke-check span { font-size: .85rem; font-weight: 500; color: var(--ocean-1); text-transform: capitalize; }

/* ── Age warning ─────────────────────────────────────────────────────────── */
.age-warning { display: none; align-items: center; gap: .7rem; background: rgba(255,77,109,.1); border: 1px solid rgba(255,77,109,.35); border-radius: var(--radius-sm); padding: .85rem 1rem; font-size: .85rem; font-weight: 500; color: #ff8fa3; margin-top: .5rem; }
.age-warning.visible { display: flex; }

/* ── Payment info bar ────────────────────────────────────────────────────── */
.payment-info { display: flex; align-items: center; justify-content: space-between; background: rgba(0,200,255,.07); border: 1px solid rgba(0,200,255,.2); border-radius: var(--radius-sm); padding: 1rem 1.2rem; margin-bottom: 1.5rem; }
.payment-label { font-size: .8rem; font-weight: 600; letter-spacing: .1em; color: var(--ocean-2); text-transform: uppercase; }
.payment-amount { font-family: var(--font-display); font-size: 1.6rem; font-weight: 700; color: var(--accent); }

/* ── Alerts ──────────────────────────────────────────────────────────────── */
.alert { border-radius: var(--radius-sm); padding: 1rem 1.2rem; margin-bottom: 1.5rem; font-size: .88rem; font-weight: 500; line-height: 1.6; }
.alert-error { background: rgba(255,77,109,.1); border: 1px solid rgba(255,77,109,.3); color: #ff8fa3; }
.alert-error ul { padding-left: 1.2rem; }
.alert-success { background: rgba(0,214,143,.1); border: 1px solid rgba(0,214,143,.3); color: var(--success); text-align: center; padding: 2rem; }
.alert-success h3 { font-family: var(--font-display); font-size: 1.3rem; margin-bottom: .5rem; }
.booking-ref { display: inline-block; margin-top: .8rem; background: rgba(0,214,143,.15); border: 1px solid rgba(0,214,143,.3); border-radius: 100px; padding: .35rem 1rem; font-family: var(--font-display); font-size: 1rem; letter-spacing: .15em; color: var(--success); }

/* ── Buttons ─────────────────────────────────────────────────────────────── */
.btn-submit { width: 100%; padding: 1rem; margin-top: 1.5rem; background: linear-gradient(135deg, var(--ocean-4), var(--accent)); border: none; border-radius: var(--radius-sm); font-family: var(--font-display); font-size: .95rem; font-weight: 700; letter-spacing: .15em; color: var(--white); text-transform: uppercase; cursor: pointer; transition: opacity var(--transition), transform var(--transition), box-shadow var(--transition); box-shadow: 0 4px 18px rgba(0,200,255,.3); }
.btn-submit:hover { opacity: .9; transform: translateY(-2px); box-shadow: 0 8px 28px rgba(0,200,255,.45); }
.btn-submit:disabled { opacity: .4; cursor: not-allowed; transform: none; }
.btn { display: inline-flex; align-items: center; gap: .5rem; padding: .75rem 1.8rem; border-radius: 100px; font-family: var(--font-body); font-size: .88rem; font-weight: 600; letter-spacing: .08em; cursor: pointer; text-decoration: none; transition: all var(--transition); border: none; }
.btn-primary { background: linear-gradient(135deg, var(--ocean-4), var(--accent)); color: var(--white); box-shadow: 0 4px 18px rgba(0,200,255,.25); }
.btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(0,200,255,.4); }
.btn-outline { background: transparent; border: 1px solid rgba(0,200,255,.4); color: var(--accent); }
.btn-outline:hover { background: rgba(0,200,255,.08); }

/* ── Animations ──────────────────────────────────────────────────────────── */
@keyframes fadeUp { from{opacity:0;transform:translateY(24px)} to{opacity:1;transform:translateY(0)} }
.hero-content > * { opacity: 0; animation: fadeUp .7s ease forwards; }
.hero-badge   { animation-delay: .1s; }
.hero-title   { animation-delay: .22s; }
.hero-sub     { animation-delay: .34s; }
.hero-stats   { animation-delay: .46s; }
.hero-actions { animation-delay: .56s; }

/* ── Responsive ──────────────────────────────────────────────────────────── */
@media (max-width: 768px) {
  .hero { grid-template-columns: 1fr; }
  .hero-visual { display: none; }
  .hero-content { padding: 3.5rem 1.5rem 2.5rem; }
  nav { padding: 1rem 1.5rem; }
  .section { padding: 3rem 1.5rem; }
  .form-grid { grid-template-columns: 1fr; }
  .form-group.span-2 { grid-column: span 1; }
  .form-body { padding: 1.5rem 1.2rem; }
}

footer { position: relative; z-index: 1; text-align: center; padding: 2.5rem; border-top: 1px solid rgba(0,200,255,.1); font-size: .78rem; font-weight: 300; color: var(--ocean-3); letter-spacing: .06em; }
</style>
</head>
<body>

<nav>
  <a href="index.php" class="nav-logo">Darken Shadows SC</a>
  <div class="nav-links">
    <a href="index.php">Dashboard</a>
    <a href="#enroll" class="nav-cta">Enroll Now</a>
    <?php if (isset($_SESSION['user_id'])): ?>
      <a href="logout.php">Logout</a>
    <?php else: ?>
      <a href="login.php">Login</a>
    <?php endif; ?>
  </div>
</nav>

<!-- ── Hero ──────────────────────────────────────────────────────────────── -->
<section class="hero">
  <div class="hero-content">
    <div class="hero-badge">Ages 6 – 14 &nbsp;·&nbsp; All Skill Levels</div>
    <h1 class="hero-title">Junior<span>Development</span>Program</h1>
    <p class="hero-sub">A structured, coach-led pathway where young swimmers build all four competitive strokes, develop water confidence, and lay the technical foundations for a lifetime in the sport.</p>
    <div class="hero-stats">
      <div class="stat"><span class="stat-num">4</span><span class="stat-label">Strokes</span></div>
      <div class="stat"><span class="stat-num">6–14</span><span class="stat-label">Age Range</span></div>
      <div class="stat"><span class="stat-num">1:8</span><span class="stat-label">Coach Ratio</span></div>
    </div>
    <div class="hero-actions">
      <a href="#enroll" class="btn btn-primary">Enroll Now →</a>
      <a href="#about"  class="btn btn-outline">Learn More</a>
    </div>
  </div>
  <div class="hero-visual">
    <div class="hero-icon-wrap">
      <svg viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="100" cy="45" r="22" stroke="#00c8ff" stroke-width="6"/>
        <path d="M60 130 Q80 90 100 110 Q120 130 160 100" stroke="#00c8ff" stroke-width="6" stroke-linecap="round"/>
        <path d="M40 148 Q70 130 100 145 Q130 160 160 140" stroke="#6db8e8" stroke-width="4" stroke-linecap="round"/>
        <path d="M45 165 Q75 150 105 162 Q135 174 168 157" stroke="#2a8cc4" stroke-width="3" stroke-linecap="round"/>
        <path d="M100 67 L85 110 M100 67 L115 110" stroke="#00c8ff" stroke-width="5" stroke-linecap="round"/>
        <path d="M65 95 L100 80 M135 95 L100 80" stroke="#00c8ff" stroke-width="5" stroke-linecap="round"/>
      </svg>
    </div>
  </div>
</section>

<!-- ── About ─────────────────────────────────────────────────────────────── -->
<div id="about" class="section">
  <p class="section-label">Program Overview</p>
  <h2 class="section-title">What to Expect</h2>
  <div class="section-divider"></div>
  <div class="highlights-grid">
    <div class="highlight-card"><span class="highlight-icon">🏊</span><p class="highlight-title">All Four Strokes</p><p class="highlight-desc">Freestyle, backstroke, breaststroke and butterfly — taught progressively with correct technique from day one.</p></div>
    <div class="highlight-card"><span class="highlight-icon">🎯</span><p class="highlight-title">Skill-Level Groups</p><p class="highlight-desc">Beginner, intermediate and advanced squads ensure every swimmer is challenged at the right level.</p></div>
    <div class="highlight-card"><span class="highlight-icon">👨‍🏫</span><p class="highlight-title">Qualified Coaches</p><p class="highlight-desc">Dedicated coaches with national accreditation. Maximum 1:8 coach-to-swimmer ratio in every session.</p></div>
    <div class="highlight-card"><span class="highlight-icon">📅</span><p class="highlight-title">Flexible Schedule</p><p class="highlight-desc">Choose 1 to 5 sessions per week to fit around school, family commitments and recovery needs.</p></div>
    <div class="highlight-card"><span class="highlight-icon">🛡️</span><p class="highlight-title">Safe Environment</p><p class="highlight-desc">Safeguarding-certified staff, clear emergency protocols, and a fully supervised pool at all times.</p></div>
    <div class="highlight-card"><span class="highlight-icon">📈</span><p class="highlight-title">Progress Tracking</p><p class="highlight-desc">Regular milestone assessments with written feedback so parents and swimmers stay informed of growth.</p></div>
  </div>
</div>

<!-- ── Enrollment Form ──────────────────────────────────────────────────── -->
<div id="enroll" class="section" style="padding-bottom:5rem;">
  <p class="section-label">Get Started</p>
  <h2 class="section-title">Enrollment Form</h2>
  <div class="section-divider"></div>

  <?php if ($success): ?>
  <div class="alert alert-success">
    <h3>🎉 Enrollment Submitted!</h3>
    <p>Your application has been received and is pending confirmation.<br>Keep your booking reference for all correspondence.</p>
    <div class="booking-ref"><?= htmlspecialchars($booking_ref) ?></div>
    <br><br>
    <a href="index.php" class="btn btn-primary">Back to Dashboard</a>
  </div>

  <?php elseif (!isset($_SESSION['user_id'])): ?>
  <div class="form-card">
    <div class="login-gate">
      <p>You must be logged in to enroll in a program.</p>
      <a href="login.php?redirect=Program_junior_development.php#enroll" class="btn btn-primary">Login to Enroll →</a>
      &nbsp;
      <a href="signup.php" class="btn btn-outline">Create Account</a>
    </div>
  </div>

  <?php else: ?>

  <?php if (!empty($errors)): ?>
  <div class="alert alert-error">
    <strong>Please fix the following:</strong>
    <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
  </div>
  <?php endif; ?>

  <form method="POST" action="#enroll" id="enrollForm">

    <!-- ── STEP 1: Who is enrolling? ────────────────────────────────── -->
    <div class="form-card" style="margin-bottom:1.5rem;">
      <div class="form-section-head">Step 1 — Who is enrolling?</div>
      <div class="form-body">

        <div class="toggle-group">
          <button type="button" class="toggle-btn" id="btn-self"  onclick="setForSelf(1)">Myself</button>
          <button type="button" class="toggle-btn" id="btn-other" onclick="setForSelf(0)">Someone Else</button>
        </div>
        <input type="hidden" name="is_for_self" id="is_for_self" value="<?= (int)($p['is_for_self'] ?? 1) ?>">

        <!-- Relationship (hidden when self) -->
        <div id="relation-row" style="display:none; margin-bottom:1.2rem;">
          <div class="form-group">
            <label>Your Relationship to the Swimmer <span class="req">*</span></label>
            <select name="relation">
              <option value="">— Select —</option>
              <?php foreach (['parent','guardian','sibling','grandparent','aunt/uncle','family friend','other'] as $r): ?>
              <option value="<?= $r ?>" <?= ($p['relation']??'')===$r?'selected':'' ?>><?= ucfirst($r) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- Swimmer details — shown always -->
        <div class="form-grid">
          <div class="form-group span-2">
            <label>Swimmer's Full Name <span class="req">*</span></label>
            <input type="text" name="swimmer_name"
                   placeholder="First and last name of the swimmer"
                   value="<?= htmlspecialchars($p['swimmer_name'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Swimmer's Date of Birth <span class="req">*</span></label>
            <input type="date" name="swimmer_dob" id="swimmer_dob"
                   value="<?= htmlspecialchars($p['swimmer_dob'] ?? '') ?>"
                   onchange="checkAge(this.value)">
            <div class="age-warning" id="age-warning">Junior Development is for ages 6–14 only.</div>
          </div>
          <div class="form-group">
            <label>Emergency Contact <span class="req">*</span></label>
            <input type="text" name="emergency_contact"
                   placeholder="Name and phone number"
                   value="<?= htmlspecialchars($p['emergency_contact'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Swimmer's Email</label>
            <input type="email" name="swimmer_email"
                   placeholder="swimmer@email.com"
                   value="<?= htmlspecialchars($p['swimmer_email'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Swimmer's Phone</label>
            <input type="tel" name="swimmer_phone"
                   placeholder="+91 98765 43210"
                   value="<?= htmlspecialchars($p['swimmer_phone'] ?? '') ?>">
          </div>
          <div class="form-group span-2">
            <label>Medical Notes</label>
            <textarea name="medical_notes" placeholder="Any medical conditions, allergies, or special requirements…"><?= htmlspecialchars($p['medical_notes'] ?? '') ?></textarea>
          </div>
        </div>

      </div>
    </div>

    <!-- ── STEP 2: Swimming Details ───────────────────────────────────── -->
    <div class="form-card" style="margin-bottom:1.5rem;">
      <div class="form-section-head">Step 2 — Swimming Details</div>
      <div class="form-body">
        <div class="form-grid">

          <div class="form-group">
            <label>Skill Level <span class="req">*</span></label>
            <select name="skill_level">
              <option value="">— Select Level —</option>
              <?php foreach ([
                'beginner'     => 'Beginner — new to swimming',
                'novice'       => 'Novice — basic water confidence',
                'intermediate' => 'Intermediate — can swim one length',
                'advanced'     => 'Advanced — multi-stroke swimmer',
              ] as $val => $lbl): ?>
              <option value="<?= $val ?>" <?= ($p['skill_level']??'')===$val?'selected':'' ?>><?= $lbl ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label>Sessions per Week <span class="req">*</span></label>
            <select name="sessions_week">
              <?php for ($i = 1; $i <= 5; $i++): ?>
              <option value="<?= $i ?>" <?= (($p['sessions_week']??3)==$i)?'selected':'' ?>>
                <?= $i ?> session<?= $i > 1 ? 's' : '' ?> / week
              </option>
              <?php endfor; ?>
            </select>
          </div>

          <div class="form-group span-2">
            <label>Strokes to Focus On <span class="req">*</span></label>
            <div class="strokes-grid">
              <?php
              $icons = ['freestyle'=>'🏊','backstroke'=>'🔄','breaststroke'=>'🐸','butterfly'=>'🦋'];
              $sel_strokes = $p['strokes'] ?? [];
              foreach ($icons as $s => $icon): ?>
              <label class="stroke-check">
                <input type="checkbox" name="strokes[]" value="<?= $s ?>"
                       <?= in_array($s, $sel_strokes) ? 'checked' : '' ?>>
                <span><?= $icon ?> <?= ucfirst($s) ?></span>
              </label>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="form-group span-2">
            <label>Goals &amp; Expectations</label>
            <textarea name="goals" placeholder="What does the swimmer hope to achieve? Any areas to improve?"><?= htmlspecialchars($p['goals'] ?? '') ?></textarea>
          </div>

        </div>
      </div>
    </div>

    <!-- ── STEP 3: Payment ────────────────────────────────────────────── -->
    <div class="form-card">
      <div class="form-section-head">Step 3 — Payment</div>
      <div class="form-body">

        <div class="payment-info">
          <div>
            <p class="payment-label">Junior Development — Enrollment Fee</p>
            <p style="font-size:.78rem;color:var(--ocean-3);margin-top:.2rem;">One-time registration · monthly fees apply on activation</p>
          </div>
          <div class="payment-amount">INR 150</div>
        </div>

        <div class="form-grid">
          <div class="form-group">
            <label>Payment Method <span class="req">*</span></label>
            <select name="payment_method">
              <option value="">— Select —</option>
              <?php foreach (['cash'=>'Cash at Reception','bank_transfer'=>'Bank Transfer','online'=>'Online Payment','cheque'=>'Cheque'] as $v => $l): ?>
              <option value="<?= $v ?>" <?= ($p['payment_method']??'')===$v?'selected':'' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Payment Reference</label>
            <input type="text" name="payment_reference"
                   placeholder="Transaction / receipt number"
                   value="<?= htmlspecialchars($p['payment_reference'] ?? '') ?>">
          </div>
        </div>

        <button type="submit" class="btn-submit" id="submitBtn">Submit Enrollment →</button>

      </div>
    </div>

  </form>
  <?php endif; ?>
</div>

<footer>&copy; <?= date('Y') ?> Darken Shadows Swimming Club &nbsp;·&nbsp; All Rights Reserved</footer>

<script>
// ── Who toggle ────────────────────────────────────────────────────────────
function setForSelf(val) {
  document.getElementById('is_for_self').value = val;
  document.getElementById('relation-row').style.display = val === 0 ? 'block' : 'none';
  document.getElementById('btn-self').classList.toggle('active',  val === 1);
  document.getElementById('btn-other').classList.toggle('active', val === 0);
}

// ── Age check ─────────────────────────────────────────────────────────────
function checkAge(dob) {
  if (!dob) return;
  const today = new Date(), born = new Date(dob);
  let age = today.getFullYear() - born.getFullYear();
  if (today.getMonth() < born.getMonth() ||
     (today.getMonth() === born.getMonth() && today.getDate() < born.getDate())) age--;
  const warn = document.getElementById('age-warning');
  const btn  = document.getElementById('submitBtn');
  const bad  = age < 6 || age > 14;
  warn.classList.toggle('visible', bad);
  if (bad) warn.textContent = `⚠ Swimmer is ${age} years old — program is for ages 6–14 only.`;
  btn.disabled = bad;
}

// ── Init ──────────────────────────────────────────────────────────────────
(function () {
  const v = parseInt(document.getElementById('is_for_self').value);
  setForSelf(v);
  const dob = document.getElementById('swimmer_dob').value;
  if (dob) checkAge(dob);
})();
</script>
</body>
</html>