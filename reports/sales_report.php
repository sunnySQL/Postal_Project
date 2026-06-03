<?php
require_once '../db_connect.php';
require_once '../functions.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || 
    ($_SESSION['role'] != 'Employee' && $_SESSION['role'] != 'Admin')) {
    header("Location: ../login.php");
    exit();
}

// Filters
$date_start = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-7 days'));
$date_end = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$filter_item = isset($_GET['item_name']) ? trim($_GET['item_name']) : '';
$facility_filter = isset($_GET['facility']) ? intval($_GET['facility']) : 0; // Added facility filter

// Add pagination parameters
$items_page = isset($_GET['items_page']) ? max(1, intval($_GET['items_page'])) : 1;
$packages_page = isset($_GET['packages_page']) ? max(1, intval($_GET['packages_page'])) : 1;
$per_page = 25; // Number of records per page

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

$conditions = ["ss.sale_date BETWEEN ? AND ?", "f.type = 'Post Office'"];
$params = [$date_start.' 00:00:00', $date_end.' 23:59:59'];
$types = "ss";

// item filter
if (!empty($filter_item)) {
    $conditions[] = "i.name LIKE ?";
    $params[] = "%{$filter_item}%";
    $types .= "s";
}

// facility filter
if ($facility_filter > 0) {
    $conditions[] = "f.facility_id = ?";
    $params[] = $facility_filter;
    $types .= "i";
}

$where_clause = implode(" AND ", $conditions);
require_once 'queries.php';

// Item Sales query
$stmt = $conn->prepare($item_sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$items_report = $stmt->get_result();
$items_data = $items_report->fetch_all(MYSQLI_ASSOC); // Store for chart use
$items_report->data_seek(0); // Reset result pointer

// Facility query
$stmt = $conn->prepare($facility_sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$facility_report = $stmt->get_result();
$facility_data = $facility_report->fetch_all(MYSQLI_ASSOC); // Store for chart use
$facility_report->data_seek(0); // Reset result pointer

// Company query
$stmt = $conn->prepare($company_sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$company_overview = $stmt->get_result()->fetch_assoc();

// Package payment queries
$payment_params = [$date_start.' 00:00:00', $date_end.' 23:59:59'];
$payment_types = "ss";

// Package payment details
$stmt = $conn->prepare($package_payment_sql);
$stmt->bind_param($payment_types, ...$payment_params);
$stmt->execute();
$package_payments = $stmt->get_result();
$package_payment_data = $package_payments->fetch_all(MYSQLI_ASSOC); // Store for chart use
$package_payments->data_seek(0); // Reset result pointer

// Package payment summary
$stmt = $conn->prepare($payment_summary_sql);
$stmt->bind_param($payment_types, ...$payment_params);
$stmt->execute();
$payment_summary = $stmt->get_result();
$payment_summary_data = $payment_summary->fetch_all(MYSQLI_ASSOC); // Store for chart use
$payment_summary->data_seek(0); // Reset result pointer

// Modify the Shop_Sale query to support pagination
$shop_sale_sql = "
    SELECT 
        ss.sale_id,
        ss.transaction_id,
        s.shop_name,
        f.city as facility_city,
        i.name as item_name,
        ss.quantity,
        ss.sale_amount,
        DATE_FORMAT(ss.sale_date, '%Y-%m-%d %H:%i') as formatted_sale_date
    FROM 
        Shop_Sale ss
    JOIN 
        Items i ON ss.item_id = i.item_id
    JOIN 
        Shop s ON ss.shop_id = s.shop_id
    JOIN 
        Facility f ON s.facility_id = f.facility_id
    WHERE 
        ss.sale_date BETWEEN ? AND ?
";

if ($facility_filter > 0) {
    $shop_sale_sql .= " AND f.facility_id = ?";
    $shop_sale_types = "ssi";
    $shop_sale_params = [$date_start.' 00:00:00', $date_end.' 23:59:59', $facility_filter];
} else {
    $shop_sale_types = "ss";
    $shop_sale_params = [$date_start.' 00:00:00', $date_end.' 23:59:59'];
}

if (!empty($filter_item)) {
    $shop_sale_sql .= " AND i.name LIKE ?";
    $shop_sale_types .= "s";
    $shop_sale_params[] = "%{$filter_item}%";
}

// First, get total count for pagination
$shop_sale_count_sql = "SELECT COUNT(*) as total FROM (" . $shop_sale_sql . ") as counted";
$stmt = $conn->prepare($shop_sale_count_sql);
$stmt->bind_param($shop_sale_types, ...$shop_sale_params);
$stmt->execute();
$shop_sale_count = $stmt->get_result()->fetch_assoc()['total'];
$shop_sale_total_pages = ceil($shop_sale_count / $per_page);
$items_page = min($items_page, max(1, $shop_sale_total_pages)); // Ensure valid page number

// Then, get the data for current page
$shop_sale_sql .= " ORDER BY ss.sale_date DESC LIMIT " . (($items_page - 1) * $per_page) . ", " . $per_page;
$stmt = $conn->prepare($shop_sale_sql);
$stmt->bind_param($shop_sale_types, ...$shop_sale_params);
$stmt->execute();
$shop_sale_result = $stmt->get_result();
$shop_sale_data = $shop_sale_result->fetch_all(MYSQLI_ASSOC);

// Same for Package Payment - modify for pagination
$raw_payment_sql = "
    SELECT 
        pp.payment_id, 
        pp.package_id as tracking_number, 
        pp.amount, 
        pp.payment_method,
        pp.transaction_status,
        pp.invoice_number,
        DATE_FORMAT(pp.payment_date, '%Y-%m-%d %H:%i') as formatted_payment_date,
        CONCAT(c.first_name, ' ', c.last_name) as customer_name,
        f.city as facility_city
    FROM 
        Package_Payment pp
    LEFT JOIN 
        Customer c ON pp.user_id = c.user_id
    LEFT JOIN
        Facility f ON pp.facility_id = f.facility_id
    WHERE 
        pp.payment_date BETWEEN ? AND ?
";

if ($facility_filter > 0) {
    $raw_payment_sql .= " AND pp.facility_id = ?";
    $raw_payment_types = "ssi";
    $raw_payment_params = [$date_start.' 00:00:00', $date_end.' 23:59:59', $facility_filter];
} else {
    $raw_payment_types = "ss";
    $raw_payment_params = [$date_start.' 00:00:00', $date_end.' 23:59:59'];
}

// First, get total count for pagination
$raw_payment_count_sql = "SELECT COUNT(*) as total FROM (" . $raw_payment_sql . ") as counted";
$stmt = $conn->prepare($raw_payment_count_sql);
$stmt->bind_param($raw_payment_types, ...$raw_payment_params);
$stmt->execute();
$raw_payment_count = $stmt->get_result()->fetch_assoc()['total'];
$raw_payment_total_pages = ceil($raw_payment_count / $per_page);
$packages_page = min($packages_page, max(1, $raw_payment_total_pages)); // Ensure valid page number

// Then, get the data for current page
$raw_payment_sql .= " ORDER BY pp.payment_date DESC LIMIT " . (($packages_page - 1) * $per_page) . ", " . $per_page;
$stmt = $conn->prepare($raw_payment_sql);
$stmt->bind_param($raw_payment_types, ...$raw_payment_params);
$stmt->execute();
$raw_payment_result = $stmt->get_result();
$raw_payment_data = $raw_payment_result->fetch_all(MYSQLI_ASSOC);

// Calculate package payment totals
$total_package_revenue = 0;
$total_package_cost = 0;
$total_package_profit = 0;
$payment_methods = [];
$payment_statuses = [];

foreach ($payment_summary_data as $row) {
    $amount = $row['total_amount'];
    $total_package_revenue += $amount;
    
    // Calculate cost based on postage 50% with +/- 10% variance
    $package_id = $row['package_id'];
    $seed_value = crc32($package_id);
    $variance = (($seed_value % 21) - 10) / 100;
    
    $base_cost = $amount * 0.5;
    $cost = $base_cost * (1 + $variance);
    $profit = $amount - $cost;
    
    $total_package_cost += $cost;
    $total_package_profit += $profit;
    
    $method = $row['payment_method'];
    $status = $row['transaction_status'];
    
    if (!isset($payment_methods[$method])) {
        $payment_methods[$method] = 0;
    }
    $payment_methods[$method] += $row['total_amount'];
    
    if (!isset($payment_statuses[$status])) {
        $payment_statuses[$status] = 0;
    }
    $payment_statuses[$status] += $row['total_amount'];
}

// Add pagination function for use in both tables
function renderPagination($current_page, $total_pages, $page_param_name, $base_url = '') {
    if ($total_pages <= 1) return '';
    
    // Build the base URL with existing filters but without the page parameter
    if (empty($base_url)) {
        $base_url = '?';
        foreach ($_GET as $key => $value) {
            if ($key != $page_param_name && $key != 'items_page' && $key != 'packages_page') {
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Revenue Report</title>
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
            <h1 class="text-3xl font-bold section-title">Sales Revenue Report</h1>
            <a href="index.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded flex items-center no-print">
                <i class="fas fa-arrow-left mr-2"></i> Back to Reports
            </a>
        </div>
        
        <!-- Filter Section -->
        <div class="bg-gray-200 p-4 border-l-4 border-blue-500 mb-6 no-print">
            <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div class="mb-2">
                    <label for="start_date" class="block mb-2">Start Date:</label>
                    <input type="date" id="start_date" name="start_date" class="w-full px-3 py-2 border border-gray-300 rounded" value="<?php echo $date_start; ?>">
                </div>
                <div class="mb-2">
                    <label for="end_date" class="block mb-2">End Date:</label>
                    <input type="date" id="end_date" name="end_date" class="w-full px-3 py-2 border border-gray-300 rounded" value="<?php echo $date_end; ?>">
                </div>
                <div class="mb-2">
                    <label for="facility" class="block mb-2">Facility:</label>
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
                    <div id="facility-help" class="text-xs text-gray-600 mt-1"><?php echo ($current_user_role == 'Admin') ? 'Filter by specific facility' : 'As a manager, you can only view reports for your facility'; ?></div>
                </div>
                <div class="mb-2">
                    <label for="item_name" class="block mb-2">Item Name:</label>
                    <input type="text" id="item_name" name="item_name" class="w-full px-3 py-2 border border-gray-300 rounded" value="<?php echo htmlspecialchars($filter_item); ?>">
                </div>
                <div class="mb-2 flex items-end">
                    <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">Filter</button>
                </div>
            </form>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <?php if ($facility_filter > 0): 
                $selected_facility = null;
                foreach ($facilities as $facility) {
                    if ($facility['facility_id'] == $facility_filter) {
                        $selected_facility = $facility['facility_name'];
                        break;
                    }
                }
            ?>
            <div class="col-span-full mb-3">
                <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4">
                    <strong>Filtering by Facility:</strong> <?= htmlspecialchars($selected_facility) ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="bg-white rounded-lg shadow p-6 border-l-4 border-blue-500">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="text-sm font-semibold text-blue-700 uppercase mb-1">Total Item Revenue</p>
                        <p class="text-2xl font-bold text-gray-800">
                            $<?php echo $company_overview && isset($company_overview['total_revenue']) ? number_format((float)$company_overview['total_revenue'], 2) : '0.00'; ?>
                        </p>
                    </div>
                    <div class="text-blue-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6 border-l-4 border-green-500">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="text-sm font-semibold text-green-700 uppercase mb-1">Total Package Revenue</p>
                        <p class="text-2xl font-bold text-gray-800">
                            $<?php echo number_format($total_package_revenue, 2); ?>
                        </p>
                    </div>
                    <div class="text-green-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6 border-l-4 border-indigo-500">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="text-sm font-semibold text-indigo-700 uppercase mb-1">Combined Revenue</p>
                        <p class="text-2xl font-bold text-gray-800">
                            $<?php echo number_format(($company_overview['total_revenue'] ?? 0) + $total_package_revenue, 2); ?>
                        </p>
                    </div>
                    <div class="text-indigo-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Report Tabs -->
        <div class="mb-6 border-b border-gray-200" id="reportTabs">
            <ul class="flex flex-wrap -mb-px text-sm font-medium text-center">
                <li class="mr-2">
                    <a class="inline-block p-4 border-b-2 border-blue-500 text-blue-500" id="items-tab" 
                       onclick="switchTab('items')">Item Sales</a>
                </li>
                <li class="mr-2">
                    <a class="inline-block p-4 border-b-2 border-transparent hover:border-gray-300 text-gray-500 hover:text-gray-600" 
                       id="packages-tab" onclick="switchTab('packages')">Package Payments</a>
                </li>
                <li class="mr-2">
                    <a class="inline-block p-4 border-b-2 border-transparent hover:border-gray-300 text-gray-500 hover:text-gray-600" 
                       id="facilities-tab" onclick="switchTab('facilities')">Facilities</a>
                </li>
                <li class="mr-2">
                    <a class="inline-block p-4 border-b-2 border-transparent hover:border-gray-300 text-gray-500 hover:text-gray-600" 
                       id="overview-tab" onclick="switchTab('overview')">Overview</a>
                </li>
            </ul>
        </div>
        
        <!-- Tab Content -->
        <div id="reportTabContent">
            <!-- Item Sales Tab -->
            <div class="block" id="items" role="tabpanel">
                <div class="bg-gray-100 p-6 rounded-lg mb-6">
                    <h3 class="text-xl font-semibold mb-4">Sales Breakdown by Item</h3>
                    <?php if ($items_report->num_rows > 0): ?>
                        <div class="chart-container">
                            <canvas id="itemSalesChart"></canvas>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="bg-gray-200 text-left">
                                        <th class="px-4 py-2">Item Name</th>
                                        <th class="px-4 py-2">Quantity</th>
                                        <th class="px-4 py-2">Unit Price</th>
                                        <th class="px-4 py-2">Total Revenue</th>
                                        <th class="px-4 py-2">Cost Price</th>
                                        <th class="px-4 py-2">Profit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php while($row = $items_report->fetch_assoc()): ?>
                                    <tr class="border-b border-gray-200 hover:bg-gray-50">
                                        <td class="px-4 py-2"><?= htmlspecialchars($row['item_name']) ?></td>
                                        <td class="px-4 py-2"><?= number_format($row['total_quantity']) ?></td>
                                        <td class="px-4 py-2">$<?= number_format($row['sale_price'], 2)?></td>
                                        <td class="px-4 py-2">$<?= number_format($row['total_revenue'], 2) ?></td>
                                        <td class="px-4 py-2">$<?= number_format($row['price_wholesale'], 2) ?></td>
                                        <td class="px-4 py-2">$<?= number_format($row['total_profit'], 2) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Raw Shop_Sale Records -->
                        <h3 class="text-xl font-semibold mb-4 mt-8">Data</h3>
                        <div class="bg-gray-50 p-3 mb-4">
                            <span class="text-sm text-gray-700">Showing <?= count($shop_sale_data) ?> of <?= $shop_sale_count ?> records</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="bg-gray-200 text-left">
                                        <th class="px-4 py-2">Sale ID</th>
                                        <th class="px-4 py-2">Transaction</th>
                                        <th class="px-4 py-2">Shop</th>
                                        <th class="px-4 py-2">Facility</th>
                                        <th class="px-4 py-2">Item</th>
                                        <th class="px-4 py-2">Quantity</th>
                                        <th class="px-4 py-2">Amount</th>
                                        <th class="px-4 py-2">Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (count($shop_sale_data) > 0): ?>
                                    <?php foreach($shop_sale_data as $sale): ?>
                                    <tr class="border-b border-gray-200 hover:bg-gray-50">
                                        <td class="px-4 py-2"><?= $sale['sale_id'] ?></td>
                                        <td class="px-4 py-2"><?= $sale['transaction_id'] ?></td>
                                        <td class="px-4 py-2"><?= htmlspecialchars($sale['shop_name']) ?></td>
                                        <td class="px-4 py-2"><?= htmlspecialchars($sale['facility_city']) ?></td>
                                        <td class="px-4 py-2"><?= htmlspecialchars($sale['item_name']) ?></td>
                                        <td class="px-4 py-2"><?= $sale['quantity'] ?></td>
                                        <td class="px-4 py-2">$<?= number_format($sale['sale_amount'], 2) ?></td>
                                        <td class="px-4 py-2"><?= $sale['formatted_sale_date'] ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="px-4 py-2 text-center">No raw sales data available.</td>
                                    </tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination for Shop_Sale Records -->
                        <?php echo renderPagination($items_page, $shop_sale_total_pages, 'items_page'); ?>
                    <?php else: ?>
                        <p class="text-gray-600">No item sales data found for the selected criteria.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Package Payments Tab -->
            <div class="hidden" id="packages" role="tabpanel">
                <?php if ($payment_summary_data && count($payment_summary_data) > 0): ?>
                <!-- Package Profit Summary -->
                <div class="bg-gray-100 p-6 rounded-lg mb-6">
                    <h3 class="text-xl font-semibold mb-4">Package Profit Summary</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div class="text-center p-4 bg-white rounded-lg shadow">
                            <h4 class="text-lg font-semibold mb-2">Total Revenue</h4>
                            <p class="text-3xl text-blue-600 font-bold">$<?= number_format($total_package_revenue, 2) ?></p>
                        </div>
                        <div class="text-center p-4 bg-white rounded-lg shadow">
                            <h4 class="text-lg font-semibold mb-2">Total Cost</h4>
                            <p class="text-3xl text-red-600 font-bold">$<?= number_format($total_package_cost, 2) ?></p>
                            <p class="text-sm text-gray-600">50% of revenue with consistent ±10% variance</p>
                        </div>
                        <div class="text-center p-4 bg-white rounded-lg shadow">
                            <h4 class="text-lg font-semibold mb-2">Total Profit</h4>
                            <p class="text-3xl text-green-600 font-bold">$<?= number_format($total_package_profit, 2) ?></p>
                            <p class="text-sm text-gray-600">Profit Margin: <?= number_format(($total_package_profit / $total_package_revenue) * 100, 1) ?>%</p>
                        </div>
                    </div>
                </div>

                <!-- Raw Package Payment Records -->
                <div class="bg-gray-100 p-6 rounded-lg mb-6">
                    <h3 class="text-xl font-semibold mb-4">Data</h3>
                    <div class="bg-gray-50 p-3 mb-4">
                        <span class="text-sm text-gray-700">Showing <?= count($raw_payment_data) ?> of <?= $raw_payment_count ?> records</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-gray-200 text-left">
                                    <th class="px-4 py-2">Payment ID</th>
                                    <th class="px-4 py-2">Tracking #</th>
                                    <th class="px-4 py-2">Customer</th>
                                    <th class="px-4 py-2">Facility</th>
                                    <th class="px-4 py-2">Method</th>
                                    <th class="px-4 py-2">Status</th>
                                    <th class="px-4 py-2">Amount</th>
                                    <th class="px-4 py-2">Invoice #</th>
                                    <th class="px-4 py-2">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (count($raw_payment_data) > 0): ?>
                                <?php foreach($raw_payment_data as $payment): ?>
                                <tr class="border-b border-gray-200 hover:bg-gray-50">
                                    <td class="px-4 py-2"><?= $payment['payment_id'] ?></td>
                                    <td class="px-4 py-2"><?= htmlspecialchars($payment['tracking_number']) ?></td>
                                    <td class="px-4 py-2"><?= htmlspecialchars($payment['customer_name']) ?></td>
                                    <td class="px-4 py-2"><?= htmlspecialchars($payment['facility_city']) ?></td>
                                    <td class="px-4 py-2"><?= htmlspecialchars($payment['payment_method']) ?></td>
                                    <td class="px-4 py-2"><?= htmlspecialchars($payment['transaction_status']) ?></td>
                                    <td class="px-4 py-2">$<?= number_format($payment['amount'], 2) ?></td>
                                    <td class="px-4 py-2"><?= htmlspecialchars($payment['invoice_number']) ?></td>
                                    <td class="px-4 py-2"><?= $payment['formatted_payment_date'] ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="px-4 py-2 text-center">No raw payment data available.</td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination for Package Payment Records -->
                    <?php echo renderPagination($packages_page, $raw_payment_total_pages, 'packages_page'); ?>
                </div>
                <?php else: ?>
                <div class="bg-gray-100 p-6 rounded-lg mb-6">
                    <h3 class="text-xl font-semibold mb-4">Package Payments</h3>
                    <p class="text-gray-600">No package payment data available for the selected criteria.</p>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Facilities Tab -->
            <div class="hidden" id="facilities" role="tabpanel">
                <div class="bg-gray-100 p-6 rounded-lg mb-6">
                    <h3 class="text-xl font-semibold mb-4">Facility-Level Profit Summary</h3>
                    <?php if ($facility_report->num_rows > 0): ?>
                        <div class="chart-container">
                            <canvas id="facilityProfitChart"></canvas>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="bg-gray-200 text-left">
                                        <th class="px-4 py-2">Facility (City)</th>
                                        <th class="px-4 py-2">Type</th>
                                        <th class="px-4 py-2">Total Revenue</th>
                                        <th class="px-4 py-2">Total Cost</th>
                                        <th class="px-4 py-2">Net Profit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php while ($row = $facility_report->fetch_assoc()): ?>
                                    <tr class="border-b border-gray-200 hover:bg-gray-50">
                                        <td class="px-4 py-2"><?= htmlspecialchars($row['city']) ?></td>
                                        <td class="px-4 py-2"><?= htmlspecialchars($row['type']) ?></td>
                                        <td class="px-4 py-2">$<?= number_format($row['total_revenue'], 2) ?></td>
                                        <td class="px-4 py-2">$<?= number_format($row['total_cost'], 2) ?></td>
                                        <td class="px-4 py-2">$<?= number_format($row['net_profit'], 2) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-600">No facility summary data found for the selected criteria.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Overview Tab -->
            <div class="hidden" id="overview" role="tabpanel">
                <div class="bg-gray-100 p-6 rounded-lg mb-6">
                    <h3 class="text-xl font-semibold mb-4">Company-Wide Overview</h3>
                    <?php if ($company_overview && (($company_overview['total_revenue'] ?? 0) > 0 || $total_package_revenue > 0)): ?>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                            <div class="text-center p-4 bg-white rounded-lg shadow">
                                <h4 class="text-lg font-semibold mb-2">Total Revenue</h4>
                                <p class="text-3xl text-blue-600 font-bold">$<?= number_format(($company_overview['total_revenue'] ?? 0) + $total_package_revenue, 2) ?></p>
                                <p class="text-sm text-gray-600">Items: $<?= number_format($company_overview['total_revenue'] ?? 0, 2) ?> + Packages: $<?= number_format($total_package_revenue, 2) ?></p>
                            </div>
                            <div class="text-center p-4 bg-white rounded-lg shadow">
                                <h4 class="text-lg font-semibold mb-2">Total Cost</h4>
                                <p class="text-3xl text-red-600 font-bold">$<?= number_format(($company_overview['total_cost'] ?? 0) + $total_package_cost, 2) ?></p>
                                <p class="text-sm text-gray-600">Items: $<?= number_format($company_overview['total_cost'] ?? 0, 2) ?> + Packages: $<?= number_format($total_package_cost, 2) ?></p>
                            </div>
                            <div class="text-center p-4 bg-white rounded-lg shadow">
                                <h4 class="text-lg font-semibold mb-2">Total Profit</h4>
                                <p class="text-3xl text-green-600 font-bold">$<?= number_format(($company_overview['total_profit'] ?? 0) + $total_package_profit, 2) ?></p>
                                <p class="text-sm text-gray-600">Items: $<?= number_format($company_overview['total_profit'] ?? 0, 2) ?> + Packages: $<?= number_format($total_package_profit, 2) ?></p>
                            </div>
                        </div>
                        <div class="chart-container mt-4">
                            <canvas id="profitOverviewChart"></canvas>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-600">No sales data found for the selected criteria.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tab switching
        function switchTab(tabId) {
            // Hide all tab contents
            document.querySelectorAll('#reportTabContent > div').forEach(tab => {
                tab.classList.add('hidden');
                tab.classList.remove('block');
            });
            
            // Show the selected tab
            const selectedTab = document.getElementById(tabId);
            if (selectedTab) {
                selectedTab.classList.remove('hidden');
                selectedTab.classList.add('block');
                
                // Update tab styling
                document.querySelectorAll('#reportTabs a').forEach(tab => {
                    tab.classList.remove('border-blue-500', 'text-blue-500');
                    tab.classList.add('border-transparent', 'text-gray-500', 'hover:border-gray-300', 'hover:text-gray-600');
                });
                
                const tabButton = document.getElementById(tabId + '-tab');
                if (tabButton) {
                    tabButton.classList.remove('border-transparent', 'text-gray-500', 'hover:border-gray-300', 'hover:text-gray-600');
                    tabButton.classList.add('border-blue-500', 'text-blue-500');
                }
            }
        }

        // Make sure all tabs are properly initialized when the page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Ensure all tab buttons are clickable
            document.querySelectorAll('#reportTabs a').forEach(tab => {
                tab.addEventListener('click', function(e) {
                    e.preventDefault();
                    const tabId = this.id.replace('-tab', '');
                    switchTab(tabId);
                });
            });
        });

        // Chart Generation
        window.onload = function() {
            // Company Overview Chart
            <?php if ($company_overview): ?>
            const overviewCtx = document.getElementById('profitOverviewChart');
            if (overviewCtx) {
                const overviewChart = new Chart(overviewCtx.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: ['Item Revenue', 'Package Revenue', 'Item Cost', 'Package Cost'],
                        datasets: [{
                            data: [
                                <?= $company_overview['total_revenue'] ?>,
                                <?= $total_package_revenue ?>,
                                <?= $company_overview['total_cost'] ?>,
                                <?= $total_package_cost ?>
                            ],
                            backgroundColor: [
                                'rgba(54, 162, 235, 0.8)',
                                'rgba(75, 192, 192, 0.8)',
                                'rgba(255, 99, 132, 0.8)',
                                'rgba(255, 159, 64, 0.8)'
                            ],
                            borderColor: [
                                'rgba(54, 162, 235, 1)',
                                'rgba(75, 192, 192, 1)',
                                'rgba(255, 99, 132, 1)',
                                'rgba(255, 159, 64, 1)'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.label || '';
                                        let value = context.raw || 0;
                                        return label + ': $' + value.toFixed(2);
                                    }
                                }
                            },
                            title: {
                                display: true,
                                text: 'Revenue and Cost Breakdown'
                            }
                        }
                    }
                });
            }
            <?php endif; ?>

            // Item Sales Chart
            const itemsCtx = document.getElementById('itemSalesChart');
            if (itemsCtx) {
                const itemsLabels = <?= json_encode(array_column($items_data, 'item_name')) ?>;
                const itemsRevenue = <?= json_encode(array_map(function($row) { return $row['total_revenue']; }, $items_data)) ?>;
                const itemsProfit = <?= json_encode(array_map(function($row) { return $row['total_profit']; }, $items_data)) ?>;
                
                const itemsChart = new Chart(itemsCtx.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: itemsLabels,
                        datasets: [
                            {
                                label: 'Revenue',
                                data: itemsRevenue,
                                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                                borderColor: 'rgba(54, 162, 235, 1)',
                                borderWidth: 1
                            },
                            {
                                label: 'Profit',
                                data: itemsProfit,
                                backgroundColor: 'rgba(75, 192, 192, 0.5)',
                                borderColor: 'rgba(75, 192, 192, 1)',
                                borderWidth: 1
                            }
                        ]
                    },
                    options: {
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return '$' + value;
                                    }
                                }
                            }
                        },
                        plugins: {
                            title: {
                                display: true,
                                text: 'Revenue and Profit by Item'
                            }
                        }
                    }
                });
            }

            // Facility Profit Chart
            const facilityCtx = document.getElementById('facilityProfitChart');
            if (facilityCtx) {
                const facilityCities = <?= json_encode(array_column($facility_data, 'city')) ?>;
                const facilityRevenue = <?= json_encode(array_map(function($row) { return $row['total_revenue']; }, $facility_data)) ?>;
                const facilityCost = <?= json_encode(array_map(function($row) { return $row['total_cost']; }, $facility_data)) ?>;
                const facilityProfit = <?= json_encode(array_map(function($row) { return $row['net_profit']; }, $facility_data)) ?>;
                
                const facilityChart = new Chart(facilityCtx.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: facilityCities,
                        datasets: [
                            {
                                label: 'Revenue',
                                data: facilityRevenue,
                                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                                borderColor: 'rgba(54, 162, 235, 1)',
                                borderWidth: 1
                            },
                            {
                                label: 'Cost',
                                data: facilityCost,
                                backgroundColor: 'rgba(255, 99, 132, 0.5)',
                                borderColor: 'rgba(255, 99, 132, 1)',
                                borderWidth: 1
                            },
                            {
                                label: 'Profit',
                                data: facilityProfit,
                                backgroundColor: 'rgba(75, 192, 192, 0.5)',
                                borderColor: 'rgba(75, 192, 192, 1)',
                                borderWidth: 1
                            }
                        ]
                    },
                    options: {
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return '$' + value;
                                    }
                                }
                            }
                        },
                        plugins: {
                            title: {
                                display: true,
                                text: 'Financial Performance by Facility'
                            }
                        }
                    }
                });
            }
        };
    </script>
</body>
</html>
