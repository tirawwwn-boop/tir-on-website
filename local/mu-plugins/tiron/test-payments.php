<?php
/**
 * Payment policy for this pre-launch release. Official Stripe Elements owns card
 * entry, eligibility and wallet rendering. Only test-mode keys may reach Stripe.
 */
defined( 'ABSPATH' ) || exit;

function tiron_local_test_checkout_enabled() {
    return 'local' === wp_get_environment_type()
        && defined( 'TIRON_ENABLE_TEST_CHECKOUT' )
        && true === TIRON_ENABLE_TEST_CHECKOUT;
}

if ( 'local' === wp_get_environment_type() ) {
    add_filter( 'pre_wp_mail', '__return_true', PHP_INT_MAX );
}

function tiron_stripe_test_keys_present( $settings ) {
    return is_string( $settings['test_publishable_key'] ?? null )
        && preg_match( '/^pk_test_[A-Za-z0-9]+$/D', $settings['test_publishable_key'] )
        && is_string( $settings['test_secret_key'] ?? null )
        && preg_match( '/^[rs]k_test_[A-Za-z0-9]+$/D', $settings['test_secret_key'] );
}

/** Apply on reads as well as writes, including legacy options imported from another installation. */
function tiron_test_stripe_settings( $settings ) {
    $settings = is_array( $settings ) ? $settings : array();
    $settings['testmode'] = 'yes';
    foreach ( array( 'secret_key', 'publishable_key', 'webhook_secret' ) as $field ) {
        $settings[ $field ] = '';
    }
    foreach ( array( 'test_secret_key' => '/^[rs]k_test_[A-Za-z0-9]+$/D', 'test_publishable_key' => '/^pk_test_[A-Za-z0-9]+$/D' ) as $field => $prefix ) {
        if ( ! is_string( $settings[ $field ] ?? null ) || ! preg_match( $prefix, $settings[ $field ] ) ) {
            $settings[ $field ] = '';
        }
    }
    if ( ! tiron_stripe_test_keys_present( $settings ) ) {
        $settings['enabled'] = 'no';
    }
    $settings['saved_cards'] = 'no';
    $settings['optimized_checkout_element'] = 'no';
    $settings['upe_checkout_experience_accepted_payments'] = array( 'card', 'link' );
    foreach ( array( 'express_checkout_button_locations', 'link_button_locations' ) as $key ) {
        $locations = $settings[ $key ] ?? array( 'cart', 'checkout' );
        $settings[ $key ] = array_values( array_intersect( (array) $locations, array( 'cart', 'checkout' ) ) );
    }
    $settings['amazon_pay_button_locations'] = array();
    return $settings;
}
foreach ( array( 'option_', 'default_option_', 'sanitize_option_' ) as $tiron_option_hook ) {
    add_filter( $tiron_option_hook . 'woocommerce_stripe_settings', 'tiron_test_stripe_settings', PHP_INT_MAX );
}
unset( $tiron_option_hook );

/** A wrong-mode request must also fail if an extension bypasses the gateway selector. */
add_filter( 'pre_http_request', static function ( $pre, $args, $url ) {
    if ( 'api.stripe.com' !== strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) ) {
        return $pre;
    }
    $headers = array_change_key_case( (array) ( $args['headers'] ?? array() ), CASE_LOWER );
    $auth    = (string) ( $headers['authorization'] ?? '' );
    if ( preg_match( '/^Basic (.+)$/iD', $auth, $match ) ) {
        $decoded = base64_decode( $match[1], true );
        $key = is_string( $decoded ) ? explode( ':', $decoded, 2 )[0] : '';
    } elseif ( preg_match( '/^Bearer (.+)$/iD', $auth, $match ) ) {
        $key = $match[1];
    } else {
        $key = '';
    }
    if ( 'https' !== wp_parse_url( $url, PHP_URL_SCHEME ) || ! preg_match( '/^[rs]k_test_[A-Za-z0-9]+$/D', $key ) ) {
        return new WP_Error( 'tiron_test_credentials_only', 'Magazinul acceptă exclusiv conexiuni Stripe de test.' );
    }
    return $pre;
}, PHP_INT_MAX, 3 );

function tiron_stripe_test_available() {
    $settings = get_option( 'woocommerce_stripe_settings', array() );
    if ( 'yes' !== ( $settings['enabled'] ?? 'no' ) || ! tiron_stripe_test_keys_present( $settings )
        || ! class_exists( 'WC_Stripe_Mode' ) || ! WC_Stripe_Mode::is_test() ) {
        return false;
    }
    // The official plugin caches the response to its authenticated account API call.
    $account = WC_Stripe::get_instance()->account->get_cached_account_data( 'test' );
    return ! empty( $account['id'] ) && ! empty( $account['charges_enabled'] );
}

function tiron_test_payment_method_allowed( $id ) {
    return ( 'tiron_local_test' === $id && tiron_local_test_checkout_enabled() )
        || ( 'stripe' === $id && tiron_stripe_test_available() );
}

add_filter( 'woocommerce_payment_gateways', static function ( $gateways ) {
    if ( tiron_local_test_checkout_enabled() ) {
        require_once __DIR__ . '/local-test-gateway.php';
        $gateways[] = 'Tiron_Local_Test_Gateway';
    }
    return $gateways;
} );
add_filter( 'woocommerce_available_payment_gateways', static function ( $gateways ) {
    foreach ( $gateways as $id => $gateway ) {
        if ( ! tiron_test_payment_method_allowed( $id )
            || ( 'stripe' === $id && ! ( $gateway instanceof WC_Stripe_UPE_Payment_Gateway ) )
            || ( 'tiron_local_test' === $id && ! ( $gateway instanceof Tiron_Local_Test_Gateway ) ) ) {
            unset( $gateways[ $id ] );
        }
    }
    return $gateways;
}, PHP_INT_MAX );

add_action( 'woocommerce_after_checkout_validation', static function ( $data, $errors ) {
    if ( ! empty( $data['payment_method'] ) && ! tiron_test_payment_method_allowed( $data['payment_method'] ) ) {
        $errors->add( 'tiron_test_method', 'Alege o metodă de plată de test disponibilă.' );
    }
}, 10, 2 );

add_filter( 'rest_pre_dispatch', static function ( $result, $server, $request ) {
    if ( 'POST' === $request->get_method() && preg_match( '~^/wc/store(?:/v\d+)?/checkout(?:/\d+)?/?$~D', $request->get_route() ) ) {
        $method = $request->get_param( 'payment_method' );
        if ( ! empty( $method ) && ! tiron_test_payment_method_allowed( $method ) ) {
            return new WP_Error( 'tiron_test_method', 'Alege o metodă de plată de test disponibilă.', array( 'status' => 400 ) );
        }
    }
    return $result;
}, 8, 3 );

// Express payment on the cart or checkout uses the existing, personalized cart.
add_action( 'wc_ajax_wc_stripe_add_to_cart', static function () {
    wp_send_json_error( array( 'message' => 'Adaugă produsul din pagina de personalizare.' ), 400 );
}, 0 );
