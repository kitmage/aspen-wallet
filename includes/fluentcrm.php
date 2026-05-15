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
		'aspen_wallet',
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

function aspen_wallet_fluentcrm_enqueue_route_fix( $hook_suffix ) {
	if ( 'admin_page_fluentcrm-admin' !== $hook_suffix ) {
		return;
	}

	$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 'fluentcrm-admin' !== $page ) {
		return;
	}

	wp_register_script( 'aspen-wallet-fcrm-wallet-route', '', array(), '1.0.0', true );
	wp_enqueue_script( 'aspen-wallet-fcrm-wallet-route' );
	wp_add_inline_script( 'aspen-wallet-fcrm-wallet-route', aspen_wallet_fluentcrm_route_fix_js() );
}

function aspen_wallet_fluentcrm_route_fix_js() {
	return "(function () {\n"
		. "\tfunction walletTargetHash() {\n"
		. "\t\tvar hash = window.location.hash || '#/';\n"
		. "\t\tvar match = hash.match(/^#\\/subscribers\\/(\\d+)\\//);\n"
		. "\t\tif (!match) {\n"
		. "\t\t\treturn '#/';\n"
		. "\t\t}\n"
		. "\t\treturn '#/subscribers/' + match[1] + '/aspen_wallet#fluentcrm_sub_info_body';\n"
		. "\t}\n"
		. "\tfunction isWalletAnchor(link) {\n"
		. "\t\tif (!link) {\n"
		. "\t\t\treturn false;\n"
		. "\t\t}\n"
		. "\t\tvar text = (link.textContent || '').trim().toLowerCase();\n"
		. "\t\tvar href = (link.getAttribute('href') || '').toLowerCase();\n"
		. "\t\treturn text === 'wallet' || href.indexOf('aspen_wallet') !== -1;\n"
		. "\t}\n"
		. "\tfunction patchLink(link) {\n"
		. "\t\tif (!isWalletAnchor(link)) {\n"
		. "\t\t\treturn;\n"
		. "\t\t}\n"
		. "\t\tvar target = walletTargetHash();\n"
		. "\t\tif ('#/' === target) {\n"
		. "\t\t\treturn;\n"
		. "\t\t}\n"
		. "\t\tlink.setAttribute('href', target);\n"
		. "\t\tif (!link.dataset.aspenWalletRouteFixBound) {\n"
		. "\t\t\tlink.addEventListener('click', function (event) {\n"
		. "\t\t\t\tevent.preventDefault();\n"
		. "\t\t\t\twindow.location.hash = target;\n"
		. "\t\t\t});\n"
		. "\t\t\tlink.dataset.aspenWalletRouteFixBound = '1';\n"
		. "\t\t}\n"
		. "\t}\n"
		. "\tfunction applyPatch() {\n"
		. "\t\tvar links = document.querySelectorAll('a');\n"
		. "\t\tfor (var i = 0; i < links.length; i++) {\n"
		. "\t\t\tpatchLink(links[i]);\n"
		. "\t\t}\n"
		. "\t}\n"
		. "\tapplyPatch();\n"
		. "\twindow.addEventListener('hashchange', applyPatch);\n"
		. "\tvar observer = new MutationObserver(applyPatch);\n"
		. "\tobserver.observe(document.body, { childList: true, subtree: true });\n"
		. "})();";
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
