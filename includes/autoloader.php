<?php
/**
 * Class autoloader
 */

/**
 * PSR-4 autoloader for classes
 *
 * @param string $className Name of the class to load
 */
function autoloadClasses($className) {
    // Define the base directory for classes
    $baseDir = __DIR__ . '/../classes/';
    
    // Convert class name to file path
    $file = $baseDir . $className . '.php';
    
    // Debug output
    error_log("Autoloader: Looking for class $className in file $file");
    
    // If the file exists, require it
    if (file_exists($file)) {
        error_log("Autoloader: Loading class $className from $file");
        require_once $file;
        return true;
    }
    
    error_log("Autoloader: Class file $file not found");
    return false;
}

// Register the autoloader
spl_autoload_register('autoloadClasses');

// Pre-load essential classes
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';

// Make sure we have the classes we need
error_log("Autoloader: Checking if User class exists: " . (class_exists('User') ? 'YES' : 'NO'));
error_log("Autoloader: Checking if Database class exists: " . (class_exists('Database') ? 'YES' : 'NO'));