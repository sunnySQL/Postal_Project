<?php
// Display all PHP errors for debugging
try {
    session_start();
    require_once 'db_connect.php';
    require_once 'functions.php';

    // Check if user is logged in and is an employee
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || 
        ($_SESSION['role'] !== 'Employee' && $_SESSION['role'] !== 'Admin')) {
        header("Location: login.php");
        exit();
    }

    $user_id = $_SESSION['user_id'];
    $role = $_SESSION['role'];

    // Get employee information
    $stmt = $conn->prepare("SELECT e.*, f.facility_id, f.city, 
                           CONCAT(f.type, ' ', f.city) as facility_name, f.type 
                          FROM Employee e 
                          JOIN Facility f ON e.facility_id = f.facility_id 
                          WHERE e.user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $employee = $stmt->get_result()->fetch_assoc();

    if (!$employee) {
        throw new Exception("Employee data not found. Please check the user account.");
    }

    $facility_id = $employee['facility_id'];
    $facility_name = $employee['facility_name'];
    $facility_city = $employee['city'];
    $facility_type = $employee['type'];

    // Debug information
    echo "<!-- Debug Info: 
    Facility ID: " . $facility_id . "
    Facility Name: " . $facility_name . "
    Facility City: " . $facility_city . "
    Facility Type: " . $facility_type . "
    -->";

    // Get all employees at this facility for assignment dropdown
    $emp_query = "SELECT user_id, first_name, last_name, role 
                  FROM Employee 
                  WHERE facility_id = ? 
                  ORDER BY role, last_name, first_name";
    $stmt = $conn->prepare($emp_query);
    $stmt->bind_param("i", $facility_id);
    $stmt->execute();
    $facility_employees = $stmt->get_result();

    // Handle status filter
    $status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
    $status_where_clause = "WHERE p.facility_id = ?";

    if ($status_filter != 'all') {
        $status_where_clause .= " AND st.status = ?";
    }

    // Handle employee assignment changes if form is submitted
    $success_message = '';
    $error_message = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_ticket'])) {
        $ticket_id = $_POST['ticket_id'] ?? 0;
        $employee_id = $_POST['employee_id'] ?? 0;
        
        if ($ticket_id > 0 && $employee_id > 0) {
            $update_query = "UPDATE Support_Ticket SET assigned_employee_id = ? WHERE ticket_id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("ii", $employee_id, $ticket_id);
            
            if ($stmt->execute()) {
                $success_message = "Ticket #$ticket_id has been assigned successfully.";
            } else {
                $error_message = "Error assigning ticket: " . $conn->error;
            }
        }
    }

    // Pagination setup
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $records_per_page = 15;
    $offset = ($page - 1) * $records_per_page;

    try {
        // Query to get tickets for this facility
        $query = "
            SELECT st.ticket_id, st.issue_type, st.status, st.assigned_employee_id,
                   p.tracking_number, p.status as package_status, p.timestamp_created,
                   c.first_name as customer_first_name, c.last_name as customer_last_name,
                   e.first_name as employee_first_name, e.last_name as employee_last_name,
                   e.role as employee_role
            FROM Support_Ticket st
            JOIN Package p ON st.package_id = p.tracking_number
            LEFT JOIN Customer c ON st.user_id = c.user_id
            LEFT JOIN Employee e ON st.assigned_employee_id = e.user_id
            $status_where_clause
            ORDER BY st.ticket_id DESC
            LIMIT ? OFFSET ?
        ";
        
        // Prepare and execute the query
        $stmt = $conn->prepare($query);
        
        if ($status_filter != 'all') {
            $stmt->bind_param("isii", $facility_id, $status_filter, $records_per_page, $offset);
        } else {
            $stmt->bind_param("iii", $facility_id, $records_per_page, $offset);
        }
        
        // Add debug info
        echo "<!-- Debug SQL Query: " . str_replace('?', '%s', $query) . " -->";
        echo "<!-- Debug SQL Params: " . json_encode([$facility_id, $status_filter, $records_per_page, $offset]) . " -->";
        
        $stmt->execute();
        $tickets = $stmt->get_result();
        
        // Count total number of tickets for pagination
        $count_query = "SELECT COUNT(*) as total 
                        FROM Support_Ticket st 
                        JOIN Package p ON st.package_id = p.tracking_number
                        $status_where_clause";
                        
        $count_stmt = $conn->prepare($count_query);
        
        if ($status_filter != 'all') {
            $count_stmt->bind_param("is", $facility_id, $status_filter);
        } else {
            $count_stmt->bind_param("i", $facility_id);
        }
        
        // Add debug info for count query
        echo "<!-- Debug Count SQL: " . str_replace('?', '%s', $count_query) . " -->";
        
        $count_stmt->execute();
        $total_records = $count_stmt->get_result()->fetch_assoc()['total'];
        $total_pages = ceil($total_records / $records_per_page);
    } catch (Exception $e) {
        $error_message = "Database error: " . $e->getMessage();
        $tickets = false;
        $total_records = 0;
        $total_pages = 0;
    }
} catch (Exception $e) {
    // Capture any exceptions
    $error_message = "Error: " . $e->getMessage();
    $facility_id = 0;
    $facility_name = "Unknown";
    $facility_city = "Unknown";
    $facility_type = "Unknown";
    $tickets = false;
    $total_records = 0;
    $total_pages = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facility Support Tickets | POSTAL PRO</title>
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
<body class="bg-gray-50">
    <nav class="top-nav fixed w-full top-0 left-0 z-50 shadow-md px-4 py-4">
        <div class="container mx-auto flex justify-between items-center">
            <a href="index.php" class="text-white font-bold text-2xl">POSTAL PRO</a>
            <ul class="flex space-x-6">
                <li><a href="index.php" class="text-white hover:text-gray-200">Home</a></li>
                <li><a href="employee_dashboard.php" class="text-white hover:text-gray-200">Dashboard</a></li>
                <li><a href="logout.php" class="text-white hover:text-gray-200">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8 max-w-6xl mt-20">
        <header class="mb-6 flex flex-col md:flex-row md:justify-between md:items-center">
            <div>
                <h1 class="text-3xl font-bold section-title mb-2">Facility Support Tickets</h1>
                <p class="text-gray-600"><?= htmlspecialchars($facility_type) ?> - <?= htmlspecialchars($facility_name) ?>, <?= htmlspecialchars($facility_city) ?></p>
            </div>
            <div class="flex space-x-2 mt-4 md:mt-0">
                <a href="employee_dashboard.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                </a>
            </div>
        </header>

        <?php if (!empty($success_message)): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
            <p><?= htmlspecialchars($success_message) ?></p>
        </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
            <p><?= htmlspecialchars($error_message) ?></p>
        </div>
        <?php endif; ?>

        <!-- Facility Info and Debug -->
        <div class="bg-white p-4 rounded-lg shadow-md mb-6">
            <h2 class="text-lg font-semibold mb-2">Facility Information</h2>
            <p><strong>Facility ID:</strong> <?= $facility_id ?></p>
            <p><strong>Facility Name:</strong> <?= htmlspecialchars($facility_name) ?></p>
            <p><strong>Facility Type:</strong> <?= htmlspecialchars($facility_type) ?></p>
            <p><strong>Facility City:</strong> <?= htmlspecialchars($facility_city) ?></p>
        </div>

        <!-- Filter Options -->
        <div class="bg-white p-4 rounded-lg shadow-md mb-6">
            <form action="" method="GET" class="flex flex-wrap gap-3 items-end">
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Ticket Status</label>
                    <select id="status" name="status" class="px-3 py-2 border border-gray-300 rounded-md">
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                        <option value="Open" <?= $status_filter === 'Open' ? 'selected' : '' ?>>Open</option>
                        <option value="In Progress" <?= $status_filter === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                        <option value="Resolved" <?= $status_filter === 'Resolved' ? 'selected' : '' ?>>Resolved</option>
                    </select>
                </div>
                
                <div>
                    <button type="submit" class="action-btn text-white px-4 py-2 rounded-md">Apply Filters</button>
                    <a href="facility_tickets.php" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-md inline-block ml-2">Reset</a>
                </div>
            </form>
        </div>

        <!-- Tickets Table -->
        <div class="bg-white p-4 rounded-lg shadow-md mb-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold section-title">Support Tickets</h2>
                <p class="text-sm text-gray-600">
                    Showing <?= min($total_records, 1 + $offset) ?> - 
                    <?= min($total_records, $offset + $records_per_page) ?> of <?= $total_records ?> tickets
                </p>
            </div>

            <?php if ($tickets && $tickets->num_rows > 0): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Issue Type</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Package</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assigned To</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php while ($ticket = $tickets->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 whitespace-nowrap">
                                #<?= htmlspecialchars($ticket['ticket_id']) ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <?php if (!empty($ticket['customer_first_name'])): ?>
                                    <?= htmlspecialchars($ticket['customer_first_name'] . ' ' . $ticket['customer_last_name']) ?>
                                <?php else: ?>
                                    <span class="text-gray-400">Unknown</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <?= htmlspecialchars($ticket['issue_type']) ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <?php if (!empty($ticket['tracking_number'])): ?>
                                    <a href="track_details.php?tracking=<?= htmlspecialchars($ticket['tracking_number']) ?>" 
                                       class="text-blue-600 hover:text-blue-900 hover:underline">
                                        <?= htmlspecialchars($ticket['tracking_number']) ?>
                                    </a>
                                    <?php if (!empty($ticket['package_status'])): ?>
                                        <span class="text-xs text-gray-500 block"><?= htmlspecialchars($ticket['package_status']) ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-gray-400">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <?php 
                                $status_color = '';
                                switch($ticket['status']) {
                                    case 'Open': $status_color = 'bg-green-100 text-green-800'; break;
                                    case 'In Progress': $status_color = 'bg-blue-100 text-blue-800'; break;
                                    case 'Resolved': $status_color = 'bg-gray-100 text-gray-800'; break;
                                    default: $status_color = 'bg-gray-100 text-gray-800';
                                }
                                ?>
                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?= $status_color ?>">
                                    <?= htmlspecialchars($ticket['status']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <?php if (!empty($ticket['employee_first_name'])): ?>
                                    <div>
                                        <?= htmlspecialchars($ticket['employee_first_name'] . ' ' . $ticket['employee_last_name']) ?>
                                        <div class="text-xs text-gray-500"><?= htmlspecialchars($ticket['employee_role']) ?></div>
                                    </div>
                                <?php else: ?>
                                    <span class="text-yellow-600">Unassigned</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                <a href="support/view_ticket.php?id=<?= $ticket['ticket_id'] ?>" 
                                   class="text-blue-600 hover:text-blue-900 mr-3 font-medium">View</a>
                                   
                                <button class="text-purple-600 hover:text-purple-900" 
                                        onclick="showAssignModal(<?= $ticket['ticket_id'] ?>, '<?= htmlspecialchars($ticket['customer_first_name'] . ' ' . $ticket['customer_last_name']) ?>', '<?= htmlspecialchars($ticket['issue_type']) ?>')">
                                    Assign
                                </button>
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
                    <a href="?page=<?= $page - 1 ?>&status=<?= urlencode($status_filter) ?>" 
                       class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                        <span class="sr-only">Previous</span>
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($start_page + 4, $total_pages);
                    
                    if ($start_page > 1): ?>
                    <a href="?page=1&status=<?= urlencode($status_filter) ?>" 
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
                    <a href="?page=<?= $i ?>&status=<?= urlencode($status_filter) ?>" 
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
                    <a href="?page=<?= $total_pages ?>&status=<?= urlencode($status_filter) ?>" 
                       class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                        <?= $total_pages ?>
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>&status=<?= urlencode($status_filter) ?>" 
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
                <i class="fas fa-ticket-alt text-4xl mb-3"></i>
                <p>No support tickets found for this facility</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Assignment Modal -->
    <div id="assignModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold section-title">Assign Ticket <span id="ticketId"></span></h3>
                <button onclick="hideAssignModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="mb-4 bg-gray-50 p-3 rounded-md">
                <p class="mb-1"><span class="font-medium">Customer:</span> <span id="customerName">-</span></p>
                <p><span class="font-medium">Issue Type:</span> <span id="issueType">-</span></p>
            </div>
            
            <form action="" method="POST">
                <input type="hidden" id="modal_ticket_id" name="ticket_id">
                
                <div class="mb-4">
                    <label for="employee_id" class="block text-sm font-medium text-gray-700 mb-1">Assign To:</label>
                    <select id="employee_id" name="employee_id" class="w-full px-3 py-2 border border-gray-300 rounded-md" required>
                        <option value="">Select an employee</option>
                        <?php if ($facility_employees && $facility_employees->num_rows > 0): ?>
                            <?php while ($emp = $facility_employees->fetch_assoc()): ?>
                                <option value="<?= $emp['user_id'] ?>">
                                    <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name'] . ' (' . $emp['role'] . ')') ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="flex justify-end">
                    <button type="button" onclick="hideAssignModal()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-md mr-2">
                        Cancel
                    </button>
                    <button type="submit" name="assign_ticket" class="action-btn text-white px-4 py-2 rounded-md">
                        Assign Ticket
                    </button>
                </div>
            </form>
        </div>
    </div>

    <footer class="text-center mt-10 py-6 bg-[#004B87] text-white rounded-lg mb-6 mx-4">
        <p>&copy; <?= date('Y') ?> Postal Service Management System</p>
    </footer>

    <!-- Fallback debug information in case the page is blank -->
    <div id="debug-info" style="display:none; position:fixed; bottom:0; right:0; background: #fff; border:1px solid #ccc; padding:20px; margin:20px; max-width:400px; z-index:9999;">
        <h3>Debug Information</h3>
        <pre><?php 
        echo "PHP Version: " . phpversion() . "\n";
        echo "Facility ID: " . ($facility_id ?? 'Not set') . "\n";
        echo "Error: " . ($error_message ?? 'None') . "\n";
        ?></pre>
        <button onclick="this.parentNode.style.display='none'" style="background:#f00;color:#fff;border:none;padding:5px 10px;">Close</button>
        <button onclick="this.parentNode.style.display='block'" style="background:#00f;color:#fff;border:none;padding:5px 10px;">Show</button>
    </div>
    <script>
        // If the page appears blank (only has script tags), show debug info
        if (document.body.children.length < 3) {
            document.getElementById('debug-info').style.display = 'block';
        }
        
        function showAssignModal(ticketId, customerName, issueType) {
            document.getElementById('modal_ticket_id').value = ticketId;
            document.getElementById('ticketId').textContent = '#' + ticketId;
            document.getElementById('customerName').textContent = customerName || 'Unknown';
            document.getElementById('issueType').textContent = issueType || '-';
            document.getElementById('assignModal').classList.remove('hidden');
        }
        
        function hideAssignModal() {
            document.getElementById('assignModal').classList.add('hidden');
        }
    </script>
</body>
</html> 