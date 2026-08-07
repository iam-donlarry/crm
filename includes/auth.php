<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config.php';

class Auth {
    private $user_id;
    private $user_role;

    public function __construct() {
        if (isset($_SESSION['user_id'])) {
            $this->user_id = $_SESSION['user_id'];
            $this->user_role = $_SESSION['user_role'] ?? 'user';
        }
    }

    public function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    public function getUserRole() {
        return $this->user_role ?? 'user';
    }

    public function getUserId() {
        return $this->user_id;
    }

    public function getUserName() {
        return $_SESSION['user_name'] ?? 'User';
    }

    public function hasRole($role) {
        return strtolower($this->user_role ?? '') === strtolower($role);
    }

    public function hasPermission($permission) {
        return hasPermission($permission);
    }

    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            if ($this->isAjaxRequest()) {
                header('Content-Type: application/json');
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Unauthorized: Please log in']);
                exit();
            } else {
                header("Location: " . BASE_URL . "/login.php");
                exit();
            }
        }
    }

    public function requireRole($roles) {
        $this->requireLogin();
        $rolesArray = is_array($roles) ? array_map('strtolower', $roles) : [strtolower($roles)];
        if (!in_array(strtolower($this->user_role ?? ''), $rolesArray, true) && !$this->hasPermission('system_admin')) {
            if ($this->isAjaxRequest()) {
                header('Content-Type: application/json');
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Forbidden: Access Denied']);
                exit();
            } else {
                header("Location: " . BASE_URL . "/dashboard");
                exit();
            }
        }
    }

    private function isAjaxRequest() {
        return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
            || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
            || (strpos($_SERVER['REQUEST_URI'] ?? '', '/ajax/') !== false);
    }
}

/**
 * Global helper to check permissions
 */
function hasPermission($permission) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    $perms = $_SESSION['permissions'] ?? [];
    $role = $_SESSION['user_role'] ?? 'user';

    // System Administrator override
    if (strtolower($role) === 'admin' || in_array('system_admin', $perms, true)) {
        return true;
    }

    return in_array($permission, $perms, true);
}

/**
 * Global helper function to check permission
 */
function checkPermission($permission) {
    return hasPermission($permission);
}

/**
 * Enforce permission check or block execution
 */
function requirePermission($permission) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $auth = new Auth();
    $auth->requireLogin();

    if (!hasPermission($permission)) {
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
            || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
            || (strpos($_SERVER['REQUEST_URI'] ?? '', '/ajax/') !== false);

        if ($isAjax) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Forbidden: Insufficient permissions']);
            exit();
        } else {
            header("Location: " . BASE_URL . "/dashboard");
            exit();
        }
    }
}

/**
 * Global helper to return SQL WHERE condition for record ownership scoping
 */
function getRecordScopeCondition($userColumn = 'assigned_to', $teamPermission = 'view_team_leads') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $userId = intval($_SESSION['user_id'] ?? 0);
    
    if (hasPermission('system_admin') || hasPermission($teamPermission)) {
        return "1=1";
    }
    
    return "({$userColumn} = {$userId} OR {$userColumn} IS NULL)";
}

$auth = new Auth();
