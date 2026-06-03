<?php
require_once '../db_connect.php';
require_once '../functions.php';
session_start();

// Check login status
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$name = $_SESSION['name'];

// Get employee info
$stmt = $conn->prepare("SELECT e.*, f.city, f.type 
                      FROM Employee e 
                      JOIN Facility f ON e.facility_id = f.facility_id 
                      WHERE e.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$employee = $stmt->get_result()->fetch_assoc();

// Check for unread admin messages for non-admin users
$unread_admin_messages = 0;
if ($role != 'Admin') {
    $msg_stmt = $conn->prepare("SELECT COUNT(*) as count FROM Admin_Messages WHERE sender_id = ? AND status = 'Replied'");
    $msg_stmt->bind_param("i", $user_id);
    $msg_stmt->execute();
    $unread_admin_messages = $msg_stmt->get_result()->fetch_assoc()['count'] ?? 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Postal Service Reports</title>
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
                <h1 class="text-3xl font-bold section-title">Postal Service Reports</h1>
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
                <li><a href="index.php" class="text-gray-700 hover:text-[#DA291C] border-b-2 border-[#DA291C] pb-1 font-medium">Reports</a></li>
                <li><a href="../trips/manage_trips.php" class="text-gray-700 hover:text-[#DA291C]">Manage Trips</a></li>
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
        
        <section class="bg-white p-6 rounded-lg shadow-md mb-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <div class="bg-gray-50 rounded-lg shadow-md p-6 h-full">
                        <div class="flex flex-col items-center">
                            <div class="text-4xl text-blue-500 mb-4">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <h5 class="text-l font-semibold mb-2">Package Volume and Distribution</h5>
                            <p class="text-gray-600 text-center mb-4">Analyze package flow patterns, busiest routes, and distribution by size/weight.</p>
                            <a href="package_volume_report.php" class="action-btn text-white px-4 py-2 rounded mt-auto">View Report</a>
                        </div>
                    </div>
                </div>
            
                <div>
                    <div class="bg-gray-50 rounded-lg shadow-md p-6 h-full">
                        <div class="flex flex-col items-center">
                            <div class="text-4xl text-green-500 mb-4">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <h5 class="text-l font-semibold mb-2">Revenue Analysis</h5>
                            <p class="text-gray-600 text-center mb-4">Revenue by service type, facility, and time period.</p>
                            <a href="sales_report.php" class="action-btn text-white px-4 py-2 rounded mt-auto">View Report</a>
                        </div>
                    </div>
                </div>
            
                <div>
                    <div class="bg-gray-50 rounded-lg shadow-md p-6 h-full">
                        <div class="flex flex-col items-center">
                            <div class="text-4xl text-purple-500 mb-4">
                                <i class="fas fa-route"></i>
                            </div>
                            <h5 class="text-l font-semibold mb-2">Delivery Route Performance</h5>
                            <p class="text-gray-600 text-center mb-4">Analyze delivery route efficiency, package delivery status, and driver performance.</p>
                            <a href="route_report.php" class="action-btn text-white px-4 py-2 rounded mt-auto">View Report</a>
                        </div>
                    </div>
                </div>
            </div>

        </section>
        
        
        

        
        <footer class="text-center mt-10 py-6 bg-[#004B87] text-white rounded-lg">
            <p>&copy; <?= date('Y') ?> Postal Service Management System</p>
        </footer>
    </div>
</body>
</html>
