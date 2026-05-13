<?php
/**
 * Shared sanitizers and balance helpers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Convert mixed value into safe integer >= 0.
 *
 * @param mixed $value Raw value.
 * @return int
 */
function aspen_wallet_to_int( $value ) {
	if ( is_bool( $value ) || is_array( $value ) || is_object( $value ) ) {
		return 0;
	}

	$value = is_string( $value ) ? wp_unslash( $value ) : $value;
	$value = is_string( $value ) ? sanitize_text_field( $value ) : $value;

	return max( 0, (int) $value );
}

/**
 * Sanitize bucket slug.
 *
 * @param string $slug Raw slug.
 * @return string
 */
function aspen_wallet_sanitize_bucket_slug( $slug ) {
	return sanitize_title( wp_unslash( (string) $slug ) );
}

/**
 * Build user meta key for bucket.
 *
 * @param string $bucket Bucket slug.
 * @return string
 */
function aspen_wallet_bucket_meta_key( $bucket ) {
	$bucket = aspen_wallet_sanitize_bucket_slug( $bucket );
	return '_user_wallet_bucket_' . str_replace( '-', '_', $bucket );
}

function aspen_wallet_get_balance( $user_id, $bucket ) {
	$meta_key = aspen_wallet_bucket_meta_key( $bucket );
	$balance  = get_user_meta( (int) $user_id, $meta_key, true );
	return aspen_wallet_to_int( $balance );
}

function aspen_wallet_set_balance( $user_id, $bucket, $amount ) {
	$meta_key = aspen_wallet_bucket_meta_key( $bucket );
	return update_user_meta( (int) $user_id, $meta_key, aspen_wallet_to_int( $amount ) );
}

function aspen_wallet_add_balance( $user_id, $bucket, $amount ) {
	$current = aspen_wallet_get_balance( $user_id, $bucket );
	return aspen_wallet_set_balance( $user_id, $bucket, $current + aspen_wallet_to_int( $amount ) );
}
