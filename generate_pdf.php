<?php
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

// Create simple PDF
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

// Remove header and footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set margins
$pdf->SetMargins(15, 15, 15);

// Add a page
$pdf->AddPage();

// Set font for Bengali text
$pdf->SetFont('freeserif', '', 12);

// Title
$pdf->SetFont('freeserif', 'B', 16);
$pdf->Cell(0, 10, 'User Information', 0, 1, 'C');
$pdf->Ln(5);

// User Information Table
$pdf->SetFont('freeserif', '', 11);

// Create a simple table
$pdf->SetFillColor(240, 240, 240);
$pdf->SetDrawColor(200, 200, 200);
$pdf->SetLineWidth(0.3);

// Table rows
$data = [
    ['ID', $user['id']],
    ['Username', $user['username']],
    ['Full Name', $user['full_name']],
    ['Email', $user['email']],
    ['Phone', $user['phone']],
    ['NID Number', $user['nid_assigned_number']],
    ['Status', ucfirst($user['status'])],
    ['Created', $user['created_at']]
];

foreach ($data as $row) {
    // Label
    $pdf->SetFont('freeserif', 'B', 11);
    $pdf->Cell(40, 8, $row[0] . ':', 1, 0, 'L', true);
    
    // Value
    $pdf->SetFont('freeserif', '', 11);
    $pdf->Cell(0, 8, $row[1], 1, 1, 'L');
}

$pdf->Ln(10);

// Address section
$pdf->SetFont('freeserif', 'B', 11);
$pdf->Cell(40, 8, 'Address:', 0, 0);
$pdf->SetFont('freeserif', '', 11);
$pdf->MultiCell(0, 8, $user['address'], 0, 'L');

$pdf->Ln(15);

// NID Front Image
if (!empty($user['nid_front_path']) && file_exists(__DIR__ . '/' . $user['nid_front_path'])) {
    $pdf->SetFont('freeserif', 'B', 12);
    $pdf->Cell(0, 10, 'NID Front', 0, 1, 'C');
    $pdf->SetFont('freeserif', '', 10);
    
    $image_path = __DIR__ . '/' . $user['nid_front_path'];
    list($img_width, $img_height) = getimagesize($image_path);
    
    // Calculate size for image (max width 150mm, maintain aspect ratio)
    $max_width = 150;
    $aspect_ratio = $img_height / $img_width;
    $display_width = min($max_width, $img_width / 3.78); // Convert pixels to mm (96 DPI approx)
    $display_height = $display_width * $aspect_ratio;
    
    // Center the image
    $x = (210 - $display_width) / 2; // A4 width = 210mm
    
    // Add the image
    $pdf->Image($image_path, $x, $pdf->GetY(), $display_width, $display_height, '', '', '', false, 300);
    
    // Move Y position down
    $pdf->SetY($pdf->GetY() + $display_height + 10);
} else {
    $pdf->SetFont('freeserif', 'I', 11);
    $pdf->Cell(0, 10, 'NID Front: Not available', 0, 1, 'C');
}

$pdf->Ln(5);

// NID Back Image
if (!empty($user['nid_back_path']) && file_exists(__DIR__ . '/' . $user['nid_back_path'])) {
    $pdf->SetFont('freeserif', 'B', 12);
    $pdf->Cell(0, 10, 'NID Back', 0, 1, 'C');
    $pdf->SetFont('freeserif', '', 10);
    
    $image_path = __DIR__ . '/' . $user['nid_back_path'];
    list($img_width, $img_height) = getimagesize($image_path);
    
    // Calculate size for image (max width 150mm, maintain aspect ratio)
    $max_width = 150;
    $aspect_ratio = $img_height / $img_width;
    $display_width = min($max_width, $img_width / 3.78); // Convert pixels to mm
    $display_height = $display_width * $aspect_ratio;
    
    // Center the image
    $x = (210 - $display_width) / 2; // A4 width = 210mm
    
    // Add the image
    $pdf->Image($image_path, $x, $pdf->GetY(), $display_width, $display_height, '', '', '', false, 300);
    
    // Move Y position down
    $pdf->SetY($pdf->GetY() + $display_height + 10);
} else {
    $pdf->SetFont('freeserif', 'I', 11);
    $pdf->Cell(0, 10, 'NID Back: Not available', 0, 1, 'C');
}

// Add footer with generation info
// $pdf->SetY(-20);
// $pdf->SetFont('freeserif', 'I', 8);
// $pdf->Cell(0, 5, 'Generated on: ' . date('Y-m-d H:i:s'), 0, 1, 'C');
// $pdf->Cell(0, 5, 'User ID: ' . $user['id'], 0, 1, 'C');

// Output PDF
$safe_name = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $user['full_name']);
$filename = 'User_' . $safe_name . '_ID_' . $user['id'] . '.pdf';

$pdf->Output($filename, 'D');
exit;