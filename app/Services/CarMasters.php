<?php

namespace App\Services;

use App\Models\VehicleBrand;
use App\Models\VehicleFuel;
use Illuminate\Support\Str;

/**
 * Resolves brand / fuel names from scraped or typed data to rows of the car master tables (vehicle_brands, vehicle_fuels),
 * creating the row when it does not exist yet. Listings store only the resulting ids.
 */
class CarMasters
{
    private const FUELS = ['cng' => 'CNG', 'lpg' => 'LPG', 'ev' => 'Electric', 'electric' => 'Electric', 'petrol' => 'Petrol', 'diesel' => 'Diesel', 'hybrid' => 'Hybrid',
        'petrol+cng' => 'CNG', 'cng+petrol' => 'CNG'];
    /** Words that show up where a brand should be but are not one ("Used Hyundai...", "2019 ..."). */
    private const NOT_BRANDS = ['used', 'new', 'buy', 'second', 'secondhand', 'pre', 'owned', 'certified', 'car', 'cars', 'sale', 'for', 'the', 'unknown', 'na', 'n/a', 'none', 'other'];

    private static array $brandCache = [];
    private static array $fuelCache = [];

    /** vehicle_brands.id for a brand name (case-insensitive, aliases like "Maruti" resolved); created when missing. Null for blank/implausible names. */
    public static function brandId(?string $name, bool $create = true): ?int
    {
        $name = trim(preg_replace('/\s+/', ' ', (string) $name));
        if (! self::plausibleBrand($name)) return null;
        $canonical = CarUpdater::canonicalBrand($name);
        $key = mb_strtolower($canonical);
        if (array_key_exists($key, self::$brandCache) && (self::$brandCache[$key] || ! $create)) return self::$brandCache[$key];

        $id = VehicleBrand::whereRaw('lower(name) = ?', [$key])->value('id');
        if (! $id && $create) $id = VehicleBrand::create(['name' => $canonical, 'is_active' => true])->id;    // slug is set by the model
        return self::$brandCache[$key] = $id;
    }

    /** vehicle_fuels.id for a fuel name ("petrol", "CNG", "Petrol + CNG" ...); created when missing. */
    public static function fuelId(?string $name, bool $create = true): ?int
    {
        $name = trim(preg_replace('/\s+/', ' ', (string) $name));
        if ($name === '' || mb_strlen($name) > 30 || ! preg_match('/^[\p{L}][\p{L}\s+\-\/]*$/u', $name)) return null;
        $canonical = self::FUELS[mb_strtolower(str_replace(' ', '', $name))] ?? Str::title($name);
        $key = mb_strtolower($canonical);
        if (array_key_exists($key, self::$fuelCache) && (self::$fuelCache[$key] || ! $create)) return self::$fuelCache[$key];

        $id = VehicleFuel::whereRaw('lower(name) = ?', [$key])->value('id');
        if (! $id && $create) $id = VehicleFuel::create(['name' => $canonical, 'is_active' => true])->id;
        return self::$fuelCache[$key] = $id;
    }

    /** Find a known brand (master table or alias list) at the start of / inside a listing title. Never creates anything. */
    public static function detectBrand(string $title): ?string
    {
        $names = array_merge(CarUpdater::brandNames(), VehicleBrand::pluck('name')->all());
        usort($names, fn ($a, $b) => strlen($b) <=> strlen($a));                 // "Maruti Suzuki" before "Suzuki"
        foreach (array_unique($names) as $b) {
            if (preg_match('/\b'.preg_quote($b, '/').'\b/i', $title)) return CarUpdater::canonicalBrand($b);
        }
        return null;
    }

    public static function plausibleBrand(string $name): bool
    {
        return $name !== '' && mb_strlen($name) >= 2 && mb_strlen($name) <= 40
            && preg_match('/^\p{L}[\p{L}\p{N}\s&.\-]*$/u', $name) === 1
            && ! in_array(mb_strtolower($name), self::NOT_BRANDS, true);
    }

    public static function flush(): void
    {
        self::$brandCache = self::$fuelCache = [];
    }
}
