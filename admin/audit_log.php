<?php
session_start();
require_once '../db_connect.php';
require_once '../functions.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'Admin') {
    header("Location: ../login.php"); exit();
}

// ── Check table exists; create it inline if missing ──────────────────────────
$table_check = $conn->query("SHOW TABLES LIKE 'Audit_Log'");
if (!$table_check || $table_check->num_rows === 0) {
    $conn->query("CREATE TABLE IF NOT EXISTS `Audit_Log` (
      `log_id`          INT AUTO_INCREMENT PRIMARY KEY,
      `action_type`     VARCHAR(60)  NOT NULL,
      `entity_type`     VARCHAR(40)  NOT NULL,
      `entity_id`       VARCHAR(60)  DEFAULT NULL,
      `performed_by`    INT          DEFAULT NULL,
      `performer_name`  VARCHAR(120) DEFAULT NULL,
      `performer_role`  VARCHAR(40)  DEFAULT NULL,
      `facility_id`     INT          DEFAULT NULL,
      `description`     VARCHAR(500) NOT NULL,
      `old_value`       TEXT         DEFAULT NULL,
      `new_value`       TEXT         DEFAULT NULL,
      `ip_address`      VARCHAR(45)  DEFAULT NULL,
      `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
      KEY `idx_facility`  (`facility_id`),
      KEY `idx_performer` (`performed_by`),
      KEY `idx_action`    (`action_type`),
      KEY `idx_entity`    (`entity_type`, `entity_id`),
      KEY `idx_created`   (`created_at`),
      CONSTRAINT `fk_audit_user`
        FOREIGN KEY (`performed_by`) REFERENCES `Users`(`user_id`) ON DELETE SET NULL,
      CONSTRAINT `fk_audit_facility`
        FOREIGN KEY (`facility_id`) REFERENCES `Facility`(`facility_id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

// ── Filters ──────────────────────────────────────────────────────────────────
$filter_facility  = isset($_GET['facility'])    ? (int)$_GET['facility']          : 0;
$filter_action    = isset($_GET['action_type']) ? trim($_GET['action_type'])       : '';
$filter_entity    = isset($_GET['entity_type']) ? trim($_GET['entity_type'])       : '';
$filter_user      = isset($_GET['performer'])   ? trim($_GET['performer'])         : '';
$filter_date_from = isset($_GET['date_from'])   ? trim($_GET['date_from'])         : '';
$filter_date_to   = isset($_GET['date_to'])     ? trim($_GET['date_to'])           : '';
$per_page = 50;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

// ── Load filter options ───────────────────────────────────────────────────────
$facilities = [];
$res = $conn->query("SELECT facility_id, CONCAT(city,', ',state,' — ',type) AS label FROM Facility ORDER BY state,city");
while ($r = $res->fetch_assoc()) $facilities[] = $r;

$action_types = [];
$res2 = $conn->query("SELECT DISTINCT action_type FROM Audit_Log ORDER BY action_type");
if ($res2) while ($r = $res2->fetch_assoc()) $action_types[] = $r['action_type'];

$entity_types = [];
$res3 = $conn->query("SELECT DISTINCT entity_type FROM Audit_Log ORDER BY entity_type");
if ($res3) while ($r = $res3->fetch_assoc()) $entity_types[] = $r['entity_type'];

// ── Build query ───────────────────────────────────────────────────────────────
$where   = [];
$params  = [];
$types   = '';

if ($filter_facility)  { $where[] = "a.facility_id = ?";                    $params[] = $filter_facility;  $types .= 'i'; }
if ($filter_action)    { $where[] = "a.action_type = ?";                    $params[] = $filter_action;    $types .= 's'; }
if ($filter_entity)    { $where[] = "a.entity_type = ?";                    $params[] = $filter_entity;    $types .= 's'; }
if ($filter_user)      {
    $where[] = "(a.performer_name LIKE ? OR CONCAT_WS(' ', e.first_name, e.last_name) LIKE ? OR CONCAT_WS(' ', c.first_name, c.last_name) LIKE ?)";
    $params[] = "%$filter_user%";
    $params[] = "%$filter_user%";
    $params[] = "%$filter_user%";
    $types .= 'sss';
}
if ($filter_date_from) { $where[] = "DATE(a.created_at) >= ?";              $params[] = $filter_date_from; $types .= 's'; }
if ($filter_date_to)   { $where[] = "DATE(a.created_at) <= ?";              $params[] = $filter_date_to;   $types .= 's'; }

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Total count (needs same JOINs as main query so performer name filter works)
$count_sql = "SELECT COUNT(*) FROM Audit_Log a
              LEFT JOIN Employee e ON a.performed_by = e.user_id AND a.performed_by > 0
              LEFT JOIN Customer c ON a.performed_by = c.user_id AND a.performed_by > 0
              $where_sql";
$cs = $conn->prepare($count_sql);
if ($params) $cs->bind_param($types, ...$params);
$cs->execute();
$total_rows = $cs->get_result()->fetch_row()[0];
$total_pages = max(1, ceil($total_rows / $per_page));

// Paginated results
$sql = "SELECT a.log_id, a.action_type, a.entity_type, a.entity_id,
               COALESCE(
                   CASE WHEN TRIM(a.performer_name) NOT IN ('','0') THEN TRIM(a.performer_name) ELSE NULL END,
                   NULLIF(TRIM(CONCAT_WS(' ', e.first_name, e.last_name)),''),
                   NULLIF(TRIM(CONCAT_WS(' ', c.first_name, c.last_name)),''),
                   '—') AS performer_name,
               COALESCE(
                   CASE WHEN TRIM(a.performer_role) NOT IN ('','0') THEN TRIM(a.performer_role) ELSE NULL END,
                   e.role,
                   '—') AS performer_role,
               a.performed_by, a.description,
               a.old_value, a.new_value, a.ip_address, a.created_at,
               CONCAT(f.city,', ',f.state) AS facility_name
        FROM Audit_Log a
        LEFT JOIN Facility  f ON a.facility_id  = f.facility_id
        LEFT JOIN Employee  e ON a.performed_by = e.user_id AND a.performed_by > 0
        LEFT JOIN Customer  c ON a.performed_by = c.user_id AND a.performed_by > 0
        $where_sql
        ORDER BY a.created_at DESC
        LIMIT ? OFFSET ?";
$all_params  = array_merge($params, [$per_page, $offset]);
$all_types   = $types . 'ii';
$stmt = $conn->prepare($sql);
$stmt->bind_param($all_types, ...$all_params);
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ── Export CSV ────────────────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="audit_log_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Log ID','Timestamp','Action','Entity','Entity ID','Performer','Role','Facility','Description','Old Value','New Value','IP']);
    foreach ($logs as $l) {
        fputcsv($out, [$l['log_id'], $l['created_at'], $l['action_type'], $l['entity_type'],
            $l['entity_id'], $l['performer_name'], $l['performer_role'], $l['facility_name'],
            $l['description'], $l['old_value'], $l['new_value'], $l['ip_address']]);
    }
    fclose($out); exit();
}

// ── Action type → color ───────────────────────────────────────────────────────
function actionBadge($type) {
    $map = [
        'PACKAGE_CREATED'      => 'bg-blue-100 text-blue-800',
        'PACKAGE_SCANNED'      => 'bg-indigo-100 text-indigo-800',
        'PACKAGE_DELIVERED'    => 'bg-green-100 text-green-800',
        'PACKAGE_CANCELLED'    => 'bg-red-100 text-red-800',
        'USER_REGISTERED'      => 'bg-teal-100 text-teal-800',
        'USER_STATUS_CHANGED'  => 'bg-yellow-100 text-yellow-800',
        'USER_UPDATED'         => 'bg-yellow-100 text-yellow-800',
        'USER_CREATED'         => 'bg-teal-100 text-teal-800',
        'TRIP_CREATED'         => 'bg-purple-100 text-purple-800',
        'TRIP_UPDATED'         => 'bg-purple-100 text-purple-800',
        'INVENTORY_UPDATED'    => 'bg-orange-100 text-orange-800',
        'SALE_COMPLETED'       => 'bg-green-100 text-green-800',
        'TICKET_CREATED'       => 'bg-pink-100 text-pink-800',
        'TICKET_STATUS_CHANGED'=> 'bg-pink-100 text-pink-800',
    ];
    $cls = $map[$type] ?? 'bg-gray-100 text-gray-700';
    $label = str_replace('_', ' ', $type);
    return "<span class=\"text-xs font-semibold px-2 py-0.5 rounded-full {$cls}\">{$label}</span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Log — Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .detail-pre { white-space: pre-wrap; word-break: break-all; font-size: 0.7rem; max-height: 120px; overflow-y: auto; }
        tr.expanded td { background: #f8fafc; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">

<!-- NAV -->
<?php include '_nav.php'; ?>

<div class="max-w-screen-xl mx-auto px-4 py-8">

    <!-- HEADER -->
    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-black text-gray-900">System Audit Log</h1>
            <p class="text-gray-500 text-sm mt-1">Full activity history across all facilities &mdash; <?= number_format($total_rows) ?> records found</p>
        </div>
        <div class="flex gap-2">
            <a href="?<?= http_build_query(array_merge($_GET, ['export'=>'csv'])) ?>"
               class="bg-green-600 hover:bg-green-700 text-white text-sm font-semibold px-4 py-2 rounded flex items-center gap-2 transition">
                <i class="fas fa-file-csv"></i> Export CSV
            </a>
            <a href="audit_log.php"
               class="bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm font-semibold px-4 py-2 rounded flex items-center gap-2 transition">
                <i class="fas fa-times"></i> Clear Filters
            </a>
        </div>
    </div>

    <!-- FILTERS -->
    <form method="get" class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
            <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wide">Facility</label>
                <select name="facility" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
                    <option value="">All Facilities</option>
                    <?php foreach ($facilities as $f): ?>
                    <option value="<?= $f['facility_id'] ?>" <?= $filter_facility == $f['facility_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($f['label']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wide">Action Type</label>
                <select name="action_type" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
                    <option value="">All Actions</option>
                    <?php foreach ($action_types as $at): ?>
                    <option value="<?= htmlspecialchars($at) ?>" <?= $filter_action === $at ? 'selected' : '' ?>>
                        <?= htmlspecialchars(str_replace('_', ' ', $at)) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wide">Entity Type</label>
                <select name="entity_type" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
                    <option value="">All Entities</option>
                    <?php foreach ($entity_types as $et): ?>
                    <option value="<?= htmlspecialchars($et) ?>" <?= $filter_entity === $et ? 'selected' : '' ?>>
                        <?= htmlspecialchars($et) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wide">Performer</label>
                <input type="text" name="performer" value="<?= htmlspecialchars($filter_user) ?>"
                    placeholder="Name search..."
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wide">From Date</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($filter_date_from) ?>"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wide">To Date</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($filter_date_to) ?>"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
            </div>
        </div>
        <div class="mt-3 flex justify-end">
            <button type="submit" class="bg-blue-700 hover:bg-blue-800 text-white text-sm font-semibold px-5 py-2 rounded-lg transition">
                <i class="fas fa-filter mr-1"></i> Apply Filters
            </button>
        </div>
    </form>

    <!-- TABLE -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <?php if (empty($logs)): ?>
        <div class="text-center py-16 text-gray-400">
            <i class="fas fa-clipboard-list text-4xl mb-3 block"></i>
            <p class="font-semibold">No audit records found.</p>
            <p class="text-sm mt-1">Adjust your filters or check back after activity has been logged.</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-100 text-xs text-gray-500 uppercase tracking-wide">
                    <th class="text-left px-4 py-3 w-6"></th>
                    <th class="text-left px-4 py-3">Timestamp</th>
                    <th class="text-left px-4 py-3">Action</th>
                    <th class="text-left px-4 py-3">Entity</th>
                    <th class="text-left px-4 py-3">Performed By</th>
                    <th class="text-left px-4 py-3">Facility</th>
                    <th class="text-left px-4 py-3">Description</th>
                    <th class="text-left px-4 py-3 w-8">Detail</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
            <?php foreach ($logs as $i => $l): ?>
                <?php $row_id = 'row-' . $l['log_id']; ?>
                <tr class="hover:bg-gray-50 transition" id="<?= $row_id ?>">
                    <td class="px-4 py-3 text-gray-300 text-xs font-mono"><?= $l['log_id'] ?></td>
                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                        <div class="font-semibold text-gray-800"><?= date('M j, Y', strtotime($l['created_at'])) ?></div>
                        <div class="text-xs text-gray-400"><?= date('g:i:s A', strtotime($l['created_at'])) ?></div>
                    </td>
                    <td class="px-4 py-3"><?= actionBadge($l['action_type']) ?></td>
                    <td class="px-4 py-3">
                        <span class="font-semibold text-gray-700"><?= htmlspecialchars($l['entity_type']) ?></span>
                        <?php if ($l['entity_id']): ?>
                        <div class="text-xs text-gray-400 font-mono"><?= htmlspecialchars($l['entity_id']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3">
                        <div class="font-semibold text-gray-800"><?= htmlspecialchars($l['performer_name'] ?: '—') ?></div>
                        <div class="text-xs text-gray-400"><?= htmlspecialchars($l['performer_role'] ?: '') ?></div>
                    </td>
                    <td class="px-4 py-3 text-gray-600 text-xs"><?= htmlspecialchars($l['facility_name'] ?? 'System') ?></td>
                    <td class="px-4 py-3 text-gray-600 max-w-xs">
                        <span title="<?= htmlspecialchars($l['description']) ?>">
                            <?= htmlspecialchars(mb_strimwidth($l['description'], 0, 80, '…')) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <?php if ($l['old_value'] || $l['new_value']): ?>
                        <button onclick="toggleDetail('<?= $row_id ?>-detail')"
                            class="text-blue-500 hover:text-blue-700 transition text-xs">
                            <i class="fas fa-chevron-down" id="<?= $row_id ?>-icon"></i>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if ($l['old_value'] || $l['new_value']): ?>
                <tr id="<?= $row_id ?>-detail" class="hidden bg-gray-50 border-b border-gray-100">
                    <td colspan="8" class="px-6 py-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php if ($l['old_value']): ?>
                            <div>
                                <p class="text-xs font-bold text-red-500 uppercase mb-1 flex items-center gap-1">
                                    <i class="fas fa-minus-circle"></i> Before
                                </p>
                                <pre class="detail-pre bg-red-50 border border-red-100 rounded-lg p-3 text-red-900"><?= htmlspecialchars(json_encode(json_decode($l['old_value']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                            </div>
                            <?php endif; ?>
                            <?php if ($l['new_value']): ?>
                            <div>
                                <p class="text-xs font-bold text-green-600 uppercase mb-1 flex items-center gap-1">
                                    <i class="fas fa-plus-circle"></i> After
                                </p>
                                <pre class="detail-pre bg-green-50 border border-green-100 rounded-lg p-3 text-green-900"><?= htmlspecialchars(json_encode(json_decode($l['new_value']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($l['ip_address']): ?>
                        <p class="text-xs text-gray-400 mt-2"><i class="fas fa-globe mr-1"></i>IP: <?= htmlspecialchars($l['ip_address']) ?></p>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <!-- PAGINATION -->
        <div class="px-4 py-4 border-t border-gray-100 flex flex-wrap items-center justify-between gap-3">
            <span class="text-sm text-gray-500">
                Showing <?= number_format($offset+1) ?>–<?= number_format(min($offset+$per_page, $total_rows)) ?> of <?= number_format($total_rows) ?> records
            </span>
            <div class="flex gap-1">
                <?php
                $base_q = array_diff_key($_GET, ['page'=>'']);
                for ($p = max(1, $page-2); $p <= min($total_pages, $page+4); $p++):
                    $q = http_build_query(array_merge($base_q, ['page'=>$p]));
                ?>
                <a href="?<?= $q ?>"
                   class="px-3 py-1 rounded text-sm font-semibold transition
                          <?= $p === $page ? 'bg-blue-700 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                    <?= $p ?>
                </a>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

</div>

<script>
function toggleDetail(id) {
    const row = document.getElementById(id);
    const icon = document.getElementById(id.replace('-detail', '-icon'));
    row.classList.toggle('hidden');
    if (icon) icon.classList.toggle('fa-chevron-up'), icon.classList.toggle('fa-chevron-down');
}
</script>
</body>
</html>
