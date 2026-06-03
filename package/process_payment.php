<?php
require '../functions.php';
require '../db_connect.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || 
    ($_SESSION['role'] != 'Employee' && $_SESSION['role'] != 'Admin')) {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header("Location: new_package.php");
    exit();
}

if (!isset($_SESSION['package_data'])) {
    $_SESSION['error_message'] = "Package data not found. Please create a new package.";
    header("Location: new_package.php");
    exit();
}

$payment_method = $_POST['payment_method'] ?? null;

if (empty($payment_method)) {
    $_SESSION['error_message'] = "Please select a payment method.";
    header("Location: package_confirmation.php");
    exit();
}

$package_data = $_SESSION['package_data'];
$tracking_number = $package_data['tracking_number'];
$weight = $package_data['weight'];
$size = $package_data['size'];
$postage = $package_data['postage'];
$sender_id = $package_data['sender_id'];
$receiver_id = $package_data['receiver_id'];
$facility_id = $package_data['facility_id'];
$signature_required = $package_data['signature_required'];
$shipping_speed = $package_data['shipping_speed'] ?? 'Standard';
$notes = $package_data['notes'] ?? '';
$invoice_number = $package_data['invoice_number'];

// Prevent duplicate processing: if this package already exists, it was already processed
$exists = $conn->prepare("SELECT 1 FROM Package WHERE tracking_number = ?");
$exists->bind_param("s", $tracking_number);
$exists->execute();
if ($exists->get_result()->num_rows > 0) {
    unset($_SESSION['package_data']);
    $_SESSION['error_message'] = "This package has already been processed. Tracking number: " . htmlspecialchars($tracking_number);
    header("Location: ../employee_dashboard.php");
    exit();
}

$conn->begin_transaction();
try {
    $debug_msg = [];
    $debug_msg[] = "Starting transaction for tracking number: $tracking_number";
    $debug_msg[] = "Sender ID: $sender_id, Receiver ID: $receiver_id";
    
    // Insert into Package table
    $stmt = $conn->prepare("INSERT INTO Package (tracking_number, weight, size, postage, signature_required, status, timestamp_created, sender_id, receiver_id, facility_id, shipping_speed) 
                           VALUES (?, ?, ?, ?, ?, 'Processing', CURRENT_TIMESTAMP, ?, ?, ?, ?)");
    
    if (!$stmt) {
        throw new Exception("Prepare failed for Package insert: " . $conn->error);
    }
    
    $stmt->bind_param("sdsssiiss", $tracking_number, $weight, $size, $postage, $signature_required, $sender_id, $receiver_id, $facility_id, $shipping_speed);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed for Package insert: " . $stmt->error);
    }
    
    $debug_msg[] = "Package record inserted successfully";
    
    // Insert into Package_Payment table
    $stmt = $conn->prepare("INSERT INTO Package_Payment (user_id, package_id, amount, payment_method, transaction_status, invoice_number, payment_date, facility_id) 
                        VALUES (?, ?, ?, ?, 'Completed', ?, CURRENT_TIMESTAMP, ?)");

    if (!$stmt) {
        throw new Exception("Prepare failed for Package_Payment insert: " . $conn->error);
    }

    $stmt->bind_param("issssi", $sender_id, $tracking_number, $postage, $payment_method, $invoice_number, $facility_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed for Package_Payment insert: " . $stmt->error);
    }
    
    $payment_id = $conn->insert_id;
    $debug_msg[] = "Package_Payment record inserted with ID: $payment_id";
    
    // Insert into Tracking_History table
    $location = ""; 
    $stmt = $conn->prepare("SELECT CONCAT(city, ', ', state, ' - ', type) AS location FROM Facility WHERE facility_id = ?");
    
    if (!$stmt) {
        throw new Exception("Prepare failed for facility query: " . $conn->error);
    }
    
    $stmt->bind_param("i", $facility_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed for facility query: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $location = $row['location'];
    }
    
    $debug_msg[] = "Retrieved facility location: $location";
    
    $stmt = $conn->prepare("INSERT INTO Tracking_History (tracking_number, location, status, employee_id, action, facility_id) 
                       VALUES (?, ?, 'Processing', ?, 'None', ?)");
    
    if (!$stmt) {
        throw new Exception("Prepare failed for Tracking_History insert: " . $conn->error);
    }
    
    $stmt->bind_param("ssii", $tracking_number, $location, $_SESSION['user_id'], $facility_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed for Tracking_History insert: " . $stmt->error);
    }
    
    $debug_msg[] = "Tracking_History record inserted successfully";
    

    $conn->commit();
    $debug_msg[] = "Transaction committed successfully";

    logAudit($conn, 'PACKAGE_CREATED', 'Package', $tracking_number,
        "Package {$tracking_number} created — {$shipping_speed}, {$size}, " .
        number_format($weight,2) . " lbs, \$" . number_format($postage,2) . " ({$payment_method})",
        $facility_id,
        null,
        ['tracking_number'=>$tracking_number,'weight'=>$weight,'size'=>$size,
         'postage'=>$postage,'shipping_speed'=>$shipping_speed,'payment_method'=>$payment_method]
    );

    unset($_SESSION['package_data']);
    $_SESSION['success_message'] = "Package #$tracking_number processed successfully with payment of $" . number_format($postage, 2);
    header("Location: ../employee_dashboard.php?package=processed");
    exit();
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Database error in process_payment.php: " . $e->getMessage());
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error Processing Payment - POSTAL PRO</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap');
            * { font-family: 'Open Sans', sans-serif; }
            body { background-color: #f8f9fa; }
            .top-nav { background: #004B87; color: white; }
            .accent-btn { background-color: #DA291C; transition: background-color 0.3s; }
            .accent-btn:hover { background-color: #b52218; }
            .section-title { color: #004B87; }
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
        <div class="container mx-auto px-4 py-8 max-w-2xl mt-20">
            <section class="bg-white p-6 rounded-lg shadow-md border-l-4 border-[#DA291C]">
                <h1 class="text-xl font-bold section-title mb-4">Error Processing Payment</h1>
                <p class="text-gray-700 mb-3">An error occurred while processing your payment:</p>
                <pre class="bg-gray-100 p-3 rounded text-sm overflow-x-auto mb-4"><?= htmlspecialchars($e->getMessage()) ?></pre>
                <p class="text-gray-600 text-sm mb-4">Please try again or contact the system administrator.</p>
                <div class="flex flex-wrap gap-3 pt-2">
                    <a href="package_confirmation.php" class="accent-btn text-white px-4 py-2 rounded">Back to Payment</a>
                    <a href="new_package.php" class="border border-gray-300 bg-white text-gray-700 px-4 py-2 rounded hover:bg-gray-50">Create New Package</a>
                </div>
            </section>
        </div>
    </body>
    </html>
    <?php
    exit();
}
?>