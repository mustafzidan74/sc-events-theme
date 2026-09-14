<?php
/**
 * Organizer form — shared by organizer-create.php and organizer-edit.php.
 * Posts to sc_save_organizer (inc/admin-dashboard/organizers-dashboard.php).
 *
 * Expects: $organizer (object|null) — a row from sc_organizers.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$is_edit = !empty($organizer);
$dashboard_url = home_url('/event-manager-dashboard/');
$o = $is_edit ? $organizer : (object) array(
    'id' => 0, 'name' => '', 'description' => '', 'logo' => 0, 'email' => '', 'phone' => '', 'website' => '',
    'address' => '', 'social_links' => '', 'display_order' => 0, 'is_active' => 1, 'updated_at' => '',
);
$social = json_decode((string) $o->social_links, true);
$social = is_array($social) ? $social : array();
$networks = array('facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'twitter' => 'X (Twitter)', 'youtube' => 'YouTube', 'tiktok' => 'TikTok');

$linked_events = $is_edit ? $wpdb->get_results($wpdb->prepare(
    "SELECT e.id, e.title, COALESCE(e.end_date, e.start_date) >= %s AS upcoming
     FROM {$wpdb->prefix}sc_event_organizers eo JOIN {$wpdb->prefix}sc_events e ON e.id = eo.event_id
     WHERE eo.organizer_id = %d ORDER BY e.start_date DESC",
    current_time('Y-m-d'),
    (int) $o->id
)) : array();

$logo_url = $o->logo ? (wp_get_attachment_image_url((int) $o->logo, 'medium') ?: '') : '';
$saved_label = $o->updated_at ? sprintf(sc_t('dashboard_pages.saved_at', 'Saved %s'), mysql2date('j M Y, H:i', $o->updated_at)) : '';
$save_label = $is_edit ? sc_t('dashboard_pages.save_changes', 'Save changes') : sc_t('dashboard_pages.create_organizer', 'Create organizer');
$js = function ($value) {
    return wp_json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
};
?>

<div id="main-content">
<div class="container-fluid">
<form id="organizer-form" novalidate>
    <input type="hidden" name="organizer_id" value="<?php echo (int) $o->id; ?>">

    <div class="w-form-head">
        <div>
            <span class="w-form-head__eyebrow"><?php echo esc_html($is_edit ? sc_t('dashboard_pages.organizer', 'Organizer') : sc_t('dashboard_pages.new_organizer', 'New organizer')); ?></span>
            <h1 data-w-title><?php echo esc_html($o->name !== '' ? $o->name : sc_t('dashboard_pages.new_organizer', 'New organizer')); ?></h1>
            <div class="w-form-head__meta">
                <?php if ($is_edit): ?><span data-w-saved><?php echo esc_html($saved_label); ?></span><?php endif; ?>
                <span class="w-dirty" data-w-dirty hidden><?php echo esc_html(sc_t('dashboard_pages.unsaved_changes', 'Unsaved changes')); ?></span>
            </div>
        </div>
        <div class="w-form-head__actions">
            <a class="btn btn-secondary" href="<?php echo esc_url($dashboard_url . 'organizers'); ?>"><?php echo esc_html($is_edit ? sc_t('nav.organizers', 'Organizers') : sc_t('dashboard_pages.cancel', 'Cancel')); ?></a>
            <button type="submit" class="btn btn-primary w-save-head" data-w-save><?php echo esc_html($save_label); ?></button>
        </div>
    </div>

    <div class="w-errors" data-w-errors hidden></div>

    <div class="w-form-layout w-form-layout--noseq">
        <div class="w-form-main">
            <section class="w-section" aria-labelledby="org-profile-title">
                <div class="w-section__head"><h2 id="org-profile-title"><?php echo esc_html(sc_t('dashboard_pages.profile', 'Profile')); ?></h2></div>
                <div class="w-speaker-top">
                    <div class="w-field w-speaker-top__photo">
                        <span class="w-field__label"><?php echo esc_html(sc_t('dashboard_pages.logo', 'Logo')); ?></span>
                        <div class="w-media w-media--square" data-w-media="<?php echo esc_attr(sc_t('dashboard_pages.logo', 'Logo')); ?>">
                            <input type="hidden" name="logo" value="<?php echo $o->logo ? (int) $o->logo : ''; ?>">
                            <div class="w-media__preview">
                                <img src="<?php echo esc_url($logo_url); ?>" alt=""<?php echo $logo_url ? '' : ' hidden'; ?>>
                                <span class="w-media__empty" role="button" tabindex="0"<?php echo $logo_url ? ' hidden' : ''; ?>>
                                    <i class="fa fa-building-o fa-lg" aria-hidden="true"></i>
                                    <?php echo esc_html(sc_t('dashboard_pages.choose_image', 'Choose an image')); ?>
                                </span>
                            </div>
                            <div class="w-media__actions">
                                <button type="button" class="btn btn-sm btn-secondary" data-w-media-choose><?php echo esc_html(sc_t('dashboard_pages.replace', 'Choose')); ?></button>
                                <button type="button" class="btn btn-sm btn-secondary" data-w-media-remove<?php echo $logo_url ? '' : ' hidden'; ?>><?php echo esc_html(sc_t('dashboard_pages.remove', 'Remove')); ?></button>
                            </div>
                        </div>
                    </div>
                    <div class="w-fields">
                        <div class="w-field">
                            <label for="org-name"><?php echo esc_html(sc_t('dashboard_pages.name', 'Name')); ?><span class="w-req" aria-hidden="true">*</span></label>
                            <input type="text" class="form-control" id="org-name" name="name" value="<?php echo esc_attr($o->name); ?>" maxlength="255" required>
                        </div>
                        <div class="w-field">
                            <label for="org-address"><?php echo esc_html(sc_t('dashboard_pages.address', 'Address')); ?></label>
                            <textarea class="form-control" id="org-address" name="address" rows="2"><?php echo esc_textarea((string) $o->address); ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="w-field mt-3">
                    <span class="w-field__label"><?php echo esc_html(sc_t('dashboard_pages.about', 'About')); ?></span>
                    <?php wp_editor((string) $o->description, 'organizer_description_editor', array('textarea_name' => 'description', 'media_buttons' => false, 'teeny' => true, 'textarea_rows' => 6)); ?>
                </div>
            </section>

            <section class="w-section" aria-labelledby="org-contact-title">
                <div class="w-section__head"><h2 id="org-contact-title"><?php echo esc_html(sc_t('dashboard_pages.contact_links', 'Contact & links')); ?></h2></div>
                <div class="w-fields w-fields--2">
                    <div class="w-field">
                        <label for="org-email"><?php echo esc_html(sc_t('general.email', 'Email')); ?></label>
                        <input type="email" class="form-control w-ltr" id="org-email" name="email" value="<?php echo esc_attr($o->email); ?>">
                    </div>
                    <div class="w-field">
                        <label for="org-phone"><?php echo esc_html(sc_t('general.phone', 'Phone')); ?></label>
                        <input type="tel" class="form-control w-ltr" id="org-phone" name="phone" value="<?php echo esc_attr($o->phone); ?>">
                    </div>
                    <div class="w-field">
                        <label for="org-website"><?php echo esc_html(sc_t('dashboard_pages.website', 'Website')); ?></label>
                        <input type="url" class="form-control w-ltr" id="org-website" name="website" value="<?php echo esc_attr($o->website); ?>" placeholder="https://">
                    </div>
                    <?php foreach ($networks as $key => $label): ?>
                    <div class="w-field">
                        <label for="org-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label>
                        <input type="url" class="form-control w-ltr" id="org-<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($social[$key] ?? ''); ?>" placeholder="https://">
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>

        <aside class="w-form-aside">
            <div class="w-aside-card">
                <span class="w-aside-card__title"><?php echo esc_html(sc_t('dashboard_pages.status', 'Status')); ?></span>
                <div class="w-switches">
                    <input type="hidden" name="is_active" value="0">
                    <label class="w-switch">
                        <input type="checkbox" name="is_active" value="1" <?php checked((int) $o->is_active, 1); ?>>
                        <span class="w-switch__track" aria-hidden="true"></span>
                        <span class="w-switch__text"><strong><?php echo esc_html(sc_t('dashboard_pages.active', 'Active')); ?></strong><span><?php echo esc_html(sc_t('dashboard_pages.organizer_active_help', 'Inactive organizers can’t be picked for new events.')); ?></span></span>
                    </label>
                    <div class="w-field">
                        <label for="org-order"><?php echo esc_html(sc_t('dashboard_pages.order', 'Order')); ?></label>
                        <input type="number" class="form-control" id="org-order" name="display_order" value="<?php echo (int) $o->display_order; ?>" min="0" step="1" inputmode="numeric">
                    </div>
                </div>
            </div>

            <?php if ($is_edit): ?>
            <div class="w-aside-card">
                <span class="w-aside-card__title"><?php echo esc_html(sprintf(sc_t('dashboard_pages.in_n_events', 'In %s events'), number_format_i18n(count($linked_events)))); ?></span>
                <?php if ($linked_events): ?>
                <div class="w-aside-links">
                    <?php foreach (array_slice($linked_events, 0, 8) as $ev): ?>
                        <a href="<?php echo esc_url($dashboard_url . 'event-edit?id=' . (int) $ev->id . '#people'); ?>"><?php echo esc_html($ev->title); ?><?php echo (int) $ev->upcoming ? ' · ' . esc_html(sc_t('dashboard_pages.upcoming', 'Upcoming')) : ''; ?></a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <p class="w-field__help mb-0 mt-2"><?php echo esc_html(sc_t('dashboard_pages.organizer_events_help', 'Add or remove organizers in an event’s People section.')); ?></p>
            </div>

            <div class="w-danger">
                <strong><?php echo esc_html(sc_t('dashboard_pages.delete_organizer', 'Delete organizer')); ?></strong>
                <p><?php echo esc_html(sc_t('dashboard_pages.delete_organizer_help', 'Removes them from every event. To keep past events as they are, switch them to inactive instead.')); ?></p>
                <button type="button" class="btn btn-sm" id="delete-organizer-btn"><?php echo esc_html(sc_t('dashboard_pages.delete_organizer', 'Delete organizer')); ?></button>
            </div>
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

<script>
jQuery(function ($) {
    'use strict';

    var isEdit = <?php echo $is_edit ? 'true' : 'false'; ?>;
    var organizerId = <?php echo (int) $o->id; ?>;
    var dashboardUrl = <?php echo $js($dashboard_url); ?>;
    var L = <?php echo $js(array(
        'name'      => sc_t('dashboard_pages.err_organizer_name', 'Enter the organizer’s name.'),
        'email'     => sc_t('dashboard_pages.err_email', 'Enter a valid email address.'),
        'url'       => sc_t('dashboard_pages.err_url', 'Enter a full link starting with https://'),
        'untitled'  => sc_t('dashboard_pages.new_organizer', 'New organizer'),
        'saved'     => sc_t('dashboard_pages.saved', 'Saved.'),
        'created'   => sc_t('dashboard_pages.organizer_created', 'Organizer created.'),
        'confirmDelete' => sc_t('dashboard_pages.confirm_delete_organizer', 'Delete %s? They are removed from every event. This cannot be undone.'),
        'failed'    => sc_t('errors.something_wrong', 'Something went wrong. Please try again.'),
        'saving'    => sc_t('dashboard_pages.saving', 'Saving…'),
        'fixErrors' => sc_t('dashboard_pages.fix_n', 'Fix %d to save'),
        'errorsTitle'   => sc_t('dashboard_pages.errors_title', '%d fields need attention before saving.'),
        'errorTitleOne' => sc_t('dashboard_pages.error_title_one', 'One field needs attention before saving.'),
        'leave'     => sc_t('dashboard_pages.unsaved_leave', 'You have unsaved changes.'),
        'savedAt'   => sc_t('dashboard_pages.saved_just_now', 'Saved just now'),
    )); ?>;
    var urlFields = ['website', <?php echo implode(', ', array_map('wp_json_encode', array_keys($networks))); ?>];

    $('#org-name').on('input', function () { $('[data-w-title]').text($.trim(this.value) || L.untitled); });

    var form = WDForm.create({
        form: document.getElementById('organizer-form'),
        i18n: { saving: L.saving, fixErrors: L.fixErrors, errorsTitle: L.errorsTitle, errorTitleOne: L.errorTitleOne, failed: L.failed, leave: L.leave, savedJustNow: L.savedAt },
        validate: function (v) {
            var e = [];
            if (!$.trim(v.name)) { e.push({ field: 'name', message: L.name }); }
            if ($.trim(v.email) && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test($.trim(v.email))) { e.push({ field: 'email', message: L.email }); }
            urlFields.forEach(function (k) {
                var val = $.trim(v[k] || '');
                if (val && !/^https?:\/\/\S+\.\S+/i.test(val)) { e.push({ field: k, message: L.url }); }
            });
            return e;
        },
        submit: function (fd) {
            fd.append('action', 'sc_save_organizer');
            fd.append('nonce', scDashboard.nonce);
            return fetch(scDashboard.ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
        },
        onSuccess: function (data, api) {
            if (!isEdit && data.redirect) { api.markClean(); window.location.href = data.redirect; return; }
            if (window.toastr) { toastr.success(data.message || L.saved); }
        }
    });

    if (/[?&]created=1/.test(location.search)) {
        if (window.toastr) { toastr.success(L.created); }
        history.replaceState(null, '', location.pathname + location.search.replace(/[?&]created=1/, '').replace(/^&/, '?'));
    }

    $('#delete-organizer-btn').on('click', function () {
        showDeleteConfirm(L.confirmDelete.replace('%s', $('#org-name').val())).then(function (r) {
            if (!r.isConfirmed) { return; }
            $.ajax({ url: scDashboard.ajaxurl, type: 'POST', data: { action: 'sc_bulk_organizers', op: 'delete', nonce: scDashboard.nonce, ids: [organizerId] } })
                .done(function (res) {
                    if (res.success) { form.markClean(); window.location.href = dashboardUrl + 'organizers'; }
                    else { showError(res.data && res.data.message ? res.data.message : L.failed); }
                })
                .fail(function () { showError(L.failed); });
        });
    });
});
</script>
