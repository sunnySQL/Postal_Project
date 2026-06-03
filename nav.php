<?php
session_start();
?>
<nav>
    <ul>
        <li><a href="index.php">Home</a></li>
        <?php if (isset($_SESSION["user_id"])): ?>
            <?php if (isset($_SESSION["role"]) && $_SESSION["role"] === "Customer"): ?>
                <li><a href="customer_dashboard.php">Dashboard</a></li>
            <?php else: ?>
                <li><a href="dashboard.php">Dashboard</a></li>
            <?php endif; ?>
            <li><a href="logout.php">Logout</a></li>
        <?php else: ?>
            <li><a href="register.php">Register</a></li>
            <li><a href="login.php">Login</a></li>
        <?php endif; ?>
    </ul>
</nav>
