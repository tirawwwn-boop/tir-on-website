<?php
/** Optional marketing; no vendor script on checkout, receipts or account pages. */
defined('ABSPATH') || exit;

function tiron_pixel_ids(): array {
    if (!defined('TIRON_MARKETING_APPROVED') || TIRON_MARKETING_APPROVED !== true || !function_exists('tiron_commercial_options')) return [];
    $settings = tiron_commercial_options();
    return array_filter([
        'meta' => preg_match('/^[0-9]{5,24}$/D', $settings['meta'] ?? '') ? $settings['meta'] : '',
        'tiktok' => preg_match('/^[A-Z0-9]{10,32}$/D', $settings['tiktok'] ?? '') ? $settings['tiktok'] : '',
    ]);
}

add_action('wp_footer', static function () {
    $ids = tiron_pixel_ids();
    if (!$ids || is_admin() || is_customize_preview() || is_user_logged_in() && current_user_can('manage_woocommerce')) return;
    $catalog = is_front_page();
    $config = ['ids' => $ids, 'catalog' => $catalog];
    echo '<details id="tiron-marketing" class="marketing-choice"><summary>Preferințe de confidențialitate</summary><p>Permiți Meta și TikTok să măsoare vizitele și achizițiile pentru publicitate? Alegerea este opțională și poate fi schimbată aici.</p><button type="button" data-choice="yes">Accept măsurarea</button> <button type="button" data-choice="no">Refuz măsurarea</button><p role="status"></p></details>';
    wp_enqueue_script('tiron-marketing', t_asset('marketing.js'), [], '6.0', true);
    wp_add_inline_script('tiron-marketing', 'window.tironMarketing=' . wp_json_encode($config, JSON_HEX_TAG) . ';', 'before');
}, 9);

function tiron_purchase_nonce_action($order): string {
    return 'tiron_measure_' . $order->get_id() . '_' . $order->get_order_key();
}
add_action('woocommerce_thankyou', static function ($id) {
    if (!tiron_pixel_ids()) return;
    $order = wc_get_order($id);
    if (!$order || !$order->is_paid() || !$order->get_order_key() || !is_string($_GET['key'] ?? null)
        || !hash_equals($order->get_order_key(), wp_unslash($_GET['key']))
        || ($order->get_customer_id() && $order->get_customer_id() !== get_current_user_id())) return;
    echo '<span hidden id="tiron-conversion" data-url="' . esc_url(WC_AJAX::get_endpoint('tiron_measure')) . '" data-id="' . esc_attr($id) . '" data-proof="' . esc_attr(wp_create_nonce(tiron_purchase_nonce_action($order))) . '"></span>';
});

// Receipt events are sent server-side so third-party scripts never see order URLs or checkout fields.
// V6 processes test payments exclusively. Both vendors must route purchases to their test tools.
function tiron_measure_order($order, string $anonymous_id): void {
    $paid = $order->get_date_paid();
    if (!$order->is_paid() || !$paid || $paid->getTimestamp() < time() - 7 * DAY_IN_SECONDS) return;
    $event = hash_hmac('sha256', 'purchase:' . $order->get_id(), wp_salt('auth'));
    $user = hash('sha256', $anonymous_id);
    $checkout_url = strtok(wc_get_checkout_url(), '?');
    $amount = ['currency' => $order->get_currency(), 'value' => (float)$order->get_total()];
    foreach (tiron_pixel_ids() as $provider => $pixel) {
        $prefix = 'TIRON_' . strtoupper($provider);
        $token = defined($prefix . '_TOKEN') ? constant($prefix . '_TOKEN') : '';
        $test = defined($prefix . '_TEST_EVENT_CODE') ? constant($prefix . '_TEST_EVENT_CODE') : '';
        $meta = '_tiron_conversion_' . $provider . '_' . $pixel;
        if (!$token || !$test || $order->get_meta($meta)) continue;
        if ($provider === 'meta') {
            $url = 'https://graph.facebook.com/v23.0/' . $pixel . '/events';
            $headers = ['Authorization' => 'Bearer ' . $token];
            $body = ['test_event_code' => $test, 'data' => [[
                'event_name' => 'Purchase', 'event_time' => $paid->getTimestamp(), 'event_id' => $event,
                'action_source' => 'website', 'event_source_url' => $checkout_url, 'user_data' => ['external_id' => [$user]], 'custom_data' => $amount,
            ]]];
        } else {
            $url = 'https://business-api.tiktok.com/open_api/v1.3/event/track/';
            $headers = ['Access-Token' => $token];
            $body = ['event_source' => 'web', 'event_source_id' => $pixel, 'test_event_code' => $test, 'data' => [[
                'event' => 'Purchase', 'event_time' => $paid->getTimestamp(), 'event_id' => $event,
                'user' => ['external_id' => $user], 'page' => ['url' => $checkout_url], 'properties' => $amount,
            ]]];
        }
        $headers['Content-Type'] = 'application/json';
        $reply = wp_remote_post($url, ['timeout' => 4, 'redirection' => 0, 'headers' => $headers, 'body' => wp_json_encode($body)]);
        $result = json_decode(wp_remote_retrieve_body($reply), true);
        if (wp_remote_retrieve_response_code($reply) === 200 &&
            ($provider === 'meta' ? ($result['events_received'] ?? 0) === 1 : ($result['code'] ?? null) === 0)) {
            $order->update_meta_data($meta, $event);
            $order->save_meta_data();
        }
    }
}

add_action('wc_ajax_tiron_measure', static function () {
    $id = $_POST['id'] ?? null;
    $proof = $_POST['proof'] ?? null;
    $anon = $_COOKIE['tiron_ad_id'] ?? '';
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !tiron_pixel_ids() || ($_SERVER['HTTP_SEC_GPC'] ?? '') === '1'
        || ($_COOKIE['tiron_marketing'] ?? '') !== 'yes' || !is_string($anon) || !preg_match('/^[a-f0-9]{32}$/D', $anon)
        || !is_string($id) || !ctype_digit($id) || !is_string($proof)) wp_send_json_error(null, 403);
    $order = wc_get_order((int)$id);
    if (!$order || !$order->get_order_key() || !wp_verify_nonce($proof, tiron_purchase_nonce_action($order))
        || ($order->get_customer_id() && $order->get_customer_id() !== get_current_user_id())) wp_send_json_error(null, 403);
    tiron_measure_order($order, $anon);
    wp_send_json_success();
});
