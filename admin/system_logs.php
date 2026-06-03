<?php
// Display all PHP errors for debugging
session_start();
require_once '../db_connect.php';
require_once '../functions.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'Admin') {
    header("Location: ../login.php");
    exit();
}

// Check if Tracking_History table exists
$table_exists = false;
$tables_result = $conn->query("SHOW TABLES LIKE 'Tracking_History'");
if ($tables_result && $tables_result->num_rows > 0) {
    $table_exists = true;
}

// If table doesn't exist, create it
if (!$table_exists) {
    $create_table = "CREATE TABLE IF NOT EXISTS Tracking_History (
        tracking_history_id INT AUTO_INCREMENT PRIMARY KEY,
        tracking_number VARCHAR(50) NOT NULL,
        action VARCHAR(50) NOT NULL,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        facility_id INT,
        employee_id INT,
        FOREIGN KEY (facility_id) REFERENCES Facility(facility_id) ON DELETE SET NULL,
        FOREIGN KEY (employee_id) REFERENCES Employee(user_id) ON DELETE SET NULL
    )";
    
    try {
        $conn->query($create_table);
        echo "<div style='background: yellow; padding: 10px; margin-bottom: 20px;'>Tracking_History table was missing and has been created. Sample data will be added.</div>";
        $table_exists = true;
        
        // Now add some sample data
        try {
            // First check if we have some facilities and employees to reference
            $facility_id = null;
            $employee_id = null;
            
            $facility_result = $conn->query("SELECT facility_id FROM Facility LIMIT 1");
            if ($facility_result && $facility_result->num_rows > 0) {
                $facility_id = $facility_result->fetch_assoc()['facility_id'];
            }
            
            $employee_result = $conn->query("SELECT user_id FROM Employee LIMIT 1");
            if ($employee_result && $employee_result->num_rows > 0) {
                $employee_id = $employee_result->fetch_assoc()['user_id'];
            }
            
            // Get some existing package tracking numbers if available
            $tracking_numbers = [];
            $package_result = $conn->query("SELECT tracking_number FROM Package LIMIT 5");
            if ($package_result && $package_result->num_rows > 0) {
                while ($row = $package_result->fetch_assoc()) {
                    $tracking_numbers[] = $row['tracking_number'];
                }
            }
            
            // If no packages exist, create some sample tracking numbers
            if (empty($tracking_numbers)) {
                $tracking_numbers = [
                    'PS'. rand(1000000000, 9999999999),
                    'PS'. rand(1000000000, 9999999999),
                    'PS'. rand(1000000000, 9999999999)
                ];
            }
            
            // Sample actions
            $actions = ['Processing', 'Scanning', 'Loading', 'Delivery', 'Arrival'];
            
            // Insert sample data
            $sample_data_query = "INSERT INTO Tracking_History 
                (tracking_number, action, timestamp, facility_id, employee_id) VALUES 
                (?, ?, DATE_SUB(NOW(), INTERVAL ? HOUR), ?, ?)";
            
            $sample_stmt = $conn->prepare($sample_data_query);
            
            if ($sample_stmt) {
                // Add several sample entries with different timestamps
                for ($i = 0; $i < 10; $i++) {
                    $tracking = $tracking_numbers[array_rand($tracking_numbers)];
                    $action = $actions[array_rand($actions)];
                    $hours_ago = rand(1, 168); // Within last week
                    
                    $sample_stmt->bind_param("ssiii", $tracking, $action, $hours_ago, $facility_id, $employee_id);
                    $sample_stmt->execute();
                }
                
                echo "<div style='background: #ccffcc; padding: 10px; margin-bottom: 20px;'>Sample tracking data has been added.</div>";
            }
        } catch (Exception $e) {
            echo "<div style='background: #ffcccc; padding: 10px; margin-bottom: 20px;'>Error adding sample data: " . $e->getMessage() . "</div>";
        }
    } catch (Exception $e) {
        echo "<div style='background: #ffcccc; padding: 10px; margin-bottom: 20px;'>Error creating Tracking_History table: " . $e->getMessage() . "</div>";
    }
} else {
    // Check if the table is empty and add sample data if needed
    $count_check = $conn->query("SELECT COUNT(*) as count FROM Tracking_History");
    if ($count_check && $count_check->fetch_assoc()['count'] == 0) {
        // The logic to add sample data could be reused here if needed
        // For brevity, we'll just show a message
        echo "<div style='background: yellow; padding: 10px; margin-bottom: 20px;'>Tracking_History table exists but has no data yet. Data will appear when packages are processed.</div>";
    }
}

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 20;
$offset = ($page - 1) * $records_per_page;

// Filtering options
$filter_type = isset($_GET['filter']) ? $_GET['filter'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Base query
$query = "SELECT th.*, f.city as facility_name,
          e.first_name, e.last_name, e.role
          FROM Tracking_History th
          LEFT JOIN Facility f ON th.facility_id = f.facility_id
          LEFT JOIN Employee e ON th.employee_id = e.user_id";

$count_query = "SELECT COUNT(*) as total FROM Tracking_History th";

// Add filters if specified
$where_clauses = [];
$params = [];
$types = "";

if (!empty($search)) {
    $where_clauses[] = "(th.tracking_number LIKE ? OR th.action LIKE ? OR e.first_name LIKE ? OR e.last_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssss";
}

if (!empty($filter_type)) {
    $where_clauses[] = "th.action = ?";
    $params[] = $filter_type;
    $types .= "s";
}

if (!empty($date_from)) {
    $where_clauses[] = "DATE(th.timestamp) >= ?";
    $params[] = $date_from;
    $types .= "s";
}

if (!empty($date_to)) {
    $where_clauses[] = "DATE(th.timestamp) <= ?";
    $params[] = $date_to;
    $types .= "s";
}

// Build the final query
if (!empty($where_clauses)) {
    $query .= " WHERE " . implode(" AND ", $where_clauses);
    $count_query .= " WHERE " . implode(" AND ", $where_clauses);
}

$query .= " ORDER BY th.timestamp DESC LIMIT ? OFFSET ?";
$params[] = $records_per_page;
$params[] = $offset;
$types .= "ii";

// Initialize variables that might be used in case of errors
$result = null;
$total_records = 0;
$total_pages = 1;
$error_message = '';

try {
    // Prepare and execute the query
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to execute statement: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    // Get total records for pagination
    $count_stmt = $conn->prepare($count_query);
    if ($count_stmt === false) {
        throw new Exception("Failed to prepare count statement: " . $conn->error);
    }
    
    if (!empty($params)) {
        // Remove the last two parameters (limit and offset) for the count query
        array_pop($params);
        array_pop($params);
        $count_types = substr($types, 0, -2);
        if (!empty($params)) {
            $count_stmt->bind_param($count_types, ...$params);
        }
    }
    
    if (!$count_stmt->execute()) {
        throw new Exception("Failed to execute count statement: " . $count_stmt->error);
    }
    
    $count_result = $count_stmt->get_result();
    if ($count_result) {
        $total_records = $count_result->fetch_assoc()['total'];
        $total_pages = ceil($total_records / $records_per_page);
    }
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
    // Continue execution to display the error on the page
}

// Get distinct action types for filtering
$action_types = [];
try {
    $action_types_query = "SELECT DISTINCT action FROM Tracking_History ORDER BY action";
    $action_types_result = $conn->query($action_types_query);
    if ($action_types_result) {
        while ($row = $action_types_result->fetch_assoc()) {
            $action_types[] = $row['action'];
        }
    }
} catch (Exception $e) {
    // Just log the error but continue
    error_log("Failed to get action types: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Activity Logs - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap');
        
        * {
            font-family: 'Open Sans', sans-serif;
        }
        
        body {
            background-color: #f8f9fa;
        }
        
        .action-btn {
            background-color: #004B87;
            transition: background-color 0.3s;
        }
        
        .action-btn:hover {
            background-color: #003366;
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include '_nav.php'; ?>

    <div class="container mx-auto px-4 py-8 max-w-6xl">
        <header class="mb-6 flex flex-col md:flex-row md:justify-between md:items-center">
            <h1 class="text-3xl font-bold text-[#004B87] mb-4 md:mb-0">System Activity Logs</h1>
            <div class="flex space-x-2">
                <a href="../employee_dashboard.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                </a>
            </div>
        </header>

        <?php if (!empty($error_message)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
            <p class="font-semibold">An error occurred:</p>
            <p><?= htmlspecialchars($error_message) ?></p>
            <p class="mt-2">
                <a href="../employee_dashboard.php" class="underline">Return to dashboard</a> or 
                <a href="system_logs.php" class="underline">try again</a>
            </p>
        </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="bg-white p-4 rounded-lg shadow-md mb-6">
            <h2 class="text-lg font-semibold mb-3">Filter Activity Logs</h2>
            <form action="" method="GET" class="flex flex-wrap gap-3">
                <div class="flex-1 min-w-[200px]">
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md" 
                           placeholder="Tracking #, Employee, Action">
                </div>
                
                <div class="flex-1 min-w-[150px]">
                    <label for="filter" class="block text-sm font-medium text-gray-700 mb-1">Action Type</label>
                    <select id="filter" name="filter" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        <option value="">All Types</option>
                        <?php foreach ($action_types as $type): ?>
                            <option value="<?= htmlspecialchars($type) ?>" <?= $filter_type === $type ? 'selected' : '' ?>>
                                <?= htmlspecialchars($type) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="flex-1 min-w-[150px]">
                    <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                    <input type="date" id="date_from" name="date_from" value="<?= htmlspecialchars($date_from) ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md">
                </div>
                
                <div class="flex-1 min-w-[150px]">
                    <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                    <input type="date" id="date_to" name="date_to" value="<?= htmlspecialchars($date_to) ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md">
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="h-[42px] action-btn text-white px-4 py-2 rounded-md">Apply Filters</button>
                    <a href="system_logs.php" class="h-[42px] ml-2 bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-md flex items-center">
                        <i class="fas fa-times mr-1"></i> Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Activity Logs Table -->
        <div class="bg-white p-4 rounded-lg shadow-md mb-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold">Activity Logs</h2>
                <p class="text-sm text-gray-600">
                    Showing <?= min(($page - 1) * $records_per_page + 1, $total_records) ?> - 
                    <?= min($page * $records_per_page, $total_records) ?> of <?= $total_records ?> records
                </p>
            </div>

            <?php if ($result && $result->num_rows > 0): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tracking Number</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Facility</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php while ($row = $result->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 whitespace-nowrap">
                                <?php 
                                $action_icon = '';
                                $action_color = '';
                                switch($row['action']) {
                                    case 'Processing': 
                                        $action_icon = 'fa-cogs'; 
                                        $action_color = 'bg-blue-100 text-blue-800';
                                        break;
                                    case 'Scanning': 
                                        $action_icon = 'fa-barcode'; 
                                        $action_color = 'bg-purple-100 text-purple-800';
                                        break;
                                    case 'Loading': 
                                        $action_icon = 'fa-truck-loading'; 
                                        $action_color = 'bg-orange-100 text-orange-800';
                                        break;
                                    case 'Delivery': 
                                        $action_icon = 'fa-truck'; 
                                        $action_color = 'bg-green-100 text-green-800';
                                        break;
                                    default: 
                                        $action_icon = 'fa-box'; 
                                        $action_color = 'bg-gray-100 text-gray-800';
                                }
                                ?>
                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?= $action_color ?>">
                                    <i class="fas <?= $action_icon ?> mr-1"></i> <?= htmlspecialchars($row['action']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
<a href="track_package.php?tracking=<?= htmlspecialchars(urlencode($row['tracking_number'])) ?>"
                                   class="text-blue-600 hover:text-blue-900 hover:underline">
                                    <?= htmlspecialchars($row['tracking_number']) ?>
                                </a>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <?php if (!empty($row['first_name']) && !empty($row['last_name'])): ?>
                                    <div class="text-sm text-gray-900">
                                        <?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?>
                                    </div>
                                    <div class="text-xs text-gray-500"><?= htmlspecialchars($row['role']) ?></div>
                                <?php else: ?>
                                    <span class="text-gray-400">Unknown</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <?php if (!empty($row['facility_name'])): ?>
                                    <?= htmlspecialchars($row['facility_name']) ?>
                                <?php else: ?>
                                    <span class="text-gray-400">Unknown</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                <?= date('M d, Y H:i:s', strtotime($row['timestamp'])) ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="mt-4 flex justify-center">
                <nav class="inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&filter=<?= urlencode($filter_type) ?>&search=<?= urlencode($search) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>" 
                       class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                        <span class="sr-only">Previous</span>
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($start_page + 4, $total_pages);
                    
                    if ($start_page > 1): ?>
                    <a href="?page=1&filter=<?= urlencode($filter_type) ?>&search=<?= urlencode($search) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>" 
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
                    <a href="?page=<?= $i ?>&filter=<?= urlencode($filter_type) ?>&search=<?= urlencode($search) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>" 
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
                    <a href="?page=<?= $total_pages ?>&filter=<?= urlencode($filter_type) ?>&search=<?= urlencode($search) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>" 
                       class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                        <?= $total_pages ?>
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>&filter=<?= urlencode($filter_type) ?>&search=<?= urlencode($search) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>" 
                       class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                        <span class="sr-only">Next</span>
                        <i class="fas fa-chevron-right"></i>
                    </a>
                    <?php endif; ?>
                </nav>
            </div>
            <?php endif; ?>
            
            <?php else: ?>
            <div class="text-center py-8 text-gray-500">
                <i class="fas fa-search text-4xl mb-3"></i>
                <?php if ($table_exists): ?>
                    <p>No activity logs found matching your criteria</p>
                <?php else: ?>
                    <p>The Tracking History system has been set up but no tracking data is available yet.</p>
                    <p class="mt-2">Data will appear here once packages are processed in the system.</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <footer class="text-center mt-10 py-6 bg-[#004B87] text-white rounded-lg mb-6 mx-4">
        <p>&copy; <?= date('Y') ?> Postal Service Management System</p>
    </footer>
</body>
</html> 