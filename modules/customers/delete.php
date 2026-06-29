<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
if (!Auth::hasRole(['super_admin','admin','hall_manager','manager'])) { Helper::flash('error',Lang::t('access_denied')); Helper::redirect(BASE_URL.'/modules/customers/index.php'); }
$db = Database::getInstance();
$id = (int)($_GET['id'] ?? 0);
$cust = $db->fetchOne("SELECT * FROM customers WHERE id=?", [$id]);
if ($cust) {
    Logger::log('delete', 'customers', $id, $cust['name'],
        ['name'=>$cust['name'],'mobile'=>$cust['mobile'],'email'=>$cust['email']],
        null, "Deleted customer {$cust['name']}");
}
$db->execute("DELETE FROM customers WHERE id=?", [$id]);
Helper::flash('success','Customer deleted.');
Helper::redirect(BASE_URL.'/modules/customers/index.php');
