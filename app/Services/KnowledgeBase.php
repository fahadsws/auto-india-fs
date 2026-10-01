<?php

namespace App\Services;

use App\Models\Article;
use App\Models\KnowledgeChunk;
use App\Models\Listing;
use App\Models\Setting;
use App\Models\Video;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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
        KnowledgeChunk::updateOrCreate(['type' => $type, 'ref_id' => $m->getKey()], $data);
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
    }

    public static function reindexAll(): int
    {
        KnowledgeChunk::query()->delete();
        $n = 0;
        Article::published()->chunkById(200, function ($rows) use (&$n) { foreach ($rows as $r) { self::sync($r); $n++; } });
        Listing::active()->chunkById(200, function ($rows) use (&$n) { foreach ($rows as $r) { self::sync($r); $n++; } });
        Video::active()->chunkById(200, function ($rows) use (&$n) { foreach ($rows as $r) { self::sync($r); $n++; } });
        \App\Models\VehicleModel::published()->chunkById(200, function ($rows) use (&$n) { foreach ($rows as $r) { self::sync($r); $n++; } });
        self::syncSiteInfo();
        return $n + 1;
    }

    /** A fixed chunk describing the business so the assistant can answer "who are you / contact" questions. */
    public static function syncSiteInfo(): void
    {
        $name = Setting::get('site.name', config('app.name'));
        $content = trim($name.'. '.Setting::get('site.tagline', '').' '.Setting::get('site.about', '')
            .' Contact email: '.Setting::get('site.email', '').'. Phone: '.Setting::get('site.phone', '').'. Address: '.Setting::get('site.address', '').'.');
        KnowledgeChunk::updateOrCreate(['type' => 'page', 'ref_id' => 1], [
            'title' => 'About '.$name, 'content' => $content, 'url' => self::relative(route('about')), 'image' => null,
        ]);
    }

    /** @return \Illuminate\Support\Collection<int, KnowledgeChunk> */
    public static function search(string $query, int $limit = 5)
    {
        $tokens = collect(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($query), -1, PREG_SPLIT_NO_EMPTY))
            ->filter(fn ($t) => mb_strlen($t) >= 3 && ! in_array($t, self::STOP, true))->unique()->values();
        if ($tokens->isEmpty()) return collect();

        $hits = KnowledgeChunk::query()
            ->select('*', DB::raw('MATCH(title, content) AGAINST (? IN NATURAL LANGUAGE MODE) AS score'))
            ->addBinding($tokens->implode(' '), 'select')
            ->whereRaw('MATCH(title, content) AGAINST (? IN NATURAL LANGUAGE MODE)', [$tokens->implode(' ')])
            ->orderByDesc('score')->limit($limit)->get();

        if ($hits->isNotEmpty()) return $hits;

        // Fallback for short / partial tokens (e.g. "creta", "xuv") the fulltext index may miss.
        return KnowledgeChunk::query()
            ->where(function ($q) use ($tokens) {
                foreach ($tokens->take(5) as $t) { $q->orWhere('title', 'like', "%$t%")->orWhere('content', 'like', "%$t%"); }
            })->limit($limit)->get();
    }
}
