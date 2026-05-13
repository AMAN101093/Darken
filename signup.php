<?php
// signup.php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config/db.php';

$errors = [];
$success = '';
$old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST;

    $first_name   = trim($_POST['first_name'] ?? '');
    $middle_name  = trim($_POST['middle_name'] ?? '');
    $last_name    = trim($_POST['last_name'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $password     = $_POST['password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($first_name))  $errors['first_name'] = 'First name is required.';
    elseif (!preg_match('/^[A-Za-z\s]{2,50}$/', $first_name)) $errors['first_name'] = 'Enter a valid first name.';

    if (!empty($middle_name) && !preg_match('/^[A-Za-z\s]{1,50}$/', $middle_name))
        $errors['middle_name'] = 'Enter a valid middle name.';

    if (empty($last_name))  $errors['last_name'] = 'Last name is required.';
    elseif (!preg_match('/^[A-Za-z\s]{2,50}$/', $last_name)) $errors['last_name'] = 'Enter a valid last name.';

    if (empty($phone))  $errors['phone'] = 'Phone number is required.';
    elseif (!preg_match('/^[0-9]{7,15}$/', $phone)) $errors['phone'] = 'Enter a valid phone number (7-15 digits).';

    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors['email'] = 'Enter a valid email address.';

    if (empty($password))  $errors['password'] = 'Password is required.';
    elseif (strlen($password) < 8) $errors['password'] = 'Password must be at least 8 characters.';
    elseif (!preg_match('/[A-Z]/', $password)) $errors['password'] = 'Password must contain at least one uppercase letter.';
    elseif (!preg_match('/[0-9]/', $password)) $errors['password'] = 'Password must contain at least one number.';

    if (empty($confirm_pass)) $errors['confirm_password'] = 'Please confirm your password.';
    elseif ($password !== $confirm_pass) $errors['confirm_password'] = 'Passwords do not match.';

    if (empty($errors)) {
        global $pdo;

        // Check duplicate phone
        $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
        $stmt->execute([$phone]);
        if ($stmt->fetch()) $errors['phone'] = 'This phone number is already registered.';

        // Check duplicate email
        if (!empty($email)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) $errors['email'] = 'This email is already registered.';
        }
    }

    if (empty($errors)) {
        global $pdo;
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $emailVal = !empty($email) ? $email : null;
        $middleVal = !empty($middle_name) ? $middle_name : null;

        $stmt = $pdo->prepare("INSERT INTO users (first_name, middle_name, last_name, phone, email, password_hash) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$first_name, $middleVal, $last_name, $phone, $emailVal, $hash]);

        $_SESSION['signup_success'] = 'Account created! Please log in.';
        header('Location: login.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Sign Up — Darken Shadows</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700&family=Raleway:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --ocean-1: #0a0e27;
  --ocean-2: #0d2137;
  --ocean-3: #0a3d62;
  --ocean-4: #1565c0;
  --ocean-5: #1e88e5;
  --ocean-6: #29b6f6;
  --ocean-7: #4fc3f7;
  --ocean-glow: #00d4ff;
  --glass: rgba(255,255,255,0.06);
  --glass-border: rgba(79,195,247,0.25);
  --text-primary: #e8f4fd;
  --text-muted: rgba(232,244,253,0.55);
  --error-color: #ff6b8a;
  --success-color: #4fc3f7;
  --input-bg: rgba(255,255,255,0.05);
  --input-border: rgba(79,195,247,0.2);
  --input-focus: rgba(41,182,246,0.5);
}

html { scroll-behavior: smooth; }

body {
  min-height: 100vh;
  font-family: 'Raleway', sans-serif;
  background: var(--ocean-1);
  display: flex;
  align-items: center;
  justify-content: center;
  overflow-x: hidden;
  position: relative;
  padding: 40px 20px;
}

/* Animated ocean background */
body::before {
  content: '';
  position: fixed;
  inset: 0;
  background:
    radial-gradient(ellipse 80% 60% at 20% 10%, rgba(21,101,192,0.4) 0%, transparent 60%),
    radial-gradient(ellipse 60% 80% at 80% 90%, rgba(13,33,55,0.8) 0%, transparent 60%),
    radial-gradient(ellipse 100% 50% at 50% 50%, rgba(10,61,98,0.3) 0%, transparent 70%),
    linear-gradient(160deg, #0a0e27 0%, #0d2137 40%, #0a3d62 75%, #1565c0 100%);
  z-index: 0;
  animation: oceanPulse 8s ease-in-out infinite alternate;
}

@keyframes oceanPulse {
  0%  { filter: brightness(1); }
  100%{ filter: brightness(1.15) hue-rotate(5deg); }
}

/* Floating particles */
.particles { position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden; }
.particle {
  position: absolute;
  border-radius: 50%;
  background: radial-gradient(circle, var(--ocean-glow), transparent);
  animation: floatUp linear infinite;
  opacity: 0;
}
@keyframes floatUp {
  0%   { transform: translateY(100vh) scale(0); opacity: 0; }
  10%  { opacity: 0.6; }
  90%  { opacity: 0.2; }
  100% { transform: translateY(-10vh) scale(1.5); opacity: 0; }
}

/* Wave lines */
.wave-lines { position: fixed; inset: 0; z-index: 0; pointer-events: none; }
.wave-lines::before, .wave-lines::after {
  content: '';
  position: absolute;
  width: 200%;
  height: 2px;
  background: linear-gradient(90deg, transparent, rgba(79,195,247,0.15), transparent);
  animation: waveSweep 6s ease-in-out infinite;
}
.wave-lines::before { top: 30%; animation-delay: 0s; }
.wave-lines::after  { top: 70%; animation-delay: -3s; }
@keyframes waveSweep {
  0%   { transform: translateX(-50%) skewX(-5deg); }
  100% { transform: translateX(0%)  skewX(5deg); }
}

.card-wrapper {
  position: relative;
  z-index: 10;
  width: 100%;
  max-width: 560px;
  animation: riseIn 0.8s cubic-bezier(0.16,1,0.3,1) both;
}
@keyframes riseIn {
  from { opacity: 0; transform: translateY(40px) scale(0.96); }
  to   { opacity: 1; transform: translateY(0)    scale(1); }
}

.card {
  background: linear-gradient(135deg, rgba(255,255,255,0.08) 0%, rgba(255,255,255,0.03) 100%);
  border: 1px solid var(--glass-border);
  border-radius: 24px;
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  padding: 44px 48px 40px;
  box-shadow:
    0 8px 32px rgba(0,0,0,0.4),
    0 0 0 1px rgba(79,195,247,0.08),
    inset 0 1px 0 rgba(255,255,255,0.1);
  position: relative;
  overflow: hidden;
}

.card::before {
  content: '';
  position: absolute;
  top: -50%;
  left: -50%;
  width: 200%;
  height: 200%;
  background: conic-gradient(from 0deg at 50% 50%, transparent 0deg, rgba(79,195,247,0.05) 60deg, transparent 120deg);
  animation: rotate 12s linear infinite;
  pointer-events: none;
}
@keyframes rotate { to { transform: rotate(360deg); } }

/* Logo */
.logo {
  text-align: center;
  margin-bottom: 32px;
}
.logo-icon {
  width: 64px;
  height: 64px;
  margin: 0 auto 14px;
  background: linear-gradient(135deg, var(--ocean-4), var(--ocean-6));
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 0 30px rgba(41,182,246,0.4), 0 4px 16px rgba(0,0,0,0.3);
  position: relative;
}
.logo-icon svg { width: 32px; height: 32px; fill: white; }
.logo h1 {
  font-family: 'Cinzel', serif;
  font-size: 1.6rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  background: linear-gradient(135deg, #e8f4fd, var(--ocean-7));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}
.logo p { color: var(--text-muted); font-size: 0.78rem; letter-spacing: 0.15em; text-transform: uppercase; margin-top: 4px; }

.section-title {
  font-family: 'Cinzel', serif;
  font-size: 1.05rem;
  color: var(--ocean-7);
  letter-spacing: 0.08em;
  margin: 28px 0 16px;
  padding-bottom: 8px;
  border-bottom: 1px solid rgba(79,195,247,0.15);
  display: flex;
  align-items: center;
  gap: 8px;
}
.section-title::before {
  content: '';
  display: inline-block;
  width: 4px;
  height: 16px;
  background: linear-gradient(to bottom, var(--ocean-6), var(--ocean-4));
  border-radius: 2px;
}

.name-row { display: grid; grid-template-columns: 1fr 0.8fr 1fr; gap: 12px; }
.row-2   { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

.field { position: relative; margin-bottom: 18px; }
.field:last-child { margin-bottom: 0; }

label {
  display: block;
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--text-muted);
  margin-bottom: 7px;
  transition: color 0.2s;
}
.field:focus-within label { color: var(--ocean-6); }

.optional-badge {
  font-size: 0.6rem;
  background: rgba(79,195,247,0.12);
  color: var(--ocean-6);
  padding: 1px 6px;
  border-radius: 4px;
  margin-left: 5px;
  text-transform: uppercase;
  vertical-align: middle;
  letter-spacing: 0.08em;
}

input[type="text"],
input[type="email"],
input[type="tel"],
input[type="password"] {
  width: 100%;
  padding: 13px 16px;
  background: var(--input-bg);
  border: 1px solid var(--input-border);
  border-radius: 12px;
  color: var(--text-primary);
  font-family: 'Raleway', sans-serif;
  font-size: 0.9rem;
  font-weight: 500;
  outline: none;
  transition: all 0.3s ease;
  position: relative;
}
input::placeholder { color: rgba(232,244,253,0.25); }
input:focus {
  border-color: var(--ocean-5);
  background: rgba(255,255,255,0.08);
  box-shadow: 0 0 0 3px rgba(30,136,229,0.18), 0 0 20px rgba(41,182,246,0.1);
}
input.error-input { border-color: var(--error-color); box-shadow: 0 0 0 3px rgba(255,107,138,0.12); }

.error-msg {
  color: var(--error-color);
  font-size: 0.72rem;
  margin-top: 5px;
  display: flex;
  align-items: center;
  gap: 4px;
  animation: fadeIn 0.3s ease;
}
.error-msg::before { content: '⚠'; font-size: 0.7rem; }
@keyframes fadeIn { from { opacity:0; transform: translateY(-4px); } to { opacity:1; transform: translateY(0); } }

.pw-wrapper { position: relative; }
.pw-wrapper input { padding-right: 44px; }
.pw-toggle {
  position: absolute;
  right: 14px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  cursor: pointer;
  color: var(--text-muted);
  transition: color 0.2s;
  padding: 4px;
  display: flex;
  align-items: center;
}
.pw-toggle:hover { color: var(--ocean-6); }

.pw-strength {
  margin-top: 8px;
  height: 3px;
  background: rgba(255,255,255,0.08);
  border-radius: 2px;
  overflow: hidden;
}
.pw-strength-bar {
  height: 100%;
  border-radius: 2px;
  transition: width 0.4s, background 0.4s;
  width: 0%;
}

.btn-submit {
  width: 100%;
  padding: 15px;
  background: linear-gradient(135deg, var(--ocean-4) 0%, var(--ocean-5) 50%, var(--ocean-6) 100%);
  color: #fff;
  border: none;
  border-radius: 14px;
  font-family: 'Cinzel', serif;
  font-size: 0.95rem;
  font-weight: 600;
  letter-spacing: 0.12em;
  cursor: pointer;
  position: relative;
  overflow: hidden;
  transition: all 0.3s ease;
  margin-top: 28px;
  box-shadow: 0 4px 20px rgba(30,136,229,0.35);
}
.btn-submit::before {
  content: '';
  position: absolute;
  top: 0; left: -100%;
  width: 100%; height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,0.15), transparent);
  transition: left 0.5s;
}
.btn-submit:hover::before { left: 100%; }
.btn-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(30,136,229,0.5); }
.btn-submit:active { transform: translateY(0); }

.login-link {
  text-align: center;
  margin-top: 22px;
  font-size: 0.82rem;
  color: var(--text-muted);
}
.login-link a {
  color: var(--ocean-6);
  text-decoration: none;
  font-weight: 600;
  transition: color 0.2s;
}
.login-link a:hover { color: var(--ocean-7); text-decoration: underline; }

/* Responsive */
@media (max-width: 520px) {
  .card { padding: 32px 24px 28px; }
  .name-row { grid-template-columns: 1fr; gap: 0; }
  .row-2 { grid-template-columns: 1fr; gap: 0; }
}
</style>
</head>
<body>

<div class="particles" id="particles"></div>
<div class="wave-lines"></div>

<div class="card-wrapper">
  <div class="card">
    <div class="logo">
      <div class="logo-icon">
        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
          <path d="M3 17c1.5-1.5 3-2 4.5-2s3 1 4.5 1 3-.5 4.5-2M3 12c1.5-1.5 3-2 4.5-2s3 1 4.5 1 3-.5 4.5-2M3 7c1.5-1.5 3-2 4.5-2s3 1 4.5 1 3-.5 4.5-2"/>
        </svg>
      </div>
      <h1>Darken Shadows</h1>
      <p>Swimming &amp; Aquatics Club</p>
    </div>

    <form method="POST" action="signup.php" novalidate>

      <div class="section-title">Personal Information</div>

      <div class="name-row">
        <div class="field">
          <label for="first_name">First Name</label>
          <input type="text" id="first_name" name="first_name"
                 placeholder="First"
                 value="<?= htmlspecialchars($old['first_name'] ?? '') ?>"
                 class="<?= isset($errors['first_name']) ? 'error-input' : '' ?>"
                 autocomplete="given-name">
          <?php if (isset($errors['first_name'])): ?>
            <div class="error-msg"><?= $errors['first_name'] ?></div>
          <?php endif; ?>
        </div>

        <div class="field">
          <label for="middle_name">Middle <span class="optional-badge">opt</span></label>
          <input type="text" id="middle_name" name="middle_name"
                 placeholder="Middle"
                 value="<?= htmlspecialchars($old['middle_name'] ?? '') ?>"
                 class="<?= isset($errors['middle_name']) ? 'error-input' : '' ?>"
                 autocomplete="additional-name">
          <?php if (isset($errors['middle_name'])): ?>
            <div class="error-msg"><?= $errors['middle_name'] ?></div>
          <?php endif; ?>
        </div>

        <div class="field">
          <label for="last_name">Last Name</label>
          <input type="text" id="last_name" name="last_name"
                 placeholder="Last"
                 value="<?= htmlspecialchars($old['last_name'] ?? '') ?>"
                 class="<?= isset($errors['last_name']) ? 'error-input' : '' ?>"
                 autocomplete="family-name">
          <?php if (isset($errors['last_name'])): ?>
            <div class="error-msg"><?= $errors['last_name'] ?></div>
          <?php endif; ?>
        </div>
      </div>

      <div class="section-title">Contact Details</div>

      <div class="row-2">
        <div class="field">
          <label for="phone">Phone Number</label>
          <input type="tel" id="phone" name="phone"
                 placeholder="+91 98765 43210"
                 value="<?= htmlspecialchars($old['phone'] ?? '') ?>"
                 class="<?= isset($errors['phone']) ? 'error-input' : '' ?>"
                 autocomplete="tel">
          <?php if (isset($errors['phone'])): ?>
            <div class="error-msg"><?= $errors['phone'] ?></div>
          <?php endif; ?>
        </div>

        <div class="field">
          <label for="email">Email <span class="optional-badge">opt</span></label>
          <input type="email" id="email" name="email"
                 placeholder="you@example.com"
                 value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                 class="<?= isset($errors['email']) ? 'error-input' : '' ?>"
                 autocomplete="email">
          <?php if (isset($errors['email'])): ?>
            <div class="error-msg"><?= $errors['email'] ?></div>
          <?php endif; ?>
        </div>
      </div>

      <div class="section-title">Security</div>

      <div class="field">
        <label for="password">Password</label>
        <div class="pw-wrapper">
          <input type="password" id="password" name="password"
                 placeholder="Min 8 chars, 1 uppercase, 1 number"
                 class="<?= isset($errors['password']) ? 'error-input' : '' ?>"
                 autocomplete="new-password"
                 oninput="checkStrength(this.value)">
          <button type="button" class="pw-toggle" onclick="togglePw('password', this)" aria-label="Toggle password">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
            </svg>
          </button>
        </div>
        <div class="pw-strength"><div class="pw-strength-bar" id="strength-bar"></div></div>
        <?php if (isset($errors['password'])): ?>
          <div class="error-msg"><?= $errors['password'] ?></div>
        <?php endif; ?>
      </div>

      <div class="field">
        <label for="confirm_password">Confirm Password</label>
        <div class="pw-wrapper">
          <input type="password" id="confirm_password" name="confirm_password"
                 placeholder="Re-enter password"
                 class="<?= isset($errors['confirm_password']) ? 'error-input' : '' ?>"
                 autocomplete="new-password">
          <button type="button" class="pw-toggle" onclick="togglePw('confirm_password', this)" aria-label="Toggle confirm password">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
            </svg>
          </button>
        </div>
        <?php if (isset($errors['confirm_password'])): ?>
          <div class="error-msg"><?= $errors['confirm_password'] ?></div>
        <?php endif; ?>
      </div>

      <button type="submit" class="btn-submit">Create Account</button>

    </form>

    <div class="login-link">
      Already have an account? <a href="login.php">Sign In</a>
    </div>
  </div>
</div>

<script>
// Particles
(function() {
  const container = document.getElementById('particles');
  for (let i = 0; i < 18; i++) {
    const p = document.createElement('div');
    p.className = 'particle';
    const size = Math.random() * 6 + 2;
    p.style.cssText = `
      width:${size}px; height:${size}px;
      left:${Math.random()*100}%;
      animation-duration:${Math.random()*12+8}s;
      animation-delay:${Math.random()*10}s;
      opacity:0;
    `;
    container.appendChild(p);
  }
})();

function togglePw(id, btn) {
  const input = document.getElementById(id);
  input.type = input.type === 'password' ? 'text' : 'password';
  const eye = btn.querySelector('svg');
  eye.style.opacity = input.type === 'text' ? '1' : '0.5';
}

function checkStrength(val) {
  const bar = document.getElementById('strength-bar');
  let score = 0;
  if (val.length >= 8) score++;
  if (/[A-Z]/.test(val)) score++;
  if (/[0-9]/.test(val)) score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;
  const colors = ['#ff6b8a','#ffab76','#ffd166','#4fc3f7'];
  const widths = ['25%','50%','75%','100%'];
  bar.style.width  = score ? widths[score-1] : '0%';
  bar.style.background = score ? colors[score-1] : 'transparent';
}
</script>
</body>
</html>