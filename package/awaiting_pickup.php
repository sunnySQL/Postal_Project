<?php
// Enable error reporting for debugging (can be removed in production)
session_start();
require_once '../db_connect.php';
require_once '../functions.php';

// Check if user is logged in and is an employee
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || 
    ($_SESSION['role'] != 'Employee' && $_SESSION['role'] != 'Admin')) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get employee info
$stmt = $conn->prepare("SELECT e.*, f.facility_id, f.city, f.type 
                       FROM Employee e 
                       JOIN Facility f ON e.facility_id = f.facility_id 
                       WHERE e.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc();

// Make sure the employee is authorized (currently just Clerks and Admins)
if ($employee['role'] != 'Clerk' && $role != 'Admin') {
    header("Location: ../employee_dashboard.php");
    exit();
}

$facility_id = $employee['facility_id'];
$facility_name = $employee['type'] . ' - ' . $employee['city'];
$name = trim(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? '')) ?: 'Employee';

$unread_admin_messages = 0;
if ($role !== 'Admin') {
    $msg_stmt = $conn->prepare("SELECT COUNT(*) as count FROM Admin_Messages WHERE sender_id = ? AND status = 'Replied'");
    $msg_stmt->bind_param("i", $user_id);
    $msg_stmt->execute();
    $unread_admin_messages = (int)($msg_stmt->get_result()->fetch_assoc()['count'] ?? 0);
}

// Process actions
$message = '';
$message_type = '';

if (isset($_POST['action']) && isset($_POST['tracking_number'])) {
    $tracking = $_POST['tracking_number'];
    $item_type = $_POST['item_type'] ?? 'package';
    
    if ($_POST['action'] == 'deliver') {
        if ($item_type == 'package') {
            // Mark regular package as delivered
            $update_stmt = $conn->prepare("UPDATE Package SET status = 'Delivered' WHERE tracking_number = ? AND facility_id = ?");
            $update_stmt->bind_param("si", $tracking, $facility_id);
            
            if ($update_stmt->execute()) {
                // Add tracking history entry
                $history_stmt = $conn->prepare("INSERT INTO Tracking_History (tracking_number, employee_id, facility_id, action) VALUES (?, ?, ?, 'Delivered to Customer')");
                $history_stmt->bind_param("sii", $tracking, $user_id, $facility_id);
                $history_stmt->execute();
                
                $message = "Package $tracking has been marked as delivered.";
                $message_type = 'success';
            } else {
                $message = "Error updating package status: " . $conn->error;
                $message_type = 'error';
            }
        } else {
            // Mark shop purchase as picked up
            $transaction_id = $_POST['transaction_id'] ?? 0;
            $update_stmt = $conn->prepare("UPDATE Shop_Transaction SET transaction_status = 'Picked Up' WHERE transaction_id = ?");
            $update_stmt->bind_param("i", $transaction_id);
            
            if ($update_stmt->execute()) {
                $message = "Shop purchase #$transaction_id has been marked as picked up.";
                $message_type = 'success';
            } else {
                $message = "Error updating shop purchase status: " . $conn->error;
                $message_type = 'error';
            }
        }
    } elseif ($_POST['action'] == 'notify') {
        if ($item_type == 'package') {
            // Notify customer about package
            $history_stmt = $conn->prepare("INSERT INTO Tracking_History (tracking_number, employee_id, facility_id, action) VALUES (?, ?, ?, 'Customer Notified')");
            $history_stmt->bind_param("sii", $tracking, $user_id, $facility_id);
            
            if ($history_stmt->execute()) {
                $message = "Customer has been notified about package $tracking.";
                $message_type = 'success';
            } else {
                $message = "Error recording notification: " . $conn->error;
                $message_type = 'error';
            }
        } else {
            // Notify customer about shop purchase
            $transaction_id = $_POST['transaction_id'] ?? 0;
            
            // Get customer details
            $customer_stmt = $conn->prepare("
                SELECT c.email, c.phone, c.first_name, c.last_name, s.shop_name
                FROM Shop_Transaction t
                JOIN Customer c ON t.user_id = c.user_id
                JOIN Shop s ON t.shop_id = s.shop_id
                WHERE t.transaction_id = ?
            ");
            $customer_stmt->bind_param("i", $transaction_id);
            $customer_stmt->execute();
            $customer_data = $customer_stmt->get_result()->fetch_assoc();
            
            if ($customer_data) {
                // In a real system, this would send an email/SMS
                // For this project, just mark as notified
                // Check if notification_sent column exists
                $columnsResult = $conn->query("SHOW COLUMNS FROM Shop_Transaction LIKE 'notification_sent'");
                if ($columnsResult->num_rows > 0) {
                    $update_stmt = $conn->prepare("UPDATE Shop_Transaction SET notification_sent = 1 WHERE transaction_id = ?");
                } else {
                    $update_stmt = $conn->prepare("UPDATE Shop_Transaction SET transaction_date = transaction_date WHERE transaction_id = ?");
                }
                $update_stmt->bind_param("i", $transaction_id);
                
                if ($update_stmt->execute()) {
                    $message = "Customer has been notified about shop purchase #$transaction_id.";
                    $message_type = 'success';
                } else {
                    $message = "Error recording notification: " . $conn->error;
                    $message_type = 'error';
                }
            } else {
                $message = "Customer data not found for transaction #$transaction_id.";
                $message_type = 'error';
            }
        }
    }
}

// Handle filters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'ready_since';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'ASC';

// Validate sort parameters
$allowed_sort_fields = ['tracking_number', 'customer_name', 'ready_since', 'waiting_days'];
if (!in_array($sort_by, $allowed_sort_fields)) {
    $sort_by = 'ready_since';
}

$allowed_sort_orders = ['ASC', 'DESC'];
if (!in_array($sort_order, $allowed_sort_orders)) {
    $sort_order = 'ASC';
}

// Build the query
try {
    $query = "SELECT p.tracking_number, p.timestamp_created, p.weight, p.shipping_speed as service_type,
                    c.first_name, c.last_name, c.phone,
                    (SELECT MAX(timestamp) FROM Tracking_History 
                    WHERE tracking_number = p.tracking_number 
                    AND action = 'Ready for Pickup') as ready_since,
                    'Package' as item_type,
                    NULL as shop_transaction_id,
                    NULL as transaction_date
            FROM Package p
            JOIN Customer c ON p.receiver_id = c.user_id
            WHERE p.facility_id = ? AND p.status = 'Ready for Pickup'
            
            UNION
            
            SELECT CONCAT('ST', t.transaction_id) as tracking_number, 
                    t.transaction_date as timestamp_created,
                    NULL as weight,
                    'Shop Purchase' as service_type,
                    c.first_name, c.last_name, c.phone,
                    t.transaction_date as ready_since,
                    'Shop' as item_type,
                    t.transaction_id as shop_transaction_id,
                    t.transaction_date
            FROM Shop_Transaction t
            JOIN Customer c ON t.user_id = c.user_id
            JOIN Shop s ON t.shop_id = s.shop_id
            WHERE s.facility_id = ? 
            AND t.transaction_status = 'Completed'";

    $params = [$facility_id, $facility_id];
    $types = "ii";

    if (!empty($search)) {
        $query = "SELECT * FROM ($query) as combined 
                WHERE (tracking_number LIKE ? OR 
                        first_name LIKE ? OR 
                        last_name LIKE ? OR 
                        phone LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
        $types .= "ssss";
    }

    // Add sorting
    if ($sort_by == 'customer_name') {
        $query .= " ORDER BY last_name $sort_order, first_name $sort_order";
    } elseif ($sort_by == 'ready_since') {
        $query .= " ORDER BY ready_since $sort_order";
    } elseif ($sort_by == 'waiting_days') {
        // This is a special case since waiting_days is calculated
        $query .= " ORDER BY ready_since " . ($sort_order == 'ASC' ? 'DESC' : 'ASC');
    } else {
        $query .= " ORDER BY $sort_by $sort_order";
    }

    // Prepare statement
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $packages = $result;
    $total_packages = $packages->num_rows;

} catch (Exception $e) {
    // If there's an error in the query, log it and set an empty result
    error_log("Query error in awaiting_pickup.php: " . $e->getMessage());
    $packages = null; // Set to null instead of trying to create an empty mysqli_result
    $total_packages = 0;
    $message = "Error loading package data: " . $e->getMessage();
    $message_type = 'error';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Packages Awaiting Pickup - POSTAL PRO</title>
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
                <h1 class="text-3xl font-bold section-title">Packages Awaiting Pickup</h1>
                <div class="mt-4 md:mt-0 bg-white shadow-sm p-4 rounded-lg">
                    <p class="text-sm"><span class="font-semibold">Facility:</span> <?= htmlspecialchars($facility_name) ?></p>
                    <p class="text-sm"><span class="font-semibold">Total Packages:</span> <?= $total_packages ?></p>
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
                <li><a href="../employee/contact_admin.php" class="text-gray-700 hover:text-[#DA291C] flex items-center">Contact Admin<?php if ($unread_admin_messages > 0): ?> <span class="ml-2 bg-red-600 text-white text-xs px-2 py-1 rounded-full"><?= $unread_admin_messages ?></span><?php endif; ?></a></li>
                <?php endif; ?>
                <?php if ($employee['role'] == 'Clerk'): ?>
                <li><a href="new_package.php" class="text-gray-700 hover:text-[#DA291C]">Create Shipment</a></li>
                <li><a href="../shop/inventory_management.php" class="text-gray-700 hover:text-[#DA291C]">Inventory Management</a></li>
                <li><a href="search.php" class="text-gray-700 hover:text-[#DA291C]">Package Search &amp; Scan</a></li>
                <li><a href="awaiting_pickup.php" class="text-gray-700 hover:text-[#DA291C] border-b-2 border-[#DA291C] pb-1 font-medium">Pickup</a></li>
                <?php endif; ?>
                <?php if ($employee['role'] == 'Manager'): ?>
                <li><a href="../shop/inventory_management.php" class="text-gray-700 hover:text-[#DA291C]">Inventory Management</a></li>
                <li><a href="../reports/index.php" class="text-gray-700 hover:text-[#DA291C]">Reports</a></li>
                <li><a href="../trips/manage_trips.php" class="text-gray-700 hover:text-[#DA291C]">Manage Trips</a></li>
                <?php endif; ?>
                <?php if ($role != 'Admin'): ?>
                <li><a href="../edit_profile.php" class="text-gray-700 hover:text-[#DA291C]">Edit Profile</a></li>
                <?php endif; ?>
            </ul>
        </nav>
        
        <?php if (!empty($message)): ?>
        <div class="mb-6 p-4 rounded-lg <?= $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>
        
        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
            <!-- Search and Filter Form -->
            <form action="" method="GET" class="mb-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="col-span-2">
                        <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>" 
                               placeholder="Tracking #, Name, Phone, Email" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label for="sort_by" class="block text-sm font-medium text-gray-700 mb-1">Sort By</label>
                        <select id="sort_by" name="sort_by" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                            <option value="ready_since" <?= $sort_by === 'ready_since' ? 'selected' : '' ?>>Ready Since</option>
                            <option value="waiting_days" <?= $sort_by === 'waiting_days' ? 'selected' : '' ?>>Waiting Time</option>
                            <option value="tracking_number" <?= $sort_by === 'tracking_number' ? 'selected' : '' ?>>Tracking Number</option>
                            <option value="customer_name" <?= $sort_by === 'customer_name' ? 'selected' : '' ?>>Customer Name</option>
                        </select>
                    </div>
                    <div>
                        <label for="sort_order" class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                        <select id="sort_order" name="sort_order" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                            <option value="ASC" <?= $sort_order === 'ASC' ? 'selected' : '' ?>>Ascending</option>
                            <option value="DESC" <?= $sort_order === 'DESC' ? 'selected' : '' ?>>Descending</option>
                        </select>
                    </div>
                </div>
                <div class="mt-4 text-right">
                    <button type="submit" class="action-btn text-white px-4 py-2 rounded">
                        <i class="fas fa-search mr-1"></i> Search
                    </button>
                    <a href="awaiting_pickup.php" class="ml-2 px-4 py-2 border border-gray-300 rounded text-gray-600 hover:bg-gray-100">
                        Clear
                    </a>
                </div>
            </form>
            
            <!-- Packages Table -->
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="p-3 text-left">Tracking #</th>
                            <th class="p-3 text-left">Recipient</th>
                            <th class="p-3 text-left">Contact</th>
                            <th class="p-3 text-left">Package Info</th>
                            <th class="p-3 text-left">Ready Since</th>
                            <th class="p-3 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($packages !== null && $packages->num_rows > 0):
                            while ($package = $packages->fetch_assoc()):
                                // Calculate how long the package has been waiting
                                $ready_time = strtotime($package['ready_since'] ?? $package['timestamp_created']);
                                $ready_days = floor((time() - $ready_time) / 86400);
                                $waiting_class = $ready_days > 7 ? 'text-red-600 font-bold' : ($ready_days > 3 ? 'text-orange-600' : 'text-gray-600');
                        ?>
                        <tr class="border-b hover:bg-gray-50">
                            <td class="p-3"><?= htmlspecialchars($package['tracking_number']) ?></td>
                            <td class="p-3">
                                <div class="font-medium"><?= htmlspecialchars($package['first_name'] . ' ' . $package['last_name']) ?></div>
                            </td>
                            <td class="p-3">
                                <div class="text-sm"><?= htmlspecialchars($package['phone']) ?></div>
                            </td>
                            <td class="p-3">
                                <?php if ($package['item_type'] === 'Package'): ?>
                                <div class="text-sm"><?= htmlspecialchars($package['service_type']) ?></div>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars($package['weight']) ?> lbs</div>
                                <?php else: ?>
                                <div class="text-sm bg-purple-100 text-purple-800 px-2 py-1 rounded-full inline-block">
                                    <?= htmlspecialchars($package['service_type']) ?>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">Transaction #<?= htmlspecialchars($package['shop_transaction_id']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="p-3">
                                <div><?= date('M d, Y', $ready_time) ?></div>
                                <div class="<?= $waiting_class ?> text-xs">
                                    <?= $ready_days == 0 ? 'Today' : ($ready_days == 1 ? 'Yesterday' : $ready_days . ' days ago') ?>
                                </div>
                            </td>
                            <td class="p-3">
                                <div class="flex flex-col space-y-2">
                                    <?php if ($package['item_type'] === 'Package'): ?>
                                    <form method="post" action="" class="inline">
                                        <input type="hidden" name="tracking_number" value="<?= htmlspecialchars($package['tracking_number']) ?>">
                                        <input type="hidden" name="action" value="deliver">
                                        <input type="hidden" name="item_type" value="package">
                                        <button type="submit" class="bg-[#004B87] text-white px-3 py-1 rounded text-sm w-full">
                                            <i class="fas fa-check mr-1"></i> Mark Delivered
                                        </button>
                                    </form>
                                    <a href="../package_details.php?tracking=<?= htmlspecialchars($package['tracking_number']) ?>" 
                                       class="bg-gray-200 text-gray-800 px-3 py-1 rounded text-sm text-center">
                                        <i class="fas fa-info-circle mr-1"></i> View Details
                                    </a>
                                    <?php else: ?>
                                    <form method="post" action="" class="inline">
                                        <input type="hidden" name="tracking_number" value="<?= htmlspecialchars($package['tracking_number']) ?>">
                                        <input type="hidden" name="transaction_id" value="<?= htmlspecialchars($package['shop_transaction_id']) ?>">
                                        <input type="hidden" name="action" value="deliver">
                                        <input type="hidden" name="item_type" value="shop">
                                        <button type="submit" class="bg-[#004B87] text-white px-3 py-1 rounded text-sm w-full">
                                            <i class="fas fa-check mr-1"></i> Mark as Picked Up
                                        </button>
                                    </form>
                                    <a href="../shop/transaction_details.php?id=<?= htmlspecialchars($package['shop_transaction_id']) ?>" 
                                       class="bg-gray-200 text-gray-800 px-3 py-1 rounded text-sm text-center mt-2">
                                        <i class="fas fa-info-circle mr-1"></i> View Details
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="6" class="p-3 text-center text-gray-500">
                                No packages found matching your criteria
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($packages !== null && $packages->num_rows > 0): ?>
            <div class="mt-6 text-center">
                <a href="../employee_dashboard.php" class="action-btn text-white px-4 py-2 rounded inline-block">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Dashboard
                </a>
            </div>
            <?php else: ?>
            <div class="mt-6 text-center">
                <div class="text-gray-500 mb-4">No packages are currently awaiting pickup at your facility.</div>
                <a href="../employee_dashboard.php" class="action-btn text-white px-4 py-2 rounded inline-block">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Dashboard
                </a>
            </div>
            <?php endif; ?>
        </div>
        
        <footer class="text-center mt-10 py-6 bg-[#004B87] text-white rounded-lg">
            <p>&copy; <?= date('Y') ?> Postal Service Management System</p>
        </footer>
    </div>
</body>
</html> 