<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
if (!Auth::isSuperAdmin()) { Helper::flash('error','Only Super Admin can delete bookings.'); Helper::redirect(BASE_URL.'/modules/bookings/index.php'); }
$db = Database::getInstance();
$id = (int)($_GET['id'] ?? 0);
$bk = $db->fetchOne("SELECT * FROM bookings WHERE id=?", [$id]);
if (!$bk) { Helper::flash('error','Booking not found.'); Helper::redirect(BASE_URL.'/modules/bookings/index.php'); }
if (!in_array($bk['status'],['inquiry','cancelled'])) { Helper::flash('error','Can only delete inquiry or cancelled bookings.'); Helper::redirect(BASE_URL.'/modules/bookings/view.php?id='.$id); }
Logger::log('delete', 'bookings', $id, $bk['booking_ref'],
    ['booking_ref'=>$bk['booking_ref'],'customer_id'=>$bk['customer_id'],'event_date'=>$bk['event_date'],'status'=>$bk['status'],'final_amount'=>$bk['final_amount']],
    null, "Deleted booking {$bk['booking_ref']}");
$db->execute("DELETE FROM booking_addons WHERE booking_id=?", [$id]);
$db->execute("DELETE FROM booking_rooms WHERE booking_id=?", [$id]);
$db->execute("DELETE FROM bookings WHERE id=?", [$id]);
Helper::flash('success','Booking deleted.');
Helper::redirect(BASE_URL.'/modules/bookings/index.php');
