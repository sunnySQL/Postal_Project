<?php
session_start();
require_once 'db_connect.php';
require_once 'functions.php';

$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email           = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password        = $_POST['password'];
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $role            = "Customer";

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT * FROM Users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception("This email is already registered.");
        }

        $stmt = $conn->prepare("INSERT INTO Users (email, password, role, account_status) VALUES (?, ?, ?, 'Active')");
        $stmt->bind_param("sss", $email, $hashed_password, $role);
        $stmt->execute();
        $user_id = $conn->insert_id;

        $first_name    = trim($_POST['first_name']);
        $last_name     = trim($_POST['last_name']);
        $phone         = trim($_POST['phone'] ?? '');
        $street        = trim($_POST['street_address'] ?? '');
        $city          = trim($_POST['city'] ?? '');
        $state         = trim($_POST['state'] ?? '');
        $postal_code   = trim($_POST['postal_code'] ?? '');

        $stmt = $conn->prepare("INSERT INTO Customer (user_id, first_name, last_name, phone, street_address, city, state, postal_code, is_guest) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)");
        $stmt->bind_param("isssssss", $user_id, $first_name, $last_name, $phone, $street, $city, $state, $postal_code);
        $stmt->execute();

        $conn->commit();

        $_SESSION["logged_in"] = true;
        $_SESSION["user_id"]   = $user_id;
        $_SESSION["email"]     = $email;
        $_SESSION["role"]      = $role;
        $_SESSION["name"]      = $first_name . ' ' . $last_name;

        logAudit($conn, 'USER_REGISTERED', 'User', (string)$user_id,
            "New customer account registered: {$first_name} {$last_name} ({$email})",
            null, null,
            ['email' => $email, 'name' => $first_name . ' ' . $last_name, 'role' => $role]
        );

        header("Location: customer_dashboard.php");
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = $e->getMessage();
    }
}

$states = [
    'AL'=>'Alabama','AK'=>'Alaska','AZ'=>'Arizona','AR'=>'Arkansas','CA'=>'California',
    'CO'=>'Colorado','CT'=>'Connecticut','DE'=>'Delaware','FL'=>'Florida','GA'=>'Georgia',
    'HI'=>'Hawaii','ID'=>'Idaho','IL'=>'Illinois','IN'=>'Indiana','IA'=>'Iowa',
    'KS'=>'Kansas','KY'=>'Kentucky','LA'=>'Louisiana','ME'=>'Maine','MD'=>'Maryland',
    'MA'=>'Massachusetts','MI'=>'Michigan','MN'=>'Minnesota','MS'=>'Mississippi','MO'=>'Missouri',
    'MT'=>'Montana','NE'=>'Nebraska','NV'=>'Nevada','NH'=>'New Hampshire','NJ'=>'New Jersey',
    'NM'=>'New Mexico','NY'=>'New York','NC'=>'North Carolina','ND'=>'North Dakota','OH'=>'Ohio',
    'OK'=>'Oklahoma','OR'=>'Oregon','PA'=>'Pennsylvania','RI'=>'Rhode Island','SC'=>'South Carolina',
    'SD'=>'South Dakota','TN'=>'Tennessee','TX'=>'Texas','UT'=>'Utah','VT'=>'Vermont',
    'VA'=>'Virginia','WA'=>'Washington','WV'=>'West Virginia','WI'=>'Wisconsin','WY'=>'Wyoming'
];
$post = $_POST;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account | POSTAL PRO</title>
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
            width: 38%; background: linear-gradient(160deg, #003a6e 0%, #004B87 50%, #0068b5 100%);
            display: flex; flex-direction: column; justify-content: center;
            padding: 60px 48px; color: white; position: relative; overflow: hidden;
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
        .panel-heading { font-size: 1.7rem; font-weight: 800; line-height: 1.2; margin-bottom: 14px; }
        .panel-sub { font-size: 0.93rem; color: rgba(255,255,255,0.7); line-height: 1.7; margin-bottom: 36px; }
        .panel-features { list-style: none; display: flex; flex-direction: column; gap: 14px; }
        .panel-features li { display: flex; align-items: center; gap: 12px; font-size: 0.88rem; color: rgba(255,255,255,0.85); }
        .panel-features li .feat-icon { width: 32px; height: 32px; border-radius: 50%; background: rgba(255,255,255,0.18); display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: rgba(255,255,255,0.9); }
        .free-badge {
            display: inline-flex; align-items: center; gap: 7px;
            background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.2);
            color: white; font-size: 0.78rem; font-weight: 700;
            padding: 6px 14px; border-radius: 999px; margin-bottom: 22px;
        }
        .free-badge i { color: #fbbf24; }

        /* RIGHT PANEL */
        .auth-panel-right {
            flex: 1; display: flex; align-items: flex-start; justify-content: center;
            padding: 50px 40px; background: white; overflow-y: auto;
        }
        .auth-form-wrap { width: 100%; max-width: 480px; padding-bottom: 40px; }
        .auth-form-wrap h2 { font-size: 1.55rem; font-weight: 800; color: #1a202c; margin-bottom: 4px; }
        .auth-form-wrap p.auth-sub { font-size: 0.88rem; color: #888; margin-bottom: 26px; }

        /* STEPS INDICATOR */
        .steps { display: flex; gap: 6px; margin-bottom: 28px; }
        .step-dot { height: 4px; border-radius: 2px; flex: 1; background: #e5e7eb; transition: background 0.3s; }
        .step-dot.active { background: #004B87; }

        /* SECTION LABEL */
        .section-label {
            font-size: 0.72rem; font-weight: 700; letter-spacing: 1.5px;
            text-transform: uppercase; color: #DA291C; margin: 22px 0 14px;
            display: flex; align-items: center; gap: 8px;
        }
        .section-label::after { content: ''; flex: 1; height: 1px; background: #f1f5f9; }

        /* ERROR */
        .alert-error {
            background: #fff0f0; border-left: 4px solid #DA291C; color: #7f1d1d;
            padding: 12px 16px; border-radius: 8px; font-size: 0.88rem;
            display: flex; align-items: center; gap: 10px; margin-bottom: 20px;
        }

        /* FORM */
        .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .field { margin-bottom: 14px; }
        .field label { display: block; font-size: 0.78rem; font-weight: 700; color: #374151; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.5px; }
        .field label span { color: #DA291C; }
        .input-wrap { position: relative; }
        .input-wrap i.field-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: 0.85rem; pointer-events: none; }
        .field input, .field select {
            width: 100%; padding: 10px 12px 10px 36px;
            border: 1.5px solid #e5e7eb; border-radius: 8px;
            font-size: 0.93rem; color: #1a202c;
            transition: border-color 0.2s, box-shadow 0.2s; outline: none;
            background: white;
        }
        .field select { padding-left: 36px; cursor: pointer; }
        .field input:focus, .field select:focus { border-color: #004B87; box-shadow: 0 0 0 3px rgba(0,75,135,0.1); }
        .field input.optional { background: #fafafa; }

        /* ADDRESS SUGGESTIONS DROPDOWN */
        .address-suggestions {
            position: absolute; top: calc(100% + 4px); left: 0; right: 0;
            background: white; border: 1.5px solid #e5e7eb; border-radius: 10px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
            z-index: 100; max-height: 240px; overflow-y: auto;
            display: none;
        }
        .address-suggestions.open { display: block; }
        .suggestion-item {
            padding: 11px 14px; cursor: pointer; font-size: 0.87rem; color: #374151;
            border-bottom: 1px solid #f3f4f6; display: flex; align-items: flex-start; gap: 10px;
            transition: background 0.15s;
        }
        .suggestion-item:last-child { border-bottom: none; }
        .suggestion-item:hover, .suggestion-item.focused { background: #eff6ff; }
        .suggestion-item i { color: #DA291C; margin-top: 2px; flex-shrink: 0; font-size: 0.8rem; }
        .suggestion-main { font-weight: 600; color: #1a202c; }
        .suggestion-sub  { font-size: 0.78rem; color: #888; margin-top: 1px; }
        .suggestion-loading { padding: 14px; text-align: center; color: #888; font-size: 0.85rem; }
        .suggestion-none { padding: 14px; text-align: center; color: #aaa; font-size: 0.83rem; }
        .toggle-pw {
            position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
            color: #9ca3af; cursor: pointer; font-size: 0.88rem; background: none; border: none;
            transition: color 0.2s;
        }
        .toggle-pw:hover { color: #004B87; }

        /* PASSWORD STRENGTH */
        .pw-strength { margin-top: 6px; display: flex; gap: 4px; }
        .pw-bar { height: 3px; border-radius: 2px; flex: 1; background: #e5e7eb; transition: background 0.3s; }
        .pw-label { font-size: 0.72rem; color: #888; margin-top: 4px; }

        .submit-btn {
            width: 100%; padding: 13px; background: #DA291C; color: white;
            border: none; border-radius: 8px; font-size: 1rem; font-weight: 700;
            cursor: pointer; transition: background 0.2s, transform 0.15s;
            margin-top: 8px;
        }
        .submit-btn:hover { background: #b52218; transform: translateY(-1px); }
        .submit-btn:active { transform: translateY(0); }

        .terms { font-size: 0.78rem; color: #888; text-align: center; margin-top: 12px; line-height: 1.6; }
        .terms a { color: #004B87; font-weight: 600; }

        .divider { display: flex; align-items: center; gap: 12px; margin: 20px 0; }
        .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: #e5e7eb; }
        .divider span { font-size: 0.78rem; color: #aaa; white-space: nowrap; }

        .auth-footer-link { text-align: center; font-size: 0.88rem; color: #666; }
        .auth-footer-link a { color: #004B87; font-weight: 700; transition: color 0.2s; }
        .auth-footer-link a:hover { color: #DA291C; }

        /* FOOTER */
        footer { background: #1a2e4a; color: rgba(255,255,255,0.5); text-align: center; padding: 16px; font-size: 0.78rem; }
        .footer-knight { color: #DA291C; }

        /* RESPONSIVE */
        @media (max-width: 900px) {
            .auth-panel-left { display: none; }
            .auth-panel-right { background: #f4f6f9; }
        }
        @media (max-width: 560px) {
            .auth-panel-right { padding: 32px 20px; }
            .field-row { grid-template-columns: 1fr; }
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
        <span class="free-badge">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="currentColor" style="flex-shrink:0;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            Free account — no credit card needed
        </span>
        <h2 class="panel-heading">Start shipping<br>smarter today.</h2>
        <p class="panel-sub">Create your free account to access all Postal Pro services — tracking, shipment creation, the postal shop, and customer support.</p>
        <ul class="panel-features">
            <li>
                <span class="feat-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                </span>
                Track all your packages in real time
            </li>
            <li>
                <span class="feat-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                </span>
                Create and pay for shipments online
            </li>
            <li>
                <span class="feat-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                </span>
                Receive alerts on delivery updates
            </li>
            <li>
                <span class="feat-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                </span>
                Open support tickets for any issues
            </li>
            </ul>
    </div>

    <!-- RIGHT FORM PANEL -->
    <div class="auth-panel-right">
        <div class="auth-form-wrap">
            <h2>Create Your Account</h2>
            <p class="auth-sub">Fill in the details below to get started. Fields marked <span style="color:#DA291C">*</span> are required.</p>

            <?php if (!empty($error_message)): ?>
            <div class="alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?= htmlspecialchars($error_message) ?></span>
            </div>
            <?php endif; ?>

            <form method="post" action="register.php" novalidate>

                <p class="section-label">Account Info</p>

                <div class="field">
                    <label for="email">Email Address <span>*</span></label>
                    <div class="input-wrap">
                        <i class="fas fa-envelope field-icon"></i>
                        <input type="email" id="email" name="email" placeholder="you@example.com"
                               value="<?= htmlspecialchars($post['email'] ?? '') ?>" required autocomplete="email">
                    </div>
                </div>

                <div class="field">
                    <label for="password">Password <span>*</span></label>
                    <div class="input-wrap">
                        <i class="fas fa-lock field-icon"></i>
                        <input type="password" id="password" name="password"
                               placeholder="Create a strong password" required autocomplete="new-password">
                        <button type="button" class="toggle-pw" id="togglePw" tabindex="-1">
                            <i class="fas fa-eye" id="togglePwIcon"></i>
                        </button>
                    </div>
                    <div class="pw-strength">
                        <div class="pw-bar" id="bar1"></div>
                        <div class="pw-bar" id="bar2"></div>
                        <div class="pw-bar" id="bar3"></div>
                        <div class="pw-bar" id="bar4"></div>
                    </div>
                    <p class="pw-label" id="pwLabel">Enter a password</p>
                </div>

                <p class="section-label">Personal Info</p>

                <div class="field-row">
                    <div class="field">
                        <label for="first_name">First Name <span>*</span></label>
                        <div class="input-wrap">
                            <i class="fas fa-user field-icon"></i>
                            <input type="text" id="first_name" name="first_name" placeholder="Jane"
                                   value="<?= htmlspecialchars($post['first_name'] ?? '') ?>" required autocomplete="given-name">
                        </div>
                    </div>
                    <div class="field">
                        <label for="last_name">Last Name <span>*</span></label>
                        <div class="input-wrap">
                            <i class="fas fa-user field-icon"></i>
                            <input type="text" id="last_name" name="last_name" placeholder="Doe"
                                   value="<?= htmlspecialchars($post['last_name'] ?? '') ?>" required autocomplete="family-name">
                        </div>
                    </div>
                </div>

                <div class="field">
                    <label for="phone">Phone Number</label>
                    <div class="input-wrap">
                        <i class="fas fa-phone field-icon"></i>
                        <input type="tel" id="phone" name="phone" placeholder="(555) 000-0000" class="optional"
                               value="<?= htmlspecialchars($post['phone'] ?? '') ?>" autocomplete="tel">
                    </div>
                </div>

                <p class="section-label">Address</p>

                <div class="field">
                    <label for="street_address">Street Address</label>
                    <div class="input-wrap" style="position:relative;">
                        <i class="fas fa-map-marker-alt field-icon"></i>
                        <input type="text" id="street_address" name="street_address" placeholder="Start typing an address…" class="optional"
                               value="<?= htmlspecialchars($post['street_address'] ?? '') ?>" autocomplete="off">
                        <div class="address-suggestions" id="addressSuggestions"></div>
                    </div>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label for="city">City</label>
                        <div class="input-wrap">
                            <i class="fas fa-city field-icon"></i>
                            <input type="text" id="city" name="city" placeholder="Houston" class="optional"
                                   value="<?= htmlspecialchars($post['city'] ?? '') ?>" autocomplete="address-level2">
                        </div>
                    </div>
                    <div class="field">
                        <label for="postal_code">ZIP Code</label>
                        <div class="input-wrap">
                            <i class="fas fa-hashtag field-icon"></i>
                            <input type="text" id="postal_code" name="postal_code" placeholder="77001" class="optional"
                                   value="<?= htmlspecialchars($post['postal_code'] ?? '') ?>" autocomplete="postal-code">
                        </div>
                    </div>
                </div>

                <div class="field">
                    <label for="state">State</label>
                    <div class="input-wrap">
                        <i class="fas fa-flag field-icon"></i>
                <select id="state" name="state" autocomplete="address-level1">
                            <option value="">— Select State —</option>
                            <?php foreach ($states as $abbr => $name): ?>
                            <option value="<?= $abbr ?>" <?= (($post['state'] ?? '') == $abbr) ? 'selected' : '' ?>>
                                <?= $name ?>
                            </option>
                            <?php endforeach; ?>
                </select>
                    </div>
                </div>
                
                <button type="submit" class="submit-btn">
                    <i class="fas fa-user-plus" style="margin-right:8px;"></i> Create Free Account
                </button>
                
                <p class="terms">
                    By creating an account you agree to our
                    <a href="terms.php" target="_blank">Terms of Service</a> and <a href="privacy.php" target="_blank">Privacy Policy</a>.
                </p>
            </form>

            <div class="divider"><span>already have an account?</span></div>

            <div class="auth-footer-link">
                <a href="login.php">Sign in instead &rarr;</a>
            </div>
        </div>
    </div>

</div>

<footer>
    &copy; <?= date('Y') ?> Postal Service Management System. All rights reserved.
    &nbsp;&mdash;&nbsp; Powered by the Postal Pro Team <i class="fas fa-chess-knight footer-knight"></i>
</footer>

    <script>
    /* ── MOBILE MENU ── */
    });

    /* ── SHOW/HIDE PASSWORD ── */
    const togglePw   = document.getElementById('togglePw');
    const pwField    = document.getElementById('password');
    const toggleIcon = document.getElementById('togglePwIcon');
    togglePw.addEventListener('click', () => {
        const show = pwField.type === 'password';
        pwField.type = show ? 'text' : 'password';
        toggleIcon.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
    });

    /* ── PASSWORD STRENGTH ── */
    const bars    = [document.getElementById('bar1'), document.getElementById('bar2'), document.getElementById('bar3'), document.getElementById('bar4')];
    const pwLabel = document.getElementById('pwLabel');
    const colors  = ['#DA291C', '#f59e0b', '#3b82f6', '#16a34a'];
    const labels  = ['Too short', 'Weak', 'Fair', 'Strong'];
    pwField.addEventListener('input', () => {
        const val = pwField.value;
        let score = 0;
        if (val.length >= 8)          score++;
        if (/[A-Z]/.test(val))        score++;
        if (/[0-9]/.test(val))        score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;
        bars.forEach((b, i) => { b.style.background = i < score ? colors[score - 1] : '#e5e7eb'; });
        pwLabel.textContent = val.length === 0 ? 'Enter a password' : (labels[score - 1] || 'Too short');
        pwLabel.style.color = val.length === 0 ? '#888' : (colors[score - 1] || '#888');
    });

    /* ── PHONE AUTO-FORMAT (xxx-xxx-xxxx) ── */
    const phoneField = document.getElementById('phone');
    phoneField.addEventListener('input', function () {
        let digits = this.value.replace(/\D/g, '').slice(0, 10);
        let formatted = '';
        if (digits.length > 6)      formatted = digits.slice(0,3) + '-' + digits.slice(3,6) + '-' + digits.slice(6);
        else if (digits.length > 3) formatted = digits.slice(0,3) + '-' + digits.slice(3);
        else                         formatted = digits;
        this.value = formatted;
    });
    phoneField.addEventListener('keydown', function (e) {
        // Allow backspace to delete the hyphen cleanly
        if (e.key === 'Backspace' && this.value.endsWith('-')) {
            e.preventDefault();
            this.value = this.value.slice(0, -1);
        }
    });

    /* ── ADDRESS AUTOCOMPLETE (Nominatim / OpenStreetMap) ── */
    const streetInput   = document.getElementById('street_address');
    const cityInput     = document.getElementById('city');
    const stateSelect   = document.getElementById('state');
    const postalInput   = document.getElementById('postal_code');
    const dropdown      = document.getElementById('addressSuggestions');

    // State name → abbreviation map
    const stateMap = {
        'alabama':'AL','alaska':'AK','arizona':'AZ','arkansas':'AR','california':'CA',
        'colorado':'CO','connecticut':'CT','delaware':'DE','florida':'FL','georgia':'GA',
        'hawaii':'HI','idaho':'ID','illinois':'IL','indiana':'IN','iowa':'IA','kansas':'KS',
        'kentucky':'KY','louisiana':'LA','maine':'ME','maryland':'MD','massachusetts':'MA',
        'michigan':'MI','minnesota':'MN','mississippi':'MS','missouri':'MO','montana':'MT',
        'nebraska':'NE','nevada':'NV','new hampshire':'NH','new jersey':'NJ','new mexico':'NM',
        'new york':'NY','north carolina':'NC','north dakota':'ND','ohio':'OH','oklahoma':'OK',
        'oregon':'OR','pennsylvania':'PA','rhode island':'RI','south carolina':'SC',
        'south dakota':'SD','tennessee':'TN','texas':'TX','utah':'UT','vermont':'VT',
        'virginia':'VA','washington':'WA','west virginia':'WV','wisconsin':'WI','wyoming':'WY'
    };

    let debounceTimer = null;
    let focusedIndex  = -1;

    streetInput.addEventListener('input', function () {
        const query = this.value.trim();
        clearTimeout(debounceTimer);
        focusedIndex = -1;

        if (query.length < 4) { closeDropdown(); return; }

        dropdown.innerHTML = '<div class="suggestion-loading"><i class="fas fa-circle-notch fa-spin"></i> Searching…</div>';
        dropdown.classList.add('open');

        debounceTimer = setTimeout(() => fetchSuggestions(query), 420);
    });

    async function fetchSuggestions(query) {
        try {
            const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&countrycodes=us&addressdetails=1&limit=6`;
            const res  = await fetch(url, { headers: { 'Accept-Language': 'en' } });
            const data = await res.json();

            if (!data.length) {
                dropdown.innerHTML = '<div class="suggestion-none"><i class="fas fa-magnifying-glass"></i> No results found</div>';
                return;
            }

            dropdown.innerHTML = '';
            data.forEach((item, idx) => {
                const addr   = item.address || {};
                const house  = addr.house_number || '';
                const road   = addr.road || addr.pedestrian || '';
                const city   = addr.city || addr.town || addr.village || addr.county || '';
                const state  = addr.state || '';
                const zip    = addr.postcode || '';

                const mainLine = [house, road].filter(Boolean).join(' ') || item.display_name.split(',')[0];
                const subLine  = [city, state, zip].filter(Boolean).join(', ');

                const el = document.createElement('div');
                el.className = 'suggestion-item';
                el.dataset.idx = idx;
                el.innerHTML = `<i class="fas fa-location-dot"></i><div><div class="suggestion-main">${mainLine}</div><div class="suggestion-sub">${subLine}</div></div>`;

                el.addEventListener('mousedown', (e) => {
                    e.preventDefault(); // prevent input blur before fill
                    fillAddress(mainLine, city, state, zip);
                    closeDropdown();
                });
                dropdown.appendChild(el);
            });
        } catch (err) {
            dropdown.innerHTML = '<div class="suggestion-none">Unable to fetch suggestions</div>';
        }
    }

    function fillAddress(street, city, state, zip) {
        streetInput.value = street;
        cityInput.value   = city;
        postalInput.value = zip;

        // Match state name to abbreviation for the select
        const abbr = stateMap[state.toLowerCase()] || '';
        if (abbr) stateSelect.value = abbr;
    }

    // Keyboard nav inside dropdown
    streetInput.addEventListener('keydown', function (e) {
        const items = dropdown.querySelectorAll('.suggestion-item');
        if (!items.length) return;
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            focusedIndex = Math.min(focusedIndex + 1, items.length - 1);
            updateFocus(items);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            focusedIndex = Math.max(focusedIndex - 1, 0);
            updateFocus(items);
        } else if (e.key === 'Enter' && focusedIndex >= 0) {
            e.preventDefault();
            items[focusedIndex].dispatchEvent(new Event('mousedown'));
        } else if (e.key === 'Escape') {
            closeDropdown();
        }
    });

    function updateFocus(items) {
        items.forEach((el, i) => el.classList.toggle('focused', i === focusedIndex));
        if (items[focusedIndex]) items[focusedIndex].scrollIntoView({ block: 'nearest' });
    }

    function closeDropdown() {
        dropdown.classList.remove('open');
        dropdown.innerHTML = '';
        focusedIndex = -1;
    }

    // Close when clicking outside
    document.addEventListener('click', (e) => {
        if (!streetInput.contains(e.target) && !dropdown.contains(e.target)) closeDropdown();
        });
    </script>
</body>
</html>
