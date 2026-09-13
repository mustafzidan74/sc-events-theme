<?php
/**
 * Speaker form — shared by speaker-create.php and speaker-edit.php.
 *
 * Posts to sc_save_speaker. Events are linked from the event form, so this
 * page only lists them.
 *
 * Expects: $speaker (object|null) — a row from sc_speakers.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$is_edit = !empty($speaker);
$dashboard_url = home_url('/event-manager-dashboard/');
$s = $is_edit ? $speaker : (object) array(
    'id' => 0, 'name' => '', 'slug' => '', 'title' => '', 'company' => '', 'bio' => '', 'photo' => 0,
    'email' => '', 'phone' => '', 'website' => '', 'social_links' => '', 'display_order' => 0, 'is_active' => 1, 'updated_at' => '',
);
$social = json_decode((string) $s->social_links, true);
$social = is_array($social) ? $social : array();

// Networks shown by default, plus any other network this speaker already has.
$networks = array(
    'linkedin' => 'LinkedIn', 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'twitter' => 'X (Twitter)', 'youtube' => 'YouTube', 'tiktok' => 'TikTok',
);
$more = array('github' => 'GitHub', 'whatsapp' => 'WhatsApp', 'snapchat' => 'Snapchat', 'pinterest' => 'Pinterest', 'tumblr' => 'Tumblr', 'reddit' => 'Reddit', 'medium' => 'Medium', 'vimeo' => 'Vimeo');
foreach ($more as $key => $label) {
    if (!empty($social[$key])) {
        $networks[$key] = $label;
    }
}

$linked_events = $is_edit ? $wpdb->get_results($wpdb->prepare(
    "SELECT e.id, e.title, e.start_date, COALESCE(e.end_date, e.start_date) >= %s AS upcoming
     FROM {$wpdb->prefix}sc_event_speakers p JOIN {$wpdb->prefix}sc_events e ON e.id = p.event_id
     WHERE p.speaker_id = %d ORDER BY e.start_date DESC",
    current_time('Y-m-d'),
    (int) $s->id
)) : array();

$photo_url = $s->photo ? (wp_get_attachment_image_url((int) $s->photo, 'medium') ?: '') : '';
$public_url = $s->slug ? home_url('/speaker/' . $s->slug . '/') : '';
$saved_label = $s->updated_at ? sprintf(sc_t('dashboard_pages.saved_at', 'Saved %s'), mysql2date('j M Y, H:i', $s->updated_at)) : '';
$save_label = $is_edit ? sc_t('dashboard_pages.save_changes', 'Save changes') : sc_t('dashboard_pages.create_speaker', 'Create speaker');
$js = function ($value) {
    return wp_json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
};
?>

<div id="main-content">
<div class="container-fluid">
<form id="speaker-form" novalidate>
    <input type="hidden" name="speaker_id" value="<?php echo (int) $s->id; ?>">

    <div class="w-form-head">
        <div>
            <span class="w-form-head__eyebrow"><?php echo esc_html($is_edit ? sc_t('dashboard_pages.speaker', 'Speaker') : sc_t('dashboard_pages.new_speaker', 'New speaker')); ?></span>
            <h1 data-w-title><?php echo esc_html($s->name !== '' ? $s->name : sc_t('dashboard_pages.new_speaker', 'New speaker')); ?></h1>
            <div class="w-form-head__meta">
                <?php if ($is_edit): ?><span data-w-saved><?php echo esc_html($saved_label); ?></span><?php endif; ?>
                <span class="w-dirty" data-w-dirty hidden><?php echo esc_html(sc_t('dashboard_pages.unsaved_changes', 'Unsaved changes')); ?></span>
            </div>
        </div>
        <div class="w-form-head__actions">
            <?php if ($is_edit && $public_url && (int) $s->is_active): ?>
                <a class="btn btn-secondary" href="<?php echo esc_url($public_url); ?>" target="_blank" rel="noopener" data-w-preview><i class="fa fa-external-link" aria-hidden="true"></i> <?php echo esc_html(sc_t('dashboard_pages.view_on_site', 'View on site')); ?></a>
            <?php else: ?>
                <a class="btn btn-secondary" href="<?php echo esc_url($dashboard_url . 'speakers'); ?>"><?php echo esc_html(sc_t('dashboard_pages.cancel', 'Cancel')); ?></a>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary w-save-head" data-w-save><?php echo esc_html($save_label); ?></button>
        </div>
    </div>

    <div class="w-errors" data-w-errors hidden></div>

    <div class="w-form-layout w-form-layout--noseq">
        <div class="w-form-main">
            <section class="w-section" id="profile" aria-labelledby="profile-title">
                <div class="w-section__head">
                    <h2 id="profile-title"><?php echo esc_html(sc_t('dashboard_pages.profile', 'Profile')); ?></h2>
                    <span class="w-section__hint"><?php echo esc_html(sc_t('dashboard_pages.speaker_profile_hint', 'Shown on the speaker’s page and on event pages')); ?></span>
                </div>
                <div class="w-speaker-top">
                    <div class="w-field w-speaker-top__photo">
                        <span class="w-field__label"><?php echo esc_html(sc_t('dashboard_pages.photo', 'Photo')); ?></span>
                        <div class="w-media w-media--square" data-w-media="<?php echo esc_attr(sc_t('dashboard_pages.photo', 'Photo')); ?>">
                            <input type="hidden" name="photo" value="<?php echo $s->photo ? (int) $s->photo : ''; ?>">
                            <div class="w-media__preview">
                                <img src="<?php echo esc_url($photo_url); ?>" alt=""<?php echo $photo_url ? '' : ' hidden'; ?>>
                                <span class="w-media__empty" role="button" tabindex="0"<?php echo $photo_url ? ' hidden' : ''; ?>>
                                    <i class="fa fa-user fa-lg" aria-hidden="true"></i>
                                    <?php echo esc_html(sc_t('dashboard_pages.choose_image', 'Choose an image')); ?>
                                </span>
                            </div>
                            <div class="w-media__actions">
                                <button type="button" class="btn btn-sm btn-secondary" data-w-media-choose><?php echo esc_html(sc_t('dashboard_pages.replace', 'Choose')); ?></button>
                                <button type="button" class="btn btn-sm btn-secondary" data-w-media-remove<?php echo $photo_url ? '' : ' hidden'; ?>><?php echo esc_html(sc_t('dashboard_pages.remove', 'Remove')); ?></button>
                            </div>
                        </div>
                    </div>
                    <div class="w-fields">
                        <div class="w-field">
                            <label for="sp-name"><?php echo esc_html(sc_t('dashboard_pages.name', 'Name')); ?><span class="w-req" aria-hidden="true">*</span></label>
                            <input type="text" class="form-control" id="sp-name" name="name" value="<?php echo esc_attr($s->name); ?>" maxlength="255" required>
                        </div>
                        <div class="w-field">
                            <label for="sp-title"><?php echo esc_html(sc_t('dashboard_pages.speaker_title', 'Title or talk')); ?></label>
                            <input type="text" class="form-control" id="sp-title" name="title" value="<?php echo esc_attr($s->title); ?>" maxlength="255">
                        </div>
                        <div class="w-field">
                            <label for="sp-company"><?php echo esc_html(sc_t('dashboard_pages.company', 'Company or university')); ?></label>
                            <input type="text" class="form-control" id="sp-company" name="company" value="<?php echo esc_attr($s->company); ?>" maxlength="255">
                        </div>
                    </div>
                </div>
                <div class="w-fields mt-3">
                    <?php if ($is_edit): ?>
                    <div class="w-field">
                        <label for="sp-slug"><?php echo esc_html(sc_t('dashboard_pages.url', 'Web address')); ?></label>
                        <div class="w-affix">
                            <span class="w-affix__text">/speaker/</span>
                            <input type="text" class="form-control w-ltr" id="sp-slug" name="slug" value="<?php echo esc_attr($s->slug); ?>" autocomplete="off" spellcheck="false">
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="w-field">
                        <span class="w-field__label"><?php echo esc_html(sc_t('dashboard_pages.bio', 'Bio')); ?></span>
                        <?php wp_editor($s->bio, 'speaker_bio_editor', array('textarea_name' => 'bio', 'media_buttons' => false, 'teeny' => true, 'textarea_rows' => 8)); ?>
                    </div>
                </div>
            </section>

            <section class="w-section" id="contact" aria-labelledby="contact-title">
                <div class="w-section__head">
                    <h2 id="contact-title"><?php echo esc_html(sc_t('dashboard_pages.contact_links', 'Contact & links')); ?></h2>
                    <span class="w-section__hint"><?php echo esc_html(sc_t('dashboard_pages.contact_hint', 'Email and phone stay private; links appear on the speaker page')); ?></span>
                </div>
                <div class="w-fields w-fields--2">
                    <div class="w-field">
                        <label for="sp-email"><?php echo esc_html(sc_t('general.email', 'Email')); ?></label>
                        <input type="email" class="form-control w-ltr" id="sp-email" name="email" value="<?php echo esc_attr($s->email); ?>">
                    </div>
                    <div class="w-field">
                        <label for="sp-phone"><?php echo esc_html(sc_t('general.phone', 'Phone')); ?></label>
                        <input type="tel" class="form-control w-ltr" id="sp-phone" name="phone" value="<?php echo esc_attr($s->phone); ?>">
                    </div>
                    <div class="w-field">
                        <label for="sp-website"><?php echo esc_html(sc_t('dashboard_pages.website', 'Website')); ?></label>
                        <input type="url" class="form-control w-ltr" id="sp-website" name="website" value="<?php echo esc_attr($s->website); ?>" placeholder="https://">
                    </div>
                    <?php foreach ($networks as $key => $label): ?>
                    <div class="w-field">
                        <label for="sp-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label>
                        <input type="url" class="form-control w-ltr" id="sp-<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($social[$key] ?? ''); ?>" placeholder="https://">
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>

        <aside class="w-form-aside">
            <div class="w-aside-card">
                <span class="w-aside-card__title"><?php echo esc_html(sc_t('dashboard_pages.visibility', 'Visibility')); ?></span>
                <div class="w-switches">
                    <input type="hidden" name="is_active" value="0">
                    <label class="w-switch">
                        <input type="checkbox" name="is_active" value="1" <?php checked((int) $s->is_active, 1); ?>>
                        <span class="w-switch__track" aria-hidden="true"></span>
                        <span class="w-switch__text"><strong><?php echo esc_html(sc_t('dashboard_pages.show_on_site', 'Show on site')); ?></strong><span><?php echo esc_html(sc_t('dashboard_pages.speaker_visible_help', 'Hidden speakers leave the speakers page and search.')); ?></span></span>
                    </label>
                    <div class="w-field">
                        <label for="sp-order"><?php echo esc_html(sc_t('dashboard_pages.order', 'Order')); ?></label>
                        <input type="number" class="form-control" id="sp-order" name="display_order" value="<?php echo (int) $s->display_order; ?>" min="0" step="1" inputmode="numeric">
                        <p class="w-field__help"><?php echo esc_html(sc_t('dashboard_pages.order_help', 'Lower numbers come first where lists use this order.')); ?></p>
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
                <p class="w-field__help mb-0 mt-2"><?php echo esc_html(sc_t('dashboard_pages.speaker_events_help', 'Add or remove speakers from an event’s Speakers section.')); ?></p>
            </div>

            <div class="w-danger">
                <strong><?php echo esc_html(sc_t('dashboard_pages.delete_speaker', 'Delete speaker')); ?></strong>
                <p><?php echo esc_html(sc_t('dashboard_pages.delete_speaker_help', 'Removes them from every event. To keep them but take them off the site, turn off “Show on site”.')); ?></p>
                <button type="button" class="btn btn-sm" id="delete-speaker-btn"><?php echo esc_html(sc_t('dashboard_pages.delete_speaker', 'Delete speaker')); ?></button>
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
    var speakerId = <?php echo (int) $s->id; ?>;
    var dashboardUrl = <?php echo $js($dashboard_url); ?>;
    var L = <?php echo $js(array(
        'name'      => sc_t('dashboard_pages.err_speaker_name', 'Enter the speaker’s name.'),
        'email'     => sc_t('dashboard_pages.err_email', 'Enter a valid email address.'),
        'url'       => sc_t('dashboard_pages.err_url', 'Enter a full link starting with https://'),
        'untitled'  => sc_t('dashboard_pages.new_speaker', 'New speaker'),
        'saved'     => sc_t('dashboard_pages.saved', 'Saved.'),
        'created'   => sc_t('dashboard_pages.speaker_created', 'Speaker created.'),
        'confirmDelete' => sc_t('dashboard_pages.confirm_delete_speaker', 'Delete %s? They are removed from every event they are in. This cannot be undone.'),
        'failed'    => sc_t('errors.something_wrong', 'Something went wrong. Please try again.'),
        'saving'    => sc_t('dashboard_pages.saving', 'Saving…'),
        'fixErrors' => sc_t('dashboard_pages.fix_n', 'Fix %d to save'),
        'errorsTitle'   => sc_t('dashboard_pages.errors_title', '%d fields need attention before saving.'),
        'errorTitleOne' => sc_t('dashboard_pages.error_title_one', 'One field needs attention before saving.'),
        'leave'     => sc_t('dashboard_pages.unsaved_leave', 'You have unsaved changes.'),
        'savedAt'   => sc_t('dashboard_pages.saved_just_now', 'Saved just now'),
    )); ?>;
    var urlFields = ['website', <?php echo implode(', ', array_map(function ($k) { return wp_json_encode($k); }, array_keys($networks))); ?>];

    $('#sp-name').on('input', function () { $('[data-w-title]').text($.trim(this.value) || L.untitled); });

    var form = WDForm.create({
        form: document.getElementById('speaker-form'),
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
            fd.append('action', 'sc_save_speaker');
            fd.append('nonce', scDashboard.nonce);
            return fetch(scDashboard.ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
        },
        onSuccess: function (data, api) {
            if (!isEdit && data.redirect) {
                api.markClean();
                window.location.href = data.redirect;
                return;
            }
            if (data.slug) {
                $('#sp-slug').val(data.slug);
                $('[data-w-preview]').attr('href', <?php echo $js(home_url('/speaker/')); ?> + data.slug + '/');
                api.markClean();
            }
            if (window.toastr) { toastr.success(data.message || L.saved); }
        }
    });

    if (/[?&]created=1/.test(location.search)) {
        if (window.toastr) { toastr.success(L.created); }
        history.replaceState(null, '', location.pathname + location.search.replace(/[?&]created=1/, '').replace(/^&/, '?'));
    }

    $('#delete-speaker-btn').on('click', function () {
        showDeleteConfirm(L.confirmDelete.replace('%s', $('#sp-name').val())).then(function (r) {
            if (!r.isConfirmed) { return; }
            $.ajax({ url: scDashboard.ajaxurl, type: 'POST', data: { action: 'sc_delete_speaker', nonce: scDashboard.nonce, speaker_id: speakerId } })
                .done(function (res) {
                    if (res.success) { form.markClean(); window.location.href = dashboardUrl + 'speakers'; }
                    else { showError(res.data && res.data.message ? res.data.message : L.failed); }
                })
                .fail(function () { showError(L.failed); });
        });
    });
});
</script>
