<?php

namespace App\Services;

use App\Models\VehicleBodyType;
use App\Models\VehicleBrand;
use App\Models\VehicleModel;
use App\Services\Crawl\LinkFinder;
use Illuminate\Support\Str;

/**
 * Builds a catalog model (car, bike or truck) from any product / launch page.
 * The facts are read from the page and rewritten in our own words; the link is not stored anywhere on the model,
 * so nothing on the public page points back to the source.
 */
class VehicleImporter
{
    private const MIN_WORDS = 60;

    /** @return VehicleModel|string the created model, or a human-readable reason it could not be imported */
    public function importUrl(string $url, string $type = 'car', bool $publish = false): VehicleModel|string
    {
        $v = config("vehicles.$type");
        if (! $v) return 'Unknown vehicle type.';
        $url = LinkFinder::normalize(trim($url));
        if (! $url) return 'That is not a valid link.';
        if (! AiClient::configured()) return 'AI provider is not configured (Settings → AI).';

        $page = PageFetcher::article($url);
        if (! $page) return 'Could not open that page (blocked, offline or not HTML).';
        if ($page['words'] < self::MIN_WORDS && ! $page['description']) return 'No readable details found on that page ('.$page['words'].' words).';

        $label = strtolower($v['label']);
        $bodies = VehicleBodyType::ofType($type)->where('is_active', true)->pluck('name')->implode(', ');
        $data = AiClient::json([
            ['role' => 'system', 'content' => "You build the model page for an Indian {$label} catalog from a web page. Use ONLY facts on the page; never invent specs or prices (leave them out when unknown). "
                ."Write the text in your own words as original copy. Never mention the source website, its brand, URLs, 'according to' or 'as per'. "
                .'Reply with ONE JSON object only: {"brand":"","name":"model name without the brand","status":"upcoming|launched|facelift","body_type":"one of: '.$bodies.'",'
                .'"fuel_types":["Petrol"],"price_min_lakh":0,"price_max_lakh":0,"launch_date":"YYYY-MM-DD or empty","specs":{"Engine":"","Power":"","Mileage":""},'
                .'"tagline":"max 140 chars","overview_html":"250-400 words using only <p>, <h2>, <ul>, <li>, <strong>","highlights":["up to 6 short points"],'
                .'"faq":[{"q":"","a":""}],"meta_title":"max 60 chars","meta_description":"max 155 chars"}. Prices are ex-showroom in Indian lakh rupees (1.2 = ₹1.2 lakh).'],
            ['role' => 'user', 'content' => "PAGE TITLE: {$page['title']}\nDESCRIPTION: {$page['description']}\n\nPAGE TEXT:\n".Str::limit($page['text'], 9000, '')],
        ], ['temperature' => 0.4, 'max_tokens' => 2600, 'timeout' => 120]);
        if (! $data) return 'The AI could not read details from that page. '.(AiClient::lastError() ?: 'Try again.');

        $brandName = CarUpdater::canonicalBrand((string) ($data['brand'] ?? ''));
        $name = trim(preg_replace('/^'.preg_quote($brandName, '/').'\s+/i', '', (string) ($data['name'] ?? '')));
        if ($brandName === '' || mb_strlen($name) < 2) return 'Could not tell which model this page is about.';

        $slug = Str::slug("$brandName $name");
        if ($existing = VehicleModel::where('slug', $slug)->first()) return "Already in the catalog: {$existing->full_name}.";

        $brand = VehicleBrand::firstOrCreate(['name' => $brandName], ['slug' => Str::slug($brandName)]);
        $body = VehicleBodyType::ofType($type)->whereRaw('lower(name) = ?', [mb_strtolower(trim((string) ($data['body_type'] ?? '')))])->first();

        $model = new VehicleModel([
            'vehicle_type' => $type, 'brand_id' => $brand->id, 'name' => $name, 'slug' => $slug, 'body_type_id' => $body?->id,
            'status' => in_array($data['status'] ?? '', ['upcoming', 'launched', 'facelift'], true) ? $data['status'] : 'launched',
            'price_min' => ! empty($data['price_min_lakh']) ? (int) round((float) $data['price_min_lakh'] * 100000) : null,
            'price_max' => ! empty($data['price_max_lakh']) ? (int) round((float) $data['price_max_lakh'] * 100000) : null,
            'launch_date' => ! empty($data['launch_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['launch_date']) ? $data['launch_date'] : null,
            'specs' => array_filter(array_map('strval', array_slice((array) ($data['specs'] ?? []), 0, 24, true))) ?: null,
            'tagline' => Str::limit((string) ($data['tagline'] ?? ''), 290, '') ?: null,
            'overview' => ! empty($data['overview_html']) ? Editorial::cleanHtml($data['overview_html']) : null,
            'highlights' => array_slice(array_map('strval', (array) ($data['highlights'] ?? [])), 0, 6) ?: null,
            'faq' => array_slice(array_values(array_filter((array) ($data['faq'] ?? []), fn ($f) => ! empty($f['q']) && ! empty($f['a']))), 0, 6) ?: null,
            'meta_title' => Str::limit((string) ($data['meta_title'] ?? ''), 70, '') ?: null,
            'meta_description' => Str::limit((string) ($data['meta_description'] ?? ''), 160, '') ?: null,
            'latest_event' => 'launch', 'latest_event_at' => now(), 'refreshed_at' => now(), 'is_published' => $publish,
        ]);
        $model->save();

        $fuelIds = collect((array) ($data['fuel_types'] ?? []))->map(fn ($f) => CarMasters::fuelId((string) $f))->filter()->unique()->values()->all();
        $model->fuels()->sync($fuelIds);

        CarUpdater::refreshImages($model, array_slice($page['images'], 0, 8));
        CarUpdater::linkListings($model);

        return $model->fresh();
    }
}
