<?php
session_start();
require_once 'config/db.php';

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
    <title>About Us — Darken Shadows Swimming Club</title>

    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700;900&family=Raleway:wght@300;400;500;600&display=swap" rel="stylesheet">

    <style>
        /* ─── Ocean Palette ─── */
        :root {
            --ocean-1: #e0f4ff;
            --ocean-2: #b3e0f7;
            --ocean-3: #5db8e8;
            --ocean-4: #1a8fc1;
            --ocean-5: #0d6a99;
            --ocean-6: #064d75;
            --ocean-7: #02304d;

            --white: #ffffff;
            --text-light: rgba(255,255,255,0.85);
            --text-muted: rgba(255,255,255,0.55);
            --font-display: 'Cinzel', serif;
            --font-body: 'Raleway', sans-serif;
            --transition: 0.4s ease;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }

        body {
            font-family: var(--font-body);
            background: var(--ocean-7);
            color: var(--white);
            overflow-x: hidden;
        }

        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: var(--ocean-7); }
        ::-webkit-scrollbar-thumb { background: var(--ocean-4); border-radius: 3px; }

        /* ─── NAVBAR ─── */
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
        .nav-links a:hover, .nav-links a.active { color: var(--ocean-2); }

        .nav-user { display: flex; align-items: center; gap: 1rem; }

        .nav-greeting { font-size: 0.82rem; color: var(--text-muted); letter-spacing: 0.06em; }
        .nav-greeting strong { color: var(--ocean-2); font-weight: 600; }

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
        .btn-logout:hover { background: var(--ocean-4); color: var(--white); }

        /* ─── PAGE HERO ─── */
        .page-hero {
            position: relative;
            padding: 14rem 4rem 8rem;
            overflow: hidden;
            text-align: center;
        }

        /* animated water-like gradient background */
        .page-hero-bg {
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 80% 60% at 20% 80%, rgba(26,143,193,0.18) 0%, transparent 60%),
                radial-gradient(ellipse 60% 50% at 80% 20%, rgba(93,184,232,0.12) 0%, transparent 55%),
                linear-gradient(175deg, var(--ocean-7) 0%, #01253d 50%, var(--ocean-6) 100%);
            animation: bgShift 12s ease-in-out infinite alternate;
        }

        @keyframes bgShift {
            0%   { filter: hue-rotate(0deg); }
            100% { filter: hue-rotate(8deg); }
        }

        /* ripple rings */
        .ripple {
            position: absolute;
            border-radius: 50%;
            border: 1px solid rgba(93,184,232,0.12);
            animation: rippleExpand 8s ease-out infinite;
        }
        .ripple:nth-child(1) { width: 300px; height: 300px; top: 50%; left: 15%; animation-delay: 0s; }
        .ripple:nth-child(2) { width: 500px; height: 500px; top: 30%; right: 10%; animation-delay: 2s; }
        .ripple:nth-child(3) { width: 200px; height: 200px; bottom: 20%; left: 60%; animation-delay: 4s; }

        @keyframes rippleExpand {
            0%   { transform: translate(-50%,-50%) scale(0.6); opacity: 0.6; }
            100% { transform: translate(-50%,-50%) scale(1.4); opacity: 0; }
        }

        /* wave lines */
        .wave-lines {
            position: absolute;
            inset: 0;
            overflow: hidden;
            pointer-events: none;
        }

        .wave-line {
            position: absolute;
            left: -100%;
            right: -100%;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(93,184,232,0.2), transparent);
            animation: waveSweep 6s ease-in-out infinite;
        }
        .wave-line:nth-child(1) { top: 25%; animation-delay: 0s; }
        .wave-line:nth-child(2) { top: 55%; animation-delay: 2s; }
        .wave-line:nth-child(3) { top: 80%; animation-delay: 4s; }

        @keyframes waveSweep {
            0%   { transform: translateX(-20%); opacity: 0; }
            30%  { opacity: 1; }
            70%  { opacity: 1; }
            100% { transform: translateX(20%); opacity: 0; }
        }

        .page-hero-content {
            position: relative;
            z-index: 2;
        }

        .hero-eyebrow {
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.38em;
            text-transform: uppercase;
            color: var(--ocean-3);
            margin-bottom: 1.5rem;
            animation: fadeUp 1s ease both;
        }

        .page-hero h1 {
            font-family: var(--font-display);
            font-size: clamp(3rem, 7vw, 6rem);
            font-weight: 700;
            line-height: 1.05;
            letter-spacing: 0.04em;
            color: var(--white);
            margin-bottom: 2rem;
            animation: fadeUp 1s 0.15s ease both;
        }

        .page-hero h1 em {
            font-style: normal;
            color: var(--ocean-3);
            display: block;
        }

        .page-hero p {
            font-size: 1.1rem;
            font-weight: 300;
            line-height: 1.85;
            color: var(--text-light);
            max-width: 560px;
            margin: 0 auto;
            animation: fadeUp 1s 0.3s ease both;
        }

        /* vertical stroke accent below hero title */
        .title-stroke {
            display: block;
            width: 1px;
            height: 60px;
            background: linear-gradient(to bottom, var(--ocean-3), transparent);
            margin: 2.5rem auto 0;
            animation: fadeUp 1s 0.45s ease both;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(25px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ─── SECTION COMMONS ─── */
        .reveal {
            opacity: 0;
            transform: translateY(35px);
            transition: opacity 0.85s ease, transform 0.85s ease;
        }
        .reveal.visible { opacity: 1; transform: translateY(0); }

        .section-tag {
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.3em;
            text-transform: uppercase;
            color: var(--ocean-3);
            margin-bottom: 0.9rem;
        }

        .section-title {
            font-family: var(--font-display);
            font-size: clamp(1.7rem, 3.2vw, 2.6rem);
            font-weight: 600;
            line-height: 1.2;
            letter-spacing: 0.03em;
            color: var(--white);
            margin-bottom: 1.3rem;
        }

        .section-body {
            font-size: 1rem;
            font-weight: 300;
            line-height: 1.9;
            color: var(--text-light);
        }

        /* ─── STORY SECTION ─── */
        .story {
            padding: 6rem 4rem;
            display: grid;
            grid-template-columns: 1fr 1.6fr;
            gap: 5rem;
            max-width: 1200px;
            margin: 0 auto;
            align-items: center;
        }

        .story-left {
            position: relative;
        }

        .year-badge {
            font-family: var(--font-display);
            font-size: 7rem;
            font-weight: 900;
            line-height: 1;
            color: transparent;
            -webkit-text-stroke: 1px rgba(93,184,232,0.2);
            letter-spacing: -0.02em;
            margin-bottom: -1rem;
            display: block;
        }

        .timeline {
            display: flex;
            flex-direction: column;
            gap: 0;
            margin-top: 2rem;
        }

        .tl-item {
            display: grid;
            grid-template-columns: 60px 1fr;
            gap: 1.2rem;
            position: relative;
        }

        .tl-item:not(:last-child)::after {
            content: '';
            position: absolute;
            left: 29px;
            top: 34px;
            bottom: -2px;
            width: 1px;
            background: linear-gradient(to bottom, rgba(93,184,232,0.4), rgba(93,184,232,0.05));
        }

        .tl-year {
            font-family: var(--font-display);
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--ocean-3);
            letter-spacing: 0.06em;
            padding-top: 0.15rem;
            text-align: right;
        }

        .tl-dot-col {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .tl-body {
            padding-bottom: 2.2rem;
        }

        .tl-event {
            font-family: var(--font-display);
            font-size: 0.88rem;
            font-weight: 600;
            color: var(--ocean-1);
            letter-spacing: 0.04em;
            margin-bottom: 0.3rem;
        }

        .tl-desc {
            font-size: 0.85rem;
            font-weight: 300;
            line-height: 1.7;
            color: var(--text-muted);
        }

        /* NEW LAYOUT: year | dot | text */
        .tl-item {
            display: grid;
            grid-template-columns: 42px 16px 1fr;
            gap: 0 1rem;
            position: relative;
            align-items: start;
        }

        .tl-item:not(:last-child)::after {
            content: '';
            position: absolute;
            left: 53px; /* 42 + gap(~11) = centre of dot col */
            top: 16px;
            bottom: -4px;
            width: 1px;
            background: linear-gradient(to bottom, rgba(93,184,232,0.35), rgba(93,184,232,0.04));
        }

        .tl-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--ocean-4);
            border: 2px solid var(--ocean-3);
            margin-top: 4px;
            flex-shrink: 0;
            box-shadow: 0 0 8px rgba(93,184,232,0.4);
        }

        /* ─── MISSION / VALUES ─── */
        .values-section {
            background: linear-gradient(180deg, transparent, rgba(6,77,117,0.25), transparent);
            padding: 6rem 4rem;
        }

        .values-inner {
            max-width: 1200px;
            margin: 0 auto;
        }

        .values-header {
            text-align: center;
            max-width: 580px;
            margin: 0 auto 4.5rem;
        }

        .values-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
        }

        .value-card {
            position: relative;
            padding: 2.8rem 2.2rem;
            background: rgba(6,77,117,0.2);
            border: 1px solid rgba(93,184,232,0.12);
            border-radius: 3px;
            overflow: hidden;
            transition: border-color 0.35s, background 0.35s, transform 0.35s;
        }

        .value-card:hover {
            border-color: rgba(93,184,232,0.35);
            background: rgba(13,106,153,0.3);
            transform: translateY(-5px);
        }

        .value-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--ocean-4), var(--ocean-3));
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s;
        }
        .value-card:hover::before { transform: scaleX(1); }

        .value-number {
            font-family: var(--font-display);
            font-size: 3.5rem;
            font-weight: 900;
            color: transparent;
            -webkit-text-stroke: 1px rgba(93,184,232,0.2);
            line-height: 1;
            margin-bottom: 0.8rem;
            letter-spacing: -0.02em;
        }

        .value-title {
            font-family: var(--font-display);
            font-size: 1rem;
            font-weight: 600;
            letter-spacing: 0.06em;
            color: var(--ocean-1);
            margin-bottom: 1rem;
        }

        .value-text {
            font-size: 0.92rem;
            font-weight: 300;
            line-height: 1.8;
            color: var(--text-light);
        }

        /* ─── COACHES ─── */
        .coaches-section {
            padding: 7rem 4rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .coaches-header {
            text-align: center;
            max-width: 540px;
            margin: 0 auto 4rem;
        }

        .coaches-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
        }

        .coach-card {
            text-align: center;
            padding: 2.5rem 1.5rem 2rem;
            background: rgba(6,77,117,0.15);
            border: 1px solid rgba(93,184,232,0.1);
            border-radius: 3px;
            transition: all 0.35s;
            position: relative;
            overflow: hidden;
        }

        .coach-card:hover {
            background: rgba(13,106,153,0.25);
            border-color: rgba(93,184,232,0.3);
            transform: translateY(-4px);
        }

        /* abstract avatar — concentric circles with initials */
        .coach-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin: 0 auto 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: var(--font-display);
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--ocean-2);
            position: relative;
        }

        .coach-avatar::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 50%;
            background: radial-gradient(circle at 40% 35%, rgba(93,184,232,0.25), rgba(6,77,117,0.6));
            border: 1px solid rgba(93,184,232,0.3);
        }

        .coach-avatar::after {
            content: '';
            position: absolute;
            inset: -5px;
            border-radius: 50%;
            border: 1px solid rgba(93,184,232,0.12);
        }

        .coach-initials {
            position: relative;
            z-index: 1;
        }

        .coach-name {
            font-family: var(--font-display);
            font-size: 0.95rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            color: var(--ocean-1);
            margin-bottom: 0.4rem;
        }

        .coach-role {
            font-size: 0.78rem;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--ocean-3);
            margin-bottom: 1rem;
            font-weight: 500;
        }

        .coach-spec {
            font-size: 0.85rem;
            font-weight: 300;
            color: var(--text-muted);
            line-height: 1.65;
        }

        .coach-tag {
            display: inline-block;
            margin-top: 1.2rem;
            font-size: 0.7rem;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--ocean-7);
            background: var(--ocean-3);
            padding: 0.25rem 0.75rem;
            border-radius: 1px;
            font-weight: 600;
        }

        /* ─── PHILOSOPHY / MANIFESTO ─── */
        .manifesto {
            position: relative;
            padding: 8rem 4rem;
            overflow: hidden;
        }

        .manifesto-bg {
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 100% 80% at 50% 50%, rgba(26,143,193,0.1) 0%, transparent 70%),
                linear-gradient(180deg, var(--ocean-7) 0%, rgba(2,30,50,1) 50%, var(--ocean-7) 100%);
        }

        /* large decorative text behind */
        .manifesto-bg-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-family: var(--font-display);
            font-size: clamp(8rem, 18vw, 18rem);
            font-weight: 900;
            color: transparent;
            -webkit-text-stroke: 1px rgba(93,184,232,0.06);
            letter-spacing: -0.02em;
            white-space: nowrap;
            pointer-events: none;
            user-select: none;
        }

        .manifesto-inner {
            position: relative;
            z-index: 2;
            max-width: 820px;
            margin: 0 auto;
            text-align: center;
        }

        .manifesto-quote {
            font-family: var(--font-display);
            font-size: clamp(1.6rem, 3.5vw, 2.8rem);
            font-weight: 600;
            line-height: 1.35;
            letter-spacing: 0.03em;
            color: var(--white);
            margin-bottom: 2.5rem;
        }

        .manifesto-quote em {
            font-style: normal;
            color: var(--ocean-3);
        }

        .manifesto-attr {
            font-size: 0.78rem;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            color: var(--text-muted);
        }

        .manifesto-divider {
            width: 40px;
            height: 1px;
            background: var(--ocean-4);
            margin: 1.2rem auto;
        }

        .manifesto-pillars {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 3rem;
            margin-top: 5rem;
            text-align: left;
        }

        .pillar {
            border-left: 1px solid rgba(93,184,232,0.2);
            padding-left: 2rem;
        }

        .pillar-icon {
            font-size: 1.6rem;
            margin-bottom: 1rem;
        }

        .pillar-title {
            font-family: var(--font-display);
            font-size: 0.95rem;
            font-weight: 600;
            letter-spacing: 0.08em;
            color: var(--ocean-2);
            margin-bottom: 0.8rem;
        }

        .pillar-text {
            font-size: 0.9rem;
            font-weight: 300;
            line-height: 1.8;
            color: var(--text-muted);
        }

        /* ─── ACHIEVEMENTS ─── */
        .achievements {
            padding: 6rem 4rem;
            background: linear-gradient(90deg, var(--ocean-6), var(--ocean-5));
            border-top: 1px solid rgba(93,184,232,0.15);
            border-bottom: 1px solid rgba(93,184,232,0.15);
        }

        .achievements-inner {
            max-width: 1200px;
            margin: 0 auto;
        }

        .achievements-header {
            text-align: center;
            margin-bottom: 4rem;
        }

        .ach-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 2rem;
        }

        .ach-item {
            text-align: center;
            padding: 2rem 1rem;
            border: 1px solid rgba(93,184,232,0.15);
            border-radius: 3px;
            background: rgba(2,48,77,0.3);
            transition: all 0.35s;
        }

        .ach-item:hover {
            border-color: rgba(93,184,232,0.35);
            background: rgba(2,48,77,0.5);
            transform: translateY(-4px);
        }

        .ach-number {
            font-family: var(--font-display);
            font-size: 3rem;
            font-weight: 700;
            color: var(--ocean-2);
            line-height: 1;
            margin-bottom: 0.5rem;
        }

        .ach-label {
            font-size: 0.78rem;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: var(--text-muted);
            line-height: 1.5;
        }

        /* ─── CTA STRIP ─── */
        .cta-strip {
            padding: 6rem 4rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .cta-strip::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse 70% 60% at 50% 50%, rgba(26,143,193,0.12), transparent 70%);
        }

        .cta-strip-inner {
            position: relative;
            z-index: 2;
            max-width: 600px;
            margin: 0 auto;
        }

        .btn-primary {
            display: inline-block;
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
            display: inline-block;
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
            margin-left: 1rem;
        }

        .btn-outline:hover {
            border-color: var(--ocean-2);
            background: rgba(93,184,232,0.08);
            transform: translateY(-2px);
        }

        /* ─── FOOTER ─── */
        footer {
            background: rgba(1,20,35,0.9);
            border-top: 1px solid rgba(93,184,232,0.1);
            padding: 2.5rem 4rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .footer-brand {
            font-family: var(--font-display);
            font-size: 0.9rem;
            letter-spacing: 0.1em;
            color: var(--ocean-2);
        }

        .footer-brand span { color: var(--ocean-3); }

        .footer-copy {
            font-size: 0.78rem;
            color: var(--text-muted);
            letter-spacing: 0.06em;
        }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 1100px) {
            .coaches-grid { grid-template-columns: repeat(2, 1fr); }
            .ach-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 900px) {
            .navbar { padding: 1rem 2rem; }
            .story { grid-template-columns: 1fr; gap: 3rem; padding: 4rem 2rem; }
            .values-grid { grid-template-columns: 1fr; }
            .manifesto-pillars { grid-template-columns: 1fr; gap: 2rem; }
            .coaches-grid { grid-template-columns: repeat(2, 1fr); }
            .coaches-section { padding: 5rem 2rem; }
            .values-section { padding: 5rem 2rem; }
            .manifesto { padding: 6rem 2rem; }
            .cta-strip { padding: 5rem 2rem; }
            .achievements { padding: 5rem 2rem; }
            footer { flex-direction: column; gap: 1rem; text-align: center; padding: 2rem; }
        }

        @media (max-width: 600px) {
            .nav-links { display: none; }
            .navbar { padding: 1rem 1.5rem; }
            .page-hero { padding: 10rem 2rem 5rem; }
            .coaches-grid { grid-template-columns: 1fr; }
            .ach-grid { grid-template-columns: 1fr 1fr; }
            .btn-outline { margin-left: 0; margin-top: 0.8rem; }
            .cta-strip .btn-outline { display: block; }
        }
    </style>
</head>
<body>

<!-- NAV -->
<nav class="navbar" id="navbar">
    <a href="index.php" class="nav-brand">Darken <span>Shadows</span></a>
    <ul class="nav-links">
        <li><a href="index.php#about">About</a></li>
        <li><a href="program.php">Programs</a></li>
        <li><a href="facilities.php">Facilities</a></li>
        <li><a href="membership.php">Join</a></li>
    </ul>
    <div class="nav-user">
        <span class="nav-greeting">Welcome, <a href="profile.php" style="text-decoration:none;color:inherit"><strong><?= $user_name ?></strong></a></span>
        <a href="logout.php" class="btn-logout">Log Out</a>
    </div>
</nav>

<!-- PAGE HERO -->
<section class="page-hero">
    <div class="page-hero-bg"></div>
    <div class="ripple"></div>
    <div class="ripple"></div>
    <div class="ripple"></div>
    <div class="wave-lines">
        <div class="wave-line"></div>
        <div class="wave-line"></div>
        <div class="wave-line"></div>
    </div>
    <div class="page-hero-content">
        <p class="hero-eyebrow">Est. 2010 &nbsp;·&nbsp; Our Story</p>
        <h1>
            The Club Behind
            <em>The Champions</em>
        </h1>
        <p>A decade of relentless dedication, world-class coaching, and an unwavering belief that the water tests the truest version of every swimmer.</p>
        <span class="title-stroke"></span>
    </div>
</section>

<!-- ORIGIN STORY + TIMELINE -->
<section style="padding: 0 0 2rem; background: var(--ocean-7);">
    <div class="story">
        <div class="story-left reveal">
            <span class="year-badge">2010</span>
            <div class="timeline">
                <div class="tl-item">
                    <span class="tl-year">2010</span>
                    <div class="tl-dot"></div>
                    <div class="tl-body">
                        <div class="tl-event">The Beginning</div>
                        <div class="tl-desc">Founded by former national swimmer Marcus Hale with 12 members and a single rented lane.</div>
                    </div>
                </div>
                <div class="tl-item">
                    <span class="tl-year">2013</span>
                    <div class="tl-dot"></div>
                    <div class="tl-body">
                        <div class="tl-event">First Championship Title</div>
                        <div class="tl-desc">Our junior squad claimed their first regional crown, setting the tone for years to come.</div>
                    </div>
                </div>
                <div class="tl-item">
                    <span class="tl-year">2016</span>
                    <div class="tl-dot"></div>
                    <div class="tl-body">
                        <div class="tl-event">New Facility Opens</div>
                        <div class="tl-desc">Moved into our purpose-built 50m competition pool with full coaching infrastructure.</div>
                    </div>
                </div>
                <div class="tl-item">
                    <span class="tl-year">2019</span>
                    <div class="tl-dot"></div>
                    <div class="tl-body">
                        <div class="tl-event">Elite Programme Launch</div>
                        <div class="tl-desc">Introduced one-on-one elite coaching and mental conditioning modules for national-level athletes.</div>
                    </div>
                </div>
                <div class="tl-item">
                    <span class="tl-year">2024</span>
                    <div class="tl-dot"></div>
                    <div class="tl-body">
                        <div class="tl-event">350+ Members Strong</div>
                        <div class="tl-desc">Across 6 programmes, from age 6 to Masters, with 14 coaches on staff.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="story-right reveal" style="transition-delay:0.15s">
            <p class="section-tag">Our Origins</p>
            <h2 class="section-title">Born from Discipline.<br>Forged in the Water.</h2>
            <p class="section-body">
                Darken Shadows Swimming Club began not with grand ambitions or institutional backing — it began with a single, stubborn belief: that the right environment can turn potential into excellence.
            </p>
            <p class="section-body" style="margin-top:1.3rem">
                In 2010, Marcus Hale — a former national swimmer who understood the gap between talent and achievement — gathered a small group of dedicated young athletes. With one borrowed lane and uncompromising standards, the foundation was laid.
            </p>
            <p class="section-body" style="margin-top:1.3rem">
                Today, that philosophy remains unchanged. We don't promise shortcuts. We offer structure, expert guidance, and a community that demands the best from every swimmer — regardless of age, background, or ability. From beginner to elite, Darken Shadows is where the serious come to grow.
            </p>
            <p class="section-body" style="margin-top:1.3rem">
                Our growth has been measured not in size, but in standard. Every programme, every session, every coach appointment is made with a single question in mind: does this make our swimmers better?
            </p>
        </div>
    </div>
</section>

<!-- MISSION & VALUES -->
<section class="values-section">
    <div class="values-inner">
        <div class="values-header reveal">
            <p class="section-tag">What Drives Us</p>
            <h2 class="section-title">Our Core Values</h2>
            <p class="section-body">Six principles that shape how we coach, how we compete, and how we build swimmers who last.</p>
        </div>
        <div class="values-grid">
            <div class="value-card reveal">
                <div class="value-number">01</div>
                <div class="value-title">Excellence Without Compromise</div>
                <p class="value-text">Mediocrity is not an option. Every session is designed to push the limit — not in a reckless way, but with the precision of coaches who know exactly what the swimmer needs next.</p>
            </div>
            <div class="value-card reveal" style="transition-delay:0.1s">
                <div class="value-number">02</div>
                <div class="value-title">Technical Mastery</div>
                <p class="value-text">Speed follows technique. Our coaches obsess over stroke mechanics, turns, and underwater work — because the marginal gains that win races are found in the details others overlook.</p>
            </div>
            <div class="value-card reveal" style="transition-delay:0.2s">
                <div class="value-number">03</div>
                <div class="value-title">Mental Fortitude</div>
                <p class="value-text">Champions are made in the mind before they're made in the water. Our integrated sport psychology programme prepares swimmers for the pressure of competition at every level.</p>
            </div>
            <div class="value-card reveal" style="transition-delay:0.1s">
                <div class="value-number">04</div>
                <div class="value-title">Inclusive Ambition</div>
                <p class="value-text">From our youngest juniors to our Masters competitors, every swimmer receives the same quality of attention. Ambition has no age limit inside Darken Shadows.</p>
            </div>
            <div class="value-card reveal" style="transition-delay:0.2s">
                <div class="value-number">05</div>
                <div class="value-title">Community Accountability</div>
                <p class="value-text">We train as individuals, but we improve as a team. The culture of mutual accountability — swimmer to swimmer, coach to athlete — accelerates growth in ways solo training cannot replicate.</p>
            </div>
            <div class="value-card reveal" style="transition-delay:0.3s">
                <div class="value-number">06</div>
                <div class="value-title">Long-Term Development</div>
                <p class="value-text">We don't chase short-term results at the expense of a swimmer's development. Our programmes are built to produce athletes who improve for years, not just the next season.</p>
            </div>
        </div>
    </div>
</section>

<!-- MANIFESTO / PHILOSOPHY -->
<section class="manifesto">
    <div class="manifesto-bg"></div>
    <div class="manifesto-bg-text">DEPTH</div>
    <div class="manifesto-inner">
        <div class="reveal">
            <p class="section-tag">Our Philosophy</p>
            <blockquote class="manifesto-quote">
                "The water doesn't lie. It strips away every excuse, every comfort, every pretence — and what remains is <em>exactly</em> who you are."
            </blockquote>
            <div class="manifesto-divider"></div>
            <p class="manifesto-attr">Marcus Hale &nbsp;·&nbsp; Founder, Darken Shadows Swimming Club</p>
        </div>

        <div class="manifesto-pillars">
            <div class="pillar reveal">
                <div class="pillar-icon">🌊</div>
                <div class="pillar-title">Depth Before Speed</div>
                <p class="pillar-text">We build the foundations before we push the pace. A swimmer with poor mechanics swimming fast is just building bad habits faster. We take the time to get it right.</p>
            </div>
            <div class="pillar reveal" style="transition-delay:0.12s">
                <div class="pillar-icon">🔬</div>
                <div class="pillar-title">Evidence-Led Coaching</div>
                <p class="pillar-text">Every drill, training block, and recovery protocol is grounded in sport science. Our coaches hold internationally recognised qualifications and commit to continuous professional development.</p>
            </div>
            <div class="pillar reveal" style="transition-delay:0.24s">
                <div class="pillar-icon">🎯</div>
                <div class="pillar-title">The Long Game</div>
                <p class="pillar-text">Burnout is the enemy of greatness. We monitor load, celebrate incremental progress, and protect the joy of swimming — because the athletes who last are the ones who still love the water.</p>
            </div>
        </div>
    </div>
</section>

<!-- COACHING TEAM -->
<section class="coaches-section">
    <div class="coaches-header reveal">
        <p class="section-tag">The Coaching Staff</p>
        <h2 class="section-title">The People Behind the Performance</h2>
        <p class="section-body">Our coaches combine elite competitive experience with internationally recognised qualifications — and a genuine passion for developing the next generation.</p>
    </div>
    <div class="coaches-grid">
        <div class="coach-card reveal">
            <div class="coach-avatar"><span class="coach-initials">MH</span></div>
            <div class="coach-name">Marcus Hale</div>
            <div class="coach-role">Head Coach &amp; Founder</div>
            <p class="coach-spec">Former national finalist. 15+ years coaching. Specialises in elite squad strategy and race-day performance.</p>
            <span class="coach-tag">Elite &amp; Competitive</span>
        </div>
        <div class="coach-card reveal" style="transition-delay:0.1s">
            <div class="coach-avatar"><span class="coach-initials">SV</span></div>
            <div class="coach-name">Sasha Vance</div>
            <div class="coach-role">Junior Development Lead</div>
            <p class="coach-spec">Specialist in youth motor skill acquisition. Builds technique from first stroke with patience and precision.</p>
            <span class="coach-tag">Junior Development</span>
        </div>
        <div class="coach-card reveal" style="transition-delay:0.2s">
            <div class="coach-avatar"><span class="coach-initials">DR</span></div>
            <div class="coach-name">Daniel Rourke</div>
            <div class="coach-role">Competitive Squad Coach</div>
            <p class="coach-spec">Former club record holder. Intensive focus on IM events and dryland conditioning integration.</p>
            <span class="coach-tag">Competitive Squad</span>
        </div>
        <div class="coach-card reveal" style="transition-delay:0.3s">
            <div class="coach-avatar"><span class="coach-initials">AK</span></div>
            <div class="coach-name">Ayesha Karim</div>
            <div class="coach-role">Sport Psychologist</div>
            <p class="coach-spec">MSc in Applied Sport Psychology. Leads our mental conditioning programme for all performance levels.</p>
            <span class="coach-tag">Mental Conditioning</span>
        </div>
    </div>
</section>

<!-- ACHIEVEMENTS STRIP -->
<section class="achievements">
    <div class="achievements-inner">
        <div class="achievements-header reveal">
            <p class="section-tag">By the Numbers</p>
            <h2 class="section-title">A Record Built Stroke by Stroke</h2>
        </div>
        <div class="ach-grid">
            <div class="ach-item reveal">
                <div class="ach-number">18</div>
                <div class="ach-label">Regional &amp; National<br>Championship Titles</div>
            </div>
            <div class="ach-item reveal" style="transition-delay:0.1s">
                <div class="ach-number">350+</div>
                <div class="ach-label">Active Members<br>Across All Programmes</div>
            </div>
            <div class="ach-item reveal" style="transition-delay:0.2s">
                <div class="ach-number">14</div>
                <div class="ach-label">Qualified Coaches<br>On Our Staff</div>
            </div>
            <div class="ach-item reveal" style="transition-delay:0.3s">
                <div class="ach-number">6</div>
                <div class="ach-label">Specialist Programmes<br>From Age 6 to Masters</div>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="cta-strip">
    <div class="cta-strip-inner reveal">
        <p class="section-tag">Join the Club</p>
        <h2 class="section-title" style="margin-bottom:1.3rem">Ready to Write Your Chapter?</h2>
        <p class="section-body" style="margin-bottom:2.5rem">Whether you're placing a child in their first lesson or chasing a personal best at 45 — there is a place for you here. Come and see what we're about.</p>
        <a href="membership.php" class="btn-primary">Apply for Membership</a>
        <a href="program.php" class="btn-outline">Explore Programmes</a>
    </div>
</section>

<!-- FOOTER -->
<footer>
    <div class="footer-brand">Darken <span>Shadows</span> Swimming Club</div>
    <div class="footer-copy">&copy; <?= date('Y') ?> Darken Shadows. All rights reserved.</div>
</footer>

<script>
    // Navbar scroll
    const navbar = document.getElementById('navbar');
    window.addEventListener('scroll', () => {
        navbar.classList.toggle('scrolled', window.scrollY > 60);
    });
    navbar.classList.add('scrolled'); // always solid on inner pages

    // Reveal on scroll
    const reveals = document.querySelectorAll('.reveal');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12 });
    reveals.forEach(el => observer.observe(el));
</script>

</body>
</html>