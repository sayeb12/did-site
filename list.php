<?php
require_once __DIR__ . '/auth.php';
require_login();

require_once __DIR__ . '/db.php';

session_start();
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

$q = trim($_GET['q'] ?? '');
$status_filter = $_GET['status'] ?? 'all';
$current_role = get_current_role();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('CSRF token validation failed');
    }
    
    $id = (int)($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? 'pending';
    
    if ($id > 0 && in_array($status, ['pending', 'approved', 'rejected'])) {
        // Only admin and viewer can update status
        if (has_permission('approve_user')) {
            $stmt = $pdo->prepare("UPDATE users SET status = ?, approved_by = ?, approved_at = NOW() WHERE id = ?");
            $stmt->execute([$status, $_SESSION['admin_id'], $id]);
        }
    }
    
    // Redirect to avoid form resubmission
    header("Location: list.php?" . http_build_query($_GET));
    exit;
}

// Build query with filters
$params = [];
$whereSql = "WHERE 1=1";

if ($q !== '') {
    $whereSql .= " AND (
        CAST(id AS CHAR) LIKE :q
        OR username LIKE :q
        OR full_name LIKE :q
        OR email LIKE :q
        OR phone LIKE :q
        OR nid_assigned_number LIKE :q
    )";
    $params[':q'] = '%' . $q . '%';
}

if ($status_filter !== 'all') {
    $whereSql .= " AND status = :status";
    $params[':status'] = $status_filter;
}

// Viewer can only see approved records by default
if ($current_role === 'viewer' && $status_filter === 'all') {
    $whereSql .= " AND status IN ('approved', 'pending')";
}

$sql = "SELECT u.*, 
               a.full_name as approver_name 
        FROM users u
        LEFT JOIN admin_users a ON u.approved_by = a.id
        $whereSql
        ORDER BY u.created_at DESC
        LIMIT 300";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Get counts for status filter
$count_sql = "SELECT status, COUNT(*) as count FROM users GROUP BY status";
$count_stmt = $pdo->query($count_sql);
$counts = $count_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$total_count = array_sum($counts);
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
        .btnmini { 
            padding:7px 10px; 
            border-radius:10px; 
            display:inline-block; 
            text-decoration: none;
            font-size: 12px;
            cursor: pointer;
            border: 1px solid rgba(255,255,255,.2);
            background: rgba(255,255,255,.1);
            color: white;
        }
        .btnmini:hover {
            background: rgba(255,255,255,.15);
        }
        .btnmini.btn-danger {
            background: rgba(220, 53, 69, 0.8);
            border: 1px solid rgba(220, 53, 69, 0.9);
        }
        .btnmini.btn-danger:hover {
            background: rgba(220, 53, 69, 1);
        }
        .action-buttons {
            display: flex;
            gap: 5px;
            align-items: center;
            flex-wrap: wrap;
        }
        .btnmini.pdf-btn {
            background: rgba(40, 167, 69, 0.8);
            border: 1px solid rgba(40, 167, 69, 0.9);
        }
        .btnmini.pdf-btn:hover {
            background: rgba(40, 167, 69, 1);
        }
        .btnmini.edit-btn {
            background: rgba(255, 193, 7, 0.8);
            border: 1px solid rgba(255, 193, 7, 0.9);
            color: #212529;
        }
        .btnmini.edit-btn:hover {
            background: rgba(255, 193, 7, 1);
        }
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-pending { background: rgba(255, 193, 7, 0.2); color: #ffc107; }
        .status-approved { background: rgba(40, 167, 69, 0.2); color: #28a745; }
        .status-rejected { background: rgba(220, 53, 69, 0.2); color: #dc3545; }
        .status-filter {
            display: flex;
            gap: 5px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        .status-filter a {
            padding: 5px 10px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 12px;
            background: rgba(255,255,255,.05);
            border: 1px solid rgba(255,255,255,.1);
        }
        .status-filter a.active {
            background: var(--accent);
            color: #061022;
        }
        select {
            padding: 5px 10px;
            border-radius: 8px;
            background: rgba(10,16,33,.55);
            color: white;
            border: 1px solid rgba(255,255,255,.14);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="h1">All Saved Records</div>
            <div style="display:flex; gap:10px; align-items:center;">
                <span class="badge">Role: <?= htmlspecialchars(ucfirst($current_role)) ?></span>
                <?php if (has_permission('all') || $current_role === 'admin'): ?>
                    <a class="btn btn-ghost" href="admin_users.php">Manage Users</a>
                <?php endif; ?>
                <?php if (has_permission('add_user')): ?>
                    <a class="btn btn-ghost" href="index.php">Add New</a>
                <?php endif; ?>
                <a class="btn btn-ghost" href="logout.php">Logout</a>
            </div>
        </div>

        <div class="card">
            <div class="topbar">
                <form class="searchbox" method="get" action="list.php">
                    <input
                        name="q"
                        value="<?= htmlspecialchars($q) ?>"
                        placeholder="Search by record id, username, name, email, phone, or NID number..."
                    >
                    <select name="status" onchange="this.form.submit()">
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status (<?= $total_count ?>)</option>
                        <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending (<?= $counts['pending'] ?? 0 ?>)</option>
                        <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Approved (<?= $counts['approved'] ?? 0 ?>)</option>
                        <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Rejected (<?= $counts['rejected'] ?? 0 ?>)</option>
                    </select>
                    <button class="btn btn-primary" type="submit">Search</button>
                    <a class="btn btn-ghost" href="list.php">Clear</a>
                </form>

                <div class="pill">Showing <?= count($rows) ?> record(s)</div>
            </div>

            <div class="hr"></div>

            <?php if (isset($_GET['deleted'])): ?>
                <div class="success">Record deleted successfully.</div>
                <div class="hr"></div>
            <?php endif; ?>

            <?php if (isset($_GET['updated'])): ?>
                <div class="success">Record updated successfully.</div>
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
                                <th>Status</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>NID No.</th>
                                <th>Created</th>
                                <th>Actions</th>
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
                                    
                                    <td>
                                        <span class="status-badge status-<?= htmlspecialchars($r['status']) ?>">
                                            <?= htmlspecialchars(ucfirst($r['status'])) ?>
                                        </span>
                                        <?php if ($r['approved_by']): ?>
                                            <div class="small">by <?= htmlspecialchars($r['approver_name'] ?? 'Unknown') ?></div>
                                        <?php endif; ?>
                                    </td>

                                    <td><?= htmlspecialchars($r['email']) ?></td>
                                    <td><?= htmlspecialchars($r['phone']) ?></td>
                                    <td><?= htmlspecialchars($r['nid_assigned_number']) ?></td>
                                    <td><?= htmlspecialchars($r['created_at']) ?></td>

                                    <td>
                                        <div class="action-buttons">
                                            <a class="btnmini pdf-btn" href="generate_pdf.php?id=<?= (int)$r['id'] ?>" title="Generate PDF">
                                                PDF
                                            </a>
                                            
                                            <?php if (has_permission('edit_user')): ?>
                                                <a class="btnmini edit-btn" href="edit.php?id=<?= (int)$r['id'] ?>" title="Edit Record">
                                                    Edit
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if (has_permission('all') || $current_role === 'admin'): ?>
                                                <form method="post" action="delete.php"
                                                      onsubmit="return confirm('Delete this record permanently?');"
                                                      style="display:inline;">
                                                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                                    <input type="hidden" name="token" value="<?= htmlspecialchars($csrf) ?>">
                                                    <button class="btnmini btn-danger" type="submit" title="Delete Record">
                                                        Delete
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php if (has_permission('approve_user') && in_array($current_role, ['admin', 'viewer'])): ?>
                                                <form method="post" style="display:inline;" onsubmit="return confirm('Change status?');">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                                    <input type="hidden" name="update_status" value="1">
                                                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                                    <select name="status" onchange="this.form.submit()" style="font-size:11px; padding:3px;">
                                                        <option value="pending" <?= $r['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                        <option value="approved" <?= $r['status'] === 'approved' ? 'selected' : '' ?>>Approve</option>
                                                        <option value="rejected" <?= $r['status'] === 'rejected' ? 'selected' : '' ?>>Reject</option>
                                                    </select>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="small" style="margin-top:10px;">
                    Tip: click <b>ID / Username / Full Name</b> to open Preview.
                    <?php if ($current_role === 'viewer'): ?>
                        <br>Viewers can only approve/reject records and view approved/pending records.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Add confirmation for delete action
        document.addEventListener('DOMContentLoaded', function() {
            const deleteForms = document.querySelectorAll('form[action="delete.php"]');
            deleteForms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    if (!confirm('Are you sure you want to delete this record? This action cannot be undone.')) {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>
</body>
</html>