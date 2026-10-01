# AI Assistant: lead capture, email OTP and token throttling

## What changed
The site assistant is now gated by a lead form, verified by email OTP, and protected by strict per-visitor and site-wide token limits. The widget has a new animated design.

## Fix
- `public/js/site.js` was never loaded by `resources/views/site/layout.blade.php`, so the widget did not run. It is now loaded, along with `public/css/assistant.css`.

## Visitor flow
1. Visitor opens the orb button.
2. New visitors enter name, 10-digit Indian mobile, email, and optional city.
3. A 6-digit code is emailed (valid 10 min, 5 attempts, 60 s resend cooldown, max 3 codes/email/hour).
4. On success the visitor is saved as a lead (`type = chatbot`) and the chat unlocks.
5. Returning visitors skip the form.
6. On closing after 2 or more messages, a feedback form (stars, reason, comment) is shown.

## Token-waste protection (`app/Services/AssistantGuard.php`)
All values are editable in Admin -> Settings -> Assistant limits.

| Limit | Default |
|---|---|
| Messages per minute (per visitor) | 6 |
| Minimum gap between messages | 2 s |
| Messages per day (per visitor) | 20 |
| Tokens per day (per visitor) | 15,000 |
| Tokens lifetime (per visitor) | 100,000 |
| Tokens per day (per IP) | 40,000 |
| Tokens per day (whole site) | 500,000 |
| Spoken-reply characters per day | 1,500 |
| Max question length / reply tokens (text / voice) | 300 / 300 / 180 |

- Checks run before any AI call; over-budget requests cost nothing.
- Repeated violations cause a 10-minute block; one in-flight request per visitor.
- When the site-wide cap is hit, answers come from site data only (no AI cost) until midnight.
- Savings: 24-hour response cache, local replies for greetings/thanks/prompt-injection, 3 knowledge hits x 500 chars, last 4 history turns, capped output, real provider usage recorded.

## Voice
Browser speech input is free. ElevenLabs speaks replies only when the visitor turns the speaker on; clips are cached and counted against the daily character cap. Without a key it falls back to the browser voice.

## Admin
- New page: AI chats & usage (site tokens vs cap, chats served free, ratings, per-visitor conversations, block/unblock, reset usage).
- Settings: new "Assistant limits" group; greeting, "ask for details" and "verify email" switches.
- Leads: new `chatbot` type filter.
- New permission `assistant.manage` (granted to the Admin role by the migration; Super Admin has it automatically).

## New / changed files
- Migration: `database/migrations/2026_10_01_000008_assistant_leads_and_usage.php`
- Models: `AssistantSession`, `AssistantOtp`, `AssistantFeedback`
- Services: `AssistantGuard` (new), `Assistant`, `AiClient` (token usage capture)
- Controllers: `Site/AssistantLeadController` (new), `Site/AssistantController`, `Admin/AssistantController` (new), `Admin/SettingController`
- Middleware: `EnsureAssistantSession` (alias `assistant.session`)
- Views/assets: `site/partials/assistant.blade.php`, `public/js/site.js`, `public/css/assistant.css`, `admin/assistant/*`
- Routes: `/assistant/me|lead|verify|feedback`; `/assistant/chat` and `/assistant/tts` now require a verified visitor; `/admin/assistant*`
- Tests: `tests/Feature/AssistantTest.php`

## Deploy checklist
1. `php artisan migrate`
2. Set `MAIL_*` in `.env` so OTP emails are delivered.
3. Add the AI provider key and ElevenLabs key in Admin -> Settings.
4. Review the limits in Settings -> Assistant limits.

## Notes
- Existing tests need MySQL (one migration uses MySQL-only FULLTEXT); they fail on the default sqlite config.
- The dealer, brochure, colours and service chips only prompt the AI because the site has no dealer or brochure data.

## Design update: 3D bubble and reference-style forms
- The orb (floating button, greeting, lead and OTP steps) is now a 3D glass bubble: translucent blue body, top-left specular highlight, bright rim, soft bottom glow, and a twinkling 4-point sparkle with a small second star.
- Form buttons follow the feedback reference: solid black full-width buttons, rounded-square tool and star buttons, light grey radio rows with a small hollow circle, light grey inputs, and a soft white-to-grey panel.
- The chat box is shorter (620 px instead of 740 px, 390 px wide; expanded view is capped at 760 px). The greeting, promo strip and form spacing were tightened so the lead form and the chat input fit without clipping. Mobile still opens full screen.
- Files: `public/css/assistant.css`, `resources/views/site/partials/assistant.blade.php`.

## Design update: liquid water bubble
- The orb is now a glass bubble with real "water" inside: two layered waves that roll, a surface that sloshes and tilts, a violet pool at the lower right, rising micro-bubbles, a light streak that sweeps across the glass, and a gentle water-balloon squish.
- Twinkling stars float around the bubble, and the sparkle in the centre twinkles.
- The floating button sends out soft, wobbly water ripples, jellies when hovered, and splashes when clicked.
- While the assistant is thinking, the water stirs faster and rises.
- Files: new `resources/views/site/partials/bubble.blade.php` (shared by the button, greeting, and form steps), `public/css/assistant.css`, `public/js/site.js` (thinking and splash hooks).
