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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize inputs
    $name = trim($_POST['name'] ?? '');
    $price_wholesale = filter_var($_POST['price_wholesale'] ?? 0, FILTER_VALIDATE_FLOAT);
    $sale_price = filter_var($_POST['sale_price'] ?? 0, FILTER_VALIDATE_FLOAT);
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '');
    
    // Basic validation
    if (empty($name) || empty($description) || empty($category) || $price_wholesale <= 0 || $sale_price <= 0) {
        $error = "Please fill in all required fields with valid values.";
    } else {
        // Handle image upload
        $image_path = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $target_dir = "../uploads/items/";
            
            // Create directory if it doesn't exist
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $file_extension;
            $target_file = $target_dir . $filename;
            
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (!in_array(strtolower($file_extension), $allowed_types)) {
                $error = "Only JPG, JPEG, PNG & GIF files are allowed.";
            } elseif ($_FILES['image']['size'] > 5000000) { // 5MB max
                $error = "File is too large. Maximum size is 5MB.";
            } elseif (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                $image_path = 'uploads/items/' . $filename;
            } else {
                $error = "Error uploading file.";
            }
        }
        
        if (empty($error)) {
            // All validation passed, insert the item
            try {
                $stmt = $conn->prepare("INSERT INTO Items (name, price_wholesale, sale_price, description, category, image) 
                                    VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sddsss", $name, $price_wholesale, $sale_price, $description, $category, $image_path);
                
                if ($stmt->execute()) {
                    $item_id = $conn->insert_id;
                    $_SESSION['message'] = "Item #$item_id created successfully.";
                    $_SESSION['message_type'] = "success";
                    logAudit($conn, 'ITEM_ADDED', 'Inventory', (string)$item_id,
                        "New item added: \"{$name}\" (category: {$category}, price: \${$sale_price})",
                        null, null,
                        ['item_id' => $item_id, 'name' => $name, 'category' => $category,
                         'sale_price' => $sale_price, 'price_wholesale' => $price_wholesale]
                    );
                    header("Location: manage_items.php");
                    exit();
                } else {
                    $error = "Error creating item: " . $conn->error;
                }
            } catch (Exception $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Get distinct categories for dropdown
$categories = [];
$category_query = "SELECT DISTINCT category FROM Items ORDER BY category";
$category_result = $conn->query($category_query);
if ($category_result && $category_result->num_rows > 0) {
    while ($row = $category_result->fetch_assoc()) {
        $categories[] = $row['category'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Item — Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen">

<?php $nav_back_url = 'manage_items.php'; $nav_back_text = 'Items'; ?>
<?php include '_nav.php'; ?>

<div class="max-w-2xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-black text-gray-900">Add Item</h1>
            <p class="text-sm text-gray-500 mt-0.5">Add a new product to the postal shop catalog.</p>
        </div>
        <a href="manage_items.php" class="text-sm text-[#004B87] hover:underline"><i class="fas fa-arrow-left mr-1"></i>Back</a>
    </div>

    <?php if (!empty($error)): ?>
    <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded mb-6 text-sm"><i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <form action="add_item.php" method="post" enctype="multipart/form-data">
            <h2 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-5">Item Details</h2>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Item Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" required
                       class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004B87]"
                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" placeholder="e.g., Bubble Wrap Roll">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Category <span class="text-red-500">*</span></label>
                <select name="category" id="category-select"
                        class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004B87] <?= empty($categories) ? 'hidden' : '' ?>">
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>" <?= (isset($_POST['category']) && $_POST['category'] === $cat) ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                    <?php endforeach; ?>
                    <option value="new">+ Add New Category</option>
                </select>
                <input type="text" name="category" id="category-input"
                       class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004B87] <?= !empty($categories) ? 'hidden' : '' ?>"
                       placeholder="Enter new category name"
                       value="<?= htmlspecialchars($_POST['category'] ?? '') ?>">
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Wholesale Price ($) <span class="text-red-500">*</span></label>
                    <input type="number" name="price_wholesale" step="0.01" min="0.01" required
                           class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004B87]"
                           value="<?= htmlspecialchars($_POST['price_wholesale'] ?? '') ?>">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Sale Price ($) <span class="text-red-500">*</span></label>
                    <input type="number" name="sale_price" step="0.01" min="0.01" required
                           class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004B87]"
                           value="<?= htmlspecialchars($_POST['sale_price'] ?? '') ?>">
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Description <span class="text-red-500">*</span></label>
                <textarea name="description" rows="3" required
                          class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004B87]"
                          placeholder="Brief product description..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Product Image</label>
                <input type="file" name="image" accept="image/*"
                       class="w-full text-sm text-gray-600 border border-gray-200 rounded-lg px-3 py-2 file:mr-3 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-[#004B87] hover:file:bg-blue-100">
                <p class="text-xs text-gray-400 mt-1">JPG, PNG or GIF. Recommended 500×500px. Max 5MB.</p>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                <a href="manage_items.php" class="px-5 py-2 rounded-lg border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-100 transition">Cancel</a>
                <button type="submit" class="bg-[#004B87] hover:bg-blue-900 text-white px-5 py-2 rounded-lg text-sm font-semibold transition">
                    <i class="fas fa-box mr-2"></i>Create Item
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sel = document.getElementById('category-select');
    const inp = document.getElementById('category-input');
    if (sel) {
        sel.addEventListener('change', function() {
            if (this.value === 'new') {
                this.classList.add('hidden');
                inp.classList.remove('hidden');
                inp.focus();
                inp.value = '';
            }
        });
    }
});
</script>
</body>
</html>