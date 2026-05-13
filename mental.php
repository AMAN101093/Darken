<?php
// mental.php — Mental Conditioning Program
session_start();
require_once 'config/db.php';

$errors      = [];
$success     = false;
$booking_ref = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php?redirect=mental.php');
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

    if (!$swimmer_name)      $errors[] = "Participant's full name is required.";
    if (!$swimmer_dob)       $errors[] = "Date of birth is required.";
    if ($is_for_self === 0 && !$relation)
                             $errors[] = "Your relationship to the participant is required.";
    if (!$emergency_contact) $errors[] = "Emergency contact is required.";

    // ── Program-specific fields ───────────────────────────────────────────
    $delivery_format      = trim($_POST['delivery_format']   ?? '');
    $sessions_per_week    = max(1, min(7, (int)($_POST['sessions_per_week'] ?? 1)));
    $linked_program       = trim($_POST['linked_program']    ?? '') ?: null;
    $focus_visualisation  = isset($_POST['focus_visualisation'])  ? 1 : 0;
    $focus_stress_control = isset($_POST['focus_stress_control']) ? 1 : 0;
    $focus_race_readiness = isset($_POST['focus_race_readiness']) ? 1 : 0;
    $focus_resilience     = isset($_POST['focus_resilience'])     ? 1 : 0;
    $custom_focus         = trim($_POST['custom_focus']      ?? '');

    if (!$delivery_format) $errors[] = "Please select a delivery format.";
    if (!$focus_visualisation && !$focus_stress_control && !$focus_race_readiness && !$focus_resilience)
        $errors[] = "Please select at least one focus area.";

    // ── Payment ───────────────────────────────────────────────────────────
    $payment_method    = trim($_POST['payment_method']    ?? '');
    $payment_amount    = 120.00;
    $payment_reference = trim($_POST['payment_reference'] ?? '');

    if (!$payment_method) $errors[] = "Payment method is required.";

    // ── Insert ────────────────────────────────────────────────────────────
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO prog_mental_conditioning
                    (user_id, is_for_self, relation,
                     swimmer_full_name, swimmer_dob, swimmer_email, swimmer_phone,
                     emergency_contact, medical_notes,
                     delivery_format, sessions_per_week, linked_program,
                     focus_visualisation, focus_stress_control, focus_race_readiness, focus_resilience,
                     custom_focus,
                     created_at)
                VALUES (?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?,
                        ?, ?, ?,
                        ?, ?, ?, ?,
                        ?,
                        NOW())
            ");
            $stmt->execute([
                $user_id, $is_for_self, $relation ?: null,
                $swimmer_name, $swimmer_dob, $swimmer_email ?: null, $swimmer_phone ?: null,
                $emergency_contact, $medical_notes ?: null,
                $delivery_format, $sessions_per_week, $linked_program,
                $focus_visualisation, $focus_stress_control, $focus_race_readiness, $focus_resilience,
                $custom_focus ?: null,
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
                        'mental_conditioning', ?,
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
<title>Mental Conditioning — Darken Shadows Swimming Club</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700;900&family=Raleway:ital,wght@0,300;0,400;0,500;0,600;1,300&display=swap" rel="stylesheet">
<style>
:root {
  --ocean-1:#e8f4fd; --ocean-2:#b8ddf5; --ocean-3:#6db8e8;
  --ocean-4:#2a8cc4; --ocean-5:#1565a0; --ocean-6:#0d3d6b; --ocean-7:#061e35;
  --accent:#b57bee; --accent-2:#8b46d4; --accent-3:#e8d5ff;
  --white:#ffffff; --error:#ff4d6d; --success:#00d68f;
  --font-display:'Cinzel',serif; --font-body:'Raleway',sans-serif;
  --radius:12px; --radius-sm:6px;
  --shadow:0 8px 40px rgba(6,30,53,.55);
  --transition:.25s cubic-bezier(.4,0,.2,1);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html{scroll-behavior:smooth;}
body{font-family:var(--font-body);background:var(--ocean-7);color:var(--ocean-1);min-height:100vh;overflow-x:hidden;}
body::before{content:'';position:fixed;inset:0;background:radial-gradient(ellipse 70% 55% at 15% 8%,rgba(139,70,212,.18) 0%,transparent 58%),radial-gradient(ellipse 55% 70% at 88% 90%,rgba(181,123,238,.12) 0%,transparent 55%),radial-gradient(ellipse 100% 100% at 50% 50%,var(--ocean-7) 38%,var(--ocean-6) 100%);z-index:0;pointer-events:none;}

nav{position:sticky;top:0;z-index:100;display:flex;align-items:center;justify-content:space-between;padding:1rem 2.5rem;background:rgba(6,30,53,.85);backdrop-filter:blur(20px);border-bottom:1px solid rgba(181,123,238,.14);}
.nav-logo{font-family:var(--font-display);font-size:1.05rem;font-weight:700;letter-spacing:.14em;color:var(--accent);text-decoration:none;text-transform:uppercase;}
.nav-links{display:flex;gap:1.8rem;align-items:center;}
.nav-links a{font-size:.82rem;font-weight:600;letter-spacing:.08em;color:var(--ocean-2);text-decoration:none;text-transform:uppercase;transition:color var(--transition);}
.nav-links a:hover{color:var(--accent);}
.nav-cta{background:linear-gradient(135deg,var(--accent-2),var(--accent));color:var(--white)!important;padding:.45rem 1.2rem;border-radius:100px;font-size:.78rem!important;}

.hero{position:relative;z-index:1;display:grid;grid-template-columns:1fr 1fr;min-height:530px;overflow:hidden;}
.hero-content{padding:5rem 3rem 4rem 5vw;display:flex;flex-direction:column;justify-content:center;gap:1.4rem;}
.hero-badge{display:inline-flex;align-items:center;gap:.55rem;background:rgba(181,123,238,.1);border:1px solid rgba(181,123,238,.3);border-radius:100px;padding:.35rem 1rem;font-size:.72rem;font-weight:600;letter-spacing:.14em;color:var(--accent);text-transform:uppercase;width:fit-content;}
.hero-badge::before{content:'';width:7px;height:7px;border-radius:50%;background:var(--accent);animation:pulse 2s ease-in-out infinite;}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(1.4)}}
.hero-title{font-family:var(--font-display);font-size:clamp(2.4rem,4vw,3.4rem);font-weight:900;line-height:1.1;color:var(--white);letter-spacing:.02em;}
.hero-title span{display:block;background:linear-gradient(135deg,var(--accent-3),var(--accent));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.hero-sub{font-size:1rem;font-weight:300;line-height:1.7;color:var(--ocean-2);max-width:440px;}
.hero-pills{display:flex;gap:.6rem;flex-wrap:wrap;}
.pill{background:rgba(181,123,238,.1);border:1px solid rgba(181,123,238,.2);border-radius:100px;padding:.28rem .85rem;font-size:.72rem;font-weight:600;letter-spacing:.1em;color:var(--accent-3);text-transform:uppercase;}
.hero-actions{display:flex;gap:1rem;flex-wrap:wrap;}
.hero-visual{position:relative;overflow:hidden;background:linear-gradient(145deg,#0a1628 0%,#0f1f3c 100%);}
.hero-visual::before{content:'';position:absolute;inset:0;background-image:radial-gradient(circle at 1px 1px,rgba(181,123,238,.06) 1px,transparent 0);background-size:28px 28px;}
.hero-visual::after{content:'';position:absolute;inset:0;background:radial-gradient(ellipse 70% 70% at 50% 50%,rgba(139,70,212,.12) 0%,transparent 70%);}
.hero-icon-wrap{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;z-index:2;}
.hero-icon-wrap svg{width:230px;height:230px;filter:drop-shadow(0 0 44px rgba(181,123,238,.5));}

.section{position:relative;z-index:1;padding:4rem 5vw;max-width:1200px;margin:0 auto;}
.section-label{font-size:.72rem;font-weight:600;letter-spacing:.2em;color:var(--accent);text-transform:uppercase;margin-bottom:.6rem;}
.section-title{font-family:var(--font-display);font-size:clamp(1.6rem,2.5vw,2.2rem);font-weight:700;color:var(--white);line-height:1.2;margin-bottom:1rem;}
.section-divider{width:60px;height:2px;background:linear-gradient(90deg,var(--accent),transparent);margin-bottom:1.5rem;}

.highlights-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1.2rem;margin-top:2.5rem;}
.highlight-card{background:rgba(181,123,238,.05);border:1px solid rgba(181,123,238,.12);border-radius:var(--radius);padding:1.5rem 1.2rem;text-align:center;transition:transform var(--transition),border-color var(--transition),box-shadow var(--transition);}
.highlight-card:hover{transform:translateY(-4px);border-color:rgba(181,123,238,.35);box-shadow:0 12px 32px rgba(139,70,212,.12);}
.highlight-icon{font-size:2rem;margin-bottom:.75rem;display:block;}
.highlight-title{font-family:var(--font-display);font-size:.85rem;font-weight:600;color:var(--accent);letter-spacing:.1em;text-transform:uppercase;margin-bottom:.4rem;}
.highlight-desc{font-size:.82rem;font-weight:300;color:var(--ocean-2);line-height:1.6;}

.form-card{background:rgba(181,123,238,.04);border:1px solid rgba(181,123,238,.13);border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow);}
.login-gate{text-align:center;padding:4rem 2rem;}
.login-gate p{font-size:1rem;color:var(--ocean-2);margin-bottom:1.5rem;font-weight:300;}
.form-section-head{background:rgba(181,123,238,.07);border-bottom:1px solid rgba(181,123,238,.13);padding:1rem 1.8rem;font-family:var(--font-display);font-size:.78rem;font-weight:600;letter-spacing:.15em;color:var(--accent);text-transform:uppercase;display:flex;align-items:center;gap:.7rem;}
.form-section-head .step-num{width:22px;height:22px;border-radius:50%;background:linear-gradient(135deg,var(--accent-2),var(--accent));display:flex;align-items:center;justify-content:center;font-size:.68rem;font-weight:700;color:var(--white);flex-shrink:0;}
.form-body{padding:2rem 1.8rem;}

.toggle-group{display:flex;border-radius:var(--radius-sm);overflow:hidden;border:1px solid rgba(181,123,238,.22);width:fit-content;margin-bottom:2rem;}
.toggle-btn{padding:.65rem 1.6rem;font-family:var(--font-body);font-size:.85rem;font-weight:600;letter-spacing:.06em;cursor:pointer;border:none;background:transparent;color:var(--ocean-2);transition:background var(--transition),color var(--transition);}
.toggle-btn.active{background:linear-gradient(135deg,var(--accent-2),var(--accent));color:var(--white);}

.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;}
.form-group{display:flex;flex-direction:column;gap:.45rem;}
.form-group.span-2{grid-column:span 2;}
label{font-size:.78rem;font-weight:600;letter-spacing:.08em;color:var(--ocean-2);text-transform:uppercase;}
label .req{color:var(--accent);margin-left:2px;}
input[type="text"],input[type="email"],input[type="tel"],input[type="date"],input[type="number"],select,textarea{width:100%;padding:.7rem 1rem;background:rgba(255,255,255,.05);border:1px solid rgba(181,123,238,.18);border-radius:var(--radius-sm);font-family:var(--font-body);font-size:.9rem;font-weight:400;color:var(--white);transition:border-color var(--transition),box-shadow var(--transition);outline:none;-webkit-appearance:none;}
input:focus,select:focus,textarea:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(181,123,238,.15);}
select option{background:var(--ocean-6);}
textarea{resize:vertical;min-height:90px;}

.focus-grid{display:grid;grid-template-columns:1fr 1fr;gap:.8rem;}
.focus-check{display:flex;align-items:flex-start;gap:.75rem;background:rgba(181,123,238,.05);border:1px solid rgba(181,123,238,.13);border-radius:var(--radius-sm);padding:.85rem 1rem;cursor:pointer;transition:border-color var(--transition),background var(--transition);}
.focus-check:has(input:checked){border-color:var(--accent);background:rgba(181,123,238,.12);}
.focus-check input[type="checkbox"]{width:16px;height:16px;accent-color:var(--accent);cursor:pointer;flex-shrink:0;margin-top:2px;}
.focus-check-text{display:flex;flex-direction:column;gap:.2rem;}
.focus-check-label{font-size:.85rem;font-weight:600;color:var(--ocean-1);}
.focus-check-desc{font-size:.75rem;font-weight:300;color:var(--ocean-3);line-height:1.45;}

.format-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:.7rem;}
.format-radio{display:flex;flex-direction:column;align-items:center;gap:.4rem;background:rgba(181,123,238,.05);border:1px solid rgba(181,123,238,.14);border-radius:var(--radius-sm);padding:.9rem .8rem;cursor:pointer;text-align:center;transition:border-color var(--transition),background var(--transition);}
.format-radio:has(input:checked){border-color:var(--accent);background:rgba(181,123,238,.12);}
.format-radio input[type="radio"]{display:none;}
.format-radio .fmt-icon{font-size:1.5rem;}
.format-radio .fmt-name{font-size:.78rem;font-weight:600;color:var(--ocean-1);letter-spacing:.05em;text-transform:uppercase;}
.format-radio .fmt-desc{font-size:.68rem;font-weight:300;color:var(--ocean-3);line-height:1.4;}

.psycho-note{background:rgba(181,123,238,.08);border:1px solid rgba(181,123,238,.18);border-left:3px solid var(--accent);border-radius:var(--radius-sm);padding:.85rem 1rem;font-size:.82rem;font-weight:300;color:var(--ocean-2);line-height:1.6;margin-top:.5rem;}
.psycho-note strong{color:var(--accent);font-weight:600;}

.alert{border-radius:var(--radius-sm);padding:1rem 1.2rem;margin-bottom:1.5rem;font-size:.88rem;font-weight:500;line-height:1.6;}
.alert-error{background:rgba(255,77,109,.1);border:1px solid rgba(255,77,109,.3);color:#ff8fa3;}
.alert-error ul{padding-left:1.2rem;}
.alert-success{background:rgba(0,214,143,.1);border:1px solid rgba(0,214,143,.3);color:var(--success);text-align:center;padding:2rem;}
.alert-success h3{font-family:var(--font-display);font-size:1.3rem;margin-bottom:.5rem;}
.booking-ref{display:inline-block;margin-top:.8rem;background:rgba(0,214,143,.15);border:1px solid rgba(0,214,143,.3);border-radius:100px;padding:.35rem 1rem;font-family:var(--font-display);font-size:1rem;letter-spacing:.15em;color:var(--success);}

.payment-info{display:flex;align-items:center;justify-content:space-between;background:rgba(181,123,238,.07);border:1px solid rgba(181,123,238,.2);border-radius:var(--radius-sm);padding:1rem 1.2rem;margin-bottom:1.5rem;}
.payment-label{font-size:.8rem;font-weight:600;letter-spacing:.1em;color:var(--ocean-2);text-transform:uppercase;}
.payment-amount{font-family:var(--font-display);font-size:1.6rem;font-weight:700;color:var(--accent);}

.btn-submit{width:100%;padding:1rem;margin-top:1.5rem;background:linear-gradient(135deg,var(--accent-2),var(--accent));border:none;border-radius:var(--radius-sm);font-family:var(--font-display);font-size:.95rem;font-weight:700;letter-spacing:.15em;color:var(--white);text-transform:uppercase;cursor:pointer;transition:opacity var(--transition),transform var(--transition),box-shadow var(--transition);box-shadow:0 4px 18px rgba(139,70,212,.3);}
.btn-submit:hover{opacity:.9;transform:translateY(-2px);box-shadow:0 8px 28px rgba(139,70,212,.45);}
.btn{display:inline-flex;align-items:center;gap:.5rem;padding:.75rem 1.8rem;border-radius:100px;font-family:var(--font-body);font-size:.88rem;font-weight:600;letter-spacing:.08em;cursor:pointer;text-decoration:none;transition:all var(--transition);border:none;}
.btn-primary{background:linear-gradient(135deg,var(--accent-2),var(--accent));color:var(--white);box-shadow:0 4px 18px rgba(139,70,212,.25);}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(139,70,212,.4);}
.btn-outline{background:transparent;border:1px solid rgba(181,123,238,.4);color:var(--accent);}
.btn-outline:hover{background:rgba(181,123,238,.08);}

@keyframes fadeUp{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:translateY(0)}}
.hero-content > *{opacity:0;animation:fadeUp .7s ease forwards;}
.hero-badge{animation-delay:.1s;}.hero-title{animation-delay:.22s;}.hero-sub{animation-delay:.34s;}.hero-pills{animation-delay:.44s;}.hero-actions{animation-delay:.54s;}

@media(max-width:768px){.hero{grid-template-columns:1fr;}.hero-visual{display:none;}.hero-content{padding:3.5rem 1.5rem 2.5rem;}nav{padding:1rem 1.5rem;}.section{padding:3rem 1.5rem;}.form-grid{grid-template-columns:1fr;}.form-group.span-2{grid-column:span 1;}.form-body{padding:1.5rem 1.2rem;}.focus-grid{grid-template-columns:1fr;}.format-grid{grid-template-columns:1fr;}}
footer{position:relative;z-index:1;text-align:center;padding:2.5rem;border-top:1px solid rgba(181,123,238,.08);font-size:.78rem;font-weight:300;color:var(--ocean-3);letter-spacing:.06em;}
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
    <div class="hero-badge">Sport Psychology &nbsp;·&nbsp; Mind Training</div>
    <h1 class="hero-title">Mental<span>Conditioning</span></h1>
    <p class="hero-sub">Train the mind as hard as the body. Our sport psychology program equips swimmers with the mental tools to perform under pressure, manage nerves, and unlock consistent race-day excellence.</p>
    <div class="hero-pills">
      <span class="pill">Visualisation</span>
      <span class="pill">Stress Control</span>
      <span class="pill">Race Readiness</span>
      <span class="pill">Resilience</span>
    </div>
    <div class="hero-actions">
      <a href="#enroll" class="btn btn-primary">Enroll Now →</a>
      <a href="#about"  class="btn btn-outline">Learn More</a>
    </div>
  </div>
  <div class="hero-visual">
    <div class="hero-icon-wrap">
      <svg viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="100" cy="100" r="60" stroke="rgba(181,123,238,.3)" stroke-width="1.5" stroke-dasharray="5 4"/>
        <circle cx="100" cy="100" r="42" stroke="rgba(181,123,238,.2)" stroke-width="1"/>
        <!-- brain shape approximation -->
        <path d="M80 80 Q65 68 70 55 Q75 42 88 46 Q92 38 100 40 Q108 38 112 46 Q125 42 130 55 Q135 68 120 80" stroke="#b57bee" stroke-width="3.5" stroke-linecap="round" fill="none"/>
        <path d="M80 80 Q78 95 82 108 Q86 120 100 124 Q114 120 118 108 Q122 95 120 80" stroke="#b57bee" stroke-width="3.5" stroke-linecap="round" fill="none"/>
        <path d="M88 46 Q88 60 80 80" stroke="#b57bee" stroke-width="2.5" stroke-linecap="round"/>
        <path d="M112 46 Q112 60 120 80" stroke="#b57bee" stroke-width="2.5" stroke-linecap="round"/>
        <path d="M78 90 Q88 86 100 88 Q112 86 122 90" stroke="#b57bee" stroke-width="2.5" stroke-linecap="round"/>
        <!-- spark lines -->
        <line x1="100" y1="25" x2="100" y2="35" stroke="#8b46d4" stroke-width="2" stroke-linecap="round"/>
        <line x1="130" y1="35" x2="124" y2="42" stroke="#8b46d4" stroke-width="2" stroke-linecap="round"/>
        <line x1="70"  y1="35" x2="76"  y2="42" stroke="#8b46d4" stroke-width="2" stroke-linecap="round"/>
        <circle cx="100" cy="100" r="3" fill="#b57bee"/>
      </svg>
    </div>
  </div>
</section>

<!-- ── About ─────────────────────────────────────────────────────────────── -->
<div id="about" class="section">
  <p class="section-label">Program Overview</p>
  <h2 class="section-title">The Psychology of Peak Performance</h2>
  <div class="section-divider"></div>
  <div class="highlights-grid">
    <div class="highlight-card"><span class="highlight-icon">🧘</span><p class="highlight-title">Visualisation</p><p class="highlight-desc">Guided imagery sessions that train the brain to experience winning performances before race day, building neural pathways for success.</p></div>
    <div class="highlight-card"><span class="highlight-icon">🌡️</span><p class="highlight-title">Stress Control</p><p class="highlight-desc">Evidence-based breathing, relaxation, and arousal-regulation techniques to manage nerves and channel energy productively.</p></div>
    <div class="highlight-card"><span class="highlight-icon">🏁</span><p class="highlight-title">Race Readiness</p><p class="highlight-desc">Pre-race routines, focus anchors, and warm-up protocols designed to ensure you arrive on the blocks in the optimal mental state.</p></div>
    <div class="highlight-card"><span class="highlight-icon">🛡️</span><p class="highlight-title">Resilience</p><p class="highlight-desc">Frameworks for bouncing back from poor performances, handling external pressure, and building long-term mental toughness.</p></div>
    <div class="highlight-card"><span class="highlight-icon">🔗</span><p class="highlight-title">Integrated Option</p><p class="highlight-desc">Optionally link this program to your swimming squad or elite coaching plan for fully synchronised physical and mental periodisation.</p></div>
    <div class="highlight-card"><span class="highlight-icon">👥</span><p class="highlight-title">Group & 1-on-1</p><p class="highlight-desc">Choose group workshops for shared experience and team cohesion, or individual one-on-one sessions for targeted, private coaching.</p></div>
  </div>
</div>

<!-- ── Enrollment Form ──────────────────────────────────────────────────── -->
<div id="enroll" class="section" style="padding-bottom:5rem;">
  <p class="section-label">Get Started</p>
  <h2 class="section-title">Enrollment Form</h2>
  <div class="section-divider"></div>

  <?php if ($success): ?>
  <div class="alert alert-success">
    <h3>🧠 Enrollment Received!</h3>
    <p>Your Mental Conditioning enrollment is confirmed. Our sport psychologist will be in touch within 48 hours to schedule your first session.</p>
    <div class="booking-ref"><?= htmlspecialchars($booking_ref) ?></div>
    <br><br>
    <a href="index.php" class="btn btn-primary">Back to Dashboard</a>
  </div>

  <?php elseif (!isset($_SESSION['user_id'])): ?>
  <div class="form-card">
    <div class="login-gate">
      <p>You must be logged in to enroll in a program.</p>
      <a href="login.php?redirect=mental.php#enroll" class="btn btn-primary">Login to Enroll →</a>
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
      <div class="form-section-head"><span class="step-num">1</span> Who is this program for?</div>
      <div class="form-body">
        <div class="toggle-group">
          <button type="button" class="toggle-btn" id="btn-self"  onclick="setForSelf(1)">Myself</button>
          <button type="button" class="toggle-btn" id="btn-other" onclick="setForSelf(0)">Someone Else</button>
        </div>
        <input type="hidden" name="is_for_self" id="is_for_self" value="<?= (int)($p['is_for_self'] ?? 1) ?>">

        <div id="relation-row" style="display:none;margin-bottom:1.2rem;">
          <div class="form-group">
            <label>Your Relationship to the Participant <span class="req">*</span></label>
            <select name="relation">
              <option value="">— Select —</option>
              <?php foreach (['parent','guardian','spouse','agent','coach','other'] as $r): ?>
              <option value="<?= $r ?>" <?= ($p['relation']??'')===$r?'selected':'' ?>><?= ucfirst($r) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-grid">
          <div class="form-group span-2">
            <label>Participant's Full Name <span class="req">*</span></label>
            <input type="text" name="swimmer_name" placeholder="First and last name"
                   value="<?= htmlspecialchars($p['swimmer_name'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Date of Birth <span class="req">*</span></label>
            <input type="date" name="swimmer_dob" value="<?= htmlspecialchars($p['swimmer_dob'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Emergency Contact <span class="req">*</span></label>
            <input type="text" name="emergency_contact" placeholder="Name and phone number"
                   value="<?= htmlspecialchars($p['emergency_contact'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="swimmer_email" placeholder="participant@email.com"
                   value="<?= htmlspecialchars($p['swimmer_email'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Phone Number</label>
            <input type="tel" name="swimmer_phone" placeholder="+91 98765 43210"
                   value="<?= htmlspecialchars($p['swimmer_phone'] ?? '') ?>">
          </div>
          <div class="form-group span-2">
            <label>Additional Notes</label>
            <textarea name="medical_notes" placeholder="Any relevant health, psychological, or performance background…"><?= htmlspecialchars($p['medical_notes'] ?? '') ?></textarea>
          </div>
        </div>
      </div>
    </div>

    <!-- STEP 2 -->
    <div class="form-card" style="margin-bottom:1.5rem;">
      <div class="form-section-head"><span class="step-num">2</span> Program Preferences</div>
      <div class="form-body">
        <div class="form-grid">

          <div class="form-group span-2">
            <label style="margin-bottom:.7rem;">Delivery Format <span class="req">*</span></label>
            <div class="format-grid">
              <?php
              $formats = [
                'workshop'   => ['🎓', 'Group Workshop', 'Shared sessions with team members'],
                'integrated' => ['🔗', 'Integrated',     'Embedded into your swim programme'],
                'one_on_one' => ['👤', 'One-on-One',     'Private sessions, maximum focus'],
              ];
              foreach ($formats as $val => [$icon, $name, $desc]):
              ?>
              <label class="format-radio">
                <input type="radio" name="delivery_format" value="<?= $val ?>" <?= ($p['delivery_format']??'')===$val?'checked':'' ?>>
                <span class="fmt-icon"><?= $icon ?></span>
                <span class="fmt-name"><?= $name ?></span>
                <span class="fmt-desc"><?= $desc ?></span>
              </label>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="form-group">
            <label>Sessions per Week</label>
            <select name="sessions_per_week">
              <?php for ($i = 1; $i <= 4; $i++): ?>
              <option value="<?= $i ?>" <?= (($p['sessions_per_week']??1)==$i)?'selected':'' ?>>
                <?= $i ?> session<?= $i > 1 ? 's' : '' ?> / week
              </option>
              <?php endfor; ?>
            </select>
          </div>

          <div class="form-group">
            <label>Linked Swimming Program</label>
            <select name="linked_program">
              <option value="">— None / Standalone —</option>
              <?php foreach ([
                'junior_development' => 'Junior Development',
                'competitive_squad'  => 'Competitive Squad',
                'elite_coaching'     => 'Elite Coaching',
                'adult_fitness_swim' => 'Adult Fitness Swim',
                'masters_program'    => 'Masters Program',
              ] as $v => $l): ?>
              <option value="<?= $v ?>" <?= ($p['linked_program']??'')===$v?'selected':'' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group span-2">
            <label style="margin-bottom:.7rem;">Focus Areas <span class="req">*</span></label>
            <div class="focus-grid">
              <label class="focus-check">
                <input type="checkbox" name="focus_visualisation" value="1" <?= !empty($p['focus_visualisation'])?'checked':'' ?>>
                <div class="focus-check-text">
                  <span class="focus-check-label">🧘 Visualisation</span>
                  <span class="focus-check-desc">Mental rehearsal and imagery techniques for peak performance execution.</span>
                </div>
              </label>
              <label class="focus-check">
                <input type="checkbox" name="focus_stress_control" value="1" <?= !empty($p['focus_stress_control'])?'checked':'' ?>>
                <div class="focus-check-text">
                  <span class="focus-check-label">🌡️ Stress Control</span>
                  <span class="focus-check-desc">Breathing, relaxation, and arousal-management protocols.</span>
                </div>
              </label>
              <label class="focus-check">
                <input type="checkbox" name="focus_race_readiness" value="1" <?= !empty($p['focus_race_readiness'])?'checked':'' ?>>
                <div class="focus-check-text">
                  <span class="focus-check-label">🏁 Race Readiness</span>
                  <span class="focus-check-desc">Pre-race routines, focus cues, and meet-day preparation frameworks.</span>
                </div>
              </label>
              <label class="focus-check">
                <input type="checkbox" name="focus_resilience" value="1" <?= !empty($p['focus_resilience'])?'checked':'' ?>>
                <div class="focus-check-text">
                  <span class="focus-check-label">🛡️ Resilience Building</span>
                  <span class="focus-check-desc">Bounce-back frameworks, pressure handling, and long-term mental toughness.</span>
                </div>
              </label>
            </div>
          </div>

          <div class="form-group span-2">
            <label>Other / Custom Focus Area</label>
            <textarea name="custom_focus" placeholder="Any other mental skills, specific challenges, or goals you'd like the psychologist to address…"><?= htmlspecialchars($p['custom_focus'] ?? '') ?></textarea>
            <p class="psycho-note"><strong>Confidential:</strong> All information shared in this form and in sessions is treated with strict professional confidentiality in line with sport psychology ethics codes.</p>
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
            <p class="payment-label">Mental Conditioning — Enrollment Fee</p>
            <p style="font-size:.78rem;color:var(--ocean-3);margin-top:.2rem;">Initial assessment included · session fees invoiced separately</p>
          </div>
          <div class="payment-amount">INR 120</div>
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