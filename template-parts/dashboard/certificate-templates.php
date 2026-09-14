<?php
/**
 * Certificate templates — cards with where each template is used.
 *
 * Designs are edited in the visual builder; this page previews them as a PDF
 * with sample values, duplicates, sets the default and deletes unused ones.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!SC_Event_Manager_Dashboard::is_event_manager()) {
    wp_die(__('You do not have permission to access this page.', 'sc_events'));
}

global $wpdb, $load_wd_overview;
$load_wd_overview = true;
$p = $wpdb->prefix;

$templates = $wpdb->get_results("SELECT id, name, description, background_image, orientation, paper_size, is_default, is_active, design_mode, updated_at FROM {$p}sc_certificate_templates ORDER BY is_default DESC, updated_at DESC");
$issued = array();
foreach ($wpdb->get_results("SELECT template_id, COUNT(*) n FROM {$p}sc_certificates GROUP BY template_id") as $r) {
    $issued[(int) $r->template_id] = (int) $r->n;
}
$used_by = array();
// Two queries: the events and workshops tables use different collations, so a UNION fails.
foreach (array('sc_events', 'sc_workshops') as $table) {
    foreach ($wpdb->get_results("SELECT certificate_template_id AS tid, title FROM {$p}{$table} WHERE certificate_template_id > 0") as $r) {
        $used_by[(int) $r->tid][] = $r->title;
    }
}

$dashboard_url = home_url('/event-manager-dashboard/');
$preview_base = add_query_arg(array('action' => 'sc_certificate_template_preview', 'nonce' => wp_create_nonce('sc_dashboard_nonce')), admin_url('admin-ajax.php'));
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
            <h1><?php echo esc_html(sc_t('dashboard_pages.certificate_templates', 'Certificate templates')); ?><span class="w-page-head__count"><?php echo esc_html(number_format_i18n(count($templates))); ?></span></h1>
            <p class="w-page-head__sub"><?php echo esc_html(sc_t('dashboard_pages.certificate_templates_sub', 'The designs certificates are printed from. Choose one per event or workshop in its Certificates settings; the default is used when none is chosen.')); ?></p>
        </div>
        <div class="w-page-head__actions">
            <a class="btn btn-secondary" href="<?php echo esc_url($dashboard_url . 'certificates'); ?>"><?php echo esc_html(sc_t('dashboard_pages.issued_certificates', 'Issued certificates')); ?></a>
            <a class="btn btn-primary" href="<?php echo esc_url($dashboard_url . 'certificate-visual-builder'); ?>"><i class="fa fa-plus" aria-hidden="true"></i> <?php echo esc_html(sc_t('dashboard_pages.new_template', 'New template')); ?></a>
        </div>
    </div>

    <?php if (!$templates): ?>
        <div class="w-state"><p class="w-state__title"><?php echo esc_html(sc_t('dashboard_pages.no_templates_yet', 'No templates yet')); ?></p><p class="w-state__text"><?php echo esc_html(sc_t('dashboard_pages.no_templates_help', 'Design one in the visual builder: upload the background artwork and place the name, event, date and QR code on it.')); ?></p></div>
    <?php else: ?>
    <div class="w-tplgrid">
        <?php foreach ($templates as $tpl):
            $id = (int) $tpl->id;
            $thumb = $tpl->background_image ? wp_get_attachment_image_url((int) $tpl->background_image, 'medium_large') : '';
            $uses = $used_by[$id] ?? array();
            $count = $issued[$id] ?? 0;
            $why_locked = $tpl->is_default ? sc_t('dashboard_pages.tpl_locked_default', 'It’s the default template.') : ($uses ? sc_t('dashboard_pages.tpl_locked_used', 'An event or workshop uses it.') : ($count ? sc_t('dashboard_pages.tpl_locked_issued', 'Certificates were issued with it — they’re printed from this design.') : ''));
        ?>
        <article class="w-tpl<?php echo $tpl->is_active ? '' : ' is-inactive'; ?>" data-id="<?php echo $id; ?>">
            <a class="w-tpl__thumb w-tpl__thumb--<?php echo esc_attr($tpl->orientation); ?>" href="<?php echo esc_url(add_query_arg('template_id', $id, $preview_base)); ?>" target="_blank" rel="noopener" aria-label="<?php echo esc_attr(sprintf(sc_t('dashboard_pages.preview_x', 'Preview %s'), $tpl->name)); ?>">
                <?php if ($thumb): ?>
                    <img src="<?php echo esc_url($thumb); ?>" alt="" loading="lazy">
                <?php else: ?>
                    <i class="fa fa-certificate" aria-hidden="true"></i>
                <?php endif; ?>
            </a>
            <div class="w-tpl__body">
                <h2 class="w-tpl__name"><?php echo esc_html($tpl->name); ?>
                    <?php if ($tpl->is_default): ?><span class="w-tag w-tag--primary"><?php echo esc_html(sc_t('dashboard_pages.default', 'Default')); ?></span><?php endif; ?>
                    <?php if (!$tpl->is_active): ?><span class="w-tag"><?php echo esc_html(sc_t('dashboard_pages.inactive', 'Inactive')); ?></span><?php endif; ?>
                </h2>
                <p class="w-sub"><?php echo esc_html(ucfirst($tpl->orientation) . ' · ' . $tpl->paper_size . ' · ' . sprintf(_n('%s certificate issued', '%s certificates issued', $count, 'sc_events'), number_format_i18n($count))); ?></p>
                <p class="w-tpl__uses"><?php
                    if ($uses) {
                        echo esc_html(sc_t('dashboard_pages.used_by', 'Used by') . ': ' . implode(', ', array_slice($uses, 0, 3)) . (count($uses) > 3 ? ' +' . (count($uses) - 3) : ''));
                    } else {
                        echo esc_html(sc_t('dashboard_pages.tpl_not_assigned', 'Not chosen by any event or workshop.'));
                    }
                ?></p>
            </div>
            <div class="w-tpl__actions">
                <a class="btn btn-sm btn-primary" href="<?php echo esc_url($dashboard_url . 'certificate-visual-builder?template_id=' . $id); ?>"><?php echo esc_html(sc_t('dashboard_pages.edit_design', 'Edit design')); ?></a>
                <a class="btn btn-sm btn-secondary" href="<?php echo esc_url(add_query_arg('template_id', $id, $preview_base)); ?>" target="_blank" rel="noopener"><?php echo esc_html(sc_t('dashboard_pages.preview_pdf', 'Preview PDF')); ?></a>
                <button type="button" class="btn btn-sm btn-secondary" data-act="duplicate"><?php echo esc_html(sc_t('dashboard_pages.duplicate', 'Duplicate')); ?></button>
                <button type="button" class="btn btn-sm btn-secondary" data-act="default"><?php echo esc_html($tpl->is_default ? sc_t('dashboard_pages.remove_default', 'Remove default') : sc_t('dashboard_pages.make_default', 'Make default')); ?></button>
                <button type="button" class="btn btn-sm w-tpl__delete" data-act="delete" data-name="<?php echo esc_attr($tpl->name); ?>"<?php echo $why_locked ? ' disabled title="' . esc_attr(sc_t('dashboard_pages.cant_delete', 'Can’t delete:') . ' ' . $why_locked) . '"' : ''; ?>><?php echo esc_html(sc_t('dashboard_pages.delete', 'Delete')); ?></button>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <p class="w-field__help mt-3"><?php echo esc_html(sc_t('dashboard_pages.tpl_edit_note', 'Editing a design changes every certificate printed from it, including ones already issued — they are generated fresh on each download. Duplicate a template first if past certificates should keep the old look.')); ?></p>
    <?php endif; ?>

</div>
</div>

<script>
jQuery(function ($) {
    'use strict';
    var L = <?php echo $js(array(
        'confirmDelete' => sc_t('dashboard_pages.confirm_delete_template', 'Delete the template “%s”? This cannot be undone.'),
        'failed'        => sc_t('errors.something_wrong', 'Something went wrong. Please try again.'),
    )); ?>;
    var actions = { duplicate: 'sc_duplicate_certificate_template', 'default': 'sc_toggle_default_certificate_template', 'delete': 'sc_delete_certificate_template' };

    function run(act, id, $btn) {
        $btn.prop('disabled', true);
        $.post(scDashboard.ajaxurl, { action: actions[act], nonce: scDashboard.nonce, template_id: id })
            .done(function (res) {
                if (res.success) {
                    try { sessionStorage.setItem('scTplMessage', res.data.message || ''); } catch (x) { /* storage blocked */ }
                    window.location.reload();
                } else {
                    $btn.prop('disabled', false);
                    showError(res.data && res.data.message || L.failed);
                }
            })
            .fail(function () { $btn.prop('disabled', false); showError(L.failed); });
    }

    $('.w-tplgrid').on('click', '[data-act]', function () {
        var $btn = $(this), act = $btn.data('act'), id = $btn.closest('.w-tpl').data('id');
        if (act === 'delete') {
            showDeleteConfirm(L.confirmDelete.replace('%s', $btn.data('name'))).then(function (r) { if (r.isConfirmed) { run(act, id, $btn); } });
        } else {
            run(act, id, $btn);
        }
    });

    try {
        var msg = sessionStorage.getItem('scTplMessage');
        if (msg) { sessionStorage.removeItem('scTplMessage'); showSuccess(msg); }
    } catch (x) { /* storage blocked */ }
});
</script>

<?php get_template_part('template-parts/dashboard/components/dashboard', 'footer'); ?>
