<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\HomeSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * EV charging station finder. Data comes from OpenStreetMap (the free, open "charging_station" tag) through the Overpass API,
 * cached on our server so a busy day never hammers the public service. Cities are geocoded from a built-in list first and
 * from OpenStreetMap Nominatim only when needed. No key, no scraping, no cost.
 */
class EvStationController extends Controller
{
    /** Quick city centres (lat, lng) - avoids a geocoding call for the common cities. */
    public const CITIES = \App\Support\Cities::ALL;

    private const OVERPASS = ['https://overpass-api.de/api/interpreter', 'https://overpass.kumi.systems/api/interpreter'];

    public function page()
    {
        return view('site.evstations', [
            'cities' => array_keys(self::CITIES),
            'homeSettings' => HomeSetting::current(),
            'latest' => Article::published()->latest('published_at')->take(5)->get(),
        ]);
    }

    /** City name -> coordinates. */
    public function geocode(Request $r)
    {
        $q = trim((string) $r->query('q', ''));
        if (mb_strlen($q) < 2 || mb_strlen($q) > 80) return response()->json(['message' => 'Please enter a city or area name.'], 422);

        foreach (self::CITIES as $name => [$lat, $lng]) {
            if (strcasecmp($name, $q) === 0) return response()->json(['name' => $name, 'lat' => $lat, 'lng' => $lng]);
        }

        $hit = Cache::remember('ev:geo:'.md5(mb_strtolower($q)), now()->addDays(14), function () use ($q) {
            try {
                $res = Http::timeout(12)->withHeaders(['User-Agent' => config('app.name', 'AutomobilIndia').' EV finder ('.config('app.url').')', 'Accept-Language' => 'en'])
                    ->get('https://nominatim.openstreetmap.org/search', ['q' => $q, 'format' => 'jsonv2', 'limit' => 1, 'countrycodes' => 'in']);
                $row = $res->successful() ? ($res->json()[0] ?? null) : null;
                return $row ? ['name' => explode(',', (string) ($row['display_name'] ?? $q))[0], 'lat' => (float) $row['lat'], 'lng' => (float) $row['lon']] : false;
            } catch (\Throwable $e) {
                Log::warning('EV geocode failed: '.$e->getMessage());
                return null;   // not cached long: Cache::remember stores null as a miss
            }
        });

        if (! $hit) return response()->json(['message' => 'We could not find that place in India. Try a nearby city name.'], 404);
        return response()->json($hit);
    }

    /** Charging stations around a point. */
    public function search(Request $r)
    {
        $d = $r->validate(['lat' => 'required|numeric|between:6,38', 'lng' => 'required|numeric|between:67,98', 'radius' => 'nullable|integer|between:1,25']);
        $lat = round((float) $d['lat'], 3);
        $lng = round((float) $d['lng'], 3);
        $km = (int) ($d['radius'] ?? 8);

        $rows = Cache::remember("ev:stations:$lat:$lng:$km", now()->addHours(6), fn () => $this->fetch($lat, $lng, $km));
        if ($rows === null) {
            return response()->json(['message' => 'The charging-station service is busy right now. Please try again in a minute, or open the area in Google Maps.', 'stations' => []], 503);
        }

        $stations = collect($rows)->map(function ($s) use ($lat, $lng) {
            $s['km'] = round($this->distance($lat, $lng, $s['lat'], $s['lng']), 1);
            return $s;
        })->sortBy('km')->values()->take(60)->all();

        return response()->json(['center' => ['lat' => $lat, 'lng' => $lng], 'radius' => $km, 'count' => count($stations), 'stations' => $stations,
            'source' => 'OpenStreetMap contributors']);
    }

    /** @return array<int,array>|null  null = every Overpass server failed (not cached) */
    private function fetch(float $lat, float $lng, int $km): ?array
    {
        $m = $km * 1000;
        $query = "[out:json][timeout:25];(node[\"amenity\"=\"charging_station\"](around:$m,$lat,$lng);way[\"amenity\"=\"charging_station\"](around:$m,$lat,$lng););out center 120;";

        foreach (self::OVERPASS as $url) {
            try {
                $res = Http::timeout(28)->asForm()->withHeaders(['User-Agent' => config('app.name', 'AutomobilIndia').' EV finder'])->post($url, ['data' => $query]);
                if (! $res->successful() || ! is_array($res->json('elements'))) continue;
                return collect($res->json('elements'))->map(fn ($e) => $this->shape($e))->filter()->values()->all();
            } catch (\Throwable $e) {
                Log::warning('EV Overpass failed on '.$url.': '.$e->getMessage());
            }
        }
        // Do not cache a failure: Cache::remember would keep the null as a miss anyway.
        return null;
    }

    private function shape(array $e): ?array
    {
        $t = $e['tags'] ?? [];
        $lat = $e['lat'] ?? ($e['center']['lat'] ?? null);
        $lng = $e['lon'] ?? ($e['center']['lon'] ?? null);
        if ($lat === null || $lng === null) return null;

        $sockets = [];
        foreach (['type2' => 'Type 2', 'type2_combo' => 'CCS2', 'chademo' => 'CHAdeMO', 'type1' => 'Type 1', 'tesla_supercharger' => 'Tesla', 'schuko' => 'AC socket', 'type3c' => 'Type 3', 'gb_t' => 'GB/T'] as $k => $label) {
            if (! empty($t["socket:$k"]) && $t["socket:$k"] !== 'no') {
                $out = $t["socket:$k:output"] ?? null;
                $sockets[] = $label.($out ? ' '.$out : '').(is_numeric($t["socket:$k"]) && (int) $t["socket:$k"] > 1 ? ' ×'.(int) $t["socket:$k"] : '');
            }
        }
        $addr = collect([trim(($t['addr:housenumber'] ?? '').' '.($t['addr:street'] ?? '')), $t['addr:suburb'] ?? null, $t['addr:city'] ?? null])->filter()->implode(', ');

        return [
            'name' => $this->clip($t['name'] ?? $t['brand'] ?? $t['operator'] ?? 'EV charging station', 80),
            'operator' => $this->clip($t['operator'] ?? $t['brand'] ?? '', 60),
            'lat' => (float) $lat, 'lng' => (float) $lng,
            'address' => $this->clip($addr, 120),
            'sockets' => array_slice($sockets, 0, 6),
            'capacity' => isset($t['capacity']) && is_numeric($t['capacity']) ? (int) $t['capacity'] : null,
            'fee' => isset($t['fee']) ? ($t['fee'] === 'yes' ? 'Paid' : ($t['fee'] === 'no' ? 'Free' : null)) : null,
            'hours' => $this->clip($t['opening_hours'] ?? '', 60),
            'access' => ($t['access'] ?? '') === 'private' ? 'Private' : null,
        ];
    }

    private function clip(string $s, int $n): string
    {
        return trim(mb_substr(strip_tags($s), 0, $n));
    }

    private function distance(float $la1, float $lo1, float $la2, float $lo2): float
    {
        $r = 6371; $p = M_PI / 180;
        $a = 0.5 - cos(($la2 - $la1) * $p) / 2 + cos($la1 * $p) * cos($la2 * $p) * (1 - cos(($lo2 - $lo1) * $p)) / 2;
        return 2 * $r * asin(sqrt($a));
    }
}
