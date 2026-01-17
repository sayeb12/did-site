<?php
// auth.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
  // more secure session defaults
  ini_set('session.cookie_httponly', '1');
  // If you use HTTPS in production, enable this:
  // ini_set('session.cookie_secure', '1');
  ini_set('session.use_strict_mode', '1');
  session_start();
}

function require_login(): void {
  if (empty($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
  }
}

function csrf_token(): string {
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token'];
}
