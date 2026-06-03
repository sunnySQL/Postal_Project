<?php
session_start();
require_once 'db_connect.php';
require_once 'functions.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Ensure transaction ID is provided
if (!isset($_GET['tx']) || !is_numeric($_GET['tx'])) {
    header("Location: " . ($role == 'Customer' ? 'customer_dashboard.php' : 'employee_dashboard.php'));
    exit();
}

$transaction_id = intval($_GET['tx']);

// Get transaction details based on user role
$transaction_query = "";
if ($role == 'Customer') {
    // Customers can only view their own transactions
    $transaction_query = "SELECT t.*, s.shop_name, f.city, f.address as shop_address, f.state as shop_state, 
                          f.zip_code as shop_zip, c.first_name, c.last_name, c.address, c.city as customer_city, 
                          c.state, c.zip, c.email, c.phone
                          FROM Shop_Transaction t
                          JOIN Shop s ON t.shop_id = s.shop_id
                          JOIN Facility f ON s.facility_id = f.facility_id
                          JOIN Customer c ON t.user_id = c.user_id
                          WHERE t.transaction_id = ? AND t.user_id = ?";
    $stmt = $conn->prepare($transaction_query);
    $stmt->bind_param("ii", $transaction_id, $user_id);
} else {
    // Employees/Admin can view any transaction
    $transaction_query = "SELECT t.*, s.shop_name, f.city, f.address as shop_address, f.state as shop_state, 
                          f.zip_code as shop_zip, c.first_name, c.last_name, c.address, c.city as customer_city, 
                          c.state, c.zip, c.email, c.phone
                          FROM Shop_Transaction t
                          JOIN Shop s ON t.shop_id = s.shop_id
                          JOIN Facility f ON s.facility_id = f.facility_id
                          LEFT JOIN Customer c ON t.user_id = c.user_id
                          WHERE t.transaction_id = ?";
    $stmt = $conn->prepare($transaction_query);
    $stmt->bind_param("i", $transaction_id);
}

$stmt->execute();
$transaction = $stmt->get_result()->fetch_assoc();

// Check if transaction exists and user has permission to view it
if (!$transaction) {
    header("Location: " . ($role == 'Customer' ? 'customer_dashboard.php' : 'employee_dashboard.php'));
    exit();
}

// Get transaction items
$stmt = $conn->prepare("SELECT ss.*, i.name, i.description
                      FROM Shop_Sale ss
                      JOIN Items i ON ss.item_id = i.item_id
                      WHERE ss.transaction_id = ?");
$stmt->bind_param("i", $transaction_id);
$stmt->execute();
$items = $stmt->get_result();

// Generate invoice number if it doesn't exist
$invoice_number = "SHOP-" . str_pad($transaction_id, 6, "0", STR_PAD_LEFT);

// Format transaction date
$transaction_date = date('F d, Y', strtotime($transaction['transaction_date']));
$invoice_date = date('F d, Y');

// Calculate totals
$subtotal = $transaction['total_amount'];
$tax_rate = 0.07; // 7% tax
$tax = $subtotal * $tax_rate;
$total = $subtotal; // Tax included in the original price
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?= $invoice_number ?></title>
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
        
        .invoice-container {
            background-color: white;
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.12);
        }
        
        .logo {
            color: #004B87;
            font-weight: bold;
            font-size: 1.5rem;
        }
        
        .invoice-header {
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 1rem;
        }
        
        .invoice-title {
            color: #004B87;
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .invoice-details {
            margin: 1rem 0;
        }
        
        .customer-details, .shop-details {
            margin-bottom: 1rem;
        }
        
        .section-title {
            font-weight: bold;
            margin-bottom: 0.5rem;
            color: #004B87;
        }
        
        .table-container {
            margin: 1.5rem 0;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background-color: #f1f5f9;
            text-align: left;
            padding: 0.75rem;
        }
        
        td {
            padding: 0.75rem;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .totals {
            margin-top: 1rem;
            margin-left: auto;
            width: 300px;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
        }
        
        .grand-total {
            font-weight: bold;
            border-top: 2px solid #004B87;
            padding-top: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .footer {
            border-top: 1px solid #e2e8f0;
            margin-top: 2rem;
            padding-top: 1rem;
            text-align: center;
            color: #718096;
        }
        
        .print-button {
            background-color: #004B87;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.25rem;
            text-decoration: none;
            display: inline-block;
            margin-top: 1rem;
        }
        
        .print-button:hover {
            background-color: #003366;
        }
        
        @media print {
            body {
                background-color: white;
            }
            
            .invoice-container {
                box-shadow: none;
                max-width: 100%;
                margin: 0;
                padding: 0;
            }
            
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <div class="invoice-header flex justify-between items-center">
            <div class="logo">POSTAL PRO</div>
            <div class="invoice-title">INVOICE</div>
        </div>
        
        <div class="invoice-details flex justify-between">
            <div>
                <div class="section-title">Invoice Number:</div>
                <div><?= $invoice_number ?></div>
                <div class="mt-2">
                    <div class="section-title">Date:</div>
                    <div><?= $invoice_date ?></div>
                </div>
            </div>
            
            <div>
                <div class="section-title">Transaction ID:</div>
                <div><?= $transaction_id ?></div>
                <div class="mt-2">
                    <div class="section-title">Transaction Date:</div>
                    <div><?= $transaction_date ?></div>
                </div>
            </div>
        </div>
        
        <div class="flex flex-col md:flex-row justify-between">
            <div class="shop-details mb-4 md:mb-0 md:w-1/2 pr-4">
                <div class="section-title">From:</div>
                <div class="font-semibold"><?= htmlspecialchars($transaction['shop_name']) ?></div>
                <div><?= htmlspecialchars($transaction['shop_address']) ?></div>
                <div><?= htmlspecialchars($transaction['city']) . ', ' . htmlspecialchars($transaction['shop_state']) . ' ' . htmlspecialchars($transaction['shop_zip']) ?></div>
            </div>
            
            <div class="customer-details md:w-1/2 pl-0 md:pl-4">
                <div class="section-title">To:</div>
                <?php if ($transaction['user_id']): ?>
                <div class="font-semibold"><?= htmlspecialchars($transaction['first_name'] . ' ' . $transaction['last_name']) ?></div>
                <div><?= htmlspecialchars($transaction['address']) ?></div>
                <div><?= htmlspecialchars($transaction['customer_city']) . ', ' . htmlspecialchars($transaction['state']) . ' ' . htmlspecialchars($transaction['zip']) ?></div>
                <div><?= htmlspecialchars($transaction['email']) ?></div>
                <div><?= htmlspecialchars($transaction['phone']) ?></div>
                <?php else: ?>
                <div class="font-semibold">Walk-in Customer</div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th class="w-1/2">Item</th>
                        <th>Price</th>
                        <th>Quantity</th>
                        <th class="text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $item_total = 0; ?>
                    <?php while ($item = $items->fetch_assoc()): ?>
                    <?php $item_price = $item['sale_amount'] / $item['quantity']; ?>
                    <tr>
                        <td>
                            <div class="font-semibold"><?= htmlspecialchars($item['name']) ?></div>
                            <div class="text-sm text-gray-600"><?= htmlspecialchars(substr($item['description'], 0, 60)) . (strlen($item['description']) > 60 ? '...' : '') ?></div>
                        </td>
                        <td>$<?= number_format($item_price, 2) ?></td>
                        <td><?= $item['quantity'] ?></td>
                        <td class="text-right">$<?= number_format($item['sale_amount'], 2) ?></td>
                    </tr>
                    <?php $item_total += $item['sale_amount']; ?>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        
        <div class="totals ml-auto">
            <div class="total-row">
                <div>Subtotal:</div>
                <div>$<?= number_format($subtotal, 2) ?></div>
            </div>
            <div class="total-row text-gray-600">
                <div>Tax (Included):</div>
                <div>$<?= number_format($tax, 2) ?></div>
            </div>
            <div class="total-row grand-total">
                <div>Total:</div>
                <div>$<?= number_format($total, 2) ?></div>
            </div>
            <div class="total-row">
                <div>Payment Method:</div>
                <div><?= htmlspecialchars($transaction['payment_method']) ?></div>
            </div>
            <div class="total-row text-green-600 font-semibold">
                <div>Status:</div>
                <div><?= htmlspecialchars($transaction['transaction_status']) ?></div>
            </div>
        </div>
        
        <div class="footer">
            <div class="mb-2">Thank you for your business!</div>
            <div class="text-sm">This is a computer-generated invoice and does not require a signature.</div>
        </div>
        
        <div class="flex justify-between items-center mt-6 no-print">
            <a href="<?= $role == 'Customer' ? 'shop_purchase_history.php' : 'shop/shop_dashboard.php' ?>" class="text-blue-600">
                <i class="fas fa-arrow-left mr-1"></i> Back
            </a>
            <button onclick="window.print()" class="print-button">
                <i class="fas fa-print mr-1"></i> Print Invoice
            </button>
        </div>
    </div>
</body>
</html> 