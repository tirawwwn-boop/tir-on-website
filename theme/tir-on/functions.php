<?php
defined('ABSPATH') || exit;

require_once __DIR__ . '/inc/presentation.php';
require_once __DIR__ . '/inc/storefront.php';
require_once __DIR__ . '/inc/cart.php';
require_once __DIR__ . '/inc/checkout.php';
require_once __DIR__ . '/inc/marketing.php';
if (is_readable(__DIR__ . '/views/home-story-customizer.php')) {
    require_once __DIR__ . '/views/home-story-customizer.php';
}

add_action('after_setup_theme', static function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('woocommerce');
    add_theme_support('html5', ['gallery', 'caption', 'style', 'script', 'search-form']);
});

// Supply the brand icon through WordPress, including its /favicon.ico redirect.
add_filter('get_site_icon_url', static function ($url, $size, $blog_id) {
    if (($blog_id && (int) $blog_id !== get_current_blog_id()) || get_option('site_icon')) {
        return $url;
    }
    $files = [32 => 'favicon-32.png', 180 => 'apple-touch-icon.png'];
    return t_asset('brand/' . ($files[(int) $size] ?? 'favicon.svg'));
}, 10, 3);

add_action('wp_enqueue_scripts', static function () {
    $css = ['fonts', 'site'];
    $js = ['site'];
    if (function_exists('is_cart') && (is_cart() || is_checkout())) {
        $css[] = 'commerce';
        if (is_cart()) {
            $css[] = 'cart';
            $js[] = 'cart';
            // Classic cart updates quantities and removes rows with WooCommerce AJAX.
            wp_enqueue_script('wc-cart-fragments');
        } elseif (is_checkout() && !is_wc_endpoint_url()) {
            $css[] = 'checkout';
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
            wp_enqueue_style('tiron-' . $name, get_theme_file_uri($path), [], substr(hash_file('sha256', __DIR__ . $path), 0, 12));
        }
    }
    foreach ($js as $name) {
        $path = '/assets/' . $name . '.js';
        if (is_file(__DIR__ . $path)) {
            wp_enqueue_script('tiron-' . $name, get_theme_file_uri($path), [], substr(hash_file('sha256', __DIR__ . $path), 0, 12), ['strategy' => 'defer', 'in_footer' => true]);
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
    foreach (t_home_copy_fields() as $key => [$label, $default]) {
        $customizer->add_setting('tiron_' . $key, ['default' => $default, 'sanitize_callback' => 'sanitize_text_field']);
        $customizer->add_control('tiron_' . $key, ['label' => $label, 'section' => 'tiron_home', 'type' => 'text']);
    }
    foreach (['hero' => 'Copertă · vedere principală', 'hero-alt' => 'Luna în ambient', 'feature' => 'Compoziție cu fotografia originală', 'detail' => 'Detaliu de finisaj'] as $key => $label) {
        $setting = 'tiron_image_' . $key;
        $customizer->add_setting($setting, ['default' => 0, 'sanitize_callback' => 'absint']);
        $customizer->add_control(new WP_Customize_Media_Control($customizer, $setting, ['label' => $label, 'section' => 'tiron_home', 'mime_type' => 'image']));
    }
});

add_filter('document_title_parts', static function ($parts) {
    if (is_front_page()) {
        $parts['tagline'] = 'Cadouri personalizate cu lumină';
    } elseif (function_exists('is_product') && is_product() && t_is_luna(get_queried_object_id())) {
        $parts['title'] = 'Luna · Litofan luminos personalizat';
    }
    return $parts;
});

add_action('wp_head', static function () {
    $home = is_front_page();
    $luna = function_exists('is_product') && is_product() && t_is_luna(get_queried_object_id());
    if (!$home && !$luna) {
        return;
    }
    $product = $home ? null : t_product_data();
    $description = $home ? t_home_copy('hero_intro') : $product['description'];
    $settings = function_exists('tiron_commercial_options') ? tiron_commercial_options() : [];
    $description = ($settings[$home ? 'home_description' : 'luna_description'] ?? '') ?: $description;
    $image = $home ? t_scene('hero') : $product['images'][0];
    $concept = $home ? !t_storefront_image('hero') : $product['concept'];
    echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
    echo '<meta property="og:locale" content="ro_RO"><meta property="og:site_name" content="TIR-ON">';
    echo '<meta property="og:type" content="' . ($home ? 'website' : 'product') . '">';
    echo '<meta property="og:title" content="' . esc_attr(wp_get_document_title()) . '">';
    echo '<meta property="og:description" content="' . esc_attr($description) . '">';
    echo '<meta property="og:image" content="' . esc_url($concept ? t_asset('images/social-luna.jpg') : $image) . '">';
    echo '<meta name="twitter:card" content="summary_large_image">';
    echo '<meta property="og:image:alt" content="' . esc_attr($concept ? 'Luna iluminată · vizualizare de concept' : 'Luna, litofan luminos personalizat') . '">';
    echo '<meta property="og:url" content="' . esc_url($home ? home_url('/') : get_permalink()) . '">';
    if ($home && $concept) {
        if (!t_storefront_image('hero-mobile')) {
            echo '<link rel="preload" as="image" href="' . esc_url(t_asset('images/hero-mobile.webp')) . '" media="(max-width: 700px)">';
        }
        echo '<link rel="preload" as="image" href="' . esc_url($image) . '" media="(min-width: 701px)">';
    }
}, 2);

add_action('wp', static function () {
    if (function_exists('is_product') && is_product() && t_is_luna(get_queried_object_id())
        && isset(WC()->structured_data)) {
        WC()->structured_data->generate_product_data(wc_get_product(get_queried_object_id()));
    }
});

add_filter('woocommerce_structured_data_product', static function ($markup, $product) {
    if (t_is_luna($product->get_id()) && empty($markup['image'])) {
        $markup['image'] = t_asset('images/social-luna.jpg');
    }
    return $markup;
}, 10, 2);

add_filter('woocommerce_structured_data_product_offer', static function ($offer, $product) {
    if (t_is_luna($product->get_id()) && !$product->is_on_sale()) {
        unset($offer['priceValidUntil']);
        foreach (array_keys($offer['priceSpecification'] ?? []) as $key) {
            unset($offer['priceSpecification'][$key]['validThrough']);
        }
    }
    return $offer;
}, 10, 2);


add_filter('wp_sitemaps_posts_query_args', static function ($args, $type) {
    if ($type === 'page' && function_exists('wc_get_page_id')) {
        $private = array_map('wc_get_page_id', ['cart', 'checkout', 'myaccount']);
        $args['post__not_in'] = array_unique(array_merge($args['post__not_in'] ?? [], array_filter($private, static fn($id) => $id > 0)));
    }
    return $args;
}, 10, 2);

add_filter('wp_robots', static function ($robots) {
    if (function_exists('is_checkout') && (is_checkout() || is_cart() || is_account_page())) {
        unset($robots['index']);
        $robots['noindex'] = true;
    }
    return $robots;
});
