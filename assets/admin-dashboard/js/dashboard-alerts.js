/**
 * Dashboard Alert & Notification Helpers
 * SweetAlert2 wrapper functions for beautiful alerts
 *
 * @package sc_events
 * @version 1.0.0
 */

(function(window) {
    'use strict';

    // Ensure SweetAlert2 is loaded
    if (typeof Swal === 'undefined') {
        console.warn('SweetAlert2 not loaded. Alert functions will not work.');
        return;
    }

    /**
     * Show success alert
     */
    window.showSuccess = function(message, title) {
        title = title || 'Success!';
        Swal.fire({
            icon: 'success',
            title: title,
            text: message,
            confirmButtonColor: '#4CAF50',
            timer: 3000,
            timerProgressBar: true
        });
    };

    /**
     * Show error alert
     */
    window.showError = function(message, title) {
        title = title || 'Error!';
        Swal.fire({
            icon: 'error',
            title: title,
            text: message,
            confirmButtonColor: '#f44336'
        });
    };

    /**
     * Show warning alert
     */
    window.showWarning = function(message, title) {
        title = title || 'Warning!';
        Swal.fire({
            icon: 'warning',
            title: title,
            text: message,
            confirmButtonColor: '#ff9800'
        });
    };

    /**
     * Show info alert
     */
    window.showInfo = function(message, title) {
        title = title || 'Info';
        Swal.fire({
            icon: 'info',
            title: title,
            text: message,
            confirmButtonColor: '#2196F3'
        });
    };

    /**
     * Show confirmation dialog
     */
    window.showConfirm = function(message, title, confirmText, cancelText) {
        title = title || 'Are you sure?';
        confirmText = confirmText || 'Yes';
        cancelText = cancelText || 'No';

        return Swal.fire({
            title: title,
            text: message,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#4CAF50',
            cancelButtonColor: '#f44336',
            confirmButtonText: confirmText,
            cancelButtonText: cancelText
        });
    };

    /**
     * Show delete confirmation dialog
     */
    window.showDeleteConfirm = function(message, title) {
        message = message || "You won't be able to revert this!";
        title = title || 'Are you sure?';

        return Swal.fire({
            title: title,
            text: message,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#f44336',
            cancelButtonColor: '#757575',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel'
        });
    };

    /**
     * Show loading indicator
     */
    window.showLoading = function(message) {
        message = message || 'Please wait...';
        Swal.fire({
            title: message,
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: function() {
                Swal.showLoading();
            }
        });
    };

    /**
     * Close loading indicator
     */
    window.closeLoading = function() {
        Swal.close();
    };

})(window);
