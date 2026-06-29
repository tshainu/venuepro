<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
if (!Auth::hasRole(['super_admin','admin','hall_manager','manager'])) { Helper::flash('error', Lang::t('access_denied')); Helper::redirect(BASE_URL.'/modules/halls/index.php'); }
$db = Database::getInstance();
$id = (int)($_GET['id'] ?? 0);
$hall = $db->fetchOne("SELECT * FROM halls WHERE id=?", [$id]);
if ($hall) Logger::log('delete','halls',$id,$hall['name'],'name:'.$hall['name'],null,'Hall deleted');
$db->execute("DELETE FROM halls WHERE id=?", [$id]);
Helper::flash('success', 'Hall deleted.');
Helper::redirect(($_GET['return']??'')==='settings' ? BASE_URL.'/modules/settings/index.php?tab=halls' : BASE_URL.'/modules/halls/index.php');
