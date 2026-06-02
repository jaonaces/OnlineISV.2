<?php
/**
 * Audit Trail Page (Super Admin Only)
 */

$pageTitle = 'Audit Logs';
require_once 'config/config.php';
require_once 'includes/functions.php';

requireSuperAdmin();

$db = Database::getInstance();

// Get filters
$filter_user = $_GET['user_id'] ?? '';
$filter_module = $_GET['module'] ?? '';
$filter_action = $_GET['action'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';

// Build WHERE clause
$whereClause = 'WHERE 1=1';
$params = [];

if ($filter_user) {
    $whereClause .= ' AND user_id = ?';
    $params[] = $filter_user;
}

if ($filter_module) {
    $whereClause .= ' AND module = ?';
    $params[] = $filter_module;
}

if ($filter_action) {
    $whereClause .= ' AND action = ?';
    $params[] = $filter_action;
}

if ($filter_date_from) {
    $whereClause .= ' AND DATE(created_at) >= ?';
    $params[] = $filter_date_from;
}

if ($filter_date_to) {
    $whereClause .= ' AND DATE(created_at) <= ?';
    $params[] = $filter_date_to;
}

// Get audit logs
$audit_logs = $db->fetchAll(
    "SELECT a.*, b.branch_name 
     FROM audit_logs a 
     LEFT JOIN branches b ON a.branch_id = b.branch_id 
     $whereClause 
     ORDER BY a.created_at DESC LIMIT 500",
    $params
);

// Get unique users, modules, and actions for filters
$users = $db->fetchAll("SELECT DISTINCT user_id, username FROM audit_logs ORDER BY username ASC");
$modules = $db->fetchAll("SELECT DISTINCT module FROM audit_logs ORDER BY module ASC");
$actions = $db->fetchAll("SELECT DISTINCT action FROM audit_logs ORDER BY action ASC");

$csrf_token = generateCSRFToken();
require_once 'includes/header.php';
?>

<div class="main-content">
    <div class="top-bar">
        <div class="page-title">
            <h1>Audit Trail</h1>
            <p>View system activity logs</p>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">User</label>
                    <select class="form-select" name="user_id">
                        <option value="">All Users</option>
                        <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['user_id']; ?>" <?php echo $filter_user == $user['user_id'] ? 'selected' : ''; ?>>
                            <?php echo $user['username']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Module</label>
                    <select class="form-select" name="module">
                        <option value="">All Modules</option>
                        <?php foreach ($modules as $mod): ?>
                        <option value="<?php echo $mod['module']; ?>" <?php echo $filter_module === $mod['module'] ? 'selected' : ''; ?>>
                            <?php echo $mod['module']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Action</label>
                    <select class="form-select" name="action">
                        <option value="">All Actions</option>
                        <?php foreach ($actions as $act): ?>
                        <option value="<?php echo $act['action']; ?>" <?php echo $filter_action === $act['action'] ? 'selected' : ''; ?>>
                            <?php echo $act['action']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">From Date</label>
                    <input type="date" class="form-control" name="date_from" value="<?php echo $filter_date_from; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">To Date</label>
                    <input type="date" class="form-control" name="date_to" value="<?php echo $filter_date_to; ?>">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-filter me-2"></i>Filter
                    </button>
                    <a href="audit.php" class="btn btn-secondary">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover datatable">
                    <thead>
                        <tr>
                            <th>Date/Time</th>
                            <th>User</th>
                            <th>Branch</th>
                            <th>Action</th>
                            <th>Module</th>
                            <th>Description</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($audit_logs as $log): ?>
                        <tr>
                            <td><?php echo formatDateTime($log['created_at']); ?></td>
                            <td><?php echo $log['username']; ?></td>
                            <td><?php echo $log['branch_name'] ?? 'N/A'; ?></td>
                            <td>
                                <span class="badge badge-<?php 
                                    echo $log['action'] === 'Created' ? 'success' : 
                                        ($log['action'] === 'Updated' ? 'info' : 
                                        ($log['action'] === 'Deleted' ? 'danger' : 'warning')); 
                                ?>">
                                    <?php echo $log['action']; ?>
                                </span>
                            </td>
                            <td><?php echo $log['module']; ?></td>
                            <td><?php echo $log['description'] ?? '-'; ?></td>
                            <td><?php echo $log['ip_address']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/sidebar.php'; ?>
<?php require_once 'includes/footer.php'; ?>
