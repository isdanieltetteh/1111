<?php

require_once __DIR__ . '/functions.php';

/**
 * Ensure support ticket tables contain the columns required for
 * the conversational workflow. This runs lazily and will attempt
 * to migrate missing columns when required.
 */
function ensure_support_schema(PDO $db): void
{
    static $checked = false;
    if ($checked) {
        return;
    }

    try {
        $columns = $db->query("SHOW COLUMNS FROM support_replies LIKE 'sender_type'");
        if ($columns && !$columns->fetch(PDO::FETCH_ASSOC)) {
            $db->exec("ALTER TABLE support_replies ADD COLUMN sender_type ENUM('admin','user','system') NOT NULL DEFAULT 'admin' AFTER admin_id");
        }

        $columns = $db->query("SHOW COLUMNS FROM support_replies LIKE 'user_id'");
        if ($columns && !$columns->fetch(PDO::FETCH_ASSOC)) {
            $db->exec("ALTER TABLE support_replies ADD COLUMN user_id INT NULL AFTER admin_id");
        }

        $db->exec("ALTER TABLE support_replies MODIFY admin_id INT NULL");
        $db->exec("UPDATE support_replies SET sender_type = 'admin' WHERE sender_type IS NULL");

        $statusColumn = $db->query("SHOW COLUMNS FROM support_tickets LIKE 'status'")->fetch(PDO::FETCH_ASSOC);
        if ($statusColumn && strpos($statusColumn['Type'], 'awaiting_user') === false) {
            $db->exec("ALTER TABLE support_tickets MODIFY status ENUM('open','replied','closed') DEFAULT 'open'");
        }
    } catch (Exception $e) {
        error_log('[SupportSchema] ' . $e->getMessage());
    }

    $checked = true;
}

