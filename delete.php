<?php
require_once __DIR__ . '/auth.php';
require_login();

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit("Method not allowed");
}

$id = (int)($_POST['id'] ?? 0);
$token = $_POST['token'] ?? '';

if ($id <= 0) {
  http_response_code(400);
  exit("Invalid ID");
}

// Simple CSRF token check (stored in session)
session_start();
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
  http_response_code(403);
  exit("Invalid token");
}

// Get file paths so we can delete uploaded images too
$stmt = $pdo->prepare("SELECT nid_front_path, nid_back_path FROM users WHERE id=:id");
$stmt->execute([':id' => $id]);
$row = $stmt->fetch();

if ($row) {
  // Delete DB record
  $del = $pdo->prepare("DELETE FROM users WHERE id=:id");
  $del->execute([':id' => $id]);

  // Delete image files from disk (if they exist)
  foreach (['nid_front_path','nid_back_path'] as $k) {
    $rel = $row[$k] ?? '';
    if ($rel) {
      $full = __DIR__ . '/' . $rel;
      if (is_file($full)) @unlink($full);
    }
  }
}

header("Location: list.php?deleted=1");
exit;
