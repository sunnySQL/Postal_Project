<?php
session_start();
require_once 'db_connect.php'; // your DB connection

$tracking_number = $_GET['tracking_number'] ?? '';
$current_status = '';
$statuses = ['Shipped', 'In Transit', 'Delivered'];

if ($tracking_number) {
    $stmt = $conn->prepare("SELECT status FROM Package WHERE tracking_number = ?");
    $stmt->bind_param("s", $tracking_number);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $shipment = $result->fetch_assoc();
        $current_status = $shipment['status'];
    } else {
        $error = "Tracking number not found.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Track Your Package</title>
    <style>
        body {
            font-family: 'Bad Script', cursive;
            background-color: #F7F4EB;
            padding: 40px;
        }

        .tracking-wrapper {
            max-width: 600px;
            margin: auto;
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .tracking-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .tracking-step {
            display: flex;
            align-items: center;
            margin-bottom: 25px;
            position: relative;
        }

        .circle {
            width: 20px;
            height: 20px;
            background-color: #ccc;
            border-radius: 50%;
            z-index: 2;
        }

        .active .circle {
            background-color: #FF8D8D;
        }

        .label {
            margin-left: 15px;
            font-size: 18px;
            color: black;
        }

        .line {
            position: absolute;
            width: 2px;
            background-color: #ccc;
            height: 100%;
            top: 20px;
            left: 9px;
            z-index: 1;
        }

        .last .line {
            display: none;
        }

        .error {
            text-align: center;
            color: red;
            font-size: 1.2em;
        }
    </style>
</head>
<body>

<div class="tracking-wrapper">
    <div class="tracking-header">
        <h2>Tracking Number: <?= htmlspecialchars($tracking_number ?: 'N/A') ?></h2>
        <p>Status Progress</p>
    </div>

    <?php if (isset($error)): ?>
        <p class="error"><?= $error ?></p>
    <?php else: ?>
        <?php foreach ($statuses as $index => $status): 
            $is_active = array_search($current_status, $statuses) >= $index;
            $is_last = $index === count($statuses) - 1;
        ?>
            <div class="tracking-step <?= $is_active ? 'active' : '' ?> <?= $is_last ? 'last' : '' ?>">
                <div class="circle"></div>
                <?php if (!$is_last): ?><div class="line"></div><?php endif; ?>
                <div class="label"><?= $status ?></div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</body>
</html>
