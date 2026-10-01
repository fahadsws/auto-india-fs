<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class MenuItem extends Model
{
    protected $fillable = ['location', 'parent_id', 'title', 'url', 'open_new_tab', 'is_active', 'sort_order'];

    protected $casts = ['open_new_tab' => 'boolean', 'is_active' => 'boolean'];

    protected static function booted(): void
    {
        $flush = fn () => collect(['header', 'footer'])->each(fn ($l) => Cache::forget("menu.$l"));
        static::saved($flush);
        static::deleted($flush);
    }

    public function parent() { return $this->belongsTo(self::class, 'parent_id'); }

    public function children() { return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('id'); }

    /** Active top-level items with their active children, cached per location. */
    public static function tree(string $location)
    {
        return Cache::rememberForever("menu.$location", fn () => static::where('location', $location)
            ->whereNull('parent_id')->where('is_active', true)->orderBy('sort_order')->orderBy('id')
            ->with(['children' => fn ($q) => $q->where('is_active', true)])->get());
    }
}
