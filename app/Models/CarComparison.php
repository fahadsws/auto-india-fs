<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CarComparison extends Model
{
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean', 'is_featured' => 'boolean'];

    public function carA() { return $this->belongsTo(VehicleModel::class, 'car_a_id'); }
    public function carB() { return $this->belongsTo(VehicleModel::class, 'car_b_id'); }
    public function winner() { return $this->belongsTo(VehicleModel::class, 'winner_id'); }

    /** Active pairs whose two cars are both published, featured first. */
    public function scopeLive($q)
    {
        return $q->where('is_active', true)
            ->whereHas('carA', fn ($c) => $c->where('is_published', true))
            ->whereHas('carB', fn ($c) => $c->where('is_published', true));
    }

    public function scopeOrdered($q) { return $q->orderByDesc('is_featured')->orderBy('sort_order')->orderByDesc('id'); }

    /** Pair in either order. */
    public function scopeForPair($q, int $a, int $b)
    {
        return $q->where(fn ($w) => $w->where(['car_a_id' => $a, 'car_b_id' => $b])->orWhere(fn ($x) => $x->where(['car_a_id' => $b, 'car_b_id' => $a])));
    }

    public function getHeadingAttribute(): string
    {
        return $this->title ?: ($this->carA?->full_name.' vs '.$this->carB?->full_name);
    }

    public function getUrlAttribute(): string { return self::urlFor($this->carA, $this->carB); }

    public static function urlFor(VehicleModel $a, VehicleModel $b): string
    {
        return route('compare.show', $a->slug.'-vs-'.$b->slug);
    }
}
