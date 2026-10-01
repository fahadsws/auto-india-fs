<?php

namespace App\Services;

use App\Models\AssistantSession;
use App\Models\Listing;
use App\Models\VehicleModel;
use Illuminate\Support\Str;

/**
 * Compact per-visitor conversation state, stored on the assistant session. It replaces long chat history:
 * the AI gets a few lines ("looking for a used car in Pune under 8 lakh; shown 1..3; focus #2") instead of the whole transcript,
 * which is cheaper in tokens and never forgets what was asked or which car was picked.
 */
class AssistantMemory
{
    /** Filter keys that describe the search and survive between messages. */
    private const KEEP = ['type', 'vehicle', 'status', 'price_min', 'price_max', 'fuel', 'transmission', 'city', 'brand', 'brand_id', 'body', 'body_id', 'year', 'year_min', 'year_max', 'km_max', 'owner', 'city_any', 'budget_any'];

    public static function get(AssistantSession $s): array
    {
        return ($s->memory ?: []) + ['f' => [], 'shown' => [], 'focus' => null, 'stage' => 'discover', 'ask' => null, 'asked' => [], 'booked' => [], 'flow' => null, 'cid' => null, 'turns' => 0, 'synced' => ''];
    }

    /** Memory is a convenience: if the column is missing (migration not run yet) chat keeps working without it. */
    public static function save(AssistantSession $s, array $m): void
    {
        try {
            $s->forceFill(['memory' => $m])->save();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Assistant memory not saved (run php artisan migrate): '.$e->getMessage());
            $s->syncOriginal();
        }
    }

    public static function clear(AssistantSession $s): void
    {
        self::save($s, []);
        try { $s->forceFill(['memory' => null])->save(); } catch (\Throwable) {}
    }

    public static function wantsReset(string $message): bool
    {
        return (bool) preg_match('/\b(start over|new search|search again|something else|koi aur|kuch aur|dusri|doosri search|alag|reset|naya search)\b/i', $message);
    }

    /** Fold this message's filters into the running search. New values win; a used<->new switch starts fresh. */
    public static function merge(array $old, array $new): array
    {
        $new = array_intersect_key($new, array_flip(self::KEEP));
        if (isset($new['type'], $old['type']) && $new['type'] !== $old['type']) $old = [];
        if (isset($new['vehicle'], $old['vehicle']) && $new['vehicle'] !== $old['vehicle']) $old = [];
        if (isset($new['price_min']) || isset($new['price_max'])) unset($old['price_min'], $old['price_max'], $old['budget_any']);
        if (isset($new['city'])) unset($old['city_any']);
        return array_merge($old, $new);
    }

    /** "pehli wali", "2nd one", "isko", "Creta wali" -> one of the cars already shown (or the current focus). */
    public static function resolveReference(string $message, array $m): ?array
    {
        $shown = $m['shown'] ?? [];
        $t = Str::lower($message);
        $ordinals = [0 => '/\b(first|1st|pehl[iae]|pahl[iae]|number 1|no\.? ?1|option 1|#1)\b/', 1 => '/\b(second|2nd|d[ou]{1,2}sr[iae]|number 2|no\.? ?2|option 2|#2)\b/', 2 => '/\b(third|3rd|teesr[iae]|number 3|no\.? ?3|option 3|#3)\b/', 3 => '/\b(fourth|4th|chauth[iae]|number 4|option 4|#4)\b/', 4 => '/\b(fifth|5th|panchv[iae]|number 5|option 5|#5)\b/'];
        foreach ($ordinals as $i => $re) {
            if (preg_match($re, $t) && isset($shown[$i])) return $shown[$i];
        }
        if (preg_match('/\b(last|aakhri|aakhiri)\b/', $t) && $shown) return end($shown);

        $words = collect(preg_split('/[^\p{L}\p{N}]+/u', $t, -1, PREG_SPLIT_NO_EMPTY))->filter(fn ($w) => mb_strlen($w) >= 4 && ! in_array($w, ['test', 'drive', 'book', 'inspection', 'karna', 'karni', 'chahiye', 'wali', 'wala', 'cars', 'used', 'diesel', 'petrol', 'manual', 'automatic', 'show', 'dikhao'], true));
        foreach (array_merge($m['focus'] ? [$m['focus']] : [], $shown) as $item) {
            $title = Str::lower($item['t'] ?? '');
            if ($words->contains(fn ($w) => str_contains($title, $w))) return $item;
        }
        if (preg_match('/\b(this|this one|that one|ye wali|yeh wali|ye wala|yeh wala|isko|isse|yahi|wahi|same one|ise)\b/', $t)) {
            if ($m['focus']) return $m['focus'];
            if (count($shown) === 1) return $shown[0];
        }
        return null;
    }

    /** Short text for the AI prompt. */
    public static function block(array $m): string
    {
        $f = $m['f'] ?? [];
        $look = [];
        if ($f) {
            $look[] = 'looking for '.(($f['type'] ?? null) === 'new' ? 'a new' : (($f['type'] ?? null) === 'used' ? 'a used' : 'a')).' '.($f['vehicle'] ?? 'car').($f['body'] ?? false ? ' ('.$f['body'].')' : '');
            if (! empty($f['brand'])) $look[] = 'brand '.$f['brand'];
            if (! empty($f['fuel'])) $look[] = implode('/', $f['fuel']);
            if (! empty($f['city'])) $look[] = 'city '.$f['city'];
            if (isset($f['price_max']) || isset($f['price_min'])) $look[] = 'budget '.trim(SiteData::describe(array_intersect_key($f, array_flip(['price_min', 'price_max']))));
            if (! empty($f['year']) || ! empty($f['year_min'])) $look[] = 'year '.($f['year'] ?? 'from '.$f['year_min']);
        }
        $lines = [];
        if ($look) $lines[] = 'Wants: '.implode(', ', $look).'.';
        if (! empty($m['shown'])) $lines[] = 'Shown: '.collect($m['shown'])->map(fn ($x, $i) => ($i + 1).') '.$x['t'].($x['p'] ? ' '.$x['p'] : ''))->implode('; ').'.';
        if (! empty($m['focus'])) $lines[] = 'Selected car: '.$m['focus']['t'].($m['focus']['p'] ? ' '.$m['focus']['p'] : '').'.';
        if (! empty($m['booked'])) $lines[] = 'Already booked: '.collect($m['booked'])->map(fn ($b) => str_replace('_', ' ', $b['kind']).' for '.$b['t'].' on '.$b['date'].' (ref '.$b['ref'].')')->implode('; ').'.';
        return implode(' ', $lines);
    }

    /** Resolve the listing / new model a memory item points at (null if it no longer exists). */
    public static function find(array $item)
    {
        return ($item['k'] ?? '') === 'c' ? VehicleModel::published()->find($item['id'] ?? 0) : Listing::active()->find($item['id'] ?? 0);
    }

    public static function item($model): array
    {
        return $model instanceof VehicleModel
            ? ['k' => 'c', 'id' => $model->id, 't' => $model->full_name, 'p' => $model->price_label, 'u' => $model->url]
            : ['k' => 'l', 'id' => $model->id, 't' => $model->title, 'p' => $model->price_label, 'u' => $model->url];
    }

    /** Keep the sign-up lead enriched with what the visitor wants, so sales sees it even without a booking. */
    public static function syncLead(AssistantSession $s, array &$m): void
    {
        $lead = $s->lead;
        if (! $lead) return;
        $f = $m['f'] ?? [];
        $bits = [];
        if ($f) $bits[] = 'Looking for: '.trim(($f['type'] ?? '').' '.($f['vehicle'] ?? 'car').' '.(SiteData::describe($f) === 'no filters' ? '' : SiteData::describe($f)));
        if (! empty($m['focus'])) $bits[] = 'Interested in: '.$m['focus']['t'].' '.($m['focus']['p'] ?? '');
        $text = trim('Chat with the AI assistant. '.implode('. ', $bits));
        $hash = md5($text.($f['city'] ?? ''));
        if (($m['synced'] ?? '') === $hash || ! $bits) return;
        $upd = ['message' => Str::limit($text, 480, '')];
        if (! empty($f['city']) && ! $lead->city) $upd['city'] = $f['city'];
        $lead->forceFill($upd)->save();
        $m['synced'] = $hash;
    }
}
