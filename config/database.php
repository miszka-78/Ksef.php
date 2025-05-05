<?php
/**
 * Database configuration file
 * Handles connection to PostgreSQL database
 */

// Get database credentials from environment variables
$pgHost = getenv('PGHOST') ?: 'localhost';
$pgPort = getenv('PGPORT') ?: '5432';
$pgDatabase = getenv('PGDATABASE') ?: 'ksef_invoices';
$pgUser = getenv('PGUSER') ?: 'postgres';
$pgPassword = getenv('PGPASSWORD') ?: '';
$databaseUrl = getenv('DATABASE_URL') ?: null;

/**
 * Get PDO database connection
 * 
 * @return PDO database connection object
 */
function getDbConnection() {
    global $pgHost, $pgPort, $pgDatabase, $pgUser, $pgPassword, $databaseUrl;
    
    try {
        // If DATABASE_URL is provided, use it
        if ($databaseUrl) {
            $dbConn = new PDO($databaseUrl);
        } else {
            // Otherwise use separate connection parameters
            $dsn = "pgsql:host=$pgHost;port=$pgPort;dbname=$pgDatabase";
            $dbConn = new PDO($dsn, $pgUser, $pgPassword);
        }
        
        // Set error mode to exceptions for better error handling
        $dbConn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        return $dbConn;
    } catch (PDOException $e) {
        // Log the error but don't expose connection details
        error_log("Database connection failed: " . $e->getMessage());
        return null;
    }
}
