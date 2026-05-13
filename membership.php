<?php
// membership.php — Membership Plans
session_start();
require_once 'config/db.php';

$errors      = [];
$success     = false;
$booking_ref = '';

// ── Membership plan definitions ───────────────────────────────────────────────
$plans = [
    'bronze' => [
        'name'        => 'Bronze',
        'tagline'     => 'The first stroke',
        'price'       => 499,
        'color'       => '#cd7f32',
        'color_bg'    => 'rgba(205,127,50,.1)',
        'color_border'=> 'rgba(205,127,50,.3)',
        'icon'        => '🥉',
        'features'    => [
            'Access to 25m warm-up pool',
            '3 coached sessions / week',
            'Locker & changing suite access',
            'Club newsletter & event updates',
            'Basic progress tracking',
        ],
        'not_included'=> [
            'Competition pool access',
            'Dryland gym access',
            'Video analysis',
            'Guest passes',
        ],
    ],
    'silver' => [
        'name'        => 'Silver',
        'tagline'     => 'Steady momentum',
        'price'       => 999,
        'color'       => '#a8a9ad',
        'color_bg'    => 'rgba(168,169,173,.1)',
        'color_border'=> 'rgba(168,169,173,.3)',
        'icon'        => '🥈',
        'features'    => [
            'Full 50m competition pool access',
            '5 coached sessions / week',
            'Dryland gym access',
            'Locker & premium changing suite',
            'Monthly performance report',
            '2 guest passes / month',
        ],
        'not_included'=> [
            'Video analysis',
            'Personal coach assignment',
            'Priority lane booking',
        ],
    ],
    'gold' => [
        'name'        => 'Gold',
        'tagline'     => 'Chase the podium',
        'price'       => 1799,
        'color'       => '#d4af37',
        'color_bg'    => 'rgba(212,175,55,.1)',
        'color_border'=> 'rgba(212,175,55,.35)',
        'icon'        => '🥇',
        'popular'     => true,
        'features'    => [
            'Unlimited pool access (50m + 25m)',
            'Unlimited coached sessions',
            'Dryland gym & recovery centre',
            'Underwater video analysis (monthly)',
            'Personal coach assignment',
            'Priority lane booking',
            '4 guest passes / month',
            'Quarterly competition entry support',
        ],
        'not_included'=> [
            'Dedicated 1-on-1 head coach',
            'Sport psychology sessions',
        ],
    ],
    'platinum' => [
        'name'        => 'Platinum',
        'tagline'     => 'No limits',
        'price'       => 2999,
        'color'       => '#e5e4e2',
        'color_bg'    => 'rgba(229,228,226,.08)',
        'color_border'=> 'rgba(229,228,226,.35)',
        'icon'        => '💎',
        'features'    => [
            'Everything in Gold',
            'Dedicated head coach (1-on-1)',
            'Weekly video analysis sessions',
            'Full Mental Conditioning access',
            'Physio assessment & recovery priority',
            'Unlimited guest passes',
            'VIP poolside locker suite',
            'Meet entry covered (club events)',
            'Annual performance review with MD',
        ],
        'not_included'=> [],
    ],
];

// ── POST handler ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php?redirect=membership.php');
        exit;
    }

    $user_id = (int)$_SESSION['user_id'];

    // ── Fields ────────────────────────────────────────────────────────────
    $plan_key          = trim($_POST['plan']             ?? '');
    $full_name         = trim($_POST['full_name']        ?? '');
    $dob               = trim($_POST['dob']              ?? '');
    $phone             = trim($_POST['phone']            ?? '');
    $email             = trim($_POST['email']            ?? '');
    $emergency_contact = trim($_POST['emergency_contact']?? '');
    $medical_notes     = trim($_POST['medical_notes']    ?? '');
    $payment_method    = trim($_POST['payment_method']   ?? '');
    $payment_reference = trim($_POST['payment_reference']?? '');
    $start_date        = trim($_POST['start_date']       ?? '');
    $duration_months   = max(1, min(12, (int)($_POST['duration_months'] ?? 1)));
    $auto_renew        = isset($_POST['auto_renew']) ? 1 : 0;

    // ── Validation ────────────────────────────────────────────────────────
    if (!array_key_exists($plan_key, $plans)) $errors[] = 'Please select a membership plan.';
    if (!$full_name)         $errors[] = 'Full name is required.';
    if (!$dob)               $errors[] = 'Date of birth is required.';
    if (!$emergency_contact) $errors[] = 'Emergency contact is required.';
    if (!$payment_method)    $errors[] = 'Payment method is required.';
    if (!$start_date)        $errors[] = 'Preferred start date is required.';

    // Validate start_date not in the past
    if ($start_date && strtotime($start_date) < strtotime('today'))
        $errors[] = 'Start date cannot be in the past.';

    if (empty($errors)) {
        $plan          = $plans[$plan_key];
        $payment_amount = $plan['price'] * $duration_months;
        $end_date       = date('Y-m-d', strtotime($start_date . " +{$duration_months} months"));

        try {
            $pdo->beginTransaction();

            // 1 — membership record
            $stmt = $pdo->prepare("
                INSERT INTO memberships
                    (user_id, plan, full_name, dob, phone, email,
                     emergency_contact, medical_notes,
                     start_date, end_date, duration_months, auto_renew,
                     payment_amount, payment_method, payment_reference,
                     status, created_at)
                VALUES (?, ?, ?, ?, ?, ?,
                        ?, ?,
                        ?, ?, ?, ?,
                        ?, ?, ?,
                        'pending', NOW())
            ");
            $stmt->execute([
                $user_id, $plan_key, $full_name, $dob,
                $phone ?: null, $email ?: null,
                $emergency_contact, $medical_notes ?: null,
                $start_date, $end_date, $duration_months, $auto_renew,
                $payment_amount, $payment_method, $payment_reference ?: null,
            ]);
            $membership_id = (int)$pdo->lastInsertId();

            // 2 — master bookings row (program_record_id = membership id)
            $stmt2 = $pdo->prepare("
              INSERT INTO bookings
                  (booked_by_user_id, is_for_self,
                  swimmer_name, swimmer_dob,
                  swimmer_phone, swimmer_email,
                  swimmer_emergency_contact, swimmer_medical_notes,
                  program, program_record_id,
                  payment_amount, payment_method, payment_reference, payment_status,
                  start_date, end_date,
                  status, created_at)
              VALUES (?, 1,
                      ?, ?,
                      ?, ?,
                      ?, ?,
                      'membership', ?,
                      ?, ?, ?, 'pending',
                      ?, ?,
                      'pending', NOW())
          ");
          $stmt2->execute([
              $user_id,
              $full_name, $dob,
              $phone ?: null, $email ?: null,
              $emergency_contact, $medical_notes ?: null,
              $membership_id,
              $payment_amount, $payment_method, $payment_reference ?: null,
              $start_date, $end_date,
          ]);

            $booking_id = (int)$pdo->lastInsertId();
            $ref = $pdo->prepare("SELECT booking_reference FROM bookings WHERE id = ?");
            $ref->execute([$booking_id]);
            $booking_ref = $ref->fetchColumn();

            $pdo->commit();
            $success = true;

        } catch (PDOException $e) {
            $pdo->rollBack();
            // If memberships table doesn't exist yet, run the CREATE and retry
            if ($e->getCode() == '42S02') {
                // Table doesn't exist — it will be created via the SQL block below
                $errors[] = 'Please run the membership SQL migration first (see pool.sql addition).';
            } else {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

$p            = $_POST;
$selected_plan = $p['plan'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Membership — Darken Shadows Swimming Club</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700;900&family=Raleway:ital,wght@0,300;0,400;0,500;0,600;1,300&display=swap" rel="stylesheet">
<style>
:root {
  --ocean-1:#e8f4fd; --ocean-2:#b8ddf5; --ocean-3:#6db8e8;
  --ocean-4:#2a8cc4; --ocean-5:#1565a0; --ocean-6:#0d3d6b; --ocean-7:#061e35;
  --white:#ffffff; --error:#ff4d6d; --success:#00d68f;
  --font-display:'Cinzel',serif; --font-body:'Raleway',sans-serif;
  --radius:12px; --radius-sm:6px;
  --shadow:0 8px 40px rgba(6,30,53,.55);
  --transition:.25s cubic-bezier(.4,0,.2,1);

  /* tier accent vars (overridden per card) */
  --tier-color: #a8a9ad;
  --tier-bg: rgba(168,169,173,.1);
  --tier-border: rgba(168,169,173,.3);
}

*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html{scroll-behavior:smooth;}
body{font-family:var(--font-body);background:var(--ocean-7);color:var(--ocean-1);min-height:100vh;overflow-x:hidden;}

/* Background */
body::before{
  content:'';position:fixed;inset:0;
  background:
    radial-gradient(ellipse 75% 55% at 10% 5%, rgba(42,140,196,.2) 0%, transparent 58%),
    radial-gradient(ellipse 55% 65% at 90% 92%, rgba(21,101,160,.18) 0%, transparent 55%),
    radial-gradient(ellipse 100% 100% at 50% 50%, var(--ocean-7) 38%, var(--ocean-6) 100%);
  z-index:0;pointer-events:none;
}

/* subtle grid dots */
body::after{
  content:'';position:fixed;inset:0;z-index:0;
  background-image:radial-gradient(circle at 1px 1px, rgba(109,184,232,.04) 1px, transparent 0);
  background-size:32px 32px;pointer-events:none;
}

/* ── Nav ──────────────────────────────────────────────────────────────────── */
nav{
  position:sticky;top:0;z-index:100;
  display:flex;align-items:center;justify-content:space-between;
  padding:1rem 2.5rem;
  background:rgba(6,30,53,.85);
  backdrop-filter:blur(20px);
  border-bottom:1px solid rgba(109,184,232,.14);
}
.nav-logo{
  font-family:var(--font-display);font-size:1.05rem;font-weight:700;
  letter-spacing:.14em;color:var(--ocean-3);text-decoration:none;text-transform:uppercase;
}
.nav-links{display:flex;gap:1.8rem;align-items:center;}
.nav-links a{
  font-size:.82rem;font-weight:600;letter-spacing:.08em;
  color:var(--ocean-2);text-decoration:none;text-transform:uppercase;
  transition:color var(--transition);
}
.nav-links a:hover,.nav-links a.active{color:var(--ocean-3);}
.nav-cta{
  background:linear-gradient(135deg,var(--ocean-5),var(--ocean-4));
  color:var(--white)!important;padding:.45rem 1.2rem;
  border-radius:100px;font-size:.78rem!important;
}
.nav-user{font-size:.82rem;color:rgba(184,221,245,.6);}
.nav-user span{color:var(--ocean-2);font-weight:600;}

/* ── Hero ─────────────────────────────────────────────────────────────────── */
.hero{
  position:relative;z-index:1;
  text-align:center;padding:5.5rem 2rem 3.5rem;
}
.hero-eyebrow{
  font-size:.7rem;font-weight:700;letter-spacing:.32em;
  text-transform:uppercase;color:var(--ocean-3);
  margin-bottom:1rem;opacity:0;animation:fadeUp .6s .1s ease forwards;
}
.hero h1{
  font-family:var(--font-display);
  font-size:clamp(2.5rem,5.5vw,4.2rem);
  font-weight:900;line-height:1.07;letter-spacing:.04em;
  color:var(--white);margin-bottom:1.2rem;
  opacity:0;animation:fadeUp .6s .24s ease forwards;
}
.hero h1 em{
  color:var(--ocean-3);font-style:normal;
  display:block;
}
.hero-sub{
  font-size:1rem;font-weight:300;line-height:1.8;
  color:var(--ocean-2);max-width:520px;margin:0 auto 2rem;
  opacity:0;animation:fadeUp .6s .38s ease forwards;
}
.hero-line{
  width:72px;height:2px;margin:0 auto;
  background:linear-gradient(90deg,transparent,var(--ocean-3),transparent);
  opacity:0;animation:fadeUp .6s .5s ease forwards;
}

/* ── Plans grid ───────────────────────────────────────────────────────────── */
.plans-section{position:relative;z-index:1;padding:3.5rem 3vw 2rem;max-width:1260px;margin:0 auto;}
.plans-grid{
  display:grid;
  grid-template-columns:repeat(4,1fr);
  gap:1.2rem;
  align-items:start;
}

/* ── Plan card ────────────────────────────────────────────────────────────── */
.plan-card{
  background:rgba(13,61,107,.35);
  border:1px solid rgba(109,184,232,.18);
  border-radius:16px;
  padding:2rem 1.6rem 1.8rem;
  cursor:pointer;
  position:relative;
  overflow:hidden;
  transition:transform var(--transition),border-color var(--transition),box-shadow var(--transition);
  backdrop-filter:blur(10px);
  opacity:0;
  animation:fadeUp .55s forwards;
}
.plan-card:nth-child(1){animation-delay:.15s;}
.plan-card:nth-child(2){animation-delay:.25s;}
.plan-card:nth-child(3){animation-delay:.35s;}
.plan-card:nth-child(4){animation-delay:.45s;}

.plan-card::before{
  content:'';position:absolute;top:0;left:0;right:0;height:3px;
  background:var(--tier-color);
  border-radius:16px 16px 0 0;
  opacity:.7;
}
.plan-card:hover{
  transform:translateY(-8px);
  box-shadow:0 24px 60px rgba(0,0,0,.4),0 0 0 1px var(--tier-border);
}
.plan-card.selected{
  border-color:var(--tier-color);
  box-shadow:0 0 0 2px var(--tier-color),0 20px 50px rgba(0,0,0,.35);
}
.plan-card.popular-card{
  border-color:rgba(212,175,55,.4);
}

.popular-badge{
  display:inline-block;
  background:rgba(212,175,55,.2);
  border:1px solid rgba(212,175,55,.5);
  color:#f5d060;
  font-size:.62rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;
  padding:.26rem .8rem;border-radius:100px;
  margin-bottom:1rem;
}

.tier-icon{font-size:2rem;margin-bottom:.9rem;display:block;}

.tier-tag{
  font-size:.63rem;font-weight:700;letter-spacing:.22em;
  text-transform:uppercase;color:var(--tier-color);
  margin-bottom:.35rem;
}

.tier-name{
  font-family:var(--font-display);
  font-size:1.35rem;font-weight:700;
  color:var(--white);
  margin-bottom:.25rem;letter-spacing:.04em;
}

.tier-tagline{
  font-size:.82rem;font-weight:300;
  color:var(--ocean-2);margin-bottom:1.4rem;
}

.tier-price-wrap{
  display:flex;align-items:baseline;gap:.3rem;
  margin-bottom:1.5rem;padding-bottom:1.4rem;
  border-bottom:1px solid rgba(109,184,232,.12);
}
.tier-currency{font-size:.9rem;font-weight:600;color:var(--tier-color);}
.tier-price{
  font-family:var(--font-display);
  font-size:2.2rem;font-weight:900;
  color:var(--tier-color);line-height:1;
}
.tier-per{font-size:.75rem;font-weight:400;color:var(--ocean-3);}

.feature-list{list-style:none;display:flex;flex-direction:column;gap:.55rem;margin-bottom:1.2rem;}
.feature-list li{
  display:flex;align-items:flex-start;gap:.6rem;
  font-size:.83rem;font-weight:400;color:var(--ocean-1);
  line-height:1.4;
}
.feature-list li .check{color:var(--tier-color);flex-shrink:0;margin-top:1px;font-size:.9rem;}

.not-included-list{list-style:none;display:flex;flex-direction:column;gap:.4rem;margin-bottom:1.4rem;}
.not-included-list li{
  display:flex;align-items:flex-start;gap:.6rem;
  font-size:.78rem;font-weight:300;color:rgba(184,221,245,.4);
  line-height:1.4;text-decoration:line-through;
}
.not-included-list li .cross{color:rgba(184,221,245,.25);flex-shrink:0;font-size:.9rem;}

.btn-select{
  width:100%;padding:.75rem;
  background:var(--tier-bg);
  border:1px solid var(--tier-border);
  border-radius:var(--radius-sm);
  font-family:var(--font-display);
  font-size:.8rem;font-weight:600;letter-spacing:.14em;
  color:var(--tier-color);text-transform:uppercase;
  cursor:pointer;
  transition:background var(--transition),box-shadow var(--transition),transform var(--transition);
}
.btn-select:hover{
  background:rgba(109,184,232,.12);
  box-shadow:0 4px 16px rgba(0,0,0,.25);
  transform:translateY(-1px);
}
.plan-card.selected .btn-select{
  background:var(--tier-color);
  color:var(--ocean-7);
  font-weight:700;
}

/* ── Compare note ─────────────────────────────────────────────────────────── */
.compare-note{
  position:relative;z-index:1;
  text-align:center;padding:.5rem 2rem 3rem;
  font-size:.8rem;font-weight:300;color:var(--ocean-3);
}
.compare-note a{color:var(--ocean-3);text-decoration:underline;cursor:pointer;}

/* ── Enrollment form area ─────────────────────────────────────────────────── */
.enroll-section{
  position:relative;z-index:1;
  max-width:820px;margin:0 auto;
  padding:0 2rem 6rem;
}
.section-label{font-size:.72rem;font-weight:600;letter-spacing:.2em;color:var(--ocean-3);text-transform:uppercase;margin-bottom:.6rem;}
.section-title{font-family:var(--font-display);font-size:clamp(1.5rem,2.5vw,2rem);font-weight:700;color:var(--white);line-height:1.2;margin-bottom:.6rem;}
.section-divider{width:50px;height:2px;background:linear-gradient(90deg,var(--ocean-3),transparent);margin-bottom:2rem;}

/* selected plan summary pill */
.plan-summary{
  display:flex;align-items:center;justify-content:space-between;
  background:rgba(13,61,107,.5);
  border:1px solid rgba(109,184,232,.25);
  border-radius:var(--radius);padding:1rem 1.4rem;
  margin-bottom:1.5rem;
  backdrop-filter:blur(10px);
}
.plan-summary-left{display:flex;align-items:center;gap:.9rem;}
.plan-summary-icon{font-size:1.6rem;}
.plan-summary-name{
  font-family:var(--font-display);font-size:1rem;font-weight:700;color:var(--white);
}
.plan-summary-sub{font-size:.78rem;color:var(--ocean-2);margin-top:.1rem;}
.plan-summary-price{
  font-family:var(--font-display);font-size:1.4rem;font-weight:900;
  color:var(--ocean-3);
}

/* form cards */
.form-card{
  background:rgba(255,255,255,.04);
  border:1px solid rgba(109,184,232,.14);
  border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow);
  margin-bottom:1.5rem;
}
.form-card:last-child{margin-bottom:0;}
.form-section-head{
  background:rgba(42,140,196,.07);
  border-bottom:1px solid rgba(109,184,232,.13);
  padding:.95rem 1.8rem;
  display:flex;align-items:center;gap:.7rem;
  font-family:var(--font-display);font-size:.76rem;font-weight:600;
  letter-spacing:.17em;color:var(--ocean-3);text-transform:uppercase;
}
.step-num{
  width:22px;height:22px;border-radius:50%;
  background:linear-gradient(135deg,var(--ocean-5),var(--ocean-4));
  display:flex;align-items:center;justify-content:center;
  font-size:.68rem;font-weight:700;color:var(--white);flex-shrink:0;
}
.form-body{padding:2rem 1.8rem;}

.login-gate{text-align:center;padding:4rem 2rem;}
.login-gate p{font-size:1rem;color:var(--ocean-2);margin-bottom:1.5rem;font-weight:300;}

.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;}
.form-group{display:flex;flex-direction:column;gap:.45rem;}
.form-group.span-2{grid-column:span 2;}
label{font-size:.75rem;font-weight:600;letter-spacing:.09em;color:var(--ocean-2);text-transform:uppercase;}
label .req{color:var(--ocean-3);margin-left:2px;}

input[type="text"],input[type="email"],input[type="tel"],
input[type="date"],input[type="number"],select,textarea{
  width:100%;padding:.72rem 1rem;
  background:rgba(255,255,255,.05);
  border:1px solid rgba(109,184,232,.18);
  border-radius:var(--radius-sm);
  font-family:var(--font-body);font-size:.9rem;font-weight:400;
  color:var(--white);
  transition:border-color var(--transition),box-shadow var(--transition);
  outline:none;-webkit-appearance:none;
}
input:focus,select:focus,textarea:focus{
  border-color:var(--ocean-4);
  box-shadow:0 0 0 3px rgba(42,140,196,.18);
}
select option{background:var(--ocean-6);}
textarea{resize:vertical;min-height:90px;}

/* duration grid */
.duration-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:.6rem;}
.dur-radio{
  display:flex;flex-direction:column;align-items:center;gap:.3rem;
  background:rgba(255,255,255,.04);border:1px solid rgba(109,184,232,.15);
  border-radius:var(--radius-sm);padding:.75rem .5rem;
  cursor:pointer;text-align:center;
  transition:border-color var(--transition),background var(--transition);
}
.dur-radio:has(input:checked){
  border-color:var(--ocean-4);background:rgba(42,140,196,.12);
}
.dur-radio input[type="radio"]{display:none;}
.dur-months{font-family:var(--font-display);font-size:1.1rem;font-weight:700;color:var(--white);}
.dur-label{font-size:.68rem;font-weight:500;color:var(--ocean-3);text-transform:uppercase;letter-spacing:.08em;}
.dur-saving{font-size:.62rem;font-weight:600;color:#4fc3f7;margin-top:.1rem;}

/* total price display */
.price-calc{
  display:flex;align-items:center;justify-content:space-between;
  background:rgba(42,140,196,.08);
  border:1px solid rgba(109,184,232,.2);
  border-radius:var(--radius-sm);
  padding:1rem 1.3rem;margin-bottom:1.5rem;
}
.price-calc-label{font-size:.78rem;font-weight:600;letter-spacing:.1em;color:var(--ocean-2);text-transform:uppercase;}
.price-calc-sub{font-size:.72rem;color:var(--ocean-3);margin-top:.2rem;}
.price-calc-total{font-family:var(--font-display);font-size:1.8rem;font-weight:700;color:var(--ocean-3);}

.opt-row{
  display:flex;align-items:center;gap:.75rem;
  background:rgba(255,255,255,.04);
  border:1px solid rgba(109,184,232,.14);
  border-radius:var(--radius-sm);padding:.75rem 1rem;cursor:pointer;
  transition:border-color var(--transition),background var(--transition);
}
.opt-row:has(input:checked){border-color:var(--ocean-4);background:rgba(42,140,196,.09);}
.opt-row input[type="checkbox"]{width:16px;height:16px;accent-color:var(--ocean-4);cursor:pointer;}
.opt-row span{font-size:.88rem;font-weight:500;color:var(--ocean-1);}

/* Alerts */
.alert{border-radius:var(--radius-sm);padding:1rem 1.2rem;margin-bottom:1.5rem;font-size:.88rem;font-weight:500;line-height:1.6;}
.alert-error{background:rgba(255,77,109,.1);border:1px solid rgba(255,77,109,.3);color:#ff8fa3;}
.alert-error ul{padding-left:1.2rem;}
.alert-success{background:rgba(0,214,143,.1);border:1px solid rgba(0,214,143,.3);color:var(--success);text-align:center;padding:2.5rem 2rem;}
.alert-success h3{font-family:var(--font-display);font-size:1.4rem;margin-bottom:.6rem;}
.booking-ref{display:inline-block;margin-top:.9rem;background:rgba(0,214,143,.12);border:1px solid rgba(0,214,143,.28);border-radius:100px;padding:.38rem 1.2rem;font-family:var(--font-display);font-size:1rem;letter-spacing:.18em;color:var(--success);}

/* Submit button */
.btn-submit{
  width:100%;padding:1.05rem;margin-top:1.5rem;
  background:linear-gradient(135deg,var(--ocean-5),var(--ocean-4));
  border:none;border-radius:var(--radius-sm);
  font-family:var(--font-display);font-size:.92rem;font-weight:700;
  letter-spacing:.16em;color:var(--white);text-transform:uppercase;
  cursor:pointer;
  transition:opacity var(--transition),transform var(--transition),box-shadow var(--transition);
  box-shadow:0 4px 20px rgba(42,140,196,.35);
}
.btn-submit:hover{opacity:.9;transform:translateY(-2px);box-shadow:0 10px 32px rgba(42,140,196,.5);}

.btn{display:inline-flex;align-items:center;gap:.5rem;padding:.75rem 1.9rem;border-radius:100px;font-family:var(--font-body);font-size:.88rem;font-weight:600;letter-spacing:.08em;cursor:pointer;text-decoration:none;transition:all var(--transition);border:none;}
.btn-primary{background:linear-gradient(135deg,var(--ocean-5),var(--ocean-4));color:var(--white);box-shadow:0 4px 18px rgba(42,140,196,.3);}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 10px 28px rgba(42,140,196,.45);}
.btn-outline{background:transparent;border:1px solid rgba(109,184,232,.4);color:var(--ocean-2);}
.btn-outline:hover{background:rgba(109,184,232,.08);}

/* no-plan banner */
.no-plan-banner{
  text-align:center;padding:2.5rem;
  background:rgba(42,140,196,.06);
  border:1px solid rgba(109,184,232,.15);
  border-radius:var(--radius);
  font-size:.95rem;color:var(--ocean-2);
  font-weight:300;line-height:1.7;
}
.no-plan-banner strong{color:var(--ocean-3);font-weight:600;}

/* Animations */
@keyframes fadeUp{from{opacity:0;transform:translateY(22px)}to{opacity:1;transform:translateY(0)}}

/* ── Responsive ───────────────────────────────────────────────────────────── */
@media(max-width:1024px){
  .plans-grid{grid-template-columns:repeat(2,1fr);}
}
@media(max-width:768px){
  nav{padding:1rem 1.5rem;}
  .nav-links{display:none;}
  .plans-grid{grid-template-columns:1fr;max-width:420px;margin:0 auto;}
  .plans-section{padding:2.5rem 1.5rem 1.5rem;}
  .form-grid{grid-template-columns:1fr;}
  .form-group.span-2{grid-column:span 1;}
  .form-body{padding:1.5rem 1.2rem;}
  .duration-grid{grid-template-columns:repeat(2,1fr);}
  .enroll-section{padding:0 1.2rem 4rem;}
}

footer{position:relative;z-index:1;text-align:center;padding:2.5rem;border-top:1px solid rgba(109,184,232,.08);font-size:.78rem;font-weight:300;color:var(--ocean-3);letter-spacing:.06em;}
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
    <a href="membership.php" class="active">Membership</a>
    <?php if (isset($_SESSION['user_id'])): ?>
      <a href="logout.php">Logout</a>
    <?php else: ?>
      <a href="login.php">Login</a>
    <?php endif; ?>
  </div>
  <?php if (isset($_SESSION['user_id'])): ?>
    <div class="nav-user">Welcome, <span><?= htmlspecialchars($_SESSION['user_name']) ?></span></div>
  <?php endif; ?>
</nav>

<!-- ── Hero ─────────────────────────────────────────────────────────────────── -->
<div class="hero">
  <p class="hero-eyebrow">Darken Shadows Swimming Club</p>
  <h1>Choose Your <em>Membership</em></h1>
  <p class="hero-sub">Four tiers built for every ambition — from your first lap to the national podium. Each plan gives you access to world-class facilities and expert coaching.</p>
  <div class="hero-line"></div>
</div>

<!-- ── Plans grid ──────────────────────────────────────────────────────────── -->
<div class="plans-section">
  <div class="plans-grid">

    <?php foreach ($plans as $key => $plan):
      $is_selected = ($selected_plan === $key);
    ?>
    <div class="plan-card <?= $is_selected ? 'selected' : '' ?> <?= !empty($plan['popular']) ? 'popular-card' : '' ?>"
         id="card-<?= $key ?>"
         style="--tier-color:<?= $plan['color'] ?>;--tier-bg:<?= $plan['color_bg'] ?>;--tier-border:<?= $plan['color_border'] ?>;"
         onclick="selectPlan('<?= $key ?>')">

      <?php if (!empty($plan['popular'])): ?>
        <div class="popular-badge">⭐ Most Popular</div>
      <?php endif; ?>

      <span class="tier-icon"><?= $plan['icon'] ?></span>
      <div class="tier-tag"><?= htmlspecialchars($plan['name']) ?> Tier</div>
      <div class="tier-name"><?= htmlspecialchars($plan['name']) ?></div>
      <div class="tier-tagline"><?= htmlspecialchars($plan['tagline']) ?></div>

      <div class="tier-price-wrap">
        <span class="tier-currency">₹</span>
        <span class="tier-price"><?= number_format($plan['price']) ?></span>
        <span class="tier-per">/ month</span>
      </div>

      <ul class="feature-list">
        <?php foreach ($plan['features'] as $feat): ?>
          <li><span class="check">✓</span><?= htmlspecialchars($feat) ?></li>
        <?php endforeach; ?>
      </ul>

      <?php if (!empty($plan['not_included'])): ?>
      <ul class="not-included-list">
        <?php foreach ($plan['not_included'] as $nf): ?>
          <li><span class="cross">✕</span><?= htmlspecialchars($nf) ?></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>

      <button type="button" class="btn-select" id="btn-<?= $key ?>">
        <?= $is_selected ? '✓ Selected' : 'Select Plan' ?>
      </button>

    </div>
    <?php endforeach; ?>

  </div><!-- /plans-grid -->

  <p class="compare-note" style="margin-top:1.4rem;">
    All memberships are subject to a one-time INR 50 registration admin fee &nbsp;·&nbsp;
    Cancel or upgrade any time &nbsp;·&nbsp;
    <a onclick="document.getElementById('enroll').scrollIntoView({behavior:'smooth'})">Jump to enrollment ↓</a>
  </p>
</div>

<!-- ── Enrollment form ─────────────────────────────────────────────────────── -->
<div id="enroll" class="enroll-section">
  <p class="section-label">Get Started</p>
  <h2 class="section-title">Membership Enrollment</h2>
  <div class="section-divider"></div>

  <?php if ($success): ?>

    <div class="alert alert-success">
      <h3>🎉 Welcome to Darken Shadows!</h3>
      <p>Your <strong><?= htmlspecialchars(ucfirst($p['plan'])) ?></strong> membership application has been received and is pending payment confirmation. We'll send your membership card and welcome pack to your registered email within 2 working days.</p>
      <div class="booking-ref"><?= htmlspecialchars($booking_ref) ?></div>
      <br><br>
      <a href="index.php" class="btn btn-primary">Go to Dashboard</a>
      &nbsp;
      <a href="program.php" class="btn btn-outline">Browse Programs</a>
    </div>

  <?php elseif (!isset($_SESSION['user_id'])): ?>

    <div class="form-card">
      <div class="login-gate">
        <p>You must be logged in to enroll in a membership plan.</p>
        <a href="login.php?redirect=membership.php#enroll" class="btn btn-primary">Login to Enroll →</a>
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

    <!-- No plan selected hint -->
    <div id="no-plan-msg" class="no-plan-banner" style="<?= $selected_plan ? 'display:none' : '' ?>">
      <strong>↑ Pick a plan above</strong> to continue — select Bronze, Silver, Gold or Platinum to unlock the enrollment form.
    </div>

    <form method="POST" action="#enroll" id="enrollForm" style="<?= $selected_plan ? '' : 'display:none' ?>">
      <input type="hidden" name="plan" id="plan-input" value="<?= htmlspecialchars($selected_plan) ?>">

      <!-- selected plan summary -->
      <?php if ($selected_plan && isset($plans[$selected_plan])): $sp = $plans[$selected_plan]; ?>
      <div class="plan-summary" id="plan-summary-bar"
           style="border-color:<?= $sp['color_border'] ?>;">
        <div class="plan-summary-left">
          <span class="plan-summary-icon"><?= $sp['icon'] ?></span>
          <div>
            <div class="plan-summary-name"><?= htmlspecialchars($sp['name']) ?> Membership</div>
            <div class="plan-summary-sub"><?= htmlspecialchars($sp['tagline']) ?> · ₹<?= number_format($sp['price']) ?> / month</div>
          </div>
        </div>
        <div class="plan-summary-price" id="total-display">₹<?= number_format($sp['price']) ?></div>
      </div>
      <?php else: ?>
      <div class="plan-summary" id="plan-summary-bar" style="display:none;">
        <div class="plan-summary-left">
          <span class="plan-summary-icon" id="sum-icon"></span>
          <div>
            <div class="plan-summary-name" id="sum-name"></div>
            <div class="plan-summary-sub" id="sum-sub"></div>
          </div>
        </div>
        <div class="plan-summary-price" id="total-display"></div>
      </div>
      <?php endif; ?>

      <!-- STEP 1 — Personal Details -->
      <div class="form-card">
        <div class="form-section-head">
          <span class="step-num">1</span> Personal Details
        </div>
        <div class="form-body">
          <div class="form-grid">
            <div class="form-group span-2">
              <label>Full Name <span class="req">*</span></label>
              <input type="text" name="full_name" placeholder="Your full name as on ID"
                     value="<?= htmlspecialchars($p['full_name'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label>Date of Birth <span class="req">*</span></label>
              <input type="date" name="dob" value="<?= htmlspecialchars($p['dob'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label>Emergency Contact <span class="req">*</span></label>
              <input type="text" name="emergency_contact" placeholder="Name and phone number"
                     value="<?= htmlspecialchars($p['emergency_contact'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label>Phone Number</label>
              <input type="tel" name="phone" placeholder="+91 98765 43210"
                     value="<?= htmlspecialchars($p['phone'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label>Email Address</label>
              <input type="email" name="email" placeholder="you@email.com"
                     value="<?= htmlspecialchars($p['email'] ?? '') ?>">
            </div>
            <div class="form-group span-2">
              <label>Medical / Physical Notes</label>
              <textarea name="medical_notes"
                        placeholder="Any conditions, injuries, or information the coaching team should know…"><?= htmlspecialchars($p['medical_notes'] ?? '') ?></textarea>
            </div>
          </div>
        </div>
      </div>

      <!-- STEP 2 — Duration & Start Date -->
      <div class="form-card">
        <div class="form-section-head">
          <span class="step-num">2</span> Duration &amp; Start Date
        </div>
        <div class="form-body">

          <div class="form-group" style="margin-bottom:1.4rem;">
            <label style="margin-bottom:.75rem;">Membership Duration <span class="req">*</span></label>
            <div class="duration-grid">
              <?php
              $durations = [
                1  => ['1 Month',   null],
                3  => ['3 Months',  'Save 5%'],
                6  => ['6 Months',  'Save 10%'],
                12 => ['12 Months', 'Save 15%'],
              ];
              $sel_dur = (int)($p['duration_months'] ?? 1);
              foreach ($durations as $months => [$label, $saving]):
              ?>
              <label class="dur-radio">
                <input type="radio" name="duration_months" value="<?= $months ?>"
                       <?= $sel_dur === $months ? 'checked' : '' ?>
                       onchange="updateTotal()">
                <span class="dur-months"><?= $months ?></span>
                <span class="dur-label"><?= $label ?></span>
                <?php if ($saving): ?><span class="dur-saving"><?= $saving ?></span><?php endif; ?>
              </label>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="form-grid">
            <div class="form-group">
              <label>Preferred Start Date <span class="req">*</span></label>
              <input type="date" name="start_date"
                     min="<?= date('Y-m-d') ?>"
                     value="<?= htmlspecialchars($p['start_date'] ?? date('Y-m-d')) ?>">
            </div>
            <div class="form-group" style="justify-content:flex-end;">
              <label style="margin-bottom:.6rem;">Auto-Renewal</label>
              <label class="opt-row">
                <input type="checkbox" name="auto_renew" value="1"
                       <?= !empty($p['auto_renew']) ? 'checked' : '' ?>>
                <span>🔄 Auto-renew membership at end of term</span>
              </label>
            </div>
          </div>
        </div>
      </div>

      <!-- STEP 3 — Payment -->
      <div class="form-card">
        <div class="form-section-head">
          <span class="step-num">3</span> Payment
        </div>
        <div class="form-body">

          <div class="price-calc">
            <div>
              <p class="price-calc-label" id="calc-label">Membership Fee</p>
              <p class="price-calc-sub" id="calc-sub">Select duration above to see total</p>
            </div>
            <div class="price-calc-total" id="calc-total">
              ₹<?= isset($plans[$selected_plan]) ? number_format($plans[$selected_plan]['price'] * ($sel_dur ?? 1)) : '—' ?>
            </div>
          </div>

          <div class="form-grid">
            <div class="form-group">
              <label>Payment Method <span class="req">*</span></label>
              <select name="payment_method">
                <option value="">— Select —</option>
                <?php foreach (['cash'=>'Cash at Reception','bank_transfer'=>'Bank Transfer','online'=>'Online Payment','cheque'=>'Cheque','card'=>'Credit / Debit Card'] as $v => $l): ?>
                <option value="<?= $v ?>" <?= ($p['payment_method']??'')===$v?'selected':'' ?>><?= $l ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Payment / Transaction Reference</label>
              <input type="text" name="payment_reference" placeholder="Transaction or receipt number"
                     value="<?= htmlspecialchars($p['payment_reference'] ?? '') ?>">
            </div>
          </div>

          <button type="submit" class="btn-submit">Confirm Membership →</button>
        </div>
      </div>

    </form>
  <?php endif; ?>
</div>

<footer>&copy; <?= date('Y') ?> Darken Shadows Swimming Club &nbsp;·&nbsp; All Rights Reserved</footer>

<!-- ══════════════════════════════════════════════════════════════════════════
     SQL migration note (add to pool.sql before using this page):

     CREATE TABLE IF NOT EXISTS memberships (
         id                 INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
         user_id            INT UNSIGNED    NOT NULL,
         plan               ENUM('bronze','silver','gold','platinum') NOT NULL,
         full_name          VARCHAR(120)    NOT NULL,
         dob                DATE            NOT NULL,
         phone              VARCHAR(20)     DEFAULT NULL,
         email              VARCHAR(180)    DEFAULT NULL,
         emergency_contact  VARCHAR(120)    NOT NULL,
         medical_notes      TEXT            DEFAULT NULL,
         start_date         DATE            NOT NULL,
         end_date           DATE            NOT NULL,
         duration_months    TINYINT         NOT NULL DEFAULT 1,
         auto_renew         TINYINT(1)      NOT NULL DEFAULT 0,
         payment_amount     DECIMAL(9,2)    NOT NULL,
         payment_method     ENUM('cash','bank_transfer','online','cheque','card') DEFAULT NULL,
         payment_reference  VARCHAR(100)    DEFAULT NULL,
         status             ENUM('pending','active','paused','expired','cancelled') NOT NULL DEFAULT 'pending',
         created_at         TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
         updated_at         TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
         CONSTRAINT fk_mem_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

     Also add 'membership' to the bookings.program ENUM:
     ALTER TABLE bookings MODIFY COLUMN program ENUM(
         'junior_development','competitive_squad','elite_coaching',
         'adult_fitness_swim','mental_conditioning','masters_program','membership'
     ) NOT NULL;
══════════════════════════════════════════════════════════════════════════ -->

<script>
// ── Plan data (mirrored from PHP) ────────────────────────────────────────────
const planData = <?php echo json_encode(array_map(function($k, $v) {
    return [
        'key'      => $k,
        'name'     => $v['name'],
        'tagline'  => $v['tagline'],
        'icon'     => $v['icon'],
        'price'    => $v['price'],
        'color'    => $v['color'],
        'colorBg'  => $v['color_bg'],
        'colorBorder' => $v['color_border'],
    ];
}, array_keys($plans), $plans)); ?>;

const planMap = {};
planData.forEach(p => planMap[p.key] = p);

let currentPlan = '<?= addslashes($selected_plan) ?>';

// ── Select plan ───────────────────────────────────────────────────────────────
function selectPlan(key) {
    currentPlan = key;
    const plan = planMap[key];

    // Update all cards
    document.querySelectorAll('.plan-card').forEach(card => {
        card.classList.toggle('selected', card.id === 'card-' + key);
    });
    document.querySelectorAll('.btn-select').forEach(btn => {
        btn.textContent = btn.id === 'btn-' + key ? '✓ Selected' : 'Select Plan';
    });

    // Set hidden input
    document.getElementById('plan-input').value = key;

    // Show form
    document.getElementById('enrollForm').style.display = '';
    document.getElementById('no-plan-msg').style.display = 'none';

    // Update summary bar
    const bar = document.getElementById('plan-summary-bar');
    bar.style.display = '';
    bar.style.borderColor = plan.colorBorder;
    document.getElementById('sum-icon') && (document.getElementById('sum-icon').textContent = plan.icon);
    document.getElementById('sum-name') && (document.getElementById('sum-name').textContent = plan.name + ' Membership');
    document.getElementById('sum-sub')  && (document.getElementById('sum-sub').textContent  = plan.tagline + ' · ₹' + plan.price.toLocaleString('en-IN') + ' / month');

    updateTotal();

    // Smooth scroll to form
    setTimeout(() => document.getElementById('enroll').scrollIntoView({behavior:'smooth', block:'start'}), 80);
}

// ── Update total price ────────────────────────────────────────────────────────
function updateTotal() {
    if (!currentPlan) return;
    const plan = planMap[currentPlan];
    if (!plan) return;

    const durInput = document.querySelector('input[name="duration_months"]:checked');
    const months   = durInput ? parseInt(durInput.value) : 1;

    // Discounts
    const discounts = { 1: 0, 3: 0.05, 6: 0.10, 12: 0.15 };
    const disc = discounts[months] || 0;
    const total = Math.round(plan.price * months * (1 - disc));

    const durLabels = { 1: '1 Month', 3: '3 Months', 6: '6 Months', 12: '12 Months' };
    const saved = Math.round(plan.price * months * disc);

    // Calc bar
    const calcLabel = document.getElementById('calc-label');
    const calcSub   = document.getElementById('calc-sub');
    const calcTotal = document.getElementById('calc-total');
    const totalDisp = document.getElementById('total-display');

    if (calcLabel) calcLabel.textContent = plan.name + ' Membership — ' + (durLabels[months] || months + ' months');
    if (calcSub)   calcSub.textContent   = saved > 0 ? 'Saving ₹' + saved.toLocaleString('en-IN') + ' with this duration' : 'Full price · no discount for this duration';
    if (calcTotal) calcTotal.textContent = '₹' + total.toLocaleString('en-IN');
    if (totalDisp) totalDisp.textContent = '₹' + total.toLocaleString('en-IN');
}

// ── Init ──────────────────────────────────────────────────────────────────────
(function() {
    if (currentPlan) {
        // Re-apply selected state on page load (after PHP POST error)
        document.querySelectorAll('.plan-card').forEach(card => {
            card.classList.toggle('selected', card.id === 'card-' + currentPlan);
        });
        document.querySelectorAll('.btn-select').forEach(btn => {
            btn.textContent = btn.id === 'btn-' + currentPlan ? '✓ Selected' : 'Select Plan';
        });
        updateTotal();
    }
})();
</script>
</body>
</html>