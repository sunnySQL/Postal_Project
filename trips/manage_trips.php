<?php
require_once '../functions.php';
require_once '../db_connect.php';

// enable debug
// Check user authentication
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || 
    ($_SESSION['role'] != 'Employee' && $_SESSION['role'] != 'Admin')) {
    header("Location: ../login.php");
    exit();
}

// Get employee information
$employee_id = $_SESSION['user_id'];
$employee_query = "SELECT e.first_name, e.last_name, e.role as employee_role, 
                  f.facility_id, f.city, f.state, f.type
                  FROM Employee e 
                  JOIN Facility f ON e.facility_id = f.facility_id 
                  WHERE e.user_id = ?";
$stmt = $conn->prepare($employee_query);
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$employee = $stmt->get_result()->fetch_assoc();

// If no employee record found, show an error
if (!$employee) {
    $error_message = "Error: Unable to find your employee record. Please contact an administrator.";
    $employee = ['role' => 'Employee', 'first_name' => '', 'last_name' => '', 'type' => '', 'city' => '', 'employee_role' => 'Employee'];
}

$role = $_SESSION['role'] ?? 'Employee';
$name = ($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? '');
$employee['role'] = $employee['employee_role'] ?? $role;

$unread_admin_messages = 0;
if ($role != 'Admin') {
    $msg_stmt = $conn->prepare("SELECT COUNT(*) as count FROM Admin_Messages WHERE sender_id = ? AND status = 'Replied'");
    $msg_stmt->bind_param("i", $employee_id);
    $msg_stmt->execute();
    $unread_admin_messages = $msg_stmt->get_result()->fetch_assoc()['count'] ?? 0;
}

// Check if employee is a manager/admin or driver - for access control
$is_manager = ($employee['employee_role'] === 'Manager' || $employee['employee_role'] === 'Admin');
$is_driver = ($employee['employee_role'] === 'Driver' || $employee['employee_role'] === 'Pilot');
$current_facility_id = $employee['facility_id'];

// Packages at this facility ready to ship (not delivered, not already in transit on an active trip)
$packages_ready_to_ship = [];
if ($is_manager) {
    $ready_query = "SELECT p.tracking_number, p.status, p.timestamp_created
                    FROM Package p
                    WHERE p.facility_id = ?
                    AND p.status != 'Delivered'
                    AND p.tracking_number NOT IN (
                        SELECT tp.tracking_number FROM Trip_Package tp
                        JOIN Trip t ON tp.trip_id = t.trip_id
                        WHERE t.arrival_time IS NULL
                    )
                    ORDER BY p.timestamp_created DESC";
    $stmt = $conn->prepare($ready_query);
    $stmt->bind_param("i", $current_facility_id);
    $stmt->execute();
    $packages_ready_to_ship = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Process form submission for creating/updating trips
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Create or update trip - only managers can create trips
    if (isset($_POST['action']) && $_POST['action'] == 'create_trip') {
        if (!$is_manager) {
            $error = "You do not have permission to create trips. Only managers can perform this action.";
        } else {
            $depart_facility = $current_facility_id;
            $arrive_facility = $_POST['arrive_facility'] ?? null;
            $depart_time = $_POST['depart_time'] ?? null;
            $arrival_time = null;
            $trip_type = $_POST['trip_type'] ?? null;
            $assigned_employee = $_POST['assigned_employee'] ?? null;
            $vehicle_id = $_POST['vehicle_id'] ?? null;
            $flight_number = $_POST['flight_number'] ?? null;
            $aircraft_registration = $_POST['aircraft_registration'] ?? null;
            $airline = $_POST['airline'] ?? null;
            $is_delivery_route = ($trip_type === 'Delivery') ? 1 : 0;
            $route_area = $_POST['route_area'] ?? null;

            
            // Validate inputs (arrive_facility is optional for delivery routes)
            if (!$depart_facility || !$depart_time || !$trip_type) {
                $error = "Please fill in all required fields.";
            } else if (empty($assigned_employee)) {
                $error = "Please select a driver for this trip.";
            } else if ($trip_type !== 'Delivery' && !$arrive_facility) {
                $error = "Arrival facility is required for non-delivery trips.";
            } else {
                // Set arrive_facility to NULL for delivery routes
                if ($trip_type === 'Delivery') {
                    $arrive_facility = null;
                }
                
                // Insert trip record
                try {
                    $stmt = $conn->prepare("INSERT INTO Trip (depart_facility_id, arrive_facility_id, depart_time, arrival_time, 
                                          trip_type, employee_id, vehicle_id, flight_number, aircraft_registration, airline, is_delivery_route) 
                                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    $stmt->bind_param("iisssiisssi", $depart_facility, $arrive_facility, $depart_time, $arrival_time, 
                                   $trip_type, $assigned_employee, $vehicle_id, $flight_number, $aircraft_registration, $airline, $is_delivery_route);
                    
                    if ($stmt->execute()) {
                        $trip_id = $conn->insert_id;
                        $message = "Trip #$trip_id created successfully.";
                        logAudit($conn, 'TRIP_CREATED', 'Trip', (string)$trip_id,
                            "Trip #{$trip_id} created — type: {$trip_type}, departs: {$depart_time}",
                            $current_facility_id,
                            null,
                            ['trip_id'=>$trip_id,'trip_type'=>$trip_type,'depart_time'=>$depart_time,
                             'depart_facility'=>$depart_facility,'arrive_facility'=>$arrive_facility]
                        );
                    } else {
                        $error = "Error creating trip: " . $stmt->error;
                    }
                } catch (Exception $e) {
                    $error = "Error: " . $e->getMessage();
                }
            }
        }
    }
    
    // Add package(s) to trip - only managers can assign packages
    elseif (isset($_POST['action']) && $_POST['action'] == 'add_package') {
        if (!$is_manager) {
            $error = "You do not have permission to assign packages to trips. Only managers can perform this action.";
        } else {
            $trip_id = $_POST['trip_id'] ?? null;
            $tracking_numbers = isset($_POST['tracking_numbers']) && is_array($_POST['tracking_numbers'])
                ? array_filter($_POST['tracking_numbers'])
                : [];
            
            if (!$trip_id || empty($tracking_numbers)) {
                $error = "Please select a trip and at least one package.";
            } else {
                // Validate trip exists and is from the current facility
                $stmt = $conn->prepare("SELECT trip_id FROM Trip WHERE trip_id = ? AND depart_facility_id = ?");
                $stmt->bind_param("ii", $trip_id, $current_facility_id);
                $stmt->execute();
                $trip_result = $stmt->get_result();
                
                if ($trip_result->num_rows == 0) {
                    $error = "Trip #$trip_id not found at your facility.";
                } else {
                    $trip_query = $conn->prepare("SELECT 
                                                       t.trip_id, t.trip_type, t.is_delivery_route,
                                                       f1.city as depart_city, f1.state as depart_state,
                                                       f2.city as arrive_city, f2.state as arrive_state
                                                     FROM Trip t
                                                     JOIN Facility f1 ON t.depart_facility_id = f1.facility_id
                                                     LEFT JOIN Facility f2 ON t.arrive_facility_id = f2.facility_id
                                                     WHERE t.trip_id = ?");
                    $trip_query->bind_param("i", $trip_id);
                    $trip_query->execute();
                    $trip_info = $trip_query->get_result()->fetch_assoc();
                    
                    $added = 0;
                    $errors = [];
                    foreach ($tracking_numbers as $tracking_number) {
                        $tracking_number = trim($tracking_number);
                        if (empty($tracking_number)) continue;
                        
                        $stmt = $conn->prepare("SELECT tracking_number, status FROM Package WHERE tracking_number = ? AND facility_id = ?");
                        $stmt->bind_param("si", $tracking_number, $current_facility_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        
                        if ($result->num_rows == 0) {
                            $errors[] = "$tracking_number: not found at your facility.";
                            continue;
                        }
                        $pkg = $result->fetch_assoc();
                        if ($pkg['status'] == 'Delivered') {
                            $errors[] = "$tracking_number: already delivered.";
                            continue;
                        }
                        
                        try {
                            $stmt = $conn->prepare("INSERT INTO Trip_Package (trip_id, tracking_number) VALUES (?, ?)");
                            $stmt->bind_param("is", $trip_id, $tracking_number);
                            if (!$stmt->execute()) {
                                $errors[] = "$tracking_number: " . $conn->error;
                                continue;
                            }
                            
                            $update = $conn->prepare("UPDATE Package SET status = 'In Transit' WHERE tracking_number = ?");
                            $update->bind_param("s", $tracking_number);
                            $update->execute();
                            
                            if ($trip_info['is_delivery_route']) {
                                $location = "Delivery Route #$trip_id: From {$trip_info['depart_city']}, {$trip_info['depart_state']} ({$trip_info['trip_type']})";
                            } else {
                                $location = "Trip #$trip_id: {$trip_info['depart_city']}, {$trip_info['depart_state']} to {$trip_info['arrive_city']}, {$trip_info['arrive_state']} ({$trip_info['trip_type']})";
                            }
                            $history = $conn->prepare("INSERT INTO Tracking_History (tracking_number, location, status, employee_id, action, trip_id, facility_id) 
                                                    VALUES (?, ?, 'In Transit', ?, 'Loading', ?, ?)");
                            $history->bind_param("ssisi", $tracking_number, $location, $employee_id, $trip_id, $current_facility_id);
                            $history->execute();
                            $added++;
                        } catch (Exception $e) {
                            $errors[] = "$tracking_number: " . $e->getMessage();
                        }
                    }
                    
                    if ($added > 0) {
                        $message = $added === 1
                            ? "1 package added to Trip #$trip_id successfully."
                            : "$added packages added to Trip #$trip_id successfully.";
                    }
                    if (!empty($errors)) {
                        $error = implode(' ', $errors);
                    }
                }
            }
        }
    }
    
    // Update trip status - drivers can mark trips as completed
    elseif (isset($_POST['action']) && $_POST['action'] == 'update_trip') {
        $trip_id = $_POST['trip_id'] ?? null;
        $status = $_POST['status'] ?? null;
        $arrival_time = ($status == 'completed') ? date('Y-m-d H:i:s') : null;
        
        if (!$trip_id || !$status) {
            $error = "Trip ID and status are required.";
        } else {
            // Verify the trip is assigned to the current driver or this is a manager
            $stmt = $conn->prepare("SELECT trip_id FROM Trip WHERE trip_id = ? AND (employee_id = ? OR ?)");
            $stmt->bind_param("iii", $trip_id, $employee_id, $is_manager);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 0) {
                $error = "You are not authorized to update Trip #$trip_id.";
            } else {
                try {
                    // If trip is completed, update arrival time
                    if ($status == 'completed') {
                        $stmt = $conn->prepare("UPDATE Trip SET arrival_time = ? WHERE trip_id = ?");
                        $stmt->bind_param("si", $arrival_time, $trip_id);
                        $stmt->execute();
                        
                        // Check if this is a delivery route
                        $trip_check = $conn->prepare("SELECT is_delivery_route, depart_facility_id FROM Trip WHERE trip_id = ?");
                        $trip_check->bind_param("i", $trip_id);
                        $trip_check->execute();
                        $trip_data = $trip_check->get_result()->fetch_assoc();
                        $is_delivery_route = $trip_data['is_delivery_route'];
                        $facility_id = $trip_data['depart_facility_id'];
                        
                        if ($is_delivery_route) {
                            // For delivery routes, check for packages with delivery records
                            $packages_query = $conn->prepare("SELECT 
                                                         tp.tracking_number,
                                                         p.status
                                                       FROM Trip_Package tp
                                                       JOIN Package p ON tp.tracking_number = p.tracking_number
                                                       WHERE tp.trip_id = ?");
                            $packages_query->bind_param("i", $trip_id);
                            $packages_query->execute();
                            $packages = $packages_query->get_result();
                            
                            while ($pkg = $packages->fetch_assoc()) {
                                // Skip packages that are already marked as delivered
                                if ($pkg['status'] === 'Delivered') {
                                    continue;
                                }
                                
                                // For packages without delivery records, mark them as Processing
                                $status = 'Processing';
                                $history = $conn->prepare("INSERT INTO Tracking_History (tracking_number, location, status, employee_id, action, trip_id, facility_id) 
                                                    VALUES (?, 'Returned to facility', ?, ?, 'Scanning', ?, ?)");
                                $history->bind_param("ssiii", $pkg['tracking_number'], $status, $employee_id, $trip_id, $facility_id);
                                $history->execute();
                                
                                // Update package status
                                $update = $conn->prepare("UPDATE Package SET status = ?, facility_id = ? WHERE tracking_number = ?");
                                $update->bind_param("sis", $status, $facility_id, $pkg['tracking_number']);
                                $update->execute();
                            }
                        } else {
                            // Original code for regular trips
                            $packages_query = $conn->prepare("SELECT tp.tracking_number, t.arrive_facility_id, 
                                                       CONCAT(f.city, ', ', f.state, ' - ', f.type) as location
                                                     FROM Trip_Package tp
                                                     JOIN Trip t ON tp.trip_id = t.trip_id
                                                     JOIN Facility f ON t.arrive_facility_id = f.facility_id
                                                     WHERE tp.trip_id = ?");
                            $packages_query->bind_param("i", $trip_id);
                            $packages_query->execute();
                            $packages = $packages_query->get_result();
                            
                            while ($pkg = $packages->fetch_assoc()) {
                                // Add tracking history for arrival
                                $history = $conn->prepare("INSERT INTO Tracking_History (tracking_number, location, status, employee_id, action, trip_id, facility_id) 
                                                VALUES (?, ?, 'Processing', ?, 'Scanning', ?, ?)");
                                $history->bind_param("ssisi", $pkg['tracking_number'], $pkg['location'], $employee_id, $trip_id, $pkg['arrive_facility_id']);
                                $history->execute();
                                
                                // Update package status and facility
                                $update = $conn->prepare("UPDATE Package SET status = 'Processing', facility_id = ? WHERE tracking_number = ?");
                                $update->bind_param("is", $pkg['arrive_facility_id'], $pkg['tracking_number']);
                                $update->execute();
                            }
                        }
                        
                        $message = "Trip #$trip_id updated successfully.";
                        
                    }
                    
                } catch (Exception $e) {
                    $error = "Error updating trip: " . $e->getMessage();
                }
            }
        }
    }
}

// Fetch all facilities for dropdown
$facilities_query = "SELECT facility_id, CONCAT(city, ', ', state, ' - ', type) as facility_name FROM Facility ORDER BY state, city";
$facilities = $conn->query($facilities_query)->fetch_all(MYSQLI_ASSOC);

// Fetch all vehicles for dropdown - limited to current facility
$vehicles_query = "SELECT vehicle_id, license_plate, vehicle_type, capacity, aircraft_registration 
                  FROM Vehicle 
                  WHERE current_facility_id = ?
                  ORDER BY vehicle_type, license_plate";
$stmt = $conn->prepare($vehicles_query);
$stmt->bind_param("i", $current_facility_id);
$stmt->execute();
$vehicles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch all drivers/pilots for dropdown - limited to current facility
$drivers_query = "SELECT e.user_id, CONCAT(e.first_name, ' ', e.last_name) as name, e.role
                 FROM Employee e 
                 WHERE e.role IN ('Driver', 'Pilot') 
                 AND e.facility_id = ?
                 ORDER BY e.role, e.last_name";
$stmt = $conn->prepare($drivers_query);
$stmt->bind_param("i", $current_facility_id);
$stmt->execute();
$drivers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch active trips - facility only for managers; drivers see only their own
$active_trips_query = "SELECT 
                      t.trip_id, t.employee_id, t.depart_time, t.arrival_time, t.trip_type, t.flight_number,
                      f1.city as depart_city, f1.state as depart_state, 
                      f2.city as arrive_city, f2.state as arrive_state,
                      CONCAT(e.first_name, ' ', e.last_name) as employee_name,
                      v.license_plate, v.vehicle_type,
                      COUNT(tp.tracking_number) as package_count
                    FROM Trip t
                    JOIN Facility f1 ON t.depart_facility_id = f1.facility_id
                    LEFT JOIN Facility f2 ON t.arrive_facility_id = f2.facility_id
                    LEFT JOIN Employee e ON t.employee_id = e.user_id
                    LEFT JOIN Vehicle v ON t.vehicle_id = v.vehicle_id
                    LEFT JOIN Trip_Package tp ON t.trip_id = tp.trip_id
                    WHERE t.arrival_time IS NULL
                    AND t.depart_facility_id = ?
                    " . ($is_driver ? "AND t.employee_id = ?" : "") . "
                    GROUP BY t.trip_id
                    ORDER BY t.depart_time DESC";
$stmt = $conn->prepare($active_trips_query);
if ($is_driver) {
    $stmt->bind_param("ii", $current_facility_id, $employee_id);
} else {
    $stmt->bind_param("i", $current_facility_id);
}
$stmt->execute();
$active_trips = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch completed trips (limit to last 20) - facility only for managers; drivers see only their own
$completed_trips_query = "SELECT 
                        t.trip_id, t.employee_id, t.depart_time, t.arrival_time, t.trip_type, t.flight_number,
                        f1.city as depart_city, f1.state as depart_state, 
                        f2.city as arrive_city, f2.state as arrive_state,
                        CONCAT(e.first_name, ' ', e.last_name) as employee_name,
                        v.license_plate, v.vehicle_type,
                        COUNT(tp.tracking_number) as package_count
                      FROM Trip t
                      JOIN Facility f1 ON t.depart_facility_id = f1.facility_id
                      LEFT JOIN Facility f2 ON t.arrive_facility_id = f2.facility_id
                      LEFT JOIN Employee e ON t.employee_id = e.user_id
                      LEFT JOIN Vehicle v ON t.vehicle_id = v.vehicle_id
                      LEFT JOIN Trip_Package tp ON t.trip_id = tp.trip_id
                      WHERE t.arrival_time IS NOT NULL
                      AND t.depart_facility_id = ?
                      " . ($is_driver ? "AND t.employee_id = ?" : "") . "
                      GROUP BY t.trip_id
                      ORDER BY t.arrival_time DESC
                      LIMIT 20";
$stmt = $conn->prepare($completed_trips_query);
if ($is_driver) {
    $stmt->bind_param("ii", $current_facility_id, $employee_id);
} else {
    $stmt->bind_param("i", $current_facility_id);
}
$stmt->execute();
$completed_trips = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Trips</title>
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
<body class="bg-gray-50">
<?php $emp_base = '../'; include '../_employee_nav.php'; ?>

    <div class="container mx-auto px-4 py-8 max-w-6xl">
        <header class="mb-6">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center">
                <h1 class="text-3xl font-bold section-title">Manage Trips</h1>
                <div class="mt-4 md:mt-0 bg-white shadow-sm p-4 rounded-lg">
                    <p class="mb-1"><span class="font-semibold">Welcome,</span> <?= htmlspecialchars($name) ?> | <span class="font-medium"><?= htmlspecialchars($employee['role']) ?></span></p>
                    <p><span class="font-semibold">Facility:</span> <?= htmlspecialchars($employee['type']) ?> - <?= htmlspecialchars($employee['city']) ?></p>
                </div>
            </div>
        </header>

        <nav class="bg-white shadow-sm p-4 rounded-lg mb-6">
            <ul class="flex flex-wrap space-x-2 md:space-x-6">
                <?php if ($role == 'Admin'): ?>
                <li><a href="../admin/manage_users.php" class="text-gray-700 hover:text-[#DA291C]">Manage Users</a></li>
                <li><a href="../admin/manage_facilities.php" class="text-gray-700 hover:text-[#DA291C]">Manage Facilities</a></li>
                <li><a href="../admin/manage_vehicles.php" class="text-gray-700 hover:text-[#DA291C]">Manage Vehicles</a></li>
                <li><a href="../admin/inbox.php" class="text-gray-700 hover:text-[#DA291C]">Admin Inbox</a></li>
                <?php else: ?>
                <li><a href="../employee/contact_admin.php" class="text-gray-700 hover:text-[#DA291C] flex items-center">
                    Contact Admin
                    <?php if ($unread_admin_messages > 0): ?>
                        <span class="ml-2 bg-red-600 text-white text-xs px-2 py-1 rounded-full"><?= $unread_admin_messages ?></span>
                    <?php endif; ?>
                </a></li>
                <?php endif; ?>
                <?php if (isset($employee['role'])): ?>
                <?php if ($employee['role'] == 'Clerk'): ?>
                <li><a href="../package/new_package.php" class="text-gray-700 hover:text-[#DA291C]">Create Shipment</a></li>
                <li><a href="../shop/inventory_management.php" class="text-gray-700 hover:text-[#DA291C]">Inventory Management</a></li>
                <li><a href="../package/search.php" class="text-gray-700 hover:text-[#DA291C]">Package Search &amp; Scan</a></li>
                <?php endif; ?>
                <?php if ($employee['role'] == 'Manager'): ?>
                <li><a href="../shop/shop_dashboard.php" class="text-gray-700 hover:text-[#DA291C]">Shop Overview</a></li>
                <li><a href="../reports/index.php" class="text-gray-700 hover:text-[#DA291C]">Reports</a></li>
                <li><a href="manage_trips.php" class="text-gray-700 hover:text-[#DA291C] border-b-2 border-[#DA291C] pb-1 font-medium">Manage Trips</a></li>
                <?php endif; ?>
                <?php endif; ?>
                <?php if (isset($employee['role']) && $employee['role'] == 'Clerk'): ?>
                <li><a href="../package/awaiting_pickup.php" class="text-gray-700 hover:text-[#DA291C]">Pickup</a></li>
                <?php endif; ?>
                <?php if ($role != 'Admin'): ?>
                <li><a href="../edit_profile.php" class="text-gray-700 hover:text-[#DA291C]">Edit Profile</a></li>
                <?php endif; ?>
            </ul>
        </nav>

        <?php
        $tab_param = $_GET['tab'] ?? 'active';
        $valid_tabs = ['active' => 'active-trips', 'completed' => 'completed-trips', 'delivery' => 'delivery-routes', 'create' => 'create-trip', 'add_package' => 'add-package'];
        $current_tab_pane = isset($valid_tabs[$tab_param]) ? $valid_tabs[$tab_param] : 'active-trips';
        ?>
        <nav class="bg-gray-100 border border-gray-200 rounded-lg px-4 py-3 mb-6">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Trips</p>
            <ul class="flex flex-wrap gap-x-4 gap-y-1">
                <li><a href="manage_trips.php?tab=active" class="text-gray-700 hover:text-[#DA291C] <?= $current_tab_pane === 'active-trips' ? 'border-b-2 border-[#DA291C] pb-0.5 font-medium' : '' ?>">Active Trips</a></li>
                <li><a href="manage_trips.php?tab=completed" class="text-gray-700 hover:text-[#DA291C] <?= $current_tab_pane === 'completed-trips' ? 'border-b-2 border-[#DA291C] pb-0.5 font-medium' : '' ?>">Trip History</a></li>
                <li><a href="manage_trips.php?tab=delivery" class="text-gray-700 hover:text-[#DA291C] <?= $current_tab_pane === 'delivery-routes' ? 'border-b-2 border-[#DA291C] pb-0.5 font-medium' : '' ?>">Delivery Routes</a></li>
                <?php if ($is_manager): ?>
                <li><a href="manage_trips.php?tab=create" class="text-gray-700 hover:text-[#DA291C] <?= $current_tab_pane === 'create-trip' ? 'border-b-2 border-[#DA291C] pb-0.5 font-medium' : '' ?>">Create New Trip</a></li>
                <li><a href="manage_trips.php?tab=add_package" class="text-gray-700 hover:text-[#DA291C] <?= $current_tab_pane === 'add-package' ? 'border-b-2 border-[#DA291C] pb-0.5 font-medium' : '' ?>">Add Package to Trip</a></li>
                <?php endif; ?>
                <?php if ($is_driver): ?>
                <li><a href="current_trip.php" class="text-gray-700 hover:text-[#DA291C]">My Current Trip</a></li>
                <?php endif; ?>
            </ul>
        </nav>
        
        <?php if (isset($error_message)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6"><?= htmlspecialchars($error_message) ?></div>
        <?php else: ?>
            
            <?php if (!empty($message)): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <div class="tab-content pt-4" id="tripTabContent">
                <!-- Active Trips Tab -->
                <div class="tab-pane <?= $current_tab_pane === 'active-trips' ? 'block' : 'hidden' ?>" id="active-trips" role="tabpanel">
                    <div class="bg-white shadow-sm p-6 mb-6 rounded-lg">
                        <h2 class="text-2xl font-semibold mb-4 section-title">Active Trips</h2>
                        
                        <?php if (empty($active_trips)): ?>
                            <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4">No active trips found.</div>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($active_trips as $trip): ?>
                                    <div class="bg-gray-50 rounded-lg shadow-sm border-l-4 border-blue-500">
                                        <div class="bg-gray-100 px-4 py-3 rounded-t-lg font-medium flex justify-between items-center">
                                            <div>
                                                Trip #<?= $trip['trip_id'] ?> 
                                                <?php if ($trip['trip_type'] == 'Road'): ?>
                                                    <span class="ml-2 bg-gray-600 text-white px-2 py-1 rounded-full text-xs font-medium">Road</span>
                                                <?php elseif ($trip['trip_type'] == 'Air'): ?>
                                                    <span class="ml-2 bg-blue-500 text-white px-2 py-1 rounded-full text-xs font-medium">
                                                        Air <?= !empty($trip['flight_number']) ? '- ' . $trip['flight_number'] : '' ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="ml-2 bg-green-500 text-white px-2 py-1 rounded-full text-xs font-medium">Delivery</span>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <span class="bg-gray-200 px-2 py-1 rounded-full text-xs font-medium">
                                                    Packages: <?= $trip['package_count'] ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="p-4">
                                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                                <div>
                                                    <p class="mb-1"><span class="font-medium">From:</span> <?= htmlspecialchars($trip['depart_city'] . ', ' . $trip['depart_state']) ?></p>
                                                    <?php if ($trip['trip_type'] === 'Delivery'): ?>
                                                        <p class="mb-1"><span class="font-medium">Route:</span> Delivery Route</p>
                                                    <?php else: ?>
                                                        <p class="mb-1"><span class="font-medium">To:</span> <?= htmlspecialchars($trip['arrive_city'] . ', ' . $trip['arrive_state']) ?></p>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <p class="mb-1"><span class="font-medium">Departure:</span> <?= date('m/d/y H:i', strtotime($trip['depart_time'])) ?></p>
                                                    <p class="mb-1"><span class="font-medium">Employee:</span> <?= htmlspecialchars($trip['employee_name']) ?></p>
                                                </div>
                                                <div>
                                                    <p class="mb-2"><span class="font-medium">Vehicle:</span> 
                                                        <?php 
                                                        if ($trip['trip_type'] == 'Road') {
                                                            echo htmlspecialchars($trip['vehicle_type'] . ' - ' . $trip['license_plate']);
                                                        } else {
                                                            echo htmlspecialchars($trip['vehicle_type']);
                                                        }
                                                        ?>
                                                    </p>
                                                    <div class="flex space-x-2">
                                                        <?php if ($is_driver && $trip['employee_id'] == $employee_id || $is_manager): ?>
                                                        <form method="post" action="">
                                                            <input type="hidden" name="action" value="update_trip">
                                                            <input type="hidden" name="trip_id" value="<?= $trip['trip_id'] ?>">
                                                            <input type="hidden" name="status" value="completed">
                                                            <button type="submit" class="accent-btn text-white px-3 py-2 rounded text-sm">
                                                                Mark as Arrived
                                                            </button>
                                                        </form>
                                                        <?php endif; ?>
                                                        <a href="view_trip.php?id=<?= $trip['trip_id'] ?>" class="action-btn text-white px-3 py-2 rounded text-sm">
                                                            View Details
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Trip History (Active + Completed) Tab -->
                <div class="tab-pane <?= $current_tab_pane === 'completed-trips' ? 'block' : 'hidden' ?>" id="completed-trips" role="tabpanel">
                    <div class="bg-white shadow-sm p-6 mb-6 rounded-lg">
                        <h2 class="text-2xl font-semibold mb-4 section-title">Trip History</h2>
                        
                        <!-- Active Trips section -->
                        <h3 class="text-lg font-semibold text-gray-700 mb-3">Active</h3>
                        <?php if (empty($active_trips)): ?>
                            <p class="text-gray-500 text-sm mb-6">No active trips.</p>
                        <?php else: ?>
                            <div class="space-y-4 mb-8">
                                <?php foreach ($active_trips as $trip): ?>
                                    <div class="bg-gray-50 rounded-lg shadow-sm border-l-4 border-blue-500">
                                        <div class="bg-gray-100 px-4 py-3 rounded-t-lg font-medium flex justify-between items-center">
                                            <div>
                                                Trip #<?= $trip['trip_id'] ?> 
                                                <span class="ml-2 bg-blue-500 text-white px-2 py-1 rounded-full text-xs font-medium">Active</span>
                                                <?php if ($trip['trip_type'] == 'Road'): ?>
                                                    <span class="ml-2 bg-gray-600 text-white px-2 py-1 rounded-full text-xs font-medium">Road</span>
                                                <?php elseif ($trip['trip_type'] == 'Air'): ?>
                                                    <span class="ml-2 bg-blue-600 text-white px-2 py-1 rounded-full text-xs font-medium">
                                                        Air <?= !empty($trip['flight_number']) ? '- ' . htmlspecialchars($trip['flight_number']) : '' ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="ml-2 bg-green-500 text-white px-2 py-1 rounded-full text-xs font-medium">Delivery</span>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <span class="bg-gray-200 px-2 py-1 rounded-full text-xs font-medium">
                                                    Packages: <?= $trip['package_count'] ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="p-4">
                                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                                <div>
                                                    <p class="mb-1"><span class="font-medium">From:</span> <?= htmlspecialchars($trip['depart_city'] . ', ' . $trip['depart_state']) ?></p>
                                                    <?php if (!empty($trip['arrive_city'])): ?>
                                                        <p class="mb-1"><span class="font-medium">To:</span> <?= htmlspecialchars($trip['arrive_city'] . ', ' . ($trip['arrive_state'] ?? '')) ?></p>
                                                    <?php else: ?>
                                                        <p class="mb-1"><span class="font-medium">Route:</span> Delivery Route</p>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <p class="mb-1"><span class="font-medium">Departure:</span> <?= date('m/d/y H:i', strtotime($trip['depart_time'])) ?></p>
                                                    <p class="mb-1"><span class="font-medium">Employee:</span> <?= htmlspecialchars($trip['employee_name'] ?? '—') ?></p>
                                                </div>
                                                <div>
                                                    <a href="view_trip.php?id=<?= $trip['trip_id'] ?>" class="action-btn text-white px-3 py-2 rounded text-sm inline-block">
                                                        View Details
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Completed Trips section -->
                        <h3 class="text-lg font-semibold text-gray-700 mb-3">Completed</h3>
                        <?php if (empty($completed_trips)): ?>
                            <p class="text-gray-500 text-sm">No completed trips yet.</p>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($completed_trips as $trip): ?>
                                    <div class="bg-gray-50 rounded-lg shadow-sm border-l-4 border-green-500">
                                        <div class="bg-gray-100 px-4 py-3 rounded-t-lg font-medium flex justify-between items-center">
                                            <div>
                                                Trip #<?= $trip['trip_id'] ?> 
                                                <?php if ($trip['trip_type'] == 'Road'): ?>
                                                    <span class="ml-2 bg-gray-600 text-white px-2 py-1 rounded-full text-xs font-medium">Road</span>
                                                <?php elseif ($trip['trip_type'] == 'Air'): ?>
                                                    <span class="ml-2 bg-blue-500 text-white px-2 py-1 rounded-full text-xs font-medium">
                                                        Air <?= !empty($trip['flight_number']) ? '- ' . htmlspecialchars($trip['flight_number']) : '' ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="ml-2 bg-green-500 text-white px-2 py-1 rounded-full text-xs font-medium">Delivery</span>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <span class="bg-gray-200 px-2 py-1 rounded-full text-xs font-medium">
                                                    Packages: <?= $trip['package_count'] ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="p-4">
                                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                                <div>
                                                    <p class="mb-1"><span class="font-medium">From:</span> <?= htmlspecialchars($trip['depart_city'] . ', ' . $trip['depart_state']) ?></p>
                                                    <?php if (!empty($trip['arrive_city'])): ?>
                                                        <p class="mb-1"><span class="font-medium">To:</span> <?= htmlspecialchars($trip['arrive_city'] . ', ' . ($trip['arrive_state'] ?? '')) ?></p>
                                                    <?php else: ?>
                                                        <p class="mb-1"><span class="font-medium">Route:</span> Delivery Route</p>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <p class="mb-1"><span class="font-medium">Departure:</span> <?= date('m/d/y H:i', strtotime($trip['depart_time'])) ?></p>
                                                    <p class="mb-1"><span class="font-medium">Arrival:</span> <?= date('m/d/y H:i', strtotime($trip['arrival_time'])) ?></p>
                                                </div>
                                                <div>
                                                    <p class="mb-2"><span class="font-medium">Duration:</span> 
                                                        <?php 
                                                        $depart = new DateTime($trip['depart_time']);
                                                        $arrive = new DateTime($trip['arrival_time']);
                                                        $interval = $depart->diff($arrive);
                                                        echo $interval->format('%h hrs, %i mins');
                                                        ?>
                                                    </p>
                                                    <a href="view_trip.php?id=<?= $trip['trip_id'] ?>" class="action-btn text-white px-3 py-2 rounded text-sm inline-block">
                                                        View Details
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Delivery Routes Tab -->
                <div class="tab-pane <?= $current_tab_pane === 'delivery-routes' ? 'block' : 'hidden' ?>" id="delivery-routes" role="tabpanel">
                    <div class="bg-white shadow-sm p-6 mb-6 rounded-lg">
                        <h2 class="text-2xl font-semibold mb-4 section-title">Delivery Routes</h2>
                        
                        <?php
                        $delivery_routes_query = "SELECT 
                            t.trip_id, t.depart_time, t.arrival_time, t.trip_type, 
                            f1.city as depart_city, f1.state as depart_state, 
                            CONCAT(e.first_name, ' ', e.last_name) as employee_name,
                            v.license_plate, v.vehicle_type,
                            COUNT(tp.tracking_number) as package_count,
                            t.is_delivery_route
                            FROM Trip t
                            JOIN Facility f1 ON t.depart_facility_id = f1.facility_id
                            LEFT JOIN Employee e ON t.employee_id = e.user_id
                            LEFT JOIN Vehicle v ON t.vehicle_id = v.vehicle_id
                            LEFT JOIN Trip_Package tp ON t.trip_id = tp.trip_id
                            WHERE t.is_delivery_route = 1
                            AND t.depart_facility_id = ?
                            " . ($is_driver ? "AND t.employee_id = ?" : "") . "
                            GROUP BY t.trip_id
                            ORDER BY t.depart_time DESC";
                        $dr_stmt = $conn->prepare($delivery_routes_query);
                        if ($is_driver) {
                            $dr_stmt->bind_param("ii", $current_facility_id, $employee_id);
                        } else {
                            $dr_stmt->bind_param("i", $current_facility_id);
                        }
                        $dr_stmt->execute();
                        $delivery_routes = $dr_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                        ?>
                        
                        <?php if (empty($delivery_routes)): ?>
                            <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4">No delivery routes found.</div>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($delivery_routes as $route): ?>
                                    <div class="bg-gray-50 rounded-lg shadow-sm border-l-4 <?= $route['arrival_time'] ? 'border-green-500' : 'border-blue-500' ?>">
                                        <div class="bg-gray-100 px-4 py-3 rounded-t-lg font-medium flex justify-between items-center">
                                            <div>
                                                Delivery Route #<?= $route['trip_id'] ?> 
                                                <span class="ml-2 bg-green-500 text-white px-2 py-1 rounded-full text-xs font-medium">
                                                    Delivery
                                                </span>
                                            </div>
                                            <div>
                                                <span class="bg-gray-200 px-2 py-1 rounded-full text-xs font-medium">
                                                    Packages: <?= $route['package_count'] ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="p-4">
                                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                                <div>
                                                    <p class="mb-1"><span class="font-medium">From:</span> <?= htmlspecialchars($route['depart_city'] . ', ' . $route['depart_state']) ?></p>
                                                    <p class="mb-1"><span class="font-medium">Area:</span> <?= htmlspecialchars($route['route_area'] ?? 'Not specified') ?></p>
                                                </div>
                                                <div>
                                                    <p class="mb-1"><span class="font-medium">Departure:</span> <?= date('m/d/y H:i', strtotime($route['depart_time'])) ?></p>
                                                    <?php if ($route['arrival_time']): ?>
                                                        <p class="mb-1"><span class="font-medium">Completion:</span> <?= date('m/d/y H:i', strtotime($route['arrival_time'])) ?></p>
                                                    <?php else: ?>
                                                        <p class="mb-1"><span class="font-medium">Status:</span> <span class="text-yellow-600 font-medium">In Progress</span></p>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <p class="mb-1"><span class="font-medium">Driver:</span> <?= htmlspecialchars($route['employee_name']) ?></p>
                                                    <p class="mb-2"><span class="font-medium">Vehicle:</span> <?= htmlspecialchars($route['vehicle_type'] . ' - ' . $route['license_plate']) ?></p>
                                                    <div class="flex flex-wrap gap-2">
                                                        <?php if (!$route['arrival_time']): ?>
                                                            <form method="post" action="">
                                                                <input type="hidden" name="action" value="update_trip">
                                                                <input type="hidden" name="trip_id" value="<?= $route['trip_id'] ?>">
                                                                <input type="hidden" name="status" value="completed">
                                                                <button type="submit" class="accent-btn text-white px-3 py-2 rounded text-sm">
                                                                    Mark as Completed
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                        <a href="view_trip.php?id=<?= $route['trip_id'] ?>" class="action-btn text-white px-3 py-2 rounded text-sm">
                                                            View Details
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Create Trip Tab -->
                <div class="tab-pane <?= $current_tab_pane === 'create-trip' ? 'block' : 'hidden' ?>" id="create-trip" role="tabpanel">
                    <div class="bg-white shadow-sm p-6 mb-6 rounded-lg">
                        <h2 class="text-2xl font-semibold mb-4 section-title">Create New Trip</h2>
                        
                        <form method="post" action="" id="createTripForm">
                            <input type="hidden" name="action" value="create_trip">
                            <input type="hidden" name="vehicle_id" id="vehicle_id" value="">
                            <input type="hidden" name="is_delivery_route" id="is_delivery_route" value="0">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label for="trip_type" class="block text-sm font-medium text-gray-700 mb-1">Trip Type</label>
                                    <select class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:border-[#004B87] focus:ring focus:ring-[#004B87] focus:ring-opacity-30" id="trip_type" name="trip_type" required>
                                        <option value="">-- Select Type --</option>
                                        <option value="Road">Road Trip</option>
                                        <option value="Air">Air Trip</option>
                                        <option value="Delivery">Delivery Route</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label for="depart_time" class="block text-sm font-medium text-gray-700 mb-1">Departure Time</label>
                                    <input type="datetime-local" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:border-[#004B87] focus:ring focus:ring-[#004B87] focus:ring-opacity-30" id="depart_time" name="depart_time" required>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label for="depart_facility" class="block text-sm font-medium text-gray-700 mb-1">Departure Facility</label>
                                    <select class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-100" id="depart_facility" name="depart_facility" required disabled>
                                        <?php foreach ($facilities as $facility): ?>
                                            <option value="<?= $facility['facility_id'] ?>" <?= ($facility['facility_id'] == $current_facility_id) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($facility['facility_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="depart_facility" value="<?= $current_facility_id ?>">
                                </div>
                                
                                <div id="arrive_facility_group">
                                    <label for="arrive_facility" class="block text-sm font-medium text-gray-700 mb-1">Arrival Facility</label>
                                    <select class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm" id="arrive_facility" name="arrive_facility">
                                        <option value="">-- Select Arrival Facility --</option>
                                        <?php foreach ($facilities as $facility): ?>
                                            <?php if ($facility['facility_id'] != $current_facility_id): ?>
                                                <option value="<?= $facility['facility_id'] ?>">
                                                    <?= htmlspecialchars($facility['facility_name']) ?>
                                                </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label for="assigned_employee" class="block text-sm font-medium text-gray-700 mb-1">Assigned Employee</label>
                                    <select class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm" id="assigned_employee" name="assigned_employee" required>
                                        <option value="">-- Select Driver/Pilot --</option>
                                        <?php foreach ($drivers as $driver): ?>
                                            <option value="<?= $driver['user_id'] ?>" data-role="<?= $driver['role'] ?>">
                                                <?= htmlspecialchars($driver['name']) ?> (<?= $driver['role'] ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Road Fields -->
                            <div id="road_fields" class="mb-4 hidden">
                                <div>
                                    <label for="road_vehicle_id" class="block text-sm font-medium text-gray-700 mb-1">Vehicle</label>
                                    <select class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm" id="road_vehicle_id" name="road_vehicle_id">
                                        <option value="">-- Select Vehicle --</option>
                                        <?php foreach ($vehicles as $vehicle): 
                                            if ($vehicle['vehicle_type'] != 'Airplane'): ?>
                                        <option value="<?= $vehicle['vehicle_id'] ?>" data-type="<?= $vehicle['vehicle_type'] ?>">
                                            <?= htmlspecialchars($vehicle['vehicle_type'] . ' - ' . $vehicle['license_plate']) ?>
                                        </option>
                                        <?php endif; endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Delivery Route Fields -->
                            <div id="delivery_route_fields" class="mb-4 hidden">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="route_area" class="block text-sm font-medium text-gray-700 mb-1">Route Area</label>
                                        <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm" id="route_area" name="route_area" placeholder="e.g. North Seattle, Downtown">
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <label for="delivery_vehicle_id" class="block text-sm font-medium text-gray-700 mb-1">Delivery Vehicle</label>
                                    <select class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm" id="delivery_vehicle_id" name="delivery_vehicle_id">
                                        <option value="">-- Select Vehicle --</option>
                                        <?php foreach ($vehicles as $vehicle): 
                                            if (in_array($vehicle['vehicle_type'], ['Van', 'Truck', 'Motorcycle'])): ?>
                                        <option value="<?= $vehicle['vehicle_id'] ?>" data-type="<?= $vehicle['vehicle_type'] ?>">
                                            <?= htmlspecialchars($vehicle['vehicle_type'] . ' - ' . $vehicle['license_plate']) ?>
                                        </option>
                                        <?php endif; endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Air Fields -->
                            <div id="air_fields" class="mb-4 hidden">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                    <div>
                                        <label for="flight_number" class="block text-sm font-medium text-gray-700 mb-1">Flight Number</label>
                                        <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm" id="flight_number" name="flight_number">
                                    </div>
                                    <div>
                                        <label for="aircraft_registration" class="block text-sm font-medium text-gray-700 mb-1">Aircraft Registration</label>
                                        <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm" id="aircraft_registration" name="aircraft_registration">
                                    </div>
                                    <div>
                                        <label for="airline" class="block text-sm font-medium text-gray-700 mb-1">Airline</label>
                                        <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm" id="airline" name="airline" value="Postal Airways">
                                    </div>
                                </div>
                                
                                <div>
                                    <label for="air_vehicle_id" class="block text-sm font-medium text-gray-700 mb-1">Aircraft</label>
                                    <select class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm" id="air_vehicle_id" name="air_vehicle_id">
                                        <option value="">-- Select Aircraft --</option>
                                        <?php foreach ($vehicles as $vehicle): 
                                            if ($vehicle['vehicle_type'] == 'Airplane'): ?>
                                        <option value="<?= $vehicle['vehicle_id'] ?>">
                                            <?= htmlspecialchars('Airplane - ' . $vehicle['aircraft_registration']) ?>
                                        </option>
                                        <?php endif; endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <button type="submit" class="action-btn text-white px-4 py-2 rounded shadow-sm">Create Trip</button>
                        </form>
                    </div>
                </div>
                
                <!-- Add Package to Trip Tab -->
                <div class="tab-pane <?= $current_tab_pane === 'add-package' ? 'block' : 'hidden' ?>" id="add-package" role="tabpanel">
                    <div class="bg-white shadow-sm p-6 mb-6 rounded-lg">
                        <h2 class="text-2xl font-semibold mb-4 section-title">Add Package to Trip</h2>
                        
                        <form method="post" action="" id="addPackageForm">
                            <input type="hidden" name="action" value="add_package">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label for="trip_id" class="block text-sm font-medium text-gray-700 mb-1">Trip</label>
                                    <select class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:border-[#004B87] focus:ring focus:ring-[#004B87] focus:ring-opacity-30" id="trip_id" name="trip_id" required>
                                        <option value="">-- Select Trip --</option>
                                        <?php 
                                        // Fetch active trips from current facility
                                        $all_active_trips_query = "SELECT 
                                                                t.trip_id, t.depart_time, t.trip_type, t.is_delivery_route,
                                                                f1.city as depart_city, f1.state as depart_state, 
                                                                f2.city as arrive_city, f2.state as arrive_state
                                                              FROM Trip t
                                                              JOIN Facility f1 ON t.depart_facility_id = f1.facility_id
                                                              LEFT JOIN Facility f2 ON t.arrive_facility_id = f2.facility_id
                                                              WHERE t.arrival_time IS NULL
                                                              AND t.depart_facility_id = ?
                                                              ORDER BY t.depart_time DESC";
                                        $stmt = $conn->prepare($all_active_trips_query);
                                        $stmt->bind_param("i", $current_facility_id);
                                        $stmt->execute();
                                        $all_active_trips = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                                        
                                        foreach ($all_active_trips as $trip): 
                                        ?>
                                        <option value="<?= $trip['trip_id'] ?>">
                                            <?php if ($trip['is_delivery_route']): ?>
                                            Delivery Route #<?= $trip['trip_id'] ?> - 
                                            <?= htmlspecialchars($trip['depart_city']) ?> Area
                                            (Delivery)
                                            <?php else: ?>
                                            Trip #<?= $trip['trip_id'] ?> - 
                                            <?= htmlspecialchars($trip['depart_city']) ?> to 
                                            <?= htmlspecialchars($trip['arrive_city']) ?> 
                                            (<?= $trip['trip_type'] ?>)
                                            <?php endif; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="relative" id="package_select_wrapper">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Packages</label>
                                    <div id="package_search_box" class="flex items-center justify-between w-full px-3 py-2.5 border border-gray-300 rounded-md shadow-sm bg-white cursor-pointer hover:border-gray-400 focus-within:border-[#004B87] focus-within:ring-1 focus-within:ring-[#004B87]" tabindex="0" role="button" aria-expanded="false" aria-haspopup="listbox">
                                        <span id="package_search_label" class="text-gray-500 text-sm">Click to select packages</span>
                                        <span id="package_selected_count" class="text-sm font-medium text-[#004B87] hidden">0 selected</span>
                                        <i class="fas fa-chevron-down text-gray-400 text-xs transition-transform" id="package_chevron"></i>
                                    </div>
                                    <div id="package_dropdown" class="hidden absolute left-0 right-0 top-full mt-1 z-20 border border-gray-300 rounded-md shadow-lg bg-white max-h-[320px] overflow-hidden flex flex-col">
                                        <?php if (empty($packages_ready_to_ship)): ?>
                                            <p class="p-4 text-gray-500 text-sm">No packages ready to ship at this facility.</p>
                                        <?php else: ?>
                                            <div class="p-2 border-b border-gray-200 bg-gray-50 shrink-0">
                                                <label class="flex items-center gap-2 cursor-pointer text-sm font-medium text-gray-700">
                                                    <input type="checkbox" id="select_all_packages" class="rounded border-gray-300 text-[#004B87] focus:ring-[#004B87]">
                                                    <span>Select all</span>
                                                </label>
                                            </div>
                                            <div class="p-2 overflow-y-auto flex-1 min-h-0" id="package_checkbox_list">
                                                <?php foreach ($packages_ready_to_ship as $p): ?>
                                                <label class="flex items-center gap-3 py-2 px-2 hover:bg-gray-50 rounded cursor-pointer border-b border-gray-100 last:border-0">
                                                    <input type="checkbox" name="tracking_numbers[]" value="<?= htmlspecialchars($p['tracking_number']) ?>" class="package-cb rounded border-gray-300 text-[#004B87] focus:ring-[#004B87]">
                                                    <span class="text-sm"><?= htmlspecialchars($p['tracking_number']) ?> — <?= htmlspecialchars($p['status']) ?> <span class="text-gray-500">(<?= date('M j, Y', strtotime($p['timestamp_created'])) ?>)</span></span>
                                                </label>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <p class="mt-1 text-sm text-gray-500">Click the box above to open the list. Sorted by date (most recent first).</p>
                                </div>
                            </div>
                            
                            <button type="submit" class="action-btn text-white px-4 py-2 rounded shadow-sm" <?= empty($packages_ready_to_ship) ? 'disabled' : '' ?>>Add Selected Package(s) to Trip</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Tab visibility is now controlled by PHP via ?tab= param and the Trips bar links
                
                // Handle trip type change to show appropriate fields
                const tripTypeSelect = document.getElementById('trip_type');
                if (tripTypeSelect) {
                    tripTypeSelect.addEventListener('change', function() {
                        const type = this.value;
                        
                        const roadFields = document.getElementById('road_fields');
                        const deliveryRouteFields = document.getElementById('delivery_route_fields');
                        const airFields = document.getElementById('air_fields');
                        const isDeliveryRouteInput = document.getElementById('is_delivery_route');
                        const arriveFacilityGroup = document.getElementById('arrive_facility_group');
                        const arriveFacilitySelect = document.getElementById('arrive_facility');
                        const assignedEmployeeSelect = document.getElementById('assigned_employee');
                        const roadVehicleSelect = document.getElementById('road_vehicle_id');
                        const airVehicleSelect = document.getElementById('air_vehicle_id');
                        
                        if (type === 'Road') {
                            roadFields.style.display = 'block';
                            deliveryRouteFields.style.display = 'none';
                            airFields.style.display = 'none';
                            isDeliveryRouteInput.value = '0';
                            arriveFacilityGroup.style.display = 'block';
                            
                            // Clear air vehicle selection when switching to road
                            airVehicleSelect.value = '';
                            
                            // Filter employee dropdown for drivers
                            Array.from(assignedEmployeeSelect.options).forEach(option => {
                                if (option.getAttribute('data-role') === 'Driver' || option.value === '') {
                                    option.style.display = 'block';
                                } else {
                                    option.style.display = 'none';
                                }
                            });
                            
                        } else if (type === 'Air') {
                            airFields.style.display = 'block';
                            roadFields.style.display = 'none';
                            deliveryRouteFields.style.display = 'none';
                            isDeliveryRouteInput.value = '0';
                            arriveFacilityGroup.style.display = 'block';
                            
                            // Clear road vehicle selection when switching to air
                            roadVehicleSelect.value = '';
                            
                            // Filter employee dropdown for pilots
                            Array.from(assignedEmployeeSelect.options).forEach(option => {
                                if (option.getAttribute('data-role') === 'Pilot' || option.value === '') {
                                    option.style.display = 'block';
                                } else {
                                    option.style.display = 'none';
                                }
                            });
                        } else if (type === 'Delivery') {
                            deliveryRouteFields.style.display = 'block';
                            roadFields.style.display = 'none';
                            airFields.style.display = 'none';
                            isDeliveryRouteInput.value = '1';
                            arriveFacilityGroup.style.display = 'none';
                            arriveFacilitySelect.value = '';
                            
                            // Filter employee dropdown for drivers
                            Array.from(assignedEmployeeSelect.options).forEach(option => {
                                if (option.getAttribute('data-role') === 'Driver' || option.value === '') {
                                    option.style.display = 'block';
                                } else {
                                    option.style.display = 'none';
                                }
                            });
                        } else {
                            roadFields.style.display = 'none';
                            deliveryRouteFields.style.display = 'none';
                            airFields.style.display = 'none';
                            isDeliveryRouteInput.value = '0';
                            arriveFacilityGroup.style.display = 'block';
                            
                            // Clear both vehicle selections when no type selected
                            roadVehicleSelect.value = '';
                            airVehicleSelect.value = '';
                            
                            // Show all employee options
                            Array.from(assignedEmployeeSelect.options).forEach(option => {
                                option.style.display = 'block';
                            });
                        }
                    });
                }
                
                // Update the hidden vehicle_id field when vehicle is selected
                const roadVehicleSelect = document.getElementById('road_vehicle_id');
                const airVehicleSelect = document.getElementById('air_vehicle_id');
                const deliveryVehicleSelect = document.getElementById('delivery_vehicle_id');
                const vehicleIdInput = document.getElementById('vehicle_id');
                
                if (roadVehicleSelect) {
                    roadVehicleSelect.addEventListener('change', function() {
                        vehicleIdInput.value = this.value;
                    });
                }
                
                if (airVehicleSelect) {
                    airVehicleSelect.addEventListener('change', function() {
                        vehicleIdInput.value = this.value;
                    });
                }
                
                if (deliveryVehicleSelect) {
                    deliveryVehicleSelect.addEventListener('change', function() {
                        vehicleIdInput.value = this.value;
                    });
                }
                
                // Set default departure time to now
                const departTimeInput = document.getElementById('depart_time');
                if (departTimeInput) {
                    const now = new Date();
                    const year = now.getFullYear();
                    const month = String(now.getMonth() + 1).padStart(2, '0');
                    const day = String(now.getDate()).padStart(2, '0');
                    const hours = String(now.getHours()).padStart(2, '0');
                    const minutes = String(now.getMinutes()).padStart(2, '0');
                    
                    const formattedDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;
                    departTimeInput.value = formattedDateTime;
                }
                
                // Form validation
                const createTripForm = document.getElementById('createTripForm');
                if (createTripForm) {
                    createTripForm.addEventListener('submit', function(event) {
                        const tripType = document.getElementById('trip_type').value;
                        const departFacility = document.getElementById('depart_facility').value;
                        const arriveFacility = document.getElementById('arrive_facility').value;
                        const assignedEmployee = document.getElementById('assigned_employee').value;
                        
                        // Set the vehicle ID based on trip type before form submission
                        if (tripType === 'Road') {
                            vehicleIdInput.value = roadVehicleSelect.value;
                        } else if (tripType === 'Air') {
                            vehicleIdInput.value = airVehicleSelect.value;
                        } else if (tripType === 'Delivery') {
                            vehicleIdInput.value = deliveryVehicleSelect.value;
                        }
                        
                        if (!assignedEmployee) {
                            alert('Please select an employee to assign to this trip.');
                            event.preventDefault();
                            return false;
                        }
                        
                        if (departFacility === arriveFacility && tripType !== 'Delivery') {
                            alert('Departure and arrival facilities cannot be the same.');
                            event.preventDefault();
                            return false;
                        }
                        
                        if (tripType === 'Air') {
                            const flightNumber = document.getElementById('flight_number').value;
                            if (!flightNumber) {
                                alert('Flight number is required for air trips.');
                                event.preventDefault();
                                return false;
                            }
                        }
                        
                        return true;
                    });
                }
                
                // Add validation for package form
                const addPackageForm = document.getElementById('addPackageForm');
                if (addPackageForm) {
                    addPackageForm.addEventListener('submit', function(event) {
                        const tripId = document.getElementById('trip_id').value;
                        const checked = document.querySelectorAll('.package-cb:checked').length;
                        
                        if (!tripId) {
                            alert('Please select a trip.');
                            event.preventDefault();
                            return false;
                        }
                        
                        if (checked === 0) {
                            alert('Please select at least one package.');
                            event.preventDefault();
                            return false;
                        }
                        
                        return true;
                    });
                }
                
                // Select all / clear packages
                const selectAllPackages = document.getElementById('select_all_packages');
                if (selectAllPackages) {
                    selectAllPackages.addEventListener('change', function() {
                        document.querySelectorAll('.package-cb').forEach(function(cb) {
                            cb.checked = selectAllPackages.checked;
                        });
                        updatePackageSelectLabel();
                    });
                }
                
                // Package dropdown: show/hide on search box click
                const packageSearchBox = document.getElementById('package_search_box');
                const packageDropdown = document.getElementById('package_dropdown');
                const packageWrapper = document.getElementById('package_select_wrapper');
                const packageLabel = document.getElementById('package_search_label');
                const packageCount = document.getElementById('package_selected_count');
                const packageChevron = document.getElementById('package_chevron');
                
                function updatePackageSelectLabel() {
                    const checked = document.querySelectorAll('.package-cb:checked');
                    const n = checked.length;
                    if (packageCount) {
                        if (n > 0) {
                            packageCount.textContent = n + ' selected';
                            packageCount.classList.remove('hidden');
                            if (packageLabel) packageLabel.classList.add('hidden');
                        } else {
                            packageCount.classList.add('hidden');
                            if (packageLabel) packageLabel.classList.remove('hidden');
                        }
                    }
                }
                
                function setPackageDropdownOpen(open) {
                    if (!packageDropdown) return;
                    if (open) {
                        packageDropdown.classList.remove('hidden');
                        if (packageSearchBox) packageSearchBox.setAttribute('aria-expanded', 'true');
                        if (packageChevron) packageChevron.style.transform = 'rotate(180deg)';
                    } else {
                        packageDropdown.classList.add('hidden');
                        if (packageSearchBox) packageSearchBox.setAttribute('aria-expanded', 'false');
                        if (packageChevron) packageChevron.style.transform = '';
                    }
                }
                
                if (packageSearchBox && packageDropdown) {
                    packageSearchBox.addEventListener('click', function(e) {
                        e.preventDefault();
                        const isOpen = !packageDropdown.classList.contains('hidden');
                        setPackageDropdownOpen(!isOpen);
                    });
                    document.querySelectorAll('.package-cb').forEach(function(cb) {
                        cb.addEventListener('change', updatePackageSelectLabel);
                    });
                }
                
                document.addEventListener('click', function(e) {
                    if (packageWrapper && packageDropdown && !packageWrapper.contains(e.target)) {
                        setPackageDropdownOpen(false);
                    }
                });
            });
        </script>
    </div>
</body>
</html>