<?php
// m_coach.php — Admin: Manage Coaching Staff (full control)
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=m_coach.php');
    exit;
}

require_once 'config/db.php';

// ── Role guard — admins only ───────────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT id, first_name, last_name, role FROM users WHERE id = ? AND is_active = 1");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();

if (!$admin || $admin['role'] !== 'admin') {
    http_response_code(403);
    die('<!DOCTYPE html><html><head><title>Access Denied</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=Raleway:wght@400&display=swap" rel="stylesheet">
    <style>*{margin:0;padding:0;box-sizing:border-box;}body{background:#061e35;color:#b8ddf5;font-family:Raleway,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;flex-direction:column;gap:1.5rem;}h1{font-family:Cinzel,serif;color:#ff4d6d;font-size:2rem;}p{color:#6db8e8;}a{color:#6db8e8;}</style>
    </head><body><h1>403 — Access Denied</h1><p>Admins only.</p><a href="index.php">← Back</a></body></html>');
}

$admin_id = (int)$admin['id'];

// ── Table existence guards ─────────────────────────────────────────────────────
function table_exists(PDO $pdo, string $table): bool {
    try { $pdo->query("SELECT 1 FROM `$table` LIMIT 1"); return true; }
    catch (PDOException $e) { return false; }
}
$tbl_coaches     = table_exists($pdo, 'coaches');
$tbl_attendance  = table_exists($pdo, 'coach_attendance');
$tbl_assignments = table_exists($pdo, 'coach_assignments');

// ── POST handler ──────────────────────────────────────────────────────────────
$flash      = '';
$flash_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Edit coach profile ────────────────────────────────────────────────────
    if ($action === 'edit_coach' && $tbl_coaches) {
        $cid          = (int)$_POST['coach_id'];
        $spec         = trim($_POST['specialisation'] ?? '');
        $qual         = trim($_POST['qualification']  ?? '');
        $bio          = trim($_POST['bio']            ?? '');
        $phone_direct = trim($_POST['phone_direct']   ?? '');
        $hire_date    = $_POST['hire_date'] ?: null;
        $programs_raw = $_POST['assigned_programs'] ?? [];
        $allowed_progs = ['junior_development','competitive_squad','elite_coaching',
                          'adult_fitness_swim','mental_conditioning','masters_program'];
        $programs = implode(',', array_filter($programs_raw, fn($p) => in_array($p, $allowed_progs)));

        if ($cid) {
            $pdo->prepare("
                UPDATE coaches SET
                    specialisation    = ?,
                    qualification     = ?,
                    bio               = ?,
                    phone_direct      = ?,
                    hire_date         = ?,
                    assigned_programs = ?
                WHERE id = ?
            ")->execute([$spec ?: null, $qual ?: null, $bio ?: null,
                         $phone_direct ?: null, $hire_date, $is_head,
                         $programs ?: null, $cid]);
            $flash = 'Coach profile updated successfully.';
        }
    }

    // ── Toggle active status ──────────────────────────────────────────────────
    if ($action === 'toggle_active') {
        $uid        = (int)$_POST['user_id'];
        $new_active = (int)$_POST['new_active'];
        if ($uid && in_array($new_active, [0, 1])) {
            $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?")->execute([$new_active, $uid]);
            $flash = $new_active ? 'Coach reactivated.' : 'Coach deactivated.';
        }
    }

    // ── Demote coach → user ───────────────────────────────────────────────────
    if ($action === 'demote_coach') {
        $uid = (int)$_POST['user_id'];
        if ($uid) {
            $pdo->prepare("UPDATE users SET role = 'user' WHERE id = ?")->execute([$uid]);
            $flash = 'Coach demoted to member. Attendance history preserved.';
        }
    }

    // ── Override / add attendance entry ──────────────────────────────────────
    if ($action === 'override_attendance' && $tbl_attendance) {
        $cid        = (int)$_POST['coach_id'];
        $sess_date  = $_POST['session_date'] ?? date('Y-m-d');
        $att_status = in_array($_POST['att_status'] ?? '', ['present','absent','late','leave'])
                      ? $_POST['att_status'] : 'present';
        $att_notes  = trim($_POST['att_notes'] ?? '');

        if ($cid && $sess_date) {
            $pdo->prepare("
                INSERT INTO coach_attendance (coach_id, session_date, status, notes, recorded_by)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    status = VALUES(status),
                    notes  = VALUES(notes),
                    recorded_by = VALUES(recorded_by)
            ")->execute([$cid, $sess_date, $att_status, $att_notes ?: null, $admin_id]);
            $flash = 'Attendance record saved for ' . date('d M Y', strtotime($sess_date)) . '.';
        }
    }

    // ── Delete attendance entry ───────────────────────────────────────────────
    if ($action === 'delete_attendance' && $tbl_attendance) {
        $att_id = (int)$_POST['att_id'];
        if ($att_id) {
            $pdo->prepare("DELETE FROM coach_attendance WHERE id = ?")->execute([$att_id]);
            $flash = 'Attendance entry removed.';
        }
    }

    // ── Assign student to coach ───────────────────────────────────────────────
    if ($action === 'assign_student' && $tbl_assignments) {
        $assign_coach_id   = (int)($_POST['assign_coach_id']  ?? 0);
        $assign_booking_id = (int)($_POST['booking_id']       ?? 0);
        $assign_notes      = trim($_POST['assign_notes']      ?? '');

        if ($assign_coach_id && $assign_booking_id) {
            $binfo = $pdo->prepare("SELECT booked_by_user_id, program FROM bookings WHERE id = ? AND program != 'membership'");
            $binfo->execute([$assign_booking_id]);
            $brow = $binfo->fetch();
            if ($brow) {
                try {
                    $pdo->prepare("
                        INSERT INTO coach_assignments
                            (coach_id, booking_id, student_user_id, program, assigned_by, notes)
                        VALUES (?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                            status      = 'active',
                            notes       = VALUES(notes),
                            assigned_by = VALUES(assigned_by)
                    ")->execute([
                        $assign_coach_id, $assign_booking_id,
                        $brow['booked_by_user_id'], $brow['program'],
                        $admin_id, $assign_notes ?: null,
                    ]);
                    $flash = 'Student assigned to coach successfully.';
                } catch (PDOException $e) {
                    $flash      = 'Assignment error: ' . $e->getMessage();
                    $flash_type = 'error';
                }
            }
        }
    }

    // ── Remove assignment ─────────────────────────────────────────────────────
    if ($action === 'remove_assignment' && $tbl_assignments) {
        $asgn_id = (int)($_POST['assignment_id'] ?? 0);
        if ($asgn_id) {
            $pdo->prepare("UPDATE coach_assignments SET status = 'removed' WHERE id = ?")->execute([$asgn_id]);
            $flash = 'Assignment removed.';
        }
    }

    // ── Promote user to coach ─────────────────────────────────────────────────
    if ($action === 'promote_to_coach' && $tbl_coaches) {
        $uid  = (int)$_POST['user_id'];
        $spec = trim($_POST['specialisation'] ?? '');
        $qual = trim($_POST['qualification']  ?? '');
        $hire = $_POST['hire_date'] ?: date('Y-m-d');
        if ($uid) {
            $pdo->prepare("UPDATE users SET role = 'coach' WHERE id = ?")->execute([$uid]);
            $pdo->prepare("
                INSERT IGNORE INTO coaches (user_id, specialisation, qualification, hire_date,)
                VALUES (?, ?, ?, ?)
            ")->execute([$uid, $spec ?: null, $qual ?: null, $hire, $head]);
            $flash = 'User promoted to coach successfully.';
        }
    }

    header('Location: m_coach.php?coach=' . (int)($_POST['coach_id'] ?? 0)
         . '&att_month=' . urlencode($_POST['att_month'] ?? date('Y-m'))
         . '&msg='       . urlencode($flash)
         . '&mtype='     . $flash_type);
    exit;
}

if (isset($_GET['msg'])) {
    $flash      = htmlspecialchars($_GET['msg']);
    $flash_type = $_GET['mtype'] ?? 'success';
}

// ── Fetch all coaches ─────────────────────────────────────────────────────────
$coaches = [];
if ($tbl_coaches) {
    $coaches = $pdo->query("
        SELECT c.id AS coach_id, c.user_id, c.specialisation, c.qualification, c.bio,
               c.phone_direct, c.hire_date, c.assigned_programs,
               u.first_name, u.last_name, u.email, u.phone, u.is_active,
               u.created_at AS joined_at
        FROM coaches c
        JOIN users u ON u.id = c.user_id
        ORDER BY u.first_name ASC
    ")->fetchAll();
}

// ── Selected coach detail ─────────────────────────────────────────────────────
$sel_coach_id = isset($_GET['coach']) ? (int)$_GET['coach'] : ($coaches[0]['coach_id'] ?? 0);
$sc = null;
foreach ($coaches as $c) {
    if ((int)$c['coach_id'] === $sel_coach_id) { $sc = $c; break; }
}

// ── Attendance data ───────────────────────────────────────────────────────────
$att_month  = $_GET['att_month'] ?? date('Y-m');
[$att_y, $att_m] = array_pad(explode('-', $att_month), 2, date('m'));
$prev_month = date('Y-m', mktime(0,0,0,(int)$att_m-1,1,(int)$att_y));
$next_month = date('Y-m', mktime(0,0,0,(int)$att_m+1,1,(int)$att_y));

$attendance_log      = [];
$att_stats_month     = ['present'=>0,'absent'=>0,'late'=>0,'leave'=>0];
$att_stats_30        = ['present'=>0,'absent'=>0,'late'=>0,'leave'=>0];

if ($sc && $tbl_attendance) {
    $s = $pdo->prepare("
        SELECT ca.id, ca.session_date, ca.status, ca.notes, ca.created_at,
               u.first_name AS rec_fn, u.last_name AS rec_ln
        FROM coach_attendance ca
        LEFT JOIN users u ON u.id = ca.recorded_by
        WHERE ca.coach_id = ? AND DATE_FORMAT(ca.session_date,'%Y-%m') = ?
        ORDER BY ca.session_date DESC
    ");
    $s->execute([$sc['coach_id'], $att_month]);
    $attendance_log = $s->fetchAll();

    foreach ($attendance_log as $row) {
        if (isset($att_stats_month[$row['status']])) $att_stats_month[$row['status']]++;
    }

    $s2 = $pdo->prepare("
        SELECT status, COUNT(*) AS cnt FROM coach_attendance
        WHERE coach_id = ? AND session_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY status
    ");
    $s2->execute([$sc['coach_id']]);
    foreach ($s2->fetchAll() as $row) {
        if (isset($att_stats_30[$row['status']])) $att_stats_30[$row['status']] = (int)$row['cnt'];
    }
}
$att_total_30    = array_sum($att_stats_30);
$att_rate_30     = $att_total_30 > 0 ? round($att_stats_30['present'] / $att_total_30 * 100) : 0;
$att_total_month = array_sum($att_stats_month);

// ── Assignments for selected coach ────────────────────────────────────────────
$assignments         = [];
$assignable_bookings = [];

if ($sc && $tbl_assignments) {
    $s3 = $pdo->prepare("
        SELECT ca.id, ca.program, ca.assigned_at, ca.notes, ca.status AS asgn_status,
               b.id AS booking_id, b.booking_reference, b.swimmer_name, b.status AS booking_status,
               u.first_name AS st_fn, u.last_name AS st_ln, u.phone AS st_phone,
               ab.first_name AS by_fn, ab.last_name AS by_ln
        FROM coach_assignments ca
        JOIN bookings b ON b.id = ca.booking_id
        JOIN users u    ON u.id = ca.student_user_id
        JOIN users ab   ON ab.id = ca.assigned_by
        WHERE ca.coach_id = ? AND ca.status = 'active'
        ORDER BY ca.assigned_at DESC
    ");
    $s3->execute([$sc['coach_id']]);
    $assignments = $s3->fetchAll();

    // Bookings not yet assigned to this coach
    $avail = $pdo->prepare("
        SELECT b.id, b.booking_reference, b.swimmer_name, b.program, b.status,
               u.first_name, u.last_name
        FROM bookings b
        JOIN users u ON u.id = b.booked_by_user_id
        WHERE b.status IN ('confirmed','active','pending')
          AND b.program != 'membership'
          AND b.id NOT IN (
              SELECT booking_id FROM coach_assignments
              WHERE coach_id = ? AND status = 'active'
          )
        ORDER BY b.program, b.created_at DESC
        LIMIT 300
    ");
    $avail->execute([$sc['coach_id']]);
    $assignable_bookings = $avail->fetchAll();
}

// ── Promotable members ────────────────────────────────────────────────────────
$promotable = $pdo->query("
    SELECT id, first_name, last_name, phone, email
    FROM users
    WHERE role = 'user' AND is_active = 1
    ORDER BY first_name ASC
    LIMIT 200
")->fetchAll();

// ── Summary stats ─────────────────────────────────────────────────────────────
$total_coaches    = count($coaches);
$active_coaches   = count(array_filter($coaches, fn($c) => $c['is_active']));
$inactive_coaches = $total_coaches - $active_coaches;
$total_asgn_count = 0;
if ($tbl_assignments) {
    $total_asgn_count = (int)$pdo->query("SELECT COUNT(*) FROM coach_assignments WHERE status='active'")->fetchColumn();
}

// ── Helpers ───────────────────────────────────────────────────────────────────
$prog_labels = [
    'junior_development'  => 'Junior Development',
    'competitive_squad'   => 'Competitive Squad',
    'elite_coaching'      => 'Elite Coaching',
    'adult_fitness_swim'  => 'Adult Fitness',
    'mental_conditioning' => 'Mental Conditioning',
    'masters_program'     => 'Masters Program',
];
$prog_colors = [
    'junior_development'  => '#00c8ff',
    'competitive_squad'   => '#ffc847',
    'elite_coaching'      => '#c8a8f8',
    'adult_fitness_swim'  => '#00e5b0',
    'mental_conditioning' => '#b57bee',
    'masters_program'     => '#e8b84b',
];
$all_programs = array_keys($prog_labels);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Coaches — Darken Shadows SC Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700;900&family=Raleway:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
:root {
  --ocean-1:#e8f4fd; --ocean-2:#b8ddf5; --ocean-3:#6db8e8;
  --ocean-4:#2a8cc4; --ocean-5:#1565a0; --ocean-6:#0d3d6b; --ocean-7:#061e35;
  --accent:#00e5b0; --accent-2:#00b38a;
  --danger:#ff4d6d; --warn:#ffc847; --success:#00d68f; --purple:#b57bee;
  --font-display:'Cinzel',serif; --font-body:'Raleway',sans-serif;
  --radius:12px; --radius-sm:7px;
  --card-bg:rgba(13,61,107,.42); --card-border:rgba(0,229,176,.15);
  --transition:.22s cubic-bezier(.4,0,.2,1);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html{scroll-behavior:smooth;}
body{font-family:var(--font-body);background:var(--ocean-7);color:var(--ocean-1);min-height:100vh;}
body::before{content:'';position:fixed;inset:0;z-index:0;
  background:radial-gradient(ellipse 70% 55% at 10% 5%,rgba(0,229,176,.1) 0%,transparent 55%),
  radial-gradient(ellipse 55% 65% at 90% 90%,rgba(42,140,196,.12) 0%,transparent 55%),
  linear-gradient(160deg,var(--ocean-7) 0%,#062e22 55%,var(--ocean-7) 100%);pointer-events:none;}
body::after{content:'';position:fixed;inset:0;z-index:0;
  background-image:radial-gradient(circle at 1px 1px,rgba(0,229,176,.04) 1px,transparent 0);
  background-size:36px 36px;pointer-events:none;}

/* ── Nav ── */
nav{position:sticky;top:0;z-index:100;display:flex;align-items:center;
  justify-content:space-between;padding:1rem 2.5rem;
  background:rgba(6,30,53,.93);backdrop-filter:blur(20px);
  border-bottom:1px solid rgba(0,229,176,.15);}
.nav-logo{font-family:var(--font-display);font-size:.92rem;font-weight:700;
  letter-spacing:.12em;color:var(--accent);text-decoration:none;text-transform:uppercase;}
.nav-links{display:flex;gap:1.4rem;align-items:center;}
.nav-links a{font-size:.74rem;font-weight:600;letter-spacing:.1em;color:var(--ocean-2);
  text-decoration:none;text-transform:uppercase;transition:color var(--transition);}
.nav-links a:hover,.nav-links a.active{color:var(--accent);}
.nav-badge{font-size:.64rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;
  padding:.22rem .75rem;border-radius:100px;
  background:rgba(0,229,176,.1);border:1px solid rgba(0,229,176,.3);color:var(--accent);}
.breadcrumb{font-size:.72rem;color:rgba(184,221,245,.4);display:flex;align-items:center;gap:.4rem;}
.breadcrumb a{color:rgba(184,221,245,.4);text-decoration:none;}
.breadcrumb a:hover{color:var(--ocean-2);}

/* ── Layout ── */
.page{position:relative;z-index:1;max-width:1360px;margin:0 auto;padding:2rem 2rem 6rem;}
.two-col{display:grid;grid-template-columns:300px 1fr;gap:1.5rem;align-items:start;}

/* ── Flash ── */
.flash{position:fixed;top:78px;right:20px;z-index:500;
  border-radius:var(--radius-sm);padding:.85rem 1.3rem;max-width:360px;
  font-size:.83rem;font-weight:600;animation:slideIn .35s ease;
  box-shadow:0 8px 28px rgba(0,0,0,.4);}
.flash.success{background:rgba(0,214,143,.13);border:1px solid rgba(0,214,143,.4);color:var(--success);}
.flash.error{background:rgba(255,77,109,.13);border:1px solid rgba(255,77,109,.4);color:#ff4d6d;}
@keyframes slideIn{from{opacity:0;transform:translateX(28px)}to{opacity:1;transform:translateX(0)}}

/* ── Page header ── */
.page-header{display:flex;align-items:flex-start;justify-content:space-between;
  margin-bottom:1.6rem;opacity:0;animation:fadeUp .5s .08s ease forwards;}
.page-header h1{font-family:var(--font-display);font-size:clamp(1.2rem,2.5vw,1.75rem);
  font-weight:700;color:var(--white);letter-spacing:.04em;}
.page-header p{font-size:.8rem;color:var(--ocean-3);margin-top:.3rem;font-weight:300;}

/* ── Stats strip ── */
.stats-strip{display:grid;grid-template-columns:repeat(5,1fr);gap:.8rem;
  margin-bottom:1.6rem;opacity:0;animation:fadeUp .5s .14s ease forwards;}
.s-card{background:var(--card-bg);border:1px solid var(--card-border);
  border-radius:var(--radius-sm);padding:.9rem 1rem;text-align:center;
  backdrop-filter:blur(8px);transition:transform var(--transition);}
.s-card:hover{transform:translateY(-2px);}
.s-num{font-family:var(--font-display);font-size:1.5rem;font-weight:700;
  color:var(--accent);line-height:1;margin-bottom:.2rem;}
.s-label{font-size:.6rem;font-weight:700;letter-spacing:.15em;
  text-transform:uppercase;color:rgba(184,221,245,.4);}
.s-card.warn .s-num{color:var(--warn);}
.s-card.danger .s-num{color:var(--danger);}
.s-card.blue .s-num{color:#00c8ff;}

/* ── Setup banner ── */
.setup-banner{background:rgba(255,200,71,.07);border:1px solid rgba(255,200,71,.25);
  border-radius:var(--radius);padding:1.8rem 2rem;margin-bottom:1.5rem;}
.setup-banner h3{font-family:var(--font-display);font-size:.9rem;color:var(--warn);margin-bottom:.6rem;}
.setup-banner p{font-size:.82rem;color:rgba(184,221,245,.7);font-weight:300;line-height:1.7;}
.setup-banner code{display:block;background:rgba(0,0,0,.3);border:1px solid rgba(255,200,71,.15);
  border-radius:6px;padding:.8rem 1rem;font-size:.72rem;color:#ffd580;margin-top:.8rem;
  white-space:pre-wrap;overflow-x:auto;font-family:monospace;}

/* ── Coach sidebar ── */
.coach-sidebar{background:var(--card-bg);border:1px solid var(--card-border);
  border-radius:var(--radius);overflow:hidden;backdrop-filter:blur(10px);
  position:sticky;top:80px;
  opacity:0;animation:fadeUp .5s .2s ease forwards;}
.sidebar-head{padding:.85rem 1.2rem;background:rgba(0,229,176,.07);
  border-bottom:1px solid rgba(0,229,176,.12);
  font-family:var(--font-display);font-size:.7rem;font-weight:600;
  letter-spacing:.18em;text-transform:uppercase;color:var(--accent);
  display:flex;align-items:center;justify-content:space-between;}
.sidebar-count{font-size:.65rem;font-weight:600;
  background:rgba(0,229,176,.12);border:1px solid rgba(0,229,176,.25);
  color:var(--accent);padding:.15rem .55rem;border-radius:100px;}
.coach-list{max-height:620px;overflow-y:auto;}
.coach-item{display:flex;align-items:center;gap:.75rem;
  padding:.85rem 1.1rem;border-bottom:1px solid rgba(0,229,176,.07);
  text-decoration:none;color:inherit;cursor:pointer;
  transition:background var(--transition);}
.coach-item:hover,.coach-item.active{background:rgba(0,229,176,.08);}
.coach-item.active{border-left:3px solid var(--accent);padding-left:.85rem;}
.coach-item.inactive{opacity:.55;}
.coach-avatar{width:36px;height:36px;border-radius:50%;flex-shrink:0;
  background:linear-gradient(135deg,var(--accent-2),var(--accent));
  display:flex;align-items:center;justify-content:center;
  font-family:var(--font-display);font-size:.85rem;font-weight:700;color:var(--ocean-7);}
.coach-item.inactive .coach-avatar{background:linear-gradient(135deg,#4a6070,#6a8090);}
.coach-item-name{font-size:.83rem;font-weight:600;color:var(--ocean-1);line-height:1.2;}
.coach-item-spec{font-size:.7rem;font-weight:300;color:rgba(184,221,245,.45);
  margin-top:.1rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:180px;}
.head-badge{font-size:.56rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;
  padding:.12rem .5rem;border-radius:100px;
  background:rgba(255,200,71,.12);border:1px solid rgba(255,200,71,.3);
  color:var(--warn);display:inline-block;margin-top:.2rem;}
.inactive-badge{font-size:.56rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;
  padding:.12rem .5rem;border-radius:100px;
  background:rgba(255,77,109,.1);border:1px solid rgba(255,77,109,.25);
  color:#ff4d6d;display:inline-block;margin-top:.2rem;}

/* ── Detail panel ── */
.detail-panel{opacity:0;animation:fadeUp .5s .26s ease forwards;}
.det-card{background:var(--card-bg);border:1px solid var(--card-border);
  border-radius:var(--radius);overflow:hidden;backdrop-filter:blur(10px);
  margin-bottom:1.2rem;position:relative;}
.det-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;
  background:linear-gradient(90deg,var(--accent-2),var(--accent),transparent);}
.det-head{padding:.9rem 1.5rem;background:rgba(0,229,176,.06);
  border-bottom:1px solid rgba(0,229,176,.12);
  display:flex;align-items:center;justify-content:space-between;gap:.6rem;}
.det-title{font-family:var(--font-display);font-size:.7rem;font-weight:600;
  letter-spacing:.18em;text-transform:uppercase;color:var(--accent);}
.det-body{padding:1.5rem;}

/* Profile strip */
.profile-strip{display:flex;align-items:center;gap:1.4rem;margin-bottom:1.5rem;}
.avatar-lg{width:68px;height:68px;border-radius:50%;flex-shrink:0;
  background:linear-gradient(135deg,var(--accent-2),var(--accent));
  display:flex;align-items:center;justify-content:center;
  font-family:var(--font-display);font-size:1.55rem;font-weight:700;color:var(--ocean-7);
  box-shadow:0 0 0 3px rgba(0,229,176,.2);}
.avatar-lg.inactive-av{background:linear-gradient(135deg,#3a5060,#5a7080);
  box-shadow:0 0 0 3px rgba(255,77,109,.15);}
.profile-name{font-family:var(--font-display);font-size:1.15rem;font-weight:700;
  color:var(--white);margin-bottom:.3rem;letter-spacing:.03em;}
.profile-meta{display:flex;flex-wrap:wrap;gap:.3rem 1rem;font-size:.78rem;color:var(--ocean-2);}
.status-active{color:var(--success);font-weight:600;}
.status-inactive{color:var(--danger);font-weight:600;}

/* Info grid */
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:.75rem;}
.info-item{background:rgba(255,255,255,.04);border:1px solid rgba(0,229,176,.1);
  border-radius:var(--radius-sm);padding:.8rem .95rem;}
.ilabel{font-size:.6rem;font-weight:700;letter-spacing:.18em;text-transform:uppercase;
  color:rgba(184,221,245,.38);margin-bottom:.3rem;}
.ival{font-size:.86rem;font-weight:500;color:var(--ocean-1);line-height:1.5;}
.ival.empty{color:rgba(184,221,245,.28);font-style:italic;font-size:.8rem;}
.prog-tags{display:flex;flex-wrap:wrap;gap:.35rem;margin-top:.15rem;}
.prog-tag{font-size:.62rem;font-weight:600;letter-spacing:.07em;text-transform:uppercase;
  padding:.18rem .55rem;border-radius:4px;}

/* Action buttons */
.action-row{display:flex;gap:.6rem;flex-wrap:wrap;margin-top:1rem;}
.btn-sm{padding:.42rem 1rem;border-radius:var(--radius-sm);font-family:var(--font-body);
  font-size:.7rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;
  cursor:pointer;transition:all var(--transition);border:none;}
.btn-edit{background:rgba(0,229,176,.12);border:1px solid rgba(0,229,176,.3);color:var(--accent);}
.btn-edit:hover{background:rgba(0,229,176,.22);}
.btn-deactivate{background:rgba(255,200,71,.1);border:1px solid rgba(255,200,71,.28);color:var(--warn);}
.btn-deactivate:hover{background:rgba(255,200,71,.2);}
.btn-reactivate{background:rgba(0,214,143,.1);border:1px solid rgba(0,214,143,.28);color:var(--success);}
.btn-reactivate:hover{background:rgba(0,214,143,.2);}
.btn-demote{background:rgba(255,77,109,.09);border:1px solid rgba(255,77,109,.25);color:#ff4d6d;}
.btn-demote:hover{background:rgba(255,77,109,.2);}
.btn-view-coach{background:rgba(42,140,196,.12);border:1px solid rgba(42,140,196,.28);
  color:var(--ocean-3);text-decoration:none;display:inline-flex;align-items:center;gap:.35rem;}
.btn-view-coach:hover{background:rgba(42,140,196,.22);}

/* Edit form */
.edit-form-wrap{display:none;background:rgba(0,0,0,.22);border:1px solid rgba(0,229,176,.15);
  border-radius:var(--radius-sm);padding:1.3rem;margin-top:1rem;}
.edit-form-wrap.open{display:block;}
.form-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:.85rem;}
.form-group{display:flex;flex-direction:column;gap:.35rem;}
.form-group.span-2{grid-column:span 2;}
label.flabel{font-size:.66rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;
  color:rgba(184,221,245,.45);}
input[type=text],input[type=date],select,textarea{
  width:100%;padding:.58rem .85rem;background:rgba(255,255,255,.05);
  border:1px solid rgba(0,229,176,.18);border-radius:var(--radius-sm);
  font-family:var(--font-body);font-size:.84rem;color:var(--ocean-1);
  outline:none;transition:border-color var(--transition);}
input:focus,select:focus,textarea:focus{border-color:var(--accent);}
select option{background:var(--ocean-6);}
textarea{resize:vertical;min-height:80px;}
.prog-checkbox-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:.5rem;margin-top:.2rem;}
.prog-check-label{display:flex;align-items:center;gap:.5rem;
  background:rgba(255,255,255,.04);border:1px solid rgba(0,229,176,.12);
  border-radius:var(--radius-sm);padding:.5rem .65rem;cursor:pointer;
  font-size:.74rem;font-weight:500;color:var(--ocean-2);
  transition:border-color var(--transition),background var(--transition);}
.prog-check-label:has(input:checked){border-color:var(--accent);background:rgba(0,229,176,.09);color:var(--ocean-1);}
.prog-check-label input[type=checkbox]{width:13px;height:13px;accent-color:var(--accent);cursor:pointer;}
.head-check-label{display:flex;align-items:center;gap:.6rem;
  background:rgba(255,200,71,.05);border:1px solid rgba(255,200,71,.15);
  border-radius:var(--radius-sm);padding:.65rem .85rem;cursor:pointer;
  font-size:.84rem;font-weight:500;color:var(--ocean-1);}
.head-check-label:has(input:checked){border-color:var(--warn);background:rgba(255,200,71,.1);}
.head-check-label input[type=checkbox]{width:15px;height:15px;accent-color:var(--warn);cursor:pointer;}
.btn-save{padding:.6rem 1.3rem;background:linear-gradient(135deg,var(--accent-2),var(--accent));
  border:none;border-radius:var(--radius-sm);font-family:var(--font-display);
  font-size:.76rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;
  color:var(--ocean-7);cursor:pointer;transition:all var(--transition);margin-top:.6rem;}
.btn-save:hover{opacity:.9;transform:translateY(-1px);}
.btn-cancel-edit{padding:.6rem 1rem;background:transparent;
  border:1px solid rgba(109,184,232,.2);border-radius:var(--radius-sm);
  font-family:var(--font-body);font-size:.76rem;font-weight:600;letter-spacing:.1em;
  text-transform:uppercase;color:rgba(184,221,245,.5);cursor:pointer;margin-top:.6rem;}
.btn-cancel-edit:hover{border-color:rgba(109,184,232,.4);color:var(--ocean-2);}

/* Attendance */
.att-stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:.7rem;margin-bottom:1rem;}
.att-stat{background:rgba(255,255,255,.04);border:1px solid rgba(0,229,176,.1);
  border-radius:var(--radius-sm);padding:.75rem;text-align:center;}
.att-num{font-family:var(--font-display);font-size:1.35rem;font-weight:700;line-height:1;margin-bottom:.2rem;}
.att-label{font-size:.58rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:rgba(184,221,245,.38);}
.att-stat.p .att-num{color:var(--accent);}
.att-stat.a .att-num{color:var(--danger);}
.att-stat.l .att-num{color:var(--warn);}
.att-stat.lv .att-num{color:var(--purple);}
.att-rate-bar-row{display:flex;align-items:center;gap:.7rem;margin-bottom:1rem;}
.bar-wrap{flex:1;height:5px;background:rgba(255,255,255,.07);border-radius:3px;overflow:hidden;}
.bar-fill{height:100%;background:linear-gradient(90deg,var(--accent-2),var(--accent));border-radius:3px;}
.rate-label{font-size:.74rem;font-weight:600;color:var(--accent);white-space:nowrap;}
.month-nav{display:flex;align-items:center;gap:.6rem;margin-bottom:.8rem;}
.month-nav a{padding:.3rem .75rem;border-radius:4px;font-size:.7rem;font-weight:600;
  color:var(--ocean-3);text-decoration:none;border:1px solid rgba(0,229,176,.18);}
.month-nav a:hover{background:rgba(0,229,176,.1);color:var(--accent);}
.month-nav span{font-family:var(--font-display);font-size:.82rem;color:var(--ocean-2);letter-spacing:.07em;}
.att-table{width:100%;border-collapse:collapse;font-size:.81rem;}
.att-table th{padding:.5rem .8rem;font-size:.58rem;font-weight:700;letter-spacing:.16em;
  text-transform:uppercase;color:rgba(184,221,245,.35);text-align:left;
  border-bottom:1px solid rgba(0,229,176,.1);}
.att-table td{padding:.65rem .8rem;border-bottom:1px solid rgba(0,229,176,.06);vertical-align:middle;}
.att-table tr:last-child td{border-bottom:none;}
.att-table tr:hover td{background:rgba(0,229,176,.03);}
.att-pill{display:inline-block;padding:.17rem .6rem;border-radius:100px;
  font-size:.6rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;}
.att-pill.present{background:rgba(0,229,176,.1);border:1px solid rgba(0,229,176,.25);color:var(--accent);}
.att-pill.absent{background:rgba(255,77,109,.1);border:1px solid rgba(255,77,109,.25);color:#ff4d6d;}
.att-pill.late{background:rgba(255,200,71,.1);border:1px solid rgba(255,200,71,.25);color:#ffc847;}
.att-pill.leave{background:rgba(181,123,238,.1);border:1px solid rgba(181,123,238,.25);color:#b57bee;}
.btn-del-att{padding:.18rem .55rem;background:rgba(255,77,109,.09);
  border:1px solid rgba(255,77,109,.22);border-radius:4px;
  font-size:.62rem;font-weight:700;color:#ff4d6d;cursor:pointer;}
.btn-del-att:hover{background:rgba(255,77,109,.2);}
.empty-log{text-align:center;padding:2rem;color:rgba(184,221,245,.35);font-size:.85rem;}
.override-form-wrap{background:rgba(0,0,0,.2);border:1px solid rgba(0,229,176,.15);
  border-radius:var(--radius-sm);padding:1.1rem;margin-top:1rem;}
.override-form-wrap summary{cursor:pointer;font-size:.72rem;font-weight:600;
  color:var(--accent);letter-spacing:.1em;text-transform:uppercase;user-select:none;
  padding:.3rem 0;list-style:none;display:flex;align-items:center;gap:.5rem;}
.override-form-wrap summary::before{content:'＋';font-size:.8rem;}
.override-form-wrap[open] summary::before{content:'－';}
.override-row{display:grid;grid-template-columns:1fr 1fr 2fr auto;gap:.7rem;
  align-items:end;margin-top:.9rem;}

/* ── Assignments ── */
.asgn-table{width:100%;border-collapse:collapse;font-size:.81rem;}
.asgn-table th{padding:.5rem .9rem;font-size:.58rem;font-weight:700;letter-spacing:.16em;
  text-transform:uppercase;color:rgba(184,221,245,.35);text-align:left;
  border-bottom:1px solid rgba(0,229,176,.1);}
.asgn-table td{padding:.7rem .9rem;border-bottom:1px solid rgba(0,229,176,.06);vertical-align:middle;}
.asgn-table tr:last-child td{border-bottom:none;}
.asgn-table tr:hover td{background:rgba(0,229,176,.03);}
.prog-chip{display:inline-block;padding:.17rem .6rem;border-radius:4px;
  font-size:.62rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;
  background:rgba(42,140,196,.15);border:1px solid rgba(42,140,196,.25);color:var(--ocean-3);}
.ref-txt{font-family:var(--font-display);font-size:.67rem;letter-spacing:.09em;
  color:rgba(184,221,245,.4);}
.muted{color:rgba(184,221,245,.4);font-size:.74rem;}
.no-asgn{text-align:center;padding:1.8rem;color:rgba(184,221,245,.35);font-size:.85rem;}
.btn-remove{padding:.26rem .7rem;background:rgba(255,77,109,.09);
  border:1px solid rgba(255,77,109,.22);border-radius:4px;
  font-family:var(--font-body);font-size:.65rem;font-weight:700;
  letter-spacing:.08em;text-transform:uppercase;color:#ff4d6d;cursor:pointer;}
.btn-remove:hover{background:rgba(255,77,109,.2);}

/* Assign form */
.assign-form-wrap{background:rgba(0,0,0,.2);border:1px solid rgba(0,229,176,.15);
  border-radius:var(--radius-sm);padding:1.2rem;margin-top:1rem;}
.assign-form-wrap summary{cursor:pointer;font-size:.72rem;font-weight:600;
  color:var(--accent);letter-spacing:.1em;text-transform:uppercase;user-select:none;
  padding:.3rem 0;list-style:none;display:flex;align-items:center;gap:.5rem;}
.assign-form-wrap summary::before{content:'＋';}
.assign-form-wrap[open] summary::before{content:'－';}
.assign-form-body{margin-top:.9rem;display:flex;flex-direction:column;gap:.85rem;}

/* Promote section */
.promote-card{background:var(--card-bg);border:1px solid var(--card-border);
  border-radius:var(--radius);overflow:hidden;backdrop-filter:blur(10px);
  margin-top:2rem;opacity:0;animation:fadeUp .5s .5s ease forwards;}

/* Empty states */
.empty-panel{background:var(--card-bg);border:1px dashed rgba(0,229,176,.18);
  border-radius:var(--radius);padding:3rem;text-align:center;}
.empty-panel p{color:rgba(184,221,245,.45);font-size:.9rem;font-weight:300;margin-bottom:1.2rem;}
.btn-goto{display:inline-block;padding:.6rem 1.3rem;border-radius:var(--radius-sm);
  background:rgba(0,229,176,.1);border:1px solid rgba(0,229,176,.25);
  color:var(--accent);font-size:.78rem;font-weight:600;text-decoration:none;
  letter-spacing:.08em;text-transform:uppercase;}
.btn-goto:hover{background:rgba(0,229,176,.2);}
.select-hint{background:var(--card-bg);border:1px dashed rgba(0,229,176,.15);
  border-radius:var(--radius);padding:3rem;text-align:center;color:rgba(184,221,245,.4);font-size:.9rem;}
.select-hint .sh-icon{font-size:2.5rem;display:block;margin-bottom:.8rem;}

@keyframes fadeUp{from{opacity:0;transform:translateY(15px)}to{opacity:1;transform:translateY(0)}}

@media(max-width:1100px){.two-col{grid-template-columns:1fr;}.coach-sidebar{position:static;}}
@media(max-width:768px){
  nav{padding:1rem 1rem;}.page{padding:1.2rem .8rem 4rem;}
  .stats-strip{grid-template-columns:repeat(3,1fr);}
  .info-grid{grid-template-columns:1fr;}
  .att-stats-row{grid-template-columns:repeat(2,1fr);}
  .form-grid-2{grid-template-columns:1fr;}
  .form-group.span-2{grid-column:span 1;}
  .prog-checkbox-grid{grid-template-columns:repeat(2,1fr);}
  .override-row{grid-template-columns:1fr 1fr;}
}
</style>
</head>
<body>

<?php if ($flash): ?>
<div class="flash <?= $flash_type === 'error' ? 'error' : 'success' ?>" id="flash-el">
  <?= $flash_type === 'error' ? '⚠' : '✓' ?> <?= $flash ?>
</div>
<script>setTimeout(()=>document.getElementById('flash-el')?.remove(),4500);</script>
<?php endif; ?>

<nav>
  <a href="admin.php" class="nav-logo">⚙ Admin</a>
  <div class="nav-links">
    <a href="admin.php">Dashboard</a>
    <a href="database.php">Users</a>
    <a href="pending.php">Enrollments</a>
    <a href="m_pending.php">Memberships</a>
    <a href="m_coach.php" class="active">Coaches</a>
    <a href="logout.php">Logout</a>
  </div>
  <div class="breadcrumb">
    <a href="admin.php">Admin</a> / <span style="color:var(--ocean-2);">Manage Coaches</span>
  </div>
</nav>

<div class="page">

  <div class="page-header">
    <div>
      <h1>🏊 Manage Coaching Staff</h1>
      <p>Edit profiles, manage attendance, assign students, and control coach access</p>
    </div>
    <a href="coach.php" class="btn-sm btn-view-coach" style="padding:.55rem 1.1rem;font-size:.72rem;">
      👁 Coach View
    </a>
  </div>

  <!-- Setup banners -->
  <?php if (!$tbl_coaches): ?>
  <div class="setup-banner">
    <h3>⚠ Coaches table missing</h3>
    <p>Run the SQL below (or from <code>pool.sql</code>), then promote members to coaches from the <a href="database.php" style="color:var(--warn);">User Database</a>.</p>
    <code>CREATE TABLE coaches (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    specialisation VARCHAR(120) DEFAULT NULL,
    qualification VARCHAR(200) DEFAULT NULL,
    bio TEXT DEFAULT NULL,
    phone_direct VARCHAR(20) DEFAULT NULL,
    hire_date DATE DEFAULT NULL,
    assigned_programs SET('junior_development','competitive_squad','elite_coaching',
                          'adult_fitness_swim','mental_conditioning','masters_program') DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_coach_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;</code>
  </div>
  <?php endif; ?>

  <?php if ($tbl_coaches && !$tbl_assignments): ?>
  <div class="setup-banner">
    <h3>⚠ coach_assignments table missing — student assignments disabled</h3>
    <p>Add this table to enable the full assignment system:</p>
    <code>CREATE TABLE coach_assignments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    coach_id INT UNSIGNED NOT NULL,
    booking_id INT UNSIGNED NOT NULL,
    student_user_id INT UNSIGNED NOT NULL,
    program VARCHAR(60) NOT NULL,
    assigned_by INT UNSIGNED NOT NULL,
    status ENUM('active','removed') NOT NULL DEFAULT 'active',
    notes TEXT DEFAULT NULL,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ca_booking (coach_id, booking_id),
    INDEX idx_ca_coach (coach_id),
    INDEX idx_ca_student (student_user_id),
    CONSTRAINT fk_ca_coach   FOREIGN KEY (coach_id)   REFERENCES coaches(id)  ON DELETE CASCADE,
    CONSTRAINT fk_ca_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    CONSTRAINT fk_ca_student FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_ca_by     FOREIGN KEY (assigned_by) REFERENCES users(id)    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;</code>
  </div>
  <?php endif; ?>

  <!-- Stats -->
  <div class="stats-strip">
    <div class="s-card"><div class="s-num"><?= $total_coaches ?></div><div class="s-label">Total Coaches</div></div>
    <div class="s-card"><div class="s-num"><?= $active_coaches ?></div><div class="s-label">Active</div></div>
    <div class="s-card danger"><div class="s-num"><?= $inactive_coaches ?></div><div class="s-label">Inactive</div></div>
    <div class="s-card blue"><div class="s-num"><?= $total_asgn_count ?></div><div class="s-label">Assigned Students</div></div>
  </div>

  <?php if (empty($coaches)): ?>
  <div class="empty-panel">
    <p>No coaching staff yet. Promote members to coaches via the User Database.</p>
    <a href="database.php?tab=users" class="btn-goto">Go to User Database →</a>
  </div>

  <?php else: ?>
  <div class="two-col">

    <!-- ── Sidebar ── -->
    <div class="coach-sidebar">
      <div class="sidebar-head">Staff <span class="sidebar-count"><?= $total_coaches ?></span></div>
      <div class="coach-list">
        <?php foreach ($coaches as $c):
          $initials = strtoupper(substr($c['first_name'],0,1).substr($c['last_name'],0,1));
          $is_sel   = ((int)$c['coach_id'] === $sel_coach_id);
          $inactive = !$c['is_active'];
        ?>
        <a class="coach-item <?= $is_sel ? 'active' : '' ?> <?= $inactive ? 'inactive' : '' ?>"
           href="?coach=<?= $c['coach_id'] ?>&att_month=<?= urlencode($att_month) ?>">
          <div class="coach-avatar"><?= $initials ?></div>
          <div>
            <div class="coach-item-name"><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?></div>
            <div class="coach-item-spec"><?= htmlspecialchars($c['specialisation'] ?? 'Swimming Coach') ?></div>
            <?php if ($inactive): ?><span class="inactive-badge">Inactive</span><?php endif; ?>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- ── Detail panel ── -->
    <div class="detail-panel">

      <?php if (!$sc): ?>
      <div class="select-hint">
        <span class="sh-icon">👈</span>
        Select a coach from the list to view and manage their profile.
      </div>

      <?php else:
        $initials_lg    = strtoupper(substr($sc['first_name'],0,1).substr($sc['last_name'],0,1));
        $is_inactive    = !$sc['is_active'];
        $assigned_progs = $sc['assigned_programs'] ? array_map('trim', explode(',', $sc['assigned_programs'])) : [];
      ?>

      <!-- ══ Profile Card ══ -->
      <div class="det-card">
        <div class="det-head">
          <div style="display:flex;align-items:center;gap:.6rem;">
            <span class="det-title">👤 Coach Profile</span>
          </div>
          <span style="font-size:.72rem;color:rgba(184,221,245,.35);font-family:var(--font-body);">
            ID #<?= $sc['coach_id'] ?>
          </span>
        </div>
        <div class="det-body">
          <div class="profile-strip">
            <div class="avatar-lg <?= $is_inactive ? 'inactive-av' : '' ?>"><?= $initials_lg ?></div>
            <div>
              <div class="profile-name"><?= htmlspecialchars($sc['first_name'] . ' ' . $sc['last_name']) ?></div>
              <div class="profile-meta">
                <?= $sc['is_active'] ? '<span class="status-active">● Active</span>' : '<span class="status-inactive">● Inactive</span>' ?>
                <span>📞 <?= htmlspecialchars($sc['phone'] ?? '—') ?></span>
                <?php if ($sc['phone_direct']): ?><span>📱 <?= htmlspecialchars($sc['phone_direct']) ?></span><?php endif; ?>
                <span>✉ <?= htmlspecialchars($sc['email'] ?? '—') ?></span>
              </div>
            </div>
          </div>

          <div class="info-grid">
            <div class="info-item">
              <div class="ilabel">Specialisation</div>
              <div class="ival <?= $sc['specialisation'] ? '' : 'empty' ?>"><?= htmlspecialchars($sc['specialisation'] ?? 'Not specified') ?></div>
            </div>
            <div class="info-item">
              <div class="ilabel">Qualification</div>
              <div class="ival <?= $sc['qualification'] ? '' : 'empty' ?>"><?= htmlspecialchars($sc['qualification'] ?? 'Not specified') ?></div>
            </div>
            <div class="info-item">
              <div class="ilabel">Hire Date</div>
              <div class="ival <?= $sc['hire_date'] ? '' : 'empty' ?>"><?= $sc['hire_date'] ? date('d F Y', strtotime($sc['hire_date'])) : 'Not recorded' ?></div>
            </div>
            <div class="info-item">
              <div class="ilabel">Member Since</div>
              <div class="ival"><?= date('F Y', strtotime($sc['joined_at'])) ?></div>
            </div>
            <div class="info-item" style="grid-column:span 2;">
              <div class="ilabel">Assigned Programs</div>
              <div class="ival">
                <?php if (!empty($assigned_progs)): ?>
                <div class="prog-tags">
                  <?php foreach ($assigned_progs as $ap):
                    $col = $prog_colors[$ap] ?? '#6db8e8';
                    $lbl = $prog_labels[$ap]  ?? ucwords(str_replace('_',' ',$ap));
                  ?>
                  <span class="prog-tag" style="background:<?= $col ?>18;border:1px solid <?= $col ?>44;color:<?= $col ?>;"><?= htmlspecialchars($lbl) ?></span>
                  <?php endforeach; ?>
                </div>
                <?php else: ?><span class="empty">No programs assigned</span><?php endif; ?>
              </div>
            </div>
            <?php if ($sc['bio']): ?>
            <div class="info-item" style="grid-column:span 2;">
              <div class="ilabel">Bio</div>
              <div class="ival" style="line-height:1.6;"><?= htmlspecialchars($sc['bio']) ?></div>
            </div>
            <?php endif; ?>
          </div>

          <!-- Action row -->
          <div class="action-row">
            <button class="btn-sm btn-edit" onclick="toggleEdit()">✏️ Edit Profile</button>
            <a href="coach.php?coach=<?= $sc['coach_id'] ?>" class="btn-sm btn-view-coach">👁 Coach View</a>

            <?php if ($sc['is_active']): ?>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Deactivate this coach?')">
              <input type="hidden" name="action"    value="toggle_active">
              <input type="hidden" name="user_id"   value="<?= $sc['user_id'] ?>">
              <input type="hidden" name="new_active" value="0">
              <input type="hidden" name="coach_id"  value="<?= $sc['coach_id'] ?>">
              <input type="hidden" name="att_month"  value="<?= htmlspecialchars($att_month) ?>">
              <button type="submit" class="btn-sm btn-deactivate">⏸ Deactivate</button>
            </form>
            <?php else: ?>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="action"    value="toggle_active">
              <input type="hidden" name="user_id"   value="<?= $sc['user_id'] ?>">
              <input type="hidden" name="new_active" value="1">
              <input type="hidden" name="coach_id"  value="<?= $sc['coach_id'] ?>">
              <input type="hidden" name="att_month"  value="<?= htmlspecialchars($att_month) ?>">
              <button type="submit" class="btn-sm btn-reactivate">▶ Reactivate</button>
            </form>
            <?php endif; ?>

            <form method="POST" style="display:inline;"
                  onsubmit="return confirm('Demote to member? Coaching history will be kept.')">
              <input type="hidden" name="action"   value="demote_coach">
              <input type="hidden" name="user_id"  value="<?= $sc['user_id'] ?>">
              <input type="hidden" name="coach_id" value="<?= $sc['coach_id'] ?>">
              <input type="hidden" name="att_month" value="<?= htmlspecialchars($att_month) ?>">
              <button type="submit" class="btn-sm btn-demote">↩ Demote to Member</button>
            </form>
          </div>

          <!-- Edit form -->
          <div class="edit-form-wrap" id="edit-form">
            <form method="POST">
              <input type="hidden" name="action"   value="edit_coach">
              <input type="hidden" name="coach_id" value="<?= $sc['coach_id'] ?>">
              <input type="hidden" name="att_month" value="<?= htmlspecialchars($att_month) ?>">
              <div class="form-grid-2">
                <div class="form-group">
                  <label class="flabel">Specialisation</label>
                  <input type="text" name="specialisation" placeholder="e.g. Sprint Freestyle"
                         value="<?= htmlspecialchars($sc['specialisation'] ?? '') ?>">
                </div>
                <div class="form-group">
                  <label class="flabel">Qualification</label>
                  <input type="text" name="qualification" placeholder="e.g. ASA Level 2"
                         value="<?= htmlspecialchars($sc['qualification'] ?? '') ?>">
                </div>
                <div class="form-group">
                  <label class="flabel">Direct Phone</label>
                  <input type="text" name="phone_direct" placeholder="Direct / coaching line"
                         value="<?= htmlspecialchars($sc['phone_direct'] ?? '') ?>">
                </div>
                <div class="form-group">
                  <label class="flabel">Hire Date</label>
                  <input type="date" name="hire_date" value="<?= htmlspecialchars($sc['hire_date'] ?? '') ?>">
                </div>
                <div class="form-group span-2">
                  <label class="flabel">Bio</label>
                  <textarea name="bio" placeholder="Background, coaching philosophy, achievements…"><?= htmlspecialchars($sc['bio'] ?? '') ?></textarea>
                </div>
                <div class="form-group span-2">
                  <label class="flabel">Assigned Programs</label>
                  <div class="prog-checkbox-grid">
                    <?php foreach ($all_programs as $prog):
                      $col     = $prog_colors[$prog] ?? '#6db8e8';
                      $lbl     = $prog_labels[$prog];
                      $checked = in_array($prog, $assigned_progs) ? 'checked' : '';
                    ?>
                    <label class="prog-check-label" style="<?= $checked ? "border-color:{$col}55;background:{$col}11;" : '' ?>">
                      <input type="checkbox" name="assigned_programs[]" value="<?= $prog ?>" <?= $checked ?>>
                      <?= htmlspecialchars($lbl) ?>
                    </label>
                    <?php endforeach; ?>
                  </div>
                </div>
                <div class="form-group span-2">
                </div>
              </div>
              <div style="display:flex;gap:.6rem;margin-top:.8rem;">
                <button type="submit" class="btn-save">Save Changes →</button>
                <button type="button" class="btn-cancel-edit" onclick="toggleEdit()">Cancel</button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- ══ Attendance Card ══ -->
      <div class="det-card">
        <div class="det-head">
          <span class="det-title">📅 Attendance Log</span>
          <span style="font-size:.72rem;color:rgba(184,221,245,.35);font-family:var(--font-body);">
            Last 30 days — <?= $att_rate_30 ?>% attendance rate
          </span>
        </div>
        <div class="det-body">
          <?php if (!$tbl_attendance): ?>
          <p style="color:rgba(184,221,245,.4);font-size:.85rem;">The <code>coach_attendance</code> table is not set up yet.</p>
          <?php else: ?>

          <div class="att-stats-row">
            <div class="att-stat p"><div class="att-num"><?= $att_stats_30['present'] ?></div><div class="att-label">Present</div></div>
            <div class="att-stat a"><div class="att-num"><?= $att_stats_30['absent'] ?></div><div class="att-label">Absent</div></div>
            <div class="att-stat l"><div class="att-num"><?= $att_stats_30['late'] ?></div><div class="att-label">Late</div></div>
            <div class="att-stat lv"><div class="att-num"><?= $att_stats_30['leave'] ?></div><div class="att-label">On Leave</div></div>
          </div>

          <div class="att-rate-bar-row">
            <div class="bar-wrap"><div class="bar-fill" style="width:<?= $att_rate_30 ?>%;"></div></div>
            <span class="rate-label"><?= $att_rate_30 ?>% (30 days)</span>
          </div>

          <div class="month-nav">
            <a href="?coach=<?= $sc['coach_id'] ?>&att_month=<?= $prev_month ?>">← Prev</a>
            <span><?= date('F Y', mktime(0,0,0,(int)$att_m,1,(int)$att_y)) ?></span>
            <a href="?coach=<?= $sc['coach_id'] ?>&att_month=<?= $next_month ?>">Next →</a>
          </div>

          <?php if ($att_total_month > 0): ?>
          <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:.9rem;">
            <?php foreach (['present'=>'Present','absent'=>'Absent','late'=>'Late','leave'=>'Leave'] as $k=>$l): ?>
              <?php if ($att_stats_month[$k] > 0): ?>
              <span class="att-pill <?= $k ?>"><?= $att_stats_month[$k] ?> <?= $l ?></span>
              <?php endif; ?>
            <?php endforeach; ?>
            <span style="font-size:.72rem;color:rgba(184,221,245,.35);align-self:center;">
              — <?= $att_total_month ?> session<?= $att_total_month !== 1 ? 's' : '' ?> this month
            </span>
          </div>
          <?php endif; ?>

          <?php if (empty($attendance_log)): ?>
          <div class="empty-log">No attendance records for <?= date('F Y', mktime(0,0,0,(int)$att_m,1,(int)$att_y)) ?>.</div>
          <?php else: ?>
          <table class="att-table">
            <thead><tr><th>Date</th><th>Day</th><th>Status</th><th>Notes</th><th>Recorded By</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($attendance_log as $att): ?>
              <tr>
                <td style="font-family:'Cinzel',serif;font-size:.73rem;letter-spacing:.06em;"><?= date('d M Y', strtotime($att['session_date'])) ?></td>
                <td class="muted"><?= date('D', strtotime($att['session_date'])) ?></td>
                <td><span class="att-pill <?= $att['status'] ?>"><?= ucfirst($att['status']) ?></span></td>
                <td style="color:rgba(184,221,245,.55);max-width:160px;word-break:break-word;"><?= $att['notes'] ? htmlspecialchars($att['notes']) : '<span class="muted">—</span>' ?></td>
                <td class="muted" style="font-size:.72rem;"><?= $att['rec_fn'] ? htmlspecialchars($att['rec_fn'].' '.$att['rec_ln']) : 'Self / System' ?></td>
                <td>
                  <form method="POST" onsubmit="return confirm('Delete this entry?')">
                    <input type="hidden" name="action"    value="delete_attendance">
                    <input type="hidden" name="att_id"   value="<?= $att['id'] ?>">
                    <input type="hidden" name="coach_id" value="<?= $sc['coach_id'] ?>">
                    <input type="hidden" name="att_month" value="<?= htmlspecialchars($att_month) ?>">
                    <button type="submit" class="btn-del-att" title="Delete">✕</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php endif; ?>

          <details class="override-form-wrap" style="margin-top:1rem;">
            <summary>Add / Override Attendance Entry</summary>
            <form method="POST">
              <input type="hidden" name="action"   value="override_attendance">
              <input type="hidden" name="coach_id" value="<?= $sc['coach_id'] ?>">
              <input type="hidden" name="att_month" value="<?= htmlspecialchars($att_month) ?>">
              <div class="override-row">
                <div class="form-group">
                  <label class="flabel">Date *</label>
                  <input type="date" name="session_date" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                  <label class="flabel">Status *</label>
                  <select name="att_status">
                    <option value="present">Present</option>
                    <option value="absent">Absent</option>
                    <option value="late">Late</option>
                    <option value="leave">On Leave</option>
                  </select>
                </div>
                <div class="form-group">
                  <label class="flabel">Notes</label>
                  <input type="text" name="att_notes" placeholder="Optional correction note">
                </div>
                <div class="form-group">
                  <label class="flabel" style="opacity:0;">Go</label>
                  <button type="submit" class="btn-save" style="margin-top:0;">Save →</button>
                </div>
              </div>
              <p style="font-size:.72rem;color:rgba(184,221,245,.3);margin-top:.5rem;">Saving for an existing date will overwrite the previous record.</p>
            </form>
          </details>

          <?php endif; ?>
        </div>
      </div>

      <!-- ══ Assigned Students Card ══ -->
      <div class="det-card">
        <div class="det-head">
          <span class="det-title">👥 Assigned Students</span>
          <span style="font-size:.72rem;color:rgba(184,221,245,.35);font-family:var(--font-body);">
            <?= count($assignments) ?> active assignment<?= count($assignments) !== 1 ? 's' : '' ?>
          </span>
        </div>
        <div class="det-body">
          <?php if (!$tbl_assignments): ?>
          <p style="color:rgba(184,221,245,.4);font-size:.85rem;">
            Create the <code>coach_assignments</code> table (see banner above) to enable student assignments.
          </p>

          <?php elseif (empty($assignments)): ?>
          <div class="no-asgn">No students currently assigned to this coach.</div>

          <?php else: ?>
          <table class="asgn-table">
            <thead><tr>
              <th>Swimmer</th><th>Account</th><th>Program</th>
              <th>Booking Ref</th><th>Enrollment Status</th><th>Assigned</th><th></th>
            </tr></thead>
            <tbody>
              <?php foreach ($assignments as $a):
                $p_col = $prog_colors[$a['program']] ?? '#6db8e8';
                $p_lbl = $prog_labels[$a['program']] ?? ucwords(str_replace('_',' ',$a['program']));
                $status_colors = [
                  'pending'=>'#ffc847','confirmed'=>'#00d68f',
                  'active'=>'#00e5b0','completed'=>'#6db8e8','cancelled'=>'#ff4d6d',
                ];
                $bs_col = $status_colors[$a['booking_status']] ?? '#6db8e8';
              ?>
              <tr>
                <td>
                  <strong style="color:var(--ocean-1);"><?= htmlspecialchars($a['swimmer_name'] ?? '—') ?></strong><br>
                  <span class="muted"><?= htmlspecialchars($a['st_phone'] ?? '—') ?></span>
                </td>
                <td style="color:var(--ocean-2);"><?= htmlspecialchars($a['st_fn'] . ' ' . $a['st_ln']) ?></td>
                <td>
                  <span class="prog-chip" style="background:<?= $p_col ?>18;border-color:<?= $p_col ?>44;color:<?= $p_col ?>;">
                    <?= htmlspecialchars($p_lbl) ?>
                  </span>
                </td>
                <td class="ref-txt"><?= htmlspecialchars($a['booking_reference']) ?></td>
                <td>
                  <span style="display:inline-block;padding:.17rem .6rem;border-radius:100px;font-size:.6rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;background:<?= $bs_col ?>20;border:1px solid <?= $bs_col ?>55;color:<?= $bs_col ?>;">
                    <?= ucfirst($a['booking_status']) ?>
                  </span>
                </td>
                <td class="muted">
                  <?= date('d M Y', strtotime($a['assigned_at'])) ?><br>
                  <span style="font-size:.68rem;">by <?= htmlspecialchars($a['by_fn'] . ' ' . $a['by_ln']) ?></span>
                </td>
                <td>
                  <form method="POST" onsubmit="return confirm('Remove this assignment?')">
                    <input type="hidden" name="action"        value="remove_assignment">
                    <input type="hidden" name="assignment_id" value="<?= $a['id'] ?>">
                    <input type="hidden" name="coach_id"      value="<?= $sc['coach_id'] ?>">
                    <input type="hidden" name="att_month"     value="<?= htmlspecialchars($att_month) ?>">
                    <button type="submit" class="btn-remove">✕ Remove</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php endif; ?>

          <?php if ($tbl_assignments): ?>
          <details class="assign-form-wrap">
            <summary>Assign a Student to This Coach</summary>
            <div class="assign-form-body">
              <?php if (empty($assignable_bookings)): ?>
              <p style="font-size:.82rem;color:rgba(184,221,245,.4);">All active enrollments are already assigned to this coach.</p>
              <?php else: ?>
              <form method="POST">
                <input type="hidden" name="action"          value="assign_student">
                <input type="hidden" name="assign_coach_id" value="<?= $sc['coach_id'] ?>">
                <input type="hidden" name="coach_id"        value="<?= $sc['coach_id'] ?>">
                <input type="hidden" name="att_month"       value="<?= htmlspecialchars($att_month) ?>">
                <div class="form-group" style="margin-bottom:.85rem;">
                  <label class="flabel">Select Enrollment *</label>
                  <select name="booking_id" required>
                    <option value="">— Choose an enrollment —</option>
                    <?php
                      $prev_prog_key = '';
                      foreach ($assignable_bookings as $ab):
                        $prog_display = $prog_labels[$ab['program']] ?? ucwords(str_replace('_',' ',$ab['program']));
                        if ($prev_prog_key !== $ab['program']) {
                          if ($prev_prog_key !== '') echo '</optgroup>';
                          echo '<optgroup label="' . htmlspecialchars($prog_display) . '">';
                          $prev_prog_key = $ab['program'];
                        }
                    ?>
                    <option value="<?= $ab['id'] ?>">
                      <?= htmlspecialchars($ab['swimmer_name']) ?>
                      · <?= htmlspecialchars($ab['first_name'] . ' ' . $ab['last_name']) ?>
                      · <?= htmlspecialchars($ab['booking_reference']) ?>
                      · <?= ucfirst($ab['status']) ?>
                    </option>
                    <?php endforeach; if ($prev_prog_key !== '') echo '</optgroup>'; ?>
                  </select>
                </div>
                <div class="form-group" style="margin-bottom:.85rem;">
                  <label class="flabel">Assignment Notes (optional)</label>
                  <input type="text" name="assign_notes" placeholder="e.g. Focus on butterfly, Mon/Wed/Fri sessions">
                </div>
                <button type="submit" class="btn-save">Assign Student →</button>
              </form>
              <?php endif; ?>
            </div>
          </details>
          <?php endif; ?>
        </div>
      </div>

      <?php endif; // $sc ?>
    </div><!-- /detail-panel -->
  </div><!-- /two-col -->
  <?php endif; // coaches not empty ?>

  <!-- ── Promote Member to Coach ── -->
  <?php if ($tbl_coaches && !empty($promotable)): ?>
  <div class="promote-card">
    <div class="det-head">
      <span class="det-title">⬆ Promote Member to Coach</span>
    </div>
    <div class="det-body">
      <form method="POST">
        <input type="hidden" name="action" value="promote_to_coach">
        <input type="hidden" name="coach_id" value="0">
        <input type="hidden" name="att_month" value="<?= htmlspecialchars($att_month) ?>">
        <div class="form-grid-2">
          <div class="form-group">
            <label class="flabel">Select Member *</label>
            <select name="user_id" required>
              <option value="">— Choose a member —</option>
              <?php foreach ($promotable as $u): ?>
              <option value="<?= $u['id'] ?>">
                <?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?>
                <?= $u['phone'] ? ' · ' . $u['phone'] : '' ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="flabel">Hire Date</label>
            <input type="date" name="hire_date" value="<?= date('Y-m-d') ?>">
          </div>
          <div class="form-group">
            <label class="flabel">Specialisation</label>
            <input type="text" name="specialisation" placeholder="e.g. Sprint Freestyle, Breaststroke">
          </div>
          <div class="form-group">
            <label class="flabel">Qualification</label>
            <input type="text" name="qualification" placeholder="e.g. ASA Level 2, NCAS">
          </div>
          <div class="form-group span-2">
          </div>
        </div>
        <button type="submit" class="btn-save" style="margin-top:.8rem;">Promote to Coach →</button>
      </form>
    </div>
  </div>
  <?php endif; ?>

</div><!-- /page -->

<script>
function toggleEdit() {
  const form = document.getElementById('edit-form');
  form.classList.toggle('open');
  if (form.classList.contains('open')) {
    window.scrollTo({ top: form.offsetTop - 100, behavior: 'smooth' });
  }
}
</script>
</body>
</html>