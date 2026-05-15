# Data Model

## Bucket Registry

Global bucket definitions are stored in the WordPress option:

- `wallet_buckets` (array)

Each bucket:

- `label` (text)
- `slug` (sanitized with `sanitize_title`)
- `description` (textarea)

## User Balance Storage

Balances are stored in user meta keys generated as:

- `_user_wallet_bucket_{slug_with_underscores}`

Example:

- bucket slug `nexus-sub` -> meta key `_user_wallet_bucket_nexus_sub`

## Product Grant Storage

Per product/variation grants are stored in post meta:

- `_aspen_wallet_grants`

Grant row shape:

- `bucket` (valid registered slug)
- `amount` (integer >= 0)
- `type` (`one_time_grant` | `subscription_reset`)

## Idempotency / Processing Meta

Order-level markers:

- `_aspen_wallet_one_time_grants_applied`
- `_aspen_wallet_one_time_grants_applied_details`

Subscription-level markers:

- `_aspen_wallet_processed_renewal_orders`
- `_aspen_wallet_last_cleared_status`

## Core Balance Semantics

- `wallet_set_balance`: hard set to integer >= 0.
- `wallet_add_balance`: additive grant for one-time purchases.
- `wallet_can_afford`: aggregate check across ordered bucket list.
- `wallet_debit_balances`: ordered debit with per-bucket deltas and success/failure result payload.
