<?php
// Enable error reporting for debugging
session_start();
require_once '../db_connect.php';
require_once '../functions.php';

// Check if user is logged in and is an employee with less strict check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    error_log("User not logged in. Redirecting to login page.");
    header("Location: ../login.php");
    exit();
}

if ($_SESSION['role'] != 'Employee' && $_SESSION['role'] != 'Admin') {
    error_log("User role not authorized: " . $_SESSION['role']);
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

// Debug employee data
if (!$employee) {
    error_log("Employee data not found for user_id: $user_id");
}

// Make sure the employee is authorized (now with less strict role check)
if ($employee && $employee['role'] != 'Clerk' && $employee['role'] != 'Manager' && $role != 'Admin') {
    error_log("Employee role not authorized: " . $employee['role']);
    header("Location: ../employee_dashboard.php");
    exit();
}

$facility_id = $employee ? $employee['facility_id'] : 0;
$transaction_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Debug transaction ID
error_log("Processing transaction ID: $transaction_id");

// Get transaction details
$transaction = null;
$transaction_items = null;
$customer = null;
$error_message = '';

if ($transaction_id > 0) {
    try {
        // Get transaction details with safety checks
        $query = "SELECT t.*, s.shop_name, s.facility_id, f.city as facility_city, f.type as facility_type
                FROM Shop_Transaction t
                JOIN Shop s ON t.shop_id = s.shop_id
                JOIN Facility f ON s.facility_id = f.facility_id
                WHERE t.transaction_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $transaction_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $error_message = "Transaction not found";
            error_log("Transaction not found: $transaction_id");
        } else {
            $transaction = $result->fetch_assoc();
            error_log("Found transaction: " . print_r($transaction, true));
            
            // More lenient facility check for admins and special roles
            if ($transaction['facility_id'] != $facility_id && $role != 'Admin') {
                $error_message = "You don't have permission to view this transaction";
                error_log("Transaction facility mismatch. Transaction facility: " . $transaction['facility_id'] . ", Employee facility: $facility_id");
            } else {
                // Get customer information - handle case where join might fail
                try {
                    $query = "SELECT c.* 
                            FROM Customer c
                            WHERE c.user_id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("i", $transaction['user_id']);
                    $stmt->execute();
                    $customer = $stmt->get_result()->fetch_assoc();
                    
                    if (!$customer) {
                        error_log("Customer not found for user_id: " . $transaction['user_id']);
                    } else {
                        error_log("Found customer: " . print_r($customer, true));
                    }
                } catch (Exception $e) {
                    error_log("Error fetching customer: " . $e->getMessage());
                }
                
                // Get transaction items with safety
                try {
                    $query = "SELECT ss.*, i.name, i.description, i.image_path
                            FROM Shop_Sale ss
                            JOIN Items i ON ss.item_id = i.item_id
                            WHERE ss.transaction_id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("i", $transaction_id);
                    $stmt->execute();
                    $transaction_items = $stmt->get_result();
                    
                    if ($transaction_items->num_rows === 0) {
                        error_log("No items found for transaction: $transaction_id");
                    }
                } catch (Exception $e) {
                    error_log("Error fetching items: " . $e->getMessage());
                }
            }
        }
    } catch (Exception $e) {
        $error_message = "Error retrieving transaction: " . $e->getMessage();
        error_log("Transaction error: " . $e->getMessage());
    }
} else {
    $error_message = "Invalid transaction ID";
    error_log("Invalid transaction ID: $transaction_id");
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
        <div class="flex items-center mb-6">
            <a href="../package/awaiting_pickup.php" class="text-blue-600 hover:text-blue-800 mr-4">
                <i class="fas fa-arrow-left"></i> Back to Awaiting Pickup
            </a>
            <h1 class="text-3xl font-bold section-title flex-grow">Shop Transaction Details</h1>
        </div>
        
        <nav class="bg-white shadow-sm p-4 rounded-lg mb-6">
            <ul class="flex flex-wrap space-x-2 md:space-x-6">
                <li><a href="shop_dashboard.php" class="text-gray-700 hover:text-[#DA291C]">Shop Overview</a></li>
                <li><a href="process_sale.php" class="text-gray-700 hover:text-[#DA291C]">Process Sale</a></li>
                <li><a href="transaction_list.php" class="text-gray-700 hover:text-[#DA291C]">Transaction List</a></li>
                <li><a href="../employee_dashboard.php" class="text-gray-700 hover:text-[#DA291C]">Main Dashboard</a></li>
            </ul>
        </nav>
        
        <?php if (!empty($error_message)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
            <p><?= htmlspecialchars($error_message) ?></p>
            <a href="../package/awaiting_pickup.php" class="text-blue-600 hover:text-blue-800 mt-2 inline-block">
                Return to Awaiting Pickup
            </a>
        </div>
        <?php endif; ?>
        
        <?php if ($transaction): ?>
        <div class="bg-white shadow-md rounded-lg overflow-hidden mb-6">
            <div class="bg-[#004B87] text-white p-4">
                <div class="flex justify-between items-center">
                    <h2 class="text-xl font-semibold">Transaction #<?= htmlspecialchars($transaction['transaction_id']) ?></h2>
                    <span class="px-2 py-1 rounded-full text-xs font-medium bg-white text-[#004B87]">
                        <?= htmlspecialchars($transaction['transaction_status']) ?>
                    </span>
                </div>
                <p class="text-sm mt-1">
                    <?= date('F j, Y, g:i a', strtotime($transaction['transaction_date'])) ?>
                </p>
            </div>
            
            <div class="p-6">
                <!-- Customer Information -->
                <div class="mb-6">
                    <h3 class="text-lg font-semibold mb-2 text-[#004B87]">Customer Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-gray-50 p-4 rounded-lg">
                        <?php if ($customer): ?>
                        <div>
                            <p class="font-medium"><?= htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) ?></p>
                            <p class="text-sm text-gray-600"><?= htmlspecialchars($customer['phone']) ?></p>
                        </div>
                        <div>
                            <?php if (isset($customer['address'])): ?>
                            <p class="text-sm"><?= htmlspecialchars($customer['address']) ?></p>
                            <p class="text-sm"><?= htmlspecialchars($customer['city'] . ', ' . $customer['state'] . ' ' . $customer['postal_code']) ?></p>
                            <?php else: ?>
                            <p class="text-sm text-gray-600">Address information not available</p>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <p class="text-gray-600">Customer information not available</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Transaction Details -->
                <div class="mb-6">
                    <h3 class="text-lg font-semibold mb-2 text-[#004B87]">Transaction Details</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 bg-gray-50 p-4 rounded-lg">
                        <div>
                            <p class="text-sm text-gray-600">Shop Location</p>
                            <p class="font-medium"><?= htmlspecialchars($transaction['facility_type'] . ' - ' . $transaction['facility_city']) ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Total Amount</p>
                            <p class="font-medium">$<?= number_format($transaction['total_amount'], 2) ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Payment Method</p>
                            <p class="font-medium"><?= htmlspecialchars($transaction['payment_method']) ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Shop Name</p>
                            <p class="font-medium"><?= htmlspecialchars($transaction['shop_name']) ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Item List -->
                <div>
                    <h3 class="text-lg font-semibold mb-2 text-[#004B87]">Purchased Items</h3>
                    <?php if ($transaction_items && $transaction_items->num_rows > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="px-4 py-2 text-left">Item</th>
                                    <th class="px-4 py-2 text-left">Description</th>
                                    <th class="px-4 py-2 text-right">Quantity</th>
                                    <th class="px-4 py-2 text-right">Unit Price</th>
                                    <th class="px-4 py-2 text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total = 0;
                                while ($item = $transaction_items->fetch_assoc()): 
                                    $item_total = $item['quantity'] * $item['sale_amount'];
                                    $total += $item_total;
                                ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center">
                                            <?php if (!empty($item['image_path'])): ?>
                                            <img src="<?= htmlspecialchars($item['image_path']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="w-10 h-10 object-cover rounded mr-3">
                                            <?php endif; ?>
                                            <span class="font-medium"><?= htmlspecialchars($item['name']) ?></span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        <?= htmlspecialchars(substr($item['description'], 0, 100)) ?>
                                        <?= strlen($item['description']) > 100 ? '...' : '' ?>
                                    </td>
                                    <td class="px-4 py-3 text-right"><?= htmlspecialchars($item['quantity']) ?></td>
                                    <td class="px-4 py-3 text-right">$<?= number_format($item['sale_amount'], 2) ?></td>
                                    <td class="px-4 py-3 text-right font-medium">$<?= number_format($item_total, 2) ?></td>
                                </tr>
                                <?php endwhile; ?>
                                <tr class="bg-gray-50">
                                    <td colspan="4" class="px-4 py-3 text-right font-bold">Total:</td>
                                    <td class="px-4 py-3 text-right font-bold">$<?= number_format($total, 2) ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-gray-600 p-4 bg-gray-50 rounded-lg">No items available for this transaction</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="bg-gray-50 px-6 py-4 flex justify-between">
                <div>
                    <a href="../package/awaiting_pickup.php" class="text-gray-600 hover:text-gray-800">
                        <i class="fas fa-arrow-left mr-1"></i> Back to Awaiting Pickup
                    </a>
                </div>
                
                <?php if ($transaction['transaction_status'] === 'Completed'): ?>
                <form method="get" action="../package/deliver.php" class="inline">
                    <input type="hidden" name="tracking" value="ST<?= htmlspecialchars($transaction['transaction_id']) ?>">
                    <input type="hidden" name="transaction_id" value="<?= htmlspecialchars($transaction['transaction_id']) ?>">
                    <input type="hidden" name="item_type" value="shop">
                    <button type="submit" class="bg-[#004B87] text-white px-4 py-2 rounded">
                        <i class="fas fa-check mr-1"></i> Mark as Picked Up
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <footer class="text-center mt-10 py-6 bg-[#004B87] text-white rounded-lg">
            <p>&copy; <?= date('Y') ?> Postal Service Management System</p>
        </footer>
    </div>
</body>
</html> 