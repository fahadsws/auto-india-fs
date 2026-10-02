<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\AssistantSession;
use App\Models\ChatLog;
use App\Services\AssistantMemory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Starting a new chat. Test drives, inspections and enquiries are no longer taken with manual buttons or forms:
 * the AI collects the details in conversation and saves them (see App\Services\AssistantCapture).
 */
class AssistantBookingController extends Controller
{
    public function reset(Request $r)
    {
        $s = $r->attributes->get('assistant_session');
        AssistantMemory::clear($s);
        if (! $r->boolean('wipe')) return response()->json(['ok' => true]);

        // Refresh / new chat: forget every conversation of this visitor and start a new session. The verified visitor keeps the same
        // row (so daily limits cannot be reset by refreshing) but gets a NEW token - the old one stops working.
        $others = $s->lead_id ? AssistantSession::where('lead_id', $s->lead_id)->whereKeyNot($s->id)->pluck('id') : collect();
        ChatLog::whereIn('assistant_session_id', $others->push($s->id))->delete();
        AssistantSession::whereIn('id', $others->reject(fn ($id) => $id === $s->id))->delete();
        $s->forceFill(['token' => Str::random(40)])->save();
        $r->session()->put('assistant.token', $s->token);
        return response()->json(['ok' => true, 'token' => $s->token]);
    }
}
