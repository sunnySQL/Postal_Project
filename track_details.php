<?php
session_start();
require_once 'db_connect.php';
require_once 'functions.php';

// Enable error reporting for debugging
// Get tracking number from URL parameter
$tracking_number = $_GET['tracking'] ?? '';
$error_message = '';
$package_info = null;
$tracking_history = null;
$customer_info = null;
$is_owner = false;

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php?redirect=track_details.php?tracking=" . urlencode($tracking_number));
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Check if tracking number exists
if (!empty($tracking_number)) {
    // Get package information
    $package_query = "SELECT p.*, f.city as facility_city, f.state as facility_state, f.type as facility_type
                     FROM Package p
                     LEFT JOIN Facility f ON p.facility_id = f.facility_id
                     WHERE p.tracking_number = ?";
    $stmt = $conn->prepare($package_query);
    $stmt->bind_param("s", $tracking_number);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $package_info = $result->fetch_assoc();
        
        // Check if the logged-in user has permission to view this package
        if ($user_role === 'Customer' && $user_id != $package_info['sender_id'] && $user_id != $package_info['receiver_id']) {
            $error_message = "You don't have permission to view this package.";
            $package_info = null;
        } else {
            // Get customer information
            $customer_query = "SELECT 
                        s.user_id as sender_id, s.first_name as sender_first, s.last_name as sender_last, 
                        s.street_address as sender_address, s.city as sender_city, s.state as sender_state, s.postal_code as sender_postal,
                        r.user_id as receiver_id, r.first_name as receiver_first, r.last_name as receiver_last,
                        r.street_address as receiver_address, r.city as receiver_city, r.state as receiver_state, 
                        r.postal_code as receiver_postal
                        FROM Package p
                        LEFT JOIN Customer s ON p.sender_id = s.user_id
                        LEFT JOIN Customer r ON p.receiver_id = r.user_id
                        WHERE p.tracking_number = ?";
            $stmt = $conn->prepare($customer_query);
            $stmt->bind_param("s", $tracking_number);
            $stmt->execute();
            $customer_info = $stmt->get_result()->fetch_assoc();
            
            // Get tracking history
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
            
            // Set owner flag
            if ($user_role === 'Customer' && ($user_id == $package_info['sender_id'] || $user_id == $package_info['receiver_id'])) {
                $is_owner = true;
            }
        }
    } else {
        $error_message = "Package with tracking number $tracking_number not found.";
    }
} else {
    $error_message = "Please provide a tracking number.";
}

// Calculate estimated delivery date
$estimated_delivery = '';
if ($package_info) {
    // Calculate based on shipping speed and creation date
    $creation_date = new DateTime($package_info['timestamp_created']);
    switch ($package_info['shipping_speed']) {
        case 'Economy':
            $delivery_days = 7;
            break;
        case 'Express':
            $delivery_days = 2;
            break;
        default: // Standard
            $delivery_days = 4;
            break;
    }
    
    if ($package_info['status'] != 'Delivered') {
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
    <title>Package Details - <?= htmlspecialchars($tracking_number) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .status-step {
            position: relative;
        }
        .status-step::before {
            content: '';
            position: absolute;
            left: 16px;
            top: 32px;
            height: calc(100% - 32px);
            width: 2px;
            background-color: #e5e7eb;
            z-index: 0;
        }
        .status-step:last-child::before {
            display: none;
        }
        .status-bullet {
            z-index: 1;
            position: relative;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8 max-w-6xl">
        <header class="mb-6">
            <div class="flex justify-between items-center">
                <h1 class="text-3xl font-bold">Package Details</h1>
                <?php if ($user_role === 'Customer'): ?>
                    <a href="customer_dashboard.php" class="text-blue-600 hover:text-blue-800">
                        <i class="fas fa-arrow-left mr-1"></i> Back to Dashboard
                    </a>
                <?php else: ?>
                    <a href="employee_dashboard.php" class="text-blue-600 hover:text-blue-800">
                        <i class="fas fa-arrow-left mr-1"></i> Back to Dashboard
                    </a>
                <?php endif; ?>
            </div>
        </header>
        
        <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                <p><?= htmlspecialchars($error_message) ?></p>
            </div>
        <?php elseif ($package_info): ?>
            <!-- Package Summary -->
            <div class="bg-white shadow rounded-lg p-6 mb-6">
                <div class="flex flex-col md:flex-row justify-between gap-4">
                    <div>
                        <h2 class="text-2xl font-semibold mb-3">Package #<?= htmlspecialchars($tracking_number) ?></h2>
                        <div class="flex items-center mb-2">
                            <span class="font-medium mr-2">Status:</span>
                            <?php 
                            $status_class = '';
                            switch($package_info['status']) {
                                case 'Delivered':
                                    $status_class = 'bg-green-100 text-green-800';
                                    break;
                                case 'In Transit':
                                    $status_class = 'bg-blue-100 text-blue-800';
                                    break;
                                case 'Processing':
                                    $status_class = 'bg-yellow-100 text-yellow-800';
                                    break;
                                case 'Out for Delivery':
                                    $status_class = 'bg-purple-100 text-purple-800';
                                    break;
                                case 'Returned':
                                    $status_class = 'bg-red-100 text-red-800';
                                    break;
                                default:
                                    $status_class = 'bg-gray-100 text-gray-800';
                            }
                            ?>
                            <span class="<?= $status_class ?> px-3 py-1 rounded-full text-sm font-medium">
                                <?= htmlspecialchars($package_info['status']) ?>
                            </span>
                        </div>
                        <p class="text-gray-600 mb-1">
                            <span class="font-medium">Weight:</span> <?= htmlspecialchars($package_info['weight']) ?> lbs
                        </p>
                        <p class="text-gray-600 mb-1">
                            <span class="font-medium">Size:</span> <?= htmlspecialchars($package_info['size']) ?>
                        </p>
                        <p class="text-gray-600 mb-1">
                            <span class="font-medium">Shipping Method:</span> <?= htmlspecialchars($package_info['shipping_speed']) ?>
                        </p>
                        <p class="text-gray-600 mb-1">
                            <span class="font-medium">Signature Required:</span> <?= $package_info['signature_required'] === 'Y' ? 'Yes' : 'No' ?>
                        </p>
                        <p class="text-gray-600 mb-1">
                            <span class="font-medium">Date Sent:</span> <?= date('M d, Y', strtotime($package_info['timestamp_created'])) ?>
                        </p>
                        <?php if (!empty($estimated_delivery) && $package_info['status'] !== 'Delivered'): ?>
                            <p class="text-gray-600 mb-1">
                                <span class="font-medium">Estimated Delivery:</span> <?= $estimated_delivery ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($is_owner || $user_role === 'Employee' || $user_role === 'Admin'): ?>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h3 class="font-semibold mb-2 text-lg">Shipping Details</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <h4 class="font-semibold text-sm text-gray-500 mb-1">From</h4>
                                <p class="font-medium"><?= htmlspecialchars($customer_info['sender_first'] . ' ' . $customer_info['sender_last']) ?></p>
                                <?php if ($is_owner || $user_role === 'Employee' || $user_role === 'Admin'): ?>
                                    <p><?= htmlspecialchars($customer_info['sender_address']) ?></p>
                                    <p><?= htmlspecialchars($customer_info['sender_city'] . ', ' . $customer_info['sender_state'] . ' ' . $customer_info['sender_postal']) ?></p>
                                <?php endif; ?>
                            </div>
                            <div>
                                <h4 class="font-semibold text-sm text-gray-500 mb-1">To</h4>
                                <p class="font-medium"><?= htmlspecialchars($customer_info['receiver_first'] . ' ' . $customer_info['receiver_last']) ?></p>
                                <?php if ($is_owner || $user_role === 'Employee' || $user_role === 'Admin'): ?>
                                    <p><?= htmlspecialchars($customer_info['receiver_address']) ?></p>
                                    <p><?= htmlspecialchars($customer_info['receiver_city'] . ', ' . $customer_info['receiver_state'] . ' ' . $customer_info['receiver_postal']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Tracking Timeline -->
            <div class="bg-white shadow rounded-lg p-6 mb-6">
                <h3 class="text-xl font-semibold mb-4">Tracking History</h3>
                
                <?php if ($tracking_history && $tracking_history->num_rows > 0): ?>
                <div class="space-y-6 pl-2">
                    <?php while ($event = $tracking_history->fetch_assoc()): ?>
                        <div class="flex status-step">
                            <div class="h-8 w-8 rounded-full bg-blue-500 flex items-center justify-center status-bullet">
                                <i class="fas fa-check text-white text-sm"></i>
                            </div>
                            <div class="ml-4">
                                <div class="font-semibold"><?= htmlspecialchars($event['status']) ?></div>
                                <div class="text-sm text-gray-500">
                                    <?= date('M d, Y h:i A', strtotime($event['timestamp'])) ?>
                                </div>
                                <div class="text-gray-600 mt-1">
                                    <?php if (!empty($event['location'])): ?>
                                        <span class="font-medium">Location:</span> <?= htmlspecialchars($event['location']) ?>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($event['facility_city'])): ?>
                                        <div class="mt-1">
                                            <span class="font-medium">Facility:</span> 
                                            <?= htmlspecialchars($event['facility_city'] . ', ' . $event['facility_state'] . ' (' . $event['facility_type'] . ')') ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($event['action']) && $event['action'] !== 'None'): ?>
                                        <div class="mt-1">
                                            <span class="font-medium">Action:</span> <?= htmlspecialchars($event['action']) ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($event['employee_name']) && ($user_role === 'Employee' || $user_role === 'Admin')): ?>
                                        <div class="mt-1 text-sm text-gray-500">
                                            <span class="font-medium">Processed by:</span> 
                                            <?= htmlspecialchars($event['employee_name'] . ' (' . $event['employee_role'] . ')') ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
                <?php else: ?>
                <p class="text-gray-600">No tracking updates available for this package yet.</p>
                <?php endif; ?>
            </div>
            
            <!-- Actions -->
            <div class="flex flex-wrap gap-4 justify-center mt-6">
                <?php if ($user_role === 'Customer'): ?>
                    <a href="support.php?tracking=<?= urlencode($tracking_number) ?>" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                        <i class="fas fa-question-circle mr-2"></i> Need Help?
                    </a>
                <?php endif; ?>
                <a href="customer_dashboard.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                </a>
            </div>
        <?php else: ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6">
                <p>No package details available. Please check the tracking number and try again.</p>
            </div>
            <div class="text-center mt-6">
                <a href="customer_dashboard.php" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                    Back to Dashboard
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html> 