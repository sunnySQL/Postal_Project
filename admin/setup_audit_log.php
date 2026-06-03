<?php
session_start();
require_once '../db_connect.php';
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'Admin') {
    die('Access denied.');
}

$sql = "CREATE TABLE IF NOT EXISTS `Audit_Log` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if ($conn->query($sql)) {
    echo '<p style="font-family:monospace;padding:20px;color:green;">
        ✔ Audit_Log table created (or already exists).<br><br>
        You can now <a href="audit_log.php">view the Audit Log</a> or
        <a href="../employee_dashboard.php">return to Dashboard</a>.<br><br>
        <strong>Delete this file after running it.</strong>
    </p>';
} else {
    echo '<p style="font-family:monospace;padding:20px;color:red;">Error: ' . htmlspecialchars($conn->error) . '</p>';
}
