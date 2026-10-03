# Go-live fixes (assistant, mechanic, roast, e-challan, EV finder, page headers)

## 1. Direct chat first, details form later
- Visitors chat with the assistant, the online mechanic and "Roast my car" immediately. After a few free messages the details form + email OTP appears, and the message they typed is sent automatically once they finish.
- Settings -> Assistant & voice: `Free messages before the details form` (default **3**; assistant + roast) and `...(Online mechanic)` (default **4**). `0` = ask straight away.
- Middleware `assistant.session` creates an anonymous session (max 12 per IP per day) and counts `messages_total`. On sign-up the same session becomes the lead's session, so the conversation, memory and limits carry over. The session token is returned in responses so counting does not depend on cookies.

## 2. Home assistant: New car / Used car tabs
- First screen: **New car**, **Used car**, **Sell my car**. The tabs are answered by the server **without the AI** (`Assistant::choose`) and do not use a free message. The choice is stored in memory, then tap-able budget / body-type / city chips guide the search; results get budget chips when no budget was given.
- Root cause of "stuck on used": `SiteData::parse` recognised a bare "used" but not a bare "new" (or "new SUV", or the answer "new" to "new or used?"). Fixed, including "not used", "new or used?" (ambiguous = no type) and "New Delhi" (a city, not a type).

## 3. Professional tone, English by default
- Assistant and mechanic prompts rewritten: clear, courteous, concise; English by default, mirrors the visitor's language/script. Built-in fallback lines and the whole mechanic page UI are now English. (Roast my car keeps its playful Hinglish by design.)

## 4. E-challan (`/e-challan`)
- Validates the vehicle number in the browser, then sends the visitor to the official Parivahan portal (number copied for pasting), with state traffic-police and Virtual Courts links, a scam warning and an FAQ. Nothing is scraped or stored (the portals use a captcha and personal data).

## 5. EV charging station finder (`/ev-charging-stations`)
- Search by city/area or "Use my location". Data: OpenStreetMap `charging_station` via the Overpass API (free, no key), cached 6 h on our server; city names via a built-in list, then Nominatim (cached 14 days). Leaflet map, distance, connectors, fee, hours, directions link. Falls back to a Google Maps link if the service is busy.

## 6/7. AI errors and mechanic JSON
- Raw errors never reach visitors: controllers catch exceptions and log them; the widget and the mechanic/roast pages map 5xx/419/404/429 to calm messages.
- Mechanic: the model reply is decoded leniently, a **truncated JSON object is repaired**, the chat `reply` is salvaged if the rest is broken, one retry is made with a larger token budget, and JSON-looking text is never shown as a chat message.

## 8. Page headers
- The big `.phead` banner is replaced by a slim breadcrumb (`site/partials/crumb.blade.php`); the page `<h1>` is the last crumb (one h1 per page).

## Menus / SEO
- Migration `2026_10_03_000001` adds a **Tools** dropdown (header + footer) with EMI, cost per km, online mechanic, E-challan, EV stations and Roast (editable in Admin -> Menus). Both new pages are in the sitemap, `llms.txt` and the SEO admin list.

## Deploy
1. `php artisan migrate` (adds the Tools menu).
2. Optionally adjust the free-message settings.
3. The server must reach `overpass-api.de` and `nominatim.openstreetmap.org` (EV finder); visitors' browsers load the map from `unpkg.com` and `tile.openstreetmap.org`.

# Follow-up: location, reCAPTCHA instead of OTP, "Just launched" cards

## Current location, detected automatically
- `site.js` now owns one saved location (`localStorage.aic_loc`: city, lat, lng). On the first visit the browser's current location is requested and turned into a city (`/location/resolve`: nearest listed city, else OpenStreetMap reverse geocoding, cached). If the visitor blocks it, the existing "Where are you buying?" dialog opens once so they can pick a city. The header city button now works (list, "Use my current location", manual pick); the previously missing mobile-menu toggle was added too.
- Used everywhere a city is needed: any input/select with `data-loc-city` (or `name="city"`) is filled automatically unless the visitor already typed there - lead forms ("Get best offers"), assistant form, mechanic vehicle details, new city field on the Sell page; the EV finder searches around the saved location by itself; the assistant's Used-car tab offers the detected city first. For a **service-center** search box, just add `data-loc-city` to its input.

## reCAPTCHA instead of email OTP
- The details form no longer sends or asks for an email code. Invisible Google reCAPTCHA v3 protects it (assistant, mechanic and roast share the endpoint). Add the keys in Admin -> Settings -> Assistant & voice (`reCAPTCHA v3 site key`, `secret key`, minimum score 0.5). With no keys saved the check is skipped (honeypot and rate limits still apply).
- `/assistant/verify` and the OTP screens were removed. An existing email never hands over another browser's session (the browser's own session becomes the lead's session).

## "Just launched" cards
- The row list became a card grid like the other home sections: brand + status chip, image on a soft red-glow panel, name, fuel/body line, "From" price and an arrow button; 2 columns on phones.
