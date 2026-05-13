# WordPress Plugin Handoff Specification

## Bucketed Credit Wallet for WooCommerce + Fluent Booking + FluentCRM

---

# Project Overview

Build a custom WordPress plugin that creates a **bucketed credit wallet system** integrated with:

* [WooCommerce](https://woocommerce.com?utm_source=chatgpt.com)
* [WooCommerce Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/?utm_source=chatgpt.com)
* [Fluent Booking](https://fluentbooking.com?utm_source=chatgpt.com)
* [FluentCRM](https://fluentcrm.com?utm_source=chatgpt.com)

The system allows users to:

* receive credits from products/subscriptions
* maintain separate balances per “bucket”
* spend credits when booking Fluent Booking events
* restrict booking access based on balance
* display balances on the frontend
* manage balances from the FluentCRM contact profile

This is an MVP implementation optimized for simplicity and maintainability.

---

# Core Business Rules

## Credits

Credits are stored as **whole integers only**.

Example:

```text
120 credits
90 credits
30 credits
```

Internally, credits represent “minutes”, but this is abstracted from the user.

Frontend formatting may divide credits into hours or other units.

Example:

```text
120 credits internally
displayed as:
2 Hours
```

using:

```text
divide_by="60"
```

---

# Buckets

Credits are separated into named buckets.

Examples:

```text
nexus-sub
nexus-gift
admin-training
first-aid
```

Each bucket has:

* label
* slug
* optional description

Buckets are globally managed in wp-admin.

---

# Subscription Behavior

Subscriptions DO NOT accumulate credits.

On successful renewal:

```text
bucket balance is RESET to configured amount
```

Examples:

```text
Current balance: 40
Renewal amount: 120
Result: 120
```

```text
Current balance: 180
Renewal amount: 120
Result: 120
```

If a subscription becomes:

* cancelled
* expired
* failed payment

then all buckets associated with that subscription are emptied.

---

# One-Off Purchases

Simple products and variable products may grant credits one time.

Example:

```text
Product:
+120 nexus-gift credits
```

On successful order completion:

```text
add credits to existing balance
```

Unlike subscriptions, one-off purchases DO accumulate.

---

# Booking Event Rules

Each Fluent Booking event can define:

* credit cost
* allowed debit buckets
* bucket priority order

Example:

```text
Allowed buckets:
nexus-gift,nexus-sub

Cost:
720 credits
```

Validation behavior:

* combined balance across all allowed buckets must be sufficient
* debits occur in listed priority order

Example:

```text
nexus-gift = 300
nexus-sub  = 600
cost       = 720
```

Debit result:

```text
nexus-gift = 0
nexus-sub  = 180
```

---

# Storage Architecture

## Use ONLY User Meta

Do NOT create custom DB tables for MVP.

Balances stored as:

```text
_user_wallet_bucket_{slug}
```

Example:

```text
_user_wallet_bucket_nexus_sub = 120
```

No ledger table for MVP.

No caching layer.

---

# Admin UI Requirements

## 1. Global Bucket Settings Page

Create admin submenu:

```text
Wallet
```

Under either:

* Settings
  OR
* FluentCRM
  OR
* WooCommerce

Admin can:

* create bucket
* edit bucket
* delete bucket

Bucket fields:

```text
Label
Slug
Description
```

Slug should sanitize to lowercase kebab-case.

---

# WooCommerce Product Integration

Support:

* simple products
* variable products
* subscription products
* subscription variations

Add product settings section:

```text
Wallet Credits
```

Admin can define multiple grants:

Example:

```text
Bucket: nexus-sub
Amount: 120
Type: subscription_reset
```

or:

```text
Bucket: nexus-gift
Amount: 240
Type: one_time_grant
```

Rules:

## One-time grants

Applied on successful WooCommerce order completion.

Behavior:

```text
existing + amount
```

## Subscription reset grants

Applied on successful subscription renewal payment.

Behavior:

```text
existing = amount
```

NOT additive.

---

# Subscription Lifecycle Hooks

Use WooCommerce Subscriptions hooks.

Required behavior:

## Renewal success

Reset bucket balances to configured amount.

## Subscription cancelled/expired/on-hold/failed

Empty associated buckets.

---

# Fluent Booking Integration

Each Fluent Booking event requires configurable wallet settings.

Event settings:

```text
Enable Wallet Restriction
Credit Cost
Allowed Buckets (ordered)
```

Example:

```text
Cost:
720

Buckets:
nexus-gift,nexus-sub
```

---

# Booking Validation

Before rendering calendar:

* validate combined balance across allowed buckets
* if insufficient:

  * DO NOT render calendar
  * render fallback instead

Must also validate again server-side before successful booking creation.

---

# Debit Timing

Debit only AFTER successful booking creation.

No reservation system required for MVP.

No rollback system required for MVP.

---

# Frontend Shortcodes

---

# 1. Calendar Restriction Shortcode

Example:

```text
[wallet_booking
    calendar_id="1"
    event_id="2"
    fallback="You need more credits."
]
```

OR:

```text
[wallet_booking
    calendar_id="1"
    event_id="2"
    fallback="[elementor-template id='123']"
]
```

Behavior:

## Sufficient balance

Render Fluent Booking shortcode.

## Insufficient balance

Render fallback.

Fallback must support:

* plain text
* HTML
* nested shortcode rendering

---

# 2. Conditional Content Shortcode

Example:

```text
[wallet_if
    bucket="nexus-sub"
    min="60"]
    Low Balance Warning
[/wallet_if]
```

Support:

```text
min
max
equals
```

Support optional fallback:

```text
[wallet_if
    bucket="nexus-sub"
    min="60"
    fallback="[elementor-template id='44']"]
Content
[/wallet_if]
```

---

# 3. Balance Display Shortcode

Example:

```text
[wallet_balance bucket="nexus-sub"]
```

Outputs raw integer:

```text
120
```

Formatting options:

```text
[wallet_balance
    bucket="nexus-sub"
    divide_by="60"
    decimals="1"
    suffix="Hours"]
```

Output:

```text
2.0 Hours
```

---

# FluentCRM Contact Integration

Add custom FluentCRM contact profile tab:

```text
Wallet
```

Use FluentCRM extender/profile APIs.

The Wallet tab should display all buckets and balances.

Example:

```text
Nexus Subscription: [120]
Nexus Gift: [60]
Admin Training: [240]
```

Requirements:

* integer-only inputs
* editable
* save button
* updates user meta balances

No transaction history required for MVP.

---

# Validation Rules

## Booking validation

Combined bucket balances must satisfy cost.

Example:

```text
Allowed buckets:
nexus-gift,nexus-sub

Balances:
300 + 600

Cost:
720

PASS
```

---

# Debit Priority Rules

Debit from buckets in listed order.

Example:

```text
Buckets:
nexus-gift,nexus-sub
```

Consume nexus-gift first.

---

# Security Requirements

* sanitize all admin input
* validate all numeric values
* verify permissions before saving
* validate booking server-side even if frontend already checked
* do not trust shortcode parameters

---

# Coding Standards

* procedural or lightweight OOP acceptable
* avoid overengineering
* WordPress coding standards preferred
* no React build systems required
* no external JS frameworks required

---

# MVP Constraints

Specifically NOT required:

* transaction ledger
* reservation system
* rollback system
* rollover credits
* expiration dates
* reporting dashboards
* REST API
* customer transaction history
* concurrency locking
* partial subscription prorations

---

# Recommended Internal Helper Functions

Examples only:

```php
wallet_get_balance($user_id, $bucket)

wallet_set_balance($user_id, $bucket, $amount)

wallet_add_balance($user_id, $bucket, $amount)

wallet_debit_balances($user_id, $buckets, $amount)

wallet_can_afford($user_id, $buckets, $amount)

wallet_format_balance($amount, $divide_by, $decimals)
```

---

# Suggested Plugin Structure

```text
/plugin-root
    plugin.php
    /includes
        admin.php
        buckets.php
        balances.php
        shortcodes.php
        woo.php
        subscriptions.php
        fluent-booking.php
        fluentcrm.php
```

---

# Important UX Notes

Frontend users should think in:

```text
Hours
Sessions
Training Time
```

NOT raw “credits”.

Admins work in raw integer credits internally.

---

# Final Priority

The most important requirements are:

1. Reliable subscription reset behavior
2. Reliable booking debit behavior
3. Flexible bucket priority debiting
4. Shortcode-based booking restriction/fallback rendering
5. Easy admin editing from FluentCRM contact profile
