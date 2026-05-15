<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function aspen_wallet_register_fluentcrm_hooks() {
	if ( ! defined( 'FLUENTCRM' ) ) {
		return;
	}

	add_action( 'fluent_crm/after_init', 'aspen_wallet_fluentcrm_register_profile_section', 20 );
	add_action( 'fluentcrm_loaded', 'aspen_wallet_fluentcrm_register_profile_section', 20 );
	add_filter( 'fluentcrm_profile_sections', 'aspen_wallet_fluentcrm_add_profile_tab', 20, 1 );
	add_action( 'admin_enqueue_scripts', 'aspen_wallet_fluentcrm_enqueue_route_fix' );
}

function aspen_wallet_fluentcrm_has_extender_profile_api() {
	if ( ! function_exists( 'FluentCrmApi' ) ) {
		return false;
	}

	$extender = FluentCrmApi( 'extender' );
	return $extender && method_exists( $extender, 'addProfileSection' );
}

function aspen_wallet_fluentcrm_register_profile_section() {
	if ( ! function_exists( 'FluentCrmApi' ) ) {
		return;
	}

	if ( ! aspen_wallet_fluentcrm_has_extender_profile_api() ) {
		return;
	}

	$extender = FluentCrmApi( 'extender' );

	$extender->addProfileSection(
		'fluentcrm_sub_info_body',
		__( 'Wallet', 'aspen-wallet' ),
		'aspen_wallet_fluentcrm_profile_section_callback'
	);
}

function aspen_wallet_fluentcrm_add_profile_tab( $sections ) {
	if ( ! is_array( $sections ) ) {
		$sections = array();
	}

	$sections['aspen_wallet'] = array(
		'slug'  => 'aspen_wallet',
		'title' => __( 'Wallet', 'aspen-wallet' ),
		'icon'  => 'el-icon-wallet',
	);

	return $sections;
}


function aspen_wallet_fluentcrm_profile_section_callback( $content, $subscriber ) {
	$content_arr = is_array( $content ) ? $content : array();
	$user_id     = aspen_wallet_fluentcrm_get_wp_user_id_from_subscriber( $subscriber );
	$buckets     = aspen_wallet_get_buckets();

	$content_arr['heading'] = __( 'Wallet Balances', 'aspen-wallet' );
	$content_arr['content_html'] = aspen_wallet_fluentcrm_render_wallet_html( $user_id, $buckets );

	return $content_arr;
}

function aspen_wallet_fluentcrm_get_wp_user_id_from_subscriber( $subscriber ) {
	$resolved_user_id = 0;

	if ( ! is_object( $subscriber ) ) {
		return $resolved_user_id;
	}

	if ( isset( $subscriber->user_id ) ) {
		$resolved_user_id = (int) $subscriber->user_id;
	} elseif ( isset( $subscriber->wp_user_id ) ) {
		$resolved_user_id = (int) $subscriber->wp_user_id;
	} elseif ( method_exists( $subscriber, 'getUserId' ) ) {
		$resolved_user_id = (int) $subscriber->getUserId();
	}

	return $resolved_user_id;
}

/**
 * Back-compat no-op for older deployments that may still register this callback.
 *
 * @param string $hook_suffix Current admin hook suffix.
 * @return void
 */
function aspen_wallet_fluentcrm_enqueue_route_fix( $hook_suffix ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
	// Intentionally left blank.
}


function aspen_wallet_fluentcrm_render_wallet_html( $user_id, $buckets ) {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'fluentcrm_manage_contacts' ) ) {
		return '<p>' . esc_html__( 'You do not have permission to manage wallet balances.', 'aspen-wallet' ) . '</p>';
	}

	if ( $user_id <= 0 ) {
		return '<p>' . esc_html__( 'This contact is not mapped to a WordPress user, so wallet balances cannot be edited.', 'aspen-wallet' ) . '</p>';
	}

	if ( empty( $buckets ) ) {
		return '<p>' . esc_html__( 'No wallet buckets configured yet.', 'aspen-wallet' ) . '</p>';
	}

	if ( isset( $_POST['aspen_wallet_fcrm_action'] ) && 'save_wallet' === sanitize_text_field( wp_unslash( $_POST['aspen_wallet_fcrm_action'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$nonce_ok = isset( $_POST['aspen_wallet_fcrm_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aspen_wallet_fcrm_nonce'] ) ), 'aspen_wallet_fcrm_save' );
		if ( $nonce_ok ) {
			$values = isset( $_POST['aspen_wallet_balances'] ) && is_array( $_POST['aspen_wallet_balances'] ) ? wp_unslash( $_POST['aspen_wallet_balances'] ) : array();
			foreach ( $buckets as $bucket ) {
				$slug   = $bucket['slug'];
				$amount = isset( $values[ $slug ] ) ? aspen_wallet_to_int( $values[ $slug ] ) : 0;
				wallet_set_balance( $user_id, $slug, $amount );
			}
		}
	}

	ob_start();
	echo '<form method="post">';
	echo '<input type="hidden" name="aspen_wallet_fcrm_action" value="save_wallet" />';
	wp_nonce_field( 'aspen_wallet_fcrm_save', 'aspen_wallet_fcrm_nonce' );
	echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Bucket', 'aspen-wallet' ) . '</th><th>' . esc_html__( 'Balance', 'aspen-wallet' ) . '</th></tr></thead><tbody>';

	foreach ( $buckets as $bucket ) {
		$slug    = $bucket['slug'];
		$label   = isset( $bucket['label'] ) ? $bucket['label'] : $slug;
		$balance = wallet_get_balance( $user_id, $slug );
		echo '<tr>';
		echo '<td><strong>' . esc_html( $label ) . '</strong><br /><code>' . esc_html( $slug ) . '</code></td>';
		echo '<td><input type="number" min="0" step="1" name="aspen_wallet_balances[' . esc_attr( $slug ) . ']" value="' . esc_attr( $balance ) . '" class="small-text" /></td>';
		echo '</tr>';
	}

	echo '</tbody></table>';
	echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Save Wallet Balances', 'aspen-wallet' ) . '</button></p>';
	echo '</form>';

	return (string) ob_get_clean();
}
