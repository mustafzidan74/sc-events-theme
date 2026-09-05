/**
 * SC Events - Frontend Main Entry Point
 *
 * This file bundles all frontend JavaScript modules
 *
 * @package sc_events
 * @version 1.0.0
 */

// Import CSS (will be extracted by Vite)
import '../css/frontend-main.css';

// Import Sweetalert2
import Swal from 'sweetalert2';

// Make Swal available globally
window.Swal = Swal;

// Import modules
import { initCountdown } from './modules/countdown.js';
import { initSwalHelpers } from './modules/swal-helpers.js';

// Initialize on DOMContentLoaded
document.addEventListener('DOMContentLoaded', () => {
    // Initialize countdown timers
    initCountdown();

    // Initialize SweetAlert helpers
    initSwalHelpers();

    console.log('SC Events Frontend loaded');
});
