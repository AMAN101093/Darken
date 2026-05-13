<?php
// pending.php — Program Enrollment Approvals (Admin only)
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=pending.php');
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
    </head><body><h1>403 — Access Denied</h1><p>Admins only.</p><a href="index.php">← Back</a></body></html>');
}

// ── POST: Approve or Reject ───────────────────────────────────────────────────
$flash      = '';
$flash_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['booking_id'])) {
    $booking_id = (int)$_POST['booking_id'];
    $action     = $_POST['action'];
    $reason     = trim($_POST['reason'] ?? '');

    if ($action === 'approve' && $booking_id) {
        $pdo->prepare("
            UPDATE bookings
            SET status = 'confirmed',
                payment_status = 'paid',
                updated_at = NOW()
            WHERE id = ? AND status = 'pending'
        ")->execute([$booking_id]);
        $flash = 'Enrollment approved and payment confirmed.';
    } elseif ($action === 'reject' && $booking_id) {
        $pdo->prepare("
            UPDATE bookings
            SET status = 'cancelled',
                cancellation_reason = ?,
                cancelled_at = NOW(),
                updated_at = NOW()
            WHERE id = ? AND status = 'pending'
        ")->execute([$reason ?: 'Rejected by admin', $booking_id]);
        $flash      = 'Enrollment request has been rejected.';
        $flash_type = 'danger';
    } elseif ($action === 'restore' && $booking_id) {
        $pdo->prepare("
            UPDATE bookings
            SET status = 'pending',
                cancellation_reason = NULL,
                cancelled_at = NULL,
                updated_at = NOW()
            WHERE id = ?
        ")->execute([$booking_id]);
        $flash = 'Booking restored to pending.';
    }

    header('Location: pending.php?msg=' . urlencode($flash) . '&type=' . $flash_type . '&tab=' . ($_POST['current_tab'] ?? 'pending'));
    exit;
}

if (isset($_GET['msg'])) {
    $flash      = htmlspecialchars($_GET['msg']);
    $flash_type = $_GET['type'] ?? 'success';
}

// ── Filters ───────────────────────────────────────────────────────────────────
$tab          = $_GET['tab']     ?? 'pending';   // pending | confirmed | cancelled | all
$search       = trim($_GET['search'] ?? '');
$prog_filter  = $_GET['program'] ?? '';
$page         = max(1, (int)($_GET['page'] ?? 1));
$per_page     = 15;
$offset       = ($page - 1) * $per_page;

// ── Build WHERE ───────────────────────────────────────────────────────────────
$where_parts  = ["b.program != 'membership'"];
$params       = [];

if ($tab === 'pending')   { $where_parts[] = "b.status = 'pending'"; }
elseif ($tab === 'confirmed') { $where_parts[] = "b.status IN ('confirmed','active')"; }
elseif ($tab === 'cancelled') { $where_parts[] = "b.status = 'cancelled'"; }
// 'all' — no status filter

if ($prog_filter) {
    $where_parts[] = "b.program = ?";
    $params[] = $prog_filter;
}

if ($search) {
    $where_parts[] = "(b.swimmer_name LIKE ? OR b.booking_reference LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.phone LIKE ?)";
    $s = "%$search%";
    array_push($params, $s, $s, $s, $s, $s);
}

$where_sql = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';

// ── Count ─────────────────────────────────────────────────────────────────────
$count_stmt = $pdo->prepare("
    SELECT COUNT(*) FROM bookings b
    JOIN users u ON u.id = b.booked_by_user_id
    $where_sql
");
$count_stmt->execute($params);
$total_rows  = (int)$count_stmt->fetchColumn();
$total_pages = max(1, ceil($total_rows / $per_page));

// ── Fetch bookings ────────────────────────────────────────────────────────────
$data_stmt = $pdo->prepare("
    SELECT
        b.id, b.booking_reference, b.program,
        b.swimmer_name, b.swimmer_dob, b.swimmer_relation, b.is_for_self,
        b.swimmer_phone, b.swimmer_email,
        b.swimmer_emergency_contact, b.swimmer_medical_notes,
        b.payment_amount, b.payment_method, b.payment_reference, b.payment_status,
        b.status, b.cancellation_reason, b.cancelled_at,
        b.created_at,
        u.id AS user_id, u.first_name, u.last_name, u.phone AS user_phone, u.email AS user_email
    FROM bookings b
    JOIN users u ON u.id = b.booked_by_user_id
    $where_sql
    ORDER BY b.created_at DESC
    LIMIT $per_page OFFSET $offset
");
$data_stmt->execute($params);
$bookings = $data_stmt->fetchAll();

// ── Quick stats ───────────────────────────────────────────────────────────────
$stats = [
    'pending'   => $pdo->query("SELECT COUNT(*) FROM bookings WHERE status='pending' AND program!='membership'")->fetchColumn(),
    'confirmed' => $pdo->query("SELECT COUNT(*) FROM bookings WHERE status IN ('confirmed','active') AND program!='membership'")->fetchColumn(),
    'cancelled' => $pdo->query("SELECT COUNT(*) FROM bookings WHERE status='cancelled' AND program!='membership'")->fetchColumn(),
    'total'     => $pdo->query("SELECT COUNT(*) FROM bookings WHERE program!='membership'")->fetchColumn(),
    'revenue'   => $pdo->query("SELECT COALESCE(SUM(payment_amount),0) FROM bookings WHERE payment_status='paid' AND program!='membership'")->fetchColumn(),
];

// ── Helpers ───────────────────────────────────────────────────────────────────
$program_labels = [
    'junior_development'  => 'Junior Development',
    'competitive_squad'   => 'Competitive Squad',
    'elite_coaching'      => 'Elite Coaching',
    'adult_fitness_swim'  => 'Adult Fitness Swim',
    'mental_conditioning' => 'Mental Conditioning',
    'masters_program'     => 'Masters Program',
];
$program_colors = [
    'junior_development'  => '#00c8ff',
    'competitive_squad'   => '#ffc847',
    'elite_coaching'      => '#c8a8f8',
    'adult_fitness_swim'  => '#00e5b0',
    'mental_conditioning' => '#b57bee',
    'masters_program'     => '#e8b84b',
];
$program_icons = [
    'junior_development'  => '🌊',
    'competitive_squad'   => '🏊',
    'elite_coaching'      => '🎯',
    'adult_fitness_swim'  => '💪',
    'mental_conditioning' => '🧠',
    'masters_program'     => '🥇',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Enrollment Approvals — Darken Shadows SC Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700;900&family=Raleway:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
:root {
  --ocean-1:#e8f4fd; --ocean-2:#b8ddf5; --ocean-3:#6db8e8;
  --ocean-4:#2a8cc4; --ocean-5:#1565a0; --ocean-6:#0d3d6b; --ocean-7:#061e35;
  --accent:#00c8ff; --danger:#ff4d6d; --success:#00d68f; --warn:#ffc847;
  --font-display:'Cinzel',serif; --font-body:'Raleway',sans-serif;
  --radius:12px; --radius-sm:7px;
  --card-bg:rgba(13,61,107,.42); --card-border:rgba(109,184,232,.15);
  --transition:.22s cubic-bezier(.4,0,.2,1);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html{scroll-behavior:smooth;}
body{font-family:var(--font-body);background:var(--ocean-7);color:var(--ocean-1);min-height:100vh;}
body::before{content:'';position:fixed;inset:0;z-index:0;
  background:radial-gradient(ellipse 65% 50% at 12% 5%,rgba(0,200,255,.12) 0%,transparent 55%),
  radial-gradient(ellipse 50% 60% at 88% 90%,rgba(42,140,196,.1) 0%,transparent 55%),
  linear-gradient(160deg,var(--ocean-7) 0%,#082840 60%,var(--ocean-7) 100%);pointer-events:none;}
body::after{content:'';position:fixed;inset:0;z-index:0;
  background-image:radial-gradient(circle at 1px 1px,rgba(109,184,232,.04) 1px,transparent 0);
  background-size:36px 36px;pointer-events:none;}

/* ── Nav ── */
nav{position:sticky;top:0;z-index:100;display:flex;align-items:center;justify-content:space-between;
  padding:1rem 2.5rem;background:rgba(6,30,53,.93);backdrop-filter:blur(20px);
  border-bottom:1px solid rgba(0,200,255,.15);}
.nav-logo{font-family:var(--font-display);font-size:.92rem;font-weight:700;letter-spacing:.12em;color:var(--accent);text-decoration:none;text-transform:uppercase;}
.nav-links{display:flex;gap:1.4rem;align-items:center;}
.nav-links a{font-size:.74rem;font-weight:600;letter-spacing:.1em;color:var(--ocean-2);text-decoration:none;text-transform:uppercase;transition:color var(--transition);}
.nav-links a:hover,.nav-links a.active{color:var(--accent);}
.nav-badge{background:rgba(255,200,71,.15);border:1px solid rgba(255,200,71,.35);color:var(--warn);padding:.22rem .75rem;border-radius:100px;font-size:.62rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;}
.breadcrumb{font-size:.72rem;color:rgba(184,221,245,.4);display:flex;align-items:center;gap:.4rem;}
.breadcrumb a{color:rgba(184,221,245,.4);text-decoration:none;transition:color var(--transition);}
.breadcrumb a:hover{color:var(--ocean-2);}

/* ── Layout ── */
.page{position:relative;z-index:1;max-width:1320px;margin:0 auto;padding:2rem 2rem 6rem;}

/* ── Flash ── */
.flash{position:fixed;top:78px;right:20px;z-index:500;
  border-radius:var(--radius-sm);padding:.85rem 1.3rem;
  font-size:.84rem;font-weight:600;letter-spacing:.04em;
  animation:slideIn .35s ease,fadeOut .4s 3.5s ease forwards;
  box-shadow:0 8px 32px rgba(0,0,0,.4);}
.flash.success{background:rgba(0,214,143,.14);border:1px solid rgba(0,214,143,.4);color:#00d68f;}
.flash.danger{background:rgba(255,77,109,.14);border:1px solid rgba(255,77,109,.4);color:#ff4d6d;}
@keyframes slideIn{from{opacity:0;transform:translateX(28px)}to{opacity:1;transform:translateX(0)}}
@keyframes fadeOut{from{opacity:1}to{opacity:0;pointer-events:none}}

/* ── Page header ── */
.page-header{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:1.8rem;
  opacity:0;animation:fadeUp .5s .08s ease forwards;}
.page-header h1{font-family:var(--font-display);font-size:clamp(1.2rem,2.5vw,1.75rem);font-weight:700;color:var(--white);letter-spacing:.04em;}
.page-header p{font-size:.8rem;color:var(--ocean-3);margin-top:.3rem;font-weight:300;}

/* ── Stats strip ── */
.stats-strip{display:grid;grid-template-columns:repeat(5,1fr);gap:.8rem;margin-bottom:1.8rem;
  opacity:0;animation:fadeUp .5s .16s ease forwards;}
.s-card{background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius-sm);
  padding:.9rem 1rem;text-align:center;backdrop-filter:blur(8px);transition:transform var(--transition);}
.s-card:hover{transform:translateY(-2px);}
.s-num{font-family:var(--font-display);font-size:1.55rem;font-weight:700;color:var(--accent);line-height:1;margin-bottom:.2rem;}
.s-label{font-size:.6rem;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:rgba(184,221,245,.4);}
.s-card.warn .s-num{color:var(--warn);}
.s-card.green .s-num{color:var(--success);}
.s-card.red .s-num{color:var(--danger);}

/* ── Tabs ── */
.tab-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.3rem;flex-wrap:wrap;gap:.8rem;
  opacity:0;animation:fadeUp .5s .22s ease forwards;}
.tab-bar{display:flex;border-radius:var(--radius-sm);overflow:hidden;border:1px solid rgba(0,200,255,.18);width:fit-content;}
.tab-lnk{padding:.58rem 1.3rem;font-size:.72rem;font-weight:600;letter-spacing:.1em;text-transform:uppercase;
  text-decoration:none;color:var(--ocean-2);transition:all var(--transition);border:none;background:transparent;cursor:pointer;white-space:nowrap;}
.tab-lnk.active{background:rgba(0,200,255,.14);color:var(--accent);}
.tab-lnk:hover{background:rgba(0,200,255,.08);color:var(--accent);}
.tab-lnk .badge{display:inline-block;padding:.08rem .45rem;border-radius:100px;font-size:.6rem;
  background:rgba(255,200,71,.2);color:var(--warn);margin-left:.35rem;font-weight:700;}

/* ── Toolbar ── */
.toolbar{display:flex;align-items:center;gap:.8rem;margin-bottom:1.2rem;flex-wrap:wrap;
  opacity:0;animation:fadeUp .5s .28s ease forwards;}
.search-wrap{display:flex;align-items:center;gap:.6rem;background:rgba(255,255,255,.05);
  border:1px solid rgba(0,200,255,.18);border-radius:var(--radius-sm);padding:.5rem .9rem;flex:1;max-width:340px;}
.search-wrap input{background:none;border:none;outline:none;font-family:var(--font-body);font-size:.84rem;color:var(--ocean-1);width:100%;}
.search-wrap input::placeholder{color:rgba(184,221,245,.3);}
.prog-filter{background:rgba(255,255,255,.05);border:1px solid rgba(0,200,255,.18);border-radius:var(--radius-sm);
  padding:.5rem .8rem;font-family:var(--font-body);font-size:.8rem;color:var(--ocean-1);outline:none;cursor:pointer;}
.prog-filter option{background:var(--ocean-6);}
.count-info{font-size:.74rem;color:rgba(184,221,245,.4);white-space:nowrap;margin-left:auto;}

/* ── Table wrapper ── */
.table-wrap{background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius);
  overflow:hidden;backdrop-filter:blur(10px);
  opacity:0;animation:fadeUp .5s .34s ease forwards;}
.data-table{width:100%;border-collapse:collapse;}
.data-table thead tr{background:rgba(0,200,255,.06);border-bottom:1px solid rgba(0,200,255,.12);}
.data-table th{padding:.7rem 1rem;font-size:.6rem;font-weight:700;letter-spacing:.16em;
  text-transform:uppercase;color:rgba(184,221,245,.4);text-align:left;white-space:nowrap;}
.data-table td{padding:.8rem 1rem;font-size:.82rem;color:var(--ocean-1);
  border-bottom:1px solid rgba(109,184,232,.06);vertical-align:middle;}
.data-table tr:last-child td{border-bottom:none;}
.data-table tbody tr:hover td{background:rgba(0,200,255,.04);}
.muted{color:rgba(184,221,245,.4);font-size:.74rem;}

/* Prog chip */
.prog-chip{display:inline-flex;align-items:center;gap:.35rem;padding:.22rem .7rem;border-radius:100px;
  font-size:.62rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;border:1px solid;}

/* Status + payment pills */
.pill{display:inline-block;padding:.18rem .65rem;border-radius:100px;font-size:.62rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;}
.pill-pending{background:rgba(255,200,71,.1);border:1px solid rgba(255,200,71,.25);color:var(--warn);}
.pill-confirmed,.pill-active{background:rgba(0,214,143,.1);border:1px solid rgba(0,214,143,.25);color:var(--success);}
.pill-cancelled,.pill-expired{background:rgba(255,77,109,.1);border:1px solid rgba(255,77,109,.25);color:#ff4d6d;}
.pill-paid{background:rgba(0,214,143,.1);border:1px solid rgba(0,214,143,.25);color:var(--success);}
.pill-unpaid,.pill-pending-pay{background:rgba(255,200,71,.1);border:1px solid rgba(255,200,71,.25);color:var(--warn);}

/* ── Action buttons ── */
.actions{display:flex;gap:.4rem;align-items:center;flex-wrap:nowrap;}
.btn-approve{padding:.32rem .8rem;background:rgba(0,214,143,.12);border:1px solid rgba(0,214,143,.35);
  border-radius:var(--radius-sm);font-family:var(--font-body);font-size:.68rem;font-weight:700;
  letter-spacing:.08em;text-transform:uppercase;color:var(--success);cursor:pointer;
  transition:all var(--transition);}
.btn-approve:hover{background:rgba(0,214,143,.25);transform:translateY(-1px);}
.btn-reject{padding:.32rem .8rem;background:rgba(255,77,109,.1);border:1px solid rgba(255,77,109,.3);
  border-radius:var(--radius-sm);font-family:var(--font-body);font-size:.68rem;font-weight:700;
  letter-spacing:.08em;text-transform:uppercase;color:#ff4d6d;cursor:pointer;
  transition:all var(--transition);}
.btn-reject:hover{background:rgba(255,77,109,.2);transform:translateY(-1px);}
.btn-restore{padding:.32rem .8rem;background:rgba(109,184,232,.1);border:1px solid rgba(109,184,232,.3);
  border-radius:var(--radius-sm);font-family:var(--font-body);font-size:.68rem;font-weight:700;
  letter-spacing:.08em;text-transform:uppercase;color:var(--ocean-3);cursor:pointer;
  transition:all var(--transition);}
.btn-restore:hover{background:rgba(109,184,232,.2);}

/* Detail row expand */
.detail-row td{padding:0 !important;background:rgba(0,0,0,.25) !important;border-bottom:2px solid rgba(0,200,255,.12) !important;}
.detail-inner{padding:1.2rem 1.6rem;display:grid;grid-template-columns:repeat(4,1fr);gap:.8rem;}
.detail-box{background:rgba(255,255,255,.04);border:1px solid rgba(109,184,232,.1);border-radius:var(--radius-sm);padding:.75rem .9rem;}
.detail-label{font-size:.58rem;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:rgba(184,221,245,.35);margin-bottom:.3rem;}
.detail-val{font-size:.82rem;font-weight:500;color:var(--ocean-1);}
.detail-val.empty{color:rgba(184,221,245,.28);font-style:italic;font-size:.76rem;}
.detail-reject-form{grid-column:span 4;display:flex;gap:.7rem;align-items:flex-end;}
.detail-reject-form input{flex:1;padding:.6rem .9rem;background:rgba(255,255,255,.05);
  border:1px solid rgba(255,77,109,.25);border-radius:var(--radius-sm);
  font-family:var(--font-body);font-size:.84rem;color:var(--ocean-1);outline:none;}
.detail-reject-form input:focus{border-color:#ff4d6d;}
.toggle-detail{padding:.26rem .7rem;background:transparent;border:1px solid rgba(109,184,232,.2);
  border-radius:4px;font-size:.66rem;font-weight:600;color:var(--ocean-3);cursor:pointer;
  transition:all var(--transition);}
.toggle-detail:hover{background:rgba(109,184,232,.1);}

/* ── Empty state ── */
.empty-state{text-align:center;padding:4rem;color:rgba(184,221,245,.35);}
.empty-state .e-icon{font-size:3rem;display:block;margin-bottom:1rem;}
.empty-state p{font-size:.9rem;}

/* ── Pagination ── */
.pagination{display:flex;align-items:center;justify-content:center;gap:.45rem;margin-top:1.2rem;
  opacity:0;animation:fadeUp .5s .4s ease forwards;}
.page-btn{padding:.4rem .85rem;border-radius:var(--radius-sm);font-size:.73rem;font-weight:600;
  letter-spacing:.06em;text-decoration:none;border:1px solid rgba(109,184,232,.2);
  color:var(--ocean-3);transition:all var(--transition);}
.page-btn:hover,.page-btn.current{background:rgba(0,200,255,.12);border-color:rgba(0,200,255,.35);color:var(--accent);}
.page-btn.disabled{opacity:.3;pointer-events:none;}

/* ── Ref style ── */
.ref-txt{font-family:var(--font-display);font-size:.68rem;letter-spacing:.1em;color:rgba(184,221,245,.4);}

@keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}

@media(max-width:1024px){.stats-strip{grid-template-columns:repeat(3,1fr);}.data-table th:nth-child(n+7),.data-table td:nth-child(n+7){display:none;}}
@media(max-width:768px){nav{padding:1rem 1rem;}.page{padding:1.2rem .8rem 4rem;}.stats-strip{grid-template-columns:repeat(2,1fr);}.toolbar{flex-direction:column;align-items:stretch;}.search-wrap{max-width:100%;}.detail-inner{grid-template-columns:1fr 1fr;}.detail-reject-form{grid-column:span 2;}}
</style>
</head>
<body>

<?php if ($flash): ?>
<div class="flash <?= $flash_type === 'danger' ? 'danger' : 'success' ?>" id="flash-el">
  <?= $flash_type === 'danger' ? '✕' : '✓' ?> <?= $flash ?>
</div>
<script>setTimeout(()=>document.getElementById('flash-el')?.remove(), 4000);</script>
<?php endif; ?>

<nav>
  <a href="admin.php" class="nav-logo">⚙ Admin</a>
  <div class="nav-links">
    <a href="admin.php">Dashboard</a>
    <a href="database.php">Users</a>
    <a href="pending.php" class="active">Enrollments</a>
    <a href="m_pending.php">Memberships</a>
    <a href="coach.php">Coaches</a>
    <a href="logout.php">Logout</a>
  </div>
  <div class="breadcrumb">
    <a href="admin.php">Admin</a> / <span style="color:var(--ocean-2);">Enrollment Approvals</span>
  </div>
</nav>

<div class="page">

  <div class="page-header">
    <div>
      <h1>📋 Enrollment Approvals</h1>
      <p>Review, approve, or reject program enrollment requests</p>
    </div>
    <a href="m_pending.php" style="display:inline-flex;align-items:center;gap:.5rem;padding:.6rem 1.2rem;background:rgba(0,200,255,.1);border:1px solid rgba(0,200,255,.25);border-radius:var(--radius-sm);font-size:.74rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--accent);text-decoration:none;transition:all var(--transition);">
      🏅 Membership Queue →
    </a>
  </div>

  <!-- Stats -->
  <div class="stats-strip">
    <div class="s-card warn">
      <div class="s-num"><?= number_format($stats['pending']) ?></div>
      <div class="s-label">Awaiting Review</div>
    </div>
    <div class="s-card green">
      <div class="s-num"><?= number_format($stats['confirmed']) ?></div>
      <div class="s-label">Approved</div>
    </div>
    <div class="s-card red">
      <div class="s-num"><?= number_format($stats['cancelled']) ?></div>
      <div class="s-label">Rejected</div>
    </div>
    <div class="s-card">
      <div class="s-num"><?= number_format($stats['total']) ?></div>
      <div class="s-label">Total Enrollments</div>
    </div>
    <div class="s-card green">
      <div class="s-num">₹<?= number_format($stats['revenue']) ?></div>
      <div class="s-label">Revenue (Paid)</div>
    </div>
  </div>

  <!-- Tab bar + search -->
  <div class="tab-row">
    <div class="tab-bar">
      <?php
        $tabs = [
          'pending'   => ['Pending', $stats['pending']],
          'confirmed' => ['Approved', null],
          'cancelled' => ['Rejected', null],
          'all'       => ['All', $stats['total']],
        ];
        foreach ($tabs as $key => [$label, $count]):
          $href = "?tab=$key&search=" . urlencode($search) . "&program=" . urlencode($prog_filter);
      ?>
      <a class="tab-lnk <?= $tab === $key ? 'active' : '' ?>" href="<?= $href ?>">
        <?= $label ?>
        <?php if ($count): ?><span class="badge"><?= $count ?></span><?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Toolbar -->
  <form method="GET" class="toolbar">
    <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
    <div class="search-wrap">
      <span style="color:rgba(184,221,245,.3);">🔍</span>
      <input type="text" name="search" placeholder="Name, phone, reference…"
             value="<?= htmlspecialchars($search) ?>" oninput="this.form.submit()">
    </div>
    <select name="program" class="prog-filter" onchange="this.form.submit()">
      <option value="">All Programs</option>
      <?php foreach ($program_labels as $key => $label): ?>
      <option value="<?= $key ?>" <?= $prog_filter === $key ? 'selected' : '' ?>><?= $label ?></option>
      <?php endforeach; ?>
    </select>
    <span class="count-info"><?= number_format($total_rows) ?> record<?= $total_rows != 1 ? 's' : '' ?></span>
  </form>

  <!-- Table -->
  <div class="table-wrap">
    <table class="data-table" id="main-table">
      <thead>
        <tr>
          <th></th>
          <th>Reference</th>
          <th>Program</th>
          <th>Swimmer</th>
          <th>Booked By</th>
          <th>Amount</th>
          <th>Payment</th>
          <th>Status</th>
          <th>Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($bookings)): ?>
        <tr>
          <td colspan="10">
            <div class="empty-state">
              <span class="e-icon">📭</span>
              <p>No enrollments found<?= $tab === 'pending' ? ' awaiting review' : '' ?>.</p>
            </div>
          </td>
        </tr>

        <?php else: foreach ($bookings as $i => $b):
          $prog_color = $program_colors[$b['program']] ?? '#6db8e8';
          $prog_label = $program_labels[$b['program']] ?? ucwords(str_replace('_', ' ', $b['program']));
          $prog_icon  = $program_icons[$b['program']] ?? '🏊';
          $row_id     = 'detail-' . $b['id'];
        ?>

        <!-- Main row -->
        <tr id="row-<?= $b['id'] ?>">
          <td>
            <button type="button" class="toggle-detail" onclick="toggleDetail('<?= $row_id ?>')">▾</button>
          </td>
          <td>
            <span class="ref-txt"><?= htmlspecialchars($b['booking_reference']) ?></span>
          </td>
          <td>
            <span class="prog-chip" style="color:<?= $prog_color ?>;border-color:<?= $prog_color ?>44;background:<?= $prog_color ?>14;">
              <?= $prog_icon ?> <?= htmlspecialchars($prog_label) ?>
            </span>
          </td>
          <td>
            <strong style="color:var(--ocean-1);"><?= htmlspecialchars($b['swimmer_name']) ?></strong>
            <?php if (!$b['is_for_self']): ?>
              <br><span class="muted">(<?= htmlspecialchars($b['swimmer_relation']) ?>)</span>
            <?php endif; ?>
          </td>
          <td>
            <?= htmlspecialchars($b['first_name'] . ' ' . $b['last_name']) ?>
            <br><span class="muted"><?= htmlspecialchars($b['user_phone']) ?></span>
          </td>
          <td style="font-weight:600;color:var(--ocean-2);">
            <?= $b['payment_amount'] ? '₹' . number_format($b['payment_amount']) : '<span class="muted">—</span>' ?>
          </td>
          <td>
            <span class="pill pill-<?= $b['payment_status'] === 'paid' ? 'paid' : 'pending-pay' ?>">
              <?= ucfirst($b['payment_status']) ?>
            </span>
          </td>
          <td>
            <span class="pill pill-<?= $b['status'] ?>">
              <?= ucfirst($b['status']) ?>
            </span>
          </td>
          <td class="muted"><?= date('d M Y', strtotime($b['created_at'])) ?></td>
          <td>
            <div class="actions">
              <?php if ($b['status'] === 'pending'): ?>
                <!-- Quick approve -->
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                  <input type="hidden" name="action" value="approve">
                  <input type="hidden" name="current_tab" value="<?= $tab ?>">
                  <button type="submit" class="btn-approve">✓ Approve</button>
                </form>
                <!-- Reject opens detail row -->
                <button type="button" class="btn-reject" onclick="openReject('<?= $row_id ?>')">✕ Reject</button>

              <?php elseif ($b['status'] === 'cancelled'): ?>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                  <input type="hidden" name="action" value="restore">
                  <input type="hidden" name="current_tab" value="<?= $tab ?>">
                  <button type="submit" class="btn-restore">↩ Restore</button>
                </form>

              <?php else: ?>
                <span class="muted" style="font-size:.72rem;">No action</span>
              <?php endif; ?>

              <!-- Always show detail toggle -->
            </div>
          </td>
        </tr>

        <!-- Detail / reject row (hidden by default) -->
        <tr class="detail-row" id="<?= $row_id ?>" style="display:none;">
          <td colspan="10">
            <div class="detail-inner">
              <div class="detail-box">
                <div class="detail-label">Swimmer DOB</div>
                <div class="detail-val <?= $b['swimmer_dob'] ? '' : 'empty' ?>">
                  <?= $b['swimmer_dob'] ? date('d M Y', strtotime($b['swimmer_dob'])) : 'Not provided' ?>
                </div>
              </div>
              <div class="detail-box">
                <div class="detail-label">Emergency Contact</div>
                <div class="detail-val <?= $b['swimmer_emergency_contact'] ? '' : 'empty' ?>">
                  <?= htmlspecialchars($b['swimmer_emergency_contact'] ?? 'Not provided') ?>
                </div>
              </div>
              <div class="detail-box">
                <div class="detail-label">Swimmer Email</div>
                <div class="detail-val <?= $b['swimmer_email'] ? '' : 'empty' ?>">
                  <?= $b['swimmer_email'] ? htmlspecialchars($b['swimmer_email']) : 'Not provided' ?>
                </div>
              </div>
              <div class="detail-box">
                <div class="detail-label">Swimmer Phone</div>
                <div class="detail-val <?= $b['swimmer_phone'] ? '' : 'empty' ?>">
                  <?= $b['swimmer_phone'] ? htmlspecialchars($b['swimmer_phone']) : 'Not provided' ?>
                </div>
              </div>
              <?php if ($b['swimmer_medical_notes']): ?>
              <div class="detail-box" style="grid-column:span 2;">
                <div class="detail-label">Medical Notes</div>
                <div class="detail-val"><?= htmlspecialchars($b['swimmer_medical_notes']) ?></div>
              </div>
              <?php endif; ?>
              <div class="detail-box">
                <div class="detail-label">Payment Method</div>
                <div class="detail-val <?= $b['payment_method'] ? '' : 'empty' ?>">
                  <?= $b['payment_method'] ? ucwords(str_replace('_', ' ', $b['payment_method'])) : 'Not specified' ?>
                </div>
              </div>
              <div class="detail-box">
                <div class="detail-label">Payment Reference</div>
                <div class="detail-val <?= $b['payment_reference'] ? '' : 'empty' ?>">
                  <?= $b['payment_reference'] ? htmlspecialchars($b['payment_reference']) : 'Not provided' ?>
                </div>
              </div>
              <?php if ($b['status'] === 'cancelled' && $b['cancellation_reason']): ?>
              <div class="detail-box" style="grid-column:span 4;border-color:rgba(255,77,109,.25);">
                <div class="detail-label" style="color:rgba(255,77,109,.6);">Rejection Reason</div>
                <div class="detail-val" style="color:#ff8fa3;"><?= htmlspecialchars($b['cancellation_reason']) ?></div>
              </div>
              <?php endif; ?>

              <?php if ($b['status'] === 'pending'): ?>
              <!-- Inline reject form -->
              <form method="POST" class="detail-reject-form" id="reject-form-<?= $b['id'] ?>">
                <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="current_tab" value="<?= $tab ?>">
                <input type="text" name="reason" placeholder="Rejection reason (optional)…">
                <button type="submit" class="btn-reject" style="white-space:nowrap;">✕ Confirm Reject</button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>

        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($total_pages > 1):
    $base_url = "?tab=$tab&search=" . urlencode($search) . "&program=" . urlencode($prog_filter);
  ?>
  <div class="pagination">
    <?php if ($page > 1): ?>
      <a class="page-btn" href="<?= $base_url ?>&page=<?= $page - 1 ?>">← Prev</a>
    <?php endif; ?>
    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
      <a class="page-btn <?= $i === $page ? 'current' : '' ?>" href="<?= $base_url ?>&page=<?= $i ?>"><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($page < $total_pages): ?>
      <a class="page-btn" href="<?= $base_url ?>&page=<?= $page + 1 ?>">Next →</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

</div><!-- /page -->

<script>
function toggleDetail(id) {
  const row = document.getElementById(id);
  if (!row) return;
  const isHidden = row.style.display === 'none';
  row.style.display = isHidden ? 'table-row' : 'none';
}

function openReject(id) {
  const row = document.getElementById(id);
  if (!row) return;
  row.style.display = 'table-row';
  const input = row.querySelector('input[name="reason"]');
  if (input) setTimeout(() => input.focus(), 80);
}
</script>
</body>
</html>