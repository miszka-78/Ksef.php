<?php
/**
 * Application entry point
 * Redirects to login or dashboard based on authentication status
 */

// Include configuration
require_once __DIR__ . '/config/config.php';

// Include auth functions
require_once __DIR__ . '/includes/auth.php';

// Redirect to dashboard if authenticated, otherwise to login
if (isAuthenticated()) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;
