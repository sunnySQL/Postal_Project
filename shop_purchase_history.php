<?php
// Version: 2.0 - Image references removed
session_start();
require_once 'db_connect.php';
require_once 'functions.php';
//debug otpions
// Allow Customer, Employee, and Admin roles to access this page
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || 
    ($_SESSION['role'] != 'Customer' && $_SESSION['role'] != 'Employee' && $_SESSION['role'] != 'Admin')) {
    header("Location: login.php");
    exit();
}

// Check if user is a customer or employee
$is_employee = ($_SESSION['role'] == 'Employee' || $_SESSION['role'] == 'Admin');

// For customers, only show their own transactions
if (!$is_employee) {
    $user_id = $_SESSION['user_id'];
    $name = $_SESSION['name'];
} else {
    // For employees, they can view any customer's transactions
    // Default to showing all if no specific user_id is provided
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
    $name = isset($_GET['name']) ? $_GET['name'] : "All Customers";
}

// Get all transactions for this customer (if user_id is set) or all transactions if employee
$transactions = null;
if ($is_employee && $user_id === null) {
    // Employee viewing all transactions
    $stmt = $conn->prepare("SELECT t.*, s.shop_name, f.city, CONCAT(c.first_name, ' ', c.last_name) as customer_name
                          FROM Shop_Transaction t
                          JOIN Shop s ON t.shop_id = s.shop_id
                          JOIN Facility f ON s.facility_id = f.facility_id
                          LEFT JOIN Customer c ON t.user_id = c.user_id
                          ORDER BY t.transaction_date DESC");
    $stmt->execute();
    $transactions = $stmt->get_result();
} else {
    // Customer viewing their own transactions or employee viewing specific customer
    $stmt = $conn->prepare("SELECT t.*, s.shop_name, f.city
                          FROM Shop_Transaction t
                          JOIN Shop s ON t.shop_id = s.shop_id
                          JOIN Facility f ON s.facility_id = f.facility_id
                          WHERE " . ($user_id ? "t.user_id = ?" : "1=1") . "
                          ORDER BY t.transaction_date DESC");
    if ($user_id) {
        $stmt->bind_param("i", $user_id);
    }
    $stmt->execute();
    $transactions = $stmt->get_result();
}

// Specific transaction details if requested
$transaction_details = null;
$transaction_items = null;
if (isset($_GET['tx_id'])) {
    $tx_id = intval($_GET['tx_id']);
    
    // Get transaction details - employees can view any transaction, customers only their own
    if ($is_employee) {
        $stmt = $conn->prepare("SELECT t.*, s.shop_name, f.city, CONCAT(c.first_name, ' ', c.last_name) as customer_name
                             FROM Shop_Transaction t
                             JOIN Shop s ON t.shop_id = s.shop_id
                             JOIN Facility f ON s.facility_id = f.facility_id
                             LEFT JOIN Customer c ON t.user_id = c.user_id
                             WHERE t.transaction_id = ?");
        $stmt->bind_param("i", $tx_id);
    } else {
        $stmt = $conn->prepare("SELECT t.*, s.shop_name, f.city
                             FROM Shop_Transaction t
                             JOIN Shop s ON t.shop_id = s.shop_id
                             JOIN Facility f ON s.facility_id = f.facility_id
                             WHERE t.transaction_id = ? AND t.user_id = ?");
        $stmt->bind_param("ii", $tx_id, $user_id);
    }
    
    $stmt->execute();
    $transaction_details = $stmt->get_result()->fetch_assoc();
    
    if ($transaction_details) {
        // Get transaction items
        $stmt = $conn->prepare("SELECT ss.*, i.name, i.description
                              FROM Shop_Sale ss
                              JOIN Items i ON ss.item_id = i.item_id
                              WHERE ss.transaction_id = ?");
        $stmt->bind_param("i", $tx_id);
        $stmt->execute();
        $transaction_items = $stmt->get_result();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase History - Postal Shop</title>
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
    </style>
</head>
<body class="bg-gray-50">
    <nav class="top-nav fixed w-full top-0 left-0 z-50 shadow-md px-4 py-4">
        <div class="container mx-auto flex justify-between items-center">
            <a href="index.php" class="text-white font-bold text-2xl">POSTAL PRO</a>
            <ul class="flex space-x-6">
                <li><a href="index.php" class="text-white hover:text-gray-200">Home</a></li>
                <?php if ($is_employee): ?>
                    <li><a href="employee_dashboard.php" class="text-white hover:text-gray-200">Dashboard</a></li>
                    <li><a href="shop/shop_dashboard.php" class="text-white hover:text-gray-200">Shop</a></li>
                <?php else: ?>
                    <li><a href="customer_dashboard.php" class="text-white hover:text-gray-200">Dashboard</a></li>
                    <li><a href="shop.php" class="text-white hover:text-gray-200">Shop</a></li>
                <?php endif; ?>
                <li><a href="logout.php" class="text-white hover:text-gray-200">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8 max-w-6xl mt-20">
        <header class="mb-6">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center">
                <h1 class="text-3xl font-bold section-title">Purchase History</h1>
                <div class="mt-4 md:mt-0 bg-white shadow-sm p-4 rounded-lg">
                    <p><span class="font-semibold">Customer:</span> <?= htmlspecialchars($name) ?></p>
                    <?php if ($is_employee): ?>
                        <p class="text-sm text-gray-500">Viewing as Employee</p>
                    <?php endif; ?>
                </div>
            </div>
        </header>
        
        <nav class="bg-white shadow-sm p-4 rounded-lg mb-6">
            <ul class="flex flex-wrap space-x-2 md:space-x-6">
                <?php if ($is_employee): ?>
                    <li><a href="employee_dashboard.php" class="text-gray-700 hover:text-[#DA291C]">Dashboard</a></li>
                    <li><a href="shop/shop_dashboard.php" class="text-gray-700 hover:text-[#DA291C]">Shop Overview</a></li>
                    <li><a href="shop/transaction_list.php" class="text-gray-700 hover:text-[#DA291C]">Transaction List</a></li>
                <?php else: ?>
                    <li><a href="customer_dashboard.php" class="text-gray-700 hover:text-[#DA291C]">Dashboard</a></li>
                    <li><a href="shop.php" class="text-gray-700 hover:text-[#DA291C]">Shop</a></li>
                <?php endif; ?>
            </ul>
        </nav>
        
        <main>
            <?php if (isset($transaction_details)): ?>
                <div class="bg-white p-6 rounded-lg shadow-sm mb-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-2xl font-semibold text-[#004B87]">Order #<?= $transaction_details['transaction_id'] ?></h2>
                        <a href="shop_purchase_history.php" class="text-gray-600 hover:text-[#DA291C]">
                            <i class="fas fa-arrow-left mr-1"></i> Back to All Orders
                        </a>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <h3 class="text-lg font-semibold mb-2">Order Details</h3>
                            <div class="bg-gray-50 p-4 rounded">
                                <?php if ($is_employee && isset($transaction_details['customer_name'])): ?>
                                    <p><span class="font-medium">Customer:</span> <?= htmlspecialchars($transaction_details['customer_name']) ?></p>
                                <?php endif; ?>
                                <p><span class="font-medium">Date:</span> <?= date('F d, Y g:i A', strtotime($transaction_details['transaction_date'])) ?></p>
                                <p><span class="font-medium">Total Amount:</span> $<?= number_format($transaction_details['total_amount'], 2) ?></p>
                                <p><span class="font-medium">Payment Method:</span> <?= htmlspecialchars($transaction_details['payment_method']) ?></p>
                                <p><span class="font-medium">Status:</span> 
                                    <span class="<?= $transaction_details['transaction_status'] === 'Completed' ? 'text-green-600' : 'text-yellow-600' ?>">
                                        <?= htmlspecialchars($transaction_details['transaction_status']) ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                        
                        <div>
                            <h3 class="text-lg font-semibold mb-2">Shop Information</h3>
                            <div class="bg-gray-50 p-4 rounded">
                                <p><span class="font-medium">Shop Name:</span> <?= htmlspecialchars($transaction_details['shop_name']) ?></p>
                                <p><span class="font-medium">Location:</span> <?= htmlspecialchars($transaction_details['city']) ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <h3 class="text-lg font-semibold mb-2">Items Purchased</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-gray-100 text-left">
                                    <th class="px-4 py-2">Item</th>
                                    <th class="px-4 py-2">Price</th>
                                    <th class="px-4 py-2">Quantity</th>
                                    <th class="px-4 py-2">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($transaction_items && $transaction_items->num_rows > 0): ?>
                                    <?php while ($item = $transaction_items->fetch_assoc()): ?>
                                        <tr class="border-b border-gray-200 hover:bg-gray-50">
                                            <td class="px-4 py-2">
                                                <div class="font-medium"><?= htmlspecialchars($item['name']) ?></div>
                                            </td>
                                            <td class="px-4 py-2">$<?= number_format($item['sale_amount'] / $item['quantity'], 2) ?></td>
                                            <td class="px-4 py-2"><?= $item['quantity'] ?></td>
                                            <td class="px-4 py-2 font-medium">$<?= number_format($item['sale_amount'], 2) ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                    <tr class="bg-gray-50 font-semibold">
                                        <td class="px-4 py-3" colspan="3" align="right">Total:</td>
                                        <td class="px-4 py-3">$<?= number_format($transaction_details['total_amount'], 2) ?></td>
                                    </tr>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="px-4 py-3 text-center text-gray-500">No items found for this order.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    

                </div>
            <?php else: ?>
                <div class="bg-white p-6 rounded-lg shadow-sm">
                    <h2 class="text-2xl font-semibold mb-4 text-[#004B87]">
                        <?= $is_employee ? 'Transaction History' : 'Your Purchase History' ?>
                    </h2>
                    
                    <?php if ($transactions && $transactions->num_rows > 0): ?>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="bg-gray-100 text-left">
                                        <th class="px-4 py-2">Order #</th>
                                        <th class="px-4 py-2">Date</th>
                                        <?php if ($is_employee && $user_id === null): ?>
                                            <th class="px-4 py-2">Customer</th>
                                        <?php endif; ?>
                                        <th class="px-4 py-2">Shop</th>
                                        <th class="px-4 py-2">Amount</th>
                                        <th class="px-4 py-2">Status</th>
                                        <th class="px-4 py-2">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($tx = $transactions->fetch_assoc()): ?>
                                        <tr class="border-b border-gray-200 hover:bg-gray-50">
                                            <td class="px-4 py-2">#<?= $tx['transaction_id'] ?></td>
                                            <td class="px-4 py-2"><?= date('M d, Y', strtotime($tx['transaction_date'])) ?></td>
                                            <?php if ($is_employee && $user_id === null): ?>
                                                <td class="px-4 py-2"><?= isset($tx['customer_name']) ? htmlspecialchars($tx['customer_name']) : 'Guest' ?></td>
                                            <?php endif; ?>
                                            <td class="px-4 py-2"><?= htmlspecialchars($tx['shop_name']) ?> (<?= htmlspecialchars($tx['city']) ?>)</td>
                                            <td class="px-4 py-2 font-medium">$<?= number_format($tx['total_amount'], 2) ?></td>
                                            <td class="px-4 py-2">
                                                <span class="<?= $tx['transaction_status'] === 'Completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?> px-2 py-1 rounded-full text-xs">
                                                    <?= htmlspecialchars($tx['transaction_status']) ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-2">
                                                <a href="shop_purchase_history.php?tx_id=<?= $tx['transaction_id'] ?>" 
                                                   class="text-[#004B87] hover:text-[#DA291C] mr-2">
                                                     View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-10">
                            <i class="fas fa-shopping-bag text-gray-300 text-5xl mb-4"></i>
                            <p class="text-gray-500 mb-4">No transactions found.</p>
                            <?php if (!$is_employee): ?>
                                <a href="shop.php" class="action-btn text-white px-4 py-2 rounded inline-block">
                                    Go to Shop
                                </a>
                            <?php else: ?>
                                <a href="shop/process_sale.php" class="action-btn text-white px-4 py-2 rounded inline-block">
                                    Process a Sale
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
        
        <footer class="text-center mt-10 py-6 bg-[#004B87] text-white rounded-lg">
            <p>&copy; <?= date('Y') ?> Postal Service Management System</p>
        </footer>
    </div>
</body>
</html> 