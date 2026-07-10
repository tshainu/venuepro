<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
$lang = $_GET['lang'] ?? 'en';
if (!in_array($lang, ['en', 'ta', 'si'])) $lang = 'en';
$_SESSION['language'] = $lang;
// Also update DB
$db = Database::getInstance();
$db->execute("UPDATE users SET language = ? WHERE id = ?", [$lang, $_SESSION['user_id']]);
Helper::redirect(BASE_URL . '/index.php');
