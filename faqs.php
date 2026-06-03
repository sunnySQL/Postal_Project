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
    <title>FAQs | POSTAL PRO</title>
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
            padding: 90px 5% 50px; text-align: center; color: white;
        }
        .page-hero p.eyebrow { font-size: 0.75rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: rgba(255,255,255,0.6); margin-bottom: 10px; }
        .page-hero h1 { font-size: 2.4rem; font-weight: 800; margin-bottom: 10px; }
        .page-hero p.sub { color: rgba(255,255,255,0.72); font-size: 0.97rem; max-width: 520px; margin: 0 auto 28px; line-height: 1.65; }

        /* SEARCH IN HERO */
        .faq-search-wrap {
            max-width: 500px; margin: 0 auto; position: relative;
        }
        .faq-search-wrap i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: 0.95rem; }
        .faq-search-wrap input {
            width: 100%; padding: 14px 16px 14px 44px;
            border: none; border-radius: 10px;
            font-size: 0.97rem; outline: none;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            color: #1a202c;
        }

        /* PAGE BODY */
        .page-body { max-width: 860px; margin: 0 auto; padding: 48px 5% 60px; }

        /* CATEGORY TABS */
        .cat-tabs {
            display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 32px;
        }
        .cat-tab {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 9px 18px; border-radius: 999px;
            font-size: 0.85rem; font-weight: 600; cursor: pointer;
            border: 1.5px solid #e5e7eb; background: white; color: #374151;
            transition: all 0.2s;
        }
        .cat-tab:hover { border-color: #004B87; color: #004B87; }
        .cat-tab.active { background: #004B87; color: white; border-color: #004B87; }
        .cat-tab i { font-size: 0.8rem; }

        /* SECTION */
        .faq-section { margin-bottom: 40px; }
        .faq-section-title {
            font-size: 0.75rem; font-weight: 700; letter-spacing: 2px;
            text-transform: uppercase; color: #DA291C;
            display: flex; align-items: center; gap: 10px;
            margin-bottom: 16px;
        }
        .faq-section-title::after { content: ''; flex: 1; height: 1px; background: #f1f5f9; }

        /* ACCORDION */
        .faq-item {
            background: white; border-radius: 10px;
            box-shadow: 0 1px 8px rgba(0,0,0,0.06);
            margin-bottom: 10px; overflow: hidden;
            transition: box-shadow 0.2s;
        }
        .faq-item:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.09); }
        .faq-question {
            display: flex; justify-content: space-between; align-items: center;
            padding: 18px 22px; cursor: pointer;
            user-select: none; gap: 14px;
        }
        .faq-question span {
            font-size: 0.95rem; font-weight: 600; color: #1a202c; flex: 1; line-height: 1.45;
        }
        .faq-icon {
            width: 28px; height: 28px; border-radius: 50%;
            background: #f0f5ff; color: #004B87;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.75rem; flex-shrink: 0;
            transition: background 0.2s, transform 0.25s;
        }
        .faq-item.open .faq-icon { background: #004B87; color: white; transform: rotate(45deg); }
        .faq-answer {
            max-height: 0; overflow: hidden;
            transition: max-height 0.3s ease, padding 0.3s ease;
            padding: 0 22px;
        }
        .faq-item.open .faq-answer { max-height: 400px; padding: 0 22px 18px; }
        .faq-answer p { font-size: 0.9rem; color: #555; line-height: 1.75; margin-bottom: 10px; }
        .faq-answer p:last-child { margin-bottom: 0; }
        .faq-answer a { color: #004B87; font-weight: 600; transition: color 0.2s; }
        .faq-answer a:hover { color: #DA291C; }
        .faq-answer ul { padding-left: 18px; margin-top: 6px; }
        .faq-answer ul li { font-size: 0.88rem; color: #555; line-height: 1.7; }

        /* EMPTY STATE */
        .empty-state { text-align: center; padding: 50px 20px; display: none; }
        .empty-state i { font-size: 2.5rem; color: #d1d5db; display: block; margin-bottom: 14px; }
        .empty-state p { color: #aaa; font-size: 0.93rem; }

        /* CONTACT CTA */
        .contact-cta {
            background: linear-gradient(135deg, #003a6e, #004B87);
            border-radius: 12px; padding: 40px; text-align: center; color: white; margin-top: 48px;
        }
        .contact-cta h3 { font-size: 1.4rem; font-weight: 800; margin-bottom: 8px; }
        .contact-cta p  { color: rgba(255,255,255,0.75); font-size: 0.93rem; margin-bottom: 22px; }
        .cta-buttons { display: flex; justify-content: center; gap: 12px; flex-wrap: wrap; }
        .btn-primary { background: #DA291C; color: white; padding: 11px 26px; border-radius: 7px; font-weight: 700; font-size: 0.9rem; transition: background 0.2s; }
        .btn-primary:hover { background: #b52218; }
        .btn-outline { background: transparent; color: white; padding: 11px 26px; border-radius: 7px; font-weight: 600; font-size: 0.9rem; border: 2px solid rgba(255,255,255,0.4); transition: border-color 0.2s, background 0.2s; }
        .btn-outline:hover { border-color: white; background: rgba(255,255,255,0.08); }

        /* FOOTER */
        footer { background: #1a2e4a; color: rgba(255,255,255,0.5); padding: 40px 5% 20px; }
        .footer-grid { max-width: 860px; margin: 0 auto; display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 32px; padding-bottom: 28px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .footer-brand .logo { font-size: 1.3rem; display: inline-block; margin-bottom: 10px; }
        .footer-brand p { font-size: 0.83rem; line-height: 1.6; }
        .footer-col h4 { color: white; font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px; }
        .footer-col ul { list-style: none; }
        .footer-col ul li { margin-bottom: 7px; }
        .footer-col ul li a { color: rgba(255,255,255,0.6); font-size: 0.83rem; transition: color 0.2s; }
        .footer-col ul li a:hover { color: white; }
        .footer-bottom { max-width: 860px; margin: 18px auto 0; display: flex; justify-content: space-between; align-items: center; font-size: 0.75rem; color: rgba(255,255,255,0.4); flex-wrap: wrap; gap: 6px; }
        .footer-knight { color: #DA291C; }

        /* RESPONSIVE */
        @media (max-width: 700px) {
            .footer-grid { grid-template-columns: 1fr 1fr; }
            .page-hero h1 { font-size: 1.9rem; }
        }
        @media (max-width: 480px) {
            .footer-grid { grid-template-columns: 1fr; }
            .menu-toggle { display: block; }
            nav ul {
                position: fixed; top: 0; right: -260px; width: 260px; height: 100vh;
                background: #003366; flex-direction: column; padding-top: 70px;
                transition: right 0.3s; z-index: 150; box-shadow: -5px 0 15px rgba(0,0,0,0.15); gap: 0;
            }
            nav ul.show { right: 0; }
            nav ul li a { padding: 14px 20px; border-radius: 0; }
        }
    </style>
</head>
<body>

<!-- NAV -->
<?php include '_public_nav.php'; ?>

<!-- HERO -->
<section class="page-hero">
    <p class="eyebrow">Help Center</p>
    <h1>Frequently Asked Questions</h1>
    <p class="sub">Find quick answers about tracking, shipping, your account, and more.</p>
    <div class="faq-search-wrap">
        <i class="fas fa-search"></i>
        <input type="text" id="faqSearch" placeholder="Search questions…" autocomplete="off">
    </div>
</section>

<div class="page-body">

    <!-- CATEGORY TABS -->
    <div class="cat-tabs" id="catTabs">
        <button class="cat-tab active" data-cat="all"><i class="fas fa-list"></i> All</button>
        <button class="cat-tab" data-cat="tracking"><i class="fas fa-map-marker-alt"></i> Tracking</button>
        <button class="cat-tab" data-cat="shipping"><i class="fas fa-shipping-fast"></i> Shipping</button>
        <button class="cat-tab" data-cat="account"><i class="fas fa-user"></i> Account</button>
        <button class="cat-tab" data-cat="support"><i class="fas fa-headset"></i> Support</button>
        <button class="cat-tab" data-cat="payment"><i class="fas fa-credit-card"></i> Payment</button>
    </div>

    <div id="emptyState" class="empty-state">
        <i class="fas fa-magnifying-glass"></i>
        <p>No questions match your search. Try different keywords or <a href="#" style="color:#004B87;" onclick="clearSearch()">clear the search</a>.</p>
    </div>

    <!-- TRACKING -->
    <div class="faq-section" data-cat="tracking">
        <p class="faq-section-title"><i class="fas fa-map-marker-alt"></i> Tracking & Delivery</p>

        <div class="faq-item" data-q="how do i track my package">
            <div class="faq-question">
                <span>How do I track my package?</span>
                <div class="faq-icon"><i class="fas fa-plus"></i></div>
            </div>
            <div class="faq-answer">
                <p>Visit the <a href="package/track.php">Track a Package</a> page and enter your tracking number. Your tracking number is provided when your shipment is created and also appears in your customer dashboard under "My Packages."</p>
            </div>
        </div>

        <div class="faq-item" data-q="where is my package why is it not moving">
            <div class="faq-question">
                <span>Why hasn't my package status updated?</span>
                <div class="faq-icon"><i class="fas fa-plus"></i></div>
            </div>
            <div class="faq-answer">
                <p>Package status updates occur each time a clerk or driver scans the package at a facility or during transit. If there's been no update for more than 24 hours, it may be in transit between facilities with no scan events in between.</p>
                <p>If your package appears stuck for more than 48 hours, consider <a href="<?= $logged_in ? 'support.php' : 'login.php' ?>">opening a support ticket</a> so our team can investigate.</p>
            </div>
        </div>

        <div class="faq-item" data-q="what does each status mean delivered in transit processing out for delivery">
            <div class="faq-question">
                <span>What do the different package statuses mean?</span>
                <div class="faq-icon"><i class="fas fa-plus"></i></div>
            </div>
            <div class="faq-answer">
                <ul>
                    <li><strong>Processing</strong> — Package has been created and is being prepared at the origin facility.</li>
                    <li><strong>In Transit</strong> — Package is moving between facilities via ground transport.</li>
                    <li><strong>On Flight</strong> — Package is on an air transport route.</li>
                    <li><strong>Out for Delivery</strong> — Package is with a driver and will be delivered today.</li>
                    <li><strong>Awaiting Pickup</strong> — Package is ready for the recipient to collect at the facility.</li>
                    <li><strong>Delivered</strong> — Package has been delivered to its destination.</li>
                </ul>
            </div>
        </div>

        <div class="faq-item" data-q="estimated delivery date accurate">
            <div class="faq-question">
                <span>How accurate is the estimated delivery date?</span>
                <div class="faq-icon"><i class="fas fa-plus"></i></div>
            </div>
            <div class="faq-answer">
                <p>Estimated delivery dates are calculated from the shipment creation date based on the selected shipping speed — Economy (5–7 days), Standard (3–5 days), or Express (1–2 days). Delays due to weather, high volume periods, or address issues may affect the actual delivery date.</p>
            </div>
        </div>
    </div>

    <!-- SHIPPING -->
    <div class="faq-section" data-cat="shipping">
        <p class="faq-section-title"><i class="fas fa-shipping-fast"></i> Shipping & Pricing</p>

        <div class="faq-item" data-q="shipping speeds economy standard express difference">
            <div class="faq-question">
                <span>What's the difference between Economy, Standard, and Express shipping?</span>
                <div class="faq-icon"><i class="fas fa-plus"></i></div>
            </div>
            <div class="faq-answer">
                <ul>
                    <li><strong>Economy</strong> — Slowest but most affordable. 5–7 business days. Best for non-urgent shipments.</li>
                    <li><strong>Standard</strong> — Our most popular option. 3–5 business days at the base rate.</li>
                    <li><strong>Express</strong> — Fastest option. 1–2 business days. A 70% premium is applied over the base rate.</li>
                </ul>
                <p>View the full breakdown on our <a href="index.php#shipping">Shipping Options</a> section.</p>
            </div>
        </div>

        <div class="faq-item" data-q="how is postage calculated price weight size">
            <div class="faq-question">
                <span>How is the shipping cost calculated?</span>
                <div class="faq-icon"><i class="fas fa-plus"></i></div>
            </div>
            <div class="faq-answer">
                <p>Postage is calculated based on the package's weight, size, destination ZIP code, and selected shipping speed. The price is displayed on the confirmation screen before you complete payment. Express shipping adds a 70% premium; Economy gives a 20% discount off the base rate.</p>
            </div>
        </div>

        <div class="faq-item" data-q="signature required what does it mean">
            <div class="faq-question">
                <span>What does "Signature Required" mean?</span>
                <div class="faq-icon"><i class="fas fa-plus"></i></div>
            </div>
            <div class="faq-answer">
                <p>When signature is required, the recipient must be physically present and sign for the package upon delivery. If no one is available, the driver will leave a notice and the package will be held at the nearest Postal Pro facility for pickup.</p>
            </div>
        </div>

        <div class="faq-item" data-q="how do i create a new shipment package">
            <div class="faq-question">
                <span>How do I create a new shipment?</span>
                <div class="faq-icon"><i class="fas fa-plus"></i></div>
            </div>
            <div class="faq-answer">
                <p>Shipments are created by a Postal Pro clerk at any of our facilities. Visit your nearest <a href="locations.php">Postal Pro location</a>, and a clerk will collect your package details, calculate postage, and generate a tracking number on the spot.</p>
                <?php if ($logged_in && $user_role == 'Customer'): ?>
                <p>You can also initiate a shipment online from your <a href="customer_dashboard.php">customer dashboard</a>.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ACCOUNT -->
    <div class="faq-section" data-cat="account">
        <p class="faq-section-title"><i class="fas fa-user"></i> Account & Registration</p>

        <div class="faq-item" data-q="how do i create an account register">
            <div class="faq-question">
                <span>How do I create an account?</span>
                <div class="faq-icon"><i class="fas fa-plus"></i></div>
            </div>
            <div class="faq-answer">
                <p>Click <a href="register.php">Get Started</a> at the top of any page. Fill in your email, password, name, and optional address — your account is created instantly for free. No credit card required.</p>
            </div>
        </div>

        <div class="faq-item" data-q="forgot password reset change password">
            <div class="faq-question">
                <span>How do I change or reset my password?</span>
                <div class="faq-icon"><i class="fas fa-plus"></i></div>
            </div>
            <div class="faq-answer">
                <p>Log in and visit the <a href="<?= $logged_in ? 'edit_profile.php' : 'login.php' ?>">Edit Profile</a> page to change your password. If you've forgotten your password and cannot log in, contact our support team to have it reset by an administrator.</p>
            </div>
        </div>

        <div class="faq-item" data-q="update address phone email profile information">
            <div class="faq-question">
                <span>How do I update my profile information?</span>
                <div class="faq-icon"><i class="fas fa-plus"></i></div>
            </div>
            <div class="faq-answer">
                <p>Go to <a href="<?= $logged_in ? 'edit_profile.php' : 'login.php' ?>">Edit Profile</a> from the top navigation bar. You can update your name, phone number, address, and password from that page.</p>
            </div>
        </div>

        <div class="faq-item" data-q="account suspended disabled inactive status">
            <div class="faq-question">
                <span>My account is showing as Inactive or Suspended — what do I do?</span>
                <div class="faq-icon"><i class="fas fa-plus"></i></div>
            </div>
            <div class="faq-answer">
                <p>Account status is managed by administrators. If you see an "Inactive" or "Suspended" error when logging in, please <a href="<?= $logged_in ? 'support.php' : 'login.php' ?>">contact support</a> with your email address and we'll resolve it promptly.</p>
            </div>
        </div>
    </div>

    <!-- SUPPORT -->
    <div class="faq-section" data-cat="support">
        <p class="faq-section-title"><i class="fas fa-headset"></i> Support & Issues</p>

        <div class="faq-item" data-q="how do i open a support ticket problem issue complaint">
            <div class="faq-question">
                <span>How do I report a problem with my package?</span>
                <div class="faq-icon"><i class="fas fa-plus"></i></div>
            </div>
            <div class="faq-answer">
                <p>Log in and visit the <a href="<?= $logged_in ? 'support.php' : 'login.php' ?>">Customer Support</a> page to open a ticket. Select your package, describe the issue (delayed, lost, damaged, etc.), and a support agent will respond as soon as possible.</p>
            </div>
        </div>

        <div class="faq-item" data-q="package lost damaged missing what to do">
            <div class="faq-question">
                <span>My package is lost or damaged — what should I do?</span>
                <div class="faq-icon"><i class="fas fa-plus"></i></div>
            </div>
            <div class="faq-answer">
                <p>First, <a href="package/track.php">track your package</a> to check its current status. If it appears to be stuck or shows no updates for more than 48 hours, open a support ticket through the <a href="<?= $logged_in ? 'support.php' : 'login.php' ?>">support page</a>. Our team will investigate and provide an update within 1–2 business days.</p>
            </div>
        </div>

        <div class="faq-item" data-q="pickup package collect facility how to pick up">
            <div class="faq-question">
                <span>How do I pick up a package from a facility?</span>
                <div class="faq-icon"><i class="fas fa-plus"></i></div>
            </div>
            <div class="faq-answer">
                <p>When your package has the status "Awaiting Pickup," visit the <a href="locations.php">facility</a> listed in your tracking details. Bring your tracking number and a valid photo ID. A clerk will verify and hand over the package.</p>
            </div>
        </div>

        <div class="faq-item" data-q="how long does support take response time">
            <div class="faq-question">
                <span>How long does it take to get a response from support?</span>
                <div class="faq-icon"><i class="fas fa-plus"></i></div>
            </div>
            <div class="faq-answer">
                <p>Our support team typically responds to open tickets within 1–2 business days. Complex investigations (e.g., lost packages) may take up to 3–5 business days. You can check the status of your ticket anytime from your customer dashboard.</p>
            </div>
        </div>
    </div>

    <!-- PAYMENT -->
    <div class="faq-section" data-cat="payment">
        <p class="faq-section-title"><i class="fas fa-credit-card"></i> Payment</p>

        <div class="faq-item" data-q="payment methods accepted cash credit card paypal">
            <div class="faq-question">
                <span>What payment methods are accepted?</span>
                <div class="faq-icon"><i class="fas fa-plus"></i></div>
            </div>
            <div class="faq-answer">
                <p>Postal Pro currently accepts the following payment methods at the point of shipment:</p>
                <ul>
                    <li><strong>Cash</strong> — Paid in person at the facility.</li>
                    <li><strong>Credit/Debit Card</strong> — Processed securely at the facility terminal.</li>
                    <li><strong>PayPal</strong> — Available as a digital payment option.</li>
                </ul>
            </div>
        </div>

        <div class="faq-item" data-q="refund cancel transaction shipment payment">
            <div class="faq-question">
                <span>Can I cancel a shipment and get a refund?</span>
                <div class="faq-icon"><i class="fas fa-plus"></i></div>
            </div>
            <div class="faq-answer">
                <p>Shipments can be cancelled before they are scanned into the system. Once a package has been processed and received its first tracking scan, the transaction cannot be reversed automatically. Please <a href="<?= $logged_in ? 'support.php' : 'login.php' ?>">contact support</a> for refund inquiries.</p>
            </div>
        </div>

        <div class="faq-item" data-q="invoice receipt payment confirmation where to find">
            <div class="faq-question">
                <span>Where can I find my payment receipt?</span>
                <div class="faq-icon"><i class="fas fa-plus"></i></div>
            </div>
            <div class="faq-answer">
                <p>Payment records are visible on the tracking page for each package (when logged in as the sender or receiver). Employees can see the full invoice details including invoice number, amount, and payment method.</p>
            </div>
        </div>
    </div>

    <!-- CONTACT CTA -->
    <div class="contact-cta">
        <h3>Still have questions?</h3>
        <p>Our support team is ready to help. Open a ticket and we'll get back to you within 1–2 business days.</p>
        <div class="cta-buttons">
            <a href="<?= $logged_in ? 'support.php' : 'login.php' ?>" class="btn-primary">
                <i class="fas fa-headset" style="margin-right:7px;"></i> Contact Support
            </a>
            <a href="package/track.php" class="btn-outline">
                <i class="fas fa-search" style="margin-right:7px;"></i> Track a Package
            </a>
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
    /* ACCORDION */
    document.querySelectorAll('.faq-question').forEach(q => {
        q.addEventListener('click', () => {
            const item = q.parentElement;
            const wasOpen = item.classList.contains('open');
            // Close all
            document.querySelectorAll('.faq-item').forEach(i => i.classList.remove('open'));
            // Toggle clicked
            if (!wasOpen) item.classList.add('open');
        });
    });

    /* CATEGORY TABS */
    const catTabs    = document.querySelectorAll('.cat-tab');
    const faqSections = document.querySelectorAll('.faq-section');

    catTabs.forEach(tab => {
        tab.addEventListener('click', () => {
            catTabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            const cat = tab.dataset.cat;

            faqSections.forEach(sec => {
                sec.style.display = (cat === 'all' || sec.dataset.cat === cat) ? '' : 'none';
            });
            updateEmpty();
        });
    });

    /* SEARCH */
    const faqSearch = document.getElementById('faqSearch');
    faqSearch.addEventListener('input', () => {
        const query = faqSearch.value.toLowerCase().trim();
        // Reset tab selection
        catTabs.forEach(t => t.classList.remove('active'));
        document.querySelector('[data-cat="all"]').classList.add('active');
        faqSections.forEach(sec => sec.style.display = '');

        document.querySelectorAll('.faq-item').forEach(item => {
            const text = item.dataset.q + ' ' + item.querySelector('.faq-question span').textContent.toLowerCase()
                       + ' ' + item.querySelector('.faq-answer').textContent.toLowerCase();
            item.style.display = (!query || text.includes(query)) ? '' : 'none';
        });
        updateEmpty();
    });

    function clearSearch() {
        faqSearch.value = '';
        document.querySelectorAll('.faq-item').forEach(i => i.style.display = '');
        faqSections.forEach(sec => sec.style.display = '');
        updateEmpty();
    }

    function updateEmpty() {
        const anyVisible = Array.from(document.querySelectorAll('.faq-item')).some(i => i.style.display !== 'none');
        document.getElementById('emptyState').style.display = anyVisible ? 'none' : 'block';
    }

    // Open FAQ item if URL has a hash matching a question
    window.addEventListener('load', () => {
        if (location.hash) {
            const target = document.querySelector(location.hash);
            if (target && target.classList.contains('faq-item')) {
                target.classList.add('open');
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    });
</script>
</body>
</html>
