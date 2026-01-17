<?php
// Set Dhaka timezone
date_default_timezone_set('Asia/Dhaka');

// generate_pdf.php
require_once __DIR__ . '/auth.php';
require_login();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/vendor/autoload.php';

// Include TCPDF
require_once __DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf.php';

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

// Check permissions
$current_role = get_current_role();
if ($current_role === 'viewer' && !in_array($user['status'], ['approved', 'pending'])) {
    http_response_code(403);
    exit("Access denied.");
}

// Function to ensure text is properly encoded for PDF
function prepareTextForPDF($text) {
    if (empty($text)) return $text;
    
    // Check if text is already UTF-8
    if (mb_detect_encoding($text, 'UTF-8', true) === 'UTF-8') {
        return $text;
    }
    
    // Try to convert from common encodings to UTF-8
    $encodings = ['ISO-8859-1', 'Windows-1252', 'ASCII'];
    foreach ($encodings as $enc) {
        if (mb_detect_encoding($text, $enc, true) === $enc) {
            return mb_convert_encoding($text, 'UTF-8', $enc);
        }
    }
    
    return $text;
}

// Prepare text - DO NOT use htmlspecialchars for Bengali text
$fullName = prepareTextForPDF($user['full_name'] ?? '');
$address = prepareTextForPDF($user['address'] ?? '');
$nidNumber = prepareTextForPDF($user['nid_assigned_number'] ?? '');

// For English fields, use htmlspecialchars if needed, but TCPDF doesn't require it
$email = $user['email'] ?? '';
$phone = $user['phone'] ?? '';
$username = $user['username'] ?? '';
$status = ucfirst($user['status'] ?? 'pending');
$createdAt = $user['created_at'] ?? '';
$userId = (int)$user['id'];

// Create PDF
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(15, 15, 15);
$pdf->AddPage();

// Use freeserif font which supports Bengali in TCPDF
$pdf->SetFont('freeserif', '', 12);

// Title
$pdf->SetFont('freeserif', 'B', 16);
$pdf->Cell(0, 10, 'User Information', 0, 1, 'C');
$pdf->Ln(5);

// User Information Table
$pdf->SetFont('freeserif', '', 11);

// Create a simple table with alternating row colors
$pdf->SetFillColor(240, 240, 240);
$pdf->SetDrawColor(200, 200, 200);
$pdf->SetLineWidth(0.3);

// Table data - ALL in one table including address
$tableData = [
    ['ID', (string)$userId],
    ['Username', $username],
    ['Full Name', $fullName],
    ['Email', $email],
    ['Phone', $phone],
    ['NID Number', $nidNumber],
    ['Status', $status],
    ['Created', $createdAt],
    ['Address', $address]
];

$labelWidth = 40; // Width for label column

foreach ($tableData as $index => $row) {
    $fill = $index % 2 == 0; // Alternate row shading
    
    // Label column
    $pdf->SetFont('freeserif', 'B', 11);
    $pdf->Cell($labelWidth, 8, $row[0] . ':', 1, 0, 'L', $fill);
    
    // Value column - handle address specially
    $pdf->SetFont('freeserif', '', 11);
    
    if ($row[0] === 'Address') {
        // Save current position
        $startX = $pdf->GetX();
        $startY = $pdf->GetY();
        
        // Draw the address with MultiCell (it will expand downward)
        $pdf->MultiCell(0, 8, $row[1], 1, 'L', $fill);
        
        // Calculate how much we moved down
        $endY = $pdf->GetY();
        $height = $endY - $startY;
        
        // Go back and adjust the label cell height to match
        $pdf->SetXY($startX - $labelWidth, $startY);
        $pdf->SetFont('freeserif', 'B', 11);
        $pdf->Cell($labelWidth, $height, $row[0] . ':', 1, 0, 'L', $fill);
        
        // Set position for next content (already set by MultiCell)
        $pdf->SetY($endY);
    } else {
        $pdf->Cell(0, 8, $row[1], 1, 1, 'L', $fill);
    }
}

$pdf->Ln(15);

// NID Front Image
if (!empty($user['nid_front_path']) && file_exists(__DIR__ . '/' . $user['nid_front_path'])) {
    $pdf->SetFont('freeserif', 'B', 12);
    $pdf->Cell(0, 10, 'NID Front', 0, 1, 'C');
    $pdf->SetFont('freeserif', '', 10);
    
    $image_path = __DIR__ . '/' . $user['nid_front_path'];
    list($img_width, $img_height) = getimagesize($image_path);
    
    // Calculate size for image (max width 160mm, maintain aspect ratio)
    $max_width = 160;
    $aspect_ratio = $img_height / $img_width;
    $display_width = $max_width;
    $display_height = $display_width * $aspect_ratio;
    
    // Center the image
    $x = (210 - $display_width) / 2; // A4 width = 210mm
    
    // Add the image
    $pdf->Image($image_path, $x, $pdf->GetY(), $display_width, $display_height, '', '', '', false, 300);
    
    // Move Y position down
    $pdf->SetY($pdf->GetY() + $display_height + 15);
} else {
    $pdf->SetFont('freeserif', 'I', 11);
    $pdf->Cell(0, 10, 'NID Front: Not available', 0, 1, 'C');
    $pdf->Ln(5);
}

// NID Back Image
if (!empty($user['nid_back_path']) && file_exists(__DIR__ . '/' . $user['nid_back_path'])) {
    $pdf->SetFont('freeserif', 'B', 12);
    $pdf->Cell(0, 10, 'NID Back', 0, 1, 'C');
    $pdf->SetFont('freeserif', '', 10);
    
    $image_path = __DIR__ . '/' . $user['nid_back_path'];
    list($img_width, $img_height) = getimagesize($image_path);
    
    // Calculate size for image (max width 160mm, maintain aspect ratio)
    $max_width = 160;
    $aspect_ratio = $img_height / $img_width;
    $display_width = $max_width;
    $display_height = $display_width * $aspect_ratio;
    
    // Center the image
    $x = (210 - $display_width) / 2; // A4 width = 210mm
    
    // Add the image
    $pdf->Image($image_path, $x, $pdf->GetY(), $display_width, $display_height, '', '', '', false, 300);
} else {
    $pdf->SetFont('freeserif', 'I', 11);
    $pdf->Cell(0, 10, 'NID Back: Not available', 0, 1, 'C');
}

// Output PDF
$safe_name = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $user['full_name']);
$filename = 'User_' . $safe_name . '_ID_' . $user['id'] . '_' . date('Ymd_His') . '.pdf';

$pdf->Output($filename, 'D');
exit;