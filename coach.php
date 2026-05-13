<?php
// coach.php — Coaching Staff Management
// Accessible by: admin (full access) | coach (own profile + roster view only)
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=coach.php');
    exit;
}

require_once 'config/db.php';

// Role guard — admin or coach only
$stmt = $pdo->prepare("SELECT id, first_name, last_name, role FROM users WHERE id = ? AND is_active = 1");
$stmt->execute([$_SESSION['user_id']]);
$viewer = $stmt->fetch();

if (!$viewer || !in_array($viewer['role'], ['admin', 'coach'])) {
    http_response_code(403);
    die('<!DOCTYPE html><html><head><title>Access Denied</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=Raleway:wght@400&display=swap" rel="stylesheet">
    <style>*{margin:0;padding:0;box-sizing:border-box;}body{background:#061e35;color:#b8ddf5;font-family:Raleway,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;flex-direction:column;gap:1.5rem;}h1{font-family:Cinzel,serif;color:#ff4d6d;font-size:2rem;}p{color:#6db8e8;}a{color:#6db8e8;}</style>
    </head><body><h1>403 — Access Denied</h1><p>Coaches and Admins only.</p><a href="index.php">← Back</a></body></html>');
}

$is_admin = ($viewer['role'] === 'admin');
$viewer_id = (int)$viewer['id'];

/*
==========================================================================
  SQL TO ADD BEFORE USING THIS PAGE — run in pool.sql or via migration:

  CREATE TABLE IF NOT EXISTS coaches (
      id                INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
      user_id           INT UNSIGNED   NOT NULL UNIQUE,
      specialisation    VARCHAR(120)   DEFAULT NULL,
      qualification     VARCHAR(200)   DEFAULT NULL,
      bio               TEXT           DEFAULT NULL,
      phone_direct      VARCHAR(20)    DEFAULT NULL,
      hire_date         DATE           DEFAULT NULL,
      assigned_programs SET('junior_development','competitive_squad','elite_coaching',
                            'adult_fitness_swim','mental_conditioning','masters_program')
                        DEFAULT NULL,
      created_at        TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at        TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      CONSTRAINT fk_coach_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

  CREATE TABLE IF NOT EXISTS coach_attendance (
      id           INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
      coach_id     INT UNSIGNED   NOT NULL,
      session_date DATE           NOT NULL,
      status       ENUM('present','absent','late','leave') NOT NULL DEFAULT 'present',
      notes        TEXT           DEFAULT NULL,
      recorded_by  INT UNSIGNED   DEFAULT NULL,
      created_at   TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_ca_coach (coach_id),
      INDEX idx_ca_date  (session_date),
      CONSTRAINT fk_ca_coach    FOREIGN KEY (coach_id)    REFERENCES coaches(id) ON DELETE CASCADE,
      CONSTRAINT fk_ca_recorder FOREIGN KEY (recorded_by) REFERENCES users(id)   ON DELETE SET NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
==========================================================================
*/

// ── Handle POST: mark attendance / promote coach ──────────────────────────────
$flash = '';
$flash_type = 'success'; // 'success' | 'error'
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Coach self-check-in (coaches only, for their own record) ──────────────
    if ($action === 'coach_checkin' && !$is_admin) {
        // Find this coach's coaches.id
        $own_coach = null;
        try {
            $oc = $pdo->prepare("SELECT id FROM coaches WHERE user_id = ?");
            $oc->execute([$viewer_id]);
            $own_coach = $oc->fetch();
        } catch (PDOException $e) {}

        if ($own_coach) {
            $checkin_date   = date('Y-m-d'); // always today
            $checkin_status = in_array($_POST['checkin_status'] ?? '', ['present','late','leave'])
                              ? $_POST['checkin_status']
                              : 'present';
            $checkin_notes  = trim($_POST['checkin_notes'] ?? '');

            // Check if already checked in today
            $already = $pdo->prepare("SELECT id FROM coach_attendance WHERE coach_id = ? AND session_date = ?");
            $already->execute([$own_coach['id'], $checkin_date]);
            if ($already->fetch()) {
                $flash      = 'You have already checked in for today (' . date('d M Y') . ').';
                $flash_type = 'error';
            } else {
                $pdo->prepare("
                    INSERT INTO coach_attendance (coach_id, session_date, status, notes, recorded_by)
                    VALUES (?, ?, ?, ?, ?)
                ")->execute([$own_coach['id'], $checkin_date, $checkin_status, $checkin_notes ?: null, $viewer_id]);
                $flash = 'Attendance checked in for ' . date('d M Y') . ' — ' . ucfirst($checkin_status) . '.';
            }
        } else {
            $flash      = 'Coach profile not found. Contact an admin.';
            $flash_type = 'error';
        }

        header('Location: coach.php?msg=' . urlencode($flash) . '&mtype=' . $flash_type);
        exit;
    }

    // Admin-only actions below ────────────────────────────────────────────────
    if ($is_admin) {

    // Mark attendance (admin override — any coach, any date)
    if ($action === 'mark_attendance') {
        $coach_id    = (int)$_POST['coach_id'];
        $sess_date   = $_POST['session_date'] ?? date('Y-m-d');
        $att_status  = $_POST['att_status'] ?? 'present';
        $att_notes   = trim($_POST['att_notes'] ?? '');
        if ($coach_id) {
            // Upsert
            $pdo->prepare("
                INSERT INTO coach_attendance (coach_id, session_date, status, notes, recorded_by)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE status=VALUES(status), notes=VALUES(notes), recorded_by=VALUES(recorded_by)
            ")->execute([$coach_id, $sess_date, $att_status, $att_notes ?: null, $viewer_id]);
            $flash = 'Attendance marked successfully.';
        }
    }

    // Promote user to coach (creates coaches row)
    if ($action === 'promote_to_coach') {
        $uid = (int)$_POST['user_id'];
        $spec = trim($_POST['specialisation'] ?? '');
        $qual = trim($_POST['qualification'] ?? '');
        $hire = $_POST['hire_date'] ?? date('Y-m-d');
        if ($uid) {
            $pdo->prepare("UPDATE users SET role='coach' WHERE id=?")->execute([$uid]);
            $pdo->prepare("
                INSERT IGNORE INTO coaches (user_id, specialisation, qualification, hire_date,)
                VALUES (?, ?, ?, ?)
            ")->execute([$uid, $spec ?: null, $qual ?: null, $hire, $head]);
            $flash = 'User promoted to coach successfully.';
        }
    }

    // Assign a student (booking) to a coach
    if ($action === 'assign_student') {
        $assign_coach_id  = (int)($_POST['assign_coach_id'] ?? 0);
        $assign_booking_id = (int)($_POST['booking_id'] ?? 0);
        $assign_notes      = trim($_POST['assign_notes'] ?? '');
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
                            status = 'active',
                            notes = VALUES(notes),
                            assigned_by = VALUES(assigned_by)
                    ")->execute([
                        $assign_coach_id, $assign_booking_id,
                        $brow['booked_by_user_id'], $brow['program'],
                        $viewer_id, $assign_notes ?: null,
                    ]);
                    $flash = 'Student assigned to coach successfully.';
                } catch (PDOException $e) {
                    $flash = 'Assignment error: ' . $e->getMessage();
                }
            }
        }
    }

    // Remove (soft-delete) an assignment
    if ($action === 'remove_assignment') {
        $asgn_id = (int)($_POST['assignment_id'] ?? 0);
        if ($asgn_id) {
            $pdo->prepare("UPDATE coach_assignments SET status='removed' WHERE id=?")->execute([$asgn_id]);
            $flash = 'Assignment removed.';
        }
    }

    if ($flash) {
        header('Location: coach.php?msg=' . urlencode($flash));
        exit;
    }

    } // end if ($is_admin)
}
if (isset($_GET['msg'])) {
    $flash      = htmlspecialchars($_GET['msg']);
    $flash_type = $_GET['mtype'] ?? 'success';
}

// ── Check if coaches table exists ─────────────────────────────────────────────
$coaches_table_exists = false;
try {
    $pdo->query("SELECT 1 FROM coaches LIMIT 1");
    $coaches_table_exists = true;
} catch (PDOException $e) {}

$attendance_table_exists = false;
try {
    $pdo->query("SELECT 1 FROM coach_attendance LIMIT 1");
    $attendance_table_exists = true;
} catch (PDOException $e) {}

$assignments_table_exists = false;
try {
    $pdo->query("SELECT 1 FROM coach_assignments LIMIT 1");
    $assignments_table_exists = true;
} catch (PDOException $e) {}

// ── Auto-create coaches row if promoted via role change without coaches table entry ──
if (!$is_admin && $coaches_table_exists) {
    try {
        $chk = $pdo->prepare("SELECT id FROM coaches WHERE user_id = ?");
        $chk->execute([$viewer_id]);
        if (!$chk->fetch()) {
            $pdo->prepare("INSERT IGNORE INTO coaches (user_id, hire_date) VALUES (?, CURDATE())")->execute([$viewer_id]);
        }
    } catch (PDOException $e) {}
}

// ── Fetch coaches ─────────────────────────────────────────────────────────────
$coaches = [];
$selected_coach = null;
$attendance_log = [];
$att_stats = ['present'=>0,'absent'=>0,'late'=>0,'leave'=>0];

if ($coaches_table_exists) {
    $coach_where = $is_admin ? "" : "AND c.user_id = $viewer_id";
    $coaches = $pdo->query("
        SELECT c.*, u.first_name, u.last_name, u.email, u.phone, u.created_at AS joined
        FROM coaches c
        JOIN users u ON u.id = c.user_id
        WHERE u.is_active = 1 $coach_where
        ORDER BY u.first_name ASC
    ")->fetchAll();

    // Selected coach for detail view
    $sel_id = isset($_GET['coach']) ? (int)$_GET['coach'] : ($coaches[0]['id'] ?? null);
    if ($sel_id) {
        foreach ($coaches as $c) {
            if ((int)$c['id'] === $sel_id) { $selected_coach = $c; break; }
        }
        // Non-admins can only see their own
        if (!$is_admin && $selected_coach && $selected_coach['user_id'] != $viewer_id) {
            $selected_coach = null;
        }
    }

    // Attendance log for selected coach
    if ($selected_coach && $attendance_table_exists) {
        $att_month = $_GET['att_month'] ?? date('Y-m');
        $attendance_log = $pdo->prepare("
            SELECT ca.*, u.first_name AS rec_fname, u.last_name AS rec_lname
            FROM coach_attendance ca
            LEFT JOIN users u ON u.id = ca.recorded_by
            WHERE ca.coach_id = ? AND DATE_FORMAT(ca.session_date,'%Y-%m') = ?
            ORDER BY ca.session_date DESC
        ");
        $attendance_log->execute([$selected_coach['id'], $att_month]);
        $attendance_log = $attendance_log->fetchAll();

        // Stats — last 30 days
        $att_raw = $pdo->prepare("
            SELECT status, COUNT(*) as cnt FROM coach_attendance
            WHERE coach_id = ? AND session_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY status
        ");
        $att_raw->execute([$selected_coach['id']]);
        foreach ($att_raw->fetchAll() as $row) {
            $att_stats[$row['status']] = (int)$row['cnt'];
        }
    }
}

// Fetch all non-coach users for promotion (admin only)
$promotable = [];
if ($is_admin) {
    $promotable = $pdo->query("
        SELECT id, first_name, last_name, phone, email
        FROM users
        WHERE role = 'user' AND is_active = 1
        ORDER BY first_name ASC
        LIMIT 100
    ")->fetchAll();
}

// Fetch assignments for selected coach
$assignments = [];
$assignable_bookings = [];
if ($selected_coach && $assignments_table_exists) {
    $asgn_stmt = $pdo->prepare("
        SELECT ca.id, ca.booking_id, ca.program, ca.assigned_at, ca.notes,
               b.booking_reference, b.swimmer_name, b.status AS booking_status,
               u.first_name, u.last_name, u.phone AS student_phone,
               ab.first_name AS assigner_fn, ab.last_name AS assigner_ln
        FROM coach_assignments ca
        JOIN bookings b ON b.id = ca.booking_id
        JOIN users u    ON u.id = ca.student_user_id
        JOIN users ab   ON ab.id = ca.assigned_by
        WHERE ca.coach_id = ? AND ca.status = 'active'
        ORDER BY ca.assigned_at DESC
    ");
    $asgn_stmt->execute([$selected_coach['id']]);
    $assignments = $asgn_stmt->fetchAll();

    // Bookings that can still be assigned to this coach (admin only)
    if ($is_admin) {
        $avail_stmt = $pdo->prepare("
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
            ORDER BY b.created_at DESC
            LIMIT 200
        ");
        $avail_stmt->execute([$selected_coach['id']]);
        $assignable_bookings = $avail_stmt->fetchAll();
    }
}

$total_coaches = count($coaches);
$att_total = array_sum($att_stats);
$att_rate  = $att_total > 0 ? round(($att_stats['present'] / $att_total) * 100) : 0;
$total_assignments = 0;
if ($assignments_table_exists) {
    $total_assignments = (int)$pdo->query("SELECT COUNT(*) FROM coach_assignments WHERE status='active'")->fetchColumn();
}

// For coaches: detect their own coaches.id and whether they've checked in today
// Auto-create a coaches row if the user has role='coach' but no coaches record yet
$coach_own_record   = null;
$coach_checked_today = false;
$coach_today_entry  = null;
if (!$is_admin && $coaches_table_exists && $attendance_table_exists) {
    try {
        $cor = $pdo->prepare("SELECT id FROM coaches WHERE user_id = ?");
        $cor->execute([$viewer_id]);
        $coach_own_record = $cor->fetch();

        // Auto-create the coaches row if it doesn't exist yet
        if (!$coach_own_record) {
            $pdo->prepare(
                "INSERT IGNORE INTO coaches (user_id, hire_date) VALUES (?, CURDATE())"
            )->execute([$viewer_id]);
            $cor->execute([$viewer_id]);
            $coach_own_record = $cor->fetch();
        }

        if ($coach_own_record) {
            $tod = $pdo->prepare("SELECT status, notes FROM coach_attendance WHERE coach_id = ? AND session_date = ?");
            $tod->execute([$coach_own_record['id'], date('Y-m-d')]);
            $coach_today_entry = $tod->fetch();
            $coach_checked_today = ($coach_today_entry !== false);
        }
    } catch (PDOException $e) {}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Coaching Staff — Darken Shadows SC</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700;900&family=Raleway:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
:root {
  --ocean-1:#e8f4fd; --ocean-2:#b8ddf5; --ocean-3:#6db8e8;
  --ocean-4:#2a8cc4; --ocean-5:#1565a0; --ocean-6:#0d3d6b; --ocean-7:#061e35;
  --accent:#00e5b0; --accent-2:#00b38a; --danger:#ff4d6d; --warn:#ffc847;
  --font-display:'Cinzel',serif; --font-body:'Raleway',sans-serif;
  --radius:12px; --radius-sm:7px;
  --card-bg:rgba(13,61,107,.42); --card-border:rgba(0,229,176,.15);
  --transition:.22s cubic-bezier(.4,0,.2,1);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html{scroll-behavior:smooth;}
body{font-family:var(--font-body);background:var(--ocean-7);color:var(--ocean-1);min-height:100vh;}
body::before{content:'';position:fixed;inset:0;z-index:0;
  background:radial-gradient(ellipse 70% 55% at 10% 5%,rgba(0,229,176,.12) 0%,transparent 55%),
  linear-gradient(160deg,var(--ocean-7) 0%,#062e22 60%,var(--ocean-7) 100%);pointer-events:none;}
body::after{content:'';position:fixed;inset:0;z-index:0;
  background-image:radial-gradient(circle at 1px 1px,rgba(0,229,176,.04) 1px,transparent 0);
  background-size:36px 36px;pointer-events:none;}

/* ── Nav ── */
nav{position:sticky;top:0;z-index:100;display:flex;align-items:center;justify-content:space-between;
  padding:1rem 2.5rem;background:rgba(6,30,53,.92);backdrop-filter:blur(20px);
  border-bottom:1px solid rgba(0,229,176,.15);}
.nav-logo{font-family:var(--font-display);font-size:.95rem;font-weight:700;letter-spacing:.12em;color:var(--accent);text-decoration:none;text-transform:uppercase;}
.nav-links{display:flex;gap:1.4rem;align-items:center;}
.nav-links a{font-size:.76rem;font-weight:600;letter-spacing:.1em;color:var(--ocean-2);text-decoration:none;text-transform:uppercase;transition:color var(--transition);}
.nav-links a:hover,.nav-links a.active{color:var(--accent);}
.role-chip{font-size:.66rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;
  padding:.22rem .75rem;border-radius:100px;
  background:rgba(0,229,176,.1);border:1px solid rgba(0,229,176,.3);color:var(--accent);}

/* ── Layout ── */
.page{position:relative;z-index:1;max-width:1300px;margin:0 auto;padding:2rem 2rem 5rem;}
.layout{display:grid;grid-template-columns:300px 1fr;gap:1.5rem;align-items:start;}

/* ── Flash ── */
.flash{position:fixed;top:80px;right:20px;z-index:200;
  border-radius:var(--radius-sm);padding:.75rem 1.2rem;
  font-size:.82rem;font-weight:500;
  animation:slideIn .4s ease;max-width:340px;}
.flash.success{background:rgba(0,229,176,.12);border:1px solid rgba(0,229,176,.3);color:var(--accent);}
.flash.error{background:rgba(255,77,109,.12);border:1px solid rgba(255,77,109,.3);color:#ff4d6d;}
@keyframes slideIn{from{opacity:0;transform:translateX(30px)}to{opacity:1;transform:translateX(0)}}

/* ── Coach self check-in card ── */
.checkin-card{
  background:linear-gradient(135deg,rgba(0,229,176,.07) 0%,rgba(0,179,138,.04) 100%);
  border:1px solid rgba(0,229,176,.28);
  border-radius:var(--radius);
  padding:1.6rem 1.8rem;
  margin-bottom:1.5rem;
  position:relative;overflow:hidden;
  opacity:0;animation:fadeUp .5s .15s ease forwards;
}
.checkin-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;
  background:linear-gradient(90deg,var(--accent-2),var(--accent),rgba(0,229,176,.3));}
.checkin-title{font-family:var(--font-display);font-size:.85rem;font-weight:700;
  letter-spacing:.12em;text-transform:uppercase;color:var(--accent);margin-bottom:.3rem;}
.checkin-subtitle{font-size:.8rem;font-weight:300;color:var(--ocean-2);margin-bottom:1.2rem;}
.checkin-form{display:flex;align-items:flex-end;gap:.8rem;flex-wrap:wrap;}
.checkin-status-group{display:flex;gap:.5rem;flex-wrap:wrap;}
.checkin-radio{display:none;}
.checkin-label{
  display:flex;align-items:center;gap:.4rem;
  padding:.5rem 1rem;border-radius:100px;
  font-size:.75rem;font-weight:600;letter-spacing:.1em;text-transform:uppercase;
  cursor:pointer;transition:all var(--transition);
  border:1px solid rgba(0,229,176,.2);color:var(--ocean-2);background:rgba(255,255,255,.04);
  user-select:none;
}
.checkin-radio:checked + .checkin-label{color:var(--ocean-7);border-color:transparent;}
.checkin-radio[value="present"]:checked + .checkin-label{background:var(--accent);box-shadow:0 4px 14px rgba(0,229,176,.35);}
.checkin-radio[value="late"]:checked + .checkin-label{background:#ffc847;box-shadow:0 4px 14px rgba(255,200,71,.35);}
.checkin-radio[value="leave"]:checked + .checkin-label{background:#b57bee;box-shadow:0 4px 14px rgba(181,123,238,.35);}
.checkin-notes-wrap{flex:1;min-width:160px;}
.checkin-notes-input{
  width:100%;padding:.52rem .85rem;
  background:rgba(255,255,255,.05);border:1px solid rgba(0,229,176,.18);
  border-radius:var(--radius-sm);font-family:var(--font-body);font-size:.84rem;
  color:var(--ocean-1);outline:none;transition:border-color var(--transition);}
.checkin-notes-input:focus{border-color:var(--accent);}
.btn-checkin{
  padding:.6rem 1.4rem;
  background:linear-gradient(135deg,var(--accent-2),var(--accent));
  border:none;border-radius:var(--radius-sm);
  font-family:var(--font-display);font-size:.78rem;font-weight:700;
  letter-spacing:.14em;text-transform:uppercase;color:var(--ocean-7);
  cursor:pointer;transition:all var(--transition);white-space:nowrap;
  box-shadow:0 4px 14px rgba(0,229,176,.28);}
.btn-checkin:hover{opacity:.9;transform:translateY(-2px);box-shadow:0 7px 20px rgba(0,229,176,.4);}
.checkin-done{
  display:flex;align-items:center;gap:1rem;
  background:rgba(0,229,176,.07);border:1px solid rgba(0,229,176,.2);
  border-radius:var(--radius-sm);padding:.85rem 1.2rem;
  font-size:.88rem;color:var(--ocean-1);}
.checkin-done .cd-icon{font-size:1.5rem;flex-shrink:0;}
.checkin-done .cd-text strong{color:var(--accent);}
.checkin-done .cd-text small{display:block;font-size:.75rem;color:var(--ocean-3);margin-top:.1rem;}

/* ── Page header ── */
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;
  opacity:0;animation:fadeUp .5s .1s ease forwards;}
.page-header h1{font-family:var(--font-display);font-size:clamp(1.2rem,2.5vw,1.7rem);font-weight:700;color:var(--white);letter-spacing:.04em;}
.page-header p{font-size:.82rem;color:var(--ocean-3);font-weight:300;margin-top:.3rem;}

/* ── Setup banner ── */
.setup-banner{background:rgba(255,200,71,.07);border:1px solid rgba(255,200,71,.25);
  border-radius:var(--radius);padding:1.8rem 2rem;margin-bottom:1.5rem;
  opacity:0;animation:fadeUp .5s .15s ease forwards;}
.setup-banner h3{font-family:var(--font-display);font-size:.95rem;color:var(--warn);margin-bottom:.6rem;}
.setup-banner p{font-size:.82rem;color:rgba(184,221,245,.7);font-weight:300;line-height:1.7;}
.setup-banner code{display:block;background:rgba(0,0,0,.3);border:1px solid rgba(255,200,71,.15);
  border-radius:6px;padding:.8rem 1rem;font-size:.75rem;color:#ffd580;margin-top:.8rem;
  white-space:pre;overflow-x:auto;font-family:monospace;}

/* ── Stats strip ── */
.stats-strip{display:grid;grid-template-columns:repeat(4,1fr);gap:.8rem;margin-bottom:1.5rem;
  opacity:0;animation:fadeUp .5s .18s ease forwards;}
.s-card{background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius-sm);
  padding:.9rem 1rem;text-align:center;backdrop-filter:blur(8px);}
.s-num{font-family:var(--font-display);font-size:1.6rem;font-weight:700;color:var(--accent);line-height:1;margin-bottom:.2rem;}
.s-label{font-size:.6rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:rgba(184,221,245,.4);}
.s-card.w .s-num{color:var(--warn);}
.s-card.d .s-num{color:var(--danger);}

/* ── Sidebar — coach list ── */
.coach-sidebar{
  background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius);
  backdrop-filter:blur(10px);overflow:hidden;
  opacity:0;animation:fadeUp .5s .25s ease forwards;
  position:sticky;top:80px;
}
.sidebar-head{padding:.85rem 1.2rem;background:rgba(0,229,176,.07);border-bottom:1px solid rgba(0,229,176,.13);
  font-family:var(--font-display);font-size:.72rem;font-weight:600;letter-spacing:.18em;text-transform:uppercase;color:var(--accent);}
.coach-list{max-height:600px;overflow-y:auto;}
.coach-item{
  display:flex;align-items:center;gap:.8rem;
  padding:.9rem 1.1rem;border-bottom:1px solid rgba(0,229,176,.07);
  text-decoration:none;color:inherit;cursor:pointer;
  transition:background var(--transition);
}
.coach-item:hover,.coach-item.active{background:rgba(0,229,176,.08);}
.coach-item.active{border-left:3px solid var(--accent);}
.coach-avatar{width:38px;height:38px;border-radius:50%;flex-shrink:0;
  background:linear-gradient(135deg,var(--accent-2),var(--accent));
  display:flex;align-items:center;justify-content:center;
  font-family:var(--font-display);font-size:.9rem;font-weight:700;color:var(--ocean-7);}
.coach-item-info{}
.coach-item-name{font-size:.85rem;font-weight:600;color:var(--ocean-1);}
.coach-item-spec{font-size:.72rem;font-weight:300;color:rgba(184,221,245,.45);}
.head-badge{font-size:.58rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;
  padding:.15rem .55rem;border-radius:100px;
  background:rgba(255,200,71,.12);border:1px solid rgba(255,200,71,.3);color:var(--warn);
  display:inline-block;margin-top:.2rem;}

/* ── Detail panel ── */
.detail-panel{
  opacity:0;animation:fadeUp .5s .3s ease forwards;
}
.detail-card{background:var(--card-bg);border:1px solid var(--card-border);border-radius:var(--radius);
  backdrop-filter:blur(10px);overflow:hidden;margin-bottom:1.2rem;position:relative;}
.detail-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;
  background:linear-gradient(90deg,var(--accent-2),var(--accent),transparent);}
.card-head{padding:1rem 1.5rem;background:rgba(0,229,176,.06);border-bottom:1px solid rgba(0,229,176,.12);
  font-family:var(--font-display);font-size:.72rem;font-weight:600;letter-spacing:.18em;
  text-transform:uppercase;color:var(--accent);display:flex;align-items:center;gap:.6rem;}
.card-body{padding:1.5rem;}

/* Profile layout */
.profile-header-strip{display:flex;align-items:center;gap:1.5rem;margin-bottom:1.5rem;}
.profile-avatar-lg{width:70px;height:70px;border-radius:50%;flex-shrink:0;
  background:linear-gradient(135deg,var(--accent-2),var(--accent));
  display:flex;align-items:center;justify-content:center;
  font-family:var(--font-display);font-size:1.6rem;font-weight:700;color:var(--ocean-7);
  box-shadow:0 0 0 3px rgba(0,229,176,.2);}
.profile-name{font-family:var(--font-display);font-size:1.2rem;font-weight:700;color:var(--white);margin-bottom:.3rem;}
.profile-meta{display:flex;flex-wrap:wrap;gap:.4rem 1.2rem;font-size:.78rem;color:var(--ocean-2);}

/* Info grid */
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:.8rem;}
.info-item{background:rgba(255,255,255,.04);border:1px solid rgba(0,229,176,.1);
  border-radius:var(--radius-sm);padding:.85rem 1rem;}
.info-item .ilabel{font-size:.62rem;font-weight:700;letter-spacing:.18em;text-transform:uppercase;
  color:rgba(184,221,245,.4);margin-bottom:.35rem;}
.info-item .ival{font-size:.88rem;font-weight:500;color:var(--ocean-1);}
.info-item .ival.empty{color:rgba(184,221,245,.3);font-style:italic;font-size:.82rem;}

/* Programs tags */
.prog-tags{display:flex;flex-wrap:wrap;gap:.4rem;margin-top:.2rem;}
.prog-tag{font-size:.65rem;font-weight:600;letter-spacing:.08em;text-transform:uppercase;
  padding:.2rem .6rem;border-radius:4px;background:rgba(0,229,176,.1);
  border:1px solid rgba(0,229,176,.2);color:var(--accent);}

/* Attendance ── */
.att-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:.7rem;margin-bottom:1.2rem;}
.att-stat{background:rgba(255,255,255,.04);border:1px solid rgba(0,229,176,.1);border-radius:var(--radius-sm);
  padding:.8rem;text-align:center;}
.att-num{font-family:var(--font-display);font-size:1.4rem;font-weight:700;line-height:1;margin-bottom:.2rem;}
.att-label{font-size:.6rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:rgba(184,221,245,.4);}
.att-stat.p .att-num{color:var(--accent);}
.att-stat.a .att-num{color:var(--danger);}
.att-stat.l .att-num{color:var(--warn);}
.att-stat.lv .att-num{color:#b57bee;}
.att-rate{display:flex;align-items:center;gap:.6rem;margin-bottom:1.2rem;}
.att-bar-wrap{flex:1;height:6px;background:rgba(255,255,255,.07);border-radius:3px;overflow:hidden;}
.att-bar{height:100%;background:linear-gradient(90deg,var(--accent-2),var(--accent));border-radius:3px;transition:width .6s ease;}
.att-rate-label{font-size:.75rem;font-weight:600;color:var(--accent);white-space:nowrap;}

/* Attendance table */
.att-table{width:100%;border-collapse:collapse;font-size:.82rem;}
.att-table th{padding:.55rem .8rem;font-size:.6rem;font-weight:700;letter-spacing:.16em;
  text-transform:uppercase;color:rgba(184,221,245,.35);text-align:left;
  border-bottom:1px solid rgba(0,229,176,.1);}
.att-table td{padding:.65rem .8rem;border-bottom:1px solid rgba(0,229,176,.06);}
.att-table tr:last-child td{border-bottom:none;}
.att-pill{display:inline-block;padding:.18rem .65rem;border-radius:100px;font-size:.62rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;}
.att-pill.present{background:rgba(0,229,176,.1);border:1px solid rgba(0,229,176,.25);color:var(--accent);}
.att-pill.absent{background:rgba(255,77,109,.1);border:1px solid rgba(255,77,109,.25);color:#ff4d6d;}
.att-pill.late{background:rgba(255,200,71,.1);border:1px solid rgba(255,200,71,.25);color:#ffc847;}
.att-pill.leave{background:rgba(181,123,238,.1);border:1px solid rgba(181,123,238,.25);color:#b57bee;}

/* ── Forms ── */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:.9rem;}
.form-group{display:flex;flex-direction:column;gap:.35rem;}
.form-group.span-2{grid-column:span 2;}
label.flabel{font-size:.68rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:rgba(184,221,245,.45);}
input[type=text],input[type=date],select,textarea{
  width:100%;padding:.6rem .9rem;background:rgba(255,255,255,.05);
  border:1px solid rgba(0,229,176,.18);border-radius:var(--radius-sm);
  font-family:var(--font-body);font-size:.85rem;color:var(--ocean-1);
  outline:none;transition:border-color var(--transition);}
input:focus,select:focus,textarea:focus{border-color:var(--accent);}
select option{background:var(--ocean-6);}
.btn-submit{padding:.65rem 1.4rem;background:linear-gradient(135deg,var(--accent-2),var(--accent));
  border:none;border-radius:var(--radius-sm);font-family:var(--font-display);
  font-size:.78rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;
  color:var(--ocean-7);cursor:pointer;transition:all var(--transition);}
.btn-submit:hover{opacity:.9;transform:translateY(-1px);}
.month-nav{display:flex;align-items:center;gap:.6rem;margin-bottom:.8rem;}
.month-nav a{padding:.3rem .75rem;border-radius:4px;font-size:.72rem;font-weight:600;
  color:var(--ocean-3);text-decoration:none;border:1px solid rgba(0,229,176,.18);transition:all var(--transition);}
.month-nav a:hover{background:rgba(0,229,176,.1);color:var(--accent);}
.month-nav span{font-family:var(--font-display);font-size:.82rem;color:var(--ocean-2);letter-spacing:.08em;}

/* Empty states */
.empty-box{background:var(--card-bg);border:1px dashed rgba(0,229,176,.18);border-radius:var(--radius);
  padding:3rem;text-align:center;color:rgba(184,221,245,.4);font-size:.88rem;font-weight:300;}
.empty-box .eicon{font-size:2.5rem;display:block;margin-bottom:1rem;}

.no-coaches{background:var(--card-bg);border:1px dashed rgba(0,229,176,.18);border-radius:var(--radius);
  padding:2rem;text-align:center;}
.no-coaches p{color:rgba(184,221,245,.45);font-size:.88rem;margin-bottom:1rem;font-weight:300;}

/* ── Assignment table ── */
.asgn-table{width:100%;border-collapse:collapse;font-size:.82rem;}
.asgn-table th{padding:.55rem .9rem;font-size:.6rem;font-weight:700;letter-spacing:.16em;
  text-transform:uppercase;color:rgba(184,221,245,.35);text-align:left;
  border-bottom:1px solid rgba(0,229,176,.1);}
.asgn-table td{padding:.7rem .9rem;border-bottom:1px solid rgba(0,229,176,.06);vertical-align:middle;}
.asgn-table tr:last-child td{border-bottom:none;}
.asgn-table tr:hover td{background:rgba(0,229,176,.04);}
.prog-asgn-chip{display:inline-block;padding:.18rem .6rem;border-radius:4px;font-size:.62rem;
  font-weight:700;letter-spacing:.08em;text-transform:uppercase;
  background:rgba(42,140,196,.15);color:var(--ocean-3);border:1px solid rgba(42,140,196,.25);}
.btn-remove{padding:.26rem .7rem;background:rgba(255,77,109,.1);border:1px solid rgba(255,77,109,.28);
  border-radius:4px;font-family:var(--font-body);font-size:.65rem;font-weight:700;
  letter-spacing:.08em;text-transform:uppercase;color:#ff4d6d;cursor:pointer;transition:all var(--transition);}
.btn-remove:hover{background:rgba(255,77,109,.22);}
.no-asgn{text-align:center;padding:1.5rem;color:rgba(184,221,245,.35);font-size:.85rem;}

/* Setup banner link */
.assignments-setup-note{background:rgba(255,200,71,.06);border:1px solid rgba(255,200,71,.2);
  border-radius:var(--radius-sm);padding:.85rem 1rem;font-size:.8rem;color:rgba(184,221,245,.6);
  line-height:1.6;margin-bottom:.5rem;}
.assignments-setup-note code{color:#ffd580;font-family:monospace;font-size:.78rem;}

@keyframes fadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
@media(max-width:1024px){.layout{grid-template-columns:1fr;}.coach-sidebar{position:static;}}
@media(max-width:768px){nav{padding:1rem 1rem;}.page{padding:1.2rem .8rem 4rem;}
  .stats-strip{grid-template-columns:repeat(2,1fr);}.info-grid{grid-template-columns:1fr;}
  .att-stats{grid-template-columns:repeat(2,1fr);}.form-grid{grid-template-columns:1fr;}
  .form-group.span-2{grid-column:span 1;}}
</style>
</head>
<body>

<?php if ($flash): ?>
<div class="flash <?= ($flash_type ?? 'success') === 'error' ? 'error' : 'success' ?>" id="flash-msg">
  <?= ($flash_type ?? 'success') === 'error' ? '⚠' : '✓' ?> <?= $flash ?>
</div>
<script>setTimeout(()=>document.getElementById('flash-msg')?.remove(), 4500);</script>
<?php endif; ?>

<nav>
  <a href="<?= $is_admin ? 'admin.php' : 'index.php' ?>" class="nav-logo">🏊 Coaches</a>
  <div class="nav-links">
    <?php if ($is_admin): ?>
      <a href="admin.php">Admin</a>
      <a href="database.php">Users</a>
    <?php else: ?>
      <a href="index.php">Dashboard</a>
    <?php endif; ?>
    <a href="coach.php" class="active">Coaching Staff</a>
    <a href="logout.php">Logout</a>
  </div>
  <span class="role-chip"><?= $is_admin ? '⚙ Admin View' : '🏊 Coach View' ?></span>
</nav>

<div class="page">

  <div class="page-header">
    <div>
      <h1>🏊 Coaching Staff</h1>
      <p><?= $is_admin ? 'Full admin view — manage coaches, mark attendance, promote members' : 'Your coaching profile and attendance record' ?></p>
    </div>
  </div>

  <?php if (!$is_admin && $coaches_table_exists && $attendance_table_exists): ?>
  <!-- ── Coach self check-in ──────────────────────────────────────────────── -->
  <div class="checkin-card">
    <div class="checkin-title">📍 Mark Your Attendance</div>
    <div class="checkin-subtitle">
      <?= date('l, d F Y') ?> &nbsp;·&nbsp; Session check-in for today
    </div>

    <?php if ($coach_checked_today && $coach_today_entry): ?>
    <!-- Already checked in -->
    <?php
      $entry_status = $coach_today_entry['status'];
      $status_icons = ['present'=>'✅','late'=>'🕐','leave'=>'🏖️','absent'=>'❌'];
      $entry_icon   = $status_icons[$entry_status] ?? '✔';
    ?>
    <div class="checkin-done">
      <span class="cd-icon"><?= $entry_icon ?></span>
      <div class="cd-text">
        <strong>Checked in — <?= ucfirst(htmlspecialchars($entry_status)) ?></strong>
        <small>
          <?php if ($coach_today_entry['notes']): ?>
            Note: <?= htmlspecialchars($coach_today_entry['notes']) ?>
          <?php else: ?>
            No additional notes recorded.
          <?php endif; ?>
        </small>
      </div>
    </div>

    <?php elseif (!$coach_own_record): ?>
    <p style="font-size:.82rem;color:rgba(184,221,245,.5);">Your coach profile hasn't been set up yet. Contact an admin.</p>

    <?php else: ?>
    <!-- Check-in form -->
    <form method="POST" class="checkin-form">
      <input type="hidden" name="action" value="coach_checkin">

      <div class="checkin-status-group">
        <input type="radio" class="checkin-radio" name="checkin_status" id="cs-present" value="present" checked>
        <label class="checkin-label" for="cs-present">✅ Present</label>

        <input type="radio" class="checkin-radio" name="checkin_status" id="cs-late" value="late">
        <label class="checkin-label" for="cs-late">🕐 Late</label>

        <input type="radio" class="checkin-radio" name="checkin_status" id="cs-leave" value="leave">
        <label class="checkin-label" for="cs-leave">🏖️ On Leave</label>
      </div>

      <div class="checkin-notes-wrap">
        <input type="text" class="checkin-notes-input" name="checkin_notes"
               placeholder="Optional note (e.g. running 10 min late)">
      </div>

      <button type="submit" class="btn-checkin">Check In →</button>
    </form>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if (!$coaches_table_exists): ?>
  <!-- Setup banner -->
  <div class="setup-banner">
    <h3>⚠ Database Setup Required</h3>
    <p>The <code>coaches</code> and <code>coach_attendance</code> tables haven't been created yet. Run the following SQL in your database, then promote users to coaches from the <a href="database.php" style="color:var(--warn);">User Database</a>.</p>
    <code>-- Run this in MySQL/MariaDB (or paste into pool.sql)
CREATE TABLE coaches (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE coach_attendance (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    coach_id INT UNSIGNED NOT NULL,
    session_date DATE NOT NULL,
    status ENUM('present','absent','late','leave') NOT NULL DEFAULT 'present',
    notes TEXT DEFAULT NULL,
    recorded_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ca (coach_id, session_date),
    CONSTRAINT fk_ca_coach FOREIGN KEY (coach_id) REFERENCES coaches(id) ON DELETE CASCADE,
    CONSTRAINT fk_ca_recorder FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;</code>
  </div>
  <?php endif; ?>

  <!-- Stats strip -->
  <div class="stats-strip">
    <div class="s-card"><div class="s-num"><?= $total_coaches ?></div><div class="s-label">Total Coaches</div></div>
    <div class="s-card"><div class="s-num"><?= $att_stats['present'] ?></div><div class="s-label">Present (30d)</div></div>
    <div class="s-card d"><div class="s-num"><?= $att_stats['absent'] ?></div><div class="s-label">Absent (30d)</div></div>
    <?php if ($is_admin): ?>
    <div class="s-card" style="border-color:rgba(0,200,255,.2);"><div class="s-num" style="color:#00c8ff;"><?= $total_assignments ?></div><div class="s-label">Active Assignments</div></div>
    <?php endif; ?>
  </div>

  <?php if (!empty($coaches)): ?>
  <div class="layout">

    <!-- Sidebar: coach list -->
    <div class="coach-sidebar">
      <div class="sidebar-head">🏊 Staff (<?= $total_coaches ?>)</div>
      <div class="coach-list">
        <?php foreach ($coaches as $c):
          $initials = strtoupper(substr($c['first_name'],0,1).substr($c['last_name'],0,1));
          $is_sel   = $selected_coach && (int)$selected_coach['id'] === (int)$c['id'];
        ?>
        <a class="coach-item <?= $is_sel ? 'active' : '' ?>"
           href="?coach=<?= $c['id'] ?>">
          <div class="coach-avatar"><?= $initials ?></div>
          <div class="coach-item-info">
            <div class="coach-item-name"><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?></div>
            <div class="coach-item-spec"><?= htmlspecialchars($c['specialisation'] ?? 'Swimming Coach') ?></div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Detail panel -->
    <div class="detail-panel">

      <?php if ($selected_coach): ?>
      <?php
        $sc = $selected_coach;
        $initials_lg = strtoupper(substr($sc['first_name'],0,1).substr($sc['last_name'],0,1));
        $att_month = $_GET['att_month'] ?? date('Y-m');
        [$att_y, $att_m] = explode('-', $att_month);
        $prev_month = date('Y-m', mktime(0,0,0,(int)$att_m-1,1,(int)$att_y));
        $next_month = date('Y-m', mktime(0,0,0,(int)$att_m+1,1,(int)$att_y));
      ?>

      <!-- Profile card -->
      <div class="detail-card">
        <div class="card-head">👤 Coach Profile</div>
        <div class="card-body">
          <div class="profile-header-strip">
            <div class="profile-avatar-lg"><?= $initials_lg ?></div>
            <div>
              <div class="profile-name"><?= htmlspecialchars($sc['first_name'] . ' ' . $sc['last_name']) ?></div>
              <div class="profile-meta" style="margin-top:.5rem;">
                <span>📞 <?= htmlspecialchars($sc['phone'] ?? '—') ?></span>
                <span>✉ <?= htmlspecialchars($sc['email'] ?? '—') ?></span>
              </div>
            </div>
          </div>

          <div class="info-grid">
            <div class="info-item">
              <div class="ilabel">Specialisation</div>
              <div class="ival <?= $sc['specialisation'] ? '' : 'empty' ?>">
                <?= htmlspecialchars($sc['specialisation'] ?? 'Not specified') ?>
              </div>
            </div>
            <div class="info-item">
              <div class="ilabel">Qualification</div>
              <div class="ival <?= $sc['qualification'] ? '' : 'empty' ?>">
                <?= htmlspecialchars($sc['qualification'] ?? 'Not specified') ?>
              </div>
            </div>
            <div class="info-item">
              <div class="ilabel">Hire Date</div>
              <div class="ival <?= $sc['hire_date'] ? '' : 'empty' ?>">
                <?= $sc['hire_date'] ? date('d F Y', strtotime($sc['hire_date'])) : 'Not recorded' ?>
              </div>
            </div>
            <div class="info-item">
              <div class="ilabel">Assigned Programs</div>
              <div class="ival">
                <?php if ($sc['assigned_programs']): ?>
                <div class="prog-tags">
                  <?php foreach (explode(',', $sc['assigned_programs']) as $ap): ?>
                    <span class="prog-tag"><?= htmlspecialchars(ucwords(str_replace('_',' ',trim($ap)))) ?></span>
                  <?php endforeach; ?>
                </div>
                <?php else: ?>
                  <span class="empty">None assigned</span>
                <?php endif; ?>
              </div>
            </div>
            <?php if ($sc['bio']): ?>
            <div class="info-item" style="grid-column:span 2;">
              <div class="ilabel">Bio</div>
              <div class="ival" style="line-height:1.6;"><?= htmlspecialchars($sc['bio']) ?></div>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Attendance card -->
      <div class="detail-card">
        <div class="card-head">📅 Attendance Log</div>
        <div class="card-body">

          <?php if (!$attendance_table_exists): ?>
          <div class="empty-box"><span class="eicon">📋</span>Attendance table not set up yet — run the SQL above first.</div>
          <?php else: ?>

          <!-- 30-day stats -->
          <div class="att-stats">
            <div class="att-stat p"><div class="att-num"><?= $att_stats['present'] ?></div><div class="att-label">Present</div></div>
            <div class="att-stat a"><div class="att-num"><?= $att_stats['absent'] ?></div><div class="att-label">Absent</div></div>
            <div class="att-stat l"><div class="att-num"><?= $att_stats['late'] ?></div><div class="att-label">Late</div></div>
            <div class="att-stat lv"><div class="att-num"><?= $att_stats['leave'] ?></div><div class="att-label">On Leave</div></div>
          </div>

          <div class="att-rate">
            <div class="att-bar-wrap">
              <div class="att-bar" style="width:<?= $att_rate ?>%;"></div>
            </div>
            <span class="att-rate-label"><?= $att_rate ?>% attendance rate (30 days)</span>
          </div>

          <!-- Month navigation -->
          <div class="month-nav">
            <a href="?coach=<?= $sc['id'] ?>&att_month=<?= $prev_month ?>">← Prev</a>
            <span><?= date('F Y', mktime(0,0,0,(int)$att_m,1,(int)$att_y)) ?></span>
            <a href="?coach=<?= $sc['id'] ?>&att_month=<?= $next_month ?>">Next →</a>
          </div>

          <?php if (empty($attendance_log)): ?>
          <div class="empty-box" style="padding:1.5rem;">
            <span style="font-size:1.5rem;">📭</span>
            <p style="margin-top:.5rem;">No attendance records for this month.</p>
          </div>
          <?php else: ?>
          <table class="att-table">
            <thead><tr>
              <th>Date</th><th>Status</th><th>Notes</th><th>Recorded By</th>
            </tr></thead>
            <tbody>
              <?php foreach ($attendance_log as $att): ?>
              <tr>
                <td><?= date('D, d M Y', strtotime($att['session_date'])) ?></td>
                <td><span class="att-pill <?= $att['status'] ?>"><?= ucfirst($att['status']) ?></span></td>
                <td style="color:rgba(184,221,245,.55);"><?= $att['notes'] ? htmlspecialchars($att['notes']) : '—' ?></td>
                <td style="color:rgba(184,221,245,.45);font-size:.75rem;">
                  <?= $att['rec_fname'] ? htmlspecialchars($att['rec_fname'].' '.$att['rec_lname']) : 'System' ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php endif; ?>

          <!-- Mark attendance form (admin only) -->
          <?php if ($is_admin): ?>
          <details style="margin-top:1.2rem;">
            <summary style="cursor:pointer;font-size:.75rem;font-weight:600;color:var(--accent);letter-spacing:.1em;text-transform:uppercase;user-select:none;padding:.4rem 0;">
              + Mark Attendance
            </summary>
            <form method="POST" style="margin-top:.9rem;">
              <input type="hidden" name="action" value="mark_attendance">
              <input type="hidden" name="coach_id" value="<?= $sc['id'] ?>">
              <div class="form-grid">
                <div class="form-group">
                  <label class="flabel">Session Date</label>
                  <input type="date" name="session_date" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                  <label class="flabel">Status</label>
                  <select name="att_status">
                    <option value="present">Present</option>
                    <option value="absent">Absent</option>
                    <option value="late">Late</option>
                    <option value="leave">On Leave</option>
                  </select>
                </div>
                <div class="form-group span-2">
                  <label class="flabel">Notes (optional)</label>
                  <input type="text" name="att_notes" placeholder="e.g. Arrived 20 min late — traffic">
                </div>
              </div>
              <button type="submit" class="btn-submit" style="margin-top:.8rem;">Mark Attendance →</button>
            </form>
          </details>
          <?php endif; ?>

          <?php endif; // attendance table ?>
        </div>
      </div>

      <?php if ($is_admin): ?>
      <!-- ── Assigned Students card (admin only) ── -->
      <div class="detail-card">
        <div class="card-head">👤 Assigned Students
          <span style="margin-left:auto;font-size:.68rem;font-weight:400;color:rgba(184,221,245,.4);font-family:var(--font-body);letter-spacing:0;">
            <?= count($assignments) ?> active assignment<?= count($assignments) != 1 ? 's' : '' ?>
          </span>
        </div>
        <div class="card-body">

          <?php if (!$assignments_table_exists): ?>
          <div class="assignments-setup-note">
            ⚠ The <code>coach_assignments</code> table hasn't been created yet.
            Run the SQL from the top of this page in your database to enable student assignments.
          </div>

          <?php else: ?>

          <?php if (empty($assignments)): ?>
          <div class="no-asgn">No students currently assigned to this coach.</div>
          <?php else: ?>
          <table class="asgn-table">
            <thead><tr>
              <th>Swimmer</th><th>Booked By</th><th>Program</th>
              <th>Booking Ref</th><th>Enrollment</th><th>Assigned</th><th></th>
            </tr></thead>
            <tbody>
              <?php foreach ($assignments as $a):
                $prog_labels = [
                  'junior_development'  => 'Junior Dev',
                  'competitive_squad'   => 'Competitive',
                  'elite_coaching'      => 'Elite',
                  'adult_fitness_swim'  => 'Adult Fitness',
                  'mental_conditioning' => 'Mental',
                  'masters_program'     => 'Masters',
                ];
                $status_colors = [
                  'pending'   => '#ffc847','confirmed' => '#00d68f',
                  'active'    => '#00e5b0','completed' => '#6db8e8','cancelled' => '#ff4d6d',
                ];
                $sc_color = $status_colors[$a['booking_status']] ?? '#6db8e8';
              ?>
              <tr>
                <td>
                  <strong style="color:var(--ocean-1);"><?= htmlspecialchars($a['swimmer_name']) ?></strong><br>
                  <span style="color:rgba(184,221,245,.4);font-size:.73rem;"><?= htmlspecialchars($a['student_phone'] ?? '—') ?></span>
                </td>
                <td style="color:var(--ocean-2);">
                  <?= htmlspecialchars($a['first_name'] . ' ' . $a['last_name']) ?>
                </td>
                <td>
                  <span class="prog-asgn-chip"><?= htmlspecialchars($prog_labels[$a['program']] ?? $a['program']) ?></span>
                </td>
                <td style="font-family:'Cinzel',serif;font-size:.68rem;letter-spacing:.08em;color:rgba(184,221,245,.45);">
                  <?= htmlspecialchars($a['booking_reference']) ?>
                </td>
                <td>
                  <span style="display:inline-block;padding:.18rem .65rem;border-radius:100px;font-size:.62rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;background:<?= $sc_color ?>22;border:1px solid <?= $sc_color ?>55;color:<?= $sc_color ?>;">
                    <?= ucfirst($a['booking_status']) ?>
                  </span>
                </td>
                <td style="color:rgba(184,221,245,.4);font-size:.74rem;">
                  <?= date('d M Y', strtotime($a['assigned_at'])) ?><br>
                  <span style="font-size:.68rem;">by <?= htmlspecialchars($a['assigner_fn'] . ' ' . $a['assigner_ln']) ?></span>
                </td>
                <td>
                  <form method="POST" onsubmit="return confirm('Remove this assignment?')">
                    <input type="hidden" name="action" value="remove_assignment">
                    <input type="hidden" name="assignment_id" value="<?= $a['id'] ?>">
                    <button type="submit" class="btn-remove">✕ Remove</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php endif; ?>

          <!-- Assign new student -->
          <?php if ($assignments_table_exists): ?>
          <details style="margin-top:1.3rem;">
            <summary style="cursor:pointer;font-size:.75rem;font-weight:600;color:var(--accent);letter-spacing:.1em;text-transform:uppercase;user-select:none;padding:.4rem 0;">
              + Assign a Student to This Coach
            </summary>
            <form method="POST" style="margin-top:.9rem;">
              <input type="hidden" name="action" value="assign_student">
              <input type="hidden" name="assign_coach_id" value="<?= $sc['id'] ?>">
              <div class="form-grid">
                <div class="form-group span-2">
                  <label class="flabel">Select Enrollment <span style="color:var(--accent);">*</span></label>
                  <?php if (empty($assignable_bookings)): ?>
                    <p style="font-size:.8rem;color:rgba(184,221,245,.4);padding:.5rem 0;">
                      All active enrollments are already assigned to this coach.
                    </p>
                  <?php else: ?>
                  <select name="booking_id" required>
                    <option value="">— Choose an enrollment —</option>
                    <?php
                      $prog_labels_full = [
                        'junior_development'  => 'Junior Development',
                        'competitive_squad'   => 'Competitive Squad',
                        'elite_coaching'      => 'Elite Coaching',
                        'adult_fitness_swim'  => 'Adult Fitness Swim',
                        'mental_conditioning' => 'Mental Conditioning',
                        'masters_program'     => 'Masters Program',
                      ];
                      $prev_prog = '';
                      foreach ($assignable_bookings as $ab):
                        $prog_display = $prog_labels_full[$ab['program']] ?? ucwords(str_replace('_',' ',$ab['program']));
                        if ($prev_prog !== $ab['program']) {
                          if ($prev_prog !== '') echo '</optgroup>';
                          echo '<optgroup label="' . htmlspecialchars($prog_display) . '">';
                          $prev_prog = $ab['program'];
                        }
                    ?>
                    <option value="<?= $ab['id'] ?>">
                      <?= htmlspecialchars($ab['swimmer_name']) ?>
                      (<?= htmlspecialchars($ab['first_name'] . ' ' . $ab['last_name']) ?>)
                      · <?= htmlspecialchars($ab['booking_reference']) ?>
                      · <?= ucfirst($ab['status']) ?>
                    </option>
                    <?php endforeach; if ($prev_prog !== '') echo '</optgroup>'; ?>
                  </select>
                  <?php endif; ?>
                </div>
                <div class="form-group span-2">
                  <label class="flabel">Notes (optional)</label>
                  <input type="text" name="assign_notes" placeholder="e.g. Focus on butterfly technique, Mon/Wed/Fri sessions">
                </div>
              </div>
              <?php if (!empty($assignable_bookings)): ?>
              <button type="submit" class="btn-submit" style="margin-top:.8rem;">Assign Student →</button>
              <?php endif; ?>
            </form>
          </details>
          <?php endif; ?>

          <?php endif; // assignments table ?>
        </div>
      </div>
      <?php endif; // is_admin ?>

      <?php else: ?>
      <div class="empty-box"><span class="eicon">🏊</span>Select a coach from the left to view their profile and attendance.</div>
      <?php endif; ?>

    </div><!-- /detail-panel -->
  </div><!-- /layout -->

  <!-- Promote member to coach (admin only) -->
  <?php if ($is_admin && !empty($promotable)): ?>
  <div style="margin-top:2rem;opacity:0;animation:fadeUp .5s .55s ease forwards;">
    <div class="detail-card">
      <div class="card-head">⬆ Promote Member to Coach</div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="action" value="promote_to_coach">
          <div class="form-grid">
            <div class="form-group">
              <label class="flabel">Select Member <span style="color:var(--accent);">*</span></label>
              <select name="user_id" required>
                <option value="">— Choose a member —</option>
                <?php foreach ($promotable as $u): ?>
                <option value="<?= $u['id'] ?>">
                  <?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?>
                  <?= $u['phone'] ? '· '.$u['phone'] : '' ?>
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
            <div class="form-group span-2" style="flex-direction:row;align-items:center;gap:.7rem;background:rgba(255,200,71,.05);border:1px solid rgba(255,200,71,.15);border-radius:var(--radius-sm);padding:.75rem 1rem;">
              <label for="is_head" style="font-size:.85rem;font-weight:500;color:var(--ocean-1);cursor:pointer;text-transform:none;letter-spacing:0;">
                ⭐ Designate as Head Coach
              </label>
            </div>
          </div>
          <button type="submit" class="btn-submit" style="margin-top:1rem;">Promote to Coach →</button>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php elseif ($coaches_table_exists): ?>
  <!-- No coaches yet -->
  <div class="no-coaches">
    <p>No coaching staff found yet. Promote members to coaches using the User Database panel.</p>
    <?php if ($is_admin): ?>
    <a href="database.php" style="display:inline-block;padding:.6rem 1.3rem;border-radius:var(--radius-sm);background:rgba(0,229,176,.1);border:1px solid rgba(0,229,176,.25);color:var(--accent);font-size:.8rem;font-weight:600;text-decoration:none;letter-spacing:.08em;text-transform:uppercase;">
      Go to User Database →
    </a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

</div>
</body>
</html>