<?php
session_start();
$logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$user_role = $logged_in ? $_SESSION['role'] : '';
$effective_date = 'January 1, 2024';
$last_updated   = 'March 1, 2025';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy | POSTAL PRO</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700;800&display=swap');

        *, *::before, *::after { margin: 0; padding: 0; font-family: 'Open Sans', sans-serif; box-sizing: border-box; }
        body { background: #f4f6f9; color: #333; min-height: 100vh; }
        a { text-decoration: none; }

        /* NAV */
        nav { display: flex; align-items: center; justify-content: space-between; background: #004B87; padding: 0 5%; height: 60px; position: fixed; width: 100%; top: 0; left: 0; z-index: 200; box-shadow: 0 2px 8px rgba(0,0,0,0.25); }
        .logo { font-weight: 800; font-size: 1.6rem; color: #fff; letter-spacing: -0.5px; transition: color 0.2s; }
        .logo:hover { color: #DA291C; }
        nav ul { display: flex; list-style: none; gap: 5px; }
        nav ul li a { color: #fff; font-size: 0.95rem; padding: 8px 14px; border-radius: 4px; transition: background 0.2s; display: block; }
        nav ul li a:hover { background: rgba(255,255,255,0.15); }
        nav ul li a.nav-cta { background: #DA291C; font-weight: 600; }
        nav ul li a.nav-cta:hover { background: #b52218; }
        .menu-toggle { display: none; font-size: 22px; color: #fff; cursor: pointer; }

        /* HERO */
        .page-hero { background: linear-gradient(135deg, #003a6e 0%, #004B87 55%, #0068b5 100%); padding: 90px 5% 52px; text-align: center; color: white; }
        .page-hero p.eyebrow { font-size: 0.75rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: rgba(255,255,255,0.6); margin-bottom: 10px; }
        .page-hero h1 { font-size: 2.4rem; font-weight: 800; margin-bottom: 12px; }
        .page-hero .meta { display: flex; justify-content: center; gap: 28px; margin-top: 18px; flex-wrap: wrap; }
        .page-hero .meta span { font-size: 0.8rem; color: rgba(255,255,255,0.65); display: flex; align-items: center; gap: 6px; }

        /* LAYOUT */
        .doc-layout { max-width: 1060px; margin: 0 auto; padding: 48px 5% 72px; display: grid; grid-template-columns: 220px 1fr; gap: 40px; align-items: start; }

        /* SIDEBAR TOC */
        .toc { position: sticky; top: 80px; background: white; border-radius: 12px; padding: 22px 20px; box-shadow: 0 2px 12px rgba(0,0,0,0.07); }
        .toc h4 { font-size: 0.72rem; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: #9ca3af; margin-bottom: 14px; }
        .toc ol { list-style: none; display: flex; flex-direction: column; gap: 6px; counter-reset: toc-counter; }
        .toc ol li { counter-increment: toc-counter; }
        .toc ol li a { display: flex; align-items: flex-start; gap: 8px; color: #555; font-size: 0.8rem; line-height: 1.45; transition: color 0.2s; }
        .toc ol li a::before { content: counter(toc-counter) "."; font-size: 0.72rem; color: #9ca3af; flex-shrink: 0; margin-top: 1px; }
        .toc ol li a:hover { color: #004B87; }
        .toc ol li a.active { color: #004B87; font-weight: 700; }
        .toc-divider { height: 1px; background: #f1f5f9; margin: 14px 0; }
        .related-link { display: flex; align-items: center; gap: 7px; color: #555; font-size: 0.8rem; padding: 8px 10px; border-radius: 6px; border: 1.5px solid #e5e7eb; transition: all 0.2s; margin-top: 4px; }
        .related-link:hover { border-color: #004B87; color: #004B87; }

        /* DOC CONTENT */
        .doc-content { background: white; border-radius: 14px; padding: 40px 44px; box-shadow: 0 2px 16px rgba(0,0,0,0.07); }
        .doc-notice { background: #eff6ff; border: 1.5px solid #bfdbfe; border-radius: 8px; padding: 14px 18px; margin-bottom: 32px; font-size: 0.84rem; color: #1e3a5f; line-height: 1.6; }
        .doc-notice strong { color: #1e3a5f; }

        .doc-section { margin-bottom: 40px; scroll-margin-top: 80px; }
        .doc-section h2 { font-size: 1.15rem; font-weight: 800; color: #1a202c; padding-bottom: 10px; border-bottom: 2px solid #f1f5f9; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; }
        .section-num { display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; background: #004B87; color: white; border-radius: 6px; font-size: 0.75rem; font-weight: 800; flex-shrink: 0; }
        .doc-section p { font-size: 0.88rem; color: #444; line-height: 1.8; margin-bottom: 12px; }
        .doc-section p:last-child { margin-bottom: 0; }
        .doc-section ul, .doc-section ol { padding-left: 22px; margin: 10px 0 14px; }
        .doc-section ul li, .doc-section ol li { font-size: 0.88rem; color: #444; line-height: 1.75; margin-bottom: 5px; }
        .doc-section h3 { font-size: 0.92rem; font-weight: 700; color: #1a202c; margin: 18px 0 8px; }
        .doc-section table { width: 100%; border-collapse: collapse; margin: 14px 0; font-size: 0.84rem; }
        .doc-section table th { background: #f8fafc; font-weight: 700; color: #374151; padding: 9px 14px; text-align: left; border: 1px solid #e5e7eb; }
        .doc-section table td { padding: 9px 14px; border: 1px solid #e5e7eb; color: #444; vertical-align: top; line-height: 1.55; }
        .doc-section table tr:nth-child(even) td { background: #fafafa; }
        .highlight-box { background: #eff6ff; border-left: 3px solid #004B87; border-radius: 0 8px 8px 0; padding: 12px 16px; margin: 14px 0; font-size: 0.86rem; color: #1e3a5f; line-height: 1.7; }
        .green-box { background: #f0fdf4; border-left: 3px solid #16a34a; border-radius: 0 8px 8px 0; padding: 12px 16px; margin: 14px 0; font-size: 0.86rem; color: #14532d; line-height: 1.7; }

        /* FOOTER */
        footer { background: #1a202c; color: #9ca3af; padding: 48px 5% 24px; }
        .footer-grid { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 32px; margin-bottom: 32px; }
        .footer-brand .logo { color: #fff; font-size: 1.3rem; }
        .footer-brand p { font-size: 0.82rem; color: #9ca3af; margin-top: 10px; line-height: 1.65; }
        .footer-col h4 { color: #fff; font-size: 0.82rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 14px; }
        .footer-col ul { list-style: none; display: flex; flex-direction: column; gap: 8px; }
        .footer-col ul li a { color: #9ca3af; font-size: 0.82rem; transition: color 0.2s; }
        .footer-col ul li a:hover { color: #fff; }
        .footer-bottom { border-top: 1px solid #2d3748; padding-top: 18px; display: flex; justify-content: space-between; align-items: center; font-size: 0.78rem; flex-wrap: wrap; gap: 8px; }

        @media (max-width: 860px) {
            .doc-layout { grid-template-columns: 1fr; }
            .toc { position: static; }
            nav ul { display: none; }
            .menu-toggle { display: block; }
            nav ul.open { display: flex; flex-direction: column; position: absolute; top: 60px; left: 0; right: 0; background: #003a6e; padding: 10px 5%; gap: 2px; }
            .doc-content { padding: 28px 22px; }
            .footer-grid { grid-template-columns: 1fr 1fr; gap: 24px; }
            .footer-brand { grid-column: 1 / -1; }
        }
    </style>
</head>
<body>

<nav>
    <a href="index.php" class="logo">POSTAL PRO</a>
    <ul id="navMenu">
        <li><a href="package/track.php">Track</a></li>
        <li><a href="locations.php">Locations</a></li>
        <li><a href="about.php">About</a></li>
        <?php if($logged_in): ?>
        <li><a href="<?= $user_role == 'Customer' ? 'customer_dashboard.php' : 'employee_dashboard.php' ?>">Dashboard</a></li>
        <li><a href="logout.php" class="nav-cta">Logout</a></li>
        <?php else: ?>
        <li><a href="login.php">Sign In</a></li>
        <li><a href="register.php" class="nav-cta">Get Started</a></li>
        <?php endif; ?>
    </ul>
    <i class="fas fa-bars menu-toggle" id="menuToggle"></i>
</nav>

<div class="page-hero">
    <p class="eyebrow">Legal</p>
    <h1>Privacy Policy</h1>
    <div class="meta">
        <span>&#128197; Effective: <?= $effective_date ?></span>
        <span>&#128260; Last Updated: <?= $last_updated ?></span>
        <span>&#128203; 13 Sections</span>
    </div>
</div>

<div class="doc-layout">

    <!-- SIDEBAR -->
    <aside class="toc">
        <h4>Contents</h4>
        <ol>
            <li><a href="#p1">Introduction</a></li>
            <li><a href="#p2">Information We Collect</a></li>
            <li><a href="#p3">How We Use Your Information</a></li>
            <li><a href="#p4">Legal Basis for Processing</a></li>
            <li><a href="#p5">How We Share Information</a></li>
            <li><a href="#p6">Data Retention</a></li>
            <li><a href="#p7">Data Security</a></li>
            <li><a href="#p8">Cookies &amp; Tracking</a></li>
            <li><a href="#p9">Your Rights &amp; Choices</a></li>
            <li><a href="#p10">Children's Privacy</a></li>
            <li><a href="#p11">Third-Party Services</a></li>
            <li><a href="#p12">Changes to This Policy</a></li>
            <li><a href="#p13">Contact Us</a></li>
        </ol>
        <div class="toc-divider"></div>
        <a href="terms.php" class="related-link">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            Terms of Service
        </a>
    </aside>

    <!-- CONTENT -->
    <main class="doc-content">

        <div class="doc-notice">
            <strong>Your privacy matters to us.</strong> This Privacy Policy explains what personal information Postal Pro collects, how we use and protect it, and the choices available to you. Please read it carefully alongside our <a href="terms.php" style="color:#004B87;font-weight:600;">Terms of Service</a>.
        </div>

        <div class="doc-section" id="p1">
            <h2><span class="section-num">1</span> Introduction</h2>
            <p>Postal Pro, LLC ("Postal Pro," "we," "our," or "us") is committed to protecting the privacy and security of the personal information you share with us. This Privacy Policy ("Policy") describes how we collect, use, store, disclose, and protect information obtained through our website, customer portal, mobile interfaces, and in-person postal services (collectively, the "Services").</p>
            <p>This Policy applies to all individuals who interact with our Services, including registered customers, visitors to our website, applicants through our careers portal, and any other parties whose personal data we process. It does not apply to the practices of third-party services, applications, or websites that may be accessible through links on our platform.</p>
            <p>By using the Services or submitting information to us, you acknowledge that you have read and understood this Privacy Policy and consent to the practices described herein.</p>
        </div>

        <div class="doc-section" id="p2">
            <h2><span class="section-num">2</span> Information We Collect</h2>
            <p>We collect several categories of information depending on how you interact with us:</p>

            <h3>2.1 Information You Provide Directly</h3>
            <ul>
                <li><strong>Account Registration:</strong> First and last name, email address, phone number (in xxx-xxx-xxxx format), and U.S. mailing address (street, city, state, ZIP code), and a hashed password.</li>
                <li><strong>Shipment Information:</strong> Sender and recipient names, delivery addresses, declared package weight and dimensions, selected service tier, and payment information processed at the counter.</li>
                <li><strong>Support Tickets:</strong> Written descriptions of issues, uploaded attachments, and communication history between you and our support staff.</li>
                <li><strong>Postal Shop Purchases:</strong> Items purchased, quantities, pricing, transaction IDs, and payment method type (e.g., cash, card).</li>
                <li><strong>Career Applications:</strong> Name, contact information, resume, cover letter, and the specific role applied for.</li>
                <li><strong>Communications:</strong> Any messages, feedback, or inquiries submitted through the platform or to our staff directly.</li>
            </ul>

            <h3>2.2 Information Collected Automatically</h3>
            <ul>
                <li><strong>Session Data:</strong> Login timestamps, session identifiers, and account access activity stored server-side.</li>
                <li><strong>Usage Data:</strong> Pages visited within the portal, features used, tracking searches performed (including tracking numbers entered), and interaction patterns within the interface.</li>
                <li><strong>Device &amp; Browser Information:</strong> IP address, browser type and version, operating system, device type, and referring URL, collected for security and analytics purposes.</li>
                <li><strong>Tracking Scan Logs:</strong> Each scan event generated during a package's journey through our network is recorded, including the facility identifier, scan type, timestamp, and responsible employee ID.</li>
            </ul>

            <h3>2.3 Information from Third Parties</h3>
            <ul>
                <li><strong>Address Autocomplete (Nominatim / OpenStreetMap):</strong> If you use the address autocomplete feature during registration, partial address strings are transmitted to the Nominatim API. We do not retain the raw API responses; only the final address you confirm is stored.</li>
                <li><strong>Mapping Services:</strong> "View on Map" and "Get Directions" links in the Facility Locator open Google Maps in a new window. Postal Pro does not transmit your personal data to Google Maps; however, Google's own privacy policy governs data collected by their services.</li>
            </ul>
        </div>

        <div class="doc-section" id="p3">
            <h2><span class="section-num">3</span> How We Use Your Information</h2>
            <p>We use the personal information we collect for the following purposes:</p>
            <table>
                <thead>
                    <tr><th>Purpose</th><th>Categories of Data Used</th></tr>
                </thead>
                <tbody>
                    <tr><td>Creating and managing your account</td><td>Name, email, phone, address, password hash</td></tr>
                    <tr><td>Processing and routing shipments</td><td>Sender/recipient info, package details, service tier</td></tr>
                    <tr><td>Providing real-time package tracking</td><td>Tracking number, scan logs, facility data</td></tr>
                    <tr><td>Processing payments at facilities</td><td>Transaction amount, payment method type, clerk ID</td></tr>
                    <tr><td>Responding to support tickets</td><td>Ticket content, account info, shipment history</td></tr>
                    <tr><td>Sending service notifications</td><td>Email address, tracking number, shipment status</td></tr>
                    <tr><td>Preventing fraud and unauthorized access</td><td>IP address, session data, login history</td></tr>
                    <tr><td>Generating internal operational reports</td><td>Aggregated, de-identified transaction and volume data</td></tr>
                    <tr><td>Improving the Services</td><td>Usage patterns, support ticket trends, error logs</td></tr>
                    <tr><td>Complying with legal obligations</td><td>Any data required by applicable law</td></tr>
                    <tr><td>Processing career applications</td><td>Application data submitted via the Careers page</td></tr>
                </tbody>
            </table>
            <p>We do not sell your personal information to third parties for marketing purposes.</p>
        </div>

        <div class="doc-section" id="p4">
            <h2><span class="section-num">4</span> Legal Basis for Processing</h2>
            <p>We process your personal information on the following legal grounds depending on the nature of the processing activity:</p>
            <ul>
                <li><strong>Performance of a Contract:</strong> Processing necessary to fulfill shipment services you have requested and to manage your account.</li>
                <li><strong>Legitimate Interests:</strong> Processing for fraud prevention, security monitoring, analytics, and improvement of the Services, where these interests are not overridden by your privacy rights.</li>
                <li><strong>Legal Obligation:</strong> Processing required to comply with applicable federal and state laws, regulatory requirements, or valid legal process.</li>
                <li><strong>Consent:</strong> Where we rely on your consent (e.g., for optional address autocomplete), you may withdraw consent at any time without affecting the lawfulness of processing before withdrawal.</li>
            </ul>
        </div>

        <div class="doc-section" id="p5">
            <h2><span class="section-num">5</span> How We Share Information</h2>
            <p>Postal Pro does not sell, rent, or trade your personal information. We may share information only in the following limited circumstances:</p>

            <h3>5.1 Within Postal Pro</h3>
            <p>Your information is accessible to Postal Pro employees, clerks, drivers, managers, and administrators only to the extent necessary to perform their role-specific duties. Role-based access controls strictly limit which data each employee category can view or modify.</p>

            <h3>5.2 Service Providers</h3>
            <p>We may share data with trusted third-party vendors who assist us in operating the Services under confidentiality agreements, including: database hosting providers, email delivery services, and payment processors. These vendors are contractually prohibited from using your data for purposes other than providing services to Postal Pro.</p>

            <h3>5.3 Legal Requirements</h3>
            <p>We may disclose your information if required to do so by law, subpoena, court order, or other governmental authority, or if we believe in good faith that disclosure is reasonably necessary to: (a) protect our legal rights; (b) prevent fraud or imminent harm; (c) enforce our Terms of Service; or (d) comply with a valid legal process.</p>

            <h3>5.4 Business Transfers</h3>
            <p>In the event of a merger, acquisition, reorganization, or sale of assets, your personal information may be transferred to the successor entity. We will provide notice before your information is transferred and becomes subject to a different privacy policy.</p>

            <h3>5.5 Aggregated &amp; De-Identified Data</h3>
            <p>We may share aggregated, anonymized, or de-identified data (from which individual identities have been removed) with partners or for research and reporting purposes. This data cannot reasonably be used to identify you.</p>

            <div class="green-box">We never share your tracking number, shipment details, or account information with other customers. Each customer may only view tracking information for packages associated with their own account.</div>
        </div>

        <div class="doc-section" id="p6">
            <h2><span class="section-num">6</span> Data Retention</h2>
            <p>We retain personal information for as long as necessary to fulfill the purposes for which it was collected, including legal, operational, and regulatory requirements:</p>
            <table>
                <thead>
                    <tr><th>Data Category</th><th>Retention Period</th></tr>
                </thead>
                <tbody>
                    <tr><td>Account information</td><td>Duration of account + 3 years after closure</td></tr>
                    <tr><td>Shipment records &amp; tracking logs</td><td>7 years from shipment date (compliance)</td></tr>
                    <tr><td>Payment transaction records</td><td>7 years (IRS and financial record requirements)</td></tr>
                    <tr><td>Support ticket history</td><td>3 years from ticket resolution date</td></tr>
                    <tr><td>Login &amp; session logs</td><td>90 days rolling</td></tr>
                    <tr><td>Career applications (not hired)</td><td>1 year from submission</td></tr>
                    <tr><td>De-identified analytics data</td><td>Indefinitely</td></tr>
                </tbody>
            </table>
            <p>When data is no longer required, it is securely deleted or anonymized in accordance with our internal data lifecycle policies.</p>
        </div>

        <div class="doc-section" id="p7">
            <h2><span class="section-num">7</span> Data Security</h2>
            <p>Postal Pro takes data security seriously and implements administrative, technical, and physical safeguards designed to protect your personal information from unauthorized access, alteration, disclosure, or destruction.</p>
            <h3>7.1 Technical Measures</h3>
            <ul>
                <li>All passwords are stored using one-way cryptographic hashing. Postal Pro employees cannot view your plain-text password.</li>
                <li>Database access is restricted to authorized personnel and application processes only, using role-based access controls and prepared statements to prevent SQL injection.</li>
                <li>Platform access logs are maintained for security auditing and anomaly detection.</li>
                <li>HTTPS encryption is used for all data in transit between your browser and our servers.</li>
            </ul>
            <h3>7.2 Organizational Measures</h3>
            <ul>
                <li>Employees receive privacy and security training as part of onboarding.</li>
                <li>Access to customer data is granted on a least-privilege basis and reviewed periodically.</li>
                <li>Internal audit logs record which employees viewed, created, or modified sensitive records.</li>
            </ul>
            <h3>7.3 Limitations</h3>
            <p>No method of electronic transmission or storage is 100% secure. While we strive to use commercially acceptable means to protect your personal information, we cannot guarantee its absolute security. In the event of a data breach affecting your rights and freedoms, we will notify you and applicable authorities as required by law.</p>
        </div>

        <div class="doc-section" id="p8">
            <h2><span class="section-num">8</span> Cookies &amp; Tracking Technologies</h2>
            <p>Postal Pro uses PHP server-side sessions (session cookies) to manage authenticated user sessions. These are temporary, first-party cookies that expire when you close your browser or log out. We do not use persistent tracking cookies, advertising cookies, or third-party analytics cookies.</p>
            <table>
                <thead>
                    <tr><th>Cookie / Technology</th><th>Type</th><th>Purpose</th><th>Duration</th></tr>
                </thead>
                <tbody>
                    <tr><td>PHPSESSID</td><td>Session Cookie (1st party)</td><td>Maintains your authenticated login session</td><td>Session (deleted on logout/close)</td></tr>
                    <tr><td>Google Fonts (CSS)</td><td>CDN Request</td><td>Loads the Open Sans typeface for page rendering</td><td>No cookie set by Postal Pro</td></tr>
                    <tr><td>Nominatim API (address autocomplete)</td><td>API Request</td><td>Fetches address suggestions on registration form</td><td>No cookie; request sent on input only</td></tr>
                </tbody>
            </table>
            <p>You may disable cookies in your browser settings; however, disabling session cookies will prevent you from logging into the platform.</p>
        </div>

        <div class="doc-section" id="p9">
            <h2><span class="section-num">9</span> Your Rights &amp; Choices</h2>
            <p>Depending on your location, you may have the following rights with respect to your personal information:</p>

            <h3>9.1 Right to Access</h3>
            <p>You may request a copy of the personal information we hold about you, including your account details, shipment history, and support ticket history, by submitting a request to <a href="mailto:privacy@postalpro.com" style="color:#004B87;">privacy@postalpro.com</a>.</p>

            <h3>9.2 Right to Correction</h3>
            <p>You may update your personal information at any time through the "Edit Profile" section of your Customer Dashboard. For corrections to shipment records or other non-editable fields, contact Customer Support.</p>

            <h3>9.3 Right to Deletion</h3>
            <p>You may request deletion of your account and personal information. We will honor deletion requests subject to retention requirements described in Section 6 (e.g., we must retain shipment and payment records for compliance purposes for up to 7 years).</p>

            <h3>9.4 Right to Portability</h3>
            <p>Upon request, we will provide your personal information in a structured, machine-readable format (CSV or JSON) where technically feasible.</p>

            <h3>9.5 Right to Object or Restrict Processing</h3>
            <p>You may object to or request restriction of processing in certain circumstances, such as where you contest the accuracy of data or object to processing based on our legitimate interests. Submit requests to <a href="mailto:privacy@postalpro.com" style="color:#004B87;">privacy@postalpro.com</a>.</p>

            <h3>9.6 California Residents (CCPA)</h3>
            <p>If you are a California resident, you have additional rights under the California Consumer Privacy Act (CCPA), including the right to know what personal information we collect and how it is used, the right to opt out of the sale of personal information (we do not sell personal information), and the right not to be discriminated against for exercising your privacy rights. To submit a CCPA request, contact us at <a href="mailto:privacy@postalpro.com" style="color:#004B87;">privacy@postalpro.com</a>.</p>

            <div class="highlight-box">We will respond to all verifiable privacy rights requests within 30 days. In complex cases, we may extend this period by an additional 60 days with prior notice.</div>
        </div>

        <div class="doc-section" id="p10">
            <h2><span class="section-num">10</span> Children's Privacy</h2>
            <p>The Services are not directed to individuals under the age of 18. We do not knowingly collect personal information from children. If you are a parent or guardian and believe that your child under 18 has provided personal information to us, please contact us immediately at <a href="mailto:privacy@postalpro.com" style="color:#004B87;">privacy@postalpro.com</a> and we will take steps to delete such information from our systems as promptly as possible.</p>
        </div>

        <div class="doc-section" id="p11">
            <h2><span class="section-num">11</span> Third-Party Services</h2>
            <p>Our platform integrates with the following third-party services, each governed by its own privacy policy:</p>
            <ul>
                <li><strong>Google Fonts:</strong> Fonts are loaded via Google's CDN. Google may log request metadata. See <a href="https://policies.google.com/privacy" target="_blank" style="color:#004B87;">Google Privacy Policy</a>.</li>
                <li><strong>Nominatim (OpenStreetMap):</strong> Used for address autocomplete. See <a href="https://wiki.osmfoundation.org/wiki/Privacy_Policy" target="_blank" style="color:#004B87;">OSM Foundation Privacy Policy</a>.</li>
                <li><strong>Google Maps:</strong> Used for "View on Map" and "Get Directions" links. Your use of these links is governed by <a href="https://policies.google.com/privacy" target="_blank" style="color:#004B87;">Google's Privacy Policy</a>.</li>
                <li><strong>Font Awesome (cdnjs):</strong> Icon assets loaded via the Cloudflare CDN. See <a href="https://www.cloudflare.com/privacypolicy/" target="_blank" style="color:#004B87;">Cloudflare Privacy Policy</a>.</li>
            </ul>
            <p>Postal Pro is not responsible for the privacy practices of these third parties.</p>
        </div>

        <div class="doc-section" id="p12">
            <h2><span class="section-num">12</span> Changes to This Policy</h2>
            <p>We reserve the right to update this Privacy Policy at any time to reflect changes in our practices, technology, legal requirements, or other factors. When we make material changes, we will update the "Last Updated" date at the top of this page and, where appropriate, provide additional notice (such as an in-portal notification or email to registered customers).</p>
            <p>Your continued use of the Services after the effective date of the revised Policy constitutes your acceptance of the changes. We encourage you to review this Policy periodically.</p>
        </div>

        <div class="doc-section" id="p13">
            <h2><span class="section-num">13</span> Contact Us</h2>
            <p>If you have any questions, concerns, or requests regarding this Privacy Policy or the personal information we hold about you, please contact us:</p>
            <p>
                <strong>Postal Pro Privacy Office</strong><br>
                Email: <a href="mailto:privacy@postalpro.com" style="color:#004B87;font-weight:600;">privacy@postalpro.com</a><br>
                General Inquiries: <a href="mailto:support@postalpro.com" style="color:#004B87;font-weight:600;">support@postalpro.com</a><br>
                Mailing Address: Postal Pro, LLC — Privacy Office, 1 Postal Way, Austin, TX 78701
            </p>
            <p>For data subject rights requests, include your full name, registered email address, and a description of your request. We will verify your identity before processing any rights request.</p>
        </div>

    </main>
</div>

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
            <h4>Legal</h4>
            <ul>
                <li><a href="terms.php">Terms of Service</a></li>
                <li><a href="privacy.php" style="color:#fff;">Privacy Policy</a></li>
                <li><a href="register.php">Create Account</a></li>
                <li><a href="login.php">Sign In</a></li>
            </ul>
        </div>
    </div>
    <div class="footer-bottom">
        <span>&copy; <?= date('Y') ?> Postal Service Management System. All rights reserved.</span>
        <span>Postal Pro, LLC &mdash; Austin, TX</span>
    </div>
</footer>

<script>
    document.getElementById('menuToggle').addEventListener('click', function() {
        document.getElementById('navMenu').classList.toggle('open');
    });

    const sections = document.querySelectorAll('.doc-section');
    const tocLinks = document.querySelectorAll('.toc a');
    window.addEventListener('scroll', () => {
        let current = '';
        sections.forEach(sec => {
            if (window.scrollY >= sec.offsetTop - 100) current = sec.id;
        });
        tocLinks.forEach(link => {
            link.classList.remove('active');
            if (link.getAttribute('href') === '#' + current) link.classList.add('active');
        });
    });
</script>
</body>
</html>
