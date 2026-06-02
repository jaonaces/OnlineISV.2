<?php
/**
 * Event Calendar Page
 */

$pageTitle = 'Event Calendar';
require_once 'config/config.php';
require_once 'includes/functions.php';

requireLogin();

$db = Database::getInstance();
$role = getCurrentUserRole();
$branchId = getCurrentUserBranchId();

// Get view type (day, week, month)
$view = $_GET['view'] ?? 'month';
$date = $_GET['date'] ?? date('Y-m-d');

// Build WHERE clause
$whereClause = '';
$params = [];

if ($role !== 'Super Admin') {
    $whereClause = 'WHERE c.branch_id = ?';
    $params[] = $branchId;
}

// Get bookings for calendar
$bookings = $db->fetchAll(
    "SELECT b.*, c.full_name as client_name, p.package_name 
     FROM bookings b 
     JOIN clients c ON b.client_id = c.client_id 
     LEFT JOIN packages p ON b.package_id = p.package_id 
     $whereClause 
     AND event_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
     AND event_date <= DATE_ADD(CURDATE(), INTERVAL 365 DAY)
     ORDER BY b.event_date ASC, b.event_time ASC",
    $params
);

$csrf_token = generateCSRFToken();
require_once 'includes/header.php';
?>

<div class="main-content">
    <div class="top-bar">
        <div class="page-title">
            <h1>Event Calendar</h1>
            <p>View and manage scheduled events</p>
        </div>
        <div class="d-flex gap-2">
            <div class="btn-group">
                <button class="btn btn-outline-primary <?php echo $view === 'month' ? 'active' : ''; ?>" onclick="changeView('month')">Month</button>
                <button class="btn btn-outline-primary <?php echo $view === 'week' ? 'active' : ''; ?>" onclick="changeView('week')">Week</button>
                <button class="btn btn-outline-primary <?php echo $view === 'day' ? 'active' : ''; ?>" onclick="changeView('day')">Day</button>
            </div>
            <button class="btn btn-primary" onclick="location.href='bookings.php'">
                <i class="bi bi-plus-lg me-2"></i>New Booking
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
            <div id="calendar"></div>
        </div>
    </div>

    <!-- Upcoming Events List -->
    <div class="card mt-4">
        <div class="card-header">
            <i class="bi bi-calendar-event me-2"></i>
            Upcoming Events
        </div>
        <div class="card-body">
            <?php if (empty($bookings)): ?>
            <div class="text-center text-muted py-4">
                <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                <p class="mt-3">No upcoming events</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Client</th>
                            <th>Event Type</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                        <tr>
                            <td><?php echo formatDate($booking['event_date']); ?></td>
                            <td><?php echo $booking['event_time']; ?></td>
                            <td><?php echo $booking['client_name']; ?></td>
                            <td><?php echo $booking['event_type']; ?></td>
                            <td><?php echo $booking['venue_name'] ?? substr($booking['event_location'], 0, 30); ?></td>
                            <td>
                                <span class="badge badge-<?php 
                                    echo $booking['status'] === 'Confirmed' ? 'success' : 
                                        ($booking['status'] === 'Pending' ? 'warning' : 
                                        ($booking['status'] === 'Cancelled' ? 'danger' : 'info')); 
                                ?>">
                                    <?php echo $booking['status']; ?>
                                </span>
                            </td>
                            <td>
                                <a href="bookings.php" class="btn btn-sm btn-primary">
                                    <i class="bi bi-eye"></i>
                                </a>
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

<?php require_once 'includes/sidebar.php'; ?>
<?php require_once 'includes/footer.php'; ?>

<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendar');
    const bookings = <?php echo json_encode($bookings); ?>;
    
    const events = bookings.map(booking => ({
        title: booking.event_type + ' - ' + booking.client_name,
        start: booking.event_date + 'T' + booking.event_time,
        end: booking.end_time ? booking.event_date + 'T' + booking.end_time : null,
        backgroundColor: getStatusColor(booking.status),
        borderColor: getStatusColor(booking.status),
        extendedProps: {
            booking_id: booking.booking_id,
            booking_number: booking.booking_number,
            status: booking.status
        }
    }));
    
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: '<?php echo $view; ?>',
        initialDate: '<?php echo $date; ?>',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        events: events,
        eventClick: function(info) {
            const bookingId = info.event.extendedProps.booking_id;
            window.location.href = 'bookings.php';
        },
        eventDidMount: function(info) {
            const tooltip = new bootstrap.Tooltip(info.el, {
                title: info.event.title,
                placement: 'top'
            });
        }
    });
    
    calendar.render();
});

function getStatusColor(status) {
    const colors = {
        'Confirmed': '#10b981',
        'Pending': '#f59e0b',
        'Inquiry': '#3b82f6',
        'Ongoing': '#8b5cf6',
        'Completed': '#06b6d4',
        'Cancelled': '#ef4444'
    };
    return colors[status] || '#3b82f6';
}

function changeView(view) {
    const url = new URL(window.location);
    url.searchParams.set('view', view);
    window.location.href = url.toString();
}
</script>
