<?php
/**
 * Workshop form — shared by workshop-create.php and workshop-edit.php.
 *
 * Expects from the including page:
 *   $workshop        object|null  null when creating
 *   $events          SC_Event rows for the parent select
 *   $cert_templates  active certificate templates
 *   $tickets         the workshop's tickets (edit only)
 *   $stats           object {registered, checked_in, revenue} (edit only)
 *   $preselected_event_id  int (create only)
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

$is_edit = !empty($workshop);
$dashboard_url = home_url('/event-manager-dashboard/');
$w = $is_edit ? $workshop : (object) array(
    'id' => 0, 'event_id' => !empty($preselected_event_id) ? (int) $preselected_event_id : 0,
    'title' => '', 'slug' => '', 'excerpt' => '', 'description' => '',
    'featured_image' => 0, 'banner_image' => 0,
    'start_date' => '', 'end_date' => '', 'start_time' => '', 'end_time' => '',
    'location_type' => 'offline', 'venue_name' => '', 'venue_address' => '', 'meeting_link' => '',
    'total_capacity' => 0, 'registration_deadline' => '', 'min_tickets_per_order' => 1, 'max_tickets_per_order' => 1,
    'enable_certificates' => 0, 'certificate_template_id' => 0, 'auto_issue_certificate' => 0, 'certificate_require_checkin' => 1,
    'status' => 'draft', 'updated_at' => '',
);

$img = function ($id, $size = 'large') {
    return $id ? (wp_get_attachment_image_url((int) $id, $size) ?: '') : '';
};
$featured_url = $img($w->featured_image);
$banner_url = $img($w->banner_image);
$public_url = $w->slug ? home_url('/workshop/' . $w->slug . '/') : '';

$statuses = array(
    'publish'   => array(sc_t('dashboard_pages.status_published', 'Published'), sc_t('dashboard_pages.status_published_help', 'Visible on the site, open for registration'), 'var(--w-teal)'),
    'draft'     => array(sc_t('dashboard_pages.status_draft', 'Draft'), sc_t('dashboard_pages.status_draft_help', 'Hidden; only managers can preview it'), 'var(--w-text-3)'),
    'private'   => array(sc_t('dashboard_pages.status_private', 'Private'), sc_t('dashboard_pages.status_hidden_help', 'Hidden from the site'), 'var(--w-text-3)'),
    'completed' => array(sc_t('dashboard_pages.status_completed', 'Completed'), sc_t('dashboard_pages.status_completed_help', 'Still visible, shown as past'), 'var(--w-info)'),
    'cancelled' => array(sc_t('dashboard_pages.status_cancelled', 'Cancelled'), sc_t('dashboard_pages.status_hidden_help', 'Hidden from the site'), 'var(--w-danger)'),
    'disabled'  => array(sc_t('dashboard_pages.status_disabled', 'Disabled'), sc_t('dashboard_pages.status_hidden_help', 'Hidden from the site'), 'var(--w-text-3)'),
);

$sections = array(
    'basic'        => sc_t('dashboard_pages.section_basic', 'Basic information'),
    'images'       => sc_t('dashboard_pages.section_images', 'Images'),
    'schedule'     => sc_t('dashboard_pages.section_schedule', 'Date & location'),
    'capacity'     => sc_t('dashboard_pages.section_capacity', 'Capacity & registration'),
    'tickets'      => sc_t('dashboard_pages.section_tickets', 'Tickets'),
    'certificates' => sc_t('dashboard_pages.section_certificates', 'Certificates'),
);

$deadline_value = $w->registration_deadline ? date('Y-m-d\TH:i', strtotime($w->registration_deadline)) : '';
$time_value = function ($t) {
    return $t ? substr($t, 0, 5) : '';
};
$saved_label = $w->updated_at ? sprintf(sc_t('dashboard_pages.saved_at', 'Saved %s'), mysql2date('j M Y, H:i', $w->updated_at)) : '';
$capacity = (int) $w->total_capacity;
$registered = $is_edit ? (int) $stats->registered : 0;
$fill = $capacity > 0 ? min(100, round($registered / $capacity * 100)) : 0;

$js = function ($value) {
    return wp_json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
};
?>

<div id="main-content">
<div class="container-fluid">
<form id="workshop-form" novalidate>
    <input type="hidden" name="id" value="<?php echo (int) $w->id; ?>">

    <div class="w-form-head">
        <div>
            <span class="w-form-head__eyebrow">
                <?php if ($is_edit): ?>
                    <?php echo esc_html(sc_t('nav.workshops', 'Workshop')); ?> · ID <span class="w-ltr"><?php echo (int) $w->id; ?></span>
                <?php else: ?>
                    <?php echo esc_html(sc_t('dashboard_pages.new_workshop', 'New workshop')); ?>
                <?php endif; ?>
            </span>
            <h1 data-w-title><?php echo esc_html($w->title !== '' ? $w->title : sc_t('dashboard_pages.untitled_workshop', 'Untitled workshop')); ?></h1>
            <div class="w-form-head__meta">
                <?php if ($is_edit): ?><span data-w-saved><?php echo esc_html($saved_label); ?></span><?php endif; ?>
                <span class="w-dirty" data-w-dirty hidden><?php echo esc_html(sc_t('dashboard_pages.unsaved_changes', 'Unsaved changes')); ?></span>
            </div>
        </div>
        <div class="w-form-head__actions">
            <?php if ($is_edit): ?>
                <a class="btn btn-secondary" href="<?php echo esc_url($dashboard_url . 'workshop-attendees?workshop_id=' . (int) $w->id); ?>"><i class="fa fa-users" aria-hidden="true"></i> <?php echo esc_html(sc_t('nav.attendees', 'Attendees')); ?> <span class="w-ltr">(<?php echo (int) $registered; ?>)</span></a>
                <?php if ($public_url): ?>
                <a class="btn btn-secondary" href="<?php echo esc_url($public_url); ?>" target="_blank" rel="noopener" data-w-preview><i class="fa fa-external-link" aria-hidden="true"></i> <?php echo esc_html(sc_t('dashboard_pages.preview', 'Preview')); ?></a>
                <?php endif; ?>
            <?php else: ?>
                <a class="btn btn-secondary" href="<?php echo esc_url($dashboard_url . 'workshops'); ?>"><?php echo esc_html(sc_t('dashboard_pages.cancel', 'Cancel')); ?></a>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary w-save-head" data-w-save><?php echo esc_html($is_edit ? sc_t('dashboard_pages.save_changes', 'Save changes') : sc_t('dashboard_pages.create_workshop', 'Create workshop')); ?></button>
        </div>
    </div>

    <div class="w-errors" data-w-errors hidden></div>

    <div class="w-form-layout">
        <nav class="w-secnav" data-w-secnav aria-label="<?php echo esc_attr(sc_t('dashboard_pages.form_sections', 'Form sections')); ?>">
            <?php foreach ($sections as $key => $label): ?>
                <a href="#<?php echo esc_attr($key); ?>" aria-current="<?php echo $key === 'basic' ? 'true' : 'false'; ?>"><span><?php echo esc_html($label); ?></span><i class="w-secnav__warn" hidden></i></a>
            <?php endforeach; ?>
        </nav>

        <div class="w-form-main">

            <section class="w-section" id="basic" aria-labelledby="basic-title">
                <div class="w-section__head">
                    <h2 id="basic-title"><?php echo esc_html($sections['basic']); ?></h2>
                    <span class="w-section__hint"><?php echo esc_html(sc_t('dashboard_pages.basic_hint', 'Shown on the workshop page and its card')); ?></span>
                </div>
                <div class="w-fields">
                    <div class="w-field">
                        <label for="f-event"><?php echo esc_html(sc_t('dashboard_pages.parent_event', 'Parent event')); ?><span class="w-req" aria-hidden="true">*</span></label>
                        <select class="form-control" id="f-event" name="event_id" required>
                            <option value=""><?php echo esc_html(sc_t('dashboard_pages.choose_event', '— Choose the event —')); ?></option>
                            <?php foreach ($events as $ev): ?>
                                <option value="<?php echo (int) $ev->id; ?>" <?php selected((int) $ev->id, (int) $w->event_id); ?>><?php echo esc_html($ev->title); ?><?php echo $ev->start_date ? ' · ' . esc_html(mysql2date('j M Y', $ev->start_date)) : ''; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="w-fields w-fields--wide">
                        <div class="w-field">
                            <label for="f-title"><?php echo esc_html(sc_t('dashboard_pages.title', 'Title')); ?><span class="w-req" aria-hidden="true">*</span></label>
                            <input type="text" class="form-control" id="f-title" name="title" value="<?php echo esc_attr($w->title); ?>" maxlength="255" required>
                        </div>
                        <div class="w-field">
                            <label for="f-slug"><?php echo esc_html(sc_t('dashboard_pages.url', 'Web address')); ?></label>
                            <div class="w-affix">
                                <span class="w-affix__text">/workshop/</span>
                                <input type="text" class="form-control w-ltr" id="f-slug" name="slug" value="<?php echo esc_attr($w->slug); ?>" placeholder="<?php echo esc_attr(sc_t('dashboard_pages.slug_auto', 'from the title')); ?>" autocomplete="off" spellcheck="false">
                            </div>
                        </div>
                    </div>
                    <div class="w-field">
                        <label for="f-excerpt"><?php echo esc_html(sc_t('dashboard_pages.excerpt', 'Short summary')); ?> <span class="w-field__count"></span></label>
                        <textarea class="form-control" id="f-excerpt" name="excerpt" rows="2" data-w-count="300"><?php echo esc_textarea($w->excerpt); ?></textarea>
                    </div>
                    <div class="w-field">
                        <span class="w-field__label"><?php echo esc_html(sc_t('dashboard_pages.description', 'Description')); ?></span>
                        <?php
                        wp_editor($w->description, 'workshop_description', array(
                            'textarea_name' => 'description',
                            'media_buttons' => true,
                            'textarea_rows' => 10,
                            'teeny'         => false,
                        ));
                        ?>
                    </div>
                </div>
            </section>

            <section class="w-section" id="images" aria-labelledby="images-title">
                <div class="w-section__head">
                    <h2 id="images-title"><?php echo esc_html($sections['images']); ?></h2>
                    <span class="w-section__hint"><?php echo esc_html(sc_t('dashboard_pages.images_hint', 'JPG, PNG or WebP · landscape works best')); ?></span>
                </div>
                <div class="w-fields w-fields--2">
                    <?php foreach (array(
                        'featured_image' => array($featured_url, sc_t('dashboard_pages.featured_image', 'Card image'), sc_t('dashboard_pages.featured_image_help', 'Used on workshop cards and lists.')),
                        'banner_image'   => array($banner_url, sc_t('dashboard_pages.banner_image', 'Page banner'), sc_t('dashboard_pages.banner_image_help', 'Top of the workshop page. Falls back to the card image.')),
                    ) as $field => $info): ?>
                    <div class="w-field">
                        <span class="w-field__label"><?php echo esc_html($info[1]); ?></span>
                        <div class="w-media" data-w-media="<?php echo esc_attr($info[1]); ?>">
                            <input type="hidden" name="<?php echo esc_attr($field); ?>" value="<?php echo (int) $w->$field; ?>">
                            <div class="w-media__preview">
                                <img src="<?php echo esc_url($info[0]); ?>" alt=""<?php echo $info[0] ? '' : ' hidden'; ?>>
                                <span class="w-media__empty" role="button" tabindex="0"<?php echo $info[0] ? ' hidden' : ''; ?>>
                                    <i class="fa fa-image fa-lg" aria-hidden="true"></i>
                                    <?php echo esc_html(sc_t('dashboard_pages.choose_image', 'Choose an image')); ?>
                                </span>
                            </div>
                            <div class="w-media__actions">
                                <button type="button" class="btn btn-sm btn-secondary" data-w-media-choose><?php echo esc_html(sc_t('dashboard_pages.replace', 'Choose')); ?></button>
                                <button type="button" class="btn btn-sm btn-secondary" data-w-media-remove<?php echo $info[0] ? '' : ' hidden'; ?>><?php echo esc_html(sc_t('dashboard_pages.remove', 'Remove')); ?></button>
                            </div>
                        </div>
                        <p class="w-field__help"><?php echo esc_html($info[2]); ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="w-section" id="schedule" aria-labelledby="schedule-title">
                <div class="w-section__head">
                    <h2 id="schedule-title"><?php echo esc_html($sections['schedule']); ?></h2>
                    <span class="w-section__hint"><?php echo esc_html(wp_timezone_string()); ?></span>
                </div>
                <div class="w-fields">
                    <div class="w-fields w-fields--4">
                        <div class="w-field">
                            <label for="f-start-date"><?php echo esc_html(sc_t('dashboard_pages.starts', 'Starts')); ?><span class="w-req" aria-hidden="true">*</span></label>
                            <input type="date" class="form-control" id="f-start-date" name="start_date" value="<?php echo esc_attr($w->start_date); ?>" required>
                        </div>
                        <div class="w-field">
                            <label for="f-start-time"><?php echo esc_html(sc_t('dashboard_pages.time', 'Time')); ?></label>
                            <input type="time" class="form-control" id="f-start-time" name="start_time" value="<?php echo esc_attr($time_value($w->start_time)); ?>">
                        </div>
                        <div class="w-field">
                            <label for="f-end-date"><?php echo esc_html(sc_t('dashboard_pages.ends', 'Ends')); ?></label>
                            <input type="date" class="form-control" id="f-end-date" name="end_date" value="<?php echo esc_attr($w->end_date); ?>">
                        </div>
                        <div class="w-field">
                            <label for="f-end-time"><?php echo esc_html(sc_t('dashboard_pages.time', 'Time')); ?></label>
                            <input type="time" class="form-control" id="f-end-time" name="end_time" value="<?php echo esc_attr($time_value($w->end_time)); ?>">
                        </div>
                    </div>

                    <div class="w-field" role="radiogroup" aria-labelledby="f-location-label">
                        <span class="w-field__label" id="f-location-label"><?php echo esc_html(sc_t('dashboard_pages.venue_type', 'Where')); ?></span>
                        <div class="w-choice">
                            <?php foreach (array(
                                'offline' => sc_t('dashboard_pages.in_person', 'In person'),
                                'online'  => sc_t('dashboard_pages.online', 'Online'),
                                'hybrid'  => sc_t('dashboard_pages.hybrid', 'Hybrid'),
                            ) as $value => $label): ?>
                            <label class="w-choice__item">
                                <input type="radio" name="location_type" value="<?php echo esc_attr($value); ?>" <?php checked($w->location_type ?: 'offline', $value); ?>>
                                <span class="w-choice__box"><?php echo esc_html($label); ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="w-fields w-fields--2" data-w-show-if="location_type:offline,hybrid">
                        <div class="w-field">
                            <label for="f-venue"><?php echo esc_html(sc_t('dashboard_pages.venue_name', 'Venue or hall')); ?></label>
                            <input type="text" class="form-control" id="f-venue" name="venue_name" value="<?php echo esc_attr($w->venue_name); ?>">
                        </div>
                        <div class="w-field">
                            <label for="f-address"><?php echo esc_html(sc_t('dashboard_pages.venue_address', 'Address')); ?></label>
                            <input type="text" class="form-control" id="f-address" name="venue_address" value="<?php echo esc_attr($w->venue_address); ?>">
                        </div>
                    </div>
                    <div class="w-field" data-w-show-if="location_type:online,hybrid">
                        <label for="f-meeting"><?php echo esc_html(sc_t('dashboard_pages.meeting_link', 'Meeting link')); ?><span class="w-req" aria-hidden="true">*</span></label>
                        <input type="url" class="form-control w-ltr" id="f-meeting" name="meeting_link" value="<?php echo esc_attr($w->meeting_link); ?>" placeholder="https://">
                        <p class="w-field__help"><?php echo esc_html(sc_t('dashboard_pages.meeting_link_help', 'Zoom, Teams or Meet link, shared with registered attendees.')); ?></p>
                    </div>
                </div>
            </section>

            <section class="w-section" id="capacity" aria-labelledby="capacity-title">
                <div class="w-section__head">
                    <h2 id="capacity-title"><?php echo esc_html($sections['capacity']); ?></h2>
                    <?php if ($is_edit && $capacity > 0): ?>
                        <span class="w-tag <?php echo $fill >= 100 ? 'w-tag--red' : ($fill >= 80 ? 'w-tag--gold' : 'w-tag--teal'); ?>"><span class="w-ltr"><?php echo (int) $registered; ?> / <?php echo (int) $capacity; ?></span>&nbsp;<?php echo esc_html(sc_t('dashboard_pages.taken', 'taken')); ?></span>
                    <?php endif; ?>
                </div>
                <div class="w-fields w-fields--4">
                    <div class="w-field">
                        <label for="f-capacity"><?php echo esc_html(sc_t('dashboard_pages.capacity', 'Capacity')); ?></label>
                        <input type="number" class="form-control" id="f-capacity" name="total_capacity" value="<?php echo (int) $w->total_capacity; ?>" min="0" step="1" inputmode="numeric">
                        <p class="w-field__help"><?php echo esc_html(sc_t('dashboard_pages.capacity_help', '0 means unlimited')); ?></p>
                    </div>
                    <div class="w-field">
                        <label for="f-deadline"><?php echo esc_html(sc_t('dashboard_pages.registration_closes', 'Registration closes')); ?></label>
                        <input type="datetime-local" class="form-control" id="f-deadline" name="registration_deadline" value="<?php echo esc_attr($deadline_value); ?>">
                        <p class="w-field__help"><?php echo esc_html(sc_t('dashboard_pages.deadline_help', 'Leave empty to keep it open')); ?></p>
                    </div>
                    <div class="w-field">
                        <label for="f-min"><?php echo esc_html(sc_t('dashboard_pages.min_per_order', 'Min per order')); ?></label>
                        <input type="number" class="form-control" id="f-min" name="min_tickets_per_order" value="<?php echo max(1, (int) $w->min_tickets_per_order); ?>" min="1" step="1" inputmode="numeric">
                    </div>
                    <div class="w-field">
                        <label for="f-max"><?php echo esc_html(sc_t('dashboard_pages.max_per_order', 'Max per order')); ?></label>
                        <input type="number" class="form-control" id="f-max" name="max_tickets_per_order" value="<?php echo max(1, (int) $w->max_tickets_per_order); ?>" min="1" step="1" inputmode="numeric">
                    </div>
                </div>
            </section>

            <section class="w-section" id="tickets" aria-labelledby="tickets-title">
                <div class="w-section__head">
                    <div>
                        <h2 id="tickets-title"><?php echo esc_html($sections['tickets']); ?></h2>
                        <span class="w-section__hint"><?php echo esc_html(sc_t('dashboard_pages.tickets_hint', 'What people pick when they register. A price of 0 is free.')); ?></span>
                    </div>
                    <?php if ($is_edit): ?>
                    <button type="button" class="btn btn-sm btn-secondary" id="add-ticket-btn"><i class="fa fa-plus" aria-hidden="true"></i> <?php echo esc_html(sc_t('dashboard_pages.add_ticket', 'Add ticket')); ?></button>
                    <?php endif; ?>
                </div>
                <?php if ($is_edit): ?>
                    <div class="w-subtable">
                        <table>
                            <thead><tr>
                                <th><?php echo esc_html(sc_t('dashboard_pages.ticket', 'Ticket')); ?></th>
                                <th><?php echo esc_html(sc_t('dashboard_pages.price', 'Price')); ?></th>
                                <th><?php echo esc_html(sc_t('dashboard_pages.sold', 'Sold')); ?></th>
                                <th><?php echo esc_html(sc_t('dashboard_pages.status', 'Status')); ?></th>
                                <th><span class="sr-only"><?php echo esc_html(sc_t('dashboard_pages.actions', 'Actions')); ?></span></th>
                            </tr></thead>
                            <tbody id="tickets-body"></tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="w-field__help mb-0"><?php echo esc_html(sc_t('dashboard_pages.tickets_after_create', 'You can add tickets once the workshop is created.')); ?></p>
                <?php endif; ?>
            </section>

            <section class="w-section" id="certificates" aria-labelledby="certificates-title">
                <div class="w-section__head">
                    <h2 id="certificates-title"><?php echo esc_html($sections['certificates']); ?></h2>
                </div>
                <div class="w-switches">
                    <label class="w-switch">
                        <input type="checkbox" name="enable_certificates" value="1" <?php checked((int) $w->enable_certificates, 1); ?>>
                        <span class="w-switch__track" aria-hidden="true"></span>
                        <span class="w-switch__text"><strong><?php echo esc_html(sc_t('dashboard_pages.enable_certificates', 'Issue certificates for this workshop')); ?></strong><span><?php echo esc_html(sc_t('dashboard_pages.enable_certificates_help', 'Attendees can download them from their account.')); ?></span></span>
                    </label>
                    <div class="w-switches" data-w-show-if="enable_certificates:1">
                        <?php if (!empty($cert_templates)): ?>
                        <div class="w-field">
                            <label for="f-template"><?php echo esc_html(sc_t('dashboard_pages.certificate_template', 'Template')); ?></label>
                            <select class="form-control" id="f-template" name="certificate_template_id">
                                <option value="0"><?php echo esc_html(sc_t('dashboard_pages.default_template', 'Default template')); ?></option>
                                <?php foreach ($cert_templates as $tpl): ?>
                                    <option value="<?php echo (int) $tpl->id; ?>" <?php selected((int) $w->certificate_template_id, (int) $tpl->id); ?>><?php echo esc_html($tpl->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <label class="w-switch">
                            <input type="checkbox" name="certificate_require_checkin" value="1" <?php checked((int) $w->certificate_require_checkin, 1); ?>>
                            <span class="w-switch__track" aria-hidden="true"></span>
                            <span class="w-switch__text"><strong><?php echo esc_html(sc_t('dashboard_pages.require_checkin', 'Only for people who checked in')); ?></strong></span>
                        </label>
                        <label class="w-switch">
                            <input type="checkbox" name="auto_issue_certificate" value="1" <?php checked((int) $w->auto_issue_certificate, 1); ?>>
                            <span class="w-switch__track" aria-hidden="true"></span>
                            <span class="w-switch__text"><strong><?php echo esc_html(sc_t('dashboard_pages.auto_issue', 'Issue automatically')); ?></strong><span><?php echo esc_html(sc_t('dashboard_pages.auto_issue_help', 'Otherwise issue them from Certificates → Issue.')); ?></span></span>
                        </label>
                    </div>
                </div>
            </section>
        </div>

        <aside class="w-form-aside">
            <div class="w-aside-card">
                <span class="w-aside-card__title" id="status-title"><?php echo esc_html(sc_t('dashboard_pages.status', 'Status')); ?></span>
                <div class="w-choice w-choice--stack" role="radiogroup" aria-labelledby="status-title">
                    <?php foreach ($statuses as $value => $info): ?>
                    <label class="w-choice__item">
                        <input type="radio" name="status" value="<?php echo esc_attr($value); ?>" <?php checked($w->status ?: 'draft', $value); ?>>
                        <span class="w-choice__box"><span><?php echo esc_html($info[0]); ?><span class="w-choice__sub"><?php echo esc_html($info[1]); ?></span></span><i class="w-choice__dot" style="background:<?php echo esc_attr($info[2]); ?>" aria-hidden="true"></i></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($is_edit): ?>
            <div class="w-aside-card">
                <span class="w-aside-card__title"><?php echo esc_html(sc_t('dashboard_pages.statistics', 'Statistics')); ?></span>
                <div class="w-stats">
                    <span class="w-stat"><span class="w-stat__value w-ltr"><?php echo number_format_i18n($registered); ?></span><span class="w-stat__label"><?php echo esc_html(sc_t('dashboard_pages.registered', 'Registered')); ?></span></span>
                    <span class="w-stat"><span class="w-stat__value w-ltr"><?php echo number_format_i18n((int) $stats->checked_in); ?></span><span class="w-stat__label"><?php echo esc_html(sc_t('dashboard_pages.checked_in', 'Checked in')); ?></span></span>
                    <span class="w-stat"><span class="w-stat__value w-ltr"><?php echo $capacity > 0 ? number_format_i18n(max(0, $capacity - $registered)) : '∞'; ?></span><span class="w-stat__label"><?php echo esc_html(sc_t('dashboard_pages.seats_left', 'Seats left')); ?></span></span>
                    <span class="w-stat"><span class="w-stat__value w-ltr"><?php echo number_format_i18n((float) $stats->revenue); ?></span><span class="w-stat__label"><?php echo esc_html(sc_t('dashboard_pages.revenue_egp', 'Revenue')); ?></span></span>
                </div>
                <?php if ($capacity > 0): ?>
                <div class="w-meter">
                    <div class="w-meter__row"><span><?php echo esc_html(sc_t('dashboard_pages.fill_rate', 'Filled')); ?></span><strong class="w-ltr"><?php echo (int) $fill; ?>%</strong></div>
                    <div class="w-meter__track"><div class="w-meter__fill<?php echo $fill >= 100 ? ' is-full' : ($fill >= 80 ? ' is-high' : ''); ?>" style="width:<?php echo (int) $fill; ?>%"></div></div>
                </div>
                <?php endif; ?>
                <div class="w-aside-links">
                    <a href="<?php echo esc_url($dashboard_url . 'workshop-attendees?workshop_id=' . (int) $w->id); ?>"><?php echo esc_html(sc_t('dashboard_pages.view_attendees', 'View attendees')); ?> →</a>
                    <a href="<?php echo esc_url($dashboard_url . 'workshop-scanner?workshop_id=' . (int) $w->id); ?>"><?php echo esc_html(sc_t('dashboard_pages.open_scanner', 'Open the workshop scanner')); ?> →</a>
                </div>
            </div>

            <div class="w-danger">
                <strong><?php echo esc_html(sc_t('dashboard_pages.delete_workshop', 'Delete workshop')); ?></strong>
                <p><?php echo esc_html(sc_t('dashboard_pages.delete_workshop_help', 'Removes its tickets, registrations, check-ins and certificates. To stop registration without losing anything, set the status to Disabled instead.')); ?></p>
                <button type="button" class="btn btn-sm" id="delete-workshop-btn"><?php echo esc_html(sc_t('dashboard_pages.delete_workshop', 'Delete workshop')); ?></button>
            </div>
            <?php endif; ?>
        </aside>
    </div>

    <div class="w-savebar">
        <span class="w-dirty" data-w-dirty hidden><?php echo esc_html(sc_t('dashboard_pages.unsaved_changes', 'Unsaved changes')); ?></span>
        <button type="submit" class="btn btn-primary" data-w-save><?php echo esc_html($is_edit ? sc_t('dashboard_pages.save_changes', 'Save changes') : sc_t('dashboard_pages.create_workshop', 'Create workshop')); ?></button>
    </div>
</form>
</div>
</div>

<?php if ($is_edit): ?>
<div class="modal fade" id="ticketModal" tabindex="-1" role="dialog" aria-labelledby="ticket-modal-title">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="ticket-form" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="ticket-modal-title"><?php echo esc_html(sc_t('dashboard_pages.add_ticket', 'Add ticket')); ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo esc_attr(sc_t('dashboard_pages.close', 'Close')); ?>"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="ticket_id" value="">
                    <input type="hidden" name="workshop_id" value="<?php echo (int) $w->id; ?>">
                    <div class="w-fields">
                        <div class="w-field">
                            <label for="t-name"><?php echo esc_html(sc_t('dashboard_pages.name', 'Name')); ?><span class="w-req" aria-hidden="true">*</span></label>
                            <input type="text" class="form-control" id="t-name" name="name" required>
                        </div>
                        <div class="w-field">
                            <label for="t-description"><?php echo esc_html(sc_t('dashboard_pages.description', 'Description')); ?></label>
                            <textarea class="form-control" id="t-description" name="description" rows="2"></textarea>
                        </div>
                        <div class="w-fields w-fields--2">
                            <div class="w-field">
                                <label for="t-price"><?php echo esc_html(sprintf(sc_t('dashboard_pages.price_in', 'Price (%s)'), get_option('sc_currency_code', 'EGP'))); ?></label>
                                <input type="number" class="form-control" id="t-price" name="price" min="0" step="0.01" value="0" inputmode="decimal">
                                <p class="w-field__help"><?php echo esc_html(sc_t('dashboard_pages.price_help', '0 = free; paid tickets need a payment gateway.')); ?></p>
                            </div>
                            <div class="w-field">
                                <label for="t-quantity"><?php echo esc_html(sc_t('dashboard_pages.quantity', 'Quantity')); ?></label>
                                <input type="number" class="form-control" id="t-quantity" name="quantity" min="0" step="1" value="0" inputmode="numeric">
                                <p class="w-field__help"><?php echo esc_html(sc_t('dashboard_pages.capacity_help', '0 means unlimited')); ?></p>
                            </div>
                            <div class="w-field">
                                <label for="t-min"><?php echo esc_html(sc_t('dashboard_pages.min_per_order', 'Min per order')); ?></label>
                                <input type="number" class="form-control" id="t-min" name="min_per_order" min="1" step="1" value="1" inputmode="numeric">
                            </div>
                            <div class="w-field">
                                <label for="t-max"><?php echo esc_html(sc_t('dashboard_pages.max_per_order', 'Max per order')); ?></label>
                                <input type="number" class="form-control" id="t-max" name="max_per_order" min="1" step="1" value="1" inputmode="numeric">
                            </div>
                        </div>
                        <label class="w-switch">
                            <input type="checkbox" name="is_active" value="1" checked>
                            <span class="w-switch__track" aria-hidden="true"></span>
                            <span class="w-switch__text"><strong><?php echo esc_html(sc_t('dashboard_pages.ticket_on_sale', 'On sale')); ?></strong></span>
                        </label>
                        <label class="w-switch">
                            <input type="checkbox" name="enable_coupons" value="1" checked>
                            <span class="w-switch__track" aria-hidden="true"></span>
                            <span class="w-switch__text"><strong><?php echo esc_html(sc_t('dashboard_pages.ticket_coupons', 'Accept coupons')); ?></strong></span>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo esc_html(sc_t('dashboard_pages.cancel', 'Cancel')); ?></button>
                    <button type="submit" class="btn btn-primary" id="save-ticket-btn"><?php echo esc_html(sc_t('dashboard_pages.save_ticket', 'Save ticket')); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
jQuery(function ($) {
    'use strict';

    var isEdit = <?php echo $is_edit ? 'true' : 'false'; ?>;
    var workshopId = <?php echo (int) $w->id; ?>;
    var dashboardUrl = <?php echo $js($dashboard_url); ?>;
    var initialTickets = <?php echo $js(array_map(function ($t) {
        return array(
            'id' => (int) $t->id, 'name' => $t->name, 'description' => $t->description, 'price' => (float) $t->price,
            'quantity' => (int) $t->quantity, 'sold' => (int) $t->sold, 'min_per_order' => (int) $t->min_per_order,
            'max_per_order' => (int) $t->max_per_order, 'is_active' => (int) $t->is_active, 'enable_coupons' => (int) $t->enable_coupons,
        );
    }, $is_edit ? (array) $tickets : array())); ?>;
    var L = <?php echo $js(array(
        'required'      => sc_t('dashboard_pages.err_title', 'Title is required — it shows on the card and the ticket.'),
        'event'         => sc_t('dashboard_pages.err_event', 'Choose the event this workshop belongs to.'),
        'startDate'     => sc_t('dashboard_pages.err_start', 'Pick the day the workshop starts.'),
        'endDate'       => sc_t('dashboard_pages.err_end_date', 'Ends before it starts. Check the end date.'),
        'endTime'       => sc_t('dashboard_pages.err_end_time', 'Ends before it starts. Check the end time.'),
        'meeting'       => sc_t('dashboard_pages.err_meeting', 'Online and hybrid workshops need a meeting link.'),
        'meetingUrl'    => sc_t('dashboard_pages.err_meeting_url', 'Enter a full link starting with https://'),
        'capacity'      => sc_t('dashboard_pages.err_capacity', 'Capacity can’t be negative. Use 0 for unlimited.'),
        'min'           => sc_t('dashboard_pages.err_min', 'At least 1.'),
        'max'           => sc_t('dashboard_pages.err_max', 'Must be at least the minimum per order.'),
        'ticketName'    => sc_t('dashboard_pages.err_ticket_name', 'Give the ticket a name.'),
        'untitled'      => sc_t('dashboard_pages.untitled_workshop', 'Untitled workshop'),
        'free'          => sc_t('general.free', 'Free'),
        'onSale'        => sc_t('dashboard_pages.ticket_on_sale', 'On sale'),
        'offSale'       => sc_t('dashboard_pages.ticket_off_sale', 'Off sale'),
        'unlimited'     => sc_t('dashboard_pages.unlimited', 'unlimited'),
        'edit'          => sc_t('dashboard_pages.edit', 'Edit'),
        'delete'        => sc_t('dashboard_pages.delete', 'Delete'),
        'noTickets'     => sc_t('dashboard_pages.no_tickets', 'No tickets yet — without one, nobody can register.'),
        'addTicket'     => sc_t('dashboard_pages.add_ticket', 'Add ticket'),
        'editTicket'    => sc_t('dashboard_pages.edit_ticket', 'Edit ticket'),
        'deleteTicket'  => sc_t('dashboard_pages.confirm_delete_ticket', 'Delete this ticket? People who already registered with it keep their registration.'),
        'deleteWorkshop'=> sc_t('dashboard_pages.confirm_delete_workshop', 'Delete this workshop with its %d registrations, tickets, check-ins and certificates? This cannot be undone.'),
        'saved'         => sc_t('dashboard_pages.saved', 'Saved.'),
        'created'       => sc_t('dashboard_pages.workshop_created', 'Workshop created. Now add its tickets.'),
        'savedAt'       => sc_t('dashboard_pages.saved_just_now', 'Saved just now'),
        'failed'        => sc_t('errors.something_wrong', 'Something went wrong. Please try again.'),
        'saving'        => sc_t('dashboard_pages.saving', 'Saving…'),
        'currency'      => get_option('sc_currency_code', 'EGP'),
        'fixErrors'     => sc_t('dashboard_pages.fix_n', 'Fix %d to save'),
        'errorsTitle'   => sc_t('dashboard_pages.errors_title', '%d fields need attention before saving.'),
        'errorTitleOne' => sc_t('dashboard_pages.error_title_one', 'One field needs attention before saving.'),
        'leave'         => sc_t('dashboard_pages.unsaved_leave', 'You have unsaved changes.'),
    )); ?>;
    var registered = <?php echo (int) $registered; ?>;
    var esc = function (v) { return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]; }); };
    var post = function (data) {
        data.append('nonce', scDashboard.nonce);
        return fetch(scDashboard.ajaxurl, { method: 'POST', body: data, credentials: 'same-origin' }).then(function (r) { return r.json(); });
    };

    /* ---------------------------------------------------------------- form */

    var form = WDForm.create({
        form: document.getElementById('workshop-form'),
        i18n: { saving: L.saving, fixErrors: L.fixErrors, errorsTitle: L.errorsTitle, errorTitleOne: L.errorTitleOne, failed: L.failed, leave: L.leave, savedJustNow: L.savedAt },
        validate: function (v) {
            var e = [];
            if (!v.event_id) { e.push({ field: 'event_id', message: L.event }); }
            if (!$.trim(v.title)) { e.push({ field: 'title', message: L.required }); }
            if (!v.start_date) { e.push({ field: 'start_date', message: L.startDate }); }
            if (v.end_date && v.start_date && v.end_date < v.start_date) { e.push({ field: 'end_date', message: L.endDate }); }
            var sameDay = !v.end_date || v.end_date === v.start_date;
            if (sameDay && v.start_time && v.end_time && v.end_time <= v.start_time) { e.push({ field: 'end_time', message: L.endTime }); }
            if (v.location_type === 'online' || v.location_type === 'hybrid') {
                if (!$.trim(v.meeting_link)) { e.push({ field: 'meeting_link', message: L.meeting }); }
                else if (!/^https?:\/\/\S+\.\S+/.test($.trim(v.meeting_link))) { e.push({ field: 'meeting_link', message: L.meetingUrl }); }
            }
            if (parseInt(v.total_capacity || '0', 10) < 0) { e.push({ field: 'total_capacity', message: L.capacity }); }
            var min = parseInt(v.min_tickets_per_order || '1', 10), max = parseInt(v.max_tickets_per_order || '1', 10);
            if (!(min >= 1)) { e.push({ field: 'min_tickets_per_order', message: L.min }); }
            if (!(max >= Math.max(1, min))) { e.push({ field: 'max_tickets_per_order', message: L.max }); }
            return e;
        },
        submit: function (fd) {
            fd.append('action', 'sc_save_workshop');
            return post(fd);
        },
        onSuccess: function (data, api) {
            if (!isEdit && data.redirect) {
                api.markClean();
                window.location.href = data.redirect;
                return;
            }
            if (data.slug) {
                $('#f-slug').val(data.slug);
                $('[data-w-preview]').attr('href', <?php echo $js(home_url('/workshop/')); ?> + data.slug + '/');
                api.markClean();
            }
            if (window.toastr) { toastr.success(data.message || L.saved); }
        }
    });

    // Title in the page head follows the field.
    $('#f-title').on('input', function () { $('[data-w-title]').text($.trim(this.value) || L.untitled); });

    if (/[?&]created=1/.test(location.search)) {
        if (window.toastr) { toastr.success(L.created); }
        history.replaceState(null, '', location.pathname + location.search.replace(/[?&]created=1/, '').replace(/^&/, '?'));
    }

    if (!isEdit) { return; }

    /* ------------------------------------------------------------- tickets */

    var tickets = initialTickets;
    function money(n) { return Number(n) > 0 ? Number(n).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) + ' ' + L.currency : L.free; }

    function renderTickets() {
        var $body = $('#tickets-body');
        if (!tickets.length) {
            $body.html('<tr><td colspan="5" class="w-subtable__empty">' + esc(L.noTickets) + '</td></tr>');
            return;
        }
        $body.html(tickets.map(function (t) {
            return '<tr>' +
                '<td><strong>' + esc(t.name) + '</strong>' + (t.description ? '<span class="w-sub">' + esc(t.description) + '</span>' : '') + '</td>' +
                '<td class="w-ltr' + (Number(t.price) > 0 ? '' : ' text-success') + '">' + esc(money(t.price)) + '</td>' +
                '<td class="w-ltr">' + t.sold + ' / ' + (t.quantity > 0 ? t.quantity : esc(L.unlimited)) + '</td>' +
                '<td>' + (t.is_active ? '<span class="w-tag w-tag--teal">' + esc(L.onSale) + '</span>' : '<span class="w-tag">' + esc(L.offSale) + '</span>') + '</td>' +
                '<td class="text-right" style="white-space:nowrap">' +
                '<button type="button" class="btn btn-sm btn-secondary" data-ticket-edit="' + t.id + '">' + esc(L.edit) + '</button> ' +
                '<button type="button" class="btn btn-sm btn-link text-danger" data-ticket-delete="' + t.id + '">' + esc(L.delete) + '</button>' +
                '</td></tr>';
        }).join(''));
    }
    renderTickets();

    function reloadTickets() {
        var fd = new FormData();
        fd.append('action', 'sc_get_workshop_tickets');
        fd.append('workshop_id', workshopId);
        return post(fd).then(function (res) { if (res.success) { tickets = res.data.tickets; renderTickets(); } });
    }

    var $modal = $('#ticketModal');
    var ticketForm = document.getElementById('ticket-form');

    function clearTicketErrors() {
        $(ticketForm).find('.w-field.has-error').removeClass('has-error').find('.w-field__error').remove();
    }

    function openTicket(t) {
        ticketForm.reset();
        clearTicketErrors();
        $('#ticket-modal-title').text(t ? L.editTicket : L.addTicket);
        ticketForm.ticket_id.value = t ? t.id : '';
        if (t) {
            ticketForm.name.value = t.name;
            ticketForm.description.value = t.description || '';
            ticketForm.price.value = t.price;
            ticketForm.quantity.value = t.quantity;
            ticketForm.min_per_order.value = t.min_per_order || 1;
            ticketForm.max_per_order.value = t.max_per_order || 1;
            ticketForm.is_active.checked = !!t.is_active;
            ticketForm.enable_coupons.checked = !!t.enable_coupons;
        }
        $modal.modal('show');
    }
    $modal.on('shown.bs.modal', function () { $('#t-name').trigger('focus'); });

    $('#add-ticket-btn').on('click', function () { openTicket(null); });
    $(document).on('click', '[data-ticket-edit]', function () {
        var id = +this.getAttribute('data-ticket-edit');
        openTicket(tickets.filter(function (t) { return t.id === id; })[0]);
    });

    $(ticketForm).on('submit', function (e) {
        e.preventDefault();
        clearTicketErrors();
        if (!$.trim(ticketForm.name.value)) {
            var $f = $('#t-name').closest('.w-field').addClass('has-error');
            $f.append($('<p class="w-field__error">').text(L.ticketName));
            $('#t-name').trigger('focus');
            return;
        }
        var $btn = $('#save-ticket-btn').prop('disabled', true);
        var fd = new FormData(ticketForm);
        fd.append('action', 'sc_save_workshop_ticket');
        fd.append('event_id', $('#f-event').val());
        post(fd).then(function (res) {
            $btn.prop('disabled', false);
            if (res.success) {
                $modal.modal('hide');
                if (window.toastr) { toastr.success(res.data.message || L.saved); }
                reloadTickets();
                return;
            }
            var errs = res.data && res.data.errors ? res.data.errors : {};
            Object.keys(errs).forEach(function (k) {
                $(ticketForm).find('[name="' + k + '"]').closest('.w-field').addClass('has-error').append($('<p class="w-field__error">').text(errs[k]));
            });
            if (!Object.keys(errs).length && window.toastr) { toastr.error(res.data && res.data.message ? res.data.message : L.failed); }
        }).catch(function () { $btn.prop('disabled', false); if (window.toastr) { toastr.error(L.failed); } });
    });

    $(document).on('click', '[data-ticket-delete]', function () {
        var id = this.getAttribute('data-ticket-delete');
        showDeleteConfirm(L.deleteTicket).then(function (r) {
            if (!r.isConfirmed) { return; }
            var fd = new FormData();
            fd.append('action', 'sc_delete_workshop_ticket');
            fd.append('ticket_id', id);
            post(fd).then(function (res) {
                if (res.success) { reloadTickets(); } else if (window.toastr) { toastr.error(res.data && res.data.message ? res.data.message : L.failed); }
            });
        });
    });

    /* ------------------------------------------------------------- delete */

    $('#delete-workshop-btn').on('click', function () {
        showDeleteConfirm(L.deleteWorkshop.replace('%d', registered)).then(function (r) {
            if (!r.isConfirmed) { return; }
            var fd = new FormData();
            fd.append('action', 'sc_delete_workshop');
            fd.append('id', workshopId);
            post(fd).then(function (res) {
                if (res.success) {
                    form.markClean();
                    window.location.href = dashboardUrl + 'workshops';
                } else if (window.toastr) {
                    toastr.error(res.data && res.data.message ? res.data.message : L.failed);
                }
            });
        });
    });
});
</script>
