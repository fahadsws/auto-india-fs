<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** Admin-editable SEO for a built-in page (home, EMI calculator, listings...). No row = the page's own defaults apply. */
class SeoEntry extends Model
{
    /** route_key => [label, public route name, path] */
    public const PAGES = [
        'home' => ['Home page', 'home'],
        'newcars' => ['New cars', 'newcars.index'],
        'newbikes' => ['New bikes', 'newbikes.index'],
        'newtrucks' => ['New trucks', 'newtrucks.index'],
        'used' => ['Used cars', 'cars.index'],
        'news' => ['News', 'news.index'],
        'videos' => ['Videos', 'videos.index'],
        'compare' => ['Compare cars', 'compare.index'],
        'emi' => ['Car loan EMI calculator', 'emi'],
        'sell' => ['Sell your car', 'sell'],
        'about' => ['About us', 'about'],
        'contact' => ['Contact us', 'contact'],
    ];

    protected $guarded = [];
    protected $casts = ['faq' => 'array'];

    protected static function booted(): void
    {
        $flush = fn (self $m) => Cache::forget('seo.all');
        static::saved($flush);
        static::deleted($flush);
    }

    /** All rows keyed by route_key (one cached query per request cycle). */
    public static function all_(): \Illuminate\Support\Collection
    {
        return Cache::rememberForever('seo.all', fn () => static::query()->get()->keyBy('route_key'));
    }

    public static function forRoute(?string $routeName): ?self
    {
        if (! $routeName) return null;
        foreach (self::PAGES as $key => [, $name]) {
            if ($name === $routeName) return self::all_()->get($key);
        }

        return null;
    }

    public function isNoindex(): bool { return str_starts_with((string) $this->robots, 'noindex'); }

    public function faqItems(): array
    {
        return collect($this->faq ?? [])->filter(fn ($f) => filled($f['q'] ?? null) && filled($f['a'] ?? null))->values()->all();
    }
}
