<?php
/**
 * Client Management Page
 */

$pageTitle = 'Client Management';
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
            $client_code = 'CLI' . date('ym') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $full_name = sanitize($_POST['full_name']);
            $email = sanitize($_POST['email']);
            $phone = sanitize($_POST['phone']);
            $address = sanitize($_POST['address']);
            $company_name = sanitize($_POST['company_name']);
            $client_branch_id = $role === 'Super Admin' ? intval($_POST['branch_id']) : $branchId;
            
            $client_id = $db->insert('clients', [
                'client_code' => $client_code,
                'full_name' => $full_name,
                'email' => $email,
                'phone' => $phone,
                'address' => $address,
                'company_name' => $company_name,
                'branch_id' => $client_branch_id,
                'status' => 'Active'
            ]);
            
            logAudit('Created', 'Client', $client_id, "Created client: $full_name");
            setFlashMessage('success', 'Client added successfully.');
        } elseif ($action === 'edit') {
            $client_id = intval($_POST['client_id']);
            $full_name = sanitize($_POST['full_name']);
            $email = sanitize($_POST['email']);
            $phone = sanitize($_POST['phone']);
            $address = sanitize($_POST['address']);
            $company_name = sanitize($_POST['company_name']);
            $status = $_POST['status'];
            
            $db->update('clients', [
                'full_name' => $full_name,
                'email' => $email,
                'phone' => $phone,
                'address' => $address,
                'company_name' => $company_name,
                'status' => $status
            ], 'client_id = ?', [$client_id]);
            
            logAudit('Updated', 'Client', $client_id, "Updated client: $full_name");
            setFlashMessage('success', 'Client updated successfully.');
        } elseif ($action === 'delete') {
            $client_id = intval($_POST['client_id']);
            
            // Check if client has bookings
            $has_bookings = $db->fetchOne("SELECT COUNT(*) as count FROM bookings WHERE client_id = ?", [$client_id])['count'];
            if ($has_bookings > 0) {
                setFlashMessage('error', 'Cannot delete client with existing bookings.');
            } else {
                $client = $db->fetchOne("SELECT full_name FROM clients WHERE client_id = ?", [$client_id]);
                $db->delete('clients', 'client_id = ?', [$client_id]);
                logAudit('Deleted', 'Client', $client_id, "Deleted client: " . $client['full_name']);
                setFlashMessage('success', 'Client deleted successfully.');
            }
        }
        
        redirect('clients.php');
    }
}

// Get clients
$clients = $db->fetchAll(
    "SELECT c.*, b.branch_name 
     FROM clients c 
     LEFT JOIN branches b ON c.branch_id = b.branch_id" .
    ($role !== 'Super Admin' ? ' WHERE c.branch_id = ?' : '') .
    " ORDER BY c.created_at DESC",
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
            <h1>Client Management</h1>
            <p>Manage client information</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addClientModal">
            <i class="bi bi-plus-lg me-2"></i>Add Client
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
                            <th>Client Code</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Company</th>
                            <?php if ($role === 'Super Admin'): ?>
                            <th>Branch</th>
                            <?php endif; ?>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clients as $client): ?>
                        <tr>
                            <td><strong><?php echo $client['client_code']; ?></strong></td>
                            <td><?php echo $client['full_name']; ?></td>
                            <td><?php echo $client['email']; ?></td>
                            <td><?php echo $client['phone']; ?></td>
                            <td><?php echo $client['company_name'] ?? 'N/A'; ?></td>
                            <?php if ($role === 'Super Admin'): ?>
                            <td><?php echo $client['branch_name']; ?></td>
                            <?php endif; ?>
                            <td>
                                <span class="badge badge-<?php echo $client['status'] === 'Active' ? 'success' : 'danger'; ?>">
                                    <?php echo $client['status']; ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editClientModal<?php echo $client['client_id']; ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-danger btn-delete" onclick="deleteClient(<?php echo $client['client_id']; ?>, '<?php echo $client['full_name']; ?>')">
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

<!-- Add Client Modal -->
<div class="modal fade" id="addClientModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Client</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label">Full Name *</label>
                        <input type="text" class="form-control" name="full_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone *</label>
                        <input type="text" class="form-control" name="phone" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" name="address" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Company Name</label>
                        <input type="text" class="form-control" name="company_name">
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
                    <button type="submit" class="btn btn-primary">Add Client</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Client Modals -->
<?php foreach ($clients as $client): ?>
<div class="modal fade" id="editClientModal<?php echo $client['client_id']; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Client</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="client_id" value="<?php echo $client['client_id']; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Client Code</label>
                        <input type="text" class="form-control" value="<?php echo $client['client_code']; ?>" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Full Name *</label>
                        <input type="text" class="form-control" name="full_name" value="<?php echo $client['full_name']; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" value="<?php echo $client['email']; ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone *</label>
                        <input type="text" class="form-control" name="phone" value="<?php echo $client['phone']; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" name="address" rows="3"><?php echo $client['address']; ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Company Name</label>
                        <input type="text" class="form-control" name="company_name" value="<?php echo $client['company_name']; ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status *</label>
                        <select class="form-select" name="status" required>
                            <option value="Active" <?php echo $client['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?php echo $client['status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Client</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteClientModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete client <strong id="deleteClientName"></strong>?</p>
                <form method="POST" id="deleteClientForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="client_id" id="deleteClientId">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="deleteClientForm" class="btn btn-danger">Delete</button>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/sidebar.php'; ?>
<?php require_once 'includes/footer.php'; ?>

<script>
function deleteClient(clientId, clientName) {
    document.getElementById('deleteClientId').value = clientId;
    document.getElementById('deleteClientName').textContent = clientName;
    new bootstrap.Modal(document.getElementById('deleteClientModal')).show();
}
</script>