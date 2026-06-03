<?php
session_start();
$logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$user_role = $logged_in ? $_SESSION['role'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us | POSTAL PRO</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700;800&display=swap');

        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        body, body * { font-family: 'Open Sans', sans-serif; }
        .fa, .fas, .far, .fab,
        .fa::before, .fas::before, .far::before, .fab::before {
            font-family: "Font Awesome 6 Free", "Font Awesome 6 Brands" !important;
        }

        body { background-color: #f4f6f9; color: #333; min-height: 100vh; }

        a { text-decoration: none; }

        /* NAV */
        nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #004B87;
            padding: 0 5%;
            position: fixed;
            width: 100%; top: 0; left: 0;
            height: 60px;
            z-index: 200;
            box-shadow: 0 2px 8px rgba(0,0,0,0.25);
        }
        .logo-container { display: flex; align-items: center; }
        .logo { font-weight: 800; font-size: 1.6rem; color: #fff; letter-spacing: -0.5px; transition: color 0.2s; }
        .logo:hover { color: #DA291C; }
        nav ul { display: flex; list-style: none; gap: 5px; }
        nav ul li a { color: #fff; font-size: 0.95rem; padding: 8px 14px; border-radius: 4px; transition: background 0.2s; display: block; }
        nav ul li a:hover { background: rgba(255,255,255,0.15); }
        nav ul li a.nav-cta { background: #DA291C; font-weight: 600; }
        nav ul li a.nav-cta:hover { background: #b52218; }
        .menu-toggle { display: none; font-size: 22px; color: #fff; cursor: pointer; }

        /* PAGE HERO */
        .page-hero {
            background: linear-gradient(135deg, #003a6e 0%, #004B87 50%, #0068b5 100%);
            padding: 100px 5% 56px;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }
        .page-hero::before {
            content: '';
            position: absolute;
            top: -60px; right: -60px;
            width: 300px; height: 300px;
            background: rgba(218,41,28,0.08);
            border-radius: 50%;
        }
        .page-hero p.eyebrow {
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.65);
            margin-bottom: 10px;
        }
        .page-hero h1 {
            font-size: 2.6rem;
            font-weight: 800;
            line-height: 1.15;
            margin-bottom: 14px;
        }
        .page-hero p.sub {
            color: rgba(255,255,255,0.75);
            font-size: 1rem;
            max-width: 540px;
            margin: 0 auto;
            line-height: 1.65;
        }

        /* LAYOUT */
        .page-body { max-width: 1100px; margin: 0 auto; padding: 56px 5%; }

        /* SECTION HEADER */
        .section-header { text-align: center; margin-bottom: 36px; }
        .section-eyebrow { font-size: 0.75rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #DA291C; margin-bottom: 8px; }
        .section-header h2 { font-size: 1.75rem; font-weight: 700; color: #004B87; }
        .section-header p { color: #666; margin-top: 10px; font-size: 0.95rem; max-width: 560px; margin-left: auto; margin-right: auto; line-height: 1.65; }

        /* MISSION CARD */
        .mission-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.07);
            padding: 40px 44px;
            margin-bottom: 56px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 48px;
            align-items: start;
        }
        .mission-block h3 { font-size: 1.15rem; font-weight: 700; color: #004B87; margin-bottom: 12px; display: flex; align-items: center; gap: 10px; }
        .mission-block h3 i { color: #DA291C; font-size: 1rem; }
        .mission-block p { font-size: 0.93rem; color: #555; line-height: 1.75; margin-bottom: 14px; }
        .mission-block p:last-child { margin-bottom: 0; }

        /* FEATURES */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 56px;
        }
        .feature-card {
            background: white;
            border-radius: 12px;
            padding: 28px 20px;
            text-align: center;
            box-shadow: 0 4px 16px rgba(0,0,0,0.06);
            transition: transform 0.25s, box-shadow 0.25s;
        }
        .feature-card:hover { transform: translateY(-6px); box-shadow: 0 10px 28px rgba(0,0,0,0.1); }
        .feature-icon { font-size: 2rem; color: #004B87; margin-bottom: 14px; }
        .feature-card h3 { font-size: 0.95rem; font-weight: 700; color: #1a202c; margin-bottom: 8px; }
        .feature-card p { font-size: 0.83rem; color: #666; line-height: 1.6; }

        /* TECH STACK */
        .tech-band {
            background: #f0f5ff;
            border-radius: 12px;
            padding: 36px 40px;
            margin-bottom: 56px;
        }
        .tech-list {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: center;
            margin-top: 24px;
        }
        .tech-tag {
            background: white;
            border: 1px solid #dce8f5;
            color: #004B87;
            font-size: 0.83rem;
            font-weight: 600;
            padding: 7px 18px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 7px;
        }
        .tech-tag i { color: #DA291C; }

        /* TEAM */
        .team-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 56px;
        }
        .team-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(0,0,0,0.06);
            transition: transform 0.25s, box-shadow 0.25s;
            text-align: center;
        }
        .team-card:hover { transform: translateY(-6px); box-shadow: 0 12px 30px rgba(0,0,0,0.1); }
        .team-card-header {
            background: linear-gradient(135deg, #003a6e, #004B87);
            padding: 28px 20px 16px;
        }
        .team-img {
            width: 88px; height: 88px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid rgba(255,255,255,0.35);
            display: block;
            margin: 0 auto;
        }
        .team-card-body { padding: 18px 16px 22px; }
        .team-card-body h3 { font-size: 0.97rem; font-weight: 700; color: #1a202c; margin-bottom: 4px; }
        .team-card-body p { font-size: 0.78rem; color: #888; margin-bottom: 14px; }
        .team-social { display: flex; justify-content: center; gap: 10px; }
        .team-social a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px; height: 32px;
            border-radius: 50%;
            background: #f0f5ff;
            color: #004B87;
            font-size: 0.9rem;
            transition: background 0.2s, color 0.2s;
        }
        .team-social a:hover { background: #DA291C; color: white; }

        /* TIMELINE */
        .timeline { position: relative; padding-left: 32px; margin-bottom: 56px; }
        .timeline::before {
            content: '';
            position: absolute;
            left: 7px; top: 4px; bottom: 4px;
            width: 2px; background: #e2e8f0;
        }
        .timeline-item { position: relative; margin-bottom: 32px; }
        .timeline-item:last-child { margin-bottom: 0; }
        .timeline-dot {
            position: absolute;
            left: -32px; top: 4px;
            width: 16px; height: 16px;
            border-radius: 50%;
            background: #004B87;
            border: 2px solid white;
            box-shadow: 0 0 0 2px #004B87;
        }
        .timeline-item.accent .timeline-dot { background: #DA291C; box-shadow: 0 0 0 2px #DA291C; }
        .timeline-year {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #DA291C;
            margin-bottom: 4px;
        }
        .timeline-item h4 { font-size: 0.97rem; font-weight: 700; color: #1a202c; margin-bottom: 5px; }
        .timeline-item p { font-size: 0.87rem; color: #666; line-height: 1.6; }

        /* CTA BAND */
        .cta-band {
            background: linear-gradient(135deg, #003a6e, #004B87);
            border-radius: 12px;
            padding: 48px 40px;
            text-align: center;
            color: white;
            margin-bottom: 56px;
        }
        .cta-band h2 { font-size: 1.8rem; font-weight: 800; margin-bottom: 10px; }
        .cta-band p { color: rgba(255,255,255,0.75); font-size: 0.97rem; margin-bottom: 26px; }
        .cta-buttons { display: flex; justify-content: center; gap: 14px; flex-wrap: wrap; }
        .btn-primary { background: #DA291C; color: white; padding: 12px 30px; border-radius: 6px; font-weight: 700; font-size: 0.95rem; transition: background 0.2s; display: inline-block; }
        .btn-primary:hover { background: #b52218; }
        .btn-outline { background: transparent; color: white; padding: 12px 30px; border-radius: 6px; font-weight: 600; font-size: 0.95rem; border: 2px solid rgba(255,255,255,0.45); transition: border-color 0.2s, background 0.2s; display: inline-block; }
        .btn-outline:hover { border-color: white; background: rgba(255,255,255,0.08); }

        /* FOOTER */
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
        .footer-col h4 { color: white; font-size: 0.82rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 14px; }
        .footer-col ul { list-style: none; }
        .footer-col ul li { margin-bottom: 8px; }
        .footer-col ul li a { color: rgba(255,255,255,0.65); font-size: 0.85rem; transition: color 0.2s; }
        .footer-col ul li a:hover { color: white; }
        .footer-bottom { max-width: 1100px; margin: 20px auto 0; display: flex; justify-content: space-between; align-items: center; font-size: 0.78rem; color: rgba(255,255,255,0.4); flex-wrap: wrap; gap: 8px; }
        .footer-knight { color: #DA291C; }

        /* RESPONSIVE */
        @media (max-width: 900px) {
            .mission-card { grid-template-columns: 1fr; gap: 28px; }
            .features-grid, .team-grid { grid-template-columns: repeat(2, 1fr); }
            .stats-strip { grid-template-columns: repeat(2, 1fr); gap: 20px; }
            .footer-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 640px) {
            .page-hero h1 { font-size: 1.9rem; }
            .features-grid, .team-grid { grid-template-columns: 1fr; }
            .mission-card { padding: 28px 22px; }
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
            .footer-grid { grid-template-columns: 1fr; }
            .footer-bottom { flex-direction: column; text-align: center; }
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
        .page-hero .eyebrow { animation: heroFadeDown 0.65s 0.05s both ease; }
        .page-hero h1       { animation: heroFadeDown 0.65s 0.18s both ease; }
        .page-hero .sub     { animation: heroFadeUp  0.65s 0.32s both ease; }
        /* Animated hero gradient */
        .page-hero { background-size: 200% 200%; animation: heroBgShift 8s ease infinite; }
        /* CTA pulse */
        .btn-primary { animation: ctaPulse 2.8s 1.5s ease infinite; }
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
        .d7 { transition-delay: 0.49s; }
        .d8 { transition-delay: 0.56s; }
    </style>
</head>
<body>

    <!-- NAV -->
    <?php include '_public_nav.php'; ?>

    <!-- HERO -->
    <section class="page-hero">
        <p class="eyebrow">Our story & mission</p>
        <h1>About Postal Pro</h1>
        <p class="sub">Built by a team passionate about modernizing postal operations — from tracking and shipping to inventory, support, and beyond.</p>
    </section>

    <div class="page-body">

        <!-- MISSION + STORY -->
        <div class="section-header reveal" style="margin-top:0;">
            <p class="section-eyebrow">Who We Are</p>
            <h2>Mission & Story</h2>
        </div>

        <div class="mission-card">
            <div class="mission-block reveal from-left">
                <h3><i class="fas fa-bullseye"></i> Our Mission</h3>
                <p>Postal Pro was built with a clear goal: to modernize how postal services are managed and accessed. We believe in creating systems that are intuitive, efficient, and accessible to everyone — from individual customers to postal clerks, drivers, managers, and administrators.</p>
                <p>We strive to optimize the entire postal process — from package creation and real-time tracking to inventory management, trip routing, and customer support — all in a single, unified platform.</p>
            </div>
            <div class="mission-block reveal from-right">
                <h3><i class="fas fa-book-open"></i> Our Story</h3>
                <p>Postal Pro started as a database systems class project with a simple question: "What if managing a postal service felt as simple as checking an app?" That question turned into a full-featured platform built from the ground up.</p>
                <p>From package tracking and multi-role dashboards to trip management, shop inventory, and a ticket support system — every feature was designed to solve a real problem in how postal operations are run.</p>
            </div>
        </div>

        <!-- FEATURES -->
        <div class="section-header reveal">
            <p class="section-eyebrow">Platform Capabilities</p>
            <h2>What the System Does</h2>
            <p>Postal Pro is a full-stack postal management platform with tools for every role in the operation.</p>
        </div>

        <div class="features-grid">
            <div class="feature-card reveal d1">
                <div class="feature-icon"><i class="fas fa-map-marker-alt"></i></div>
                <h3>Real-Time Tracking</h3>
                <p>Every scan is logged with timestamp, location, employee, and action — giving full visibility from drop-off to delivery.</p>
            </div>
            <div class="feature-card reveal d2">
                <div class="feature-icon"><i class="fas fa-route"></i></div>
                <h3>Trip & Route Management</h3>
                <p>Managers create ground, air, and delivery routes. Drivers follow their route, mark packages delivered stop-by-stop.</p>
            </div>
            <div class="feature-card reveal d3">
                <div class="feature-icon"><i class="fas fa-store"></i></div>
                <h3>Inventory & Shop</h3>
                <p>Clerks manage stock levels, process sales, and handle in-facility shop transactions with full inventory control.</p>
            </div>
            <div class="feature-card reveal d4">
                <div class="feature-icon"><i class="fas fa-headset"></i></div>
                <h3>Support Ticket System</h3>
                <p>Customers open tickets for delayed, lost, or damaged packages. Agents respond in a real-time chat-style log.</p>
            </div>
            <div class="feature-card reveal d5">
                <div class="feature-icon"><i class="fas fa-users-cog"></i></div>
                <h3>Multi-Role Dashboards</h3>
                <p>Custom dashboards for Customers, Clerks, Drivers, Sorting Staff, Managers, and Admins — each with role-specific tools.</p>
            </div>
            <div class="feature-card reveal d6">
                <div class="feature-icon"><i class="fas fa-chart-bar"></i></div>
                <h3>Reports & Analytics</h3>
                <p>Managers and admins access reports on package volume, sales, route performance, and facility-level statistics.</p>
            </div>
            <div class="feature-card reveal d7">
                <div class="feature-icon"><i class="fas fa-box-open"></i></div>
                <h3>Pickup Management</h3>
                <p>Clerks see a live queue of packages ready for pickup, mark them as delivered, and notify customers — all from one page.</p>
            </div>
            <div class="feature-card reveal d8">
                <div class="feature-icon"><i class="fas fa-shield-alt"></i></div>
                <h3>Secure & Role-Gated</h3>
                <p>Every page is role-checked. Customers, employees, and admins each see only what they're authorized to see.</p>
            </div>
        </div>

        <!-- TECH STACK -->
        <div class="tech-band reveal">
            <div class="section-header" style="margin-bottom: 0;">
                <p class="section-eyebrow">Built With</p>
                <h2>Technology Stack</h2>
            </div>
            <div class="tech-list">
                <span class="tech-tag"><i class="fas fa-code"></i> PHP 8</span>
                <span class="tech-tag"><i class="fas fa-database"></i> MySQL / MariaDB</span>
                <span class="tech-tag"><i class="fas fa-file-code"></i> HTML5</span>
                <span class="tech-tag"><i class="fas fa-palette"></i> CSS3</span>
                <span class="tech-tag"><i class="fas fa-terminal"></i> JavaScript (Vanilla)</span>
                <span class="tech-tag"><i class="fas fa-wind"></i> Tailwind CSS</span>
                <span class="tech-tag"><i class="fas fa-server"></i> Apache (XAMPP)</span>
                <span class="tech-tag"><i class="fas fa-star"></i> Font Awesome 6</span>
                <span class="tech-tag"><i class="fas fa-font"></i> Google Fonts (Open Sans)</span>
                <span class="tech-tag"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="flex-shrink:0"><path d="M12 0C5.374 0 0 5.373 0 12c0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23A11.509 11.509 0 0112 5.803c1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576C20.566 21.797 24 17.3 24 12c0-6.627-5.373-12-12-12z"/></svg> Git & GitHub</span>
            </div>
        </div>

        <!-- PROJECT TIMELINE -->
        <div class="section-header reveal">
            <p class="section-eyebrow">Development Journey</p>
            <h2>Project Timeline</h2>
        </div>

        <div class="timeline">
            <div class="timeline-item reveal from-left d1">
                <div class="timeline-dot"></div>
                <p class="timeline-year">Phase 1 — Foundation</p>
                <h4>Database Schema & Core Architecture</h4>
                <p>Designed the relational database schema covering Users, Packages, Facilities, Employees, Trips, and Inventory. Established session-based authentication and multi-role access control.</p>
            </div>
            <div class="timeline-item accent reveal from-left d2">
                <div class="timeline-dot"></div>
                <p class="timeline-year">Phase 2 — Operations</p>
                <h4>Package Processing & Tracking</h4>
                <p>Built the full package lifecycle: create shipment, payment processing, scan-and-update, tracking history, and the public-facing tracking page with timeline visualization.</p>
            </div>
            <div class="timeline-item reveal from-left d3">
                <div class="timeline-dot"></div>
                <p class="timeline-year">Phase 3 — Logistics</p>
                <h4>Trip Management & Delivery Routes</h4>
                <p>Implemented trip creation (ground, air, delivery routes), driver dashboards, package loading, real-time delivery marking, and route history.</p>
            </div>
            <div class="timeline-item accent reveal from-left d4">
                <div class="timeline-dot"></div>
                <p class="timeline-year">Phase 4 — Commerce & Support</p>
                <h4>Shop, Inventory & Support Tickets</h4>
                <p>Added in-facility shop with sales processing, inventory management, stock alerts, the support ticket system with chat-style responses, and the customer portal.</p>
            </div>
            <div class="timeline-item reveal from-left d5">
                <div class="timeline-dot"></div>
                <p class="timeline-year">Phase 5 — Polish & QoL</p>
                <h4>Redesign, Consistency & Feature Refinements</h4>
                <p>Unified the design system across all pages, redesigned the homepage and about page, added the Pickup queue, Package Search & Scan combined page, and numerous QoL improvements across all role dashboards.</p>
            </div>
        </div>

        <!-- TEAM -->
        <div class="section-header reveal">
            <p class="section-eyebrow">The People</p>
            <h2>Meet the Team</h2>
            <p>Postal Pro was built by four students who turned a class project into a full-featured postal management platform.</p>
        </div>

        <div class="team-grid">
            <div class="team-card reveal d1">
                <div class="team-card-header">
                    <img src="https://github.com/SunnySQL.png" alt="Hung Liu" class="team-img">
                </div>
                <div class="team-card-body">
                    <h3>Hung Liu</h3>
                    <p>Lead Developer</p>
                    <div class="team-social">
                        <a href="https://github.com/SunnySQL" target="_blank" title="GitHub"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.374 0 0 5.373 0 12c0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23A11.509 11.509 0 0112 5.803c1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576C20.566 21.797 24 17.3 24 12c0-6.627-5.373-12-12-12z"/></svg></a>
                        <a href="https://linkedin.com/in/hung-liu0" target="_blank" title="LinkedIn"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg></a>
                    </div>
                </div>
            </div>

            <div class="team-card reveal d2">
                <div class="team-card-header">
                    <img src="https://github.com/MontyPithon.png" alt="Christopher Newsome" class="team-img">
                </div>
                <div class="team-card-body">
                    <h3>Christopher Newsome</h3>
                    <p>Developer</p>
                    <div class="team-social">
                        <a href="https://github.com/MontyPithon" target="_blank" title="GitHub"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.374 0 0 5.373 0 12c0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23A11.509 11.509 0 0112 5.803c1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576C20.566 21.797 24 17.3 24 12c0-6.627-5.373-12-12-12z"/></svg></a>
                    </div>
                </div>
            </div>

            <div class="team-card reveal d3">
                <div class="team-card-header">
                    <img src="https://github.com/AdaezeOwunna21.png" alt="Adaeze Victoria Owunna" class="team-img">
                </div>
                <div class="team-card-body">
                    <h3>Adaeze Victoria Owunna</h3>
                    <p>Developer</p>
                    <div class="team-social">
                        <a href="https://github.com/AdaezeOwunna21" target="_blank" title="GitHub"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.374 0 0 5.373 0 12c0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23A11.509 11.509 0 0112 5.803c1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576C20.566 21.797 24 17.3 24 12c0-6.627-5.373-12-12-12z"/></svg></a>
                    </div>
                </div>
            </div>

            <div class="team-card reveal d4">
                <div class="team-card-header">
                    <img src="https://github.com/ShayaanQ.png" alt="Shayaan Qureshi" class="team-img">
                </div>
                <div class="team-card-body">
                    <h3>Shayaan Qureshi</h3>
                    <p>Developer</p>
                    <div class="team-social">
                        <a href="https://github.com/ShayaanQ" target="_blank" title="GitHub"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.374 0 0 5.373 0 12c0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23A11.509 11.509 0 0112 5.803c1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576C20.566 21.797 24 17.3 24 12c0-6.627-5.373-12-12-12z"/></svg></a>
                        <a href="https://www.linkedin.com/in/shayaan-qureshi-257725251/" target="_blank" title="LinkedIn"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg></a>
                    </div>
                </div>
            </div>
        </div>

        <!-- CTA -->
        <div class="cta-band reveal">
            <h2>Ready to Get Started?</h2>
            <p>Create a free account to start shipping, tracking, and managing your postal needs with Postal Pro.</p>
            <div class="cta-buttons">
                <?php if(!$logged_in): ?>
                <a href="register.php" class="btn-primary">Create a Free Account</a>
                <a href="package/track.php" class="btn-outline">Track a Package</a>
                <?php else: ?>
                <a href="<?= $user_role == 'Customer' ? 'customer_dashboard.php' : 'employee_dashboard.php' ?>" class="btn-primary">Go to Dashboard</a>
                <a href="package/track.php" class="btn-outline">Track a Package</a>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /page-body -->

    <!-- FOOTER -->
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
                    <li><a href="shipping.php" style="display:inline-flex;align-items:center;gap:6px;">Shipping Options <i class="fas fa-arrow-right" style="font-size:0.7rem;"></i></a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Resources</h4>
                <ul>
                    <li><a href="faqs.php">FAQs</a></li>
                    <li><a href="about.php">About Postal Pro</a></li>
                    <li><a href="locations.php">Our Locations</a></li>
                    <li><a href="<?= $logged_in ? 'support.php' : 'login.php' ?>">Contact Support</a></li>
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
    </script>
</body>
</html>
