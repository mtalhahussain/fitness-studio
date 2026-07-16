# WhatsApp Per-Gym Message Template + Setup Guide

## Problem

Each business (gym) using this platform has its own WhatsApp Business number and
its own Meta-approved message template, but today the reminder-sending code has
two hardcoded, global values instead of per-gym ones:

1. **Template name** — `PaymentDueReminderService::queueDueReminders()` and
   `queueManualRemindersForInvoices()` read
   `config('whatsapp.reminders.template_name', 'payment_due')`, a single
   app-wide `.env` value. Every gym's reminder is sent using the same template
   name regardless of what that gym actually got approved on Meta.
2. **Template language** — never passed at all; `WhatsAppService::sendTemplate()`
   always falls back to its `en_US` default.

Note: per-gym WhatsApp **credentials** (`whatsapp_token`,
`whatsapp_phone_number_id`) already route correctly — `SendPaymentDueReminderJob::handle()`
(app/Jobs/SendPaymentDueReminderJob.php:40-54) overrides the global config with
the gym's own credentials before sending. That part is not in scope; it already
works.

Admins also have no in-app guidance on how to actually create and get a
message template approved on Meta's side — this is a manual, undocumented
process today.

## Goal

- Let each gym set its own template name + language in its WhatsApp settings,
  instead of relying on one global `.env` value.
- Give admins an in-app step-by-step guide for creating a template on Meta
  Business Manager and getting it approved.

Out of scope: no direct Meta Template Management API integration (no in-app
template creation/submission, no approval-status polling). Admin creates the
template on Meta manually, then pastes the approved name/language back into
our settings form.

## Design

### 1. Data

New columns on `gyms` table (migration):

- `whatsapp_template_name` (string, nullable)
- `whatsapp_template_language` (string, nullable, default `en_US`)

Added to `Gym::$fillable`.

Both nullable so existing gyms keep working via the current `.env` fallback
until an admin fills them in.

### 2. Sending path

`PaymentDueReminderService::queueDueReminders()` and
`queueManualRemindersForInvoices()`:

```php
$templateName = $gym->whatsapp_template_name ?: config('whatsapp.reminders.template_name', 'payment_due');
$language      = $gym->whatsapp_template_language ?: 'en_US';
```

(computed per-gym, inside the existing `foreach ($gyms as $gym)` loop for
`queueDueReminders`; `queueManualRemindersForInvoices` already receives a
single `$gym`).

`SendPaymentDueReminderJob`:
- constructor gains `public string $language = 'en_US'`
- `handle()` passes `language: $this->language` to `$whatsAppService->sendTemplate(...)`

Both dispatch call sites pass the resolved `$language` through.

### 3. Admin UI

`resources/views/gyms/whatsapp-settings.blade.php` — add to the existing
Alpine.js card (same pattern as current fields, no new wizard/multi-step
component):

- Text input: **Template Name** (`whatsapp_template_name`)
- Select: **Template Language** (`whatsapp_template_language`) — options
  `en_US`, `en`, `ur`
- A collapsible **"How to create & get your template approved"** guide block
  (static text, no interactivity beyond expand/collapse), covering: log into
  Meta Business Manager → WhatsApp Manager → Message Templates → create
  template (name, category, language, body) → submit → wait for approval →
  copy the approved name + language back into the two fields above.

`GymWebController::updateWhatsAppSettings()` — extend validation to accept
`whatsapp_template_name` (nullable string) and `whatsapp_template_language`
(nullable, in: en_US,en,ur) and save them alongside the existing fields.

### 4. Testing

- Feature test: saving WhatsApp settings persists the two new fields.
- Unit/feature test: `PaymentDueReminderService` resolves template
  name/language from the gym when set, and falls back to the global config /
  `en_US` default when not set.

## Files touched

- `database/migrations/xxxx_xx_xx_add_whatsapp_template_fields_to_gyms_table.php` (new)
- `app/Models/Gym.php`
- `app/Services/PaymentDueReminderService.php`
- `app/Jobs/SendPaymentDueReminderJob.php`
- `app/Http/Controllers/Web/GymWebController.php`
- `resources/views/gyms/whatsapp-settings.blade.php`
