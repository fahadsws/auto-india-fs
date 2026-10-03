<?php

namespace App\Services;

use App\Models\AssistantSession;
use App\Models\ChatLog;
use App\Models\Setting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Site assistant. Per message it decides between:
 *  - answering from OUR database / content (stock, prices, counts, pages, business facts) and showing cards,
 *  - running the sales flow (ask city -> show cars -> select -> book a test drive / inspection),
 *  - opening a site page (show-all), or
 *  - plain conversation where the AI answers on its own.
 * Conversation state lives in AssistantMemory (a few compact lines) instead of long chat history, so the AI never
 * forgets what was asked or which car was chosen, and every request stays small. See AssistantGuard for limits.
 */
class Assistant
{
    /** Words that mean "show me something from the website". */
    private const SITE_WORDS = '/\b(price|prices|pricing|cost|on[- ]?road|ex[- ]?showroom|emi|variants?|used cars?|second[- ]?hand|listings?|for sale|in stock|stock|news|articles?|videos?|reviews?|launch(es|ed)?|upcoming|compare|comparison|brochure|test ?drive|book(ing)?|dealers?|showrooms?|contact|address|phone|email|timings?|hours|opening|working hours|where are you|location|services?|offers?|warranty|finance|loan|exchange|insurance|about (you|us)|your (site|website|cars?|stock|team)|(on|from) (your|this) (site|website)|show me|list of)\b/i';

    /** Questions about the business itself (contact, hours, services...). */
    private const ABOUT_WORDS = '/\b(contact|address|phone|email|timings?|hours|opening|working hours|where are you|location|services?|offers?|warranty|finance|loan|exchange|insurance|about (you|us)|who are you|your (team|company|business))\b/i';

    private const SPECIFIC = '/\b(price|prices|pricing|cost|on[- ]?road|ex[- ]?showroom|emi|variants?|reviews?|compare|comparison|brochure|news|videos?|dealers?|contact|mileage|features?|specs?)\b/i';
    private const TEST_DRIVE = '/\b(test[- ]?drive|testdrive|drive (karni|karna|krni|krna|lena|leni|dekhni|dekhna)|take a drive)\b/i';
    private const INSPECTION = '/\b(inspection|inspect|check(ing)? (karwana|karana|karvana|krwana)|physical check|dekhne ?(aana|aunga|ana|jana|aana hai)|site visit|showroom visit|visit)\b/i';
    private const SHOW_ALL = '/\b(sare|saare|saari|sabhi|poori|puri)\b|\b(show|see|view|open|browse|list|dikhao|dikha|dikhana|dekhna|dekhao)\b.*\b(all|every|full list|whole)\b|\b(all|every)\b.*\b(cars?|bikes?|trucks?|listings?|stock|gaa?d(i|iyan))\b.*\b(show|dikhao|dikha|open|list)\b/i';
    /** A real request to book ("book a test drive", "kal test drive chahiye"), as opposed to a question about test drives. */
    private const BOOK_CUE = '/\b(book|schedule|arrange|fix|want|wanna|need|chahiye|chaiye|chahie|karni|karna|krna|krni|karwa\w*|karao|lena|leni|please|can i|could i|kal|aaj|parso|today|tomorrow|monday|tuesday|wednesday|thursday|friday|saturday|sunday|weekend)\b/i';
    private const DECLINE = '/\b(never ?mind|cancel|rehne do|chhodo|chodo|baad me|later|not now|abhi nahi|nahi chahiye|no thanks|mat karo)\b/i';
    private const BUY = '/\b(chahiye|chaiye|chahie|chahta|chahti|buy|kharid|kharidna|lena|leni|looking for|want|need|dikhao|dikha|show|interested)\b/i';
    private const HINDI = '/[\x{0900}-\x{097F}]|\b(mujhe|muje|mere|mera|meri|chahiye|chaiye|chahie|kya|kaun|kitna|kitne|nahi|nhi|haan|aap|apna|mein|mai|hai|hain|karna|karni|dikhao|dikha|sare|saare|gaadi|gadi|batao|bataiye|wali|wala|purani|nayi)\b/iu';

    /** @return array{answer:string, source:string, links:array, actions?:array, captured?:array, lang?:string, cached?:bool} */
    public function reply(string $message, array $history, AssistantSession $session, string $ip, bool $voice = false, bool $degraded = false): array
    {
        $message = Str::limit(trim(preg_replace('/\s+/', ' ', $message)), AssistantGuard::limit('max_input') ?: 300, '');
        $norm = HindiText::normalize($message);          // Devanagari -> English words, for filters / intent rules / knowledge search
        $m = AssistantMemory::get($session);
        // No default language: the AI replies in whatever language the visitor writes (see messages()). $hi only picks the wording of the
        // few built-in fallback lines (used when the AI is off), and follows THIS message; an ambiguous one ("Pune") keeps the last choice.
        if (HindiText::has($message) || preg_match(self::HINDI, $message)) $m['hi'] = true;
        elseif (HindiText::isEnglish($message) || ! isset($m['hi'])) $m['hi'] = false;
        $hi = (bool) $m['hi'];
        $none = ['mode' => 'ai', 'items' => []];

        // 1) Zero-cost local answers (small talk, abuse).
        if ($local = $this->local($norm, $session, $m, $hi)) {
            $m['turns']++; AssistantMemory::save($session, $m);
            return $this->finish($session, $ip, $message, $local, 'none', [], $voice, 0, 0, true, $none, ['lang' => $hi ? 'hi' : 'en']);
        }

        // 2) Understand the message in the context of the conversation.
        if (AssistantMemory::wantsReset($norm)) $m = ['hi' => $hi, 'turns' => $m['turns']] + AssistantMemory::get(new AssistantSession());
        $parsed = SiteData::parse($norm);
        $clean = QueryIntent::clean($norm);                                                  // "muje aaj ki latest news do" -> "news"
        $models = QueryIntent::models($norm);                                                // a named car, typos included ("tata siera")
        $news = QueryIntent::wantsNews($norm, (bool) ($parsed['_vehicle_word'] || $models));
        $answered = $this->absorbAnswer($m, $message, $norm, $parsed, (bool) $models);
        $m['ask'] = null;   // a question applies to the very next message only
        $ref = $models ? null : AssistantMemory::resolveReference($norm, $m);
        if ($ref) $m['focus'] = $ref;
        $filters = AssistantMemory::merge($m['f'], $parsed);

        // A booking in progress ends when the visitor cancels, moves to news or to another car, or lets it go cold.
        if (! empty($m['flow'])) {
            $cold = ($m['turns'] - ($m['flow']['t'] ?? $m['turns'])) > 8;
            if ($cold || preg_match(self::DECLINE, $norm) || $news || ($models && ! preg_match(self::TEST_DRIVE, $norm) && ! preg_match(self::INSPECTION, $norm))) { $m['flow'] = null; $m['missing'] = null; }
        }

        $siteWords = (bool) preg_match(self::SITE_WORDS, $norm);
        $kinds = count(array_intersect_key($parsed, array_flip(['price_min', 'price_max', 'fuel', 'transmission', 'city', 'brand_id', 'body_id', 'year', 'year_min', 'year_max', 'km_max', 'owner', 'status'])));
        $short = count(preg_split('/\s+/', $message)) <= 5 && ! str_contains($message, '?');
        $searchActive = ! empty($filters['type']) || ! empty($filters['vehicle']) || ! empty($m['f']);
        $refine = $searchActive && ($answered || ($kinds > 0 && $short));
        $showAll = ($parsed['_vehicle_word'] || ! empty($m['f'])) && preg_match(self::SHOW_ALL, $norm) && ($searchActive || $parsed['_vehicle_word']);
        // Only a REQUEST starts a booking; "does the Nexon have a test drive?" is just a question and gets a normal answer.
        $bookWord = preg_match(self::TEST_DRIVE, $norm) ? 'test_drive' : (preg_match(self::INSPECTION, $norm) ? 'inspection' : null);
        $bookKind = $bookWord && (! empty($m['flow']) || preg_match(self::BOOK_CUE, $norm)) ? $bookWord : null;
        $direct = SiteData::wanted($parsed, $siteWords);
        $run = $direct || $refine || $showAll || ($parsed['_vehicle_word'] && ! empty($filters['type']));
        $actions = []; $nudge = []; $askKey = null;

        // A named car or a news request is answered from that car / the newest articles, not from a brand-wide or relevance-ranked list.
        $catalog = array_values(array_filter($models, fn ($x) => $x['id']));                  // models with a catalog page (the rest are used-only)
        if (count($catalog) === 1 && ($m['focus']['k'] ?? '') === 'c' && ($m['focus']['id'] ?? 0) !== $catalog[0]['id']
            && preg_match('/\b(compar\w*|vs|versus|difference|farak|fark|better|behtar)\b/i', $norm) && preg_match('/\b(iska|iske|iski|isse|ise|isko|its|it|this one|this)\b/i', $norm)) {
            array_unshift($catalog, ['id' => $m['focus']['id'], 'name' => $m['focus']['t'], 'exact' => true]);   // "this one" = the car we were just discussing
        }
        $special = ! $bookKind && ($news || ($catalog && ($filters['type'] ?? null) !== 'used'));
        if ($models && ! $special) {                                                          // "Nexon ki test drive" / "used Nexon": the named car is the subject
            if ($catalog) $filters['model_ids'] = array_column($catalog, 'id'); else { $filters['model_like'] = $models[0]['name']; $run = true; }
            if ($bookKind && $catalog && ($mdl = \App\Models\VehicleModel::published()->find($catalog[0]['id']))) $ref = AssistantMemory::item($mdl);
        }
        if ($bookKind && $models && ! $catalog && ! $ref) {                                   // booking a used model by name: the matching cars become the numbered list
            $pre = SiteData::lookup(['model_like' => $models[0]['name'], 'type' => 'used']);
            if ($pre['items']) { $m['shown'] = $pre['items']; if (count($pre['items']) === 1) $ref = $pre['items'][0]; }
        }
        if ($special) { $run = false; $showAll = false; }

        // 3) The AI talks the visitor through a test drive / inspection; these hints tell it where the conversation is.
        if ($bookKind) {
            $run = false; $showAll = false;                                                 // keep the numbered car list stable while booking
            if ($models && ! $ref) $m['focus'] = null;                                      // a car was named but is not unique: never fall back to an older pick
            $target = $ref ?? $m['focus'] ?? (count($m['shown']) === 1 ? $m['shown'][0] : null);
            $label = $bookKind === 'test_drive' ? 'test drive' : 'inspection';
            if ($target) {
                $m['focus'] = $target; $m['stage'] = 'interested';
                $m['flow'] = ['kind' => $bookKind, 'car' => $target['t'], 't' => $m['turns']];
                $nudge[] = "The visitor wants a $label for {$target['t']}. Collect what is still missing, one question at a time (date within the next 30 days, time slot, showroom or their address), then repeat it back and ask them to confirm.";
            } elseif ($m['shown']) {
                $m['flow'] = ['kind' => $bookKind, 'car' => null, 't' => $m['turns']];
                $nudge[] = "The visitor wants a $label but has not chosen the car. Ask which one of the cars shown (by number or name).";
            } else {
                $m['flow'] = ['kind' => $bookKind, 'car' => null, 't' => $m['turns']];
                $nudge[] = "The visitor wants a $label but we do not know the car yet. Ask which car, or their city and budget so you can suggest some.";
            }
        } elseif ($ref && ! $run && ! $siteWords) {
            $m['stage'] = 'interested';
            $nudge[] = "They just picked {$ref['t']}. Answer what they said about it; mention a test drive only if it fits naturally.";
        }

        // 4) Sales funnel: a generic request ("mujhe used car chahiye") gets one quick question first, not a random list.
        if ($run && ! $showAll && empty($parsed['count']) && empty($parsed['sort'])) {
            $generic = $kinds === 0 && ! preg_match(self::SPECIFIC, $norm);   // a bare "I want a used car", not a price/news/compare question
            $asked = $m['asked'] ?? [];
            if ($generic && ($filters['type'] ?? null) === 'used' && empty($filters['city']) && empty($filters['city_any']) && ! in_array('city', $asked, true)) {
                $m['f'] = $this->clean($filters); $m['ask'] = 'city'; $m['asked'][] = 'city'; $run = false; $askKey = 'ask_city';
                $nudge[] = 'Ask ONE short question: which city or area are they looking in (they may say anywhere). Do not list cars yet.';
            } elseif ($generic && ($filters['type'] ?? null) === 'new' && ! isset($filters['price_max']) && ! isset($filters['price_min']) && empty($filters['brand_id']) && empty($filters['body_id']) && empty($filters['budget_any']) && ! in_array('budget', $asked, true)) {
                $m['f'] = $this->clean($filters); $m['ask'] = 'budget'; $m['asked'][] = 'budget'; $run = false; $askKey = 'ask_budget';
                $nudge[] = 'Ask ONE short question: roughly what budget do they have in mind. Do not list cars yet.';
            }
        }
        if (! $run && ! $askKey && ! $bookKind && empty($filters['type']) && $parsed['_vehicle_word'] && preg_match(self::BUY, $norm) && $kinds === 0 && ! preg_match(self::SPECIFIC, $norm) && ! in_array('type', $m['asked'] ?? [], true)) {
            $m['ask'] = 'type'; $m['asked'][] = 'type'; $askKey = 'ask_type';
            $nudge[] = 'Ask ONE short question: are they looking for a new car or a used one.';
        }

        // 5) Look it up in OUR database (stock, prices, counts) and in our content.
        $db = null; $info = null;
        if ($special) {
            $parts = [];
            if ($catalog && ($info = SiteData::modelInfo($catalog))) $parts[] = $info;
            if ($news) $parts[] = SiteData::news($models ? ($models[0]['name'] ?? null) : ($parsed['brand'] ?? null));
            if ($parts) {
                $db = ['context' => collect($parts)->pluck('context')->implode("\n\n"), 'links' => collect($parts)->pluck('links')->flatten(1)->take(3)->values()->all(),
                    'brief' => collect($parts)->pluck('brief')->filter()->implode("

"), 'sources' => collect($parts)->pluck('sources')->flatten(1)->all(), 'found' => count($info['items'] ?? []), 'items' => $info['items'] ?? [], 'totals' => ['used' => 0, 'new' => 0]];
                if ($info) {
                    $m['shown'] = $info['items']; $m['stage'] = 'interested'; $m['ask'] = null; $m['topic'] = null;   // a named car replaces the old topic
                    if (count($info['items']) === 1) $m['focus'] = $info['items'][0];
                }
            }
        } elseif ($run) {
            $db = SiteData::lookup($filters);
        }
        if ($db && ! $special) {
            $m['f'] = $this->clean($filters); $m['shown'] = $db['items']; $m['stage'] = $db['found'] ? 'shortlist' : $m['stage']; $m['ask'] = null;
        }

        // 6) "Show me all ..." -> open the real, filtered page.
        if ($showAll && $db) {
            $kindKey = (($filters['type'] ?? null) === 'new' || ($filters['vehicle'] ?? 'car') !== 'car') ? 'new' : 'used';
            $total = $db['totals'][$kindKey] ?: max($db['totals']);
            if ($total > 0) {
                $url = SiteData::browseUrl($filters);
                $label = $this->label($filters, $kindKey, $hi);
                $m['turns']++;
                return $this->done($session, $ip, $message, $this->t('opening', $hi, ['n' => $total, 'what' => $label]), 'kb', $db['links'], $voice, $m, ['mode' => 'database', 'items' => $db['sources']], [['type' => 'navigate', 'url' => $url, 'label' => $this->t('open_label', $hi), 'count' => $total, 'auto' => true]]);
            }
        }

        $search = $clean !== '' ? KnowledgeBase::correct(collect(explode(' ', $clean)))->implode(' ') : $norm;     // spelling fixed against our own titles
        $hits = $askKey ? collect() : KnowledgeBase::search($search, 4);                              // a pure question needs no site data
        $about = preg_match(self::ABOUT_WORDS, $norm) ? KnowledgeBase::siteInfo() : null;          // business details: contact, hours, services...
        // Our own dynamic pages are part of the assistant's knowledge: a page that clearly matches is used even without "site words".
        $hits = $hits->isNotEmpty() ? $hits->filter(fn ($h) => $siteWords || $this->titleMatches(collect([$h]), $search) || ($h->type === 'webpage' && $this->pageMatches($h, $search)))->values() : $hits;
        if ($db && $info) $hits = $hits->reject(fn ($h) => $h->type === 'car' && in_array($h->ref_id, array_column($catalog, 'id'), true))->values();   // already in the data block
        if ($db && ! $this->titleMatches($hits, $search)) $hits = $hits->reject(fn ($h) => $h->type !== 'webpage')->values();   // the database answer is the main source
        if ($about) $hits = $hits->reject(fn ($h) => $h->id === $about->id)->prepend($about)->take(3)->values();
        $hits = $hits->take(3)->values();
        if (! $db && $hits->isNotEmpty() && $this->titleMatches($hits->take(1), $search)) $m['topic'] = Str::limit((string) $hits[0]->title, 90, '');   // so "iska / more about it" has something to refer to

        $site = $db !== null || $hits->isNotEmpty();
        $ctx = trim(($db['context'] ?? '').($hits->isNotEmpty() ? ($db ? "\n\n" : '').$hits->map(fn ($h, $i) => '['.($i + 1)."] ({$h->type}) {$h->title}\n".Str::limit($h->content, in_array($h->type, ['car', 'listing'], true) ? 700 : ($h->type === 'webpage' ? 1100 : 450), '…'))->implode("\n\n") : ''));
        $links = $db['links'] ?? $hits->take(3)->map(fn ($h) => ['title' => $h->title, 'url' => KnowledgeBase::absolute($h->url), 'image' => KnowledgeBase::absolute($h->image), 'type' => $h->type] + (in_array($h->type, ['car', 'listing'], true) ? ['k' => $h->type === 'car' ? 'c' : 'l', 'id' => $h->ref_id] : []))->values()->all();
        $sources = $site ? ['mode' => $db ? 'database' : 'search', 'items' => array_merge($db['sources'] ?? [], $hits->map(fn ($h) => ['type' => $h->type, 'title' => $h->title, 'url' => KnowledgeBase::absolute($h->url)])->all())] : $none;
        $source = $site ? 'kb' : 'web';

        if ($db && ! $special && $db['found'] > count($db['items'])) {                                           // more than the 3 cards: offer the full list
            $kindKey = (($filters['type'] ?? null) === 'new' || ($filters['vehicle'] ?? 'car') !== 'car') ? 'new' : 'used';
            $actions[] = ['type' => 'link', 'url' => SiteData::browseUrl($filters), 'label' => $this->t('view_all', $hi, ['n' => $db['totals'][$kindKey] ?: $db['found']])];
        }
        if ($db && ! $special && $db['found'] && ! $bookKind) $nudge[] = 'The cars are numbered in the order shown. Mention the best match.';

        // 7) Ask the AI (compact context). Stand-alone answers are cached; anything conversation-dependent is not.
        $memBlock = AssistantMemory::block($m);
        $ckey = 'asst:resp:'.md5(Str::lower($message).'|'.($site ? md5($ctx) : 'g').'|'.(int) $voice.'|'.(int) $hi);
        if (! $memBlock && ! $actions && ! $nudge && ($c = Cache::get($ckey))) {
            return $this->finish($session, $ip, $message, $c['answer'], $c['source'], $c['links'], $voice, 0, 0, true, $c['sources'] ?? $sources, ['lang' => $hi ? 'hi' : 'en']);
        }

        $answer = null; $in = 0; $out = 0; $captured = null;
        if (! $degraded && AiClient::configured()) {
            $msgs = $this->messages($message, $history, $ctx, $memBlock, $voice, $session, $hi, $nudge);
            $answer = AiClient::chat($msgs, [
                'temperature' => 0.7, 'timeout' => 40,
                'max_tokens' => $voice ? AssistantGuard::limit('max_output_voice') : AssistantGuard::limit('max_output'),
            ]);
            if ($answer) {
                // method_exists keeps chat alive even if an old AiClient.php is still on the server (stale deploy / OPcache).
                $u = method_exists(AiClient::class, 'lastUsage') ? AiClient::lastUsage() : ['in' => 0, 'out' => 0];
                $in = $u['in'] ?: AssistantGuard::estimate(collect($msgs)->pluck('content')->implode(' '));
                $out = $u['out'] ?: AssistantGuard::estimate($answer);
            }
            if ($answer && str_contains($answer, '[[WEB]]')) {
                $answer = trim(str_replace('[[WEB]]', '', $answer));
                $source = 'web'; $links = []; $sources = $none;
            }
        }

        $fromAi = (bool) $answer;
        $saved = false;
        if ($answer) {
            // The AI may end its reply with a hidden LEAD line once the visitor confirmed: validate it and write it to the database.
            [$answer, $req] = AssistantCapture::extract($answer);
            $answer = $this->plain($answer);
            if ($req !== null) {
                $res = AssistantCapture::save($session, $req, $m, $ip);
                if ($res['ok']) {
                    $saved = true;
                    $answer = trim($answer."\n\n".AssistantCapture::confirmation($res, $hi));
                    $captured = ['kind' => $res['kind'], 'ref' => $res['ref']];
                    $m['flow'] = null; $m['missing'] = null; $m['stage'] = 'booked';
                    if ($res['item']) $m['focus'] = $res['item'];
                    $m['booked'] = collect($m['booked'])->reject(fn ($b) => $b['ref'] === $res['ref'])->push(['kind' => $res['kind'], 't' => $res['car'] ?? '-', 'date' => $res['when'] ?? now()->format('D, d M Y'), 'ref' => $res['ref']])->take(-3)->values()->all();
                } else {
                    $m['missing'] = array_values($res['missing'] ?? []);                    // the AI is told what is still needed and asks for it itself next turn
                    $answer = AssistantCapture::missingLine($res['missing'] ?? [], $hi);   // never claim a booking that was not saved
                }
            }
            if ($answer === '') $answer = $this->t('ok', $hi);
        }
        if (! $answer) { // AI off, failed, or site-wide budget reached: answer straight from our own data
            if ($askKey) {
                $source = 'none'; $links = []; $sources = $none;
                $answer = $this->t($askKey, $hi);
            } elseif ($bookKind) {
                $source = 'none'; $links = []; $sources = $none;
                $answer = $this->t('busy', $hi, ['phone' => Setting::get('site.phone', '')]);
            } elseif ($ctx !== '') {
                $source = 'kb';
                $answer = ! empty($db['brief']) ? $db['brief'] : ($links ? $this->t('found', $hi).$this->linkTitles($links) : $this->t('nothing', $hi));
            } else {
                $fallback = KnowledgeBase::search($search, 3);
                if ($fallback->isNotEmpty()) {
                    $source = 'kb';
                    $links = $fallback->map(fn ($h) => ['title' => $h->title, 'url' => KnowledgeBase::absolute($h->url), 'image' => KnowledgeBase::absolute($h->image), 'type' => $h->type])->values()->all();
                    $sources = ['mode' => 'search', 'items' => $fallback->map(fn ($h) => ['type' => $h->type, 'title' => $h->title, 'url' => KnowledgeBase::absolute($h->url)])->all()];
                    $answer = $this->t('found', $hi).$fallback->take(2)->map(fn ($h) => $h->title.($h->type === 'article' ? ' - '.Str::limit(trim($h->content), 200, '…') : ''))->implode("
");
                } else {
                    $source = 'none'; $links = []; $sources = $none;
                    $answer = $this->t('nothing', $hi);
                }
            }
        }

        if ($fromAi && ! $saved && ! $memBlock && ! $actions && ! $nudge) Cache::put($ckey, ['answer' => $answer, 'source' => $source, 'links' => $links, 'sources' => $sources], now()->addDay());

        $m['turns']++;
        return $this->done($session, $ip, $message, $answer, $source, $links, $voice, $m, $sources, $actions, $in, $out, $captured, $this->chipsFor($db ?? null, $special, $bookKind, $filters, $answered));
    }

    /**
     * First-step tabs ("New car" / "Used car" / "Sell my car"): answered without the AI. The choice is stored in the conversation
     * memory, so everything the visitor says next is searched in that category and never drifts to the other one.
     */
    public function choose(string $intent, AssistantSession $session, string $city = ''): array
    {
        $m = AssistantMemory::get($session);
        $turns = $m['turns'] ?? 0;
        $m = ['hi' => false, 'turns' => $turns] + AssistantMemory::get(new AssistantSession());
        $actions = []; $chips = [];

        if ($intent === 'new') {
            $m['f'] = ['type' => 'new', 'vehicle' => 'car']; $m['ask'] = 'budget'; $m['asked'] = ['budget'];
            $answer = 'New cars it is. What budget do you have in mind? You can also pick a body type.';
            $chips = $this->budgetChips('new');
            foreach (['SUV', 'Hatchback', 'Sedan', 'Electric'] as $b) $chips[] = ['label' => $b, 'q' => 'new '.Str::lower($b).' cars'];
        } elseif ($intent === 'used') {
            $m['f'] = ['type' => 'used', 'vehicle' => 'car']; $m['ask'] = 'city'; $m['asked'] = ['city'];
            $answer = 'Used cars it is. Which city should I search in?';
            $city = trim(preg_replace('/[^\p{L}\s.\-]/u', '', $city));
            if ($city !== '') { $answer = "Used cars it is. Shall I search in $city, or another city?"; $chips[] = ['label' => $city, 'q' => "used cars in $city"]; }
            foreach (array_slice(array_map(fn ($c) => is_array($c) ? ($c['name'] ?? '') : $c, \App\Support\Filters::CITIES), 0, 6) as $c) if ($c !== '' && strcasecmp($c, $city) !== 0) $chips[] = ['label' => $c, 'q' => "used cars in $c"];
            $chips[] = ['label' => 'Anywhere', 'q' => 'Anywhere'];
        } else {   // sell
            $m['f'] = []; $m['ask'] = null;
            $answer = 'You can list your car with us in a minute. Share a few details and our team will call you back with an offer.';
            $actions[] = ['type' => 'link', 'url' => route('sell'), 'label' => 'Sell your car'];
        }
        AssistantMemory::save($session, $m);
        return ['answer' => $answer, 'source' => 'none', 'links' => [], 'left' => max(0, AssistantGuard::limit('msgs_day') - ($session->usage_date?->isToday() ? $session->messages_today : 0)), 'cached' => true, 'actions' => $actions, 'chips' => $chips, 'lang' => 'en'];
    }

    /** Tap-able budget ranges; each one is a full sentence the filters understand. */
    private function budgetChips(string $type): array
    {
        $word = $type === 'new' ? 'new' : 'used';
        $ranges = $type === 'new' ? [['Under ₹5 lakh', 'under 5 lakh'], ['₹5 - 10 lakh', '5 to 10 lakh'], ['₹10 - 20 lakh', '10 to 20 lakh'], ['Above ₹20 lakh', 'above 20 lakh']]
            : [['Under ₹3 lakh', 'under 3 lakh'], ['₹3 - 6 lakh', '3 to 6 lakh'], ['₹6 - 10 lakh', '6 to 10 lakh'], ['Above ₹10 lakh', 'above 10 lakh']];
        return array_map(fn ($r) => ['label' => $r[0], 'q' => "$word cars ".$r[1]], $ranges);
    }

    /** Follow-up buttons shown under a search result: narrow by budget when none was given. */
    private function chipsFor(?array $db, bool $special, $bookKind, array $filters, bool $answered): array
    {
        if (! $db || $special || $bookKind || empty($db['found'])) return [];
        if (isset($filters['price_max']) || isset($filters['price_min'])) return [];
        $type = ($filters['type'] ?? null) === 'new' || ($filters['vehicle'] ?? 'car') !== 'car' ? 'new' : 'used';
        return $this->budgetChips($type);
    }

    /** "Title - price; Title - price." for the built-in fallback replies (the cards below carry the links). */
    private function linkTitles(array $links): string
    {
        return collect($links)->take(3)->map(fn ($l) => $l['title'].(! empty($l['price']) ? ' - '.$l['price'] : ''))->implode('; ').'.';
    }

    /** Replies are plain conversation: links belong on the cards / buttons, never pasted into the text. */
    private function plain(string $a): string
    {
        $a = preg_replace('/\[([^\]]+)\]\(https?:[^)]*\)/i', '$1', $a);                 // [text](url) -> text
        $a = preg_replace('/\s*\(?\bhttps?:\/\/[^\s)]+\)?/i', '', $a);                  // bare urls, with surrounding brackets
        $a = preg_replace('/\s*\bwww\.[^\s)]+/i', '', $a);
        return trim(preg_replace('/ {2,}/', ' ', preg_replace('/ +([.,!?।])/u', '$1', $a)));
    }

    /** Remember what the visitor is looking at, enrich their lead, then build the reply. */
    private function done(AssistantSession $s, string $ip, string $q, string $answer, string $source, array $links, bool $voice, array $m, array $sources, array $actions, int $in = 0, int $out = 0, ?array $captured = null, array $chips = []): array
    {
        AssistantMemory::syncLead($s, $m);
        AssistantMemory::save($s, $m);
        return $this->finish($s, $ip, $q, $answer, $source, $links, $voice, $in, $out, $in + $out === 0, $sources, ['actions' => $actions, 'focus' => $m['focus'] ?? null, 'captured' => $captured, 'chips' => $chips, 'lang' => ($m['hi'] ?? false) ? 'hi' : 'en']);
    }

    private function finish(AssistantSession $s, string $ip, string $q, string $answer, string $source, array $links, bool $voice, int $in, int $out, bool $cached, array $sources = [], array $extra = []): array
    {
        AssistantGuard::record($s, $in, $out, $ip);
        ChatLog::create(['session_id' => Str::limit((string) $s->token, 60, ''), 'assistant_session_id' => $s->id, 'question' => $q, 'answer' => $answer, 'source' => $source, 'voice' => $voice, 'tokens_in' => $in, 'tokens_out' => $out, 'cached' => $cached, 'sources' => $sources ?: null]);

        $limit = AssistantGuard::limit('msgs_day');
        return ['answer' => $answer, 'source' => $source, 'links' => $links, 'left' => max(0, $limit - $s->messages_today), 'cached' => $cached] + $extra;
    }

    /** The visitor may be answering the question we just asked ("Pune", "anywhere", "no limit"). Returns true when they did. */
    private function absorbAnswer(array &$m, string $message, string $norm, array &$parsed, bool $namedCar = false): bool
    {
        $ask = $m['ask'] ?? null;
        if (! $ask) return false;
        $t = trim(Str::lower($norm), " .!?");
        $any = (bool) preg_match('/^(anywhere|any|any city|koi bhi|kahin bhi|kahi bhi|sab|all|no preference|flexible|no limit|no budget|koi limit nahi|koi bhi budget)\b/', $t);
        if ($ask === 'city') {
            if ($any) { $m['f']['city_any'] = true; return true; }
            if (! isset($parsed['city']) && ! $namedCar && empty($parsed['brand_id']) && empty($parsed['body_id']) && preg_match('/^[\p{L}\p{M} ]{3,30}$/u', $message) && count(explode(' ', trim($message))) <= 3 && ! preg_match(self::BUY, $norm) && ! preg_match(self::SITE_WORDS, $norm)) $parsed['city'] = Str::title(trim($message));
            return isset($parsed['city']);
        }
        if ($ask === 'budget') {
            if ($any) { $m['f']['budget_any'] = true; return true; }
            return isset($parsed['price_max']) || isset($parsed['price_min']);
        }
        if ($ask === 'type') return isset($parsed['type']);
        return false;
    }

    private function clean(array $f): array
    {
        return array_intersect_key($f, array_flip(['type', 'vehicle', 'status', 'price_min', 'price_max', 'fuel', 'transmission', 'city', 'brand', 'brand_id', 'body', 'body_id', 'year', 'year_min', 'year_max', 'km_max', 'owner', 'city_any', 'budget_any']));
    }

    private function label(array $f, string $kind, bool $hi = false): string
    {
        $vehicle = $f['vehicle'] ?? 'car';
        $thing = $vehicle === 'bike' ? ($hi ? 'बाइक' : 'bikes') : ($vehicle === 'truck' ? ($hi ? 'ट्रक' : 'trucks') : ($hi ? 'गाड़ियाँ' : 'cars'));
        $what = $kind === 'used' ? ($hi ? 'पुरानी ' : 'used ').($vehicle === 'car' ? ($hi ? 'गाड़ियाँ' : 'cars') : $thing) : ($hi ? 'नई ' : 'new ').$thing;
        return trim($what.(! empty($f['city']) ? ($hi ? ' ('.$f['city'].')' : ' in '.$f['city']) : ''));
    }

    /** Short, varied, token-free replies. Index 0 = English, 1 = Hindi. Used when the AI is off / over budget, and for page-opening. */
    private function t(string $key, bool $hi, array $v = []): string
    {
        $set = [
            'ask_city' => [['Certainly. Which city or area are you looking in?', 'Happy to help. Which city or area should I search in?'], ['ज़रूर! आप किस शहर या इलाके में देख रहे हैं?', 'बिल्कुल! पहले बताइए, किस शहर या इलाके में चाहिए?']],
            'ask_budget' => [['Certainly. What is your approximate budget?', 'Happy to help. Roughly what budget do you have in mind?'], ['बहुत बढ़िया! आपका बजट कितना है?', 'अच्छी पसंद! लगभग कितना बजट सोचा है आपने?']],
            'ask_type' => [['Happy to help. Are you looking for a new car or a used one?'], ['ज़रूर! आपको नई गाड़ी चाहिए या पुरानी (used)?']],
            'opening' => [['Opening all {n} {what} for you.'], ['{n} {what} का पूरा पेज खोल रहा हूँ…']],
            'open_label' => [['Open now'], ['अभी खोलें']],
            'view_all' => [['View all {n} cars →'], ['सभी {n} गाड़ियाँ देखें →']],
            'found' => [["Here is what I found on our site: "], ['हमारी साइट पर यह मिला: ']],
            'nothing' => [["I could not find that on our site yet. Could you share a little more about what you need, or browse our new and used cars?"], ['यह जानकारी अभी हमारी साइट पर नहीं मिली। थोड़ा और बताइए कि आपको क्या चाहिए, या हमारी नई और पुरानी गाड़ियाँ देखिए।']],
            'busy' => [['Our assistant is unavailable for a moment. Please try again shortly or contact our team on {phone} and we will arrange it for you.'], ['अभी हमारा असिस्टेंट बहुत व्यस्त है। कृपया हमारी टीम को {phone} पर कॉल करें, हम आपके लिए व्यवस्था कर देंगे।']],
            'ok' => [['Noted. Is there anything else I can help you with?'], ['ठीक है! और कुछ मदद चाहिए?']],
        ];
        $variants = $set[$key][$hi ? 1 : 0];
        return str_replace(array_map(fn ($k) => '{'.$k.'}', array_keys($v)), array_values($v), Arr::random($variants));
    }

    /** A dynamic page is relevant when its title matches, or at least two of the visitor's meaningful words appear in it. */
    private function pageMatches($h, string $message): bool
    {
        $words = collect(preg_split('/[^\p{L}\p{N}]+/u', Str::lower($message), -1, PREG_SPLIT_NO_EMPTY))->filter(fn ($w) => mb_strlen($w) >= 4)->unique();
        if ($words->isEmpty()) return false;
        $hay = Str::lower($h->title.' '.$h->content);
        return $words->filter(fn ($w) => str_contains($hay, $w))->count() >= 2 || $this->titleMatches(collect([$h]), $message);
    }

    /** True when a hit's title shares a brand/model word with the question ("creta", "brezza", "nexon"). */
    private function titleMatches($hits, string $message): bool
    {
        $words = collect(preg_split('/[^\p{L}\p{N}]+/u', Str::lower($message), -1, PREG_SPLIT_NO_EMPTY))
            ->filter(fn ($w) => mb_strlen($w) >= 4 && ! in_array($w, ['best', 'good', 'cars', 'with', 'what', 'which', 'this', 'that', 'have', 'does', 'mileage', 'petrol', 'diesel', 'electric', 'family', 'budget', 'lakh', 'under', 'about', 'tell', 'much', 'give', 'need', 'want', 'where', 'there', 'their', 'india'], true));
        foreach ($hits as $h) {
            $title = Str::lower((string) $h->title);
            if ($words->contains(fn ($w) => str_contains($title, $w))) return true;
        }
        return false;
    }

    /** Greetings, thanks and prompt-injection attempts never reach the LLM. A greeting is never repeated once the chat has started. */
    private function local(string $msg, AssistantSession $s, array $m, bool $hi): ?string
    {
        $first = Str::before((string) $s->lead?->name, ' ');
        $t = Str::lower(trim($msg, " !.?"));
        $started = ($m['turns'] ?? 0) > 0 || $s->messages_total > 0;
        if (preg_match('/^(hi+|hello+|hey+|namaste|namaskar|good (morning|afternoon|evening)|hola)$/', $t)) {
            if ($started) {
                $focus = $m['focus']['t'] ?? null;
                return $hi
                    ? ($focus ? "हाँ, बताइए{$this->ji($first)}! $focus के बारे में आगे बढ़ें?" : 'हाँ, मैं यहीं हूँ। बताइए, क्या देखना है?')
                    : ($focus ? "Hello again".($first ? ", $first" : '').". Shall we continue with $focus?" : "Hello again".($first ? ", $first" : '').'. What would you like to look at?');
            }
            return $hi
                ? Arr::random(["नमस्ते{$this->ji($first)}! 👋 बताइए, नई गाड़ी देख रहे हैं, पुरानी, या बस जानकारी चाहिए?", "नमस्ते{$this->ji($first)}! आपके मन में क्या है — बजट, गाड़ी का टाइप, कुछ भी बताइए, हम मिलकर ढूँढ लेंगे।", "नमस्ते{$this->ji($first)}! 😊 मैं आपकी क्या मदद कर सकता हूँ?"])
                : Arr::random(["Hello".($first ? " $first" : '').". I can help you find a new or used car, compare models or answer questions about buying. What are you looking for?", "Welcome".($first ? ", $first" : '').". Tell me your budget or the type of car you have in mind, and I will suggest suitable options.", "Hello".($first ? " $first" : '').". How can I help you today?"]);
        }
        if (preg_match('/^(thanks?|thank you|thx|ok|okay|cool|great|bye|goodbye|shukriya|dhanyavad)$/', $t)) {
            return $hi
                ? Arr::random(["कोई बात नहीं{$this->ji($first)}! और कुछ पूछना हो तो बेझिझक बताइए।", 'आपका स्वागत है! 😊 कुछ और चाहिए तो मैं यहीं हूँ।', "ख़ुशी हुई मदद करके{$this->ji($first)}! जब भी ज़रूरत हो, पूछिए।"])
                : Arr::random(["You're welcome".($first ? ", $first" : '').". Let me know if there is anything else I can help with.", "Glad to help. Feel free to ask if you need anything else.", "My pleasure. I am here whenever you need further assistance."]);
        }
        if (preg_match('/(ignore (all |the )?(previous|above|prior)|system prompt|your instructions|api[ _-]?key|jailbreak|developer mode|reveal .*prompt)/i', $msg)) {
            return $hi ? "यह मैं नहीं कर सकता{$this->ji($first)} — लेकिन गाड़ियों, खरीदने-बेचने या हमारी वेबसाइट के बारे में कुछ भी पूछिए!" : "I'm unable to help with that request, but I can answer questions about cars, buying, selling or our website.";
        }
        return null;
    }

    private function ji(string $first): string
    {
        return $first !== '' ? " $first जी" : '';
    }

    private function messages(string $message, array $history, string $ctx, string $memBlock, bool $voice, AssistantSession $s, bool $hi, array $nudge): array
    {
        $name = Setting::get('assistant.name', 'Auto Guide');
        $site = Setting::get('site.name', config('app.name'));
        $first = $s->lead?->name ? Str::before($s->lead->name, ' ') : null;
        $today = now()->format('l, Y-m-d');

        $persona = "You are $name, the virtual assistant of $site (new cars, used cars, car news, calculators and a used-car marketplace in India), speaking with ".($first ?: 'a visitor').($s->lead?->city ? " from {$s->lead->city}" : '').'. '
            .'LANGUAGE: reply in clear, professional English by default. If the visitor writes in another language or script (Hindi in Devanagari, Hinglish in Roman letters, Marathi, Gujarati, Tamil, Telugu, Bengali...), reply in that same language and script, and switch again when they switch. Keep everyday English terms (test drive, EMI, SUV, diesel, on-road price, showroom) as they are, and write prices in rupees (lakh / crore). '
            .'TONE: professional, polite and precise, like a senior advisor at a reputable dealership. No slang or casual filler (no "bhai", "boss", "dost", "yaar"), no jokes, no more than one exclamation mark, emojis only if the visitor uses them. Do not greet again after the first message. Give the answer first, in 2-4 short sentences; use a short list only to compare three or more options. End with ONE clear next step or ONE question, not several. If asked who you are, say you are the site\'s virtual assistant. Be honest when unsure; never invent stock, prices, offers, dealers or specifications. Stay with cars, buying, selling, ownership and this website; for an unrelated topic decline in one polite sentence and offer to help with cars. '
            .($voice ? 'This reply will be SPOKEN: plain sentences, no markdown, lists, URLs or emojis. ' : 'Plain text; light **bold** is fine, no long lists. ')
            ."\nANSWER FIRST: answer exactly what they asked, using the website data below when it applies (quote prices, names and dates as given). The visitor may write in Hinglish with typos or loose wording - understand the meaning, never ask them to repeat or rephrase. Do not repeat earlier wording or re-offer something they already ignored."
            ."\nGOAL: be genuinely helpful. Only when the visitor clearly shows buying intent, or asks, offer a test drive, inspection or callback - never in every reply. If they decline, respect it and keep helping."
            ."\nCOLLECTING DETAILS: the visitor's name, mobile and email are ALREADY verified - never ask for them. Ask only what is missing, one thing at a time. Test drive / inspection needs: which car (a number from Shown, or a name), date, time slot (morning 9-12, afternoon 12-4, evening 4-8), at the showroom or at their address (then the address). Any other enquiry (price/on-road, EMI or loan, exchange, selling their car, brochure, callback) needs: what they want, plus city, budget and best time to call when relevant."
            ."\nSAVING: only when you have everything AND the visitor has said yes/confirmed, end your reply with ONE last line, in exactly this format and nothing after it (omit keys you do not need):\n"
            .'[[LEAD {"kind":"test_drive|inspection|enquiry","car":2,"date":"YYYY-MM-DD","slot":"morning|afternoon|evening","place":"showroom|home","address":"","topic":"","city":"","budget":"","callback":"","note":""}]]'
            ."\nToday is $today (so \"kal\"/tomorrow is the next date; dates must be within 30 days). In the visible text just say you have noted it and the team will call to confirm; NEVER invent a reference number (the system adds it). Never write the line before they confirm, and never twice. Never say you have noted, booked, saved or sent anything unless this same reply ends with the line - otherwise ask for what is missing. NEVER write a link or URL in your reply: when a page, car or article matters, just name it (a card with the link is shown automatically). "
            .Setting::get('assistant.extra_instructions', '');

        if ($memBlock !== '') $persona .= "\nVISITOR SO FAR (never ask again what is known): $memBlock";
        if ($nudge) $persona .= "\nNOW: ".implode(' ', $nudge);
        if ($pages = KnowledgeBase::pageIndex()) $persona .= "\nOUR PAGES (you may name one when helpful): ".preg_replace('/\s*\(https?:[^)]*\)/', '', $pages);
        if ($ctx !== '') {
            $persona .= "\nFROM OUR WEBSITE (live data: stock, prices, listings, news, pages, business info). Quote prices exactly, name items, never invent OUR stock, prices, offers or dealers. Use this first. If it does not cover the question, still answer helpfully from your general knowledge (say briefly that it is general information, not from our site) and never refuse just because our site lacks it:\n$ctx\nIf this data is not relevant at all, start with [[WEB]] and answer from your own knowledge.";
        } else {
            $persona .= "\nOur site has nothing on this, so answer from your general knowledge like a knowledgeable friend. Only for OUR stock, prices, offers or dealers: do not guess - say we do not have it listed and offer to connect them with the team.";
        }

        $msgs = [['role' => 'system', 'content' => $persona]];
        foreach (array_slice($history, -6) as $h) {   // memory carries the rest, so only the very latest turns are sent
            if (in_array($h['role'] ?? '', ['user', 'assistant'], true) && is_string($h['content'] ?? null)) {
                $msgs[] = ['role' => $h['role'], 'content' => Str::limit($h['content'], 300, '')];
            }
        }
        $msgs[] = ['role' => 'user', 'content' => $message];
        return $msgs;
    }
}
