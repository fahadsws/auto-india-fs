<?php

namespace App\Services;

use App\Models\AssistantSession;
use App\Models\Lead;
use App\Models\Listing;
use App\Models\VehicleModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Conversation -> database. AssistantFlow collects the details in conversation; this validates them against real data
 * (the car must still exist, the date must be in the next 30 days, valid slot/place, address for a home visit) and only
 * then writes the lead, so nothing is ever confirmed to the visitor that was not saved.
 */
class AssistantCapture
{
    public const SLOTS = ['morning' => '9 am - 12 pm', 'afternoon' => '12 pm - 4 pm', 'evening' => '4 pm - 8 pm'];
    public const KINDS = ['test_drive' => 'Test drive', 'inspection' => 'Inspection', 'enquiry' => 'Enquiry'];
    private const PREFIX = ['test_drive' => 'TD', 'inspection' => 'IN', 'enquiry' => 'EQ'];

    /**
     * Validate and save. @return array{ok:bool, ref?:string, kind?:string, car?:?string, when?:?string, missing?:array<string>, lead?:Lead}
     */
    public static function save(AssistantSession $s, array $req, array $m, string $ip): array
    {
        $kind = (string) ($req['kind'] ?? '');
        if (! isset(self::KINDS[$kind])) return ['ok' => false, 'missing' => ['details']];
        $visitor = $s->lead;
        if (! $visitor) return ['ok' => false, 'missing' => ['contact']];

        $model = self::resolveCar($req['car'] ?? null, $m);
        $clean = fn ($v, int $n) => ($v = trim(strip_tags((string) ($v ?? '')))) === '' ? null : Str::limit($v, $n, '');
        $note = $clean($req['note'] ?? null, 300);
        $city = $clean($req['city'] ?? null, 80);
        $budget = $clean($req['budget'] ?? null, 60);
        $item = $model ? AssistantMemory::item($model) : null;
        $when = null; $bits = [];

        if ($kind === 'enquiry') {
            $topic = $clean($req['topic'] ?? null, 160);
            if (! $topic && ! $item) return ['ok' => false, 'missing' => ['topic']];
            $bits[] = 'Enquiry: '.($topic ?: 'about '.$item['t']).'.';
            if ($item) $bits[] = "Car: {$item['t']}".($item['p'] ? " ({$item['p']})" : '').'.';
            if ($budget) $bits[] = "Budget: $budget.";
            if ($city) $bits[] = "City: $city.";
            if ($cb = $clean($req['callback'] ?? null, 60)) $bits[] = "Best time to call: $cb.";
        } else {
            $missing = [];
            if (! $item) $missing[] = 'car';
            $date = self::date($req['date'] ?? null);
            if (! $date) $missing[] = 'date';
            $slot = (string) ($req['slot'] ?? '');
            if (! isset(self::SLOTS[$slot])) $missing[] = 'time';
            $place = (string) ($req['place'] ?? '');
            if (! in_array($place, ['showroom', 'home'], true)) $missing[] = 'place';
            $address = $clean($req['address'] ?? null, 200);
            if ($place === 'home' && ! $address) $missing[] = 'address';
            if ($missing) return ['ok' => false, 'missing' => $missing];

            $when = $date->format('D, d M Y').', '.self::SLOTS[$slot];
            $label = self::KINDS[$kind];
            $bits[] = "$label requested for {$item['t']}".($item['p'] ? " ({$item['p']})" : '')." on $when, ".($place === 'home' ? "at the customer's address: $address" : 'at the showroom').'.';
            if ($city) $bits[] = "City: $city.";
        }
        if ($note) $bits[] = "Note: $note";
        $bits[] = 'Captured by the AI assistant from the chat.';
        $message = implode(' ', $bits);

        $attrs = ['type' => $kind, 'name' => $visitor->name, 'phone' => $visitor->phone, 'email' => $visitor->email, 'city' => $city ?: $visitor->city,
            'listing_id' => $model instanceof Listing ? $model->id : null, 'message' => $message, 'source' => 'chatbot', 'status' => 'new', 'ip' => $ip];

        // The same visitor asking about the same thing again within a day updates the request instead of duplicating it.
        $q = Lead::where('type', $kind)->where('email', $visitor->email)->where('created_at', '>=', now()->subDay());
        if ($model instanceof Listing) $q->where('listing_id', $model->id);
        elseif ($item) $q->where('message', 'like', '%'.$item['t'].'%');
        elseif ($kind === 'enquiry') $q->where('message', 'like', '%'.Str::limit((string) ($req['topic'] ?? ''), 40, '').'%');
        $lead = $q->first();
        if ($lead) $lead->update($attrs); else $lead = Lead::create($attrs);
        LeadNotifier::notify($lead);

        return ['ok' => true, 'kind' => $kind, 'ref' => self::PREFIX[$kind].'-'.$lead->id, 'car' => $item['t'] ?? null, 'when' => $when, 'item' => $item, 'lead' => $lead];
    }

    /** The line shown to the visitor after a successful save (a real reference, never invented). */
    public static function confirmation(array $r, bool $hi): string
    {
        $what = ['test_drive' => 'Test drive', 'inspection' => 'Inspection', 'enquiry' => 'Your request'][$r['kind']];
        $tail = ($r['car'] ? ' for '.$r['car'] : '').($r['when'] ? ' ('.$r['when'].')' : '');
        return $hi
            ? "✅ $what book ho gayi{$tail}. Reference: {$r['ref']}. Hamari team aapko call karke confirm karegi."
            : "✅ $what booked{$tail}. Reference: {$r['ref']}. Our team will call you to confirm.";
    }

    /** A car number from the list we just showed (1-based), a title, or the car they picked earlier. */
    private static function resolveCar(mixed $ref, array $m)
    {
        $shown = $m['shown'] ?? [];
        $item = null;
        if (is_array($ref) && isset($ref['k'], $ref['id'])) $item = $ref;
        elseif (is_numeric($ref) && isset($shown[(int) $ref - 1])) $item = $shown[(int) $ref - 1];
        elseif (is_string($ref) && trim($ref) !== '') {
            $t = Str::lower(trim($ref));
            foreach (array_merge($m['focus'] ? [$m['focus']] : [], $shown) as $c) {
                if (Str::lower($c['t'] ?? '') === $t || str_contains(Str::lower($c['t'] ?? ''), $t) || str_contains($t, Str::lower($c['t'] ?? '~'))) { $item = $c; break; }
            }
        }
        $item ??= (($ref === null || $ref === '' || $ref === 0) ? ($m['focus'] ?? null) : null);
        return $item ? AssistantMemory::find($item) : null;
    }

    private static function date(mixed $v): ?Carbon
    {
        if (! is_string($v) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return null;
        try { $d = Carbon::createFromFormat('Y-m-d', $v)->startOfDay(); } catch (\Throwable) { return null; }
        return ($d->gte(today()) && $d->lte(today()->addDays(30))) ? $d : null;
    }
}
