<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

class Filters
{
    public const CITIES = ['Delhi', 'Mumbai', 'Bengaluru', 'Chennai', 'Hyderabad', 'Kolkata', 'Pune', 'Ahmedabad', 'Indore'];

    /** Budget bands in rupees for a vehicle type: key => [label, min, max]. */
    public static function budgets(string $vehicle = 'car'): array
    {
        return config("vehicles.$vehicle.budgets", config('vehicles.car.budgets'));
    }

    public static function budgetOptions(string $vehicle = 'car'): array
    {
        return collect(self::budgets($vehicle))->map(fn ($b, $k) => ['value' => $k, 'label' => $b[0]])->values()->all();
    }

    public static function applyBudget(Builder $q, string $column, $keys, string $vehicle = 'car'): void
    {
        $budgets = self::budgets($vehicle);
        $bands = collect((array) $keys)->map(fn ($k) => $budgets[$k] ?? null)->filter();
        if ($bands->isEmpty()) return;
        $q->where(function ($w) use ($bands, $column) {
            foreach ($bands as $b) {
                $w->orWhere(function ($x) use ($b, $column) {
                    $x->where($column, '>=', $b[1]);
                    if ($b[2]) $x->where($column, '<', $b[2]);
                });
            }
        });
    }

    /** Turn a value => count map into filter options. */
    public static function fromCounts($counts): array
    {
        return collect($counts)->map(fn ($n, $v) => ['value' => $v, 'label' => $v, 'count' => $n])->values()->all();
    }
}
