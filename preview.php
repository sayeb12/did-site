<?php
require_once __DIR__ . '/auth.php';
require_login();

require_once __DIR__ . '/db.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(404); exit("Not found."); }

$stmt = $pdo->prepare("SELECT u.*, a.full_name as approver_name FROM users u LEFT JOIN admin_users a ON u.approved_by = a.id WHERE u.id = :id");
$stmt->execute([':id' => $id]);
$user = $stmt->fetch();

if (!$user) { http_response_code(404); exit("Not found."); }

// Check if viewer can see this record
$current_role = get_current_role();
if ($current_role === 'viewer' && !in_array($user['status'], ['approved', 'pending'])) {
    http_response_code(403);
    exit("Access denied. You can only view approved or pending records.");
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Preview - <?= htmlspecialchars($user['username']) ?></title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            margin-left: 10px;
        }
        .status-pending { background: rgba(255, 193, 7, 0.2); color: #ffc107; }
        .status-approved { background: rgba(40, 167, 69, 0.2); color: #28a745; }
        .status-rejected { background: rgba(220, 53, 69, 0.2); color: #dc3545; }
        .approval-info {
            background: rgba(255,255,255,.05);
            padding: 10px;
            border-radius: 10px;
            margin-top: 10px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="h1">User Preview
                <span class="status-badge status-<?= htmlspecialchars($user['status']) ?>">
                    <?= htmlspecialchars(ucfirst($user['status'])) ?>
                </span>
            </div>
            
            <div class="badge"><?= htmlspecialchars($user['username']) ?> • ID <?= (int)$user['id'] ?></div>
            <a class="btn btn-ghost" href="logout.php">Logout</a>
        </div>

        <div class="card">
            <?php if ($user['approved_by']): ?>
                <div class="approval-info">
                    <strong>Approval Info:</strong> 
                    <?= htmlspecialchars(ucfirst($user['status'])) ?> by <?= htmlspecialchars($user['approver_name']) ?> 
                    on <?= htmlspecialchars($user['approved_at']) ?>
                </div>
                <div class="hr"></div>
            <?php endif; ?>

            <div class="kv"><div class="k">Username</div><div class="v"><?= htmlspecialchars($user['username']) ?></div></div>
            <div class="kv"><div class="k">Full Name</div><div class="v"><?= htmlspecialchars($user['full_name']) ?></div></div>
            <div class="kv"><div class="k">Email</div><div class="v"><?= htmlspecialchars($user['email']) ?></div></div>
            <div class="kv"><div class="k">Phone</div><div class="v"><?= htmlspecialchars($user['phone']) ?></div></div>
            <div class="kv"><div class="k">Address</div><div class="v"><?= nl2br(htmlspecialchars($user['address'])) ?></div></div>
            <div class="kv"><div class="k">NID Assigned Number</div><div class="v"><?= htmlspecialchars($user['nid_assigned_number']) ?></div></div>
            <div class="kv"><div class="k">Status</div><div class="v">
                <span class="status-badge status-<?= htmlspecialchars($user['status']) ?>">
                    <?= htmlspecialchars(ucfirst($user['status'])) ?>
                </span>
            </div></div>

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
                <a class="btn btn-ghost" href="list.php">Back to List</a>
                <?php if (has_permission('add_user')): ?>
                    <a class="btn btn-ghost" href="index.php">Add Another</a>
                <?php endif; ?>
                <a class="btn btn-primary" href="generate_pdf.php?id=<?= (int)$user['id'] ?>">Generate PDF</a>
                <?php if (has_permission('edit_user')): ?>
                    <a class="btn btn-ghost" href="edit.php?id=<?= (int)$user['id'] ?>">Edit Record</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>