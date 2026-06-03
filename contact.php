<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Contact Us | POSTAL PRO</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
       @import url('https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap');

        * {
            margin: 0;
            padding: 0;
            font-family: 'Open Sans', sans-serif;
            box-sizing: border-box;
        }
        
        body {
            background-color: #f8f9fa;
            color: #333;
            position: relative;
            min-height: 100vh;
            padding-bottom: 60px;
        }
        
        .container {
            padding: 10px 10%;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            background: #004B87;
            padding: 15px 10%;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 100;
            left: 0;
            box-shadow: 0px 2px 5px rgba(0, 0, 0, 0.2);
        }
        
        .logo-container {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .logo {
            font-family: 'Open Sans', sans-serif;
            font-weight: 700; 
            font-size: 2rem;
            color: #ffffff;
            text-decoration: none;
            letter-spacing: -1px;
            transition: all 0.3s ease;
        }
        
        .logo:hover {
            color: #DA291C;
        }
        
        nav ul {
            display: flex;
            list-style: none;
        }
        
        nav ul li {
            margin: 0 15px;
        }
        
        nav ul li a {
            color: #ffffff;
            text-decoration: none;
            font-size: 1.2rem;
            position: relative;
            transition: color 0.3s;
            padding: 10px;
        }
        
        nav ul li a:hover {
            color: #DA291C;
        }
        
        #contact {
            padding: 100px 0 60px;
        }
        
        .contact-container {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            margin-top: 50px;
        }
        
        .contact-left {
            flex-basis: 35%;
        }
        
        .contact-right {
            flex-basis: 60%;
        }
        
        .contact-left h1 {
            font-size: 2.5rem;
            margin-bottom: 20px;
            color: #004B87;
            font-weight: 600;
        }
        
        .contact-left p {
            margin-bottom: 30px;
            color: #666;
            line-height: 1.6;
        }
        
        .contact-left .contact-info {
            margin-bottom: 30px;
        }
        
        .contact-left .contact-info i {
            color: #004B87;
            margin-right: 15px;
            font-size: 1.2rem;
        }
        
        .contact-left .contact-info div {
            margin: 20px 0;
            display: flex;
            align-items: center;
        }
        
        .contact-right form {
            width: 100%;
        }
        
        form input, form textarea {
            width: 100%;
            border: 0;
            outline: none;
            background: #fff;
            padding: 15px;
            margin: 15px 0;
            color: #333;
            font-size: 1rem;
            border-radius: 6px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        form textarea {
            height: 150px;
            resize: none;
        }
        
        form button {
            padding: 12px 30px;
            background: #004B87;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s;
            margin-top: 10px;
        }
        
        form button:hover {
            background: #003366;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        #msg {
            display: block;
            margin-top: 20px;
            color: #DA291C;
            font-weight: 400;
        }
        
        .copyright {
            text-align: center;
            padding: 20px;
            background-color: #004B87;
            color: #ffffff;
            position: fixed;
            bottom: 0;
            width: 100%;
            font-size: 0.9rem;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            box-shadow: 0px -2px 5px rgba(0, 0, 0, 0.1);
        }
        
        .footer-logo {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .footer-knight {
            color: #DA291C;
        }
        
        /* Mobile menu styles */
        #sidemenu {
            display: flex;
        }

        .menu-toggle {
            display: none;
            font-size: 25px;
            color: #ffffff;
            cursor: pointer;
            z-index: 101;
        }

        @media only screen and (max-width: 768px) {
            body {
                padding-bottom: 0;
            }
            
            .menu-toggle {
                display: block;
            }
            
            nav ul {
                position: fixed;
                top: 0;
                right: -250px;
                width: 250px;
                height: 100vh;
                background: #003366;
                flex-direction: column;
                padding-top: 80px;
                transition: right 0.3s;
                z-index: 100;
                box-shadow: -5px 0 15px rgba(0,0,0,0.1);
            }
            
            nav ul.show {
                right: 0;
            }
            
            nav ul li {
                margin: 20px;
            }
            
            .contact-container {
                flex-direction: column;
            }
            
            .contact-left, .contact-right {
                flex-basis: 100%;
            }
            
            .contact-right {
                margin-top: 30px;
            }
            
            .copyright {
                position: relative;
                margin-top: 60px;
            }
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 6px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .btn-link {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 30px;
            background: #DA291C;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-link:hover {
            background: #b52218;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <?php
    session_start();
    require_once 'db_connect.php';
    
    $message = '';
    $error = '';
    $is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    
    // If user is logged in, redirect them to the support page
    if ($is_logged_in) {
        header("Location: support.php");
        exit();
    }
    
    // Process the contact form
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = $_POST['Name'] ?? '';
        $email = $_POST['Email'] ?? '';
        $message_text = $_POST['Message'] ?? '';
        
        if (empty($name) || empty($email) || empty($message_text)) {
            $error = "Please fill in all fields.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {
            // Store in database or send email
            // For now, just display a success message
            $message = "Thank you for your message! We will get back to you soon.";
            
            // In a real implementation, you might:
            // 1. Check if user exists with this email
            // 2. If yes, create a support ticket
            // 3. If not, send an email to support
            
            // Reset form
            $name = $email = $message_text = '';
        }
    }
    ?>

    <div id="header">
        <nav>
            <div class="logo-container">
                <a href="index.php" class="logo">POSTAL PRO</a>
            </div>
            <i class="fas fa-bars menu-toggle" id="menuToggle"></i>
            <ul id="sidemenu">
                <li><a href="register.php">Register</a></li>
                <li><a href="login.php">Sign In</a></li>
                <li><a href="click-n-ship.php">Ship</a></li>
            </ul>
        </nav>
    </div>

    <div id="contact">
        <div class="container">
            <?php if (!empty($message)): ?>
            <div class="alert alert-success">
                <p><?= htmlspecialchars($message) ?></p>
                <p>For faster support in the future, please <a href="register.php" style="color: #004B87; text-decoration: underline;">register</a> or <a href="login.php" style="color: #004B87; text-decoration: underline;">login</a> to access our complete support system.</p>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <p><?= htmlspecialchars($error) ?></p>
            </div>
            <?php endif; ?>
            
            <div class="contact-container">
                <div class="contact-left">
                    <h1>Contact Us</h1>
                    <p>Have questions or need assistance with your shipments? Our customer support team is here to help.</p>
                    
                    <div class="contact-info">
                        <div>
                            <i class="fas fa-paper-plane"></i>
                            <p>contact@postalpro.com</p>
                        </div>
                        <div>
                            <i class="fas fa-phone"></i>
                            <p>1-800-POSTAL-PRO</p>
                        </div>
                        <div>
                            <i class="fas fa-map-marker-alt"></i>
                            <p>123 Shipping Lane, Logistics City, LC 12345</p>
                        </div>
                    </div>
                    
                    <p>Already have an account? Login to access our full support center.</p>
                    <a href="login.php" class="btn-link">Sign In For Support</a>
                </div>
                <div class="contact-right">
                    <form action="contact.php" method="post">
                        <input type="text" name="Name" placeholder="Your Name" required value="<?= htmlspecialchars($name ?? '') ?>">
                        <input type="email" name="Email" placeholder="Your Email" required value="<?= htmlspecialchars($email ?? '') ?>">
                        <textarea name="Message" placeholder="Your Message" required><?= htmlspecialchars($message_text ?? '') ?></textarea>
                        <button type="submit">Send Message</button>
                    </form>
                    <span id="msg"></span>
                </div>
            </div>
        </div>
    </div>

    <div class="copyright">
        <footer>
            <div class="footer-logo">
                <span>&copy; <?= date('Y') ?> Postal Service Management System. All rights reserved. Powered by the </span>
                <span>Postal Pro Team <i class="fas fa-chess-knight footer-knight"></i></span>
            </div>
        </footer>
    </div>

    <script>
        // Mobile menu toggle
        const menuToggle = document.getElementById('menuToggle');
        const sideMenu = document.getElementById('sidemenu');
        
        menuToggle.addEventListener('click', function() {
            sideMenu.classList.toggle('show');
            this.classList.toggle('fa-times');
        });
    </script>
</body>
</html>