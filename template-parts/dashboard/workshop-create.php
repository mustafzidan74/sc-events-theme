<?php
/**
 * Workshop Create Page
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) exit;

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

$page_title = sc_t('dashboard_pages.add_workshop', 'Add Workshop');
$preselected_event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;

// Events for parent selector
$events = class_exists('SC_Event') ? SC_Event::get_all(array(
    'status'  => array('publish', 'completed', 'draft'),
    'limit'   => 500,
    'orderby' => 'start_date',
    'order'   => 'DESC',
)) : array();

// Certificate templates
$cert_templates = array();
if (class_exists('SC_Certificate_Template')) {
    $cert_templates = SC_Certificate_Template::get_all(array('is_active' => 1, 'limit' => 200));
}

get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');
?>

<div id="main-content">
<div class="container-fluid">
    <div class="block-header">
        <div class="row">
            <div class="col-lg-6 col-md-6 col-sm-12">
                <h2><?php echo esc_html($page_title); ?></h2>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/home'); ?>"><i class="fa fa-dashboard"></i></a></li>
                    <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/workshops'); ?>">Workshops</a></li>
                    <li class="breadcrumb-item active"><?php echo esc_html($page_title); ?></li>
                </ul>
            </div>
        </div>
    </div>

    <form id="workshop-form">
        <input type="hidden" name="action" value="sc_save_workshop">

        <div class="row">
            <div class="col-lg-8">
                <!-- Basic Info -->
                <div class="card">
                    <div class="header"><h2><i class="fa fa-info-circle"></i> Basic Information</h2></div>
                    <div class="body">
                        <div class="form-group">
                            <label>Parent Event <span class="text-danger">*</span></label>
                            <select class="form-control" name="event_id" required>
                                <option value="">-- Select Event --</option>
                                <?php foreach ($events as $ev): ?>
                                <option value="<?php echo (int) $ev->id; ?>" <?php selected($preselected_event_id, (int) $ev->id); ?>><?php echo esc_html($ev->title); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Workshop Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="title" required>
                        </div>
                        <div class="form-group">
                            <label>Slug (auto-generated if empty)</label>
                            <input type="text" class="form-control" name="slug">
                        </div>
                        <div class="form-group">
                            <label>Excerpt</label>
                            <textarea class="form-control" name="excerpt" rows="2"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <?php
                            wp_editor('', 'workshop_description', array(
                                'textarea_name' => 'description',
                                'media_buttons' => true,
                                'textarea_rows' => 8,
                            ));
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Date & Location -->
                <div class="card">
                    <div class="header"><h2><i class="fa fa-calendar"></i> Date &amp; Location</h2></div>
                    <div class="body">
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label>Start Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="start_date" required>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>End Date</label>
                                <input type="date" class="form-control" name="end_date">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label>Start Time</label>
                                <input type="time" class="form-control" name="start_time">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>End Time</label>
                                <input type="time" class="form-control" name="end_time">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Location Type</label>
                            <select class="form-control" name="location_type">
                                <option value="offline">In-person</option>
                                <option value="online">Online</option>
                                <option value="hybrid">Hybrid</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Venue Name</label>
                            <input type="text" class="form-control" name="venue_name">
                        </div>
                        <div class="form-group">
                            <label>Venue Address</label>
                            <textarea class="form-control" name="venue_address" rows="2"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Online Meeting Link</label>
                            <input type="url" class="form-control" name="meeting_link" placeholder="https://...">
                        </div>
                    </div>
                </div>

                <!-- Capacity & Registration -->
                <div class="card">
                    <div class="header"><h2><i class="fa fa-users"></i> Capacity</h2></div>
                    <div class="body">
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label>Total Capacity (0 = unlimited)</label>
                                <input type="number" class="form-control" name="total_capacity" value="0" min="0">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Registration Deadline</label>
                                <input type="datetime-local" class="form-control" name="registration_deadline">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label>Min Tickets per Order</label>
                                <input type="number" class="form-control" name="min_tickets_per_order" value="1" min="1">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Max Tickets per Order</label>
                                <input type="number" class="form-control" name="max_tickets_per_order" value="10" min="1">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Certificates -->
                <div class="card">
                    <div class="header"><h2><i class="fa fa-certificate"></i> Certificates</h2></div>
                    <div class="body">
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="enable_certificates" value="1"> Enable certificates for this workshop
                            </label>
                        </div>
                        <?php if (!empty($cert_templates)): ?>
                        <div class="form-group">
                            <label>Certificate Template</label>
                            <select class="form-control" name="certificate_template_id">
                                <option value="">-- Select Template --</option>
                                <?php foreach ($cert_templates as $tpl): ?>
                                <option value="<?php echo (int) $tpl->id; ?>"><?php echo esc_html($tpl->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="auto_issue_certificate" value="1"> Auto-issue certificate after check-in
                            </label>
                        </div>
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="certificate_require_checkin" value="1" checked> Require check-in before issuing certificate
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="header"><h2>Publish</h2></div>
                    <div class="body">
                        <div class="form-group">
                            <label>Status</label>
                            <select class="form-control" name="status">
                                <option value="draft">Draft</option>
                                <option value="publish" selected>Published</option>
                                <option value="private">Private</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg btn-block" id="save-workshop-btn">
                            <i class="fa fa-save"></i> Save Workshop
                        </button>
                        <p class="text-muted mt-2 mb-0">
                            <small>You can add tickets after creating the workshop.</small>
                        </p>
                    </div>
                </div>

                <div class="card">
                    <div class="header"><h2>Featured Image</h2></div>
                    <div class="body">
                        <div class="sc-media-picker">
                            <input type="hidden" name="featured_image" id="featured-image-id" value="">
                            <button type="button" class="btn btn-outline-primary btn-block" id="pick-featured-image">
                                <i class="fa fa-image"></i> Choose Image
                            </button>
                            <div id="featured-image-preview" style="margin-top:10px;"></div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="header"><h2>Banner Image</h2></div>
                    <div class="body">
                        <div class="sc-media-picker">
                            <input type="hidden" name="banner_image" id="banner-image-id" value="">
                            <button type="button" class="btn btn-outline-primary btn-block" id="pick-banner-image">
                                <i class="fa fa-image"></i> Choose Image
                            </button>
                            <div id="banner-image-preview" style="margin-top:10px;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    'use strict';

    // Media picker (WP media uploader)
    function bindMediaPicker(buttonId, hiddenId, previewId) {
        $('#' + buttonId).on('click', function(e) {
            e.preventDefault();
            const frame = wp.media({ title: 'Choose Image', button: { text: 'Use this image' }, multiple: false });
            frame.on('select', function() {
                const att = frame.state().get('selection').first().toJSON();
                $('#' + hiddenId).val(att.id);
                $('#' + previewId).html('<img src="' + att.url + '" style="max-width:100%;border-radius:6px;">');
            });
            frame.open();
        });
    }
    bindMediaPicker('pick-featured-image', 'featured-image-id', 'featured-image-preview');
    bindMediaPicker('pick-banner-image', 'banner-image-id', 'banner-image-preview');

    $('#workshop-form').on('submit', function(e) {
        e.preventDefault();

        // Sync TinyMCE content into textarea before serializing
        if (typeof tinymce !== 'undefined' && tinymce.get('workshop_description')) {
            tinymce.get('workshop_description').save();
        }

        const $btn = $('#save-workshop-btn');
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: $(this).serialize() + '&nonce=' + scDashboard.nonce
        }).done(function(resp) {
            if (resp.success) {
                if (typeof toastr !== 'undefined') toastr.success(resp.data.message);
                window.location.href = resp.data.redirect;
            } else {
                alert(resp.data.message || 'Failed to save workshop');
                $btn.prop('disabled', false).html('<i class="fa fa-save"></i> Save Workshop');
            }
        }).fail(function() {
            alert('Connection error. Please try again.');
            $btn.prop('disabled', false).html('<i class="fa fa-save"></i> Save Workshop');
        });
    });
});
</script>

</div>
</div>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
