<?php

namespace App\Services;

use App\Models\Listing;
use App\Models\ListingSource;
use App\Services\Crawl\Fetcher;
use App\Services\Crawl\SourceHealth;
use Illuminate\Support\Str;

/**
 * Imports used-car listings from any JSON or CSV feed (partner feeds, marketplace exports, your own sheets).
 * The field mapping tells us which key in the feed holds each of our fields.
 */
class ListingImporter
{
    public const FIELDS = ['title', 'brand', 'model', 'year', 'price', 'km_driven', 'fuel', 'transmission', 'owner', 'city', 'description', 'image', 'url', 'external_id'];

    /** Run one source (admin "Run now" and the scheduler share this). Returns the status line; throws nothing. */
    public function runOne(ListingSource $s): string
    {
        try {
            if ($s->mode === 'scrape') {
                $scraper = new ListingScraper();
                $r = $scraper->runSource($s);
                $msg = "{$r['new']} added, {$r['pending']} more waiting ({$r['seen']} on list pages), {$r['skipped']} skipped".($r['duplicates'] ? ", {$r['duplicates']} duplicate(s)" : '');
                // Nothing discovered at all means the site changed or blocked us: count it as a failure so it is flagged and backed off.
                if ($r['seen'] === 0) { $msg = 'no listings found'.($scraper->notes ? ' - '.implode('; ', array_slice($scraper->notes, 0, 2)) : ''); SourceHealth::fail($s, $msg); return $msg; }
                if ($scraper->notes) $msg .= ' | '.implode('; ', array_slice($scraper->notes, 0, 2));
                SourceHealth::ok($s, $msg, $r['pending']);
                return $msg;
            }
            [$new, $upd] = $this->runSource($s);
            SourceHealth::ok($s, "$new new, $upd updated", $new + $upd);
            return "$new new, $upd updated";
        } catch (\Throwable $e) {
            SourceHealth::fail($s, $e->getMessage());
            return 'error: '.$e->getMessage();
        }
    }

    public function runAll(): string
    {
        $out = []; $healthy = 0; $tried = 0;
        foreach (ListingSource::where('is_active', true)->get() as $s) {
            if (SourceHealth::coolingDown($s)) { $out[] = "{$s->name}: cooling down after repeated failures"; continue; }
            $tried++;
            $msg = $this->runOne($s);
            if (! str_starts_with($msg, 'error') && ! str_starts_with($msg, 'no listings')) $healthy++;
            $out[] = "{$s->name}: $msg";
        }
        if (! $out) return 'No active listing sources.';
        return ($tried > 0 && $healthy === 0 ? 'Error: every source failed - ' : '').implode(' | ', $out);
    }

    /** @return array{0:int,1:int} */
    public function runSource(ListingSource $s): array
    {
        $res = Fetcher::get($s->url, ['timeout' => 40, 'accept' => 'application/json,text/csv,text/plain,*/*;q=0.5']);
        if (! $res['ok']) throw new \RuntimeException($res['error'] ?? 'Fetch failed');
        $rows = $s->format === 'csv' ? $this->parseCsv($res['body']) : $this->parseJson(json_decode($res['body'], true), $s->items_path);
        if (! $rows) throw new \RuntimeException('Feed is empty or its format changed');
        return $this->importRows($rows, $s->mapping ?? [], $s->name);
    }

    public function parseCsv(string $csv): array
    {
        $lines = array_map('str_getcsv', preg_split('/\R/', trim(preg_replace('/^\xEF\xBB\xBF/', '', $csv))));
        $head = array_map(fn ($h) => trim((string) $h), array_shift($lines) ?? []);
        $rows = [];
        foreach ($lines as $l) { if (count($l) === count($head)) $rows[] = array_combine($head, $l); }
        return $rows;
    }

    private function parseJson(mixed $json, ?string $path): array
    {
        $arr = $path ? data_get($json, $path) : $json;
        return is_array($arr) ? array_values($arr) : [];
    }

    /** @return array{0:int,1:int} */
    public function importRows(array $rows, array $mapping, string $sourceName): array
    {
        $new = $upd = 0;
        foreach (array_slice($rows, 0, 500) as $row) {
            $get = fn ($f) => data_get($row, $mapping[$f] ?? $f);
            $title = trim((string) $get('title'));
            if (! $title) continue;

            $ext = (string) ($get('external_id') ?: sha1($sourceName.$title.$get('year').$get('price')));
            $extKey = Str::limit($sourceName, 20, '').':'.$ext;
            $existing = Listing::where('external_id', $extKey)->first();

            $data = [
                'title' => $title,
                'brand' => $get('brand') ?: CarMasters::detectBrand($title),
                'model' => $get('model'),
                'year' => (int) preg_replace('/\D/', '', (string) $get('year')) ?: null,
                'price' => (int) preg_replace('/[^\d]/', '', explode('.', (string) $get('price'))[0]) ?: null,
                'km_driven' => (int) preg_replace('/\D/', '', (string) $get('km_driven')) ?: null,
                'fuel' => $get('fuel'), 'transmission' => $get('transmission'), 'owner' => $get('owner'), 'city' => $get('city'),
                'description' => $get('description'),
                'source_name' => $sourceName, 'source_url' => $get('url'),
            ];

            if ($existing) {
                $existing->update($data); $upd++;
            } else {
                $img = ImageStore::fromUrl($get('image'), 'listings');
                Listing::create($data + ['slug' => Listing::uniqueSlug($title.' '.$get('year')), 'external_id' => $extKey, 'image_path' => $img, 'status' => 'active']);
                $new++;
            }
        }
        return [$new, $upd];
    }
}
