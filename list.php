<?php
require_once __DIR__ . '/auth.php';
require_login();

require_once __DIR__ . '/db.php';


if (!isset($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

$q = trim($_GET['q'] ?? '');

// Search across: record id, username, full name, email, phone, nid assigned number
$params = [];
$whereSql = "";

if ($q !== '') {
  $whereSql = "WHERE
      CAST(id AS CHAR) LIKE :q
      OR username LIKE :q
      OR full_name LIKE :q
      OR email LIKE :q
      OR phone LIKE :q
      OR nid_assigned_number LIKE :q
  ";
  $params[':q'] = '%' . $q . '%';
}

$sql = "SELECT id, username, full_name, email, phone, nid_assigned_number, created_at
        FROM users
        $whereSql
        ORDER BY id DESC
        LIMIT 300";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Records List</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    table { width:100%; border-collapse: collapse; overflow:hidden; border-radius:14px; }
    th, td { padding: 10px 10px; border-bottom: 1px solid rgba(255,255,255,.10); text-align:left; font-size: 13px; }
    th { color: var(--muted); font-size: 12px; font-weight: 700; background: rgba(255,255,255,.04); }
    tr:hover td { background: rgba(90,167,255,.08); }
    .topbar { display:flex; gap:10px; flex-wrap:wrap; align-items:center; justify-content:space-between; }
    .searchbox { display:flex; gap:10px; flex-wrap:wrap; align-items:center; width: 100%; }
    .searchbox input { flex:1; min-width: 220px; }
    .pill { font-size:12px; color: var(--muted); }
    .btnmini { padding:7px 10px; border-radius:10px; display:inline-block; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <div class="h1">All Saved Records</div>
      <div class="badge">Search • Click row to preview • PDF • Delete</div>
    </div>

    <div class="card">
      <div class="topbar">
        <form class="searchbox" method="get" action="list.php">
          <input
            name="q"
            value="<?= htmlspecialchars($q) ?>"
            placeholder="Search by Sender ID (record id/username), email, name, phone, or NID assigned number..."
          >
          <button class="btn btn-primary" type="submit">Search</button>
          <a class="btn btn-ghost" href="list.php">Clear</a>
          <a class="btn btn-ghost" href="index.php">Add New</a>
        </form>

        <div class="pill">Showing <?= count($rows) ?> record(s)</div>
      </div>

      <div class="hr"></div>

      <?php if (isset($_GET['deleted'])): ?>
        <div class="success">Record deleted successfully.</div>
        <div class="hr"></div>
      <?php endif; ?>

      <?php if (count($rows) === 0): ?>
        <div class="error">No records found.</div>
      <?php else: ?>
        <div style="overflow:auto; border-radius:14px; border:1px solid rgba(255,255,255,.10);">
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Full Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>NID No.</th>
                <th>Created</th>
                <th>PDF</th>
                <th>Delete</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td>
                    <a href="preview.php?id=<?= (int)$r['id'] ?>"><?= (int)$r['id'] ?></a>
                  </td>

                  <td>
                    <a href="preview.php?id=<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['username']) ?></a>
                  </td>

                  <td>
                    <a href="preview.php?id=<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['full_name']) ?></a>
                  </td>

                  <td><?= htmlspecialchars($r['email']) ?></td>
                  <td><?= htmlspecialchars($r['phone']) ?></td>
                  <td><?= htmlspecialchars($r['nid_assigned_number']) ?></td>
                  <td><?= htmlspecialchars($r['created_at']) ?></td>

                  <td>
                    <a class="btn btn-ghost btnmini" href="generate_pdf.php?id=<?= (int)$r['id'] ?>">
                      PDF
                    </a>
                  </td>

                  <td>
                    <form method="post" action="delete.php"
                          onsubmit="return confirm('Delete this record permanently?');"
                          style="display:inline;">
                      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                      <input type="hidden" name="token" value="<?= htmlspecialchars($csrf) ?>">
                      <button class="btn btn-danger btnmini" type="submit">
                        Delete
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="small" style="margin-top:10px;">
          Tip: click <b>ID / Username / Full Name</b> to open Preview.
        </div>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
