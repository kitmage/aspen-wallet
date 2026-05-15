# Architecture

## Entry Point

- `plugin.php` is the bootstrap file.
- On `plugins_loaded`, `aspen_wallet_bootstrap()` loads all modules and registers hooks.
- Activation checks enforce minimum PHP `7.4` and WordPress `6.2`.

## Module Map

- `includes/balances.php`: core shared helpers (sanitization, balance reads/writes, affordability, debit logic, formatting).
- `includes/buckets.php`: bucket registry CRUD using a single WordPress option (`wallet_buckets`).
- `includes/admin.php`: wp-admin bucket management UI (`Settings > Wallet`).
- `includes/woo.php`: WooCommerce product grant configuration + one-time grant application on completed/paid orders.
- `includes/subscriptions.php`: subscription renewal reset behavior and terminal-status bucket clearing.
- `includes/fluent-booking.php`: Fluent Booking event wallet settings, pre-checks, server-side validation, and post-booking debit.
- `includes/shortcodes.php`: frontend output helpers (`wallet_balance`, `wallet_if`, `wallet_booking`).
- `includes/fluentcrm.php`: FluentCRM contact profile `Wallet` tab for balance editing.

## Runtime Flow (High Level)

1. Admin configures global buckets in **Settings > Wallet**.
2. Admin configures product-level grants (`one_time_grant` and/or `subscription_reset`).
3. Customer purchases product/subscription:
   - one-time grants add to balances on order completion/payment;
   - renewal grants reset balances on successful subscription renewal.
4. Fluent Booking event wallet rules (cost + ordered buckets) are checked:
   - before calendar rendering,
   - again before booking create (server-side).
5. After successful booking creation, debits execute in strict bucket order.
6. FluentCRM admin can inspect/edit all bucket balances for mapped WP users.

## Design Principles in Current Implementation

- Integer-only credit model.
- Minimal schema: user meta + one option object.
- Defensive sanitization and capability checks on write paths.
- Progressive enhancement with guard clauses when dependency plugins are unavailable.
