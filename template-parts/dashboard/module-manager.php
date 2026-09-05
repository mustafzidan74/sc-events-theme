<?php
/**
 * Module Manager Dashboard Page
 *
 * Allows administrators to enable/disable system modules
 *
 * @package sc_events
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check permissions
if (!current_user_can('administrator')) {
    wp_die(__('You do not have permission to access this page.', 'flavor-flavor'));
}

// RTL detection
$is_rtl = function_exists('sc_is_rtl') ? sc_is_rtl() : is_rtl();

// Translations
$t = array(
    'page_title' => $is_rtl ? 'مدير الوحدات' : 'Module Manager',
    'dashboard' => $is_rtl ? 'لوحة التحكم' : 'Dashboard',
    'settings' => $is_rtl ? 'الإعدادات' : 'Settings',
    'modules' => $is_rtl ? 'الوحدات' : 'Modules',
    'description' => $is_rtl ? 'قم بتفعيل أو تعطيل وحدات النظام حسب احتياجاتك' : 'Enable or disable system modules according to your needs',
    'enabled' => $is_rtl ? 'مفعّل' : 'Enabled',
    'disabled' => $is_rtl ? 'معطّل' : 'Disabled',
    'enable' => $is_rtl ? 'تفعيل' : 'Enable',
    'disable' => $is_rtl ? 'تعطيل' : 'Disable',
    'status' => $is_rtl ? 'الحالة' : 'Status',
    'actions' => $is_rtl ? 'الإجراءات' : 'Actions',
    'core_modules' => $is_rtl ? 'الوحدات الأساسية' : 'Core Modules',
    'optional_modules' => $is_rtl ? 'الوحدات الاختيارية' : 'Optional Modules',
    'module_name' => $is_rtl ? 'اسم الوحدة' : 'Module Name',
    'save_changes' => $is_rtl ? 'حفظ التغييرات' : 'Save Changes',
    'saving' => $is_rtl ? 'جاري الحفظ...' : 'Saving...',
    'changes_saved' => $is_rtl ? 'تم حفظ التغييرات بنجاح' : 'Changes saved successfully',
    'error_saving' => $is_rtl ? 'حدث خطأ أثناء الحفظ' : 'Error saving changes',
    'restart_note' => $is_rtl ? 'ملاحظة: قد تحتاج لتحديث الصفحة لرؤية التغييرات' : 'Note: You may need to refresh the page to see changes',
    'reloading' => $is_rtl ? 'جاري إعادة تحميل الصفحة...' : 'Reloading page...',
    'total_modules' => $is_rtl ? 'إجمالي الوحدات' : 'Total Modules',
    'enabled_modules' => $is_rtl ? 'الوحدات المفعّلة' : 'Enabled Modules',
    'disabled_modules' => $is_rtl ? 'الوحدات المعطّلة' : 'Disabled Modules',
);

// Module descriptions
$module_info = array(
    // Core Modules (can now be disabled)
    'events' => array(
        'name' => $is_rtl ? 'إدارة الفعاليات' : 'Events Management',
        'description' => $is_rtl ? 'إنشاء وإدارة الفعاليات والتصنيفات والمنظمين' : 'Create and manage events, categories, and organizers',
        'icon' => 'fa-calendar',
        'category' => 'core',
    ),
    'speakers' => array(
        'name' => $is_rtl ? 'المتحدثين' : 'Speakers',
        'description' => $is_rtl ? 'إدارة المتحدثين والضيوف' : 'Manage speakers and guests',
        'icon' => 'fa-microphone',
        'category' => 'optional',
    ),
    'attendees' => array(
        'name' => $is_rtl ? 'الحضور' : 'Attendees',
        'description' => $is_rtl ? 'إدارة الحضور وتسجيل الدخول والماسح الضوئي' : 'Manage attendees, check-in and scanner',
        'icon' => 'fa-users',
        'category' => 'core',
    ),
    'companies' => array(
        'name' => $is_rtl ? 'الشركات B2B' : 'Companies / B2B',
        'description' => $is_rtl ? 'نظام تسجيل الشركات والحضور المجمع' : 'Company registration and bulk attendee system',
        'icon' => 'fa-building-o',
        'category' => 'optional',
    ),
    'certificates' => array(
        'name' => $is_rtl ? 'الشهادات' : 'Certificates',
        'description' => $is_rtl ? 'إصدار وإدارة شهادات الحضور' : 'Issue and manage attendance certificates',
        'icon' => 'fa-certificate',
        'category' => 'optional',
    ),
    'payments' => array(
        'name' => $is_rtl ? 'بوابات الدفع' : 'Payment Gateways',
        'description' => $is_rtl ? 'إدارة طرق الدفع الإلكتروني' : 'Manage electronic payment methods',
        'icon' => 'fa-credit-card',
        'category' => 'optional',
    ),
    'chat' => array(
        'name' => $is_rtl ? 'نظام الدردشة' : 'Chat System',
        'description' => $is_rtl ? 'التواصل المباشر مع الحضور' : 'Direct communication with attendees',
        'icon' => 'fa-comments',
        'category' => 'optional',
    ),
    'referrals' => array(
        'name' => $is_rtl ? 'نظام الإحالات' : 'Referral System',
        'description' => $is_rtl ? 'نظام النقاط والمكافآت للإحالات' : 'Points and rewards system for referrals',
        'icon' => 'fa-share-alt',
        'category' => 'optional',
    ),
    'booths' => array(
        'name' => $is_rtl ? 'إدارة الأجنحة' : 'Booth Management',
        'description' => $is_rtl ? 'إدارة المعارض والأجنحة' : 'Manage exhibitions and booths',
        'icon' => 'fa-th-large',
        'category' => 'optional',
    ),
    'invoices' => array(
        'name' => $is_rtl ? 'الفوترة الإلكترونية' : 'E-Invoicing',
        'description' => $is_rtl ? 'فواتير متوافقة مع ZATCA' : 'ZATCA-compliant invoices',
        'icon' => 'fa-file-text-o',
        'category' => 'optional',
    ),
    'sms' => array(
        'name' => $is_rtl ? 'رسائل SMS' : 'SMS Notifications',
        'description' => $is_rtl ? 'إشعارات عبر الرسائل النصية' : 'Text message notifications',
        'icon' => 'fa-mobile',
        'category' => 'optional',
    ),
    'coupons' => array(
        'name' => $is_rtl ? 'الكوبونات والخصومات' : 'Coupons & Discounts',
        'description' => $is_rtl ? 'إنشاء وإدارة كوبونات الخصم' : 'Create and manage discount coupons',
        'icon' => 'fa-ticket',
        'category' => 'optional',
    ),
    'reports' => array(
        'name' => $is_rtl ? 'التقارير' : 'Reports',
        'description' => $is_rtl ? 'تقارير شاملة عن الفعاليات والحضور والإيرادات' : 'Comprehensive reports on events, attendance, and revenue',
        'icon' => 'fa-bar-chart',
        'category' => 'optional',
    ),
    'tickets' => array(
        'name' => $is_rtl ? 'التذاكر' : 'Tickets',
        'description' => $is_rtl ? 'إدارة أنواع وأسعار التذاكر' : 'Manage ticket types and pricing',
        'icon' => 'fa-ticket',
        'category' => 'core',
    ),
);

// Get all modules status
$all_modules = array();
if (function_exists('sc_modules')) {
    $all_modules = sc_modules()->get_all_modules_status();
}

// Merge with module info
foreach ($all_modules as $id => $module) {
    if (isset($module_info[$id])) {
        $all_modules[$id] = array_merge($module, $module_info[$id]);
    } else {
        // Default info for unknown modules
        $all_modules[$id]['icon'] = 'fa-puzzle-piece';
        $all_modules[$id]['category'] = 'optional';
        if (empty($all_modules[$id]['description'])) {
            $all_modules[$id]['description'] = '';
        }
    }
}

// Get module dependencies (what modules depend on this module)
$module_dependents = array();
if (function_exists('sc_get_module_dependents')) {
    foreach (array_keys($all_modules) as $module_id) {
        $dependents = sc_get_module_dependents($module_id);
        if (!empty($dependents)) {
            // Filter to only include enabled dependents
            $active_dependents = array();
            foreach ($dependents as $dep) {
                if (isset($all_modules[$dep]) && $all_modules[$dep]['is_enabled']) {
                    $active_dependents[] = $dep;
                }
            }
            if (!empty($active_dependents)) {
                $module_dependents[$module_id] = $active_dependents;
            }
        }
    }
}

// Count stats
$total_modules = count($all_modules);
$enabled_count = 0;
$disabled_count = 0;
foreach ($all_modules as $module) {
    if ($module['is_enabled']) {
        $enabled_count++;
    } else {
        $disabled_count++;
    }
}

$page_title = $t['page_title'];
get_template_part('template-parts/dashboard/components/dashboard', 'header');
?>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'sidebar'); ?>

<!-- main page content body part -->
<div id="main-content">
    <div class="container-fluid">
        <div class="block-header">
            <div class="row">
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <h2><?php echo esc_html($t['page_title']); ?></h2>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/home'); ?>"><i class="fa fa-dashboard"></i></a></li>
                        <li class="breadcrumb-item"><a href="<?php echo home_url('/event-manager-dashboard/settings'); ?>"><?php echo esc_html($t['settings']); ?></a></li>
                        <li class="breadcrumb-item active"><?php echo esc_html($t['modules']); ?></li>
                    </ul>
                </div>
                <div class="col-lg-6 col-md-6 col-sm-12">
                    <div class="d-flex flex-row-reverse">
                        <div class="page_action">
                            <button type="button" class="btn btn-primary" id="save-modules-btn">
                                <i class="fa fa-save"></i> <?php echo esc_html($t['save_changes']); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alert Messages Container -->
        <div class="row">
            <div class="col-12">
                <div id="module-alerts"></div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row clearfix">
            <div class="col-lg-4 col-md-4 col-sm-12">
                <div class="card">
                    <div class="body d-flex align-items-center">
                        <div class="icon-in-bg bg-indigo text-white rounded-circle"><i class="fa fa-cubes"></i></div>
                        <div class="<?php echo $is_rtl ? 'mr-4' : 'ml-4'; ?>">
                            <span class="text-muted"><?php echo esc_html($t['total_modules']); ?></span>
                            <h4 class="mb-0 font-weight-bold"><?php echo $total_modules; ?></h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-4 col-sm-12">
                <div class="card">
                    <div class="body d-flex align-items-center">
                        <div class="icon-in-bg bg-success text-white rounded-circle"><i class="fa fa-check-circle"></i></div>
                        <div class="<?php echo $is_rtl ? 'mr-4' : 'ml-4'; ?>">
                            <span class="text-muted"><?php echo esc_html($t['enabled_modules']); ?></span>
                            <h4 class="mb-0 font-weight-bold text-success"><?php echo $enabled_count; ?></h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-4 col-sm-12">
                <div class="card">
                    <div class="body d-flex align-items-center">
                        <div class="icon-in-bg bg-danger text-white rounded-circle"><i class="fa fa-times-circle"></i></div>
                        <div class="<?php echo $is_rtl ? 'mr-4' : 'ml-4'; ?>">
                            <span class="text-muted"><?php echo esc_html($t['disabled_modules']); ?></span>
                            <h4 class="mb-0 font-weight-bold text-danger"><?php echo $disabled_count; ?></h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modules Grid -->
        <div class="row clearfix">
            <?php foreach ($all_modules as $module_id => $module): ?>
            <div class="col-lg-4 col-md-6 col-sm-12">
                <div class="card module-card <?php echo $module['is_enabled'] ? 'module-enabled' : 'module-disabled'; ?>" data-module-id="<?php echo esc_attr($module_id); ?>">
                    <div class="body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="module-icon">
                                <i class="fa <?php echo esc_attr($module['icon'] ?? 'fa-puzzle-piece'); ?> fa-2x text-primary"></i>
                            </div>
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input module-toggle"
                                       id="module-<?php echo esc_attr($module_id); ?>"
                                       data-module-id="<?php echo esc_attr($module_id); ?>"
                                       <?php echo $module['is_enabled'] ? 'checked' : ''; ?>>
                                <label class="custom-control-label" for="module-<?php echo esc_attr($module_id); ?>"></label>
                            </div>
                        </div>
                        <h5 class="mb-1"><?php echo esc_html($module['name']); ?></h5>
                        <p class="text-muted small mb-3"><?php echo esc_html($module['description']); ?></p>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="badge <?php echo $module['is_enabled'] ? 'badge-success' : 'badge-secondary'; ?> module-status-badge">
                                <?php echo $module['is_enabled'] ? esc_html($t['enabled']) : esc_html($t['disabled']); ?>
                            </span>
                            <small class="text-muted"><?php echo esc_attr($module_id); ?></small>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (empty($all_modules)): ?>
            <div class="col-12">
                <div class="card">
                    <div class="body text-center py-5">
                        <i class="fa fa-puzzle-piece fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted"><?php echo $is_rtl ? 'لا توجد وحدات مسجلة' : 'No modules registered'; ?></h5>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<style>
.module-card {
    transition: all 0.3s ease;
    border: 2px solid transparent;
}
.module-card:hover {
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
}
.module-card.module-enabled {
    border-color: #28a745;
}
.module-card.module-disabled {
    border-color: #e0e0e0;
    opacity: 0.8;
}
.module-icon {
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(102, 126, 234, 0.1);
    border-radius: 10px;
}
.custom-switch .custom-control-label::before {
    width: 2.5rem;
    height: 1.25rem;
    border-radius: 0.625rem;
}
.custom-switch .custom-control-label::after {
    width: 1rem;
    height: 1rem;
    border-radius: 50%;
}
.custom-switch .custom-control-input:checked ~ .custom-control-label::after {
    transform: translateX(1.25rem);
}
.icon-in-bg {
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var translations = <?php echo json_encode(array(
        'saving' => $t['saving'],
        'save_changes' => $t['save_changes'],
        'changes_saved' => $t['changes_saved'],
        'error_saving' => $t['error_saving'],
        'reloading' => $t['reloading'],
        'enabled' => $t['enabled'],
        'disabled' => $t['disabled'],
        'dependency_warning' => $is_rtl ? 'تحذير: لا يمكن تعطيل هذه الوحدة لأن الوحدات التالية تعتمد عليها:' : 'Warning: Cannot disable this module because the following modules depend on it:',
        'disable_dependents_first' => $is_rtl ? 'قم بتعطيل الوحدات التابعة أولاً.' : 'Disable the dependent modules first.',
    )); ?>;

    // Module dependencies data
    var moduleDependents = <?php echo json_encode($module_dependents); ?>;
    var moduleNames = <?php echo json_encode(array_map(function($m) { return $m['name']; }, $all_modules)); ?>;

    // Track changes
    var pendingChanges = {};

    // Handle toggle change
    document.querySelectorAll('.module-toggle').forEach(function(toggle) {
        toggle.addEventListener('change', function(e) {
            var moduleId = this.dataset.moduleId;
            var isEnabled = this.checked;
            var card = this.closest('.module-card');
            var badge = card.querySelector('.module-status-badge');

            // Check for dependencies when disabling
            if (!isEnabled && moduleDependents[moduleId]) {
                var activeDeps = moduleDependents[moduleId].filter(function(dep) {
                    // Check if dependent is still enabled (not in pending changes as disabled)
                    if (pendingChanges.hasOwnProperty(dep)) {
                        return pendingChanges[dep];
                    }
                    var depToggle = document.querySelector('.module-toggle[data-module-id="' + dep + '"]');
                    return depToggle && depToggle.checked;
                });

                if (activeDeps.length > 0) {
                    e.preventDefault();
                    this.checked = true; // Revert the toggle

                    var depNames = activeDeps.map(function(dep) {
                        return moduleNames[dep] || dep;
                    }).join(', ');

                    showAlert('warning', translations.dependency_warning + ' <strong>' + depNames + '</strong>. ' + translations.disable_dependents_first);
                    return;
                }
            }

            // Update UI
            if (isEnabled) {
                card.classList.remove('module-disabled');
                card.classList.add('module-enabled');
                badge.classList.remove('badge-secondary');
                badge.classList.add('badge-success');
                badge.textContent = translations.enabled;
            } else {
                card.classList.remove('module-enabled');
                card.classList.add('module-disabled');
                badge.classList.remove('badge-success');
                badge.classList.add('badge-secondary');
                badge.textContent = translations.disabled;
            }

            // Track change
            pendingChanges[moduleId] = isEnabled;
        });
    });

    // Save button click
    document.getElementById('save-modules-btn').addEventListener('click', function() {
        var btn = this;
        var originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> ' + translations.saving;
        btn.disabled = true;

        // Convert pending changes to array format
        var changes = [];
        for (var moduleId in pendingChanges) {
            changes.push({
                module_id: moduleId,
                is_enabled: pendingChanges[moduleId]
            });
        }

        if (changes.length === 0) {
            btn.innerHTML = originalText;
            btn.disabled = false;
            showAlert('info', '<?php echo $is_rtl ? "لا توجد تغييرات للحفظ" : "No changes to save"; ?>');
            return;
        }

        // Send AJAX request
        var xhr = new XMLHttpRequest();
        xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            btn.innerHTML = originalText;
            btn.disabled = false;

            if (xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        showAlert('success', translations.changes_saved + ' ' + translations.reloading);
                        pendingChanges = {}; // Clear pending changes
                        // Auto-reload after 1.5s so sidebar reflects changes
                        setTimeout(function() { window.location.reload(); }, 1500);
                    } else {
                        showAlert('danger', response.data.message || translations.error_saving);
                    }
                } catch (e) {
                    showAlert('danger', translations.error_saving);
                }
            } else {
                showAlert('danger', translations.error_saving);
            }
        };
        xhr.onerror = function() {
            btn.innerHTML = originalText;
            btn.disabled = false;
            showAlert('danger', translations.error_saving);
        };
        xhr.send('action=sc_update_modules&changes=' + encodeURIComponent(JSON.stringify(changes)) + '&nonce=<?php echo wp_create_nonce('sc_module_manager'); ?>');
    });

    function showAlert(type, message) {
        var alertHtml = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
            message +
            '<button type="button" class="close" data-dismiss="alert" aria-label="Close">' +
            '<span aria-hidden="true">&times;</span></button></div>';
        document.getElementById('module-alerts').innerHTML = alertHtml;

        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            var alert = document.querySelector('#module-alerts .alert');
            if (alert) {
                alert.classList.remove('show');
                setTimeout(function() { alert.remove(); }, 150);
            }
        }, 5000);
    }
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
