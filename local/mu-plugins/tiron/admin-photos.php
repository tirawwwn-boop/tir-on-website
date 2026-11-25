<?php
/** Restricted access to production files and scheduled removal of private photos. */
defined( 'ABSPATH' ) || exit;

function tiron_photo_settings() {
	return array(
		'tiron_photo_limit_mb'   => array( 'Dimensiunea maximă a fotografiei (MB)', 10, 20 ),
		'tiron_photo_cart_days'  => array( 'Păstrarea fotografiilor de coș (zile)', 2, 30 ),
		'tiron_photo_order_days' => array( 'Păstrarea după închiderea comenzii (zile)', 30, 365 ),
	);
}

function tiron_photo_option( $key ) {
	list( , $default, $max ) = tiron_photo_settings()[ $key ];
	return min( $max, max( 1, (int) get_option( $key, $default ) ) );
}

add_action( 'admin_menu', static function () {
	add_submenu_page( 'woocommerce', 'Fotografii private TIR-ON', 'Fotografii private', 'manage_options', 'tiron-photos', 'tiron_render_photo_settings' );
} );

function tiron_render_photo_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Acces restricționat.', '', array( 'response' => 403 ) );
	}
	?>
	<div class="wrap"><h1>Fotografii private TIR-ON</h1>
	<?php if ( isset( $_GET['updated'] ) && $_GET['updated'] === '1' ) : ?>
		<div class="notice notice-success"><p>Setările au fost salvate.</p></div>
	<?php endif; ?>
	<p>Încarci fotografiile comerciale în Media. Fotografiile clienților se păstrează separat, într-un volum privat. Le poți descărca din linia produsului din comanda WooCommerce.</p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="tiron_save_photo_settings">
		<?php wp_nonce_field( 'tiron_save_photo_settings' ); ?>
		<table class="form-table" role="presentation">
		<?php foreach ( tiron_photo_settings() as $key => list( $label, , $max ) ) : ?>
			<tr><th scope="row"><label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td><input id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" type="number" min="1" max="<?php echo (int) $max; ?>" required value="<?php echo tiron_photo_option( $key ); ?>" class="small-text"></td></tr>
		<?php endforeach; ?>
		</table>
		<p>Limita efectivă ține cont și de configurația PHP. Schimbarea perioadei de păstrare se aplică inclusiv fotografiilor existente, la următoarea curățare.</p>
		<p>Comenzile în lucru își păstrează fotografia. Pentru comenzile finalizate, perioada începe la finalizare. Pentru comenzile anulate, rambursate sau eșuate, calculul pornește de la ultima modificare. Copiile abandonate în timpul creării unei comenzi expiră după două zile.</p>
		<?php submit_button( 'Salvează setările' ); ?>
	</form></div>
	<?php
}

add_action( 'admin_post_tiron_save_photo_settings', static function () {
	if ( ! current_user_can( 'manage_options' ) || ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' ) {
		wp_die( 'Acces restricționat.', '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'tiron_save_photo_settings' );
	$values = array();
	foreach ( tiron_photo_settings() as $key => list( , , $max ) ) {
		$raw = isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
		if ( ! ctype_digit( $raw ) || (int) $raw < 1 || (int) $raw > $max ) {
			wp_die( 'Valoare în afara intervalului permis. Revino la setări.', '', array( 'response' => 400 ) );
		}
		$values[ $key ] = (int) $raw;
	}
	foreach ( $values as $key => $value ) {
		update_option( $key, $value, false );
	}
	wp_safe_redirect( admin_url( 'admin.php?page=tiron-photos&updated=1' ) );
	exit;
} );

function tiron_can_access_order_photo( $order_id ) {
	// WooCommerce maps edit_post to per-order capabilities for both CPT and HPOS.
	return current_user_can( 'manage_options' ) && current_user_can( 'manage_woocommerce' ) && current_user_can( 'edit_post', $order_id );
}

function tiron_photo_request_url( $action, $order_id, $item_id ) {
	return add_query_arg(
		array(
			'action'   => $action,
			'order_id' => $order_id,
			'item_id'  => $item_id,
			'_wpnonce' => wp_create_nonce( $action . '_' . $order_id . '_' . $item_id ),
		),
		admin_url( 'admin-post.php' )
	);
}

add_filter( 'woocommerce_hidden_order_itemmeta', static function ( $keys ) {
	return array_merge( $keys, array( '_tiron_photo_id', '_tiron_notes' ) );
} );

add_action( 'woocommerce_after_order_itemmeta', static function ( $item_id, $item ) {
	if ( ! $item instanceof WC_Order_Item_Product || ! tiron_can_access_order_photo( $item->get_order_id() ) ) {
		return;
	}
	$order_id = $item->get_order_id();
	$photo_id = $item->get_meta( '_tiron_photo_id', true );
	$notes    = $item->get_meta( '_tiron_notes', true );
	if ( is_string( $notes ) && $notes !== '' ) {
		echo '<p><strong>Observații pentru atelier</strong><br>' . nl2br( esc_html( $notes ) ) . '</p>';
	}
	if ( ! is_string( $photo_id ) || $photo_id === '' ) {
		return;
	}
	$path = function_exists( 'tiron_order_photo_path' ) ? tiron_order_photo_path( $photo_id ) : false;
	if ( ! is_string( $path ) || ! is_file( $path ) ) {
		echo '<p>Fotografia acestei comenzi a expirat sau a fost eliminată.</p>';
		return;
	}
	echo '<p><a class="button" href="' . esc_url( tiron_photo_request_url( 'tiron_download_photo', $order_id, $item_id ) ) . '">Descarcă fotografia privată</a> ';
	echo '<a href="' . esc_url( tiron_photo_request_url( 'tiron_confirm_erasure', $order_id, $item_id ) ) . '">Șterge fotografia…</a></p>';
}, 10, 2 );

/** Resolve an order-owned line after permission and action-specific nonce checks. */
function tiron_requested_photo_item( $action, $input ) {
	$id = static function ( $key ) use ( $input ) {
		$value = $input[ $key ] ?? '';
		return is_string( $value ) && ctype_digit( $value ) ? (int) $value : 0;
	};
	$order_id = $id( 'order_id' );
	$item_id  = $id( 'item_id' );
	$nonce    = isset( $input['_wpnonce'] ) && is_string( $input['_wpnonce'] ) ? wp_unslash( $input['_wpnonce'] ) : '';
	if ( ! $order_id || ! $item_id || ! tiron_can_access_order_photo( $order_id ) || ! wp_verify_nonce( $nonce, $action . '_' . $order_id . '_' . $item_id ) ) {
		wp_die( 'Acces restricționat.', '', array( 'response' => 403 ) );
	}
	if ( ! function_exists( 'wc_get_order' ) || ! function_exists( 'tiron_order_photo_path' ) ) {
		wp_die( 'Fotografie indisponibilă.', '', array( 'response' => 503 ) );
	}
	$order = wc_get_order( $order_id );
	$item  = $order ? $order->get_item( $item_id ) : false;
	if ( ! $item instanceof WC_Order_Item_Product || (int) $item->get_order_id() !== $order_id || ! is_string( $item->get_meta( '_tiron_photo_id' ) ) || ! $item->get_meta( '_tiron_photo_id' ) ) {
		wp_die( 'Fotografie indisponibilă.', '', array( 'response' => 404 ) );
	}
	return $item;
}

function tiron_stream_order_photo( string $action, string $disposition ): void {
	if ( 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		wp_die( 'Metodă invalidă.', '', array( 'response' => 405 ) );
	}
	$item   = tiron_requested_photo_item( $action, $_GET );
	$path   = tiron_order_photo_path( $item->get_meta( '_tiron_photo_id' ) );
	$stream = is_string( $path ) && is_file( $path ) ? @fopen( $path, 'rb' ) : false;
	if ( ! $stream ) {
		wp_die( 'Fotografie indisponibilă.', '', array( 'response' => 404 ) );
	}
	// Keep a single open handle through download if garbage collection unlinks the file.
	nocache_headers();
	header( 'Cache-Control: private, no-store, max-age=0' );
	header( 'Referrer-Policy: no-referrer' );
	header( 'Cross-Origin-Resource-Policy: same-origin' );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'X-Robots-Tag: noindex, noarchive' );
	header( 'Content-Type: image/jpeg' );
	header( 'Content-Disposition: ' . $disposition . '; filename="Luna-' . (int) $item->get_order_id() . '-' . (int) $item->get_id() . '.jpg"' );
	$stat = fstat( $stream );
	if ( $stat ) {
		header( 'Content-Length: ' . $stat['size'] );
	}
	fpassthru( $stream );
	fclose( $stream );
	exit;
}

add_action( 'admin_post_tiron_download_photo', static function () {
	tiron_stream_order_photo( 'tiron_download_photo', 'attachment' );
} );

add_action( 'admin_post_tiron_preview_photo', static function () {
	tiron_stream_order_photo( 'tiron_preview_photo', 'inline' );
} );

add_action( 'admin_post_tiron_confirm_erasure', static function () {
	$item  = tiron_requested_photo_item( 'tiron_confirm_erasure', $_GET );
	$order = $item->get_order();
	nocache_headers();
	$content  = '<p>Ștergerea fotografiei pentru acest produs este definitivă. Datele contabile ale comenzii se păstrează.</p>';
	$content .= '<form action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" method="post">';
	$content .= '<input type="hidden" name="action" value="tiron_erase_photo"><input type="hidden" name="order_id" value="' . (int) $order->get_id() . '"><input type="hidden" name="item_id" value="' . (int) $item->get_id() . '">';
	$content .= wp_nonce_field( 'tiron_erase_photo_' . (int) $order->get_id() . '_' . (int) $item->get_id(), '_wpnonce', false, false );
	$content .= '<button type="submit" class="button">Confirmă ștergerea</button></form><p><a href="' . esc_url( $order->get_edit_order_url() ) . '">Revino la comandă</a></p>';
	wp_die( $content, 'Ștergere fotografie', array( 'response' => 200 ) );
} );

add_action( 'admin_post_tiron_erase_photo', static function () {
	if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' ) {
		wp_die( 'Metodă invalidă.', '', array( 'response' => 405 ) );
	}
	$item = tiron_requested_photo_item( 'tiron_erase_photo', $_POST );
	$id   = $item->get_meta( '_tiron_photo_id' );
	if ( tiron_order_photo_path( $id ) && ! tiron_delete_photo( $id ) && tiron_order_photo_path( $id ) ) {
		wp_die( 'Ștergerea a eșuat. Încearcă din nou.', '', array( 'response' => 500 ) );
	}
	wp_safe_redirect( $item->get_order()->get_edit_order_url() );
	exit;
} );

add_action( 'init', static function () {
	if ( ! wp_next_scheduled( 'tiron_cleanup_private_photos' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'tiron_cleanup_private_photos' );
	}
} );

add_action( 'tiron_cleanup_private_photos', 'tiron_cleanup_private_photos' );
function tiron_cleanup_private_photos() {
	if ( ! function_exists( 'wc_get_order' ) || ! function_exists( 'tiron_order_photos_before' ) ) {
		return;
	}
	$now           = time();
	$staged_cutoff = $now - tiron_photo_option( 'tiron_photo_cart_days' ) * DAY_IN_SECONDS;
	$order_cutoff  = $now - tiron_photo_option( 'tiron_photo_order_days' ) * DAY_IN_SECONDS;
	foreach ( tiron_staged_photos_before( $staged_cutoff ) as $id ) {
		tiron_delete_staged_photo( $id, $staged_cutoff );
	}

	// Include one-day terminal retention as well as two-day orphan cleanup.
	$orphan_cutoff = $now - 2 * DAY_IN_SECONDS;
	$scan_cutoff   = max( $orphan_cutoff, $order_cutoff );
	foreach ( tiron_order_photos_before( $scan_cutoff ) as $id ) {
		tiron_delete_order_photo( $id, $scan_cutoff, static function ( $id, $created ) use ( $order_cutoff, $orphan_cutoff ) {
			global $wpdb;
			// Woo stores line IDs and metadata in these same tables for HPOS and CPT.
			$order_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT items.order_id FROM {$wpdb->prefix}woocommerce_order_items items
					INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta meta ON items.order_item_id = meta.order_item_id
					WHERE meta.meta_key = %s AND meta.meta_value = %s",
					'_tiron_photo_id', $id
				)
			);
			if ( $wpdb->last_error !== '' ) {
				return false;
			}
			if ( ! $order_ids ) {
				return $created <= $orphan_cutoff;
			}
			foreach ( $order_ids as $order_id ) {
				$order = wc_get_order( $order_id );
				if ( ! $order || ! $order->has_status( array( 'completed', 'cancelled', 'failed', 'refunded' ) ) ) {
					return false;
				}
				$date = $order->has_status( 'completed' ) ? $order->get_date_completed() : $order->get_date_modified();
				if ( ! $date || $date->getTimestamp() > $order_cutoff ) {
					return false;
				}
			}
			return true;
		} );
	}
}
