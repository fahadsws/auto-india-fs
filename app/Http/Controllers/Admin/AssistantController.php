<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssistantFeedback;
use App\Models\AssistantSession;
use App\Models\ChatLog;
use App\Services\AssistantGuard;
use Illuminate\Http\Request;

class AssistantController extends Controller
{
    /** Visitor filters shared by the list and the feedback box: name / email / phone (partial match) and a last-active date range. */
    private function visitors(Request $r)
    {
        $like = fn ($v) => '%'.str_replace(['%', '_'], ['\%', '\_'], trim((string) $v)).'%';
        return AssistantSession::query()
            ->when($r->filled('name'), fn ($q) => $q->whereHas('lead', fn ($l) => $l->where('name', 'like', $like($r->name))))
            ->when($r->filled('email'), fn ($q) => $q->whereHas('lead', fn ($l) => $l->where('email', 'like', $like($r->email))))
            ->when($r->filled('phone'), fn ($q) => $q->whereHas('lead', fn ($l) => $l->where('phone', 'like', $like(preg_replace('/[^0-9+]/', '', (string) $r->phone) ?: $r->phone))))
            ->when($r->filled('range'), function ($q) use ($r) {
                $parts = preg_split('/\s+(?:to|—|–)\s+/', trim((string) $r->range));
                try {
                    $from = \Carbon\Carbon::parse($parts[0])->startOfDay();
                    $to = \Carbon\Carbon::parse($parts[1] ?? $parts[0])->endOfDay();
                    $q->whereRaw('COALESCE(last_message_at, created_at) BETWEEN ? AND ?', [$from, $to]);
                } catch (\Throwable) {
                    // Ignore an incomplete Flatpickr value.
                }
            });
    }

    public function index(Request $r)
    {
        $r->validate(['name' => 'nullable|string|max:100', 'email' => 'nullable|string|max:150', 'phone' => 'nullable|string|max:30', 'range' => 'nullable|string|max:50']);
        $global = AssistantGuard::todayGlobalTokens();
        $filtered = $r->hasAny(['name', 'email', 'phone', 'range']) && $r->anyFilled(['name', 'email', 'phone', 'range']);
        return view('admin.assistant.index', [
            'sessions' => $this->visitors($r)->with('lead')->orderByDesc('last_message_at')->orderByDesc('id')->paginate(25)->withQueryString(),
            'filtered' => $filtered,
            'globalTokens' => $global,
            'globalLimit' => AssistantGuard::limit('global_tokens_day'),
            'chatsToday' => ChatLog::whereDate('created_at', today())->count(),
            'cachedToday' => ChatLog::whereDate('created_at', today())->where('cached', true)->count(),
            'avgRating' => round((float) AssistantFeedback::whereNotNull('rating')->avg('rating'), 1),
            'feedback' => AssistantFeedback::with('session.lead')->when($filtered, fn ($q) => $q->whereIn('assistant_session_id', $this->visitors($r)->select('id')))->latest()->limit(15)->get(),
        ]);
    }

    public function show(AssistantSession $session)
    {
        return view('admin.assistant.show', ['s' => $session->load('lead'), 'logs' => ChatLog::where('assistant_session_id', $session->id)->orderBy('id')->limit(200)->get()]);
    }

    public function block(AssistantSession $session)
    {
        $blocked = $session->blocked_until && $session->blocked_until->isFuture();
        $session->update(['blocked_until' => $blocked ? null : now()->addYears(10)]);
        return back()->with('success', $blocked ? 'Visitor unblocked.' : 'Visitor blocked from the assistant.');
    }

    public function reset(AssistantSession $session)
    {
        $session->update(['messages_today' => 0, 'tokens_today' => 0, 'tts_chars_today' => 0, 'strikes' => 0]);
        return back()->with('success', "Today's usage reset.");
    }
}
