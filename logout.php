<?php
/**
 * Logout page
 */

// Include configuration
require_once __DIR__ . '/config/config.php';

// Include auth functions
require_once __DIR__ . '/includes/auth.php';

// Check if user is authenticated
if (isAuthenticated()) {
    // Log the logout activity
    $userId = $_SESSION['user_id'] ?? null;
    if ($userId) {
        $user = new User();
        $user->loadById($userId);
        $user->logActivity('logout', null, 'User logged out');
    }
    
    // Log out the user
    logoutUser();
    
    // Set flash message
    setFlashMessage('success', 'You have been successfully logged out');
}

// Redirect to login page
redirect('login.php');
