<?php
session_start();
require_once '../db_connect.php';
require_once '../functions.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'Admin') {
    header("Location: ../unauthorized.php");
    exit();
}

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn->begin_transaction();
    try {
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        if ($role === 'Administrator') {
            $role = 'Admin';
        }
        
        $account_status = 'Active';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format");
        }
        
        // Check if email already exists
        $stmt = $conn->prepare("SELECT user_id FROM Users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception("Email already exists in the system");
        }
        
        // Generate temp password
        $temp_password = bin2hex(random_bytes(4));
        $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
        
        // Insert new user
        $stmt = $conn->prepare("INSERT INTO Users (email, password, role, account_status, password_change_required) VALUES (?, ?, ?, ?, 1)");
        $stmt->bind_param("ssss", $email, $hashed_password, $role, $account_status);
        $stmt->execute();
        $user_id = $conn->insert_id;
        
        // Insert employee data
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $employee_role = $_POST['employee_role'];
        $facility_id = intval($_POST['facility_id']);
        $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
        
        $stmt = $conn->prepare("INSERT INTO Employee (user_id, first_name, last_name, role, facility_id, phone) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssss", $user_id, $first_name, $last_name, $employee_role, $facility_id, $phone);
        $stmt->execute();
        
        $conn->commit();
        $success_message = "Employee added successfully. Temporary password: $temp_password";

        logAudit($conn, 'EMPLOYEE_ADDED', 'User', (string)$user_id,
            "New employee added: {$first_name} {$last_name} ({$employee_role}) at facility #{$facility_id}",
            $facility_id, null,
            ['user_id' => $user_id, 'name' => "$first_name $last_name",
             'role' => $employee_role, 'email' => $email, 'facility_id' => $facility_id]
        );

    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error: " . $e->getMessage();
    }
}

// Get all facilities for dropdown
try {
    $facilities = $conn->query("SELECT facility_id, city, state, type FROM Facility ORDER BY state, city");
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Employee</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php $nav_back_url = 'manage_users.php'; $nav_back_text = 'Users'; ?>
    <?php include '_nav.php'; ?>
    <div class="container mx-auto px-4 py-8 max-w-6xl">
        <header class="mb-6">
            <div class="flex justify-between items-center">
                <h1 class="text-3xl font-bold">Add New Employee</h1>
                <a href="manage_users.php" class="text-blue-600 hover:text-blue-800">
                    <i class="fas fa-arrow-left mr-1"></i> Back to User Management
                </a>
            </div>
        </header>
        
        <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                <p><?= htmlspecialchars($error_message) ?></p>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
                <p><?= htmlspecialchars($success_message) ?></p>
                <p class="mt-2"><strong>Important:</strong> Please securely share this temporary password with the employee.</p>
            </div>
        <?php endif; ?>
        
        <div class="bg-gray-100 p-6 rounded-lg mb-6">
            <form action="add_user.php" method="post">
                <div class="mb-6 pb-4 border-b border-gray-300">
                    <h2 class="text-xl font-semibold mb-4">Basic User Information</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="mb-4">
                            <label for="email" class="block mb-2 font-medium">Email:</label>
                            <input type="email" id="email" name="email" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div class="mb-4">
                            <label for="role" class="block mb-2 font-medium">System Role:</label>
                            <select id="role" name="role" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="Employee">Employee</option>
                                <option value="Admin">Admin</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="mb-6">
                    <h2 class="text-xl font-semibold mb-4">Employee Details</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="mb-4">
                            <label for="first_name" class="block mb-2 font-medium">First Name:</label>
                            <input type="text" id="first_name" name="first_name" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div class="mb-4">
                            <label for="last_name" class="block mb-2 font-medium">Last Name:</label>
                            <input type="text" id="last_name" name="last_name" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div class="mb-4">
                            <label for="phone" class="block mb-2 font-medium">Phone Number:</label>
                            <input type="tel" id="phone" name="phone"
                                   class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div class="mb-4">
                            <label for="employee_role" class="block mb-2 font-medium">Employee Role:</label>
                            <select id="employee_role" name="employee_role" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="Clerk">Clerk</option>
                                <option value="Driver">Driver</option>
                                <option value="Sorting Staff">Sorting Staff</option>
                                <option value="Pilot">Pilot</option>
                                <option value="Customer Support">Customer Support</option>
                                <option value="Manager">Manager</option>
                                <option value="Admin">Admin</option>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label for="facility_id" class="block mb-2 font-medium">Facility:</label>
                            <select id="facility_id" name="facility_id" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <?php if ($facilities && $facilities->num_rows > 0): ?>
                                    <?php while ($facility = $facilities->fetch_assoc()): ?>
                                        <option value="<?= htmlspecialchars($facility['facility_id']) ?>">
                                            <?= htmlspecialchars($facility['city'] . ', ' . $facility['state'] . ' - ' . $facility['type']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <option value="" disabled>No facilities available</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end">
                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded">
                        <i class="fas fa-save mr-2"></i> Create Employee
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
<script>
    // Ensure that when Admin is selected as system role, employee role is also set to Admin
    document.addEventListener('DOMContentLoaded', function() {
        const systemRoleSelect = document.getElementById('role');
        const employeeRoleSelect = document.getElementById('employee_role');
        
        // Function to update employee role based on system role
        function updateEmployeeRole() {
            if (systemRoleSelect.value === 'Admin') {
                // Find the Admin option in employee role
                for (let i = 0; i < employeeRoleSelect.options.length; i++) {
                    if (employeeRoleSelect.options[i].value === 'Admin') {
                        employeeRoleSelect.selectedIndex = i;
                        break;
                    }
                }
                // Instead of disabling, make it read-only by using CSS and a click handler
                employeeRoleSelect.classList.add('bg-gray-100');
                employeeRoleSelect.setAttribute('data-locked', 'true');
            } else {
                // Enable employee role selection
                employeeRoleSelect.classList.remove('bg-gray-100');
                employeeRoleSelect.removeAttribute('data-locked');
            }
        }
        
        // Prevent changes to employee role when locked
        employeeRoleSelect.addEventListener('mousedown', function(e) {
            if (this.getAttribute('data-locked') === 'true') {
                e.preventDefault();
                this.blur();
                return false;
            }
        });
        
        // Initialize on page load
        updateEmployeeRole();
        
        // Update when system role changes
        systemRoleSelect.addEventListener('change', updateEmployeeRole);
    });
</script>
</html>