<?php
session_start();
require_once 'db_connect.php';
require_once 'functions.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php?redirect=package_history.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$name = $_SESSION['name'];
$error = '';

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Filter settings
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Base query parts for sent packages
$base_sent_query = "
    SELECT p.*, f.city as facility_city, f.state as facility_state, 
           c.first_name as receiver_first_name, c.last_name as receiver_last_name,
           c.city as receiver_city, c.state as receiver_state
    FROM Package p
    LEFT JOIN Facility f ON p.facility_id = f.facility_id
    LEFT JOIN Customer c ON p.receiver_id = c.user_id
    WHERE p.sender_id = ?
";

// Base query parts for received packages
$base_received_query = "
    SELECT p.*, f.city as facility_city, f.state as facility_state, 
           c.first_name as sender_first_name, c.last_name as sender_last_name,
           c.city as sender_city, c.state as sender_state
    FROM Package p
    LEFT JOIN Facility f ON p.facility_id = f.facility_id
    LEFT JOIN Customer c ON p.sender_id = c.user_id
    WHERE p.receiver_id = ?
";

// Add filters
$where_additions = "";
$params = [$user_id]; // Start with user_id
$types = "i"; // Start with integer type for user_id

if (!empty($status_filter)) {
    $where_additions .= " AND p.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($date_from)) {
    $where_additions .= " AND DATE(p.timestamp_created) >= ?";
    $params[] = $date_from;
    $types .= "s";
}

if (!empty($date_to)) {
    $where_additions .= " AND DATE(p.timestamp_created) <= ?";
    $params[] = $date_to;
    $types .= "s";
}

if (!empty($search)) {
    $where_additions .= " AND (p.tracking_number LIKE ? OR p.description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

// Complete queries
$sent_query = $base_sent_query . $where_additions . " ORDER BY p.timestamp_created DESC LIMIT ?, ?";
$received_query = $base_received_query . $where_additions . " ORDER BY p.timestamp_created DESC LIMIT ?, ?";

// Add pagination parameters
$params[] = $offset;
$params[] = $per_page;
$types .= "ii";

// Prepare and execute for sent packages
$sent_stmt = $conn->prepare($sent_query);
$sent_stmt->bind_param($types, ...$params);
$sent_stmt->execute();
$sent_packages = $sent_stmt->get_result();

// Prepare and execute for received packages
$received_stmt = $conn->prepare($received_query);
$received_stmt->bind_param($types, ...$params);
$received_stmt->execute();
$received_packages = $received_stmt->get_result();

// Count total packages for pagination
// We need to remove LIMIT clause for counting
$count_sent_query = $base_sent_query . $where_additions;
$count_received_query = $base_received_query . $where_additions;

// Remove pagination params
array_pop($params); // Remove per_page
array_pop($params); // Remove offset
$count_types = substr($types, 0, -2); // Remove ii

$count_sent_stmt = $conn->prepare($count_sent_query);
$count_sent_stmt->bind_param($count_types, ...$params);
$count_sent_stmt->execute();
$total_sent = $count_sent_stmt->get_result()->num_rows;

$count_received_stmt = $conn->prepare($count_received_query);
$count_received_stmt->bind_param($count_types, ...$params);
$count_received_stmt->execute();
$total_received = $count_received_stmt->get_result()->num_rows;

$total_packages = $total_sent + $total_received;
$total_pages = ceil($total_packages / $per_page);

// Get all possible package statuses for filter dropdown
$status_query = "SELECT DISTINCT status FROM Package WHERE sender_id = ? OR receiver_id = ? ORDER BY status";
$status_stmt = $conn->prepare($status_query);
$status_stmt->bind_param("ii", $user_id, $user_id);
$status_stmt->execute();
$statuses = $status_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Package History | POSTAL PRO</title>
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
            <a href="index.php" class="text-white font-bold text-2xl">POSTAL PRO</a>
            <ul class="flex space-x-6">
                <li><a href="index.php" class="text-white hover:text-gray-200">Home</a></li>
                <li><a href="customer_dashboard.php" class="text-white hover:text-gray-200">Dashboard</a></li>
                <li><a href="logout.php" class="text-white hover:text-gray-200">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8 max-w-6xl mt-20">
        <header class="mb-6">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center">
                <h1 class="text-3xl font-bold section-title">Your Packages</h1>
                <a href="customer_dashboard.php" class="mt-4 md:mt-0 action-btn text-white px-4 py-2 rounded inline-flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                </a>
            </div>
        </header>
        
        <!-- Filter Form -->
        <div class="bg-white p-5 rounded-lg shadow-sm mb-6">
            <h2 class="text-lg font-semibold mb-3">Filter Packages</h2>
            <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md" 
                           placeholder="Tracking # or description">
                </div>
                
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select id="status" name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        <option value="">All Statuses</option>
                        <?php while ($status = $statuses->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($status['status']) ?>" 
                                    <?= $status_filter === $status['status'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($status['status']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div>
                    <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                    <input type="date" id="date_from" name="date_from" value="<?= htmlspecialchars($date_from) ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md">
                </div>
                
                <div>
                    <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                    <input type="date" id="date_to" name="date_to" value="<?= htmlspecialchars($date_to) ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md">
                </div>
                
                <div class="md:col-span-4 flex justify-end space-x-2">
                    <button type="submit" class="action-btn text-white px-4 py-2 rounded">Apply Filters</button>
                    <a href="package_history.php" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded">
                        Reset
                    </a>
                </div>
            </form>
        </div>
        
        <div class="mb-2 text-gray-600">
            <p>Showing packages <?= min(1, $total_packages) ?>-<?= min($total_packages, $offset + $per_page) ?> of <?= $total_packages ?></p>
        </div>
        
        <!-- Packages You've Sent -->
        <div class="bg-white p-5 rounded-lg shadow-sm mb-6">
            <div class="border-l-4 border-[#004B87] pl-3 mb-4">
                <h2 class="text-xl font-semibold">Packages You've Sent</h2>
            </div>
            
            <?php if ($sent_packages->num_rows > 0): ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-100 text-left">
                            <th class="px-4 py-2">Tracking Number</th>
                            <th class="px-4 py-2">Status</th>
                            <th class="px-4 py-2">Current Location</th>
                            <th class="px-4 py-2">Recipient</th>
                            <th class="px-4 py-2">Date Sent</th>
                            <th class="px-4 py-2">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($package = $sent_packages->fetch_assoc()): 
                            $status_class = '';
                            switch($package['status']) {
                                case 'Delivered': $status_class = 'bg-green-100 text-green-800'; break;
                                case 'In Transit': $status_class = 'bg-blue-100 text-blue-800'; break;
                                case 'Processing': 
                                case 'Shipped': $status_class = 'bg-yellow-100 text-yellow-800'; break;
                                case 'Out for Delivery': $status_class = 'bg-purple-100 text-purple-800'; break;
                                case 'Failed': $status_class = 'bg-red-100 text-red-800'; break;
                                case 'Returned': $status_class = 'bg-gray-100 text-gray-800'; break;
                                default: $status_class = 'bg-gray-100 text-gray-800';
                            }
                            
                            $current_location = !empty($package['facility_city']) 
                                ? htmlspecialchars($package['facility_city']) 
                                : 'Processing';
                                
                            $recipient = !empty($package['receiver_first_name']) 
                                ? htmlspecialchars($package['receiver_first_name'] . ' ' . $package['receiver_last_name']) 
                                : 'Unknown';
                        ?>
                        <tr class="border-b border-gray-200 hover:bg-gray-50">
                            <td class="px-4 py-2"><?= htmlspecialchars($package['tracking_number']) ?></td>
                            <td class="px-4 py-2">
                                <span class="<?= $status_class ?> px-2 py-1 rounded-full text-xs">
                                    <?= htmlspecialchars($package['status']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-2"><?= $current_location ?></td>
                            <td class="px-4 py-2"><?= $recipient ?></td>
                            <td class="px-4 py-2"><?= date('M d, Y', strtotime($package['timestamp_created'])) ?></td>
                            <td class="px-4 py-2">
                                <a href="package/track.php?tracking=<?= htmlspecialchars($package['tracking_number']) ?>" 
                                   class="text-[#004B87] hover:text-[#DA291C]">Details</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-gray-600">You haven't sent any packages<?= !empty($status_filter) || !empty($date_from) || !empty($date_to) || !empty($search) ? ' matching your criteria' : '' ?>.</p>
            <?php endif; ?>
        </div>
        
        <!-- Packages Coming to You -->
        <div class="bg-white p-5 rounded-lg shadow-sm mb-6">
            <div class="border-l-4 border-[#DA291C] pl-3 mb-4">
                <h2 class="text-xl font-semibold">Packages Coming to You</h2>
            </div>
            
            <?php if ($received_packages->num_rows > 0): ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-100 text-left">
                            <th class="px-4 py-2">Tracking Number</th>
                            <th class="px-4 py-2">From</th>
                            <th class="px-4 py-2">Status</th>
                            <th class="px-4 py-2">Current Location</th>
                            <th class="px-4 py-2">Date Shipped</th>
                            <th class="px-4 py-2">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($package = $received_packages->fetch_assoc()): 
                            $status_class = '';
                            switch($package['status']) {
                                case 'Delivered': $status_class = 'bg-green-100 text-green-800'; break;
                                case 'In Transit': $status_class = 'bg-blue-100 text-blue-800'; break;
                                case 'Processing': 
                                case 'Shipped': $status_class = 'bg-yellow-100 text-yellow-800'; break;
                                case 'Out for Delivery': $status_class = 'bg-purple-100 text-purple-800'; break;
                                case 'Failed': $status_class = 'bg-red-100 text-red-800'; break;
                                case 'Arrived at Facility': $status_class = 'bg-indigo-100 text-indigo-800'; break;
                                case 'Returned': $status_class = 'bg-gray-100 text-gray-800'; break;
                                default: $status_class = 'bg-gray-100 text-gray-800';
                            }
                            
                            $current_location = !empty($package['facility_city']) 
                                ? htmlspecialchars($package['facility_city']) 
                                : 'Processing';
                                
                            $sender = !empty($package['sender_first_name']) 
                                ? htmlspecialchars($package['sender_first_name'] . ' ' . $package['sender_last_name']) 
                                : 'Unknown';
                        ?>
                        <tr class="border-b border-gray-200 hover:bg-gray-50">
                            <td class="px-4 py-2"><?= htmlspecialchars($package['tracking_number']) ?></td>
                            <td class="px-4 py-2"><?= $sender ?></td>
                            <td class="px-4 py-2">
                                <span class="<?= $status_class ?> px-2 py-1 rounded-full text-xs">
                                    <?= htmlspecialchars($package['status']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-2"><?= $current_location ?></td>
                            <td class="px-4 py-2"><?= date('M d, Y', strtotime($package['timestamp_created'])) ?></td>
                            <td class="px-4 py-2">
                                <a href="package/track.php?tracking=<?= htmlspecialchars($package['tracking_number']) ?>" 
                                   class="text-[#004B87] hover:text-[#DA291C]">Details</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-gray-600">You don't have any incoming packages<?= !empty($status_filter) || !empty($date_from) || !empty($date_to) || !empty($search) ? ' matching your criteria' : '' ?>.</p>
            <?php endif; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="flex justify-center mt-6">
            <nav class="inline-flex rounded-md shadow-sm" aria-label="Pagination">
                <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>&status=<?= urlencode($status_filter) ?>&search=<?= urlencode($search) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>" 
                   class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                    <span class="sr-only">Previous</span>
                    <i class="fas fa-chevron-left"></i>
                </a>
                <?php endif; ?>
                
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($start_page + 4, $total_pages);
                
                if ($start_page > 1): ?>
                <a href="?page=1&status=<?= urlencode($status_filter) ?>&search=<?= urlencode($search) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>" 
                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                    1
                </a>
                <?php if ($start_page > 2): ?>
                <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                    ...
                </span>
                <?php endif; ?>
                <?php endif; ?>
                
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                <a href="?page=<?= $i ?>&status=<?= urlencode($status_filter) ?>&search=<?= urlencode($search) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>" 
                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 <?= $i === $page ? 'bg-blue-50 text-blue-600 font-bold' : 'bg-white text-gray-700 hover:bg-gray-50' ?> text-sm font-medium">
                    <?= $i ?>
                </a>
                <?php endfor; ?>
                
                <?php if ($end_page < $total_pages): ?>
                <?php if ($end_page < $total_pages - 1): ?>
                <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                    ...
                </span>
                <?php endif; ?>
                <a href="?page=<?= $total_pages ?>&status=<?= urlencode($status_filter) ?>&search=<?= urlencode($search) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>" 
                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                    <?= $total_pages ?>
                </a>
                <?php endif; ?>
                
                <?php if ($page < $total_pages): ?>
                <a href="?page=<?= $page + 1 ?>&status=<?= urlencode($status_filter) ?>&search=<?= urlencode($search) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>" 
                   class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                    <span class="sr-only">Next</span>
                    <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>
            </nav>
        </div>
        <?php endif; ?>
        
        <div class="text-center mt-8">
            <a href="new_shipment.php" class="accent-btn text-white px-6 py-3 rounded font-semibold">
                Create New Shipment
            </a>
        </div>
    </div>
    
    <footer class="text-center mt-10 py-6 bg-[#004B87] text-white rounded-lg mb-6 mx-4">
        <p>&copy; <?= date('Y') ?> Postal Service Management System</p>
    </footer>
</body>
</html> 