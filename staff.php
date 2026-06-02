<?php
/**
 * Staff Management Page
 */

$pageTitle = 'Staff Management';
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
            $staff_code = 'STF' . date('ym') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $user_id = intval($_POST['user_id']) ?: null;
            $full_name = sanitize($_POST['full_name']);
            $position = sanitize($_POST['position']);
            $phone = sanitize($_POST['phone']);
            $email = sanitize($_POST['email']);
            $staff_branch_id = $role === 'Super Admin' ? intval($_POST['branch_id']) : $branchId;
            $hire_date = $_POST['hire_date'] ?: null;
            
            $staff_id = $db->insert('staff', [
                'staff_code' => $staff_code,
                'user_id' => $user_id,
                'full_name' => $full_name,
                'position' => $position,
                'phone' => $phone,
                'email' => $email,
                'branch_id' => $staff_branch_id,
                'hire_date' => $hire_date,
                'status' => 'Active'
            ]);
            
            logAudit('Created', 'Staff', $staff_id, "Created staff: $full_name");
            setFlashMessage('success', 'Staff added successfully.');
        } elseif ($action === 'edit') {
            $staff_id = intval($_POST['staff_id']);
            $full_name = sanitize($_POST['full_name']);
            $position = sanitize($_POST['position']);
            $phone = sanitize($_POST['phone']);
            $email = sanitize($_POST['email']);
            $hire_date = $_POST['hire_date'] ?: null;
            $status = $_POST['status'];
            
            $db->update('staff', [
                'full_name' => $full_name,
                'position' => $position,
                'phone' => $phone,
                'email' => $email,
                'hire_date' => $hire_date,
                'status' => $status
            ], 'staff_id = ?', [$staff_id]);
            
            logAudit('Updated', 'Staff', $staff_id, "Updated staff: $full_name");
            setFlashMessage('success', 'Staff updated successfully.');
        } elseif ($action === 'delete') {
            $staff_id = intval($_POST['staff_id']);
            
            // Check if staff has event assignments
            $has_assignments = $db->fetchOne("SELECT COUNT(*) as count FROM event_assignments WHERE staff_id = ?", [$staff_id])['count'];
            if ($has_assignments > 0) {
                setFlashMessage('error', 'Cannot delete staff with existing event assignments.');
            } else {
                $staff = $db->fetchOne("SELECT full_name FROM staff WHERE staff_id = ?", [$staff_id]);
                $db->delete('staff', 'staff_id = ?', [$staff_id]);
                logAudit('Deleted', 'Staff', $staff_id, "Deleted staff: " . $staff['full_name']);
                setFlashMessage('success', 'Staff deleted successfully.');
            }
        }
        
        redirect('staff.php');
    }
}

// Build WHERE clause
$whereClause = '';
$params = [];

if ($role !== 'Super Admin') {
    $whereClause = 'WHERE s.branch_id = ?';
    $params[] = $branchId;
}

// Get staff
$staff = $db->fetchAll(
    "SELECT s.*, b.branch_name, u.username 
     FROM staff s 
     LEFT JOIN branches b ON s.branch_id = b.branch_id 
     LEFT JOIN users u ON s.user_id = u.user_id 
     $whereClause 
     ORDER BY s.full_name ASC",
    $params
);

// Get users for dropdown (staff without staff records)
$users = $db->fetchAll(
    "SELECT user_id, username, full_name 
     FROM users 
     WHERE role = 'Staff' 
     AND user_id NOT IN (SELECT user_id FROM staff WHERE user_id IS NOT NULL)" .
    ($role !== 'Super Admin' ? ' AND branch_id = ?' : '') .
    " ORDER BY full_name ASC",
    $role !== 'Super Admin' ? [$branchId] : []
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
            <h1>Staff Management</h1>
            <p>Manage staff members</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStaffModal">
            <i class="bi bi-plus-lg me-2"></i>Add Staff
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
                            <th>Staff Code</th>
                            <th>Full Name</th>
                            <th>Position</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>User Account</th>
                            <?php if ($role === 'Super Admin'): ?>
                            <th>Branch</th>
                            <?php endif; ?>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($staff as $member): ?>
                        <tr>
                            <td><strong><?php echo $member['staff_code']; ?></strong></td>
                            <td><?php echo $member['full_name']; ?></td>
                            <td><?php echo $member['position']; ?></td>
                            <td><?php echo $member['phone']; ?></td>
                            <td><?php echo $member['email']; ?></td>
                            <td><?php echo $member['username'] ?? 'N/A'; ?></td>
                            <?php if ($role === 'Super Admin'): ?>
                            <td><?php echo $member['branch_name']; ?></td>
                            <?php endif; ?>
                            <td>
                                <span class="badge badge-<?php echo $member['status'] === 'Active' ? 'success' : 'danger'; ?>">
                                    <?php echo $member['status']; ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editStaffModal<?php echo $member['staff_id']; ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-danger btn-delete" onclick="deleteStaff(<?php echo $member['staff_id']; ?>, '<?php echo $member['full_name']; ?>')">
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

<!-- Add Staff Modal -->
<div class="modal fade" id="addStaffModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Staff</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label">Link to User Account</label>
                        <select class="form-select" name="user_id">
                            <option value="">No User Account</option>
                            <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['user_id']; ?>"><?php echo $user['full_name']; ?> (<?php echo $user['username']; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Optional: Link to existing staff user account</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Full Name *</label>
                        <input type="text" class="form-control" name="full_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Position</label>
                        <input type="text" class="form-control" name="position">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" class="form-control" name="phone">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Hire Date</label>
                        <input type="date" class="form-control" name="hire_date">
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
                    <button type="submit" class="btn btn-primary">Add Staff</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Staff Modals -->
<?php foreach ($staff as $member): ?>
<div class="modal fade" id="editStaffModal<?php echo $member['staff_id']; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Staff</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="staff_id" value="<?php echo $member['staff_id']; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Staff Code</label>
                        <input type="text" class="form-control" value="<?php echo $member['staff_code']; ?>" disabled>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Full Name *</label>
                        <input type="text" class="form-control" name="full_name" value="<?php echo $member['full_name']; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Position</label>
                        <input type="text" class="form-control" name="position" value="<?php echo $member['position']; ?>">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" class="form-control" name="phone" value="<?php echo $member['phone']; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" value="<?php echo $member['email']; ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Hire Date</label>
                        <input type="date" class="form-control" name="hire_date" value="<?php echo $member['hire_date']; ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Status *</label>
                        <select class="form-select" name="status" required>
                            <option value="Active" <?php echo $member['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?php echo $member['status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Staff</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteStaffModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete staff <strong id="deleteStaffName"></strong>?</p>
                <form method="POST" id="deleteStaffForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="staff_id" id="deleteStaffId">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="deleteStaffForm" class="btn btn-danger">Delete</button>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/sidebar.php'; ?>
<?php require_once 'includes/footer.php'; ?>

<script>
function deleteStaff(staffId, staffName) {
    document.getElementById('deleteStaffId').value = staffId;
    document.getElementById('deleteStaffName').textContent = staffName;
    new bootstrap.Modal(document.getElementById('deleteStaffModal')).show();
}
</script>
