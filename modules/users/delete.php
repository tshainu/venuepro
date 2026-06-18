<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
if (!Auth::isSuperAdmin()) { Helper::flash('error','Super Admin only.'); Helper::redirect(BASE_URL.'/index.php'); }
$db = Database::getInstance();
$id = (int)($_GET['id'] ?? 0);
if ($id === Auth::currentUser()['id']) { Helper::flash('error','Cannot delete your own account.'); Helper::redirect(BASE_URL.'/modules/users/index.php'); }
$user = $db->fetchOne("SELECT * FROM users WHERE id=?", [$id]);
if (!$user) { Helper::flash('error','User not found.'); Helper::redirect(BASE_URL.'/modules/users/index.php'); }
$db->execute("UPDATE users SET is_active=0 WHERE id=?", [$id]); // soft delete
Helper::flash('success','User deactivated.');
Helper::redirect(BASE_URL.'/modules/users/index.php');
