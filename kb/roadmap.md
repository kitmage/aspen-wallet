# Roadmap: Team Wallets for MemberPress Corporate Accounts

## Status

Planned future update. This article describes the intended architecture, admin experience, and rollout plan for associating Aspen Wallet balances with a MemberPress Corporate Account so WooCommerce products can grant shared minutes to a team.

## Background

Aspen Wallet currently grants integer credits to individual WordPress users from WooCommerce products, can reset subscription-backed grants through WooCommerce Subscriptions, and spends credits during Fluent Booking flows.

MemberPress Corporate Accounts is a MemberPress add-on for team-style memberships. MemberPress describes the add-on as a way to sell memberships to organizations or other groups where one member pays for multiple people to access content individually. When a member signs up for a Corporate Account membership, that member can add sub-account members who receive access based on their own subscription level.

The future Aspen Wallet update should let a team purchase or renew WooCommerce products that grant minutes to the corporate account wallet, then let every active member of that team use the shared minutes when booking.

## Goals

- Associate one wallet with one MemberPress Corporate Account, not only with an individual user.
- Allow WooCommerce products and variations to grant minutes to a team wallet.
- Allow subscription products to reset or refresh team minutes on renewal.
- Let active corporate sub-account members spend from the same team wallet in Fluent Booking.
- Keep existing individual-user wallet behavior working for sites that do not use MemberPress Corporate Accounts.
- Provide a predictable migration path from user-only balances to team balances.
- Preserve bucket support so products can grant team minutes into buckets such as `general`, `coaching`, or `premium`.

## Non-goals for the first release

- Seat billing or seat limit management. MemberPress Corporate Accounts remains the source of truth for corporate seats.
- Replacing MemberPress access rules or membership entitlements.
- Splitting one booking debit across multiple corporate accounts.
- Letting sub-account users transfer team minutes to personal wallets.
- Building a standalone team-management UI that duplicates MemberPress Corporate Accounts.

## Proposed terminology

- **Corporate owner**: The parent MemberPress user who owns or pays for the Corporate Account.
- **Sub-account member**: A MemberPress user assigned to the Corporate Account by the corporate owner.
- **Team wallet**: A wallet balance scope associated with the Corporate Account rather than an individual user.
- **Personal wallet**: The existing per-user wallet scope backed by user meta.
- **Wallet scope**: The owner type and owner identifier used for a balance, such as `user:123` or `corporate_account:456`.

## Proposed data model

Aspen Wallet should add an ownership layer instead of assuming every balance belongs to a user.

### Wallet owner table

Add a wallet owner registry, either as custom tables or a carefully namespaced post/meta model. A custom table is preferred for clear lookups and reporting.

Suggested table: `{$wpdb->prefix}aspen_wallet_owners`

| Column | Purpose |
| --- | --- |
| `id` | Internal wallet owner ID. |
| `owner_type` | `user` or `memberpress_corporate_account`. |
| `external_id` | User ID for personal wallets, Corporate Account ID or canonical MemberPress Corporate Accounts identifier for team wallets. |
| `owner_user_id` | Corporate owner user ID for team wallets; same as user ID for personal wallets. |
| `label` | Human-readable label for admin screens. |
| `status` | `active`, `inactive`, `archived`. |
| `created_at` / `updated_at` | Audit timestamps. |

### Balance table

Move new team balances into a table keyed by wallet owner and bucket. Existing user-meta balances can remain as a compatibility layer until migrated.

Suggested table: `{$wpdb->prefix}aspen_wallet_balances`

| Column | Purpose |
| --- | --- |
| `wallet_owner_id` | References the wallet owner registry. |
| `bucket` | Existing bucket slug. |
| `balance` | Integer minute balance. |
| `updated_at` | Last balance update timestamp. |

Add a unique key on `wallet_owner_id + bucket`.

### Ledger table

Add a ledger for grants, debits, adjustments, reversals, and renewals. This is especially important for shared team balances because multiple users can spend from the same balance.

Suggested table: `{$wpdb->prefix}aspen_wallet_ledger`

| Column | Purpose |
| --- | --- |
| `id` | Ledger event ID. |
| `wallet_owner_id` | Team or personal wallet owner. |
| `actor_user_id` | User who caused the change; for automated renewal jobs, store the corporate owner or `0` plus context. |
| `bucket` | Bucket affected. |
| `delta` | Positive grant or negative debit. |
| `balance_after` | Balance after the event for auditability. |
| `event_type` | `woo_order_grant`, `subscription_reset`, `fluent_booking_debit`, `admin_adjustment`, `refund_reversal`, etc. |
| `object_type` / `object_id` | Related order, subscription, booking, or corporate account. |
| `idempotency_key` | Prevents duplicate grants/debits. |
| `created_at` | Event timestamp. |

## MemberPress Corporate Accounts discovery

Before implementation, confirm the add-on's current APIs and storage model in the installed MemberPress Corporate Accounts plugin version. The public add-on page confirms that Corporate Accounts lets one member pay for a group and add sub-account members who receive their own access, but implementation should rely on installed plugin code or official developer documentation rather than guessing internal table names.

Discovery tasks:

1. Identify the canonical Corporate Account identifier.
2. Identify how to resolve a sub-account user to its parent Corporate Account.
3. Identify how to resolve the corporate owner user from a Corporate Account.
4. Identify the add-on hooks fired when:
   - a corporate account is created;
   - a sub-account is added;
   - a sub-account is removed;
   - a corporate subscription becomes inactive;
   - seats are changed.
5. Confirm whether the installed add-on exposes helper functions/classes that can be used safely instead of direct database queries.
6. Confirm whether MemberPress Corporate Accounts supports multiple corporate accounts per owner or per sub-account, and define Aspen Wallet behavior for those edge cases.

## WooCommerce product configuration

Extend the existing product wallet grant UI with a target scope selector.

### New product fields

For each wallet grant row, add:

| Field | Options | Behavior |
| --- | --- | --- |
| `Grant target` | `Purchasing customer`, `MemberPress Corporate Account`, `Auto-detect` | Determines whether credits are granted to a personal wallet or team wallet. |
| `Corporate account resolution` | `Order customer is corporate owner`, `Order contains linked MemberPress membership`, `Manual corporate account lookup` | Defines how Aspen Wallet resolves the team wallet when an order is processed. |
| `Seat sharing policy` | `All active sub-accounts can spend`, `Owner only`, `Owner + active sub-accounts` | Controls who can spend team minutes. First release should default to all active sub-accounts. |

### Order processing behavior

When an order completes:

1. Read the product or variation grant rows.
2. For each grant, resolve the wallet scope:
   - personal wallet for existing behavior;
   - team wallet when the product is configured for Corporate Accounts and the order customer can be resolved to a Corporate Account;
   - personal wallet or skipped grant based on the selected fallback for unresolved Corporate Accounts.
3. Apply one-time grants once per order using a team-aware idempotency key.
4. Add an order note showing whether minutes were granted to a personal wallet or team wallet.
5. Write ledger entries for each bucket grant.

### Subscription behavior

For subscription reset rows:

1. Resolve the subscription owner to the Corporate Account wallet.
2. On successful renewal, set or refresh the team wallet bucket balances according to product configuration.
3. On cancellation, expiration, failed payment, or hold status, optionally clear the related team wallet buckets according to a new product-level setting.
4. Record renewal order IDs per wallet owner and subscription, not just per subscription, so team resets are idempotent and auditable.

## Booking debit behavior

Fluent Booking checks should resolve the wallet owner before affordability checks.

Suggested resolution order:

1. If the logged-in user belongs to an active Corporate Account with an enabled team wallet, use that team wallet.
2. If the event permits personal fallback and the user has sufficient personal minutes, use the personal wallet.
3. If the user belongs to multiple eligible Corporate Accounts, require a team selector before booking or apply a deterministic admin-configured priority.
4. If no eligible wallet can pay, show the existing fallback/restriction message with team-specific wording.

Debit rules:

- Debit from the selected wallet owner's buckets in existing allowed-bucket priority order.
- Record the booking user as `actor_user_id` even when the corporate owner owns the wallet.
- Store the selected wallet owner and debit ledger IDs on the booking record to support cancellation or admin reversal later.
- Prevent race conditions by using row-level locking or atomic conditional updates when multiple team members book at the same time.

## Admin experience

### Product admin

- Add team-targeting controls beside current wallet grant rows.
- Show contextual help explaining that MemberPress Corporate Accounts controls team membership and seats.
- Warn when MemberPress Corporate Accounts is inactive but a product is configured for team grants.

### Wallet admin

- Add a Team Wallets list with columns for Corporate Account, owner, active members, buckets, balance, status, and last activity.
- Add filters for owner, bucket, status, and low balance.
- Add a team wallet detail page showing:
  - current balances by bucket;
  - corporate owner;
  - active sub-account members;
  - recent ledger events;
  - related WooCommerce orders/subscriptions;
  - related Fluent Booking debits.

### User profile admin

- Show whether a user can spend from a team wallet.
- Show both personal and team balances when applicable.
- For corporate owners, show owned team wallets and renewal status.

## Migration and backward compatibility

- Keep existing personal wallet helper functions working for user-scoped balances.
- Introduce new internal APIs that accept a wallet owner reference instead of only a user ID.
- Add wrappers so old calls such as `wallet_get_balance( $user_id, $bucket )` continue to operate on personal wallets.
- Add an optional migration tool that can move a corporate owner's personal balance into the new team wallet.
- Do not automatically merge balances into team wallets without explicit admin confirmation.
- Preserve existing WooCommerce product grant rows by defaulting their target to `Purchasing customer`.

## Developer API plan

Add team-aware APIs while preserving existing user-based wrappers.

Proposed new helpers:

```php
aspen_wallet_get_wallet_owner_for_user( $user_id );
aspen_wallet_get_wallet_owner_for_memberpress_corporate_account( $corporate_account_id );
aspen_wallet_resolve_spendable_wallet_owner( $user_id, $context = array() );
aspen_wallet_get_owner_balance( $wallet_owner_id, $bucket );
aspen_wallet_set_owner_balance( $wallet_owner_id, $bucket, $amount, $context = array() );
aspen_wallet_add_owner_balance( $wallet_owner_id, $bucket, $amount, $context = array() );
aspen_wallet_debit_owner_balances( $wallet_owner_id, $buckets, $amount, $context = array() );
aspen_wallet_user_can_spend_wallet_owner( $user_id, $wallet_owner_id );
```

Proposed hooks:

```php
do_action( 'aspen_wallet_team_wallet_created', $wallet_owner_id, $corporate_account_id );
do_action( 'aspen_wallet_team_wallet_member_added', $wallet_owner_id, $user_id, $corporate_account_id );
do_action( 'aspen_wallet_team_wallet_member_removed', $wallet_owner_id, $user_id, $corporate_account_id );
apply_filters( 'aspen_wallet_resolved_wallet_owner', $wallet_owner_id, $user_id, $context );
apply_filters( 'aspen_wallet_can_user_spend_team_wallet', $can_spend, $user_id, $wallet_owner_id, $context );
```

## Edge cases to define

- Guest checkout purchases that cannot be mapped to a WordPress user.
- Corporate owner buys a team-minutes product before the MemberPress Corporate Account is fully created.
- Corporate owner owns multiple Corporate Accounts.
- Sub-account belongs to multiple Corporate Accounts.
- Sub-account is removed after spending team minutes.
- Corporate subscription becomes inactive after some, but not all, minutes have been spent.
- Order refunds and chargebacks.
- Booking cancellation refunds.
- Manual admin changes made while renewal jobs or booking debits are running.
- Product variation grants overriding parent product grants.

## Implementation phases

### Phase 1: Discovery and technical spike

- Install or inspect the target MemberPress Corporate Accounts add-on version.
- Document available Corporate Accounts classes, functions, tables, meta keys, and hooks.
- Build a read-only resolver that maps a user to an active Corporate Account and corporate owner.
- Add unit/integration fixtures for owner, sub-account, inactive member, and multi-account cases.

### Phase 2: Wallet ownership foundation

- Add wallet owner, balance, and ledger storage.
- Add team-aware balance APIs.
- Add compatibility wrappers for existing personal-wallet functions.
- Add concurrency-safe grant and debit operations.
- Add upgrade routines and admin-only migration tooling.

### Phase 3: WooCommerce team grants

- Add product and variation fields for grant target and Corporate Account resolution.
- Apply one-time grants to team wallets on completed orders.
- Apply subscription reset grants to team wallets on renewals.
- Add order/subscription notes and ledger entries.
- Add refund/reversal hooks if supported by the business rules.

### Phase 4: Fluent Booking team spending

- Resolve team wallets before affordability checks.
- Add event-level setting for team wallet usage and personal fallback.
- Debit team balances atomically.
- Store wallet owner and ledger references on bookings.
- Add cancellation/reversal support if booking cancellations should return minutes.

### Phase 5: Admin reporting and support tools

- Add Team Wallets admin list and detail screen.
- Add team wallet information to user profiles.
- Add diagnostics for unresolved Corporate Accounts, inactive add-on, duplicate memberships, and low balances.
- Add export support for team ledger events.

### Phase 6: Documentation and release

- Update user documentation for product setup, team purchase flow, booking behavior, and troubleshooting.
- Update developer docs with new team-aware APIs and hooks.
- Add upgrade notes explaining that existing products keep granting personal minutes until changed.
- Run end-to-end tests with WooCommerce, WooCommerce Subscriptions, Fluent Booking, MemberPress, and MemberPress Corporate Accounts active.

## Acceptance criteria

- Admin can configure a WooCommerce product to grant minutes to a MemberPress Corporate Account wallet.
- A corporate owner purchase creates or updates the correct team wallet balance.
- Active sub-account members can book using the shared team minutes.
- Removed or inactive sub-account members cannot spend team minutes.
- Subscription renewal refreshes team minutes exactly once per renewal order.
- Existing personal wallet products, shortcodes, and booking rules continue to work.
- Every team wallet grant and debit is visible in an auditable ledger.
- Concurrent bookings cannot overspend a team wallet.

## Open questions

- Which MemberPress Corporate Accounts identifier should Aspen Wallet store as `external_id`?
- Should team minutes be owned by the Corporate Account record, the corporate owner user, or the MemberPress subscription?
- Should sub-account users see the full team balance or only whether enough minutes remain for a booking?
- Should booking cancellation automatically refund team minutes?
- Should order refunds reverse team grants, and how should partial refunds map to minutes?
- Should team wallet usage be enabled globally, per bucket, per Fluent Booking event, or all three?
- How should Aspen Wallet handle users who are eligible for more than one team wallet?

## References

- MemberPress Corporate Accounts add-on: https://memberpress.com/addons/corporate-accounts/
- Aspen Wallet developer documentation: `kb/dev-docs.md`
- Existing WooCommerce grant integration: `includes/woo.php`
- Existing subscription renewal/reset integration: `includes/subscriptions.php`
- Existing balance helpers: `includes/balances.php`
