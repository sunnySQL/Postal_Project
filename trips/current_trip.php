<?php
require_once '../functions.php';
require_once '../db_connect.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || 
    ($_SESSION['role'] != 'Employee' && $_SESSION['role'] != 'Admin')) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'Employee';

// Get employee info
$stmt = $conn->prepare("SELECT e.*, f.city, f.type 
                      FROM Employee e 
                      JOIN Facility f ON e.facility_id = f.facility_id 
                      WHERE e.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$employee = $stmt->get_result()->fetch_assoc();
$name = ($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? '');

$unread_admin_messages = 0;
if ($role != 'Admin') {
    $msg_stmt = $conn->prepare("SELECT COUNT(*) as count FROM Admin_Messages WHERE sender_id = ? AND status = 'Replied'");
    $msg_stmt->bind_param("i", $user_id);
    $msg_stmt->execute();
    $unread_admin_messages = $msg_stmt->get_result()->fetch_assoc()['count'] ?? 0;
}

// Check if the employee is a Driver or Pilot
if ($employee['role'] != 'Driver' && $employee['role'] != 'Pilot' && $_SESSION['role'] != 'Admin') {
    header("Location: ../employee_dashboard.php");
    exit();
}

// Get active trip for the employee
$trip_query = "SELECT t.*, 
              f1.city as depart_city, f1.state as depart_state, f1.type as depart_facility_type,
              f2.city as arrive_city, f2.state as arrive_state, f2.type as arrive_facility_type,
              v.license_plate, v.vehicle_type,
              COUNT(tp.tracking_number) as package_count,
              (SELECT COUNT(*) FROM Trip_Package tp2 
                JOIN Package p ON tp2.tracking_number = p.tracking_number 
                WHERE tp2.trip_id = t.trip_id AND p.status = 'Delivered') as delivered_count
              FROM Trip t
              JOIN Facility f1 ON t.depart_facility_id = f1.facility_id
              LEFT JOIN Facility f2 ON t.arrive_facility_id = f2.facility_id
              LEFT JOIN Vehicle v ON t.vehicle_id = v.vehicle_id
              LEFT JOIN Trip_Package tp ON t.trip_id = tp.trip_id
              WHERE t.employee_id = ? AND t.arrival_time IS NULL
              GROUP BY t.trip_id
              ORDER BY t.depart_time ASC
              LIMIT 1";
$stmt = $conn->prepare($trip_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$trip = $stmt->get_result()->fetch_assoc();

// Process form submissions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Handle completion of trip
    if (isset($_POST['action']) && $_POST['action'] == 'complete_trip') {
        $trip_id = $_POST['trip_id'];
        
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Update trip to completed
            $arrival_time = date('Y-m-d H:i:s');
            $update_trip = $conn->prepare("UPDATE Trip SET arrival_time = ? WHERE trip_id = ?");
            $update_trip->bind_param("si", $arrival_time, $trip_id);
            $update_trip->execute();
            
            // For facility to facility trips, update package statuses
            if (!$trip['is_delivery_route']) {
                // Get all packages on this trip
                $get_packages = $conn->prepare("SELECT tp.tracking_number FROM Trip_Package tp WHERE tp.trip_id = ?");
                $get_packages->bind_param("i", $trip_id);
                $get_packages->execute();
                $result = $get_packages->get_result();
                
                while ($package = $result->fetch_assoc()) {
                    // Update package status to In Transit or Arrived at Facility
                    $new_status = 'Arrived at Facility';
                    $update_package = $conn->prepare("UPDATE Package SET status = ?, facility_id = ? WHERE tracking_number = ?");
                    $update_package->bind_param("sis", $new_status, $trip['arrive_facility_id'], $package['tracking_number']);
                    $update_package->execute();
                    
                    // Add tracking history entry
                    $location = "Arrived at " . $trip['arrive_city'] . ' ' . $trip['arrive_facility_type'];
                    $history = $conn->prepare("INSERT INTO Tracking_History (tracking_number, location, status, employee_id, action, trip_id, facility_id) 
                                             VALUES (?, ?, ?, ?, 'None', ?, ?)");
                    $history->bind_param("sssiii", $package['tracking_number'], $location, $new_status, $user_id, $trip_id, $trip['arrive_facility_id']);
                    $history->execute();
                }
            }
            
            // Commit transaction
            $conn->commit();
            $message = "Trip #$trip_id has been successfully completed!";
            
            // Redirect to refresh the page
            header("Location: current_trip.php?message=" . urlencode($message));
            exit();
        } catch (Exception $e) {
            // Roll back transaction on error
            $conn->rollback();
            $error = "Error completing trip: " . $e->getMessage();
        }
    }
    
    // Handle package delivery update
    if (isset($_POST['action']) && $_POST['action'] == 'update_delivery') {
        $tracking_number = $_POST['tracking_number'];
        $delivery_status = $_POST['delivery_status'];
        $signature = isset($_POST['signature']) ? 'Y' : 'N';
        $notes = $_POST['notes'];
        $trip_id = $_POST['trip_id'];
        
        try {
            // Update package status
            $update = $conn->prepare("UPDATE Package SET status = ? WHERE tracking_number = ?");
            $update->bind_param("ss", $delivery_status, $tracking_number);
            
            if ($update->execute()) {
                // Add tracking history entry
                $location = "Delivery attempt at " . $_POST['address'];
                $employee_id = $_SESSION['user_id'];
                $history = $conn->prepare("INSERT INTO Tracking_History (tracking_number, location, status, employee_id, action, trip_id, facility_id) 
                                         VALUES (?, ?, ?, ?, 'Delivery', ?, ?)");
                $history->bind_param("sssiii", $tracking_number, $location, $delivery_status, $employee_id, $trip_id, $trip['depart_facility_id']);
                $history->execute();
                
                $message = "Package #$tracking_number status updated to $delivery_status.";
                
                // Redirect to refresh the page
                header("Location: current_trip.php?message=" . urlencode($message));
                exit();
            } else {
                $error = "Error updating delivery: " . $update->error;
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Get message from URL if redirected
if (isset($_GET['message'])) {
    $message = $_GET['message'];
}

// Get packages for the current trip if a trip exists
$packages = [];
if ($trip) {
    $is_delivery_route = $trip['is_delivery_route'] == 1;
    
    // Get all packages on this trip
    $packages_query = $conn->prepare("SELECT 
                                tp.tracking_number,
                                p.weight, p.size, p.postage, p.signature_required,
                                p.status, c.first_name, c.last_name, c.street_address,
                                c.city, c.state, c.postal_code, c.phone
                              FROM Trip_Package tp
                              JOIN Package p ON tp.tracking_number = p.tracking_number
                              JOIN Customer c ON p.receiver_id = c.user_id
                              WHERE tp.trip_id = ?
                              ORDER BY c.postal_code, c.street_address");
    $packages_query->bind_param("i", $trip['trip_id']);
    $packages_query->execute();
    $packages = $packages_query->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // If it's a delivery route, filter out delivered packages
    if ($is_delivery_route) {
        $undelivered_packages = [];
        foreach ($packages as $package) {
            if ($package['status'] != 'Delivered' && $package['status'] != 'Failed') {
                $undelivered_packages[] = $package;
            }
        }
        $delivered_packages = array_filter($packages, function($package) {
            return $package['status'] == 'Delivered' || $package['status'] == 'Failed';
        });
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Current Trip</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .section-title { color: #004B87; }
        .action-btn { background-color: #004B87; transition: background-color 0.3s; }
        .action-btn:hover { background-color: #003366; }
    </style>
</head>
<body class="bg-gray-50">
<?php $emp_base = '../'; include '../_employee_nav.php'; ?>

    <div class="container mx-auto px-4 py-8 max-w-6xl">
        <header class="mb-6">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center">
                <h1 class="text-3xl font-bold section-title">Current Trip</h1>
                <div class="mt-4 md:mt-0 bg-white shadow-sm p-4 rounded-lg">
                    <p class="mb-1"><span class="font-semibold">Welcome,</span> <?= htmlspecialchars($name) ?> | <span class="font-medium"><?= htmlspecialchars($employee['role']) ?></span></p>
                    <p><span class="font-semibold">Facility:</span> <?= htmlspecialchars($employee['type']) ?> - <?= htmlspecialchars($employee['city']) ?></p>
                </div>
            </div>
        </header>

        <nav class="bg-white shadow-sm p-4 rounded-lg mb-6">
            <ul class="flex flex-wrap space-x-2 md:space-x-6">
                <?php if ($role == 'Admin'): ?>
                <li><a href="../admin/manage_users.php" class="text-gray-700 hover:text-[#DA291C]">Manage Users</a></li>
                <li><a href="../admin/manage_facilities.php" class="text-gray-700 hover:text-[#DA291C]">Manage Facilities</a></li>
                <li><a href="../admin/manage_vehicles.php" class="text-gray-700 hover:text-[#DA291C]">Manage Vehicles</a></li>
                <li><a href="../admin/inbox.php" class="text-gray-700 hover:text-[#DA291C]">Admin Inbox</a></li>
                <?php else: ?>
                <li><a href="../employee/contact_admin.php" class="text-gray-700 hover:text-[#DA291C] flex items-center">
                    Contact Admin
                    <?php if ($unread_admin_messages > 0): ?>
                        <span class="ml-2 bg-red-600 text-white text-xs px-2 py-1 rounded-full"><?= $unread_admin_messages ?></span>
                    <?php endif; ?>
                </a></li>
                <?php endif; ?>
                <?php if (isset($employee['role'])): ?>
                <?php if ($employee['role'] == 'Clerk'): ?>
                <li><a href="../package/new_package.php" class="text-gray-700 hover:text-[#DA291C]">Create Shipment</a></li>
                <li><a href="../shop/inventory_management.php" class="text-gray-700 hover:text-[#DA291C]">Inventory Management</a></li>
                <li><a href="../package/search.php" class="text-gray-700 hover:text-[#DA291C]">Package Search &amp; Scan</a></li>
                <?php endif; ?>
                <?php if ($employee['role'] == 'Manager'): ?>
                <li><a href="../shop/shop_dashboard.php" class="text-gray-700 hover:text-[#DA291C]">Shop Overview</a></li>
                <li><a href="../reports/index.php" class="text-gray-700 hover:text-[#DA291C]">Reports</a></li>
                <li><a href="manage_trips.php" class="text-gray-700 hover:text-[#DA291C]">Manage Trips</a></li>
                <?php endif; ?>
                <?php endif; ?>
                <?php if (isset($employee['role']) && $employee['role'] == 'Clerk'): ?>
                <li><a href="../package/awaiting_pickup.php" class="text-gray-700 hover:text-[#DA291C]">Pickup</a></li>
                <?php endif; ?>
                <?php if ($role != 'Admin'): ?>
                <li><a href="../edit_profile.php" class="text-gray-700 hover:text-[#DA291C]">Edit Profile</a></li>
                <?php endif; ?>
            </ul>
        </nav>

        <nav class="bg-gray-100 border border-gray-200 rounded-lg px-4 py-3 mb-6">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Trips</p>
            <ul class="flex flex-wrap gap-x-4 gap-y-1">
                <li><a href="manage_trips.php" class="text-gray-700 hover:text-[#DA291C]">Manage Trips</a></li>
                <li><a href="current_trip.php" class="text-gray-700 hover:text-[#DA291C] border-b-2 border-[#DA291C] pb-0.5 font-medium">My Current Trip</a></li>
            </ul>
        </nav>
        
        <?php if (!empty($message)): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if (!$trip): ?>
            <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-6">
                <p>You don't have any active trips assigned. Please contact your supervisor for more information.</p>
            </div>
            <div class="mt-4">
                <a href="../employee_dashboard.php" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                    Return to Dashboard
                </a>
            </div>
        <?php else: ?>
            <!-- Trip Details Card -->
            <div class="bg-gray-100 p-6 rounded-lg mb-6">
                <h2 class="text-2xl font-semibold mb-4"><?= $is_delivery_route ? 'Delivery Route' : 'Trip' ?> Information</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="mb-2"><span class="font-semibold">Trip ID:</span> #<?= htmlspecialchars($trip['trip_id']) ?></p>
                        <p class="mb-2"><span class="font-semibold">Origin Facility:</span> <?= htmlspecialchars($trip['depart_city'] . ', ' . $trip['depart_state'] . ' - ' . $trip['depart_facility_type']) ?></p>
                        <?php if (!$is_delivery_route): ?>
                            <p class="mb-2"><span class="font-semibold">Destination Facility:</span> <?= htmlspecialchars($trip['arrive_city'] . ', ' . $trip['arrive_state'] . ' - ' . $trip['arrive_facility_type']) ?></p>
                        <?php endif; ?>
                        <?php if ($trip['vehicle_id']): ?>
                            <p class="mb-2"><span class="font-semibold">Vehicle:</span> <?= htmlspecialchars($trip['vehicle_type'] . ' - ' . $trip['license_plate']) ?></p>
                        <?php endif; ?>
                        <?php if (!$is_delivery_route && $trip['flight_number']): ?>
                            <p class="mb-2"><span class="font-semibold">Flight:</span> <?= htmlspecialchars($trip['airline'] . ' #' . $trip['flight_number']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <p class="mb-2"><span class="font-semibold">Scheduled Departure:</span> <?= date('m/d/Y h:i A', strtotime($trip['depart_time'])) ?></p>
                        <p class="mb-2"><span class="font-semibold">Type:</span> 
                            <span class="<?= $trip['trip_type'] == 'Air' ? 'bg-blue-100 text-blue-800' : ($is_delivery_route ? 'bg-green-100 text-green-800' : 'bg-purple-100 text-purple-800') ?> px-2 py-1 rounded-full text-xs">
                                <?= $is_delivery_route ? 'Delivery Route' : htmlspecialchars($trip['trip_type'] . ' Transport') ?>
                            </span>
                        </p>
                        <p class="mb-2"><span class="font-semibold">Packages:</span> 
                            <span class="bg-gray-200 px-2 py-1 rounded-full text-xs font-medium"><?= $trip['package_count'] ?> total</span>
                            <?php if ($is_delivery_route): ?>
                                <span class="bg-green-200 px-2 py-1 rounded-full text-xs font-medium ml-1"><?= $trip['delivered_count'] ?> delivered</span>
                                <span class="bg-yellow-200 px-2 py-1 rounded-full text-xs font-medium ml-1"><?= $trip['package_count'] - $trip['delivered_count'] ?> remaining</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end">
                    <?php if (!$is_delivery_route || ($is_delivery_route && $trip['delivered_count'] == $trip['package_count'])): ?>
                        <form method="post" action="current_trip.php" onsubmit="return confirm('Are you sure you want to mark this trip as complete? This cannot be undone.')">
                            <input type="hidden" name="action" value="complete_trip">
                            <input type="hidden" name="trip_id" value="<?= $trip['trip_id'] ?>">
                            <button type="submit" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded">
                                <i class="fas fa-check-circle mr-1"></i> Mark Trip as Complete
                            </button>
                        </form>
                    <?php elseif ($is_delivery_route): ?>
                        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4">
                            You need to deliver all packages before marking this route as complete.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($is_delivery_route): ?>
                <!-- Undelivered Packages -->
                <h2 class="text-2xl font-semibold mb-4">Packages to Deliver</h2>
                
                <?php if (empty($undelivered_packages)): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
                        All packages have been delivered! You can now mark this route as complete.
                    </div>
                <?php else: ?>
                    <div class="space-y-4 mb-8">
                        <?php foreach ($undelivered_packages as $package): ?>
                            <div class="bg-white rounded-lg shadow-sm border-l-4 border-blue-500">
                                <div class="bg-gray-50 px-4 py-3 rounded-t-lg font-medium flex justify-between items-center">
                                    <div>
                                        <span>Package #<?= htmlspecialchars($package['tracking_number']) ?></span>
                                        <span class="ml-2 bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-xs font-medium"><?= htmlspecialchars($package['status']) ?></span>
                                    </div>
                                    <div>
                                        <?php if ($package['signature_required'] === 'Y'): ?>
                                            <span class="bg-red-100 text-red-800 px-2 py-1 rounded-full text-xs">Signature Required</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="p-4">
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                        <div>
                                            <h3 class="text-lg font-medium mb-2">Recipient</h3>
                                            <p class="text-gray-700">
                                                <?= htmlspecialchars($package['first_name'] . ' ' . $package['last_name']) ?><br>
                                                <?= htmlspecialchars($package['street_address']) ?><br>
                                                <?= htmlspecialchars($package['city'] . ', ' . $package['state'] . ' ' . $package['postal_code']) ?><br>
                                                <?= htmlspecialchars($package['phone']) ?>
                                            </p>
                                        </div>
                                        <div>
                                            <h3 class="text-lg font-medium mb-2">Package Details</h3>
                                            <p class="mb-1"><span class="font-medium">Size:</span> <?= htmlspecialchars($package['size']) ?></p>
                                            <p class="mb-1"><span class="font-medium">Weight:</span> <?= htmlspecialchars($package['weight']) ?> lbs</p>
                                            <p><span class="font-medium">Postage:</span> $<?= number_format($package['postage'], 2) ?></p>
                                        </div>
                                        <div class="flex items-center">
                                            <button type="button" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded w-full update-delivery" 
                                                    data-toggle="modal" 
                                                    data-target="#deliveryModal"
                                                    data-tracking="<?= htmlspecialchars($package['tracking_number']) ?>"
                                                    data-signature="<?= htmlspecialchars($package['signature_required']) ?>"
                                                    data-address="<?= htmlspecialchars($package['street_address'] . ', ' . $package['city'] . ', ' . $package['state'] . ' ' . $package['postal_code']) ?>">
                                                <i class="fas fa-truck-loading mr-1"></i> Update Delivery Status
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                
                    <!-- Delivered Packages (Collapsed by Default) -->
                    <?php if (!empty($delivered_packages)): ?>
                        <div class="mb-8">
                            <button class="flex items-center justify-between w-full p-3 bg-gray-100 rounded-lg focus:outline-none" id="toggle-delivered">
                                <h2 class="text-xl font-semibold">Delivered Packages (<?= count($delivered_packages) ?>)</h2>
                                <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            
                            <div class="hidden space-y-4 mt-4" id="delivered-packages">
                                <?php foreach ($delivered_packages as $package): 
                                    $borderColor = $package['status'] === 'Delivered' ? 'border-green-500' : 'border-red-500';
                                ?>
                                    <div class="bg-white rounded-lg shadow-sm border-l-4 <?= $borderColor ?>">
                                        <div class="bg-gray-50 px-4 py-3 rounded-t-lg font-medium flex justify-between items-center">
                                            <div>
                                                <span>Package #<?= htmlspecialchars($package['tracking_number']) ?></span>
                                                <span class="ml-2 <?= $package['status'] === 'Delivered' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?> px-2 py-1 rounded-full text-xs font-medium">
                                                    <?= htmlspecialchars($package['status']) ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="p-4">
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                                <div>
                                                    <h3 class="text-lg font-medium mb-2">Recipient</h3>
                                                    <p class="text-gray-700">
                                                        <?= htmlspecialchars($package['first_name'] . ' ' . $package['last_name']) ?><br>
                                                        <?= htmlspecialchars($package['street_address']) ?><br>
                                                        <?= htmlspecialchars($package['city'] . ', ' . $package['state'] . ' ' . $package['postal_code']) ?>
                                                    </p>
                                                </div>
                                                <div>
                                                    <h3 class="text-lg font-medium mb-2">Package Details</h3>
                                                    <p class="mb-1"><span class="font-medium">Size:</span> <?= htmlspecialchars($package['size']) ?></p>
                                                    <p class="mb-1"><span class="font-medium">Weight:</span> <?= htmlspecialchars($package['weight']) ?> lbs</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php else: ?>
                <!-- Facility to Facility Trip Packages -->
                <h2 class="text-2xl font-semibold mb-4">Packages in Transit</h2>
                
                <?php if (empty($packages)): ?>
                    <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-6">No packages assigned to this trip.</div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white rounded-lg overflow-hidden">
                            <thead class="bg-gray-100 text-gray-700">
                                <tr>
                                    <th class="px-4 py-3 text-left">Tracking No.</th>
                                    <th class="px-4 py-3 text-left">Status</th>
                                    <th class="px-4 py-3 text-left">Recipient</th>
                                    <th class="px-4 py-3 text-left">Destination</th>
                                    <th class="px-4 py-3 text-left">Details</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($packages as $package): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 font-medium"><?= htmlspecialchars($package['tracking_number']) ?></td>
                                    <td class="px-4 py-3">
                                        <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-xs font-medium">
                                            <?= htmlspecialchars($package['status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3"><?= htmlspecialchars($package['first_name'] . ' ' . $package['last_name']) ?></td>
                                    <td class="px-4 py-3"><?= htmlspecialchars($package['city'] . ', ' . $package['state']) ?></td>
                                    <td class="px-4 py-3 text-sm">
                                        <?= htmlspecialchars($package['size']) ?>, <?= htmlspecialchars($package['weight']) ?> lbs
                                        <?php if ($package['signature_required'] === 'Y'): ?>
                                            <span class="bg-red-100 text-red-800 px-2 py-1 rounded-full text-xs ml-1">Sig. Req.</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <!-- Delivery Update Modal -->
            <?php if ($is_delivery_route): ?>
            <div id="deliveryModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center hidden">
                <div class="bg-white rounded-lg overflow-hidden shadow-xl max-w-lg w-full">
                    <div class="bg-blue-500 px-4 py-3 text-white">
                        <h3 class="text-lg font-medium">Update Delivery Status</h3>
                    </div>
                    <form method="post" action="current_trip.php">
                        <input type="hidden" name="action" value="update_delivery">
                        <input type="hidden" name="tracking_number" id="modal-tracking">
                        <input type="hidden" name="address" id="modal-address">
                        <input type="hidden" name="trip_id" value="<?= $trip['trip_id'] ?>">
                        
                        <div class="p-4">
                            <div class="mb-4">
                                <label class="block text-gray-700 font-medium mb-2">Delivery Status</label>
                                <select name="delivery_status" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="Delivered">Delivered Successfully</option>
                                    <option value="Failed">Delivery Failed</option>
                                </select>
                            </div>
                            
                            <div class="mb-4" id="signature-container">
                                <label class="flex items-center">
                                    <input type="checkbox" name="signature" class="mr-2">
                                    <span>Signature Obtained</span>
                                </label>
                                <p class="text-sm text-gray-500 mt-1">Required for packages marked as "Signature Required"</p>
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-gray-700 font-medium mb-2">Notes</label>
                                <textarea name="notes" rows="3" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                          placeholder="Add delivery notes here..."></textarea>
                            </div>
                        </div>
                        
                        <div class="bg-gray-50 px-4 py-3 flex justify-end">
                            <button type="button" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded mr-2" id="close-modal">Cancel</button>
                            <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">Update Status</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <script>
                // Delivery modal functionality
                document.addEventListener('DOMContentLoaded', function() {
                    const modal = document.getElementById('deliveryModal');
                    const updateButtons = document.querySelectorAll('.update-delivery');
                    const closeButton = document.getElementById('close-modal');
                    
                    updateButtons.forEach(button => {
                        button.addEventListener('click', function() {
                            const tracking = this.getAttribute('data-tracking');
                            const address = this.getAttribute('data-address');
                            const signatureRequired = this.getAttribute('data-signature');
                            
                            document.getElementById('modal-tracking').value = tracking;
                            document.getElementById('modal-address').value = address;
                            
                            // If signature is required, check the box and make it required
                            const signatureContainer = document.getElementById('signature-container');
                            if (signatureRequired === 'Y') {
                                signatureContainer.classList.add('font-semibold', 'text-red-600');
                            } else {
                                signatureContainer.classList.remove('font-semibold', 'text-red-600');
                            }
                            
                            modal.classList.remove('hidden');
                        });
                    });
                    
                    closeButton.addEventListener('click', function() {
                        modal.classList.add('hidden');
                    });
                    
                    // Close modal if clicking outside of it
                    modal.addEventListener('click', function(e) {
                        if (e.target === modal) {
                            modal.classList.add('hidden');
                        }
                    });
                    
                    // Toggle delivered packages section
                    const toggleButton = document.getElementById('toggle-delivered');
                    const deliveredPackages = document.getElementById('delivered-packages');
                    
                    if (toggleButton && deliveredPackages) {
                        toggleButton.addEventListener('click', function() {
                            deliveredPackages.classList.toggle('hidden');
                            const icon = this.querySelector('svg');
                            if (deliveredPackages.classList.contains('hidden')) {
                                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>';
                            } else {
                                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>';
                            }
                        });
                    }
                });
            </script>
            <?php endif; ?>
        <?php endif; ?>
        
        <footer class="text-center mt-10 py-4 text-gray-600 border-t border-gray-300">
            <p>&copy; <?= date('Y') ?> Postal Service Management System</p>
        </footer>
    </div>
</body>
</html> 