<?php
/**
 * Module manager — feature modules as cards with a switch. Administrators only.
 * Toggle: sc_module_toggle (inc/admin-dashboard/modules-dashboard.php).
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('administrator')) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

global $load_wd_list, $load_wd_form, $load_wd_overview;
$load_wd_list = true;
$load_wd_form = true;
$load_wd_overview = true;

$catalog = sc_module_catalog();
$info = array(
    'events'       => array(sc_t('modules.events', 'Events'), sc_t('modules.events_desc', 'Events, categories, organizers and the public event pages.')),
    'tickets'      => array(sc_t('modules.tickets', 'Tickets'), sc_t('modules.tickets_desc', 'Ticket types, prices and sales limits.')),
    'attendees'    => array(sc_t('modules.attendees', 'Attendees'), sc_t('modules.attendees_desc', 'Registrations, the scanner, check-in, customers and badges.')),
    'speakers'     => array(sc_t('modules.speakers', 'Speakers'), sc_t('modules.speakers_desc', 'Speaker profiles and the programme.')),
    'companies'    => array(sc_t('modules.companies', 'Companies / B2B'), sc_t('modules.companies_desc', 'Exhibitor registrations with scannable company badges.')),
    'booths'       => array(sc_t('modules.booths', 'Booths'), sc_t('modules.booths_desc', 'Booth types, booths, bookings and the floor plan.')),
    'certificates' => array(sc_t('modules.certificates', 'Certificates'), sc_t('modules.certificates_desc', 'Certificate templates, issuing and public verification.')),
    'coupons'      => array(sc_t('modules.coupons', 'Coupons'), sc_t('modules.coupons_desc', 'Discount codes for tickets.')),
    'payments'     => array(sc_t('modules.payments', 'Payments'), sc_t('modules.payments_desc', 'Online payment gateways for paid tickets.')),
    'chat'         => array(sc_t('modules.chat', 'Chat'), sc_t('modules.chat_desc', 'The live chat window on the website and its inbox.')),
    'reports'      => array(sc_t('modules.reports', 'Reports'), sc_t('modules.reports_desc', 'Sales and attendance reports and exports.')),
);
$label = function ($id) use ($info) {
    return $info[$id][0] ?? ucfirst(str_replace(array('-', '_'), ' ', $id));
};
$order = array_merge(sc_locked_modules(), array_keys($info));
uksort($catalog, function ($a, $b) use ($order) {
    $ia = array_search($a, $order, true);
    $ib = array_search($b, $order, true);
    return ($ia === false ? 99 : $ia) <=> ($ib === false ? 99 : $ib);
});
$on = count(array_filter($catalog, function ($m) { return $m['enabled']; }));
$js = function ($value) {
    return wp_json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
};

get_template_part('template-parts/dashboard/components/dashboard', 'header');
get_template_part('template-parts/dashboard/components/dashboard', 'sidebar');
?>

<div id="main-content">
<div class="container-fluid">

    <div class="w-page-head">
        <div>
            <h1><?php echo esc_html(sc_t('dashboard_pages.module_manager', 'Modules')); ?><span class="w-page-head__count"><?php echo esc_html($on . ' / ' . count($catalog)); ?></span></h1>
            <p class="w-page-head__sub"><?php echo esc_html(sc_t('modules.sub', 'Turn features you do not use off to hide their pages. Turning a module off keeps its data; turning it back on brings everything back.')); ?></p>
        </div>
    </div>

    <div class="w-catgrid" id="modules">
        <?php foreach ($catalog as $id => $m): ?>
            <article class="w-cat w-mod<?php echo $m['enabled'] ? ' is-on' : ''; ?>" data-module="<?php echo esc_attr($id); ?>">
                <div class="w-mod__top">
                    <div class="w-cat__body">
                        <h2 class="w-cat__name"><?php echo esc_html($label($id)); ?>
                            <?php if ($m['locked']): ?><span class="w-tag"><?php echo esc_html(sc_t('modules.always_on', 'Always on')); ?></span><?php endif; ?>
                        </h2>
                        <p class="w-sub"><?php echo esc_html($info[$id][1] ?? ''); ?></p>
                    </div>
                    <label class="w-switch w-mod__switch">
                        <input type="checkbox" data-toggle="<?php echo esc_attr($id); ?>" <?php checked($m['enabled']); ?> <?php disabled($m['locked'] && $m['enabled']); ?> aria-label="<?php echo esc_attr(sprintf(sc_t('modules.toggle', 'Turn %s on or off'), $label($id))); ?>">
                        <span class="w-switch__track" aria-hidden="true"></span>
                    </label>
                </div>
                <ul class="w-issue__rules">
                    <?php if ($m['pages']): ?><li><?php echo esc_html(sprintf(_n('%s dashboard page', '%s dashboard pages', $m['pages'], 'sc_events'), number_format_i18n($m['pages']))); ?></li><?php endif; ?>
                    <?php if ($m['needs']): ?><li><?php echo esc_html(sprintf(sc_t('modules.needs', 'Needs %s'), implode(', ', array_map($label, $m['needs'])))); ?></li><?php endif; ?>
                    <?php if ($m['used_by']): ?><li><?php echo esc_html(sprintf(sc_t('modules.used_by', 'Needed by %s'), implode(', ', array_map($label, $m['used_by'])))); ?></li><?php endif; ?>
                </ul>
            </article>
        <?php endforeach; ?>
    </div>

</div>
</div>

<script>
jQuery(function ($) {
    'use strict';
    var names = <?php echo $js(array_combine(array_keys($catalog), array_map($label, array_keys($catalog)))); ?>;
    var L = <?php echo $js(array(
        'offAsk'  => sc_t('modules.off_ask', 'Turn off %s? Its pages disappear from the dashboard and the website. The data stays and comes back when you turn it on again.'),
        'offBtn'  => sc_t('modules.turn_off', 'Turn off'),
        'failed'  => sc_t('errors.something_wrong', 'Something went wrong. Please try again.'),
    )); ?>;

    $('#modules').on('change', '[data-toggle]', function () {
        var box = this, id = box.getAttribute('data-toggle'), enable = box.checked;
        var go = function () {
            box.disabled = true;
            $.post(scDashboard.ajaxurl, { action: 'sc_module_toggle', nonce: scDashboard.nonce, module: id, enable: enable ? 1 : 0 })
                .done(function (res) {
                    if (!res.success) { box.checked = !enable; box.disabled = false; showError(res.data && res.data.message || L.failed); return; }
                    if (window.toastr) { toastr.success(res.data.message); }
                    // The sidebar and page guards read module state on load.
                    setTimeout(function () { window.location.reload(); }, 700);
                })
                .fail(function () { box.checked = !enable; box.disabled = false; showError(L.failed); });
        };
        if (enable) { go(); return; }
        Swal.fire({ text: L.offAsk.replace('%s', names[id] || id), icon: 'warning', showCancelButton: true, confirmButtonText: L.offBtn, confirmButtonColor: '#b42318' })
            .then(function (r) { if (r.isConfirmed) { go(); } else { box.checked = true; } });
    });
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
