<?php
require "functions.php"; // Ensure this line is at the top of your file
?>
<?php
if(isset($_GET['category'])) {
    $cat = urldecode($_GET['category']);
}
 ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="We have a wide collection of electronics, phones, books, and games">
    <meta name="keywords" content="envelopes, packages, office-supples, mail">
    <style>
@import url('https://fonts.googleapis.com/css2?family=Rubik+Bubbles&display=swap');
</style>
<style>
@import url('https://fonts.googleapis.com/css2?family=Bad+Script&display=swap');
</style>
    <link rel= "stylesheet" href = "css/style.css">
    <title>Shop</title>

</head>
</div>
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
</div>
   

    <footer>
            <p>&copy; <?= date('Y') ?> Postal Service Management System</p>
        </footer>
</div>


        <main>
            <div class="left">
                <div class="section-title">Product Categories</div>
                <?php $categories = getCategories() ?>
                <?php 
                    foreach($categories as $category) {
                        ?>
                        <a href="category.php?category=<?php echo urlencode($category['category']); ?>" >

                                                <?php echo ucfirst($category['category']) ?>
                          </a>
                        <?php
                    }
                ?>
            </div>

            <div class="right">
                <div class ="section-title"> Products</div>
                <?php $products = getProductsByCategory($cat) ?>
                 <div class = "product">
                    <?php 
                        foreach($products as $product) {
                            ?>
                            <div class="product-left">
                            <img src="css/img/<?php echo $product['image']; ?>" alt="Product Image">

                             </div>
                <div class="product-right">
                    <p class="title">
                        <a href="product.php?title=<?php echo urlencode($product['name'])?>">
                        
                        <?php echo $product['name'] ?>
                    </a>

                    </p>
                    <p class="description">
                       <?php echo $product['description'] ?>
                    </p>            
                    <p class="price">
                        <?php echo $product['sale_price'] ?>
                    </p>
                    </div>  
                            
                            
                            <?php

                        }
                    
                    ?>
        </main>

    </div>
</body>
</html>
