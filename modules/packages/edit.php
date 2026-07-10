<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
if (!Auth::hasRole(['super_admin','admin','hall_manager','manager','owner','general_manager'])) { Helper::flash('error',Lang::t('access_denied')); Helper::redirect(($_GET['return']??'')==='settings' ? BASE_URL.'/modules/settings/index.php?tab=packages' : BASE_URL.'/modules/packages/index.php'); }
$db = Database::getInstance();
$id  = (int)($_GET['id'] ?? 0);
$pkg = $db->fetchOne("SELECT * FROM packages WHERE id=?", [$id]);
if (!$pkg) { Helper::flash('error','Package not found.'); Helper::redirect(($_GET['return']??'')==='settings' ? BASE_URL.'/modules/settings/index.php?tab=packages' : BASE_URL.'/modules/packages/index.php'); }
$existing_items = $db->fetchAll("SELECT * FROM package_items WHERE package_id=? ORDER BY id", [$id]);
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
$return   = $_GET['return'] ?? '';
$errors   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name'] ?? '');
    $branch_ids  = array_map('intval', $_POST['branch_ids'] ?? [$pkg['branch_id']]);
    $description = trim($_POST['description'] ?? '');
    $price       = (float)($_POST['price'] ?? 0);
    $items       = $_POST['items'] ?? [];
    if (!$name) $errors[] = 'Package name required.';
    if (empty($branch_ids)) $errors[] = 'Please select at least one branch.';
    if (!$errors) {
        $primary_branch_id = $branch_ids[0];
        Logger::log('edit','packages',$id,$pkg['name'],'name:'.$pkg['name'].' price:'.$pkg['price'],'name:'.$name.' price:'.$price,'Package updated');
        $db->execute("UPDATE packages SET branch_id=?,name=?,description=?,price=? WHERE id=?", [$primary_branch_id,$name,$description,$price,$id]);
        // Sync multi-branch associations
        $db->execute("DELETE FROM package_branches WHERE package_id=?", [$id]);
        foreach ($branch_ids as $bid_val) {
            $db->insert("INSERT IGNORE INTO package_branches (package_id, branch_id) VALUES (?,?)", [$id, $bid_val]);
        }
        $db->execute("DELETE FROM package_items WHERE package_id=?", [$id]);
        foreach ($items as $it) {
            $iname = trim($it['name'] ?? '');
            $qty   = (int)($it['qty'] ?? 1);
            $unit  = trim($it['unit'] ?? '');
            $notes = trim($it['notes'] ?? '');
            if ($iname) $db->insert("INSERT INTO package_items (package_id,item_name,quantity,unit,notes) VALUES (?,?,?,?,?)", [$id,$iname,$qty,$unit,$notes]);
        }
        Helper::flash('success','Package updated.');
        Helper::redirect($return==='settings' ? BASE_URL.'/modules/settings/index.php?tab=packages' : BASE_URL.'/modules/packages/index.php');
    }
}

$pageTitle   = 'Edit Package';
$breadcrumbs = [['label'=>'Packages','url'=>BASE_URL.'/modules/packages/index.php'],['label'=>'Edit']];
require_once __DIR__ . '/../../includes/header.php';
?>

<?php include __DIR__ . '/package_form.php'; ?>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
