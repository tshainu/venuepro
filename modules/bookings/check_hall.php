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

if (!$hall_id || !$date) { echo json_encode(['conflict'=>false]); exit; }

$sql = "SELECT id, booking_ref FROM bookings WHERE hall_id=? AND status NOT IN ('cancelled') AND event_date=?";
$params = [$hall_id, $date];
if ($exclude_id) { $sql .= " AND id != ?"; $params[] = $exclude_id; }

$conflict = $db->fetchOne($sql, $params);
if ($conflict) {
    echo json_encode(['conflict'=>true, 'ref'=>$conflict['booking_ref'], 'id'=>$conflict['id']]);
} else {
    echo json_encode(['conflict'=>false]);
}
