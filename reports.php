<?php
/**
 * Reports Page
 */

$pageTitle = 'Reports';
require_once 'config/config.php';
require_once 'includes/functions.php';

requireLogin();

$db = Database::getInstance();
$role = getCurrentUserRole();
$branchId = getCurrentUserBranchId();

// Get report type
$report_type = $_GET['type'] ?? 'bookings';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Build WHERE clause (alias set per-query below)
$branchFilter = $role !== 'Super Admin';
$params = [];

if ($branchFilter) {
    $params[] = $branchId;
}

// Generate report data based on type
$report_data = [];
$report_title = '';

switch ($report_type) {
    case 'bookings':
        $report_title = 'Booking Report';
        $whereClause = $branchFilter ? ' AND b.branch_id = ?' : '';
        $report_data = $db->fetchAll(
            "SELECT b.*, c.full_name as client_name, p.package_name 
             FROM bookings b 
             JOIN clients c ON b.client_id = c.client_id 
             LEFT JOIN packages p ON b.package_id = p.package_id 
             WHERE b.event_date BETWEEN ? AND ? $whereClause
             ORDER BY b.event_date ASC",
            array_merge([$start_date, $end_date], $params)
        );
        break;
        
    case 'inventory':
        $report_title = 'Inventory Report';
        $whereClause = $branchFilter ? ' AND i.branch_id = ?' : '';
        $report_data = $db->fetchAll(
            "SELECT i.*, b.branch_name 
             FROM inventory i 
             LEFT JOIN branches b ON i.branch_id = b.branch_id 
             WHERE i.status = 'Active' $whereClause
             ORDER BY i.category ASC, i.item_name ASC",
            $params
        );
        break;
        
    case 'financial':
        $report_title = 'Financial Report';
        $whereClause = $branchFilter ? ' AND p.branch_id = ?' : '';
        $report_data = $db->fetchAll(
            "SELECT p.*, i.invoice_number, b.booking_number, c.full_name as client_name, br.branch_name
             FROM payments p
             JOIN invoices i ON p.invoice_id = i.invoice_id
             JOIN bookings b ON p.booking_id = b.booking_id
             JOIN clients c ON p.client_id = c.client_id
             LEFT JOIN branches br ON p.branch_id = br.branch_id
             WHERE p.payment_date BETWEEN ? AND ? $whereClause
             ORDER BY p.payment_date ASC",
            array_merge([$start_date, $end_date], $params)
        );
        break;
        
    case 'event_type':
        $report_title = 'Event Type Analysis';
        $whereClause = $branchFilter ? ' AND b.branch_id = ?' : '';
        $report_data = $db->fetchAll(
            "SELECT b.event_type, COUNT(*) as booking_count, SUM(b.total_amount) as total_revenue
             FROM bookings b
             WHERE b.event_date BETWEEN ? AND ? $whereClause
             GROUP BY b.event_type
             ORDER BY booking_count DESC",
            array_merge([$start_date, $end_date], $params)
        );
        break;
        
    case 'branch':
        if ($role !== 'Super Admin') {
            $report_type = 'bookings';
            $whereClause = $branchFilter ? ' AND b.branch_id = ?' : '';
            $report_data = $db->fetchAll(
                "SELECT b.*, c.full_name as client_name, p.package_name 
                 FROM bookings b 
                 JOIN clients c ON b.client_id = c.client_id 
                 LEFT JOIN packages p ON b.package_id = p.package_id 
                 WHERE b.event_date BETWEEN ? AND ? $whereClause
                 ORDER BY b.event_date ASC",
                array_merge([$start_date, $end_date], $params)
            );
        } else {
            $report_title = 'Branch Performance Report';
            $report_data = $db->fetchAll(
                "SELECT b.branch_id, b.branch_name, 
                        (SELECT COUNT(*) FROM bookings WHERE branch_id = b.branch_id AND event_date BETWEEN ? AND ?) as booking_count,
                        (SELECT COALESCE(SUM(total_amount), 0) FROM bookings WHERE branch_id = b.branch_id AND event_date BETWEEN ? AND ?) as total_revenue
                 FROM branches b
                 WHERE b.status = 'Active'
                 ORDER BY booking_count DESC",
                [$start_date, $end_date, $start_date, $end_date]
            );
        }
        break;
}

$csrf_token = generateCSRFToken();
require_once 'includes/header.php';
?>

<div class="main-content">
    <div class="top-bar">
        <div class="page-title">
            <h1>Reports</h1>
            <p>Generate and export reports</p>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Report Type</label>
                    <select class="form-select" name="type">
                        <option value="bookings" <?php echo $report_type === 'bookings' ? 'selected' : ''; ?>>Bookings</option>
                        <option value="inventory" <?php echo $report_type === 'inventory' ? 'selected' : ''; ?>>Inventory</option>
                        <option value="financial" <?php echo $report_type === 'financial' ? 'selected' : ''; ?>>Financial</option>
                        <option value="event_type" <?php echo $report_type === 'event_type' ? 'selected' : ''; ?>>Event Type Analysis</option>
                        <?php if ($role === 'Super Admin'): ?>
                        <option value="branch" <?php echo $report_type === 'branch' ? 'selected' : ''; ?>>Branch Performance</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Date</label>
                    <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-filter me-2"></i>Generate Report
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><?php echo $report_title; ?> (<?php echo formatDate($start_date); ?> to <?php echo formatDate($end_date); ?>)</span>
            <div>
                <button class="btn btn-sm btn-success" onclick="exportToExcel()">
                    <i class="bi bi-file-earmark-excel me-1"></i> Excel
                </button>
                <button class="btn btn-sm btn-danger" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i> Print
                </button>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($report_data)): ?>
            <div class="text-center text-muted py-4">
                <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                <p class="mt-3">No data available for the selected period</p>
            </div>
            <?php else: ?>
            <div class="table-responsive" id="reportTable">
                <table class="table table-bordered table-striped">
                    <thead class="table-dark">
                        <?php if ($report_type === 'bookings'): ?>
                        <tr>
                            <th>Booking #</th>
                            <th>Client</th>
                            <th>Event Type</th>
                            <th>Event Date</th>
                            <th>Time</th>
                            <th>Location</th>
                            <th>Package</th>
                            <th>Total Amount</th>
                            <th>Status</th>
                        </tr>
                        <?php elseif ($report_type === 'inventory'): ?>
                        <tr>
                            <th>Item Code</th>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Available</th>
                            <th>Total</th>
                            <th>Rental Price</th>
                            <th>Condition</th>
                            <?php if ($role === 'Super Admin'): ?>
                            <th>Branch</th>
                            <?php endif; ?>
                        </tr>
                        <?php elseif ($report_type === 'financial'): ?>
                        <tr>
                            <th>Payment Code</th>
                            <th>Invoice #</th>
                            <th>Booking #</th>
                            <th>Client</th>
                            <th>Payment Date</th>
                            <th>Method</th>
                            <th>Amount</th>
                            <?php if ($role === 'Super Admin'): ?>
                            <th>Branch</th>
                            <?php endif; ?>
                        </tr>
                        <?php elseif ($report_type === 'event_type'): ?>
                        <tr>
                            <th>Event Type</th>
                            <th>Number of Bookings</th>
                            <th>Total Revenue</th>
                        </tr>
                        <?php elseif ($report_type === 'branch'): ?>
                        <tr>
                            <th>Branch</th>
                            <th>Number of Bookings</th>
                            <th>Total Revenue</th>
                        </tr>
                        <?php endif; ?>
                    </thead>
                    <tbody>
                        <?php foreach ($report_data as $row): ?>
                        <?php if ($report_type === 'bookings'): ?>
                        <tr>
                            <td><?php echo $row['booking_number']; ?></td>
                            <td><?php echo $row['client_name']; ?></td>
                            <td><?php echo $row['event_type']; ?></td>
                            <td><?php echo formatDate($row['event_date']); ?></td>
                            <td><?php echo $row['event_time']; ?></td>
                            <td><?php echo substr($row['event_location'], 0, 30); ?></td>
                            <td><?php echo $row['package_name'] ?? 'N/A'; ?></td>
                            <td><?php echo formatCurrency($row['total_amount']); ?></td>
                            <td><?php echo $row['status']; ?></td>
                        </tr>
                        <?php elseif ($report_type === 'inventory'): ?>
                        <tr>
                            <td><?php echo $row['item_code']; ?></td>
                            <td><?php echo $row['item_name']; ?></td>
                            <td><?php echo $row['category']; ?></td>
                            <td><?php echo $row['available_quantity']; ?></td>
                            <td><?php echo $row['total_quantity']; ?></td>
                            <td><?php echo formatCurrency($row['rental_price']); ?></td>
                            <td><?php echo $row['condition_status']; ?></td>
                            <?php if ($role === 'Super Admin'): ?>
                            <td><?php echo $row['branch_name']; ?></td>
                            <?php endif; ?>
                        </tr>
                        <?php elseif ($report_type === 'financial'): ?>
                        <tr>
                            <td><?php echo $row['payment_code']; ?></td>
                            <td><?php echo $row['invoice_number']; ?></td>
                            <td><?php echo $row['booking_number']; ?></td>
                            <td><?php echo $row['client_name']; ?></td>
                            <td><?php echo formatDate($row['payment_date']); ?></td>
                            <td><?php echo $row['payment_method']; ?></td>
                            <td><?php echo formatCurrency($row['amount']); ?></td>
                            <?php if ($role === 'Super Admin'): ?>
                            <td><?php echo $row['branch_name']; ?></td>
                            <?php endif; ?>
                        </tr>
                        <?php elseif ($report_type === 'event_type'): ?>
                        <tr>
                            <td><?php echo $row['event_type']; ?></td>
                            <td><?php echo number_format($row['booking_count']); ?></td>
                            <td><?php echo formatCurrency($row['total_revenue']); ?></td>
                        </tr>
                        <?php elseif ($report_type === 'branch'): ?>
                        <tr>
                            <td><?php echo $row['branch_name']; ?></td>
                            <td><?php echo number_format($row['booking_count']); ?></td>
                            <td><?php echo formatCurrency($row['total_revenue']); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/sidebar.php'; ?>
<?php require_once 'includes/footer.php'; ?>

<script>
function exportToExcel() {
    const table = document.getElementById('reportTable');
    const wb = XLSX.utils.table_to_book(table, {sheet: "Report"});
    XLSX.writeFile(wb, '<?php echo $report_title; ?>_<?php echo date('Y-m-d'); ?>.xlsx');
}
</script>

<script src="https://cdn.sheetjs.com/xlsx-0.20.0/package/dist/xlsx.full.min.js"></script>