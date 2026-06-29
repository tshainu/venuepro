<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
if (!Auth::hasRole(['super_admin','admin','hall_manager'])) { Helper::flash('error','Super Admin only.'); Helper::redirect(BASE_URL.'/index.php'); }
$db = Database::getInstance();
$id = (int)($_GET['id'] ?? 0);
$br = $db->fetchOne("SELECT * FROM branches WHERE id=?", [$id]);
if (!$br) { Helper::flash('error','Branch not found.'); Helper::redirect(BASE_URL.'/modules/branches/index.php'); }
$bk = $db->fetchOne("SELECT COUNT(*) as cnt FROM bookings WHERE branch_id=?", [$id]);
if ($bk['cnt'] > 0) { Helper::flash('error','Cannot delete — branch has '.$bk['cnt'].' booking(s).'); Helper::redirect(BASE_URL.'/modules/branches/index.php'); }
$db->execute("UPDATE branches SET is_active=0 WHERE id=?", [$id]);
Helper::flash('success','Branch deactivated.');
Helper::redirect(BASE_URL.'/modules/branches/index.php');
