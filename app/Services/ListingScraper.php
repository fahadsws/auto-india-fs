<?php

namespace App\Services;

use App\Models\Listing;
use App\Models\ListingSource;
use Illuminate\Support\Str;

/**
 * Polite marketplace scraper.
 *  - obeys robots.txt (per-source switch), identifies itself, throttles requests, caps pages per run
 *  - discovers detail-page links on listing pages, extracts facts from JSON-LD / Open Graph / page text
 *  - re-checks stale listings so sold or removed cars leave the site automatically
 * Stores facts + our own generated summary (not the seller's text) and downloads photos into our storage.
 */
class ListingScraper
{
    private const FUELS = ['Petrol', 'Diesel', 'CNG', 'Electric', 'Hybrid', 'LPG'];
    private const SOLD_STRONG_RE = '/(this car (has been|is) (already )?sold|this (listing|car) (has )?(expired|is no longer available)|car (is )?no longer available)/i';
    private const SOLD_RE ='/(this car (has been|is) sold|sold out|no longer available|listing (has )?expired|car (is )?sold|not available anymore)/i';

    /** Problems met while crawling (list page unreachable, no links found ...) - surfaced in the source status. */
    public array $notes = [];

    /** @return array{new:int,seen:int,skipped:int,duplicates:int,pending:int} */
    public function runSource(ListingSource $s): array
    {
        $this->notes = [];
        $urls = $this->discover($s);
        $stats = ['new' => 0, 'seen' => count($urls), 'skipped' => 0, 'duplicates' => 0, 'pending' => 0];

        // Anything we already know about is "still listed" -> refresh last_seen_at (and re-open if it was auto-closed).
        $known = Listing::whereIn('source_url', $urls)->get()->keyBy('source_url');
        foreach ($known as $l) {
            $l->last_seen_at = now();
            if ($l->status === 'sold' && $l->status_reason === 'no longer listed') { $l->status = 'active'; $l->sold_at = null; $l->status_reason = null; }
            $l->save();
        }

        $fresh = array_values(array_filter($urls, fn ($u) => ! $known->has($u)));
        $batch = array_slice($fresh, 0, max(1, $s->max_per_run));
        $stats['pending'] = count($fresh) - count($batch);      // found on the list pages but beyond this run's limit: still to import
        foreach ($batch as $url) {
            if ($s->respect_robots && ! Robots::allowed($url)) { $stats['skipped']++; continue; }
            usleep($s->delay_ms * 1000);
            $r = $this->importPage($url, $s);
            if ($r instanceof Listing) $stats['new']++;
            elseif ($r === 'duplicate') $stats['duplicates']++;
            else $stats['skipped']++;
        }
        return $stats;
    }

    /**
     * Import ONE vehicle page from a link (admin "import from link" / `crawl:url --type=listing`).
     *
     * @return Listing|string  the listing, or the reason it was not imported
     */
    public function importUrl(string $url, ?ListingSource $s = null): Listing|string
    {
        $url = $this->normalize($url);
        if ($url === '') return 'That is not a valid link.';
        if ($l = Listing::where('source_url', $url)->first()) return "Already imported: {$l->title}";
        $r = $this->importPage($url, $s);
        return $r === 'duplicate' ? 'The same car (title, year, km, price) is already listed.' : $r;
    }

    /** Fetch one detail page, extract the vehicle facts and store them. @return Listing|string */
    private function importPage(string $url, ?ListingSource $s): Listing|string
    {
        $page = Crawl\Fetcher::get($url);
        if (! $page['ok']) return $page['error'] ?? 'Fetch failed';
        $data = $this->extract($page['body'], $page['url']);
        if (! $data) return 'Not enough vehicle facts (title plus price or km) found on the page.';
        if ($this->isDuplicate($data)) return 'duplicate';
        return $this->store($s, $url, $data);
    }

    /** The same car is often advertised on several marketplaces: match on title + year + km + price. */
    private function isDuplicate(array $d): bool
    {
        if (! $d['price'] || ! $d['km'] || ! $d['year']) return false;
        return Listing::where('year', $d['year'])->where('km_driven', $d['km'])->where('price', $d['price'])->where('status', 'active')
            ->get(['title'])->contains(fn ($l) => TextTools::titleSimilarity($l->title, $d['title']) >= 0.6);
    }

    /** Collect detail-page URLs from the configured listing pages. */
    public function discover(ListingSource $s): array
    {
        $urls = [];
        foreach (array_filter(array_map('trim', preg_split('/\R/', (string) $s->list_urls))) as $list) {
            if ($s->respect_robots && ! Robots::allowed($list)) { $this->notes[] = 'robots.txt blocks '.Str::limit($list, 60); continue; }
            $page = Crawl\Fetcher::get($list);
            if (! $page['ok']) { $this->notes[] = Str::limit($list, 60).': '.($page['error'] ?? 'fetch failed'); continue; }

            $links = Crawl\LinkFinder::articleLinks($page['body'], $page['url'], $s->detail_pattern ?: null, 400);
            if (! $links) $this->notes[] = 'no vehicle links found on '.Str::limit($list, 60).' (page layout or detail pattern changed?)';
            foreach ($links as $l) {
                $u = $this->normalize($l['link']);
                if ($u) $urls[$u] = true;
            }
            usleep($s->delay_ms * 1000);
        }
        return array_keys($urls);
    }

    private function normalize(string $url): string
    {
        return $url === '' ? '' : (strtok(strtok($url, '#'), '?') ?: '');
    }

    /* --------------------------- extraction --------------------------- */

    public function extract(string $html, string $url): ?array
    {
        $dom = PageFetcher::dom($html);
        $meta = PageFetcher::meta($dom);
        $ld = collect(PageFetcher::jsonLd($dom))->first(fn ($n) => in_array($n['@type'] ?? '', ['Car', 'Vehicle', 'Product'], true) || (is_array($n['@type'] ?? null) && array_intersect($n['@type'], ['Car', 'Vehicle', 'Product'])));

        $ogTitle = html_entity_decode($meta['og:title'] ?? '');
        $ogDesc = html_entity_decode($meta['og:description'] ?? $meta['description'] ?? '');
        $blob = trim($ogTitle.' | '.$ogDesc.' | '.($ld['name'] ?? '').' '.($ld['description'] ?? ''));

        $title = $this->cleanTitle($ogTitle, $ogDesc, $ld['name'] ?? null);
        if (! $title || mb_strlen($title) < 6) return null;

        $offer = $ld['offers'] ?? null; if (isset($offer[0])) $offer = $offer[0];
        $price = isset($offer['price']) ? (int) preg_replace('/\D/', '', explode('.', (string) $offer['price'])[0]) : null;
        $price = $price ?: $this->price($blob);

        $year = (int) ($ld['vehicleModelDate'] ?? 0) ?: (preg_match('/\b(19[89]\d|20[0-4]\d)\b/', $title, $m) ? (int) $m[1] : (preg_match('/\b(19[89]\d|20[0-4]\d)\b/', $blob, $m2) ? (int) $m2[1] : null));
        $km = null;
        if (isset($ld['mileageFromOdometer']['value'])) $km = (int) preg_replace('/\D/', '', (string) $ld['mileageFromOdometer']['value']);
        if (! $km && preg_match('/([\d,]{3,9})\s*(?:km|kms|kilomet)/i', $blob, $m)) $km = (int) str_replace(',', '', $m[1]);

        $fuel = collect(self::FUELS)->first(fn ($f) => stripos($blob, $f) !== false);
        $trans = stripos($blob, 'automatic') !== false ? 'Automatic' : (stripos($blob, 'manual') !== false ? 'Manual' : null);
        $owner = preg_match('/\b(1st|2nd|3rd|4th|first|second|third)\s+owner/i', $blob, $m) ? ucfirst(strtolower($m[1])).' Owner' : null;
        $city = preg_match('/\b(?:in|,)\s+([A-Z][a-zA-Z]+(?:\s[A-Z][a-zA-Z]+)?)\s+(?:at|for|with|-|\||₹)/', $ogTitle.' ', $m) ? trim($m[1]) : null;
        // Sanity ranges: a wrong regex match must become "unknown", never a bad number on the site.
        if ($price !== null && ($price < 30000 || $price > 300000000)) $price = null;
        if ($km !== null && ($km < 1 || $km > 1500000)) $km = null;
        if ($year !== null && ($year < 1985 || $year > (int) date('Y') + 1)) $year = null;
        if (! $price && ! $km) return null; // not a real listing page

        // Known brand in the title first; otherwise the brand the page itself declares (JSON-LD), which store() adds to vehicle_brands if new.
        $ldBrand = is_array($ld['brand'] ?? null) ? ($ld['brand']['name'] ?? null) : ($ld['brand'] ?? null);
        $brand = $this->brand($title) ?? (is_string($ldBrand) && CarMasters::plausibleBrand(trim($ldBrand)) ? CarUpdater::canonicalBrand($ldBrand) : null);
        if ($fuel === null && is_string($ld['fuelType'] ?? null) && trim($ld['fuelType']) !== '') $fuel = trim($ld['fuelType']);
        $images = $this->images($html, $meta, $ld, $url);

        return compact('title', 'price', 'year', 'km', 'fuel', 'trans', 'owner', 'city', 'brand', 'images');
    }

    private function cleanTitle(string $ogTitle, string $ogDesc, ?string $ldName): string
    {
        $cut = function (string $t) {
            $t = preg_replace('/^(buy\s+)?(used|second[\s-]hand|pre[\s-]owned)\s+/i', '', trim($t));
            $t = preg_split('/\s+(?:\d{3,4}\s?cc\b|petrol\b|diesel\b|cng\b|electric\b|hybrid\b|lpg\b|automatic\b|manual\b)|\s+car\s+(?:for\s+sale|in)\b|\s+for\s+sale\b|\s+at\s+(?:₹|rs)|\s+-\s+|\s+\|\s+|\s+in\s+[A-Z]/iu', $t)[0] ?? $t;
            return trim(preg_replace('/\(\d{4}\s*-\s*\d{4}\)/', '', $t));
        };
        $fromTitle = $cut($ogTitle ?: (string) $ldName);
        // Descriptions often carry the variant: "Buy used 2022 Tata Harrier (2019-2023) XTA Plus AT BSVI Diesel with ..."
        if (preg_match('/used\s+((?:19|20)\d{2}\s+.+?)\s+(?:petrol|diesel|cng|electric|hybrid)\b/i', $ogDesc, $m)) {
            $fromDesc = trim(preg_replace('/\(\d{4}\s*-\s*\d{4}\)/', '', $m[1]));
            if (mb_strlen($fromDesc) >= mb_strlen($fromTitle) && mb_strlen($fromDesc) < 90) return preg_replace('/\s+/', ' ', $fromDesc);
        }
        return preg_replace('/\s+/', ' ', $fromTitle);
    }

    private function price(string $text): ?int
    {
        if (preg_match('/(?:₹|rs\.?|inr)\s*([\d,]+(?:\.\d+)?)\s*(lakh|lac|cr|crore)?/iu', $text, $m) || preg_match('/\bat\s+([\d,]+(?:\.\d+)?)\s*(lakh|lac|cr|crore)\b/i', $text, $m)) {
            $n = (float) str_replace(',', '', $m[1]); $u = strtolower($m[2] ?? '');
            $v = in_array($u, ['lakh', 'lac']) ? $n * 100000 : (in_array($u, ['cr', 'crore']) ? $n * 10000000 : $n);
            return ($v >= 30000 && $v <= 300000000) ? (int) round($v) : null;
        }
        return null;
    }

    /** A brand already in vehicle_brands (or the alias list) found in the title; new brands are added on store() when the page names one we can trust. */
    private function brand(string $title): ?string
    {
        return CarMasters::detectBrand($title);
    }

    private function images(string $html, array $meta, ?array $ld, string $url): array
    {
        $imgs = [];
        if (! empty($meta['og:image'])) $imgs[] = PageFetcher::absolute($meta['og:image'], $url);
        foreach ((array) ($ld['image'] ?? []) as $i) if (is_string($i)) $imgs[] = PageFetcher::absolute($i, $url);
        if (! empty($imgs[0])) { // other photos of the same car usually share the folder of the main image
            $prefix = dirname(dirname($imgs[0]));
            if (strlen($prefix) > 25 && preg_match_all('#'.preg_quote($prefix, '#').'/[^"\'\s<>\\\\)]+\.(?:jpe?g|png|webp)#i', $html, $m)) array_push($imgs, ...$m[0]);
        }
        return array_slice(array_values(array_unique(array_filter($imgs))), 0, 5);
    }

    /* ----------------------------- storage ----------------------------- */

    /** $s is null for a one-off link import: the listing is then attributed to the site's host. */
    private function store(?ListingSource $s, string $url, array $d): Listing
    {
        $title = $d['title'];
        $carId = CarUpdater::matchListing($d['brand'] ?? null, $title);
        $rest = preg_replace('/^(?:19|20)\d{2}\s+/', '', $title);
        if (! empty($d['brand'])) $rest = preg_replace('/^'.preg_quote($d['brand'], '/').'\s*/i', '', $rest);
        $model = $carId ? \App\Models\VehicleModel::find($carId)?->name : $this->modelName($rest);

        $paths = [];
        foreach (array_slice($d['images'], 0, 4) as $i => $img) { if ($p = ImageStore::fromUrl($img, 'listings')) $paths[] = $p; }

        $facts = array_filter([$d['year'], $d['fuel'], $d['trans'], $d['km'] ? number_format($d['km']).' km driven' : null, $d['owner'], $d['city']]);
        $desc = "$title".($facts ? ' — '.implode(', ', $facts) : '').'. Enquire with us for the latest availability, inspection details and a test drive.';

        return Listing::create([
            'title' => Str::limit($title, 190, ''),
            'slug' => Listing::uniqueSlug(preg_match('/^(19|20)\d{2}\b/', $title) ? $title : $title.' '.($d['year'] ?? '')),
            'brand_id' => CarMasters::brandId($d['brand'] ?? null), 'model' => $model ?: null,      // ids from vehicle_brands / vehicle_fuels; a missing one is created
            'year' => $d['year'], 'price' => $d['price'], 'km_driven' => $d['km'], 'fuel_id' => CarMasters::fuelId($d['fuel'] ?? null), 'transmission' => $d['trans'], 'owner' => $d['owner'], 'city' => $d['city'],
            'description' => $desc, 'image_path' => $paths[0] ?? null, 'images' => $paths ?: null,
            'source_name' => $s?->name ?? Crawl\LinkFinder::host($url), 'source_url' => $url, 'listing_source_id' => $s?->id, 'vehicle_model_id' => $carId,
            'external_id' => 'scrape:'.sha1($url), 'status' => 'active', 'last_seen_at' => now(),
        ]);
    }

    /** First word of the remainder, plus the next word for well-known two-part names (Grand i10, XUV 300, Innova Crysta...). */
    private function modelName(string $rest): ?string
    {
        $w = preg_split('/\s+/', trim($rest), -1, PREG_SPLIT_NO_EMPTY);
        if (! $w) return null;
        $joiners = ['grand', 'xuv', 'xev', 'be', 'innova', 'land', 'range', 'urban', 'wagon', 's', 'cr', 'santro', 'new'];
        return in_array(strtolower($w[0]), $joiners, true) && isset($w[1]) ? $w[0].' '.$w[1] : $w[0];
    }

    /* --------------------------- availability --------------------------- */

    /** Re-check listings not confirmed recently; mark sold/removed ones so they disappear from the site. */
    public function checkAvailability(int $max = 15): string
    {
        $sold = 0; $ok = 0; $repriced = 0;
        $stale = Listing::with('source')->where('status', 'active')->whereNotNull('listing_source_id')->whereNotNull('source_url')
            ->where(fn ($q) => $q->whereNull('last_seen_at')->orWhere('last_seen_at', '<', now()->subHours(20)))
            ->orderBy('last_seen_at')->limit($max)->get();

        foreach ($stale as $l) {
            $src = $l->source;
            if ($src && $src->respect_robots && ! Robots::allowed($l->source_url)) { $l->update(['last_seen_at' => now()]); continue; }
            usleep(($src->delay_ms ?? 1500) * 1000);
            $page = Crawl\Fetcher::get($l->source_url);
            // Network trouble, rate limiting, blocks and 5xx say nothing about the car: never mark it sold on those, retry next run.
            if (! $page['ok'] && ! in_array($page['status'], [404, 410], true)) continue;

            $gone = in_array($page['status'], [404, 410], true) || $this->looksSold($page['body'], $page['url'], $l->source_url);
            if ($gone) {
                $l->update(['status' => 'sold', 'sold_at' => now(), 'status_reason' => 'no longer listed']); $sold++; continue;
            }
            $d = $this->extract($page['body'], $l->source_url);
            $upd = ['last_seen_at' => now()];
            // A new price counts only when it is plausibly the same car (within -60%/+150%); otherwise it is an extraction glitch.
            if ($d && $d['price'] && $d['price'] !== $l->price && (! $l->price || ($d['price'] >= $l->price * 0.4 && $d['price'] <= $l->price * 2.5))) { $upd['price'] = $d['price']; $repriced++; }
            $l->update($upd); $ok++;
        }
        return "Checked ".$stale->count()." listing(s): $ok still available, $sold marked sold/removed, $repriced repriced.";
    }

    /**
     * Sold/removed detection without false alarms: explicit phrases anywhere in the text, loose phrases ("sold out")
     * only in the title / headings / meta where a sidebar of other cars cannot trigger them, structured availability,
     * or a redirect to a different page.
     */
    public function looksSold(string $html, string $finalUrl, string $originalUrl): bool
    {
        $dom = PageFetcher::dom($html);
        $meta = PageFetcher::meta($dom);
        $head = ($meta['og:title'] ?? '').' '.($meta['og:description'] ?? '').' '.($dom->getElementsByTagName('title')->item(0)?->textContent ?? '');
        foreach (['h1', 'h2'] as $h) foreach ($dom->getElementsByTagName($h) as $n) $head .= ' '.$n->textContent;
        if (preg_match(self::SOLD_RE, $head) || preg_match(self::SOLD_RE, (string) ($meta['availability'] ?? ''))) return true;

        foreach (PageFetcher::jsonLd($dom) as $n) {
            $offer = $n['offers'] ?? null; if (isset($offer[0])) $offer = $offer[0];
            if (is_array($offer) && preg_match('/SoldOut|OutOfStock|Discontinued/i', (string) ($offer['availability'] ?? ''))) return true;
        }
        if (preg_match(self::SOLD_STRONG_RE, strip_tags(substr($html, 0, 400000)))) return true;

        $a = rtrim($this->normalize($finalUrl), '/'); $b = rtrim($this->normalize($originalUrl), '/');
        return $a !== $b && ! str_contains($finalUrl, basename($b));     // bounced to a category/home page
    }
}
