<?php
/**
 * Workshop Edit Page
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) exit;

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

$workshop_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$workshop_id) {
    wp_redirect(home_url('/event-manager-dashboard/workshops'));
    exit;
}

$workshop = SC_Workshop::get($workshop_id);
if (!$workshop) {
    wp_die('Workshop not found.');
}

$page_title = 'Edit: ' . esc_html($workshop->title);

$events = class_exists('SC_Event') ? SC_Event::get_all(array(
    'status'  => array('publish', 'completed', 'draft'),
    'limit'   => 500,
    'orderby' => 'start_date',
    'order'   => 'DESC',
)) : array();

$cert_templates = array();
if (class_exists('SC_Certificate_Template')) {
    $cert_templates = SC_Certificate_Template::get_all(array('is_active' => 1, 'limit' => 200));
}

// Workshop tickets
$tickets = SC_Ticket::get_by_workshop($workshop_id);

get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');

$featured_url = $workshop->featured_image ? wp_get_attachment_url($workshop->featured_image) : '';
$banner_url = $workshop->banner_image ? wp_get_attachment_url($workshop->banner_image) : '';
?>

<div id="main-content">
<div class="container-fluid">
    <div class="block-header">
        <div class="row">
            <div class="col-lg-8">
                <h2><?php echo $page_title; ?></h2>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/home'); ?>"><i class="fa fa-dashboard"></i></a></li>
                    <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/workshops'); ?>">Workshops</a></li>
                    <li class="breadcrumb-item active">Edit</li>
                </ul>
            </div>
            <div class="col-lg-4 text-right">
                <a href="<?php echo home_url('/event-manager-dashboard/workshop-attendees?workshop_id=' . $workshop_id); ?>" class="btn btn-info">
                    <i class="fa fa-users"></i> Attendees (<?php echo (int) $workshop->total_sold; ?>)
                </a>
                <a href="<?php echo home_url('/event-manager-dashboard/workshop-scanner?workshop_id=' . $workshop_id); ?>" class="btn btn-success">
                    <i class="fa fa-qrcode"></i> Scanner
                </a>
                <a href="<?php echo home_url('/workshop/' . $workshop->slug . '/'); ?>" target="_blank" class="btn btn-outline-secondary">
                    <i class="fa fa-eye"></i> View
                </a>
            </div>
        </div>
    </div>

    <form id="workshop-form">
        <input type="hidden" name="action" value="sc_save_workshop">
        <input type="hidden" name="id" value="<?php echo $workshop_id; ?>">

        <div class="row">
            <div class="col-lg-8">
                <!-- Basic Info -->
                <div class="card">
                    <div class="header"><h2><i class="fa fa-info-circle"></i> Basic Information</h2></div>
                    <div class="body">
                        <div class="form-group">
                            <label>Parent Event <span class="text-danger">*</span></label>
                            <select class="form-control" name="event_id" required>
                                <?php foreach ($events as $ev): ?>
                                <option value="<?php echo (int) $ev->id; ?>" <?php selected((int) $ev->id, (int) $workshop->event_id); ?>><?php echo esc_html($ev->title); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Workshop Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="title" value="<?php echo esc_attr($workshop->title); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Slug</label>
                            <input type="text" class="form-control" name="slug" value="<?php echo esc_attr($workshop->slug); ?>">
                        </div>
                        <div class="form-group">
                            <label>Excerpt</label>
                            <textarea class="form-control" name="excerpt" rows="2"><?php echo esc_textarea($workshop->excerpt); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <?php
                            wp_editor($workshop->description, 'workshop_description', array(
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
                                <input type="date" class="form-control" name="start_date" value="<?php echo esc_attr($workshop->start_date); ?>" required>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>End Date</label>
                                <input type="date" class="form-control" name="end_date" value="<?php echo esc_attr($workshop->end_date); ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label>Start Time</label>
                                <input type="time" class="form-control" name="start_time" value="<?php echo esc_attr($workshop->start_time); ?>">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>End Time</label>
                                <input type="time" class="form-control" name="end_time" value="<?php echo esc_attr($workshop->end_time); ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Location Type</label>
                            <select class="form-control" name="location_type">
                                <option value="offline" <?php selected($workshop->location_type, 'offline'); ?>>In-person</option>
                                <option value="online" <?php selected($workshop->location_type, 'online'); ?>>Online</option>
                                <option value="hybrid" <?php selected($workshop->location_type, 'hybrid'); ?>>Hybrid</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Venue Name</label>
                            <input type="text" class="form-control" name="venue_name" value="<?php echo esc_attr($workshop->venue_name); ?>">
                        </div>
                        <div class="form-group">
                            <label>Venue Address</label>
                            <textarea class="form-control" name="venue_address" rows="2"><?php echo esc_textarea($workshop->venue_address); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Online Meeting Link</label>
                            <input type="url" class="form-control" name="meeting_link" value="<?php echo esc_attr($workshop->meeting_link); ?>" placeholder="https://...">
                        </div>
                    </div>
                </div>

                <!-- Capacity -->
                <div class="card">
                    <div class="header"><h2><i class="fa fa-users"></i> Capacity</h2></div>
                    <div class="body">
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label>Total Capacity (0 = unlimited)</label>
                                <input type="number" class="form-control" name="total_capacity" value="<?php echo (int) $workshop->total_capacity; ?>" min="0">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Registration Deadline</label>
                                <input type="datetime-local" class="form-control" name="registration_deadline" value="<?php echo $workshop->registration_deadline ? esc_attr(date('Y-m-d\TH:i', strtotime($workshop->registration_deadline))) : ''; ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label>Min Tickets per Order</label>
                                <input type="number" class="form-control" name="min_tickets_per_order" value="<?php echo (int) $workshop->min_tickets_per_order; ?>" min="1">
                            </div>
                            <div class="col-md-6 form-group">
                                <label>Max Tickets per Order</label>
                                <input type="number" class="form-control" name="max_tickets_per_order" value="<?php echo (int) $workshop->max_tickets_per_order; ?>" min="1">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tickets Management -->
                <div class="card">
                    <div class="header">
                        <h2><i class="fa fa-ticket"></i> Tickets</h2>
                        <ul class="header-dropdown">
                            <li><button type="button" class="btn btn-sm btn-primary" id="add-ticket-btn"><i class="fa fa-plus"></i> Add Ticket</button></li>
                        </ul>
                    </div>
                    <div class="body">
                        <div id="workshop-tickets-list">
                            <?php if (empty($tickets)): ?>
                                <p class="text-muted text-center py-3">No tickets yet. Click "Add Ticket" to create one.</p>
                            <?php else: foreach ($tickets as $t): ?>
                                <div class="workshop-ticket-row card mb-2" data-ticket-id="<?php echo (int) $t->id; ?>">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-md-4">
                                                <strong><?php echo esc_html($t->name); ?></strong><br>
                                                <small class="text-muted"><?php echo esc_html($t->description); ?></small>
                                            </div>
                                            <div class="col-md-2 text-center">
                                                <span class="badge badge-info"><?php echo number_format((float) $t->price, 2); ?></span>
                                            </div>
                                            <div class="col-md-3 text-center">
                                                Sold: <strong><?php echo (int) $t->sold; ?></strong> / <?php echo (int) $t->quantity ?: '∞'; ?>
                                            </div>
                                            <div class="col-md-3 text-right">
                                                <button type="button" class="btn btn-sm btn-primary edit-ticket" data-id="<?php echo (int) $t->id; ?>"><i class="fa fa-edit"></i></button>
                                                <button type="button" class="btn btn-sm btn-danger delete-ticket" data-id="<?php echo (int) $t->id; ?>"><i class="fa fa-trash"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Certificates -->
                <div class="card">
                    <div class="header"><h2><i class="fa fa-certificate"></i> Certificates</h2></div>
                    <div class="body">
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="enable_certificates" value="1" <?php checked($workshop->enable_certificates, 1); ?>> Enable certificates
                            </label>
                        </div>
                        <?php if (!empty($cert_templates)): ?>
                        <div class="form-group">
                            <label>Certificate Template</label>
                            <select class="form-control" name="certificate_template_id">
                                <option value="">-- Select --</option>
                                <?php foreach ($cert_templates as $tpl): ?>
                                <option value="<?php echo (int) $tpl->id; ?>" <?php selected((int) $workshop->certificate_template_id, (int) $tpl->id); ?>><?php echo esc_html($tpl->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="auto_issue_certificate" value="1" <?php checked($workshop->auto_issue_certificate, 1); ?>> Auto-issue after check-in
                            </label>
                        </div>
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="certificate_require_checkin" value="1" <?php checked($workshop->certificate_require_checkin, 1); ?>> Require check-in
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="header"><h2>Status</h2></div>
                    <div class="body">
                        <div class="form-group">
                            <select class="form-control" name="status">
                                <option value="draft" <?php selected($workshop->status, 'draft'); ?>>Draft</option>
                                <option value="publish" <?php selected($workshop->status, 'publish'); ?>>Published</option>
                                <option value="private" <?php selected($workshop->status, 'private'); ?>>Private</option>
                                <option value="completed" <?php selected($workshop->status, 'completed'); ?>>Completed</option>
                                <option value="cancelled" <?php selected($workshop->status, 'cancelled'); ?>>Cancelled</option>
                                <option value="disabled" <?php selected($workshop->status, 'disabled'); ?>>Disabled</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg btn-block" id="save-workshop-btn">
                            <i class="fa fa-save"></i> Update Workshop
                        </button>
                    </div>
                </div>

                <div class="card">
                    <div class="header"><h2>Statistics</h2></div>
                    <div class="body">
                        <p>Sold: <strong><?php echo (int) $workshop->total_sold; ?></strong></p>
                        <p>Revenue: <strong><?php echo number_format((float) $workshop->total_revenue, 2); ?></strong></p>
                        <p>Checked In: <strong><?php echo (int) $workshop->total_checked_in; ?></strong></p>
                    </div>
                </div>

                <div class="card">
                    <div class="header"><h2>Featured Image</h2></div>
                    <div class="body">
                        <input type="hidden" name="featured_image" id="featured-image-id" value="<?php echo (int) $workshop->featured_image; ?>">
                        <button type="button" class="btn btn-outline-primary btn-block" id="pick-featured-image">
                            <i class="fa fa-image"></i> Choose Image
                        </button>
                        <div id="featured-image-preview" style="margin-top:10px;">
                            <?php if ($featured_url): ?><img src="<?php echo esc_url($featured_url); ?>" style="max-width:100%;border-radius:6px;"><?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="header"><h2>Banner Image</h2></div>
                    <div class="body">
                        <input type="hidden" name="banner_image" id="banner-image-id" value="<?php echo (int) $workshop->banner_image; ?>">
                        <button type="button" class="btn btn-outline-primary btn-block" id="pick-banner-image">
                            <i class="fa fa-image"></i> Choose Image
                        </button>
                        <div id="banner-image-preview" style="margin-top:10px;">
                            <?php if ($banner_url): ?><img src="<?php echo esc_url($banner_url); ?>" style="max-width:100%;border-radius:6px;"><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Ticket Modal -->
<div class="modal fade" id="ticketModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="ticket-modal-title"><i class="fa fa-ticket"></i> Add Ticket</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="ticket-form">
                <div class="modal-body">
                    <input type="hidden" id="ticket-id" name="ticket_id" value="">
                    <input type="hidden" name="workshop_id" value="<?php echo (int) $workshop_id; ?>">
                    <input type="hidden" name="event_id" value="<?php echo (int) $workshop->event_id; ?>">

                    <div class="form-group">
                        <label>Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="ticket-name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea class="form-control" id="ticket-description" name="description" rows="2"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Price (0 = Free) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" class="form-control" id="ticket-price" name="price" value="0" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Quantity (0 = unlimited)</label>
                            <input type="number" min="0" class="form-control" id="ticket-quantity" name="quantity" value="0">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Min per Order</label>
                            <input type="number" min="1" class="form-control" id="ticket-min" name="min_per_order" value="1">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Max per Order</label>
                            <input type="number" min="1" class="form-control" id="ticket-max" name="max_per_order" value="10">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="ticket-active" name="is_active" value="1" checked> Active
                        </label>
                        <label class="ml-3">
                            <input type="checkbox" id="ticket-coupons" name="enable_coupons" value="1" checked> Enable coupons
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="save-ticket-btn"><i class="fa fa-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    'use strict';

    // ============= Media pickers =============
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

    // ============= Save workshop =============
    $('#workshop-form').on('submit', function(e) {
        e.preventDefault();
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
                $btn.prop('disabled', false).html('<i class="fa fa-save"></i> Update Workshop');
            } else {
                alert(resp.data.message || 'Failed');
                $btn.prop('disabled', false).html('<i class="fa fa-save"></i> Update Workshop');
            }
        }).fail(function() {
            alert('Connection error');
            $btn.prop('disabled', false).html('<i class="fa fa-save"></i> Update Workshop');
        });
    });

    // ============= Tickets =============
    function showTicketModal() {
        try { $('#ticketModal').modal('show'); } catch(e) {
            $('#ticketModal').addClass('show').css('display', 'block');
            $('body').addClass('modal-open').append('<div class="modal-backdrop fade show"></div>');
        }
    }
    function hideTicketModal() {
        try { $('#ticketModal').modal('hide'); } catch(e) {
            $('#ticketModal').removeClass('show').css('display', 'none');
            $('body').removeClass('modal-open');
            $('.modal-backdrop').remove();
        }
    }

    $('#add-ticket-btn').on('click', function() {
        $('#ticket-modal-title').html('<i class="fa fa-plus"></i> Add Ticket');
        $('#ticket-form')[0].reset();
        $('#ticket-id').val('');
        $('#ticket-active').prop('checked', true);
        $('#ticket-coupons').prop('checked', true);
        showTicketModal();
    });

    $(document).on('click', '.edit-ticket', function() {
        const id = $(this).data('id');
        $.ajax({
            url: scDashboard.ajaxurl, type: 'POST',
            data: { action: 'sc_get_ticket', nonce: scDashboard.nonce, ticket_id: id }
        }).done(function(resp) {
            if (resp.success) {
                const t = resp.data.ticket;
                $('#ticket-modal-title').html('<i class="fa fa-edit"></i> Edit Ticket');
                $('#ticket-id').val(t.id);
                $('#ticket-name').val(t.name);
                $('#ticket-description').val(t.description);
                $('#ticket-price').val(t.price);
                $('#ticket-quantity').val(t.quantity);
                $('#ticket-min').val(t.min_per_order);
                $('#ticket-max').val(t.max_per_order);
                $('#ticket-active').prop('checked', parseInt(t.is_active) === 1);
                $('#ticket-coupons').prop('checked', parseInt(t.enable_coupons) === 1);
                showTicketModal();
            }
        });
    });

    $('#ticket-form').on('submit', function(e) {
        e.preventDefault();
        const $btn = $('#save-ticket-btn');
        $btn.prop('disabled', true);

        $.ajax({
            url: scDashboard.ajaxurl, type: 'POST',
            data: $(this).serialize() + '&action=sc_save_workshop_ticket&nonce=' + scDashboard.nonce
        }).done(function(resp) {
            if (resp.success) {
                hideTicketModal();
                location.reload();
            } else {
                alert(resp.data.message || 'Failed');
                $btn.prop('disabled', false);
            }
        });
    });

    $(document).on('click', '.delete-ticket', function() {
        if (!confirm('Delete this ticket?')) return;
        const id = $(this).data('id');
        $.ajax({
            url: scDashboard.ajaxurl, type: 'POST',
            data: { action: 'sc_delete_workshop_ticket', nonce: scDashboard.nonce, ticket_id: id }
        }).done(function(resp) {
            if (resp.success) location.reload();
            else alert(resp.data.message || 'Failed');
        });
    });

    $('[data-dismiss="modal"]').on('click', function() {
        hideTicketModal();
    });
});
</script>

</div>
</div>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
