<?php
/**
 * Entity class to handle entity (company/organization) operations
 */
class Entity {
    private $db;
    private $id;
    private $name;
    private $taxId;
    private $ksefIdentifier;
    private $ksefToken;
    private $ksefTokenExpiry;
    private $ksefEnvironment;
    private $isActive;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Load entity by ID
     * 
     * @param int $id Entity ID
     * @return bool Success or failure
     */
    public function loadById($id) {
        $query = "SELECT * FROM entities WHERE id = ?";
        $entity = $this->db->fetchRow($query, [$id]);
        
        if ($entity) {
            $this->setProperties($entity);
            return true;
        }
        return false;
    }
    
    /**
     * Load entity by tax ID
     * 
     * @param string $taxId Tax ID (NIP)
     * @param string $environment KSeF environment
     * @return bool Success or failure
     */
    public function loadByTaxId($taxId, $environment = 'test') {
        $query = "SELECT * FROM entities WHERE tax_id = ? AND ksef_environment = ?";
        $entity = $this->db->fetchRow($query, [$taxId, $environment]);
        
        if ($entity) {
            $this->setProperties($entity);
            return true;
        }
        return false;
    }
    
    /**
     * Set entity properties from database row
     * 
     * @param array $entity Entity data from database
     */
    private function setProperties($entity) {
        $this->id = $entity['id'];
        $this->name = $entity['name'];
        $this->taxId = $entity['tax_id'];
        $this->ksefIdentifier = $entity['ksef_identifier'];
        $this->ksefToken = $entity['ksef_token'];
        $this->ksefTokenExpiry = $entity['ksef_token_expiry'];
        $this->ksefEnvironment = $entity['ksef_environment'];
        $this->isActive = $entity['is_active'];
    }
    
    /**
     * Create a new entity
     * 
     * @param array $entityData Entity data
     * @return int|bool Entity ID or false on failure
     */
    public function create($entityData) {
        // Convert camelCase keys to snake_case for database
        if (isset($entityData['taxId'])) {
            $entityData['tax_id'] = $entityData['taxId'];
            unset($entityData['taxId']);
        }
        
        if (isset($entityData['ksefIdentifier'])) {
            $entityData['ksef_identifier'] = $entityData['ksefIdentifier'];
            unset($entityData['ksefIdentifier']);
        }
        
        if (isset($entityData['ksefToken'])) {
            $entityData['ksef_token'] = $entityData['ksefToken'];
            unset($entityData['ksefToken']);
        }
        
        if (isset($entityData['ksefTokenExpiry'])) {
            $entityData['ksef_token_expiry'] = $entityData['ksefTokenExpiry'];
            unset($entityData['ksefTokenExpiry']);
        }
        
        if (isset($entityData['ksefEnvironment'])) {
            $entityData['ksef_environment'] = $entityData['ksefEnvironment'];
            unset($entityData['ksefEnvironment']);
        }
        
        if (isset($entityData['isActive'])) {
            $entityData['is_active'] = $entityData['isActive'];
            unset($entityData['isActive']);
        }
        
        // Insert into database
        $id = $this->db->insert('entities', $entityData);
        
        if ($id) {
            // Load the newly created entity
            $this->loadById($id);
            return $id;
        }
        
        return false;
    }
    
    /**
     * Update entity
     * 
     * @param array $entityData Entity data
     * @return bool Success or failure
     */
    public function update($entityData) {
        if (!$this->id) {
            return false;
        }
        
        // Convert camelCase keys to snake_case for database
        if (isset($entityData['taxId'])) {
            $entityData['tax_id'] = $entityData['taxId'];
            unset($entityData['taxId']);
        }
        
        if (isset($entityData['ksefIdentifier'])) {
            $entityData['ksef_identifier'] = $entityData['ksefIdentifier'];
            unset($entityData['ksefIdentifier']);
        }
        
        if (isset($entityData['ksefToken'])) {
            $entityData['ksef_token'] = $entityData['ksefToken'];
            unset($entityData['ksefToken']);
        }
        
        if (isset($entityData['ksefTokenExpiry'])) {
            $entityData['ksef_token_expiry'] = $entityData['ksefTokenExpiry'];
            unset($entityData['ksefTokenExpiry']);
        }
        
        if (isset($entityData['ksefEnvironment'])) {
            $entityData['ksef_environment'] = $entityData['ksefEnvironment'];
            unset($entityData['ksefEnvironment']);
        }
        
        if (isset($entityData['isActive'])) {
            $entityData['is_active'] = $entityData['isActive'];
            unset($entityData['isActive']);
        }
        
        // Update timestamps
        $entityData['updated_at'] = date('Y-m-d H:i:s');
        
        $success = $this->db->update('entities', $entityData, 'id = ?', [$this->id]);
        
        if ($success) {
            // Reload entity data
            $this->loadById($this->id);
        }
        
        return $success;
    }
    
    /**
     * Delete entity
     * 
     * @return bool Success or failure
     */
    public function delete() {
        if (!$this->id) {
            return false;
        }
        
        return $this->db->delete('entities', 'id = ?', [$this->id]);
    }
    
    /**
     * Get all entities
     * 
     * @param int $page Page number
     * @param int $perPage Items per page
     * @return array List of entities
     */
    public function getAllEntities($page = 1, $perPage = 20) {
        $offset = ($page - 1) * $perPage;
        
        $query = "SELECT id, name, tax_id, ksef_identifier, ksef_environment, is_active, created_at 
                 FROM entities 
                 ORDER BY name ASC 
                 LIMIT ? OFFSET ?";
                 
        return $this->db->fetchAll($query, [$perPage, $offset]);
    }
    
    /**
     * Count total entities
     * 
     * @return int Total number of entities
     */
    public function countEntities() {
        $query = "SELECT COUNT(*) as count FROM entities";
        $result = $this->db->fetchRow($query);
        return $result ? $result['count'] : 0;
    }
    
    /**
     * Get entity users with access rights
     * 
     * @return array List of users with access rights
     */
    public function getEntityUsers() {
        if (!$this->id) {
            return [];
        }
        
        $query = "SELECT u.id, u.username, u.full_name, u.email, u.role, 
                 a.can_view, a.can_download, a.can_export 
                 FROM users u 
                 JOIN user_entity_access a ON u.id = a.user_id 
                 WHERE a.entity_id = ? 
                 ORDER BY u.full_name";
                 
        return $this->db->fetchAll($query, [$this->id]);
    }
    
    /**
     * Update KSeF token
     * 
     * @param string $token KSeF token
     * @param string $expiry Token expiry datetime
     * @return bool Success or failure
     */
    public function updateKsefToken($token, $expiry) {
        if (!$this->id) {
            return false;
        }
        
        $data = [
            'ksef_token' => $token,
            'ksef_token_expiry' => $expiry,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $success = $this->db->update('entities', $data, 'id = ?', [$this->id]);
        
        if ($success) {
            $this->ksefToken = $token;
            $this->ksefTokenExpiry = $expiry;
        }
        
        return $success;
    }
    
    /**
     * Check if KSeF token is valid and not expired
     * 
     * @return bool Token is valid or not
     */
    public function hasValidKsefToken() {
        if (!$this->ksefToken || !$this->ksefTokenExpiry) {
            return false;
        }
        
        $now = new DateTime();
        $expiry = new DateTime($this->ksefTokenExpiry);
        
        return $now < $expiry;
    }
    
    // Getters
    public function getId() {
        return $this->id;
    }
    
    public function getName() {
        return $this->name;
    }
    
    public function getTaxId() {
        return $this->taxId;
    }
    
    public function getKsefIdentifier() {
        return $this->ksefIdentifier;
    }
    
    public function getKsefToken() {
        return $this->ksefToken;
    }
    
    public function getKsefTokenExpiry() {
        return $this->ksefTokenExpiry;
    }
    
    public function getKsefEnvironment() {
        return $this->ksefEnvironment;
    }
    
    public function isActive() {
        return $this->isActive;
    }
    
    /**
     * Get KSeF API URL based on environment
     * 
     * @return string API URL
     */
    public function getKsefApiUrl() {
        switch ($this->ksefEnvironment) {
            case 'prod':
                return KSEF_API_PROD_URL;
            case 'demo':
                return KSEF_API_DEMO_URL;
            case 'test':
            default:
                return KSEF_API_TEST_URL;
        }
    }
}
