<?php

namespace App\Services;

use App\Models\Article;
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

        $parts = []; $links = []; $sources = []; $items = []; $found = 0; $totals = ['used' => 0, 'new' => 0];
        $desc = self::describe($f);

        if ($wantUsed) {
            $q = self::usedQuery($f);
            $total = (clone $q)->count(); $found += $total; $totals['used'] = $total;
            $rows = self::order($q, 'price', $f)->limit(5)->get();
            $all = Listing::active()->count();
            $parts[] = $total
                ? "USED CARS IN OUR STOCK matching ($desc): $total".($total > 5 ? ' (showing the first 5)' : '').":\n".$rows->map(fn ($l) => '- '.self::usedLine($l))->implode("\n")
                : "USED CARS: none in our stock match ($desc). Total used cars in stock right now: $all.";
            foreach ($rows as $l) { $items[] = AssistantMemory::item($l); }
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
            foreach ($rows as $m) { $items[] = AssistantMemory::item($m); }
            foreach ($rows->take(3) as $m) { $links[] = ['title' => $m->full_name, 'url' => $m->url, 'image' => $m->hero_url, 'type' => 'car', 'k' => 'c', 'id' => $m->id, 'price' => $m->price_label]; $sources[] = ['type' => 'car', 'title' => $m->full_name, 'url' => $m->url]; }
        }

        return ['context' => implode("\n\n", $parts), 'links' => array_slice($links, 0, 3), 'sources' => $sources, 'found' => $found, 'items' => array_slice($items, 0, 5), 'totals' => $totals];
    }

    /**
     * Everything we know about one (or a few) named models: price range, specs, highlights, FAQ, plus how many used ones we have.
     * The named model comes FIRST and alone, never "5 recent cars of the brand". Same shape as lookup().
     *
     * @param  array<int, array{id:int}>  $hits  from QueryIntent::models()
     */
    public static function modelInfo(array $hits): ?array
    {
        $ids = collect($hits)->pluck('id')->all();
        $models = VehicleModel::published()->with(['brandMaster', 'fuels', 'bodyType'])->whereIn('id', $ids)->get()->sortBy(fn ($m) => array_search($m->id, $ids))->values();
        if ($models->isEmpty()) return null;

        $parts = []; $links = []; $sources = []; $items = []; $brief = [];
        foreach ($models as $i => $m) {
            $price = $m->price_min ? 'from ₹ '.self::money($m->price_min).($m->price_max > $m->price_min ? ' to ₹ '.self::money($m->price_max) : '') : $m->price_label;
            $used = Listing::active()->where('vehicle_model_id', $m->id);
            $usedN = (clone $used)->count();
            $usedLine = $usedN ? "\nUsed {$m->full_name} in our stock: $usedN (e.g. ".$used->latest('updated_at')->limit(3)->get()->map(fn ($l) => self::usedLine($l))->implode('; ').').' : '';
            $body = Str::limit($m->toKnowledge()['content'] ?? '', $i === 0 ? 2200 : 500, '…');
            $parts[] = "MODEL {$m->full_name} (new ".($m->vehicle_type ?: 'car').", status {$m->status_label}): price $price.\n$body$usedLine";
            $hl = collect($m->highlights ?? [])->map(fn ($h) => is_array($h) ? ($h['text'] ?? $h['title'] ?? '') : $h)->filter()->take(3)->implode('; ');
            $brief[] = $m->full_name.': '.$price.', '.strtolower($m->status_label).($m->body_type ? ', '.$m->body_type : '').($m->fuel_types ? ', '.implode('/', $m->fuel_types) : '').($hl ? '. '.Str::limit($hl, 220, '…') : '.');
            $items[] = AssistantMemory::item($m);
            $links[] = ['title' => $m->full_name, 'url' => $m->url, 'image' => $m->hero_url, 'type' => 'car', 'k' => 'c', 'id' => $m->id, 'price' => $m->price_label];
            $sources[] = ['type' => 'car', 'title' => $m->full_name, 'url' => $m->url];
        }
        $ctx = $models->count() > 1 ? implode("\n\n", $parts) : $parts[0];
        if ($models->count() > 1) {                                                           // two named cars: our own comparison, when the admin wrote one
            $cmp = \App\Models\CarComparison::live()->forPair($models[0]->id, $models[1]->id)->with('winner')->first();
            if ($cmp) {
                $ctx .= "\n\nOUR OWN COMPARISON ({$cmp->heading}): ".Str::limit(trim(strip_tags(($cmp->intro ?? '').' '.($cmp->verdict ?? ''))), 1500, '…').($cmp->winner ? ' Our pick: '.$cmp->winner->full_name.'.' : '');
                $brief[] = 'Our verdict: '.Str::limit(trim(strip_tags(($cmp->verdict ?: $cmp->intro) ?? '')), 300, '…').($cmp->winner ? ' Our pick: '.$cmp->winner->full_name.'.' : '');
                array_unshift($links, ['title' => $cmp->heading, 'url' => $cmp->url, 'image' => $models[0]->hero_url, 'type' => 'comparison']);
                array_unshift($sources, ['type' => 'comparison', 'title' => $cmp->heading, 'url' => $cmp->url]);
            } else {
                $ctx = "The visitor named several models (they may mean any; if they ask to compare, compare them using these facts plus well-known general knowledge):\n\n".$ctx;
            }
        }
        return ['context' => $ctx, 'links' => $links, 'sources' => $sources, 'found' => count($items), 'items' => $items, 'totals' => ['used' => 0, 'new' => count($items)], 'ids' => $models->pluck('id')->all(), 'brief' => implode("
", $brief)];
    }

    /**
     * Newest articles first, optionally about one topic (a model or brand). With a topic and no match it says so, then shows the newest.
     * Same shape as lookup().
     */
    public static function news(?string $topic = null, int $n = 5): array
    {
        $base = Article::published()->orderByDesc('published_at');
        $rows = $topic ? (clone $base)->where(fn ($q) => $q->where('title', 'like', "%$topic%")->orWhere('excerpt', 'like', "%$topic%"))->limit($n)->get() : collect();
        $note = '';
        if ($topic && $rows->isEmpty()) { $note = "No news articles on our site match \"$topic\" - say so in one short line, share what you generally know about it if useful, and offer the newest ones below.\n"; }
        if ($rows->isEmpty()) $rows = $base->limit($n)->get();

        $ctx = $rows->isEmpty()
            ? 'NEWS: there are no published articles on our site yet.'
            : $note.'LATEST NEWS ON OUR SITE (newest first):'."\n".$rows->map(fn ($a) => '- '.optional($a->published_at)->format('d M Y').': '.$a->title.' - '.Str::limit(trim(strip_tags((string) $a->excerpt)), 160, '…'))->implode("\n");
        $links = $rows->take(3)->map(fn ($a) => ['title' => $a->title, 'url' => $a->url, 'image' => $a->image_url, 'type' => 'article'])->all();
        $sources = $rows->take(3)->map(fn ($a) => ['type' => 'article', 'title' => $a->title, 'url' => $a->url])->all();
        $brief = $rows->take(5)->map(fn ($a) => '- '.$a->title.' ('.optional($a->published_at)->format('d M').')')->implode("
");
        return ['context' => $ctx, 'brief' => ($note ? 'Nothing on our site about "'.$topic.'". ' : '')."Latest news:
".$brief, 'links' => $links, 'sources' => $sources, 'found' => $rows->count(), 'items' => [], 'totals' => ['used' => 0, 'new' => 0]];
    }

    private static function money(int|float $v): string
    {
        return $v >= 10000000 ? rtrim(rtrim(number_format($v / 1e7, 2), '0'), '.').' Cr' : rtrim(rtrim(number_format($v / 1e5, 2), '0'), '.').' Lakh';
    }

    /** Lowercase brand words (first word of each brand name) - used to avoid mistaking "tata"/"maruti" for a car name. */
    public static function brandWords(): array
    {
        return collect(self::brands())->flatMap(fn ($n) => [Str::lower(Str::before($n, ' ')), Str::lower($n)])->unique()->values()->all();
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
        if (isset($f['model_ids'])) $q->whereIn('vehicle_model_id', $f['model_ids']);
        if (isset($f['model_like'])) $q->where('model', 'like', '%'.$f['model_like'].'%');
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
        if (isset($f['model_ids'])) $q->whereIn('id', $f['model_ids']);
        if (isset($f['model_like'])) $q->where('name', 'like', '%'.$f['model_like'].'%');
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

    /** The real, filtered listing page for these filters (used cars, or new cars/bikes/trucks). */
    public static function browseUrl(array $f): string
    {
        $vehicle = $f['vehicle'] ?? 'car';
        $bands = function (string $v) use ($f) {
            $lo = $f['price_min'] ?? 0; $hi = $f['price_max'] ?? null;
            return collect(Filters::budgets($v))->filter(fn ($b, $k) => ! (isset($f['price_min']) || isset($f['price_max'])) ? false : (($b[2] === null || $b[2] > $lo) && ($hi === null || $b[1] < $hi)))->keys()->all();
        };

        if (($f['type'] ?? null) !== 'new' && $vehicle === 'car') {                      // used cars: /cars?brand[]=&fuel[]=&city[]=&transmission[]=&budget[]=&sort=
            $q = [];
            if (! empty($f['brand'])) $q['brand'] = [$f['brand']];
            if (! empty($f['fuel'])) $q['fuel'] = \App\Models\VehicleFuel::where(fn ($w) => collect($f['fuel'])->each(fn ($n) => $w->orWhere('name', 'like', "%$n%")))->pluck('name')->all();
            if (! empty($f['city'])) $q['city'] = [$f['city']];
            if (! empty($f['transmission'])) {
                $vals = Listing::active()->whereNotNull('transmission')->distinct()->pluck('transmission');
                $q['transmission'] = $vals->filter(fn ($v) => $f['transmission'] === 'auto' ? preg_match('/auto|amt|cvt|dct/i', $v) : preg_match('/manual/i', $v))->values()->all();
            }
            if ($b = $bands('car')) $q['budget'] = $b;
            if (($f['sort'] ?? null) === 'desc') $q['sort'] = 'price_desc';
            elseif (($f['sort'] ?? null) === 'asc' || isset($f['price_max'])) $q['sort'] = 'price_asc';
            return route('cars.index', array_filter($q, fn ($v) => $v !== []));
        }

        $route = ['car' => 'newcars.index', 'bike' => 'newbikes.index', 'truck' => 'newtrucks.index'][$vehicle] ?? 'newcars.index';
        $q = [];                                                                          // new vehicles: /new-cars?brand[]=id&body_type[]=id&fuel[]=id&budget[]=
        if (! empty($f['brand_id'])) $q['brand'] = [$f['brand_id']];
        if (! empty($f['body_id'])) $q['body_type'] = [$f['body_id']];
        if (! empty($f['fuel'])) $q['fuel'] = \App\Models\VehicleFuel::where(fn ($w) => collect($f['fuel'])->each(fn ($n) => $w->orWhere('name', 'like', "%$n%")))->pluck('id')->all();
        if ($b = $bands($vehicle)) $q['budget'] = $b;
        if (($f['status'] ?? null)) $q['status'] = $f['status'];
        return route($route, array_filter($q, fn ($v) => $v !== []));
    }
}
