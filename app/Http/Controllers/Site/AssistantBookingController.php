<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Listing;
use App\Models\VehicleModel;
use App\Services\AssistantMemory;
use App\Services\LeadNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Select a car, book a test drive / inspection (creates a real lead), reset the conversation. */
class AssistantBookingController extends Controller
{
    private const SLOTS = ['morning' => '9 am - 12 pm', 'afternoon' => '12 pm - 4 pm', 'evening' => '4 pm - 8 pm'];

    public function select(Request $r)
    {
        $d = $r->validate(['k' => 'required|in:l,c', 'id' => 'required|integer']);
        $model = AssistantMemory::find(['k' => $d['k'], 'id' => $d['id']]);
        if (! $model) return response()->json(['message' => 'That car is no longer available.'], 404);

        $s = $r->attributes->get('assistant_session');
        $m = AssistantMemory::get($s);
        $m['focus'] = AssistantMemory::item($model);
        $m['stage'] = 'interested';
        AssistantMemory::syncLead($s, $m);
        AssistantMemory::save($s, $m);

        return response()->json(['ok' => true, 'focus' => $m['focus']]);
    }

    public function reset(Request $r)
    {
        AssistantMemory::clear($r->attributes->get('assistant_session'));
        return response()->json(['ok' => true]);
    }

    public function book(Request $r)
    {
        $d = $r->validate([
            'kind' => 'required|in:test_drive,inspection',
            'k' => 'required|in:l,c',
            'id' => 'required|integer',
            'date' => 'required|date|after_or_equal:today|before_or_equal:'.now()->addDays(30)->toDateString(),
            'slot' => 'required|in:morning,afternoon,evening',
            'place' => 'required|in:showroom,home',
            'address' => 'nullable|required_if:place,home|string|max:200',
            'note' => 'nullable|string|max:300',
        ], ['date.after_or_equal' => 'Please pick today or a later date.', 'date.before_or_equal' => 'Bookings are open for the next 30 days.', 'address.required_if' => 'Please add the address for a home visit.']);

        $model = AssistantMemory::find(['k' => $d['k'], 'id' => $d['id']]);
        if (! $model) return response()->json(['message' => 'That car is no longer available. Please pick another one.'], 422);

        $s = $r->attributes->get('assistant_session');
        $visitor = $s->lead;
        $item = AssistantMemory::item($model);
        $when = Carbon::parse($d['date'])->format('D, d M Y');
        $label = $d['kind'] === 'test_drive' ? 'Test drive' : 'Inspection';
        $place = $d['place'] === 'home' ? 'at the customer\'s address: '.$d['address'] : 'at the showroom';

        $message = "$label requested for {$item['t']} ({$item['p']}) on $when, ".self::SLOTS[$d['slot']].", $place."
            .(! empty($d['note']) ? ' Note: '.$d['note'] : '').' Booked via the AI assistant.';

        $attrs = ['type' => $d['kind'], 'name' => $visitor->name, 'phone' => $visitor->phone, 'email' => $visitor->email, 'city' => $visitor->city,
            'listing_id' => $model instanceof Listing ? $model->id : null, 'message' => $message, 'source' => 'chatbot', 'status' => 'new', 'ip' => $r->ip()];

        // The same visitor re-booking the same car within a day updates the request instead of creating a duplicate.
        $lead = Lead::where('type', $d['kind'])->where('email', $visitor->email)->where('created_at', '>=', now()->subDay())
            ->where(fn ($q) => $model instanceof Listing ? $q->where('listing_id', $model->id) : $q->where('message', 'like', '%'.$item['t'].'%'))->first();
        if ($lead) $lead->update($attrs); else $lead = Lead::create($attrs);
        LeadNotifier::notify($lead);

        $ref = ($d['kind'] === 'test_drive' ? 'TD' : 'IN').'-'.$lead->id;
        $m = AssistantMemory::get($s);
        $m['focus'] = $item; $m['stage'] = 'booked';
        $m['booked'] = collect($m['booked'])->reject(fn ($b) => $b['ref'] === $ref)->push(['kind' => $d['kind'], 't' => $item['t'], 'date' => $when, 'ref' => $ref])->take(-3)->values()->all();
        AssistantMemory::save($s, $m);

        return response()->json(['ok' => true, 'ref' => $ref, 'car' => $item['t'], 'when' => $when.', '.self::SLOTS[$d['slot']], 'kind' => $label, 'phone' => $visitor->phone]);
    }
}
