<?php

namespace App\Services;

use App\Models\AssistantSession;
use App\Models\ChatLog;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Site assistant: own data first (knowledge base), general knowledge only when our data has no answer.
 * Built to waste as few tokens as possible: local answers for small talk / abuse, a 24h response cache,
 * trimmed context + history, and hard output caps (see AssistantGuard).
 */
class Assistant
{
    /** @return array{answer:string, source:string, links:array, cached?:bool} */
    public function reply(string $message, array $history, AssistantSession $session, string $ip, bool $voice = false, bool $degraded = false): array
    {
        $message = Str::limit(trim(preg_replace('/\s+/', ' ', $message)), AssistantGuard::limit('max_input') ?: 300, '');

        // 1) Zero-cost local answers.
        if ($local = $this->local($message, $session)) {
            return $this->finish($session, $ip, $message, $local, 'none', [], $voice, 0, 0, true);
        }

        $hits = KnowledgeBase::search($message, 3);
        if ($hits->isEmpty() && $history) { // short follow-up ("what about diesel?") loses context
            $lastUser = collect($history)->where('role', 'user')->last()['content'] ?? '';
            if ($lastUser) $hits = KnowledgeBase::search(Str::limit($lastUser, 120, '').' '.$message, 3);
        }
        $links = $hits->take(3)->map(fn ($h) => ['title' => $h->title, 'url' => KnowledgeBase::absolute($h->url), 'image' => KnowledgeBase::absolute($h->image), 'type' => $h->type])->values()->all();
        $source = $hits->isNotEmpty() ? 'kb' : 'web';

        // 2) Response cache (only for stand-alone questions, so context never leaks between conversations).
        $ckey = 'asst:resp:'.md5(Str::lower($message).'|'.$hits->pluck('id')->implode(',').'|'.(int) $voice);
        if (! $history && ($c = Cache::get($ckey))) {
            return $this->finish($session, $ip, $message, $c['answer'], $c['source'], $c['links'], $voice, 0, 0, true);
        }

        $answer = null; $in = 0; $out = 0;
        if (! $degraded && AiClient::configured()) {
            $msgs = $this->messages($message, $history, $hits, $voice, $session);
            $answer = AiClient::chat($msgs, [
                'temperature' => 0.6, 'timeout' => 40,
                'max_tokens' => $voice ? AssistantGuard::limit('max_output_voice') : AssistantGuard::limit('max_output'),
            ]);
            if ($answer) {
                $u = AiClient::lastUsage();
                $in = $u['in'] ?: AssistantGuard::estimate(collect($msgs)->pluck('content')->implode(' '));
                $out = $u['out'] ?: AssistantGuard::estimate($answer);
            }
            if ($answer && str_contains($answer, '[[WEB]]')) {
                $answer = trim(str_replace('[[WEB]]', '', $answer));
                $source = 'web'; $links = [];
            }
        }

        $fromAi = (bool) $answer;
        if (! $answer) { // AI off, failed, or site-wide budget reached: answer straight from our own data
            if ($hits->isNotEmpty()) {
                $source = 'kb';
                $answer = "Here's what I found on our site: ".$hits->take(3)->pluck('title')->implode('; ').'. Tap a link below to read more.';
            } else {
                $source = 'none'; $links = [];
                $answer = "I couldn't find that on our site yet. Try browsing our news or used cars, or tell me a bit more about what you're looking for.";
            }
        }

        if ($fromAi && ! $history) Cache::put($ckey, ['answer' => $answer, 'source' => $source, 'links' => $links], now()->addDay());

        return $this->finish($session, $ip, $message, $answer, $source, $links, $voice, $in, $out, false);
    }

    private function finish(AssistantSession $s, string $ip, string $q, string $answer, string $source, array $links, bool $voice, int $in, int $out, bool $cached): array
    {
        AssistantGuard::record($s, $in, $out, $ip);
        ChatLog::create(['session_id' => Str::limit((string) $s->token, 60, ''), 'assistant_session_id' => $s->id, 'question' => $q, 'answer' => $answer, 'source' => $source, 'voice' => $voice, 'tokens_in' => $in, 'tokens_out' => $out, 'cached' => $cached]);

        $limit = AssistantGuard::limit('msgs_day');
        return ['answer' => $answer, 'source' => $source, 'links' => $links, 'left' => max(0, $limit - $s->messages_today), 'cached' => $cached];
    }

    /** Greetings, thanks and prompt-injection attempts never reach the LLM. */
    private function local(string $m, AssistantSession $s): ?string
    {
        $first = Str::before((string) $s->lead?->name, ' ') ?: 'there';
        $t = Str::lower(trim($m, " !.?"));
        if (preg_match('/^(hi+|hello+|hey+|namaste|good (morning|afternoon|evening)|hola)$/', $t)) {
            return "Hi $first! 👋 Tell me what you're looking for — a car type, budget, or a model you like — and I'll help.";
        }
        if (preg_match('/^(thanks?|thank you|thx|ok|okay|cool|great|bye|goodbye)$/', $t)) {
            return "You're welcome, $first! Ask me anything else about cars anytime.";
        }
        if (preg_match('/(ignore (all |the )?(previous|above|prior)|system prompt|your instructions|api[ _-]?key|jailbreak|developer mode|reveal .*prompt)/i', $m)) {
            return "I can only help with cars and this website — new cars, prices, used cars, news and bookings. What would you like to know?";
        }
        return null;
    }

    private function messages(string $message, array $history, $hits, bool $voice, AssistantSession $s): array
    {
        $name = Setting::get('assistant.name', 'Auto Guide');
        $site = Setting::get('site.name', config('app.name'));
        $who = $s->lead?->name ? ' Visitor: '.Str::before($s->lead->name, ' ').($s->lead->city ? ' ('.$s->lead->city.')' : '').'.' : '';

        $persona = "You are $name, assistant of $site, an Indian car news & car marketplace.$who "
            .'Warm, natural, concise (max 3-4 sentences). Ask at most one useful follow-up (budget, city, fuel, usage). '
            .'Reply in the visitor\'s language (English/Hinglish). Prices in INR. Only discuss cars, this website and car buying/ownership; politely decline anything else. Never invent listings, prices or site facts. '
            .($voice ? 'Spoken reply: plain sentences, no markdown/lists/URLs. ' : 'Light **bold** allowed. ')
            .Setting::get('assistant.extra_instructions', '');

        if ($hits->isNotEmpty()) {
            $ctx = $hits->map(fn ($h, $i) => '['.($i + 1)."] ({$h->type}) {$h->title}\n".Str::limit($h->content, 500, '…'))->implode("\n\n");
            $persona .= "\n\nSITE DATA (use first, name the item):\n$ctx\n\nIf SITE DATA is not relevant, start your reply with [[WEB]] then answer from general automotive knowledge, noting uncertainty.";
        } else {
            $persona .= "\n\nNo site content matches. Answer from general automotive knowledge (honest about uncertainty on prices/dates) and invite them to browse our cars or news.";
        }

        $msgs = [['role' => 'system', 'content' => $persona]];
        foreach (array_slice($history, -4) as $h) {
            if (in_array($h['role'] ?? '', ['user', 'assistant'], true) && is_string($h['content'] ?? null)) {
                $msgs[] = ['role' => $h['role'], 'content' => Str::limit($h['content'], 400, '')];
            }
        }
        $msgs[] = ['role' => 'user', 'content' => $message];
        return $msgs;
    }
}
