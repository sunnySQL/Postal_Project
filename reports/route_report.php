<?php
require_once '../db_connect.php';
require_once '../functions.php';

//devbug enable
// Set default filter values
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$driver_filter = isset($_GET['driver']) ? intval($_GET['driver']) : 0;
$destination_filter = isset($_GET['destination']) ? intval($_GET['destination']) : 0;
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'depart_time';
$order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

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

// For managers (not admins), filter routes by their facility
if ($current_user_role != 'Admin' && $user_facility_id > 0) {
    $destination_filter = $user_facility_id;
}

// Build WHERE clause based on filters
$where_conditions = [];
$where_conditions[] = "t.trip_type = 'Delivery'"; // Only show delivery routes
if ($driver_filter > 0) {
    $where_conditions[] = "t.employee_id = $driver_filter";
}
if ($destination_filter > 0) {
    $where_conditions[] = "t.arrive_facility_id = $destination_filter";
}

// Create the full WHERE clause
$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = ' AND ' . implode(' AND ', $where_conditions);
}

// Build ORDER BY clause
$order_clause = "$sort_by $order";

// Get route data
$route_query = "SELECT 
    t.trip_id,
    t.trip_type,
    t.depart_time,
    t.arrival_time,
    TIMESTAMPDIFF(MINUTE, t.depart_time, t.arrival_time) AS route_time_minutes,
    CONCAT(e.first_name, ' ', e.last_name) AS driver_name,
    df.city AS origin_city,
    af.city AS destination_city,
    COUNT(tp.tracking_number) AS package_count,
    SUM(CASE WHEN latest_status.status = 'Lost' THEN 1 ELSE 0 END) AS packages_lost,
    SUM(CASE WHEN latest_status.status = 'Delivered' THEN 1 ELSE 0 END) AS packages_delivered
FROM 
    Trip t
LEFT JOIN 
    Employee e ON t.employee_id = e.user_id
LEFT JOIN 
    Facility df ON t.depart_facility_id = df.facility_id
LEFT JOIN 
    Facility af ON t.arrive_facility_id = af.facility_id
LEFT JOIN 
    Trip_Package tp ON t.trip_id = tp.trip_id
LEFT JOIN (
    SELECT th1.tracking_number, th1.status
    FROM Tracking_History th1
    INNER JOIN (
        SELECT tracking_number, MAX(history_id) as latest_id
        FROM Tracking_History
        GROUP BY tracking_number
    ) th2 ON th1.tracking_number = th2.tracking_number AND th1.history_id = th2.latest_id
) latest_status ON tp.tracking_number = latest_status.tracking_number
WHERE 
    t.depart_time BETWEEN ? AND ?
    $where_clause
GROUP BY 
    t.trip_id
ORDER BY 
    $order_clause";

$stmt = $conn->prepare($route_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$routes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get statistics by route type
$stats_query = "SELECT 
    t.trip_type,
    COUNT(DISTINCT t.trip_id) AS route_count,
    AVG(TIMESTAMPDIFF(MINUTE, t.depart_time, t.arrival_time)) AS avg_route_time,
    AVG(subquery.package_count) AS avg_packages_per_route,
    SUM(subquery.packages_lost) AS total_packages_lost,
    SUM(subquery.packages_delivered) AS total_packages_delivered,
    CASE WHEN SUM(subquery.packages_lost) + SUM(subquery.packages_delivered) > 0 
         THEN (SUM(subquery.packages_lost) / (SUM(subquery.packages_lost) + SUM(subquery.packages_delivered))) * 100 
         ELSE 0 
    END AS percentage_lost
FROM 
    Trip t
JOIN (
    SELECT 
        t.trip_id,
        COUNT(tp.tracking_number) AS package_count,
        SUM(CASE WHEN latest_status.status = 'Lost' THEN 1 ELSE 0 END) AS packages_lost,
        SUM(CASE WHEN latest_status.status = 'Delivered' THEN 1 ELSE 0 END) AS packages_delivered
    FROM 
        Trip t
    LEFT JOIN 
        Trip_Package tp ON t.trip_id = tp.trip_id
    LEFT JOIN (
        SELECT th1.tracking_number, th1.status
        FROM Tracking_History th1
        INNER JOIN (
            SELECT tracking_number, MAX(history_id) as latest_id
            FROM Tracking_History
            GROUP BY tracking_number
        ) th2 ON th1.tracking_number = th2.tracking_number AND th1.history_id = th2.latest_id
    ) latest_status ON tp.tracking_number = latest_status.tracking_number
    WHERE 
        t.depart_time BETWEEN ? AND ?
        $where_clause
    GROUP BY 
        t.trip_id
) subquery ON t.trip_id = subquery.trip_id
GROUP BY 
    t.trip_type";

$stmt = $conn->prepare($stats_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$route_stats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get list of drivers for filter dropdown
$drivers_query = "SELECT e.user_id, CONCAT(e.first_name, ' ', e.last_name) as driver_name 
                 FROM Employee e 
                 WHERE e.role IN ('Driver', 'Pilot')
                 ORDER BY e.last_name, e.first_name";
$drivers = $conn->query($drivers_query)->fetch_all(MYSQLI_ASSOC);

// Get list of facilities for destination filter dropdown
$facilities_query = "SELECT facility_id, CONCAT(city, ', ', state, ' (', type, ')') as facility_name 
                    FROM Facility 
                    ORDER BY state, city";
$facilities = $conn->query($facilities_query)->fetch_all(MYSQLI_ASSOC);

// Calculate package data for package histogram
$package_histogram_data = [];
foreach ($routes as $route) {
    $package_count = intval($route['package_count']);
    $bin = floor($package_count / 5) * 5; // Group by 5s (0-4, 5-9, 10-14, etc.)
    if (!isset($package_histogram_data[$bin])) {
        $package_histogram_data[$bin] = 0;
    }
    $package_histogram_data[$bin]++;
}
ksort($package_histogram_data);

// Prepare data for charts
$chart_labels = [];
$route_counts = [];
$packages_lost = [];
$packages_delivered = [];
$routes_by_date = [];

// Data for pie chart
$total_lost = 0;
$total_delivered = 0;

foreach ($route_stats as $stat) {
    $chart_labels[] = $stat['trip_type'];
    $route_counts[] = $stat['route_count'];
    $packages_lost[] = $stat['total_packages_lost'];
    $packages_delivered[] = $stat['total_packages_delivered'];
    
    $total_lost += $stat['total_packages_lost'];
    $total_delivered += $stat['total_packages_delivered'];
}

// Data for line chart - Average Route Duration over time
$route_duration_query = "SELECT 
                            DATE(t.depart_time) as route_date,
                            AVG(TIMESTAMPDIFF(MINUTE, t.depart_time, t.arrival_time)) as avg_duration_minutes
                         FROM 
                            Trip t
                         WHERE 
                            t.depart_time BETWEEN ? AND ?
                            AND t.arrival_time IS NOT NULL 
                            AND t.arrival_time > t.depart_time -- Ensure valid duration
                            $where_clause
                         GROUP BY 
                            DATE(t.depart_time)
                         ORDER BY 
                            route_date";

$stmt = $conn->prepare($route_duration_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$route_durations_by_date = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$date_labels = [];
$avg_durations = [];

foreach ($route_durations_by_date as $date_data) {
    $date_labels[] = $date_data['route_date'];
    $avg_durations[] = round($date_data['avg_duration_minutes']); // Round to nearest minute
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Route Performance Report</title>
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
            <h1 class="text-3xl font-bold section-title">Delivery Route Performance Report</h1>
            <a href="index.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded flex items-center no-print">
                <i class="fas fa-arrow-left mr-2"></i> Back to Reports
            </a>
        </div>
        
        <!-- Filter Section -->
        <div class="bg-gray-200 p-4 border-l-4 border-blue-500 mb-6 no-print">
            <form method="get" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">
                <div class="mb-2">
                    <label for="start_date" class="block mb-2">Start Date:</label>
                    <input type="date" id="start_date" name="start_date" class="w-full px-3 py-2 border border-gray-300 rounded" value="<?php echo $start_date; ?>">
                </div>
                <div class="mb-2">
                    <label for="end_date" class="block mb-2">End Date:</label>
                    <input type="date" id="end_date" name="end_date" class="w-full px-3 py-2 border border-gray-300 rounded" value="<?php echo $end_date; ?>">
                </div>
                <div class="mb-2">
                    <label for="driver" class="block mb-2">Driver:</label>
                    <select id="driver" name="driver" class="w-full px-3 py-2.5 border border-gray-300 rounded">
                        <option value="0">All Drivers</option>
                        <?php foreach($drivers as $driver): ?>
                        <option value="<?php echo $driver['user_id']; ?>" <?php echo ($driver_filter == $driver['user_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($driver['driver_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-2">
                    <label for="destination" class="block mb-2">Destination:</label>
                    <select id="destination" name="destination" class="w-full px-3 py-2.5 border border-gray-300 rounded">
                        <?php if ($current_user_role == 'Admin'): ?>
                        <option value="0">All Destinations</option>
                        <?php foreach($facilities as $facility): ?>
                        <option value="<?php echo $facility['facility_id']; ?>" <?php echo ($destination_filter == $facility['facility_id']) ? 'selected' : ''; ?>>
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
                    <?php if ($current_user_role != 'Admin'): ?>
                    <div class="text-xs text-gray-600 mt-1">As a manager, you can only view reports for your facility</div>
                    <?php endif; ?>
                </div>
                <div class="mb-2">
                    <label for="sort_by" class="block mb-2">Sort By:</label>
                    <select id="sort_by" name="sort_by" class="w-full px-3 py-2.5 border border-gray-300 rounded">
                        <option value="depart_time" <?php echo ($sort_by == 'depart_time') ? 'selected' : ''; ?>>Departure Time</option>
                        <option value="route_time_minutes" <?php echo ($sort_by == 'route_time_minutes') ? 'selected' : ''; ?>>Route Duration</option>
                        <option value="package_count" <?php echo ($sort_by == 'package_count') ? 'selected' : ''; ?>>Package Count</option>
                        <option value="packages_lost" <?php echo ($sort_by == 'packages_lost') ? 'selected' : ''; ?>>Packages Lost</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label for="order" class="block mb-2">Order:</label>
                    <select id="order" name="order" class="w-full px-3 py-2.5 border border-gray-300 rounded">
                        <option value="DESC" <?php echo ($order == 'DESC') ? 'selected' : ''; ?>>Descending</option>
                        <option value="ASC" <?php echo ($order == 'ASC') ? 'selected' : ''; ?>>Ascending</option>
                    </select>
                </div>
                <div class="mb-2 col-span-full">
                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">Generate Report</button>
                    <button type="button" onclick="window.print()" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded ml-2">Print Report</button>
                </div>
            </form>
        </div>
        
        <!-- Summary Statistics -->
        <div class="bg-gray-100 p-6 rounded-lg mb-6">
            <h2 class="text-2xl font-semibold mb-4">Summary Statistics</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($route_stats as $stat): ?>
                <div class="stats-card">
                    <h3 class="text-xl font-semibold mb-2"><?php echo htmlspecialchars($stat['trip_type']); ?> Routes</h3>
                    <div class="grid grid-cols-2 gap-y-2">
                        <div class="text-gray-600">Total Routes:</div>
                        <div class="font-semibold"><?php echo number_format($stat['route_count']); ?></div>
                        
                        <div class="text-gray-600">Avg Route Time:</div>
                        <div class="font-semibold"><?php echo round($stat['avg_route_time']); ?> minutes</div>
                        
                        <div class="text-gray-600">Avg Packages/Route:</div>
                        <div class="font-semibold"><?php echo round($stat['avg_packages_per_route'], 1); ?></div>
                        
                        <div class="text-gray-600">Total Packages Lost:</div>
                        <div class="font-semibold"><?php echo number_format($stat['total_packages_lost']); ?></div>
                        
                        <div class="text-gray-600">Total Delivered:</div>
                        <div class="font-semibold"><?php echo number_format($stat['total_packages_delivered']); ?></div>
                        
                        <div class="text-gray-600">Lost Package %:</div>
                        <div class="font-semibold"><?php echo round($stat['percentage_lost'], 2); ?>%</div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Charts Section -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <!-- Histogram of route frequencies by package count -->
            <div class="bg-gray-100 p-6 rounded-lg">
                <h3 class="text-xl font-semibold mb-2">Package Distribution</h3>
                <div class="chart-container">
                    <canvas id="packageHistogram"></canvas>
                </div>
            </div>
            
            <!-- Donut chart of lost vs delivered packages -->
            <div class="bg-gray-100 p-6 rounded-lg">
                <h3 class="text-xl font-semibold mb-2">Package Delivery Status</h3>
                <div class="chart-container">
                    <canvas id="packageDonut"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Line graph of routes over time -->
        <div class="bg-gray-100 p-6 rounded-lg mb-6">
            <h3 class="text-xl font-semibold mb-2">Average Route Duration Over Time</h3>
            <div class="chart-container">
                <canvas id="avgDurationTimeline"></canvas>
            </div>
        </div>

        <!-- Data Table Section -->
        <div class="bg-gray-100 p-6 rounded-lg page-break">
            <h2 class="text-2xl font-semibold mb-4">Route Details</h2>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-200 text-left">
                            <th class="px-4 py-2">ID</th>
                            <th class="px-4 py-2">Type</th>
                            <th class="px-4 py-2">Driver</th>
                            <th class="px-4 py-2">Origin - Destination</th>
                            <th class="px-4 py-2">Departure</th>
                            <th class="px-4 py-2">Duration</th>
                            <th class="px-4 py-2">Packages</th>
                            <th class="px-4 py-2">Lost</th>
                            <th class="px-4 py-2">Delivered</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($routes as $route): ?>
                        <tr class="border-b border-gray-200 hover:bg-gray-50">
                            <td class="px-4 py-2"><?php echo $route['trip_id']; ?></td>
                            <td class="px-4 py-2"><?php echo htmlspecialchars($route['trip_type']); ?></td>
                            <td class="px-4 py-2"><?php echo htmlspecialchars($route['driver_name'] ?? 'N/A'); ?></td>
                            <td class="px-4 py-2"><?php echo htmlspecialchars($route['origin_city'] . ' → ' . $route['destination_city']); ?></td>
                            <td class="px-4 py-2"><?php echo date('M d, Y H:i', strtotime($route['depart_time'])); ?></td>
                            <td class="px-4 py-2"><?php echo $route['route_time_minutes']; ?> min</td>
                            <td class="px-4 py-2"><?php echo $route['package_count']; ?></td>
                            <td class="px-4 py-2"><?php echo $route['packages_lost']; ?></td>
                            <td class="px-4 py-2"><?php echo $route['packages_delivered']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        // Package count histogram chart
        const packageHistogramCtx = document.getElementById('packageHistogram').getContext('2d');
        new Chart(packageHistogramCtx, {
            type: 'bar',
            data: {
                labels: [<?php echo implode(',', array_map(function($key) { return "'$key-" . ($key+4) . "'"; }, array_keys($package_histogram_data))); ?>],
                datasets: [{
                    label: 'Number of Routes',
                    data: [<?php echo implode(',', $package_histogram_data); ?>],
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Routes'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Package Count Range'
                        }
                    }
                },
                responsive: true,
                maintainAspectRatio: false
            }
        });
        
        // Package delivery status donut chart
        const packageDonutCtx = document.getElementById('packageDonut').getContext('2d');
        new Chart(packageDonutCtx, {
            type: 'doughnut',
            data: {
                labels: ['Delivered', 'Lost'],
                datasets: [{
                    data: [<?php echo $total_delivered; ?>, <?php echo $total_lost; ?>],
                    backgroundColor: [
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(255, 99, 132, 0.7)'
                    ],
                    borderColor: [
                        'rgba(75, 192, 192, 1)',
                        'rgba(255, 99, 132, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = <?php echo $total_delivered + $total_lost; ?>;
                                const value = context.raw;
                                const percentage = Math.round((value / total) * 100);
                                return `${context.label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
        
        // Average Route Duration timeline chart
        const avgDurationTimelineCtx = document.getElementById('avgDurationTimeline').getContext('2d');
        new Chart(avgDurationTimelineCtx, {
            type: 'line',
            data: {
                labels: [<?php echo "'" . implode("','", $date_labels) . "'"; ?>],
                datasets: [{
                    label: 'Average Route Duration (Minutes)',
                    data: [<?php echo implode(',', $avg_durations); ?>],
                    fill: false,
                    borderColor: 'rgba(153, 102, 255, 1)',
                    tension: 0.1,
                    pointBackgroundColor: 'rgba(153, 102, 255, 1)',
                    pointRadius: 4
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Average Duration (Minutes)'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    }
                },
                responsive: true,
                maintainAspectRatio: false
            }
        });
    </script>
</body>
</html> 