<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
if (!Auth::hasRole(['super_admin','hall_manager'])) { Helper::flash('error',Lang::t('access_denied')); Helper::redirect(BASE_URL.'/modules/customers/index.php'); }
$db = Database::getInstance();
$id = (int)($_GET['id'] ?? 0);
$db->execute("DELETE FROM customers WHERE id=?", [$id]);
Helper::flash('success','Customer deleted.');
Helper::redirect(BASE_URL.'/modules/customers/index.php');
