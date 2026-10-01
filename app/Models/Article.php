<?php

namespace App\Models;

use App\Models\Concerns\Indexable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Article extends Model
{
    use Indexable;

    protected $guarded = [];
    protected $casts = ['published_at' => 'datetime', 'is_ai_generated' => 'boolean', 'tags' => 'array', 'tldr' => 'array', 'faq' => 'array', 'source_links' => 'array'];

    public static function knowledgeType(): string { return 'article'; }

    public function category() { return $this->belongsTo(Category::class); }
    public function author() { return $this->belongsTo(User::class, 'user_id'); }
    public function source() { return $this->belongsTo(NewsSource::class, 'news_source_id'); }
    public function videos() { return $this->belongsToMany(Video::class); }
    public function vehicleModels() { return $this->belongsToMany(VehicleModel::class)->withPivot('event'); }

    public function scopePublished($q)
    {
        return $q->where('status', 'published')->where('published_at', '<=', now())->whereNull('duplicate_of');
    }

    /**
     * Story identity: "<brand-model>:<event group>". Launch/facelift/teaser are one "launch" family (an unveil is reported under
     * all three); price and spec changes are "update". Anything else (reviews, industry news) has no key.
     */
    public static function storyKey(?string $brand, ?string $model, ?string $event): ?string
    {
        $group = match ($event) { 'launch', 'facelift', 'teaser', 'reveal', 'unveil' => 'launch', 'price_update', 'spec_update', 'price', 'spec' => 'update', default => null };
        $name = Str::slug(trim(preg_replace('/^'.preg_quote((string) $brand, '/').'\s+/i', '', (string) $model)));
        if (! $group || ! $brand || $name === '') return null;
        return Str::slug($brand).'-'.$name.':'.$group;
    }

    /** Same story? Compares ignoring hyphen placement, since the pre-check and the writer may slug "3 Series" differently. */
    public static function sameStory(?string $a, ?string $b): bool
    {
        if (! $a || ! $b || ! str_contains($a, ':') || ! str_contains($b, ':')) return false;
        $n = fn ($k) => str_replace('-', '', $k);
        return $n($a) === $n($b);
    }

    /** How long a story key blocks a second article: launches are covered for weeks, price/spec news for days. */
    public static function storyWindowDays(?string $key): int
    {
        return str_ends_with((string) $key, ':update') ? 7 : 21;
    }

    public function getImageUrlAttribute(): string
    {
        if (! $this->image_path) return asset('img/placeholder.svg');
        return Str::startsWith($this->image_path, ['http://', 'https://']) ? $this->image_path : (Str::startsWith($this->image_path, '/') ? asset(ltrim($this->image_path, '/')) : Storage::disk('public')->url($this->image_path));
    }

    public function getUrlAttribute(): string { return route('news.show', $this->slug); }

    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug(Str::limit($title, 90, '')) ?: 'article';
        $slug = $base; $i = 2;
        while (static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }
        return $slug;
    }

    public function toKnowledge(): ?array
    {
        if ($this->status !== 'published') return null;
        return [
            'title' => $this->title,
            'content' => Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags(($this->excerpt ?? '').' '.$this->body))), 3500, ''),
            'url' => $this->url,
            'image' => $this->image_url,
        ];
    }
}
