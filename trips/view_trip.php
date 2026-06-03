<?php
require_once '../functions.php';
require_once '../db_connect.php';

// Debug enable
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || 
    ($_SESSION['role'] != 'Employee' && $_SESSION['role'] != 'Admin')) {
    header("Location: ../login.php");
    exit();
}

// Get the trip ID from URL
$trip_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Verify trip exists - now handles both delivery routes and regular trips
$stmt = $conn->prepare("SELECT t.*, 
                      f1.city as depart_city, f1.state as depart_state, f1.type as depart_facility_type,
                      f2.city as arrive_city, f2.state as arrive_state, f2.type as arrive_facility_type,
                      CONCAT(e.first_name, ' ', e.last_name) as employee_name,
                      v.license_plate, v.vehicle_type
                    FROM Trip t
                    JOIN Facility f1 ON t.depart_facility_id = f1.facility_id
                    LEFT JOIN Facility f2 ON t.arrive_facility_id = f2.facility_id
                    LEFT JOIN Employee e ON t.employee_id = e.user_id
                    LEFT JOIN Vehicle v ON t.vehicle_id = v.vehicle_id
                    WHERE t.trip_id = ?");
$stmt->bind_param("i", $trip_id);
$stmt->execute();
$trip = $stmt->get_result()->fetch_assoc();

if (!$trip) {
    // Trip not found
    header("Location: manage_trips.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'Employee';
$emp_stmt = $conn->prepare("SELECT e.first_name, e.last_name, e.role as employee_role, f.city, f.type FROM Employee e JOIN Facility f ON e.facility_id = f.facility_id WHERE e.user_id = ?");
$emp_stmt->bind_param("i", $user_id);
$emp_stmt->execute();
$employee = $emp_stmt->get_result()->fetch_assoc();
if (!$employee) {
    $employee = ['role' => $role, 'first_name' => '', 'last_name' => '', 'type' => '', 'city' => ''];
} else {
    $employee['role'] = $employee['employee_role'];
}
$name = ($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? '');
$unread_admin_messages = 0;
if ($role != 'Admin') {
    $msg_stmt = $conn->prepare("SELECT COUNT(*) as count FROM Admin_Messages WHERE sender_id = ? AND status = 'Replied'");
    $msg_stmt->bind_param("i", $user_id);
    $msg_stmt->execute();
    $unread_admin_messages = $msg_stmt->get_result()->fetch_assoc()['count'] ?? 0;
}

// Determine if this is a delivery route
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
$packages_query->bind_param("i", $trip_id);
$packages_query->execute();
$packages = $packages_query->get_result()->fetch_all(MYSQLI_ASSOC);

// Process form submission for delivery updates
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'update_delivery') {
        $tracking_number = $_POST['tracking_number'] ?? '';
        $delivery_status = $_POST['delivery_status'] ?? '';
        $delivery_time = ($delivery_status == 'Delivered') ? date('Y-m-d H:i:s') : null;
        $notes = $_POST['notes'] ?? '';
        
        if (empty($tracking_number) || empty($delivery_status)) {
            $error = "Tracking number and delivery status are required.";
        } else {
            try {
                // Update package status
                $update = $conn->prepare("UPDATE Package SET status = ? WHERE tracking_number = ?");
                $update->bind_param("ss", $delivery_status, $tracking_number);
                
                if ($update->execute()) {
                    // Add tracking history entry
                    $location = "Delivery to " . $_POST['address'];
                    $employee_id = $_SESSION['user_id'];
                    $history = $conn->prepare("INSERT INTO Tracking_History (tracking_number, location, status, employee_id, action, trip_id, facility_id) 
                                             VALUES (?, ?, ?, ?, 'Delivery', ?, ?)");
                    $history->bind_param("sssiii", $tracking_number, $location, $delivery_status, $employee_id, $trip_id, $trip['depart_facility_id']);
                    $history->execute();
                    
                    $message = "Package #$tracking_number status updated to $delivery_status.";
                    
                    // Refresh the page to show updated data
                    header("Location: view_trip.php?id=$trip_id&message=" . urlencode($message));
                    exit();
                } else {
                    $error = "Error updating delivery: " . $update->error;
                }
            } catch (Exception $e) {
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}

// Get message from URL if redirected
if (isset($_GET['message'])) {
    $message = $_GET['message'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_delivery_route ? 'Delivery Route' : 'Trip' ?> #<?= $trip_id ?> Details</title>
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
<?php $emp_base = '../'; include '../_employee_nav.php'; ?>

    <div class="container mx-auto px-4 py-8 max-w-6xl">
        <header class="mb-6">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center">
                <h1 class="text-3xl font-bold section-title"><?= $is_delivery_route ? 'Delivery Route' : 'Trip' ?> #<?= $trip_id ?> Details</h1>
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
            </ul>
        </nav>

        <div class="flex justify-end mb-4">
            <a href="manage_trips.php" class="action-btn text-white px-4 py-2 rounded">
                <i class="fas fa-arrow-left mr-1"></i> Back to Trips
            </a>
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <!-- Trip Details Card -->
        <div class="bg-white p-6 rounded-lg shadow-sm mb-6">
            <h2 class="text-2xl font-semibold mb-4 section-title"><?= $is_delivery_route ? 'Route' : 'Trip' ?> Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="mb-2"><span class="font-semibold">Origin Facility:</span> <?= htmlspecialchars($trip['depart_city'] . ', ' . $trip['depart_state'] . ' - ' . $trip['depart_facility_type']) ?></p>
                    <?php if (!$is_delivery_route): ?>
                        <p class="mb-2"><span class="font-semibold">Destination Facility:</span> <?= htmlspecialchars($trip['arrive_city'] . ', ' . $trip['arrive_state'] . ' - ' . $trip['arrive_facility_type']) ?></p>
                    <?php endif; ?>
                    <p class="mb-2"><span class="font-semibold"><?= $is_delivery_route ? 'Driver' : 'Employee' ?>:</span> <?= htmlspecialchars($trip['employee_name']) ?></p>
                    <?php if ($trip['vehicle_id']): ?>
                        <p class="mb-2"><span class="font-semibold">Vehicle:</span> <?= htmlspecialchars($trip['vehicle_type'] . ' - ' . $trip['license_plate']) ?></p>
                    <?php endif; ?>
                    <?php if (!$is_delivery_route && $trip['flight_number']): ?>
                        <p class="mb-2"><span class="font-semibold">Flight:</span> <?= htmlspecialchars($trip['airline'] . ' #' . $trip['flight_number']) ?></p>
                    <?php endif; ?>
                </div>
                <div>
                    <p class="mb-2"><span class="font-semibold">Scheduled Departure:</span> <?= date('m/d/Y h:i A', strtotime($trip['depart_time'])) ?></p>
                    <p class="mb-2"><span class="font-semibold">Status:</span> 
                        <?php if ($trip['arrival_time']): ?>
                            <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs font-medium">Completed</span>
                            <span class="ml-2"><?= date('m/d/Y h:i A', strtotime($trip['arrival_time'])) ?></span>
                        <?php else: ?>
                            <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded-full text-xs font-medium">In Progress</span>
                        <?php endif; ?>
                    </p>
                    <p class="mb-2"><span class="font-semibold">Package Count:</span> 
                        <span class="bg-gray-200 px-2 py-1 rounded-full text-xs font-medium"><?= count($packages) ?></span>
                    </p>
                </div>
            </div>
            
            <?php if (!$trip['arrival_time']): ?>
                <div class="mt-6 flex justify-end">
                    <form method="post" action="manage_trips.php">
                        <input type="hidden" name="action" value="update_trip">
                        <input type="hidden" name="trip_id" value="<?= $trip_id ?>">
                        <input type="hidden" name="status" value="completed">
                        <button type="submit" class="accent-btn text-white px-4 py-2 rounded">
                            <i class="fas fa-check-circle mr-1"></i> Mark as Completed
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Packages List -->
        <h2 class="text-2xl font-semibold mb-4 section-title">Packages on This <?= $is_delivery_route ? 'Route' : 'Trip' ?></h2>
        
        <?php if (empty($packages)): ?>
            <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-6">No packages assigned to this <?= $is_delivery_route ? 'delivery route' : 'trip' ?>.</div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($packages as $package): 
                    $delivered = $package['status'] === 'Delivered';
                    $failed = $package['status'] === 'Failed';
                    $borderColor = $delivered ? 'border-green-500' : ($failed ? 'border-red-500' : 'border-blue-500');
                ?>
                    <div class="bg-white rounded-lg shadow-sm border-l-4 <?= $borderColor ?>">
                        <div class="bg-gray-50 px-4 py-3 rounded-t-lg font-medium flex justify-between items-center">
                            <div>
                                <span>Package #<?= htmlspecialchars($package['tracking_number']) ?></span>
                                <?php if ($delivered): ?>
                                    <span class="ml-2 bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs font-medium">Delivered</span>
                                <?php elseif ($failed): ?>
                                    <span class="ml-2 bg-red-100 text-red-800 px-2 py-1 rounded-full text-xs font-medium">Failed</span>
                                <?php elseif ($package['status'] === 'Out for Delivery'): ?>
                                    <span class="ml-2 bg-yellow-100 text-yellow-800 px-2 py-1 rounded-full text-xs font-medium">Out for Delivery</span>
                                <?php else: ?>
                                    <span class="ml-2 bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-xs font-medium"><?= htmlspecialchars($package['status']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <?php if ($package['signature_required'] === 'Y'): ?>
                                    <span class="bg-gray-800 text-white px-2 py-1 rounded-full text-xs">Signature Required</span>
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
                                <div>
                                    <?php if (!$delivered && !$failed && !$trip['arrival_time'] && $is_delivery_route): ?>
                                        <button type="button" class="action-btn text-white px-4 py-2 rounded update-delivery" 
                                                data-toggle="modal" 
                                                data-target="#deliveryModal"
                                                data-tracking="<?= htmlspecialchars($package['tracking_number']) ?>"
                                                data-address="<?= htmlspecialchars($package['street_address'] . ', ' . $package['city'] . ', ' . $package['state'] . ' ' . $package['postal_code']) ?>">
                                            <i class="fas fa-truck-loading mr-1"></i> Update Delivery Status
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <!-- Delivery Update Modal -->
        <?php if ($is_delivery_route): ?>
        <!-- The modal backdrop -->
        <div class="fixed inset-0 bg-black bg-opacity-50 hidden z-40" id="modalBackdrop"></div>
        
        <!-- Modal -->
        <div id="deliveryModal" class="fixed inset-0 flex items-center justify-center hidden z-50">
            <div class="bg-white rounded-lg shadow-lg w-full max-w-md mx-4">
                <div class="flex justify-between items-center border-b p-4">
                    <h3 class="text-lg font-semibold section-title">Update Delivery Status</h3>
                    <button type="button" class="text-gray-500 hover:text-gray-700 close-modal">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="p-4">
                    <form method="post" action="" id="deliveryForm">
                        <input type="hidden" name="action" value="update_delivery">
                        <input type="hidden" name="tracking_number" id="modal_tracking">
                        <input type="hidden" name="address" id="modal_address">
                        
                        <div class="mb-4">
                            <label class="block mb-1 font-medium">Tracking Number</label>
                            <div class="bg-gray-100 p-2 rounded" id="display_tracking"></div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block mb-1 font-medium">Delivery Address</label>
                            <div class="bg-gray-100 p-2 rounded" id="display_address"></div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="delivery_status" class="block mb-1 font-medium">Delivery Status</label>
                            <select class="w-full px-3 py-2 border border-gray-300 rounded focus:border-[#004B87] focus:ring focus:ring-[#004B87] focus:ring-opacity-30" id="delivery_status" name="delivery_status" required>
                                <option value="">-- Select Status --</option>
                                <option value="Delivered">Delivered</option>
                                <option value="Failed">Failed Delivery</option>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label for="notes" class="block mb-1 font-medium">Notes</label>
                            <textarea class="w-full px-3 py-2 border border-gray-300 rounded focus:border-[#004B87] focus:ring focus:ring-[#004B87] focus:ring-opacity-30" id="notes" name="notes" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="flex justify-end p-4 border-t">
                    <button type="button" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded mr-2 close-modal">Cancel</button>
                    <button type="button" class="action-btn text-white px-4 py-2 rounded" id="submitDelivery">Update Status</button>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($is_delivery_route): ?>
            // Modal functionality
            const modal = document.getElementById('deliveryModal');
            const modalBackdrop = document.getElementById('modalBackdrop');
            const closeButtons = document.querySelectorAll('.close-modal');
            
            // Open modal
            document.querySelectorAll('.update-delivery').forEach(button => {
                button.addEventListener('click', function() {
                    const tracking = this.getAttribute('data-tracking');
                    const address = this.getAttribute('data-address');
                    
                    document.getElementById('modal_tracking').value = tracking;
                    document.getElementById('modal_address').value = address;
                    document.getElementById('display_tracking').textContent = tracking;
                    document.getElementById('display_address').textContent = address;
                    
                    // Reset form
                    document.getElementById('delivery_status').value = '';
                    document.getElementById('notes').value = '';
                    
                    // Show modal
                    modal.classList.remove('hidden');
                    modalBackdrop.classList.remove('hidden');
                });
            });
            
            // Close modal
            closeButtons.forEach(button => {
                button.addEventListener('click', function() {
                    modal.classList.add('hidden');
                    modalBackdrop.classList.add('hidden');
                });
            });
            
            // Form submission
            document.getElementById('submitDelivery').addEventListener('click', function() {
                document.getElementById('deliveryForm').submit();
            });
            
            // Handle delivery status change
            document.getElementById('delivery_status').addEventListener('change', function() {
                // No special handling needed for status changes anymore
            });
            <?php endif; ?>
        });
    </script>
</body>
</html> 