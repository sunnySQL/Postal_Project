<?php
// Enable full error reporting for debugging
session_start();
require_once 'db_connect.php';
require_once 'functions.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php?redirect=payment_history.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$name = $_SESSION['name'];
$error = '';
$success_message = '';
$debug_info = []; // For storing debug information

// Create Payment table if form submitted and user is admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_payment_table']) && $role === 'Admin') {
    try {
        // SQL to create Payment table
        $create_table_sql = "
        CREATE TABLE IF NOT EXISTS Payment (
            payment_id INT PRIMARY KEY AUTO_INCREMENT,
            invoice_number VARCHAR(50) NOT NULL,
            customer_id INT NOT NULL,
            package_id INT,
            amount DECIMAL(10,2) NOT NULL,
            payment_method VARCHAR(50) NOT NULL,
            transaction_status VARCHAR(20) NOT NULL,
            payment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            description TEXT,
            FOREIGN KEY (customer_id) REFERENCES Customer(user_id),
            FOREIGN KEY (package_id) REFERENCES Package(package_id) ON DELETE SET NULL
        )";
        
        if ($conn->query($create_table_sql)) {
            $success_message = "Payment table created successfully!";
            
            // Create sample payment data
            $sample_data = [
                [
                    'invoice' => 'INV-' . date('Ymd') . '-0001',
                    'amount' => 12.99,
                    'method' => 'Credit Card',
                    'status' => 'Completed'
                ],
                [
                    'invoice' => 'INV-' . date('Ymd') . '-0002',
                    'amount' => 16.95,
                    'method' => 'PayPal',
                    'status' => 'Completed'
                ],
                [
                    'invoice' => 'INV-' . date('Ymd') . '-0003',
                    'amount' => 8.50,
                    'method' => 'Bank Transfer',
                    'status' => 'Pending'
                ]
            ];
            
            // Get package IDs for this user if available
            $package_query = "SELECT package_id FROM Package WHERE sender_id = ? LIMIT 3";
            $pkg_stmt = $conn->prepare($package_query);
            $pkg_stmt->bind_param("i", $user_id);
            $pkg_stmt->execute();
            $pkg_result = $pkg_stmt->get_result();
            $package_ids = [];
            
            while ($row = $pkg_result->fetch_assoc()) {
                $package_ids[] = $row['package_id'];
            }
            
            // Insert sample data
            $insert_sql = "INSERT INTO Payment (invoice_number, customer_id, package_id, amount, payment_method, transaction_status, description) 
                           VALUES (?, ?, ?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            
            foreach ($sample_data as $index => $data) {
                $package_id = isset($package_ids[$index]) ? $package_ids[$index] : NULL;
                $description = "Payment for shipping services";
                
                $insert_stmt->bind_param("siidsss", 
                    $data['invoice'], 
                    $user_id, 
                    $package_id, 
                    $data['amount'], 
                    $data['method'], 
                    $data['status'], 
                    $description
                );
                $insert_stmt->execute();
            }
            
            $success_message .= " Sample payment data added.";
        } else {
            throw new Exception("Error creating Payment table: " . $conn->error);
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

try {
    // Pagination settings
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $per_page = 10;
    $offset = ($page - 1) * $per_page;

    // Filter settings
    $status_filter = isset($_GET['status']) ? $_GET['status'] : '';
    $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
    $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
    $search = isset($_GET['search']) ? $_GET['search'] : '';

    // Check if the required tables exist
    $shop_trans_check = $conn->query("SHOW TABLES LIKE 'Shop_Transaction'");
    $package_pay_check = $conn->query("SHOW TABLES LIKE 'Package_Payment'");
    
    if ($shop_trans_check->num_rows == 0 && $package_pay_check->num_rows == 0) {
        throw new Exception("Required payment tables do not exist in the database.");
    }
    
    // Combine data from Shop_Transaction and Package_Payment
    $queries = [];
    $all_params = [];
    $all_types = "";
    
    // Base query for Shop_Transaction
    if ($shop_trans_check->num_rows > 0) {
        // In our schema, the Shop_Transaction connects to Shop_Sale, and Shop_Sale connects to Items
        $items_check = $conn->query("SHOW TABLES LIKE 'Items'");
        $shop_sale_check = $conn->query("SHOW TABLES LIKE 'Shop_Sale'");
        
        // Basic query without item information
        $shop_query = "
            SELECT 
                st.transaction_id as payment_id,
                st.transaction_id as invoice_number,
                st.user_id as customer_id,
                NULL as package_id,
                st.total_amount as amount,
                st.payment_method,
                st.transaction_status,
                st.transaction_date as payment_date,
                'Shop purchase' as description,
                NULL as tracking_number
            FROM Shop_Transaction st
        ";
        
        // Only join the item tables if they exist
        if ($items_check->num_rows > 0 && $shop_sale_check->num_rows > 0) {
            $shop_query = "
                SELECT 
                    st.transaction_id as payment_id,
                    st.transaction_id as invoice_number,
                    st.user_id as customer_id,
                    NULL as package_id,
                    st.total_amount as amount,
                    st.payment_method,
                    st.transaction_status,
                    st.transaction_date as payment_date,
                    CONCAT('Shop purchase: ', i.name) as description,
                    NULL as tracking_number
                FROM Shop_Transaction st
                LEFT JOIN Shop_Sale ss ON st.transaction_id = ss.transaction_id
                LEFT JOIN Items i ON ss.item_id = i.item_id
            ";
        }
        
        $shop_query .= " WHERE st.user_id = ?";
        $queries[] = $shop_query;
        $all_params[] = $user_id;
        $all_types .= "i";
    }
    
    // Base query for Package_Payment
    if ($package_pay_check->num_rows > 0) {
        $package_query = "
            SELECT 
                pp.payment_id,
                pp.invoice_number,
                pp.user_id as customer_id,
                pp.package_id,
                pp.amount,
                pp.payment_method,
                pp.transaction_status,
                pp.payment_date,
                'Package shipping payment' as description,
                p.tracking_number
            FROM Package_Payment pp
            LEFT JOIN Package p ON pp.package_id = p.tracking_number
            WHERE pp.user_id = ?
        ";
        $queries[] = $package_query;
        $all_params[] = $user_id;
        $all_types .= "i";
    }
    
    // Add filters to each query
    foreach ($queries as $index => $query) {
        if (!empty($status_filter)) {
            if ($index == 0 && $shop_trans_check->num_rows > 0) {
                $queries[$index] .= " AND st.transaction_status = ?";
            } else if ($index == 1 || ($index == 0 && $shop_trans_check->num_rows == 0)) {
                $queries[$index] .= " AND pp.transaction_status = ?";
            }
            $all_params[] = $status_filter;
            $all_types .= "s";
        }

        if (!empty($date_from)) {
            if ($index == 0 && $shop_trans_check->num_rows > 0) {
                $queries[$index] .= " AND DATE(st.transaction_date) >= ?";
            } else if ($index == 1 || ($index == 0 && $shop_trans_check->num_rows == 0)) {
                $queries[$index] .= " AND DATE(pp.payment_date) >= ?";
            }
            $all_params[] = $date_from;
            $all_types .= "s";
        }

        if (!empty($date_to)) {
            if ($index == 0 && $shop_trans_check->num_rows > 0) {
                $queries[$index] .= " AND DATE(st.transaction_date) <= ?";
            } else if ($index == 1 || ($index == 0 && $shop_trans_check->num_rows == 0)) {
                $queries[$index] .= " AND DATE(pp.payment_date) <= ?";
            }
            $all_params[] = $date_to;
            $all_types .= "s";
        }

        if (!empty($search)) {
            if ($index == 0 && $shop_trans_check->num_rows > 0) {
                $items_check = $conn->query("SHOW TABLES LIKE 'Items'");
                if ($items_check->num_rows > 0) {
                    $queries[$index] .= " AND (CAST(st.transaction_id AS CHAR) LIKE ? OR i.name LIKE ?)";
                } else {
                    $queries[$index] .= " AND CAST(st.transaction_id AS CHAR) LIKE ?";
                    $all_params[] = "%$search%";
                    $all_types .= "s";
                    continue; // Skip adding the extra parameter
                }
                $search_param = "%$search%";
                $all_params[] = $search_param;
                $all_params[] = $search_param;
                $all_types .= "ss";
            } else if ($index == 1 || ($index == 0 && $shop_trans_check->num_rows == 0)) {
                $queries[$index] .= " AND (pp.invoice_number LIKE ? OR p.tracking_number LIKE ?)";
                $search_param = "%$search%";
                $all_params[] = $search_param;
                $all_params[] = $search_param;
                $all_types .= "ss";
            }
        }
    }
    
    // Create UNION query if both tables exist
    if (count($queries) > 1) {
        $union_query = "(" . $queries[0] . ") UNION ALL (" . $queries[1] . ") ORDER BY payment_date DESC LIMIT ?, ?";
    } else {
        $union_query = $queries[0] . " ORDER BY payment_date DESC LIMIT ?, ?";
    }
    
    // Add pagination parameters
    $all_params[] = $offset;
    $all_params[] = $per_page;
    $all_types .= "ii";
    
    // Execute the query
    $stmt = $conn->prepare($union_query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error . " for query: " . $union_query);
    }
    
    $stmt->bind_param($all_types, ...$all_params);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $payments = $stmt->get_result();
    $debug_info[] = "Payment query executed successfully";

    // Count total payments for pagination
    // We need to remove LIMIT clause for counting
    $count_params = $all_params;
    array_pop($count_params); // Remove per_page
    array_pop($count_params); // Remove offset
    $count_types = substr($all_types, 0, -2); // Remove ii
    
    if (count($queries) > 1) {
        $count_query = "SELECT COUNT(*) as total FROM ((" . $queries[0] . ") UNION ALL (" . $queries[1] . ")) as combined_payments";
    } else {
        $count_query = "SELECT COUNT(*) as total FROM (" . $queries[0] . ") as combined_payments";
    }
    
    $count_stmt = $conn->prepare($count_query);
    if (!$count_stmt) {
        throw new Exception("Count prepare failed: " . $conn->error);
    }
    
    $count_stmt->bind_param($count_types, ...$count_params);
    if (!$count_stmt->execute()) {
        throw new Exception("Count execute failed: " . $count_stmt->error);
    }
    
    $total_result = $count_stmt->get_result();
    $total_row = $total_result->fetch_assoc();
    $total_payments = $total_row['total'];
    $total_pages = ceil($total_payments / $per_page);
    
    $debug_info[] = "Count query executed successfully. Total payments: " . $total_payments;

    // Get all possible payment statuses for filter dropdown
    $statuses_arr = [];
    
    if ($shop_trans_check->num_rows > 0) {
        $shop_status_query = "SELECT DISTINCT transaction_status FROM Shop_Transaction WHERE user_id = ? ORDER BY transaction_status";
        $shop_status_stmt = $conn->prepare($shop_status_query);
        $shop_status_stmt->bind_param("i", $user_id);
        $shop_status_stmt->execute();
        $shop_statuses = $shop_status_stmt->get_result();
        
        while ($status = $shop_statuses->fetch_assoc()) {
            $statuses_arr[$status['transaction_status']] = $status['transaction_status'];
        }
    }
    
    if ($package_pay_check->num_rows > 0) {
        $package_status_query = "SELECT DISTINCT transaction_status FROM Package_Payment WHERE user_id = ? ORDER BY transaction_status";
        $package_status_stmt = $conn->prepare($package_status_query);
        $package_status_stmt->bind_param("i", $user_id);
        $package_status_stmt->execute();
        $package_statuses = $package_status_stmt->get_result();
        
        while ($status = $package_statuses->fetch_assoc()) {
            $statuses_arr[$status['transaction_status']] = $status['transaction_status'];
        }
    }
    
    $debug_info[] = "Status query executed successfully";
    
} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
    $debug_info[] = $error;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History | POSTAL PRO</title>
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
                <h1 class="text-3xl font-bold section-title">Your Payment History</h1>
                <a href="customer_dashboard.php" class="mt-4 md:mt-0 action-btn text-white px-4 py-2 rounded inline-flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                </a>
            </div>
        </header>
        
        <?php if (!empty($success_message)): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
            <p><?= htmlspecialchars($success_message) ?></p>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
            <p><?= htmlspecialchars($error) ?></p>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($debug_info) && $role === 'Admin'): ?>
        <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-6">
            <h3 class="font-bold">Debug Information</h3>
            <ul class="list-disc pl-5">
                <?php foreach ($debug_info as $info): ?>
                <li><?= htmlspecialchars($info) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <!-- Filter Form -->
        <div class="bg-white p-5 rounded-lg shadow-sm mb-6">
            <h2 class="text-lg font-semibold mb-3">Filter Payments</h2>
            <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md" 
                           placeholder="Invoice # or tracking #">
                </div>
                
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select id="status" name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        <option value="">All Statuses</option>
                        <?php foreach ($statuses_arr as $status): ?>
                            <option value="<?= htmlspecialchars($status) ?>" 
                                    <?= $status_filter === $status ? 'selected' : '' ?>>
                                <?= htmlspecialchars($status) ?>
                            </option>
                        <?php endforeach; ?>
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
                    <a href="payment_history.php" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded">
                        Reset
                    </a>
                </div>
            </form>
        </div>
        
        <div class="mb-2 text-gray-600">
            <p>Showing payments <?= isset($total_payments) ? min(1, $total_payments) : 0 ?>-<?= isset($total_payments) ? min($total_payments, $offset + $per_page) : 0 ?> of <?= $total_payments ?? 0 ?></p>
        </div>
        
        <!-- Payment History -->
        <div class="bg-white p-5 rounded-lg shadow-sm mb-6">
            <div class="border-l-4 border-[#004B87] pl-3 mb-4">
                <h2 class="text-xl font-semibold">All Payments</h2>
            </div>
            
            <?php if (isset($payments) && $payments instanceof mysqli_result && $payments->num_rows > 0): ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-100 text-left">
                            <th class="px-4 py-2">Invoice #</th>
                            <th class="px-4 py-2">Date</th>
                            <th class="px-4 py-2">Amount</th>
                            <th class="px-4 py-2">Method</th>
                            <th class="px-4 py-2">Status</th>
                            <th class="px-4 py-2">Package</th>
                            <th class="px-4 py-2">Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($payment = $payments->fetch_assoc()): 
                            $status_class = '';
                            switch($payment['transaction_status']) {
                                case 'Completed': $status_class = 'bg-green-100 text-green-800'; break;
                                case 'Pending': $status_class = 'bg-yellow-100 text-yellow-800'; break;
                                case 'Failed': $status_class = 'bg-red-100 text-red-800'; break;
                                case 'Refunded': $status_class = 'bg-blue-100 text-blue-800'; break;
                                default: $status_class = 'bg-gray-100 text-gray-800';
                            }
                        ?>
                        <tr class="border-b border-gray-200 hover:bg-gray-50">
                            <td class="px-4 py-2"><?= htmlspecialchars($payment['invoice_number']) ?></td>
                            <td class="px-4 py-2"><?= date('M d, Y', strtotime($payment['payment_date'])) ?></td>
                            <td class="px-4 py-2 font-medium">$<?= number_format($payment['amount'], 2) ?></td>
                            <td class="px-4 py-2"><?= htmlspecialchars($payment['payment_method']) ?></td>
                            <td class="px-4 py-2">
                                <span class="<?= $status_class ?> px-2 py-1 rounded-full text-xs">
                                    <?= htmlspecialchars($payment['transaction_status']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-2">
                                <?php if (!empty($payment['tracking_number'])): ?>
                                <a href="package/track.php?tracking=<?= htmlspecialchars($payment['tracking_number']) ?>" 
                                   class="text-[#004B87] hover:text-[#DA291C]">
                                    <?= htmlspecialchars($payment['tracking_number']) ?>
                                </a>
                                <?php else: ?>
                                N/A
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2"><?= htmlspecialchars($payment['description'] ?? 'N/A') ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-gray-600">You don't have any payment records<?= !empty($status_filter) || !empty($date_from) || !empty($date_to) || !empty($search) ? ' matching your criteria' : '' ?>.</p>
            <?php endif; ?>
        </div>
        
        <!-- Pagination -->
        <?php if (isset($total_pages) && $total_pages > 1): ?>
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
    </div>
    
    <footer class="text-center mt-10 py-6 bg-[#004B87] text-white rounded-lg mb-6 mx-4">
        <p>&copy; <?= date('Y') ?> Postal Service Management System</p>
    </footer>
</body>
</html> 