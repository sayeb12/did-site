<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

if (!empty($_SESSION['admin_id'])) {
  header("Location: list.php");
  exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username'] ?? '');
  $password = $_POST['password'] ?? '';

  $stmt = $pdo->prepare("SELECT id, username, password_hash, full_name FROM admin_users WHERE username = :u LIMIT 1");
  $stmt->execute([':u' => $username]);
  $admin = $stmt->fetch();

  if ($admin && password_verify($password, $admin['password_hash'])) {
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int)$admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_full_name'] = $admin['full_name'] ?? '';
    header("Location: list.php");
    exit;
  } else {
    $error = "Invalid username or password.";
  }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Login</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <div class="container">
    <div class="header">
      <div class="h1">Admin Login</div>
      <div class="badge">Secure Access</div>
    </div>

    <div class="card">
      <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
        <div class="hr"></div>
      <?php endif; ?>

      <form method="post" action="login.php">
        <div class="grid">
          <div>
            <label>Username</label>
            <input name="username" required>
          </div>
          <div>
            <label>Password</label>
            <input type="password" name="password" required>
          </div>
        </div>

        <div class="actions">
          <button class="btn btn-primary" type="submit">Login</button>
        </div>

        <div class="small" style="margin-top:10px;">
          Tip: After you create admin, delete <b>create_admin.php</b>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
