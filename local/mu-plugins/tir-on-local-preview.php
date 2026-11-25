<?php
/**
 * Plugin Name: TIR-ON — Fotografii private și checkout de test
 * Description: Încarcă personalizarea comenzilor și menține integrarea Stripe în modul de test.
 */
defined( 'ABSPATH' ) || exit;

$tiron_ready = true;
foreach ( array( 'private-photos', 'cart-orders', 'admin-photos', 'test-payments' ) as $tiron_module ) {
    $tiron_path = __DIR__ . '/tiron/' . $tiron_module . '.php';
    if ( is_readable( $tiron_path ) ) {
        require_once $tiron_path;
    } else {
        $tiron_ready = false;
    }
}
if ( ! $tiron_ready ) {
    add_filter( 'woocommerce_is_purchasable', '__return_false', PHP_INT_MAX );
    add_filter( 'woocommerce_variation_is_purchasable', '__return_false', PHP_INT_MAX );
    add_filter( 'woocommerce_available_payment_gateways', '__return_empty_array', PHP_INT_MAX );
    add_filter( 'pre_http_request', static function ( $pre, $args, $url ) {
        return 'api.stripe.com' === strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) )
            ? new WP_Error( 'tiron_incomplete_install', 'Integrarea Stripe este indisponibilă.' )
            : $pre;
    }, PHP_INT_MAX, 3 );
    add_action( 'woocommerce_api_wc_stripe', static function () {
        status_header( 503 );
        exit;
    }, -1 );
    add_action( 'admin_notices', static function () {
        if ( current_user_can( 'manage_options' ) ) {
            echo '<div class="notice notice-error"><p>TIR-ON: pachetul MU este incomplet. Reinstalează toate fișierele înainte de a testa comenzile.</p></div>';
        }
    } );
}
if ( $tiron_ready && is_readable( __DIR__ . '/tiron/atelier.php' ) ) {
    require_once __DIR__ . '/tiron/atelier.php';
}
unset( $tiron_ready, $tiron_module, $tiron_path );
