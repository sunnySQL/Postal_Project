<?php
session_start();
require_once '../db_connect.php';
require_once '../functions.php';
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || 
    ($_SESSION['role'] != 'Employee' && $_SESSION['role'] != 'Admin')) {
    header("Location: ../login.php");
    exit();
}

// Handle success messages from session
$success_message = null;
if (isset($_SESSION['message']) && isset($_SESSION['message_type']) && $_SESSION['message_type'] === 'success') {
    $success_message = $_SESSION['message'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Get employee information
$user_id = $_SESSION['user_id'];
$top_selling_items = null;

try {
    $stmt = $conn->prepare("SELECT e.*, f.city, f.type, f.facility_id 
                          FROM Employee e 
                          JOIN Facility f ON e.facility_id = f.facility_id
                          WHERE e.user_id = ?");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $user_id);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    if ($result->num_rows == 0) {
        throw new Exception("Employee record not found");
    }
    
    $employee = $result->fetch_assoc();
    $facility_id = $employee['facility_id'];
    $role = $_SESSION['role'] ?? 'Employee';
    if ($conn->query("SHOW TABLES LIKE 'Shop_Sale'")->num_rows > 0
        && $conn->query("SHOW TABLES LIKE 'Shop_Transaction'")->num_rows > 0) {
        $top_q = $conn->prepare(
            "SELECT it.name, SUM(ss.quantity) AS total_sold, SUM(ss.sale_amount) AS total_revenue
             FROM Shop_Sale ss
             JOIN Items it ON ss.item_id = it.item_id
             JOIN Shop s ON ss.shop_id = s.shop_id
             JOIN Shop_Transaction st ON ss.transaction_id = st.transaction_id
             WHERE s.facility_id = ?
             AND st.transaction_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             AND (st.transaction_status = 'Completed' OR st.transaction_status = 'Picked Up' OR st.transaction_status IS NULL OR st.transaction_status = '')
             GROUP BY it.item_id, it.name
             ORDER BY total_sold DESC
             LIMIT 5"
        );
        if ($top_q) {
            $top_q->bind_param("i", $facility_id);
            if ($top_q->execute()) {
                $top_selling_items = $top_q->get_result();
            }
        }
    }
    $name = ($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? '');
    
    // Unread admin messages for nav badge (non-Admin)
    $unread_admin_messages = 0;
    if ($role != 'Admin') {
        $msg_stmt = $conn->prepare("SELECT COUNT(*) as count FROM Admin_Messages WHERE sender_id = ? AND status = 'Replied'");
        $msg_stmt->bind_param("i", $user_id);
        $msg_stmt->execute();
        $unread_admin_messages = $msg_stmt->get_result()->fetch_assoc()['count'] ?? 0;
    }
    
    // Get shop info
    $stmt = $conn->prepare("SELECT * FROM Shop WHERE facility_id = ?");
    if (!$stmt) {
        throw new Exception("Prepare failed for shop query: " . $conn->error);
    }
    
    $stmt->bind_param("i", $facility_id);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed for shop query: " . $stmt->error);
    }
    
    $shop_result = $stmt->get_result();
    $has_shop = $shop_result->num_rows > 0;
    $recent_sales = null;
    $low_stock_items = null;
    
    if ($has_shop) {
        $shop = $shop_result->fetch_assoc();
        $shop_id = $shop['shop_id'];
        $shop_sale_table_exists = $conn->query("SHOW TABLES LIKE 'Shop_Transaction'")->num_rows > 0;
        
        if ($shop_sale_table_exists) {
            try {
                $sales_query = $conn->prepare("SELECT t.transaction_id, t.total_amount, t.payment_method, t.transaction_status, 
                                         t.transaction_id as invoice_number, DATE(t.transaction_date) as sale_date,
                                         CONCAT(c.first_name, ' ', c.last_name) as customer_name
                                         FROM Shop_Transaction t
                                         LEFT JOIN Customer c ON t.user_id = c.user_id
                                         WHERE t.shop_id = ?
                                         ORDER BY t.transaction_id DESC LIMIT 10");
                if (!$sales_query) {
                    throw new Exception("Prepare failed for sales query: " . $conn->error);
                }
                
                $sales_query->bind_param("i", $shop_id);
                if (!$sales_query->execute()) {
                    throw new Exception("Execute failed for sales query: " . $sales_query->error);
                }
                
                $recent_sales = $sales_query->get_result();
            } catch (Exception $e) {
                error_log("Sales query error: " . $e->getMessage());
            }
        }
        $items_table_exists = $conn->query("SHOW TABLES LIKE 'Items'")->num_rows > 0;
        $inventory_table_exists = $conn->query("SHOW TABLES LIKE 'Inventory'")->num_rows > 0;
        
        if ($items_table_exists && $inventory_table_exists) {
            // Get low stock items
            try {
                $low_stock_query = $conn->prepare("SELECT i.item_id, i.name, inv.quantity, inv.min_stock_level 
                                             FROM Inventory inv
                                             JOIN Items i ON inv.item_id = i.item_id
                                             WHERE inv.shop_id = ? AND inv.is_active = 1 AND inv.quantity <= inv.min_stock_level
                                             ORDER BY (inv.quantity - inv.min_stock_level) ASC");
                if (!$low_stock_query) {
                    throw new Exception("Prepare failed for inventory query: " . $conn->error);
                }
                
                $low_stock_query->bind_param("i", $shop_id);
                if (!$low_stock_query->execute()) {
                    throw new Exception("Execute failed for inventory query: " . $low_stock_query->error);
                }
                
                $low_stock_items = $low_stock_query->get_result();
            } catch (Exception $e) {
                // Log error but continue
                error_log("Inventory query error: " . $e->getMessage());
            }
        }
    }
} catch (Exception $e) {
    $error_message = "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop Overview</title>
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
                <h1 class="text-3xl font-bold section-title">Shop Overview</h1>
                <div class="mt-4 md:mt-0 bg-white shadow-sm p-4 rounded-lg">
                    <p class="mb-1"><span class="font-semibold">Welcome,</span> <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?> | <span class="font-medium"><?= htmlspecialchars($employee['role']) ?></span></p>
                    <p><span class="font-semibold">Facility:</span> <?= htmlspecialchars($employee['type']) ?> - <?= htmlspecialchars($employee['city']) ?></p>
                </div>
            </div>
        </header>
        
        <nav class="bg-white shadow-sm p-4 rounded-lg mb-6">
            <ul class="flex flex-wrap space-x-2 md:space-x-6">
                <?php if ($role == 'Admin'): ?>
                <li><a href="../admin/manage_users.php" class="text-gray-700 hover:text-[#DA291C]">Manage Users</a></li>
                <li><a href="../admin/manage_facilities.php" class="text-gray-700 hover:text-[#DA291C]">Manage Facilities</a></li>
                <li><a href="inventory_management.php" class="text-gray-700 hover:text-[#DA291C]">Inventory Management</a></li>
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
                <li><a href="shop_dashboard.php" class="text-gray-700 hover:text-[#DA291C] border-b-2 border-[#DA291C] pb-0.5 font-medium">Shop Overview</a></li>
                <li><a href="../package/search.php" class="text-gray-700 hover:text-[#DA291C]">Package Search &amp; Scan</a></li>
                <?php endif; ?>
                <?php if ($employee['role'] == 'Manager'): ?>
                <li><a href="shop_dashboard.php" class="text-gray-700 hover:text-[#DA291C] border-b-2 border-[#DA291C] pb-0.5 font-medium">Shop Overview</a></li>
                <li><a href="../reports/index.php" class="text-gray-700 hover:text-[#DA291C]">Reports</a></li>
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

        <nav class="bg-gray-100 border border-gray-200 rounded-lg px-4 py-3 mb-6">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Shop &amp; Inventory</p>
            <ul class="flex flex-wrap gap-x-4 gap-y-1">
                <li><a href="process_sale.php" class="text-gray-700 hover:text-[#DA291C]">Process Sale</a></li>
                <li><a href="transaction_list.php" class="text-gray-700 hover:text-[#DA291C]">Transaction List</a></li>
                <li><a href="inventory_management.php" class="text-gray-700 hover:text-[#DA291C]">Inventory Management</a></li>
            </ul>
        </nav>
        

        <?php if (isset($success_message)): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
            <?= htmlspecialchars($success_message) ?>
        </div>
        <?php endif; ?>

        <main>
            <?php if (isset($error_message)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                    <p><strong>Error:</strong> <?= htmlspecialchars($error_message) ?></p>
                    <p>Please contact IT support with the above error message.</p>
                </div>
            <?php endif; ?>
            
            <?php if (!$has_shop): ?>
                <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6">
                    <p>There is no shop configured for your facility. Please contact an administrator.</p>
                </div>
            <?php else: ?>
                <!-- Low Stock Items Section -->
                <section class="bg-white shadow-sm p-6 rounded-lg mb-6">
                    <h2 class="text-2xl font-semibold mb-4 section-title">Low Stock Items</h2>
                    <?php if (isset($low_stock_items) && $low_stock_items && $low_stock_items->num_rows > 0): ?>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="bg-gray-200 text-left">
                                        <th class="px-4 py-2">Item</th>
                                        <th class="px-4 py-2">Current Stock</th>
                                        <th class="px-4 py-2">Min Level</th>
                                        <th class="px-4 py-2">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($item = $low_stock_items->fetch_assoc()): ?>
                                        <tr class="border-b border-gray-200 hover:bg-gray-50">
                                            <td class="px-4 py-2"><?= htmlspecialchars($item['name']) ?></td>
                                            <td class="px-4 py-2"><?= $item['quantity'] ?></td>
                                            <td class="px-4 py-2"><?= $item['min_stock_level'] ?></td>
                                            <td class="px-4 py-2">
                                                <?php if ($item['quantity'] == 0): ?>
                                                    <span class="bg-red-100 text-red-800 px-2 py-1 rounded-full text-xs">Out of Stock</span>
                                                <?php else: ?>
                                                    <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded-full text-xs">Low Stock</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if (isset($employee['role']) && $employee['role'] != 'Clerk'): ?>
                        <div class="text-center mt-4">
                            <a href="inventory_management.php" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                                Manage Inventory
                            </a>
                        </div>
                        <?php endif; ?>
                    <?php elseif (isset($low_stock_items) && $low_stock_items): ?>
                        <p class="text-gray-600">No low stock items at this time.</p>
                    <?php else: ?>
                        <p class="text-gray-600">Inventory system not available. Tables may not be set up yet.</p>
                    <?php endif; ?>
                </section>

                <!-- Top Selling Items (30 days) — same metric as manager dashboard -->
                <section class="bg-white shadow-sm p-6 rounded-lg mb-6">
                    <h2 class="text-2xl font-semibold mb-4 section-title">Top Selling Items (30 Days)</h2>
                    <?php if ($top_selling_items && $top_selling_items->num_rows > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-gray-200 text-left">
                                    <th class="px-4 py-2">Item</th>
                                    <th class="px-4 py-2">Quantity Sold</th>
                                    <th class="px-4 py-2">Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $top_selling_items->data_seek(0);
                                while ($row = $top_selling_items->fetch_assoc()): ?>
                                <tr class="border-b border-gray-200 hover:bg-gray-50">
                                    <td class="px-4 py-2"><?= htmlspecialchars($row['name']) ?></td>
                                    <td class="px-4 py-2"><?= (int) $row['total_sold'] ?></td>
                                    <td class="px-4 py-2">$<?= number_format((float) $row['total_revenue'], 2) ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php elseif ($top_selling_items): ?>
                    <p class="text-gray-600">No sales in the last 30 days for this facility.</p>
                    <?php else: ?>
                    <p class="text-gray-600">Sales line-item data is not available yet.</p>
                    <?php endif; ?>
                </section>
                
                <!-- Recent Sales Section -->
                <section class="bg-white shadow-sm p-6 rounded-lg mb-6">
                    <h2 class="text-2xl font-semibold mb-4 section-title">Recent Sales</h2>
                    <?php if (isset($recent_sales) && $recent_sales && $recent_sales->num_rows > 0): ?>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="bg-gray-200 text-left">
                                        <th class="px-4 py-2">Invoice #</th>
                                        <th class="px-4 py-2">Date</th>
                                        <th class="px-4 py-2">Customer</th>
                                        <th class="px-4 py-2">Amount</th>
                                        <th class="px-4 py-2">Payment Method</th>
                                        <th class="px-4 py-2">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($sale = $recent_sales->fetch_assoc()): ?>
                                        <tr class="border-b border-gray-200 hover:bg-gray-50">
                                            <td class="px-4 py-2"><?= $sale['invoice_number'] ?></td>
                                            <td class="px-4 py-2"><?= $sale['sale_date'] ?></td>
                                            <td class="px-4 py-2"><?= $sale['customer_name'] ?: 'Walk-in Customer' ?></td>
                                            <td class="px-4 py-2">$<?= number_format($sale['total_amount'], 2) ?></td>
                                            <td class="px-4 py-2"><?= $sale['payment_method'] ?></td>
                                            <td class="px-4 py-2">
                                                <span class="<?php
                                                    switch($sale['transaction_status']) {
                                                        case 'Completed': echo 'bg-green-100 text-green-800'; break;
                                                        case 'Pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                                        case 'Refunded': echo 'bg-red-100 text-red-800'; break;
                                                        default: echo 'bg-gray-100 text-gray-800';
                                                    }
                                                ?> px-2 py-1 rounded-full text-xs">
                                                    <?= htmlspecialchars($sale['transaction_status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center mt-4">
                            <a href="transaction_list.php" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                                View All Sales
                            </a>
                        </div>
                    <?php elseif (isset($recent_sales) && $recent_sales): ?>
                        <p class="text-gray-600">No recent sales.</p>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </main>
        
        <footer class="text-center mt-10 py-4 text-gray-600 border-t border-gray-300">
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
