<?php
require "functions.php";
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Customer') {
  header("Location: login.php");
  exit();
}

$user_id = $_SESSION['user_id'];

// Initialize cart
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle removing from cart FIRST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_id'])) {
    $removeId = (int)$_POST['remove_id'];
    if (isset($_SESSION['cart'][$removeId])) {
        unset($_SESSION['cart'][$removeId]);
    }
    header("Location: cart.php?removed=true");
    exit;
}

// Handle adding to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
    $id = (int)$_POST['product_id'];

    if (!isset($_SESSION['cart'][$id])) {
        $_SESSION['cart'][$id] = 1;
    } else {
        $_SESSION['cart'][$id]++;
    }

    header("Location: cart.php?added=true");
    exit;
}


// Get product info for items in cart
$productsInCart = [];
$total = 0.00;

foreach ($_SESSION['cart'] as $productId => $qty) {
    $product = getProductById($productId); // You'll need this helper function
    if ($product) {
        $product['quantity'] = $qty;
        $product['subtotal'] = $qty * $product['sale_price'];
        $productsInCart[] = $product;
        $total += $product['subtotal'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Your Cart | Postal Pro</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
  <style>
    body {
      font-family: 'Open Sans', sans-serif;
      background: #f8f9fa;
      padding: 80px 10%;
    }

    h1 {
      color: #004B87;
      margin-bottom: 20px;
    }

    table {
      width: 100%;
      background: white;
      border-collapse: collapse;
      border-radius: 10px;
      overflow: hidden;
      box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    }

    th, td {
      padding: 15px;
      border-bottom: 1px solid #ddd;
      text-align: left;
    }

    th {
      background: #004B87;
      color: white;
    }

    tfoot td {
      font-weight: bold;
    }

    .checkout-btn {
      display: inline-block;
      margin-top: 20px;
      padding: 12px 25px;
      background: #DA291C;
      color: white;
      border-radius: 5px;
      text-decoration: none;
      font-weight: bold;
      transition: background 0.3s;
    }

    .checkout-btn:hover {
      background: #b52218;
    }
  </style>
</head>
<body>
  <h1>Your Cart</h1>

  <?php if (empty($productsInCart)): ?>
    <p>Your cart is empty.</p>
  <?php else: ?>
    <div style="text-align:center;">
  <a href="shop.php" style="display:inline-block;margin:20px 0;padding:10px 20px;background:#004B87;color:white;text-decoration:none;border-radius:5px;">
      ← Back to Shop
  </a>
  <?php if (isset($_GET['added']) && $_GET['added'] === 'true'): ?>
  <div id="success-msg" style="background-color: #d4edda; color: #155724; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 20px auto; width: fit-content; text-align: center;">
    Item successfully added to cart!
  </div>
<?php endif; ?>
<?php if (isset($_GET['removed']) && $_GET['removed'] === 'true'): ?>
  <div id="success-msg" style="background-color: #f8d7da; color: #721c24; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 20px auto; width: fit-content; text-align: center;">
    ❌ Item removed from cart.
  </div>
<?php endif; ?>


</div>
    <table>
      <thead>
        <tr>
          <th>Product</th>
          <th>Qty</th>
          <th>Price</th>
          <th>Subtotal</th>
        </tr>
      </thead>
      <tbody>
            <?php foreach ($productsInCart as $item): ?>
                <tr>
                <td><?= htmlspecialchars($item['name']) ?></td>
                <td><?= $item['quantity'] ?></td>
                <td>$<?= number_format($item['sale_price'], 2) ?></td>
                <td>
                    $<?= number_format($item['subtotal'], 2) ?>
                    <form method="post" class="remove-form" style="display:inline;">
                    <input type="hidden" name="remove_id" value="<?= $item['item_id'] ?>">
                    <button type="button" class="remove-btn" style="margin-left:10px;padding:5px 10px;background:#ffcc00;color:#333;border:none;border-radius:3px;cursor:pointer;">
                        🗑️ Remove
                    </button>
                    <button type="submit" class="confirm-btn" style="display:none;padding:5px 10px;background:#DA291C;color:white;border:none;border-radius:3px;cursor:pointer;">
                        ❌ Confirm?
                    </button>
                    </form>
                </td>
                </tr>
            <?php endforeach; ?>
        </tbody>

      <tfoot>
        <tr>
          <td colspan="3">Total</td>
          <td>$<?= number_format($total, 2) ?></td>
        </tr>
      </tfoot>
    </table>

    <a href="checkout.php" class="checkout-btn">Proceed to Checkout</a>
  <?php endif; ?>
  <script>
  // Fade out message
  setTimeout(() => {
    const msg = document.getElementById('success-msg');
    if (msg) {
      msg.style.transition = "opacity 0.5s ease";
      msg.style.opacity = 0;
      setTimeout(() => msg.remove(), 500);
    }
  }, 2500);

  // Transform "Remove" into "Confirm?" on click
  document.querySelectorAll('.remove-btn').forEach((btn) => {
    btn.addEventListener('click', function () {
      const form = this.closest('.remove-form');
      this.style.display = 'none';
      form.querySelector('.confirm-btn').style.display = 'inline-block';
    });
  });
</script>


</body>
</html>
