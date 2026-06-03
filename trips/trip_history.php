<?php
session_start();
// Trip history is now combined with Trip Management (Completed Trips tab)
header("Location: manage_trips.php?tab=completed");
exit;

// Get trip history with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10; // 10 trips per page
$offset = ($page - 1) * $limit;

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM Trip WHERE employee_id = ? AND arrival_time IS NOT NULL";
$stmt = $conn->prepare($count_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total_count = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_count / $limit);

// Get paginated trip history
$history_query = "SELECT t.trip_id, t.depart_time, t.arrival_time, t.trip_type,
               d.city as departure_city, d.facility_id as departure_facility_id,
               a.city as arrival_city, a.facility_id as arrival_facility_id,
               COUNT(tp.tracking_number) as package_count,
               t.is_delivery_route
             FROM Trip t
             JOIN Facility d ON t.depart_facility_id = d.facility_id
             LEFT JOIN Facility a ON t.arrive_facility_id = a.facility_id
             LEFT JOIN Trip_Package tp ON t.trip_id = tp.trip_id
             WHERE t.employee_id = ? AND t.arrival_time IS NOT NULL
             GROUP BY t.trip_id
             ORDER BY t.depart_time DESC
             LIMIT ? OFFSET ?";
$stmt = $conn->prepare($history_query);
$stmt->bind_param("iii", $user_id, $limit, $offset);
$stmt->execute();
$trips = $stmt->get_result();

// Get driver performance metrics
$performance_query = "SELECT 
                    (SELECT COUNT(*) FROM Trip WHERE employee_id = ? AND arrival_time IS NOT NULL) as completed_trips,
                    (SELECT COUNT(*) FROM Trip_Package tp 
                    JOIN Trip t ON tp.trip_id = t.trip_id
                    WHERE t.employee_id = ?) as packages_delivered,
                    (SELECT COUNT(*) FROM Tracking_History WHERE employee_id = ? AND action = 'Delivery') as delivery_scans,
                    (SELECT COUNT(DISTINCT DATE(depart_time)) FROM Trip WHERE employee_id = ? AND arrival_time IS NOT NULL) as days_worked
                    ";
$stmt = $conn->prepare($performance_query);
$stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
$stmt->execute();
$driver_performance = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trip History</title>
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
            <a href="../index.php" class="text-white font-bold text-2xl">POSTAL PRO</a>
            <ul class="flex space-x-6">
                <li><a href="../index.php" class="text-white hover:text-gray-200">Home</a></li>
                <li><a href="../employee_dashboard.php" class="text-white hover:text-gray-200">Dashboard</a></li>
                <li><a href="../logout.php" class="text-white hover:text-gray-200">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8 max-w-6xl mt-20">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold section-title">Trip History</h1>
            <a href="../employee_dashboard.php" class="text-[#004B87] hover:text-[#DA291C]">
                <i class="fas fa-arrow-left mr-1"></i> Back to Dashboard
            </a>
        </div>

        <!-- Performance Summary -->
        <section class="bg-white shadow-sm p-6 rounded-lg mb-6">
            <h2 class="text-2xl font-semibold mb-4 section-title">Performance Summary</h2>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-center">
                <div class="bg-gray-50 p-4 rounded-md">
                    <p class="text-2xl font-bold text-[#004B87]"><?= $driver_performance['completed_trips'] ?? 0 ?></p>
                    <p class="text-sm text-gray-600">Trips Completed</p>
                </div>
                <div class="bg-gray-50 p-4 rounded-md">
                    <p class="text-2xl font-bold text-[#004B87]"><?= $driver_performance['packages_delivered'] ?? 0 ?></p>
                    <p class="text-sm text-gray-600">Packages Delivered</p>
                </div>
                <div class="bg-gray-50 p-4 rounded-md">
                    <p class="text-2xl font-bold text-[#004B87]"><?= $driver_performance['days_worked'] ?? 0 ?></p>
                    <p class="text-sm text-gray-600">Days Worked</p>
                </div>
            </div>
        </section>

        <!-- Trip History Table -->
        <section class="bg-white shadow-sm p-6 rounded-lg mb-6">
            <h2 class="text-2xl font-semibold mb-4 section-title">Trip History</h2>
            
            <?php if ($trips->num_rows > 0): ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-100 text-left">
                            <th class="px-4 py-2">Trip ID</th>
                            <th class="px-4 py-2">Type</th>
                            <th class="px-4 py-2">Route</th>
                            <th class="px-4 py-2">Date</th>
                            <th class="px-4 py-2">Duration</th>
                            <th class="px-4 py-2">Packages</th>
                            <th class="px-4 py-2">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($trip = $trips->fetch_assoc()): 
                            $trip_type = $trip['is_delivery_route'] ? 'Delivery' : ($trip['trip_type'] == 'Air' ? 'Air Transport' : 'Ground Transport');
                            $bg_class = $trip['is_delivery_route'] ? 'bg-green-100' : 'bg-blue-100';
                            $text_class = $trip['is_delivery_route'] ? 'text-green-800' : 'text-blue-800';
                            
                            $depart_time = strtotime($trip['depart_time']);
                            $arrival_time = strtotime($trip['arrival_time']);
                            $duration_seconds = $arrival_time - $depart_time;
                            $duration_hours = floor($duration_seconds / 3600);
                            $duration_minutes = floor(($duration_seconds % 3600) / 60);
                            $duration = "{$duration_hours}h {$duration_minutes}m";
                        ?>
                        <tr class="border-b border-gray-200 hover:bg-gray-50">
                            <td class="px-4 py-2">#<?= htmlspecialchars($trip['trip_id']) ?></td>
                            <td class="px-4 py-2">
                                <span class="<?= $bg_class ?> <?= $text_class ?> px-2 py-1 rounded-full text-xs">
                                    <?= $trip_type ?>
                                </span>
                            </td>
                            <td class="px-4 py-2">
                                <?= htmlspecialchars($trip['departure_city']) ?> 
                                <?php if ($trip['arrival_city']): ?>
                                    → <?= htmlspecialchars($trip['arrival_city']) ?>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2"><?= date('M d, Y', $depart_time) ?></td>
                            <td class="px-4 py-2"><?= $duration ?></td>
                            <td class="px-4 py-2"><?= htmlspecialchars($trip['package_count']) ?></td>
                            <td class="px-4 py-2">
                                <a href="view_trip.php?id=<?= urlencode($trip['trip_id']) ?>" 
                                   class="text-[#004B87] hover:text-[#DA291C]">Details</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($total_pages > 1): ?>
            <div class="flex justify-center mt-6">
                <nav class="flex items-center">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>" class="px-3 py-1 bg-gray-100 text-gray-800 rounded-l hover:bg-gray-200">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?= $i ?>" class="px-3 py-1 <?= $i === $page ? 'bg-[#004B87] text-white' : 'bg-gray-100 text-gray-800' ?> hover:bg-gray-200">
                        <?= $i ?>
                    </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>" class="px-3 py-1 bg-gray-100 text-gray-800 rounded-r hover:bg-gray-200">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                    <?php endif; ?>
                </nav>
            </div>
            <?php endif; ?>
            
            <?php else: ?>
            <div class="text-center py-8">
                <p class="text-gray-600 mb-4">You haven't completed any trips yet.</p>
                <a href="../employee_dashboard.php" class="action-btn text-white px-4 py-2 rounded">
                    Return to Dashboard
                </a>
            </div>
            <?php endif; ?>
        </section>
        
        <footer class="text-center mt-10 py-6 bg-[#004B87] text-white rounded-lg">
            <p>&copy; <?= date('Y') ?> Postal Service Management System</p>
        </footer>
    </div>
</body>
</html> 