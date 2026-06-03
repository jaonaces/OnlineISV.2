<?php
/**
 * Booking Management Page
 */

$pageTitle = 'Booking Management';
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
            $booking_number = generateBookingNumber();
            $client_id = intval($_POST['client_id']);
            $booking_branch_id = $role === 'Super Admin' ? intval($_POST['branch_id']) : $branchId;
            $event_type = $_POST['event_type'];
            $event_date = $_POST['event_date'];
            $event_time = $_POST['event_time'];
            $end_time = $_POST['end_time'] ?? null;
            $event_location = sanitize($_POST['event_location']);
            $venue_name = sanitize($_POST['venue_name']);
            $package_id = intval($_POST['package_id']) ?: null;
            $estimated_guests = intval($_POST['estimated_guests']) ?: null;
            $total_amount = floatval($_POST['total_amount']) ?: 0;
            $down_payment = floatval($_POST['down_payment']) ?: 0;
            $special_requests = sanitize($_POST['special_requests']);
            $notes = sanitize($_POST['notes']);
            
            $booking_id = $db->insert('bookings', [
                'booking_number' => $booking_number,
                'client_id' => $client_id,
                'branch_id' => $booking_branch_id,
                'event_type' => $event_type,
                'event_date' => $event_date,
                'event_time' => $event_time,
                'end_time' => $end_time,
                'event_location' => $event_location,
                'venue_name' => $venue_name,
                'package_id' => $package_id,
                'estimated_guests' => $estimated_guests,
                'status' => 'Pending',
                'total_amount' => $total_amount,
                'down_payment' => $down_payment,
                'balance' => $total_amount - $down_payment,
                'special_requests' => $special_requests,
                'notes' => $notes,
                'created_by' => getCurrentUserId()
            ]);

            setFlashMessage('success', 'Booking added successfully.');
        } elseif ($action === 'edit') {
            $booking_id = intval($_POST['booking_id']);
            $event_date = $_POST['event_date'];
            $event_time = $_POST['event_time'];
            $end_time = $_POST['end_time'] ?? null;
            $event_location = sanitize($_POST['event_location']);
            $venue_name = sanitize($_POST['venue_name']);
            $package_id = intval($_POST['package_id']) ?: null;
            $estimated_guests = intval($_POST['estimated_guests']) ?: null;
            $total_amount = floatval($_POST['total_amount']) ?: 0;
            $down_payment = floatval($_POST['down_payment']) ?: 0;
            $special_requests = sanitize($_POST['special_requests']);
            $notes = sanitize($_POST['notes']);
            $status = $_POST['status'];
            
            $balance = $total_amount - $down_payment;
            
            $db->update('bookings', [
                'event_date' => $event_date,
                'event_time' => $event_time,
                'end_time' => $end_time,
                'event_location' => $event_location,
                'venue_name' => $venue_name,
                'package_id' => $package_id,
                'estimated_guests' => $estimated_guests,
                'total_amount' => $total_amount,
                'down_payment' => $down_payment,
                'balance' => $balance,
                'special_requests' => $special_requests,
                'notes' => $notes,
                'status' => $status,
                'updated_by' => getCurrentUserId()
            ], 'booking_id = ?', [$booking_id]);

            setFlashMessage('success', 'Booking updated successfully.');
        } elseif ($action === 'delete') {
            $booking_id = intval($_POST['booking_id']);
            $booking = $db->fetchOne("SELECT booking_number FROM bookings WHERE booking_id = ?", [$booking_id]);
            $db->delete('bookings', 'booking_id = ?', [$booking_id]);
            setFlashMessage('success', 'Booking deleted successfully.');
        } elseif ($action === 'rent') {
            $inventory_id = intval($_POST['inventory_id']);
            $client_id = intval($_POST['client_id']);
            $rental_date = $_POST['rental_date'];
            $return_date = $_POST['return_date'];
            $quantity = intval($_POST['quantity']);
            
            // Get inventory item
            $inventory = $db->fetchOne("SELECT * FROM inventory WHERE inventory_id = ?", [$inventory_id]);
            
            if (!$inventory) {
                setFlashMessage('error', 'Inventory item not found.');
            } elseif ($inventory['available_quantity'] < $quantity) {
                setFlashMessage('error', 'Insufficient inventory available.');
            } else {
                // Calculate total amount
                $rental_date_obj = new DateTime($rental_date);
                $return_date_obj = new DateTime($return_date);
                $days = $rental_date_obj->diff($return_date_obj)->days;
                $total_amount = $inventory['rental_price'] * $quantity * $days;
                
                // Create booking
                $booking_number = generateBookingNumber();
                $booking_id = $db->insert('bookings', [
                    'booking_number' => $booking_number,
                    'client_id' => $client_id,
                    'branch_id' => $inventory['branch_id'],
                    'event_type' => 'Rental',
                    'event_date' => $rental_date,
                    'event_time' => '00:00:00',
                    'end_time' => '23:59:59',
                    'event_location' => 'Rental',
                    'total_amount' => $total_amount,
                    'status' => 'Confirmed',
                    'created_by' => getCurrentUserId()
                ]);
                
                // Add booking item
                $db->insert('booking_items', [
                    'booking_id' => $booking_id,
                    'inventory_id' => $inventory_id,
                    'quantity' => $quantity,
                    'rental_price' => $inventory['rental_price'],
                    'subtotal' => $total_amount,
                    'status' => 'Reserved'
                ]);
                
                // Update inventory quantity
                $new_available = $inventory['available_quantity'] - $quantity;
                $db->update('inventory', ['available_quantity' => $new_available], 'inventory_id = ?', [$inventory_id]);
                
                // Log transaction
                $transaction_code = 'TXN' . date('ym') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $db->insert('inventory_transactions', [
                    'transaction_code' => $transaction_code,
                    'inventory_id' => $inventory_id,
                    'transaction_type' => 'Rent Out',
                    'quantity' => -$quantity,
                    'previous_quantity' => $inventory['available_quantity'],
                    'new_quantity' => $new_available,
                    'notes' => "Rented to client ID: $client_id",
                    'performed_by' => getCurrentUserId(),
                    'branch_id' => $inventory['branch_id']
                ]);
                
                setFlashMessage('success', 'Item rented successfully. Booking number: ' . $booking_number);
            }
        } elseif ($action === 'return_item') {
            $booking_id = intval($_POST['booking_id']);
            
            // Get booking details
            $booking = $db->fetchOne("SELECT * FROM bookings WHERE booking_id = ?", [$booking_id]);
            
            if (!$booking) {
                setFlashMessage('error', 'Booking not found.');
            } elseif ($booking['event_type'] !== 'Rental') {
                setFlashMessage('error', 'This is not a rental booking.');
            } else {
                // Get all booking items for this booking
                $booking_items = $db->fetchAll(
                    "SELECT bi.*, i.item_name 
                     FROM booking_items bi 
                     JOIN inventory i ON bi.inventory_id = i.inventory_id 
                     WHERE bi.booking_id = ? AND bi.status != 'Returned'",
                    [$booking_id]
                );
                
                if (empty($booking_items)) {
                    setFlashMessage('error', 'No items to return for this booking.');
                } else {
                    $returned_count = 0;
                    
                    foreach ($booking_items as $booking_item) {
                        // Get inventory item
                        $inventory = $db->fetchOne("SELECT * FROM inventory WHERE inventory_id = ?", [$booking_item['inventory_id']]);
                        
                        if ($inventory) {
                            // Update booking item status to Returned
                            $db->update('booking_items', ['status' => 'Returned'], 'booking_item_id = ?', [$booking_item['booking_item_id']]);
                            
                            // Restore inventory quantity
                            $new_available = $inventory['available_quantity'] + $booking_item['quantity'];
                            $db->update('inventory', ['available_quantity' => $new_available], 'inventory_id = ?', [$booking_item['inventory_id']]);
                            
                            // Log transaction
                            $transaction_code = 'TXN' . date('ym') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                            $db->insert('inventory_transactions', [
                                'transaction_code' => $transaction_code,
                                'inventory_id' => $booking_item['inventory_id'],
                                'transaction_type' => 'Return',
                                'quantity' => $booking_item['quantity'],
                                'previous_quantity' => $inventory['available_quantity'],
                                'new_quantity' => $new_available,
                                'notes' => "Returned from booking: " . $booking['booking_number'],
                                'performed_by' => getCurrentUserId(),
                                'branch_id' => $inventory['branch_id']
                            ]);
                            
                            $returned_count++;
                        }
                    }
                    
                    setFlashMessage('success', "Successfully returned $returned_count item(s). Inventory quantities restored.");
                }
            }
        }
        
        redirect('bookings.php');
    }
}

// Build WHERE clause
$whereClause = '';
$params = [];

if ($role !== 'Super Admin') {
    $whereClause = 'WHERE b.branch_id = ?';
    $params[] = $branchId;
}

// Get bookings
$bookings = $db->fetchAll(
    "SELECT b.*, c.full_name as client_name, p.package_name, br.branch_name,
            (SELECT GROUP_CONCAT(CONCAT(i.item_name, ' (', bi.quantity, ')') SEPARATOR ', ')
             FROM booking_items bi
             JOIN inventory i ON bi.inventory_id = i.inventory_id
             WHERE bi.booking_id = b.booking_id) as items,
            (SELECT GROUP_CONCAT(bi.status SEPARATOR ', ')
             FROM booking_items bi
             WHERE bi.booking_id = b.booking_id) as item_statuses
     FROM bookings b
     JOIN clients c ON b.client_id = c.client_id
     LEFT JOIN packages p ON b.package_id = p.package_id
     LEFT JOIN branches br ON b.branch_id = br.branch_id
     $whereClause
     ORDER BY b.created_at DESC",
    $params
);

// Get clients for dropdown
$clients = $db->fetchAll(
    "SELECT client_id, full_name FROM clients WHERE status = 'Active'" .
    ($role !== 'Super Admin' ? ' AND branch_id = ?' : '') .
    " ORDER BY full_name ASC",
    $role !== 'Super Admin' ? [$branchId] : []
);

// Get packages for dropdown
$packages = $db->fetchAll(
    "SELECT package_id, package_name, base_price FROM packages WHERE status = 'Active'" .
    ($role !== 'Super Admin' ? ' AND branch_id = ?' : '') .
    " ORDER BY package_name ASC",
    $role !== 'Super Admin' ? [$branchId] : []
);

// Get branches for dropdown (Super Admin only)
$branches = [];
if ($role === 'Super Admin') {
    $branches = $db->fetchAll("SELECT branch_id, branch_name FROM branches WHERE status = 'Active' ORDER BY branch_name ASC");
}

// Get available inventory for rent item modal
$whereClause = 'WHERE available_quantity > 0 AND status = \'Active\'';
$params = [];

if ($role !== 'Super Admin') {
    $whereClause = 'WHERE branch_id = ? AND available_quantity > 0 AND status = \'Active\'';
    $params[] = $branchId;
}

$inventory = $db->fetchAll(
    "SELECT * FROM inventory
     $whereClause
     ORDER BY category ASC, item_name ASC",
    $params
);

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
                <h1>Booking Management</h1>
                <p>Manage event bookings</p>
            </div>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBookingModal">
                <i class="bi bi-plus-lg me-2"></i>New Booking
            </button>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#rentItemModal">
                <i class="bi bi-cart me-2"></i>Rent Item
            </button>
        </div>
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
                            <th>Booking #</th>
                            <th>Client</th>
                            <th>Event Type</th>
                            <th>Items</th>
                            <th>Event Date</th>
                            <th>Time</th>
                            <th>Location</th>
                            <th>Package</th>
                            <th>Branch</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                        <tr>
                            <td><strong><?php echo $booking['booking_number']; ?></strong></td>
                            <td><?php echo $booking['client_name']; ?></td>
                            <td><?php echo $booking['event_type']; ?></td>
                            <td><?php echo $booking['items'] ?? 'N/A'; ?></td>
                            <td><?php echo formatDate($booking['event_date']); ?></td>
                            <td><?php echo $booking['event_time']; ?></td>
                            <td><?php echo $booking['venue_name'] ?? $booking['event_location']; ?></td>
                            <td><?php echo $booking['package_name'] ?? 'N/A'; ?></td>
                            <td><?php echo $booking['branch_name'] ?? 'N/A'; ?></td>
                            <td><?php echo formatCurrency($booking['total_amount']); ?></td>
                            <td>
                                <span class="badge badge-<?php
                                    echo $booking['status'] === 'Confirmed' ? 'success' :
                                        ($booking['status'] === 'Pending' ? 'warning' :
                                        ($booking['status'] === 'Cancelled' ? 'danger' :
                                        ($booking['status'] === 'Completed' ? 'info' : 'primary')));
                                ?>">
                                    <?php echo $booking['status']; ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editBookingModal<?php echo $booking['booking_id']; ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <?php if ($booking['event_type'] === 'Rental' && $booking['item_statuses'] && strpos($booking['item_statuses'], 'Returned') === false): ?>
                                <button class="btn btn-sm btn-success" onclick="returnItem(<?php echo $booking['booking_id']; ?>, '<?php echo $booking['booking_number']; ?>')">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </button>
                                <?php endif; ?>
                                <button class="btn btn-sm btn-danger btn-delete" onclick="deleteBooking(<?php echo $booking['booking_id']; ?>, '<?php echo $booking['booking_number']; ?>')">
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

<!-- Add Booking Modal -->
<div class="modal fade" id="addBookingModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">New Booking</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Client *</label>
                            <select class="form-select" name="client_id" required>
                                <option value="">Select Client</option>
                                <?php foreach ($clients as $client): ?>
                                <option value="<?php echo $client['client_id']; ?>"><?php echo $client['full_name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($role === 'Super Admin'): ?>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Branch *</label>
                            <select class="form-select" name="branch_id" required>
                                <?php foreach ($branches as $branch): ?>
                                <option value="<?php echo $branch['branch_id']; ?>"><?php echo $branch['branch_name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
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
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Package</label>
                            <select class="form-select" name="package_id" id="packageSelect">
                                <option value="">Select Package</option>
                                <?php foreach ($packages as $package): ?>
                                <option value="<?php echo $package['package_id']; ?>" data-price="<?php echo $package['base_price']; ?>">
                                    <?php echo $package['package_name']; ?> - <?php echo formatCurrency($package['base_price']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Event Date *</label>
                            <input type="date" class="form-control" name="event_date" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Start Time *</label>
                            <input type="time" class="form-control" name="event_time" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">End Time</label>
                            <input type="time" class="form-control" name="end_time">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Venue Name</label>
                        <input type="text" class="form-control" name="venue_name">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Event Location *</label>
                        <textarea class="form-control" name="event_location" rows="2" required></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Estimated Guests</label>
                            <input type="number" class="form-control" name="estimated_guests" min="1">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Total Amount</label>
                            <input type="number" class="form-control" name="total_amount" id="totalAmount" min="0" step="0.01">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Down Payment</label>
                            <input type="number" class="form-control" name="down_payment" min="0" step="0.01">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Special Requests</label>
                        <textarea class="form-control" name="special_requests" rows="2"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Booking</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Booking Modals -->
<?php foreach ($bookings as $booking): ?>
<div class="modal fade" id="editBookingModal<?php echo $booking['booking_id']; ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Booking</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Booking Number</label>
                        <input type="text" class="form-control" value="<?php echo $booking['booking_number']; ?>" disabled>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Event Date *</label>
                            <input type="date" class="form-control" name="event_date" value="<?php echo $booking['event_date']; ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Start Time *</label>
                            <input type="time" class="form-control" name="event_time" value="<?php echo $booking['event_time']; ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">End Time</label>
                            <input type="time" class="form-control" name="end_time" value="<?php echo $booking['end_time']; ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Venue Name</label>
                        <input type="text" class="form-control" name="venue_name" value="<?php echo $booking['venue_name']; ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Event Location *</label>
                        <textarea class="form-control" name="event_location" rows="2" required><?php echo $booking['event_location']; ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Package</label>
                            <select class="form-select" name="package_id">
                                <option value="">No Package</option>
                                <?php foreach ($packages as $package): ?>
                                <option value="<?php echo $package['package_id']; ?>" <?php echo $booking['package_id'] == $package['package_id'] ? 'selected' : ''; ?>>
                                    <?php echo $package['package_name']; ?> - <?php echo formatCurrency($package['base_price']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Estimated Guests</label>
                            <input type="number" class="form-control" name="estimated_guests" value="<?php echo $booking['estimated_guests']; ?>" min="1">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Total Amount</label>
                            <input type="number" class="form-control" name="total_amount" value="<?php echo $booking['total_amount']; ?>" min="0" step="0.01">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Down Payment</label>
                            <input type="number" class="form-control" name="down_payment" value="<?php echo $booking['down_payment']; ?>" min="0" step="0.01">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Status *</label>
                            <select class="form-select" name="status" required>
                                <option value="Pending" <?php echo $booking['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="Confirmed" <?php echo $booking['status'] === 'Confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="Ongoing" <?php echo $booking['status'] === 'Ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                                <option value="Completed" <?php echo $booking['status'] === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="Cancelled" <?php echo $booking['status'] === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Special Requests</label>
                        <textarea class="form-control" name="special_requests" rows="2"><?php echo $booking['special_requests']; ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="2"><?php echo $booking['notes']; ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Booking</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteBookingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete booking <strong id="deleteBookingNumber"></strong>?</p>
                <form method="POST" id="deleteBookingForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="booking_id" id="deleteBookingId">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="deleteBookingForm" class="btn btn-danger">Delete</button>
            </div>
        </div>
    </div>
</div>

<!-- Return Item Confirmation Modal -->
<div class="modal fade" id="returnItemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Return</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to return items for booking <strong id="returnBookingNumber"></strong>?</p>
                <p>This will restore the inventory quantities.</p>
                <form method="POST" id="returnItemForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="return_item">
                    <input type="hidden" name="booking_id" id="returnBookingId">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="returnItemForm" class="btn btn-success">Return Items</button>
            </div>
        </div>
    </div>
</div>

<!-- Rent Item Modal -->
<div class="modal fade" id="rentItemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Rent Inventory Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="rent">
                    
                    <div class="mb-3">
                        <label class="form-label">Select Item *</label>
                        <select class="form-select" name="inventory_id" required>
                            <option value="">-- Select Item --</option>
                            <?php foreach ($inventory as $item): ?>
                            <option value="<?php echo $item['inventory_id']; ?>" data-price="<?php echo $item['rental_price']; ?>">
                                <?php echo $item['item_name']; ?> (Available: <?php echo $item['available_quantity']; ?>, Price: <?php echo formatCurrency($item['rental_price']); ?>/day)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Client *</label>
                        <select class="form-select" name="client_id" required>
                            <option value="">-- Select Client --</option>
                            <?php foreach ($clients as $client): ?>
                            <option value="<?php echo $client['client_id']; ?>"><?php echo $client['full_name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Rental Date *</label>
                            <input type="date" class="form-control" name="rental_date" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Return Date *</label>
                            <input type="date" class="form-control" name="return_date" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Quantity *</label>
                        <input type="number" class="form-control" name="quantity" min="1" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Total Amount</label>
                        <input type="text" class="form-control" id="rentalTotal" readonly>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-cart me-2"></i>Rent Item
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/sidebar.php'; ?>
<?php require_once 'includes/footer.php'; ?>

<script>
// Auto-fill total amount when package is selected
document.getElementById('packageSelect').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const price = selectedOption.getAttribute('data-price');
    if (price) {
        document.getElementById('totalAmount').value = price;
    }
});

function deleteBooking(bookingId, bookingNumber) {
    document.getElementById('deleteBookingId').value = bookingId;
    document.getElementById('deleteBookingNumber').textContent = bookingNumber;
    new bootstrap.Modal(document.getElementById('deleteBookingModal')).show();
}

function returnItem(bookingId, bookingNumber) {
    document.getElementById('returnBookingId').value = bookingId;
    document.getElementById('returnBookingNumber').textContent = bookingNumber;
    new bootstrap.Modal(document.getElementById('returnItemModal')).show();
}

// Calculate rental total
document.addEventListener('DOMContentLoaded', function() {
    const itemSelect = document.querySelector('#rentItemModal select[name="inventory_id"]');
    const quantityInput = document.querySelector('#rentItemModal input[name="quantity"]');
    const rentalDateInput = document.querySelector('#rentItemModal input[name="rental_date"]');
    const returnDateInput = document.querySelector('#rentItemModal input[name="return_date"]');
    const totalInput = document.getElementById('rentalTotal');
    
    function calculateTotal() {
        if (!itemSelect || !quantityInput || !rentalDateInput || !returnDateInput || !totalInput) return;
        
        const selectedOption = itemSelect.options[itemSelect.selectedIndex];
        const price = parseFloat(selectedOption.getAttribute('data-price')) || 0;
        const quantity = parseInt(quantityInput.value) || 0;
        const rentalDate = new Date(rentalDateInput.value);
        const returnDate = new Date(returnDateInput.value);
        
        if (rentalDate && returnDate && rentalDate < returnDate) {
            const days = Math.ceil((returnDate - rentalDate) / (1000 * 60 * 60 * 24));
            const total = price * quantity * days;
            totalInput.value = formatCurrency(total);
        } else {
            totalInput.value = '';
        }
    }
    
    if (itemSelect) {
        itemSelect.addEventListener('change', calculateTotal);
        quantityInput.addEventListener('change', calculateTotal);
        rentalDateInput.addEventListener('change', calculateTotal);
        returnDateInput.addEventListener('change', calculateTotal);
    }
});
</script>
