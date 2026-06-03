<?php
session_start();
require_once '../functions.php';
require_once '../db_connect.php';

// Check if user is logged in as an employee or admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || 
    ($_SESSION['role'] != 'Employee' && $_SESSION['role'] != 'Admin')) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'Employee';
$stmt = $conn->prepare("SELECT e.first_name, e.last_name, e.role as employee_role, e.facility_id,
                        CONCAT(f.city, ', ', f.state, ' - ', f.type) as facility_name
                        FROM Employee e JOIN Facility f ON e.facility_id = f.facility_id WHERE e.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$employee = $stmt->get_result()->fetch_assoc();
$name = $employee ? trim($employee['first_name'] . ' ' . $employee['last_name']) : ($_SESSION['name'] ?? 'Employee');
if ($employee) {
    $employee['role'] = $employee['employee_role'];
}
$unread_admin_messages = 0;
if ($role !== 'Admin' && $employee) {
    $msg_stmt = $conn->prepare("SELECT COUNT(*) as count FROM Admin_Messages WHERE sender_id = ? AND status = 'Replied'");
    $msg_stmt->bind_param("i", $user_id);
    $msg_stmt->execute();
    $unread_admin_messages = (int)($msg_stmt->get_result()->fetch_assoc()['count'] ?? 0);
}

// Get customers for dropdowns
$customers_query = "SELECT user_id, CONCAT(first_name, ' ', last_name) AS name, 
                    phone, street_address, city, state, postal_code 
                    FROM Customer ORDER BY last_name, first_name";
$customers_result = $conn->query($customers_query);
$customers = [];
while ($row = $customers_result->fetch_assoc()) {
    $customers[] = $row;
}

// Get employee's facility
$employee_query = "SELECT f.facility_id, f.city, f.state, f.type, f.postal_code
                  FROM Employee e 
                  JOIN Facility f ON e.facility_id = f.facility_id 
                  WHERE e.user_id = ?";
$stmt = $conn->prepare($employee_query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$facility = $stmt->get_result()->fetch_assoc();

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

// Display error message if no facility is assigned to the employee
if (!$facility) {
    $error_message = "Error: You are not assigned to a facility. Please contact an administrator.";
}

// Get package size options
$sizes = ['Small Envelope', 'Medium Envelope', 'Large Envelope', 'Small Box', 'Medium Box', 'Large Box', 'Extra Large Box'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Shipment - POSTAL PRO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap');
        * { font-family: 'Open Sans', sans-serif; }
        body { background-color: #f8f9fa; }
        .action-btn { background-color: #004B87; transition: background-color 0.3s; }
        .action-btn:hover { background-color: #003366; }
        .accent-btn { background-color: #DA291C; transition: background-color 0.3s; }
        .accent-btn:hover { background-color: #b52218; }
        .section-title { color: #004B87; }
        .required-field::after { content: " *"; color: #DA291C; }
    </style>
</head>
<body class="bg-gray-50">
<?php $emp_base = '../'; include '../_employee_nav.php'; ?>

    <div class="container mx-auto px-4 py-8 max-w-6xl">
        <header class="mb-6">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center">
                <h1 class="text-3xl font-bold section-title">Create Shipment</h1>
                <div class="mt-4 md:mt-0 bg-white shadow-sm p-4 rounded-lg">
                    <p class="mb-1"><span class="font-semibold">Welcome,</span> <?= htmlspecialchars($name) ?> | <span class="font-medium"><?= htmlspecialchars(isset($employee['role']) ? $employee['role'] : $role) ?></span></p>
                    <p><span class="font-semibold">Facility:</span> <?= htmlspecialchars(isset($employee['facility_name']) ? $employee['facility_name'] : (isset($facility) && $facility ? $facility['city'] . ', ' . $facility['state'] . ' - ' . $facility['type'] : '—')) ?></p>
                </div>
            </div>
        </header>

        <nav class="bg-white shadow-sm p-4 rounded-lg mb-6">
            <ul class="flex flex-wrap space-x-2 md:space-x-6">
                <?php if ($role == 'Admin'): ?>
                <li><a href="../admin/manage_users.php" class="text-gray-700 hover:text-[#DA291C]">Manage Users</a></li>
                <li><a href="../admin/manage_facilities.php" class="text-gray-700 hover:text-[#DA291C]">Manage Facilities</a></li>
                <li><a href="../admin/manage_vehicles.php" class="text-gray-700 hover:text-[#DA291C]">Manage Vehicles</a></li>
                <li><a href="../admin/inbox.php" class="text-gray-700 hover:text-[#DA291C]">Admin Inbox</a></li>
                <?php else: ?>
                <li><a href="../employee/contact_admin.php" class="text-gray-700 hover:text-[#DA291C] flex items-center">Contact Admin<?php if ($unread_admin_messages > 0): ?> <span class="ml-2 bg-red-600 text-white text-xs px-2 py-1 rounded-full"><?= $unread_admin_messages ?></span><?php endif; ?></a></li>
                <?php endif; ?>
                <?php if (isset($employee) && !empty($employee['role']) && $employee['role'] == 'Clerk'): ?>
                <li><a href="new_package.php" class="text-gray-700 hover:text-[#DA291C] border-b-2 border-[#DA291C] pb-1 font-medium">Create Shipment</a></li>
                <li><a href="../shop/inventory_management.php" class="text-gray-700 hover:text-[#DA291C]">Inventory Management</a></li>
                <li><a href="search.php" class="text-gray-700 hover:text-[#DA291C]">Package Search &amp; Scan</a></li>
                <?php endif; ?>
                <?php if (isset($employee) && !empty($employee['role']) && $employee['role'] == 'Manager'): ?>
                <li><a href="../shop/inventory_management.php" class="text-gray-700 hover:text-[#DA291C]">Inventory Management</a></li>
                <li><a href="../reports/index.php" class="text-gray-700 hover:text-[#DA291C]">Reports</a></li>
                <li><a href="../trips/manage_trips.php" class="text-gray-700 hover:text-[#DA291C]">Manage Trips</a></li>
                <?php endif; ?>
                <?php if (isset($employee) && !empty($employee['role']) && $employee['role'] == 'Clerk'): ?>
                <li><a href="awaiting_pickup.php" class="text-gray-700 hover:text-[#DA291C]">Pickup</a></li>
                <?php endif; ?>
                <?php if ($role != 'Admin'): ?>
                <li><a href="../edit_profile.php" class="text-gray-700 hover:text-[#DA291C]">Edit Profile</a></li>
                <?php endif; ?>
            </ul>
        </nav>

        <?php if (!empty($_SESSION['error_message'])): ?>
            <div class="bg-red-50 border-l-4 border-[#DA291C] text-red-800 p-4 mb-6 rounded-lg shadow-md"><?= htmlspecialchars($_SESSION['error_message']) ?></div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
        <?php if (isset($error_message)): ?>
            <div class="bg-red-50 border-l-4 border-[#DA291C] text-red-800 p-4 mb-6 rounded-lg shadow-md"><?= htmlspecialchars($error_message) ?></div>
        <?php else: ?>

        <!-- Facility Information -->
        <section class="bg-white p-6 rounded-lg shadow-md mb-6">
            <h2 class="text-xl font-semibold section-title mb-4">Shipment Details</h2>
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <p class="mb-1"><span class="font-semibold">Facility:</span> <?= htmlspecialchars($facility['city'] . ', ' . $facility['state'] . ' - ' . $facility['type']) ?></p>
                    <p class="mb-1"><span class="font-semibold">Facility ZIP Code:</span> <?= htmlspecialchars($facility['postal_code']) ?></p>
                </div>
                <div>
                    <p class="mb-1"><span class="font-semibold">Tracking Number:</span> <?= htmlspecialchars($tracking_number) ?></p>
                    <p class="mb-1"><span class="font-semibold">Date:</span> <?= date('Y-m-d H:i') ?></p>
                </div>
            </div>
        </section>
            
        <form action="package_confirmation.php" method="post" id="packageForm">
            <!-- Hidden fields -->
            <input type="hidden" name="tracking_number" value="<?= htmlspecialchars($tracking_number) ?>">
            <input type="hidden" name="facility_id" value="<?= $facility['facility_id'] ?>">
            <input type="hidden" name="origin_zip" value="<?= htmlspecialchars($facility['postal_code']) ?>">
            
            <div class="grid md:grid-cols-2 gap-6">
                <!-- Left Column -->
                <div>
                    <!-- Sender Information -->
                    <section class="bg-white p-6 rounded-lg shadow-md mb-6">
                        <h3 class="text-xl font-semibold section-title mb-4">Sender Information</h3>
                        
                        <div class="mb-4">
                            <label for="sender_select" class="block mb-2">Select Existing Sender</label>
                            <select class="w-full px-3 py-2 border border-gray-300 rounded" id="sender_select" name="sender_select">
                                <option value="">-- Create New Sender --</option>
                                <?php foreach ($customers as $customer): ?>
                                    <option value="<?= $customer['user_id'] ?>" 
                                            data-zip="<?= htmlspecialchars($customer['postal_code']) ?>">
                                        <?= htmlspecialchars($customer['name']) ?> - 
                                        <?= htmlspecialchars($customer['city'] . ', ' . $customer['state']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="sender_id" id="sender_id" value="">
                        </div>
                        
                        <div id="sender_fields" class="hidden">
                            <div class="grid md:grid-cols-2 gap-4">
                                <div class="mb-4">
                                    <label for="sender_first_name" class="block mb-2 required-field">First Name</label>
                                    <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded" id="sender_first_name" name="sender_first_name">
                                </div>
                                <div class="mb-4">
                                    <label for="sender_last_name" class="block mb-2 required-field">Last Name</label>
                                    <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded" id="sender_last_name" name="sender_last_name">
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="sender_phone" class="block mb-2 required-field">Phone</label>
                                <input type="tel" class="w-full px-3 py-2 border border-gray-300 rounded" id="sender_phone" name="sender_phone" placeholder="123-456-7890">
                            </div>
                            
                            <div class="mb-4">
                                <label for="sender_street" class="block mb-2 required-field">Street Address</label>
                                <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded" id="sender_street" name="sender_street">
                            </div>
                            
                            <div class="grid grid-cols-12 gap-4">
                                <div class="col-span-5 mb-4">
                                    <label for="sender_city" class="block mb-2 required-field">City</label>
                                    <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded" id="sender_city" name="sender_city">
                                </div>
                                <div class="col-span-3 mb-4">
                                    <label for="sender_state" class="block mb-2 required-field">State</label>
                                    <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded" id="sender_state" name="sender_state" maxlength="2">
                                </div>
                                <div class="col-span-4 mb-4">
                                    <label for="sender_zip" class="block mb-2 required-field">ZIP Code</label>
                                    <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded" id="sender_zip" name="sender_zip" maxlength="5" pattern="[0-9]{5}">
                                </div>
                            </div>
                        </div>
                    </section>
                    
                    <!-- Receiver Information -->
                    <section class="bg-white p-6 rounded-lg shadow-md mb-6">
                        <h3 class="text-xl font-semibold section-title mb-4">Receiver Information</h3>
                        
                        <div class="mb-4">
                            <label for="receiver_select" class="block mb-2">Select Existing Receiver</label>
                            <select class="w-full px-3 py-2 border border-gray-300 rounded" id="receiver_select" name="receiver_select">
                                <option value="">-- Create New Receiver --</option>
                                <?php foreach ($customers as $customer): ?>
                                    <option value="<?= $customer['user_id'] ?>" 
                                            data-zip="<?= htmlspecialchars($customer['postal_code']) ?>">
                                        <?= htmlspecialchars($customer['name']) ?> - 
                                        <?= htmlspecialchars($customer['city'] . ', ' . $customer['state']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="receiver_id" id="receiver_id" value="">
                        </div>
                        
                        <div id="receiver_fields" class="hidden">
                            <div class="grid md:grid-cols-2 gap-4">
                                <div class="mb-4">
                                    <label for="receiver_first_name" class="block mb-2 required-field">First Name</label>
                                    <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded" id="receiver_first_name" name="receiver_first_name">
                                </div>
                                <div class="mb-4">
                                    <label for="receiver_last_name" class="block mb-2 required-field">Last Name</label>
                                    <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded" id="receiver_last_name" name="receiver_last_name">
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="receiver_phone" class="block mb-2 required-field">Phone</label>
                                <input type="tel" class="w-full px-3 py-2 border border-gray-300 rounded" id="receiver_phone" name="receiver_phone" placeholder="123-456-7890">
                            </div>
                            
                            <div class="mb-4">
                                <label for="receiver_street" class="block mb-2 required-field">Street Address</label>
                                <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded" id="receiver_street" name="receiver_street">
                            </div>
                            
                            <div class="grid grid-cols-12 gap-4">
                                <div class="col-span-5 mb-4">
                                    <label for="receiver_city" class="block mb-2 required-field">City</label>
                                    <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded" id="receiver_city" name="receiver_city">
                                </div>
                                <div class="col-span-3 mb-4">
                                    <label for="receiver_state" class="block mb-2 required-field">State</label>
                                    <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded" id="receiver_state" name="receiver_state" maxlength="2">
                                </div>
                                <div class="col-span-4 mb-4">
                                    <label for="receiver_zip" class="block mb-2 required-field">ZIP Code</label>
                                    <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded" id="receiver_zip" name="receiver_zip" maxlength="5" pattern="[0-9]{5}">
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
                
                <!-- Right Column -->
                <div>
                    <!-- Package Details -->
                    <section class="bg-white p-6 rounded-lg shadow-md mb-6">
                        <h3 class="text-xl font-semibold section-title mb-4">Package Details</h3>
                        
                        <div class="mb-4">
                            <label for="size" class="block mb-2 required-field">Package Size</label>
                            <select class="w-full px-3 py-2 border border-gray-300 rounded" id="size" name="size" required>
                                <option value="">-- Select Size --</option>
                                <?php foreach ($sizes as $size): ?>
                                    <option value="<?= htmlspecialchars($size) ?>"><?= htmlspecialchars($size) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label for="weight" class="block mb-2 required-field">Weight (lbs)</label>
                            <input type="number" class="w-full px-3 py-2 border border-gray-300 rounded" id="weight" name="weight" step="0.1" min="0.1" required>
                        </div>
                        
                        <div class="mb-4">
                            <label for="shipping_speed" class="block mb-2 required-field">Shipping Speed</label>
                            <select class="w-full px-3 py-2 border border-gray-300 rounded" id="shipping_speed" name="shipping_speed" required>
                                <option value="Economy">Economy (5-7 days) - 20% Discount</option>
                                <option value="Standard" selected>Standard (3-5 days) - Regular Rate</option>
                                <option value="Express">Express (1-2 days) - 70% Premium</option>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <div class="flex items-center">
                                <input type="checkbox" class="h-4 w-4 text-blue-600" id="signature_required" name="signature_required" value="Y">
                                <label class="ml-2" for="signature_required">Signature Required ($3.50 additional fee)</label>
                            </div>
                        </div>
                    </section>
                    
                    <!-- Additional Information -->
                    <section class="bg-white p-6 rounded-lg shadow-md mb-6">
                        <h3 class="text-xl font-semibold section-title mb-4">Additional Information</h3>
                        
                        <div class="mb-4">
                            <label for="notes" class="block mb-2">Notes (Optional)</label>
                            <textarea class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-[#004B87] focus:border-[#004B87]" id="notes" name="notes" rows="3"></textarea>
                        </div>
                        
                        <div class="bg-gray-50 border-l-4 border-[#004B87] text-gray-700 p-4 rounded">
                            <p class="text-sm">
                                <span class="font-semibold">Note:</span> After submitting this form, you'll be taken to a confirmation page 
                                where you can complete the payment process or cancel the transaction.
                            </p>
                        </div>
                    </section>
                </div>
            </div>
            
            <div class="flex justify-end gap-3 mt-6">
                <a href="../employee_dashboard.php" class="px-4 py-2 rounded border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">Cancel</a>
                <button type="submit" class="accent-btn text-white px-4 py-2 rounded">Continue to Payment</button>
            </div>
        </form>
        <?php endif; ?>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sender selection
            const senderSelect = document.getElementById('sender_select');
            const senderFields = document.getElementById('sender_fields');
            const senderId = document.getElementById('sender_id');
            
            senderSelect.addEventListener('change', function() {
                if (this.value === '') {
                    senderFields.classList.remove('hidden');
                    senderId.value = '';
                } else {
                    senderFields.classList.add('hidden');
                    senderId.value = this.value;
                }
            });
            
            // Initialize sender fields visibility
            if (senderSelect.value === '') {
                senderFields.classList.remove('hidden');
            }
            
            // Receiver selection
            const receiverSelect = document.getElementById('receiver_select');
            const receiverFields = document.getElementById('receiver_fields');
            const receiverId = document.getElementById('receiver_id');
            
            receiverSelect.addEventListener('change', function() {
                if (this.value === '') {
                    receiverFields.classList.remove('hidden');
                    receiverId.value = '';
                } else {
                    receiverFields.classList.add('hidden');
                    receiverId.value = this.value;
                }
            });
            
            // Initialize receiver fields visibility
            if (receiverSelect.value === '') {
                receiverFields.classList.remove('hidden');
            }
            
            // Form validation
            document.getElementById('packageForm').addEventListener('submit', function(event) {
                let isValid = true;
                
                // Validate sender information
                if (senderId.value === '') {
                    const requiredSenderFields = [
                        'sender_first_name', 'sender_last_name', 'sender_phone', 
                        'sender_street', 'sender_city', 'sender_state', 'sender_zip'
                    ];
                    
                    requiredSenderFields.forEach(function(field) {
                        const input = document.getElementById(field);
                        if (!input.value) {
                            input.classList.add('border-red-500');
                            isValid = false;
                        } else {
                            input.classList.remove('border-red-500');
                        }
                    });
                }
                
                // Validate receiver information
                if (receiverId.value === '') {
                    const requiredReceiverFields = [
                        'receiver_first_name', 'receiver_last_name', 'receiver_phone', 
                        'receiver_street', 'receiver_city', 'receiver_state', 'receiver_zip'
                    ];
                    
                    requiredReceiverFields.forEach(function(field) {
                        const input = document.getElementById(field);
                        if (!input.value) {
                            input.classList.add('border-red-500');
                            isValid = false;
                        } else {
                            input.classList.remove('border-red-500');
                        }
                    });
                }
                
                // Validate package details
                const packageFields = ['size', 'weight', 'shipping_speed'];
                packageFields.forEach(function(field) {
                    const input = document.getElementById(field);
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
