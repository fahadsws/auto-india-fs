<?php

namespace App\Models;

use App\Models\Concerns\Indexable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CarComparison extends Model
{
    use Indexable;

    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean', 'is_featured' => 'boolean'];

    public static function knowledgeType(): string { return 'comparison'; }

    /** Our own editorial comparison (intro, verdict, pick) becomes assistant knowledge, so it answers "X vs Y" from OUR view. */
    public function toKnowledge(): ?array
    {
        $a = $this->carA; $b = $this->carB;
        if (! $this->is_active || ! $a || ! $b || ! $a->is_published || ! $b->is_published) return null;
        $facts = fn (VehicleModel $m) => $m->full_name.': '.$m->price_label.($m->body_type ? ', '.$m->body_type : '').($m->fuel_types ? ', '.implode('/', $m->fuel_types) : '');
        return [
            'title' => $this->heading,
            'content' => trim(Str::limit(strip_tags('Comparison '.$this->heading.'. '.$facts($a).'. '.$facts($b).'. '.($this->intro ?? '').' '.($this->verdict ?? '')
                .($this->winner ? ' Our pick: '.$this->winner->full_name.'.' : '')), 3500, '')),
            'url' => $this->url,
            'image' => $a->hero_url,
        ];
    }

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
