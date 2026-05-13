<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$user_name = $_SESSION['user_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Programs — Darken Shadows Swimming Club</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700;900&family=Raleway:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --ocean-1: #e8f4f8;
            --ocean-2: #b8dce8;
            --ocean-3: #6aaec8;
            --ocean-4: #2a7fa8;
            --ocean-5: #1a5f7a;
            --ocean-6: #0e3f55;
            --ocean-7: #071e2b;
            --accent: #4fc3f7;
            --accent-gold: #f0c040;
            --text-light: #e8f4f8;
            --text-muted: #8bb8cc;
            --card-bg: rgba(14, 63, 85, 0.55);
            --card-border: rgba(74, 195, 247, 0.18);
            --card-hover-border: rgba(74, 195, 247, 0.55);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Raleway', sans-serif;
            background: var(--ocean-7);
            color: var(--text-light);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* ── Background ── */
        .bg-layer {
            position: fixed; inset: 0; z-index: 0;
            background:
                radial-gradient(ellipse 80% 60% at 20% 10%, rgba(42,127,168,0.22) 0%, transparent 60%),
                radial-gradient(ellipse 60% 80% at 80% 80%, rgba(26,95,122,0.28) 0%, transparent 60%),
                linear-gradient(160deg, #071e2b 0%, #0d3347 40%, #071e2b 100%);
        }
        .bg-layer::after {
            content: '';
            position: absolute; inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%234fc3f7' fill-opacity='0.03'%3E%3Ccircle cx='30' cy='30' r='1'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }

        /* ── Nav ── */
        nav {
            position: relative; z-index: 10;
            display: flex; align-items: center; justify-content: space-between;
            padding: 1.2rem 4rem;
            border-bottom: 1px solid rgba(74,195,247,0.12);
            background: rgba(7,30,43,0.85);
            backdrop-filter: blur(12px);
        }
        .nav-logo {
            font-family: 'Cinzel', serif;
            font-size: 1.15rem; font-weight: 700;
            color: var(--accent);
            letter-spacing: 0.08em;
            text-decoration: none;
        }
        .nav-links { display: flex; gap: 2rem; align-items: center; }
        .nav-links a {
            font-family: 'Raleway', sans-serif;
            font-size: 0.82rem; font-weight: 600;
            letter-spacing: 0.12em; text-transform: uppercase;
            color: var(--text-muted); text-decoration: none;
            transition: color 0.25s;
        }
        .nav-links a:hover, .nav-links a.active { color: var(--accent); }
        .nav-user {
            font-size: 0.82rem; font-weight: 500;
            color: var(--text-muted);
        }
        .nav-user span { color: var(--accent); font-weight: 600; }

        /* ── Hero Banner ── */
        .hero {
            position: relative; z-index: 1;
            text-align: center;
            padding: 5rem 2rem 3.5rem;
        }
        .hero-eyebrow {
            font-family: 'Raleway', sans-serif;
            font-size: 0.72rem; font-weight: 700;
            letter-spacing: 0.3em; text-transform: uppercase;
            color: var(--accent);
            margin-bottom: 1rem;
            opacity: 0; animation: fadeUp 0.6s 0.1s forwards;
        }
        .hero h1 {
            font-family: 'Cinzel', serif;
            font-size: clamp(2.2rem, 5vw, 3.8rem);
            font-weight: 900; line-height: 1.1;
            color: var(--text-light);
            margin-bottom: 1.2rem;
            opacity: 0; animation: fadeUp 0.6s 0.25s forwards;
        }
        .hero h1 em { color: var(--accent); font-style: normal; }
        .hero-sub {
            font-size: 1rem; font-weight: 400;
            color: var(--text-muted); max-width: 540px; margin: 0 auto 2.5rem;
            line-height: 1.7;
            opacity: 0; animation: fadeUp 0.6s 0.4s forwards;
        }
        .hero-divider {
            width: 60px; height: 2px;
            background: linear-gradient(90deg, transparent, var(--accent), transparent);
            margin: 0 auto;
            opacity: 0; animation: fadeUp 0.6s 0.5s forwards;
        }

        /* ── Grid ── */
        .programs-grid {
            position: relative; z-index: 1;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            max-width: 1200px; margin: 0 auto;
            padding: 3rem 2rem 6rem;
        }

        /* ── Card ── */
        .program-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 2rem 1.8rem 1.8rem;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: flex; flex-direction: column;
            backdrop-filter: blur(10px);
            transition: border-color 0.3s, transform 0.3s, box-shadow 0.3s;
            opacity: 0;
            animation: fadeUp 0.55s forwards;
            position: relative; overflow: hidden;
        }
        .program-card::before {
            content: '';
            position: absolute; inset: 0;
            background: linear-gradient(135deg, rgba(74,195,247,0.05) 0%, transparent 60%);
            opacity: 0; transition: opacity 0.3s;
        }
        .program-card:hover::before { opacity: 1; }
        .program-card:hover {
            border-color: var(--card-hover-border);
            transform: translateY(-6px);
            box-shadow: 0 20px 50px rgba(0,0,0,0.35), 0 0 0 1px rgba(74,195,247,0.1);
        }

        /* stagger delays */
        .program-card:nth-child(1) { animation-delay: 0.15s; }
        .program-card:nth-child(2) { animation-delay: 0.25s; }
        .program-card:nth-child(3) { animation-delay: 0.35s; }
        .program-card:nth-child(4) { animation-delay: 0.45s; }
        .program-card:nth-child(5) { animation-delay: 0.55s; }
        .program-card:nth-child(6) { animation-delay: 0.65s; }

        .card-icon-wrap {
            width: 58px; height: 58px;
            border-radius: 50%;
            background: rgba(42,127,168,0.25);
            border: 1px solid rgba(74,195,247,0.2);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.55rem;
            margin-bottom: 1.4rem;
            transition: background 0.3s, border-color 0.3s;
        }
        .program-card:hover .card-icon-wrap {
            background: rgba(74,195,247,0.18);
            border-color: rgba(74,195,247,0.5);
        }

        .card-tag {
            font-size: 0.65rem; font-weight: 700;
            letter-spacing: 0.2em; text-transform: uppercase;
            color: var(--accent);
            margin-bottom: 0.5rem;
        }

        .card-title {
            font-family: 'Cinzel', serif;
            font-size: 1.12rem; font-weight: 700;
            color: var(--text-light);
            margin-bottom: 0.9rem;
            line-height: 1.3;
        }

        .card-desc {
            font-size: 0.88rem; font-weight: 400;
            color: var(--text-muted);
            line-height: 1.7;
            flex: 1;
            margin-bottom: 1.5rem;
        }

        .card-meta {
            display: flex; flex-wrap: wrap; gap: 0.5rem;
            margin-bottom: 1.4rem;
        }
        .meta-pill {
            font-size: 0.68rem; font-weight: 600;
            letter-spacing: 0.08em;
            padding: 0.28rem 0.75rem;
            border-radius: 20px;
            background: rgba(74,195,247,0.1);
            border: 1px solid rgba(74,195,247,0.2);
            color: var(--accent);
        }

        .card-cta {
            display: flex; align-items: center; gap: 0.5rem;
            font-size: 0.75rem; font-weight: 700;
            letter-spacing: 0.15em; text-transform: uppercase;
            color: var(--accent);
            transition: gap 0.25s;
        }
        .program-card:hover .card-cta { gap: 0.85rem; }
        .card-cta .arrow { font-size: 0.9rem; }

        /* ── Animations ── */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(22px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── Responsive ── */
        @media (max-width: 900px) {
            .programs-grid { grid-template-columns: repeat(2, 1fr); }
            nav { padding: 1rem 1.5rem; }
        }
        @media (max-width: 580px) {
            .programs-grid { grid-template-columns: 1fr; padding: 2rem 1rem 4rem; }
            .nav-links { display: none; }
        }
    </style>
</head>
<body>

<div class="bg-layer"></div>

<nav>
    <a class="nav-logo" href="index.php">⬡ Darken Shadows</a>
    <div class="nav-links">
        <a href="program.php" class="active">Programs</a>
        <a href="logout.php">Logout</a>
    </div>
        <span class="nav-greeting">Welcome, <a href="profile.php" style="text-decoration: none; color: inherit;"><strong><?= $user_name ?></strong></a></span>
</nav>

<div class="hero">
    <p class="hero-eyebrow">Darken Shadows Swimming Club</p>
    <h1>Our <em>Training</em> Programs</h1>
    <p class="hero-sub">From first strokes to podium finishes — choose the program that matches your goals and dive in.</p>
    <div class="hero-divider"></div>
</div>

<div class="programs-grid">

    <!-- 1. Junior Development -->
    <a class="program-card" href="junior.php">
        <div class="card-icon-wrap">🌊</div>
        <div class="card-tag">Ages 6 – 14</div>
        <div class="card-title">Junior Development</div>
        <div class="card-desc">Structured foundational training for young swimmers. We build technique, confidence, and a lifelong love for the water in a safe, encouraging environment.</div>
        <div class="card-meta">
            <span class="meta-pill">Beginner Friendly</span>
            <span class="meta-pill">3× / week</span>
            <span class="meta-pill">All 4 Strokes</span>
        </div>
        <div class="card-cta">Explore Program <span class="arrow">→</span></div>
    </a>

    <!-- 2. Competitive Squad -->
    <a class="program-card" href="competitive.php">
        <div class="card-icon-wrap">🏊</div>
        <div class="card-tag">Performance</div>
        <div class="card-title">Competitive Squad</div>
        <div class="card-desc">High-performance training for competitive swimmers targeting regional and national championships. Race-ready programmes with periodised plans.</div>
        <div class="card-meta">
            <span class="meta-pill">Advanced</span>
            <span class="meta-pill">5× / week</span>
            <span class="meta-pill">Meet Prep</span>
        </div>
        <div class="card-cta">Explore Program <span class="arrow">→</span></div>
    </a>

    <!-- 3. Elite Coaching -->
    <a class="program-card" href="elite.php">
        <div class="card-icon-wrap">🎯</div>
        <div class="card-tag">1-on-1</div>
        <div class="card-title">Elite Coaching</div>
        <div class="card-desc">One-on-one sessions with our head coaches for athletes chasing personal bests and podium finishes. Tailored drills, video analysis, and peak performance strategies.</div>
        <div class="card-meta">
            <span class="meta-pill">Private Sessions</span>
            <span class="meta-pill">Video Analysis</span>
            <span class="meta-pill">Flexible</span>
        </div>
        <div class="card-cta">Explore Program <span class="arrow">→</span></div>
    </a>

    <!-- 4. Adult Fitness Swim -->
    <a class="program-card" href="adult.php">
        <div class="card-icon-wrap">💪</div>
        <div class="card-tag">18+  All Levels</div>
        <div class="card-title">Adult Fitness Swim</div>
        <div class="card-desc">Structured adult lanes for fitness-focused swimmers of all levels. Coached sessions with flexible scheduling — swim before work, at lunch, or after hours.</div>
        <div class="card-meta">
            <span class="meta-pill">All Abilities</span>
            <span class="meta-pill">Flexible Schedule</span>
            <span class="meta-pill">Lane Swim</span>
        </div>
        <div class="card-cta">Explore Program <span class="arrow">→</span></div>
    </a>

    <!-- 5. Mental Conditioning -->
    <a class="program-card" href="mental.php">
        <div class="card-icon-wrap">🧠</div>
        <div class="card-tag">Sport Psychology</div>
        <div class="card-title">Mental Conditioning</div>
        <div class="card-desc">Sport psychology workshops integrated into our elite programs to sharpen focus, resilience, and race-day readiness. Train the mind as hard as the body.</div>
        <div class="card-meta">
            <span class="meta-pill">Workshops</span>
            <span class="meta-pill">Visualisation</span>
            <span class="meta-pill">Stress Control</span>
        </div>
        <div class="card-cta">Explore Program <span class="arrow">→</span></div>
    </a>

    <!-- 6. Masters Program -->
    <a class="program-card" href="masters.php">
        <div class="card-icon-wrap">🥇</div>
        <div class="card-tag">35+ Swimmers</div>
        <div class="card-title">Masters Program</div>
        <div class="card-desc">For swimmers 35+ who compete or simply seek the discipline of structured aquatic training. Age-group competition, camaraderie, and serious fitness gains.</div>
        <div class="card-meta">
            <span class="meta-pill">Age-Group Comps</span>
            <span class="meta-pill">Social Events</span>
            <span class="meta-pill">Coaching Included</span>
        </div>
        <div class="card-cta">Explore Program <span class="arrow">→</span></div>
    </a>

</div>

</body>
</html>