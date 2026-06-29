<?php
require_once __DIR__ . '/../../core/bootstrap.php';

header('Content-Type: application/json');

// JSON-safe auth check — never redirect AJAX requests
if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success'=>false,'error'=>'Session expired. Please refresh the page.']);
    exit;
}

$db = Database::getInstance();
$cu = Auth::currentUser();

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) { echo json_encode(['success'=>false,'error'=>'Invalid request.']); exit; }

$name       = trim($input['name'] ?? '');
$mobile     = trim($input['mobile'] ?? '');
$mobile2    = trim($input['mobile2'] ?? '');
$email      = trim($input['email'] ?? '');
$nic        = trim($input['nic'] ?? '');
$city       = trim($input['city'] ?? '');
$address    = trim($input['address'] ?? '');
$bride_name = trim($input['bride_name'] ?? '');
$groom_name = trim($input['groom_name'] ?? '');
$notes      = trim($input['notes'] ?? '');
$branch_id  = $cu['branch_id'] ?? 1;

if (!$name)   { echo json_encode(['success'=>false,'error'=>'Name is required.']);   exit; }
if (!$mobile) { echo json_encode(['success'=>false,'error'=>'Mobile is required.']); exit; }
if (!preg_match('/^\d{10}$/', $mobile)) { echo json_encode(['success'=>false,'error'=>'Mobile number must be exactly 10 digits.']); exit; }

try {
    $id = $db->insert(
        "INSERT INTO customers (branch_id,name,bride_name,groom_name,nic,address,city,mobile,mobile2,email,notes,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
        [$branch_id,$name,$bride_name,$groom_name,$nic,$address,$city,$mobile,$mobile2,$email,$notes,$cu['id']]
    );
    echo json_encode([
        'success'    => true,
        'id'         => $id,
        'name'       => $name,
        'mobile'     => $mobile,
        'email'      => $email,
        'bride_name' => $bride_name,
        'groom_name' => $groom_name,
    ]);
} catch (Exception $e) {
    echo json_encode(['success'=>false,'error'=>'Database error: '.$e->getMessage()]);
}
