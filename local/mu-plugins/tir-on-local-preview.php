<?php
/**
 * Plugin Name: TIR-ON — Fotografii private și checkout de test
 * Description: Încarcă personalizarea comenzilor și menține integrarea Stripe în modul de test.
 */
defined( 'ABSPATH' ) || exit;

$tiron_ready = true;
foreach ( array( 'private-photos', 'cart-orders', 'admin-photos' ) as $tiron_module ) {
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
}
unset( $tiron_ready, $tiron_module, $tiron_path );

require_once __DIR__ . '/tiron/test-payments.php';
