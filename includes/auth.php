<?php
/**
 * Authentication and authorization functions
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Class User is loaded via autoloader

/**
 * Authenticate user with username and password
 * 
 * @param string $username Username
 * @param string $password Password
 * @return bool|array Authentication result or false
 */
function authenticateUser($username, $password) {
    // Debug class existence
    error_log("Auth: Checking if User class exists before instantiation: " . (class_exists('User') ? 'YES' : 'NO'));
    
    $user = new User();
    
    if ($user->authenticate($username, $password)) {
        // Set session variables
        $_SESSION['user_id'] = $user->getId();
        $_SESSION['username'] = $user->getUsername();
        $_SESSION['user_name'] = $user->getFullName();
        $_SESSION['user_role'] = $user->getRole();
        
        // Set a CSRF token
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'fullName' => $user->getFullName(),
            'role' => $user->getRole()
        ];
    }
    
    return false;
}

/**
 * Log out current user
 */
function logoutUser() {
    // Clear user-related session variables
    unset($_SESSION['user_id']);
    unset($_SESSION['username']);
    unset($_SESSION['user_name']);
    unset($_SESSION['user_role']);
    unset($_SESSION['selected_entity']);
    
    // Regenerate session ID
    session_regenerate_id(true);
}

/**
 * Check if user is authenticated
 * 
 * @return bool User is authenticated or not
 */
function isAuthenticated() {
    return isset($_SESSION['user_id']) && $_SESSION['user_id'];
}

/**
 * Require authentication for the current page
 * 
 * @param string $redirectUrl URL to redirect to if not authenticated
 */
function requireAuth($redirectUrl = '/login.php') {
    if (!isAuthenticated()) {
        setFlashMessage('error', 'You must be logged in to access this page');
        redirect($redirectUrl . '?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    }
}

/**
 * Check if current user has a specific role
 * 
 * @param string|array $roles Role or roles to check
 * @return bool User has role or not
 */
function userHasRole($roles) {
    if (!isAuthenticated()) {
        return false;
    }
    
    if (is_array($roles)) {
        return in_array($_SESSION['user_role'], $roles);
    }
    
    return $_SESSION['user_role'] === $roles;
}

/**
 * Require specific role for the current page
 * 
 * @param string|array $roles Role or roles to require
 * @param string $redirectUrl URL to redirect to if not authorized
 */
function requireRole($roles, $redirectUrl = '/dashboard.php') {
    requireAuth();
    
    if (!userHasRole($roles)) {
        setFlashMessage('error', 'You do not have permission to access this page');
        redirect($redirectUrl);
    }
}

/**
 * Check CSRF token
 * 
 * @param string $token Token to check
 * @return bool Token is valid or not
 */
function checkCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get CSRF token
 * 
 * @return string CSRF token
 */
function getCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}

/**
 * Require valid CSRF token for the current request
 * 
 * @param string $redirectUrl URL to redirect to if token is invalid
 */
function requireCsrfToken($redirectUrl = '/') {
    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? null;
    
    if (!$token || !checkCsrfToken($token)) {
        setFlashMessage('error', 'Invalid security token. Please try again.');
        redirect($redirectUrl);
    }
}

/**
 * Check if user has access to an entity
 * 
 * @param int $entityId Entity ID
 * @param string $accessType Type of access (view, download, export)
 * @return bool Has access or not
 */
function userHasEntityAccess($entityId, $accessType = 'view') {
    if (!isAuthenticated()) {
        return false;
    }
    
    // Admin has access to everything
    if ($_SESSION['user_role'] === ROLE_ADMIN) {
        return true;
    }
    
    $user = new User();
    $user->loadById($_SESSION['user_id']);
    
    return $user->hasEntityAccess($entityId, $accessType);
}

/**
 * Require entity access for the current request
 * 
 * @param int $entityId Entity ID
 * @param string $accessType Type of access (view, download, export)
 * @param string $redirectUrl URL to redirect to if no access
 */
function requireEntityAccess($entityId, $accessType = 'view', $redirectUrl = '/entities.php') {
    requireAuth();
    
    if (!userHasEntityAccess($entityId, $accessType)) {
        setFlashMessage('error', 'You do not have permission to access this entity');
        redirect($redirectUrl);
    }
}

/**
 * Get current user's entities
 * 
 * @return array List of entities
 */
function getUserEntities() {
    if (!isAuthenticated()) {
        return [];
    }
    
    $user = new User();
    $user->loadById($_SESSION['user_id']);
    
    return $user->getUserEntities();
}

/**
 * Set selected entity in session
 * 
 * @param int $entityId Entity ID
 * @return bool Success or failure
 */
function setSelectedEntity($entityId) {
    if (!isAuthenticated() || !userHasEntityAccess($entityId)) {
        return false;
    }
    
    $_SESSION['selected_entity'] = $entityId;
    return true;
}

/**
 * Get selected entity from session
 * 
 * @return int|null Selected entity ID or null
 */
function getSelectedEntity() {
    if (!isAuthenticated() || !isset($_SESSION['selected_entity'])) {
        return null;
    }
    
    return $_SESSION['selected_entity'];
}

/**
 * Log user activity
 * 
 * @param string $action Action performed
 * @param int|null $entityId Related entity ID
 * @param string $details Additional details
 */
function logUserActivity($action, $entityId = null, $details = '') {
    if (!isAuthenticated()) {
        return;
    }
    
    $user = new User();
    $user->loadById($_SESSION['user_id']);
    $user->logActivity($action, $entityId, $details);
}
