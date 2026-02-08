<?php
require_once __DIR__ . '/auth.php';
require_any_role(['admin', 'editor']); // Only admin and editor can edit

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

session_start();

// Check if ID is provided
if (!isset($_GET['id'])) {
    header('Location: list.php');
    exit;
}

$id = (int)$_GET['id'];

// Fetch user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: list.php?error=Record not found');
    exit;
}

function deleteUploadIfExists($relPath) {
    if (!$relPath) return;
    $full = __DIR__ . '/' . $relPath;
    if (is_file($full)) {
        @unlink($full);
    }
}

function saveUploadOptional($fileKey, array &$errors, $allowPdf = false) {
    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Upload failed: $fileKey";
        return null;
    }

    $tmp = $_FILES[$fileKey]['tmp_name'];
    $size = $_FILES[$fileKey]['size'];

    if ($size > 7 * 1024 * 1024) {
        $errors[] = "File too large: $fileKey";
        return null;
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
        $errors[] = "Invalid file type for $fileKey. Use $typeMsg.";
        return null;
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    $ext = $allowed[$mime];
    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    $dest = UPLOAD_DIR . $name;

    if (!move_uploaded_file($tmp, $dest)) {
        $errors[] = "Could not save uploaded file: $fileKey";
        return null;
    }

    return UPLOAD_URL . $name;
}

// Handle form submission for update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('CSRF token validation failed');
    }
    
    // Get form data
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
    
    // Validate required fields
    $errors = [];
    if (empty($username)) $errors[] = "Username is required";
    if (empty($full_name)) $errors[] = "Full name is required";
    if (empty($company_name)) $errors[] = "Company name is required";
    if (empty($did_number)) $errors[] = "DID number is required";
    if (empty($trunk_password)) $errors[] = "Trunk password is required";
    if ($channel_count === '') $errors[] = "No. of channels is required";
    if (empty($email)) $errors[] = "Email is required";
    if (empty($phone)) $errors[] = "Phone is required";
    if (empty($address)) $errors[] = "Address is required";
    $channel_count_int = filter_var($channel_count, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($channel_count !== '' && $channel_count_int === false) $errors[] = "No. of channels must be a positive number";
    
    // Check if username already exists (excluding current user)
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmt->execute([$username, $id]);
    if ($stmt->fetch()) {
        $errors[] = "Username already exists";
    }
    
    // Handle file uploads
    $nid_front_path = $user['nid_front_path'];
    $nid_back_path = $user['nid_back_path'];
    $personal_photo_path = $user['personal_photo_path'] ?? '';
    $trade_license_path = $user['trade_license_path'] ?? '';

    $new_front = saveUploadOptional('nid_front', $errors);
    if ($new_front) {
        deleteUploadIfExists($nid_front_path);
        $nid_front_path = $new_front;
    }

    $new_back = saveUploadOptional('nid_back', $errors);
    if ($new_back) {
        deleteUploadIfExists($nid_back_path);
        $nid_back_path = $new_back;
    }

    $new_photo = saveUploadOptional('personal_photo', $errors);
    if ($new_photo) {
        deleteUploadIfExists($personal_photo_path);
        $personal_photo_path = $new_photo;
    }

    $new_trade = saveUploadOptional('trade_license', $errors, true);
    if ($new_trade) {
        deleteUploadIfExists($trade_license_path);
        $trade_license_path = $new_trade;
    }
    
    // If no errors, update the record
    if (empty($errors)) {
        $stmt = $pdo->prepare("UPDATE users SET 
            username = ?, 
            full_name = ?, 
            company_name = ?,
            did_number = ?,
            trunk_password = ?,
            channel_count = ?,
            email = ?, 
            phone = ?, 
            address = ?, 
            nid_assigned_number = ?, 
            nid_front_path = ?, 
            nid_back_path = ?,
            personal_photo_path = ?,
            trade_license_path = ?,
            status = 'pending' -- Reset status when edited
            WHERE id = ?");
        
        $stmt->execute([
            $username,
            $full_name,
            $company_name,
            $did_number,
            $trunk_password,
            $channel_count_int,
            $email,
            $phone,
            $address,
            $nid_assigned_number,
            $nid_front_path,
            $nid_back_path,
            $personal_photo_path,
            $trade_license_path,
            $id
        ]);
        
        header('Location: list.php?updated=1');
        exit;
    }
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Edit Record</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 500; }
        input, textarea { width: 100%; padding: 10px; border-radius: 8px; border: 1px solid rgba(255,255,255,.15); background: rgba(255,255,255,.05); color: white; }
        textarea { min-height: 100px; resize: vertical; }
        .current-files { margin: 10px 0; padding: 10px; background: rgba(255,255,255,.05); border-radius: 8px; }
        .current-files img { max-width: 200px; margin: 5px; border: 1px solid rgba(255,255,255,.1); border-radius: 5px; }
        .file-note { font-size: 12px; color: var(--muted); margin-top: 5px; }
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            margin-left: 10px;
        }
        .status-pending { background: rgba(255, 193, 7, 0.2); color: #ffc107; }
        .status-approved { background: rgba(40, 167, 69, 0.2); color: #28a745; }
        .status-rejected { background: rgba(220, 53, 69, 0.2); color: #dc3545; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="h1">
                Edit Record: <?= htmlspecialchars($user['full_name']) ?>
                <span class="status-badge status-<?= htmlspecialchars($user['status']) ?>">
                    <?= htmlspecialchars(ucfirst($user['status'])) ?>
                </span>
            </div>
            <a class="btn btn-ghost" href="list.php">Back to List</a>
        </div>

        <div class="card">
            <?php if (!empty($errors)): ?>
                <div class="error">
                    <?php foreach ($errors as $error): ?>
                        <div><?= htmlspecialchars($error) ?></div>
                    <?php endforeach; ?>
                </div>
                <div class="hr"></div>
            <?php endif; ?>
            
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                
                <div class="form-group">
                    <label for="username">Username *</label>
                    <input type="text" id="username" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="full_name">Full Name *</label>
                    <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                </div>

                <div class="form-group">
                    <label for="company_name">Company Name *</label>
                    <input type="text" id="company_name" name="company_name" value="<?= htmlspecialchars($user['company_name'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label for="did_number">DID Number *</label>
                    <input type="text" id="did_number" name="did_number" value="<?= htmlspecialchars($user['did_number'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label for="trunk_password">Trunk Password *</label>
                    <input type="text" id="trunk_password" name="trunk_password" value="<?= htmlspecialchars($user['trunk_password'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label for="channel_count">No. of Channels *</label>
                    <input type="number" id="channel_count" name="channel_count" min="1" max="9999" value="<?= htmlspecialchars((string)($user['channel_count'] ?? '')) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone *</label>
                    <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($user['phone']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="address">Address *</label>
                    <textarea id="address" name="address" required><?= htmlspecialchars($user['address']) ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="nid_assigned_number">NID Assigned Number</label>
                    <input type="text" id="nid_assigned_number" name="nid_assigned_number" value="<?= htmlspecialchars($user['nid_assigned_number']) ?>">
                </div>

                <!-- Current Personal Photo -->
                <div class="current-files">
                    <strong>Current Personal Photo:</strong><br>
                    <?php if (!empty($user['personal_photo_path']) && is_file(__DIR__ . '/' . $user['personal_photo_path'])): ?>
                        <img src="<?= htmlspecialchars($user['personal_photo_path']) ?>" alt="Current Personal Photo">
                    <?php else: ?>
                        <div class="error">No personal photo uploaded.</div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="personal_photo">Update Personal Photo (Optional)</label>
                    <input type="file" id="personal_photo" name="personal_photo" accept="image/*">
                    <div class="file-note">Leave empty to keep current file</div>
                </div>

                <!-- Current Trade License -->
                <div class="current-files">
                    <strong>Current Trade License:</strong><br>
                    <?php if (!empty($user['trade_license_path']) && is_file(__DIR__ . '/' . $user['trade_license_path'])): ?>
                        <?php if (strtolower(pathinfo($user['trade_license_path'], PATHINFO_EXTENSION)) === 'pdf'): ?>
                            <a href="<?= htmlspecialchars($user['trade_license_path']) ?>" target="_blank">View Trade License (PDF)</a>
                        <?php else: ?>
                            <img src="<?= htmlspecialchars($user['trade_license_path']) ?>" alt="Current Trade License">
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="error">No trade license uploaded.</div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="trade_license">Update Trade License (Optional)</label>
                    <input type="file" id="trade_license" name="trade_license" accept="image/*,application/pdf">
                    <div class="file-note">Leave empty to keep current file</div>
                </div>
                
                <!-- Current NID Front -->
                <div class="current-files">
                    <strong>Current NID Front:</strong><br>
                    <?php if (!empty($user['nid_front_path']) && is_file(__DIR__ . '/' . $user['nid_front_path'])): ?>
                        <img src="<?= htmlspecialchars($user['nid_front_path']) ?>" alt="Current NID Front">
                    <?php else: ?>
                        <div class="error">File not found: <?= htmlspecialchars($user['nid_front_path']) ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="nid_front">Update NID Front (Optional)</label>
                    <input type="file" id="nid_front" name="nid_front" accept="image/*">
                    <div class="file-note">Leave empty to keep current file</div>
                </div>
                
                <!-- Current NID Back -->
                <div class="current-files">
                    <strong>Current NID Back:</strong><br>
                    <?php if (!empty($user['nid_back_path']) && is_file(__DIR__ . '/' . $user['nid_back_path'])): ?>
                        <img src="<?= htmlspecialchars($user['nid_back_path']) ?>" alt="Current NID Back">
                    <?php else: ?>
                        <div class="error">File not found: <?= htmlspecialchars($user['nid_back_path']) ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="nid_back">Update NID Back (Optional)</label>
                    <input type="file" id="nid_back" name="nid_back" accept="image/*">
                    <div class="file-note">Leave empty to keep current file</div>
                </div>
                
                <div class="hr"></div>
                
                <div style="display:flex; gap:10px;">
                    <button class="btn btn-primary" type="submit">Update Record</button>
                    <a class="btn btn-ghost" href="list.php">Cancel</a>
                    <a class="btn btn-ghost" href="preview.php?id=<?= $id ?>">Preview</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
