<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function aspen_wallet_register_fluentcrm_hooks() {
	if ( ! defined( 'FLUENTCRM' ) ) {
		return;
	}

	add_filter( 'fluentcrm_contact_profile_tabs', 'aspen_wallet_fluentcrm_register_wallet_tab' );
	add_filter( 'fluentcrm_contact_tabs', 'aspen_wallet_fluentcrm_register_wallet_tab' );

	add_action( 'fluentcrm_contact_profile_tab_content_wallet', 'aspen_wallet_fluentcrm_render_wallet_tab' );
	add_action( 'fluentcrm_contact_wallet_tab_content', 'aspen_wallet_fluentcrm_render_wallet_tab' );
}

function aspen_wallet_fluentcrm_register_wallet_tab( $tabs ) {
	if ( ! is_array( $tabs ) ) {
		$tabs = array();
	}

	$tabs['wallet'] = array(
		'title'    => __( 'Wallet', 'aspen-wallet' ),
		'priority' => 80,
	);

	return $tabs;
}

function aspen_wallet_fluentcrm_get_contact( $contact ) {
	if ( is_object( $contact ) ) {
		return $contact;
	}

	$contact_id = isset( $_GET['contact_id'] ) ? aspen_wallet_to_int( $_GET['contact_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( $contact_id <= 0 ) {
		return null;
	}

	if ( class_exists( '\\FluentCrm\\App\\Models\\Subscriber' ) ) {
		return \FluentCrm\App\Models\Subscriber::find( $contact_id );
	}

	return null;
}

function aspen_wallet_fluentcrm_get_wp_user_id_from_contact( $contact ) {
	if ( ! is_object( $contact ) ) {
		return 0;
	}

	if ( isset( $contact->user_id ) ) {
		return (int) $contact->user_id;
	}

	if ( isset( $contact->wp_user_id ) ) {
		return (int) $contact->wp_user_id;
	}

	if ( method_exists( $contact, 'getUserId' ) ) {
		return (int) $contact->getUserId();
	}

	return 0;
}

function aspen_wallet_fluentcrm_can_manage_wallet() {
	return current_user_can( 'manage_options' ) || current_user_can( 'fluentcrm_manage_contacts' );
}

function aspen_wallet_fluentcrm_render_wallet_tab( $contact = null ) {
	$contact    = aspen_wallet_fluentcrm_get_contact( $contact );
	$buckets    = aspen_wallet_get_buckets();
	$notice     = '';
	$notice_css = '';

	if ( ! aspen_wallet_fluentcrm_can_manage_wallet() ) {
		echo '<p>' . esc_html__( 'You do not have permission to manage wallet balances.', 'aspen-wallet' ) . '</p>';
		return;
	}

	if ( ! $contact ) {
		echo '<p>' . esc_html__( 'Unable to load FluentCRM contact.', 'aspen-wallet' ) . '</p>';
		return;
	}

	$user_id = aspen_wallet_fluentcrm_get_wp_user_id_from_contact( $contact );

	if ( isset( $_POST['aspen_wallet_fcrm_action'] ) && 'save_wallet' === sanitize_text_field( wp_unslash( $_POST['aspen_wallet_fcrm_action'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$nonce_ok = isset( $_POST['aspen_wallet_fcrm_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aspen_wallet_fcrm_nonce'] ) ), 'aspen_wallet_fcrm_save' );

		if ( ! $nonce_ok ) {
			$notice     = __( 'Security check failed. Please refresh and try again.', 'aspen-wallet' );
			$notice_css = 'notice notice-error';
		} elseif ( $user_id <= 0 ) {
			$notice     = __( 'This contact is not linked to a WordPress user.', 'aspen-wallet' );
			$notice_css = 'notice notice-error';
		} else {
			$values = isset( $_POST['aspen_wallet_balances'] ) && is_array( $_POST['aspen_wallet_balances'] ) ? wp_unslash( $_POST['aspen_wallet_balances'] ) : array();

			$save_error = false;
			foreach ( $buckets as $bucket ) {
				$slug   = $bucket['slug'];
				$amount = isset( $values[ $slug ] ) ? aspen_wallet_to_int( $values[ $slug ] ) : 0;

				if ( ! wallet_set_balance( $user_id, $slug, $amount ) ) {
					$save_error = true;
				}
			}

			if ( $save_error ) {
				$notice     = __( 'Some balances could not be saved. Please try again.', 'aspen-wallet' );
				$notice_css = 'notice notice-error';
			} else {
				$notice     = __( 'Wallet balances saved.', 'aspen-wallet' );
				$notice_css = 'notice notice-success';
			}
		}
	}

	echo '<div class="aspen-wallet-fcrm-tab">';

	if ( '' !== $notice ) {
		echo '<div class="' . esc_attr( $notice_css ) . '"><p>' . esc_html( $notice ) . '</p></div>';
	}

	if ( $user_id <= 0 ) {
		echo '<p>' . esc_html__( 'This contact is not mapped to a WordPress user, so wallet balances cannot be edited.', 'aspen-wallet' ) . '</p>';
		echo '</div>';
		return;
	}

	if ( empty( $buckets ) ) {
		echo '<p>' . esc_html__( 'No wallet buckets configured yet.', 'aspen-wallet' ) . '</p>';
		echo '</div>';
		return;
	}

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
	echo '</div>';
}
