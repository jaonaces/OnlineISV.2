<?php
/**
 * Inventory Management Page
 */

$pageTitle = 'Inventory Management';
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
            $item_code = strtoupper(sanitize($_POST['item_code']));
            $item_name = sanitize($_POST['item_name']);
            $category = $_POST['category'];
            $description = sanitize($_POST['description']);
            $inventory_branch_id = $role === 'Super Admin' ? intval($_POST['branch_id']) : $branchId;
            $total_quantity = intval($_POST['total_quantity']);
            $available_quantity = $total_quantity;
            $rental_price = floatval($_POST['rental_price']);
            $condition_status = 'Available';
            
            // Check if item code exists
            $existing = $db->fetchOne("SELECT inventory_id FROM inventory WHERE item_code = ?", [$item_code]);
            if ($existing) {
                setFlashMessage('error', 'Item code already exists.');
            } else {
                $inventory_id = $db->insert('inventory', [
                    'item_code' => $item_code,
                    'item_name' => $item_name,
                    'category' => $category,
                    'description' => $description,
                    'branch_id' => $inventory_branch_id,
                    'total_quantity' => $total_quantity,
                    'available_quantity' => $available_quantity,
                    'rental_price' => $rental_price,
                    'condition_status' => $condition_status,
                    'status' => 'Active'
                ]);
                
                // Log inventory transaction
                $transaction_code = 'TXN' . date('ym') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $db->insert('inventory_transactions', [
                    'transaction_code' => $transaction_code,
                    'inventory_id' => $inventory_id,
                    'transaction_type' => 'Stock In',
                    'quantity' => $total_quantity,
                    'previous_quantity' => 0,
                    'new_quantity' => $total_quantity,
                    'notes' => 'Initial stock',
                    'performed_by' => getCurrentUserId(),
                    'branch_id' => $inventory_branch_id
                ]);

                setFlashMessage('success', 'Inventory item added successfully.');
            }
        } elseif ($action === 'edit') {
            $inventory_id = intval($_POST['inventory_id']);
            $item_name = sanitize($_POST['item_name']);
            $category = $_POST['category'];
            $description = sanitize($_POST['description']);
            $total_quantity = intval($_POST['total_quantity']);
            $rental_price = floatval($_POST['rental_price']);
            $condition_status = $_POST['condition_status'];
            $status = $_POST['status'];
            
            $db->update('inventory', [
                'item_name' => $item_name,
                'category' => $category,
                'description' => $description,
                'total_quantity' => $total_quantity,
                'rental_price' => $rental_price,
                'condition_status' => $condition_status,
                'status' => $status
            ], 'inventory_id = ?', [$inventory_id]);

            setFlashMessage('success', 'Inventory item updated successfully.');
        } elseif ($action === 'adjust') {
            $inventory_id = intval($_POST['inventory_id']);
            $adjustment_quantity = intval($_POST['adjustment_quantity']);
            $notes = sanitize($_POST['notes']);
            
            $inventory = $db->fetchOne("SELECT * FROM inventory WHERE inventory_id = ?", [$inventory_id]);
            $new_quantity = $inventory['available_quantity'] + $adjustment_quantity;
            
            if ($new_quantity < 0) {
                setFlashMessage('error', 'Cannot reduce quantity below zero.');
            } else {
                $db->update('inventory', [
                    'available_quantity' => $new_quantity,
                    'total_quantity' => $inventory['total_quantity'] + $adjustment_quantity
                ], 'inventory_id = ?', [$inventory_id]);
                
                // Log transaction
                $transaction_code = 'TXN' . date('ym') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $db->insert('inventory_transactions', [
                    'transaction_code' => $transaction_code,
                    'inventory_id' => $inventory_id,
                    'transaction_type' => 'Adjustment',
                    'quantity' => $adjustment_quantity,
                    'previous_quantity' => $inventory['available_quantity'],
                    'new_quantity' => $new_quantity,
                    'notes' => $notes,
                    'performed_by' => getCurrentUserId(),
                    'branch_id' => $inventory['branch_id']
                ]);

                setFlashMessage('success', 'Inventory adjusted successfully.');
            }
        } elseif ($action === 'delete') {
            $inventory_id = intval($_POST['inventory_id']);
            
            // Check if inventory is used in bookings
            $has_bookings = $db->fetchOne("SELECT COUNT(*) as count FROM booking_items WHERE inventory_id = ?", [$inventory_id])['count'];
            if ($has_bookings > 0) {
                setFlashMessage('error', 'Cannot delete inventory item with existing bookings.');
            } else {
                $inventory = $db->fetchOne("SELECT item_name FROM inventory WHERE inventory_id = ?", [$inventory_id]);
                $db->delete('inventory', 'inventory_id = ?', [$inventory_id]);
                setFlashMessage('success', 'Inventory item deleted successfully.');
            }
        }
        
        redirect('inventory.php');
    }
}

// Build WHERE clause
$whereClause = '';
$params = [];

if ($role !== 'Super Admin') {
    $whereClause = 'WHERE i.branch_id = ?';
    $params[] = $branchId;
}

// Get inventory
$inventory = $db->fetchAll(
    "SELECT i.*, b.branch_name 
     FROM inventory i 
     LEFT JOIN branches b ON i.branch_id = b.branch_id 
     $whereClause 
     ORDER BY i.category ASC, i.item_name ASC",
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
        <div class="d-flex align-items-center gap-3">
            <button class="mobile-menu-toggle" id="sidebarToggle">
                <i class="bi bi-list"></i>
            </button>
            <div class="page-title">
                <h1>Inventory Management</h1>
                <p>Manage event equipment and supplies</p>
            </div>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addInventoryModal">
            <i class="bi bi-plus-lg me-2"></i>Add Item
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
                            <th>Item Code</th>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Available</th>
                            <th>Rental Price</th>
                            <th>Condition</th>
                            <?php if ($role === 'Super Admin'): ?>
                            <th>Branch</th>
                            <?php endif; ?>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inventory as $item): ?>
                        <tr>
                            <td><strong><?php echo $item['item_code']; ?></strong></td>
                            <td><?php echo $item['item_name']; ?></td>
                            <td><?php echo $item['category']; ?></td>
                            <td>
                                <span class="badge badge-<?php echo $item['available_quantity'] <= 5 ? 'danger' : 'success'; ?>">
                                    <?php echo $item['available_quantity']; ?>
                                </span>
                            </td>
                            <td><?php echo formatCurrency($item['rental_price']); ?></td>
                            <td><?php echo $item['condition_status']; ?></td>
                            <?php if ($role === 'Super Admin'): ?>
                            <td><?php echo $item['branch_name']; ?></td>
                            <?php endif; ?>
                            <td>
                                <span class="badge badge-<?php echo $item['status'] === 'Active' ? 'success' : 'danger'; ?>">
                                    <?php echo $item['status']; ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editInventoryModal<?php echo $item['inventory_id']; ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#adjustInventoryModal<?php echo $item['inventory_id']; ?>">
                                    <i class="bi bi-arrow-left-right"></i>
                                </button>
                                <button class="btn btn-sm btn-danger btn-delete" onclick="deleteInventory(<?php echo $item['inventory_id']; ?>, '<?php echo $item['item_name']; ?>')">
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

<!-- Add Inventory Modal -->
<div class="modal fade" id="addInventoryModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Inventory Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Item Code *</label>
                            <input type="text" class="form-control" name="item_code" required placeholder="e.g., CHR001">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Item Name *</label>
                            <input type="text" class="form-control" name="item_name" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category *</label>
                            <select class="form-select" name="category" required>
                                <option value="">Select Category</option>
                                <option value="Chairs">Chairs</option>
                                <option value="Tables">Tables</option>
                                <option value="Tents">Tents</option>
                                <option value="Sound Systems">Sound Systems</option>
                                <option value="Lights">Lights</option>
                                <option value="LED Walls">LED Walls</option>
                                <option value="Decorations">Decorations</option>
                                <option value="Catering Equipment">Catering Equipment</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Total Quantity *</label>
                            <input type="number" class="form-control" name="total_quantity" required min="1">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="2"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Rental Price *</label>
                            <input type="number" class="form-control" name="rental_price" required min="0" step="0.01">
                        </div>
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
                    <button type="submit" class="btn btn-primary">Add Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Inventory Modals -->
<?php foreach ($inventory as $item): ?>
<div class="modal fade" id="editInventoryModal<?php echo $item['inventory_id']; ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Inventory Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="inventory_id" value="<?php echo $item['inventory_id']; ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Item Code</label>
                            <input type="text" class="form-control" value="<?php echo $item['item_code']; ?>" disabled>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Item Name *</label>
                            <input type="text" class="form-control" name="item_name" value="<?php echo $item['item_name']; ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category *</label>
                            <select class="form-select" name="category" required>
                                <option value="Chairs" <?php echo $item['category'] === 'Chairs' ? 'selected' : ''; ?>>Chairs</option>
                                <option value="Tables" <?php echo $item['category'] === 'Tables' ? 'selected' : ''; ?>>Tables</option>
                                <option value="Tents" <?php echo $item['category'] === 'Tents' ? 'selected' : ''; ?>>Tents</option>
                                <option value="Sound Systems" <?php echo $item['category'] === 'Sound Systems' ? 'selected' : ''; ?>>Sound Systems</option>
                                <option value="Lights" <?php echo $item['category'] === 'Lights' ? 'selected' : ''; ?>>Lights</option>
                                <option value="LED Walls" <?php echo $item['category'] === 'LED Walls' ? 'selected' : ''; ?>>LED Walls</option>
                                <option value="Decorations" <?php echo $item['category'] === 'Decorations' ? 'selected' : ''; ?>>Decorations</option>
                                <option value="Catering Equipment" <?php echo $item['category'] === 'Catering Equipment' ? 'selected' : ''; ?>>Catering Equipment</option>
                                <option value="Other" <?php echo $item['category'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Total Quantity *</label>
                            <input type="number" class="form-control" name="total_quantity" value="<?php echo $item['total_quantity']; ?>" required min="1">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="2"><?php echo $item['description']; ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Rental Price *</label>
                            <input type="number" class="form-control" name="rental_price" value="<?php echo $item['rental_price']; ?>" required min="0" step="0.01">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Condition *</label>
                            <select class="form-select" name="condition_status" required>
                                <option value="Available" <?php echo $item['condition_status'] === 'Available' ? 'selected' : ''; ?>>Available</option>
                                <option value="Reserved" <?php echo $item['condition_status'] === 'Reserved' ? 'selected' : ''; ?>>Reserved</option>
                                <option value="In Use" <?php echo $item['condition_status'] === 'In Use' ? 'selected' : ''; ?>>In Use</option>
                                <option value="Maintenance" <?php echo $item['condition_status'] === 'Maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                <option value="Damaged" <?php echo $item['condition_status'] === 'Damaged' ? 'selected' : ''; ?>>Damaged</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status *</label>
                            <select class="form-select" name="status" required>
                                <option value="Active" <?php echo $item['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                                <option value="Inactive" <?php echo $item['status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Adjust Inventory Modals -->
<div class="modal fade" id="adjustInventoryModal<?php echo $item['inventory_id']; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Adjust Inventory</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="adjust">
                    <input type="hidden" name="inventory_id" value="<?php echo $item['inventory_id']; ?>">
                    
                    <p>Adjust inventory for <strong><?php echo $item['item_name']; ?></strong></p>
                    <p>Current Available: <strong><?php echo $item['available_quantity']; ?></strong></p>
                    
                    <div class="mb-3">
                        <label class="form-label">Adjustment Quantity *</label>
                        <input type="number" class="form-control" name="adjustment_quantity" required>
                        <small class="text-muted">Positive to add, negative to remove</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Adjust</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteInventoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete inventory item <strong id="deleteItemName"></strong>?</p>
                <form method="POST" id="deleteInventoryForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="inventory_id" id="deleteInventoryId">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="deleteInventoryForm" class="btn btn-danger">Delete</button>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/sidebar.php'; ?>
<?php require_once 'includes/footer.php'; ?>

<script>
function deleteInventory(inventoryId, itemName) {
    document.getElementById('deleteInventoryId').value = inventoryId;
    document.getElementById('deleteItemName').textContent = itemName;
    new bootstrap.Modal(document.getElementById('deleteInventoryModal')).show();
}
</script>
