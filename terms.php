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
    <title>Terms of Service | POSTAL PRO</title>
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
        .toc {
            position: sticky; top: 80px;
            background: white; border-radius: 12px; padding: 22px 20px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.07);
        }
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
        .doc-notice { background: #fffbeb; border: 1.5px solid #fde68a; border-radius: 8px; padding: 14px 18px; margin-bottom: 32px; font-size: 0.84rem; color: #92400e; line-height: 1.6; }
        .doc-notice strong { color: #78350f; }

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
        .warning-box { background: #fff0f0; border-left: 3px solid #DA291C; border-radius: 0 8px 8px 0; padding: 12px 16px; margin: 14px 0; font-size: 0.86rem; color: #7f1d1d; line-height: 1.7; }

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
    <h1>Terms of Service</h1>
    <div class="meta">
        <span>&#128197; Effective: <?= $effective_date ?></span>
        <span>&#128260; Last Updated: <?= $last_updated ?></span>
        <span>&#128203; 18 Sections</span>
    </div>
</div>

<div class="doc-layout">

    <!-- SIDEBAR -->
    <aside class="toc">
        <h4>Contents</h4>
        <ol>
            <li><a href="#s1">Acceptance of Terms</a></li>
            <li><a href="#s2">Eligibility &amp; Account Registration</a></li>
            <li><a href="#s3">Description of Services</a></li>
            <li><a href="#s4">Prohibited Uses</a></li>
            <li><a href="#s5">Package Policies</a></li>
            <li><a href="#s6">Pricing &amp; Payment</a></li>
            <li><a href="#s7">Refunds &amp; Cancellations</a></li>
            <li><a href="#s8">Delivery Timelines</a></li>
            <li><a href="#s9">Lost, Damaged &amp; Delayed Packages</a></li>
            <li><a href="#s10">Postal Shop</a></li>
            <li><a href="#s11">Intellectual Property</a></li>
            <li><a href="#s12">Privacy</a></li>
            <li><a href="#s13">Third-Party Services</a></li>
            <li><a href="#s14">Disclaimer of Warranties</a></li>
            <li><a href="#s15">Limitation of Liability</a></li>
            <li><a href="#s16">Indemnification</a></li>
            <li><a href="#s17">Termination</a></li>
            <li><a href="#s18">Governing Law &amp; Disputes</a></li>
        </ol>
        <div class="toc-divider"></div>
        <a href="privacy.php" class="related-link">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            Privacy Policy
        </a>
    </aside>

    <!-- CONTENT -->
    <main class="doc-content">

        <div class="doc-notice">
            <strong>Please read these Terms of Service carefully before creating an account or using Postal Pro.</strong> By accessing or using our services, you confirm that you have read, understood, and agree to be bound by these Terms. If you do not agree, you may not use our services.
        </div>

        <div class="doc-section" id="s1">
            <h2><span class="section-num">1</span> Acceptance of Terms</h2>
            <p>These Terms of Service ("Terms," "Agreement") constitute a legally binding agreement between you ("User," "Customer," or "you") and Postal Pro, LLC ("Postal Pro," "we," "our," or "us"), governing your access to and use of the Postal Pro postal management platform, including all associated websites, mobile interfaces, physical facilities, and postal services (collectively, the "Services").</p>
            <p>By (a) registering for an account, (b) clicking "I agree" or any similar button, (c) submitting a package for shipment through our system, or (d) otherwise accessing or using any portion of the Services, you agree to these Terms in their entirety.</p>
            <p>These Terms apply to all users of the Services, including customers, employees, facility managers, and administrators. Additional role-specific terms may apply depending on your account type and will be presented at the time of onboarding.</p>
            <p>We reserve the right to modify these Terms at any time. Continued use of the Services following notice of changes constitutes acceptance of the revised Terms.</p>
        </div>

        <div class="doc-section" id="s2">
            <h2><span class="section-num">2</span> Eligibility &amp; Account Registration</h2>
            <h3>2.1 Eligibility</h3>
            <p>To create a Customer account, you must be at least 18 years of age and legally capable of entering into binding contracts under the laws of your jurisdiction. Use of our Services by persons under 18 is not permitted. By registering, you represent and warrant that you meet these eligibility requirements.</p>
            <h3>2.2 Account Creation</h3>
            <p>You must provide accurate, complete, and current information during the registration process, including your legal name, a valid email address, a U.S. phone number, and a valid U.S. mailing address. You agree to update this information promptly if it changes.</p>
            <h3>2.3 Account Security</h3>
            <p>You are solely responsible for maintaining the confidentiality of your account credentials and for all activity that occurs under your account. You must notify us immediately at <strong>support@postalpro.com</strong> if you suspect any unauthorized use of your account. Postal Pro will not be liable for any loss resulting from unauthorized use of your account where you failed to safeguard your credentials.</p>
            <h3>2.4 One Account Per Person</h3>
            <p>Each individual may maintain only one active Customer account. Creating duplicate accounts to circumvent restrictions, bans, or service limitations is a violation of these Terms and may result in permanent suspension of all associated accounts.</p>
            <h3>2.5 Employee &amp; Staff Accounts</h3>
            <p>Employee, Clerk, Driver, Manager, and Administrator accounts are created exclusively by Postal Pro personnel and are governed by the applicable employment agreement and internal Acceptable Use Policy in addition to these Terms.</p>
        </div>

        <div class="doc-section" id="s3">
            <h2><span class="section-num">3</span> Description of Services</h2>
            <p>Postal Pro operates a network of post offices, regional distribution hubs, and airport logistics facilities across the United States. Our Services include:</p>
            <ul>
                <li><strong>Package Shipment:</strong> Accepting, processing, routing, and delivering packages via Economy, Standard, and Express service tiers.</li>
                <li><strong>Real-Time Tracking:</strong> A web-based tracking system that provides status updates at each scan point throughout a package's journey.</li>
                <li><strong>Customer Portal:</strong> An online dashboard allowing customers to view shipment history, track packages, manage account settings, and access support.</li>
                <li><strong>Postal Shop:</strong> An in-system retail interface offering packaging supplies, stamps, and ancillary postal products for sale at participating locations.</li>
                <li><strong>Customer Support:</strong> A ticket-based support system for addressing inquiries, claims, and disputes related to shipments or account activity.</li>
                <li><strong>Facility Locator:</strong> A searchable directory of Postal Pro locations with addresses, facility types, and mapping links.</li>
            </ul>
            <p>All Services are subject to availability and may be modified, suspended, or discontinued at any time without prior notice. Postal Pro does not guarantee uninterrupted or error-free access to the Services.</p>
        </div>

        <div class="doc-section" id="s4">
            <h2><span class="section-num">4</span> Prohibited Uses</h2>
            <p>You agree not to use the Services to ship, store, process, or facilitate the transport of any item or information that:</p>
            <ul>
                <li>Is prohibited by federal, state, or local law, including but not limited to controlled substances, firearms (without proper licensing), and hazardous materials not disclosed at time of shipment.</li>
                <li>Contains counterfeit, stolen, or fraudulently obtained goods.</li>
                <li>Constitutes human trafficking or the exploitation of minors in any form.</li>
                <li>Is intended to defraud Postal Pro, its employees, or other customers.</li>
                <li>Contains live animals or perishable biological materials, unless explicitly pre-authorized by a Facility Manager.</li>
                <li>Infringes upon the intellectual property rights of any third party.</li>
            </ul>
            <p>You further agree not to:</p>
            <ul>
                <li>Attempt to gain unauthorized access to any portion of the Postal Pro platform, backend systems, or employee-facing tools.</li>
                <li>Use automated bots, scrapers, or data extraction tools against the platform without express written permission.</li>
                <li>Impersonate another person or entity, or misrepresent your affiliation with any person or organization.</li>
                <li>Circumvent, disable, or interfere with security or rate-limiting features of the Services.</li>
                <li>Submit false, misleading, or incomplete package information, including inaccurate declared weights or dimensions.</li>
                <li>Use another customer's tracking number to access or attempt to access package information you are not authorized to view.</li>
            </ul>
            <div class="warning-box">Violation of these prohibitions may result in immediate account suspension, forfeiture of any pending shipments without refund, referral to law enforcement, and civil or criminal liability.</div>
        </div>

        <div class="doc-section" id="s5">
            <h2><span class="section-num">5</span> Package Policies</h2>
            <h3>5.1 Accepted Items</h3>
            <p>Postal Pro accepts packages for shipment subject to size, weight, and content restrictions enforced at the time of drop-off. All packages must be properly packaged and sealed prior to tendering to a Postal Clerk. Postal Pro reserves the right to refuse any package that appears improperly packed, leaking, unlabeled, or in violation of these Terms.</p>
            <h3>5.2 Declared Information</h3>
            <p>You are required to accurately declare the contents, weight, and dimensions of your package at the time of shipment. Undeclared hazardous materials, fraudulent declarations, or materially inaccurate information may result in package seizure, additional charges, and account termination without refund.</p>
            <h3>5.3 Packaging Standards</h3>
            <p>Packages must meet minimum packaging standards to be accepted for shipment. Postal Pro is not responsible for damage caused by inadequate packaging, including insufficient padding, improper sealing, or use of compromised boxes. A Clerk may reject packages that do not meet these standards at their discretion.</p>
            <h3>5.4 Package Identification</h3>
            <p>Each accepted package is assigned a unique tracking number. This number is the sole means by which a Customer may track their shipment. Tracking numbers are non-transferable. Postal Pro does not display the contents of other customers' shipments to unauthorized parties under any circumstances.</p>
            <h3>5.5 Pickup Policy</h3>
            <p>Packages designated for customer pickup must be retrieved within 14 calendar days of the scheduled pickup notice. Unclaimed packages after this period may be returned to sender or disposed of at the discretion of the facility, with no obligation to provide a refund.</p>
        </div>

        <div class="doc-section" id="s6">
            <h2><span class="section-num">6</span> Pricing &amp; Payment</h2>
            <h3>6.1 Rate Calculation</h3>
            <p>Shipping rates are calculated at the point of service by a Postal Clerk based on three factors: (1) declared package weight in pounds, (2) package size classification (Small, Medium, Large, or Extra Large), and (3) selected service tier. Economy shipping applies a 20% discount to the base rate; Standard shipping is billed at the base rate; Express shipping applies a 70% surcharge to the base rate.</p>
            <h3>6.2 Accepted Payment Methods</h3>
            <p>Postal Pro accepts cash, credit card, and debit card payments at the counter. Online or prepaid accounts may support additional payment methods at the discretion of the facility. All prices are in U.S. Dollars (USD).</p>
            <h3>6.3 Taxes</h3>
            <p>Applicable federal, state, and local taxes will be applied to all transactions as required by law. The displayed postage price at checkout does not include taxes, which will be itemized on your final receipt.</p>
            <h3>6.4 Price Changes</h3>
            <p>Postal Pro reserves the right to change its pricing structure at any time. Changes will not affect transactions already completed. For recurring services, notice will be provided at least 30 days in advance.</p>
        </div>

        <div class="doc-section" id="s7">
            <h2><span class="section-num">7</span> Refunds &amp; Cancellations</h2>
            <h3>7.1 Pre-Pickup Cancellations</h3>
            <p>A shipment transaction may be cancelled and refunded in full by an authorized Clerk or Manager prior to the package being dispatched from its originating facility. Once a package has entered the transit network, it cannot be cancelled and no refund will be issued for the postage cost.</p>
            <h3>7.2 Lost or Damaged Packages</h3>
            <p>Claims for lost or damaged packages must be submitted via the Customer Support portal within 30 calendar days of the estimated delivery date. Approved claims may be eligible for a partial or full refund of postage, up to the declared value of the shipment, not to exceed the maximum liability limits described in Section 9.</p>
            <h3>7.3 No Refund for Refused or Returned Packages</h3>
            <p>Packages refused by the recipient or returned to sender due to an incorrect address provided by the sender are not eligible for a postage refund. A return shipping fee may apply.</p>
            <h3>7.4 Postal Shop Purchases</h3>
            <p>Retail purchases made through the Postal Shop are non-refundable once the transaction is completed, except where the item is defective or was received in error.</p>
        </div>

        <div class="doc-section" id="s8">
            <h2><span class="section-num">8</span> Delivery Timelines</h2>
            <p>Estimated transit times by service tier are as follows:</p>
            <table>
                <thead>
                    <tr><th>Service Tier</th><th>Estimated Transit</th><th>Price Modifier</th></tr>
                </thead>
                <tbody>
                    <tr><td>Economy</td><td>5–7 Business Days</td><td>−20% off base rate</td></tr>
                    <tr><td>Standard</td><td>3–5 Business Days</td><td>Base rate (no surcharge)</td></tr>
                    <tr><td>Express</td><td>1–2 Business Days</td><td>+70% over base rate</td></tr>
                </tbody>
            </table>
            <div class="highlight-box">All delivery timelines are estimates only and are not guarantees. Business days exclude Saturdays, Sundays, and federally recognized public holidays. Postal Pro is not liable for delays caused by weather events, natural disasters, labor disruptions, carrier errors, incorrect addresses, or other circumstances beyond our reasonable control.</div>
            <p>Delivery is considered complete upon the first attempted delivery scan at the destination address or upon confirmed customer pickup at the designated facility, whichever occurs first.</p>
        </div>

        <div class="doc-section" id="s9">
            <h2><span class="section-num">9</span> Lost, Damaged &amp; Delayed Packages</h2>
            <h3>9.1 Definition of Loss</h3>
            <p>A package is considered "lost" if it has not been scanned within the Postal Pro tracking network for more than 10 consecutive business days and cannot be located upon investigation by our operations team.</p>
            <h3>9.2 Maximum Liability</h3>
            <p>Postal Pro's maximum liability for any single lost or damaged package is limited to the lesser of: (a) the actual documented value of the package contents, (b) $100 USD for Economy shipments, (c) $250 USD for Standard shipments, or (d) $500 USD for Express shipments, unless additional declared-value insurance was purchased at the time of shipment.</p>
            <h3>9.3 Claim Process</h3>
            <p>To file a claim, the Customer must (1) submit a support ticket through the Customer Portal within 30 days of the estimated delivery date, (2) provide the tracking number and a description of contents, and (3) provide proof of value (receipt, invoice, or photograph) where applicable. Claims submitted without sufficient documentation may be denied.</p>
            <h3>9.4 Exclusions</h3>
            <p>Postal Pro is not liable for loss or damage resulting from: improper packaging by the sender; the inherent nature of the contents; delays caused by customs, government seizure, or acts of God; packages abandoned at a facility after the 14-day pickup window; or packages containing items prohibited under Section 4.</p>
        </div>

        <div class="doc-section" id="s10">
            <h2><span class="section-num">10</span> Postal Shop</h2>
            <p>The Postal Shop is an in-system retail feature available at participating facilities. Products listed in the shop are subject to availability and may vary by location. All prices are displayed inclusive of applicable product taxes at checkout.</p>
            <p>By completing a Postal Shop purchase, you authorize Postal Pro to charge the total amount to your selected payment method. Receipts are generated electronically and are accessible through your Customer Portal. Physical receipts may be requested from a Clerk at the time of purchase.</p>
            <p>Postal Pro reserves the right to limit quantities, refuse service, or discontinue products without notice. Promotional pricing is valid only during the specified promotional period and may not be combined with other offers unless expressly stated.</p>
        </div>

        <div class="doc-section" id="s11">
            <h2><span class="section-num">11</span> Intellectual Property</h2>
            <p>All content on the Postal Pro platform — including but not limited to the "POSTAL PRO" name and mark, logos, interface designs, text, data, software, and documentation — is the exclusive property of Postal Pro, LLC or its licensors and is protected under applicable U.S. and international intellectual property laws.</p>
            <p>You are granted a limited, non-exclusive, non-transferable, revocable license to access and use the Services for their intended personal or business purpose. You may not copy, reproduce, modify, distribute, reverse-engineer, create derivative works from, or otherwise exploit any portion of the Services without our prior written consent.</p>
            <p>Any feedback, suggestions, or ideas you submit regarding the Services may be used by Postal Pro without restriction or compensation to you.</p>
        </div>

        <div class="doc-section" id="s12">
            <h2><span class="section-num">12</span> Privacy</h2>
            <p>Your privacy is important to us. Our collection, use, and disclosure of personal information in connection with the Services is governed by our <a href="privacy.php" style="color:#004B87;font-weight:600;">Privacy Policy</a>, which is incorporated into these Terms by reference. By using the Services, you consent to the data practices described in the Privacy Policy.</p>
        </div>

        <div class="doc-section" id="s13">
            <h2><span class="section-num">13</span> Third-Party Services</h2>
            <p>The Services may contain links to third-party websites or services, including mapping providers (e.g., Google Maps) used in the Facility Locator feature. These third-party services are governed by their own terms of service and privacy policies. Postal Pro is not responsible for the content, practices, or availability of third-party services and does not endorse them.</p>
            <p>Address autocomplete functionality on the registration form uses the OpenStreetMap Nominatim API. Your use of this feature is also subject to the Nominatim Usage Policy.</p>
        </div>

        <div class="doc-section" id="s14">
            <h2><span class="section-num">14</span> Disclaimer of Warranties</h2>
            <p>THE SERVICES ARE PROVIDED "AS IS" AND "AS AVAILABLE" WITHOUT WARRANTIES OF ANY KIND, EITHER EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO IMPLIED WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE, TITLE, OR NON-INFRINGEMENT.</p>
            <p>POSTAL PRO DOES NOT WARRANT THAT: (A) THE SERVICES WILL BE UNINTERRUPTED OR ERROR-FREE; (B) DEFECTS WILL BE CORRECTED; (C) THE SERVICES OR THE SERVERS THAT MAKE THEM AVAILABLE ARE FREE OF VIRUSES OR OTHER HARMFUL COMPONENTS; OR (D) THE SERVICES WILL MEET YOUR REQUIREMENTS OR EXPECTATIONS.</p>
            <p>Some jurisdictions do not allow the exclusion of implied warranties, so the above exclusions may not apply to you to the extent prohibited by applicable law.</p>
        </div>

        <div class="doc-section" id="s15">
            <h2><span class="section-num">15</span> Limitation of Liability</h2>
            <p>TO THE FULLEST EXTENT PERMITTED BY LAW, POSTAL PRO, ITS OFFICERS, DIRECTORS, EMPLOYEES, CONTRACTORS, AND AGENTS SHALL NOT BE LIABLE FOR ANY INDIRECT, INCIDENTAL, SPECIAL, CONSEQUENTIAL, EXEMPLARY, OR PUNITIVE DAMAGES, INCLUDING BUT NOT LIMITED TO LOSS OF PROFITS, DATA, BUSINESS, OR GOODWILL, ARISING OUT OF OR IN CONNECTION WITH YOUR USE OF OR INABILITY TO USE THE SERVICES, EVEN IF POSTAL PRO HAS BEEN ADVISED OF THE POSSIBILITY OF SUCH DAMAGES.</p>
            <p>IN NO EVENT SHALL POSTAL PRO'S TOTAL CUMULATIVE LIABILITY TO YOU FOR ANY CLAIMS ARISING UNDER THESE TERMS EXCEED THE GREATER OF: (A) THE AMOUNT PAID BY YOU TO POSTAL PRO IN THE TWELVE (12) MONTHS PRECEDING THE CLAIM, OR (B) ONE HUNDRED U.S. DOLLARS ($100.00).</p>
            <div class="warning-box">The limitations in this section are a fundamental element of the basis of the bargain between you and Postal Pro. Without these limitations, Postal Pro could not provide the Services at the rates offered.</div>
        </div>

        <div class="doc-section" id="s16">
            <h2><span class="section-num">16</span> Indemnification</h2>
            <p>You agree to defend, indemnify, and hold harmless Postal Pro and its affiliates, officers, directors, employees, contractors, agents, licensors, and service providers from and against any claims, liabilities, damages, judgments, awards, losses, costs, expenses, or fees (including reasonable attorneys' fees) arising out of or relating to: (a) your violation of these Terms; (b) your use of the Services; (c) the content or accuracy of any information you provide; (d) your violation of any applicable law or third-party rights; or (e) any package you tender to Postal Pro for shipment.</p>
        </div>

        <div class="doc-section" id="s17">
            <h2><span class="section-num">17</span> Termination</h2>
            <h3>17.1 Termination by You</h3>
            <p>You may close your account at any time by contacting Customer Support. Upon closure, your access to the Customer Portal will be revoked. Any in-transit shipments at the time of account closure will continue to be processed and delivered.</p>
            <h3>17.2 Termination by Postal Pro</h3>
            <p>We reserve the right to suspend or permanently terminate your account, at our sole discretion, with or without notice, for any reason including but not limited to: violation of these Terms, fraudulent or abusive activity, failure to pay outstanding charges, or legal requirements. Upon termination, your license to use the Services immediately ends.</p>
            <h3>17.3 Effect of Termination</h3>
            <p>Sections 4, 9, 11, 14, 15, 16, and 18 of these Terms shall survive any termination or expiration of this Agreement and continue in full force and effect.</p>
        </div>

        <div class="doc-section" id="s18">
            <h2><span class="section-num">18</span> Governing Law &amp; Disputes</h2>
            <h3>18.1 Governing Law</h3>
            <p>These Terms and any disputes arising hereunder shall be governed by and construed in accordance with the laws of the State of Texas, without regard to its conflict of law principles.</p>
            <h3>18.2 Informal Resolution</h3>
            <p>Before initiating any formal dispute, you agree to first contact Postal Pro at <strong>legal@postalpro.com</strong> and attempt to resolve the dispute informally. We will attempt to resolve the dispute within 30 days of receiving your written notice.</p>
            <h3>18.3 Binding Arbitration</h3>
            <p>If informal resolution is unsuccessful, any dispute, claim, or controversy arising out of or relating to these Terms or the Services shall be resolved by binding arbitration administered by the American Arbitration Association (AAA) under its Consumer Arbitration Rules, rather than in court. You waive any right to a jury trial.</p>
            <h3>18.4 Class Action Waiver</h3>
            <p>YOU AGREE THAT ANY DISPUTE RESOLUTION PROCEEDINGS WILL BE CONDUCTED ONLY ON AN INDIVIDUAL BASIS AND NOT IN A CLASS, CONSOLIDATED, OR REPRESENTATIVE ACTION. If this waiver is found unenforceable, the entire arbitration clause shall be void.</p>
            <h3>18.5 Contact</h3>
            <p>Questions regarding these Terms may be directed to:<br>
            <strong>Postal Pro Legal</strong><br>
            Email: <a href="mailto:legal@postalpro.com" style="color:#004B87;">legal@postalpro.com</a><br>
            Mailing Address: Postal Pro, LLC — Legal Department, 1 Postal Way, Austin, TX 78701</p>
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
                <li><a href="terms.php" style="color:#fff;">Terms of Service</a></li>
                <li><a href="privacy.php">Privacy Policy</a></li>
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

    // Highlight active TOC section on scroll
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
