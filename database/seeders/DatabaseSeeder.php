<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Category;
use App\Models\Listing;
use App\Models\ListingSource;
use App\Models\NewsSource;
use App\Models\Setting;
use App\Models\User;
use App\Services\KnowledgeBase;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    /** permission => label group */
    public const PERMISSIONS = [
        'Dashboard' => ['admin.access'],
        'Articles' => ['articles.view', 'articles.create', 'articles.edit_own', 'articles.edit_all', 'articles.publish', 'articles.delete'],
        'Content' => ['categories.manage', 'sources.manage', 'videos.manage', 'cars.manage'],
        'Cars & Leads' => ['listings.manage', 'leads.view', 'leads.manage'],
        'System' => ['users.manage', 'roles.manage', 'settings.manage', 'automation.manage'],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $perms) {
            foreach ($perms as $p) Permission::findOrCreate($p, 'web');
        }
        $all = Permission::pluck('name')->all();

        Role::findOrCreate('Super Admin', 'web');
        Role::findOrCreate('Admin', 'web')->syncPermissions(array_diff($all, ['roles.manage']));
        Role::findOrCreate('Editor', 'web')->syncPermissions([
            'admin.access', 'articles.view', 'articles.create', 'articles.edit_own', 'articles.edit_all', 'articles.publish', 'articles.delete',
            'categories.manage', 'videos.manage', 'sources.manage', 'cars.manage',
        ]);
        Role::findOrCreate('Author', 'web')->syncPermissions(['admin.access', 'articles.view', 'articles.create', 'articles.edit_own']);
        Role::findOrCreate('Sales', 'web')->syncPermissions(['admin.access', 'listings.manage', 'leads.view', 'leads.manage']);

        $admin = User::firstOrCreate(['email' => 'admin@automobilindia.test'], ['name' => 'Super Admin', 'password' => 'Admin@12345']);
        $admin->assignRole('Super Admin');

        foreach (['Car News', 'Reviews', 'Launches', 'Electric Vehicles', 'Buying Guides', 'Industry'] as $c) {
            Category::firstOrCreate(['slug' => Str::slug($c)], ['name' => $c]);
        }
        $news = Category::where('slug', 'car-news')->first();

        // name, type, url, link pattern, active, scope
        foreach ([
            ['Autocar India', 'rss', 'https://www.autocarindia.com/rss/news', null, true, 'india'],
            ['CarWale', 'rss', 'https://www.carwale.com/rss', null, true, 'india'],
            ['Rushlane', 'rss', 'https://www.rushlane.com/feed', null, true, 'india'],
            ['Motorbeam', 'rss', 'https://www.motorbeam.com/feed/', null, true, 'india'],
            ['Motoroids', 'rss', 'https://www.motoroids.com/feed/', null, true, 'india'],
            ['GaadiWaadi', 'rss', 'https://www.gaadiwaadi.com/feed/', null, true, 'india'],
            ['ET Auto', 'rss', 'https://auto.economictimes.indiatimes.com/rss/topstories', null, true, 'india'],
            ['Mint Auto', 'rss', 'https://www.livemint.com/rss/auto', null, true, 'india'],
            ['EVreporter', 'rss', 'https://www.evreporter.com/feed/', null, true, 'india'],
            ['IndianAutosBlog', 'rss', 'https://www.indianautosblog.com/feed', null, true, 'india'],
            ['CarDekho', 'page', 'https://www.cardekho.com/india-car-news.htm', '/news/', true, 'india'],
            ['ZigWheels', 'page', 'https://www.zigwheels.com/news-features', '/news-features/', true, 'india'],
            ['Autocar UK', 'rss', 'https://www.autocar.co.uk/rss', null, false, 'global'],
            ['Carscoops', 'rss', 'https://www.carscoops.com/feed/', null, true, 'global'],
            ['Motor1', 'rss', 'https://www.motor1.com/rss/articles/all/', null, true, 'global'],
        ] as [$name, $type, $url, $pattern, $active, $scope]) {
            NewsSource::firstOrCreate(['name' => $name], ['type' => $type, 'scope' => $scope, 'feed_url' => $url, 'link_pattern' => $pattern, 'category_id' => $news?->id, 'is_active' => $active]);
        }

        // Marketplace scrapers: robots.txt respected, throttled. list_urls = one listing page per line.
        foreach ([
            ['CarDekho Used', 'https://www.cardekho.com', '/used-car-details/', ['mumbai', 'pune', 'hyderabad', 'chennai', 'kolkata'], 'https://www.cardekho.com/used-car-in-%s'],
            ['Spinny', 'https://www.spinny.com', '/buy-used-cars/', ['mumbai', 'delhi-ncr', 'chennai', 'kolkata', 'ahmedabad', 'gurgaon'], 'https://www.spinny.com/used-cars-in-%s/s/'],
        ] as [$name, $home, $pattern, $cities, $tpl]) {
            ListingSource::firstOrCreate(['name' => $name], [
                'url' => $home, 'format' => 'json', 'mode' => 'scrape', 'detail_pattern' => $pattern,
                'list_urls' => implode("\n", array_map(fn ($c) => sprintf($tpl, $c), $cities)),
                'max_per_run' => 20, 'delay_ms' => 1500, 'respect_robots' => true, 'is_active' => true,
            ]);
        }

        $defaults = [
            'site.name' => 'Automobil India', 'site.tagline' => "India's AI-powered car news, reviews and used-car marketplace",
            'site.about' => 'Automobil India brings you the latest car news, expert reviews, videos and a marketplace of used cars, with an AI assistant that helps you find the right car.',
            'site.email' => 'info@automobilindia.test', 'site.phone' => '+91 00000 00000', 'site.address' => 'India',
            'ai.base_url' => 'https://openrouter.ai/api/v1', 'ai.model' => 'meta-llama/llama-3.3-70b-instruct:free', 'ai.fallback_model' => 'google/gemma-3-27b-it:free',
            'news.per_run' => '3', 'news.auto_publish' => '1', 'youtube.auto_attach' => '1',
            'assistant.name' => 'Auto Guide', 'assistant.enabled' => '1',
            'elevenlabs.voice_id' => '21m00Tcm4TlvDq8ikWAM', 'elevenlabs.model' => 'eleven_flash_v2_5',
        ];
        foreach ($defaults as $k => $v) {
            if (Setting::get($k) === null) Setting::put($k, $v);
        }
        if (! Setting::get('cron.token')) Setting::put('cron.token', Str::random(40));

        if (Listing::count() === 0) $this->demoListings();
        if (Article::count() === 0) $this->demoArticles($news);

        KnowledgeBase::reindexAll();
    }

    private function demoListings(): void
    {
        $rows = [
            ['Hyundai Creta SX Diesel', 'Hyundai', 'Creta', 2021, 1450000, 42000, 'Diesel', 'Manual', '1st Owner', 'Mumbai'],
            ['Maruti Suzuki Baleno Zeta', 'Maruti Suzuki', 'Baleno', 2022, 760000, 18000, 'Petrol', 'Automatic', '1st Owner', 'Delhi'],
            ['Tata Nexon EV Max', 'Tata', 'Nexon EV', 2023, 1590000, 12000, 'Electric', 'Automatic', '1st Owner', 'Bengaluru'],
            ['Mahindra XUV700 AX7', 'Mahindra', 'XUV700', 2022, 2350000, 31000, 'Diesel', 'Automatic', '1st Owner', 'Pune'],
            ['Honda City ZX', 'Honda', 'City', 2020, 1080000, 56000, 'Petrol', 'Manual', '2nd Owner', 'Hyderabad'],
            ['Toyota Innova Crysta 2.4 GX', 'Toyota', 'Innova Crysta', 2019, 1750000, 88000, 'Diesel', 'Manual', '1st Owner', 'Chennai'],
        ];
        foreach ($rows as [$t, $b, $m, $y, $p, $km, $f, $tr, $o, $c]) {
            Listing::create([
                'title' => $t, 'slug' => Listing::uniqueSlug("$t $y"), 'brand' => $b, 'model' => $m, 'year' => $y, 'price' => $p, 'km_driven' => $km,
                'fuel' => $f, 'transmission' => $tr, 'owner' => $o, 'city' => $c, 'status' => 'active', 'source_name' => 'Demo',
                'description' => "Well maintained $y $t in $c with full service history, clean interiors and no accident record. Insurance valid. Test drive available on request.",
            ]);
        }
    }

    private function demoArticles(?Category $cat): void
    {
        $a = [
            ['How to choose between petrol, diesel and electric in 2026', 'A practical guide to picking the right fuel type for your daily running, budget and city.', 'Buying Guides'],
            ['5 things to check before buying a used car in India', 'From service history to RC transfer, here is a checklist that saves you from costly surprises.', 'Buying Guides'],
            ['Why electric SUVs are becoming the default family car', 'Falling battery costs and better charging networks are changing the equation for Indian buyers.', 'Electric Vehicles'],
        ];
        foreach ($a as $i => [$title, $excerpt, $catName]) {
            Article::create([
                'category_id' => Category::where('name', $catName)->value('id') ?? $cat?->id,
                'title' => $title, 'slug' => Article::uniqueSlug($title), 'excerpt' => $excerpt,
                'body' => "<p>$excerpt</p><h2>What matters most</h2><p>Start with how many kilometres you drive each month, where you park, and how long you plan to keep the car. These three answers narrow the choice faster than any spec sheet.</p><ul><li>Set a total-cost budget, not just an ex-showroom price.</li><li>Test drive at least two rivals back to back.</li><li>Ask about resale value and service network in your city.</li></ul><p>Use our AI assistant on this site to compare options for your exact budget and city.</p>",
                'status' => 'published', 'published_at' => now()->subHours($i * 5 + 1), 'meta_title' => $title, 'meta_description' => $excerpt,
            ]);
        }
    }
}
