<?php
session_start();
require_once '../db_connect.php';
require_once '../functions.php';

// Check if user is logged in and has clerk or higher permissions
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || 
    ($_SESSION['role'] != 'Employee' && $_SESSION['role'] != 'Admin')) {
    header("Location: ../login.php");
    exit();
}

// Get employee info (for nav and welcome block)
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'Employee';
$stmt = $conn->prepare("SELECT e.first_name, e.last_name, e.role as employee_role, e.facility_id,
                        CONCAT(f.city, ', ', f.state, ' - ', f.type) as facility_name
                        FROM Employee e JOIN Facility f ON e.facility_id = f.facility_id WHERE e.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$employee = $stmt->get_result()->fetch_assoc();
$facility_id = $employee ? $employee['facility_id'] : null;
$name = $employee ? trim($employee['first_name'] . ' ' . $employee['last_name']) : 'Employee';
if ($employee) {
    $employee['role'] = $employee['employee_role'];
}
$unread_admin_messages = 0;
if ($role !== 'Admin' && $employee) {
    $msg_stmt = $conn->prepare("SELECT COUNT(*) as count FROM Admin_Messages WHERE sender_id = ? AND status = 'Replied'");
    $msg_stmt->bind_param("i", $user_id);
    $msg_stmt->execute();
    $unread_admin_messages = (int)($msg_stmt->get_result()->fetch_assoc()['count'] ?? 0);
}

$employee_id = $user_id;
$locations = [];
if ($role == 'Admin') {
    $loc_q = $conn->query("SELECT facility_id, CONCAT(city, ', ', state, ' - ', type) as location_name FROM Facility ORDER BY state, city");
    while ($row = $loc_q->fetch_assoc()) { $locations[] = $row; }
}

// Initialize variables (used by search and after bulk process)
$tracking_number = '';
$status = '';
$date_from = '';
$date_to = '';
$results = [];
$searched = false;

// Helper: run search with current $tracking_number, $status, $date_from, $date_to and set $results, $searched. $limit default 100.
function run_package_search($conn, $facility_id, &$results, &$searched, $tracking_number, $status, $date_from, $date_to, $limit = 100) {
    $query = "SELECT p.*,
             CONCAT(c.first_name, ' ', c.last_name) as sender_name,
             CONCAT(r.first_name, ' ', r.last_name) as recipient_name
             FROM Package p
             LEFT JOIN Customer c ON p.sender_id = c.user_id
             LEFT JOIN Customer r ON p.receiver_id = r.user_id
             WHERE p.facility_id = ?";
    $params = [$facility_id];
    $types = "i";
    if (!empty($tracking_number)) { $query .= " AND p.tracking_number LIKE ?"; $params[] = "%$tracking_number%"; $types .= "s"; }
    if (!empty($status)) { $query .= " AND p.status = ?"; $params[] = $status; $types .= "s"; }
    if (!empty($date_from)) { $query .= " AND DATE(p.timestamp_created) >= ?"; $params[] = $date_from; $types .= "s"; }
    if (!empty($date_to)) { $query .= " AND DATE(p.timestamp_created) <= ?"; $params[] = $date_to; $types .= "s"; }
    $limit = max(1, min(500, (int) $limit));
    $query .= " ORDER BY p.timestamp_created DESC LIMIT " . $limit;
    $stmt = $conn->prepare($query);
    if ($stmt && $stmt->bind_param($types, ...$params) && $stmt->execute()) {
        $results = $stmt->get_result();
        $searched = true;
    }
}

// Process bulk (multiple packages at once)
$bulk_result = '';
$bulk_error = false;
$bulk_processed = 0;
$bulk_skipped = 0;
$bulk_errors = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_selected']) && $employee) {
    $tracking_list = isset($_POST['tracking_numbers']) && is_array($_POST['tracking_numbers'])
        ? array_filter(array_map('trim', $_POST['tracking_numbers']))
        : [];
    $tracking_list = array_slice(array_unique($tracking_list), 0, 50); // max 50 per request
    $processed = 0;
    $skipped = 0;
    $errors = 0;
    $location = $employee['facility_name'] ?? '';
    $bulk_action = $_POST['bulk_action'] ?? 'Scanning';
    $bulk_status = $_POST['bulk_status'] ?? 'Processed';
    foreach ($tracking_list as $tn) {
        if ($tn === '') continue;
        $pkg = $conn->prepare("SELECT p.tracking_number, p.status, p.facility_id FROM Package p WHERE p.tracking_number = ? AND p.facility_id = ?");
        $pkg->bind_param("si", $tn, $facility_id);
        $pkg->execute();
        $pkg_row = $pkg->get_result()->fetch_assoc();
        if (!$pkg_row) {
            $errors++;
            continue;
        }
        $action = $bulk_action;
        $status_val = $bulk_status;
        if ($role !== 'Admin') {
            $emp_role = $employee['employee_role'] ?? 'Clerk';
            switch ($emp_role) {
                case 'Clerk': $action = 'Scanning'; $status_val = 'Processed'; break;
                case 'Driver':
                    $status_val = ($pkg_row['status'] == 'Out for Delivery') ? 'Delivered' : 'Out for Delivery';
                    $action = ($pkg_row['status'] == 'Out for Delivery') ? 'Delivery' : 'Loading';
                    break;
                case 'Sorting Staff': $action = 'Sorting'; $status_val = 'Processed'; break;
                case 'Pilot': $action = 'Loading'; $status_val = 'On Flight'; break;
                default: $action = 'Scanning'; $status_val = 'Processed';
            }
        }
        // For Clerk/Sorting: only skip when package is already past "Processing" (e.g. Processed, In Transit, etc.)
        $is_dup = false;
        if (in_array($employee['employee_role'] ?? '', ['Clerk', 'Sorting Staff']) && in_array($action, ['Scanning', 'Sorting'])) {
            if (strtolower(trim($pkg_row['status'] ?? '')) !== 'processing') {
                $is_dup = true;
                $skipped++;
            }
        }
        if (!$is_dup) {
            $ins = $conn->prepare("INSERT INTO Tracking_History (tracking_number, location, status, employee_id, action, timestamp, facility_id) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, ?)");
            $ins->bind_param("sssisi", $tn, $location, $status_val, $employee_id, $action, $employee['facility_id']);
            if ($ins->execute()) {
                $up = $conn->prepare("UPDATE Package SET status = ? WHERE tracking_number = ?");
                $up->bind_param("ss", $status_val, $tn);
                $up->execute();
                logAudit($conn, 'PACKAGE_SCANNED', 'Package', $tn,
                    "Bulk scan — action: {$action}, new status: {$status_val}",
                    $employee['facility_id'] ?? null,
                    ['status' => $pkg_row['status']],
                    ['status' => $status_val, 'action' => $action]
                );
                $processed++;
            } else {
                $errors++;
            }
        }
    }
    $bulk_processed = $processed;
    $bulk_skipped = $skipped;
    $bulk_errors = $errors;
    $bulk_result = empty($tracking_list) ? 'No packages selected.' : '';
    $bulk_error = ($processed === 0 && $errors > 0) || ($processed === 0 && $skipped === 0 && empty($tracking_list));
    $tracking_number = isset($_POST['preserve_tracking']) ? trim((string) $_POST['preserve_tracking']) : '';
    $status = isset($_POST['preserve_status']) ? trim((string) $_POST['preserve_status']) : '';
    $date_from = isset($_POST['preserve_date_from']) ? trim((string) $_POST['preserve_date_from']) : '';
    $date_to = isset($_POST['preserve_date_to']) ? trim((string) $_POST['preserve_date_to']) : '';
    run_package_search($conn, $facility_id, $results, $searched, $tracking_number, $status, $date_from, $date_to);
}

// Process search form (only when not a bulk POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['process_selected'])) {
    $tracking_number = $_POST['tracking_number'] ?? '';
    $status = $_POST['status'] ?? '';
    $date_from = $_POST['date_from'] ?? '';
    $date_to = $_POST['date_to'] ?? '';
    run_package_search($conn, $facility_id, $results, $searched, $tracking_number, $status, $date_from, $date_to, 100);
}

// Default: pre-populate with last 20 packages at this facility (no search needed)
if (!($searched) && $facility_id !== null && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    run_package_search($conn, $facility_id, $results, $searched, '', '', '', '', 20);
}

// Get all possible package statuses for the dropdown
$status_query = "SELECT DISTINCT status FROM Package ORDER BY status";
$status_result = $conn->query($status_query);
$statuses = [];
while ($row = $status_result->fetch_assoc()) {
    $statuses[] = $row['status'];
}

// Recent scans (for combined page)
$recent_scans = null;
if ($employee) {
    $recent_q = "SELECT p.tracking_number, th.location, th.status, th.action, th.timestamp,
        s.first_name as sender_first, s.last_name as sender_last,
        r.first_name as receiver_first, r.last_name as receiver_last
        FROM Package p
        LEFT JOIN (SELECT th1.* FROM Tracking_History th1
            INNER JOIN (SELECT tracking_number, MAX(timestamp) as latest_ts FROM Tracking_History GROUP BY tracking_number) th2
            ON th1.tracking_number = th2.tracking_number AND th1.timestamp = th2.latest_ts) th ON p.tracking_number = th.tracking_number
        LEFT JOIN Customer s ON p.sender_id = s.user_id
        LEFT JOIN Customer r ON p.receiver_id = r.user_id
        ORDER BY COALESCE(th.timestamp, p.timestamp_created) DESC LIMIT 10";
    $rs = $conn->query($recent_q);
    $recent_scans = ($rs instanceof mysqli_result) ? $rs : null;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Packages - Search &amp; Scan</title>
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
        #bulkToast {
            position: fixed;
            top: 80px;
            right: 24px;
            z-index: 9999;
            max-width: 420px;
            opacity: 1;
            transition: opacity 0.5s ease;
        }
        #bulkToast.toast-fade { opacity: 0; pointer-events: none; }
    </style>
</head>
<body class="bg-gray-50">
<?php $emp_base = '../'; include '../_employee_nav.php'; ?>

    <div class="container mx-auto px-4 py-8 max-w-6xl">
        <header class="mb-6">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center">
                <h1 class="text-3xl font-bold section-title">Packages — Search &amp; Scan</h1>
                <div class="mt-4 md:mt-0 bg-white shadow-sm p-4 rounded-lg">
                    <p class="mb-1"><span class="font-semibold">Welcome,</span> <?= htmlspecialchars($name) ?> | <span class="font-medium"><?= htmlspecialchars(isset($employee['role']) ? $employee['role'] : $role) ?></span></p>
                    <p><span class="font-semibold">Facility:</span> <?= htmlspecialchars(isset($employee['facility_name']) ? $employee['facility_name'] : '—') ?></p>
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
                <li><a href="../employee/contact_admin.php" class="text-gray-700 hover:text-[#DA291C] flex items-center">Contact Admin<?php if ($unread_admin_messages > 0): ?> <span class="ml-2 bg-red-600 text-white text-xs px-2 py-1 rounded-full"><?= $unread_admin_messages ?></span><?php endif; ?></a></li>
                <?php endif; ?>
                <?php if (!empty($employee['role']) && $employee['role'] == 'Clerk'): ?>
                <li><a href="new_package.php" class="text-gray-700 hover:text-[#DA291C]">Create Shipment</a></li>
                <li><a href="../shop/inventory_management.php" class="text-gray-700 hover:text-[#DA291C]">Inventory Management</a></li>
                <?php endif; ?>
                <?php if (!empty($employee['role']) && $employee['role'] == 'Manager'): ?>
                <li><a href="../shop/inventory_management.php" class="text-gray-700 hover:text-[#DA291C]">Inventory Management</a></li>
                <li><a href="../reports/index.php" class="text-gray-700 hover:text-[#DA291C]">Reports</a></li>
                <li><a href="../trips/manage_trips.php" class="text-gray-700 hover:text-[#DA291C]">Manage Trips</a></li>
                <?php endif; ?>
                <li><a href="search.php" class="text-gray-700 hover:text-[#DA291C] border-b-2 border-[#DA291C] pb-1 font-medium">Package Search &amp; Scan</a></li>
                <?php if (!empty($employee['role']) && $employee['role'] == 'Clerk'): ?>
                <li><a href="awaiting_pickup.php" class="text-gray-700 hover:text-[#DA291C]">Pickup</a></li>
                <?php endif; ?>
                <?php if ($role != 'Admin'): ?>
                <li><a href="../edit_profile.php" class="text-gray-700 hover:text-[#DA291C]">Edit Profile</a></li>
                <?php endif; ?>
            </ul>
        </nav>

        <!-- Search Form -->
        <section class="bg-white p-6 rounded-lg shadow-md mb-6">
            <h2 class="text-xl font-semibold mb-4">Search Packages</h2>
            <form method="POST" action="">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                    <div>
                        <label for="tracking_number" class="block text-sm font-medium text-gray-700 mb-1">Tracking Number</label>
                        <input type="text" id="tracking_number" name="tracking_number" 
                               value="<?= htmlspecialchars($tracking_number) ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select id="status" name="status" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">All Statuses</option>
                            <?php foreach ($statuses as $status_option): ?>
                                <option value="<?= htmlspecialchars($status_option) ?>" 
                                        <?= $status === $status_option ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($status_option) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                        <input type="date" id="date_from" name="date_from" 
                               value="<?= htmlspecialchars($date_from) ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                        <input type="date" id="date_to" name="date_to" 
                               value="<?= htmlspecialchars($date_to) ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                
                <div class="flex justify-end">
                    <button type="submit" class="action-btn text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-search mr-2"></i> Search
                    </button>
                </div>
            </form>
        </section>

        <?php if ($bulk_result !== '' || $bulk_processed > 0 || $bulk_skipped > 0 || $bulk_errors > 0): ?>
        <div id="bulkToast" class="shadow-lg rounded-lg p-4 bg-white border border-gray-200 flex flex-col gap-2">
            <?php if (!empty($bulk_result)): ?>
            <p class="font-semibold text-sm text-amber-700"><i class="fas fa-info-circle mr-2"></i><?= htmlspecialchars($bulk_result) ?></p>
            <?php endif; ?>
            <?php if ($bulk_processed > 0): ?>
            <p class="font-semibold text-sm text-green-700"><i class="fas fa-check-circle mr-2"></i><?= (int)$bulk_processed ?> processed</p>
            <?php endif; ?>
            <?php if ($bulk_skipped > 0): ?>
            <p class="font-semibold text-sm text-red-700"><i class="fas fa-minus-circle mr-2"></i><?= (int)$bulk_skipped ?> skipped (already processed)</p>
            <?php endif; ?>
            <?php if ($bulk_errors > 0): ?>
            <p class="font-semibold text-sm text-red-700"><i class="fas fa-exclamation-circle mr-2"></i><?= (int)$bulk_errors ?> error(s)</p>
            <?php endif; ?>
        </div>
        <script>
        (function(){
            var el = document.getElementById('bulkToast');
            if (!el) return;
            setTimeout(function(){
                el.classList.add('toast-fade');
                setTimeout(function(){ el.style.display = 'none'; }, 500);
            }, 3000);
        })();
        </script>
        <?php endif; ?>

        <!-- Search Results -->
        <section class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-semibold mb-4">Search Results</h2>
            
            <?php if ($searched && is_object($results) && $results->num_rows === 0): ?>
                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-yellow-700">
                                No packages found matching your search criteria.
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($searched && is_object($results) && $results->num_rows > 0 && $employee): ?>
                <form method="post" action="" id="bulkProcessForm">
                    <input type="hidden" name="process_selected" value="1">
                    <input type="hidden" name="preserve_tracking" value="<?= htmlspecialchars($tracking_number) ?>">
                    <input type="hidden" name="preserve_status" value="<?= htmlspecialchars($status) ?>">
                    <input type="hidden" name="preserve_date_from" value="<?= htmlspecialchars($date_from) ?>">
                    <input type="hidden" name="preserve_date_to" value="<?= htmlspecialchars($date_to) ?>">
                    <div class="flex flex-wrap items-center gap-3 mb-4 p-3 bg-gray-50 rounded-lg border border-gray-200">
                        <?php if ($role == 'Admin'): ?>
                        <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                            Action
                            <select name="bulk_action" class="px-2 py-1.5 border border-gray-300 rounded text-sm">
                                <option value="Scanning">Scanning</option>
                                <option value="Sorting">Sorting</option>
                                <option value="Loading">Loading</option>
                                <option value="Delivery">Delivery</option>
                            </select>
                        </label>
                        <label class="flex items-center gap-2 text-sm font-medium text-gray-700">
                            Status
                            <select name="bulk_status" class="px-2 py-1.5 border border-gray-300 rounded text-sm">
                                <option value="Processing">Processing</option>
                                <option value="Processed">Processed</option>
                                <option value="In Transit">In Transit</option>
                                <option value="Out for Delivery">Out for Delivery</option>
                                <option value="Delivered">Delivered</option>
                            </select>
                        </label>
                        <?php endif; ?>
                        <button type="submit" id="bulkProcessBtn" class="action-btn text-white px-4 py-2 rounded text-sm disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                            <i class="fas fa-barcode mr-2"></i> Process selected (<span id="selectedCount">0</span>)
                        </button>
                    </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="px-4 py-2 text-left w-10">
                                    <label class="cursor-pointer flex items-center gap-1">
                                        <input type="checkbox" id="selectAll" class="rounded border-gray-300 text-[#004B87] focus:ring-[#004B87]" title="Select all on this page">
                                    </label>
                                </th>
                                <th class="px-4 py-2 text-left">Tracking #</th>
                                <th class="px-4 py-2 text-left">Sender</th>
                                <th class="px-4 py-2 text-left">Recipient</th>
                                <th class="px-4 py-2 text-left">Status</th>
                                <th class="px-4 py-2 text-left">Created</th>
                                <th class="px-4 py-2 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($package = $results->fetch_assoc()): 
                                $status_class = '';
                                switch($package['status']) {
                                    case 'Delivered': $status_class = 'bg-green-100 text-green-800'; break;
                                    case 'Processed': $status_class = 'bg-teal-100 text-teal-800'; break;
                                    case 'In Transit': $status_class = 'bg-blue-100 text-blue-800'; break;
                                    case 'Processing': $status_class = 'bg-yellow-100 text-yellow-800'; break;
                                    case 'Out for Delivery': $status_class = 'bg-purple-100 text-purple-800'; break;
                                    case 'Ready for Pickup': $status_class = 'bg-indigo-100 text-indigo-800'; break;
                                    default: $status_class = 'bg-gray-100 text-gray-800';
                                }
                            ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="px-4 py-2">
                                    <input type="checkbox" name="tracking_numbers[]" value="<?= htmlspecialchars($package['tracking_number']) ?>" class="row-check rounded border-gray-300 text-[#004B87] focus:ring-[#004B87]">
                                </td>
                                <td class="px-4 py-2"><?= htmlspecialchars($package['tracking_number']) ?></td>
                                <td class="px-4 py-2"><?= htmlspecialchars($package['sender_name']) ?></td>
                                <td class="px-4 py-2"><?= htmlspecialchars($package['recipient_name']) ?></td>
                                <td class="px-4 py-2">
                                    <span class="<?= $status_class ?> px-2 py-1 rounded-full text-xs">
                                        <?= htmlspecialchars($package['status']) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-2"><?= date('M d, Y H:i', strtotime($package['timestamp_created'])) ?></td>
                                <td class="px-4 py-2">
                                    <a href="track.php?tracking=<?= htmlspecialchars($package['tracking_number']) ?>" 
                                       class="text-blue-600 hover:text-blue-800">
                                        <i class="fas fa-search"></i> Details
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                </form>
                <script>
                (function(){
                    var form = document.getElementById('bulkProcessForm');
                    var selectAll = document.getElementById('selectAll');
                    var rowChecks = form ? form.querySelectorAll('.row-check') : [];
                    var btn = document.getElementById('bulkProcessBtn');
                    var countEl = document.getElementById('selectedCount');
                    function updateCount(){
                        var n = 0;
                        for (var i = 0; i < rowChecks.length; i++) if (rowChecks[i].checked) n++;
                        if (countEl) countEl.textContent = n;
                        if (btn) { btn.disabled = n === 0; }
                        if (selectAll) selectAll.checked = n > 0 && n === rowChecks.length;
                        if (selectAll) selectAll.indeterminate = n > 0 && n < rowChecks.length;
                    }
                    if (selectAll) selectAll.addEventListener('change', function(){ for (var i = 0; i < rowChecks.length; i++) rowChecks[i].checked = selectAll.checked; updateCount(); });
                    for (var i = 0; i < rowChecks.length; i++) rowChecks[i].addEventListener('change', updateCount);
                    updateCount();
                })();
                </script>
            <?php elseif ($searched && is_object($results) && $results->num_rows > 0): ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="px-4 py-2 text-left">Tracking #</th>
                                <th class="px-4 py-2 text-left">Sender</th>
                                <th class="px-4 py-2 text-left">Recipient</th>
                                <th class="px-4 py-2 text-left">Status</th>
                                <th class="px-4 py-2 text-left">Created</th>
                                <th class="px-4 py-2 text-left">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $results->data_seek(0);
                            while ($package = $results->fetch_assoc()): 
                                $status_class = '';
                                switch($package['status']) {
                                    case 'Delivered': $status_class = 'bg-green-100 text-green-800'; break;
                                    case 'Processed': $status_class = 'bg-teal-100 text-teal-800'; break;
                                    case 'In Transit': $status_class = 'bg-blue-100 text-blue-800'; break;
                                    case 'Processing': $status_class = 'bg-yellow-100 text-yellow-800'; break;
                                    case 'Out for Delivery': $status_class = 'bg-purple-100 text-purple-800'; break;
                                    case 'Ready for Pickup': $status_class = 'bg-indigo-100 text-indigo-800'; break;
                                    default: $status_class = 'bg-gray-100 text-gray-800';
                                }
                            ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="px-4 py-2"><?= htmlspecialchars($package['tracking_number']) ?></td>
                                <td class="px-4 py-2"><?= htmlspecialchars($package['sender_name']) ?></td>
                                <td class="px-4 py-2"><?= htmlspecialchars($package['recipient_name']) ?></td>
                                <td class="px-4 py-2">
                                    <span class="<?= $status_class ?> px-2 py-1 rounded-full text-xs">
                                        <?= htmlspecialchars($package['status']) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-2"><?= date('M d, Y H:i', strtotime($package['timestamp_created'])) ?></td>
                                <td class="px-4 py-2">
                                    <a href="track.php?tracking=<?= htmlspecialchars($package['tracking_number']) ?>" 
                                       class="text-blue-600 hover:text-blue-800">
                                        <i class="fas fa-search"></i> Details
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php elseif (!$searched): ?>
                <p class="text-gray-500 text-center py-4">Use the search form above to find packages.</p>
            <?php endif; ?>
        </section>

        <?php if ($employee && $recent_scans): ?>
        <!-- Recent Scans -->
        <section class="bg-white p-6 rounded-lg shadow-md mt-6">
            <h2 class="text-xl font-semibold section-title mb-4">Recent Scans</h2>
            <?php if ($recent_scans->num_rows > 0): ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-100 text-left text-sm font-medium text-gray-700">
                            <th class="px-4 py-3">Tracking #</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Action</th>
                            <th class="px-4 py-3">From / To</th>
                            <th class="px-4 py-3">Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($scan = $recent_scans->fetch_assoc()): ?>
                        <tr class="border-b border-gray-100 hover:bg-gray-50">
                            <td class="px-4 py-3"><?= htmlspecialchars($scan['tracking_number']) ?></td>
                            <td class="px-4 py-3"><?= htmlspecialchars($scan['status'] ?? '—') ?></td>
                            <td class="px-4 py-3"><?= htmlspecialchars($scan['action'] ?? '—') ?></td>
                            <td class="px-4 py-3"><?= htmlspecialchars(($scan['sender_first'] ?? '') . ' ' . ($scan['sender_last'] ?? '') . ' → ' . ($scan['receiver_first'] ?? '') . ' ' . ($scan['receiver_last'] ?? '')) ?></td>
                            <td class="px-4 py-3 text-gray-600"><?= $scan['timestamp'] ? date('M j, g:i A', strtotime($scan['timestamp'])) : '—' ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-gray-500 text-sm">No recent scans.</p>
            <?php endif; ?>
        </section>
        <?php endif; ?>
    </div>
</body>
</html> 