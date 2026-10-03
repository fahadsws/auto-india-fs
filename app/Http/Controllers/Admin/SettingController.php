<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AiClient;
use App\Services\Automation;
use App\Services\ElevenLabs;
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
            'EMI calculator' => ['ti-calculator', [
                ['emi.default_amount', 'Default loan amount (₹)', 'number', 'Default 1000000.'],
                ['emi.min_amount', 'Minimum loan amount (₹)', 'number', 'Default 200000.'],
                ['emi.max_amount', 'Maximum loan amount (₹)', 'number', 'Default 50000000.'],
                ['emi.default_rate', 'Default interest rate (% p.a.)', 'number', 'Default 10.'],
                ['emi.default_tenure', 'Default tenure (years)', 'number', 'Default 5.'],
            ]],
            'Cost per km calculator' => ['ti-gauge', [
                ['costkm.petrol_price', 'Petrol price (₹/litre)', 'number', 'Default 105. Visitors can still edit it on the page.'],
                ['costkm.diesel_price', 'Diesel price (₹/litre)', 'number', 'Default 92.'],
                ['costkm.cng_price', 'CNG price (₹/kg)', 'number', 'Default 78.'],
                ['costkm.electric_price', 'Electricity price (₹/kWh)', 'number', 'Default 9.'],
                ['costkm.bike_rate', 'Bike running cost (₹/km)', 'number', 'Used in the "kaun sasta" comparison. Default 3.5.'],
                ['costkm.auto_rate', 'Auto-rickshaw cost (₹/km)', 'number', 'Default 12. The AI line compares the car with the Auto.'],
                ['costkm.cab_rate', 'Cab cost (₹/km)', 'number', 'Default 16.'],
            ]],
            'Roast my car' => ['ti-flame', [
                ['roast.limit_day', 'Roasts per visitor per day', 'number', 'Default 10. Each AI roast costs a few hundred tokens; when the site-wide AI budget is used up, hand-written roasts are shown instead.'],
            ]],
            'SEO & Tracking' => ['ti-seo', [
                ['seo.default_description', 'Default meta description', 'textarea', 'Used on pages that have no description of their own.'],
                ['seo.default_og_image', 'Default social share image URL', 'text', 'Used when a page has no image. 1200×630 recommended.'],
                ['seo.twitter_handle', 'X / Twitter handle', 'text', 'e.g. @automobilindia'],
                ['seo.org_name', 'Organization name (schema)', 'text', 'Falls back to the site name.'],
                ['seo.org_logo', 'Organization logo URL (schema)', 'text', ''],
                ['seo.social_profiles', 'Social profile URLs (one per line)', 'textarea', 'Facebook, Instagram, YouTube, X ... added as Organization "sameAs".'],
                ['seo.google_verification', 'Google Search Console verification code', 'text', 'The content value of the google-site-verification meta tag.'],
                ['seo.bing_verification', 'Bing Webmaster verification code', 'text', ''],
                ['seo.ga4_id', 'Google Analytics 4 ID', 'text', 'e.g. G-XXXXXXXXXX'],
                ['seo.gtm_id', 'Google Tag Manager ID', 'text', 'e.g. GTM-XXXXXXX'],
                ['seo.article_default_robots', 'New automated/imported articles: robots', 'text', 'index,follow (default), noindex,follow or noindex,nofollow. Use noindex,follow to keep imports out of Google until reviewed.'],
                ['seo.article_default_schema', 'New automated/imported articles: schema type', 'text', 'NewsArticle (default), Article, BlogPosting, Review or None.'],
                ['seo.robots_extra', 'Extra robots.txt lines', 'textarea', 'Appended to the generated robots.txt.'],
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
                ['assistant.greeting', 'Greeting line', 'text', 'Shown under "Hello there!". Default: the site name + "Smart Assistant" welcome line.'],
                ['assistant.require_lead', 'Ask visitors for their details after the free messages', 'bool', 'Saved as a lead in Admin → Leads (type "chatbot"). Visitors chat freely first, then see the details form.'],
                ['assistant.free_messages', 'Free messages before the details form (assistant & Roast my car)', 'number', 'Default 3. Set 0 to ask for details straight away.'],
                ['assistant.free_messages_mechanic', 'Free messages before the details form (Online mechanic)', 'number', 'Default 4 - a diagnosis needs a few questions.'],
                ['recaptcha.site_key', 'reCAPTCHA v3 site key', 'text', 'Protects the details form (no email code needed). Create keys at google.com/recaptcha/admin (type: reCAPTCHA v3, add your domain). Leave empty to switch the check off.'],
                ['recaptcha.secret_key', 'reCAPTCHA v3 secret key', 'secret', 'Stored encrypted. Leave blank to keep the current key.'],
                ['recaptcha.min_score', 'reCAPTCHA minimum score (0-1)', 'number', 'Default 0.5. Lower it if real visitors are blocked.'],
                ['assistant.business_facts', 'Business facts the assistant should know', 'textarea', 'Opening hours, showroom/dealer addresses, services, current offers, finance/exchange/warranty policies... one fact per line. The assistant answers questions about your business from this plus the contact details in General.'],
                ['assistant.extra_instructions', 'Extra personality / business rules', 'textarea', 'e.g. "Always suggest booking a test drive. Never discuss competitor dealerships."'],
                ['assistant.voice_greeting', 'Spoken greeting', 'text', 'Spoken once when the chat opens (if the speaker is on). {name} = assistant name. Same text for everyone, so it is cached and costs almost no ElevenLabs characters. Default (Hindi): नमस्ते! मैं {name} हूँ, आपका कार असिस्टेंट। बताइए, मैं आपकी क्या मदद करूँ?'],
                ['elevenlabs.api_key', 'ElevenLabs API key', 'text', 'Saved and shown as plain text (only admins can see this page). Create it in ElevenLabs -> Developers -> API keys with "Text to Speech" enabled. Without a key the assistant uses the browser\'s own voice.'],
                ['elevenlabs.voice_id', 'ElevenLabs voice ID', 'text', 'Must come from MY VOICES (ElevenLabs -> Voices -> My Voices -> ID). On the free plan a Voice-Library voice only works through the API after you click "Add to my voices". Click "Run setup check" below to hear it.'],
                ['elevenlabs.model', 'ElevenLabs model', 'text', 'eleven_flash_v2_5 (cheapest, fastest, supports Hindi) or eleven_multilingual_v2 (richest, best for Hinglish, uses more characters).'],
                ['elevenlabs.stability', 'Voice stability (0-1)', 'number', 'Default 0.5. Lower = more expressive, higher = steadier.'],
                ['elevenlabs.similarity', 'Voice similarity (0-1)', 'number', 'Default 0.75.'],
                ['elevenlabs.speed', 'Speaking speed (0.7-1.2)', 'number', 'Default 1.'],
                ['elevenlabs.browser_fallback', 'If ElevenLabs fails, speak with the browser voice instead', 'bool', 'Off (recommended): the assistant stays silent rather than sounding like a different voice.'],
            ]],
            'Assistant limits' => ['ti-shield-lock', [
                ['assistant.limit_per_min', 'Messages per minute (per visitor)', 'number', 'Default 8.'],
                ['assistant.limit_min_gap', 'Minimum seconds between messages', 'number', 'Default 2.'],
                ['assistant.limit_msgs_day', 'Messages per day (per visitor)', 'number', 'Default 40.'],
                ['assistant.limit_tokens_day', 'AI tokens per day (per visitor)', 'number', 'Default 40000. Counts what the provider reports.'],
                ['assistant.limit_tokens_total', 'AI tokens in total (per visitor, lifetime)', 'number', 'Default 200000.'],
                ['assistant.limit_ip_tokens_day', 'AI tokens per day (per IP address)', 'number', 'Default 80000. Stops people creating many leads to dodge limits.'],
                ['assistant.limit_global_tokens_day', 'AI tokens per day (whole site)', 'number', 'Default 500000. When reached, the assistant answers only from your own site data (zero token cost) until midnight.'],
                ['assistant.limit_tts_chars_day', 'Spoken-reply characters per day (per visitor)', 'number', 'Default 6000. Protects your ElevenLabs quota.'],
                ['assistant.limit_max_input', 'Max characters per question', 'number', 'Default 300.'],
                ['assistant.limit_max_output', 'Max reply tokens (text)', 'number', 'Default 480 (Hindi needs more tokens than English).'],
                ['assistant.limit_max_output_voice', 'Max reply tokens (voice)', 'number', 'Default 340.'],
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

    /** "Run setup check": verifies DB, code version, AI provider, ElevenLabs voice, mail and knowledge base. */
    public function check()
    {
        $rows = [];
        $add = function (string $label, string $status, string $detail, ?string $audio = null) use (&$rows) { $rows[] = ['label' => $label, 'status' => $status, 'detail' => $detail, 'audio' => $audio]; };

        $add('Database', \Illuminate\Support\Facades\Schema::hasTable('assistant_sessions') ? 'ok' : 'fail',
            \Illuminate\Support\Facades\Schema::hasTable('assistant_sessions') ? 'Assistant tables are installed.' : 'Run: php artisan migrate');
        $add('Code version', method_exists(AiClient::class, 'lastUsage') ? 'ok' : 'fail',
            method_exists(AiClient::class, 'lastUsage') ? 'All assistant files are up to date.' : 'AiClient.php on this server is old. Upload every changed file, then run php artisan optimize:clear and restart PHP/OPcache.');

        if (AiClient::configured()) {
            $t = AiClient::test();
            $add('AI provider', $t['ok'] ? 'ok' : 'fail', $t['ok'] ? 'Replied "'.trim((string) $t['reply']).'" in '.$t['ms'].' ms via '.$t['endpoint'] : ($t['error'] ?: 'No reply').' ('.$t['endpoint'].')');
        } else {
            $add('AI provider', 'fail', 'No AI API key saved (Settings -> AI provider).');
        }

        foreach (ElevenLabs::diagnose() as $row) $add($row[0], $row[1], $row[2], $row[3] ?? null);

        $mail = (string) config('mail.default');
        $add('Email (OTP codes)', in_array($mail, ['log', 'array'], true) ? 'warn' : 'ok',
            in_array($mail, ['log', 'array'], true) ? "MAIL_MAILER is \"$mail\" - codes are only written to the log. Set real MAIL_* values in .env." : "Mail driver: $mail.");
        foreach (['site.phone' => 'phone', 'site.email' => 'email', 'site.address' => 'address'] as $k => $label) {
            if (! Setting::get($k)) $add('Business '.$label, 'warn', "Settings -> General has no $label, so the assistant cannot tell visitors your $label.");
        }
        if (! Setting::get('assistant.business_facts')) $add('Business facts', 'warn', 'Empty. Add opening hours, dealer addresses, services and offers in Settings -> Assistant & voice -> Business facts.');

        // What the assistant can read from the database: records in the DB vs records indexed for text search.
        $indexed = \App\Models\KnowledgeChunk::query()->selectRaw('type, count(*) c')->groupBy('type')->pluck('c', 'type');
        $db = [
            'car' => ['New vehicles', \App\Models\VehicleModel::published()->count()],
            'listing' => ['Used cars', \App\Models\Listing::active()->count()],
            'article' => ['News articles', \App\Models\Article::published()->count()],
            'video' => ['Videos', \App\Models\Video::active()->count()],
            'webpage' => ['Pages', \App\Models\Page::published()->count()],
        ];
        foreach ($db as $type => [$label, $count]) {
            $in = (int) ($indexed[$type] ?? 0);
            $add('Data: '.$label, $in >= $count ? 'ok' : 'warn', "$count in your database, $in ready for the assistant".($in >= $count ? '.' : ' - click "Rebuild knowledge base now".'));
        }
        $add('Live stock lookup', 'ok', 'Questions like "diesel used cars in Pune under 8 lakh" or "how many cars do you have" are answered straight from the database (cars, bikes, trucks, used listings).');

        return response()->json(['rows' => $rows]);
    }

    /** Rebuild the assistant's knowledge base from the database right now. */
    public function reindex()
    {
        return response()->json(['ok' => true, 'items' => KnowledgeBase::reindexAll()]);
    }

    public function update(Request $r)
    {
        foreach (self::groups() as [, $fields]) {
            foreach ($fields as [$key, , $type]) {
                $input = $r->input(str_replace('.', '__', $key));
                if ($type === 'bool') { Setting::put($key, $r->boolean(str_replace('.', '__', $key)) ? '1' : '0'); continue; }
                if ($type === 'secret') { if ($input !== null && $input !== '' && ! str_starts_with($input, '••')) Setting::put($key, trim($input)); continue; }
                if ($key === 'elevenlabs.api_key' && $input !== null) $input = trim($input, " \t\n\r\0\x0B\"'");   // pasted keys often carry spaces/quotes
                Setting::put($key, $input === null ? null : trim($input));
            }
        }
        KnowledgeBase::syncSiteInfo();
        return back()->with('success', 'Settings saved.');
    }
}
