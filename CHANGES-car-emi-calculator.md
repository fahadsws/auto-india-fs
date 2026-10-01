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
