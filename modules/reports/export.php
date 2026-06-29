<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
if (!Auth::hasRole(['super_admin','hall_manager','manager','accountant'])) { http_response_code(403); exit; }
$db = Database::getInstance();
$cu = Auth::currentUser();

$type      = $_GET['type']      ?? 'revenue';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to   = $_GET['date_to']   ?? date('Y-m-d');
$branch_id = $cu['branch_id']   ?? (int)($_GET['branch_id'] ?? 0);

$b_cond_bk  = $branch_id ? "AND b.branch_id=$branch_id" : "";
$b_cond_pay = $branch_id ? "AND p.branch_id=$branch_id" : "";

$filename = "venuepro_{$type}_{$date_from}_{$date_to}.csv";
header('Content-Type: text/csv; charset=utf-8');
header("Content-Disposition: attachment; filename=\"$filename\"");
$out = fopen('php://output','w');
// BOM for Excel UTF-8
fwrite($out, "\xEF\xBB\xBF");

if ($type === 'revenue') {
    fputcsv($out, ['Payment Ref','Date','Customer','Booking Ref','Invoice #','Method','Bank Ref','Amount (Rs.)','Received By']);
    $rows = $db->fetchAll(
        "SELECT p.payment_ref, p.payment_date, c.name, b.booking_ref, i.invoice_number, p.payment_method, p.reference_number, p.amount, u.name as rcv
         FROM payments p
         LEFT JOIN customers c ON p.customer_id=c.id
         LEFT JOIN bookings b ON p.booking_id=b.id
         LEFT JOIN invoices i ON p.invoice_id=i.id
         LEFT JOIN users u ON p.received_by=u.id
         WHERE p.payment_date BETWEEN ? AND ? $b_cond_pay ORDER BY p.payment_date DESC",
        [$date_from, $date_to]
    );
    foreach ($rows as $r) {
        fputcsv($out, [$r['payment_ref'],$r['payment_date'],$r['name'],$r['booking_ref']??'',$r['invoice_number']??'',ucfirst(str_replace('_',' ',$r['payment_method'])),$r['reference_number']??'',$r['amount'],$r['rcv']??'']);
    }
} elseif ($type === 'bookings') {
    fputcsv($out, ['Booking Ref','Event Date','Customer','Hall','Event Type','Status','Total Amount (Rs.)','Paid (Rs.)','Balance (Rs.)']);
    $rows = $db->fetchAll(
        "SELECT b.booking_ref, b.event_date, c.name, h.name as hall, b.event_type, b.status, b.final_amount, b.paid_amount, b.balance_amount
         FROM bookings b
         LEFT JOIN customers c ON b.customer_id=c.id
         LEFT JOIN halls h ON b.hall_id=h.id
         WHERE b.event_date BETWEEN ? AND ? $b_cond_bk ORDER BY b.event_date DESC",
        [$date_from, $date_to]
    );
    foreach ($rows as $r) {
        fputcsv($out, [$r['booking_ref'],$r['event_date'],$r['name'],$r['hall']??'',$r['event_type']??'',$r['status'],$r['final_amount'],$r['paid_amount'],$r['balance_amount']]);
    }
} elseif ($type === 'customers') {
    fputcsv($out, ['Customer','Mobile','Bookings','Total Value (Rs.)','Last Event']);
    $rows = $db->fetchAll(
        "SELECT c.name, c.mobile, COUNT(b.id) as bk, SUM(b.final_amount) as total, MAX(b.event_date) as last_event
         FROM bookings b LEFT JOIN customers c ON b.customer_id=c.id
         WHERE b.event_date BETWEEN ? AND ? $b_cond_bk
         GROUP BY b.customer_id ORDER BY total DESC",
        [$date_from, $date_to]
    );
    foreach ($rows as $r) {
        fputcsv($out, [$r['name'],$r['mobile'],$r['bk'],$r['total'],$r['last_event']]);
    }
} elseif ($type === 'payments') {
    // same as revenue but labelled as payments
    fputcsv($out, ['Payment Ref','Date','Customer','Booking','Method','Reference','Amount (Rs.)']);
    $rows = $db->fetchAll(
        "SELECT p.payment_ref, p.payment_date, c.name, b.booking_ref, p.payment_method, p.reference_number, p.amount
         FROM payments p
         LEFT JOIN customers c ON p.customer_id=c.id
         LEFT JOIN bookings b ON p.booking_id=b.id
         WHERE p.payment_date BETWEEN ? AND ? $b_cond_pay ORDER BY p.payment_date DESC",
        [$date_from, $date_to]
    );
    foreach ($rows as $r) {
        fputcsv($out, [$r['payment_ref'],$r['payment_date'],$r['name'],$r['booking_ref']??'',ucfirst(str_replace('_',' ',$r['payment_method'])),$r['reference_number']??'',$r['amount']]);
    }
}

fclose($out);
exit;
