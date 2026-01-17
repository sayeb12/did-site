<?php
require_once __DIR__ . '/db.php';

// CHANGE THESE:
$adminUsername = "admin";
$adminPassword = "admin12345"; // change to strong password
$fullName = "System Admin";

$hash = password_hash($adminPassword, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("INSERT INTO admin_users (username, password_hash, full_name)
                       VALUES (:u, :p, :f)");
try {
  $stmt->execute([
    ':u' => $adminUsername,
    ':p' => $hash,
    ':f' => $fullName
  ]);
  echo "Admin created successfully. NOW DELETE create_admin.php";
} catch (PDOException $e) {
  echo "Failed: maybe username exists already.";
}
