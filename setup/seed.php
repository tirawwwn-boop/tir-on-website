<?php
/**
 * Initial catalog and settings for a new TIR-ON development installation.
 * Run after WooCommerce activation: wp eval-file setup/seed.php.
 */

defined( 'ABSPATH' ) || exit;

if ( get_option( 'tiron_local_seed_v1' ) ) {
	echo "Catalogul local este deja configurat.\n";
	return;
}
if ( ! class_exists( 'WC_Product_Simple' ) ) {
	throw new RuntimeException( 'Activează WooCommerce înainte de inițializarea catalogului.' );
}

/** Reuse the existing WooCommerce page when the installer has already created it. */
$ensure_page = static function ( $option, $slug, $title, $content = '' ) {
	$existing = absint( get_option( $option ) );
	$page     = $existing ? get_post( $existing ) : null;
	if ( ! $page || 'page' !== $page->post_type || 'trash' === $page->post_status ) {
		$page = get_page_by_path( $slug );
	}
	$values = array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => $title,
		'post_name'    => $slug,
		'post_content' => $content,
	);
	if ( $page ) {
		$values['ID'] = $page->ID;
	}
	$id = wp_insert_post( $values, true );
	if ( is_wp_error( $id ) ) {
		throw new RuntimeException( $id->get_error_message() );
	}
	update_option( $option, $id );
};

$ensure_page( 'page_on_front', 'acasa', 'Acasă' );
$ensure_page( 'woocommerce_shop_page_id', 'magazin', 'Magazin' );
$ensure_page( 'woocommerce_cart_page_id', 'cos', 'Coș', '[woocommerce_cart]' );
$ensure_page( 'woocommerce_checkout_page_id', 'finalizare-comanda', 'Finalizare comandă', '[woocommerce_checkout]' );
$ensure_page( 'woocommerce_myaccount_page_id', 'contul-meu', 'Contul meu', '[woocommerce_my_account]' );

update_option( 'show_on_front', 'page' );
update_option( 'blogdescription', 'Turning Memories ON' );
update_option( 'timezone_string', 'Europe/Bucharest' );
update_option( 'blog_public', '0' );
update_option( 'woocommerce_currency', 'RON' );
update_option( 'woocommerce_currency_pos', 'right_space' );
update_option( 'woocommerce_price_decimal_sep', ',' );
update_option( 'woocommerce_price_thousand_sep', '.' );
update_option( 'woocommerce_default_country', 'RO' );
update_option( 'woocommerce_allowed_countries', 'specific' );
update_option( 'woocommerce_specific_allowed_countries', array( 'RO' ) );
update_option( 'woocommerce_enable_guest_checkout', 'yes' );
update_option( 'woocommerce_enable_checkout_login_reminder', 'no' );
update_option( 'woocommerce_enable_signup_and_login_from_checkout', 'no' );
update_option( 'woocommerce_allow_tracking', 'no' );
update_option( 'woocommerce_feature_order_attribution_enabled', 'no' );
update_option( 'woocommerce_store_pages_only', 'no' );
update_option( 'woocommerce_coming_soon', 'no' );

global $wp_rewrite;
$wp_rewrite->set_permalink_structure( '/%postname%/' );
$permalinks                  = (array) get_option( 'woocommerce_permalinks', array() );
$permalinks['product_base']   = '/produs';
update_option( 'woocommerce_permalinks', $permalinks );

$product_id = wc_get_product_id_by_sku( 'TIR-ON-LUNA' );
if ( ! $product_id ) {
	$product = new WC_Product_Simple();
	$product->set_name( 'TIR-ON Luna' );
	$product->set_slug( 'luna' );
	$product->set_sku( 'TIR-ON-LUNA' );
	$product->set_status( 'publish' );
	$product->set_regular_price( '299' );
	$product->set_catalog_visibility( 'visible' );
	$product->set_short_description( 'Fotografia ta prinde formă într-un litofan circular, luminat din spate de un LED alb-cald.' );
	$product->set_description( 'Un disc luminos pe un suport sculptural curbat și o bază joasă, cu colțuri rotunjite. Textura în relief redă fotografia în nuanțe de lumină.' );
	$product->update_meta_data( '_tiron_diameter', 'aprox. 134,5 mm' );
	$product->update_meta_data( '_tiron_power', 'Alimentare prin cablu' );
	$product->update_meta_data( '_tiron_light', 'LED alb-cald' );
	$product->update_meta_data( '_tiron_finish', 'Espresso mat' );
	$product->save();
}
update_option( 'tiron_luna_product_id', wc_get_product_id_by_sku( 'TIR-ON-LUNA' ) );
flush_rewrite_rules( false );
update_option( 'tiron_local_seed_v1', 1, false );
echo "Catalogul TIR-ON a fost configurat.\n";
