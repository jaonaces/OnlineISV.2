<?php
/**
 * Event Planner Multi-Branch Inventory & Booking Management System
 * Configuration File
 */

// Database Configuration - Railway Environment Variables
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASSWORD') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'event_planner_db');

// Application Configuration
define('APP_NAME', 'Event Planner Pro');
define('APP_URL', getenv('APP_URL') ?: 'http://localhost/OnlineIsv.2');
define('APP_VERSION', '1.0.0');

// Session Configuration
define('SESSION_NAME', 'EVENT_PLANNER_SESSION');
define('SESSION_LIFETIME', 7200); // 2 hours

// Security Configuration
define('CSRF_TOKEN_NAME', 'csrf_token');
define('PASSWORD_MIN_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes

// Pagination
define('RECORDS_PER_PAGE', 10);

// File Upload Configuration
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx']);

// Date/Time Configuration
define('DATE_FORMAT', 'Y-m-d');
define('TIME_FORMAT', 'H:i');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');

// Currency Configuration
define('CURRENCY', 'PHP');
define('CURRENCY_SYMBOL', '₱');
define('TAX_RATE', 0.12);

// Error Reporting (Disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('Asia/Manila');

// Start Session
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}
