<?php
/**
 * Plugin Name: TIR-ON — Previzualizare locală
 * Description: Dezactivează cumpărăturile, plățile și e-mailurile în proiectul de evaluare vizuală.
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'woocommerce_is_purchasable', '__return_false', PHP_INT_MAX );
add_filter( 'woocommerce_variation_is_purchasable', '__return_false', PHP_INT_MAX );
add_filter( 'woocommerce_available_payment_gateways', '__return_empty_array', PHP_INT_MAX );
add_filter( 'woocommerce_add_to_cart_validation', '__return_false', PHP_INT_MAX );
add_filter( 'pre_wp_mail', '__return_true', PHP_INT_MAX );

add_action(
	'woocommerce_checkout_process',
	static function () {
		wc_add_notice( 'Comenzile vor fi disponibile după deschiderea magazinului.', 'error' );
	}
);

// The block checkout can create a draft order even on a GET request.
add_filter(
	'rest_pre_dispatch',
	static function ( $response, $server, $request ) {
		if ( preg_match( '~^/wc/store(?:/v[0-9]+)?/(?:checkout(?:/|$)|cart/(?:add-item|update-item)(?:/|$))~', $request->get_route() ) ) {
			return new WP_Error( 'tiron_preview_only', 'Comenzile vor fi disponibile după deschiderea magazinului.', array( 'status' => 403 ) );
		}
		return $response;
	},
	10,
	3
);
