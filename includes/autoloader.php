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
    
    // If the file exists, require it
    if (file_exists($file)) {
        require_once $file;
        return true;
    }
    return false;
}

// Register the autoloader
spl_autoload_register('autoloadClasses');

// Pre-load essential classes
require_once __DIR__ . '/../classes/Database.php';