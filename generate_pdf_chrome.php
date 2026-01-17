<?php
// generate_pdf_chrome.php (updated: base64-embed font)
require_once __DIR__ . '/auth.php';
require_login();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/vendor/autoload.php';

use Spatie\Browsershot\Browsershot;

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(404); exit("Not found."); }

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute([':id' => $id]);
$user = $stmt->fetch();
if (!$user) { http_response_code(404); exit("Not found."); }

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function imgToDataUri($path) {
    $full = __DIR__ . '/' . $path;
    if (!is_file($full)) return '';
    $mime = mime_content_type($full);
    $data = base64_encode(file_get_contents($full));
    return "data:$mime;base64,$data";
}

$front = imgToDataUri($user['nid_front_path']);
$back  = imgToDataUri($user['nid_back_path']);

/* -----------------------------
   Embed Bengali TTF as base64
----------------------------- */
$fontPath = __DIR__ . '/fonts/NotoSansBengali-Regular.ttf';
if (!is_readable($fontPath)) {
    http_response_code(500);
    exit("Font not found or unreadable at fonts/NotoSansBengali-Regular.ttf");
}
$fontData = base64_encode(file_get_contents($fontPath));
$fontFace = "@font-face {
    font-family: 'NotoSansBengali';
    src: url('data:font/ttf;base64,{$fontData}') format('truetype');
    font-weight: normal;
    font-style: normal;
    font-display: swap;
}";

/* -----------------------------
   Build HTML (with embedded font)
----------------------------- */
$css = $fontFace . "
body { font-family: 'NotoSansBengali', sans-serif; font-size:12px; color:#111; }
h1 { font-size: 18px; margin-bottom: 10px; }
.meta { color: #444; margin-bottom: 14px; }
table { width:100%; border-collapse: collapse; }
td { border: 1px solid #ddd; padding: 8px; vertical-align: top; }
.k { width: 28%; background: #f6f6f6; font-weight: bold; }
.imgwrap { text-align: center; margin-top: 10px; }
.imglabel { font-size: 11px; color:#666; margin: 10px 0 6px; font-weight: bold; }
.nidimg { width: 92%; max-width: 560px; margin-bottom:14px; border:1px solid #ddd; border-radius:12px; }
";

$html = '<!doctype html><html><head><meta charset="utf-8"><style>' . $css . '</style></head><body>';
$html .= '<h1>User Information</h1>';
$html .= '<div class="meta">Record ID: ' . (int)$user['id'] . ' | Username: ' . e($user['username']) . '</div>';
$html .= '<table>';
$html .= '<tr><td class="k">Full Name</td><td>' . e($user['full_name']) . '</td></tr>';
$html .= '<tr><td class="k">Email</td><td>' . e($user['email']) . '</td></tr>';
$html .= '<tr><td class="k">Phone</td><td>' . e($user['phone']) . '</td></tr>';
$html .= '<tr><td class="k">Address</td><td>' . nl2br(e($user['address'])) . '</td></tr>';
$html .= '<tr><td class="k">NID Assigned Number</td><td>' . e($user['nid_assigned_number']) . '</td></tr>';
$html .= '<tr><td class="k">Created At</td><td>' . e($user['created_at']) . '</td></tr>';
$html .= '</table>';
$html .= '<div class="imgwrap"><div class="imglabel">NID Front</div><img class="nidimg" src="' . $front . '"><div class="imglabel">NID Back</div><img class="nidimg" src="' . $back . '"></div>';
$html .= '</body></html>';

/* Optional: save debug HTML you can open in a browser */
file_put_contents(__DIR__ . '/bengali_debug.html', $html);

/* -----------------------------
   Generate PDF via Chromium
----------------------------- */
$pdfPath = __DIR__ . '/user_' . (int)$user['id'] . '.pdf';

Browsershot::html($html)
    ->noSandbox()
    ->waitUntilNetworkIdle()
    ->format('A4')
    ->savePdf($pdfPath);

/* Stream PDF to browser */
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="user_' . preg_replace('/[^a-zA-Z0-9_-]+/','_', $user['username']) . '_id_' . (int)$user['id'] . '.pdf"');
readfile($pdfPath);
exit;
