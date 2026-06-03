<?php
require_once '../functions.php';
require_once '../db_connect.php';
session_start();

$tracking_number = $_GET['tracking'] ?? '';
$access_level    = 'guest';
$error_message   = '';
$package_info    = null;
$tracking_history = null;
$customer_info   = null;
$is_owner        = false;
$logged_in       = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$user_role       = $logged_in ? $_SESSION['role'] : '';

if (!empty($tracking_number)) {
    $package_query = "SELECT p.tracking_number, p.weight, p.size, p.postage, p.signature_required,
                     p.shipping_speed, p.status, p.timestamp_created, p.sender_id, p.receiver_id, p.facility_id,
                     f.city as facility_city, f.state as facility_state, f.type as facility_type
                     FROM Package p
                     LEFT JOIN Facility f ON p.facility_id = f.facility_id
                     WHERE p.tracking_number = ?";
    $stmt = $conn->prepare($package_query);
    $stmt->bind_param("s", $tracking_number);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $package_info = $result->fetch_assoc();

        $customer_query = "SELECT
                        s.user_id as sender_id, s.first_name as sender_first, s.last_name as sender_last,
                        s.street_address as sender_address, s.city as sender_city, s.state as sender_state, s.postal_code as sender_postal,
                        r.user_id as receiver_id, r.first_name as receiver_first, r.last_name as receiver_last,
                        r.street_address as receiver_address, r.city as receiver_city, r.state as receiver_state,
                        r.postal_code as receiver_postal
                        FROM Package p
                        JOIN Customer s ON p.sender_id = s.user_id
                        JOIN Customer r ON p.receiver_id = r.user_id
                        WHERE p.tracking_number = ?";
        $stmt = $conn->prepare($customer_query);
        $stmt->bind_param("s", $tracking_number);
        $stmt->execute();
        $customer_info = $stmt->get_result()->fetch_assoc();

        $history_query = "SELECT
                        th.history_id, th.tracking_number, th.location, th.status, th.timestamp, th.action,
                        CONCAT(e.first_name, ' ', e.last_name) as employee_name, e.role as employee_role,
                        f.city as facility_city, f.state as facility_state, f.type as facility_type,
                        t.trip_id, t.trip_type, t.depart_facility_id, t.arrive_facility_id
                        FROM Tracking_History th
                        LEFT JOIN Employee e ON th.employee_id = e.user_id
                        LEFT JOIN Facility f ON th.facility_id = f.facility_id
                        LEFT JOIN Trip t ON th.trip_id = t.trip_id
                        WHERE th.tracking_number = ?
                        ORDER BY th.timestamp DESC";
        $stmt = $conn->prepare($history_query);
        $stmt->bind_param("s", $tracking_number);
        $stmt->execute();
        $tracking_history = $stmt->get_result();

        if ($logged_in) {
            if ($user_role == 'Employee' || $user_role == 'Admin') {
                $access_level = 'employee';
            } elseif ($user_role == 'Customer') {
                $uid = $_SESSION['user_id'];
                if ($uid == $package_info['sender_id'] || $uid == $package_info['receiver_id']) {
                    $access_level = 'receiver';
                    $is_owner = true;
                }
            }
        }
    } else {
        $error_message = "No package found with tracking number <strong>" . htmlspecialchars($tracking_number) . "</strong>.";
    }
} else {
    $error_message = "Enter a tracking number above to get started.";
}

$latest_status     = '';
$estimated_delivery = '';
if ($package_info) {
    $latest_status = $package_info['status'];
    $creation_date = new DateTime($package_info['timestamp_created']);
    switch ($package_info['shipping_speed']) {
        case 'Economy': $delivery_days = 7; break;
        case 'Express': $delivery_days = 2; break;
        default:        $delivery_days = 4; break;
    }
    if ($latest_status != 'Delivered') {
        $estimated_date = clone $creation_date;
        $estimated_date->add(new DateInterval("P{$delivery_days}D"));
        $estimated_delivery = $estimated_date->format('M d, Y');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Package<?= $tracking_number ? ' — ' . htmlspecialchars($tracking_number) : '' ?> | POSTAL PRO</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700;800&display=swap');

        *, *::before, *::after { margin: 0; padding: 0; font-family: 'Open Sans', sans-serif; box-sizing: border-box; }
        .fa, .fas, .far, .fab, .fa-solid, .fa-regular, .fa-brands,
        .fa::before, .fas::before, .far::before, .fab::before { font-family: "Font Awesome 6 Free", "Font Awesome 6 Brands" !important; }

        body { background: #f4f6f9; color: #333; min-height: 100vh; }
        a { text-decoration: none; }

        /* NAV */
        nav {
            display: flex; align-items: center; justify-content: space-between;
            background: #004B87; padding: 0 5%;
            position: fixed; width: 100%; top: 0; left: 0; height: 60px;
            z-index: 200; box-shadow: 0 2px 8px rgba(0,0,0,0.25);
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
        .page-hero h1 { font-size: 2.4rem; font-weight: 800; margin-bottom: 8px; }
        .page-hero p.sub { color: rgba(255,255,255,0.7); font-size: 0.97rem; margin-bottom: 28px; }

        /* TRACK CARD IN HERO */
        .track-card {
            background: white; border-radius: 12px; padding: 24px 28px;
            box-shadow: 0 12px 40px rgba(0,0,0,0.2);
            max-width: 640px; margin: 0 auto;
            display: flex; gap: 10px; align-items: center;
        }
        .track-card input {
            flex: 1; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px;
            font-size: 1rem; outline: none; transition: border-color 0.2s;
        }
        .track-card input:focus { border-color: #004B87; }
        .track-btn {
            padding: 12px 24px; background: #004B87; color: white; border: none;
            border-radius: 8px; cursor: pointer; font-size: 0.97rem; font-weight: 700;
            transition: background 0.2s; white-space: nowrap;
            display: flex; align-items: center; gap: 8px;
        }
        .track-btn:hover { background: #003366; }

        /* PAGE BODY */
        .page-body { max-width: 1000px; margin: 0 auto; padding: 40px 5% 60px; }

        /* ALERT / ERROR */
        .alert {
            border-radius: 10px; padding: 16px 20px; margin-bottom: 28px;
            display: flex; align-items: flex-start; gap: 12px; font-size: 0.93rem;
        }
        .alert-info { background: #eff6ff; border-left: 4px solid #004B87; color: #1e3a5f; }
        .alert-error { background: #fff0f0; border-left: 4px solid #DA291C; color: #7f1d1d; }
        .alert i { margin-top: 2px; flex-shrink: 0; }

        /* STATUS BANNER */
        .status-banner {
            border-radius: 12px; padding: 22px 28px; margin-bottom: 24px;
            display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;
        }
        .status-banner.delivered  { background: #f0fdf4; border: 1px solid #bbf7d0; }
        .status-banner.transit    { background: #eff6ff; border: 1px solid #bfdbfe; }
        .status-banner.processing { background: #fffbeb; border: 1px solid #fde68a; }

        .banner-left h2 { font-size: 1.1rem; font-weight: 700; color: #1a202c; margin-bottom: 4px; }
        .banner-left p  { font-size: 0.83rem; color: #666; }

        .status-pill {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 7px 16px; border-radius: 999px; font-weight: 700; font-size: 0.88rem;
        }
        .pill-delivered  { background: #dcfce7; color: #166534; }
        .pill-transit    { background: #dbeafe; color: #1d4ed8; }
        .pill-processing { background: #fef9c3; color: #854d0e; }

        .delivery-box { text-align: right; }
        .delivery-box p:first-child { font-size: 0.75rem; color: #888; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; }
        .delivery-box p:last-child  { font-size: 1.05rem; font-weight: 700; color: #1a202c; }

        /* CARDS */
        .card {
            background: white; border-radius: 12px;
            box-shadow: 0 2px 16px rgba(0,0,0,0.06);
            margin-bottom: 24px; overflow: hidden;
        }
        .card-header {
            padding: 16px 24px; border-bottom: 1px solid #f1f5f9;
            display: flex; align-items: center; gap: 10px;
        }
        .card-header h3 { font-size: 1rem; font-weight: 700; color: #1a202c; }
        .card-header i  { color: #004B87; font-size: 0.95rem; }
        .card-body { padding: 22px 24px; }

        /* DETAILS GRID */
        .details-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
        .detail-box { background: #f8fafc; border-radius: 8px; padding: 16px 18px; }
        .detail-box h4 { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #004B87; margin-bottom: 10px; }
        .detail-box p  { font-size: 0.88rem; color: #444; line-height: 1.65; }
        .detail-box p span { color: #888; font-size: 0.8rem; display: block; }

        /* SCAN BUTTON */
        .scan-btn {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 9px 20px; background: #004B87; color: white;
            border-radius: 7px; font-size: 0.88rem; font-weight: 600;
            transition: background 0.2s;
        }
        .scan-btn:hover { background: #003366; }

        /* TIMELINE */
        .timeline { position: relative; }
        .timeline::before {
            content: ''; position: absolute;
            left: 15px; top: 6px; bottom: 6px;
            width: 2px; background: #e2e8f0;
        }
        .tl-item { display: flex; gap: 20px; margin-bottom: 20px; position: relative; }
        .tl-item:last-child { margin-bottom: 0; }

        .tl-dot {
            flex-shrink: 0; width: 32px; height: 32px;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            color: white; font-size: 0.75rem; position: relative; z-index: 2;
            box-shadow: 0 0 0 3px white;
        }
        .tl-dot i { font-size: 0.85rem; }
        .tl-dot.delivered  { background: #16a34a; }
        .tl-dot.transit    { background: #2563eb; }
        .tl-dot.processing { background: #d97706; }

        .tl-content {
            flex: 1; background: #f8fafc; border-radius: 10px;
            padding: 14px 18px; border-left: 4px solid #e2e8f0;
        }
        .tl-content.delivered  { border-left-color: #16a34a; }
        .tl-content.transit    { border-left-color: #2563eb; }
        .tl-content.processing { border-left-color: #d97706; }

        .tl-top { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 6px; margin-bottom: 4px; }
        .tl-status { font-weight: 700; font-size: 0.95rem; color: #1a202c; }
        .tl-date   { font-size: 0.78rem; color: #888; }
        .tl-location { font-size: 0.88rem; color: #555; margin-top: 4px; }
        .tl-meta { margin-top: 8px; display: flex; flex-wrap: wrap; gap: 6px; }
        .tl-tag {
            font-size: 0.75rem; padding: 3px 10px; border-radius: 999px;
            background: #e2e8f0; color: #475569; display: inline-flex; align-items: center; gap: 5px;
        }

        /* LOCATION BOX */
        .location-box { background: #f8fafc; border-radius: 10px; padding: 18px 20px; }
        .location-box p { font-size: 0.9rem; color: #444; line-height: 1.7; }
        .location-box strong { color: #1a202c; }

        .active-trip-box {
            margin-top: 14px; background: #eff6ff; border: 1px solid #bfdbfe;
            border-radius: 10px; padding: 16px 18px;
        }
        .active-trip-box p { font-size: 0.87rem; color: #1e3a5f; line-height: 1.6; }
        .view-trip-link {
            display: inline-flex; align-items: center; gap: 6px;
            margin-top: 10px; font-size: 0.85rem; font-weight: 600;
            color: #004B87; transition: color 0.2s;
        }
        .view-trip-link:hover { color: #DA291C; }

        /* SUPPORT CARDS */
        .support-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .support-card { background: #f8fafc; border-radius: 10px; padding: 16px 18px; border: 1px solid #e2e8f0; }
        .support-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        .support-card-header span:first-child { font-size: 0.88rem; font-weight: 600; color: #1a202c; }
        .badge { font-size: 0.72rem; font-weight: 700; padding: 3px 9px; border-radius: 999px; }
        .badge-open     { background: #fef9c3; color: #854d0e; }
        .badge-progress { background: #dbeafe; color: #1d4ed8; }
        .badge-resolved { background: #dcfce7; color: #166534; }
        .badge-failed   { background: #fee2e2; color: #991b1b; }
        .badge-completed{ background: #dcfce7; color: #166534; }
        .badge-pending  { background: #fef9c3; color: #854d0e; }
        .support-card p { font-size: 0.83rem; color: #555; line-height: 1.6; }
        .support-card a { font-size: 0.82rem; color: #004B87; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; margin-top: 8px; transition: color 0.2s; }
        .support-card a:hover { color: #DA291C; }

        /* FOOTER */
        footer { background: #1a2e4a; color: rgba(255,255,255,0.75); padding: 40px 5% 20px; margin-top: 20px; }
        .footer-grid { max-width: 1000px; margin: 0 auto; display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 32px; padding-bottom: 28px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .footer-brand .logo { font-size: 1.3rem; display: inline-block; margin-bottom: 10px; }
        .footer-brand p  { font-size: 0.83rem; line-height: 1.6; }
        .footer-col h4   { color: white; font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px; }
        .footer-col ul   { list-style: none; }
        .footer-col ul li { margin-bottom: 7px; }
        .footer-col ul li a { color: rgba(255,255,255,0.6); font-size: 0.83rem; transition: color 0.2s; }
        .footer-col ul li a:hover { color: white; }
        .footer-bottom { max-width: 1000px; margin: 18px auto 0; display: flex; justify-content: space-between; align-items: center; font-size: 0.75rem; color: rgba(255,255,255,0.4); flex-wrap: wrap; gap: 6px; }
        .footer-knight { color: #DA291C; }

        /* RESPONSIVE */
        @media (max-width: 820px) {
            .details-grid { grid-template-columns: 1fr 1fr; }
            .support-grid  { grid-template-columns: 1fr; }
            .footer-grid   { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 600px) {
            .page-hero h1 { font-size: 1.8rem; }
            .track-card   { flex-direction: column; }
            .track-btn    { width: 100%; justify-content: center; }
            .details-grid { grid-template-columns: 1fr; }
            .status-banner { flex-direction: column; }
            .delivery-box  { text-align: left; }
            .menu-toggle   { display: block; }
            nav ul {
                position: fixed; top: 0; right: -260px;
                width: 260px; height: 100vh;
                background: #003366; flex-direction: column;
                padding-top: 70px; transition: right 0.3s;
                z-index: 150; box-shadow: -5px 0 15px rgba(0,0,0,0.15);
                gap: 0;
            }
            nav ul.show { right: 0; }
            nav ul li a { padding: 14px 20px; border-radius: 0; }
            .footer-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- NAV -->
<?php $base_path = '../'; include '../_public_nav.php'; ?>

<!-- HERO WITH TRACKING FORM -->
<section class="page-hero">
    <p class="eyebrow">Real-time package tracking</p>
    <h1>Track Your Shipment</h1>
    <p class="sub">Enter your tracking number to see live status, location history, and estimated delivery.</p>
    <form method="get" action="" class="track-card">
        <input type="text" name="tracking"
               value="<?= htmlspecialchars($tracking_number) ?>"
               placeholder="e.g. POS-2024-XXXXXX" autocomplete="off" autofocus>
        <button type="submit" class="track-btn">
            <i class="fas fa-search"></i> Track
        </button>
    </form>
</section>

<div class="page-body">

    <?php if (!empty($error_message) && empty($tracking_number)): ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i>
        <span><?= $error_message ?></span>
    </div>
    <?php elseif (!empty($error_message)): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <span><?= $error_message ?></span>
    </div>

    <?php elseif ($package_info): ?>

        <?php
        // Determine status theme
        $theme = 'processing';
        $icon  = 'fa-circle-dot';
        switch ($package_info['status']) {
            case 'Delivered':
                $theme = 'delivered'; $icon = 'fa-circle-check'; break;
            case 'Out for Delivery': case 'In Transit': case 'On Flight':
                $theme = 'transit'; $icon = 'fa-truck-fast'; break;
        }
        ?>

        <!-- STATUS BANNER -->
        <div class="status-banner <?= $theme ?>">
            <div class="banner-left">
                <h2>Tracking #<?= htmlspecialchars($tracking_number) ?></h2>
                <p>Shipped <?= date('M d, Y', strtotime($package_info['timestamp_created'])) ?>
                    &middot; <?= htmlspecialchars($package_info['shipping_speed']) ?> Service</p>
            </div>
            <div style="display:flex; align-items:center; gap:20px; flex-wrap:wrap;">
                <span class="status-pill pill-<?= $theme ?>">
                    <i class="fas <?= $icon ?>"></i>
                    <?= htmlspecialchars($package_info['status']) ?>
                </span>
                <div class="delivery-box">
                    <?php if ($package_info['status'] == 'Delivered'): ?>
                        <p>Delivered on</p>
                        <?php
                        if ($tracking_history && $tracking_history->num_rows > 0) {
                            $tracking_history->data_seek(0);
                            $latest = $tracking_history->fetch_assoc();
                            echo '<p>' . date('M d, Y', strtotime($latest['timestamp'])) . '</p>';
                        }
                        ?>
                    <?php else: ?>
                        <p>Estimated delivery</p>
                        <p><?= $estimated_delivery ? htmlspecialchars($estimated_delivery) : 'Pending' ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- PACKAGE DETAILS -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-box"></i>
                <h3>Package Details</h3>
            </div>
            <div class="card-body">
                <div class="details-grid">
                    <div class="detail-box">
                        <h4>From</h4>
                        <p><?= htmlspecialchars($customer_info['sender_first'] . ' ' . $customer_info['sender_last']) ?></p>
                        <?php if ($access_level == 'employee'): ?>
                            <p><?= htmlspecialchars($customer_info['sender_address']) ?></p>
                        <?php endif; ?>
                        <p><?= htmlspecialchars($customer_info['sender_city'] . ', ' . $customer_info['sender_state'] . ' ' . $customer_info['sender_postal']) ?></p>
                    </div>
                    <div class="detail-box">
                        <h4>To</h4>
                        <p><?= htmlspecialchars($customer_info['receiver_first'] . ' ' . $customer_info['receiver_last']) ?></p>
                        <?php if ($access_level == 'employee' || $is_owner): ?>
                            <p><?= htmlspecialchars($customer_info['receiver_address']) ?></p>
                        <?php endif; ?>
                        <p><?= htmlspecialchars($customer_info['receiver_city'] . ', ' . $customer_info['receiver_state'] . ' ' . $customer_info['receiver_postal']) ?></p>
                    </div>
                    <div class="detail-box">
                        <h4>Shipment Info</h4>
                        <p>
                            <span>Weight</span><?= htmlspecialchars($package_info['weight']) ?> lbs
                        </p>
                        <p>
                            <span>Size</span><?= htmlspecialchars($package_info['size']) ?>
                        </p>
                        <p>
                            <span>Service</span><?= htmlspecialchars($package_info['shipping_speed']) ?>
                        </p>
                        <?php if ($access_level == 'employee' || $is_owner): ?>
                        <p>
                            <span>Signature</span><?= $package_info['signature_required'] == 'Y' ? 'Required' : 'Not required' ?>
                        </p>
                        <p>
                            <span>Postage</span>$<?= number_format($package_info['postage'], 2) ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- TRACKING TIMELINE -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-timeline"></i>
                <h3>Tracking History</h3>
            </div>
            <div class="card-body">
                <?php if ($tracking_history && $tracking_history->num_rows > 0): ?>
                    <div class="timeline">
                        <?php
                        $tracking_history->data_seek(0);
                        while ($event = $tracking_history->fetch_assoc()):
                            $ev_theme = 'processing';
                            $ev_icon  = 'fa-box';
                            switch ($event['status']) {
                                case 'Delivered':
                                    $ev_theme = 'delivered'; $ev_icon = 'fa-check'; break;
                                case 'Out for Delivery':
                                    $ev_theme = 'transit'; $ev_icon = 'fa-truck'; break;
                                case 'In Transit':
                                    $ev_theme = 'transit'; $ev_icon = 'fa-route'; break;
                                case 'On Flight':
                                    $ev_theme = 'transit'; $ev_icon = 'fa-plane'; break;
                                case 'Arrived at Facility':
                                    $ev_theme = 'transit'; $ev_icon = 'fa-building'; break;
                                case 'Processing':
                                    $ev_theme = 'processing'; $ev_icon = 'fa-box'; break;
                            }
                        ?>
                        <div class="tl-item">
                            <div class="tl-dot <?= $ev_theme ?>">
                                <i class="fas <?= $ev_icon ?>"></i>
                            </div>
                            <div class="tl-content <?= $ev_theme ?>">
                                <div class="tl-top">
                                    <span class="tl-status"><?= htmlspecialchars($event['status']) ?></span>
                                    <span class="tl-date"><?= date('M d, Y g:i A', strtotime($event['timestamp'])) ?></span>
                                </div>
                                <p class="tl-location">
                                    <i class="fas fa-location-dot" style="color:#DA291C; font-size:0.75rem; margin-right:4px;"></i>
                                    <?= htmlspecialchars($event['location']) ?>
                                </p>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info" style="margin:0;">
                        <i class="fas fa-info-circle"></i>
                        <span>No tracking history available yet for this package.</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($access_level == 'employee' || $access_level == 'receiver'): ?>
        <!-- CURRENT LOCATION -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-location-dot"></i>
                <h3>Current Location</h3>
            </div>
            <div class="card-body">
                <div class="location-box">
                    <p>
                    <?php
                    if ($package_info['status'] == 'Delivered') {
                        echo '<strong>Delivered to:</strong> ' . htmlspecialchars($customer_info['receiver_city'] . ', ' . $customer_info['receiver_state']);
                    } elseif ($package_info['facility_id']) {
                        echo '<strong>Currently at:</strong> ' . htmlspecialchars($package_info['facility_city'] . ', ' . $package_info['facility_state'] . ' — ' . $package_info['facility_type']);
                    } else {
                        echo 'Location information is not yet available.';
                    }
                    ?>
                    </p>

                    <?php if ($access_level == 'employee'): ?>
                    <p style="margin-top:8px;"><strong>Facility ID:</strong> <?= htmlspecialchars($package_info['facility_id'] ?? 'N/A') ?></p>

                    <?php
                    if (in_array($package_info['status'], ['In Transit', 'On Flight', 'Out for Delivery'])) {
                        $trip_query = "SELECT tp.trip_id, t.trip_type, t.depart_time, t.arrival_time,
                                    f1.city as depart_city, f1.state as depart_state,
                                    f2.city as arrive_city, f2.state as arrive_state,
                                    CONCAT(e.first_name, ' ', e.last_name) as employee_name
                                    FROM Trip_Package tp
                                    JOIN Trip t ON tp.trip_id = t.trip_id
                                    JOIN Facility f1 ON t.depart_facility_id = f1.facility_id
                                    LEFT JOIN Facility f2 ON t.arrive_facility_id = f2.facility_id
                                    LEFT JOIN Employee e ON t.employee_id = e.user_id
                                    WHERE tp.tracking_number = ? AND t.arrival_time IS NULL
                                    LIMIT 1";
                        $stmt = $conn->prepare($trip_query);
                        $stmt->bind_param("s", $tracking_number);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if ($result->num_rows > 0) {
                            $at = $result->fetch_assoc();
                            echo '<div class="active-trip-box">';
                            echo '<p><strong>Active Trip #' . htmlspecialchars($at['trip_id']) . '</strong> &middot; ' . htmlspecialchars($at['trip_type']) . '</p>';
                            echo '<p>From: ' . htmlspecialchars($at['depart_city'] . ', ' . $at['depart_state']) . '</p>';
                            if ($at['arrive_city']) echo '<p>To: ' . htmlspecialchars($at['arrive_city'] . ', ' . $at['arrive_state']) . '</p>';
                            echo '<p>Driver: ' . htmlspecialchars($at['employee_name']) . '</p>';
                            echo '<p>Departed: ' . date('M d, Y H:i', strtotime($at['depart_time'])) . '</p>';
                            echo '<a href="../trips/view_trip.php?id=' . $at['trip_id'] . '" class="view-trip-link"><i class="fas fa-arrow-up-right-from-square"></i> View Trip Details</a>';
                            echo '</div>';
                        }
                    }
                    ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

    <?php endif; ?>

</div><!-- /page-body -->

<!-- FOOTER -->
<footer>
    <div class="footer-grid">
        <div class="footer-brand">
            <a href="../index.php" class="logo">POSTAL PRO</a>
            <p>America's trusted postal management network — delivering reliability and transparency to every doorstep.</p>
        </div>
        <div class="footer-col">
            <h4>Quick Links</h4>
            <ul>
                <li><a href="track.php">Track a Package</a></li>
                <li><a href="../locations.php">Find a Location</a></li>
                <li><a href="<?= $logged_in ? '../shop.php' : '../login.php' ?>">Postal Shop</a></li>
                <li><a href="<?= $logged_in ? '../support.php' : '../login.php' ?>">Support</a></li>
                <li><a href="../shipping.php" style="display:inline-flex;align-items:center;gap:6px;">Shipping Options <i class="fas fa-arrow-right" style="font-size:0.7rem;"></i></a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h4>Resources</h4>
            <ul>
                <li><a href="../faqs.php">FAQs</a></li>
                <li><a href="../about.php">About Postal Pro</a></li>
                <li><a href="../locations.php">Our Locations</a></li>
                <li><a href="<?= $logged_in ? '../support.php' : '../login.php' ?>">Contact Support</a></li>
                <li><a href="../careers.php">Join Our Team</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h4>Account</h4>
            <ul>
                <?php if($logged_in): ?>
                <li><a href="<?= ($user_role == 'Customer') ? '../customer_dashboard.php' : '../employee_dashboard.php' ?>">My Dashboard</a></li>
                <li><a href="../edit_profile.php">Edit Profile</a></li>
                <li><a href="../logout.php">Logout</a></li>
                <?php else: ?>
                <li><a href="../login.php">Sign In</a></li>
                <li><a href="../register.php">Create Account</a></li>
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
