<?php
/**
 * Booth booking form — shared by booth-booking-create.php and booth-booking-edit.php.
 * Posts to sc_booth_booking_save (inc/admin-dashboard/booths-dashboard.php).
 *
 * Expects: $booking (object|null).
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$p = $wpdb->prefix;
$is_edit = !empty($booking);
$dashboard_url = home_url('/event-manager-dashboard/');
$pre_booth = !$is_edit && isset($_GET['booth_id']) ? $wpdb->get_row($wpdb->prepare("SELECT id, event_id FROM {$p}sc_booths WHERE id = %d", absint($_GET['booth_id']))) : null;
$pre_company = !$is_edit && isset($_GET['company_id']) ? $wpdb->get_row($wpdb->prepare("SELECT id, event_id FROM {$p}sc_company_attendees WHERE id = %d", absint($_GET['company_id']))) : null;
$bk = $is_edit ? $booking : (object) array(
    'id' => 0, 'booking_ref' => '', 'event_id' => $pre_booth ? (int) $pre_booth->event_id : ($pre_company ? (int) $pre_company->event_id : (isset($_GET['event_id']) ? absint($_GET['event_id']) : 0)),
    'booth_id' => $pre_booth ? (int) $pre_booth->id : 0, 'company_attendee_id' => $pre_company ? (int) $pre_company->id : 0,
    'base_price' => '', 'extras_price' => '', 'discount_amount' => '', 'tax_amount' => '', 'total_amount' => 0, 'deposit_required' => '', 'deposit_paid' => 0, 'balance_paid' => 0,
    'balance_due_date' => '', 'setup_date' => '', 'teardown_date' => '', 'start_date' => '', 'end_date' => '', 'company_legal_name' => '', 'commercial_registry_no' => '', 'vat_number' => '',
    'contract_signed' => 0, 'contract_signer_name' => '', 'contract_signer_title' => '', 'special_requests' => '', 'notes' => '', 'internal_notes' => '',
    'status' => 'pending', 'payment_status' => 'pending', 'cancellation_reason' => '', 'checked_in_at' => null, 'confirmed_at' => null, 'created_at' => '',
);

// Events that have booths, plus this booking's event.
$events = $wpdb->get_results($wpdb->prepare(
    "SELECT e.id, e.title, e.start_date, e.end_date FROM {$p}sc_events e WHERE e.id = %d OR EXISTS (SELECT 1 FROM {$p}sc_booths b WHERE b.event_id = e.id) ORDER BY e.start_date DESC",
    (int) $bk->event_id
));
$event_ids = array_map('intval', wp_list_pluck($events, 'id'));
$booths = $event_ids ? $wpdb->get_results($wpdb->prepare(
    "SELECT b.id, b.event_id, b.booth_number, b.booth_name, b.status, COALESCE(b.custom_price, t.base_price, 0) price, t.name type_name,
            t.deposit_amount, t.deposit_percentage, COALESCE(b.custom_width, t.width_meters) w, COALESCE(b.custom_depth, t.depth_meters) d
     FROM {$p}sc_booths b LEFT JOIN {$p}sc_booth_types t ON t.id = b.booth_type_id
     WHERE b.event_id IN (" . implode(',', $event_ids) . ") AND (b.status = 'available' OR b.id = %d) ORDER BY LENGTH(b.booth_number), b.booth_number",
    (int) $bk->booth_id
)) : array();
$companies = $event_ids ? $wpdb->get_results($wpdb->prepare(
    "SELECT id, event_id, company_name, contact_name, booth_number FROM {$p}sc_company_attendees WHERE event_id IN (" . implode(',', $event_ids) . ") AND (status = 'active' OR id = %d) ORDER BY company_name",
    (int) $bk->company_attendee_id
)) : array();

$currency = get_option('sc_currency_code', 'EGP');
$num = function ($v) {
    return $v === null || $v === '' || (float) $v == 0 ? '' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
};
$status_labels = array(
    'pending'   => array(sc_t('booths.pending', 'Pending'), sc_t('booths.pending_help', 'Booth reserved while you agree terms')),
    'confirmed' => array(sc_t('booths.confirmed', 'Confirmed'), sc_t('booths.confirmed_help', 'Agreed; the booth is booked')),
    'active'    => array(sc_t('booths.set_up', 'At the booth'), sc_t('booths.active_help', 'The exhibitor has set up')),
    'completed' => array(sc_t('booths.completed', 'Completed'), sc_t('booths.completed_help', 'Event over; booth handed back')),
    'cancelled' => array(sc_t('dashboard_pages.status_cancelled', 'Cancelled'), sc_t('booths.cancelled_help', 'The booth is free again')),
    'no_show'   => array(sc_t('booths.no_show', 'No-show'), sc_t('booths.no_show_help', 'Booked but never came')),
);
$payment_labels = array('pending' => sc_t('booths.unpaid', 'Unpaid'), 'deposit_paid' => sc_t('booths.deposit_paid', 'Deposit paid'), 'fully_paid' => sc_t('booths.paid', 'Paid'), 'overdue' => sc_t('booths.overdue', 'Overdue'), 'refunded' => sc_t('payments.refunded', 'Refunded'), 'cancelled' => sc_t('dashboard_pages.status_cancelled', 'Cancelled'));
$save_label = $is_edit ? sc_t('dashboard_pages.save_changes', 'Save changes') : sc_t('booths.create_booking', 'Create booking');
$date_val = function ($v) {
    return $v && $v !== '0000-00-00' ? substr((string) $v, 0, 10) : '';
};
$js = function ($value) {
    return wp_json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
};
?>

<div id="main-content">
<div class="container-fluid">
<form id="booking-form" novalidate autocomplete="off">
    <input type="hidden" name="id" value="<?php echo (int) $bk->id; ?>">

    <div class="w-form-head">
        <div>
            <span class="w-form-head__eyebrow"><?php echo esc_html($is_edit ? sc_t('booths.booking', 'Booking') . ' · ' . $bk->booking_ref : sc_t('booths.new_booking', 'New booking')); ?></span>
            <h1 data-w-title><?php echo esc_html(sc_t('booths.booth_booking', 'Booth booking')); ?></h1>
            <div class="w-form-head__meta">
                <?php if ($is_edit): ?>
                    <span class="w-tag"><?php echo esc_html($status_labels[$bk->status][0] ?? $bk->status); ?></span>
                    <span class="w-tag"><?php echo esc_html($payment_labels[$bk->payment_status] ?? $bk->payment_status); ?></span>
                <?php endif; ?>
                <span class="w-dirty" data-w-dirty hidden><?php echo esc_html(sc_t('dashboard_pages.unsaved_changes', 'Unsaved changes')); ?></span>
            </div>
        </div>
        <div class="w-form-head__actions">
            <a class="btn btn-secondary" href="<?php echo esc_url($dashboard_url . 'booth-bookings' . ($bk->event_id ? '?event_id=' . (int) $bk->event_id : '')); ?>"><?php echo esc_html($is_edit ? sc_t('booths.bookings', 'Bookings') : sc_t('dashboard_pages.cancel', 'Cancel')); ?></a>
            <button type="submit" class="btn btn-primary w-save-head" data-w-save><?php echo esc_html($save_label); ?></button>
        </div>
    </div>

    <div class="w-errors" data-w-errors hidden></div>

    <?php if (!$events): ?>
        <div class="w-state">
            <strong><?php echo esc_html(sc_t('booths.no_booths_to_book', 'There are no booths to book yet')); ?></strong>
            <p><?php echo esc_html(sc_t('booths.no_booths_to_book_help', 'Add booth types and booths for the event first.')); ?></p>
            <a class="btn btn-primary" href="<?php echo esc_url($dashboard_url . 'booth-types'); ?>"><?php echo esc_html(sc_t('booths.booth_types', 'Booth types')); ?></a>
        </div>
    <?php else: ?>
    <div class="w-form-layout w-form-layout--noseq">
        <div class="w-form-main">
            <section class="w-section" aria-labelledby="bk-who">
                <div class="w-section__head"><h2 id="bk-who"><?php echo esc_html(sc_t('booths.who_where', 'Company and booth')); ?></h2></div>
                <div class="w-fields">
                    <div class="w-field">
                        <label for="bk-event"><?php echo esc_html(sc_t('events.event', 'Event')); ?><span class="w-req" aria-hidden="true">*</span></label>
                        <select class="form-control" id="bk-event">
                            <?php foreach ($events as $ev): ?>
                                <option value="<?php echo (int) $ev->id; ?>" <?php selected((int) $bk->event_id, (int) $ev->id); ?>><?php echo esc_html($ev->title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="w-fields w-fields--2">
                        <div class="w-field">
                            <label for="bk-company"><?php echo esc_html(sc_t('dashboard_pages.company', 'Company')); ?><span class="w-req" aria-hidden="true">*</span></label>
                            <select class="form-control" id="bk-company" name="company_attendee_id" required></select>
                            <p class="w-field__help" id="bk-company-help"></p>
                        </div>
                        <div class="w-field">
                            <label for="bk-booth"><?php echo esc_html(sc_t('booths.booth', 'Booth')); ?><span class="w-req" aria-hidden="true">*</span></label>
                            <select class="form-control" id="bk-booth" name="booth_id" required></select>
                            <p class="w-field__help" id="bk-booth-help"></p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="w-section" aria-labelledby="bk-price">
                <div class="w-section__head"><h2 id="bk-price"><?php echo esc_html(sc_t('booths.price', 'Price')); ?></h2><span class="w-section__hint"><?php echo esc_html(sprintf(sc_t('booths.amounts_in', 'Amounts in %s'), $currency)); ?></span></div>
                <div class="w-fields">
                    <div class="w-fields w-fields--2">
                        <div class="w-field">
                            <label for="bk-base"><?php echo esc_html(sc_t('booths.booth_price', 'Booth price')); ?></label>
                            <input type="number" class="form-control" id="bk-base" name="base_price" value="<?php echo esc_attr($num($bk->base_price)); ?>" min="0" step="0.01" inputmode="decimal">
                        </div>
                        <div class="w-field">
                            <label for="bk-extras"><?php echo esc_html(sc_t('booths.extras', 'Extras')); ?></label>
                            <input type="number" class="form-control" id="bk-extras" name="extras_price" value="<?php echo esc_attr($num($bk->extras_price)); ?>" min="0" step="0.01" inputmode="decimal" placeholder="0">
                            <p class="w-field__help"><?php echo esc_html(sc_t('booths.extras_help', 'Furniture, extra power, branding…')); ?></p>
                        </div>
                        <div class="w-field">
                            <label for="bk-discount"><?php echo esc_html(sc_t('booths.discount', 'Discount')); ?></label>
                            <input type="number" class="form-control" id="bk-discount" name="discount_amount" value="<?php echo esc_attr($num($bk->discount_amount)); ?>" min="0" step="0.01" inputmode="decimal" placeholder="0">
                        </div>
                        <div class="w-field">
                            <label for="bk-tax"><?php echo esc_html(sc_t('booths.tax', 'Tax')); ?></label>
                            <div class="w-affix">
                                <input type="number" class="form-control" id="bk-tax" name="tax_amount" value="<?php echo esc_attr($num($bk->tax_amount)); ?>" min="0" step="0.01" inputmode="decimal" placeholder="0">
                                <button type="button" class="w-affix__btn" id="bk-vat14"><?php echo esc_html(sc_t('booths.add_vat', 'Add 14% VAT')); ?></button>
                            </div>
                        </div>
                    </div>
                    <div class="w-totalbar"><span><?php echo esc_html(sc_t('booths.total', 'Total')); ?></span><strong class="w-ltr" id="bk-total"></strong></div>
                </div>
            </section>

            <section class="w-section" aria-labelledby="bk-pay">
                <div class="w-section__head"><h2 id="bk-pay"><?php echo esc_html(sc_t('booths.payments', 'Payments')); ?></h2><span class="w-section__hint" id="bk-due-hint"></span></div>
                <div class="w-fields w-fields--2">
                    <div class="w-field">
                        <label for="bk-dep-req"><?php echo esc_html(sc_t('booths.deposit_required', 'Deposit required')); ?></label>
                        <input type="number" class="form-control" id="bk-dep-req" name="deposit_required" value="<?php echo esc_attr($num($bk->deposit_required)); ?>" min="0" step="0.01" inputmode="decimal">
                    </div>
                    <div class="w-field">
                        <label for="bk-due-date"><?php echo esc_html(sc_t('booths.balance_due_date', 'Balance due by')); ?></label>
                        <input type="date" class="form-control" id="bk-due-date" name="balance_due_date" value="<?php echo esc_attr($date_val($bk->balance_due_date)); ?>">
                    </div>
                    <div class="w-field">
                        <label for="bk-dep-paid"><?php echo esc_html(sc_t('booths.deposit_received', 'Deposit received')); ?></label>
                        <input type="number" class="form-control" id="bk-dep-paid" name="deposit_paid" value="<?php echo esc_attr($num($bk->deposit_paid)); ?>" min="0" step="0.01" inputmode="decimal" placeholder="0">
                    </div>
                    <div class="w-field">
                        <label for="bk-bal-paid"><?php echo esc_html(sc_t('booths.balance_received', 'Balance received')); ?></label>
                        <input type="number" class="form-control" id="bk-bal-paid" name="balance_paid" value="<?php echo esc_attr($num($bk->balance_paid)); ?>" min="0" step="0.01" inputmode="decimal" placeholder="0">
                    </div>
                </div>
                <p class="w-field__help mt-2 mb-0"><?php echo esc_html(sc_t('booths.payments_help', 'Recorded by hand when money arrives. The payment status follows these amounts.')); ?></p>
            </section>

            <section class="w-section" aria-labelledby="bk-contract">
                <div class="w-section__head"><h2 id="bk-contract"><?php echo esc_html(sc_t('booths.contract', 'Contract and invoicing')); ?></h2></div>
                <div class="w-fields">
                    <div class="w-fields w-fields--3">
                        <div class="w-field">
                            <label for="bk-legal"><?php echo esc_html(sc_t('booths.legal_name', 'Legal company name')); ?></label>
                            <input type="text" class="form-control" id="bk-legal" name="company_legal_name" value="<?php echo esc_attr((string) $bk->company_legal_name); ?>">
                        </div>
                        <div class="w-field">
                            <label for="bk-cr"><?php echo esc_html(sc_t('booths.cr_no', 'Commercial register no.')); ?></label>
                            <input type="text" class="form-control w-ltr" id="bk-cr" name="commercial_registry_no" value="<?php echo esc_attr((string) $bk->commercial_registry_no); ?>" maxlength="50">
                        </div>
                        <div class="w-field">
                            <label for="bk-vat"><?php echo esc_html(sc_t('booths.tax_no', 'Tax registration no.')); ?></label>
                            <input type="text" class="form-control w-ltr" id="bk-vat" name="vat_number" value="<?php echo esc_attr((string) $bk->vat_number); ?>" maxlength="50">
                        </div>
                    </div>
                    <label class="w-switch"><input type="hidden" name="contract_signed" value="0"><input type="checkbox" name="contract_signed" value="1" <?php checked((int) $bk->contract_signed); ?>><span class="w-switch__track" aria-hidden="true"></span><span class="w-switch__text"><strong><?php echo esc_html(sc_t('booths.contract_signed', 'Contract signed')); ?></strong><?php if (!empty($bk->contract_signed_at)): ?><span><?php echo esc_html(mysql2date('j M Y', $bk->contract_signed_at)); ?></span><?php endif; ?></span></label>
                    <div class="w-fields w-fields--2" data-w-show-if="contract_signed:1">
                        <div class="w-field">
                            <label for="bk-signer"><?php echo esc_html(sc_t('booths.signed_by', 'Signed by')); ?></label>
                            <input type="text" class="form-control" id="bk-signer" name="contract_signer_name" value="<?php echo esc_attr((string) $bk->contract_signer_name); ?>">
                        </div>
                        <div class="w-field">
                            <label for="bk-signer-title"><?php echo esc_html(sc_t('dashboard_pages.job_title', 'Job title')); ?></label>
                            <input type="text" class="form-control" id="bk-signer-title" name="contract_signer_title" value="<?php echo esc_attr((string) $bk->contract_signer_title); ?>" maxlength="100">
                        </div>
                    </div>
                </div>
            </section>

            <section class="w-section" aria-labelledby="bk-dates">
                <div class="w-section__head"><h2 id="bk-dates"><?php echo esc_html(sc_t('booths.logistics', 'Dates and requests')); ?></h2></div>
                <div class="w-fields">
                    <div class="w-fields w-fields--2">
                        <div class="w-field">
                            <label for="bk-setup"><?php echo esc_html(sc_t('booths.setup_date', 'Setup day')); ?></label>
                            <input type="date" class="form-control" id="bk-setup" name="setup_date" value="<?php echo esc_attr($date_val($bk->setup_date)); ?>">
                        </div>
                        <div class="w-field">
                            <label for="bk-teardown"><?php echo esc_html(sc_t('booths.teardown_date', 'Teardown day')); ?></label>
                            <input type="date" class="form-control" id="bk-teardown" name="teardown_date" value="<?php echo esc_attr($date_val($bk->teardown_date)); ?>">
                        </div>
                    </div>
                    <div class="w-field">
                        <label for="bk-requests"><?php echo esc_html(sc_t('booths.special_requests', 'Exhibitor requests')); ?></label>
                        <textarea class="form-control" id="bk-requests" name="special_requests" rows="2"><?php echo esc_textarea((string) $bk->special_requests); ?></textarea>
                    </div>
                    <div class="w-fields w-fields--2">
                        <div class="w-field">
                            <label for="bk-notes"><?php echo esc_html(sc_t('dashboard_pages.notes', 'Notes')); ?></label>
                            <textarea class="form-control" id="bk-notes" name="notes" rows="2"><?php echo esc_textarea((string) $bk->notes); ?></textarea>
                        </div>
                        <div class="w-field">
                            <label for="bk-internal"><?php echo esc_html(sc_t('booths.internal_notes', 'Internal notes')); ?></label>
                            <textarea class="form-control" id="bk-internal" name="internal_notes" rows="2"><?php echo esc_textarea((string) $bk->internal_notes); ?></textarea>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <aside class="w-form-aside">
            <div class="w-aside-card">
                <span class="w-aside-card__title"><?php echo esc_html(sc_t('dashboard_pages.status', 'Status')); ?></span>
                <div class="w-choice w-choice--stack" role="radiogroup">
                    <?php foreach ($status_labels as $value => $label):
                        if (!$is_edit && !in_array($value, array('pending', 'confirmed'), true)) {
                            continue;
                        } ?>
                        <label class="w-choice__item"><input type="radio" name="status" value="<?php echo esc_attr($value); ?>" <?php checked($bk->status, $value); ?>><span class="w-choice__box"><span><?php echo esc_html($label[0]); ?><span class="w-choice__sub"><?php echo esc_html($label[1]); ?></span></span></span></label>
                    <?php endforeach; ?>
                </div>
                <div class="w-field mt-3" data-w-show-if="status:cancelled,no_show">
                    <label for="bk-reason"><?php echo esc_html(sc_t('booths.reason', 'Reason')); ?></label>
                    <input type="text" class="form-control" id="bk-reason" name="cancellation_reason" value="<?php echo esc_attr((string) $bk->cancellation_reason); ?>">
                </div>
            </div>
            <?php if ($is_edit): ?>
            <div class="w-aside-card">
                <span class="w-aside-card__title"><?php echo esc_html(sc_t('booths.money', 'Money')); ?></span>
                <div class="w-stats">
                    <div class="w-stat"><span class="w-stat__value w-ltr" id="as-paid"></span><span class="w-stat__label"><?php echo esc_html(sc_t('booths.received', 'Received')); ?></span></div>
                    <div class="w-stat"><span class="w-stat__value w-ltr" id="as-due"></span><span class="w-stat__label"><?php echo esc_html(sc_t('booths.still_due', 'Still due')); ?></span></div>
                </div>
                <p class="w-field__help mt-2 mb-0"><?php
                    echo esc_html(sprintf(sc_t('booths.booked_on', 'Booked %s'), mysql2date('j M Y', $bk->booking_date ?: $bk->created_at)));
                    if ($bk->checked_in_at) {
                        echo '<br>' . esc_html(sprintf(sc_t('booths.set_up_on', 'Set up %s'), mysql2date('j M Y, H:i', $bk->checked_in_at)));
                    }
                ?></p>
            </div>
            <?php endif; ?>
        </aside>
    </div>

    <div class="w-savebar">
        <span class="w-dirty" data-w-dirty hidden><?php echo esc_html(sc_t('dashboard_pages.unsaved_changes', 'Unsaved changes')); ?></span>
        <button type="submit" class="btn btn-primary" data-w-save><?php echo esc_html($save_label); ?></button>
    </div>
    <?php endif; ?>
</form>
</div>
</div>

<?php if ($events): ?>
<script>
jQuery(function ($) {
    'use strict';
    var isEdit = <?php echo $is_edit ? 'true' : 'false'; ?>;
    var currency = <?php echo $js($currency); ?>;
    var selected = { booth: <?php echo (int) $bk->booth_id; ?>, company: <?php echo (int) $bk->company_attendee_id; ?> };
    var booths = <?php echo $js(array_map(function ($b) { return array('id' => (int) $b->id, 'event' => (int) $b->event_id, 'number' => $b->booth_number, 'name' => (string) $b->booth_name, 'type' => (string) $b->type_name, 'price' => (float) $b->price, 'w' => (float) $b->w, 'd' => (float) $b->d, 'deposit' => (float) $b->deposit_percentage > 0 ? round((float) $b->price * (float) $b->deposit_percentage / 100, 2) : (float) $b->deposit_amount); }, $booths)); ?>;
    var companies = <?php echo $js(array_map(function ($c) { return array('id' => (int) $c->id, 'event' => (int) $c->event_id, 'name' => $c->company_name, 'contact' => (string) $c->contact_name, 'booth' => (string) $c->booth_number); }, $companies)); ?>;
    var L = <?php echo $js(array(
        'chooseCompany' => sc_t('booths.choose_company', '— Choose the company —'),
        'chooseBooth'   => sc_t('booths.choose_booth', '— Choose an available booth —'),
        'noCompanies'   => sc_t('booths.no_companies', 'No companies registered for this event yet.'),
        'noBooths'      => sc_t('booths.no_free_booths', 'No available booths for this event.'),
        'hasBooth'      => sc_t('booths.company_has_booth', 'Currently at booth %s.'),
        'boothHelp'     => sc_t('booths.booth_help', '%1$s · %2$s × %3$s m · %4$s'),
        'dueHint'       => sc_t('booths.due_hint', '%s still due'),
        'paidHint'      => sc_t('booths.paid_hint', 'Paid in full'),
        'errCompany'    => sc_t('booths.err_company', 'Choose the company.'),
        'errBooth'      => sc_t('booths.err_booth', 'Choose the booth.'),
        'errDiscount'   => sc_t('booths.err_discount', 'The discount is bigger than the price.'),
        'errPaid'       => sc_t('booths.err_paid', 'More has been received than the total.'),
        'saved'         => sc_t('dashboard_pages.saved', 'Saved.'),
        'failed'        => sc_t('errors.something_wrong', 'Something went wrong. Please try again.'),
        'saving'        => sc_t('dashboard_pages.saving', 'Saving…'),
        'fixErrors'     => sc_t('dashboard_pages.fix_n', 'Fix %d to save'),
        'errorsTitle'   => sc_t('dashboard_pages.errors_title', '%d fields need attention before saving.'),
        'errorTitleOne' => sc_t('dashboard_pages.error_title_one', 'One field needs attention before saving.'),
        'leave'         => sc_t('dashboard_pages.unsaved_leave', 'You have unsaved changes.'),
    )); ?>;
    var esc = function (v) { return String(v == null ? '' : v).replace(/[&<>"']/g, function (ch) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[ch]; }); };
    var fmt = function (n) { return Number(n || 0).toLocaleString('en-US', { maximumFractionDigits: 2 }); };
    var val = function (id) { return Math.max(0, parseFloat($(id).val()) || 0); };
    var formEl = document.getElementById('booking-form');

    function fillLists(keep) {
        var ev = +$('#bk-event').val();
        var bs = booths.filter(function (b) { return b.event === ev; });
        var cs = companies.filter(function (c) { return c.event === ev; });
        $('#bk-booth').html('<option value="">' + esc(bs.length ? L.chooseBooth : L.noBooths) + '</option>' + bs.map(function (b) {
            return '<option value="' + b.id + '">' + esc(b.number + (b.name ? ' — ' + b.name : '') + ' · ' + b.type) + '</option>';
        }).join('')).val(keep && bs.some(function (b) { return b.id === selected.booth; }) ? selected.booth : '');
        $('#bk-company').html('<option value="">' + esc(cs.length ? L.chooseCompany : L.noCompanies) + '</option>' + cs.map(function (c) {
            return '<option value="' + c.id + '">' + esc(c.name + (c.contact ? ' · ' + c.contact : '')) + '</option>';
        }).join('')).val(keep && cs.some(function (c) { return c.id === selected.company; }) ? selected.company : '');
        helps();
    }
    function helps() {
        var b = booths.filter(function (x) { return x.id === +$('#bk-booth').val(); })[0];
        var c = companies.filter(function (x) { return x.id === +$('#bk-company').val(); })[0];
        $('#bk-booth-help').text(b ? L.boothHelp.replace('%1$s', b.type).replace('%2$s', fmt(b.w)).replace('%3$s', fmt(b.d)).replace('%4$s', fmt(b.price) + ' ' + currency) : '');
        $('#bk-company-help').text(c && c.booth && (!b || c.booth !== b.number) ? L.hasBooth.replace('%s', c.booth) : '');
    }
    $('#bk-event').on('change', function () { fillLists(false); });
    $('#bk-company').on('change', helps);
    $('#bk-booth').on('change', function () {
        helps();
        // A new booking starts at the booth's price and deposit; edits keep the agreed figures.
        var b = booths.filter(function (x) { return x.id === +$('#bk-booth').val(); })[0];
        if (b && !isEdit) {
            $('#bk-base').val(b.price || '');
            $('#bk-dep-req').val(b.deposit || '');
            totals();
        }
    });
    fillLists(true);

    function totals() {
        var total = Math.max(0, val('#bk-base') + val('#bk-extras') - val('#bk-discount') + val('#bk-tax'));
        var paid = val('#bk-dep-paid') + val('#bk-bal-paid');
        var due = Math.max(0, total - paid);
        $('#bk-total').text(fmt(total) + ' ' + currency);
        $('#bk-due-hint').text(total ? (due > 0 ? L.dueHint.replace('%s', fmt(due) + ' ' + currency) : L.paidHint) : '');
        $('#as-paid').text(fmt(paid));
        $('#as-due').text(fmt(due));
    }
    $(formEl).on('input change', '#bk-base, #bk-extras, #bk-discount, #bk-tax, #bk-dep-paid, #bk-bal-paid', totals);
    $('#bk-vat14').on('click', function () {
        $('#bk-tax').val(Math.round((val('#bk-base') + val('#bk-extras') - val('#bk-discount')) * 14) / 100).trigger('input');
    });
    totals();

    WDForm.create({
        form: formEl,
        i18n: { saving: L.saving, fixErrors: L.fixErrors, errorsTitle: L.errorsTitle, errorTitleOne: L.errorTitleOne, failed: L.failed, leave: L.leave },
        validate: function (v) {
            var e = [];
            if (!v.company_attendee_id) { e.push({ field: 'company_attendee_id', message: L.errCompany }); }
            if (!v.booth_id) { e.push({ field: 'booth_id', message: L.errBooth }); }
            if (val('#bk-discount') > val('#bk-base') + val('#bk-extras')) { e.push({ field: 'discount_amount', message: L.errDiscount }); }
            var total = Math.max(0, val('#bk-base') + val('#bk-extras') - val('#bk-discount') + val('#bk-tax'));
            if (val('#bk-dep-paid') + val('#bk-bal-paid') > total + 0.005) { e.push({ field: 'balance_paid', message: L.errPaid }); }
            return e;
        },
        submit: function (fd) {
            fd.append('action', 'sc_booth_booking_save');
            fd.append('nonce', scDashboard.nonce);
            return fetch(scDashboard.ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
        },
        onSuccess: function (data, api) {
            api.markClean();
            try { sessionStorage.setItem('scBookingSaved', data.message || L.saved); } catch (x) { /* storage blocked */ }
            // Booth and payment status are worked out on the server: reload to show them.
            window.location.href = data.redirect || window.location.href;
        }
    });
    try {
        var saved = sessionStorage.getItem('scBookingSaved');
        if (saved) { sessionStorage.removeItem('scBookingSaved'); if (window.toastr) { toastr.success(saved); } }
    } catch (x) { /* storage blocked */ }
});
</script>
<?php endif; ?>
