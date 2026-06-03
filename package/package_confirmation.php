<?php
session_start();
require_once '../functions.php';
require_once '../db_connect.php';
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || 
    ($_SESSION['role'] != 'Employee' && $_SESSION['role'] != 'Admin')) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'Employee';
$stmt = $conn->prepare("SELECT e.first_name, e.last_name, e.role as employee_role, e.facility_id,
                        CONCAT(f.city, ', ', f.state, ' - ', f.type) as facility_name
                        FROM Employee e JOIN Facility f ON e.facility_id = f.facility_id WHERE e.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$employee = $stmt->get_result()->fetch_assoc();
$name = $employee ? trim($employee['first_name'] . ' ' . $employee['last_name']) : 'Employee';
if ($employee) {
    $employee['role'] = $employee['employee_role'];
}
$unread_admin_messages = 0;
if ($role !== 'Admin' && $employee) {
    $msg_stmt = $conn->prepare("SELECT COUNT(*) as count FROM Admin_Messages WHERE sender_id = ? AND status = 'Replied'");
    $msg_stmt->bind_param("i", $user_id);
    $msg_stmt->execute();
    $unread_admin_messages = (int)($msg_stmt->get_result()->fetch_assoc()['count'] ?? 0);
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header("Location: new_package.php");
    exit();
}

// Retrieve form data
$tracking_number = $_POST['tracking_number'] ?? '';
$size = $_POST['size'] ?? '';
$weight = $_POST['weight'] ?? '';
$facility_id = $_POST['facility_id'] ?? '';
$origin_zip = $_POST['origin_zip'] ?? '';
$signature_required = isset($_POST['signature_required']) ? 'Y' : 'N';
$shipping_speed = $_POST['shipping_speed'] ?? 'Standard';
$notes = $_POST['notes'] ?? '';

// Fix for sender_id - check both possible field names
$sender_id = '';
if (!empty($_POST['sender_id'])) {
    $sender_id = $_POST['sender_id'];
} elseif (!empty($_POST['sender_select'])) {
    $sender_id = $_POST['sender_select'];
}

if (empty($sender_id)) {
    $sender_first_name = $_POST['sender_first_name'] ?? '';
    $sender_last_name = $_POST['sender_last_name'] ?? '';
    $sender_phone = $_POST['sender_phone'] ?? '';
    $sender_street = $_POST['sender_street'] ?? '';
    $sender_city = $_POST['sender_city'] ?? '';
    $sender_state = $_POST['sender_state'] ?? '';
    $sender_zip = $_POST['sender_zip'] ?? '';
}

// Fix for receiver_id - check both possible field names
$receiver_id = '';
if (!empty($_POST['receiver_id'])) {
    $receiver_id = $_POST['receiver_id'];
} elseif (!empty($_POST['receiver_select'])) {
    $receiver_id = $_POST['receiver_select'];
}

if (empty($receiver_id)) {
    $receiver_first_name = $_POST['receiver_first_name'] ?? '';
    $receiver_last_name = $_POST['receiver_last_name'] ?? '';
    $receiver_phone = $_POST['receiver_phone'] ?? '';
    $receiver_street = $_POST['receiver_street'] ?? '';
    $receiver_city = $_POST['receiver_city'] ?? '';
    $receiver_state = $_POST['receiver_state'] ?? '';
    $receiver_zip = $_POST['receiver_zip'] ?? '';
}

// Create new sender if needed
if (empty($sender_id)) {
    if (empty($sender_first_name) || empty($sender_last_name)) {
        $_SESSION['error_message'] = "Missing required sender information. Please go back and try again.";
        header("Location: new_package.php");
        exit();
    }
    
    // First create a user account
    $sender_email = strtolower($sender_first_name . '.' . $sender_last_name . rand(100,999) . '@example.com');
    $random_password = password_hash(uniqid(), PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("INSERT INTO Users (email, password, role, account_status) VALUES (?, ?, 'Customer', 'Active')");
    $stmt->bind_param("ss", $sender_email, $random_password);
    $stmt->execute();
    $sender_id = $conn->insert_id;
    
    // Then create the customer record
    $stmt = $conn->prepare("INSERT INTO Customer (user_id, first_name, last_name, phone, street_address, city, state, postal_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssssss", $sender_id, $sender_first_name, $sender_last_name, $sender_phone, $sender_street, $sender_city, $sender_state, $sender_zip);
    $stmt->execute();
    
    // Get the sender data for display
    $sender = [
        'first_name' => $sender_first_name,
        'last_name' => $sender_last_name,
        'phone' => $sender_phone,
        'street_address' => $sender_street,
        'city' => $sender_city,
        'state' => $sender_state,
        'postal_code' => $sender_zip
    ];
} else {
    // Get existing sender data
    $stmt = $conn->prepare("SELECT first_name, last_name, phone, street_address, city, state, postal_code FROM Customer WHERE user_id = ?");
    $stmt->bind_param("i", $sender_id);
    $stmt->execute();
    $sender_result = $stmt->get_result();
    if ($sender_result->num_rows > 0) {
        $sender = $sender_result->fetch_assoc();
    } else {
        $_SESSION['error_message'] = "Sender not found. Please go back and try again.";
        header("Location: new_package.php");
        exit();
    }
}

// Create new receiver if needed
if (empty($receiver_id)) {
    if (empty($receiver_first_name) || empty($receiver_last_name)) {
        $_SESSION['error_message'] = "Missing required receiver information. Please go back and try again.";
        header("Location: new_package.php");
        exit();
    }
    
    // First create a user account
    $receiver_email = strtolower($receiver_first_name . '.' . $receiver_last_name . rand(100,999) . '@example.com');
    $random_password = password_hash(uniqid(), PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("INSERT INTO Users (email, password, role, account_status) VALUES (?, ?, 'Customer', 'Active')");
    $stmt->bind_param("ss", $receiver_email, $random_password);
    $stmt->execute();
    $receiver_id = $conn->insert_id;
    
    // Then create the customer record
    $stmt = $conn->prepare("INSERT INTO Customer (user_id, first_name, last_name, phone, street_address, city, state, postal_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssssss", $receiver_id, $receiver_first_name, $receiver_last_name, $receiver_phone, $receiver_street, $receiver_city, $receiver_state, $receiver_zip);
    $stmt->execute();
    
    // Get the receiver data for display
    $receiver = [
        'first_name' => $receiver_first_name,
        'last_name' => $receiver_last_name,
        'phone' => $receiver_phone,
        'street_address' => $receiver_street,
        'city' => $receiver_city,
        'state' => $receiver_state,
        'postal_code' => $receiver_zip
    ];
} else {
    // Get existing receiver data
    $stmt = $conn->prepare("SELECT first_name, last_name, phone, street_address, city, state, postal_code FROM Customer WHERE user_id = ?");
    $stmt->bind_param("i", $receiver_id);
    $stmt->execute();
    $receiver_result = $stmt->get_result();
    if ($receiver_result->num_rows > 0) {
        $receiver = $receiver_result->fetch_assoc();
    } else {
        $_SESSION['error_message'] = "Receiver not found. Please go back and try again.";
        header("Location: new_package.php");
        exit();
    }
}

// Get facility information
$stmt = $conn->prepare("SELECT city, state, type FROM Facility WHERE facility_id = ?");
$stmt->bind_param("i", $facility_id);
$stmt->execute();
$facility_result = $stmt->get_result();
if ($facility_result->num_rows > 0) {
    $facility = $facility_result->fetch_assoc();
} else {
    $_SESSION['error_message'] = "Facility not found. Please go back and try again.";
    header("Location: new_package.php");
    exit();
}

// Ensure we have valid zip codes for calculation
$dest_zip = $receiver['postal_code'] ?? '';
if (empty($origin_zip) && !empty($sender['postal_code'])) {
    $origin_zip = $sender['postal_code'];
}
if (empty($dest_zip) && !empty($receiver['postal_code'])) {
    $dest_zip = $receiver['postal_code'];
}

// Calculate postage based on package details
$postage_info = calculateDistanceBasedPostage($weight, $size, $origin_zip, $dest_zip, $signature_required, $shipping_speed);
$postage = $postage_info['postage'];
$distance = $postage_info['distance'];
$speed_fee = $postage_info['speed_fee'];
$speed_multiplier = $postage_info['speed_multiplier'];
$delivery_time = $postage_info['delivery_time'];

// Generate invoice number
$invoice_number = 'INV-' . date('Ymd') . '-' . substr($tracking_number, -4);

// Store data in session for payment processing
$_SESSION['package_data'] = [
    'tracking_number' => $tracking_number,
    'weight' => $weight,
    'size' => $size,
    'postage' => $postage,
    'sender_id' => $sender_id,
    'receiver_id' => $receiver_id,
    'facility_id' => $facility_id,
    'signature_required' => $signature_required,
    'shipping_speed' => $shipping_speed,
    'notes' => $notes,
    'invoice_number' => $invoice_number
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Package Confirmation - POSTAL PRO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap');
        * { font-family: 'Open Sans', sans-serif; }
        body { background-color: #f8f9fa; }
        .action-btn { background-color: #004B87; transition: background-color 0.3s; }
        .action-btn:hover { background-color: #003366; }
        .accent-btn { background-color: #DA291C; transition: background-color 0.3s; }
        .accent-btn:hover { background-color: #b52218; }
        .section-title { color: #004B87; }
        .price-item { display: flex; justify-content: space-between; padding: 5px 0; }
    </style>
</head>
<body class="bg-gray-50">
<?php $emp_base = '../'; include '../_employee_nav.php'; ?>

    <div class="container mx-auto px-4 py-8 max-w-4xl mt-20">
        <header class="mb-6">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center">
                <h1 class="text-3xl font-bold section-title">Package Confirmation</h1>
                <div class="mt-4 md:mt-0 bg-white shadow-sm p-4 rounded-lg">
                    <p class="mb-1"><span class="font-semibold">Welcome,</span> <?= htmlspecialchars($name) ?> | <span class="font-medium"><?= htmlspecialchars(isset($employee['role']) ? $employee['role'] : $role) ?></span></p>
                    <p><span class="font-semibold">Facility:</span> <?= htmlspecialchars(isset($employee['facility_name']) ? $employee['facility_name'] : '—') ?></p>
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
                <li><a href="../employee/contact_admin.php" class="text-gray-700 hover:text-[#DA291C] flex items-center">Contact Admin<?php if ($unread_admin_messages > 0): ?> <span class="ml-2 bg-red-600 text-white text-xs px-2 py-1 rounded-full"><?= $unread_admin_messages ?></span><?php endif; ?></a></li>
                <?php endif; ?>
                <?php if (isset($employee) && !empty($employee['role']) && $employee['role'] == 'Clerk'): ?>
                <li><a href="new_package.php" class="text-gray-700 hover:text-[#DA291C] border-b-2 border-[#DA291C] pb-1 font-medium">Create Shipment</a></li>
                <li><a href="../shop/inventory_management.php" class="text-gray-700 hover:text-[#DA291C]">Inventory Management</a></li>
                <li><a href="search.php" class="text-gray-700 hover:text-[#DA291C]">Package Search &amp; Scan</a></li>
                <?php endif; ?>
                <?php if (isset($employee) && !empty($employee['role']) && $employee['role'] == 'Manager'): ?>
                <li><a href="../shop/inventory_management.php" class="text-gray-700 hover:text-[#DA291C]">Inventory Management</a></li>
                <li><a href="../reports/index.php" class="text-gray-700 hover:text-[#DA291C]">Reports</a></li>
                <li><a href="../trips/manage_trips.php" class="text-gray-700 hover:text-[#DA291C]">Manage Trips</a></li>
                <?php endif; ?>
                <?php if (isset($employee) && !empty($employee['role']) && $employee['role'] == 'Clerk'): ?>
                <li><a href="awaiting_pickup.php" class="text-gray-700 hover:text-[#DA291C]">Pickup</a></li>
                <?php endif; ?>
                <?php if ($role != 'Admin'): ?>
                <li><a href="../edit_profile.php" class="text-gray-700 hover:text-[#DA291C]">Edit Profile</a></li>
                <?php endif; ?>
            </ul>
        </nav>

        <div class="bg-gray-50 border-l-4 border-[#004B87] text-gray-700 p-4 mb-6 rounded-lg shadow-md">
            <strong>Please review the package details below.</strong> Then choose a payment method to complete the transaction.
        </div>

        <!-- Package Summary -->
        <section class="bg-white p-6 rounded-lg shadow-md mb-6">
            <div class="grid md:grid-cols-2 gap-6">
                <div>
                    <h4 class="text-xl font-semibold section-title mb-3">Package Information</h4>
                    <p class="mb-2"><span class="font-semibold">Tracking Number:</span> <?= htmlspecialchars($tracking_number) ?></p>
                    <p class="mb-2"><span class="font-semibold">Size:</span> <?= htmlspecialchars($size) ?></p>
                    <p class="mb-2"><span class="font-semibold">Weight:</span> <?= htmlspecialchars($weight) ?> lbs</p>
                    <p class="mb-2"><span class="font-semibold">Signature Required:</span> <?= $signature_required === 'Y' ? 'Yes' : 'No' ?></p>
                    <p class="mb-2"><span class="font-semibold">Processing Facility:</span> <?= htmlspecialchars($facility['city'] . ', ' . $facility['state']) ?></p>
                    <p class="mb-2"><span class="font-semibold">Shipping Speed:</span> <?= htmlspecialchars($shipping_speed) ?></p>
                    <p class="mb-2"><span class="font-semibold">Estimated Delivery:</span> <?= htmlspecialchars($delivery_time) ?></p>
                </div>
                <div>
                    <div class="bg-gray-50 border border-gray-200 p-4 rounded-lg">
                        <h4 class="text-xl font-semibold section-title mb-3">Price Calculation</h4>
                        <div class="price-item">
                            <span>Base Rate (<?= htmlspecialchars($size) ?>):</span>
                            <span>$<?= number_format(getSizeBaseRate($size), 2) ?></span>
                        </div>
                        <div class="price-item">
                            <span>Weight Charge (<?= htmlspecialchars($weight) ?> lbs):</span>
                            <span>$<?= number_format(getWeightRate($weight), 2) ?></span>
                        </div>
                        <div class="price-item">
                            <span>Distance: <?= number_format($distance, 2) ?> miles</span>
                            <span>x<?= getDistanceMultiplier($distance) ?></span>
                        </div>
                        <?php if ($shipping_speed !== 'Standard'): ?>
                        <div class="price-item">
                            <span>Shipping Speed (<?= htmlspecialchars($shipping_speed) ?>):</span>
                            <span>x<?= $speed_multiplier ?></span>
                        </div>
                        <?php if ($speed_fee > 0): ?>
                        <div class="price-item">
                            <span><?= htmlspecialchars($shipping_speed) ?> Fee:</span>
                            <span>$<?= number_format($speed_fee, 2) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                        <?php if ($signature_required === 'Y'): ?>
                        <div class="price-item">
                            <span>Signature Required:</span>
                            <span>$3.50</span>
                        </div>
                        <?php endif; ?>
                        <div class="price-item font-bold border-t-2 border-[#004B87] mt-3 pt-3">
                            <span>Total:</span>
                            <span>$<?= number_format($postage, 2) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Address Information -->
        <section class="bg-white p-6 rounded-lg shadow-md mb-6">
            <h4 class="text-xl font-semibold section-title mb-3">Shipping Details</h4>
            <div class="grid md:grid-cols-2 gap-6">
                <div class="mb-3">
                    <div class="border border-gray-300 p-4 rounded-lg h-full">
                        <h5 class="font-semibold mb-2">From:</h5>
                        <p>
                            <strong><?= htmlspecialchars($sender['first_name'] . ' ' . $sender['last_name']) ?></strong><br>
                            <?= htmlspecialchars($sender['street_address']) ?><br>
                            <?= htmlspecialchars($sender['city'] . ', ' . $sender['state'] . ' ' . $sender['postal_code']) ?><br>
                            Phone: <?= htmlspecialchars($sender['phone']) ?>
                        </p>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="border border-gray-300 p-4 rounded-lg h-full">
                        <h5 class="font-semibold mb-2">To:</h5>
                        <p>
                            <strong><?= htmlspecialchars($receiver['first_name'] . ' ' . $receiver['last_name']) ?></strong><br>
                            <?= htmlspecialchars($receiver['street_address']) ?><br>
                            <?= htmlspecialchars($receiver['city'] . ', ' . $receiver['state'] . ' ' . $receiver['postal_code']) ?><br>
                            Phone: <?= htmlspecialchars($receiver['phone']) ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($notes)): ?>
            <div class="mt-4">
                <h5 class="font-semibold mb-2">Notes:</h5>
                <p><?= nl2br(htmlspecialchars($notes)) ?></p>
            </div>
            <?php endif; ?>
        </section>

        <!-- Payment Processing -->
        <section class="bg-white p-6 rounded-lg shadow-md mb-6">
            <h4 class="text-xl font-semibold section-title mb-3">Payment</h4>
            <p class="text-gray-600 mb-4">Select a payment method to complete this transaction:</p>

            <div class="grid md:grid-cols-3 gap-4 my-6">
                <form action="process_payment.php" method="post">
                    <input type="hidden" name="payment_method" value="Cash">
                    <button type="submit" class="w-full py-4 px-4 bg-green-600 hover:bg-green-700 text-white text-center font-semibold rounded-lg shadow hover:shadow-md transition-colors">Cash</button>
                </form>
                <form action="process_payment.php" method="post">
                    <input type="hidden" name="payment_method" value="Credit Card">
                    <button type="submit" class="w-full py-4 px-4 action-btn text-white text-center font-semibold rounded-lg shadow hover:shadow-md">Credit Card</button>
                </form>
                <form action="process_payment.php" method="post">
                    <input type="hidden" name="payment_method" value="PayPal">
                    <button type="submit" class="w-full py-4 px-4 bg-indigo-600 hover:bg-indigo-700 text-white text-center font-semibold rounded-lg shadow hover:shadow-md transition-colors">PayPal</button>
                </form>
            </div>

            <div class="flex flex-wrap justify-center gap-3 pt-4 border-t border-gray-200">
                <a href="new_package.php" class="px-4 py-2 rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">Back to Package Form</a>
                <a href="cancel_transaction.php" class="px-4 py-2 rounded accent-btn text-white hover:opacity-90">Cancel Transaction</a>
            </div>
        </section>
    </div>
</body>
</html>
