<?php
session_start();
require_once '../db_connect.php';
require_once '../functions.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'Admin') {
    header("Location: unauthorized.php");
    exit();
}

// Handle item deletion if requested
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $item_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    
    if ($item_id) {
        // Check if item has any dependencies before deleting
        $check_dependencies = $conn->prepare("
            SELECT 
                (SELECT COUNT(*) FROM Inventory WHERE item_id = ?) as inventory_count,
                (SELECT COUNT(*) FROM Shop_Sale WHERE item_id = ?) as sale_count
        ");
        $check_dependencies->bind_param("ii", $item_id, $item_id);
        $check_dependencies->execute();
        $dependencies = $check_dependencies->get_result()->fetch_assoc();
        
        if ($dependencies['inventory_count'] > 0 || $dependencies['sale_count'] > 0) {
            $_SESSION['message'] = "Cannot delete item because it is in inventory or has sales records.";
            $_SESSION['message_type'] = "error";
        } else {
            // Fetch item details before deleting for audit
            $item_info = $conn->prepare("SELECT name, category, sale_price FROM Items WHERE item_id = ?");
            $item_info->bind_param("i", $item_id);
            $item_info->execute();
            $item_row = $item_info->get_result()->fetch_assoc();

            $stmt = $conn->prepare("DELETE FROM Items WHERE item_id = ?");
            $stmt->bind_param("i", $item_id);

            if ($stmt->execute()) {
                $_SESSION['message'] = "Item deleted successfully.";
                $_SESSION['message_type'] = "success";
                logAudit($conn, 'ITEM_DELETED', 'Inventory', (string)$item_id,
                    "Item #{$item_id} \"{$item_row['name']}\" deleted from catalog",
                    null,
                    ['item_id' => $item_id, 'name' => $item_row['name'],
                     'category' => $item_row['category'], 'sale_price' => $item_row['sale_price']],
                    null
                );
            } else {
                $_SESSION['message'] = "Error deleting item: " . $conn->error;
                $_SESSION['message_type'] = "error";
            }
        }
    }
    header("Location: manage_items.php");
    exit();
}

// Handle stock level update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_levels') {
    $item_id   = (int)$_POST['item_id'];
    $min_stock = max(0, (int)$_POST['min_stock']);
    $max_stock = max(1, (int)$_POST['max_stock']);
    if ($max_stock < $min_stock) $max_stock = $min_stock + 1;

    $upd = $conn->prepare("UPDATE Inventory SET min_stock_level = ?, max_stock_level = ? WHERE item_id = ?");
    $upd->bind_param("iii", $min_stock, $max_stock, $item_id);
    if ($upd->execute()) {
        $_SESSION['message'] = "Stock thresholds updated for all shops carrying this item.";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error updating thresholds: " . $conn->error;
        $_SESSION['message_type'] = "error";
    }
    header("Location: manage_items.php");
    exit();
}

// Handle unenrolling an item from one or more shops
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'unenroll_item') {
    $item_id  = (int)$_POST['item_id'];
    $shop_ids = array_map('intval', $_POST['shop_ids'] ?? []);

    if (empty($shop_ids)) {
        $_SESSION['message'] = "Please select at least one shop to unenroll from.";
        $_SESSION['message_type'] = "error";
    } else {
        $removed = 0;
        foreach ($shop_ids as $shop_id) {
            // Soft-delete: mark inactive so sales history is preserved
            $upd = $conn->prepare("UPDATE Inventory SET is_active = 0 WHERE item_id = ? AND shop_id = ?");
            $upd->bind_param("ii", $item_id, $shop_id);
            $upd->execute();
            if ($upd->affected_rows > 0) $removed++;
        }
        $_SESSION['message']      = $removed > 0 ? "Removed from {$removed} shop(s)." : "Nothing changed.";
        $_SESSION['message_type'] = $removed > 0 ? "success" : "error";
    }
    header("Location: manage_items.php");
    exit();
}

// Handle enrolling an item into one or more shops
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'enroll_item') {
    $item_id   = (int)$_POST['item_id'];
    $shop_ids  = array_map('intval', $_POST['shop_ids'] ?? []);
    $quantity  = max(0, (int)$_POST['quantity']);
    $min_stock = max(0, (int)$_POST['min_stock']);
    $max_stock = max(1, (int)$_POST['max_stock']);
    if ($max_stock < $min_stock) $max_stock = $min_stock + 1;

    if (empty($shop_ids)) {
        $_SESSION['message'] = "Please select at least one shop.";
        $_SESSION['message_type'] = "error";
    } else {
        $enrolled = 0; $skipped = 0;
        foreach ($shop_ids as $shop_id) {
            // Check if a record exists (active or inactive)
            $chk = $conn->prepare("SELECT inventory_id, is_active FROM Inventory WHERE item_id = ? AND shop_id = ?");
            $chk->bind_param("ii", $item_id, $shop_id);
            $chk->execute();
            $existing = $chk->get_result()->fetch_assoc();

            if ($existing) {
                if ((int)$existing['is_active'] === 1) {
                    // Already active — skip
                    $skipped++;
                } else {
                    // Reactivate a previously removed record with new values
                    $rea = $conn->prepare("UPDATE Inventory SET is_active=1, quantity=?, min_stock_level=?, max_stock_level=?, last_updated=CURRENT_TIMESTAMP WHERE inventory_id=?");
                    $rea->bind_param("iiii", $quantity, $min_stock, $max_stock, $existing['inventory_id']);
                    $rea->execute();
                    $enrolled++;
                }
            } else {
                $ins = $conn->prepare("INSERT INTO Inventory (shop_id, item_id, quantity, min_stock_level, max_stock_level) VALUES (?, ?, ?, ?, ?)");
                $ins->bind_param("iiiii", $shop_id, $item_id, $quantity, $min_stock, $max_stock);
                $ins->execute();
                $enrolled++;
            }
        }
        if ($enrolled > 0) {
            $_SESSION['message'] = "Enrolled in {$enrolled} shop(s)." . ($skipped ? " {$skipped} already active and were skipped." : "");
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "All selected shops already carry this item.";
            $_SESSION['message_type'] = "error";
        }
    }
    header("Location: manage_items.php");
    exit();
}

// Get all shops for enroll modal
$shops = $conn->query("SELECT s.shop_id, s.shop_name, f.city FROM Shop s JOIN Facility f ON s.facility_id = f.facility_id ORDER BY f.city");
$shops_list = [];
while ($s = $shops->fetch_assoc()) $shops_list[] = $s;

// Get which shops each item is already enrolled in (for the enroll modal)
$enrolled_map = [];
$enrolled_res = $conn->query("SELECT item_id, GROUP_CONCAT(shop_id) as shop_ids FROM Inventory WHERE is_active = 1 GROUP BY item_id");
while ($e = $enrolled_res->fetch_assoc()) {
    $enrolled_map[$e['item_id']] = array_map('intval', explode(',', $e['shop_ids']));
}

// Get all items with aggregated inventory thresholds
try {
    $query = "SELECT i.*,
                COALESCE(MIN(inv.min_stock_level), 0)    as min_stock,
                COALESCE(MAX(inv.max_stock_level), 9999) as max_stock,
                COUNT(inv.inventory_id)                  as shop_count
              FROM Items i
              LEFT JOIN Inventory inv ON i.item_id = inv.item_id AND inv.is_active = 1
              GROUP BY i.item_id
              ORDER BY i.category, i.name";
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
    <title>Manage Items — Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen">

<?php include '_nav.php'; ?>

<div class="max-w-6xl mx-auto px-4 py-8">

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-black text-gray-900">Item Management</h1>
            <p class="text-sm text-gray-500 mt-0.5">Manage the postal shop catalog — items, pricing and categories.</p>
        </div>
        <a href="add_item.php" class="bg-[#004B87] hover:bg-blue-900 text-white px-4 py-2 rounded-lg text-sm font-semibold inline-flex items-center gap-2 transition">
            <i class="fas fa-plus"></i> Add Item
        </a>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
    <?php $is_err = $_SESSION['message_type'] === 'error'; ?>
    <div id="ppToast"
         style="position:fixed;top:80px;right:24px;z-index:9999;min-width:280px;max-width:400px;
                display:flex;align-items:flex-start;gap:12px;
                padding:14px 16px;border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,0.18);
                background:<?= $is_err ? '#fff1f2' : '#f0fdf4' ?>;
                border-left:4px solid <?= $is_err ? '#ef4444' : '#22c55e' ?>;
                transform:translateX(120%);opacity:0;
                transition:transform 0.35s cubic-bezier(.34,1.4,.64,1),opacity 0.3s ease;">
        <i class="fas <?= $is_err ? 'fa-times-circle' : 'fa-check-circle' ?>"
           style="font-size:1.15rem;margin-top:1px;flex-shrink:0;color:<?= $is_err ? '#ef4444' : '#22c55e' ?>;"></i>
        <span style="font-size:0.875rem;font-weight:500;color:<?= $is_err ? '#b91c1c' : '#15803d' ?>;line-height:1.4;">
            <?= htmlspecialchars($_SESSION['message']) ?>
        </span>
        <button onclick="dismissToast()" aria-label="Close"
                style="margin-left:auto;flex-shrink:0;background:none;border:none;cursor:pointer;
                       opacity:0.45;font-size:1rem;line-height:1;color:inherit;padding:0 0 0 6px;">
            &times;
        </button>
    </div>
    <script>
    (function(){
        var t = document.getElementById('ppToast');
        if (!t) return;
        requestAnimationFrame(function(){
            requestAnimationFrame(function(){
                t.style.transform = 'translateX(0)';
                t.style.opacity   = '1';
            });
        });
        var timer = setTimeout(dismissToast, 3500);
        function dismissToast(){
            clearTimeout(timer);
            t.style.transition = 'transform 0.3s ease,opacity 0.3s ease';
            t.style.transform  = 'translateX(120%)';
            t.style.opacity    = '0';
            setTimeout(function(){ t.remove(); }, 320);
        }
        window.dismissToast = dismissToast;
    })();
    </script>
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
                        <th class="px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wide">Image</th>
                        <th class="px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wide">Name</th>
                        <th class="px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wide">Category</th>
                        <th class="px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wide">Wholesale</th>
                        <th class="px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wide">Sale Price</th>
                        <th class="px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wide">Min Stock</th>
                        <th class="px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wide">Max Stock</th>
                        <th class="px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wide">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($item = $result->fetch_assoc()): ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3 text-gray-400 font-mono text-xs">#<?= $item['item_id'] ?></td>
                            <td class="px-4 py-3">
                                <?php if (!empty($item['image'])): ?>
                                    <img src="../<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="h-10 w-10 object-cover rounded-lg border border-gray-100">
                                <?php else: ?>
                                    <div class="h-10 w-10 bg-gray-100 rounded-lg flex items-center justify-center text-gray-300">
                                        <i class="fas fa-image"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 font-semibold text-gray-800"><?= htmlspecialchars($item['name']) ?></td>
                            <td class="px-4 py-3">
                                <span class="bg-blue-50 text-blue-700 text-xs font-semibold px-2 py-1 rounded-full"><?= htmlspecialchars($item['category']) ?></span>
                            </td>
                            <td class="px-4 py-3 text-gray-500">$<?= number_format($item['price_wholesale'], 2) ?></td>
                            <td class="px-4 py-3 font-semibold text-gray-800">$<?= number_format($item['sale_price'], 2) ?></td>
                            <td class="px-4 py-3">
                                <?php if ($item['shop_count'] > 0): ?>
                                <span class="font-semibold text-red-600"><?= $item['min_stock'] ?></span>
                                <?php else: ?>
                                <span class="text-gray-400 text-xs">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3">
                                <?php if ($item['shop_count'] > 0): ?>
                                <span class="font-semibold text-blue-600"><?= $item['max_stock'] ?></span>
                                <?php else: ?>
                                <span class="text-gray-400 text-xs">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3">
                                <?php
                                $enrolled_shops  = $enrolled_map[$item['item_id']] ?? [];
                                $available_shops = array_values(array_filter($shops_list, fn($s) => !in_array($s['shop_id'], $enrolled_shops)));
                                ?>
                                <div class="flex items-center gap-1">
                                    <!-- Edit -->
                                    <a href="edit_item.php?id=<?= $item['item_id'] ?>"
                                       title="Edit item"
                                       class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-blue-50 text-[#004B87] hover:bg-blue-100 transition" >
                                        <i class="fas fa-edit text-xs"></i>
                                    </a>
                                    <!-- Thresholds -->
                                    <?php if ($item['shop_count'] > 0): ?>
                                    <button onclick="openLevels(<?= $item['item_id'] ?>, <?= $item['min_stock'] ?>, <?= $item['max_stock'] ?>, '<?= htmlspecialchars(addslashes($item['name'])) ?>')"
                                            title="Set stock thresholds"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-green-50 text-green-700 hover:bg-green-100 transition">
                                        <i class="fas fa-sliders-h text-xs"></i>
                                    </button>
                                    <?php else: ?>
                                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-gray-50 text-gray-300 cursor-not-allowed" title="Enroll in a shop first">
                                        <i class="fas fa-sliders-h text-xs"></i>
                                    </span>
                                    <?php endif; ?>
                                    <!-- Manage Shops (enroll + unenroll combined) -->
                                    <?php
                                    $currently_enrolled = array_values(array_filter($shops_list, fn($s) => in_array($s['shop_id'], $enrolled_shops)));
                                    $can_manage = !empty($available_shops) || !empty($currently_enrolled);
                                    ?>
                                    <?php if ($can_manage): ?>
                                    <button onclick="openManageShops(<?= $item['item_id'] ?>, '<?= htmlspecialchars(addslashes($item['name'])) ?>', <?= htmlspecialchars(json_encode($available_shops)) ?>, <?= htmlspecialchars(json_encode($currently_enrolled)) ?>)"
                                            title="Manage shops"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-purple-50 text-purple-700 hover:bg-purple-100 transition">
                                        <i class="fas fa-store text-xs"></i>
                                    </button>
                                    <?php else: ?>
                                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-gray-50 text-gray-300 cursor-not-allowed" title="No shops available">
                                        <i class="fas fa-store text-xs"></i>
                                    </span>
                                    <?php endif; ?>
                                    <!-- Delete -->
                                    <button onclick="confirmDelete(<?= $item['item_id'] ?>)"
                                            title="Delete item"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-50 text-[#DA291C] hover:bg-red-100 transition">
                                        <i class="fas fa-trash text-xs"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="px-4 py-10 text-center text-gray-400"><i class="fas fa-box-open text-2xl mb-2 block"></i>No items found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Stock Threshold Modal -->
<div id="levelsModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/40">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <div class="flex items-start justify-between mb-1">
            <h3 class="text-lg font-bold text-gray-800">Set Stock Thresholds</h3>
            <button onclick="closeLevels()" class="text-gray-400 hover:text-gray-600 text-xl leading-none ml-4">&times;</button>
        </div>
        <p id="levelsItemName" class="text-sm text-[#004B87] font-semibold mb-4"></p>
        <form method="POST" action="manage_items.php">
            <input type="hidden" name="action" value="update_levels">
            <input type="hidden" name="item_id" id="levelsItemId">
            <div class="grid grid-cols-2 gap-6 mb-2">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Min Stock</label>
                    <input type="number" name="min_stock" id="levelsMin" min="0" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#004B87]">
                    <p class="text-xs text-red-500 mt-1">Triggers low-stock alert</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Max Stock</label>
                    <input type="number" name="max_stock" id="levelsMax" min="1" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#004B87]">
                    <p class="text-xs text-blue-500 mt-1">Triggers overstock alert</p>
                </div>
            </div>
            <p class="text-xs text-gray-400 mb-5">Applied to all shops carrying this item.</p>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeLevels()" class="px-4 py-2 rounded-lg border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-100 transition">Cancel</button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-[#004B87] hover:bg-blue-900 text-white text-sm font-semibold transition">Save Thresholds</button>
            </div>
        </form>
    </div>
</div>

<!-- Manage Shops Modal (Enroll + Unenroll combined) -->
<div id="manageShopsModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/40">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <div class="flex items-start justify-between mb-1">
            <h3 class="text-lg font-bold text-gray-800">Manage Shops</h3>
            <button onclick="closeManageShops()" class="text-gray-400 hover:text-gray-600 text-xl leading-none ml-4">&times;</button>
        </div>
        <p id="manageShopsItemName" class="text-sm text-[#004B87] font-semibold mb-4"></p>

        <!-- Tabs -->
        <div class="flex border-b border-gray-200 mb-5 gap-0">
            <button type="button" id="tabEnroll" onclick="switchTab('enroll')"
                    class="px-4 py-2 text-sm font-semibold border-b-2 border-purple-600 text-purple-700 -mb-px transition">
                <i class="fas fa-plus mr-1"></i>Enroll
            </button>
            <button type="button" id="tabUnenroll" onclick="switchTab('unenroll')"
                    class="px-4 py-2 text-sm font-semibold border-b-2 border-transparent text-gray-500 hover:text-gray-700 -mb-px transition">
                <i class="fas fa-minus mr-1"></i>Remove
            </button>
        </div>

        <!-- Enroll Panel -->
        <div id="panelEnroll">
            <form method="POST" action="manage_items.php" onsubmit="return validateEnroll()">
                <input type="hidden" name="action" value="enroll_item">
                <input type="hidden" name="item_id" id="enrollItemId">
                <div class="mb-4">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Select Facilities to Add</label>
                    <div id="enrollShopList" class="space-y-2 border border-gray-200 rounded-lg p-3 bg-gray-50 min-h-[48px]"></div>
                </div>
                <div class="grid grid-cols-3 gap-3 mb-5">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Start Qty</label>
                        <input type="number" name="quantity" value="0" min="0" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Min Stock</label>
                        <input type="number" name="min_stock" value="10" min="0" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-500">
                        <p class="text-xs text-red-400 mt-0.5">Alert level</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Max Stock</label>
                        <input type="number" name="max_stock" value="100" min="1" required
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-500">
                        <p class="text-xs text-blue-400 mt-0.5">Overstock</p>
                    </div>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeManageShops()" class="px-4 py-2 rounded-lg border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-100 transition">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-lg bg-purple-700 hover:bg-purple-900 text-white text-sm font-semibold transition">
                        <i class="fas fa-store mr-1"></i>Enroll
                    </button>
                </div>
            </form>
        </div>

        <!-- Remove Panel -->
        <div id="panelUnenroll" class="hidden">
            <p class="text-xs text-gray-500 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 mb-4">
                <i class="fas fa-info-circle mr-1"></i>
                The item will be hidden from the shop's inventory. Existing sales history is preserved.
            </p>
            <form method="POST" action="manage_items.php" onsubmit="return validateUnenroll()">
                <input type="hidden" name="action" value="unenroll_item">
                <input type="hidden" name="item_id" id="unenrollItemId">
                <div class="mb-5">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Select Facilities to Remove</label>
                    <div id="unenrollShopList" class="space-y-2 border border-gray-200 rounded-lg p-3 bg-gray-50 min-h-[48px]"></div>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeManageShops()" class="px-4 py-2 rounded-lg border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-100 transition">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-lg bg-orange-600 hover:bg-orange-700 text-white text-sm font-semibold transition">
                        <i class="fas fa-store-slash mr-1"></i>Remove
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function confirmDelete(id) {
    if (confirm('Delete this item? This cannot be undone.')) {
        window.location.href = 'manage_items.php?action=delete&id=' + id;
    }
}
function openLevels(id, min, max, name) {
    document.getElementById('levelsItemId').value  = id;
    document.getElementById('levelsMin').value     = min;
    document.getElementById('levelsMax').value     = max;
    document.getElementById('levelsItemName').textContent = name;
    document.getElementById('levelsModal').classList.remove('hidden');
}
function closeLevels() {
    document.getElementById('levelsModal').classList.add('hidden');
}
document.getElementById('levelsModal').addEventListener('click', function(e) {
    if (e.target === this) closeLevels();
});
function buildShopCheckboxes(containerId, shops, accentColor) {
    var list = document.getElementById(containerId);
    list.innerHTML = '';
    if (shops.length === 0) {
        list.innerHTML = '<p class="text-xs text-gray-400 text-center py-2">No shops available.</p>';
        return;
    }
    shops.forEach(function(s) {
        var label = document.createElement('label');
        label.className = 'flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-white cursor-pointer transition border border-transparent hover:border-gray-200';
        label.innerHTML =
            '<input type="checkbox" name="shop_ids[]" value="' + s.shop_id + '" class="w-4 h-4 rounded" style="accent-color:' + accentColor + '">' +
            '<span class="text-sm font-medium text-gray-700">' + s.shop_name + '</span>' +
            '<span class="text-xs text-gray-400 ml-auto">' + s.city + '</span>';
        list.appendChild(label);
    });
}
function switchTab(tab) {
    var isEnroll = tab === 'enroll';
    document.getElementById('panelEnroll').classList.toggle('hidden', !isEnroll);
    document.getElementById('panelUnenroll').classList.toggle('hidden', isEnroll);
    document.getElementById('tabEnroll').className  = 'px-4 py-2 text-sm font-semibold border-b-2 -mb-px transition ' + (isEnroll  ? 'border-purple-600 text-purple-700' : 'border-transparent text-gray-500 hover:text-gray-700');
    document.getElementById('tabUnenroll').className = 'px-4 py-2 text-sm font-semibold border-b-2 -mb-px transition ' + (!isEnroll ? 'border-orange-500 text-orange-600' : 'border-transparent text-gray-500 hover:text-gray-700');
}
function openManageShops(id, name, availableShops, enrolledShops) {
    document.getElementById('enrollItemId').value   = id;
    document.getElementById('unenrollItemId').value = id;
    document.getElementById('manageShopsItemName').textContent = name;
    buildShopCheckboxes('enrollShopList',   availableShops, '#7c3aed');
    buildShopCheckboxes('unenrollShopList', enrolledShops,  '#ea580c');
    switchTab('enroll');
    document.getElementById('manageShopsModal').classList.remove('hidden');
}
function closeManageShops() {
    document.getElementById('manageShopsModal').classList.add('hidden');
}
function validateEnroll() {
    if (!document.querySelectorAll('#enrollShopList input:checked').length) {
        alert('Please select at least one shop to enroll in.'); return false;
    }
    return true;
}
function validateUnenroll() {
    if (!document.querySelectorAll('#unenrollShopList input:checked').length) {
        alert('Please select at least one shop to remove from.'); return false;
    }
    return true;
}
document.getElementById('manageShopsModal').addEventListener('click', function(e) {
    if (e.target === this) closeManageShops();
});
</script>
</body>
</html>