<?php
// Add error reporting for debugging
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../db_connect.php';
require_once '../functions.php';

// Only admin can access this page
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'Admin') {
    header("Location: ../login.php");
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
                     e.first_name, e.last_name, e.role as employee_role,
                     DATE_FORMAT(m.created_at, '%M %d, %Y at %h:%i %p') as formatted_date
                     FROM Admin_Messages m
                     JOIN Users u ON m.sender_id = u.user_id
                     LEFT JOIN Employee e ON m.sender_id = e.user_id
                     WHERE m.message_id = ?";
    $stmt = $conn->prepare($message_query);
    $stmt->bind_param("i", $message_id);
    $stmt->execute();
    $message_detail = $stmt->get_result()->fetch_assoc();
    
    if ($message_detail) {
        // Fetch replies for this message
        $replies_query = "SELECT r.*, 
                        u.email as sender_email,
                        CASE 
                            WHEN u.role = 'Admin' THEN 'Admin'
                            ELSE CONCAT(e.first_name, ' ', e.last_name)
                        END as sender_name,
                        u.role as sender_role,
                        DATE_FORMAT(r.created_at, '%M %d, %Y at %h:%i %p') as formatted_date
                        FROM Message_Replies r
                        JOIN Users u ON r.sender_id = u.user_id
                        LEFT JOIN Employee e ON r.sender_id = e.user_id
                        WHERE r.message_id = ?
                        ORDER BY r.created_at ASC";
        $replies_stmt = $conn->prepare($replies_query);
        $replies_stmt->bind_param("i", $message_id);
        $replies_stmt->execute();
        $replies_result = $replies_stmt->get_result();
        
        while ($reply = $replies_result->fetch_assoc()) {
            $replies[] = $reply;
        }
        
        // Handle reply submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply'])) {
            $reply_text = trim($_POST['reply'] ?? '');
            $new_status = trim($_POST['status'] ?? '');
            
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
                            FOREIGN KEY (sender_id) REFERENCES Users(user_id) ON DELETE CASCADE
                        )";
                        $conn->query($create_table_sql);
                        
                        if ($conn->error) {
                            $error = "Error creating replies table: " . $conn->error;
                            throw new Exception($error);
                        }
                    }
                    
                    // Start transaction for inserting reply and updating status
                    $conn->begin_transaction();
                    
                    // Insert reply
                    $is_admin = 1; // Admin is sending the reply
                    $reply_stmt = $conn->prepare("INSERT INTO Message_Replies 
                                              (message_id, sender_id, reply_text, is_admin) 
                                              VALUES (?, ?, ?, ?)");
                    $reply_stmt->bind_param("iisi", $message_id, $_SESSION['user_id'], $reply_text, $is_admin);
                    $reply_stmt->execute();
                    
                    // Update message status if needed
                    if (!empty($new_status) && $new_status !== $message_detail['status']) {
                        $update_stmt = $conn->prepare("UPDATE Admin_Messages SET status = ? WHERE message_id = ?");
                        $update_stmt->bind_param("si", $new_status, $message_id);
                        $update_stmt->execute();
                    }
                    
                    $conn->commit();
                    
                    $success = "Your reply has been added and the message has been updated.";
                    
                    // Redirect to prevent form resubmission
                    header("Location: inbox.php?view=".$message_id."&success=1");
                    exit();
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "Database error: " . $e->getMessage();
                }
            }
        }
    } else {
        // Message not found
        header("Location: inbox.php?error=1");
        exit();
    }
}

// Set success/error from URL parameters
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success = $message_detail ? "Your reply has been added." : "Action completed successfully.";
}
if (isset($_GET['error']) && $_GET['error'] == 1) {
    $error = "Message not found.";
}

// Fetch messages if we're not viewing a specific message
$messages = [];
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$valid_statuses = ['New', 'In Progress', 'Updated', 'Replied', 'Resolved', 'Rejected'];

if (!$message_detail) {
    // Prepare where clause for status filtering
    $where_clause = "";
    $params = [];
    $types = "";
    
    if (in_array($status_filter, $valid_statuses)) {
        $where_clause = " AND m.status = ?";
        $params[] = $status_filter;
        $types .= "s";
    }
    
    // Fetch messages
    $messages_query = "SELECT m.*, 
                      u.email as sender_email,
                      e.first_name, e.last_name, e.role as employee_role,
                      DATE_FORMAT(m.created_at, '%M %d, %Y at %h:%i %p') as formatted_date,
                      (SELECT COUNT(*) FROM Message_Replies WHERE message_id = m.message_id) as reply_count
                      FROM Admin_Messages m
                      JOIN Users u ON m.sender_id = u.user_id
                      LEFT JOIN Employee e ON m.sender_id = e.user_id
                      WHERE 1=1" . $where_clause . "
                      ORDER BY 
                          CASE WHEN m.status IN ('New', 'Updated') THEN 0 ELSE 1 END, 
                          m.created_at DESC";
    
    $messages_stmt = $conn->prepare($messages_query);
    if (!empty($types)) {
        $messages_stmt->bind_param($types, ...$params);
    }
    $messages_stmt->execute();
    $messages_result = $messages_stmt->get_result();
    
    while ($message = $messages_result->fetch_assoc()) {
        $messages[] = $message;
    }
}

// Get status counts for filter badges
$status_counts = [];
$count_query = "SELECT status, COUNT(*) as count FROM Admin_Messages GROUP BY status";
$count_result = $conn->query($count_query);
if ($count_result && $count_result->num_rows > 0) {
    while ($row = $count_result->fetch_assoc()) {
        $status_counts[$row['status']] = $row['count'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $message_detail ? 'Message #'.$message_detail['message_id'] : 'Admin Inbox' ?> — Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen">

<?php if ($message_detail): $nav_back_url = 'inbox.php'; $nav_back_text = 'Inbox'; endif; ?>
<?php include '_nav.php'; ?>

<div class="max-w-5xl mx-auto px-4 py-8">

    <?php if (!$message_detail): ?>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-black text-gray-900">Admin Inbox</h1>
            <p class="text-sm text-gray-500 mt-0.5">Messages and support requests from employees.</p>
        </div>
        <span class="bg-[#004B87] text-white text-xs font-bold px-3 py-1 rounded-full"><?= array_sum($status_counts) ?> total</span>
    </div>
    <?php else: ?>
    <div class="mb-6">
        <h1 class="text-2xl font-black text-gray-900">Message <span class="text-[#004B87]">#<?= $message_detail['message_id'] ?></span></h1>
        <p class="text-sm text-gray-500 mt-0.5"><?= htmlspecialchars($message_detail['subject']) ?></p>
    </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
    <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded mb-6 text-sm"><i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
    <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded mb-6 text-sm"><i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($message_detail): ?>
    <!-- ── Message Detail View ── -->

    <?php
        $statusColors = ['New'=>'bg-blue-100 text-blue-700','In Progress'=>'bg-yellow-100 text-yellow-700','Resolved'=>'bg-green-100 text-green-700','Rejected'=>'bg-red-100 text-red-700','Replied'=>'bg-purple-100 text-purple-700','Updated'=>'bg-indigo-100 text-indigo-700'];
        $sc = $statusColors[$message_detail['status']] ?? 'bg-gray-100 text-gray-700';
    ?>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-4">
        <div class="flex items-start justify-between mb-4">
            <div>
                <h2 class="text-lg font-bold text-gray-900"><?= htmlspecialchars($message_detail['subject']) ?></h2>
                <p class="text-sm text-gray-500 mt-1">
                    <i class="fas fa-user mr-1"></i>
                    <strong><?= htmlspecialchars($message_detail['first_name'].' '.$message_detail['last_name']) ?></strong>
                    &lt;<?= htmlspecialchars($message_detail['sender_email']) ?>&gt;
                    &bull; <?= htmlspecialchars($message_detail['employee_role']) ?>
                    &bull; <i class="fas fa-clock mr-1"></i><?= htmlspecialchars($message_detail['formatted_date']) ?>
                </p>
            </div>
            <span class="<?= $sc ?> text-xs font-bold px-3 py-1 rounded-full whitespace-nowrap"><?= htmlspecialchars($message_detail['status']) ?></span>
        </div>

        <div class="border-t border-gray-50 pt-4 text-sm text-gray-700 leading-relaxed">
            <?= nl2br(htmlspecialchars($message_detail['message'])) ?>
        </div>

        <div class="flex flex-wrap gap-2 mt-4">
            <span class="bg-gray-100 text-gray-600 text-xs px-2 py-1 rounded-full"><i class="fas fa-tag mr-1"></i><?= htmlspecialchars($message_detail['type']) ?></span>
            <?php if ($message_detail['related_id']): ?>
            <span class="bg-gray-100 text-gray-600 text-xs px-2 py-1 rounded-full">Related: #<?= htmlspecialchars($message_detail['related_id']) ?></span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Conversation History -->
    <?php if (count($replies) > 0): ?>
    <div class="mb-4">
        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Conversation History</h3>
        <?php foreach ($replies as $reply): ?>
        <div class="bg-white rounded-xl border border-gray-100 p-4 mb-3 <?= $reply['is_admin'] ? 'ml-8 border-l-4 border-[#004B87]' : 'mr-8' ?>">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-semibold <?= $reply['is_admin'] ? 'text-[#004B87]' : 'text-gray-700' ?>">
                    <?php if ($reply['is_admin']): ?>
                        <i class="fas fa-shield-alt mr-1"></i>Admin
                    <?php else: ?>
                        <?= htmlspecialchars($reply['sender_name']) ?>
                        <span class="text-xs text-gray-400 font-normal">(<?= htmlspecialchars($reply['sender_role']) ?>)</span>
                    <?php endif; ?>
                </span>
                <span class="text-xs text-gray-400"><?= htmlspecialchars($reply['formatted_date']) ?></span>
            </div>
            <div class="text-sm text-gray-700 leading-relaxed">
                <?= nl2br(htmlspecialchars($reply['reply_text'])) ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Reply Form -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-5">Reply to Employee</h3>
        <form action="inbox.php?view=<?= $message_detail['message_id'] ?>" method="post">
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Update Status</label>
                <select name="status" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004B87]">
                    <option value="<?= $message_detail['status'] ?>" selected>Keep current: <?= $message_detail['status'] ?></option>
                    <option value="In Progress">In Progress</option>
                    <option value="Resolved">Resolved</option>
                    <option value="Rejected">Rejected</option>
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Your Reply <span class="text-red-500">*</span></label>
                <textarea name="reply" rows="4" required
                          class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004B87]"
                          placeholder="Write your reply to the employee..."><?= htmlspecialchars($_POST['reply'] ?? '') ?></textarea>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="bg-[#004B87] hover:bg-blue-900 text-white px-5 py-2 rounded-lg text-sm font-semibold transition">
                    <i class="fas fa-reply mr-2"></i>Send Reply
                </button>
            </div>
        </form>
    </div>

    <?php else: ?>
    <!-- ── Inbox List View ── -->

    <!-- Status Filter Pills -->
    <div class="flex flex-wrap gap-2 mb-6">
        <a href="inbox.php"
           class="<?= empty($status_filter) ? 'bg-[#004B87] text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?> px-3 py-1.5 rounded-full text-xs font-semibold transition">
            All <span class="<?= empty($status_filter) ? 'opacity-70' : 'text-gray-400' ?>">(<?= array_sum($status_counts) ?>)</span>
        </a>
        <?php
        $pillColors = ['New'=>'blue','In Progress'=>'yellow','Resolved'=>'green','Rejected'=>'red','Replied'=>'purple','Updated'=>'indigo'];
        foreach ($valid_statuses as $status):
            $cnt = $status_counts[$status] ?? 0;
            $active = $status_filter === $status;
        ?>
        <a href="inbox.php?status=<?= urlencode($status) ?>"
           class="<?= $active ? 'bg-[#004B87] text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?> px-3 py-1.5 rounded-full text-xs font-semibold transition">
            <?= $status ?> <span class="<?= $active ? 'opacity-70' : 'text-gray-400' ?>">(<?= $cnt ?>)</span>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if (count($messages) > 0): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100 text-left">
                        <th class="px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wide">ID</th>
                        <th class="px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wide">Subject</th>
                        <th class="px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wide">From</th>
                        <th class="px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wide">Type</th>
                        <th class="px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wide">Status</th>
                        <th class="px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wide">Replies</th>
                        <th class="px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wide">Date</th>
                        <th class="px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wide"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach ($messages as $msg):
                        $sc = $statusColors[$msg['status']] ?? 'bg-gray-100 text-gray-700';
                        $isNew = in_array($msg['status'], ['New','Updated']);
                    ?>
                    <tr class="<?= $isNew ? 'bg-blue-50/40' : 'hover:bg-gray-50' ?> transition">
                        <td class="px-4 py-3 text-gray-400 font-mono text-xs">#<?= $msg['message_id'] ?></td>
                        <td class="px-4 py-3">
                            <div class="font-semibold text-gray-800 flex items-center gap-2">
                                <?php if ($isNew): ?><span class="w-2 h-2 bg-blue-500 rounded-full inline-block flex-shrink-0"></span><?php endif; ?>
                                <?= htmlspecialchars($msg['subject']) ?>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-700"><?= htmlspecialchars($msg['first_name'].' '.$msg['last_name']) ?></div>
                            <div class="text-xs text-gray-400"><?= htmlspecialchars($msg['employee_role']) ?></div>
                        </td>
                        <td class="px-4 py-3 text-gray-500"><?= htmlspecialchars($msg['type']) ?></td>
                        <td class="px-4 py-3">
                            <span class="<?= $sc ?> text-xs font-semibold px-2 py-1 rounded-full"><?= htmlspecialchars($msg['status']) ?></span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <?php if ((int)$msg['reply_count'] > 0): ?>
                            <span class="bg-gray-100 text-gray-600 text-xs font-semibold px-2 py-0.5 rounded-full"><?= (int)$msg['reply_count'] ?></span>
                            <?php else: ?>
                            <span class="text-gray-300">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-400"><?= htmlspecialchars($msg['formatted_date']) ?></td>
                        <td class="px-4 py-3">
                            <a href="inbox.php?view=<?= $msg['message_id'] ?>" class="text-[#004B87] hover:text-blue-900 text-xs font-semibold whitespace-nowrap">
                                <i class="fas fa-reply mr-1"></i>Reply
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
    <div class="bg-white rounded-xl border border-gray-100 p-10 text-center text-gray-400">
        <i class="fas fa-inbox text-3xl mb-3 block"></i>
        <p class="font-semibold">No messages<?= $status_filter ? ' with status: '.htmlspecialchars($status_filter) : '' ?>.</p>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>