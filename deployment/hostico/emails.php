<?php
/** WooCommerce email settings for a fresh Hostico installation. */
defined( 'ABSPATH' ) || exit;

function tiron_hostico_seed_emails(): void {
	$options = array(
		'woocommerce_email_from_name'             => 'TIR-ON',
		'woocommerce_email_from_address'          => 'comenzi@tir-on.ro',
		'woocommerce_email_background_color'      => '#f5f1ea',
		'woocommerce_email_body_background_color' => '#fffdf9',
		'woocommerce_email_base_color'            => '#dfc199',
		'woocommerce_email_text_color'            => '#241d18',
		'woocommerce_email_footer_text_color'     => '#5e554b',
		'woocommerce_email_footer_text'           => 'TIR-ON · Pentru întrebări despre comandă, scrie-ne la contact@tir-on.ro.',
	);
	foreach ( $options as $key => $value ) {
		update_option( $key, $value );
	}

	// Native templates keep WooCommerce's order tables, plain text and email client compatibility.
	$messages = array(
		'customer_on_hold_order'    => array( 'Am primit comanda #{order_number}', 'Am primit comanda ta', 'Îți mulțumim că ai ales Luna. Te anunțăm când comanda trece în etapa următoare.' ),
		'customer_processing_order' => array( 'Comanda #{order_number} este în lucru', 'Luna ta este în lucru', 'Am confirmat plata. Pregătim Luna după fotografia pe care ai ales-o.' ),
		'customer_completed_order'  => array( 'Am finalizat comanda #{order_number}', 'Comanda ta a fost finalizată', 'Îți mulțumim că ai ales să păstrezi o amintire în lumină.' ),
		'customer_cancelled_order'  => array( 'Comanda #{order_number} a fost anulată', 'Comanda a fost anulată', 'Dacă ai întrebări despre această comandă, suntem la contact@tir-on.ro.' ),
		'customer_failed_order'     => array( 'Plata comenzii #{order_number} nu s-a confirmat', 'Plata nu s-a confirmat', 'Poți reîncerca din pagina comenzii, dacă opțiunea este disponibilă. Scrie-ne dacă ai nevoie de ajutor.' ),
		'new_order'                 => array( 'Comandă nouă #{order_number} · TIR-ON', 'Comandă nouă', 'Verifică starea plății și personalizarea din administrarea WooCommerce.' ),
	);
	foreach ( $messages as $id => list( $subject, $heading, $content ) ) {
		$key = 'woocommerce_' . $id . '_settings';
		$settings = (array) get_option( $key, array() );
		$settings['subject'] = $subject;
		$settings['heading'] = $heading;
		$settings['additional_content'] = $content;
		$settings['enabled'] = 'yes';
		if ( 'new_order' === $id ) {
			$settings['recipient'] = 'comenzi@tir-on.ro';
		}
		update_option( $key, $settings );
	}
}
