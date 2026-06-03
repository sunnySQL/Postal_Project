<?php
session_start();
require_once '../db_connect.php';
require_once '../functions.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || 
    ($_SESSION['role'] != 'Employee' && $_SESSION['role'] != 'Admin')) {
    header("Location: ../login.php");
    exit();
}

// Get employee and shop info (include facility city/type for nav)
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'Employee';
$stmt = $conn->prepare("SELECT e.*, f.city, f.type, s.shop_id, s.shop_name 
                      FROM Employee e 
                      JOIN Facility f ON e.facility_id = f.facility_id
                      LEFT JOIN Shop s ON f.facility_id = s.facility_id
                      WHERE e.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$employee = $stmt->get_result()->fetch_assoc();
if (!$employee) {
    $employee = ['role' => $role, 'city' => '', 'type' => '', 'shop_id' => null, 'shop_name' => ''];
}

// Unread admin messages for nav badge (non-Admin)
$unread_admin_messages = 0;
if ($role != 'Admin') {
    $msg_stmt = $conn->prepare("SELECT COUNT(*) as count FROM Admin_Messages WHERE sender_id = ? AND status = 'Replied'");
    $msg_stmt->bind_param("i", $user_id);
    $msg_stmt->execute();
    $unread_admin_messages = $msg_stmt->get_result()->fetch_assoc()['count'] ?? 0;
}

// Check if there's a shop at this facility
if (!$employee['shop_id']) {
    $_SESSION['message'] = "There is no shop configured for your facility.";
    $_SESSION['message_type'] = "error";
    header("Location: shop_dashboard.php");
    exit();
}

$shop_id = $employee['shop_id'];

// Handle inventory adjustment if submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_inventory'])) {
    $conn->begin_transaction();
    try {
        $changes = [];

        foreach ($_POST['quantity'] as $item_id => $quantity) {
            $item_id = intval($item_id);
            $quantity = intval($quantity);

            // Fetch old quantity and item name before updating
            $old_stmt = $conn->prepare("SELECT inv.quantity, i.name FROM Inventory inv
                                        JOIN Items i ON inv.item_id = i.item_id
                                        WHERE inv.item_id = ? AND inv.shop_id = ?");
            $old_stmt->bind_param("ii", $item_id, $shop_id);
            $old_stmt->execute();
            $old_row = $old_stmt->get_result()->fetch_assoc();
            $old_qty  = $old_row['quantity'] ?? null;
            $item_name = $old_row['name'] ?? "Item #{$item_id}";

            $stmt = $conn->prepare("UPDATE Inventory SET quantity = ?, last_updated = CURRENT_TIMESTAMP
                                  WHERE item_id = ? AND shop_id = ?");
            $stmt->bind_param("iii", $quantity, $item_id, $shop_id);
            $stmt->execute();

            if ($old_qty !== null && (int)$old_qty !== $quantity) {
                $changes[] = [
                    'item_id'   => $item_id,
                    'item_name' => $item_name,
                    'old_qty'   => $old_qty,
                    'new_qty'   => $quantity,
                ];
            }
        }

        $conn->commit();

        // Log each changed item separately
        foreach ($changes as $ch) {
            logAudit($conn, 'INVENTORY_UPDATED', 'Inventory', (string)$ch['item_id'],
                "Inventory adjusted: \"{$ch['item_name']}\" qty {$ch['old_qty']} → {$ch['new_qty']} at shop #{$shop_id}",
                $employee['facility_id'] ?? null,
                ['quantity' => $ch['old_qty']],
                ['quantity' => $ch['new_qty']]
            );
        }

        $_SESSION['message'] = "Inventory updated successfully";
        $_SESSION['message_type'] = "success";
        header("Location: inventory_management.php");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Update failed: " . $e->getMessage();
    }
}

// Get inventory for this shop
$stmt = $conn->prepare("SELECT i.item_id, i.name, i.sale_price, i.price_wholesale, 
                      inv.quantity, inv.min_stock_level, inv.max_stock_level, inv.last_updated
                      FROM Items i
                      JOIN Inventory inv ON i.item_id = inv.item_id
                      WHERE inv.shop_id = ? AND inv.is_active = 1
                      ORDER BY i.name");
$stmt->bind_param("i", $shop_id);
$stmt->execute();
$inventory = $stmt->get_result();

// Get category filter if set
$category_filter = $_GET['category'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management</title>
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
                <h1 class="text-3xl font-bold section-title">Inventory Management</h1>
                <div class="mt-4 md:mt-0 bg-white shadow-sm p-4 rounded-lg">
                    <p class="mb-1"><span class="font-semibold">Shop:</span> <?= htmlspecialchars($employee['shop_name']) ?></p>
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
                <li><a href="shop_dashboard.php" class="text-gray-700 hover:text-[#DA291C]">Shop Overview</a></li>
                <li><a href="../package/search.php" class="text-gray-700 hover:text-[#DA291C]">Package Search &amp; Scan</a></li>
                <?php endif; ?>
                <?php if ($employee['role'] == 'Manager'): ?>
                <li><a href="shop_dashboard.php" class="text-gray-700 hover:text-[#DA291C]">Shop Overview</a></li>
                <li><a href="../reports/index.php" class="text-gray-700 hover:text-[#DA291C]">Reports</a></li>
                <li><a href="../trips/manage_trips.php" class="text-gray-700 hover:text-[#DA291C]">Manage Trips</a></li>
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

        <nav class="bg-gray-100 border border-gray-200 rounded-lg px-4 py-3 mb-6">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Shop &amp; Inventory</p>
            <ul class="flex flex-wrap gap-x-4 gap-y-1">
                <li><a href="process_sale.php" class="text-gray-700 hover:text-[#DA291C]">Process Sale</a></li>
                <li><a href="transaction_list.php" class="text-gray-700 hover:text-[#DA291C]">Transaction List</a></li>
                <li><a href="inventory_management.php" class="text-gray-700 hover:text-[#DA291C] border-b-2 border-[#DA291C] pb-0.5 font-medium">Inventory Management</a></li>
            </ul>
        </nav>
        
        <main>
            <?php if (isset($_SESSION['message'])): ?>
                <div class="<?= $_SESSION['message_type'] == 'success' ? 'bg-green-100 border-l-4 border-green-500 text-green-700' : 'bg-red-100 border-l-4 border-red-500 text-red-700' ?> p-4 mb-6">
                    <?= htmlspecialchars($_SESSION['message']) ?>
                </div>
                <?php 
                    unset($_SESSION['message']);
                    unset($_SESSION['message_type']);
                ?>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>
            
            <section class="bg-white shadow-sm p-6 rounded-lg mb-6">
                <h2 class="text-2xl font-semibold mb-4 section-title">Current Inventory</h2>
                
                <div class="flex flex-col md:flex-row md:justify-between md:items-center mb-4">
                    <div class="flex space-x-2 mb-3 md:mb-0">
                        <a href="inventory_management.php" class="px-3 py-1 rounded text-sm <?= empty($category_filter) ? 'bg-[#004B87] text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">All Items</a>
                        <a href="inventory_management.php?category=low" class="px-3 py-1 rounded text-sm <?= $category_filter == 'low' ? 'bg-[#DA291C] text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">Low Stock</a>
                        <a href="inventory_management.php?category=overstock" class="px-3 py-1 rounded text-sm <?= $category_filter == 'overstock' ? 'bg-[#004B87] text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?>">Overstocked</a>
                    </div>
                    
                    <input type="text" id="inventory-search" class="border border-gray-300 rounded px-3 py-2 w-full md:w-64" placeholder="Search items...">
                </div>
                
                <form action="inventory_management.php" method="post">
                    <div class="overflow-x-auto">
                        <table class="w-full" id="inventory-table">
                            <thead>
                                <tr class="bg-gray-200 text-left">
                                    <th class="px-4 py-2">Item Name</th>
                                    <th class="px-4 py-2">Retail Price</th>
                                    <th class="px-4 py-2">Wholesale Price</th>
                                    <th class="px-4 py-2">Current Stock</th>
                                    <th class="px-4 py-2">Min Level</th>
                                    <th class="px-4 py-2">Max Level</th>
                                    <th class="px-4 py-2">Status</th>
                                    <th class="px-4 py-2">Last Updated</th>
                                    <th class="px-4 py-2">Adjust</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($inventory->num_rows > 0): ?>
                                    <?php while ($item = $inventory->fetch_assoc()): ?>
                                        <?php 
                                            $stockStatus = '';
                                            $stockClass = '';
                                            if ($item['quantity'] <= $item['min_stock_level']) {
                                                $stockStatus = 'Low Stock';
                                                $stockClass = 'bg-red-100 text-red-800';
                                            } elseif ($item['quantity'] >= $item['max_stock_level']) {
                                                $stockStatus = 'Overstocked';
                                                $stockClass = 'bg-yellow-100 text-yellow-800';
                                            } else {
                                                $stockStatus = 'Optimal';
                                                $stockClass = 'bg-green-100 text-green-800';
                                            }
                                            
                                            // Apply category filter
                                            $showItem = true;
                                            if ($category_filter == 'low' && $item['quantity'] > $item['min_stock_level']) {
                                                $showItem = false;
                                            } elseif ($category_filter == 'overstock' && $item['quantity'] < $item['max_stock_level']) {
                                                $showItem = false;
                                            }
                                        ?>
                                        
                                        <?php if ($showItem): ?>
                                        <tr class="border-b border-gray-200 hover:bg-gray-50">
                                            <td class="px-4 py-2"><?= htmlspecialchars($item['name']) ?></td>
                                            <td class="px-4 py-2">$<?= number_format($item['sale_price'], 2) ?></td>
                                            <td class="px-4 py-2">$<?= number_format($item['price_wholesale'], 2) ?></td>
                                            <td class="px-4 py-2"><?= $item['quantity'] ?></td>
                                            <td class="px-4 py-2"><?= $item['min_stock_level'] ?></td>
                                            <td class="px-4 py-2"><?= $item['max_stock_level'] ?></td>
                                            <td class="px-4 py-2">
                                                <span class="<?= $stockClass ?> px-2 py-1 rounded-full text-xs">
                                                    <?= $stockStatus ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-2"><?= date('Y-m-d', strtotime($item['last_updated'])) ?></td>
                                            <td class="px-4 py-2">
                                                <input type="number" name="quantity[<?= $item['item_id'] ?>]" 
                                                       value="<?= $item['quantity'] ?>" min="0"
                                                       class="border border-gray-300 rounded px-2 py-1 w-20">
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="px-4 py-2 text-center text-gray-600">No inventory items found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mt-6 text-center">
                        <button type="submit" name="update_inventory" class="action-btn text-white px-6 py-2 rounded font-medium">
                            Update Inventory
                        </button>
                    </div>
                </form>
            </section>
        </main>
        
        <footer class="text-center mt-10 py-4 text-gray-600 border-t border-gray-300">
            <p>&copy; <?= date('Y') ?> Postal Service Management System</p>
        </footer>
    </div>
    
    <script>
        // Search functionality
        document.getElementById('inventory-search').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const table = document.getElementById('inventory-table');
            const rows = table.getElementsByTagName('tr');
            for (let i = 1; i < rows.length; i++) {
                if (rows[i].cells && rows[i].cells[0]) {
                    const itemName = rows[i].cells[0].textContent.toLowerCase();
                    
                    if (itemName.includes(searchTerm)) {
                        rows[i].style.display = '';
                    } else {
                        rows[i].style.display = 'none';
                    }
                }
            }
        });
    </script>
</body>
</html>
