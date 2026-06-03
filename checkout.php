<?php
require "functions.php";
session_start();
// Load cart
$cart = $_SESSION['cart'] ?? [];

if (empty($cart)) {
    header("Location: cart.php");
    exit();
}

// Get product info for items in cart (shop only)
$productsInCart = [];
$total = 0.00;

foreach ($cart as $productId => $qty) {
    $product = getProductById($productId);
    if ($product) {
        $product['quantity'] = $qty;
        $product['subtotal'] = $qty * $product['sale_price'];
        $productsInCart[] = $product;
        $total += $product['subtotal'];
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Checkout | Postal Pro</title>
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

    form {
      background: white;
      padding: 25px;
      border-radius: 10px;
      box-shadow: 0 5px 15px rgba(0,0,0,0.05);
      margin-bottom: 40px;
    }

    label {
      display: block;
      margin-top: 15px;
      font-weight: bold;
    }

    input {
      width: 100%;
      padding: 10px;
      margin-top: 5px;
      border: 1px solid #ccc;
      border-radius: 5px;
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
      border: none;
      cursor: pointer;
    }

    .checkout-btn:hover {
      background: #b52218;
    }
    </style>
</head>
<body>

<h1>Checkout</h1>

<form action="invoice.php" method="POST">
    <label>First Name:</label>
    <input type="text" name="first_name" required>

    <label>Last Name:</label>
    <input type="text" name="last_name" required>

    <label>Email:</label>
    <input type="email" name="email" required>

    <label>Address:</label>
    <input type="text" name="address" required>

    <label>City:</label>
    <input type="text" name="city" required>

    <label>State:</label>
    <input type="text" name="state" required>

    <label>Postal Code:</label>
    <input type="text" name="postal_code" required>

    <input type="hidden" name="total_amount" value="<?= $total ?>">

    <button type="submit" class="checkout-btn">Place Order</button>
</form>

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
        <td>$<?= number_format($item['subtotal'], 2) ?></td>
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

</body>
</html>
