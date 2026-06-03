<?php
session_start();
require_once '../db_connect.php';
require_once '../functions.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

$tracking_number = trim($_GET['tracking'] ?? '');
$package_info    = null;
$customer_info   = null;
$tracking_history = null;
$payments        = null;
$tickets         = null;
$active_trip     = null;
$error_message   = '';

if (!empty($tracking_number)) {
    $stmt = $conn->prepare("SELECT p.tracking_number, p.weight, p.size, p.postage, p.signature_required,
                     p.shipping_speed, p.status, p.timestamp_created, p.sender_id, p.receiver_id, p.facility_id, p.last_tracking_id,
                     f.facility_id as f_id, f.city as facility_city, f.state as facility_state, f.type as facility_type, f.street_address as facility_address, f.postal_code as facility_postal
                     FROM Package p
                     LEFT JOIN Facility f ON p.facility_id = f.facility_id
                     WHERE p.tracking_number = ?");
    $stmt->bind_param("s", $tracking_number);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $package_info = $res->fetch_assoc();
    }
}

if ($package_info) {
    $stmt = $conn->prepare("SELECT
        s.user_id as sender_id, s.first_name as sender_first, s.last_name as sender_last, s.phone as sender_phone,
        s.street_address as sender_address, s.city as sender_city, s.state as sender_state, s.postal_code as sender_postal,
        u_s.email as sender_email,
        r.user_id as receiver_id, r.first_name as receiver_first, r.last_name as receiver_last, r.phone as receiver_phone,
        r.street_address as receiver_address, r.city as receiver_city, r.state as receiver_state, r.postal_code as receiver_postal,
        u_r.email as receiver_email
        FROM Package p
        JOIN Customer s ON p.sender_id = s.user_id
        JOIN Users u_s ON s.user_id = u_s.user_id
        JOIN Customer r ON p.receiver_id = r.user_id
        JOIN Users u_r ON r.user_id = u_r.user_id
        WHERE p.tracking_number = ?");
    $stmt->bind_param("s", $tracking_number);
    $stmt->execute();
    $customer_info = $stmt->get_result()->fetch_assoc();

    $history_query = "SELECT th.history_id, th.tracking_number, th.location, th.status, th.timestamp, th.action, th.facility_id, th.employee_id, th.trip_id,
        CONCAT(e.first_name, ' ', e.last_name) as employee_name, e.role as employee_role,
        u.email as employee_email,
        f.facility_id as f_id, f.city as facility_city, f.state as facility_state, f.type as facility_type,
        t.trip_type, t.depart_time, t.arrival_time
        FROM Tracking_History th
        LEFT JOIN Employee e ON th.employee_id = e.user_id
        LEFT JOIN Users u ON e.user_id = u.user_id
        LEFT JOIN Facility f ON th.facility_id = f.facility_id
        LEFT JOIN Trip t ON th.trip_id = t.trip_id
        WHERE th.tracking_number = ?
        ORDER BY th.timestamp DESC";
    $stmt = $conn->prepare($history_query);
    $stmt->bind_param("s", $tracking_number);
    $stmt->execute();
    $tracking_history = $stmt->get_result();

    $pay_stmt = $conn->prepare("SELECT payment_id, user_id, amount, payment_method, transaction_status, invoice_number, payment_date, facility_id FROM Package_Payment WHERE package_id = ? ORDER BY payment_date DESC");
    $pay_stmt->bind_param("s", $tracking_number);
    $pay_stmt->execute();
    $payments = $pay_stmt->get_result();

    $ticket_stmt = $conn->prepare("SELECT st.ticket_id, st.user_id, st.issue_type, st.status, st.assigned_employee_id,
        CONCAT(e.first_name, ' ', e.last_name) as assigned_name
        FROM Support_Ticket st
        LEFT JOIN Employee e ON st.assigned_employee_id = e.user_id
        WHERE st.package_id = ? ORDER BY st.ticket_id DESC");
    $ticket_stmt->bind_param("s", $tracking_number);
    $ticket_stmt->execute();
    $tickets = $ticket_stmt->get_result();

    if (in_array($package_info['status'], ['In Transit', 'On Flight', 'Out for Delivery'])) {
        $trip_stmt = $conn->prepare("SELECT tp.trip_id, t.trip_type, t.depart_time, t.arrival_time, t.employee_id, t.depart_facility_id, t.arrive_facility_id,
            f1.city as depart_city, f1.state as depart_state, f2.city as arrive_city, f2.state as arrive_state,
            CONCAT(e.first_name, ' ', e.last_name) as employee_name, u.email as employee_email
            FROM Trip_Package tp
            JOIN Trip t ON tp.trip_id = t.trip_id
            JOIN Facility f1 ON t.depart_facility_id = f1.facility_id
            LEFT JOIN Facility f2 ON t.arrive_facility_id = f2.facility_id
            LEFT JOIN Employee e ON t.employee_id = e.user_id
            LEFT JOIN Users u ON e.user_id = u.user_id
            WHERE tp.tracking_number = ? AND t.arrival_time IS NULL LIMIT 1");
        $trip_stmt->bind_param("s", $tracking_number);
        $trip_stmt->execute();
        $tr = $trip_stmt->get_result();
        if ($tr->num_rows > 0) $active_trip = $tr->fetch_assoc();
    }
} else {
    if (!empty($tracking_number)) {
        $error_message = "No package found with tracking number <strong>" . htmlspecialchars($tracking_number) . "</strong>.";
    } else {
        $error_message = "Provide a tracking number in the URL (e.g. track_package.php?tracking=PS...).";
    }
}

$estimated_delivery = '';
if ($package_info && $package_info['status'] != 'Delivered') {
    $creation_date = new DateTime($package_info['timestamp_created']);
    switch ($package_info['shipping_speed']) {
        case 'Economy': $delivery_days = 7; break;
        case 'Express': $delivery_days = 2; break;
        default:        $delivery_days = 4; break;
    }
    $creation_date->add(new DateInterval("P{$delivery_days}D"));
    $estimated_delivery = $creation_date->format('M d, Y');
}

$nav_back_url  = 'system_logs.php';
$nav_back_text = 'System Log';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin: Package <?= htmlspecialchars($tracking_number) ?> | POSTAL PRO</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Open Sans', sans-serif; }
        .fa, .fas, .far, .fab, .fa::before, .fas::before { font-family: "Font Awesome 6 Free", "Font Awesome 6 Brands" !important; }
        body { background: #f1f5f9; color: #1e293b; min-height: 100vh; padding-bottom: 40px; }
        .container { max-width: 1000px; margin: 0 auto; padding: 24px 20px; }
        .breadcrumb { font-size: 0.8rem; color: #64748b; margin-bottom: 20px; }
        .breadcrumb a { color: #004B87; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        .card { background: #fff; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 20px; overflow: hidden; }
        .card-header { padding: 14px 20px; border-bottom: 1px solid #e2e8f0; font-weight: 700; font-size: 0.95rem; color: #1e293b; display: flex; align-items: center; gap: 10px; }
        .card-header i { color: #004B87; }
        .card-body { padding: 20px; }
        .badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 999px; font-size: 0.8rem; font-weight: 700; }
        .badge-admin { background: #dbeafe; color: #1d4ed8; }
        .badge-delivered { background: #dcfce7; color: #166534; }
        .badge-transit { background: #dbeafe; color: #1d4ed8; }
        .badge-processing { background: #fef9c3; color: #854d0e; }
        .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .grid3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
        .detail-block { background: #f8fafc; border-radius: 8px; padding: 14px 16px; }
        .detail-block h4 { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #004B87; margin-bottom: 8px; }
        .detail-block p { font-size: 0.88rem; color: #334155; line-height: 1.5; margin-bottom: 4px; }
        .detail-block .muted { font-size: 0.78rem; color: #64748b; }
        .alert-error { background: #fef2f2; border-left: 4px solid #dc2626; padding: 14px 18px; border-radius: 8px; color: #991b1b; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .alert-info { background: #eff6ff; border-left: 4px solid #2563eb; padding: 14px 18px; border-radius: 8px; color: #1e40af; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        th { text-align: left; padding: 10px 12px; background: #f8fafc; font-weight: 600; color: #475569; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; }
        tr:hover { background: #fafafa; }
        .timeline { position: relative; }
        .timeline::before { content: ''; position: absolute; left: 15px; top: 6px; bottom: 6px; width: 2px; background: #e2e8f0; }
        .tl-item { display: flex; gap: 16px; margin-bottom: 16px; position: relative; }
        .tl-dot { flex-shrink: 0; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 0.8rem; position: relative; z-index: 2; box-shadow: 0 0 0 3px #fff; }
        .tl-dot.delivered { background: #16a34a; }
        .tl-dot.transit { background: #2563eb; }
        .tl-dot.processing { background: #d97706; }
        .tl-content { flex: 1; background: #f8fafc; border-radius: 8px; padding: 12px 16px; border-left: 4px solid #e2e8f0; }
        .tl-content.delivered { border-left-color: #16a34a; }
        .tl-content.transit { border-left-color: #2563eb; }
        .tl-content.processing { border-left-color: #d97706; }
        .tl-meta { font-size: 0.78rem; color: #64748b; margin-top: 6px; }
        .actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 20px; }
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 8px; font-size: 0.88rem; font-weight: 600; text-decoration: none; transition: background 0.2s; }
        .btn-primary { background: #004B87; color: #fff; border: none; cursor: pointer; }
        .btn-primary:hover { background: #003366; }
        .btn-secondary { background: #fff; color: #004B87; border: 2px solid #004B87; }
        .btn-secondary:hover { background: #eff6ff; }
        @media (max-width: 768px) { .grid2, .grid3 { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<?php include '_nav.php'; ?>

<div class="container" style="margin-top: 20px;">
    <div class="breadcrumb">
        <a href="system_logs.php">System Log</a>
        <?php if ($tracking_number): ?>
            &rarr; <span>Package <?= htmlspecialchars($tracking_number) ?></span>
        <?php endif; ?>
    </div>

    <?php if ($error_message): ?>
        <div class="alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?= $error_message ?></span>
        </div>
        <p><a href="system_logs.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to System Log</a></p>
    <?php elseif ($package_info): ?>
        <?php
        $theme = 'processing';
        $icon  = 'fa-box';
        switch ($package_info['status']) {
            case 'Delivered': $theme = 'delivered'; $icon = 'fa-circle-check'; break;
            case 'Out for Delivery': case 'In Transit': case 'On Flight': $theme = 'transit'; $icon = 'fa-truck-fast'; break;
        }
        ?>
        <div class="card">
            <div class="card-body">
                <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px;">
                    <div>
                        <h1 style="font-size: 1.4rem; font-weight: 800; color: #1e293b; margin-bottom: 4px;">
                            <?= htmlspecialchars($tracking_number) ?>
                        </h1>
                        <p style="font-size: 0.88rem; color: #64748b;">
                            Created <?= date('M d, Y H:i', strtotime($package_info['timestamp_created'])) ?>
                            &middot; <?= htmlspecialchars($package_info['shipping_speed']) ?> &middot; Facility ID: <?= htmlspecialchars($package_info['facility_id'] ?? '—') ?>
                        </p>
                    </div>
                    <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                        <span class="badge badge-admin"><i class="fas fa-shield-halved"></i> Admin View</span>
                        <span class="badge badge-<?= $theme ?>"><i class="fas <?= $icon ?>"></i> <?= htmlspecialchars($package_info['status']) ?></span>
                        <a href="../package/track.php?tracking=<?= urlencode($tracking_number) ?>" target="_blank" rel="noopener" class="btn btn-secondary"><i class="fas fa-external-link-alt"></i> View as customer</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid3">
            <div class="card">
                <div class="card-header"><i class="fas fa-user"></i> Sender</div>
                <div class="card-body">
                    <div class="detail-block">
                        <p><strong><?= htmlspecialchars($customer_info['sender_first'] . ' ' . $customer_info['sender_last']) ?></strong></p>
                        <p class="muted">User ID: <?= (int)$customer_info['sender_id'] ?></p>
                        <p class="muted"><?= htmlspecialchars($customer_info['sender_email']) ?></p>
                        <?php if (!empty($customer_info['sender_phone'])): ?><p><?= htmlspecialchars($customer_info['sender_phone']) ?></p><?php endif; ?>
                        <p><?= htmlspecialchars($customer_info['sender_address']) ?></p>
                        <p><?= htmlspecialchars($customer_info['sender_city'] . ', ' . $customer_info['sender_state'] . ' ' . $customer_info['sender_postal']) ?></p>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><i class="fas fa-user"></i> Receiver</div>
                <div class="card-body">
                    <div class="detail-block">
                        <p><strong><?= htmlspecialchars($customer_info['receiver_first'] . ' ' . $customer_info['receiver_last']) ?></strong></p>
                        <p class="muted">User ID: <?= (int)$customer_info['receiver_id'] ?></p>
                        <p class="muted"><?= htmlspecialchars($customer_info['receiver_email']) ?></p>
                        <?php if (!empty($customer_info['receiver_phone'])): ?><p><?= htmlspecialchars($customer_info['receiver_phone']) ?></p><?php endif; ?>
                        <p><?= htmlspecialchars($customer_info['receiver_address']) ?></p>
                        <p><?= htmlspecialchars($customer_info['receiver_city'] . ', ' . $customer_info['receiver_state'] . ' ' . $customer_info['receiver_postal']) ?></p>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><i class="fas fa-box"></i> Shipment &amp; facility</div>
                <div class="card-body">
                    <div class="detail-block">
                        <p><span class="muted">Weight</span> <?= htmlspecialchars($package_info['weight']) ?> lbs</p>
                        <p><span class="muted">Size</span> <?= htmlspecialchars($package_info['size']) ?></p>
                        <p><span class="muted">Postage</span> $<?= number_format($package_info['postage'], 2) ?></p>
                        <p><span class="muted">Signature</span> <?= $package_info['signature_required'] === 'Y' ? 'Required' : 'No' ?></p>
                        <p><span class="muted">Last tracking ID</span> <?= $package_info['last_tracking_id'] ?? '—' ?></p>
                        <?php if ($package_info['facility_id']): ?>
                            <p><span class="muted">Current facility</span> ID <?= (int)$package_info['facility_id'] ?> — <?= htmlspecialchars($package_info['facility_city'] . ', ' . $package_info['facility_state']) ?></p>
                            <p class="muted"><?= htmlspecialchars($package_info['facility_type'] ?? '') ?> &middot; <?= htmlspecialchars($package_info['facility_address'] ?? '') ?> <?= htmlspecialchars($package_info['facility_postal'] ?? '') ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tracking timeline (admin: history_id, employee email, facility_id) -->
        <div class="card">
            <div class="card-header"><i class="fas fa-timeline"></i> Tracking history</div>
            <div class="card-body">
                <?php if ($tracking_history && $tracking_history->num_rows > 0): ?>
                    <div class="timeline">
                        <?php $tracking_history->data_seek(0); while ($ev = $tracking_history->fetch_assoc()):
                            $ev_theme = 'processing'; $ev_icon = 'fa-box';
                            switch ($ev['status']) {
                                case 'Delivered': $ev_theme = 'delivered'; $ev_icon = 'fa-check'; break;
                                case 'Out for Delivery': $ev_theme = 'transit'; $ev_icon = 'fa-truck'; break;
                                case 'In Transit': $ev_theme = 'transit'; $ev_icon = 'fa-route'; break;
                                case 'On Flight': $ev_theme = 'transit'; $ev_icon = 'fa-plane'; break;
                                case 'Arrived at Facility': $ev_theme = 'transit'; $ev_icon = 'fa-building'; break;
                            }
                        ?>
                        <div class="tl-item">
                            <div class="tl-dot <?= $ev_theme ?>"><i class="fas <?= $ev_icon ?>"></i></div>
                            <div class="tl-content <?= $ev_theme ?>">
                                <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 6px;">
                                    <strong><?= htmlspecialchars($ev['status']) ?></strong>
                                    <span class="muted"><?= date('M d, Y g:i A', strtotime($ev['timestamp'])) ?></span>
                                </div>
                                <p><?= htmlspecialchars($ev['location']) ?></p>
                                <div class="tl-meta">
                                    ID <?= (int)$ev['history_id'] ?>
                                    <?php if ($ev['facility_id']): ?> &middot; Facility <?= (int)$ev['facility_id'] ?> (<?= htmlspecialchars($ev['facility_city'] ?? '') ?>)<?php endif; ?>
                                    <?php if ($ev['action'] && $ev['action'] !== 'None'): ?> &middot; <?= htmlspecialchars($ev['action']) ?><?php endif; ?>
                                    <?php if (!empty($ev['employee_name'])): ?>
                                        &middot; <strong><?= htmlspecialchars($ev['employee_name']) ?></strong> (<?= htmlspecialchars($ev['employee_role'] ?? '') ?>)
                                        <?php if (!empty($ev['employee_email'])): ?> &middot; <?= htmlspecialchars($ev['employee_email']) ?><?php endif; ?>
                                    <?php endif; ?>
                                    <?php if (!empty($ev['trip_id'])): ?> &middot; Trip #<?= (int)$ev['trip_id'] ?> (<?= htmlspecialchars($ev['trip_type'] ?? '') ?>)<?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p class="muted">No tracking events yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($active_trip): ?>
        <div class="card">
            <div class="card-header"><i class="fas fa-truck"></i> Active trip</div>
            <div class="card-body">
                <p><strong>Trip #<?= (int)$active_trip['trip_id'] ?></strong> &middot; <?= htmlspecialchars($active_trip['trip_type']) ?></p>
                <p>From: <?= htmlspecialchars($active_trip['depart_city'] . ', ' . $active_trip['depart_state']) ?></p>
                <?php if ($active_trip['arrive_city']): ?><p>To: <?= htmlspecialchars($active_trip['arrive_city'] . ', ' . $active_trip['arrive_state']) ?></p><?php endif; ?>
                <p>Driver/Pilot: <?= htmlspecialchars($active_trip['employee_name'] ?? '—') ?> <?php if (!empty($active_trip['employee_email'])): ?>(<?= htmlspecialchars($active_trip['employee_email']) ?>)<?php endif; ?></p>
                <p>Departed: <?= date('M d, Y H:i', strtotime($active_trip['depart_time'])) ?></p>
                <a href="../trips/view_trip.php?id=<?= (int)$active_trip['trip_id'] ?>" class="btn btn-secondary"><i class="fas fa-arrow-up-right-from-square"></i> View trip</a>
            </div>
        </div>
        <?php endif; ?>

        <div class="grid2">
            <div class="card">
                <div class="card-header"><i class="fas fa-credit-card"></i> Payments</div>
                <div class="card-body">
                    <?php if ($payments && $payments->num_rows > 0): ?>
                        <table>
                            <thead><tr><th>Invoice</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th><th>Facility</th></tr></thead>
                            <tbody>
                                <?php while ($pay = $payments->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($pay['invoice_number']) ?></td>
                                    <td>$<?= number_format($pay['amount'], 2) ?></td>
                                    <td><?= htmlspecialchars($pay['payment_method']) ?></td>
                                    <td><?= htmlspecialchars($pay['transaction_status']) ?></td>
                                    <td><?= date('M d, Y', strtotime($pay['payment_date'])) ?></td>
                                    <td><?= $pay['facility_id'] ? (int)$pay['facility_id'] : '—' ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="muted">No payment records.</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><i class="fas fa-headset"></i> Support tickets</div>
                <div class="card-body">
                    <?php if ($tickets && $tickets->num_rows > 0): ?>
                        <table>
                            <thead><tr><th>ID</th><th>Issue</th><th>Status</th><th>Assigned</th><th></th></tr></thead>
                            <tbody>
                                <?php while ($t = $tickets->fetch_assoc()): ?>
                                <tr>
                                    <td>#<?= (int)$t['ticket_id'] ?></td>
                                    <td><?= htmlspecialchars($t['issue_type']) ?></td>
                                    <td><?= htmlspecialchars($t['status']) ?></td>
                                    <td><?= htmlspecialchars($t['assigned_name'] ?? '—') ?></td>
                                    <td><a href="../support/view_ticket.php?id=<?= (int)$t['ticket_id'] ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 0.8rem;">View</a></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="muted">No support tickets for this package.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($package_info['status'] != 'Delivered' && $estimated_delivery): ?>
        <p class="muted" style="margin-top: 8px;">Estimated delivery: <?= htmlspecialchars($estimated_delivery) ?></p>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
