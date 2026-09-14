<?php
/**
 * Sponsor / partner form — shared by sponsor-create/edit and partner-create/edit.
 * Posts to sc_save_{type} (inc/admin-dashboard/brands-dashboard.php).
 *
 * Expects: $brand_type ('sponsor'|'partner'), $brand (object|null).
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$is_sponsor = $brand_type === 'sponsor';
$is_edit = !empty($brand);
$dashboard_url = home_url('/event-manager-dashboard/');
$b = $is_edit ? $brand : (object) array(
    'id' => 0, 'name' => '', 'title' => '', 'tier' => 'gold', 'description' => '', 'logo' => 0,
    'email' => '', 'phone' => '', 'website' => '', 'sort_order' => 0, 'is_active' => 1, 'updated_at' => '',
);
$pivot = $wpdb->prefix . ($is_sponsor ? 'sc_event_sponsors' : 'sc_event_partners');
$fk = $is_sponsor ? 'sponsor_id' : 'partner_id';
$linked_events = $is_edit ? $wpdb->get_results($wpdb->prepare(
    "SELECT e.id, e.title, COALESCE(e.end_date, e.start_date) >= %s AS upcoming
     FROM $pivot pv JOIN {$wpdb->prefix}sc_events e ON e.id = pv.event_id
     WHERE pv.$fk = %d ORDER BY e.start_date DESC",
    current_time('Y-m-d'),
    (int) $b->id
)) : array();

$logo_url = $b->logo ? (wp_get_attachment_image_url((int) $b->logo, 'medium') ?: '') : '';
$tiers = array(
    'diamond'  => sc_t('frontend.tier_diamond', 'Diamond'),
    'platinum' => sc_t('frontend.tier_platinum', 'Platinum'),
    'gold'     => sc_t('frontend.tier_gold', 'Gold'),
    'silver'   => sc_t('frontend.tier_silver', 'Silver'),
    'bronze'   => sc_t('frontend.tier_bronze', 'Bronze'),
);
$noun = $is_sponsor ? sc_t('dashboard_pages.sponsor', 'Sponsor') : sc_t('dashboard_pages.partner', 'Partner');
$new_label = $is_sponsor ? sc_t('dashboard_pages.new_sponsor', 'New sponsor') : sc_t('dashboard_pages.new_partner', 'New partner');
$save_label = $is_edit ? sc_t('dashboard_pages.save_changes', 'Save changes') : ($is_sponsor ? sc_t('dashboard_pages.create_sponsor', 'Create sponsor') : sc_t('dashboard_pages.create_partner', 'Create partner'));
$saved_label = $b->updated_at ? sprintf(sc_t('dashboard_pages.saved_at', 'Saved %s'), mysql2date('j M Y, H:i', $b->updated_at)) : '';
$js = function ($value) {
    return wp_json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
};
?>

<div id="main-content">
<div class="container-fluid">
<form id="brand-form" novalidate>
    <input type="hidden" name="id" value="<?php echo (int) $b->id; ?>">

    <div class="w-form-head">
        <div>
            <span class="w-form-head__eyebrow"><?php echo esc_html($is_edit ? $noun : $new_label); ?></span>
            <h1 data-w-title><?php echo esc_html($b->name !== '' ? $b->name : $new_label); ?></h1>
            <div class="w-form-head__meta">
                <?php if ($is_edit): ?><span data-w-saved><?php echo esc_html($saved_label); ?></span><?php endif; ?>
                <span class="w-dirty" data-w-dirty hidden><?php echo esc_html(sc_t('dashboard_pages.unsaved_changes', 'Unsaved changes')); ?></span>
            </div>
        </div>
        <div class="w-form-head__actions">
            <a class="btn btn-secondary" href="<?php echo esc_url($dashboard_url . $brand_type . 's'); ?>"><?php echo esc_html($is_edit ? ($is_sponsor ? sc_t('nav.sponsors', 'Sponsors') : sc_t('nav.partners', 'Partners')) : sc_t('dashboard_pages.cancel', 'Cancel')); ?></a>
            <button type="submit" class="btn btn-primary w-save-head" data-w-save><?php echo esc_html($save_label); ?></button>
        </div>
    </div>

    <div class="w-errors" data-w-errors hidden></div>

    <div class="w-form-layout w-form-layout--noseq">
        <div class="w-form-main">
            <section class="w-section" aria-labelledby="brand-main-title">
                <div class="w-section__head">
                    <h2 id="brand-main-title"><?php echo esc_html(sc_t('dashboard_pages.logo_and_name', 'Logo and name')); ?></h2>
                    <span class="w-section__hint"><?php echo esc_html(sc_t('dashboard_pages.logo_hint', 'A PNG with a transparent or white background looks best on the sponsor wall.')); ?></span>
                </div>
                <div class="w-speaker-top">
                    <div class="w-field w-speaker-top__photo">
                        <span class="w-field__label"><?php echo esc_html(sc_t('dashboard_pages.logo', 'Logo')); ?></span>
                        <div class="w-media w-media--square w-media--contain" data-w-media="<?php echo esc_attr(sc_t('dashboard_pages.logo', 'Logo')); ?>">
                            <input type="hidden" name="logo" value="<?php echo $b->logo ? (int) $b->logo : ''; ?>">
                            <div class="w-media__preview">
                                <img src="<?php echo esc_url($logo_url); ?>" alt=""<?php echo $logo_url ? '' : ' hidden'; ?>>
                                <span class="w-media__empty" role="button" tabindex="0"<?php echo $logo_url ? ' hidden' : ''; ?>>
                                    <i class="fa fa-picture-o fa-lg" aria-hidden="true"></i>
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
                            <label for="br-name"><?php echo esc_html(sc_t('dashboard_pages.name', 'Name')); ?><span class="w-req" aria-hidden="true">*</span></label>
                            <input type="text" class="form-control" id="br-name" name="name" value="<?php echo esc_attr($b->name); ?>" maxlength="255" required>
                            <p class="w-field__help"><?php echo esc_html(sc_t('dashboard_pages.brand_name_help', 'Shown when there is no logo, and as the logo’s alt text.')); ?></p>
                        </div>
                        <?php if ($is_sponsor): ?>
                        <div class="w-field">
                            <label for="br-title"><?php echo esc_html(sc_t('dashboard_pages.sponsorship_title', 'Sponsorship title')); ?></label>
                            <input type="text" class="form-control" id="br-title" name="title" value="<?php echo esc_attr((string) $b->title); ?>" maxlength="255" placeholder="<?php echo esc_attr(sc_t('dashboard_pages.sponsor_line_placeholder', 'e.g. Official Endo Partner')); ?>">
                            <p class="w-field__help"><?php echo esc_html(sc_t('dashboard_pages.sponsorship_title_help', 'For your records — public pages show the logo and tier.')); ?></p>
                        </div>
                        <?php endif; ?>
                        <div class="w-field">
                            <label for="br-website"><?php echo esc_html(sc_t('dashboard_pages.website', 'Website')); ?></label>
                            <input type="url" class="form-control w-ltr" id="br-website" name="website" value="<?php echo esc_attr($b->website); ?>" placeholder="https://">
                            <p class="w-field__help"><?php echo esc_html(sc_t('dashboard_pages.brand_website_help', 'The logo links here on the site.')); ?></p>
                        </div>
                    </div>
                </div>
            </section>

            <?php if ($is_sponsor): ?>
            <section class="w-section" aria-labelledby="brand-tier-title">
                <div class="w-section__head">
                    <h2 id="brand-tier-title"><?php echo esc_html(sc_t('dashboard_pages.tier', 'Tier')); ?></h2>
                    <span class="w-section__hint"><?php echo esc_html(sc_t('dashboard_pages.tier_hint', 'Higher tiers are shown first and larger. An event can override the tier for itself.')); ?></span>
                </div>
                <div class="w-choice" role="radiogroup" aria-labelledby="brand-tier-title">
                    <?php foreach ($tiers as $key => $label): ?>
                    <label class="w-choice__item"><input type="radio" name="tier" value="<?php echo esc_attr($key); ?>" <?php checked($b->tier ?: 'bronze', $key); ?>><span class="w-choice__box"><?php echo esc_html($label); ?></span></label>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <section class="w-section" aria-labelledby="brand-more-title">
                <div class="w-section__head">
                    <h2 id="brand-more-title"><?php echo esc_html(sc_t('dashboard_pages.details', 'Details')); ?></h2>
                    <span class="w-section__hint"><?php echo esc_html(sc_t('dashboard_pages.brand_contact_hint', 'Contact details stay in the dashboard.')); ?></span>
                </div>
                <div class="w-fields">
                    <div class="w-fields w-fields--2">
                        <div class="w-field">
                            <label for="br-email"><?php echo esc_html(sc_t('dashboard_pages.contact_email', 'Contact email')); ?></label>
                            <input type="email" class="form-control w-ltr" id="br-email" name="email" value="<?php echo esc_attr($b->email); ?>">
                        </div>
                        <div class="w-field">
                            <label for="br-phone"><?php echo esc_html(sc_t('dashboard_pages.contact_phone', 'Contact phone')); ?></label>
                            <input type="tel" class="form-control w-ltr" id="br-phone" name="phone" value="<?php echo esc_attr($b->phone); ?>">
                        </div>
                    </div>
                    <div class="w-field">
                        <label for="br-description"><?php echo esc_html(sc_t('dashboard_pages.notes', 'Notes')); ?></label>
                        <textarea class="form-control" id="br-description" name="description" rows="3"><?php echo esc_textarea((string) $b->description); ?></textarea>
                    </div>
                </div>
            </section>
        </div>

        <aside class="w-form-aside">
            <div class="w-aside-card">
                <span class="w-aside-card__title"><?php echo esc_html(sc_t('dashboard_pages.visibility', 'Visibility')); ?></span>
                <div class="w-switches">
                    <input type="hidden" name="is_active" value="0">
                    <label class="w-switch">
                        <input type="checkbox" name="is_active" value="1" <?php checked((int) $b->is_active, 1); ?>>
                        <span class="w-switch__track" aria-hidden="true"></span>
                        <span class="w-switch__text"><strong><?php echo esc_html(sc_t('dashboard_pages.active', 'Active')); ?></strong><span><?php echo esc_html(sc_t('dashboard_pages.brand_active_help', 'Inactive: left off the Sponsors page. Events it’s linked to still show it — remove it from those events to take it off.')); ?></span></span>
                    </label>
                    <div class="w-field">
                        <label for="br-order"><?php echo esc_html(sc_t('dashboard_pages.order', 'Order')); ?></label>
                        <input type="number" class="form-control" id="br-order" name="sort_order" value="<?php echo (int) $b->sort_order; ?>" min="0" step="1" inputmode="numeric">
                        <p class="w-field__help"><?php echo esc_html(sc_t('dashboard_pages.brand_order_help', 'Lower numbers come first within the same tier.')); ?></p>
                    </div>
                </div>
            </div>

            <?php if ($is_edit): ?>
            <div class="w-aside-card">
                <span class="w-aside-card__title"><?php echo esc_html(sprintf(sc_t('dashboard_pages.in_n_events', 'In %s events'), number_format_i18n(count($linked_events)))); ?></span>
                <?php if ($linked_events): ?>
                <div class="w-aside-links">
                    <?php foreach (array_slice($linked_events, 0, 8) as $ev): ?>
                        <a href="<?php echo esc_url($dashboard_url . 'event-edit?id=' . (int) $ev->id . '#sponsors'); ?>"><?php echo esc_html($ev->title); ?><?php echo (int) $ev->upcoming ? ' · ' . esc_html(sc_t('dashboard_pages.upcoming', 'Upcoming')) : ''; ?></a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <p class="w-field__help mb-0 mt-2"><?php echo esc_html(sc_t('dashboard_pages.brand_events_help', 'Add or remove it in an event’s Sponsors section.')); ?></p>
            </div>

            <div class="w-danger">
                <strong><?php echo esc_html(sprintf(sc_t('dashboard_pages.delete_x', 'Delete %s'), strtolower($noun))); ?></strong>
                <p><?php echo esc_html(sc_t('dashboard_pages.delete_brand_help', 'The logo comes off every event and the Sponsors page.')); ?></p>
                <button type="button" class="btn btn-sm" id="delete-brand-btn"><?php echo esc_html(sc_t('dashboard_pages.delete', 'Delete')); ?></button>
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

    var type = <?php echo $js($brand_type); ?>;
    var isEdit = <?php echo $is_edit ? 'true' : 'false'; ?>;
    var brandId = <?php echo (int) $b->id; ?>;
    var dashboardUrl = <?php echo $js($dashboard_url); ?>;
    var L = <?php echo $js(array(
        'name'      => sc_t('dashboard_pages.err_brand_name', 'Enter the name.'),
        'email'     => sc_t('dashboard_pages.err_email', 'Enter a valid email address.'),
        'url'       => sc_t('dashboard_pages.err_url', 'Enter a full link starting with https://'),
        'untitled'  => $new_label,
        'saved'     => sc_t('dashboard_pages.saved', 'Saved.'),
        'created'   => sc_t('dashboard_pages.created', 'Created.'),
        'confirmDelete' => sc_t('dashboard_pages.confirm_delete_brand', 'Delete %s? The logo comes off every event and the Sponsors page. This cannot be undone.'),
        'failed'    => sc_t('errors.something_wrong', 'Something went wrong. Please try again.'),
        'saving'    => sc_t('dashboard_pages.saving', 'Saving…'),
        'fixErrors' => sc_t('dashboard_pages.fix_n', 'Fix %d to save'),
        'errorsTitle'   => sc_t('dashboard_pages.errors_title', '%d fields need attention before saving.'),
        'errorTitleOne' => sc_t('dashboard_pages.error_title_one', 'One field needs attention before saving.'),
        'leave'     => sc_t('dashboard_pages.unsaved_leave', 'You have unsaved changes.'),
        'savedAt'   => sc_t('dashboard_pages.saved_just_now', 'Saved just now'),
    )); ?>;

    $('#br-name').on('input', function () { $('[data-w-title]').text($.trim(this.value) || L.untitled); });

    var form = WDForm.create({
        form: document.getElementById('brand-form'),
        i18n: { saving: L.saving, fixErrors: L.fixErrors, errorsTitle: L.errorsTitle, errorTitleOne: L.errorTitleOne, failed: L.failed, leave: L.leave, savedJustNow: L.savedAt },
        validate: function (v) {
            var e = [];
            if (!$.trim(v.name)) { e.push({ field: 'name', message: L.name }); }
            if ($.trim(v.email) && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test($.trim(v.email))) { e.push({ field: 'email', message: L.email }); }
            if ($.trim(v.website) && !/^https?:\/\/\S+\.\S+/i.test($.trim(v.website))) { e.push({ field: 'website', message: L.url }); }
            return e;
        },
        submit: function (fd) {
            fd.append('action', 'sc_save_' + type);
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

    $('#delete-brand-btn').on('click', function () {
        showDeleteConfirm(L.confirmDelete.replace('%s', $('#br-name').val())).then(function (r) {
            if (!r.isConfirmed) { return; }
            $.ajax({ url: scDashboard.ajaxurl, type: 'POST', data: { action: 'sc_bulk_' + type + 's', op: 'delete', nonce: scDashboard.nonce, ids: [brandId] } })
                .done(function (res) {
                    if (res.success) { form.markClean(); window.location.href = dashboardUrl + type + 's'; }
                    else { showError(res.data && res.data.message ? res.data.message : L.failed); }
                })
                .fail(function () { showError(L.failed); });
        });
    });
});
</script>
