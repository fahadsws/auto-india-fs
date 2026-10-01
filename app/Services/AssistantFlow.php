<?php

namespace App\Services;

use App\Models\Listing;
use App\Models\VehicleModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Takes a test drive / inspection / enquiry in conversation, ONE question at a time, with plain-language answers
 * ("kal subah", "Saturday evening", "ghar par", "3 pm"). Collecting the details is deterministic - not left to the LLM - so the
 * visitor is never asked several things at once, never asked for something already given, and the lead that lands in the
 * database always has a real car, a valid date and a slot. The AI keeps answering questions in between; this class
 * remembers where the booking stands (memory['flow']) and re-asks the pending question after the answer.
 */
class AssistantFlow
{
    public const YES = '/^(yes|yeah|yep|yup|y|ok|okay|sure|haan|han|ha|hanji|haan ji|ji haan|ji|theek hai|thik hai|theek|thik|bilkul|zaroor|please|kar do|karo|karde|book|confirm|confirmed|pakka|done|sahi hai|correct|go ahead|chalo|alright)\b/';
    public const NO = '/^(no|nope|nah|nahi|nhi|na|dont|don\'t|later|baad mein|abhi nahi|not now|rehne do|mat karo|cancel|stop|never ?mind)\b/';
    private const CANCEL = '/\b(cancel|never ?mind|forget it|rehne do|rahne do|chhodo|chodo|mat karo|nahi chahiye|not interested|stop this|drop it)\b/';

    private const SLOT_LABEL = ['morning' => 'morning (9 am - 12 pm)', 'afternoon' => 'afternoon (12 pm - 4 pm)', 'evening' => 'evening (4 pm - 8 pm)'];

    /** Which enquiry (if any) is this message asking for? test drive / inspection are handled separately. */
    public static function enquiryTopic(string $t): ?string
    {
        return match (true) {
            (bool) preg_match('/\b(sell my|sell the|selling my|want to sell|wanna sell|sell (karni|karna|krni|krna)|bech(ni|na|ne)|gaadi bech|car bech)\b/i', $t) => 'sell',
            (bool) preg_match('/\b(call ?back|call me|give me a call|phone me|call karo|call kar do|call krna|baat karni|baat karna|baat karwao|contact me|reach me|talk to (someone|your team|a person|sales))\b/i', $t) => 'callback',
            (bool) preg_match('/\b(loan|finance)\b.*\b(chahiye|chaiye|lena|leni|apply|need|want|karwana|karana)\b|\b(chahiye|chaiye|need|want|apply)\b.*\b(loan|finance)\b/i', $t) => 'loan',
            (bool) preg_match('/\b(exchange my|trade[- ]?in|exchange (karni|karna|krni|krna)|exchange offer|old car exchange)\b/i', $t) => 'exchange',
            default => null,
        };
    }

    public static function begin(string $kind, ?array $car, ?string $topic = null): array
    {
        return ['kind' => $kind, 'topic' => $topic, 'car' => $car, 'date' => null, 'slot' => null, 'place' => null, 'address' => null, 'detail' => null, 'time' => null, 'step' => null, 'tries' => 0];
    }

    /** Look a car up by a name the visitor typed ("Creta", "i20 sportz"): our used stock first, then the new catalog. */
    public static function findCarByName(string $text): ?array
    {
        $words = collect(preg_split('/[^\p{L}\p{N}]+/u', Str::lower($text), -1, PREG_SPLIT_NO_EMPTY))
            ->filter(fn ($w) => mb_strlen($w) >= 3 && ! in_array($w, ['test', 'drive', 'book', 'inspection', 'car', 'cars', 'used', 'new', 'for', 'the', 'and', 'ki', 'ka', 'ke', 'karni', 'karna', 'chahiye', 'chaiye', 'mujhe', 'muje', 'want', 'need', 'please', 'show', 'this', 'that', 'one', 'wali', 'wala', 'ghar', 'home', 'showroom', 'kal', 'aaj', 'tomorrow', 'today', 'morning', 'evening', 'afternoon', 'subah', 'shaam', 'dopahar', 'page', 'link', 'detail', 'details', 'take', 'open', 'kholo', 'jao', 'chalo', 'iske', 'iski', 'iska', 'information', 'share', 'batao', 'bata'], true))->take(3);
        if ($words->isEmpty()) return null;
        $like = fn ($q, $col) => $words->each(fn ($w) => $q->where($col, 'like', "%$w%"));
        $l = $like(Listing::active(), 'title')->latest('updated_at')->first();
        if ($l) return AssistantMemory::item($l);
        $v = $like(VehicleModel::published(), 'name')->latest('updated_at')->first();
        return $v ? AssistantMemory::item($v) : null;
    }

    /**
     * Take one visitor message into the open booking.
     * @return array{status:string, text?:string, req?:array}
     *   status: ask (next question) | confirm | save (req ready) | cancel | retry (re-asking) | digress (not an answer: let the AI reply, then re-ask)
     */
    public static function advance(array &$m, string $message, string $norm, bool $hi, bool $hasFilters = false, bool $fresh = false): array
    {
        $f = $m['flow'];
        $t = trim(Str::lower($norm));
        $plain = trim($t, " .!?,");

        if (preg_match(self::CANCEL, $t)) { $m['flow'] = null; return ['status' => 'cancel', 'text' => self::say('cancelled', $hi)]; }

        if (($f['step'] ?? null) === 'confirm') {
            if (preg_match(self::YES, $plain)) return ['status' => 'save', 'req' => self::request($f, $m)];
            if (preg_match(self::NO, $plain)) {
                if ($f['kind'] === 'enquiry') { $f['time'] = null; }
                else { $f['date'] = $f['slot'] = $f['place'] = $f['address'] = null; }
                $f['step'] = null; $m['flow'] = $f;
                return ['status' => 'ask', 'text' => self::say('change', $hi).' '.self::question($m['flow'], $m, $hi)];
            }
            // neither yes nor no: they may be changing a detail ("make it evening") - fall through and absorb it below
        }

        $hadCar = (bool) $f['car'];
        $before = json_encode([$f['car'], $f['date'], $f['slot'], $f['place'], $f['address'], $f['detail'], $f['time']]);
        $err = null;
        $step = self::nextStep($f);

        if ($f['kind'] === 'enquiry') {
            $long = count(preg_split('/\s+/', $plain)) >= 9;
            if ($fresh && ! $long) { /* the opening message only names the topic */ }
            elseif ($step === 'detail' && self::looksLikeAnswer($t)) $f['detail'] = Str::limit(trim($message), 200, '');
            elseif ($step === 'time' && self::looksLikeAnswer($t)) $f['time'] = Str::limit(trim($message), 60, '');
            elseif ($step === 'confirm') { if ($s = self::slot($t)) $f['time'] = self::SLOT_LABEL[$s]; }
        } else {
            if (! $f['car']) {
                $ref = AssistantMemory::resolveReference($norm, array_merge($m, ['focus' => null]));
                if (! $ref && preg_match('/^\s*#?(\d)\s*$/', $plain, $n) && isset($m['shown'][(int) $n[1] - 1])) $ref = $m['shown'][(int) $n[1] - 1];
                if (! $ref && ! $hasFilters && count(preg_split('/\s+/', $plain)) <= 4) $ref = self::findCarByName($norm);
                if ($ref) $f['car'] = $ref;
            }
            if ($d = self::date($t)) {
                if ($d->lt(today()) || $d->gt(today()->addDays(30))) $err = 'range'; else $f['date'] = $d->toDateString();
            }
            if ($s = self::slot($t)) $f['slot'] = $s;
            if ($p = self::place($t)) $f['place'] = $p;
            if (($f['place'] ?? null) === 'home' && ! $f['address'] && $step === 'address' && ! self::place($t) && mb_strlen($plain) >= 5) $f['address'] = Str::limit(trim($message), 200, '');
        }

        $changed = $before !== json_encode([$f['car'], $f['date'], $f['slot'], $f['place'], $f['address'], $f['detail'], $f['time']]);
        if (! $changed && $fresh) {                                    // just opened: nothing to absorb yet, ask the first question
            $f['step'] = self::nextStep($f); $m['flow'] = $f;
            return ['status' => 'ask', 'text' => self::question($f, $m, $hi, true)];
        }
        if (! $changed) {
            if ($err === 'range') { $m['flow'] = $f; return ['status' => 'retry', 'text' => self::say('range', $hi).' '.self::question($f, $m, $hi)]; }
            $words = count(preg_split('/\s+/', $plain));
            if ($hasFilters || str_contains($message, '?') || $words > 6) { $m['flow'] = $f; return ['status' => 'digress']; }   // a different question: the AI answers it, then we come back
            $f['tries']++;
            if ($f['tries'] >= 3) { $m['flow'] = null; return ['status' => 'cancel', 'text' => self::say('dropped', $hi)]; }
            $m['flow'] = $f;
            return ['status' => 'retry', 'text' => self::say('retry', $hi).' '.self::question($f, $m, $hi)];
        }

        $f['tries'] = 0;
        $next = self::nextStep($f);
        $f['step'] = $next;
        $m['flow'] = $f;
        if ($next === 'confirm') return ['status' => 'confirm', 'text' => self::recap($f, $hi)];
        return ['status' => 'ask', 'text' => self::question($f, $m, $hi, ! $hadCar && (bool) $f['car'] || $fresh)];
    }

    /** The question for whatever is still missing (also used as the reminder after a digression). */
    public static function pending(array $m, bool $hi): string
    {
        $f = $m['flow'];
        $step = self::nextStep($f);
        return $step === 'confirm' ? self::recap($f, $hi) : self::question($f, $m, $hi);
    }

    public static function nextStep(array $f): string
    {
        if ($f['kind'] === 'enquiry') {
            if (! $f['detail'] && ! in_array($f['topic'], ['callback'], true)) return 'detail';
            return $f['time'] ? 'confirm' : 'time';
        }
        if (! $f['car']) return 'car';
        if (! $f['date']) return 'date';
        if (! $f['slot']) return 'slot';
        if (! $f['place']) return 'place';
        if ($f['place'] === 'home' && ! $f['address']) return 'address';
        return 'confirm';
    }

    /** The request AssistantCapture::save() validates and writes. */
    private static function request(array $f, array $m): array
    {
        if ($f['kind'] === 'enquiry') {
            $labels = ['sell' => 'Wants to sell their car', 'callback' => 'Callback request', 'loan' => 'Car loan / finance enquiry', 'exchange' => 'Car exchange enquiry'];
            return ['kind' => 'enquiry', 'topic' => ($labels[$f['topic']] ?? 'Enquiry').($f['detail'] ? ': '.$f['detail'] : ''), 'callback' => $f['time'], 'car' => $f['car'], 'city' => $m['f']['city'] ?? null];
        }
        return ['kind' => $f['kind'], 'car' => $f['car'], 'date' => $f['date'], 'slot' => $f['slot'], 'place' => $f['place'], 'address' => $f['address'], 'city' => $m['f']['city'] ?? null];
    }

    private static function looksLikeAnswer(string $t): bool
    {
        return mb_strlen(trim($t, " .!?")) >= 2 && ! str_contains($t, '?') && ! preg_match(self::NO, trim($t, " .!?"));
    }

    // ---- reading plain-language answers -------------------------------------------------------------------------------------

    public static function date(string $t): ?Carbon
    {
        $today = today();
        if (preg_match('/\b(20\d\d)-(\d\d)-(\d\d)\b/', $t, $x)) { try { return Carbon::createFromFormat('Y-m-d', "$x[1]-$x[2]-$x[3]")->startOfDay(); } catch (\Throwable) {} }
        if (preg_match('/\b(day after( tomorrow)?|parso|parson|perso)\b/', $t)) return $today->copy()->addDays(2);
        if (preg_match('/\b(tomorrow|tmrw|tmw|tomorow|kal)\b/', $t)) return $today->copy()->addDay();
        if (preg_match('/\b(today|aaj|aj)\b/', $t)) return $today->copy();
        if (preg_match('/\b(?:in|after)\s+(\d{1,2})\s+days?\b|\b(\d{1,2})\s+din\s+(?:baad|mein)\b/', $t, $x)) return $today->copy()->addDays((int) ($x[1] ?: $x[2]));

        $days = ['mon' => 1, 'monday' => 1, 'somvar' => 1, 'tue' => 2, 'tues' => 2, 'tuesday' => 2, 'mangalvar' => 2, 'wed' => 3, 'wednesday' => 3, 'budhvar' => 3, 'thu' => 4, 'thur' => 4, 'thurs' => 4, 'thursday' => 4, 'guruvar' => 4, 'brihaspativar' => 4, 'fri' => 5, 'friday' => 5, 'shukravar' => 5, 'sat' => 6, 'saturday' => 6, 'shanivar' => 6, 'sun' => 0, 'sunday' => 0, 'ravivar' => 0, 'itvar' => 0];
        if (preg_match('/\b(mon|monday|somvar|tue|tues|tuesday|mangalvar|wed|wednesday|budhvar|thu|thur|thurs|thursday|guruvar|brihaspativar|fri|friday|shukravar|sat|saturday|shanivar|sun|sunday|ravivar|itvar)\b/', $t, $x)) {
            $diff = ($days[$x[1]] - (int) $today->dayOfWeek + 7) % 7;
            return $today->copy()->addDays($diff === 0 ? 7 : $diff);
        }

        $months = ['jan' => 1, 'january' => 1, 'feb' => 2, 'february' => 2, 'mar' => 3, 'march' => 3, 'apr' => 4, 'april' => 4, 'may' => 5, 'jun' => 6, 'june' => 6, 'jul' => 7, 'july' => 7, 'aug' => 8, 'august' => 8, 'sep' => 9, 'sept' => 9, 'september' => 9, 'oct' => 10, 'october' => 10, 'nov' => 11, 'november' => 11, 'dec' => 12, 'december' => 12];
        $mre = implode('|', array_keys($months));
        $dm = null;
        if (preg_match('/\b(\d{1,2})(?:st|nd|rd|th)?\s*(?:of\s+)?('.$mre.')\b/', $t, $x)) $dm = [(int) $x[1], $months[$x[2]]];
        elseif (preg_match('/\b('.$mre.')\s*(\d{1,2})(?:st|nd|rd|th)?\b/', $t, $x)) $dm = [(int) $x[2], $months[$x[1]]];
        elseif (preg_match('/\b(\d{1,2})[\/\-.](\d{1,2})(?:[\/\-.]\d{2,4})?\b/', $t, $x) && (int) $x[2] <= 12 && (int) $x[1] <= 31) $dm = [(int) $x[1], (int) $x[2]];
        if ($dm) {
            try { $d = Carbon::create((int) $today->year, $dm[1], $dm[0])->startOfDay(); } catch (\Throwable) { return null; }
            return $d->lt($today) ? $d->addYear() : $d;
        }
        if (preg_match('/\b(?:on\s+)?(\d{1,2})(?:st|nd|rd|th)\b/', $t, $x) && (int) $x[1] >= 1 && (int) $x[1] <= 31) {
            for ($i = 0; $i <= 31; $i++) { $d = $today->copy()->addDays($i); if ((int) $d->day === (int) $x[1]) return $d; }
        }
        return null;
    }

    public static function slot(string $t): ?string
    {
        if (preg_match('/\b(morning|subah|subha|savere|sawere|early)\b/', $t)) return 'morning';
        if (preg_match('/\b(afternoon|dopahar|dophar|lunch|noon)\b/', $t)) return 'afternoon';
        if (preg_match('/\b(evening|shaam|sham|sandhya|night|raat)\b/', $t)) return 'evening';
        if (preg_match('/\b(\d{1,2})(?::\d\d)?\s*(am|pm|baje)\b/', $t, $x)) {
            $h = (int) $x[1];
            if ($x[2] === 'pm' && $h < 12) $h += 12;
            elseif ($x[2] === 'baje' && $h >= 1 && $h <= 8) $h += 12;       // "3 baje" / "5 baje" mean the afternoon / evening
            return $h >= 9 && $h < 12 ? 'morning' : ($h >= 12 && $h < 16 ? 'afternoon' : ($h >= 16 && $h <= 20 ? 'evening' : null));
        }
        return null;
    }

    public static function place(string $t): ?string
    {
        if (preg_match('/\b(home|ghar|house|doorstep|door step|my address|mere address|meri address|my place|mere ghar|apne ghar|my office|mere office|society|at my)\b/', $t)) return 'home';
        if (preg_match('/\b(showroom|show room|shop|dealership|dealer|store|your place|aapke yaha|apke yaha|aapke yahan|there|wahan|vahan|khud aa|aa jaunga|aunga|aaunga|aa jaungi|aaungi)\b/', $t)) return 'showroom';
        return null;
    }

    // ---- what we say --------------------------------------------------------------------------------------------------------

    private static function question(array $f, array $m, bool $hi, bool $intro = false): string
    {
        $step = self::nextStep($f);
        $kind = ['test_drive' => 'test drive', 'inspection' => 'inspection', 'enquiry' => 'enquiry'][$f['kind']];
        $car = $f['car']['t'] ?? '';
        return match ($step) {
            'car' => ! empty($m['shown'])
                ? self::say('car_pick', $hi, ['list' => collect($m['shown'])->map(fn ($x, $i) => ($i + 1).') '.$x['t'])->implode('  ')])
                : self::say('car_ask', $hi),
            'date' => self::say($intro ? 'date' : 'date_short', $hi, ['kind' => $kind, 'car' => $car]),
            'slot' => self::say('slot', $hi),
            'place' => self::say('place', $hi),
            'address' => self::say('address', $hi),
            'detail' => self::say('detail_'.$f['topic'], $hi),
            'time' => self::say('time', $hi),
            default => self::recap($f, $hi),
        };
    }

    private static function recap(array $f, bool $hi): string
    {
        if ($f['kind'] === 'enquiry') {
            $what = ['sell' => 'selling your car', 'callback' => 'a callback', 'loan' => 'a car loan', 'exchange' => 'a car exchange'][$f['topic']] ?? 'your enquiry';
            return self::say('recap_enq', $hi, ['what' => $what.($f['detail'] ? " ({$f['detail']})" : ''), 'time' => $f['time']]);
        }
        $d = Carbon::parse($f['date'])->format('D, d M');
        $where = $f['place'] === 'home' ? ($hi ? "aapke address par ({$f['address']})" : "at your address ({$f['address']})") : ($hi ? 'hamare showroom par' : 'at our showroom');
        return self::say('recap', $hi, ['kind' => $f['kind'] === 'test_drive' ? 'test drive' : 'inspection', 'car' => $f['car']['t'] ?? '', 'date' => $d, 'slot' => self::SLOT_LABEL[$f['slot']], 'where' => $where]);
    }

    public static function say(string $key, bool $hi, array $v = []): string
    {
        $s = [
            'cancelled' => ['No problem, I have cancelled that. Anything else I can help you with?', 'Koi baat nahi, maine woh cancel kar diya. Aur kuch madad chahiye?'],
            'dropped' => ["No worries - I'll leave it for now. Just say \"test drive\" whenever you're ready.", 'Koi baat nahi - abhi rehne dete hain. Jab ready ho tab "test drive" bol dena.'],
            'retry' => ["Sorry, I didn't catch that.", 'Sorry, samajh nahi aaya.'],
            'range' => ['Bookings are open for the next 30 days only.', 'Booking agle 30 din ke liye hi khuli hai.'],
            'change' => ["Sure, let's change it.", 'Theek hai, badal dete hain.'],
            'car_pick' => ['Which car is it for? {list}. Just tell me the number or the name.', 'Kaunsi car ke liye? {list}. Number ya naam bata dijiye.'],
            'car_ask' => ["Which car are you interested in? Tell me a model name, or your city and budget and I'll show you some options.", 'Kaunsi car pasand hai? Model ka naam bataiye, ya city aur budget bataiye main options dikhata hoon.'],
            'date' => ["Great, let's set up the {kind} for {car}. Which day suits you - tomorrow, Saturday, 5 Oct...?", '{car} ki {kind} set karte hain. Kaunsa din theek rahega - kal, Saturday, 5 Oct...?'],
            'date_short' => ['Which day suits you - tomorrow, Saturday, 5 Oct...?', 'Kaunsa din theek rahega - kal, Saturday, 5 Oct...?'],
            'slot' => ['What time works - morning (9-12), afternoon (12-4) or evening (4-8)?', 'Kaunsa time theek rahega - subah (9-12), dopahar (12-4) ya shaam (4-8)?'],
            'place' => ['Would you like it at our showroom, or at your address?', 'Showroom par karna hai ya aapke address par?'],
            'address' => ['Please share your full address (area and a landmark).', 'Apna poora address bataiye (area aur landmark ke saath).'],
            'detail_sell' => ['Sure, we can help you sell it. Which car is it - brand, model, year and roughly how many km driven?', 'Zaroor, bechne mein madad karenge. Kaunsi car hai - brand, model, year aur kitne km chali hai?'],
            'detail_loan' => ["Happy to help with finance. Which car are you buying, and roughly how much loan do you need?", 'Finance mein madad karenge. Kaunsi car le rahe ho aur lagbhag kitna loan chahiye?'],
            'detail_exchange' => ['Sure! Which car do you want to exchange, and which one are you planning to buy?', 'Zaroor! Kaunsi car exchange karni hai aur kaunsi leni hai?'],
            'detail_' => ['Sure! Tell me a little about what you need.', 'Zaroor! Thoda bataiye aapko kya chahiye.'],
            'time' => ['What is the best time for our team to call you?', 'Hamari team aapko kab call kare - kaunsa time theek rahega?'],
            'recap' => ['Let me confirm: {kind} for {car} on {date}, {slot}, {where}. Shall I book it? (yes / no)', 'Confirm kar lein: {car} ki {kind}, {date}, {slot}, {where}. Book kar doon? (haan / nahi)'],
            'recap_enq' => ['Let me confirm: our team will call you ({time}) about {what}. Shall I send it? (yes / no)', 'Confirm kar lein: hamari team aapko ({time}) call karegi - {what} ke liye. Bhej doon? (haan / nahi)'],
        ];
        $variants = $s[$key] ?? $s['detail_'];
        return str_replace(array_map(fn ($k) => '{'.$k.'}', array_keys($v)), array_values($v), $variants[$hi ? 1 : 0]);
    }
}
