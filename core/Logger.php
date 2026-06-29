<?php
/**
 * Logger — activity log helper
 * Usage: Logger::log('create'|'edit'|'delete', 'bookings', $id, 'BK-0001', $old, $new, 'description')
 */
class Logger {

    /**
     * @param string      $action   'create'|'edit'|'delete'
     * @param string      $module   e.g. 'bookings','users','invoices'
     * @param int|null    $recordId primary key of the affected record
     * @param string|null $ref      human-readable reference (booking_ref, invoice_ref, name…)
     * @param mixed       $old      old data (array/string); null for creates
     * @param mixed       $new      new data (array/string); null for deletes
     * @param string|null $desc     short description override
     */
    public static function log(
        string $action,
        string $module,
        ?int   $recordId  = null,
        ?string $ref       = null,
        $old               = null,
        $new               = null,
        ?string $desc      = null
    ): void {
        try {
            $db      = Database::getInstance();
            $user_id = $_SESSION['user_id']   ?? 0;
            $uname   = $_SESSION['user_name'] ?? 'System';
            $branch  = $_SESSION['branch_id'] ?? null;

            $oldJson = $old !== null ? json_encode($old, JSON_UNESCAPED_UNICODE) : null;
            $newJson = $new !== null ? json_encode($new, JSON_UNESCAPED_UNICODE) : null;

            // Build default description if none supplied
            if ($desc === null) {
                $refPart = $ref ? " [{$ref}]" : ($recordId ? " [#{$recordId}]" : '');
                $desc = ucfirst($action) . "d " . $module . $refPart;
            }

            $db->execute(
                "INSERT INTO activity_logs
                 (action, module, record_id, record_ref, description, old_data, new_data, user_id, user_name, branch_id, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [$action, $module, $recordId, $ref, $desc, $oldJson, $newJson, $user_id, $uname, $branch]
            );
        } catch (Exception $e) {
            // Logging must never break the main flow
            error_log('Logger::log error: ' . $e->getMessage());
        }
    }
}
