<?php
// Enable error reporting
require_once 'db_connect.php';
require_once 'functions.php';


if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$error_message = '';
$success_message = '';
$password_changed = false;

// Get user information
$stmt = $conn->prepare("SELECT u.*, 
                       e.first_name as emp_first_name, e.last_name as emp_last_name, e.phone as emp_phone, e.role as employee_role,
                       c.first_name as cust_first_name, c.last_name as cust_last_name, c.phone as cust_phone,
                       u.password_change_required
                       FROM Users u
                       LEFT JOIN Employee e ON u.user_id = e.user_id
                       LEFT JOIN Customer c ON u.user_id = c.user_id
                       WHERE u.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Determine the correct name and phone based on role
$user['first_name'] = $role === 'Customer' ? $user['cust_first_name'] : $user['emp_first_name'];
$user['last_name'] = $role === 'Customer' ? $user['cust_last_name'] : $user['emp_last_name'];
$user['phone'] = $role === 'Customer' ? $user['cust_phone'] : $user['emp_phone'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn->begin_transaction();
    try {
        // Handle phone number update
        if (isset($_POST['phone'])) {
            $phone = trim($_POST['phone']);
            if ($role === 'Customer') {
                $stmt = $conn->prepare("UPDATE Customer SET phone = ? WHERE user_id = ?");
            } else {
                $stmt = $conn->prepare("UPDATE Employee SET phone = ? WHERE user_id = ?");
            }
            $stmt->bind_param("si", $phone, $user_id);
            $stmt->execute();
        }
        
        // Handle password change
        $password_changed = false;
        if (isset($_POST['current_password']) && isset($_POST['new_password']) && isset($_POST['confirm_password'])) {
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];

            if (!password_verify($current_password, $user['password'])) {
                throw new Exception("Current password is incorrect");
            }
            if ($new_password !== $confirm_password) {
                throw new Exception("New passwords do not match");
            }
            if (strlen($new_password) < 8) {
                throw new Exception("Password must be at least 8 characters long");
            }
            
            // Hash the new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update the password
            $stmt = $conn->prepare("UPDATE Users SET password = ?, password_change_required = 0 WHERE user_id = ?");
            $stmt->bind_param("si", $hashed_password, $user_id);
            $stmt->execute();
            
            $password_changed = true;
        }
        
        $conn->commit();
        $success_message = "Profile updated successfully";
        $stmt = $conn->prepare("SELECT u.*, 
                              e.first_name as emp_first_name, e.last_name as emp_last_name, e.phone as emp_phone, e.role as employee_role,
                              c.first_name as cust_first_name, c.last_name as cust_last_name, c.phone as cust_phone,
                              u.password_change_required
                              FROM Users u
                              LEFT JOIN Employee e ON u.user_id = e.user_id
                              LEFT JOIN Customer c ON u.user_id = c.user_id
                              WHERE u.user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        // Update name and phone variables after refresh
        $user['first_name'] = $role === 'Customer' ? $user['cust_first_name'] : $user['emp_first_name'];
        $user['last_name'] = $role === 'Customer' ? $user['cust_last_name'] : $user['emp_last_name']; 
        $user['phone'] = $role === 'Customer' ? $user['cust_phone'] : $user['emp_phone'];
        
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error: " . $e->getMessage();
    }
}

// Check if user needs to change password (first login)
$force_password_change = $user['password_change_required'] == 1;

// For employees: get name and facility for header (same as employee_dashboard)
$name = $_SESSION['name'] ?? ($user['first_name'] . ' ' . $user['last_name']);
$employee = ['role' => $role, 'city' => '', 'type' => ''];
if ($role !== 'Customer') {
    $emp_stmt = $conn->prepare("SELECT e.*, f.city, f.type FROM Employee e LEFT JOIN Facility f ON e.facility_id = f.facility_id WHERE e.user_id = ?");
    $emp_stmt->bind_param("i", $user_id);
    $emp_stmt->execute();
    $emp_result = $emp_stmt->get_result();
    if ($emp_result->num_rows > 0) {
        $employee = $emp_result->fetch_assoc();
    }
}

// Unread admin messages count for employee nav
$unread_admin_messages = 0;
if ($role !== 'Customer' && $role !== 'Admin') {
    $msg_stmt = $conn->prepare("SELECT COUNT(*) as count FROM Admin_Messages WHERE sender_id = ? AND status = 'Replied'");
    $msg_stmt->bind_param("i", $user_id);
    $msg_stmt->execute();
    $unread_admin_messages = (int)($msg_stmt->get_result()->fetch_assoc()['count'] ?? 0);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap');
        * { font-family: 'Open Sans', sans-serif; }
        body { background-color: #f8f9fa; }
        .section-title { color: #004B87; }
        .action-btn { background-color: #004B87; transition: background-color 0.3s; }
        .action-btn:hover { background-color: #003366; }
        .accent-btn { background-color: #DA291C; transition: background-color 0.3s; }
        .accent-btn:hover { background-color: #b52218; }
        .input-focus:focus { outline: none; box-shadow: 0 0 0 2px #004B87; }
    </style>
</head>
<body class="bg-gray-50">
<?php $emp_base = ''; include '_employee_nav.php'; ?>

    <div class="container mx-auto px-4 py-8 max-w-6xl">
        <header class="mb-6">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center">
                <h1 class="text-3xl font-bold section-title">Edit Profile</h1>
                <div class="mt-4 md:mt-0 bg-white shadow-sm p-4 rounded-lg">
                    <?php if ($role === 'Customer'): ?>
                    <p class="mb-1"><span class="font-semibold">Welcome,</span> <?= htmlspecialchars($name) ?></p>
                    <p><span class="font-semibold">Account Status:</span> <span class="<?= ($_SESSION['account_status'] ?? 'Active') === 'Active' ? 'text-green-600' : 'text-red-600' ?>"><?= htmlspecialchars($_SESSION['account_status'] ?? 'Active') ?></span></p>
                    <?php else: ?>
                    <p class="mb-1"><span class="font-semibold">Welcome,</span> <?= htmlspecialchars($name) ?> | <span class="font-medium"><?= htmlspecialchars($employee['role']) ?></span></p>
                    <p><span class="font-semibold">Facility:</span> <?= htmlspecialchars($employee['type'] ?: '—') ?> <?= $employee['city'] ? ' - ' . htmlspecialchars($employee['city']) : '' ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <?php if (!$force_password_change): ?>
        <nav class="bg-white shadow-sm p-4 rounded-lg mb-6">
            <ul class="flex flex-wrap space-x-2 md:space-x-6">
                <?php if ($role === 'Customer'): ?>
                <li><a href="edit_profile.php" class="text-gray-700 hover:text-[#DA291C] border-b-2 border-[#DA291C] pb-1 font-medium">Edit Profile</a></li>
                <li><a href="sendpackage.php" class="text-gray-700 hover:text-[#DA291C]">Send Package</a></li>
                <li><a href="package/track.php" class="text-gray-700 hover:text-[#DA291C]">Track Package</a></li>
                <li><a href="support.php" class="text-gray-700 hover:text-[#DA291C]">Support</a></li>
                <li><a href="shop.php" class="text-gray-700 hover:text-[#DA291C]">Shop</a></li>
                <?php else: ?>
                <?php if ($role === 'Admin'): ?>
                <li><a href="admin/manage_users.php" class="text-gray-700 hover:text-[#DA291C]">Manage Users</a></li>
                <li><a href="admin/manage_facilities.php" class="text-gray-700 hover:text-[#DA291C]">Manage Facilities</a></li>
                <li><a href="admin/manage_vehicles.php" class="text-gray-700 hover:text-[#DA291C]">Manage Vehicles</a></li>
                <li><a href="admin/inbox.php" class="text-gray-700 hover:text-[#DA291C]">Admin Inbox</a></li>
                <li><a href="edit_profile.php" class="text-gray-700 hover:text-[#DA291C] border-b-2 border-[#DA291C] pb-1 font-medium">Edit Profile</a></li>
                <?php else: ?>
                <li><a href="employee/contact_admin.php" class="text-gray-700 hover:text-[#DA291C] flex items-center">Contact Admin<?php if ($unread_admin_messages > 0): ?> <span class="ml-2 bg-red-600 text-white text-xs px-2 py-1 rounded-full"><?= $unread_admin_messages ?></span><?php endif; ?></a></li>
                <?php endif; ?>
                <?php if (isset($employee['role']) && $employee['role'] === 'Clerk'): ?>
                <li><a href="package/new_package.php" class="text-gray-700 hover:text-[#DA291C]">Create Shipment</a></li>
                <li><a href="shop/inventory_management.php" class="text-gray-700 hover:text-[#DA291C]">Inventory Management</a></li>
                <li><a href="package/search.php" class="text-gray-700 hover:text-[#DA291C]">Package Search &amp; Scan</a></li>
                <?php endif; ?>
                <?php if (isset($employee['role']) && $employee['role'] === 'Manager'): ?>
                <li><a href="shop/shop_dashboard.php" class="text-gray-700 hover:text-[#DA291C]">Shop Overview</a></li>
                <li><a href="reports/index.php" class="text-gray-700 hover:text-[#DA291C]">Reports</a></li>
                <li><a href="trips/manage_trips.php" class="text-gray-700 hover:text-[#DA291C]">Manage Trips</a></li>
                <?php endif; ?>
                <?php if (isset($employee['role']) && $employee['role'] === 'Clerk'): ?>
                <li><a href="package/awaiting_pickup.php" class="text-gray-700 hover:text-[#DA291C]">Pickup</a></li>
                <?php endif; ?>
                <li><a href="edit_profile.php" class="text-gray-700 hover:text-[#DA291C] border-b-2 border-[#DA291C] pb-1 font-medium">Edit Profile</a></li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow-sm">
            <p><?= htmlspecialchars($error_message) ?></p>
        </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-sm">
            <p><?= htmlspecialchars($success_message) ?></p>
            <?php if ($password_changed): ?>
            <p class="mt-2">Your password has been updated successfully.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($force_password_change): ?>
        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-800 p-4 mb-6 rounded shadow-sm">
            <p><strong>Welcome!</strong> Please change your temporary password before continuing.</p>
        </div>
        <?php endif; ?>

        <!-- Personal Information -->
        <div class="bg-white shadow-md rounded-lg p-6 mb-6">
            <h2 class="text-xl font-semibold section-title mb-4">Personal Information</h2>
            <form action="edit_profile.php" method="post">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block mb-2 font-medium text-gray-700">Email</label>
                        <input type="text" value="<?= htmlspecialchars($user['email']) ?>" disabled class="w-full px-3 py-2 border border-gray-300 rounded bg-gray-100 text-gray-600">
                        <p class="text-sm text-gray-500 mt-1">Email cannot be changed</p>
                    </div>
                    <div>
                        <label class="block mb-2 font-medium text-gray-700">Name</label>
                        <input type="text" value="<?= htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name'])) ?>" disabled class="w-full px-3 py-2 border border-gray-300 rounded bg-gray-100 text-gray-600">
                        <p class="text-sm text-gray-500 mt-1">Contact administrator to change name</p>
                    </div>
                    <?php if ($role !== 'Customer' && !empty($user['employee_role'])): ?>
                    <div>
                        <label class="block mb-2 font-medium text-gray-700">Employee Role</label>
                        <input type="text" value="<?= htmlspecialchars($user['employee_role']) ?>" disabled class="w-full px-3 py-2 border border-gray-300 rounded bg-gray-100 text-gray-600">
                    </div>
                    <?php endif; ?>
                    <div>
                        <label for="phone" class="block mb-2 font-medium text-gray-700">Phone Number</label>
                        <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" class="w-full px-3 py-2 border border-gray-300 rounded input-focus">
                    </div>
                </div>
                <div class="flex justify-end mt-6">
                    <button type="submit" class="action-btn text-white px-5 py-2.5 rounded font-medium">
                        <i class="fas fa-save mr-2"></i> Update Profile
                    </button>
                </div>
            </form>
        </div>

        <!-- Change Password -->
        <div class="bg-white shadow-md rounded-lg p-6">
            <h2 class="text-xl font-semibold section-title mb-4">Change Password</h2>
            <form action="edit_profile.php" method="post">
                <div class="max-w-md space-y-4">
                    <div>
                        <label for="current_password" class="block mb-2 font-medium text-gray-700">Current Password</label>
                        <input type="password" id="current_password" name="current_password" required class="w-full px-3 py-2 border border-gray-300 rounded input-focus">
                    </div>
                    <div>
                        <label for="new_password" class="block mb-2 font-medium text-gray-700">New Password</label>
                        <input type="password" id="new_password" name="new_password" required class="w-full px-3 py-2 border border-gray-300 rounded input-focus">
                        <p class="text-sm text-gray-500 mt-1">Password must be at least 8 characters long</p>
                    </div>
                    <div>
                        <label for="confirm_password" class="block mb-2 font-medium text-gray-700">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required class="w-full px-3 py-2 border border-gray-300 rounded input-focus">
                    </div>
                </div>
                <div class="flex justify-end mt-6">
                    <button type="submit" class="accent-btn text-white px-5 py-2.5 rounded font-medium">
                        <i class="fas fa-lock mr-2"></i> Change Password
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($force_password_change): ?>
    <script>
        history.pushState(null, null, document.URL);
        window.addEventListener('popstate', function () {
            history.pushState(null, null, document.URL);
        });
    </script>
    <?php endif; ?>
</body>
</html>