<?php
require_once '../functions.php';
require_once '../db_connect.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || 
    ($_SESSION['role'] != 'Employee' && $_SESSION['role'] != 'Admin')) {
    header("Location: ../login.php");
    exit();
}

if (isset($_SESSION['package_data'])) {
    unset($_SESSION['package_data']);
}

$_SESSION['info_message'] = "Package transaction has been cancelled.";

header("Location: ../employee_dashboard.php");
exit();
