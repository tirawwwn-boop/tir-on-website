<?php
/** Provision a simulated Romanian shipping option on this local development store. */
defined( 'ABSPATH' ) || exit;
if ( wp_get_environment_type() !== 'local' || ! defined( 'TIRON_ENABLE_TEST_CHECKOUT' ) || TIRON_ENABLE_TEST_CHECKOUT !== true ) {
	return;
}
if ( ! get_option( 'tiron_local_seed_v1' ) || get_option( 'tiron_test_shipping_instance' ) ) {
	return;
}

$zone_id = (int) get_option( 'tiron_test_shipping_zone' );
if ( ! $zone_id ) {
	// Preserve any shipping rules the owner configured since the last project stage.
	$default_zone = new WC_Shipping_Zone( 0 );
	if ( WC_Shipping_Zones::get_zones() || $default_zone->get_shipping_methods() ) {
		echo "Configurația de livrare existentă a fost păstrată.\n";
		return;
	}
	$zone = new WC_Shipping_Zone();
	$zone->set_zone_name( 'România — test local TIR-ON' );
	$zone->add_location( 'RO', 'country' );
	$zone_id = $zone->save();
	if ( ! $zone_id ) {
		throw new RuntimeException( 'Zona de livrare locală nu a putut fi pregătită.' );
	}
	update_option( 'tiron_test_shipping_zone', $zone_id, false );
}
$zone = WC_Shipping_Zones::get_zone( $zone_id );
if ( ! $zone || $zone->get_id() !== $zone_id ) {
	throw new RuntimeException( 'Verifică zona de livrare în WooCommerce.' );
}
// A retry may follow an interruption between zone creation and the settings save.
$methods = $zone->get_shipping_methods();
if ( $methods ) {
	$method = reset( $methods );
	if ( count( $methods ) !== 1 || $method->id !== 'flat_rate' ) {
		return;
	}
	$instance = $method->get_instance_id();
} else {
	$instance = $zone->add_shipping_method( 'flat_rate' );
}
if ( ! $instance ) {
	throw new RuntimeException( 'Metoda de livrare simulată nu a putut fi inițializată.' );
}
update_option(
	'woocommerce_flat_rate_' . $instance . '_settings',
	array( 'title' => 'Livrare simulată pentru test', 'tax_status' => 'none', 'cost' => '0' )
);
update_option( 'tiron_test_shipping_instance', (int) $instance, false );
WC_Cache_Helper::get_transient_version( 'shipping', true );
echo "Livrarea simulată pentru România este configurată.\n";
