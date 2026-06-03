<?php
session_start();
require_once 'db_connect.php';
require_once 'functions.php';

// Only allow customers to access this page
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'Customer') {
    header("Location: login.php");
    exit();
}

// Get customer information
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM Customer WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$customer = $result->fetch_assoc();

// Get all available shop locations
$shops = $conn->query("SELECT s.shop_id, s.shop_name, f.city FROM Shop s JOIN Facility f ON s.facility_id = f.facility_id ORDER BY f.city");

// Get selected shop 
$shop_id = isset($_POST['shop_id']) ? intval($_POST['shop_id']) : (isset($_GET['shop_id']) ? intval($_GET['shop_id']) : 0);

// Get items for the selected shop
$items = null;
if ($shop_id > 0) {
    $stmt = $conn->prepare("SELECT i.item_id, i.name, i.sale_price, inv.quantity, i.image, i.description, i.category 
                          FROM Items i
                          JOIN Inventory inv ON i.item_id = inv.item_id
                          WHERE inv.shop_id = ? AND inv.quantity > 0 AND inv.is_active = 1
                          ORDER BY i.category, i.name");
    $stmt->bind_param("i", $shop_id);
    $stmt->execute();
    $items = $stmt->get_result();
    
    // Get categories for filtering
    $stmt = $conn->prepare("SELECT DISTINCT i.category 
                          FROM Items i
                          JOIN Inventory inv ON i.item_id = inv.item_id
                          WHERE inv.shop_id = ? AND inv.quantity > 0 AND inv.is_active = 1
                          ORDER BY i.category");
    $stmt->bind_param("i", $shop_id);
    $stmt->execute();
    $categories = $stmt->get_result();
}

// Process order if form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $conn->begin_transaction();
    
    try {
        // Get form data
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
        
        // Generate transaction status and pickup code
        $transaction_status = 'Pending Pickup';
        $pickup_code = strtoupper(substr(md5(uniqid() . $user_id), 0, 8));
        
        // Record the transaction
        $stmt = $conn->prepare("INSERT INTO Shop_Transaction (shop_id, user_id, total_amount, payment_method, transaction_status, transaction_date) 
                                VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("iidss", $shop_id, $user_id, $total_amount, $payment_method, $transaction_status);
        $stmt->execute();
        $transaction_id = $conn->insert_id;
        
        // Store pickup code in a separate table
        $stmt = $conn->prepare("INSERT INTO Order_Pickup (transaction_id, order_number, pickup_code) 
                              VALUES (?, ?, ?)");
        $order_number = 'ORD-' . str_pad($transaction_id, 6, '0', STR_PAD_LEFT);
        $stmt->bind_param("iss", $transaction_id, $order_number, $pickup_code);
        $stmt->execute();
        
        // Record individual items and update inventory
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
        
        // Store order info in session for confirmation page
        $_SESSION['last_order'] = [
            'transaction_id' => $transaction_id,
            'order_number' => $order_number,
            'pickup_code' => $pickup_code,
            'total' => $total_amount,
            'payment_method' => $payment_method,
            'shop_id' => $shop_id,
            'order_date' => date('Y-m-d H:i:s')
        ];
        
        // Redirect to order confirmation page
        header("Location: order_confirmation.php");
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Order failed: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop Online | Postal Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .item-card {
            @apply border rounded-lg p-4 hover:shadow-md transition cursor-pointer bg-white;
        }
        .item-card.out-of-stock {
            @apply opacity-50 cursor-not-allowed;
        }
        .cart-item {
            @apply flex justify-between items-center border-b py-2 mb-2;
        }
        .item-controls {
            @apply flex items-center space-x-2;
        }
        .item-quantity {
            @apply mx-2 font-semibold;
        }
        .subtotal {
            @apply font-semibold px-2;
        }
        .category-badge {
            @apply px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-800 inline-block mb-2;
        }
        .nav-btn {
            @apply bg-[#004B87] hover:bg-[#003366] text-white px-4 py-2 rounded transition;
        }
        .accent-btn {
            @apply bg-[#DA291C] hover:bg-[#b52218] text-white px-4 py-2 rounded transition;
        }
    </style>
</head>
<body class="bg-gray-50">
    <nav class="top-nav fixed w-full top-0 left-0 z-50 shadow-md px-4 py-4 bg-[#004B87] text-white">
        <div class="container mx-auto flex justify-between items-center">
            <a href="index.php" class="text-white font-bold text-2xl">POSTAL PRO</a>
            <ul class="flex space-x-6">
                <li><a href="customer_dashboard.php" class="text-white hover:text-gray-200">Dashboard</a></li>
                <li><a href="cart.php" class="text-white hover:text-gray-200"><i class="fas fa-shopping-cart mr-1"></i>Cart</a></li>
                <li><a href="logout.php" class="text-white hover:text-gray-200">Logout</a></li>
            </ul>
        </div>
    </nav>
    
    <div class="container mx-auto px-4 py-8 max-w-6xl mt-20">
        <header class="mb-6">
            <h1 class="text-3xl font-bold text-[#004B87]">Shop Online</h1>
            <p class="text-gray-600 mt-2">Browse our products and place an order for pickup</p>
        </header>
        
        <?php if (isset($error_message)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>
        
        <?php if (!$shop_id): ?>
            <!-- Shop Selection -->
            <div class="bg-white shadow-sm rounded-lg p-6 mb-6">
                <h2 class="text-xl font-semibold mb-4">Select a Store Location</h2>
                <p class="mb-4 text-gray-600">Choose a store location to browse products available for pickup.</p>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php while ($shop = $shops->fetch_assoc()): ?>
                        <a href="?shop_id=<?= $shop['shop_id'] ?>" class="block p-4 border rounded-lg hover:shadow-md transition">
                            <h3 class="font-semibold text-lg"><?= htmlspecialchars($shop['shop_name']) ?></h3>
                            <p class="text-gray-600"><?= htmlspecialchars($shop['city']) ?></p>
                        </a>
                    <?php endwhile; ?>
                </div>
            </div>
        <?php else: ?>
            <form action="customer_order.php" method="post" id="order-form">
                <input type="hidden" name="shop_id" value="<?= $shop_id ?>">
                
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2">
                        <div class="bg-white shadow-sm rounded-lg p-6 mb-6">
                            <div class="flex justify-between items-center mb-4">
                                <h2 class="text-xl font-semibold">Available Products</h2>
                                <a href="customer_order.php" class="text-[#004B87] hover:underline">
                                    <i class="fas fa-arrow-left mr-1"></i> Change Store
                                </a>
                            </div>
                            
                            <!-- Search and Category Filter -->
                            <div class="flex flex-col md:flex-row gap-2 mb-4">
                                <div class="flex-grow">
                                    <input type="text" id="item-search" class="w-full border border-gray-300 rounded px-3 py-2" 
                                           placeholder="Search for items..." autocomplete="off">
                                </div>
                                <div>
                                    <select id="category-filter" class="border border-gray-300 rounded px-3 py-2 w-full md:w-auto">
                                        <option value="">All Categories</option>
                                        <?php if ($categories): while ($cat = $categories->fetch_assoc()): ?>
                                            <option value="<?= htmlspecialchars($cat['category']) ?>">
                                                <?= htmlspecialchars($cat['category']) ?>
                                            </option>
                                        <?php endwhile; endif; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Product Grid -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-4">
                                <?php if ($items && $items->num_rows > 0): ?>
                                    <?php while ($item = $items->fetch_assoc()): ?>
                                        <div class="item-card <?= $item['quantity'] <= 0 ? 'out-of-stock' : '' ?>" 
                                             data-id="<?= $item['item_id'] ?>"
                                             data-name="<?= htmlspecialchars($item['name']) ?>"
                                             data-price="<?= $item['sale_price'] ?>"
                                             data-stock="<?= $item['quantity'] ?>"
                                             data-category="<?= htmlspecialchars($item['category']) ?>"
                                             onclick="addToCart(this)">
                                            <?php if (!empty($item['category'])): ?>
                                                <div class="category-badge"><?= htmlspecialchars($item['category']) ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($item['image'])): ?>
                                                <div class="h-32 bg-gray-100 rounded mb-2 flex items-center justify-center overflow-hidden">
                                                    <img src="css/img/<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="max-h-full">
                                                </div>
                                            <?php endif; ?>
                                            <div class="font-semibold"><?= htmlspecialchars($item['name']) ?></div>
                                            <?php if (!empty($item['description'])): ?>
                                                <div class="text-sm text-gray-600 mb-2 line-clamp-2"><?= htmlspecialchars($item['description']) ?></div>
                                            <?php endif; ?>
                                            <div class="text-lg text-green-600">$<?= number_format($item['sale_price'], 2) ?></div>
                                            <div class="text-sm text-gray-600">In Stock: <?= $item['quantity'] ?></div>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <p class="text-gray-600 col-span-3">No items available in inventory at this location.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="sticky top-24 h-fit">
                        <div class="bg-white shadow-sm rounded-lg p-6">
                            <h2 class="text-xl font-semibold mb-4">Your Order</h2>
                            
                            <div class="mb-4">
                                <label for="payment_method" class="block mb-2 font-medium">Payment Method:</label>
                                <select name="payment_method" id="payment_method" class="w-full border border-gray-300 rounded px-3 py-2" required>
                                    <option value="Credit Card">Credit Card</option>
                                    <option value="PayPal">PayPal</option>
                                </select>
                            </div>
                            
                            <div class="bg-gray-50 p-4 rounded-lg mb-4 min-h-48">
                                <div id="cart-items">
                                    <p id="empty-cart-message" class="text-gray-500 text-center py-8">Your order is empty. Click on items to add them.</p>
                                </div>
                                
                                <div class="flex justify-between items-center border-t border-gray-200 pt-4 mt-4">
                                    <span class="text-lg font-semibold">Total:</span>
                                    <span class="text-xl font-bold text-green-600">$<span id="cart-total">0.00</span></span>
                                </div>
                            </div>
                            
                            <div class="flex justify-between mt-6">
                                <button type="button" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded" onclick="clearCart()">
                                    Clear Order
                                </button>
                                <button type="submit" name="place_order" class="accent-btn px-6 py-2 rounded font-medium" id="checkout-btn" disabled>
                                    Place Order
                                </button>
                            </div>
                            
                            <div class="mt-4 text-sm text-gray-600">
                                <p class="mb-1"><i class="fas fa-info-circle mr-1"></i> Orders are for pickup only.</p>
                                <p>You'll receive a pickup code to show the clerk.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        <?php endif; ?>
        
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
        document.getElementById('item-search')?.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            filterItems();
        });
        
        // Category filter
        document.getElementById('category-filter')?.addEventListener('change', function() {
            filterItems();
        });
        
        function filterItems() {
            const searchTerm = document.getElementById('item-search')?.value.toLowerCase() || '';
            const categoryFilter = document.getElementById('category-filter')?.value || '';
            
            document.querySelectorAll('.item-card').forEach(card => {
                const itemName = card.dataset.name.toLowerCase();
                const itemCategory = card.dataset.category;
                const matchesSearch = itemName.includes(searchTerm);
                const matchesCategory = categoryFilter === '' || itemCategory === categoryFilter;
                
                card.style.display = matchesSearch && matchesCategory ? 'block' : 'none';
            });
        }
    </script>
</body>
</html> 