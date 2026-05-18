# Aspen Wallet

Aspen Wallet is a WordPress plugin that provides a bucketed credit wallet for:

- WooCommerce
- WooCommerce Subscriptions
- Fluent Booking
- FluentCRM

It lets you grant, reset, and debit integer credits across named buckets, then gate booking access based on wallet affordability.

## Current Status

MVP implementation with:

- Global bucket management in wp-admin (`Settings > Wallet`)
- Wallet balance management in wp-admin (`Wallet > User Balances`) and WP user edit (`Users > Edit User`)
- Product-level wallet grants (`one_time_grant`, `subscription_reset`)
- Subscription renewal reset behavior and terminal-status clearing
- Fluent Booking affordability checks and post-booking debit
- Frontend shortcodes for booking restriction, conditional content, and balance display
- Optional FluentCRM contact profile wallet tab with links to the new Wallet editing screens

## Quick File Map

- `plugin.php` – bootstrap + module loading
- `includes/balances.php` – core balance helpers and debit logic
- `includes/buckets.php` – bucket registry storage and CRUD helpers
- `includes/admin.php` – bucket admin UI + save/delete handlers
- `includes/woo.php` – Woo product/variation grant config + one-time grant application
- `includes/subscriptions.php` – subscription lifecycle and reset/clear flows
- `includes/fluent-booking.php` – event wallet settings, validation, and debit hooks
- `includes/shortcodes.php` – `[wallet_balance]`, `[wallet_if]`, `[wallet_booking]`
- `includes/integrations/fluentcrm.php` – FluentCRM Wallet profile tab integration + compatibility filter
- `kb/` – developer knowledge base documentation

## Knowledge Base

Developer documentation is in [`/kb`](./kb/README.md):

- Architecture and runtime flow
- Data model and meta keys
- Integration behavior by plugin
- Shortcode contracts and sanitization
- Setup, QA scenarios, and troubleshooting

## Version & Requirements

From plugin headers/activation checks:

- Plugin version: `0.1.0`
- PHP: `>= 7.4`
- WordPress: `>= 6.2`

## Notes

- Credits are integer-only.
- Balances are stored in user meta (`_user_wallet_bucket_{slug}` pattern).
- MVP intentionally does **not** include a transaction ledger or reservation/rollback system.

## Admin Paths & Capabilities

- `Settings > Wallet` (`wp-admin/options-general.php?page=aspen-wallet`) for bucket definitions. Requires `manage_options`.
- `Wallet > User Balances` (`wp-admin/admin.php?page=aspen-wallet-users`) for cross-user balance editing/search. Requires `edit_users`.
- `Users > Edit User` (`wp-admin/user-edit.php?user_id={id}`) for per-user balance editing. Requires `edit_user` for the target user.
- FluentCRM contact profile `Wallet` tab (optional compatibility path) requires FluentCRM integration readiness and either `manage_options` or `fluentcrm_manage_contacts`.

## FluentCRM Compatibility Toggle (Migration Period)

To disable the FluentCRM Wallet tab after migration, add this filter:

```php
add_filter( 'aspen_wallet_enable_fluentcrm_wallet_tab', '__return_false' );
```

This keeps Wallet admin and WordPress user profile editing available while hiding the legacy FluentCRM tab.

## Troubleshooting (FluentCRM Inactive)

- If FluentCRM is inactive/unavailable, Aspen Wallet still works via `Settings > Wallet`, `Wallet > User Balances`, and WP user profile editing.
- A wp-admin notice is shown to admins indicating FluentCRM integration is disabled.
- If you need to fully remove the FluentCRM tab during transition even when FluentCRM is active, use the `aspen_wallet_enable_fluentcrm_wallet_tab` filter above.
