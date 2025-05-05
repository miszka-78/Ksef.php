<?php
/**
 * Utility functions for the application
 */

/**
 * Sanitize input to prevent XSS
 * 
 * @param string $input Input to sanitize
 * @return string Sanitized input
 */
function sanitize($input) {
    return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
}

/**
 * Validate required fields
 * 
 * @param array $data Input data
 * @param array $requiredFields List of required fields
 * @return array Validation errors
 */
function validateRequiredFields($data, $requiredFields) {
    $errors = [];
    
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            $errors[] = "Field '$field' is required";
        }
    }
    
    return $errors;
}

/**
 * Get current page URL
 * 
 * @return string Current page URL
 */
function getCurrentUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $url = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    return $url;
}

/**
 * Redirect to another page
 * 
 * @param string $url URL to redirect to
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Set flash message
 * 
 * @param string $type Message type (success, error, warning, info)
 * @param string $message Message text
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Get and clear flash message
 * 
 * @return array|null Flash message or null
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    
    return null;
}

/**
 * Format date for display
 * 
 * @param string $date Date string
 * @param string $format Output format
 * @return string Formatted date
 */
function formatDate($date, $format = 'Y-m-d') {
    if (!$date) {
        return '';
    }
    
    $timestamp = strtotime($date);
    return date($format, $timestamp);
}

/**
 * Format number for display
 * 
 * @param float $number Number to format
 * @param int $decimals Number of decimal places
 * @return string Formatted number
 */
function formatNumber($number, $decimals = 2) {
    return number_format($number, $decimals, ',', ' ');
}

/**
 * Format money amount
 * 
 * @param float $amount Amount to format
 * @param string $currency Currency code
 * @return string Formatted amount
 */
function formatMoney($amount, $currency = 'PLN') {
    return formatNumber($amount) . ' ' . $currency;
}

/**
 * Generate pagination HTML
 * 
 * @param int $currentPage Current page number
 * @param int $totalPages Total number of pages
 * @param string $urlPattern URL pattern with {page} placeholder
 * @return string Pagination HTML
 */
function generatePagination($currentPage, $totalPages, $urlPattern) {
    $html = '<nav aria-label="Page navigation"><ul class="pagination">';
    
    // Previous button
    if ($currentPage > 1) {
        $prevUrl = str_replace('{page}', $currentPage - 1, $urlPattern);
        $html .= '<li class="page-item"><a class="page-link" href="' . $prevUrl . '">&laquo; Previous</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><a class="page-link" href="#">&laquo; Previous</a></li>';
    }
    
    // Page numbers
    $startPage = max(1, $currentPage - 2);
    $endPage = min($totalPages, $currentPage + 2);
    
    if ($startPage > 1) {
        $url = str_replace('{page}', 1, $urlPattern);
        $html .= '<li class="page-item"><a class="page-link" href="' . $url . '">1</a></li>';
        
        if ($startPage > 2) {
            $html .= '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
        }
    }
    
    for ($i = $startPage; $i <= $endPage; $i++) {
        $url = str_replace('{page}', $i, $urlPattern);
        
        if ($i == $currentPage) {
            $html .= '<li class="page-item active"><a class="page-link" href="#">' . $i . '</a></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="' . $url . '">' . $i . '</a></li>';
        }
    }
    
    if ($endPage < $totalPages) {
        if ($endPage < $totalPages - 1) {
            $html .= '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
        }
        
        $url = str_replace('{page}', $totalPages, $urlPattern);
        $html .= '<li class="page-item"><a class="page-link" href="' . $url . '">' . $totalPages . '</a></li>';
    }
    
    // Next button
    if ($currentPage < $totalPages) {
        $nextUrl = str_replace('{page}', $currentPage + 1, $urlPattern);
        $html .= '<li class="page-item"><a class="page-link" href="' . $nextUrl . '">Next &raquo;</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><a class="page-link" href="#">Next &raquo;</a></li>';
    }
    
    $html .= '</ul></nav>';
    
    return $html;
}

/**
 * Truncate text to specified length
 * 
 * @param string $text Text to truncate
 * @param int $length Maximum length
 * @param string $suffix Suffix to add when truncated
 * @return string Truncated text
 */
function truncateText($text, $length = 100, $suffix = '...') {
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    
    return mb_substr($text, 0, $length) . $suffix;
}

/**
 * Generate random token
 * 
 * @param int $length Token length
 * @return string Random token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Clean filename from unsafe characters
 * 
 * @param string $filename Filename to clean
 * @return string Cleaned filename
 */
function cleanFilename($filename) {
    // Remove unwanted characters
    $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);
    
    // Ensure it doesn't start with a dot
    $filename = ltrim($filename, '.');
    
    // Replace multiple underscores with a single one
    $filename = preg_replace('/_+/', '_', $filename);
    
    return $filename;
}

/**
 * Get client IP address
 * 
 * @return string Client IP address
 */
function getClientIp() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    return $ip;
}

/**
 * Log application error
 * 
 * @param string $message Error message
 * @param string $level Error level
 */
function logError($message, $level = 'ERROR') {
    $logFile = __DIR__ . '/../logs/app.log';
    $logDir = dirname($logFile);
    
    // Create logs directory if it doesn't exist
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $date = date('Y-m-d H:i:s');
    $ip = getClientIp();
    $user = isset($_SESSION['username']) ? $_SESSION['username'] : 'guest';
    
    $logMessage = "[$date] [$level] [$ip] [$user] $message" . PHP_EOL;
    
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

/**
 * Get human-readable file size
 * 
 * @param int $bytes File size in bytes
 * @param int $decimals Number of decimal places
 * @return string Human-readable file size
 */
function formatFileSize($bytes, $decimals = 2) {
    $size = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
    $factor = floor((strlen($bytes) - 1) / 3);
    
    return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . ' ' . @$size[$factor];
}

/**
 * Check if string is a valid XML
 * 
 * @param string $xml XML string to check
 * @return bool Is valid XML or not
 */
function isValidXml($xml) {
    $prev = libxml_use_internal_errors(true);
    
    $doc = simplexml_load_string($xml);
    $errors = libxml_get_errors();
    
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    
    return $doc !== false && empty($errors);
}

/**
 * Convert Polish characters to ASCII
 * 
 * @param string $text Text to convert
 * @return string Converted text
 */
function convertPolishChars($text) {
    $polishChars = [
        'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n', 
        'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z',
        'Ą' => 'A', 'Ć' => 'C', 'Ę' => 'E', 'Ł' => 'L', 'Ń' => 'N', 
        'Ó' => 'O', 'Ś' => 'S', 'Ź' => 'Z', 'Ż' => 'Z'
    ];
    
    return str_replace(array_keys($polishChars), array_values($polishChars), $text);
}

/**
 * Format Polish tax ID (NIP)
 * 
 * @param string $nip Tax ID to format
 * @return string Formatted tax ID
 */
function formatNip($nip) {
    // Remove non-digit characters
    $nip = preg_replace('/[^0-9]/', '', $nip);
    
    // Check if NIP has 10 digits
    if (strlen($nip) != 10) {
        return $nip;
    }
    
    // Format as XXX-XXX-XX-XX
    return substr($nip, 0, 3) . '-' . substr($nip, 3, 3) . '-' . substr($nip, 6, 2) . '-' . substr($nip, 8, 2);
}

/**
 * Check if user is logged in
 * 
 * @return bool User is logged in or not
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && $_SESSION['user_id'];
}

/**
 * Check if current user has specified role
 * 
 * @param string|array $roles Role or roles to check
 * @return bool User has role or not
 */
function hasRole($roles) {
    if (!isLoggedIn()) {
        return false;
    }
    
    if (!isset($_SESSION['user_role'])) {
        return false;
    }
    
    if (is_array($roles)) {
        return in_array($_SESSION['user_role'], $roles);
    }
    
    return $_SESSION['user_role'] === $roles;
}

/**
 * Initialize database tables
 */
function initializeTables() {
    $sqlFile = __DIR__ . '/../sql/schema.sql';
    
    if (!file_exists($sqlFile)) {
        logError("SQL schema file not found: $sqlFile");
        return false;
    }
    
    $sql = file_get_contents($sqlFile);
    $db = Database::getInstance();
    
    try {
        $db->getConnection()->exec($sql);
        return true;
    } catch (PDOException $e) {
        logError("Error initializing database: " . $e->getMessage());
        return false;
    }
}
