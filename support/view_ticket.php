<?php
session_start();
require_once '../db_connect.php';
require_once '../functions.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login.php?redirect=support/view_ticket.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$name = $_SESSION['name'];
$message = '';
$error = '';

// Check if ticket ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: " . ($role == 'Customer' ? "../support.php" : "../employee_dashboard.php"));
    exit();
}

$ticket_id = intval($_GET['id']);

// Get ticket details
$stmt = $conn->prepare("SELECT t.*, 
                    c.first_name as customer_first_name, c.last_name as customer_last_name,
                    p.status as package_status, p.tracking_number,
                    e.first_name as employee_first_name, e.last_name as employee_last_name, e.role as employee_role,
                    IFNULL((SELECT MIN(timestamp) FROM Tracking_History th WHERE th.tracking_number = t.package_id), NOW()) as created_at
                    FROM Support_Ticket t
                    LEFT JOIN Customer c ON t.user_id = c.user_id
                    LEFT JOIN Package p ON t.package_id = p.tracking_number
                    LEFT JOIN Employee e ON t.assigned_employee_id = e.user_id
                    WHERE t.ticket_id = ?");
$stmt->bind_param("i", $ticket_id);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();

// Verify that the ticket exists and belongs to the user (or is being viewed by an employee/admin)
if (!$ticket || ($ticket['user_id'] != $user_id && $role != 'Employee' && $role != 'Admin')) {
    header("Location: " . ($role == 'Customer' ? "../support.php" : "../employee_dashboard.php"));
    exit();
}

// Handle ticket updates (only for employees/admins)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_ticket']) && ($role == 'Employee' || $role == 'Admin')) {
    $new_status = $_POST['status'] ?? '';
    
    if (!empty($new_status)) {
        $update = $conn->prepare("UPDATE Support_Ticket SET status = ? WHERE ticket_id = ?");
        $update->bind_param("si", $new_status, $ticket_id);
        
        if ($update->execute()) {
            $message = "Ticket updated successfully!";
            
            // Refresh ticket data
            $stmt->execute();
            $ticket = $stmt->get_result()->fetch_assoc();
        } else {
            $error = "Error updating ticket: " . $conn->error;
        }
    }
}

// Handle adding a comment (for both customers and employees)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment'])) {
    $comment = $_POST['comment'] ?? '';
    
    if (!empty($comment)) {
        // Get current chat log or initialize if empty
        $chat_log = [];
        if (!empty($ticket['chat_log'])) {
            $chat_log = json_decode($ticket['chat_log'], true);
        } 
        
        // If chat_log is empty or invalid, initialize with original description if resolution_notes exists
        if (empty($chat_log) && !empty($ticket['resolution_notes'])) {
            $chat_log = [
                [
                    'timestamp' => date('Y-m-d H:i:s', strtotime($ticket['created_at'] ?? 'now')),
                    'user_id' => $ticket['user_id'],
                    'name' => "{$ticket['customer_first_name']} {$ticket['customer_last_name']}",
                    'role' => 'Customer',
                    'message' => $ticket['resolution_notes']
                ]
            ];
        }
        
        // Add new comment to chat log
        $chat_log[] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'user_id' => $user_id,
            'name' => $name,
            'role' => $role,
            'message' => $comment
        ];
        
        // Update chat_log in database
        $chat_log_json = json_encode($chat_log);
        $update = $conn->prepare("UPDATE Support_Ticket SET chat_log = ? WHERE ticket_id = ?");
        $update->bind_param("si", $chat_log_json, $ticket_id);
        
        if ($update->execute()) {
            $message = "Comment added successfully!";
            
            // Refresh ticket data
            $stmt->execute();
            $ticket = $stmt->get_result()->fetch_assoc();
        } else {
            $error = "Error adding comment: " . $conn->error;
        }
    } else {
        $error = "Comment cannot be empty.";
    }
}

// Check if the ticket was just created
$just_created = isset($_GET['created']) && $_GET['created'] == 1;

// Prepare chat logs for display
$comments = [];
if (!empty($ticket['chat_log'])) {
    $chat_log = json_decode($ticket['chat_log'], true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($chat_log)) {
        $comments = $chat_log;
    }
} else if (!empty($ticket['resolution_notes'])) {
    // Fallback to resolution_notes if chat_log is empty
    $comments[] = [
        'timestamp' => date('Y-m-d H:i:s', strtotime($ticket['created_at'] ?? 'now')),
        'user_id' => $ticket['user_id'],
        'name' => "{$ticket['customer_first_name']} {$ticket['customer_last_name']}",
        'role' => 'Customer',
        'message' => $ticket['resolution_notes']
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Ticket #<?= $ticket_id ?> | POSTAL PRO</title>
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
    <nav class="top-nav fixed w-full top-0 left-0 z-50 shadow-md px-4 py-4">
        <div class="container mx-auto flex justify-between items-center">
            <a href="../index.php" class="text-white font-bold text-2xl">POSTAL PRO</a>
            <ul class="flex space-x-6">
                <li><a href="../index.php" class="text-white hover:text-gray-200">Home</a></li>
                <li><a href="<?= $role == 'Customer' ? '../customer_dashboard.php' : '../employee_dashboard.php' ?>" class="text-white hover:text-gray-200">Dashboard</a></li>
                <li><a href="../logout.php" class="text-white hover:text-gray-200">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8 max-w-6xl mt-20">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold section-title">Support Ticket #<?= $ticket_id ?></h1>
            <a href="<?= $role == 'Customer' ? '../support.php' : '../employee_dashboard.php' ?>" class="text-[#004B87] hover:text-[#DA291C]">
                <i class="fas fa-arrow-left mr-1"></i> Back to <?= $role == 'Customer' ? 'Support' : 'Dashboard' ?>
            </a>
        </div>

        <?php if ($just_created): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
            <p>Ticket created successfully! We'll review your issue and get back to you as soon as possible.</p>
        </div>
        <?php endif; ?>

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

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="md:col-span-2">
                <!-- Ticket Details and Discussion -->
                <section class="bg-white shadow-sm p-6 rounded-lg mb-6">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h2 class="text-2xl font-semibold section-title">Ticket Details</h2>
                            <p class="text-gray-500">Created on: <?= date('M d, Y H:i', strtotime($ticket['created_at'] ?? 'now')) ?></p>
                        </div>
                        <span class="<?php
                            switch($ticket['status']) {
                                case 'Open': echo 'bg-green-100 text-green-800'; break;
                                case 'In Progress': echo 'bg-blue-100 text-blue-800'; break;
                                case 'Resolved': echo 'bg-gray-100 text-gray-800'; break;
                                default: echo 'bg-gray-100 text-gray-800';
                            }
                        ?> px-3 py-1 rounded-full text-sm font-medium">
                            <?= htmlspecialchars($ticket['status']) ?>
                        </span>
                    </div>
                    
                    <div class="bg-gray-50 p-4 rounded-lg mb-6">
                        <p class="font-semibold">Issue Type: <?= htmlspecialchars($ticket['issue_type'] ?? 'N/A') ?></p>
                        <?php if (!empty($ticket['package_id'])): ?>
                        <p class="mt-2">
                            <span class="font-semibold">Related Package (Click to view details) :</span> 
                            <a href="../track_details.php?tracking=<?= urlencode($ticket['package_id']) ?>" 
                               class="text-[#004B87] hover:text-[#DA291C]">
                                <?= htmlspecialchars($ticket['tracking_number'] ?? $ticket['package_id']) ?>
                            </a>
                            <?php if (!empty($ticket['package_status'])): ?>
                                <span class="<?php
                                    switch($ticket['package_status']) {
                                        case 'Delivered': echo 'bg-green-100 text-green-800'; break;
                                        case 'In Transit': echo 'bg-blue-100 text-blue-800'; break;
                                        case 'Processing': echo 'bg-yellow-100 text-yellow-800'; break;
                                        case 'Out for Delivery': echo 'bg-purple-100 text-purple-800'; break;
                                        default: echo 'bg-gray-100 text-gray-800';
                                    }
                                ?> px-2 py-0.5 rounded-full text-xs ml-2">
                                    <?= htmlspecialchars($ticket['package_status']) ?>
                                </span>
                            <?php endif; ?>
                        </p>
                        <?php endif; ?>
                        
                        <?php if ($role == 'Employee' || $role == 'Admin'): ?>
                        <p class="mt-2">
                            <span class="font-semibold">Customer:</span> 
                            <?= htmlspecialchars(($ticket['customer_first_name'] ?? 'Unknown') . ' ' . ($ticket['customer_last_name'] ?? '')) ?>
                        </p>
                        <?php endif; ?>
                        
                        <?php if ($role == 'Customer'): ?>
                        <p class="mt-2">
                            <span class="font-semibold">Assigned To:</span> 
                            <?= !empty($ticket['employee_first_name']) ? 
                                htmlspecialchars($ticket['employee_first_name'] . ' ' . ($ticket['employee_last_name'] ?? '') . ' (' . ($ticket['employee_role'] ?? 'Staff') . ')') : 
                                'Waiting for assignment' ?>
                        </p>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <h3 class="text-xl font-semibold mb-4 section-title">Discussion</h3>
                        
                        <div class="space-y-4 mb-6">
                            <?php if (!empty($comments)): ?>
                                <?php foreach ($comments as $comment): ?>
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <div class="flex justify-between items-center mb-2">
                                        <span class="font-semibold"><?= htmlspecialchars($comment['name']) ?> (<?= htmlspecialchars($comment['role']) ?>)</span>
                                        <span class="text-sm text-gray-500"><?= date('M d, Y H:i', strtotime($comment['timestamp'])) ?></span>
                                    </div>
                                    <p class="whitespace-pre-line"><?= nl2br(htmlspecialchars($comment['message'])) ?></p>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-gray-500">No comments yet.</p>
                            <?php endif; ?>
                        </div>
                        
                        <form action="view_ticket.php?id=<?= $ticket_id ?>" method="post" class="mt-4">
                            <div class="mb-4">
                                <label for="comment" class="block text-gray-700 font-medium mb-2">Add a comment</label>
                                <textarea name="comment" id="comment" rows="3" 
                                          class="w-full p-3 border border-gray-300 rounded" 
                                          placeholder="Type your message here..." required></textarea>
                            </div>
                            
                            <div class="flex justify-end">
                                <button type="submit" name="add_comment" class="action-btn text-white px-4 py-2 rounded">
                                    Add Comment
                                </button>
                            </div>
                        </form>
                    </div>
                </section>
            </div>
            
            <div>
                <!-- Sidebar content -->
                <?php if ($role == 'Employee' || $role == 'Admin'): ?>
                <!-- Employee actions panel -->
                <section class="bg-white shadow-sm p-6 rounded-lg mb-6">
                    <h2 class="text-xl font-semibold mb-4 section-title">Update Ticket</h2>
                    <form action="view_ticket.php?id=<?= $ticket_id ?>" method="post">
                        <div class="mb-4">
                            <label for="status" class="block text-gray-700 font-medium mb-2">Status</label>
                            <select name="status" id="status" class="w-full p-3 border border-gray-300 rounded" required>
                                <option value="Open" <?= $ticket['status'] === 'Open' ? 'selected' : '' ?>>Open</option>
                                <option value="In Progress" <?= $ticket['status'] === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                                <option value="Resolved" <?= $ticket['status'] === 'Resolved' ? 'selected' : '' ?>>Resolved</option>
                            </select>
                        </div>
                        
                        <div class="flex justify-end">
                            <button type="submit" name="update_ticket" class="accent-btn text-white px-4 py-2 rounded">
                                Update Ticket
                            </button>
                        </div>
                    </form>
                </section>
                <?php endif; ?>
            </div>
        </div>
        
        <footer class="text-center mt-10 py-6 bg-[#004B87] text-white rounded-lg">
            <p>&copy; <?= date('Y') ?> Postal Service Management System</p>
        </footer>
    </div>
</body>
</html> 