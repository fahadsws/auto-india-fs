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

## Voice and conversation update
**Fixes**
- `Call to undefined method AiClient::lastUsage()`: this happens when `app/Services/Assistant.php` was uploaded but an old `app/Services/AiClient.php` is still on the server (or OPcache is serving the old class). Chat no longer crashes in that case, and **Settings -> Run setup check** now reports "Code version" so you can see it. Fix: upload every changed file, then run `php artisan optimize:clear` and restart PHP (or reset OPcache).
- Different voice: the old code quietly switched to the browser's built-in voice whenever ElevenLabs refused a request (wrong plan for that voice, bad key, quota, daily cap). Now the saved voice is always used. If ElevenLabs fails, the assistant says "Voice is unavailable right now" and stays silent instead of sounding like someone else. A browser-voice fallback is available as an optional setting (off by default).

**Real conversation**
- The assistant now talks like a person: reacts to what the visitor says, short sentences, mirrors English/Hindi/Hinglish, uses the visitor's first name now and then.
- When the visitor asks for something on the website (prices, cars, listings, news, brochures, test drives, contact...) it answers from the website data and shows link cards. For everything else the AI answers fully on its own with no website data attached (also cheaper in tokens).
- A spoken greeting plays when the chat opens (same text for everyone, so it is cached and costs almost no ElevenLabs characters). The speaker is on by default once an ElevenLabs key is saved; the visitor can mute it.

**Voice setup guide**
1. ElevenLabs -> Developers -> API keys -> create a key with **Text to Speech** enabled (add "Voices: read" and "User: read" if you want the checker to show the voice name and plan).
2. Pick the voice in ElevenLabs -> Voices. On the **free plan**, a voice from the public Voice Library does not work through the API until you click **Add to my voices**. Copy the ID from **My Voices** (or use a premade voice).
3. Admin -> Settings -> Assistant & voice: paste the key and the voice ID. Model: `eleven_flash_v2_5` (cheap and fast, supports Hindi) or `eleven_multilingual_v2` (richest, best for Hinglish, uses more characters). Tune stability/similarity/speed if you like.
4. Click **Save settings**, then **Run setup check**. Every row should be green, and you should hear a sample in your voice.
5. The free plan has about 10,000 characters a month. Replies are limited per visitor per day (Assistant limits) and every repeated sentence is served from cache.

## ElevenLabs key stored as plain text
- The ElevenLabs API key is no longer encrypted. It is saved exactly as typed (spaces and quotes from pasting are stripped) and shown in the Settings field, so you can always see what is saved. Other keys (AI provider, YouTube, cron token) are still encrypted and masked.
- Run `php artisan migrate`: it converts an old encrypted key to plain text when it can still be read. If the old key cannot be read (for example `APP_KEY` changed), just paste it again and save.

## Where the assistant gets its data (DB audit and live lookup)
**Reads from your database**
- **Live stock lookup (new):** questions like "diesel used cars in Pune under 8 lakh", "SUVs between 10 and 15 lakh", "how many cars do you have", "cheapest petrol hatchback", "first owner, automatic, after 2018" are answered by querying the database directly: used listings and the new vehicle catalog (cars, bikes, trucks). It understands budget (lakh/crore/k, under/above/between/around), fuel, transmission, city, brand, body type, year, km driven, owner, new vs used, upcoming, counts and cheapest/costliest. The AI is told these are the only matching items, so it quotes real prices and never invents stock. If nothing matches, it says so honestly.
- **Text search (existing, improved):** new vehicles (now including highlights and FAQ), used listings, news, videos, **custom pages (new)**, and a business chunk with your About, contact details, address and the new **Business facts** box (opening hours, dealer addresses, services, offers, policies). Up to 800 characters per car/listing are shown to the AI (500 for others).
- **Never read by the assistant:** leads, OTP codes, other visitors' chats, users, passwords. Only the visitor's own first name (and city) is sent to the AI provider, together with public site content.

**Proof and control**
- Admin -> AI chats & usage -> open a visitor: every answer shows where it came from ("From your database", "From your site content" or "AI general knowledge") and the exact records used.
- Settings -> **Run setup check** now lists, per type, how many records are in your database and how many are ready for the assistant, and warns when phone, email, address or Business facts are empty.
- Settings -> **Rebuild knowledge base now** re-reads everything from the database immediately. Run it once after deploying (it also runs daily and whenever a record is saved).

Deploy: `php artisan migrate` (adds `chat_logs.sources`), `php artisan optimize:clear`, then click **Rebuild knowledge base now**.
