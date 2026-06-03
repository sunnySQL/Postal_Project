<?php
session_start();
require_once '../db_connect.php';
require_once '../functions.php';

// Check if user is logged in and is an employee
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || 
    ($_SESSION['role'] != 'Employee' && $_SESSION['role'] != 'Admin')) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get employee info including facility
$stmt = $conn->prepare("SELECT e.*, f.facility_id, f.city FROM Employee e 
                      JOIN Facility f ON e.facility_id = f.facility_id 
                      WHERE e.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$employee = $stmt->get_result()->fetch_assoc();

// Make sure the employee is authorized (currently just Clerks and Admins)
if ($employee['role'] != 'Clerk' && $_SESSION['role'] != 'Admin') {
    header("Location: ../employee_dashboard.php");
    exit();
}

$facility_id = $employee['facility_id'];
$facility_city = $employee['city'];

// Check if tracking number is provided
if (!isset($_GET['tracking']) || empty($_GET['tracking'])) {
    header("Location: ../employee_dashboard.php?error=missing_tracking");
    exit();
}

$tracking = $_GET['tracking'];
$item_type = $_GET['item_type'] ?? 'package';
$transaction_id = $_GET['transaction_id'] ?? 0;

if ($item_type === 'package') {
    // Verify package exists and is at this facility
    $check_stmt = $conn->prepare("SELECT p.*, c.email, c.phone, c.first_name, c.last_name 
                                FROM Package p
                                JOIN Customer c ON p.receiver_id = c.user_id
                                WHERE p.tracking_number = ? AND p.facility_id = ? AND p.status = 'Ready for Pickup'");
    $check_stmt->bind_param("si", $tracking, $facility_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows == 0) {
        header("Location: ../employee_dashboard.php?error=invalid_package");
        exit();
    }

    $package = $result->fetch_assoc();

    // In a real system, this would send an email or SMS notification
    $message = "Dear " . $package['first_name'] . " " . $package['last_name'] . ",\n\n";
    $message .= "Your package with tracking number " . $package['tracking_number'] . " is ready for pickup at our " . $facility_city . " facility.\n";
    $message .= "Please bring a valid ID to collect your package.\n\n";
    $message .= "Thank you for using our services.";

    // Log this notification in tracking history
    $history_stmt = $conn->prepare("INSERT INTO Tracking_History (tracking_number, employee_id, facility_id, action, notes) 
                                  VALUES (?, ?, ?, 'Customer Notified', ?)");
    $history_stmt->bind_param("siis", $tracking, $user_id, $facility_id, $message);

    if ($history_stmt->execute()) {
        header("Location: ../employee_dashboard.php?success=notified&tracking=$tracking");
        exit();
    } else {
        header("Location: ../employee_dashboard.php?error=notification_failed&tracking=$tracking");
        exit();
    }
} else {
    // Verify shop transaction exists and belongs to correct facility
    $check_stmt = $conn->prepare("SELECT t.*, c.email, c.phone, c.first_name, c.last_name, s.shop_name 
                               FROM Shop_Transaction t
                               JOIN Customer c ON t.user_id = c.user_id
                               JOIN Shop s ON t.shop_id = s.shop_id
                               WHERE t.transaction_id = ? AND s.facility_id = ? AND t.transaction_status = 'Completed'");
    $check_stmt->bind_param("ii", $transaction_id, $facility_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows == 0) {
        header("Location: ../employee_dashboard.php?error=invalid_transaction");
        exit();
    }

    $transaction = $result->fetch_assoc();

    $message = "Dear " . $transaction['first_name'] . " " . $transaction['last_name'] . ",\n\n";
    $message .= "Your shop purchase (Order #" . $transaction['transaction_id'] . ") from " . $transaction['shop_name'] . " is ready for pickup at our " . $facility_city . " facility.\n";
    $message .= "Please bring a valid ID to collect your items.\n\n";
    $message .= "Thank you for shopping with us.";

    // Check table structure to determine the update approach
    $columnsResult = $conn->query("SHOW COLUMNS FROM Shop_Transaction");
    $columns = [];
    while ($row = $columnsResult->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    
    if (in_array('notification_message', $columns)) {
        $update_stmt = $conn->prepare("UPDATE Shop_Transaction SET notification_message = ? WHERE transaction_id = ?");
        $update_stmt->bind_param("si", $message, $transaction_id);
    } else {
        $update_stmt = $conn->prepare("UPDATE Shop_Transaction SET transaction_date = transaction_date WHERE transaction_id = ?");
        $update_stmt->bind_param("i", $transaction_id);
    }

    if ($update_stmt->execute()) {
        header("Location: ../employee_dashboard.php?success=notified&transaction=$transaction_id");
        exit();
    } else {
        header("Location: ../employee_dashboard.php?error=notification_failed&transaction=$transaction_id");
        exit();
    }
}
