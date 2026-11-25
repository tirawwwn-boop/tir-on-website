<?php
/** Run with WP-CLI after commerce_smoke.py, on a disposable local site. */
defined( 'ABSPATH' ) || exit;
if ( 'local' !== wp_get_environment_type() || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	throw new RuntimeException( 'Testul este disponibil numai în instalarea locală.' );
}
$order = wc_get_order( (int) ( $args[0] ?? 0 ) );
if ( ! $order || 'tiron_local_test' !== $order->get_payment_method() || 'yes' !== $order->get_meta( '_tiron_local_simulation' ) ) {
	throw new RuntimeException( 'Comanda nu este o comandă simulată.' );
}
$items = $order->get_items();
$item = reset( $items );
$photo = $item ? tiron_photo_path( $item->get_meta( '_tiron_photo_id' ) ) : false;
if ( count( $items ) !== 1 || (int) $item->get_quantity() !== 2 || (float) $order->get_total() !== 498.0
	|| 'RON' !== $order->get_currency() || 0 !== $order->get_customer_id()
	|| 'on-hold' !== $order->get_status() || 'tiron-qa@example.invalid' !== $order->get_billing_email()
	|| 'București' !== $order->get_shipping_city() || '010101' !== $order->get_shipping_postcode()
	|| 'Test automat TIR-ON: imagine sintetică.' !== $item->get_meta( '_tiron_notes' )
	|| ! $photo || strpos( $photo, tiron_photo_root() . '/orders/' ) !== 0
	|| IMAGETYPE_JPEG !== ( getimagesize( $photo )[2] ?? false ) ) {
	throw new RuntimeException( 'Comanda nu respectă datele scenariului de regresie.' );
}
echo "PASS: date de contact și livrare, total, observații, fotografie privată JPEG\n";
