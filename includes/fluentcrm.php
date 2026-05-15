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
	add_action( 'fluentcrm_loaded', 'aspen_wallet_fluentcrm_register_fallback_hooks', 30 );
}

function aspen_wallet_fluentcrm_register_fallback_hooks() {
	if ( aspen_wallet_fluentcrm_has_extender_profile_api() ) {
		return;
	}

	add_filter( 'fluentcrm_profile_nav', 'aspen_wallet_fluentcrm_fallback_register_wallet_nav' );
	add_filter( 'fluentcrm_profile_sections', 'aspen_wallet_fluentcrm_fallback_register_wallet_section' );
	add_action( 'fluentcrm_profile_section_content_wallet', 'aspen_wallet_fluentcrm_fallback_render_wallet_section' );
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
		'wallet',
		__( 'Wallet', 'aspen-wallet' ),
		'aspen_wallet_fluentcrm_profile_section_callback'
	);
}

function aspen_wallet_fluentcrm_fallback_register_wallet_nav( $nav ) {
	if ( aspen_wallet_fluentcrm_has_extender_profile_api() ) {
		return $nav;
	}

	$sample_item = array();
	if ( is_array( $nav ) && isset( $nav[0] ) && is_array( $nav[0] ) ) {
		$sample_item = $nav[0];
	} elseif ( is_array( $nav ) ) {
		$first = reset( $nav );
		if ( is_array( $first ) ) {
			$sample_item = $first;
		}
	}

	if ( ! is_array( $nav ) ) {
		$nav = array();
	}

	$wallet_item = $sample_item;
	$wallet_item = array_merge( $wallet_item, array(
		'key'      => 'wallet',
		'slug'     => 'wallet',
		'title'    => __( 'Wallet', 'aspen-wallet' ),
		'label'    => __( 'Wallet', 'aspen-wallet' ),
		'name'     => __( 'Wallet', 'aspen-wallet' ),
		'hash'     => 'wallet',
		'route'    => 'wallet',
		'priority' => 80,
	) );

	$nav[] = $wallet_item;

	return $nav;
}

function aspen_wallet_fluentcrm_fallback_register_wallet_section( $sections ) {
	if ( aspen_wallet_fluentcrm_has_extender_profile_api() ) {
		return $sections;
	}

	$sample_section = array();
	if ( is_array( $sections ) && isset( $sections[0] ) && is_array( $sections[0] ) ) {
		$sample_section = $sections[0];
	} elseif ( is_array( $sections ) ) {
		$first = reset( $sections );
		if ( is_array( $first ) ) {
			$sample_section = $first;
		}
	}

	if ( ! is_array( $sections ) ) {
		$sections = array();
	}

	$wallet_section = $sample_section;
	$wallet_section = array_merge( $wallet_section, array(
		'key'   => 'wallet',
		'slug'  => 'wallet',
		'title' => __( 'Wallet', 'aspen-wallet' ),
		'label' => __( 'Wallet', 'aspen-wallet' ),
		'name'  => __( 'Wallet', 'aspen-wallet' ),
		'hash'  => 'wallet',
		'route' => 'wallet',
	) );

	$sections[] = $wallet_section;

	return $sections;
}

function aspen_wallet_fluentcrm_fallback_render_wallet_section( $subscriber = null ) {
	if ( aspen_wallet_fluentcrm_has_extender_profile_api() ) {
		return;
	}

	$user_id = aspen_wallet_fluentcrm_get_wp_user_id_from_subscriber( $subscriber );
	echo aspen_wallet_fluentcrm_render_wallet_html( $user_id, aspen_wallet_get_buckets() );
}

add_action( 'fluentcrm_profile_section_wallet', 'aspen_wallet_fluentcrm_fallback_render_wallet_section' );
add_action( 'fluentcrm_profile_sections_content_wallet', 'aspen_wallet_fluentcrm_fallback_render_wallet_section' );

function aspen_wallet_fluentcrm_profile_section_callback( $content, $subscriber ) {
	$content_arr = is_array( $content ) ? $content : array();
	$user_id     = aspen_wallet_fluentcrm_get_wp_user_id_from_subscriber( $subscriber );
	$buckets     = aspen_wallet_get_buckets();

	$content_arr['heading'] = __( 'Wallet Balances', 'aspen-wallet' );
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
