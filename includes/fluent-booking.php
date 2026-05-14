<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const ASPEN_WALLET_FB_META_ENABLED         = '_aspen_wallet_enabled';
const ASPEN_WALLET_FB_META_COST            = '_aspen_wallet_credit_cost';
const ASPEN_WALLET_FB_META_ALLOWED_BUCKETS = '_aspen_wallet_allowed_buckets';

function aspen_wallet_register_fluent_booking_hooks() {
	if ( ! defined( 'FLUENT_BOOKING' ) ) {
		return;
	}

	add_action( 'fluent_booking_after_event_settings_fields', 'aspen_wallet_render_fluent_booking_event_wallet_settings', 20, 1 );
	add_action( 'fluent_booking_save_event_settings', 'aspen_wallet_save_fluent_booking_event_wallet_settings', 20, 2 );

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

	$settings['enabled'] = (bool) get_post_meta( $event_id, ASPEN_WALLET_FB_META_ENABLED, true );
	$settings['credit_cost'] = aspen_wallet_to_int( get_post_meta( $event_id, ASPEN_WALLET_FB_META_COST, true ) );
	$settings['allowed_buckets'] = aspen_wallet_sanitize_allowed_buckets( get_post_meta( $event_id, ASPEN_WALLET_FB_META_ALLOWED_BUCKETS, true ) );

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
		return;
	}

	$event_id = (int) $event_id;
	if ( $event_id <= 0 ) {
		return;
	}

	$enabled_input = isset( $payload['aspen_wallet_enabled'] ) ? $payload['aspen_wallet_enabled'] : 0;
	$cost_input    = isset( $payload['aspen_wallet_credit_cost'] ) ? $payload['aspen_wallet_credit_cost'] : 0;
	$buckets_input = isset( $payload['aspen_wallet_allowed_buckets'] ) ? $payload['aspen_wallet_allowed_buckets'] : array();

	$enabled = ! empty( $enabled_input );
	$cost    = aspen_wallet_to_int( $cost_input );
	$buckets = aspen_wallet_sanitize_allowed_buckets( $buckets_input );

	update_post_meta( $event_id, ASPEN_WALLET_FB_META_ENABLED, $enabled ? 1 : 0 );
	update_post_meta( $event_id, ASPEN_WALLET_FB_META_COST, $cost );
	update_post_meta( $event_id, ASPEN_WALLET_FB_META_ALLOWED_BUCKETS, $buckets );
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
