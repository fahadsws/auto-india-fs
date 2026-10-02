<?php

namespace App\Services;

use App\Models\Article;
use App\Models\KnowledgeChunk;
use App\Models\Listing;
use App\Models\Setting;
use App\Models\Video;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Own-data knowledge base. Uses MySQL FULLTEXT (free, no embeddings/API cost).
 */
class KnowledgeBase
{
    private const STOP = ['the','and','for','with','what','which','who','how','can','you','your','are','was','were','this','that','have','has','any','tell','about','give','show','please','need','want','looking','best','good','india','car','cars','from','into','does','will','would','should','could','there','their','them','than','then','also','more','some'];

    public static function sync(Model $m): void
    {
        $data = $m->toKnowledge();
        $type = $m::knowledgeType();
        if (! $data) { self::forget($m); return; }
        $data['url'] = self::relative($data['url'] ?? null);
        $data['image'] = self::relative($data['image'] ?? null);
        $chunk = KnowledgeChunk::updateOrCreate(['type' => $type, 'ref_id' => $m->getKey()], $data);
        if (! $chunk->wasRecentlyCreated && ! $chunk->wasChanged()) $chunk->touch();   // marks it as seen by reindexAll()
        if ($m instanceof \App\Models\VehicleModel) Cache::forget('asst:modelidx');
    }

    /** Store site URLs without the domain so they survive domain/APP_URL changes. */
    public static function relative(?string $u): ?string
    {
        $root = rtrim(url('/'), '/');
        return ($u && str_starts_with($u, $root.'/')) ? substr($u, strlen($root)) : $u;
    }

    public static function absolute(?string $u): ?string
    {
        return ($u && str_starts_with($u, '/')) ? url($u) : $u;
    }

    public static function forget(Model $m): void
    {
        KnowledgeChunk::where('type', $m::knowledgeType())->where('ref_id', $m->getKey())->delete();
        if ($m instanceof \App\Models\VehicleModel) Cache::forget('asst:modelidx');
    }

    public static function reindexAll(): int
    {
        // Rebuild in place: the knowledge is never empty while this runs. Chunks not re-saved below are stale and removed at the end.
        $started = now()->startOfSecond();
        $n = 0;
        Article::published()->chunkById(200, function ($rows) use (&$n) { foreach ($rows as $r) { self::sync($r); $n++; } });
        Listing::active()->chunkById(200, function ($rows) use (&$n) { foreach ($rows as $r) { self::sync($r); $n++; } });
        Video::active()->chunkById(200, function ($rows) use (&$n) { foreach ($rows as $r) { self::sync($r); $n++; } });
        \App\Models\VehicleModel::published()->chunkById(200, function ($rows) use (&$n) { foreach ($rows as $r) { self::sync($r); $n++; } });
        \App\Models\CarComparison::query()->chunkById(200, function ($rows) use (&$n) { foreach ($rows as $r) { self::sync($r); $n++; } });
        \App\Models\Page::published()->chunkById(200, function ($rows) use (&$n) { foreach ($rows as $r) { self::sync($r); $n++; } });
        self::syncSiteInfo();
        KnowledgeChunk::where('updated_at', '<', $started)->delete();
        Cache::forget('asst:modelidx');
        return $n + 1;
    }

    /** The business chunk: who we are, contact details, and the admin's own "business facts" (hours, dealers, services, offers). */
    public static function syncSiteInfo(): void
    {
        $name = Setting::get('site.name', config('app.name'));
        $content = trim($name.'. '.Setting::get('site.tagline', '').' '.Setting::get('site.about', '')
            .' Contact email: '.Setting::get('site.email', '').'. Phone: '.Setting::get('site.phone', '').'. Address: '.Setting::get('site.address', '').'. '
            .'Website: '.url('/').'. '.Setting::get('assistant.business_facts', ''));
        KnowledgeChunk::updateOrCreate(['type' => 'page', 'ref_id' => 1], [
            'title' => 'About '.$name, 'content' => $content, 'url' => self::relative(route('about')), 'image' => null,
        ]);
    }

    public static function siteInfo(): ?KnowledgeChunk
    {
        return KnowledgeChunk::where('type', 'page')->where('ref_id', 1)->first();
    }

    /**
     * "Title (link); ..." of the key site pages and the admin-made dynamic pages, so the assistant can point visitors to the right page.
     * Cached briefly; any page edit is picked up within minutes.
     */
    public static function pageIndex(): string
    {
        return Cache::remember('asst:pageindex', 300, function () {
            $fixed = ['Used cars' => route('cars.index'), 'New cars' => route('newcars.index'), 'Car news' => route('news.index'), 'Compare cars' => route('compare.index'), 'Sell your car' => route('sell'), 'Car EMI calculator' => route('emi'), 'Cost per km calculator' => route('costperkm'), 'Roast my car' => route('roast'), 'Contact' => route('contact'), 'About us' => route('about')];
            $pages = [];
            try { $pages = \App\Models\Page::published()->orderByDesc('updated_at')->limit(15)->get(['title', 'slug'])->mapWithKeys(fn ($p) => [Str::limit($p->title, 45, '') => url('/'.$p->slug)])->all(); } catch (\Throwable) {}
            return collect($fixed + $pages)->map(fn ($u, $t) => "$t ($u)")->implode('; ');
        });
    }

    /** Distinct words (4+ letters) used in knowledge titles: model, brand and topic names as OUR content spells them. Cached briefly. */
    private static function vocabulary(): array
    {
        return Cache::remember('asst:vocab', 600, function () {
            $words = [];
            KnowledgeChunk::query()->select('title')->orderByDesc('id')->limit(5000)->pluck('title')->each(function ($t) use (&$words) {
                foreach (QueryIntent::tokens((string) $t) as $w) if (mb_strlen($w) >= 4 && ! ctype_digit($w)) $words[$w] = ($words[$w] ?? 0) + 1;
            });
            return $words;
        });
    }

    /**
     * Fix misspelled / misheard names ("jeyout" -> "jetour", "siera" -> "sierra") by snapping a word that appears nowhere in our titles
     * to the closest title word (same first letter, 1-2 letters off). Words that are already spelled right are left alone.
     *
     * @param  \Illuminate\Support\Collection<int, string>  $tokens
     */
    public static function correct($tokens)
    {
        $vocab = self::vocabulary();
        if (! $vocab) return $tokens;
        return $tokens->map(function ($t) use ($vocab) {
            $len = mb_strlen($t);
            if ($len < 5 || isset($vocab[$t]) || ctype_digit($t) || in_array($t, QueryIntent::FILLER, true)) return $t;
            $best = null; $bestD = 99;
            foreach ($vocab as $w => $n) {
                $wl = mb_strlen($w);
                if (abs($wl - $len) > 2 || $w[0] !== $t[0]) continue;
                $d = levenshtein($t, $w);
                if ($d < $bestD || ($d === $bestD && $n > ($vocab[$best] ?? 0))) { $bestD = $d; $best = $w; }
            }
            $limit = $len >= 6 ? 2 : 1;
            return $best !== null && $bestD <= $limit ? $best : $t;
        })->unique()->values();
    }

    /**
     * Best chunks for a question. Title matches (a model or brand named in the title) outrank text-only matches,
     * FULLTEXT adds relevance, and newer content wins ties. Chat filler (Hinglish included) never reaches the query.
     *
     * @return \Illuminate\Support\Collection<int, KnowledgeChunk>
     */
    public static function search(string $query, int $limit = 5)
    {
        $tokens = collect(QueryIntent::tokens($query))
            ->filter(fn ($t) => mb_strlen($t) >= 3 && ! in_array($t, self::STOP, true) && ! in_array($t, QueryIntent::FILLER, true))->unique()->values();
        if ($tokens->isEmpty()) return collect();
        $tokens = self::correct($tokens);

        $text = $tokens->implode(' ');
        $ft = collect();
        if (DB::connection()->getDriverName() === 'mysql') {
            try {
                $ft = KnowledgeChunk::query()
                    ->select('*', DB::raw('MATCH(title, content) AGAINST (? IN NATURAL LANGUAGE MODE) AS score'))
                    ->addBinding($text, 'select')
                    ->whereRaw('MATCH(title, content) AGAINST (? IN NATURAL LANGUAGE MODE)', [$text])
                    ->orderByDesc('score')->limit($limit * 3)->get();
            } catch (\Throwable) {}
        }

        // Words in the title: finds "creta", "xuv" and other short or partial words the FULLTEXT index misses.
        $byTitle = KnowledgeChunk::query()
            ->where(function ($q) use ($tokens) { foreach ($tokens->take(6) as $t) $q->orWhere('title', 'like', "%$t%"); })
            ->orderByDesc('updated_at')->limit($limit * 3)->get();

        $all = $ft->concat($byTitle)->unique('id');
        if ($all->isEmpty()) {
            $all = KnowledgeChunk::query()->where(function ($q) use ($tokens) { foreach ($tokens->take(5) as $t) $q->orWhere('content', 'like', "%$t%"); })->orderByDesc('updated_at')->limit($limit * 2)->get();
        }

        return $all->map(function ($h) use ($tokens) {
            $title = mb_strtolower((string) $h->title);
            $h->rank = $tokens->filter(fn ($t) => str_contains($title, $t))->count() * 10 + (float) ($h->score ?? 0);
            return $h;
        })->sortBy([['rank', 'desc'], ['updated_at', 'desc']])->take($limit)->values();
    }
}
