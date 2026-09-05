/**
 * SweetAlert2 Helper Functions
 *
 * @package sc_events
 * @version 1.0.0
 */

/**
 * Initialize SweetAlert helpers
 */
export function initSwalHelpers() {
    // Make helper functions available globally
    window.showSuccess = showSuccess;
    window.showError = showError;
    window.showConfirm = showConfirm;
    window.showLoading = showLoading;
    window.closeLoading = closeLoading;
    window.getAjaxErrorMessage = getAjaxErrorMessage;
}

/**
 * Show success message
 * @param {string} message
 * @param {string} title
 * @returns {Promise}
 */
export function showSuccess(message, title = 'Success') {
    return Swal.fire({
        icon: 'success',
        title: title,
        text: message,
        timer: 2000,
        showConfirmButton: false
    });
}

/**
 * Show error message
 * @param {string} message
 * @param {string} title
 * @returns {Promise}
 */
export function showError(message, title = 'Error') {
    return Swal.fire({
        icon: 'error',
        title: title,
        text: message
    });
}

/**
 * Show confirmation dialog
 * @param {string} message
 * @param {string} title
 * @param {string} confirmText
 * @param {string} cancelText
 * @returns {Promise}
 */
export function showConfirm(message, title = 'Confirm', confirmText = 'Yes', cancelText = 'Cancel') {
    return Swal.fire({
        icon: 'question',
        title: title,
        text: message,
        showCancelButton: true,
        confirmButtonText: confirmText,
        cancelButtonText: cancelText,
        confirmButtonColor: getComputedStyle(document.documentElement).getPropertyValue('--sc-primary').trim() || '#D4AF37',
        cancelButtonColor: '#6c757d'
    });
}

/**
 * Show loading indicator
 * @param {string} message
 */
export function showLoading(message = 'Please wait...') {
    Swal.fire({
        title: message,
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
}

/**
 * Close loading indicator
 */
export function closeLoading() {
    Swal.close();
}

/**
 * Get user-friendly AJAX error message
 * @param {XMLHttpRequest} xhr
 * @param {string} status
 * @param {string} action
 * @returns {string}
 */
export function getAjaxErrorMessage(xhr, status, action = '') {
    if (status === 'timeout') {
        return 'The request timed out. Please try again.';
    }

    if (xhr.status === 0) {
        return 'Could not connect to server. Please check your internet connection.';
    }

    if (xhr.status === 403) {
        return 'Your session may have expired. Please refresh the page and try again.';
    }

    if (xhr.status === 404) {
        return 'The requested resource was not found.';
    }

    if (xhr.status === 500) {
        return 'A server error occurred. Please try again later.';
    }

    return 'An error occurred. Please try again or contact support.';
}
