<?php
/**
 * Settings Page
 */

$pageTitle = 'Settings';
require_once 'config/config.php';
require_once 'includes/functions.php';

requireLogin();

$db = Database::getInstance();
$role = getCurrentUserRole();

// Only Super Admin can access settings
if ($role !== 'Super Admin') {
    setFlashMessage('error', 'Access denied. Super Admin only.');
    redirect('dashboard.php');
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrf_token)) {
        setFlashMessage('error', 'Invalid request. Please try again.');
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'update_settings') {
            $company_name = sanitize($_POST['company_name']);
            $tax_rate = floatval($_POST['tax_rate'] ?? 0);
            $currency = sanitize($_POST['currency']);
            $date_format = sanitize($_POST['date_format']);
            $time_format = sanitize($_POST['time_format']);

            // Update settings
            $db->update('system_settings', ['setting_value' => $company_name], "setting_key = 'company_name'");
            $db->update('system_settings', ['setting_value' => $tax_rate], "setting_key = 'tax_rate'");
            $db->update('system_settings', ['setting_value' => $currency], "setting_key = 'currency'");
            $db->update('system_settings', ['setting_value' => $date_format], "setting_key = 'date_format'");
            $db->update('system_settings', ['setting_value' => $time_format], "setting_key = 'time_format'");

            setFlashMessage('success', 'Settings updated successfully.');
        }
        
        redirect('settings.php');
    }
}

// Get current settings
$settings = [];
$setting_rows = $db->fetchAll("SELECT setting_key, setting_value FROM system_settings");
foreach ($setting_rows as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$csrf_token = generateCSRFToken();
require_once 'includes/header.php';
?>

<div class="main-content">
    <div class="top-bar">
        <div class="d-flex align-items-center gap-3">
            <button class="mobile-menu-toggle" id="sidebarToggle">
                <i class="bi bi-list"></i>
            </button>
            <div class="page-title">
                <h1>System Settings</h1>
                <p>Configure system-wide settings</p>
            </div>
        </div>
    </div>

    <?php $flash = getFlashMessage(); ?>
    <?php if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show" role="alert">
        <?php echo $flash['message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-gear me-2"></i>
                    General Settings
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="update_settings">
                        
                        <div class="mb-3">
                            <label class="form-label">Company Name *</label>
                            <input type="text" class="form-control" name="company_name" value="<?php echo $settings['company_name'] ?? APP_NAME; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Currency *</label>
                            <select class="form-select" name="currency" required>
                                <option value="PHP" <?php echo ($settings['currency'] ?? 'PHP') === 'PHP' ? 'selected' : ''; ?>>PHP (Philippine Peso)</option>
                                <option value="USD" <?php echo ($settings['currency'] ?? 'PHP') === 'USD' ? 'selected' : ''; ?>>USD (US Dollar)</option>
                                <option value="EUR" <?php echo ($settings['currency'] ?? 'PHP') === 'EUR' ? 'selected' : ''; ?>>EUR (Euro)</option>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Date Format *</label>
                                <select class="form-select" name="date_format" required>
                                    <option value="Y-m-d" <?php echo ($settings['date_format'] ?? 'Y-m-d') === 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD</option>
                                    <option value="m/d/Y" <?php echo ($settings['date_format'] ?? 'Y-m-d') === 'm/d/Y' ? 'selected' : ''; ?>>MM/DD/YYYY</option>
                                    <option value="d/m/Y" <?php echo ($settings['date_format'] ?? 'Y-m-d') === 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Time Format *</label>
                                <select class="form-select" name="time_format" required>
                                    <option value="H:i" <?php echo ($settings['time_format'] ?? 'H:i') === 'H:i' ? 'selected' : ''; ?>>24 Hour (HH:MM)</option>
                                    <option value="h:i A" <?php echo ($settings['time_format'] ?? 'H:i') === 'h:i A' ? 'selected' : ''; ?>>12 Hour (HH:MM AM/PM)</option>
                                </select>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-2"></i>Save Settings
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-info-circle me-2"></i>
                    System Information
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <td><strong>App Name:</strong></td>
                            <td><?php echo APP_NAME; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Version:</strong></td>
                            <td><?php echo APP_VERSION; ?></td>
                        </tr>
                        <tr>
                            <td><strong>PHP Version:</strong></td>
                            <td><?php echo PHP_VERSION; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Database:</strong></td>
                            <td>MySQL</td>
                        </tr>
                        <tr>
                            <td><strong>Timezone:</strong></td>
                            <td><?php echo date_default_timezone_get(); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header">
                    <i class="bi bi-shield-check me-2"></i>
                    Security Notes
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>CSRF Protection Enabled</li>
                        <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Password Hashing (Bcrypt)</li>
                        <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>SQL Injection Prevention</li>
                        <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Session Management</li>
                        <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Role-Based Access Control</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/sidebar.php'; ?>
<?php require_once 'includes/footer.php'; ?>
