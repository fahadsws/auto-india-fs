<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Support\Cities;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Turns the browser's coordinates into a city name for the whole site (header, forms, finders). Nothing is stored about the visitor. */
class LocationController extends Controller
{
    public function cities()
    {
        return response()->json(array_keys(Cities::ALL))->header('Cache-Control', 'public, max-age=86400');
    }

    public function resolve(Request $r)
    {
        $d = $r->validate(['lat' => 'required|numeric|between:6,38', 'lng' => 'required|numeric|between:67,98']);
        $lat = (float) $d['lat']; $lng = (float) $d['lng'];

        if ($near = Cities::nearest($lat, $lng)) {
            return response()->json(['city' => $near['name'], 'lat' => round($lat, 4), 'lng' => round($lng, 4)]);
        }

        // Smaller town: ask OpenStreetMap (cached per ~1 km cell).
        $key = 'loc:rev:'.round($lat, 2).':'.round($lng, 2);
        $city = Cache::remember($key, now()->addDays(30), function () use ($lat, $lng) {
            try {
                $res = Http::timeout(8)->withHeaders(['User-Agent' => config('app.name', 'AutomobilIndia').' location ('.config('app.url').')', 'Accept-Language' => 'en'])
                    ->get('https://nominatim.openstreetmap.org/reverse', ['lat' => $lat, 'lon' => $lng, 'format' => 'jsonv2', 'zoom' => 10, 'addressdetails' => 1]);
                $a = $res->successful() ? ($res->json('address') ?? []) : [];
                $name = $a['city'] ?? $a['town'] ?? $a['municipality'] ?? $a['state_district'] ?? $a['county'] ?? null;
                return $name ? mb_substr(strip_tags((string) $name), 0, 60) : null;
            } catch (\Throwable $e) {
                Log::info('Reverse geocode failed: '.$e->getMessage());
                return null;
            }
        });

        if (! $city) return response()->json(['message' => 'Could not work out your city.'], 404);
        return response()->json(['city' => $city, 'lat' => round($lat, 4), 'lng' => round($lng, 4)]);
    }
}
