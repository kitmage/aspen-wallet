<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const ASPEN_WALLET_FB_META_ENABLED         = '_aspen_wallet_enabled';
const ASPEN_WALLET_FB_META_COST            = '_aspen_wallet_credit_cost';
const ASPEN_WALLET_FB_META_ALLOWED_BUCKETS = '_aspen_wallet_allowed_buckets';
const ASPEN_WALLET_FB_META_USE_CREDITS_PAYMENT_SETTINGS = '_aspen_wallet_use_credits_in_payment_settings';


function aspen_wallet_fb_debug_log( $message, $context = array() ) {
	if ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG || ! defined( 'ASPEN_WALLET_DEBUG' ) || ! ASPEN_WALLET_DEBUG ) {
		return;
	}

	$line = '[ASPEN_WALLET_FB] ' . $message;
	if ( ! empty( $context ) ) {
		$encoded = wp_json_encode( $context );
		if ( false !== $encoded ) {
			$line .= ' ' . $encoded;
		}
	}

	error_log( $line ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
}

function aspen_wallet_register_fluent_booking_hooks() {
	if ( ! defined( 'FLUENT_BOOKING' ) ) {
		aspen_wallet_fb_debug_log( 'Hook registration skipped because FLUENT_BOOKING is not defined.' );
		return;
	}

	aspen_wallet_fb_debug_log( 'Hook registration path reached.' );

	add_action( 'fluent_booking_after_event_settings_fields', 'aspen_wallet_render_fluent_booking_event_wallet_settings', 20, 1 );
	add_action( 'fluent_booking_save_event_settings', 'aspen_wallet_save_fluent_booking_event_wallet_settings', 20, 2 );

	add_filter( 'fluent_booking/payment/get_payment_settings', 'aspen_wallet_add_payment_settings_panel_checkbox', 20, 2 );
	add_filter( 'fluent_booking/payment/payment_settings_before_update_native', 'aspen_wallet_save_payment_settings_panel_checkbox', 20, 1 );
	add_filter( 'fluent_booking/payment/payment_settings_before_update_woo', 'aspen_wallet_save_payment_settings_panel_checkbox', 20, 1 );

	add_filter( 'fluent_booking_event_calendar_html', 'aspen_wallet_filter_fluent_booking_calendar_html', 20, 3 );
	add_filter( 'aspen_wallet_booking_shortcode_output', 'aspen_wallet_maybe_block_booking_shortcode_output', 20, 4 );

	add_filter( 'fluent_booking_before_create_booking', 'aspen_wallet_validate_fluent_booking_before_create', 20, 3 );
	add_action( 'fluent_booking_booking_created', 'aspen_wallet_debit_after_fluent_booking_created', 20, 2 );
}

function aspen_wallet_get_bucket_registry_slugs() {
	$slugs = array();

	foreach ( aspen_wallet_get_buckets() as $bucket ) {
		if ( ! empty( $bucket['slug'] ) ) {
			$slugs[] = $bucket['slug'];
		}
	}

	return array_values( array_unique( $slugs ) );
}

function aspen_wallet_sanitize_allowed_buckets( $raw_buckets ) {
	if ( is_string( $raw_buckets ) ) {
		$raw_buckets = explode( ',', $raw_buckets );
	}

	$buckets        = aspen_wallet_normalize_bucket_list( $raw_buckets );
	$registry_slugs = aspen_wallet_get_bucket_registry_slugs();

	if ( empty( $registry_slugs ) ) {
		return array();
	}

	return array_values( array_filter( $buckets, static function ( $bucket ) use ( $registry_slugs ) {
		return in_array( $bucket, $registry_slugs, true );
	} ) );
}

function aspen_wallet_get_fluent_booking_event_wallet_settings( $event_id ) {
	$event_id = (int) $event_id;

	$settings = array(
		'enabled'         => false,
		'credit_cost'     => 0,
		'allowed_buckets' => array(),
	);

	if ( $event_id <= 0 ) {
		return $settings;
	}

	$legacy_enabled = (bool) get_post_meta( $event_id, ASPEN_WALLET_FB_META_ENABLED, true );
	$payment_settings = get_post_meta( $event_id, 'payment_settings', true );
	$payment_toggle_exists = is_array( $payment_settings ) && array_key_exists( 'enabled', $payment_settings );

	// Migration behavior: once Fluent Booking's payment-settings toggle is available for an event,
	// it becomes the source of truth so the wallet gate follows the same enable/disable switch as booking payments.
	if ( $payment_toggle_exists ) {
		$settings['enabled'] = in_array( strtolower( (string) $payment_settings['enabled'] ), array( '1', 'true', 'yes', 'on' ), true );
	} else {
		// Backward compatibility: older events may only have the legacy Aspen flag, so we fall back until
		// the new payment_settings[enabled] value is saved at least once.
		$settings['enabled'] = $legacy_enabled;
	}

	$settings['credit_cost'] = aspen_wallet_to_int( get_post_meta( $event_id, ASPEN_WALLET_FB_META_COST, true ) );
	$settings['allowed_buckets'] = aspen_wallet_sanitize_allowed_buckets( get_post_meta( $event_id, ASPEN_WALLET_FB_META_ALLOWED_BUCKETS, true ) );

	// Hard safety requirements: wallet enforcement is only effective when both a positive cost and
	// at least one allowed bucket are configured, regardless of which enable toggle was used above.
	if ( $settings['credit_cost'] <= 0 || empty( $settings['allowed_buckets'] ) ) {
		$settings['enabled'] = false;
	}

	return $settings;
}

function aspen_wallet_render_fluent_booking_event_wallet_settings( $event ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$event_id  = is_object( $event ) && isset( $event->id ) ? (int) $event->id : (int) $event;
	aspen_wallet_fb_debug_log( 'Payment-settings render callback fired.', array( 'event_id' => $event_id ) );
	$settings  = aspen_wallet_get_fluent_booking_event_wallet_settings( $event_id );
	$buckets   = aspen_wallet_get_buckets();
	?>
	<div class="aspen-wallet-event-settings">
		<h3><?php esc_html_e( 'Wallet Restriction', 'aspen-wallet' ); ?></h3>
		<p>
			<label>
				<input type="checkbox" name="aspen_wallet_enabled" value="1" <?php checked( $settings['enabled'] ); ?> />
				<?php esc_html_e( 'Enable Wallet Restriction', 'aspen-wallet' ); ?>
			</label>
		</p>
		<p>
			<label for="aspen-wallet-credit-cost"><?php esc_html_e( 'Credit Cost (int)', 'aspen-wallet' ); ?></label>
			<input id="aspen-wallet-credit-cost" type="number" min="0" step="1" name="aspen_wallet_credit_cost" value="<?php echo esc_attr( $settings['credit_cost'] ); ?>" />
		</p>
		<?php wp_nonce_field( 'aspen_wallet_save_fluent_booking_event_settings', 'aspen_wallet_fb_nonce' ); ?>
		<p>
			<label for="aspen-wallet-allowed-buckets"><?php esc_html_e( 'Allowed Buckets (ordered)', 'aspen-wallet' ); ?></label>
			<select id="aspen-wallet-allowed-buckets" name="aspen_wallet_allowed_buckets[]" multiple="multiple">
				<?php foreach ( $buckets as $bucket ) : ?>
					<option value="<?php echo esc_attr( $bucket['slug'] ); ?>" <?php selected( in_array( $bucket['slug'], $settings['allowed_buckets'], true ) ); ?>><?php echo esc_html( $bucket['label'] ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
	</div>
	<?php
}

function aspen_wallet_save_fluent_booking_event_wallet_settings( $event_id, $payload ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		aspen_wallet_fb_debug_log( 'Capability check failed for save callback.', array( 'capability' => 'manage_options' ) );
		return;
	}

	aspen_wallet_fb_debug_log( 'Capability check passed for save callback.', array( 'capability' => 'manage_options' ) );

	$event_id = (int) $event_id;
	if ( $event_id <= 0 ) {
		aspen_wallet_fb_debug_log( 'Save callback fired with invalid event ID.', array( 'event_id' => $event_id ) );
		return;
	}

	$nonce = isset( $payload['aspen_wallet_fb_nonce'] ) ? sanitize_text_field( wp_unslash( $payload['aspen_wallet_fb_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'aspen_wallet_save_fluent_booking_event_settings' ) ) {
		aspen_wallet_fb_debug_log( 'Nonce verification failed in save callback.', array( 'event_id' => $event_id ) );
		return;
	}

	aspen_wallet_fb_debug_log( 'Nonce verification passed in save callback.', array( 'event_id' => $event_id ) );

	$enabled_input = isset( $payload['aspen_wallet_enabled'] ) ? $payload['aspen_wallet_enabled'] : 0;
	$cost_input    = isset( $payload['aspen_wallet_credit_cost'] ) ? $payload['aspen_wallet_credit_cost'] : 0;
	$buckets_input = isset( $payload['aspen_wallet_allowed_buckets'] ) ? $payload['aspen_wallet_allowed_buckets'] : array();

	$enabled = ! empty( $enabled_input );
	$cost    = aspen_wallet_to_int( $cost_input );
	$buckets = aspen_wallet_sanitize_allowed_buckets( $buckets_input );

	aspen_wallet_fb_debug_log( 'Save callback fired with sanitized values.', array(
		'event_id'         => $event_id,
		'enabled'          => $enabled ? 1 : 0,
		'credit_cost'      => $cost,
		'allowed_buckets'  => $buckets,
	) );

	update_post_meta( $event_id, ASPEN_WALLET_FB_META_ENABLED, $enabled ? 1 : 0 );
	update_post_meta( $event_id, ASPEN_WALLET_FB_META_COST, $cost );
	update_post_meta( $event_id, ASPEN_WALLET_FB_META_ALLOWED_BUCKETS, $buckets );
}


function aspen_wallet_add_payment_settings_panel_checkbox( $data, $calendar_event ) {
	$event_id = is_object( $calendar_event ) && isset( $calendar_event->id ) ? (int) $calendar_event->id : 0;
	$enabled  = (bool) get_post_meta( $event_id, ASPEN_WALLET_FB_META_USE_CREDITS_PAYMENT_SETTINGS, true );

	if ( ! isset( $data['settings'] ) || ! is_array( $data['settings'] ) ) {
		$data['settings'] = array();
	}

	$data['settings']['aspen_wallet_use_credits_in_payment_settings'] = $enabled ? 'yes' : 'no';
	$data['settings']['aspen_wallet_use_credits_in_payment_settings_label'] = 'Enable Aspen Credits for this event';
	$data['settings']['aspen_wallet_use_credits_in_payment_settings_nonce'] = wp_create_nonce( 'aspen_wallet_payment_settings_' . $event_id );

	return $data;
}

function aspen_wallet_save_payment_settings_panel_checkbox( $settings ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		aspen_wallet_fb_debug_log( 'Capability check failed for payment-settings save callback.', array( 'capability' => 'manage_options' ) );
		return $settings;
	}

	aspen_wallet_fb_debug_log( 'Capability check passed for payment-settings save callback.', array( 'capability' => 'manage_options' ) );

	$event_id = isset( $_REQUEST['event_id'] ) ? (int) $_REQUEST['event_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( $event_id <= 0 ) {
		return $settings;
	}

	$nonce = isset( $settings['aspen_wallet_use_credits_in_payment_settings_nonce'] )
		? sanitize_text_field( wp_unslash( $settings['aspen_wallet_use_credits_in_payment_settings_nonce'] ) )
		: '';

	if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'aspen_wallet_payment_settings_' . $event_id ) ) {
		aspen_wallet_fb_debug_log( 'Nonce verification failed for payment-settings save callback.', array( 'event_id' => $event_id ) );
		return $settings;
	}

	aspen_wallet_fb_debug_log( 'Nonce verification passed for payment-settings save callback.', array( 'event_id' => $event_id ) );

	$raw_value = isset( $settings['aspen_wallet_use_credits_in_payment_settings'] ) ? $settings['aspen_wallet_use_credits_in_payment_settings'] : 'no';
	$normalized = ( 'yes' === $raw_value || '1' === (string) $raw_value || 1 === $raw_value ) ? 1 : 0;

	aspen_wallet_fb_debug_log( 'Payment-settings save callback fired with sanitized values.', array(
		'event_id'                                            => $event_id,
		'aspen_wallet_use_credits_in_payment_settings'        => $normalized,
	) );

	update_post_meta( $event_id, ASPEN_WALLET_FB_META_USE_CREDITS_PAYMENT_SETTINGS, $normalized );

	return $settings;
}

function aspen_wallet_fluent_booking_affordability( $event_id, $user_id ) {
	$settings = aspen_wallet_get_fluent_booking_event_wallet_settings( $event_id );
	$user_id  = (int) $user_id;

	if ( ! $settings['enabled'] ) {
		return array( 'allowed' => true, 'reason' => '' );
	}

	if ( $user_id <= 0 ) {
		return array( 'allowed' => false, 'reason' => __( 'You must be logged in to book this event.', 'aspen-wallet' ) );
	}

	if ( ! wallet_can_afford( $user_id, $settings['allowed_buckets'], $settings['credit_cost'] ) ) {
		return array( 'allowed' => false, 'reason' => __( 'Insufficient wallet credits for this booking.', 'aspen-wallet' ) );
	}

	return array( 'allowed' => true, 'reason' => '' );
}

function aspen_wallet_filter_fluent_booking_calendar_html( $html, $event, $context ) {
	$event_id = is_object( $event ) && isset( $event->id ) ? (int) $event->id : (int) $event;
	$check    = aspen_wallet_fluent_booking_affordability( $event_id, get_current_user_id() );

	if ( $check['allowed'] ) {
		return $html;
	}

	$fallback = is_array( $context ) && isset( $context['fallback'] ) ? (string) $context['fallback'] : $check['reason'];
	return do_shortcode( $fallback );
}

function aspen_wallet_maybe_block_booking_shortcode_output( $output, $event_id, $fallback, $user_id ) {
	$check = aspen_wallet_fluent_booking_affordability( $event_id, $user_id );
	if ( $check['allowed'] ) {
		return $output;
	}

	$fallback = '' !== (string) $fallback ? (string) $fallback : $check['reason'];
	return do_shortcode( $fallback );
}

function aspen_wallet_validate_fluent_booking_before_create( $validation, $event, $booking_data ) {
	$event_id = is_object( $event ) && isset( $event->id ) ? (int) $event->id : (int) $event;
	$user_id  = isset( $booking_data['user_id'] ) ? (int) $booking_data['user_id'] : get_current_user_id();

	$check = aspen_wallet_fluent_booking_affordability( $event_id, $user_id );
	if ( $check['allowed'] ) {
		return $validation;
	}

	return new WP_Error( 'wallet_insufficient_credits', $check['reason'] );
}

function aspen_wallet_debit_after_fluent_booking_created( $booking, $event ) {
	$event_id = is_object( $event ) && isset( $event->id ) ? (int) $event->id : (int) $event;
	$user_id  = is_object( $booking ) && isset( $booking->user_id ) ? (int) $booking->user_id : 0;

	$settings = aspen_wallet_get_fluent_booking_event_wallet_settings( $event_id );
	if ( ! $settings['enabled'] || $user_id <= 0 ) {
		return;
	}

	$debit = wallet_debit_balances( $user_id, $settings['allowed_buckets'], $settings['credit_cost'] );
	if ( ! empty( $debit['success'] ) ) {
		return;
	}

	do_action(
		'aspen_wallet_booking_debit_failed',
		array(
			'booking_id' => is_object( $booking ) && isset( $booking->id ) ? (int) $booking->id : 0,
			'event_id'   => $event_id,
			'user_id'    => $user_id,
			'reason'     => __( 'Wallet debit failed after booking creation.', 'aspen-wallet' ),
		)
	);
}
