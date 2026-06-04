<?php
/**
 * Dashboard Page
 */

$pageTitle = 'Dashboard';
require_once 'config/config.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

$db = Database::getInstance();
$role = getCurrentUserRole();
$branchId = getCurrentUserBranchId();

// Branch filter for Super Admin
$selectedBranchId = null;
if ($role === 'Super Admin' && isset($_GET['branch']) && !empty($_GET['branch'])) {
    $selectedBranchId = intval($_GET['branch']);
}

// Branch filter flag (each query uses its own alias-prefixed WHERE clause)
$branchFilter = $role !== 'Super Admin' || $selectedBranchId !== null;
$filterBranchId = $selectedBranchId !== null ? $selectedBranchId : $branchId;

// Get statistics
$totalBookings = $db->fetchOne(
    "SELECT COUNT(*) as count FROM bookings b" .
    ($branchFilter ? ' WHERE b.branch_id = ?' : ''),
    $branchFilter ? [$filterBranchId] : []
)['count'];

$upcomingEvents = $db->fetchOne(
    "SELECT COUNT(*) as count FROM bookings b WHERE b.event_date >= CURDATE() AND b.status IN ('Confirmed', 'Ongoing')" .
    ($branchFilter ? ' AND b.branch_id = ?' : ''),
    $branchFilter ? [$filterBranchId] : []
)['count'];

// Monthly revenue
$currentMonth = date('Y-m');
$monthlyRevenue = $db->fetchOne(
    "SELECT COALESCE(SUM(p.amount), 0) as total 
     FROM payments p
     JOIN invoices i ON p.invoice_id = i.invoice_id
     WHERE DATE_FORMAT(p.payment_date, '%Y-%m') = ?" . 
    ($branchFilter ? ' AND p.branch_id = ?' : ''),
    $branchFilter ? [$currentMonth, $filterBranchId] : [$currentMonth]
)['total'];

// Low stock items
$lowStockItems = $db->fetchAll(
    "SELECT i.item_name, i.available_quantity, i.total_quantity 
     FROM inventory i
     WHERE i.available_quantity <= 5 AND i.status = 'Active'" .
    ($branchFilter ? ' AND i.branch_id = ?' : '') .
    " ORDER BY i.available_quantity ASC LIMIT 5",
    $branchFilter ? [$filterBranchId] : []
);

// Recent bookings
$recentBookings = $db->fetchAll(
    "SELECT b.booking_number, b.event_type, b.event_date, b.status, c.full_name as client_name, br.branch_name
     FROM bookings b
     JOIN clients c ON b.client_id = c.client_id
     JOIN branches br ON b.branch_id = br.branch_id" .
    ($branchFilter ? ' WHERE b.branch_id = ?' : '') .
    " ORDER BY b.created_at DESC LIMIT 5",
    $branchFilter ? [$filterBranchId] : []
);

// Best selling packages
$bestPackages = $db->fetchAll(
    "SELECT p.package_name, COUNT(b.booking_id) as booking_count, SUM(b.total_amount) as revenue
     FROM packages p
     LEFT JOIN bookings b ON p.package_id = b.package_id
     " . ($branchFilter ? 'WHERE p.branch_id = ?' : '') . "
     GROUP BY p.package_id, p.package_name
     ORDER BY booking_count DESC LIMIT 5",
    $branchFilter ? [$filterBranchId] : []
);

// Chart data - Monthly revenue (last 6 months)
$monthlyRevenueData = [];
$monthlyLabels = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $monthlyLabels[] = date('M Y', strtotime("-$i months"));
    
    $revenue = $db->fetchOne(
        "SELECT COALESCE(SUM(p.amount), 0) as total 
         FROM payments p
         JOIN invoices i ON p.invoice_id = i.invoice_id
         WHERE DATE_FORMAT(p.payment_date, '%Y-%m') = ?" .
        ($branchFilter ? ' AND p.branch_id = ?' : ''),
        $branchFilter ? [$month, $filterBranchId] : [$month]
    )['total'];
    
    $monthlyRevenueData[] = floatval($revenue);
}

// Chart data - Booking trends by event type
$eventTypeData = $db->fetchAll(
    "SELECT b.event_type, COUNT(*) as count
     FROM bookings b" .
    ($branchFilter ? ' WHERE b.branch_id = ?' : '') .
    " GROUP BY b.event_type
     ORDER BY count DESC",
    $branchFilter ? [$filterBranchId] : []
);

$eventTypes = array_column($eventTypeData, 'event_type');
$eventTypeCounts = array_column($eventTypeData, 'count');

// Branch data for Super Admin
$branches = [];
if ($role === 'Super Admin') {
    $branches = $db->fetchAll("SELECT branch_id, branch_name FROM branches WHERE status = 'Active'");
}
?>

<div class="main-content">
    <div class="top-bar">
        <div class="d-flex align-items-center gap-3">
            <button class="mobile-menu-toggle" id="sidebarToggle">
                <i class="bi bi-list"></i>
            </button>
            <div class="page-title">
                <h1>Dashboard</h1>
                <p>Welcome back, <?php echo $_SESSION['full_name']; ?>!</p>
            </div>
        </div>
        <div class="d-flex gap-2">
            <?php if ($role === 'Super Admin'): ?>
            <select class="form-select" id="branchFilter" style="width: 200px;">
                <option value="">All Branches</option>
                <?php foreach ($branches as $branch): ?>
                <option value="<?php echo $branch['branch_id']; ?>" <?php echo $selectedBranchId == $branch['branch_id'] ? 'selected' : ''; ?>><?php echo $branch['branch_name']; ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <button class="btn btn-primary" onclick="location.reload()">
                <i class="bi bi-arrow-clockwise me-2"></i>Refresh
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

    <!-- Stats Cards - Asymmetric Bento Grid -->
    <div class="row g-4 mb-5">
        <div class="col-lg-5 col-md-12">
            <div class="stats-card primary h-100">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <div class="icon mb-3">
                            <i class="bi bi-cash-coin"></i>
                        </div>
                        <div class="value"> <?php echo formatCurrency($monthlyRevenue); ?></div>
                        <div class="label">Monthly Revenue</div>
                    </div>
                    <div class="text-end">
                        <div class="badge badge-primary mb-2">All Time</div>
                        <p class="mb-0 text-muted small">Across all branches</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stats-card success h-100">
                <div class="icon">
                    <i class="bi bi-calendar-event"></i>
                </div>
                <div class="value"><?php echo number_format($upcomingEvents); ?></div>
                <div class="label">Upcoming Events</div>
            </div>
        </div>
        <div class="col-lg-4 col-md-6">
            <div class="stats-card warning h-100">
                <div class="icon">
                    <i class="bi bi-calendar-check"></i>
                </div>
                <div class="value"><?php echo number_format($totalBookings); ?></div>
                <div class="label">Total Bookings</div>
            </div>
        </div>
        <div class="col-lg-3 offset-lg-3 col-md-6">
            <div class="stats-card danger h-100">
                <div class="icon">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
                <div class="value"><?php echo count($lowStockItems); ?></div>
                <div class="label">Low Stock Alerts</div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stats-card info h-100">
                <div class="icon">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
                <div class="value"><?php echo $monthlyRevenue > 0 ? '+12%' : '0%'; ?></div>
                <div class="label">Growth Rate</div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-graph-up me-2"></i>
                    Monthly Revenue (Last 6 Months)
                </div>
                <div class="card-body">
                    <canvas id="revenueChart" height="300"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-pie-chart me-2"></i>
                    Bookings by Event Type
                </div>
                <div class="card-body">
                    <canvas id="eventTypeChart" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Tables Row -->
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-clock-history me-2"></i>
                    Recent Bookings
                </div>
                <div class="card-body">
                    <?php if (empty($recentBookings)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                        <p class="mt-3">No recent bookings</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Booking #</th>
                                    <th>Client</th>
                                    <th>Event Type</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentBookings as $booking): ?>
                                <tr>
                                    <td><?php echo $booking['booking_number']; ?></td>
                                    <td><?php echo $booking['client_name']; ?></td>
                                    <td><?php echo $booking['event_type']; ?></td>
                                    <td><?php echo formatDate($booking['event_date']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php 
                                            echo $booking['status'] === 'Confirmed' ? 'success' : 
                                                ($booking['status'] === 'Pending' ? 'warning' : 
                                                ($booking['status'] === 'Cancelled' ? 'danger' : 'info')); 
                                        ?>">
                                            <?php echo $booking['status']; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-box-seam me-2"></i>
                    Best-Selling Packages
                </div>
                <div class="card-body">
                    <?php if (empty($bestPackages)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                        <p class="mt-3">No package data available</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Package Name</th>
                                    <th>Bookings</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bestPackages as $package): ?>
                                <tr>
                                    <td><?php echo $package['package_name']; ?></td>
                                    <td><?php echo number_format($package['booking_count']); ?></td>
                                    <td><?php echo formatCurrency($package['revenue']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Low Stock Alert -->
    <?php if (!empty($lowStockItems)): ?>
    <div class="card mb-4">
        <div class="card-header text-danger">
            <i class="bi bi-exclamation-triangle me-2"></i>
            Low Stock Alerts
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Available</th>
                            <th>Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lowStockItems as $item): ?>
                        <tr>
                            <td><?php echo $item['item_name']; ?></td>
                            <td>
                                <span class="badge badge-danger"><?php echo $item['available_quantity']; ?></span>
                            </td>
                            <td><?php echo $item['total_quantity']; ?></td>
                            <td>
                                <span class="badge badge-danger">Critical</span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/sidebar.php'; ?>
<?php require_once 'includes/footer.php'; ?>

<script>
// Revenue Chart - Modern Styling
const revenueCtx = document.getElementById('revenueChart').getContext('2d');
new Chart(revenueCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($monthlyLabels); ?>,
        datasets: [{
            label: 'Revenue',
            data: <?php echo json_encode($monthlyRevenueData); ?>,
            borderColor: '#059669',
            backgroundColor: (context) => {
                const ctx = context.chart.ctx;
                const gradient = ctx.createLinearGradient(0, 0, 0, 300);
                gradient.addColorStop(0, 'rgba(5, 150, 105, 0.3)');
                gradient.addColorStop(1, 'rgba(5, 150, 105, 0.0)');
                return gradient;
            },
            fill: true,
            tension: 0.4,
            borderWidth: 3,
            pointBackgroundColor: '#059669',
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2,
            pointRadius: 5,
            pointHoverRadius: 8,
            pointHoverBackgroundColor: '#047857',
            pointHoverBorderColor: '#ffffff',
            pointHoverBorderWidth: 3
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: {
            duration: 2000,
            easing: 'easeOutQuart'
        },
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                backgroundColor: 'rgba(10, 10, 10, 0.9)',
                titleColor: '#ffffff',
                bodyColor: '#ffffff',
                borderColor: '#059669',
                borderWidth: 1,
                cornerRadius: 12,
                padding: 16,
                displayColors: false,
                callbacks: {
                    label: function(context) {
                        return '₱' + context.parsed.y.toLocaleString();
                    }
                }
            }
        },
        scales: {
            x: {
                grid: {
                    display: false,
                    drawBorder: false
                },
                ticks: {
                    color: '#737373',
                    font: {
                        family: 'Manrope',
                        size: 12
                    }
                }
            },
            y: {
                beginAtZero: true,
                grid: {
                    color: '#e5e5e5',
                    drawBorder: false
                },
                ticks: {
                    color: '#737373',
                    font: {
                        family: 'Manrope',
                        size: 12
                    },
                    callback: function(value) {
                        return '₱' + value.toLocaleString();
                    }
                }
            }
        },
        interaction: {
            intersect: false,
            mode: 'index'
        }
    }
});

// Event Type Chart - Modern Styling
const eventTypeCtx = document.getElementById('eventTypeChart').getContext('2d');
new Chart(eventTypeCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode($eventTypes); ?>,
        datasets: [{
            data: <?php echo json_encode($eventTypeCounts); ?>,
            backgroundColor: [
                '#059669',
                '#0891b2',
                '#d97706',
                '#dc2626',
                '#7c3aed',
                '#0891b2'
            ],
            borderColor: '#ffffff',
            borderWidth: 3,
            hoverOffset: 10
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: {
            animateRotate: true,
            animateScale: true,
            duration: 2000,
            easing: 'easeOutQuart'
        },
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 20,
                    usePointStyle: true,
                    pointStyle: 'circle',
                    font: {
                        family: 'Manrope',
                        size: 13,
                        weight: '500'
                    },
                    color: '#171717'
                }
            },
            tooltip: {
                backgroundColor: 'rgba(10, 10, 10, 0.9)',
                titleColor: '#ffffff',
                bodyColor: '#ffffff',
                borderColor: '#059669',
                borderWidth: 1,
                cornerRadius: 12,
                padding: 16,
                callbacks: {
                    label: function(context) {
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = ((context.parsed / total) * 100).toFixed(1);
                        return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                    }
                }
            }
        },
        cutout: '65%'
    }
});

// Branch filter for Super Admin
<?php if ($role === 'Super Admin'): ?>
document.getElementById('branchFilter').addEventListener('change', function() {
    const branchId = this.value;
    if (branchId) {
        window.location.href = '?branch=' + branchId;
    } else {
        window.location.href = 'dashboard.php';
    }
});
<?php endif; ?>
</script>