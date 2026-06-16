# Aspen Wallet
Aspen Wallet is a WordPress plugin that gives your business a **bucketed credit wallet** for customers. You can grant credits from products, spend credits on bookings, and keep balances organized by credit type (bucket).

## Who this is for

This plugin is for teams that want to:
- Sell or grant credits in WooCommerce.
- Reset or refresh credits on subscription renewals.
- Let customers spend credits in Fluent Booking.
- Keep different credit types separate (for example: `general`, `coaching`, `premium`).

## What Aspen Wallet does

- **Tracks customer balances by bucket** (integer credits only).
- **Grants credits from WooCommerce purchases**.
- **Supports subscription reset grants** through WooCommerce Subscriptions.
- **Checks and debits credits for Fluent Booking events**.
- **Uses a team owner’s wallet for Teams for WooCommerce Memberships members**.
- **Provides shortcodes** for balance displays and conditional content.

## Requirements

- WordPress **6.2+**
- PHP **7.4+**
- Optional integrations for full functionality:
  - WooCommerce
  - WooCommerce Subscriptions
  - Fluent Booking
  - Teams for WooCommerce Memberships

## Installation

1. Upload the plugin folder to your WordPress site’s `wp-content/plugins/` directory.
2. In WordPress Admin, go to **Plugins**.
3. Activate **Aspen Wallet**.
4. Confirm the plugin dependencies you plan to use are also installed and active.

## Quick start

1. Go to **Wallet** in WordPress Admin and create your first buckets.
2. Open WooCommerce products and configure wallet grants (bucket + amount).
3. (Optional) Set subscription reset grants on subscription products.
4. Configure wallet rules in Fluent Booking events (enable wallet, set cost, choose allowed buckets).
5. Test with a customer account:
   - Purchase a product that grants credits.
   - Verify balance updates.
   - Attempt a booking that costs credits.

## Typical customer journey

1. Customer buys a product that grants credits.
2. Aspen Wallet adds credits to the configured bucket(s).
3. Customer visits a booking page.
4. Aspen Wallet checks whether the customer can afford the configured event cost.
   - If the customer is part of a Teams for WooCommerce Memberships team, the team owner's wallet is checked.
5. If affordable, booking proceeds and credits are debited from the applicable wallet.
6. If not affordable, the customer sees your fallback/restriction message.

## Admin features

- Bucket management (create, edit, and safely delete buckets).
- User wallet balance management from wp-admin.
- Profile-level balance editing (with capability checks).
- Booking restriction and debit rules tied to event settings.

## Shortcodes

- `[wallet_balance bucket="your-bucket"]`
  - Display the current user’s balance for a bucket.
- `[wallet_if bucket="your-bucket" min="1"]...[/wallet_if]`
  - Render content only when a balance condition passes.
- `[wallet_booking event_id="123" fallback="Not enough credits."]`
  - Show booking output only when the user can afford it.

## Notes for site owners

- Aspen Wallet uses **integer credits** (no decimal balances).
- Credits are bucket-specific and can be prioritized by allowed buckets in booking rules.
- For Teams for WooCommerce Memberships users, front-end balance displays, booking affordability checks, and booking debits resolve to the team owner's wallet by default. If a user belongs to multiple teams, Aspen Wallet uses the first team returned by the Teams API unless customized with `aspen_wallet_effective_wallet_user_id`.
- Some functionality is dependency-gated and only activates when related plugins are active.

## Support and implementation details

For technical and developer-focused implementation details, see:
- `kb/dev-docs.md`