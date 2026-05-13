<?php
// Program_elite_coaching.php
session_start();
require_once 'config/db.php';

$errors      = [];
$success     = false;
$booking_ref = '';

// ── POST handler ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php?redirect=Program_elite_coaching.php');
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
    $skill_level            = trim($_POST['skill_level']            ?? '');
    $video_analysis         = isset($_POST['video_analysis'])        ? 1 : 0;
    $drills_custom          = isset($_POST['drills_custom'])         ? 1 : 0;
    $drills_notes           = trim($_POST['drills_notes']           ?? '');
    $performance_strategy   = trim($_POST['performance_strategy']   ?? '');
    $preferred_schedule     = trim($_POST['preferred_schedule']     ?? '');
    $goals                  = trim($_POST['goals']                  ?? '');

    if (!$skill_level) $errors[] = "Please select your current level.";
    if (!$goals)       $errors[] = "Please describe your goals — this helps us assign the right head coach.";

    // ── Payment ───────────────────────────────────────────────────────────
    $payment_method    = trim($_POST['payment_method']    ?? '');
    $payment_amount    = 380.00;
    $payment_reference = trim($_POST['payment_reference'] ?? '');

    if (!$payment_method) $errors[] = "Payment method is required.";

    // ── Insert ────────────────────────────────────────────────────────────
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO prog_elite_coaching
                    (user_id, is_for_self, relation,
                     swimmer_full_name, swimmer_dob, swimmer_email, swimmer_phone,
                     emergency_contact, medical_notes,
                     skill_level, video_analysis, drills_custom, drills_notes,
                     performance_strategy, preferred_schedule, goals,
                     created_at)
                VALUES (?, ?, ?,
                        ?, ?, ?, ?,
                        ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?,
                        NOW())
            ");
            $stmt->execute([
                $user_id, $is_for_self, $relation ?: null,
                $swimmer_name, $swimmer_dob, $swimmer_email ?: null, $swimmer_phone ?: null,
                $emergency_contact, $medical_notes ?: null,
                $skill_level, $video_analysis, $drills_custom, $drills_notes ?: null,
                $performance_strategy ?: null, $preferred_schedule ?: null, $goals,
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
                        'elite_coaching', ?,
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
<title>Elite Coaching — Darken Shadows Swimming Club</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700;900&family=Raleway:ital,wght@0,300;0,400;0,500;0,600;1,300&display=swap" rel="stylesheet">
<style>
:root {
  --ocean-1:#e8f4fd; --ocean-2:#b8ddf5; --ocean-3:#6db8e8;
  --ocean-4:#2a8cc4; --ocean-5:#1565a0; --ocean-6:#0d3d6b; --ocean-7:#061e35;
  --accent:#c8a8f8; --accent-2:#8b5cf6; --accent-3:#e0d4ff;
  --white:#ffffff; --error:#ff4d6d; --success:#00d68f;
  --font-display:'Cinzel',serif; --font-body:'Raleway',sans-serif;
  --radius:12px; --radius-sm:6px;
  --shadow:0 8px 40px rgba(6,30,53,.6);
  --transition:.25s cubic-bezier(.4,0,.2,1);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html{scroll-behavior:smooth;}
body{font-family:var(--font-body);background:var(--ocean-7);color:var(--ocean-1);min-height:100vh;overflow-x:hidden;}
body::before{content:'';position:fixed;inset:0;background:radial-gradient(ellipse 65% 50% at 10% 5%,rgba(139,92,246,.14) 0%,transparent 60%),radial-gradient(ellipse 55% 65% at 92% 92%,rgba(200,168,248,.08) 0%,transparent 55%),radial-gradient(ellipse 80% 80% at 50% 40%,rgba(6,30,53,1) 30%,var(--ocean-6) 100%);z-index:0;pointer-events:none;}

nav{position:sticky;top:0;z-index:100;display:flex;align-items:center;justify-content:space-between;padding:1rem 2.5rem;background:rgba(6,30,53,.88);backdrop-filter:blur(20px);border-bottom:1px solid rgba(200,168,248,.12);}
.nav-logo{font-family:var(--font-display);font-size:1.05rem;font-weight:700;letter-spacing:.14em;color:var(--accent);text-decoration:none;text-transform:uppercase;}
.nav-links{display:flex;gap:1.8rem;align-items:center;}
.nav-links a{font-size:.82rem;font-weight:600;letter-spacing:.08em;color:var(--ocean-2);text-decoration:none;text-transform:uppercase;transition:color var(--transition);}
.nav-links a:hover{color:var(--accent);}
.nav-cta{background:linear-gradient(135deg,var(--accent-2),var(--accent));color:var(--white)!important;padding:.45rem 1.2rem;border-radius:100px;font-size:.78rem!important;}

.hero{position:relative;z-index:1;display:grid;grid-template-columns:1fr 1fr;min-height:560px;overflow:hidden;}
.hero-content{padding:5.5rem 3rem 4rem 5vw;display:flex;flex-direction:column;justify-content:center;gap:1.5rem;position:relative;}
.hero-content::before{content:'';position:absolute;left:calc(5vw - 1.5rem);top:20%;bottom:20%;width:1px;background:linear-gradient(180deg,transparent,var(--accent-2),transparent);}
.hero-badge{display:inline-flex;align-items:center;gap:.6rem;background:rgba(139,92,246,.12);border:1px solid rgba(200,168,248,.25);border-radius:100px;padding:.38rem 1.1rem;font-size:.7rem;font-weight:600;letter-spacing:.18em;color:var(--accent);text-transform:uppercase;width:fit-content;}
.hero-badge::before{content:'';width:6px;height:6px;border-radius:50%;background:var(--accent);animation:pulse 2.5s ease-in-out infinite;}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.35;transform:scale(1.5)}}
.hero-title{font-family:var(--font-display);font-size:clamp(2.5rem,4.2vw,3.6rem);font-weight:900;line-height:1.05;color:var(--white);letter-spacing:.03em;}
.hero-title span{display:block;background:linear-gradient(135deg,var(--accent-3),var(--accent),var(--accent-2));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.hero-sub{font-size:1rem;font-weight:300;line-height:1.75;color:var(--ocean-2);max-width:430px;}
.hero-pills{display:flex;gap:.6rem;flex-wrap:wrap;}
.pill{background:rgba(139,92,246,.1);border:1px solid rgba(200,168,248,.2);border-radius:100px;padding:.28rem .85rem;font-size:.72rem;font-weight:600;letter-spacing:.1em;color:var(--accent-3);text-transform:uppercase;}
.hero-actions{display:flex;gap:1rem;flex-wrap:wrap;margin-top:.2rem;}
.hero-visual{position:relative;overflow:hidden;background:linear-gradient(160deg,#0a1628 0%,#0d1f3c 50%,#0a0e1f 100%);}
.hero-visual::before{content:'';position:absolute;inset:0;background-image:radial-gradient(circle at 1px 1px,rgba(200,168,248,.08) 1px,transparent 0);background-size:28px 28px;}
.hero-visual::after{content:'';position:absolute;inset:0;background:radial-gradient(ellipse 70% 70% at 50% 50%,rgba(139,92,246,.1) 0%,transparent 70%);}
.hero-icon-wrap{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;z-index:2;}
.hero-icon-wrap svg{width:220px;height:220px;filter:drop-shadow(0 0 48px rgba(139,92,246,.6));}
.orb{position:absolute;border-radius:50%;filter:blur(40px);pointer-events:none;z-index:1;}
.orb-1{width:160px;height:160px;background:rgba(139,92,246,.2);top:10%;right:10%;animation:float 7s ease-in-out infinite;}
.orb-2{width:100px;height:100px;background:rgba(200,168,248,.12);bottom:20%;left:15%;animation:float 9s ease-in-out infinite reverse;}
@keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-14px)}}

.section{position:relative;z-index:1;padding:4.5rem 5vw;max-width:1200px;margin:0 auto;}
.section-label{font-size:.7rem;font-weight:600;letter-spacing:.22em;color:var(--accent);text-transform:uppercase;margin-bottom:.6rem;}
.section-title{font-family:var(--font-display);font-size:clamp(1.7rem,2.5vw,2.3rem);font-weight:700;color:var(--white);line-height:1.2;margin-bottom:1rem;}
.section-divider{width:50px;height:1px;background:linear-gradient(90deg,var(--accent-2),transparent);margin-bottom:1.8rem;}

.highlights-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1.2rem;margin-top:2.5rem;}
.highlight-card{background:rgba(139,92,246,.05);border:1px solid rgba(200,168,248,.1);border-radius:var(--radius);padding:1.6rem 1.3rem;transition:transform var(--transition),border-color var(--transition),box-shadow var(--transition);position:relative;overflow:hidden;}
.highlight-card::before{content:'';position:absolute;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,var(--accent-2),transparent);opacity:0;transition:opacity var(--transition);}
.highlight-card:hover{transform:translateY(-5px);border-color:rgba(200,168,248,.28);box-shadow:0 16px 40px rgba(139,92,246,.15);}
.highlight-card:hover::before{opacity:1;}
.highlight-icon{font-size:1.8rem;margin-bottom:.8rem;display:block;}
.highlight-title{font-family:var(--font-display);font-size:.82rem;font-weight:600;color:var(--accent);letter-spacing:.12em;text-transform:uppercase;margin-bottom:.5rem;}
.highlight-desc{font-size:.82rem;font-weight:300;color:var(--ocean-2);line-height:1.65;}

.form-card{background:rgba(10,14,31,.55);border:1px solid rgba(200,168,248,.12);border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow);backdrop-filter:blur(8px);}
.login-gate{text-align:center;padding:4.5rem 2rem;}
.login-gate p{font-size:1rem;color:var(--ocean-2);margin-bottom:1.5rem;font-weight:300;}
.form-section-head{background:rgba(139,92,246,.08);border-bottom:1px solid rgba(200,168,248,.12);padding:.95rem 1.8rem;font-family:var(--font-display);font-size:.76rem;font-weight:600;letter-spacing:.17em;color:var(--accent);text-transform:uppercase;display:flex;align-items:center;gap:.7rem;}
.form-section-head .step-num{width:22px;height:22px;border-radius:50%;background:linear-gradient(135deg,var(--accent-2),var(--accent));display:flex;align-items:center;justify-content:center;font-size:.68rem;font-weight:700;color:var(--white);flex-shrink:0;}
.form-body{padding:2rem 1.8rem;}

.toggle-group{display:flex;border-radius:var(--radius-sm);overflow:hidden;border:1px solid rgba(200,168,248,.18);width:fit-content;margin-bottom:2rem;}
.toggle-btn{padding:.65rem 1.6rem;font-family:var(--font-body);font-size:.85rem;font-weight:600;letter-spacing:.06em;cursor:pointer;border:none;background:transparent;color:var(--ocean-2);transition:background var(--transition),color var(--transition);}
.toggle-btn.active{background:linear-gradient(135deg,var(--accent-2),var(--accent));color:var(--white);}

.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;}
.form-group{display:flex;flex-direction:column;gap:.45rem;}
.form-group.span-2{grid-column:span 2;}
label{font-size:.75rem;font-weight:600;letter-spacing:.09em;color:var(--ocean-2);text-transform:uppercase;}
label .req{color:var(--accent);margin-left:2px;}
input[type="text"],input[type="email"],input[type="tel"],input[type="date"],input[type="number"],select,textarea{width:100%;padding:.72rem 1rem;background:rgba(255,255,255,.04);border:1px solid rgba(200,168,248,.15);border-radius:var(--radius-sm);font-family:var(--font-body);font-size:.9rem;font-weight:400;color:var(--white);transition:border-color var(--transition),box-shadow var(--transition);outline:none;-webkit-appearance:none;}
input:focus,select:focus,textarea:focus{border-color:var(--accent-2);box-shadow:0 0 0 3px rgba(139,92,246,.18);}
select option{background:#0a0e1f;}
textarea{resize:vertical;min-height:100px;}

.feature-grid{display:grid;grid-template-columns:1fr 1fr;gap:.8rem;}
.feature-check{display:flex;align-items:flex-start;gap:.75rem;background:rgba(139,92,246,.06);border:1px solid rgba(200,168,248,.12);border-radius:var(--radius-sm);padding:.85rem 1rem;cursor:pointer;transition:border-color var(--transition),background var(--transition);}
.feature-check:has(input:checked){border-color:var(--accent-2);background:rgba(139,92,246,.12);}
.feature-check input[type="checkbox"]{width:16px;height:16px;accent-color:var(--accent-2);cursor:pointer;flex-shrink:0;margin-top:2px;}
.feature-check-text{display:flex;flex-direction:column;gap:.2rem;}
.feature-check-label{font-size:.85rem;font-weight:600;color:var(--ocean-1);}
.feature-check-desc{font-size:.75rem;font-weight:300;color:var(--ocean-3);line-height:1.45;}

.coach-note{background:rgba(139,92,246,.08);border:1px solid rgba(200,168,248,.15);border-left:3px solid var(--accent-2);border-radius:var(--radius-sm);padding:.85rem 1rem;font-size:.82rem;font-weight:300;color:var(--ocean-2);line-height:1.6;margin-top:.5rem;}
.coach-note strong{color:var(--accent);font-weight:600;}

.alert{border-radius:var(--radius-sm);padding:1rem 1.2rem;margin-bottom:1.5rem;font-size:.88rem;font-weight:500;line-height:1.6;}
.alert-error{background:rgba(255,77,109,.08);border:1px solid rgba(255,77,109,.28);color:#ff8fa3;}
.alert-error ul{padding-left:1.2rem;}
.alert-success{background:rgba(0,214,143,.08);border:1px solid rgba(0,214,143,.28);color:var(--success);text-align:center;padding:2.5rem;}
.alert-success h3{font-family:var(--font-display);font-size:1.4rem;margin-bottom:.6rem;}
.booking-ref{display:inline-block;margin-top:.9rem;background:rgba(0,214,143,.12);border:1px solid rgba(0,214,143,.28);border-radius:100px;padding:.38rem 1.2rem;font-family:var(--font-display);font-size:1rem;letter-spacing:.18em;color:var(--success);}

.payment-info{display:flex;align-items:center;justify-content:space-between;background:rgba(139,92,246,.08);border:1px solid rgba(200,168,248,.15);border-radius:var(--radius-sm);padding:1.1rem 1.3rem;margin-bottom:1.5rem;}
.payment-label{font-size:.78rem;font-weight:600;letter-spacing:.1em;color:var(--ocean-2);text-transform:uppercase;}
.payment-sub{font-size:.72rem;color:var(--ocean-3);margin-top:.2rem;}
.payment-amount{font-family:var(--font-display);font-size:1.7rem;font-weight:700;color:var(--accent);}

.btn-submit{width:100%;padding:1.05rem;margin-top:1.5rem;background:linear-gradient(135deg,var(--accent-2),var(--accent));border:none;border-radius:var(--radius-sm);font-family:var(--font-display);font-size:.92rem;font-weight:700;letter-spacing:.16em;color:var(--white);text-transform:uppercase;cursor:pointer;transition:opacity var(--transition),transform var(--transition),box-shadow var(--transition);box-shadow:0 4px 20px rgba(139,92,246,.35);}
.btn-submit:hover{opacity:.9;transform:translateY(-2px);box-shadow:0 10px 32px rgba(139,92,246,.5);}
.btn{display:inline-flex;align-items:center;gap:.5rem;padding:.75rem 1.9rem;border-radius:100px;font-family:var(--font-body);font-size:.88rem;font-weight:600;letter-spacing:.08em;cursor:pointer;text-decoration:none;transition:all var(--transition);border:none;}
.btn-primary{background:linear-gradient(135deg,var(--accent-2),var(--accent));color:var(--white);box-shadow:0 4px 18px rgba(139,92,246,.3);}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 10px 28px rgba(139,92,246,.45);}
.btn-outline{background:transparent;border:1px solid rgba(200,168,248,.35);color:var(--accent);}
.btn-outline:hover{background:rgba(139,92,246,.08);}

@keyframes fadeUp{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:translateY(0)}}
.hero-content > *{opacity:0;animation:fadeUp .7s ease forwards;}
.hero-badge{animation-delay:.08s;}.hero-title{animation-delay:.2s;}.hero-sub{animation-delay:.32s;}.hero-pills{animation-delay:.42s;}.hero-actions{animation-delay:.52s;}

@media(max-width:768px){.hero{grid-template-columns:1fr;}.hero-visual{display:none;}.hero-content{padding:3.5rem 1.5rem 2.5rem;}.hero-content::before{display:none;}nav{padding:1rem 1.5rem;}.section{padding:3rem 1.5rem;}.form-grid{grid-template-columns:1fr;}.form-group.span-2{grid-column:span 1;}.form-body{padding:1.5rem 1.2rem;}.feature-grid{grid-template-columns:1fr;}}

footer{position:relative;z-index:1;text-align:center;padding:2.5rem;border-top:1px solid rgba(200,168,248,.07);font-size:.78rem;font-weight:300;color:var(--ocean-3);letter-spacing:.06em;}
</style>
</head>
<body>

<nav>
  <a href="index.php" class="nav-logo">Darken Shadows SC</a>
  <div class="nav-links">
    <a href="index.php">Dashboard</a>
    <a href="#enroll" class="nav-cta">Apply Now</a>
    <?php if (isset($_SESSION['user_id'])): ?><a href="logout.php">Logout</a><?php else: ?><a href="login.php">Login</a><?php endif; ?>
  </div>
</nav>

<section class="hero">
  <div class="hero-content">
    <div class="hero-badge">1-on-1 · Head Coaches · By Application</div>
    <h1 class="hero-title">Elite<span>Coaching</span></h1>
    <p class="hero-sub">The pinnacle of personalised training. One coach, one swimmer, one mission. Video-analysed technique, bespoke performance strategy, and direct access to our most experienced head coaches.</p>
    <div class="hero-pills">
      <span class="pill">Video Analysis</span>
      <span class="pill">Custom Drills</span>
      <span class="pill">Head Coaches</span>
      <span class="pill">Performance Strategy</span>
    </div>
    <div class="hero-actions">
      <a href="#enroll" class="btn btn-primary">Apply Now →</a>
      <a href="#about"  class="btn btn-outline">Learn More</a>
    </div>
  </div>
  <div class="hero-visual">
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="hero-icon-wrap">
      <svg viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="100" cy="100" r="70" stroke="rgba(200,168,248,.25)" stroke-width="1" stroke-dasharray="4 4"/>
        <circle cx="100" cy="100" r="50" stroke="rgba(200,168,248,.18)" stroke-width="1"/>
        <circle cx="100" cy="68" r="14" stroke="#c8a8f8" stroke-width="3.5"/>
        <path d="M88 82 Q80 105 75 120" stroke="#c8a8f8" stroke-width="3.5" stroke-linecap="round"/>
        <path d="M112 82 Q120 105 125 120" stroke="#c8a8f8" stroke-width="3.5" stroke-linecap="round"/>
        <path d="M75 100 Q100 92 125 100" stroke="#c8a8f8" stroke-width="3.5" stroke-linecap="round"/>
        <line x1="100" y1="18" x2="100" y2="30" stroke="#8b5cf6" stroke-width="2" stroke-linecap="round"/>
        <line x1="100" y1="170" x2="100" y2="182" stroke="#8b5cf6" stroke-width="2" stroke-linecap="round"/>
        <line x1="18" y1="100" x2="30" y2="100" stroke="#8b5cf6" stroke-width="2" stroke-linecap="round"/>
        <line x1="170" y1="100" x2="182" y2="100" stroke="#8b5cf6" stroke-width="2" stroke-linecap="round"/>
        <line x1="36" y1="36" x2="45" y2="45" stroke="#8b5cf6" stroke-width="2" stroke-linecap="round"/>
        <line x1="155" y1="155" x2="164" y2="164" stroke="#8b5cf6" stroke-width="2" stroke-linecap="round"/>
        <line x1="164" y1="36" x2="155" y2="45" stroke="#8b5cf6" stroke-width="2" stroke-linecap="round"/>
        <line x1="45" y1="155" x2="36" y2="164" stroke="#8b5cf6" stroke-width="2" stroke-linecap="round"/>
        <circle cx="100" cy="100" r="4" fill="#c8a8f8"/>
      </svg>
    </div>
  </div>
</section>

<div id="about" class="section">
  <p class="section-label">Program Overview</p>
  <h2 class="section-title">What Makes Elite Different</h2>
  <div class="section-divider"></div>
  <div class="highlights-grid">
    <div class="highlight-card"><span class="highlight-icon">🎥</span><p class="highlight-title">Video Analysis</p><p class="highlight-desc">Every session filmed from multiple angles. Frame-by-frame stroke breakdown with annotated feedback delivered after each session.</p></div>
    <div class="highlight-card"><span class="highlight-icon">🧬</span><p class="highlight-title">Bespoke Drills</p><p class="highlight-desc">No generic sets. Your coach designs drills specifically targeting your individual technique inefficiencies and race demands.</p></div>
    <div class="highlight-card"><span class="highlight-icon">👤</span><p class="highlight-title">Head Coaches Only</p><p class="highlight-desc">Exclusive access to our senior coaching staff — athletes with national and international coaching credentials.</p></div>
    <div class="highlight-card"><span class="highlight-icon">📋</span><p class="highlight-title">Performance Strategy</p><p class="highlight-desc">A written performance plan updated monthly — training loads, recovery windows, race strategy, and long-term periodisation.</p></div>
    <div class="highlight-card"><span class="highlight-icon">🔄</span><p class="highlight-title">Continuous Feedback</p><p class="highlight-desc">Direct coach contact between sessions. Questions answered, plans adjusted, and real-time guidance whenever you need it.</p></div>
    <div class="highlight-card"><span class="highlight-icon">🧠</span><p class="highlight-title">Mind & Body</p><p class="highlight-desc">Optional pairing with our Mental Conditioning program — visualisation, pre-race routines, and resilience frameworks built in.</p></div>
  </div>
</div>

<div id="enroll" class="section" style="padding-bottom:5rem;">
  <p class="section-label">Apply</p>
  <h2 class="section-title">Elite Coaching Application</h2>
  <div class="section-divider"></div>

  <?php if ($success): ?>
  <div class="alert alert-success">
    <h3>✨ Application Received</h3>
    <p>Your Elite Coaching application is under review. A head coach will contact you within 48 hours to schedule an initial assessment session.</p>
    <div class="booking-ref"><?= htmlspecialchars($booking_ref) ?></div>
    <br><br>
    <a href="index.php" class="btn btn-primary">Back to Dashboard</a>
  </div>

  <?php elseif (!isset($_SESSION['user_id'])): ?>
  <div class="form-card">
    <div class="login-gate">
      <p>You must be logged in to apply for Elite Coaching.</p>
      <a href="login.php?redirect=Program_elite_coaching.php#enroll" class="btn btn-primary">Login to Apply →</a>
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
      <div class="form-section-head"><span class="step-num">1</span> Who is this coaching for?</div>
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
              <?php foreach (['parent','guardian','spouse','agent','coach','other'] as $r): ?>
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
            <label>Medical / Physical Notes</label>
            <textarea name="medical_notes" placeholder="Any injuries, physical limitations, or conditions your coach must be aware of…"><?= htmlspecialchars($p['medical_notes'] ?? '') ?></textarea>
          </div>
        </div>
      </div>
    </div>

    <!-- STEP 2 -->
    <div class="form-card" style="margin-bottom:1.5rem;">
      <div class="form-section-head"><span class="step-num">2</span> Coaching Preferences</div>
      <div class="form-body">
        <div class="form-grid">
          <div class="form-group">
            <label>Current Level <span class="req">*</span></label>
            <select name="skill_level">
              <option value="">— Select —</option>
              <?php foreach (['advanced'=>'Advanced Club Swimmer','regional'=>'Regional / County Level','national'=>'National Level','elite'=>'Elite / International','professional'=>'Professional Athlete'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= ($p['skill_level']??'')===$v?'selected':'' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Preferred Schedule</label>
            <select name="preferred_schedule">
              <option value="">— Select —</option>
              <?php foreach (['early_morning'=>'Early Morning (5–8 AM)','morning'=>'Morning (8–12 PM)','afternoon'=>'Afternoon (12–5 PM)','evening'=>'Evening (5–9 PM)','flexible'=>'Flexible — coach to decide'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= ($p['preferred_schedule']??'')===$v?'selected':'' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group span-2">
            <label style="margin-bottom:.7rem;">Session Features</label>
            <div class="feature-grid">
              <label class="feature-check">
                <input type="checkbox" name="video_analysis" value="1" <?= !empty($p['video_analysis'])?'checked':'' ?>>
                <div class="feature-check-text">
                  <span class="feature-check-label">🎥 Video Analysis</span>
                  <span class="feature-check-desc">Underwater &amp; above-water filming with annotated stroke feedback after every session.</span>
                </div>
              </label>
              <label class="feature-check">
                <input type="checkbox" name="drills_custom" value="1" <?= !empty($p['drills_custom'])?'checked':'' ?>>
                <div class="feature-check-text">
                  <span class="feature-check-label">🧬 Custom Drills Program</span>
                  <span class="feature-check-desc">Individualised drill sets designed around your specific technique gaps and race demands.</span>
                </div>
              </label>
            </div>
          </div>

          <div class="form-group span-2">
            <label>Specific Drills / Technique Areas</label>
            <textarea name="drills_notes" placeholder="e.g. underwater dolphin kick, breaststroke pullout, freestyle catch, flip-turn mechanics…"><?= htmlspecialchars($p['drills_notes'] ?? '') ?></textarea>
          </div>

          <div class="form-group span-2">
            <label>Performance Strategy Notes</label>
            <textarea name="performance_strategy" placeholder="Describe your strengths, weaknesses, race strategy challenges, or anything for planning…"><?= htmlspecialchars($p['performance_strategy'] ?? '') ?></textarea>
          </div>

          <div class="form-group span-2">
            <label>Goals &amp; Aspirations <span class="req">*</span></label>
            <textarea name="goals" placeholder="Target competitions, qualifying times, technique milestones, or long-term athletic ambitions…"><?= htmlspecialchars($p['goals'] ?? '') ?></textarea>
            <p class="coach-note"><strong>Why this matters:</strong> Your head coach is assigned based on your goals and discipline. The more detail you provide, the better the match we can make for you.</p>
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
            <p class="payment-label">Elite 1-on-1 Coaching — Application Fee</p>
            <p class="payment-sub">Includes initial assessment session · monthly retainer invoiced separately</p>
          </div>
          <div class="payment-amount">INR 380</div>
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
        <button type="submit" class="btn-submit">Submit Application →</button>
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