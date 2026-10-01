<?php

namespace App\Models;

use App\Models\Concerns\Indexable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Listing extends Model
{
    use Indexable;

    protected $guarded = [];
    protected $casts = ['images' => 'array', 'last_seen_at' => 'datetime', 'sold_at' => 'datetime'];
    protected $with = ['brandMaster', 'fuelMaster'];

    public static function knowledgeType(): string { return 'listing'; }

    public function leads() { return $this->hasMany(Lead::class); }
    public function vehicleModel() { return $this->belongsTo(VehicleModel::class); }
    public function brandMaster() { return $this->belongsTo(VehicleBrand::class, 'brand_id'); }
    public function fuelMaster() { return $this->belongsTo(VehicleFuel::class, 'fuel_id'); }

    /** Brand and fuel are stored as ids of vehicle_brands / vehicle_fuels; these keep `$listing->brand` and `fill(['brand' => 'Tata'])` working by name. */
    public function getBrandAttribute(): ?string { return $this->brandMaster?->name; }
    public function getFuelAttribute(): ?string { return $this->fuelMaster?->name; }
    public function setBrandAttribute(?string $v): void
    {
        $this->attributes['brand_id'] = \App\Services\CarMasters::brandId($v);
        $this->unsetRelation('brandMaster');
    }
    public function setFuelAttribute(?string $v): void
    {
        $this->attributes['fuel_id'] = \App\Services\CarMasters::fuelId($v);
        $this->unsetRelation('fuelMaster');
    }
    public function source() { return $this->belongsTo(ListingSource::class, 'listing_source_id'); }

    public function scopeActive($q) { return $q->where('status', 'active'); }

    public function getImageUrlAttribute(): string
    {
        if (! $this->image_path) return asset('img/placeholder.svg');
        return Str::startsWith($this->image_path, ['http://', 'https://']) ? $this->image_path : (Str::startsWith($this->image_path, '/') ? asset(ltrim($this->image_path, '/')) : Storage::disk('public')->url($this->image_path));
    }

    public function getUrlAttribute(): string { return route('cars.show', $this->slug); }

    public function getPriceLabelAttribute(): string
    {
        if (! $this->price) return 'Price on request';
        if ($this->price >= 10000000) return '₹ '.rtrim(rtrim(number_format($this->price / 10000000, 2), '0'), '.').' Cr';
        if ($this->price >= 100000) return '₹ '.rtrim(rtrim(number_format($this->price / 100000, 2), '0'), '.').' Lakh';
        return '₹ '.number_format($this->price);
    }

    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug(Str::limit($title, 90, '')) ?: 'car';
        $slug = $base; $i = 2;
        while (static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }
        return $slug;
    }

    public function toKnowledge(): ?array
    {
        if ($this->status !== 'active') return null;
        $facts = array_filter([
            $this->year ? "Year {$this->year}" : null,
            $this->price ? "Price {$this->price_label}" : null,
            $this->km_driven ? number_format($this->km_driven).' km driven' : null,
            $this->fuel ? "Fuel {$this->fuel}" : null,
            $this->transmission ? "Transmission {$this->transmission}" : null,
            $this->owner ? "Owner {$this->owner}" : null,
            $this->city ? "Location {$this->city}" : null,
        ]);
        return [
            'title' => $this->title,
            'content' => trim("Used car listing. {$this->brand} {$this->model}. ".implode('. ', $facts).'. '.Str::limit(strip_tags((string) $this->description), 1500, '')),
            'url' => $this->url,
            'image' => $this->image_url,
        ];
    }
}
