<?php
/** Private production queue for Luna, using WooCommerce order objects for CPT and HPOS. */
defined( 'ABSPATH' ) || exit;

function tiron_atelier_stages(): array {
	return array(
		'received'              => 'Comandă primită',
		'awaiting-photo-review' => 'Fotografie în așteptarea verificării',
		'photo-approved'        => 'Fotografie aprobată',
		'production'            => 'În producție',
		'quality-control'       => 'Controlul calității',
		'ready-to-ship'          => 'Pregătită pentru expediere',
		'completed'             => 'Finalizată în atelier',
	);
}

function tiron_atelier_enabled(): bool {
	return function_exists( 'wc_get_orders' ) && function_exists( 'tiron_requires_photo' )
		&& function_exists( 'tiron_can_access_order_photo' ) && function_exists( 'tiron_is_photo_id' );
}

function tiron_atelier_can_manage(): bool {
	return current_user_can( 'manage_options' ) && current_user_can( 'manage_woocommerce' );
}

function tiron_atelier_url( array $args = array() ): string {
	return add_query_arg( array( 'page' => 'tiron-atelier' ) + $args, admin_url( 'admin.php' ) );
}

function tiron_atelier_number( $value, int $minimum = 1 ) {
	return is_string( $value ) && ctype_digit( $value )
		? filter_var( $value, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => $minimum, 'max_range' => PHP_INT_MAX - 1 ) ) )
		: false;
}

function tiron_atelier_items( WC_Order $order ): array {
	return array_filter( $order->get_items( 'line_item' ), static function ( $item ) {
		// A saved photo reference remains part of Luna's history if the product changes or is deleted.
		return $item instanceof WC_Order_Item_Product
			&& ( tiron_requires_photo( $item->get_product_id() ) || tiron_is_photo_id( $item->get_meta( '_tiron_photo_id', true ) ) );
	} );
}

function tiron_atelier_workflow( WC_Order $order ): array {
	$data  = $order->get_meta( '_tiron_atelier_workflow', true );
	$data  = is_array( $data ) ? $data : array();
	$stage = $data['stage'] ?? '';
	return array(
		'stage'    => is_string( $stage ) && isset( tiron_atelier_stages()[ $stage ] ) ? $stage : 'received',
		'notes'    => is_string( $data['notes'] ?? null ) ? $data['notes'] : '',
		'revision' => max( 0, (int) ( $data['revision'] ?? 0 ) ),
	);
}

function tiron_atelier_payment( WC_Order $order ): string {
	if ( 'yes' === $order->get_meta( '_tiron_local_simulation', true ) && $order->has_status( 'on-hold' ) ) {
		return 'Simulare locală';
	}
	if ( $order->has_status( 'failed' ) ) {
		return 'Plată eșuată';
	}
	if ( $order->has_status( 'refunded' ) ) {
		return 'Rambursată';
	}
	return $order->get_date_paid() ? 'Plată înregistrată' : 'Plată neconfirmată';
}

function tiron_atelier_shipping( WC_Order $order ): string {
	$states = array( 'shipped' => 'Expediată', 'out-for-delivery' => 'În livrare', 'delivered' => 'Livrată' );
	$status = $order->get_status();
	if ( isset( $states[ $status ] ) ) {
		return $states[ $status ];
	}
	// WooCommerce Shipment Tracking. A tracking record alone does not confirm delivery.
	$tracking = $order->get_meta( '_wc_shipment_tracking_items', true );
	foreach ( is_array( $tracking ) ? $tracking : array() as $entry ) {
		if ( is_array( $entry ) && ! empty( $entry['tracking_number'] ) ) {
			return 'Urmărire înregistrată';
		}
	}
	return 'Fără status';
}

function tiron_atelier_customer( WC_Order $order ): string {
	$name = $order->get_formatted_billing_full_name() ?: $order->get_formatted_shipping_full_name();
	return $name ?: 'Client fără nume';
}

function tiron_atelier_date( WC_Order $order ): string {
	$date = $order->get_date_created();
	return $date ? wc_format_datetime( $date ) : '—';
}

/** Scan small batches through WooCommerce. Stage and Luna membership use the same logic in CPT and HPOS. */
function tiron_atelier_queue( string $filter, int $page ): array {
	$queue = array( 'orders' => 0, 'units' => 0, 'review' => 0, 'matches' => 0, 'rows' => array() );
	$offset = ( $page - 1 ) * 20;
	$stages_for_review = array( 'received', 'awaiting-photo-review' );
	for ( $batch = 1; ; ++$batch ) {
		$result = wc_get_orders( array(
			'limit'    => 50,
			'page'     => $batch,
			'paginate' => true,
			'type'     => 'shop_order',
			'orderby'  => 'date',
			'order'    => 'ASC',
		) );
		foreach ( $result->orders as $order ) {
			if ( ! tiron_can_access_order_photo( $order->get_id() ) ) {
				continue;
			}
			$items = tiron_atelier_items( $order );
			if ( ! $items ) {
				continue;
			}
			$stage = tiron_atelier_workflow( $order )['stage'];
			$units = array_sum( array_map( static fn( $item ) => max( 0, $item->get_quantity() ), $items ) );
			$active = 'completed' !== $stage && ! $order->has_status( array( 'failed', 'cancelled', 'refunded' ) );
			$review = $active && in_array( $stage, $stages_for_review, true );
			if ( $active ) {
				++$queue['orders'];
				$queue['units'] += $units;
			}
			if ( $review ) {
				++$queue['review'];
			}
			if ( 'all' !== $filter && $stage !== $filter && ! ( 'work' === $filter && $active ) && ! ( 'review' === $filter && $review ) ) {
				continue;
			}
			$index = $queue['matches']++;
			if ( $index >= $offset && $index < $offset + 20 ) {
				$queue['rows'][] = array( $order, $items, $stage, $units );
			}
		}
		if ( $batch >= (int) $result->max_num_pages ) {
			return $queue;
		}
	}
}

add_action( 'admin_menu', static function () {
	$order = null;
	$hook = add_submenu_page( 'woocommerce', 'Atelier TIR-ON', 'Atelier TIR-ON', 'manage_options', 'tiron-atelier', static function () use ( &$order ) {
		tiron_atelier_page( $order );
	} );
	if ( ! $hook ) {
		return;
	}
	add_action( 'load-' . $hook, static function () use ( &$order ) {
		if ( ! tiron_atelier_can_manage() ) {
			wp_die( 'Acces restricționat.', '', array( 'response' => 403 ) );
		}
		nocache_headers();
		header( 'Referrer-Policy: no-referrer' );
		if ( ! tiron_atelier_enabled() || ! isset( $_GET['order_id'] ) ) {
			return;
		}
		$id = tiron_atelier_number( $_GET['order_id'] );
		if ( false === $id ) {
			wp_die( 'Număr de comandă nevalid.', '', array( 'response' => 400 ) );
		}
		$order = wc_get_order( $id );
		if ( ! $order instanceof WC_Order || ! tiron_atelier_items( $order ) ) {
			wp_die( 'Comanda Luna nu a fost găsită.', '', array( 'response' => 404 ) );
		}
		if ( ! tiron_can_access_order_photo( $id ) ) {
			wp_die( 'Acces restricționat.', '', array( 'response' => 403 ) );
		}
	} );
} );

add_action( 'admin_enqueue_scripts', static function ( $hook ) {
	$path = __DIR__ . '/atelier.css';
	if ( 'woocommerce_page_tiron-atelier' === $hook && is_readable( $path ) ) {
		wp_enqueue_style( 'tiron-atelier', WPMU_PLUGIN_URL . '/tiron/atelier.css', array(), (string) filemtime( $path ) );
	}
} );

function tiron_atelier_page( $order ): void {
	if ( ! tiron_atelier_enabled() ) {
		echo '<div class="wrap"><h1>Atelier TIR-ON</h1><div class="notice notice-warning"><p>Activează WooCommerce și verifică modulele TIR-ON pentru a încărca atelierul.</p></div></div>';
		return;
	}
	if ( $order ) {
		tiron_atelier_detail( $order );
		return;
	}

	$filters = array( 'work' => 'Comenzi în lucru', 'review' => 'De verificat fotografia', 'all' => 'Toate comenzile' ) + tiron_atelier_stages();
	$filter  = $_GET['stage'] ?? 'work';
	$filter  = is_string( $filter ) && isset( $filters[ $filter ] ) ? $filter : 'work';
	$page    = min( 100000, tiron_atelier_number( $_GET['paged'] ?? '' ) ?: 1 );
	$queue   = tiron_atelier_queue( $filter, $page );
	?>
	<div class="wrap tiron-at" data-tiron-atelier>
		<header class="tiron-at-hero">
			<p>WooCommerce · Atelier TIR-ON</p>
			<h1>Panou de producție</h1>
			<p>Comenzile Luna, începând cu cele mai vechi.</p>
		</header>
		<dl class="tiron-at-kpis">
			<div><dt>Comenzi în lucru</dt><dd data-atelier-kpi="orders"><?php echo esc_html( (string) $queue['orders'] ); ?></dd></div>
			<div><dt>Bucăți rămase</dt><dd data-atelier-kpi="units"><?php echo esc_html( (string) $queue['units'] ); ?></dd></div>
			<div><dt>Comenzi cu fotografii de verificat</dt><dd data-atelier-kpi="review"><?php echo esc_html( (string) $queue['review'] ); ?></dd></div>
		</dl>
		<section class="tiron-at-panel" aria-labelledby="tiron-at-queue">
			<div class="tiron-at-toolbar">
				<div><h2 id="tiron-at-queue">Lista de lucru</h2><p><?php echo esc_html( sprintf( 'Comenzi în această listă: %d', $queue['matches'] ) ); ?></p></div>
				<form method="get" data-atelier-filter>
					<input type="hidden" name="page" value="tiron-atelier">
					<label for="tiron-at-filter">Filtru</label>
					<select id="tiron-at-filter" name="stage">
						<?php foreach ( $filters as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $filter, $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<button class="button" type="submit">Aplică</button>
				</form>
			</div>
			<?php if ( ! $queue['rows'] ) : ?>
				<p class="tiron-at-empty">Nu sunt comenzi Luna pentru acest filtru.</p>
			<?php else : ?>
				<table class="widefat tiron-at-table">
					<thead><tr><th scope="col">Comandă</th><th scope="col">Producție</th><th scope="col">WooCommerce și plată</th><th scope="col">Livrare</th><th scope="col"><span class="screen-reader-text">Acțiune</span></th></tr></thead>
					<tbody>
					<?php foreach ( $queue['rows'] as list( $order, $items, $stage, $units ) ) :
						$id = $order->get_id();
						$ready = count( array_filter( $items, static fn( $item ) => tiron_atelier_photo_available( $item ) ) );
						?>
						<tr data-atelier-order="<?php echo esc_attr( (string) $id ); ?>">
							<th scope="row" class="tiron-at-order">
								<a href="<?php echo esc_url( tiron_atelier_url( array( 'order_id' => $id ) ) ); ?>">#<?php echo esc_html( $order->get_order_number() ); ?></a>
								<span><?php echo esc_html( tiron_atelier_customer( $order ) ); ?></span>
								<small><?php echo esc_html( tiron_atelier_date( $order ) ); ?></small>
							</th>
							<td><span class="tiron-at-mobile-label">Producție</span><span class="tiron-at-stage" data-stage="<?php echo esc_attr( $stage ); ?>"><?php echo esc_html( tiron_atelier_stages()[ $stage ] ); ?></span>
								<small><?php echo esc_html( sprintf( 'Bucăți: %d · Fotografii: %d/%d', $units, $ready, count( $items ) ) ); ?></small>
							</td>
							<td><span class="tiron-at-mobile-label">WooCommerce și plată</span><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?><small><?php echo esc_html( tiron_atelier_payment( $order ) ); ?></small></td>
							<td><span class="tiron-at-mobile-label">Livrare</span><?php echo esc_html( tiron_atelier_shipping( $order ) ); ?></td>
							<td><a class="button" href="<?php echo esc_url( tiron_atelier_url( array( 'order_id' => $id ) ) ); ?>" aria-label="<?php echo esc_attr( sprintf( 'Vezi detaliile comenzii %s', $order->get_order_number() ) ); ?>">Detalii</a></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif;
			$pages = (int) ceil( $queue['matches'] / 20 );
			if ( $pages > 1 ) :
				$links = paginate_links( array(
					'base'      => tiron_atelier_url( array( 'stage' => $filter ) ) . '%_%',
					'format'    => '&paged=%#%',
					'current'   => $page,
					'total'     => $pages,
					'prev_text' => 'Anterior',
					'next_text' => 'Următor',
				) );
				?>
				<nav class="tiron-at-pagination" aria-label="Pagini atelier"><?php echo wp_kses_post( $links ); ?></nav>
			<?php endif; ?>
		</section>
	</div>
	<?php
}

function tiron_atelier_photo_available( WC_Order_Item_Product $item ): bool {
	return function_exists( 'tiron_order_photo_path' ) && (bool) tiron_order_photo_path( $item->get_meta( '_tiron_photo_id', true ) );
}

function tiron_atelier_detail( WC_Order $order ): void {
	$id = $order->get_id();
	$workflow = tiron_atelier_workflow( $order );
	?>
	<div class="wrap tiron-at" data-tiron-atelier data-atelier-detail="<?php echo esc_attr( (string) $id ); ?>">
		<nav class="tiron-at-back" aria-label="Navigare în atelier">
			<a href="<?php echo esc_url( tiron_atelier_url() ); ?>">← Panou de producție</a>
			<a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">Deschide în WooCommerce</a>
		</nav>
		<header class="tiron-at-hero">
			<p>Comandă Luna · <?php echo esc_html( tiron_atelier_date( $order ) ); ?></p>
			<h1>Comanda #<?php echo esc_html( $order->get_order_number() ); ?></h1>
			<p><?php echo esc_html( tiron_atelier_customer( $order ) ); ?></p>
		</header>
		<?php if ( '1' === ( $_GET['updated'] ?? '' ) ) : ?>
			<div class="notice notice-success inline" role="status"><p>Progresul din atelier a fost salvat.</p></div>
		<?php endif; ?>
		<div class="tiron-at-layout">
			<section class="tiron-at-panel tiron-at-panel-content" aria-labelledby="tiron-at-lines">
				<h2 id="tiron-at-lines">Fotografii și personalizare</h2>
				<?php foreach ( tiron_atelier_items( $order ) as $item ) :
					$item_id = $item->get_id();
					$notes = $item->get_meta( '_tiron_notes', true );
					?>
					<article class="tiron-at-line" data-atelier-item="<?php echo esc_attr( (string) $item_id ); ?>">
						<h3><?php echo esc_html( $item->get_name() ); ?><span>× <?php echo esc_html( (string) $item->get_quantity() ); ?></span></h3>
						<?php if ( tiron_atelier_photo_available( $item ) ) : ?>
							<img class="tiron-at-photo" src="<?php echo esc_url( tiron_photo_request_url( 'tiron_preview_photo', $id, $item_id ) ); ?>" alt="Fotografia privată pentru această linie Luna" loading="lazy" referrerpolicy="no-referrer">
							<a class="button" href="<?php echo esc_url( tiron_photo_request_url( 'tiron_download_photo', $id, $item_id ) ); ?>">Descarcă fotografia</a>
						<?php else : ?>
							<p>Fotografia lipsește sau a fost ștearsă conform regulilor de păstrare.</p>
						<?php endif; ?>
						<h4>Observațiile clientului</h4>
						<p class="tiron-at-notes"><?php echo esc_html( is_string( $notes ) && '' !== $notes ? $notes : 'Fără observații.' ); ?></p>
					</article>
				<?php endforeach; ?>
			</section>
			<section class="tiron-at-panel tiron-at-panel-content" aria-labelledby="tiron-at-production">
				<h2 id="tiron-at-production">Progres în atelier</h2>
				<dl class="tiron-at-statuses">
					<div><dt>Comandă WooCommerce</dt><dd><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></dd></div>
					<div><dt>Plată</dt><dd><?php echo esc_html( tiron_atelier_payment( $order ) ); ?></dd></div>
					<div><dt>Livrare</dt><dd><?php echo esc_html( tiron_atelier_shipping( $order ) ); ?></dd></div>
				</dl>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-atelier-form>
					<input type="hidden" name="action" value="tiron_atelier_save">
					<input type="hidden" name="order_id" value="<?php echo esc_attr( (string) $id ); ?>">
					<input type="hidden" name="revision" value="<?php echo esc_attr( (string) $workflow['revision'] ); ?>">
					<?php wp_nonce_field( 'tiron_atelier_save_' . $id ); ?>
					<label for="tiron-at-stage">Etapă de producție</label>
					<select id="tiron-at-stage" name="stage">
						<?php foreach ( tiron_atelier_stages() as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $workflow['stage'], $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<label for="tiron-at-notes">Note interne de producție și control al calității</label>
					<textarea id="tiron-at-notes" name="notes" rows="6" maxlength="2000" aria-describedby="tiron-at-private"><?php echo esc_textarea( $workflow['notes'] ); ?></textarea>
					<p id="tiron-at-private">Etapa și notele sunt vizibile doar în atelier. Starea comenzii și a plății se gestionează în WooCommerce.</p>
					<button class="button button-primary" type="submit">Salvează progresul</button>
				</form>
			</section>
		</div>
	</div>
	<?php
}

/** Keep concurrent edits of the same revision from overwriting each other. */
function tiron_atelier_update( int $id, int $revision, string $stage, string $notes ): int {
	$root = function_exists( 'tiron_photo_root' ) ? tiron_photo_root() : false;
	$dir  = $root ? tiron_photo_bucket( $root, 'atelier-locks' ) : false;
	$path = $dir ? $dir . '/' . substr( hash( 'sha256', (string) $id ), 0, 2 ) . '.lock' : '';
	$file = $path && ! is_link( $path ) ? @fopen( $path, 'c+b' ) : false;
	if ( ! $file ) {
		return 503;
	}
	try {
		if ( ! @chmod( $path, 0600 ) ) {
			return 503;
		}
		if ( ! flock( $file, LOCK_EX | LOCK_NB ) ) {
			return 409;
		}
		$order = wc_get_order( $id );
		if ( ! $order instanceof WC_Order || ! tiron_atelier_items( $order ) ) {
			return 404;
		}
		if ( ! tiron_can_access_order_photo( $id ) ) {
			return 403;
		}
		$order->read_meta_data( true );
		$previous = tiron_atelier_workflow( $order );
		if ( $previous['revision'] !== $revision ) {
			return 409;
		}
		$next = array( 'stage' => $stage, 'notes' => $notes, 'revision' => $revision + 1 );
		// Only save internal metadata. Do not call payment/status transitions or order notes.
		$order->update_meta_data( '_tiron_atelier_workflow', $next );
		$order->save_meta_data();
		$order->read_meta_data( true );
		return $next === tiron_atelier_workflow( $order ) ? 303 : 503;
	} finally {
		fclose( $file );
	}
}

add_action( 'admin_post_tiron_atelier_save', static function () {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! tiron_atelier_can_manage() ) {
		wp_die( 'Acces restricționat.', '', array( 'response' => 403 ) );
	}
	if ( ! tiron_atelier_enabled() ) {
		wp_die( 'Atelierul nu este disponibil momentan.', '', array( 'response' => 503 ) );
	}
	$id = tiron_atelier_number( $_POST['order_id'] ?? '' );
	$revision = tiron_atelier_number( $_POST['revision'] ?? '', 0 );
	$stage = $_POST['stage'] ?? '';
	$notes = isset( $_POST['notes'] ) && is_string( $_POST['notes'] ) ? wp_unslash( $_POST['notes'] ) : null;
	if ( false === $id || false === $revision || ! is_string( $stage ) || ! isset( tiron_atelier_stages()[ $stage ] )
		|| ! is_string( $notes ) || 1 !== preg_match( '/\A.{0,2000}\z/usD', $notes ) ) {
		wp_die( 'Verifică numărul comenzii, etapa și lungimea notelor.', '', array( 'response' => 400 ) );
	}
	check_admin_referer( 'tiron_atelier_save_' . $id );
	$result = tiron_atelier_update( $id, $revision, $stage, sanitize_textarea_field( $notes ) );
	if ( 303 === $result ) {
		wp_safe_redirect( tiron_atelier_url( array( 'order_id' => $id, 'updated' => 1 ) ), 303 );
		exit;
	}
	$messages = array(
		403 => 'Acces restricționat.',
		404 => 'Comanda Luna nu a fost găsită.',
		409 => 'Progresul a fost actualizat sau este în curs de salvare. Revino la comandă, verifică datele și încearcă din nou.',
		503 => 'Progresul nu a putut fi salvat. Verifică stocarea privată și încearcă din nou.',
	);
	wp_die(
		esc_html( $messages[ $result ] ) . ' <a href="' . esc_url( tiron_atelier_url( array( 'order_id' => $id ) ) ) . '">Revino la comandă</a>',
		'Atelier TIR-ON',
		array( 'response' => $result )
	);
} );
