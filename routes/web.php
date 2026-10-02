<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Site;
use Illuminate\Support\Facades\Route;

/* ------------------------------ Public site ------------------------------ */
Route::get('/', [Site\HomeController::class, 'index'])->name('home');

Route::get('/news', [Site\NewsController::class, 'index'])->name('news.index');
Route::get('/news/category/{category:slug}', [Site\NewsController::class, 'category'])->name('news.category');
Route::get('/news/{slug}', [Site\NewsController::class, 'show'])->name('news.show');

Route::get('/cars', [Site\CarController::class, 'index'])->name('cars.index');
Route::get('/cars/{slug}', [Site\CarController::class, 'show'])->name('cars.show');
Route::post('/cars/{listing}/enquire', [Site\CarController::class, 'enquire'])->middleware('throttle:6,1')->name('cars.enquire');

Route::get('/new-cars', [Site\NewCarController::class, 'index'])->name('newcars.index');
Route::get('/new-cars/{slug}', [Site\NewCarController::class, 'show'])->name('newcars.show');
Route::get('/new-bikes', [Site\NewCarController::class, 'index'])->defaults('vehicle', 'bike')->name('newbikes.index');
Route::get('/new-bikes/{slug}', [Site\NewCarController::class, 'show'])->defaults('vehicle', 'bike')->name('newbikes.show');
Route::get('/new-trucks', [Site\NewCarController::class, 'index'])->defaults('vehicle', 'truck')->name('newtrucks.index');
Route::get('/new-trucks/{slug}', [Site\NewCarController::class, 'show'])->defaults('vehicle', 'truck')->name('newtrucks.show');

Route::get('/compare', [Site\CompareController::class, 'index'])->name('compare.index');
Route::get('/compare/{pair}', [Site\CompareController::class, 'show'])->where('pair', '.+-vs-.+')->name('compare.show');

Route::get('/videos', [Site\VideoController::class, 'index'])->name('videos.index');
Route::get('/videos/{youtubeId}', [Site\VideoController::class, 'show'])->name('videos.show');

Route::get('/search', [Site\PageController::class, 'search'])->name('search');
Route::get('/search/suggestions', [Site\PageController::class, 'suggestions'])->name('search.suggestions');
Route::post('/lead', [Site\PageController::class, 'quickLead'])->middleware('throttle:6,1')->name('lead.submit');
Route::get('/about', [Site\PageController::class, 'about'])->name('about');
Route::get('/contact', [Site\PageController::class, 'contact'])->name('contact');
Route::post('/contact', [Site\PageController::class, 'contactSubmit'])->middleware('throttle:6,1')->name('contact.submit');
Route::get('/car-emi-calculator', [Site\CalculatorController::class, 'emi'])->name('emi');
Route::get('/cost-per-km-calculator', [Site\CalculatorController::class, 'costPerKm'])->name('costperkm');
Route::get('/roast-my-car', [Site\RoastController::class, 'page'])->name('roast');
Route::post('/roast-my-car/roast', [Site\RoastController::class, 'roast'])->middleware(['throttle:roast', 'assistant.session'])->name('roast.run');
Route::get('/online-mechanic', [Site\MechanicController::class, 'page'])->name('mechanic');
Route::post('/online-mechanic/chat', [Site\MechanicController::class, 'chat'])->middleware(['throttle:mechanic', 'assistant.session'])->name('mechanic.chat');
Route::get('/sell-your-car', [Site\PageController::class, 'sell'])->name('sell');
Route::post('/sell-your-car', [Site\PageController::class, 'sellSubmit'])->middleware('throttle:6,1')->name('sell.submit');
Route::get('/sitemap.xml', [Site\PageController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [Site\PageController::class, 'robots']);
Route::get('/llms.txt', [Site\PageController::class, 'llms'])->name('llms');
Route::get('/feed.xml', [Site\PageController::class, 'feed'])->name('feed');

Route::get('/assistant', [Site\AssistantController::class, 'page'])->name('assistant');
Route::get('/assistant/me', [Site\AssistantLeadController::class, 'me'])->middleware('throttle:assistant-me')->name('assistant.me');
Route::post('/assistant/lead', [Site\AssistantLeadController::class, 'submit'])->middleware('throttle:assistant-lead')->name('assistant.lead');
Route::post('/assistant/verify', [Site\AssistantLeadController::class, 'verify'])->middleware('throttle:assistant-verify')->name('assistant.verify');
Route::post('/assistant/feedback', [Site\AssistantLeadController::class, 'feedback'])->middleware('throttle:assistant-feedback')->name('assistant.feedback');
Route::post('/assistant/chat', [Site\AssistantController::class, 'chat'])->middleware(['throttle:assistant-chat', 'assistant.session'])->name('assistant.chat');
Route::post('/assistant/reset', [Site\AssistantBookingController::class, 'reset'])->middleware(['throttle:assistant-reset', 'assistant.session'])->name('assistant.reset');
Route::post('/assistant/tts', [Site\AssistantController::class, 'tts'])->middleware(['throttle:assistant-tts', 'assistant.session'])->name('assistant.tts');

/* Cron: hit this URL from any uptime pinger / cPanel cron (every 5-15 min). Each task keeps its own interval. */
Route::match(['get', 'post'], '/cron/{token}/{task?}', Site\CronController::class)->middleware('throttle:30,1')->name('cron');

/* ------------------------------ Admin panel ------------------------------ */
Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [Admin\AuthController::class, 'showLogin'])->name('login');
        Route::post('login', [Admin\AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.submit');
    });

    Route::middleware(['auth', 'permission:admin.access'])->group(function () {
        Route::post('logout', [Admin\AuthController::class, 'logout'])->name('logout');
        Route::get('/', Admin\DashboardController::class)->name('dashboard');
        Route::post('media/upload', [Admin\MediaController::class, 'upload'])->name('media.upload');
        Route::get('profile', [Admin\ProfileController::class, 'edit'])->name('profile');
        Route::put('profile', [Admin\ProfileController::class, 'update'])->name('profile.update');

        // Articles (per-record ownership rules are enforced in the controller)
        Route::middleware('permission:articles.view')->group(function () {
            Route::get('articles', [Admin\ArticleController::class, 'index'])->name('articles.index');
            Route::get('articles/data', [Admin\ArticleController::class, 'data'])->name('articles.data');
            Route::post('articles/bulk', [Admin\ArticleController::class, 'bulk'])->name('articles.bulk');
        });
        Route::middleware('permission:articles.create')->group(function () {
            Route::get('articles/create', [Admin\ArticleController::class, 'create'])->name('articles.create');
            Route::post('articles', [Admin\ArticleController::class, 'store'])->name('articles.store');
        });
        Route::get('articles/{article}/edit', [Admin\ArticleController::class, 'edit'])->name('articles.edit');
        Route::get('articles/{article}/preview', [Admin\ArticleController::class, 'preview'])->middleware('permission:articles.view')->name('articles.preview');
        Route::put('articles/{article}', [Admin\ArticleController::class, 'update'])->name('articles.update');
        Route::delete('articles/{article}', [Admin\ArticleController::class, 'destroy'])->name('articles.destroy');
        Route::post('articles/import-url', [Admin\ArticleController::class, 'importUrl'])->middleware('permission:articles.create')->name('articles.import-url');
        Route::post('articles/{article}/ai-seo', [Admin\ArticleController::class, 'aiSeo'])->name('articles.ai-seo');
        Route::post('articles/ai-draft', [Admin\ArticleController::class, 'aiDraft'])->middleware('permission:articles.create')->name('articles.ai');

        Route::middleware('permission:pages.manage')->group(function () {
            Route::get('pages/data', [Admin\PageController::class, 'data'])->name('pages.data');
            Route::post('pages/bulk', [Admin\PageController::class, 'bulk'])->name('pages.bulk');
            Route::get('pages/{page}/preview', [Admin\PageController::class, 'preview'])->name('pages.preview');
            Route::resource('pages', Admin\PageController::class)->except(['show']);
        });

        Route::middleware('permission:seo.manage')->group(function () {
            Route::get('seo', [Admin\SeoController::class, 'index'])->name('seo.index');
            Route::get('seo/{key}', [Admin\SeoController::class, 'edit'])->name('seo.edit');
            Route::put('seo/{key}', [Admin\SeoController::class, 'update'])->name('seo.update');
            Route::delete('seo/{key}', [Admin\SeoController::class, 'destroy'])->name('seo.destroy');
        });

        Route::middleware('permission:categories.manage')->group(function () {
            Route::get('categories/data', [Admin\CategoryController::class, 'data'])->name('categories.data');
            Route::post('categories/bulk', [Admin\CategoryController::class, 'bulk'])->name('categories.bulk');
            Route::resource('categories', Admin\CategoryController::class)->except(['show']);
        });

        Route::middleware('permission:cars.manage')->group(function () {
            Route::get('car-masters', [Admin\CarMasterController::class, 'index'])->name('car-masters');
            Route::post('car-masters', [Admin\CarMasterController::class, 'store'])->name('car-masters.store');
            Route::put('car-masters/{type}/{id}', [Admin\CarMasterController::class, 'update'])->name('car-masters.update');
            Route::delete('car-masters/{type}/{id}', [Admin\CarMasterController::class, 'destroy'])->name('car-masters.destroy');
            Route::get('car-models/data', [Admin\CarModelController::class, 'data'])->name('car-models.data');
            Route::post('car-models/import-url', [Admin\CarModelController::class, 'importUrl'])->name('car-models.import-url');
            Route::get('car-models/{car_model}/preview', [Admin\CarModelController::class, 'preview'])->name('car-models.preview');
            Route::post('car-models/bulk', [Admin\CarModelController::class, 'bulk'])->name('car-models.bulk');
            Route::resource('car-models', Admin\CarModelController::class)->except(['show']);
            Route::get('comparisons/data', [Admin\ComparisonController::class, 'data'])->name('comparisons.data');
            Route::post('comparisons/bulk', [Admin\ComparisonController::class, 'bulk'])->name('comparisons.bulk');
            Route::resource('comparisons', Admin\ComparisonController::class)->except(['show']);
            Route::post('car-models/{car_model}/refresh', [Admin\CarModelController::class, 'refresh'])->name('car-models.refresh');
        });

        Route::middleware('permission:sources.manage')->group(function () {
            Route::get('sources/data', [Admin\NewsSourceController::class, 'data'])->name('sources.data');
            Route::post('sources/bulk', [Admin\NewsSourceController::class, 'bulk'])->name('sources.bulk');
            Route::resource('sources', Admin\NewsSourceController::class)->except(['show']);
            Route::post('sources/{source}/fetch', [Admin\NewsSourceController::class, 'fetch'])->name('sources.fetch');
        });

        Route::middleware('permission:listings.manage')->group(function () {
            Route::get('listings/data', [Admin\ListingController::class, 'data'])->name('listings.data');
            Route::get('listings/{listing}/preview', [Admin\ListingController::class, 'preview'])->name('listings.preview');
            Route::post('listings/bulk', [Admin\ListingController::class, 'bulk'])->name('listings.bulk');
            Route::get('listing-sources/data', [Admin\ListingSourceController::class, 'data'])->name('listing-sources.data');
            Route::post('listing-sources/bulk', [Admin\ListingSourceController::class, 'bulk'])->name('listing-sources.bulk');
            Route::resource('listings', Admin\ListingController::class)->except(['show']);
            Route::post('listings-import', [Admin\ListingController::class, 'importCsv'])->name('listings.import');
            Route::resource('listing-sources', Admin\ListingSourceController::class)->except(['show']);
            Route::post('listing-sources/{listing_source}/run', [Admin\ListingSourceController::class, 'run'])->name('listing-sources.run');
            Route::post('listing-sources-import-url', [Admin\ListingSourceController::class, 'importUrl'])->name('listing-sources.import-url');
        });

        Route::middleware('permission:assistant.manage')->group(function () {
            Route::get('assistant', [Admin\AssistantController::class, 'index'])->name('assistant.index');
            Route::get('assistant/{session}', [Admin\AssistantController::class, 'show'])->name('assistant.show');
            Route::post('assistant/{session}/block', [Admin\AssistantController::class, 'block'])->name('assistant.block');
            Route::post('assistant/{session}/reset', [Admin\AssistantController::class, 'reset'])->name('assistant.reset');
        });

        Route::middleware('permission:leads.view')->group(function () {
            Route::get('leads', [Admin\LeadController::class, 'index'])->name('leads.index');
            Route::get('leads/data', [Admin\LeadController::class, 'data'])->name('leads.data');
            Route::post('leads/bulk', [Admin\LeadController::class, 'bulk'])->middleware('permission:leads.manage')->name('leads.bulk');
            Route::get('leads/{lead}', [Admin\LeadController::class, 'show'])->name('leads.show');
        });
        Route::middleware('permission:leads.manage')->group(function () {
            Route::put('leads/{lead}', [Admin\LeadController::class, 'update'])->name('leads.update');
            Route::delete('leads/{lead}', [Admin\LeadController::class, 'destroy'])->name('leads.destroy');
        });

        Route::middleware('permission:videos.manage')->group(function () {
            Route::get('videos', [Admin\VideoController::class, 'index'])->name('videos.index');
            Route::get('videos/data', [Admin\VideoController::class, 'data'])->name('videos.data');
            Route::post('videos/bulk', [Admin\VideoController::class, 'bulk'])->name('videos.bulk');
            Route::get('videos/create', [Admin\VideoController::class, 'create'])->name('videos.create');
            Route::get('videos/{video}/edit', [Admin\VideoController::class, 'edit'])->name('videos.edit');
            Route::post('videos', [Admin\VideoController::class, 'store'])->name('videos.store');
            Route::post('videos/search', [Admin\VideoController::class, 'search'])->name('videos.search');
            Route::put('videos/{video}', [Admin\VideoController::class, 'update'])->name('videos.update');
            Route::delete('videos/{video}', [Admin\VideoController::class, 'destroy'])->name('videos.destroy');
        });

        Route::middleware('permission:users.manage')->group(function () {
            Route::get('users/data', [Admin\UserController::class, 'data'])->name('users.data');
            Route::post('users/bulk', [Admin\UserController::class, 'bulk'])->name('users.bulk');
            Route::resource('users', Admin\UserController::class)->except(['show']);
        });
        Route::middleware('permission:roles.manage')->group(function () {
            Route::get('roles/data', [Admin\RoleController::class, 'data'])->name('roles.data');
            Route::post('roles/bulk', [Admin\RoleController::class, 'bulk'])->name('roles.bulk');
            Route::resource('roles', Admin\RoleController::class)->except(['show']);
        });

        Route::middleware('permission:settings.manage')->group(function () {
            Route::get('settings', [Admin\SettingController::class, 'edit'])->name('settings');
            Route::put('settings', [Admin\SettingController::class, 'update'])->name('settings.update');
            Route::post('settings/check', [Admin\SettingController::class, 'check'])->name('settings.check');
            Route::post('settings/reindex', [Admin\SettingController::class, 'reindex'])->name('settings.reindex');
            Route::get('home-settings', [Admin\HomeSettingController::class, 'edit'])->name('home-settings');
            Route::put('home-settings', [Admin\HomeSettingController::class, 'update'])->name('home-settings.update');
            Route::resource('menus', Admin\MenuItemController::class)->except(['show']);
        });

        Route::middleware('permission:automation.manage')->group(function () {
            Route::get('automation', [Admin\AutomationController::class, 'index'])->name('automation');
            Route::get('automation/logs', [Admin\AutomationController::class, 'logs'])->name('automation.logs');
            Route::post('automation/run', [Admin\AutomationController::class, 'run'])->name('automation.run');
        });
    });
});

/* Custom pages created in the admin: keep this LAST so it only matches when no other route does. */
Route::get('/{slug}', [Site\CustomPageController::class, 'show'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('page.show');
