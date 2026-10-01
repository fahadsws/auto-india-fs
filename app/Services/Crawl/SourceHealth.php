<?php

namespace App\Services\Crawl;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Keeps each source's track record so one broken site cannot waste every run:
 * consecutive failures trigger an exponential cool-down (30 min, 1 h, 2 h ... capped at 12 h), and any
 * success resets it. Works for NewsSource (last_fetched_at) and ListingSource (last_run_at).
 */
class SourceHealth
{
    private const FREE_FAILS = 2;      // failures tolerated before the cool-down starts
    private const MAX_COOLDOWN_MIN = 720;

    public static function ok(Model $s, string $message, int $found = 0): void
    {
        $s->update([self::tsColumn($s) => now(), 'last_status' => Str::limit('ok'.($message !== '' ? ': '.$message : ''), 250, ''), 'fail_count' => 0, 'last_success_at' => now(), 'last_found' => $found]);
    }

    public static function fail(Model $s, string $message): void
    {
        $s->update([self::tsColumn($s) => now(), 'last_status' => Str::limit('error: '.$message, 250, ''), 'fail_count' => min(65000, (int) $s->fail_count + 1)]);
    }

    /** True while a repeatedly failing source is cooling down. Forced runs (admin "Fetch now") should ignore this. */
    public static function coolingDown(Model $s): bool
    {
        $fails = (int) $s->fail_count;
        if ($fails <= self::FREE_FAILS) return false;
        $last = $s->{self::tsColumn($s)};
        if (! $last) return false;
        $wait = min(self::MAX_COOLDOWN_MIN, 30 * (2 ** ($fails - self::FREE_FAILS - 1)));
        return Carbon::parse($last)->addMinutes($wait)->isFuture();
    }

    private static function tsColumn(Model $s): string
    {
        return $s->getTable() === 'listing_sources' ? 'last_run_at' : 'last_fetched_at';
    }
}
