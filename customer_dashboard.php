<?php
session_start();
require_once 'db_connect.php';
require_once 'functions.php';
// Enable error reporting for debugging
// Check for 'order' parameter in URL
$order_message = "";
if (isset($_GET['order']) && $_GET['order'] === 'received') {
    $order_type = $_GET['type'] ?? 'package';
    
    if ($order_type === 'package') {
        $tracking_number = $_GET['tracking'] ?? '';
        $order_message = "Your package has been created successfully! Your tracking number is <strong>$tracking_number</strong>. Please keep this number and use it when dropping off your package at any facility.";
    } elseif ($order_type === 'shop') {
        $order_message = "Your shop purchase has been completed successfully!";
    }
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] != 'Customer') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$name = $_SESSION['name'];

// Get customer information
$stmt = $conn->prepare("SELECT * FROM Customer WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();

// Get active packages (as sender)
$stmt = $conn->prepare("SELECT p.*, f.city as current_location 
                      FROM Package p
                      LEFT JOIN Facility f ON p.facility_id = f.facility_id
                      WHERE p.sender_id = ? AND p.status != 'Delivered'
                      ORDER BY p.timestamp_created DESC
                      LIMIT 5");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$sent_packages = $stmt->get_result();

// Get active packages (as receiver)
$stmt = $conn->prepare("SELECT p.*, f.city as current_location, c.first_name as sender_first_name, c.last_name as sender_last_name 
                      FROM Package p
                      LEFT JOIN Facility f ON p.facility_id = f.facility_id
                      LEFT JOIN Customer c ON p.sender_id = c.user_id
                      WHERE p.receiver_id = ? AND p.status != 'Delivered'
                      ORDER BY p.timestamp_created DESC
                      LIMIT 5");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$received_packages = $stmt->get_result();

// Get active support tickets
$stmt = $conn->prepare("SELECT * FROM Support_Ticket 
                      WHERE user_id = ? AND status != 'Resolved'
                      ORDER BY ticket_id DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$tickets = $stmt->get_result();

// Get recent payments
$stmt = $conn->prepare("SELECT * FROM Package_Payment 
                      WHERE user_id = ?
                      ORDER BY payment_id DESC
                      LIMIT 5");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$payments = $stmt->get_result();

// Get recent shop transactions (shop purchases)
$stmt = $conn->prepare("SELECT t.*, s.shop_name
                       FROM Shop_Transaction t
                       JOIN Shop s ON t.shop_id = s.shop_id 
                       WHERE t.user_id = ? 
                       ORDER BY t.transaction_date DESC 
                       LIMIT 5");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$shop_purchases = $stmt->get_result();

// Get notifications for this user
$stmt = $conn->prepare("SELECT * FROM Notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 10");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifications = $stmt->get_result();
$notification_count = $notifications->num_rows;

// Mark notification as read if requested
if (isset($_GET['read_notification']) && is_numeric($_GET['read_notification'])) {
    $notification_id = intval($_GET['read_notification']);
    $stmt = $conn->prepare("UPDATE Notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notification_id, $user_id);
    $stmt->execute();
    header("Location: customer_dashboard.php");
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard</title>
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
        }
        
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
        .order-success-banner {
            position: fixed;
            top: 5rem;
            right: 1rem;
            z-index: 40;
            max-width: 24rem;
            transition: opacity 0.4s ease, transform 0.4s ease;
        }
        .order-success-banner.order-success-banner--hidden {
            opacity: 0;
            pointer-events: none;
            transform: translateX(100%);
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include '_public_nav.php'; ?>

    <div class="container mx-auto px-4 py-8 max-w-6xl mt-[60px]">
        <header class="mb-6">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center">
                <h1 class="text-3xl font-bold section-title">Customer Dashboard</h1>
                <div class="mt-4 md:mt-0 bg-white shadow-sm p-4 rounded-lg">
                    <p class="mb-1"><span class="font-semibold">Welcome,</span> <?= htmlspecialchars($name) ?></p>
                    <p><span class="font-semibold">Account Status:</span> 
                        <span class="<?= ($_SESSION['account_status'] ?? 'Active') === 'Active' ? 'text-green-600' : 'text-red-600' ?>">
                            <?= htmlspecialchars($_SESSION['account_status'] ?? 'Active') ?>
                        </span>
                    </p>
                </div>
            </div>
        </header>
        
        <nav class="bg-white shadow-sm p-4 rounded-lg mb-6">
            <ul class="flex flex-wrap space-x-2 md:space-x-6">
                <li><a href="edit_profile.php" class="text-gray-700 hover:text-[#DA291C]">Edit Profile</a></li>
                <li><a href="sendpackage.php" class="text-gray-700 hover:text-[#DA291C]">Send Package</a></li>
                <li><a href="./package/track.php" class="text-gray-700 hover:text-[#DA291C]">Track Package</a></li>
                <li><a href="support.php" class="text-gray-700 hover:text-[#DA291C]">Support</a></li>
                <li><a href="shop.php" class="text-gray-700 hover:text-[#DA291C]">Shop</a></li>
            </ul>
        </nav>
        
        <?php if ($notification_count > 0): ?>
        <div id="notificationBanner" class="bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-4 rounded mb-6 shadow-md">
            <div class="flex justify-between items-center">
                <div class="flex items-center">
                    <i class="fas fa-bell text-blue-500 mr-3 text-xl"></i>
                    <span class="font-semibold">You have <?= $notification_count ?> new notification<?= $notification_count > 1 ? 's' : '' ?></span>
                </div>
                <button id="toggleNotifications" class="text-blue-500 hover:text-blue-700">
                    <i class="fas fa-chevron-up"></i>
                </button>
            </div>
            <div id="notificationsContainer" class="mt-4">
                <ul class="divide-y divide-blue-200">
                    <?php 
                    $notifications->data_seek(0);
                    while ($notification = $notifications->fetch_assoc()): 
                        $bg_class = 'bg-blue-50';
                        $icon_class = 'fa-box text-blue-500';
                        if ($notification['type'] == 'Delivery') {
                            $icon_class = 'fa-truck text-green-500';
                            $bg_class = 'bg-green-50';
                        }
                    ?>
                    <li class="py-3 px-2 <?= $bg_class ?> rounded my-2">
                        <div class="flex">
                            <div class="flex-shrink-0 mr-3">
                                <i class="fas <?= $icon_class ?>"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm"><?= htmlspecialchars($notification['message']) ?></p>
                                <p class="text-xs text-gray-500 mt-1">
                                    <?= date('M d, g:i a', strtotime($notification['created_at'])) ?>
                                </p>
                            </div>
                            <div>
                                <a href="customer_dashboard.php?read_notification=<?= $notification['notification_id'] ?>" 
                                   class="text-sm text-gray-500 hover:text-gray-700">
                                    <i class="fas fa-times"></i>
                                </a>
                            </div>
                        </div>
                    </li>
                    <?php endwhile; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($order_message)): ?>
        <div id="orderSuccessBanner" class="order-success-banner bg-green-100 border-l-4 border-green-500 text-green-700 p-6 rounded shadow-lg" role="alert">
            <div class="flex items-center mb-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span class="text-lg font-bold">Success!</span>
            </div>
            <p class="ml-8"><?= $order_message ?></p>
        </div>
        <?php endif; ?>
        
        <main>
            <section class="bg-white shadow-sm p-6 rounded-lg mb-6">
                <h2 class="text-2xl font-semibold mb-4 section-title">Your Packages</h2>
                
                <div class="mb-6">
                    <h3 class="text-xl font-semibold mb-3 border-l-4 border-[#004B87] pl-3">Packages You've Sent</h3>
                    <?php if ($sent_packages->num_rows > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-gray-100 text-left">
                                    <th class="px-4 py-2">Tracking Number</th>
                                    <th class="px-4 py-2">Status</th>
                                    <th class="px-4 py-2">Current Location</th>
                                    <th class="px-4 py-2">Date Sent</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($package = $sent_packages->fetch_assoc()): ?>
                                <tr class="border-b border-gray-200 hover:bg-gray-50">
                                    <td class="px-4 py-2"><?= htmlspecialchars($package['tracking_number']) ?></td>
                                    <td class="px-4 py-2">
                                        <span class="<?php
                                            switch($package['status']) {
                                                case 'Delivered': echo 'bg-green-100 text-green-800'; break;
                                                case 'In Transit': echo 'bg-blue-100 text-blue-800'; break;
                                                case 'Processing': echo 'bg-yellow-100 text-yellow-800'; break;
                                                case 'Out for Delivery': echo 'bg-purple-100 text-purple-800'; break;
                                                default: echo 'bg-gray-100 text-gray-800';
                                            }
                                        ?> px-2 py-1 rounded-full text-xs">
                                            <?= htmlspecialchars($package['status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-2"><?= htmlspecialchars($package['current_location'] ?? 'In Transit') ?></td>
                                    <td class="px-4 py-2"><?= date('M d, Y', strtotime($package['timestamp_created'])) ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-gray-600">You haven't sent any packages yet.</p>
                    <?php endif; ?>
                </div>
                
                <div class="mb-6">
                    <h3 class="text-xl font-semibold mb-3 border-l-4 border-[#DA291C] pl-3">Packages Coming to You</h3>
                    <?php if ($received_packages->num_rows > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-gray-100 text-left">
                                    <th class="px-4 py-2">Tracking Number</th>
                                    <th class="px-4 py-2">From</th>
                                    <th class="px-4 py-2">Status</th>
                                    <th class="px-4 py-2">Current Location</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($package = $received_packages->fetch_assoc()): ?>
                                <tr class="border-b border-gray-200 hover:bg-gray-50">
                                    <td class="px-4 py-2"><?= htmlspecialchars($package['tracking_number']) ?></td>
                                    <td class="px-4 py-2"><?= htmlspecialchars($package['sender_first_name'] . ' ' . $package['sender_last_name']) ?></td>
                                    <td class="px-4 py-2">
                                        <span class="<?php
                                            switch($package['status']) {
                                                case 'Delivered': echo 'bg-green-100 text-green-800'; break;
                                                case 'In Transit': echo 'bg-blue-100 text-blue-800'; break;
                                                case 'Processing': echo 'bg-yellow-100 text-yellow-800'; break;
                                                case 'Out for Delivery': echo 'bg-purple-100 text-purple-800'; break;
                                                default: echo 'bg-gray-100 text-gray-800';
                                            }
                                        ?> px-2 py-1 rounded-full text-xs">
                                            <?= htmlspecialchars($package['status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-2"><?= htmlspecialchars($package['current_location'] ?? 'In Transit') ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-gray-600">You don't have any incoming packages at the moment.</p>
                    <?php endif; ?>
                </div>
                
                <p class="text-center mt-4">
                    <a href="package_history.php" class="action-btn text-white px-4 py-2 rounded">
                        View all packages
                    </a>
                </p>
            </section>
            
            <section class="bg-white shadow-sm p-6 rounded-lg mb-6">
                <h2 class="text-2xl font-semibold mb-4 section-title">Support Tickets</h2>
                <?php if ($tickets->num_rows > 0): ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-100 text-left">
                                <th class="px-4 py-2">Ticket ID</th>
                                <th class="px-4 py-2">Issue Type</th>
                                <th class="px-4 py-2">Status</th>
                                <th class="px-4 py-2">Package ID</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($ticket = $tickets->fetch_assoc()): ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-50">
                                <td class="px-4 py-2">#<?= htmlspecialchars($ticket['ticket_id']) ?></td>
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
                                <td class="px-4 py-2"><?= $ticket['package_id'] ? htmlspecialchars($ticket['package_id']) : 'N/A' ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-gray-600">You don't have any active support tickets.</p>
                <div class="text-center mt-4">
                    <a href="support.php?action=new" class="accent-btn text-white px-4 py-2 rounded">
                        Create Support Ticket
                    </a>
                </div>
                <?php endif; ?>
                
                <?php if ($tickets->num_rows > 0): ?>
                <div class="text-center mt-4">
                    <a href="support.php?action=view_all" class="action-btn text-white px-4 py-2 rounded">
                        View All Tickets
                    </a>
                </div>
                <?php endif; ?>
            </section>
            
            <section class="bg-white shadow-sm p-6 rounded-lg mb-6">
    <h2 class="text-2xl font-semibold mb-4 section-title">Recent Payments</h2>

    <?php if ($payments->num_rows > 0): ?>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="bg-gray-100 text-left">
                    <th class="px-4 py-2">Invoice #</th>
                    <th class="px-4 py-2">Date</th>
                    <th class="px-4 py-2">Amount</th>
                    <th class="px-4 py-2">Method</th>
                    <th class="px-4 py-2">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($payment = $payments->fetch_assoc()): ?>
                <tr class="border-b border-gray-200 hover:bg-gray-50">
                    <td class="px-4 py-2"><?= htmlspecialchars($payment['invoice_number']) ?></td>
                    <td class="px-4 py-2"><?= date('M d, Y', strtotime($payment['payment_date'])) ?></td>
                    <td class="px-4 py-2 font-medium">$<?= number_format($payment['amount'], 2) ?></td>
                    <td class="px-4 py-2"><?= htmlspecialchars($payment['payment_method']) ?></td>
                    <td class="px-4 py-2">
                        <span class="<?php
                            switch($payment['transaction_status']) {
                                case 'Completed': echo 'bg-green-100 text-green-800'; break;
                                case 'Pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                case 'Failed': echo 'bg-red-100 text-red-800'; break;
                                default: echo 'bg-gray-100 text-gray-800';
                            }
                        ?> px-2 py-1 rounded-full text-xs">
                            <?= htmlspecialchars($payment['transaction_status']) ?>
                        </span>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <div class="text-center mt-4">
        <a href="payment_history.php" class="action-btn text-white px-4 py-2 rounded">
            View Full Payment History
        </a>
    </div>

    <?php else: ?>
    <p class="text-gray-600">You don't have any recent payment records.</p>
    <?php endif; ?>
</section>


<section class="bg-white shadow-sm p-6 rounded-lg mb-6">
    <h2 class="text-2xl font-semibold mb-4 section-title">Recent Shop Purchases</h2>
    
    <?php if ($shop_purchases && $shop_purchases->num_rows > 0): ?>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-100 text-left">
                        <th class="px-4 py-2">Transaction ID</th>
                        <th class="px-4 py-2">Date</th>
                        <th class="px-4 py-2">Shop</th>
                        <th class="px-4 py-2">Total</th>
                        <th class="px-4 py-2">Method</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($tx = $shop_purchases->fetch_assoc()): ?>
                        <tr class="border-b border-gray-200 hover:bg-gray-50">
                            <td class="px-4 py-2">#<?= htmlspecialchars($tx['transaction_id']) ?></td>
                            <td class="px-4 py-2"><?= date('M d, Y', strtotime($tx['transaction_date'])) ?></td>
                            <td class="px-4 py-2"><?= htmlspecialchars($tx['shop_name']) ?></td>
                            <td class="px-4 py-2">$<?= number_format($tx['total_amount'], 2) ?></td>
                            <td class="px-4 py-2"><?= htmlspecialchars($tx['payment_method']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <div class="text-center mt-4">
            <a href="shop_purchase_history.php" class="action-btn text-white px-4 py-2 rounded">
                View All Shop Purchases
            </a>
        </div>
    <?php else: ?>
        <p class="text-gray-600">You have no recent shop purchases.</p>
        <div class="text-center mt-4">
            <a href="shop.php" class="accent-btn text-white px-4 py-2 rounded">
                Visit Shop
            </a>
        </div>
    <?php endif; ?>
</section>


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
        // Hide success banner(s) smoothly after 3 seconds
        (function() {
            var banners = document.querySelectorAll('.order-success-banner');
            if (banners.length) {
                setTimeout(function() {
                    banners.forEach(function(el) {
                        el.classList.add('order-success-banner--hidden');
                        el.addEventListener('transitionend', function onEnd() {
                            el.removeEventListener('transitionend', onEnd);
                            el.style.display = 'none';
                        });
                    });
                }, 3000);
            }
        })();
    </script>
</body>
</html>
