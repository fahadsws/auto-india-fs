<?php

namespace App\Services;

use App\Models\Setting;

/**
 * Settings for the cost-per-km page: fuel prices and the running cost of the alternatives (bike / auto / cab).
 *
 * The page does the maths in the browser, so every change shows instantly and no AI or server call is needed:
 *   fuel / month  = km * fuel price / average
 *   fixed / month = EMI + service / 12 + insurance / 12
 *   Rs per km     = (fuel + fixed) / km
 */
class CostPerKm
{
    /** key => [label, average unit, price unit, default price, default average, slider range for the average]. */
    public const FUELS = [
        'petrol' => ['Petrol', 'km/l', '₹/litre', 105, 16, [5, 35]],
        'diesel' => ['Diesel', 'km/l', '₹/litre', 92, 18, [5, 35]],
        'cng' => ['CNG', 'km/kg', '₹/kg', 78, 24, [10, 40]],
        'electric' => ['Electric', 'km/kWh', '₹/kWh', 9, 6, [3, 12]],
    ];

    /** Typical all-in running cost per km of the alternatives, used for the "kaun sasta" comparison and the dial pins. */
    public const BENCHMARKS = ['bike' => ['Bike', 3.5], 'auto' => ['Auto', 12], 'cab' => ['Cab', 16]];

    /** Prices and benchmarks as the admin set them (Settings -> Cost per km calculator), with safe defaults. */
    public static function config(): array
    {
        $fuels = [];
        foreach (self::FUELS as $k => [$label, $avgUnit, $priceUnit, $price, $avg, $range]) {
            $fuels[$k] = ['label' => $label, 'avg_unit' => $avgUnit, 'price_unit' => $priceUnit, 'price' => self::num(Setting::get("costkm.{$k}_price"), $price, 1, 500),
                'average' => $avg, 'min' => $range[0], 'max' => $range[1]];
        }
        $bench = [];
        foreach (self::BENCHMARKS as $k => [$label, $default]) $bench[$k] = ['label' => $label, 'rate' => self::num(Setting::get("costkm.{$k}_rate"), $default, 0.5, 100)];
        return ['fuels' => $fuels, 'bench' => $bench];
    }

    private static function num(mixed $v, float|int $default, float|int $min, float|int $max): float
    {
        $n = is_numeric($v) ? (float) $v : (float) $default;
        return min(max($n, $min), $max);
    }
}
