<?php
session_start();
require_once '../db_connect.php';
require_once '../functions.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'Admin') {
    header("Location: unauthorized.php");
    exit();
}

// Handle facility deletion if requested
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $facility_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    
    if ($facility_id) {
        // Check if facility has any dependencies before deleting
        $check_dependencies = $conn->prepare("
            SELECT 
                (SELECT COUNT(*) FROM Employee WHERE facility_id = ?) as employee_count,
                (SELECT COUNT(*) FROM Package WHERE facility_id = ?) as package_count,
                (SELECT COUNT(*) FROM Trip WHERE depart_facility_id = ? OR arrive_facility_id = ?) as trip_count
        ");
        $check_dependencies->bind_param("iiii", $facility_id, $facility_id, $facility_id, $facility_id);
        $check_dependencies->execute();
        $dependencies = $check_dependencies->get_result()->fetch_assoc();
        
        if ($dependencies['employee_count'] > 0 || $dependencies['package_count'] > 0 || $dependencies['trip_count'] > 0) {
            $_SESSION['message'] = "Cannot delete facility because it has associated employees, packages, or trips.";
            $_SESSION['message_type'] = "error";
        } else {
            // Safe to delete
            $stmt = $conn->prepare("DELETE FROM Facility WHERE facility_id = ?");
            $stmt->bind_param("i", $facility_id);
            
            if ($stmt->execute()) {
                $_SESSION['message'] = "Facility deleted successfully.";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Error deleting facility: " . $conn->error;
                $_SESSION['message_type'] = "error";
            }
        }
    }
    header("Location: manage_facilities.php");
    exit();
}

// Get all facilities
try {
    $query = "SELECT * FROM Facility ORDER BY facility_id";
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
    <title>Manage Facilities — Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen">

<?php include '_nav.php'; ?>

<div class="max-w-6xl mx-auto px-4 py-8">

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-black text-gray-900">Facility Management</h1>
            <p class="text-sm text-gray-500 mt-0.5">View, edit and manage postal facilities.</p>
        </div>
        <a href="add_facility.php" class="bg-[#004B87] hover:bg-blue-900 text-white px-4 py-2 rounded-lg text-sm font-semibold inline-flex items-center gap-2 transition">
            <i class="fas fa-plus"></i> Add Facility
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
                        <th class="px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wide">Address</th>
                        <th class="px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wide">City</th>
                        <th class="px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wide">State</th>
                        <th class="px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wide">Postal Code</th>
                        <th class="px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wide">Airport</th>
                        <th class="px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wide">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($facility = $result->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3 text-gray-400 font-mono text-xs">#<?= $facility['facility_id'] ?></td>
                            <td class="px-4 py-3">
                                <?php
                                    $tc = ['Hub'=>'bg-blue-100 text-blue-700','Post Office'=>'bg-green-100 text-green-700','Airport'=>'bg-purple-100 text-purple-700'];
                                    $cls = $tc[$facility['type']] ?? 'bg-gray-100 text-gray-700';
                                ?>
                                <span class="<?= $cls ?> text-xs font-semibold px-2 py-1 rounded-full"><?= htmlspecialchars($facility['type']) ?></span>
                            </td>
                            <td class="px-4 py-3 text-gray-700"><?= htmlspecialchars($facility['street_address']) ?></td>
                            <td class="px-4 py-3 font-semibold text-gray-800"><?= htmlspecialchars($facility['city']) ?></td>
                            <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($facility['state']) ?></td>
                            <td class="px-4 py-3 text-gray-600 font-mono text-xs"><?= htmlspecialchars($facility['postal_code']) ?></td>
                            <td class="px-4 py-3">
                                <?php if ($facility['airport_code']): ?>
                                    <span class="bg-yellow-100 text-yellow-700 text-xs font-bold px-2 py-1 rounded"><?= htmlspecialchars($facility['airport_code']) ?></span>
                                <?php else: ?>
                                    <span class="text-gray-300">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <a href="edit_facility.php?id=<?= $facility['facility_id'] ?>" class="text-[#004B87] hover:text-blue-900 font-medium transition">
                                        <i class="fas fa-edit mr-1"></i>Edit
                                    </a>
                                    <button onclick="confirmDelete(<?= $facility['facility_id'] ?>)" class="text-[#DA291C] hover:text-red-800 font-medium transition">
                                        <i class="fas fa-trash mr-1"></i>Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="px-4 py-10 text-center text-gray-400"><i class="fas fa-building text-2xl mb-2 block"></i>No facilities found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function confirmDelete(id) {
    if (confirm('Delete this facility? This cannot be undone.')) {
        window.location.href = 'manage_facilities.php?action=delete&id=' + id;
    }
}
</script>
</body>
</html>