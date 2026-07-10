<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();

$db = Database::getInstance();

$hall_id   = (int)($_GET['hall_id'] ?? 0);
$status    = $_GET['status'] ?? '';
$branch_id = (int)($_GET['branch_id'] ?? 0);
$start     = $_GET['start'] ?? '';
$end       = $_GET['end'] ?? '';

$where  = ["1=1"];
$params = [];

if ($branch_id) { $where[] = "b.branch_id = ?"; $params[] = $branch_id; }
if ($hall_id)   { $where[] = "b.hall_id = ?";   $params[] = $hall_id; }
if ($status)    { $where[] = "b.status = ?";     $params[] = $status; }
if ($start)     { $where[] = "b.event_date >= ?"; $params[] = $start; }
if ($end)       { $where[] = "b.event_date <= ?"; $params[] = $end; }

$sql = "SELECT b.id, b.booking_ref, b.event_date, b.event_end_date, b.event_type, b.status, b.guest_count,
               c.name as customer_name, h.name as hall_name
        FROM bookings b
        LEFT JOIN customers c ON b.customer_id = c.id
        LEFT JOIN halls h ON b.hall_id = h.id
        WHERE " . implode(" AND ", $where) . "
        ORDER BY b.event_date ASC";

$bookings = $db->fetchAll($sql, $params);

$colorMap = [
    'confirmed'  => '#198754',
    'tentative'  => '#ffc107',
    'cancelled'  => '#dc3545',
    'completed'  => '#0d6efd',
    'inquiry'    => '#6c757d',
];

$events = [];
foreach ($bookings as $bk) {
    $color = $colorMap[$bk['status']] ?? '#6c757d';
    $endDate = $bk['event_end_date'] ?: $bk['event_date'];
    // Add 1 day to end for FullCalendar exclusive end
    $endDateExclusive = date('Y-m-d', strtotime($endDate . ' +1 day'));

    $events[] = [
        'id'    => $bk['id'],
        'title' => $bk['customer_name'] . ' — ' . ($bk['event_type'] ?? 'Event') . ' [' . $bk['hall_name'] . ']',
        'start' => $bk['event_date'],
        'end'   => $endDateExclusive,
        'color' => $color,
        'extendedProps' => [
            'status'       => $bk['status'],
            'booking_ref'  => $bk['booking_ref'],
            'guest_count'  => $bk['guest_count'],
            'hall'         => $bk['hall_name'],
        ],
    ];
}

header('Content-Type: application/json');
echo json_encode($events);
