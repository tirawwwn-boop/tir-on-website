<?php
defined('ABSPATH') || exit;

require_once __DIR__ . '/inc/presentation.php';
if (is_readable(__DIR__ . '/views/home-story-customizer.php')) {
    require_once __DIR__ . '/views/home-story-customizer.php';
}

add_action('after_setup_theme', static function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('woocommerce');
    add_theme_support('html5', ['gallery', 'caption', 'style', 'script', 'search-form']);
});

add_action('wp_enqueue_scripts', static function () {
    $css = ['fonts', 'site'];
    $js = ['site'];
    if (function_exists('is_cart') && (is_cart() || is_checkout())) {
        $css[] = 'commerce';
        if (is_cart()) {
            // Classic cart updates quantities and removes rows with WooCommerce AJAX.
            wp_enqueue_script('wc-cart-fragments');
        }
    }
    if (is_front_page()) {
        $css[] = 'story';
        $js[] = 'story';
    }
    if (function_exists('is_product') && is_product() && t_is_luna(get_queried_object_id())) {
        $css[] = 'product';
        $js[] = 'product';
    }
    foreach ($css as $name) {
        $path = '/assets/' . $name . '.css';
        if (is_file(__DIR__ . $path)) {
            wp_enqueue_style('tiron-' . $name, get_theme_file_uri($path), [], (string) filemtime(__DIR__ . $path));
        }
    }
    foreach ($js as $name) {
        $path = '/assets/' . $name . '.js';
        if (is_file(__DIR__ . $path)) {
            wp_enqueue_script('tiron-' . $name, get_theme_file_uri($path), [], (string) filemtime(__DIR__ . $path), ['strategy' => 'defer', 'in_footer' => true]);
        }
    }
}, 20);

add_filter('woocommerce_add_to_cart_fragments', static function ($fragments) {
    $fragments['span.bag-count'] = t_cart_count();
    return $fragments;
});

// The editorial views render their own product form and layout.
add_action('wp_enqueue_scripts', static function () {
    if (!is_front_page() && !(function_exists('is_product') && is_product() && t_is_luna(get_queried_object_id()))) {
        return;
    }
    foreach (['woocommerce-general', 'woocommerce-layout', 'woocommerce-smallscreen', 'wc-blocks-style'] as $handle) {
        wp_dequeue_style($handle);
    }
    foreach (['woocommerce', 'wc-single-product', 'wc-add-to-cart', 'wc-cart-fragments', 'wc-order-attribution'] as $handle) {
        wp_dequeue_script($handle);
    }
}, 100);

add_filter('template_include', static function ($template) {
    if (function_exists('is_product') && is_product() && t_is_luna(get_queried_object_id())) {
        return __DIR__ . '/single-luna.php';
    }
    return $template;
}, 99);

add_action('customize_register', static function ($customizer) {
    $customizer->add_section('tiron_home', ['title' => 'TIR-ON · Pagina de start', 'priority' => 30]);
    $fields = [
        'hero_title' => ['Titlu, primul rând', 'Amintirile tale,'],
        'hero_emphasis' => ['Titlu, rândul italic', 'într-o nouă lumină.'],
        'hero_intro' => ['Text introductiv', 'Transformăm fotografia ta într-un obiect luminos personalizat. O amintire pe care o poți ține aproape.'],
        'feature_intro' => ['Prezentare Luna', 'Un disc sculptat în lumină, un suport cu linii fluide și o fotografie care înseamnă totul pentru tine. Acesta este Luna.'],
    ];
    foreach ($fields as $key => [$label, $default]) {
        $customizer->add_setting('tiron_' . $key, ['default' => $default, 'sanitize_callback' => 'sanitize_text_field']);
        $customizer->add_control('tiron_' . $key, ['label' => $label, 'section' => 'tiron_home', 'type' => 'text']);
    }
    foreach (['hero' => 'Copertă · vedere principală', 'hero-alt' => 'Luna în ambient', 'feature' => 'Compoziție cu fotografia originală', 'detail' => 'Detaliu de finisaj'] as $key => $label) {
        $setting = 'tiron_image_' . $key;
        $customizer->add_setting($setting, ['default' => 0, 'sanitize_callback' => 'absint']);
        $customizer->add_control(new WP_Customize_Media_Control($customizer, $setting, ['label' => $label, 'section' => 'tiron_home', 'mime_type' => 'image']));
    }
});

add_action('wp_head', static function () {
    if (!is_front_page() && !(function_exists('is_product') && is_product())) {
        return;
    }
    $product = t_product_data();
    $description = is_front_page()
        ? get_theme_mod('tiron_hero_intro', 'Transformăm fotografia ta într-un obiect luminos personalizat. Descoperă TIR-ON Luna.')
        : $product['description'];
    $image = is_front_page() ? t_scene('hero') : $product['images'][0];
    echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
    echo '<meta property="og:locale" content="ro_RO"><meta property="og:site_name" content="TIR-ON">';
    echo '<meta property="og:type" content="' . (is_front_page() ? 'website' : 'product') . '">';
    echo '<meta property="og:title" content="' . esc_attr(wp_get_document_title()) . '">';
    echo '<meta property="og:description" content="' . esc_attr($description) . '">';
    echo '<meta property="og:image" content="' . esc_url($image) . '">';
    echo '<meta property="og:url" content="' . esc_url(is_front_page() ? home_url('/') : get_permalink()) . '">';
    if (is_front_page() && !get_theme_mod('tiron_image_hero')) {
        echo '<link rel="preload" as="image" href="' . esc_url(t_asset('images/hero-mobile.webp')) . '" media="(max-width: 700px)">';
        echo '<link rel="preload" as="image" href="' . esc_url($image) . '" media="(min-width: 701px)">';
    }
}, 2);
