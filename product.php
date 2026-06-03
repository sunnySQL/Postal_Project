<?php
require "functions.php";
session_start(); // needed for cart

$product = null;
$title = "";

if (isset($_GET['title'])) {
    $title = urldecode($_GET['title']);
    $product = getProductByTitle($title);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <meta name="description" content="We have a wide collection of electronics, phones, books, and games">
  <meta name="keywords" content="envelopes, packages, office-supplies, mail">
  <link rel="stylesheet" href="css/style.css">
  <title><?= htmlspecialchars($title) ?></title>
</head>
<body>
  <div class="container">
    <header>
      <h1 class="logo"><a href="index.php">POST</a></h1>
      <nav class="navbar">
        <ul>
          <li><a href="index.php">Mail</a></li>
          <li><a href="shop.php">Shop</a></li>
          <li><a href="register.php">Register</a></li>
          <li><a href="login.php">Sign-In</a></li>
          <li><a href="contact.php">Contact</a></li>
        </ul>
      </nav>
    </header>

    <main>
      <div class="left">
        <div class="section-title">Product Categories</div>
        <?php $categories = getCategories(); ?>
        <?php foreach ($categories as $category): ?>
          <a href="category.php?category=<?= urlencode($category['category']); ?>">
            <?= ucfirst($category['category']); ?>
          </a>
        <?php endforeach; ?>
      </div>

      <div class="right">
        <div class="section-title">Product Details</div>

        <?php if ($product): ?>
          <div class="product">
            <div class="product-left">
              <img src="css/img/<?= htmlspecialchars($product['image']); ?>" alt="Product Image" style="width:100%; max-height:300px; object-fit:cover; border-radius:10px;">
            </div>
            <div class="product-right" style="padding-top: 15px;">
              <p class="title" style="font-size: 1.5rem; font-weight: bold; color: #004B87;">
                <?= htmlspecialchars($product['name']); ?>
              </p>
              <p class="description" style="color: #555; margin: 15px 0; font-size: 1rem;">
                <?= htmlspecialchars($product['description']); ?>
              </p>
              <p class="price" style="font-size: 1.2rem; font-weight: bold; color: #DA291C;">
                $<?= number_format($product['sale_price'], 2); ?>
              </p>
              <form action="cart.php" method="post" style="margin-top: 20px;">
                <input type="hidden" name="product_id" value="<?= $product['item_id']; ?>">
                <button type="submit" style="padding:10px 20px; background:#004B87; color:white; border:none; border-radius:5px; cursor:pointer; font-weight: bold;">
                  Add to Cart
                </button>
              </form>
            </div>
          </div>
        <?php else: ?>
          <p style="color: red;">Product not found.</p>
        <?php endif; ?>
      </div>
    </main>

    <footer>
      <p>&copy; <?= date('Y') ?> Postal Service Management System</p>
    </footer>
  </div>
</body>
</html>
