<?php

namespace App\Services;

class TextTools
{
    /** Phrases that make copy read as boilerplate; the writer is told to avoid them and the gate counts them. */
    public const CLICHES = [
        'in conclusion', 'delve', 'game-changer', 'game changer', 'in the ever-evolving', 'ever-evolving', 'tapestry', 'testament to', 'landscape of',
        'it is worth noting', "it's worth noting", 'in today\'s fast-paced', 'unleash', 'elevate your', 'stands as a', 'a symphony of', 'navigate the', 'embark on',
        'revolutionize', 'seamlessly', 'look no further', 'when it comes to', 'at the end of the day', 'buckle up', 'without further ado',
    ];

    public static function plain(string $html): string
    {
        $t = html_entity_decode(strip_tags(preg_replace('#</(p|h\d|li|div|br)>#i', "\n", $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/[ \t]+/', ' ', preg_replace('/\n{3,}/', "\n\n", $t)));
    }

    public static function words(string $text): array
    {
        return preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    public static function wordCount(string $html): int
    {
        return count(self::words(self::plain($html)));
    }

    /** % of the output's 5-word sequences that also appear in any source text (0-100). */
    public static function overlap(string $output, array $sources, int $n = 5): int
    {
        $out = self::words(self::plain($output));
        if (count($out) < $n + 1) return 0;
        $seen = [];
        foreach ($sources as $s) {
            $w = self::words($s);
            for ($i = 0, $c = count($w) - $n; $i <= $c; $i++) $seen[implode(' ', array_slice($w, $i, $n))] = true;
        }
        $hit = 0; $total = count($out) - $n + 1;
        for ($i = 0; $i < $total; $i++) if (isset($seen[implode(' ', array_slice($out, $i, $n))])) $hit++;
        return (int) round($hit / max(1, $total) * 100);
    }

    public static function clicheCount(string $text): int
    {
        $t = mb_strtolower($text); $n = 0;
        foreach (self::CLICHES as $c) $n += substr_count($t, $c);
        return $n;
    }

    /** Headline similarity: shared significant words relative to the shorter headline (0-1). */
    public static function titleSimilarity(string $a, string $b): float
    {
        $f = fn ($t) => array_values(array_unique(array_filter(self::words($t), fn ($w) => mb_strlen($w) > 3)));
        $x = $f($a); $y = $f($b);
        if (count($x) < 3 || count($y) < 3) return 0.0;
        $common = count(array_intersect($x, $y));
        return $common >= 3 ? $common / min(count($x), count($y)) : 0.0;
    }
    /**
     * Do two items describe the same story, even when the outlets word it differently? (0-1)
     * Headline overlap alone misses "Sierra EV launched at ₹21 lakh" vs "Tata's electric SUV debuts, starts Rs 21 lakh", so a
     * shared model/brand name plus shared hard numbers (prices, cc, dates) also counts as the same story.
     */
    public static function storySimilarity(string $titleA, string $titleB, string $textA = '', string $textB = ''): float
    {
        $score = self::titleSimilarity($titleA, $titleB);
        $sig = fn ($t) => array_values(array_unique(array_filter(self::words($t), fn ($w) => mb_strlen($w) > 3 && ! ctype_digit($w))));
        $facts = fn ($t) => array_keys(array_filter(self::numberFacts($t), fn ($v, $k) => ! preg_match('/^(19|20)\d{2}$/', (string) $k), ARRAY_FILTER_USE_BOTH));
        $words = count(array_intersect($sig($titleA), $sig($titleB)));
        $x = $facts($titleA.' '.mb_substr($textA, 0, 1500)); $y = $facts($titleB.' '.mb_substr($textB, 0, 1500));
        $shared = count(array_intersect($x, $y));
        if ($words >= 2 && $shared >= 1 && $shared / max(1, min(count($x), count($y))) >= 0.3) $score = max($score, 0.65);
        return $score;
    }

    /**
     * The set of numeric facts in a text, normalised so equivalent spellings match:
     * "₹10,50,000" = "10.5 lakh" = 1050000, "1,199 cc" = 1199, "12.0" = 12. Single-digit integers are ignored as noise.
     *
     * @return array<string,true>
     */
    public static function numberFacts(string $text): array
    {
        $t = mb_strtolower($text);
        $facts = [];
        $t = preg_replace_callback('/(\d[\d,]*(?:\.\d+)?)\s*(lakhs?|lacs?|crores?|cr)\b/u', function ($m) use (&$facts) {
            $n = (float) str_replace(',', '', $m[1]);
            $mult = str_starts_with($m[2], 'cr') ? 10000000 : 100000;
            $facts[(string) round($n * $mult)] = true;
            return ' ';
        }, $t);
        $t = preg_replace('/(?<=\d),(?=\d)/', '', $t);
        preg_match_all('/\d+(?:\.\d+)?/', $t, $m);
        foreach ($m[0] as $n) {
            $n = str_contains($n, '.') ? rtrim(rtrim($n, '0'), '.') : ltrim($n, '0');
            if ($n === '' || (! str_contains($n, '.') && strlen($n) < 2)) continue;
            $facts[$n] = true;
        }
        return $facts;
    }

    /**
     * Compare a rewrite against its sources.
     *
     * @return array{invented:array<int,string>,missing:array<int,string>,coverage:float}
     */
    public static function factCheck(string $output, string $sources): array
    {
        $out = self::numberFacts($output); $src = self::numberFacts($sources);
        $invented = array_keys(array_diff_key($out, $src));
        $missing = array_keys(array_diff_key($src, $out));
        $coverage = $src ? 1 - count($missing) / count($src) : 1.0;
        return ['invented' => $invented, 'missing' => $missing, 'coverage' => $coverage];
    }
}
