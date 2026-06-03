<?php
/**
 * Common Functions
 */

require_once __DIR__ . '/../config/db.php';

// Sanitize Input
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Validate Email
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Generate Random String
function generateRandomString($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

// Generate CSRF Token
function generateCSRFToken() {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = generateRandomString();
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

// Verify CSRF Token
function verifyCSRFToken($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

// Hash Password
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

// Verify Password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Format Currency
function formatCurrency($amount) {
    return CURRENCY_SYMBOL . number_format($amount ?? 0, 2);
}

// Format Date
function formatDate($date, $format = null) {
    $format = $format ?: DATE_FORMAT;
    return date($format, strtotime($date));
}

// Format DateTime
function formatDateTime($datetime, $format = null) {
    $format = $format ?: DATETIME_FORMAT;
    return date($format, strtotime($datetime));
}

// Get Current Date
function getCurrentDate() {
    return date(DATE_FORMAT);
}

// Get Current DateTime
function getCurrentDateTime() {
    return date(DATETIME_FORMAT);
}

// Redirect
function redirect($url) {
    header("Location: $url");
    exit();
}

// Get Company Name from Settings
function getCompanyName() {
    $db = Database::getInstance();
    $result = $db->fetchOne("SELECT setting_value FROM system_settings WHERE setting_key = 'company_name'");
    return $result['setting_value'] ?? 'Event Planner Pro';
}

// Set Flash Message
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

// Get Flash Message
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

// Check if User is Logged In
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Get Current User ID
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

// Get Current User Role
function getCurrentUserRole() {
    return $_SESSION['role'] ?? null;
}

// Get Current User Branch ID
function getCurrentUserBranchId() {
    return $_SESSION['branch_id'] ?? null;
}

// Check if Super Admin
function isSuperAdmin() {
    return getCurrentUserRole() === 'Super Admin';
}

// Check if Branch Admin
function isBranchAdmin() {
    return getCurrentUserRole() === 'Branch Admin';
}

// Check if Staff
function isStaff() {
    return getCurrentUserRole() === 'Staff';
}

// Require Login
function requireLogin() {
    if (!isLoggedIn()) {
        setFlashMessage('error', 'Please login to access this page.');
        redirect('login.php');
    }
}

// Require Super Admin
function requireSuperAdmin() {
    requireLogin();
    if (!isSuperAdmin()) {
        setFlashMessage('error', 'Access denied. Super Admin only.');
        redirect('dashboard.php');
    }
}

// Require Branch Access
function requireBranchAccess($branchId) {
    requireLogin();
    if (isSuperAdmin()) {
        return true;
    }
    if (getCurrentUserBranchId() != $branchId) {
        setFlashMessage('error', 'Access denied. You can only access your branch data.');
        redirect('dashboard.php');
    }
}

// Generate Booking Number
function generateBookingNumber() {
    $db = Database::getInstance();
    $prefix = date('ym');
    
    $result = $db->fetchOne(
        "SELECT MAX(CAST(SUBSTRING(booking_number, 8) AS UNSIGNED)) as max_num 
         FROM bookings 
         WHERE booking_number LIKE ?",
        [$prefix . '-%']
    );
    
    $nextNum = ($result['max_num'] ?? 0) + 1;
    return $prefix . '-' . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
}

// Generate Invoice Number
function generateInvoiceNumber() {
    $db = Database::getInstance();
    $prefix = date('ym');
    
    $result = $db->fetchOne(
        "SELECT MAX(CAST(SUBSTRING(invoice_number, 8) AS UNSIGNED)) as max_num 
         FROM invoices 
         WHERE invoice_number LIKE ?",
        [$prefix . '-%']
    );
    
    $nextNum = ($result['max_num'] ?? 0) + 1;
    return $prefix . '-' . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
}

// Generate Transfer Code
function generateTransferCode() {
    $db = Database::getInstance();
    $prefix = date('ym');
    
    $result = $db->fetchOne(
        "SELECT MAX(CAST(SUBSTRING(transfer_code, 8) AS UNSIGNED)) as max_num 
         FROM inventory_transfers 
         WHERE transfer_code LIKE ?",
        ['TR' . $prefix . '-%']
    );
    
    $nextNum = ($result['max_num'] ?? 0) + 1;
    return 'TR' . $prefix . '-' . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
}

// Upload File
function uploadFile($file, $directory = 'uploads/') {
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'message' => 'No file uploaded'];
    }
    
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($fileExt, ALLOWED_FILE_TYPES)) {
        return ['success' => false, 'message' => 'Invalid file type'];
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'File size exceeds limit'];
    }
    
    $fileName = uniqid() . '.' . $fileExt;
    $filePath = $directory . $fileName;
    
    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    
    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        return ['success' => true, 'filename' => $fileName, 'path' => $filePath];
    }
    
    return ['success' => false, 'message' => 'Failed to upload file'];
}

// Delete File
function deleteFile($filePath) {
    if (file_exists($filePath)) {
        return unlink($filePath);
    }
    return false;
}

// Pagination
function paginate($totalRecords, $currentPage, $recordsPerPage = RECORDS_PER_PAGE) {
    $totalPages = ceil($totalRecords / $recordsPerPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $recordsPerPage;
    
    return [
        'total_records' => $totalRecords,
        'total_pages' => $totalPages,
        'current_page' => $currentPage,
        'records_per_page' => $recordsPerPage,
        'offset' => $offset,
        'has_next' => $currentPage < $totalPages,
        'has_prev' => $currentPage > 1
    ];
}

// JSON Response
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}
