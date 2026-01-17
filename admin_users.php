<?php
require_once __DIR__ . '/auth.php';
require_role('admin');

require_once __DIR__ . '/db.php';

session_start();
$csrf = csrf_token();

$message = '';
$error = '';

// Handle form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Invalid CSRF token";
    } else {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'add':
                $username = trim($_POST['username'] ?? '');
                $password = $_POST['password'] ?? '';
                $full_name = trim($_POST['full_name'] ?? '');
                $role = $_POST['role'] ?? 'viewer';
                
                if (empty($username) || empty($password)) {
                    $error = "Username and password are required";
                } elseif (strlen($password) < 6) {
                    $error = "Password must be at least 6 characters";
                } else {
                    // Check if username exists
                    $check = $pdo->prepare("SELECT id FROM admin_users WHERE username = ?");
                    $check->execute([$username]);
                    if ($check->fetch()) {
                        $error = "Username already exists";
                    } else {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO admin_users (username, password_hash, full_name, role) VALUES (?, ?, ?, ?)");
                        if ($stmt->execute([$username, $hash, $full_name, $role])) {
                            $message = "User created successfully";
                        } else {
                            $error = "Failed to create user";
                        }
                    }
                }
                break;
                
            case 'edit':
                $id = (int)($_POST['id'] ?? 0);
                $username = trim($_POST['username'] ?? '');
                $full_name = trim($_POST['full_name'] ?? '');
                $role = $_POST['role'] ?? 'viewer';
                $password = $_POST['password'] ?? '';
                
                if ($id <= 0) {
                    $error = "Invalid user ID";
                } else {
                    $params = [$username, $full_name, $role, $id];
                    $sql = "UPDATE admin_users SET username = ?, full_name = ?, role = ? WHERE id = ?";
                    
                    if (!empty($password)) {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $sql = "UPDATE admin_users SET username = ?, full_name = ?, role = ?, password_hash = ? WHERE id = ?";
                        $params = [$username, $full_name, $role, $hash, $id];
                    }
                    
                    $stmt = $pdo->prepare($sql);
                    if ($stmt->execute($params)) {
                        $message = "User updated successfully";
                    } else {
                        $error = "Failed to update user";
                    }
                }
                break;
                
            case 'delete':
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) {
                    $error = "Invalid user ID";
                } else {
                    // Don't allow deleting your own account
                    if ($id == $_SESSION['admin_id']) {
                        $error = "You cannot delete your own account";
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM admin_users WHERE id = ?");
                        if ($stmt->execute([$id])) {
                            $message = "User deleted successfully";
                        } else {
                            $error = "Failed to delete user";
                        }
                    }
                }
                break;
        }
    }
}

// Get all users
$stmt = $pdo->query("SELECT * FROM admin_users ORDER BY role, username");
$users = $stmt->fetchAll();
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>User Management - Admin Panel</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .role-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .role-admin { background: #dc3545; color: white; }
        .role-editor { background: #ffc107; color: #212529; }
        .role-viewer { background: #28a745; color: white; }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: var(--card);
            margin: 10% auto;
            padding: 20px;
            border-radius: 18px;
            width: 90%;
            max-width: 500px;
            border: 1px solid rgba(255,255,255,.1);
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .close {
            font-size: 24px;
            cursor: pointer;
            color: var(--muted);
        }
        .form-group {
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="h1">User Management</div>
            <div>
                <a class="btn btn-ghost" href="list.php">Back to Records</a>
                <a class="btn btn-ghost" href="logout.php">Logout</a>
            </div>
        </div>

        <div class="card">
            <?php if ($message): ?>
                <div class="success"><?= htmlspecialchars($message) ?></div>
                <div class="hr"></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
                <div class="hr"></div>
            <?php endif; ?>

            <div class="actions">
                <button class="btn btn-primary" onclick="openModal('add')">Add New User</button>
            </div>

            <div class="hr"></div>

            <div style="overflow:auto;">
                <table style="width:100%; border-collapse:collapse;">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Role</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= (int)$user['id'] ?></td>
                            <td><?= htmlspecialchars($user['username']) ?></td>
                            <td><?= htmlspecialchars($user['full_name']) ?></td>
                            <td>
                                <span class="role-badge role-<?= htmlspecialchars($user['role']) ?>">
                                    <?= htmlspecialchars(ucfirst($user['role'])) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($user['created_at']) ?></td>
                            <td>
                                <button class="btnmini edit-btn" onclick="editUser(<?= htmlspecialchars(json_encode($user)) ?>)">Edit</button>
                                <?php if ($user['id'] != $_SESSION['admin_id']): ?>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Delete this user?');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
                                    <button class="btnmini btn-danger" type="submit">Delete</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add/Edit Modal -->
    <div id="userModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Add New User</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form id="userForm" method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="formId" value="0">
                
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" name="username" id="formUsername" required>
                </div>
                
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" id="formFullName">
                </div>
                
                <div class="form-group">
                    <label>Role *</label>
                    <select name="role" id="formRole" required style="width:100%; padding:12px; border-radius:12px; background:rgba(10,16,33,.55); color:white; border:1px solid rgba(255,255,255,.14);">
                        <option value="admin">Admin</option>
                        <option value="editor">Editor</option>
                        <option value="viewer">Viewer</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label id="passwordLabel">Password *</label>
                    <input type="password" name="password" id="formPassword" required>
                    <div class="small" id="passwordNote">Leave blank to keep current password when editing</div>
                </div>
                
                <div class="actions">
                    <button class="btn btn-primary" type="submit">Save</button>
                    <button class="btn btn-ghost" type="button" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(action) {
            document.getElementById('userModal').style.display = 'block';
            document.getElementById('formAction').value = action;
            
            if (action === 'add') {
                document.getElementById('modalTitle').textContent = 'Add New User';
                document.getElementById('userForm').reset();
                document.getElementById('formId').value = '0';
                document.getElementById('formPassword').required = true;
                document.getElementById('passwordLabel').innerHTML = 'Password *';
                document.getElementById('passwordNote').style.display = 'none';
            }
        }
        
        function editUser(user) {
            openModal('edit');
            document.getElementById('modalTitle').textContent = 'Edit User: ' + user.username;
            document.getElementById('formId').value = user.id;
            document.getElementById('formUsername').value = user.username;
            document.getElementById('formFullName').value = user.full_name || '';
            document.getElementById('formRole').value = user.role;
            document.getElementById('formPassword').required = false;
            document.getElementById('passwordLabel').innerHTML = 'Password (leave blank to keep current)';
            document.getElementById('passwordNote').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('userModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            var modal = document.getElementById('userModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>