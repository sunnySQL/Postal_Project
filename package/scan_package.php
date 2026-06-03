<?php
/**
 * Scan package is now combined with Package Search.
 * Redirect to the combined Packages (Search & Scan) page.
 */
require_once '../functions.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || 
    ($_SESSION['role'] != 'Employee' && $_SESSION['role'] != 'Admin')) {
    header("Location: ../login.php");
    exit();
}

$tracking = isset($_GET['tracking']) ? '?tracking=' . urlencode(trim($_GET['tracking'])) : '';
header("Location: search.php" . $tracking);
exit();
