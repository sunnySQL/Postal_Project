<?php
session_start();
require_once '../db_connect.php';
require_once '../functions.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'Admin') {
    header("Location: unauthorized.php");
    exit();
}

// ── Auto-add deleted_at column if missing ─────────────────────────────────────
$col_check = $conn->query("SHOW COLUMNS FROM Users LIKE 'deleted_at'");
if ($col_check && $col_check->num_rows === 0) {
    $conn->query("ALTER TABLE Users ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL");
}

// ── Soft delete ───────────────────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $user_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($user_id && $user_id != $_SESSION['user_id']) {
        $stmt = $conn->prepare("UPDATE Users SET deleted_at = NOW(), account_status = 'Suspended' WHERE user_id = ? AND deleted_at IS NULL");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            logAudit($conn, 'USER_DELETED', 'User', (string)$user_id,
                "User #{$user_id} soft-deleted (account deactivated, data retained)",
                null, null, ['deleted_at' => date('Y-m-d H:i:s')]
            );
            $_SESSION['message'] = "User has been deleted. Their data is retained and can be restored.";
            $_SESSION['message_type'] = "success";
        }
    } else {
        $_SESSION['message'] = "You cannot delete your own account.";
        $_SESSION['message_type'] = "error";
    }
    header("Location: manage_users.php"); exit();
}

// ── Restore deleted user ──────────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'restore' && isset($_GET['id'])) {
    $user_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($user_id) {
        $stmt = $conn->prepare("UPDATE Users SET deleted_at = NULL, account_status = 'Active' WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            logAudit($conn, 'USER_RESTORED', 'User', (string)$user_id,
                "User #{$user_id} restored — account reactivated",
                null, ['deleted_at' => 'set'], ['deleted_at' => null]
            );
            $_SESSION['message'] = "User has been restored and reactivated.";
            $_SESSION['message_type'] = "success";
        }
    }
    header("Location: manage_users.php?tab=deleted"); exit();
}

// ── Status toggle ─────────────────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'toggle' && isset($_GET['id'])) {
    $user_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    $new_status = $_GET['status'] === 'Active' ? 'Suspended' : 'Active';
    if ($user_id) {
        if ($user_id == $_SESSION['user_id']) {
            $_SESSION['message'] = "You cannot change your own account status.";
            $_SESSION['message_type'] = "error";
        } else {
            $stmt = $conn->prepare("UPDATE Users SET account_status = ? WHERE user_id = ?");
            $stmt->bind_param("si", $new_status, $user_id);
            if ($stmt->execute()) {
                $_SESSION['message'] = "User status updated successfully.";
                $_SESSION['message_type'] = "success";
                $old_status = ($_GET['status'] === 'Active') ? 'Active' : 'Suspended';
                logAudit($conn, 'USER_STATUS_CHANGED', 'User', (string)$user_id,
                    "User #{$user_id} status changed from {$old_status} to {$new_status}",
                    null,
                    ['account_status' => $old_status],
                    ['account_status' => $new_status]
                );
            } else {
                $_SESSION['message'] = "Error updating user status: " . $conn->error;
                $_SESSION['message_type'] = "error";
            }
        }
    }
    header("Location: manage_users.php"); exit();
}

$active_tab = (isset($_GET['tab']) && $_GET['tab'] === 'deleted') ? 'deleted' : 'active';

// ── Active users ──────────────────────────────────────────────────────────────
try {
    $query = "SELECT u.user_id, u.email, u.role, u.account_status, u.last_login,
             CASE
                WHEN u.role = 'Customer' THEN CONCAT(c.first_name, ' ', c.last_name)
                WHEN u.role IN ('Employee', 'Admin') THEN CONCAT(e.first_name, ' ', e.last_name)
                ELSE 'Unknown'
             END AS display_name
             FROM Users u
             LEFT JOIN Customer c ON u.user_id = c.user_id AND u.role = 'Customer'
             LEFT JOIN Employee e ON u.user_id = e.user_id AND u.role IN ('Employee', 'Admin')
             WHERE u.deleted_at IS NULL
             ORDER BY u.role, u.user_id";
    $result = $conn->query($query);
    if (!$result) throw new Exception($conn->error);
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
}

// ── Deleted users ─────────────────────────────────────────────────────────────
try {
    $del_query = "SELECT u.user_id, u.email, u.role, u.deleted_at,
             CASE
                WHEN u.role = 'Customer' THEN CONCAT(c.first_name, ' ', c.last_name)
                WHEN u.role IN ('Employee', 'Admin') THEN CONCAT(e.first_name, ' ', e.last_name)
                ELSE 'Unknown'
             END AS display_name
             FROM Users u
             LEFT JOIN Customer c ON u.user_id = c.user_id AND u.role = 'Customer'
             LEFT JOIN Employee e ON u.user_id = e.user_id AND u.role IN ('Employee', 'Admin')
             WHERE u.deleted_at IS NOT NULL
             ORDER BY u.deleted_at DESC";
    $deleted_result = $conn->query($del_query);
    if (!$deleted_result) throw new Exception($conn->error);
} catch (Exception $e) {
    $error_message = $error_message ?? "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include '_nav.php'; ?>
    <div class="container mx-auto px-4 py-8 max-w-6xl">
        <header class="mb-6">
            <div class="flex justify-between items-center">
                <h1 class="text-3xl font-bold">User Management</h1>
            </div>
        </header>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="<?= $_SESSION['message_type'] === 'error' ? 'bg-red-100 border-l-4 border-red-500 text-red-700' : 'bg-green-100 border-l-4 border-green-500 text-green-700' ?> p-4 mb-6">
                <p><?= htmlspecialchars($_SESSION['message']) ?></p>
            </div>
            <?php 
                unset($_SESSION['message']);
                unset($_SESSION['message_type']);
            ?>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                <p><?= htmlspecialchars($error_message) ?></p>
            </div>
        <?php else: ?>

            <!-- Tabs -->
            <div class="flex gap-2 mb-4 border-b border-gray-200">
                <a href="manage_users.php?tab=active"
                   class="px-4 py-2 text-sm font-semibold rounded-t <?= $active_tab === 'active' ? 'bg-white border border-b-white text-blue-700' : 'text-gray-500 hover:text-gray-700' ?>">
                    <i class="fas fa-users mr-1"></i> Active Users
                    <span class="ml-1 bg-blue-100 text-blue-700 text-xs px-2 py-0.5 rounded-full"><?= $result->num_rows ?></span>
                </a>
                <a href="manage_users.php?tab=deleted"
                   class="px-4 py-2 text-sm font-semibold rounded-t <?= $active_tab === 'deleted' ? 'bg-white border border-b-white text-red-700' : 'text-gray-500 hover:text-gray-700' ?>">
                    <i class="fas fa-trash-alt mr-1"></i> Deleted Users
                    <span class="ml-1 bg-red-100 text-red-700 text-xs px-2 py-0.5 rounded-full"><?= $deleted_result->num_rows ?></span>
                </a>
            </div>

            <?php if ($active_tab === 'active'): ?>
            <!-- Active Users Table -->
            <div class="bg-white border rounded-lg mb-6 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b text-left text-xs text-gray-500 uppercase tracking-wide">
                            <th class="px-4 py-3">ID</th>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Email</th>
                            <th class="px-4 py-3">Role</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Last Login</th>
                            <th class="px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($user = $result->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-gray-400 font-mono text-xs"><?= $user['user_id'] ?></td>
                                <td class="px-4 py-3 font-semibold text-gray-800"><?= htmlspecialchars($user['display_name'] ?? 'Unknown') ?></td>
                                <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($user['email']) ?></td>
                                <td class="px-4 py-3">
                                    <span class="<?php
                                        switch($user['role']) {
                                            case 'Admin':    echo 'bg-purple-100 text-purple-800'; break;
                                            case 'Employee': echo 'bg-blue-100 text-blue-800'; break;
                                            case 'Customer': echo 'bg-green-100 text-green-800'; break;
                                            default:         echo 'bg-gray-100 text-gray-800';
                                        }
                                    ?> px-2 py-1 rounded-full text-xs font-medium">
                                        <?= htmlspecialchars($user['role']) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="<?= $user['account_status'] === 'Active' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' ?> px-2 py-1 rounded-full text-xs font-medium">
                                        <?= htmlspecialchars($user['account_status']) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-500 text-xs"><?= $user['last_login'] ? date('M j, Y g:i A', strtotime($user['last_login'])) : 'Never' ?></td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <a href="edit_user.php?id=<?= $user['user_id'] ?>" class="text-blue-600 hover:text-blue-800 text-xs font-medium">
                                            <i class="fas fa-edit mr-1"></i>Edit
                                        </a>
                                        <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                        <a href="manage_users.php?action=toggle&id=<?= $user['user_id'] ?>&status=<?= htmlspecialchars($user['account_status']) ?>"
                                           class="<?= $user['account_status'] === 'Active' ? 'text-yellow-600 hover:text-yellow-800' : 'text-green-600 hover:text-green-800' ?> text-xs font-medium">
                                            <i class="fas <?= $user['account_status'] === 'Active' ? 'fa-ban' : 'fa-check-circle' ?> mr-1"></i>
                                            <?= $user['account_status'] === 'Active' ? 'Suspend' : 'Activate' ?>
                                        </a>
                                        <a href="manage_users.php?action=delete&id=<?= $user['user_id'] ?>"
                                           class="text-red-500 hover:text-red-700 text-xs font-medium"
                                           onclick="return confirm('Soft-delete this user? Their data will be kept and the account can be restored later.')">
                                            <i class="fas fa-trash mr-1"></i>Delete
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="px-4 py-6 text-center text-gray-400">No active users found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="flex justify-end gap-3">
                <a href="add_customer.php"
                   class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded text-sm font-semibold inline-flex items-center gap-2 transition">
                    <i class="fas fa-user-plus"></i> Add Customer
                </a>
                <a href="add_user.php"
                   class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm font-semibold inline-flex items-center gap-2 transition">
                    <i class="fas fa-id-badge"></i> Add Employee
                </a>
            </div>

            <?php else: ?>
            <!-- Deleted Users Table -->
            <div class="bg-white border border-red-100 rounded-lg mb-6 overflow-x-auto">
                <div class="px-4 py-3 bg-red-50 border-b border-red-100">
                    <p class="text-sm text-red-700"><i class="fas fa-info-circle mr-1"></i> Deleted users cannot log in. Their packages, payments, and history are fully preserved. You can restore any account at any time.</p>
                </div>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b text-left text-xs text-gray-500 uppercase tracking-wide">
                            <th class="px-4 py-3">ID</th>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Email</th>
                            <th class="px-4 py-3">Role</th>
                            <th class="px-4 py-3">Deleted On</th>
                            <th class="px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if ($deleted_result->num_rows > 0): ?>
                            <?php while ($user = $deleted_result->fetch_assoc()): ?>
                            <tr class="hover:bg-red-50 opacity-75">
                                <td class="px-4 py-3 text-gray-400 font-mono text-xs"><?= $user['user_id'] ?></td>
                                <td class="px-4 py-3 font-semibold text-gray-500 line-through"><?= htmlspecialchars($user['display_name'] ?? 'Unknown') ?></td>
                                <td class="px-4 py-3 text-gray-400"><?= htmlspecialchars($user['email']) ?></td>
                                <td class="px-4 py-3">
                                    <span class="bg-gray-100 text-gray-500 px-2 py-1 rounded-full text-xs"><?= htmlspecialchars($user['role']) ?></span>
                                </td>
                                <td class="px-4 py-3 text-gray-400 text-xs"><?= date('M j, Y g:i A', strtotime($user['deleted_at'])) ?></td>
                                <td class="px-4 py-3">
                                    <a href="manage_users.php?action=restore&id=<?= $user['user_id'] ?>"
                                       class="text-green-600 hover:text-green-800 text-xs font-medium"
                                       onclick="return confirm('Restore this user? Their account will be reactivated.')">
                                        <i class="fas fa-undo mr-1"></i>Restore
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">No deleted users.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</body>
</html>
