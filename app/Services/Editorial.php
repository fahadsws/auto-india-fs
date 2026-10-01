<?php

namespace App\Services;

use App\Models\VehicleModel;
use App\Models\Category;
use App\Models\Setting;
use Illuminate\Support\Str;

/**
 * The writing desk. Turns one or more source reports into a single original article and runs a quality gate
 * (source-overlap, length, boilerplate phrases) before anything is published.
 *
 * Honest by design: the prompt forbids invented quotes, invented specs and fake first-hand "we drove it" claims.
 * "Human feel" here means genuine editorial craft - concrete Indian context, varied rhythm, a point of view -
 * not tricks to fool AI detectors.
 */
class Editorial
{
    private const ANGLES = [
        'news_brief' => 'A tight, punchy news report (300-420 words). Lead with the single most important fact, then what changed, then what happens next.',
        'explainer' => 'An explainer (420-600 words): open with the news, then use 2-3 descriptive <h2> sections that answer the questions a curious buyer would actually ask.',
        'buyer_angle' => 'A buyer-focused piece (400-550 words): who this matters to, what it costs in real Indian terms, and whether waiting or buying now makes more sense.',
        'analysis' => 'A short analysis (450-600 words): place the news in the market - rivals, pricing pressure, segment trends - and take a clear, reasoned position.',
    ];

    /**
     * @param  array<int,array{title:string,text:string,outlet:string,url:string}>  $sources
     * @param  string|null  $mode  'faithful' (same facts and meaning, new wording - default) or 'original' (free editorial angle). Setting: news.rewrite_mode
     * @return array{data:array,similarity:int}|array{error:string}
     */
    public static function write(array $sources, string $topicHint = '', ?string $mode = null): array
    {
        $mode = $mode ?: (string) Setting::get('news.rewrite_mode', 'faithful');
        $faithful = $mode !== 'original';
        $angleKey = array_rand(self::ANGLES);
        $cats = Category::where('is_active', true)->pluck('name')->implode(', ') ?: 'Car News';
        $known = VehicleModel::published()->orderByDesc('updated_at')->limit(60)->get()->map->full_name->implode(', ');
        $srcText = collect($sources)->values()->map(fn ($s, $i) => '[Source '.($i + 1).' - '.$s['outlet']."]\nHeadline: {$s['title']}\n".Str::limit($s['text'], 6000, ''))->implode("\n\n");
        $plainSources = collect($sources)->pluck('text')->push(...collect($sources)->pluck('title'))->all();
        $srcWords = max(array_map(fn ($t) => TextTools::wordCount($t), $plainSources));
        $target = (int) max(250, min(700, $srcWords * 0.85));
        $minWords = $faithful ? (int) max(100, min(400, 0.5 * min($srcWords, 800))) : 260;

        $system = $faithful ? self::faithfulPrompt($target, $cats, $known) : self::systemPrompt($angleKey, $cats, $known);
        $temperature = $faithful ? 0.35 : 0.85;
        $attempt = 0; $note = ''; $aiFails = 0; $check = null;
        do {
            $data = AiClient::json([
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $srcText.($topicHint ? "\n\nEditor's note: $topicHint" : '').$note],
            ], ['temperature' => $temperature, 'max_tokens' => 4000, 'timeout' => 120]);

            if (! $data || empty($data['title']) || empty($data['body_html'])) {
                // Transient model failure (rate limit, cut-off JSON): try once more before giving up on this story.
                if (++$aiFails < 2) { sleep(2); continue; }
                return ['error' => 'AI returned no usable article ('.(AiClient::lastError() ?? 'empty reply').')'];
            }
            if (($data['india_relevant'] ?? true) === false) return ['error' => 'Not relevant to Indian readers'];

            $body = self::cleanHtml($data['body_html']);
            $words = TextTools::wordCount($body);
            $overlap = TextTools::overlap($body, $plainSources);
            $cliches = TextTools::clicheCount(TextTools::plain($body));
            $maxOverlap = $faithful ? 30 : 18;

            $problems = [];
            if ($overlap > $maxOverlap) $problems[] = "about $overlap% of your phrasing matches the sources - re-express every sentence in clearly different words and sentence structure (names, model names, numbers and spec terms excepted)";
            if ($words < $minWords) $problems[] = "it is only $words words - cover every fact in the sources (at least $minWords words)";
            if (! $faithful && $cliches > 1) $problems[] = 'it contains stock phrases (e.g. "in conclusion", "game-changer", "when it comes to") - remove them';
            if ($faithful) {
                $outText = TextTools::plain($body).' '.($data['title'] ?? '').' '.implode(' ', (array) ($data['tldr'] ?? [])).' '.implode(' ', array_map(fn ($f) => ($f['q'] ?? '').' '.($f['a'] ?? ''), (array) ($data['faq'] ?? [])));
                $check = TextTools::factCheck($outText, implode("\n", $plainSources));
                if (count($check['invented']) > 1) $problems[] = 'it contains figures that are not in the sources ('.implode(', ', array_slice($check['invented'], 0, 6)).') - remove or correct them; never add facts';
                if ($check['coverage'] < 0.6) $problems[] = 'it dropped important figures from the sources ('.implode(', ', array_slice($check['missing'], 0, 8)).') - keep every price, date, spec and number';
            }

            if (! $problems) { $data['body_html'] = $body; return ['data' => $data, 'similarity' => $overlap]; }
            $note = "\n\nREVISION REQUIRED: your previous draft was rejected because ".implode('; ', $problems).'. Write a new, better draft now.';
        } while (++$attempt < 2);

        // After one revision accept only if it is distinct enough, long enough and (faithful mode) not inventing facts.
        $lenient = $faithful ? ($overlap <= 40 && $words >= $minWords * 0.8 && count($check['invented'] ?? []) <= 1 && ($check['coverage'] ?? 1) >= 0.5) : ($overlap <= 30 && $words >= 220);
        if ($lenient) { $data['body_html'] = $body; return ['data' => $data, 'similarity' => $overlap]; }
        return ['error' => "Quality gate failed (overlap $overlap%, $words words".($faithful ? ', '.count($check['invented'] ?? []).' invented figure(s), '.round(($check['coverage'] ?? 1) * 100).'% of figures kept' : '').')'];
    }

    private static function faithfulPrompt(int $target, string $cats, string $known): string
    {
        return <<<TXT
You are a sub-editor at Automobil India, an Indian car news site. You are given one or more reports on the same story. Produce ONE rewritten news article that carries exactly the same information and meaning as the reports, in entirely new wording.

RULES
1. Same meaning, every fact: keep every material fact - what happened, who, when, where, prices (keep the currency and the lakh/crore style), dates, specs, figures, model and variant names, and who said what. Copy numbers and units exactly as given; do not convert units or round. Do not leave out important details.
2. Nothing added: no facts, numbers, opinions, predictions, rivals or background that are not in the reports. If reports disagree, say "reports differ" and give both. If a detail is unclear, leave it out or say it is not confirmed.
3. New wording: rewrite every sentence with different vocabulary and sentence structure; do not copy phrases of five or more words (names, model names, spec terms and numbers are exempt). Turn direct quotes into reported speech ("the company said it will ...") and never invent a quote.
4. Structure: lead with the most important fact in the first sentence, then follow the order of the reports, merging overlapping details from several reports into one flow. Neutral, clear newsroom tone. No first-person claims (we drove, we attended), no marketing language, no stock phrases.
5. Length: about {$target} words - as long as it takes to cover all facts, no padding. Use only <p>, <h2>, <h3>, <ul>, <li>, <strong>, <em>. Add 1-2 short <h2> subheads only if the story is long. Do not include the title as a heading.
6. The title must state the same news as the source headline in different words (specific, under 90 characters, not clickbait). The FAQ must only use facts from the reports.

If the story is about a specific car model being launched, facelifted, repriced or spec-updated, fill "car"; otherwise set "car" to null. Known models on our site (reuse these exact names when the story is about one): {$known}

OUTPUT: ONE JSON object, no markdown fences, with keys:
"title", "excerpt" (max 200 chars), "tldr" (array of exactly 3 short takeaway strings), "body_html", "faq" (array of 3 objects {"q","a"} answered only from the reports), "meta_title" (max 60 chars), "meta_description" (max 155 chars, includes the key fact), "category" (exactly one of: {$cats}), "tags" (array of 4-6 lowercase strings), "india_relevant" (boolean - false if the story has no relevance to the Indian market),
"car": null or {"brand","name" (model name without brand),"event" (one of launch, facelift, price_update, spec_update, teaser, review, other),"status" (upcoming, launched or facelift),"body_type","fuel_types" (array),"price_min_lakh" (number or null),"price_max_lakh" (number or null),"launch_date" (YYYY-MM-DD or null),"specs" (object of label -> value, only facts stated in the reports),"rivals" (array)}
TXT;
    }

    private static function systemPrompt(string $angleKey, string $cats, string $known): string
    {
        $angle = self::ANGLES[$angleKey];
        $cliches = implode('", "', array_slice(TextTools::CLICHES, 0, 12));

        return <<<TXT
You are a staff writer at Automobil India, an Indian car news site. You are given one or more reports on the same story. Write ONE original article for Indian car buyers.

FORMAT FOR THIS PIECE: {$angle}

RULES
1. Originality: use the sources only as fact material. Never reuse their sentences, distinctive phrasing or paragraph order. Combine what different sources say into one better-organised story.
2. Accuracy: every number, price, date, spec and model name must come from the sources. If sources disagree or something is unknown, say "not yet confirmed" - never guess. No invented quotes. No claims that we drove, tested or attended anything.
3. Voice: write like an experienced Indian auto journalist talking to a smart friend. Vary sentence length; short sentences are fine. Use contractions. Add concrete Indian context where the sources support it (city traffic, festive-season launches, rivals people cross-shop, running costs, service network). A point of view is welcome, clearly framed as analysis ("Our read:", "The catch:"), never as fake first-hand experience.
4. Craft: no generic intro; don't open every sentence with the car's name; headings must be specific to this story, not templated. Avoid stock phrases such as "{$cliches}".
5. Answer-engine friendly: put the key facts (what, price in INR, when, who it rivals) in the first 2 sentences and again in a short "Quick facts" <ul>. Write the FAQ as questions real people type into a search box or ask an AI assistant, each answered in 1-3 self-contained sentences.
6. Length and HTML: use only <p>, <h2>, <h3>, <ul>, <li>, <strong>, <em>. Do not include the title as a heading.

If the story is about a specific car model that is being launched, facelifted, repriced or spec-updated, fill "car"; otherwise set "car" to null. Known models on our site (reuse these exact names when the story is about one of them): {$known}

OUTPUT: ONE JSON object, no markdown fences, with keys:
"title" (specific, under 90 chars, not clickbait), "excerpt" (max 200 chars), "tldr" (array of exactly 3 short takeaway strings), "body_html", "faq" (array of 3-4 objects {"q","a"}), "meta_title" (max 60 chars), "meta_description" (max 155 chars, includes the key fact), "category" (exactly one of: {$cats}), "tags" (array of 4-6 lowercase strings), "india_relevant" (boolean - false if the story has no relevance to the Indian market),
"car": null or {"brand","name" (model name without brand),"event" (one of launch, facelift, price_update, spec_update, teaser, review, other),"status" (upcoming, launched or facelift),"body_type","fuel_types" (array),"price_min_lakh" (number or null),"price_max_lakh" (number or null),"launch_date" (YYYY-MM-DD or null),"specs" (object of label -> value, only facts stated in the sources),"rivals" (array)}
TXT;
    }

    public static function cleanHtml(string $html): string
    {
        $html = preg_replace('#<(script|style|iframe)[^>]*>.*?</\1>#is', '', $html);
        $html = strip_tags($html, '<p><h2><h3><ul><ol><li><strong><em><a><br>');
        return trim(preg_replace('/\son\w+="[^"]*"/i', '', $html));
    }

    /**
     * Sanitise HTML written by an editor in the admin (TinyMCE). Allows images/tables/figures, and turns any
     * embedded base64 image into a real file so the database never stores image bytes.
     */
    public static function cleanEditorHtml(?string $html): string
    {
        $html = self::externalizeImages((string) $html);
        foreach (['script', 'style', 'iframe', 'object', 'embed'] as $tag) $html = preg_replace('#<'.$tag.'\b[^>]*>.*?</'.$tag.'>#is', '', $html);
        $html = strip_tags($html, '<p><h2><h3><h4><ul><ol><li><strong><em><b><i><u><s><a><br><blockquote><img><figure><figcaption><table><thead><tbody><tr><th><td><hr><video><source><span><div>');
        $html = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $html);
        return trim(preg_replace('/(href|src)\s*=\s*(["\'])\s*javascript:[^"\']*/i', '$1=$2#', $html));
    }

    /** Save <img src="data:image/...;base64,..."> payloads to disk and swap in the file URL. */
    public static function externalizeImages(string $html): string
    {
        if (! str_contains($html, 'data:image/')) return $html;
        return preg_replace_callback('#data:image/(png|jpe?g|gif|webp|avif);base64,([A-Za-z0-9+/=\s]+)#i', function ($m) {
            $bin = base64_decode(preg_replace('/\s+/', '', $m[2]), true);
            if ($bin === false || strlen($bin) > 10 * 1024 * 1024) return '';
            $ext = strtolower($m[1]) === 'jpeg' ? 'jpg' : strtolower($m[1]);
            $path = 'editor/'.date('Y/m').'/'.\Illuminate\Support\Str::random(24).'.'.$ext;
            \Illuminate\Support\Facades\Storage::disk('public')->put($path, $bin);
            return parse_url(asset('storage/'.$path), PHP_URL_PATH);
        }, $html);
    }

    /** Link the first mention of each known car model to its page (internal linking for SEO). */
    public static function autoLink(string $html, ?int $exceptCarId = null): string
    {
        $cars = VehicleModel::published()->when($exceptCarId, fn ($q) => $q->where('id', '!=', $exceptCarId))->get();
        if ($cars->isEmpty() || trim($html) === '') return $html;

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?><div id="root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $xp = new \DOMXPath($dom);

        foreach ($cars->sortByDesc(fn ($c) => strlen($c->full_name)) as $car) {
            foreach ([$car->full_name, $car->name] as $needle) {
                if (mb_strlen($needle) < 5) continue;
                foreach ($xp->query('//div[@id="root"]//text()[not(ancestor::a) and not(ancestor::h2) and not(ancestor::h3)]') as $node) {
                    $pos = mb_stripos($node->nodeValue, $needle);
                    if ($pos === false) continue;
                    $before = mb_substr($node->nodeValue, 0, $pos);
                    $match = mb_substr($node->nodeValue, $pos, mb_strlen($needle));
                    $after = mb_substr($node->nodeValue, $pos + mb_strlen($needle));
                    $a = $dom->createElement('a'); $a->appendChild($dom->createTextNode($match)); $a->setAttribute('href', $car->url);
                    $parent = $node->parentNode;
                    $parent->insertBefore($dom->createTextNode($before), $node);
                    $parent->insertBefore($a, $node);
                    $parent->insertBefore($dom->createTextNode($after), $node);
                    $parent->removeChild($node);
                    break 2;
                }
            }
        }
        $root = $xp->query('//div[@id="root"]')->item(0) ?? $dom->documentElement;
        $out = '';
        foreach ($root->childNodes as $c) $out .= $dom->saveHTML($c);
        return $out ?: $html;
    }
}
