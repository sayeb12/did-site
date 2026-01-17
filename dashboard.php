<?php
require_once __DIR__ . '/auth.php';
require_login();

require_once __DIR__ . '/db.php';

$current_role = get_current_role();

// Get counts for dashboard
$count_sql = "SELECT status, COUNT(*) as count FROM users GROUP BY status";
$count_stmt = $pdo->query($count_sql);
$counts = $count_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Get recent records
$recent_sql = "SELECT * FROM users ORDER BY created_at DESC LIMIT 10";
$recent_stmt = $pdo->query($recent_sql);
$recent_records = $recent_stmt->fetchAll();
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Dashboard - DID Plan</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: rgba(15, 26, 51, .88);
            border: 1px solid rgba(255,255,255,.1);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
        }
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: var(--accent);
        }
        .stat-label {
            font-size: 14px;
            color: var(--muted);
            margin-top: 5px;
        }
        .quick-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .recent-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .recent-table th, .recent-table td {
            padding: 10px;
            border-bottom: 1px solid rgba(255,255,255,.1);
            text-align: left;
        }
        .role-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            margin-left: 10px;
        }
        .role-admin { background: #dc3545; color: white; }
        .role-editor { background: #ffc107; color: #212529; }
        .role-viewer { background: #28a745; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="h1">Dashboard</div>
            <div style="display:flex; gap:10px; align-items:center;">
                <span class="role-badge role-<?= $current_role ?>">
                    <?= ucfirst($current_role) ?>
                </span>
                <a class="btn btn-ghost" href="list.php">View All Records</a>
                <a class="btn btn-ghost" href="logout.php">Logout</a>
            </div>
        </div>

        <div class="card">
            <h2 style="margin-top:0;">Welcome, <?= htmlspecialchars($_SESSION['admin_full_name'] ?? $_SESSION['admin_username']) ?></h2>
            
            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-number"><?= $counts['pending'] ?? 0 ?></div>
                    <div class="stat-label">Pending Records</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $counts['approved'] ?? 0 ?></div>
                    <div class="stat-label">Approved Records</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= $counts['rejected'] ?? 0 ?></div>
                    <div class="stat-label">Rejected Records</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= array_sum($counts) ?></div>
                    <div class="stat-label">Total Records</div>
                </div>
            </div>

            <div class="quick-actions">
                <?php if (has_permission('add_user')): ?>
                    <a class="btn btn-primary" href="index.php">Add New Record</a>
                <?php endif; ?>
                <a class="btn btn-ghost" href="list.php?status=pending">View Pending</a>
                <a class="btn btn-ghost" href="list.php?status=approved">View Approved</a>
                <?php if (has_permission('all') || $current_role === 'admin'): ?>
                    <a class="btn btn-ghost" href="admin_users.php">Manage Users</a>
                <?php endif; ?>
            </div>

            <div class="hr"></div>

            <h3>Recent Records</h3>
            <?php if (count($recent_records) > 0): ?>
                <div style="overflow:auto;">
                    <table class="recent-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_records as $record): ?>
                                <tr>
                                    <td><?= $record['id'] ?></td>
                                    <td><?= htmlspecialchars($record['username']) ?></td>
                                    <td><?= htmlspecialchars($record['full_name']) ?></td>
                                    <td>
                                        <?php if ($record['status'] === 'pending'): ?>
                                            <span style="color:#ffc107;">● Pending</span>
                                        <?php elseif ($record['status'] === 'approved'): ?>
                                            <span style="color:#28a745;">● Approved</span>
                                        <?php else: ?>
                                            <span style="color:#dc3545;">● Rejected</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $record['created_at'] ?></td>
                                    <td>
                                        <a class="btnmini pdf-btn" href="preview.php?id=<?= $record['id'] ?>">View</a>
                                        <?php if (has_permission('edit_user')): ?>
                                            <a class="btnmini edit-btn" href="edit.php?id=<?= $record['id'] ?>">Edit</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="error">No records found.</div>
            <?php endif; ?>

            <div class="hr"></div>

            <div class="small">
                <strong>Your Permissions:</strong><br>
                <?php if ($current_role === 'admin'): ?>
                    • Full access to all features<br>
                    • User management<br>
                    • Approve/Reject records<br>
                    • Add/Edit/Delete records
                <?php elseif ($current_role === 'editor'): ?>
                    • Add new records<br>
                    • Edit existing records<br>
                    • View all records<br>
                    • Generate PDFs
                <?php else: ?>
                    • View approved/pending records<br>
                    • Approve/Reject records<br>
                    • Generate PDFs<br>
                    • Cannot add/edit/delete records
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>