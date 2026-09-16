<?php
/**
 * Module Helper Functions
 *
 * مجموعة دوال مساعدة للتحقق من حالة الموديولات
 * وتطبيق Graceful Degradation عبر النظام
 *
 * @package sc_events
 * @since 2.3.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * التحقق من تفعيل موديول مع backward compatibility
 *
 * @param string $module_id معرف الموديول
 * @return bool
 */
function sc_module_active($module_id) {
    // إذا لم تكن الدالة الأصلية موجودة، نفترض أن الموديول مفعل (backward compatibility)
    if (!function_exists('sc_is_module_enabled')) {
        return true;
    }

    return sc_is_module_enabled($module_id);
}

/**
 * منع الوصول للصفحة إذا كان الموديول معطل
 * يستخدم في بداية صفحات الداشبورد
 *
 * @param string $module_id معرف الموديول
 * @param string $redirect_to الصفحة للتوجيه إليها (افتراضي: home)
 * @return void
 */
function sc_require_module($module_id, $redirect_to = 'home') {
    if (!sc_module_active($module_id)) {
        wp_redirect(home_url('/event-manager-dashboard/' . $redirect_to));
        exit;
    }
}

/**
 * رفض طلب AJAX إذا كان الموديول معطل
 * يستخدم في بداية كل AJAX handler
 *
 * @param string $module_id معرف الموديول
 * @return void
 */
function sc_ajax_require_module($module_id) {
    if (!sc_module_active($module_id)) {
        wp_send_json_error([
            'message' => __('This feature is not available.', 'sc_events'),
            'message_ar' => 'هذه الميزة غير متاحة.',
            'code' => 'module_disabled'
        ]);
    }
}

/**
 * الحصول على قائمة الموديولات المفعلة
 *
 * @return array
 */
function sc_get_active_modules() {
    $all_modules = [
        'events',
        'speakers',
        'attendees',
        'companies',
        'certificates',
        'payments',
        'chat',
        'referrals',
        'booths',
        'invoices',
        'sms',
        'coupons',
        'sponsors',
        'partners'
    ];

    $active = [];
    foreach ($all_modules as $module_id) {
        if (sc_module_active($module_id)) {
            $active[] = $module_id;
        }
    }

    return $active;
}

/**
 * الحصول على حالة جميع الموديولات كـ array
 * مفيد لتمرير البيانات لـ JavaScript
 *
 * @return array
 */
function sc_get_modules_status_array() {
    return [
        'events' => sc_module_active('events'),
        'speakers' => sc_module_active('speakers'),
        'attendees' => sc_module_active('attendees'),
        'companies' => sc_module_active('companies'),
        'certificates' => sc_module_active('certificates'),
        'payments' => sc_module_active('payments'),
        'chat' => sc_module_active('chat'),
        'referrals' => sc_module_active('referrals'),
        'booths' => sc_module_active('booths'),
        'invoices' => sc_module_active('invoices'),
        'sms' => sc_module_active('sms'),
        'coupons' => sc_module_active('coupons'),
        'sponsors' => sc_module_active('sponsors'),
        'partners' => sc_module_active('partners'),
    ];
}

/**
 * طباعة حالة الموديولات كـ JavaScript object
 * يستخدم في dashboard-footer.php
 *
 * @return void
 */
function sc_print_modules_js_object() {
    $modules = sc_get_modules_status_array();

    echo '<script>' . PHP_EOL;
    echo 'const scModules = {' . PHP_EOL;

    $items = [];
    foreach ($modules as $module_id => $is_active) {
        $js_key = str_replace('-', '_', $module_id); // Convert saudi-integration to saudi_integration
        $items[] = "    {$js_key}: " . ($is_active ? 'true' : 'false');
    }

    echo implode(',' . PHP_EOL, $items) . PHP_EOL;
    echo '};' . PHP_EOL;
    echo '</script>' . PHP_EOL;
}

/**
 * التحقق من أن الموديول أساسي (لا يمكن تعطيله)
 *
 * @param string $module_id معرف الموديول
 * @return bool
 */
function sc_is_core_module($module_id) {
    // Core modules that cannot be disabled
    // Note: events, attendees, speakers are now optional modules
    $core_modules = [
        'tickets'  // Tickets is always needed as it's tied to attendees
    ];

    return in_array($module_id, $core_modules);
}

/**
 * الحصول على الموديولات التي يعتمد عليها موديول معين
 * Dependencies: الموديولات التي يحتاجها هذا الموديول للعمل
 *
 * @param string $module_id معرف الموديول
 * @return array
 */
function sc_get_module_dependencies($module_id) {
    $dependencies = [
        // Certificates تحتاج Attendees
        'certificates' => ['attendees'],
    ];

    return isset($dependencies[$module_id]) ? $dependencies[$module_id] : [];
}

/**
 * الحصول على الموديولات التي تعتمد على موديول معين
 * Dependents: الموديولات التي ستتأثر إذا تم تعطيل هذا الموديول
 *
 * @param string $module_id معرف الموديول
 * @return array
 */
function sc_get_module_dependents($module_id) {
    $dependents = [
        // إذا تم تعطيل Events، ستتأثر:
        'events' => ['speakers'],

        // إذا تم تعطيل Attendees، ستتأثر:
        'attendees' => ['certificates'],
    ];

    return isset($dependents[$module_id]) ? $dependents[$module_id] : [];
}

/**
 * التحقق من أن موديول يمكن تعطيله بأمان
 * (أي أن الموديولات التي تعتمد عليه معطلة أيضاً)
 *
 * @param string $module_id معرف الموديول
 * @return array ['can_disable' => bool, 'blocking_modules' => array]
 */
function sc_can_safely_disable_module($module_id) {
    $dependents = sc_get_module_dependents($module_id);
    $blocking = [];

    foreach ($dependents as $dependent) {
        if (sc_module_active($dependent)) {
            $blocking[] = $dependent;
        }
    }

    return [
        'can_disable' => empty($blocking),
        'blocking_modules' => $blocking
    ];
}

/**
 * الحصول على أجزاء موديول يجب إخفاؤها في صفحة أخرى
 * مثال: إخفاء قسم Speakers في صفحة Event إذا كان Speakers معطل
 *
 * @param string $page_context السياق (event-form, session-form, etc.)
 * @return array الأجزاء التي يجب إخفاؤها
 */
function sc_get_hidden_form_sections($page_context) {
    $hidden = [];

    switch ($page_context) {
        case 'event-form':
            // في فورم الحدث
            if (!sc_module_active('speakers')) {
                $hidden[] = 'speakers-section';
            }
            if (!sc_module_active('certificates')) {
                $hidden[] = 'certificate-section';
            }
            break;

        case 'attendee-form':
            // في فورم الحضور
            if (!sc_module_active('companies')) {
                $hidden[] = 'company-section';
            }
            break;
    }

    return $hidden;
}

/**
 * التحقق إذا كان يجب إخفاء قسم معين في الفورم
 *
 * @param string $page_context السياق
 * @param string $section_id معرف القسم
 * @return bool
 */
function sc_should_hide_section($page_context, $section_id) {
    $hidden = sc_get_hidden_form_sections($page_context);
    return in_array($section_id, $hidden);
}

/**
 * الحصول على mapping الصفحات والموديولات
 * يستخدم في dashboard-init.php لمنع الوصول للصفحات المعطلة
 *
 * @return array
 */
function sc_get_page_module_requirements() {
    return [
        // Events Module
        'events' => 'events',
        'event-create' => 'events',
        'event-edit' => 'events',
        'event-view' => 'events',
        'categories' => 'events',
        'organizers' => 'events',

        // Speakers Module
        'speakers' => 'speakers',
        'speaker-create' => 'speakers',
        'speaker-edit' => 'speakers',

        // Sessions Module
        'sessions' => 'sessions',
        'session-create' => 'sessions',
        'session-edit' => 'sessions',
        'session-attendees' => 'sessions',

        // Attendees Module
        'attendees' => 'attendees',
        'attendee-add' => 'attendees',
        'attendee-edit' => 'attendees',
        'scanner' => 'attendees',
        'door-board' => 'attendees',
        'customers' => 'attendees',
        'scanners' => 'attendees',
        'scanner-create' => 'attendees',
        'scanner-edit' => 'attendees',
        'badges' => 'attendees',

        // Companies Module
        'company-attendees' => 'companies',
        'company-attendee-add' => 'companies',
        'company-attendee-edit' => 'companies',
        'company-scanner' => 'companies',

        // Certificates Module
        'certificate-templates' => 'certificates',
        'certificate-template-create' => 'certificates',
        'certificate-template-edit' => 'certificates',
        'certificate-visual-builder' => 'certificates',
        'certificates' => 'certificates',
        'certificate-issue' => 'certificates',

        // Chat Module
        'chat' => 'chat',

        // Booths Module
        'booths' => 'booths',
        'booth-create' => 'booths',
        'booth-edit' => 'booths',
        'booth-types' => 'booths',
        'booth-type-create' => 'booths',
        'booth-type-edit' => 'booths',
        'booth-bookings' => 'booths',
        'booth-booking-create' => 'booths',
        'booth-booking-edit' => 'booths',
        'booth-floor-plan' => 'booths',

        // Coupons Module
        'coupons' => 'coupons',
        'coupon-create' => 'coupons',
        'coupon-edit' => 'coupons',

        // Sponsors Module
        'sponsors' => 'sponsors',
        'sponsor-create' => 'sponsors',
        'sponsor-edit' => 'sponsors',

        // Partners Module
        'partners' => 'partners',
        'partner-create' => 'partners',
        'partner-edit' => 'partners',
    ];
}

/**
 * التحقق من أن الصفحة تتطلب موديول معين
 *
 * @param string $page اسم الصفحة
 * @return string|null اسم الموديول أو null
 */
function sc_get_required_module_for_page($page) {
    $requirements = sc_get_page_module_requirements();
    return isset($requirements[$page]) ? $requirements[$page] : null;
}

/**
 * التحقق من إمكانية الوصول للصفحة
 *
 * @param string $page اسم الصفحة
 * @return bool
 */
function sc_can_access_page($page) {
    $required_module = sc_get_required_module_for_page($page);

    // إذا لم تتطلب الصفحة موديول، يمكن الوصول إليها
    if ($required_module === null) {
        return true;
    }

    return sc_module_active($required_module);
}
