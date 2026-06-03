<?php
/**
 * Branch Management Page (Super Admin Only)
 */

$pageTitle = 'Branch Management';
require_once 'config/config.php';
require_once 'includes/functions.php';

requireSuperAdmin();

$db = Database::getInstance();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrf_token)) {
        setFlashMessage('error', 'Invalid request. Please try again.');
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add') {
            $branch_code = strtoupper(sanitize($_POST['branch_code']));
            $branch_name = sanitize($_POST['branch_name']);
            $address = sanitize($_POST['address']);
            $contact_number = sanitize($_POST['contact_number']);
            $email = sanitize($_POST['email']);
            $manager_name = sanitize($_POST['manager_name']);
            
            // Check if branch code exists
            $existing = $db->fetchOne("SELECT branch_id FROM branches WHERE branch_code = ?", [$branch_code]);
            if ($existing) {
                setFlashMessage('error', 'Branch code already exists.');
            } else {
                $branch_id = $db->insert('branches', [
                    'branch_code' => $branch_code,
                    'branch_name' => $branch_name,
                    'address' => $address,
                    'contact_number' => $contact_number,
                    'email' => $email,
                    'manager_name' => $manager_name,
                    'status' => 'Active'
                ]);
                
                logAudit('Created', 'Branch', $branch_id, "Created branch: $branch_name");
                setFlashMessage('success', 'Branch added successfully.');
            }
        } elseif ($action === 'edit') {
            $branch_id = intval($_POST['branch_id']);
            $branch_name = sanitize($_POST['branch_name']);
            $address = sanitize($_POST['address']);
            $contact_number = sanitize($_POST['contact_number']);
            $email = sanitize($_POST['email']);
            $manager_name = sanitize($_POST['manager_name']);
            $status = $_POST['status'];
            
            $db->update('branches', [
                'branch_name' => $branch_name,
                'address' => $address,
                'contact_number' => $contact_number,
                'email' => $email,
                'manager_name' => $manager_name,
                'status' => $status
            ], 'branch_id = ?', [$branch_id]);
            
            logAudit('Updated', 'Branch', $branch_id, "Updated branch: $branch_name");
            setFlashMessage('success', 'Branch updated successfully.');
        } elseif ($action === 'delete') {
            $branch_id = intval($_POST['branch_id']);
            
            // Check if branch has users
            $has_users = $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE branch_id = ?", [$branch_id])['count'];
            if ($has_users > 0) {
                setFlashMessage('error', 'Cannot delete branch with assigned users.');
            } else {
                $branch = $db->fetchOne("SELECT branch_name FROM branches WHERE branch_id = ?", [$branch_id]);
                $db->delete('branches', 'branch_id = ?', [$branch_id]);
                setFlashMessage('success', 'Branch deleted successfully.');
            }
        }
        
        redirect('branches.php');
    }
}

// Get all branches
$branches = $db->fetchAll("SELECT * FROM branches ORDER BY branch_code ASC");

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
                <h1>Branch Management</h1>
                <p>Manage all branches</p>
            </div>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBranchModal">
            <i class="bi bi-plus-lg me-2"></i>Add Branch
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
                            <th>Branch Code</th>
                            <th>Branch Name</th>
                            <th>Address</th>
                            <th>Contact Number</th>
                            <th>Email</th>
                            <th>Manager</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($branches as $branch): ?>
                        <tr>
                            <td><strong><?php echo $branch['branch_code']; ?></strong></td>
                            <td><?php echo $branch['branch_name']; ?></td>
                            <td><?php echo $branch['address']; ?></td>
                            <td><?php echo $branch['contact_number']; ?></td>
                            <td><?php echo $branch['email']; ?></td>
                            <td><?php echo $branch['manager_name']; ?></td>
                            <td>
                                <span class="badge badge-<?php echo $branch['status'] === 'Active' ? 'success' : 'danger'; ?>">
                                    <?php echo $branch['status']; ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editBranchModal<?php echo $branch['branch_id']; ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-danger btn-delete" onclick="deleteBranch(<?php echo $branch['branch_id']; ?>, '<?php echo $branch['branch_name']; ?>')">
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

<!-- Add Branch Modal -->
<div class="modal fade" id="addBranchModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Branch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label">Branch Code *</label>
                        <input type="text" class="form-control" name="branch_code" required placeholder="e.g., BR001">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Branch Name *</label>
                        <input type="text" class="form-control" name="branch_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address *</label>
                        <textarea class="form-control" name="address" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact Number *</label>
                        <input type="text" class="form-control" name="contact_number" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Manager Name</label>
                        <input type="text" class="form-control" name="manager_name">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Branch</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Branch Modals -->
<?php foreach ($branches as $branch): ?>
<div class="modal fade" id="editBranchModal<?php echo $branch['branch_id']; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Branch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="branch_id" value="<?php echo $branch['branch_id']; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Branch Code</label>
                        <input type="text" class="form-control" value="<?php echo $branch['branch_code']; ?>" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Branch Name *</label>
                        <input type="text" class="form-control" name="branch_name" value="<?php echo $branch['branch_name']; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address *</label>
                        <textarea class="form-control" name="address" rows="3" required><?php echo $branch['address']; ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contact Number *</label>
                        <input type="text" class="form-control" name="contact_number" value="<?php echo $branch['contact_number']; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" value="<?php echo $branch['email']; ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Manager Name</label>
                        <input type="text" class="form-control" name="manager_name" value="<?php echo $branch['manager_name']; ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status *</label>
                        <select class="form-select" name="status" required>
                            <option value="Active" <?php echo $branch['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?php echo $branch['status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Branch</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteBranchModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete branch <strong id="deleteBranchName"></strong>?</p>
                <form method="POST" id="deleteBranchForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="branch_id" id="deleteBranchId">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="deleteBranchForm" class="btn btn-danger">Delete</button>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/sidebar.php'; ?>
<?php require_once 'includes/footer.php'; ?>

<script>
function deleteBranch(branchId, branchName) {
    document.getElementById('deleteBranchId').value = branchId;
    document.getElementById('deleteBranchName').textContent = branchName;
    new bootstrap.Modal(document.getElementById('deleteBranchModal')).show();
}
</script>
