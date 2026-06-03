<?php
session_start();
require_once '../db_connect.php';
require_once '../functions.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'Admin') {
    header("Location: unauthorized.php");
    exit();
}

$error = '';
$success = '';

// Get all facilities for dropdown
$facilities = [];
$facilities_query = "SELECT facility_id, CONCAT(street_address, ', ', city, ', ', state, ' ', postal_code) AS facility_address, type FROM Facility ORDER BY state, city";
$facilities_result = $conn->query($facilities_query);

if ($facilities_result && $facilities_result->num_rows > 0) {
    while ($row = $facilities_result->fetch_assoc()) {
        $facilities[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize inputs
    $vehicle_type = trim($_POST['vehicle_type'] ?? '');
    $license_plate = trim($_POST['license_plate'] ?? '');
    $capacity = filter_var($_POST['capacity'] ?? 0, FILTER_VALIDATE_FLOAT);
    $current_facility_id = filter_var($_POST['current_facility_id'] ?? null, FILTER_VALIDATE_INT);
    $aircraft_registration = trim($_POST['aircraft_registration'] ?? '');
    $airline = trim($_POST['airline'] ?? '');
    
    // Basic validation
    if (empty($vehicle_type) || $capacity <= 0) {
        $error = "Please fill in all required fields with valid values.";
    } else {
        // Set nulls for optional fields if empty
        if (empty($license_plate)) $license_plate = null;
        if (empty($current_facility_id)) $current_facility_id = null;
        if (empty($aircraft_registration)) $aircraft_registration = null;
        if (empty($airline)) $airline = null;
        
        // Additional validation for specific vehicle types
        if ($vehicle_type == 'Airplane' && empty($aircraft_registration)) {
            $error = "Aircraft registration is required for airplanes.";
        } else if (($vehicle_type == 'Van' || $vehicle_type == 'Truck' || $vehicle_type == 'Motorcycle') && empty($license_plate)) {
            $error = "License plate is required for " . strtolower($vehicle_type) . "s.";
        }
        
        if (empty($error)) {
            // All validation passed, insert the vehicle
            try {
                $stmt = $conn->prepare("INSERT INTO Vehicle (vehicle_type, license_plate, capacity, current_facility_id, aircraft_registration, airline) 
                                       VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssdiis", $vehicle_type, $license_plate, $capacity, $current_facility_id, $aircraft_registration, $airline);
                
                if ($stmt->execute()) {
                    $vehicle_id = $conn->insert_id;
                    $_SESSION['message'] = "Vehicle #$vehicle_id created successfully.";
                    $_SESSION['message_type'] = "success";
                    
                    // Redirect to the management page
                    header("Location: manage_vehicles.php");
                    exit();
                } else {
                    $error = "Error creating vehicle: " . $conn->error;
                }
            } catch (Exception $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Vehicle — Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen">

<?php $nav_back_url = 'manage_vehicles.php'; $nav_back_text = 'Vehicles'; ?>
<?php include '_nav.php'; ?>

<div class="max-w-2xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-black text-gray-900">Add Vehicle</h1>
            <p class="text-sm text-gray-500 mt-0.5">Register a new vehicle or aircraft to the fleet.</p>
        </div>
        <a href="manage_vehicles.php" class="text-sm text-[#004B87] hover:underline"><i class="fas fa-arrow-left mr-1"></i>Back</a>
    </div>

    <?php if (!empty($error)): ?>
    <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded mb-6 text-sm"><i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <form action="add_vehicle.php" method="post">
            <h2 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-5">Vehicle Info</h2>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Vehicle Type <span class="text-red-500">*</span></label>
                <select name="vehicle_type" id="vehicle_type" required
                        class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004B87]">
                    <option value="">Select Vehicle Type</option>
                    <?php foreach (['Van','Truck','Motorcycle','Drone','Airplane'] as $vt): ?>
                    <option value="<?= $vt ?>" <?= (isset($_POST['vehicle_type']) && $_POST['vehicle_type'] === $vt) ? 'selected' : '' ?>><?= $vt ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-1">License Plate <span class="license-required hidden text-red-500">*</span></label>
                <input type="text" name="license_plate" id="license_plate"
                       class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004B87] uppercase"
                       value="<?= htmlspecialchars($_POST['license_plate'] ?? '') ?>" placeholder="e.g., ABC-1234">
                <p class="text-xs text-gray-400 mt-1">Required for vans, trucks, and motorcycles.</p>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Capacity (kg) <span class="text-red-500">*</span></label>
                <input type="number" name="capacity" step="0.01" min="0.01" required
                       class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004B87]"
                       value="<?= htmlspecialchars($_POST['capacity'] ?? '') ?>">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Current Facility <span class="text-gray-400 font-normal">(optional)</span></label>
                <select name="current_facility_id" id="current_facility_id"
                        class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004B87]">
                    <option value="">Not Assigned</option>
                    <?php foreach ($facilities as $facility): ?>
                    <option value="<?= $facility['facility_id'] ?>" <?= (isset($_POST['current_facility_id']) && $_POST['current_facility_id'] == $facility['facility_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($facility['facility_address']) ?> (<?= htmlspecialchars($facility['type']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="airplaneSection" class="mb-4 hidden">
                <div class="border-t border-gray-100 pt-4 mt-2">
                    <h2 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4">Aircraft Details</h2>
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Aircraft Registration <span class="aircraft-required hidden text-red-500">*</span></label>
                        <input type="text" name="aircraft_registration" id="aircraft_registration"
                               class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004B87] uppercase"
                               value="<?= htmlspecialchars($_POST['aircraft_registration'] ?? '') ?>" placeholder="e.g., N12345">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Airline <span class="text-gray-400 font-normal">(optional)</span></label>
                        <input type="text" name="airline"
                               class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004B87]"
                               value="<?= htmlspecialchars($_POST['airline'] ?? '') ?>" placeholder="e.g., FedEx Air">
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100 mt-4">
                <a href="manage_vehicles.php" class="px-5 py-2 rounded-lg border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-100 transition">Cancel</a>
                <button type="submit" class="bg-[#004B87] hover:bg-blue-900 text-white px-5 py-2 rounded-lg text-sm font-semibold transition">
                    <i class="fas fa-truck mr-2"></i>Create Vehicle
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sel = document.getElementById('vehicle_type');
    const airSec = document.getElementById('airplaneSection');
    const aircraftReq = document.querySelector('.aircraft-required');
    const licenseReq = document.querySelector('.license-required');
    function update() {
        const t = sel.value;
        if (t === 'Airplane') { airSec.classList.remove('hidden'); aircraftReq.classList.remove('hidden'); }
        else { airSec.classList.add('hidden'); aircraftReq.classList.add('hidden'); }
        if (['Van','Truck','Motorcycle'].includes(t)) licenseReq.classList.remove('hidden');
        else licenseReq.classList.add('hidden');
    }
    update();
    sel.addEventListener('change', update);
});
</script>
</body>
</html>