<?php
require_once __DIR__ . '/../../core/bootstrap.php';

header('Content-Type: application/json');

if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['conflict'=>false,'error'=>'Session expired.']);
    exit;
}

$db = Database::getInstance();

$hall_id    = (int)($_GET['hall_id'] ?? 0);
$date       = trim($_GET['date'] ?? '');
$exclude_id = (int)($_GET['exclude'] ?? 0);
$start_time = trim($_GET['start_time'] ?? '');
$end_time   = trim($_GET['end_time'] ?? '');

if (!$hall_id || !$date) { echo json_encode(['conflict'=>false]); exit; }

// If both times provided, use time-overlap logic: (new_start < existing_end AND new_end > existing_start)
// Overlap condition: existing_start < new_end AND existing_end > new_start
if ($start_time && $end_time) {
    $sql = "SELECT id, booking_ref, event_time, event_end_time
            FROM bookings
            WHERE hall_id=?
              AND status NOT IN ('cancelled')
              AND event_date=?
              AND event_time IS NOT NULL
              AND event_end_time IS NOT NULL
              AND event_time < ?
              AND event_end_time > ?";
    $params = [$hall_id, $date, $end_time, $start_time];
    if ($exclude_id) { $sql .= " AND id != ?"; $params[] = $exclude_id; }
    $conflict = $db->fetchOne($sql, $params);

    // Also check bookings with no times on same date (full-day blockers)
    if (!$conflict) {
        $sql2 = "SELECT id, booking_ref FROM bookings
                 WHERE hall_id=? AND status NOT IN ('cancelled') AND event_date=?
                   AND (event_time IS NULL OR event_end_time IS NULL)";
        $p2 = [$hall_id, $date];
        if ($exclude_id) { $sql2 .= " AND id != ?"; $p2[] = $exclude_id; }
        $conflict = $db->fetchOne($sql2, $p2);
    }
} else {
    // No times given — check same date (any booking is a conflict)
    $sql = "SELECT id, booking_ref FROM bookings WHERE hall_id=? AND status NOT IN ('cancelled') AND event_date=?";
    $params = [$hall_id, $date];
    if ($exclude_id) { $sql .= " AND id != ?"; $params[] = $exclude_id; }
    $conflict = $db->fetchOne($sql, $params);
}

if ($conflict) {
    echo json_encode(['conflict'=>true, 'ref'=>$conflict['booking_ref'], 'id'=>$conflict['id']]);
} else {
    echo json_encode(['conflict'=>false]);
}
