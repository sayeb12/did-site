<?php
// Set Dhaka timezone
date_default_timezone_set('Asia/Dhaka');

require_once __DIR__ . '/auth.php';
require_login();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/pdf_helpers.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    exit('Not found.');
}

$stmt = $pdo->prepare("SELECT u.*, a.full_name as approver_name 
                      FROM users u 
                      LEFT JOIN admin_users a ON u.approved_by = a.id 
                      WHERE u.id = :id");
$stmt->execute([':id' => $id]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(404);
    exit('Not found.');
}

$current_role = get_current_role();
if ($current_role === 'viewer' && !in_array($user['status'], ['approved', 'pending'])) {
    http_response_code(403);
    exit('Access denied.');
}

$pdfData = build_form_pdf($user);

$safe_name = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $user['full_name'] ?? 'User');
$filename = 'DID_Form_' . $safe_name . '_ID_' . $user['id'] . '_' . date('Ymd_His') . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdfData));
echo $pdfData;
exit;
