<?php
session_start();
require_once 'db_connect.php';
require_once 'functions.php';

// Only allow customers to access this page
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'Customer') {
    header("Location: login.php");
    exit();
}

// Check if there's order information in the session
if (!isset($_SESSION['last_order'])) {
    header("Location: customer_order.php");
    exit();
}

$order = $_SESSION['last_order'];
$transaction_id = $order['transaction_id'];
$shop_id = $order['shop_id'];

// Get shop information
$stmt = $conn->prepare("SELECT s.shop_name, f.address, f.city, f.state, f.zip_code 
                       FROM Shop s 
                       JOIN Facility f ON s.facility_id = f.facility_id 
                       WHERE s.shop_id = ?");
$stmt->bind_param("i", $shop_id);
$stmt->execute();
$shop = $stmt->get_result()->fetch_assoc();

// Get items purchased
$stmt = $conn->prepare("SELECT i.name, s.quantity, s.sale_amount 
                       FROM Shop_Sale s 
                       JOIN Items i ON s.item_id = i.item_id 
                       WHERE s.transaction_id = ?");
$stmt->bind_param("i", $transaction_id);
$stmt->execute();
$items = $stmt->get_result();

// Get customer information
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT first_name, last_name, email FROM Customer WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation | Postal Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap');
        
        * {
            font-family: 'Open Sans', sans-serif;
        }
        
        .pickup-code {
            font-size: 32px;
            font-weight: bold;
            letter-spacing: 3px;
            color: #DA291C;
            text-align: center;
            margin: 10px 0;
        }
        
        .order-number {
            font-size: 18px;
            font-weight: semibold;
            letter-spacing: 1px;
            padding: 12px;
            background-color: #f2f7ff;
            border: 2px dashed #004B87;
            border-radius: 8px;
            text-align: center;
            margin: 20px 0;
        }
        
        .action-btn {
            background-color: #004B87;
            transition: background-color 0.3s;
        }
        
        .action-btn:hover {
            background-color: #003366;
        }
    </style>
</head>
<body class="bg-gray-50">
    <nav class="fixed w-full top-0 left-0 z-50 shadow-md px-4 py-4 bg-[#004B87] text-white">
        <div class="container mx-auto flex justify-between items-center">
            <a href="index.php" class="text-white font-bold text-2xl">POSTAL PRO</a>
            <ul class="flex space-x-6">
                <li><a href="customer_dashboard.php" class="text-white hover:text-gray-200">Dashboard</a></li>
                <li><a href="customer_order.php" class="text-white hover:text-gray-200">Shop</a></li>
                <li><a href="logout.php" class="text-white hover:text-gray-200">Logout</a></li>
            </ul>
        </div>
    </nav>
    
    <div class="container mx-auto px-4 py-8 max-w-4xl mt-20">
        <div class="bg-white shadow-sm rounded-lg p-8">
            <div class="text-center mb-8">
                <i class="fas fa-check-circle text-green-500 text-5xl mb-4"></i>
                <h1 class="text-3xl font-bold text-[#004B87]">Order Confirmed!</h1>
                <p class="text-gray-600 mt-2">Your order has been successfully placed and is ready for pickup</p>
            </div>
            
            <div class="bg-gray-50 p-6 rounded-lg border border-gray-200 mb-8">
                <h2 class="text-xl font-semibold mb-4 text-center">Your Pickup Code</h2>
                <div class="pickup-code"><?= htmlspecialchars($order['pickup_code']) ?></div>
                <p class="text-center text-gray-600">Present this code to the clerk when you arrive at the postal facility</p>
                
                <h3 class="text-lg font-semibold mt-6 mb-2 text-center">Order Number</h3>
                <div class="order-number"><?= htmlspecialchars($order['order_number']) ?></div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <div>
                    <h3 class="text-lg font-semibold mb-4 border-b pb-2">Order Details</h3>
                    <p><span class="text-gray-600">Order Date:</span> <?= date('F j, Y g:i A', strtotime($order['order_date'])) ?></p>
                    <p><span class="text-gray-600">Payment Method:</span> <?= htmlspecialchars($order['payment_method']) ?></p>
                    <p><span class="text-gray-600">Total Amount:</span> $<?= number_format($order['total'], 2) ?></p>
                    <p><span class="text-gray-600">Status:</span> <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded-full text-xs">Pending Pickup</span></p>
                </div>
                
                <div>
                    <h3 class="text-lg font-semibold mb-4 border-b pb-2">Pickup Location</h3>
                    <p><span class="font-medium"><?= htmlspecialchars($shop['shop_name']) ?></span></p>
                    <p><?= htmlspecialchars($shop['address']) ?></p>
                    <p><?= htmlspecialchars($shop['city']) ?>, <?= htmlspecialchars($shop['state']) ?> <?= htmlspecialchars($shop['zip_code']) ?></p>
                </div>
            </div>
            
            <div class="mb-8">
                <h3 class="text-lg font-semibold mb-4 border-b pb-2">Items Purchased</h3>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="py-2 px-4 text-left">Item</th>
                                <th class="py-2 px-4 text-center">Quantity</th>
                                <th class="py-2 px-4 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php while ($item = $items->fetch_assoc()): ?>
                                <tr>
                                    <td class="py-3 px-4"><?= htmlspecialchars($item['name']) ?></td>
                                    <td class="py-3 px-4 text-center"><?= $item['quantity'] ?></td>
                                    <td class="py-3 px-4 text-right">$<?= number_format($item['sale_amount'], 2) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                        <tfoot class="bg-gray-50 font-semibold">
                            <tr>
                                <td colspan="2" class="py-3 px-4 text-right">Total:</td>
                                <td class="py-3 px-4 text-right">$<?= number_format($order['total'], 2) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            
            <div class="flex justify-center gap-4">
                <button onclick="window.print()" class="action-btn text-white px-6 py-2 rounded flex items-center">
                    <i class="fas fa-print mr-2"></i> Print Receipt
                </button>
                <a href="customer_dashboard.php" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded flex items-center">
                    <i class="fas fa-home mr-2"></i> Dashboard
                </a>
            </div>
        </div>
    </div>
    
    <footer class="text-center mt-10 py-4 text-gray-600 border-t border-gray-300">
        <p>&copy; <?= date('Y') ?> Postal Service Management System</p>
    </footer>
</body>
</html> 