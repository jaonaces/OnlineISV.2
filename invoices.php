<?php
/**
 * Invoice Management Page
 */

$pageTitle = 'Invoice Management';
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
        
        if ($action === 'create') {
            $booking_id = intval($_POST['booking_id']);
            $booking = $db->fetchOne("SELECT * FROM bookings WHERE booking_id = ?", [$booking_id]);
            
            if (!$booking) {
                setFlashMessage('error', 'Booking not found.');
            } else {
                $invoice_number = generateInvoiceNumber();
                $invoice_date = $_POST['invoice_date'];
                $due_date = $_POST['due_date'];
                $subtotal = $booking['total_amount'];
                $tax = 0;
                $discount = floatval($_POST['discount']) ?: 0;
                $total_amount = $subtotal - $discount;
                $notes = sanitize($_POST['notes']);

                $invoice_id = $db->insert('invoices', [
                    'invoice_number' => $invoice_number,
                    'booking_id' => $booking_id,
                    'client_id' => $booking['client_id'],
                    'branch_id' => $booking['branch_id'],
                    'invoice_date' => $invoice_date,
                    'due_date' => $due_date,
                    'subtotal' => $subtotal,
                    'tax' => $tax,
                    'discount' => $discount,
                    'total_amount' => $total_amount,
                    'status' => 'Sent',
                    'notes' => $notes,
                    'created_by' => getCurrentUserId()
                ]);

                // If downpayment was made on the booking, create a payment record for it
                if ($booking['down_payment'] > 0) {
                    $payment_code = 'PAY' . date('ym') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    $db->insert('payments', [
                        'payment_code' => $payment_code,
                        'invoice_id' => $invoice_id,
                        'booking_id' => $booking_id,
                        'client_id' => $booking['client_id'],
                        'branch_id' => $booking['branch_id'],
                        'payment_date' => $invoice_date,
                        'payment_method' => 'Cash',
                        'amount' => $booking['down_payment'],
                        'reference_number' => 'Downpayment',
                        'notes' => 'Downpayment from booking',
                        'received_by' => $booking['created_by']
                    ]);

                    // Update invoice status based on downpayment
                    if ($booking['down_payment'] >= $total_amount) {
                        $db->update('invoices', ['status' => 'Paid'], 'invoice_id = ?', [$invoice_id]);
                    } else {
                        $db->update('invoices', ['status' => 'Partial'], 'invoice_id = ?', [$invoice_id]);
                    }
                }

                setFlashMessage('success', 'Invoice created successfully.');
            }
        } elseif ($action === 'update_status') {
            $invoice_id = intval($_POST['invoice_id']);
            $status = $_POST['status'];
            
            $db->update('invoices', ['status' => $status], 'invoice_id = ?', [$invoice_id]);
            setFlashMessage('success', 'Invoice status updated successfully.');
        } elseif ($action === 'delete') {
            $invoice_id = intval($_POST['invoice_id']);
            
            // Check if invoice has payments
            $has_payments = $db->fetchOne("SELECT COUNT(*) as count FROM payments WHERE invoice_id = ?", [$invoice_id])['count'];
            if ($has_payments > 0) {
                setFlashMessage('error', 'Cannot delete invoice with existing payments.');
            } else {
                $invoice = $db->fetchOne("SELECT invoice_number FROM invoices WHERE invoice_id = ?", [$invoice_id]);
                $db->delete('invoices', 'invoice_id = ?', [$invoice_id]);
                setFlashMessage('success', 'Invoice deleted successfully.');
            }
        }
        
        redirect('invoices.php');
    }
}

// Build WHERE clause
$whereClause = '';
$params = [];

if ($role !== 'Super Admin') {
    $whereClause = 'WHERE i.branch_id = ?';
    $params[] = $branchId;
}

// Get invoices
$invoices = $db->fetchAll(
    "SELECT i.*, b.booking_number, c.full_name as client_name, br.branch_name,
            (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = i.invoice_id) as paid_amount
     FROM invoices i
     JOIN bookings b ON i.booking_id = b.booking_id
     JOIN clients c ON i.client_id = c.client_id
     LEFT JOIN branches br ON i.branch_id = br.branch_id
     $whereClause
     ORDER BY i.created_at DESC",
    $params
);

// Get bookings without invoices
$bookings = $db->fetchAll(
    "SELECT b.booking_id, b.booking_number, b.total_amount, c.full_name as client_name
     FROM bookings b
     JOIN clients c ON b.client_id = c.client_id
     WHERE b.booking_id NOT IN (SELECT booking_id FROM invoices)
     AND b.status IN ('Confirmed', 'Ongoing', 'Completed')" .
    ($role !== 'Super Admin' ? ' AND b.branch_id = ?' : '') .
    " ORDER BY b.event_date DESC",
    $role !== 'Super Admin' ? [$branchId] : []
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
                <h1>Invoice Management</h1>
                <p>Manage client invoices</p>
            </div>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createInvoiceModal">
            <i class="bi bi-plus-lg me-2"></i>Create Invoice
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
                            <th>Invoice #</th>
                            <th>Booking #</th>
                            <th>Client</th>
                            <th>Branch</th>
                            <th>Invoice Date</th>
                            <th>Due Date</th>
                            <th>Total</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $invoice): ?>
                        <tr>
                            <td><strong><?php echo $invoice['invoice_number']; ?></strong></td>
                            <td><?php echo $invoice['booking_number']; ?></td>
                            <td><?php echo $invoice['client_name']; ?></td>
                            <td><?php echo $invoice['branch_name'] ?? 'N/A'; ?></td>
                            <td><?php echo formatDate($invoice['invoice_date']); ?></td>
                            <td><?php echo $invoice['due_date'] ? formatDate($invoice['due_date']) : 'N/A'; ?></td>
                            <td><?php echo formatCurrency($invoice['total_amount']); ?></td>
                            <td><?php echo formatCurrency($invoice['paid_amount']); ?></td>
                            <td><?php echo formatCurrency($invoice['total_amount'] - $invoice['paid_amount']); ?></td>
                            <td>
                                <span class="badge badge-<?php 
                                    echo $invoice['status'] === 'Paid' ? 'success' : 
                                        ($invoice['status'] === 'Partial' ? 'warning' : 
                                        ($invoice['status'] === 'Overdue' ? 'danger' : 'info')); 
                                ?>">
                                    <?php echo $invoice['status']; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Create Invoice Modal -->
<div class="modal fade" id="createInvoiceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Invoice</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="mb-3">
                        <label class="form-label">Select Booking *</label>
                        <select class="form-select" name="booking_id" id="bookingSelect" required onchange="loadBookingDetails()">
                            <option value="">Select Booking</option>
                            <?php foreach ($bookings as $booking): ?>
                            <option value="<?php echo $booking['booking_id']; ?>" data-amount="<?php echo $booking['total_amount']; ?>">
                                <?php echo $booking['booking_number']; ?> - <?php echo $booking['client_name']; ?> (<?php echo formatCurrency($booking['total_amount']); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Invoice Date *</label>
                            <input type="date" class="form-control" name="invoice_date" value="<?php echo getCurrentDate(); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Due Date</label>
                            <input type="date" class="form-control" name="due_date">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Discount</label>
                        <input type="number" class="form-control" name="discount" min="0" step="0.01" value="0">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="2"></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <strong>Tax Rate:</strong> <?php echo (TAX_RATE * 100); ?>%
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Invoice</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteInvoiceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete invoice <strong id="deleteInvoiceNumber"></strong>?</p>
                <form method="POST" id="deleteInvoiceForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="invoice_id" id="deleteInvoiceId">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="deleteInvoiceForm" class="btn btn-danger">Delete</button>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/sidebar.php'; ?>
<?php require_once 'includes/footer.php'; ?>

<script>
function viewInvoice(invoiceId) {
    // Implement invoice view/print functionality
    alert('Invoice view feature - Invoice ID: ' + invoiceId);
}

function deleteInvoice(invoiceId, invoiceNumber) {
    document.getElementById('deleteInvoiceId').value = invoiceId;
    document.getElementById('deleteInvoiceNumber').textContent = invoiceNumber;
    new bootstrap.Modal(document.getElementById('deleteInvoiceModal')).show();
}
</script>
