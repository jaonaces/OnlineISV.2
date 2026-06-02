<?php
/**
 * Logout Page
 */

require_once 'config/config.php';
require_once 'includes/functions.php';

// Log audit before destroying session
if (isLoggedIn()) {
    logAudit('Logout', 'Authentication', getCurrentUserId(), 'User logged out');
}

// Destroy session
session_unset();
session_destroy();

// Redirect to login
setFlashMessage('success', 'You have been logged out successfully.');
redirect('login.php');
