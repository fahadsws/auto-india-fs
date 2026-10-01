<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\CarComparison;
use App\Models\VehicleModel;
use Illuminate\Http\Request;

class CompareController extends Controller
{
    /** Picker (any two cars) + editor-suggested pairs. */
    public function index(Request $r)
    {
        $r->validate(['a' => 'nullable|string|max:160', 'b' => 'nullable|string|max:160']);
        if ($r->filled('a') && $r->filled('b') && $r->a !== $r->b) {
            $a = VehicleModel::published()->where('slug', $r->a)->first();
            $b = VehicleModel::published()->where('slug', $r->b)->first();
            if ($a && $b) return redirect(CarComparison::urlFor($a, $b));
        }

        return view('site.compare.index', [
            'cars' => $this->pickerCars(),
            'selectedA' => $r->a, 'selectedB' => $r->b,
            'suggested' => $this->suggested(12),
            'error' => ($r->filled('a') && $r->a === $r->b) ? 'Pick two different cars to compare.' : null,
        ]);
    }

    public function show(string $pair)
    {
        [$a, $b] = $this->resolve($pair);
        $meta = CarComparison::forPair($a->id, $b->id)->where('is_active', true)->with('winner.brandMaster')->first();

        return view('site.compare.show', [
            'a' => $a, 'b' => $b, 'meta' => $meta,
            'rows' => $this->rows($a, $b),
            'cars' => $this->pickerCars(),
            'others' => $this->suggested(4, [$a->id, $b->id]),
        ]);
    }

    /** "creta-vs-seltos" → two published cars; tries every "-vs-" split since a slug may itself contain one. */
    private function resolve(string $pair): array
    {
        $offset = 0;
        while (($pos = strpos($pair, '-vs-', $offset)) !== false) {
            $offset = $pos + 1;
            $a = VehicleModel::published()->with(['brandMaster', 'bodyType', 'fuels'])->where('slug', substr($pair, 0, $pos))->first();
            $b = $a ? VehicleModel::published()->with(['brandMaster', 'bodyType', 'fuels'])->where('slug', substr($pair, $pos + 4))->first() : null;
            if ($a && $b && $a->id !== $b->id) return [$a, $b];
        }
        abort(404);
    }

    private function pickerCars()
    {
        return VehicleModel::published()->with('brandMaster')->get()->sortBy('full_name')->values();
    }

    private function suggested(int $limit, array $exclude = [])
    {
        return CarComparison::live()->with(['carA.brandMaster', 'carB.brandMaster'])->ordered()
            ->when($exclude, fn ($q) => $q->where(fn ($w) => $w->whereNotIn('car_a_id', $exclude)->orWhereNotIn('car_b_id', $exclude)))
            ->take($limit)->get();
    }

    /** Sections of [label, valueA, valueB, best?] where best is 'a' | 'b' | null. */
    private function rows(VehicleModel $a, VehicleModel $b): array
    {
        $none = '—';
        $best = fn ($x, $y, bool $lowerWins = true) => (! $x || ! $y || $x == $y) ? null : (($x < $y) === $lowerWins ? 'a' : 'b');

        $overview = [
            ['Starting price', $a->price_label, $b->price_label, $best($a->price_min, $b->price_min)],
            ['Top price', $a->price_max ? $this->money($a->price_max) : $none, $b->price_max ? $this->money($b->price_max) : $none, null],
            ['Status', $a->status_label, $b->status_label, null],
            ['Body type', $a->body_type ?: $none, $b->body_type ?: $none, null],
            ['Fuel', $a->fuel_types ? implode(' / ', $a->fuel_types) : $none, $b->fuel_types ? implode(' / ', $b->fuel_types) : $none, null],
            ['Launch date', $a->launch_date?->format('d M Y') ?? $none, $b->launch_date?->format('d M Y') ?? $none, null],
        ];

        $sa = (array) ($a->specs ?? []);
        $sb = (array) ($b->specs ?? []);
        // Match spec labels case-insensitively so "Engine" and "engine" line up.
        $keys = [];
        foreach ([$sa, $sb] as $set) foreach (array_keys($set) as $k) $keys[mb_strtolower($k)] ??= $k;
        $lookup = fn (array $set, string $lk) => collect($set)->first(fn ($v, $k) => mb_strtolower($k) === $lk);
        $specs = [];
        foreach ($keys as $lk => $label) $specs[] = [$label, $lookup($sa, $lk) ?? $none, $lookup($sb, $lk) ?? $none, null];

        return array_values(array_filter([['Overview', $overview], ['Specifications', $specs]], fn ($s) => $s[1]));
    }

    private function money(int $v): string
    {
        return $v >= 10000000 ? '₹ '.rtrim(rtrim(number_format($v / 10000000, 2), '0'), '.').' Cr' : '₹ '.rtrim(rtrim(number_format($v / 100000, 2), '0'), '.').' Lakh';
    }
}
