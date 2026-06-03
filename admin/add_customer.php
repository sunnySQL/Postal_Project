<?php
session_start();
require_once '../db_connect.php';
require_once '../functions.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'Admin') {
    header("Location: ../unauthorized.php");
    exit();
}

$error_message   = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn->begin_transaction();
    try {
        $email        = trim($_POST['email'] ?? '');
        $first_name   = trim($_POST['first_name'] ?? '');
        $last_name    = trim($_POST['last_name'] ?? '');
        $phone        = trim($_POST['phone'] ?? '');
        $street       = trim($_POST['street_address'] ?? '');
        $city         = trim($_POST['city'] ?? '');
        $state        = trim($_POST['state'] ?? '');
        $postal_code  = trim($_POST['postal_code'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL))
            throw new Exception("Invalid email format.");
        if (empty($first_name) || empty($last_name))
            throw new Exception("First and last name are required.");

        // Check email uniqueness
        $chk = $conn->prepare("SELECT user_id FROM Users WHERE email = ?");
        $chk->bind_param("s", $email);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0)
            throw new Exception("Email already exists in the system.");

        // Generate temp password
        $temp_password  = bin2hex(random_bytes(5));
        $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);

        // Insert into Users
        $stmt = $conn->prepare("INSERT INTO Users (email, password, role, account_status, password_change_required) VALUES (?, ?, 'Customer', 'Active', 1)");
        $stmt->bind_param("ss", $email, $hashed_password);
        $stmt->execute();
        $user_id = $conn->insert_id;

        // Insert into Customer
        $stmt = $conn->prepare("INSERT INTO Customer (user_id, first_name, last_name, phone, street_address, city, state, postal_code, is_guest) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)");
        $stmt->bind_param("isssssss", $user_id, $first_name, $last_name, $phone, $street, $city, $state, $postal_code);
        $stmt->execute();

        $conn->commit();

        logAudit($conn, 'CUSTOMER_ADDED', 'User', (string)$user_id,
            "New customer account created by admin: {$first_name} {$last_name} ({$email})",
            null, null,
            ['user_id' => $user_id, 'name' => "$first_name $last_name", 'email' => $email]
        );

        $success_message = "Customer account created successfully. Temporary password: <strong>{$temp_password}</strong>";

    } catch (Exception $e) {
        $conn->rollback();
        $error_message = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Customer — Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen">
<?php $nav_back_url = 'manage_users.php'; $nav_back_text = 'Users'; ?>
<?php include '_nav.php'; ?>
<div class="max-w-2xl mx-auto px-4 py-10">

    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-black text-gray-900">Add Customer Account</h1>
            <p class="text-gray-500 text-sm mt-1">Create a new customer account on behalf of a customer.</p>
        </div>
        <a href="manage_users.php" class="text-sm text-blue-600 hover:text-blue-800 flex items-center gap-1">
            <i class="fas fa-arrow-left"></i> Back to Users
        </a>
    </div>

    <?php if ($error_message): ?>
    <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded mb-6 text-sm">
        <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error_message) ?>
    </div>
    <?php endif; ?>

    <?php if ($success_message): ?>
    <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded mb-6 text-sm">
        <i class="fas fa-check-circle mr-2"></i><?= $success_message ?>
        <p class="mt-2 font-semibold">Share this temporary password securely with the customer. They will be prompted to change it on first login.</p>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <form method="POST" action="">

            <!-- Account Info -->
            <h2 class="text-sm font-bold text-gray-500 uppercase tracking-wide mb-4">Account Info</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email Address <span class="text-red-500">*</span></label>
                    <input type="email" name="email" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="customer@example.com">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">First Name <span class="text-red-500">*</span></label>
                    <input type="text" name="first_name" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Last Name <span class="text-red-500">*</span></label>
                    <input type="text" name="last_name" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                    <input type="tel" name="phone"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="(555) 000-0000">
                </div>
            </div>

            <!-- Address -->
            <h2 class="text-sm font-bold text-gray-500 uppercase tracking-wide mb-4">Address <span class="text-gray-400 font-normal normal-case">(optional)</span></h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Street Address</label>
                    <input type="text" name="street_address"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="123 Main St">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                    <input type="text" name="city"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">State</label>
                    <input type="text" name="state" maxlength="2"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="CA">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Postal Code</label>
                    <input type="text" name="postal_code" maxlength="10"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="90210">
                </div>
            </div>

            <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                <p class="text-xs text-gray-400"><i class="fas fa-lock mr-1"></i>A temporary password will be auto-generated.</p>
                <button type="submit"
                        class="bg-green-600 hover:bg-green-700 text-white px-5 py-2 rounded-lg text-sm font-semibold transition flex items-center gap-2">
                    <i class="fas fa-user-plus"></i> Create Customer
                </button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
