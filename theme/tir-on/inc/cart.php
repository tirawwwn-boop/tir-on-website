<?php
/** Presentation around the classic WooCommerce cart. WooCommerce owns all cart mutations. */
defined('ABSPATH') || exit;

add_action('woocommerce_before_cart', static function () {
    echo '<div class="tiron-cart-layout">';
}, 99);

add_action('woocommerce_after_cart', static function () {
    echo '</div>';
}, 1);

add_filter('woocommerce_cart_item_thumbnail', static function ($html, $line) {
    $product = $line['data'];
    if (!t_is_luna($line['product_id']) || $product->get_image_id()) {
        return $html;
    }

    // Use only public catalogue imagery, including when the atelier has started adding gallery photos.
    foreach ($product->get_gallery_image_ids() as $id) {
        $gallery_image = wp_get_attachment_image($id, 'woocommerce_thumbnail');
        if ($gallery_image) {
            return $gallery_image;
        }
    }
    return sprintf('<img src="%s" width="120" height="144" decoding="async" alt="%s">', esc_url(t_asset('images/hero-mobile.webp')), esc_attr('Luna iluminată · vizualizare de concept'));
}, 10, 2);
