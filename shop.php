<?php
// Version: 3.0 - Enhanced styling with black borders and even card sizes
session_start();
require_once 'db_connect.php';
require_once 'functions.php';

// Enable error reporting for debugging
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] != 'Customer') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$name = $_SESSION['name'];

// Get customer information
$stmt = $conn->prepare("SELECT * FROM Customer WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();

// Get nearest facility with a shop based on customer's zip code
$zip_code = $customer['postal_code'];
$stmt = $conn->prepare("SELECT f.facility_id, f.city, s.shop_id, s.shop_name, 
                        (
                            SELECT COUNT(*) 
                            FROM Inventory inv 
                            WHERE inv.shop_id = s.shop_id AND inv.quantity > 0 AND inv.is_active = 1
                        ) as item_count
                        FROM Facility f
                        JOIN Shop s ON f.facility_id = s.facility_id
                        WHERE f.postal_code = ?
                        LIMIT 1");
$stmt->bind_param("s", $zip_code);
$stmt->execute();
$nearest_shop = $stmt->get_result()->fetch_assoc();

// If no shop in customer's zip, find the closest one
if (!$nearest_shop) {
    // Get the state from customer's address
    $customer_state = $customer['state'];
    
    // First try finding a shop in the same city
    $stmt = $conn->prepare("SELECT f.facility_id, f.city, s.shop_id, s.shop_name,
                          (
                              SELECT COUNT(*) 
                              FROM Inventory inv 
                              WHERE inv.shop_id = s.shop_id AND inv.quantity > 0 AND inv.is_active = 1
                          ) as item_count
                          FROM Facility f
                          JOIN Shop s ON f.facility_id = s.facility_id
                          WHERE f.city = ? AND f.state = ?
                          LIMIT 1");
    $stmt->bind_param("ss", $customer['city'], $customer_state);
    $stmt->execute();
    $nearest_shop = $stmt->get_result()->fetch_assoc();
    
    // If still no shop, try finding one in the same state
    if (!$nearest_shop) {
        $stmt = $conn->prepare("SELECT f.facility_id, f.city, s.shop_id, s.shop_name,
                              (
                                  SELECT COUNT(*) 
                                  FROM Inventory inv 
                                  WHERE inv.shop_id = s.shop_id AND inv.quantity > 0 AND inv.is_active = 1
                              ) as item_count
                              FROM Facility f
                              JOIN Shop s ON f.facility_id = s.facility_id
                              WHERE f.state = ?
                              LIMIT 1");
        $stmt->bind_param("s", $customer_state);
        $stmt->execute();
        $nearest_shop = $stmt->get_result()->fetch_assoc();
    }
    
    // Final fallback - use any shop
    if (!$nearest_shop) {
        $stmt = $conn->prepare("SELECT f.facility_id, f.city, f.state, s.shop_id, s.shop_name,
                              (
                                  SELECT COUNT(*) 
                                  FROM Inventory inv 
                                  WHERE inv.shop_id = s.shop_id AND inv.quantity > 0 AND inv.is_active = 1
                              ) as item_count
                              FROM Facility f
                              JOIN Shop s ON f.facility_id = s.facility_id
                              ORDER BY RAND()
                              LIMIT 1");
        $stmt->execute();
        $nearest_shop = $stmt->get_result()->fetch_assoc();
    }
}

// If still no shop available, show an error
if (!$nearest_shop) {
    $error_message = "No shops are currently available. Please try again later.";
} else {
    $shop_id = $nearest_shop['shop_id'];
    
    // Get all inventory items for this shop
    $stmt = $conn->prepare("SELECT i.item_id, i.name, i.description, i.sale_price, inv.quantity 
                          FROM Items i
                          JOIN Inventory inv ON i.item_id = inv.item_id
                          WHERE inv.shop_id = ? AND inv.quantity > 0 AND inv.is_active = 1
                          ORDER BY i.name");
    $stmt->bind_param("i", $shop_id);
    $stmt->execute();
    $items = $stmt->get_result();
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
        
        // Record the transaction
        $stmt = $conn->prepare("INSERT INTO Shop_Transaction (shop_id, user_id, total_amount, payment_method, transaction_status, transaction_date) 
                                VALUES (?, ?, ?, ?, 'Completed', NOW())");
        $stmt->bind_param("isds", $shop_id, $user_id, $total_amount, $payment_method);
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
        
        // Redirect with success message
        header("Location: customer_dashboard.php?order=received&type=shop");
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
    <title>Postal Shop</title>
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
    <?php include '_public_nav.php'; ?>

    <div class="container mx-auto px-4 py-8 max-w-6xl mt-[60px]">
        <header class="mb-6">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center">
                <h1 class="text-3xl font-bold section-title">Postal Shop</h1>
                <?php if (isset($nearest_shop)): ?>
                <div class="mt-4 md:mt-0 bg-white shadow-sm p-4 rounded-lg">
                    <p class="mb-1"><span class="font-semibold">Customer:</span> <?= htmlspecialchars($name) ?></p>
                    <p><span class="font-semibold">Shopping at:</span> <?= htmlspecialchars($nearest_shop['shop_name']) ?> (<?= htmlspecialchars($nearest_shop['city']) ?>)</p>
                </div>
                <?php endif; ?>
            </div>
        </header>
        
        <nav class="bg-white shadow-sm p-4 rounded-lg mb-6">
            <ul class="flex flex-wrap space-x-2 md:space-x-6">
                <li><a href="customer_dashboard.php" class="text-gray-700 hover:text-[#DA291C]">Dashboard</a></li>
                <li><a href="shop_purchase_history.php" class="text-gray-700 hover:text-[#DA291C]">Purchase History</a></li>
    </ul>
  </nav>

        <main>
            <?php if (isset($error_message)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($nearest_shop) && $nearest_shop['item_count'] > 0): ?>
            <form action="shop.php" method="post" id="order-form">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="bg-white p-6 rounded-lg shadow-sm lg:col-span-2">
                        <h2 class="text-2xl font-semibold mb-4 text-[#004B87]">Available Items</h2>
                        <div class="mb-4">
                            <input type="text" id="item-search" class="w-full border border-gray-300 rounded px-3 py-2" 
                                   placeholder="Search for items..." autocomplete="off">
                        </div>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 mt-4">
                            <?php if ($items->num_rows > 0): ?>
                                <?php while ($item = $items->fetch_assoc()): ?>
                                    <div class="item-card <?= $item['quantity'] <= 0 ? 'out-of-stock' : '' ?>" 
                                         data-id="<?= $item['item_id'] ?>"
                                         data-name="<?= htmlspecialchars($item['name']) ?>"
                                         data-price="<?= $item['sale_price'] ?>"
                                         data-stock="<?= $item['quantity'] ?>">
                                        <?php if ($item['quantity'] <= 0): ?>
                                            <span class="sold-out-badge">Sold Out</span>
                                        <?php endif; ?>
                                        <div class="item-name"><?= htmlspecialchars($item['name']) ?></div>
                                        <div class="item-description"><?= htmlspecialchars(substr($item['description'], 0, 60)) . (strlen($item['description']) > 60 ? '...' : '') ?></div>
                                        <div class="flex justify-between items-center mt-auto">
                                            <div>
                                                <div class="item-price">$<?= number_format($item['sale_price'], 2) ?></div>
                                            </div>
                                            <?php if ($item['quantity'] > 0): ?>
                                                <button type="button" class="add-to-cart-btn" onclick="addToCart(this.parentNode.parentNode)">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p class="text-gray-600 col-span-3">No items available in this shop.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="bg-white p-6 rounded-lg shadow-sm">
                        <h2 class="text-2xl font-semibold mb-4 text-[#004B87]">Your Cart</h2>
                        
                        <div class="bg-gray-50 p-4 rounded-lg mb-4 min-h-48">
                            <div id="cart-items">
                                <p id="empty-cart-message" class="text-gray-500 text-center py-8">Your cart is empty. Click on items to add them to your cart.</p>
                            </div>
                            
                            <div class="flex justify-between items-center border-t border-gray-200 pt-4 mt-4">
                                <span class="text-lg font-semibold">Total:</span>
                                <span class="text-xl font-bold text-green-600">$<span id="cart-total">0.00</span></span>
                            </div>
    </div>

                        <div class="mb-4">
                            <label for="payment_method" class="block mb-2 font-medium">Payment Method:</label>
                            <select name="payment_method" id="payment_method" class="w-full border border-gray-300 rounded px-3 py-2" required>
                                <option value="Credit Card">Credit Card</option>
                                <option value="PayPal">PayPal</option>
                            </select>
          </div>
                        
                        <div class="flex justify-between mt-6">
                            <button type="button" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded" onclick="clearCart()">
                                Clear Cart
                            </button>
                            <button type="submit" name="place_order" class="accent-btn text-white px-6 py-2 rounded font-medium" id="checkout-btn" disabled>
                                Place Order
                </button>
                        </div>
                    </div>
                </div>
            </form>
            <?php else: ?>
            <div class="bg-white p-6 rounded-lg shadow-sm text-center">
                <i class="fas fa-store-slash text-gray-400 text-5xl mb-4"></i>
                <h2 class="text-2xl font-semibold mb-2 text-[#004B87]">No Items Available</h2>
                <p class="text-gray-600 mb-4">
                    The nearest shop doesn't have any items available right now. Please check back later.
                </p>
                <a href="customer_dashboard.php" class="action-btn inline-block text-white px-6 py-2 rounded">
                    Return to Dashboard
                </a>
            </div>
            <?php endif; ?>
        </main>
        
        <footer class="text-center mt-10 py-6 bg-[#004B87] text-white rounded-lg">
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