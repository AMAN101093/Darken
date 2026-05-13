<?php
// database.php — Users & Bookings Management (Admin only)
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=database.php');
    exit;
}

require_once 'config/db.php';

// Role guard
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

// ── Filters ───────────────────────────────────────────────────────────────────
$filter_tab   = $_GET['tab']    ?? 'users';      // users | bookings | memberships
$filter_status = $_GET['filter'] ?? '';
$search       = trim($_GET['search'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$per_page     = 20;
$offset       = ($page - 1) * $per_page;

// ── Handle role change (POST) ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $target_id = (int)($_POST['user_id'] ?? 0);
    $new_role  = $_POST['new_role'] ?? '';
    if ($target_id && in_array($new_role, ['user','coach','admin'])) {
        $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$new_role, $target_id]);
        header('Location: database.php?tab=users&msg=role_updated');
        exit;
    }
}

// ── Data fetchers ─────────────────────────────────────────────────────────────
$msg = $_GET['msg'] ?? '';

// --- USERS ---
$user_where = "WHERE u.is_active = 1";
$user_params = [];
if ($search) {
    $user_where .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.phone LIKE ? OR u.email LIKE ?)";
    $s = "%$search%";
    $user_params = [$s,$s,$s,$s];
}
if ($filter_status === 'admin')  $user_where .= " AND u.role = 'admin'";
if ($filter_status === 'coach')  $user_where .= " AND u.role = 'coach'";
if ($filter_status === 'member') $user_where .= " AND u.role = 'user'";

$user_count = $pdo->prepare("SELECT COUNT(*) FROM users u $user_where");
$user_count->execute($user_params);
$total_users = (int)$user_count->fetchColumn();
$total_pages_users = max(1, ceil($total_users / $per_page));

$users_stmt = $pdo->prepare("
    SELECT u.id, u.first_name, u.middle_name, u.last_name, u.phone, u.email, u.role, u.created_at,
           COUNT(DISTINCT b.id) AS booking_count,
           ANY_VALUE(m.plan) AS mem_plan, ANY_VALUE(m.status) AS mem_status
    FROM users u
    LEFT JOIN bookings b ON b.booked_by_user_id = u.id
    LEFT JOIN memberships m ON m.user_id = u.id AND m.status IN ('active','pending')
    $user_where
    GROUP BY u.id
    ORDER BY u.created_at DESC
    LIMIT $per_page OFFSET $offset
");
$users_stmt->execute($user_params);
$users = $users_stmt->fetchAll();

// --- BOOKINGS ---
$book_where = "WHERE 1=1";
$book_params = [];
if ($search) {
    $book_where .= " AND (b.swimmer_name LIKE ? OR b.booking_reference LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $s = "%$search%";
    $book_params = [$s,$s,$s,$s];
}
if ($filter_status && in_array($filter_status,['pending','confirmed','active','completed','cancelled'])) {
    $book_where .= " AND b.status = '$filter_status'";
}

$book_count = $pdo->prepare("SELECT COUNT(*) FROM bookings b JOIN users u ON u.id = b.booked_by_user_id $book_where");
$book_count->execute($book_params);
$total_bookings = (int)$book_count->fetchColumn();
$total_pages_bookings = max(1, ceil($total_bookings / $per_page));

$bookings_stmt = $pdo->prepare("
    SELECT b.*, u.first_name, u.last_name, u.phone
    FROM bookings b
    JOIN users u ON u.id = b.booked_by_user_id
    $book_where
    ORDER BY b.created_at DESC
    LIMIT $per_page OFFSET $offset
");
$bookings_stmt->execute($book_params);
$bookings = $bookings_stmt->fetchAll();

// --- MEMBERSHIPS ---
$mem_where = "WHERE 1=1";
$mem_params = [];
if ($search) {
    $mem_where .= " AND (m.full_name LIKE ? OR u.phone LIKE ?)";
    $s = "%$search%";
    $mem_params = [$s,$s];
}
if ($filter_status && in_array($filter_status,['pending','active','paused','expired','cancelled'])) {
    $mem_where .= " AND m.status = '$filter_status'";
}

$mem_count = $pdo->prepare("SELECT COUNT(*) FROM memberships m JOIN users u ON u.id = m.user_id $mem_where");
$mem_count->execute($mem_params);
$total_memberships = (int)$mem_count->fetchColumn();
$total_pages_memberships = max(1, ceil($total_memberships / $per_page));

$memberships_stmt = $pdo->prepare("
    SELECT m.*, u.first_name, u.last_name, u.phone, u.email
    FROM memberships m
    JOIN users u ON u.id = m.user_id
    $mem_where
    ORDER BY m.created_at DESC
    LIMIT $per_page OFFSET $offset
");
$memberships_stmt->execute($mem_params);
$memberships = $memberships_stmt->fetchAll();

// Summary stats
$stats = [
    'users'      => $pdo->query("SELECT COUNT(*) FROM users WHERE is_active=1")->fetchColumn(),
    'bookings'   => $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn(),
    'pending'    => $pdo->query("SELECT COUNT(*) FROM bookings WHERE status='pending'")->fetchColumn(),
    'active_mem' => $pdo->query("SELECT COUNT(*) FROM memberships WHERE status='active'")->fetchColumn(),
    'revenue'    => $pdo->query("SELECT SUM(payment_amount) FROM bookings WHERE payment_status='paid'")->fetchColumn() ?? 0,
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Database — Darken Shadows SC Admin</title>
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
  --card-bg:rgba(13,61,107,.4); --card-border:rgba(109,184,232,.15);
  --transition:.22s cubic-bezier(.4,0,.2,1);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html{scroll-behavior:smooth;}
body{font-family:var(--font-body);background:var(--ocean-7);color:var(--ocean-1);min-height:100vh;}
body::before{content:'';position:fixed;inset:0;z-index:0;
  background:radial-gradient(ellipse 65% 50% at 10% 5%,rgba(0,200,255,.12) 0%,transparent 55%),
  linear-gradient(160deg,var(--ocean-7) 0%,#092840 60%,var(--ocean-7) 100%);pointer-events:none;}
body::after{content:'';position:fixed;inset:0;z-index:0;
  background-image:radial-gradient(circle at 1px 1px,rgba(109,184,232,.04) 1px,transparent 0);
  background-size:36px 36px;pointer-events:none;}

/* ── Nav ── */
nav{position:sticky;top:0;z-index:100;display:flex;align-items:center;justify-content:space-between;
  padding:1rem 2.5rem;background:rgba(6,30,53,.92);backdrop-filter:blur(20px);
  border-bottom:1px solid rgba(0,200,255,.15);}
.nav-logo{font-family:var(--font-display);font-size:.95rem;font-weight:700;letter-spacing:.12em;color:var(--accent);text-decoration:none;text-transform:uppercase;}
.nav-links{display:flex;gap:1.4rem;align-items:center;}
.nav-links a{font-size:.76rem;font-weight:600;letter-spacing:.1em;color:var(--ocean-2);text-decoration:none;text-transform:uppercase;transition:color var(--transition);}
.nav-links a:hover,.nav-links a.active{color:var(--accent);}
.breadcrumb{font-size:.75rem;color:rgba(184,221,245,.4);display:flex;align-items:center;gap:.5rem;}
.breadcrumb a{color:rgba(184,221,245,.4);text-decoration:none;transition:color var(--transition);}
.breadcrumb a:hover{color:var(--ocean-2);}

/* ── Page ── */
.page{position:relative;z-index:1;max-width:1300px;margin:0 auto;padding:2rem 2rem 5rem;}

/* ── Page header ── */
.page-header{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:1.8rem;
  opacity:0;animation:fadeUp .5s .1s ease forwards;}
.page-header h1{font-family:var(--font-display);font-size:clamp(1.3rem,2.5vw,1.8rem);font-weight:700;
  color:var(--white);letter-spacing:.04em;}
.page-header p{font-size:.82rem;color:var(--ocean-3);margin-top:.3rem;font-weight:300;}

/* ── Stats ── */
.stats-strip{display:grid;grid-template-columns:repeat(5,1fr);gap:.8rem;margin-bottom:1.8rem;
  opacity:0;animation:fadeUp .5s .18s ease forwards;}
.s-card{background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius-sm);
  padding:.9rem 1rem;text-align:center;backdrop-filter:blur(8px);}
.s-num{font-family:var(--font-display);font-size:1.5rem;font-weight:700;color:var(--accent);line-height:1;margin-bottom:.2rem;}
.s-label{font-size:.6rem;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:rgba(184,221,245,.4);}
.s-card.w .s-num{color:var(--warn);}
.s-card.g .s-num{color:var(--success);}
.s-card.d .s-num{color:var(--danger);}

/* ── Tabs ── */
.tab-bar{display:flex;gap:0;border-radius:var(--radius-sm);overflow:hidden;
  border:1px solid rgba(0,200,255,.18);width:fit-content;margin-bottom:1.5rem;
  opacity:0;animation:fadeUp .5s .24s ease forwards;}
.tab{padding:.6rem 1.4rem;font-size:.76rem;font-weight:600;letter-spacing:.1em;text-transform:uppercase;
  text-decoration:none;color:var(--ocean-2);transition:all var(--transition);border:none;background:transparent;cursor:pointer;}
.tab.active,.tab:hover{background:rgba(0,200,255,.12);color:var(--accent);}
.tab.active{border-bottom:2px solid var(--accent);}

/* ── Toolbar ── */
.toolbar{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1.2rem;
  opacity:0;animation:fadeUp .5s .3s ease forwards;flex-wrap:wrap;}
.search-wrap{display:flex;align-items:center;gap:.6rem;background:rgba(255,255,255,.05);
  border:1px solid rgba(0,200,255,.18);border-radius:var(--radius-sm);padding:.5rem .9rem;
  flex:1;max-width:360px;}
.search-wrap input{background:none;border:none;outline:none;font-family:var(--font-body);
  font-size:.85rem;color:var(--ocean-1);width:100%;}
.search-wrap input::placeholder{color:rgba(184,221,245,.3);}
.filter-pills{display:flex;gap:.4rem;flex-wrap:wrap;}
.filter-pill{padding:.3rem .85rem;border-radius:100px;font-size:.66rem;font-weight:600;
  letter-spacing:.1em;text-transform:uppercase;text-decoration:none;
  border:1px solid rgba(109,184,232,.22);color:var(--ocean-3);transition:all var(--transition);}
.filter-pill:hover,.filter-pill.active{background:rgba(0,200,255,.12);border-color:rgba(0,200,255,.4);color:var(--accent);}
.count-label{font-size:.75rem;color:rgba(184,221,245,.4);white-space:nowrap;}

/* ── Table ── */
.table-wrap{background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius);
  overflow:hidden;backdrop-filter:blur(10px);
  opacity:0;animation:fadeUp .5s .36s ease forwards;}
.data-table{width:100%;border-collapse:collapse;}
.data-table thead tr{background:rgba(0,200,255,.06);border-bottom:1px solid rgba(0,200,255,.12);}
.data-table th{padding:.65rem 1rem;font-size:.62rem;font-weight:700;letter-spacing:.16em;
  text-transform:uppercase;color:rgba(184,221,245,.4);text-align:left;white-space:nowrap;}
.data-table td{padding:.75rem 1rem;font-size:.82rem;color:var(--ocean-1);
  border-bottom:1px solid rgba(109,184,232,.07);vertical-align:middle;}
.data-table tr:last-child td{border-bottom:none;}
.data-table tbody tr:hover td{background:rgba(0,200,255,.04);}
.data-table td .muted{color:rgba(184,221,245,.4);font-size:.75rem;}

/* Pills & badges */
.pill{display:inline-block;padding:.18rem .65rem;border-radius:100px;
  font-size:.62rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;white-space:nowrap;}
.pill-user{background:rgba(109,184,232,.1);border:1px solid rgba(109,184,232,.2);color:var(--ocean-3);}
.pill-coach{background:rgba(0,229,176,.1);border:1px solid rgba(0,229,176,.2);color:#00e5b0;}
.pill-admin{background:rgba(255,200,71,.1);border:1px solid rgba(255,200,71,.2);color:#ffc847;}
.pill-bronze{background:rgba(205,127,50,.1);border:1px solid rgba(205,127,50,.25);color:#cd7f32;}
.pill-silver{background:rgba(168,169,173,.1);border:1px solid rgba(168,169,173,.2);color:#a8a9ad;}
.pill-gold{background:rgba(212,175,55,.12);border:1px solid rgba(212,175,55,.3);color:#d4af37;}
.pill-platinum{background:rgba(229,228,226,.08);border:1px solid rgba(229,228,226,.2);color:#e5e4e2;}
.pill-pending{background:rgba(255,200,71,.1);border:1px solid rgba(255,200,71,.2);color:#ffc847;}
.pill-active,.pill-confirmed{background:rgba(0,214,143,.1);border:1px solid rgba(0,214,143,.2);color:#00d68f;}
.pill-cancelled,.pill-expired{background:rgba(255,77,109,.1);border:1px solid rgba(255,77,109,.2);color:#ff4d6d;}
.pill-paused{background:rgba(181,123,238,.1);border:1px solid rgba(181,123,238,.2);color:#b57bee;}
.pill-completed{background:rgba(109,184,232,.1);border:1px solid rgba(109,184,232,.2);color:var(--ocean-3);}
.prog-chip{display:inline-block;font-size:.65rem;font-weight:600;letter-spacing:.06em;
  padding:.18rem .55rem;border-radius:4px;background:rgba(42,140,196,.15);color:var(--ocean-3);}

/* Role change inline form */
.role-form{display:flex;align-items:center;gap:.4rem;}
.role-select{background:rgba(255,255,255,.05);border:1px solid rgba(109,184,232,.2);
  border-radius:4px;padding:.2rem .5rem;font-family:var(--font-body);font-size:.75rem;
  color:var(--ocean-1);outline:none;cursor:pointer;}
.role-select option{background:var(--ocean-6);}
.role-btn{padding:.2rem .55rem;background:rgba(0,200,255,.12);border:1px solid rgba(0,200,255,.25);
  border-radius:4px;font-size:.66rem;font-weight:600;color:var(--accent);cursor:pointer;
  transition:all var(--transition);}
.role-btn:hover{background:rgba(0,200,255,.25);}

/* ── Pagination ── */
.pagination{display:flex;align-items:center;justify-content:center;gap:.5rem;margin-top:1.2rem;
  opacity:0;animation:fadeUp .5s .42s ease forwards;}
.page-btn{padding:.4rem .85rem;border-radius:var(--radius-sm);font-size:.75rem;font-weight:600;
  letter-spacing:.08em;text-decoration:none;border:1px solid rgba(109,184,232,.2);
  color:var(--ocean-3);transition:all var(--transition);}
.page-btn:hover,.page-btn.current{background:rgba(0,200,255,.12);border-color:rgba(0,200,255,.35);color:var(--accent);}
.page-btn.disabled{opacity:.3;pointer-events:none;}

/* ── Flash message ── */
.flash{position:fixed;top:80px;right:20px;z-index:200;
  background:rgba(0,214,143,.12);border:1px solid rgba(0,214,143,.3);
  border-radius:var(--radius-sm);padding:.75rem 1.2rem;
  font-size:.82rem;font-weight:500;color:#00d68f;
  animation:slideIn .4s ease;}
@keyframes slideIn{from{opacity:0;transform:translateX(30px)}to{opacity:1;transform:translateX(0)}}
@keyframes fadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}

/* empty state */
.empty-state{text-align:center;padding:3rem;color:rgba(184,221,245,.35);font-size:.9rem;}

@media(max-width:900px){.stats-strip{grid-template-columns:repeat(3,1fr);}
  .data-table th:nth-child(n+5),.data-table td:nth-child(n+5){display:none;}}
@media(max-width:600px){nav{padding:1rem 1rem;}.page{padding:1.2rem .8rem 4rem;}
  .stats-strip{grid-template-columns:repeat(2,1fr);}.toolbar{flex-direction:column;align-items:stretch;}}
</style>
</head>
<body>

<?php if ($msg === 'role_updated'): ?>
<div class="flash" id="flash-msg">✓ Role updated successfully</div>
<script>setTimeout(()=>document.getElementById('flash-msg')?.remove(), 3500);</script>
<?php endif; ?>

<nav>
  <a href="admin.php" class="nav-logo">⚙ Admin</a>
  <div class="nav-links">
    <a href="admin.php">Dashboard</a>
    <a href="database.php" class="active">Users</a>
    <a href="coach.php">Coaches</a>
    <a href="index.php">Site</a>
    <a href="logout.php">Logout</a>
  </div>
  <div class="breadcrumb">
    <a href="admin.php">Admin</a> / <span style="color:var(--ocean-2);">User Database</span>
  </div>
</nav>

<div class="page">

  <div class="page-header">
    <div>
      <h1>👥 User Database</h1>
      <p>All registered members, bookings, and membership records</p>
    </div>
  </div>

  <!-- Stats -->
  <div class="stats-strip">
    <div class="s-card"><div class="s-num"><?= $stats['users'] ?></div><div class="s-label">Members</div></div>
    <div class="s-card"><div class="s-num"><?= $stats['bookings'] ?></div><div class="s-label">Bookings</div></div>
    <div class="s-card w"><div class="s-num"><?= $stats['pending'] ?></div><div class="s-label">Pending</div></div>
    <div class="s-card g"><div class="s-num"><?= $stats['active_mem'] ?></div><div class="s-label">Active Memberships</div></div>
    <div class="s-card g"><div class="s-num">₹<?= number_format($stats['revenue']) ?></div><div class="s-label">Revenue (Paid)</div></div>
  </div>

  <!-- Tabs -->
  <div class="tab-bar">
    <a class="tab <?= $filter_tab==='users'?'active':'' ?>" href="?tab=users&search=<?= urlencode($search) ?>">
      Members (<?= $stats['users'] ?>)
    </a>
    <a class="tab <?= $filter_tab==='bookings'?'active':'' ?>" href="?tab=bookings&search=<?= urlencode($search) ?>">
      Bookings (<?= $stats['bookings'] ?>)
    </a>
    <a class="tab <?= $filter_tab==='memberships'?'active':'' ?>" href="?tab=memberships&search=<?= urlencode($search) ?>">
      Memberships (<?= $total_memberships ?>)
    </a>
  </div>

  <!-- Toolbar -->
  <div class="toolbar">
    <form method="GET" style="display:flex;align-items:center;gap:.8rem;flex-wrap:wrap;flex:1;">
      <input type="hidden" name="tab" value="<?= htmlspecialchars($filter_tab) ?>">
      <div class="search-wrap">
        <span style="color:rgba(184,221,245,.3);font-size:.9rem;">🔍</span>
        <input type="text" name="search" placeholder="Search name, phone, email, reference…"
               value="<?= htmlspecialchars($search) ?>" oninput="this.form.submit()">
      </div>

      <?php if ($filter_tab === 'users'): ?>
      <div class="filter-pills">
        <a class="filter-pill <?= !$filter_status?'active':'' ?>" href="?tab=users&search=<?= urlencode($search) ?>">All</a>
        <a class="filter-pill <?= $filter_status==='member'?'active':'' ?>" href="?tab=users&filter=member&search=<?= urlencode($search) ?>">Members</a>
        <a class="filter-pill <?= $filter_status==='coach'?'active':'' ?>"  href="?tab=users&filter=coach&search=<?= urlencode($search) ?>">Coaches</a>
        <a class="filter-pill <?= $filter_status==='admin'?'active':'' ?>"  href="?tab=users&filter=admin&search=<?= urlencode($search) ?>">Admins</a>
      </div>
      <?php elseif ($filter_tab === 'bookings'): ?>
      <div class="filter-pills">
        <a class="filter-pill <?= !$filter_status?'active':'' ?>"              href="?tab=bookings&search=<?= urlencode($search) ?>">All</a>
        <a class="filter-pill <?= $filter_status==='pending'?'active':'' ?>"   href="?tab=bookings&filter=pending&search=<?= urlencode($search) ?>">Pending</a>
        <a class="filter-pill <?= $filter_status==='confirmed'?'active':'' ?>" href="?tab=bookings&filter=confirmed&search=<?= urlencode($search) ?>">Confirmed</a>
        <a class="filter-pill <?= $filter_status==='active'?'active':'' ?>"    href="?tab=bookings&filter=active&search=<?= urlencode($search) ?>">Active</a>
        <a class="filter-pill <?= $filter_status==='completed'?'active':'' ?>" href="?tab=bookings&filter=completed&search=<?= urlencode($search) ?>">Completed</a>
        <a class="filter-pill <?= $filter_status==='cancelled'?'active':'' ?>" href="?tab=bookings&filter=cancelled&search=<?= urlencode($search) ?>">Cancelled</a>
      </div>
      <?php elseif ($filter_tab === 'memberships'): ?>
      <div class="filter-pills">
        <a class="filter-pill <?= !$filter_status?'active':'' ?>"             href="?tab=memberships&search=<?= urlencode($search) ?>">All</a>
        <a class="filter-pill <?= $filter_status==='active'?'active':'' ?>"   href="?tab=memberships&filter=active&search=<?= urlencode($search) ?>">Active</a>
        <a class="filter-pill <?= $filter_status==='pending'?'active':'' ?>"  href="?tab=memberships&filter=pending&search=<?= urlencode($search) ?>">Pending</a>
        <a class="filter-pill <?= $filter_status==='expired'?'active':'' ?>"  href="?tab=memberships&filter=expired&search=<?= urlencode($search) ?>">Expired</a>
        <a class="filter-pill <?= $filter_status==='paused'?'active':'' ?>"   href="?tab=memberships&filter=paused&search=<?= urlencode($search) ?>">Paused</a>
      </div>
      <?php endif; ?>

    </form>

    <?php
      $total_shown = $filter_tab==='users' ? $total_users : ($filter_tab==='bookings' ? $total_bookings : $total_memberships);
    ?>
    <span class="count-label"><?= number_format($total_shown) ?> record<?= $total_shown!=1?'s':'' ?></span>
  </div>

  <!-- ══ USERS TABLE ══ -->
  <?php if ($filter_tab === 'users'): ?>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr>
        <th>#</th><th>Name</th><th>Phone</th><th>Email</th>
        <th>Role</th><th>Membership</th><th>Bookings</th><th>Joined</th><th>Change Role</th>
      </tr></thead>
      <tbody>
        <?php if (empty($users)): ?>
        <tr><td colspan="9" class="empty-state">No members found.</td></tr>
        <?php else: foreach ($users as $i => $u):
          $full = trim($u['first_name'] . ' ' . ($u['middle_name'] ? $u['middle_name'].' ' : '') . $u['last_name']);
        ?>
        <tr>
          <td class="muted"><?= $offset + $i + 1 ?></td>
          <td><strong><?= htmlspecialchars($full) ?></strong></td>
          <td><?= htmlspecialchars($u['phone']) ?></td>
          <td><?= $u['email'] ? htmlspecialchars($u['email']) : '<span class="muted">—</span>' ?></td>
          <td><span class="pill pill-<?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span></td>
          <td>
            <?php if ($u['mem_plan']): ?>
              <span class="pill pill-<?= $u['mem_plan'] ?>"><?= ucfirst($u['mem_plan']) ?></span>
              &nbsp;<span class="pill pill-<?= $u['mem_status'] ?>"><?= ucfirst($u['mem_status']) ?></span>
            <?php else: ?>
              <span class="muted">None</span>
            <?php endif; ?>
          </td>
          <td style="text-align:center;"><?= $u['booking_count'] ?></td>
          <td class="muted"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
          <td>
            <form method="POST" class="role-form">
              <input type="hidden" name="action" value="change_role">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <select name="new_role" class="role-select">
                <option value="user"  <?= $u['role']==='user'?'selected':'' ?>>Member</option>
                <option value="coach" <?= $u['role']==='coach'?'selected':'' ?>>Coach</option>
                <option value="admin" <?= $u['role']==='admin'?'selected':'' ?>>Admin</option>
              </select>
              <button type="submit" class="role-btn">Save</button>
            </form>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <!-- ══ BOOKINGS TABLE ══ -->
  <?php elseif ($filter_tab === 'bookings'): ?>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr>
        <th>Reference</th><th>Program</th><th>Swimmer</th><th>Booked By</th>
        <th>Payment</th><th>Amount</th><th>Status</th><th>Date</th>
      </tr></thead>
      <tbody>
        <?php if (empty($bookings)): ?>
        <tr><td colspan="8" class="empty-state">No bookings found.</td></tr>
        <?php else: foreach ($bookings as $b): ?>
        <tr>
          <td style="font-family:'Cinzel',serif;font-size:.7rem;letter-spacing:.08em;color:rgba(184,221,245,.5);">
            <?= htmlspecialchars($b['booking_reference']) ?>
          </td>
          <td><span class="prog-chip"><?= htmlspecialchars(ucwords(str_replace('_',' ',$b['program']))) ?></span></td>
          <td>
            <?= htmlspecialchars($b['swimmer_name']) ?>
            <?php if (!$b['is_for_self']): ?>
              <br><span class="muted">(<?= htmlspecialchars($b['swimmer_relation']) ?>)</span>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($b['first_name'] . ' ' . $b['last_name']) ?><br>
              <span class="muted"><?= htmlspecialchars($b['phone']) ?></span></td>
          <td><span class="pill pill-<?= $b['payment_status'] ?>"><?= ucfirst($b['payment_status']) ?></span></td>
          <td>₹<?= number_format($b['payment_amount']) ?></td>
          <td><span class="pill pill-<?= $b['status'] ?>"><?= ucfirst($b['status']) ?></span></td>
          <td class="muted"><?= date('d M Y', strtotime($b['created_at'])) ?></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <!-- ══ MEMBERSHIPS TABLE ══ -->
  <?php elseif ($filter_tab === 'memberships'): ?>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr>
        <th>Member</th><th>Plan</th><th>Duration</th><th>Starts</th>
        <th>Expires</th><th>Auto-Renew</th><th>Amount</th><th>Status</th>
      </tr></thead>
      <tbody>
        <?php if (empty($memberships)): ?>
        <tr><td colspan="8" class="empty-state">No memberships found.</td></tr>
        <?php else: foreach ($memberships as $m): ?>
        <tr>
          <td>
            <?= htmlspecialchars($m['full_name']) ?><br>
            <span class="muted"><?= htmlspecialchars($m['first_name'] . ' ' . $m['last_name']) ?>
            · <?= htmlspecialchars($m['phone']) ?></span>
          </td>
          <td><span class="pill pill-<?= $m['plan'] ?>"><?= ucfirst($m['plan']) ?></span></td>
          <td><?= $m['duration_months'] ?> mo.</td>
          <td class="muted"><?= date('d M Y', strtotime($m['start_date'])) ?></td>
          <td class="muted"><?= date('d M Y', strtotime($m['end_date'])) ?></td>
          <td><?= $m['auto_renew'] ? '<span style="color:#00d68f;">🔄 Yes</span>' : '<span class="muted">No</span>' ?></td>
          <td>₹<?= number_format($m['payment_amount']) ?></td>
          <td><span class="pill pill-<?= $m['status'] ?>"><?= ucfirst($m['status']) ?></span></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <!-- Pagination -->
  <?php
    $total_pages = $filter_tab==='users' ? $total_pages_users : ($filter_tab==='bookings' ? $total_pages_bookings : $total_pages_memberships);
    if ($total_pages > 1):
  ?>
  <div class="pagination">
    <?php
      $base = "?tab=$filter_tab&search=".urlencode($search)."&filter=$filter_status";
      if ($page > 1): ?>
        <a class="page-btn" href="<?= $base ?>&page=<?= $page-1 ?>">← Prev</a>
    <?php endif;
      for ($i = max(1,$page-2); $i <= min($total_pages,$page+2); $i++): ?>
        <a class="page-btn <?= $i===$page?'current':'' ?>" href="<?= $base ?>&page=<?= $i ?>"><?= $i ?></a>
    <?php endfor;
      if ($page < $total_pages): ?>
        <a class="page-btn" href="<?= $base ?>&page=<?= $page+1 ?>">Next →</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

</div>
</body>
</html>