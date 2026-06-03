<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Informed Delivery | POSTAL PRO</title>
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
            background-color: #F7F4EB;
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
            background: #DDEDEA;
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
            color: #000;
            text-decoration: none;
            letter-spacing: -1px;
            transition: all 0.3s ease;
        }
        
        .logo:hover {
            color: #FF8D8D;
        }
        
        nav ul {
            display: flex;
            list-style: none;
        }
        
        nav ul li {
            margin: 0 15px;
        }
        
        nav ul li a {
            color: #000;
            text-decoration: none;
            font-size: 1.2rem;
            position: relative;
            transition: color 0.3s;
            padding: 10px;
        }
        
        nav ul li a:hover {
            color: #FF8D8D;
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
        
        .benefits {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin: 40px 0;
        }
        
        .benefit {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .benefit:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        }
        
        .benefit i {
            font-size: 3rem;
            color: #FF8D8D;
            margin-bottom: 20px;
        }
        
        .benefit h3 {
            margin-bottom: 15px;
            color: #333;
            font-weight: 400;
        }
        
        .benefit p {
            color: #666;
            line-height: 1.6;
        }
        
        .signup-cta {
            text-align: center;
            margin: 50px 0;
        }
        
        .cta-button {
            display: inline-block;
            padding: 12px 30px;
            background: #FF8D8D;
            color: white;
            border-radius: 5px;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .cta-button:hover {
            background: #e67e7e;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .copyright {
            text-align: center;
            padding: 20px;
            background-color: #DDEDEA;
            color: #000;
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
            color: #FF8D8D;
        }
        
        /* Mobile menu styles */
        #sidemenu {
            display: flex;
        }

        .menu-toggle {
            display: none;
            font-size: 25px;
            color: #000;
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
                background: #DDEDEA;
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
            
            .benefits {
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
                <li><a href="register.php">Register</a></li>
                <li><a href="login.php">Sign In</a></li>
                <li><a href="click-n-ship.php">Ship</a></li>
                <li><a href="contact.php">Contact</a></li>
            </ul>
        </nav>
    </div>

    <main class="container">
        <div class="service-header">
            <h1>Informed Delivery®</h1>
            <p>Digitally preview your incoming mail and packages</p>
        </div>
        
        <div class="benefits">
            <div class="benefit">
                <i class="fas fa-envelope"></i>
                <h3>Mail Preview</h3>
                <p>See grayscale images of your letter-sized mail pieces before they arrive in your mailbox.</p>
            </div>
            <div class="benefit">
                <i class="fas fa-box"></i>
                <h3>Package Tracking</h3>
                <p>Track all your packages in one convenient place with automatic delivery notifications.</p>
            </div>
            <div class="benefit">
                <i class="fas fa-bell"></i>
                <h3>Delivery Alerts</h3>
                <p>Get real-time notifications when your mail and packages are on their way.</p>
            </div>
            <div class="benefit">
                <i class="fas fa-mobile-alt"></i>
                <h3>Mobile Access</h3>
                <p>Manage your mail from anywhere with our easy-to-use mobile app and website.</p>
            </div>
        </div>
        
        <div class="signup-cta">
            <a href="register.php" class="cta-button">Sign Up Now</a>
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