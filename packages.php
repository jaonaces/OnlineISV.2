<?php
/**
 * Package Management Page
 */

$pageTitle = 'Package Management';
require_once 'config/config.php';
require_once 'includes/functions.php';

requireLogin();

$db = Database::getInstance();
$role = getCurrentUserRole();
$branchId = getCurrentUserBranchId();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrf_token)) {
        setFlashMessage('error', 'Invalid request. Please try again.');
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add') {
            $package_code = 'PKG' . date('ym') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $package_name = sanitize($_POST['package_name']);
            $description = sanitize($_POST['description']);
            $event_type = $_POST['event_type'];
            $base_price = floatval($_POST['base_price']);
            $package_branch_id = $role === 'Super Admin' ? intval($_POST['branch_id']) : $branchId;
            
            $package_id = $db->insert('packages', [
                'package_code' => $package_code,
                'package_name' => $package_name,
                'description' => $description,
                'event_type' => $event_type,
                'base_price' => $base_price,
                'branch_id' => $package_branch_id,
                'status' => 'Active'
            ]);
            
            logAudit('Created', 'Package', $package_id, "Created package: $package_name");
            setFlashMessage('success', 'Package added successfully.');
        } elseif ($action === 'edit') {
            $package_id = intval($_POST['package_id']);
            $package_name = sanitize($_POST['package_name']);
            $description = sanitize($_POST['description']);
            $event_type = $_POST['event_type'];
            $base_price = floatval($_POST['base_price']);
            $status = $_POST['status'];
            
            $db->update('packages', [
                'package_name' => $package_name,
                'description' => $description,
                'event_type' => $event_type,
                'base_price' => $base_price,
                'status' => $status
            ], 'package_id = ?', [$package_id]);
            
            logAudit('Updated', 'Package', $package_id, "Updated package: $package_name");
            setFlashMessage('success', 'Package updated successfully.');
        } elseif ($action === 'delete') {
            $package_id = intval($_POST['package_id']);
            
            // Check if package is used in bookings
            $has_bookings = $db->fetchOne("SELECT COUNT(*) as count FROM bookings WHERE package_id = ?", [$package_id])['count'];
            if ($has_bookings > 0) {
                setFlashMessage('error', 'Cannot delete package with existing bookings.');
            } else {
                $package = $db->fetchOne("SELECT package_name FROM packages WHERE package_id = ?", [$package_id]);
                $db->delete('packages', 'package_id = ?', [$package_id]);
                logAudit('Deleted', 'Package', $package_id, "Deleted package: " . $package['package_name']);
                setFlashMessage('success', 'Package deleted successfully.');
            }
        }
        
        redirect('packages.php');
    }
}

// Build WHERE clause
$whereClause = '';
$params = [];

if ($role !== 'Super Admin') {
    $whereClause = 'WHERE p.branch_id = ?';
    $params[] = $branchId;
}

// Get packages
$packages = $db->fetchAll(
    "SELECT p.*, b.branch_name 
     FROM packages p 
     LEFT JOIN branches b ON p.branch_id = b.branch_id 
     $whereClause 
     ORDER BY p.package_name ASC",
    $params
);

// Get branches for dropdown (Super Admin only)
$branches = [];
if ($role === 'Super Admin') {
    $branches = $db->fetchAll("SELECT branch_id, branch_name FROM branches WHERE status = 'Active' ORDER BY branch_name ASC");
}

$csrf_token = generateCSRFToken();
require_once 'includes/header.php';
?>

<div class="main-content">
    <div class="top-bar">
        <div class="page-title">
            <h1>Package Management</h1>
            <p>Manage event packages</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPackageModal">
            <i class="bi bi-plus-lg me-2"></i>Add Package
        </button>
    </div>

    <?php $flash = getFlashMessage(); ?>
    <?php if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show" role="alert">
        <?php echo $flash['message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover datatable">
                    <thead>
                        <tr>
                            <th>Package Code</th>
                            <th>Package Name</th>
                            <th>Event Type</th>
                            <th>Base Price</th>
                            <?php if ($role === 'Super Admin'): ?>
                            <th>Branch</th>
                            <?php endif; ?>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($packages as $package): ?>
                        <tr>
                            <td><strong><?php echo $package['package_code']; ?></strong></td>
                            <td><?php echo $package['package_name']; ?></td>
                            <td><?php echo $package['event_type']; ?></td>
                            <td><?php echo formatCurrency($package['base_price']); ?></td>
                            <?php if ($role === 'Super Admin'): ?>
                            <td><?php echo $package['branch_name']; ?></td>
                            <?php endif; ?>
                            <td>
                                <span class="badge badge-<?php echo $package['status'] === 'Active' ? 'success' : 'danger'; ?>">
                                    <?php echo $package['status']; ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editPackageModal<?php echo $package['package_id']; ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-danger btn-delete" onclick="deletePackage(<?php echo $package['package_id']; ?>, '<?php echo $package['package_name']; ?>')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Package Modal -->
<div class="modal fade" id="addPackageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Package</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label">Package Name *</label>
                        <input type="text" class="form-control" name="package_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Event Type *</label>
                        <select class="form-select" name="event_type" required>
                            <option value="">Select Type</option>
                            <option value="Wedding">Wedding</option>
                            <option value="Birthday">Birthday</option>
                            <option value="Corporate Event">Corporate Event</option>
                            <option value="Debut">Debut</option>
                            <option value="Catering">Catering</option>
                            <option value="Rental">Rental</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Base Price *</label>
                        <input type="number" class="form-control" name="base_price" required min="0" step="0.01">
                    </div>
                    <?php if ($role === 'Super Admin'): ?>
                    <div class="mb-3">
                        <label class="form-label">Branch *</label>
                        <select class="form-select" name="branch_id" required>
                            <?php foreach ($branches as $branch): ?>
                            <option value="<?php echo $branch['branch_id']; ?>"><?php echo $branch['branch_name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Package</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Package Modals -->
<?php foreach ($packages as $package): ?>
<div class="modal fade" id="editPackageModal<?php echo $package['package_id']; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Package</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="package_id" value="<?php echo $package['package_id']; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Package Code</label>
                        <input type="text" class="form-control" value="<?php echo $package['package_code']; ?>" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Package Name *</label>
                        <input type="text" class="form-control" name="package_name" value="<?php echo $package['package_name']; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"><?php echo $package['description']; ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Event Type *</label>
                        <select class="form-select" name="event_type" required>
                            <option value="Wedding" <?php echo $package['event_type'] === 'Wedding' ? 'selected' : ''; ?>>Wedding</option>
                            <option value="Birthday" <?php echo $package['event_type'] === 'Birthday' ? 'selected' : ''; ?>>Birthday</option>
                            <option value="Corporate Event" <?php echo $package['event_type'] === 'Corporate Event' ? 'selected' : ''; ?>>Corporate Event</option>
                            <option value="Debut" <?php echo $package['event_type'] === 'Debut' ? 'selected' : ''; ?>>Debut</option>
                            <option value="Catering" <?php echo $package['event_type'] === 'Catering' ? 'selected' : ''; ?>>Catering</option>
                            <option value="Rental" <?php echo $package['event_type'] === 'Rental' ? 'selected' : ''; ?>>Rental</option>
                            <option value="Other" <?php echo $package['event_type'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Base Price *</label>
                        <input type="number" class="form-control" name="base_price" value="<?php echo $package['base_price']; ?>" required min="0" step="0.01">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status *</label>
                        <select class="form-select" name="status" required>
                            <option value="Active" <?php echo $package['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?php echo $package['status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Package</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deletePackageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete package <strong id="deletePackageName"></strong>?</p>
                <form method="POST" id="deletePackageForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="package_id" id="deletePackageId">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="deletePackageForm" class="btn btn-danger">Delete</button>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/sidebar.php'; ?>
<?php require_once 'includes/footer.php'; ?>

<script>
function deletePackage(packageId, packageName) {
    document.getElementById('deletePackageId').value = packageId;
    document.getElementById('deletePackageName').textContent = packageName;
    new bootstrap.Modal(document.getElementById('deletePackageModal')).show();
}
</script>
