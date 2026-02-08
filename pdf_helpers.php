<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf.php';

function prepareTextForPDF($text) {
    if (empty($text)) return $text;

    if (mb_detect_encoding($text, 'UTF-8', true) === 'UTF-8') {
        return $text;
    }

    $encodings = ['ISO-8859-1', 'Windows-1252', 'ASCII'];
    foreach ($encodings as $enc) {
        if (mb_detect_encoding($text, $enc, true) === $enc) {
            return mb_convert_encoding($text, 'UTF-8', $enc);
        }
    }

    return $text;
}

function addCenteredImageToPdf($pdf, $title, $imagePath) {
    if (!empty($title)) {
        $pdf->SetFont('freeserif', 'B', 12);
        $pdf->Cell(0, 10, $title, 0, 1, 'C');
        $pdf->SetFont('freeserif', '', 10);
    }

    if (!is_file($imagePath)) {
        $pdf->SetFont('freeserif', 'I', 11);
        $pdf->Cell(0, 8, $title . ': Not available', 0, 1, 'C');
        $pdf->Ln(4);
        return;
    }

    $size = @getimagesize($imagePath);
    if (!$size) {
        $pdf->SetFont('freeserif', 'I', 11);
        $pdf->Cell(0, 8, $title . ': Not available', 0, 1, 'C');
        $pdf->Ln(4);
        return;
    }

    $img_width = $size[0];
    $img_height = $size[1];

    $max_width = 160;
    $aspect_ratio = $img_height / max(1, $img_width);
    $display_width = $max_width;
    $display_height = $display_width * $aspect_ratio;

    if ($pdf->GetY() + $display_height > 270) {
        $pdf->AddPage();
    }

    $x = (210 - $display_width) / 2;
    $pdf->Image($imagePath, $x, $pdf->GetY(), $display_width, $display_height, '', '', '', false, 300);
    $pdf->SetY($pdf->GetY() + $display_height + 10);
}

function build_user_info_pdf(array $user, $dest = 'S', $filename = null) {
    $fullName = prepareTextForPDF($user['full_name'] ?? '');
    $address = prepareTextForPDF($user['address'] ?? '');
    $nidNumber = prepareTextForPDF($user['nid_assigned_number'] ?? '');
    $companyName = prepareTextForPDF($user['company_name'] ?? '');
    $didNumber = prepareTextForPDF($user['did_number'] ?? '');
    $trunkPassword = prepareTextForPDF($user['trunk_password'] ?? '');
    $channelCount = prepareTextForPDF(isset($user['channel_count']) ? (string)$user['channel_count'] : '');

    $email = $user['email'] ?? '';
    $phone = $user['phone'] ?? '';
    $username = $user['username'] ?? '';
    $status = ucfirst($user['status'] ?? 'pending');
    $createdAt = $user['created_at'] ?? '';
    $userId = (int)($user['id'] ?? 0);

    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(15, 15, 15);
    $pdf->AddPage();
    $pdf->SetFont('freeserif', '', 12);

    $pdf->SetFont('freeserif', 'B', 16);
    $pdf->Cell(0, 10, 'User Information', 0, 1, 'C');
    $pdf->Ln(5);

    $pdf->SetFont('freeserif', '', 11);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->SetDrawColor(200, 200, 200);
    $pdf->SetLineWidth(0.3);

    $tableData = [
        ['ID', (string)$userId],
        ['Username', $username],
        ['Full Name', $fullName],
        ['Company Name', $companyName],
        ['DID Number', $didNumber],
        ['Trunk Password', $trunkPassword],
        ['No. of Channels', $channelCount],
        ['Email', $email],
        ['Phone', $phone],
        ['NID Number', $nidNumber],
        ['Status', $status],
        ['Created', $createdAt],
        ['Address', $address]
    ];

    $labelWidth = 40;

    foreach ($tableData as $index => $row) {
        $fill = $index % 2 == 0;

        $pdf->SetFont('freeserif', 'B', 11);
        $pdf->Cell($labelWidth, 8, $row[0] . ':', 1, 0, 'L', $fill);

        $pdf->SetFont('freeserif', '', 11);
        if ($row[0] === 'Address') {
            $startX = $pdf->GetX();
            $startY = $pdf->GetY();

            $pdf->MultiCell(0, 8, $row[1], 1, 'L', $fill);

            $endY = $pdf->GetY();
            $height = $endY - $startY;

            $pdf->SetXY($startX - $labelWidth, $startY);
            $pdf->SetFont('freeserif', 'B', 11);
            $pdf->Cell($labelWidth, $height, $row[0] . ':', 1, 0, 'L', $fill);
            $pdf->SetY($endY);
        } else {
            $pdf->Cell(0, 8, $row[1], 1, 1, 'L', $fill);
        }
    }

    $pdf->Ln(15);

    $nidFront = !empty($user['nid_front_path']) ? (__DIR__ . '/' . $user['nid_front_path']) : '';
    $nidBack = !empty($user['nid_back_path']) ? (__DIR__ . '/' . $user['nid_back_path']) : '';

    if ($nidFront) {
        addCenteredImageToPdf($pdf, 'NID Front', $nidFront);
    } else {
        $pdf->SetFont('freeserif', 'I', 11);
        $pdf->Cell(0, 10, 'NID Front: Not available', 0, 1, 'C');
        $pdf->Ln(5);
    }

    if ($nidBack) {
        addCenteredImageToPdf($pdf, 'NID Back', $nidBack);
    } else {
        $pdf->SetFont('freeserif', 'I', 11);
        $pdf->Cell(0, 10, 'NID Back: Not available', 0, 1, 'C');
    }

    if (!$filename) {
        $safe_name = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $user['full_name'] ?? 'User');
        $filename = 'User_' . $safe_name . '_ID_' . $userId . '_' . date('Ymd_His') . '.pdf';
    }

    return $pdf->Output($filename, $dest);
}

function build_nid_only_pdf(array $user, $dest = 'S', $filename = null) {
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(15, 15, 15);
    $pdf->AddPage();
    $pdf->SetFont('freeserif', '', 12);

    $nidFront = !empty($user['nid_front_path']) ? (__DIR__ . '/' . $user['nid_front_path']) : '';
    $nidBack = !empty($user['nid_back_path']) ? (__DIR__ . '/' . $user['nid_back_path']) : '';

    addCenteredImageToPdf($pdf, 'NID Front', $nidFront);
    addCenteredImageToPdf($pdf, 'NID Back', $nidBack);

    if (!$filename) {
        $filename = 'NID_Images_' . (int)($user['id'] ?? 0) . '.pdf';
    }

    return $pdf->Output($filename, $dest);
}

function build_trade_license_pdf_from_image($imagePath, $dest = 'S', $filename = null) {
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(15, 15, 15);
    $pdf->AddPage();
    $pdf->SetFont('freeserif', '', 12);

    addCenteredImageToPdf($pdf, 'Trade License', $imagePath);

    if (!$filename) {
        $filename = 'Trade_License_' . date('Ymd_His') . '.pdf';
    }

    return $pdf->Output($filename, $dest);
}

function build_form_pdf(array $user) {
    $companyName = $user['company_name'] ?? '';
    $fullName = $user['full_name'] ?? '';
    $didNumber = $user['did_number'] ?? '';
    $didDigits = preg_replace('/\\D+/', '', (string)$didNumber);
    $trunkPassword = $user['trunk_password'] ?? '';
    $channelCount = isset($user['channel_count']) ? (string)$user['channel_count'] : '';
    $address = $user['address'] ?? '';
    $email = $user['email'] ?? '';
    $phone = $user['phone'] ?? '';
    $nidNumber = $user['nid_assigned_number'] ?? '';
    $createdAt = $user['created_at'] ?? '';

    $serialNo = (string)($user['id'] ?? '');
    $salesDate = '';
    if (!empty($createdAt)) {
        $ts = strtotime($createdAt);
        if ($ts !== false) {
            $salesDate = date('d/m/Y', $ts);
        }
    }
    if ($salesDate === '') {
        $salesDate = date('d/m/Y');
    }

    $logoSrc = '';
    if (is_file(__DIR__ . '/RITT_logo.png')) {
        $logoSrc = 'RITT_logo.png';
    }

    $photoSrc = '';
    if (!empty($user['personal_photo_path']) && is_file(__DIR__ . '/' . $user['personal_photo_path'])) {
        $photoSrc = $user['personal_photo_path'];
    }

    $subscriber_name_bn = $companyName !== '' ? $companyName : $fullName;
    $subscriber_name_en = $subscriber_name_bn;
    $authorized_name_bn = $fullName;
    $authorized_name_en = $fullName;
    $national_id_no = $nidNumber;
    $passport_no = '';
    $dob = '';
    $gender = '';

    $perm_flat = '';
    $perm_house = '';
    $perm_road = '';
    $perm_village = $address;
    $perm_po = '';
    $perm_upazila = '';
    $perm_thana = '';
    $perm_district = '';
    $perm_postcode = '';

    $pres_flat = '';
    $pres_house = '';
    $pres_road = '';
    $pres_village = $address;
    $pres_po = '';
    $pres_upazila = '';
    $pres_thana = '';
    $pres_district = '';
    $pres_postcode = '';

    $contact_tel = '';
    $contact_mobile = $phone;
    $contact_email = $email;
    $occupation = '';
    $occupation_other = '';
    $father_name = '';
    $mother_name = '';
    $spouse_name = '';
    $identifier_name = '';
    $identifier_phone = '';
    $identifier_nid = '';
    $internet_required = '';

    $companyNorm = strtolower(trim($companyName));
    $fullNorm = strtolower(trim($fullName));
    $isCorporate = ($companyNorm !== '' && ($fullNorm == '' || $companyNorm !== $fullNorm));
    $isIndividual = !$isCorporate;

    $banglaFontCssPath = '';
    $candidateFonts = [
        'NotoSansBengali-Regular.ttf',
        'SolaimanLipi_20-04-07.ttf',
        'Siyamrupali.ttf',
        'kalpurush.ttf'
    ];
    foreach ($candidateFonts as $fontFile) {
        if (is_file(__DIR__ . '/fonts/' . $fontFile)) {
            $banglaFontCssPath = 'fonts/' . $fontFile;
            break;
        }
    }
    $hasBanglaFont = ($banglaFontCssPath !== '');

    $termsHtml = '';
    $termsPath = __DIR__ . '/templates/did_terms.html';
    if (is_file($termsPath)) {
        $termsHtml = file_get_contents($termsPath);
    }

    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <style>
<?php if ($hasBanglaFont): ?>
            @font-face {
                font-family: 'Bangla';
                src: url('<?= htmlspecialchars($banglaFontCssPath, ENT_QUOTES, "UTF-8") ?>') format('truetype');
            }
            body {
                font-family: 'Bangla', DejaVu Sans, Arial, Helvetica, sans-serif;
                font-size: 11px;
            }
<?php else: ?>
            body {
                font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
                font-size: 11px;
            }
<?php endif; ?>
            .form-wrapper {
                width: 100%;
                border: 1px solid #000;
                padding: 10px 15px;
            }
            .top-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 5px;
            }
            .top-table td {
                vertical-align: top;
            }
            .thumb-cell,
            .photo-cell {
                width: 110px;
            }
            .header-center {
                text-align: center;
                font-weight: bold;
                font-size: 15px;
            }
            .small {
                font-size: 10px;
            }
            .thumb-box,
            .photo-box {
                width: 95px;
                height: 90px;
                border: 1px solid #000;
                display: flex;
                align-items: center;
                justify-content: center;
                text-align: center;
                padding: 2px;
            }
            .thumb-box {
                margin-right: 10px;
            }
            .photo-box {
                margin-left: 10px;
            }
            .section-table {
                width: 100%;
                border-collapse: collapse;
                font-size: 11px;
                margin-bottom: 6px;
            }
            .section-table td {
                padding: 2px 4px;
                vertical-align: top;
            }
            .label {
                width: 28%;
                white-space: nowrap;
            }
            .value-line {
                border-bottom: 1px solid #000;
                padding-bottom: 2px;
            }
            .section-title {
                font-weight: bold;
                margin-top: 4px;
            }
            .checkbox-line {
                font-size: 10px;
                margin-bottom: 3px;
            }
            .checkbox-line span.box {
                display: inline-block;
                width: 14px;
                height: 14px;
                border: 1px solid #000;
                margin: 0 2px 0 6px;
                text-align: center;
                line-height: 14px;
            }
            .checkbox-gap {
                display: inline-block;
                width: 45px;
            }
            .digit-box {
                display: inline-block;
                width: 14px;
                height: 18px;
                border: 1px solid #000;
                margin: 0 1px;
                text-align: center;
                line-height: 14px;
                font-size: 9px;
                vertical-align: middle;
            }
            .terms-page {
                page-break-before: auto;
                font-size: 9px;
                line-height: 1.3;
                margin-top: 6px;
            }
            .terms-box {
                border: 1px solid #000;
            }
            .terms-header {
                background: #dcdcdc;
                border-bottom: 1px solid #000;
                text-align: center;
                font-weight: bold;
                padding: 2px 0;
            }
            .terms-table {
                width: 100%;
                border-collapse: collapse;
            }
            .terms-table td {
                vertical-align: top;
                padding: 4px 6px;
            }
            .terms-content {
                column-count: 2;
                column-gap: 18px;
                padding: 4px 6px;
            }
            .terms-sign-row {
                width: 100%;
                border-collapse: collapse;
                margin-top: 4px;
            }
            .terms-sign-box {
                width: 50%;
                border: 1px solid #000;
                height: 45px;
                padding: 4px 8px;
                vertical-align: bottom;
                font-size: 9px;
            }
            .sign-line {
                display: inline-block;
                border-bottom: 1px solid #000;
                min-width: 80px;
                margin-bottom: 2px;
            }
            .sign-line.short {
                min-width: 50px;
            }
            .terms-section-title {
                font-weight: bold;
                margin-top: 4px;
                margin-bottom: 2px;
            }
            .terms-text {
                margin: 0 0 2px 0;
                text-align: justify;
            }
        </style>
    </head>
    <body>
    <div class="form-wrapper">
        <table class="top-table">
            <tr>
                <td class="thumb-cell">
                    <div class="thumb-box small">
                        (Thumb Print)
                    </div>
                </td>
                <td class="header-center">
                    <?php if ($logoSrc): ?>
                    <img src="<?= htmlspecialchars($logoSrc) ?>" alt="RITT Logo" style="height:40px;"><br>
                    <?php endif; ?>
                    <div style="background:#dcdcdc;border:1px solid #000;padding:2px 10px;display:inline-block;margin-top:2px;">
                        USER REGISTRATION FORM
                    </div>
                </td>
                <td class="photo-cell">
                    <div class="photo-box small">
                        <?php if ($photoSrc): ?>
                            <img src="<?= htmlspecialchars($photoSrc) ?>" alt="Passport Photo" style="max-width:85px;max-height:80px;">
                        <?php else: ?>
                            (Verified<br>Passport Size<br>Photograph*)
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        </table>

        <table class="section-table small">
            <tr>
                <td style="width:25%;">Serial No:</td>
                <td class="value-line" style="width:25%;"><?= htmlspecialchars($serialNo) ?></td>
                <td style="width:20%;">Sales Order No:</td>
                <td class="value-line" style="width:30%;"><?= htmlspecialchars($serialNo) ?></td>
            </tr>
            <tr>
                <td>Date of Sales:</td>
                <td class="value-line"><?= htmlspecialchars($salesDate) ?></td>
                <td></td>
                <td></td>
            </tr>
        </table>

        <div class="checkbox-line">
            Subscription Type:
            <span class="box" style="<?= $isIndividual ? 'background:#000;' : '' ?>"></span> Individual
            <span class="box" style="<?= $isCorporate ? 'background:#000;' : '' ?>"></span> Corporate
            <span class="checkbox-gap"></span>
            Service Type:
            <span class="box"></span> E1
            <span class="box" style="background:#000;"></span> SIP
            <span class="box"></span> Others
        </div>
        <div class="checkbox-line">
            Solution Type:
            <span class="box" style="background:#000;"></span> SIP Trunk
            <span class="checkbox-gap"></span>
            Issued IP Phone:
            <?php foreach (str_split($didDigits) as $digit): ?>
                <span class="digit-box"><?= htmlspecialchars($digit) ?></span>
            <?php endforeach; ?>
        </div>

        <div class="section-title small">DID Information</div>
        <table class="section-table">
            <tr>
                <td class="label small">DID Number</td>
                <td class="value-line small"><?= htmlspecialchars($didNumber) ?></td>
                <td class="label small">No. of Channels</td>
                <td class="value-line small"><?= htmlspecialchars($channelCount) ?></td>
            </tr>
            <tr>
                <td class="label small">Trunk Password</td>
                <td class="value-line small" colspan="3"><?= htmlspecialchars($trunkPassword) ?></td>
            </tr>
        </table>

        <div class="section-title small">1. User Name: Individual/Organization</div>
        <table class="section-table">
            <tr>
                <td class="label small">(In Bangla)</td>
                <td class="value-line small"><?= htmlspecialchars($subscriber_name_bn) ?></td>
            </tr>
            <tr>
                <td class="label small">(In English)</td>
                <td class="value-line small"><?= htmlspecialchars($subscriber_name_en) ?></td>
            </tr>
        </table>

        <div class="section-title small">2. Authorized Person's Name (Applicable for Organization)</div>
        <table class="section-table">
            <tr>
                <td class="label small">(In Bangla)</td>
                <td class="value-line small"><?= htmlspecialchars($authorized_name_bn) ?></td>
            </tr>
            <tr>
                <td class="label small">(In English)</td>
                <td class="value-line small"><?= htmlspecialchars($authorized_name_en) ?></td>
            </tr>
        </table>

        <div class="section-title small">3. National ID No / Copy of Passport</div>
        <table class="section-table">
            <tr>
                <td class="label small">National ID No (Bangladeshi)</td>
                <td class="value-line small"><?= htmlspecialchars($national_id_no) ?></td>
            </tr>
            <tr>
                <td class="label small">Passport No (Non-Bangladeshi)</td>
                <td class="value-line small"><?= htmlspecialchars($passport_no) ?></td>
            </tr>
        </table>

        <div class="section-title small">4. Date of Birth &amp; Gender</div>
        <table class="section-table">
            <tr>
                <td class="label small">Date of Birth</td>
                <td class="value-line small"><?= htmlspecialchars($dob) ?></td>
                <td class="label small">Gender</td>
                <td class="value-line small"><?= htmlspecialchars(ucfirst($gender)) ?></td>
            </tr>
        </table>

        <div class="section-title small">5. Permanent Address</div>
        <table class="section-table">
            <tr>
                <td class="label small">Flat No</td>
                <td class="value-line small"><?= htmlspecialchars($perm_flat) ?></td>
                <td class="label small">House No</td>
                <td class="value-line small"><?= htmlspecialchars($perm_house) ?></td>
            </tr>
            <tr>
                <td class="label small">Road</td>
                <td class="value-line small"><?= htmlspecialchars($perm_road) ?></td>
                <td class="label small">Village / Area / Block</td>
                <td class="value-line small"><?= htmlspecialchars($perm_village) ?></td>
            </tr>
            <tr>
                <td class="label small">Upazila / Thana</td>
                <td class="value-line small"><?= htmlspecialchars(trim($perm_upazila . ' ' . $perm_thana)) ?></td>
                <td class="label small">District</td>
                <td class="value-line small"><?= htmlspecialchars($perm_district) ?></td>
            </tr>
            <tr>
                <td class="label small">Post Code</td>
                <td class="value-line small"><?= htmlspecialchars($perm_postcode) ?></td>
            </tr>
        </table>

        <div class="section-title small">6. Present Address</div>
        <table class="section-table">
            <tr>
                <td class="label small">Flat No</td>
                <td class="value-line small"><?= htmlspecialchars($pres_flat) ?></td>
                <td class="label small">House No</td>
                <td class="value-line small"><?= htmlspecialchars($pres_house) ?></td>
            </tr>
            <tr>
                <td class="label small">Road</td>
                <td class="value-line small"><?= htmlspecialchars($pres_road) ?></td>
                <td class="label small">Village / Area / Block</td>
                <td class="value-line small"><?= htmlspecialchars($pres_village) ?></td>
            </tr>
            <tr>
                <td class="label small">Upazila / Thana</td>
                <td class="value-line small"><?= htmlspecialchars(trim($pres_upazila . ' ' . $pres_thana)) ?></td>
                <td class="label small">District</td>
                <td class="value-line small"><?= htmlspecialchars($pres_district) ?></td>
            </tr>
            <tr>
                <td class="label small">Post Code</td>
                <td class="value-line small"><?= htmlspecialchars($pres_postcode) ?></td>
            </tr>
        </table>

        <div class="section-title small">7. Billing Address (For Postpaid only)</div>
        <p class="small">
            Billing Address: Present Address
        </p>

        <div class="section-title small">8. Contact No.</div>
        <table class="section-table">
            <tr>
                <td class="label small">Tel</td>
                <td class="value-line small"><?= htmlspecialchars($contact_tel) ?></td>
                <td class="label small">Mobile</td>
                <td class="value-line small"><?= htmlspecialchars($contact_mobile) ?></td>
            </tr>
            <tr>
                <td class="label small">E-mail</td>
                <td class="value-line small" colspan="3"><?= htmlspecialchars($contact_email) ?></td>
            </tr>
        </table>

        <div class="section-title small">9. Occupation</div>
        <p class="small">
            <?php
                $occLabel = $occupation;
                if ($occupation === 'others' && $occupation_other) {
                    $occLabel .= ' (' . $occupation_other . ')';
                }
                echo htmlspecialchars($occLabel);
            ?>
        </p>

        <div class="section-title small">10-12. Family Information</div>
        <table class="section-table">
            <tr>
                <td class="label small">Father's Name</td>
                <td class="value-line small"><?= htmlspecialchars($father_name) ?></td>
            </tr>
            <tr>
                <td class="label small">Mother's Name</td>
                <td class="value-line small"><?= htmlspecialchars($mother_name) ?></td>
            </tr>
            <tr>
                <td class="label small">Spouse's Name</td>
                <td class="value-line small"><?= htmlspecialchars($spouse_name) ?></td>
            </tr>
        </table>

        <div class="section-title small">13. Identifier</div>
        <table class="section-table">
            <tr>
                <td class="label small">Name &amp; Phone No.</td>
                <td class="value-line small"><?= htmlspecialchars(trim($identifier_name . ' / ' . $identifier_phone)) ?></td>
            </tr>
            <tr>
                <td class="label small">National ID No.</td>
                <td class="value-line small"><?= htmlspecialchars($identifier_nid) ?></td>
            </tr>
        </table>

        <div class="section-title small">14-15. Internet Connection</div>
        <p class="small">
            Internet Connection Required:
            <?= htmlspecialchars($internet_required === 'yes' ? 'Yes' : ($internet_required === 'no' ? 'No' : 'N/A')) ?>
        </p>

        <table class="section-table small">
            <tr>
                <td style="width:50%; border:1px solid #000; padding:4px 6px;">
                    <strong>Ranks ITT's Representative / Authorized Seller:</strong><br>
                    I have personally verified the attached photograph and required documents.
                    <br><br>
                    Signature with Seal:
                    <span class="value-line" style="display:inline-block; width:72%;"></span><br>
                    Name &amp; Address:
                    <span class="value-line" style="display:inline-block; width:68%;"></span><br>
                    Date:
                    <span class="value-line" style="display:inline-block; width:78%;"></span>
                </td>
                <td style="width:50%; border:1px solid #000; padding:4px 6px;">
                    <strong>User:</strong><br>
                    I do hereby declare that the information and data given above are correct. In case of any false
                    information, I shall be liable to appropriate legal action &amp; I shall abide by the overall terms &amp;
                    conditions.
                    <br><br>
                    Signature:
                    <span class="value-line" style="display:inline-block; width:55%; vertical-align:middle; position:relative;"></span>
                    Date: <?= date('d/m/Y') ?><br>
                    Organization Seal (if applicable):
                    <span class="value-line" style="display:inline-block; width:40%;"></span>
                </td>
            </tr>
        </table>

        <?php if ($termsHtml !== ''): ?>
            <?= $termsHtml ?>
        <?php endif; ?>
    </div>
    </body>
    </html>
    <?php
    $html = ob_get_clean();

    $options = new \Dompdf\Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $options->setChroot(__DIR__);
    if ($hasBanglaFont) {
        $options->setDefaultFont('Bangla');
    }

    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    return $dompdf->output();
}
