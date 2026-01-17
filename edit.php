<?php
require_once __DIR__ . '/auth.php';
require_login();

require_once __DIR__ . '/db.php';

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

// Handle form submission for update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('CSRF token validation failed');
    }
    
    // Get form data
    $username = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $nid_assigned_number = trim($_POST['nid_assigned_number'] ?? '');
    
    // Validate required fields
    $errors = [];
    if (empty($username)) $errors[] = "Username is required";
    if (empty($full_name)) $errors[] = "Full name is required";
    if (empty($email)) $errors[] = "Email is required";
    if (empty($phone)) $errors[] = "Phone is required";
    if (empty($address)) $errors[] = "Address is required";
    
    // Check if username already exists (excluding current user)
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmt->execute([$username, $id]);
    if ($stmt->fetch()) {
        $errors[] = "Username already exists";
    }
    
    // Handle file uploads
    $nid_front_path = $user['nid_front_path'];
    $nid_back_path = $user['nid_back_path'];
    
    if (isset($_FILES['nid_front']) && $_FILES['nid_front']['error'] === UPLOAD_ERR_OK) {
        // Delete old file
        if (file_exists($nid_front_path)) {
            unlink($nid_front_path);
        }
        
        $upload_dir = 'uploads/';
        $file_name = uniqid() . '_' . basename($_FILES['nid_front']['name']);
        $target_file = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['nid_front']['tmp_name'], $target_file)) {
            $nid_front_path = $target_file;
        }
    }
    
    if (isset($_FILES['nid_back']) && $_FILES['nid_back']['error'] === UPLOAD_ERR_OK) {
        // Delete old file
        if (file_exists($nid_back_path)) {
            unlink($nid_back_path);
        }
        
        $upload_dir = 'uploads/';
        $file_name = uniqid() . '_' . basename($_FILES['nid_back']['name']);
        $target_file = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['nid_back']['tmp_name'], $target_file)) {
            $nid_back_path = $target_file;
        }
    }
    
    // If no errors, update the record
    if (empty($errors)) {
        $stmt = $pdo->prepare("UPDATE users SET 
            username = ?, 
            full_name = ?, 
            email = ?, 
            phone = ?, 
            address = ?, 
            nid_assigned_number = ?, 
            nid_front_path = ?, 
            nid_back_path = ?
            WHERE id = ?");
        
        $stmt->execute([
            $username,
            $full_name,
            $email,
            $phone,
            $address,
            $nid_assigned_number,
            $nid_front_path,
            $nid_back_path,
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
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <div class="h1">Edit Record: <?= htmlspecialchars($user['full_name']) ?></div>
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
        
        <!-- Current NID Front -->
        <div class="current-files">
          <strong>Current NID Front:</strong><br>
          <?php if (file_exists($user['nid_front_path'])): ?>
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
          <?php if (file_exists($user['nid_back_path'])): ?>
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