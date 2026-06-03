<?php
session_start();
require_once 'db_connect.php';

$logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$user_role = $logged_in ? $_SESSION['role'] : '';

// Fetch all facilities
$facilities = [];
$result = $conn->query("SELECT facility_id, street_address, city, state, postal_code, type, airport_code FROM Facility ORDER BY state, city");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $facilities[] = $row;
    }
}

// Build unique states list for filter
$states = array_unique(array_column($facilities, 'state'));
sort($states);

// Count by type
$counts = ['Post Office' => 0, 'Hub' => 0, 'Airport' => 0];
foreach ($facilities as $f) {
    if (isset($counts[$f['type']])) $counts[$f['type']]++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find a Location | POSTAL PRO</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700;800&display=swap');

        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        body, body * { font-family: 'Open Sans', sans-serif; }
        .fa, .fas, .far, .fab,
        .fa::before, .fas::before, .far::before, .fab::before {
            font-family: "Font Awesome 6 Free", "Font Awesome 6 Brands" !important;
        }
        body { background: #f4f6f9; color: #333; min-height: 100vh; }
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

        /* HERO */
        .page-hero {
            background: linear-gradient(135deg, #003a6e 0%, #004B87 55%, #0068b5 100%);
            padding: 90px 5% 50px; text-align: center; color: white;
        }
        .page-hero p.eyebrow { font-size: 0.75rem; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: rgba(255,255,255,0.6); margin-bottom: 10px; }
        .page-hero h1 { font-size: 2.4rem; font-weight: 800; margin-bottom: 10px; }
        .page-hero p.sub { color: rgba(255,255,255,0.72); font-size: 0.97rem; margin-bottom: 28px; max-width: 520px; margin-left: auto; margin-right: auto; }

        /* SUMMARY PILLS */
        .summary-pills { display: flex; justify-content: center; gap: 12px; flex-wrap: wrap; }
        .pill {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.2);
            color: white; font-size: 0.85rem; font-weight: 600;
            padding: 8px 18px; border-radius: 999px;
        }
        .pill i { font-size: 0.8rem; opacity: 0.85; }

        /* PAGE BODY */
        .page-body { max-width: 1100px; margin: 0 auto; padding: 40px 5% 60px; }

        /* SEARCH & FILTER BAR */
        .filter-bar {
            background: white; border-radius: 12px;
            box-shadow: 0 2px 16px rgba(0,0,0,0.07);
            padding: 20px 24px; margin-bottom: 28px;
            display: flex; gap: 14px; align-items: center; flex-wrap: wrap;
        }
        .search-wrap { position: relative; flex: 1; min-width: 200px; }
        .search-wrap i { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: 0.88rem; }
        .search-wrap input {
            width: 100%; padding: 10px 12px 10px 36px;
            border: 1.5px solid #e5e7eb; border-radius: 8px;
            font-size: 0.93rem; outline: none; transition: border-color 0.2s;
        }
        .search-wrap input:focus { border-color: #004B87; }

        .filter-select {
            padding: 10px 14px; border: 1.5px solid #e5e7eb; border-radius: 8px;
            font-size: 0.9rem; outline: none; cursor: pointer; background: white;
            color: #374151; transition: border-color 0.2s; min-width: 150px;
        }
        .filter-select:focus { border-color: #004B87; }

        .filter-btn {
            padding: 10px 18px; border-radius: 8px; font-size: 0.88rem; font-weight: 600;
            cursor: pointer; border: 1.5px solid transparent; transition: all 0.2s;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .filter-btn.active, .filter-btn:hover { background: #004B87; color: white; border-color: #004B87; }
        .filter-btn:not(.active) { background: white; color: #374151; border-color: #e5e7eb; }
        .filter-btn.type-hub.active     { background: #7c3aed; border-color: #7c3aed; }
        .filter-btn.type-post.active    { background: #0284c7; border-color: #0284c7; }
        .filter-btn.type-airport.active { background: #0891b2; border-color: #0891b2; }

        /* RESULTS COUNT */
        .results-meta { font-size: 0.85rem; color: #888; margin-bottom: 18px; }
        .results-meta strong { color: #1a202c; }

        /* LOCATION GRID */
        .locations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 18px;
        }

        /* LOCATION CARD */
        .loc-card {
            background: white; border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            overflow: hidden; transition: transform 0.2s, box-shadow 0.2s;
            display: flex; flex-direction: column;
        }
        .loc-card:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(0,0,0,0.1); }

        .loc-card-top {
            padding: 18px 20px 14px;
            border-bottom: 1px solid #f1f5f9;
            display: flex; justify-content: space-between; align-items: flex-start; gap: 10px;
        }
        .loc-id { font-size: 0.72rem; color: #aaa; font-weight: 600; letter-spacing: 0.5px; margin-bottom: 4px; }
        .loc-name { font-size: 1rem; font-weight: 700; color: #1a202c; }

        /* TYPE BADGES */
        .type-badge {
            display: inline-flex; align-items: center; gap: 5px;
            font-size: 0.72rem; font-weight: 700; padding: 4px 10px;
            border-radius: 999px; white-space: nowrap; flex-shrink: 0;
        }
        .badge-post-office { background: #dbeafe; color: #1d4ed8; }
        .badge-hub         { background: #ede9fe; color: #6d28d9; }
        .badge-airport     { background: #cffafe; color: #0e7490; }

        .loc-card-body { padding: 16px 20px; flex: 1; }
        .loc-address { font-size: 0.88rem; color: #555; line-height: 1.65; margin-bottom: 14px; }
        .loc-address i { color: #DA291C; margin-right: 6px; font-size: 0.8rem; }
        .loc-airport { font-size: 0.82rem; color: #0e7490; font-weight: 600; margin-top: 4px; }
        .loc-airport i { margin-right: 5px; }

        .loc-card-footer {
            padding: 12px 20px; background: #f8fafc;
            border-top: 1px solid #f1f5f9;
            display: flex; gap: 10px;
        }
        .map-link {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 0.82rem; font-weight: 600; color: #004B87;
            padding: 7px 14px; border-radius: 6px;
            background: #eff6ff; border: 1px solid #dbeafe;
            transition: background 0.2s, color 0.2s; flex: 1; justify-content: center;
        }
        .map-link:hover { background: #004B87; color: white; border-color: #004B87; }
        .directions-link {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 0.82rem; font-weight: 600; color: #DA291C;
            padding: 7px 14px; border-radius: 6px;
            background: #fff0f0; border: 1px solid #fecaca;
            transition: background 0.2s, color 0.2s; flex: 1; justify-content: center;
        }
        .directions-link:hover { background: #DA291C; color: white; border-color: #DA291C; }

        /* EMPTY STATE */
        .empty-state { text-align: center; padding: 60px 20px; color: #aaa; }
        .empty-state i { font-size: 3rem; margin-bottom: 16px; color: #d1d5db; display: block; }
        .empty-state p { font-size: 0.95rem; }

        /* FOOTER */
        footer { background: #1a2e4a; color: rgba(255,255,255,0.5); padding: 40px 5% 20px; }
        .footer-grid { max-width: 1100px; margin: 0 auto; display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 32px; padding-bottom: 28px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .footer-brand .logo { font-size: 1.3rem; display: inline-block; margin-bottom: 10px; }
        .footer-brand p { font-size: 0.83rem; line-height: 1.6; }
        .footer-col h4 { color: white; font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px; }
        .footer-col ul { list-style: none; }
        .footer-col ul li { margin-bottom: 7px; }
        .footer-col ul li a { color: rgba(255,255,255,0.6); font-size: 0.83rem; transition: color 0.2s; }
        .footer-col ul li a:hover { color: white; }
        .footer-bottom { max-width: 1100px; margin: 18px auto 0; display: flex; justify-content: space-between; align-items: center; font-size: 0.75rem; color: rgba(255,255,255,0.4); flex-wrap: wrap; gap: 6px; }
        .footer-knight { color: #DA291C; }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .locations-grid { grid-template-columns: 1fr; }
            .footer-grid { grid-template-columns: 1fr 1fr; }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .filter-select { width: 100%; }
        }
        @media (max-width: 480px) {
            .page-hero h1 { font-size: 1.9rem; }
            .menu-toggle { display: block; }
            nav ul {
                position: fixed; top: 0; right: -260px; width: 260px; height: 100vh;
                background: #003366; flex-direction: column; padding-top: 70px;
                transition: right 0.3s; z-index: 150; box-shadow: -5px 0 15px rgba(0,0,0,0.15); gap: 0;
            }
            nav ul.show { right: 0; }
            nav ul li a { padding: 14px 20px; border-radius: 0; }
            .footer-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- NAV -->
<?php include '_public_nav.php'; ?>

<!-- HERO -->
<section class="page-hero">
    <p class="eyebrow">Postal Pro Network</p>
    <h1>Find a Location</h1>
    <p class="sub">Browse all Postal Pro facilities — post offices, distribution hubs, and airport stations across the country.</p>
    <div class="summary-pills">
        <span class="pill"><i class="fas fa-building"></i> <?= count($facilities) ?> Total Facilities</span>
        <span class="pill"><i class="fas fa-envelope"></i> <?= $counts['Post Office'] ?> Post Offices</span>
        <span class="pill"><i class="fas fa-warehouse"></i> <?= $counts['Hub'] ?> Distribution Hubs</span>
        <span class="pill"><i class="fas fa-plane"></i> <?= $counts['Airport'] ?> Airport Stations</span>
    </div>
</section>

<div class="page-body">

    <!-- FILTER BAR -->
    <div class="filter-bar">
        <div class="search-wrap">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" placeholder="Search by city, state, or ZIP…" autocomplete="off">
        </div>

        <select class="filter-select" id="stateFilter">
            <option value="">All States</option>
            <?php foreach ($states as $s): ?>
            <option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></option>
            <?php endforeach; ?>
        </select>

        <button class="filter-btn active" data-type="all" id="btnAll">
            <i class="fas fa-map-marker-alt"></i> All
        </button>
        <button class="filter-btn type-post" data-type="Post Office">
            <i class="fas fa-envelope"></i> Post Offices
        </button>
        <button class="filter-btn type-hub" data-type="Hub">
            <i class="fas fa-warehouse"></i> Hubs
        </button>
        <button class="filter-btn type-airport" data-type="Airport">
            <i class="fas fa-plane"></i> Airports
        </button>
    </div>

    <p class="results-meta" id="resultsMeta">
        Showing <strong id="resultsCount"><?= count($facilities) ?></strong> location<?= count($facilities) != 1 ? 's' : '' ?>
    </p>

    <!-- LOCATION CARDS -->
    <div class="locations-grid" id="locationsGrid">
        <?php foreach ($facilities as $f):
            $mapsQuery  = urlencode($f['street_address'] . ', ' . $f['city'] . ', ' . $f['state'] . ' ' . $f['postal_code']);
            $mapsView   = "https://www.google.com/maps/search/?api=1&query={$mapsQuery}";
            $mapsDir    = "https://www.google.com/maps/dir/?api=1&destination={$mapsQuery}";

            switch ($f['type']) {
                case 'Hub':         $badgeClass = 'badge-hub';         $icon = 'fa-warehouse'; break;
                case 'Airport':     $badgeClass = 'badge-airport';     $icon = 'fa-plane';     break;
                default:            $badgeClass = 'badge-post-office'; $icon = 'fa-envelope';  break;
            }
        ?>
        <div class="loc-card"
             data-type="<?= htmlspecialchars($f['type']) ?>"
             data-city="<?= strtolower($f['city']) ?>"
             data-state="<?= strtolower($f['state']) ?>"
             data-zip="<?= htmlspecialchars($f['postal_code']) ?>">

            <div class="loc-card-top">
                <div>
                    <p class="loc-id">FACILITY #<?= $f['facility_id'] ?></p>
                    <p class="loc-name"><?= htmlspecialchars($f['city']) ?>, <?= htmlspecialchars($f['state']) ?></p>
                </div>
                <span class="type-badge <?= $badgeClass ?>">
                    <i class="fas <?= $icon ?>"></i>
                    <?= htmlspecialchars($f['type']) ?>
                </span>
            </div>

            <div class="loc-card-body">
                <p class="loc-address">
                    <i class="fas fa-location-dot"></i>
                    <?= htmlspecialchars($f['street_address']) ?><br>
                    <?= htmlspecialchars($f['city']) ?>, <?= htmlspecialchars($f['state']) ?> <?= htmlspecialchars($f['postal_code']) ?>
                </p>
                <?php if ($f['type'] === 'Airport' && $f['airport_code']): ?>
                <p class="loc-airport"><i class="fas fa-plane-departure"></i> Airport Code: <?= htmlspecialchars($f['airport_code']) ?></p>
                <?php endif; ?>
            </div>

            <div class="loc-card-footer">
                <a href="<?= $mapsView ?>" target="_blank" class="map-link">
                    <i class="fas fa-map"></i> View on Map
                </a>
                <a href="<?= $mapsDir ?>" target="_blank" class="directions-link">
                    <i class="fas fa-diamond-turn-right"></i> Directions
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="empty-state" id="emptyState" style="display:none;">
        <i class="fas fa-map-location-dot"></i>
        <p>No locations found matching your search.</p>
    </div>

</div>

<!-- FOOTER -->
<footer>
    <div class="footer-grid">
        <div class="footer-brand">
            <a href="index.php" class="logo">POSTAL PRO</a>
            <p>America's trusted postal management network — delivering reliability and transparency to every doorstep.</p>
        </div>
        <div class="footer-col">
            <h4>Quick Links</h4>
            <ul>
                <li><a href="package/track.php">Track a Package</a></li>
                <li><a href="locations.php">Find a Location</a></li>
                <li><a href="<?= $logged_in ? 'shop.php' : 'login.php' ?>">Postal Shop</a></li>
                <li><a href="<?= $logged_in ? 'support.php' : 'login.php' ?>">Customer Support</a></li>
                <li><a href="shipping.php" style="display:inline-flex;align-items:center;gap:6px;">Shipping Options <i class="fas fa-arrow-right" style="font-size:0.7rem;"></i></a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h4>Resources</h4>
            <ul>
                <li><a href="faqs.php">FAQs</a></li>
                <li><a href="about.php">About Postal Pro</a></li>
                <li><a href="locations.php">Our Locations</a></li>
                <li><a href="<?= $logged_in ? 'support.php' : 'login.php' ?>">Contact Support</a></li>
                <li><a href="careers.php">Join Our Team</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h4>Account</h4>
            <ul>
                <?php if($logged_in): ?>
                <li><a href="<?= $user_role == 'Customer' ? 'customer_dashboard.php' : 'employee_dashboard.php' ?>">My Dashboard</a></li>
                <li><a href="edit_profile.php">Edit Profile</a></li>
                <li><a href="logout.php">Logout</a></li>
                <?php else: ?>
                <li><a href="login.php">Sign In</a></li>
                <li><a href="register.php">Create Account</a></li>
                    <li><a href="employee_login.php">Employee Login</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
    <div class="footer-bottom">
        <span>&copy; <?= date('Y') ?> Postal Service Management System. All rights reserved.</span>
        <span>Powered by the Postal Pro Team <i class="fas fa-chess-knight footer-knight"></i></span>
    </div>
</footer>

<script>
    const cards       = Array.from(document.querySelectorAll('.loc-card'));
    const searchInput = document.getElementById('searchInput');
    const stateFilter = document.getElementById('stateFilter');
    const emptyState  = document.getElementById('emptyState');
    const countEl     = document.getElementById('resultsCount');
    const typeBtns    = document.querySelectorAll('.filter-btn');

    let activeType = 'all';

    typeBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            typeBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            activeType = btn.dataset.type;
            applyFilters();
        });
    });

    searchInput.addEventListener('input', applyFilters);
    stateFilter.addEventListener('change', applyFilters);

    function applyFilters() {
        const query     = searchInput.value.toLowerCase().trim();
        const stateSel  = stateFilter.value.toLowerCase();

        let visible = 0;
        cards.forEach(card => {
            const type  = card.dataset.type;
            const city  = card.dataset.city;
            const state = card.dataset.state;
            const zip   = card.dataset.zip;

            const matchesType  = activeType === 'all' || type === activeType;
            const matchesState = !stateSel || state === stateSel;
            const matchesQuery = !query || city.includes(query) || state.includes(query) || zip.includes(query);

            const show = matchesType && matchesState && matchesQuery;
            card.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        countEl.textContent = visible;
        emptyState.style.display = visible === 0 ? 'block' : 'none';
    }
</script>
</body>
</html>
