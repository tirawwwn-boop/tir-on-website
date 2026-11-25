<?php
defined('ABSPATH') || exit;

/** Published editorial media selected by the shop owner, independent of order photos. */
function t_storefront_image($key) {
    $id = absint(get_theme_mod('tiron_image_' . $key));
    if (!$id || !wp_attachment_is_image($id)) {
        return null;
    }
    $url = wp_get_attachment_image_url($id, 'large');
    return $url ? [
        'url' => $url,
        'srcset' => wp_get_attachment_image_srcset($id, 'large') ?: '',
        'alt' => (string) get_post_meta($id, '_wp_attachment_image_alt', true),
    ] : null;
}

add_action('customize_register', static function ($customizer) {
    $customizer->add_section('tiron_mobile_covers', [
        'title' => 'TIR-ON · Fotografii de copertă pe mobil',
        'description' => 'Opțional, alege un cadraj mobil pentru fiecare fotografie de copertă. Fotografia principală se configurează în „TIR-ON · Pagina de start”.',
    ]);
    foreach (['hero' => 'Lumină aprinsă', 'hero-alt' => 'În ambient', 'detail' => 'Detaliu de design'] as $scene => $label) {
        $setting = 'tiron_image_' . $scene . '-mobile';
        $customizer->add_setting($setting, ['default' => 0, 'sanitize_callback' => 'absint']);
        $customizer->add_control(new WP_Customize_Media_Control($customizer, $setting, [
            'label' => $label, 'section' => 'tiron_mobile_covers', 'mime_type' => 'image',
            'description' => 'Alege întâi fotografia principală, apoi un cadraj apropiat de pătrat al aceleiași fotografii. Completează textul alternativ în biblioteca media.',
        ]));
    }
});

add_action('after_setup_theme', static function () {
    register_nav_menus([
        'tiron-footer-help' => 'Subsol TIR-ON · Asistență',
        'tiron-footer-delivery' => 'Subsol TIR-ON · Livrare',
        'tiron-footer-legal' => 'Subsol TIR-ON · Informații legale',
        'tiron-footer-social' => 'Subsol TIR-ON · Rețele sociale',
    ]);
});

/** A configured footer menu takes precedence over the published store destinations. */
function t_footer_links($slot, $fallback = []) {
    if (has_nav_menu('tiron-footer-' . $slot)) {
        wp_nav_menu([
            'theme_location' => 'tiron-footer-' . $slot,
            'container' => false,
            'menu_class' => 'footer-links',
            'depth' => 1,
            'fallback_cb' => false,
        ]);
        return;
    }
    if ($fallback) {
        echo '<ul class="footer-links">';
        foreach ($fallback as $label => $url) {
            if ($url) {
                echo '<li><a href="' . esc_url($url) . '">' . esc_html($label) . '</a></li>';
            }
        }
        echo '</ul>';
    }
}
