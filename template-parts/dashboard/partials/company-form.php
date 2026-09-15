<?php
/**
 * Company form — shared by company-attendee-add.php and company-attendee-edit.php.
 * Posts to sc_save_company_attendee (inc/admin-dashboard/company-dashboard.php).
 *
 * Expects: $company (object|null) — a row from sc_company_attendees.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$p = $wpdb->prefix;
$is_edit = !empty($company);
$dashboard_url = home_url('/event-manager-dashboard/');
$c = $is_edit ? $company : (object) array(
    'id' => 0, 'event_id' => isset($_GET['event_id']) ? absint($_GET['event_id']) : 0, 'ticket_id' => 0, 'company_code' => '',
    'company_name' => '', 'company_name_ar' => '', 'company_logo' => 0, 'industry' => '', 'company_size' => '', 'website' => '',
    'contact_name' => '', 'contact_title' => '', 'contact_email' => '', 'contact_phone' => '', 'country' => '', 'city' => '', 'address' => '',
    'booth_number' => '', 'sponsorship_level' => '', 'payment_status' => 'success', 'payment_method' => '', 'amount_paid' => 0,
    'status' => 'active', 'notes' => '', 'social_media' => '[]', 'products' => '[]', 'checked_in' => 0, 'checked_in_at' => null,
    'email_sent_at' => null, 'email_sent' => 0, 'created_at' => '', 'updated_at' => '',
);
$social = json_decode((string) $c->social_media, true);
$social = is_array($social) ? array_values($social) : array();
$products = json_decode((string) $c->products, true);
$products = is_array($products) ? array_values($products) : array();

$events = $wpdb->get_results($wpdb->prepare("SELECT id, title FROM {$p}sc_events WHERE status IN ('publish', 'completed', 'draft') OR id = %d ORDER BY start_date DESC LIMIT 200", (int) $c->event_id));
$tickets = $events ? $wpdb->get_results("SELECT id, event_id, name FROM {$p}sc_tickets WHERE (workshop_id IS NULL OR workshop_id = 0) AND event_id IN (" . implode(',', array_map('intval', wp_list_pluck($events, 'id'))) . ') ORDER BY sort_order, id') : array();
$booths = $wpdb->get_col("SELECT DISTINCT booth_number FROM {$p}sc_company_attendees WHERE booth_number <> '' ORDER BY booth_number LIMIT 200");
$levels = array_unique(array_merge(array('platinum', 'gold', 'silver', 'bronze', 'exhibitor'), $wpdb->get_col("SELECT DISTINCT sponsorship_level FROM {$p}sc_company_attendees WHERE sponsorship_level <> ''")));
$logo_url = $c->company_logo ? (wp_get_attachment_image_url((int) $c->company_logo, 'medium') ?: '') : '';
$badge_url = $is_edit ? home_url('/company-ticket/' . rawurlencode($c->company_code) . '/') : '';
$platforms = array('website' => 'Website', 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'twitter' => 'X (Twitter)', 'youtube' => 'YouTube', 'tiktok' => 'TikTok', 'whatsapp' => 'WhatsApp');
$save_label = $is_edit ? sc_t('dashboard_pages.save_changes', 'Save changes') : sc_t('dashboard_pages.register_company', 'Register company');
$js = function ($value) {
    return wp_json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
};
?>

<div id="main-content">
<div class="container-fluid">
<form id="company-form" novalidate autocomplete="off">
    <input type="hidden" name="id" value="<?php echo (int) $c->id; ?>">

    <div class="w-form-head">
        <div>
            <span class="w-form-head__eyebrow"><?php echo esc_html($is_edit ? sc_t('dashboard_pages.company', 'Company') . ' · ' . $c->company_code : sc_t('dashboard_pages.new_company', 'New company')); ?></span>
            <h1 data-w-title><?php echo esc_html($c->company_name !== '' ? $c->company_name : sc_t('dashboard_pages.new_company', 'New company')); ?></h1>
            <div class="w-form-head__meta">
                <?php if ($is_edit): ?>
                    <span class="w-tag <?php echo (int) $c->checked_in ? 'w-tag--teal' : ''; ?>"><?php echo esc_html((int) $c->checked_in ? sprintf(sc_t('dashboard_pages.checked_in_at', 'Checked in %s'), mysql2date('j M, H:i', $c->checked_in_at)) : sc_t('dashboard_pages.not_checked_in', 'Not checked in')); ?></span>
                <?php endif; ?>
                <span class="w-dirty" data-w-dirty hidden><?php echo esc_html(sc_t('dashboard_pages.unsaved_changes', 'Unsaved changes')); ?></span>
            </div>
        </div>
        <div class="w-form-head__actions">
            <a class="btn btn-secondary" href="<?php echo esc_url($dashboard_url . 'company-attendees' . ($c->event_id ? '?event_id=' . (int) $c->event_id : '')); ?>"><?php echo esc_html($is_edit ? sc_t('dashboard_pages.companies', 'Companies') : sc_t('dashboard_pages.cancel', 'Cancel')); ?></a>
            <?php if ($is_edit): ?><a class="btn btn-secondary" href="<?php echo esc_url($badge_url); ?>" target="_blank" rel="noopener"><i class="fa fa-qrcode" aria-hidden="true"></i> <?php echo esc_html(sc_t('dashboard_pages.open_badge', 'Open badge')); ?></a><?php endif; ?>
            <button type="submit" class="btn btn-primary w-save-head" data-w-save><?php echo esc_html($save_label); ?></button>
        </div>
    </div>

    <div class="w-errors" data-w-errors hidden></div>

    <div class="w-form-layout w-form-layout--noseq">
        <div class="w-form-main">
            <section class="w-section" aria-labelledby="co-reg">
                <div class="w-section__head"><h2 id="co-reg"><?php echo esc_html(sc_t('dashboard_pages.registration', 'Registration')); ?></h2></div>
                <div class="w-fields w-fields--2">
                    <div class="w-field">
                        <label for="co-event"><?php echo esc_html(sc_t('events.event', 'Event')); ?><span class="w-req" aria-hidden="true">*</span></label>
                        <select class="form-control" id="co-event" name="event_id" required>
                            <option value=""><?php echo esc_html(sc_t('dashboard_pages.choose_event', '— Choose the event —')); ?></option>
                            <?php foreach ($events as $ev): ?>
                                <option value="<?php echo (int) $ev->id; ?>" <?php selected((int) $c->event_id, (int) $ev->id); ?>><?php echo esc_html($ev->title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="w-field">
                        <label for="co-ticket"><?php echo esc_html(sc_t('dashboard_pages.ticket_optional', 'Ticket (optional)')); ?></label>
                        <select class="form-control" id="co-ticket" name="ticket_id"></select>
                    </div>
                    <div class="w-field">
                        <label for="co-booth"><?php echo esc_html(sc_t('dashboard_pages.booth', 'Booth')); ?></label>
                        <input type="text" class="form-control" id="co-booth" name="booth_number" value="<?php echo esc_attr($c->booth_number); ?>" list="co-booths" maxlength="50">
                        <datalist id="co-booths"><?php foreach ($booths as $bn): ?><option value="<?php echo esc_attr($bn); ?>"></option><?php endforeach; ?></datalist>
                    </div>
                    <div class="w-field">
                        <label for="co-level"><?php echo esc_html(sc_t('dashboard_pages.sponsorship', 'Sponsorship')); ?></label>
                        <input type="text" class="form-control" id="co-level" name="sponsorship_level" value="<?php echo esc_attr($c->sponsorship_level); ?>" list="co-levels" maxlength="50">
                        <datalist id="co-levels"><?php foreach ($levels as $lv): ?><option value="<?php echo esc_attr($lv); ?>"></option><?php endforeach; ?></datalist>
                    </div>
                </div>
            </section>

            <section class="w-section" aria-labelledby="co-company">
                <div class="w-section__head"><h2 id="co-company"><?php echo esc_html(sc_t('dashboard_pages.company', 'Company')); ?></h2></div>
                <div class="w-speaker-top">
                    <div class="w-field w-speaker-top__photo">
                        <span class="w-field__label"><?php echo esc_html(sc_t('dashboard_pages.logo', 'Logo')); ?></span>
                        <div class="w-media w-media--square w-media--contain" data-w-media="<?php echo esc_attr(sc_t('dashboard_pages.logo', 'Logo')); ?>">
                            <input type="hidden" name="company_logo" value="<?php echo $c->company_logo ? (int) $c->company_logo : ''; ?>">
                            <div class="w-media__preview">
                                <img src="<?php echo esc_url($logo_url); ?>" alt=""<?php echo $logo_url ? '' : ' hidden'; ?>>
                                <span class="w-media__empty" role="button" tabindex="0"<?php echo $logo_url ? ' hidden' : ''; ?>><i class="fa fa-building-o fa-lg" aria-hidden="true"></i> <?php echo esc_html(sc_t('dashboard_pages.choose_image', 'Choose an image')); ?></span>
                            </div>
                            <div class="w-media__actions">
                                <button type="button" class="btn btn-sm btn-secondary" data-w-media-choose><?php echo esc_html(sc_t('dashboard_pages.replace', 'Choose')); ?></button>
                                <button type="button" class="btn btn-sm btn-secondary" data-w-media-remove<?php echo $logo_url ? '' : ' hidden'; ?>><?php echo esc_html(sc_t('dashboard_pages.remove', 'Remove')); ?></button>
                            </div>
                        </div>
                    </div>
                    <div class="w-fields">
                        <div class="w-field">
                            <label for="co-name"><?php echo esc_html(sc_t('dashboard_pages.company_name', 'Company name')); ?><span class="w-req" aria-hidden="true">*</span></label>
                            <input type="text" class="form-control" id="co-name" name="company_name" value="<?php echo esc_attr($c->company_name); ?>" maxlength="255" required>
                        </div>
                        <div class="w-field">
                            <label for="co-name-ar"><?php echo esc_html(sc_t('dashboard_pages.company_name_ar', 'Name in Arabic')); ?></label>
                            <input type="text" class="form-control" id="co-name-ar" name="company_name_ar" value="<?php echo esc_attr((string) $c->company_name_ar); ?>" dir="rtl" maxlength="255">
                        </div>
                        <div class="w-field">
                            <label for="co-website"><?php echo esc_html(sc_t('dashboard_pages.website', 'Website')); ?></label>
                            <input type="url" class="form-control w-ltr" id="co-website" name="website" value="<?php echo esc_attr((string) $c->website); ?>" placeholder="https://">
                        </div>
                    </div>
                </div>
                <div class="w-fields w-fields--2 mt-3">
                    <div class="w-field">
                        <label for="co-industry"><?php echo esc_html(sc_t('dashboard_pages.industry', 'Industry')); ?></label>
                        <input type="text" class="form-control" id="co-industry" name="industry" value="<?php echo esc_attr((string) $c->industry); ?>" placeholder="<?php echo esc_attr(sc_t('dashboard_pages.industry_placeholder', 'e.g. Dental equipment')); ?>">
                    </div>
                    <div class="w-field">
                        <label for="co-size"><?php echo esc_html(sc_t('dashboard_pages.company_size', 'Company size')); ?></label>
                        <select class="form-control" id="co-size" name="company_size">
                            <option value=""><?php echo esc_html(sc_t('dashboard_pages.not_set', 'Not set')); ?></option>
                            <?php foreach (array('1-10', '11-50', '51-200', '201-500', '500+') as $size): ?>
                                <option value="<?php echo esc_attr($size); ?>" <?php selected((string) $c->company_size, $size); ?>><?php echo esc_html(sprintf(sc_t('dashboard_pages.n_employees', '%s employees'), $size)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </section>

            <section class="w-section" aria-labelledby="co-contact">
                <div class="w-section__head">
                    <h2 id="co-contact"><?php echo esc_html(sc_t('dashboard_pages.contact_person', 'Contact person')); ?></h2>
                    <span class="w-section__hint"><?php echo esc_html(sc_t('dashboard_pages.company_contact_hint', 'The badge email goes to this address.')); ?></span>
                </div>
                <div class="w-fields w-fields--2">
                    <div class="w-field">
                        <label for="co-cname"><?php echo esc_html(sc_t('dashboard_pages.name', 'Name')); ?></label>
                        <input type="text" class="form-control" id="co-cname" name="contact_name" value="<?php echo esc_attr((string) $c->contact_name); ?>">
                    </div>
                    <div class="w-field">
                        <label for="co-ctitle"><?php echo esc_html(sc_t('dashboard_pages.job_title', 'Job title')); ?></label>
                        <input type="text" class="form-control" id="co-ctitle" name="contact_title" value="<?php echo esc_attr((string) $c->contact_title); ?>">
                    </div>
                    <div class="w-field">
                        <label for="co-email"><?php echo esc_html(sc_t('general.email', 'Email')); ?><span class="w-req" aria-hidden="true">*</span></label>
                        <input type="email" class="form-control w-ltr" id="co-email" name="contact_email" value="<?php echo esc_attr($c->contact_email); ?>" required>
                    </div>
                    <div class="w-field">
                        <label for="co-phone"><?php echo esc_html(sc_t('general.phone', 'Phone')); ?></label>
                        <input type="tel" class="form-control w-ltr" id="co-phone" name="contact_phone" value="<?php echo esc_attr((string) $c->contact_phone); ?>">
                    </div>
                    <div class="w-field">
                        <label for="co-country"><?php echo esc_html(sc_t('dashboard_pages.country', 'Country')); ?></label>
                        <input type="text" class="form-control" id="co-country" name="country" value="<?php echo esc_attr((string) $c->country); ?>">
                    </div>
                    <div class="w-field">
                        <label for="co-city"><?php echo esc_html(sc_t('dashboard_pages.city', 'City')); ?></label>
                        <input type="text" class="form-control" id="co-city" name="city" value="<?php echo esc_attr((string) $c->city); ?>">
                    </div>
                </div>
                <div class="w-field mt-3">
                    <label for="co-address"><?php echo esc_html(sc_t('dashboard_pages.address', 'Address')); ?></label>
                    <textarea class="form-control" id="co-address" name="address" rows="2"><?php echo esc_textarea((string) $c->address); ?></textarea>
                </div>
            </section>

            <section class="w-section" aria-labelledby="co-links">
                <div class="w-section__head"><h2 id="co-links"><?php echo esc_html(sc_t('dashboard_pages.links_products', 'Links and products')); ?></h2></div>
                <div class="w-fields">
                    <div class="w-field">
                        <span class="w-field__label"><?php echo esc_html(sc_t('dashboard_pages.social_links', 'Social links')); ?></span>
                        <div class="w-repeat" id="social-rows"></div>
                        <button type="button" class="btn btn-sm btn-secondary mt-2" id="add-social"><i class="fa fa-plus" aria-hidden="true"></i> <?php echo esc_html(sc_t('dashboard_pages.add_link', 'Add link')); ?></button>
                    </div>
                    <div class="w-field">
                        <span class="w-field__label"><?php echo esc_html(sc_t('dashboard_pages.products', 'Products')); ?></span>
                        <div class="w-repeat" id="product-rows"></div>
                        <button type="button" class="btn btn-sm btn-secondary mt-2" id="add-product"><i class="fa fa-plus" aria-hidden="true"></i> <?php echo esc_html(sc_t('dashboard_pages.add_product', 'Add product')); ?></button>
                    </div>
                </div>
            </section>

            <section class="w-section" aria-labelledby="co-pay">
                <div class="w-section__head"><h2 id="co-pay"><?php echo esc_html(sc_t('payments.payment', 'Payment')); ?></h2></div>
                <div class="w-fields w-fields--2">
                    <div class="w-field">
                        <label for="co-pstatus"><?php echo esc_html(sc_t('dashboard_pages.payment_status', 'Payment status')); ?></label>
                        <select class="form-control" id="co-pstatus" name="payment_status">
                            <?php foreach (array('success' => sc_t('dashboard_pages.confirmed', 'Confirmed'), 'pending' => sc_t('dashboard_pages.pending', 'Pending'), 'failed' => sc_t('payments.failed', 'Failed'), 'refunded' => sc_t('payments.refunded', 'Refunded')) as $value => $label): ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($c->payment_status, $value); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="w-field__help"><?php echo esc_html(sc_t('dashboard_pages.company_payment_help', 'The scanner only admits companies with a confirmed payment.')); ?></p>
                    </div>
                    <div class="w-field">
                        <label for="co-amount"><?php echo esc_html(sprintf(sc_t('dashboard_pages.amount_paid_in', 'Amount paid (%s)'), get_option('sc_currency_code', 'EGP'))); ?></label>
                        <input type="number" class="form-control" id="co-amount" name="amount_paid" value="<?php echo (float) $c->amount_paid ? esc_attr((float) $c->amount_paid) : ''; ?>" min="0" step="0.01" inputmode="decimal">
                    </div>
                    <div class="w-field">
                        <label for="co-pmethod"><?php echo esc_html(sc_t('dashboard_pages.payment_method', 'Payment method')); ?></label>
                        <input type="text" class="form-control" id="co-pmethod" name="payment_method" value="<?php echo esc_attr((string) $c->payment_method); ?>" placeholder="<?php echo esc_attr(sc_t('dashboard_pages.payment_method_placeholder', 'e.g. Bank transfer')); ?>">
                    </div>
                </div>
                <div class="w-field mt-3">
                    <label for="co-notes"><?php echo esc_html(sc_t('dashboard_pages.notes', 'Notes')); ?></label>
                    <textarea class="form-control" id="co-notes" name="notes" rows="2"><?php echo esc_textarea((string) $c->notes); ?></textarea>
                    <p class="w-field__help"><?php echo esc_html(sc_t('dashboard_pages.notes_private', 'Only visible in the dashboard.')); ?></p>
                </div>
            </section>
        </div>

        <aside class="w-form-aside">
            <div class="w-aside-card">
                <span class="w-aside-card__title"><?php echo esc_html(sc_t('dashboard_pages.status', 'Status')); ?></span>
                <div class="w-choice w-choice--stack" role="radiogroup">
                    <label class="w-choice__item"><input type="radio" name="status" value="active" <?php checked($c->status !== 'cancelled'); ?>><span class="w-choice__box"><span><?php echo esc_html(sc_t('dashboard_pages.active', 'Active')); ?><span class="w-choice__sub"><?php echo esc_html(sc_t('dashboard_pages.company_active_help', 'The badge works at the entrance')); ?></span></span></span></label>
                    <label class="w-choice__item"><input type="radio" name="status" value="cancelled" <?php checked($c->status, 'cancelled'); ?>><span class="w-choice__box"><span><?php echo esc_html(sc_t('dashboard_pages.status_cancelled', 'Cancelled')); ?><span class="w-choice__sub"><?php echo esc_html(sc_t('dashboard_pages.company_cancelled_help', 'Kept for records; the badge is refused')); ?></span></span></span></label>
                </div>
            </div>

            <?php if (!$is_edit): ?>
            <div class="w-aside-card">
                <span class="w-aside-card__title"><?php echo esc_html(sc_t('dashboard_pages.after_saving', 'After saving')); ?></span>
                <label class="w-switch">
                    <input type="checkbox" name="send_email" value="1" checked>
                    <span class="w-switch__track" aria-hidden="true"></span>
                    <span class="w-switch__text"><strong><?php echo esc_html(sc_t('dashboard_pages.send_badge', 'Send the badge')); ?></strong><span><?php echo esc_html(sc_t('dashboard_pages.send_badge_help', 'On WhatsApp with the QR code, and by email when email is on.')); ?></span></span>
                </label>
            </div>
            <?php else: ?>
            <div class="w-aside-card">
                <span class="w-aside-card__title"><?php echo esc_html(sc_t('dashboard_pages.badge', 'Badge')); ?></span>
                <p class="w-ticket-code w-ltr"><?php echo esc_html($c->company_code); ?></p>
                <div class="w-aside-actions">
                    <a class="btn btn-sm btn-secondary" href="<?php echo esc_url($badge_url); ?>" target="_blank" rel="noopener"><i class="fa fa-qrcode" aria-hidden="true"></i> <?php echo esc_html(sc_t('dashboard_pages.open_badge', 'Open badge')); ?></a>
                    <button type="button" class="btn btn-sm btn-secondary" data-op="email"><i class="fa fa-paper-plane-o" aria-hidden="true"></i> <?php echo esc_html(sc_t('dashboard_pages.send_badge', 'Send the badge')); ?></button>
                    <?php if ((int) $c->checked_in): ?>
                        <button type="button" class="btn btn-sm btn-secondary" data-op="undo"><?php echo esc_html(sc_t('dashboard_pages.undo_checkin', 'Undo check-in')); ?></button>
                    <?php else: ?>
                        <button type="button" class="btn btn-sm btn-primary" data-op="check_in"><i class="fa fa-check" aria-hidden="true"></i> <?php echo esc_html(sc_t('dashboard_pages.check_in', 'Check in')); ?></button>
                    <?php endif; ?>
                </div>
                <p class="w-field__help mt-2 mb-0"><?php
                    echo esc_html(sprintf(sc_t('dashboard_pages.registered_on', 'Registered %s'), mysql2date('j M Y, H:i', $c->created_at)));
                    if ((int) $c->email_sent && $c->email_sent_at) {
                        echo '<br>' . esc_html(sprintf(sc_t('dashboard_pages.badge_emailed', 'Badge emailed %s'), mysql2date('j M Y, H:i', $c->email_sent_at)));
                    }
                ?></p>
            </div>
            <div class="w-danger">
                <strong><?php echo esc_html(sc_t('dashboard_pages.delete_company', 'Delete company')); ?></strong>
                <p><?php echo esc_html(sc_t('dashboard_pages.delete_company_help', 'The badge stops working and the check-in history goes too. To keep the record, set it to Cancelled.')); ?></p>
                <button type="button" class="btn btn-sm" data-op="delete"><?php echo esc_html(sc_t('dashboard_pages.delete', 'Delete')); ?></button>
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
    var companyId = <?php echo (int) $c->id; ?>;
    var dashboardUrl = <?php echo $js($dashboard_url); ?>;
    var tickets = <?php echo $js(array_map(function ($t) { return array('id' => (int) $t->id, 'event_id' => (int) $t->event_id, 'name' => $t->name); }, $tickets)); ?>;
    var selectedTicket = <?php echo (int) $c->ticket_id; ?>;
    var platforms = <?php echo $js($platforms); ?>;
    var social = <?php echo $js($social); ?>;
    var products = <?php echo $js($products); ?>;
    var L = <?php echo $js(array(
        'noTicket'   => sc_t('dashboard_pages.no_ticket', 'No ticket'),
        'url'        => sc_t('dashboard_pages.link', 'Link'),
        'platform'   => sc_t('dashboard_pages.platform', 'Platform'),
        'remove'     => sc_t('dashboard_pages.remove', 'Remove'),
        'pname'      => sc_t('dashboard_pages.product_name', 'Product name'),
        'pdesc'      => sc_t('dashboard_pages.short_description', 'Short description'),
        'untitled'   => sc_t('dashboard_pages.new_company', 'New company'),
        'errEvent'   => sc_t('dashboard_pages.err_event', 'Choose the event.'),
        'errName'    => sc_t('dashboard_pages.err_company_name', 'Enter the company name.'),
        'errEmail'   => sc_t('dashboard_pages.err_email', 'Enter a valid email address.'),
        'errUrl'     => sc_t('dashboard_pages.err_url', 'Enter a full link starting with https://'),
        'saved'      => sc_t('dashboard_pages.saved', 'Saved.'),
        'created'    => sc_t('dashboard_pages.company_registered', 'Company registered.'),
        'confirmDelete' => sc_t('dashboard_pages.confirm_delete_company', 'Delete %s? Their badge stops working. This cannot be undone.'),
        'confirmEmail'  => sc_t('dashboard_pages.confirm_send_badge_one', 'Send the badge to %s?'),
        'failed'     => sc_t('errors.something_wrong', 'Something went wrong. Please try again.'),
        'saving'     => sc_t('dashboard_pages.saving', 'Saving…'),
        'fixErrors'  => sc_t('dashboard_pages.fix_n', 'Fix %d to save'),
        'errorsTitle'   => sc_t('dashboard_pages.errors_title', '%d fields need attention before saving.'),
        'errorTitleOne' => sc_t('dashboard_pages.error_title_one', 'One field needs attention before saving.'),
        'leave'      => sc_t('dashboard_pages.unsaved_leave', 'You have unsaved changes.'),
    )); ?>;
    var esc = function (v) { return String(v == null ? '' : v).replace(/[&<>"']/g, function (ch) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[ch]; }); };
    var formEl = document.getElementById('company-form');

    function fillTickets() {
        var ev = +$('#co-event').val() || 0;
        var list = tickets.filter(function (t) { return t.event_id === ev; });
        $('#co-ticket').html('<option value="">' + esc(L.noTicket) + '</option>' + list.map(function (t) {
            return '<option value="' + t.id + '"' + (t.id === selectedTicket ? ' selected' : '') + '>' + esc(t.name) + '</option>';
        }).join('')).prop('disabled', !ev);
    }
    $('#co-event').on('change', function () { selectedTicket = 0; fillTickets(); });
    fillTickets();

    var socialIdx = 0, productIdx = 0;
    function socialRow(row) {
        var i = socialIdx++;
        var opts = Object.keys(platforms).map(function (k) { return '<option value="' + k + '"' + (row.platform === k ? ' selected' : '') + '>' + esc(platforms[k]) + '</option>'; }).join('');
        return '<div class="w-repeat__row"><select class="form-control w-repeat__narrow" name="social_media[' + i + '][platform]" aria-label="' + esc(L.platform) + '">' + opts + '</select>' +
            '<input type="url" class="form-control w-ltr" name="social_media[' + i + '][url]" value="' + esc(row.url || '') + '" placeholder="https://" aria-label="' + esc(L.url) + '">' +
            '<button type="button" class="w-icon-btn w-icon-btn--danger" data-remove aria-label="' + esc(L.remove) + '">&times;</button></div>';
    }
    function productRow(row) {
        var i = productIdx++;
        return '<div class="w-repeat__row w-repeat__row--stack"><input type="hidden" name="products[' + i + '][image]" value="' + esc(row.image || '') + '">' +
            '<input type="text" class="form-control" name="products[' + i + '][name]" value="' + esc(row.name || '') + '" placeholder="' + esc(L.pname) + '" aria-label="' + esc(L.pname) + '">' +
            '<input type="text" class="form-control" name="products[' + i + '][description]" value="' + esc(row.description || '') + '" placeholder="' + esc(L.pdesc) + '" aria-label="' + esc(L.pdesc) + '">' +
            '<button type="button" class="w-icon-btn w-icon-btn--danger" data-remove aria-label="' + esc(L.remove) + '">&times;</button></div>';
    }
    $('#social-rows').html(social.map(socialRow).join(''));
    $('#product-rows').html(products.map(productRow).join(''));
    function changed() { formEl.dispatchEvent(new Event('input', { bubbles: true })); }
    $('#add-social').on('click', function () { $('#social-rows').append(socialRow({ platform: 'website' })); changed(); $('#social-rows .w-repeat__row:last input').trigger('focus'); });
    $('#add-product').on('click', function () { $('#product-rows').append(productRow({})); changed(); $('#product-rows .w-repeat__row:last input[type=text]').first().trigger('focus'); });
    $(formEl).on('click', '[data-remove]', function () { $(this).closest('.w-repeat__row').remove(); changed(); });

    $('#co-name').on('input', function () { $('[data-w-title]').text($.trim(this.value) || L.untitled); });

    var form = WDForm.create({
        form: formEl,
        i18n: { saving: L.saving, fixErrors: L.fixErrors, errorsTitle: L.errorsTitle, errorTitleOne: L.errorTitleOne, failed: L.failed, leave: L.leave },
        validate: function (v) {
            var e = [];
            if (!v.event_id) { e.push({ field: 'event_id', message: L.errEvent }); }
            if (!$.trim(v.company_name)) { e.push({ field: 'company_name', message: L.errName }); }
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test($.trim(v.contact_email || ''))) { e.push({ field: 'contact_email', message: L.errEmail }); }
            if ($.trim(v.website) && !/^https?:\/\/\S+\.\S+/i.test($.trim(v.website))) { e.push({ field: 'website', message: L.errUrl }); }
            return e;
        },
        submit: function (fd) {
            fd.append('action', 'sc_save_company_attendee');
            fd.append('nonce', scDashboard.nonce);
            return fetch(scDashboard.ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
        },
        onSuccess: function (data, api) {
            api.markClean();
            if (!isEdit && data.redirect) {
                try { sessionStorage.setItem('scCompanySaved', data.message || L.created); } catch (x) { /* storage blocked */ }
                window.location.href = data.redirect;
                return;
            }
            if (window.toastr) { toastr.success(data.message || L.saved); }
        }
    });

    try {
        var saved = sessionStorage.getItem('scCompanySaved');
        if (saved) { sessionStorage.removeItem('scCompanySaved'); if (window.toastr) { toastr.success(saved); } }
    } catch (x) { /* storage blocked */ }
    if (/[?&]created=1/.test(location.search)) {
        history.replaceState(null, '', location.pathname + location.search.replace(/[?&]created=1/, '').replace(/^&/, '?'));
    }

    function op(name) {
        return $.ajax({ url: scDashboard.ajaxurl, type: 'POST', data: { action: 'sc_company_bulk', nonce: scDashboard.nonce, op: name, ids: [companyId] } });
    }
    $(formEl).on('click', '[data-op]', function () {
        var name = $(this).data('op');
        var company = $('#co-name').val();
        var ask = name === 'delete' ? showDeleteConfirm(L.confirmDelete.replace('%s', company))
            : name === 'email' ? showConfirm(L.confirmEmail.replace('%s', $('#co-email').val()))
            : Promise.resolve({ isConfirmed: true });
        ask.then(function (r) {
            if (!r.isConfirmed) { return; }
            op(name).done(function (res) {
                if (!res.success) { showError(res.data && res.data.message || L.failed); return; }
                if (name === 'delete') { form.markClean(); window.location.href = dashboardUrl + 'company-attendees'; return; }
                (res.data.failed ? showWarning : showSuccess)(res.data.message);
                if (name !== 'email') { form.markClean(); setTimeout(function () { window.location.reload(); }, 500); }
            }).fail(function () { showError(L.failed); });
        });
    });
});
</script>
