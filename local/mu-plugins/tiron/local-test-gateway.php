<?php
defined( 'ABSPATH' ) || exit;

/** A no-network simulator for local checkout, with an actual WooCommerce order. */
final class Tiron_Local_Test_Gateway extends WC_Payment_Gateway {
    public function __construct() {
        $this->id                 = 'tiron_local_test';
        $this->method_title       = 'TIR-ON · Test local';
        $this->method_description = 'Simulează finalizarea comenzii exclusiv în mediul local de test.';
        $this->title              = 'Simulare de plată · test local';
        $this->description        = 'Comanda este înregistrată pentru testare. Nu se debitează bani și nu se expediază produse.';
        $this->enabled            = 'yes';
        $this->has_fields         = false;
        $this->supports           = array( 'products' );
    }

    public function is_available() {
        return tiron_local_test_checkout_enabled() && parent::is_available();
    }

    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $this->is_available() || ! $order || $this->id !== $order->get_payment_method() ) {
            throw new RuntimeException( 'Simularea locală nu este disponibilă pentru această comandă.' );
        }
        if ( $order->has_status( array( 'pending', 'failed' ) ) ) {
            $order->update_meta_data( '_tiron_local_simulation', 'yes' );
            $order->update_status( 'on-hold', 'Comandă locală de test. Plata este simulată. Comanda nu se expediază.' );
        }
        if ( WC()->cart ) {
            WC()->cart->empty_cart();
        }
        return array( 'result' => 'success', 'redirect' => $this->get_return_url( $order ) );
    }
}
