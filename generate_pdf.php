<?php
require_once __DIR__ . '/auth.php';
require_login();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(404); exit("Not found."); }

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute([':id' => $id]);
$user = $stmt->fetch();
if (!$user) { http_response_code(404); exit("Not found."); }

function imgToDataUri($path) {
  // $path is like "uploads/xxxx.jpg"
  $full = __DIR__ . '/' . $path;
  if (!is_file($full)) return '';
  $mime = mime_content_type($full);
  $data = base64_encode(file_get_contents($full));
  return "data:$mime;base64,$data";
}

$front = imgToDataUri($user['nid_front_path']);
$back  = imgToDataUri($user['nid_back_path']);

function e($s) {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

$html = '
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    @page { margin: 28px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color:#111; }
    h1 { font-size: 18px; margin: 0 0 10px; }
    .meta { color: #444; margin-bottom: 14px; }

    table { width: 100%; border-collapse: collapse; margin: 10px 0 16px; }
    td { border: 1px solid #ddd; padding: 8px; vertical-align: top; }
    .k { width: 28%; background: #f6f6f6; font-weight: bold; }

    /* NEW: Centered + Bigger images (stacked) */
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
  </style>
</head>
<body>
  <h1>User Information</h1>
  <div class="meta">Record ID: '.(int)$user['id'].' | Username: '.e($user['username']).'</div>

  <table>
    <tr><td class="k">Full Name</td><td>'.e($user['full_name']).'</td></tr>
    <tr><td class="k">Email</td><td>'.e($user['email']).'</td></tr>
    <tr><td class="k">Phone</td><td>'.e($user['phone']).'</td></tr>
    <tr><td class="k">Address</td><td>'.nl2br(e($user['address'])).'</td></tr>
    <tr><td class="k">NID Assigned Number</td><td>'.e($user['nid_assigned_number']).'</td></tr>
    <tr><td class="k">Created At</td><td>'.e($user['created_at']).'</td></tr>
  </table>

  <div class="imgwrap">
    <div class="imglabel">NID Front</div>
    <img class="nidimg" src="'.$front.'" alt="NID Front">

    <div class="imglabel">NID Back</div>
    <img class="nidimg" src="'.$back.'" alt="NID Back">
  </div>
</body>
</html>
';

$options = new Options();
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Make filename safe
$safeUser = preg_replace('/[^a-zA-Z0-9_-]+/', '_', (string)$user['username']);
$filename = 'user_' . $safeUser . '_id_' . (int)$user['id'] . '.pdf';

// Auto download
$dompdf->stream($filename, ["Attachment" => true]);
exit;
