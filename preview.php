<?php
require_once __DIR__ . '/auth.php';
require_login();

require_once __DIR__ . '/db.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(404); exit("Not found."); }

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute([':id' => $id]);
$user = $stmt->fetch();

if (!$user) { http_response_code(404); exit("Not found."); }
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Preview - <?= htmlspecialchars($user['username']) ?></title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <div class="container">
    <div class="header">
      <div class="h1">User Preview</div>
      <div class="badge"><?= htmlspecialchars($user['username']) ?> • ID <?= (int)$user['id'] ?></div>
    </div>

    <div class="card">
      <div class="kv"><div class="k">Username</div><div class="v"><?= htmlspecialchars($user['username']) ?></div></div>
      <div class="kv"><div class="k">Full Name</div><div class="v"><?= htmlspecialchars($user['full_name']) ?></div></div>
      <div class="kv"><div class="k">Email</div><div class="v"><?= htmlspecialchars($user['email']) ?></div></div>
      <div class="kv"><div class="k">Phone</div><div class="v"><?= htmlspecialchars($user['phone']) ?></div></div>
      <div class="kv"><div class="k">Address</div><div class="v"><?= nl2br(htmlspecialchars($user['address'])) ?></div></div>
      <div class="kv"><div class="k">NID Assigned Number</div><div class="v"><?= htmlspecialchars($user['nid_assigned_number']) ?></div></div>

      <div class="hr"></div>

      <div class="imgrow">
        <div>
          <div class="small">NID Front</div>
          <img class="previewimg" src="<?= htmlspecialchars($user['nid_front_path']) ?>" alt="NID Front">
          <div class="small"><a href="<?= htmlspecialchars($user['nid_front_path']) ?>" download>Download front</a></div>
        </div>
        <div>
          <div class="small">NID Back</div>
          <img class="previewimg" src="<?= htmlspecialchars($user['nid_back_path']) ?>" alt="NID Back">
          <div class="small"><a href="<?= htmlspecialchars($user['nid_back_path']) ?>" download>Download back</a></div>
        </div>
      </div>

      <div class="actions">
        <a class="btn btn-ghost" href="index.php">Add Another</a>
         <a class="btn btn-ghost" href="list.php">Back to List</a>
  <a class="btn btn-ghost" href="index.php">Add Another</a>
  <a class="btn btn-primary" href="generate_pdf.php?id=<?= (int)$user['id'] ?>">Generate PDF</a>
      </div>
    </div>
  </div>
</body>
</html>
