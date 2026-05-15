# Shortcodes

## `[wallet_balance]`

Purpose:

- Output current user bucket balance.

Parameters:

- `bucket` (required valid slug)
- `divide_by` (optional int, default `1`)
- `decimals` (optional int, default `0`, clamped to `<=6`)
- `suffix` (optional text)

Behavior:

- With `divide_by <= 1`, outputs raw integer.
- With `divide_by > 1`, uses formatted division (`wallet_format_balance`).

## `[wallet_if] ... [/wallet_if]`

Purpose:

- Conditional content rendering based on one bucket balance.

Parameters:

- `bucket` (required)
- `min`, `max`, `equals` (at least one required)
- `fallback` (optional, supports nested shortcode rendering)

Behavior:

- If conditions pass, renders inner content.
- If conditions fail or bucket invalid, renders `fallback` when provided.

## `[wallet_booking]`

Purpose:

- Wrapper around Fluent Booking shortcode with wallet gating.

Parameters:

- `calendar_id` (required int)
- `event_id` (required int)
- `fallback` (optional; text/HTML/shortcode)

Behavior:

- Runs affordability check before rendering booking UI.
- Returns fallback when insufficient.
- Applies final output filter: `aspen_wallet_booking_shortcode_output`.

## Security Notes

- Fallback strings are sanitized with `wp_kses_post` before nested shortcode execution.
- Bucket slugs and numeric parameters are normalized before use.
