<?php
/**
 * Plugin Name: TIR-ON — Detalii de atelier
 * Description: Câmpurile de produs configurabile ale atelierului TIR-ON.
 */

defined( 'ABSPATH' ) || exit;

function tiron_atelier_fields() {
	return array(
		'_tiron_diameter'   => 'Diametrul litofanului',
		'_tiron_power'      => 'Alimentare',
		'_tiron_light'      => 'Sursă de lumină',
		'_tiron_finish'     => 'Finisaj',
		'_tiron_packaging'  => 'Descrierea ambalajului',
		'_tiron_production' => 'Termen estimat de producție',
	);
}

add_action(
	'woocommerce_product_options_general_product_data',
	static function () {
		echo '<div class="options_group"><p><strong>' . esc_html( 'Detalii de atelier TIR-ON' ) . '</strong></p>';
		foreach ( tiron_atelier_fields() as $key => $label ) {
			woocommerce_wp_text_input( array( 'id' => $key, 'label' => $label ) );
		}
		echo '</div>';
	}
);

add_action(
	'woocommerce_admin_process_product_object',
	static function ( $product ) {
		// WooCommerce verifies the product-data nonce before invoking this save hook.
		if ( ! current_user_can( 'edit_post', $product->get_id() ) ) {
			return;
		}
		foreach ( array_keys( tiron_atelier_fields() ) as $key ) {
			if ( isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ) {
				$product->update_meta_data( $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
			}
		}
	}
);
