<?php
/**
 * Partners Management Page
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

$page_title = sc_t('dashboard_pages.partners_management', 'Partners Management');
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
                <h2><?php echo esc_html(sc_t('dashboard_pages.partners_management', 'Partners Management')); ?></h2>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/home'); ?>"><i class="fa fa-dashboard"></i></a></li>
                    <li class="breadcrumb-item active"><?php echo esc_html(sc_t('nav.partners', 'Partners')); ?></li>
                </ul>
            </div>
            <div class="col-lg-6 col-md-6 col-sm-12">
                <div class="d-flex flex-row-reverse">
                    <div class="page_action">
                        <a href="<?php echo home_url('/event-manager-dashboard/partner-create'); ?>" class="btn btn-primary">
                            <i class="fa fa-plus"></i> <?php echo esc_html(sc_t('dashboard_pages.add_partner', 'Add Partner')); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search and Filter -->
    <div class="row mb-3">
        <div class="col-md-4">
            <div class="input-group">
                <div class="input-group-prepend">
                    <span class="input-group-text"><i class="fa fa-search"></i></span>
                </div>
                <input type="text" class="form-control" id="partner-search" placeholder="<?php echo esc_attr(sc_t('dashboard_pages.search_partners', 'Search by name or email...')); ?>">
            </div>
        </div>
        <div class="col-md-4">
            <select class="form-control" id="event-filter">
                <option value=""><?php echo esc_html(sc_t('dashboard_pages.all_events', 'All Events')); ?></option>
                <?php foreach ($events as $event): ?>
                    <option value="<?php echo esc_attr($event->id); ?>"><?php echo esc_html($event->title); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <select class="form-control" id="tier-filter">
                <option value=""><?php echo esc_html(sc_t('dashboard_pages.all_tiers', 'All Tiers')); ?></option>
                <option value="platinum"><?php echo esc_html(sc_t('dashboard_pages.tier_platinum', 'Platinum')); ?></option>
                <option value="gold"><?php echo esc_html(sc_t('dashboard_pages.tier_gold', 'Gold')); ?></option>
                <option value="silver"><?php echo esc_html(sc_t('dashboard_pages.tier_silver', 'Silver')); ?></option>
                <option value="bronze"><?php echo esc_html(sc_t('dashboard_pages.tier_bronze', 'Bronze')); ?></option>
            </select>
        </div>
    </div>

    <!-- Partners List -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="partners-table">
                            <thead>
                                <tr>
                                    <th width="60"><?php echo esc_html(sc_t('dashboard_pages.partner_logo', 'Logo')); ?></th>
                                    <th><?php echo esc_html(sc_t('general.name', 'Name')); ?></th>
                                    <th><?php echo esc_html(sc_t('dashboard_pages.partner_tier', 'Tier')); ?></th>
                                    <th><?php echo esc_html(sc_t('dashboard_pages.website', 'Website')); ?></th>
                                    <th><?php echo esc_html(sc_t('general.email', 'Email')); ?></th>
                                    <th><?php echo esc_html(sc_t('nav.events', 'Events')); ?></th>
                                    <th width="150"><?php echo esc_html(sc_t('dashboard_pages.actions', 'Actions')); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <i class="fa fa-spinner fa-spin fa-3x text-muted"></i>
                                        <p class="mt-3"><?php echo esc_html(sc_t('dashboard_pages.loading', 'Loading partners...')); ?></p>
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
                            <nav aria-label="Partners pagination">
                                <ul class="pagination justify-content-end mb-0" id="partners-pagination">
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
var partnersTranslations = {
    loading_partners: '<?php echo esc_js(sc_t('dashboard_pages.loading_partners', 'Loading partners...')); ?>',
    error_loading_partners: '<?php echo esc_js(sc_t('dashboard_pages.error_loading_partners', 'Error loading partners')); ?>',
    failed_load_partners: '<?php echo esc_js(sc_t('dashboard_pages.failed_load_partners', 'Failed to load partners. Please try again.')); ?>',
    no_partners_found: '<?php echo esc_js(sc_t('dashboard_pages.no_partners_found', 'No partners found. Click "Add Partner" to create one.')); ?>',
    events_label: '<?php echo esc_js(sc_t('nav.events', 'events')); ?>',
    showing_partners: '<?php echo esc_js(sc_t('dashboard_pages.showing_to_of_partners', 'Showing {start} to {end} of {total} partners')); ?>',
    error_deleting_partner: '<?php echo esc_js(sc_t('dashboard_pages.error_deleting_partner', 'Error deleting partner')); ?>',
    visit_website: '<?php echo esc_js(sc_t('dashboard_pages.visit_website', 'Visit Website')); ?>'
};

jQuery(document).ready(function($) {
    'use strict';

    let currentPage = 1;
    let currentSearch = '';
    let currentEventId = '';
    let currentTier = '';
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

    // Get tier badge style
    function getTierBadge(tier) {
        var styles = {
            'platinum': 'background:#8B5CF6; color:#fff',
            'gold': 'background:#F59E0B; color:#fff',
            'silver': 'background:#6B7280; color:#fff',
            'bronze': 'background:#CD7F32; color:#fff'
        };
        var style = styles[tier] || 'background:#6B7280; color:#fff';
        var label = tier ? tier.charAt(0).toUpperCase() + tier.slice(1) : '-';
        return '<span class="badge" style="' + style + '">' + escapeHtml(label) + '</span>';
    }

    // Get logo HTML
    function getLogoHtml(partner) {
        if (partner.logo_url) {
            return '<img src="' + escapeHtml(partner.logo_url) + '" class="rounded" width="40" height="40" style="object-fit:cover;">';
        }
        var letter = partner.name ? partner.name.charAt(0).toUpperCase() : '?';
        var colors = ['#8B5CF6', '#F59E0B', '#10B981', '#3B82F6', '#EF4444', '#EC4899'];
        var colorIndex = letter.charCodeAt(0) % colors.length;
        return '<div style="width:40px;height:40px;border-radius:50%;background:' + colors[colorIndex] + ';color:#fff;display:flex;align-items:center;justify-content:center;font-weight:bold;font-size:16px;">' + escapeHtml(letter) + '</div>';
    }

    // Load partners with pagination
    function loadPartnersPage(page) {
        currentPage = page;

        $.ajax({
            url: scDashboard.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_get_partners_paginated',
                nonce: scDashboard.nonce,
                page: page,
                per_page: 50,
                search: currentSearch,
                event_id: currentEventId,
                tier: currentTier
            },
            beforeSend: function() {
                const tbody = $('#partners-table tbody');
                tbody.html('<tr><td colspan="7" class="text-center py-5"><i class="fa fa-spinner fa-spin fa-3x text-muted"></i><p class="mt-3">' + partnersTranslations.loading_partners + '</p></td></tr>');
            }
        }).done(function(response) {
            if (response.success) {
                renderPartnersTable(response.data.partners);
                renderPagination(response.data.current_page, response.data.pages);
                updatePaginationInfo(response.data.total, response.data.current_page, 50);
            } else {
                showError(response.data.message || partnersTranslations.error_loading_partners);
            }
        }).fail(function() {
            showError(partnersTranslations.failed_load_partners);
        });
    }

    function renderPartnersTable(partners) {
        const tbody = $('#partners-table tbody');
        tbody.empty();

        if (!partners || partners.length === 0) {
            tbody.html('<tr><td colspan="7" class="text-center py-4">' + partnersTranslations.no_partners_found + '</td></tr>');
            return;
        }

        partners.forEach(function(partner) {
            var logoHtml = getLogoHtml(partner);
            var tierBadge = getTierBadge(partner.tier);

            var websiteHtml = '-';
            if (partner.website) {
                websiteHtml = '<a href="' + escapeHtml(partner.website) + '" target="_blank" title="' + partnersTranslations.visit_website + '"><i class="fa fa-external-link"></i> ' + escapeHtml(partner.website.replace(/^https?:\/\//, '').substring(0, 30)) + '</a>';
            }

            const row = `
                <tr>
                    <td>${logoHtml}</td>
                    <td><strong>${escapeHtml(partner.name)}</strong></td>
                    <td>${tierBadge}</td>
                    <td>${websiteHtml}</td>
                    <td>${escapeHtml(partner.email || '-')}</td>
                    <td><span class="badge badge-info">${partner.events_count || 0} ${partnersTranslations.events_label}</span></td>
                    <td>
                        <div class="btn-group">
                            <a href="<?php echo home_url('/event-manager-dashboard/partner-edit'); ?>?id=${partner.id}" class="btn btn-sm btn-primary" title="<?php echo esc_attr(sc_t('dashboard_pages.edit', 'Edit')); ?>">
                                <i class="fa fa-edit"></i>
                            </a>
                            <button type="button" class="btn btn-sm btn-primary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="sr-only">Toggle Dropdown</span>
                            </button>
                            <div class="dropdown-menu dropdown-menu-right">
                                <a class="dropdown-item" href="<?php echo home_url('/event-manager-dashboard/partner-edit'); ?>?id=${partner.id}"><i class="fa fa-edit mr-2"></i> <?php echo esc_js(sc_t('dashboard_pages.edit', 'Edit')); ?></a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-danger delete-partner" href="javascript:void(0);" data-id="${partner.id}"><i class="fa fa-trash mr-2"></i> <?php echo esc_js(sc_t('dashboard_pages.delete', 'Delete')); ?></a>
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
        const pagination = $('#partners-pagination');
        pagination.empty();

        if (total <= 1) return;

        const maxVisible = 7;
        let startPage = Math.max(1, current - Math.floor(maxVisible / 2));
        let endPage = Math.min(total, startPage + maxVisible - 1);

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
                loadPartnersPage(page);
            }
        });
    }

    // Update pagination info text
    function updatePaginationInfo(total, page, perPage) {
        const start = total === 0 ? 0 : ((page - 1) * perPage) + 1;
        const end = Math.min(page * perPage, total);
        const text = partnersTranslations.showing_partners.replace('{start}', start).replace('{end}', end).replace('{total}', total);
        $('#pagination-info-text').text(text);
    }

    // Delete partner
    $(document).on('click', '.delete-partner', function() {
        showDeleteConfirm().then((result) => {
            if (!result.isConfirmed) {
                return;
            }

            const partnerId = $(this).data('id');
            const btn = $(this);
            btn.prop('disabled', true);

            $.ajax({
                url: scDashboard.ajaxurl,
                type: 'POST',
                data: {
                    action: 'sc_delete_partner',
                    nonce: scDashboard.nonce,
                    partner_id: partnerId
                }
            }).done(function(response) {
                if (response.success) {
                    loadPartnersPage(currentPage);
                } else {
                    showError(response.data.message || partnersTranslations.error_deleting_partner);
                    btn.prop('disabled', false);
                }
            });
        });
    });

    // Search functionality with debounce
    $('#partner-search').on('input', function() {
        clearTimeout(searchTimeout);
        const searchValue = $(this).val().trim();

        searchTimeout = setTimeout(function() {
            currentSearch = searchValue;
            loadPartnersPage(1);
        }, 500);
    });

    // Event filter
    $('#event-filter').on('change', function() {
        currentEventId = $(this).val();
        loadPartnersPage(1);
    });

    // Tier filter
    $('#tier-filter').on('change', function() {
        currentTier = $(this).val();
        loadPartnersPage(1);
    });

    // Initial load
    loadPartnersPage(1);
});
</script>

</div>
</div>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
