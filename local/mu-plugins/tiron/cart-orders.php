<?php
/**
 * Guest personalization, WooCommerce cart validation and private order-line references.
 */
defined( 'ABSPATH' ) || exit;

function tiron_requires_photo( $product_id ) {
    $id = (int) get_option( 'tiron_luna_product_id', 0 );
    if ( ! $id ) {
        $id = (int) ( get_page_by_path( 'luna', OBJECT, 'product' )->ID ?? 0 );
    }
    return $id > 0 && $id === (int) $product_id;
}

/** A shared cart link copies the cart, but WooCommerce keeps this secret with its original session. */
function tiron_photo_session_secret( $create = false ) {
    $session = WC()->session;
    if ( ! $session ) {
        return '';
    }
    $secret = (string) $session->get( 'tiron_photo_secret', '' );
    if ( '' === $secret && $create ) {
        $secret = bin2hex( random_bytes( 32 ) );
        $session->set( 'tiron_photo_secret', $secret );
    }
    return $secret;
}

function tiron_nonce_action( $intent ) {
    return 'woocommerce_tiron_personalize_' . WC()->session->get_customer_id() . '_' . $intent;
}

/** A signed, per-render submission identifier for CSRF protection and POST retries. */
function tiron_personalization_nonce() {
    if ( ! function_exists( 'WC' ) || ! WC()->session || ! WC()->session->has_session() ) {
        return '';
    }
    $intent = bin2hex( random_bytes( 16 ) );
    return $intent . '.' . wp_create_nonce( tiron_nonce_action( $intent ) );
}

function tiron_photo_signature( $product_id, $line ) {
    $secret = tiron_photo_session_secret();
    if ( '' === $secret || ! is_string( $line['_tiron_stage'] ?? null ) ||
        ! is_string( $line['_tiron_intent'] ?? null ) || ! is_string( $line['_tiron_notes'] ?? null ) ) {
        return '';
    }
    return hash_hmac( 'sha256', wp_json_encode( [
        (int) $product_id, $line['_tiron_stage'], $line['_tiron_intent'], $line['_tiron_notes'],
    ] ), $secret );
}

function tiron_valid_photo_line( $line, $product_id ) {
    $expected = tiron_photo_signature( $product_id, $line );
    if ( '' === $expected || ! is_string( $line['_tiron_signature'] ?? null ) || ! hash_equals( $expected, $line['_tiron_signature'] ) ) {
        return false;
    }
    $path = tiron_photo_path( $line['_tiron_stage'] );
    return is_string( $path ) && is_file( $path );
}

function tiron_cart_error( $message ) {
    if ( ! wc_has_notice( $message, 'error' ) ) {
        wc_add_notice( $message, 'error' );
    }
}

function tiron_photo_required_message() {
    return 'Pentru TIR-ON Luna, încarcă fotografia pe pagina produsului.';
}

function tiron_photo_expired_message() {
    return 'Fotografia pentru TIR-ON Luna nu mai este disponibilă. Reîncarcă fotografia pe pagina produsului.';
}

function tiron_valid_luna_quantity( $value, $product ) {
    if ( ! is_scalar( $value ) || ! preg_match( '/^[1-9][0-9]?$/D', (string) $value ) ) {
        return false;
    }
    $maximum = $product->get_max_purchase_quantity();
    return (int) $value >= $product->get_min_purchase_quantity() && ( $maximum < 0 || (int) $value <= $maximum );
}

/** On success return true. WC's stock/extension validation may supply its own notices. */
function tiron_process_personalization( $product_id ) {
    $token = $_POST['tiron_nonce'] ?? '';
    if ( ! is_string( $token ) || ! preg_match( '/^([a-f0-9]{32})\.([a-zA-Z0-9]{10})$/D', $token, $matches ) ||
        ! wp_verify_nonce( $matches[2], tiron_nonce_action( $matches[1] ) ) ) {
        return new WP_Error( 'tiron_session', 'Sesiunea formularului a expirat. Alege din nou fotografia și trimite formularul.' );
    }
    if ( (string) $product_id !== ( $_POST['product_id'] ?? '' ) ) {
        return new WP_Error( 'tiron_product', 'Produsul selectat nu este disponibil.' );
    }
    $intent = $matches[1];
    foreach ( WC()->cart->get_cart() as $line ) {
        if ( ( $line['_tiron_intent'] ?? '' ) === $intent && tiron_valid_photo_line( $line, $product_id ) ) {
            return true;
        }
    }
    $product = wc_get_product( $product_id );
    if ( ! $product || 'publish' !== $product->get_status() || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
        return new WP_Error( 'tiron_availability', 'Produsul nu este disponibil pentru comandă.' );
    }
    $quantity = $_POST['quantity'] ?? '';
    if ( ! tiron_valid_luna_quantity( $quantity, $product ) ) {
        return new WP_Error( 'tiron_quantity', 'Verifică numărul de bucăți și stocul disponibil.' );
    }
    $notes = wp_unslash( $_POST['tiron_notes'] ?? '' );
    if ( ! is_string( $notes ) || ! preg_match( '/^.{0,500}$/usD', $notes ) ) {
        return new WP_Error( 'tiron_notes', 'Observațiile pot avea cel mult 500 de caractere.' );
    }
    if ( ! is_array( $_FILES['tiron_photo'] ?? null ) ) {
        return new WP_Error( 'tiron_image_required', 'Alege o fotografie JPG, PNG sau WebP.' );
    }
    $stage = tiron_store_photo( $_FILES['tiron_photo'] );
    if ( is_wp_error( $stage ) ) {
        return $stage;
    }
    $inserted = false;
    try {
        tiron_photo_session_secret( true );
        $line = [
            '_tiron_stage'  => $stage,
            '_tiron_notes'  => sanitize_textarea_field( $notes ),
            '_tiron_intent' => $intent,
        ];
        $line['_tiron_signature'] = tiron_photo_signature( $product_id, $line );
        $quantity = (int) $quantity;
        if ( apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity, 0, [], $line ) ) {
            $inserted = WC()->cart->add_to_cart( $product_id, $quantity, 0, [], $line );
        }
    } finally {
        if ( ! $inserted ) {
            tiron_delete_photo( $stage );
        }
    }
    return (bool) $inserted;
}

add_action( 'template_redirect', static function () {
    if ( ! function_exists( 'WC' ) || ! WC()->cart || ! WC()->session || ! is_product() || ! tiron_requires_photo( get_queried_object_id() ) ) {
        return;
    }
    wc_nocache_headers();
    WC()->session->set_customer_session_cookie( true );
    if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
        return;
    }
    $post_limit = wp_convert_hr_to_bytes( ini_get( 'post_max_size' ) );
    if ( $post_limit > 0 && (int) ( $_SERVER['CONTENT_LENGTH'] ?? 0 ) > $post_limit && ! $_POST && ! $_FILES ) {
        $result = new WP_Error( 'tiron_post_limit', 'Fișierul depășește limita serverului. Alege o fotografie mai mică.' );
    } elseif ( '1' === ( $_POST['tiron_add_luna'] ?? '' ) ) {
        $result = tiron_process_personalization( get_queried_object_id() );
    } else {
        return;
    }
    if ( true === $result ) {
        wp_safe_redirect( wc_get_cart_url(), 303 );
        exit;
    }
    if ( is_wp_error( $result ) ) {
        tiron_cart_error( $result->get_error_message() );
    } elseif ( ! wc_notice_count( 'error' ) ) {
        tiron_cart_error( 'Produsul nu a putut fi adăugat. Încearcă din nou.' );
    }
    wp_safe_redirect( get_permalink( get_queried_object_id() ) . '#personalizare', 303 );
    exit;
}, 1 );

// The normal add-to-cart URL, AJAX, Store API and order-again calls never receive a signed upload.
add_filter( 'woocommerce_add_to_cart_validation', static function ( $valid, $product_id, $quantity, $variation_id = 0, $variation = [], $line = [] ) {
    if ( tiron_requires_photo( $product_id ) && ! tiron_valid_photo_line( $line, $product_id ) ) {
        tiron_cart_error( tiron_photo_required_message() );
        return false;
    }
    return $valid;
}, 10, 6 );

// WC_Cart::add_to_cart() can also be called directly, without the form-handler validation filter.
add_filter( 'woocommerce_add_cart_item_data', static function ( $line, $product_id ) {
    if ( tiron_requires_photo( $product_id ) && ! tiron_valid_photo_line( $line, $product_id ) ) {
        throw new Exception( tiron_photo_required_message() );
    }
    return $line;
}, 99, 2 );

add_filter( 'woocommerce_pre_remove_cart_item_from_session', static function ( $remove, $key, $line, $product ) {
    if ( tiron_requires_photo( $product->get_id() ) && ! tiron_valid_photo_line( $line, $product->get_id() ) ) {
        tiron_cart_error( tiron_photo_expired_message() );
        return true;
    }
    return $remove;
}, 10, 4 );

add_filter( 'woocommerce_quantity_input_args', static function ( $args, $product ) {
    if ( $product && tiron_requires_photo( $product->get_id() ) ) {
        $max = (int) $args['max_value'];
        $args['max_value'] = $max > 0 ? min( 99, $max ) : 99;
    }
    return $args;
}, 10, 2 );

add_filter( 'woocommerce_update_cart_validation', static function ( $valid, $key, $line, $quantity ) {
    if ( $quantity > 0 && tiron_requires_photo( $line['product_id'] ) && ! tiron_valid_luna_quantity( $quantity, $line['data'] ) ) {
        tiron_cart_error( 'Verifică numărul de bucăți pentru TIR-ON Luna.' );
        return false;
    }
    return $valid;
}, 10, 4 );

add_action( 'woocommerce_check_cart_items', static function () {
    foreach ( WC()->cart->get_cart() as $line ) {
        if ( ! tiron_requires_photo( $line['product_id'] ) ) {
            continue;
        }
        if ( ! tiron_valid_photo_line( $line, $line['product_id'] ) ) {
            tiron_cart_error( tiron_photo_expired_message() );
        }
        if ( ! tiron_valid_luna_quantity( $line['quantity'], $line['data'] ) ) {
            tiron_cart_error( 'Verifică numărul de bucăți pentru TIR-ON Luna.' );
        }
    }
} );

add_filter( 'woocommerce_get_item_data', static function ( $data, $line ) {
    if ( ! empty( $line['_tiron_stage'] ) && tiron_valid_photo_line( $line, $line['product_id'] ) ) {
        $data[] = [ 'key' => 'Personalizare', 'value' => 'Fotografie încărcată' ];
        if ( '' !== $line['_tiron_notes'] ) {
            $data[] = [ 'key' => 'Observații', 'value' => esc_html( $line['_tiron_notes'] ) ];
        }
    }
    return $data;
}, 10, 2 );

add_filter( 'woocommerce_loop_add_to_cart_link', static function ( $html, $product ) {
    if ( tiron_requires_photo( $product->get_id() ) ) {
        return '<a class="button" href="' . esc_url( $product->get_permalink() . '#personalizare' ) . '">Personalizează Luna</a>';
    }
    return $html;
}, 10, 2 );

add_action( 'woocommerce_checkout_create_order_line_item', static function ( $item, $cart_key, $line ) {
    if ( ! tiron_requires_photo( $line['product_id'] ) ) {
        return;
    }
    if ( ! tiron_valid_photo_line( $line, $line['product_id'] ) ) {
        throw new Exception( tiron_photo_expired_message() );
    }
    if ( ! tiron_valid_luna_quantity( $line['quantity'], $line['data'] ) ) {
        throw new Exception( 'Verifică numărul de bucăți pentru TIR-ON Luna.' );
    }
    // A retry may rebuild order lines. Every line retains a separate private copy.
    $order_photo = tiron_copy_photo_for_order( $line['_tiron_stage'] );
    if ( is_wp_error( $order_photo ) ) {
        throw new Exception( $order_photo->get_error_message() );
    }
    $item->add_meta_data( '_tiron_photo_id', $order_photo, true );
    if ( '' !== $line['_tiron_notes'] ) {
        $item->add_meta_data( '_tiron_notes', $line['_tiron_notes'], true );
    }
}, 10, 3 );

/** Re-check persisted references before payment, including an existing order's pay link. */
function tiron_validate_order_photos( $order ) {
    foreach ( $order->get_items() as $item ) {
        if ( ! tiron_requires_photo( $item->get_product_id() ) ) {
            continue;
        }
        $file = tiron_photo_path( $item->get_meta( '_tiron_photo_id', true ) );
        if ( ! is_string( $file ) || ! is_file( $file ) ) {
            throw new Exception( tiron_photo_expired_message() );
        }
    }
}
add_action( 'woocommerce_before_pay_action', 'tiron_validate_order_photos' );
add_action( 'woocommerce_store_api_checkout_order_processed', 'tiron_validate_order_photos' );
add_action( 'woocommerce_checkout_order_processed', static function ( $order_id ) {
    tiron_validate_order_photos( wc_get_order( $order_id ) );
} );

add_filter( 'woocommerce_hidden_order_itemmeta', static function ( $keys ) {
    return array_merge( $keys, [ '_tiron_photo_id', '_tiron_notes' ] );
} );
