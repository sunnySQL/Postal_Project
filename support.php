<?php
session_start();
require_once 'db_connect.php';
require_once 'functions.php';
//debug enabled
// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php?redirect=support.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$name = $_SESSION['name'];
$message = '';
$error = '';

// Create a new support ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_ticket'])) {
    $issue_type = $_POST['issue_type'] ?? '';
    $package_id = !empty($_POST['package_id']) ? $_POST['package_id'] : null;
    $description = $_POST['description'] ?? '';
    $selected_support_rep = !empty($_POST['support_rep']) ? $_POST['support_rep'] : null;
    
    // Validate input
    if (empty($issue_type) || empty($description)) {
        $error = "Please fill in all required fields.";
    } else {
        // Check if package exists and belongs to user if package ID is provided
        if (!empty($package_id)) {
            $check_package = $conn->prepare("SELECT * FROM Package WHERE tracking_number = ? AND (sender_id = ? OR receiver_id = ?)");
            $check_package->bind_param("sii", $package_id, $user_id, $user_id);
            $check_package->execute();
            $package_result = $check_package->get_result();
            
            if ($package_result->num_rows === 0) {
                $error = "Package not found or does not belong to you.";
            }
        }
        
        if (empty($error)) {
            // Use selected support rep if provided, otherwise assign randomly
            if (!empty($selected_support_rep)) {
                $assigned_employee_id = $selected_support_rep;
            } else {
                // Assign to a random employee with Customer Support role
                $get_support_emp = $conn->prepare("SELECT e.user_id FROM Employee e 
                                                JOIN Users u ON e.user_id = u.user_id 
                                                WHERE e.role = 'Customer Support'
                                                ORDER BY RAND() LIMIT 1");
                $get_support_emp->execute();
                $support_result = $get_support_emp->get_result();
                
                if ($support_result->num_rows > 0) {
                    $support_emp = $support_result->fetch_assoc();
                    $assigned_employee_id = $support_emp['user_id'];
                } else {
                    // If no customer support employees, assign to a random admin
                    $get_admin = $conn->prepare("SELECT e.user_id FROM Employee e 
                                              JOIN Users u ON e.user_id = u.user_id 
                                              WHERE u.role = 'Admin'
                                              ORDER BY RAND() LIMIT 1");
                    $get_admin->execute();
                    $admin_result = $get_admin->get_result();
                    
                    if ($admin_result->num_rows > 0) {
                        $admin = $admin_result->fetch_assoc();
                        $assigned_employee_id = $admin['user_id'];
                    } else {
                        $assigned_employee_id = null; // No support staff or admin available
                    }
                }
            }
            
            // Insert ticket
            $initial_chat_log = json_encode([
                [
                    'timestamp' => date('Y-m-d H:i:s'),
                    'user_id' => $user_id,
                    'name' => $name,
                    'role' => 'Customer',
                    'message' => $description
                ]
            ]);

            $stmt = $conn->prepare("INSERT INTO Support_Ticket (user_id, package_id, issue_type, status, assigned_employee_id, resolution_notes, chat_log) 
                                  VALUES (?, ?, ?, 'Open', ?, ?, ?)");
            $stmt->bind_param("isssss", $user_id, $package_id, $issue_type, $assigned_employee_id, $description, $initial_chat_log);
            
            if ($stmt->execute()) {
                $ticket_id = $conn->insert_id;
                $message = "Support ticket created successfully! Your ticket ID is #$ticket_id";
                
                // Redirect to view the new ticket
                header("Location: support_detail.php?id=$ticket_id&created=1");
                exit();
            } else {
                $error = "Error creating ticket: " . $conn->error;
            }
        }
    }
}

// Get user's packages for dropdown
$get_packages = $conn->prepare("SELECT p.tracking_number, p.status, 
                              DATE_FORMAT(p.timestamp_created, '%m/%d/%Y') as created_date
                              FROM Package p
                              WHERE p.sender_id = ? OR p.receiver_id = ?
                              ORDER BY p.timestamp_created DESC
                              LIMIT 20");
$get_packages->bind_param("ii", $user_id, $user_id);
$get_packages->execute();
$packages = $get_packages->get_result();

// Get all customer support representatives for dropdown
$get_support_reps = $conn->prepare("SELECT e.user_id, e.first_name, e.last_name 
                                  FROM Employee e
                                  WHERE e.role = 'Customer Support'
                                  ORDER BY e.last_name, e.first_name");
$get_support_reps->execute();
$support_reps = $get_support_reps->get_result();

// Get user's tickets - either all tickets or only active ones based on action
$show_all_tickets = isset($_GET['action']) && $_GET['action'] === 'view_all';

if ($show_all_tickets) {
    // Get ALL tickets (active and resolved) when specifically requested
    $get_tickets = $conn->prepare("SELECT t.*, 
                                DATE_FORMAT(MAX(th.timestamp), '%m/%d/%Y') as last_update
                                FROM Support_Ticket t
                                LEFT JOIN Tracking_History th ON t.package_id = th.tracking_number
                                WHERE t.user_id = ?
                                GROUP BY t.ticket_id
                                ORDER BY t.ticket_id DESC");
} else {
    // Only get active tickets by default
    $get_tickets = $conn->prepare("SELECT t.*, 
                                DATE_FORMAT(MAX(th.timestamp), '%m/%d/%Y') as last_update
                                FROM Support_Ticket t
                                LEFT JOIN Tracking_History th ON t.package_id = th.tracking_number
                                WHERE t.user_id = ? AND t.status != 'Resolved'
                                GROUP BY t.ticket_id
                                ORDER BY t.ticket_id DESC");
}

$get_tickets->bind_param("i", $user_id);
$get_tickets->execute();
$tickets = $get_tickets->get_result();

// Determine if we're creating a new ticket or viewing the default page
$action = isset($_GET['action']) ? $_GET['action'] : 'default';
$create_new = $action === 'new';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Support | POSTAL PRO</title>
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
    </style>
</head>
<body>
    <?php include '_public_nav.php'; ?>

    <div class="container mx-auto px-4 py-8 max-w-6xl mt-[60px]">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold section-title">Customer Support</h1>
            <a href="customer_dashboard.php" class="text-[#004B87] hover:text-[#DA291C]">
                <i class="fas fa-arrow-left mr-1"></i> Back to Dashboard
            </a>
        </div>

        <?php if (!empty($message)): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
            <p><?= $message ?></p>
        </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
            <p><?= $error ?></p>
        </div>
        <?php endif; ?>

        <?php if ($action === 'new'): ?>
        <section class="bg-white shadow-sm p-6 rounded-lg mb-6">
            <h2 class="text-2xl font-semibold mb-4 section-title">Create New Support Ticket</h2>
            <form action="support.php" method="post">
                <div class="mb-4">
                    <label for="issue_type" class="block text-gray-700 font-medium mb-2">Issue Type *</label>
                    <select name="issue_type" id="issue_type" class="w-full p-3 border border-gray-300 rounded" required>
                        <option value="">Select Issue Type</option>
                        <option value="Delayed">Delayed Package</option>
                        <option value="Lost">Lost Package</option>
                        <option value="Damaged">Damaged Package</option>
                        <option value="Payment Issue">Payment Issue</option>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label for="package_id" class="block text-gray-700 font-medium mb-2">Related Package (Optional)</label>
                    <select name="package_id" id="package_id" class="w-full p-3 border border-gray-300 rounded">
                        <option value="">Select Package</option>
                        <?php if ($packages->num_rows > 0): ?>
                            <?php while ($package = $packages->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($package['tracking_number']) ?>">
                                    <?= htmlspecialchars($package['tracking_number']) ?> - 
                                    <?= htmlspecialchars($package['status']) ?> 
                                    (<?= htmlspecialchars($package['created_date']) ?>)
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label for="support_rep" class="block text-gray-700 font-medium mb-2">Select Support Representative</label>
                    <select name="support_rep" id="support_rep" class="w-full p-3 border border-gray-300 rounded">
                        <option value="">Select Support Representative</option>
                        <?php if ($support_reps->num_rows > 0): ?>
                            <?php while ($support_rep = $support_reps->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($support_rep['user_id']) ?>">
                                    <?= htmlspecialchars($support_rep['first_name']) ?> <?= htmlspecialchars($support_rep['last_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                    <p class="text-sm text-gray-600 mt-1">You can choose a specific customer support representative or leave it blank for random assignment.</p>
                </div>
                
                <div class="mb-4">
                    <label for="description" class="block text-gray-700 font-medium mb-2">Description *</label>
                    <textarea name="description" id="description" rows="5" 
                              class="w-full p-3 border border-gray-300 rounded" 
                              placeholder="Please describe your issue in detail..." required></textarea>
                </div>
                
                <div class="flex justify-end gap-4">
                    <a href="support.php" class="px-4 py-2 border border-gray-300 rounded text-gray-700">Cancel</a>
                    <button type="submit" name="create_ticket" class="accent-btn text-white px-4 py-2 rounded">
                        Create Ticket
                    </button>
                </div>
            </form>
        </section>
        <?php else: ?>
        <section class="bg-white shadow-sm p-6 rounded-lg mb-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-2xl font-semibold section-title">
                    <?= $show_all_tickets ? 'All Support Tickets' : 'Active Support Tickets' ?>
                </h2>
                <a href="support.php?action=new" class="accent-btn text-white px-4 py-2 rounded">
                    <i class="fas fa-plus mr-1"></i> Create Ticket
                </a>
            </div>
            
            <?php if ($tickets->num_rows > 0): ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-100 text-left">
                            <th class="px-4 py-2">Ticket ID</th>
                            <th class="px-4 py-2">Issue Type</th>
                            <th class="px-4 py-2">Status</th>
                            <th class="px-4 py-2">Package ID</th>
                            <th class="px-4 py-2">Last Update</th>
                            <th class="px-4 py-2">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($ticket = $tickets->fetch_assoc()): ?>
                        <tr class="border-b border-gray-200 hover:bg-gray-50">
                            <td class="px-4 py-2">#<?= htmlspecialchars($ticket['ticket_id']) ?></td>
                            <td class="px-4 py-2"><?= htmlspecialchars($ticket['issue_type']) ?></td>
                            <td class="px-4 py-2">
                                <span class="<?php
                                    switch($ticket['status']) {
                                        case 'Open': echo 'bg-green-100 text-green-800'; break;
                                        case 'In Progress': echo 'bg-blue-100 text-blue-800'; break;
                                        case 'Resolved': echo 'bg-gray-100 text-gray-800'; break;
                                        default: echo 'bg-gray-100 text-gray-800';
                                    }
                                ?> px-2 py-1 rounded-full text-xs">
                                    <?= htmlspecialchars($ticket['status']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-2"><?= $ticket['package_id'] ? htmlspecialchars($ticket['package_id']) : 'N/A' ?></td>
                            <td class="px-4 py-2"><?= $ticket['last_update'] ? htmlspecialchars($ticket['last_update']) : 'N/A' ?></td>
                            <td class="px-4 py-2">
                                <a href="support_detail.php?id=<?= htmlspecialchars($ticket['ticket_id']) ?>" 
                                   class="text-[#004B87] hover:text-[#DA291C]">View</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-8">
                <p class="text-gray-600 mb-4">You don't have any support tickets yet.</p>
                <a href="support.php?action=new" class="accent-btn text-white px-4 py-2 rounded">
                    Create Your First Support Ticket
                </a>
            </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <footer class="text-center mt-10 py-6 bg-[#004B87] text-white rounded-lg">
            <p>&copy; <?= date('Y') ?> Postal Service Management System</p>
        </footer>
    </div>
</body>
</html> 