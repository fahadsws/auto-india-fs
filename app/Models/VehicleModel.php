<?php

namespace App\Models;

use App\Models\Concerns\Indexable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VehicleModel extends Model
{
    use Indexable;

    protected $guarded = [];
    /** Whatever reaches the JSON columns is made valid UTF-8 first, so one odd character can never break a save. */
    public function setSpecsAttribute($value): void { $this->attributes['specs'] = $value === null ? null : json_encode(\App\Services\AiClient::utf8($value), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE); }
    public function setHighlightsAttribute($value): void { $this->attributes['highlights'] = $value === null ? null : json_encode(\App\Services\AiClient::utf8($value), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE); }
    public function setFaqAttribute($value): void { $this->attributes['faq'] = $value === null ? null : json_encode(\App\Services\AiClient::utf8($value), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE); }

    protected $casts = [
        'highlights' => 'array', 'specs' => 'array', 'faq' => 'array', 'gallery' => 'array', 'archive_gallery' => 'array',
        'image_hashes' => 'array', 'locked' => 'array', 'launch_date' => 'date', 'latest_event_at' => 'datetime', 'refreshed_at' => 'datetime',
        'needs_refresh' => 'boolean', 'is_published' => 'boolean',
    ];

    public static function knowledgeType(): string { return 'car'; }

    public function articles() { return $this->belongsToMany(Article::class)->withPivot('event'); }
    public function brandMaster() { return $this->belongsTo(VehicleBrand::class, 'brand_id'); }
    public function bodyType() { return $this->belongsTo(VehicleBodyType::class, 'body_type_id'); }
    public function fuels() { return $this->belongsToMany(VehicleFuel::class, 'vehicle_model_fuel'); }
    public function listings() { return $this->hasMany(Listing::class); }

    public function scopePublished($q) { return $q->where('is_published', true); }
    public function scopeOfType($q, string $type) { return $q->where('vehicle_type', $type); }

    public function getBrandAttribute(): ?string { return $this->brandMaster()->value('name'); }
    public function getBodyTypeAttribute(): ?string { return $this->bodyType()->value('name'); }
    public function getFuelTypesAttribute(): array { return $this->relationLoaded('fuels') ? $this->fuels->pluck('name')->all() : $this->fuels()->pluck('name')->all(); }
    public function getFullNameAttribute(): string { return trim(($this->brandMaster?->name ?? '').' '.$this->name); }
    public function getUrlAttribute(): string { return route((config("vehicles.{$this->vehicle_type}.route") ?? 'newcars').'.show', $this->slug); }

    public function isLocked(string $field): bool { return in_array($field, $this->locked ?? [], true); }

    private static function imgUrl(?string $p): string
    {
        if (! $p) return asset('img/placeholder.svg');
        return Str::startsWith($p, ['http://', 'https://']) ? $p : (Str::startsWith($p, '/') ? asset(ltrim($p, '/')) : Storage::disk('public')->url($p));
    }

    public function getHeroUrlAttribute(): string { return self::imgUrl($this->hero_image ?: ($this->gallery[0] ?? null)); }
    public function galleryUrls(): array { return array_map([self::class, 'imgUrl'], $this->gallery ?? []); }

    public function getPriceLabelAttribute(): string
    {
        $fmt = function ($v) {
            if ($v >= 10000000) return rtrim(rtrim(number_format($v / 10000000, 2), '0'), '.').' Cr';
            return rtrim(rtrim(number_format($v / 100000, 2), '0'), '.').' Lakh';
        };
        if (! $this->price_min) return $this->status === 'upcoming' ? 'Expected soon' : 'Price awaited';
        $s = '₹ '.$fmt($this->price_min);
        return $s;
    }

    public function getStatusLabelAttribute(): string
    {
        return ['upcoming' => 'Upcoming', 'launched' => 'Launched', 'facelift' => 'Facelift launched', 'discontinued' => 'Discontinued'][$this->status] ?? ucfirst($this->status);
    }

    public function toKnowledge(): ?array
    {
        if (! $this->is_published) return null;
        $specs = collect($this->specs ?? [])->map(fn ($v, $k) => "$k: $v")->implode('; ');
        return [
            'title' => $this->full_name.' ('.$this->status_label.')',
            'content' => trim('New '.strtolower(config("vehicles.{$this->vehicle_type}.label") ?? 'car')." model.{$this->full_name}. Status: {$this->status}. Price: {$this->price_label}. "
                .($this->body_type ? "Body type {$this->body_type}. " : '').($this->fuel_types ? 'Fuel: '.implode(', ', $this->fuel_types).'. ' : '')
                .$specs.'. '.$this->extraKnowledge().Str::limit(strip_tags((string) $this->overview), 2200, '')),
            'url' => $this->url,
            'image' => $this->hero_url,
        ];
    }

    /** Highlights and FAQ answers, so the assistant can answer from them too. */
    private function extraKnowledge(): string
    {
        $hl = collect($this->highlights ?? [])->map(fn ($h) => is_array($h) ? ($h['text'] ?? $h['title'] ?? implode(' ', $h)) : $h)->filter()->implode('; ');
        $faq = collect($this->faq ?? [])->filter(fn ($f) => filled($f['q'] ?? null) && filled($f['a'] ?? null))->map(fn ($f) => 'Q: '.$f['q'].' A: '.strip_tags($f['a']))->implode(' ');
        return trim(($hl ? 'Highlights: '.$hl.'. ' : '').($faq ? 'FAQ: '.Str::limit($faq, 1500, '').' ' : ''));
    }
}
