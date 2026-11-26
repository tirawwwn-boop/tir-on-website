<?php
/** Run only through tests/v6-launch.py in its disposable, offline WooCommerce fixture. */
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type() !== 'local') exit(1);
function v6_check($condition, $name) {
    if (!$condition) throw new RuntimeException($name);
}
// Exercise Woo Stripe's actual header builder against the TIR-ON policy, without a network call.
WC_Stripe_API::set_secret_key('sk_test_SyntheticOnly');
$url = 'https://api.stripe.com/v1/payment_intents';
v6_check(false === apply_filters('pre_http_request', false, ['headers'=>WC_Stripe_API::get_headers()], $url), 'official test header rejected');
WC_Stripe_API::set_secret_key('unsupported-synthetic-key');
$blocked = apply_filters('pre_http_request', false, ['headers'=>WC_Stripe_API::get_headers()], $url);
v6_check(is_wp_error($blocked) && $blocked->get_error_code() === 'tiron_test_credentials_only', 'wrong-mode guard');
$called = [];
add_filter('pre_http_request', static function ($pre, $args, $url) use (&$called) {
    $body = json_decode($args['body'] ?? '', true);
    $called[] = $body;
    return ['headers' => [], 'response' => ['code' => 200], 'body' => wp_json_encode(['events_received'=>1, 'code'=>0])];
}, PHP_INT_MAX, 3);
$options = ['home_description'=>'', 'luna_description'=>'', 'from' => '2026-12-01','until' => '2026-12-24','christmas' => 'Mesaj sintetic','meta' => '123456789','tiktok' => 'TESTPIXEL123456789'];
v6_check(tiron_clean_commercial($options)['christmas'] === 'Mesaj sintetic','valid settings');
update_option('tiron_commercial_v6', $options);
v6_check(tiron_clean_commercial(array_merge($options, ['from'=>'2026-02-31'])) === $options, 'reject invalid date');
v6_check(!tiron_pixel_ids(), 'default off even with IDs');
define('TIRON_MARKETING_APPROVED', true);
foreach (['META','TIKTOK'] as $vendor) {
    define("TIRON_{$vendor}_TOKEN",'synthetic');
    define("TIRON_{$vendor}_TEST_EVENT_CODE",'SYNTHETIC');
}
$order = wc_create_order();
$order->set_total('249.00');
$order->set_currency('RON');
$order->save();
tiron_measure_order($order, str_repeat('a',32));
v6_check(!$called, 'unpaid order ignored');
$order->set_status('processing');
$order->set_date_paid(time());
$order->save();
tiron_measure_order($order, str_repeat('a',32));
tiron_measure_order(wc_get_order($order->get_id()), str_repeat('a',32));
v6_check(count($called) === 2, 'one acknowledged purchase per provider');
v6_check($called[0]['data'][0]['event_id'] === $called[1]['data'][0]['event_id'], 'stable event id');
v6_check($called[0]['data'][0]['custom_data'] === ['currency'=>'RON','value'=>249], 'paid order value');
$registered = apply_filters('woocommerce_shipping_methods', []);
v6_check(isset($registered['tiron_ro']), 'shipping type');
$package = ['contents'=>[], 'destination'=>['country'=>'RO']];
function v6_rates($cost, $package) {
    update_option('woocommerce_tiron_ro_98_settings', ['cost'=>$cost,'estimate'=>'Termen sintetic','tax_status'=>'none']);
    $method = new Tiron_RO_Delivery(98);
    $method->calculate_shipping($package);
    return array_values($method->rates);
}
v6_check(!v6_rates('', $package) && !v6_rates('-1', $package), 'absent and invalid rate');
v6_check(count(v6_rates('0', $package)) === 1, 'explicit free rate');
$rates = v6_rates('17.25', $package);
v6_check(count($rates) === 1 && (float)$rates[0]->get_cost() === 17.25 && str_contains($rates[0]->get_label(), 'Termen sintetic'), 'RO rate');
$package['destination']['country'] = 'HU';
v6_check(!v6_rates('17.25', $package), 'destination enforcement');
echo 'PASS settings, test-only purchase deduplication (mock HTTP), RO shipping rates';
