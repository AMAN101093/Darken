<?php
// Program_competitive_squad.php
session_start();
require_once 'config/db.php';

$errors      = [];
$success     = false;
$booking_ref = '';

// ── POST handler ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php?redirect=Program_competitive_squad.php');
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

    if (!$swimmer_name)      $errors[] = "Swimmer's full name is required.";
    if (!$swimmer_dob)       $errors[] = "Swimmer's date of birth is required.";
    if ($is_for_self === 0 && !$relation)
                             $errors[] = "Your relationship to the swimmer is required.";
    if (!$emergency_contact) $errors[] = "Emergency contact is required.";

    // ── Program-specific fields ───────────────────────────────────────────
    $skill_level    = trim($_POST['skill_level']    ?? '');
    $target_event   = trim($_POST['target_event']   ?? '');
    $pb_seconds = (($_POST['pb_seconds'] ?? '') !== '') ? (float)$_POST['pb_seconds'] : null;
    $meet_tracking  = isset($_POST['meet_tracking']) ? 1 : 0;
    $goals          = trim($_POST['goals'] ?? '');

    if (!$skill_level)  $errors[] = "Please select an experience level.";
    if (!$target_event) $errors[] = "Please select a target event.";

    // ── Payment ───────────────────────────────────────────────────────────
    $payment_method    = trim($_POST['payment_method']    ?? '');
    $payment_amount    = 220.00;
    $payment_reference = trim($_POST['payment_reference'] ?? '');

    if (!$payment_method) $errors[] = "Payment method is required.";

    // ── Insert ────────────────────────────────────────────────────────────
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO prog_competitive_squad
                    (user_id, is_for_self, relation,
                     swimmer_full_name, swimmer_dob, swimmer_email, swimmer_phone,
                     emergency_contact, medical_notes,
                     skill_level, target_event, current_pb_seconds, meet_tracking, goals,
                     created_at)
                VALUES (?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?,
                        ?, ?, ?, ?, ?,
                        NOW())
            ");
            $stmt->execute([
                $user_id, $is_for_self, $relation ?: null,
                $swimmer_name, $swimmer_dob, $swimmer_email ?: null, $swimmer_phone ?: null,
                $emergency_contact, $medical_notes ?: null,
                $skill_level, $target_event, $pb_seconds, $meet_tracking, $goals ?: null,
            ]);
            $program_record_id = (int)$pdo->lastInsertId();

            $stmt2 = $pdo->prepare("
                INSERT INTO bookings
                    (booked_by_user_id, is_for_self,
                     swimmer_name, swimmer_dob, swimmer_relation,
                     swimmer_phone, swimmer_email,
                     swimmer_emergency_contact, swimmer_medical_notes,
                     program, program_record_id,
                     payment_amount, payment_method, payment_reference, payment_status,
                     status, created_at)
                VALUES (?, ?,
                        ?, ?, ?,
                        ?, ?,
                        ?, ?,
                        'competitive_squad', ?,
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

            $booking_id  = (int)$pdo->lastInsertId();
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

$p = $_POST;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Competitive Squad — Darken Shadows Swimming Club</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700;900&family=Raleway:ital,wght@0,300;0,400;0,500;0,600;1,300&display=swap" rel="stylesheet">
<style>
:root {
  --ocean-1:#e8f4fd; --ocean-2:#b8ddf5; --ocean-3:#6db8e8;
  --ocean-4:#2a8cc4; --ocean-5:#1565a0; --ocean-6:#0d3d6b; --ocean-7:#061e35;
  --accent:#ffc847; --accent-2:#ff7c3a;
  --white:#ffffff; --error:#ff4d6d; --success:#00d68f;
  --font-display:'Cinzel',serif; --font-body:'Raleway',sans-serif;
  --radius:12px; --radius-sm:6px;
  --shadow:0 8px 32px rgba(6,30,53,.5);
  --transition:.25s cubic-bezier(.4,0,.2,1);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html{scroll-behavior:smooth;}
body{font-family:var(--font-body);background:var(--ocean-7);color:var(--ocean-1);min-height:100vh;overflow-x:hidden;}
body::before{content:'';position:fixed;inset:0;background:radial-gradient(ellipse 70% 55% at 15% 5%,rgba(255,200,71,.08) 0%,transparent 55%),radial-gradient(ellipse 60% 70% at 85% 95%,rgba(255,124,58,.07) 0%,transparent 55%),radial-gradient(ellipse 100% 100% at 50% 50%,var(--ocean-7) 35%,var(--ocean-6) 100%);z-index:0;pointer-events:none;}

nav{position:sticky;top:0;z-index:100;display:flex;align-items:center;justify-content:space-between;padding:1rem 2.5rem;background:rgba(6,30,53,.85);backdrop-filter:blur(18px);border-bottom:1px solid rgba(255,200,71,.12);}
.nav-logo{font-family:var(--font-display);font-size:1.05rem;font-weight:700;letter-spacing:.12em;color:var(--accent);text-decoration:none;text-transform:uppercase;}
.nav-links{display:flex;gap:1.8rem;align-items:center;}
.nav-links a{font-size:.82rem;font-weight:600;letter-spacing:.08em;color:var(--ocean-2);text-decoration:none;text-transform:uppercase;transition:color var(--transition);}
.nav-links a:hover{color:var(--accent);}
.nav-cta{background:linear-gradient(135deg,var(--accent-2),var(--accent));color:var(--ocean-7)!important;padding:.45rem 1.2rem;border-radius:100px;font-size:.78rem!important;}

.hero{position:relative;z-index:1;display:grid;grid-template-columns:1fr 1fr;min-height:540px;overflow:hidden;}
.hero-content{padding:5rem 3rem 4rem 5vw;display:flex;flex-direction:column;justify-content:center;gap:1.4rem;}
.hero-badge{display:inline-flex;align-items:center;gap:.55rem;background:rgba(255,200,71,.1);border:1px solid rgba(255,200,71,.3);border-radius:100px;padding:.35rem 1rem;font-size:.72rem;font-weight:600;letter-spacing:.14em;color:var(--accent);text-transform:uppercase;width:fit-content;}
.hero-badge::before{content:'';width:7px;height:7px;border-radius:50%;background:var(--accent);animation:pulse 2s ease-in-out infinite;}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(1.4)}}
.hero-title{font-family:var(--font-display);font-size:clamp(2.4rem,4vw,3.4rem);font-weight:900;line-height:1.1;color:var(--white);letter-spacing:.02em;}
.hero-title span{display:block;background:linear-gradient(135deg,var(--accent),var(--accent-2));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.hero-sub{font-size:1rem;font-weight:300;line-height:1.7;color:var(--ocean-2);max-width:440px;}
.hero-stats{display:flex;gap:2rem;margin-top:.5rem;}
.stat{display:flex;flex-direction:column;gap:.2rem;}
.stat-num{font-family:var(--font-display);font-size:1.9rem;font-weight:700;color:var(--accent);line-height:1;}
.stat-label{font-size:.72rem;font-weight:500;letter-spacing:.1em;color:var(--ocean-3);text-transform:uppercase;}
.hero-actions{display:flex;gap:1rem;flex-wrap:wrap;}
.hero-visual{position:relative;overflow:hidden;background:linear-gradient(135deg,var(--ocean-6) 0%,#0a2e50 100%);}
.hero-visual::after{content:'';position:absolute;inset:0;background:repeating-linear-gradient(45deg,transparent,transparent 28px,rgba(255,200,71,.03) 28px,rgba(255,200,71,.03) 30px),repeating-linear-gradient(-45deg,transparent,transparent 28px,rgba(255,200,71,.03) 28px,rgba(255,200,71,.03) 30px);}
.hero-icon-wrap{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;z-index:2;}
.hero-icon-wrap svg{width:240px;height:240px;opacity:.15;filter:drop-shadow(0 0 40px var(--accent));}

.section{position:relative;z-index:1;padding:4rem 5vw;max-width:1200px;margin:0 auto;}
.section-label{font-size:.72rem;font-weight:600;letter-spacing:.2em;color:var(--accent);text-transform:uppercase;margin-bottom:.6rem;}
.section-title{font-family:var(--font-display);font-size:clamp(1.6rem,2.5vw,2.2rem);font-weight:700;color:var(--white);line-height:1.2;margin-bottom:1rem;}
.section-divider{width:60px;height:2px;background:linear-gradient(90deg,var(--accent),transparent);margin-bottom:1.5rem;}

.highlights-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1.2rem;margin-top:2.5rem;}
.highlight-card{background:rgba(255,255,255,.04);border:1px solid rgba(255,200,71,.1);border-radius:var(--radius);padding:1.5rem 1.2rem;text-align:center;transition:transform var(--transition),border-color var(--transition),box-shadow var(--transition);}
.highlight-card:hover{transform:translateY(-4px);border-color:rgba(255,200,71,.35);box-shadow:0 12px 32px rgba(255,200,71,.08);}
.highlight-icon{font-size:2rem;margin-bottom:.75rem;display:block;}
.highlight-title{font-family:var(--font-display);font-size:.85rem;font-weight:600;color:var(--accent);letter-spacing:.1em;text-transform:uppercase;margin-bottom:.4rem;}
.highlight-desc{font-size:.82rem;font-weight:300;color:var(--ocean-2);line-height:1.6;}

.form-card{background:rgba(255,255,255,.04);border:1px solid rgba(255,200,71,.13);border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow);}
.login-gate{text-align:center;padding:4rem 2rem;}
.login-gate p{font-size:1rem;color:var(--ocean-2);margin-bottom:1.5rem;font-weight:300;}
.form-section-head{background:rgba(255,200,71,.07);border-bottom:1px solid rgba(255,200,71,.13);padding:1rem 1.8rem;font-family:var(--font-display);font-size:.78rem;font-weight:600;letter-spacing:.15em;color:var(--accent);text-transform:uppercase;}
.form-body{padding:2rem 1.8rem;}

.toggle-group{display:flex;border-radius:var(--radius-sm);overflow:hidden;border:1px solid rgba(255,200,71,.2);width:fit-content;margin-bottom:2rem;}
.toggle-btn{padding:.65rem 1.6rem;font-family:var(--font-body);font-size:.85rem;font-weight:600;letter-spacing:.06em;cursor:pointer;border:none;background:transparent;color:var(--ocean-2);transition:background var(--transition),color var(--transition);}
.toggle-btn.active{background:linear-gradient(135deg,var(--accent-2),var(--accent));color:var(--ocean-7);}

.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;}
.form-group{display:flex;flex-direction:column;gap:.45rem;}
.form-group.span-2{grid-column:span 2;}
label{font-size:.78rem;font-weight:600;letter-spacing:.08em;color:var(--ocean-2);text-transform:uppercase;}
label .req{color:var(--accent);margin-left:2px;}
input[type="text"],input[type="email"],input[type="tel"],input[type="date"],input[type="number"],select,textarea{width:100%;padding:.7rem 1rem;background:rgba(255,255,255,.05);border:1px solid rgba(255,200,71,.18);border-radius:var(--radius-sm);font-family:var(--font-body);font-size:.9rem;font-weight:400;color:var(--white);transition:border-color var(--transition),box-shadow var(--transition);outline:none;-webkit-appearance:none;}
input:focus,select:focus,textarea:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(255,200,71,.12);}
select option{background:var(--ocean-6);}
textarea{resize:vertical;min-height:90px;}
.pb-hint{font-size:.75rem;color:var(--ocean-3);font-style:italic;margin-top:.25rem;}

.check-row{display:flex;align-items:center;gap:.75rem;background:rgba(255,255,255,.04);border:1px solid rgba(255,200,71,.15);border-radius:var(--radius-sm);padding:.75rem 1rem;cursor:pointer;transition:border-color var(--transition),background var(--transition);}
.check-row:has(input:checked){border-color:var(--accent);background:rgba(255,200,71,.07);}
.check-row input[type="checkbox"]{width:16px;height:16px;accent-color:var(--accent);cursor:pointer;}
.check-row span{font-size:.88rem;font-weight:500;color:var(--ocean-1);}

.alert{border-radius:var(--radius-sm);padding:1rem 1.2rem;margin-bottom:1.5rem;font-size:.88rem;font-weight:500;line-height:1.6;}
.alert-error{background:rgba(255,77,109,.1);border:1px solid rgba(255,77,109,.3);color:#ff8fa3;}
.alert-error ul{padding-left:1.2rem;}
.alert-success{background:rgba(0,214,143,.1);border:1px solid rgba(0,214,143,.3);color:var(--success);text-align:center;padding:2rem;}
.alert-success h3{font-family:var(--font-display);font-size:1.3rem;margin-bottom:.5rem;}
.booking-ref{display:inline-block;margin-top:.8rem;background:rgba(0,214,143,.15);border:1px solid rgba(0,214,143,.3);border-radius:100px;padding:.35rem 1rem;font-family:var(--font-display);font-size:1rem;letter-spacing:.15em;color:var(--success);}

.payment-info{display:flex;align-items:center;justify-content:space-between;background:rgba(255,200,71,.07);border:1px solid rgba(255,200,71,.2);border-radius:var(--radius-sm);padding:1rem 1.2rem;margin-bottom:1.5rem;}
.payment-label{font-size:.8rem;font-weight:600;letter-spacing:.1em;color:var(--ocean-2);text-transform:uppercase;}
.payment-amount{font-family:var(--font-display);font-size:1.6rem;font-weight:700;color:var(--accent);}

.btn-submit{width:100%;padding:1rem;margin-top:1.5rem;background:linear-gradient(135deg,var(--accent-2),var(--accent));border:none;border-radius:var(--radius-sm);font-family:var(--font-display);font-size:.95rem;font-weight:700;letter-spacing:.15em;color:var(--ocean-7);text-transform:uppercase;cursor:pointer;transition:opacity var(--transition),transform var(--transition),box-shadow var(--transition);box-shadow:0 4px 18px rgba(255,200,71,.25);}
.btn-submit:hover{opacity:.9;transform:translateY(-2px);box-shadow:0 8px 28px rgba(255,200,71,.4);}
.btn{display:inline-flex;align-items:center;gap:.5rem;padding:.75rem 1.8rem;border-radius:100px;font-family:var(--font-body);font-size:.88rem;font-weight:600;letter-spacing:.08em;cursor:pointer;text-decoration:none;transition:all var(--transition);border:none;}
.btn-primary{background:linear-gradient(135deg,var(--accent-2),var(--accent));color:var(--ocean-7);box-shadow:0 4px 18px rgba(255,200,71,.22);}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(255,200,71,.38);}
.btn-outline{background:transparent;border:1px solid rgba(255,200,71,.4);color:var(--accent);}
.btn-outline:hover{background:rgba(255,200,71,.07);}

@keyframes fadeUp{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:translateY(0)}}
.hero-content > *{opacity:0;animation:fadeUp .7s ease forwards;}
.hero-badge{animation-delay:.1s;}.hero-title{animation-delay:.22s;}.hero-sub{animation-delay:.34s;}.hero-stats{animation-delay:.46s;}.hero-actions{animation-delay:.56s;}

@media(max-width:768px){.hero{grid-template-columns:1fr;}.hero-visual{display:none;}.hero-content{padding:3.5rem 1.5rem 2.5rem;}nav{padding:1rem 1.5rem;}.section{padding:3rem 1.5rem;}.form-grid{grid-template-columns:1fr;}.form-group.span-2{grid-column:span 1;}.form-body{padding:1.5rem 1.2rem;}}

footer{position:relative;z-index:1;text-align:center;padding:2.5rem;border-top:1px solid rgba(255,200,71,.08);font-size:.78rem;font-weight:300;color:var(--ocean-3);letter-spacing:.06em;}
</style>
</head>
<body>

<nav>
  <a href="index.php" class="nav-logo">Darken Shadows SC</a>
  <div class="nav-links">
    <a href="index.php">Dashboard</a>
    <a href="#enroll" class="nav-cta">Enroll Now</a>
    <?php if (isset($_SESSION['user_id'])): ?><a href="logout.php">Logout</a><?php else: ?><a href="login.php">Login</a><?php endif; ?>
  </div>
</nav>

<section class="hero">
  <div class="hero-content">
    <div class="hero-badge">Performance · Race-Ready Training</div>
    <h1 class="hero-title">Competitive<span>Squad</span>Program</h1>
    <p class="hero-sub">A high-performance training environment for serious swimmers. Periodised plans, race analysis, and meet preparation — everything you need to compete at your best.</p>
    <div class="hero-stats">
      <div class="stat"><span class="stat-num">5+</span><span class="stat-label">Days/Week</span></div>
      <div class="stat"><span class="stat-num">PB</span><span class="stat-label">Tracking</span></div>
      <div class="stat"><span class="stat-num">∞</span><span class="stat-label">Potential</span></div>
    </div>
    <div class="hero-actions">
      <a href="#enroll" class="btn btn-primary">Enroll Now →</a>
      <a href="#about"  class="btn btn-outline">Learn More</a>
    </div>
  </div>
  <div class="hero-visual">
    <div class="hero-icon-wrap">
      <svg viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect x="70" y="60" width="60" height="80" rx="4" stroke="#ffc847" stroke-width="5"/>
        <rect x="30" y="90" width="40" height="50" rx="4" stroke="#ffc847" stroke-width="4"/>
        <rect x="130" y="105" width="40" height="35" rx="4" stroke="#ffc847" stroke-width="4"/>
        <circle cx="100" cy="38" r="16" stroke="#ffc847" stroke-width="5"/>
        <path d="M90 38 L97 46 L115 30" stroke="#ffc847" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M50 165 L150 165" stroke="#ffc847" stroke-width="5" stroke-linecap="round"/>
      </svg>
    </div>
  </div>
</section>

<div id="about" class="section">
  <p class="section-label">Program Overview</p>
  <h2 class="section-title">Built to Compete</h2>
  <div class="section-divider"></div>
  <div class="highlights-grid">
    <div class="highlight-card"><span class="highlight-icon">📊</span><p class="highlight-title">Periodised Plans</p><p class="highlight-desc">Structured training cycles aligned to your competition calendar — base, build, peak, and taper phases.</p></div>
    <div class="highlight-card"><span class="highlight-icon">⏱️</span><p class="highlight-title">PB Tracking</p><p class="highlight-desc">Personal best times logged and monitored. Every session feeds into your long-term performance curve.</p></div>
    <div class="highlight-card"><span class="highlight-icon">🏆</span><p class="highlight-title">Meet Preparation</p><p class="highlight-desc">Race-day warm-up routines, event-specific pacing strategy, and taper protocols for every competition.</p></div>
    <div class="highlight-card"><span class="highlight-icon">🎯</span><p class="highlight-title">Event-Specific</p><p class="highlight-desc">Training blocks tailored to your target event — from 50m sprints to distance freestyle and IM events.</p></div>
    <div class="highlight-card"><span class="highlight-icon">🔬</span><p class="highlight-title">Technique Analysis</p><p class="highlight-desc">Regular underwater and above-water stroke analysis with corrective drills assigned by your coach.</p></div>
    <div class="highlight-card"><span class="highlight-icon">🧠</span><p class="highlight-title">Mental Readiness</p><p class="highlight-desc">Optional integration with the Mental Conditioning program for pre-race visualisation and stress management.</p></div>
  </div>
</div>

<div id="enroll" class="section" style="padding-bottom:5rem;">
  <p class="section-label">Get Started</p>
  <h2 class="section-title">Enrollment Form</h2>
  <div class="section-divider"></div>

  <?php if ($success): ?>
  <div class="alert alert-success">
    <h3>🏆 Enrollment Submitted!</h3>
    <p>Your Competitive Squad application has been received and is pending confirmation.</p>
    <div class="booking-ref"><?= htmlspecialchars($booking_ref) ?></div>
    <br><br>
    <a href="index.php" class="btn btn-primary">Back to Dashboard</a>
  </div>

  <?php elseif (!isset($_SESSION['user_id'])): ?>
  <div class="form-card">
    <div class="login-gate">
      <p>You must be logged in to enroll in a program.</p>
      <a href="login.php?redirect=Program_competitive_squad.php#enroll" class="btn btn-primary">Login to Enroll →</a>
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

  <form method="POST" action="#enroll">

    <!-- STEP 1 -->
    <div class="form-card" style="margin-bottom:1.5rem;">
      <div class="form-section-head">Step 1 — Who is enrolling?</div>
      <div class="form-body">
        <div class="toggle-group">
          <button type="button" class="toggle-btn" id="btn-self"  onclick="setForSelf(1)">Myself</button>
          <button type="button" class="toggle-btn" id="btn-other" onclick="setForSelf(0)">Someone Else</button>
        </div>
        <input type="hidden" name="is_for_self" id="is_for_self" value="<?= (int)($p['is_for_self'] ?? 1) ?>">

        <div id="relation-row" style="display:none;margin-bottom:1.2rem;">
          <div class="form-group">
            <label>Your Relationship to the Swimmer <span class="req">*</span></label>
            <select name="relation">
              <option value="">— Select —</option>
              <?php foreach (['parent','guardian','spouse','sibling','coach','other'] as $r): ?>
              <option value="<?= $r ?>" <?= ($p['relation']??'')===$r?'selected':'' ?>><?= ucfirst($r) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-grid">
          <div class="form-group span-2">
            <label>Swimmer's Full Name <span class="req">*</span></label>
            <input type="text" name="swimmer_name" placeholder="First and last name of the swimmer"
                   value="<?= htmlspecialchars($p['swimmer_name'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Swimmer's Date of Birth <span class="req">*</span></label>
            <input type="date" name="swimmer_dob" value="<?= htmlspecialchars($p['swimmer_dob'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Emergency Contact <span class="req">*</span></label>
            <input type="text" name="emergency_contact" placeholder="Name and phone number"
                   value="<?= htmlspecialchars($p['emergency_contact'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Swimmer's Email</label>
            <input type="email" name="swimmer_email" placeholder="swimmer@email.com"
                   value="<?= htmlspecialchars($p['swimmer_email'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Swimmer's Phone</label>
            <input type="tel" name="swimmer_phone" placeholder="+91 98765 43210"
                   value="<?= htmlspecialchars($p['swimmer_phone'] ?? '') ?>">
          </div>
          <div class="form-group span-2">
            <label>Medical Notes</label>
            <textarea name="medical_notes" placeholder="Injuries, conditions, or anything your coach should know…"><?= htmlspecialchars($p['medical_notes'] ?? '') ?></textarea>
          </div>
        </div>
      </div>
    </div>

    <!-- STEP 2 -->
    <div class="form-card" style="margin-bottom:1.5rem;">
      <div class="form-section-head">Step 2 — Performance Details</div>
      <div class="form-body">
        <div class="form-grid">
          <div class="form-group">
            <label>Experience Level <span class="req">*</span></label>
            <select name="skill_level">
              <option value="">— Select Level —</option>
              <?php foreach (['club'=>'Club Swimmer','regional'=>'Regional / County Level','national'=>'National Level','elite'=>'Elite / International'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= ($p['skill_level']??'')===$v?'selected':'' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Personal Best (seconds)</label>
            <input type="number" name="pb_seconds" step="0.01" min="0" placeholder="e.g. 58.43"
                   value="<?= htmlspecialchars($p['pb_seconds'] ?? '') ?>">
            <p class="pb-hint">Enter your PB in the target event in seconds. Leave blank if unknown.</p>
          </div>
          <div class="form-group">
            <label>Target Event <span class="req">*</span></label>
            <select name="target_event">
              <option value="">— Select Event —</option>
              <?php foreach (['50m Freestyle','100m Freestyle','200m Freestyle','400m Freestyle','800m Freestyle','1500m Freestyle','50m Backstroke','100m Backstroke','200m Backstroke','50m Breaststroke','100m Breaststroke','200m Breaststroke','50m Butterfly','100m Butterfly','200m Butterfly','200m Individual Medley','400m Individual Medley','4×100m Relay','4×200m Relay','4×100m Medley Relay'] as $e): ?>
              <option value="<?= $e ?>" <?= ($p['target_event']??'')===$e?'selected':'' ?>><?= $e ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="justify-content:flex-end;">
            <label style="margin-bottom:.6rem;">Meet Tracking</label>
            <label class="check-row">
              <input type="checkbox" name="meet_tracking" value="1" <?= !empty($p['meet_tracking'])?'checked':'' ?>>
              <span>📅 Track my competition results and upcoming meets</span>
            </label>
          </div>
          <div class="form-group span-2">
            <label>Goals &amp; Competition Plans</label>
            <textarea name="goals" placeholder="Which competitions are you targeting? What times are you chasing?"><?= htmlspecialchars($p['goals'] ?? '') ?></textarea>
          </div>
        </div>
      </div>
    </div>

    <!-- STEP 3 -->
    <div class="form-card">
      <div class="form-section-head">Step 3 — Payment</div>
      <div class="form-body">
        <div class="payment-info">
          <div>
            <p class="payment-label">Competitive Squad — Enrollment Fee</p>
            <p style="font-size:.78rem;color:var(--ocean-3);margin-top:.2rem;">One-time registration · monthly squad fees apply on activation</p>
          </div>
          <div class="payment-amount">INR 220</div>
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label>Payment Method <span class="req">*</span></label>
            <select name="payment_method">
              <option value="">— Select —</option>
              <?php foreach (['cash'=>'Cash at Reception','bank_transfer'=>'Bank Transfer','online'=>'Online Payment','cheque'=>'Cheque'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= ($p['payment_method']??'')===$v?'selected':'' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Payment Reference</label>
            <input type="text" name="payment_reference" placeholder="Transaction / receipt number"
                   value="<?= htmlspecialchars($p['payment_reference'] ?? '') ?>">
          </div>
        </div>
        <button type="submit" class="btn-submit">Submit Enrollment →</button>
      </div>
    </div>

  </form>
  <?php endif; ?>
</div>

<footer>&copy; <?= date('Y') ?> Darken Shadows Swimming Club &nbsp;·&nbsp; All Rights Reserved</footer>

<script>
function setForSelf(val) {
  document.getElementById('is_for_self').value = val;
  document.getElementById('relation-row').style.display = val === 0 ? 'block' : 'none';
  document.getElementById('btn-self').classList.toggle('active',  val === 1);
  document.getElementById('btn-other').classList.toggle('active', val === 0);
}
(function () { setForSelf(parseInt(document.getElementById('is_for_self').value)); })();
</script>
</body>
</html>