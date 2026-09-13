<?php
/**
 * Attendee form — shared by attendee-add.php and attendee-edit.php.
 *
 * Posts to sc_save_attendee (sc_attendee_form_save). Adding is built for the
 * registration desk: pick event, workshop and ticket, fill the person in, and
 * optionally check them in and email the ticket in the same step.
 *
 * Expects: $attendee (object|null), $preselect_event_id (int).
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$p = $wpdb->prefix;
$is_edit = !empty($attendee);
$dashboard_url = home_url('/event-manager-dashboard/');
$a = $is_edit ? $attendee : (object) array(
    'id' => 0, 'event_id' => (int) ($preselect_event_id ?? 0), 'workshop_id' => 0, 'ticket_id' => 0, 'name' => '', 'email' => '', 'phone' => '',
    'payment_status' => 'success', 'payment_method' => 'free', 'amount_paid' => 0, 'coupon_code' => '', 'notes' => '', 'status' => 'active',
    'extra_fields' => '', 'ticket_code' => '', 'checked_in' => 0, 'checked_in_at' => null, 'created_at' => '', 'updated_at' => '', 'email_sent_at' => null,
);

$events = $wpdb->get_results($wpdb->prepare(
    "SELECT id, title, start_date, extra_fields, COALESCE(end_date, start_date) >= %s AS current FROM {$p}sc_events
     WHERE status IN ('publish', 'completed', 'draft') OR id = %d ORDER BY current DESC, start_date DESC LIMIT 200",
    current_time('Y-m-d'),
    (int) $a->event_id
));
$event_ids = array_map('intval', wp_list_pluck($events, 'id'));
$workshops = $event_ids ? $wpdb->get_results("SELECT id, event_id, title, start_date, extra_fields FROM {$p}sc_workshops WHERE event_id IN (" . implode(',', $event_ids) . ') ORDER BY start_date, title') : array();
$tickets = $event_ids ? $wpdb->get_results("SELECT id, event_id, workshop_id, name, price, is_active, enable_coupons, quantity, sold FROM {$p}sc_tickets WHERE event_id IN (" . implode(',', $event_ids) . ') ORDER BY sort_order, id') : array();

$questions = function ($json) {
    $list = json_decode((string) $json, true);
    return array_values(array_filter(array_map(function ($q) {
        if (!is_array($q) || empty($q['label'])) {
            return null;
        }
        $options = $q['options'] ?? '';
        $options = is_array($options) ? $options : array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string) $options))));
        return array('label' => (string) $q['label'], 'type' => (string) ($q['type'] ?? $q['field_type'] ?? 'text'), 'required' => !empty($q['required']) && $q['required'] !== 'false', 'options' => $options);
    }, is_array($list) ? $list : array())));
};

// SC_Attendee::get() hands extra_fields back already decoded.
$answers = is_string($a->extra_fields) ? json_decode($a->extra_fields, true) : json_decode(wp_json_encode($a->extra_fields), true);
$answers = is_array($answers) ? array_filter($answers, 'is_scalar') : array();

$history = $is_edit ? $wpdb->get_results($wpdb->prepare(
    "SELECT action, scanned_by, notes, created_at FROM {$p}sc_checkins WHERE attendee_id = %d ORDER BY created_at DESC LIMIT 20",
    (int) $a->id
)) : array();

$current_event = null;
foreach ($events as $ev) {
    if ((int) $ev->id === (int) $a->event_id) {
        $current_event = $ev;
    }
}
$current_workshop = null;
foreach ($workshops as $w) {
    if ((int) $w->id === (int) $a->workshop_id) {
        $current_workshop = $w;
    }
}
$ticket_url = $is_edit ? home_url('/ticket-view/?attendee_id=' . (int) $a->id . '&ticket_code=' . rawurlencode($a->ticket_code)) : '';
$save_label = $is_edit ? sc_t('dashboard_pages.save_changes', 'Save changes') : sc_t('dashboard_pages.register_attendee', 'Register attendee');
$currency = get_option('sc_currency_code', 'EGP');
$js = function ($value) {
    return wp_json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
};
?>

<div id="main-content">
<div class="container-fluid">
<form id="attendee-form" novalidate autocomplete="off">
    <input type="hidden" name="attendee_id" value="<?php echo (int) $a->id; ?>">

    <div class="w-form-head">
        <div>
            <span class="w-form-head__eyebrow"><?php echo esc_html($is_edit ? sc_t('dashboard_pages.attendee', 'Attendee') . ' · ' . $a->ticket_code : sc_t('dashboard_pages.new_registration', 'New registration')); ?></span>
            <h1 data-w-title><?php echo esc_html($a->name !== '' ? $a->name : sc_t('dashboard_pages.new_attendee', 'New attendee')); ?></h1>
            <div class="w-form-head__meta">
                <?php if ($is_edit): ?>
                    <span class="w-tag <?php echo (int) $a->checked_in ? 'w-tag--teal' : ''; ?>" id="checkin-tag"><?php echo esc_html((int) $a->checked_in ? sprintf(sc_t('dashboard_pages.checked_in_at', 'Checked in %s'), mysql2date('j M, H:i', $a->checked_in_at)) : sc_t('dashboard_pages.not_checked_in', 'Not checked in')); ?></span>
                    <?php if ($a->status !== 'active'): ?><span class="w-tag w-tag--red"><?php echo esc_html(sc_t('dashboard_pages.status_cancelled', 'Cancelled')); ?></span><?php endif; ?>
                <?php endif; ?>
                <span class="w-dirty" data-w-dirty hidden><?php echo esc_html(sc_t('dashboard_pages.unsaved_changes', 'Unsaved changes')); ?></span>
            </div>
        </div>
        <div class="w-form-head__actions">
            <a class="btn btn-secondary" href="<?php echo esc_url($dashboard_url . 'attendees' . ($a->event_id ? '?event_id=' . (int) $a->event_id : '')); ?>"><?php echo esc_html($is_edit ? sc_t('nav.attendees', 'Attendees') : sc_t('dashboard_pages.cancel', 'Cancel')); ?></a>
            <?php if (!$is_edit): ?>
            <button type="button" class="btn btn-secondary" id="save-another"><?php echo esc_html(sc_t('dashboard_pages.save_add_another', 'Save and add another')); ?></button>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary w-save-head" data-w-save><?php echo esc_html($save_label); ?></button>
        </div>
    </div>

    <div class="w-errors" data-w-errors hidden></div>
    <p class="w-panel__note" id="duplicate-note" hidden></p>

    <div class="w-form-layout w-form-layout--noseq">
        <div class="w-form-main">
            <section class="w-section" aria-labelledby="att-reg">
                <div class="w-section__head"><h2 id="att-reg"><?php echo esc_html(sc_t('dashboard_pages.registration', 'Registration')); ?></h2></div>
                <div class="w-fields">
                    <?php if ($is_edit): ?>
                        <div class="w-fields w-fields--2">
                            <div class="w-field">
                                <span class="w-field__label"><?php echo esc_html(sc_t('events.event', 'Event')); ?></span>
                                <p class="w-static"><?php echo esc_html($current_event ? $current_event->title : '#' . (int) $a->event_id); ?></p>
                            </div>
                            <div class="w-field">
                                <span class="w-field__label"><?php echo esc_html(sc_t('dashboard_pages.workshop', 'Workshop')); ?></span>
                                <p class="w-static"><?php echo esc_html($current_workshop ? $current_workshop->title : sc_t('dashboard_pages.event_only', 'Event only (no workshop)')); ?></p>
                            </div>
                        </div>
                        <input type="hidden" id="at-event" value="<?php echo (int) $a->event_id; ?>">
                        <input type="hidden" id="at-workshop" value="<?php echo (int) $a->workshop_id; ?>">
                    <?php else: ?>
                        <div class="w-fields w-fields--2">
                            <div class="w-field">
                                <label for="at-event"><?php echo esc_html(sc_t('events.event', 'Event')); ?><span class="w-req" aria-hidden="true">*</span></label>
                                <select class="form-control" id="at-event" name="event_id" required>
                                    <option value=""><?php echo esc_html(sc_t('dashboard_pages.choose_event', '— Choose the event —')); ?></option>
                                    <?php foreach ($events as $ev): ?>
                                        <option value="<?php echo (int) $ev->id; ?>" <?php selected((int) $a->event_id, (int) $ev->id); ?>><?php echo esc_html($ev->title . ' · ' . mysql2date('j M Y', $ev->start_date)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="w-field">
                                <label for="at-workshop"><?php echo esc_html(sc_t('dashboard_pages.registering_for', 'Registering for')); ?></label>
                                <select class="form-control" id="at-workshop" name="workshop_id"></select>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="w-field">
                        <label for="at-ticket"><?php echo esc_html(sc_t('dashboard_pages.ticket', 'Ticket')); ?><span class="w-req" aria-hidden="true">*</span></label>
                        <select class="form-control" id="at-ticket" name="ticket_id" required></select>
                    </div>
                </div>
            </section>

            <section class="w-section" aria-labelledby="att-person">
                <div class="w-section__head"><h2 id="att-person"><?php echo esc_html(sc_t('dashboard_pages.person', 'Person')); ?></h2></div>
                <div class="w-fields">
                    <div class="w-field">
                        <label for="at-name"><?php echo esc_html(sc_t('dashboard_pages.name', 'Name')); ?><span class="w-req" aria-hidden="true">*</span></label>
                        <input type="text" class="form-control" id="at-name" name="name" value="<?php echo esc_attr($a->name); ?>" maxlength="255" required>
                    </div>
                    <div class="w-fields w-fields--2">
                        <div class="w-field">
                            <label for="at-email"><?php echo esc_html(sc_t('general.email', 'Email')); ?><span class="w-req" aria-hidden="true">*</span></label>
                            <input type="email" class="form-control w-ltr" id="at-email" name="email" value="<?php echo esc_attr($a->email); ?>" required>
                        </div>
                        <div class="w-field">
                            <label for="at-phone"><?php echo esc_html(sc_t('general.phone', 'Phone')); ?></label>
                            <input type="tel" class="form-control w-ltr" id="at-phone" name="phone" value="<?php echo esc_attr($a->phone); ?>">
                        </div>
                    </div>
                    <div id="answers" class="w-fields"></div>
                    <div class="w-field">
                        <label for="at-notes"><?php echo esc_html(sc_t('dashboard_pages.notes', 'Notes')); ?></label>
                        <textarea class="form-control" id="at-notes" name="notes" rows="2"><?php echo esc_textarea((string) $a->notes); ?></textarea>
                        <p class="w-field__help"><?php echo esc_html(sc_t('dashboard_pages.notes_private', 'Only visible in the dashboard.')); ?></p>
                    </div>
                </div>
            </section>

            <section class="w-section" aria-labelledby="att-pay">
                <div class="w-section__head"><h2 id="att-pay"><?php echo esc_html(sc_t('payments.payment', 'Payment')); ?></h2></div>
                <div class="w-fields">
                    <div class="w-choice" role="radiogroup" aria-labelledby="att-pay">
                        <?php foreach (array('free' => sc_t('general.free', 'Free'), 'coupon' => sc_t('tickets.discount_code', 'Coupon'), 'paid' => sc_t('general.paid', 'Paid')) as $value => $label): ?>
                        <label class="w-choice__item"><input type="radio" name="payment_method" value="<?php echo esc_attr($value); ?>" <?php checked(in_array($a->payment_method, array('free', 'coupon', 'paid'), true) ? $a->payment_method : 'paid', $value); ?>><span class="w-choice__box"><?php echo esc_html($label); ?></span></label>
                        <?php endforeach; ?>
                    </div>
                    <div class="w-fields w-fields--2">
                        <div class="w-field" data-w-show-if="payment_method:coupon,paid">
                            <label for="at-coupon"><?php echo esc_html(sc_t('dashboard_pages.coupon_code', 'Coupon code')); ?></label>
                            <input type="text" class="form-control w-ltr" id="at-coupon" name="coupon_code" value="<?php echo esc_attr((string) $a->coupon_code); ?>">
                        </div>
                        <div class="w-field" data-w-show-if="payment_method:paid">
                            <label for="at-amount"><?php echo esc_html(sprintf(sc_t('dashboard_pages.amount_paid_in', 'Amount paid (%s)'), $currency)); ?></label>
                            <input type="number" class="form-control" id="at-amount" name="amount_paid" value="<?php echo $is_edit ? esc_attr((float) $a->amount_paid) : ''; ?>" min="0" step="0.01" inputmode="decimal">
                        </div>
                        <div class="w-field">
                            <label for="at-pstatus"><?php echo esc_html(sc_t('dashboard_pages.payment_status', 'Payment status')); ?></label>
                            <select class="form-control" id="at-pstatus" name="payment_status">
                                <?php foreach (array('success' => sc_t('dashboard_pages.confirmed', 'Confirmed'), 'pending' => sc_t('dashboard_pages.pending', 'Pending'), 'failed' => sc_t('payments.failed', 'Failed'), 'refunded' => sc_t('payments.refunded', 'Refunded')) as $value => $label): ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($a->payment_status, $value); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="w-field__help"><?php echo esc_html(sc_t('dashboard_pages.payment_status_help', 'Only confirmed registrations count towards seats and can be checked in.')); ?></p>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <aside class="w-form-aside">
            <div class="w-aside-card">
                <span class="w-aside-card__title"><?php echo esc_html(sc_t('dashboard_pages.status', 'Status')); ?></span>
                <div class="w-choice w-choice--stack" role="radiogroup">
                    <label class="w-choice__item"><input type="radio" name="status" value="active" <?php checked($a->status !== 'cancelled'); ?>><span class="w-choice__box"><span><?php echo esc_html(sc_t('dashboard_pages.active', 'Active')); ?><span class="w-choice__sub"><?php echo esc_html(sc_t('dashboard_pages.active_reg_help', 'Can enter and receive a certificate')); ?></span></span></span></label>
                    <label class="w-choice__item"><input type="radio" name="status" value="cancelled" <?php checked($a->status, 'cancelled'); ?>><span class="w-choice__box"><span><?php echo esc_html(sc_t('dashboard_pages.status_cancelled', 'Cancelled')); ?><span class="w-choice__sub"><?php echo esc_html(sc_t('dashboard_pages.cancelled_reg_help', 'Kept for records; the seat is released')); ?></span></span></span></label>
                </div>
            </div>

            <?php if (!$is_edit): ?>
            <div class="w-aside-card">
                <span class="w-aside-card__title"><?php echo esc_html(sc_t('dashboard_pages.after_saving', 'After saving')); ?></span>
                <div class="w-switches">
                    <label class="w-switch">
                        <input type="checkbox" name="check_in_now" value="1">
                        <span class="w-switch__track" aria-hidden="true"></span>
                        <span class="w-switch__text"><strong><?php echo esc_html(sc_t('dashboard_pages.check_in_now', 'Check in now')); ?></strong><span><?php echo esc_html(sc_t('dashboard_pages.check_in_now_help', 'For someone registering at the door.')); ?></span></span>
                    </label>
                    <label class="w-switch">
                        <input type="checkbox" name="send_email" value="1" checked>
                        <span class="w-switch__track" aria-hidden="true"></span>
                        <span class="w-switch__text"><strong><?php echo esc_html(sc_t('dashboard_pages.email_ticket', 'Email the ticket')); ?></strong><span><?php echo esc_html(sc_t('dashboard_pages.email_ticket_help', 'A link to their ticket and QR code.')); ?></span></span>
                    </label>
                </div>
            </div>
            <?php else: ?>
            <div class="w-aside-card">
                <span class="w-aside-card__title"><?php echo esc_html(sc_t('dashboard_pages.ticket', 'Ticket')); ?></span>
                <p class="w-ticket-code w-ltr" id="ticket-code"><?php echo esc_html($a->ticket_code); ?></p>
                <div class="w-aside-actions">
                    <a class="btn btn-sm btn-secondary" id="open-ticket" href="<?php echo esc_url($ticket_url); ?>" target="_blank" rel="noopener"><i class="fa fa-qrcode" aria-hidden="true"></i> <?php echo esc_html(sc_t('dashboard_pages.open_ticket', 'Open ticket')); ?></a>
                    <button type="button" class="btn btn-sm btn-secondary" id="send-ticket"><i class="fa fa-envelope-o" aria-hidden="true"></i> <?php echo esc_html(sc_t('dashboard_pages.email_ticket', 'Email the ticket')); ?></button>
                    <?php if ((int) $a->checked_in): ?>
                        <button type="button" class="btn btn-sm btn-secondary" id="undo-checkin"><?php echo esc_html(sc_t('dashboard_pages.undo_checkin', 'Undo check-in')); ?></button>
                    <?php else: ?>
                        <button type="button" class="btn btn-sm btn-primary" id="do-checkin"><i class="fa fa-check" aria-hidden="true"></i> <?php echo esc_html(sc_t('dashboard_pages.check_in', 'Check in')); ?></button>
                    <?php endif; ?>
                </div>
                <p class="w-field__help mt-2 mb-0"><?php
                    echo esc_html(sprintf(sc_t('dashboard_pages.registered_on', 'Registered %s'), mysql2date('j M Y, H:i', $a->created_at)));
                    if ($a->email_sent_at) {
                        echo '<br>' . esc_html(sprintf(sc_t('dashboard_pages.ticket_emailed_on', 'Ticket emailed %s'), mysql2date('j M Y, H:i', $a->email_sent_at)));
                    }
                ?></p>
            </div>

            <?php if ($history): ?>
            <div class="w-aside-card">
                <span class="w-aside-card__title"><?php echo esc_html(sc_t('dashboard_pages.scan_history', 'Scan history')); ?></span>
                <ul class="w-history">
                    <?php foreach ($history as $h):
                        $who = $h->scanned_by ? get_userdata((int) $h->scanned_by) : null;
                        $labels = array('checkin' => 'Check-in (QR)', 'manual_checkin' => 'Check-in', 'checkout' => 'Check-out (QR)', 'manual_checkout' => 'Check-out');
                    ?>
                    <li><strong><?php echo esc_html($labels[$h->action] ?? $h->action); ?></strong> <span class="w-ltr"><?php echo esc_html(mysql2date('j M, H:i', $h->created_at)); ?></span><?php echo $who ? '<span class="w-sub">' . esc_html($who->display_name) . '</span>' : ''; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <div class="w-danger">
                <strong><?php echo esc_html(sc_t('dashboard_pages.danger_zone', 'Ticket code and removal')); ?></strong>
                <p><?php echo esc_html(sc_t('dashboard_pages.regen_help', 'A new code makes the old QR code stop working — use it if a ticket was shared. To keep the record, set the status to Cancelled instead of deleting.')); ?></p>
                <div class="w-aside-actions">
                    <button type="button" class="btn btn-sm" id="regen-code"><?php echo esc_html(sc_t('dashboard_pages.new_ticket_code', 'New ticket code')); ?></button>
                    <button type="button" class="btn btn-sm" id="delete-attendee"><?php echo esc_html(sc_t('dashboard_pages.delete', 'Delete')); ?></button>
                </div>
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
    var attendeeId = <?php echo (int) $a->id; ?>;
    var dashboardUrl = <?php echo $js($dashboard_url); ?>;
    var currency = <?php echo $js($currency); ?>;
    var events = <?php echo $js(array_map(function ($e) use ($questions) { return array('id' => (int) $e->id, 'questions' => $questions($e->extra_fields)); }, $events)); ?>;
    var workshops = <?php echo $js(array_map(function ($w) use ($questions) { return array('id' => (int) $w->id, 'event_id' => (int) $w->event_id, 'title' => $w->title, 'questions' => $questions($w->extra_fields)); }, $workshops)); ?>;
    var tickets = <?php echo $js(array_map(function ($t) { return array('id' => (int) $t->id, 'event_id' => (int) $t->event_id, 'workshop_id' => (int) $t->workshop_id, 'name' => $t->name, 'price' => (float) $t->price, 'active' => (bool) $t->is_active, 'coupon' => (bool) $t->enable_coupons, 'left' => (int) $t->quantity > 0 ? max(0, (int) $t->quantity - (int) $t->sold) : null); }, $tickets)); ?>;
    var selectedTicket = <?php echo (int) $a->ticket_id; ?>;
    var selectedWorkshop = <?php echo (int) $a->workshop_id; ?>;
    var answers = <?php echo $js($answers); ?>;
    var L = <?php echo $js(array(
        'eventOnly'   => sc_t('dashboard_pages.event_only_registration', 'The event itself'),
        'chooseTicket'=> sc_t('dashboard_pages.choose_ticket', '— Choose a ticket —'),
        'noTickets'   => sc_t('dashboard_pages.no_tickets_here', 'No tickets here yet'),
        'offSale'     => sc_t('dashboard_pages.ticket_off_sale', 'Off sale'),
        'free'        => sc_t('general.free', 'Free'),
        'couponOnly'  => sc_t('dashboard_pages.coupon_only_short', 'Coupon'),
        'left'        => sc_t('dashboard_pages.n_left', '%d left'),
        'choose'      => sc_t('dashboard_pages.choose_option', '— Choose —'),
        'errEvent'    => sc_t('dashboard_pages.err_event', 'Choose the event.'),
        'errTicket'   => sc_t('dashboard_pages.err_ticket', 'Choose a ticket.'),
        'errName'     => sc_t('dashboard_pages.err_attendee_name', 'Enter the attendee’s name.'),
        'errEmail'    => sc_t('dashboard_pages.err_email', 'Enter a valid email address.'),
        'errRequired' => sc_t('dashboard_pages.err_answer', 'This question is required.'),
        'duplicate'   => sc_t('dashboard_pages.duplicate_registration', 'This email is already registered here.'),
        'openExisting'=> sc_t('dashboard_pages.open_existing', 'Open that registration'),
        'registerAnyway' => sc_t('dashboard_pages.register_anyway', 'Register again anyway'),
        'untitled'    => sc_t('dashboard_pages.new_attendee', 'New attendee'),
        'saved'       => sc_t('dashboard_pages.saved', 'Saved.'),
        'created'     => sc_t('dashboard_pages.attendee_registered', 'Attendee registered.'),
        'checkedIn'   => sc_t('dashboard_pages.checked_in_ok', 'Checked in.'),
        'confirmRegen'=> sc_t('dashboard_pages.confirm_new_code', 'Create a new ticket code? The current QR code will stop working.'),
        'confirmDelete' => sc_t('dashboard_pages.confirm_delete_attendee', 'Delete this attendee? Their ticket, check-ins and certificate go too. This cannot be undone.'),
        'failed'      => sc_t('errors.something_wrong', 'Something went wrong. Please try again.'),
        'saving'      => sc_t('dashboard_pages.saving', 'Saving…'),
        'fixErrors'   => sc_t('dashboard_pages.fix_n', 'Fix %d to save'),
        'errorsTitle' => sc_t('dashboard_pages.errors_title', '%d fields need attention before saving.'),
        'errorTitleOne' => sc_t('dashboard_pages.error_title_one', 'One field needs attention before saving.'),
        'leave'       => sc_t('dashboard_pages.unsaved_leave', 'You have unsaved changes.'),
    )); ?>;
    var esc = function (v) { return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]; }); };
    var post = function (data) { return $.ajax({ url: scDashboard.ajaxurl, type: 'POST', data: $.extend({ nonce: scDashboard.nonce }, data) }); };
    var formEl = document.getElementById('attendee-form');
    var money = function (n) { return Number(n).toLocaleString('en-US', { maximumFractionDigits: 2 }) + ' ' + currency; };

    function eventId() { return +$('#at-event').val() || 0; }
    function workshopId() { return +$('#at-workshop').val() || 0; }

    function fillWorkshops() {
        if (isEdit) { return; }
        var list = workshops.filter(function (w) { return w.event_id === eventId(); });
        $('#at-workshop').html('<option value="">' + esc(L.eventOnly) + '</option>' + list.map(function (w) {
            return '<option value="' + w.id + '"' + (w.id === selectedWorkshop ? ' selected' : '') + '>' + esc(w.title) + '</option>';
        }).join('')).prop('disabled', !eventId()).closest('.w-field').prop('hidden', !list.length);
    }
    function fillTickets() {
        var ev = eventId(), ws = workshopId();
        var list = tickets.filter(function (t) { return t.event_id === ev && t.workshop_id === ws && (t.active || t.id === selectedTicket); });
        $('#at-ticket').html(list.length ? '<option value="">' + esc(L.chooseTicket) + '</option>' + list.map(function (t) {
            var bits = [t.coupon && !t.price ? L.couponOnly : (t.price ? money(t.price) : L.free)];
            if (t.left !== null) { bits.push(L.left.replace('%d', t.left)); }
            if (!t.active) { bits.push(L.offSale); }
            return '<option value="' + t.id + '"' + (t.id === selectedTicket ? ' selected' : '') + '>' + esc(t.name + ' · ' + bits.join(' · ')) + '</option>';
        }).join('') : '<option value="">' + esc(L.noTickets) + '</option>');
        if (!isEdit && list.length === 1) { $('#at-ticket').val(list[0].id); syncAmount(); }
    }
    // Registration answers follow the event's (or workshop's) own questions; stored answers without a question still show.
    function fillAnswers() {
        var source = workshopId() ? workshops.filter(function (w) { return w.id === workshopId(); })[0] : events.filter(function (e) { return e.id === eventId(); })[0];
        var qs = source ? source.questions.slice() : [];
        Object.keys(answers).forEach(function (label) {
            if (!qs.some(function (q) { return q.label === label; })) { qs.push({ label: label, type: 'text', required: false, options: [] }); }
        });
        $('#answers').html(qs.map(function (q, i) {
            var name = 'extra_fields[' + q.label + ']', id = 'q-' + i, val = answers[q.label] != null ? String(answers[q.label]) : '';
            var req = q.required ? '<span class="w-req" aria-hidden="true">*</span>' : '';
            var control;
            if (q.type === 'select' && q.options.length) {
                control = '<select class="form-control" id="' + id + '" name="' + esc(name) + '" data-required="' + (q.required ? 1 : 0) + '"><option value="">' + esc(L.choose) + '</option>' +
                    q.options.map(function (o) { return '<option value="' + esc(o) + '"' + (o === val ? ' selected' : '') + '>' + esc(o) + '</option>'; }).join('') + '</select>';
            } else if (q.type === 'textarea') {
                control = '<textarea class="form-control" id="' + id + '" name="' + esc(name) + '" rows="2" data-required="' + (q.required ? 1 : 0) + '">' + esc(val) + '</textarea>';
            } else if (q.type === 'checkbox') {
                return '<input type="hidden" name="' + esc(name) + '" value=""><label class="w-switch"><input type="checkbox" name="' + esc(name) + '" value="Yes"' + (val && val !== 'No' ? ' checked' : '') + '><span class="w-switch__track" aria-hidden="true"></span><span class="w-switch__text"><strong>' + esc(q.label) + '</strong></span></label>';
            } else {
                var type = q.type === 'email' ? 'email' : (q.type === 'number' ? 'number' : 'text');
                control = '<input type="' + type + '" class="form-control" id="' + id + '" name="' + esc(name) + '" value="' + esc(val) + '" data-required="' + (q.required ? 1 : 0) + '">';
            }
            return '<div class="w-field"><label for="' + id + '">' + esc(q.label) + req + '</label>' + control + '</div>';
        }).join(''));
    }
    function syncAmount() {
        var t = tickets.filter(function (x) { return x.id === +$('#at-ticket').val(); })[0];
        if (!isEdit && t && !$('#at-amount').val()) {
            $(formEl).find('[name="payment_method"][value="' + (t.coupon && !t.price ? 'coupon' : (t.price ? 'paid' : 'free')) + '"]').prop('checked', true).trigger('change');
            if (t.price) { $('#at-amount').val(t.price); }
        }
    }

    $('#at-event').on('change', function () { selectedWorkshop = 0; selectedTicket = 0; fillWorkshops(); fillTickets(); fillAnswers(); });
    $('#at-workshop').on('change', function () { selectedTicket = 0; fillTickets(); fillAnswers(); });
    $('#at-ticket').on('change', syncAmount);
    fillWorkshops();
    fillTickets();
    fillAnswers();

    $('#at-name').on('input', function () { $('[data-w-title]').text($.trim(this.value) || L.untitled); });

    var addAnother = false, allowDuplicate = false;
    var form = WDForm.create({
        form: formEl,
        i18n: { saving: L.saving, fixErrors: L.fixErrors, errorsTitle: L.errorsTitle, errorTitleOne: L.errorTitleOne, failed: L.failed, leave: L.leave },
        validate: function (v) {
            var e = [];
            if (!isEdit && !v.event_id) { e.push({ field: 'event_id', message: L.errEvent }); }
            if (!v.ticket_id) { e.push({ field: 'ticket_id', message: L.errTicket }); }
            if (!$.trim(v.name)) { e.push({ field: 'name', message: L.errName }); }
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test($.trim(v.email || ''))) { e.push({ field: 'email', message: L.errEmail }); }
            $('#answers [data-required="1"]').each(function () { if (!$.trim(this.value)) { e.push({ field: this.id, message: L.errRequired }); } });
            return e;
        },
        submit: function (fd) {
            fd.append('action', 'sc_save_attendee');
            fd.append('nonce', scDashboard.nonce);
            if (allowDuplicate) { fd.append('allow_duplicate', '1'); }
            return fetch(scDashboard.ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (res) {
                allowDuplicate = false;
                var $note = $('#duplicate-note');
                if (!res.success && res.data && res.data.duplicate) {
                    $note.html(esc(L.duplicate) + ' <a href="' + esc(dashboardUrl + 'attendee-edit?id=' + res.data.duplicate) + '">' + esc(L.openExisting) + '</a> · <button type="button" class="btn btn-link p-0" id="register-anyway">' + esc(L.registerAnyway) + '</button>').prop('hidden', false);
                } else {
                    $note.prop('hidden', true);
                }
                return res;
            });
        },
        onSuccess: function (data, api) {
            api.markClean();
            if (!isEdit) {
                if (addAnother) {
                    try { sessionStorage.setItem('scAttendeeAdded', data.message || L.created); } catch (x) { /* storage blocked */ }
                    window.location.href = dashboardUrl + 'attendee-add?event_id=' + eventId() + (workshopId() ? '&workshop_id=' + workshopId() : '');
                } else {
                    window.location.href = data.redirect;
                }
                return;
            }
            if (window.toastr) { toastr.success(data.message || L.saved); }
        }
    });
    $(document).on('click', '#register-anyway', function () { allowDuplicate = true; form.save(); });
    $('#save-another').on('click', function () { addAnother = true; form.save().then(function () { addAnother = false; }); });

    try {
        var added = sessionStorage.getItem('scAttendeeAdded');
        if (added) { sessionStorage.removeItem('scAttendeeAdded'); if (window.toastr) { toastr.success(added); } }
    } catch (x) { /* storage blocked */ }
    if (/[?&]created=1/.test(location.search)) {
        if (window.toastr) { toastr.success(L.created); }
        history.replaceState(null, '', location.pathname + location.search.replace(/[?&]created=1/, '').replace(/^&/, '?'));
    }
    var wsParam = +(new URLSearchParams(location.search).get('workshop_id') || 0);
    if (!isEdit && wsParam) { $('#at-workshop').val(wsParam).trigger('change'); form.markClean(); }

    if (!isEdit) { return; }

    function act(data, reload) {
        return post(data).done(function (res) {
            if (res.success) {
                if (window.toastr) { toastr.success(res.data.message || L.saved); }
                if (reload) { form.markClean(); setTimeout(function () { window.location.reload(); }, 400); }
            } else {
                showError(res.data && res.data.message || L.failed);
            }
        }).fail(function () { showError(L.failed); });
    }
    $('#do-checkin').on('click', function () { act({ action: 'sc_checkin_attendee', attendee_id: attendeeId }, true); });
    $('#undo-checkin').on('click', function () { act({ action: 'sc_undo_checkin_attendee', attendee_id: attendeeId }, true); });
    $('#send-ticket').on('click', function () { act({ action: 'sc_attendee_send_ticket', attendee_id: attendeeId }, false); });
    $('#regen-code').on('click', function () {
        showConfirm(L.confirmRegen).then(function (r) { if (r.isConfirmed) { act({ action: 'sc_regenerate_ticket_code', attendee_id: attendeeId }, true); } });
    });
    $('#delete-attendee').on('click', function () {
        showDeleteConfirm(L.confirmDelete).then(function (r) {
            if (!r.isConfirmed) { return; }
            post({ action: 'sc_delete_attendee', attendee_id: attendeeId }).done(function (res) {
                if (res.success) { form.markClean(); window.location.href = dashboardUrl + 'attendees'; } else { showError(res.data && res.data.message || L.failed); }
            }).fail(function () { showError(L.failed); });
        });
    });
});
</script>
