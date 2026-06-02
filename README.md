# Event Planner Pro - Multi-Branch Inventory & Booking Management System

A comprehensive, professional web-based event planning management system with multi-branch support, role-based access control, inventory management, and booking capabilities.

## Features

### Core Modules
- **Dashboard** - Real-time analytics with Chart.js, branch filtering, and key metrics
- **Branch Management** - Manage multiple branches (Super Admin only)
- **User Management** - Role-based access control (Super Admin, Branch Admin, Staff)
- **Client Management** - Comprehensive client database with booking history
- **Booking Management** - Full booking lifecycle with status workflow
- **Package Management** - Create and manage event packages
- **Inventory Management** - Track equipment, stock levels, and conditions
- **Inventory Transfers** - Transfer inventory between branches (Super Admin only)
- **Staff Management** - Manage staff assignments and scheduling
- **Event Calendar** - Daily, weekly, and monthly calendar views
- **Invoicing** - Generate invoices with tax calculations
- **Payment Tracking** - Record and track payments
- **Reports** - Generate booking, inventory, financial, and branch reports
- **Audit Trail** - Complete system activity logging (Super Admin only)
- **Settings** - Configure system-wide settings

### Security Features
- CSRF Protection
- Password Hashing (Bcrypt)
- SQL Injection Prevention (Prepared Statements)
- Session Management
- Role-Based Access Control (RBAC)
- Branch-Based Data Filtering
- Input Validation

## Technology Stack

- **Backend**: PHP 8
- **Database**: MySQL (XAMPP)
- **Frontend**: HTML5, CSS3, Bootstrap 5
- **JavaScript**: Vanilla JS, AJAX
- **Libraries**: DataTables, Chart.js, FullCalendar
- **Icons**: Bootstrap Icons

## Installation

### Prerequisites
- XAMPP (or equivalent PHP/MySQL environment)
- PHP 8.0 or higher
- MySQL 5.7 or higher
- Modern web browser

### Setup Instructions

1. **Extract the Files**
   ```
   Copy the project files to: C:\xampp\htdocs\OnlineIsv.2\
   ```

2. **Create the Database**
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Create a new database named `event_planner_db`
   - Import the SQL schema from `database/schema.sql`
   - This will create all required tables and default data

3. **Configure Database Connection**
   - Edit `config/config.php` if needed:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   define('DB_NAME', 'event_planner_db');
   ```

4. **Start Apache and MySQL**
   - Open XAMPP Control Panel
   - Start Apache and MySQL services

5. **Access the Application**
   - Open your browser and navigate to: http://localhost/OnlineIsv.2/
   - Default Super Admin credentials:
     - Username: `admin`
     - Password: `admin123`

## User Roles

### Super Admin
- Full system access
- Manage all branches
- View all data from all branches
- Manage all users
- Transfer inventory between branches
- Access audit logs
- Configure system settings

### Branch Admin
- Manage bookings within their branch
- Manage inventory within their branch
- Manage staff assigned to their branch
- View branch reports
- Approve bookings
- Cannot view other branches' data

### Staff
- View bookings
- Update booking status
- Manage event setup and completion
- View inventory
- Record inventory usage and returns
- Cannot access financial reports
- Cannot delete records
- Cannot manage users

## Database Schema

The system includes the following tables:
- `branches` - Branch information
- `users` - User accounts and roles
- `clients` - Client information
- `bookings` - Event bookings
- `booking_items` - Items assigned to bookings
- `packages` - Event packages
- `package_items` - Items in packages
- `inventory` - Inventory items
- `inventory_transactions` - Inventory movement history
- `inventory_transfers` - Inter-branch transfers
- `staff` - Staff members
- `event_assignments` - Staff to event assignments
- `invoices` - Client invoices
- `payments` - Payment records
- `audit_logs` - System activity logs
- `system_settings` - Configuration settings

## Booking Workflow

1. **Inquiry** - Initial booking request
2. **Pending** - Booking under review
3. **Confirmed** - Booking approved and inventory reserved
4. **Ongoing** - Event in progress
5. **Completed** - Event finished
6. **Cancelled** - Booking cancelled

## Inventory Conditions

- **Available** - Ready for use
- **Reserved** - Reserved for a booking
- **In Use** - Currently being used
- **Maintenance** - Under maintenance
- **Damaged** - Needs repair/replacement

## Support

For issues or questions:
- Check the audit logs for system errors
- Verify database connections
- Ensure proper file permissions
- Check PHP error logs

## License

This system is provided as-is for event planning businesses.

## Credits

Developed with PHP 8, MySQL, Bootstrap 5, and modern web technologies.
