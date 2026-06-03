<?php
session_start();
require 'functions.php';
require_once 'db_connect.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Customer') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$cart = $_SESSION['cart'] ?? [];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($cart)) {
    header("Location: cart.php");
    exit();
}

// Collect form data
$first_name = $_POST['first_name'];
$last_name = $_POST['last_name'];
$full_name = $first_name . ' ' . $last_name;

$email = $_POST['email'];
$address = $_POST['address'];
$city = $_POST['city'];
$state = $_POST['state'];
$postalcode = $_POST['postal_code'];

// Build product list & total
$productsInCart = [];
$total = 0.00;

foreach ($cart as $productId => $qty) {
    $product = getProductById($productId);
    if ($product) {
        $product['quantity'] = $qty;
        $product['subtotal'] = $qty * $product['sale_price'];
        $productsInCart[] = $product;
        $total += $product['subtotal'];
    }
}

$order_number = 'ORD-' . strtoupper(substr(md5(uniqid()), 0, 8));
$pickup_code = strtoupper(substr(md5(time() . $user_id), 0, 6));

$shop_id = 1; // TODO: Replace with your actual shop ID or fetch based on facility/user logic

$orderSaved = false;
$errorMessages = [];

$tableResult = $conn->query("SHOW TABLES LIKE 'Order_Pickup'");
if ($tableResult->num_rows == 0) {
    error_log('invoice.php: Order_Pickup table does not exist');
    $errorMessages[] = 'Order could not be processed. Please contact support.';
}

if (empty($errorMessages)) {
    $conn->begin_transaction();

    try {
        $payment_method = 'Online';
        $total_amount = $total;
        $order_status = 'Pending Pickup';

        $stmt = $conn->prepare("INSERT INTO Shop_Transaction (shop_id, user_id, total_amount, payment_method, transaction_status, transaction_date) 
                              VALUES (?, ?, ?, ?, ?, NOW())");
        if (!$stmt) {
            throw new Exception("Prepare failed for Shop_Transaction: " . $conn->error);
        }

        $stmt->bind_param("iidss", $shop_id, $user_id, $total_amount, $payment_method, $order_status);

        if (!$stmt->execute()) {
            throw new Exception("Execute failed for Shop_Transaction: " . $stmt->error);
        }

        $transaction_id = $conn->insert_id;

        $stmt = $conn->prepare("INSERT INTO Order_Pickup (transaction_id, order_number, pickup_code) 
                              VALUES (?, ?, ?)");
        if (!$stmt) {
            throw new Exception("Prepare failed for Order_Pickup: " . $conn->error);
        }

        $stmt->bind_param("iss", $transaction_id, $order_number, $pickup_code);

        if (!$stmt->execute()) {
            throw new Exception("Execute failed for Order_Pickup: " . $stmt->error);
        }

        foreach ($productsInCart as $item) {
            $item_id = $item['item_id'];
            $quantity = $item['quantity'];
            $sale_amount = $item['subtotal'];

            $stmt = $conn->prepare("INSERT INTO Shop_Sale (shop_id, item_id, quantity, sale_amount, transaction_id) 
                                    VALUES (?, ?, ?, ?, ?)");
            if (!$stmt) {
                throw new Exception("Prepare failed for Shop_Sale: " . $conn->error);
            }

            $stmt->bind_param("iiidi", $shop_id, $item_id, $quantity, $sale_amount, $transaction_id);

            if (!$stmt->execute()) {
                throw new Exception("Execute failed for Shop_Sale: " . $stmt->error);
            }

            $stmt = $conn->prepare("UPDATE Inventory 
                                    SET quantity = quantity - ?, last_updated = CURRENT_TIMESTAMP 
                                    WHERE item_id = ? AND shop_id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed for Inventory update: " . $conn->error);
            }

            $stmt->bind_param("iii", $quantity, $item_id, $shop_id);

            if (!$stmt->execute()) {
                throw new Exception("Execute failed for Inventory update: " . $stmt->error);
            }
        }

        $stmt = $conn->prepare("UPDATE Shop SET sales = sales + ? WHERE shop_id = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed for Shop update: " . $conn->error);
        }

        $stmt->bind_param("di", $total, $shop_id);

        if (!$stmt->execute()) {
            throw new Exception("Execute failed for Shop update: " . $stmt->error);
        }

        $conn->commit();
        $orderSaved = true;

        $_SESSION['cart'] = [];
        $_SESSION['last_order'] = [
            'order_number' => $order_number,
            'pickup_code' => $pickup_code,
            'total' => $total,
            'date' => date('Y-m-d H:i:s')
        ];

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Shop sync failed: " . $e->getMessage());
        $errorMessages[] = !empty($app_config['debug'])
            ? "Database Error: " . $e->getMessage()
            : 'Order could not be processed. Please try again or contact support.';
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Order Invoice | Postal Pro</title>
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
        
        .order-number {
            font-size: 24px;
            font-weight: bold;
            letter-spacing: 1px;
            padding: 15px;
            background-color: #f2f7ff;
            border: 2px dashed #004B87;
            border-radius: 8px;
            text-align: center;
            margin: 20px 0;
        }
        
        .pickup-code {
            font-size: 32px;
            font-weight: bold;
            letter-spacing: 2px;
            color: #DA291C;
            text-align: center;
            margin: 10px 0;
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
    </style>
</head>
<body class="p-4">
    <div class="max-w-4xl mx-auto bg-white p-8 rounded-lg shadow-md">
        <div class="flex justify-between items-center border-b border-gray-200 pb-4 mb-6">
            <h1 class="text-3xl font-bold text-[#004B87]">Order Confirmation</h1>
            <a href="index.php" class="text-[#004B87] hover:text-[#DA291C]">
                <i class="fas fa-home mr-1"></i> Home
            </a>
        </div>

        <?php if (!empty($errorMessages)): ?>
        <div class="bg-red-100 text-red-700 p-4 rounded-lg mb-6">
            <i class="fas fa-exclamation-circle text-2xl mr-2"></i>
            <span class="font-semibold"><?= htmlspecialchars($errorMessages[0]) ?></span>
            <p class="mt-2"><a href="cart.php" class="underline">Return to cart</a></p>
        </div>
        <?php elseif ($orderSaved): ?>
        
        <div class="mb-8 text-center">
            <div class="bg-green-100 text-green-700 p-4 rounded-lg mb-4">
                <i class="fas fa-check-circle text-2xl mr-2"></i>
                <span class="font-semibold">Your order has been successfully placed!</span>
            </div>
            
            <h2 class="text-xl font-semibold mb-2">Order Number:</h2>
            <div class="order-number"><?= htmlspecialchars($order_number) ?></div>
            
            <h2 class="text-xl font-semibold mt-6 mb-2">Your Pickup Code:</h2>
            <div class="pickup-code"><?= htmlspecialchars($pickup_code) ?></div>
            
            <p class="text-gray-600 mt-4">
                Please present this pickup code to the clerk when collecting your order.
                <br>The clerk will scan or enter this code to verify your purchase.
            </p>
        </div>
        
        <div class="mb-8">
            <h2 class="text-xl font-semibold border-b border-gray-200 pb-2 mb-4">Order Details</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h3 class="font-semibold mb-2">Customer Information:</h3>
                    <p><span class="text-gray-600">Name:</span> <?= htmlspecialchars($full_name) ?></p>
                    <p><span class="text-gray-600">Email:</span> <?= htmlspecialchars($email) ?></p>
                    <p><span class="text-gray-600">Address:</span> <?= htmlspecialchars($address) ?></p>
                    <p><span class="text-gray-600">City:</span> <?= htmlspecialchars($city) ?></p>
                    <p><span class="text-gray-600">State:</span> <?= htmlspecialchars($state) ?></p>
                    <p><span class="text-gray-600">Postal Code:</span> <?= htmlspecialchars($postalcode) ?></p>
                </div>
                <div>
                    <h3 class="font-semibold mb-2">Order Summary:</h3>
                    <p><span class="text-gray-600">Order Date:</span> <?= date('F j, Y g:i A') ?></p>
                    <p><span class="text-gray-600">Status:</span> <span class="text-orange-500">Pending Pickup</span></p>
                    <p><span class="text-gray-600">Payment Method:</span> Online</p>
                    <p class="font-semibold mt-4">Total: $<?= number_format($total, 2) ?></p>
                </div>
            </div>
        </div>
        
        <div class="mb-8">
            <h2 class="text-xl font-semibold border-b border-gray-200 pb-2 mb-4">Purchased Items</h2>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="py-2 px-4 text-left">Item</th>
                            <th class="py-2 px-4 text-center">Quantity</th>
                            <th class="py-2 px-4 text-right">Price</th>
                            <th class="py-2 px-4 text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($productsInCart as $item): ?>
                            <tr>
                                <td class="py-3 px-4"><?= htmlspecialchars($item['name']) ?></td>
                                <td class="py-3 px-4 text-center"><?= $item['quantity'] ?></td>
                                <td class="py-3 px-4 text-right">$<?= number_format($item['sale_price'], 2) ?></td>
                                <td class="py-3 px-4 text-right">$<?= number_format($item['subtotal'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-gray-50 font-semibold">
                        <tr>
                            <td colspan="3" class="py-3 px-4 text-right">Total:</td>
                            <td class="py-3 px-4 text-right">$<?= number_format($total, 2) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        
        <div class="flex justify-center mt-8 space-x-4">
            <button onclick="window.print()" class="action-btn text-white px-6 py-2 rounded flex items-center">
                <i class="fas fa-print mr-2"></i> Print Receipt
            </button>
            <a href="customer_dashboard.php" class="accent-btn text-white px-6 py-2 rounded flex items-center">
                <i class="fas fa-tachometer-alt mr-2"></i> Dashboard
            </a>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
