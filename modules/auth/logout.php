<?php
require_once __DIR__ . '/../../core/bootstrap.php';
session_destroy();
header('Location: ' . BASE_URL . '/login.php');
exit;
