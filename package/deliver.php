<?php
// Enable error reporting
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
$stmt = $conn->prepare("SELECT e.*, f.facility_id FROM Employee e 
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
    $check_stmt = $conn->prepare("SELECT * FROM Package WHERE tracking_number = ? AND facility_id = ? AND status = 'Ready for Pickup'");
    $check_stmt->bind_param("si", $tracking, $facility_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows == 0) {
        header("Location: ../employee_dashboard.php?error=invalid_package");
        exit();
    }

    // Mark package as delivered
    $update_stmt = $conn->prepare("UPDATE Package SET status = 'Delivered' WHERE tracking_number = ?");
    $update_stmt->bind_param("s", $tracking);

    if ($update_stmt->execute()) {
        // Add tracking history entry
        $history_stmt = $conn->prepare("INSERT INTO Tracking_History (tracking_number, employee_id, facility_id, action, status) 
                                VALUES (?, ?, ?, 'Delivered to Customer', 'Delivered')");
        $history_stmt->bind_param("sii", $tracking, $user_id, $facility_id);
        $history_stmt->execute();
        
        header("Location: ../employee_dashboard.php?success=delivered&tracking=$tracking");
        exit();
    } else {
        header("Location: ../employee_dashboard.php?error=update_failed&tracking=$tracking");
        exit();
    }
} else {
    // Verify shop transaction exists and is at this facility
    $check_stmt = $conn->prepare("SELECT t.* FROM Shop_Transaction t 
                               JOIN Shop s ON t.shop_id = s.shop_id
                               WHERE t.transaction_id = ? AND s.facility_id = ? AND t.transaction_status = 'Completed'");
    $check_stmt->bind_param("ii", $transaction_id, $facility_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows == 0) {
        header("Location: ../employee_dashboard.php?error=invalid_transaction");
        exit();
    }

    // Mark transaction as picked up
    $update_stmt = $conn->prepare("UPDATE Shop_Transaction SET transaction_status = 'Picked Up' WHERE transaction_id = ?");
    $update_stmt->bind_param("i", $transaction_id);

    if ($update_stmt->execute()) {
        header("Location: ../employee_dashboard.php?success=pickedup&transaction=$transaction_id");
        exit();
    } else {
        header("Location: ../employee_dashboard.php?error=update_failed&transaction=$transaction_id");
        exit();
    }
}
?> 