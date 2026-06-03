/**
 * Event Planner Pro - Main JavaScript
 */

$(document).ready(function() {
    // Initialize DataTables
    $('.datatable').DataTable({
        responsive: true,
        pageLength: 10,
        order: [[0, 'desc']],
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search..."
        }
    });
    
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Sidebar toggle for mobile
    $('#sidebarToggle').on('click', function() {
        $('.sidebar').toggleClass('show');
        $('#sidebarOverlay').toggleClass('show');
        $('body').toggleClass('sidebar-open');
    });
    
    // Close sidebar when clicking overlay
    $('#sidebarOverlay').on('click', function() {
        $('.sidebar').removeClass('show');
        $('#sidebarOverlay').removeClass('show');
        $('body').removeClass('sidebar-open');
    });
    
    // Close sidebar when clicking close button
    $('#sidebarClose').on('click', function() {
        $('.sidebar').removeClass('show');
        $('#sidebarOverlay').removeClass('show');
        $('body').removeClass('sidebar-open');
    });
    
    // Close sidebar when clicking a menu link on mobile
    if (window.innerWidth <= 992) {
        $('.sidebar .components li a').on('click', function() {
            $('.sidebar').removeClass('show');
            $('#sidebarOverlay').removeClass('show');
            $('body').removeClass('sidebar-open');
        });
    }
    
    // Confirm delete actions
    $('.btn-delete').on('click', function(e) {
        if (!confirm('Are you sure you want to delete this item?')) {
            e.preventDefault();
        }
    });
    
    // Auto-hide flash messages
    setTimeout(function() {
        $('.alert').fadeOut('slow');
    }, 5000);
    
    // Loading overlay for AJAX requests
    $(document).ajaxStart(function() {
        $('.loading-overlay').show();
    });
    
    $(document).ajaxStop(function() {
        $('.loading-overlay').hide();
    });
    
    // Form validation
    $('form[data-validate]').on('submit', function(e) {
        var form = $(this);
        var isValid = true;
        
        form.find('input[required], select[required], textarea[required]').each(function() {
            if ($(this).val() === '') {
                isValid = false;
                $(this).addClass('is-invalid');
            } else {
                $(this).removeClass('is-invalid');
            }
        });
        
        if (!isValid) {
            e.preventDefault();
        }
    });
    
    // Number input validation
    $('input[type="number"]').on('input', function() {
        var min = $(this).attr('min');
        var max = $(this).attr('max');
        var value = parseFloat($(this).val());
        
        if (min !== undefined && value < parseFloat(min)) {
            $(this).val(min);
        }
        
        if (max !== undefined && value > parseFloat(max)) {
            $(this).val(max);
        }
    });
    
    // Date picker initialization
    if ($.fn.datepicker) {
        $('.datepicker').datepicker({
            format: 'yyyy-mm-dd',
            autoclose: true,
            todayHighlight: true
        });
    }
    
    // Currency formatting
    function formatCurrency(amount) {
        return '₱' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    }
    
    // Show toast notification
    function showToast(message, type = 'success') {
        var toastHtml = `
            <div class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;
        
        var toastElement = $(toastHtml);
        $('.toast-container').append(toastElement);
        
        var toast = new bootstrap.Toast(toastElement[0], {
            delay: 3000
        });
        
        toast.show();
        
        toastElement.on('hidden.bs.toast', function() {
            $(this).remove();
        });
    }
    
    // Export functions
    function exportToExcel(tableId, filename) {
        var table = document.getElementById(tableId);
        var wb = XLSX.utils.table_to_book(table, {sheet: "Sheet 1"});
        XLSX.writeFile(wb, filename + '.xlsx');
    }
    
    function exportToPDF(tableId, filename) {
        var table = document.getElementById(tableId);
        var doc = new jsPDF();
        doc.autoTable({html: table});
        doc.save(filename + '.pdf');
    }
    
    // Print function
    function printElement(elementId) {
        var element = document.getElementById(elementId);
        var originalContents = document.body.innerHTML;
        
        document.body.innerHTML = element.innerHTML;
        window.print();
        document.body.innerHTML = originalContents;
        location.reload();
    }
    
    // Make functions global
    window.showToast = showToast;
    window.formatCurrency = formatCurrency;
});

// CSRF token for AJAX requests
$.ajaxSetup({
    headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
});
