<?php
session_start();
require_once 'db_connect.php';
require_once 'functions.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error_message = '';
$success_message = '';
$redirect_url = $_SESSION['role'] == 'Customer' ? 'customer_dashboard.php' : 'employee_dashboard.php';

// Check if this is a forced password change (for new accounts/temp passwords)
$force_change = isset($_GET['force']) && $_GET['force'] == 1;

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate input
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = "All fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "New passwords do not match.";
    } elseif (strlen($new_password) < 8) {
        $error_message = "Password must be at least 8 characters long.";
    } else {
        // Verify current password
        $stmt = $conn->prepare("SELECT password FROM Users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($current_password, $user['password'])) {
                // Hash new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Update password in database
                $stmt = $conn->prepare("UPDATE Users SET password = ? WHERE user_id = ?");
                $stmt->bind_param("si", $hashed_password, $user_id);
                
                if ($stmt->execute()) {
                    $success_message = "Password updated successfully.";
                    
                    // If this was a forced password change, remove the flag
                    if ($force_change) {
                        // Set a session flag indicating password has been changed
                        $_SESSION['password_changed'] = true;
                        
                        // Redirect to dashboard after 2 seconds
                        header("refresh:2;url=$redirect_url");
                        exit();
                    }
                } else {
                    $error_message = "Error updating password: " . $conn->error;
                }
            } else {
                $error_message = "Current password is incorrect.";
            }
        } else {
            $error_message = "User not found.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <h1><?= $force_change ? 'Set New Password' : 'Change Password' ?></h1>
        
        <?php if ($force_change): ?>
            <div class="message info">
                <p>You need to change your temporary password before continuing.</p>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="message error">
                <?= $error_message ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success_message)): ?>
            <div class="message success">
                <?= $success_message ?>
            </div>
        <?php endif; ?>
        
        <form method="post" action="<?= $force_change ? 'change_password.php?force=1' : 'change_password.php' ?>" class="password-form">
            <div class="form-group">
                <label for="current_password">Current Password:</label>
                <input type="password" id="current_password" name="current_password" required>
            </div>
            
            <div class="form-group">
                <label for="new_password">New Password:</label>
                <input type="password" id="new_password" name="new_password" required 
                       pattern=".{8,}" title="Password must be at least 8 characters">
                <span class="form-hint">Must be at least 8 characters long</span>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm New Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            
            <div class="form-actions">
                <button type="submit">Change Password</button>
                <?php if (!$force_change): ?>
                <a href="<?= $redirect_url ?>" class="button secondary">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</body>
</html> 