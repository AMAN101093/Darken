<?php
session_start();
require_once 'config/db.php';

// Guard: redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_name = htmlspecialchars($_SESSION['user_name']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Darken Shadows Swimming Club</title>

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700;900&family=Raleway:wght@300;400;500;600&display=swap" rel="stylesheet">

    <style>
        /* ─── Ocean Palette ─────────────────────────────── */
        :root {
            --ocean-1: #e0f4ff;
            --ocean-2: #b3e0f7;
            --ocean-3: #5db8e8;
            --ocean-4: #1a8fc1;
            --ocean-5: #0d6a99;
            --ocean-6: #064d75;
            --ocean-7: #02304d;

            --white:   #ffffff;
            --off-white: #f0f8ff;
            --text-light: rgba(255,255,255,0.85);
            --text-muted: rgba(255,255,255,0.55);

            --font-display: 'Cinzel', serif;
            --font-body:    'Raleway', sans-serif;

            --transition: 0.4s ease;
        }

        /* ─── Reset ─────────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }

        body {
            font-family: var(--font-body);
            background: var(--ocean-7);
            color: var(--white);
            overflow-x: hidden;
        }

        img { display: block; max-width: 100%; }

        /* ─── Scrollbar ─────────────────────────────────── */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: var(--ocean-7); }
        ::-webkit-scrollbar-thumb { background: var(--ocean-4); border-radius: 3px; }

        /* ═══════════════════════════════════════════════════
           NAV BAR (minimal — header will be built later)
        ═══════════════════════════════════════════════════ */
        .navbar {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.2rem 4rem;
            transition: background var(--transition), backdrop-filter var(--transition);
        }

        .navbar.scrolled {
            background: rgba(2, 48, 77, 0.88);
            backdrop-filter: blur(14px);
            border-bottom: 1px solid rgba(93,184,232,0.15);
        }

        .nav-brand {
            font-family: var(--font-display);
            font-size: 1.1rem;
            letter-spacing: 0.12em;
            color: var(--ocean-2);
            text-decoration: none;
        }

        .nav-brand span { color: var(--ocean-3); }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 2.5rem;
            list-style: none;
        }

        .nav-links a {
            font-family: var(--font-body);
            font-size: 0.82rem;
            font-weight: 500;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--text-light);
            text-decoration: none;
            transition: color 0.25s;
        }

        .nav-links a:hover { color: var(--ocean-2); }

        .nav-user {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .nav-greeting {
            font-size: 0.82rem;
            color: var(--text-muted);
            letter-spacing: 0.06em;
        }

        .nav-greeting strong {
            color: var(--ocean-2);
            font-weight: 600;
        }

        .btn-logout {
            font-family: var(--font-body);
            font-size: 0.78rem;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--ocean-3);
            background: transparent;
            border: 1px solid var(--ocean-4);
            padding: 0.45rem 1.2rem;
            border-radius: 2px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.25s;
        }

        .btn-logout:hover {
            background: var(--ocean-4);
            color: var(--white);
        }

        /* ═══════════════════════════════════════════════════
           HERO — bg1.png  (16:9, full viewport)
        ═══════════════════════════════════════════════════ */
        .hero {
            position: relative;
            width: 100%;
            height: 100vh;
            min-height: 600px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .hero-bg {
            position: absolute;
            inset: 0;
            background: url('images/bg/bg1.png') center center / cover no-repeat;
            transform: scale(1.06);
            transition: transform 8s ease-out;
        }

        .hero-bg.loaded { transform: scale(1); }

        /* layered ocean overlay */
        .hero-overlay {
            position: absolute;
            inset: 0;
            background:
                linear-gradient(180deg,
                    rgba(2,48,77,0.35) 0%,
                    rgba(6,77,117,0.15) 40%,
                    rgba(2,48,77,0.75) 85%,
                    rgba(2,48,77,1)    100%
                );
        }

        /* animated wave shimmer */
        .hero-shimmer {
            position: absolute;
            inset: 0;
            background: repeating-linear-gradient(
                135deg,
                transparent,
                transparent 40px,
                rgba(93,184,232,0.04) 40px,
                rgba(93,184,232,0.04) 80px
            );
            animation: shimmerMove 12s linear infinite;
        }

        @keyframes shimmerMove {
            0%   { background-position: 0 0; }
            100% { background-position: 200px 200px; }
        }

        .hero-content {
            position: relative;
            z-index: 2;
            text-align: center;
            padding: 0 2rem;
            animation: fadeUp 1.2s ease both;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(30px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .hero-eyebrow {
            font-family: var(--font-body);
            font-size: 0.78rem;
            font-weight: 600;
            letter-spacing: 0.35em;
            text-transform: uppercase;
            color: var(--ocean-3);
            margin-bottom: 1.4rem;
            animation: fadeUp 1.2s 0.15s ease both;
        }

        .hero-title {
            font-family: var(--font-display);
            font-size: clamp(2.8rem, 6vw, 5.5rem);
            font-weight: 700;
            line-height: 1.08;
            letter-spacing: 0.04em;
            color: var(--white);
            margin-bottom: 1.6rem;
            animation: fadeUp 1.2s 0.3s ease both;
        }

        .hero-title .accent {
            color: var(--ocean-3);
            display: block;
        }

        .hero-subtitle {
            font-size: 1.05rem;
            font-weight: 300;
            color: var(--text-light);
            letter-spacing: 0.04em;
            max-width: 540px;
            margin: 0 auto 2.8rem;
            line-height: 1.75;
            animation: fadeUp 1.2s 0.45s ease both;
        }

        .hero-cta-group {
            display: flex;
            gap: 1.2rem;
            justify-content: center;
            flex-wrap: wrap;
            animation: fadeUp 1.2s 0.6s ease both;
        }

        .btn-primary {
            font-family: var(--font-display);
            font-size: 0.8rem;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: var(--ocean-7);
            background: var(--ocean-2);
            border: none;
            padding: 1rem 2.6rem;
            border-radius: 2px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            background: var(--white);
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(93,184,232,0.35);
        }

        .btn-outline {
            font-family: var(--font-display);
            font-size: 0.8rem;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: var(--ocean-2);
            background: transparent;
            border: 1px solid rgba(93,184,232,0.5);
            padding: 1rem 2.6rem;
            border-radius: 2px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-outline:hover {
            border-color: var(--ocean-2);
            background: rgba(93,184,232,0.08);
            transform: translateY(-2px);
        }

        /* scroll indicator */
        .scroll-hint {
            position: absolute;
            bottom: 2.5rem;
            left: 50%;
            transform: translateX(-50%);
            z-index: 2;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            animation: fadeUp 1.2s 1s ease both;
        }

        .scroll-hint span {
            font-size: 0.68rem;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            color: var(--text-muted);
        }

        .scroll-line {
            width: 1px;
            height: 50px;
            background: linear-gradient(to bottom, var(--ocean-3), transparent);
            animation: scrollPulse 2s ease-in-out infinite;
        }

        @keyframes scrollPulse {
            0%, 100% { opacity: 0.4; transform: scaleY(1); }
            50%       { opacity: 1;   transform: scaleY(1.15); }
        }

        /* ═══════════════════════════════════════════════════
           STATS BAR
        ═══════════════════════════════════════════════════ */
        .stats-bar {
            background: linear-gradient(90deg, var(--ocean-6), var(--ocean-5));
            border-top: 1px solid rgba(93,184,232,0.2);
            border-bottom: 1px solid rgba(93,184,232,0.2);
            padding: 2.5rem 4rem;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
        }

        .stat-item {
            text-align: center;
            padding: 0 1rem;
            border-right: 1px solid rgba(93,184,232,0.15);
        }

        .stat-item:last-child { border-right: none; }

        .stat-number {
            font-family: var(--font-display);
            font-size: 2.4rem;
            font-weight: 700;
            color: var(--ocean-2);
            line-height: 1;
            margin-bottom: 0.4rem;
        }

        .stat-label {
            font-size: 0.75rem;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--text-muted);
        }

        /* ═══════════════════════════════════════════════════
           SECTION COMMONS
        ═══════════════════════════════════════════════════ */
        .section {
            position: relative;
            overflow: hidden;
        }

        .section-tag {
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.28em;
            text-transform: uppercase;
            color: var(--ocean-3);
            margin-bottom: 0.9rem;
        }

        .section-title {
            font-family: var(--font-display);
            font-size: clamp(1.8rem, 3.5vw, 2.8rem);
            font-weight: 600;
            line-height: 1.2;
            letter-spacing: 0.03em;
            color: var(--white);
            margin-bottom: 1.2rem;
        }

        .section-body {
            font-size: 1rem;
            font-weight: 300;
            line-height: 1.85;
            color: var(--text-light);
        }

        .reveal {
            opacity: 0;
            transform: translateY(40px);
            transition: opacity 0.8s ease, transform 0.8s ease;
        }

        .reveal.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* ═══════════════════════════════════════════════════
           ABOUT SECTION — bg2.png (21:9)
        ═══════════════════════════════════════════════════ */
        .about {
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 620px;
        }

        .about-image {
            position: relative;
            overflow: hidden;
        }

        .about-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 8s ease;
        }

        .about-image:hover img { transform: scale(1.04); }

        .about-image-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg,
                transparent 60%,
                var(--ocean-7) 100%
            );
        }

        .about-content {
            background: var(--ocean-7);
            padding: 6rem 5rem 6rem 4rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .about-badge {
            display: inline-block;
            font-size: 0.72rem;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--ocean-7);
            background: var(--ocean-3);
            padding: 0.35rem 1rem;
            border-radius: 1px;
            margin-bottom: 2rem;
            font-weight: 600;
        }

        /* ═══════════════════════════════════════════════════
           PROGRAMS SECTION — bg3.png background
        ═══════════════════════════════════════════════════ */
        .programs {
            position: relative;
            padding: 8rem 4rem;
            text-align: center;
        }

        .programs-bg {
            position: absolute;
            inset: 0;
            background: url('images/bg/bg3.png') center center / cover no-repeat;
            filter: brightness(0.25) saturate(1.4);
        }

        .programs-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg,
                var(--ocean-7) 0%,
                rgba(2,48,77,0.6) 40%,
                rgba(2,48,77,0.6) 60%,
                var(--ocean-7) 100%
            );
        }

        .programs-inner {
            position: relative;
            z-index: 2;
        }

        .programs-header {
            max-width: 600px;
            margin: 0 auto 4rem;
        }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            max-width: 1100px;
            margin: 0 auto;
        }

        .program-card {
            background: rgba(6,77,117,0.4);
            border: 1px solid rgba(93,184,232,0.18);
            border-radius: 3px;
            padding: 2.5rem 2rem;
            text-align: left;
            backdrop-filter: blur(6px);
            transition: all 0.35s;
            position: relative;
            overflow: hidden;
        }

        .program-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--ocean-3), var(--ocean-4));
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s;
        }

        .program-card:hover {
            background: rgba(13,106,153,0.45);
            border-color: rgba(93,184,232,0.4);
            transform: translateY(-6px);
        }

        .program-card:hover::before { transform: scaleX(1); }

        .card-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: rgba(93,184,232,0.15);
            border: 1px solid rgba(93,184,232,0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            font-size: 1.4rem;
        }

        .card-title {
            font-family: var(--font-display);
            font-size: 1rem;
            font-weight: 600;
            letter-spacing: 0.06em;
            color: var(--ocean-1);
            margin-bottom: 0.8rem;
        }

        .card-text {
            font-size: 0.9rem;
            font-weight: 300;
            line-height: 1.75;
            color: var(--text-light);
        }

        .card-link {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            margin-top: 1.5rem;
            font-size: 0.78rem;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--ocean-3);
            text-decoration: none;
            font-weight: 600;
            transition: gap 0.25s;
        }

        .card-link:hover { gap: 0.7rem; }

        /* ═══════════════════════════════════════════════════
           GALLERY / IMMERSION — bg4.png
        ═══════════════════════════════════════════════════ */
        .immersion {
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 580px;
        }

        .immersion-content {
            background: linear-gradient(135deg, var(--ocean-6), var(--ocean-7));
            padding: 6rem 4rem 6rem 5rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .feature-list {
            list-style: none;
            margin-top: 2rem;
            display: flex;
            flex-direction: column;
            gap: 1.2rem;
        }

        .feature-list li {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            font-size: 0.95rem;
            font-weight: 300;
            color: var(--text-light);
            line-height: 1.6;
        }

        .feature-check {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: rgba(93,184,232,0.2);
            border: 1px solid var(--ocean-3);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 2px;
            font-size: 0.7rem;
            color: var(--ocean-3);
        }

        .immersion-image {
            position: relative;
            overflow: hidden;
        }

        .immersion-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 8s ease;
        }

        .immersion-image:hover img { transform: scale(1.04); }

        .immersion-image-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(270deg,
                transparent 60%,
                var(--ocean-6) 100%
            );
        }

        /* ═══════════════════════════════════════════════════
           CTA SECTION — bg5.png
        ═══════════════════════════════════════════════════ */
        .cta-section {
            position: relative;
            padding: 10rem 4rem;
            text-align: center;
            overflow: hidden;
        }

        .cta-bg {
            position: absolute;
            inset: 0;
            background: url('images/bg/bg5.png') center center / cover no-repeat;
            filter: brightness(0.2) saturate(1.6);
        }

        .cta-overlay {
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse at center,
                rgba(6,77,117,0.5) 0%,
                rgba(2,48,77,0.9) 70%
            );
        }

        .cta-inner {
            position: relative;
            z-index: 2;
            max-width: 700px;
            margin: 0 auto;
        }

        .cta-inner .section-title {
            font-size: clamp(2rem, 4vw, 3.5rem);
            margin-bottom: 1.5rem;
        }

        .cta-inner .section-body {
            margin-bottom: 3rem;
            font-size: 1.05rem;
        }

        /* decorative ring */
        .cta-ring {
            position: absolute;
            width: 500px;
            height: 500px;
            border-radius: 50%;
            border: 1px solid rgba(93,184,232,0.1);
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            animation: ringPulse 6s ease-in-out infinite;
        }

        .cta-ring:nth-child(2) {
            width: 700px;
            height: 700px;
            animation-delay: 2s;
        }

        @keyframes ringPulse {
            0%, 100% { opacity: 0.3; transform: translate(-50%, -50%) scale(1); }
            50%       { opacity: 0.7; transform: translate(-50%, -50%) scale(1.03); }
        }

        /* ═══════════════════════════════════════════════════
           RESPONSIVE
        ═══════════════════════════════════════════════════ */
        @media (max-width: 900px) {
            .navbar { padding: 1rem 2rem; }
            .stats-bar { grid-template-columns: repeat(2, 1fr); padding: 2rem; }
            .stat-item:nth-child(2) { border-right: none; }
            .about { grid-template-columns: 1fr; }
            .about-image { height: 320px; }
            .about-content { padding: 4rem 2.5rem; }
            .cards-grid { grid-template-columns: 1fr; max-width: 480px; }
            .immersion { grid-template-columns: 1fr; }
            .immersion-content { padding: 4rem 2.5rem; }
            .immersion-image { height: 320px; }
            .programs { padding: 6rem 2rem; }
            .cta-section { padding: 7rem 2rem; }
        }

        @media (max-width: 600px) {
            .nav-links { display: none; }
            .navbar { padding: 1rem 1.5rem; }
            .hero-title { font-size: 2.4rem; }
            .stats-bar { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>

<!-- ═══ NAV BAR ══════════════════════════════════════════ -->
<nav class="navbar" id="navbar">
    <a href="index.php" class="nav-brand">Darken <span>Shadows</span></a>

    <ul class="nav-links">
        <li><a href="#about">About</a></li>
        <li><a href="program.php">Programs</a></li>
        <li><a href="facilities.php">Facilities</a></li>
        <li><a href="membership.php">Join</a></li>
        
    </ul>

    <div class="nav-user">
            <span class="nav-greeting">Welcome, <a href="profile.php" style="text-decoration: none; color: inherit;"><strong><?= $user_name ?></strong></a></span>
        <a href="logout.php" class="btn-logout">Log Out</a>
    </div>
</nav>

<!-- ═══ HERO ═════════════════════════════════════════════ -->
<section class="hero">
    <div class="hero-bg" id="heroBg"></div>
    <div class="hero-overlay"></div>
    <div class="hero-shimmer"></div>

    <div class="hero-content">
        <p class="hero-eyebrow">Est. 2010 &nbsp;·&nbsp; Elite Aquatics</p>
        <h1 class="hero-title">
            Where Champions
            <span class="accent">Meet the Tide</span>
        </h1>
        <p class="hero-subtitle">
            Darken Shadows Swimming Club — forging elite swimmers through discipline, passion, and the relentless pursuit of the water's edge.
        </p>
        <div class="hero-cta-group">
            <a href="#programs" class="btn-primary">Explore Programs</a>
            <a href="#about" class="btn-outline">Our Story</a>
        </div>
    </div>

    <div class="scroll-hint">
        <span>Scroll</span>
        <div class="scroll-line"></div>
    </div>
</section>

<!-- ═══ STATS BAR ════════════════════════════════════════ -->
<div class="stats-bar">
    <div class="stat-item reveal">
        <div class="stat-number">350+</div>
        <div class="stat-label">Active Members</div>
    </div>
    <div class="stat-item reveal" style="transition-delay:0.1s">
        <div class="stat-number">18</div>
        <div class="stat-label">Championship Titles</div>
    </div>
    <div class="stat-item reveal" style="transition-delay:0.2s">
        <div class="stat-number">14</div>
        <div class="stat-label">Expert Coaches</div>
    </div>
    <div class="stat-item reveal" style="transition-delay:0.3s">
        <div class="stat-number">6</div>
        <div class="stat-label">Olympic Pool Lanes</div>
    </div>
</div>

<!-- ═══ ABOUT — bg2.png ═══════════════════════════════════ -->
<section class="about section" id="about">
    <div class="about-image">
        <img src="images/bg/bg2.png" alt="Swimming club atmosphere" loading="lazy">
        <div class="about-image-overlay"></div>
    </div>
    <div class="about-content">
        <div class="reveal">
            <span class="about-badge">Our Story</span>
            <p class="section-tag">Since 2010</p>
            <h2 class="section-title">Born from the Depths of Dedication</h2>
            <p class="section-body">
                Darken Shadows Swimming Club was founded on a singular belief — that the water reveals the truest version of a swimmer. From humble beginnings with just a dozen members, we've grown into one of the region's most respected aquatic institutions.
            </p>
            <p class="section-body" style="margin-top:1.2rem">
                Our philosophy merges technical mastery with mental fortitude. Every stroke, every turn, every breath is sculpted with purpose. We don't just train swimmers — we forge champions.
            </p>
        </div>
    </div>
</section>

<!-- ═══ PROGRAMS — bg3.png ════════════════════════════════ -->
<section class="programs section" id="programs">
    <div class="programs-bg"></div>
    <div class="programs-overlay"></div>

    <div class="programs-inner">
        <div class="programs-header reveal">
            <p class="section-tag">What We Offer</p>
            <h2 class="section-title">Programs Built to Excel</h2>
            <p class="section-body" style="max-width:520px; margin:0 auto">
                Whether you're just finding your rhythm or competing at national level, our programs are tailored to carry you further.
            </p>
        </div>

        <div class="cards-grid">
            <div class="program-card reveal">
                <div class="card-icon">🌊</div>
                <h3 class="card-title">Junior Development</h3>
                <p class="card-text">Structured foundational training for ages 6–14. We build technique, confidence, and a lifelong love for the water.</p>
                <a href="junior.php" class="card-link">Learn more →</a>
            </div>

            <div class="program-card reveal" style="transition-delay:0.12s">
                <div class="card-icon">🏊</div>
                <h3 class="card-title">Competitive Squad</h3>
                <p class="card-text">High-performance training for competitive swimmers targeting regional and national championships.</p>
                <a href="competitive.php" class="card-link">Learn more →</a>
            </div>

            <div class="program-card reveal" style="transition-delay:0.24s">
                <div class="card-icon">🎯</div>
                <h3 class="card-title">Elite Coaching</h3>
                <p class="card-text">One-on-one sessions with our head coaches for athletes chasing personal bests and podium finishes.</p>
                <a href="elite.php" class="card-link">Learn more →</a>
            </div>

            <div class="program-card reveal" style="transition-delay:0.12s">
                <div class="card-icon">💪</div>
                <h3 class="card-title">Adult Fitness Swim</h3>
                <p class="card-text">Structured adult lanes for fitness-focused swimmers of all levels. Coached sessions, flexible scheduling.</p>
                <a href="adult.php" class="card-link">Learn more →</a>
            </div>

            <div class="program-card reveal" style="transition-delay:0.24s">
                <div class="card-icon">🧠</div>
                <h3 class="card-title">Mental Conditioning</h3>
                <p class="card-text">Sport psychology workshops integrated into our elite programs to sharpen focus, resilience, and race-day readiness.</p>
                <a href="mental.php" class="card-link">Learn more →</a>
            </div>

            <div class="program-card reveal" style="transition-delay:0.36s">
                <div class="card-icon">🏅</div>
                <h3 class="card-title">Masters Program</h3>
                <p class="card-text">For swimmers 35+ who compete or simply seek the discipline of structured aquatic training.</p>
                <a href="masters.php" class="card-link">Learn more →</a>
            </div>
        </div>
    </div>
</section>

<!-- ═══ FACILITIES — bg4.png ════════════════════════════════ -->
<section class="immersion section" id="facilities">
    <div class="immersion-content">
        <div class="reveal">
            <p class="section-tag">World-Class Facilities</p>
            <h2 class="section-title">Built for Performance. Designed for the Elite.</h2>
            <p class="section-body">
                Our facility stands as a benchmark for aquatic excellence. Every detail — from lane temperature to lighting — is engineered to push limits.
            </p>
            <ul class="feature-list">
                <li>
                    <span class="feature-check">✓</span>
                    Olympic-length 50m heated competition pool with 6 competition lanes
                </li>
                <li>
                    <span class="feature-check">✓</span>
                    25m warm-up pool and dedicated diving section
                </li>
                <li>
                    <span class="feature-check">✓</span>
                    Underwater camera system for real-time stroke analysis
                </li>
                <li>
                    <span class="feature-check">✓</span>
                    Fully equipped dryland strength & conditioning gym
                </li>
                <li>
                    <span class="feature-check">✓</span>
                    Private changing suites, physiotherapy room & recovery bays
                </li>
            </ul>
        </div>
    </div>
    <div class="immersion-image">
        <img src="images/bg/bg4.png" alt="Club facilities" loading="lazy">
        <div class="immersion-image-overlay"></div>
    </div>
</section>

<!-- ═══ CTA — bg5.png ════════════════════════════════════ -->
<section class="cta-section section" id="join">
    <div class="cta-bg"></div>
    <div class="cta-overlay"></div>
    <div class="cta-ring"></div>
    <div class="cta-ring"></div>

    <div class="cta-inner reveal">
        <p class="section-tag">Ready to Dive In?</p>
        <h2 class="section-title">Your Journey Starts at the Edge of the Water</h2>
        <p class="section-body">
            Join Darken Shadows Swimming Club and become part of a legacy built on grit, grace, and the pursuit of excellence. Limited spots available for the upcoming season.
        </p>
        <div class="hero-cta-group">
            <a href="memebership.php" class="btn-primary">Apply for Membership</a>
            <a href="program.php" class="btn-outline">View Programs</a>
        </div>
    </div>
</section>

<!-- ═══ SCRIPTS ═══════════════════════════════════════════ -->
<script>
    // ── Navbar scroll effect
    const navbar = document.getElementById('navbar');
    window.addEventListener('scroll', () => {
        navbar.classList.toggle('scrolled', window.scrollY > 60);
    });

    // ── Hero bg parallax subtle load animation
    const heroBg = document.getElementById('heroBg');
    window.addEventListener('load', () => {
        heroBg.classList.add('loaded');
    });

    // ── Reveal on scroll (IntersectionObserver)
    const reveals = document.querySelectorAll('.reveal');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.15 });

    reveals.forEach(el => observer.observe(el));

    // ── Smooth anchor scroll
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth' });
            }
        });
    });
</script>

</body>
</html>