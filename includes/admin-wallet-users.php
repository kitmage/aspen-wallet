<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function aspen_wallet_register_admin_wallet_users_hooks() {
	add_action( 'admin_menu', 'aspen_wallet_register_users_admin_menu', 5 );
	add_action( 'admin_post_aspen_wallet_save_user_balances', 'aspen_wallet_handle_save_user_balances' );
}

function aspen_wallet_register_users_admin_menu() {
	add_menu_page(
		__( 'Wallet', 'aspen-wallet' ),
		__( 'Wallet', 'aspen-wallet' ),
		'edit_users',
		'aspen-wallet-users',
		'aspen_wallet_render_user_balances_page',
		'dashicons-money-alt',
		56
	);

	add_submenu_page(
		'aspen-wallet-users',
		__( 'User Balances', 'aspen-wallet' ),
		__( 'User Balances', 'aspen-wallet' ),
		'edit_users',
		'aspen-wallet-users',
		'aspen_wallet_render_user_balances_page'
	);
}

function aspen_wallet_render_user_balances_page() {
	if ( ! current_user_can( 'edit_users' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'aspen-wallet' ) );
	}

	$search_term = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
	$user_id     = isset( $_GET['user_id'] ) ? absint( wp_unslash( $_GET['user_id'] ) ) : 0;
	$selected    = $user_id > 0 ? get_user_by( 'id', $user_id ) : false;
	$buckets     = aspen_wallet_get_buckets();
	$messages    = aspen_wallet_parse_notice_messages( isset( $_GET['wallet_success'] ) ? wp_unslash( $_GET['wallet_success'] ) : '' );
	$errors      = aspen_wallet_parse_notice_messages( isset( $_GET['wallet_errors'] ) ? wp_unslash( $_GET['wallet_errors'] ) : '' );

	$users = array();
	if ( '' !== $search_term ) {
		$users = get_users(
			array(
				'number'         => 20,
				'search'         => '*' . esc_attr( $search_term ) . '*',
				'search_columns' => array( 'user_login', 'user_email', 'display_name' ),
				'orderby'        => 'display_name',
				'order'          => 'ASC',
			)
		);
	}
	?>
	<div class="wrap">
		<h1><?php echo esc_html__( 'Wallet User Balances', 'aspen-wallet' ); ?></h1>

		<?php foreach ( $errors as $error ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
		<?php endforeach; ?>
		<?php foreach ( $messages as $message ) : ?>
			<div class="notice notice-success"><p><?php echo esc_html( $message ); ?></p></div>
		<?php endforeach; ?>

		<form method="get">
			<input type="hidden" name="page" value="aspen-wallet-users" />
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="aspen_wallet_user_search"><?php echo esc_html__( 'Find User', 'aspen-wallet' ); ?></label></th>
					<td>
						<input id="aspen_wallet_user_search" type="text" name="s" class="regular-text" value="<?php echo esc_attr( $search_term ); ?>" placeholder="<?php echo esc_attr__( 'Search by email, login, or display name', 'aspen-wallet' ); ?>" />
						<?php submit_button( __( 'Search', 'aspen-wallet' ), 'secondary', '', false ); ?>
					</td>
				</tr>
			</table>
		</form>

		<?php if ( '' !== $search_term ) : ?>
			<h2><?php echo esc_html__( 'Search Results', 'aspen-wallet' ); ?></h2>
			<?php if ( empty( $users ) ) : ?>
				<p><?php echo esc_html__( 'No users found.', 'aspen-wallet' ); ?></p>
			<?php else : ?>
				<ul>
					<?php foreach ( $users as $user ) : ?>
						<li>
							<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'aspen-wallet-users', 's' => $search_term, 'user_id' => $user->ID ), admin_url( 'admin.php' ) ) ); ?>">
								<?php echo esc_html( sprintf( '%1$s (%2$s)', $user->display_name, $user->user_email ) ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		<?php endif; ?>

		<?php if ( $selected instanceof WP_User ) : ?>
			<h2>
				<?php echo esc_html( sprintf( __( 'Balances for %1$s', 'aspen-wallet' ), $selected->display_name ) ); ?>
				<small>&lt;<?php echo esc_html( $selected->user_email ); ?>&gt;</small>
			</h2>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'aspen_wallet_save_user_balances' ); ?>
				<input type="hidden" name="action" value="aspen_wallet_save_user_balances" />
				<input type="hidden" name="user_id" value="<?php echo esc_attr( $selected->ID ); ?>" />
				<input type="hidden" name="s" value="<?php echo esc_attr( $search_term ); ?>" />

				<table class="widefat striped">
					<thead><tr><th><?php echo esc_html__( 'Bucket', 'aspen-wallet' ); ?></th><th><?php echo esc_html__( 'Balance (integer)', 'aspen-wallet' ); ?></th><th><?php echo esc_html__( 'FluentCRM', 'aspen-wallet' ); ?></th></tr></thead>
					<tbody>
					<?php if ( empty( $buckets ) ) : ?>
						<tr><td colspan="3"><?php echo esc_html__( 'No buckets configured yet.', 'aspen-wallet' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $buckets as $bucket ) : ?>
							<?php
							$slug    = $bucket['slug'];
							$balance = wallet_get_balance( $selected->ID, $slug );
							?>
							<tr>
								<td><strong><?php echo esc_html( $bucket['label'] ); ?></strong><br /><code><?php echo esc_html( $slug ); ?></code></td>
								<td><input type="number" min="0" step="1" name="balances[<?php echo esc_attr( $slug ); ?>]" value="<?php echo esc_attr( $balance ); ?>" class="small-text" /></td>
								<td><?php echo wp_kses_post( aspen_wallet_get_fluentcrm_contact_link( $selected ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
					</tbody>
				</table>
				<?php submit_button( __( 'Save Balances', 'aspen-wallet' ) ); ?>
			</form>
		<?php elseif ( $user_id > 0 ) : ?>
			<p><?php echo esc_html__( 'Selected user was not found.', 'aspen-wallet' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

function aspen_wallet_handle_save_user_balances() {
	if ( ! current_user_can( 'edit_users' ) ) {
		wp_die( esc_html__( 'You do not have permission to edit user balances.', 'aspen-wallet' ) );
	}

	check_admin_referer( 'aspen_wallet_save_user_balances' );

	$user_id = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;
	$user    = $user_id > 0 ? get_user_by( 'id', $user_id ) : false;

	if ( ! ( $user instanceof WP_User ) ) {
		aspen_wallet_user_balances_redirect(
			array(
				'wallet_errors' => rawurlencode( __( 'User not found.', 'aspen-wallet' ) ),
			)
		);
	}

	$values  = isset( $_POST['balances'] ) ? (array) wp_unslash( $_POST['balances'] ) : array();
	$buckets = aspen_wallet_get_buckets();

	foreach ( $buckets as $bucket ) {
		$slug   = $bucket['slug'];
		$amount = isset( $values[ $slug ] ) ? aspen_wallet_to_int( $values[ $slug ] ) : 0;
		wallet_set_balance( $user_id, $slug, $amount );
	}

	aspen_wallet_user_balances_redirect(
		array(
			'user_id'        => $user_id,
			's'              => isset( $_POST['s'] ) ? sanitize_text_field( wp_unslash( $_POST['s'] ) ) : '',
			'wallet_success' => rawurlencode( __( 'Balances updated.', 'aspen-wallet' ) ),
		)
	);
}

function aspen_wallet_user_balances_redirect( $args ) {
	wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php?page=aspen-wallet-users' ) ) );
	exit;
}

function aspen_wallet_get_fluentcrm_contact_link( WP_User $user ) {
	if ( ! class_exists( '\\FluentCrm\\App\\Models\\Subscriber' ) ) {
		return '<em>' . esc_html__( 'FluentCRM unavailable', 'aspen-wallet' ) . '</em>';
	}

	$subscriber = \FluentCrm\App\Models\Subscriber::where( 'user_id', $user->ID )->first();
	if ( ! $subscriber || empty( $subscriber->id ) ) {
		return '<em>' . esc_html__( 'No linked contact', 'aspen-wallet' ) . '</em>';
	}

	$url = add_query_arg(
		array(
			'page'       => 'fluentcrm-admin',
			'route'      => 'contact',
			'contact_id' => absint( $subscriber->id ),
		),
		admin_url( 'admin.php' )
	);

	return sprintf( '<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>', esc_url( $url ), esc_html__( 'Open contact', 'aspen-wallet' ) );
}
