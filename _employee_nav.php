<?php
/**
 * Shared employee/manager navigation bar — matches admin/_nav.php style.
 * Set before including:
 *   $emp_base (string) — relative path prefix, e.g. '../' for subdirectories, '' for root
 */
$emp_base = $emp_base ?? '';
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
    .pp-nav {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #004B87;
        padding: 0 40px;
        position: sticky;
        width: 100%;
        top: 0;
        z-index: 200;
        height: 60px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.25);
        font-family: 'Open Sans', sans-serif;
    }
    .pp-logo {
        font-weight: 800;
        font-size: 1.5rem;
        color: #fff;
        letter-spacing: -0.5px;
        text-decoration: none;
        transition: color 0.2s;
        flex-shrink: 0;
    }
    .pp-logo:hover { color: #DA291C; }
    .pp-nav-links {
        display: flex;
        list-style: none;
        gap: 4px;
        margin: 0;
        padding: 0;
        align-items: center;
    }
    .pp-nav-links li a {
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
    .pp-nav-links li a:hover { background: rgba(255,255,255,0.15); }
    .pp-nav-links li a.pp-cta {
        background: #DA291C;
        font-weight: 600;
    }
    .pp-nav-links li a.pp-cta:hover { background: #b52218; }
    .fa, .fas, .far, .fab,
    .fa::before, .fas::before, .far::before, .fab::before {
        font-family: "Font Awesome 6 Free", "Font Awesome 6 Brands" !important;
    }
</style>
<nav class="pp-nav">
    <a href="<?= $emp_base ?>employee_dashboard.php" class="pp-logo">POSTAL PRO</a>
    <ul class="pp-nav-links">
        <li><a href="<?= $emp_base ?>employee_dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a></li>
        <li><a href="<?= $emp_base ?>logout.php" class="pp-cta"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</nav>
