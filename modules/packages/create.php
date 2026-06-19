<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
if (!Auth::hasRole(['super_admin','admin','hall_manager'])) { Helper::flash('error',Lang::t('access_denied')); Helper::redirect(($_GET['return']??'')==='settings' ? BASE_URL.'/modules/settings/index.php?tab=packages' : BASE_URL.'/modules/packages/index.php'); }
$db = Database::getInstance();
$cu = Auth::currentUser();
$branches = $db->fetchAll("SELECT id,name FROM branches WHERE is_active=1");
$return = $_GET['return'] ?? '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name'] ?? '');
    $branch_id   = (int)($_POST['branch_id'] ?? $cu['branch_id']);
    $description = trim($_POST['description'] ?? '');
    $price       = (float)($_POST['price'] ?? 0);
    $items       = $_POST['items'] ?? [];
    if (!$name) $errors[] = 'Package name required.';
    if (!$errors) {
        $pid = $db->insert("INSERT INTO packages (branch_id,name,description,price) VALUES (?,?,?,?)", [$branch_id,$name,$description,$price]);
        foreach ($items as $it) {
            $iname = trim($it['name'] ?? '');
            $qty   = (int)($it['qty'] ?? 1);
            $unit  = trim($it['unit'] ?? '');
            $notes = trim($it['notes'] ?? '');
            if ($iname) $db->insert("INSERT INTO package_items (package_id,item_name,quantity,unit,notes) VALUES (?,?,?,?,?)", [$pid,$iname,$qty,$unit,$notes]);
        }
        Helper::flash('success','Package created.');
        Helper::redirect($return==='settings' ? BASE_URL.'/modules/settings/index.php?tab=packages' : BASE_URL.'/modules/packages/index.php');
    }
}

$pageTitle   = 'Add Package';
$breadcrumbs = [['label'=>'Packages','url'=>BASE_URL.'/modules/packages/index.php'],['label'=>'Add Package']];
require_once __DIR__ . '/../../includes/header.php';
?>

<?php include __DIR__ . '/package_form.php'; ?>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
