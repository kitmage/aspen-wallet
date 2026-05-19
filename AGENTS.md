# AGENTS Notes for Aspen Wallet Repo

## Current Task Context (Fluent Booking Payment Settings)

Route under investigation:
- `#/calendars/{id}/slot-settings/{id}/payment-settings`

Primary source locations (added under tmp):
- `tmp/Fluent Booking Plugins/fluent-booking/fluent-booking`
- `tmp/Fluent Booking Plugins/fluent-booking-pro/fluent-booking-pro`

### Key label text
- `Enable this event as Paid and collect payment on booking`
- Found in:
  - `app/Services/TransStrings.php` key `PaymentSettings/enable_payment_description`

### How UI is rendered
- Payment settings UI is SPA-driven (admin app bundle), not PHP template-rendered.
- Data is supplied by event payment settings API endpoints.

### Relevant API routes
- Core:
  - `GET /{id}/events/{event_id}/payment-settings` -> `CalendarController@getEventPaymentSettings`
- Pro:
  - `GET /{id}/events/{event_id}/payment-settings` -> `PaymentMethodController@getPaymentSettings`
  - `POST /{id}/events/{event_id}/payment-settings` -> `PaymentMethodController@updatePaymentSettings`

### Relevant hooks nearest to Payment Settings rendering/data path
1. `fluent_booking/payment/get_payment_settings`
   - Signature: `function(array $data, CalendarSlot $calendarEvent): array`
   - Type: API-schema-driven (filters payload returned to SPA)

2. `fluent_booking/event_payment_settings_defaults`
   - Signature: `function(array $defaults, CalendarSlot $calendarSlot): array`
   - Type: API-schema-driven (filters default event payment settings)

3. `fluent_booking/get_event_payment_settings`
   - Signature: `function(array $settings, CalendarSlot $calendarSlot): array`
   - Type: API-schema-driven (filters final event payment settings)

### Notes for next step
- If asked for exact checkbox component source, inspect built `assets/admin/app.js` (minified) unless unminified source is provided.
- Prefer citing PHP files above for stable hook/signature evidence.
