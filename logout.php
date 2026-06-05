<?php
/**
 * Logout Page
 */

require_once 'config/config.php';
require_once 'includes/functions.php';

// Destroy session
session_unset();
session_destroy();

// Redirect to login
setFlashMessage('success', 'You have been logged out successfully.');
redirect('index.php');
