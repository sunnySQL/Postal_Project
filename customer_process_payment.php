<?php
session_start();
require 'functions.php';
require 'db_connect.php';

// Check if customer is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'Customer') {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: customer_new_package.php");
    exit();
}

if (!isset($_SESSION['package_data'])) {
    $_SESSION['error_message'] = "Package data not found. Please create a new package.";
    header("Location: customer_new_package.php");
    exit();
}

$payment_method = $_POST['payment_method'] ?? null;
if (empty($payment_method)) {
    $_SESSION['error_message'] = "Please select a payment method.";
    header("Location: customer_package_confirmation.php");
    exit();
}

$data = $_SESSION['package_data'];
$tracking_number = trim($data['tracking_number']);
$weight = (float) $data['weight'];
$size = trim($data['size']);
$postage = (float) $data['postage'];
$sender_id = (int) $data['sender_id'];
$receiver_id = (int) $data['receiver_id'];
$origin_zip = $data['origin_zip'] ?? '';
$signature_required = (!empty($data['signature_required']) && strtoupper($data['signature_required']) === 'Y') ? 'Y' : 'N';

$allowed_speeds = ['Economy', 'Standard', 'Express'];
$shipping_speed_raw = trim($data['shipping_speed'] ?? '');
$shipping_speed = ucfirst(strtolower($shipping_speed_raw));
$shipping_speed = in_array($shipping_speed, $allowed_speeds) ? $shipping_speed : 'Standard';

$notes = $data['notes'] ?? '';
$invoice_number = trim($data['invoice_number']);

// Validate before inserting
if (!in_array($shipping_speed, $allowed_speeds)) {
    die("❌ Invalid ENUM value for shipping_speed: '$shipping_speed'");
}

// Facility lookup
$facility_id = 1;
$facilityStmt = $conn->prepare("SELECT facility_id FROM Facility WHERE postal_code = ? LIMIT 1");
$facilityStmt->bind_param("s", $origin_zip);
$facilityStmt->execute();
$facilityResult = $facilityStmt->get_result();
if ($facilityResult->num_rows > 0) {
    $facility_id = (int) $facilityResult->fetch_assoc()['facility_id'];
}

$conn->begin_transaction();
try {
    // Insert into Package
    $stmt = $conn->prepare("INSERT INTO Package (
        tracking_number, weight, size, postage, signature_required,
        shipping_speed, status, timestamp_created, sender_id, receiver_id, facility_id
    ) VALUES (?, ?, ?, ?, ?, ?, 'Processing', CURRENT_TIMESTAMP, ?, ?, ?)");

    if (!$stmt) throw new Exception("Prepare failed for Package: " . $conn->error);

    $stmt->bind_param(
        "sdssssiii",
        $tracking_number,
        $weight,
        $size,
        $postage,
        $signature_required,
        $shipping_speed,
        $sender_id,
        $receiver_id,
        $facility_id
    );

    if (!$stmt->execute()) {
        throw new Exception("Execute failed for Package: " . $stmt->error);
    }

    // Insert into Package_Payment
    $stmt = $conn->prepare("INSERT INTO Package_Payment (
        user_id, package_id, amount, payment_method, transaction_status, invoice_number, payment_date, facility_id
    ) VALUES (?, ?, ?, ?, 'Completed', ?, CURRENT_TIMESTAMP, ?)");

    if (!$stmt) throw new Exception("Prepare failed for Package_Payment: " . $conn->error);

    $stmt->bind_param(
        "issssi",
        $sender_id,
        $tracking_number,
        $postage,
        $payment_method,
        $invoice_number,
        $facility_id
    );

    if (!$stmt->execute()) {
        throw new Exception("Execute failed for Package_Payment: " . $stmt->error);
    }

    // Insert into Tracking_History
    $stmt = $conn->prepare("SELECT CONCAT(city, ', ', state, ' - ', type) AS location FROM Facility WHERE facility_id = ?");
    $stmt->bind_param("i", $facility_id);
    $stmt->execute();
    $location = $stmt->get_result()->fetch_assoc()['location'] ?? 'Unknown Facility';

    $action = "Created by Customer";
    $stmt = $conn->prepare("INSERT INTO Tracking_History (
        tracking_number, location, status, employee_id, action, facility_id
    ) VALUES (?, ?, 'Processing', NULL, ?, ?)");

    if (!$stmt) throw new Exception("Prepare failed for Tracking_History: " . $conn->error);

    $stmt->bind_param("sssi", $tracking_number, $location, $action, $facility_id);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed for Tracking_History: " . $stmt->error);
    }

    $conn->commit();
    unset($_SESSION['package_data']);

    $_SESSION['success_message'] = "Your package #$tracking_number was processed successfully. A payment of $" . number_format($postage, 2) . " has been recorded.";
    header("Location: customer_dashboard.php?order=received&type=package&tracking=$tracking_number");
    exit();

} catch (Exception $e) {
    $conn->rollback();
    echo "<h2 style='color:red;'>Payment Failed</h2>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<pre>Shipping Speed Sent: '$shipping_speed'</pre>";
    exit();
}
?>
