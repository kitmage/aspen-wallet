<?php
/**
 * Bucket config helpers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function aspen_wallet_get_buckets() {
	$buckets = get_option( 'wallet_buckets', array() );
	if ( ! is_array( $buckets ) ) {
		return array();
	}

	$clean = array();
	foreach ( $buckets as $bucket ) {
		if ( ! is_array( $bucket ) ) {
			continue;
		}

		$slug = isset( $bucket['slug'] ) ? aspen_wallet_sanitize_bucket_slug( $bucket['slug'] ) : '';
		if ( '' === $slug ) {
			continue;
		}

		$clean[] = array(
			'label'       => isset( $bucket['label'] ) ? sanitize_text_field( $bucket['label'] ) : $slug,
			'slug'        => $slug,
			'description' => isset( $bucket['description'] ) ? sanitize_textarea_field( $bucket['description'] ) : '',
		);
	}

	return $clean;
}

function aspen_wallet_get_bucket_by_slug( $slug ) {
	$slug = aspen_wallet_sanitize_bucket_slug( $slug );
	if ( '' === $slug ) {
		return null;
	}

	foreach ( aspen_wallet_get_buckets() as $bucket ) {
		if ( $slug === $bucket['slug'] ) {
			return $bucket;
		}
	}

	return null;
}

function aspen_wallet_upsert_bucket( $bucket, $original_slug = '' ) {
	$bucket = is_array( $bucket ) ? $bucket : array();

	$label       = isset( $bucket['label'] ) ? sanitize_text_field( $bucket['label'] ) : '';
	$description = isset( $bucket['description'] ) ? sanitize_textarea_field( $bucket['description'] ) : '';
	$slug        = isset( $bucket['slug'] ) ? aspen_wallet_sanitize_bucket_slug( $bucket['slug'] ) : '';
	$original_slug = aspen_wallet_sanitize_bucket_slug( $original_slug );

	if ( '' === $slug ) {
		return new WP_Error( 'invalid_slug', __( 'Bucket slug is required.', 'aspen-wallet' ) );
	}

	if ( '' === $label ) {
		$label = $slug;
	}

	$buckets    = aspen_wallet_get_buckets();
	$next       = array();
	$did_update = false;

	foreach ( $buckets as $existing ) {
		if ( $existing['slug'] === $original_slug && '' !== $original_slug ) {
			$next[]     = array(
				'label'       => $label,
				'slug'        => $slug,
				'description' => $description,
			);
			$did_update = true;
			continue;
		}

		if ( $existing['slug'] === $slug && $existing['slug'] !== $original_slug ) {
			return new WP_Error( 'duplicate_slug', __( 'Bucket slug already exists.', 'aspen-wallet' ) );
		}

		$next[] = $existing;
	}

	if ( ! $did_update ) {
		$next[] = array(
			'label'       => $label,
			'slug'        => $slug,
			'description' => $description,
		);
	}

	update_option( 'wallet_buckets', array_values( $next ), false );
	return true;
}

function aspen_wallet_delete_bucket( $slug ) {
	$slug = aspen_wallet_sanitize_bucket_slug( $slug );
	if ( '' === $slug ) {
		return new WP_Error( 'invalid_slug', __( 'Bucket slug is required.', 'aspen-wallet' ) );
	}

	$buckets = aspen_wallet_get_buckets();
	$next    = array();
	$found   = false;

	foreach ( $buckets as $bucket ) {
		if ( $bucket['slug'] === $slug ) {
			$found = true;
			continue;
		}
		$next[] = $bucket;
	}

	if ( ! $found ) {
		return new WP_Error( 'not_found', __( 'Bucket not found.', 'aspen-wallet' ) );
	}

	update_option( 'wallet_buckets', array_values( $next ), false );
	return true;
}
