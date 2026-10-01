<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Automation;
use App\Services\KnowledgeBase;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /** group => [icon, fields]; field = [key, label, type(text|number|textarea|bool|secret), help] */
    public static function groups(): array
    {
        $cron = [];
        foreach (Automation::TASKS as $task => [$label, $default]) {
            $cron[] = ["cron.enabled.$task", "$label - enabled", 'bool', ''];
            $cron[] = ["cron.every.$task", "$label - run every (minutes)", 'number', "Default $default"];
        }

        return [
            'General' => ['ti-building-store', [
                ['site.name', 'Site name', 'text', ''],
                ['site.tagline', 'Tagline', 'text', ''],
                ['site.about', 'About the business', 'textarea', 'Also used by the AI assistant to answer "who are you" questions.'],
                ['site.email', 'Contact email', 'text', ''],
                ['site.phone', 'Contact phone', 'text', ''],
                ['site.address', 'Address', 'text', ''],
                ['leads.notify_emails', 'Sales team emails (comma separated)', 'text', 'New enquiries are emailed here. Configure SMTP in .env (MAIL_*) for real delivery.'],
            ]],
            'AI provider' => ['ti-sparkles', [
                ['ai.base_url', 'API base URL', 'text', 'Any OpenAI-compatible endpoint. OpenRouter: https://openrouter.ai/api/v1 · Groq: https://api.groq.com/openai/v1 · Gemini: https://generativelanguage.googleapis.com/v1beta/openai'],
                ['ai.api_key', 'API key', 'secret', 'Stored encrypted. Leave blank to keep the current key.'],
                ['ai.model', 'Primary model', 'text', 'Use a ":free" model on OpenRouter to keep costs at zero.'],
                ['ai.fallback_model', 'Fallback model', 'text', 'Used automatically if the primary model fails or is rate-limited.'],
            ]],
            'Assistant & voice' => ['ti-microphone', [
                ['assistant.enabled', 'Show the AI assistant on the site', 'bool', ''],
                ['assistant.name', 'Assistant name', 'text', ''],
                ['assistant.extra_instructions', 'Extra personality / business rules', 'textarea', 'e.g. "Always suggest booking a test drive. Never discuss competitor dealerships."'],
                ['elevenlabs.api_key', 'ElevenLabs API key', 'secret', 'Optional. Without it the assistant speaks with the browser\'s free built-in voice.'],
                ['elevenlabs.voice_id', 'ElevenLabs voice ID', 'text', ''],
                ['elevenlabs.model', 'ElevenLabs model', 'text', 'eleven_flash_v2_5 is the cheapest and fastest.'],
            ]],
            'News automation' => ['ti-news', [
                ['news.per_run', 'Articles to publish per run', 'number', 'Total across all sources each time the news task runs.'],
                ['news.auto_publish', 'Publish automatically (otherwise save as draft for review)', 'bool', ''],
                ['news.create_cars', 'Let news create new cars in the catalog', 'bool', 'Off (default): news runs never add a car model; they only update cars that already exist. Turn on to auto-create cars from launch/facelift stories.'],
                ['news.rewrite_mode', 'Rewrite style', 'text', '"faithful" (default) keeps every fact and the meaning and only changes the wording; "original" writes a fresh editorial piece from the facts.'],
                ['news.max_age_days', 'Ignore stories older than (days)', 'number', 'Default 3. Keeps the desk on the latest news.'],
            ]],
            'YouTube' => ['ti-brand-youtube', [
                ['youtube.api_key', 'YouTube Data API key', 'secret', 'Free quota: 10,000 units/day = about 100 searches. Only needed for auto-discovery; you can always add videos by link.'],
                ['youtube.channel_id', 'Limit search to one channel ID (optional)', 'text', ''],
                ['youtube.keywords', 'Keywords to keep the library fresh (one per line)', 'textarea', ''],
                ['youtube.auto_attach', 'Auto-attach matching videos to articles', 'bool', ''],
            ]],
            'Task schedule' => ['ti-clock', array_merge($cron, [
                ['cleanup.automation_days', 'Clean-up: keep automation run logs (days)', 'number', 'Default 30.'],
                ['cleanup.chat_days', 'Clean-up: keep assistant chat logs (days)', 'number', 'Default 90.'],
            ])],
        ];
    }

    public function edit()
    {
        $values = [];
        foreach (self::groups() as [, $fields]) {
            foreach ($fields as [$key, , $type]) {
                $values[$key] = $type === 'secret' ? (Setting::get($key) ? '••••••••' : '') : Setting::get($key);
            }
        }
        return view('admin.settings', ['groups' => self::groups(), 'values' => $values]);
    }

    public function update(Request $r)
    {
        foreach (self::groups() as [, $fields]) {
            foreach ($fields as [$key, , $type]) {
                $input = $r->input(str_replace('.', '__', $key));
                if ($type === 'bool') { Setting::put($key, $r->boolean(str_replace('.', '__', $key)) ? '1' : '0'); continue; }
                if ($type === 'secret') { if ($input !== null && $input !== '' && ! str_starts_with($input, '••')) Setting::put($key, trim($input)); continue; }
                Setting::put($key, $input === null ? null : trim($input));
            }
        }
        KnowledgeBase::syncSiteInfo();
        return back()->with('success', 'Settings saved.');
    }
}
