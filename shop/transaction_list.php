<?php
session_start();
require_once '../db_connect.php';
require_once '../functions.php';

// Enable error reporting for debugging
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || 
    ($_SESSION['role'] != 'Employee' && $_SESSION['role'] != 'Admin')) {
    header("Location: ../login.php");
    exit();
}

// Get employee information
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'Employee';
$stmt = $conn->prepare("SELECT e.*, f.city, f.type, f.facility_id 
                      FROM Employee e 
                      JOIN Facility f ON e.facility_id = f.facility_id
                      WHERE e.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$employee = $stmt->get_result()->fetch_assoc();
$facility_id = $employee['facility_id'];
$name = ($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? '');

$unread_admin_messages = 0;
if ($role != 'Admin') {
    $msg_stmt = $conn->prepare("SELECT COUNT(*) as count FROM Admin_Messages WHERE sender_id = ? AND status = 'Replied'");
    $msg_stmt->bind_param("i", $user_id);
    $msg_stmt->execute();
    $unread_admin_messages = $msg_stmt->get_result()->fetch_assoc()['count'] ?? 0;
}

// Get shop info
$stmt = $conn->prepare("SELECT * FROM Shop WHERE facility_id = ?");
$stmt->bind_param("i", $facility_id);
$stmt->execute();
$shop_result = $stmt->get_result();
$has_shop = $shop_result->num_rows > 0;

if (!$has_shop) {
    $_SESSION['message'] = "There is no shop configured for your facility.";
    $_SESSION['message_type'] = "error";
    header("Location: shop_dashboard.php");
    exit();
}

$shop = $shop_result->fetch_assoc();
$shop_id = $shop['shop_id'];

// Set default filter values
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$payment_method = isset($_GET['payment_method']) ? $_GET['payment_method'] : '';
$customer_search = isset($_GET['customer_search']) ? $_GET['customer_search'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

// Base query
$query = "SELECT t.*, CONCAT(c.first_name, ' ', c.last_name) as customer_name 
         FROM Shop_Transaction t 
         LEFT JOIN Customer c ON t.user_id = c.user_id 
         WHERE t.shop_id = ?";
$params = [$shop_id];
$types = "i";

// Add filters
if (!empty($start_date)) {
    $query .= " AND DATE(t.transaction_date) >= ?";
    $params[] = $start_date;
    $types .= "s";
}

if (!empty($end_date)) {
    $query .= " AND DATE(t.transaction_date) <= ?";
    $params[] = $end_date;
    $types .= "s";
}

if (!empty($payment_method)) {
    $query .= " AND t.payment_method = ?";
    $params[] = $payment_method;
    $types .= "s";
}

if (!empty($status)) {
    $query .= " AND t.transaction_status = ?";
    $params[] = $status;
    $types .= "s";
}

if (!empty($customer_search)) {
    $query .= " AND (c.first_name LIKE ? OR c.last_name LIKE ? OR CONCAT(c.first_name, ' ', c.last_name) LIKE ?)";
    $search_term = "%$customer_search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "sss";
}

// Add sorting
$query .= " ORDER BY t.transaction_date DESC";

// Prepare and execute the query
$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $transactions = $stmt->get_result();
} else {
    $error_message = "Database error: " . $conn->error;
}

// Get payment methods for filter dropdown
$payment_methods = [];
$payment_query = $conn->query("SELECT DISTINCT payment_method FROM Shop_Transaction WHERE shop_id = $shop_id");
if ($payment_query) {
    while ($row = $payment_query->fetch_assoc()) {
        $payment_methods[] = $row['payment_method'];
    }
}

// Get statuses for filter dropdown
$statuses = [];
$status_query = $conn->query("SELECT DISTINCT transaction_status FROM Shop_Transaction WHERE shop_id = $shop_id");
if ($status_query) {
    while ($row = $status_query->fetch_assoc()) {
        $statuses[] = $row['transaction_status'];
    }
}

// Get summary statistics
$stats = [
    'total_transactions' => 0,
    'total_revenue' => 0,
    'avg_transaction' => 0
];

$stats_query = $conn->prepare("SELECT 
                             COUNT(*) as total_transactions,
                             SUM(total_amount) as total_revenue,
                             AVG(total_amount) as avg_transaction
                           FROM Shop_Transaction 
                           WHERE shop_id = ? 
                           AND DATE(transaction_date) BETWEEN ? AND ?");
                           
if ($stats_query) {
    $stats_query->bind_param("iss", $shop_id, $start_date, $end_date);
    $stats_query->execute();
    $stats_result = $stats_query->get_result()->fetch_assoc();
    
    if ($stats_result) {
        $stats = $stats_result;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction List - POSTAL PRO</title>
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
                <h1 class="text-3xl font-bold section-title">Transaction List</h1>
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
                <li><a href="inventory_management.php" class="text-gray-700 hover:text-[#DA291C]">Inventory Management</a></li>
                <li><a href="../package/search.php" class="text-gray-700 hover:text-[#DA291C]">Package Search &amp; Scan</a></li>
                <?php endif; ?>
                <?php if ($employee['role'] == 'Manager'): ?>
                <li><a href="shop_dashboard.php" class="text-gray-700 hover:text-[#DA291C]">Shop Overview</a></li>
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
                <li><a href="transaction_list.php" class="text-gray-700 hover:text-[#DA291C] border-b-2 border-[#DA291C] pb-0.5 font-medium">Transaction List</a></li>
                <li><a href="inventory_management.php" class="text-gray-700 hover:text-[#DA291C]">Inventory Management</a></li>
            </ul>
        </nav>

        <?php if (isset($error_message)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                <p><?= htmlspecialchars($error_message) ?></p>
            </div>
        <?php endif; ?>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white p-5 rounded-lg shadow-md">
                <h3 class="text-lg font-semibold mb-2 text-gray-700">Total Transactions</h3>
                <p class="text-3xl font-bold"><?= number_format($stats['total_transactions']) ?></p>
                <p class="text-sm text-gray-500">For selected period</p>
            </div>
            <div class="bg-white p-5 rounded-lg shadow-md">
                <h3 class="text-lg font-semibold mb-2 text-gray-700">Total Revenue</h3>
                <p class="text-3xl font-bold">$<?= number_format($stats['total_revenue'], 2) ?></p>
                <p class="text-sm text-gray-500">For selected period</p>
            </div>
            <div class="bg-white p-5 rounded-lg shadow-md">
                <h3 class="text-lg font-semibold mb-2 text-gray-700">Average Transaction</h3>
                <p class="text-3xl font-bold">$<?= number_format($stats['avg_transaction'], 2) ?></p>
                <p class="text-sm text-gray-500">For selected period</p>
            </div>
        </div>

        <!-- Search and Filter Section -->
        <div class="bg-white shadow-sm p-6 rounded-lg mb-6">
            <h2 class="text-xl font-semibold mb-4 section-title">Search & Filter Transactions</h2>
            
            <form action="transaction_list.php" method="get" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                        <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" 
                               class="w-full border border-gray-300 rounded px-3 py-2">
                    </div>
                    <div>
                        <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                        <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" 
                               class="w-full border border-gray-300 rounded px-3 py-2">
                    </div>
                    <div>
                        <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                        <select id="payment_method" name="payment_method" class="w-full border border-gray-300 rounded px-3 py-2">
                            <option value="">All Payment Methods</option>
                            <?php foreach ($payment_methods as $method): ?>
                                <option value="<?= htmlspecialchars($method) ?>" <?= $payment_method === $method ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($method) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select id="status" name="status" class="w-full border border-gray-300 rounded px-3 py-2">
                            <option value="">All Statuses</option>
                            <?php foreach ($statuses as $status_option): ?>
                                <option value="<?= htmlspecialchars($status_option) ?>" <?= $status === $status_option ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($status_option) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="flex flex-col md:flex-row md:items-end space-y-4 md:space-y-0 md:space-x-4">
                    <div class="flex-grow">
                        <label for="customer_search" class="block text-sm font-medium text-gray-700 mb-1">Customer Name</label>
                        <input type="text" id="customer_search" name="customer_search" value="<?= htmlspecialchars($customer_search) ?>" 
                               placeholder="Search by customer name" class="w-full border border-gray-300 rounded px-3 py-2">
                    </div>
                    <div class="flex space-x-2">
                        <button type="submit" class="action-btn text-white px-4 py-2 rounded">
                            <i class="fas fa-search mr-1"></i> Search
                        </button>
                        <a href="transaction_list.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">
                            <i class="fas fa-sync-alt mr-1"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Transaction List -->
        <div class="bg-white shadow-sm p-6 rounded-lg mb-6">
            <h2 class="text-xl font-semibold mb-4 section-title">Transactions</h2>
            
            <?php if (isset($transactions) && $transactions->num_rows > 0): ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-200 text-left">
                                <th class="px-4 py-2">ID</th>
                                <th class="px-4 py-2">Date</th>
                                <th class="px-4 py-2">Customer</th>
                                <th class="px-4 py-2">Amount</th>
                                <th class="px-4 py-2">Payment Method</th>
                                <th class="px-4 py-2">Status</th>
                                <th class="px-4 py-2">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($transaction = $transactions->fetch_assoc()): ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="px-4 py-2">
                                        <span class="font-medium">#<?= htmlspecialchars($transaction['transaction_id']) ?></span>
                                    </td>
                                    <td class="px-4 py-2"><?= date('M d, Y, g:i a', strtotime($transaction['transaction_date'])) ?></td>
                                    <td class="px-4 py-2"><?= htmlspecialchars($transaction['customer_name'] ?: 'Walk-in Customer') ?></td>
                                    <td class="px-4 py-2">$<?= number_format($transaction['total_amount'], 2) ?></td>
                                    <td class="px-4 py-2"><?= htmlspecialchars($transaction['payment_method']) ?></td>
                                    <td class="px-4 py-2">
                                        <span class="<?php
                                            switch($transaction['transaction_status']) {
                                                case 'Completed': echo 'bg-green-100 text-green-800'; break;
                                                case 'Pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                                case 'Refunded': echo 'bg-red-100 text-red-800'; break;
                                                default: echo 'bg-gray-100 text-gray-800';
                                            }
                                        ?> px-2 py-1 rounded-full text-xs">
                                            <?= htmlspecialchars($transaction['transaction_status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-2">
                                        <a href="../shop_purchase_history.php?tx_id=<?= $transaction['transaction_id'] ?>" 
                                           class="text-blue-600 hover:text-blue-800">
                                            <i class="fas fa-eye mr-1"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
            <?php else: ?>
                <div class="text-center py-8">
                    <i class="fas fa-receipt text-gray-300 text-6xl mb-4"></i>
                    <p class="text-gray-600">No transactions found matching your criteria.</p>
                    <p class="text-sm text-gray-500 mt-2">Try adjusting your search filters.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <footer class="text-center mt-10 py-6 bg-[#004B87] text-white rounded-lg">
            <p>&copy; <?= date('Y') ?> Postal Service Management System</p>
        </footer>
    </div>
    
    <script>
        // Set max date for date inputs to today
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('end_date').setAttribute('max', today);
        });
    </script>
</body>
</html> 