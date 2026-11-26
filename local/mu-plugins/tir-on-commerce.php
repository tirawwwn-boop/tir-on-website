<?php
/** Plugin Name: TIR-ON — Administrare comercială */
defined('ABSPATH') || exit;

add_filter('woocommerce_shipping_methods', static function ($methods) {
    require_once __DIR__ . '/tiron/ro-delivery.php';
    $methods['tiron_ro'] = 'Tiron_RO_Delivery';
    return $methods;
});

function tiron_commercial_options(): array {
    return (array) get_option('tiron_commercial_v6', []);
}

add_action('admin_menu', static function () {
    add_submenu_page('woocommerce', 'Magazin TIR-ON', 'Magazin TIR-ON', 'manage_options', 'tiron-commercial', 'tiron_commercial_page');
});

add_action('admin_init', static function () {
    register_setting('tiron_commercial', 'tiron_commercial_v6', ['sanitize_callback' => 'tiron_clean_commercial']);
});

function tiron_clean_commercial($input): array {
    $old = tiron_commercial_options();
    $out = [];
    foreach (['christmas', 'from', 'until', 'home_description', 'luna_description', 'meta', 'tiktok'] as $key) {
        $out[$key] = sanitize_text_field(is_string($input[$key] ?? null) ? $input[$key] : '');
    }
    foreach (['from', 'until'] as $key) {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $out[$key]);
        if ($out[$key] !== '' && (!$date || $date->format('Y-m-d') !== $out[$key])) {
            add_settings_error('tiron_commercial', 'date', 'Verifică datele campaniei.');
            return $old;
        }
    }
    if (($out['christmas'] && (!$out['from'] || !$out['until'] || $out['from'] > $out['until']))
        || ($out['meta'] && !preg_match('/^[0-9]{5,24}$/D', $out['meta']))
        || ($out['tiktok'] && !preg_match('/^[A-Z0-9]{10,32}$/D', $out['tiktok']))) {
        add_settings_error('tiron_commercial', 'invalid', 'Verifică perioada campaniei și identificatorii pixelilor.');
        return $old;
    }
    return $out;
}

function tiron_commercial_page(): void {
    if (!current_user_can('manage_options')) return;
    $settings = tiron_commercial_options();
    $product = absint(get_option('tiron_luna_product_id'));
    $links = [
        'Comenzi' => class_exists('Automattic\WooCommerce\Utilities\OrderUtil') && Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ? 'admin.php?page=wc-orders' : 'edit.php?post_type=shop_order',
        'Producție și fotografii' => 'admin.php?page=tiron-atelier',
        'Costuri și marjă' => 'admin.php?page=tiron-profit',
        'Zone și tarife' => 'admin.php?page=wc-settings&tab=shipping',
        'Plăți' => 'admin.php?page=wc-settings&tab=checkout',
        'Jurnale de diagnostic' => 'admin.php?page=wc-status&tab=logs',
    ];
    if ($product) $links['Luna · producție, livrare și ambalare'] = 'post.php?action=edit&post=' . $product;
    echo '<div class="wrap"><h1>Magazin TIR-ON</h1><p>';
    foreach ($links as $label => $target) {
        echo '<a class="button" href="' . esc_url(admin_url($target)) . '">' . esc_html($label) . '</a> ';
    }
    echo '</p><p>În zona România poți adăuga „Livrare TIR-ON · România”. Completează tariful, taxarea și estimarea pentru fiecare serviciu aprobat.</p>';
    $stripe = get_option('woocommerce_stripe_settings', []);
    echo '<h2>Verificări rapide</h2><ul>';
    $checks = [
        'Mod configurat Stripe' => ($stripe['testmode'] ?? '') === 'yes' ? 'TEST' : 'De verificat',
        'Simulator de plată local' => function_exists('tiron_local_test_checkout_enabled') && tiron_local_test_checkout_enabled() ? 'Activ local' : 'Oprit',
        'Procesare și director privat' => function_exists('tiron_photo_processing_ready') && tiron_photo_processing_ready() ? 'Disponibile' : 'Verifică permisiunile PHP',
        'Indexarea WordPress' => get_option('blog_public') ? 'Permisă' : 'Oprită',
    ];
    foreach ($checks as $label => $value) echo '<li><strong>' . esc_html($label) . '</strong> — ' . esc_html($value) . '</li>';
    echo '</ul>';
    settings_errors();
    echo '<form action="options.php" method="post">';
    settings_fields('tiron_commercial');
    echo '<h2>Campanie de Crăciun</h2><p>Mesajul apare pe prima pagină în perioada aleasă, conform fusului orar WordPress. Lasă textul gol pentru dezactivare.</p>';
    foreach (['christmas' => 'Mesaj', 'from' => 'Prima zi', 'until' => 'Ultima zi', 'home_description' => 'Descriere SEO pentru Acasă', 'luna_description' => 'Descriere SEO pentru Luna', 'meta' => 'ID Meta Pixel', 'tiktok' => 'ID TikTok Pixel'] as $key => $label) {
        if ($key === 'home_description') echo '<h2>Căutare și distribuire</h2><p>Lasă descrierile goale pentru a folosi prezentările din magazin.</p>';
        if ($key === 'meta') echo '<h2>Măsurare cu consimțământ</h2><p>Activarea necesită și aprobarea explicită în configurația site-ului.</p>';
        $type = in_array($key, ['from','until'], true) ? 'date' : 'text';
        echo '<p><label>' . esc_html($label) . '<br><input class="regular-text" type="' . $type . '" name="tiron_commercial_v6[' . $key . ']" value="' . esc_attr($settings[$key] ?? '') . '"></label></p>';
    }
    submit_button();
    echo '</form></div>';
}
