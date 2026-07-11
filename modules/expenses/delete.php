<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
$db = Database::getInstance();
$cu = Auth::currentUser();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . BASE_URL . '/modules/expenses/index.php'); exit; }

// Branch-wise access control
$accessible_branch_ids = Auth::getAccessibleBranches();
$branch_ids = !empty($accessible_branch_ids) ? array_column($accessible_branch_ids, 'id') : [];
if (empty($branch_ids) && $cu['branch_id']) $branch_ids = [$cu['branch_id']];

$expense = $db->fetchOne("SELECT * FROM expenses WHERE id = ?", [$id]);
if (!$expense || !in_array($expense['branch_id'], $branch_ids)) {
    header('Location: ' . BASE_URL . '/modules/expenses/index.php');
    exit;
}

// Only owner, general_manager, manager can delete
$allowed_roles = ['owner', 'general_manager', 'manager', 'super_admin', 'admin'];
if (!in_array($cu['role'], $allowed_roles)) {
    header('Location: ' . BASE_URL . '/modules/expenses/index.php?error=noperm');
    exit;
}

$db->execute("DELETE FROM expenses WHERE id = ?", [$id]);
Logger::log('delete', 'expenses', $id, $expense['expense_ref'], ['title'=>$expense['title'],'amount'=>$expense['amount']], null, "Expense {$expense['expense_ref']} deleted");
header('Location: ' . BASE_URL . '/modules/expenses/index.php?success=deleted');
exit;
