<?php
/**
 * Inventory Transfer Page (Super Admin Only)
 */

$pageTitle = 'Inventory Transfers';
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
            $transfer_code = generateTransferCode();
            $source_branch_id = intval($_POST['source_branch_id']);
            $destination_branch_id = intval($_POST['destination_branch_id']);
            $inventory_id = intval($_POST['inventory_id']);
            $quantity = intval($_POST['quantity']);
            $notes = sanitize($_POST['notes']);
            
            // Validate
            if ($source_branch_id === $destination_branch_id) {
                setFlashMessage('error', 'Source and destination branches cannot be the same.');
            } else {
                // Check inventory availability
                $inventory = $db->fetchOne(
                    "SELECT * FROM inventory WHERE inventory_id = ? AND branch_id = ?",
                    [$inventory_id, $source_branch_id]
                );
                
                if (!$inventory) {
                    setFlashMessage('error', 'Inventory item not found in source branch.');
                } elseif ($inventory['available_quantity'] < $quantity) {
                    setFlashMessage('error', 'Insufficient quantity available for transfer.');
                } else {
                    $transfer_id = $db->insert('inventory_transfers', [
                        'transfer_code' => $transfer_code,
                        'source_branch_id' => $source_branch_id,
                        'destination_branch_id' => $destination_branch_id,
                        'inventory_id' => $inventory_id,
                        'quantity' => $quantity,
                        'status' => 'Pending',
                        'requested_by' => getCurrentUserId(),
                        'notes' => $notes
                    ]);
                    
                    logAudit('Created', 'Transfer', $transfer_id, "Created transfer: $transfer_code");
                    setFlashMessage('success', 'Transfer request created successfully.');
                }
            }
        } elseif ($action === 'approve') {
            $transfer_id = intval($_POST['transfer_id']);
            
            $transfer = $db->fetchOne("SELECT * FROM inventory_transfers WHERE transfer_id = ?", [$transfer_id]);
            
            if ($transfer['status'] !== 'Pending') {
                setFlashMessage('error', 'Transfer can only be approved when status is Pending.');
            } else {
                // Check inventory availability
                $inventory = $db->fetchOne(
                    "SELECT * FROM inventory WHERE inventory_id = ? AND branch_id = ?",
                    [$transfer['inventory_id'], $transfer['source_branch_id']]
                );
                
                if ($inventory['available_quantity'] < $transfer['quantity']) {
                    setFlashMessage('error', 'Insufficient quantity available for transfer.');
                } else {
                    // Deduct from source
                    $db->update('inventory', [
                        'available_quantity' => $inventory['available_quantity'] - $transfer['quantity'],
                        'reserved_quantity' => $inventory['reserved_quantity'] + $transfer['quantity']
                    ], 'inventory_id = ?', [$transfer['inventory_id']]);
                    
                    // Log transaction
                    $transaction_code = 'TXN' . date('ym') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    $db->insert('inventory_transactions', [
                        'transaction_code' => $transaction_code,
                        'inventory_id' => $transfer['inventory_id'],
                        'transaction_type' => 'Transfer',
                        'quantity' => -$transfer['quantity'],
                        'previous_quantity' => $inventory['available_quantity'],
                        'new_quantity' => $inventory['available_quantity'] - $transfer['quantity'],
                        'reference_id' => $transfer_id,
                        'reference_type' => 'Transfer',
                        'notes' => 'Transfer to branch ' . $transfer['destination_branch_id'],
                        'performed_by' => getCurrentUserId(),
                        'branch_id' => $transfer['source_branch_id']
                    ]);
                    
                    // Update transfer status
                    $db->update('inventory_transfers', [
                        'status' => 'Approved',
                        'approved_by' => getCurrentUserId(),
                        'approved_at' => getCurrentDateTime()
                    ], 'transfer_id = ?', [$transfer_id]);
                    
                    logAudit('Approved', 'Transfer', $transfer_id, "Approved transfer: " . $transfer['transfer_code']);
                    setFlashMessage('success', 'Transfer approved successfully.');
                }
            }
        } elseif ($action === 'receive') {
            $transfer_id = intval($_POST['transfer_id']);
            
            $transfer = $db->fetchOne("SELECT * FROM inventory_transfers WHERE transfer_id = ?", [$transfer_id]);
            
            if ($transfer['status'] !== 'Approved') {
                setFlashMessage('error', 'Transfer can only be received when status is Approved.');
            } else {
                // Add to destination
                $dest_inventory = $db->fetchOne(
                    "SELECT * FROM inventory WHERE item_code = (SELECT item_code FROM inventory WHERE inventory_id = ?) AND branch_id = ?",
                    [$transfer['inventory_id'], $transfer['destination_branch_id']]
                );
                
                if ($dest_inventory) {
                    // Update existing inventory
                    $db->update('inventory', [
                        'total_quantity' => $dest_inventory['total_quantity'] + $transfer['quantity'],
                        'available_quantity' => $dest_inventory['available_quantity'] + $transfer['quantity']
                    ], 'inventory_id = ?', [$dest_inventory['inventory_id']]);
                    
                    $new_inventory_id = $dest_inventory['inventory_id'];
                } else {
                    // Create new inventory entry
                    $source_inventory = $db->fetchOne("SELECT * FROM inventory WHERE inventory_id = ?", [$transfer['inventory_id']]);
                    $new_item_code = $source_inventory['item_code'] . '-' . $transfer['destination_branch_id'];
                    
                    $new_inventory_id = $db->insert('inventory', [
                        'item_code' => $new_item_code,
                        'item_name' => $source_inventory['item_name'],
                        'category' => $source_inventory['category'],
                        'description' => $source_inventory['description'],
                        'branch_id' => $transfer['destination_branch_id'],
                        'total_quantity' => $transfer['quantity'],
                        'available_quantity' => $transfer['quantity'],
                        'rental_price' => $source_inventory['rental_price'],
                        'condition_status' => 'Available',
                        'status' => 'Active'
                    ]);
                }
                
                // Release from source reserved
                $source_inventory = $db->fetchOne("SELECT * FROM inventory WHERE inventory_id = ?", [$transfer['inventory_id']]);
                $db->update('inventory', [
                    'reserved_quantity' => $source_inventory['reserved_quantity'] - $transfer['quantity']
                ], 'inventory_id = ?', [$transfer['inventory_id']]);
                
                // Log transaction at destination
                $transaction_code = 'TXN' . date('ym') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $db->insert('inventory_transactions', [
                    'transaction_code' => $transaction_code,
                    'inventory_id' => $new_inventory_id,
                    'transaction_type' => 'Transfer',
                    'quantity' => $transfer['quantity'],
                    'previous_quantity' => $dest_inventory['available_quantity'] ?? 0,
                    'new_quantity' => ($dest_inventory['available_quantity'] ?? 0) + $transfer['quantity'],
                    'reference_id' => $transfer_id,
                    'reference_type' => 'Transfer',
                    'notes' => 'Transfer received from branch ' . $transfer['source_branch_id'],
                    'performed_by' => getCurrentUserId(),
                    'branch_id' => $transfer['destination_branch_id']
                ]);
                
                // Update transfer status
                $db->update('inventory_transfers', [
                    'status' => 'Received',
                    'received_by' => getCurrentUserId(),
                    'received_at' => getCurrentDateTime()
                ], 'transfer_id = ?', [$transfer_id]);
                
                logAudit('Received', 'Transfer', $transfer_id, "Received transfer: " . $transfer['transfer_code']);
                setFlashMessage('success', 'Transfer received successfully.');
            }
        } elseif ($action === 'cancel') {
            $transfer_id = intval($_POST['transfer_id']);
            
            $transfer = $db->fetchOne("SELECT * FROM inventory_transfers WHERE transfer_id = ?", [$transfer_id]);
            
            if ($transfer['status'] === 'Received') {
                setFlashMessage('error', 'Cannot cancel a received transfer.');
            } else {
                // If approved, release reserved quantity
                if ($transfer['status'] === 'Approved') {
                    $inventory = $db->fetchOne("SELECT * FROM inventory WHERE inventory_id = ?", [$transfer['inventory_id']]);
                    $db->update('inventory', [
                        'available_quantity' => $inventory['available_quantity'] + $transfer['quantity'],
                        'reserved_quantity' => $inventory['reserved_quantity'] - $transfer['quantity']
                    ], 'inventory_id = ?', [$transfer['inventory_id']]);
                }
                
                $db->update('inventory_transfers', ['status' => 'Cancelled'], 'transfer_id = ?', [$transfer_id]);
                logAudit('Cancelled', 'Transfer', $transfer_id, "Cancelled transfer: " . $transfer['transfer_code']);
                setFlashMessage('success', 'Transfer cancelled successfully.');
            }
        }
        
        redirect('transfers.php');
    }
}

// Get transfers
$transfers = $db->fetchAll(
    "SELECT t.*, 
            i.item_name, 
            sb.branch_name as source_branch, 
            db.branch_name as dest_branch,
            u1.full_name as requested_by_name,
            u2.full_name as approved_by_name,
            u3.full_name as received_by_name
     FROM inventory_transfers t
     JOIN inventory i ON t.inventory_id = i.inventory_id
     JOIN branches sb ON t.source_branch_id = sb.branch_id
     JOIN branches db ON t.destination_branch_id = db.branch_id
     LEFT JOIN users u1 ON t.requested_by = u1.user_id
     LEFT JOIN users u2 ON t.approved_by = u2.user_id
     LEFT JOIN users u3 ON t.received_by = u3.user_id
     ORDER BY t.created_at DESC"
);

// Get branches
$branches = $db->fetchAll("SELECT branch_id, branch_name FROM branches WHERE status = 'Active' ORDER BY branch_name ASC");

// Get inventory
$inventory = $db->fetchAll(
    "SELECT inventory_id, item_name, item_code, available_quantity, branch_id 
     FROM inventory 
     WHERE status = 'Active' 
     ORDER BY item_name ASC"
);

$csrf_token = generateCSRFToken();
require_once 'includes/header.php';
?>

<div class="main-content">
    <div class="top-bar">
        <div class="page-title">
            <h1>Inventory Transfers</h1>
            <p>Manage inventory transfers between branches</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTransferModal">
            <i class="bi bi-plus-lg me-2"></i>New Transfer
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
                            <th>Transfer Code</th>
                            <th>Item</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Quantity</th>
                            <th>Status</th>
                            <th>Requested By</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transfers as $transfer): ?>
                        <tr>
                            <td><strong><?php echo $transfer['transfer_code']; ?></strong></td>
                            <td><?php echo $transfer['item_name']; ?></td>
                            <td><?php echo $transfer['source_branch']; ?></td>
                            <td><?php echo $transfer['dest_branch']; ?></td>
                            <td><?php echo $transfer['quantity']; ?></td>
                            <td>
                                <span class="badge badge-<?php 
                                    echo $transfer['status'] === 'Approved' ? 'success' : 
                                        ($transfer['status'] === 'Pending' ? 'warning' : 
                                        ($transfer['status'] === 'Received' ? 'info' : 'danger')); 
                                ?>">
                                    <?php echo $transfer['status']; ?>
                                </span>
                            </td>
                            <td><?php echo $transfer['requested_by_name']; ?></td>
                            <td><?php echo formatDate($transfer['created_at']); ?></td>
                            <td>
                                <?php if ($transfer['status'] === 'Pending'): ?>
                                <button class="btn btn-sm btn-success" onclick="approveTransfer(<?php echo $transfer['transfer_id']; ?>)">
                                    <i class="bi bi-check"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="cancelTransfer(<?php echo $transfer['transfer_id']; ?>)">
                                    <i class="bi bi-x"></i>
                                </button>
                                <?php elseif ($transfer['status'] === 'Approved'): ?>
                                <button class="btn btn-sm btn-info" onclick="receiveTransfer(<?php echo $transfer['transfer_id']; ?>)">
                                    <i class="bi bi-box-arrow-in-down"></i> Receive
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="cancelTransfer(<?php echo $transfer['transfer_id']; ?>)">
                                    <i class="bi bi-x"></i>
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

<!-- Add Transfer Modal -->
<div class="modal fade" id="addTransferModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">New Inventory Transfer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label">Source Branch *</label>
                        <select class="form-select" name="source_branch_id" id="sourceBranch" required onchange="loadInventory()">
                            <option value="">Select Source Branch</option>
                            <?php foreach ($branches as $branch): ?>
                            <option value="<?php echo $branch['branch_id']; ?>"><?php echo $branch['branch_name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Destination Branch *</label>
                        <select class="form-select" name="destination_branch_id" required>
                            <option value="">Select Destination Branch</option>
                            <?php foreach ($branches as $branch): ?>
                            <option value="<?php echo $branch['branch_id']; ?>"><?php echo $branch['branch_name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Inventory Item *</label>
                        <select class="form-select" name="inventory_id" id="inventorySelect" required>
                            <option value="">Select Source Branch First</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Quantity *</label>
                        <input type="number" class="form-control" name="quantity" required min="1">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Transfer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Approve Transfer Form -->
<form method="POST" id="approveTransferForm">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" value="approve">
    <input type="hidden" name="transfer_id" id="approveTransferId">
</form>

<!-- Receive Transfer Form -->
<form method="POST" id="receiveTransferForm">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" value="receive">
    <input type="hidden" name="transfer_id" id="receiveTransferId">
</form>

<!-- Cancel Transfer Form -->
<form method="POST" id="cancelTransferForm">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" value="cancel">
    <input type="hidden" name="transfer_id" id="cancelTransferId">
</form>

<?php require_once 'includes/sidebar.php'; ?>
<?php require_once 'includes/footer.php'; ?>

<script>
function loadInventory() {
    const branchId = document.getElementById('sourceBranch').value;
    const inventorySelect = document.getElementById('inventorySelect');
    
    if (!branchId) {
        inventorySelect.innerHTML = '<option value="">Select Source Branch First</option>';
        return;
    }
    
    // Filter inventory by branch
    const inventoryData = <?php echo json_encode($inventory); ?>;
    const filteredInventory = inventoryData.filter(item => item.branch_id == branchId);
    
    inventorySelect.innerHTML = '<option value="">Select Item</option>';
    filteredInventory.forEach(item => {
        inventorySelect.innerHTML += `<option value="${item.inventory_id}">${item.item_name} (${item.item_code}) - Available: ${item.available_quantity}</option>`;
    });
}

function approveTransfer(transferId) {
    if (confirm('Are you sure you want to approve this transfer?')) {
        document.getElementById('approveTransferId').value = transferId;
        document.getElementById('approveTransferForm').submit();
    }
}

function receiveTransfer(transferId) {
    if (confirm('Are you sure you want to receive this transfer?')) {
        document.getElementById('receiveTransferId').value = transferId;
        document.getElementById('receiveTransferForm').submit();
    }
}

function cancelTransfer(transferId) {
    if (confirm('Are you sure you want to cancel this transfer?')) {
        document.getElementById('cancelTransferId').value = transferId;
        document.getElementById('cancelTransferForm').submit();
    }
}
</script>
