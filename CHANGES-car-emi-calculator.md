# Car Loan EMI Calculator — what changed

Branch: `claude/youthful-bohr-5b594n` → merged into `main`. New page: `/car-emi-calculator`.

After deploying run: `php artisan migrate` (adds the "Car Loan" header link and a footer link).

## New files
| Path | Purpose |
|---|---|
| `app/Http/Controllers/Site/CalculatorController.php` | Controller for the page (defaults from settings, latest 5 news) |
| `resources/views/site/emi.blade.php` | Calculator page: sliders, EMI/interest/total, donut chart, FAQ, ads, lead form, latest news |
| `resources/views/site/partials/lead-form.blade.php` | "Get best offers" lead form, extracted so it is shared |
| `resources/views/site/partials/latest-news.blade.php` | "Latest" news sidebar box |
| `database/migrations/2026_10_01_000002_add_car_loan_menu_item.php` | Adds "Car Loan" header menu item and footer link |
| `tests/Feature/EmiCalculatorTest.php` | Feature tests (need a MySQL test DB; repo migrations use FULLTEXT) |

## Modified files
| Path | Change |
|---|---|
| `routes/web.php` | Route `GET /car-emi-calculator` (name `emi`) |
| `app/Models/HomeSetting.php` | Added `emi` to `AD_PAGES` so vertical ads can be placed on the calculator (Admin → Home settings) |
| `app/Http/Controllers/Site/PageController.php` | Lead saves optional EMI details; page added to sitemap and `llms.txt` |
| `app/Http/Controllers/Admin/SettingController.php` | New "EMI calculator" settings group (default/min/max amount, rate, tenure) |
| `resources/views/site/partials/sidebar.blade.php` | Lead form replaced with the shared `lead-form` partial (same behaviour) |
| `public/css/site-pages.css` | EMI calculator and latest-news styles appended |

## Not verified
Not run in a browser or via the test suite in the build environment (no MySQL). EMI formula checked: ₹10,00,000 / 10% / 5 yrs → EMI ₹21,247, interest ₹2,74,823, total ₹12,74,823.

---

# Admin "Pages" CRUD — what changed

Branch: `claude/youthful-bohr-5b594n`. Admin: **Pages** (sidebar → Content). Each published page opens at `/{slug}`.

After deploying run: `php artisan migrate` (creates `pages` table and the `pages.manage` permission for Admin and Editor roles; Super Admin always has access).

## New files
| Path | Purpose |
|---|---|
| `database/migrations/2026_10_01_000003_create_pages_table.php` | `pages` table (content, SEO, OG, schema, FAQ, show-lead/ads/news switches, soft deletes) |
| `database/migrations/2026_10_01_000004_add_pages_permission.php` | `pages.manage` permission |
| `app/Models/Page.php` | Model, templates, reserved slugs, `published()` scope |
| `app/Http/Controllers/Admin/PageController.php` | List (DataTable + bulk publish/draft/delete), create/edit/delete, preview |
| `app/Http/Controllers/Site/CustomPageController.php` | Renders a published page at `/{slug}` |
| `resources/views/admin/pages/index.blade.php` | Pages list with filters |
| `resources/views/admin/pages/form.blade.php` | Tabs: Basic Info, Content (TinyMCE), SEO & Schema, FAQ |
| `resources/views/site/page.blade.php` | Public page: SEO meta, JSON-LD, FAQ, lead form, ads, latest news |
| `tests/Feature/PagesTest.php` | Feature tests |

## Modified files
| Path | Change |
|---|---|
| `routes/web.php` | `admin/pages` resource routes; catch-all `/{slug}` route registered last |
| `resources/views/admin/layout.blade.php` | "Pages" sidebar item |
| `resources/views/site/layout.blade.php` | Page can override canonical, robots, og:title, og:description |
| `app/Models/HomeSetting.php` | Ad placement "Custom pages" (`page`) |
| `app/Http/Controllers/Site/PageController.php` | Published pages in `sitemap.xml` and `llms.txt` |
| `app/Providers/AppServiceProvider.php` | Overrides Laravel's built-in `@context` Blade directive, which was corrupting every JSON-LD `"@context"` key on the site (home, news, new cars, used cars, videos, EMI) |
| `database/seeders/DatabaseSeeder.php` | `pages.manage` in permission map and Editor role |

## Verified
`php artisan test` on MariaDB: 11 tests pass (pages CRUD, draft 404, reserved/duplicate slugs, JSON-LD validity, sitemap, EMI page).

---

# Central SEO admin (Home, EMI and all built-in pages) — what changed

Admin: **SEO** (sidebar → Content) for per-page SEO, and **Settings → SEO & Tracking** for site-wide SEO.
After deploying run: `php artisan migrate` (creates `seo_entries` and the `seo.manage` permission for Admin and Editor).

## New files
| Path | Purpose |
|---|---|
| `database/migrations/2026_10_01_000005_create_seo_entries_table.php` | `seo_entries` table, one row per built-in page |
| `database/migrations/2026_10_01_000006_add_seo_permission.php` | `seo.manage` permission |
| `app/Models/SeoEntry.php` | Page list (home, new cars/bikes/trucks, used, news, videos, compare, EMI, sell, about, contact) + cached lookup by route |
| `app/Http/Controllers/Admin/SeoController.php` | List, edit, save, reset to defaults |
| `app/Support/SeoRules.php` | Validation + normalisation shared with Pages |
| `resources/views/admin/seo/{index,form}.blade.php` | SEO list and editor |
| `resources/views/admin/partials/seo-fields.blade.php` | SEO & Schema + FAQ tabs (live Google preview), shared by Pages and SEO entries |
| `resources/views/site/partials/seo-jsonld.blade.php` | JSON-LD output (schema type, FAQPage, breadcrumb, custom) shared by Pages and layout |
| `tests/Feature/SeoTest.php` | Feature tests |

## Modified files
| Path | Change |
|---|---|
| `resources/views/site/layout.blade.php` | SEO entry overrides title, description, keywords, canonical, robots, Open Graph, Twitter tags; verification tags, GA4/GTM, Organization schema on home |
| `app/Providers/AppServiceProvider.php` | Shares the current page's SEO entry with site views (safe before migrate) |
| `resources/views/site/emi.blade.php` | Uses the SEO entry's FAQ when set (no duplicate FAQPage) |
| `resources/views/site/page.blade.php`, `resources/views/admin/pages/form.blade.php`, `app/Http/Controllers/Admin/PageController.php`, `app/Models/Page.php` | Use the shared SEO partials and rules |
| `app/Http/Controllers/Admin/SettingController.php` | New "SEO & Tracking" group (default description/OG image, Twitter, Organization schema, social profiles, Search Console/Bing codes, GA4/GTM, extra robots.txt) |
| `app/Http/Controllers/Site/PageController.php` | Sitemap built from the page list, noindex pages dropped; extra robots.txt lines |
| `resources/views/admin/layout.blade.php`, `resources/views/admin/home-settings.blade.php`, `routes/web.php`, `database/seeders/DatabaseSeeder.php` | Sidebar item, "Home page SEO" shortcut, routes, permission |

## Verified
`php artisan test` on MariaDB: 16 tests pass.
