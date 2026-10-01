<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomeSetting extends Model
{
    /** Pages a vertical ad can be placed on: key => label. Horizontal ads always sit under the home hero. */
    public const AD_PAGES = ['new' => 'New cars / bikes / trucks', 'used' => 'Used cars', 'news' => 'News', 'videos' => 'Videos', 'emi' => 'Car EMI calculator'];

    protected $guarded = [];
    protected $casts = ['hero_banners' => 'array', 'trending_ids' => 'array', 'collections' => 'array', 'ads' => 'array'];

    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1]);
    }

    /** Saved trending model ids for one vehicle type, in display order. */
    public function trending(string $type): array
    {
        return array_values(array_map('intval', ($this->trending_ids ?? [])[$type] ?? []));
    }

    /** Active ads of an orientation; vertical ones are filtered to the page key they were placed on. */
    public function adsFor(string $orientation, ?string $page = null): \Illuminate\Support\Collection
    {
        return collect($this->ads ?? [])->filter(fn ($a) => ($a['active'] ?? true) && ! empty($a['image'])
            && ($a['orientation'] ?? 'horizontal') === $orientation
            && ($orientation === 'horizontal' || in_array($page, $a['pages'] ?? [], true)))->values();
    }
}
