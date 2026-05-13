<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function aspen_wallet_register_woo_hooks() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	add_action( 'woocommerce_process_product_meta', 'aspen_wallet_woo_save_product_wallet_meta', 10, 1 );
}

function aspen_wallet_woo_save_product_wallet_meta( $product_id ) {
	if ( ! current_user_can( 'edit_product', $product_id ) ) {
		return;
	}
}
