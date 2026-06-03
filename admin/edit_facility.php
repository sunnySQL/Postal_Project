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

// Get facility ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['message'] = "Invalid facility ID.";
    $_SESSION['message_type'] = "error";
    header("Location: manage_facilities.php");
    exit();
}

$facility_id = intval($_GET['id']);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize inputs
    $street_address = trim($_POST['street_address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $postal_code = trim($_POST['postal_code'] ?? '');
    $type = $_POST['type'] ?? '';
    $airport_code = !empty($_POST['airport_code']) ? trim($_POST['airport_code']) : null;
    
    // Basic validation
    if (empty($street_address) || empty($city) || empty($state) || empty($postal_code) || empty($type)) {
        $error = "Please fill in all required fields.";
    } elseif ($type === 'Airport' && empty($airport_code)) {
        $error = "Airport code is required for Airport facility type.";
    } else {
        // All validation passed, update the facility
        try {
            $stmt = $conn->prepare("UPDATE Facility SET street_address = ?, city = ?, state = ?, 
                                 postal_code = ?, type = ?, airport_code = ? 
                                 WHERE facility_id = ?");
            $stmt->bind_param("ssssssi", $street_address, $city, $state, $postal_code, $type, $airport_code, $facility_id);
            
            if ($stmt->execute()) {
                $_SESSION['message'] = "Facility #$facility_id updated successfully.";
                $_SESSION['message_type'] = "success";
                
                // Redirect to the management page
                header("Location: manage_facilities.php");
                exit();
            } else {
                $error = "Error updating facility: " . $conn->error;
            }
        } catch (Exception $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
} else {
    // GET request - fetch facility data
    $stmt = $conn->prepare("SELECT * FROM Facility WHERE facility_id = ?");
    $stmt->bind_param("i", $facility_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['message'] = "Facility not found.";
        $_SESSION['message_type'] = "error";
        header("Location: manage_facilities.php");
        exit();
    }
    
    $facility = $result->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Facility — Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen">

<?php $nav_back_url = 'manage_facilities.php'; $nav_back_text = 'Facilities'; ?>
<?php include '_nav.php'; ?>

<div class="max-w-2xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-black text-gray-900">Edit Facility <span class="text-[#004B87]">#<?= $facility_id ?></span></h1>
            <p class="text-sm text-gray-500 mt-0.5"><?= htmlspecialchars($facility['city'] ?? '') ?>, <?= htmlspecialchars($facility['state'] ?? '') ?> &mdash; <?= htmlspecialchars($facility['type'] ?? '') ?></p>
        </div>
        <a href="manage_facilities.php" class="text-sm text-[#004B87] hover:underline"><i class="fas fa-arrow-left mr-1"></i>Back</a>
    </div>

    <?php if (!empty($error)): ?>
    <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded mb-6 text-sm"><i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <form action="edit_facility.php?id=<?= $facility_id ?>" method="post">
            <h2 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-5">Facility Info</h2>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Facility Type <span class="text-red-500">*</span></label>
                <select name="type" id="type" required onchange="toggleAirportCode()"
                        class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004B87]">
                    <option value="">Select Type</option>
                    <option value="Hub" <?= isset($facility) && $facility['type'] === 'Hub' ? 'selected' : '' ?>>Hub</option>
                    <option value="Post Office" <?= isset($facility) && $facility['type'] === 'Post Office' ? 'selected' : '' ?>>Post Office</option>
                    <option value="Airport" <?= isset($facility) && $facility['type'] === 'Airport' ? 'selected' : '' ?>>Airport</option>
                </select>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Street Address <span class="text-red-500">*</span></label>
                <input type="text" name="street_address" required
                       class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004B87]"
                       value="<?= htmlspecialchars($facility['street_address'] ?? '') ?>">
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">City <span class="text-red-500">*</span></label>
                    <input type="text" name="city" required
                           class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004B87]"
                           value="<?= htmlspecialchars($facility['city'] ?? '') ?>">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">State <span class="text-red-500">*</span></label>
                    <input type="text" name="state" required maxlength="2"
                           class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004B87]"
                           value="<?= htmlspecialchars($facility['state'] ?? '') ?>">
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Postal Code <span class="text-red-500">*</span></label>
                <input type="text" name="postal_code" required
                       class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004B87]"
                       value="<?= htmlspecialchars($facility['postal_code'] ?? '') ?>">
            </div>

            <div id="airport_code_section" class="mb-4 <?= isset($facility) && $facility['type'] === 'Airport' ? '' : 'hidden' ?>">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Airport Code (IATA) <span class="text-red-500">*</span></label>
                <input type="text" name="airport_code" id="airport_code" maxlength="3"
                       class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004B87] uppercase"
                       value="<?= htmlspecialchars($facility['airport_code'] ?? '') ?>" placeholder="LAX">
                <p class="text-xs text-gray-400 mt-1">Required for Airport type. 3-letter IATA code.</p>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100 mt-6">
                <a href="manage_facilities.php" class="px-5 py-2 rounded-lg border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-100 transition">Cancel</a>
                <button type="submit" class="bg-[#004B87] hover:bg-blue-900 text-white px-5 py-2 rounded-lg text-sm font-semibold transition">
                    <i class="fas fa-save mr-2"></i>Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleAirportCode() {
    const t = document.getElementById('type').value;
    const s = document.getElementById('airport_code_section');
    const i = document.getElementById('airport_code');
    if (t === 'Airport') { s.classList.remove('hidden'); i.setAttribute('required','required'); }
    else { s.classList.add('hidden'); i.removeAttribute('required'); }
}
document.addEventListener('DOMContentLoaded', toggleAirportCode);
</script>
</body>
</html>