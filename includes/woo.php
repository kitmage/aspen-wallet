<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'ASPEN_WALLET_PRODUCT_GRANTS_META_KEY' ) ) {
	define( 'ASPEN_WALLET_PRODUCT_GRANTS_META_KEY', '_aspen_wallet_grants' );
}

if ( ! defined( 'ASPEN_WALLET_PRODUCT_GRANTS_NONCE' ) ) {
	define( 'ASPEN_WALLET_PRODUCT_GRANTS_NONCE', 'aspen_wallet_product_grants_nonce' );
}

function aspen_wallet_register_woo_hooks() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	add_action( 'woocommerce_product_options_general_product_data', 'aspen_wallet_woo_render_product_wallet_fields' );
	add_action( 'woocommerce_process_product_meta', 'aspen_wallet_woo_save_product_wallet_meta', 10, 1 );

	add_action( 'woocommerce_variation_options', 'aspen_wallet_woo_render_variation_wallet_fields', 10, 3 );
	add_action( 'woocommerce_save_product_variation', 'aspen_wallet_woo_save_variation_wallet_meta', 10, 2 );
}

function aspen_wallet_get_grant_types() {
	return array(
		'one_time_grant'     => __( 'One-time grant', 'aspen-wallet' ),
		'subscription_reset' => __( 'Subscription reset', 'aspen-wallet' ),
	);
}

function aspen_wallet_woo_get_bucket_options() {
	$options = array();

	foreach ( aspen_wallet_get_buckets() as $bucket ) {
		$options[ $bucket['slug'] ] = $bucket['label'] . ' (' . $bucket['slug'] . ')';
	}

	return $options;
}

function aspen_wallet_woo_default_grant_row() {
	return array(
		'bucket' => '',
		'amount' => 0,
		'type'   => 'one_time_grant',
	);
}

function aspen_wallet_woo_get_product_grants( $product_id ) {
	$raw = get_post_meta( $product_id, ASPEN_WALLET_PRODUCT_GRANTS_META_KEY, true );
	return aspen_wallet_woo_normalize_grants( $raw );
}

function aspen_wallet_woo_normalize_grants( $raw_grants ) {
	$raw_grants = is_array( $raw_grants ) ? $raw_grants : array();
	$valid      = array();
	$types      = aspen_wallet_get_grant_types();
	$buckets    = aspen_wallet_woo_get_bucket_options();

	foreach ( $raw_grants as $raw_grant ) {
		if ( ! is_array( $raw_grant ) ) {
			continue;
		}

		$bucket = isset( $raw_grant['bucket'] ) ? aspen_wallet_sanitize_bucket_slug( $raw_grant['bucket'] ) : '';
		$type   = isset( $raw_grant['type'] ) ? sanitize_key( $raw_grant['type'] ) : '';
		$amount = isset( $raw_grant['amount'] ) ? absint( $raw_grant['amount'] ) : 0;

		if ( '' === $bucket || ! isset( $buckets[ $bucket ] ) ) {
			continue;
		}

		if ( ! isset( $types[ $type ] ) ) {
			continue;
		}

		$valid[] = array(
			'bucket' => $bucket,
			'amount' => $amount,
			'type'   => $type,
		);
	}

	return array_values( $valid );
}

function aspen_wallet_woo_render_product_wallet_fields() {
	global $post;

	if ( ! $post instanceof WP_Post ) {
		return;
	}

	$product = wc_get_product( $post->ID );
	if ( ! $product ) {
		return;
	}

	if ( ! $product->is_type( array( 'simple', 'variable', 'subscription', 'variable-subscription' ) ) ) {
		return;
	}

	$grants = aspen_wallet_woo_get_product_grants( $post->ID );
	if ( empty( $grants ) ) {
		$grants = array( aspen_wallet_woo_default_grant_row() );
	}

	aspen_wallet_woo_render_wallet_grants_panel( 'aspen_wallet_grants', $grants );
}

function aspen_wallet_woo_render_variation_wallet_fields( $loop, $variation_data, $variation ) {
	$grants = aspen_wallet_woo_get_product_grants( $variation->ID );
	if ( empty( $grants ) ) {
		$grants = array( aspen_wallet_woo_default_grant_row() );
	}

	echo '<div class="form-row form-row-full">';
	echo '<h4>' . esc_html__( 'Wallet Credits', 'aspen-wallet' ) . '</h4>';
	aspen_wallet_woo_render_wallet_grants_panel( 'aspen_wallet_variation_grants[' . absint( $loop ) . ']', $grants, 'variable_' . absint( $loop ) );
	echo '</div>';
}

function aspen_wallet_woo_render_wallet_grants_panel( $field_name, $grants, $uniq = 'product' ) {
	$bucket_options = aspen_wallet_woo_get_bucket_options();
	$grant_types    = aspen_wallet_get_grant_types();

	wp_nonce_field( 'aspen_wallet_save_product_grants', ASPEN_WALLET_PRODUCT_GRANTS_NONCE );

	echo '<p><strong>' . esc_html__( 'Wallet Credits', 'aspen-wallet' ) . '</strong></p>';
	echo '<p class="description">' . esc_html__( 'Each row grants credits to a bucket. Subscription reset rows set the balance on renewal; one-time grant rows add balance when order completes.', 'aspen-wallet' ) . '</p>';
	echo '<table class="widefat striped" style="max-width:900px">';
	echo '<thead><tr>';
	echo '<th>' . esc_html__( 'Bucket', 'aspen-wallet' ) . '</th>';
	echo '<th>' . esc_html__( 'Amount', 'aspen-wallet' ) . '</th>';
	echo '<th>' . esc_html__( 'Type', 'aspen-wallet' ) . '</th>';
	echo '</tr></thead><tbody>';

	foreach ( $grants as $index => $grant ) {
		$bucket = isset( $grant['bucket'] ) ? aspen_wallet_sanitize_bucket_slug( $grant['bucket'] ) : '';
		$amount = isset( $grant['amount'] ) ? absint( $grant['amount'] ) : 0;
		$type   = isset( $grant['type'] ) ? sanitize_key( $grant['type'] ) : 'one_time_grant';

		echo '<tr>';
		echo '<td><select name="' . esc_attr( $field_name ) . '[' . absint( $index ) . '][bucket]">';
		echo '<option value="">' . esc_html__( 'Select bucket', 'aspen-wallet' ) . '</option>';
		foreach ( $bucket_options as $slug => $label ) {
			echo '<option value="' . esc_attr( $slug ) . '" ' . selected( $bucket, $slug, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td>';

		echo '<td><input min="0" type="number" step="1" name="' . esc_attr( $field_name ) . '[' . absint( $index ) . '][amount]" value="' . esc_attr( (string) $amount ) . '" /></td>';

		echo '<td><select name="' . esc_attr( $field_name ) . '[' . absint( $index ) . '][type]">';
		foreach ( $grant_types as $grant_type => $grant_label ) {
			echo '<option value="' . esc_attr( $grant_type ) . '" ' . selected( $type, $grant_type, false ) . '>' . esc_html( $grant_label ) . '</option>';
		}
		echo '</select></td>';
		echo '</tr>';
	}

	$empty_row = aspen_wallet_woo_default_grant_row();
	$index     = count( $grants );
	echo '<tr>';
	echo '<td><select name="' . esc_attr( $field_name ) . '[' . absint( $index ) . '][bucket]">';
	echo '<option value="">' . esc_html__( 'Select bucket', 'aspen-wallet' ) . '</option>';
	foreach ( $bucket_options as $slug => $label ) {
		echo '<option value="' . esc_attr( $slug ) . '">' . esc_html( $label ) . '</option>';
	}
	echo '</select></td>';
	echo '<td><input min="0" type="number" step="1" name="' . esc_attr( $field_name ) . '[' . absint( $index ) . '][amount]" value="' . esc_attr( (string) $empty_row['amount'] ) . '" /></td>';
	echo '<td><select name="' . esc_attr( $field_name ) . '[' . absint( $index ) . '][type]">';
	foreach ( $grant_types as $grant_type => $grant_label ) {
		echo '<option value="' . esc_attr( $grant_type ) . '">' . esc_html( $grant_label ) . '</option>';
	}
	echo '</select></td>';
	echo '</tr>';

	echo '</tbody></table>';
}

function aspen_wallet_woo_save_product_wallet_meta( $product_id ) {
	if ( ! current_user_can( 'edit_product', $product_id ) ) {
		return;
	}

	if ( ! isset( $_POST[ ASPEN_WALLET_PRODUCT_GRANTS_NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ ASPEN_WALLET_PRODUCT_GRANTS_NONCE ] ) ), 'aspen_wallet_save_product_grants' ) ) {
		return;
	}

	$raw_grants = isset( $_POST['aspen_wallet_grants'] ) ? (array) wp_unslash( $_POST['aspen_wallet_grants'] ) : array();
	$grants     = aspen_wallet_woo_normalize_grants( $raw_grants );

	if ( empty( $grants ) ) {
		delete_post_meta( $product_id, ASPEN_WALLET_PRODUCT_GRANTS_META_KEY );
		return;
	}

	update_post_meta( $product_id, ASPEN_WALLET_PRODUCT_GRANTS_META_KEY, $grants );
}

function aspen_wallet_woo_save_variation_wallet_meta( $variation_id, $i ) {
	if ( ! current_user_can( 'edit_product', $variation_id ) ) {
		return;
	}

	if ( ! isset( $_POST[ ASPEN_WALLET_PRODUCT_GRANTS_NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ ASPEN_WALLET_PRODUCT_GRANTS_NONCE ] ) ), 'aspen_wallet_save_product_grants' ) ) {
		return;
	}

	$raw_variation_grants = isset( $_POST['aspen_wallet_variation_grants'] ) ? (array) wp_unslash( $_POST['aspen_wallet_variation_grants'] ) : array();
	$raw_grants           = isset( $raw_variation_grants[ $i ] ) && is_array( $raw_variation_grants[ $i ] ) ? $raw_variation_grants[ $i ] : array();
	$grants               = aspen_wallet_woo_normalize_grants( $raw_grants );

	if ( empty( $grants ) ) {
		delete_post_meta( $variation_id, ASPEN_WALLET_PRODUCT_GRANTS_META_KEY );
		return;
	}

	update_post_meta( $variation_id, ASPEN_WALLET_PRODUCT_GRANTS_META_KEY, $grants );
}
