# Integration Details

## WooCommerce

Hook registration is conditional on `class_exists('WooCommerce')`.

Capabilities:

- Adds **Wallet Credits** grant UI to simple products and variations.
- Saves sanitized grant rows.
- Applies only `one_time_grant` rows on order completion/payment hooks.
- Resolves user by order user ID, customer ID, then billing email fallback.

## WooCommerce Subscriptions

Hook registration is conditional on `function_exists('wcs_get_subscription')`.

Behavior:

- Renewal success: reset configured subscription buckets to exact configured values (`existing = amount`).
- Terminal statuses (`cancelled`, `expired`, `on-hold`, `failed`): clear relevant buckets to `0`.
- Renewal processing is de-duplicated with renewal order ID history.

## Fluent Booking

Hook registration is conditional on `defined('FLUENT_BOOKING')`.

Event-level settings:

- wallet enable flag,
- credit cost,
- ordered allowed buckets.

Validation path:

- calendar HTML filtering can block rendering and return fallback,
- server-side pre-create validation returns `WP_Error` when unaffordable,
- post-create hook debits balances in priority order.

## FluentCRM

Hook registration checks FluentCRM API availability (`FluentCrmApi('extender')->addProfileSection`) and the compatibility filter `aspen_wallet_enable_fluentcrm_wallet_tab`.

Capabilities:

- Adds `Wallet` tab in contact profile tabs.
- If contact maps to a WP user, renders integer balance fields per bucket.
- Renders inline migration links to:
  - `Wallet > User Balances` (`admin.php?page=aspen-wallet-users`)
  - the mapped WordPress user profile edit screen.
- Save operation writes directly to user meta via `wallet_set_balance`.
- If FluentCRM is unavailable, Wallet still functions via WordPress-native admin screens and a notice is shown to eligible admins.
