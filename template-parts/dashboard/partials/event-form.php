<?php
/**
 * Event form — shared by event-create.php and event-edit.php.
 *
 * Posts to sc_create_or_update_event with the same keys the older form sent, so
 * the handler stays the single place that saves an event. Repeaters (tickets,
 * FAQ, registration questions, page sections, links) are drawn by
 * assets/dashboard/js/event-form.js into hidden JSON inputs.
 *
 * Expects: $event (object|null), $form_data (from event-form-data.php).
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

$is_edit = !empty($event);
$dashboard_url = home_url('/event-manager-dashboard/');
$e = $is_edit ? $event : (object) array(
    'id' => 0, 'title' => '', 'slug' => '', 'description' => '', 'status' => 'draft',
    'start_date' => '', 'end_date' => '', 'start_time' => '', 'end_time' => '',
    'location_type' => 'offline', 'venue_name' => '', 'venue_address' => '', 'venue_city' => '',
    'google_maps_url' => '', 'meeting_link' => '', 'venue_image' => 0,
    'featured_image' => 0, 'logo_image' => 0, 'banner_image' => 0, 'schedules_file' => 0,
    'faq' => array(), 'extra_fields' => array(), 'additional_sections' => array(), 'social_links' => array(),
    'organizing_company' => null, 'attendance_tracking' => 0,
    'enable_certificates' => 0, 'certificate_template_id' => 0, 'auto_issue_certificate' => 1,
    'certificate_require_checkin' => 1, 'certificate_require_checkout' => 0, 'certificate_require_event_ended' => 0,
    'updated_at' => '',
);

$img = function ($id, $size = 'large') {
    return $id ? (wp_get_attachment_image_url((int) $id, $size) ?: '') : '';
};
$public_url = $e->slug ? home_url('/event/' . $e->slug . '/') : '';
$time_value = function ($t) {
    return $t && $t !== '00:00:00' ? substr($t, 0, 5) : '';
};
$saved_label = $e->updated_at ? sprintf(sc_t('dashboard_pages.saved_at', 'Saved %s'), mysql2date('j M Y, H:i', $e->updated_at)) : '';
$stats = $form_data['stats'];
$modules = $form_data['modules'];

$oc = $e->organizing_company;
if (is_string($oc)) {
    $oc = json_decode($oc, true);
}
$oc = is_array($oc) ? $oc : array();
$oc_social = is_array($oc['social'] ?? null) ? $oc['social'] : array();

$schedules_file_url = !empty($e->schedules_file) ? wp_get_attachment_url((int) $e->schedules_file) : '';

$statuses = array(
    'publish'   => array(sc_t('dashboard_pages.status_published', 'Published'), sc_t('dashboard_pages.event_published_help', 'On the site and selling tickets'), 'var(--w-teal)'),
    'draft'     => array(sc_t('dashboard_pages.status_draft', 'Draft'), sc_t('dashboard_pages.status_draft_help', 'Hidden; only managers can preview it'), 'var(--w-text-3)'),
    'completed' => array(sc_t('dashboard_pages.status_completed', 'Completed'), sc_t('dashboard_pages.status_completed_help', 'Still visible, shown as past'), 'var(--w-info)'),
    'disabled'  => array(sc_t('dashboard_pages.status_disabled', 'Disabled'), sc_t('dashboard_pages.status_hidden_help', 'Hidden from the site'), 'var(--w-text-3)'),
    'cancelled' => array(sc_t('dashboard_pages.status_cancelled', 'Cancelled'), sc_t('dashboard_pages.status_hidden_help', 'Hidden from the site'), 'var(--w-danger)'),
    'private'   => array(sc_t('dashboard_pages.status_private', 'Private'), sc_t('dashboard_pages.status_hidden_help', 'Hidden from the site'), 'var(--w-text-3)'),
);
// Rarely used statuses stay out of the way unless the event already has one.
foreach (array('cancelled', 'private') as $rare) {
    if ($e->status !== $rare) {
        unset($statuses[$rare]);
    }
}

$sections = array(
    'basic'     => sc_t('dashboard_pages.section_basic', 'Basic information'),
    'when'      => sc_t('dashboard_pages.section_when_where', 'Date & place'),
    'images'    => sc_t('dashboard_pages.section_images', 'Images'),
    'tickets'   => sc_t('dashboard_pages.section_tickets', 'Tickets'),
    'questions' => sc_t('dashboard_pages.section_registration', 'Registration'),
    'people'    => $modules['speakers'] ? sc_t('dashboard_pages.section_people', 'Speakers & organizers') : sc_t('dashboard_pages.section_organizers', 'Organizers'),
);
if ($modules['sponsors'] || $modules['partners']) {
    $sections['sponsors'] = sc_t('dashboard_pages.section_sponsors', 'Sponsors & partners');
}
$sections += array(
    'faq'      => sc_t('dashboard_pages.section_faq', 'FAQ'),
    'page'     => sc_t('dashboard_pages.section_page', 'Page sections'),
    'links'    => sc_t('dashboard_pages.section_links', 'Social links'),
    'company'  => sc_t('dashboard_pages.section_company', 'Organizing company'),
);
if ($modules['certificates']) {
    $sections['certificates'] = sc_t('dashboard_pages.section_certificates', 'Certificates');
}

$js = function ($value) {
    return wp_json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
};

$save_label = $is_edit ? sc_t('dashboard_pages.save_changes', 'Save changes') : sc_t('dashboard_pages.create_event', 'Create event');

$media_field = function ($name, $value, $label, $help, $ratio = '') use ($img) {
    $url = $img($value);
    ?>
    <div class="w-field">
        <span class="w-field__label"><?php echo esc_html($label); ?></span>
        <div class="w-media" data-w-media="<?php echo esc_attr($label); ?>"<?php echo $ratio ? ' style="--w-media-ratio:' . esc_attr($ratio) . '"' : ''; ?>>
            <input type="hidden" name="<?php echo esc_attr($name); ?>" value="<?php echo $value ? (int) $value : ''; ?>">
            <div class="w-media__preview">
                <img src="<?php echo esc_url($url); ?>" alt=""<?php echo $url ? '' : ' hidden'; ?>>
                <span class="w-media__empty" role="button" tabindex="0"<?php echo $url ? ' hidden' : ''; ?>>
                    <i class="fa fa-image fa-lg" aria-hidden="true"></i>
                    <?php echo esc_html(sc_t('dashboard_pages.choose_image', 'Choose an image')); ?>
                </span>
            </div>
            <div class="w-media__actions">
                <button type="button" class="btn btn-sm btn-secondary" data-w-media-choose><?php echo esc_html(sc_t('dashboard_pages.replace', 'Choose')); ?></button>
                <button type="button" class="btn btn-sm btn-secondary" data-w-media-remove<?php echo $url ? '' : ' hidden'; ?>><?php echo esc_html(sc_t('dashboard_pages.remove', 'Remove')); ?></button>
            </div>
        </div>
        <?php if ($help): ?><p class="w-field__help"><?php echo esc_html($help); ?></p><?php endif; ?>
    </div>
    <?php
};

$section_head = function ($key, $hint = '', $action = '') use ($sections) {
    ?>
    <div class="w-section__head">
        <div>
            <h2 id="<?php echo esc_attr($key); ?>-title"><?php echo esc_html($sections[$key]); ?></h2>
            <?php if ($hint): ?><span class="w-section__hint"><?php echo esc_html($hint); ?></span><?php endif; ?>
        </div>
        <?php echo $action; // Pre-escaped button markup. ?>
    </div>
    <?php
};
$add_button = function ($id, $label) {
    return '<button type="button" class="btn btn-sm btn-secondary" id="' . esc_attr($id) . '"><i class="fa fa-plus" aria-hidden="true"></i> ' . esc_html($label) . '</button>';
};
?>

<div id="main-content">
<div class="container-fluid">
<form id="event-form" novalidate enctype="multipart/form-data">
    <input type="hidden" name="event_id" value="<?php echo (int) $e->id; ?>">

    <div class="w-form-head">
        <div>
            <span class="w-form-head__eyebrow">
                <?php if ($is_edit): ?>
                    <?php echo esc_html(sc_t('events.event', 'Event')); ?> · ID <span class="w-ltr"><?php echo (int) $e->id; ?></span>
                <?php else: ?>
                    <?php echo esc_html(sc_t('dashboard_pages.new_event', 'New event')); ?>
                <?php endif; ?>
            </span>
            <h1 data-w-title><?php echo esc_html($e->title !== '' ? $e->title : sc_t('dashboard_pages.untitled_event', 'Untitled event')); ?></h1>
            <div class="w-form-head__meta">
                <?php if ($is_edit): ?><span data-w-saved><?php echo esc_html($saved_label); ?></span><?php endif; ?>
                <span class="w-dirty" data-w-dirty hidden><?php echo esc_html(sc_t('dashboard_pages.unsaved_changes', 'Unsaved changes')); ?></span>
            </div>
        </div>
        <div class="w-form-head__actions">
            <?php if ($is_edit): ?>
                <a class="btn btn-secondary" href="<?php echo esc_url($dashboard_url . 'attendees?event_id=' . (int) $e->id); ?>"><i class="fa fa-users" aria-hidden="true"></i> <?php echo esc_html(sc_t('nav.attendees', 'Attendees')); ?> <span class="w-ltr">(<?php echo number_format_i18n($stats['registered']); ?>)</span></a>
                <?php if ($public_url): ?>
                <a class="btn btn-secondary" href="<?php echo esc_url($public_url); ?>" target="_blank" rel="noopener" data-w-preview><i class="fa fa-external-link" aria-hidden="true"></i> <?php echo esc_html(sc_t('dashboard_pages.preview', 'Preview')); ?></a>
                <?php endif; ?>
            <?php else: ?>
                <a class="btn btn-secondary" href="<?php echo esc_url($dashboard_url . 'events'); ?>"><?php echo esc_html(sc_t('dashboard_pages.cancel', 'Cancel')); ?></a>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary w-save-head" data-w-save><?php echo esc_html($save_label); ?></button>
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

            <!-- Basic information -->
            <section class="w-section" id="basic" aria-labelledby="basic-title">
                <?php $section_head('basic', sc_t('dashboard_pages.event_basic_hint', 'Shown at the top of the event page')); ?>
                <div class="w-fields">
                    <div class="w-fields w-fields--wide">
                        <div class="w-field">
                            <label for="f-title"><?php echo esc_html(sc_t('dashboard_pages.title', 'Title')); ?><span class="w-req" aria-hidden="true">*</span></label>
                            <input type="text" class="form-control" id="f-title" name="event_title" value="<?php echo esc_attr($e->title); ?>" maxlength="255" required>
                        </div>
                        <div class="w-field">
                            <label for="f-slug"><?php echo esc_html(sc_t('dashboard_pages.url', 'Web address')); ?></label>
                            <div class="w-affix">
                                <span class="w-affix__text">/event/</span>
                                <input type="text" class="form-control w-ltr" id="f-slug" name="slug" value="<?php echo esc_attr($e->slug); ?>" placeholder="<?php echo esc_attr(sc_t('dashboard_pages.slug_auto', 'from the title')); ?>" autocomplete="off" spellcheck="false">
                            </div>
                            <?php if ($is_edit): ?><p class="w-field__help"><?php echo esc_html(sc_t('dashboard_pages.slug_change_help', 'Changing it breaks links already shared.')); ?></p><?php endif; ?>
                        </div>
                    </div>
                    <div class="w-field">
                        <span class="w-field__label"><?php echo esc_html(sc_t('dashboard_pages.description', 'Description')); ?></span>
                        <?php
                        wp_editor($e->description, 'event_description', array(
                            'textarea_name' => 'description',
                            'media_buttons' => true,
                            'textarea_rows' => 12,
                        ));
                        ?>
                    </div>
                    <?php if ($form_data['categories']['options']): ?>
                    <div class="w-field">
                        <span class="w-field__label" id="categories-label"><?php echo esc_html(sc_t('dashboard_pages.categories', 'Categories')); ?></span>
                        <div class="w-picker" data-picker="categories" data-name="categories[]" aria-labelledby="categories-label"></div>
                    </div>
                    <?php endif; ?>
                    <div class="w-field">
                        <span class="w-field__label"><?php echo esc_html(sc_t('dashboard_pages.schedules_file', 'Programme (PDF)')); ?></span>
                        <?php if ($schedules_file_url): ?>
                            <div class="w-file" data-schedules-current>
                                <i class="fa fa-file-pdf-o" aria-hidden="true"></i>
                                <a href="<?php echo esc_url($schedules_file_url); ?>" target="_blank" rel="noopener" class="w-ltr"><?php echo esc_html(wp_basename($schedules_file_url)); ?></a>
                                <label class="w-file__remove"><input type="checkbox" name="remove_schedules_file" value="1"> <?php echo esc_html(sc_t('dashboard_pages.remove', 'Remove')); ?></label>
                            </div>
                        <?php endif; ?>
                        <input type="file" class="form-control" id="f-schedules-file" name="schedules_file" accept="application/pdf,.pdf">
                        <p class="w-field__help"><?php echo esc_html(sc_t('dashboard_pages.schedules_file_help', 'Offered as a download next to the schedule. Uploading replaces the current file.')); ?></p>
                    </div>
                </div>
            </section>

            <!-- Date & place -->
            <section class="w-section" id="when" aria-labelledby="when-title">
                <?php $section_head('when'); ?>
                <div class="w-fields">
                    <div class="w-fields w-fields--4">
                        <div class="w-field">
                            <label for="f-start-date"><?php echo esc_html(sc_t('dashboard_pages.starts', 'Starts')); ?><span class="w-req" aria-hidden="true">*</span></label>
                            <input type="date" class="form-control" id="f-start-date" name="start_date" value="<?php echo esc_attr($e->start_date); ?>" required>
                        </div>
                        <div class="w-field">
                            <label for="f-start-time"><?php echo esc_html(sc_t('dashboard_pages.time', 'Time')); ?></label>
                            <input type="time" class="form-control" id="f-start-time" name="start_time" value="<?php echo esc_attr($time_value($e->start_time)); ?>">
                        </div>
                        <div class="w-field">
                            <label for="f-end-date"><?php echo esc_html(sc_t('dashboard_pages.ends', 'Ends')); ?></label>
                            <input type="date" class="form-control" id="f-end-date" name="end_date" value="<?php echo esc_attr($e->end_date); ?>">
                        </div>
                        <div class="w-field">
                            <label for="f-end-time"><?php echo esc_html(sc_t('dashboard_pages.time', 'Time')); ?></label>
                            <input type="time" class="form-control" id="f-end-time" name="end_time" value="<?php echo esc_attr($time_value($e->end_time)); ?>">
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
                                <input type="radio" name="location_type" value="<?php echo esc_attr($value); ?>" <?php checked($e->location_type ?: 'offline', $value); ?>>
                                <span class="w-choice__box"><?php echo esc_html($label); ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="w-fields" data-w-show-if="location_type:offline,hybrid">
                        <div class="w-fields w-fields--2">
                            <div class="w-field">
                                <label for="f-venue"><?php echo esc_html(sc_t('dashboard_pages.venue_name', 'Venue')); ?></label>
                                <input type="text" class="form-control" id="f-venue" name="venue_name" value="<?php echo esc_attr($e->venue_name); ?>">
                            </div>
                            <div class="w-field">
                                <label for="f-city"><?php echo esc_html(sc_t('dashboard_pages.city', 'City')); ?></label>
                                <input type="text" class="form-control" id="f-city" name="city" value="<?php echo esc_attr($e->venue_city); ?>">
                            </div>
                        </div>
                        <div class="w-field">
                            <label for="f-address"><?php echo esc_html(sc_t('dashboard_pages.venue_address', 'Address')); ?></label>
                            <input type="text" class="form-control" id="f-address" name="venue_address" value="<?php echo esc_attr($e->venue_address); ?>">
                        </div>
                        <div class="w-field">
                            <label for="f-map"><?php echo esc_html(sc_t('dashboard_pages.google_maps_url', 'Google Maps link')); ?></label>
                            <input type="url" class="form-control w-ltr" id="f-map" name="google_maps_url" value="<?php echo esc_attr($e->google_maps_url); ?>" placeholder="https://maps.app.goo.gl/…">
                            <p class="w-field__help"><?php echo esc_html(sc_t('dashboard_pages.google_maps_help', 'Powers the “Directions” button on the event page.')); ?></p>
                        </div>
                        <div class="w-fields w-fields--2">
                            <?php $media_field('venue_image', (int) $e->venue_image, sc_t('dashboard_pages.venue_image', 'Venue photo'), sc_t('dashboard_pages.venue_image_help', 'Shown with the halls on the event page.')); ?>
                        </div>
                    </div>
                    <div class="w-field" data-w-show-if="location_type:online,hybrid">
                        <label for="f-meeting"><?php echo esc_html(sc_t('dashboard_pages.meeting_link', 'Meeting link')); ?><span class="w-req" aria-hidden="true">*</span></label>
                        <input type="url" class="form-control w-ltr" id="f-meeting" name="meeting_link" value="<?php echo esc_attr($e->meeting_link); ?>" placeholder="https://">
                    </div>
                </div>
            </section>

            <!-- Images -->
            <section class="w-section" id="images" aria-labelledby="images-title">
                <?php $section_head('images', sc_t('dashboard_pages.images_hint', 'JPG, PNG or WebP · landscape works best')); ?>
                <div class="w-fields w-fields--2">
                    <?php
                    $media_field('existing_banner_image', (int) $e->banner_image, sc_t('dashboard_pages.banner_image', 'Page banner'), sc_t('dashboard_pages.event_banner_help', 'Behind the title at the top of the event page.'));
                    $media_field('featured_image', (int) $e->featured_image, sc_t('dashboard_pages.featured_image', 'Card image'), sc_t('dashboard_pages.event_featured_help', 'Used on checkout and sign-in pages, and as the logo when there is none.'));
                    $media_field('existing_logo_image', (int) $e->logo_image, sc_t('dashboard_pages.event_logo', 'Event logo'), sc_t('dashboard_pages.event_logo_help', 'Used when the event has no banner.'));
                    ?>
                </div>
            </section>

            <!-- Tickets -->
            <section class="w-section" id="tickets" aria-labelledby="tickets-title">
                <?php $section_head('tickets', sc_t('dashboard_pages.event_tickets_hint', 'What people pick when they register for the event. Workshop tickets are on each workshop.'), $add_button('add-ticket-btn', sc_t('dashboard_pages.add_ticket', 'Add ticket'))); ?>
                <input type="hidden" name="tickets_data" id="tickets-data" value="">
                <div class="w-subtable">
                    <table>
                        <thead><tr>
                            <th><?php echo esc_html(sc_t('dashboard_pages.ticket', 'Ticket')); ?></th>
                            <th><?php echo esc_html(sc_t('dashboard_pages.price', 'Price')); ?></th>
                            <th><?php echo esc_html(sc_t('dashboard_pages.registered', 'Registered')); ?></th>
                            <th><?php echo esc_html(sc_t('dashboard_pages.status', 'Status')); ?></th>
                            <th><span class="sr-only"><?php echo esc_html(sc_t('dashboard_pages.actions', 'Actions')); ?></span></th>
                        </tr></thead>
                        <tbody id="tickets-body"></tbody>
                    </table>
                </div>
                <p class="w-field__help mb-0"><?php echo esc_html(sc_t('dashboard_pages.tickets_save_note', 'Ticket changes are saved with the event.')); ?></p>
            </section>

            <!-- Registration -->
            <section class="w-section" id="questions" aria-labelledby="questions-title">
                <?php $section_head('questions', sc_t('dashboard_pages.questions_hint', 'Extra questions on the registration form'), $add_button('add-question-btn', sc_t('dashboard_pages.add_question', 'Add question'))); ?>
                <input type="hidden" name="extra_fields_data" id="extra-fields-data" value="">
                <div class="w-repeat" id="questions-list"></div>
                <div class="w-switches mt-3">
                    <input type="hidden" name="attendance_tracking" value="0">
                    <label class="w-switch">
                        <input type="checkbox" name="attendance_tracking" value="1" <?php checked((int) $e->attendance_tracking, 1); ?>>
                        <span class="w-switch__track" aria-hidden="true"></span>
                        <span class="w-switch__text"><strong><?php echo esc_html(sc_t('dashboard_pages.attendance_tracking', 'Track time inside')); ?></strong><span><?php echo esc_html(sc_t('dashboard_pages.attendance_tracking_help', 'Scanners record check-in and check-out, so you can see how long each person stayed.')); ?></span></span>
                    </label>
                </div>
            </section>

            <!-- People -->
            <section class="w-section" id="people" aria-labelledby="people-title">
                <?php $section_head('people', sc_t('dashboard_pages.people_hint', 'Search and add; the order here is the order on the page')); ?>
                <div class="w-fields">
                    <?php if ($modules['speakers']): ?>
                    <div class="w-field">
                        <span class="w-field__label" id="speakers-label"><?php echo esc_html(sc_t('nav.speakers', 'Speakers')); ?></span>
                        <div class="w-picker" data-picker="speakers" data-name="speakers[]" aria-labelledby="speakers-label"></div>
                    </div>
                    <?php endif; ?>
                    <div class="w-field">
                        <span class="w-field__label" id="organizers-label"><?php echo esc_html(sc_t('nav.organizers', 'Organizers')); ?></span>
                        <div class="w-picker" data-picker="organizers" data-name="organizers[]" aria-labelledby="organizers-label"></div>
                    </div>
                </div>
            </section>

            <?php if (isset($sections['sponsors'])): ?>
            <!-- Sponsors & partners -->
            <section class="w-section" id="sponsors" aria-labelledby="sponsors-title">
                <?php $section_head('sponsors'); ?>
                <div class="w-fields">
                    <?php if ($modules['sponsors']): ?>
                    <div class="w-field">
                        <span class="w-field__label" id="sponsors-label"><?php echo esc_html(sc_t('nav.sponsors', 'Sponsors')); ?></span>
                        <div class="w-picker" data-picker="sponsors" data-name="sponsors[]" aria-labelledby="sponsors-label"></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($modules['partners']): ?>
                    <div class="w-field">
                        <span class="w-field__label" id="partners-label"><?php echo esc_html(sc_t('nav.partners', 'Partners')); ?></span>
                        <div class="w-picker" data-picker="partners" data-name="partners[]" aria-labelledby="partners-label"></div>
                    </div>
                    <?php endif; ?>
                </div>
            </section>
            <?php endif; ?>

            <!-- FAQ -->
            <section class="w-section" id="faq" aria-labelledby="faq-title">
                <?php $section_head('faq', '', $add_button('add-faq-btn', sc_t('dashboard_pages.add_question', 'Add question'))); ?>
                <input type="hidden" name="faq_data" id="faq-data" value="">
                <div class="w-repeat" id="faq-list"></div>
            </section>

            <!-- Page sections -->
            <section class="w-section" id="page" aria-labelledby="page-title">
                <?php $section_head('page', sc_t('dashboard_pages.page_sections_hint', 'Extra blocks under the description: text, photos or cards'), $add_button('add-section-btn', sc_t('dashboard_pages.add_section', 'Add section'))); ?>
                <input type="hidden" name="additional_sections_data" id="sections-data" value="">
                <div class="w-repeat" id="sections-list"></div>
            </section>

            <!-- Social links -->
            <section class="w-section" id="links" aria-labelledby="links-title">
                <?php $section_head('links', sc_t('dashboard_pages.links_hint', 'Icons under the event title'), $add_button('add-link-btn', sc_t('dashboard_pages.add_link', 'Add link'))); ?>
                <input type="hidden" name="social_links" id="social-links-data" value="">
                <div class="w-repeat" id="links-list"></div>
            </section>

            <!-- Organizing company -->
            <section class="w-section" id="company" aria-labelledby="company-title">
                <?php $section_head('company', sc_t('dashboard_pages.company_hint', 'Leave the name empty to hide this block')); ?>
                <div class="w-fields">
                    <div class="w-fields w-fields--2">
                        <div class="w-field">
                            <label for="f-oc-name"><?php echo esc_html(sc_t('dashboard_pages.name', 'Name')); ?></label>
                            <input type="text" class="form-control" id="f-oc-name" name="oc_name" value="<?php echo esc_attr($oc['name'] ?? ''); ?>">
                        </div>
                        <div class="w-field">
                            <label for="f-oc-website"><?php echo esc_html(sc_t('dashboard_pages.website', 'Website')); ?></label>
                            <input type="url" class="form-control w-ltr" id="f-oc-website" name="oc_website" value="<?php echo esc_attr($oc['website'] ?? ''); ?>" placeholder="https://">
                        </div>
                    </div>
                    <div class="w-field">
                        <label for="f-oc-description"><?php echo esc_html(sc_t('dashboard_pages.description', 'Description')); ?></label>
                        <textarea class="form-control" id="f-oc-description" name="oc_description" rows="3"><?php echo esc_textarea($oc['description'] ?? ''); ?></textarea>
                    </div>
                    <div class="w-fields w-fields--2">
                        <?php
                        $media_field('oc_logo', (int) ($oc['logo'] ?? 0), sc_t('dashboard_pages.company_logo', 'Logo'), '');
                        $media_field('oc_banner', (int) ($oc['banner'] ?? 0), sc_t('dashboard_pages.company_banner', 'Banner'), '');
                        ?>
                    </div>
                    <div class="w-fields w-fields--2">
                        <?php foreach (array(
                            'oc_phone'     => array(sc_t('general.phone', 'Phone'), 'tel', $oc['phone'] ?? ''),
                            'oc_email'     => array(sc_t('general.email', 'Email'), 'email', $oc['email'] ?? ''),
                            'oc_whatsapp'  => array('WhatsApp', 'tel', $oc['whatsapp'] ?? ''),
                            'oc_facebook'  => array('Facebook', 'url', $oc_social['facebook'] ?? ''),
                            'oc_instagram' => array('Instagram', 'url', $oc_social['instagram'] ?? ''),
                            'oc_linkedin'  => array('LinkedIn', 'url', $oc_social['linkedin'] ?? ''),
                            'oc_twitter'   => array('X (Twitter)', 'url', $oc_social['twitter'] ?? ''),
                        ) as $name => $info): ?>
                        <div class="w-field">
                            <label for="f-<?php echo esc_attr($name); ?>"><?php echo esc_html($info[0]); ?></label>
                            <input type="<?php echo esc_attr($info[1]); ?>" class="form-control w-ltr" id="f-<?php echo esc_attr($name); ?>" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($info[2]); ?>"<?php echo $info[1] === 'url' ? ' placeholder="https://"' : ''; ?>>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <?php if ($modules['certificates']): ?>
            <!-- Certificates -->
            <section class="w-section" id="certificates" aria-labelledby="certificates-title">
                <?php $section_head('certificates'); ?>
                <div class="w-switches">
                    <input type="hidden" name="enable_certificates" value="0">
                    <label class="w-switch">
                        <input type="checkbox" name="enable_certificates" value="1" <?php checked((int) $e->enable_certificates, 1); ?>>
                        <span class="w-switch__track" aria-hidden="true"></span>
                        <span class="w-switch__text"><strong><?php echo esc_html(sc_t('dashboard_pages.enable_event_certificates', 'Issue certificates for this event')); ?></strong><span><?php echo esc_html(sc_t('dashboard_pages.enable_certificates_help', 'Attendees can download them from their account.')); ?></span></span>
                    </label>
                    <div class="w-switches" data-w-show-if="enable_certificates:1">
                        <?php if ($form_data['cert_templates']): ?>
                        <div class="w-field">
                            <label for="f-template"><?php echo esc_html(sc_t('dashboard_pages.certificate_template', 'Template')); ?></label>
                            <select class="form-control" id="f-template" name="certificate_template_id">
                                <option value="0"><?php echo esc_html(sc_t('dashboard_pages.default_template', 'Default template')); ?></option>
                                <?php foreach ($form_data['cert_templates'] as $tpl): ?>
                                    <option value="<?php echo (int) $tpl['id']; ?>" <?php selected((int) $e->certificate_template_id, $tpl['id']); ?>><?php echo esc_html($tpl['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="w-field" role="radiogroup" aria-labelledby="f-issue-label">
                            <span class="w-field__label" id="f-issue-label"><?php echo esc_html(sc_t('dashboard_pages.issue_method', 'How they are issued')); ?></span>
                            <div class="w-choice">
                                <label class="w-choice__item">
                                    <input type="radio" name="certificate_issue_method" value="auto" <?php checked((int) $e->auto_issue_certificate, 1); ?>>
                                    <span class="w-choice__box"><span><?php echo esc_html(sc_t('dashboard_pages.issue_auto', 'Automatically')); ?><span class="w-choice__sub"><?php echo esc_html(sc_t('dashboard_pages.issue_auto_help', 'As soon as the conditions below are met')); ?></span></span></span>
                                </label>
                                <label class="w-choice__item">
                                    <input type="radio" name="certificate_issue_method" value="manual" <?php checked((int) $e->auto_issue_certificate, 0); ?>>
                                    <span class="w-choice__box"><span><?php echo esc_html(sc_t('dashboard_pages.issue_manual', 'By hand')); ?><span class="w-choice__sub"><?php echo esc_html(sc_t('dashboard_pages.issue_manual_help', 'From Certificates → Issue')); ?></span></span></span>
                                </label>
                            </div>
                        </div>
                        <?php foreach (array(
                            'certificate_require_checkin'     => array((int) $e->certificate_require_checkin, sc_t('dashboard_pages.require_checkin', 'Only for people who checked in')),
                            'certificate_require_checkout'    => array((int) $e->certificate_require_checkout, sc_t('dashboard_pages.require_checkout', 'Only for people who also checked out')),
                            'certificate_require_event_ended' => array((int) $e->certificate_require_event_ended, sc_t('dashboard_pages.require_event_ended', 'Only after the event has ended')),
                        ) as $name => $info): ?>
                        <input type="hidden" name="<?php echo esc_attr($name); ?>" value="0">
                        <label class="w-switch">
                            <input type="checkbox" name="<?php echo esc_attr($name); ?>" value="1" <?php checked($info[0], 1); ?>>
                            <span class="w-switch__track" aria-hidden="true"></span>
                            <span class="w-switch__text"><strong><?php echo esc_html($info[1]); ?></strong></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
            <?php endif; ?>
        </div>

        <aside class="w-form-aside">
            <div class="w-aside-card">
                <span class="w-aside-card__title" id="status-title"><?php echo esc_html(sc_t('dashboard_pages.status', 'Status')); ?></span>
                <div class="w-choice w-choice--stack" role="radiogroup" aria-labelledby="status-title">
                    <?php foreach ($statuses as $value => $info): ?>
                    <label class="w-choice__item">
                        <input type="radio" name="status" value="<?php echo esc_attr($value); ?>" <?php checked($e->status ?: 'draft', $value); ?>>
                        <span class="w-choice__box"><span><?php echo esc_html($info[0]); ?><span class="w-choice__sub"><?php echo esc_html($info[1]); ?></span></span><i class="w-choice__dot" style="background:<?php echo esc_attr($info[2]); ?>" aria-hidden="true"></i></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($is_edit): ?>
            <div class="w-aside-card">
                <span class="w-aside-card__title"><?php echo esc_html(sc_t('dashboard_pages.statistics', 'Statistics')); ?></span>
                <div class="w-stats">
                    <span class="w-stat"><span class="w-stat__value w-ltr"><?php echo number_format_i18n($stats['registered']); ?></span><span class="w-stat__label"><?php echo esc_html(sc_t('dashboard_pages.registered', 'Registered')); ?></span></span>
                    <span class="w-stat"><span class="w-stat__value w-ltr"><?php echo number_format_i18n($stats['checked_in']); ?></span><span class="w-stat__label"><?php echo esc_html(sc_t('dashboard_pages.checked_in', 'Checked in')); ?></span></span>
                    <span class="w-stat"><span class="w-stat__value w-ltr"><?php echo number_format_i18n($stats['workshop_registrations']); ?></span><span class="w-stat__label"><?php echo esc_html(sc_t('dashboard_pages.in_workshops', 'In workshops')); ?></span></span>
                    <span class="w-stat"><span class="w-stat__value w-ltr"><?php echo number_format_i18n($stats['revenue']); ?></span><span class="w-stat__label"><?php echo esc_html(sc_t('dashboard_pages.revenue', 'Revenue')); ?></span></span>
                </div>
                <div class="w-aside-links">
                    <a href="<?php echo esc_url($dashboard_url . 'attendees?event_id=' . (int) $e->id); ?>"><?php echo esc_html(sc_t('dashboard_pages.view_attendees', 'View attendees')); ?> →</a>
                    <a href="<?php echo esc_url($dashboard_url . 'workshops?event_id=' . (int) $e->id); ?>"><?php echo esc_html(sprintf(sc_t('dashboard_pages.n_workshops_link', 'Workshops (%s)'), number_format_i18n($stats['workshops']))); ?> →</a>
                    <a href="<?php echo esc_url($dashboard_url . 'schedules?event_id=' . (int) $e->id); ?>"><?php echo esc_html(sprintf(sc_t('dashboard_pages.n_sessions_link', 'Schedule (%s sessions)'), number_format_i18n($stats['sessions']))); ?> →</a>
                    <a href="<?php echo esc_url($dashboard_url . 'event-view?id=' . (int) $e->id); ?>"><?php echo esc_html(sc_t('dashboard_pages.event_overview', 'Overview')); ?> →</a>
                </div>
            </div>

            <?php if ($stats['attendee_rows'] === 0): ?>
            <div class="w-danger">
                <strong><?php echo esc_html(sc_t('dashboard_pages.delete_event', 'Delete event')); ?></strong>
                <p><?php echo esc_html(sc_t('dashboard_pages.delete_event_help', 'Nobody has registered yet, so it can be deleted with its tickets. This cannot be undone.')); ?></p>
                <button type="button" class="btn btn-sm" id="delete-event-btn"><?php echo esc_html(sc_t('dashboard_pages.delete_event', 'Delete event')); ?></button>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </aside>
    </div>

    <div class="w-savebar">
        <span class="w-dirty" data-w-dirty hidden><?php echo esc_html(sc_t('dashboard_pages.unsaved_changes', 'Unsaved changes')); ?></span>
        <button type="submit" class="btn btn-primary" data-w-save><?php echo esc_html($save_label); ?></button>
    </div>
</form>
</div>
</div>

<!-- Ticket editor (edits the list above; saved with the event) -->
<div class="modal fade" id="ticketModal" tabindex="-1" role="dialog" aria-labelledby="ticket-modal-title">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="ticket-form" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="ticket-modal-title"><?php echo esc_html(sc_t('dashboard_pages.add_ticket', 'Add ticket')); ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo esc_attr(sc_t('dashboard_pages.close', 'Close')); ?>"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="w-fields">
                        <div class="w-field">
                            <label for="t-name"><?php echo esc_html(sc_t('dashboard_pages.name', 'Name')); ?><span class="w-req" aria-hidden="true">*</span></label>
                            <input type="text" class="form-control" id="t-name" name="name" required>
                        </div>
                        <div class="w-field">
                            <label for="t-description"><?php echo esc_html(sc_t('dashboard_pages.description', 'Description')); ?></label>
                            <textarea class="form-control" id="t-description" name="description" rows="2"></textarea>
                        </div>
                        <div class="w-field" role="radiogroup" aria-labelledby="t-type-label">
                            <span class="w-field__label" id="t-type-label"><?php echo esc_html(sc_t('dashboard_pages.ticket_for', 'For')); ?></span>
                            <div class="w-choice">
                                <label class="w-choice__item"><input type="radio" name="ticket_type" value="general" checked><span class="w-choice__box"><?php echo esc_html(sc_t('dashboard_pages.ticket_general', 'Attendees')); ?></span></label>
                                <label class="w-choice__item"><input type="radio" name="ticket_type" value="competitor"><span class="w-choice__box"><?php echo esc_html(sc_t('dashboard_pages.ticket_competitor', 'Competitors')); ?></span></label>
                            </div>
                        </div>
                        <label class="w-switch">
                            <input type="checkbox" name="coupon_only" value="1">
                            <span class="w-switch__track" aria-hidden="true"></span>
                            <span class="w-switch__text"><strong><?php echo esc_html(sc_t('dashboard_pages.coupon_only', 'Needs a coupon')); ?></strong><span><?php echo esc_html(sc_t('dashboard_pages.coupon_only_help', 'Free, but only with a valid coupon code.')); ?></span></span>
                        </label>
                        <div class="w-fields w-fields--2">
                            <div class="w-field" data-ticket-price>
                                <label for="t-price"><?php echo esc_html(sprintf(sc_t('dashboard_pages.price_in', 'Price (%s)'), get_option('sc_currency_code', 'EGP'))); ?></label>
                                <input type="number" class="form-control" id="t-price" name="price" min="0" step="0.01" value="0" inputmode="decimal">
                                <p class="w-field__help"><?php echo esc_html(sc_t('dashboard_pages.price_help', '0 = free; paid tickets need a payment gateway.')); ?></p>
                            </div>
                            <div class="w-field">
                                <label for="t-quantity"><?php echo esc_html(sc_t('dashboard_pages.quantity', 'Quantity')); ?></label>
                                <input type="number" class="form-control" id="t-quantity" name="quantity" min="1" step="1" value="" placeholder="<?php echo esc_attr(sc_t('dashboard_pages.unlimited_cap', 'Unlimited')); ?>" inputmode="numeric">
                                <p class="w-field__help"><?php echo esc_html(sc_t('dashboard_pages.quantity_help', 'Empty means unlimited')); ?></p>
                            </div>
                            <div class="w-field">
                                <label for="t-min"><?php echo esc_html(sc_t('dashboard_pages.min_per_order', 'Min per order')); ?></label>
                                <input type="number" class="form-control" id="t-min" name="min_per_order" min="1" step="1" value="1" inputmode="numeric">
                            </div>
                            <div class="w-field">
                                <label for="t-max"><?php echo esc_html(sc_t('dashboard_pages.max_per_order', 'Max per order')); ?></label>
                                <input type="number" class="form-control" id="t-max" name="max_per_order" min="1" step="1" value="10" inputmode="numeric">
                            </div>
                            <div class="w-field">
                                <label for="t-sale-start"><?php echo esc_html(sc_t('dashboard_pages.sale_starts', 'Sale starts')); ?></label>
                                <input type="datetime-local" class="form-control" id="t-sale-start" name="sale_start">
                            </div>
                            <div class="w-field">
                                <label for="t-sale-end"><?php echo esc_html(sc_t('dashboard_pages.sale_ends', 'Sale ends')); ?></label>
                                <input type="datetime-local" class="form-control" id="t-sale-end" name="sale_end">
                            </div>
                        </div>
                        <label class="w-switch">
                            <input type="checkbox" name="is_active" value="1" checked>
                            <span class="w-switch__track" aria-hidden="true"></span>
                            <span class="w-switch__text"><strong><?php echo esc_html(sc_t('dashboard_pages.ticket_on_sale', 'On sale')); ?></strong></span>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo esc_html(sc_t('dashboard_pages.cancel', 'Cancel')); ?></button>
                    <button type="submit" class="btn btn-primary" id="save-ticket-btn"><?php echo esc_html(sc_t('dashboard_pages.done', 'Done')); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
window.scEventForm = <?php echo $js(array(
    'isEdit'       => $is_edit,
    'eventId'      => (int) $e->id,
    'dashboardUrl' => $dashboard_url,
    'eventBase'    => home_url('/event/'),
    'currency'     => get_option('sc_currency_code', 'EGP'),
    'stats'        => $stats,
    'tickets'      => $form_data['tickets'],
    'pickers'      => array(
        'categories' => $form_data['categories'],
        'speakers'   => $form_data['speakers'],
        'organizers' => $form_data['organizers'],
        'sponsors'   => $form_data['sponsors'],
        'partners'   => $form_data['partners'],
    ),
    'faq'          => is_array($e->faq) ? array_values($e->faq) : array(),
    'questions'    => is_array($e->extra_fields) ? array_values($e->extra_fields) : array(),
    'sections'     => array_map(function ($s) use ($img) {
        $s = (array) $s;
        $s['image_urls'] = array();
        foreach ((array) ($s['images'] ?? array()) as $id) {
            $s['image_urls'][(string) $id] = is_numeric($id) ? $img($id, 'thumbnail') : (string) $id;
        }
        foreach ((array) ($s['cards'] ?? array()) as $i => $card) {
            $s['cards'][$i]['image_url'] = !empty($card['image']) && is_numeric($card['image']) ? $img($card['image'], 'thumbnail') : '';
        }
        return $s;
    }, is_array($e->additional_sections) ? array_values($e->additional_sections) : array()),
    'links'        => is_array($e->social_links) ? array_values($e->social_links) : array(),
    'i18n'         => array(
        'untitled'        => sc_t('dashboard_pages.untitled_event', 'Untitled event'),
        'errTitle'        => sc_t('dashboard_pages.err_event_title', 'Title is required — it heads the event page and every ticket.'),
        'errStart'        => sc_t('dashboard_pages.err_start_event', 'Pick the day the event starts.'),
        'errEndDate'      => sc_t('dashboard_pages.err_end_date', 'Ends before it starts. Check the end date.'),
        'errEndTime'      => sc_t('dashboard_pages.err_end_time', 'Ends before it starts. Check the end time.'),
        'errMeeting'      => sc_t('dashboard_pages.err_meeting_event', 'Online and hybrid events need a meeting link.'),
        'errUrl'          => sc_t('dashboard_pages.err_meeting_url', 'Enter a full link starting with https://'),
        'errTicketName'   => sc_t('dashboard_pages.err_ticket_name', 'Give the ticket a name.'),
        'errTicketMax'    => sc_t('dashboard_pages.err_max', 'Must be at least the minimum per order.'),
        'errSaleEnd'      => sc_t('dashboard_pages.err_sale_end', 'Sale ends before it starts.'),
        'errQuestion'     => sc_t('dashboard_pages.err_question', 'Question %d has no label.'),
        'errOptions'      => sc_t('dashboard_pages.err_options', 'Question %d needs at least one option.'),
        'errFaq'          => sc_t('dashboard_pages.err_faq', 'FAQ %d needs a question.'),
        'errLink'         => sc_t('dashboard_pages.err_link', 'Link %d is not a full web address.'),
        'free'            => sc_t('general.free', 'Free'),
        'couponOnly'      => sc_t('dashboard_pages.coupon_only_short', 'Coupon'),
        'competitor'      => sc_t('dashboard_pages.ticket_competitor', 'Competitors'),
        'onSale'          => sc_t('dashboard_pages.ticket_on_sale', 'On sale'),
        'offSale'         => sc_t('dashboard_pages.ticket_off_sale', 'Off sale'),
        'unlimited'       => sc_t('dashboard_pages.unlimited', 'unlimited'),
        'edit'            => sc_t('dashboard_pages.edit', 'Edit'),
        'remove'          => sc_t('dashboard_pages.remove', 'Remove'),
        'moveUp'          => sc_t('dashboard_pages.move_up', 'Move up'),
        'moveDown'        => sc_t('dashboard_pages.move_down', 'Move down'),
        'noTickets'       => sc_t('dashboard_pages.no_event_tickets', 'No tickets yet — without one, nobody can register.'),
        'addTicket'       => sc_t('dashboard_pages.add_ticket', 'Add ticket'),
        'editTicket'      => sc_t('dashboard_pages.edit_ticket', 'Edit ticket'),
        'removeHeld'      => sc_t('dashboard_pages.remove_held_ticket', '%d people hold this ticket. It will be taken off sale instead of deleted when you save.'),
        'removeTicket'    => sc_t('dashboard_pages.remove_ticket', 'Remove this ticket? It is deleted when you save.'),
        'newTicket'       => sc_t('dashboard_pages.new_unsaved', 'New'),
        'search'          => sc_t('dashboard_pages.picker_search', 'Search to add…'),
        'noMatches'       => sc_t('dashboard_pages.picker_no_matches', 'No matches'),
        'noneSelected'    => sc_t('dashboard_pages.picker_none', 'None added yet.'),
        'inactive'        => sc_t('dashboard_pages.inactive', 'inactive'),
        'noQuestions'     => sc_t('dashboard_pages.no_questions', 'Only name, email and phone are asked.'),
        'question'        => sc_t('dashboard_pages.question_label', 'Question'),
        'type'            => sc_t('dashboard_pages.answer_type', 'Answer'),
        'types'           => array(
            'text'     => sc_t('dashboard_pages.type_text', 'Short text'),
            'textarea' => sc_t('dashboard_pages.type_textarea', 'Long text'),
            'number'   => sc_t('dashboard_pages.type_number', 'Number'),
            'email'    => sc_t('dashboard_pages.type_email', 'Email'),
            'select'   => sc_t('dashboard_pages.type_select', 'Pick from a list'),
            'checkbox' => sc_t('dashboard_pages.type_checkbox', 'Tick box'),
        ),
        'required'        => sc_t('dashboard_pages.required', 'Required'),
        'options'         => sc_t('dashboard_pages.options_one_per_line', 'Options, one per line'),
        'noFaq'           => sc_t('dashboard_pages.no_faq', 'No questions yet.'),
        'faqQuestion'     => sc_t('dashboard_pages.faq_question', 'Question'),
        'faqAnswer'       => sc_t('dashboard_pages.faq_answer', 'Answer'),
        'noSections'      => sc_t('dashboard_pages.no_sections', 'No extra sections.'),
        'sectionTypes'    => array(
            'about'        => sc_t('dashboard_pages.section_about', 'Text'),
            'image_grid'   => sc_t('dashboard_pages.section_grid', 'Photo grid'),
            'image_slider' => sc_t('dashboard_pages.section_slider', 'Photo slider'),
            'card'         => sc_t('dashboard_pages.section_cards', 'Cards'),
        ),
        'heading'         => sc_t('dashboard_pages.heading', 'Heading'),
        'layout'          => sc_t('dashboard_pages.layout', 'Layout'),
        'text'            => sc_t('dashboard_pages.text', 'Text'),
        'photos'          => sc_t('dashboard_pages.photos', 'Photos'),
        'addPhotos'       => sc_t('dashboard_pages.add_photos', 'Add photos'),
        'buttonText'      => sc_t('dashboard_pages.button_text', 'Button text'),
        'buttonUrl'       => sc_t('dashboard_pages.button_link', 'Button link'),
        'cards'           => sc_t('dashboard_pages.cards', 'Cards'),
        'addCard'         => sc_t('dashboard_pages.add_card', 'Add card'),
        'cardTitle'       => sc_t('dashboard_pages.card_title', 'Title'),
        'cardText'        => sc_t('dashboard_pages.card_text', 'Text'),
        'image'           => sc_t('dashboard_pages.image', 'Image'),
        'chooseImage'     => sc_t('dashboard_pages.choose_image', 'Choose an image'),
        'noLinks'         => sc_t('dashboard_pages.no_links', 'No links yet.'),
        'network'         => sc_t('dashboard_pages.network', 'Network'),
        'networks'        => array(
            'fa-facebook' => 'Facebook', 'fa-instagram' => 'Instagram', 'fa-linkedin' => 'LinkedIn', 'fa-twitter' => 'X (Twitter)',
            'fa-youtube' => 'YouTube', 'fa-tiktok' => 'TikTok', 'fa-whatsapp' => 'WhatsApp', 'fa-telegram' => 'Telegram',
            'fa-globe' => sc_t('dashboard_pages.website', 'Website'), 'fa-envelope' => sc_t('general.email', 'Email'), 'fa-phone' => sc_t('general.phone', 'Phone'),
        ),
        'link'            => sc_t('dashboard_pages.link', 'Link'),
        'otherIcon'       => sc_t('dashboard_pages.other_icon', 'Other'),
        'deleteEvent'     => sc_t('dashboard_pages.confirm_delete_event_plain', 'Delete this event and its tickets? This cannot be undone.'),
        'saved'           => sc_t('dashboard_pages.saved', 'Saved.'),
        'created'         => sc_t('dashboard_pages.event_created', 'Event created.'),
        'savedAt'         => sc_t('dashboard_pages.saved_just_now', 'Saved just now'),
        'failed'          => sc_t('errors.something_wrong', 'Something went wrong. Please try again.'),
        'saving'          => sc_t('dashboard_pages.saving', 'Saving…'),
        'fixErrors'       => sc_t('dashboard_pages.fix_n', 'Fix %d to save'),
        'errorsTitle'     => sc_t('dashboard_pages.errors_title', '%d fields need attention before saving.'),
        'errorTitleOne'   => sc_t('dashboard_pages.error_title_one', 'One field needs attention before saving.'),
        'leave'           => sc_t('dashboard_pages.unsaved_leave', 'You have unsaved changes.'),
    ),
)); ?>;
</script>
<script src="<?php echo esc_url(get_template_directory_uri() . '/assets/dashboard/js/event-form.js?ver=' . filemtime(get_template_directory() . '/assets/dashboard/js/event-form.js')); ?>"></script>
