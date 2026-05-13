<?php
// m_pending.php — Membership Approvals (Admin only)
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=m_pending.php');
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['membership_id'])) {
    $mid    = (int)$_POST['membership_id'];
    $action = $_POST['action'];
    $reason = trim($_POST['reason'] ?? '');

    if ($action === 'approve' && $mid) {
        // Activate the membership
        $pdo->prepare("
            UPDATE memberships
            SET status = 'active', updated_at = NOW()
            WHERE id = ? AND status = 'pending'
        ")->execute([$mid]);

        // Also confirm the corresponding booking row
        $pdo->prepare("
            UPDATE bookings
            SET status = 'confirmed',
                payment_status = 'paid',
                updated_at = NOW()
            WHERE program = 'membership'
              AND program_record_id = ?
              AND status = 'pending'
        ")->execute([$mid]);

        $flash = 'Membership approved and activated successfully.';

    } elseif ($action === 'reject' && $mid) {
        $pdo->prepare("
            UPDATE memberships
            SET status = 'cancelled', updated_at = NOW()
            WHERE id = ? AND status = 'pending'
        ")->execute([$mid]);

        $pdo->prepare("
            UPDATE bookings
            SET status = 'cancelled',
                cancellation_reason = ?,
                cancelled_at = NOW(),
                updated_at = NOW()
            WHERE program = 'membership'
              AND program_record_id = ?
              AND status = 'pending'
        ")->execute([$reason ?: 'Rejected by admin', $mid]);

        $flash      = 'Membership request has been rejected.';
        $flash_type = 'danger';

    } elseif ($action === 'pause' && $mid) {
        $pdo->prepare("UPDATE memberships SET status='paused', updated_at=NOW() WHERE id=?")->execute([$mid]);
        $flash = 'Membership paused.';

    } elseif ($action === 'reactivate' && $mid) {
        $pdo->prepare("UPDATE memberships SET status='active', updated_at=NOW() WHERE id=?")->execute([$mid]);
        $flash = 'Membership reactivated.';

    } elseif ($action === 'restore' && $mid) {
        $pdo->prepare("UPDATE memberships SET status='pending', updated_at=NOW() WHERE id=?")->execute([$mid]);
        $flash = 'Membership restored to pending.';
    }

    header('Location: m_pending.php?msg=' . urlencode($flash) . '&type=' . $flash_type . '&tab=' . ($_POST['current_tab'] ?? 'pending'));
    exit;
}

if (isset($_GET['msg'])) {
    $flash      = htmlspecialchars($_GET['msg']);
    $flash_type = $_GET['type'] ?? 'success';
}

// ── Filters ───────────────────────────────────────────────────────────────────
$tab         = $_GET['tab']    ?? 'pending';
$search      = trim($_GET['search'] ?? '');
$plan_filter = $_GET['plan']   ?? '';
$page        = max(1, (int)($_GET['page'] ?? 1));
$per_page    = 15;
$offset      = ($page - 1) * $per_page;

// ── WHERE clause ──────────────────────────────────────────────────────────────
$where_parts = [];
$params      = [];

if ($tab === 'pending')   { $where_parts[] = "m.status = 'pending'"; }
elseif ($tab === 'active')   { $where_parts[] = "m.status = 'active'"; }
elseif ($tab === 'paused')   { $where_parts[] = "m.status = 'paused'"; }
elseif ($tab === 'cancelled'){ $where_parts[] = "m.status = 'cancelled'"; }
elseif ($tab === 'expired')  { $where_parts[] = "m.status = 'expired'"; }

if ($plan_filter) {
    $where_parts[] = "m.plan = ?";
    $params[] = $plan_filter;
}

if ($search) {
    $where_parts[] = "(m.full_name LIKE ? OR u.phone LIKE ? OR u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $s = "%$search%";
    array_push($params, $s, $s, $s, $s, $s);
}

$where_sql = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';

// ── Count ─────────────────────────────────────────────────────────────────────
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM memberships m JOIN users u ON u.id = m.user_id $where_sql");
$count_stmt->execute($params);
$total_rows  = (int)$count_stmt->fetchColumn();
$total_pages = max(1, ceil($total_rows / $per_page));

// ── Fetch memberships ─────────────────────────────────────────────────────────
$data_stmt = $pdo->prepare("
    SELECT
        m.id, m.plan, m.full_name, m.dob,
        m.phone, m.email, m.emergency_contact, m.medical_notes,
        m.start_date, m.end_date, m.duration_months, m.auto_renew,
        m.payment_amount, m.payment_method, m.payment_reference,
        m.status, m.created_at,
        u.id AS user_id, u.first_name, u.last_name,
        u.phone AS user_phone, u.email AS user_email, u.created_at AS user_since
    FROM memberships m
    JOIN users u ON u.id = m.user_id
    $where_sql
    ORDER BY m.created_at DESC
    LIMIT $per_page OFFSET $offset
");
$data_stmt->execute($params);
$memberships = $data_stmt->fetchAll();

// ── Stats ─────────────────────────────────────────────────────────────────────
$stats = [
    'pending'   => $pdo->query("SELECT COUNT(*) FROM memberships WHERE status='pending'")->fetchColumn(),
    'active'    => $pdo->query("SELECT COUNT(*) FROM memberships WHERE status='active'")->fetchColumn(),
    'paused'    => $pdo->query("SELECT COUNT(*) FROM memberships WHERE status='paused'")->fetchColumn(),
    'expired'   => $pdo->query("SELECT COUNT(*) FROM memberships WHERE status='expired'")->fetchColumn(),
    'cancelled' => $pdo->query("SELECT COUNT(*) FROM memberships WHERE status='cancelled'")->fetchColumn(),
    'revenue'   => $pdo->query("SELECT COALESCE(SUM(payment_amount),0) FROM memberships WHERE status='active'")->fetchColumn(),
];

// ── Plan meta ─────────────────────────────────────────────────────────────────
$plan_meta = [
    'bronze'   => ['#cd7f32', '🥉', 'rgba(205,127,50,.12)',  'rgba(205,127,50,.3)'],
    'silver'   => ['#a8a9ad', '🥈', 'rgba(168,169,173,.12)', 'rgba(168,169,173,.3)'],
    'gold'     => ['#d4af37', '🥇', 'rgba(212,175,55,.12)',  'rgba(212,175,55,.3)'],
    'platinum' => ['#e5e4e2', '💎', 'rgba(229,228,226,.1)',  'rgba(229,228,226,.3)'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Membership Approvals — Darken Shadows SC Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700;900&family=Raleway:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
:root {
  --ocean-1:#e8f4fd; --ocean-2:#b8ddf5; --ocean-3:#6db8e8;
  --ocean-4:#2a8cc4; --ocean-5:#1565a0; --ocean-6:#0d3d6b; --ocean-7:#061e35;
  --accent:#ffc847; --danger:#ff4d6d; --success:#00d68f; --warn:#ffc847;
  --font-display:'Cinzel',serif; --font-body:'Raleway',sans-serif;
  --radius:12px; --radius-sm:7px;
  --card-bg:rgba(13,61,107,.42); --card-border:rgba(109,184,232,.15);
  --transition:.22s cubic-bezier(.4,0,.2,1);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html{scroll-behavior:smooth;}
body{font-family:var(--font-body);background:var(--ocean-7);color:var(--ocean-1);min-height:100vh;}
body::before{content:'';position:fixed;inset:0;z-index:0;
  background:radial-gradient(ellipse 70% 50% at 10% 5%,rgba(255,200,71,.1) 0%,transparent 55%),
  radial-gradient(ellipse 55% 65% at 90% 92%,rgba(42,140,196,.1) 0%,transparent 55%),
  linear-gradient(160deg,var(--ocean-7) 0%,#082030 60%,var(--ocean-7) 100%);pointer-events:none;}
body::after{content:'';position:fixed;inset:0;z-index:0;
  background-image:radial-gradient(circle at 1px 1px,rgba(109,184,232,.04) 1px,transparent 0);
  background-size:36px 36px;pointer-events:none;}

/* ── Nav ── */
nav{position:sticky;top:0;z-index:100;display:flex;align-items:center;justify-content:space-between;
  padding:1rem 2.5rem;background:rgba(6,30,53,.93);backdrop-filter:blur(20px);
  border-bottom:1px solid rgba(255,200,71,.15);}
.nav-logo{font-family:var(--font-display);font-size:.92rem;font-weight:700;letter-spacing:.12em;color:var(--accent);text-decoration:none;text-transform:uppercase;}
.nav-links{display:flex;gap:1.4rem;align-items:center;}
.nav-links a{font-size:.74rem;font-weight:600;letter-spacing:.1em;color:var(--ocean-2);text-decoration:none;text-transform:uppercase;transition:color var(--transition);}
.nav-links a:hover,.nav-links a.active{color:var(--accent);}
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
.stats-strip{display:grid;grid-template-columns:repeat(6,1fr);gap:.8rem;margin-bottom:1.8rem;
  opacity:0;animation:fadeUp .5s .16s ease forwards;}
.s-card{background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius-sm);
  padding:.9rem 1rem;text-align:center;backdrop-filter:blur(8px);transition:transform var(--transition);}
.s-card:hover{transform:translateY(-2px);}
.s-num{font-family:var(--font-display);font-size:1.55rem;font-weight:700;color:var(--accent);line-height:1;margin-bottom:.2rem;}
.s-label{font-size:.6rem;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:rgba(184,221,245,.4);}
.s-card.green .s-num{color:var(--success);}
.s-card.red   .s-num{color:var(--danger);}
.s-card.blue  .s-num{color:var(--ocean-3);}
.s-card.pur   .s-num{color:#b57bee;}

/* ── Tab bar ── */
.tab-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.3rem;flex-wrap:wrap;gap:.8rem;
  opacity:0;animation:fadeUp .5s .22s ease forwards;}
.tab-bar{display:flex;border-radius:var(--radius-sm);overflow:hidden;border:1px solid rgba(255,200,71,.2);width:fit-content;}
.tab-lnk{padding:.58rem 1.3rem;font-size:.72rem;font-weight:600;letter-spacing:.1em;text-transform:uppercase;
  text-decoration:none;color:var(--ocean-2);transition:all var(--transition);border:none;background:transparent;cursor:pointer;white-space:nowrap;}
.tab-lnk.active{background:rgba(255,200,71,.12);color:var(--accent);}
.tab-lnk:hover{background:rgba(255,200,71,.07);color:var(--accent);}
.tab-lnk .badge{display:inline-block;padding:.08rem .45rem;border-radius:100px;font-size:.6rem;
  background:rgba(255,77,109,.2);color:#ff4d6d;margin-left:.35rem;font-weight:700;}

/* ── Toolbar ── */
.toolbar{display:flex;align-items:center;gap:.8rem;margin-bottom:1.2rem;flex-wrap:wrap;
  opacity:0;animation:fadeUp .5s .28s ease forwards;}
.search-wrap{display:flex;align-items:center;gap:.6rem;background:rgba(255,255,255,.05);
  border:1px solid rgba(255,200,71,.18);border-radius:var(--radius-sm);padding:.5rem .9rem;flex:1;max-width:340px;}
.search-wrap input{background:none;border:none;outline:none;font-family:var(--font-body);font-size:.84rem;color:var(--ocean-1);width:100%;}
.search-wrap input::placeholder{color:rgba(184,221,245,.3);}
.plan-filter{background:rgba(255,255,255,.05);border:1px solid rgba(255,200,71,.18);border-radius:var(--radius-sm);
  padding:.5rem .8rem;font-family:var(--font-body);font-size:.8rem;color:var(--ocean-1);outline:none;cursor:pointer;}
.plan-filter option{background:var(--ocean-6);}
.count-info{font-size:.74rem;color:rgba(184,221,245,.4);white-space:nowrap;margin-left:auto;}

/* ── Table ── */
.table-wrap{background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius);
  overflow:hidden;backdrop-filter:blur(10px);
  opacity:0;animation:fadeUp .5s .34s ease forwards;}
.data-table{width:100%;border-collapse:collapse;}
.data-table thead tr{background:rgba(255,200,71,.06);border-bottom:1px solid rgba(255,200,71,.12);}
.data-table th{padding:.7rem 1rem;font-size:.6rem;font-weight:700;letter-spacing:.16em;
  text-transform:uppercase;color:rgba(184,221,245,.4);text-align:left;white-space:nowrap;}
.data-table td{padding:.8rem 1rem;font-size:.82rem;color:var(--ocean-1);
  border-bottom:1px solid rgba(109,184,232,.06);vertical-align:middle;}
.data-table tr:last-child td{border-bottom:none;}
.data-table tbody tr:hover td{background:rgba(255,200,71,.03);}
.muted{color:rgba(184,221,245,.4);font-size:.74rem;}

/* Plan chip */
.plan-chip{display:inline-flex;align-items:center;gap:.35rem;padding:.25rem .75rem;border-radius:100px;
  font-size:.65rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;border:1px solid;}

/* Status pills */
.pill{display:inline-block;padding:.18rem .65rem;border-radius:100px;font-size:.62rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;}
.pill-pending{background:rgba(255,200,71,.1);border:1px solid rgba(255,200,71,.25);color:var(--warn);}
.pill-active{background:rgba(0,214,143,.1);border:1px solid rgba(0,214,143,.25);color:var(--success);}
.pill-paused{background:rgba(181,123,238,.1);border:1px solid rgba(181,123,238,.25);color:#b57bee;}
.pill-cancelled,.pill-expired{background:rgba(255,77,109,.1);border:1px solid rgba(255,77,109,.25);color:#ff4d6d;}

/* ── Action buttons ── */
.actions{display:flex;gap:.4rem;align-items:center;flex-wrap:nowrap;}
.btn-approve{padding:.32rem .8rem;background:rgba(0,214,143,.12);border:1px solid rgba(0,214,143,.35);
  border-radius:var(--radius-sm);font-family:var(--font-body);font-size:.68rem;font-weight:700;
  letter-spacing:.08em;text-transform:uppercase;color:var(--success);cursor:pointer;transition:all var(--transition);}
.btn-approve:hover{background:rgba(0,214,143,.25);transform:translateY(-1px);}
.btn-reject{padding:.32rem .8rem;background:rgba(255,77,109,.1);border:1px solid rgba(255,77,109,.3);
  border-radius:var(--radius-sm);font-family:var(--font-body);font-size:.68rem;font-weight:700;
  letter-spacing:.08em;text-transform:uppercase;color:#ff4d6d;cursor:pointer;transition:all var(--transition);}
.btn-reject:hover{background:rgba(255,77,109,.2);transform:translateY(-1px);}
.btn-pause{padding:.32rem .8rem;background:rgba(181,123,238,.1);border:1px solid rgba(181,123,238,.28);
  border-radius:var(--radius-sm);font-family:var(--font-body);font-size:.68rem;font-weight:700;
  letter-spacing:.08em;text-transform:uppercase;color:#b57bee;cursor:pointer;transition:all var(--transition);}
.btn-pause:hover{background:rgba(181,123,238,.2);}
.btn-restore{padding:.32rem .8rem;background:rgba(109,184,232,.1);border:1px solid rgba(109,184,232,.3);
  border-radius:var(--radius-sm);font-family:var(--font-body);font-size:.68rem;font-weight:700;
  letter-spacing:.08em;text-transform:uppercase;color:var(--ocean-3);cursor:pointer;transition:all var(--transition);}
.btn-restore:hover{background:rgba(109,184,232,.2);}
.toggle-detail{padding:.26rem .7rem;background:transparent;border:1px solid rgba(109,184,232,.2);
  border-radius:4px;font-size:.66rem;font-weight:600;color:var(--ocean-3);cursor:pointer;transition:all var(--transition);}
.toggle-detail:hover{background:rgba(109,184,232,.1);}

/* ── Membership card (detail expand) ── */
.detail-row td{padding:0 !important;background:rgba(0,0,0,.22) !important;border-bottom:2px solid rgba(255,200,71,.1) !important;}
.detail-inner{padding:1.2rem 1.6rem;}

/* Plan banner inside detail */
.plan-banner{
  display:flex;align-items:center;gap:1.2rem;
  background:var(--plan-bg,rgba(212,175,55,.08));
  border:1px solid var(--plan-border,rgba(212,175,55,.2));
  border-radius:var(--radius-sm);
  padding:1rem 1.2rem;
  margin-bottom:1rem;
  position:relative;overflow:hidden;
}
.plan-banner::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:var(--plan-color,#d4af37);opacity:.7;}
.plan-icon-lg{font-size:2rem;line-height:1;}
.plan-info{}
.plan-name-lg{font-family:var(--font-display);font-size:1rem;font-weight:700;color:var(--white);letter-spacing:.06em;margin-bottom:.2rem;}
.plan-detail-sub{font-size:.78rem;color:var(--ocean-2);}

.detail-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:.8rem;}
.detail-box{background:rgba(255,255,255,.04);border:1px solid rgba(255,200,71,.1);border-radius:var(--radius-sm);padding:.75rem .9rem;}
.detail-label{font-size:.58rem;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:rgba(184,221,245,.35);margin-bottom:.3rem;}
.detail-val{font-size:.82rem;font-weight:500;color:var(--ocean-1);}
.detail-val.empty{color:rgba(184,221,245,.28);font-style:italic;font-size:.76rem;}
.auto-renew-pill{display:inline-block;padding:.15rem .55rem;border-radius:100px;font-size:.62rem;font-weight:700;
  background:rgba(0,214,143,.1);border:1px solid rgba(0,214,143,.25);color:var(--success);}

.reject-form-wrap{margin-top:1rem;display:flex;gap:.7rem;align-items:center;}
.reject-form-wrap input{flex:1;padding:.6rem .9rem;background:rgba(255,255,255,.05);
  border:1px solid rgba(255,77,109,.25);border-radius:var(--radius-sm);
  font-family:var(--font-body);font-size:.84rem;color:var(--ocean-1);outline:none;}
.reject-form-wrap input:focus{border-color:#ff4d6d;}

/* ── Empty ── */
.empty-state{text-align:center;padding:4rem;color:rgba(184,221,245,.35);}
.empty-state .e-icon{font-size:3rem;display:block;margin-bottom:1rem;}
.empty-state p{font-size:.9rem;}

/* ── Pagination ── */
.pagination{display:flex;align-items:center;justify-content:center;gap:.45rem;margin-top:1.2rem;
  opacity:0;animation:fadeUp .5s .4s ease forwards;}
.page-btn{padding:.4rem .85rem;border-radius:var(--radius-sm);font-size:.73rem;font-weight:600;
  letter-spacing:.06em;text-decoration:none;border:1px solid rgba(255,200,71,.2);
  color:var(--ocean-3);transition:all var(--transition);}
.page-btn:hover,.page-btn.current{background:rgba(255,200,71,.1);border-color:rgba(255,200,71,.4);color:var(--accent);}
.page-btn.disabled{opacity:.3;pointer-events:none;}

/* ── Duration badge ── */
.dur-badge{background:rgba(42,140,196,.1);border:1px solid rgba(42,140,196,.2);border-radius:4px;
  padding:.12rem .5rem;font-size:.65rem;font-weight:600;color:var(--ocean-3);}

@keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}

@media(max-width:1100px){.stats-strip{grid-template-columns:repeat(3,1fr);}.detail-grid{grid-template-columns:1fr 1fr;}}
@media(max-width:768px){nav{padding:1rem 1rem;}.page{padding:1.2rem .8rem 4rem;}.stats-strip{grid-template-columns:repeat(2,1fr);}.toolbar{flex-direction:column;align-items:stretch;}.search-wrap{max-width:100%;}}
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
    <a href="pending.php">Enrollments</a>
    <a href="m_pending.php" class="active">Memberships</a>
    <a href="coach.php">Coaches</a>
    <a href="logout.php">Logout</a>
  </div>
  <div class="breadcrumb">
    <a href="admin.php">Admin</a> / <span style="color:var(--ocean-2);">Membership Approvals</span>
  </div>
</nav>

<div class="page">

  <div class="page-header">
    <div>
      <h1>🏅 Membership Approvals</h1>
      <p>Review and manage club membership applications</p>
    </div>
    <a href="pending.php" style="display:inline-flex;align-items:center;gap:.5rem;padding:.6rem 1.2rem;background:rgba(0,200,255,.1);border:1px solid rgba(0,200,255,.25);border-radius:var(--radius-sm);font-size:.74rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#00c8ff;text-decoration:none;transition:all var(--transition);">
      📋 Enrollment Queue →
    </a>
  </div>

  <!-- Stats -->
  <div class="stats-strip">
    <div class="s-card">
      <div class="s-num"><?= number_format($stats['pending']) ?></div>
      <div class="s-label">Pending</div>
    </div>
    <div class="s-card green">
      <div class="s-num"><?= number_format($stats['active']) ?></div>
      <div class="s-label">Active</div>
    </div>
    <div class="s-card pur">
      <div class="s-num"><?= number_format($stats['paused']) ?></div>
      <div class="s-label">Paused</div>
    </div>
    <div class="s-card blue">
      <div class="s-num"><?= number_format($stats['expired']) ?></div>
      <div class="s-label">Expired</div>
    </div>
    <div class="s-card red">
      <div class="s-num"><?= number_format($stats['cancelled']) ?></div>
      <div class="s-label">Cancelled</div>
    </div>
    <div class="s-card green">
      <div class="s-num">₹<?= number_format($stats['revenue']) ?></div>
      <div class="s-label">Active Revenue</div>
    </div>
  </div>

  <!-- Tabs -->
  <div class="tab-row">
    <div class="tab-bar">
      <?php
        $tabs = [
          'pending'   => ['Pending', $stats['pending']],
          'active'    => ['Active', null],
          'paused'    => ['Paused', null],
          'expired'   => ['Expired', null],
          'cancelled' => ['Cancelled', null],
          'all'       => ['All', null],
        ];
        foreach ($tabs as $key => [$label, $count]):
          $href = "?tab=$key&search=" . urlencode($search) . "&plan=" . urlencode($plan_filter);
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
      <input type="text" name="search" placeholder="Name, phone, email…"
             value="<?= htmlspecialchars($search) ?>" oninput="this.form.submit()">
    </div>
    <select name="plan" class="plan-filter" onchange="this.form.submit()">
      <option value="">All Plans</option>
      <option value="bronze"   <?= $plan_filter === 'bronze'   ? 'selected' : '' ?>>🥉 Bronze</option>
      <option value="silver"   <?= $plan_filter === 'silver'   ? 'selected' : '' ?>>🥈 Silver</option>
      <option value="gold"     <?= $plan_filter === 'gold'     ? 'selected' : '' ?>>🥇 Gold</option>
      <option value="platinum" <?= $plan_filter === 'platinum' ? 'selected' : '' ?>>💎 Platinum</option>
    </select>
    <span class="count-info"><?= number_format($total_rows) ?> record<?= $total_rows != 1 ? 's' : '' ?></span>
  </form>

  <!-- Table -->
  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th></th>
          <th>Plan</th>
          <th>Member Name</th>
          <th>Account Holder</th>
          <th>Duration</th>
          <th>Starts</th>
          <th>Amount</th>
          <th>Status</th>
          <th>Applied</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($memberships)): ?>
        <tr>
          <td colspan="10">
            <div class="empty-state">
              <span class="e-icon">📭</span>
              <p>No membership records<?= $tab === 'pending' ? ' awaiting review' : '' ?>.</p>
            </div>
          </td>
        </tr>

        <?php else: foreach ($memberships as $m):
          [$p_color, $p_icon, $p_bg, $p_border] = $plan_meta[$m['plan']] ?? ['#6db8e8', '🏅', 'rgba(109,184,232,.12)', 'rgba(109,184,232,.3)'];
          $detail_id = 'mdetail-' . $m['id'];
        ?>

        <tr>
          <td>
            <button type="button" class="toggle-detail" onclick="toggleDetail('<?= $detail_id ?>')">▾</button>
          </td>
          <td>
            <span class="plan-chip" style="color:<?= $p_color ?>;border-color:<?= $p_border ?>;background:<?= $p_bg ?>;">
              <?= $p_icon ?> <?= ucfirst($m['plan']) ?>
            </span>
          </td>
          <td>
            <strong style="color:var(--ocean-1);"><?= htmlspecialchars($m['full_name']) ?></strong>
          </td>
          <td>
            <?= htmlspecialchars($m['first_name'] . ' ' . $m['last_name']) ?>
            <br><span class="muted"><?= htmlspecialchars($m['user_phone']) ?></span>
          </td>
          <td>
            <span class="dur-badge"><?= $m['duration_months'] ?> mo.</span>
            <?php if ($m['auto_renew']): ?>
              <span style="font-size:.65rem;color:#00d68f;margin-left:.3rem;">🔄</span>
            <?php endif; ?>
          </td>
          <td class="muted"><?= date('d M Y', strtotime($m['start_date'])) ?></td>
          <td style="font-weight:600;color:var(--ocean-2);">₹<?= number_format($m['payment_amount']) ?></td>
          <td>
            <span class="pill pill-<?= $m['status'] ?>"><?= ucfirst($m['status']) ?></span>
          </td>
          <td class="muted"><?= date('d M Y', strtotime($m['created_at'])) ?></td>
          <td>
            <div class="actions">
              <?php if ($m['status'] === 'pending'): ?>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="membership_id" value="<?= $m['id'] ?>">
                  <input type="hidden" name="action" value="approve">
                  <input type="hidden" name="current_tab" value="<?= $tab ?>">
                  <button type="submit" class="btn-approve">✓ Approve</button>
                </form>
                <button type="button" class="btn-reject" onclick="openReject('<?= $detail_id ?>')">✕ Reject</button>

              <?php elseif ($m['status'] === 'active'): ?>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="membership_id" value="<?= $m['id'] ?>">
                  <input type="hidden" name="action" value="pause">
                  <input type="hidden" name="current_tab" value="<?= $tab ?>">
                  <button type="submit" class="btn-pause">⏸ Pause</button>
                </form>

              <?php elseif ($m['status'] === 'paused'): ?>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="membership_id" value="<?= $m['id'] ?>">
                  <input type="hidden" name="action" value="reactivate">
                  <input type="hidden" name="current_tab" value="<?= $tab ?>">
                  <button type="submit" class="btn-approve">▶ Reactivate</button>
                </form>

              <?php elseif ($m['status'] === 'cancelled' || $m['status'] === 'expired'): ?>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="membership_id" value="<?= $m['id'] ?>">
                  <input type="hidden" name="action" value="restore">
                  <input type="hidden" name="current_tab" value="<?= $tab ?>">
                  <button type="submit" class="btn-restore">↩ Restore</button>
                </form>

              <?php else: ?>
                <span class="muted" style="font-size:.72rem;">No action</span>
              <?php endif; ?>
            </div>
          </td>
        </tr>

        <!-- Detail expand row -->
        <tr class="detail-row" id="<?= $detail_id ?>" style="display:none;">
          <td colspan="10">
            <div class="detail-inner">

              <!-- Plan banner -->
              <div class="plan-banner" style="--plan-color:<?= $p_color ?>;--plan-bg:<?= $p_bg ?>;--plan-border:<?= $p_border ?>;">
                <div class="plan-icon-lg"><?= $p_icon ?></div>
                <div class="plan-info">
                  <div class="plan-name-lg"><?= ucfirst($m['plan']) ?> Membership</div>
                  <div class="plan-detail-sub">
                    <?= $m['duration_months'] ?> month<?= $m['duration_months'] > 1 ? 's' : '' ?>
                    · ₹<?= number_format($m['payment_amount']) ?>
                    · <?= $m['auto_renew'] ? '<span class="auto-renew-pill">🔄 Auto-renew</span>' : 'No auto-renew' ?>
                  </div>
                </div>
                <div style="margin-left:auto;text-align:right;">
                  <div style="font-size:.68rem;color:rgba(184,221,245,.4);margin-bottom:.2rem;">Period</div>
                  <div style="font-size:.82rem;color:var(--ocean-2);">
                    <?= date('d M Y', strtotime($m['start_date'])) ?> →
                    <?= date('d M Y', strtotime($m['end_date'])) ?>
                  </div>
                </div>
              </div>

              <!-- Detail boxes -->
              <div class="detail-grid">
                <div class="detail-box">
                  <div class="detail-label">Date of Birth</div>
                  <div class="detail-val <?= $m['dob'] ? '' : 'empty' ?>">
                    <?= $m['dob'] ? date('d M Y', strtotime($m['dob'])) : 'Not provided' ?>
                  </div>
                </div>
                <div class="detail-box">
                  <div class="detail-label">Emergency Contact</div>
                  <div class="detail-val <?= $m['emergency_contact'] ? '' : 'empty' ?>">
                    <?= htmlspecialchars($m['emergency_contact'] ?? 'Not provided') ?>
                  </div>
                </div>
                <div class="detail-box">
                  <div class="detail-label">Member Phone</div>
                  <div class="detail-val <?= $m['phone'] ? '' : 'empty' ?>">
                    <?= $m['phone'] ? htmlspecialchars($m['phone']) : 'Not provided' ?>
                  </div>
                </div>
                <div class="detail-box">
                  <div class="detail-label">Member Email</div>
                  <div class="detail-val <?= $m['email'] ? '' : 'empty' ?>">
                    <?= $m['email'] ? htmlspecialchars($m['email']) : 'Not provided' ?>
                  </div>
                </div>
                <div class="detail-box">
                  <div class="detail-label">Payment Method</div>
                  <div class="detail-val <?= $m['payment_method'] ? '' : 'empty' ?>">
                    <?= $m['payment_method'] ? ucwords(str_replace('_', ' ', $m['payment_method'])) : 'Not specified' ?>
                  </div>
                </div>
                <div class="detail-box">
                  <div class="detail-label">Payment Reference</div>
                  <div class="detail-val <?= $m['payment_reference'] ? '' : 'empty' ?>">
                    <?= $m['payment_reference'] ? htmlspecialchars($m['payment_reference']) : 'Not provided' ?>
                  </div>
                </div>
                <div class="detail-box">
                  <div class="detail-label">Account Phone</div>
                  <div class="detail-val"><?= htmlspecialchars($m['user_phone']) ?></div>
                </div>
                <div class="detail-box">
                  <div class="detail-label">Account Email</div>
                  <div class="detail-val <?= $m['user_email'] ? '' : 'empty' ?>">
                    <?= $m['user_email'] ? htmlspecialchars($m['user_email']) : 'Not provided' ?>
                  </div>
                </div>
                <?php if ($m['medical_notes']): ?>
                <div class="detail-box" style="grid-column:span 4;border-color:rgba(255,200,71,.15);">
                  <div class="detail-label">Medical / Physical Notes</div>
                  <div class="detail-val"><?= htmlspecialchars($m['medical_notes']) ?></div>
                </div>
                <?php endif; ?>
              </div>

              <?php if ($m['status'] === 'pending'): ?>
              <!-- Inline reject form -->
              <form method="POST" class="reject-form-wrap" id="mreject-<?= $m['id'] ?>">
                <input type="hidden" name="membership_id" value="<?= $m['id'] ?>">
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
    $base_url = "?tab=$tab&search=" . urlencode($search) . "&plan=" . urlencode($plan_filter);
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
  row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
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