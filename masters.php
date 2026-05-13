<?php
// masters.php — Masters Program (35+ Swimmers)
session_start();
require_once 'config/db.php';

$errors      = [];
$success     = false;
$booking_ref = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php?redirect=masters.php');
        exit;
    }

    $user_id     = (int)$_SESSION['user_id'];
    $is_for_self = (int)($_POST['is_for_self'] ?? 1);

    // ── Swimmer details ───────────────────────────────────────────────────
    $swimmer_name      = trim($_POST['swimmer_name']      ?? '');
    $swimmer_dob       = trim($_POST['swimmer_dob']       ?? '');
    $swimmer_email     = trim($_POST['swimmer_email']     ?? '');
    $swimmer_phone     = trim($_POST['swimmer_phone']     ?? '');
    $emergency_contact = trim($_POST['emergency_contact'] ?? '');
    $medical_notes     = trim($_POST['medical_notes']     ?? '');
    $relation          = $is_for_self === 1 ? 'self' : trim($_POST['relation'] ?? '');

    if (!$swimmer_name)      $errors[] = "Swimmer's full name is required.";
    if (!$swimmer_dob)       $errors[] = "Date of birth is required.";
    if ($is_for_self === 0 && !$relation)
                             $errors[] = "Your relationship to the swimmer is required.";
    if (!$emergency_contact) $errors[] = "Emergency contact is required.";

    // ── Age gate (35+) ────────────────────────────────────────────────────
    if (empty($errors) && $swimmer_dob) {
        $age = (int)(new DateTime())->diff(new DateTime($swimmer_dob))->y;
        if ($age < 35) {
            $errors[] = "Masters Program is for swimmers aged 35 and over. The swimmer is {$age} years old.";
        }
    }

    // ── Program-specific fields ───────────────────────────────────────────
    $sessions_per_week     = max(1, min(7, (int)($_POST['sessions_per_week'] ?? 3)));
    $primary_goal          = trim($_POST['primary_goal']          ?? '');
    $competes_in_meets     = isset($_POST['competes_in_meets'])    ? 1 : 0;
    $age_group_category    = trim($_POST['age_group_category']    ?? '');
    $social_events_opt_in  = isset($_POST['social_events_opt_in']) ? 1 : 0;

    if (!$primary_goal) $errors[] = "Please select a primary goal.";

    // ── Payment ───────────────────────────────────────────────────────────
    $payment_method    = trim($_POST['payment_method']    ?? '');
    $payment_amount    = 200.00;
    $payment_reference = trim($_POST['payment_reference'] ?? '');

    if (!$payment_method) $errors[] = "Payment method is required.";

    // ── Insert ────────────────────────────────────────────────────────────
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO prog_masters_program
                    (user_id, is_for_self, relation,
                     swimmer_full_name, swimmer_dob, swimmer_email, swimmer_phone,
                     emergency_contact, medical_notes,
                     sessions_per_week, primary_goal, competes_in_meets,
                     age_group_category, social_events_opt_in,
                     created_at)
                VALUES (?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?,
                        ?, ?, ?,
                        ?, ?,
                        NOW())
            ");
            $stmt->execute([
                $user_id, $is_for_self, $relation ?: null,
                $swimmer_name, $swimmer_dob, $swimmer_email ?: null, $swimmer_phone ?: null,
                $emergency_contact, $medical_notes ?: null,
                $sessions_per_week, $primary_goal, $competes_in_meets,
                $age_group_category ?: null, $social_events_opt_in,
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
                        'masters_program', ?,
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
<title>Masters Program — Darken Shadows Swimming Club</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700;900&family=Raleway:ital,wght@0,300;0,400;0,500;0,600;1,300&display=swap" rel="stylesheet">
<style>
:root {
  --ocean-1:#e8f4fd; --ocean-2:#b8ddf5; --ocean-3:#6db8e8;
  --ocean-4:#2a8cc4; --ocean-5:#1565a0; --ocean-6:#0d3d6b; --ocean-7:#061e35;
  --accent:#e8b84b; --accent-2:#c9922a; --accent-3:#fff3cc;
  --white:#ffffff; --error:#ff4d6d; --success:#00d68f;
  --font-display:'Cinzel',serif; --font-body:'Raleway',sans-serif;
  --radius:12px; --radius-sm:6px;
  --shadow:0 8px 40px rgba(6,30,53,.55);
  --transition:.25s cubic-bezier(.4,0,.2,1);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html{scroll-behavior:smooth;}
body{font-family:var(--font-body);background:var(--ocean-7);color:var(--ocean-1);min-height:100vh;overflow-x:hidden;}
body::before{content:'';position:fixed;inset:0;background:radial-gradient(ellipse 75% 55% at 15% 8%,rgba(232,184,75,.12) 0%,transparent 58%),radial-gradient(ellipse 55% 70% at 88% 90%,rgba(201,146,42,.1) 0%,transparent 55%),radial-gradient(ellipse 100% 100% at 50% 50%,var(--ocean-7) 38%,var(--ocean-6) 100%);z-index:0;pointer-events:none;}

nav{position:sticky;top:0;z-index:100;display:flex;align-items:center;justify-content:space-between;padding:1rem 2.5rem;background:rgba(6,30,53,.85);backdrop-filter:blur(20px);border-bottom:1px solid rgba(232,184,75,.14);}
.nav-logo{font-family:var(--font-display);font-size:1.05rem;font-weight:700;letter-spacing:.14em;color:var(--accent);text-decoration:none;text-transform:uppercase;}
.nav-links{display:flex;gap:1.8rem;align-items:center;}
.nav-links a{font-size:.82rem;font-weight:600;letter-spacing:.08em;color:var(--ocean-2);text-decoration:none;text-transform:uppercase;transition:color var(--transition);}
.nav-links a:hover{color:var(--accent);}
.nav-cta{background:linear-gradient(135deg,var(--accent-2),var(--accent));color:var(--ocean-7)!important;padding:.45rem 1.2rem;border-radius:100px;font-size:.78rem!important;}

.hero{position:relative;z-index:1;display:grid;grid-template-columns:1fr 1fr;min-height:530px;overflow:hidden;}
.hero-content{padding:5rem 3rem 4rem 5vw;display:flex;flex-direction:column;justify-content:center;gap:1.4rem;}
.hero-badge{display:inline-flex;align-items:center;gap:.55rem;background:rgba(232,184,75,.1);border:1px solid rgba(232,184,75,.3);border-radius:100px;padding:.35rem 1rem;font-size:.72rem;font-weight:600;letter-spacing:.14em;color:var(--accent);text-transform:uppercase;width:fit-content;}
.hero-badge::before{content:'';width:7px;height:7px;border-radius:50%;background:var(--accent);animation:pulse 2s ease-in-out infinite;}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(1.4)}}
.hero-title{font-family:var(--font-display);font-size:clamp(2.4rem,4vw,3.4rem);font-weight:900;line-height:1.1;color:var(--white);letter-spacing:.02em;}
.hero-title span{display:block;background:linear-gradient(135deg,var(--accent-3),var(--accent));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.hero-sub{font-size:1rem;font-weight:300;line-height:1.7;color:var(--ocean-2);max-width:440px;}
.hero-stats{display:flex;gap:2rem;margin-top:.5rem;}
.stat{display:flex;flex-direction:column;gap:.2rem;}
.stat-num{font-family:var(--font-display);font-size:1.9rem;font-weight:700;color:var(--accent);line-height:1;}
.stat-label{font-size:.72rem;font-weight:500;letter-spacing:.1em;color:var(--ocean-3);text-transform:uppercase;}
.hero-actions{display:flex;gap:1rem;flex-wrap:wrap;}
.hero-visual{position:relative;overflow:hidden;background:linear-gradient(145deg,var(--ocean-6) 0%,#0a2a1a 100%);}
.hero-visual::after{content:'';position:absolute;inset:0;background:radial-gradient(ellipse 70% 70% at 50% 50%,rgba(232,184,75,.08) 0%,transparent 70%);}
.hero-icon-wrap{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;z-index:2;}
.hero-icon-wrap svg{width:230px;height:230px;filter:drop-shadow(0 0 44px rgba(232,184,75,.5));}

.section{position:relative;z-index:1;padding:4rem 5vw;max-width:1200px;margin:0 auto;}
.section-label{font-size:.72rem;font-weight:600;letter-spacing:.2em;color:var(--accent);text-transform:uppercase;margin-bottom:.6rem;}
.section-title{font-family:var(--font-display);font-size:clamp(1.6rem,2.5vw,2.2rem);font-weight:700;color:var(--white);line-height:1.2;margin-bottom:1rem;}
.section-divider{width:60px;height:2px;background:linear-gradient(90deg,var(--accent),transparent);margin-bottom:1.5rem;}

.highlights-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1.2rem;margin-top:2.5rem;}
.highlight-card{background:rgba(232,184,75,.04);border:1px solid rgba(232,184,75,.12);border-radius:var(--radius);padding:1.5rem 1.2rem;text-align:center;transition:transform var(--transition),border-color var(--transition),box-shadow var(--transition);}
.highlight-card:hover{transform:translateY(-4px);border-color:rgba(232,184,75,.38);box-shadow:0 12px 32px rgba(232,184,75,.1);}
.highlight-icon{font-size:2rem;margin-bottom:.75rem;display:block;}
.highlight-title{font-family:var(--font-display);font-size:.85rem;font-weight:600;color:var(--accent);letter-spacing:.1em;text-transform:uppercase;margin-bottom:.4rem;}
.highlight-desc{font-size:.82rem;font-weight:300;color:var(--ocean-2);line-height:1.6;}

.form-card{background:rgba(232,184,75,.03);border:1px solid rgba(232,184,75,.13);border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow);}
.login-gate{text-align:center;padding:4rem 2rem;}
.login-gate p{font-size:1rem;color:var(--ocean-2);margin-bottom:1.5rem;font-weight:300;}
.form-section-head{background:rgba(232,184,75,.07);border-bottom:1px solid rgba(232,184,75,.13);padding:1rem 1.8rem;font-family:var(--font-display);font-size:.78rem;font-weight:600;letter-spacing:.15em;color:var(--accent);text-transform:uppercase;display:flex;align-items:center;gap:.7rem;}
.form-section-head .step-num{width:22px;height:22px;border-radius:50%;background:linear-gradient(135deg,var(--accent-2),var(--accent));display:flex;align-items:center;justify-content:center;font-size:.68rem;font-weight:700;color:var(--ocean-7);flex-shrink:0;}
.form-body{padding:2rem 1.8rem;}

.toggle-group{display:flex;border-radius:var(--radius-sm);overflow:hidden;border:1px solid rgba(232,184,75,.22);width:fit-content;margin-bottom:2rem;}
.toggle-btn{padding:.65rem 1.6rem;font-family:var(--font-body);font-size:.85rem;font-weight:600;letter-spacing:.06em;cursor:pointer;border:none;background:transparent;color:var(--ocean-2);transition:background var(--transition),color var(--transition);}
.toggle-btn.active{background:linear-gradient(135deg,var(--accent-2),var(--accent));color:var(--ocean-7);}

.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;}
.form-group{display:flex;flex-direction:column;gap:.45rem;}
.form-group.span-2{grid-column:span 2;}
label{font-size:.78rem;font-weight:600;letter-spacing:.08em;color:var(--ocean-2);text-transform:uppercase;}
label .req{color:var(--accent);margin-left:2px;}
input[type="text"],input[type="email"],input[type="tel"],input[type="date"],input[type="number"],select,textarea{width:100%;padding:.7rem 1rem;background:rgba(255,255,255,.05);border:1px solid rgba(232,184,75,.18);border-radius:var(--radius-sm);font-family:var(--font-body);font-size:.9rem;font-weight:400;color:var(--white);transition:border-color var(--transition),box-shadow var(--transition);outline:none;-webkit-appearance:none;}
input:focus,select:focus,textarea:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(232,184,75,.15);}
select option{background:var(--ocean-6);}
textarea{resize:vertical;min-height:90px;}

.age-warning{display:none;align-items:center;gap:.7rem;background:rgba(255,77,109,.1);border:1px solid rgba(255,77,109,.35);border-radius:var(--radius-sm);padding:.85rem 1rem;font-size:.85rem;font-weight:500;color:#ff8fa3;margin-top:.5rem;}
.age-warning.visible{display:flex;}

.goal-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:.8rem;}
.goal-radio{display:flex;align-items:flex-start;gap:.75rem;background:rgba(232,184,75,.04);border:1px solid rgba(232,184,75,.14);border-radius:var(--radius-sm);padding:.9rem 1rem;cursor:pointer;transition:border-color var(--transition),background var(--transition);}
.goal-radio:has(input:checked){border-color:var(--accent);background:rgba(232,184,75,.1);}
.goal-radio input[type="radio"]{width:16px;height:16px;accent-color:var(--accent);cursor:pointer;flex-shrink:0;margin-top:3px;}
.goal-radio-text{display:flex;flex-direction:column;gap:.2rem;}
.goal-radio-label{font-size:.85rem;font-weight:600;color:var(--ocean-1);}
.goal-radio-desc{font-size:.75rem;font-weight:300;color:var(--ocean-3);line-height:1.45;}

.opt-row{display:flex;align-items:center;gap:.75rem;background:rgba(232,184,75,.04);border:1px solid rgba(232,184,75,.14);border-radius:var(--radius-sm);padding:.75rem 1rem;cursor:pointer;transition:border-color var(--transition),background var(--transition);}
.opt-row:has(input:checked){border-color:var(--accent);background:rgba(232,184,75,.09);}
.opt-row input[type="checkbox"]{width:16px;height:16px;accent-color:var(--accent);cursor:pointer;}
.opt-row span{font-size:.88rem;font-weight:500;color:var(--ocean-1);}

.alert{border-radius:var(--radius-sm);padding:1rem 1.2rem;margin-bottom:1.5rem;font-size:.88rem;font-weight:500;line-height:1.6;}
.alert-error{background:rgba(255,77,109,.1);border:1px solid rgba(255,77,109,.3);color:#ff8fa3;}
.alert-error ul{padding-left:1.2rem;}
.alert-success{background:rgba(0,214,143,.1);border:1px solid rgba(0,214,143,.3);color:var(--success);text-align:center;padding:2rem;}
.alert-success h3{font-family:var(--font-display);font-size:1.3rem;margin-bottom:.5rem;}
.booking-ref{display:inline-block;margin-top:.8rem;background:rgba(0,214,143,.15);border:1px solid rgba(0,214,143,.3);border-radius:100px;padding:.35rem 1rem;font-family:var(--font-display);font-size:1rem;letter-spacing:.15em;color:var(--success);}

.payment-info{display:flex;align-items:center;justify-content:space-between;background:rgba(232,184,75,.07);border:1px solid rgba(232,184,75,.2);border-radius:var(--radius-sm);padding:1rem 1.2rem;margin-bottom:1.5rem;}
.payment-label{font-size:.8rem;font-weight:600;letter-spacing:.1em;color:var(--ocean-2);text-transform:uppercase;}
.payment-amount{font-family:var(--font-display);font-size:1.6rem;font-weight:700;color:var(--accent);}

.btn-submit{width:100%;padding:1rem;margin-top:1.5rem;background:linear-gradient(135deg,var(--accent-2),var(--accent));border:none;border-radius:var(--radius-sm);font-family:var(--font-display);font-size:.95rem;font-weight:700;letter-spacing:.15em;color:var(--ocean-7);text-transform:uppercase;cursor:pointer;transition:opacity var(--transition),transform var(--transition),box-shadow var(--transition);box-shadow:0 4px 18px rgba(232,184,75,.3);}
.btn-submit:hover{opacity:.9;transform:translateY(-2px);box-shadow:0 8px 28px rgba(232,184,75,.45);}
.btn-submit:disabled{opacity:.4;cursor:not-allowed;transform:none;}
.btn{display:inline-flex;align-items:center;gap:.5rem;padding:.75rem 1.8rem;border-radius:100px;font-family:var(--font-body);font-size:.88rem;font-weight:600;letter-spacing:.08em;cursor:pointer;text-decoration:none;transition:all var(--transition);border:none;}
.btn-primary{background:linear-gradient(135deg,var(--accent-2),var(--accent));color:var(--ocean-7);box-shadow:0 4px 18px rgba(232,184,75,.25);}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(232,184,75,.4);}
.btn-outline{background:transparent;border:1px solid rgba(232,184,75,.4);color:var(--accent);}
.btn-outline:hover{background:rgba(232,184,75,.08);}

@keyframes fadeUp{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:translateY(0)}}
.hero-content > *{opacity:0;animation:fadeUp .7s ease forwards;}
.hero-badge{animation-delay:.1s;}.hero-title{animation-delay:.22s;}.hero-sub{animation-delay:.34s;}.hero-stats{animation-delay:.46s;}.hero-actions{animation-delay:.56s;}

@media(max-width:768px){.hero{grid-template-columns:1fr;}.hero-visual{display:none;}.hero-content{padding:3.5rem 1.5rem 2.5rem;}nav{padding:1rem 1.5rem;}.section{padding:3rem 1.5rem;}.form-grid{grid-template-columns:1fr;}.form-group.span-2{grid-column:span 1;}.form-body{padding:1.5rem 1.2rem;}.goal-grid{grid-template-columns:1fr;}}
footer{position:relative;z-index:1;text-align:center;padding:2.5rem;border-top:1px solid rgba(232,184,75,.08);font-size:.78rem;font-weight:300;color:var(--ocean-3);letter-spacing:.06em;}
</style>
</head>
<body>

<nav>
  <a href="index.php" class="nav-logo">Darken Shadows SC</a>
  <div class="nav-links">
    <a href="index.php">Dashboard</a>
    <a href="program.php">Programs</a>
    <a href="#enroll" class="nav-cta">Enroll Now</a>
    <?php if (isset($_SESSION['user_id'])): ?><a href="logout.php">Logout</a><?php else: ?><a href="login.php">Login</a><?php endif; ?>
  </div>
</nav>

<!-- ── Hero ──────────────────────────────────────────────────────────────── -->
<section class="hero">
  <div class="hero-content">
    <div class="hero-badge">35+ Swimmers &nbsp;·&nbsp; Age-Group Competition</div>
    <h1 class="hero-title">Masters<span>Program</span></h1>
    <p class="hero-sub">Experience, discipline, and the knowledge that true competitive swimming has no retirement age. Our Masters Program brings serious training, age-group competition, and a community of swimmers who understand the long game.</p>
    <div class="hero-stats">
      <div class="stat"><span class="stat-num">35+</span><span class="stat-label">Age Requirement</span></div>
      <div class="stat"><span class="stat-num">3×</span><span class="stat-label">Weekly Sessions</span></div>
      <div class="stat"><span class="stat-num">∞</span><span class="stat-label">Experience</span></div>
    </div>
    <div class="hero-actions">
      <a href="#enroll" class="btn btn-primary">Enroll Now →</a>
      <a href="#about"  class="btn btn-outline">Learn More</a>
    </div>
  </div>
  <div class="hero-visual">
    <div class="hero-icon-wrap">
      <svg viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg">
        <!-- trophy -->
        <path d="M75 55 L75 115 Q75 135 100 140 Q125 135 125 115 L125 55 Z" stroke="#e8b84b" stroke-width="4.5" stroke-linejoin="round" fill="none"/>
        <path d="M75 70 Q52 70 52 90 Q52 108 75 108" stroke="#e8b84b" stroke-width="4" stroke-linecap="round" fill="none"/>
        <path d="M125 70 Q148 70 148 90 Q148 108 125 108" stroke="#e8b84b" stroke-width="4" stroke-linecap="round" fill="none"/>
        <path d="M90 140 L90 158" stroke="#e8b84b" stroke-width="4" stroke-linecap="round"/>
        <path d="M110 140 L110 158" stroke="#e8b84b" stroke-width="4" stroke-linecap="round"/>
        <path d="M78 158 L122 158" stroke="#e8b84b" stroke-width="4.5" stroke-linecap="round"/>
        <!-- star on trophy -->
        <path d="M100 72 L103.5 82.5 L115 82.5 L105.8 89 L109.3 99.5 L100 93.5 L90.7 99.5 L94.2 89 L85 82.5 L96.5 82.5 Z" stroke="#e8b84b" stroke-width="2.5" stroke-linejoin="round" fill="rgba(232,184,75,.2)"/>
        <!-- shine lines -->
        <line x1="100" y1="28" x2="100" y2="40" stroke="#c9922a" stroke-width="2" stroke-linecap="round"/>
        <line x1="130" y1="36" x2="123" y2="44" stroke="#c9922a" stroke-width="2" stroke-linecap="round"/>
        <line x1="70"  y1="36" x2="77"  y2="44" stroke="#c9922a" stroke-width="2" stroke-linecap="round"/>
      </svg>
    </div>
  </div>
</section>

<!-- ── About ─────────────────────────────────────────────────────────────── -->
<div id="about" class="section">
  <p class="section-label">Program Overview</p>
  <h2 class="section-title">Competition Never Retires</h2>
  <div class="section-divider"></div>
  <div class="highlights-grid">
    <div class="highlight-card"><span class="highlight-icon">🏆</span><p class="highlight-title">Age-Group Competition</p><p class="highlight-desc">FINA-recognised 5-year age bands from 35–39 through to 85+. We prepare every swimmer for the specific demands of their category.</p></div>
    <div class="highlight-card"><span class="highlight-icon">👥</span><p class="highlight-title">Community</p><p class="highlight-desc">Train alongside fellow Masters swimmers who share your dedication. Club socials, gala events, and a culture of mutual encouragement.</p></div>
    <div class="highlight-card"><span class="highlight-icon">💪</span><p class="highlight-title">Structured Training</p><p class="highlight-desc">Coached sessions 3–5 times a week with periodised plans that respect the physiological profile of the Masters athlete.</p></div>
    <div class="highlight-card"><span class="highlight-icon">🌊</span><p class="highlight-title">All Goals Welcome</p><p class="highlight-desc">Whether you're chasing a national Masters record or simply want structured fitness swimming, this program accommodates every ambition.</p></div>
    <div class="highlight-card"><span class="highlight-icon">📅</span><p class="highlight-title">Social Events</p><p class="highlight-desc">Regular club galas, open-water swims, and social gatherings — because swimming is as much about community as competition.</p></div>
    <div class="highlight-card"><span class="highlight-icon">🧠</span><p class="highlight-title">Smart Programming</p><p class="highlight-desc">Training loads, recovery, and taper windows are carefully designed for the over-35 athlete — maximising performance while protecting health.</p></div>
  </div>
</div>

<!-- ── Enrollment Form ──────────────────────────────────────────────────── -->
<div id="enroll" class="section" style="padding-bottom:5rem;">
  <p class="section-label">Get Started</p>
  <h2 class="section-title">Enrollment Form</h2>
  <div class="section-divider"></div>

  <?php if ($success): ?>
  <div class="alert alert-success">
    <h3>🥇 Enrollment Submitted!</h3>
    <p>Welcome to the Darken Shadows Masters Program. Your enrollment is pending confirmation — we'll contact you shortly with your squad placement and first session details.</p>
    <div class="booking-ref"><?= htmlspecialchars($booking_ref) ?></div>
    <br><br>
    <a href="index.php" class="btn btn-primary">Back to Dashboard</a>
  </div>

  <?php elseif (!isset($_SESSION['user_id'])): ?>
  <div class="form-card">
    <div class="login-gate">
      <p>You must be logged in to enroll in a program.</p>
      <a href="login.php?redirect=masters.php#enroll" class="btn btn-primary">Login to Enroll →</a>
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
      <div class="form-section-head"><span class="step-num">1</span> Who is enrolling?</div>
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
              <?php foreach (['spouse','partner','sibling','friend','agent','other'] as $r): ?>
              <option value="<?= $r ?>" <?= ($p['relation']??'')===$r?'selected':'' ?>><?= ucfirst($r) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-grid">
          <div class="form-group span-2">
            <label>Swimmer's Full Name <span class="req">*</span></label>
            <input type="text" name="swimmer_name" placeholder="First and last name"
                   value="<?= htmlspecialchars($p['swimmer_name'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Date of Birth <span class="req">*</span></label>
            <input type="date" name="swimmer_dob" id="swimmer_dob"
                   value="<?= htmlspecialchars($p['swimmer_dob'] ?? '') ?>"
                   onchange="checkAge(this.value)">
            <div class="age-warning" id="age-warning">⚠ Masters Program is for swimmers aged 35 and over.</div>
          </div>
          <div class="form-group">
            <label>Emergency Contact <span class="req">*</span></label>
            <input type="text" name="emergency_contact" placeholder="Name and phone number"
                   value="<?= htmlspecialchars($p['emergency_contact'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="swimmer_email" placeholder="you@email.com"
                   value="<?= htmlspecialchars($p['swimmer_email'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Phone Number</label>
            <input type="tel" name="swimmer_phone" placeholder="+91 98765 43210"
                   value="<?= htmlspecialchars($p['swimmer_phone'] ?? '') ?>">
          </div>
          <div class="form-group span-2">
            <label>Medical / Physical Notes</label>
            <textarea name="medical_notes" placeholder="Any conditions, injuries, or anything your coach must know…"><?= htmlspecialchars($p['medical_notes'] ?? '') ?></textarea>
          </div>
        </div>
      </div>
    </div>

    <!-- STEP 2 -->
    <div class="form-card" style="margin-bottom:1.5rem;">
      <div class="form-section-head"><span class="step-num">2</span> Program Details</div>
      <div class="form-body">
        <div class="form-grid">

          <div class="form-group span-2">
            <label style="margin-bottom:.7rem;">Primary Goal <span class="req">*</span></label>
            <div class="goal-grid">
              <?php
              $goals = [
                'competition' => ['🏆', 'Competition',  'Targeting Masters galas, national or international events'],
                'fitness'     => ['💪', 'Fitness',      'Structured training for health, fitness, and wellbeing'],
                'social'      => ['👥', 'Social',       'Community, camaraderie, and enjoyment of the sport'],
                'all'         => ['⭐', 'All of the Above', 'Competition, fitness, and the social side — the full package'],
              ];
              foreach ($goals as $val => [$icon, $name, $desc]):
              ?>
              <label class="goal-radio">
                <input type="radio" name="primary_goal" value="<?= $val ?>" <?= ($p['primary_goal']??'')===$val?'checked':'' ?>>
                <div class="goal-radio-text">
                  <span class="goal-radio-label"><?= $icon ?> <?= $name ?></span>
                  <span class="goal-radio-desc"><?= $desc ?></span>
                </div>
              </label>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="form-group">
            <label>Sessions per Week</label>
            <select name="sessions_per_week">
              <?php for ($i = 1; $i <= 5; $i++): ?>
              <option value="<?= $i ?>" <?= (($p['sessions_per_week']??3)==$i)?'selected':'' ?>>
                <?= $i ?> session<?= $i > 1 ? 's' : '' ?> / week
              </option>
              <?php endfor; ?>
            </select>
          </div>

          <div class="form-group">
            <label>Age-Group Category</label>
            <select name="age_group_category">
              <option value="">— Select if applicable —</option>
              <?php foreach (['35-39','40-44','45-49','50-54','55-59','60-64','65-69','70-74','75-79','80-84','85+'] as $cat): ?>
              <option value="<?= $cat ?>" <?= ($p['age_group_category']??'')===$cat?'selected':'' ?>><?= $cat ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group" style="justify-content:flex-end;">
            <label style="margin-bottom:.6rem;">Competition</label>
            <label class="opt-row">
              <input type="checkbox" name="competes_in_meets" value="1" <?= !empty($p['competes_in_meets'])?'checked':'' ?>>
              <span>🏊 I intend to compete in Masters meets</span>
            </label>
          </div>

          <div class="form-group" style="justify-content:flex-end;">
            <label style="margin-bottom:.6rem;">Social Events</label>
            <label class="opt-row">
              <input type="checkbox" name="social_events_opt_in" value="1" <?= ($p['social_events_opt_in'] ?? '1') == '1'?'checked':'' ?>>
              <span>🎉 Include me in club socials &amp; event notifications</span>
            </label>
          </div>

        </div>
      </div>
    </div>

    <!-- STEP 3 -->
    <div class="form-card">
      <div class="form-section-head"><span class="step-num">3</span> Payment</div>
      <div class="form-body">
        <div class="payment-info">
          <div>
            <p class="payment-label">Masters Program — Enrollment Fee</p>
            <p style="font-size:.78rem;color:var(--ocean-3);margin-top:.2rem;">One-time registration · monthly squad fees billed on activation</p>
          </div>
          <div class="payment-amount">INR 200</div>
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
            <input type="text" name="payment_reference" placeholder="Transaction / receipt number"
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
function setForSelf(val) {
  document.getElementById('is_for_self').value = val;
  document.getElementById('relation-row').style.display = val === 0 ? 'block' : 'none';
  document.getElementById('btn-self').classList.toggle('active',  val === 1);
  document.getElementById('btn-other').classList.toggle('active', val === 0);
}

function checkAge(dob) {
  if (!dob) return;
  const today = new Date(), born = new Date(dob);
  let age = today.getFullYear() - born.getFullYear();
  if (today.getMonth() < born.getMonth() || (today.getMonth() === born.getMonth() && today.getDate() < born.getDate())) age--;
  const warn = document.getElementById('age-warning');
  const btn  = document.getElementById('submitBtn');
  const bad  = age < 35;
  warn.classList.toggle('visible', bad);
  if (bad) warn.textContent = `⚠ This swimmer is ${age} years old — Masters Program requires age 35+.`;
  btn.disabled = bad;
}

(function () {
  setForSelf(parseInt(document.getElementById('is_for_self').value));
  const dob = document.getElementById('swimmer_dob').value;
  if (dob) checkAge(dob);
})();
</script>
</body>
</html>