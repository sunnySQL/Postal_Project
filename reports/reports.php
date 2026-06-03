<?php
require_once '../db_connect.php';
require_once '../functions.php';
session_start();


if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || 
    ($_SESSION['role'] != 'Employee' && $_SESSION['role'] != 'Admin')) {
    header("Location: ../login.php");
    exit();
}
$query = "SELECT `item_id`, `name` FROM `Items` ORDER BY `name` ASC";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Report Filter</title>
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: #f4f4f4;
        }
        
        .reports-container {
            display: flex;
            gap: 20px;
            width: 100%;
            max-width: 1200px;
        }
        .filter-container {
            max-width: 1200px;
            margin: auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .filter-container form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
    </style>
</head>
<body>
    <div class="reports-container">
        <div class="filter-container">
            <h1>Sales Report</h1>
            <form method="GET" action="sales_report.php">
                <label for="start_date">Start Date:</label>
                <input type="date" id="start_date" name="start_date" value="<?= date('Y-m-d', strtotime('-7 days')) ?>" required>

                <label for="end_date">End Date:</label>
                <input type="date" id="end_date" name="end_date" value="<?= date('Y-m-d') ?>" required>

                <label for="item_name">Item Name (Optional):</label>
                <select id="item_name" name="item_name">
                    <option value="">Select Item</option>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <option value="<?= $row['name'] ?>"><?= htmlspecialchars($row['name']) ?></option>
                    <?php endwhile; ?>
                </select>
                
                <!--
                <label for="facility_id">Facility ID (Optional):</label>
                <input type="number" id="facility_id" name="facility_id" placeholder="Enter facility ID">

                <label for="shop_id">Shop ID (Optional):</label>
                <input type="number" id="shop_id" name="shop_id" placeholder="Enter shop ID">
                -->

                <button type="submit">Generate Report</button>
            </form>
        </div>
        <div class="filter-container">
            <h1>Throughput Report</h1>
            <form method="GET" action="throughput_report.php">
                <label for="start_date">Start Date:</label>
                <input type="date" id="start_date" name="start_date" value="<?= date('Y-m-d', strtotime('-7 days')) ?>" required>

                <label for="end_date">End Date:</label>
                <input type="date" id="end_date" name="end_date" value="<?= date('Y-m-d') ?>" required>

                <button type="submit">Generate Report</button>
        </div>
    </div>
</body>
</html>