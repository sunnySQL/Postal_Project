<?php
session_start();
require_once '../db_connect.php';
require_once '../functions.php';

// Check if user is logged in and has admin role
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'Admin') {
    header("Location: ../unauthorized.php");
    exit();
}

// Get available facilities for dropdown
$facilities_query = "SELECT facility_id, CONCAT(street_address, ', ', city, ', ', state, ' ', postal_code) AS facility_name, 
                    type FROM Facility ORDER BY type, city";
$facilities_result = $conn->query($facilities_query);

$success_message = '';
$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate and sanitize input
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
    $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
    $employee_role = mysqli_real_escape_string($conn, $_POST['employee_role']);
    $facility_id = filter_var($_POST['facility_id'], FILTER_VALIDATE_INT);
    
    // Validate employee data
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } elseif (empty($first_name) || empty($last_name)) {
        $error_message = "First name and last name are required.";
    } elseif (empty($employee_role)) {
        $error_message = "Employee role is required.";
    } elseif (!$facility_id) {
        $error_message = "Please select a valid facility.";
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT user_id FROM Users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error_message = "This email is already registered. Please use a different one.";
        } else {
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Generate a random temporary password
                $temp_password = generate_random_password(12);
                $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
                
                // Insert user record
                $user_role = "Employee"; // Setting role to Employee for the Users table
                $stmt = $conn->prepare("INSERT INTO Users (email, password, role, account_status) VALUES (?, ?, ?, 'Active')");
                $stmt->bind_param("sss", $email, $hashed_password, $user_role);
                $stmt->execute();
                
                $user_id = $conn->insert_id;
                
                // Insert employee record
                $stmt = $conn->prepare("INSERT INTO Employee (user_id, first_name, last_name, role, facility_id) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("isssi", $user_id, $first_name, $last_name, $employee_role, $facility_id);
                $stmt->execute();
                
                // Commit transaction
                $conn->commit();

                logAudit($conn, 'EMPLOYEE_ADDED', 'User', (string)$user_id,
                    "New employee added: {$first_name} {$last_name} ({$employee_role}) at facility #{$facility_id}",
                    $facility_id, null,
                    ['user_id' => $user_id, 'name' => "$first_name $last_name",
                     'role' => $employee_role, 'email' => $email, 'facility_id' => $facility_id]
                );

                // Display success message with temporary password
                $success_message = "Employee added successfully. Temporary password: " . $temp_password;

                // Clear form data to prevent resubmission
                $email = $first_name = $last_name = $employee_role = $facility_id = "";
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                $error_message = "Error creating employee: " . $e->getMessage();
            }
        }
    }
}

// Function to generate random password
function generate_random_password($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_=+';
    $password = '';
    $max = strlen($chars) - 1;
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, $max)];
    }
    return $password;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Employee — Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen">

<?php $nav_back_url = 'manage_users.php'; $nav_back_text = 'Users'; ?>
<?php include '_nav.php'; ?>

<div class="max-w-2xl mx-auto px-4 py-8">

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-black text-gray-900">Add Employee</h1>
            <p class="text-sm text-gray-500 mt-0.5">Create a new employee account with a temporary password.</p>
        </div>
        <a href="manage_users.php" class="text-sm text-[#004B87] hover:underline"><i class="fas fa-arrow-left mr-1"></i>Back to Users</a>
    </div>

    <?php if (!empty($success_message)): ?>
    <div class="bg-green-50 border-l-4 border-green-500 text-green-800 p-4 rounded mb-6 text-sm">
        <p class="font-semibold"><i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($success_message) ?></p>
        <p class="mt-1 text-green-700">Share this temporary password securely. The employee will be prompted to change it on first login.</p>
    </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
    <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded mb-6 text-sm"><i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <form method="post" action="add_employee.php">

            <h2 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-5">Account Info</h2>
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Email Address <span class="text-red-500">*</span></label>
                <input type="email" name="email" required value="<?= isset($email) ? htmlspecialchars($email) : '' ?>"
                       class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004B87]"
                       placeholder="employee@postal.com">
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">First Name <span class="text-red-500">*</span></label>
                    <input type="text" name="first_name" required value="<?= isset($first_name) ? htmlspecialchars($first_name) : '' ?>"
                           class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004B87]">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Last Name <span class="text-red-500">*</span></label>
                    <input type="text" name="last_name" required value="<?= isset($last_name) ? htmlspecialchars($last_name) : '' ?>"
                           class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004B87]">
                </div>
            </div>

            <div class="border-t border-gray-100 pt-5 mt-2 mb-4">
                <h2 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-5">Role & Facility</h2>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Role <span class="text-red-500">*</span></label>
                        <select name="employee_role" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004B87]">
                            <option value="" disabled <?= !isset($employee_role) ? 'selected' : '' ?>>Select Role</option>
                            <?php foreach (['Clerk','Driver','Sorting Staff','Pilot','Customer Support','Manager','Admin'] as $er): ?>
                            <option value="<?= $er ?>" <?= isset($employee_role) && $employee_role == $er ? 'selected' : '' ?>><?= $er ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Facility <span class="text-red-500">*</span></label>
                        <select name="facility_id" required class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004B87]">
                            <option value="" disabled <?= !isset($facility_id) ? 'selected' : '' ?>>Select Facility</option>
                            <?php if ($facilities_result && $facilities_result->num_rows > 0): ?>
                                <?php while ($facility = $facilities_result->fetch_assoc()): ?>
                                <option value="<?= $facility['facility_id'] ?>" <?= isset($facility_id) && $facility_id == $facility['facility_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($facility['facility_name']) ?> (<?= $facility['type'] ?>)
                                </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="bg-blue-50 border border-blue-100 rounded-lg p-3 text-xs text-blue-700 mb-5">
                <i class="fas fa-info-circle mr-1"></i> A temporary password will be auto-generated. The employee must change it on first login.
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                <a href="manage_users.php" class="px-5 py-2 rounded-lg border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-100 transition">Cancel</a>
                <button type="submit" class="bg-[#004B87] hover:bg-blue-900 text-white px-5 py-2 rounded-lg text-sm font-semibold transition">
                    <i class="fas fa-id-badge mr-2"></i>Add Employee
                </button>
            </div>
        </form>
    </div>
</div>
</body>
</html>