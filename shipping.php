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
    <title>Shipping Options | POSTAL PRO</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700;800&display=swap');

        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        body, body * { font-family: 'Open Sans', sans-serif; }
        .fa, .fas, .far, .fab,
        .fa::before, .fas::before, .far::before, .fab::before {
            font-family: "Font Awesome 6 Free", "Font Awesome 6 Brands" !important;
        }
        body { background: #f4f6f9; color: #333; min-height: 100vh; }
        a { text-decoration: none; }

        /* NAV */
        nav {
            display: flex; align-items: center; justify-content: space-between;
            background: #004B87; padding: 0 5%; height: 60px;
            position: fixed; width: 100%; top: 0; left: 0; z-index: 200;
            box-shadow: 0 2px 8px rgba(0,0,0,0.25);
        }
        .logo { font-weight: 800; font-size: 1.6rem; color: #fff; letter-spacing: -0.5px; transition: color 0.2s; }
        .logo:hover { color: #DA291C; }
        nav ul { display: flex; list-style: none; gap: 5px; }
        nav ul li a { color: #fff; font-size: 0.95rem; padding: 8px 14px; border-radius: 4px; transition: background 0.2s; display: block; }
        nav ul li a:hover { background: rgba(255,255,255,0.15); }
        nav ul li a.nav-cta { background: #DA291C; font-weight: 600; }
        nav ul li a.nav-cta:hover { background: #b52218; }
        .menu-toggle { display: none; font-size: 22px; color: #fff; cursor: pointer; }

        /* HERO */
        .page-hero {
            background: linear-gradient(135deg, #003a6e 0%, #004B87 55%, #0068b5 100%);
            padding: 90px 5% 56px; text-align: center; color: white;
        }
        .page-hero p.eyebrow { font-size: 0.75rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: rgba(255,255,255,0.6); margin-bottom: 10px; }
        .page-hero h1 { font-size: 2.4rem; font-weight: 800; margin-bottom: 12px; }
        .page-hero p.sub { color: rgba(255,255,255,0.72); font-size: 0.97rem; max-width: 520px; margin: 0 auto; line-height: 1.65; }

        /* PAGE BODY */
        .page-body { max-width: 1060px; margin: 0 auto; padding: 52px 5% 60px; }

        /* SECTION HEADER */
        .section-header { text-align: center; margin-bottom: 36px; }
        .section-eyebrow { font-size: 0.72rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #DA291C; margin-bottom: 8px; }
        .section-header h2 { font-size: 1.65rem; font-weight: 800; color: #1a202c; }
        .section-header p { font-size: 0.93rem; color: #666; margin-top: 8px; line-height: 1.65; }

        /* SPEED CARDS */
        .speed-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 22px; margin-bottom: 56px; }

        .speed-card {
            background: white; border-radius: 14px;
            box-shadow: 0 2px 16px rgba(0,0,0,0.07);
            overflow: hidden; display: flex; flex-direction: column;
            transition: transform 0.2s, box-shadow 0.2s;
            border: 2px solid transparent;
        }
        .speed-card:hover { transform: translateY(-5px); box-shadow: 0 12px 32px rgba(0,0,0,0.11); }
        .speed-card.featured { border-color: #004B87; }

        .speed-card-top { padding: 28px 26px 22px; }
        .speed-badge {
            display: inline-flex; align-items: center; gap: 7px;
            font-size: 0.72rem; font-weight: 800; letter-spacing: 1.5px;
            text-transform: uppercase; padding: 5px 13px; border-radius: 999px; margin-bottom: 16px;
        }
        .badge-economy { background: #dcfce7; color: #166534; }
        .badge-standard { background: #dbeafe; color: #1d4ed8; }
        .badge-express  { background: #fee2e2; color: #991b1b; }

        .featured-tag {
            font-size: 0.7rem; font-weight: 700; background: #004B87; color: white;
            padding: 4px 12px; border-radius: 999px; margin-left: 8px;
            vertical-align: middle; letter-spacing: 0.5px;
        }

        .speed-card-top h3 { font-size: 1.3rem; font-weight: 800; color: #1a202c; margin-bottom: 8px; }
        .speed-card-top p  { font-size: 0.88rem; color: #555; line-height: 1.65; }

        .speed-price-row {
            display: flex; align-items: baseline; gap: 8px;
            margin: 18px 0 4px;
        }
        .speed-modifier {
            font-size: 1.5rem; font-weight: 800; color: #1a202c;
        }
        .speed-modifier.discount { color: #16a34a; }
        .speed-modifier.premium  { color: #DA291C; }
        .speed-modifier.base     { color: #004B87; }
        .speed-modifier-label    { font-size: 0.8rem; color: #888; }

        .speed-eta {
            display: flex; align-items: center; gap: 8px;
            background: #f8fafc; border-radius: 8px;
            padding: 10px 14px; margin-top: 14px; font-size: 0.87rem; color: #374151;
        }
        .speed-eta i { color: #004B87; font-size: 0.85rem; }

        .speed-divider { height: 1px; background: #f1f5f9; margin: 0 26px; }

        .speed-features { padding: 20px 26px 26px; flex: 1; }
        .speed-features ul { list-style: none; display: flex; flex-direction: column; gap: 10px; }
        .speed-features ul li { display: flex; align-items: flex-start; gap: 10px; font-size: 0.87rem; color: #444; line-height: 1.5; }
        .speed-features ul li .fi { font-size: 0.78rem; margin-top: 3px; flex-shrink: 0; font-weight: 700; }
        .speed-features ul li .fi.yes { color: #16a34a; }
        .speed-features ul li .fi.no  { color: #d1d5db; }

        /* COMPARISON TABLE */
        .compare-wrap { background: white; border-radius: 14px; box-shadow: 0 2px 16px rgba(0,0,0,0.07); overflow: hidden; margin-bottom: 56px; }
        .compare-table { width: 100%; border-collapse: collapse; }
        .compare-table th, .compare-table td { padding: 14px 20px; text-align: left; border-bottom: 1px solid #f1f5f9; font-size: 0.88rem; }
        .compare-table thead tr { background: #004B87; color: white; }
        .compare-table thead th { font-size: 0.82rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .compare-table thead th:first-child { width: 30%; }
        .compare-table tbody tr:last-child td { border-bottom: none; }
        .compare-table tbody tr:hover { background: #f8fafc; }
        .compare-table td:first-child { font-weight: 600; color: #374151; }
        .check { color: #16a34a; font-size: 1.1rem; font-weight: 700; }
        .cross { color: #d1d5db; font-size: 1.1rem; font-weight: 700; }
        .highlight { color: #004B87; font-weight: 700; }
        .col-economy  { background: rgba(220,252,231,0.15); }
        .col-standard { background: rgba(219,234,254,0.15); }
        .col-express  { background: rgba(254,226,226,0.15); }

        /* PRICING EXPLAINER */
        .pricing-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; margin-bottom: 56px; }
        .pricing-card { background: white; border-radius: 12px; padding: 24px 22px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
        .pricing-card-icon { width: 44px; height: 44px; border-radius: 10px; background: #eff6ff; color: #004B87; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; margin-bottom: 14px; }
        .pricing-card h4 { font-size: 0.95rem; font-weight: 700; color: #1a202c; margin-bottom: 8px; }
        .pricing-card p { font-size: 0.85rem; color: #555; line-height: 1.65; }

        /* CTA BAND */
        .cta-band { background: linear-gradient(135deg, #003a6e, #004B87); border-radius: 14px; padding: 48px 40px; text-align: center; color: white; }
        .cta-band h2 { font-size: 1.75rem; font-weight: 800; margin-bottom: 10px; }
        .cta-band p  { color: rgba(255,255,255,0.72); font-size: 0.95rem; margin-bottom: 26px; }
        .cta-buttons { display: flex; justify-content: center; gap: 14px; flex-wrap: wrap; }
        .btn-primary { background: #DA291C; color: white; padding: 12px 28px; border-radius: 7px; font-weight: 700; font-size: 0.93rem; transition: background 0.2s; }
        .btn-primary:hover { background: #b52218; }
        .btn-outline { background: transparent; color: white; padding: 12px 28px; border-radius: 7px; font-weight: 600; font-size: 0.93rem; border: 2px solid rgba(255,255,255,0.4); transition: all 0.2s; }
        .btn-outline:hover { border-color: white; background: rgba(255,255,255,0.08); }

        /* FOOTER */
        footer { background: #1a2e4a; color: rgba(255,255,255,0.5); padding: 40px 5% 20px; margin-top: 20px; }
        .footer-grid { max-width: 1060px; margin: 0 auto; display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 32px; padding-bottom: 28px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .footer-brand .logo { font-size: 1.3rem; display: inline-block; margin-bottom: 10px; }
        .footer-brand p { font-size: 0.83rem; line-height: 1.6; }
        .footer-col h4 { color: white; font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px; }
        .footer-col ul { list-style: none; }
        .footer-col ul li { margin-bottom: 7px; }
        .footer-col ul li a { color: rgba(255,255,255,0.6); font-size: 0.83rem; transition: color 0.2s; }
        .footer-col ul li a:hover { color: white; }
        .footer-bottom { max-width: 1060px; margin: 18px auto 0; display: flex; justify-content: space-between; align-items: center; font-size: 0.75rem; color: rgba(255,255,255,0.4); flex-wrap: wrap; gap: 6px; }
        .footer-knight { color: #DA291C; }

        /* RESPONSIVE */
        @media (max-width: 860px) {
            .speed-grid, .pricing-grid { grid-template-columns: 1fr; }
            .footer-grid { grid-template-columns: 1fr 1fr; }
            .compare-table { font-size: 0.82rem; }
            .compare-table th, .compare-table td { padding: 11px 14px; }
        }
        @media (max-width: 560px) {
            .page-hero h1 { font-size: 1.9rem; }
            .menu-toggle { display: block; }
            nav ul {
                position: fixed; top: 0; right: -260px; width: 260px; height: 100vh;
                background: #003366; flex-direction: column; padding-top: 70px;
                transition: right 0.3s; z-index: 150;
                box-shadow: -5px 0 15px rgba(0,0,0,0.15); gap: 0;
            }
            nav ul.show { right: 0; }
            nav ul li a { padding: 14px 20px; border-radius: 0; }
            .footer-grid { grid-template-columns: 1fr; }
            .compare-wrap { overflow-x: auto; }
        }
    </style>
</head>
<body>

<!-- NAV -->
<?php include '_public_nav.php'; ?>

<!-- HERO -->
<section class="page-hero">
    <p class="eyebrow">Delivery Speeds & Pricing</p>
    <h1>Shipping Options</h1>
    <p class="sub">Choose the speed that fits your timeline. Every option includes full real-time tracking from the moment your package is accepted.</p>
</section>

<div class="page-body">

    <!-- SPEED CARDS -->
    <div class="section-header" style="margin-top:0;">
        <p class="section-eyebrow">Compare Speeds</p>
        <h2>Pick Your Delivery Speed</h2>
        <p>All shipping options include a tracking number, scan history, and delivery confirmation.</p>
    </div>

    <div class="speed-grid">

        <!-- ECONOMY -->
        <div class="speed-card" id="economy">
            <div class="speed-card-top">
                <span class="speed-badge badge-economy">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:middle;margin-right:4px;"><path d="M17 8C8 10 5.9 16.17 3.82 22H5c.5-1.5 1-3 2-4c1.9 1 3.7 1 5.5 0C15 16.5 16 13 17 8z"/></svg>
                    Economy
                </span>
                <h3>Economy Shipping</h3>
                <p>Best value for non-urgent deliveries. Ideal for heavier items where cost matters more than speed.</p>
                <div class="speed-price-row">
                    <span class="speed-modifier discount">−20%</span>
                    <span class="speed-modifier-label">off base rate</span>
                </div>
                <div class="speed-eta">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <span><strong>5–7 Business Days</strong> estimated transit</span>
                </div>
            </div>
            <div class="speed-divider"></div>
            <div class="speed-features">
                <ul>
                    <li><span class="fi yes">&#10003;</span> Full real-time tracking included</li>
                    <li><span class="fi yes">&#10003;</span> Ground transport route</li>
                    <li><span class="fi yes">&#10003;</span> Delivery confirmation</li>
                    <li><span class="fi yes">&#10003;</span> Optional signature on delivery</li>
                    <li><span class="fi yes">&#10003;</span> All package sizes accepted</li>
                    <li><span class="fi no">&#10005;</span> No air transport</li>
                    <li><span class="fi no">&#10005;</span> No priority processing</li>
                </ul>
            </div>
        </div>

        <!-- STANDARD -->
        <div class="speed-card featured" id="standard">
            <div class="speed-card-top">
                <span class="speed-badge badge-standard">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:middle;margin-right:4px;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    Standard
                </span>
                <span class="featured-tag">Most Popular</span>
                <h3 style="margin-top:12px;">Standard Shipping</h3>
                <p>Our most-used option. A solid balance of speed and value for everyday shipments.</p>
                <div class="speed-price-row">
                    <span class="speed-modifier base">Base Rate</span>
                    <span class="speed-modifier-label">no surcharge</span>
                </div>
                <div class="speed-eta">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <span><strong>3–5 Business Days</strong> estimated transit</span>
                </div>
            </div>
            <div class="speed-divider"></div>
            <div class="speed-features">
                <ul>
                    <li><span class="fi yes">&#10003;</span> Full real-time tracking included</li>
                    <li><span class="fi yes">&#10003;</span> Ground transport route</li>
                    <li><span class="fi yes">&#10003;</span> Delivery confirmation</li>
                    <li><span class="fi yes">&#10003;</span> Optional signature on delivery</li>
                    <li><span class="fi yes">&#10003;</span> All package sizes accepted</li>
                    <li><span class="fi yes">&#10003;</span> Priority over Economy at hubs</li>
                    <li><span class="fi no">&#10005;</span> No air transport</li>
                </ul>
            </div>
        </div>

        <!-- EXPRESS -->
        <div class="speed-card" id="express">
            <div class="speed-card-top">
                <span class="speed-badge badge-express">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:middle;margin-right:4px;"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                    Express
                </span>
                <h3>Express Shipping</h3>
                <p>When time is critical. Priority handling at every scan point — guaranteed fastest available transit.</p>
                <div class="speed-price-row">
                    <span class="speed-modifier premium">+70%</span>
                    <span class="speed-modifier-label">premium over base rate</span>
                </div>
                <div class="speed-eta" style="background:#fff0f0;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="flex-shrink:0;color:#DA291C;"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                    <span><strong>1–2 Business Days</strong> estimated transit</span>
                </div>
            </div>
            <div class="speed-divider"></div>
            <div class="speed-features">
                <ul>
                    <li><span class="fi yes">&#10003;</span> Full real-time tracking included</li>
                    <li><span class="fi yes">&#10003;</span> Air transport eligible</li>
                    <li><span class="fi yes">&#10003;</span> Delivery confirmation</li>
                    <li><span class="fi yes">&#10003;</span> Optional signature on delivery</li>
                    <li><span class="fi yes">&#10003;</span> All package sizes accepted</li>
                    <li><span class="fi yes">&#10003;</span> Priority processing at every hub</li>
                    <li><span class="fi yes">&#10003;</span> Fastest guaranteed option</li>
                </ul>
            </div>
        </div>

    </div>

    <!-- COMPARISON TABLE -->
    <div class="section-header">
        <p class="section-eyebrow">Side by Side</p>
        <h2>Full Comparison</h2>
        <p>Everything you need to choose the right option at a glance.</p>
    </div>

    <div class="compare-wrap">
        <table class="compare-table">
            <thead>
                <tr>
                    <th>Feature</th>
                    <th class="col-economy">Economy</th>
                    <th class="col-standard">Standard</th>
                    <th class="col-express">Express</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Estimated Transit</td>
                    <td class="col-economy">5–7 Business Days</td>
                    <td class="col-standard">3–5 Business Days</td>
                    <td class="col-express highlight">1–2 Business Days</td>
                </tr>
                <tr>
                    <td>Price Modifier</td>
                    <td class="col-economy highlight" style="color:#16a34a;">−20% (discount)</td>
                    <td class="col-standard highlight" style="color:#004B87;">Base rate</td>
                    <td class="col-express highlight" style="color:#DA291C;">+70% (premium)</td>
                </tr>
                <tr>
                    <td>Real-Time Tracking</td>
                    <td class="col-economy"><span class="check">&#10003;</span></td>
                    <td class="col-standard"><span class="check">&#10003;</span></td>
                    <td class="col-express"><span class="check">&#10003;</span></td>
                </tr>
                <tr>
                    <td>Delivery Confirmation</td>
                    <td class="col-economy"><span class="check">&#10003;</span></td>
                    <td class="col-standard"><span class="check">&#10003;</span></td>
                    <td class="col-express"><span class="check">&#10003;</span></td>
                </tr>
                <tr>
                    <td>Signature Option</td>
                    <td class="col-economy"><span class="check">&#10003;</span></td>
                    <td class="col-standard"><span class="check">&#10003;</span></td>
                    <td class="col-express"><span class="check">&#10003;</span></td>
                </tr>
                <tr>
                    <td>Air Transport Eligible</td>
                    <td class="col-economy"><span class="cross">&#10005;</span></td>
                    <td class="col-standard"><span class="cross">&#10005;</span></td>
                    <td class="col-express"><span class="check">&#10003;</span></td>
                </tr>
                <tr>
                    <td>Priority Hub Processing</td>
                    <td class="col-economy"><span class="cross">&#10005;</span></td>
                    <td class="col-standard">Over Economy</td>
                    <td class="col-express highlight">Highest Priority</td>
                </tr>
                <tr>
                    <td>Best For</td>
                    <td class="col-economy">Non-urgent, budget-conscious</td>
                    <td class="col-standard">Everyday shipments</td>
                    <td class="col-express">Time-critical deliveries</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- HOW PRICING WORKS -->
    <div class="section-header">
        <p class="section-eyebrow">Transparent Pricing</p>
        <h2>How Your Rate Is Calculated</h2>
        <p>Your final postage is calculated at the counter when a clerk creates your shipment. Three factors drive the price:</p>
    </div>

    <div class="pricing-grid">
        <div class="pricing-card">
            <div class="pricing-card-icon">
                <!-- weight/scale icon -->
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 3a1 1 0 1 0 0-2 1 1 0 0 0 0 2z"/><path d="M6.5 8h11l-1.5 9H8L6.5 8z"/><path d="M4 21h16"/><path d="M6.5 8L5 4"/><path d="M17.5 8L19 4"/><line x1="12" y1="3" x2="12" y2="8"/>
                </svg>
            </div>
            <h4>Weight</h4>
            <p>Heavier packages cost more to transport. The base rate scales with the declared weight of your package in pounds.</p>
        </div>
        <div class="pricing-card">
            <div class="pricing-card-icon">
                <!-- box/cube icon -->
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>
                </svg>
            </div>
            <h4>Size</h4>
            <p>Package dimensions affect how much space it occupies on a vehicle or aircraft. Larger sizes carry a higher base multiplier.</p>
        </div>
        <div class="pricing-card">
            <div class="pricing-card-icon">
                <!-- speed/gauge icon -->
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2a10 10 0 1 0 10 10"/><polyline points="12 6 12 12 16 14"/><line x1="22" y1="2" x2="16" y2="8"/><polyline points="17 2 22 2 22 7"/>
                </svg>
            </div>
            <h4>Shipping Speed</h4>
            <p>Economy gives you a 20% discount off the base rate. Standard ships at the base rate. Express adds a 70% premium for priority handling.</p>
        </div>
    </div>

    <!-- CTA -->
    <div class="cta-band">
        <h2>Ready to ship?</h2>
        <p>Visit any Postal Pro location and a clerk will walk you through the whole process — from package details to payment — in minutes.</p>
        <div class="cta-buttons">
            <?php if(!$logged_in): ?>
            <a href="register.php" class="btn-primary"><i class="fas fa-user-plus" style="margin-right:7px;"></i> Create a Free Account</a>
            <a href="locations.php" class="btn-outline"><i class="fas fa-map-marker-alt" style="margin-right:7px;"></i> Find a Location</a>
            <?php elseif($user_role == 'Customer'): ?>
            <a href="sendpackage.php" class="btn-primary"><i class="fas fa-truck-fast" style="margin-right:7px;"></i> Create a Shipment</a>
            <a href="locations.php" class="btn-outline"><i class="fas fa-map-marker-alt" style="margin-right:7px;"></i> Find a Location</a>
            <?php else: ?>
            <a href="package/new_package.php" class="btn-primary"><i class="fas fa-truck-fast" style="margin-right:7px;"></i> Create a Shipment</a>
            <a href="locations.php" class="btn-outline"><i class="fas fa-map-marker-alt" style="margin-right:7px;"></i> Find a Location</a>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- FOOTER -->
<footer>
    <div class="footer-grid">
        <div class="footer-brand">
            <a href="index.php" class="logo">POSTAL PRO</a>
            <p>America's trusted postal management network — delivering reliability and transparency to every doorstep.</p>
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
</script>
</body>
</html>
