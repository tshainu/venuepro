<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
if (!Auth::hasRole(['super_admin','admin','hall_manager','manager','owner','general_manager'])) { Helper::flash('error',Lang::t('access_denied')); Helper::redirect(($_GET['return']??'')==='settings' ? BASE_URL.'/modules/settings/index.php?tab=packages' : BASE_URL.'/modules/packages/index.php'); }
$db = Database::getInstance();
$cu = Auth::currentUser();
// Filter branches by business for owners/admins
$user_uid = $_SESSION["user_uid"] ?? "";
$biz_info = $db->fetchOne("SELECT id FROM sa_businesses WHERE admin_user_id = ?", [$user_uid]);
$sa_biz_id = $biz_info ? (int)$biz_info["id"] : 0;
if (Auth::isSuperAdmin()) {
    $branches = $db->fetchAll("SELECT id, name FROM branches WHERE is_active=1");
} elseif ($sa_biz_id) {
    $branches = $db->fetchAll("SELECT id, name FROM branches WHERE is_active=1 AND business_id = ?", [$sa_biz_id]);
} else {
    $branches = $db->fetchAll("SELECT id, name FROM branches WHERE is_active=1 AND id = ?", [$cu["branch_id"]]);
}
$return = $_GET['return'] ?? '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name'] ?? '');
    $branch_ids  = array_map('intval', $_POST['branch_ids'] ?? [$cu['branch_id']]);
    $description = trim($_POST['description'] ?? '');
    $price       = (float)($_POST['price'] ?? 0);
    $items       = $_POST['items'] ?? [];
    if (!$name) $errors[] = 'Package name required.';
    if (empty($branch_ids)) $errors[] = 'Please select at least one branch.';
    if (!$errors) {
        $primary_branch_id = $branch_ids[0];
        $pid = $db->insert("INSERT INTO packages (branch_id,name,description,price) VALUES (?,?,?,?)", [$primary_branch_id,$name,$description,$price]);
        // Save multi-branch associations
        foreach ($branch_ids as $bid_val) {
            $db->insert("INSERT IGNORE INTO package_branches (package_id, branch_id) VALUES (?,?)", [$pid, $bid_val]);
        }
        Logger::log('create','packages',$pid,$name,null,'name:'.$name.' price:'.$price,'Package created');
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
