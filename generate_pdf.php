<?php
// generate_pdf.php
require_once __DIR__ . '/auth.php';
require_login(); // All roles can generate PDF

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    exit("Not found.");
}

$stmt = $pdo->prepare("SELECT u.*, a.full_name as approver_name 
                      FROM users u 
                      LEFT JOIN admin_users a ON u.approved_by = a.id 
                      WHERE u.id = :id");
$stmt->execute([':id' => $id]);
$user = $stmt->fetch();
if (!$user) {
    http_response_code(404);
    exit("Not found.");
}

// Check if viewer can see this record
$current_role = get_current_role();
if ($current_role === 'viewer' && !in_array($user['status'], ['approved', 'pending'])) {
    http_response_code(403);
    exit("Access denied. You can only generate PDF for approved or pending records.");
}

/**
 * Convert image path (relative to project) to data URI (base64)
 */
function imgToDataUri($path) {
    $full = __DIR__ . '/' . ltrim($path, '/');
    if (!is_file($full)) return '';
    $mime = mime_content_type($full) ?: 'application/octet-stream';
    $data = base64_encode(file_get_contents($full));
    return "data:$mime;base64,$data";
}

$front = imgToDataUri($user['nid_front_path']);
$back  = imgToDataUri($user['nid_back_path']);

// Do not htmlspecialchars Bangla fields (per your preference)
$full_name = $user['full_name'] ?? '';
$address = nl2br($user['address'] ?? '');
$nid_number = $user['nid_assigned_number'] ?? '';

// Escape fields that might contain HTML or require safety
$email = htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8');
$phone = htmlspecialchars($user['phone'] ?? '', ENT_QUOTES, 'UTF-8');
$username = htmlspecialchars($user['username'] ?? '', ENT_QUOTES, 'UTF-8');
$status = htmlspecialchars(ucfirst($user['status'] ?? 'pending'), ENT_QUOTES, 'UTF-8');
$approver = htmlspecialchars($user['approver_name'] ?? 'Not yet approved', ENT_QUOTES, 'UTF-8');
$approved_at = $user['approved_at'] ? date('Y-m-d H:i:s', strtotime($user['approved_at'])) : 'N/A';

// Get session data for the footer
$admin_full_name = $_SESSION['admin_full_name'] ?? '';
$admin_username = $_SESSION['admin_username'] ?? '';
$admin_role = $_SESSION['admin_role'] ?? 'Unknown';
$generated_by = !empty($admin_full_name) ? $admin_full_name : $admin_username;
$generation_date = date('Y-m-d H:i:s');
$server_host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Path to your variable font file (as provided)
$fontPath = __DIR__ . '/fonts/NotoSansBengali-VariableFont_wdth,wght.ttf';
$fontFaceCss = '';

if (is_file($fontPath)) {
    $fontData = base64_encode(file_get_contents($fontPath));
    // embed the variable TTF as base64 data URI
    $fontFaceCss = "
    @font-face {
        font-family: 'NotoSansBengaliVar';
        src: url('data:font/ttf;base64,{$fontData}') format('truetype');
        font-weight: 100 900; /* variable font weight range */
        font-stretch: 75% 125%;
        font-style: normal;
        font-display: swap;
    }
    ";
} else {
    // If font missing, log a message; the rest of the code will still run (may not render Bangla)
    error_log('Bengali font not found at: ' . $fontPath);
}

// Keep your styles intact — only prepend the font-face
$css = <<<CSS
{$fontFaceCss}
@page { margin: 28px; }
body { font-family: 'NotoSansBengaliVar', 'DejaVu Sans', sans-serif; font-size: 12px; color:#111; }
h1 { font-size: 18px; margin: 0 0 10px; }
.meta { color: #444; margin-bottom: 14px; }

table { width: 100%; border-collapse: collapse; margin: 10px 0 16px; }
td { border: 1px solid #ddd; padding: 8px; vertical-align: top; }
.k { width: 28%; background: #f6f6f6; font-weight: bold; }

.imgwrap { text-align: center; margin-top: 10px; }
.imglabel { font-size: 11px; color:#666; margin: 10px 0 6px; font-weight: bold; }
.nidimg {
    width: 92%;
    max-width: 560px;
    display: block;
    margin: 0 auto 14px;
    border: 1px solid #ddd;
    border-radius: 12px;
}

/* Status badges */
.status-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 5px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
}
.status-pending { background: #fff3cd; color: #856404; }
.status-approved { background: #d4edda; color: #155724; }
.status-rejected { background: #f8d7da; color: #721c24; }

.approval-info {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 8px;
    margin-top: 5px;
    font-size: 11px;
    color: #666;
}

.footer-section {
    margin-top: 30px;
    padding-top: 10px;
    border-top: 1px solid #ddd;
    font-size: 10px;
    color: #666;
}
.footer-table {
    width: 100%;
    border: none;
}
.footer-cell {
    border: none;
    width: 50%;
}
.footer-right {
    text-align: right;
}
CSS;

$frontHtml = $front
    ? "<div class=\"imglabel\">NID Front</div>\n<img class=\"nidimg\" src=\"{$front}\" alt=\"NID Front\">"
    : "<div class=\"imglabel\">NID Front</div>\n<div style=\"color:#c00;\">No NID front image uploaded.</div>";

$backHtml = $back
    ? "<div class=\"imglabel\">NID Back</div>\n<img class=\"nidimg\" src=\"{$back}\" alt=\"NID Back\">"
    : "<div class=\"imglabel\">NID Back</div>\n<div style=\"color:#c00;\">No NID back image uploaded.</div>";

// Determine status badge class
$statusClass = 'status-' . $user['status'];

$html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
    {$css}
    </style>
</head>
<body>
    <h1>User Information</h1>
    <div class="meta">Record ID: {$user['id']} | Username: {$username}</div>

    <table>
        <tr><td class="k">Full Name</td><td>{$full_name}</td></tr>
        <tr><td class="k">Email</td><td>{$email}</td></tr>
        <tr><td class="k">Phone</td><td>{$phone}</td></tr>
        <tr><td class="k">Address</td><td>{$address}</td></tr>
        <tr><td class="k">NID Assigned Number</td><td>{$nid_number}</td></tr>
        <tr>
            <td class="k">Status</td>
            <td>
                <span class="status-badge {$statusClass}">{$status}</span>
                <div class="approval-info">
                    Approved by: {$approver}<br>
                    Approval Date: {$approved_at}
                </div>
            </td>
        </tr>
        <tr><td class="k">Created At</td><td>{$user['created_at']}</td></tr>
    </table>

    <div class="imgwrap">
        {$frontHtml}
        {$backHtml}
    </div>
    
    <div class="footer-section">
        <table class="footer-table">
            <tr>
                <td class="footer-cell">
                    <strong>Generated by:</strong><br>
                    {$generated_by} ({$admin_role})<br>
                    {$server_host}
                </td>
                <td class="footer-cell footer-right">
                    <strong>Generated on:</strong><br>
                    {$generation_date}<br>
                    DID Plan Data Store
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
HTML;

// Configure Dompdf for Unicode and font subsetting
$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('isHtml5ParserEnabled', true);
$options->set('isUnicodeEnabled', true);
$options->set('isFontSubsettingEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');
$options->set('defaultPaperSize', 'A4');
$options->set('defaultPaperOrientation', 'portrait');

$dompdf = new Dompdf($options);

// Load and render
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Prepare filename
$safeUser = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $user['username'] ?? 'user' . $user['id']);
$statusShort = substr($user['status'], 0, 1); // p for pending, a for approved, r for rejected
$filename = 'DID_User_' . $safeUser . '_ID_' . $user['id'] . '_' . strtoupper($statusShort) . '_' . date('Ymd') . '.pdf';

// Stream the PDF (force download)
$dompdf->stream($filename, [
    "Attachment" => true,
    "compress" => true
]);
exit;