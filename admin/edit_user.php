<?php
session_start();
require_once '../db_connect.php';
require_once '../functions.php';


if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'Admin') {
    header("Location: unauthorized.php");
    exit();
}

// Check if ID parameter exists
if (!isset($_GET['id'])) {
    $_SESSION['message'] = "No user ID specified";
    $_SESSION['message_type'] = "error";
    header("Location: manage_users.php");
    exit();
}

// Validate the ID parameter
$user_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
if (!$user_id) {
    $_SESSION['message'] = "Invalid user ID";
    $_SESSION['message_type'] = "error";
    header("Location: manage_users.php");
    exit();
}

// Process form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn->begin_transaction();
    try {
        // Get form data
        $email = $_POST['email'];
        $role = $_POST['role'];
        $account_status = $_POST['account_status'];
        
        // Update the User
        $stmt = $conn->prepare("UPDATE Users SET email = ?, account_status = ? WHERE user_id = ?");
        $stmt->bind_param("ssi", $email, $account_status, $user_id);
        $stmt->execute();
        
        // Handle password change if provided
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE Users SET password = ? WHERE user_id = ?");
            $stmt->bind_param("si", $password, $user_id);
            $stmt->execute();
        }
        
        // Handle role-specific data
        if ($role === 'Customer') {
            // Check if user already has a Customer record
            $stmt = $conn->prepare("SELECT user_id FROM Customer WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $customer_exists = $stmt->get_result()->num_rows > 0;
            
            $first_name = $_POST['first_name'];
            $last_name = $_POST['last_name'];
            $phone = $_POST['phone'];
            $street_address = $_POST['street_address'];
            $city = $_POST['city'];
            $state = $_POST['state'];
            $postal_code = $_POST['postal_code'];
            $is_guest = isset($_POST['is_guest']) ? 1 : 0;
            
            if ($customer_exists) {
                $stmt = $conn->prepare("UPDATE Customer SET first_name = ?, last_name = ?, phone = ?, 
                                        street_address = ?, city = ?, state = ?, postal_code = ?, 
                                        is_guest = ? WHERE user_id = ?");
                $stmt->bind_param("sssssssis", $first_name, $last_name, $phone, 
                                $street_address, $city, $state, $postal_code, $is_guest, $user_id);
                $stmt->execute();
            } else {
                $stmt = $conn->prepare("INSERT INTO Customer (user_id, first_name, last_name, phone, 
                                        street_address, city, state, postal_code, is_guest) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("issssssis", $user_id, $first_name, $last_name, $phone, 
                                $street_address, $city, $state, $postal_code, $is_guest);
                $stmt->execute();
                
                // Remove from Employee table if exists
                $conn->query("DELETE FROM Employee WHERE user_id = $user_id");
            }
        } elseif ($role === 'Employee' || $role === 'Admin') {
            // Check if user already has an Employee record
            $stmt = $conn->prepare("SELECT user_id FROM Employee WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $employee_exists = $stmt->get_result()->num_rows > 0;
            
            $first_name = $_POST['emp_first_name'];
            $last_name = $_POST['emp_last_name'];
            $employee_role = $_POST['employee_role'];
            $facility_id = intval($_POST['facility_id']);
            
            if ($employee_exists) {
                $stmt = $conn->prepare("UPDATE Employee SET first_name = ?, last_name = ?, role = ?, facility_id = ? WHERE user_id = ?");
                $stmt->bind_param("sssii", $first_name, $last_name, $employee_role, $facility_id, $user_id);
                $stmt->execute();
            } else {
                $stmt = $conn->prepare("INSERT INTO Employee (user_id, first_name, last_name, role, facility_id) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("isssi", $user_id, $first_name, $last_name, $employee_role, $facility_id);
                $stmt->execute();
                
                // Remove from Customer table if exists
                $conn->query("DELETE FROM Customer WHERE user_id = $user_id");
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['message'] = "User information updated successfully";
        $_SESSION['message_type'] = "success";
        header("Location: manage_users.php");
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $error_message = "Update failed: " . $e->getMessage();
    }
}

// Get user data
try {
    // First get the basic user information
    $stmt = $conn->prepare("SELECT * FROM Users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['message'] = "User not found";
        $_SESSION['message_type'] = "error";
        header("Location: manage_users.php");
        exit();
    }
    
    $user = $result->fetch_assoc();
    
    // Get role-specific information
    if ($user['role'] === 'Customer') {
        $stmt = $conn->prepare("SELECT * FROM Customer WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $role_data = $stmt->get_result()->fetch_assoc();
    } elseif ($user['role'] === 'Employee' || $user['role'] === 'Admin') {
        $stmt = $conn->prepare("SELECT * FROM Employee WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $role_data = $stmt->get_result()->fetch_assoc();
    }
    
    // Get all facilities for dropdown
    $facilities = $conn->query("SELECT facility_id, city, type FROM Facility ORDER BY city");
    
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User — Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen">

<?php
    $roleColor   = ['Admin'=>'bg-purple-100 text-purple-700','Customer'=>'bg-green-100 text-green-700','Employee'=>'bg-blue-100 text-blue-700'];
    $roleBadge   = $roleColor[$user['role']] ?? 'bg-gray-100 text-gray-700';
    $statusColor = ['Active'=>'bg-green-100 text-green-700','Suspended'=>'bg-yellow-100 text-yellow-700','Banned'=>'bg-red-100 text-red-700'];
    $statusBadge = $statusColor[$user['account_status']] ?? 'bg-gray-100 text-gray-700';
    $displayName = isset($role_data) ? trim(($role_data['first_name'] ?? '').' '.($role_data['last_name'] ?? '')) : '';
    $initials    = $displayName ? strtoupper(substr($displayName,0,1)) : strtoupper(substr($user['email'],0,1));
?>

<?php $nav_back_url = 'manage_users.php'; $nav_back_text = 'Users'; ?>
<?php include '_nav.php'; ?>

<div class="max-w-5xl mx-auto px-4 py-8">

    <!-- Breadcrumb -->
    <div class="flex items-center gap-2 text-sm text-gray-400 mb-6">
        <a href="manage_users.php" class="hover:text-[#004B87] transition">Users</a>
        <i class="fas fa-chevron-right text-xs"></i>
        <span class="text-gray-600 font-medium">Edit User #<?= $user_id ?></span>
    </div>

    <?php if (isset($error_message)): ?>
    <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-lg mb-6 text-sm">
        <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error_message) ?>
    </div>
    <?php endif; ?>

    <form action="edit_user.php?id=<?= htmlspecialchars($user_id) ?>" method="post">
    <input type="hidden" name="role" value="<?= htmlspecialchars($user['role']) ?>">

    <div class="flex flex-col lg:flex-row gap-6">

        <!-- ── LEFT SIDEBAR: User Summary (read-only context) ── -->
        <div class="lg:w-64 flex-shrink-0 space-y-4">

            <!-- Avatar / Identity card -->
            <div class="bg-white rounded-xl border border-gray-200 p-5 text-center">
                <div class="w-16 h-16 rounded-full bg-[#004B87] text-white text-2xl font-black flex items-center justify-center mx-auto mb-3">
                    <?= htmlspecialchars($initials) ?>
                </div>
                <?php if ($displayName): ?>
                <p class="font-bold text-gray-900 text-sm"><?= htmlspecialchars($displayName) ?></p>
                <?php endif; ?>
                <p class="text-xs text-gray-400 break-all mt-0.5"><?= htmlspecialchars($user['email']) ?></p>
                <div class="flex justify-center gap-2 mt-3">
                    <span class="<?= $roleBadge ?> text-xs font-semibold px-2 py-1 rounded-full"><?= htmlspecialchars($user['role']) ?></span>
                    <span class="<?= $statusBadge ?> text-xs font-semibold px-2 py-1 rounded-full"><?= htmlspecialchars($user['account_status']) ?></span>
                </div>
            </div>

            <!-- Read-only meta -->
            <div class="bg-white rounded-xl border border-gray-200 p-4 space-y-3 text-xs text-gray-500">
                <div>
                    <p class="font-bold text-gray-400 uppercase tracking-wide mb-0.5">User ID</p>
                    <p class="font-mono text-gray-700">#<?= $user_id ?></p>
                </div>
                <div>
                    <p class="font-bold text-gray-400 uppercase tracking-wide mb-0.5">Role Type</p>
                    <p class="text-gray-700"><?= htmlspecialchars($user['role']) ?> <span class="text-gray-400">(locked)</span></p>
                </div>
                <?php if (isset($role_data['facility_id']) && $role_data['facility_id']): ?>
                <div>
                    <p class="font-bold text-gray-400 uppercase tracking-wide mb-0.5">Facility</p>
                    <p class="text-gray-700">#<?= htmlspecialchars($role_data['facility_id']) ?></p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Save / Cancel (sidebar copy for large screens) -->
            <div class="hidden lg:flex flex-col gap-2">
                <button type="submit"
                        class="w-full bg-[#004B87] hover:bg-blue-900 text-white py-2.5 rounded-lg text-sm font-bold transition flex items-center justify-center gap-2">
                    <i class="fas fa-save"></i> Save Changes
                </button>
                <a href="manage_users.php"
                   class="w-full text-center py-2.5 rounded-lg border border-gray-200 text-sm font-semibold text-gray-500 hover:bg-gray-50 transition">
                    Cancel
                </a>
            </div>
        </div>

        <!-- ── RIGHT MAIN FORM ── -->
        <div class="flex-1 space-y-5">

            <!-- Section 1: Login Credentials -->
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="bg-gray-50 border-b border-gray-200 px-5 py-3 flex items-center gap-2">
                    <i class="fas fa-lock text-[#004B87] text-xs"></i>
                    <h2 class="text-xs font-bold text-gray-600 uppercase tracking-wider">Login Credentials</h2>
                </div>
                <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">Email Address <span class="text-red-400">*</span></label>
                        <input type="email" name="email" required
                               value="<?= htmlspecialchars($user['email']) ?>"
                               class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004B87] focus:border-transparent transition bg-white">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">New Password</label>
                        <input type="password" name="password" placeholder="Leave blank to keep current"
                               class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004B87] focus:border-transparent transition bg-white">
                        <p class="text-xs text-gray-400 mt-1">Only fill this to change the password.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">Account Status <span class="text-red-400">*</span></label>
                        <select name="account_status" required
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004B87] focus:border-transparent transition bg-white">
                            <option value="Active"    <?= $user['account_status'] === 'Active'    ? 'selected' : '' ?>>Active</option>
                            <option value="Suspended" <?= $user['account_status'] === 'Suspended' ? 'selected' : '' ?>>Suspended</option>
                            <option value="Banned"    <?= $user['account_status'] === 'Banned'    ? 'selected' : '' ?>>Banned</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Section 2a: Customer Profile -->
            <div id="customer-fields" style="display:none">
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <div class="bg-gray-50 border-b border-gray-200 px-5 py-3 flex items-center gap-2">
                        <i class="fas fa-user text-green-600 text-xs"></i>
                        <h2 class="text-xs font-bold text-gray-600 uppercase tracking-wider">Customer Profile</h2>
                    </div>
                    <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">First Name</label>
                            <input type="text" name="first_name"
                                   value="<?= htmlspecialchars($role_data['first_name'] ?? '') ?>"
                                   class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004B87] focus:border-transparent transition bg-white">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">Last Name</label>
                            <input type="text" name="last_name"
                                   value="<?= htmlspecialchars($role_data['last_name'] ?? '') ?>"
                                   class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004B87] focus:border-transparent transition bg-white">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">Phone</label>
                            <input type="tel" name="phone"
                                   value="<?= htmlspecialchars($role_data['phone'] ?? '') ?>"
                                   placeholder="(555) 000-0000"
                                   class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004B87] focus:border-transparent transition bg-white">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">Street Address</label>
                            <input type="text" name="street_address"
                                   value="<?= htmlspecialchars($role_data['street_address'] ?? '') ?>"
                                   placeholder="123 Main St"
                                   class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004B87] focus:border-transparent transition bg-white">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">City</label>
                            <input type="text" name="city"
                                   value="<?= htmlspecialchars($role_data['city'] ?? '') ?>"
                                   class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004B87] focus:border-transparent transition bg-white">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">State</label>
                                <input type="text" name="state" maxlength="2" placeholder="CA"
                                       value="<?= htmlspecialchars($role_data['state'] ?? '') ?>"
                                       class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004B87] focus:border-transparent transition bg-white uppercase">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">Postal Code</label>
                                <input type="text" name="postal_code" placeholder="90210"
                                       value="<?= htmlspecialchars($role_data['postal_code'] ?? '') ?>"
                                       class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004B87] focus:border-transparent transition bg-white">
                            </div>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="flex items-center gap-3 cursor-pointer group">
                                <input type="checkbox" name="is_guest"
                                       class="w-4 h-4 rounded border-gray-300 text-[#004B87] focus:ring-[#004B87]"
                                       <?= isset($role_data['is_guest']) && $role_data['is_guest'] ? 'checked' : '' ?>>
                                <div>
                                    <p class="text-sm font-semibold text-gray-700">Guest Account</p>
                                    <p class="text-xs text-gray-400">Guest accounts cannot log in directly.</p>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 2b: Employee Profile -->
            <div id="employee-fields" style="display:none">
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <div class="bg-gray-50 border-b border-gray-200 px-5 py-3 flex items-center gap-2">
                        <i class="fas fa-id-badge text-blue-600 text-xs"></i>
                        <h2 class="text-xs font-bold text-gray-600 uppercase tracking-wider">Employee Profile</h2>
                    </div>
                    <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">First Name</label>
                            <input type="text" name="emp_first_name"
                                   value="<?= htmlspecialchars($role_data['first_name'] ?? '') ?>"
                                   class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004B87] focus:border-transparent transition bg-white">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">Last Name</label>
                            <input type="text" name="emp_last_name"
                                   value="<?= htmlspecialchars($role_data['last_name'] ?? '') ?>"
                                   class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004B87] focus:border-transparent transition bg-white">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">Job Role <span class="text-red-400">*</span></label>
                            <select name="employee_role"
                                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004B87] focus:border-transparent transition bg-white">
                                <?php foreach (['Clerk','Driver','Sorting Staff','Pilot','Customer Support','Manager','Admin'] as $er): ?>
                                <option value="<?= $er ?>" <?= isset($role_data['role']) && $role_data['role'] === $er ? 'selected' : '' ?>><?= $er ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">Assigned Facility <span class="text-red-400">*</span></label>
                            <select name="facility_id"
                                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004B87] focus:border-transparent transition bg-white">
                                <?php if (isset($facilities)): while ($facility = $facilities->fetch_assoc()): ?>
                                <option value="<?= $facility['facility_id'] ?>"
                                        <?= isset($role_data['facility_id']) && $role_data['facility_id'] == $facility['facility_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($facility['city'].' — '.$facility['type']) ?>
                                </option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mobile: Save / Cancel -->
            <div class="flex lg:hidden gap-3">
                <a href="manage_users.php"
                   class="flex-1 text-center py-3 rounded-lg border border-gray-200 text-sm font-semibold text-gray-500 hover:bg-gray-50 transition">
                    Cancel
                </a>
                <button type="submit"
                        class="flex-1 bg-[#004B87] hover:bg-blue-900 text-white py-3 rounded-lg text-sm font-bold transition flex items-center justify-center gap-2">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>

        </div><!-- end right -->
    </div><!-- end flex -->
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const role = "<?= $user['role'] ?>";
    if (role === 'Customer') {
        document.getElementById('customer-fields').style.display = 'block';
    } else if (role === 'Employee' || role === 'Admin') {
        document.getElementById('employee-fields').style.display = 'block';
    }
});
</script>
</body>
</html>
