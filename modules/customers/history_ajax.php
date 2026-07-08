<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
header('Content-Type: application/json');

$customer_id = (int)($_GET['customer_id'] ?? 0);
if (!$customer_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid customer ID']);
    exit;
}

$db = Database::getInstance();
$cu = Auth::currentUser();

// Verify customer belongs to user's branch (if not super admin)
$customer = $db->fetchOne("SELECT id, branch_id FROM customers WHERE id=?", [$customer_id]);
if (!$customer) {
    echo json_encode(['success' => false, 'message' => 'Customer not found']);
    exit;
}

if (!Auth::isSuperAdmin() && $cu['branch_id'] && $customer['branch_id'] != $cu['branch_id']) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Get customer's booking history
$bookings = $db->fetchAll(
    "SELECT id, booking_ref, event_type, event_date, status 
     FROM bookings 
     WHERE customer_id=? 
     ORDER BY event_date DESC 
     LIMIT 20",
    [$customer_id]
);

echo json_encode([
    'success' => true,
    'bookings' => $bookings
]);
?>
