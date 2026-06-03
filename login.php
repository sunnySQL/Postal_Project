<?php
session_start();
require_once 'db_connect.php';
require_once 'functions.php';

$error_message = "";
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email    = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT user_id, password, role, account_status, password_change_required, deleted_at FROM Users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            if (!empty($user['deleted_at'])) {
                $error_message = "This account no longer exists. Please contact support if you believe this is an error.";
            } elseif ($user['account_status'] == 'Active') {
                $_SESSION['logged_in'] = true;
                $_SESSION['user_id']   = $user['user_id'];
                $_SESSION['role']      = $user['role'];

                $stmt = $conn->prepare("UPDATE Users SET last_login = CURRENT_TIMESTAMP WHERE user_id = ?");
                $stmt->bind_param("i", $user['user_id']);
                $stmt->execute();

                if ($user['role'] == 'Customer') {
                    $stmt = $conn->prepare("SELECT first_name, last_name FROM Customer WHERE user_id = ?");
                    $stmt->bind_param("i", $user['user_id']);
                    $stmt->execute();
                    $customer = $stmt->get_result()->fetch_assoc();
                    $_SESSION['name'] = $customer['first_name'] . ' ' . $customer['last_name'];
                    $redirect = $user['password_change_required'] == 1 ? "edit_profile.php" : "customer_dashboard.php";
                    if ($is_ajax) {
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode(['ok' => true, 'redirect' => $redirect]);
                        exit();
                    }
                    header("Location: " . $redirect);
                    exit();
                } elseif ($user['role'] == 'Employee' || $user['role'] == 'Admin') {
                    $stmt = $conn->prepare("SELECT first_name, last_name, role AS emp_role, facility_id FROM Employee WHERE user_id = ?");
                    $stmt->bind_param("i", $user['user_id']);
                    $stmt->execute();
                    $employee = $stmt->get_result()->fetch_assoc();
                    $_SESSION['name']        = trim(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? ''));
                    $_SESSION['emp_role']    = $employee['emp_role']    ?? $user['role'];
                    $_SESSION['facility_id'] = $employee['facility_id'] ?? null;

                    // Store unread notification count for login banner
                    $notif_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM Notifications WHERE user_id = ? AND is_read = 0");
                    $notif_stmt->bind_param("i", $user['user_id']);
                    $notif_stmt->execute();
                    $notif_count = (int)($notif_stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
                    if ($notif_count > 0) $_SESSION['login_notif_count'] = $notif_count;

                    $redirect = $user['password_change_required'] == 1 ? "edit_profile.php" : "employee_dashboard.php";
                    if ($is_ajax) {
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode(['ok' => true, 'redirect' => $redirect]);
                        exit();
                    }
                    header("Location: " . $redirect);
                    exit();
                }
            } else {
                $error_message = "Your account is " . htmlspecialchars($user['account_status']) . ". Please contact support.";
            }
        } else {
            $error_message = "Invalid email or password.";
        }
    } else {
        $error_message = "Invalid email or password.";
    }
    if ($is_ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => $error_message]);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In | POSTAL PRO</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700;800&display=swap');

        *, *::before, *::after { margin: 0; padding: 0; font-family: 'Open Sans', sans-serif; box-sizing: border-box; }

        body { background: #f4f6f9; min-height: 100vh; display: flex; flex-direction: column; }
        a { text-decoration: none; }

        /* NAV */
        nav {
            display: flex; align-items: center; justify-content: space-between;
            background: #004B87; padding: 0 5%; height: 60px;
            position: fixed; width: 100%; top: 0; left: 0; z-index: 200;
            box-shadow: 0 2px 8px rgba(0,0,0,0.25);
        }
        .logo { font-weight: 800; font-size: 1.6rem; color: #fff; letter-spacing: -0.5px; transition: color 0.2s; }
        .logo:hover { color: #DA291C; }
        nav ul { display: flex; list-style: none; gap: 5px; }
        nav ul li a { color: #fff; font-size: 0.95rem; padding: 8px 14px; border-radius: 4px; transition: background 0.2s; display: block; }
        nav ul li a:hover { background: rgba(255,255,255,0.15); }
        nav ul li a.nav-cta { background: #DA291C; font-weight: 600; }
        nav ul li a.nav-cta:hover { background: #b52218; }
        .menu-toggle { display: none; font-size: 22px; color: #fff; cursor: pointer; }

        /* PAGE WRAPPER */
        .auth-page {
            flex: 1; display: flex; align-items: stretch;
            min-height: calc(100vh - 60px); margin-top: 60px;
        }

        /* LEFT PANEL */
        .auth-panel-left {
            width: 42%; background: linear-gradient(160deg, #003a6e 0%, #004B87 50%, #0068b5 100%);
            display: flex; flex-direction: column; justify-content: center;
            padding: 60px 52px; color: white; position: relative; overflow: hidden;
        }
        .auth-panel-left::before {
            content: ''; position: absolute; bottom: -80px; right: -80px;
            width: 320px; height: 320px; border-radius: 50%;
            background: rgba(218,41,28,0.08);
        }
        .auth-panel-left::after {
            content: ''; position: absolute; top: -60px; left: -60px;
            width: 200px; height: 200px; border-radius: 50%;
            background: rgba(255,255,255,0.04);
        }
        .panel-logo { font-size: 1.9rem; font-weight: 800; color: white; letter-spacing: -0.5px; margin-bottom: 32px; display: inline-block; }
        .panel-logo:hover { color: #DA291C; }
        .panel-heading { font-size: 1.75rem; font-weight: 800; line-height: 1.2; margin-bottom: 14px; }
        .panel-sub { font-size: 0.95rem; color: rgba(255,255,255,0.7); line-height: 1.7; margin-bottom: 36px; }
        .panel-features { list-style: none; display: flex; flex-direction: column; gap: 14px; }
        .panel-features li { display: flex; align-items: center; gap: 12px; font-size: 0.9rem; color: rgba(255,255,255,0.85); }
        .panel-features li .feat-icon { width: 32px; height: 32px; border-radius: 50%; background: rgba(255,255,255,0.18); display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: rgba(255,255,255,0.9); }

        /* RIGHT PANEL */
        .auth-panel-right {
            flex: 1; display: flex; align-items: center; justify-content: center;
            padding: 60px 40px; background: white;
        }
        .auth-form-wrap { width: 100%; max-width: 400px; }
        .auth-form-wrap h2 { font-size: 1.6rem; font-weight: 800; color: #1a202c; margin-bottom: 6px; }
        .auth-form-wrap p.auth-sub { font-size: 0.9rem; color: #888; margin-bottom: 28px; }

        /* ERROR */
        .alert-error {
            background: #fff0f0; border-left: 4px solid #DA291C; color: #7f1d1d;
            padding: 12px 16px; border-radius: 8px; font-size: 0.88rem;
            display: flex; align-items: center; gap: 10px; margin-bottom: 22px;
        }

        /* FORM */
        .field { margin-bottom: 18px; }
        .field label { display: block; font-size: 0.82rem; font-weight: 700; color: #374151; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px; }
        .input-wrap { position: relative; }
        .input-wrap i.field-icon { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: 0.88rem; pointer-events: none; }
        .field input {
            width: 100%; padding: 11px 14px 11px 38px;
            border: 1.5px solid #e5e7eb; border-radius: 8px;
            font-size: 0.95rem; color: #1a202c;
            transition: border-color 0.2s, box-shadow 0.2s; outline: none;
        }
        .field input:focus { border-color: #004B87; box-shadow: 0 0 0 3px rgba(0,75,135,0.1); }
        .toggle-pw {
            position: absolute; right: 13px; top: 50%; transform: translateY(-50%);
            color: #9ca3af; cursor: pointer; font-size: 0.9rem; background: none; border: none;
            transition: color 0.2s;
        }
        .toggle-pw:hover { color: #004B87; }

        .submit-btn {
            width: 100%; padding: 13px; background: #004B87; color: white;
            border: none; border-radius: 8px; font-size: 1rem; font-weight: 700;
            cursor: pointer; transition: background 0.2s, transform 0.15s;
            margin-top: 6px;
        }
        .submit-btn:hover { background: #003366; transform: translateY(-1px); }
        .submit-btn:active { transform: translateY(0); }

        .divider { display: flex; align-items: center; gap: 12px; margin: 22px 0; }
        .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: #e5e7eb; }
        .divider span { font-size: 0.78rem; color: #aaa; white-space: nowrap; }

        .auth-footer-link { text-align: center; font-size: 0.88rem; color: #666; }
        .auth-footer-link a { color: #004B87; font-weight: 700; transition: color 0.2s; }
        .auth-footer-link a:hover { color: #DA291C; }

        /* Error toast: slides in from right, then fades out (no layout shift) */
        .login-error-toast {
            position: fixed;
            top: 88px;
            right: -380px;
            z-index: 10000;
            max-width: 340px;
            padding: 16px 20px;
            background: #fef2f2;
            border-left: 4px solid #dc2626;
            border-radius: 8px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            gap: 12px;
            opacity: 1;
            transition: right 0.4s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.6s ease;
        }
        .login-error-toast.show { right: 24px; }
        .login-error-toast.fade-out { opacity: 0; right: -380px; pointer-events: none; }
        .login-error-toast .toast-icon { color: #dc2626; font-size: 1.25rem; flex-shrink: 0; }
        .login-error-toast .toast-msg { font-size: 0.9rem; font-weight: 600; color: #991b1b; }

        /* LOGIN OVERLAY: blurred backdrop + centered square card */
        .auth-overlay {
            display: none;
            position: fixed; inset: 0; z-index: 9999;
            background: rgba(26, 46, 74, 0.4);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            align-items: center; justify-content: center;
        }
        .auth-overlay.show { display: flex; }
        .auth-overlay .auth-card {
            width: 200px; height: 200px;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.25);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 16px;
            padding: 24px;
        }
        .auth-overlay .spinner-wrap {
            width: 48px; height: 48px;
            border: 4px solid rgba(0, 75, 135, 0.2);
            border-top-color: #004B87;
            border-radius: 50%;
            animation: login-spin 0.9s linear infinite;
        }
        .auth-overlay .text { font-size: 0.95rem; font-weight: 600; color: #1a2e4a; text-align: center; }
        .auth-overlay.success .spinner-wrap { display: none; }
        .auth-overlay.success .text { color: #166534; }
        .auth-overlay.success .check-wrap { display: flex; }
        .auth-overlay.success .spinner-wrap,
        .auth-overlay.success .fail-wrap,
        .auth-overlay.failed .spinner-wrap,
        .auth-overlay.failed .check-wrap { display: none; }
        .auth-overlay.failed .fail-wrap { display: flex !important; }
        .auth-overlay.failed .text { color: #b91c1c; }
        .auth-overlay .check-wrap { display: none; width: 52px; height: 52px; align-items: center; justify-content: center; }
        .auth-overlay .fail-wrap { display: none; width: 56px; height: 56px; align-items: center; justify-content: center; }
        .auth-overlay .fail-wrap svg { width: 56px; height: 56px; display: block; }
        .auth-overlay .fail-wrap .fail-circle { fill: none; stroke: #dc2626; stroke-width: 2.5; }
        .auth-overlay .fail-wrap .fail-x { fill: none; stroke: #dc2626; stroke-width: 3; stroke-linecap: round; }
        .auth-overlay .check-wrap svg { width: 52px; height: 52px; overflow: visible; }
        .auth-overlay .check-wrap .check-path {
            fill: none;
            stroke: #16a34a;
            stroke-width: 4;
            stroke-linecap: round;
            stroke-linejoin: round;
            stroke-dasharray: 70;
            stroke-dashoffset: 70;
            animation: check-draw 0.5s ease-out forwards;
        }
        .auth-overlay .check-wrap .check-circle {
            fill: none;
            stroke: #16a34a;
            stroke-width: 2.5;
            stroke-dasharray: 151;
            stroke-dashoffset: 151;
            animation: check-circle-draw 0.4s ease-out forwards;
        }
        @keyframes login-spin { to { transform: rotate(360deg); } }
        @keyframes check-draw { to { stroke-dashoffset: 0; } }
        @keyframes check-circle-draw { to { stroke-dashoffset: 0; } }

        /* FOOTER */
        footer { background: #1a2e4a; color: rgba(255,255,255,0.5); text-align: center; padding: 16px; font-size: 0.78rem; }
        .footer-knight { color: #DA291C; }

        /* RESPONSIVE */
        @media (max-width: 820px) {
            .auth-panel-left { display: none; }
            .auth-panel-right { background: #f4f6f9; }
        }
        @media (max-width: 480px) {
            .auth-panel-right { padding: 40px 24px; }
            .menu-toggle { display: block; }
            nav ul {
                position: fixed; top: 0; right: -260px;
                width: 260px; height: 100vh; background: #003366;
                flex-direction: column; padding-top: 70px;
                transition: right 0.3s; z-index: 150;
                box-shadow: -5px 0 15px rgba(0,0,0,0.15); gap: 0;
            }
            nav ul.show { right: 0; }
            nav ul li a { padding: 14px 20px; border-radius: 0; }
        }
    </style>
</head>
<body>

<?php include '_public_nav.php'; ?>

<div class="auth-page">

    <!-- LEFT BRAND PANEL -->
    <div class="auth-panel-left">
        <a href="index.php" class="panel-logo">POSTAL PRO</a>
        <h2 class="panel-heading">Welcome back.<br>Pick up where you left off.</h2>
        <p class="panel-sub">Sign in to access your dashboard, track shipments, and manage your postal services.</p>
        <ul class="panel-features">
            <li>
                <span class="feat-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="10" r="3"/><path d="M12 2a8 8 0 0 0-8 8c0 5.25 8 12 8 12s8-6.75 8-12a8 8 0 0 0-8-8z"/></svg>
                </span>
                Real-time package tracking
            </li>
            <li>
                <span class="feat-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13" rx="2"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                </span>
                Create and manage shipments
            </li>
            <li>
                <span class="feat-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3z"/><path d="M3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"/></svg>
                </span>
                Customer support &amp; tickets
            </li>
            <li>
                <span class="feat-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                </span>
                Postal shop &amp; inventory
            </li>
        </ul>
    </div>

    <!-- RIGHT FORM PANEL -->
    <div class="auth-panel-right">
        <div class="auth-form-wrap">
            <h2>Sign In</h2>
            <p class="auth-sub">Enter your credentials to access your account.</p>

            <form id="loginForm" method="post" action="login.php">
                <div class="field">
                    <label for="email">Email Address</label>
                    <div class="input-wrap">
                        <i class="fas fa-envelope field-icon"></i>
                        <input type="email" id="email" name="email"
                               placeholder="you@example.com" required
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <div class="input-wrap">
                        <i class="fas fa-lock field-icon"></i>
                        <input type="password" id="password" name="password"
                               placeholder="Enter your password" required>
                        <button type="button" class="toggle-pw" id="togglePw" tabindex="-1">
                            <i class="fas fa-eye" id="togglePwIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-arrow-right-to-bracket" style="margin-right:8px;"></i> Sign In
                </button>

            </form>

            <div id="authOverlay" class="auth-overlay" aria-hidden="true">
                <div class="auth-card">
                    <div class="spinner-wrap" aria-hidden="true"></div>
                    <div class="check-wrap" aria-hidden="true">
                        <svg viewBox="0 0 52 52" xmlns="http://www.w3.org/2000/svg">
                            <circle class="check-circle" cx="26" cy="26" r="24"/>
                            <path class="check-path" d="M14 26l9 9 18-22"/>
                        </svg>
                    </div>
                    <div class="fail-wrap" aria-hidden="true">
                        <svg viewBox="0 0 52 52" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <circle class="fail-circle" cx="26" cy="26" r="24"/>
                            <path class="fail-x" d="M18 18l16 16M34 18L18 34"/>
                        </svg>
                    </div>
                    <span class="text">Authenticating…</span>
                </div>
            </div>

            <div class="divider"><span>or</span></div>

            <div class="auth-footer-link">
                Don't have an account? <a href="register.php">Create one free</a>
            </div>
            <div class="auth-footer-link" style="margin-top:10px;">
                Just browsing? <a href="package/track.php">Track a package</a>
            </div>
        </div>
    </div>

</div>

<div id="loginErrorToast" class="login-error-toast" aria-live="polite" role="alert" data-initial-error="<?= htmlspecialchars($error_message ?? '') ?>">
    <i class="fas fa-exclamation-circle toast-icon"></i>
    <span class="toast-msg" id="loginErrorToastText"></span>
</div>

<footer>
    &copy; <?= date('Y') ?> Postal Service Management System. All rights reserved.
    &nbsp;&mdash;&nbsp; Powered by the Postal Pro Team <i class="fas fa-chess-knight footer-knight"></i>
</footer>

<script>
    (function() {
        var form = document.getElementById('loginForm');
        var overlay = document.getElementById('authOverlay');
        var statusText = overlay ? overlay.querySelector('.text') : null;
        var pwInput = form ? form.querySelector('input[name="password"]') : null;
        var toast = document.getElementById('loginErrorToast');
        var toastText = document.getElementById('loginErrorToastText');
        var btn = form ? form.querySelector('button[type="submit"]') : null;
        var origBtnHtml = btn ? btn.innerHTML : '';
        var overlayShownAt = 0;
        var minAuthBeforeFailMs = 2500;
        function hideOverlay() {
            if (overlay) {
                overlay.classList.remove('show', 'success', 'failed');
                overlay.setAttribute('aria-hidden', 'true');
            }
        }
        function showErrorToast(msg) {
            if (pwInput) pwInput.value = '';
            if (!toast || !toastText) return;
            toast.classList.remove('fade-out');
            toastText.textContent = msg;
            toast.classList.add('show');
            setTimeout(function() {
                toast.classList.add('fade-out');
                setTimeout(function() { toast.classList.remove('show', 'fade-out'); }, 600);
            }, 1500);
        }
        function showFailedThenHide(msg) {
            if (!overlay || !statusText) { hideOverlay(); if (msg) showErrorToast(msg); return; }
            var elapsed = Date.now() - overlayShownAt;
            var waitBeforeFail = Math.max(0, minAuthBeforeFailMs - elapsed);
            var messageToShow = msg || 'Invalid email or password.';
            setTimeout(function() {
                overlay.classList.remove('success');
                overlay.classList.add('failed');
                statusText.textContent = 'Authentication failed';
                setTimeout(function() {
                    hideOverlay();
                    showErrorToast(messageToShow);
                }, 1200);
            }, waitBeforeFail);
        }
        if (form && overlay && statusText) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin" style="margin-right:8px;"></i> Signing in…'; }
                overlay.classList.remove('success', 'failed');
                statusText.textContent = 'Authenticating…';
                overlay.classList.add('show');
                overlay.setAttribute('aria-hidden', 'false');
                overlayShownAt = Date.now();
                var fd = new FormData(form);
                var xhr = new XMLHttpRequest();
                xhr.open('POST', form.action);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                var minAuthBeforeSuccessMs = 2000;
                var successDisplayMs = 800;
                xhr.onload = function() {
                    if (btn) { btn.disabled = false; btn.innerHTML = origBtnHtml; }
                    try {
                        var json = JSON.parse(xhr.responseText);
                        if (json.ok && json.redirect) {
                            var elapsed = Date.now() - overlayShownAt;
                            var waitBeforeSuccess = Math.max(0, minAuthBeforeSuccessMs - elapsed);
                            setTimeout(function() {
                                overlay.classList.add('success');
                                statusText.textContent = 'Authentication successful';
                                setTimeout(function() { window.location.replace(json.redirect); }, successDisplayMs);
                            }, waitBeforeSuccess);
                            return;
                        }
                        if (!json.ok && json.error) {
                            showFailedThenHide(json.error);
                        } else { hideOverlay(); }
                    } catch (_) {
                        showFailedThenHide('Something went wrong. Please try again.');
                    }
                };
                xhr.onerror = function() {
                    if (btn) { btn.disabled = false; btn.innerHTML = origBtnHtml; }
                    showFailedThenHide('Network error. Please try again.');
                };
                xhr.send(fd);
            });
        }
        var initialError = toast && toast.getAttribute('data-initial-error');
        if (initialError && toast && toastText) {
            if (pwInput) pwInput.value = '';
            toast.classList.add('show');
            toastText.textContent = initialError;
            setTimeout(function() {
                toast.classList.add('fade-out');
                setTimeout(function() { toast.classList.remove('show', 'fade-out'); }, 600);
            }, 1500);
        }
    })();
    const togglePw   = document.getElementById('togglePw');
    const pwField    = document.getElementById('password');
    const toggleIcon = document.getElementById('togglePwIcon');
    togglePw.addEventListener('click', () => {
        const show = pwField.type === 'password';
        pwField.type = show ? 'text' : 'password';
        toggleIcon.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
    });
</script>
</body>
</html>
