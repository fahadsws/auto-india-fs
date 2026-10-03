<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\HomeSetting;
use App\Services\AssistantGuard;
use App\Services\Mechanic;
use Illuminate\Http\Request;

/** "Online mechanic": AI chat that diagnoses a car problem and gives a fair, city-aware cost. A few free messages, then the same one-time lead + OTP step as the assistant. */
class MechanicController extends Controller
{
    public function page()
    {
        return view('site.mechanic', [
            'homeSettings' => HomeSetting::current(),
            'latest' => Article::published()->latest('published_at')->take(5)->get(),
        ]);
    }

    public function chat(Request $r)
    {
        $d = $r->validate([
            'message' => 'required|string|min:1|max:500',
            'history' => 'nullable|array|max:16',
            'history.*.role' => 'required|in:user,assistant',
            'history.*.content' => 'required|string|max:900',
            'profile' => 'nullable|array',
            'profile.car' => 'nullable|string|max:80',
            'profile.age' => 'nullable|string|max:30',
            'profile.km' => 'nullable|string|max:30',
            'profile.fuel' => 'nullable|in:'.implode(',', Mechanic::FUELS),
            'profile.city' => 'nullable|string|max:60',
        ]);

        $message = Mechanic::clean($d['message'], 500);
        if ($message === '') return response()->json(['message' => 'Please describe the problem with your car.'], 422);
        if (Mechanic::isUnsafe($message)) return response()->json(['message' => 'Please describe the car problem in plain words. I am unable to respond to abusive or unrelated messages.'], 422);

        $profile = [];
        foreach (['car' => 80, 'age' => 30, 'km' => 30, 'city' => 60] as $k => $n) $profile[$k] = Mechanic::clean((string) data_get($d, "profile.$k", ''), $n);
        $profile['fuel'] = (string) data_get($d, 'profile.fuel', '');
        $history = collect($d['history'] ?? [])->map(fn ($h) => ['role' => $h['role'], 'content' => Mechanic::clean($h['content'], 700)])->filter(fn ($h) => $h['content'] !== '')->values()->all();

        $s = $r->attributes->get('assistant_session');
        $lock = AssistantGuard::lock($s);   // one in-flight request per visitor
        if (! $lock) return response()->json(['message' => 'Still working on your previous question. One moment, please.', 'reason' => 'busy'], 429);

        try {
            $s->refresh();
            $verdict = AssistantGuard::admit($s, $message.' '.json_encode($history), (string) $r->ip());
            if (! $verdict['allow']) {
                return response()->json(['message' => $verdict['message'], 'reason' => $verdict['reason'], 'retry_after' => $verdict['retry_after']], 429);
            }
            if ($verdict['degraded']) {   // site-wide AI budget used up: no model call
                AssistantGuard::record($s, 0, 0, (string) $r->ip());
                return response()->json(['ok' => true, 'degraded' => true, 'reply' => Mechanic::SAFE_NOTE, 'quick_replies' => [], 'facts' => [], 'stage' => 'asking', 'diagnosis' => null, 'left' => $this->left($s)]);
            }

            $res = Mechanic::turn($profile, $history, $message);
            AssistantGuard::record($s, $res['tokens'], 0, (string) $r->ip());
            $s->refresh();

            if (! $res['ok']) {
                $msg = $res['error'] === 'off' ? Mechanic::SAFE_NOTE : Mechanic::RETRY_NOTE;
                return response()->json(['ok' => true, 'degraded' => true, 'reply' => $msg, 'quick_replies' => [], 'facts' => [], 'stage' => 'asking', 'diagnosis' => null, 'left' => $this->left($s)]);
            }
            return response()->json(['ok' => true] + $res['data'] + ['left' => $this->left($s), 'token' => $s->token]);
        } catch (\Throwable $e) {   // never show a raw exception to the owner
            \Illuminate\Support\Facades\Log::error('Mechanic chat failed: '.$e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'The mechanic could not respond just now. Please try again in a moment.', 'reason' => 'error'], 503);
        } finally {
            $lock->release();
        }
    }

    private function left($s): int
    {
        return max(0, AssistantGuard::limit('msgs_day') - ($s->usage_date?->isToday() ? $s->messages_today : 0));
    }
}
