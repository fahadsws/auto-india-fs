<?php

namespace App\Services;

use App\Models\ChatLog;
use App\Models\Setting;
use Illuminate\Support\Str;

/**
 * Site assistant: own data first (knowledge base), general knowledge only when our data has no answer.
 */
class Assistant
{
    /** @return array{answer:string, source:string, links:array} */
    public function reply(string $message, array $history, string $session, bool $voice = false): array
    {
        $message = Str::limit(trim($message), 600, '');
        $hits = KnowledgeBase::search($message, 5);

        // Short follow-ups ("what about diesel?") lose context — enrich the search with the last user turn.
        if ($hits->isEmpty() && $history) {
            $lastUser = collect($history)->where('role', 'user')->last()['content'] ?? '';
            if ($lastUser) $hits = KnowledgeBase::search($lastUser.' '.$message, 5);
        }

        $links = $hits->take(3)->map(fn ($h) => ['title' => $h->title, 'url' => KnowledgeBase::absolute($h->url), 'image' => KnowledgeBase::absolute($h->image), 'type' => $h->type])->values()->all();
        $source = $hits->isNotEmpty() ? 'kb' : 'web';
        $answer = null;

        if (AiClient::configured()) {
            $answer = AiClient::chat($this->messages($message, $history, $hits, $voice), ['temperature' => 0.7, 'max_tokens' => $voice ? 350 : 700, 'timeout' => 45]);
            if ($answer && str_contains($answer, '[[WEB]]')) {
                $answer = trim(str_replace('[[WEB]]', '', $answer));
                $source = 'web'; $links = [];
            }
        }

        if (! $answer) { // AI not configured or failed: answer straight from our own data
            if ($hits->isNotEmpty()) {
                $source = 'kb';
                $answer = "Here's what I found on our site: ".$hits->take(3)->pluck('title')->implode('; ').'. Tap a link below to read more.';
            } else {
                $source = 'none'; $links = [];
                $answer = "I couldn't find that on our site yet. Try browsing our news or used cars, or tell me a bit more about what you're looking for.";
            }
        }

        ChatLog::create(['session_id' => Str::limit($session, 60, ''), 'question' => $message, 'answer' => $answer, 'source' => $source, 'voice' => $voice]);

        return ['answer' => $answer, 'source' => $source, 'links' => $links];
    }

    private function messages(string $message, array $history, $hits, bool $voice): array
    {
        $name = Setting::get('assistant.name', 'Auto Guide');
        $site = Setting::get('site.name', config('app.name'));

        $persona = "You are $name, the friendly assistant of $site, an Indian car news and used-car marketplace. "
            ."Talk like a real, warm, knowledgeable car-enthusiast friend - natural and conversational, never robotic or stiff. "
            ."Keep replies short (2-5 sentences). Ask ONE relevant follow-up question when it helps (budget, city, fuel type, usage, family size) to narrow down what the visitor wants. "
            ."Mirror the visitor's language (English or Hinglish). Prices are in Indian rupees. Never invent listings, prices or facts about our site. "
            .($voice ? 'Your reply will be spoken aloud: use plain sentences only, no markdown, no bullet points, no URLs. ' : 'You may use short paragraphs and **bold** sparingly. ')
            .Setting::get('assistant.extra_instructions', '');

        if ($hits->isNotEmpty()) {
            $ctx = $hits->map(fn ($h, $i) => '['.($i + 1)."] ({$h->type}) {$h->title}\n".Str::limit($h->content, 900, '…'))->implode("\n\n");
            $persona .= "\n\nSITE DATA (our own content - use this FIRST for anything about our cars, listings, articles or videos):\n$ctx\n\n"
                ."Rules: answer from SITE DATA when it is relevant, and mention the item by name so the visitor can open it. "
                ."If SITE DATA is NOT relevant to the question, begin your reply with the exact token [[WEB]] and then answer from general automotive knowledge, saying you're not certain where appropriate.";
        } else {
            $persona .= "\n\nOur site has no matching content for this question. Answer from general automotive knowledge (be honest about uncertainty, especially for prices and dates) and, where natural, invite them to browse our news or used cars.";
        }

        $msgs = [['role' => 'system', 'content' => $persona]];
        foreach (array_slice($history, -8) as $h) {
            if (in_array($h['role'] ?? '', ['user', 'assistant'], true) && is_string($h['content'] ?? null)) {
                $msgs[] = ['role' => $h['role'], 'content' => Str::limit($h['content'], 800, '')];
            }
        }
        $msgs[] = ['role' => 'user', 'content' => $message];
        return $msgs;
    }
}
