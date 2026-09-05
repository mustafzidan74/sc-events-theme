/**
 * SC Events - Admin Dashboard Main Entry Point
 *
 * This file bundles all admin dashboard JavaScript modules
 *
 * @package sc_events
 * @version 1.0.0
 */

// Import CSS (will be extracted by Vite)
import '../css/admin-main.css';

// Import Sweetalert2
import Swal from 'sweetalert2';

// Make Swal available globally
window.Swal = Swal;

// Initialize on DOMContentLoaded
document.addEventListener('DOMContentLoaded', () => {
    console.log('SC Events Admin Dashboard loaded');
});
