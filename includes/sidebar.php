<?php
/**
 * Sidebar Navigation
 */

$role = getCurrentUserRole();
$branchId = getCurrentUserBranchId();

// Count pending bookings and invoices needing attention
$db = Database::getInstance();
$pendingBookingsCount = 0;
$pendingInvoicesCount = 0;
$confirmedBookingsWithoutInvoiceCount = 0;
$invoicesNeedingPaymentCount = 0;

if ($role === 'Super Admin') {
    $pendingBookingsCount = $db->fetchOne(
        "SELECT COUNT(*) as count FROM bookings WHERE status = 'Pending'"
    )['count'];
    $pendingInvoicesCount = $db->fetchOne(
        "SELECT COUNT(*) as count FROM invoices WHERE status = 'Sent'"
    )['count'];
    $confirmedBookingsWithoutInvoiceCount = $db->fetchOne(
        "SELECT COUNT(*) as count FROM bookings b 
         LEFT JOIN invoices i ON b.booking_id = i.booking_id 
         WHERE b.status = 'Confirmed' AND i.invoice_id IS NULL"
    )['count'];
    $invoicesNeedingPaymentCount = $db->fetchOne(
        "SELECT COUNT(*) as count FROM invoices 
         WHERE status IN ('Sent', 'Partial') 
         AND total_amount > (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = invoices.invoice_id)"
    )['count'];
} else {
    $pendingBookingsCount = $db->fetchOne(
        "SELECT COUNT(*) as count FROM bookings WHERE status = 'Pending' AND branch_id = ?",
        [$branchId]
    )['count'];
    $pendingInvoicesCount = $db->fetchOne(
        "SELECT COUNT(*) as count FROM invoices WHERE status = 'Sent' AND branch_id = ?",
        [$branchId]
    )['count'];
    $confirmedBookingsWithoutInvoiceCount = $db->fetchOne(
        "SELECT COUNT(*) as count FROM bookings b 
         LEFT JOIN invoices i ON b.booking_id = i.booking_id 
         WHERE b.status = 'Confirmed' AND i.invoice_id IS NULL AND b.branch_id = ?",
        [$branchId]
    )['count'];
    $invoicesNeedingPaymentCount = $db->fetchOne(
        "SELECT COUNT(*) as count FROM invoices 
         WHERE status IN ('Sent', 'Partial') AND branch_id = ?
         AND total_amount > (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = invoices.invoice_id)",
        [$branchId]
    )['count'];
}
?>

<nav id="sidebar" class="sidebar">
    <div class="sidebar-header">
        <h3>
            <i class="bi bi-calendar-check me-2"></i>
            <span class="company-name-cursive"><?php echo getCompanyName(); ?></span>
        </h3>
    </div>
    <button class="mobile-menu-toggle" id="sidebarClose" style="position: absolute; top: 15px; right: 15px; color: white;">
        <i class="bi bi-x-lg"></i>
    </button>
    
    <ul class="list-unstyled components">
        <li class="<?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
            <a href="dashboard.php">
                <i class="bi bi-speedometer2 me-2"></i>
                Dashboard
            </a>
        </li>
        
        <?php if ($role === 'Super Admin'): ?>
        <li class="<?php echo basename($_SERVER['PHP_SELF']) === 'branches.php' ? 'active' : ''; ?>">
            <a href="branches.php">
                <i class="bi bi-building me-2"></i>
                Branches
            </a>
        </li>
        <?php endif; ?>
        
        <li class="<?php echo basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : ''; ?>">
            <a href="users.php">
                <i class="bi bi-people me-2"></i>
                Users
            </a>
        </li>
        
        <li class="<?php echo basename($_SERVER['PHP_SELF']) === 'clients.php' ? 'active' : ''; ?>">
            <a href="clients.php">
                <i class="bi bi-person-lines-fill me-2"></i>
                Clients
            </a>
        </li>
        
        <li class="<?php echo basename($_SERVER['PHP_SELF']) === 'bookings.php' ? 'active' : ''; ?>">
            <a href="bookings.php">
                <i class="bi bi-calendar-event me-2"></i>
                Booking/Rent
                <?php if ($pendingBookingsCount > 0): ?>
                <span class="notification-badge"><?php echo $pendingBookingsCount; ?></span>
                <?php endif; ?>
            </a>
        </li>
        
        <li class="<?php echo basename($_SERVER['PHP_SELF']) === 'packages.php' ? 'active' : ''; ?>">
            <a href="packages.php">
                <i class="bi bi-box-seam me-2"></i>
                Packages
            </a>
        </li>
        
        <li class="<?php echo basename($_SERVER['PHP_SELF']) === 'inventory.php' ? 'active' : ''; ?>">
            <a href="inventory.php">
                <i class="bi bi-boxes me-2"></i>
                Inventory
            </a>
        </li>
        
        <?php if ($role === 'Super Admin'): ?>
        <li class="<?php echo basename($_SERVER['PHP_SELF']) === 'transfers.php' ? 'active' : ''; ?>">
            <a href="transfers.php">
                <i class="bi bi-arrow-left-right me-2"></i>
                Transfers
            </a>
        </li>
        <?php endif; ?>
        
        <li class="<?php echo basename($_SERVER['PHP_SELF']) === 'invoices.php' ? 'active' : ''; ?>">
            <a href="invoices.php">
                <i class="bi bi-receipt me-2"></i>
                Invoices
                <?php if ($pendingInvoicesCount > 0 || $confirmedBookingsWithoutInvoiceCount > 0): ?>
                <span class="notification-badge"><?php echo $pendingInvoicesCount + $confirmedBookingsWithoutInvoiceCount; ?></span>
                <?php endif; ?>
            </a>
        </li>
        
        <li class="<?php echo basename($_SERVER['PHP_SELF']) === 'payments.php' ? 'active' : ''; ?>">
            <a href="payments.php">
                <i class="bi bi-calendar-event me-2"></i>
                Payments
                <?php if ($invoicesNeedingPaymentCount > 0): ?>
                <span class="notification-badge"><?php echo $invoicesNeedingPaymentCount; ?></span>
                <?php endif; ?>
            </a>
        </li>
        
        <li class="<?php echo basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : ''; ?>">
            <a href="reports.php">
                <i class="bi bi-graph-up me-2"></i>
                Reports
            </a>
        </li>
        
        
        <li class="<?php echo basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : ''; ?>">
            <a href="settings.php">
                <i class="bi bi-gear me-2"></i>
                Settings
            </a>
        </li>
    </ul>
    
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar">
                <i class="bi bi-person-circle"></i>
            </div>
            <div class="user-details">
                <div class="user-name"><?php echo $_SESSION['full_name']; ?></div>
                <div class="user-role"><?php echo $role; ?></div>
                <?php if ($branchId): ?>
                <div class="user-branch">
                    <?php 
                    $db = Database::getInstance();
                    $branch = $db->fetchOne("SELECT branch_name FROM branches WHERE branch_id = ?", [$branchId]);
                    echo $branch['branch_name'] ?? '';
                    ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <a href="logout.php" class="btn btn-logout">
            <i class="bi bi-box-arrow-right me-2"></i>
            Logout
        </a>
    </div>
</nav>

<!-- Mobile Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>
