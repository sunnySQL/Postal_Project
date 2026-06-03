<?php
session_start();
require_once '../db_connect.php';
require_once '../functions.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || 
    ($_SESSION['role'] != 'Employee' && $_SESSION['role'] != 'Admin')) {
    header("Location: ../login.php");
    exit();
}

// Get employee information
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'Employee';
$stmt = $conn->prepare("SELECT e.*, f.facility_id, f.city, f.type, s.shop_id, s.shop_name 
                      FROM Employee e 
                      JOIN Facility f ON e.facility_id = f.facility_id
                      LEFT JOIN Shop s ON f.facility_id = s.facility_id
                      WHERE e.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc();
$name = ($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? '');

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

// Get all inventory items for this shop
$stmt = $conn->prepare("SELECT i.item_id, i.name, i.sale_price, inv.quantity 
                      FROM Items i
                      JOIN Inventory inv ON i.item_id = inv.item_id
                      WHERE inv.shop_id = ? AND inv.quantity > 0 AND inv.is_active = 1
                      ORDER BY i.name");
$stmt->bind_param("i", $shop_id);
$stmt->execute();
$items = $stmt->get_result();

// Process sale if form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_sale'])) {
    $conn->begin_transaction();
    
    try {
        // Get form data
        $customer_id = !empty($_POST['customer_id']) ? intval($_POST['customer_id']) : null;
        $payment_method = $_POST['payment_method'];
        $cart_items = isset($_POST['cart_items']) ? $_POST['cart_items'] : [];
        $cart_quantities = isset($_POST['cart_quantities']) ? $_POST['cart_quantities'] : [];
        
        if (empty($cart_items)) {
            throw new Exception("No items selected for purchase.");
        }

        $total_amount = 0;
        $cart_data = []; 
        foreach ($cart_items as $index => $item_id) {
            $item_id = intval($item_id);
            $quantity = intval($cart_quantities[$index]);
            
            if ($quantity <= 0) {
                continue;
            }
            
            // Get item price and check inventory
            $stmt = $conn->prepare("SELECT i.sale_price, inv.quantity 
                                  FROM Items i 
                                  JOIN Inventory inv ON i.item_id = inv.item_id
                                  WHERE i.item_id = ? AND inv.shop_id = ? AND inv.is_active = 1");
            $stmt->bind_param("ii", $item_id, $shop_id);
            $stmt->execute();
            $item_data = $stmt->get_result()->fetch_assoc();
            
            if (!$item_data) {
                throw new Exception("Item not found in inventory.");
            }
            
            if ($item_data['quantity'] < $quantity) {
                throw new Exception("Insufficient inventory for selected item.");
            }
            
            $subtotal = $item_data['sale_price'] * $quantity;
            $total_amount += $subtotal;
            
            $cart_data[] = [
                'item_id' => $item_id,
                'quantity' => $quantity,
                'price' => $item_data['sale_price'],
                'subtotal' => $subtotal
            ];
        }
        
        // Record the transaction
        $stmt = $conn->prepare("INSERT INTO Shop_Transaction (shop_id, user_id, total_amount, payment_method, transaction_status, transaction_date) 
                                VALUES (?, ?, ?, ?, 'Completed', NOW())");
        $stmt->bind_param("isds", $shop_id, $customer_id, $total_amount, $payment_method);
        $stmt->execute();
        $transaction_id = $conn->insert_id;
        
        // Record individual sales and update inventory
        foreach ($cart_data as $item) {
            // Insert into Shop_Sale
            $sale_amount = $item['price'] * $item['quantity'];
            $stmt = $conn->prepare("INSERT INTO Shop_Sale (shop_id, item_id, quantity, sale_amount, transaction_id) 
                                    VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iiidi", $shop_id, $item['item_id'], $item['quantity'], $sale_amount, $transaction_id);
            $stmt->execute();
            
            // Update inventory
            $stmt = $conn->prepare("UPDATE Inventory SET quantity = quantity - ?,
                                      last_updated = CURRENT_TIMESTAMP
                                      WHERE item_id = ? AND shop_id = ?");
            $stmt->bind_param("iii", $item['quantity'], $item['item_id'], $shop_id);
            $stmt->execute();
        }
        
        // Update Shop sales total
        $stmt = $conn->prepare("UPDATE Shop SET sales = sales + ? WHERE shop_id = ?");
        $stmt->bind_param("di", $total_amount, $shop_id);
        $stmt->execute();
        $conn->commit();
        
        // Store transaction info in session but don't redirect immediately
        $_SESSION['message'] = "Sale completed successfully! Transaction ID: $transaction_id";
        $_SESSION['message_type'] = "success";
        $_SESSION['last_transaction'] = $transaction_id;
        
        // Save transaction details for displaying on the page
        $sale_success = true;
        $transaction_details = [
            'id' => $transaction_id,
            'date' => date('Y-m-d H:i:s'),
            'total' => $total_amount,
            'items' => $cart_data,
            'customer_id' => $customer_id,
            'payment_method' => $payment_method
        ];
        
        // Clear the cart after successful sale
        $cart_items = [];
        $cart_quantities = [];
        
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Transaction failed: " . $e->getMessage();
    }
}
$customers = $conn->query("SELECT user_id, first_name, last_name FROM Customer ORDER BY last_name, first_name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Process Sale</title>
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
        
        .item-card {
            border: 2px solid black;
            border-radius: 8px;
            padding: 16px;
            position: relative;
            background-color: white;
            display: flex;
            flex-direction: column;
            height: 200px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .item-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .item-card.out-of-stock {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .cart-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        .item-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .item-quantity {
            margin: 0 0.5rem;
            font-weight: 600;
        }
        
        .subtotal {
            font-weight: 600;
            padding: 0 0.5rem;
        }
        
        .add-to-cart-btn {
            position: absolute;
            bottom: 1rem;
            right: 1rem;
            background-color: #DA291C;
            color: white;
            width: 2rem;
            height: 2rem;
            border-radius: 9999px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s;
            border: none;
            cursor: pointer;
        }
        
        .add-to-cart-btn:hover {
            transform: scale(1.1);
        }
        
        .sold-out-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background-color: #6b7280;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
        }
        
        .item-name {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .item-description {
            font-size: 0.875rem;
            color: #4b5563;
            margin-bottom: 1rem;
            flex-grow: 1;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .item-price {
            font-size: 1.125rem;
            font-weight: 700;
            color: #059669;
        }
    </style>
</head>
<body class="bg-gray-50">
<?php $emp_base = '../'; include '../_employee_nav.php'; ?>

    <div class="container mx-auto px-4 py-8 max-w-6xl">
        <header class="mb-6">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center">
                <h1 class="text-3xl font-bold section-title">Process Sale</h1>
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
                <li><a href="inventory_management.php" class="text-gray-700 hover:text-[#DA291C]">Inventory Management</a></li>
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
                <li><a href="process_sale.php" class="text-gray-700 hover:text-[#DA291C] border-b-2 border-[#DA291C] pb-0.5 font-medium">Process Sale</a></li>
                <li><a href="transaction_list.php" class="text-gray-700 hover:text-[#DA291C]">Transaction List</a></li>
                <li><a href="inventory_management.php" class="text-gray-700 hover:text-[#DA291C]">Inventory Management</a></li>
            </ul>
        </nav>
        
        <main>
            <?php if (isset($error_message)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($sale_success) && $sale_success): ?>
            <!-- Sale Success Confirmation -->
            <div class="bg-gray-100 p-6 rounded-lg mb-6">
                <div class="text-center mb-6">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-100 mb-4">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <h2 class="text-2xl font-semibold mb-1">Sale Completed Successfully</h2>
                    <p class="text-gray-600">Transaction ID: <?= $transaction_details['id'] ?></p>
                </div>
                
                <div class="bg-white p-6 rounded-lg mb-6 shadow-sm">
                    <div class="grid grid-cols-2 gap-4 mb-4 pb-4 border-b border-gray-200">
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Date/Time:</p>
                            <p class="font-medium"><?= $transaction_details['date'] ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 mb-1">Payment Method:</p>
                            <p class="font-medium"><?= htmlspecialchars($transaction_details['payment_method']) ?></p>
                        </div>
                    </div>
                    
                    <h3 class="font-semibold mb-3">Items Purchased</h3>
                    <div class="max-h-48 overflow-y-auto mb-4">
                        <?php foreach ($transaction_details['items'] as $item): ?>
                            <?php
                            // Get item name from item_id
                            $stmt = $conn->prepare("SELECT name FROM Items WHERE item_id = ?");
                            $stmt->bind_param("i", $item['item_id']);
                            $stmt->execute();
                            $item_result = $stmt->get_result();
                            $item_data = $item_result->fetch_assoc();
                            $item_name = $item_data ? $item_data['name'] : 'Unknown Item';
                            ?>
                            <div class="flex justify-between py-2 border-b border-gray-100">
                                <div>
                                    <span class="font-medium"><?= htmlspecialchars($item_name) ?></span>
                                    <span class="text-gray-500 ml-2">× <?= $item['quantity'] ?></span>
                                </div>
                                <span class="text-green-600">$<?= number_format($item['subtotal'], 2) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="flex justify-between items-center font-semibold text-lg pt-2 border-t border-gray-200">
                        <span>Total:</span>
                        <span class="text-green-600">$<?= number_format($transaction_details['total'], 2) ?></span>
                    </div>
                </div>
                
                <div class="flex justify-center space-x-4">
                    <a href="process_sale.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded font-medium">
                        New Sale
                    </a>
                    <a href="shop_dashboard.php" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded font-medium">
                        Return to Dashboard
                    </a>
                </div>
            </div>
            <?php else: ?>
            <form action="process_sale.php" method="post" id="sale-form">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-gray-100 p-6 rounded-lg">
                        <h2 class="text-2xl font-semibold mb-4">Products</h2>
                        <div class="mb-4">
                            <input type="text" id="item-search" class="w-full border border-gray-300 rounded px-3 py-2" 
                                   placeholder="Search for items..." autocomplete="off">
                        </div>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 mt-4">
                            <?php if ($items->num_rows > 0): ?>
                                <?php while ($item = $items->fetch_assoc()): ?>
                                    <div class="item-card <?= $item['quantity'] <= 0 ? 'out-of-stock' : '' ?> bg-white" 
                                         data-id="<?= $item['item_id'] ?>"
                                         data-name="<?= htmlspecialchars($item['name']) ?>"
                                         data-price="<?= $item['sale_price'] ?>"
                                         data-stock="<?= $item['quantity'] ?>">
                                        <div class="item-name"><?= htmlspecialchars($item['name']) ?></div>
                                        <div class="item-price">$<?= number_format($item['sale_price'], 2) ?></div>
                                        <div class="text-sm text-gray-600">Stock: <?= $item['quantity'] ?></div>
                                        <?php if ($item['quantity'] > 0): ?>
                                            <button type="button" class="add-to-cart-btn" onclick="addToCart(this.parentNode)">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p class="text-gray-600 col-span-3">No items available in inventory.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="bg-gray-100 p-6 rounded-lg">
                        <h2 class="text-2xl font-semibold mb-4">Shopping Cart</h2>
                        
                        <div class="mb-4">
                            <label for="customer_id" class="block mb-2 font-medium">Customer (Optional):</label>
                            <select name="customer_id" id="customer_id" class="w-full border border-gray-300 rounded px-3 py-2">
                                <option value="">Walk-in Customer</option>
                                <?php while ($customer = $customers->fetch_assoc()): ?>
                                    <option value="<?= $customer['user_id'] ?>">
                                        <?= htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label for="payment_method" class="block mb-2 font-medium">Payment Method:</label>
                            <select name="payment_method" id="payment_method" class="w-full border border-gray-300 rounded px-3 py-2" required>
                                <option value="Credit Card">Credit Card</option>
                                <option value="Cash">Cash</option>
                                <option value="PayPal">PayPal</option>
                            </select>
                        </div>
                        
                        <div class="bg-white p-4 rounded-lg mb-4 min-h-48">
                            <div id="cart-items">
                                <p id="empty-cart-message" class="text-gray-500 text-center py-8">Cart is empty. Add items from the product list.</p>
                            </div>
                            
                            <div class="flex justify-between items-center border-t border-gray-200 pt-4 mt-4">
                                <span class="text-lg font-semibold">Total:</span>
                                <span class="text-xl font-bold text-green-600">$<span id="cart-total">0.00</span></span>
                            </div>
                        </div>
                        
                        <div class="flex justify-between mt-6">
                            <button type="button" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded" onclick="clearCart()">
                                Clear Cart
                            </button>
                            <button type="submit" name="process_sale" class="bg-green-500 hover:bg-green-600 text-white px-6 py-2 rounded font-medium" id="checkout-btn" disabled>
                                Complete Sale
                            </button>
                        </div>
                    </div>
                </div>
            </form>
            <?php endif; ?>
        </main>
        
        <footer class="text-center mt-10 py-4 text-gray-600 border-t border-gray-300">
            <p>&copy; <?= date('Y') ?> Postal Service Management System</p>
        </footer>
    </div>
    
    <script>
        let cartItems = [];
        const cartContainer = document.getElementById('cart-items');
        const cartTotalElement = document.getElementById('cart-total');
        const checkoutBtn = document.getElementById('checkout-btn');
        const emptyCartMessage = document.getElementById('empty-cart-message');
        
        // Add item to cart
        function addToCart(itemElement) {
            if (itemElement.classList.contains('out-of-stock')) {
                alert('This item is out of stock.');
                return;
            }
            
            const itemId = itemElement.dataset.id;
            const itemName = itemElement.dataset.name;
            const itemPrice = parseFloat(itemElement.dataset.price);
            const itemStock = parseInt(itemElement.dataset.stock);
            
            const existingItem = cartItems.find(item => item.id === itemId);
            
            if (existingItem) {
                if (existingItem.quantity < itemStock) {
                    existingItem.quantity += 1;
                    existingItem.subtotal = existingItem.quantity * itemPrice;
                } else {
                    alert('Cannot add more of this item. Maximum stock reached.');
                    return;
                }
            } else {
                cartItems.push({
                    id: itemId,
                    name: itemName,
                    price: itemPrice,
                    quantity: 1,
                    stock: itemStock,
                    subtotal: itemPrice
                });
            }
            
            updateCartDisplay();
        }
        
        function updateQuantity(index, change) {
            const item = cartItems[index];
            const newQuantity = item.quantity + change;
            
            if (newQuantity > 0 && newQuantity <= item.stock) {
                item.quantity = newQuantity;
                item.subtotal = item.quantity * item.price;
                updateCartDisplay();
            }
        }
        
        function removeItem(index) {
            cartItems.splice(index, 1);
            updateCartDisplay();
        }
        
        function updateCartDisplay() {
            emptyCartMessage.style.display = cartItems.length === 0 ? 'block' : 'none';
            let total = 0;
            cartItems.forEach(item => total += item.subtotal);
            cartTotalElement.textContent = total.toFixed(2);
            checkoutBtn.disabled = cartItems.length === 0;
            cartContainer.innerHTML = '';
            
            if (cartItems.length > 0) {
                cartItems.forEach((item, index) => {
                    const cartItemDiv = document.createElement('div');
                    cartItemDiv.className = 'cart-item';
                    cartItemDiv.innerHTML = `
                        <div>
                            <div class="font-medium">${item.name}</div>
                            <div class="text-sm text-gray-600">$${item.price.toFixed(2)} each</div>
                        </div>
                        <div class="item-controls">
                            <button type="button" class="bg-gray-200 hover:bg-gray-300 rounded-full w-6 h-6 flex items-center justify-center" onclick="updateQuantity(${index}, -1)">-</button>
                            <span class="item-quantity">${item.quantity}</span>
                            <button type="button" class="bg-gray-200 hover:bg-gray-300 rounded-full w-6 h-6 flex items-center justify-center" onclick="updateQuantity(${index}, 1)">+</button>
                            <span class="subtotal">$${item.subtotal.toFixed(2)}</span>
                            <button type="button" class="text-red-500 hover:text-red-700" onclick="removeItem(${index})">×</button>
                        </div>
                        <input type="hidden" name="cart_items[]" value="${item.id}">
                        <input type="hidden" name="cart_quantities[]" value="${item.quantity}">
                    `;
                    cartContainer.appendChild(cartItemDiv);
                });
            } else {
                cartContainer.appendChild(emptyCartMessage);
            }
        }
        
        function clearCart() {
            cartItems = [];
            updateCartDisplay();
        }
        
        // Search functionality
        document.getElementById('item-search').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            document.querySelectorAll('.item-card').forEach(card => {
                const itemName = card.dataset.name.toLowerCase();
                card.style.display = itemName.includes(searchTerm) ? 'block' : 'none';
            });
        });
    </script>
</body>
</html>