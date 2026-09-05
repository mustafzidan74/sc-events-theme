<?php
/**
 * Template Name: Certificate Order Page
 * Search by Serial Code to get Certificate
 *
 * @package sc_events
 * @version 1.0.0
 */

// Load header
get_template_part('template-parts/public/header', 'public');

// Handle AJAX request
if (isset($_POST['action']) && $_POST['action'] === 'search_coupon') {
    // Verify nonce
    if (!isset($_POST['coupon_nonce']) || !wp_verify_nonce($_POST['coupon_nonce'], 'search_coupon_nonce')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'sc_events')));
    }
    
    $coupon_code = isset($_POST['coupon_code']) ? sanitize_text_field(trim($_POST['coupon_code'])) : '';
    
    if (empty($coupon_code)) {
        wp_send_json_error(array('message' => __('Please enter a Serial Code.', 'sc_events')));
    }
    
    // Search for attendee with this Serial Code
    $attendee_args = array(
        'post_type' => 'etn-attendee',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'meta_query' => array(
            array(
                'key' => 'etn_coupon_code',
                'value' => $coupon_code,
                'compare' => '='
            )
        )
    );
    
    $attendees = new WP_Query($attendee_args);
    
    if ($attendees->have_posts()) {
        $attendees->the_post();
        $attendee_id = get_the_ID();
        $event_id = get_post_meta($attendee_id, 'etn_event_id', true);
        
        if ($event_id) {
            $certificate_url = home_url('/certifcate/?event_id=' . $event_id . '&attandee_id=' . $attendee_id);
            wp_send_json_success(array(
                'redirect_url' => $certificate_url,
                'attendee_id' => $attendee_id,
                'event_id' => $event_id
            ));
        } else {
            wp_send_json_error(array('message' => __('Event not found for this coupon.', 'sc_events')));
        }
        wp_reset_postdata();
    } else {
        wp_send_json_error(array('message' => __('Invalid Serial Code. Please check and try again.', 'sc_events')));
    }
    
    exit;
}
?>

<style>
/* Certificate Order Page Styles */
.certificate-order-section {
    min-height: 100vh;
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
    padding: 60px 0;
    display: flex;
    align-items: center;
}

.certificate-order-container {
    max-width: 600px;
    margin: 0 auto;
    padding: 0 20px;
}

.certificate-order-card {
    background: rgba(255, 255, 255, 0.95);
    border-radius: 20px;
    padding: 50px 40px;
    box-shadow: 0 25px 80px rgba(0, 0, 0, 0.3);
    text-align: center;
}

.certificate-order-icon {
    width: 100px;
    height: 100px;
    background: linear-gradient(135deg, #8B1538 0%, #A91D3A 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 30px;
    box-shadow: 0 10px 30px rgba(139, 21, 56, 0.3);
}

.certificate-order-icon i {
    font-size: 45px;
    color: #ffffff;
}

.certificate-order-card h1 {
    color: #1a1a2e;
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 15px;
}

.certificate-order-card p {
    color: #666;
    font-size: 1.1rem;
    margin-bottom: 35px;
}

.coupon-form-group {
    margin-bottom: 25px;
}

.coupon-form-group label {
    display: block;
    text-align: right;
    color: #333;
    font-weight: 600;
    margin-bottom: 10px;
    font-size: 1rem;
}

.coupon-input {
    width: 100%;
    padding: 18px 20px;
    font-size: 1.2rem;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    text-align: center;
    font-weight: 600;
    letter-spacing: 2px;
    text-transform: uppercase;
    transition: all 0.3s ease;
}

.coupon-input:focus {
    border-color: #8B1538;
    outline: none;
    box-shadow: 0 0 0 4px rgba(139, 21, 56, 0.1);
}

.coupon-input::placeholder {
    color: #aaa;
    font-weight: 400;
    letter-spacing: 1px;
}

.btn-search-coupon {
    width: 100%;
    padding: 18px 30px;
    font-size: 1.1rem;
    font-weight: 700;
    color: #ffffff;
    background: linear-gradient(135deg, #8B1538 0%, #A91D3A 100%);
    border: none;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.btn-search-coupon:hover {
    background: linear-gradient(135deg, #A91D3A 0%, #C41E3A 100%);
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(139, 21, 56, 0.3);
}

.btn-search-coupon:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
}

.btn-search-coupon .spinner {
    width: 20px;
    height: 20px;
    border: 3px solid rgba(255, 255, 255, 0.3);
    border-top-color: #ffffff;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Alert Messages */
.coupon-alert {
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 25px;
    display: none;
    text-align: center;
    font-weight: 600;
}

.coupon-alert.alert-error {
    background: #fee2e2;
    color: #dc2626;
    border: 1px solid #fecaca;
}

.coupon-alert.alert-success {
    background: #dcfce7;
    color: #16a34a;
    border: 1px solid #bbf7d0;
}

.coupon-alert.show {
    display: block;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Help Text */
.help-text {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #eee;
    color: #888;
    font-size: 0.9rem;
}

.help-text i {
    color: #8B1538;
    margin-left: 5px;
}

/* Back Link */
.back-link {
    margin-top: 25px;
}

.back-link a {
    color: #8B1538;
    text-decoration: none;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.back-link a:hover {
    color: #A91D3A;
}

.certificate-order-section {
    padding-top: 160px;
}

/* Responsive */
@media (max-width: 576px) {
    .certificate-order-card {
        padding: 35px 25px;
    }
    
    .certificate-order-card h1 {
        font-size: 1.6rem;
    }
    
    .coupon-input {
        font-size: 1rem;
        padding: 15px;
    }
    
    .btn-search-coupon {
        padding: 15px 25px;
        font-size: 1rem;
    }
    .certificate-order-section {
    padding-top: 80px;
}
}
</style>

<!--===== CERTIFICATE ORDER PAGE =======-->
<div class="certificate-order-section">
    <div class="certificate-order-container">
        <div class="certificate-order-card">
            <!-- Icon -->
            <div class="certificate-order-icon">
                <i class="fa-solid fa-certificate"></i>
            </div>
            
            <!-- Title -->
            <h1><?php esc_html_e('Get Your Certificate', 'sc_events'); ?></h1>
            <p><?php esc_html_e('Enter your Serial Code to download your certificate', 'sc_events'); ?></p>
            
            <!-- Alert Message -->
            <div class="coupon-alert" id="coupon-alert"></div>
            
            <!-- Form -->
            <form id="coupon-search-form">
                <input type="hidden" name="action" value="search_coupon">
                <?php wp_nonce_field('search_coupon_nonce', 'coupon_nonce'); ?>
                
                <div class="coupon-form-group">
                    <label for="coupon_code">
                        <i class="fa-solid fa-ticket me-2"></i><?php esc_html_e('Serial Code', 'sc_events'); ?>
                    </label>
                    <input 
                        type="text" 
                        id="coupon_code" 
                        name="coupon_code" 
                        class="coupon-input" 
                        placeholder="<?php esc_attr_e('Enter Serial Code...', 'sc_events'); ?>"
                        required
                        autocomplete="off"
                    >
                </div>
                
                <button type="submit" class="btn-search-coupon" id="search-btn">
                    <i class="fa-solid fa-search"></i>
                    <span><?php esc_html_e('Get Certificate', 'sc_events'); ?></span>
                </button>
            </form>
            
            <!-- Help Text -->
            <div class="help-text">
                <i class="fa-solid fa-info-circle"></i>
                <?php esc_html_e('The Serial Code was provided to you during registration.', 'sc_events'); ?>
            </div>
            
            <!-- Back Link -->
            <div class="back-link">
                <a href="<?php echo esc_url(home_url('/')); ?>">
                    <i class="fa-solid fa-arrow-left"></i>
                    <?php esc_html_e('Back to Home', 'sc_events'); ?>
                </a>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#coupon-search-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $btn = $('#search-btn');
        var $alert = $('#coupon-alert');
        var couponCode = $('#coupon_code').val().trim();
        
        // Validate
        if (!couponCode) {
            showAlert('error', '<?php echo esc_js(__('Please enter a Serial Code.', 'sc_events')); ?>');
            return;
        }
        
        // Disable button and show loading
        $btn.prop('disabled', true);
        $btn.html('<div class="spinner"></div><span><?php echo esc_js(__('Searching...', 'sc_events')); ?></span>');
        $alert.removeClass('show alert-error alert-success');
        
        // AJAX Request
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'sc_search_certificate_by_coupon',
                coupon_code: couponCode,
                coupon_nonce: $('#coupon_nonce').val()
            },
            success: function(response) {
                if (response.success) {
                    showAlert('success', '<?php echo esc_js(__('Certificate found! Redirecting...', 'sc_events')); ?>');
                    
                    // Redirect after short delay
                    setTimeout(function() {
                        window.location.href = response.data.redirect_url;
                    }, 1000);
                } else {
                    showAlert('error', response.data.message || '<?php echo esc_js(__('Coupon not found.', 'sc_events')); ?>');
                    resetButton();
                }
            },
            error: function() {
                showAlert('error', '<?php echo esc_js(__('Connection error. Please try again.', 'sc_events')); ?>');
                resetButton();
            }
        });
        
        function showAlert(type, message) {
            $alert.removeClass('alert-error alert-success')
                  .addClass('alert-' + type + ' show')
                  .text(message);
        }
        
        function resetButton() {
            $btn.prop('disabled', false);
            $btn.html('<i class="fa-solid fa-search"></i><span><?php echo esc_js(__('Get Certificate', 'sc_events')); ?></span>');
        }
    });
    
    // Auto uppercase coupon input
    $('#coupon_code').on('input', function() {
        $(this).val($(this).val().toUpperCase());
    });
});
</script>

<?php
// Load footer
get_template_part('template-parts/public/footer', 'public');
?>
