<?php
session_start();
require_once '../db_connect.php';
require_once '../functions.php';

// Enable error reporting for debugging
// Check if user is logged in and is an employee
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || 
    ($_SESSION['role'] != 'Employee' && $_SESSION['role'] != 'Admin')) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get transaction ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    // Invalid transaction ID, redirect to dashboard
    header("Location: ../employee_dashboard.php");
    exit();
}

$transaction_id = intval($_GET['id']);

// Get employee info
$stmt = $conn->prepare("SELECT e.*, f.facility_id, f.city, f.type 
                       FROM Employee e 
                       JOIN Facility f ON e.facility_id = f.facility_id 
                       WHERE e.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc();

$facility_id = $employee['facility_id'];

// Get transaction details
$stmt = $conn->prepare("
    SELECT t.*, s.shop_name, s.facility_id,
           c.first_name, c.last_name, c.phone
    FROM Shop_Transaction t
    JOIN Shop s ON t.shop_id = s.shop_id
    JOIN Customer c ON t.user_id = c.user_id
    WHERE t.transaction_id = ?
");
$stmt->bind_param("i", $transaction_id);
$stmt->execute();
$transaction = $stmt->get_result()->fetch_assoc();

// If transaction doesn't exist or doesn't belong to this facility, redirect
if (!$transaction || ($transaction['facility_id'] != $facility_id && $role != 'Admin')) {
    header("Location: ../employee_dashboard.php");
    exit();
}

// Format transaction status for display
$status_class = '';
switch($transaction['transaction_status']) {
    case 'Completed': $status_class = 'bg-green-100 text-green-800'; break;
    case 'Picked Up': $status_class = 'bg-green-100 text-green-800'; break;
    case 'Failed': $status_class = 'bg-red-100 text-red-800'; break;
    case 'Processing': $status_class = 'bg-yellow-100 text-yellow-800'; break;
    default: $status_class = 'bg-gray-100 text-gray-800';
}

// Use the total amount from the transaction record
$total_amount = $transaction['total_amount'] ?? 0;

// Handle actions
$message = '';
$message_type = '';

if (isset($_POST['action'])) {
    if ($_POST['action'] == 'mark_picked_up') {
        // Update transaction status to Picked Up
        $update_stmt = $conn->prepare("UPDATE Shop_Transaction SET transaction_status = 'Picked Up' WHERE transaction_id = ?");
        $update_stmt->bind_param("i", $transaction_id);
        
        if ($update_stmt->execute()) {
            $message = "Transaction has been marked as picked up.";
            $message_type = 'success';
            // Refresh transaction data
            $transaction['transaction_status'] = 'Picked Up';
            $status_class = 'bg-green-100 text-green-800';
        } else {
            $message = "Error updating transaction status: " . $conn->error;
            $message_type = 'error';
        }
    } elseif ($_POST['action'] == 'notify_customer') {
        // In a real system, this would send an actual notification
        // For this project, just update a flag or create a notification record
        
        // Check if notification_sent column exists
        $columnsResult = $conn->query("SHOW COLUMNS FROM Shop_Transaction LIKE 'notification_sent'");
        if ($columnsResult->num_rows > 0) {
            $update_stmt = $conn->prepare("UPDATE Shop_Transaction SET notification_sent = 1 WHERE transaction_id = ?");
            $update_stmt->bind_param("i", $transaction_id);
        } else {
            // Create a notification record instead
            $update_stmt = $conn->prepare("INSERT INTO Notifications (user_id, message, type) VALUES (?, ?, 'Shop')");
            $notification_message = "Your shop order #$transaction_id is ready for pickup at " . $transaction['shop_name'] . ".";
            $update_stmt->bind_param("is", $transaction['user_id'], $notification_message);
        }
        
        if ($update_stmt->execute()) {
            $message = "Customer has been notified about their order.";
            $message_type = 'success';
        } else {
            $message = "Error sending notification: " . $conn->error;
            $message_type = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction Details - POSTAL PRO</title>
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
            <a href="../index.php" class="text-white font-bold text-2xl">POSTAL PRO</a>
            <ul class="flex space-x-6">
                <li><a href="../index.php" class="text-white hover:text-gray-200">Home</a></li>
                <li><a href="../employee_dashboard.php" class="text-white hover:text-gray-200">Dashboard</a></li>
                <li><a href="../logout.php" class="text-white hover:text-gray-200">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8 max-w-6xl mt-20">
        <div class="mb-6 flex justify-between items-center">
            <h1 class="text-3xl font-bold section-title">Transaction Details</h1>
        </div>
        
        <?php if (!empty($message)): ?>
        <div class="mb-6 p-4 rounded-lg <?= $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <!-- Transaction Info -->
            <div class="bg-white p-6 rounded-lg shadow-md col-span-2">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold">Transaction Information</h2>
                    <span class="<?= $status_class ?> px-3 py-1 rounded-full text-sm">
                        <?= htmlspecialchars($transaction['transaction_status']) ?>
                    </span>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-600">Transaction ID</p>
                        <p class="font-medium">#<?= htmlspecialchars($transaction_id) ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Transaction Date</p>
                        <p class="font-medium"><?= date('M d, Y h:i A', strtotime($transaction['transaction_date'])) ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Shop</p>
                        <p class="font-medium"><?= htmlspecialchars($transaction['shop_name']) ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Total Amount</p>
                        <p class="font-medium">$<?= number_format($total_amount, 2) ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Payment Method</p>
                        <p class="font-medium"><?= htmlspecialchars($transaction['payment_method'] ?? 'Cash') ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Customer Info -->
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold mb-4">Customer Information</h2>
                <div class="space-y-3">
                    <div>
                        <p class="text-sm text-gray-600">Name</p>
                        <p class="font-medium"><?= htmlspecialchars($transaction['first_name'] . ' ' . $transaction['last_name']) ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Phone</p>
                        <p class="font-medium"><?= htmlspecialchars($transaction['phone']) ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Transaction Summary -->
        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
            <h2 class="text-xl font-semibold mb-4">Transaction Summary</h2>
            <div class="rounded-lg bg-gray-50 p-4">
                <p class="text-lg">
                    This order is currently <span class="font-bold"><?= htmlspecialchars($transaction['transaction_status']) ?></span> 
                    and <?= $transaction['transaction_status'] == 'Completed' ? 'ready for pickup' : ($transaction['transaction_status'] == 'Picked Up' ? 'has been picked up' : 'has been processed') ?>.
                </p>
                <p class="mt-2 text-gray-600">
                    Transaction was processed on <?= date('F d, Y', strtotime($transaction['transaction_date'])) ?> 
                    at <?= date('h:i A', strtotime($transaction['transaction_date'])) ?> at the <?= htmlspecialchars($transaction['shop_name']) ?> shop.
                </p>
                <?php if ($total_amount > 0): ?>
                <p class="mt-2 text-lg font-semibold">
                    Total Amount: $<?= number_format($total_amount, 2) ?>
                </p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="mt-6 flex justify-between">
            <a href="../package/awaiting_pickup.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">
                <i class="fas fa-arrow-left mr-1"></i> Back to Awaiting Pickup
            </a>
            <a href="../employee_dashboard.php" class="action-btn text-white px-4 py-2 rounded">
                <i class="fas fa-home mr-1"></i> Dashboard
            </a>
        </div>
        
        <footer class="text-center mt-10 py-6 bg-[#004B87] text-white rounded-lg">
            <p>&copy; <?= date('Y') ?> Postal Service Management System</p>
        </footer>
    </div>
</body>
</html> 