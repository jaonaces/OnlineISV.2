<?php
/**
 * Payment Management Page
 */

$pageTitle = 'Payment Management';
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
            $payment_code = 'PAY' . date('ym') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $invoice_id = intval($_POST['invoice_id']);
            $invoice = $db->fetchOne("SELECT * FROM invoices WHERE invoice_id = ?", [$invoice_id]);
            
            if (!$invoice) {
                setFlashMessage('error', 'Invoice not found.');
            } else {
                $payment_date = $_POST['payment_date'];
                $payment_method = $_POST['payment_method'];
                $amount = floatval($_POST['amount']);
                $reference_number = sanitize($_POST['reference_number']);
                $notes = sanitize($_POST['notes']);
                
                $payment_id = $db->insert('payments', [
                    'payment_code' => $payment_code,
                    'invoice_id' => $invoice_id,
                    'booking_id' => $invoice['booking_id'],
                    'client_id' => $invoice['client_id'],
                    'branch_id' => $invoice['branch_id'],
                    'payment_date' => $payment_date,
                    'payment_method' => $payment_method,
                    'amount' => $amount,
                    'reference_number' => $reference_number,
                    'notes' => $notes,
                    'received_by' => getCurrentUserId()
                ]);
                
                // Update invoice status based on payments
                $total_paid = $db->fetchOne(
                    "SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE invoice_id = ?",
                    [$invoice_id]
                )['total'];
                
                $new_status = 'Partial';
                if ($total_paid >= $invoice['total_amount']) {
                    $new_status = 'Paid';
                }
                
                $db->update('invoices', ['status' => $new_status], 'invoice_id = ?', [$invoice_id]);
                
                setFlashMessage('success', 'Payment recorded successfully.');
            }
        } elseif ($action === 'delete') {
            $payment_id = intval($_POST['payment_id']);
            $payment = $db->fetchOne("SELECT * FROM payments WHERE payment_id = ?", [$payment_id]);
            
            // Recalculate invoice status
            $invoice_id = $payment['invoice_id'];
            $db->delete('payments', 'payment_id = ?', [$payment_id]);
            
            $total_paid = $db->fetchOne(
                "SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE invoice_id = ?",
                [$invoice_id]
            )['total'];
            
            $invoice = $db->fetchOne("SELECT * FROM invoices WHERE invoice_id = ?", [$invoice_id]);
            $new_status = 'Sent';
            if ($total_paid > 0 && $total_paid < $invoice['total_amount']) {
                $new_status = 'Partial';
            } elseif ($total_paid >= $invoice['total_amount']) {
                $new_status = 'Paid';
            }
            
            $db->update('invoices', ['status' => $new_status], 'invoice_id = ?', [$invoice_id]);
            
            setFlashMessage('success', 'Payment deleted successfully.');
        }
        
        redirect('payments.php');
    }
}

// Build WHERE clause
$whereClause = '';
$params = [];

// Get filter type from URL parameter
$filter_type = $_GET['filter'] ?? 'all';

if ($role !== 'Super Admin') {
    $whereClause = 'WHERE p.branch_id = ?';
    $params[] = $branchId;
}

// Add event type filter
if ($filter_type === 'rent') {
    $whereClause .= ($whereClause ? ' AND' : 'WHERE') . " b.event_type = 'Rental'";
} elseif ($filter_type === 'event') {
    $whereClause .= ($whereClause ? ' AND' : 'WHERE') . " b.event_type != 'Rental'";
}

// Get payments
$payments = $db->fetchAll(
    "SELECT p.*, i.invoice_number, b.booking_number, c.full_name as client_name, br.branch_name,
            u.full_name as received_by_name, b.event_type
     FROM payments p
     JOIN invoices i ON p.invoice_id = i.invoice_id
     JOIN bookings b ON p.booking_id = b.booking_id
     JOIN clients c ON p.client_id = c.client_id
     LEFT JOIN branches br ON p.branch_id = br.branch_id
     LEFT JOIN users u ON p.received_by = u.user_id
     $whereClause
     ORDER BY p.created_at DESC",
    $params
);

// Get unpaid invoices
$invoices = $db->fetchAll(
    "SELECT i.invoice_id, i.invoice_number, i.total_amount, 
            (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = i.invoice_id) as paid_amount,
            c.full_name as client_name
     FROM invoices i
     JOIN clients c ON i.client_id = c.client_id
     WHERE i.status IN ('Sent', 'Partial')" .
    ($role !== 'Super Admin' ? ' AND i.branch_id = ?' : '') .
    " ORDER BY i.invoice_date DESC",
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
                <h1>Payment Management</h1>
                <p>Record and track payments</p>
            </div>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPaymentModal">
            <i class="bi bi-plus-lg me-2"></i>Record Payment
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
            <div class="d-flex gap-2 mb-3">
                <a href="payments.php?filter=all" class="btn <?php echo $filter_type === 'all' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                    All Payments
                </a>
                <a href="payments.php?filter=rent" class="btn <?php echo $filter_type === 'rent' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                    Rent Payment
                </a>
                <a href="payments.php?filter=event" class="btn <?php echo $filter_type === 'event' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                    Event Payment
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover datatable">
                    <thead>
                        <tr>
                            <th>Payment Code</th>
                            <th>Invoice #</th>
                            <th>Client</th>
                            <th>Event Type</th>
                            <th>Branch</th>
                            <th>Payment Date</th>
                            <th>Method</th>
                            <th>Amount</th>
                            <th>Reference</th>
                            <th>Received By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><strong><?php echo $payment['payment_code']; ?></strong></td>
                            <td><?php echo $payment['invoice_number']; ?></td>
                            <td><?php echo $payment['client_name']; ?></td>
                            <td>
                                <span class="badge badge-<?php echo $payment['event_type'] === 'Rental' ? 'info' : 'primary'; ?>">
                                    <?php echo $payment['event_type']; ?>
                                </span>
                            </td>
                            <td><?php echo $payment['branch_name'] ?? 'N/A'; ?></td>
                            <td><?php echo formatDate($payment['payment_date']); ?></td>
                            <td><?php echo $payment['payment_method']; ?></td>
                            <td><?php echo formatCurrency($payment['amount']); ?></td>
                            <td><?php echo $payment['reference_number'] ?? 'N/A'; ?></td>
                            <td><?php echo $payment['received_by_name']; ?></td>
                            <td>
                                <button class="btn btn-sm btn-danger btn-delete" onclick="deletePayment(<?php echo $payment['payment_id']; ?>, '<?php echo $payment['payment_code']; ?>')">
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

<!-- Add Payment Modal -->
<div class="modal fade" id="addPaymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Record Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label">Select Invoice *</label>
                        <select class="form-select" name="invoice_id" id="invoiceSelect" required onchange="loadInvoiceDetails()">
                            <option value="">Select Invoice</option>
                            <?php foreach ($invoices as $invoice): ?>
                            <?php $balance = $invoice['total_amount'] - $invoice['paid_amount']; ?>
                            <option value="<?php echo $invoice['invoice_id']; ?>" data-balance="<?php echo $balance; ?>">
                                <?php echo $invoice['invoice_number']; ?> - <?php echo $invoice['client_name']; ?> (Balance: <?php echo formatCurrency($balance); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Payment Date *</label>
                            <input type="date" class="form-control" name="payment_date" value="<?php echo getCurrentDate(); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Payment Method *</label>
                            <select class="form-select" name="payment_method" required>
                                <option value="Cash">Cash</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="GCash">GCash</option>
                                <option value="Maya">Maya</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Amount *</label>
                        <input type="number" class="form-control" name="amount" required min="0" step="0.01" id="paymentAmount">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Reference Number</label>
                        <input type="text" class="form-control" name="reference_number">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Record Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deletePaymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete payment <strong id="deletePaymentCode"></strong>?</p>
                <form method="POST" id="deletePaymentForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="payment_id" id="deletePaymentId">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="deletePaymentForm" class="btn btn-danger">Delete</button>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/sidebar.php'; ?>
<?php require_once 'includes/footer.php'; ?>

<script>
function loadInvoiceDetails() {
    const select = document.getElementById('invoiceSelect');
    const selectedOption = select.options[select.selectedIndex];
    const balance = selectedOption.getAttribute('data-balance');
    
    if (balance) {
        document.getElementById('paymentAmount').value = balance;
        document.getElementById('paymentAmount').max = balance;
    }
}

function deletePayment(paymentId, paymentCode) {
    document.getElementById('deletePaymentId').value = paymentId;
    document.getElementById('deletePaymentCode').textContent = paymentCode;
    new bootstrap.Modal(document.getElementById('deletePaymentModal')).show();
}

function setPaymentType(type) {
    // This function can be used to pre-select payment type in the modal
    console.log('Payment type selected: ' + type);
}
</script>
