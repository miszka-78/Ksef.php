<?php
/**
 * Application configuration settings
 */

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1);

// Application settings
define('APP_NAME', 'KSeF Invoice Manager');
define('APP_VERSION', '1.0.0');
define('DEFAULT_TIMEZONE', 'Europe/Warsaw');

// Set default timezone
date_default_timezone_set(DEFAULT_TIMEZONE);

// KSeF API URLs
define('KSEF_API_PROD_URL', 'https://ksef.mf.gov.pl/api');
define('KSEF_API_TEST_URL', 'https://ksef-test.mf.gov.pl/api');
define('KSEF_API_DEMO_URL', 'https://ksef-demo.mf.gov.pl/api');

// Default API environment
define('KSEF_API_DEFAULT_ENV', 'test'); // Options: 'prod', 'test', 'demo'

// User roles
define('ROLE_ADMIN', 'admin');
define('ROLE_MANAGER', 'manager');
define('ROLE_USER', 'user');

// Define pagination settings
define('ITEMS_PER_PAGE', 20);

// File paths
define('TEMPLATE_DIR', __DIR__ . '/../templates/');
define('EXPORT_DIR', __DIR__ . '/../exports/');
define('INVOICE_ARCHIVE_DIR', __DIR__ . '/../archives/');

// Ensure export and archive directories exist
if (!file_exists(EXPORT_DIR)) {
    mkdir(EXPORT_DIR, 0755, true);
}

if (!file_exists(INVOICE_ARCHIVE_DIR)) {
    mkdir(INVOICE_ARCHIVE_DIR, 0755, true);
}

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Create logs directory if it doesn't exist
if (!file_exists(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0755, true);
}

// Include database configuration
require_once __DIR__ . '/database.php';

// Include utility functions
require_once __DIR__ . '/../includes/functions.php';
