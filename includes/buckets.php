<?php
/**
 * Bucket config helpers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function aspen_wallet_get_buckets() {
	$buckets = get_option( 'aspen_wallet_buckets', array() );
	return is_array( $buckets ) ? $buckets : array();
}

function aspen_wallet_save_buckets( $buckets ) {
	$clean = array();

	if ( ! is_array( $buckets ) ) {
		return false;
	}

	foreach ( $buckets as $bucket ) {
		$slug = isset( $bucket['slug'] ) ? aspen_wallet_sanitize_bucket_slug( $bucket['slug'] ) : '';
		if ( '' === $slug ) {
			continue;
		}

		$clean[] = array(
			'label'       => isset( $bucket['label'] ) ? sanitize_text_field( wp_unslash( $bucket['label'] ) ) : $slug,
			'slug'        => $slug,
			'description' => isset( $bucket['description'] ) ? sanitize_textarea_field( wp_unslash( $bucket['description'] ) ) : '',
		);
	}

	return update_option( 'aspen_wallet_buckets', $clean, false );
}
