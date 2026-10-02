<?php

namespace App\Services;

use App\Models\Listing;
use App\Models\VehicleModel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Understands what a message is ABOUT before anything is searched: which car/bike model the visitor named
 * (typos, voice mistakes and "tata siera" included) and whether they want news. Pure rules + one cached name list,
 * so it costs no AI tokens and scales with the model catalog, not with the amount of articles or listings.
 */
class QueryIntent
{
    /** Chat filler (English + Hinglish) that carries no search meaning. Removed before searching the knowledge base. */
    public const FILLER = [
        'mujhe', 'muje', 'mujhko', 'mere', 'mera', 'meri', 'hum', 'main', 'mai', 'aap', 'apna', 'apni', 'ke', 'ki', 'ka', 'ko', 'me', 'mein', 'men', 'se', 'par', 'pe', 'ye', 'yeh', 'woh', 'wo',
        'kya', 'hai', 'hain', 'tha', 'thi', 'ho', 'hota', 'hoti', 'hoga', 'bare', 'baare', 'bar', 'baat', 'batao', 'bataiye', 'bataye', 'bata', 'btao', 'dikhao', 'dikha', 'dikhaiye', 'dena', 'dijiye', 'do', 'de',
        'chahiye', 'chaiye', 'chahie', 'chahta', 'chahti', 'kuch', 'koi', 'aur', 'ya', 'bhi', 'abhi', 'aaj', 'wali', 'wala', 'waali', 'jaankari', 'janakari', 'jaanna', 'janna', 'bato', 'bolo', 'batana', 'btana', 'information', 'info', 'details', 'detail', 'share', 'karo', 'kijiye', 'please', 'plz', 'pls', 'tell', 'about', 'give', 'show', 'latest', 'newest', 'taza', 'taaza',
    ];

    /** Model names that are also everyday words: only accepted together with the brand ("Honda City"). */
    private const COMMON = ['city', 'one', 'go', 'air', 'star', 'king', 'zest', 'tour', 'max', 'plus', 'pro', 'new', 'ev', 'sport', 'classic', 'super', 'dash', 'bolt', 'prime', 'ace'];

    private const NEWS = '/\b(news|khabar\w*|samachar|headlines?|articles?|blogs?)\b/i';
    private const SOFT_NEWS = '/\b(latest|taza|taaza|aaj ki|aaj ka|today\'?s|updates?|new updates?)\b/i';

    /** @return string[] lowercase letter/number tokens */
    public static function tokens(string $text): array
    {
        return preg_split('/[^\p{L}\p{N}]+/u', Str::lower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /** The message without chat filler: "muje aaj ki latest news do" -> "news", "tata siera ke bare me batao" -> "tata siera". */
    public static function clean(string $text): string
    {
        return implode(' ', array_values(array_filter(self::tokens($text), fn ($t) => ! in_array($t, self::FILLER, true))));
    }

    /**
     * News request: an explicit news word, or "latest / aaj ki / updates" when nothing else (a car, a model) is being asked about.
     */
    public static function wantsNews(string $norm, bool $aboutSomethingElse): bool
    {
        if (preg_match(self::NEWS, $norm)) return true;
        return ! $aboutSomethingElse && (bool) preg_match(self::SOFT_NEWS, $norm);
    }

    /** Cached "who is in the catalog" list. Cleared whenever a model is saved (see KnowledgeBase::sync). */
    private static function index(): array
    {
        return Cache::remember('asst:modelidx', 600, function () {
            $catalog = VehicleModel::published()->with('brandMaster:id,name')->get(['id', 'brand_id', 'name'])->map(function ($m) {
                $brand = Str::lower(Str::before((string) $m->brandMaster?->name, ' '));
                return ['id' => $m->id, 'name' => $m->name, 'brand' => $brand, 'compact' => implode('', self::tokens($m->name))];
            });
            // Models we only have as used cars (no catalog page) are known too: id = null.
            $seen = $catalog->pluck('compact')->flip();
            $used = Listing::active()->whereNotNull('model')->where('model', '!=', '')->select('brand_id', 'model')->distinct()->limit(600)->get()
                ->map(fn ($l) => ['id' => null, 'name' => $l->model, 'brand' => Str::lower(Str::before((string) $l->brandMaster?->name, ' ')), 'compact' => implode('', self::tokens($l->model))])
                ->unique('compact')->reject(fn ($r) => isset($seen[$r['compact']]));
            return $catalog->concat($used)->filter(fn ($r) => mb_strlen($r['compact']) >= 2)->values()->all();
        });
    }

    /**
     * The catalog models the message talks about, best match first (at most 3).
     * Matches the model name exactly, with a one-letter typo, or by sound when the brand is also named
     * ("tata siera", "Tata Syria" -> Tata Sierra; "Nexon price" -> Tata Nexon).
     *
     * @return array<int, array{id:int|null, name:string, exact:bool}>  id = null: a model we only have as used cars
     */
    public static function models(string $norm): array
    {
        $tokens = self::tokens($norm);
        if (! $tokens) return [];
        $grams = $tokens;                                                    // "xuv 700" is also tried as "xuv700"
        for ($i = 0; $i < count($tokens) - 1; $i++) {
            $grams[] = $tokens[$i].$tokens[$i + 1];
            if (isset($tokens[$i + 2])) $grams[] = $tokens[$i].$tokens[$i + 1].$tokens[$i + 2];
        }
        $grams = array_values(array_unique($grams));
        $set = array_flip($grams);

        $scored = [];
        foreach (self::index() as $row) {
            $c = $row['compact'];
            $brand = $row['brand'] !== '' && isset($set[$row['brand']]);
            $common = in_array($c, self::COMMON, true) || (mb_strlen($c) < 3 && ! preg_match('/\d/', $c));
            $score = 0; $exact = false;
            if (isset($set[$c])) { $score = 3; $exact = true; }
            elseif (mb_strlen($c) >= 5 && ! $common) {
                foreach ($grams as $g) {
                    $len = mb_strlen($g);
                    if (abs($len - mb_strlen($c)) > 2 || $len < 4) continue;
                    $lev = levenshtein($g, $c);
                    if ($lev <= 1 || ($len >= 8 && $lev <= 2)) { $score = 2; break; }
                    if ($brand && $g[0] === $c[0] && metaphone($g) === metaphone($c)) { $score = 1; break; }   // "syria" ~ "sierra"
                }
            }
            if ($score === 0 || ($common && ! ($exact && $brand))) continue;
            $scored[] = ['id' => $row['id'], 'name' => $row['name'], 'exact' => $exact, 'score' => $score + ($brand ? 1 : 0) + ($row['id'] ? 0.5 : 0)];
        }
        if (! $scored) return [];
        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);
        $best = $scored[0]['score'];
        return collect($scored)->filter(fn ($r) => $r['exact'] || $r['score'] >= $best)->take(3)->map(fn ($r) => ['id' => $r['id'], 'name' => $r['name'], 'exact' => $r['exact']])->values()->all();
    }
}
