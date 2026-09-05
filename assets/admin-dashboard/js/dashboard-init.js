/**
 * Dashboard Initialization Scripts
 * Page loader, tooltips, popovers, and common UI interactions
 *
 * @package sc_events
 * @version 1.0.0
 */

(function($) {
    'use strict';

    // Wait for DOM ready
    $(document).ready(function() {

        // ===========================================
        // 1. PAGE LOADER
        // ===========================================
        setTimeout(function() {
            var $loader = $('.page-loader-wrapper');
            $loader.addClass('loaded');
            // Remove from DOM after transition completes (accessibility)
            setTimeout(function() {
                $loader.remove();
            }, 600);
        }, 500);

        // ===========================================
        // 2. BOOTSTRAP COMPONENTS
        // ===========================================

        // Initialize tooltips
        if (typeof $.fn.tooltip !== 'undefined') {
            $('[data-toggle="tooltip"]').tooltip();
        }

        // Initialize popovers
        if (typeof $.fn.popover !== 'undefined') {
            $('[data-toggle="popover"]').popover();
        }

        // ===========================================
        // 3. THEME SWITCHER
        // ===========================================
        $('.choose-skin li').on('click', function() {
            var $body = $('body');
            var $this = $(this);

            var existTheme = $('.choose-skin li.active').data('theme');
            $('.choose-skin li').removeClass('active');
            $body.removeClass('theme-' + existTheme);
            $this.addClass('active');
            $body.addClass('theme-' + $this.data('theme'));
        });

        // ===========================================
        // 4. LOGOUT HANDLER
        // ===========================================
        $('.logout-link').on('click', function(e) {
            e.preventDefault();

            if (typeof showConfirm === 'function') {
                showConfirm('Are you sure you want to logout?', 'Logout', 'Yes, Logout', 'Cancel').then(function(result) {
                    if (result.isConfirmed) {
                        performLogout();
                    }
                });
            } else {
                if (confirm('Are you sure you want to logout?')) {
                    performLogout();
                }
            }
        });

        function performLogout() {
            if (typeof showLoading === 'function') {
                showLoading('Logging out...');
            }

            $.ajax({
                url: scDashboard.ajaxurl,
                type: 'POST',
                data: {
                    action: 'event_manager_logout',
                    nonce: scDashboard.logoutNonce
                },
                success: function(response) {
                    if (typeof closeLoading === 'function') {
                        closeLoading();
                    }
                    window.location.href = scDashboard.dashboardUrl;
                },
                error: function(xhr, status, error) {
                    if (typeof closeLoading === 'function') {
                        closeLoading();
                    }
                    var errorMsg = 'Failed to logout. ';
                    if (status === 'timeout') {
                        errorMsg += 'The request timed out. Please try again.';
                    } else if (xhr.status === 0) {
                        errorMsg += 'Could not connect to server. Please check your internet connection.';
                    } else {
                        errorMsg += 'Please refresh the page and try again.';
                    }
                    if (typeof showError === 'function') {
                        showError(errorMsg);
                    } else {
                        alert(errorMsg);
                    }
                }
            });
        }

        // ===========================================
        // 5. BOOTSTRAP MODAL FIX
        // ===========================================
        if (typeof $.fn.modal === 'undefined') {
            console.warn('Bootstrap modal not found, loading separately...');
            var script = document.createElement('script');
            script.src = scDashboard.assetsUrl + 'vendor/bootstrap/js/bootstrap.bundle.min.js';
            document.head.appendChild(script);
        }

    });

})(jQuery);
