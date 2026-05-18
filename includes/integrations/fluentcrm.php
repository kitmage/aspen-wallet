<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register FluentCRM hooks only when integration prerequisites are available.
 *
 * @return void
 */
function aspen_wallet_register_fluentcrm_hooks() {
	add_action( 'admin_notices', 'aspen_wallet_wallet_migration_notice' );

	if ( ! aspen_wallet_should_enable_fluentcrm_wallet_tab() ) {
		return;
	}

	if ( ! aspen_wallet_fluentcrm_is_available() ) {
		add_action( 'admin_notices', 'aspen_wallet_fluentcrm_missing_notice' );
		return;
	}

	add_action( 'fluent_crm/after_init', 'aspen_wallet_fluentcrm_register_profile_section', 20 );
	add_action( 'fluentcrm_loaded', 'aspen_wallet_fluentcrm_register_profile_section', 20 );
}

/**
 * Allow temporary migration period to disable FluentCRM wallet profile tab.
 *
 * @return bool
 */
function aspen_wallet_should_enable_fluentcrm_wallet_tab() {
	return (bool) apply_filters( 'aspen_wallet_enable_fluentcrm_wallet_tab', true );
}

/**
 * Check FluentCRM integration readiness.
 *
 * @return bool
 */
function aspen_wallet_fluentcrm_is_available() {
	if ( ! function_exists( 'FluentCrmApi' ) ) {
		return false;
	}

	$extender = FluentCrmApi( 'extender' );

	return ( is_object( $extender ) && method_exists( $extender, 'addProfileSection' ) );
}

/**
 * Render missing-integration admin notice.
 *
 * @return void
 */
function aspen_wallet_fluentcrm_missing_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	echo '<div class="notice notice-info"><p>';
	echo esc_html__( 'FluentCRM integration disabled; core Wallet UI available under Wallet menu and user profiles.', 'aspen-wallet' );
	echo '</p></div>';
}

function aspen_wallet_fluentcrm_register_profile_section() {
	static $registered = false;

	if ( $registered || ! aspen_wallet_fluentcrm_is_available() ) {
		return;
	}

	$extender = FluentCrmApi( 'extender' );

	$extender->addProfileSection(
		'aspen_wallet',
		__( 'Wallet', 'aspen-wallet' ),
		'aspen_wallet_fluentcrm_profile_section_callback',
		3
	);

	$registered = true;
}

function aspen_wallet_fluentcrm_profile_section_callback( $content, $subscriber ) {
	$content_arr = is_array( $content ) ? $content : array();
	$user_id     = aspen_wallet_fluentcrm_get_wp_user_id_from_subscriber( $subscriber );
	$buckets     = aspen_wallet_get_buckets();

	$content_arr['heading']      = __( 'Wallet Balances', 'aspen-wallet' );
	$content_arr['content_html'] = aspen_wallet_fluentcrm_render_wallet_html( $user_id, $buckets );

	return $content_arr;
}

function aspen_wallet_fluentcrm_get_wp_user_id_from_subscriber( $subscriber ) {
	if ( ! is_object( $subscriber ) ) {
		return 0;
	}

	if ( isset( $subscriber->user_id ) ) {
		return (int) $subscriber->user_id;
	}

	if ( isset( $subscriber->wp_user_id ) ) {
		return (int) $subscriber->wp_user_id;
	}

	if ( method_exists( $subscriber, 'getUserId' ) ) {
		return (int) $subscriber->getUserId();
	}

	return 0;
}

function aspen_wallet_fluentcrm_render_wallet_html( $user_id, $buckets ) {
	$wallet_admin_url = admin_url( 'admin.php?page=aspen-wallet-users' );
	$user_edit_url    = ( $user_id > 0 ) ? get_edit_user_link( $user_id ) : '';

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
	echo '<p>' . esc_html__( 'Wallet editing is now centralized in the Wallet admin screens and WordPress user edit pages.', 'aspen-wallet' ) . '</p>';
	echo '<p><a class="button button-secondary" href="' . esc_url( $wallet_admin_url ) . '">' . esc_html__( 'Open Wallet User Balances', 'aspen-wallet' ) . '</a> ';
	if ( '' !== $user_edit_url ) {
		echo '<a class="button button-secondary" href="' . esc_url( $user_edit_url ) . '">' . esc_html__( 'Open WordPress User Profile', 'aspen-wallet' ) . '</a>';
	}
	echo '</p>';
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

/**
 * Admin notice for wallet management migration.
 *
 * @return void
 */
function aspen_wallet_wallet_migration_notice() {
	if ( ! current_user_can( 'edit_users' ) ) {
		return;
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'plugins', 'settings_page_aspen-wallet', 'toplevel_page_aspen-wallet-users', 'user-edit', 'profile' ), true ) ) {
		return;
	}

	$wallet_url = admin_url( 'admin.php?page=aspen-wallet-users' );
	$users_url  = admin_url( 'users.php' );

	echo '<div class="notice notice-info"><p>';
	echo esc_html__( 'Aspen Wallet update: wallet balance editing moved to Wallet admin and WordPress user profile screens.', 'aspen-wallet' );
	echo ' ';
	echo '<a href="' . esc_url( $wallet_url ) . '">' . esc_html__( 'Wallet admin', 'aspen-wallet' ) . '</a>';
	echo ' · ';
	echo '<a href="' . esc_url( $users_url ) . '">' . esc_html__( 'Users', 'aspen-wallet' ) . '</a>';
	echo ' ';
	echo esc_html__( 'The FluentCRM Wallet tab is now optional and can be disabled via filter.', 'aspen-wallet' );
	echo '</p></div>';
}
