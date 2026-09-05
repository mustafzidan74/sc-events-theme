/**
 * Shared Utilities
 * Common helper functions used across dashboard and frontend
 *
 * @package sc_events
 * @version 1.0.0
 */

(function(window) {
    'use strict';

    // Create namespace for shared utilities
    window.SCUtils = window.SCUtils || {};

    // ===========================================
    // AJAX ERROR MESSAGE HELPER
    // ===========================================

    /**
     * Get descriptive error message based on AJAX error
     * @param {Object} xhr - The XMLHttpRequest object
     * @param {string} status - The status text
     * @param {string} action - The action being performed (for context)
     * @returns {string} - Descriptive error message
     */
    SCUtils.getAjaxErrorMessage = function(xhr, status, action) {
        var actionName = action ? action.replace('sc_', '').replace(/_/g, ' ') : 'complete request';
        var errorMsg = 'Failed to ' + actionName + '. ';

        if (status === 'timeout') {
            errorMsg = 'The request timed out. Please check your connection and try again.';
        } else if (!navigator.onLine) {
            errorMsg = 'You appear to be offline. Please check your internet connection.';
        } else if (xhr.status === 0) {
            errorMsg = 'Could not connect to server. Please check your internet connection.';
        } else if (xhr.status === 400) {
            errorMsg += 'Invalid request. Please check your input and try again.';
        } else if (xhr.status === 401) {
            errorMsg = 'Your session has expired. Please refresh the page and login again.';
        } else if (xhr.status === 403) {
            errorMsg = 'You do not have permission to perform this action.';
        } else if (xhr.status === 404) {
            errorMsg = 'The requested resource was not found. Please refresh the page.';
        } else if (xhr.status === 408) {
            errorMsg = 'The request timed out. Please try again.';
        } else if (xhr.status === 413) {
            errorMsg = 'The file or data is too large. Please reduce the size and try again.';
        } else if (xhr.status === 429) {
            errorMsg = 'Too many requests. Please wait a moment and try again.';
        } else if (xhr.status >= 500 && xhr.status < 600) {
            errorMsg = 'Server error occurred. Please try again later or contact support.';
        } else {
            errorMsg += 'Please try again or contact support.';
        }

        return errorMsg;
    };

    // ===========================================
    // SWEETALERT2 WRAPPER FUNCTIONS
    // ===========================================

    /**
     * Check if SweetAlert2 is available
     */
    function isSwalAvailable() {
        if (typeof Swal === 'undefined') {
            console.warn('SweetAlert2 not loaded. Alert functions will fall back to native alerts.');
            return false;
        }
        return true;
    }

    /**
     * Show success alert
     * @param {string} message - The message to display
     * @param {string} title - Optional title
     * @returns {Promise}
     */
    SCUtils.showSuccess = function(message, title) {
        title = title || 'Success!';

        if (!isSwalAvailable()) {
            alert(title + '\n' + message);
            return Promise.resolve();
        }

        return Swal.fire({
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
     * @param {string} message - The message to display
     * @param {string} title - Optional title
     * @returns {Promise}
     */
    SCUtils.showError = function(message, title) {
        title = title || 'Error!';

        if (!isSwalAvailable()) {
            alert(title + '\n' + message);
            return Promise.resolve();
        }

        return Swal.fire({
            icon: 'error',
            title: title,
            text: message,
            confirmButtonColor: '#f44336'
        });
    };

    /**
     * Show warning alert
     * @param {string} message - The message to display
     * @param {string} title - Optional title
     * @returns {Promise}
     */
    SCUtils.showWarning = function(message, title) {
        title = title || 'Warning!';

        if (!isSwalAvailable()) {
            alert(title + '\n' + message);
            return Promise.resolve();
        }

        return Swal.fire({
            icon: 'warning',
            title: title,
            text: message,
            confirmButtonColor: '#ff9800'
        });
    };

    /**
     * Show info alert
     * @param {string} message - The message to display
     * @param {string} title - Optional title
     * @returns {Promise}
     */
    SCUtils.showInfo = function(message, title) {
        title = title || 'Info';

        if (!isSwalAvailable()) {
            alert(title + '\n' + message);
            return Promise.resolve();
        }

        return Swal.fire({
            icon: 'info',
            title: title,
            text: message,
            confirmButtonColor: '#2196F3'
        });
    };

    /**
     * Show confirmation dialog
     * @param {string} message - The message to display
     * @param {string} title - Optional title
     * @param {string} confirmText - Text for confirm button
     * @param {string} cancelText - Text for cancel button
     * @returns {Promise}
     */
    SCUtils.showConfirm = function(message, title, confirmText, cancelText) {
        title = title || 'Are you sure?';
        confirmText = confirmText || 'Yes';
        cancelText = cancelText || 'No';

        if (!isSwalAvailable()) {
            var result = confirm(title + '\n' + message);
            return Promise.resolve({ isConfirmed: result });
        }

        return Swal.fire({
            title: title,
            text: message,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#4CAF50',
            cancelButtonColor: '#6c757d',
            confirmButtonText: confirmText,
            cancelButtonText: cancelText
        });
    };

    /**
     * Show delete confirmation dialog
     * @param {string} message - The message to display
     * @param {string} title - Optional title
     * @returns {Promise}
     */
    SCUtils.showDeleteConfirm = function(message, title) {
        message = message || "You won't be able to revert this!";
        title = title || 'Are you sure?';

        if (!isSwalAvailable()) {
            var result = confirm(title + '\n' + message);
            return Promise.resolve({ isConfirmed: result });
        }

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
     * @param {string} message - The message to display
     */
    SCUtils.showLoading = function(message) {
        message = message || 'Please wait...';

        if (!isSwalAvailable()) {
            return;
        }

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
    SCUtils.closeLoading = function() {
        if (isSwalAvailable()) {
            Swal.close();
        }
    };

    /**
     * Show toast notification (non-blocking)
     * @param {string} type - success, error, warning, info
     * @param {string} message - The message to display
     */
    SCUtils.showToast = function(type, message) {
        if (!isSwalAvailable()) {
            console.log('[' + type.toUpperCase() + '] ' + message);
            return;
        }

        var Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: function(toast) {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            }
        });

        Toast.fire({
            icon: type,
            title: message
        });
    };

    // ===========================================
    // EXPOSE GLOBAL SHORTCUTS
    // ===========================================

    // Create global shortcuts for backward compatibility
    window.getAjaxErrorMessage = SCUtils.getAjaxErrorMessage;
    window.showSuccess = SCUtils.showSuccess;
    window.showError = SCUtils.showError;
    window.showWarning = SCUtils.showWarning;
    window.showInfo = SCUtils.showInfo;
    window.showConfirm = SCUtils.showConfirm;
    window.showDeleteConfirm = SCUtils.showDeleteConfirm;
    window.showLoading = SCUtils.showLoading;
    window.closeLoading = SCUtils.closeLoading;
    window.showToast = SCUtils.showToast;

})(window);
