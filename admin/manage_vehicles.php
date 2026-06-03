<?php
session_start();
require_once '../db_connect.php';
require_once '../functions.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'Admin') {
    header("Location: unauthorized.php");
    exit();
}

// Handle vehicle deletion if requested
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $vehicle_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    
    if ($vehicle_id) {
        // Check if vehicle has any dependencies before deleting
        $check_dependencies = $conn->prepare("
            SELECT 
                (SELECT COUNT(*) FROM Package WHERE vehicle_id = ?) as package_count
        ");
        $check_dependencies->bind_param("i", $vehicle_id);
        $check_dependencies->execute();
        $dependencies = $check_dependencies->get_result()->fetch_assoc();
        
        if ($dependencies['package_count'] > 0) {
            $_SESSION['message'] = "Cannot delete vehicle because it has packages assigned to it.";
            $_SESSION['message_type'] = "error";
        } else {
            // Safe to delete
            $stmt = $conn->prepare("DELETE FROM Vehicle WHERE vehicle_id = ?");
            $stmt->bind_param("i", $vehicle_id);
            
            if ($stmt->execute()) {
                $_SESSION['message'] = "Vehicle deleted successfully.";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Error deleting vehicle: " . $conn->error;
                $_SESSION['message_type'] = "error";
            }
        }
    }
    header("Location: manage_vehicles.php");
    exit();
}

// Get all vehicles with facility info
try {
    $query = "SELECT v.*, 
              CONCAT(f.street_address, ', ', f.city, ', ', f.state, ' ', f.postal_code) AS facility_address,
              f.type AS facility_type
              FROM Vehicle v
              LEFT JOIN Facility f ON v.current_facility_id = f.facility_id
              ORDER BY v.vehicle_type, v.vehicle_id";
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception($conn->error);
    }
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Vehicles — Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen">

<?php include '_nav.php'; ?>

<div class="max-w-6xl mx-auto px-4 py-8">

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-black text-gray-900">Vehicle Management</h1>
            <p class="text-sm text-gray-500 mt-0.5">Manage the fleet of delivery vehicles and aircraft.</p>
        </div>
        <a href="add_vehicle.php" class="bg-[#004B87] hover:bg-blue-900 text-white px-4 py-2 rounded-lg text-sm font-semibold inline-flex items-center gap-2 transition">
            <i class="fas fa-plus"></i> Add Vehicle
        </a>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
    <div class="<?= $_SESSION['message_type'] === 'error' ? 'bg-red-50 border-l-4 border-red-500 text-red-700' : 'bg-green-50 border-l-4 border-green-500 text-green-700' ?> p-4 rounded mb-6 text-sm">
        <i class="fas <?= $_SESSION['message_type'] === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle' ?> mr-2"></i><?= htmlspecialchars($_SESSION['message']) ?>
    </div>
    <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
    <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded mb-6 text-sm"><i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error_message) ?></div>
    <?php else: ?>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100 text-left">
                        <th class="px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wide">ID</th>
                        <th class="px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wide">Type</th>
                        <th class="px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wide">License / Reg.</th>
                        <th class="px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wide">Capacity</th>
                        <th class="px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wide">Current Facility</th>
                        <th class="px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wide">Airline</th>
                        <th class="px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wide">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($vehicle = $result->fetch_assoc()):
                            $typeIcons = ['Van'=>'fa-shuttle-van','Truck'=>'fa-truck','Motorcycle'=>'fa-motorcycle','Drone'=>'fa-robot','Airplane'=>'fa-plane'];
                            $typeColors = ['Van'=>'bg-blue-50 text-blue-700','Truck'=>'bg-green-50 text-green-700','Motorcycle'=>'bg-yellow-50 text-yellow-700','Drone'=>'bg-purple-50 text-purple-700','Airplane'=>'bg-red-50 text-red-700'];
                            $tIcon = $typeIcons[$vehicle['vehicle_type']] ?? 'fa-truck';
                            $tColor = $typeColors[$vehicle['vehicle_type']] ?? 'bg-gray-50 text-gray-700';
                        ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3 text-gray-400 font-mono text-xs">#<?= $vehicle['vehicle_id'] ?></td>
                            <td class="px-4 py-3">
                                <span class="<?= $tColor ?> text-xs font-semibold px-2 py-1 rounded-full inline-flex items-center gap-1">
                                    <i class="fas <?= $tIcon ?>"></i> <?= htmlspecialchars($vehicle['vehicle_type']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-700">
                                <?php if (!empty($vehicle['aircraft_registration'])): ?>
                                    <span class="text-gray-500 text-xs">Reg:</span> <?= htmlspecialchars($vehicle['aircraft_registration']) ?>
                                <?php elseif (!empty($vehicle['license_plate'])): ?>
                                    <?= htmlspecialchars($vehicle['license_plate']) ?>
                                <?php else: ?>
                                    <span class="text-gray-300">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-gray-600"><?= number_format($vehicle['capacity'], 2) ?> kg</td>
                            <td class="px-4 py-3 text-gray-600 text-xs">
                                <?php if (!empty($vehicle['facility_address'])): ?>
                                    <?= htmlspecialchars($vehicle['facility_address']) ?>
                                    <?php if (!empty($vehicle['facility_type'])): ?>
                                        <span class="text-gray-400">(<?= htmlspecialchars($vehicle['facility_type']) ?>)</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-gray-300">Not assigned</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($vehicle['airline'] ?? '') ?: '<span class="text-gray-300">—</span>' ?></td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <a href="edit_vehicle.php?id=<?= $vehicle['vehicle_id'] ?>" class="text-[#004B87] hover:text-blue-900 font-medium transition">
                                        <i class="fas fa-edit mr-1"></i>Edit
                                    </a>
                                    <button onclick="confirmDelete(<?= $vehicle['vehicle_id'] ?>)" class="text-[#DA291C] hover:text-red-800 font-medium transition">
                                        <i class="fas fa-trash mr-1"></i>Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="px-4 py-10 text-center text-gray-400"><i class="fas fa-truck text-2xl mb-2 block"></i>No vehicles found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function confirmDelete(id) {
    if (confirm('Delete this vehicle? This cannot be undone.')) {
        window.location.href = 'manage_vehicles.php?action=delete&id=' + id;
    }
}
</script>
</body>
</html>