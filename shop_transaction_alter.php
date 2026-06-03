<?php
/**
 * One-time migration script — Admin access required.
 * Run once from the browser while logged in as Admin, then delete this file.
 */
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Admin') {
    http_response_code(403);
    die('Access denied.');
}

header('Content-Type: text/plain; charset=utf-8');

echo "Updating Shop_Transaction table...\n";

$sql_statements = [
    "ALTER TABLE Shop_Transaction 
     ADD COLUMN IF NOT EXISTS pickup_status ENUM('Ready for Pickup', 'Picked Up', 'Delivered') 
     DEFAULT 'Ready for Pickup'",

    "ALTER TABLE Shop_Transaction 
     ADD COLUMN IF NOT EXISTS pickup_date DATETIME NULL",

    "ALTER TABLE Shop_Transaction 
     ADD COLUMN IF NOT EXISTS notification_sent TINYINT(1) DEFAULT 0",

    "ALTER TABLE Shop_Transaction 
     ADD COLUMN IF NOT EXISTS notification_date DATETIME NULL"
];

$success = true;
foreach ($sql_statements as $sql) {
    echo "Executing: " . $sql . "\n";
    if (!$conn->query($sql)) {
        error_log('shop_transaction_alter.php: ' . $conn->error);
        echo "Error: migration step failed.\n";
        $success = false;
    }
}

if ($success) {
    echo "Shop_Transaction table successfully updated!\n";

    $update_sql = "UPDATE Shop_Transaction SET pickup_status = 'Ready for Pickup' WHERE pickup_status IS NULL";
    if ($conn->query($update_sql)) {
        echo "Existing transactions set to 'Ready for Pickup'\n";
    } else {
        error_log('shop_transaction_alter.php update: ' . $conn->error);
        echo "Error updating existing transactions.\n";
    }
} else {
    echo "There were errors updating the table.\n";
}

echo "Done.\n";
