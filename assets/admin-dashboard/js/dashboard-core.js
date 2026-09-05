/**
 * Dashboard Core Scripts
 * Handles error suppression, DOM fixes, and core functionality
 *
 * @package sc_events
 * @version 1.0.0
 */

(function() {
    'use strict';

    // ===========================================
    // 1. ERROR SUPPRESSION FOR MAINSCRIPTS
    // ===========================================
    var suppressErrors = true;
    var originalError = window.onerror;

    window.onerror = function(msg, url, line, col, error) {
        if (suppressErrors && url && url.indexOf('mainscripts.bundle.js') > -1) {
            console.warn('Mainscripts error suppressed:', msg);
            return true;
        }
        if (originalError) {
            return originalError.apply(this, arguments);
        }
        return false;
    };

    // ===========================================
    // 2. SAFE ADDEVENTLISTENER WRAPPER
    // ===========================================
    if (typeof Element !== 'undefined' && Element.prototype.addEventListener) {
        var originalAddEventListener = Element.prototype.addEventListener;

        Element.prototype.addEventListener = function(type, listener, options) {
            if (!this || typeof listener !== 'function') {
                return;
            }
            try {
                return originalAddEventListener.call(this, type, listener, options);
            } catch (e) {
                console.warn('addEventListener error prevented:', e.message);
            }
        };
    }

    // ===========================================
    // 3. CREATE MISSING DOM ELEMENTS
    // ===========================================
    function createMissingElements() {
        // Ensure wrapper exists
        if (!document.getElementById('wrapper')) {
            var wrapper = document.querySelector('.theme-cyan') || document.querySelector('[class*="theme-"]');
            if (wrapper && !wrapper.id) {
                wrapper.id = 'wrapper';
            }
        }

        // Ensure navbar-right exists
        if (!document.querySelector('.navbar-right')) {
            var navbar = document.querySelector('.navbar .container-fluid');
            if (navbar && !navbar.querySelector('.navbar-right')) {
                var navRight = document.createElement('div');
                navRight.className = 'navbar-right';
                navbar.appendChild(navRight);
            }
        }
    }

    // Run immediately and on DOMContentLoaded
    createMissingElements();

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', createMissingElements);
    }

    // Re-enable errors after mainscripts loads
    setTimeout(function() {
        suppressErrors = false;
    }, 2000);

})();
