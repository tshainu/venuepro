<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
if (!Auth::hasRole(['super_admin','hall_manager'])) { Helper::flash('error','Access denied.'); Helper::redirect(BASE_URL.'/modules/packages/index.php'); }
$db = Database::getInstance();
$id = (int)($_GET['id'] ?? 0);
$pkg = $db->fetchOne("SELECT * FROM packages WHERE id=?", [$id]);
if (!$pkg) { Helper::flash('error','Package not found.'); Helper::redirect(BASE_URL.'/modules/packages/index.php'); }
// Check if used in bookings
$used = $db->fetchOne("SELECT COUNT(*) as cnt FROM bookings WHERE package_id=?", [$id]);
if ($used['cnt'] > 0) { Helper::flash('error','Cannot delete — package is used in ' . $used['cnt'] . ' booking(s).'); Helper::redirect(BASE_URL.'/modules/packages/index.php'); }
$db->execute("DELETE FROM package_items WHERE package_id=?", [$id]);
$db->execute("DELETE FROM packages WHERE id=?", [$id]);
Helper::flash('success','Package deleted.');
Helper::redirect(BASE_URL.'/modules/packages/index.php');
