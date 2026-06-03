<?php
require_once '../db_connect.php';
require_once '../functions.php';
session_start();

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$facility_filter = isset($_GET['facility']) ? intval($_GET['facility']) : 0;
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'volume_by_origin';
$package_page = isset($_GET['package_page']) ? max(1, intval($_GET['package_page'])) : 1;
$per_page = 25; // Number of records per page

// Function to get package volume by origin
function getPackageVolumeByOrigin($conn, $start_date, $end_date, $facility_filter = 0) {
    $facility_condition = $facility_filter > 0 ? "AND p.facility_id = ?" : "";
    $query = "SELECT 
                f.facility_id, 
                f.city, 
                f.state, 
                f.type, 
                COUNT(p.tracking_number) as package_count
              FROM 
                Package p
              JOIN 
                Facility f ON p.facility_id = f.facility_id
              WHERE 
                p.timestamp_created BETWEEN ? AND ? 
                $facility_condition
              GROUP BY 
                f.facility_id, f.city, f.state, f.type
              ORDER BY 
                package_count DESC";
    
    $stmt = $conn->prepare($query);
    if ($facility_filter > 0) {
        $stmt->bind_param("ssi", $start_date, $end_date, $facility_filter);
    } else {
        $stmt->bind_param("ss", $start_date, $end_date);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Function to get package volume by destination
function getPackageVolumeByDestination($conn, $start_date, $end_date, $facility_filter = 0) {
    $facility_condition = $facility_filter > 0 ? "AND p.facility_id = ?" : "";
    $query = "SELECT 
                c.city, 
                c.state, 
                COUNT(p.tracking_number) as package_count
              FROM 
                Package p
              JOIN 
                Customer c ON p.receiver_id = c.user_id
              WHERE 
                p.timestamp_created BETWEEN ? AND ?
                $facility_condition
              GROUP BY 
                c.city, c.state
              ORDER BY 
                package_count DESC";
    
    $stmt = $conn->prepare($query);
    if ($facility_filter > 0) {
        $stmt->bind_param("ssi", $start_date, $end_date, $facility_filter);
    } else {
        $stmt->bind_param("ss", $start_date, $end_date);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Function to get busiest routes
function getBusiestRoutes($conn, $start_date, $end_date, $facility_filter = 0) {
    $facility_condition = $facility_filter > 0 ? "AND (t.depart_facility_id = ? OR t.arrive_facility_id = ?)" : "";
    $query = "SELECT 
                f1.city as origin_city, 
                f1.state as origin_state,
                f2.city as dest_city,
                f2.state as dest_state,
                COUNT(tp.tracking_number) as package_count
              FROM 
                Trip_Package tp
              JOIN 
                Trip t ON tp.trip_id = t.trip_id
              JOIN 
                Facility f1 ON t.depart_facility_id = f1.facility_id
              JOIN 
                Facility f2 ON t.arrive_facility_id = f2.facility_id
              JOIN 
                Package p ON tp.tracking_number = p.tracking_number
              WHERE 
                p.timestamp_created BETWEEN ? AND ?
                $facility_condition
              GROUP BY 
                f1.city, f1.state, f2.city, f2.state
              ORDER BY 
                package_count DESC
              LIMIT 10";
    
    $stmt = $conn->prepare($query);
    if ($facility_filter > 0) {
        $stmt->bind_param("ssii", $start_date, $end_date, $facility_filter, $facility_filter);
    } else {
        $stmt->bind_param("ss", $start_date, $end_date);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Function to get package distribution by size
function getPackageDistributionBySize($conn, $start_date, $end_date, $facility_filter = 0) {
    $facility_condition = $facility_filter > 0 ? "AND facility_id = ?" : "";
    $query = "SELECT 
                size, 
                COUNT(*) as count
              FROM 
                Package
              WHERE 
                timestamp_created BETWEEN ? AND ?
                $facility_condition
              GROUP BY 
                size";
    
    if ($facility_filter > 0) {
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssi", $start_date, $end_date, $facility_filter);
    } else {
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $start_date, $end_date);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Function to get package distribution by weight
function getPackageDistributionByWeight($conn, $start_date, $end_date, $facility_filter = 0) {
    $facility_condition = $facility_filter > 0 ? "AND facility_id = ?" : "";
    $query = "SELECT 
                CASE
                    WHEN weight <= 1 THEN 'Under 1 lb'
                    WHEN weight <= 5 THEN '1-5 lbs'
                    WHEN weight <= 10 THEN '5-10 lbs'
                    WHEN weight <= 20 THEN '10-20 lbs'
                    ELSE 'Over 20 lbs'
                END as weight_range,
                COUNT(*) as count
              FROM 
                Package
              WHERE 
                timestamp_created BETWEEN ? AND ?
                $facility_condition
              GROUP BY 
                weight_range
              ORDER BY 
                MIN(weight)";
    
    if ($facility_filter > 0) {
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssi", $start_date, $end_date, $facility_filter);
    } else {
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $start_date, $end_date);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Function to get top customers by shipping volume
function getTopCustomers($conn, $start_date, $end_date, $limit = 10, $facility_filter = 0) {
    $query = "SELECT 
                c.user_id, 
                c.first_name, 
                c.last_name, 
                COUNT(pp.payment_id) as package_count,
                SUM(p.weight) as total_weight,
                AVG(pp.amount) as avg_postage
              FROM 
                Package_Payment pp
              JOIN 
                Package p ON pp.package_id = p.tracking_number
              JOIN 
                Customer c ON pp.user_id = c.user_id
              WHERE 
                pp.payment_date BETWEEN ? AND ?";
    
    // Add facility filter condition if applicable
    if ($facility_filter > 0) {
        $query .= " AND pp.facility_id = ?";
    }
    
    // Complete the query with GROUP BY, ORDER BY, and LIMIT
    $query .= " GROUP BY 
                c.user_id, c.first_name, c.last_name
              ORDER BY 
                package_count DESC
              LIMIT ?";
    
    $stmt = $conn->prepare($query);
    
    // Bind parameters in the correct order
    if ($facility_filter > 0) {
        $stmt->bind_param("ssii", $start_date, $end_date, $facility_filter, $limit);
    } else {
        $stmt->bind_param("ssi", $start_date, $end_date, $limit);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Add function to get raw package data with pagination
function getRawPackageData($conn, $start_date, $end_date, $facility_filter = 0, $page = 1, $per_page = 25) {
    $facility_condition = $facility_filter > 0 ? "AND p.facility_id = ?" : "";
    $offset = ($page - 1) * $per_page;
    
    // First, get total count
    $count_query = "SELECT COUNT(*) as total 
                    FROM Package p
                    LEFT JOIN Customer sender ON p.sender_id = sender.user_id
                    LEFT JOIN Customer receiver ON p.receiver_id = receiver.user_id
                    LEFT JOIN Facility f ON p.facility_id = f.facility_id
                    WHERE p.timestamp_created BETWEEN ? AND ?
                    $facility_condition";
                    
    if ($facility_filter > 0) {
        $stmt = $conn->prepare($count_query);
        $stmt->bind_param("ssi", $start_date, $end_date, $facility_filter);
    } else {
        $stmt = $conn->prepare($count_query);
        $stmt->bind_param("ss", $start_date, $end_date);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $total_count = $result->fetch_assoc()['total'];
    $total_pages = ceil($total_count / $per_page);
    
    // Then get paginated data
    $query = "SELECT 
                p.tracking_number,
                p.weight,
                p.size,
                p.postage,
                p.signature_required,
                p.shipping_speed,
                p.status,
                DATE_FORMAT(p.timestamp_created, '%Y-%m-%d %H:%i') as creation_date,
                CONCAT(sender.first_name, ' ', sender.last_name) as sender_name,
                CONCAT(sender.city, ', ', sender.state) as sender_location,
                CONCAT(receiver.first_name, ' ', receiver.last_name) as receiver_name,
                CONCAT(receiver.city, ', ', receiver.state) as receiver_location,
                CONCAT(f.city, ', ', f.state) as facility_location,
                f.type as facility_type
              FROM 
                Package p
              LEFT JOIN 
                Customer sender ON p.sender_id = sender.user_id
              LEFT JOIN 
                Customer receiver ON p.receiver_id = receiver.user_id
              LEFT JOIN 
                Facility f ON p.facility_id = f.facility_id
              WHERE 
                p.timestamp_created BETWEEN ? AND ?
                $facility_condition
              ORDER BY 
                p.timestamp_created DESC
              LIMIT ?, ?";
    
    if ($facility_filter > 0) {
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssiii", $start_date, $end_date, $facility_filter, $offset, $per_page);
    } else {
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssii", $start_date, $end_date, $offset, $per_page);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    return [
        'data' => $result->fetch_all(MYSQLI_ASSOC),
        'total_count' => $total_count,
        'total_pages' => $total_pages
    ];
}

// Add pagination function (same as in sales_report.php)
function renderPagination($current_page, $total_pages, $page_param_name, $base_url = '') {
    if ($total_pages <= 1) return '';
    
    // Build the base URL with existing filters but without the page parameter
    if (empty($base_url)) {
        $base_url = '?';
        foreach ($_GET as $key => $value) {
            if ($key != $page_param_name) {
                $base_url .= htmlspecialchars($key) . '=' . htmlspecialchars($value) . '&';
            }
        }
    }
    
    $html = '<div class="flex items-center justify-between mt-4">';
    $html .= '<div class="text-sm text-gray-700">Showing page <span class="font-medium">' . $current_page . '</span> of <span class="font-medium">' . $total_pages . '</span></div>';
    $html .= '<div class="flex space-x-1">';
    
    // Previous button
    if ($current_page > 1) {
        $html .= '<a href="' . $base_url . $page_param_name . '=' . ($current_page - 1) . '" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">Previous</a>';
    } else {
        $html .= '<span class="px-4 py-2 text-sm font-medium text-gray-400 bg-gray-100 border border-gray-300 rounded-md cursor-not-allowed">Previous</span>';
    }
    
    // Pages
    $start_page = max(1, $current_page - 2);
    $end_page = min($total_pages, $current_page + 2);
    
    if ($start_page > 1) {
        $html .= '<a href="' . $base_url . $page_param_name . '=1" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">1</a>';
        if ($start_page > 2) {
            $html .= '<span class="px-4 py-2 text-sm font-medium text-gray-700">...</span>';
        }
    }
    
    for ($i = $start_page; $i <= $end_page; $i++) {
        if ($i == $current_page) {
            $html .= '<span class="px-4 py-2 text-sm font-medium text-white bg-blue-500 border border-blue-500 rounded-md">' . $i . '</span>';
        } else {
            $html .= '<a href="' . $base_url . $page_param_name . '=' . $i . '" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">' . $i . '</a>';
        }
    }
    
    if ($end_page < $total_pages) {
        if ($end_page < $total_pages - 1) {
            $html .= '<span class="px-4 py-2 text-sm font-medium text-gray-700">...</span>';
        }
        $html .= '<a href="' . $base_url . $page_param_name . '=' . $total_pages . '" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">' . $total_pages . '</a>';
    }
    
    // Next button
    if ($current_page < $total_pages) {
        $html .= '<a href="' . $base_url . $page_param_name . '=' . ($current_page + 1) . '" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">Next</a>';
    } else {
        $html .= '<span class="px-4 py-2 text-sm font-medium text-gray-400 bg-gray-100 border border-gray-300 rounded-md cursor-not-allowed">Next</span>';
    }
    
    $html .= '</div></div>';
    
    return $html;
}

// Get list of facilities for the filter dropdown
$facilities_query = "SELECT facility_id, CONCAT(city, ', ', state, ' (', type, ')') as facility_name FROM Facility ORDER BY state, city";
$facilities = $conn->query($facilities_query)->fetch_all(MYSQLI_ASSOC);

// Get current user's facility ID and role from the session data
$current_user_id = $_SESSION['user_id'] ?? 0;
$current_user_role = $_SESSION['role'] ?? '';

// Get the user's facility ID if they're an employee
$user_facility_id = 0;
if ($current_user_id > 0) {
    $facility_stmt = $conn->prepare("SELECT facility_id FROM Employee WHERE user_id = ?");
    $facility_stmt->bind_param("i", $current_user_id);
    $facility_stmt->execute();
    $facility_result = $facility_stmt->get_result();
    if ($facility_result->num_rows > 0) {
        $user_facility_id = $facility_result->fetch_assoc()['facility_id'];
    }
}

// For managers (not admins), set their facility as the default and only option
if ($current_user_role != 'Admin' && $user_facility_id > 0) {
    $facility_filter = $user_facility_id;
}

// Execute the appropriate query based on report type
$report_data = [];
switch($report_type) {
    case 'volume_by_origin':
        $report_data = getPackageVolumeByOrigin($conn, $start_date, $end_date, $facility_filter);
        $title = "Package Volume by Origin";
        break;
    case 'volume_by_destination':
        $report_data = getPackageVolumeByDestination($conn, $start_date, $end_date, $facility_filter);
        $title = "Package Volume by Destination";
        break;
    case 'busiest_routes':
        $report_data = getBusiestRoutes($conn, $start_date, $end_date, $facility_filter);
        $title = "Busiest Routes";
        break;
    case 'distribution_by_size':
        $report_data = getPackageDistributionBySize($conn, $start_date, $end_date, $facility_filter);
        $title = "Package Distribution by Size";
        break;
    case 'distribution_by_weight':
        $report_data = getPackageDistributionByWeight($conn, $start_date, $end_date, $facility_filter);
        $title = "Package Distribution by Weight";
        break;
    case 'top_customers':
        $report_data = getTopCustomers($conn, $start_date, $end_date, 10, $facility_filter);
        $title = "Top Customers by Shipping Volume";
        break;
    default:
        $report_data = getPackageVolumeByOrigin($conn, $start_date, $end_date, $facility_filter);
        $title = "Package Volume by Origin";
}

// Get raw package data
$package_data_result = getRawPackageData($conn, $start_date, $end_date, $facility_filter, $package_page, $per_page);
$package_data = $package_data_result['data'];
$package_total_count = $package_data_result['total_count'];
$package_total_pages = $package_data_result['total_pages'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Package Volume and Distribution Report</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        .chart-container {
            position: relative;
            height: 400px;
            margin-bottom: 30px;
        }
        
        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
    <script>
        // Function to toggle facility filter based on report type
        function toggleFacilityFilter() {
            const reportType = document.getElementById('report_type').value;
            const facilityFilter = document.getElementById('facility');
            const facilityFilterContainer = facilityFilter.parentElement;
            const facilityNote = facilityFilterContainer.querySelector('#facility-help');
            
            // Enable facility filter for selected report types
            const enabledReportTypes = ['top_customers', 'distribution_by_size', 'distribution_by_weight'];
            
            if (enabledReportTypes.includes(reportType)) {
                facilityFilter.disabled = false;
                facilityFilterContainer.classList.remove('opacity-50');
                if (facilityNote) {
                    facilityNote.textContent = "";
                }
            } else {
                facilityFilter.disabled = true;
                facilityFilter.value = "0"; // Reset to "All Facilities"
                facilityFilterContainer.classList.add('opacity-50');
                if (facilityNote) {
                    facilityNote.textContent = "Not available for this report type";
                }
            }
        }
        
        // Initialize the filter state when the page loads
        document.addEventListener('DOMContentLoaded', function() {
            toggleFacilityFilter();
            
            // Add event listener for report type changes
            document.getElementById('report_type').addEventListener('change', toggleFacilityFilter);
            
            // Add event listener for form submission
            document.querySelector('form').addEventListener('submit', function(e) {
                const reportType = document.getElementById('report_type').value;
                const facilityFilter = document.getElementById('facility');
                const enabledReportTypes = ['top_customers', 'distribution_by_size', 'distribution_by_weight'];
                
                // If not an enabled report type, make sure facility filter is set to 0
                if (!enabledReportTypes.includes(reportType)) {
                    facilityFilter.value = "0";
                }
            });
        });
    </script>
</head>
<body class="bg-gray-50">
    <nav class="top-nav fixed w-full top-0 left-0 z-50 shadow-md px-4 py-4 no-print">
        <div class="container mx-auto flex justify-between items-center">
            <a href="../index.php" class="text-white font-bold text-2xl">POSTAL PRO</a>
            <ul class="flex space-x-6">
                <li><a href="../index.php" class="text-white hover:text-gray-200">Home</a></li>
                <li><a href="../employee_dashboard.php" class="text-white hover:text-gray-200">Dashboard</a></li>
                <li><a href="index.php" class="text-white hover:text-gray-200">Reports</a></li>
                <li><a href="../logout.php" class="text-white hover:text-gray-200">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8 max-w-6xl mt-20">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-3xl font-bold section-title">Package Volume and Distribution Report</h1>
            <a href="index.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded flex items-center no-print">
                <i class="fas fa-arrow-left mr-2"></i> Back to Reports
            </a>
        </div>
        
        <!-- Filter Section -->
        <div class="bg-gray-200 p-4 border-l-4 border-blue-500 mb-6 no-print">
            <form method="get" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div class="mb-2">
                    <label for="start_date" class="block mb-2">Start Date:</label>
                    <input type="date" id="start_date" name="start_date" class="w-full px-3 py-2 border border-gray-300 rounded" value="<?php echo $start_date; ?>">
                </div>
                <div class="mb-2">
                    <label for="end_date" class="block mb-2">End Date:</label>
                    <input type="date" id="end_date" name="end_date" class="w-full px-3 py-2 border border-gray-300 rounded" value="<?php echo $end_date; ?>">
                </div>
                <div class="mb-2">
                    <label for="facility" class="block mb-2">Facility Filter:</label>
                    <select id="facility" name="facility" class="w-full px-3 py-2.5 border border-gray-300 rounded">
                        <?php if ($current_user_role == 'Admin'): ?>
                        <option value="0">All Facilities</option>
                        <?php foreach($facilities as $facility): ?>
                        <option value="<?php echo $facility['facility_id']; ?>" <?php echo ($facility_filter == $facility['facility_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($facility['facility_name']); ?>
                        </option>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <?php foreach($facilities as $facility): ?>
                        <option value="<?php echo $facility['facility_id']; ?>" 
                            <?php echo ($facility['facility_id'] == $user_facility_id) ? 'selected' : 'disabled'; ?>>
                            <?php echo htmlspecialchars($facility['facility_name']) . ($facility['facility_id'] == $user_facility_id ? ' (Your Facility)' : ''); ?>
                        </option>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <div id="facility-help" class="text-xs text-gray-600 mt-1"><?php echo ($current_user_role == 'Admin') ? 'Available for multiple report types' : 'As a manager, you can only view reports for your facility'; ?></div>
                </div>
                <div class="mb-2">
                    <label for="report_type" class="block mb-2">Report Type:</label>
                    <select id="report_type" name="report_type" class="w-full px-3 py-2.5 border border-gray-300 rounded">
                        <option value="volume_by_origin" <?php echo ($report_type == 'volume_by_origin') ? 'selected' : ''; ?>>Volume by Origin</option>
                        <option value="volume_by_destination" <?php echo ($report_type == 'volume_by_destination') ? 'selected' : ''; ?>>Volume by Destination</option>
                        <option value="busiest_routes" <?php echo ($report_type == 'busiest_routes') ? 'selected' : ''; ?>>Busiest Routes</option>
                        <option value="distribution_by_size" <?php echo ($report_type == 'distribution_by_size') ? 'selected' : ''; ?>>Distribution by Size</option>
                        <option value="distribution_by_weight" <?php echo ($report_type == 'distribution_by_weight') ? 'selected' : ''; ?>>Distribution by Weight</option>
                        <option value="top_customers" <?php echo ($report_type == 'top_customers') ? 'selected' : ''; ?>>Top Customers</option>
                    </select>
                </div>
                <div class="mb-2 flex items-end">
                    <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">Generate Report</button>
                </div>
            </form>
        </div>
        
        <h2 class="text-2xl font-semibold mb-4"><?php echo $title; ?></h2>
        
        <!-- Chart Display -->
        <div class="bg-gray-100 p-6 rounded-lg mb-6">
            <div class="chart-container">
                <canvas id="reportChart"></canvas>
            </div>
        </div>
        
        <!-- Table Display -->
        <div class="bg-gray-100 p-6 rounded-lg mb-6">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-200 text-left">
                            <?php if ($report_type == 'volume_by_origin'): ?>
                            <th class="px-4 py-2">Facility</th>
                            <th class="px-4 py-2">City</th>
                            <th class="px-4 py-2">State</th>
                            <th class="px-4 py-2">Type</th>
                            <th class="px-4 py-2">Package Count</th>
                            <?php elseif ($report_type == 'volume_by_destination'): ?>
                            <th class="px-4 py-2">City</th>
                            <th class="px-4 py-2">State</th>
                            <th class="px-4 py-2">Package Count</th>
                            <?php elseif ($report_type == 'busiest_routes'): ?>
                            <th class="px-4 py-2">Origin</th>
                            <th class="px-4 py-2">Destination</th>
                            <th class="px-4 py-2">Package Count</th>
                            <?php elseif ($report_type == 'distribution_by_size' || $report_type == 'distribution_by_weight'): ?>
                            <th class="px-4 py-2">Category</th>
                            <th class="px-4 py-2">Count</th>
                            <th class="px-4 py-2">Percentage</th>
                            <?php elseif ($report_type == 'top_customers'): ?>
                            <th class="px-4 py-2">Customer</th>
                            <th class="px-4 py-2">Package Count</th>
                            <th class="px-4 py-2">Total Weight</th>
                            <th class="px-4 py-2">Avg Postage</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($report_type == 'volume_by_origin'): 
                            foreach($report_data as $row): ?>
                        <tr class="border-b border-gray-200 hover:bg-gray-50">
                            <td class="px-4 py-2"><?php echo $row['facility_id']; ?></td>
                            <td class="px-4 py-2"><?php echo htmlspecialchars($row['city']); ?></td>
                            <td class="px-4 py-2"><?php echo htmlspecialchars($row['state']); ?></td>
                            <td class="px-4 py-2"><?php echo htmlspecialchars($row['type']); ?></td>
                            <td class="px-4 py-2"><?php echo number_format($row['package_count']); ?></td>
                        </tr>
                        <?php endforeach;
                        elseif ($report_type == 'volume_by_destination'):
                            foreach($report_data as $row): ?>
                        <tr class="border-b border-gray-200 hover:bg-gray-50">
                            <td class="px-4 py-2"><?php echo htmlspecialchars($row['city']); ?></td>
                            <td class="px-4 py-2"><?php echo htmlspecialchars($row['state']); ?></td>
                            <td class="px-4 py-2"><?php echo number_format($row['package_count']); ?></td>
                        </tr>
                        <?php endforeach;
                        elseif ($report_type == 'busiest_routes'):
                            foreach($report_data as $row): ?>
                        <tr class="border-b border-gray-200 hover:bg-gray-50">
                            <td class="px-4 py-2"><?php echo htmlspecialchars($row['origin_city']) . ', ' . htmlspecialchars($row['origin_state']); ?></td>
                            <td class="px-4 py-2"><?php echo htmlspecialchars($row['dest_city']) . ', ' . htmlspecialchars($row['dest_state']); ?></td>
                            <td class="px-4 py-2"><?php echo number_format($row['package_count']); ?></td>
                        </tr>
                        <?php endforeach;
                        elseif ($report_type == 'distribution_by_size' || $report_type == 'distribution_by_weight'):
                            $total = array_sum(array_column($report_data, 'count'));
                            foreach($report_data as $row): 
                            $category = $report_type == 'distribution_by_size' ? $row['size'] : $row['weight_range'];
                            $percentage = ($row['count'] / $total) * 100;
                        ?>
                        <tr class="border-b border-gray-200 hover:bg-gray-50">
                            <td class="px-4 py-2"><?php echo htmlspecialchars($category); ?></td>
                            <td class="px-4 py-2"><?php echo number_format($row['count']); ?></td>
                            <td class="px-4 py-2"><?php echo number_format($percentage, 2) . '%'; ?></td>
                        </tr>
                        <?php endforeach;
                        elseif ($report_type == 'top_customers'):
                            foreach($report_data as $row): ?>
                        <tr class="border-b border-gray-200 hover:bg-gray-50">
                            <td class="px-4 py-2"><?php echo htmlspecialchars($row['first_name']) . ' ' . htmlspecialchars($row['last_name']); ?></td>
                            <td class="px-4 py-2"><?php echo number_format($row['package_count']); ?></td>
                            <td class="px-4 py-2"><?php echo number_format($row['total_weight'], 2) . ' lbs'; ?></td>
                            <td class="px-4 py-2">$<?php echo number_format($row['avg_postage'], 2); ?></td>
                        </tr>
                        <?php endforeach;
                        endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Raw Package Data -->
        <div class="bg-gray-100 p-6 rounded-lg mb-6">
            <h2 class="text-xl font-semibold mb-4">Raw Package Data</h2>
            <div class="bg-gray-50 p-3 mb-4">
                <span class="text-sm text-gray-700">Showing <?= count($package_data) ?> of <?= $package_total_count ?> records</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-200 text-left">
                            <th class="px-4 py-2">Tracking Number</th>
                            <th class="px-4 py-2">Created</th>
                            <th class="px-4 py-2">Sender</th>
                            <th class="px-4 py-2">Receiver</th>
                            <th class="px-4 py-2">Facility</th>
                            <th class="px-4 py-2">Weight</th>
                            <th class="px-4 py-2">Size</th>
                            <th class="px-4 py-2">Speed</th>
                            <th class="px-4 py-2">Status</th>
                            <th class="px-4 py-2">Postage</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (count($package_data) > 0): ?>
                        <?php foreach($package_data as $package): ?>
                        <tr class="border-b border-gray-200 hover:bg-gray-50">
                            <td class="px-4 py-2"><?= htmlspecialchars($package['tracking_number']) ?></td>
                            <td class="px-4 py-2"><?= $package['creation_date'] ?></td>
                            <td class="px-4 py-2"><?= htmlspecialchars($package['sender_name']) ?><br><span class="text-xs text-gray-500"><?= htmlspecialchars($package['sender_location']) ?></span></td>
                            <td class="px-4 py-2"><?= htmlspecialchars($package['receiver_name']) ?><br><span class="text-xs text-gray-500"><?= htmlspecialchars($package['receiver_location']) ?></span></td>
                            <td class="px-4 py-2"><?= htmlspecialchars($package['facility_location']) ?><br><span class="text-xs text-gray-500"><?= htmlspecialchars($package['facility_type']) ?></span></td>
                            <td class="px-4 py-2"><?= $package['weight'] ?> lbs</td>
                            <td class="px-4 py-2"><?= htmlspecialchars($package['size']) ?></td>
                            <td class="px-4 py-2"><?= htmlspecialchars($package['shipping_speed']) ?></td>
                            <td class="px-4 py-2"><?= htmlspecialchars($package['status']) ?></td>
                            <td class="px-4 py-2">$<?= number_format($package['postage'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="px-4 py-2 text-center">No package data available for the selected criteria.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination for Package Data -->
            <?php echo renderPagination($package_page, $package_total_pages, 'package_page'); ?>
        </div>
    </div>

    <script>
        // Chart Generation
        window.onload = function() {
            const ctx = document.getElementById('reportChart').getContext('2d');
            
            <?php if ($report_type == 'volume_by_origin'): ?>
                const labels = <?php echo json_encode(array_map(function($row) { return $row['city'] . ', ' . $row['state']; }, $report_data)); ?>;
                const data = <?php echo json_encode(array_column($report_data, 'package_count')); ?>;
                const chartType = 'bar';
                const chartTitle = 'Package Volume by Origin';
            <?php elseif ($report_type == 'volume_by_destination'): ?>
                const labels = <?php echo json_encode(array_map(function($row) { return $row['city'] . ', ' . $row['state']; }, $report_data)); ?>;
                const data = <?php echo json_encode(array_column($report_data, 'package_count')); ?>;
                const chartType = 'bar';
                const chartTitle = 'Package Volume by Destination';
            <?php elseif ($report_type == 'busiest_routes'): ?>
                const labels = <?php echo json_encode(array_map(function($row) { 
                    return $row['origin_city'] . ' to ' . $row['dest_city']; 
                }, $report_data)); ?>;
                const data = <?php echo json_encode(array_column($report_data, 'package_count')); ?>;
                const chartType = 'bar';
                const chartTitle = 'Busiest Routes';
            <?php elseif ($report_type == 'distribution_by_size'): ?>
                const labels = <?php echo json_encode(array_column($report_data, 'size')); ?>;
                const data = <?php echo json_encode(array_column($report_data, 'count')); ?>;
                const chartType = 'pie';
                const chartTitle = 'Package Distribution by Size';
            <?php elseif ($report_type == 'distribution_by_weight'): ?>
                const labels = <?php echo json_encode(array_column($report_data, 'weight_range')); ?>;
                const data = <?php echo json_encode(array_column($report_data, 'count')); ?>;
                const chartType = 'pie';
                const chartTitle = 'Package Distribution by Weight';
            <?php elseif ($report_type == 'top_customers'): ?>
                const labels = <?php echo json_encode(array_map(function($row) { 
                    return $row['first_name'] . ' ' . $row['last_name']; 
                }, $report_data)); ?>;
                const data = <?php echo json_encode(array_column($report_data, 'package_count')); ?>;
                const chartType = 'bar';
                const chartTitle = 'Top Customers by Shipping Volume';
            <?php endif; ?>
            
            let chartConfig = {};
            
            if (chartType === 'pie') {
                chartConfig = {
                    type: chartType,
                    data: {
                        labels: labels,
                        datasets: [{
                            data: data,
                            backgroundColor: [
                                'rgba(59, 130, 246, 0.8)', // blue-500
                                'rgba(16, 185, 129, 0.8)', // green-500
                                'rgba(99, 102, 241, 0.8)', // indigo-500
                                'rgba(245, 158, 11, 0.8)', // amber-500
                                'rgba(239, 68, 68, 0.8)',  // red-500
                                'rgba(107, 114, 128, 0.8)', // gray-500
                                'rgba(75, 85, 99, 0.8)',   // gray-600
                                'rgba(243, 244, 246, 0.8)', // gray-100
                                'rgba(209, 213, 219, 0.8)', // gray-300
                                'rgba(156, 163, 175, 0.8)'  // gray-400
                            ],
                            hoverBackgroundColor: [
                                'rgba(37, 99, 235, 0.8)', // blue-600
                                'rgba(5, 150, 105, 0.8)', // green-600
                                'rgba(79, 70, 229, 0.8)', // indigo-600
                                'rgba(217, 119, 6, 0.8)', // amber-600
                                'rgba(220, 38, 38, 0.8)', // red-600
                                'rgba(75, 85, 99, 0.8)',  // gray-600
                                'rgba(55, 65, 81, 0.8)',  // gray-700
                                'rgba(229, 231, 235, 0.8)', // gray-200
                                'rgba(191, 195, 201, 0.8)', // gray-400 darker
                                'rgba(129, 135, 146, 0.8)'  // gray-500 darker
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: true,
                                text: chartTitle,
                                font: {
                                    size: 18
                                }
                            },
                            legend: {
                                position: 'right',
                                labels: {
                                    usePointStyle: true
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const value = context.raw;
                                        const percentage = Math.round((value / total) * 100);
                                        return `${context.label}: ${value} (${percentage}%)`;
                                    }
                                }
                            }
                        }
                    }
                };
            } else {
                chartConfig = {
                    type: chartType,
                    data: {
                        labels: labels,
                        datasets: [{
                            label: chartTitle,
                            data: data,
                            backgroundColor: chartType === 'line' ? 'rgba(59, 130, 246, 0.1)' : 'rgba(59, 130, 246, 0.7)',
                            borderColor: 'rgba(59, 130, 246, 1)',
                            borderWidth: 1,
                            pointBackgroundColor: 'rgba(59, 130, 246, 1)',
                            fill: chartType === 'line'
                        }]
                    },
                    options: {
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: true,
                                text: chartTitle,
                                font: {
                                    size: 18
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                };
            }
            
            new Chart(ctx, chartConfig);
        };
    </script>
</body>
</html>
