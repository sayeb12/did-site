<?php
// index.php
require_once __DIR__ . '/auth.php';
require_login();

?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>DID Plan Data Store</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <div class="container">
    <div class="header">
      <div class="h1">DID Plan User Data</div>
      <div class="badge">PHP + MySQL • Upload + Preview + PDF</div>
    </div>

    <div class="card">
      <form action="submit.php" method="post" enctype="multipart/form-data">
        <div class="grid">
          <div>
            <label>Username</label>
            <input name="username" required maxlength="50" placeholder="e.g. dialdynamic01">
          </div>
          <div>
            <label>Full Name</label>
            <input name="full_name" required maxlength="120" placeholder="e.g. S M Mohibullah Apon">
          </div>
          <div>
            <label>Email</label>
            <input type="email" name="email" required maxlength="120" placeholder="e.g. user@email.com">
          </div>
          <div>
            <label>Phone Number</label>
            <input name="phone" required maxlength="30" placeholder="e.g. +8801XXXXXXXXX">
          </div>
          <div style="grid-column:1 / -1">
            <label>Address</label>
            <textarea name="address" required placeholder="Full address..."></textarea>
          </div>
          <div style="grid-column:1 / -1">
            <label>NID Assigned Number</label>
            <input name="nid_assigned_number" required maxlength="80" placeholder="e.g. NID-00012345">
          </div>

          <div>
            <label>NID Front (JPG/PNG/WebP)</label>
            <input type="file" name="nid_front" accept="image/*" required
                   onchange="previewImage(this,'frontPrev')">
            <div class="small">Max ~5MB recommended</div>
          </div>
          <div>
            <label>NID Back (JPG/PNG/WebP)</label>
            <input type="file" name="nid_back" accept="image/*" required
                   onchange="previewImage(this,'backPrev')">
            <div class="small">Max ~5MB recommended</div>
          </div>

          <div>
            <img id="frontPrev" class="previewimg" alt="Front preview">
          </div>
          <div>
            <img id="backPrev" class="previewimg" alt="Back preview">
          </div>
        </div>

        <div class="actions">
          <button class="btn btn-primary" type="submit">Save & Preview</button>
          <button class="btn btn-ghost" type="reset">Reset</button>
          <a class="btn btn-ghost" href="list.php">View Records</a>
          <a class="btn btn-ghost" href="logout.php">Logout</a>

        </div>
      </form>
    </div>
  </div>

  <script src="app.js"></script>
</body>
</html>
