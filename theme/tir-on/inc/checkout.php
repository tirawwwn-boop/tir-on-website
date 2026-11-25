<?php
defined('ABSPATH') || exit;

/** Use the physical items themselves; a new store may not have entered its shipping rates yet. */
function tiron_cart_requires_delivery_phone(): bool {
    if (!function_exists('WC') || !WC()->cart) {
        return false;
    }
    foreach (WC()->cart->get_cart_contents() as $item) {
        if (isset($item['data']) && $item['data']->needs_shipping()) {
            return true;
        }
    }
    return false;
}

// WooCommerce and its official Stripe wallet integration read this same setting.
// Keep dashboard settings and accounts untouched. No options are written.
add_filter('pre_option_woocommerce_checkout_phone_field', static function ($value) {
    $checkout_request = defined('WOOCOMMERCE_CHECKOUT') || (defined('WC_DOING_AJAX') && WC_DOING_AJAX)
        || (did_action('wp') && function_exists('is_checkout') && (is_checkout() || is_cart()));
    return $checkout_request && tiron_cart_requires_delivery_phone() ? 'required' : $value;
});

add_filter('woocommerce_checkout_fields', static function ($fields) {
    if (!tiron_cart_requires_delivery_phone()) {
        return $fields;
    }
    unset($fields['shipping']['shipping_phone']);
    // A legacy Romanian checkout editor can register the same plain "Telefon"
    // field under another key. Preserve differentiated fields such as intercom or fax.
    foreach ($fields['billing'] as $key => $field) {
        if ($key !== 'billing_phone' && in_array($field['type'] ?? 'text', ['tel', 'text'], true)
            && in_array(sanitize_title($field['label'] ?? ''), ['telefon', 'numar-de-telefon', 'phone', 'phone-number'], true)) {
            unset($fields['billing'][$key]);
        }
    }
    $phone = $fields['billing']['billing_phone'] ?? [];
    $fields['billing']['billing_phone'] = array_merge($phone, [
        'label'        => 'Telefon pentru livrare',
        'type'         => 'tel',
        'required'     => true,
        'hidden'       => false,
        'autocomplete' => 'section-billing billing tel',
        'class'        => ['form-row-wide'],
        'validate'     => array_unique(array_merge($phone['validate'] ?? [], ['phone'])),
        'priority'     => 8,
    ]);
    if (isset($fields['billing']['billing_email'])) {
        $fields['billing']['billing_email']['priority'] = 5;
    }
    return $fields;
}, 100);

// Country changes must not reset the courier contact's label, position or required state.
add_filter('woocommerce_country_locale_field_selectors', static function ($selectors) {
    if (is_checkout() && tiron_cart_requires_delivery_phone()) {
        unset($selectors['phone']);
    }
    return $selectors;
});

add_filter('woocommerce_checkout_required_field_notice', static function ($notice, $label, $key) {
    return $key === 'billing_phone' && tiron_cart_requires_delivery_phone()
        ? 'Introdu numărul de telefon pentru livrare.' : $notice;
}, 10, 3);

add_action('woocommerce_checkout_create_order', static function ($order, $data) {
    if (tiron_cart_requires_delivery_phone() && !empty($data['billing_phone'])) {
        // WC has already sanitised and validated this field when creating the order.
        $order->set_shipping_phone($data['billing_phone']);
    }
}, 10, 2);

function tiron_checkout_has_optional_notes_only($checkout): bool {
    $fields = $checkout->get_checkout_fields('order');
    return array_keys($fields) === ['order_comments'] && empty($fields['order_comments']['required']);
}
add_action('woocommerce_before_order_notes', static function ($checkout) {
    if (tiron_checkout_has_optional_notes_only($checkout)) {
        $open = $checkout->get_value('order_comments') ? ' open' : '';
        echo '<details class="tiron-delivery-notes"' . $open . '><summary>Instrucțiuni de livrare <small>(opțional)</small></summary>';
    }
}, 99);
add_action('woocommerce_after_order_notes', static function ($checkout) {
    if (tiron_checkout_has_optional_notes_only($checkout)) {
        echo '</details>';
    }
}, 1);

add_action('woocommerce_checkout_before_order_review_heading', static function () {
    echo '<section class="tiron-order-summary" aria-labelledby="order_review_heading">';
}, 99);
add_action('woocommerce_checkout_after_order_review', static function () {
    echo '</section>';
}, 1);
