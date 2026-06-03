<?php
/**
 * User Management Page
 */

$pageTitle = 'User Management';
require_once 'config/config.php';
require_once 'includes/functions.php';

requireLogin();

$db = Database::getInstance();
$role = getCurrentUserRole();
$branchId = getCurrentUserBranchId();

// Only Super Admin can access all users
if ($role !== 'Super Admin' && $role !== 'Branch Admin') {
    setFlashMessage('error', 'Access denied.');
    redirect('dashboard.php');
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrf_token)) {
        setFlashMessage('error', 'Invalid request. Please try again.');
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add') {
            $username = sanitize($_POST['username']);
            $password = $_POST['password'];
            $full_name = sanitize($_POST['full_name']);
            $email = sanitize($_POST['email']);
            $phone = sanitize($_POST['phone']);
            $user_role = $_POST['role'];
            $user_branch_id = $role === 'Super Admin' ? intval($_POST['branch_id']) : $branchId;
            
            // Validate
            if (strlen($password) < PASSWORD_MIN_LENGTH) {
                setFlashMessage('error', 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.');
            } else {
                // Check if username exists
                $existing = $db->fetchOne("SELECT user_id FROM users WHERE username = ?", [$username]);
                if ($existing) {
                    setFlashMessage('error', 'Username already exists.');
                } else {
                    $user_id = $db->insert('users', [
                        'username' => $username,
                        'password' => hashPassword($password),
                        'full_name' => $full_name,
                        'email' => $email,
                        'phone' => $phone,
                        'role' => $user_role,
                        'branch_id' => $user_branch_id,
                        'status' => 'Active'
                    ]);

                    setFlashMessage('success', 'User added successfully.');
                }
            }
        } elseif ($action === 'edit') {
            $user_id = intval($_POST['user_id']);
            $full_name = sanitize($_POST['full_name']);
            $email = sanitize($_POST['email']);
            $phone = sanitize($_POST['phone']);
            $user_role = $_POST['role'];
            $user_branch_id = $role === 'Super Admin' ? intval($_POST['branch_id']) : $branchId;
            $status = $_POST['status'];
            
            // Prevent changing own role
            if ($user_id === getCurrentUserId() && $user_role !== $role) {
                setFlashMessage('error', 'Cannot change your own role.');
            } else {
                $db->update('users', [
                    'full_name' => $full_name,
                    'email' => $email,
                    'phone' => $phone,
                    'role' => $user_role,
                    'branch_id' => $user_branch_id,
                    'status' => $status
                ], 'user_id = ?', [$user_id]);

                setFlashMessage('success', 'User updated successfully.');
            }
        } elseif ($action === 'reset_password') {
            $user_id = intval($_POST['user_id']);
            $new_password = $_POST['new_password'];
            
            if (strlen($new_password) < PASSWORD_MIN_LENGTH) {
                setFlashMessage('error', 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.');
            } else {
                $db->update('users', [
                    'password' => hashPassword($new_password)
                ], 'user_id = ?', [$user_id]);
                
                setFlashMessage('success', 'Password reset successfully.');
            }
        } elseif ($action === 'delete') {
            $user_id = intval($_POST['user_id']);
            
            // Prevent deleting self
            if ($user_id === getCurrentUserId()) {
                setFlashMessage('error', 'Cannot delete your own account.');
            } else {
                $user = $db->fetchOne("SELECT full_name FROM users WHERE user_id = ?", [$user_id]);
                $db->delete('users', 'user_id = ?', [$user_id]);
                setFlashMessage('success', 'User deleted successfully.');
            }
        }
        
        redirect('users.php');
    }
}

// Get users
$users = $db->fetchAll(
    "SELECT u.*, b.branch_name 
     FROM users u 
     LEFT JOIN branches b ON u.branch_id = b.branch_id" .
    ($role === 'Branch Admin' ? ' WHERE u.branch_id = ?' : '') .
    " ORDER BY u.created_at DESC",
    $role === 'Branch Admin' ? [$branchId] : []
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
        <div class="d-flex align-items-center gap-3">
            <button class="mobile-menu-toggle" id="sidebarToggle">
                <i class="bi bi-list"></i>
            </button>
            <div class="page-title">
                <h1>User Management</h1>
                <p>Manage system users</p>
            </div>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="bi bi-plus-lg me-2"></i>Add User
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
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Role</th>
                            <?php if ($role === 'Super Admin'): ?>
                            <th>Branch</th>
                            <?php endif; ?>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><strong><?php echo $user['username']; ?></strong></td>
                            <td><?php echo $user['full_name']; ?></td>
                            <td><?php echo $user['email']; ?></td>
                            <td><?php echo $user['phone']; ?></td>
                            <td>
                                <span class="badge badge-primary"><?php echo $user['role']; ?></span>
                            </td>
                            <?php if ($role === 'Super Admin'): ?>
                            <td><?php echo $user['branch_name'] ?? 'N/A'; ?></td>
                            <?php endif; ?>
                            <td>
                                <span class="badge badge-<?php echo $user['status'] === 'Active' ? 'success' : 'danger'; ?>">
                                    <?php echo $user['status']; ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editUserModal<?php echo $user['user_id']; ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#resetPasswordModal<?php echo $user['user_id']; ?>">
                                    <i class="bi bi-key"></i>
                                </button>
                                <?php if ($user['user_id'] !== getCurrentUserId()): ?>
                                <button class="btn btn-sm btn-danger btn-delete" onclick="deleteUser(<?php echo $user['user_id']; ?>, '<?php echo $user['full_name']; ?>')">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label">Username *</label>
                        <input type="text" class="form-control" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password *</label>
                        <input type="password" class="form-control" name="password" required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                        <small class="text-muted">Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Full Name *</label>
                        <input type="text" class="form-control" name="full_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" class="form-control" name="phone">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role *</label>
                        <select class="form-select" name="role" required>
                            <?php if ($role === 'Super Admin'): ?>
                            <option value="Super Admin">Super Admin</option>
                            <?php endif; ?>
                            <option value="Branch Admin">Branch Admin</option>
                            <option value="Staff">Staff</option>
                        </select>
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
                    <button type="submit" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modals -->
<?php foreach ($users as $user): ?>
<div class="modal fade" id="editUserModal<?php echo $user['user_id']; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" value="<?php echo $user['username']; ?>" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Full Name *</label>
                        <input type="text" class="form-control" name="full_name" value="<?php echo $user['full_name']; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" class="form-control" name="email" value="<?php echo $user['email']; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" class="form-control" name="phone" value="<?php echo $user['phone']; ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role *</label>
                        <select class="form-select" name="role" required <?php echo $user['user_id'] === getCurrentUserId() ? 'disabled' : ''; ?>>
                            <?php if ($role === 'Super Admin'): ?>
                            <option value="Super Admin" <?php echo $user['role'] === 'Super Admin' ? 'selected' : ''; ?>>Super Admin</option>
                            <?php endif; ?>
                            <option value="Branch Admin" <?php echo $user['role'] === 'Branch Admin' ? 'selected' : ''; ?>>Branch Admin</option>
                            <option value="Staff" <?php echo $user['role'] === 'Staff' ? 'selected' : ''; ?>>Staff</option>
                        </select>
                    </div>
                    <?php if ($role === 'Super Admin'): ?>
                    <div class="mb-3">
                        <label class="form-label">Branch *</label>
                        <select class="form-select" name="branch_id" required>
                            <?php foreach ($branches as $branch): ?>
                            <option value="<?php echo $branch['branch_id']; ?>" <?php echo $user['branch_id'] == $branch['branch_id'] ? 'selected' : ''; ?>>
                                <?php echo $branch['branch_name']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label">Status *</label>
                        <select class="form-select" name="status" required <?php echo $user['user_id'] === getCurrentUserId() ? 'disabled' : ''; ?>>
                            <option value="Active" <?php echo $user['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?php echo $user['status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reset Password Modals -->
<div class="modal fade" id="resetPasswordModal<?php echo $user['user_id']; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reset Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                    
                    <p>Reset password for <strong><?php echo $user['full_name']; ?></strong></p>
                    <div class="mb-3">
                        <label class="form-label">New Password *</label>
                        <input type="password" class="form-control" name="new_password" required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                        <small class="text-muted">Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Reset Password</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete user <strong id="deleteUserName"></strong>?</p>
                <form method="POST" id="deleteUserForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="user_id" id="deleteUserId">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="deleteUserForm" class="btn btn-danger">Delete</button>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/sidebar.php'; ?>
<?php require_once 'includes/footer.php'; ?>

<script>
function deleteUser(userId, userName) {
    document.getElementById('deleteUserId').value = userId;
    document.getElementById('deleteUserName').textContent = userName;
    new bootstrap.Modal(document.getElementById('deleteUserModal')).show();
}
</script>