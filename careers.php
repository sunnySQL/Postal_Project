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
    <title>Careers | POSTAL PRO</title>
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
            padding: 100px 5% 64px; text-align: center; color: white;
        }
        .page-hero p.eyebrow { font-size: 0.75rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: rgba(255,255,255,0.6); margin-bottom: 10px; }
        .page-hero h1 { font-size: 2.6rem; font-weight: 800; margin-bottom: 14px; }
        .page-hero p.sub { color: rgba(255,255,255,0.72); font-size: 0.97rem; max-width: 540px; margin: 0 auto 28px; line-height: 1.7; }
        .hero-stats { display: flex; justify-content: center; gap: 40px; margin-top: 36px; flex-wrap: wrap; }
        .hero-stat { text-align: center; }
        .hero-stat .stat-val { font-size: 2rem; font-weight: 800; color: #fff; }
        .hero-stat .stat-label { font-size: 0.78rem; color: rgba(255,255,255,0.65); margin-top: 3px; }

        /* PAGE BODY */
        .page-body { max-width: 1060px; margin: 0 auto; padding: 56px 5% 64px; }

        /* SECTION HEADER */
        .section-header { text-align: center; margin-bottom: 36px; }
        .section-eyebrow { font-size: 0.72rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #DA291C; margin-bottom: 8px; }
        .section-header h2 { font-size: 1.8rem; font-weight: 800; color: #1a202c; margin-bottom: 10px; }
        .section-header p { color: #666; font-size: 0.95rem; max-width: 560px; margin: 0 auto; line-height: 1.65; }

        /* WHY WORK HERE */
        .perks-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 64px; }
        .perk-card {
            background: white; border-radius: 12px; padding: 28px 24px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            border-top: 3px solid #004B87;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .perk-card:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,0.1); }
        .perk-icon {
            width: 46px; height: 46px; border-radius: 10px;
            background: #eff6ff; color: #004B87;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 14px;
        }
        .perk-card h4 { font-size: 0.95rem; font-weight: 700; color: #1a202c; margin-bottom: 8px; }
        .perk-card p { font-size: 0.85rem; color: #555; line-height: 1.65; }

        /* JOB FILTER */
        .filter-bar { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 28px; align-items: center; }
        .filter-btn {
            padding: 7px 16px; border-radius: 20px; font-size: 0.82rem; font-weight: 600;
            border: 1.5px solid #d1d5db; background: white; color: #555; cursor: pointer; transition: all 0.18s;
        }
        .filter-btn:hover, .filter-btn.active { background: #004B87; color: white; border-color: #004B87; }
        .filter-btn.active-all { background: #1a202c; color: white; border-color: #1a202c; }

        /* JOB CARDS */
        .jobs-grid { display: flex; flex-direction: column; gap: 16px; margin-bottom: 64px; }
        .job-card {
            background: white; border-radius: 12px; padding: 24px 28px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            display: flex; align-items: center; gap: 24px;
            transition: transform 0.18s, box-shadow 0.18s;
            cursor: default;
        }
        .job-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.1); }
        .job-icon {
            width: 52px; height: 52px; border-radius: 12px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center; font-size: 1.3rem;
        }
        .job-icon.type-ops     { background: #eff6ff; color: #004B87; }
        .job-icon.type-delivery { background: #f0fdf4; color: #166534; }
        .job-icon.type-mgmt    { background: #faf5ff; color: #6d28d9; }
        .job-icon.type-support { background: #fff7ed; color: #c2410c; }
        .job-info { flex: 1; }
        .job-title { font-size: 1.05rem; font-weight: 700; color: #1a202c; margin-bottom: 4px; }
        .job-meta { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 6px; }
        .job-tag {
            font-size: 0.75rem; font-weight: 600; padding: 3px 10px; border-radius: 20px;
        }
        .tag-type    { background: #eff6ff; color: #1d4ed8; }
        .tag-ft      { background: #f0fdf4; color: #166534; }
        .tag-pt      { background: #fefce8; color: #a16207; }
        .tag-location { background: #f9fafb; color: #374151; border: 1px solid #e5e7eb; }
        .job-desc { font-size: 0.84rem; color: #666; margin-top: 6px; line-height: 1.6; max-width: 620px; }
        .apply-btn {
            padding: 10px 22px; background: #004B87; color: white;
            border-radius: 8px; font-size: 0.87rem; font-weight: 700;
            transition: background 0.2s; white-space: nowrap; flex-shrink: 0;
        }
        .apply-btn:hover { background: #003a6e; }
        .job-card.hidden { display: none; }

        /* APPLICATION FORM */
        .apply-section {
            background: white; border-radius: 14px; padding: 40px 40px 44px;
            box-shadow: 0 2px 16px rgba(0,0,0,0.07); margin-bottom: 64px;
        }
        .apply-section h3 { font-size: 1.35rem; font-weight: 800; color: #1a202c; margin-bottom: 6px; }
        .apply-section p.apply-sub { font-size: 0.88rem; color: #666; margin-bottom: 28px; line-height: 1.6; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group.full { grid-column: 1 / -1; }
        .form-group label { font-size: 0.82rem; font-weight: 700; color: #374151; }
        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 10px 14px; border: 1.5px solid #d1d5db; border-radius: 8px;
            font-size: 0.88rem; color: #333; background: #f9fafb; transition: border-color 0.2s;
            font-family: inherit;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus { outline: none; border-color: #004B87; background: white; }
        .form-group textarea { resize: vertical; min-height: 100px; }
        .form-group input[type="file"] { padding: 7px 12px; cursor: pointer; }
        .form-note { font-size: 0.76rem; color: #9ca3af; }
        .submit-row { margin-top: 22px; display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
        .btn-submit {
            padding: 12px 32px; background: #DA291C; color: white; border: none;
            border-radius: 8px; font-size: 0.92rem; font-weight: 700; cursor: pointer;
            transition: background 0.2s;
        }
        .btn-submit:hover { background: #b52218; }
        .submit-note { font-size: 0.8rem; color: #9ca3af; }
        .form-success {
            display: none; background: #f0fdf4; border: 1.5px solid #bbf7d0;
            border-radius: 10px; padding: 18px 22px; color: #166534;
            font-size: 0.9rem; font-weight: 600; margin-top: 18px;
            align-items: center; gap: 10px;
        }
        .form-success.show { display: flex; }

        /* CTA BAND */
        .cta-band { background: linear-gradient(135deg, #003a6e, #004B87); border-radius: 14px; padding: 48px 40px; text-align: center; color: white; }
        .cta-band h2 { font-size: 1.75rem; font-weight: 800; margin-bottom: 10px; }
        .cta-band p { color: rgba(255,255,255,0.75); font-size: 0.95rem; max-width: 500px; margin: 0 auto 28px; line-height: 1.6; }
        .cta-buttons { display: flex; justify-content: center; gap: 12px; flex-wrap: wrap; }
        .btn-primary { display: inline-flex; align-items: center; padding: 12px 26px; background: #DA291C; color: white; border-radius: 8px; font-weight: 700; font-size: 0.92rem; transition: background 0.2s; }
        .btn-primary:hover { background: #b52218; }
        .btn-outline { display: inline-flex; align-items: center; padding: 12px 26px; border: 2px solid rgba(255,255,255,0.4); color: white; border-radius: 8px; font-weight: 700; font-size: 0.92rem; transition: all 0.2s; }
        .btn-outline:hover { background: rgba(255,255,255,0.12); border-color: white; }

        /* FOOTER */
        footer { background: #1a2e4a; color: rgba(255,255,255,0.75); padding: 40px 5% 20px; }
        .footer-grid { max-width: 1100px; margin: 0 auto; display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 32px; padding-bottom: 32px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .footer-brand .logo { font-size: 1.4rem; display: inline-block; margin-bottom: 12px; }
        .footer-brand p { font-size: 0.85rem; line-height: 1.6; }
        .footer-col h4 { color: white; font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 14px; }
        .footer-col ul { list-style: none; }
        .footer-col ul li { margin-bottom: 8px; }
        .footer-col ul li a { color: rgba(255,255,255,0.65); font-size: 0.85rem; transition: color 0.2s; }
        .footer-col ul li a:hover { color: white; }
        .footer-bottom { max-width: 1100px; margin: 20px auto 0; display: flex; justify-content: space-between; align-items: center; font-size: 0.8rem; color: rgba(255,255,255,0.45); flex-wrap: wrap; gap: 8px; }
        .footer-knight { color: #DA291C; }

        /* MOBILE */
        @media (max-width: 768px) {
            nav ul { display: none; }
            .menu-toggle { display: block; }
            nav ul.open { display: flex; flex-direction: column; position: absolute; top: 60px; left: 0; right: 0; background: #003a6e; padding: 10px 5%; gap: 2px; }
            .page-hero h1 { font-size: 1.8rem; }
            .perks-grid { grid-template-columns: 1fr; }
            .job-card { flex-direction: column; align-items: flex-start; }
            .apply-btn { align-self: stretch; text-align: center; }
            .apply-section { padding: 28px 22px; }
            .form-grid { grid-template-columns: 1fr; }
            .hero-stats { gap: 24px; }
            .footer-grid { grid-template-columns: 1fr 1fr; gap: 24px; }
            .footer-brand { grid-column: 1 / -1; }
        }
    </style>
</head>
<body>

<!-- NAV -->
<?php include '_public_nav.php'; ?>

<!-- HERO -->
<div class="page-hero">
    <p class="eyebrow">We're Hiring</p>
    <h1>Join the Postal Pro Team</h1>
    <p class="sub">Be part of the network that keeps America's packages moving. We're looking for dedicated people across operations, delivery, and support.</p>
    <div class="hero-stats">
        <div class="hero-stat">
            <div class="stat-val">200+</div>
            <div class="stat-label">Facilities Nationwide</div>
        </div>
        <div class="hero-stat">
            <div class="stat-val">6</div>
            <div class="stat-label">Staff Role Types</div>
        </div>
        <div class="hero-stat">
            <div class="stat-val">Full &amp; Part Time</div>
            <div class="stat-label">Flexible Positions</div>
        </div>
    </div>
</div>

<div class="page-body">

    <!-- WHY WORK HERE -->
    <div class="section-header">
        <p class="section-eyebrow">Why Postal Pro</p>
        <h2>More Than Just a Job</h2>
        <p>We invest in our people the same way we invest in our network — with consistency, transparency, and a commitment to growth.</p>
    </div>

    <div class="perks-grid">
        <div class="perk-card">
            <div class="perk-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            </div>
            <h4>Stable Employment</h4>
            <p>Postal Pro operates across 200+ facilities. Our workforce is the backbone of a critical national service — your role matters every day.</p>
        </div>
        <div class="perk-card">
            <div class="perk-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            </div>
            <h4>Clear Career Paths</h4>
            <p>Start as a Clerk or Driver and grow into a Management role. We promote from within and provide the tools to help you advance.</p>
        </div>
        <div class="perk-card">
            <div class="perk-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <h4>Flexible Scheduling</h4>
            <p>Full-time and part-time roles available. We work with your schedule to find shifts that fit your life — not the other way around.</p>
        </div>
        <div class="perk-card">
            <div class="perk-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <h4>Supportive Culture</h4>
            <p>Our teams are tight-knit, collaborative, and committed to doing good work. You won't just be a number here.</p>
        </div>
        <div class="perk-card">
            <div class="perk-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13" rx="2"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
            </div>
            <h4>Competitive Pay</h4>
            <p>We offer competitive hourly and salaried compensation depending on role and experience, with regular performance reviews.</p>
        </div>
        <div class="perk-card">
            <div class="perk-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
            </div>
            <h4>Paid Training</h4>
            <p>No prior postal experience required for most roles. We provide structured onboarding and training — on the clock, from day one.</p>
        </div>
    </div>

    <!-- OPEN POSITIONS -->
    <div class="section-header">
        <p class="section-eyebrow">Open Positions</p>
        <h2>Current Openings</h2>
        <p>Browse available roles across our network. Filter by department to find the best fit.</p>
    </div>

    <div class="filter-bar">
        <button class="filter-btn active-all active" data-filter="all">All Roles</button>
        <button class="filter-btn" data-filter="ops">Operations</button>
        <button class="filter-btn" data-filter="delivery">Delivery</button>
        <button class="filter-btn" data-filter="mgmt">Management</button>
        <button class="filter-btn" data-filter="support">Support</button>
    </div>

    <div class="jobs-grid" id="jobsGrid">

        <!-- CLERK -->
        <div class="job-card" data-dept="ops">
            <div class="job-icon type-ops">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>
            </div>
            <div class="job-info">
                <div class="job-title">Postal Clerk</div>
                <p class="job-desc">Process inbound and outbound packages, assist customers at the counter, handle payments, and perform package scans. The front line of our operation.</p>
                <div class="job-meta">
                    <span class="job-tag tag-type">Operations</span>
                    <span class="job-tag tag-ft">Full Time</span>
                    <span class="job-tag tag-pt">Part Time</span>
                    <span class="job-tag tag-location">Multiple Locations</span>
                </div>
            </div>
            <a href="#apply-form" class="apply-btn">Apply Now</a>
        </div>

        <!-- DRIVER -->
        <div class="job-card" data-dept="delivery">
            <div class="job-icon type-delivery">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13" rx="2"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
            </div>
            <div class="job-info">
                <div class="job-title">Delivery Driver</div>
                <p class="job-desc">Execute assigned delivery routes, confirm package drop-offs, scan deliveries in real time, and uphold our on-time delivery standard in your territory.</p>
                <div class="job-meta">
                    <span class="job-tag tag-type">Delivery</span>
                    <span class="job-tag tag-ft">Full Time</span>
                    <span class="job-tag tag-location">Multiple Locations</span>
                </div>
            </div>
            <a href="#apply-form" class="apply-btn">Apply Now</a>
        </div>

        <!-- CUSTOMER SERVICE -->
        <div class="job-card" data-dept="support">
            <div class="job-icon type-support">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            </div>
            <div class="job-info">
                <div class="job-title">Customer Service Representative</div>
                <p class="job-desc">Respond to customer support tickets, assist with package inquiries, resolve delivery issues, and ensure every customer leaves satisfied.</p>
                <div class="job-meta">
                    <span class="job-tag tag-type">Support</span>
                    <span class="job-tag tag-ft">Full Time</span>
                    <span class="job-tag tag-pt">Part Time</span>
                    <span class="job-tag tag-location">Multiple Locations</span>
                </div>
            </div>
            <a href="#apply-form" class="apply-btn">Apply Now</a>
        </div>

        <!-- FACILITY MANAGER -->
        <div class="job-card" data-dept="mgmt">
            <div class="job-icon type-mgmt">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            </div>
            <div class="job-info">
                <div class="job-title">Facility Manager</div>
                <p class="job-desc">Oversee daily operations at a postal facility. Manage staff, coordinate trip assignments, review facility reports, and ensure performance targets are met.</p>
                <div class="job-meta">
                    <span class="job-tag tag-type">Management</span>
                    <span class="job-tag tag-ft">Full Time</span>
                    <span class="job-tag tag-location">Multiple Locations</span>
                </div>
            </div>
            <a href="#apply-form" class="apply-btn">Apply Now</a>
        </div>

        <!-- HUB OPERATIONS ASSOCIATE -->
        <div class="job-card" data-dept="ops">
            <div class="job-icon type-ops">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
            </div>
            <div class="job-info">
                <div class="job-title">Hub Operations Associate</div>
                <p class="job-desc">Sort and route packages at a regional distribution hub. Ensure packages are correctly scanned, staged, and dispatched on time to the next facility in the chain.</p>
                <div class="job-meta">
                    <span class="job-tag tag-type">Operations</span>
                    <span class="job-tag tag-ft">Full Time</span>
                    <span class="job-tag tag-pt">Part Time</span>
                    <span class="job-tag tag-location">Hub Facilities</span>
                </div>
            </div>
            <a href="#apply-form" class="apply-btn">Apply Now</a>
        </div>

        <!-- AIRPORT LOGISTICS COORDINATOR -->
        <div class="job-card" data-dept="delivery">
            <div class="job-icon type-delivery">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.8 19.2L16 11l3.5-3.5C21 6 21 4 19.5 2.5S18 2 17 3.5l-3.5 3.5L5 5l-1.5 1.5 6 3L7 13l-2 1 1 2 2-1 3 6z"/></svg>
            </div>
            <div class="job-info">
                <div class="job-title">Airport Logistics Coordinator</div>
                <p class="job-desc">Coordinate Express and air-eligible shipments through airport-based postal facilities. Liaise with airline cargo partners and maintain tight transit schedules.</p>
                <div class="job-meta">
                    <span class="job-tag tag-type">Delivery</span>
                    <span class="job-tag tag-ft">Full Time</span>
                    <span class="job-tag tag-location">Airport Facilities</span>
                </div>
            </div>
            <a href="#apply-form" class="apply-btn">Apply Now</a>
        </div>

    </div>

    <!-- APPLICATION FORM -->
    <div class="apply-section" id="apply-form">
        <h3>Apply for a Position</h3>
        <p class="apply-sub">Fill out the form below and a member of our team will be in touch within 3–5 business days. All fields marked with * are required.</p>

        <form id="careerForm" novalidate>
            <div class="form-grid">
                <div class="form-group">
                    <label for="cf_first">First Name *</label>
                    <input type="text" id="cf_first" placeholder="Jane" required>
                </div>
                <div class="form-group">
                    <label for="cf_last">Last Name *</label>
                    <input type="text" id="cf_last" placeholder="Smith" required>
                </div>
                <div class="form-group">
                    <label for="cf_email">Email Address *</label>
                    <input type="email" id="cf_email" placeholder="jane@email.com" required>
                </div>
                <div class="form-group">
                    <label for="cf_phone">Phone Number *</label>
                    <input type="tel" id="cf_phone" placeholder="555-555-5555" maxlength="12" required>
                </div>
                <div class="form-group">
                    <label for="cf_role">Position of Interest *</label>
                    <select id="cf_role" required>
                        <option value="">— Select a role —</option>
                        <option>Postal Clerk</option>
                        <option>Delivery Driver</option>
                        <option>Customer Service Representative</option>
                        <option>Facility Manager</option>
                        <option>Hub Operations Associate</option>
                        <option>Airport Logistics Coordinator</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="cf_avail">Availability</label>
                    <select id="cf_avail">
                        <option value="">— Select —</option>
                        <option>Full Time</option>
                        <option>Part Time</option>
                        <option>Either</option>
                    </select>
                </div>
                <div class="form-group full">
                    <label for="cf_location">Preferred Location / City</label>
                    <input type="text" id="cf_location" placeholder="e.g. Austin, TX">
                </div>
                <div class="form-group full">
                    <label for="cf_message">Tell us about yourself *</label>
                    <textarea id="cf_message" placeholder="Brief background, relevant experience, why you want to join Postal Pro..." required></textarea>
                </div>
                <div class="form-group full">
                    <label for="cf_resume">Resume / CV (optional)</label>
                    <input type="file" id="cf_resume" accept=".pdf,.doc,.docx">
                    <span class="form-note">Accepted formats: PDF, DOC, DOCX. Max 5 MB.</span>
                </div>
            </div>
            <div class="submit-row">
                <button type="submit" class="btn-submit">Submit Application</button>
                <span class="submit-note">We review every application and respond to all qualified candidates.</span>
            </div>
            <div class="form-success" id="formSuccess">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><polyline points="20 6 9 17 4 12"/></svg>
                Thanks for applying! We'll be in touch within 3–5 business days.
            </div>
        </form>
    </div>

    <!-- CTA -->
    <div class="cta-band">
        <h2>Not ready to apply yet?</h2>
        <p>Learn more about how Postal Pro works, explore our locations, or reach out with any questions before you apply.</p>
        <div class="cta-buttons">
            <a href="about.php" class="btn-primary">About Postal Pro</a>
            <a href="locations.php" class="btn-outline">Find a Location</a>
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
                <li><a href="shipping.php">Shipping Options</a></li>
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
    // Phone auto-format
    document.getElementById('cf_phone').addEventListener('input', function(e) {
        let v = e.target.value.replace(/\D/g, '').slice(0, 10);
        if (v.length >= 7) v = v.slice(0,3) + '-' + v.slice(3,6) + '-' + v.slice(6);
        else if (v.length >= 4) v = v.slice(0,3) + '-' + v.slice(3);
        e.target.value = v;
    });

    // Department filter
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active', 'active-all'));
            this.classList.add('active');
            if (this.dataset.filter === 'all') this.classList.add('active-all');

            const dept = this.dataset.filter;
            document.querySelectorAll('.job-card').forEach(card => {
                if (dept === 'all' || card.dataset.dept === dept) {
                    card.classList.remove('hidden');
                } else {
                    card.classList.add('hidden');
                }
            });
        });
    });

    // Apply Now smooth scroll + pre-select role
    document.querySelectorAll('.apply-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const jobTitle = this.closest('.job-card').querySelector('.job-title').textContent;
            const roleSelect = document.getElementById('cf_role');
            for (let opt of roleSelect.options) {
                if (opt.text === jobTitle) { roleSelect.value = opt.value; break; }
            }
            document.getElementById('apply-form').scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    // Form submit (client-side demo — no server submission)
    document.getElementById('careerForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const required = this.querySelectorAll('[required]');
        let valid = true;
        required.forEach(field => {
            if (!field.value.trim()) {
                field.style.borderColor = '#DA291C';
                valid = false;
            } else {
                field.style.borderColor = '';
            }
        });
        if (valid) {
            document.getElementById('formSuccess').classList.add('show');
            this.querySelectorAll('input, select, textarea').forEach(f => f.disabled = true);
            this.querySelector('.btn-submit').disabled = true;
            document.getElementById('apply-form').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
</script>
</body>
</html>
