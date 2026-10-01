<?php

namespace App\Services;

use App\Models\Article;
use App\Models\VehicleModel;
use App\Models\Listing;
use Illuminate\Support\Str;

/**
 * Keeps the new-car catalog alive. When a news story reports a launch, facelift, price change or spec update,
 * the matching car page is created/updated: facts merged, images refreshed, overview rewritten, listings linked.
 * Fields an editor has "locked" in the admin are never touched.
 */
class CarUpdater
{
    private const SIGNIFICANT = ['launch', 'facelift', 'price_update', 'spec_update', 'teaser'];
    private const IMAGE_EVENTS = ['launch', 'facelift', 'spec_update', 'teaser'];

    private const BRANDS = [
        'maruti' => 'Maruti Suzuki', 'maruti suzuki' => 'Maruti Suzuki', 'suzuki' => 'Maruti Suzuki', 'nexa' => 'Maruti Suzuki',
        'mercedes' => 'Mercedes-Benz', 'mercedes-benz' => 'Mercedes-Benz', 'mercedes benz' => 'Mercedes-Benz', 'vw' => 'Volkswagen', 'volkswagen' => 'Volkswagen',
        'mg' => 'MG', 'mg motor' => 'MG', 'jsw mg' => 'MG', 'land rover' => 'Land Rover', 'range rover' => 'Land Rover', 'citroën' => 'Citroen', 'skoda' => 'Skoda', 'škoda' => 'Skoda',
        'tata' => 'Tata', 'tata motors' => 'Tata', 'mahindra' => 'Mahindra', 'hyundai' => 'Hyundai', 'kia' => 'Kia', 'toyota' => 'Toyota', 'honda' => 'Honda',
        'renault' => 'Renault', 'nissan' => 'Nissan', 'jeep' => 'Jeep', 'byd' => 'BYD', 'bmw' => 'BMW', 'audi' => 'Audi', 'volvo' => 'Volvo', 'lexus' => 'Lexus',
        'porsche' => 'Porsche', 'jaguar' => 'Jaguar', 'mini' => 'Mini', 'force' => 'Force', 'isuzu' => 'Isuzu', 'ford' => 'Ford', 'citroen' => 'Citroen', 'tesla' => 'Tesla',
    ];

    /** Every brand name / alias we recognise (used to spot the brand inside a listing title). */
    public static function brandNames(): array
    {
        return array_values(array_unique(array_merge(array_values(self::BRANDS), array_keys(self::BRANDS))));
    }

    public static function canonicalBrand(string $brand): string
    {
        $k = mb_strtolower(trim($brand));
        return self::BRANDS[$k] ?? Str::title(trim($brand));
    }

    /** @param array $car the "car" object from the writer;  @param string[] $imageUrls images from the source pages */
    public static function apply(Article $article, array $car, array $imageUrls = [], array $imageAlts = [], bool $allowCreate = true): ?VehicleModel
    {
        $brand = self::canonicalBrand((string) ($car['brand'] ?? ''));
        $name = trim(preg_replace('/^'.preg_quote($brand, '/').'\s+/i', '', (string) ($car['name'] ?? '')));
        $event = in_array($car['event'] ?? '', ['launch', 'facelift', 'price_update', 'spec_update', 'teaser', 'review', 'other'], true) ? $car['event'] : 'other';
        if ($brand === '' || mb_strlen($name) < 2) return null;

        $slug = Str::slug("$brand $name");
        $model = VehicleModel::where('slug', $slug)->first();

        // Never create a catalog page from a review/other story; only real events do.
        if (! $model && ! in_array($event, self::SIGNIFICANT, true)) return null;
        // News runs can be told not to add cars to the catalog at all (Settings -> News automation); existing cars still update.
        if (! $model && ! $allowCreate) return null;

        $isNew = ! $model;
        $brandMaster = \App\Models\VehicleBrand::firstOrCreate(['name' => $brand], ['slug' => Str::slug($brand)]);
        $model ??= new VehicleModel(['brand_id' => $brandMaster->id, 'name' => $name, 'slug' => $slug, 'status' => 'launched', 'is_published' => true]);
        $significant = in_array($event, self::SIGNIFICANT, true);

        $set = function (string $field, mixed $value) use ($model) {
            if ($value === null || $value === '' || $value === [] || $model->isLocked($field)) return;
            $model->{$field} = $value;
        };

        if (! empty($car['body_type'])) { $body = \App\Models\VehicleBodyType::firstOrCreate(['name' => Str::title($car['body_type'])], ['slug' => Str::slug($car['body_type'])]); $set('body_type_id', $body->id); }
        $fuelIds = []; foreach (array_unique(array_map('ucfirst', (array) ($car['fuel_types'] ?? []))) as $fuelName) { $fuel = \App\Models\VehicleFuel::firstOrCreate(['name' => $fuelName], ['slug' => Str::slug($fuelName)]); $fuelIds[] = $fuel->id; }
        if (! empty($car['price_min_lakh'])) {
            $set('price_min', (int) round((float) $car['price_min_lakh'] * 100000));
            $set('price_max', ! empty($car['price_max_lakh']) ? (int) round((float) $car['price_max_lakh'] * 100000) : null);
        }
        if (! empty($car['launch_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $car['launch_date']) && ($event === 'launch' || $isNew || ! $model->launch_date)) $set('launch_date', $car['launch_date']);
        if (! empty($car['specs']) && is_array($car['specs']) && ! $model->isLocked('specs')) $model->specs = array_merge($model->specs ?? [], array_slice(array_map('strval', $car['specs']), 0, 24, true));

        if (! $model->isLocked('status')) {
            $model->status = match (true) {
                $event === 'facelift' => 'facelift',
                $event === 'launch' => 'launched',
                $event === 'teaser' && $isNew => 'upcoming',
                $isNew => in_array($car['status'] ?? '', ['upcoming', 'launched', 'facelift'], true) ? $car['status'] : 'launched',
                default => $model->status,
            };
        }
        if ($significant) { $model->latest_event = $event; $model->latest_event_at = now(); $model->needs_refresh = true; }
        $model->save();
        if (isset($fuelIds)) $model->fuels()->sync($fuelIds);

        $article->vehicleModels()->syncWithoutDetaching([$model->id => ['event' => $event]]);

        if (in_array($event, self::IMAGE_EVENTS, true) || ! $model->gallery) self::refreshImages($model, self::relevantImages($model, $imageUrls, $imageAlts), $event === 'facelift' && ! $isNew);
        self::linkListings($model);

        if ($model->needs_refresh) {
            try { self::refreshContent($model); } catch (\Throwable) {}
        }
        return $model->fresh();
    }

    /**
     * Article pages carry sidebar/related-story pictures. Keep the cover image (og:image) and only those in-article
     * images whose alt text or file name mentions the model, so a car page never shows another car.
     */
    public static function relevantImages(VehicleModel $model, array $urls, array $alts): array
    {
        $norm = fn (string $s) => preg_replace('/[^a-z0-9]+/', '', mb_strtolower($s));
        $needle = $norm($model->name);
        $out = [];
        foreach (array_values($urls) as $i => $u) {
            if ($i === 0 || ($needle !== '' && (str_contains($norm(($alts[$u] ?? '').' '.basename(parse_url($u, PHP_URL_PATH) ?? '')), $needle)))) $out[] = $u;
        }
        return $out;
    }

    /** Download new images (deduplicated by content hash). On a facelift the previous gallery is archived, not deleted. */
    public static function refreshImages(VehicleModel $model, array $urls, bool $facelift = false): int
    {
        if ($model->isLocked('gallery')) return 0;
        $hashes = $model->image_hashes ?? []; $added = [];
        foreach (array_slice(array_unique($urls), 0, 8) as $u) {
            if (count($added) >= 6) break;
            $r = ImageStore::fetch($u, 'cars/'.$model->slug, $hashes);
            if (! $r) continue;
            $hashes[] = $r['hash']; $added[] = $r['path'];
        }
        if (! $added) return 0;

        if ($facelift && $model->gallery) {
            $model->archive_gallery = array_slice(array_merge($model->gallery, $model->archive_gallery ?? []), 0, 12);
            $model->gallery = $added;
        } else {
            $model->gallery = array_slice(array_merge($added, $model->gallery ?? []), 0, 12);
        }
        $model->image_hashes = array_slice($hashes, -60);
        if (! $model->isLocked('hero_image')) $model->hero_image = $model->gallery[0];
        $model->save();
        return count($added);
    }

    /** Regenerate the evergreen overview/highlights/FAQ/SEO text from everything we know about the car. */
    public static function refreshContent(VehicleModel $model, bool $force = false): bool
    {
        if (! AiClient::configured()) return false;
        if (! $force && $model->refreshed_at && $model->refreshed_at->gt(now()->subHours(6)) && ! $model->needs_refresh) return false;

        $stories = $model->articles()->published()->latest('published_at')->limit(6)->get()
            ->map(fn ($a) => '- ['.$a->pivot->event.'] '.$a->title.': '.Str::limit(implode(' ', $a->tldr ?? []) ?: $a->excerpt, 300))->implode("\n");
        $facts = json_encode(array_filter([
            'brand' => $model->brand, 'model' => $model->name, 'status' => $model->status, 'latest_event' => $model->latest_event,
            'price_ex_showroom' => $model->price_min ? $model->price_label : null, 'body_type' => $model->body_type, 'fuel_types' => $model->fuel_types,
            'launch_date' => $model->launch_date?->toDateString(), 'specs' => $model->specs,
        ]), JSON_UNESCAPED_UNICODE);

        $facelift = $model->status === 'facelift' ? 'This is a FACELIFTED model: clearly explain what changed versus the pre-facelift car (only if the stories say so). ' : '';
        $data = AiClient::json([
            ['role' => 'system', 'content' => "You write the model page for an Indian car site. {$facelift}Use ONLY the facts and stories given; never invent specs, prices or features - say 'yet to be confirmed' when unknown. Write in natural, specific, human prose for Indian buyers (rivals, running costs, who it suits), with no stock phrases. Reply with ONE JSON object: {\"tagline\": max 120 chars, \"overview_html\": 300-450 words using only <p>,<h2>,<ul>,<li>,<strong>, opening with the key facts in two sentences, \"highlights\": array of 5 short strings, \"faq\": array of 4 {q,a} as real searchers would ask, \"meta_title\": max 60 chars, \"meta_description\": max 155 chars}"],
            ['role' => 'user', 'content' => "FACTS: $facts\n\nRECENT COVERAGE:\n$stories"],
        ], ['temperature' => 0.7, 'max_tokens' => 2400, 'timeout' => 120]);
        if (! $data || empty($data['overview_html'])) return false;

        $put = function (string $f, mixed $v) use ($model) { if ($v && ! $model->isLocked($f)) $model->{$f} = $v; };
        $put('tagline', Str::limit((string) ($data['tagline'] ?? ''), 290, ''));
        $put('overview', Editorial::cleanHtml($data['overview_html']));
        $put('highlights', array_slice(array_map('strval', (array) ($data['highlights'] ?? [])), 0, 6));
        $put('faq', array_slice(array_values(array_filter((array) ($data['faq'] ?? []), fn ($f) => ! empty($f['q']) && ! empty($f['a']))), 0, 6));
        $put('meta_title', Str::limit((string) ($data['meta_title'] ?? ''), 70, ''));
        $put('meta_description', Str::limit((string) ($data['meta_description'] ?? ''), 160, ''));
        $model->needs_refresh = false; $model->refreshed_at = now();
        $model->save();
        return true;
    }

    /** Attach used listings that are this model (so listing pages can show "facelift launched" banners). */
    public static function linkListings(VehicleModel $model): int
    {
        $q = Listing::whereNull('vehicle_model_id')->where('brand_id', $model->brand_id)
            ->where(fn ($w) => $w->where('model', 'like', $model->name.'%')->orWhere('title', 'like', '%'.$model->name.'%'));
        return $q->update(['vehicle_model_id' => $model->id]);
    }

    /** Best-effort match for a scraped listing. */
    public static function matchListing(?string $brand, string $title): ?int
    {
        if (! $brand) return null;
        $brandId = \App\Models\VehicleBrand::whereRaw('lower(name)=?', [strtolower(self::canonicalBrand($brand))])->value('id');
        foreach (VehicleModel::where('brand_id', $brandId)->get() as $m) {
            if (stripos($title, $m->name) !== false) return $m->id;
        }
        return null;
    }
}
