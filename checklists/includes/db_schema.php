<?php
/**
 * Checklist & SOP Database Schema & Migration Manager
 * Follows FreeITSM core database conventions (DATETIME, snake_case, analyst_id references).
 */

if (!function_exists('ensureColumnExists')) {
    function ensureColumnExists(PDO $conn, string $table, string $column, string $definition): void {
        try {
            $stmt = $conn->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
            $stmt->execute([$column]);
            $col = $stmt->fetch();
            if (empty($col)) {
                $conn->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
            }
        } catch (Throwable $e) {
            // Ignore if column or table not ready
        }
    }
}

function ensureChecklistTablesExist(PDO $conn): void {
    static $alreadyRun = false;
    if ($alreadyRun) {
        return;
    }
    $alreadyRun = true;

    // 1. checklist_categories
    $conn->exec("CREATE TABLE IF NOT EXISTS checklist_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        created_datetime DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 2. checklist_roles
    $conn->exec("CREATE TABLE IF NOT EXISTS checklist_roles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        created_datetime DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 3. checklist_templates
    $conn->exec("CREATE TABLE IF NOT EXISTS checklist_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tenant_id INT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT NULL,
        keywords TEXT NULL,
        category VARCHAR(100) NULL DEFAULT 'General',
        suggested_role VARCHAR(100) NULL,
        scope ENUM('ticket','task','both') NOT NULL DEFAULT 'both',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_by_id INT NULL,
        created_datetime DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_datetime DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_tpl_category (category),
        INDEX idx_tpl_scope (scope)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 4. checklist_template_items
    $conn->exec("CREATE TABLE IF NOT EXISTS checklist_template_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        template_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT NULL,
        is_mandatory TINYINT(1) NOT NULL DEFAULT 1,
        suggested_role VARCHAR(100) NULL,
        sort_order INT NOT NULL DEFAULT 1,
        requires_input TINYINT(1) NOT NULL DEFAULT 0,
        input_placeholder VARCHAR(255) NULL,
        default_value VARCHAR(255) NULL,
        created_datetime DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_item_tpl (template_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 5. ticket_checklists
    $conn->exec("CREATE TABLE IF NOT EXISTS ticket_checklists (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ticket_id INT NOT NULL,
        template_id INT NULL,
        title VARCHAR(255) NOT NULL,
        created_by_id INT NULL,
        created_datetime DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_chk_ticket (ticket_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 6. ticket_checklist_items
    $conn->exec("CREATE TABLE IF NOT EXISTS ticket_checklist_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ticket_checklist_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        suggested_role VARCHAR(100) NULL,
        is_mandatory TINYINT(1) NOT NULL DEFAULT 1,
        is_completed TINYINT(1) NOT NULL DEFAULT 0,
        completed_by_id INT NULL,
        completed_by_name VARCHAR(150) NULL,
        completed_datetime DATETIME NULL,
        requires_input TINYINT(1) NOT NULL DEFAULT 0,
        input_placeholder VARCHAR(255) NULL,
        response_value TEXT NULL,
        sort_order INT NOT NULL DEFAULT 1,
        INDEX idx_item_ticket_chk (ticket_checklist_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Automated Column Builder / Upgrader for older versions:
    // checklist_templates
    ensureColumnExists($conn, 'checklist_templates', 'keywords', 'TEXT NULL AFTER description');
    ensureColumnExists($conn, 'checklist_templates', 'category', "VARCHAR(100) NULL DEFAULT 'General' AFTER keywords");
    ensureColumnExists($conn, 'checklist_templates', 'suggested_role', "VARCHAR(100) NULL AFTER category");
    ensureColumnExists($conn, 'checklist_templates', 'scope', "ENUM('ticket','task','both') NOT NULL DEFAULT 'both' AFTER suggested_role");

    // checklist_template_items
    ensureColumnExists($conn, 'checklist_template_items', 'is_mandatory', 'TINYINT(1) NOT NULL DEFAULT 1');
    ensureColumnExists($conn, 'checklist_template_items', 'suggested_role', 'VARCHAR(100) NULL');
    ensureColumnExists($conn, 'checklist_template_items', 'sort_order', 'INT NOT NULL DEFAULT 1');
    ensureColumnExists($conn, 'checklist_template_items', 'requires_input', 'TINYINT(1) NOT NULL DEFAULT 0');
    ensureColumnExists($conn, 'checklist_template_items', 'input_placeholder', 'VARCHAR(255) NULL');
    ensureColumnExists($conn, 'checklist_template_items', 'default_value', 'VARCHAR(255) NULL');

    // ticket_checklists
    ensureColumnExists($conn, 'ticket_checklists', 'template_id', 'INT NULL AFTER ticket_id');
    ensureColumnExists($conn, 'ticket_checklists', 'created_datetime', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');

    // ticket_checklist_items
    ensureColumnExists($conn, 'ticket_checklist_items', 'suggested_role', 'VARCHAR(100) NULL AFTER title');
    ensureColumnExists($conn, 'ticket_checklist_items', 'is_mandatory', 'TINYINT(1) NOT NULL DEFAULT 1');
    ensureColumnExists($conn, 'ticket_checklist_items', 'is_completed', 'TINYINT(1) NOT NULL DEFAULT 0');
    ensureColumnExists($conn, 'ticket_checklist_items', 'completed_by_id', 'INT NULL');
    ensureColumnExists($conn, 'ticket_checklist_items', 'completed_by_name', 'VARCHAR(150) NULL');
    ensureColumnExists($conn, 'ticket_checklist_items', 'completed_datetime', 'DATETIME NULL');
    ensureColumnExists($conn, 'ticket_checklist_items', 'requires_input', 'TINYINT(1) NOT NULL DEFAULT 0');
    ensureColumnExists($conn, 'ticket_checklist_items', 'input_placeholder', 'VARCHAR(255) NULL');
    ensureColumnExists($conn, 'ticket_checklist_items', 'response_value', 'TEXT NULL');
    ensureColumnExists($conn, 'ticket_checklist_items', 'sort_order', 'INT NOT NULL DEFAULT 1');

    // Seed default categories if table is empty
    $count = (int)$conn->query("SELECT COUNT(*) FROM checklist_categories")->fetchColumn();
    if ($count === 0) {
        $conn->exec("INSERT IGNORE INTO checklist_categories (name) VALUES
            ('Network'),
            ('HR & IT'),
            ('Infrastructure'),
            ('Security'),
            ('General')
        ");
    }

    // Seed default roles if table is empty
    $rCount = (int)$conn->query("SELECT COUNT(*) FROM checklist_roles")->fetchColumn();
    if ($rCount === 0) {
        $conn->exec("INSERT IGNORE INTO checklist_roles (name) VALUES
            ('Helpdesk L1'),
            ('Helpdesk L2'),
            ('Network Admin'),
            ('Systems Engineer'),
            ('Security Analyst'),
            ('Database Admin')
        ");
    }
}
