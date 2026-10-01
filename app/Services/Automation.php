<?php

namespace App\Services;

use App\Models\Article;
use App\Models\AutomationLog;
use App\Models\ChatLog;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Runs the scheduled jobs. Triggered by the secret web route (/cron/{token}) hit by an external pinger,
 * or by `php artisan automation:run` from a real cron. Each task keeps its own interval.
 */
class Automation
{
    /** task => [label, default interval in minutes] */
    public const TASKS = [
        'news' => ['Fetch & publish AI news', 180],
        'publish' => ['Publish scheduled articles', 5],
        'listings' => ['Import & scrape car listings', 360],
        'availability' => ['Check listing availability (sold/removed)', 240],
        'videos' => ['Refresh YouTube library', 720],
        'reindex' => ['Rebuild AI knowledge base', 1440],
        'cleanup' => ['Clean old logs', 1440],
    ];

    public static function interval(string $task): int
    {
        return max(1, (int) Setting::get("cron.every.$task", self::TASKS[$task][1]));
    }

    public static function lastRun(string $task): ?\Carbon\Carbon
    {
        $v = Setting::get("cron.last.$task");
        return $v ? \Carbon\Carbon::parse($v) : null;
    }

    /** @return array<string,array{status:string,message:string}> */
    public static function run(?string $only = null, bool $force = false): array
    {
        $lock = Cache::lock('automation-run', 600);
        if (! $lock->get()) return ['_' => ['status' => 'skipped', 'message' => 'Another run is in progress']];

        @set_time_limit(280);
        $results = [];
        try {
            foreach (array_keys(self::TASKS) as $task) {
                if ($only && $only !== $task) continue;
                if (! $only || ! $force) {
                    $last = self::lastRun($task);
                    if (! $force && $last && $last->diffInMinutes(now()) < self::interval($task)) continue;
                }
                if (! Setting::bool("cron.enabled.$task", true) && ! $force) continue;

                $t0 = microtime(true);
                try {
                    $msg = self::execute($task);
                    $status = str_starts_with($msg, 'Skipped') ? 'skipped' : (str_starts_with($msg, 'Error') ? 'error' : 'ok');
                } catch (\Throwable $e) {
                    $msg = $e->getMessage(); $status = 'error';
                }
                Setting::put("cron.last.$task", now()->toDateTimeString());
                AutomationLog::create(['task' => $task, 'status' => $status, 'message' => substr($msg, 0, 900), 'duration_ms' => (int) ((microtime(true) - $t0) * 1000)]);
                $results[$task] = ['status' => $status, 'message' => $msg];
            }
        } finally {
            $lock->release();
        }
        return $results;
    }

    private static function execute(string $task): string
    {
        return match ($task) {
            'news' => (new NewsImporter())->run(),
            'publish' => self::publishScheduled(),
            'listings' => (new ListingImporter())->runAll(),
            'availability' => (new ListingScraper())->checkAvailability(),
            'videos' => YouTubeService::refresh(),
            'reindex' => 'Reindexed '.KnowledgeBase::reindexAll().' items.',
            'cleanup' => self::cleanup(),
        };
    }

    private static function publishScheduled(): string
    {
        $n = 0;
        foreach (Article::where('status', 'scheduled')->where('published_at', '<=', now())->get() as $a) {
            $a->update(['status' => 'published']); $n++;
        }
        return "Published $n scheduled article(s).";
    }

    private static function cleanup(): string
    {
        $autoDays = max(1, (int) Setting::get('cleanup.automation_days', 30));
        $chatDays = max(1, (int) Setting::get('cleanup.chat_days', 90));
        $done = [];

        $done['automation log(s)'] = AutomationLog::where('created_at', '<', now()->subDays($autoDays))->delete();
        $done['chat log(s)'] = ChatLog::where('created_at', '<', now()->subDays($chatDays))->delete();

        // Housekeeping tables that grow forever on the database drivers.
        if (Schema::hasTable('cache')) $done['expired cache entr(ies)'] = DB::table('cache')->where('expiration', '<', time())->delete();
        if (Schema::hasTable('sessions')) $done['expired session(s)'] = DB::table('sessions')->where('last_activity', '<', now()->subMinutes((int) config('session.lifetime', 120))->timestamp)->delete();
        if (Schema::hasTable('failed_jobs')) $done['old failed job(s)'] = DB::table('failed_jobs')->where('failed_at', '<', now()->subDays(30))->delete();

        // Application log: keep the newest ~1 MB once it passes 10 MB.
        $log = storage_path('logs/laravel.log');
        if (is_file($log) && filesize($log) > 10 * 1024 * 1024) {
            $keep = (string) file_get_contents($log, false, null, filesize($log) - 1024 * 1024);
            file_put_contents($log, substr($keep, (int) strpos($keep, "\n") + 1));
            $done['trimmed laravel.log (MB)'] = 10;
        }

        $parts = [];
        foreach ($done as $what => $n) if ($n) $parts[] = "$n $what";
        return $parts ? 'Removed '.implode(', ', $parts).'.' : "Nothing to clean (automation logs kept $autoDays days, chat logs $chatDays days).";
    }
}
