-- Event Planner Multi-Branch Inventory & Booking Management System
-- Database Schema

-- Create Database
CREATE DATABASE IF NOT EXISTS event_planner_db;
USE event_planner_db;

-- Disable foreign key checks for entire schema import
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- DROP EXISTING TABLES (in reverse dependency order)
-- ============================================
DROP TABLE IF EXISTS event_assignments;
DROP TABLE IF EXISTS booking_items;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS invoices;
DROP TABLE IF EXISTS staff;
DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS inventory_transfers;
DROP TABLE IF EXISTS inventory_transactions;
DROP TABLE IF EXISTS package_items;
DROP TABLE IF EXISTS inventory;
DROP TABLE IF EXISTS packages;
DROP TABLE IF EXISTS clients;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS branches;
DROP TABLE IF EXISTS system_settings;

-- ============================================
-- BRANCHES TABLE
-- ============================================
CREATE TABLE branches (
    branch_id INT AUTO_INCREMENT PRIMARY KEY,
    branch_code VARCHAR(20) UNIQUE NOT NULL,
    branch_name VARCHAR(100) NOT NULL,
    address TEXT NOT NULL,
    contact_number VARCHAR(20) NOT NULL,
    email VARCHAR(100),
    manager_name VARCHAR(100),
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_branch_code (branch_code),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- USERS TABLE
-- ============================================
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    role ENUM('Super Admin', 'Branch Admin', 'Staff') NOT NULL,
    branch_id INT,
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_branch_id (branch_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- CLIENTS TABLE
-- ============================================
CREATE TABLE clients (
    client_id INT AUTO_INCREMENT PRIMARY KEY,
    client_code VARCHAR(20) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20) NOT NULL,
    address TEXT,
    company_name VARCHAR(100),
    branch_id INT NOT NULL,
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_client_code (client_code),
    INDEX idx_branch_id (branch_id),
    INDEX idx_status (status),
    INDEX idx_full_name (full_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- PACKAGES TABLE
-- ============================================
CREATE TABLE packages (
    package_id INT AUTO_INCREMENT PRIMARY KEY,
    package_code VARCHAR(20) UNIQUE NOT NULL,
    package_name VARCHAR(100) NOT NULL,
    description TEXT,
    event_type ENUM('Wedding', 'Birthday', 'Corporate Event', 'Debut', 'Catering', 'Rental', 'Other') NOT NULL,
    base_price DECIMAL(12, 2) NOT NULL,
    branch_id INT NOT NULL,
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_package_code (package_code),
    INDEX idx_branch_id (branch_id),
    INDEX idx_event_type (event_type),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INVENTORY TABLE
-- ============================================
CREATE TABLE inventory (
    inventory_id INT AUTO_INCREMENT PRIMARY KEY,
    item_code VARCHAR(20) UNIQUE NOT NULL,
    item_name VARCHAR(100) NOT NULL,
    category ENUM('Chairs', 'Tables', 'Tents', 'Sound Systems', 'Lights', 'LED Walls', 'Decorations', 'Catering Equipment', 'Other') NOT NULL,
    description TEXT,
    branch_id INT NOT NULL,
    total_quantity INT NOT NULL DEFAULT 0,
    available_quantity INT NOT NULL DEFAULT 0,
    reserved_quantity INT NOT NULL DEFAULT 0,
    in_use_quantity INT NOT NULL DEFAULT 0,
    maintenance_quantity INT NOT NULL DEFAULT 0,
    damaged_quantity INT NOT NULL DEFAULT 0,
    rental_price DECIMAL(10, 2) NOT NULL,
    condition_status ENUM('Available', 'Reserved', 'In Use', 'Maintenance', 'Damaged') DEFAULT 'Available',
    purchase_date DATE,
    purchase_price DECIMAL(12, 2),
    supplier VARCHAR(100),
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_item_code (item_code),
    INDEX idx_branch_id (branch_id),
    INDEX idx_category (category),
    INDEX idx_status (status),
    INDEX idx_condition_status (condition_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- PACKAGE ITEMS TABLE
-- ============================================
CREATE TABLE package_items (
    package_item_id INT AUTO_INCREMENT PRIMARY KEY,
    package_id INT NOT NULL,
    inventory_id INT NOT NULL,
    quantity INT NOT NULL,
    INDEX idx_package_id (package_id),
    INDEX idx_inventory_id (inventory_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INVENTORY TRANSACTIONS TABLE
-- ============================================
CREATE TABLE inventory_transactions (
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_code VARCHAR(20) UNIQUE NOT NULL,
    inventory_id INT NOT NULL,
    transaction_type ENUM('Stock In', 'Stock Out', 'Adjustment', 'Reservation', 'Release', 'Return', 'Transfer') NOT NULL,
    quantity INT NOT NULL,
    previous_quantity INT NOT NULL,
    new_quantity INT NOT NULL,
    reference_id INT,
    reference_type ENUM('Booking', 'Transfer', 'Adjustment') NULL,
    notes TEXT,
    performed_by INT NOT NULL,
    branch_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_transaction_code (transaction_code),
    INDEX idx_inventory_id (inventory_id),
    INDEX idx_transaction_type (transaction_type),
    INDEX idx_branch_id (branch_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INVENTORY TRANSFERS TABLE
-- ============================================
CREATE TABLE inventory_transfers (
    transfer_id INT AUTO_INCREMENT PRIMARY KEY,
    transfer_code VARCHAR(20) UNIQUE NOT NULL,
    source_branch_id INT NOT NULL,
    destination_branch_id INT NOT NULL,
    inventory_id INT NOT NULL,
    quantity INT NOT NULL,
    status ENUM('Pending', 'Approved', 'Received', 'Cancelled') DEFAULT 'Pending',
    requested_by INT NOT NULL,
    approved_by INT,
    received_by INT,
    approved_at TIMESTAMP NULL,
    received_at TIMESTAMP NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_transfer_code (transfer_code),
    INDEX idx_source_branch (source_branch_id),
    INDEX idx_destination_branch (destination_branch_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- BOOKINGS TABLE
-- ============================================
CREATE TABLE bookings (
    booking_id INT AUTO_INCREMENT PRIMARY KEY,
    booking_number VARCHAR(20) UNIQUE NOT NULL,
    client_id INT NOT NULL,
    branch_id INT NOT NULL,
    event_type ENUM('Wedding', 'Birthday', 'Corporate Event', 'Debut', 'Catering', 'Rental', 'Other') NOT NULL,
    event_date DATE NOT NULL,
    event_time TIME NOT NULL,
    end_time TIME,
    event_location TEXT NOT NULL,
    venue_name VARCHAR(100),
    package_id INT,
    estimated_guests INT,
    status ENUM('Inquiry', 'Pending', 'Confirmed', 'Ongoing', 'Completed', 'Cancelled') DEFAULT 'Inquiry',
    total_amount DECIMAL(12, 2) NOT NULL DEFAULT 0,
    down_payment DECIMAL(12, 2) DEFAULT 0,
    balance DECIMAL(12, 2) DEFAULT 0,
    special_requests TEXT,
    notes TEXT,
    created_by INT NOT NULL,
    updated_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_booking_number (booking_number),
    INDEX idx_client_id (client_id),
    INDEX idx_branch_id (branch_id),
    INDEX idx_event_date (event_date),
    INDEX idx_status (status),
    INDEX idx_event_type (event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- BOOKING ITEMS TABLE
-- ============================================
CREATE TABLE booking_items (
    booking_item_id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    inventory_id INT NOT NULL,
    quantity INT NOT NULL,
    rental_price DECIMAL(10, 2) NOT NULL,
    subtotal DECIMAL(12, 2) NOT NULL,
    status ENUM('Reserved', 'Released', 'Returned', 'Cancelled') DEFAULT 'Reserved',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_booking_id (booking_id),
    INDEX idx_inventory_id (inventory_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- STAFF TABLE
-- ============================================
CREATE TABLE staff (
    staff_id INT AUTO_INCREMENT PRIMARY KEY,
    staff_code VARCHAR(20) UNIQUE NOT NULL,
    user_id INT,
    full_name VARCHAR(100) NOT NULL,
    position VARCHAR(50),
    phone VARCHAR(20),
    email VARCHAR(100),
    branch_id INT NOT NULL,
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    hire_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_staff_code (staff_code),
    INDEX idx_user_id (user_id),
    INDEX idx_branch_id (branch_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- EVENT ASSIGNMENTS TABLE
-- ============================================
CREATE TABLE event_assignments (
    assignment_id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    staff_id INT NOT NULL,
    role ENUM('Coordinator', 'Setup', 'Server', 'Technician', 'Driver', 'Other') NOT NULL,
    assigned_date DATE NOT NULL,
    start_time TIME,
    end_time TIME,
    status ENUM('Assigned', 'Checked In', 'Completed', 'Absent') DEFAULT 'Assigned',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_booking_id (booking_id),
    INDEX idx_staff_id (staff_id),
    INDEX idx_assigned_date (assigned_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INVOICES TABLE
-- ============================================
CREATE TABLE invoices (
    invoice_id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(20) UNIQUE NOT NULL,
    booking_id INT NOT NULL,
    client_id INT NOT NULL,
    branch_id INT NOT NULL,
    invoice_date DATE NOT NULL,
    due_date DATE,
    subtotal DECIMAL(12, 2) NOT NULL,
    tax DECIMAL(12, 2) DEFAULT 0,
    discount DECIMAL(12, 2) DEFAULT 0,
    total_amount DECIMAL(12, 2) NOT NULL,
    status ENUM('Draft', 'Sent', 'Partial', 'Paid', 'Overdue', 'Cancelled') DEFAULT 'Draft',
    notes TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_invoice_number (invoice_number),
    INDEX idx_booking_id (booking_id),
    INDEX idx_client_id (client_id),
    INDEX idx_branch_id (branch_id),
    INDEX idx_status (status),
    INDEX idx_invoice_date (invoice_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================
-- PAYMENTS TABLE
-- ============================================
CREATE TABLE payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    payment_code VARCHAR(20) UNIQUE NOT NULL,
    invoice_id INT NOT NULL,
    booking_id INT NOT NULL,
    client_id INT NOT NULL,
    branch_id INT NOT NULL,
    payment_date DATE NOT NULL,
    payment_method ENUM('Cash', 'Bank Transfer', 'GCash', 'Maya', 'Other') NOT NULL,
    amount DECIMAL(12, 2) NOT NULL,
    reference_number VARCHAR(50),
    notes TEXT,
    received_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_payment_code (payment_code),
    INDEX idx_invoice_id (invoice_id),
    INDEX idx_booking_id (booking_id),
    INDEX idx_branch_id (branch_id),
    INDEX idx_payment_date (payment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SYSTEM SETTINGS TABLE
-- ============================================
CREATE TABLE system_settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INSERT DEFAULT DATA
-- ============================================

-- Insert Default Branch
INSERT INTO branches (branch_code, branch_name, address, contact_number, email, manager_name, status) VALUES
('BR001', 'Main Branch', '123 Event Street, Manila', '+632-8123-4567', 'main@eventplanner.com', 'Juan Dela Cruz', 'Active'),
('BR002', 'North Branch', '456 North Avenue, Quezon City', '+632-8987-6543', 'north@eventplanner.com', 'Maria Santos', 'Active'),
('BR003', 'South Branch', '789 South Road, Makati', '+632-8555-1234', 'south@eventplanner.com', 'Pedro Reyes', 'Active');

-- Insert Default Super Admin User (Password: admin123)
INSERT INTO users (username, password, full_name, email, phone, role, branch_id, status) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Super Administrator', 'admin@eventplanner.com', '+639-123-456-7890', 'Super Admin', NULL, 'Active');

-- Insert Default System Settings
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('company_name', 'Event Planner Pro', 'Company Name'),
('currency', 'PHP', 'Currency Code'),
('date_format', 'Y-m-d', 'Date Format'),
('time_format', 'H:i', 'Time Format');

-- ============================================
-- CREATE STORED PROCEDURES FOR AUTO-GENERATION
-- ============================================

DELIMITER //

-- Generate Booking Number
CREATE PROCEDURE GenerateBookingNumber()
BEGIN
    DECLARE next_number INT;
    DECLARE booking_number VARCHAR(20);
    DECLARE prefix VARCHAR(3);
    
    SET prefix = DATE_FORMAT(NOW(), '%y%m');
    
    SELECT COALESCE(MAX(CAST(SUBSTRING(booking_number, 8) AS UNSIGNED)), 0) + 1
    INTO next_number
    FROM bookings
    WHERE booking_number LIKE CONCAT(prefix, '-%');
    
    SET booking_number = CONCAT(prefix, '-', LPAD(next_number, 6, '0'));
    SELECT booking_number;
END //

-- Generate Invoice Number
CREATE PROCEDURE GenerateInvoiceNumber()
BEGIN
    DECLARE next_number INT;
    DECLARE invoice_number VARCHAR(20);
    DECLARE prefix VARCHAR(3);
    
    SET prefix = DATE_FORMAT(NOW(), '%y%m');
    
    SELECT COALESCE(MAX(CAST(SUBSTRING(invoice_number, 8) AS UNSIGNED)), 0) + 1
    INTO next_number
    FROM invoices
    WHERE invoice_number LIKE CONCAT(prefix, '-%');
    
    SET invoice_number = CONCAT(prefix, '-', LPAD(next_number, 6, '0'));
    SELECT invoice_number;
END //

-- Generate Transfer Code
CREATE PROCEDURE GenerateTransferCode()
BEGIN
    DECLARE next_number INT;
    DECLARE transfer_code VARCHAR(20);
    DECLARE prefix VARCHAR(3);
    
    SET prefix = DATE_FORMAT(NOW(), '%y%m');
    
    SELECT COALESCE(MAX(CAST(SUBSTRING(transfer_code, 8) AS UNSIGNED)), 0) + 1
    INTO next_number
    FROM inventory_transfers
    WHERE transfer_code LIKE CONCAT('TR', prefix, '-%');
    
    SET transfer_code = CONCAT('TR', prefix, '-', LPAD(next_number, 6, '0'));
    SELECT transfer_code;
END //

DELIMITER ;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- ADD FOREIGN KEY CONSTRAINTS
-- ============================================
ALTER TABLE users ADD FOREIGN KEY (branch_id) REFERENCES branches(branch_id) ON DELETE SET NULL;
ALTER TABLE clients ADD FOREIGN KEY (branch_id) REFERENCES branches(branch_id) ON DELETE CASCADE;
ALTER TABLE packages ADD FOREIGN KEY (branch_id) REFERENCES branches(branch_id) ON DELETE CASCADE;
ALTER TABLE package_items ADD FOREIGN KEY (package_id) REFERENCES packages(package_id) ON DELETE CASCADE;
ALTER TABLE package_items ADD FOREIGN KEY (inventory_id) REFERENCES inventory(inventory_id) ON DELETE CASCADE;
ALTER TABLE inventory ADD FOREIGN KEY (branch_id) REFERENCES branches(branch_id) ON DELETE CASCADE;
ALTER TABLE inventory_transactions ADD FOREIGN KEY (inventory_id) REFERENCES inventory(inventory_id) ON DELETE CASCADE;
ALTER TABLE inventory_transactions ADD FOREIGN KEY (performed_by) REFERENCES users(user_id) ON DELETE SET NULL;
ALTER TABLE inventory_transactions ADD FOREIGN KEY (branch_id) REFERENCES branches(branch_id) ON DELETE CASCADE;
ALTER TABLE inventory_transfers ADD FOREIGN KEY (source_branch_id) REFERENCES branches(branch_id) ON DELETE CASCADE;
ALTER TABLE inventory_transfers ADD FOREIGN KEY (destination_branch_id) REFERENCES branches(branch_id) ON DELETE CASCADE;
ALTER TABLE inventory_transfers ADD FOREIGN KEY (inventory_id) REFERENCES inventory(inventory_id) ON DELETE CASCADE;
ALTER TABLE inventory_transfers ADD FOREIGN KEY (requested_by) REFERENCES users(user_id) ON DELETE SET NULL;
ALTER TABLE inventory_transfers ADD FOREIGN KEY (approved_by) REFERENCES users(user_id) ON DELETE SET NULL;
ALTER TABLE inventory_transfers ADD FOREIGN KEY (received_by) REFERENCES users(user_id) ON DELETE SET NULL;
ALTER TABLE bookings ADD FOREIGN KEY (client_id) REFERENCES clients(client_id) ON DELETE CASCADE;
ALTER TABLE bookings ADD FOREIGN KEY (branch_id) REFERENCES branches(branch_id) ON DELETE CASCADE;
ALTER TABLE bookings ADD FOREIGN KEY (package_id) REFERENCES packages(package_id) ON DELETE SET NULL;
ALTER TABLE bookings ADD FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL;
ALTER TABLE bookings ADD FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL;
ALTER TABLE booking_items ADD FOREIGN KEY (booking_id) REFERENCES bookings(booking_id) ON DELETE CASCADE;
ALTER TABLE booking_items ADD FOREIGN KEY (inventory_id) REFERENCES inventory(inventory_id) ON DELETE CASCADE;
ALTER TABLE staff ADD FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL;
ALTER TABLE staff ADD FOREIGN KEY (branch_id) REFERENCES branches(branch_id) ON DELETE CASCADE;
ALTER TABLE event_assignments ADD FOREIGN KEY (booking_id) REFERENCES bookings(booking_id) ON DELETE CASCADE;
ALTER TABLE event_assignments ADD FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE CASCADE;
ALTER TABLE invoices ADD FOREIGN KEY (booking_id) REFERENCES bookings(booking_id) ON DELETE CASCADE;
ALTER TABLE invoices ADD FOREIGN KEY (client_id) REFERENCES clients(client_id) ON DELETE CASCADE;
ALTER TABLE invoices ADD FOREIGN KEY (branch_id) REFERENCES branches(branch_id) ON DELETE CASCADE;
ALTER TABLE invoices ADD FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL;
ALTER TABLE payments ADD FOREIGN KEY (invoice_id) REFERENCES invoices(invoice_id) ON DELETE CASCADE;
ALTER TABLE payments ADD FOREIGN KEY (booking_id) REFERENCES bookings(booking_id) ON DELETE CASCADE;
ALTER TABLE payments ADD FOREIGN KEY (client_id) REFERENCES clients(client_id) ON DELETE CASCADE;
ALTER TABLE payments ADD FOREIGN KEY (branch_id) REFERENCES branches(branch_id) ON DELETE CASCADE;
ALTER TABLE payments ADD FOREIGN KEY (received_by) REFERENCES users(user_id) ON DELETE SET NULL;
