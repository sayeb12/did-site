<?php
require_once __DIR__ . '/auth.php';
require_login();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

function fail($msg) {
  http_response_code(400);
  echo '<link rel="stylesheet" href="styles.css"><div class="container"><div class="card"><div class="error">'
    . htmlspecialchars($msg) . '</div><div class="hr"></div><a href="index.php">Back</a></div></div>';
  exit;
}

function saveUpload($fileKey, array $options = []) {
  $required = $options['required'] ?? true;
  $allowPdf = $options['allow_pdf'] ?? false;
  $maxSize = $options['max_size'] ?? 7 * 1024 * 1024;

  if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] === UPLOAD_ERR_NO_FILE) {
    if ($required) {
      fail("Upload required: $fileKey");
    }
    return null;
  }

  if ($_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
    fail("Upload failed: $fileKey");
  }

  $tmp = $_FILES[$fileKey]['tmp_name'];
  $size = $_FILES[$fileKey]['size'];

  if ($size > $maxSize) {
    fail("File too large: $fileKey");
  }

  $finfo = new finfo(FILEINFO_MIME_TYPE);
  $mime = $finfo->file($tmp);

  $allowed = [
    'image/jpeg' => 'jpg',
    'image/jpg'  => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp'
  ];
  if ($allowPdf) {
    $allowed['application/pdf'] = 'pdf';
  }

  if (!isset($allowed[$mime])) {
    $typeMsg = $allowPdf ? 'JPG/PNG/WebP/PDF' : 'JPG/PNG/WebP';
    fail("Invalid file type for $fileKey. Use $typeMsg.");
  }

  if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
  }

  $ext = $allowed[$mime];
  $name = bin2hex(random_bytes(16)) . '.' . $ext;
  $dest = UPLOAD_DIR . $name;

  if (!move_uploaded_file($tmp, $dest)) {
    fail("Could not save uploaded file: $fileKey");
  }

  return UPLOAD_URL . $name; // store relative path in DB
}

$username = trim($_POST['username'] ?? '');
$full_name = trim($_POST['full_name'] ?? '');
$company_name = trim($_POST['company_name'] ?? '');
$did_number = trim($_POST['did_number'] ?? '');
$trunk_password = trim($_POST['trunk_password'] ?? '');
$channel_count = trim($_POST['channel_count'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$address = trim($_POST['address'] ?? '');
$nid_assigned_number = trim($_POST['nid_assigned_number'] ?? '');

if ($username === '' || $full_name === '' || $company_name === '' || $did_number === '' || $trunk_password === '' ||
    $channel_count === '' || $email === '' || $phone === '' || $address === '' || $nid_assigned_number === '') {
  fail("All fields are required.");
}

$channel_count_int = filter_var($channel_count, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($channel_count_int === false) {
  fail("No. of channels must be a positive number.");
}

$nid_front_path = saveUpload('nid_front');
$nid_back_path  = saveUpload('nid_back');
$personal_photo_path = saveUpload('personal_photo');
$trade_license_path = saveUpload('trade_license', ['required' => false, 'allow_pdf' => true]);

try {
  $stmt = $pdo->prepare("
    INSERT INTO users (
      username, full_name, company_name, did_number, trunk_password, channel_count,
      email, phone, address, nid_assigned_number, nid_front_path, nid_back_path,
      personal_photo_path, trade_license_path
    )
    VALUES (
      :username, :full_name, :company_name, :did_number, :trunk_password, :channel_count,
      :email, :phone, :address, :nid, :front, :back, :personal_photo, :trade_license
    )
  ");
  $stmt->execute([
    ':username' => $username,
    ':full_name' => $full_name,
    ':company_name' => $company_name,
    ':did_number' => $did_number,
    ':trunk_password' => $trunk_password,
    ':channel_count' => $channel_count_int,
    ':email' => $email,
    ':phone' => $phone,
    ':address' => $address,
    ':nid' => $nid_assigned_number,
    ':front' => $nid_front_path,
    ':back' => $nid_back_path,
    ':personal_photo' => $personal_photo_path,
    ':trade_license' => $trade_license_path
  ]);

  $id = (int)$pdo->lastInsertId();
  header("Location: preview.php?id=" . $id);
  exit;

} catch (PDOException $e) {
  // If username duplicate etc.
  fail("Could not save user. (Maybe username already exists.)");
}
