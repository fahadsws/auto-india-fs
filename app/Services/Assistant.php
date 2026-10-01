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
    private const BUY = '/\b(chahiye|chaiye|chahie|chahta|chahti|buy|kharid|kharidna|lena|leni|looking for|want|need|dikhao|dikha|show|interested)\b/i';
    private const HINDI = '/[\x{0900}-\x{097F}]|\b(mujhe|muje|mere|mera|meri|chahiye|chaiye|chahie|kya|kaun|kitna|kitne|nahi|nhi|haan|aap|apna|mein|mai|hai|hain|karna|karni|dikhao|dikha|sare|saare|gaadi|gadi|batao|bataiye|wali|wala|purani|nayi)\b/iu';

    /** @return array{answer:string, source:string, links:array, actions?:array, captured?:array, lang?:string, cached?:bool} */
    public function reply(string $message, array $history, AssistantSession $session, string $ip, bool $voice = false, bool $degraded = false): array
    {
        $message = Str::limit(trim(preg_replace('/\s+/', ' ', $message)), AssistantGuard::limit('max_input') ?: 300, '');
        $norm = HindiText::normalize($message);          // Devanagari -> English words, for filters / intent rules / knowledge search
        $m = AssistantMemory::get($session);
        // Hindi is the default. Only a clearly English message switches to English; Hindi / Hinglish switches back.
        if (HindiText::isEnglish($message)) $m['hi'] = false;
        elseif (HindiText::has($message) || preg_match(self::HINDI, $message) || ! isset($m['hi'])) $m['hi'] = true;
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
        $answered = $this->absorbAnswer($m, $message, $norm, $parsed);
        $m['ask'] = null;   // a question applies to the very next message only
        $ref = AssistantMemory::resolveReference($norm, $m);
        if ($ref) $m['focus'] = $ref;
        $filters = AssistantMemory::merge($m['f'], $parsed);

        $siteWords = (bool) preg_match(self::SITE_WORDS, $norm);
        $kinds = count(array_intersect_key($parsed, array_flip(['price_min', 'price_max', 'fuel', 'transmission', 'city', 'brand_id', 'body_id', 'year', 'year_min', 'year_max', 'km_max', 'owner', 'status'])));
        $short = count(preg_split('/\s+/', $message)) <= 5 && ! str_contains($message, '?');
        $searchActive = ! empty($filters['type']) || ! empty($filters['vehicle']) || ! empty($m['f']);
        $refine = $searchActive && ($answered || ($kinds > 0 && $short));
        $showAll = ($parsed['_vehicle_word'] || ! empty($m['f'])) && preg_match(self::SHOW_ALL, $norm) && ($searchActive || $parsed['_vehicle_word']);
        $bookKind = preg_match(self::TEST_DRIVE, $norm) ? 'test_drive' : (preg_match(self::INSPECTION, $norm) ? 'inspection' : null);
        $direct = SiteData::wanted($parsed, $siteWords);
        $run = $direct || $refine || $showAll || ($parsed['_vehicle_word'] && ! empty($filters['type']));
        $actions = []; $nudge = []; $askKey = null;

        // 3) The AI talks the visitor through a test drive / inspection; these hints tell it where the conversation is.
        if ($bookKind) {
            $run = false; $showAll = false;                                                 // keep the numbered car list stable while booking
            $target = $ref ?? $m['focus'] ?? (count($m['shown']) === 1 ? $m['shown'][0] : null);
            $label = $bookKind === 'test_drive' ? 'test drive' : 'inspection';
            if ($target) {
                $m['focus'] = $target; $m['stage'] = 'interested';
                $m['flow'] = ['kind' => $bookKind, 'car' => $target['t']];
                $nudge[] = "The visitor wants a $label for {$target['t']}. Collect what is still missing, one question at a time (date within the next 30 days, time slot, showroom or their address), then repeat it back and ask them to confirm.";
            } elseif ($m['shown']) {
                $m['flow'] = ['kind' => $bookKind, 'car' => null];
                $nudge[] = "The visitor wants a $label but has not chosen the car. Ask which one of the cars shown (by number or name).";
            } else {
                $m['flow'] = ['kind' => $bookKind, 'car' => null];
                $nudge[] = "The visitor wants a $label but we do not know the car yet. Ask which car, or their city and budget so you can suggest some.";
            }
        } elseif ($ref && ! $run && ! $siteWords) {
            $m['stage'] = 'interested';
            $nudge[] = "They just picked {$ref['t']}. Acknowledge it warmly in one line and offer a test drive, an inspection or a callback.";
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
        $db = $run ? SiteData::lookup($filters) : null;
        if ($db) {
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

        $search = $norm;
        $hits = $askKey ? collect() : KnowledgeBase::search($search, 4);                              // a pure question needs no site data
        $about = preg_match(self::ABOUT_WORDS, $norm) ? KnowledgeBase::siteInfo() : null;          // business details: contact, hours, services...
        // Our own dynamic pages are part of the assistant's knowledge: a page that clearly matches is used even without "site words".
        $hits = $hits->isNotEmpty() ? $hits->filter(fn ($h) => $siteWords || $this->titleMatches(collect([$h]), $norm) || ($h->type === 'webpage' && $this->pageMatches($h, $norm)))->values() : $hits;
        if ($db && ! $this->titleMatches($hits, $norm)) $hits = $hits->reject(fn ($h) => $h->type !== 'webpage')->values();   // the database answer is the main source
        if ($about) $hits = $hits->reject(fn ($h) => $h->id === $about->id)->prepend($about)->take(3)->values();
        $hits = $hits->take(3)->values();

        $site = $db !== null || $hits->isNotEmpty();
        $ctx = trim(($db['context'] ?? '').($hits->isNotEmpty() ? ($db ? "\n\n" : '').$hits->map(fn ($h, $i) => '['.($i + 1)."] ({$h->type}) {$h->title}".($h->url ? ' - '.KnowledgeBase::absolute($h->url) : '')."\n".Str::limit($h->content, in_array($h->type, ['car', 'listing'], true) ? 700 : ($h->type === 'webpage' ? 1100 : 450), '…'))->implode("\n\n") : ''));
        $links = $db['links'] ?? $hits->take(3)->map(fn ($h) => ['title' => $h->title, 'url' => KnowledgeBase::absolute($h->url), 'image' => KnowledgeBase::absolute($h->image), 'type' => $h->type] + (in_array($h->type, ['car', 'listing'], true) ? ['k' => $h->type === 'car' ? 'c' : 'l', 'id' => $h->ref_id] : []))->values()->all();
        $sources = $site ? ['mode' => $db ? 'database' : 'search', 'items' => array_merge($db['sources'] ?? [], $hits->map(fn ($h) => ['type' => $h->type, 'title' => $h->title, 'url' => KnowledgeBase::absolute($h->url)])->all())] : $none;
        $source = $site ? 'kb' : 'web';

        if ($db && $db['found'] > count($db['items'])) {                                           // more than the 3 cards: offer the full list
            $kindKey = (($filters['type'] ?? null) === 'new' || ($filters['vehicle'] ?? 'car') !== 'car') ? 'new' : 'used';
            $actions[] = ['type' => 'link', 'url' => SiteData::browseUrl($filters), 'label' => $this->t('view_all', $hi, ['n' => $db['totals'][$kindKey] ?: $db['found']])];
        }
        if ($db && $db['found'] && ($filters['type'] ?? null) !== 'new' && ($filters['vehicle'] ?? 'car') === 'car' && ! isset($filters['price_max']) && ! isset($filters['price_min']) && empty($filters['budget_any']) && $db['found'] > 3) {
            $nudge[] = 'After showing these, ask ONE soft question about their budget.';
            $m['ask'] = 'budget';
        }
        if ($db && $db['found'] && ! $bookKind) $nudge[] = 'The cars are numbered in the order shown. Mention the best match, and offer a test drive if they like one.';

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
            if ($req !== null) {
                $res = AssistantCapture::save($session, $req, $m, $ip);
                if ($res['ok']) {
                    $saved = true;
                    $answer = trim($answer."\n\n".AssistantCapture::confirmation($res, $hi));
                    $captured = ['kind' => $res['kind'], 'ref' => $res['ref']];
                    $m['flow'] = null; $m['stage'] = 'booked';
                    if ($res['item']) $m['focus'] = $res['item'];
                    $m['booked'] = collect($m['booked'])->reject(fn ($b) => $b['ref'] === $res['ref'])->push(['kind' => $res['kind'], 't' => $res['car'] ?? '-', 'date' => $res['when'] ?? now()->format('D, d M Y'), 'ref' => $res['ref']])->take(-3)->values()->all();
                } else {
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
                $answer = $this->t('found', $hi).($db ? $db['context'] : $hits->take(3)->pluck('title')->implode('; ').'.');
            } else {
                $fallback = KnowledgeBase::search($search, 3);
                if ($fallback->isNotEmpty()) {
                    $source = 'kb';
                    $links = $fallback->map(fn ($h) => ['title' => $h->title, 'url' => KnowledgeBase::absolute($h->url), 'image' => KnowledgeBase::absolute($h->image), 'type' => $h->type])->values()->all();
                    $sources = ['mode' => 'search', 'items' => $fallback->map(fn ($h) => ['type' => $h->type, 'title' => $h->title, 'url' => KnowledgeBase::absolute($h->url)])->all()];
                    $answer = $this->t('found', $hi).$fallback->pluck('title')->implode('; ').'.';
                } else {
                    $source = 'none'; $links = []; $sources = $none;
                    $answer = $this->t('nothing', $hi);
                }
            }
        }

        if ($fromAi && ! $saved && ! $memBlock && ! $actions && ! $nudge) Cache::put($ckey, ['answer' => $answer, 'source' => $source, 'links' => $links, 'sources' => $sources], now()->addDay());

        $m['turns']++;
        return $this->done($session, $ip, $message, $answer, $source, $links, $voice, $m, $sources, $actions, $in, $out, $captured);
    }

    /** Remember what the visitor is looking at, enrich their lead, then build the reply. */
    private function done(AssistantSession $s, string $ip, string $q, string $answer, string $source, array $links, bool $voice, array $m, array $sources, array $actions, int $in = 0, int $out = 0, ?array $captured = null): array
    {
        AssistantMemory::syncLead($s, $m);
        AssistantMemory::save($s, $m);
        return $this->finish($s, $ip, $q, $answer, $source, $links, $voice, $in, $out, $in + $out === 0, $sources, ['actions' => $actions, 'focus' => $m['focus'] ?? null, 'captured' => $captured, 'lang' => ($m['hi'] ?? true) ? 'hi' : 'en']);
    }

    private function finish(AssistantSession $s, string $ip, string $q, string $answer, string $source, array $links, bool $voice, int $in, int $out, bool $cached, array $sources = [], array $extra = []): array
    {
        AssistantGuard::record($s, $in, $out, $ip);
        ChatLog::create(['session_id' => Str::limit((string) $s->token, 60, ''), 'assistant_session_id' => $s->id, 'question' => $q, 'answer' => $answer, 'source' => $source, 'voice' => $voice, 'tokens_in' => $in, 'tokens_out' => $out, 'cached' => $cached, 'sources' => $sources ?: null]);

        $limit = AssistantGuard::limit('msgs_day');
        return ['answer' => $answer, 'source' => $source, 'links' => $links, 'left' => max(0, $limit - $s->messages_today), 'cached' => $cached] + $extra;
    }

    /** The visitor may be answering the question we just asked ("Pune", "anywhere", "no limit"). Returns true when they did. */
    private function absorbAnswer(array &$m, string $message, string $norm, array &$parsed): bool
    {
        $ask = $m['ask'] ?? null;
        if (! $ask) return false;
        $t = trim(Str::lower($norm), " .!?");
        $any = (bool) preg_match('/^(anywhere|any|any city|koi bhi|kahin bhi|kahi bhi|sab|all|no preference|flexible|no limit|no budget|koi limit nahi|koi bhi budget)\b/', $t);
        if ($ask === 'city') {
            if ($any) { $m['f']['city_any'] = true; return true; }
            if (! isset($parsed['city']) && preg_match('/^[\p{L}\p{M} ]{3,30}$/u', $message) && count(explode(' ', trim($message))) <= 3 && ! preg_match(self::BUY, $norm) && ! preg_match(self::SITE_WORDS, $norm)) $parsed['city'] = Str::title(trim($message));
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

    /** Short, varied, token-free replies. Index 0 = English, 1 = Hindi (the default). Used when the AI is off / over budget, and for page-opening. */
    private function t(string $key, bool $hi, array $v = []): string
    {
        $set = [
            'ask_city' => [['Sure! Which city or area are you looking in?', 'Great, let me find some good ones. Which city or area works for you?'], ['ज़रूर! आप किस शहर या इलाके में देख रहे हैं?', 'बिल्कुल! पहले बताइए, किस शहर या इलाके में चाहिए?']],
            'ask_budget' => [['Nice! What budget are you thinking of?', 'Good choice. Roughly what budget do you have in mind?'], ['बहुत बढ़िया! आपका बजट कितना है?', 'अच्छी पसंद! लगभग कितना बजट सोचा है आपने?']],
            'ask_type' => [['Happy to help! Are you looking for a new car or a used one?'], ['ज़रूर! आपको नई गाड़ी चाहिए या पुरानी (used)?']],
            'opening' => [['Opening all {n} {what} for you…'], ['{n} {what} का पूरा पेज खोल रहा हूँ…']],
            'open_label' => [['Open now'], ['अभी खोलें']],
            'view_all' => [['View all {n} cars →'], ['सभी {n} गाड़ियाँ देखें →']],
            'found' => [["Here's what I found on our site: "], ['हमारी साइट पर यह मिला: ']],
            'nothing' => [["I couldn't find that on our site yet. Tell me a bit more about what you're looking for, or browse our used and new cars."], ['यह जानकारी अभी हमारी साइट पर नहीं मिली। थोड़ा और बताइए कि आपको क्या चाहिए, या हमारी नई और पुरानी गाड़ियाँ देखिए।']],
            'busy' => [['Our assistant is very busy right now. Please call our team on {phone} and we will arrange it for you.'], ['अभी हमारा असिस्टेंट बहुत व्यस्त है। कृपया हमारी टीम को {phone} पर कॉल करें, हम आपके लिए व्यवस्था कर देंगे।']],
            'ok' => [['Noted! Anything else I can help with?'], ['ठीक है! और कुछ मदद चाहिए?']],
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
                    : ($focus ? "I'm right here, ".($first ?: 'friend')."! Shall we continue with $focus?" : "I'm right here, ".($first ?: 'friend').' - what would you like to look at?');
            }
            return $hi
                ? Arr::random(["नमस्ते{$this->ji($first)}! 👋 बताइए, नई गाड़ी देख रहे हैं, पुरानी, या बस जानकारी चाहिए?", "नमस्ते{$this->ji($first)}! आपके मन में क्या है — बजट, गाड़ी का टाइप, कुछ भी बताइए, हम मिलकर ढूँढ लेंगे।", "नमस्ते{$this->ji($first)}! 😊 मैं आपकी क्या मदद कर सकता हूँ?"])
                : Arr::random(["Hey ".($first ?: 'there')."! 👋 Good to see you. What are you thinking about - a new car, a used one, or just exploring?", "Hi ".($first ?: 'there')."! Tell me what you have in mind - budget, type of car, anything - and we'll figure it out together.", "Hello ".($first ?: 'there')."! 😊 How can I help you today?"]);
        }
        if (preg_match('/^(thanks?|thank you|thx|ok|okay|cool|great|bye|goodbye|shukriya|dhanyavad)$/', $t)) {
            return $hi
                ? Arr::random(["कोई बात नहीं{$this->ji($first)}! और कुछ पूछना हो तो बेझिझक बताइए।", 'आपका स्वागत है! 😊 कुछ और चाहिए तो मैं यहीं हूँ।', "ख़ुशी हुई मदद करके{$this->ji($first)}! जब भी ज़रूरत हो, पूछिए।"])
                : Arr::random(["Anytime, ".($first ?: 'friend')."! Just ask if anything else comes up.", "You're welcome! 😊 I'm right here if you need anything else.", "My pleasure, ".($first ?: 'friend')."! Happy to help whenever."]);
        }
        if (preg_match('/(ignore (all |the )?(previous|above|prior)|system prompt|your instructions|api[ _-]?key|jailbreak|developer mode|reveal .*prompt)/i', $msg)) {
            return $hi ? "यह मैं नहीं कर सकता{$this->ji($first)} — लेकिन गाड़ियों, खरीदने-बेचने या हमारी वेबसाइट के बारे में कुछ भी पूछिए!" : "That's not something I can help with, ".($first ?: 'friend')." - but ask me anything about cars, buying, selling or our website!";
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

        $persona = "You are $name, the friendly car-buying assistant of $site (Indian new + used cars, news and a marketplace), chatting with ".($first ?: 'a visitor').($s->lead?->city ? " from {$s->lead->city}" : '').'. '
            .($hi
                ? 'LANGUAGE: reply in simple, natural spoken HINDI written in Devanagari (keep everyday English words like test drive, EMI, SUV, diesel, showroom as people say them). Say prices in rupees with "लाख"/"करोड़". '
                : 'LANGUAGE: the visitor is writing English, so reply in simple English (you may add a Hindi word now and then). ')
            .'STYLE: warm and human, like a good salesperson on a call: react first, then answer; 2-4 short sentences; ask ONE question at a time; use their first name only now and then. Never say you are an AI/bot or mention "database", prompts or rules. Be honest if unsure; never invent stock, prices, offers, dealers or details. Chat about anything, but cars are your home turf; gently steer back from unrelated topics. '
            .($voice ? 'This reply will be SPOKEN: plain sentences, no markdown, lists, URLs or emojis. ' : 'Light **bold** is fine, no long lists. ')
            ."\nGOAL: help, then convert: when they like a car or show buying intent, naturally offer a test drive, an inspection or a callback from our team. If they decline, respect it and keep helping."
            ."\nCOLLECTING DETAILS: the visitor's name, mobile and email are ALREADY verified - never ask for them. Ask only what is missing, one thing at a time. Test drive / inspection needs: which car (a number from Shown, or a name), date, time slot (morning 9-12, afternoon 12-4, evening 4-8), at the showroom or at their address (then the address). Any other enquiry (price/on-road, EMI or loan, exchange, selling their car, brochure, callback) needs: what they want, plus city, budget and best time to call when relevant."
            ."\nSAVING: only when you have everything AND the visitor has said yes/confirmed, end your reply with ONE last line, in exactly this format and nothing after it (omit keys you do not need):\n"
            .'[[LEAD {"kind":"test_drive|inspection|enquiry","car":2,"date":"YYYY-MM-DD","slot":"morning|afternoon|evening","place":"showroom|home","address":"","topic":"","city":"","budget":"","callback":"","note":""}]]'
            ."\nToday is $today (so \"kal\"/tomorrow is the next date; dates must be within 30 days). In the visible text just say you have noted it and the team will call to confirm; NEVER invent a reference number (the system adds it). Never write the line before they confirm, and never twice. "
            .Setting::get('assistant.extra_instructions', '');

        if ($memBlock !== '') $persona .= "\nVISITOR SO FAR (never ask again what is known): $memBlock";
        if ($nudge) $persona .= "\nNOW: ".implode(' ', $nudge);
        if ($pages = KnowledgeBase::pageIndex()) $persona .= "\nOUR PAGES (you may mention one when helpful; give the full link only in text replies): $pages";
        if ($ctx !== '') {
            $persona .= "\nFROM OUR WEBSITE (live data: stock, prices, listings, news, pages, business info). Quote prices exactly, name items, never invent stock or details; if nothing matches say so and suggest widening the search or contacting the team:\n$ctx\nIf this is not relevant, start with [[WEB]] and answer from your own knowledge.";
        } else {
            $persona .= "\nAnswer from your own knowledge. If they ask about our own stock/prices and you lack data, say you will point them to the right page - don't guess.";
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
