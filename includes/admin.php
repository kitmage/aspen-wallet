<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function aspen_wallet_register_admin_hooks() {
	add_action( 'admin_menu', 'aspen_wallet_register_admin_menu' );
	add_action( 'admin_post_aspen_wallet_save_buckets', 'aspen_wallet_handle_save_buckets' );
}

function aspen_wallet_register_admin_menu() {
	add_submenu_page(
		'options-general.php',
		__( 'Wallet', 'aspen-wallet' ),
		__( 'Wallet', 'aspen-wallet' ),
		'manage_options',
		'aspen-wallet',
		'aspen_wallet_render_admin_page'
	);
}

function aspen_wallet_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'aspen-wallet' ) );
	}
	echo '<div class="wrap"><h1>' . esc_html__( 'Wallet Buckets', 'aspen-wallet' ) . '</h1></div>';
}

function aspen_wallet_handle_save_buckets() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to save wallet settings.', 'aspen-wallet' ) );
	}

	check_admin_referer( 'aspen_wallet_save_buckets' );
	$buckets = isset( $_POST['buckets'] ) ? (array) $_POST['buckets'] : array();
	aspen_wallet_save_buckets( $buckets );

	wp_safe_redirect( admin_url( 'options-general.php?page=aspen-wallet' ) );
	exit;
}
