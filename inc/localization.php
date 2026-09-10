<?php
/**
 * Localization Functions
 *
 * Provides translation functions, RTL support, and Hijri calendar utilities.
 *
 * @package suspended_starter_events
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main Localization Class
 */
class SC_Localization {

    /**
     * Instance
     */
    private static $instance = null;

    /**
     * Translations cache
     */
    private $translations = [];

    /**
     * Current language
     */
    private $current_lang = 'en';

    /**
     * Available languages
     */
    private $available_languages = ['en', 'ar'];

    /**
     * Hijri months
     */
    private $hijri_months = [
        1 => ['en' => 'Muharram', 'ar' => 'محرم'],
        2 => ['en' => 'Safar', 'ar' => 'صفر'],
        3 => ['en' => 'Rabi al-Awwal', 'ar' => 'ربيع الأول'],
        4 => ['en' => 'Rabi al-Thani', 'ar' => 'ربيع الثاني'],
        5 => ['en' => 'Jumada al-Awwal', 'ar' => 'جمادى الأولى'],
        6 => ['en' => 'Jumada al-Thani', 'ar' => 'جمادى الآخرة'],
        7 => ['en' => 'Rajab', 'ar' => 'رجب'],
        8 => ['en' => 'Shaban', 'ar' => 'شعبان'],
        9 => ['en' => 'Ramadan', 'ar' => 'رمضان'],
        10 => ['en' => 'Shawwal', 'ar' => 'شوال'],
        11 => ['en' => 'Dhul Qadah', 'ar' => 'ذو القعدة'],
        12 => ['en' => 'Dhul Hijjah', 'ar' => 'ذو الحجة'],
    ];

    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->detect_language();
        $this->load_translations();
    }

    /**
     * Detect current language
     */
    private function detect_language() {
        // 1. Site-wide language setting (highest priority for ALL pages)
        $site_lang = get_option('sc_site_language', '');
        if ($site_lang && in_array($site_lang, $this->available_languages)) {
            $this->current_lang = $site_lang;
            return;
        }

        // 2. Check user preference in cookie
        if (isset($_COOKIE['sc_language']) && in_array($_COOKIE['sc_language'], $this->available_languages)) {
            $this->current_lang = $_COOKIE['sc_language'];
            return;
        }

        // 3. Check user meta if logged in
        if (is_user_logged_in()) {
            $user_lang = get_user_meta(get_current_user_id(), 'sc_language', true);
            if ($user_lang && in_array($user_lang, $this->available_languages)) {
                $this->current_lang = $user_lang;
                return;
            }
        }

        // 4. Check WordPress locale
        $locale = get_locale();
        if (strpos($locale, 'ar') === 0) {
            $this->current_lang = 'ar';
            return;
        }

        // 5. Check browser language
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $browser_lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
            if ($browser_lang === 'ar') {
                $this->current_lang = 'ar';
                return;
            }
        }

        // Default to English
        $this->current_lang = 'en';
    }

    /**
     * Check if current page is a dashboard page
     */
    private function is_dashboard_page() {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        return strpos($uri, 'event-manager-dashboard') !== false || is_admin();
    }

    /**
     * Load translation files
     */
    private function load_translations() {
        $translations_file = get_stylesheet_directory() . '/languages/ar_SA.php';

        if (file_exists($translations_file)) {
            $this->translations = include $translations_file;
        }
    }

    /**
     * Get translation
     *
     * @param string $key Translation key (dot notation: 'section.key')
     * @param string $fallback Fallback text if translation not found
     * @param array $replacements Placeholder replacements
     * @return string
     */
    public function get($key, $fallback = '', $replacements = []) {
        // If English, return fallback directly
        if ($this->current_lang === 'en') {
            return $this->replace_placeholders($fallback ?: $key, $replacements);
        }

        // Parse dot notation key
        $keys = explode('.', $key);
        $value = $this->translations;

        foreach ($keys as $k) {
            if (is_array($value) && isset($value[$k])) {
                $value = $value[$k];
            } else {
                // Translation not found, use fallback
                return $this->replace_placeholders($fallback ?: $key, $replacements);
            }
        }

        // If value is still an array, key was incomplete
        if (is_array($value)) {
            return $this->replace_placeholders($fallback ?: $key, $replacements);
        }

        return $this->replace_placeholders($value, $replacements);
    }

    /**
     * Replace placeholders in text
     *
     * @param string $text Text with placeholders
     * @param array $replacements Key-value pairs for replacement
     * @return string
     */
    private function replace_placeholders($text, $replacements) {
        if (empty($replacements)) {
            return $text;
        }

        foreach ($replacements as $key => $value) {
            $text = str_replace('{' . $key . '}', $value, $text);
        }

        return $text;
    }

    /**
     * Set language
     *
     * @param string $lang Language code
     * @param bool $persist Save to cookie/user meta
     */
    public function set_language($lang, $persist = true) {
        if (!in_array($lang, $this->available_languages)) {
            return false;
        }

        $this->current_lang = $lang;

        if ($persist) {
            // Set cookie for 30 days
            setcookie('sc_language', $lang, time() + (30 * DAY_IN_SECONDS), COOKIEPATH, COOKIE_DOMAIN);

            // Save to user meta if logged in
            if (is_user_logged_in()) {
                update_user_meta(get_current_user_id(), 'sc_language', $lang);
            }
        }

        return true;
    }

    /**
     * Get current language
     *
     * @return string
     */
    public function get_language() {
        return $this->current_lang;
    }

    /**
     * Check if current language is RTL
     *
     * @return bool
     */
    public function is_rtl() {
        return $this->current_lang === 'ar';
    }

    /**
     * Get text direction
     *
     * @return string 'rtl' or 'ltr'
     */
    public function get_direction() {
        return $this->is_rtl() ? 'rtl' : 'ltr';
    }

    /**
     * Get available languages
     *
     * @return array
     */
    public function get_available_languages() {
        return [
            'en' => [
                'code' => 'en',
                'name' => 'English',
                'native' => 'English',
                'dir' => 'ltr',
            ],
            'ar' => [
                'code' => 'ar',
                'name' => 'Arabic',
                'native' => 'العربية',
                'dir' => 'rtl',
            ],
        ];
    }

    /**
     * Convert Gregorian date to Hijri
     *
     * @param int|string $date Unix timestamp or date string
     * @param string $format Output format
     * @return string
     */
    public function to_hijri($date = null, $format = 'full') {
        if ($date === null) {
            $date = time();
        } elseif (is_string($date)) {
            $date = strtotime($date);
        }

        // Get Gregorian date components
        $day = (int) date('j', $date);
        $month = (int) date('n', $date);
        $year = (int) date('Y', $date);

        // Convert to Julian Day Number
        $jd = gregoriantojd($month, $day, $year);

        // Convert to Hijri
        $hijri = $this->jd_to_hijri($jd);

        // Format output
        return $this->format_hijri($hijri, $format);
    }

    /**
     * Convert Julian Day to Hijri date
     *
     * @param int $jd Julian Day Number
     * @return array [day, month, year]
     */
    private function jd_to_hijri($jd) {
        $jd = floor($jd) + 0.5;
        $year = floor(((30 * ($jd - 1948439.5)) + 10646) / 10631);
        $month = min(12, ceil(($jd - (29 + $this->hijri_to_jd(1, 1, $year))) / 29.5) + 1);
        $day = $jd - $this->hijri_to_jd(1, $month, $year) + 1;

        return [
            'day' => (int) $day,
            'month' => (int) $month,
            'year' => (int) $year,
        ];
    }

    /**
     * Convert Hijri date to Julian Day
     *
     * @param int $day
     * @param int $month
     * @param int $year
     * @return float
     */
    private function hijri_to_jd($day, $month, $year) {
        return floor((11 * $year + 3) / 30) +
               354 * $year +
               30 * $month -
               floor(($month - 1) / 2) +
               $day + 1948440 - 385;
    }

    /**
     * Format Hijri date
     *
     * @param array $hijri Hijri date components
     * @param string $format Format type
     * @return string
     */
    private function format_hijri($hijri, $format) {
        $lang = $this->current_lang;
        $month_name = $this->hijri_months[$hijri['month']][$lang];

        switch ($format) {
            case 'short':
                if ($lang === 'ar') {
                    return $this->to_arabic_numerals($hijri['day']) . '/' .
                           $this->to_arabic_numerals($hijri['month']) . '/' .
                           $this->to_arabic_numerals($hijri['year']);
                }
                return $hijri['day'] . '/' . $hijri['month'] . '/' . $hijri['year'];

            case 'medium':
                if ($lang === 'ar') {
                    return $this->to_arabic_numerals($hijri['day']) . ' ' .
                           $month_name . ' ' .
                           $this->to_arabic_numerals($hijri['year']);
                }
                return $hijri['day'] . ' ' . $month_name . ' ' . $hijri['year'];

            case 'full':
            default:
                if ($lang === 'ar') {
                    return $this->to_arabic_numerals($hijri['day']) . ' ' .
                           $month_name . ' ' .
                           $this->to_arabic_numerals($hijri['year']) . ' هـ';
                }
                return $hijri['day'] . ' ' . $month_name . ' ' . $hijri['year'] . ' H';
        }
    }

    /**
     * Convert Western numerals to Arabic-Indic numerals
     *
     * @param mixed $number Number to convert
     * @return string
     */
    public function to_arabic_numerals($number) {
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $western = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace($western, $arabic, (string) $number);
    }

    /**
     * Convert Arabic-Indic numerals to Western numerals
     *
     * @param string $number Number to convert
     * @return string
     */
    public function to_western_numerals($number) {
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $western = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace($arabic, $western, $number);
    }

    /**
     * Format currency for Saudi Arabia
     *
     * @param float $amount Amount
     * @param bool $include_symbol Include currency symbol
     * @return string
     */
    public function format_currency($amount, $include_symbol = true) {
        $formatted = number_format($amount, 2, '.', ',');

        if ($this->current_lang === 'ar') {
            $formatted = $this->to_arabic_numerals($formatted);
            if ($include_symbol) {
                return $formatted . ' ر.س';
            }
        } else {
            if ($include_symbol) {
                return 'SAR ' . $formatted;
            }
        }

        return $formatted;
    }

    /**
     * Format date based on language
     *
     * @param int|string $date Unix timestamp or date string
     * @param string $format Format type: 'short', 'medium', 'long', 'full', 'both'
     * @return string
     */
    public function format_date($date = null, $format = 'medium') {
        if ($date === null) {
            $date = time();
        } elseif (is_string($date)) {
            $date = strtotime($date);
        }

        $gregorian = '';
        $hijri = '';

        switch ($format) {
            case 'short':
                $gregorian = date('Y-m-d', $date);
                break;

            case 'medium':
                $gregorian = date('M j, Y', $date);
                break;

            case 'long':
                $gregorian = date('F j, Y', $date);
                break;

            case 'full':
                $gregorian = date('l, F j, Y', $date);
                break;

            case 'both':
                $gregorian = date('M j, Y', $date);
                $hijri = $this->to_hijri($date, 'medium');

                if ($this->current_lang === 'ar') {
                    return $hijri . ' / ' . $gregorian;
                }
                return $gregorian . ' / ' . $hijri;

            default:
                $gregorian = date($format, $date);
        }

        if ($this->current_lang === 'ar') {
            return $this->to_arabic_numerals($gregorian);
        }

        return $gregorian;
    }

    /**
     * Format time based on language
     *
     * @param int|string $time Unix timestamp or time string
     * @param bool $with_period Include AM/PM
     * @return string
     */
    public function format_time($time = null, $with_period = true) {
        if ($time === null) {
            $time = time();
        } elseif (is_string($time)) {
            $time = strtotime($time);
        }

        if ($with_period) {
            $formatted = date('g:i A', $time);

            if ($this->current_lang === 'ar') {
                $formatted = str_replace(['AM', 'PM'], ['ص', 'م'], $formatted);
                $formatted = $this->to_arabic_numerals($formatted);
            }
        } else {
            $formatted = date('H:i', $time);

            if ($this->current_lang === 'ar') {
                $formatted = $this->to_arabic_numerals($formatted);
            }
        }

        return $formatted;
    }

    /**
     * Format datetime
     *
     * @param int|string $datetime Unix timestamp or datetime string
     * @param string $date_format Date format
     * @param bool $with_period Time period
     * @return string
     */
    public function format_datetime($datetime = null, $date_format = 'medium', $with_period = true) {
        if ($datetime === null) {
            $datetime = time();
        } elseif (is_string($datetime)) {
            $datetime = strtotime($datetime);
        }

        $date = $this->format_date($datetime, $date_format);
        $time = $this->format_time($datetime, $with_period);

        if ($this->current_lang === 'ar') {
            return $date . ' - ' . $time;
        }

        return $date . ' at ' . $time;
    }

    /**
     * Format relative time (time ago)
     *
     * @param int|string $time Unix timestamp or time string
     * @return string
     */
    public function time_ago($time) {
        if (is_string($time)) {
            $time = strtotime($time);
        }

        $diff = time() - $time;

        if ($diff < 0) {
            return $this->time_until(abs($diff));
        }

        $intervals = [
            31536000 => ['year', 'سنة', 'سنوات'],
            2592000  => ['month', 'شهر', 'أشهر'],
            604800   => ['week', 'أسبوع', 'أسابيع'],
            86400    => ['day', 'يوم', 'أيام'],
            3600     => ['hour', 'ساعة', 'ساعات'],
            60       => ['minute', 'دقيقة', 'دقائق'],
            1        => ['second', 'ثانية', 'ثواني'],
        ];

        foreach ($intervals as $secs => $names) {
            $count = floor($diff / $secs);
            if ($count > 0) {
                if ($this->current_lang === 'ar') {
                    $unit = $count === 1 ? $names[1] : $names[2];
                    return 'منذ ' . $this->to_arabic_numerals($count) . ' ' . $unit;
                } else {
                    $unit = $count === 1 ? $names[0] : $names[0] . 's';
                    return $count . ' ' . $unit . ' ago';
                }
            }
        }

        return $this->current_lang === 'ar' ? 'الآن' : 'just now';
    }

    /**
     * Format time until (in the future)
     *
     * @param int $seconds Seconds until
     * @return string
     */
    private function time_until($seconds) {
        $intervals = [
            31536000 => ['year', 'سنة', 'سنوات'],
            2592000  => ['month', 'شهر', 'أشهر'],
            604800   => ['week', 'أسبوع', 'أسابيع'],
            86400    => ['day', 'يوم', 'أيام'],
            3600     => ['hour', 'ساعة', 'ساعات'],
            60       => ['minute', 'دقيقة', 'دقائق'],
            1        => ['second', 'ثانية', 'ثواني'],
        ];

        foreach ($intervals as $secs => $names) {
            $count = floor($seconds / $secs);
            if ($count > 0) {
                if ($this->current_lang === 'ar') {
                    $unit = $count === 1 ? $names[1] : $names[2];
                    return 'خلال ' . $this->to_arabic_numerals($count) . ' ' . $unit;
                } else {
                    $unit = $count === 1 ? $names[0] : $names[0] . 's';
                    return 'in ' . $count . ' ' . $unit;
                }
            }
        }

        return $this->current_lang === 'ar' ? 'الآن' : 'now';
    }

    /**
     * Get the HTML attributes for RTL support
     *
     * @return string
     */
    public function get_html_attrs() {
        $dir = $this->get_direction();
        $lang = $this->current_lang;

        return 'dir="' . esc_attr($dir) . '" lang="' . esc_attr($lang) . '"';
    }

    /**
     * Get body classes for localization
     *
     * @return array
     */
    public function get_body_classes() {
        $classes = ['lang-' . $this->current_lang];

        if ($this->is_rtl()) {
            $classes[] = 'rtl';
        } else {
            $classes[] = 'ltr';
        }

        return $classes;
    }
}

// ============================================
// WordPress Locale Sync
// ============================================

/**
 * Sync WordPress locale with sc_site_language setting
 * Only applies to frontend pages (not dashboard or wp-admin)
 * This ensures date_i18n() and other WP functions use Arabic on the public site
 */
add_filter('locale', function ($locale) {
    // Skip for dashboard and wp-admin
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (is_admin() || strpos($uri, 'event-manager-dashboard') !== false) {
        return $locale;
    }

    $site_lang = get_option('sc_site_language', '');
    if ($site_lang === 'ar') {
        return 'ar';
    }
    return $locale;
}, 5);

// ============================================
// Helper Functions
// ============================================

/**
 * Get SC_Localization instance
 *
 * @return SC_Localization
 */
function sc_localization() {
    return SC_Localization::get_instance();
}

/**
 * Translate a string
 *
 * @param string $key Translation key
 * @param string $fallback Fallback text
 * @param array $replacements Placeholder replacements
 * @return string
 */
function sc_t($key, $fallback = '', $replacements = []) {
    return sc_localization()->get($key, $fallback, $replacements);
}

/**
 * Echo translated string
 *
 * @param string $key Translation key
 * @param string $fallback Fallback text
 * @param array $replacements Placeholder replacements
 */
function sc_te($key, $fallback = '', $replacements = []) {
    echo esc_html(sc_t($key, $fallback, $replacements));
}

/**
 * Check if current language is RTL
 *
 * @return bool
 */
function sc_is_rtl() {
    return sc_localization()->is_rtl();
}

/**
 * Get current language code
 *
 * @return string
 */
function sc_get_lang() {
    return sc_localization()->get_language();
}

/**
 * Set current language
 *
 * @param string $lang Language code
 * @param bool $persist Save preference
 * @return bool
 */
function sc_set_lang($lang, $persist = true) {
    return sc_localization()->set_language($lang, $persist);
}

/**
 * Format date with localization
 *
 * @param int|string $date Date
 * @param string $format Format type
 * @return string
 */
function sc_date($date = null, $format = 'medium') {
    return sc_localization()->format_date($date, $format);
}

/**
 * Format time with localization
 *
 * @param int|string $time Time
 * @param bool $with_period Include AM/PM
 * @return string
 */
function sc_time($time = null, $with_period = true) {
    return sc_localization()->format_time($time, $with_period);
}

/**
 * Format datetime with localization
 *
 * @param int|string $datetime DateTime
 * @param string $date_format Date format
 * @param bool $with_period Include AM/PM
 * @return string
 */
function sc_datetime($datetime = null, $date_format = 'medium', $with_period = true) {
    return sc_localization()->format_datetime($datetime, $date_format, $with_period);
}

/**
 * Convert to Hijri date
 *
 * @param int|string $date Date
 * @param string $format Format type
 * @return string
 */
function sc_hijri($date = null, $format = 'full') {
    return sc_localization()->to_hijri($date, $format);
}

/**
 * Format currency (SAR)
 *
 * @param float $amount Amount
 * @param bool $include_symbol Include symbol
 * @return string
 */
function sc_currency($amount, $include_symbol = true) {
    return sc_localization()->format_currency($amount, $include_symbol);
}

/**
 * Format relative time (time ago)
 *
 * @param int|string $time Time
 * @return string
 */
function sc_time_ago($time) {
    return sc_localization()->time_ago($time);
}

/**
 * Convert number to Arabic numerals
 *
 * @param mixed $number Number
 * @return string
 */
function sc_arabic_num($number) {
    return sc_localization()->to_arabic_numerals($number);
}

/**
 * Get HTML attributes for RTL
 *
 * @return string
 */
function sc_html_attrs() {
    return sc_localization()->get_html_attrs();
}

/**
 * Get body classes for localization
 *
 * @return array
 */
function sc_body_classes() {
    return sc_localization()->get_body_classes();
}

/**
 * Get text alignment class based on RTL
 *
 * @param bool $reverse Reverse the alignment
 * @return string
 */
function sc_text_align($reverse = false) {
    $rtl = sc_is_rtl();

    if ($reverse) {
        $rtl = !$rtl;
    }

    return $rtl ? 'text-right' : 'text-left';
}

/**
 * Get start alignment (left for LTR, right for RTL)
 *
 * @return string
 */
function sc_align_start() {
    return sc_is_rtl() ? 'right' : 'left';
}

/**
 * Get end alignment (right for LTR, left for RTL)
 *
 * @return string
 */
function sc_align_end() {
    return sc_is_rtl() ? 'left' : 'right';
}

/**
 * Get margin start class
 *
 * @param string $size Size (sm, md, lg, etc.)
 * @return string
 */
function sc_ms($size = '') {
    $prefix = sc_is_rtl() ? 'me' : 'ms';
    return $size ? $prefix . '-' . $size : $prefix;
}

/**
 * Get margin end class
 *
 * @param string $size Size
 * @return string
 */
function sc_me($size = '') {
    $prefix = sc_is_rtl() ? 'ms' : 'me';
    return $size ? $prefix . '-' . $size : $prefix;
}

/**
 * Get padding start class
 *
 * @param string $size Size
 * @return string
 */
function sc_ps($size = '') {
    $prefix = sc_is_rtl() ? 'pe' : 'ps';
    return $size ? $prefix . '-' . $size : $prefix;
}

/**
 * Get padding end class
 *
 * @param string $size Size
 * @return string
 */
function sc_pe($size = '') {
    $prefix = sc_is_rtl() ? 'ps' : 'pe';
    return $size ? $prefix . '-' . $size : $prefix;
}

/**
 * Language Switcher HTML
 *
 * @param array $args Options
 * @return string
 */
function sc_language_switcher($args = []) {
    $defaults = [
        'class' => 'language-switcher',
        'dropdown' => true,
        'show_current' => true,
        'show_flag' => false,
    ];

    $args = wp_parse_args($args, $defaults);
    $localization = sc_localization();
    $current = $localization->get_language();
    $languages = $localization->get_available_languages();

    $html = '<div class="' . esc_attr($args['class']) . '">';

    if ($args['dropdown']) {
        $html .= '<div class="dropdown">';
        $html .= '<button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">';

        if ($args['show_current']) {
            $html .= esc_html($languages[$current]['native']);
        }

        $html .= '</button>';
        $html .= '<ul class="dropdown-menu">';

        foreach ($languages as $code => $lang) {
            $active = $code === $current ? ' active' : '';
            $html .= '<li>';
            $html .= '<a class="dropdown-item' . $active . '" href="#" data-lang="' . esc_attr($code) . '">';
            $html .= esc_html($lang['native']);
            $html .= '</a>';
            $html .= '</li>';
        }

        $html .= '</ul>';
        $html .= '</div>';
    } else {
        $html .= '<ul class="list-inline mb-0">';

        foreach ($languages as $code => $lang) {
            $active = $code === $current ? ' class="fw-bold"' : '';
            $html .= '<li class="list-inline-item">';
            $html .= '<a href="#" data-lang="' . esc_attr($code) . '"' . $active . '>';
            $html .= esc_html($lang['native']);
            $html .= '</a>';
            $html .= '</li>';
        }

        $html .= '</ul>';
    }

    $html .= '</div>';

    // Add JavaScript for switching
    $html .= '<script>
    document.querySelectorAll("[data-lang]").forEach(function(el) {
        el.addEventListener("click", function(e) {
            e.preventDefault();
            var lang = this.dataset.lang;
            fetch("' . admin_url('admin-ajax.php') . '", {
                method: "POST",
                headers: {"Content-Type": "application/x-www-form-urlencoded"},
                body: "action=sc_switch_language&lang=" + lang + "&nonce=' . wp_create_nonce('sc_language_switch') . '"
            }).then(function() {
                window.location.reload();
            });
        });
    });
    </script>';

    return $html;
}

/**
 * AJAX handler for language switching
 */
function sc_ajax_switch_language() {
    // Verify nonce - check both 'nonce' and '_ajax_nonce' parameters
    $nonce = '';
    if (isset($_POST['nonce'])) {
        $nonce = $_POST['nonce'];
    } elseif (isset($_REQUEST['_ajax_nonce'])) {
        $nonce = $_REQUEST['_ajax_nonce'];
    } elseif (isset($_REQUEST['_wpnonce'])) {
        $nonce = $_REQUEST['_wpnonce'];
    }

    if (!wp_verify_nonce($nonce, 'sc_language_switch')) {
        wp_send_json_error(['message' => 'Security check failed']);
        return;
    }

    $lang = isset($_POST['lang']) ? sanitize_text_field($_POST['lang']) : '';

    if (empty($lang)) {
        wp_send_json_error(['message' => 'Language not specified']);
        return;
    }

    if (sc_set_lang($lang)) {
        /*
         * sc_set_lang stores the choice against this visitor — a cookie, plus
         * user meta when signed in. It used to also write sc_site_language,
         * which is the site-wide setting and outranks every per-visitor
         * source: one person clicking the button in the header switched the
         * language of the whole site for everybody. Only someone who can
         * manage options gets to do that.
         */
        if (current_user_can('manage_options')) {
            update_option('sc_site_language', $lang);
        }

        wp_send_json_success(['language' => $lang, 'message' => 'Language switched successfully']);
    } else {
        wp_send_json_error(['message' => 'Invalid language code']);
    }
}
add_action('wp_ajax_sc_switch_language', 'sc_ajax_switch_language');
add_action('wp_ajax_nopriv_sc_switch_language', 'sc_ajax_switch_language');

/**
 * Initialize localization
 */
function sc_init_localization() {
    // Initialize the localization instance early
    sc_localization();
}
add_action('init', 'sc_init_localization', 1);
