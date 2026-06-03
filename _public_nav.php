<?php
/**
 * Shared public-facing navigation bar.
 * Optional variable to set before including:
 *   $base_path (string) — URL prefix for pages in subdirectories, e.g. '../'
 */
$base_path = $base_path ?? '';
$_nav_logged_in  = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$_nav_user_role  = $_nav_logged_in ? ($_SESSION['role'] ?? '') : '';
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    .pp-nav {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        background: #004B87;
        padding: 0 5%;
        position: fixed;
        width: 100%;
        top: 0;
        z-index: 200;
        left: 0;
        height: 60px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.25);
        font-family: 'Open Sans', sans-serif;
    }
    .pp-nav .pp-logo {
        font-weight: 800;
        font-size: 1.6rem;
        color: #fff;
        letter-spacing: -0.5px;
        text-decoration: none;
        transition: color 0.2s;
    }
    .pp-nav .pp-logo:hover { color: #DA291C; }
    .pp-nav ul {
        display: flex;
        list-style: none;
        gap: 4px;
        margin: 0;
        padding: 0;
        align-items: center;
    }
    .pp-nav ul li a {
        color: #fff;
        font-size: 0.92rem;
        padding: 7px 13px;
        border-radius: 4px;
        text-decoration: none;
        transition: background 0.2s;
        display: flex;
        align-items: center;
        gap: 6px;
        white-space: nowrap;
        font-family: 'Open Sans', sans-serif;
    }
    .pp-nav ul li a:hover { background: rgba(255,255,255,0.15); }
    .pp-nav ul li a.pp-cta {
        background: #DA291C;
        font-weight: 600;
    }
    .pp-nav ul li a.pp-cta:hover { background: #b52218; }
    .pp-menu-toggle {
        display: none;
        font-size: 22px;
        color: #fff;
        cursor: pointer;
    }
    /* Font Awesome fix — prevent Open Sans from overriding icon font */
    .pp-nav .fa, .pp-nav .fas, .pp-nav .far, .pp-nav .fab,
    .pp-nav .fa::before, .pp-nav .fas::before, .pp-nav .far::before, .pp-nav .fab::before {
        font-family: "Font Awesome 6 Free", "Font Awesome 6 Brands" !important;
    }
    @media (max-width: 640px) {
        .pp-menu-toggle { display: block; }
        .pp-nav ul {
            position: fixed;
            top: 0; right: -260px;
            width: 260px; height: 100vh;
            background: #003366;
            flex-direction: column;
            padding-top: 70px;
            transition: right 0.3s;
            z-index: 150;
            box-shadow: -5px 0 15px rgba(0,0,0,0.15);
            gap: 0;
        }
        .pp-nav ul.pp-nav-open { right: 0; }
        .pp-nav ul li a { padding: 14px 20px; border-radius: 0; }
    }
</style>
<nav class="pp-nav">
    <a href="<?= $base_path ?>index.php" class="pp-logo">POSTAL PRO</a>
    <i class="fas fa-bars pp-menu-toggle" id="ppMenuToggle"></i>
    <ul id="ppSidemenu">
        <?php if ($_nav_logged_in): ?>
            <?php if ($_nav_user_role === 'Employee' || $_nav_user_role === 'Admin'): ?>
                <li><a href="<?= $base_path ?>employee_dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a></li>
            <?php else: ?>
                <li><a href="<?= $base_path ?>customer_dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a></li>
            <?php endif; ?>
            <li><a href="<?= $base_path ?>logout.php" class="pp-cta"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        <?php else: ?>
            <li><a href="<?= $base_path ?>login.php">Sign In</a></li>
            <li><a href="<?= $base_path ?>register.php" class="pp-cta">Get Started</a></li>
        <?php endif; ?>
    </ul>
</nav>
<script>
(function () {
    var toggle = document.getElementById('ppMenuToggle');
    var menu   = document.getElementById('ppSidemenu');
    if (!toggle || !menu) return;
    toggle.addEventListener('click', function () {
        menu.classList.toggle('pp-nav-open');
        this.classList.toggle('fa-times');
        this.classList.toggle('fa-bars');
    });
    window.addEventListener('scroll', function () {
        document.querySelector('.pp-nav').style.boxShadow =
            window.scrollY > 10 ? '0 4px 28px rgba(0,0,0,0.45)' : '0 2px 8px rgba(0,0,0,0.25)';
    });
})();
</script>
