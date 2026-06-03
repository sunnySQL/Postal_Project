<?php
session_start();
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Check if tracking number is provided
if (!isset($_GET['tracking_number'])) {
    $_SESSION['error_message'] = "No tracking number provided.";
    header("Location: new_package.php");
    exit();
}

$tracking_number = $_GET['tracking_number'];

// Get package details from database
$stmt = $conn->prepare("
    SELECT p.*, 
           s.first_name as sender_first_name, s.last_name as sender_last_name, 
           s.street_address as sender_street, s.city as sender_city, 
           s.state as sender_state, s.postal_code as sender_zip,
           r.first_name as receiver_first_name, r.last_name as receiver_last_name,
           r.street_address as receiver_street, r.city as receiver_city,
           r.state as receiver_state, r.postal_code as receiver_zip
    FROM Package p
    LEFT JOIN Customer s ON p.sender_id = s.customer_id
    LEFT JOIN Customer r ON p.receiver_id = r.customer_id
    WHERE p.tracking_number = ?
");

$stmt->bind_param("s", $tracking_number);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "Package not found.";
    header("Location: new_package.php");
    exit();
}

$package = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Shipping Label - <?= htmlspecialchars($tracking_number) ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        @media print {
            .no-print {
                display: none;
            }
            .shipping-label {
                border: 2px solid #000;
                padding: 20px;
                margin: 0;
            }
        }
        .shipping-label {
            border: 2px solid #000;
            padding: 20px;
            margin: 20px;
            max-width: 800px;
            margin: 20px auto;
        }
        .barcode {
            font-family: monospace;
            font-size: 24px;
            text-align: center;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="no-print text-center my-4">
            <h1>Shipping Label</h1>
            <p>Tracking Number: <?= htmlspecialchars($tracking_number) ?></p>
            <button onclick="window.print()" class="btn btn-primary">Print Label</button>
            <a href="new_package.php" class="btn btn-secondary ml-2">Back to New Package</a>
        </div>

        <div class="shipping-label">
            <div class="row">
                <div class="col-6">
                    <h4>From:</h4>
                    <p>
                        <?= htmlspecialchars($package['sender_first_name'] . ' ' . $package['sender_last_name']) ?><br>
                        <?= htmlspecialchars($package['sender_street']) ?><br>
                        <?= htmlspecialchars($package['sender_city'] . ', ' . $package['sender_state'] . ' ' . $package['sender_zip']) ?>
                    </p>
                </div>
                <div class="col-6">
                    <h4>To:</h4>
                    <p>
                        <?= htmlspecialchars($package['receiver_first_name'] . ' ' . $package['receiver_last_name']) ?><br>
                        <?= htmlspecialchars($package['receiver_street']) ?><br>
                        <?= htmlspecialchars($package['receiver_city'] . ', ' . $package['receiver_state'] . ' ' . $package['receiver_zip']) ?>
                    </p>
                </div>
            </div>

            <div class="barcode">
                ||||| |||| ||| || ||||
            </div>

            <div class="row mt-4">
                <div class="col-6">
                    <p><strong>Package Size:</strong> <?= htmlspecialchars($package['size']) ?></p>
                    <p><strong>Weight:</strong> <?= htmlspecialchars($package['weight']) ?> lbs</p>
                </div>
                <div class="col-6">
                    <p><strong>Shipping Speed:</strong> <?= htmlspecialchars($package['shipping_speed']) ?></p>
                    <p><strong>Date:</strong> <?= date('Y-m-d') ?></p>
                </div>
            </div>

            <div class="text-center mt-4">
                <p class="mb-0">Tracking Number: <?= htmlspecialchars($tracking_number) ?></p>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html> 