<?php
/**
 * Plugin Name: TIR-ON — Pregătire Hostico
 * Description: Configurare din browser și protecții pentru instalarea privată de verificare.
 */
defined( 'ABSPATH' ) || exit;

// This release is for private validation. WooCommerce can render and preview emails.
add_filter( 'pre_option_blog_public', static fn() => '0', PHP_INT_MAX );
add_filter( 'pre_wp_mail', '__return_true', PHP_INT_MAX );

add_action( 'admin_menu', static function () {
	add_management_page( 'Instalare TIR-ON', 'Instalare TIR-ON', 'manage_options', 'tiron-hostico', 'tiron_hostico_setup_page' );
} );

function tiron_hostico_checks(): array {
	return array(
		'Configurarea Hostico este introdusă în wp-config.php' => defined( 'TIRON_HOSTICO_READINESS' ) && true === TIRON_HOSTICO_READINESS,
		'WooCommerce este activ' => class_exists( 'WC_Product_Simple' ),
		'Tema TIR-ON este activă' => 'tir-on' === get_stylesheet(),
		'Stocarea privată și procesarea JPG, PNG și WebP sunt disponibile' =>
			function_exists( 'tiron_photo_processing_ready' ) && tiron_photo_processing_ready()
			&& true === tiron_with_photo_lock( static fn() => true ),
		'Protecțiile Stripe TEST sunt încărcate' => function_exists( 'tiron_test_stripe_settings' ),
		'Toate fișierele de instalare sunt prezente' => is_readable( __DIR__ . '/tiron/hostico/seed.php' )
			&& is_readable( __DIR__ . '/tiron/hostico/emails.php' ),
	);
}

function tiron_hostico_setup_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$checks = tiron_hostico_checks();
	$seeded = (bool) get_option( 'tiron_local_seed_v1' );
	?>
	<div class="wrap">
		<h1>Instalare TIR-ON</h1>
		<p>Instalare privată de verificare. Indexarea și trimiterea e-mailurilor sunt oprite.</p>
		<ul>
			<?php foreach ( $checks as $label => $ok ) : ?>
				<li><?php echo esc_html( ( $ok ? 'OK — ' : 'Verifică — ' ) . $label ); ?></li>
			<?php endforeach; ?>
		</ul>
		<?php if ( $seeded ) : ?>
			<p>Catalogul a fost configurat. Verifică produsul Luna, livrarea și pagina de finalizare în WooCommerce.</p>
		<?php elseif ( ! in_array( false, $checks, true ) ) : ?>
			<p>Folosește această configurare pe o instalare nouă. Sunt pregătite paginile WooCommerce și produsul Luna la 249 RON.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="tiron_hostico_setup">
				<?php wp_nonce_field( 'tiron_hostico_setup' ); ?>
				<?php submit_button( 'Configurează magazinul' ); ?>
			</form>
		<?php endif; ?>
	</div>
	<?php
}

add_action( 'admin_post_tiron_hostico_setup', static function () {
	if ( ! current_user_can( 'manage_options' ) || 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		wp_die( 'Acces restricționat.', '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'tiron_hostico_setup' );
	if ( in_array( false, tiron_hostico_checks(), true ) ) {
		wp_die( 'Verifică cerințele din Instrumente → Instalare TIR-ON.', '', array( 'response' => 409 ) );
	}
	if ( ! get_option( 'tiron_local_seed_v1' ) ) {
		// The same seed is used by the local installer. A catalogue or orders must not be overwritten.
		$products = wc_get_products( array( 'limit' => 1, 'return' => 'ids', 'status' => array( 'publish', 'draft', 'pending', 'private', 'future' ) ) );
		if ( $products || wc_get_orders( array( 'limit' => 1, 'return' => 'ids' ) ) ) {
			wp_die( 'Există deja produse sau comenzi. Folosește configurarea manuală din ghid.', '', array( 'response' => 409 ) );
		}
		if ( ! add_option( 'tiron_hostico_setup_lock', '1', '', false ) ) {
			wp_die( 'Configurarea este deja în curs. Revino la pagina de instalare.', '', array( 'response' => 409 ) );
		}
		ob_start();
		$failed = false;
		try {
			require __DIR__ . '/tiron/hostico/seed.php';
			require __DIR__ . '/tiron/hostico/emails.php';
			tiron_hostico_seed_emails();
		} catch ( Throwable $error ) {
			$failed = true;
		} finally {
			ob_end_clean();
			delete_option( 'tiron_hostico_setup_lock' );
		}
		if ( $failed ) {
			wp_die( 'Configurarea a fost întreruptă. Verifică paginile și produsul înainte de o nouă încercare.', '', array( 'response' => 500 ) );
		}
	}
	wp_safe_redirect( admin_url( 'tools.php?page=tiron-hostico' ) );
	exit;
} );
