<?php
require_once __DIR__ . '/../../core/bootstrap.php';
Auth::check();
$db = Database::getInstance();
$cu = Auth::currentUser();

$name         = trim($_POST['name'] ?? '');
$mobile       = trim($_POST['mobile'] ?? '');
$email        = trim($_POST['email'] ?? '');
$event_type   = trim($_POST['event_type'] ?? '');
$event_date   = trim($_POST['event_date'] ?? '') ?: null;
$guest_count  = (int)($_POST['guest_count'] ?? 0);
$hall_id      = (int)($_POST['hall_id'] ?? 0) ?: null;
$source       = trim($_POST['source'] ?? '');
$follow_up    = trim($_POST['follow_up_date'] ?? '') ?: null;
$notes        = trim($_POST['notes'] ?? '');
$branch_id    = $cu['branch_id'] ?? 1;

if (!$name || !$mobile) {
    Helper::flash('error', 'Name and mobile are required.');
    Helper::redirect(BASE_URL.'/modules/inquiries/index.php');
}

$ref = Helper::generateRef('INQ','inquiries','inquiry_ref');
$db->insert(
    "INSERT INTO inquiries (inquiry_ref,branch_id,name,mobile,email,event_type,event_date,guest_count,hall_id,source,follow_up_date,notes,status,created_by)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,'new',?)",
    [$ref,$branch_id,$name,$mobile,$email,$event_type,$event_date,$guest_count,$hall_id,$source,$follow_up,$notes,$cu['id']]
);

Helper::flash('success', "Inquiry $ref saved.");
Helper::redirect(BASE_URL.'/modules/inquiries/index.php');
