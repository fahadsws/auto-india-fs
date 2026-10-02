<?php

namespace App\Models;

use App\Models\Concerns\Indexable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Page extends Model
{
    use SoftDeletes, Indexable;

    public const TEMPLATES = ['default' => 'Content + sidebar', 'full' => 'Full width', 'no-sidebar' => 'Content only (no sidebar)'];
    public const SCHEMA_TYPES = \App\Support\SeoRules::SCHEMA_TYPES;
    public const ROBOTS = \App\Support\SeoRules::ROBOTS;

    /** Paths that already belong to the site and must never be taken by a page slug. */
    public const RESERVED = ['admin', 'cron', 'assistant', 'news', 'new-cars', 'new-bikes', 'new-trucks', 'cars', 'compare', 'videos', 'search', 'lead',
        'about', 'contact', 'sell-your-car', 'car-emi-calculator', 'cost-per-km-calculator', 'roast-my-car', 'sitemap', 'robots', 'llms', 'feed', 'storage', 'uploads', 'css', 'js', 'img', 'vendor', 'build', 'login', 'logout', 'api'];

    protected $guarded = [];
    protected $casts = ['faq' => 'array', 'show_lead' => 'boolean', 'show_ads' => 'boolean', 'show_news' => 'boolean', 'published_at' => 'datetime'];

    public function author() { return $this->belongsTo(User::class, 'author_id'); }

    public function scopePublished($q)
    {
        return $q->where('status', 'published')->where(fn ($x) => $x->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    public function getUrlAttribute(): string { return url('/'.$this->slug); }

    public function getImageUrlAttribute(): ?string
    {
        return $this->featured_image ? (Str::startsWith($this->featured_image, ['http://', 'https://']) ? $this->featured_image : asset(ltrim($this->featured_image, '/'))) : null;
    }

    public function getIsLiveAttribute(): bool
    {
        return $this->status === 'published' && (! $this->published_at || $this->published_at->lte(now()));
    }

    /** Clean FAQ rows (both question and answer present). */
    public function faqItems(): array
    {
        return collect($this->faq ?? [])->filter(fn ($f) => filled($f['q'] ?? null) && filled($f['a'] ?? null))->values()->all();
    }

    public static function knowledgeType(): string { return 'webpage'; }

    public function toKnowledge(): ?array
    {
        if (! $this->is_live) return null;
        $faq = collect($this->faqItems())->map(fn ($f) => 'Q: '.$f['q'].' A: '.strip_tags($f['a']))->implode(' ');
        return [
            'title' => $this->title,
            'content' => Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags(($this->excerpt ?? '').' '.$this->body.' '.$faq))), 3500, ''),
            'url' => $this->url,
            'image' => $this->image_url,
        ];
    }
}
