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

// Check if user has specific role
function require_role(string $role): void {
    require_login();
    
    if (empty($_SESSION['admin_role']) || $_SESSION['admin_role'] !== $role) {
        http_response_code(403);
        exit("Access denied. You don't have permission to access this page.");
    }
}

// Check if user has any of the given roles
function require_any_role(array $roles): void {
    require_login();
    
    if (empty($_SESSION['admin_role']) || !in_array($_SESSION['admin_role'], $roles)) {
        http_response_code(403);
        exit("Access denied. You don't have permission to access this page.");
    }
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Get current user's role
function get_current_role(): string {
    return $_SESSION['admin_role'] ?? 'guest';
}

// Check if user has permission
function has_permission(string $permission): bool {
    $role = get_current_role();
    
    $permissions = [
        'admin' => ['all'],
        'editor' => ['add_user', 'edit_user', 'view_user', 'generate_pdf', 'view_list'],
        'viewer' => ['view_user', 'view_list', 'generate_pdf', 'approve_user']
    ];
    
    if ($role === 'admin') return true;
    if (!isset($permissions[$role])) return false;
    
    return in_array('all', $permissions[$role]) || in_array($permission, $permissions[$role]);
}