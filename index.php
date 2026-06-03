<?php
session_start();
require_once 'db_connect.php';
$logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$user_role = $logged_in ? $_SESSION['role'] : '';

// ── Active service notice ─────────────────────────────────────────────────────
$active_notice = null;
$table_exists = $conn->query("SHOW TABLES LIKE 'Service_Notices'");
if ($table_exists && $table_exists->num_rows > 0) {
    $res = $conn->query("SELECT * FROM Service_Notices
        WHERE is_active = 1
          AND (expires_at IS NULL OR expires_at >= CURDATE())
        LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $active_notice = $res->fetch_assoc();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>POSTAL PRO – Ship, Track, Deliver</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700;800&display=swap');

        *, *::before, *::after {
            margin: 0; padding: 0;
            box-sizing: border-box;
        }
        body, body * {
            font-family: 'Open Sans', sans-serif;
        }
        .fa, .fas, .far, .fal, .fab,
        .fa::before, .fas::before, .far::before, .fal::before, .fab::before {
            font-family: "Font Awesome 6 Free", "Font Awesome 6 Brands" !important;
        }

        body {
            background-color: #f4f6f9;
            color: #333;
            min-height: 100vh;
        }

        a { text-decoration: none; }

        /* ── TOP NAV ── */
        nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            background: #004B87;
            padding: 0 5%;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 200;
            left: 0;
            height: 60px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.25);
        }

        .logo-container { display: flex; align-items: center; gap: 10px; }

        .logo {
            font-weight: 800;
            font-size: 1.6rem;
            color: #fff;
            letter-spacing: -0.5px;
            transition: color 0.2s;
        }
        .logo:hover { color: #DA291C; }

        nav ul { display: flex; list-style: none; gap: 5px; }
        nav ul li a {
            color: #fff;
            font-size: 0.92rem;
            padding: 7px 13px;
            border-radius: 4px;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }
        nav ul li a:hover { background: rgba(255,255,255,0.15); }
        nav ul li a.nav-cta {
            background: #DA291C;
            font-weight: 600;
        }
        nav ul li a.nav-cta:hover { background: #b52218; }

        .menu-toggle { display: none; font-size: 22px; color: #fff; cursor: pointer; }

        /* ── HERO ── */
        .hero {
            background: linear-gradient(135deg, #003a6e 0%, #004B87 50%, #0068b5 100%);
            padding: 110px 5% 60px;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }
        .hero::before {
            content: '';
            position: absolute;
            top: -80px; right: -80px;
            width: 400px; height: 400px;
            background: rgba(218,41,28,0.08);
            border-radius: 50%;
        }
        .hero::after {
            content: '';
            position: absolute;
            bottom: -100px; left: -60px;
            width: 350px; height: 350px;
            background: rgba(255,255,255,0.04);
            border-radius: 50%;
        }

        .hero-eyebrow {
            font-size: 0.85rem;
            font-weight: 600;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.7);
            margin-bottom: 12px;
        }

        .hero h1 {
            font-size: 2.8rem;
            font-weight: 800;
            line-height: 1.15;
            margin-bottom: 14px;
        }
        .hero h1 span { color: #f7a800; }

        .hero-sub {
            font-size: 1.1rem;
            color: rgba(255,255,255,0.8);
            max-width: 560px;
            margin: 0 auto 32px;
            line-height: 1.6;
        }

        /* ── TRACK CARD IN HERO ── */
        .track-card {
            background: white;
            border-radius: 12px;
            padding: 28px 32px;
            max-width: 640px;
            margin: 0 auto;
            box-shadow: 0 12px 40px rgba(0,0,0,0.18);
            position: relative;
            z-index: 1;
        }
        .track-card-label {
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #888;
            margin-bottom: 10px;
            text-align: left;
        }
        .track-row {
            display: flex;
            gap: 10px;
        }
        .track-row input {
            flex: 1;
            padding: 13px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 1rem;
            color: #333;
            transition: border 0.2s;
        }
        .track-row input:focus { border-color: #004B87; outline: none; }
        .track-row input::placeholder { color: #aaa; }
        .track-btn {
            padding: 13px 24px;
            background: #004B87;
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.2s;
            white-space: nowrap;
        }
        .track-btn:hover { background: #003366; }
        .track-hint {
            font-size: 0.78rem;
            color: #aaa;
            margin-top: 8px;
            text-align: left;
        }

        /* ── QUICK ACTIONS BAR ── */
        .quick-bar {
            background: white;
            border-bottom: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .quick-bar-inner {
            display: flex;
            justify-content: center;
            gap: 0;
            max-width: 900px;
            margin: 0 auto;
        }
        .quick-action {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            padding: 18px 36px;
            color: #333;
            font-weight: 600;
            font-size: 0.92rem;
            border-right: 1px solid #e2e8f0;
            transition: background 0.2s, color 0.2s;
            cursor: pointer;
        }
        .quick-action:last-child { border-right: none; }
        .quick-action:hover { background: #f0f5ff; color: #004B87; }
        .quick-action i {
            font-size: 1.4rem;
            color: #004B87;
        }

        /* ── STATS STRIP ── */
        .stats-strip {
            background: #DA291C;
            padding: 22px 5%;
        }
        .stats-inner {
            max-width: 1100px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            text-align: center;
        }
        .stat-item { color: white; }
        .stat-number {
            font-size: 1.8rem;
            font-weight: 800;
            line-height: 1;
        }
        .stat-label {
            font-size: 0.8rem;
            opacity: 0.85;
            margin-top: 4px;
        }

        /* ── SECTION WRAPPER ── */
        .section {
            max-width: 1100px;
            margin: 0 auto;
            padding: 56px 5%;
        }
        .section-header {
            text-align: center;
            margin-bottom: 40px;
        }
        .section-eyebrow {
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #DA291C;
            margin-bottom: 8px;
        }
        .section-header h2 {
            font-size: 1.9rem;
            font-weight: 700;
            color: #004B87;
        }
        .section-header p {
            color: #666;
            margin-top: 10px;
            font-size: 1rem;
            max-width: 520px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.6;
        }

        /* ── HOW IT WORKS ── */
        .steps {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            position: relative;
        }
        .step-card {
            background: white;
            border-radius: 16px;
            padding: 32px 24px 28px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.07);
            border: 1px solid #eef0f5;
            position: relative;
            transition: transform 0.25s, box-shadow 0.25s;
        }
        .step-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 32px rgba(0,0,0,0.11);
        }
        .step-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 64px; height: 64px;
            border-radius: 50%;
            background: #f0f5ff;
            margin: 0 auto 20px;
            position: relative;
        }
        .step-badge i {
            font-size: 1.6rem;
            color: #004B87;
        }
        .step-badge .step-num {
            position: absolute;
            top: -4px; right: -4px;
            width: 22px; height: 22px;
            border-radius: 50%;
            background: #DA291C;
            color: white;
            font-size: 0.7rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid white;
        }
        .step-card h3 {
            font-size: 1.05rem;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 10px;
        }
        .step-card p {
            font-size: 0.88rem;
            color: #666;
            line-height: 1.65;
        }

        /* ── SERVICES GRID ── */
        .services-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        .service-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(0,0,0,0.06);
            transition: transform 0.25s, box-shadow 0.25s;
            display: flex;
            flex-direction: column;
        }
        .service-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 32px rgba(0,0,0,0.11);
        }
        .service-card-header {
            background: #004B87;
            padding: 22px 24px 18px;
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .service-card-header.accent { background: #DA291C; }
        .service-card-header.dark   { background: #1a2e4a; }
        .service-card-header i {
            font-size: 1.8rem;
            color: rgba(255,255,255,0.9);
        }
        .service-card-header h3 {
            color: white;
            font-size: 1.05rem;
            font-weight: 700;
        }
        .service-card-body {
            padding: 20px 24px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .service-card-body p {
            color: #555;
            font-size: 0.9rem;
            line-height: 1.6;
            flex: 1;
        }
        .service-card-body .tag-row {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin: 14px 0;
        }
        .tag {
            background: #e8f0f8;
            color: #004B87;
            font-size: 0.74rem;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 20px;
        }
        .service-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #004B87;
            font-weight: 600;
            font-size: 0.9rem;
            margin-top: auto;
            transition: gap 0.2s;
        }
        .service-link:hover { gap: 10px; }
        .service-link i { font-size: 0.8rem; }

        /* ── ALERT BAND (floating, no layout shift on dismiss) ── */
        .alert-band {
            background: #fff8e1;
            border-left: 4px solid #f7a800;
            padding: 14px 5%;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.9rem;
            color: #5a4200;
            position: fixed;
            top: 60px;
            left: 0;
            right: 0;
            z-index: 199;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .alert-band i { color: #f7a800; font-size: 1.1rem; }
        .alert-band-spacer {
            /* Keeps layout stable when band is dismissed; no page jump */
            background: linear-gradient(135deg, #003a6e 0%, #004B87 50%, #0068b5 100%);
        }

        /* ── SHIPPING OPTIONS BAND ── */
        .shipping-band {
            background: #f0f5ff;
            padding: 48px 5%;
        }
        .shipping-band-inner {
            max-width: 1100px;
            margin: 0 auto;
        }
        .shipping-options {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-top: 32px;
        }
        .ship-option {
            background: white;
            border-radius: 10px;
            padding: 24px 20px;
            border: 2px solid #e2e8f0;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .ship-option:hover { border-color: #004B87; box-shadow: 0 4px 16px rgba(0,75,135,0.12); }
        .ship-option-badge {
            display: inline-block;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 2px 10px;
            border-radius: 20px;
            margin-bottom: 12px;
        }
        .badge-ground  { background: #e8f0f8; color: #004B87; }
        .badge-express { background: #fff0f0; color: #DA291C; }
        .badge-economy { background: #e8f8ec; color: #1a6b2e; }
        .ship-option h3 {
            font-size: 1rem;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 6px;
        }
        .ship-option p { font-size: 0.85rem; color: #666; line-height: 1.5; }
        .ship-option .delivery-time {
            margin-top: 12px;
            font-size: 0.82rem;
            font-weight: 600;
            color: #004B87;
        }

        /* ── TESTIMONIALS ── */
        .testimonials-section {
            background: linear-gradient(160deg, #003a6e 0%, #004B87 60%, #00366b 100%);
            padding: 72px 5%;
        }
        .testimonials-inner { max-width: 1100px; margin: 0 auto; }
        .testimonials-header { text-align: center; margin-bottom: 16px; }
        .testimonials-header .section-eyebrow { color: rgba(255,255,255,0.6); }
        .testimonials-header h2 { color: #fff; font-size: 2rem; font-weight: 800; margin-top: 6px; }
        .rating-summary {
            display: flex; align-items: center; justify-content: center;
            gap: 10px; margin-bottom: 48px;
        }
        .rating-score { font-size: 2.6rem; font-weight: 800; color: #fff; line-height: 1; }
        .rating-detail { display: flex; flex-direction: column; gap: 3px; }
        .rating-stars { color: #f7a800; font-size: 1rem; letter-spacing: 2px; }
        .rating-count { font-size: 0.78rem; color: rgba(255,255,255,0.5); }
        .testimonials-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        .testimonial-card {
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 16px;
            padding: 28px 26px 24px;
            position: relative;
            backdrop-filter: blur(4px);
            transition: transform 0.25s, background 0.25s;
        }
        .testimonial-card:hover {
            transform: translateY(-4px);
            background: rgba(255,255,255,0.12);
        }
        .testimonial-quote {
            font-size: 3.5rem;
            color: #DA291C;
            line-height: 0.8;
            font-family: Georgia, serif;
            margin-bottom: 10px;
            display: block;
        }
        .stars { color: #f7a800; font-size: 0.82rem; letter-spacing: 1px; margin-bottom: 14px; }
        .testimonial-text {
            font-size: 0.92rem;
            color: rgba(255,255,255,0.85);
            line-height: 1.7;
            margin-bottom: 22px;
        }
        .testimonial-footer {
            display: flex; align-items: center; gap: 12px;
            border-top: 1px solid rgba(255,255,255,0.1);
            padding-top: 16px;
        }
        .testimonial-avatar {
            width: 40px; height: 40px; border-radius: 50%;
            background: #DA291C;
            display: flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: 1rem; color: #fff;
            flex-shrink: 0;
        }
        .testimonial-author { font-weight: 700; font-size: 0.88rem; color: #fff; }
        .testimonial-role { font-size: 0.78rem; color: rgba(255,255,255,0.5); margin-top: 1px; }

        /* ── BOTTOM CTA BAND ── */
        .cta-band {
            background: linear-gradient(135deg, #003a6e, #004B87);
            padding: 56px 5%;
            text-align: center;
            color: white;
        }
        .cta-band h2 {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 12px;
        }
        .cta-band p {
            color: rgba(255,255,255,0.8);
            font-size: 1rem;
            margin-bottom: 28px;
        }
        .cta-buttons {
            display: flex;
            justify-content: center;
            gap: 14px;
            flex-wrap: wrap;
        }
        .btn-primary {
            background: #DA291C;
            color: white;
            padding: 13px 32px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 1rem;
            transition: background 0.2s;
        }
        .btn-primary:hover { background: #b52218; }
        .btn-secondary {
            background: transparent;
            color: white;
            padding: 13px 32px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 1rem;
            border: 2px solid rgba(255,255,255,0.5);
            transition: border-color 0.2s, background 0.2s;
        }
        .btn-secondary:hover { border-color: white; background: rgba(255,255,255,0.08); }

        /* ── FOOTER ── */
        footer {
            background: #1a2e4a;
            color: rgba(255,255,255,0.75);
            padding: 40px 5% 20px;
        }
        .footer-grid {
            max-width: 1100px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 32px;
            padding-bottom: 32px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .footer-brand .logo { font-size: 1.4rem; display: inline-block; margin-bottom: 12px; }
        .footer-brand p { font-size: 0.85rem; line-height: 1.6; }
        .footer-col h4 {
            color: white;
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 14px;
        }
        .footer-col ul { list-style: none; }
        .footer-col ul li { margin-bottom: 8px; }
        .footer-col ul li a {
            color: rgba(255,255,255,0.65);
            font-size: 0.85rem;
            transition: color 0.2s;
        }
        .footer-col ul li a:hover { color: white; }
        .footer-bottom {
            max-width: 1100px;
            margin: 20px auto 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8rem;
            color: rgba(255,255,255,0.45);
        }
        .footer-bottom span { display: flex; align-items: center; gap: 5px; }
        .footer-knight { color: #DA291C; }

        /* ── RESPONSIVE ── */
        @media (max-width: 900px) {
            .steps, .services-grid, .testimonials-grid,
            .shipping-options, .stats-inner { grid-template-columns: 1fr 1fr; }
            .rating-summary { gap: 14px; }
            .footer-grid { grid-template-columns: 1fr 1fr; }
            .quick-bar-inner { flex-wrap: wrap; }
            .quick-action { flex: 1 1 calc(33% - 1px); border-right: none; border-bottom: 1px solid #e2e8f0; }
        }
        @media (max-width: 640px) {
            .hero h1 { font-size: 1.9rem; }
            .steps, .services-grid, .testimonials-grid,
            .shipping-options, .stats-inner { grid-template-columns: 1fr; }
            .testimonials-section { padding: 48px 5%; }
            .footer-grid { grid-template-columns: 1fr; }
            .track-row { flex-direction: column; }
            .menu-toggle { display: block; }
            nav ul {
                position: fixed;
                top: 0; right: -260px;
                width: 260px; height: 100vh;
                background: #003366;
                flex-direction: column;
                padding-top: 70px;
                transition: right 0.3s;
                z-index: 150;
                box-shadow: -5px 0 15px rgba(0,0,0,0.15);
                gap: 0;
            }
            nav ul.show { right: 0; }
            nav ul li a { padding: 14px 20px; border-radius: 0; }
            .footer-bottom { flex-direction: column; gap: 8px; text-align: center; }
        }

        /* ── ANIMATION SYSTEM ── */
        @keyframes heroFadeDown {
            from { opacity: 0; transform: translateY(-24px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        @keyframes heroFadeUp {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        @keyframes heroBgShift {
            0%,100% { background-position: 0% 50%; }
            50%      { background-position: 100% 50%; }
        }
        @keyframes ctaPulse {
            0%,100% { box-shadow: 0 0 0 0 rgba(218,41,28,0.45); }
            60%      { box-shadow: 0 0 0 10px rgba(218,41,28,0); }
        }
        /* Hero entrance */
        .hero-eyebrow { animation: heroFadeDown 0.65s 0.05s both ease; }
        .hero h1      { animation: heroFadeDown 0.65s 0.18s both ease; }
        .hero-sub     { animation: heroFadeUp  0.65s 0.32s both ease; }
        .track-card   { animation: heroFadeUp  0.7s  0.46s both ease; }
        /* Animated hero gradient */
        .hero { background-size: 200% 200%; animation: heroBgShift 8s ease infinite; }
        /* CTA pulse */
        .btn-primary  { animation: ctaPulse 2.8s 1.5s ease infinite; }
        /* Nav scroll shadow */
        nav { transition: box-shadow 0.3s; }
        nav.scrolled { box-shadow: 0 4px 28px rgba(0,0,0,0.45); }

        /* Scroll-reveal base */
        .reveal {
            opacity: 0;
            transform: translateY(32px);
            transition: opacity 0.6s ease, transform 0.6s ease;
        }
        .reveal.from-left  { transform: translateX(-44px); }
        .reveal.from-right { transform: translateX(44px); }
        .reveal.pop        { transform: scale(0.9) translateY(16px); }
        .reveal.visible    { opacity: 1 !important; transform: none !important; }
        /* Stagger delays */
        .d1 { transition-delay: 0.07s; }
        .d2 { transition-delay: 0.14s; }
        .d3 { transition-delay: 0.21s; }
        .d4 { transition-delay: 0.28s; }
        .d5 { transition-delay: 0.35s; }
        .d6 { transition-delay: 0.42s; }
    </style>
</head>
<body>

    <?php include '_public_nav.php'; ?>

    <?php
    $noticeStyles = [
        'info'    => ['band' => 'background:#fff8e1;border-color:#f7a800;color:#5a4200;', 'icon' => 'fa-info-circle', 'iconcol' => '#f7a800'],
        'warning' => ['band' => 'background:#fff3e0;border-color:#fb8c00;color:#4a2800;', 'icon' => 'fa-exclamation-triangle', 'iconcol' => '#fb8c00'],
        'danger'  => ['band' => 'background:#fff0f0;border-color:#DA291C;color:#7a1010;',  'icon' => 'fa-exclamation-circle', 'iconcol' => '#DA291C'],
        'success' => ['band' => 'background:#f0faf0;border-color:#2e7d32;color:#1b4a1b;', 'icon' => 'fa-check-circle', 'iconcol' => '#2e7d32'],
    ];
    if ($active_notice):
        $ns = $noticeStyles[$active_notice['type']] ?? $noticeStyles['info'];
    ?>
    <!-- ── ALERT BAND (floating; spacer avoids layout shift on dismiss) ── -->
    <div id="serviceBand" class="alert-band" style="<?= $ns['band'] ?>border-left-width:4px;border-left-style:solid;">
        <i class="fas <?= $ns['icon'] ?>" style="color:<?= $ns['iconcol'] ?>;font-size:1.1rem;flex-shrink:0;"></i>
        <span style="flex:1;">
            <strong>Service Notice:</strong> <?= htmlspecialchars($active_notice['message']) ?>
            <?php if ($active_notice['link_url']): ?>
            <a href="<?= htmlspecialchars($active_notice['link_url']) ?>" style="color:#004B87;font-weight:600;margin-left:6px;">
                <?= htmlspecialchars($active_notice['link_text'] ?: 'Learn more →') ?>
            </a>
            <?php endif; ?>
        </span>
        <button onclick="dismissNotice(<?= $active_notice['notice_id'] ?>)" title="Dismiss"
            style="background:none;border:none;cursor:pointer;opacity:0.5;font-size:1rem;padding:0 4px;line-height:1;flex-shrink:0;"
            onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.5'">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div id="serviceBandSpacer" class="alert-band-spacer" style="height: 52px;"></div>
    <script>
        function dismissNotice(id) {
            var band = document.getElementById('serviceBand');
            if (band) {
                band.style.transition = 'opacity 0.3s';
                band.style.opacity = '0';
                band.style.pointerEvents = 'none';
                setTimeout(function(){ band.style.display = 'none'; }, 300);
            }
            /* Spacer stays so page does not move */
        }
    </script>
    <?php else: ?>
    <div style="margin-top:60px;"></div>
    <?php endif; ?>

    <!-- ── HERO ── -->
    <section class="hero">
        <p class="hero-eyebrow">America's trusted postal network</p>
        <h1>Ship it. Track it.<br><span>Deliver it.</span></h1>
        <p class="hero-sub">Fast, reliable, and affordable shipping solutions for individuals and businesses — from your doorstep to anywhere in the country.</p>

        <div class="track-card">
            <p class="track-card-label"><i class="fas fa-map-marker-alt" style="color:#DA291C;margin-right:5px;"></i> Track a Shipment</p>
            <form class="track-row" action="package/track.php" method="get">
                <input type="text" name="tracking" placeholder="Enter your tracking number (e.g. PS20250001)" required>
                <button type="submit" class="track-btn"><i class="fas fa-search" style="margin-right:6px;"></i>Track</button>
            </form>
            <p class="track-hint">Tracking numbers are found in your confirmation email or customer dashboard.</p>
        </div>
    </section>

    <!-- ── QUICK ACTIONS BAR ── -->
    <div class="quick-bar">
        <div class="quick-bar-inner">
            <a href="<?= $logged_in ? ($user_role == 'Customer' ? 'sendpackage.php' : 'package/new_package.php') : 'login.php' ?>" class="quick-action reveal d1">
                <i class="fas fa-box"></i>
                <span>Ship a Package</span>
            </a>
            <a href="package/track.php" class="quick-action reveal d2">
                <i class="fas fa-search-location"></i>
                <span>Track & Manage</span>
            </a>
            <a href="<?= $logged_in ? 'shop.php' : 'login.php' ?>" class="quick-action reveal d3">
                <i class="fas fa-store"></i>
                <span>Postal Shop</span>
            </a>
            <a href="<?= $logged_in ? ($user_role == 'Customer' ? 'support.php' : 'employee_dashboard.php') : 'login.php' ?>" class="quick-action reveal d4">
                <i class="fas fa-headset"></i>
                <span>Get Support</span>
            </a>
            <a href="locations.php" class="quick-action reveal d5">
                <i class="fas fa-map-marked-alt"></i>
                <span>Find a Location</span>
            </a>
        </div>
    </div>

    <!-- ── STATS STRIP ── -->
    <div class="stats-strip reveal">
        <div class="stats-inner">
            <div class="stat-item">
                <div class="stat-number" data-count="50K+">50K+</div>
                <div class="stat-label">Packages Delivered Daily</div>
            </div>
            <div class="stat-item">
                <div class="stat-number" data-count="200+">200+</div>
                <div class="stat-label">Facilities Nationwide</div>
            </div>
            <div class="stat-item">
                <div class="stat-number" data-count="99.2%">99.2%</div>
                <div class="stat-label">On-Time Delivery Rate</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">24/7</div>
                <div class="stat-label">Real-Time Tracking</div>
            </div>
        </div>
    </div>

    <!-- HOW IT WORKS -->
    <div class="section" id="how-it-works">
        <div class="section-header reveal">
            <p class="section-eyebrow">Simple Process</p>
            <h2>How Postal Pro Works</h2>
            <p>From drop-off to delivery, we make every step simple and transparent.</p>
        </div>
        <div class="steps">
            <div class="step-card reveal d1">
                <div class="step-badge">
                    <i class="fas fa-user-plus"></i>
                    <span class="step-num">1</span>
                </div>
                <h3>Create an Account</h3>
                <p>Sign up in minutes to access shipping, tracking, and our online shop — all in one place.</p>
            </div>
            <div class="step-card reveal d2">
                <div class="step-badge">
                    <i class="fas fa-box"></i>
                    <span class="step-num">2</span>
                </div>
                <h3>Create a Shipment</h3>
                <p>Enter sender and receiver details, choose your package size and shipping speed, and pay securely at the counter.</p>
            </div>
            <div class="step-card reveal d3">
                <div class="step-badge">
                    <i class="fas fa-map-marker-alt"></i>
                    <span class="step-num">3</span>
                </div>
                <h3>Track in Real-Time</h3>
                <p>Every scan, sort, and delivery stop is logged. You and the recipient get full visibility from drop-off to door.</p>
            </div>
        </div>
    </div>

    <!-- SHIPPING OPTIONS -->
    <div class="shipping-band" id="shipping">
        <div class="shipping-band-inner">
            <div class="section-header reveal">
                <p class="section-eyebrow">Shipping Speeds</p>
                <h2>Choose Your Delivery Speed</h2>
                <p>Pick the shipping speed that fits your timeline and budget.</p>
            </div>
            <div class="shipping-options">
                <div class="ship-option reveal d1" id="economy">
                    <span class="ship-option-badge badge-economy">ECONOMY</span>
                    <h3>Economy Shipping</h3>
                    <p>Best value for non-urgent shipments. Reliable ground transit with full tracking included.</p>
                    <p class="delivery-time"><i class="fas fa-clock" style="margin-right:5px;"></i>5–7 Business Days &nbsp;·&nbsp; 20% discount applied</p>
                </div>
                <div class="ship-option reveal d2" id="standard">
                    <span class="ship-option-badge badge-ground">STANDARD</span>
                    <h3>Standard Shipping</h3>
                    <p>Our most popular option — solid mid-range speed with competitive rates for everyday shipping.</p>
                    <p class="delivery-time"><i class="fas fa-clock" style="margin-right:5px;"></i>3–5 Business Days &nbsp;·&nbsp; Regular rate</p>
                </div>
                <div class="ship-option reveal d3" id="express">
                    <span class="ship-option-badge badge-express">EXPRESS</span>
                    <h3>Express Shipping</h3>
                    <p>When time is critical. Priority handling at every step — guaranteed fast transit to the destination.</p>
                    <p class="delivery-time" style="color:#DA291C;"><i class="fas fa-bolt" style="margin-right:5px;"></i>1–2 Business Days &nbsp;·&nbsp; 70% premium</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ── SERVICES ── -->
    <div class="section">
        <div class="section-header reveal">
            <p class="section-eyebrow">What We Offer</p>
            <h2>Our Services</h2>
            <p>Everything you need to send, receive, and manage your postal needs in one place.</p>
        </div>
        <div class="services-grid">
            <div class="service-card reveal d1">
                <div class="service-card-header">
                    <i class="fas fa-search-location"></i>
                    <h3>Package Tracking</h3>
                </div>
                <div class="service-card-body">
                    <p>Real-time tracking from the moment your package is scanned at the facility to final delivery. Every scan is logged with location, employee, and timestamp.</p>
                    <div class="tag-row">
                        <span class="tag">Live updates</span>
                        <span class="tag">Full history</span>
                        <span class="tag">No login required</span>
                    </div>
                    <a href="package/track.php" class="service-link">Track a Package <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>

            <div class="service-card reveal d2">
                <div class="service-card-header accent">
                    <i class="fas fa-shipping-fast"></i>
                    <h3>Create a Shipment</h3>
                </div>
                <div class="service-card-body">
                    <p>Walk into any Postal Pro facility and have a clerk create your shipment instantly. Choose your speed, get a tracking number, and pay in seconds.</p>
                    <div class="tag-row">
                        <span class="tag">Same-day label</span>
                        <span class="tag">3 speed options</span>
                        <span class="tag">Signature option</span>
                    </div>
                    <a href="<?= $logged_in ? ($user_role == 'Customer' ? 'sendpackage.php' : 'package/new_package.php') : 'login.php' ?>" class="service-link">Get Started <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>

            <div class="service-card reveal d3">
                <div class="service-card-header dark">
                    <i class="fas fa-store"></i>
                    <h3>Postal Shop</h3>
                </div>
                <div class="service-card-body">
                    <p>Browse stamps, envelopes, boxes, and packaging supplies. Purchase online and pick up at your nearest facility — no shipping fee, instant ready.</p>
                    <div class="tag-row">
                        <span class="tag">In-store pickup</span>
                        <span class="tag">Supplies & stamps</span>
                        <span class="tag">No wait time</span>
                    </div>
                    <a href="<?= $logged_in ? 'shop.php' : 'login.php' ?>" class="service-link">Browse Shop <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>

            <div class="service-card reveal d4">
                <div class="service-card-header">
                    <i class="fas fa-headset"></i>
                    <h3>Customer Support</h3>
                </div>
                <div class="service-card-body">
                    <p>Submit a support ticket for delayed, lost, or damaged packages. Track your ticket status in real-time and communicate directly with our support team.</p>
                    <div class="tag-row">
                        <span class="tag">Ticket system</span>
                        <span class="tag">Live chat log</span>
                        <span class="tag">Status tracking</span>
                    </div>
                    <a href="<?= $logged_in ? 'support.php' : 'login.php' ?>" class="service-link">Open a Ticket <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>

            <div class="service-card reveal d5">
                <div class="service-card-header accent">
                    <i class="fas fa-box-open"></i>
                    <h3>Pickup Center</h3>
                </div>
                <div class="service-card-body">
                    <p>Packages ready for you? Head to your local facility's pickup desk. Clerks process your pickup instantly and mark your item as delivered on the spot.</p>
                    <div class="tag-row">
                        <span class="tag">Instant processing</span>
                        <span class="tag">Packages & shop orders</span>
                        <span class="tag">Notify alerts</span>
                    </div>
                    <a href="<?= $logged_in ? ($user_role == 'Customer' ? 'customer_dashboard.php' : 'package/awaiting_pickup.php') : 'login.php' ?>" class="service-link">View My Items <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>

            <div class="service-card reveal d6">
                <div class="service-card-header dark">
                    <i class="fas fa-map-marked-alt"></i>
                    <h3>Nationwide Network</h3>
                </div>
                <div class="service-card-body">
                    <p>Postal Pro operates a network of facilities across the U.S. with air and ground transport routes, driver delivery teams, and sorting staff at every hub.</p>
                    <div class="tag-row">
                        <span class="tag">200+ facilities</span>
                        <span class="tag">Air & ground</span>
                        <span class="tag">Coast to coast</span>
                    </div>
                    <a href="locations.php" class="service-link">Find a Location <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
        </div>
    </div>

    <!-- ── TESTIMONIALS ── -->
    <div class="testimonials-section">
        <div class="testimonials-inner">
            <div class="testimonials-header reveal">
                <p class="section-eyebrow">Customer Reviews</p>
                <h2>What Our Customers Say</h2>
            </div>
            <div class="rating-summary reveal">
                <div class="rating-score">4.9</div>
                <div class="rating-detail">
                    <div class="rating-stars">★★★★★</div>
                    <div class="rating-count">Based on 2,400+ verified reviews</div>
                </div>
            </div>
            <div class="testimonials-grid">
                <div class="testimonial-card reveal d1">
                    <span class="testimonial-quote">"</span>
                    <div class="stars">★★★★★</div>
                    <p class="testimonial-text">Postal Pro has transformed how I manage my small business shipping. The process is seamless and my packages always arrive on time — I wouldn't trust anyone else.</p>
                    <div class="testimonial-footer">
                        <div class="testimonial-avatar">S</div>
                        <div>
                            <p class="testimonial-author">Sarah Johnson</p>
                            <p class="testimonial-role">Small Business Owner</p>
                        </div>
                    </div>
                </div>
                <div class="testimonial-card reveal d2">
                    <span class="testimonial-quote">"</span>
                    <div class="stars">★★★★★</div>
                    <p class="testimonial-text">The real-time tracking lets me know exactly where my packages are at every step. No more guessing when something will arrive — total peace of mind.</p>
                    <div class="testimonial-footer">
                        <div class="testimonial-avatar" style="background:#004B87;">M</div>
                        <div>
                            <p class="testimonial-author">Michael Chen</p>
                            <p class="testimonial-role">Frequent Shipper</p>
                        </div>
                    </div>
                </div>
                <div class="testimonial-card reveal d3">
                    <span class="testimonial-quote">"</span>
                    <div class="stars">★★★★★</div>
                    <p class="testimonial-text">I love picking up my shop orders at the facility — it's instant, no shipping delays, and the staff are always professional and friendly.</p>
                    <div class="testimonial-footer">
                        <div class="testimonial-avatar" style="background:#1a6b2e;">E</div>
                        <div>
                            <p class="testimonial-author">Emily Rodriguez</p>
                            <p class="testimonial-role">Regular Customer</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── BOTTOM CTA ── -->
    <div class="cta-band reveal">
        <h2>Ready to Ship With Us?</h2>
        <p>Join thousands of customers and businesses who trust Postal Pro for reliable, trackable shipping.</p>
        <div class="cta-buttons">
            <?php if(!$logged_in): ?>
            <a href="register.php" class="btn-primary">Create a Free Account</a>
            <a href="login.php" class="btn-secondary">Sign In</a>
            <?php else: ?>
            <a href="<?= $user_role == 'Customer' ? 'sendpackage.php' : 'package/new_package.php' ?>" class="btn-primary">Create a Shipment</a>
            <a href="package/track.php" class="btn-secondary">Track a Package</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── FOOTER ── -->
    <footer>
        <div class="footer-grid">
            <div class="footer-brand">
                <a href="index.php" class="logo">POSTAL PRO</a>
                <p>America's trusted postal management network — delivering reliability, transparency, and speed to every doorstep.</p>
            </div>
            <div class="footer-col">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="package/track.php">Track a Package</a></li>
                    <li><a href="locations.php">Find a Location</a></li>
                    <li><a href="<?= $logged_in ? 'shop.php' : 'login.php' ?>">Postal Shop</a></li>
                    <li><a href="<?= $logged_in ? 'support.php' : 'login.php' ?>">Customer Support</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Resources</h4>
                <ul>
                    <li><a href="faqs.php">FAQs</a></li>
                    <li><a href="about.php">About Postal Pro</a></li>
                    <li><a href="shipping.php">Shipping Options</a></li>
                    <li><a href="careers.php">Join Our Team</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Account</h4>
                <ul>
                    <?php if($logged_in): ?>
                    <li><a href="<?= $user_role == 'Customer' ? 'customer_dashboard.php' : 'employee_dashboard.php' ?>">My Dashboard</a></li>
                    <li><a href="edit_profile.php">Edit Profile</a></li>
                    <li><a href="logout.php">Logout</a></li>
                    <?php else: ?>
                    <li><a href="login.php">Sign In</a></li>
                    <li><a href="register.php">Create Account</a></li>
                    <li><a href="employee_login.php">Employee Login</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <span>&copy; <?= date('Y') ?> Postal Service Management System. All rights reserved.</span>
            <span>Powered by the Postal Pro Team <i class="fas fa-chess-knight footer-knight"></i></span>
        </div>
    </footer>

    <script>
        // Nav scroll shadow
        window.addEventListener('scroll', () => {
            const nav = document.querySelector('.pp-nav');
            if (nav) nav.style.boxShadow = window.scrollY > 10
                ? '0 4px 28px rgba(0,0,0,0.45)'
                : '0 2px 8px rgba(0,0,0,0.25)';
        });

        // Scroll reveal via IntersectionObserver
        const revealObs = new IntersectionObserver((entries) => {
            entries.forEach(e => {
                if (e.isIntersecting) { e.target.classList.add('visible'); revealObs.unobserve(e.target); }
            });
        }, { threshold: 0.1 });
        document.querySelectorAll('.reveal').forEach(el => revealObs.observe(el));

        // Stats counter animation
        function animateCount(el) {
            const raw  = el.dataset.count;
            const num  = parseFloat(raw.replace(/[^0-9.]/g, ''));
            const suf  = raw.replace(/[0-9.]/g, '');
            const dec  = raw.includes('.') ? 1 : 0;
            if (!num) return;
            const dur  = 1600, t0 = performance.now();
            (function tick(now) {
                const p = Math.min((now - t0) / dur, 1);
                const v = (1 - Math.pow(1 - p, 3)) * num;
                el.textContent = v.toFixed(dec) + suf;
                if (p < 1) requestAnimationFrame(tick);
            })(performance.now());
        }
        const countObs = new IntersectionObserver((entries) => {
            entries.forEach(e => { if (e.isIntersecting) { animateCount(e.target); countObs.unobserve(e.target); } });
        }, { threshold: 0.6 });
        document.querySelectorAll('[data-count]').forEach(el => countObs.observe(el));
    </script>
</body>
</html>
