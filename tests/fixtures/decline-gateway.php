<?php
/** QA only. Enable the decline scenario on a disposable local WooCommerce instance. */
defined( 'ABSPATH' ) || exit;

if ( 'local' === wp_get_environment_type() ) {
	add_filter( 'tiron_local_test_decline', static fn() => '1' === get_option( 'tiron_qa_decline' ) );
}
