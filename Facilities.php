<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$user_name = htmlspecialchars($_SESSION['user_name']);

$facilities = [
    [
        'id'       => 1,
        'img'      => 'fl1.png',
        'tag'      => 'Competition Pool',
        'icon'     => '🏊',
        'title'    => 'Olympic 50m Competition Pool',
        'desc'     => 'Our centrepiece — a FINA-certified 50-metre heated competition pool featuring 8 lanes with anti-turbulence lane ropes, precision timing touchpads, and full electronic scoreboard integration. Water is maintained at 27–28 °C year-round.',
        'pills'    => ['50m × 8 Lanes', 'FINA Certified', '27–28 °C', 'Touchpad Timing'],
    ],
    [
        'id'       => 2,
        'img'      => 'fl2.png',
        'tag'      => 'Training Pool',
        'icon'     => '🌊',
        'title'    => '25m Warm-Up & Training Pool',
        'desc'     => 'A dedicated 25-metre warm-up pool with 6 lanes designed for drill work, stroke correction, and pre-race preparation. Separated from the main pool to ensure uninterrupted training sessions regardless of events.',
        'pills'    => ['25m × 6 Lanes', 'Drill Zone', 'Heated', 'Stroke Flags'],
    ],
    [
        'id'       => 3,
        'img'      => 'fl3.png',
        'tag'      => 'Performance Analysis',
        'icon'     => '🎥',
        'title'    => 'Underwater Video Analysis Suite',
        'desc'     => 'A cutting-edge underwater and above-water camera system with real-time feed to our coaching screens. Every angle of your stroke is captured and reviewed — dolphin kick, catch phase, tumble turn, breakout — frame by frame.',
        'pills'    => ['Multi-Angle Cameras', 'Live Coaching Feed', 'Session Recordings', 'HD Slow-Motion'],
    ],
    [
        'id'       => 4,
        'img'      => 'fl4.png',
        'tag'      => 'Dryland Training',
        'icon'     => '💪',
        'title'    => 'Strength & Conditioning Gym',
        'desc'     => 'A fully equipped dryland gym purpose-built for swimmers — resistance bands, cable machines, pull-up rigs, paddles boards, and sport-specific dry-land equipment. Supervised sessions run by certified strength coaches alongside swim training.',
        'pills'    => ['Sport-Specific Equipment', 'Certified S&C Coaches', 'Resistance Rigs', 'Open Access'],
    ],
    [
        'id'       => 5,
        'img'      => 'fl5.png',
        'tag'      => 'Recovery',
        'icon'     => '🧘',
        'title'    => 'Physiotherapy & Recovery Centre',
        'desc'     => 'Our on-site physiotherapy room is staffed by qualified sports physios offering injury assessments, massage, taping, and rehabilitation programmes. Adjacent recovery bays feature ice baths, contrast therapy pools, and compression boots.',
        'pills'    => ['Sports Physio On-Site', 'Ice Bath Stations', 'Compression Therapy', 'Injury Rehab'],
    ],
    [
        'id'       => 6,
        'img'      => 'fl6.png',
        'tag'      => 'Coaching Hub',
        'icon'     => '📋',
        'title'    => 'Coach & Analytics Hub',
        'desc'     => 'A dedicated coaching centre where performance data, session plans, and race analytics converge. Coaches build periodised programmes, review video sessions, and communicate directly with athletes through our club management platform.',
        'pills'    => ['Performance Software', 'Data-Driven Planning', 'Athlete Profiles', 'Meet Tracking'],
    ],
    [
        'id'       => 7,
        'img'      => 'fl7.png',
        'tag'      => 'Member Facilities',
        'icon'     => '🏅',
        'title'    => 'Premium Changing Suites',
        'desc'     => 'Private changing suites with individual lockers, heated towel rails, and high-pressure showers. Elite members have access to dedicated private changing rooms with poolside access. Secure, welcoming, and immaculately maintained.',
        'pills'    => ['Private Suites', 'Elite Poolside Access', 'Secure Lockers', 'Heated Showers'],
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facilities — Darken Shadows Swimming Club</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700;900&family=Raleway:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --ocean-1: #e0f4ff;
            --ocean-2: #b3e0f7;
            --ocean-3: #5db8e8;
            --ocean-4: #1a8fc1;
            --ocean-5: #0d6a99;
            --ocean-6: #064d75;
            --ocean-7: #02304d;
            --white:   #ffffff;
            --text-light: rgba(255,255,255,0.88);
            --text-muted: rgba(255,255,255,0.52);
            --card-bg:    rgba(6,77,117,0.35);
            --card-border: rgba(93,184,232,0.18);
            --font-display: 'Cinzel', serif;
            --font-body:    'Raleway', sans-serif;
            --transition: 0.35s ease;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }

        body {
            font-family: var(--font-body);
            background: var(--ocean-7);
            color: var(--white);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* ── Background ── */
        body::before {
            content: '';
            position: fixed; inset: 0; z-index: 0;
            background:
                radial-gradient(ellipse 80% 55% at 15% 8%,  rgba(26,143,193,.28) 0%, transparent 60%),
                radial-gradient(ellipse 60% 70% at 85% 88%, rgba(13,106,153,.32) 0%, transparent 55%),
                linear-gradient(160deg, var(--ocean-7) 0%, #063d5e 50%, var(--ocean-7) 100%);
            pointer-events: none;
        }

        /* faint grid texture */
        body::after {
            content: '';
            position: fixed; inset: 0; z-index: 0;
            background-image:
                linear-gradient(rgba(93,184,232,.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(93,184,232,.04) 1px, transparent 1px);
            background-size: 48px 48px;
            pointer-events: none;
        }

        /* ── Nav ── */
        nav {
            position: sticky; top: 0; z-index: 100;
            display: flex; align-items: center; justify-content: space-between;
            padding: 1.2rem 4rem;
            background: rgba(2,48,77,.82);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(93,184,232,.14);
        }

        .nav-brand {
            font-family: var(--font-display);
            font-size: 1.05rem; font-weight: 700;
            letter-spacing: .12em;
            color: var(--ocean-2);
            text-decoration: none;
        }
        .nav-brand span { color: var(--ocean-3); }

        .nav-links { display: flex; gap: 2rem; align-items: center; list-style: none; }
        .nav-links a {
            font-size: .8rem; font-weight: 600;
            letter-spacing: .1em; text-transform: uppercase;
            color: var(--text-muted); text-decoration: none;
            transition: color .25s;
        }
        .nav-links a:hover, .nav-links a.active { color: var(--ocean-2); }

        .nav-user {
            font-size: .8rem; color: var(--text-muted);
        }
        .nav-user span { color: var(--ocean-2); font-weight: 600; }

        /* ── Hero Banner ── */
        .hero {
            position: relative; z-index: 1;
            padding: 5.5rem 4rem 4rem;
            text-align: center;
        }

        .hero-eyebrow {
            font-size: .7rem; font-weight: 700;
            letter-spacing: .35em; text-transform: uppercase;
            color: var(--ocean-3);
            margin-bottom: 1.1rem;
            opacity: 0; animation: fadeUp .6s .1s ease forwards;
        }

        .hero h1 {
            font-family: var(--font-display);
            font-size: clamp(2.4rem, 5vw, 4rem);
            font-weight: 900; line-height: 1.08;
            letter-spacing: .04em;
            color: var(--white);
            margin-bottom: 1.3rem;
            opacity: 0; animation: fadeUp .6s .25s ease forwards;
        }

        .hero h1 em { color: var(--ocean-3); font-style: normal; }

        .hero-sub {
            font-size: 1rem; font-weight: 300;
            color: var(--text-light);
            max-width: 560px; margin: 0 auto 2.2rem;
            line-height: 1.8;
            opacity: 0; animation: fadeUp .6s .4s ease forwards;
        }

        .hero-line {
            width: 80px; height: 2px; margin: 0 auto;
            background: linear-gradient(90deg, transparent, var(--ocean-3), transparent);
            opacity: 0; animation: fadeUp .6s .52s ease forwards;
        }

        /* ── Facility Count Bar ── */
        .count-bar {
            position: relative; z-index: 1;
            display: flex; justify-content: center; gap: 4rem;
            padding: 2rem 4rem;
            border-top: 1px solid rgba(93,184,232,.1);
            border-bottom: 1px solid rgba(93,184,232,.1);
            background: linear-gradient(90deg, var(--ocean-6), var(--ocean-5), var(--ocean-6));
            margin-bottom: 0;
        }

        .count-item { text-align: center; }
        .count-num {
            font-family: var(--font-display);
            font-size: 2rem; font-weight: 700;
            color: var(--ocean-2); line-height: 1;
            margin-bottom: .3rem;
        }
        .count-label {
            font-size: .68rem; letter-spacing: .18em;
            text-transform: uppercase; color: var(--text-muted);
        }

        /* ── Facilities List ── */
        .facilities-wrapper {
            position: relative; z-index: 1;
            max-width: 1200px; margin: 0 auto;
            padding: 5rem 3rem 7rem;
            display: flex; flex-direction: column; gap: 5rem;
        }

        /* alternating layout */
        .facility-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid rgba(93,184,232,.18);
            box-shadow: 0 20px 60px rgba(0,0,0,.35);
            opacity: 0;
            transform: translateY(40px);
            transition: opacity .8s ease, transform .8s ease, box-shadow var(--transition);
        }

        .facility-row.visible {
            opacity: 1; transform: translateY(0);
        }

        .facility-row:hover {
            box-shadow: 0 28px 80px rgba(0,0,0,.45), 0 0 0 1px rgba(93,184,232,.25);
        }

        /* image side */
        .facility-img {
            position: relative; overflow: hidden;
            min-height: 420px;
        }

        .facility-img img {
            width: 100%; height: 100%;
            object-fit: cover;
            transition: transform 6s ease;
            display: block;
        }

        .facility-row:hover .facility-img img { transform: scale(1.06); }

        /* gradient fade toward content side */
        .facility-img::after {
            content: '';
            position: absolute; inset: 0;
        }

        /* odd rows — image left, content right */
        .facility-row:nth-child(odd) .facility-img::after {
            background: linear-gradient(90deg, transparent 50%, rgba(6,77,117,.85) 100%);
        }

        /* even rows — image right, content left */
        .facility-row:nth-child(even) {
            direction: rtl;
        }
        .facility-row:nth-child(even) > * { direction: ltr; }

        .facility-row:nth-child(even) .facility-img::after {
            background: linear-gradient(270deg, transparent 50%, rgba(6,77,117,.85) 100%);
        }

        /* top accent line */
        .facility-row::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; height: 2px;
            background: linear-gradient(90deg, transparent, var(--ocean-3), var(--ocean-4), transparent);
            opacity: 0;
            transition: opacity .4s;
            z-index: 5;
        }
        .facility-row:hover::before { opacity: 1; }
        .facility-row { position: relative; }

        /* content side */
        .facility-content {
            background: var(--card-bg);
            backdrop-filter: blur(14px);
            padding: 3.2rem 3.2rem 3rem;
            display: flex; flex-direction: column; justify-content: center;
            gap: 1.1rem;
        }

        .fac-tag {
            font-size: .65rem; font-weight: 700;
            letter-spacing: .28em; text-transform: uppercase;
            color: var(--ocean-3);
        }

        .fac-icon-title {
            display: flex; align-items: center; gap: .9rem;
        }

        .fac-icon {
            width: 52px; height: 52px;
            border-radius: 50%;
            background: rgba(93,184,232,.12);
            border: 1px solid rgba(93,184,232,.28);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.45rem; flex-shrink: 0;
            transition: background .3s, border-color .3s;
        }

        .facility-row:hover .fac-icon {
            background: rgba(93,184,232,.22);
            border-color: rgba(93,184,232,.55);
        }

        .fac-title {
            font-family: var(--font-display);
            font-size: 1.25rem; font-weight: 700;
            color: var(--white); line-height: 1.25;
            letter-spacing: .03em;
        }

        .fac-divider {
            width: 40px; height: 1px;
            background: linear-gradient(90deg, var(--ocean-3), transparent);
        }

        .fac-desc {
            font-size: .9rem; font-weight: 300;
            color: var(--text-light); line-height: 1.8;
        }

        .fac-pills {
            display: flex; flex-wrap: wrap; gap: .5rem;
            margin-top: .3rem;
        }

        .fac-pill {
            font-size: .67rem; font-weight: 600;
            letter-spacing: .1em;
            padding: .28rem .8rem;
            border-radius: 20px;
            background: rgba(93,184,232,.1);
            border: 1px solid rgba(93,184,232,.22);
            color: var(--ocean-2);
            transition: background .25s, border-color .25s;
        }

        .facility-row:hover .fac-pill {
            background: rgba(93,184,232,.16);
            border-color: rgba(93,184,232,.38);
        }

        .fac-num {
            font-family: var(--font-display);
            font-size: 5rem; font-weight: 900;
            color: rgba(93,184,232,.06);
            line-height: 1;
            position: absolute; bottom: 1.4rem; right: 1.8rem;
            pointer-events: none;
            user-select: none;
        }

        /* ── Bottom CTA ── */
        .cta-strip {
            position: relative; z-index: 1;
            text-align: center;
            padding: 6rem 2rem;
            background: linear-gradient(180deg, transparent, rgba(6,77,117,.35), transparent);
            border-top: 1px solid rgba(93,184,232,.1);
        }

        .cta-strip h2 {
            font-family: var(--font-display);
            font-size: clamp(1.8rem, 3vw, 2.6rem);
            font-weight: 700; color: var(--white);
            margin-bottom: 1rem; letter-spacing: .04em;
        }

        .cta-strip p {
            font-size: .95rem; font-weight: 300;
            color: var(--text-light);
            max-width: 480px; margin: 0 auto 2.5rem;
            line-height: 1.8;
        }

        .cta-strip .btn-group { display: flex; gap: 1.2rem; justify-content: center; flex-wrap: wrap; }

        .btn-primary {
            font-family: var(--font-display);
            font-size: .78rem; letter-spacing: .14em; text-transform: uppercase;
            color: var(--ocean-7); background: var(--ocean-2);
            border: none; padding: .95rem 2.4rem; border-radius: 2px;
            cursor: pointer; text-decoration: none; font-weight: 600;
            transition: all .3s;
        }
        .btn-primary:hover {
            background: var(--white); transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(93,184,232,.35);
        }

        .btn-outline {
            font-family: var(--font-display);
            font-size: .78rem; letter-spacing: .14em; text-transform: uppercase;
            color: var(--ocean-2); background: transparent;
            border: 1px solid rgba(93,184,232,.5);
            padding: .95rem 2.4rem; border-radius: 2px;
            cursor: pointer; text-decoration: none; font-weight: 600;
            transition: all .3s;
        }
        .btn-outline:hover {
            border-color: var(--ocean-2);
            background: rgba(93,184,232,.08);
            transform: translateY(-2px);
        }

        /* ── Animations ── */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── Responsive ── */
        @media (max-width: 900px) {
            nav { padding: 1rem 1.5rem; }
            .hero { padding: 4rem 1.5rem 3rem; }
            .count-bar { gap: 2rem; padding: 1.8rem 1.5rem; flex-wrap: wrap; }
            .facilities-wrapper { padding: 3.5rem 1.5rem 5rem; gap: 3rem; }
            .facility-row { grid-template-columns: 1fr; }
            .facility-img { min-height: 280px; }
            .facility-row:nth-child(even) { direction: ltr; }
            .facility-row:nth-child(odd)  .facility-img::after,
            .facility-row:nth-child(even) .facility-img::after {
                background: linear-gradient(180deg, transparent 40%, rgba(6,77,117,.9) 100%);
            }
            .facility-content { padding: 2.2rem 1.8rem; }
            .fac-num { font-size: 3.5rem; }
        }

        @media (max-width: 580px) {
            .nav-links { display: none; }
            .count-bar { gap: 1.4rem; }
            .hero h1 { font-size: 2.1rem; }
        }
    </style>
</head>
<body>

<!-- ── Nav ── -->
<nav>
    <a href="index.php" class="nav-brand">Darken <span>Shadows</span></a>
    <ul class="nav-links">
        <li><a href="index.php">Dashboard</a></li>
        <li><a href="program.php">Programs</a></li>
        <li><a href="facilities.php" class="active">Facilities</a></li>
        <li><a href="profile.php">My Profile</a></li>
        <li><a href="logout.php">Log Out</a></li>
    </ul>
        <span class="nav-greeting">Welcome, <a href="profile.php" style="text-decoration: none; color: inherit;"><strong><?= $user_name ?></strong></a></span>
</nav>

<!-- ── Hero ── -->
<div class="hero">
    <p class="hero-eyebrow">World-Class Infrastructure</p>
    <h1>Our <em>Facilities</em></h1>
    <p class="hero-sub">Every detail of the Darken Shadows complex is engineered to push limits — from competition-grade pools to cutting-edge recovery centres.</p>
    <div class="hero-line"></div>
</div>

<!-- ── Count Bar ── -->
<div class="count-bar">
    <div class="count-item">
        <div class="count-num">2</div>
        <div class="count-label">Competition Pools</div>
    </div>
    <div class="count-item">
        <div class="count-num">8</div>
        <div class="count-label">Racing Lanes</div>
    </div>
    <div class="count-item">
        <div class="count-num">7</div>
        <div class="count-label">Facility Areas</div>
    </div>
    <div class="count-item">
        <div class="count-num">24/7</div>
        <div class="count-label">Pool Monitoring</div>
    </div>
</div>

<!-- ── Facilities List ── -->
<div class="facilities-wrapper">
    <?php foreach ($facilities as $i => $f): ?>
    <div class="facility-row" id="fac-<?= $f['id'] ?>">

        <div class="facility-img">
            <img
                src="images/facilities/<?= htmlspecialchars($f['img']) ?>"
                alt="<?= htmlspecialchars($f['title']) ?>"
                loading="<?= $i === 0 ? 'eager' : 'lazy' ?>"
            >
        </div>

        <div class="facility-content">
            <p class="fac-tag"><?= htmlspecialchars($f['tag']) ?></p>
            <div class="fac-icon-title">
                <div class="fac-icon"><?= $f['icon'] ?></div>
                <h2 class="fac-title"><?= htmlspecialchars($f['title']) ?></h2>
            </div>
            <div class="fac-divider"></div>
            <p class="fac-desc"><?= htmlspecialchars($f['desc']) ?></p>
            <div class="fac-pills">
                <?php foreach ($f['pills'] as $pill): ?>
                <span class="fac-pill"><?= htmlspecialchars($pill) ?></span>
                <?php endforeach; ?>
            </div>
            <span class="fac-num"><?= str_pad($f['id'], 2, '0', STR_PAD_LEFT) ?></span>
        </div>

    </div>
    <?php endforeach; ?>
</div>

<!-- ── CTA Strip ── -->
<div class="cta-strip">
    <h2>Experience It Yourself</h2>
    <p>Book a tour of the complex or enroll in one of our programs to access all facilities from day one.</p>
    <div class="btn-group">
        <a href="program.php" class="btn-primary">Explore Programs</a>
        <a href="profile.php" class="btn-outline">My Membership</a>
    </div>
</div>

<script>
    // Reveal rows on scroll
    const rows = document.querySelectorAll('.facility-row');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12 });

    rows.forEach(row => observer.observe(row));
</script>

</body>
</html>