<?php

namespace App\Services;

use App\Models\Listing;
use App\Models\VehicleBodyType;
use App\Models\VehicleBrand;
use App\Models\VehicleModel;
use App\Support\Filters;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Answers "list / count / filter" questions straight from the website database (used listings and new vehicles),
 * so the assistant quotes real stock, prices and counts instead of guessing from text snippets.
 */
class SiteData
{
    private const UNITS = ['crore' => 1e7, 'crores' => 1e7, 'cr' => 1e7, 'lakh' => 1e5, 'lakhs' => 1e5, 'lac' => 1e5, 'lacs' => 1e5, 'l' => 1e5, 'k' => 1e3, 'thousand' => 1e3];
    private const NUM = '(\d+(?:\.\d+)?)';
    private const UNIT = '(crores?|cr|lakhs?|lacs?|l|k|thousand)';

    /** Understand the filters in a question. Returns [] when it has none. */
    public static function parse(string $q): array
    {
        $t = ' '.Str::lower(str_replace([',', '₹'], ['', ' '], $q)).' ';
        $f = [];

        // kilometres first, so "under 50000 km" is not read as a price
        if (preg_match('/(?:under|below|less than|within|upto|up to|max(?:imum)?)\s*'.self::NUM.'\s*(k)?\s*km\b/', $t, $m)) {
            $f['km_max'] = (int) ($m[1] * (! empty($m[2]) ? 1000 : 1));
            $t = str_replace($m[0], ' ', $t);
        }

        $u = fn ($n, $unit) => (int) round((float) $n * (self::UNITS[$unit] ?? 1));
        $p = '(?:rs\.?|inr)?\s*';
        if (preg_match('/'.self::NUM.'\s*'.self::UNIT.'?\s*(?:-|to|and|se)\s*'.$p.self::NUM.'\s*'.self::UNIT.'\b/', $t, $m) && in_array($m[4], ['lakh', 'lakhs', 'lac', 'lacs', 'l', 'cr', 'crore', 'crores', 'k', 'thousand'], true)) {
            $unit = $m[4]; $f['price_min'] = $u($m[1], $m[2] ?: $unit); $f['price_max'] = $u($m[3], $unit);
        } elseif (preg_match('/(?:around|approx(?:imately)?|about)\s*'.$p.self::NUM.'\s*'.self::UNIT.'\b/', $t, $m)) {
            $v = $u($m[1], $m[2]); $f['price_min'] = (int) ($v * 0.8); $f['price_max'] = (int) ($v * 1.2);
        } elseif (preg_match('/(?:above|over|more than|min(?:imum)?|starting from|at least)\s*'.$p.self::NUM.'\s*'.self::UNIT.'\b/', $t, $m)
            || preg_match('/\b'.self::NUM.'\s*'.self::UNIT.'\s*(?:se\s*)?(?:upar|zyada|jyada|above|plus|\+)/', $t, $m)) {
            $f['price_min'] = $u($m[1], $m[2]);
        } elseif (preg_match('/(?:under|below|within|upto|up to|less than|max(?:imum)?|budget(?: of| is)?)\s*'.$p.self::NUM.'\s*'.self::UNIT.'\b/', $t, $m)
            || preg_match('/\b'.self::NUM.'\s*(crores?|cr|lakhs?|lacs?)\b/', $t, $m)) {
            $f['price_max'] = $u($m[1], $m[2]);
        }

        foreach (['petrol' => 'petrol', 'diesel' => 'diesel', 'cng' => 'cng', 'electric' => 'electric', 'ev' => 'electric', 'hybrid' => 'hybrid'] as $w => $name) {
            if (preg_match('/\b'.$w.'\b/', $t)) $f['fuel'][] = $name;
        }
        if (isset($f['fuel'])) $f['fuel'] = array_values(array_unique($f['fuel']));

        if (preg_match('/\b(automatic|auto|amt|cvt|dct|dsg)\b/', $t)) $f['transmission'] = 'auto';
        elseif (preg_match('/\bmanual\b/', $t)) $f['transmission'] = 'manual';

        if (preg_match('/\b(used|second[- ]?hand|pre[- ]?owned|certified|purani|puraani|purana|puraana)\b/', $t)) $f['type'] = 'used';
        elseif (preg_match('/\b(new cars?|brand new|new models?|upcoming|launch(?:es|ed)?|new bikes?|new trucks?|nayi gaa?d(?:i|iyan)|nai gaa?d(?:i|iyan)|naya gaa?di)\b/', $t)) $f['type'] = 'new';

        if (preg_match('/\b(bikes?|motorcycles?|scooters?|two[- ]?wheelers?)\b/', $t)) $f['vehicle'] = 'bike';
        elseif (preg_match('/\btrucks?\b/', $t)) $f['vehicle'] = 'truck';

        if (preg_match('/\bupcoming\b/', $t)) $f['status'] = 'upcoming';
        if (preg_match('/\b(first|1st|single)[- ]owner\b/', $t)) $f['owner'] = '1';
        if (preg_match('/\b(after|since|newer than|from)\s*(20\d\d)\b/', $t, $m)) $f['year_min'] = (int) $m[2];
        elseif (preg_match('/\b(before|older than)\s*(20\d\d)\b/', $t, $m)) $f['year_max'] = (int) $m[2];
        elseif (preg_match('/\b(20[0-2]\d)\b/', $t, $m) && ! isset($f['price_max'], $f['price_min'])) $f['year'] = (int) $m[1];

        foreach (self::cities() as $city) {
            if (preg_match('/\b'.preg_quote(Str::lower($city), '/').'\b/', $t)) { $f['city'] = $city; break; }
        }
        foreach (self::brands() as $id => $name) {
            $short = Str::lower(Str::before($name, ' '));
            if (preg_match('/\b'.preg_quote(Str::lower($name), '/').'\b/', $t) || (mb_strlen($short) >= 4 && preg_match('/\b'.preg_quote($short, '/').'\b/', $t))) { $f['brand_id'] = $id; $f['brand'] = $name; break; }
        }
        foreach (self::bodies() as $id => $name) {
            $w = Str::lower($name);
            if (preg_match('/\b'.preg_quote($w, '/').'s?\b/', $t)) { $f['body_id'] = $id; $f['body'] = $name; break; }
        }

        if (preg_match('/\b(cheapest|lowest price|least expensive|most affordable|sasta|sasti)\b/', $t)) $f['sort'] = 'asc';
        elseif (preg_match('/\b(costliest|most expensive|highest price|priciest|luxury|premium)\b/', $t)) $f['sort'] = 'desc';
        if (preg_match('/\b(how many|number of|count of|total|kitni|kitne)\b/', $t)) $f['count'] = true;

        $f['_vehicle_word'] = (bool) preg_match('/\b(cars?|gaa?d(?:i|iyan|iyaan)|bikes?|trucks?|suvs?|sedans?|hatchbacks?|muvs?|vehicles?|models?|stock|listings?|inventory|variants?)\b/', $t);
        return $f;
    }

    /** Should this question be answered from a database lookup? */
    public static function wanted(array $f, bool $siteWords): bool
    {
        $kinds = collect(array_keys(array_diff_key($f, ['_vehicle_word' => 1, 'brand_id' => 1, 'body_id' => 1, 'vehicle' => 1])))->map(fn ($k) => in_array($k, ['price_min', 'price_max'], true) ? 'price' : (in_array($k, ['year', 'year_min', 'year_max'], true) ? 'year' : $k))->unique();
        $kinds = $kinds->merge(isset($f['brand_id']) ? ['brand'] : [])->merge(isset($f['body_id']) ? ['body'] : [])->unique();
        if ($kinds->isEmpty()) return false;
        if (! empty($f['count']) || isset($f['sort'])) return $f['_vehicle_word'] || $siteWords;
        return ($f['_vehicle_word'] || $siteWords) || $kinds->count() >= 2;
    }

    /**
     * Run the lookup. @return array{context:string, links:array, sources:array, found:int}
     */
    public static function lookup(array $f): array
    {
        $type = $f['type'] ?? null;
        $vehicle = $f['vehicle'] ?? 'car';
        $wantUsed = $type !== 'new' && $vehicle === 'car';
        $wantNew = $type !== 'used';

        $parts = []; $links = []; $sources = []; $items = []; $lines = []; $found = 0; $totals = ['used' => 0, 'new' => 0];
        $desc = self::describe($f);

        if ($wantUsed) {
            $q = self::usedQuery($f);
            $total = (clone $q)->count(); $found += $total; $totals['used'] = $total;
            $rows = self::order($q, 'price', $f)->limit(5)->get();
            $all = Listing::active()->count();
            $parts[] = $total
                ? "USED CARS IN OUR STOCK matching ($desc): $total".($total > 5 ? ' (showing the first 5)' : '').":\n".$rows->map(fn ($l) => '- '.self::usedLine($l))->implode("\n")
                : "USED CARS: none in our stock match ($desc). Total used cars in stock right now: $all.";
            foreach ($rows->take(3) as $l) { $items[] = AssistantMemory::item($l); $lines[] = self::usedLine($l); }
            foreach ($rows->take(3) as $l) { $links[] = ['title' => $l->title, 'url' => $l->url, 'image' => $l->image_url, 'type' => 'listing', 'k' => 'l', 'id' => $l->id, 'price' => $l->price_label]; $sources[] = ['type' => 'listing', 'title' => $l->title, 'url' => $l->url]; }
        }
        if ($wantNew) {
            $q = self::newQuery($f, $vehicle);
            $total = (clone $q)->count(); $found += $total; $totals['new'] = $total;
            $rows = self::order($q, 'price_min', $f)->limit(5)->get();
            $label = config("vehicles.$vehicle.label", 'Car');
            $parts[] = $total
                ? "NEW {$label} MODELS IN OUR CATALOG matching ($desc): $total".($total > 5 ? ' (showing the first 5)' : '').":\n".$rows->map(fn ($m) => '- '.self::newLine($m))->implode("\n")
                : "NEW {$label} MODELS: none in our catalog match ($desc).";
            foreach ($rows->take(3) as $m) { $items[] = AssistantMemory::item($m); $lines[] = self::newLine($m); }
            foreach ($rows->take(3) as $m) { $links[] = ['title' => $m->full_name, 'url' => $m->url, 'image' => $m->hero_url, 'type' => 'car', 'k' => 'c', 'id' => $m->id, 'price' => $m->price_label]; $sources[] = ['type' => 'car', 'title' => $m->full_name, 'url' => $m->url]; }
        }

        return ['context' => implode("\n\n", $parts), 'links' => $links, 'sources' => $sources, 'found' => $found, 'items' => $items, 'lines' => $lines, 'totals' => $totals, 'desc' => $desc];
    }

    private const NAME_SKIP = ['ki', 'ke', 'ka', 'ko', 'mein', 'me', 'for', 'the', 'and', 'price', 'news', 'review', 'reviews', 'car', 'cars', 'suv', 'electric', 'petrol', 'diesel', 'new', 'used', 'test', 'drive', 'details', 'detail', 'information', 'info', 'share', 'bata', 'batao', 'dikhao', 'kya', 'hai', 'baare', 'about', 'page', 'link', 'mujhe', 'muje', 'chahiye', 'under', 'lakh', 'launch', 'launched', 'upcoming', 'offers', 'emi', 'loan', 'finance', 'models', 'model', 'variant', 'variants', 'mileage', 'specs', 'features', 'colours', 'colors', 'wali', 'wala', 'iski', 'iske', 'iska', 'koi', 'kuch', 'aur', 'dena', 'chahie', 'chaiye', 'kitna', 'kitni', 'available', 'latest', 'stock', 'show', 'tell', 'give', 'want', 'need', 'looking', 'from'];

    /** Skeleton of a name for sound-alike matching: no vowels / y / w, doubles collapsed. "sierra", "syria", "सीरिया" -> "sr". */
    private static function skeleton(string $w): string
    {
        return preg_replace('/(.)\1+/', '$1', preg_replace('/[aeiouyw]/', '', Str::lower($w)));
    }

    /** Model-name words of a brand (new catalog + used stock), cached. */
    private static function modelTokens(int $brandId): array
    {
        return Cache::remember('asst:models:'.$brandId, 600, function () use ($brandId) {
            $names = VehicleModel::published()->where('brand_id', $brandId)->pluck('name')->merge(Listing::active()->where('brand_id', $brandId)->pluck('model'));
            return $names->flatMap(fn ($n) => preg_split('/[^\p{L}\p{N}]+/u', Str::lower((string) $n), -1, PREG_SPLIT_NO_EMPTY))
                ->filter(fn ($t) => mb_strlen($t) >= 3 && ! ctype_digit($t) && ! in_array($t, self::NAME_SKIP, true))->unique()->values()->all();
        });
    }

    /**
     * Voice / typing often garbles a model name ("Tata Syria", "टाटा सीरिया" for Sierra). The word right after a brand is matched
     * against that brand's own models by sound; only then is it corrected. @return array{0:string,1:array<string,string>} text, [wrote => meant]
     */
    public static function correctModelNames(string $norm): array
    {
        if (! preg_match_all('/[\p{L}\p{M}\p{N}]+/u', $norm, $mm, PREG_OFFSET_CAPTURE) || count($mm[0]) < 2) return [$norm, []];
        $tokens = $mm[0];
        $fixes = [];
        foreach (self::brands() as $id => $brand) {
            $key = Str::lower(Str::before($brand, ' '));
            foreach ($tokens as $i => [$tok]) {
                if (Str::lower($tok) !== $key) continue;
                $models = self::modelTokens((int) $id);
                if (! $models) continue;
                foreach ([$i + 1, $i + 2] as $j) {
                    if (! isset($tokens[$j])) continue;
                    [$cand, $off] = $tokens[$j];
                    $low = Str::lower($cand);
                    if (mb_strlen($cand) < 3 || in_array($low, self::NAME_SKIP, true) || in_array($low, $models, true) || isset($fixes[$cand])) continue;
                    $lat = HindiText::has($cand) ? HindiText::latinize($cand) : $low;
                    if (mb_strlen($lat) < 2) continue;
                    $best = null; $bestD = 99;
                    foreach ($models as $mt) {
                        $d = levenshtein($lat, $mt);
                        $same = self::skeleton($lat) === self::skeleton($mt) && $lat[0] === $mt[0];
                        $close = ! HindiText::has($cand) && $d <= (mb_strlen($mt) >= 6 ? 2 : 1);
                        if (($same || $close) && $d < $bestD) { $best = $mt; $bestD = $d; }
                    }
                    if ($best) { $fixes[$cand] = Str::title($best); }
                }
            }
        }
        foreach ($fixes as $wrote => $meant) $norm = preg_replace('/(?<![\p{L}\p{M}])'.preg_quote($wrote, '/').'(?![\p{L}\p{M}])/u', $meant, $norm);
        return [$norm, $fixes];
    }

    /**
     * The cars the visitor NAMED ("Sierra", "Sierra vs Venue", "Tata Nexon price"), straight from the database: the new catalog first,
     * otherwise our used stock. These drive the data, the cards and the "selected car" - not whatever a text search happens to return.
     * @return array<int, \App\Models\VehicleModel|\App\Models\Listing>
     */
    public static function mentionedCars(string $norm, int $max = 3): array
    {
        $set = Cache::remember('asst:alltokens', 600, fn () => VehicleModel::published()->pluck('name')->merge(Listing::active()->pluck('model'))
            ->flatMap(fn ($n) => preg_split('/[^\p{L}\p{N}]+/u', Str::lower((string) $n), -1, PREG_SPLIT_NO_EMPTY))
            ->filter(fn ($t) => mb_strlen($t) >= 3 && ! ctype_digit($t) && ! in_array($t, self::NAME_SKIP, true))->unique()->flip()->all());
        $low = Str::lower($norm);
        $brandPresent = collect(self::brands())->contains(fn ($b) => (bool) preg_match('/(?<![\p{L}\p{N}])'.preg_quote(Str::lower(Str::before($b, ' ')), '/').'(?![\p{L}\p{N}])/u', $low));
        $weak = ['city', 'range', 'space', 'star', 'point', 'sport', 'tour', 'one', 'zero', 'pro', 'max', 'plus'];     // everyday words that are also model names: only with a brand next to them
        $out = [];
        foreach (array_unique(preg_split('/[^\p{L}\p{N}]+/u', $low, -1, PREG_SPLIT_NO_EMPTY)) as $t) {
            if (! isset($set[$t]) || in_array($t, self::NAME_SKIP, true)) continue;
            if ((in_array($t, $weak, true) || mb_strlen($t) < 4) && ! $brandPresent) continue;
            $new = VehicleModel::published()->where('name', 'like', "%$t%")->orderByDesc('latest_event_at')->first();
            $found = $new ? [$new] : Listing::active()->where(fn ($q) => $q->where('model', 'like', "%$t%")->orWhere('title', 'like', "%$t%"))->latest('updated_at')->limit(2)->get()->all();
            foreach ($found as $car) $out[get_class($car).':'.$car->id] = $car;
            if (count($out) >= $max) break;
        }
        return array_slice(array_values($out), 0, $max);
    }

    /** One short factual block per car for the AI (new model: status, price, body, fuel, highlights; used car: its full line). */
    public static function carContext($car): string
    {
        if ($car instanceof Listing) return 'USED: '.self::usedLine($car);
        $used = Listing::active()->where('vehicle_model_id', $car->id)->orderBy('price')->get();
        $k = $car->toKnowledge();
        return 'NEW MODEL: '.$car->full_name.' ('.$car->status_label.') - from '.$car->price_label.($car->body_type ? ', '.$car->body_type : '').($car->fuel_types ? ', fuel '.implode('/', $car->fuel_types) : '')
            .'. '.Str::limit((string) ($k['content'] ?? ''), 900, '…').($used->isNotEmpty() ? ' | Used '.$car->name.' in our stock: '.$used->count().', from '.$used->first()->price_label : '');
    }

    private const CONTENT_STOP = ['news', 'latest', 'new', 'newest', 'recent', 'today', 'khabar', 'khabrein', 'samachar', 'article', 'articles', 'video', 'videos', 'review', 'reviews', 'show', 'tell', 'about', 'dikhao', 'batao', 'bataiye', 'dikha', 'cars', 'car', 'gaadi', 'gadi', 'mujhe', 'muje', 'kuch', 'koi', 'abhi', 'headlines', 'from', 'your', 'site', 'website', 'give', 'want', 'need', 'please', 'any', 'the', 'and', 'for', 'with', 'what', 'whats', 'have', 'you'];

    public static function newsIntent(string $t): bool
    {
        return (bool) preg_match('/\b(news|khabar|khabrein|samachar|articles?|headlines?|reviews?)\b/i', $t);
    }

    public static function videoIntent(string $t): bool
    {
        return (bool) preg_match('/\b(videos?|youtube|watch)\b/i', $t);
    }

    /**
     * Real news articles / videos from the database: the ones that match the visitor's words, otherwise the latest.
     * @return array{context:string, links:array, sources:array, found:int, lines:array, kind:string}
     */
    public static function contentLookup(string $kind, string $query): array
    {
        $words = collect(preg_split('/[^\p{L}\p{N}]+/u', Str::lower($query), -1, PREG_SPLIT_NO_EMPTY))->filter(fn ($w) => mb_strlen($w) >= 3 && ! in_array($w, self::CONTENT_STOP, true))->unique()->take(4)->values();
        $isNews = $kind === 'news';
        $base = fn () => $isNews ? \App\Models\Article::published() : \App\Models\Video::active();
        $dateCol = 'published_at';
        $rows = collect();
        if ($words->isNotEmpty()) {
            $rows = $base()->where(fn ($q) => $words->each(fn ($w) => $q->orWhere('title', 'like', "%$w%")))->latest($dateCol)->limit(3)->get();
        }
        $matched = $rows->isNotEmpty();
        $specific = $words->isNotEmpty();
        if (! $matched && ! $specific) $rows = $base()->latest($dateCol)->limit(3)->get();     // only a general "latest news" falls back to the newest

        $label = $isNews ? 'NEWS ARTICLES' : 'VIDEOS';
        $lines = $rows->map(fn ($r) => $r->title.($r->$dateCol ? ' ('.$r->$dateCol->format('d M Y').')' : ''))->all();
        $context = $rows->isEmpty()
            ? ($specific ? "$label: none about '".$words->implode(' ')."' on our site." : "$label: none published yet.")
            : "$label ".($matched ? 'matching the question' : 'latest').":\n".$rows->map(fn ($r) => '- '.$r->title.($r->$dateCol ? ' ('.$r->$dateCol->format('d M Y').')' : '').($isNews && $r->excerpt ? ': '.Str::limit(strip_tags($r->excerpt), 160, '…') : ''))->implode("\n");
        return [
            'context' => $context, 'kind' => $kind, 'found' => $rows->count(), 'lines' => $lines, 'matched' => $matched, 'specific' => $specific, 'words' => $words->implode(' '),
            'links' => $rows->map(fn ($r) => ['title' => $r->title, 'url' => $r->url, 'image' => $isNews ? $r->image_url : $r->thumbnail, 'type' => $isNews ? 'article' : 'video'])->all(),
            'sources' => $rows->map(fn ($r) => ['type' => $isNews ? 'article' : 'video', 'title' => $r->title, 'url' => $r->url])->all(),
        ];
    }

    private static function usedQuery(array $f)
    {
        $q = Listing::active();
        if (isset($f['price_max'])) $q->where('price', '>', 0)->where('price', '<=', $f['price_max']);
        if (isset($f['price_min'])) $q->where('price', '>=', $f['price_min']);
        if (isset($f['fuel'])) $q->whereHas('fuelMaster', fn ($x) => $x->where(fn ($w) => collect($f['fuel'])->each(fn ($n) => $w->orWhere('name', 'like', "%$n%"))));
        if (isset($f['transmission'])) $q->where(fn ($x) => $f['transmission'] === 'auto'
            ? $x->where('transmission', 'like', '%auto%')->orWhere('transmission', 'like', '%amt%')->orWhere('transmission', 'like', '%cvt%')->orWhere('transmission', 'like', '%dct%')
            : $x->where('transmission', 'like', '%manual%'));
        if (isset($f['city'])) $q->where('city', 'like', '%'.$f['city'].'%');
        if (isset($f['brand_id'])) $q->where('brand_id', $f['brand_id']);
        if (isset($f['body_id'])) $q->whereHas('vehicleModel', fn ($x) => $x->where('body_type_id', $f['body_id']));
        if (isset($f['year'])) $q->where('year', $f['year']);
        if (isset($f['year_min'])) $q->where('year', '>=', $f['year_min']);
        if (isset($f['year_max'])) $q->where('year', '<=', $f['year_max']);
        if (isset($f['km_max'])) $q->where('km_driven', '<=', $f['km_max']);
        if (isset($f['owner'])) $q->where(fn ($x) => $x->where('owner', 'like', '%1%')->orWhere('owner', 'like', '%first%'));
        return $q;
    }

    private static function newQuery(array $f, string $vehicle)
    {
        $q = VehicleModel::published()->where('vehicle_type', $vehicle);
        if (isset($f['price_max'])) $q->where('price_min', '>', 0)->where('price_min', '<=', $f['price_max']);
        if (isset($f['price_min'])) $q->where(fn ($x) => $x->where('price_min', '>=', $f['price_min'])->orWhere('price_max', '>=', $f['price_min']));
        if (isset($f['fuel'])) $q->whereHas('fuels', fn ($x) => $x->where(fn ($w) => collect($f['fuel'])->each(fn ($n) => $w->orWhere('name', 'like', "%$n%"))));
        if (isset($f['brand_id'])) $q->where('brand_id', $f['brand_id']);
        if (isset($f['body_id'])) $q->where('body_type_id', $f['body_id']);
        if (isset($f['status'])) $q->where('status', $f['status']);
        return $q;
    }

    private static function order($q, string $priceCol, array $f)
    {
        $hasPrice = isset($f['price_min']) || isset($f['price_max']);
        return match (true) {
            ($f['sort'] ?? null) === 'desc' => $q->orderByDesc($priceCol),
            ($f['sort'] ?? null) === 'asc' || $hasPrice => $q->where($priceCol, '>', 0)->orderBy($priceCol),
            default => $q->latest('updated_at'),
        };
    }

    private static function usedLine(Listing $l): string
    {
        $bits = array_filter([$l->price_label, $l->km_driven ? number_format($l->km_driven).' km' : null, $l->fuel, $l->transmission, $l->owner ? $l->owner.' owner' : null, $l->city]);
        return trim(($l->year ? $l->year.' ' : '').$l->title).' - '.implode(', ', $bits);
    }

    private static function newLine(VehicleModel $m): string
    {
        $bits = array_filter(['from '.$m->price_label, $m->status_label, $m->body_type, implode('/', $m->fuel_types)]);
        return $m->full_name.' - '.implode(', ', $bits);
    }

    public static function describe(array $f): string
    {
        $fmt = fn ($v) => $v >= 10000000 ? rtrim(rtrim(number_format($v / 1e7, 2), '0'), '.').' Cr' : rtrim(rtrim(number_format($v / 1e5, 2), '0'), '.').' Lakh';
        $b = array_filter([
            $f['brand'] ?? null, $f['body'] ?? null, isset($f['fuel']) ? implode('/', $f['fuel']) : null,
            isset($f['transmission']) ? $f['transmission'] : null, isset($f['city']) ? 'in '.$f['city'] : null,
            isset($f['price_min'], $f['price_max']) ? 'Rs '.$fmt($f['price_min']).' to Rs '.$fmt($f['price_max']) : (isset($f['price_max']) ? 'under Rs '.$fmt($f['price_max']) : (isset($f['price_min']) ? 'above Rs '.$fmt($f['price_min']) : null)),
            isset($f['year']) ? 'year '.$f['year'] : null, isset($f['year_min']) ? 'from '.$f['year_min'] : null, isset($f['year_max']) ? 'before '.$f['year_max'] : null,
            isset($f['km_max']) ? 'under '.number_format($f['km_max']).' km' : null, isset($f['owner']) ? 'first owner' : null,
        ]);
        return $b ? implode(', ', $b) : 'no filters';
    }

    /** Cities with used stock, biggest first (for tap-to-answer chips). */
    public static function cityOptions(int $n = 6): array
    {
        return Cache::remember('asst:cityopts:'.$n, 300, fn () => Listing::active()->whereNotNull('city')->where('city', '!=', '')->selectRaw('city, count(*) c')->groupBy('city')->orderByDesc('c')->limit($n)->pluck('city')->all());
    }

    /** Budget chips for the used/new search: [label, text the parser understands]. */
    public static function budgetOptions(string $vehicle = 'car'): array
    {
        $l = fn ($v) => rtrim(rtrim(number_format($v / 1e5, 2), '0'), '.');
        return collect(Filters::budgets($vehicle))->map(function ($b) use ($l) {
            [$label, $lo, $hi] = $b;
            $text = ! $lo ? 'under '.$l($hi).' lakh' : ($hi ? 'between '.$l($lo).' and '.$l($hi).' lakh' : 'above '.$l($lo).' lakh');
            return ['label' => $label, 'text' => 'budget '.$text];
        })->values()->all();
    }

    private static function cities(): array
    {
        return Cache::remember('asst:cities', 600, fn () => collect(Filters::CITIES)->merge(Listing::active()->whereNotNull('city')->distinct()->limit(200)->pluck('city'))->filter()->unique()->values()->all());
    }

    private static function brands(): array
    {
        return Cache::remember('asst:brands', 600, fn () => VehicleBrand::where('is_active', true)->pluck('name', 'id')->all());
    }

    private static function bodies(): array
    {
        return Cache::remember('asst:bodies', 600, fn () => VehicleBodyType::where('is_active', true)->pluck('name', 'id')->all());
    }

    /**
     * The real, filtered listing page for these filters. It must show the SAME cars the assistant counted, so exact price,
     * year, km, owner and body type travel in the URL too (the pages apply them).
     */
    public static function browseUrl(array $f): string
    {
        $vehicle = $f['vehicle'] ?? 'car';
        $price = array_filter(['price_min' => $f['price_min'] ?? null, 'price_max' => $f['price_max'] ?? null]);

        if (($f['type'] ?? null) !== 'new' && $vehicle === 'car') {                      // used cars: /cars?brand[]=&fuel[]=&city[]=&transmission[]=&price_max=...
            $q = [];
            if (! empty($f['brand'])) $q['brand'] = [$f['brand']];
            if (! empty($f['fuel'])) $q['fuel'] = \App\Models\VehicleFuel::where(fn ($w) => collect($f['fuel'])->each(fn ($n) => $w->orWhere('name', 'like', "%$n%")))->pluck('name')->all();
            if (! empty($f['city'])) $q['city'] = [$f['city']];
            if (! empty($f['transmission'])) {
                $vals = Listing::active()->whereNotNull('transmission')->distinct()->pluck('transmission');
                $q['transmission'] = $vals->filter(fn ($v) => $f['transmission'] === 'auto' ? preg_match('/auto|amt|cvt|dct/i', $v) : preg_match('/manual/i', $v))->values()->all();
            }
            if (! empty($f['body_id'])) $q['body_type'] = [$f['body_id']];
            foreach (['year', 'year_min', 'year_max', 'km_max', 'owner'] as $k) if (isset($f[$k])) $q[$k] = $f[$k];
            $q += $price;
            if (($f['sort'] ?? null) === 'desc') $q['sort'] = 'price_desc';
            elseif (($f['sort'] ?? null) === 'asc' || isset($f['price_max'])) $q['sort'] = 'price_asc';
            return route('cars.index', array_filter($q, fn ($v) => $v !== [] && $v !== null));
        }

        $route = ['car' => 'newcars.index', 'bike' => 'newbikes.index', 'truck' => 'newtrucks.index'][$vehicle] ?? 'newcars.index';
        $q = [];                                                                          // new vehicles: /new-cars?brand[]=id&body_type[]=id&fuel[]=id&price_max=
        if (! empty($f['brand_id'])) $q['brand'] = [$f['brand_id']];
        if (! empty($f['body_id'])) $q['body_type'] = [$f['body_id']];
        if (! empty($f['fuel'])) $q['fuel'] = \App\Models\VehicleFuel::where(fn ($w) => collect($f['fuel'])->each(fn ($n) => $w->orWhere('name', 'like', "%$n%")))->pluck('id')->all();
        $q += $price;
        if (($f['status'] ?? null)) $q['status'] = $f['status'];
        return route($route, array_filter($q, fn ($v) => $v !== []));
    }
}
