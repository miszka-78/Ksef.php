<?php
/**
 * Database class to handle database connections and queries
 */
class Database {
    private static $instance = null;
    private $conn;
    
    /**
     * Constructor - create a new PDO connection
     */
    private function __construct() {
        $this->conn = getDbConnection();
        
        if (!$this->conn) {
            die("Database connection failed. Please check configuration.");
        }
    }
    
    /**
     * Get Database instance (Singleton pattern)
     * 
     * @return Database
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        
        return self::$instance;
    }
    
    /**
     * Get PDO connection
     * 
     * @return PDO
     */
    public function getConnection() {
        return $this->conn;
    }
    
    /**
     * Execute a query with parameters
     * 
     * @param string $query SQL query
     * @param array $params Parameters for prepared statement
     * @return PDOStatement|bool
     */
    public function query($query, $params = []) {
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Database query error: " . $e->getMessage());
            error_log("Query: " . $query);
            error_log("Params: " . print_r($params, true));
            return false;
        }
    }
    
    /**
     * Fetch a single row
     * 
     * @param string $query SQL query
     * @param array $params Parameters for prepared statement
     * @param int $fetchMode PDO fetch mode
     * @return mixed
     */
    public function fetchRow($query, $params = [], $fetchMode = PDO::FETCH_ASSOC) {
        $stmt = $this->query($query, $params);
        if ($stmt) {
            return $stmt->fetch($fetchMode);
        }
        return false;
    }
    
    /**
     * Fetch all rows
     * 
     * @param string $query SQL query
     * @param array $params Parameters for prepared statement
     * @param int $fetchMode PDO fetch mode
     * @return array
     */
    public function fetchAll($query, $params = [], $fetchMode = PDO::FETCH_ASSOC) {
        $stmt = $this->query($query, $params);
        if ($stmt) {
            return $stmt->fetchAll($fetchMode);
        }
        return [];
    }
    
    /**
     * Insert a record and return the last insert ID
     * 
     * @param string $table Table name
     * @param array $data Associative array of column => value
     * @return int|bool Last insert ID or false on failure
     */
    public function insert($table, $data) {
        // Extract column names and placeholders
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        
        $query = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders}) RETURNING id";
        
        $stmt = $this->query($query, array_values($data));
        if ($stmt) {
            return $stmt->fetchColumn();
        }
        return false;
    }
    
    /**
     * Update a record
     * 
     * @param string $table Table name
     * @param array $data Associative array of column => value
     * @param string $where Where clause
     * @param array $whereParams Parameters for where clause
     * @return bool Success or failure
     */
    public function update($table, $data, $where, $whereParams = []) {
        // Build SET part of query
        $set = [];
        foreach ($data as $column => $value) {
            $set[] = "{$column} = ?";
        }
        $setString = implode(', ', $set);
        
        $query = "UPDATE {$table} SET {$setString}";
        if ($where) {
            $query .= " WHERE {$where}";
        }
        
        // Combine data values and where params
        $params = array_merge(array_values($data), $whereParams);
        
        $stmt = $this->query($query, $params);
        return $stmt ? true : false;
    }
    
    /**
     * Delete a record
     * 
     * @param string $table Table name
     * @param string $where Where clause
     * @param array $params Parameters for where clause
     * @return bool Success or failure
     */
    public function delete($table, $where, $params = []) {
        $query = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $this->query($query, $params);
        return $stmt ? true : false;
    }
    
    /**
     * Begin a transaction
     */
    public function beginTransaction() {
        return $this->conn->beginTransaction();
    }
    
    /**
     * Commit a transaction
     */
    public function commit() {
        return $this->conn->commit();
    }
    
    /**
     * Rollback a transaction
     */
    public function rollback() {
        return $this->conn->rollBack();
    }
    
    /**
     * Get the last insert ID
     * 
     * @return string
     */
    public function lastInsertId() {
        return $this->conn->lastInsertId();
    }
    
    /**
     * Close the database connection
     */
    public function close() {
        $this->conn = null;
    }
}
