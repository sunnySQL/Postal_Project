<?php
require_once __DIR__ . '/app_config.php';

// Only start session if one isn't already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ─── AUDIT LOG ───────────────────────────────────────────────────────────────
// Logs a system action to Audit_Log. Safe to call even before the table exists.
// $action_type  e.g. 'PACKAGE_CREATED', 'USER_STATUS_CHANGED'
// $entity_type  e.g. 'Package', 'User', 'Trip', 'Ticket', 'Inventory'
// $entity_id    primary key / tracking number of the affected record
// $description  human-readable one-liner
// $facility_id  null for system-wide actions; set to scope by facility
// $old / $new   associative arrays of before/after values (optional)
function logAudit($conn, $action_type, $entity_type, $entity_id, $description,
                  $facility_id = null, $old = null, $new = null) {
    $user_id        = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0
                        ? (int)$_SESSION['user_id'] : null;
    $performer      = null;
    $performer_role = null;
    $ip             = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // Always look up name and role directly from DB using the user_id
    if ($user_id) {
        $role_check = $_SESSION['role'] ?? '';
        if ($role_check === 'Customer') {
            $ns = $conn->prepare("SELECT first_name, last_name, NULL AS role FROM Customer WHERE user_id=? LIMIT 1");
        } else {
            $ns = $conn->prepare("SELECT first_name, last_name, role FROM Employee WHERE user_id=? LIMIT 1");
        }
        if ($ns) {
            $ns->bind_param("i", $user_id);
            $ns->execute();
            $nr = $ns->get_result()->fetch_assoc();
            $ns->close();
            if ($nr) {
                $fn = trim((string)($nr['first_name'] ?? ''));
                $ln = trim((string)($nr['last_name']  ?? ''));
                $full = trim("$fn $ln");
                if ($full !== '') $performer = $full;
                $r = trim((string)($nr['role'] ?? ''));
                if ($r !== '') $performer_role = $r;
            }
        }
    }

    // Fall back to session values only if DB lookup found nothing
    if ($performer === null) {
        $sn = trim((string)($_SESSION['name'] ?? ''));
        if ($sn !== '' && $sn !== '0') $performer = $sn;
    }
    if ($performer_role === null) {
        $sr = trim((string)($_SESSION['emp_role'] ?? ($_SESSION['role'] ?? '')));
        if ($sr !== '' && $sr !== '0') $performer_role = $sr;
    }
    $old_json      = ($old !== null) ? json_encode($old, JSON_UNESCAPED_UNICODE) : null;
    $new_json      = ($new !== null) ? json_encode($new, JSON_UNESCAPED_UNICODE) : null;

    // Silently skip if table doesn't exist yet
    $tc = $conn->query("SHOW TABLES LIKE 'Audit_Log'");
    if (!$tc || $tc->num_rows === 0) return;

    $stmt = $conn->prepare(
        "INSERT INTO Audit_Log
            (action_type, entity_type, entity_id, performed_by, performer_name,
             performer_role, facility_id, description, old_value, new_value, ip_address)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)"
    );
    if ($stmt) {
        $stmt->bind_param(
            "ssssissssss",
            $action_type, $entity_type, $entity_id,
            $user_id, $performer, $performer_role,
            $facility_id, $description,
            $old_json, $new_json, $ip
        );
        $stmt->execute();
        $stmt->close();
    }
}
// ─────────────────────────────────────────────────────────────────────────────

// Checks if a user is logged in and has the required role
function check_user_role($required_roles = []) {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header("Location: login.php");
        exit();
    }
    if (!empty($required_roles) && !in_array($_SESSION['role'], $required_roles)) {
        header("Location: unauthorized.php");
        exit();
    }
    return true;
}
require "db_connect.php";

function dbConnection() {
    global $host, $username, $password, $database;
    
    $mysqli = new mysqli($host, $username, $password, $database);

    if ($mysqli->connect_errno) {
        error_log("Database connection failed: " . $mysqli->connect_error);
        return false;
    }

    return $mysqli;
}

function getCategories() {
    $mysqli = dbConnection();
    
    if (!$mysqli) {
        return [];  // Return an empty array if the connection fails
    }

    $categories = [];
    $query = "SELECT DISTINCT category FROM items";

    if ($result = $mysqli->query($query)) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
        $result->free();
    } else {
        error_log("Query failed: " . $mysqli->error);
    }

    $mysqli->close();  // Close the connection to prevent memory leaks
    return $categories;
}

function getHomePageProducts($int) {
    $mysqli = dbConnection();
    $result = $mysqli -> query("SELECT * FROM items ORDER BY rand() LIMIT $int");

    while($row = $result ->fetch_assoc()){
        $data[] = $row;
    }
    return $data;
}
function getProductsByCategory($category) {
    $mysqli = dbConnection();

    $smtp = $mysqli -> prepare("SELECT * FROM items WHERE category = ?");
    $smtp -> bind_param("s", $category);
    $smtp -> execute();
    $result = $smtp ->get_result();
    $data = $result -> fetch_all(MYSQLI_ASSOC);
    return $data;
}

function getProductByTitle($title) {
    $mysqli = dbConnection();
    $stmt = $mysqli->prepare("SELECT * FROM items WHERE name = ?");
    $stmt->bind_param("s", $title);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // If no results are found, return null
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();  // Fetch a single product
    } else {
        return null;  // Return null if no product found
    }
}

/**
 * Gets a product by its ID
 * 
 * @param int $product_id The ID of the product to retrieve
 * @return array|null The product data or null if not found
 */
function getProductById($product_id) {
    $mysqli = dbConnection();
    $stmt = $mysqli->prepare("SELECT * FROM items WHERE item_id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // If no results are found, return null
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();  // Fetch a single product
    } else {
        return null;  // Return null if no product found
    }
}

function getMapQuestDistance($originZip, $destZip) {
    $apiKey = '13C2Dtz0oAyMxmRQLMcomvcM47eMXmlH';
    $url = "https://www.mapquestapi.com/directions/v2/route?key={$apiKey}&from={$originZip}&to={$destZip}&unit=m";
    
    $response = file_get_contents($url);
    $data = json_decode($response, true);
    
    if ($data['info']['statuscode'] === 0 && isset($data['route']['distance'])) {
        return $data['route']['distance'];
    }
    return -1; // Default if error
}

function calculateDistanceBasedPostage($weight, $size, $originZip, $destZip, $signature_required = 'N', $shipping_speed = 'Standard') {
    $size_base_rates = [
        'Small Envelope' => 1.50,
        'Medium Envelope' => 3.25,
        'Large Envelope' => 4.75,
        'Small Box' => 5.50,
        'Medium Box' => 8.75,
        'Large Box' => 12.50,
        'Extra Large Box' => 18.00
    ];

    $distance = getMapQuestDistance($originZip, $destZip);
    
    $weight_rate = 0;
    if ($weight <= 1) {
        $weight_rate = 0.50 * $weight;
    } elseif ($weight <= 5) {
        $weight_rate = 0.50 + (0.75 * ($weight - 1));
    } elseif ($weight <= 10) {
        $weight_rate = 0.50 + (0.75 * 4) + (1.25 * ($weight - 5));
    } elseif ($weight <= 20) {
        $weight_rate = 0.50 + (0.75 * 4) + (1.25 * 5) + (1.75 * ($weight - 10));
    } else {
        $weight_rate = 0.50 + (0.75 * 4) + (1.25 * 5) + (1.75 * 10) + (2.25 * ($weight - 20));
    }

    // Distance multiplier 
    $dist_multiplier = 0;
    if ($distance <= 50) {
        $dist_multiplier = 1.0;
    } elseif ($distance <= 100) {
        $dist_multiplier = 1.5;
    } elseif ($distance <= 200) {
        $dist_multiplier = 2.0;
    } elseif ($distance <= 500) {
        $dist_multiplier = 2.5;
    } else {
        $dist_multiplier = 3.0;
    }
    
    // Base rate based on size
    $base_rate = isset($size_base_rates[$size]) ? $size_base_rates[$size] : 0;
    
    // Signature required fee
    $signature_fee = ($signature_required == 'Y') ? 3.50 : 0;
    
    // Shipping speed multiplier
    $speed_multiplier = 1.0;
    $speed_fee = 0.00;
    
    switch ($shipping_speed) {
        case 'Economy':
            $speed_multiplier = 0.8; // 20% discount for slower shipping
            break;
        case 'Standard':
            $speed_multiplier = 1.0; // Standard rate
            break;
        case 'Express':
            $speed_multiplier = 1.7; // 70% premium for faster shipping
            break;
        default:
            $speed_multiplier = 1.0;
    }

    // Calculate final postage with shipping speed
    $postage = (($base_rate + $weight_rate) * $dist_multiplier * $speed_multiplier) + $signature_fee;
    
    // Estimated delivery time based on shipping speed and distance
    $delivery_time = '';
    if ($shipping_speed == 'Economy') {
        if ($distance <= 200) {
            $delivery_time = '5-7 days';
        } else {
            $delivery_time = '7-10 days';
        }
    } elseif ($shipping_speed == 'Standard') {
        if ($distance <= 200) {
            $delivery_time = '3-5 days';
        } else {
            $delivery_time = '5-7 days';
        }
    } elseif ($shipping_speed == 'Express') {
        if ($distance <= 500) {
            $delivery_time = '1-2 days';
        } else {
            $delivery_time = '2-3 days';
        }
    }
    
    // Round to nearest cent
    return [
        'postage' => round($postage, 2),
        'distance' => round($distance, 2),
        'speed_fee' => $speed_fee,
        'speed_multiplier' => $speed_multiplier,
        'delivery_time' => $delivery_time
    ];
}

function getShippingSpeedRate($shipping_speed) {
    $rates = [
        'Economy' => ['multiplier' => 0.8, 'fee' => 0.00, 'description' => 'Slower but more affordable (5-7 days) - Ground transport only'],
        'Standard' => ['multiplier' => 1.0, 'fee' => 0.00, 'description' => 'Regular shipping (3-5 days) - Mix of ground and air transport'],
        'Express' => ['multiplier' => 1.7, 'fee' => 0.00, 'description' => 'Fastest delivery (1-2 days) - Primarily air transport']
    ];
    return isset($rates[$shipping_speed]) ? $rates[$shipping_speed] : $rates['Standard'];
}

function getSizeBaseRate($size) {
    $rates = [
        'Small Envelope' => 1.50,
        'Medium Envelope' => 3.25,
        'Large Envelope' => 4.75,
        'Small Box' => 5.50,
        'Medium Box' => 8.75,
        'Large Box' => 12.50,
        'Extra Large Box' => 18.00
    ];
    return isset($rates[$size]) ? $rates[$size] : 0;
}

function getWeightRate($weight) {
    $weight = floatval($weight);
    if ($weight <= 1) {
        return 0.50 * $weight;
    } elseif ($weight <= 5) {
        return 0.50 + (0.75 * ($weight - 1));
    } elseif ($weight <= 10) {
        return 0.50 + (0.75 * 4) + (1.25 * ($weight - 5));
    } elseif ($weight <= 20) {
        return 0.50 + (0.75 * 4) + (1.25 * 5) + (1.75 * ($weight - 10));
    } else {
        return 0.50 + (0.75 * 4) + (1.25 * 5) + (1.75 * 10) + (2.25 * ($weight - 20));
    }
}

function getDistanceMultiplier($distance) {
    $distance = floatval($distance);
    if ($distance <= 50) {
        return 1.0;
    } elseif ($distance <= 100) {
        return 1.5;
    } elseif ($distance <= 200) {
        return 2.0;
    } elseif ($distance <= 500) {
        return 2.5;
    } else {
        return 3.0;
    }
}

/**
 * Checks if a user needs to change their password and redirects if needed
 */

function check_password_change() {
    global $conn;
    
    // Check if user is logged in and password change is required
    if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
        $user_id = $_SESSION['user_id'];
        
        $current_file = basename($_SERVER['PHP_SELF']);
        if ($current_file !== 'edit_profile.php' && $current_file !== 'logout.php') {
            
            // Check if password change is required
            $stmt = $conn->prepare("SELECT password_change_required FROM Users WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                
                if ($user['password_change_required'] == 1) {
                    $_SESSION['force_password_change'] = true;
                    header("Location: edit_profile.php");
                    exit();
                }
            }
        }
    }
}


?>
