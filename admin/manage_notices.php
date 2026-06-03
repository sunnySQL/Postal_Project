<?php
session_start();
require_once '../db_connect.php';
require_once '../functions.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'Admin') {
    header("Location: ../login.php"); exit();
}

// ── Auto-create table if missing ─────────────────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS `Service_Notices` (
    `notice_id`  INT AUTO_INCREMENT PRIMARY KEY,
    `message`    TEXT NOT NULL,
    `type`       ENUM('info','warning','danger','success') DEFAULT 'info',
    `link_url`   VARCHAR(255) DEFAULT NULL,
    `link_text`  VARCHAR(100) DEFAULT NULL,
    `is_active`  TINYINT(1) DEFAULT 0,
    `expires_at` DATE DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `created_by` INT DEFAULT NULL
)");

$success = $error = '';

// ── Handle POST actions ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'edit') {
        $message   = trim($_POST['message'] ?? '');
        $type      = $_POST['type'] ?? 'info';
        $link_url  = trim($_POST['link_url'] ?? '') ?: null;
        $link_text = trim($_POST['link_text'] ?? '') ?: null;
        $expires   = trim($_POST['expires_at'] ?? '') ?: null;
        $active    = isset($_POST['is_active']) ? 1 : 0;

        if (!$message) {
            $error = 'Message is required.';
        } else {
            if ($action === 'create') {
                $stmt = $conn->prepare("INSERT INTO Service_Notices (message, type, link_url, link_text, is_active, expires_at, created_by) VALUES (?,?,?,?,?,?,?)");
                $stmt->bind_param('ssssiis', $message, $type, $link_url, $link_text, $active, $expires, $_SESSION['user_id']);
                $stmt->execute();
                $success = 'Notice created successfully.';
            } else {
                $id = (int)$_POST['notice_id'];
                $stmt = $conn->prepare("UPDATE Service_Notices SET message=?, type=?, link_url=?, link_text=?, is_active=?, expires_at=? WHERE notice_id=?");
                $stmt->bind_param('ssssiis', $message, $type, $link_url, $link_text, $active, $expires, $id);
                $stmt->execute();
                $success = 'Notice updated.';
            }
        }
    }

    if ($action === 'toggle') {
        $id  = (int)$_POST['notice_id'];
        $val = (int)$_POST['current_active'] === 1 ? 0 : 1;
        // If activating, deactivate all others first (only one active at a time)
        if ($val === 1) $conn->query("UPDATE Service_Notices SET is_active = 0");
        $conn->prepare("UPDATE Service_Notices SET is_active=? WHERE notice_id=?")->execute([$val, $id]);
        $success = $val ? 'Notice is now live.' : 'Notice deactivated.';
    }

    if ($action === 'delete') {
        $id = (int)$_POST['notice_id'];
        $conn->prepare("DELETE FROM Service_Notices WHERE notice_id=?")->execute([$id]);
        $success = 'Notice deleted.';
    }
}

// ── Fetch edit target ─────────────────────────────────────────────────────────
$editing = null;
if (isset($_GET['edit'])) {
    $r = $conn->prepare("SELECT * FROM Service_Notices WHERE notice_id=?");
    $r->bind_param('i', $_GET['edit']);
    $r->execute();
    $editing = $r->get_result()->fetch_assoc();
}

// ── Fetch all notices ─────────────────────────────────────────────────────────
$notices = $conn->query("SELECT * FROM Service_Notices ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);

// ── Type config ───────────────────────────────────────────────────────────────
$typeConfig = [
    'info'    => ['label' => 'Info',    'bg' => 'bg-amber-100',  'text' => 'text-amber-800',  'dot' => 'bg-amber-400'],
    'warning' => ['label' => 'Warning', 'bg' => 'bg-orange-100', 'text' => 'text-orange-800', 'dot' => 'bg-orange-400'],
    'danger'  => ['label' => 'Danger',  'bg' => 'bg-red-100',    'text' => 'text-red-800',    'dot' => 'bg-red-400'],
    'success' => ['label' => 'Success', 'bg' => 'bg-green-100',  'text' => 'text-green-800',  'dot' => 'bg-green-400'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Notices — Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        #toastBanner {
            position: fixed;
            top: 80px;
            right: 24px;
            z-index: 9999;
            max-width: 420px;
            opacity: 1;
            transition: opacity 0.5s ease;
        }
        #toastBanner.toast-fade { opacity: 0; pointer-events: none; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">

<?php include '_nav.php'; ?>

<?php if ($success || $error): ?>
<div id="toastBanner" class="shadow-lg rounded-lg text-sm flex items-center gap-2 p-4 <?= $error ? 'bg-red-50 border-l-4 border-red-500 text-red-700' : 'bg-green-50 border-l-4 border-green-500 text-green-700' ?>">
    <?php if ($success): ?>
        <i class="fas fa-check-circle flex-shrink-0"></i>
        <span><?= htmlspecialchars($success) ?></span>
    <?php else: ?>
        <i class="fas fa-exclamation-circle flex-shrink-0"></i>
        <span><?= htmlspecialchars($error) ?></span>
    <?php endif; ?>
</div>
<script>
(function(){
    var el = document.getElementById('toastBanner');
    if (!el) return;
    setTimeout(function(){
        el.classList.add('toast-fade');
        setTimeout(function(){ el.style.display = 'none'; }, 500);
    }, 3000);
})();
</script>
<?php endif; ?>

<div class="max-w-5xl mx-auto px-4 py-8">

    <!-- Breadcrumb -->
    <div class="flex items-center gap-2 text-sm text-gray-400 mb-6">
        <a href="../employee_dashboard.php" class="hover:text-[#004B87] transition">Dashboard</a>
        <i class="fas fa-chevron-right text-xs"></i>
        <span class="text-gray-600 font-medium">Service Notices</span>
    </div>

    <div class="flex flex-col lg:flex-row gap-6">

        <!-- ── FORM (create / edit) ── -->
        <div class="lg:w-80 flex-shrink-0">
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden sticky top-[72px]">
                <div class="bg-gray-50 border-b border-gray-200 px-5 py-3 flex items-center gap-2">
                    <i class="fas fa-<?= $editing ? 'pen' : 'plus' ?> text-[#004B87] text-xs"></i>
                    <h2 class="text-xs font-bold text-gray-600 uppercase tracking-wider">
                        <?= $editing ? 'Edit Notice' : 'New Notice' ?>
                    </h2>
                </div>
                <form method="post" class="p-5 space-y-4">
                    <input type="hidden" name="action" value="<?= $editing ? 'edit' : 'create' ?>">
                    <?php if ($editing): ?>
                    <input type="hidden" name="notice_id" value="<?= $editing['notice_id'] ?>">
                    <?php endif; ?>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">Message <span class="text-red-400">*</span></label>
                        <textarea name="message" rows="3" required placeholder="e.g. Holiday shipping deadlines approaching..."
                            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004B87] focus:border-transparent resize-none"><?= htmlspecialchars($editing['message'] ?? '') ?></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">Type</label>
                        <select name="type" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004B87] focus:border-transparent bg-white">
                            <?php foreach ($typeConfig as $val => $cfg): ?>
                            <option value="<?= $val ?>" <?= ($editing['type'] ?? 'info') === $val ? 'selected' : '' ?>><?= $cfg['label'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">Link URL <span class="text-gray-400 font-normal">(optional)</span></label>
                        <input type="url" name="link_url" placeholder="https://..." value="<?= htmlspecialchars($editing['link_url'] ?? '') ?>"
                            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004B87] focus:border-transparent">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">Link Text <span class="text-gray-400 font-normal">(optional)</span></label>
                        <input type="text" name="link_text" placeholder="e.g. View updates →" value="<?= htmlspecialchars($editing['link_text'] ?? '') ?>"
                            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004B87] focus:border-transparent">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">Expires On <span class="text-gray-400 font-normal">(optional)</span></label>
                        <input type="date" name="expires_at" value="<?= htmlspecialchars($editing['expires_at'] ?? '') ?>"
                            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004B87] focus:border-transparent">
                    </div>

                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="is_active" class="w-4 h-4 rounded border-gray-300 text-[#004B87] focus:ring-[#004B87]"
                            <?= !empty($editing['is_active']) ? 'checked' : '' ?>>
                        <div>
                            <p class="text-sm font-semibold text-gray-700">Set as Live</p>
                            <p class="text-xs text-gray-400">Only one notice is shown at a time.</p>
                        </div>
                    </label>

                    <div class="flex gap-2 pt-1">
                        <button type="submit"
                            class="flex-1 bg-[#004B87] hover:bg-blue-900 text-white py-2.5 rounded-lg text-sm font-bold transition">
                            <i class="fas fa-save mr-1"></i> <?= $editing ? 'Update' : 'Create' ?>
                        </button>
                        <?php if ($editing): ?>
                        <a href="manage_notices.php"
                            class="px-4 py-2.5 rounded-lg border border-gray-200 text-sm font-semibold text-gray-500 hover:bg-gray-50 transition">
                            Cancel
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- ── NOTICES LIST ── -->
        <div class="flex-1 space-y-3">
            <div class="flex items-center justify-between mb-1">
                <h1 class="text-lg font-bold text-gray-800">All Notices</h1>
                <span class="text-sm text-gray-400"><?= count($notices) ?> total</span>
            </div>

            <?php if (empty($notices)): ?>
            <div class="bg-white rounded-xl border border-gray-200 p-10 text-center text-gray-400">
                <i class="fas fa-bell-slash text-3xl mb-3 block"></i>
                <p class="text-sm">No notices yet. Create one using the form.</p>
            </div>
            <?php endif; ?>

            <?php foreach ($notices as $n):
                $cfg = $typeConfig[$n['type']] ?? $typeConfig['info'];
                $expired = $n['expires_at'] && $n['expires_at'] < date('Y-m-d');
            ?>
            <div class="bg-white rounded-xl border <?= $n['is_active'] ? 'border-[#004B87]' : 'border-gray-200' ?> overflow-hidden">
                <!-- Status bar -->
                <div class="px-5 py-3 flex items-center justify-between border-b border-gray-100">
                    <div class="flex items-center gap-3">
                        <span class="<?= $cfg['bg'] ?> <?= $cfg['text'] ?> text-xs font-bold px-2.5 py-1 rounded-full flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full <?= $cfg['dot'] ?>"></span>
                            <?= $cfg['label'] ?>
                        </span>
                        <?php if ($n['is_active'] && !$expired): ?>
                        <span class="bg-green-100 text-green-700 text-xs font-bold px-2.5 py-1 rounded-full flex items-center gap-1">
                            <i class="fas fa-circle text-[6px]"></i> LIVE
                        </span>
                        <?php elseif ($expired): ?>
                        <span class="bg-gray-100 text-gray-500 text-xs font-semibold px-2.5 py-1 rounded-full">Expired</span>
                        <?php endif; ?>
                    </div>
                    <div class="flex items-center gap-2">
                        <!-- Toggle -->
                        <form method="post" class="inline">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="notice_id" value="<?= $n['notice_id'] ?>">
                            <input type="hidden" name="current_active" value="<?= $n['is_active'] ?>">
                            <button type="submit" title="<?= $n['is_active'] ? 'Deactivate' : 'Set Live' ?>"
                                class="<?= $n['is_active'] ? 'text-green-600 hover:text-gray-400' : 'text-gray-300 hover:text-green-600' ?> transition text-lg leading-none">
                                <i class="fas fa-toggle-<?= $n['is_active'] ? 'on' : 'off' ?>"></i>
                            </button>
                        </form>
                        <!-- Edit -->
                        <a href="?edit=<?= $n['notice_id'] ?>" class="text-gray-400 hover:text-[#004B87] transition text-sm">
                            <i class="fas fa-pen"></i>
                        </a>
                        <!-- Delete -->
                        <form method="post" class="inline" onsubmit="return confirm('Delete this notice?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="notice_id" value="<?= $n['notice_id'] ?>">
                            <button type="submit" class="text-gray-300 hover:text-red-500 transition text-sm">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
                <!-- Body -->
                <div class="px-5 py-4">
                    <p class="text-sm text-gray-700 leading-relaxed"><?= htmlspecialchars($n['message']) ?></p>
                    <div class="flex flex-wrap items-center gap-4 mt-3 text-xs text-gray-400">
                        <?php if ($n['link_url']): ?>
                        <span><i class="fas fa-link mr-1"></i>
                            <a href="<?= htmlspecialchars($n['link_url']) ?>" target="_blank" class="text-[#004B87] hover:underline">
                                <?= htmlspecialchars($n['link_text'] ?: $n['link_url']) ?>
                            </a>
                        </span>
                        <?php endif; ?>
                        <?php if ($n['expires_at']): ?>
                        <span><i class="fas fa-calendar-times mr-1"></i> Expires <?= date('M j, Y', strtotime($n['expires_at'])) ?></span>
                        <?php endif; ?>
                        <span><i class="fas fa-clock mr-1"></i> Created <?= date('M j, Y', strtotime($n['created_at'])) ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

    </div>
</div>
</body>
</html>
