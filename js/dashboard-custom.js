/**
 * Event Manager Dashboard Custom Scripts
 */

(function($) {
    'use strict';

    // Hide preloader when page is loaded
    $(window).on('load', function() {
        $('body').addClass('loaded');
        setTimeout(function() {
            $('.preloader').remove();
        }, 300);
    });

    // Logout Handler
    $(document).on('click', '.logout-link', function(e) {
        e.preventDefault();

        if (confirm('Are you sure you want to logout?')) {
            $.ajax({
                url: scDashboard.ajaxurl,
                type: 'POST',
                data: {
                    action: 'event_manager_logout',
                    nonce: scDashboard.nonce
                },
                success: function(response) {
                    if (response.success) {
                        window.location.href = response.data.redirect;
                    }
                }
            });
        }
    });

    // Settings Form Handler
    $('#platform-settings-form').on('submit', function(e) {
        e.preventDefault();

        var formData = new FormData(this);
        formData.append('action', 'update_event_manager_settings');
        formData.append('nonce', scDashboard.nonce);
        formData.append('settings_type', 'platform');

        var submitBtn = $(this).find('button[type="submit"]');
        submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showNotification('success', response.data.message);
                } else {
                    showNotification('error', response.data.message);
                }
                submitBtn.prop('disabled', false).html('<i class="fa fa-save"></i> Save Changes');
            },
            error: function(xhr, status, error) {
                var errorMsg = 'Failed to save platform settings. ';
                if (status === 'timeout') {
                    errorMsg += 'The request timed out. Please check your connection and try again.';
                } else if (xhr.status === 0) {
                    errorMsg += 'Could not connect to server. Please check your internet connection.';
                } else if (xhr.status === 403) {
                    errorMsg += 'You do not have permission to perform this action.';
                } else if (xhr.status === 500) {
                    errorMsg += 'Server error occurred. Please contact support if this persists.';
                } else {
                    errorMsg += 'Please try again or contact support.';
                }
                showNotification('error', errorMsg);
                submitBtn.prop('disabled', false).html('<i class="fa fa-save"></i> Save Changes');
            }
        });
    });

    // Account Settings Form Handler
    $('#account-settings-form').on('submit', function(e) {
        e.preventDefault();

        var formData = $(this).serialize();
        formData += '&action=update_event_manager_settings&nonce=' + scDashboard.nonce + '&settings_type=account';

        var submitBtn = $(this).find('button[type="submit"]');
        submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    showNotification('success', response.data.message);
                } else {
                    showNotification('error', response.data.message);
                }
                submitBtn.prop('disabled', false).html('<i class="fa fa-save"></i> Update Account');
            },
            error: function(xhr, status, error) {
                var errorMsg = 'Failed to update account settings. ';
                if (status === 'timeout') {
                    errorMsg += 'The request timed out. Please check your connection and try again.';
                } else if (xhr.status === 0) {
                    errorMsg += 'Could not connect to server. Please check your internet connection.';
                } else if (xhr.status === 403) {
                    errorMsg += 'Your session may have expired. Please refresh the page and try again.';
                } else if (xhr.status === 500) {
                    errorMsg += 'Server error occurred. Please contact support if this persists.';
                } else {
                    errorMsg += 'Please try again or contact support.';
                }
                showNotification('error', errorMsg);
                submitBtn.prop('disabled', false).html('<i class="fa fa-save"></i> Update Account');
            }
        });
    });

    // Load Dashboard Statistics
    function loadDashboardStats() {
        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_dashboard_stats',
                nonce: scDashboard.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#total-events').text(response.data.total_events);
                    $('#total-attendees').text(response.data.total_attendees);
                    $('#total-tickets').text(response.data.total_tickets);

                    // Update recent events list
                    if (response.data.recent_events.length > 0) {
                        var eventsList = '';
                        $.each(response.data.recent_events, function(index, event) {
                            eventsList += '<li class="list-group-item">' + event.post_title + '</li>';
                        });
                        $('#recent-events-list').html(eventsList);
                    }
                }
            }
        });
    }

    // Load stats on dashboard page
    if ($('body').hasClass('dashboard-home')) {
        loadDashboardStats();
    }

    // Coupon Form Handler
    $('#create-coupon-form').on('submit', function(e) {
        e.preventDefault();

        var formData = $(this).serialize();
        formData += '&action=create_discount_coupon&nonce=' + scDashboard.nonce;

        var submitBtn = $(this).find('button[type="submit"]');
        submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Creating...');

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    showNotification('success', response.data.message);
                    $('#create-coupon-form')[0].reset();
                    // Reload coupons list
                    location.reload();
                } else {
                    showNotification('error', response.data.message);
                }
                submitBtn.prop('disabled', false).html('<i class="fa fa-plus"></i> Create Coupon');
            },
            error: function(xhr, status, error) {
                var errorMsg = 'Failed to create coupon. ';
                if (status === 'timeout') {
                    errorMsg += 'The request timed out. Please check your connection and try again.';
                } else if (xhr.status === 0) {
                    errorMsg += 'Could not connect to server. Please check your internet connection.';
                } else if (xhr.status === 403) {
                    errorMsg += 'You do not have permission to create coupons.';
                } else if (xhr.status === 429) {
                    errorMsg += 'Too many requests. Please wait a moment and try again.';
                } else if (xhr.status === 500) {
                    errorMsg += 'Server error occurred. Please contact support if this persists.';
                } else {
                    errorMsg += 'Please try again or contact support.';
                }
                showNotification('error', errorMsg);
                submitBtn.prop('disabled', false).html('<i class="fa fa-plus"></i> Create Coupon');
            }
        });
    });

    // Export Coupons
    $(document).on('click', '#export-coupons-btn', function(e) {
        e.preventDefault();
        window.location.href = scDashboard.ajaxurl + '?action=export_coupons';
    });

    // Notification Helper Function
    function showNotification(type, message) {
        var alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        var notification = '<div class="alert ' + alertClass + ' alert-dismissible fade show" role="alert">' +
            message +
            '<button type="button" class="close" data-dismiss="alert" aria-label="Close">' +
            '<span aria-hidden="true">&times;</span>' +
            '</button>' +
            '</div>';

        $('#notification-area').html(notification);

        // Auto dismiss after 5 seconds
        setTimeout(function() {
            $('.alert').fadeOut();
        }, 5000);
    }

    // File upload preview
    $('#platform-logo-input').on('change', function() {
        var file = this.files[0];
        if (file) {
            var reader = new FileReader();
            reader.onload = function(e) {
                $('#logo-preview').attr('src', e.target.result).show();
            };
            reader.readAsDataURL(file);
        }
    });

})(jQuery);
