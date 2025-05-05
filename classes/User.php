<?php
/**
 * User class to handle user-related operations
 */
class User {
    private $db;
    private $id;
    private $username;
    private $email;
    private $fullName;
    private $role;
    private $isActive;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Load user by ID
     * 
     * @param int $id User ID
     * @return bool Success or failure
     */
    public function loadById($id) {
        $query = "SELECT * FROM users WHERE id = ?";
        $user = $this->db->fetchRow($query, [$id]);
        
        if ($user) {
            $this->id = $user['id'];
            $this->username = $user['username'];
            $this->email = $user['email'];
            $this->fullName = $user['full_name'];
            $this->role = $user['role'];
            $this->isActive = $user['is_active'];
            return true;
        }
        return false;
    }
    
    /**
     * Load user by username
     * 
     * @param string $username Username
     * @return bool Success or failure
     */
    public function loadByUsername($username) {
        $query = "SELECT * FROM users WHERE username = ?";
        $user = $this->db->fetchRow($query, [$username]);
        
        if ($user) {
            $this->id = $user['id'];
            $this->username = $user['username'];
            $this->email = $user['email'];
            $this->fullName = $user['full_name'];
            $this->role = $user['role'];
            $this->isActive = $user['is_active'];
            return true;
        }
        return false;
    }
    
    /**
     * Authenticate user with username and password
     * 
     * @param string $username Username
     * @param string $password Password
     * @return bool Authentication result
     */
    public function authenticate($username, $password) {
        $query = "SELECT * FROM users WHERE username = ? AND is_active = true";
        $user = $this->db->fetchRow($query, [$username]);
        
        if ($user && password_verify($password, $user['password'])) {
            $this->id = $user['id'];
            $this->username = $user['username'];
            $this->email = $user['email'];
            $this->fullName = $user['full_name'];
            $this->role = $user['role'];
            $this->isActive = $user['is_active'];
            
            // Log user activity
            $this->logActivity('login', null, 'User logged in');
            
            return true;
        }
        return false;
    }
    
    /**
     * Create a new user
     * 
     * @param array $userData User data
     * @return int|bool User ID or false on failure
     */
    public function create($userData) {
        // Hash the password
        $userData['password'] = password_hash($userData['password'], PASSWORD_DEFAULT);
        
        // Convert fullName to full_name for database
        if (isset($userData['fullName'])) {
            $userData['full_name'] = $userData['fullName'];
            unset($userData['fullName']);
        }
        
        // Insert into database
        return $this->db->insert('users', $userData);
    }
    
    /**
     * Update user
     * 
     * @param array $userData User data
     * @return bool Success or failure
     */
    public function update($userData) {
        if (!$this->id) {
            return false;
        }
        
        // If password is being updated, hash it
        if (isset($userData['password']) && !empty($userData['password'])) {
            $userData['password'] = password_hash($userData['password'], PASSWORD_DEFAULT);
        } else {
            unset($userData['password']);
        }
        
        // Convert fullName to full_name for database
        if (isset($userData['fullName'])) {
            $userData['full_name'] = $userData['fullName'];
            unset($userData['fullName']);
        }
        
        // Update timestamps
        $userData['updated_at'] = date('Y-m-d H:i:s');
        
        return $this->db->update('users', $userData, 'id = ?', [$this->id]);
    }
    
    /**
     * Delete user
     * 
     * @return bool Success or failure
     */
    public function delete() {
        if (!$this->id) {
            return false;
        }
        
        return $this->db->delete('users', 'id = ?', [$this->id]);
    }
    
    /**
     * Get all users
     * 
     * @param int $page Page number
     * @param int $perPage Items per page
     * @return array List of users
     */
    public function getAllUsers($page = 1, $perPage = 20) {
        $offset = ($page - 1) * $perPage;
        
        $query = "SELECT id, username, email, full_name, role, is_active, created_at 
                 FROM users 
                 ORDER BY id ASC 
                 LIMIT ? OFFSET ?";
                 
        return $this->db->fetchAll($query, [$perPage, $offset]);
    }
    
    /**
     * Count total users
     * 
     * @return int Total number of users
     */
    public function countUsers() {
        $query = "SELECT COUNT(*) FROM users";
        $result = $this->db->fetchRow($query);
        return $result ? $result['count'] : 0;
    }
    
    /**
     * Get user entities (companies/organizations) with access rights
     * 
     * @return array List of entities with access rights
     */
    public function getUserEntities() {
        if (!$this->id) {
            return [];
        }
        
        // If user is admin, return all entities
        if ($this->role === ROLE_ADMIN) {
            $query = "SELECT e.*, true as can_view, true as can_download, true as can_export 
                     FROM entities e 
                     ORDER BY e.name";
            return $this->db->fetchAll($query);
        }
        
        // Otherwise return entities with specific user access
        $query = "SELECT e.*, a.can_view, a.can_download, a.can_export 
                 FROM entities e 
                 JOIN user_entity_access a ON e.id = a.entity_id 
                 WHERE a.user_id = ? 
                 ORDER BY e.name";
                 
        return $this->db->fetchAll($query, [$this->id]);
    }
    
    /**
     * Check if user has access to a specific entity
     * 
     * @param int $entityId Entity ID
     * @param string $accessType Type of access (view, download, export)
     * @return bool Has access or not
     */
    public function hasEntityAccess($entityId, $accessType = 'view') {
        if (!$this->id) {
            return false;
        }
        
        // Admin has all access
        if ($this->role === ROLE_ADMIN) {
            return true;
        }
        
        $column = 'can_' . $accessType;
        $query = "SELECT {$column} FROM user_entity_access 
                 WHERE user_id = ? AND entity_id = ?";
                 
        $result = $this->db->fetchRow($query, [$this->id, $entityId]);
        return $result && $result[$column];
    }
    
    /**
     * Log user activity
     * 
     * @param string $action Action performed
     * @param int|null $entityId Related entity ID
     * @param string $details Additional details
     * @return bool Success or failure
     */
    public function logActivity($action, $entityId = null, $details = '') {
        if (!$this->id) {
            return false;
        }
        
        $data = [
            'user_id' => $this->id,
            'action' => $action,
            'entity_id' => $entityId,
            'details' => $details,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
        ];
        
        return $this->db->insert('activity_logs', $data) ? true : false;
    }
    
    /**
     * Grant entity access to user
     * 
     * @param int $entityId Entity ID
     * @param array $accessRights Access rights
     * @return bool Success or failure
     */
    public function grantEntityAccess($entityId, $accessRights) {
        if (!$this->id) {
            return false;
        }
        
        // Check if access already exists
        $query = "SELECT * FROM user_entity_access 
                 WHERE user_id = ? AND entity_id = ?";
        $existingAccess = $this->db->fetchRow($query, [$this->id, $entityId]);
        
        $data = [
            'can_view' => isset($accessRights['can_view']) ? true : false,
            'can_download' => isset($accessRights['can_download']) ? true : false,
            'can_export' => isset($accessRights['can_export']) ? true : false
        ];
        
        if ($existingAccess) {
            return $this->db->update(
                'user_entity_access', 
                $data, 
                'user_id = ? AND entity_id = ?', 
                [$this->id, $entityId]
            );
        } else {
            $data['user_id'] = $this->id;
            $data['entity_id'] = $entityId;
            return $this->db->query(
                "INSERT INTO user_entity_access (user_id, entity_id, can_view, can_download, can_export) 
                 VALUES (?, ?, ?, ?, ?)", 
                [$this->id, $entityId, $data['can_view'], $data['can_download'], $data['can_export']]
            ) ? true : false;
        }
    }
    
    /**
     * Revoke entity access from user
     * 
     * @param int $entityId Entity ID
     * @return bool Success or failure
     */
    public function revokeEntityAccess($entityId) {
        if (!$this->id) {
            return false;
        }
        
        return $this->db->delete(
            'user_entity_access', 
            'user_id = ? AND entity_id = ?', 
            [$this->id, $entityId]
        );
    }
    
    // Getters
    public function getId() {
        return $this->id;
    }
    
    public function getUsername() {
        return $this->username;
    }
    
    public function getEmail() {
        return $this->email;
    }
    
    public function getFullName() {
        return $this->fullName;
    }
    
    public function getRole() {
        return $this->role;
    }
    
    public function isActive() {
        return $this->isActive;
    }
    
    /**
     * Check if user is admin
     * 
     * @return bool Is admin or not
     */
    public function isAdmin() {
        return $this->role === ROLE_ADMIN;
    }
    
    /**
     * Check if user is manager
     * 
     * @return bool Is manager or not
     */
    public function isManager() {
        return $this->role === ROLE_MANAGER || $this->role === ROLE_ADMIN;
    }
}
