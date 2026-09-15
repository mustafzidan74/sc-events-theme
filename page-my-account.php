<?php
/**
 * Template Name: My Account Page
 *
 * The pass comes first: on the morning of a congress that is the only reason
 * this page gets opened. Certificates, saved events and the profile follow.
 *
 * The tab switching, QR download, certificate request, sign-out and profile
 * save are still the script at the foot of this file, so the hooks it binds to
 * (.sc-account-nav .sc-nav-item[data-tab], .sc-tab-content, .btn-download-qr,
 * .sc-request-certificate, .sc-nav-logout, #profile-form) are kept as they
 * were.
 *
 * @package sc_events
 */

// Redirect if not logged in
if (!is_user_logged_in()) {
    wp_redirect(home_url('/login/?redirect=' . urlencode(home_url('/my-account/'))));
    exit;
}

$current_user = wp_get_current_user();
$user_id = $current_user->ID;
$user_phone = get_user_meta($user_id, 'phone', true);
$assets_url = get_template_directory_uri() . '/assets/frontend/';

// Get user's tickets/registrations from Custom Tables
$attendees = array();
$total_tickets = 0;
$upcoming_tickets = 0;
$past_tickets = 0;

if (class_exists('SC_Attendee')) {
    // Tickets registered by the user themselves carry user_id; tickets created by
    // an organizer from the dashboard usually only carry an email. Pull both sets
    // and merge — a single source misses one of the two flows.
    $by_user  = SC_Attendee::get_by_user($user_id, array(
        'status' => 'active',
        'payment_status' => 'success',
        'limit' => 100,
        'order' => 'DESC',
    ));
    $by_email = SC_Attendee::search_by_email($current_user->user_email);

    $merged = array();
    foreach (array_merge($by_user, $by_email) as $row) {
        if (!$row || empty($row->id)) {
            continue;
        }
        // Filter cancelled/transferred tickets that search_by_email doesn't filter
        if (!empty($row->status) && $row->status !== 'active') {
            continue;
        }
        $merged[(int) $row->id] = $row; // dedupe by ID
    }
    $attendees = array_values($merged);
    $total_tickets = count($attendees);

    // Count upcoming vs past
    foreach ($attendees as $attendee) {
        if (class_exists('SC_Event')) {
            $event = SC_Event::get($attendee->event_id);
            if ($event) {
                $ev_end = ($event->end_date ?: $event->start_date) . ($event->end_time ? ' ' . $event->end_time : ' 23:59:59');
                if (strtotime($ev_end) >= current_time('timestamp')) {
                    $upcoming_tickets++;
                } else {
                    $past_tickets++;
                }
            }
        }
    }
}

// Get user's favorites
$favorite_events = array();
$favorites_count = 0;
global $wpdb;
$fav_table = $wpdb->prefix . 'sc_favorites';
if ($wpdb->get_var("SHOW TABLES LIKE '$fav_table'") === $fav_table) {
    $fav_event_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT event_id FROM $fav_table WHERE user_id = %d ORDER BY created_at DESC",
        $user_id
    ));
    $favorites_count = count($fav_event_ids);
    if (!empty($fav_event_ids) && class_exists('SC_Event')) {
        foreach ($fav_event_ids as $fav_eid) {
            $fev = SC_Event::get($fav_eid);
            if ($fev) $favorite_events[] = $fev;
        }
    }
}


// Certificates, flattened across every ticket, so the tab can stand alone.
$cert_list = [];
if (!empty($attendees) && class_exists('SC_Certificate') && class_exists('SC_Event')) {
    foreach ($attendees as $att) {
        $event = SC_Event::get($att->event_id);
        if (!$event) { continue; }

        $cert          = SC_Certificate::get_by_attendee_event($att->id, $att->event_id);
        $checked_in    = !empty($att->checked_in);
        $event_end     = $event->end_date ?: $event->start_date;
        $event_is_past = strtotime($event_end) < strtotime(current_time('Y-m-d'));

        $cert_list[] = [
            'event'       => $event,
            'attendee'    => $att,
            'certificate' => is_array($cert) ? $cert : null,
            'eligible'    => !empty($event->enable_certificates)
                             && (empty($event->certificate_require_checkin) || $checked_in)
                             && (empty($event->certificate_require_event_ended) || $event_is_past),
            'checked_in'  => $checked_in,
            'is_past'     => $event_is_past,
        ];
    }
}

$issued_certs = count(array_filter($cert_list, static fn($r) =>
    $r['certificate'] && ($r['certificate']['status'] ?? '') !== 'revoked'));

// Greet by the hour rather than with a flat "Welcome" — the page is opened on
// the morning of a congress more than at any other time.
$hour = (int) current_time('G');
$greeting = $hour < 12
    ? sc_t('frontend.good_morning', 'Good morning,')
    : ($hour < 18 ? sc_t('frontend.good_afternoon', 'Good afternoon,')
                  : sc_t('frontend.good_evening', 'Good evening,'));

// Split against the codes actually offered, longest first — a generic
// "+ up to four digits" is greedy and eats the start of the number.
$phone_codes  = ['+966', '+971', '+965', '+974', '+218', '+249', '+20'];
$phone_code   = '+20';
$phone_number = $user_phone;
foreach ($phone_codes as $code) {
    if ($user_phone && str_starts_with($user_phone, $code)) {
        $phone_code   = $code;
        $phone_number = trim(substr($user_phone, strlen($code)));
        break;
    }
}

get_template_part('template-parts/public/header', 'public');
?>

<main class="w-acc">
    <header class="w-acc__hello">
        <span class="w-acc__avatar"><?php echo get_avatar($user_id, 112, '', $current_user->display_name); ?></span>
        <span>
            <span class="w-acc__greet"><?php echo esc_html($greeting); ?></span>
            <h1 class="w-acc__name"><?php echo esc_html($current_user->display_name); ?></h1>
        </span>
    </header>

    <nav class="w-acc__tabs sc-account-nav" aria-label="<?php echo esc_attr(sc_t('frontend.account', 'Account')); ?>">
        <a class="w-acc__tab sc-nav-item active" href="#tickets" data-tab="tickets">
            <?php echo esc_html(sc_t('frontend.my_tickets', 'Tickets')); ?>
            <?php if ($total_tickets): ?><span class="w-acc__count"><?php echo esc_html(number_format_i18n($total_tickets)); ?></span><?php endif; ?>
        </a>
        <a class="w-acc__tab sc-nav-item" href="#certificates" data-tab="certificates">
            <?php echo esc_html(sc_t('frontend.certificates', 'Certificates')); ?>
            <?php if ($issued_certs): ?><span class="w-acc__count"><?php echo esc_html(number_format_i18n($issued_certs)); ?></span><?php endif; ?>
        </a>
        <a class="w-acc__tab sc-nav-item" href="#favorites" data-tab="favorites">
            <?php echo esc_html(sc_t('frontend.saved', 'Saved')); ?>
            <?php if ($favorites_count): ?><span class="w-acc__count"><?php echo esc_html(number_format_i18n($favorites_count)); ?></span><?php endif; ?>
        </a>
        <a class="w-acc__tab sc-nav-item" href="#profile" data-tab="profile">
            <?php echo esc_html(sc_t('frontend.profile', 'Profile')); ?>
        </a>
    </nav>

    <!-- ============ tickets ============ -->
    <section class="w-acc__panel sc-tab-content" id="tickets-tab" data-tab-content="tickets">
        <?php if (!empty($attendees)): ?>
            <?php foreach ($attendees as $attendee):
                $event = class_exists('SC_Event') ? SC_Event::get($attendee->event_id) : null;
                if (!$event) { continue; }

                $end_dt  = ($event->end_date ?: $event->start_date) . ($event->end_time ? ' ' . $event->end_time : ' 23:59:59');
                $is_past = strtotime($end_dt) < current_time('timestamp');
                $is_now  = !$is_past
                    && date('Y-m-d', strtotime($event->start_date)) <= current_time('Y-m-d')
                    && date('Y-m-d', strtotime($event->end_date ?: $event->start_date)) >= current_time('Y-m-d');

                $checked_in   = !empty($attendee->checked_in);
                $checkin_time = $attendee->checked_in_at ?? null;

                // Session attendance, where the sessions module is running.
                $sessions_seen = null;
                $cme_hours     = 0;
                if (class_exists('SC_Session_Attendance')) {
                    $sessions = SC_Session_Attendance::get_attendee_summary($attendee->id, $event->id);
                    if (!empty($sessions)) {
                        $attended = 0;
                        foreach ($sessions as $s) {
                            if (!empty($s->check_in_time)) { $attended++; }
                            $cme_hours += (float) ($s->earned_cme_hours ?? 0);
                        }
                        $sessions_seen = ['seen' => $attended, 'of' => count($sessions)];
                    }
                }

                // Shared formatter. It also states both ends of a run that
                // crosses a month, which this had been dropping — a congress
                // from 28 Sep to 2 Oct used to read simply "28 September".
                $dates = sc_date_range($event->start_date, $event->end_date, true);

                $view_url = home_url('/ticket-view/?attendee_id=' . $attendee->id . '&ticket_code=' . $attendee->ticket_code);
            ?>
            <article class="w-pass<?php echo $is_past ? ' w-pass--past' : ''; ?>">
                <div class="w-pass__top">
                    <span class="w-pass__status<?php echo $is_now ? ' w-pass__status--live' : ''; ?>">
                        <?php if ($is_now): ?>
                            <?php echo esc_html(sc_t('frontend.happening_now', 'Happening now')); ?>
                        <?php elseif ($is_past): ?>
                            <?php echo esc_html(sc_t('frontend.past', 'Past')); ?>
                        <?php else: ?>
                            <?php echo esc_html(sc_t('frontend.active', 'Active')); ?>
                        <?php endif; ?>
                    </span>

                    <h2 class="w-pass__event"><?php echo esc_html($event->title); ?></h2>

                    <span class="w-pass__holder"><?php echo esc_html($attendee->name ?: $current_user->display_name); ?></span>
                    <span class="w-pass__meta">
                        <?php echo esc_html(trim(implode(' · ', array_filter([
                            $attendee->ticket_name ?? '',
                            $dates,
                            $event->venue_name ?? '',
                        ])))); ?>
                    </span>

                    <?php if (!empty($attendee->ticket_code)): ?>
                        <span class="w-pass__code"><?php echo esc_html($attendee->ticket_code); ?></span>
                    <?php endif; ?>
                </div>

                <div class="w-pass__qr">
                    <?php if (!empty($attendee->ticket_code)): ?>
                        <canvas data-qr="<?php echo esc_attr($attendee->ticket_code); ?>"
                                aria-label="<?php echo esc_attr(sprintf(
                                    sc_t('frontend.qr_for', 'QR code for ticket %s'),
                                    $attendee->ticket_code
                                )); ?>" role="img"></canvas>
                    <?php endif; ?>

                    <div class="w-pass__qr-say">
                        <span class="w-pass__hint">
                            <?php echo esc_html(sc_t('frontend.qr_hint', 'Show this at the door. It works without a signal.')); ?>
                        </span>
                        <div class="w-pass__acts">
                            <a class="w-btn w-btn--sm w-btn--onink" href="<?php echo esc_url($view_url); ?>" target="_blank" rel="noopener">
                                <i class="fa-solid fa-up-right-from-square" aria-hidden="true"></i>
                                <?php echo esc_html(sc_t('frontend.open_ticket', 'Open ticket')); ?>
                            </a>
                            <?php if (!empty($attendee->ticket_code)): ?>
                            <button class="w-btn w-btn--sm w-btn--onink btn-download-qr" type="button"
                                    data-ticket-id="<?php echo esc_attr($attendee->ticket_code); ?>"
                                    data-qr="<?php echo esc_attr($attendee->ticket_code); ?>"
                                    data-event-title="<?php echo esc_attr($event->title); ?>">
                                <i class="fa-solid fa-download" aria-hidden="true"></i>
                                <?php echo esc_html(sc_t('frontend.save_qr', 'Save QR')); ?>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if ($checked_in || $sessions_seen || $cme_hours > 0): ?>
                <div class="w-pass__extra">
                    <?php if ($checked_in): ?>
                    <span>
                        <?php echo esc_html(sc_t('frontend.checked_in', 'Checked in')); ?>
                        <?php if ($checkin_time): ?>
                            <strong><?php echo esc_html(date_i18n('j M, H:i', strtotime($checkin_time))); ?></strong>
                        <?php endif; ?>
                    </span>
                    <?php endif; ?>

                    <?php if ($sessions_seen): ?>
                    <span>
                        <?php echo esc_html(sc_t('frontend.sessions_attended', 'Sessions attended')); ?>
                        <strong><?php echo esc_html($sessions_seen['seen'] . '/' . $sessions_seen['of']); ?></strong>
                    </span>
                    <?php endif; ?>

                    <?php if ($cme_hours > 0): ?>
                    <span>
                        <?php echo esc_html(sc_t('frontend.cme_earned', 'CME earned')); ?>
                        <strong><?php echo esc_html(number_format_i18n($cme_hours, 1)); ?></strong>
                    </span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>
        <?php else: ?>
        <div class="w-empty">
            <span class="w-empty__title"><?php echo esc_html(sc_t('frontend.no_tickets', 'No tickets yet')); ?></span>
            <p><?php echo esc_html(sc_t('frontend.no_tickets_lede', 'Your passes will show up here as soon as you register.')); ?></p>
            <a class="w-btn" href="<?php echo esc_url(home_url('/events/')); ?>">
                <?php echo esc_html(sc_t('frontend.whats_on', "What's on")); ?>
            </a>
        </div>
        <?php endif; ?>
    </section>

    <!-- ============ certificates ============ -->
    <section class="w-acc__panel sc-tab-content" id="certificates-tab" data-tab-content="certificates" style="display:none">
        <?php if ($cert_list): ?>
            <p class="w-acc__row-meta" style="margin:0">
                <?php echo esc_html(sc_t('frontend.cert_note', 'CME certificates appear here within 48 hours of your last check-in.')); ?>
            </p>

            <?php foreach ($cert_list as $row):
                $event  = $row['event'];
                $att    = $row['attendee'];
                $cert   = $row['certificate'];
                $issued = $cert && ($cert['status'] ?? '') !== 'revoked';

                $download_url = '';
                if ($issued) {
                    $token = wp_hash($cert['verification_code'] . $cert['certificate_number']);
                    $download_url = add_query_arg([
                        'action' => 'sc_download_certificate',
                        'id'     => $cert['id'],
                        'token'  => $token,
                    ], admin_url('admin-ajax.php'));
                }
            ?>
            <div class="w-acc__row">
                <span class="w-acc__row-text">
                    <span class="w-acc__row-title"><?php echo esc_html($event->title); ?></span>
                    <span class="w-acc__row-meta">
                        <?php echo esc_html(date_i18n('j F Y', strtotime($event->start_date))); ?>
                        <?php if (!empty($att->ticket_code)): ?>
                            · <?php echo esc_html($att->ticket_code); ?>
                        <?php endif; ?>
                    </span>
                </span>

                <?php if ($issued && $download_url): ?>
                    <a class="w-btn w-btn--sm" href="<?php echo esc_url($download_url); ?>" target="_blank" rel="noopener">
                        <i class="fa-solid fa-download" aria-hidden="true"></i>
                        <?php echo esc_html(sc_t('frontend.download', 'Download')); ?>
                    </a>
                <?php elseif ($row['eligible']): ?>
                    <button class="w-btn w-btn--sm w-btn--outline sc-request-certificate" type="button"
                            data-attendee-id="<?php echo esc_attr($att->id); ?>"
                            data-event-id="<?php echo esc_attr($event->id); ?>"
                            data-ticket-code="<?php echo esc_attr($att->ticket_code); ?>">
                        <?php echo esc_html(sc_t('frontend.get_certificate', 'Get certificate')); ?>
                    </button>
                <?php else: ?>
                    <span class="w-acc__row-meta">
                        <?php
                        if (empty($event->enable_certificates)) {
                            echo esc_html(sc_t('frontend.cert_not_available', 'Not available'));
                        } elseif (!empty($event->certificate_require_checkin) && !$row['checked_in']) {
                            echo esc_html(sc_t('frontend.cert_needs_checkin', 'Needs a check-in'));
                        } elseif (!empty($event->certificate_require_event_ended) && !$row['is_past']) {
                            echo esc_html(sc_t('frontend.cert_after_event', 'After the congress'));
                        } else {
                            echo esc_html(sc_t('frontend.cert_not_yet', 'Not yet'));
                        }
                        ?>
                    </span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
        <div class="w-empty">
            <span class="w-empty__title"><?php echo esc_html(sc_t('frontend.no_certificates', 'No certificates yet')); ?></span>
            <p><?php echo esc_html(sc_t('frontend.cert_note', 'CME certificates appear here within 48 hours of your last check-in.')); ?></p>
        </div>
        <?php endif; ?>
    </section>

    <!-- ============ saved ============ -->
    <section class="w-acc__panel sc-tab-content" id="favorites-tab" data-tab-content="favorites" style="display:none">
        <?php if (!empty($favorite_events)): ?>
            <?php foreach ($favorite_events as $fav):
                $fav_past = strtotime($fav->end_date ?: $fav->start_date) < strtotime(current_time('Y-m-d'));
            ?>
            <div class="w-acc__row" data-event-id="<?php echo esc_attr($fav->id); ?>">
                <span class="w-acc__row-text">
                    <a class="w-acc__row-title" href="<?php echo esc_url(home_url('/event/' . $fav->slug)); ?>"
                       style="text-decoration:none;color:inherit"><?php echo esc_html($fav->title); ?></a>
                    <span class="w-acc__row-meta">
                        <?php echo esc_html(date_i18n('j F Y', strtotime($fav->start_date))); ?>
                        <?php if ($fav_past): ?> · <?php echo esc_html(sc_t('frontend.past', 'Past')); ?><?php endif; ?>
                    </span>
                </span>
                <button class="w-btn w-btn--sm w-btn--outline sc-remove-favorite" type="button"
                        data-event-id="<?php echo esc_attr($fav->id); ?>">
                    <i class="fa-regular fa-heart" aria-hidden="true"></i>
                    <?php echo esc_html(sc_t('frontend.remove', 'Remove')); ?>
                </button>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
        <div class="w-empty">
            <span class="w-empty__title"><?php echo esc_html(sc_t('frontend.nothing_saved', 'Nothing saved yet')); ?></span>
            <p><?php echo esc_html(sc_t('frontend.nothing_saved_lede', 'Tap the heart on an event to keep it here.')); ?></p>
            <a class="w-btn w-btn--outline" href="<?php echo esc_url(home_url('/events/')); ?>">
                <?php echo esc_html(sc_t('frontend.whats_on', "What's on")); ?>
            </a>
        </div>
        <?php endif; ?>
    </section>

    <!-- ============ profile ============ -->
    <section class="w-acc__panel sc-tab-content" id="profile-tab" data-tab-content="profile" style="display:none">
        <form class="w-acc__form" id="profile-form">
            <div class="w-field">
                <label class="w-label" for="profile-name"><?php echo esc_html(sc_t('frontend.full_name', 'Name')); ?></label>
                <input class="w-input" type="text" id="profile-name" name="name"
                       value="<?php echo esc_attr($current_user->display_name); ?>" required>
            </div>

            <div class="w-field">
                <label class="w-label" for="profile-email"><?php echo esc_html(sc_t('frontend.email', 'Email')); ?></label>
                <input class="w-input" type="email" id="profile-email"
                       value="<?php echo esc_attr($current_user->user_email); ?>" disabled>
                <span class="w-hint"><?php echo esc_html(sc_t('frontend.email_locked', 'Your tickets are tied to this address — write to us to change it.')); ?></span>
            </div>

            <div class="w-field">
                <label class="w-label" for="profile-phone"><?php echo esc_html(sc_t('frontend.mobile', 'Mobile')); ?></label>
                <span class="w-acc__tel">
                    <select class="w-select" id="profile-phone-code" name="phone_code"
                            aria-label="<?php echo esc_attr(sc_t('frontend.country_code', 'Country code')); ?>">
                        <?php foreach (['+20', '+966', '+971', '+965', '+974', '+218', '+249'] as $code): ?>
                            <option value="<?php echo esc_attr($code); ?>" <?php selected($phone_code, $code); ?>>
                                <?php echo esc_html($code); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input class="w-input" type="tel" id="profile-phone" name="phone"
                           value="<?php echo esc_attr($phone_number); ?>" placeholder="100 000 0000">
                </span>
            </div>

            <span class="w-acc__legend"><?php echo esc_html(sc_t('frontend.change_password', 'Change password')); ?></span>

            <div class="w-field">
                <label class="w-label" for="current_password"><?php echo esc_html(sc_t('frontend.current_password', 'Current password')); ?></label>
                <input class="w-input" type="password" id="current_password" name="current_password" autocomplete="current-password">
            </div>

            <div class="w-field">
                <label class="w-label" for="new_password"><?php echo esc_html(sc_t('frontend.new_password', 'New password')); ?></label>
                <input class="w-input" type="password" id="new_password" name="new_password" minlength="6" autocomplete="new-password">
            </div>

            <div class="w-field">
                <label class="w-label" for="confirm_password"><?php echo esc_html(sc_t('frontend.confirm_password', 'Confirm password')); ?></label>
                <input class="w-input" type="password" id="confirm_password" name="confirm_password" autocomplete="new-password">
            </div>

            <button class="w-btn w-btn--lg" type="submit" style="align-self:flex-start">
                <?php echo esc_html(sc_t('frontend.save', 'Save')); ?>
            </button>
        </form>

        <a class="w-btn w-btn--outline sc-nav-logout" href="<?php echo esc_url(wp_logout_url(home_url())); ?>"
           style="align-self:flex-start">
            <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
            <?php echo esc_html(sc_t('frontend.sign_out', 'Sign out')); ?>
        </a>
    </section>
</main>

<script>
jQuery(document).ready(function($) {
    // Tab Navigation
    $('.sc-account-nav .sc-nav-item[data-tab]').on('click', function(e) {
        e.preventDefault();
        var tab = $(this).data('tab');

        // Update active nav
        $('.sc-account-nav .sc-nav-item').removeClass('active');
        $(this).addClass('active');

        // Show tab content
        $('.sc-tab-content').hide();
        $('[data-tab-content="' + tab + '"]').show();
    });

    // Download QR Code directly
    $('.btn-download-qr').on('click', function() {
        var ticketId = $(this).data('ticket-id');
        var qrData = $(this).data('qr');
        var eventTitle = $(this).data('event-title');

        if (typeof QRCode !== 'undefined' && typeof QRCode.toDataURL === 'function') {
            QRCode.toDataURL(qrData || ticketId, {
                width: 400,
                height: 400,
                margin: 2,
                errorCorrectionLevel: 'H',
                color: {
                    dark: '#000000',
                    light: '#ffffff'
                }
            }, function(err, url) {
                if (!err && url) {
                    var link = document.createElement('a');
                    var fileName = eventTitle ? eventTitle.replace(/[^a-z0-9]/gi, '-') : ticketId;
                    link.download = 'QR-' + fileName + '.png';
                    link.href = url;
                    link.click();
                }
            });
        }
    });

    // Request Certificate
    $('.sc-request-certificate').on('click', function() {
        var $btn = $(this);
        var ticketCode = $btn.data('ticket-code');
        var originalHtml = $btn.html();

        $btn.prop('disabled', true).html('<span class="sc-auth-spinner"></span> <?php echo esc_js(__('Requesting...', 'sc_events')); ?>');

        $.ajax({
            url: scPublic.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_request_certificate_public',
                nonce: scPublic.nonce,
                ticket_code: ticketCode
            },
            success: function(response) {
                if (response.success && response.data.download_url) {
                    $btn.replaceWith(
                        '<a href="' + response.data.download_url + '" class="sc-cert-btn sc-cert-download" target="_blank">' +
                        '<i class="fa-solid fa-download"></i> <?php echo esc_js(__('Download', 'sc_events')); ?></a>'
                    );
                    Swal.fire({
                        icon: 'success',
                        title: '<?php echo esc_js(__('Certificate Ready!', 'sc_events')); ?>',
                        text: '<?php echo esc_js(__('Your certificate has been issued.', 'sc_events')); ?>',
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    $btn.prop('disabled', false).html(originalHtml);
                    Swal.fire({
                        icon: 'info',
                        title: '<?php echo esc_js(__('Not Available', 'sc_events')); ?>',
                        text: response.data && response.data.message ? response.data.message : '<?php echo esc_js(__('Certificate is not available yet.', 'sc_events')); ?>'
                    });
                }
            },
            error: function() {
                $btn.prop('disabled', false).html(originalHtml);
            }
        });
    });

    // Logout
    $('.sc-nav-logout').on('click', function(e) {
        e.preventDefault();
        var primaryColor = getComputedStyle(document.documentElement).getPropertyValue('--sc-primary').trim() || '#D4AF37';
        Swal.fire({
            title: '<?php echo esc_js(__('Logout?', 'sc_events')); ?>',
            text: '<?php echo esc_js(__('Are you sure you want to logout?', 'sc_events')); ?>',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: primaryColor,
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<?php echo esc_js(__('Yes, logout', 'sc_events')); ?>',
            cancelButtonText: '<?php echo esc_js(__('Cancel', 'sc_events')); ?>'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '<?php echo esc_js(wp_logout_url(home_url('/'))); ?>';
            }
        });
    });

    // Profile Form
    $('#profile-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $button = $form.find('button[type="submit"]');
        var originalText = $button.html();
        var primaryColor = getComputedStyle(document.documentElement).getPropertyValue('--sc-primary').trim() || '#D4AF37';

        // Validate passwords
        var newPass = $form.find('[name="new_password"]').val();
        var confirmPass = $form.find('[name="confirm_password"]').val();

        if (newPass && newPass !== confirmPass) {
            Swal.fire({
                icon: 'error',
                title: '<?php echo esc_js(__('Password Mismatch', 'sc_events')); ?>',
                text: '<?php echo esc_js(__('New passwords do not match', 'sc_events')); ?>',
                confirmButtonColor: primaryColor
            });
            return;
        }

        $button.prop('disabled', true).html('<span class="sc-auth-spinner"></span> <?php echo esc_js(__('Saving...', 'sc_events')); ?>');

        // Combine phone code and number
        var phoneCode = $form.find('[name="phone_code"]').val() || '+20';
        var phoneNumber = $form.find('[name="phone"]').val().trim();
        var fullPhone = phoneNumber ? phoneCode + phoneNumber : '';

        $.ajax({
            url: scPublic.ajaxurl,
            type: 'POST',
            data: {
                action: 'sc_update_profile',
                nonce: scPublic.nonce,
                name: $form.find('[name="name"]').val(),
                phone: fullPhone,
                current_password: $form.find('[name="current_password"]').val(),
                new_password: newPass
            },
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '<?php echo esc_js(__('Saved!', 'sc_events')); ?>',
                        text: response.data.message || '<?php echo esc_js(__('Profile updated successfully', 'sc_events')); ?>',
                        timer: 1500,
                        showConfirmButton: false
                    });
                    // Clear password fields
                    $form.find('[name="current_password"], [name="new_password"], [name="confirm_password"]').val('');
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: '<?php echo esc_js(__('Error', 'sc_events')); ?>',
                        text: response.data.message || '<?php echo esc_js(__('Could not update profile', 'sc_events')); ?>',
                        confirmButtonColor: primaryColor
                    });
                }
                $button.prop('disabled', false).html(originalText);
            },
            error: function(xhr, status) {
                var errorMsg = '<?php echo esc_js(__('Failed to update profile. ', 'sc_events')); ?>';
                if (status === 'timeout') {
                    errorMsg += '<?php echo esc_js(__('The request timed out. Please try again.', 'sc_events')); ?>';
                } else if (xhr.status === 0) {
                    errorMsg += '<?php echo esc_js(__('Could not connect to server. Please check your internet connection.', 'sc_events')); ?>';
                } else if (xhr.status === 403) {
                    errorMsg += '<?php echo esc_js(__('Your session may have expired. Please refresh and try again.', 'sc_events')); ?>';
                } else if (xhr.status === 500) {
                    errorMsg += '<?php echo esc_js(__('Server error occurred. Please try again later.', 'sc_events')); ?>';
                } else {
                    errorMsg += '<?php echo esc_js(__('Please try again or contact support.', 'sc_events')); ?>';
                }
                Swal.fire({
                    icon: 'error',
                    title: '<?php echo esc_js(__('Error', 'sc_events')); ?>',
                    text: errorMsg,
                    confirmButtonColor: primaryColor
                });
                $button.prop('disabled', false).html(originalText);
            }
        });
    });
});
</script>

<?php
// Load footer
get_template_part('template-parts/public/footer', 'public');
?>
