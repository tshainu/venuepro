<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
if (!Auth::hasRole(['super_admin','hall_manager'])) { Helper::flash('error','Access denied.'); Helper::redirect(BASE_URL.'/modules/addons/index.php'); }
$db = Database::getInstance();
$id = (int)($_GET['id'] ?? 0);
$addon = $db->fetchOne("SELECT * FROM addons WHERE id=?", [$id]);
if (!$addon) { Helper::flash('error','Add-on not found.'); Helper::redirect(BASE_URL.'/modules/addons/index.php'); }
$used = $db->fetchOne("SELECT COUNT(*) as cnt FROM booking_addons WHERE addon_id=?", [$id]);
if ($used['cnt'] > 0) { Helper::flash('error','Cannot delete — add-on is used in ' . $used['cnt'] . ' booking(s).'); Helper::redirect(BASE_URL.'/modules/addons/index.php'); }
$db->execute("DELETE FROM addons WHERE id=?", [$id]);
Helper::flash('success','Add-on deleted.');
Helper::redirect(BASE_URL.'/modules/addons/index.php');
