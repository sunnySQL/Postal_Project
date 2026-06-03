<?php
session_start();
require_once 'db_connect.php';
require_once 'functions.php';

//enable error reporting
check_password_change();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || 
    ($_SESSION['role'] != 'Employee' && $_SESSION['role'] != 'Admin')) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$name = $_SESSION['name'];
$adminAlerts = [];

// Mark notification(s) as read (Recent Alerts)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['mark_notification_read'])) {
        $nid = (int) ($_POST['notification_id'] ?? 0);
        if ($nid > 0) {
            $u = $conn->prepare("UPDATE Notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
            $u->bind_param("ii", $nid, $user_id);
            $u->execute();
        }
        header('Location: employee_dashboard.php#recent-alerts');
        exit();
    }
    if (!empty($_POST['mark_all_notifications_read'])) {
        $u = $conn->prepare("UPDATE Notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        $u->bind_param("i", $user_id);
        $u->execute();
        header('Location: employee_dashboard.php#recent-alerts');
        exit();
    }
}

// Get employee info
$stmt = $conn->prepare("SELECT e.*, f.city, f.type 
                      FROM Employee e 
                      JOIN Facility f ON e.facility_id = f.facility_id 
                      WHERE e.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$employee = $stmt->get_result()->fetch_assoc();

// Manager specific data
$facility_id = $employee['facility_id'];
$manager_data = [];

// Check for unread admin messages for non-admin users
$unread_admin_messages = 0;
if ($role != 'Admin') {
    $msg_stmt = $conn->prepare("SELECT COUNT(*) as count FROM Admin_Messages WHERE sender_id = ? AND status = 'Replied'");
    $msg_stmt->bind_param("i", $user_id);
    $msg_stmt->execute();
    $unread_admin_messages = $msg_stmt->get_result()->fetch_assoc()['count'] ?? 0;
}

// Check if there are package processing notifications
$package_processed = isset($_GET['package']) && $_GET['package'] === 'processed';

if ($employee['role'] == 'Manager') {
    // Get packages by status for this facility
    $package_status_query = "SELECT status, COUNT(*) as count 
                          FROM Package 
                          WHERE facility_id = ? 
                          GROUP BY status";
    $stmt = $conn->prepare($package_status_query);
    $stmt->bind_param("i", $facility_id);
    $stmt->execute();
    $manager_data['package_status'] = $stmt->get_result();
    
    // Get inventory alerts (low stock items)
    $low_stock_query = "SELECT i.inventory_id, it.name, i.quantity, i.min_stock_level 
                      FROM Inventory i 
                      JOIN Items it ON i.item_id = it.item_id
                      JOIN Shop s ON i.shop_id = s.shop_id
                      WHERE s.facility_id = ? AND i.is_active = 1 AND i.quantity <= i.min_stock_level + 5
                      ORDER BY (i.quantity / i.min_stock_level) ASC
                      LIMIT 5";
    $stmt = $conn->prepare($low_stock_query);
    $stmt->bind_param("i", $facility_id);
    $stmt->execute();
    $manager_data['low_stock'] = $stmt->get_result();
    
    // Get top selling items
    $top_items_query = "SELECT it.name, SUM(ss.quantity) as total_sold, SUM(ss.sale_amount) as total_revenue
                      FROM Shop_Sale ss
                      JOIN Items it ON ss.item_id = it.item_id
                      JOIN Shop s ON ss.shop_id = s.shop_id
                      WHERE s.facility_id = ?
                      AND ss.sale_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                      GROUP BY ss.item_id
                      ORDER BY total_sold DESC
                      LIMIT 5";
    $stmt = $conn->prepare($top_items_query);
    $stmt->bind_param("i", $facility_id);
    $stmt->execute();
    $manager_data['top_items'] = $stmt->get_result();
    
    // Get employee performance data
    $emp_performance_query = "SELECT e.first_name, e.last_name, e.role,
                           COUNT(DISTINCT t.trip_id) as trips_completed,
                           COUNT(DISTINCT p.tracking_number) as packages_handled
                           FROM Employee e
                           LEFT JOIN Trip t ON e.user_id = t.employee_id
                           LEFT JOIN Tracking_History th ON e.user_id = th.employee_id
                           LEFT JOIN Package p ON th.tracking_number = p.tracking_number
                           WHERE e.facility_id = ? AND e.role = 'Driver'
                           GROUP BY e.user_id
                           ORDER BY packages_handled DESC
                           LIMIT 5";
    $stmt = $conn->prepare($emp_performance_query);
    $stmt->bind_param("i", $facility_id);
    $stmt->execute();
    $manager_data['employee_performance'] = $stmt->get_result();
    
    // Get recent trips data
    $trips_query = "SELECT t.trip_id, t.trip_type, t.depart_time, t.arrival_time, 
                  d.city as departure_city, a.city as arrival_city,
                  e.first_name, e.last_name,
                  COUNT(tp.tracking_number) as package_count
                  FROM Trip t
                  JOIN Facility d ON t.depart_facility_id = d.facility_id
                  LEFT JOIN Facility a ON t.arrive_facility_id = a.facility_id
                  LEFT JOIN Employee e ON t.employee_id = e.user_id
                  LEFT JOIN Trip_Package tp ON t.trip_id = tp.trip_id
                  WHERE (t.depart_facility_id = ? OR t.arrive_facility_id = ?)
                  GROUP BY t.trip_id
                  ORDER BY t.depart_time DESC
                  LIMIT 5";
    $stmt = $conn->prepare($trips_query);
    $stmt->bind_param("ii", $facility_id, $facility_id);
    $stmt->execute();
    $manager_data['trips'] = $stmt->get_result();
    
    // Get daily volume statistics (packages created at facility by day)
    $daily_volume_query = "SELECT DATE(timestamp_created) as date, 
                         COUNT(*) as package_count, 
                         COALESCE(SUM(postage), 0) as daily_revenue
                         FROM Package
                         WHERE facility_id = ? 
                         AND timestamp_created >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                         GROUP BY DATE(timestamp_created)
                         ORDER BY date DESC";
    $stmt = $conn->prepare($daily_volume_query);
    $stmt->bind_param("i", $facility_id);
    $stmt->execute();
    $manager_data['daily_volume'] = $stmt->get_result();
    
    // Revenue (7 days) from payments at this facility — so the stat updates when payments are made
    $weekly_revenue_stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM Package_Payment WHERE facility_id = ? AND payment_date >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND transaction_status = 'Completed'");
    $weekly_revenue_stmt->bind_param("i", $facility_id);
    $weekly_revenue_stmt->execute();
    $manager_data['weekly_revenue_total'] = $weekly_revenue_stmt->get_result()->fetch_assoc()['total'] ?? 0;
    
    // Total count of active support tickets for this facility (separate from the limited list)
    $active_tickets_count_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM Support_Ticket st LEFT JOIN Package p ON st.package_id = p.tracking_number LEFT JOIN Employee e ON st.assigned_employee_id = e.user_id WHERE st.status != 'Resolved' AND (p.facility_id = ? OR e.facility_id = ?)");
    $active_tickets_count_stmt->bind_param("ii", $facility_id, $facility_id);
    $active_tickets_count_stmt->execute();
    $manager_data['active_support_ticket_count'] = (int) ($active_tickets_count_stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
    
    // Get support tickets for this facility (list, limit 5)
    $support_query = "SELECT st.ticket_id, st.issue_type, st.status, 
                   p.tracking_number
                   FROM Support_Ticket st
                   LEFT JOIN Package p ON st.package_id = p.tracking_number
                   LEFT JOIN Employee e ON st.assigned_employee_id = e.user_id
                   WHERE st.status != 'Resolved' AND (p.facility_id = ? OR e.facility_id = ?)
                   ORDER BY st.ticket_id DESC
                   LIMIT 5";
    $stmt = $conn->prepare($support_query);
    $stmt->bind_param("ii", $facility_id, $facility_id);
    $stmt->execute();
    $manager_data['support_tickets'] = $stmt->get_result();

    // Get past notification alerts for this manager (last 10)
    $notif_stmt = $conn->prepare(
        "SELECT notification_id, message, type, related_id, is_read, created_at
         FROM Notifications
         WHERE user_id = ?
         ORDER BY created_at DESC
         LIMIT 10"
    );
    $notif_stmt->bind_param("i", $user_id);
    $notif_stmt->execute();
    $manager_data['notifications'] = $notif_stmt->get_result();
}

// Get support tickets
$tickets_query = "SELECT t.*, c.first_name, c.last_name 
                FROM Support_Ticket t
                LEFT JOIN Customer c ON t.user_id = c.user_id
                WHERE t.assigned_employee_id = ? AND t.status != 'Resolved'
                ORDER BY t.ticket_id DESC";
$stmt = $conn->prepare($tickets_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$tickets = $stmt->get_result();

// For Drivers, get trips (no GROUP BY so strict SQL mode doesn't drop rows; use subquery for package count)
$trips = [];
if ($employee['role'] == 'Driver' || $employee['role'] == 'Pilot') {
    $trips_query = "SELECT t.*, 
                  d.city as departure_city, 
                  a.city as arrival_city,
                  (SELECT COUNT(*) FROM Trip_Package tp WHERE tp.trip_id = t.trip_id) as package_count
                  FROM Trip t
                  JOIN Facility d ON t.depart_facility_id = d.facility_id
                  LEFT JOIN Facility a ON t.arrive_facility_id = a.facility_id
                  WHERE t.employee_id = ? AND t.arrival_time IS NULL
                  ORDER BY t.depart_time ASC";
    $stmt = $conn->prepare($trips_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $trips = $stmt->get_result();
    
    // Get additional driver metrics
    $driver_data = [];
    
    // Get today's route information
    if ($trips->num_rows > 0) {
        $trips->data_seek(0);
        $current_trip = $trips->fetch_assoc();
        $trip_id = $current_trip['trip_id'];
        
        // Get package status breakdown for current trip
        $package_status_query = "SELECT p.status, COUNT(*) as count 
                              FROM Trip_Package tp 
                              JOIN Package p ON tp.tracking_number = p.tracking_number
                              WHERE tp.trip_id = ?
                              GROUP BY p.status";
        $stmt = $conn->prepare($package_status_query);
        $stmt->bind_param("i", $trip_id);
        $stmt->execute();
        $driver_data['package_status'] = $stmt->get_result();
        
        // Get delivery details if it's a delivery route
        if ($current_trip['is_delivery_route'] == 1) {
            // Get delivery progress
            $delivery_progress_query = "SELECT 
                                     COUNT(CASE WHEN p.status = 'Delivered' THEN 1 END) as delivered_count,
                                     COUNT(CASE WHEN p.status = 'Failed' THEN 1 END) as failed_count,
                                     COUNT(*) as total_count
                                   FROM Trip_Package tp 
                                   JOIN Package p ON tp.tracking_number = p.tracking_number
                                   WHERE tp.trip_id = ?";
            $stmt = $conn->prepare($delivery_progress_query);
            $stmt->bind_param("i", $trip_id);
            $stmt->execute();
            $driver_data['delivery_progress'] = $stmt->get_result()->fetch_assoc();
            
            // Get stops organized by postal code
            $stops_query = "SELECT c.postal_code, c.city, COUNT(*) as package_count
                         FROM Trip_Package tp 
                         JOIN Package p ON tp.tracking_number = p.tracking_number
                         JOIN Customer c ON p.receiver_id = c.user_id
                         WHERE tp.trip_id = ? AND p.status NOT IN ('Delivered', 'Failed')
                         GROUP BY c.postal_code
                         ORDER BY c.postal_code ASC";
            $stmt = $conn->prepare($stops_query);
            $stmt->bind_param("i", $trip_id);
            $stmt->execute();
            $driver_data['stops'] = $stmt->get_result();
        }
    }
    
    // Get driver performance metrics
    $performance_query = "SELECT 
                       (SELECT COUNT(*) FROM Trip WHERE employee_id = ? AND arrival_time IS NOT NULL) as completed_trips,
                       (SELECT COUNT(*) FROM Trip_Package tp 
                        JOIN Trip t ON tp.trip_id = t.trip_id
                        WHERE t.employee_id = ?) as packages_delivered,
                       (SELECT COUNT(*) FROM Tracking_History WHERE employee_id = ? AND action = 'Delivery') as delivery_scans,
                       (SELECT COUNT(DISTINCT DATE(depart_time)) FROM Trip WHERE employee_id = ? AND arrival_time IS NOT NULL) as days_worked
                       ";
    $stmt = $conn->prepare($performance_query);
    $stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
    $stmt->execute();
    $driver_data['performance'] = $stmt->get_result()->fetch_assoc();
    
    // Get trip history
    $history_query = "SELECT t.trip_id, t.depart_time, t.arrival_time, 
                   d.city as departure_city, 
                   a.city as arrival_city,
                   COUNT(tp.tracking_number) as package_count,
                   t.is_delivery_route
                 FROM Trip t
                 JOIN Facility d ON t.depart_facility_id = d.facility_id
                 LEFT JOIN Facility a ON t.arrive_facility_id = a.facility_id
                 LEFT JOIN Trip_Package tp ON t.trip_id = tp.trip_id
                 WHERE t.employee_id = ? AND t.arrival_time IS NOT NULL
                 GROUP BY t.trip_id
                 ORDER BY t.depart_time DESC
                 LIMIT 5";
    $stmt = $conn->prepare($history_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $driver_data['history'] = $stmt->get_result();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap');
        
        * {
            font-family: 'Open Sans', sans-serif;
        }
        
        body {
            background-color: #f8f9fa;
        }
        
        .top-nav {
            background: #004B87;
            color: white;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 40px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.25);
        }
        .pp-logo {
            font-weight: 800;
            font-size: 1.5rem;
            color: #fff;
            letter-spacing: -0.5px;
            text-decoration: none;
            transition: color 0.2s;
            flex-shrink: 0;
        }
        .pp-logo:hover { color: #DA291C; }
        .pp-nav-links {
            display: flex;
            list-style: none;
            gap: 4px;
            margin: 0;
            padding: 0;
            align-items: center;
        }
        .pp-nav-links li a {
            color: #fff;
            font-size: 0.92rem;
            padding: 7px 13px;
            border-radius: 4px;
            text-decoration: none;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }
        .pp-nav-links li a:hover { background: rgba(255,255,255,0.15); }
        .pp-nav-cta {
            background: #DA291C !important;
            font-weight: 600;
        }
        .pp-nav-cta:hover { background: #b52218 !important; }
        
        .action-btn {
            background-color: #004B87;
            transition: background-color 0.3s;
        }
        
        .action-btn:hover {
            background-color: #003366;
        }
        
        .accent-btn {
            background-color: #DA291C;
            transition: background-color 0.3s;
        }
        
        .accent-btn:hover {
            background-color: #b52218;
        }
        
        .section-title {
            color: #004B87;
        }
    </style>
</head>
<body class="bg-gray-50">
    <nav class="top-nav fixed w-full top-0 left-0 z-50">
        <a href="index.php" class="pp-logo">POSTAL PRO</a>
        <ul class="pp-nav-links">
            <li><a href="employee_dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a></li>
            <li><a href="logout.php" class="pp-nav-cta"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </nav>

    <div class="container mx-auto px-4 py-8 max-w-6xl mt-[60px]">

        <?php if (!empty($_SESSION['login_notif_count'])): ?>
        <div id="loginNotifBanner" class="flex items-center gap-3 bg-blue-50 border border-blue-200 text-blue-800 rounded-lg px-4 py-3 mb-5 text-sm">
            <i class="fas fa-bell text-[#004B87]"></i>
            <span class="flex-1">You have <strong><?= (int)$_SESSION['login_notif_count'] ?> unread alert<?= $_SESSION['login_notif_count'] > 1 ? 's' : '' ?></strong> since your last login.</span>
            <button onclick="document.getElementById('loginNotifBanner').style.display='none';<?php unset($_SESSION['login_notif_count']); ?>" class="opacity-50 hover:opacity-100 text-lg leading-none">&times;</button>
        </div>
        <?php unset($_SESSION['login_notif_count']); ?>
        <?php endif; ?>

        <header class="mb-6">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center">
                <h1 class="text-3xl font-bold section-title">Employee Dashboard</h1>
                <div class="mt-4 md:mt-0 bg-white shadow-sm p-4 rounded-lg">
                    <p class="mb-1"><span class="font-semibold">Welcome,</span> <?= htmlspecialchars($name) ?> | <span class="font-medium"><?= htmlspecialchars($employee['role']) ?></span></p>
                    <p><span class="font-semibold">Facility:</span> <?= htmlspecialchars($employee['type']) ?> - <?= htmlspecialchars($employee['city']) ?></p>
                </div>
            </div>
        </header>
        
        <nav class="bg-white shadow-sm p-4 rounded-lg mb-6">
            <ul class="flex flex-wrap space-x-2 md:space-x-6">
                <?php if ($role == 'Admin'): ?>
                <li><a href="admin/manage_users.php" class="text-gray-700 hover:text-[#DA291C]">Manage Users</a></li>
                <li><a href="admin/manage_facilities.php" class="text-gray-700 hover:text-[#DA291C]">Manage Facilities</a></li>
                <li><a href="admin/manage_vehicles.php" class="text-gray-700 hover:text-[#DA291C]">Manage Vehicles</a></li>
                <li><a href="admin/manage_items.php" class="text-gray-700 hover:text-[#DA291C]">Manage Items</a></li>
                <li><a href="admin/inbox.php" class="text-gray-700 hover:text-[#DA291C]">Admin Inbox</a></li>
                <li><a href="admin/manage_notices.php" class="text-gray-700 hover:text-[#DA291C]">Service Notices</a></li>
                <li><a href="admin/audit_log.php" class="text-gray-700 hover:text-[#DA291C] font-semibold flex items-center gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                    Audit Log
                </a></li>
                <?php else: ?>
                <li><a href="./employee/contact_admin.php" class="text-gray-700 hover:text-[#DA291C] flex items-center">
                    Contact Admin
                    <?php if ($unread_admin_messages > 0): ?>
                        <span class="ml-2 bg-red-600 text-white text-xs px-2 py-1 rounded-full"><?= $unread_admin_messages ?></span>
                    <?php endif; ?>
                </a></li>
                <?php endif; ?>
                <?php if ($employee['role'] == 'Clerk'): ?>
                <li><a href="package/new_package.php" class="text-gray-700 hover:text-[#DA291C]">Create Shipment</a></li>
                <li><a href="shop/shop_dashboard.php" class="text-gray-700 hover:text-[#DA291C]">Shop Overview</a></li>
                <li><a href="package/search.php" class="text-gray-700 hover:text-[#DA291C]">Package Search &amp; Scan</a></li>
                <?php endif; ?>
                <?php if ($employee['role'] == 'Manager'): ?>
                <li><a href="shop/shop_dashboard.php" class="text-gray-700 hover:text-[#DA291C]">Shop Overview</a></li>
                <li><a href="reports/index.php" class="text-gray-700 hover:text-[#DA291C]">Reports</a></li>
                <?php endif; ?>
                <?php if ($employee['role'] == 'Manager'): ?>
                <li><a href="trips/manage_trips.php" class="text-gray-700 hover:text-[#DA291C]">Manage Trips</a></li>
                <li><a href="manager_audit.php" class="text-gray-700 hover:text-[#DA291C] font-semibold flex items-center gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                    Audit Log
                </a></li>
                <?php endif; ?>
                
                <?php if ($employee['role'] == 'Clerk'): ?>
                <li><a href="package/awaiting_pickup.php" class="text-gray-700 hover:text-[#DA291C]">Pickup</a></li>
                <?php endif; ?>
                <!-- Add Edit Profile link for everyone except Admins (who already have the Manage Users option) -->
                <?php if ($role != 'Admin'): ?>
                <li><a href="edit_profile.php" class="text-gray-700 hover:text-[#DA291C]">Edit Profile</a></li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php /* Admin low stock alerts section removed as requested
        if ($role == 'Admin' && !empty($adminAlerts)) : ?>
    <div class="bg-red-600 text-white p-4 rounded-lg mb-6 font-semibold">
        <h2 class="text-lg mb-2">⚠️ Low Stock Alerts</h2>
        <ul class="list-disc pl-5 space-y-1">
            <?php foreach ($adminAlerts as $alert): ?>
                <li><?= htmlspecialchars($alert) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; */ ?>


        <?php if ($package_processed): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded mb-6">
            <p>The package has been entered into the system and payment has been recorded.</p>
    </div>
<?php endif; ?>
        
        <main>

                    <?php if ($role == 'Admin'): ?>
            <!-- Admin Dashboard Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6 mx-auto max-w-5xl">
                <!-- Total Users Stat Card -->
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white p-5 rounded-lg shadow-md">
                    <?php
                    // Get total users count
                    $users_stmt = $conn->query("SELECT (SELECT COUNT(*) FROM Employee) + (SELECT COUNT(*) FROM Customer) as count");
                    $users_count = $users_stmt->fetch_assoc()['count'];
                    ?>
                    <div class="flex items-center">
                        <div class="rounded-full bg-blue-400 bg-opacity-30 p-3 mr-4">
                            <i class="fas fa-users text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold">Total Users</h3>
                            <p class="text-3xl font-bold"><?= number_format($users_count) ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Facilities Stat Card -->
                <div class="bg-gradient-to-r from-green-500 to-green-600 text-white p-5 rounded-lg shadow-md">
                    <?php
                    // Get facilities count
                    $facilities_stmt = $conn->query("SELECT COUNT(*) as count FROM Facility");
                    $facilities_count = $facilities_stmt->fetch_assoc()['count'];
                    ?>
                    <div class="flex items-center">
                        <div class="rounded-full bg-green-400 bg-opacity-30 p-3 mr-4">
                            <i class="fas fa-building text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold">Facilities</h3>
                            <p class="text-3xl font-bold"><?= number_format($facilities_count) ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Packages Stat Card -->
                <div class="bg-gradient-to-r from-purple-500 to-purple-600 text-white p-5 rounded-lg shadow-md">
                    <?php
                    // Get total packages count
                    $packages_stmt = $conn->query("SELECT COUNT(*) as count FROM Package");
                    $packages_count = $packages_stmt->fetch_assoc()['count'];
                    ?>
                    <div class="flex items-center">
                        <div class="rounded-full bg-purple-400 bg-opacity-30 p-3 mr-4">
                            <i class="fas fa-box text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold">Total Packages</h3>
                            <p class="text-3xl font-bold"><?= number_format($packages_count) ?></p>
                        </div>
                    </div>
                </div>
                
            </div>

            <!-- System Overview -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <!-- Package Status Distribution -->
                <section class="bg-white p-6 rounded-lg shadow-md">
                    <h2 class="text-xl font-semibold mb-4">Package Status Distribution</h2>
                    <?php
                    // Get package status distribution
                    $status_stmt = $conn->query("SELECT status, COUNT(*) as count FROM Package GROUP BY status ORDER BY count DESC");
                    $statuses = [];
                    $total = 0;
                    while($row = $status_stmt->fetch_assoc()) {
                        $statuses[] = $row;
                        $total += $row['count'];
                    }
                    
                    $colors = [
                        'In Transit' => 'bg-blue-100 text-blue-800',
                        'Pending' => 'bg-yellow-100 text-yellow-800',
                        'Delivered' => 'bg-green-100 text-green-800',
                        'Processing' => 'bg-indigo-100 text-indigo-800',
                        'Out for Delivery' => 'bg-orange-100 text-orange-800',
                        'Ready for Pickup' => 'bg-purple-100 text-purple-800'
                    ];
                    ?>
                    
                    <div class="flex flex-wrap gap-3 mb-4">
                        <?php foreach($statuses as $status): 
                            $color = $colors[$status['status']] ?? 'bg-gray-100 text-gray-800';
                            $percentage = $total > 0 ? round(($status['count'] / $total) * 100) : 0;
                        ?>
                        <div class="flex-1 min-w-[150px] bg-gray-50 p-4 rounded-md text-center">
                            <p class="text-2xl font-bold"><?= number_format($status['count']) ?></p>
                            <div class="<?= $color ?> px-3 py-1 rounded-full text-xs inline-block mt-2">
                                <?= htmlspecialchars($status['status']) ?>
                            </div>
                            <div class="mt-2 text-xs text-gray-500"><?= $percentage ?>% of total</div>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if(empty($statuses)): ?>
                            <p class="text-gray-500">No package data available</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mt-4 text-right">
                        <a href="reports/index.php" class="text-blue-600 hover:text-blue-800 text-sm">
                            View Detailed Reports →
                        </a>
                    </div>
                </section>

                <!-- System Activity -->
                <section class="bg-white p-6 rounded-lg shadow-md">
                    <h2 class="text-xl font-semibold mb-4">Recent System Activity</h2>
                    <?php
                    // Get recent tracking history as system activity
                    $activity_stmt = $conn->query("
                        SELECT th.action, th.timestamp, p.tracking_number, e.first_name, e.last_name, e.role
                        FROM Tracking_History th
                        JOIN Package p ON th.tracking_number = p.tracking_number
                        JOIN Employee e ON th.employee_id = e.user_id
                        ORDER BY th.timestamp DESC
                        LIMIT 5
                    ");
                    ?>
                    
                    <div class="space-y-3">
                        <?php if($activity_stmt && $activity_stmt->num_rows > 0): 
                            while($activity = $activity_stmt->fetch_assoc()): 
                                $time_diff = time() - strtotime($activity['timestamp']);
                                if($time_diff < 60) {
                                    $time_ago = "Just now";
                                } elseif($time_diff < 3600) {
                                    $time_ago = floor($time_diff/60) . " mins ago";
                                } elseif($time_diff < 86400) {
                                    $time_ago = floor($time_diff/3600) . " hours ago";
                                } else {
                                    $time_ago = floor($time_diff/86400) . " days ago";
                                }
                        ?>
                        <div class="border-l-4 border-blue-500 pl-3 py-2">
                            <div class="flex justify-between">
                                <div>
                                    <p class="font-medium"><?= htmlspecialchars($activity['action']) ?></p>
                                    <p class="text-sm text-gray-600">
                                        Package: <?= htmlspecialchars($activity['tracking_number']) ?>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm text-gray-600">
                                        <?= htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']) ?>
                                    </p>
                                    <p class="text-xs text-gray-500"><?= $time_ago ?></p>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                        <?php else: ?>
                        <p class="text-gray-500">No recent activity available</p>
                    <?php endif; ?>
                    </div>
                    
                    <div class="mt-4 text-right">
                        <a href="admin/system_logs.php" class="text-blue-600 hover:text-blue-800 text-sm">
                            View Full Activity Log →
                        </a>
                    </div>
                </section>
            </div>

            <!-- Inventory & Support Overview -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <!-- Low Stock Items -->
                <section class="bg-white p-6 rounded-lg shadow-md">
                    <h2 class="text-xl font-semibold mb-4">Inventory Alerts</h2>
                    <?php
                    // Get low stock items across all shops
                    $inventory_stmt = $conn->query("
                        SELECT i.item_id, i.name, inv.quantity, inv.min_stock_level, s.shop_id, f.city
                        FROM Inventory inv
                        JOIN Items i ON inv.item_id = i.item_id
                        JOIN Shop s ON inv.shop_id = s.shop_id
                        JOIN Facility f ON s.facility_id = f.facility_id
                        WHERE inv.is_active = 1 AND inv.quantity <= inv.min_stock_level
                        ORDER BY (inv.quantity / inv.min_stock_level) ASC
                        LIMIT 5
                    ");
                    ?>
                    
                    <?php if($inventory_stmt && $inventory_stmt->num_rows > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="px-4 py-2 text-left">Item</th>
                                    <th class="px-4 py-2 text-left">Location</th>
                                    <th class="px-4 py-2 text-left">Stock</th>
                                    <th class="px-4 py-2 text-left">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($item = $inventory_stmt->fetch_assoc()): 
                                    $stock_level = $item['quantity'] / $item['min_stock_level'];
                                    $status_class = $item['quantity'] == 0 ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800';
                                    $status_text = $item['quantity'] == 0 ? 'Out of Stock' : 'Low Stock';
                                ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="px-4 py-2"><?= htmlspecialchars($item['name']) ?></td>
                                    <td class="px-4 py-2"><?= htmlspecialchars($item['city']) ?></td>
                                    <td class="px-4 py-2"><?= $item['quantity'] ?> / <?= $item['min_stock_level'] ?></td>
                                    <td class="px-4 py-2">
                                        <span class="<?= $status_class ?> px-2 py-1 rounded-full text-xs">
                                            <?= $status_text ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-gray-500">No inventory alerts at this time</p>
                    <?php endif; ?>
                    
                </section>

                <!-- Support Tickets -->
                <section class="bg-white p-6 rounded-lg shadow-md">
                    <h2 class="text-xl font-semibold mb-4">Support Ticket Overview</h2>
                    
                    <?php
                    // Get support ticket stats
                    $tickets_stmt = $conn->query("
                        SELECT status, COUNT(*) as count 
                        FROM Support_Ticket 
                        GROUP BY status
                    ");
                    
                    $ticket_stats = [
                        'Open' => 0,
                        'In Progress' => 0,
                        'Resolved' => 0
                    ];
                    
                    if($tickets_stmt) {
                        while($row = $tickets_stmt->fetch_assoc()) {
                            if(isset($ticket_stats[$row['status']])) {
                                $ticket_stats[$row['status']] = $row['count'];
                            }
                        }
                    }
                    
                    // Get recent unresolved tickets
                    $recent_tickets_stmt = $conn->query("
                        SELECT t.ticket_id, t.issue_type, t.status, c.first_name, c.last_name
                        FROM Support_Ticket t
                        LEFT JOIN Customer c ON t.user_id = c.user_id
                        WHERE t.status != 'Resolved'
                        ORDER BY t.ticket_id DESC
                        LIMIT 3
                    ");
                    
                    // Create a sample ticket if none exist
                    $check_tickets = $conn->query("SELECT COUNT(*) as count FROM Support_Ticket");
                    if ($check_tickets && $check_tickets->fetch_assoc()['count'] == 0 && $role == 'Admin') {
                        // Get a random package and customer
                        $package_query = $conn->query("SELECT tracking_number, receiver_id FROM Package LIMIT 1");
                        if ($package_query && $package_query->num_rows > 0) {
                            $package = $package_query->fetch_assoc();
                            $tracking_number = $package['tracking_number'];
                            $user_id = $package['receiver_id'];
                            
                            // Insert a sample ticket
                            $insert_query = "INSERT INTO Support_Ticket 
                                (ticket_id, user_id, package_id, issue_type, status, assigned_employee_id) 
                                VALUES (1, ?, ?, 'Delayed', 'Open', ?)";
                            $stmt = $conn->prepare($insert_query);
                            $stmt->bind_param("isi", $user_id, $tracking_number, $_SESSION['user_id']);
                            $stmt->execute();
                            
                            // Refresh the queries
                            $tickets_stmt = $conn->query("
                                SELECT status, COUNT(*) as count 
                                FROM Support_Ticket 
                                GROUP BY status
                            ");
                            
                            $recent_tickets_stmt = $conn->query("
                                SELECT t.ticket_id, t.issue_type, t.status, c.first_name, c.last_name
                                FROM Support_Ticket t
                                LEFT JOIN Customer c ON t.user_id = c.user_id
                                WHERE t.status != 'Resolved'
                                ORDER BY t.ticket_id DESC
                                LIMIT 3
                            ");
                            
                            if($tickets_stmt) {
                                while($row = $tickets_stmt->fetch_assoc()) {
                                    if(isset($ticket_stats[$row['status']])) {
                                        $ticket_stats[$row['status']] = $row['count'];
                                    }
                                }
                            }
                        }
                    }
                    ?>
                    
                    <div class="grid grid-cols-3 gap-3 mb-4">
                        <?php foreach($ticket_stats as $status => $count): 
                            $color_class = 'bg-gray-100';
                            switch($status) {
                                case 'Open': $color_class = 'bg-blue-100'; break;
                                case 'In Progress': $color_class = 'bg-yellow-100'; break;
                                case 'Resolved': $color_class = 'bg-green-100'; break;
                            }
                        ?>
                        <div class="<?= $color_class ?> p-3 rounded-lg text-center">
                            <p class="text-lg font-bold"><?= $count ?></p>
                            <p class="text-xs"><?= $status ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if($recent_tickets_stmt && $recent_tickets_stmt->num_rows > 0): ?>
                    <h3 class="font-medium text-sm uppercase text-gray-500 mt-4 mb-2">Recent Unresolved Tickets</h3>
                    <div class="space-y-2">
                        <?php while($ticket = $recent_tickets_stmt->fetch_assoc()): 
                            $status_class = '';
                            switch($ticket['status']) {
                                case 'Open': $status_class = 'bg-blue-100 text-blue-800'; break;
                                case 'In Progress': $status_class = 'bg-yellow-100 text-yellow-800'; break;
                                case "Resolved": $status_class = "bg-green-100 text-green-800"; break;
                                default: $status_class = 'bg-gray-100 text-gray-800';
                            }
                        ?>
                        <div class="flex justify-between items-center border-b pb-2">
                            <div>
                                <p class="font-medium">Ticket #<?= $ticket['ticket_id'] ?></p>
                                <p class="text-sm text-gray-600">
                                    <?= htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']) ?>
                                </p>
                            </div>
                            <div class="text-right">
                                <span class="<?= $status_class ?> px-2 py-1 rounded-full text-xs">
                                    <?= $ticket['status'] ?>
                                </span>
                                <p class="text-xs text-gray-500 mt-1"><?= $ticket['issue_type'] ?></p>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mt-4 text-right">
                        <a href="admin/support_tickets.php" class="text-blue-600 hover:text-blue-800 text-sm">
                            View All Tickets →
                        </a>
                </div>
                </section>
            </div>
            <?php endif; ?>

            <?php if ($employee['role'] == 'Customer Support' || $role == 'Admin'): ?>
            
                
                <?php if ($tickets->num_rows > 0): ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-200 text-left">
                                <th class="px-4 py-2">Ticket ID</th>
                                <th class="px-4 py-2">Customer</th>
                                <th class="px-4 py-2">Issue Type</th>
                                <th class="px-4 py-2">Status</th>
                                <th class="px-4 py-2">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($ticket = $tickets->fetch_assoc()): ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-50">
                                <td class="px-4 py-2">#<?= htmlspecialchars($ticket['ticket_id']) ?></td>
                                <td class="px-4 py-2"><?= htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']) ?></td>
                                <td class="px-4 py-2"><?= htmlspecialchars($ticket['issue_type']) ?></td>
                                <td class="px-4 py-2">
                                    <span class="<?php
                                        switch($ticket['status']) {
                                            case 'Open': echo 'bg-green-100 text-green-800'; break;
                                            case 'In Progress': echo 'bg-blue-100 text-blue-800'; break;
                                            case 'Pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                            case 'Resolved': echo 'bg-gray-100 text-gray-800'; break;
                                            default: echo 'bg-gray-100 text-gray-800';
                                        }
                                    ?> px-2 py-1 rounded-full text-xs">
                                        <?= htmlspecialchars($ticket['status']) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-2">
                                    <a href="support/view_ticket.php?id=<?= htmlspecialchars($ticket['ticket_id']) ?>" 
                                       class="text-blue-600 hover:text-blue-800">View</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php else: ?>
                <div class="bg-white p-6 rounded-lg shadow-md">
                    <div class="text-center py-8">
                        <i class="fas fa-ticket-alt text-gray-300 text-6xl mb-4"></i>
                        <h3 class="text-xl font-semibold mb-2">No Support Tickets Assigned</h3>
                        <p class="text-gray-600 mb-6">You don't have any support tickets assigned to you at the moment.</p>
                    </div>
                </div>
                <?php endif; ?>
            </section>
            <?php endif; ?>
            

            
            <?php if ($employee['role'] == 'Driver' || $employee['role'] == 'Pilot'): ?>
            <!-- Driver Dashboard -->
            <?php if ($trips->num_rows > 0): ?>
            
            
            <?php 
            // Get delivery progress data for visualization
            $delivered_count = 0;
            $failed_count = 0;
            $pending_count = 0;
            $total_count = 0;
            $progress_percentage = 0;
            
            if (isset($driver_data['delivery_progress'])) {
                $delivered_count = $driver_data['delivery_progress']['delivered_count'];
                $failed_count = $driver_data['delivery_progress']['failed_count'];
                $pending_count = $driver_data['delivery_progress']['total_count'] - $delivered_count - $failed_count;
                $total_count = $driver_data['delivery_progress']['total_count'];
                if ($total_count > 0) {
                    $progress_percentage = round((($delivered_count + $failed_count) / $total_count) * 100);
                }
            }
            ?>
            
            <!-- Route Progress -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white p-6 rounded-lg shadow-md">
                    <h3 class="text-lg font-semibold mb-2">Today's Progress</h3>
                    <?php if ($progress_percentage > 0): ?>
                    <div class="relative pt-1">
                        <div class="overflow-hidden h-6 mb-2 text-xs flex rounded bg-blue-200">
                            <div style="width:<?= $progress_percentage ?>%" class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-blue-700">
                                <?= $progress_percentage ?>%
                            </div>
                        </div>
                        <div class="text-center">
                            <span class="text-sm font-medium"><?= $delivered_count + $failed_count ?> of <?= $total_count ?> Completed</span>
                        </div>
                    </div>
                    <?php else: ?>
                    <p class="text-3xl font-bold">Ready to Go</p>
                    <p class="text-sm mt-2">Your route is ready to start</p>
                    <?php endif; ?>
                </div>
                
                <div class="bg-gradient-to-r from-green-500 to-green-600 text-white p-6 rounded-lg shadow-md">
                    <h3 class="text-lg font-semibold mb-2">Package Status</h3>
                    <?php if (isset($driver_data['package_status']) && $driver_data['package_status']->num_rows > 0): ?>
                    <div class="flex justify-between">
                        <?php
                        $driver_data['package_status']->data_seek(0);
                        $status_counts = [
                            'Delivered' => 0,
                            'In Transit' => 0,
                            'Failed' => 0,
                            'Pending' => 0,
                            'Out for Delivery' => 0
                        ];
                        
                        while ($status = $driver_data['package_status']->fetch_assoc()) {
                            if (isset($status_counts[$status['status']])) {
                                $status_counts[$status['status']] = $status['count'];
                            }
                        }
                        ?>
                        <div class="text-center">
                            <p class="text-3xl font-bold"><?= $status_counts['Delivered'] ?></p>
                            <p class="text-sm">Delivered</p>
                        </div>
                        <div class="text-center">
                            <p class="text-3xl font-bold"><?= $status_counts['Failed'] ?></p>
                            <p class="text-sm">Failed</p>
                        </div>
                        <div class="text-center">
                            <p class="text-3xl font-bold"><?= $status_counts['Pending'] + $status_counts['In Transit'] + $status_counts['Out for Delivery'] ?></p>
                            <p class="text-sm">Remaining</p>
                        </div>
                    </div>
                    <?php else: ?>
                    <p class="text-3xl font-bold">0</p>
                    <p class="text-sm mt-2">No package data available</p>
                    <?php endif; ?>
                </div>
                
                <div class="bg-gradient-to-r from-purple-500 to-purple-600 text-white p-6 rounded-lg shadow-md">
                    <h3 class="text-lg font-semibold mb-2">Your Performance</h3>
                    <?php if (isset($driver_data['performance'])): ?>
                    <div class="flex flex-col">
                        <div class="flex justify-between mb-1">
                            <span>Trips Completed:</span>
                            <span class="font-bold"><?= $driver_data['performance']['completed_trips'] ?></span>
                        </div>
                        <div class="flex justify-between mb-1">
                            <span>Pkgs Delivered:</span>
                            <span class="font-bold"><?= $driver_data['performance']['packages_delivered'] ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span>Days Worked:</span>
                            <span class="font-bold"><?= $driver_data['performance']['days_worked'] ?></span>
                        </div>
                    </div>
                    <?php else: ?>
                    <p class="text-3xl font-bold">New Driver</p>
                    <p class="text-sm mt-2">No performance data yet</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <!-- Route Information -->
                <section class="bg-white p-6 rounded-lg shadow-md">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-semibold">Today's Route</h2>
                        <span class="<?= $current_trip['is_delivery_route'] ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' ?> px-3 py-1 rounded-full text-xs font-medium">
                            <?= $current_trip['is_delivery_route'] ? 'Delivery Route' : ($current_trip['trip_type'] == 'Air' ? 'Air Transport' : 'Ground Transport') ?>
                        </span>
                    </div>
                    <div class="mb-4">
                        <div class="flex items-center mb-2">
                            <i class="fas fa-map-marker-alt text-red-500 mr-2"></i>
                            <span class="font-medium">From:</span>
                            <span class="ml-2"><?= htmlspecialchars($current_trip['departure_city']) ?></span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-flag-checkered text-green-500 mr-2"></i>
                            <span class="font-medium">To:</span>
                            <span class="ml-2"><?= htmlspecialchars($current_trip['arrival_city'] ?? ($current_trip['is_delivery_route'] ? 'Delivery route' : '—')) ?></span>
                        </div>
                    </div>
                    
                    <?php if (isset($driver_data['stops']) && $driver_data['stops']->num_rows > 0): ?>
                    <div class="mt-4">
                        <h3 class="text-lg font-medium mb-2">Delivery Stops</h3>
                        <div class="space-y-2">
                            <?php 
                            $driver_data['stops']->data_seek(0);
                            while ($stop = $driver_data['stops']->fetch_assoc()): 
                            ?>
                            <div class="flex items-center bg-gray-50 p-2 rounded">
                                <div class="bg-blue-100 text-blue-800 rounded-full h-6 w-6 flex items-center justify-center mr-2 text-xs font-bold">
                                    <?= $stop['package_count'] ?>
                                </div>
                                <div>
                                    <span class="font-medium text-gray-700"><?= htmlspecialchars($stop['city']) ?></span>
                                    <span class="text-xs text-gray-500 ml-2">(<?= htmlspecialchars($stop['postal_code']) ?>)</span>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="mt-4">
                        <a href="trips/manage_trips.php" class="accent-btn text-white px-4 py-2 rounded inline-block">
                            Manage Trips
                        </a>
                    </div>
                </section>
                
                <!-- Recent Trip History -->
                <section class="bg-white p-6 rounded-lg shadow-md">
                    <h2 class="text-xl font-semibold mb-4">Recent Trip History</h2>
                    <?php if (isset($driver_data['history']) && $driver_data['history']->num_rows > 0): ?>
                    <div class="space-y-3">
                        <?php 
                        $driver_data['history']->data_seek(0);
                        while ($history_trip = $driver_data['history']->fetch_assoc()): 
                            $trip_type = $history_trip['is_delivery_route'] ? 'Delivery' : ($history_trip['arrival_city'] ? 'Transport' : 'Unknown');
                            $bg_class = $history_trip['is_delivery_route'] ? 'bg-green-100' : 'bg-blue-100';
                            $text_class = $history_trip['is_delivery_route'] ? 'text-green-800' : 'text-blue-800';
                        ?>
                        <div class="border-l-4 <?= $bg_class ?> pl-3 py-2">
                            <div class="flex justify-between items-center">
                                <div>
                                    <p class="font-medium">Trip #<?= htmlspecialchars($history_trip['trip_id']) ?></p>
                                    <p class="text-sm text-gray-600">
                                        <?= htmlspecialchars($history_trip['departure_city']) ?> 
                                        <?php if ($history_trip['arrival_city']): ?>
                                            → <?= htmlspecialchars($history_trip['arrival_city']) ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <span class="<?= $bg_class ?> <?= $text_class ?> px-2 py-1 rounded-full text-xs">
                                        <?= $trip_type ?>
                                    </span>
                                    <p class="text-xs text-gray-500 mt-1"><?= date('M d, Y', strtotime($history_trip['depart_time'])) ?></p>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <?php else: ?>
                    <p class="text-gray-500">No trip history available.</p>
                    <?php endif; ?>
                </section>
            </div>
            
            <?php else: ?>
            <!-- No Active Trips Display -->
            <div class="bg-white p-6 rounded-lg shadow-md mb-6">
                <div class="text-center py-8">
                    <i class="fas fa-truck text-gray-300 text-6xl mb-4"></i>
                    <h2 class="text-2xl font-semibold mb-2">No Active Routes</h2>
                    <p class="text-gray-600 mb-6">You don't have any active trips assigned at the moment.</p>
                    
                    <?php if (isset($driver_data['performance'])): ?>
                    <div class="max-w-md mx-auto bg-gray-100 p-4 rounded-lg">
                        <h3 class="font-medium mb-2">Your Performance Summary</h3>
                        <div class="grid grid-cols-3 gap-4 text-center">
                            <div>
                                <p class="text-2xl font-bold text-blue-600"><?= $driver_data['performance']['completed_trips'] ?></p>
                                <p class="text-sm text-gray-600">Trips Completed</p>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-green-600"><?= $driver_data['performance']['packages_delivered'] ?></p>
                                <p class="text-sm text-gray-600">Packages Delivered</p>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-purple-600"><?= $driver_data['performance']['days_worked'] ?></p>
                                <p class="text-sm text-gray-600">Days Worked</p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mt-6">
                        <a href="trips/manage_trips.php" class="accent-btn text-white px-4 py-2 rounded mx-2">
                            Manage Trips
                        </a>
                        <a href="#" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded mx-2" onClick="window.location.reload();">
                            Refresh Dashboard
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
            
            <?php if ($employee['role'] == 'Manager'): ?>
            <!-- Manager Dashboard -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <!-- KPI Cards -->
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white p-6 rounded-lg shadow-md">
                    <h3 class="text-lg font-semibold mb-2">Total Packages</h3>
                    <?php
                    $total_packages = 0;
                    if ($manager_data['package_status']->num_rows > 0) {
                        $manager_data['package_status']->data_seek(0);
                        while ($status = $manager_data['package_status']->fetch_assoc()) {
                            $total_packages += $status['count'];
                        }
                    }
                    ?>
                    <p class="text-3xl font-bold"><?= $total_packages ?></p>
                    <p class="text-sm mt-2">Currently in your facility</p>
                </div>
                
                <div class="bg-gradient-to-r from-green-500 to-green-600 text-white p-6 rounded-lg shadow-md">
                    <h3 class="text-lg font-semibold mb-2">Revenue (7 days)</h3>
                    <p class="text-3xl font-bold">$<?= number_format((float) $manager_data['weekly_revenue_total'], 2) ?></p>
                    <p class="text-sm mt-2">From payments at your facility in the last 7 days</p>
                </div>
                
                <div class="bg-gradient-to-r from-purple-500 to-purple-600 text-white p-6 rounded-lg shadow-md">
                    <h3 class="text-lg font-semibold mb-2">Active Support Tickets</h3>
                    <p class="text-3xl font-bold"><?= (int) $manager_data['active_support_ticket_count'] ?></p>
                    <p class="text-sm mt-2">Unresolved issues for your facility</p>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <!-- Package Status Distribution -->
                <section class="bg-white p-6 rounded-lg shadow-md">
                    <h2 class="text-xl font-semibold mb-4">Package Status Distribution</h2>
                    <div class="flex flex-wrap gap-2">
                        <?php if ($manager_data['package_status']->num_rows > 0): 
                            $manager_data['package_status']->data_seek(0);
                            $colors = [
                                'In Transit' => 'bg-blue-100 text-blue-800',
                                'Pending' => 'bg-yellow-100 text-yellow-800',
                                'Delivered' => 'bg-green-100 text-green-800',
                                'Processing' => 'bg-indigo-100 text-indigo-800',
                                'Out for Delivery' => 'bg-orange-100 text-orange-800',
                                'Arrived at Facility' => 'bg-purple-100 text-purple-800'
                            ];
                            while ($status = $manager_data['package_status']->fetch_assoc()): 
                                $color_class = isset($colors[$status['status']]) ? $colors[$status['status']] : 'bg-gray-100 text-gray-800';
                        ?>
                        <div class="flex-1 min-w-[150px] bg-gray-50 p-4 rounded-md text-center">
                            <p class="text-2xl font-bold"><?= htmlspecialchars($status['count']) ?></p>
                            <div class="<?= $color_class ?> px-3 py-1 rounded-full text-xs inline-block mt-2">
                                <?= htmlspecialchars($status['status']) ?>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-gray-500">No package data available</p>
                    <?php endif; ?>
                    </div>
                </section>
                
                <!-- Daily Package Volume -->
                <section class="bg-white p-6 rounded-lg shadow-md">
                    <h2 class="text-xl font-semibold mb-4">Daily Package Volume (Last 7 Days)</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="p-2">Date</th>
                                    <th class="p-2">Packages</th>
                                    <th class="p-2">Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($manager_data['daily_volume']->num_rows > 0):
                                    $manager_data['daily_volume']->data_seek(0);
                                    while ($day = $manager_data['daily_volume']->fetch_assoc()): ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="p-2"><?= date('M d, Y', strtotime($day['date'])) ?></td>
                                    <td class="p-2"><?= htmlspecialchars($day['package_count']) ?></td>
                                    <td class="p-2">$<?= number_format($day['daily_revenue'], 2) ?></td>
                                </tr>
                                <?php endwhile; ?>
                                <?php else: ?>
                                <tr>
                                    <td colspan="3" class="p-2 text-center text-gray-500">No data available</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <!-- Top Selling Items -->
                <section class="bg-white p-6 rounded-lg shadow-md">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-semibold">Top Selling Items (30 Days)</h2>
                        <a href="shop/shop_dashboard.php" class="text-[#004B87] hover:text-[#DA291C] text-sm">
                            Shop Overview →
                        </a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="p-2 text-left">Item</th>
                                    <th class="p-2 text-left">Quantity Sold</th>
                                    <th class="p-2 text-left">Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($manager_data['top_items']->num_rows > 0):
                                    $manager_data['top_items']->data_seek(0);
                                    while ($item = $manager_data['top_items']->fetch_assoc()): ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="p-2"><?= htmlspecialchars($item['name']) ?></td>
                                    <td class="p-2"><?= htmlspecialchars($item['total_sold']) ?></td>
                                    <td class="p-2">$<?= number_format($item['total_revenue'], 2) ?></td>
                                </tr>
                                <?php endwhile; ?>
                                <?php else: ?>
                                <tr>
                                    <td colspan="3" class="p-2 text-center text-gray-500">No sales data available</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Past Notification Alerts -->
                <section id="recent-alerts" class="bg-white p-6 rounded-lg shadow-md flex flex-col scroll-mt-24">
                    <div class="flex flex-wrap justify-between items-center gap-2 mb-4">
                        <h2 class="text-xl font-semibold">Recent Alerts</h2>
                        <?php
                        $manager_data['notifications']->data_seek(0);
                        $unread_count = 0;
                        while ($n = $manager_data['notifications']->fetch_assoc()) {
                            if (!$n['is_read']) $unread_count++;
                        }
                        $manager_data['notifications']->data_seek(0);
                        ?>
                        <div class="flex items-center gap-2 flex-wrap">
                            <?php if ($unread_count > 0): ?>
                            <span class="bg-red-100 text-red-700 text-xs font-semibold px-2.5 py-1 rounded-full">
                                <?= $unread_count ?> unread
                            </span>
                            <form method="post" class="inline">
                                <input type="hidden" name="mark_all_notifications_read" value="1">
                                <button type="submit" class="text-xs font-semibold text-[#004B87] hover:text-[#DA291C] border border-[#004B87] hover:border-[#DA291C] px-2.5 py-1 rounded-lg transition">
                                    Mark all read
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="space-y-2 overflow-y-auto flex-1" style="max-height:260px;">
                        <?php if ($manager_data['notifications']->num_rows > 0):
                            while ($notif = $manager_data['notifications']->fetch_assoc()):
                                $is_low_stock = $notif['type'] === 'Low Stock';
                                $icon      = $is_low_stock ? 'fa-box-open' : 'fa-truck';
                                $icon_col  = $is_low_stock ? '#DA291C' : '#004B87';
                                $bg        = $notif['is_read'] ? 'bg-gray-50' : 'bg-blue-50';
                                $dot       = $notif['is_read'] ? '' : '<span class="w-2 h-2 rounded-full bg-[#004B87] flex-shrink-0 mt-1" title="Unread"></span>';
                        ?>
                        <div class="flex items-start gap-2 p-3 rounded-lg <?= $bg ?> border border-gray-100 group">
                            <i class="fas <?= $icon ?> mt-0.5 flex-shrink-0" style="color:<?= $icon_col ?>;font-size:0.85rem;"></i>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-gray-700 leading-snug"><?= htmlspecialchars($notif['message']) ?></p>
                                <p class="text-xs text-gray-400 mt-0.5"><?= date('M d, Y g:i a', strtotime($notif['created_at'])) ?></p>
                            </div>
                            <?= $dot ?>
                            <?php if (empty($notif['is_read'])): ?>
                            <form method="post" class="flex-shrink-0 self-center">
                                <input type="hidden" name="mark_notification_read" value="1">
                                <input type="hidden" name="notification_id" value="<?= (int) $notif['notification_id'] ?>">
                                <button type="submit" class="text-xs font-semibold text-[#004B87] hover:text-white hover:bg-[#004B87] border border-[#004B87] px-2 py-1 rounded-md transition" title="Mark as read">
                                    <i class="fas fa-check mr-0.5"></i> Read
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                        <?php endwhile; ?>
                        <?php else: ?>
                        <div class="flex flex-col items-center justify-center h-32 text-gray-400">
                            <i class="fas fa-bell-slash text-2xl mb-2"></i>
                            <p class="text-sm">No alerts yet</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
            
            <div class="grid grid-cols-1 gap-6 mb-6">
                <!-- Employee Performance -->
                <section class="bg-white p-6 rounded-lg shadow-md">
                    <h2 class="text-xl font-semibold mb-4">Employee Performance</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="p-2 text-left">Employee</th>
                                    <th class="p-2 text-left">Role</th>
                                    <th class="p-2 text-left">Trips Completed</th>
                                    <th class="p-2 text-left">Packages Handled</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($manager_data['employee_performance']->num_rows > 0):
                                    $manager_data['employee_performance']->data_seek(0);
                                    while ($emp = $manager_data['employee_performance']->fetch_assoc()): ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="p-2"><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></td>
                                    <td class="p-2"><?= htmlspecialchars($emp['role']) ?></td>
                                    <td class="p-2"><?= htmlspecialchars($emp['trips_completed']) ?></td>
                                    <td class="p-2"><?= htmlspecialchars($emp['packages_handled']) ?></td>
                                </tr>
                                <?php endwhile; ?>
                                <?php else: ?>
                                <tr>
                                    <td colspan="4" class="p-2 text-center text-gray-500">No employee performance data available</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
                
                <!-- Recent Trips -->
                <section class="bg-white p-6 rounded-lg shadow-md">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-semibold">Recent Trips</h2>
                        <a href="trips/manage_trips.php" class="text-blue-600 hover:text-blue-800 text-sm">
                            Manage All Trips →
                        </a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="p-2 text-left">Trip ID</th>
                                    <th class="p-2 text-left">Type</th>
                                    <th class="p-2 text-left">Route</th>
                                    <th class="p-2 text-left">Employee</th>
                                    <th class="p-2 text-left">Packages</th>
                                    <th class="p-2 text-left">Departure</th>
                                    <th class="p-2 text-left">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($manager_data['trips']->num_rows > 0):
                                    $manager_data['trips']->data_seek(0);
                                    while ($trip = $manager_data['trips']->fetch_assoc()): 
                                        $is_completed = !empty($trip['arrival_time']);
                                        $status_class = $is_completed ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800';
                                    ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="p-2">#<?= htmlspecialchars($trip['trip_id']) ?></td>
                                    <td class="p-2">
                                        <span class="<?= $trip['trip_type'] == 'Air' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800' ?> px-2 py-1 rounded-full text-xs">
                                            <?= htmlspecialchars($trip['trip_type']) ?>
                                        </span>
                                    </td>
                                    <td class="p-2"><?= htmlspecialchars($trip['departure_city']) ?> → <?= htmlspecialchars($trip['arrival_city'] ?? '') ?></td>
                                    <td class="p-2"><?= htmlspecialchars($trip['first_name'] . ' ' . $trip['last_name']) ?></td>
                                    <td class="p-2"><?= htmlspecialchars($trip['package_count']) ?></td>
                                    <td class="p-2"><?= date('M d, H:i', strtotime($trip['depart_time'])) ?></td>
                                    <td class="p-2">
                                        <span class="<?= $status_class ?> px-2 py-1 rounded-full text-xs">
                                            <?= $is_completed ? 'Completed' : 'In Progress' ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                                <?php else: ?>
                                <tr>
                                    <td colspan="7" class="p-2 text-center text-gray-500">No trip data available</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
                
                <!-- Support Tickets -->
                <section class="bg-white p-6 rounded-lg shadow-md">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-semibold">Active Support Tickets</h2>
                        <a href="facility_tickets.php" class="text-blue-600 hover:text-blue-800 text-sm">
                            View All Tickets for Facility →
                        </a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="p-2 text-left">Ticket ID</th>
                                    <th class="p-2 text-left">Issue Type</th>
                                    <th class="p-2 text-left">Package</th>
                                    <th class="p-2 text-left">Status</th>
                                    <th class="p-2 text-left">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($manager_data['support_tickets']->num_rows > 0):
                                    $manager_data['support_tickets']->data_seek(0);
                                    while ($ticket = $manager_data['support_tickets']->fetch_assoc()): 
                                        $status_class = '';
                                        switch($ticket['status']) {
                                            case 'Open': $status_class = 'bg-green-100 text-green-800'; break;
                                            case 'In Progress': $status_class = 'bg-blue-100 text-blue-800'; break;
                                            default: $status_class = 'bg-gray-100 text-gray-800';
                                        }
                                    ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="p-2">#<?= htmlspecialchars($ticket['ticket_id']) ?></td>
                                    <td class="p-2"><?= htmlspecialchars($ticket['issue_type']) ?></td>
                                    <td class="p-2"><?= htmlspecialchars((string) ($ticket['tracking_number'] ?? '—')) ?></td>
                                    <td class="p-2">
                                        <span class="<?= $status_class ?> px-2 py-1 rounded-full text-xs">
                                            <?= htmlspecialchars($ticket['status']) ?>
                                        </span>
                                    </td>
                                    <td class="p-2">
                                        <a href="support/view_ticket.php?id=<?= htmlspecialchars($ticket['ticket_id']) ?>" 
                                           class="text-blue-600 hover:text-blue-800">View</a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                                <?php else: ?>
                                <tr>
                                    <td colspan="5" class="p-2 text-center text-gray-500">No active support tickets</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
            <?php endif; ?>

            <?php if ($employee['role'] == 'Clerk'): ?>
            <!-- Clerk Dashboard -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <!-- Recent Activity -->
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white p-6 rounded-lg shadow-md">
                    <h3 class="text-lg font-semibold mb-2">Today's Activity</h3>
                    <?php
                    // Get count of packages processed by this clerk today
                    $today = date('Y-m-d');
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Tracking_History 
                                         WHERE employee_id = ? AND DATE(timestamp) = ?");
                    $stmt->bind_param("is", $user_id, $today);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $today_count = $result->fetch_assoc()['count'] ?? 0;
                    ?>
                    <p class="text-3xl font-bold"><?= number_format($today_count) ?></p>
                    <p class="text-sm mt-2">Packages Processed Today</p>
                </div>
                
                <!-- Shop Sales -->
                <div class="bg-gradient-to-r from-green-500 to-green-600 text-white p-6 rounded-lg shadow-md">
                    <h3 class="text-lg font-semibold mb-2">Shop Sales</h3>
                    <?php
                    // Get count of sales made today
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Shop_Sale ss
                                         JOIN Shop s ON ss.shop_id = s.shop_id
                                         WHERE s.facility_id = ? AND DATE(ss.sale_date) = ?");
                    $stmt->bind_param("is", $facility_id, $today);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $sales_count = $result->fetch_assoc()['count'] ?? 0;
                    ?>
                    <p class="text-3xl font-bold"><?= number_format($sales_count) ?></p>
                    <p class="text-sm mt-2">Shop Sales Today</p>
                </div>
                
                <!-- Daily Stats -->
                <div class="bg-gradient-to-r from-purple-500 to-purple-600 text-white p-6 rounded-lg shadow-md">
                    <h3 class="text-lg font-semibold mb-2">Facility Stats</h3>
                    <?php
                    // Get count of total packages processed at this facility today
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Tracking_History 
                                         WHERE facility_id = ? AND DATE(timestamp) = ?");
                    $stmt->bind_param("is", $facility_id, $today);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $packages_count = $result->fetch_assoc()['count'] ?? 0;
                    
                    // Get count of shop sales at this facility today
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Shop_Sale ss
                                         JOIN Shop s ON ss.shop_id = s.shop_id
                                         WHERE s.facility_id = ? AND DATE(ss.sale_date) = ?");
                    $stmt->bind_param("is", $facility_id, $today);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $shop_sales_count = $result->fetch_assoc()['count'] ?? 0;
                    
                    // Calculate combined total
                    $facility_count = $packages_count + $shop_sales_count;
                    ?>
                    <p class="text-3xl font-bold"><?= number_format($facility_count) ?></p>
                    <p class="text-sm mt-2">Total Facility Volume Today</p>
                    <p class="text-xs mt-1 text-gray-100">(Packages: <?= $packages_count ?>, Sales: <?= $shop_sales_count ?>)</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 mb-6">
                <!-- Recent Packages Processed -->
                <section class="bg-white p-6 rounded-lg shadow-md">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-semibold">Recently Processed Packages</h2>
                        <a href="package/search.php" class="text-blue-600 hover:text-blue-800 text-sm">
                            Search Packages →
                        </a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="p-2 text-left">Tracking #</th>
                                    <th class="p-2 text-left">Status</th>
                                    <th class="p-2 text-left">Action</th>
                                    <th class="p-2 text-left">Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Get recently processed packages by this clerk
                                $stmt = $conn->prepare("SELECT th.*, p.status as package_status 
                                                     FROM Tracking_History th
                                                     JOIN Package p ON th.tracking_number = p.tracking_number
                                                     WHERE th.employee_id = ? AND p.facility_id = ?
                                                     ORDER BY th.timestamp DESC LIMIT 5");
                                $stmt->bind_param("ii", $user_id, $facility_id);
                                $stmt->execute();
                                $recent_packages = $stmt->get_result();
                                
                                if ($recent_packages->num_rows > 0):
                                    while ($package = $recent_packages->fetch_assoc()):
                                        // Determine status class
                                        $status_class = '';
                                        switch($package['package_status']) {
                                            case 'Delivered': $status_class = 'bg-green-100 text-green-800'; break;
                                            case 'In Transit': $status_class = 'bg-blue-100 text-blue-800'; break;
                                            case 'Processing': $status_class = 'bg-yellow-100 text-yellow-800'; break;
                                            case 'Out for Delivery': $status_class = 'bg-purple-100 text-purple-800'; break;
                                            default: $status_class = 'bg-gray-100 text-gray-800';
                                        }
                                ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="p-2"><?= htmlspecialchars($package['tracking_number']) ?></td>
                                    <td class="p-2">
                                        <span class="<?= $status_class ?> px-2 py-1 rounded-full text-xs">
                                            <?= htmlspecialchars($package['package_status']) ?>
                                        </span>
                                    </td>
                                    <td class="p-2"><?= htmlspecialchars($package['action']) ?></td>
                                    <td class="p-2"><?= date('M d, H:i', strtotime($package['timestamp'])) ?></td>
                                </tr>
                                <?php 
                                    endwhile;
                                else:
                                ?>
                                <tr>
                                    <td colspan="4" class="p-2 text-center text-gray-500">No recent package activity</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
                
                <!-- Packages Awaiting Pickup -->
                <section class="bg-white p-6 rounded-lg shadow-md">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-semibold">Packages Awaiting Pickup</h2>
                        <a href="package/awaiting_pickup.php" class="text-blue-600 hover:text-blue-800 text-sm">
                            View All →
                        </a>
                    </div>
                    <div class="overflow-x-auto">
                        <?php
                        // Get count of packages awaiting pickup
                        $package_count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM Package 
                                                        WHERE facility_id = ? AND status = 'Ready for Pickup'");
                        $package_count_stmt->bind_param("i", $facility_id);
                        $package_count_stmt->execute();
                        $package_count = $package_count_stmt->get_result()->fetch_assoc()['count'];
                        
                        // Get count of shop transactions awaiting pickup - MODIFIED to use transaction_status
                        $shop_count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM Shop_Transaction t
                                                    JOIN Shop s ON t.shop_id = s.shop_id
                                                    WHERE s.facility_id = ? AND t.transaction_status = 'Completed'");
                        $shop_count_stmt->bind_param("i", $facility_id);
                        $shop_count_stmt->execute();
                        $shop_count = $shop_count_stmt->get_result()->fetch_assoc()['count'];
                        
                        $total_count = $package_count + $shop_count;
                        ?>
                        
                        <?php if($total_count > 0): ?>
                        
                        <table class="w-full">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="p-2 text-left">Tracking #</th>
                                    <th class="p-2 text-left">Recipient</th>
                                    <th class="p-2 text-left">Ready Since</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Get packages awaiting pickup at this facility
                                $stmt = $conn->prepare("(SELECT p.tracking_number, 
                                                     c.first_name, c.last_name, c.phone,
                                                     (SELECT MAX(timestamp) FROM Tracking_History 
                                                      WHERE tracking_number = p.tracking_number 
                                                      AND action = 'Ready for Pickup') as ready_since,
                                                     'Package' as item_type,
                                                     NULL as transaction_id
                                                     FROM Package p
                                                     JOIN Customer c ON p.receiver_id = c.user_id
                                                     WHERE p.facility_id = ? AND p.status = 'Ready for Pickup'
                                                     
                                                     UNION
                                                     
                                                     SELECT CONCAT('ST', t.transaction_id) as tracking_number, 
                                                            c.first_name, c.last_name, c.phone,
                                                            t.transaction_date as ready_since,
                                                            'Shop' as item_type,
                                                            t.transaction_id
                                                     FROM Shop_Transaction t
                                                     JOIN Customer c ON t.user_id = c.user_id
                                                     JOIN Shop s ON t.shop_id = s.shop_id
                                                     WHERE s.facility_id = ? 
                                                     AND t.transaction_status = 'Completed')
                                                     ORDER BY ready_since ASC
                                                     LIMIT 10");
                                $stmt->bind_param("ii", $facility_id, $facility_id);
                                $stmt->execute();
                                $pickup_packages = $stmt->get_result();
                                
                                if ($pickup_packages->num_rows > 0):
                                    while ($package = $pickup_packages->fetch_assoc()):
                                        // Calculate how long the package has been waiting
                                        $ready_time = strtotime($package['ready_since']);
                                        $ready_days = floor((time() - $ready_time) / 86400);
                                        $waiting_class = $ready_days > 7 ? 'text-red-600 font-bold' : ($ready_days > 3 ? 'text-orange-600' : 'text-gray-600');
                                        
                                        // Different styling for different item types
                                        $type_class = $package['item_type'] === 'Package' ? 
                                            'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800';
                                ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="p-2">
                                        <div class="flex items-center">
                                            <span class="<?= $type_class ?> text-xs px-2 py-0.5 rounded-full mr-2">
                                                <?= $package['item_type'] ?>
                                            </span>
                                            <?= htmlspecialchars($package['tracking_number']) ?>
                                        </div>
                                    </td>
                                    <td class="p-2">
                                        <div><?= htmlspecialchars($package['first_name'] . ' ' . $package['last_name']) ?></div>
                                        <div class="text-xs text-gray-500">
                                            <?= htmlspecialchars($package['phone']) ?>
                                        </div>
                                    </td>
                                    <td class="p-2">
                                        <div><?= date('M d, Y', $ready_time) ?></div>
                                        <div class="<?= $waiting_class ?> text-xs">
                                            <?= $ready_days == 0 ? 'Today' : ($ready_days == 1 ? 'Yesterday' : $ready_days . ' days ago') ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php 
                                    endwhile;
                                else:
                                ?>
                                <tr>
                                    <td colspan="3" class="p-2 text-center text-gray-500">No packages awaiting pickup</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                            <div class="text-center py-8">
                                <i class="fas fa-box-open text-gray-300 text-6xl mb-4"></i>
                                <p class="text-gray-600">No packages or shop orders are awaiting pickup at this time.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>

            
            <?php endif; ?>

            <?php if (in_array($_SESSION['role'], ['Clerk', 'Driver', 'Manager'])): ?>
                <div class="mb-4">
                    <a href="./employee/contact_admin.php" class="block p-4 bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow">
                        <div class="flex items-center text-blue-600">
                            <i class="fas fa-envelope text-xl mr-3"></i>
                            <span class="text-lg font-semibold">Contact Admin</span>
                            <?php if ($unread_admin_messages > 0): ?>
                                <span class="ml-2 bg-red-600 text-white text-xs px-2 py-1 rounded-full"><?= $unread_admin_messages ?></span>
                            <?php endif; ?>
                        </div>
                        <p class="text-gray-600 mt-2">Send messages or requests to the system administrator</p>
                    </a>
                </div>
            <?php endif; ?>
        </main>
        
        <footer class="text-center mt-10 py-6 bg-[#004B87] text-white rounded-lg">
            <p>&copy; <?= date('Y') ?> Postal Service Management System</p>
        </footer>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleButton = document.getElementById('toggleNotifications');
            const notificationsContainer = document.getElementById('notificationsContainer');
            const chevronIcon = toggleButton?.querySelector('i');
            
            if (toggleButton && notificationsContainer) {
                toggleButton.addEventListener('click', function() {
                    notificationsContainer.classList.toggle('hidden');
                    if (chevronIcon) {
                        if (notificationsContainer.classList.contains('hidden')) {
                            chevronIcon.classList.remove('fa-chevron-up');
                            chevronIcon.classList.add('fa-chevron-down');
                        } else {
                            chevronIcon.classList.remove('fa-chevron-down');
                            chevronIcon.classList.add('fa-chevron-up');
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>
