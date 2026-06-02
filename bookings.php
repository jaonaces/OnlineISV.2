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
                'status' => 'Inquiry',
                'total_amount' => $total_amount,
                'down_payment' => $down_payment,
                'balance' => $total_amount - $down_payment,
                'special_requests' => $special_requests,
                'notes' => $notes,
                'created_by' => getCurrentUserId()
            ]);
            
            logAudit('Created', 'Booking', $booking_id, "Created booking: $booking_number");
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
            
            logAudit('Updated', 'Booking', $booking_id, "Updated booking status to: $status");
            setFlashMessage('success', 'Booking updated successfully.');
        } elseif ($action === 'delete') {
            $booking_id = intval($_POST['booking_id']);
            $booking = $db->fetchOne("SELECT booking_number FROM bookings WHERE booking_id = ?", [$booking_id]);
            $db->delete('bookings', 'booking_id = ?', [$booking_id]);
            logAudit('Deleted', 'Booking', $booking_id, "Deleted booking: " . $booking['booking_number']);
            setFlashMessage('success', 'Booking deleted successfully.');
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
    "SELECT b.*, c.full_name as client_name, p.package_name, br.branch_name 
     FROM bookings b 
     JOIN clients c ON b.client_id = c.client_id 
     LEFT JOIN packages p ON b.package_id = p.package_id 
     LEFT JOIN branches br ON b.branch_id = br.branch_id 
     $whereClause 
     ORDER BY b.event_date DESC, b.event_time DESC",
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

$csrf_token = generateCSRFToken();
require_once 'includes/header.php';
?>

<div class="main-content">
    <div class="top-bar">
        <div class="page-title">
            <h1>Booking Management</h1>
            <p>Manage event bookings</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBookingModal">
            <i class="bi bi-plus-lg me-2"></i>New Booking
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
                            <th>Booking #</th>
                            <th>Client</th>
                            <th>Event Type</th>
                            <th>Event Date</th>
                            <th>Time</th>
                            <th>Location</th>
                            <th>Package</th>
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
                            <td><?php echo formatDate($booking['event_date']); ?></td>
                            <td><?php echo $booking['event_time']; ?></td>
                            <td><?php echo $booking['venue_name'] ?? $booking['event_location']; ?></td>
                            <td><?php echo $booking['package_name'] ?? 'N/A'; ?></td>
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
                                <option value="Inquiry" <?php echo $booking['status'] === 'Inquiry' ? 'selected' : ''; ?>>Inquiry</option>
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
</script>
