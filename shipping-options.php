<?php
session_start();
$logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$user_role = $logged_in ? $_SESSION['role'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Shipping Options | POSTAL PRO</title>
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
        
        .service-header {
            margin-top: 100px;
            text-align: center;
            padding: 50px 0;
        }
        
        .service-header h1 {
            font-size: 2.8rem;
            margin-bottom: 20px;
            font-weight: 400;
            color: #333;
        }
        
        .service-header p {
            font-size: 1.2rem;
            color: #666;
        }
        
        .options {
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
            margin: 40px 0;
        }
        
        .additional-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin: 40px 0;
        }
        
        .option {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .option:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        }
        
        .option h3 {
            color: #004B87;
            margin-bottom: 15px;
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .option p {
            margin-bottom: 10px;
            color: #666;
            line-height: 1.6;
        }
        
        .option i {
            color: #004B87;
            margin-right: 8px;
        }
        
        .ship-now {
            text-align: center;
            margin: 50px 0;
        }
        
        .cta-button {
            display: inline-block;
            padding: 12px 30px;
            background: #DA291C;
            color: white;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .cta-button:hover {
            background: #b52218;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
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
            
            .service-header {
                margin-top: 80px;
                padding: 40px 0;
            }
            
            .service-header h1 {
                font-size: 2.2rem;
            }
            
            .options,
            .additional-options {
                grid-template-columns: 1fr;
            }

            .copyright {
                position: relative;
                margin-top: 60px;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div id="header">
        <nav>
            <div class="logo-container">
                <a href="index.php" class="logo">POSTAL PRO</a>
            </div>
            <i class="fas fa-bars menu-toggle" id="menuToggle"></i>
            <ul id="sidemenu">
                <?php if($logged_in):
                    if($user_role == 'Employee' || $user_role == 'Admin'): ?>
                        <li><a href="employee_dashboard.php">Dashboard</a></li>
                    <?php else: ?>
                        <li><a href="customer_dashboard.php">Dashboard</a></li>
                    <?php endif; ?>
                    <li><a href="package/new_package.php">Ship</a></li>
                    <li><a href="support/contact.php">Contact</a></li>
                    <li><a href="logout.php">Logout</a></li>
                <?php else: ?>
                    <li><a href="login.php">Sign In</a></li>
                    <li><a href="package/new_package.php">Ship</a></li>
                    <li><a href="support/contact.php">Contact</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>

    <main class="container">
        <div class="service-header">
            <h1>Shipping Options</h1>
            <p>Reliable delivery options to fit your schedule and budget</p>
        </div>
        
        <div class="options">
            <div class="option">
                <h3>Economy</h3>
                <p><i class="fas fa-truck"></i> 5-7 business days</p>
                <p><i class="fas fa-tag"></i> 20% Discount off standard rate</p>
                <p><i class="fas fa-box"></i> All package sizes available</p>
                <p><i class="fas fa-check-circle"></i> Free tracking included</p>
            </div>
            <div class="option">
                <h3>Standard</h3>
                <p><i class="fas fa-truck-loading"></i> 3-5 business days</p>
                <p><i class="fas fa-dollar-sign"></i> Regular shipping rate</p>
                <p><i class="fas fa-box"></i> All package sizes available</p>
                <p><i class="fas fa-shield-alt"></i> Signature confirmation available</p>
            </div>
            <div class="option">
                <h3>Express</h3>
                <p><i class="fas fa-bolt"></i> 1-2 business days</p>
                <p><i class="fas fa-percentage"></i> 70% Premium over standard rate</p>
                <p><i class="fas fa-box"></i> All package sizes available</p>
                <p><i class="fas fa-globe-americas"></i> Priority handling and delivery</p>
            </div>
        </div>
        
        <div class="additional-options">
            <div class="option">
                <h3>Package Sizes</h3>
                <p><i class="fas fa-envelope"></i> Small, Medium & Large Envelopes</p>
                <p><i class="fas fa-box"></i> Small, Medium & Large Boxes</p>
                <p><i class="fas fa-box-open"></i> Extra Large Box for bulky items</p>
                <p><i class="fas fa-info-circle"></i> Custom packaging available at locations</p>
            </div>
            <div class="option">
                <h3>Additional Services</h3>
                <p><i class="fas fa-signature"></i> Signature Required ($3.50 fee)</p>
                <p><i class="fas fa-hand-holding-usd"></i> Insurance options available</p>
                <p><i class="fas fa-file-alt"></i> Detailed tracking information</p>
                <p><i class="fas fa-bell"></i> Delivery notifications</p>
            </div>
        </div>
        
        <div class="ship-now">
            <a href="sendpackage.php" class="cta-button">Ship Now</a>
        </div>
    </main>

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

        // CTA button animation
        const ctaButton = document.querySelector('.cta-button');
        ctaButton.addEventListener('mouseenter', () => {
            ctaButton.style.transform = 'translateY(-3px)';
            ctaButton.style.boxShadow = '0 5px 15px rgba(0,0,0,0.1)';
        });
        ctaButton.addEventListener('mouseleave', () => {
            ctaButton.style.transform = 'translateY(0)';
            ctaButton.style.boxShadow = 'none';
        });
    </script>
</body>
</html>