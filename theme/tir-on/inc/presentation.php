<?php
defined('ABSPATH') || exit;

function t_e($value) {
    return esc_html((string) $value);
}

function t_home_copy_fields() {
    return [
        'hero_title' => ['Titlu, primul rând', 'Amintirile tale,'],
        'hero_emphasis' => ['Titlu, rândul italic', 'într-o nouă lumină.'],
        'hero_intro' => ['Text introductiv', 'Unele fotografii merită mai mult decât un loc în galerie. Transformă una dintre ele într-un obiect luminos, creat special pentru tine.'],
        'feature_subtitle' => ['Subtitlu Luna', 'O fotografie. O lumină. O amintire care rămâne.'],
        'feature_intro' => ['Prezentare Luna', 'Un portret în relief, creat după fotografia ta, care își dezvăluie detaliile atunci când îl aprinzi. Un obiect personal, pentru oamenii și momentele care contează.'],
    ];
}

function t_home_copy($key) {
    return get_theme_mod('tiron_' . $key, t_home_copy_fields()[$key][1]);
}

function t_asset($path) {
    return get_theme_file_uri('/assets/' . ltrim($path, '/'));
}

function t_scene($key) {
    $attachment = absint(get_theme_mod('tiron_image_' . $key, 0));
    $url = $attachment ? wp_get_attachment_image_url($attachment, 'full') : false;
    return $url ?: t_asset('images/' . $key . '.webp');
}

function t_luna_id() {
    return (int) get_option('tiron_luna_product_id', 0) ?: (int) (get_page_by_path('luna', OBJECT, 'product')->ID ?? 0);
}

function t_is_luna($id) {
    return (int) $id === t_luna_id() && $id;
}

function t_url($key) {
    if ($key === 'luna') {
        $id = t_luna_id();
        return $id ? get_permalink($id) : home_url('/produs/luna/');
    }
    if ($key === 'cart') {
        return function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/');
    }
    return home_url('/');
}

function t_cart_count() {
    $cart = function_exists('WC') ? WC()->cart : null;
    $count = $cart ? $cart->get_cart_contents_count() : 0;
    return '<span class="bag-count" aria-live="polite">' . esc_html($count) . '</span>';
}

function t_product_data() {
    $id = t_luna_id();
    $product = $id && function_exists('wc_get_product') ? wc_get_product($id) : false;
    $images = [];
    if ($product) {
        $ids = array_filter(array_merge([$product->get_image_id()], $product->get_gallery_image_ids()));
        foreach (array_unique($ids) as $image_id) {
            $url = wp_get_attachment_image_url($image_id, 'large');
            if ($url) {
                $images[] = $url;
            }
        }
    }
    $metadata = static function ($key, $fallback = '') use ($id) {
        $value = $id ? get_post_meta($id, '_tiron_' . $key, true) : '';
        return $value !== '' ? $value : $fallback;
    };
    $price = $product && $product->get_price() !== '' ? wc_get_price_to_display($product) : null;
    $currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'RON';
    $price_text = $price !== null ? number_format_i18n((float) $price, floor((float) $price) === (float) $price ? 0 : wc_get_price_decimals()) . ' ' . $currency : 'Preț în curs de configurare';
    $limit = $product ? (int) $product->get_max_purchase_quantity() : 0;
    $minimum = $product ? max(1, (int) $product->get_min_purchase_quantity()) : 1;
    $maximum = -1 === $limit ? 99 : min(99, $limit);
    return [
        'id' => $id,
        'minimum' => $minimum,
        'maximum' => $maximum,
        'name' => $product ? $product->get_name() : 'Luna',
        'price' => $price_text,
        'description' => $product && $product->get_short_description() ? wp_strip_all_tags($product->get_short_description()) : 'Un litofan circular personalizat după fotografia ta, cu lumină LED alb-caldă și finisaj espresso mat.',
        'concept' => !$images,
        'images' => $images ?: [t_asset('images/hero-mobile.webp'), t_asset('images/feature.webp'), t_asset('images/detail-mobile.webp')],
        'production' => $metadata('production'),
        'power' => $metadata('power', 'Alimentare prin cablu'),
        'diameter' => $metadata('diameter', 'aprox. 134,5 mm'),
        'finish' => $metadata('finish', 'Espresso mat'),
        'light' => $metadata('light', 'LED alb-cald'),
        'limit_mb' => function_exists('tiron_photo_limit_mb') ? tiron_photo_limit_mb() : 0,
        'available' => $product && $product->is_purchasable() && $product->is_in_stock() && $maximum >= $minimum
            && function_exists('tiron_photo_limit_mb') && function_exists('tiron_personalization_nonce'),
    ];
}

function t_icon($name, $size = 20) {
    $paths = [
        'bag' => '<path d="M5 8.5h14l1 13H4l1-13Z"/><path d="M8.5 9V6a3.5 3.5 0 0 1 7 0v3"/>',
        'menu' => '<path d="M3 7h18M3 12h18M3 17h18"/>',
        'close' => '<path d="m6 6 12 12M18 6 6 18"/>',
        'prev' => '<path d="m14.5 6-6 6 6 6"/>',
        'next' => '<path d="m9.5 6 6 6-6 6"/>',
        'spark' => '<path d="M11 2.5c0 6.7-1.4 8.2-8 8.5 6.6.4 8 2 8 8.5.2-6.5 1.7-8.1 8-8.5-6.3-.3-7.8-1.8-8-8.5Z"/><path d="m19 2 .8 2.2L22 5l-2.2.8L19 8l-.8-2.2L16 5l2.2-.8Z"/>',
        'photo' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8" cy="9" r="1.5"/><path d="m4 18 5-5 3 3 4-5 5 6"/>',
        'sun' => '<circle cx="12" cy="12" r="4.5"/><path d="M12 1v3m0 16v3M1 12h3m16 0h3M4 4l2 2m12 12 2 2M4 20l2-2M18 6l2-2"/>',
        'layers' => '<path d="m2 8 10-5 10 5-10 5L2 8Zm0 4.5 10 5 10-5M2 17l10 5 10-5"/>',
        'gift' => '<rect x="3" y="8" width="18" height="4" rx=".8"/><path d="M5 12v9h14v-9M12 8v13M12 8C2 8 6 0 10 5l2 3Zm0 0c10 0 6-8 2-3l-2 3Z"/>',
        'circle' => '<circle cx="12" cy="12" r="9"/><path d="M12 3v3m0 12v3M3 12h3m12 0h3"/>',
        'down' => '<path d="m6 9 6 6 6-6"/>',
    ];
    return '<svg aria-hidden="true" width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.45" stroke-linecap="round" stroke-linejoin="round">' . ($paths[$name] ?? '') . '</svg>';
}
