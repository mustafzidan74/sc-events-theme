<?php
/**
 * Speakers Management Page
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check permissions
if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

$page_title = sc_t('dashboard_pages.speakers_management', 'Speakers Management');
get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');

// Get all events for the filter dropdown from sc_events custom table
global $wpdb;
$sc_events_table = $wpdb->prefix . 'sc_events';
$events = $wpdb->get_results("SELECT id, title FROM $sc_events_table WHERE status = 'publish' ORDER BY title ASC");
?>

<div id="main-content">
<div class="container-fluid">
    <div class="block-header">
        <div class="row">
            <div class="col-lg-6 col-md-6 col-sm-12">
                <h2><?php echo esc_html(sc_t('dashboard_pages.speakers_management', 'Speakers Management')); ?></h2>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/home'); ?>"><i class="fa fa-dashboard"></i></a></li>
                    <li class="breadcrumb-item active"><?php echo esc_html(sc_t('nav.speakers', 'Speakers')); ?></li>
                </ul>
            </div>
            <div class="col-lg-6 col-md-6 col-sm-12">
                <div class="d-flex flex-row-reverse">
                    <div class="page_action">
                        <a href="<?php echo home_url('/event-manager-dashboard/speaker-create'); ?>" class="btn btn-primary">
                            <i class="fa fa-plus"></i> <?php echo esc_html(sc_t('dashboard_pages.add_speaker', 'Add Speaker')); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search and Filter -->
    <div class="row mb-3">
        <div class="col-md-6">
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-search"></i></span>
                </div>
                <input type="text" class="form-control" id="speaker-search" placeholder="<?php echo esc_attr(sc_t('dashboard_pages.search_speakers', 'Search by name or title/role...')); ?>">
            </div>
        </div>
        <div class="col-md-6">
            <select class="form-control" id="event-filter">
                <option value=""><?php echo esc_html(sc_t('dashboard_pages.all_events', 'All Events')); ?></option>
                <?php foreach ($events as $event): ?>
                    <option value="<?php echo $event->id; ?>"><?php echo esc_html($event->title); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Speakers List -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="speakers-table">
                            <thead>
                                <tr>
                                    <th width="60"><?php echo esc_html(sc_t('dashboard_pages.speaker_photo', 'Photo')); ?></th>
                                    <th><?php echo esc_html(sc_t('general.name', 'Name')); ?></th>
                                    <th><?php echo esc_html(sc_t('dashboard_pages.speaker_title', 'Title/Role')); ?></th>
                                    <th><?php echo esc_html(sc_t('general.email', 'Email')); ?></th>
                                    <th><?php echo esc_html(sc_t('dashboard_pages.social_links', 'Social Links')); ?></th>
                                    <th><?php echo esc_html(sc_t('nav.events', 'Events')); ?></th>
                                    <th width="150"><?php echo esc_html(sc_t('dashboard_pages.actions', 'Actions')); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <i class="fa fa-spinner fa-spin fa-3x text-muted"></i>
                                        <p class="mt-3"><?php echo esc_html(sc_t('dashboard_pages.loading', 'Loading speakers...')); ?></p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <div class="pagination-info">
                                <span id="pagination-info-text"><?php echo esc_html(sc_t('dashboard_pages.loading', 'Loading...')); ?></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <nav aria-label="Speakers pagination">
                                <ul class="pagination justify-content-end mb-0" id="speakers-pagination">
                                    <!-- Will be populated by JavaScript -->
                                </ul>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// JavaScript Translations Object
var speakersTranslations = {
    loading_speakers: '<?php echo esc_js(sc_t('dashboard_pages.loading_speakers', 'Loading speakers...')); ?>',
    error_loading_speakers: '<?php echo esc_js(sc_t('dashboard_pages.error_loading_speakers', 'Error loading speakers')); ?>',
    failed_load_speakers: '<?php echo esc_js(sc_t('dashboard_pages.failed_load_speakers', 'Failed to load speakers. Please try again.')); ?>',
    no_speakers_found: '<?php echo esc_js(sc_t('dashboard_pages.no_speakers_found', 'No speakers found. Click "Add Speaker" to create one.')); ?>',
    events_label: '<?php echo esc_js(sc_t('nav.events', 'events')); ?>',
    showing_speakers: '<?php echo esc_js(sc_t('dashboard_pages.showing_to_of', 'Showing {start} to {end} of {total} speakers')); ?>',
    upload_image_required: '<?php echo esc_js(sc_t('dashboard_pages.upload_image_required', 'Please upload a profile image.')); ?>',
    saving: '<?php echo esc_js(sc_t('dashboard_pages.saving', 'Saving...')); ?>',
    error_saving_speaker: '<?php echo esc_js(sc_t('dashboard_pages.error_saving_speaker', 'Error saving speaker')); ?>',
    save_speaker: '<?php echo esc_js(sc_t('dashboard_pages.save_speaker', 'Save Speaker')); ?>',
    error_deleting_speaker: '<?php echo esc_js(sc_t('dashboard_pages.error_deleting_speaker', 'Error deleting speaker')); ?>'
};

jQuery(document).ready(function($) {
    'use strict';

    let currentPage = 1;
    let currentSearch = '';
    let currentEventId = '';
    let searchTimeout;

    // Escape HTML helper function
    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // Load speakers with pagination
    function loadSpeakersPage(page) {
        currentPage = page;

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_get_speakers_paginated',
                nonce: scDashboard.nonce,
                page: page,
                per_page: 50,
                search: currentSearch,
                event_id: currentEventId
            },
            beforeSend: function() {
                const tbody = $('#speakers-table tbody');
                tbody.html('<tr><td colspan="7" class="text-center py-5"><i class="fa fa-spinner fa-spin fa-3x text-muted"></i><p class="mt-3">' + speakersTranslations.loading_speakers + '</p></td></tr>');
            }
        }).done(function(response) {
            if (response.success) {
                renderSpeakersTable(response.data.speakers);
                renderPagination(response.data.current_page, response.data.pages);
                updatePaginationInfo(response.data.total, response.data.current_page, 50);
            } else {
                showError(response.data.message || speakersTranslations.error_loading_speakers);
            }
        }).fail(function() {
            showError(speakersTranslations.failed_load_speakers);
        });
    }

    function renderSpeakersTable(speakers) {
        const tbody = $('#speakers-table tbody');
        tbody.empty();

        if (!speakers || speakers.length === 0) {
            tbody.html('<tr><td colspan="7" class="text-center py-4">' + speakersTranslations.no_speakers_found + '</td></tr>');
            return;
        }

        speakers.forEach(function(speaker) {
            const imageUrl = speaker.image_url || scDashboard.themeUrl + '/assets/images/default-avatar.png';
            const socialLinks = [];

            // Add all 15 social media platforms
            if (speaker.facebook) socialLinks.push('<a href="' + speaker.facebook + '" target="_blank" title="Facebook"><i class="fa fa-facebook"></i></a>');
            if (speaker.twitter) socialLinks.push('<a href="' + speaker.twitter + '" target="_blank" title="Twitter"><i class="fa fa-twitter"></i></a>');
            if (speaker.linkedin) socialLinks.push('<a href="' + speaker.linkedin + '" target="_blank" title="LinkedIn"><i class="fa fa-linkedin"></i></a>');
            if (speaker.instagram) socialLinks.push('<a href="' + speaker.instagram + '" target="_blank" title="Instagram"><i class="fa fa-instagram"></i></a>');
            if (speaker.youtube) socialLinks.push('<a href="' + speaker.youtube + '" target="_blank" title="YouTube"><i class="fa fa-youtube"></i></a>');
            if (speaker.github) socialLinks.push('<a href="' + speaker.github + '" target="_blank" title="GitHub"><i class="fa fa-github"></i></a>');
            if (speaker.tiktok) socialLinks.push('<a href="' + speaker.tiktok + '" target="_blank" title="TikTok"><i class="fa fa-video-camera"></i></a>');
            if (speaker.snapchat) socialLinks.push('<a href="' + speaker.snapchat + '" target="_blank" title="Snapchat"><i class="fa fa-snapchat"></i></a>');
            if (speaker.whatsapp) socialLinks.push('<a href="' + speaker.whatsapp + '" target="_blank" title="WhatsApp"><i class="fa fa-whatsapp"></i></a>');
            if (speaker.pinterest) socialLinks.push('<a href="' + speaker.pinterest + '" target="_blank" title="Pinterest"><i class="fa fa-pinterest"></i></a>');
            if (speaker.tumblr) socialLinks.push('<a href="' + speaker.tumblr + '" target="_blank" title="Tumblr"><i class="fa fa-tumblr"></i></a>');
            if (speaker.reddit) socialLinks.push('<a href="' + speaker.reddit + '" target="_blank" title="Reddit"><i class="fa fa-reddit"></i></a>');
            if (speaker.medium) socialLinks.push('<a href="' + speaker.medium + '" target="_blank" title="Medium"><i class="fa fa-medium"></i></a>');
            if (speaker.vimeo) socialLinks.push('<a href="' + speaker.vimeo + '" target="_blank" title="Vimeo"><i class="fa fa-vimeo"></i></a>');
            if (speaker.website) socialLinks.push('<a href="' + speaker.website + '" target="_blank" title="Website"><i class="fa fa-globe"></i></a>');

            const row = `
                <tr>
                    <td><img src="${imageUrl}" class="rounded-circle" width="40" height="40"></td>
                    <td><strong>${escapeHtml(speaker.name)}</strong></td>
                    <td>${escapeHtml(speaker.title || '-')}</td>
                    <td>${escapeHtml(speaker.email || '-')}</td>
                    <td>${socialLinks.join(' ') || '-'}</td>
                    <td><span class="badge badge-info">${speaker.events_count || 0} ${speakersTranslations.events_label}</span></td>
                    <td>
                        <div class="btn-group">
                            <a href="<?php echo home_url('/event-manager-dashboard/speaker-edit'); ?>?id=${speaker.ID}" class="btn btn-sm btn-primary" title="<?php echo esc_attr(sc_t('dashboard_pages.edit', 'Edit')); ?>">
                                <i class="fa fa-edit"></i>
                            </a>
                            <button type="button" class="btn btn-sm btn-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="sr-only">Toggle Dropdown</span>
                            </button>
                            <div class="dropdown-menu dropdown-menu-right">
                                <a class="dropdown-item" href="<?php echo home_url('/event-manager-dashboard/speaker-edit'); ?>?id=${speaker.ID}"><i class="fa fa-edit mr-2"></i> <?php echo esc_js(sc_t('dashboard_pages.edit', 'Edit')); ?></a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger delete-speaker" href="javascript:void(0);" data-id="${speaker.ID}"><i class="fa fa-trash mr-2"></i> <?php echo esc_js(sc_t('dashboard_pages.delete', 'Delete')); ?></a>
                            </div>
                        </div>
                    </td>
                </tr>
            `;
            tbody.append(row);
        });
    }

    // Render pagination controls
    function renderPagination(current, total) {
        const pagination = $('#speakers-pagination');
        pagination.empty();

        if (total <= 1) return;

        const maxVisible = 7; // Maximum page numbers to show
        let startPage = Math.max(1, current - Math.floor(maxVisible / 2));
        let endPage = Math.min(total, startPage + maxVisible - 1);

        // Adjust start if we're near the end
        if (endPage - startPage < maxVisible - 1) {
            startPage = Math.max(1, endPage - maxVisible + 1);
        }

        // Previous button
        pagination.append(`
            <li class="page-item ${current === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${current - 1}">
                    <i class="fa fa-chevron-left"></i>
                </a>
            </li>
        `);

        // First page + ellipsis
        if (startPage > 1) {
            pagination.append(`
                <li class="page-item">
                    <a class="page-link" href="#" data-page="1">1</a>
                </li>
            `);
            if (startPage > 2) {
                pagination.append(`<li class="page-item disabled"><span class="page-link">...</span></li>`);
            }
        }

        // Page numbers
        for (let i = startPage; i <= endPage; i++) {
            pagination.append(`
                <li class="page-item ${i === current ? 'active' : ''}">
                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                </li>
            `);
        }

        // Last page + ellipsis
        if (endPage < total) {
            if (endPage < total - 1) {
                pagination.append(`<li class="page-item disabled"><span class="page-link">...</span></li>`);
            }
            pagination.append(`
                <li class="page-item">
                    <a class="page-link" href="#" data-page="${total}">${total}</a>
                </li>
            `);
        }

        // Next button
        pagination.append(`
            <li class="page-item ${current === total ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${current + 1}">
                    <i class="fa fa-chevron-right"></i>
                </a>
            </li>
        `);

        // Attach click handlers
        pagination.find('a.page-link').on('click', function(e) {
            e.preventDefault();
            const page = parseInt($(this).data('page'));
            if (page && page !== current) {
                loadSpeakersPage(page);
            }
        });
    }

    // Update pagination info text
    function updatePaginationInfo(total, page, perPage) {
        const start = total === 0 ? 0 : ((page - 1) * perPage) + 1;
        const end = Math.min(page * perPage, total);
        const text = speakersTranslations.showing_speakers.replace('{start}', start).replace('{end}', end).replace('{total}', total);
        $('#pagination-info-text').text(text);
    }

    // Note: Create and Edit speaker buttons are now links to speaker-create and speaker-edit pages

    // Delete speaker
    $(document).on('click', '.delete-speaker', function() {
        showDeleteConfirm().then((result) => {
            if (!result.isConfirmed) {
                return;
            }

            const speakerId = $(this).data('id');
            const btn = $(this);
            btn.prop('disabled', true);

            $.ajax({
                url: scDashboard.ajaxurl,
                type: 'POST',
                data: {
                    action: 'sc_delete_speaker',
                    nonce: scDashboard.nonce,
                    speaker_id: speakerId
                }
            }).done(function(response) {
                if (response.success) {
                    loadSpeakersPage(currentPage);
                } else {
                    showError(response.data.message || speakersTranslations.error_deleting_speaker);
                    btn.prop('disabled', false);
                }
            });
        });
    });

    // Save speaker
    $('#speaker-form').on('submit', function(e) {
        e.preventDefault();

        // Check if image is uploaded (for new speakers) or exists (for editing)
        const hasImage = $('#speaker-has-image').val() === '1';
        const imageFile = $('#speaker-image')[0].files[0];

        if (!hasImage && !imageFile) {
            showError(speakersTranslations.upload_image_required);
            return false;
        }

        const formData = new FormData(this);
        formData.append('action', 'sc_save_speaker');
        formData.append('nonce', scDashboard.nonce);

        const btn = $('#save-speaker-btn');
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> ' + speakersTranslations.saving);

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false
        }).done(function(response) {
            if (response.success) {
                // Close modal with fallback
                try {
                    if (typeof $.fn.modal !== 'undefined') {
                        $('#speakerModal').modal('hide');
                    } else {
                        $('#speakerModal').removeClass('show').css('display', 'none');
                        $('body').removeClass('modal-open');
                        $('.modal-backdrop').remove();
                    }
                } catch (e) {
                    $('#speakerModal').removeClass('show').css('display', 'none');
                    $('body').removeClass('modal-open');
                    $('.modal-backdrop').remove();
                }
                loadSpeakersPage(currentPage);
            } else {
                showError(response.data.message || speakersTranslations.error_saving_speaker);
            }
        }).always(function() {
            btn.prop('disabled', false).html('<i class="fa fa-save"></i> ' + speakersTranslations.save_speaker);
        });
    });

    // Image preview
    $('#speaker-image').on('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#speaker-image-preview img').attr('src', e.target.result);
                $('#speaker-image-preview').show();
            };
            reader.readAsDataURL(file);
            $('.custom-file-label').text(file.name);
        }
    });

    // Close modal handler
    $('[data-dismiss="modal"]').on('click', function() {
        const modal = $(this).closest('.modal');
        try {
            if (typeof $.fn.modal !== 'undefined') {
                modal.modal('hide');
            } else {
                modal.removeClass('show').css('display', 'none');
                $('body').removeClass('modal-open');
                $('.modal-backdrop').remove();
            }
        } catch (e) {
            modal.removeClass('show').css('display', 'none');
            $('body').removeClass('modal-open');
            $('.modal-backdrop').remove();
        }
    });

    // Search functionality with debounce
    $('#speaker-search').on('input', function() {
        clearTimeout(searchTimeout);
        const searchValue = $(this).val().trim();

        searchTimeout = setTimeout(function() {
            currentSearch = searchValue;
            loadSpeakersPage(1); // Reset to page 1 on search
        }, 500); // Wait 500ms after user stops typing
    });

    // Event filter
    $('#event-filter').on('change', function() {
        currentEventId = $(this).val();
        loadSpeakersPage(1); // Reset to page 1 on filter change
    });

    // Initial load
    loadSpeakersPage(1);
});
</script>

</div>
</div>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
