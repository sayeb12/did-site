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

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    exit('ZipArchive is not available on this server.');
}

$tmpZip = tempnam(sys_get_temp_dir(), 'did_zip_');
$zip = new ZipArchive();
if ($zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    exit('Could not create ZIP.');
}

// User info PDF (existing format)
$userInfoPdf = build_user_info_pdf($user, 'S', 'user_info.pdf');
$zip->addFromString('User_Info.pdf', $userInfoPdf);

// Form PDF (new layout)
$formPdf = build_form_pdf($user);
$zip->addFromString('DID_Form.pdf', $formPdf);

// NID-only PDF
$nidPdf = build_nid_only_pdf($user, 'S', 'nid_only.pdf');
$zip->addFromString('NID_Images.pdf', $nidPdf);

// Trade license PDF if provided
if (!empty($user['trade_license_path'])) {
    $tradeRel = $user['trade_license_path'];
    $tradeFull = __DIR__ . '/' . $tradeRel;
    if (is_file($tradeFull)) {
        $ext = strtolower(pathinfo($tradeFull, PATHINFO_EXTENSION));
        if ($ext === 'pdf') {
            $zip->addFile($tradeFull, 'Trade_License.pdf');
        } else {
            $tradePdf = build_trade_license_pdf_from_image($tradeFull, 'S', 'trade_license.pdf');
            $zip->addFromString('Trade_License.pdf', $tradePdf);
        }
    }
}

// Personal photo file if provided
if (!empty($user['personal_photo_path'])) {
    $photoRel = $user['personal_photo_path'];
    $photoFull = __DIR__ . '/' . $photoRel;
    if (is_file($photoFull)) {
        $photoExt = pathinfo($photoFull, PATHINFO_EXTENSION);
        $zip->addFile($photoFull, 'Personal_Photo.' . $photoExt);
    }
}

$zip->close();

$safe_name = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $user['full_name'] ?? 'User');
$bundleName = 'DID_Bundle_' . $safe_name . '_ID_' . $user['id'] . '_' . date('Ymd_His') . '.zip';

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $bundleName . '"');
header('Content-Length: ' . filesize($tmpZip));
readfile($tmpZip);
@unlink($tmpZip);
exit;
