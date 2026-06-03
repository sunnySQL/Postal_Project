<?php
// Add error reporting for debugging
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../db_connect.php';
require_once '../functions.php';

// Only logged in employees can access this page
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php");
    exit();
}

// Check if role is appropriate
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Clerk', 'Driver', 'Manager', 'Admin', 'Employee'])) {
    header("Location: ../unauthorized.php");
    exit();
}

$error = '';
$success = '';

// Check if it's a view request for a specific message
$message_detail = null;
$replies = [];
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $message_id = (int)$_GET['view'];
    
    // Fetch the message details
    $message_query = "SELECT m.*, 
                     u.email as sender_email,
                     DATE_FORMAT(m.created_at, '%M %d, %Y at %h:%i %p') as formatted_date
                     FROM Admin_Messages m
                     JOIN users u ON m.sender_id = u.user_id
                     WHERE m.message_id = ? AND m.sender_id = ?";
    $stmt = $conn->prepare($message_query);
    $stmt->bind_param("ii", $message_id, $_SESSION['user_id']);
    $stmt->execute();
    $message_detail = $stmt->get_result()->fetch_assoc();
    
    // If not found, try again without sender filtering - it might be a message the user can view but didn't create
    if (!$message_detail) {
        $alt_query = "SELECT m.*, 
                     u.email as sender_email,
                     DATE_FORMAT(m.created_at, '%M %d, %Y at %h:%i %p') as formatted_date
                     FROM Admin_Messages m
                     JOIN users u ON m.sender_id = u.user_id
                     WHERE m.message_id = ?";
        $alt_stmt = $conn->prepare($alt_query);
        $alt_stmt->bind_param("i", $message_id);
        $alt_stmt->execute();
        $message_detail = $alt_stmt->get_result()->fetch_assoc();
    }
    
    // Debug the message detail
    if (!$message_detail) {
        error_log("Message not found: ID=$message_id, User=".$_SESSION['user_id']);
    }
    
    if ($message_detail) {
        // Fetch replies for this message
        $replies_query = "SELECT r.*, 
                        u.email as sender_email,
                        u.role as sender_role,
                        DATE_FORMAT(r.created_at, '%M %d, %Y at %h:%i %p') as formatted_date
                        FROM Message_Replies r
                        JOIN users u ON r.sender_id = u.user_id
                        WHERE r.message_id = ?
                        ORDER BY r.created_at ASC";
        $replies_stmt = $conn->prepare($replies_query);
        $replies_stmt->bind_param("i", $message_id);
        $replies_stmt->execute();
        $replies_result = $replies_stmt->get_result();
        
        // Log reply count for debugging
        $reply_count = $replies_result->num_rows;
        error_log("Found $reply_count replies for message ID=$message_id");
        
        while ($reply = $replies_result->fetch_assoc()) {
            $replies[] = $reply;
        }
        
        // Handle reply submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply'])) {
            $reply_text = trim($_POST['reply'] ?? '');
            
            if (empty($reply_text)) {
                $error = "Reply cannot be empty.";
            } else {
                try {
                    // Check if Message_Replies table exists, create if not
                    $table_check = $conn->query("SHOW TABLES LIKE 'Message_Replies'");
                    if ($table_check->num_rows == 0) {
                        $create_table_sql = "CREATE TABLE IF NOT EXISTS Message_Replies (
                            reply_id INT AUTO_INCREMENT PRIMARY KEY,
                            message_id INT NOT NULL,
                            sender_id INT NOT NULL,
                            reply_text TEXT NOT NULL,
                            is_admin BOOLEAN DEFAULT FALSE,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            FOREIGN KEY (message_id) REFERENCES Admin_Messages(message_id) ON DELETE CASCADE,
                            FOREIGN KEY (sender_id) REFERENCES users(user_id) ON DELETE CASCADE
                        )";
                        $conn->query($create_table_sql);
                        
                        if ($conn->error) {
                            $error = "Error creating replies table: " . $conn->error;
                            throw new Exception($error);
                        }
                    }
                    
                    // Insert reply
                    $is_admin = ($_SESSION['role'] === 'Admin') ? 1 : 0;
                    $reply_stmt = $conn->prepare("INSERT INTO Message_Replies 
                                              (message_id, sender_id, reply_text, is_admin) 
                                              VALUES (?, ?, ?, ?)");
                    $reply_stmt->bind_param("iisi", $message_id, $_SESSION['user_id'], $reply_text, $is_admin);
                    
                    if ($reply_stmt->execute()) {
                        // Update the status of the message - make sure it's a valid status value (max 20 chars)
                        $new_status = ($_SESSION['role'] === 'Admin') ? 'Replied' : 'Updated';
                        
                        // Ensure status doesn't exceed column length
                        if (strlen($new_status) > 20) {
                            $new_status = substr($new_status, 0, 20);
                        }
                        
                        try {
                            $update_stmt = $conn->prepare("UPDATE Admin_Messages SET status = ? WHERE message_id = ?");
                            $update_stmt->bind_param("si", $new_status, $message_id);
                            
                            if (!$update_stmt->execute()) {
                                // Log the error but don't stop execution
                                error_log("Error updating message status: " . $conn->error);
                            }
                        } catch (Exception $e) {
                            // Log the error but continue execution
                            error_log("Exception updating message status: " . $e->getMessage());
                        }
                        
                        $success = "Your reply has been added.";
                        // Redirect to prevent form resubmission
                        header("Location: contact_admin.php?view=".$message_id."&success=1");
                        exit();
                    } else {
                        $error = "Error adding reply: " . $conn->error;
                    }
                } catch (Exception $e) {
                    $error = "Database error: " . $e->getMessage();
                }
            }
        }
    } else {
        // Message not found or doesn't belong to the current user
        header("Location: contact_admin.php?error=1");
        exit();
    }
} else {
    // Handle new message submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['reply'])) {
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $type = trim($_POST['type'] ?? '');
        $related_id = !empty($_POST['related_id']) ? (int)$_POST['related_id'] : null;
        
        // Basic validation
        if (empty($subject) || empty($message) || empty($type)) {
            $error = "Please fill in all required fields.";
        } else {
            try {
                // Check if table exists
                $table_check = $conn->query("SHOW TABLES LIKE 'Admin_Messages'");
                if ($table_check->num_rows == 0) {
                    // Table doesn't exist, create it
                    $create_table_sql = "CREATE TABLE IF NOT EXISTS Admin_Messages (
                        message_id INT AUTO_INCREMENT PRIMARY KEY,
                        sender_id INT NOT NULL,
                        subject VARCHAR(255) NOT NULL,
                        message TEXT NOT NULL,
                        type VARCHAR(50) NOT NULL DEFAULT 'General',
                        related_id INT NULL,
                        status VARCHAR(20) NOT NULL DEFAULT 'New',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        FOREIGN KEY (sender_id) REFERENCES users(user_id) ON DELETE CASCADE
                    )";
                    $conn->query($create_table_sql);
                    
                    if ($conn->error) {
                        $error = "Error creating table: " . $conn->error;
                        throw new Exception($error);
                    }
                }
                
                $stmt = $conn->prepare("INSERT INTO Admin_Messages (sender_id, subject, message, type, related_id) 
                                      VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("isssi", $_SESSION['user_id'], $subject, $message, $type, $related_id);
                
                if ($stmt->execute()) {
                    $new_message_id = $conn->insert_id;
                    $success = "Your message has been sent to the admin. You will be notified of any updates.";
                    
                    // Clear form data after successful submission
                    $subject = $message = $related_id = '';
                    $type = 'General';
                    
                    // Redirect to view the new message
                    header("Location: contact_admin.php?view=".$new_message_id."&success=1");
                    exit();
                } else {
                    $error = "Error sending message: " . $conn->error;
                }
            } catch (Exception $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Get support tickets for reference (if applicable)
$support_tickets = [];
$tickets_query = "SELECT ticket_id, issue_type as subject FROM Support_Ticket 
                 WHERE status != 'Closed' 
                 ORDER BY ticket_id DESC";
$tickets_result = $conn->query($tickets_query);

if ($tickets_result && $tickets_result->num_rows > 0) {
    while ($row = $tickets_result->fetch_assoc()) {
        $support_tickets[] = $row;
    }
}

// Get employees for reference (if manager wants to request termination)
$employees = [];
if (isset($_SESSION['role']) && $_SESSION['role'] === 'Manager') {
    $employees_query = "SELECT user_id, email, role 
                      FROM users 
                      WHERE role IN ('Clerk', 'Driver') 
                      ORDER BY role, email";
    $employees_result = $conn->query($employees_query);
    
    if ($employees_result && $employees_result->num_rows > 0) {
        while ($row = $employees_result->fetch_assoc()) {
            $employees[] = $row;
        }
    }
}

// Set success/error from URL parameters
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success = $message_detail ? "Your reply has been added." : "Your message has been sent to the admin.";
}
if (isset($_GET['error']) && $_GET['error'] == 1) {
    $error = "Message not found or you don't have permission to view it.";
}

// Fetch recent messages if we're not viewing a specific message
$messages = [];
if (!$message_detail) {
    // Fetch recent messages from this user
    $messages_query = "SELECT m.*, 
                      IFNULL(DATE_FORMAT(m.created_at, '%M %d, %Y at %h:%i %p'), 
                             DATE_FORMAT(NOW(), '%M %d, %Y at %h:%i %p')) as formatted_date,
                      (SELECT COUNT(*) FROM Message_Replies WHERE message_id = m.message_id) as reply_count
                      FROM Admin_Messages m
                      WHERE m.sender_id = ?
                      ORDER BY 
                        CASE 
                            WHEN m.status IN ('New', 'Updated', 'Replied') THEN 0 
                            ELSE 1 
                        END,
                        m.updated_at DESC";
    $messages_stmt = $conn->prepare($messages_query);
    $messages_stmt->bind_param("i", $_SESSION['user_id']);
    $messages_stmt->execute();
    $messages_result = $messages_stmt->get_result();
    
    while ($message = $messages_result->fetch_assoc()) {
        $messages[] = $message;
    }
}

// Count unread messages 
$unread_count_query = "SELECT COUNT(*) as unread_count 
                      FROM Admin_Messages 
                      WHERE sender_id = ? AND status = 'Replied'";
$unread_stmt = $conn->prepare($unread_count_query);
$unread_stmt->bind_param("i", $_SESSION['user_id']);
$unread_stmt->execute();
$unread_result = $unread_stmt->get_result()->fetch_assoc();
$unread_count = $unread_result ? (int)$unread_result['unread_count'] : 0;

// Get employee info for header and nav (same as dashboard)
$name = $_SESSION['name'] ?? 'Employee';
$role = $_SESSION['role'] ?? 'Employee';
$employee = ['role' => $role, 'city' => '', 'type' => ''];
$emp_stmt = $conn->prepare("SELECT e.*, f.city, f.type FROM Employee e JOIN Facility f ON e.facility_id = f.facility_id WHERE e.user_id = ?");
$emp_stmt->bind_param("i", $_SESSION['user_id']);
$emp_stmt->execute();
$emp_result = $emp_stmt->get_result();
if ($emp_result->num_rows > 0) {
    $employee = $emp_result->fetch_assoc();
}
$unread_admin_messages = $unread_count;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $message_detail ? "Message #" . $message_detail['message_id'] : "Contact Admin" ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap');
        * { font-family: 'Open Sans', sans-serif; }
        body { background-color: #f8f9fa; }
        .section-title { color: #004B87; }
        .action-btn { background-color: #004B87; transition: background-color 0.3s; }
        .action-btn:hover { background-color: #003366; }
    </style>
</head>
<body class="bg-gray-50">
<?php $emp_base = '../'; include '../_employee_nav.php'; ?>

    <div class="container mx-auto px-4 py-8 max-w-6xl">
        <header class="mb-6">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center">
                <h1 class="text-3xl font-bold section-title">
                    <?= $message_detail ? "Message #" . $message_detail['message_id'] : "Contact Admin" ?>
                    <?php if (!$message_detail && $unread_count > 0): ?>
                        <span class="inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-red-100 bg-red-600 rounded-full ml-2"><?= $unread_count ?> new</span>
                    <?php endif; ?>
                </h1>
                <div class="mt-4 md:mt-0 bg-white shadow-sm p-4 rounded-lg">
                    <p class="mb-1"><span class="font-semibold">Welcome,</span> <?= htmlspecialchars($name) ?> | <span class="font-medium"><?= htmlspecialchars($employee['role']) ?></span></p>
                    <p><span class="font-semibold">Facility:</span> <?= htmlspecialchars($employee['type']) ?> - <?= htmlspecialchars($employee['city']) ?></p>
                </div>
            </div>
        </header>

        <nav class="bg-white shadow-sm p-4 rounded-lg mb-6">
            <ul class="flex flex-wrap space-x-2 md:space-x-6">
                <li><a href="contact_admin.php" class="text-gray-700 hover:text-[#DA291C] border-b-2 border-[#DA291C] pb-1 font-medium flex items-center">
                    Contact Admin
                    <?php if ($unread_admin_messages > 0): ?><span class="ml-2 bg-red-600 text-white text-xs px-2 py-1 rounded-full"><?= $unread_admin_messages ?></span><?php endif; ?>
                </a></li>
                <?php if (isset($employee['role'])): ?>
                <?php if ($employee['role'] == 'Clerk'): ?>
                <li><a href="../package/new_package.php" class="text-gray-700 hover:text-[#DA291C]">Create Shipment</a></li>
                <li><a href="../shop/inventory_management.php" class="text-gray-700 hover:text-[#DA291C]">Inventory Management</a></li>
                <li><a href="../package/search.php" class="text-gray-700 hover:text-[#DA291C]">Package Search &amp; Scan</a></li>
                <?php endif; ?>
                <?php if ($employee['role'] == 'Manager'): ?>
                <li><a href="../shop/shop_dashboard.php" class="text-gray-700 hover:text-[#DA291C]">Shop Overview</a></li>
                <li><a href="../reports/index.php" class="text-gray-700 hover:text-[#DA291C]">Reports</a></li>
                <li><a href="../trips/manage_trips.php" class="text-gray-700 hover:text-[#DA291C]">Manage Trips</a></li>
                <?php endif; ?>
                <?php endif; ?>
                <?php if ($employee['role'] == 'Clerk'): ?>
                <li><a href="../package/awaiting_pickup.php" class="text-gray-700 hover:text-[#DA291C]">Pickup</a></li>
                <?php endif; ?>
                <li><a href="../edit_profile.php" class="text-gray-700 hover:text-[#DA291C]">Edit Profile</a></li>
            </ul>
        </nav>
        
        <?php if (!empty($error)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                <p><?= htmlspecialchars($error) ?></p>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
                <p><?= htmlspecialchars($success) ?></p>
            </div>
        <?php endif; ?>
        
        <?php if ($message_detail): ?>
        <!-- Message Detail View -->
        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
            <div class="flex justify-between mb-4">
                <div>
                    <h2 class="text-xl font-semibold"><?= htmlspecialchars($message_detail['subject']) ?></h2>
                    <p class="text-sm text-gray-500">
                        <span class="font-medium">From:</span> <?= htmlspecialchars($message_detail['sender_email']) ?> | 
                        <span class="font-medium">Sent:</span> <?= htmlspecialchars($message_detail['formatted_date']) ?>
                    </p>
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-semibold
                    <?php 
                    switch($message_detail['status']) {
                        case 'New': echo 'bg-blue-100 text-blue-800'; break;
                        case 'In Progress': echo 'bg-yellow-100 text-yellow-800'; break;
                        case 'Resolved': echo 'bg-green-100 text-green-800'; break;
                        case 'Rejected': echo 'bg-red-100 text-red-800'; break;
                        case 'Replied': echo 'bg-purple-100 text-purple-800'; break;
                        default: echo 'bg-gray-100 text-gray-800';
                    }
                    ?>">
                    <?= htmlspecialchars($message_detail['status']) ?>
                </span>
            </div>
            
            <div class="border-t border-b border-gray-200 py-4 my-4">
                <div class="prose max-w-none">
                    <?= nl2br(htmlspecialchars($message_detail['message'])) ?>
                </div>
            </div>
            
            <!-- Message Type & Related Item Info -->
            <div class="flex flex-wrap gap-2 mb-4 text-sm">
                <span class="bg-gray-100 text-gray-800 px-3 py-1 rounded-full">
                    <?= htmlspecialchars($message_detail['type']) ?>
                </span>
                
                <?php if ($message_detail['related_id']): ?>
                    <span class="bg-gray-100 text-gray-800 px-3 py-1 rounded-full">
                        Related ID: <?= htmlspecialchars($message_detail['related_id']) ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Conversation History -->
        <?php if (count($replies) > 0): ?>
        <div class="mb-6">
            <h3 class="text-lg font-semibold mb-4">Conversation History (<?= count($replies) ?> replies)</h3>
            
            <?php foreach ($replies as $index => $reply): ?>
                <div class="bg-white p-4 rounded-lg shadow-sm mb-3 <?= $reply['is_admin'] ? 'ml-6 border-l-4 border-blue-500' : 'mr-6' ?>">
                    <div class="flex justify-between mb-2">
                        <p class="font-medium text-sm">
                            <?php if ($reply['is_admin']): ?>
                                <span class="text-blue-600">Admin</span>
                            <?php else: ?>
                                <span class="text-gray-700"><?= htmlspecialchars($reply['sender_email']) ?></span>
                                <span class="text-gray-500">(<?= htmlspecialchars($reply['sender_role']) ?>)</span>
                            <?php endif; ?>
                        </p>
                        <p class="text-xs text-gray-500"><?= htmlspecialchars($reply['formatted_date']) ?></p>
                    </div>
                    <div class="prose max-w-none text-gray-700">
                        <?= nl2br(htmlspecialchars($reply['reply_text'])) ?>
                        <div class="text-xs text-gray-400 mt-1 text-right">Reply #<?= $index+1 ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Reply Form -->
        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
            <h3 class="text-lg font-semibold mb-4">Add Reply</h3>
            <form action="contact_admin.php?view=<?= $message_detail['message_id'] ?>" method="post">
                <div class="mb-4">
                    <label for="reply" class="block text-gray-700 font-medium mb-2">Your Reply <span class="text-red-500">*</span></label>
                    <textarea name="reply" id="reply" rows="4"
                              class="w-full px-3 py-2 border border-gray-300 rounded" required
                              placeholder="Enter your reply..."><?= htmlspecialchars($_POST['reply'] ?? '') ?></textarea>
                </div>
                
                <div class="flex justify-end">
                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                        Send Reply
                    </button>
                </div>
            </form>
        </div>
        
        <?php else: ?>
        <!-- New Message Form -->
        <div class="bg-white p-6 rounded-lg shadow-md mb-6">
            <h3 class="text-lg font-semibold mb-4">New Message to Admin</h3>
            <form action="contact_admin.php" method="post">
                <div class="mb-4">
                    <label for="type" class="block text-gray-700 font-medium mb-2">Message Type <span class="text-red-500">*</span></label>
                    <select name="type" id="type" class="w-full px-3 py-2 border border-gray-300 rounded" required>
                        <option value="General" <?= (isset($type) && $type === 'General') ? 'selected' : '' ?>>General Inquiry</option>
                        <option value="Support Escalation" <?= (isset($type) && $type === 'Support Escalation') ? 'selected' : '' ?>>Support Ticket Escalation</option>
                        
                        <!-- Add Employee Action option but control visibility with JavaScript -->
                        <option value="Employee Action" id="employee-action-option" <?= (isset($type) && $type === 'Employee Action') ? 'selected' : '' ?>>Employee Action Request</option>
                    </select>
                </div>
                
                <!-- Include current role information for JavaScript -->
                <input type="hidden" id="current-user-role" value="<?= htmlspecialchars($_SESSION['role'] ?? '') ?>">
                
                <div id="related-id-section" class="mb-4 hidden">
                    <label id="related-label" for="related_id" class="block text-gray-700 font-medium mb-2">Related Item</label>
                    <select name="related_id" id="related_id" class="w-full px-3 py-2 border border-gray-300 rounded">
                        <option value="">Select Related Item</option>
                        
                        <!-- Support tickets section (visible when Support Escalation is selected) -->
                        <optgroup id="support-tickets-group" label="Support Tickets" class="hidden">
                            <?php foreach ($support_tickets as $ticket): ?>
                                <option value="<?= $ticket['ticket_id'] ?>">
                                    Ticket #<?= $ticket['ticket_id'] ?> - <?= htmlspecialchars($ticket['subject']) ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                        
                        <!-- Employees section (visible when Employee Action is selected and user is Manager) -->
                        <?php if ($_SESSION['role'] === 'Manager'): ?>
                            <optgroup id="employees-group" label="Employees" class="hidden">
                                <?php foreach ($employees as $employee): ?>
                                    <option value="<?= $employee['user_id'] ?>">
                                        <?= htmlspecialchars($employee['email']) ?> (<?= $employee['role'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label for="subject" class="block text-gray-700 font-medium mb-2">Subject <span class="text-red-500">*</span></label>
                    <input type="text" name="subject" id="subject" 
                           class="w-full px-3 py-2 border border-gray-300 rounded" required
                           value="<?= htmlspecialchars($subject ?? '') ?>"
                           placeholder="Brief summary of your message">
                </div>
                
                <div class="mb-4">
                    <label for="message" class="block text-gray-700 font-medium mb-2">Message <span class="text-red-500">*</span></label>
                    <textarea name="message" id="message" rows="6"
                              class="w-full px-3 py-2 border border-gray-300 rounded" required
                              placeholder="Please provide all relevant details to help the admin address your request"><?= htmlspecialchars($message ?? '') ?></textarea>
                </div>
                
                <div class="flex justify-end mt-6">
                    <button type="reset" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded mr-2">
                        Clear
                    </button>
                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                        Send Message
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Recent Messages -->
        <div class="mt-8">
            <h2 class="text-xl font-semibold mb-4">My Messages</h2>
            
            <?php if (count($messages) > 0): ?>
                <div class="bg-white rounded-lg shadow-md overflow-x-auto">
                    <table class="w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/5">Subject</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/6">Type</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/8">Status</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/12">Replies</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/4">Date</th>
                                <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-1/8">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($messages as $message): ?>
                                <?php
                                    $statusColors = [
                                        'New' => 'bg-blue-100 text-blue-800',
                                        'In Progress' => 'bg-yellow-100 text-yellow-800',
                                        'Resolved' => 'bg-green-100 text-green-800',
                                        'Rejected' => 'bg-red-100 text-red-800',
                                        'Replied' => 'bg-purple-100 text-purple-800 font-bold',
                                        'Updated' => 'bg-indigo-100 text-indigo-800'
                                    ];
                                    $statusColor = $statusColors[$message['status']] ?? 'bg-gray-100 text-gray-800';
                                    
                                    // Add row highlighting for messages with admin replies
                                    $rowClass = ($message['status'] === 'Replied') ? 'bg-purple-50 font-semibold' : 'hover:bg-gray-50';
                                ?>
                                <tr class="<?= $rowClass ?>">
                                    <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?= htmlspecialchars($message['subject']) ?>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= htmlspecialchars($message['type']) ?>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $statusColor ?>">
                                            <?= htmlspecialchars($message['status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-center text-gray-500">
                                        <?= (int)$message['reply_count'] ?>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= htmlspecialchars($message['formatted_date']) ?>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-center">
                                        <a href="contact_admin.php?view=<?= $message['message_id'] ?>" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm font-medium inline-block">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="bg-white p-6 rounded-lg shadow text-center text-gray-500">
                    <p>You haven't sent any messages to the admin yet.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Debug current role -->
        <?php if (!$message_detail): ?>
        <div class="mb-1 text-xs text-gray-400">
            Current role: <?= $_SESSION['role'] ?? 'Not set' ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Control visibility of Employee Action option based on role
            const userRole = document.getElementById('current-user-role').value;
            const employeeActionOption = document.getElementById('employee-action-option');
            
            console.log('Current user role:', userRole); // For debugging
            
            if (userRole !== 'Manager') {
                // Hide the option if not a Manager
                if (employeeActionOption) {
                    employeeActionOption.style.display = 'none';
                }
            } else {
                console.log('Manager role detected, showing Employee Action option');
            }
            
            // Rest of existing JavaScript
            const typeSelect = document.getElementById('type');
            const relatedSection = document.getElementById('related-id-section');
            const relatedSelect = document.getElementById('related_id');
            const relatedLabel = document.getElementById('related-label');
            const supportTicketsGroup = document.getElementById('support-tickets-group');
            const employeesGroup = document.getElementById('employees-group');
            
            function updateRelatedSection() {
                const selectedType = typeSelect.value;
                
                // Hide all by default
                relatedSection.classList.add('hidden');
                if (supportTicketsGroup) supportTicketsGroup.classList.add('hidden');
                if (employeesGroup) employeesGroup.classList.add('hidden');
                
                // Show appropriate sections based on selection
                if (selectedType === 'Support Escalation') {
                    if (supportTicketsGroup && supportTicketsGroup.querySelectorAll('option').length > 0) {
                        relatedSection.classList.remove('hidden');
                        supportTicketsGroup.classList.remove('hidden');
                        relatedLabel.textContent = 'Related Support Ticket';
                    }
                } else if (selectedType === 'Employee Action') {
                    if (employeesGroup && employeesGroup.querySelectorAll('option').length > 0) {
                        relatedSection.classList.remove('hidden');
                        employeesGroup.classList.remove('hidden');
                        relatedLabel.textContent = 'Employee';
                    }
                }
            }
            
            // Initial setup
            if (typeSelect) {
                updateRelatedSection();
                
                // Update on change
                typeSelect.addEventListener('change', updateRelatedSection);
            }
        });
    </script>
</body>
</html> 