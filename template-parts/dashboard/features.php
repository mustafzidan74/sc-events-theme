<?php
/**
 * Platform showcase — public page for people considering Wisdom for their event.
 *
 * Served without login at /event-manager-dashboard/features (dashboard-init.php).
 * Arabic and English: ?lang=ar|en, otherwise the visitor's browser language.
 * Every feature listed here exists in the theme; keep it that way when editing.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once get_template_directory() . '/inc/admin-dashboard/dashboard-nav.php'; // sc_dashboard_asset()

$lang = isset($_GET['lang']) ? sanitize_key(wp_unslash($_GET['lang'])) : '';
if (!in_array($lang, array('ar', 'en'), true)) {
    $accept = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? strtolower((string) $_SERVER['HTTP_ACCEPT_LANGUAGE']) : '';
    $lang = strpos($accept, 'ar') === 0 ? 'ar' : 'en';
}
$ar = $lang === 'ar';
$L = function ($en, $arabic) use ($ar) { return $ar ? $arabic : $en; };

$platform_name = get_option('sc_platform_name', get_bloginfo('name'));
$logo_id = get_option('sc_platform_logo');
$logo = $logo_id ? wp_get_attachment_image_src($logo_id, 'medium') : false;
$page_url = home_url('/event-manager-dashboard/features');

// Real totals from the reports, rounded down so the page does not need updating every day.
$stats = array();
if (function_exists('sc_report_overview_data')) {
    $o = sc_report_overview_data(false);
    $floor = function ($n) { return $n >= 1000 ? floor($n / 100) * 100 : $n; };
    $reg = array_sum(array_column($o['events'], 'registered'));
    $came = array_sum(array_column($o['events'], 'checked_in'));
    $certs = array_sum(array_column($o['events'], 'certificates'));
    $events = count(array_filter($o['events'], function ($e) { return $e['registered'] > 0; }));
    if ($reg > 0) {
        $stats = array(
            array(number_format_i18n($floor($reg)) . '+', $L('registrations handled', 'تسجيل تمت إدارته')),
            array(number_format_i18n($floor($came)) . '+', $L('people checked in at the door', 'شخص سجّل حضوره على الباب')),
            array(number_format_i18n($floor($certs)) . '+', $L('certificates issued', 'شهادة صدرت')),
            array(number_format_i18n($events), $L('congresses and forums', 'مؤتمر وملتقى')),
        );
    }
}

// Contact details from Settings → Contact; only what is filled in is shown.
$email = sanitize_email(get_option('sc_platform_email', ''));
$phone = trim((string) get_option('sc_platform_phone', ''));
$wa = preg_replace('/\D/', '', (string) get_option('sc_platform_whatsapp', ''));
if ($wa !== '' && strpos($wa, '0') === 0) {
    $wa = '20' . substr($wa, 1);
}
$wa_text = rawurlencode($L('Hello, I would like to know more about the Wisdom events platform.', 'مرحباً، أريد معرفة المزيد عن منصة Wisdom للفعاليات.'));
$primary_contact = $wa ? 'https://wa.me/' . $wa . '?text=' . $wa_text : ($email ? 'mailto:' . $email : home_url('/contact/'));

$icon = function ($name) {
    $paths = array(
        'calendar' => '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'ticket'   => '<path d="M3 8a2 2 0 0 0 2-2h14a2 2 0 0 0 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 0-2 2H5a2 2 0 0 0-2-2v-2a2 2 0 0 0 0-4z"/><path d="M13 6v12" stroke-dasharray="2 2"/>',
        'tag'      => '<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
        'tools'    => '<path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.4-.6-.6-2.4z"/>',
        'scan'     => '<path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2"/><path d="M7 12h10"/>',
        'badge'    => '<rect x="5" y="3" width="14" height="18" rx="2"/><circle cx="12" cy="10" r="3"/><path d="M8.5 17h7"/>',
        'users'    => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M21.5 20a6.5 6.5 0 0 0-4-6"/>',
        'award'    => '<circle cx="12" cy="9" r="6"/><path d="m8.5 14.5-1.5 7 5-3 5 3-1.5-7"/>',
        'chart'    => '<path d="M3 3v18h18"/><path d="M7 15l4-4 3 3 5-6"/>',
        'mic'      => '<rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v4"/>',
        'store'    => '<path d="M3 9 5 3h14l2 6"/><path d="M3 9h18v2a3 3 0 0 1-6 0 3 3 0 0 1-6 0 3 3 0 0 1-6 0z"/><path d="M5 13v8h14v-8"/>',
        'chat'     => '<path d="M21 12a8 8 0 0 1-11.6 7.1L3 21l1.9-6.4A8 8 0 1 1 21 12z"/>',
        'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'shield'   => '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z"/><path d="m9 12 2 2 4-4"/>',
    );
    return '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
};

$stages = array(
    array(
        'id' => 'before',
        'kicker' => $L('Before the event', 'قبل الفعالية'),
        'title' => $L('Registration that runs itself', 'تسجيل يدير نفسه'),
        'items' => array(
            array('calendar', $L('Event pages and programme', 'صفحات الفعالية والبرنامج'), array(
                $L('Multi-day events with halls, sessions and speakers', 'فعاليات متعددة الأيام بالقاعات والجلسات والمتحدثين'),
                $L('CME hours on sessions', 'ساعات CME على الجلسات'),
                $L('Arabic and English public site', 'موقع عام باللغتين العربية والإنجليزية'),
            )),
            array('ticket', $L('Tickets and registration', 'التذاكر والتسجيل'), array(
                $L('Several ticket types with quantities and sale dates', 'أنواع تذاكر متعددة بكميات وتواريخ بيع'),
                $L('Your own questions on the registration form', 'أسئلتك الخاصة في استمارة التسجيل'),
                $L('Ticket with QR code in the attendee\'s account', 'تذكرة برمز QR في حساب المشارك'),
            )),
            array('tag', $L('Coupons', 'أكواد الخصم'), array(
                $L('Percentage or fixed discounts', 'خصم بنسبة أو بمبلغ ثابت'),
                $L('Generate hundreds of codes at once, grouped by category', 'توليد مئات الأكواد دفعة واحدة وتنظيمها في تصنيفات'),
                $L('Usage limits and validity dates', 'حد للاستخدام وتواريخ صلاحية'),
            )),
            array('tools', $L('Workshops', 'الورش'), array(
                $L('Separate registration and seats for each workshop', 'تسجيل ومقاعد مستقلة لكل ورشة'),
                $L('Their own door at scanning time', 'بوابة خاصة بكل ورشة عند المسح'),
            )),
            array('users', $L('Attendee list', 'قائمة المشاركين'), array(
                $L('Search and filter thousands of registrations', 'البحث والتصفية في آلاف التسجيلات'),
                $L('Import from CSV, export to CSV', 'استيراد وتصدير CSV'),
                $L('Duplicate registrations flagged', 'تنبيه إلى التسجيلات المكررة'),
            )),
        ),
    ),
    array(
        'id' => 'door',
        'kicker' => $L('At the door', 'على الباب'),
        'title' => $L('Thousands through the door, no queue', 'آلاف الحضور على الباب دون طوابير'),
        'items' => array(
            array('scan', $L('QR scanner on any phone', 'ماسح QR على أي هاتف'), array(
                $L('Opens in the browser, nothing to install', 'يعمل من المتصفح دون أي تثبيت'),
                $L('Look people up by ticket code or phone number', 'البحث برمز التذكرة أو رقم الهاتف'),
                $L('Clear colour for every result: in, already in, refused', 'لون واضح لكل نتيجة: تم الدخول، سبق الدخول، مرفوض'),
                $L('Check-out and time inside, or a warning on a second scan — per event', 'تسجيل الخروج ومدة التواجد، أو تنبيه عند المسح الثاني — حسب كل فعالية'),
            )),
            array('shield', $L('Scanner team', 'فريق المسح'), array(
                $L('Staff accounts that only see the scanner', 'حسابات للفريق لا ترى سوى الماسح'),
                $L('Access limited to chosen events or sessions', 'صلاحيات مقصورة على فعاليات أو جلسات محددة'),
                $L('Session attendance for CME', 'تسجيل حضور الجلسات لاحتساب ساعات CME'),
            )),
            array('badge', $L('Badges', 'البطاقات التعريفية'), array(
                $L('Print-ready PDF badges for attendees, speakers, organisers and exhibitors', 'بطاقات PDF جاهزة للطباعة للمشاركين والمتحدثين والمنظمين والعارضين'),
                $L('QR on every attendee and exhibitor badge', 'رمز QR على بطاقة كل مشارك وعارض'),
            )),
        ),
    ),
    array(
        'id' => 'after',
        'kicker' => $L('After the event', 'بعد الفعالية'),
        'title' => $L('Certificates and numbers you can trust', 'شهادات وأرقام يمكنك الاعتماد عليها'),
        'items' => array(
            array('award', $L('Certificates', 'الشهادات'), array(
                $L('Drag-and-drop certificate designer on your own artwork', 'مصمم شهادات بالسحب والإفلات على تصميمك'),
                $L('Issue to everyone who attended in one step', 'إصدار الشهادات لكل الحضور بخطوة واحدة'),
                $L('Each certificate has a QR code that opens a public verification page', 'على كل شهادة رمز QR يفتح صفحة تحقق عامة'),
                $L('Attendees download them from their account', 'يحمّلها المشاركون من حساباتهم'),
            )),
            array('chart', $L('Reports', 'التقارير'), array(
                $L('Real check-in rate: people who came out of people registered', 'نسبة حضور حقيقية: من حضر مقارنةً بمن سجّل'),
                $L('Registrations by day and arrivals by hour', 'التسجيلات حسب اليوم والوصول حسب الساعة'),
                $L('Every event side by side, CSV export', 'مقارنة كل الفعاليات وتصدير CSV'),
            )),
        ),
    ),
    array(
        'id' => 'partners',
        'kicker' => $L('Partners and exhibitors', 'الشركاء والعارضون'),
        'title' => $L('Everyone who makes the congress happen', 'كل من يصنع المؤتمر'),
        'items' => array(
            array('mic', $L('Speakers, sponsors and partners', 'المتحدثين والرعاة والشركاء'), array(
                $L('Profiles shown on the event page', 'تظهر ملفاتهم في صفحة الفعالية'),
                $L('Sponsor tiers with their own wall', 'مستويات رعاية ولها صفحة خاصة'),
            )),
            array('store', $L('Exhibition and booths', 'المعرض والبوثات'), array(
                $L('Booth types, prices and an interactive floor plan', 'أنواع البوثات وأسعارها وخريطة تفاعلية للمعرض'),
                $L('Bookings from request to check-in', 'متابعة الحجز من الطلب حتى الدخول'),
                $L('Exhibitor companies with staff badges', 'الشركات العارضة وبطاقات فرقها'),
            )),
        ),
    ),
    array(
        'id' => 'people',
        'kicker' => $L('Your attendees', 'المشاركون'),
        'title' => $L('One account for every event', 'حساب واحد لكل الفعاليات'),
        'items' => array(
            array('user', $L('Attendee account', 'حساب المشارك'), array(
                $L('Tickets, e-badge and certificates in one place', 'التذاكر والبطاقة الإلكترونية والشهادات في مكان واحد'),
                $L('Syndicate number and details kept between congresses', 'رقم النقابة والبيانات محفوظة من مؤتمر لآخر'),
            )),
            array('chat', $L('Talking to attendees', 'التواصل مع المشاركين'), array(
                $L('Live chat from the website to your team\'s inbox', 'محادثة مباشرة من الموقع إلى صندوق فريقك'),
                $L('Contact-form messages in one inbox, answered by email or WhatsApp', 'رسائل التواصل في صندوق واحد والرد بالبريد أو واتساب'),
                $L('Confirmation email with the ticket', 'بريد تأكيد مرفق به التذكرة'),
            )),
        ),
    ),
);
?>
<!doctype html>
<html lang="<?php echo esc_attr($lang); ?>" dir="<?php echo $ar ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?php echo esc_html($L('Event platform', 'منصة الفعاليات') . ' · ' . $platform_name); ?></title>
    <meta name="description" content="<?php echo esc_attr($L('Registration, door scanning, badges, certificates and reports for medical congresses.', 'التسجيل ومسح الدخول والبطاقات التعريفية والشهادات والتقارير للمؤتمرات الطبية.')); ?>">
    <link rel="alternate" hreflang="en" href="<?php echo esc_url(add_query_arg('lang', 'en', $page_url)); ?>">
    <link rel="alternate" hreflang="ar" href="<?php echo esc_url(add_query_arg('lang', 'ar', $page_url)); ?>">
    <link rel="icon" href="<?php echo esc_url(get_template_directory_uri() . '/assets/admin-dashboard/images/favicon.ico'); ?>" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo esc_url(sc_dashboard_asset('frontend/css/wisdom/tokens.css')); ?>">
    <link rel="stylesheet" href="<?php echo esc_url(sc_dashboard_asset('frontend/css/wisdom/components.css')); ?>">
    <link rel="stylesheet" href="<?php echo esc_url(sc_dashboard_asset('frontend/css/wisdom/features.css')); ?>">
</head>
<body class="w-fx">

<header class="w-fx__bar">
    <a class="w-fx__brand" href="<?php echo esc_url(home_url('/')); ?>">
        <?php if ($logo): ?>
            <img src="<?php echo esc_url($logo[0]); ?>" alt="<?php echo esc_attr($platform_name); ?>">
        <?php else: ?>
            <strong><?php echo esc_html($platform_name); ?></strong>
        <?php endif; ?>
    </a>
    <nav class="w-fx__nav" aria-label="<?php echo esc_attr($L('Sections', 'الأقسام')); ?>">
        <?php foreach ($stages as $s): ?>
            <a href="#<?php echo esc_attr($s['id']); ?>"><?php echo esc_html($s['kicker']); ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="w-fx__baractions">
        <a class="w-fx__lang" href="<?php echo esc_url(add_query_arg('lang', $ar ? 'en' : 'ar', $page_url)); ?>" hreflang="<?php echo $ar ? 'en' : 'ar'; ?>" lang="<?php echo $ar ? 'en' : 'ar'; ?>"><?php echo $ar ? 'English' : 'العربية'; ?></a>
        <a class="w-btn w-btn--sm" href="#contact"><?php echo esc_html($L('Contact us', 'تواصل معنا')); ?></a>
    </div>
</header>

<main>
    <section class="w-fx__hero">
        <div class="w-fx__wrap">
            <p class="w-fx__kicker"><?php echo esc_html($L('The platform behind Wisdom\'s congresses', 'المنصة التي تدير مؤتمرات Wisdom')); ?></p>
            <h1 class="w-fx__title"><?php echo esc_html($L('Run a medical congress from the first registration to the last certificate.', 'أدِر مؤتمرك الطبي من أول تسجيل حتى آخر شهادة.')); ?></h1>
            <p class="w-fx__lede"><?php echo esc_html($L('Registration, workshops, door scanning, badges, exhibitors, certificates and reports — in one system your team already knows how to use.', 'التسجيل والورش ومسح الدخول والبطاقات التعريفية والعارضون والشهادات والتقارير — في نظام واحد يسهل على فريقك استخدامه.')); ?></p>
            <div class="w-fx__cta">
                <a class="w-btn w-btn--lg" href="<?php echo esc_url($primary_contact); ?>" <?php echo $wa ? 'target="_blank" rel="noopener"' : ''; ?>><?php echo esc_html($L('Talk to us', 'تواصل معنا')); ?></a>
                <a class="w-btn w-btn--lg w-fx__ghost" href="#before"><?php echo esc_html($L('See what it does', 'اكتشف المميزات')); ?></a>
            </div>
            <?php if ($stats): ?>
                <dl class="w-fx__stats">
                    <?php foreach ($stats as $st): ?>
                        <div><dt><?php echo esc_html($st[1]); ?></dt><dd><?php echo esc_html($st[0]); ?></dd></div>
                    <?php endforeach; ?>
                </dl>
            <?php endif; ?>
        </div>
    </section>

    <?php foreach ($stages as $i => $s): ?>
        <section class="w-fx__stage<?php echo $i % 2 ? ' w-fx__stage--alt' : ''; ?>" id="<?php echo esc_attr($s['id']); ?>" aria-labelledby="<?php echo esc_attr($s['id']); ?>-title">
            <div class="w-fx__wrap">
                <div class="w-fx__stagehead">
                    <span class="w-fx__step"><?php echo esc_html(number_format_i18n($i + 1)); ?></span>
                    <div>
                        <p class="w-fx__kicker"><?php echo esc_html($s['kicker']); ?></p>
                        <h2 class="w-fx__h2" id="<?php echo esc_attr($s['id']); ?>-title"><?php echo esc_html($s['title']); ?></h2>
                    </div>
                </div>
                <div class="w-fx__grid">
                    <?php foreach ($s['items'] as $item): ?>
                        <article class="w-fx__card">
                            <span class="w-fx__icon"><?php echo $icon($item[0]); // Static SVG markup. ?></span>
                            <h3 class="w-fx__h3"><?php echo esc_html($item[1]); ?></h3>
                            <ul class="w-fx__list">
                                <?php foreach ($item[2] as $point): ?>
                                    <li><?php echo esc_html($point); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endforeach; ?>

    <section class="w-fx__contact" id="contact" aria-labelledby="contact-title">
        <div class="w-fx__wrap">
            <h2 class="w-fx__h2" id="contact-title"><?php echo esc_html($L('Planning a congress?', 'تُحضّر لمؤتمر؟')); ?></h2>
            <p class="w-fx__lede"><?php echo esc_html($L('Tell us the date and how many people you expect. We will walk you through the platform on your own event.', 'أخبرنا بموعد فعاليتك وعدد الحضور المتوقع، وسنعرض لك المنصة على فعاليتك نفسها.')); ?></p>
            <div class="w-fx__cta">
                <?php if ($wa): ?>
                    <a class="w-btn w-btn--lg" href="<?php echo esc_url('https://wa.me/' . $wa . '?text=' . $wa_text); ?>" target="_blank" rel="noopener"><?php echo esc_html($L('WhatsApp', 'واتساب')); ?></a>
                <?php endif; ?>
                <?php if ($email): ?>
                    <a class="w-btn w-btn--lg <?php echo $wa ? 'w-fx__ghost' : ''; ?>" href="<?php echo esc_url('mailto:' . $email); ?>" dir="ltr"><?php echo esc_html($email); ?></a>
                <?php endif; ?>
                <?php if ($phone): ?>
                    <a class="w-btn w-btn--lg w-fx__ghost" href="<?php echo esc_url('tel:' . preg_replace('/[^0-9+]/', '', $phone)); ?>" dir="ltr"><?php echo esc_html($phone); ?></a>
                <?php endif; ?>
                <?php if (!$wa && !$email && !$phone): ?>
                    <a class="w-btn w-btn--lg" href="<?php echo esc_url(home_url('/contact/')); ?>"><?php echo esc_html($L('Contact page', 'صفحة التواصل')); ?></a>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<footer class="w-fx__foot">
    <div class="w-fx__wrap">
        <span>© <?php echo esc_html(wp_date('Y') . ' ' . $platform_name); ?></span>
        <a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html($L('Website', 'الموقع')); ?></a>
    </div>
</footer>

</body>
</html>
