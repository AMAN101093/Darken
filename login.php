<?php
// login.php
session_start();

// Already logged in?
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'config/db.php';

$errors  = [];
$success = '';

// Pick up success flash from signup
if (isset($_SESSION['signup_success'])) {
    $success = $_SESSION['signup_success'];
    unset($_SESSION['signup_success']);
}

// Auto-login via remember-me cookie
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    $stmt = $pdo->prepare(
        "SELECT u.id, u.first_name, u.last_name
         FROM users u
         JOIN remember_tokens rt ON u.id = rt.user_id
         WHERE rt.token = ? AND rt.expires_at > NOW() AND u.is_active = 1"
    );
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if ($user) {
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        header('Location: index.php');
        exit;
    }
    // Invalid token — clear cookie
    setcookie('remember_token', $token, [
    'expires' => time() + 60*60*24*30,
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier  = trim($_POST['identifier'] ?? '');
    $password    = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']);

    if (empty($identifier)) $errors['identifier'] = 'Please enter your phone or email.';
    if (empty($password))   $errors['password']   = 'Please enter your password.';

    if (empty($errors)) {
        // Determine login type
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $stmt = $pdo->prepare("SELECT id, first_name, last_name, password_hash FROM users WHERE email = ? AND is_active = 1");
        } else {
            $stmt = $pdo->prepare("SELECT id, first_name, last_name, password_hash FROM users WHERE phone = ? AND is_active = 1");
        }
        $stmt->execute([$identifier]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $errors['general'] = 'Invalid credentials. Please try again.';
        } else {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];

            // Remember Me — store token in DB + cookie (30 days)
            if ($remember_me) {
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
                $stmt2 = $pdo->prepare("INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
                $stmt2->execute([$user['id'], $token, $expires]);
                setcookie('remember_token', $token, time() + 60*60*24*30, '/', '', true, true);
            }

            header('Location: index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Sign In — Darken Shadows</title>
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
}

html { scroll-behavior: smooth; }

body {
  min-height: 100vh;
  font-family: 'Raleway', sans-serif;
  background: var(--ocean-1);
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  position: relative;
}

body::before {
  content: '';
  position: fixed; inset: 0;
  background:
    radial-gradient(ellipse 70% 60% at 15% 20%, rgba(21,101,192,0.45) 0%, transparent 60%),
    radial-gradient(ellipse 50% 70% at 85% 80%, rgba(10,61,98,0.5) 0%, transparent 55%),
    radial-gradient(ellipse 80% 40% at 50% 50%, rgba(13,33,55,0.6) 0%, transparent 70%),
    linear-gradient(160deg, #0a0e27 0%, #0d2137 35%, #0a3d62 70%, #1565c0 100%);
  z-index: 0;
  animation: pulse 10s ease-in-out infinite alternate;
}
@keyframes pulse {
  0%  { filter: brightness(1) saturate(1); }
  100%{ filter: brightness(1.12) saturate(1.1) hue-rotate(4deg); }
}

.particles { position: fixed; inset: 0; z-index: 0; pointer-events: none; }
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
  90%  { opacity: 0.15; }
  100% { transform: translateY(-10vh) scale(1.4); opacity: 0; }
}

/* Big decorative orb */
.orb {
  position: fixed;
  width: 500px; height: 500px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(30,136,229,0.12) 0%, transparent 70%);
  top: -100px; right: -100px;
  z-index: 0;
  animation: orbFloat 15s ease-in-out infinite alternate;
}
@keyframes orbFloat {
  0%   { transform: translate(0,0) scale(1); }
  100% { transform: translate(-40px, 40px) scale(1.05); }
}

.card-wrapper {
  position: relative;
  z-index: 10;
  width: 100%;
  max-width: 440px;
  padding: 20px;
  animation: riseIn 0.8s cubic-bezier(0.16,1,0.3,1) both;
}
@keyframes riseIn {
  from { opacity:0; transform: translateY(40px) scale(0.96); }
  to   { opacity:1; transform: translateY(0)    scale(1); }
}

.card {
  background: linear-gradient(135deg, rgba(255,255,255,0.09) 0%, rgba(255,255,255,0.03) 100%);
  border: 1px solid var(--glass-border);
  border-radius: 28px;
  backdrop-filter: blur(24px);
  -webkit-backdrop-filter: blur(24px);
  padding: 52px 48px 44px;
  box-shadow:
    0 20px 60px rgba(0,0,0,0.5),
    0 0 0 1px rgba(79,195,247,0.06),
    inset 0 1px 0 rgba(255,255,255,0.1);
  position: relative;
  overflow: hidden;
}

/* Animated corner accent */
.card::after {
  content: '';
  position: absolute;
  top: -2px; right: -2px;
  width: 120px; height: 120px;
  background: conic-gradient(from 90deg at 100% 0%, var(--ocean-5), transparent 60%);
  opacity: 0.3;
  border-radius: 0 28px 0 0;
}

.logo {
  text-align: center;
  margin-bottom: 40px;
}

.logo-icon {
  width: 72px; height: 72px;
  margin: 0 auto 16px;
  background: linear-gradient(135deg, var(--ocean-3) 0%, var(--ocean-4) 50%, var(--ocean-5) 100%);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow:
    0 0 0 8px rgba(30,136,229,0.1),
    0 0 40px rgba(41,182,246,0.35),
    0 8px 24px rgba(0,0,0,0.4);
  position: relative;
  animation: iconPulse 3s ease-in-out infinite;
}
@keyframes iconPulse {
  0%, 100% { box-shadow: 0 0 0 8px rgba(30,136,229,0.1), 0 0 40px rgba(41,182,246,0.35), 0 8px 24px rgba(0,0,0,0.4); }
  50%       { box-shadow: 0 0 0 12px rgba(30,136,229,0.06), 0 0 60px rgba(41,182,246,0.5), 0 8px 24px rgba(0,0,0,0.4); }
}
.logo-icon svg { width: 36px; height: 36px; stroke: white; fill: none; stroke-width: 2; stroke-linecap: round; }

.logo h1 {
  font-family: 'Cinzel', serif;
  font-size: 1.75rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  background: linear-gradient(135deg, #e8f4fd 30%, var(--ocean-7) 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  line-height: 1.2;
}
.logo .tagline {
  font-size: 0.72rem;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--text-muted);
  margin-top: 6px;
}

.form-heading {
  font-family: 'Cinzel', serif;
  font-size: 0.85rem;
  color: var(--ocean-6);
  text-align: center;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  margin-bottom: 28px;
  position: relative;
}
.form-heading::before,
.form-heading::after {
  content: '';
  position: absolute;
  top: 50%;
  width: 25%;
  height: 1px;
  background: linear-gradient(90deg, transparent, rgba(79,195,247,0.4));
}
.form-heading::before { left: 0; }
.form-heading::after  { right: 0; transform: scaleX(-1); }

.success-banner {
  background: rgba(79,195,247,0.1);
  border: 1px solid rgba(79,195,247,0.3);
  border-radius: 10px;
  padding: 12px 16px;
  margin-bottom: 22px;
  color: var(--ocean-7);
  font-size: 0.82rem;
  text-align: center;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  animation: fadeIn 0.4s ease;
}
.success-banner::before { content: '✓'; font-weight: 700; }

.alert-error {
  background: rgba(255,107,138,0.1);
  border: 1px solid rgba(255,107,138,0.3);
  border-radius: 10px;
  padding: 12px 16px;
  margin-bottom: 22px;
  color: #ff6b8a;
  font-size: 0.82rem;
  text-align: center;
  animation: shake 0.4s ease;
}
@keyframes shake {
  0%,100%{ transform: translateX(0); }
  25%    { transform: translateX(-6px); }
  75%    { transform: translateX(6px); }
}
@keyframes fadeIn { from { opacity:0; transform:translateY(-6px); } to { opacity:1; transform:translateY(0); } }

.field { margin-bottom: 20px; }

label {
  display: block;
  font-size: 0.73rem;
  font-weight: 600;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--text-muted);
  margin-bottom: 8px;
  transition: color 0.2s;
}
.field:focus-within label { color: var(--ocean-6); }

.input-wrap {
  position: relative;
  display: flex;
  align-items: center;
}
.input-wrap .icon {
  position: absolute;
  left: 14px;
  color: var(--text-muted);
  display: flex;
  align-items: center;
  pointer-events: none;
  transition: color 0.2s;
}
.field:focus-within .icon { color: var(--ocean-6); }

.input-wrap input {
  width: 100%;
  padding: 14px 16px 14px 44px;
  background: var(--input-bg);
  border: 1px solid var(--input-border);
  border-radius: 14px;
  color: var(--text-primary);
  font-family: 'Raleway', sans-serif;
  font-size: 0.9rem;
  font-weight: 500;
  outline: none;
  transition: all 0.3s;
}
.input-wrap input::placeholder { color: rgba(232,244,253,0.22); }
.input-wrap input:focus {
  border-color: var(--ocean-5);
  background: rgba(255,255,255,0.08);
  box-shadow: 0 0 0 3px rgba(30,136,229,0.18), 0 0 24px rgba(41,182,246,0.08);
}
.input-wrap input.error-input { border-color: var(--error-color); }

.pw-toggle {
  position: absolute;
  right: 14px;
  background: none;
  border: none;
  cursor: pointer;
  color: var(--text-muted);
  display: flex; align-items: center;
  transition: color 0.2s; padding: 4px;
}
.pw-toggle:hover { color: var(--ocean-6); }

.error-msg {
  color: var(--error-color);
  font-size: 0.72rem;
  margin-top: 6px;
  display: flex;
  align-items: center;
  gap: 4px;
}
.error-msg::before { content: '⚠'; }

/* Remember Me */
.remember-row {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 26px;
  user-select: none;
  cursor: pointer;
}
.remember-row input[type="checkbox"] { display: none; }
.custom-check {
  width: 20px; height: 20px;
  border: 1px solid var(--input-border);
  border-radius: 6px;
  background: var(--input-bg);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  transition: all 0.2s;
}
.remember-row input[type="checkbox"]:checked + .custom-check {
  background: linear-gradient(135deg, var(--ocean-4), var(--ocean-5));
  border-color: var(--ocean-5);
  box-shadow: 0 0 12px rgba(30,136,229,0.3);
}
.custom-check svg { display: none; stroke: white; stroke-width: 3; }
.remember-row input[type="checkbox"]:checked + .custom-check svg { display: block; }
.remember-label {
  font-size: 0.82rem;
  color: var(--text-muted);
  transition: color 0.2s;
}
.remember-row:hover .remember-label { color: var(--text-primary); }

.btn-login {
  width: 100%;
  padding: 16px;
  background: linear-gradient(135deg, var(--ocean-4) 0%, var(--ocean-5) 50%, var(--ocean-6) 100%);
  color: #fff;
  border: none;
  border-radius: 14px;
  font-family: 'Cinzel', serif;
  font-size: 0.95rem;
  font-weight: 600;
  letter-spacing: 0.14em;
  cursor: pointer;
  position: relative;
  overflow: hidden;
  transition: all 0.3s;
  box-shadow: 0 4px 24px rgba(30,136,229,0.4);
}
.btn-login::before {
  content: '';
  position: absolute;
  top: 0; left: -100%;
  width: 100%; height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,0.18), transparent);
  transition: left 0.5s;
}
.btn-login:hover::before { left: 100%; }
.btn-login:hover { transform: translateY(-2px); box-shadow: 0 8px 36px rgba(30,136,229,0.55); }
.btn-login:active { transform: translateY(0); }

.divider {
  display: flex; align-items: center; gap: 12px;
  margin: 24px 0 20px;
}
.divider::before, .divider::after {
  content: ''; flex: 1;
  height: 1px;
  background: linear-gradient(90deg, transparent, rgba(79,195,247,0.2), transparent);
}
.divider span { font-size: 0.7rem; color: var(--text-muted); letter-spacing: 0.1em; text-transform: uppercase; }

.signup-link {
  text-align: center;
  font-size: 0.83rem;
  color: var(--text-muted);
}
.signup-link a {
  color: var(--ocean-6);
  text-decoration: none;
  font-weight: 600;
  transition: color 0.2s;
}
.signup-link a:hover { color: var(--ocean-7); }

/* Bottom water reflection */
.reflection {
  position: absolute;
  bottom: -1px; left: 0; right: 0;
  height: 4px;
  background: linear-gradient(90deg, transparent, var(--ocean-6), var(--ocean-7), var(--ocean-6), transparent);
  border-radius: 0 0 28px 28px;
  opacity: 0.6;
  animation: shimmer 3s ease-in-out infinite;
}
@keyframes shimmer {
  0%,100% { opacity: 0.4; }
  50%     { opacity: 0.9; }
}
</style>
</head>
<body>

<div class="particles" id="particles"></div>
<div class="orb"></div>

<div class="card-wrapper">
  <div class="card">
    <div class="reflection"></div>

    <div class="logo">
      <div class="logo-icon">
        <svg viewBox="0 0 24 24">
          <path d="M2 17c2-2 4-2.5 6-2s3.5 1.5 5.5 1.5S17 15.5 22 13"/>
          <path d="M2 12c2-2 4-2.5 6-2s3.5 1.5 5.5 1.5S17 10.5 22 8"/>
          <path d="M2 7c2-2 4-2.5 6-2s3.5 1.5 5.5 1.5S17 5.5 22 3"/>
        </svg>
      </div>
      <h1>Darken Shadows</h1>
      <div class="tagline">Swimming &amp; Aquatics</div>
    </div>

    <div class="form-heading">Welcome Back</div>

    <?php if ($success): ?>
      <div class="success-banner"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if (isset($errors['general'])): ?>
      <div class="alert-error"><?= htmlspecialchars($errors['general']) ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php" novalidate>

      <div class="field">
        <label for="identifier">Phone or Email</label>
        <div class="input-wrap">
          <span class="icon">
            <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
            </svg>
          </span>
          <input type="text" id="identifier" name="identifier"
                 placeholder="e.g. 9876543210 or you@mail.com"
                 value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>"
                 class="<?= isset($errors['identifier']) ? 'error-input' : '' ?>"
                 autocomplete="username">
        </div>
        <?php if (isset($errors['identifier'])): ?>
          <div class="error-msg"><?= $errors['identifier'] ?></div>
        <?php endif; ?>
      </div>

      <div class="field">
        <label for="password">Password</label>
        <div class="input-wrap">
          <span class="icon">
            <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
          </span>
          <input type="password" id="password" name="password"
                 placeholder="Your password"
                 class="<?= isset($errors['password']) ? 'error-input' : '' ?>"
                 autocomplete="current-password">
          <button type="button" class="pw-toggle" onclick="togglePw()" aria-label="Show/hide password">
            <svg id="eye-icon" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
            </svg>
          </button>
        </div>
        <?php if (isset($errors['password'])): ?>
          <div class="error-msg"><?= $errors['password'] ?></div>
        <?php endif; ?>
      </div>

      <label class="remember-row" for="remember_me">
        <input type="checkbox" id="remember_me" name="remember_me" <?= isset($_POST['remember_me']) ? 'checked' : '' ?>>
        <div class="custom-check">
          <svg width="12" height="12" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <span class="remember-label">Remember me for 30 days</span>
      </label>

      <button type="submit" class="btn-login">Sign In</button>

    </form>

    <div class="divider"><span>or</span></div>

    <div class="signup-link">
      New here? <a href="signup.php">Create an account</a>
    </div>
  </div>
</div>

<script>
(function() {
  const c = document.getElementById('particles');
  for (let i = 0; i < 16; i++) {
    const p = document.createElement('div');
    p.className = 'particle';
    const s = Math.random() * 5 + 2;
    p.style.cssText = `width:${s}px;height:${s}px;left:${Math.random()*100}%;animation-duration:${Math.random()*12+8}s;animation-delay:${Math.random()*10}s;opacity:0;`;
    c.appendChild(p);
  }
})();

let visible = false;
function togglePw() {
  visible = !visible;
  document.getElementById('password').type = visible ? 'text' : 'password';
  document.getElementById('eye-icon').style.opacity = visible ? '1' : '0.5';
}
</script>
</body>
</html>