<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function aspen_wallet_register_shortcode_hooks() {
	add_shortcode( 'wallet_balance', 'aspen_wallet_shortcode_balance' );
	add_shortcode( 'wallet_if', 'aspen_wallet_shortcode_if' );
	add_shortcode( 'wallet_booking', 'aspen_wallet_shortcode_booking' );
}

function aspen_wallet_shortcode_balance( $atts ) {
	return '';
}

function aspen_wallet_shortcode_if( $atts, $content = '' ) {
	return do_shortcode( (string) $content );
}

function aspen_wallet_shortcode_booking( $atts ) {
	return '';
}
