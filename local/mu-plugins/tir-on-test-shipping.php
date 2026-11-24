<?php
/**
 * Plugin Name: TIR-ON — Livrare locală de test
 * Description: Limitează simularea livrării la mediul local de test.
 */
defined( 'ABSPATH' ) || exit;

function tiron_local_shipping_test_enabled() {
	return wp_get_environment_type() === 'local' && defined( 'TIRON_ENABLE_TEST_CHECKOUT' ) && TIRON_ENABLE_TEST_CHECKOUT === true;
}

add_filter( 'woocommerce_shipping_flat_rate_is_available', static function ( $available, $package, $method ) {
	$instance = (int) get_option( 'tiron_test_shipping_instance', 0 );
	if ( $instance && $instance === $method->get_instance_id() ) {
		return $available && tiron_local_shipping_test_enabled() && ( $package['destination']['country'] ?? '' ) === 'RO';
	}
	return $available;
}, PHP_INT_MAX, 3 );

// Running a development checkout never modifies the merchant's tax configuration.
add_filter( 'pre_option_woocommerce_calc_taxes', static function ( $value ) {
	return tiron_local_shipping_test_enabled() ? 'no' : $value;
} );

// Include the mode in WooCommerce's package hash so a cached simulated rate
// cannot survive switching checkout mode or moving this database.
add_filter( 'woocommerce_cart_shipping_packages', static function ( $packages ) {
	foreach ( $packages as &$package ) {
		$package['tiron_local_test'] = tiron_local_shipping_test_enabled();
	}
	return $packages;
} );
