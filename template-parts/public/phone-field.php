<?php
/**
 * WhatsApp number field: country code + number.
 * $args: id (input id), code_id, name (default phone), code_name (default phone_code), label, required, value, code.
 *
 * @package sc_events
 */

if (!defined('ABSPATH')) {
    exit;
}

$a = wp_parse_args($args ?? array(), array(
    'id'        => 'phone',
    'code_id'   => 'phone_code',
    'name'      => 'phone',
    'code_name' => 'phone_code',
    'label'     => sc_t('frontend.whatsapp_number', 'WhatsApp number'),
    'required'  => true,
    'value'     => '',
    'code'      => '+20',
    'hint'      => '',
));
?>
<div class="w-field">
    <label class="w-label" for="<?php echo esc_attr($a['id']); ?>"><?php echo esc_html($a['label']); ?></label>
    <span class="w-auth__tel">
        <select class="w-select" id="<?php echo esc_attr($a['code_id']); ?>" name="<?php echo esc_attr($a['code_name']); ?>"
                aria-label="<?php echo esc_attr(sc_t('frontend.country_code', 'Country code')); ?>">
            <?php foreach (array('+20', '+966', '+971', '+965', '+974', '+973', '+968', '+962', '+218', '+249') as $code): ?>
                <option value="<?php echo esc_attr($code); ?>" <?php selected($a['code'], $code); ?>><?php echo esc_html($code); ?></option>
            <?php endforeach; ?>
        </select>
        <input class="w-input" type="tel" id="<?php echo esc_attr($a['id']); ?>" name="<?php echo esc_attr($a['name']); ?>"
               autocomplete="tel-national" inputmode="tel" placeholder="10 0000 0000" dir="ltr"
               value="<?php echo esc_attr($a['value']); ?>" <?php echo $a['required'] ? 'required' : ''; ?>>
    </span>
    <?php if ($a['hint']): ?><span class="w-hint"><?php echo esc_html($a['hint']); ?></span><?php endif; ?>
</div>
