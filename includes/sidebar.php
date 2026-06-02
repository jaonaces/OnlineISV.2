<?php
/**
 * Sidebar Navigation
 */

$role = getCurrentUserRole();
$branchId = getCurrentUserBranchId();
?>

<nav id="sidebar" class="sidebar">
    <div class="sidebar-header">
        <h3>
            <i class="bi bi-calendar-check me-2"></i>
            Event Planner
        </h3>
    </div>
    
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
                Bookings
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
        
        <li class="<?php echo basename($_SERVER['PHP_SELF']) === 'staff.php' ? 'active' : ''; ?>">
            <a href="staff.php">
                <i class="bi bi-person-badge me-2"></i>
                Staff
            </a>
        </li>
        
        <li class="<?php echo basename($_SERVER['PHP_SELF']) === 'calendar.php' ? 'active' : ''; ?>">
            <a href="calendar.php">
                <i class="bi bi-calendar3 me-2"></i>
                Calendar
            </a>
        </li>
        
        <li class="<?php echo basename($_SERVER['PHP_SELF']) === 'invoices.php' ? 'active' : ''; ?>">
            <a href="invoices.php">
                <i class="bi bi-receipt me-2"></i>
                Invoices
            </a>
        </li>
        
        <li class="<?php echo basename($_SERVER['PHP_SELF']) === 'payments.php' ? 'active' : ''; ?>">
            <a href="payments.php">
                <i class="bi bi-cash-coin me-2"></i>
                Payments
            </a>
        </li>
        
        <li class="<?php echo basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : ''; ?>">
            <a href="reports.php">
                <i class="bi bi-graph-up me-2"></i>
                Reports
            </a>
        </li>
        
        <?php if ($role === 'Super Admin'): ?>
        <li class="<?php echo basename($_SERVER['PHP_SELF']) === 'audit.php' ? 'active' : ''; ?>">
            <a href="audit.php">
                <i class="bi bi-shield-lock me-2"></i>
                Audit Logs
            </a>
        </li>
        <?php endif; ?>
        
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
