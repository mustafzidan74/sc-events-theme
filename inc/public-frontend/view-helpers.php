<?php
/**
 * Small helpers the public templates share.
 *
 * Each of these started as a block copied between templates — resolving an
 * image reference appeared eleven times across eight files, the date range in
 * five, the speaker initials in three. Copies drift: the same fix has to be
 * made in every one, and it only takes missing a single copy for two pages to
 * disagree about what today's date is or how a name is abbreviated.
 *
 * Nothing here reaches for globals or prints anything. They take values and
 * return values, so a template can use one wherever it needs the answer.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) exit;

/**
 * Resolve an image reference to a URL.
 *
 * The custom tables store some images as attachment ids and others as plain
 * URLs, sometimes in the same column, so every template that shows one had to
 * decide which it was holding.
 *
 * @param mixed  $ref  Attachment id, URL, or an array carrying either.
 * @param string $size Registered image size, used only for attachments.
 * @return string URL, or an empty string when there is nothing to show.
 */
function sc_image_src($ref, $size = 'full') {
    if (empty($ref)) {
        return '';
    }

    if (is_array($ref)) {
        $ref = $ref['id'] ?? ($ref['url'] ?? '');
    }

    if (!is_numeric($ref)) {
        return (string) $ref;
    }

    // wp_get_attachment_url is what the templates called for full size, and it
    // answers for any attachment; wp_get_attachment_image_url returns false for
    // anything that is not an image. Keeping both keeps the old behaviour.
    if ($size === 'full') {
        return wp_get_attachment_url($ref) ?: '';
    }

    return wp_get_attachment_image_url($ref, $size) ?: '';
}

/**
 * An <img> for a picture at the size it is shown.
 *
 * The templates used to print the original upload — a 2.3 MB speaker photo in
 * a 180-pixel circle, sixteen megabytes on the home page. For an attachment
 * this prints WordPress's srcset, so each screen takes the smallest copy that
 * is still sharp on it. Width and height are left off: these layouts are sized
 * in CSS, and the attributes would change them.
 *
 * @param int|string|array $ref   Attachment ID, a URL, or ['id' => …] / ['url' => …].
 * @param string           $size  Copy used as src, for browsers that ignore srcset.
 * @param string           $sizes How wide the picture is shown, as the CSS "sizes" list.
 * @param array            $attr  alt, class, loading, fetchpriority…
 * @param int              $max   Widest copy worth offering; 0 keeps WordPress's limit.
 * @return string The tag, or '' when there is no picture.
 */
function sc_img($ref, $size, $sizes, array $attr = array(), $max = 0) {
    if (is_array($ref)) {
        $ref = $ref['id'] ?? ($ref['url'] ?? '');
    }
    if (empty($ref)) {
        return '';
    }
    $attr = array_merge(array('alt' => '', 'loading' => 'lazy', 'decoding' => 'async'), $attr);

    if (is_numeric($ref)) {
        $cap = $max ? static function () use ($max) { return $max; } : null;
        if ($cap) {
            add_filter('max_srcset_image_width', $cap);
        }
        $html = wp_get_attachment_image((int) $ref, $size, false, $attr + array('sizes' => $sizes));
        if ($cap) {
            remove_filter('max_srcset_image_width', $cap);
        }
        if ($html) {
            return preg_replace('/\s(?:width|height)="\d+"/', '', $html, 2);
        }
        // Not an image WordPress can size (an SVG logo, say): print the file itself.
        $ref = wp_get_attachment_url((int) $ref);
        if (!$ref) {
            return '';
        }
    }

    $html = '<img src="' . esc_url($ref) . '"';
    foreach ($attr as $name => $value) {
        if ($value === false || $value === null) {
            continue;
        }
        $html .= ' ' . esc_attr($name) . '="' . esc_attr($value) . '"';
    }
    return $html . '>';
}

/**
 * Format a run of days the way the design states it.
 *
 * "7–9 Oct" while the run stays inside one month, "28 Sep – 2 Oct" when it
 * crosses one, and a single date when it does not run at all. Under RTL the
 * numerals are isolated in CSS, so the order stays readable.
 *
 * @param string $start Start date, anything strtotime understands.
 * @param string $end   End date. Falls back to the start when empty.
 * @param bool   $year  Append the year — the home hero wants it, the cards do not.
 * @return string
 */
function sc_date_range($start, $end = '', $year = false) {
    $from = strtotime($start);

    if (!$from) {
        return '';
    }

    $to     = $end ? strtotime($end) : $from;
    $suffix = $year ? ' Y' : '';

    if ($from === $to) {
        return date_i18n('j M' . $suffix, $from);
    }

    if (date('Y-m', $from) === date('Y-m', $to)) {
        return date_i18n('j', $from) . '–' . date_i18n('j M' . $suffix, $to);
    }

    return date_i18n('j M', $from) . ' – ' . date_i18n('j M' . $suffix, $to);
}

/**
 * Two initials for a speaker with no portrait.
 *
 * Titles are stripped first, or every second dentist would be a "DR".
 *
 * @param string $name Full name as stored.
 * @return string
 */
function sc_initials($name) {
    $name = trim(preg_replace('/^(dr\.?|prof\.?|mr\.?|mrs\.?|ms\.?)\s+/i', '', (string) $name));

    if ($name === '') {
        return '';
    }

    $words = preg_split('/\s+/', $name);

    return mb_strtoupper(
        mb_substr($words[0] ?? '', 0, 1) . mb_substr($words[1] ?? '', 0, 1)
    );
}

/**
 * The payment methods this install can actually take.
 *
 * Naming a gateway that is switched off promises something the checkout cannot
 * deliver, so only the enabled ones are listed.
 *
 * @return string[] Labels, in the order they are declared, without duplicates.
 */
function sc_live_gateways() {
    $names = [
        'paymob'     => sc_t('frontend.gateway_paymob', 'Cards & wallets'),
        'stripe'     => sc_t('frontend.gateway_stripe', 'Cards'),
        'kashier'    => sc_t('frontend.gateway_kashier', 'Kashier'),
        'myfatoorah' => sc_t('frontend.gateway_myfatoorah', 'MyFatoorah'),
    ];

    $live = [];

    foreach ($names as $key => $label) {
        $settings = get_option('sc_gateway_' . $key);

        if (is_array($settings) && !empty($settings['enabled'])) {
            $live[] = $label;
        }
    }

    return array_values(array_unique($live));
}

/**
 * Summarise a set of tickets for the page header.
 *
 * A price of 0 means the organiser has not set one — every IDC 2026 ticket
 * sits at 0.00 against a design quoting 1,200 EGP — so `is_free` here means
 * "nothing to quote" and the templates show no price rather than promising a
 * free ticket.
 *
 * @param array $tickets Rows from SC_Ticket.
 * @return array{has_tickets: bool, is_free: bool, min_price: float}
 */
function sc_ticket_pricing($tickets) {
    $summary = [
        'has_tickets' => false,
        'is_free'     => true,
        'min_price'   => 0.0,
    ];

    foreach ((array) $tickets as $ticket) {
        if (empty($ticket->is_active)) {
            continue;
        }

        $summary['has_tickets'] = true;
        $price = (float) $ticket->price;

        if ($price > 0) {
            $summary['is_free'] = false;

            if ($summary['min_price'] == 0 || $price < $summary['min_price']) {
                $summary['min_price'] = $price;
            }
        }
    }

    return $summary;
}
