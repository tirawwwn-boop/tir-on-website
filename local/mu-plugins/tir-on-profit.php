<?php
/**
 * Plugin Name: TIR-ON — Profit Studio
 * Description: Calculator privat de costuri și scenarii pentru Luna.
 */
defined( 'ABSPATH' ) || exit;

if ( ! is_admin() || ! is_readable( __DIR__ . '/tiron/profit-calculator.php' ) ) {
	return;
}
require_once __DIR__ . '/tiron/profit-calculator.php';

function tiron_profit_require_admin(): void {
	if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( 'Acces restricționat.', '', [ 'response' => 403 ] );
	}
}

function tiron_profit_url(): string {
	return admin_url( 'admin.php?page=tiron-profit' );
}

function tiron_profit_state( ?array $evaluation = null ): array {
	static $state;
	$state = $evaluation ?? $state ?? tiron_profit_evaluate( (array) get_option( 'tiron_profit_v1', [] ) );
	return $state;
}

add_action( 'admin_menu', static function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}
	$hook = add_submenu_page( 'woocommerce', 'TIR-ON Profit', 'TIR-ON Profit', 'manage_options', 'tiron-profit', 'tiron_profit_page' );
	add_action( 'load-' . $hook, 'tiron_profit_submit' );
	add_action( 'admin_enqueue_scripts', static function ( $screen ) use ( $hook ) {
		$css = __DIR__ . '/tiron/profit.css';
		if ( $hook === $screen && is_readable( $css ) ) {
			wp_enqueue_style( 'tiron-profit', WPMU_PLUGIN_URL . '/tiron/profit.css', [], substr( hash_file( 'sha256', $css ), 0, 12 ) );
		}
	} );
} );

function tiron_profit_submit(): void {
	tiron_profit_require_admin();
	nocache_headers();
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		return;
	}
	check_admin_referer( 'tiron_profit_save' );
	if ( ! is_array( $_POST['profit'] ?? null ) ) {
		wp_die( 'Date de calcul nevalide.', '', [ 'response' => 400 ] );
	}
	$evaluation = tiron_profit_evaluate( wp_unslash( $_POST['profit'] ) );
	if ( $evaluation[2] ) {
		tiron_profit_state( $evaluation );
		return;
	}
	$data = $evaluation[0];
	foreach ( $data['materials'] as &$row ) {
		$row['supplier'] = sanitize_text_field( $row['supplier'] );
		$row['unit'] = sanitize_text_field( $row['unit'] );
	}
	unset( $row );
	if ( ! update_option( 'tiron_profit_v1', $data, false ) && $data !== get_option( 'tiron_profit_v1' ) ) {
		tiron_profit_state( [ $data, null, [ 'Salvarea a eșuat. Încearcă din nou.' ] ] );
		return;
	}
	wp_safe_redirect( add_query_arg( 'saved', '1', tiron_profit_url() ), 303 );
	exit;
}

function tiron_profit_format( int $units ): string {
	$cents = tiron_profit_divide( abs( $units ), 100 );
	$sign = $units < 0 && $cents ? '−' : '';
	return $sign . number_format( intdiv( $cents, 100 ), 0, ',', ' ' )
		. ',' . str_pad( (string) ( $cents % 100 ), 2, '0', STR_PAD_LEFT ) . ' RON';
}

function tiron_profit_display( $units ): string {
	return null === $units ? '—' : tiron_profit_format( $units );
}

function tiron_profit_page(): void {
	tiron_profit_require_admin();
	[ $data, $result, $errors ] = tiron_profit_state();
	$metrics = [
		'gross' => 'Marjă brută',
		'shipping' => 'Livrare suportată',
		'fee' => 'Comision plată',
		'advertising' => 'Achiziție client',
		'contribution' => 'Contribuție după costuri variabile',
		'break_even' => 'Publicitate la pragul de rentabilitate',
	];
	$categories = [
		'materials' => 'Materiale fără ambalaj', 'packaging' => 'Ambalaj',
		'electricity' => 'Energie', 'labour' => 'Manoperă', 'scrap' => 'Rebuturi', 'overhead' => 'Regie',
	];
	?>
	<div class="wrap tp" data-profit-studio>
		<header class="tp-hero">
			<div><p>Atelier TIR-ON</p><h1>Profit Studio</h1><p>Costuri de producție și scenarii pentru Luna.</p></div>
			<a href="#tp-inputs">Editează costurile</a>
		</header>
		<?php if ( $errors ) : ?>
			<div class="notice notice-error inline" role="alert">
				<p><strong>Verifică datele. Valorile anterioare sunt încă salvate.</strong></p>
				<ul><?php foreach ( $errors as $error ) : ?><li><?php echo esc_html( $error ); ?></li><?php endforeach; ?></ul>
			</div>
		<?php elseif ( '1' === ( $_GET['saved'] ?? '' ) ) : ?>
			<div class="notice notice-success inline" role="status"><p>Costurile au fost salvate.</p></div>
		<?php endif; ?>

		<section class="tp-section" aria-labelledby="tp-result-title">
			<h2 id="tp-result-title">Costul producției</h2>
			<p><?php echo $result ? 'Câmpurile goale au contribuție zero. Completează toate costurile aplicabile.' : 'Adaugă costurile tale pentru a vedea rezultate. Valorile pornesc de la câmpuri goale.'; ?></p>
			<dl class="tp-kpis">
				<div><dt>Cost / Luna</dt><dd><?php echo esc_html( tiron_profit_display( $result['production'] ?? null ) ); ?></dd></div>
				<?php foreach ( [ 6, 10, 20 ] as $size ) : ?>
					<div><dt>Lot de <?php echo (int) $size; ?> bucăți</dt><dd><?php echo esc_html( tiron_profit_display( $result['batches'][ $size ] ?? null ) ); ?></dd></div>
				<?php endforeach; ?>
			</dl>
			<dl class="tp-parts">
				<?php foreach ( $categories as $key => $label ) : ?>
					<div><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( tiron_profit_display( $result['parts'][ $key ] ?? null ) ); ?></dd></div>
				<?php endforeach; ?>
			</dl>
		</section>

		<section class="tp-section" aria-labelledby="tp-scenarios">
			<h2 id="tp-scenarios">Scenarii de preț</h2>
			<p>O Luna per comandă. Rezultate înainte de impozite. Un prag negativ indică o pierdere chiar și fără publicitate.</p>
			<div class="tp-scenarios">
				<?php foreach ( [ 249, 269, 299 ] as $price ) : ?>
					<article class="tp-scenario" data-scenario="<?php echo (int) $price; ?>">
						<h3><?php echo (int) $price; ?> RON <small><?php echo 249 === $price ? 'Referință' : 'Scenariu'; ?></small></h3>
						<dl>
							<?php foreach ( $metrics as $key => $label ) :
								$value = $result['scenarios'][ $price ][ $key ] ?? null;
								?>
								<div>
									<dt><?php echo esc_html( $label ); ?></dt>
									<dd data-metric="<?php echo esc_attr( $key ); ?>"<?php echo $value < 0 ? ' class="tp-negative"' : ''; ?>>
										<?php echo esc_html( tiron_profit_display( $value ) ); ?>
										<?php if ( 'gross' === $key && null !== $value ) : ?>
											<small><?php echo esc_html( number_format( $result['scenarios'][ $price ]['gross_rate'] / 100, 2, ',', ' ' ) . '%' ); ?></small>
										<?php endif; ?>
									</dd>
								</div>
							<?php endforeach; ?>
						</dl>
					</article>
				<?php endforeach; ?>
			</div>
		</section>

		<form class="tp-section" id="tp-inputs" method="post" action="<?php echo esc_url( tiron_profit_url() ); ?>">
			<?php wp_nonce_field( 'tiron_profit_save' ); ?>
			<div class="tp-form-head"><h2>Date de cost</h2><button type="submit" class="button button-primary">Calculează și salvează</button></div>
			<p>Folosește aceeași unitate la achiziție și consum, de exemplu g, m sau buc. Totalul plătit exclude transportul introdus separat.</p>
			<?php $fields = [
				'supplier' => 'Furnizor', 'bought' => 'Cant. cumpărată', 'unit' => 'Unitate',
				'paid' => 'Total plătit (RON)', 'freight' => 'Transport alocat (RON)', 'used' => 'Consum / Luna',
			]; ?>
			<table class="widefat tp-materials">
				<thead><tr><th scope="col">Componentă</th>
					<?php foreach ( $fields as $label ) : ?><th scope="col"><?php echo esc_html( $label ); ?></th><?php endforeach; ?>
					<th scope="col">Cost / Luna</th></tr></thead>
				<tbody>
					<?php foreach ( tiron_profit_components() as $key => $component ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( $component ); ?></th>
							<?php foreach ( $fields as $field => $label ) :
								$is_number = ! in_array( $field, [ 'supplier', 'unit' ], true );
								?>
								<td><label>
									<span class="tp-field-label"><?php echo esc_html( $label ); ?><span class="screen-reader-text"><?php echo esc_html( ' – ' . $component ); ?></span></span>
									<input type="text" maxlength="<?php echo $is_number ? 16 : ( 'supplier' === $field ? 160 : 24 ); ?>"
										<?php echo $is_number ? 'inputmode="decimal"' : ''; ?>
										name="<?php echo esc_attr( 'profit[materials][' . $key . '][' . $field . ']' ); ?>"
										value="<?php echo esc_attr( $data['materials'][ $key ][ $field ] ); ?>">
								</label></td>
							<?php endforeach; ?>
							<td class="tp-component-cost"><span class="tp-field-label">Cost / Luna</span><?php echo esc_html( tiron_profit_display( $result['components'][ $key ] ?? null ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<h3>Producție și vânzare</h3>
			<p>Ambalajul suplimentar se adaugă la ambalajul din listă. Rata de rebuturi se aplică materialelor fără ambalaj, energiei și manoperei.</p>
			<div class="tp-costs">
				<?php foreach ( tiron_profit_cost_fields() as $key => [ $label ] ) : ?>
					<label><?php echo esc_html( $label ); ?>
						<input type="text" inputmode="decimal" maxlength="16" name="<?php echo esc_attr( 'profit[costs][' . $key . ']' ); ?>"
							value="<?php echo esc_attr( $data['costs'][ $key ] ); ?>">
					</label>
				<?php endforeach; ?>
			</div>
			<p class="submit"><button type="submit" class="button button-primary">Calculează și salvează</button></p>
		</form>
	</div>
	<?php
}
