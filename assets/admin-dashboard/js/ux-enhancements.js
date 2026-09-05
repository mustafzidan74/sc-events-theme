/**
 * SC Events UX Enhancements
 *
 * Unified JavaScript for:
 * - Loading states (spinners, skeletons, overlays)
 * - Form validation with bilingual messages
 * - Toast notifications
 * - AJAX enhancements
 *
 * @package sc_events
 * @since 2.3.0
 */

(function($) {
    'use strict';

    // ========================================
    // CONFIGURATION
    // ========================================

    const SC_UX = {
        isRTL: document.documentElement.dir === 'rtl' || $('html').attr('lang') === 'ar',
        defaultDuration: 3000,
        animationDuration: 300
    };

    // Bilingual messages
    const MESSAGES = {
        loading: {
            en: 'Loading...',
            ar: 'جاري التحميل...'
        },
        saving: {
            en: 'Saving...',
            ar: 'جاري الحفظ...'
        },
        deleting: {
            en: 'Deleting...',
            ar: 'جاري الحذف...'
        },
        success: {
            en: 'Success!',
            ar: 'تم بنجاح!'
        },
        error: {
            en: 'An error occurred',
            ar: 'حدث خطأ'
        },
        required: {
            en: 'This field is required',
            ar: 'هذا الحقل مطلوب'
        },
        invalidEmail: {
            en: 'Please enter a valid email',
            ar: 'الرجاء إدخال بريد إلكتروني صحيح'
        },
        invalidPhone: {
            en: 'Please enter a valid phone number',
            ar: 'الرجاء إدخال رقم هاتف صحيح'
        },
        invalidNumber: {
            en: 'Please enter a valid number',
            ar: 'الرجاء إدخال رقم صحيح'
        },
        minLength: {
            en: 'Minimum {min} characters required',
            ar: 'الحد الأدنى {min} حرف'
        },
        maxLength: {
            en: 'Maximum {max} characters allowed',
            ar: 'الحد الأقصى {max} حرف'
        },
        confirmDelete: {
            en: 'Are you sure you want to delete this?',
            ar: 'هل أنت متأكد من الحذف؟'
        },
        networkError: {
            en: 'Network error. Please try again.',
            ar: 'خطأ في الاتصال. حاول مرة أخرى.'
        }
    };

    // Get message based on current language
    function getMessage(key, replacements = {}) {
        const msg = MESSAGES[key];
        if (!msg) return key;

        let text = SC_UX.isRTL ? msg.ar : msg.en;

        // Replace placeholders
        Object.keys(replacements).forEach(k => {
            text = text.replace('{' + k + '}', replacements[k]);
        });

        return text;
    }

    // ========================================
    // LOADING STATES
    // ========================================

    const LoadingState = {
        // Spinner HTML template
        spinnerHTML: `
            <div class="sc-loading-spinner">
                <div class="spinner-border text-primary" role="status">
                    <span class="sr-only">${getMessage('loading')}</span>
                </div>
            </div>
        `,

        // Overlay HTML template
        overlayHTML: `
            <div class="sc-loading-overlay">
                <div class="sc-loading-content">
                    <div class="spinner-border text-light" role="status"></div>
                    <p class="sc-loading-text">${getMessage('loading')}</p>
                </div>
            </div>
        `,

        // Skeleton HTML templates
        skeletons: {
            card: `
                <div class="sc-skeleton-card">
                    <div class="sc-skeleton sc-skeleton-img"></div>
                    <div class="sc-skeleton sc-skeleton-title"></div>
                    <div class="sc-skeleton sc-skeleton-text"></div>
                    <div class="sc-skeleton sc-skeleton-text short"></div>
                </div>
            `,
            table: `
                <div class="sc-skeleton-table">
                    <div class="sc-skeleton sc-skeleton-row"></div>
                    <div class="sc-skeleton sc-skeleton-row"></div>
                    <div class="sc-skeleton sc-skeleton-row"></div>
                    <div class="sc-skeleton sc-skeleton-row"></div>
                    <div class="sc-skeleton sc-skeleton-row"></div>
                </div>
            `,
            list: `
                <div class="sc-skeleton-list">
                    <div class="sc-skeleton-list-item">
                        <div class="sc-skeleton sc-skeleton-avatar"></div>
                        <div class="sc-skeleton-list-content">
                            <div class="sc-skeleton sc-skeleton-text"></div>
                            <div class="sc-skeleton sc-skeleton-text short"></div>
                        </div>
                    </div>
                    <div class="sc-skeleton-list-item">
                        <div class="sc-skeleton sc-skeleton-avatar"></div>
                        <div class="sc-skeleton-list-content">
                            <div class="sc-skeleton sc-skeleton-text"></div>
                            <div class="sc-skeleton sc-skeleton-text short"></div>
                        </div>
                    </div>
                    <div class="sc-skeleton-list-item">
                        <div class="sc-skeleton sc-skeleton-avatar"></div>
                        <div class="sc-skeleton-list-content">
                            <div class="sc-skeleton sc-skeleton-text"></div>
                            <div class="sc-skeleton sc-skeleton-text short"></div>
                        </div>
                    </div>
                </div>
            `
        },

        // Show spinner inside element
        showSpinner: function(element, size = 'md') {
            const $el = $(element);
            $el.data('original-content', $el.html());
            $el.html(this.spinnerHTML);
            $el.addClass('sc-is-loading');
        },

        // Hide spinner and restore content
        hideSpinner: function(element) {
            const $el = $(element);
            const original = $el.data('original-content');
            if (original) {
                $el.html(original);
            }
            $el.removeClass('sc-is-loading');
        },

        // Show overlay on element
        showOverlay: function(element, text = null) {
            const $el = $(element);
            $el.css('position', 'relative');

            let overlay = $(this.overlayHTML);
            if (text) {
                overlay.find('.sc-loading-text').text(text);
            }

            $el.append(overlay);
            $el.addClass('sc-has-overlay');
        },

        // Hide overlay
        hideOverlay: function(element) {
            const $el = $(element);
            $el.find('.sc-loading-overlay').remove();
            $el.removeClass('sc-has-overlay');
        },

        // Show skeleton loader
        showSkeleton: function(element, type = 'card') {
            const $el = $(element);
            $el.data('original-content', $el.html());
            $el.html(this.skeletons[type] || this.skeletons.card);
            $el.addClass('sc-is-skeleton');
        },

        // Hide skeleton and restore content
        hideSkeleton: function(element) {
            const $el = $(element);
            const original = $el.data('original-content');
            if (original) {
                $el.html(original);
            }
            $el.removeClass('sc-is-skeleton');
        },

        // Button loading state
        buttonLoading: function(button, loading = true) {
            const $btn = $(button);

            if (loading) {
                $btn.data('original-text', $btn.html());
                $btn.html('<span class="spinner-border spinner-border-sm me-2"></span>' + getMessage('loading'));
                $btn.prop('disabled', true);
                $btn.addClass('sc-btn-loading');
            } else {
                const original = $btn.data('original-text');
                if (original) {
                    $btn.html(original);
                }
                $btn.prop('disabled', false);
                $btn.removeClass('sc-btn-loading');
            }
        }
    };

    // ========================================
    // TOAST NOTIFICATIONS
    // ========================================

    const Toast = {
        container: null,

        init: function() {
            if (!this.container) {
                this.container = $('<div class="sc-toast-container"></div>');
                $('body').append(this.container);
            }
        },

        show: function(message, type = 'info', duration = SC_UX.defaultDuration) {
            this.init();

            const icons = {
                success: '<i class="fa fa-check-circle"></i>',
                error: '<i class="fa fa-times-circle"></i>',
                warning: '<i class="fa fa-exclamation-triangle"></i>',
                info: '<i class="fa fa-info-circle"></i>'
            };

            const toast = $(`
                <div class="sc-toast sc-toast-${type}">
                    <div class="sc-toast-icon">${icons[type] || icons.info}</div>
                    <div class="sc-toast-message">${message}</div>
                    <button class="sc-toast-close">&times;</button>
                </div>
            `);

            this.container.append(toast);

            // Animate in
            setTimeout(() => toast.addClass('sc-toast-show'), 10);

            // Close button
            toast.find('.sc-toast-close').on('click', () => this.hide(toast));

            // Auto hide
            if (duration > 0) {
                setTimeout(() => this.hide(toast), duration);
            }

            return toast;
        },

        hide: function(toast) {
            toast.removeClass('sc-toast-show');
            setTimeout(() => toast.remove(), SC_UX.animationDuration);
        },

        success: function(message, duration) {
            return this.show(message, 'success', duration);
        },

        error: function(message, duration) {
            return this.show(message, 'error', duration);
        },

        warning: function(message, duration) {
            return this.show(message, 'warning', duration);
        },

        info: function(message, duration) {
            return this.show(message, 'info', duration);
        }
    };

    // ========================================
    // FORM VALIDATION
    // ========================================

    const FormValidator = {
        // Validation rules
        rules: {
            required: function(value) {
                return value !== null && value !== undefined && value.toString().trim() !== '';
            },
            email: function(value) {
                if (!value) return true; // Not required, let required rule handle it
                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
            },
            phone: function(value) {
                if (!value) return true;
                return /^[\+]?[(]?[0-9]{3}[)]?[-\s\.]?[0-9]{3}[-\s\.]?[0-9]{4,6}$/.test(value);
            },
            number: function(value) {
                if (!value) return true;
                return !isNaN(parseFloat(value)) && isFinite(value);
            },
            minLength: function(value, min) {
                if (!value) return true;
                return value.length >= min;
            },
            maxLength: function(value, max) {
                if (!value) return true;
                return value.length <= max;
            },
            min: function(value, min) {
                if (!value) return true;
                return parseFloat(value) >= min;
            },
            max: function(value, max) {
                if (!value) return true;
                return parseFloat(value) <= max;
            },
            pattern: function(value, pattern) {
                if (!value) return true;
                return new RegExp(pattern).test(value);
            }
        },

        // Validate a single field
        validateField: function($field) {
            const value = $field.val();
            const rules = this.getFieldRules($field);
            const errors = [];

            for (const rule of rules) {
                const ruleName = rule.name;
                const ruleParam = rule.param;
                const validator = this.rules[ruleName];

                if (validator && !validator(value, ruleParam)) {
                    errors.push(this.getErrorMessage(ruleName, ruleParam));
                }
            }

            return errors;
        },

        // Get rules for a field from data attributes
        getFieldRules: function($field) {
            const rules = [];

            if ($field.prop('required') || $field.data('required')) {
                rules.push({ name: 'required' });
            }

            if ($field.attr('type') === 'email' || $field.data('email')) {
                rules.push({ name: 'email' });
            }

            if ($field.data('phone')) {
                rules.push({ name: 'phone' });
            }

            if ($field.attr('type') === 'number') {
                rules.push({ name: 'number' });
            }

            if ($field.attr('minlength') || $field.data('minlength')) {
                rules.push({ name: 'minLength', param: parseInt($field.attr('minlength') || $field.data('minlength')) });
            }

            if ($field.attr('maxlength') || $field.data('maxlength')) {
                rules.push({ name: 'maxLength', param: parseInt($field.attr('maxlength') || $field.data('maxlength')) });
            }

            if ($field.attr('min')) {
                rules.push({ name: 'min', param: parseFloat($field.attr('min')) });
            }

            if ($field.attr('max')) {
                rules.push({ name: 'max', param: parseFloat($field.attr('max')) });
            }

            if ($field.data('pattern')) {
                rules.push({ name: 'pattern', param: $field.data('pattern') });
            }

            return rules;
        },

        // Get error message for rule
        getErrorMessage: function(ruleName, param) {
            switch (ruleName) {
                case 'required':
                    return getMessage('required');
                case 'email':
                    return getMessage('invalidEmail');
                case 'phone':
                    return getMessage('invalidPhone');
                case 'number':
                    return getMessage('invalidNumber');
                case 'minLength':
                    return getMessage('minLength', { min: param });
                case 'maxLength':
                    return getMessage('maxLength', { max: param });
                default:
                    return getMessage('error');
            }
        },

        // Show field error
        showFieldError: function($field, errors) {
            this.clearFieldError($field);

            if (errors.length === 0) return;

            $field.addClass('is-invalid');

            const errorHTML = `<div class="invalid-feedback">${errors[0]}</div>`;
            $field.after(errorHTML);
        },

        // Clear field error
        clearFieldError: function($field) {
            $field.removeClass('is-invalid');
            $field.siblings('.invalid-feedback').remove();
        },

        // Validate entire form
        validateForm: function($form) {
            let isValid = true;
            const self = this;

            $form.find('input, select, textarea').each(function() {
                const $field = $(this);
                const errors = self.validateField($field);

                if (errors.length > 0) {
                    isValid = false;
                    self.showFieldError($field, errors);
                } else {
                    self.clearFieldError($field);
                }
            });

            return isValid;
        },

        // Initialize form validation
        initForm: function($form) {
            const self = this;

            // Real-time validation on blur
            $form.on('blur', 'input, select, textarea', function() {
                const $field = $(this);
                const errors = self.validateField($field);
                self.showFieldError($field, errors);
            });

            // Clear error on focus
            $form.on('focus', 'input, select, textarea', function() {
                self.clearFieldError($(this));
            });

            // Validate on submit
            $form.on('submit', function(e) {
                if (!self.validateForm($form)) {
                    e.preventDefault();
                    Toast.error(SC_UX.isRTL ? 'الرجاء تصحيح الأخطاء في النموذج' : 'Please fix the errors in the form');

                    // Scroll to first error
                    const $firstError = $form.find('.is-invalid').first();
                    if ($firstError.length) {
                        $('html, body').animate({
                            scrollTop: $firstError.offset().top - 100
                        }, 300);
                    }
                }
            });
        }
    };

    // ========================================
    // AJAX ENHANCEMENTS
    // ========================================

    const AjaxEnhancer = {
        // Enhanced AJAX call with loading states
        request: function(options) {
            const defaults = {
                loadingElement: null,
                loadingType: 'overlay', // 'overlay', 'spinner', 'button'
                loadingText: getMessage('loading'),
                showToast: true,
                successMessage: getMessage('success'),
                errorMessage: getMessage('error')
            };

            const settings = $.extend({}, defaults, options);

            // Show loading state
            if (settings.loadingElement) {
                switch (settings.loadingType) {
                    case 'overlay':
                        LoadingState.showOverlay(settings.loadingElement, settings.loadingText);
                        break;
                    case 'spinner':
                        LoadingState.showSpinner(settings.loadingElement);
                        break;
                    case 'button':
                        LoadingState.buttonLoading(settings.loadingElement, true);
                        break;
                }
            }

            return $.ajax(settings)
                .done(function(response) {
                    if (settings.showToast && settings.successMessage) {
                        if (response.success !== false) {
                            Toast.success(response.message || settings.successMessage);
                        }
                    }
                })
                .fail(function(xhr) {
                    if (settings.showToast) {
                        let errorMsg = settings.errorMessage;
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMsg = xhr.responseJSON.message;
                        }
                        Toast.error(errorMsg);
                    }
                })
                .always(function() {
                    // Hide loading state
                    if (settings.loadingElement) {
                        switch (settings.loadingType) {
                            case 'overlay':
                                LoadingState.hideOverlay(settings.loadingElement);
                                break;
                            case 'spinner':
                                LoadingState.hideSpinner(settings.loadingElement);
                                break;
                            case 'button':
                                LoadingState.buttonLoading(settings.loadingElement, false);
                                break;
                        }
                    }
                });
        }
    };

    // ========================================
    // CONFIRMATION DIALOGS
    // ========================================

    const Confirm = {
        show: function(message, options = {}) {
            return new Promise((resolve) => {
                const defaults = {
                    title: SC_UX.isRTL ? 'تأكيد' : 'Confirm',
                    confirmText: SC_UX.isRTL ? 'تأكيد' : 'Confirm',
                    cancelText: SC_UX.isRTL ? 'إلغاء' : 'Cancel',
                    type: 'warning' // 'warning', 'danger', 'info'
                };

                const settings = $.extend({}, defaults, options);

                const modal = $(`
                    <div class="modal fade sc-confirm-modal" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">${settings.title}</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <p>${message}</p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary sc-confirm-cancel" data-bs-dismiss="modal">
                                        ${settings.cancelText}
                                    </button>
                                    <button type="button" class="btn btn-${settings.type === 'danger' ? 'danger' : 'primary'} sc-confirm-ok">
                                        ${settings.confirmText}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `);

                $('body').append(modal);

                const bsModal = new bootstrap.Modal(modal[0]);
                bsModal.show();

                modal.find('.sc-confirm-ok').on('click', function() {
                    bsModal.hide();
                    resolve(true);
                });

                modal.find('.sc-confirm-cancel, .btn-close').on('click', function() {
                    resolve(false);
                });

                modal.on('hidden.bs.modal', function() {
                    modal.remove();
                });
            });
        },

        delete: function(message = null) {
            return this.show(message || getMessage('confirmDelete'), {
                title: SC_UX.isRTL ? 'تأكيد الحذف' : 'Confirm Delete',
                confirmText: SC_UX.isRTL ? 'حذف' : 'Delete',
                type: 'danger'
            });
        }
    };

    // ========================================
    // INITIALIZATION
    // ========================================

    $(document).ready(function() {
        // Initialize Toast container
        Toast.init();

        // Auto-initialize forms with sc-validate class
        $('.sc-validate').each(function() {
            FormValidator.initForm($(this));
        });

        // Delete confirmation for buttons with data-confirm-delete
        $(document).on('click', '[data-confirm-delete]', async function(e) {
            e.preventDefault();
            const $btn = $(this);
            const message = $btn.data('confirm-delete') || getMessage('confirmDelete');

            const confirmed = await Confirm.delete(message);
            if (confirmed) {
                // Trigger the original action
                if ($btn.data('action')) {
                    $btn.trigger($btn.data('action'));
                } else if ($btn.attr('href')) {
                    window.location.href = $btn.attr('href');
                } else {
                    $btn.closest('form').submit();
                }
            }
        });
    });

    // ========================================
    // EXPORT TO GLOBAL SCOPE
    // ========================================

    window.SC_UX = {
        LoadingState: LoadingState,
        Toast: Toast,
        FormValidator: FormValidator,
        AjaxEnhancer: AjaxEnhancer,
        Confirm: Confirm,
        getMessage: getMessage,
        isRTL: SC_UX.isRTL
    };

})(jQuery);
