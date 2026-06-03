<?php
session_start();
require_once 'functions.php';
require_once 'db_connect.php';

// Enable debugging
// Ensure logged-in customer
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'Customer') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id']; // This is the sender!
$name = $_SESSION['name'];       // For display if needed

// Check if form submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: customer_sendpackage.php");
    exit();
}

// Get sender details from DB (customer is always sender)
$stmt = $conn->prepare("SELECT * FROM Customer WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$sender = $stmt->get_result()->fetch_assoc();
if (!$sender) {
    die("Sender data not found. Please contact support.");
}

// Get form data
$tracking_number = $_POST['tracking_number'] ?? '';
$size = $_POST['size'] ?? '';
$weight = $_POST['weight'] ?? '';
$origin_zip = $_POST['origin_zip'] ?? $sender['postal_code'];
$signature_required = isset($_POST['signature_required']) ? 'Y' : 'N';
$shipping_speed = $_POST['shipping_speed'] ?? 'Standard';
$notes = $_POST['notes'] ?? '';

// Handle receiver
$receiver_id = $_POST['receiver_select'] ?? '';
if (empty($receiver_id)) {
    // New receiver
    $receiver_first_name = $_POST['receiver_first_name'] ?? '';
    $receiver_last_name = $_POST['receiver_last_name'] ?? '';
    $receiver_phone = $_POST['receiver_phone'] ?? '';
    $receiver_street = $_POST['receiver_street'] ?? '';
    $receiver_city = $_POST['receiver_city'] ?? '';
    $receiver_state = $_POST['receiver_state'] ?? '';
    $receiver_zip = $_POST['receiver_zip'] ?? '';

    // Insert user + customer
    $email = strtolower($receiver_first_name . '.' . $receiver_last_name . rand(100,999) . '@example.com');
    $random_password = password_hash(uniqid(), PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO Users (email, password, role, account_status) VALUES (?, ?, 'Customer', 'Active')");
    $stmt->bind_param("ss", $email, $random_password);
    $stmt->execute();
    $receiver_id = $conn->insert_id;

    $stmt = $conn->prepare("INSERT INTO Customer (user_id, first_name, last_name, phone, street_address, city, state, postal_code)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssssss", $receiver_id, $receiver_first_name, $receiver_last_name, $receiver_phone,
                      $receiver_street, $receiver_city, $receiver_state, $receiver_zip);
    $stmt->execute();

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
    // Existing receiver
    $stmt = $conn->prepare("SELECT first_name, last_name, phone, street_address, city, state, postal_code FROM Customer WHERE user_id = ?");
    $stmt->bind_param("i", $receiver_id);
    $stmt->execute();
    $receiver = $stmt->get_result()->fetch_assoc();
}

// Calculate postage
$dest_zip = $receiver['postal_code'];
$postage_info = calculateDistanceBasedPostage($weight, $size, $origin_zip, $dest_zip, $signature_required, $shipping_speed);
$postage = $postage_info['postage'];
$distance = $postage_info['distance'];
$speed_fee = $postage_info['speed_fee'];
$speed_multiplier = $postage_info['speed_multiplier'];
$delivery_time = $postage_info['delivery_time'];

// Invoice
$invoice_number = 'INV-' . date('Ymd') . '-' . substr($tracking_number, -4);
$signature_required = isset($_POST['signature_required']) && $_POST['signature_required'] === 'Y' ? 'Y' : 'N';
$allowed_speeds = ['Economy', 'Standard', 'Express'];
$shipping_speed = in_array($_POST['shipping_speed'], $allowed_speeds) ? $_POST['shipping_speed'] : 'Standard';


$_SESSION['package_data'] = [
    'tracking_number'      => $tracking_number,
    'weight'               => $weight,
    'size'                 => $size,
    'postage'              => $postage,
    'sender_id'            => $user_id,
    'receiver_id'          => $receiver_id,
    'signature_required'   => $signature_required, 
    'shipping_speed'       => $shipping_speed,
    'notes'                => $notes,
    'invoice_number'       => $invoice_number,
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Package Confirmation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
       
        .price-item {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8 max-w-4xl">
        <h1 class="text-3xl font-bold mb-6">Package Confirmation</h1>
        
        <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-6">
            <strong>Please review the package details below</strong>. After confirmation, you'll need to process the payment.
        </div>
        
        <!-- Package Summary -->
        <div class="bg-gray-100 p-6 rounded-lg mb-6">
            <div class="grid md:grid-cols-2 gap-6">
                <div>
                    <h4 class="text-xl font-semibold mb-3">Package Information</h4>
                    <p class="mb-2"><span class="font-semibold">Tracking Number:</span> <?= htmlspecialchars($tracking_number) ?></p>
                    <p class="mb-2"><span class="font-semibold">Size:</span> <?= htmlspecialchars($size) ?></p>
                    <p class="mb-2"><span class="font-semibold">Weight:</span> <?= htmlspecialchars($weight) ?> lbs</p>
                    <p class="mb-2"><span class="font-semibold">Signature Required:</span> <?= $signature_required === 'Y' ? 'Yes' : 'No' ?></p>
                    <p class="mb-2"><span class="font-semibold">Shipping Speed:</span> <?= htmlspecialchars($shipping_speed) ?></p>
                    <p class="mb-2"><span class="font-semibold">Estimated Delivery:</span> <?= htmlspecialchars($delivery_time) ?></p>
                </div>
                <div>
                    <div class="bg-green-50 p-4 rounded-lg">
                        <h4 class="text-xl font-semibold mb-3">Price Calculation</h4>
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
                        <div class="price-item font-bold border-t border-green-500 mt-3 pt-3">
                            <span>Total:</span>
                            <span>$<?= number_format($postage, 2) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Address Information -->
        <div class="bg-gray-100 p-6 rounded-lg mb-6">
            <h4 class="text-xl font-semibold mb-3">Shipping Details</h4>
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
        </div>
        
        <!-- Payment Processing: Simple version with direct, prominent buttons -->
        <div class="bg-gray-100 p-6 rounded-lg mb-6">
            <h4 class="text-xl font-semibold mb-3">Payment Processing</h4>
            <p>Select a payment method to complete this transaction:</p>
            
            <div class="grid md:grid-cols-3 gap-4 my-6">
                <div>
                    <form action="customer_process_payment.php" method="post">
                        <input type="hidden" name="payment_method" value="Cash">
                        <button type="submit" class="w-full py-5 px-4 bg-green-600 hover:bg-green-700 text-white text-center font-bold rounded text-lg">CASH PAYMENT</button>
                    </form>
                </div>
                <div>
                    <form action="customer_process_payment.php" method="post">
                        <input type="hidden" name="payment_method" value="Credit Card">
                        <button type="submit" class="w-full py-5 px-4 bg-blue-600 hover:bg-blue-700 text-white text-center font-bold rounded text-lg">CREDIT CARD</button>
                    </form>
                </div>
                <div>
                    <form action="customer_process_payment.php" method="post">
                        <input type="hidden" name="payment_method" value="PayPal">
                        <button type="submit" class="w-full py-5 px-4 bg-indigo-600 hover:bg-indigo-700 text-white text-center font-bold rounded text-lg">PAYPAL</button>
                    </form>
                </div>
            </div>
            
            <div class="flex justify-center mt-6">
                <a href="new_package.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded mr-2">Back to Package Form</a>
                <a href="cancel_transaction.php" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded">Cancel Transaction</a>
            </div>
        </div>
    </div>
</body>
</html>
