/**
 * Public Frontend Scripts
 *
 * @package sc_events
 * @version 1.0.1
 *
 * Note: Helper functions (showSuccess, showError, showConfirm, showLoading,
 * closeLoading, getAjaxErrorMessage) are now provided by shared-utilities.js
 */

(function($) {
    'use strict';

    // ===========================================
    // AJAX HELPER
    // ===========================================

    window.scAjax = function(action, data, callback) {
        data = data || {};
        data.action = action;
        data.nonce = scPublic.nonce;

        $.ajax({
            url: scPublic.ajaxurl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (callback) callback(response);
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                var errorMsg = getAjaxErrorMessage(xhr, status, action);
                if (callback) callback({ success: false, data: { message: errorMsg } });
            }
        });
    };

    // ===========================================
    // LOGIN FORM HANDLER
    // ===========================================

    $(document).on('submit', '#login-form', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');
        var originalText = $btn.html();

        $btn.prop('disabled', true).html('<span class="login-spinner"></span> Logging in...');

        scAjax('sc_public_login', {
            email: $form.find('[name="email"]').val(),
            password: $form.find('[name="password"]').val(),
            remember: $form.find('[name="remember"]').is(':checked') ? 1 : 0
        }, function(response) {
            $btn.prop('disabled', false).html(originalText);

            if (response.success) {
                showSuccess(response.data.message).then(function() {
                    window.location.href = response.data.redirect || scPublic.homeUrl;
                });
            } else {
                showError(response.data.message);
            }
        });
    });

    // ===========================================
    // REGISTER FORM HANDLER
    // ===========================================

    $(document).on('submit', '#register-form', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');
        var originalText = $btn.html();

        // Validate passwords match
        var password = $form.find('[name="password"]').val();
        var confirmPassword = $form.find('[name="confirm_password"]').val();

        if (password !== confirmPassword) {
            showError('Passwords do not match');
            return;
        }

        // Validate terms agreement
        if (!$form.find('[name="terms"]').is(':checked')) {
            showError('Please agree to the Terms & Conditions to continue');
            return;
        }

        $btn.prop('disabled', true).html('<span class="login-spinner"></span> Registering...');

        scAjax('sc_public_register', {
            name: $form.find('[name="name"]').val(),
            email: $form.find('[name="email"]').val(),
            phone: $form.find('[name="phone"]').val(),
            password: password
        }, function(response) {
            $btn.prop('disabled', false).html(originalText);

            if (response.success) {
                showSuccess(response.data.message).then(function() {
                    window.location.href = response.data.redirect || scPublic.homeUrl;
                });
            } else {
                showError(response.data.message);
            }
        });
    });

    // ===========================================
    // FORGOT PASSWORD FORM HANDLER
    // ===========================================

    $(document).on('submit', '#forgot-password-form', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');
        var originalText = $btn.html();
        var email = $form.find('[name="email"]').val();

        $btn.prop('disabled', true).html('<span class="login-spinner"></span> Sending...');

        scAjax('sc_forgot_password', {
            email: email
        }, function(response) {
            $btn.prop('disabled', false).html(originalText);

            if (response.success) {
                showSuccess(response.data.message || 'Password reset link has been sent to your email');
                $form[0].reset();
            } else {
                showError(response.data.message || 'Could not send reset link. Please try again.');
            }
        });
    });

    // ===========================================
    // TICKET PURCHASE HANDLER
    // ===========================================

    // Extra Fields Global Variables
    var eventExtraFields = [];
    var pendingRegistration = null;
    var eventData = {};

    // Load Event Data
    try {
        var eventDataText = $('#event-data').text();
        if (eventDataText) {
            eventData = JSON.parse(eventDataText);
        }
    } catch(e) {
        console.error('Failed to parse event data:', e);
    }

    // Load Extra Fields Data
    try {
        var extraFieldsData = $('#event-extra-fields-data').text();
        if (extraFieldsData) {
            eventExtraFields = JSON.parse(extraFieldsData);
        }
    } catch(e) {
        console.error('Failed to parse extra fields data:', e);
    }

    // Check if user is registered when email is entered
    var checkRegistrationTimeout;
    $(document).on('blur', 'input[name="user_email"], input[type="email"]', function() {
        var email = $(this).val().trim();

        // Skip if already registered on page load or invalid email
        if (eventData.is_registered || !email || email.indexOf('@') === -1) {
            return;
        }

        clearTimeout(checkRegistrationTimeout);
        checkRegistrationTimeout = setTimeout(function() {
            $.ajax({
                url: scPublic.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sc_check_registration',
                    nonce: scPublic.nonce,
                    email: email,
                    event_id: eventData.event_id
                }
            }).done(function(response) {
                if (response.success && response.data.is_registered) {
                    // User is already registered - show message
                    var message = '<div class="alert alert-info registration-notice" style="margin: 20px 0; padding: 20px; border-radius: 10px; background: linear-gradient(135deg, var(--primary-color), var(--primary-color2)); color: white;">';
                    message += '<h5 style="margin: 0 0 10px 0;"><i class="fa-solid fa-circle-check"></i> You are already registered!</h5>';
                    message += '<p style="margin: 0 0 10px 0;">You registered for this event on <strong>' + response.data.registration_date + '</strong></p>';
                    if (response.data.ticket_name) {
                        message += '<p style="margin: 0 0 10px 0;">Ticket: <strong>' + response.data.ticket_name + '</strong></p>';
                    }
                    if (response.data.ticket_id) {
                        message += '<p style="margin: 0 0 10px 0;">Ticket ID: <code style="background: rgba(255,255,255,0.2); padding: 3px 8px; border-radius: 4px;">' + response.data.ticket_id + '</code></p>';
                    }
                    message += '<p style="margin: 10px 0 0 0;"><a href="' + scPublic.homeUrl + '/my-account/" style="color: white; text-decoration: underline;">View Your Tickets</a></p>';
                    message += '</div>';

                    // Hide ticket forms and show message
                    $('.tickets-box').hide();
                    if ($('.registration-notice').length === 0) {
                        $('.event-booking-sidebar').prepend(message);
                    }
                }
            });
        }, 500);
    });

    // Legacy aliases (replaced by buildExtraFieldsInto / collectExtraFields)
    function buildExtraFieldsForm() { return buildExtraFieldsInto($('#extra-fields-container')); }
    function collectExtraFieldsData() { return collectExtraFields(); }

    // Helper function to escape HTML
    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // ===========================================
    // CHECKOUT: Build extra fields into a container
    // ===========================================
    function buildExtraFieldsInto($container) {
        $container.empty();

        if (!eventExtraFields || eventExtraFields.length === 0) return false;

        var hasFields = false;
        eventExtraFields.forEach(function(field) {
            if (!field.show_attendee_form) return;
            hasFields = true;

            var fid = field.id;
            var html = '<div class="sc-checkout-field-group">';
            html += '<label class="sc-checkout-field-label">' + escapeHtml(field.label);
            if (field.required) html += ' <span class="required-star">*</span>';
            html += '</label>';

            var opts = field.options || field.field_options || [];
            if (Array.isArray(opts)) {
                opts = opts.map(function(o) { return (typeof o === 'object') ? (o.value || o.label || o) : o; });
            }

            if (field.field_type === 'textarea') {
                html += '<textarea class="sc-input checkout-extra-field" data-field-id="' + fid + '" data-label="' + escapeHtml(field.label) + '" rows="3"' + (field.required ? ' required' : '') + (field.placeholder ? ' placeholder="' + escapeHtml(field.placeholder) + '"' : '') + '></textarea>';
            } else if (field.field_type === 'select' || field.field_type === 'dropdown') {
                html += '<select class="sc-input checkout-extra-field" data-field-id="' + fid + '" data-label="' + escapeHtml(field.label) + '"' + (field.required ? ' required' : '') + '>';
                html += '<option value="">Select...</option>';
                opts.forEach(function(o) { html += '<option value="' + escapeHtml(o) + '">' + escapeHtml(o) + '</option>'; });
                html += '</select>';
            } else if (field.field_type === 'radio') {
                opts.forEach(function(o, i) {
                    html += '<div class="form-check"><input class="form-check-input checkout-extra-field" type="radio" name="field_' + fid + '" data-field-id="' + fid + '" data-label="' + escapeHtml(field.label) + '" value="' + escapeHtml(o) + '" id="cf_r_' + fid + '_' + i + '"' + (field.required && i === 0 ? ' required' : '') + '>';
                    html += '<label class="form-check-label" for="cf_r_' + fid + '_' + i + '">' + escapeHtml(o) + '</label></div>';
                });
            } else if (field.field_type === 'checkbox') {
                opts.forEach(function(o, i) {
                    html += '<div class="form-check"><input class="form-check-input checkout-extra-field" type="checkbox" data-field-id="' + fid + '" data-label="' + escapeHtml(field.label) + '" value="' + escapeHtml(o) + '" id="cf_c_' + fid + '_' + i + '">';
                    html += '<label class="form-check-label" for="cf_c_' + fid + '_' + i + '">' + escapeHtml(o) + '</label></div>';
                });
            } else {
                var inputType = field.field_type || 'text';
                html += '<input type="' + inputType + '" class="sc-input checkout-extra-field" data-field-id="' + fid + '" data-label="' + escapeHtml(field.label) + '"' + (field.required ? ' required' : '') + (field.placeholder ? ' placeholder="' + escapeHtml(field.placeholder) + '"' : '') + '>';
            }

            html += '</div>';
            $container.append(html);
        });

        return hasFields;
    }

    // Collect extra fields data from any container with .checkout-extra-field
    function collectExtraFields() {
        var data = [];
        if (!eventExtraFields) return data;

        eventExtraFields.forEach(function(field) {
            if (!field.show_attendee_form) return;
            var el = $('.checkout-extra-field[data-field-id="' + field.id + '"]');
            var value = '';

            if (field.field_type === 'checkbox') {
                var vals = [];
                el.filter(':checked').each(function() { vals.push($(this).val()); });
                value = vals.join(', ');
            } else if (field.field_type === 'radio') {
                value = el.filter(':checked').val() || '';
            } else {
                value = el.val() || '';
            }

            data.push({ label: field.label, value: value });
        });

        return data;
    }

    // Validate required extra fields in a container
    function validateExtraFields($container) {
        var valid = true;
        $container.find('.checkout-extra-field[required]').each(function() {
            var $f = $(this);
            if ($f.is(':radio')) {
                var name = $f.attr('name');
                if (!$container.find('input[name="' + name + '"]:checked').length) {
                    valid = false;
                    $f.closest('.form-check').addClass('is-invalid');
                }
            } else if (!$f.val() || !$f.val().trim()) {
                valid = false;
                $f.addClass('is-invalid').css('border-color', '#ef4444');
            }
        });
        if (!valid) {
            showError('Please fill all required fields.');
        }
        return valid;
    }

    // Checkout modal state
    var checkoutState = {
        ticketPrice: 0,
        quantity: 1,
        discount: 0,
        couponCode: '',
        discountType: '',
        discountValue: 0
    };

    // Currency formatter
    function scFormatPrice(amount) {
        amount = parseFloat(amount);
        if (amount <= 0) return scPublic.i18n.free || 'FREE';

        var c = scPublic.currency || { symbol: 'EGP', position: 'after', decimal_places: 0, thousand_separator: ',', decimal_separator: '.' };
        var formatted = amount.toFixed(c.decimal_places || 0);

        // Add thousand separator
        var parts = formatted.split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, c.thousand_separator || ',');
        formatted = parts.join(c.decimal_separator || '.');

        if (c.position === 'before') {
            return c.symbol + ' ' + formatted;
        }
        return formatted + ' ' + c.symbol;
    }

    function updateCheckoutTotal() {
        var subtotal = checkoutState.ticketPrice * checkoutState.quantity;
        var disc = 0;

        if (checkoutState.couponCode && checkoutState.discountValue > 0) {
            if (checkoutState.discountType === 'percentage') {
                disc = (subtotal * checkoutState.discountValue) / 100;
            } else {
                disc = checkoutState.discountValue;
            }
        }

        checkoutState.discount = Math.min(disc, subtotal);
        var total = Math.max(0, subtotal - checkoutState.discount);

        // Update display
        var priceText = checkoutState.ticketPrice > 0 ? scFormatPrice(checkoutState.ticketPrice) : (scPublic.i18n.free || 'FREE');
        if (checkoutState.quantity > 1) {
            priceText = scFormatPrice(checkoutState.ticketPrice) + ' × ' + checkoutState.quantity;
        }
        $('#checkout-ticket-price').text(priceText).data('price', checkoutState.ticketPrice);

        if (checkoutState.discount > 0) {
            $('#checkout-discount-row').show();
            $('#checkout-discount').text('-' + scFormatPrice(checkoutState.discount));
        } else {
            $('#checkout-discount-row').hide();
        }

        var totalText = total > 0 ? scFormatPrice(total) : (scPublic.i18n.free || 'FREE');
        $('#final-price').text(totalText);

        // Update button text
        if (total === 0) {
            $('#checkout-btn-text').text(scPublic.i18n.registerNow || 'Register Now');
            $('#checkout-mode').val('free');
        } else {
            $('#checkout-btn-text').text(scPublic.i18n.proceedToPayment || 'Proceed to Payment');
            $('#checkout-mode').val('paid');
        }
    }

    // ===========================================
    // FREE TICKET REGISTRATION
    // ===========================================
    $(document).on('click', '.btn-register-free', function(e) {
        e.preventDefault();

        if (!scPublic.isLoggedIn) {
            showConfirm(scPublic.i18n.loginRequired, 'Login Required', 'Login', 'Cancel').then(function(result) {
                if (result.isConfirmed) {
                    window.location.href = scPublic.homeUrl + 'login/?redirect=' + encodeURIComponent(window.location.href);
                }
            });
            return;
        }

        var $btn = $(this);
        var eventId = $btn.data('event-id');
        var ticketId = $btn.data('ticket-id');
        var workshopId = $btn.data('workshop-id') || 0;

        // Check if extra fields exist
        var hasExtraFields = eventExtraFields && eventExtraFields.filter(function(f) { return f.show_attendee_form; }).length > 0;

        if (hasExtraFields) {
            pendingRegistration = { type: 'free', eventId: eventId, ticketId: ticketId, workshopId: workshopId };
            buildExtraFieldsInto($('#extra-fields-container'));
            var modal = new bootstrap.Modal(document.getElementById('extraFieldsModal'));
            modal.show();
            return;
        }

        processFreeRegistration(eventId, ticketId, [], workshopId);
    });

    // Submit Extra Fields Form (for free tickets)
    $(document).on('submit', '#extra-fields-form', function(e) {
        e.preventDefault();

        var $container = $('#extra-fields-container');
        if (!validateExtraFields($container)) return;

        var extraFieldsData = collectExtraFields();
        var modal = bootstrap.Modal.getInstance(document.getElementById('extraFieldsModal'));
        if (modal) modal.hide();

        if (pendingRegistration) {
            if (pendingRegistration.type === 'free') {
                processFreeRegistration(pendingRegistration.eventId, pendingRegistration.ticketId, extraFieldsData, pendingRegistration.workshopId || 0);
            } else if (pendingRegistration.type === 'coupon') {
                processCouponRegistration(pendingRegistration.eventId, pendingRegistration.couponCode, extraFieldsData, pendingRegistration.workshopId || 0, pendingRegistration.ticketId || 0);
            }
            pendingRegistration = null;
        }
    });

    function processFreeRegistration(eventId, ticketId, extraFieldsData, workshopId) {
        showLoading('Registering...');

        var payload = {
            event_id: eventId,
            ticket_id: ticketId,
            extra_fields: extraFieldsData
        };
        if (workshopId) payload.workshop_id = workshopId;

        scAjax('sc_register_free_ticket', payload, function(response) {
            closeLoading();
            if (response.success) {
                var html = response.data.message;
                if (response.data.session_count > 0) {
                    html += '<br><small style="opacity:0.8;margin-top:6px;display:inline-block"><i class="fa-solid fa-chalkboard-user"></i> ' + response.data.session_count + ' sessions registered</small>';
                }
                Swal.fire({ icon: 'success', title: 'Success', html: html, timer: 3000, showConfirmButton: false }).then(function() {
                    if (response.data.redirect) window.location.href = response.data.redirect;
                });
            } else {
                showError(response.data.message);
            }
        });
    }

    function processCouponRegistration(eventId, couponCode, extraFieldsData, workshopId, ticketId) {
        showLoading('Registering with coupon...');

        var payload = {
            event_id: eventId,
            coupon_code: couponCode,
            extra_fields: extraFieldsData
        };
        if (workshopId) payload.workshop_id = workshopId;
        if (ticketId) payload.ticket_id = ticketId;

        scAjax('sc_register_with_coupon', payload, function(response) {
            closeLoading();
            if (response.success) {
                var html = response.data.message;
                if (response.data.session_count > 0) {
                    html += '<br><small style="opacity:0.8;margin-top:6px;display:inline-block"><i class="fa-solid fa-chalkboard-user"></i> ' + response.data.session_count + ' sessions registered</small>';
                }
                Swal.fire({ icon: 'success', title: 'Success', html: html, timer: 3000, showConfirmButton: false }).then(function() {
                    if (response.data.redirect) window.location.href = response.data.redirect;
                });
            } else {
                showError(response.data.message);
            }
        });
    }

    // ===========================================
    // COUPON-REQUIRED TICKET REGISTRATION
    // ===========================================
    var couponRegState = { validated: false, couponCode: '' };

    $(document).on('click', '.btn-register-coupon', function(e) {
        e.preventDefault();

        if (!scPublic.isLoggedIn) {
            showConfirm(scPublic.i18n.loginRequired, 'Login Required', 'Login', 'Cancel').then(function(result) {
                if (result.isConfirmed) {
                    window.location.href = scPublic.homeUrl + 'login/?redirect=' + encodeURIComponent(window.location.href);
                }
            });
            return;
        }

        var $btn = $(this);
        var eventId = $btn.data('event-id');
        var ticketId = $btn.data('ticket-id');
        var ticketName = $btn.data('ticket-name');
        var workshopId = $btn.data('workshop-id') || 0;

        // Reset state
        couponRegState = { validated: false, couponCode: '', workshopId: workshopId };
        $('#coupon-reg-event-id').val(eventId);
        $('#coupon-reg-ticket-id').val(ticketId);
        $('#coupon-reg-code').val('');
        $('#coupon-reg-result').hide().empty();
        $('#coupon-reg-ticket-info').hide();
        $('#coupon-reg-ticket-name').text(ticketName);
        $('#coupon-reg-submit-btn').prop('disabled', true);

        // Build extra fields
        buildExtraFieldsInto($('#coupon-reg-extra-fields'));

        // Open modal
        var modal = new bootstrap.Modal(document.getElementById('couponRegisterModal'));
        modal.show();
    });

    // Verify coupon button inside coupon registration modal
    $(document).on('click', '.btn-validate-coupon-reg', function(e) {
        e.preventDefault();

        var couponCode = $('#coupon-reg-code').val().trim();
        var eventId = $('#coupon-reg-event-id').val();
        var $result = $('#coupon-reg-result');

        if (!couponCode) {
            $result.show().removeClass('success').addClass('error').html('<i class="fa-solid fa-circle-xmark"></i> ' + (scPublic.i18n.enterCoupon || 'Please enter a coupon or serial number'));
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i>');

        scAjax('sc_validate_coupon', {
            event_id: eventId,
            coupon_code: couponCode
        }, function(response) {
            $btn.prop('disabled', false).html(scPublic.i18n.verify || 'Verify');

            if (response.success) {
                // For coupon registration, must be 100% discount
                if (!response.data.is_free) {
                    couponRegState.validated = false;
                    couponRegState.couponCode = '';
                    $result.show().removeClass('success').addClass('error').html(
                        '<i class="fa-solid fa-circle-xmark"></i> ' + (scPublic.i18n.couponNotFull || 'This coupon does not provide full access. A 100% discount coupon is required.')
                    );
                    $('#coupon-reg-ticket-info').hide();
                    $('#coupon-reg-submit-btn').prop('disabled', true);
                    return;
                }

                couponRegState.validated = true;
                couponRegState.couponCode = couponCode;

                $result.show().removeClass('error').addClass('success').html(
                    '<i class="fa-solid fa-circle-check"></i> ' + couponCode.toUpperCase() + ' — ' + (response.data.discount_text || 'Valid')
                );

                // Show ticket info and enable submit
                $('#coupon-reg-ticket-info').show();
                $('#coupon-reg-submit-btn').prop('disabled', false);
            } else {
                couponRegState.validated = false;
                couponRegState.couponCode = '';

                $result.show().removeClass('success').addClass('error').html(
                    '<i class="fa-solid fa-circle-xmark"></i> ' + (response.data.message || 'Invalid coupon')
                );

                $('#coupon-reg-ticket-info').hide();
                $('#coupon-reg-submit-btn').prop('disabled', true);
            }
        });
    });

    // Allow Enter key on coupon input to trigger verify
    $(document).on('keypress', '#coupon-reg-code', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            $('.btn-validate-coupon-reg').trigger('click');
        }
    });

    // Reset validation when coupon code changes
    $(document).on('input', '#coupon-reg-code', function() {
        if (couponRegState.validated) {
            couponRegState.validated = false;
            couponRegState.couponCode = '';
            $('#coupon-reg-result').hide();
            $('#coupon-reg-ticket-info').hide();
            $('#coupon-reg-submit-btn').prop('disabled', true);
        }
    });

    // Submit coupon registration form
    $(document).on('submit', '#coupon-register-form', function(e) {
        e.preventDefault();

        if (!couponRegState.validated) {
            showError(scPublic.i18n.verifyCouponFirst || 'Please verify your coupon first');
            return;
        }

        // Validate extra fields
        var $efContainer = $('#coupon-reg-extra-fields');
        if ($efContainer.find('.checkout-extra-field').length > 0) {
            if (!validateExtraFields($efContainer)) return;
        }

        var extraFieldsData = collectExtraFields();
        var eventId = $('#coupon-reg-event-id').val();
        var ticketId = $('#coupon-reg-ticket-id').val();

        // Close modal
        var modal = bootstrap.Modal.getInstance(document.getElementById('couponRegisterModal'));
        if (modal) modal.hide();

        // Register with coupon
        showLoading(scPublic.i18n.registering || 'Registering...');

        var couponPayload = {
            event_id: eventId,
            ticket_id: ticketId,
            coupon_code: couponRegState.couponCode,
            extra_fields: extraFieldsData
        };
        if (couponRegState.workshopId) couponPayload.workshop_id = couponRegState.workshopId;

        scAjax('sc_register_with_coupon', couponPayload, function(response) {
            closeLoading();
            if (response.success) {
                showSuccess(response.data.message).then(function() {
                    if (response.data.redirect) window.location.href = response.data.redirect;
                });
            } else {
                showError(response.data.message);
            }
        });
    });

    // ===========================================
    // PAID TICKET - Open Checkout Modal
    // ===========================================
    $(document).on('click', '.btn-buy-ticket', function(e) {
        e.preventDefault();

        if (!scPublic.isLoggedIn) {
            showConfirm(scPublic.i18n.loginRequired, 'Login Required', 'Login', 'Cancel').then(function(result) {
                if (result.isConfirmed) {
                    window.location.href = scPublic.homeUrl + 'login/?redirect=' + encodeURIComponent(window.location.href);
                }
            });
            return;
        }

        var $btn = $(this);
        var eventId = $btn.data('event-id');
        var ticketId = $btn.data('ticket-id');
        var ticketName = $btn.data('ticket-name');
        var ticketPrice = parseFloat($btn.data('ticket-price')) || 0;
        var minQty = parseInt($btn.data('min-qty')) || 1;
        var maxQty = parseInt($btn.data('max-qty')) || 10;
        var workshopId = $btn.data('workshop-id') || 0;

        // Reset checkout state
        checkoutState.ticketPrice = ticketPrice;
        checkoutState.quantity = 1;
        checkoutState.discount = 0;
        checkoutState.couponCode = '';
        checkoutState.discountType = '';
        checkoutState.discountValue = 0;
        checkoutState.workshopId = workshopId;

        // Populate modal
        $('#checkout-event-id').val(eventId);
        $('#checkout-ticket-id').val(ticketId);
        // Make sure a workshop_id input exists in the form
        if ($('#checkout-workshop-id').length === 0) {
            $('#checkout-form').append('<input type="hidden" id="checkout-workshop-id" name="workshop_id" value="">');
        }
        $('#checkout-workshop-id').val(workshopId || '');
        $('#checkout-ticket-name').text(ticketName);
        $('#applied-coupon').val('');
        $('#modal-coupon-code').val('');
        $('#coupon-result').hide().empty();

        // Set qty limits
        var $qtyInput = $('#checkout-modal .qty-input');
        $qtyInput.val(1).attr('min', minQty).attr('max', maxQty);

        // Build extra fields in checkout modal
        buildExtraFieldsInto($('#checkout-extra-fields-container'));

        // Update total display
        updateCheckoutTotal();

        // Open modal
        var modal = new bootstrap.Modal(document.getElementById('checkout-modal'));
        modal.show();
    });

    // ===========================================
    // COUPON - Outside modal (content.php coupon area)
    // ===========================================
    $(document).on('click', '.btn-apply-coupon', function(e) {
        e.preventDefault();

        if (!scPublic.isLoggedIn) {
            showConfirm(scPublic.i18n.loginRequired || 'Please login to use coupons', 'Login Required', 'Login', 'Cancel').then(function(result) {
                if (result.isConfirmed) {
                    window.location.href = scPublic.homeUrl + 'login/?redirect=' + encodeURIComponent(window.location.href);
                }
            });
            return;
        }

        var $btn = $(this);
        var eventId = $btn.data('event-id');
        var couponCode = $('#coupon-code').val().trim();

        if (!couponCode) { showError('Please enter a coupon code'); return; }

        showLoading('Validating coupon...');

        scAjax('sc_validate_coupon', {
            event_id: eventId,
            coupon_code: couponCode
        }, function(response) {
            closeLoading();
            if (response.success) {
                var d = response.data;
                if (d.is_free) {
                    var hasExtraFields = eventExtraFields && eventExtraFields.filter(function(f) { return f.show_attendee_form; }).length > 0;
                    if (hasExtraFields) {
                        pendingRegistration = { type: 'coupon', eventId: eventId, couponCode: couponCode };
                        buildExtraFieldsInto($('#extra-fields-container'));
                        var modal = new bootstrap.Modal(document.getElementById('extraFieldsModal'));
                        modal.show();
                    } else {
                        showConfirm('This coupon gives you free access! Register now?', 'Free Access', 'Register', 'Cancel').then(function(result) {
                            if (result.isConfirmed) processCouponRegistration(eventId, couponCode, []);
                        });
                    }
                } else {
                    showError('Only 100% discount coupons are allowed for free registration. This coupon provides ' + d.discount_text + ' discount.');
                }
            } else {
                showError(response.data.message);
            }
        });
    });

    // ===========================================
    // COUPON - Inside checkout modal
    // ===========================================
    $(document).on('click', '.btn-apply-modal-coupon', function(e) {
        e.preventDefault();

        var couponCode = $('#modal-coupon-code').val().trim();
        var eventId = $('#checkout-event-id').val();
        var $result = $('#coupon-result');

        if (!couponCode) {
            $result.show().removeClass('success').addClass('error').html('<i class="fa-solid fa-circle-xmark"></i> Please enter a coupon code');
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i>');

        scAjax('sc_validate_coupon', {
            event_id: eventId,
            coupon_code: couponCode
        }, function(response) {
            $btn.prop('disabled', false).html(scPublic.i18n.apply || 'Apply');

            if (response.success) {
                var d = response.data;
                checkoutState.couponCode = couponCode;
                checkoutState.discountType = d.discount_type;
                checkoutState.discountValue = parseFloat(d.discount_value);
                $('#applied-coupon').val(couponCode);

                $result.show().removeClass('error').addClass('success').html('<i class="fa-solid fa-circle-check"></i> ' + couponCode.toUpperCase() + ' applied: ' + d.discount_text + ' off');
                updateCheckoutTotal();
            } else {
                checkoutState.couponCode = '';
                checkoutState.discountType = '';
                checkoutState.discountValue = 0;
                $('#applied-coupon').val('');

                $result.show().removeClass('success').addClass('error').html('<i class="fa-solid fa-circle-xmark"></i> ' + (response.data.message || 'Invalid coupon'));
                updateCheckoutTotal();
            }
        });
    });

    // ===========================================
    // CHECKOUT FORM SUBMIT
    // ===========================================
    $(document).on('submit', '#checkout-form', function(e) {
        e.preventDefault();

        // Validate extra fields
        var $efContainer = $('#checkout-extra-fields-container');
        if ($efContainer.find('.checkout-extra-field').length > 0) {
            if (!validateExtraFields($efContainer)) return;
        }

        var $form = $(this);
        var $btn = $('#checkout-submit-btn');
        var originalHtml = $btn.html();

        $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Processing...');

        var formData = {};
        $form.serializeArray().forEach(function(item) {
            formData[item.name] = item.value;
        });

        formData.extra_fields = collectExtraFields();

        scAjax('sc_process_checkout', formData, function(response) {
            $btn.prop('disabled', false).html(originalHtml);

            if (response.success) {
                $('#checkout-modal').modal('hide');
                if (response.data.redirect) {
                    showLoading('Redirecting...');
                    window.location.href = response.data.redirect;
                } else {
                    showSuccess(response.data.message).then(function() {
                        if (response.data.redirect) window.location.href = response.data.redirect;
                    });
                }
            } else {
                showError(response.data.message);
            }
        });
    });

    // ===========================================
    // PROFILE UPDATE HANDLER
    // ===========================================

    $(document).on('submit', '#profile-form', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');
        var originalText = $btn.html();

        // Validate password if provided
        var newPassword = $form.find('[name="new_password"]').val();
        var confirmPassword = $form.find('[name="confirm_password"]').val();

        if (newPassword && newPassword !== confirmPassword) {
            showError('Passwords do not match');
            return;
        }

        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

        scAjax('sc_update_profile', {
            name: $form.find('[name="name"]').val(),
            phone: $form.find('[name="phone"]').val(),
            current_password: $form.find('[name="current_password"]').val(),
            new_password: newPassword
        }, function(response) {
            $btn.prop('disabled', false).html(originalText);

            if (response.success) {
                showSuccess(response.data.message);
                // Clear password fields
                $form.find('[name="current_password"], [name="new_password"], [name="confirm_password"]').val('');
            } else {
                showError(response.data.message);
            }
        });
    });

    // ===========================================
    // LOGOUT HANDLER
    // ===========================================

    $(document).on('click', '.btn-logout', function(e) {
        e.preventDefault();

        showConfirm('Are you sure you want to logout?', 'Logout', 'Yes, Logout', 'Cancel').then(function(result) {
            if (result.isConfirmed) {
                showLoading('Logging out...');
                scAjax('sc_public_logout', {}, function(response) {
                    window.location.href = scPublic.homeUrl;
                });
            }
        });
    });

    // ===========================================
    // NEWSLETTER SUBSCRIPTION
    // ===========================================

    $(document).on('submit', '#newsletter-form', function(e) {
        e.preventDefault();

        var $form = $(this);
        var email = $form.find('[name="email"]').val();

        if (!email) {
            showError('Please enter your email');
            return;
        }

        showLoading('Subscribing...');

        scAjax('sc_newsletter_subscribe', { email: email }, function(response) {
            closeLoading();
            if (response.success) {
                showSuccess(response.data.message);
                $form.find('[name="email"]').val('');
            } else {
                showError(response.data.message);
            }
        });
    });

    // ===========================================
    // EVENT FILTERING
    // ===========================================

    $(document).on('click', '.event-filter-btn', function(e) {
        e.preventDefault();

        var $btn = $(this);
        var filter = $btn.data('filter');

        $('.event-filter-btn').removeClass('active');
        $btn.addClass('active');

        if (filter === 'all') {
            $('.event-card').show();
        } else if (filter === 'upcoming') {
            $('.event-card').hide();
            $('.event-card[data-status="upcoming"]').show();
        } else if (filter === 'past') {
            $('.event-card').hide();
            $('.event-card[data-status="past"]').show();
        }
    });

    // ===========================================
    // QUANTITY SELECTOR
    // ===========================================

    $(document).on('click', '.qty-btn-minus', function() {
        var $input = $(this).closest('.sc-checkout-qty').find('.qty-input');
        if (!$input.length) $input = $(this).siblings('.qty-input');
        var val = parseInt($input.val()) || 1;
        var min = parseInt($input.attr('min')) || 1;
        if (val > min) {
            $input.val(val - 1);
            checkoutState.quantity = val - 1;
            updateCheckoutTotal();
        }
    });

    $(document).on('click', '.qty-btn-plus', function() {
        var $input = $(this).closest('.sc-checkout-qty').find('.qty-input');
        if (!$input.length) $input = $(this).siblings('.qty-input');
        var val = parseInt($input.val()) || 1;
        var max = parseInt($input.attr('max')) || 10;
        if (val < max) {
            $input.val(val + 1);
            checkoutState.quantity = val + 1;
            updateCheckoutTotal();
        }
    });

    // ===========================================
    // COUNTDOWN TIMER
    // ===========================================

    function initCountdown() {
        var $countdown = $('#countdown');
        if ($countdown.length === 0) return;

        var targetDate = $countdown.data('date');
        if (!targetDate) return;

        // Parse date (format: Y-m-d)
        var parts = targetDate.split('-');
        var target = new Date(parts[0], parts[1] - 1, parts[2]);

        function updateCountdown() {
            var now = new Date();
            var diff = target - now;

            if (diff <= 0) {
                $('#days').text('00');
                $('#hours').text('00');
                $('#minutes').text('00');
                $('#seconds').text('00');
                return;
            }

            var days = Math.floor(diff / (1000 * 60 * 60 * 24));
            var hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            var minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            var seconds = Math.floor((diff % (1000 * 60)) / 1000);

            $('#days').text(days.toString().padStart(2, '0'));
            $('#hours').text(hours.toString().padStart(2, '0'));
            $('#minutes').text(minutes.toString().padStart(2, '0'));
            $('#seconds').text(seconds.toString().padStart(2, '0'));
        }

        updateCountdown();
        setInterval(updateCountdown, 1000);
    }

    // ===========================================
    // QR CODE GENERATION
    // ===========================================

    $(document).on('click', '.btn-view-qr', function(e) {
        e.preventDefault();

        var $btn = $(this);
        var ticketId = $btn.data('ticket-id');
        var ticketCode = $btn.data('ticket-code');
        var eventName = $btn.data('event-name');
        var attendeeName = $btn.data('attendee-name');

        // Show QR modal
        $('#qr-modal-event-name').text(eventName);
        $('#qr-modal-attendee-name').text(attendeeName);
        $('#qr-modal-ticket-code').text(ticketCode);

        // Generate QR code
        var $qrContainer = $('#qr-code-container');
        $qrContainer.empty();

        if (typeof QRCode !== 'undefined') {
            QRCode.toCanvas(document.createElement('canvas'), ticketCode, {
                width: 200,
                margin: 2
            }, function(error, canvas) {
                if (error) {
                    console.error('QR Error:', error);
                    $qrContainer.html('<p class="text-danger">Error generating QR code</p>');
                    return;
                }
                $qrContainer.append(canvas);
            });
        } else {
            $qrContainer.html('<p class="text-muted">QR code library not loaded</p>');
        }

        $('#qrModal').modal('show');
    });

    // Download QR Code
    $(document).on('click', '.btn-download-qr', function(e) {
        e.preventDefault();

        var canvas = $('#qr-code-container canvas')[0];
        if (!canvas) {
            showError('QR code not generated');
            return;
        }

        var link = document.createElement('a');
        link.download = 'ticket-qr-' + $('#qr-modal-ticket-code').text() + '.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
    });

    // ===========================================
    // SCROLL REVEAL ANIMATION
    // ===========================================

    function initScrollReveal() {
        var reveals = document.querySelectorAll('.reveal, .reveal-left, .reveal-right');

        function checkReveal() {
            var windowHeight = window.innerHeight;
            var revealPoint = 150;

            reveals.forEach(function(element) {
                var elementTop = element.getBoundingClientRect().top;

                if (elementTop < windowHeight - revealPoint) {
                    element.classList.add('active');
                }
            });
        }

        window.addEventListener('scroll', checkReveal);
        checkReveal(); // Check on load
    }

    // ===========================================
    // SMOOTH SCROLL FOR ANCHOR LINKS
    // ===========================================

    function initSmoothScroll() {
        $('a[href^="#"]:not([href="#"])').on('click', function(e) {
            var target = $(this.hash);
            if (target.length) {
                e.preventDefault();
                $('html, body').animate({
                    scrollTop: target.offset().top - 80
                }, 800, 'easeInOutCubic');
            }
        });
    }

    // Add easing function
    $.easing.easeInOutCubic = function(x, t, b, c, d) {
        if ((t /= d / 2) < 1) return c / 2 * t * t * t + b;
        return c / 2 * ((t -= 2) * t * t + 2) + b;
    };

    // ===========================================
    // NAVBAR SCROLL EFFECT
    // ===========================================

    function initNavbarScroll() {
        var $header = $('.sc-header');
        if (!$header.length) $header = $('.main_header_area');
        var scrollThreshold = 80;
        var lastScrollTop = 0;
        var scrollDelta = 8;
        var headerHeight = $header.outerHeight() || 70;
        var ticking = false;

        $(window).on('scroll', function() {
            if (!ticking) {
                window.requestAnimationFrame(function() {
                    var currentScroll = $(window).scrollTop();

                    // Add/remove scrolled class
                    if (currentScroll > scrollThreshold) {
                        $header.addClass('scrolled');
                    } else {
                        $header.removeClass('scrolled');
                        $header.removeClass('header-hidden');
                    }

                    // Show/hide on scroll direction (only after passing header height)
                    if (currentScroll > headerHeight) {
                        if (Math.abs(currentScroll - lastScrollTop) > scrollDelta) {
                            if (currentScroll > lastScrollTop) {
                                // Scrolling DOWN → hide
                                $header.addClass('header-hidden');
                            } else {
                                // Scrolling UP → show
                                $header.removeClass('header-hidden');
                            }
                            lastScrollTop = currentScroll;
                        }
                    }

                    ticking = false;
                });
                ticking = true;
            }
        });
    }

    // ===========================================
    // RIPPLE EFFECT FOR BUTTONS
    // ===========================================

    function initRippleEffect() {
        $(document).on('click', '.btn', function(e) {
            var $btn = $(this);
            var x = e.pageX - $btn.offset().left;
            var y = e.pageY - $btn.offset().top;

            var $ripple = $('<span class="ripple-effect"></span>');
            $ripple.css({
                left: x + 'px',
                top: y + 'px'
            });

            $btn.append($ripple);

            setTimeout(function() {
                $ripple.remove();
            }, 600);
        });
    }

    // Add ripple CSS dynamically
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .btn {
                position: relative;
                overflow: hidden;
            }
            .ripple-effect {
                position: absolute;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.4);
                transform: scale(0);
                animation: ripple-animation 0.6s linear;
                pointer-events: none;
            }
            @keyframes ripple-animation {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `)
        .appendTo('head');

    // ===========================================
    // LAZY LOAD IMAGES
    // ===========================================

    function initLazyLoad() {
        if ('IntersectionObserver' in window) {
            var lazyImages = document.querySelectorAll('img[data-src]');

            var imageObserver = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        var img = entry.target;
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                        img.classList.add('loaded');
                        imageObserver.unobserve(img);
                    }
                });
            });

            lazyImages.forEach(function(img) {
                imageObserver.observe(img);
            });
        }
    }

    // ===========================================
    // CARD HOVER TILT EFFECT
    // ===========================================

    function initCardTilt() {
        $('.event-card, .card-modern, .ticket-card').on('mousemove', function(e) {
            var $card = $(this);
            var rect = this.getBoundingClientRect();
            var x = e.clientX - rect.left;
            var y = e.clientY - rect.top;

            var centerX = rect.width / 2;
            var centerY = rect.height / 2;

            var rotateX = (y - centerY) / 20;
            var rotateY = (centerX - x) / 20;

            $card.css('transform', 'perspective(1000px) rotateX(' + rotateX + 'deg) rotateY(' + rotateY + 'deg) translateY(-10px)');
        }).on('mouseleave', function() {
            $(this).css('transform', 'perspective(1000px) rotateX(0) rotateY(0) translateY(0)');
        });
    }

    // ===========================================
    // COUNTER ANIMATION
    // ===========================================

    function initCounterAnimation() {
        var $counters = $('.stat-number, .counter-value, .sc-stat-number[data-count]');

        if ('IntersectionObserver' in window) {
            var counterObserver = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        var $counter = $(entry.target);
                        var target = parseInt($counter.data('count') || $counter.data('target') || $counter.text());

                        if (!$counter.hasClass('counted')) {
                            $counter.addClass('counted');
                            animateCounter($counter, target);
                        }

                        counterObserver.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.5 });

            $counters.each(function() {
                counterObserver.observe(this);
            });
        }

        function animateCounter($element, target) {
            var duration = 2000;
            var start = 0;
            var startTime = null;

            function step(timestamp) {
                if (!startTime) startTime = timestamp;
                var progress = Math.min((timestamp - startTime) / duration, 1);
                var value = Math.floor(progress * target);
                $element.text(value);

                if (progress < 1) {
                    window.requestAnimationFrame(step);
                } else {
                    $element.text(target);
                }
            }

            window.requestAnimationFrame(step);
        }
    }

    // ===========================================
    // PASSWORD VISIBILITY TOGGLE
    // ===========================================

    function initPasswordToggle() {
        $(document).on('click', '.toggle-password', function() {
            // Skip if button has onclick attribute (handled by inline handler)
            if ($(this).attr('onclick')) {
                return;
            }

            var $input = $(this).siblings('input');
            var $icon = $(this).find('i');

            if ($input.length && $input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $icon.removeClass('fa-eye').addClass('fa-eye-slash');
            } else if ($input.length) {
                $input.attr('type', 'password');
                $icon.removeClass('fa-eye-slash').addClass('fa-eye');
            }
        });
    }

    // ===========================================
    // FORM FLOATING LABELS
    // ===========================================

    function initFloatingLabels() {
        $('.form-floating-modern input').each(function() {
            var $input = $(this);
            if ($input.val()) {
                $input.addClass('has-value');
            }
        });

        $(document).on('blur', '.form-floating-modern input', function() {
            var $input = $(this);
            if ($input.val()) {
                $input.addClass('has-value');
            } else {
                $input.removeClass('has-value');
            }
        });
    }

    // ===========================================
    // DOCUMENT READY
    // ===========================================

    $(document).ready(function() {
        // Initialize countdown
        initCountdown();

        // Initialize Bootstrap tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Initialize modern UI enhancements
        initScrollReveal();
        initSmoothScroll();
        initNavbarScroll();
        initRippleEffect();
        initLazyLoad();
        initCounterAnimation();
        initPasswordToggle();
        initFloatingLabels();

        // Optional: Card tilt effect (can be heavy on mobile)
        if (window.innerWidth > 768) {
            // initCardTilt(); // Uncomment to enable
        }

        // Add loaded class to body
        $('body').addClass('page-loaded');
    });

    // ===========================================
    // FAVORITES
    // ===========================================
    (function() {
        var userFavorites = [];
        var favoritesLoaded = false;

        // Load user favorites on page load
        function loadFavorites() {
            if (!scPublic.isLoggedIn) return;

            scAjax('sc_get_favorites', {}, function(response) {
                if (response.success && response.data) {
                    userFavorites = response.data.favorites || [];
                    favoritesLoaded = true;
                    markFavoriteButtons();
                    updateBadge(response.data.count || 0);
                }
            });
        }

        // Mark all favorite buttons on page
        function markFavoriteButtons() {
            $('.sc-favorite-btn').each(function() {
                var eventId = parseInt($(this).data('event-id'));
                if (userFavorites.indexOf(eventId) !== -1) {
                    $(this).addClass('favorited');
                    // Update hero button text
                    var $text = $(this).find('.sc-favorite-text');
                    if ($text.length) {
                        $text.text(scPublic.i18n && scPublic.i18n.savedToFavorites ? scPublic.i18n.savedToFavorites : 'Saved');
                    }
                }
            });
        }

        // Update header badge
        function updateBadge(count) {
            var $badge = $('#sc-favorites-badge');
            if (count > 0) {
                $badge.text(count).show();
            } else {
                $badge.hide();
            }
        }

        // Toggle favorite
        $(document).on('click', '.sc-favorite-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();

            var $btn = $(this);
            var eventId = parseInt($btn.data('event-id'));

            if (!scPublic.isLoggedIn) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'info',
                        title: scPublic.i18n && scPublic.i18n.loginRequired ? scPublic.i18n.loginRequired : 'Login Required',
                        text: scPublic.i18n && scPublic.i18n.loginToFavorite ? scPublic.i18n.loginToFavorite : 'Please login to save events to your favorites.',
                        confirmButtonText: scPublic.i18n && scPublic.i18n.login ? scPublic.i18n.login : 'Login',
                        showCancelButton: true,
                        cancelButtonText: scPublic.i18n && scPublic.i18n.cancel ? scPublic.i18n.cancel : 'Cancel',
                        customClass: { popup: 'sc-swal-popup', confirmButton: 'sc-swal-confirm' }
                    }).then(function(result) {
                        if (result.isConfirmed) {
                            window.location.href = scPublic.homeUrl + 'login/?redirect=' + encodeURIComponent(window.location.href);
                        }
                    });
                } else {
                    window.location.href = scPublic.homeUrl + 'login/?redirect=' + encodeURIComponent(window.location.href);
                }
                return;
            }

            // Prevent double-clicks
            if ($btn.data('loading')) return;
            $btn.data('loading', true);

            scAjax('sc_toggle_favorite', { event_id: eventId }, function(response) {
                $btn.data('loading', false);

                if (response.success && response.data) {
                    var isFavorited = response.data.favorited;

                    // Update ALL buttons for this event on the page
                    $('.sc-favorite-btn[data-event-id="' + eventId + '"]').each(function() {
                        var $b = $(this);
                        if (isFavorited) {
                            $b.addClass('favorited');
                            var $t = $b.find('.sc-favorite-text');
                            if ($t.length) $t.text(scPublic.i18n && scPublic.i18n.savedToFavorites ? scPublic.i18n.savedToFavorites : 'Saved');
                        } else {
                            $b.removeClass('favorited');
                            var $t2 = $b.find('.sc-favorite-text');
                            if ($t2.length) $t2.text(scPublic.i18n && scPublic.i18n.saveToFavorites ? scPublic.i18n.saveToFavorites : 'Save to Favorites');
                        }

                        // Pulse animation
                        $b.addClass('sc-heart-animating');
                        setTimeout(function() { $b.removeClass('sc-heart-animating'); }, 500);
                    });

                    // Update local array
                    if (isFavorited) {
                        if (userFavorites.indexOf(eventId) === -1) userFavorites.push(eventId);
                    } else {
                        userFavorites = userFavorites.filter(function(id) { return id !== eventId; });
                    }

                    // Update badge
                    updateBadge(response.data.count);
                }
            });
        });

        // Remove favorite from My Account page
        $(document).on('click', '.sc-remove-favorite', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var eventId = parseInt($btn.data('event-id'));
            var $card = $btn.closest('.sc-favorite-card');

            if ($btn.data('loading')) return;
            $btn.data('loading', true);

            scAjax('sc_toggle_favorite', { event_id: eventId }, function(response) {
                $btn.data('loading', false);

                if (response.success && response.data && !response.data.favorited) {
                    // Animate removal
                    $card.addClass('sc-removing');
                    setTimeout(function() {
                        $card.remove();
                        // Update badge
                        updateBadge(response.data.count);
                        // Update nav badge
                        var $navBadge = $('.sc-nav-badge');
                        if (response.data.count > 0) {
                            $navBadge.text(response.data.count);
                        } else {
                            $navBadge.remove();
                            // Show empty state if no more favorites
                            if ($('.sc-favorite-card').length === 0) {
                                var emptyHtml = '<div class="sc-empty-state">' +
                                    '<div class="sc-empty-icon"><i class="fa-solid fa-heart"></i></div>' +
                                    '<h4>' + (scPublic.i18n && scPublic.i18n.noFavorites ? scPublic.i18n.noFavorites : "You haven't saved any events yet") + '</h4>' +
                                    '<p>' + (scPublic.i18n && scPublic.i18n.browseFavoritesHint ? scPublic.i18n.browseFavoritesHint : 'Browse events and click the heart icon to save your favorites!') + '</p>' +
                                    '<a href="' + scPublic.homeUrl + 'events/" class="sc-btn sc-btn-gold">' + (scPublic.i18n && scPublic.i18n.browseEvents ? scPublic.i18n.browseEvents : 'Browse Events') + '</a>' +
                                    '</div>';
                                $('.sc-favorites-list').replaceWith(emptyHtml);
                            }
                        }
                    }, 400);
                }
            });
        });

        // Hash-based tab switching for #favorites
        function checkHashTab() {
            var hash = window.location.hash.replace('#', '');
            if (hash && $('[data-tab="' + hash + '"]').length) {
                $('[data-tab]').removeClass('active');
                $('[data-tab="' + hash + '"]').addClass('active');
                $('[data-tab-content]').hide();
                $('[data-tab-content="' + hash + '"]').show();
            }
        }

        $(window).on('hashchange', checkHashTab);
        checkHashTab();

        // Init
        loadFavorites();
    })();

    // ===========================================
    // WINDOW LOAD
    // ===========================================

    $(window).on('load', function() {
        // Remove preloader if exists
        $('.preloader').fadeOut(500);

        // Trigger scroll reveal on load
        $(window).trigger('scroll');
    });

})(jQuery);
