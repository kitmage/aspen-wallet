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
- Product-level wallet grants (`one_time_grant`, `subscription_reset`)
- Subscription renewal reset behavior and terminal-status clearing
- Fluent Booking affordability checks and post-booking debit
- Frontend shortcodes for booking restriction, conditional content, and balance display
- FluentCRM contact profile wallet tab for direct balance editing

## Quick File Map

- `plugin.php` – bootstrap + module loading
- `includes/balances.php` – core balance helpers and debit logic
- `includes/buckets.php` – bucket registry storage and CRUD helpers
- `includes/admin.php` – bucket admin UI + save/delete handlers
- `includes/woo.php` – Woo product/variation grant config + one-time grant application
- `includes/subscriptions.php` – subscription lifecycle and reset/clear flows
- `includes/fluent-booking.php` – event wallet settings, validation, and debit hooks
- `includes/shortcodes.php` – `[wallet_balance]`, `[wallet_if]`, `[wallet_booking]`
- `includes/fluentcrm.php` – FluentCRM Wallet profile tab integration
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
