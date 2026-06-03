<?php
session_start();
require_once 'functions.php';
require_once 'db_connect.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'Customer') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$name = $_SESSION['name'];

// Generate tracking number
$tracking_prefix = 'PS' . date('Ymd');
$tracking_query = "SELECT tracking_number FROM Package 
                  WHERE tracking_number LIKE '{$tracking_prefix}%' 
                  ORDER BY tracking_number DESC LIMIT 1";
$result = $conn->query($tracking_query);
if ($result->num_rows > 0) {
    $last_tracking = $result->fetch_assoc()['tracking_number'];
    $last_number = intval(substr($last_tracking, -4));
    $tracking_number = $tracking_prefix . str_pad($last_number + 1, 4, '0', STR_PAD_LEFT);
} else {
    $tracking_number = $tracking_prefix . '0001';
}

// Package size options
$sizes = ['Small Envelope', 'Medium Envelope', 'Large Envelope', 'Small Box', 'Medium Box', 'Large Box', 'Extra Large Box'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send a Package</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap');
        
        * {
            font-family: 'Open Sans', sans-serif;
        }
        
        body {
            background-color: #f8f9fa;
        }
        
        .top-nav {
            background: #004B87;
            color: white;
        }
        
        .action-btn {
            background-color: #004B87;
            transition: background-color 0.3s;
        }
        
        .action-btn:hover {
            background-color: #003366;
        }
        
        .accent-btn {
            background-color: #DA291C;
            transition: background-color 0.3s;
        }
        
        .accent-btn:hover {
            background-color: #b52218;
        }
        
        .section-title {
            color: #004B87;
        }
        
        .required-field::after {
            content: " *";
            color: red;
        }
    </style>
</head>
<body class="bg-gray-50">
    <nav class="top-nav fixed w-full top-0 left-0 z-50 shadow-md px-4 py-4">
        <div class="container mx-auto flex justify-between items-center">
            <a href="index.php" class="text-white font-bold text-2xl">POSTAL PRO</a>
            <ul class="flex space-x-6">
                <li><a href="index.php" class="text-white hover:text-gray-200">Home</a></li>
                <li><a href="customer_dashboard.php" class="text-white hover:text-gray-200">Dashboard</a></li>
                <li><a href="shop.php" class="text-white hover:text-gray-200">Shop</a></li>
                <li><a href="logout.php" class="text-white hover:text-gray-200">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8 max-w-4xl mt-20">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold section-title">Send a Package</h1>
            <a href="customer_dashboard.php" class="text-blue-600 hover:text-blue-800">
                <i class="fas fa-arrow-left mr-1"></i> Back to Dashboard
            </a>
        </div>

        <!-- Current tracking number info -->
        <div class="bg-gray-100 p-4 border-l-4 border-blue-500 mb-6">
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <p class="mb-1"><span class="font-semibold">Tracking Number:</span> <?= htmlspecialchars($tracking_number) ?></p>
                </div>
                <div>
                    <p class="mb-1"><span class="font-semibold">Date:</span> <?= date('Y-m-d H:i') ?></p>
                </div>
            </div>
        </div>

        <form action="customer_package_confirmation.php" method="post" id="packageForm">
            <!-- Hidden fields -->
            <input type="hidden" name="tracking_number" value="<?= htmlspecialchars($tracking_number) ?>">
            <input type="hidden" name="sender_id" value="<?= $user_id ?>">

            <!-- Receiver Info -->
            <div class="bg-white p-6 shadow rounded-lg mb-6">
                <h2 class="text-xl font-semibold mb-4 section-title">Receiver Information</h2>
                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="required-field block mb-2">First Name</label>
                        <input type="text" name="receiver_first_name" class="w-full border border-gray-300 px-3 py-2 rounded" required>
                    </div>
                    <div>
                        <label class="required-field block mb-2">Last Name</label>
                        <input type="text" name="receiver_last_name" class="w-full border border-gray-300 px-3 py-2 rounded" required>
                    </div>
                    <div class="md:col-span-2">
                        <label class="required-field block mb-2">Phone</label>
                        <input type="tel" name="receiver_phone" class="w-full border border-gray-300 px-3 py-2 rounded" required placeholder="123-456-7890">
                    </div>
                    <div class="md:col-span-2">
                        <label class="required-field block mb-2">Street Address</label>
                        <input type="text" name="receiver_street" class="w-full border border-gray-300 px-3 py-2 rounded" required>
                    </div>
                    <div>
                        <label class="required-field block mb-2">City</label>
                        <input type="text" name="receiver_city" class="w-full border border-gray-300 px-3 py-2 rounded" required>
                    </div>
                    <div>
                        <label class="required-field block mb-2">State</label>
                        <input type="text" name="receiver_state" maxlength="2" class="w-full border border-gray-300 px-3 py-2 rounded" required>
                    </div>
                    <div class="md:col-span-2">
                        <label class="required-field block mb-2">ZIP Code</label>
                        <input type="text" name="receiver_zip" pattern="[0-9]{5}" class="w-full border border-gray-300 px-3 py-2 rounded" required>
                    </div>
                </div>
            </div>

            <!-- Package Info -->
            <div class="bg-white p-6 shadow rounded-lg mb-6">
                <h2 class="text-xl font-semibold mb-4 section-title">Package Details</h2>
                <div class="mb-4">
                    <label class="required-field block mb-2">Package Size</label>
                    <select name="size" class="w-full border border-gray-300 px-3 py-2 rounded" required>
                        <option value="">-- Select Size --</option>
                        <?php foreach ($sizes as $size): ?>
                            <option value="<?= htmlspecialchars($size) ?>"><?= htmlspecialchars($size) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="required-field block mb-2">Weight (lbs)</label>
                    <input type="number" name="weight" step="0.1" min="0.1" class="w-full border border-gray-300 px-3 py-2 rounded" required>
                </div>
                <div class="mb-4">
                    <label class="required-field block mb-2">Shipping Speed</label>
                    <select name="shipping_speed" class="w-full border border-gray-300 px-3 py-2 rounded" required>
                        <option value="Economy">Economy (5–7 days) - 20% Discount</option>
                        <option value="Standard" selected>Standard (3–5 days)</option>
                        <option value="Express">Express (1–2 days) - 70% Premium</option>
                    </select>
                </div>
                <div class="mb-4">
                    <div class="flex items-center">
                        <input type="checkbox" id="signature_required" name="signature_required" value="Y" class="h-4 w-4 text-blue-600">
                        <label for="signature_required" class="ml-2">Signature Required ($3.50 fee)</label>
                    </div>
                </div>
            </div>

            <!-- Notes -->
            <div class="bg-white p-6 shadow rounded-lg mb-6">
                <h2 class="text-xl font-semibold mb-4 section-title">Additional Notes</h2>
                <textarea name="notes" class="w-full border border-gray-300 px-3 py-2 rounded" rows="4" placeholder="Optional"></textarea>
                
                <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mt-4">
                    <p class="text-sm">
                        <span class="font-semibold">Note:</span> After submitting this form, you'll be taken to a confirmation page 
                        where you can complete the payment process or cancel the transaction.
                    </p>
                </div>
            </div>

            <!-- Submit -->
            <div class="flex justify-end">
                <a href="customer_dashboard.php" class="bg-gray-500 text-white px-4 py-2 rounded mr-3 hover:bg-gray-600">Cancel</a>
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">
                    Continue to Payment
                </button>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Form validation
            document.getElementById('packageForm').addEventListener('submit', function(event) {
                let isValid = true;
                
                // Validate receiver information
                const requiredReceiverFields = [
                    'receiver_first_name', 'receiver_last_name', 'receiver_phone', 
                    'receiver_street', 'receiver_city', 'receiver_state', 'receiver_zip'
                ];
                
                requiredReceiverFields.forEach(function(field) {
                    const input = document.getElementsByName(field)[0];
                    if (!input.value) {
                        input.classList.add('border-red-500');
                        isValid = false;
                    } else {
                        input.classList.remove('border-red-500');
                    }
                });
                
                // Validate package details
                const packageFields = ['size', 'weight', 'shipping_speed'];
                packageFields.forEach(function(field) {
                    const input = document.getElementsByName(field)[0];
                    if (!input.value) {
                        input.classList.add('border-red-500');
                        isValid = false;
                    } else {
                        input.classList.remove('border-red-500');
                    }
                });
                
                if (!isValid) {
                    event.preventDefault();
                    alert('Please fill in all required fields marked with *');
                }
            });
        });
    </script>
</body>
</html>
