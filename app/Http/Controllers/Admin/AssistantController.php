<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssistantFeedback;
use App\Models\AssistantSession;
use App\Models\ChatLog;
use App\Services\AssistantGuard;

class AssistantController extends Controller
{
    public function index()
    {
        $global = AssistantGuard::todayGlobalTokens();
        return view('admin.assistant.index', [
            'sessions' => AssistantSession::with('lead')->orderByDesc('last_message_at')->orderByDesc('id')->paginate(25),
            'globalTokens' => $global,
            'globalLimit' => AssistantGuard::limit('global_tokens_day'),
            'chatsToday' => ChatLog::whereDate('created_at', today())->count(),
            'cachedToday' => ChatLog::whereDate('created_at', today())->where('cached', true)->count(),
            'avgRating' => round((float) AssistantFeedback::whereNotNull('rating')->avg('rating'), 1),
            'feedback' => AssistantFeedback::latest()->limit(10)->get(),
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
