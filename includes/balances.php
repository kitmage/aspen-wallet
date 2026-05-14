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

/**
 * Normalize and de-duplicate a bucket list while preserving order.
 *
 * @param mixed $buckets Bucket list.
 * @return string[]
 */
function aspen_wallet_normalize_bucket_list( $buckets ) {
	if ( ! is_array( $buckets ) ) {
		$buckets = array();
	}

	$normalized = array();

	foreach ( $buckets as $bucket ) {
		$slug = aspen_wallet_sanitize_bucket_slug( $bucket );

		if ( '' === $slug || isset( $normalized[ $slug ] ) ) {
			continue;
		}

		$normalized[ $slug ] = $slug;
	}

	return array_values( $normalized );
}

/**
 * Get a bucket balance.
 *
 * @param mixed $user_id User ID.
 * @param mixed $bucket  Bucket slug.
 * @return int
 */
function wallet_get_balance( $user_id, $bucket ) {
	$user_id = (int) $user_id;
	$bucket  = aspen_wallet_sanitize_bucket_slug( $bucket );

	if ( $user_id <= 0 || '' === $bucket ) {
		return 0;
	}

	$meta_key = aspen_wallet_bucket_meta_key( $bucket );
	$balance  = get_user_meta( $user_id, $meta_key, true );

	return aspen_wallet_to_int( $balance );
}

/**
 * Set a bucket balance.
 *
 * @param mixed $user_id User ID.
 * @param mixed $bucket  Bucket slug.
 * @param mixed $amount  Balance to set.
 * @return bool
 */
function wallet_set_balance( $user_id, $bucket, $amount ) {
	$user_id = (int) $user_id;
	$bucket  = aspen_wallet_sanitize_bucket_slug( $bucket );

	if ( $user_id <= 0 || '' === $bucket ) {
		return false;
	}

	$meta_key = aspen_wallet_bucket_meta_key( $bucket );
	$amount   = aspen_wallet_to_int( $amount );

	return false !== update_user_meta( $user_id, $meta_key, $amount );
}

/**
 * Add credits to a bucket.
 *
 * @param mixed $user_id User ID.
 * @param mixed $bucket  Bucket slug.
 * @param mixed $amount  Amount to add.
 * @return bool
 */
function wallet_add_balance( $user_id, $bucket, $amount ) {
	$user_id = (int) $user_id;
	$amount  = aspen_wallet_to_int( $amount );

	if ( $user_id <= 0 ) {
		return false;
	}

	if ( $amount <= 0 ) {
		return true;
	}

	$current = wallet_get_balance( $user_id, $bucket );
	return wallet_set_balance( $user_id, $bucket, $current + $amount );
}

/**
 * Determine if a user can afford a debit from a bucket list.
 *
 * @param mixed $user_id User ID.
 * @param mixed $buckets Ordered list of allowed bucket slugs.
 * @param mixed $amount  Required amount.
 * @return bool
 */
function wallet_can_afford( $user_id, $buckets, $amount ) {
	$user_id = (int) $user_id;
	$amount  = aspen_wallet_to_int( $amount );
	$buckets = aspen_wallet_normalize_bucket_list( $buckets );

	if ( $user_id <= 0 || empty( $buckets ) ) {
		return false;
	}

	if ( 0 === $amount ) {
		return true;
	}

	$total = 0;

	foreach ( $buckets as $bucket ) {
		$total += wallet_get_balance( $user_id, $bucket );

		if ( $total >= $amount ) {
			return true;
		}
	}

	return false;
}

/**
 * Debit credits from buckets in strict priority order.
 *
 * @param mixed $user_id User ID.
 * @param mixed $buckets Ordered bucket slugs.
 * @param mixed $amount  Debit amount.
 * @return array<string,mixed>
 */
function wallet_debit_balances( $user_id, $buckets, $amount ) {
	$user_id = (int) $user_id;
	$amount  = aspen_wallet_to_int( $amount );
	$buckets = aspen_wallet_normalize_bucket_list( $buckets );

	$result = array(
		'success' => false,
		'requested_amount' => $amount,
		'debited_amount' => 0,
		'remaining_amount' => $amount,
		'deltas' => array(),
	);

	if ( $user_id <= 0 || empty( $buckets ) ) {
		return $result;
	}

	if ( 0 === $amount ) {
		$result['success']          = true;
		$result['remaining_amount'] = 0;
		return $result;
	}

	if ( ! wallet_can_afford( $user_id, $buckets, $amount ) ) {
		return $result;
	}

	$remaining = $amount;

	foreach ( $buckets as $bucket ) {
		if ( $remaining <= 0 ) {
			break;
		}

		$current = wallet_get_balance( $user_id, $bucket );
		if ( $current <= 0 ) {
			continue;
		}

		$debit = min( $current, $remaining );
		$next  = $current - $debit;

		if ( ! wallet_set_balance( $user_id, $bucket, $next ) ) {
			return $result;
		}

		$result['deltas'][ $bucket ] = 0 - $debit;
		$remaining                  -= $debit;
	}

	$result['debited_amount']   = $amount - $remaining;
	$result['remaining_amount'] = $remaining;
	$result['success']          = ( 0 === $remaining );

	return $result;
}

/**
 * Format a raw integer balance for display.
 *
 * @param mixed $amount    Raw amount.
 * @param mixed $divide_by Divisor.
 * @param mixed $decimals  Decimal precision.
 * @return string
 */
function wallet_format_balance( $amount, $divide_by = 1, $decimals = 0 ) {
	$amount    = aspen_wallet_to_int( $amount );
	$divide_by = aspen_wallet_to_int( $divide_by );
	$decimals  = min( 6, aspen_wallet_to_int( $decimals ) );

	if ( $divide_by <= 0 ) {
		$divide_by = 1;
	}

	$value = $amount / $divide_by;

	return number_format_i18n( $value, $decimals );
}

// Back-compat wrappers.
function aspen_wallet_get_balance( $user_id, $bucket ) {
	return wallet_get_balance( $user_id, $bucket );
}

function aspen_wallet_set_balance( $user_id, $bucket, $amount ) {
	return wallet_set_balance( $user_id, $bucket, $amount );
}

function aspen_wallet_add_balance( $user_id, $bucket, $amount ) {
	return wallet_add_balance( $user_id, $bucket, $amount );
}
