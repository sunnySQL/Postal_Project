<?php
session_start();
require_once 'db_connect.php';

// Already logged in as employee — send home (replace in history so Back goes to prior page)
if (!empty($_SESSION['logged_in']) && !empty($_SESSION['role']) && $_SESSION['role'] !== 'Customer') {
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><script>window.location.replace("employee_dashboard.php");</script></head><body>Redirecting…</body></html>';
    exit();
}

$error_message = "";

// AJAX login: no new history entry, so one Back from dashboard goes to index
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT user_id, password, role, account_status, password_change_required, deleted_at FROM Users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (!empty($user['deleted_at'])) {
            $error_message = "This account no longer exists. Contact your administrator.";
        } elseif ($user['role'] === 'Customer') {
            $error_message = "This portal is for staff only. Please use the customer sign-in page.";
        } elseif (!password_verify($password, $user['password'])) {
            $error_message = "Invalid email or password.";
        } elseif ($user['account_status'] !== 'Active') {
            $error_message = "Your account is " . htmlspecialchars($user['account_status']) . ". Contact your administrator.";
        } else {
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id']   = $user['user_id'];
            $_SESSION['role']      = $user['role'];

            $upd = $conn->prepare("UPDATE Users SET last_login = CURRENT_TIMESTAMP WHERE user_id = ?");
            $upd->bind_param("i", $user['user_id']);
            $upd->execute();

            $emp = $conn->prepare("SELECT first_name, last_name, role AS emp_role, facility_id FROM Employee WHERE user_id = ?");
            $emp->bind_param("i", $user['user_id']);
            $emp->execute();
            $employee = $emp->get_result()->fetch_assoc();
            $_SESSION['name']        = trim(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? ''));
            $_SESSION['emp_role']    = $employee['emp_role']    ?? $user['role'];
            $_SESSION['facility_id'] = $employee['facility_id'] ?? null;

            $ns = $conn->prepare("SELECT COUNT(*) as cnt FROM Notifications WHERE user_id = ? AND is_read = 0");
            $ns->bind_param("i", $user['user_id']);
            $ns->execute();
            $nc = (int)($ns->get_result()->fetch_assoc()['cnt'] ?? 0);
            if ($nc > 0) $_SESSION['login_notif_count'] = $nc;

            $redirect = $user['password_change_required'] == 1 ? "edit_profile.php" : "employee_dashboard.php";

            if ($is_ajax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'redirect' => $redirect]);
                exit();
            }
            echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><script>window.location.replace("' . htmlspecialchars($redirect) . '");</script></head><body>Redirecting…</body></html>';
            exit();
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
    <title>Employee Portal | POSTAL PRO</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;500;600;700;800&display=swap');

        *, *::before, *::after {
            margin: 0; padding: 0; box-sizing: border-box;
            font-family: 'Open Sans', sans-serif;
        }
        body {
            background: #000;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }

        /* ── BACKGROUND SCENE ── */
        body::before {
            content: '';
            position: fixed; inset: 0; z-index: 0; pointer-events: none;
            /* dot grid */
            background-image: radial-gradient(rgba(255,255,255,0.07) 1px, transparent 1px);
            background-size: 28px 28px;
        }
        body::after {
            content: '';
            position: fixed; inset: 0; z-index: 0; pointer-events: none;
            /* ambient colour glows */
            background:
                radial-gradient(ellipse 70% 60% at 15% 20%,  rgba(0,75,135,0.28) 0%, transparent 70%),
                radial-gradient(ellipse 50% 45% at 88% 80%,  rgba(218,41,28,0.14) 0%, transparent 65%),
                radial-gradient(ellipse 40% 40% at 75% 10%,  rgba(74,159,212,0.1) 0%, transparent 60%);
        }

        a { text-decoration: none; }

        /* ── NAV ── */
        nav {
            display: flex; align-items: center;
            background: rgba(0,0,0,0.75);
            backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
            padding: 0 5%; height: 60px;
            position: fixed; width: 100%; top: 0; left: 0; z-index: 200;
            border-bottom: 1px solid rgba(255,255,255,0.07);
        }
        .logo { font-weight: 800; font-size: 1.6rem; color: #fff; letter-spacing: -0.5px; transition: color 0.2s; }
        .logo:hover { color: #DA291C; }

        /* ── PAGE SHELL ── */
        .page {
            position: relative; z-index: 1;
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 60px;
            min-height: calc(100vh - 60px);
            padding: 40px 20px;
        }

        /* ── CARD ── */
        .card {
            position: relative;
            width: 100%;
            max-width: 420px;
            background: rgba(255,255,255,0.04);
            backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 40px 36px;
            box-shadow: 0 24px 60px rgba(0,0,0,0.6), 0 0 0 1px rgba(255,255,255,0.04) inset;
        }
        /* Top edge highlight */
        .card::before {
            content: '';
            position: absolute; top: 0; left: 10%; right: 10%;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.18), transparent);
            border-radius: 999px;
        }

        .form-header { margin-bottom: 28px; }
        .form-header .eyebrow {
            font-size: 0.72rem; font-weight: 700;
            letter-spacing: 1.4px; text-transform: uppercase;
            color: #4a9fd4; margin-bottom: 8px;
        }
        .form-header h2 {
            font-size: 1.6rem; font-weight: 800; color: #fff;
            margin-bottom: 6px; line-height: 1.2;
        }
        .form-header p {
            font-size: 0.85rem; color: rgba(255,255,255,0.4);
        }

        /* Error */
        .alert-error {
            background: rgba(239,68,68,0.1); border-left: 3px solid #ef4444;
            color: #fca5a5; padding: 11px 14px; border-radius: 8px;
            font-size: 0.85rem; display: flex; align-items: flex-start;
            gap: 10px; margin-bottom: 22px; line-height: 1.5;
        }
        .alert-error i { margin-top: 2px; flex-shrink: 0; }

        /* Fields */
        .field { margin-bottom: 18px; }
        .field label {
            display: block; font-size: 0.72rem; font-weight: 700;
            color: rgba(255,255,255,0.5); margin-bottom: 7px;
            text-transform: uppercase; letter-spacing: 0.7px;
        }
        .input-wrap { position: relative; }
        .input-wrap i.fi {
            position: absolute; left: 13px; top: 50%;
            transform: translateY(-50%);
            color: rgba(255,255,255,0.25); font-size: 0.85rem; pointer-events: none;
        }
        .field input {
            width: 100%; padding: 11px 14px 11px 38px;
            border: 1.5px solid rgba(255,255,255,0.1); border-radius: 8px;
            font-size: 0.95rem; color: #fff;
            background: rgba(255,255,255,0.05);
            transition: border-color 0.2s, box-shadow 0.2s; outline: none;
        }
        .field input::placeholder { color: rgba(255,255,255,0.2); }
        .field input:focus {
            border-color: #4a9fd4;
            box-shadow: 0 0 0 3px rgba(74,159,212,0.15);
            background: rgba(255,255,255,0.07);
        }
        .toggle-pw {
            position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
            color: rgba(255,255,255,0.25); cursor: pointer; font-size: 0.9rem;
            background: none; border: none; transition: color 0.2s;
        }
        .toggle-pw:hover { color: rgba(255,255,255,0.7); }

        .submit-btn {
            width: 100%; padding: 12px;
            background: #004B87; color: #fff;
            border: none; border-radius: 8px;
            font-size: 0.95rem; font-weight: 700;
            cursor: pointer; letter-spacing: 0.3px;
            transition: background 0.2s, transform 0.15s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            margin-top: 6px;
        }
        .submit-btn:hover { background: #003a6e; transform: translateY(-1px); }
        .submit-btn:active { transform: translateY(0); }

        /* ── Error toast: slides in from right, then fades out ── */
        .login-error-toast {
            position: fixed;
            top: 88px;
            right: -380px;
            z-index: 10000;
            max-width: 340px;
            padding: 16px 20px;
            background: rgba(127, 29, 29, 0.95);
            border-left: 4px solid #f87171;
            border-radius: 8px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.4);
            display: flex;
            align-items: center;
            gap: 12px;
            opacity: 1;
            transition: right 0.4s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.6s ease;
        }
        .login-error-toast.show { right: 24px; }
        .login-error-toast.fade-out { opacity: 0; right: -380px; pointer-events: none; }
        .login-error-toast .toast-icon { color: #fca5a5; font-size: 1.25rem; flex-shrink: 0; }
        .login-error-toast .toast-msg { font-size: 0.9rem; font-weight: 600; color: #fecaca; }

        /* ── LOGIN OVERLAY: blurred backdrop + centered square card ── */
        .auth-overlay {
            display: none;
            position: fixed; inset: 0; z-index: 9999;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            align-items: center; justify-content: center;
        }
        .auth-overlay.show { display: flex; }
        .auth-overlay .auth-card {
            width: 200px; height: 200px;
            background: rgba(26, 46, 74, 0.95);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 16px;
            padding: 24px;
        }
        .auth-overlay .spinner-wrap {
            width: 48px; height: 48px;
            border: 4px solid rgba(255,255,255,0.2);
            border-top-color: rgba(255,255,255,0.95);
            border-radius: 50%;
            animation: login-spin 0.9s linear infinite;
        }
        .auth-overlay .text { font-size: 0.95rem; font-weight: 600; color: rgba(255,255,255,0.95); text-align: center; }
        .auth-overlay.success .spinner-wrap,
        .auth-overlay.success .fail-wrap,
        .auth-overlay.failed .spinner-wrap,
        .auth-overlay.failed .check-wrap { display: none; }
        .auth-overlay.success .text { color: #86efac; }
        .auth-overlay.success .check-wrap { display: flex; }
        .auth-overlay.failed .fail-wrap { display: flex !important; }
        .auth-overlay.failed .text { color: #fca5a5; }
        .auth-overlay .fail-wrap { display: none; width: 56px; height: 56px; align-items: center; justify-content: center; }
        .auth-overlay .fail-wrap svg { width: 56px; height: 56px; display: block; }
        .auth-overlay .fail-wrap .fail-circle { fill: none; stroke: #ef4444; stroke-width: 2.5; }
        .auth-overlay .fail-wrap .fail-x { fill: none; stroke: #ef4444; stroke-width: 3; stroke-linecap: round; }
        .auth-overlay .check-wrap { display: none; width: 52px; height: 52px; align-items: center; justify-content: center; }
        .auth-overlay .check-wrap svg { width: 52px; height: 52px; overflow: visible; }
        .auth-overlay .check-wrap .check-path {
            fill: none;
            stroke: #86efac;
            stroke-width: 4;
            stroke-linecap: round;
            stroke-linejoin: round;
            stroke-dasharray: 70;
            stroke-dashoffset: 70;
            animation: check-draw 0.5s ease-out forwards;
        }
        .auth-overlay .check-wrap .check-circle {
            fill: none;
            stroke: #86efac;
            stroke-width: 2.5;
            stroke-dasharray: 151;
            stroke-dashoffset: 151;
            animation: check-circle-draw 0.4s ease-out forwards;
        }
        @keyframes login-spin { to { transform: rotate(360deg); } }
        @keyframes check-draw { to { stroke-dashoffset: 0; } }
        @keyframes check-circle-draw { to { stroke-dashoffset: 0; } }

        /* ── PAGE FOOTER ── */
        footer {
            position: relative; z-index: 1;
            background: transparent; color: rgba(255,255,255,0.18);
            border-top: 1px solid rgba(255,255,255,0.06);
            text-align: center; padding: 14px;
            font-size: 0.76rem;
        }
        .footer-knight { color: #DA291C; }

        @media (max-width: 480px) {
            .card { padding: 32px 24px; }
        }
    </style>
</head>
<body>

<!-- NAV -->
<nav>
    <a href="index.php" class="logo">POSTAL PRO</a>
</nav>

<div class="page">
    <div class="card">

        <div class="form-header">
            <div class="eyebrow">Staff Access</div>
            <h2>Employee Login</h2>
            <p>Sign in with your employee credentials.</p>
        </div>

        <form id="loginForm" method="POST" action="employee_login.php">
            <div class="field">
                <label for="email">Work Email</label>
                <div class="input-wrap">
                    <i class="fas fa-envelope fi"></i>
                    <input type="email" id="email" name="email"
                           placeholder="name@postalpost.com" required autocomplete="username"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <div class="input-wrap">
                    <i class="fas fa-lock fi"></i>
                    <input type="password" id="password" name="password"
                           placeholder="Enter your password" required autocomplete="current-password">
                    <button type="button" class="toggle-pw" id="togglePw" tabindex="-1">
                        <i class="fas fa-eye" id="toggleIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="submit-btn">
                <i class="fas fa-right-to-bracket"></i> Sign In
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

    </div>
</div>

<div id="loginErrorToast" class="login-error-toast" aria-live="polite" role="alert" data-initial-error="<?= htmlspecialchars($error_message ?? '') ?>">
    <i class="fas fa-exclamation-circle toast-icon"></i>
    <span class="toast-msg" id="loginErrorToastText"></span>
</div>

<footer>
    &copy; <?= date('Y') ?> Postal Service Management System &mdash; Internal Staff Portal &nbsp;
    <i class="fas fa-chess-knight footer-knight"></i>
</footer>

<script>
const pw   = document.getElementById('password');
const icon = document.getElementById('toggleIcon');
document.getElementById('togglePw').addEventListener('click', function() {
    const show = pw.type === 'password';
    pw.type = show ? 'text' : 'password';
    icon.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
});

// Submit via fetch so no extra history entry; then replace = one Back to index
document.getElementById('loginForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var form = this;
    var pwInput = form.querySelector('input[name="password"]');
    var toast = document.getElementById('loginErrorToast');
    var toastText = document.getElementById('loginErrorToastText');
    var btn = form.querySelector('button[type="submit"]');
    var overlay = document.getElementById('authOverlay');
    var statusText = overlay ? overlay.querySelector('.text') : null;
    var origText = btn.innerHTML;
    var minAuthMs = 1500;
    var successDisplayMs = 500;
    var overlayShownAt = 0;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin" style="margin-right:6px;"></i> Signing in…';
    if (overlay && statusText) {
        overlay.classList.remove('success', 'failed');
        statusText.textContent = 'Authenticating…';
        overlay.classList.add('show');
        overlay.setAttribute('aria-hidden', 'false');
        overlayShownAt = Date.now();
    }

    var fd = new FormData(form);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', form.action);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
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
    var minAuthBeforeFailMs = 2500;
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
    function showSuccessAndRedirect(url) {
        var elapsed = Date.now() - overlayShownAt;
        var waitBeforeSuccess = Math.max(0, minAuthMs - elapsed);
        setTimeout(function() {
            overlay.classList.add('success');
            statusText.textContent = 'Authentication successful';
            setTimeout(function() { window.location.replace(url); }, successDisplayMs);
        }, waitBeforeSuccess);
    }
    xhr.onload = function() {
        btn.disabled = false;
        btn.innerHTML = origText;
        try {
            var json = JSON.parse(xhr.responseText);
            if (json.ok && json.redirect) {
                if (overlay && statusText) {
                    showSuccessAndRedirect(json.redirect);
                } else {
                    window.location.replace(json.redirect);
                }
                return;
            }
            if (!json.ok && json.error) {
                showFailedThenHide(json.error);
            } else {
                hideOverlay();
            }
        } catch (_) {
            showFailedThenHide('Something went wrong. Please try again.');
        }
    };
    xhr.onerror = function() {
        btn.disabled = false;
        btn.innerHTML = origText;
        showFailedThenHide('Network error. Please try again.');
    };
    xhr.send(fd);
});

var toast = document.getElementById('loginErrorToast');
var toastText = document.getElementById('loginErrorToastText');
var pwInput = document.getElementById('password');
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
</script>
</body>
</html>
