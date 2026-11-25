<?php
/** Synthetic QA helpers invoked by WP-CLI on an isolated WooCommerce instance. */
defined( 'ABSPATH' ) || exit;

if ( 'local' !== wp_get_environment_type() || ! defined( 'WP_CLI' ) || ! WP_CLI
	|| ! function_exists( 'tiron_local_test_checkout_enabled' ) || ! tiron_local_test_checkout_enabled() ) {
	throw new RuntimeException( 'Testele sunt limitate la o instalare WooCommerce locală, izolată.' );
}

$args = $args ?? array();
switch ( $args[0] ?? '' ) {
	case 'setup':
		$mode = $args[1] ?? '';
		if ( ! in_array( $mode, array( 'yes', 'no' ), true ) ) {
			throw new InvalidArgumentException( 'Alege modul HPOS yes sau no.' );
		}
		update_option( 'woocommerce_custom_orders_table_enabled', $mode );
		$admin = get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0] ?? null;
		$customer = wp_insert_user(
			array(
				'user_login' => 'qa-atelier-' . wp_generate_password( 12, false ),
				'user_pass' => wp_generate_password( 36 ),
				'user_email' => 'qa-' . wp_generate_password( 12, false ) . '@example.invalid',
				'role' => 'customer',
			)
		);
		if ( ! $admin || is_wp_error( $customer ) ) {
			throw new RuntimeException( 'Nu s-au putut pregăti rolurile de test.' );
		}
		$login = static function ( $id ) {
			$expires = time() + 3600;
			$token = WP_Session_Tokens::get_instance( $id )->create( $expires );
			return array(
				AUTH_COOKIE => wp_generate_auth_cookie( $id, $expires, 'auth', $token ),
				LOGGED_IN_COOKIE => wp_generate_auth_cookie( $id, $expires, 'logged_in', $token ),
			);
		};
		echo wp_json_encode( array( 'admin' => $login( $admin->ID ), 'customer' => $login( $customer ) ) );
		break;
	case 'inspect':
		$orders = array();
		$ids = array_map( 'absint', explode( ',', $args[1] ?? '' ) );
		foreach ( wc_get_orders( array( 'include' => $ids, 'limit' => -1 ) ) as $order ) {
			$lines = array();
			foreach ( $order->get_items() as $item ) {
				$id = $item->get_meta( '_tiron_photo_id', true );
				$file = $id ? tiron_photo_path( $id ) : false;
				$dimensions = $file ? getimagesize( $file ) : false;
				$lines[] = array(
					'id' => $item->get_id(),
					'quantity' => $item->get_quantity(),
					'notes' => $item->get_meta( '_tiron_notes', true ),
					'photo' => $id,
					'dimensions' => $dimensions ? array( $dimensions[0], $dimensions[1] ) : null,
					'sha256' => $file ? hash_file( 'sha256', $file ) : null,
				);
			}
			$orders[ $order->get_id() ] = array(
				'status' => $order->get_status(),
				'paid' => $order->is_paid(),
				'paid_date' => $order->get_date_paid() ? $order->get_date_paid()->getTimestamp() : null,
				'payment' => $order->get_payment_method(),
				'total' => $order->get_total(),
				'currency' => $order->get_currency(),
				'email' => $order->get_billing_email(),
				'note' => $order->get_customer_note(),
				'workflow' => $order->get_meta( '_tiron_atelier_workflow', true ),
				'lines' => $lines,
			);
		}
		echo wp_json_encode( $orders );
		break;
	case 'find':
		$ids = wc_get_orders( array( 'billing_email' => $args[1], 'limit' => -1, 'return' => 'ids' ) );
		echo wp_json_encode( array_map( 'intval', $ids ) );
		break;
	case 'foreign_line_url':
		$cookie = wp_parse_auth_cookie( $args[3], 'logged_in' );
		$user = $cookie ? get_user_by( 'login', $cookie['username'] ) : false;
		if ( ! $user || ! user_can( $user, 'manage_options' ) ) {
			throw new RuntimeException( 'Sesiunea sintetică de administrator lipsește.' );
		}
		wp_set_current_user( $user->ID );
		$_COOKIE[ LOGGED_IN_COOKIE ] = $args[3];
		echo wp_json_encode( tiron_photo_request_url( 'tiron_preview_photo', absint( $args[1] ), absint( $args[2] ) ) );
		break;
	default:
		throw new InvalidArgumentException( 'Scenariu de test necunoscut.' );
}
