# Operations & Maintenance

## Local Setup Checklist

1. Install and activate plugin dependencies:
   - WooCommerce
   - WooCommerce Subscriptions
   - Fluent Booking
   - FluentCRM (optional for legacy contact-tab editing)
2. Activate Aspen Wallet plugin.
3. Create buckets in **Settings > Wallet**.
4. Configure user balances in **Wallet > User Balances** or **Users > Edit User**.
5. Configure product grants.
6. Configure Fluent Booking event wallet settings.

## Manual QA Scenarios

### 1) One-time grant accumulation

- Buy a product with `one_time_grant` (e.g., +120 to `nexus-gift`).
- Complete/payment-complete order.
- Verify user meta increased additively.

### 2) Subscription reset

- Start with non-matching existing balance (e.g., 40).
- Run renewal with `subscription_reset=120`.
- Verify result is exactly 120 (not 160).

### 3) Terminal status clearing

- Move subscription to `cancelled`, `expired`, `on-hold`, and `failed`.
- Verify associated reset buckets become `0`.

### 4) Booking affordability + debit order

- Configure event cost and ordered buckets.
- Confirm blocked rendering when combined balance is insufficient.
- Confirm post-booking debit consumes buckets in configured order.

### 5) FluentCRM compatibility edit flow (optional)

- Open Wallet tab on mapped contact.
- Confirm migration links to Wallet admin and WP user edit screen are visible.
- Save integer balances.
- Verify user meta updates and values persist.

## Troubleshooting Quick Reference

- **No wallet fields on products**: WooCommerce may be inactive or hook screen mismatch.
- **No subscription resets**: Subscriptions plugin unavailable or renewal hook not firing.
- **No booking restrictions**: Fluent Booking constant/hooks may differ by version.
- **No FluentCRM wallet tab**: check CRM version/API compatibility, and verify `aspen_wallet_enable_fluentcrm_wallet_tab` filter is not set to `false`.
- **No balance updates**: confirm contact is linked to a WP user.
- **FluentCRM inactive**: use `Settings > Wallet`, `Wallet > User Balances`, and `Users > Edit User`; FluentCRM tab is optional and not required.

## Extension Ideas (Post-MVP)

- Add transaction ledger for auditability.
- Add admin tools for manual adjustments with reason codes.
- Add REST endpoints for external orchestration.
- Add conflict/locking strategy around booking debit race conditions.
