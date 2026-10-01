<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Automation;

class CronController extends Controller
{
    /**
     * GET /cron/{token}          -> runs every task that is due
     * GET /cron/{token}/{task}   -> forces one task (news|publish|listings|videos|reindex|cleanup)
     */
    public function __invoke(string $token, ?string $task = null)
    {
        $expected = (string) Setting::get('cron.token', '');
        abort_unless($expected !== '' && hash_equals($expected, $token), 404);
        abort_if($task !== null && ! array_key_exists($task, Automation::TASKS), 404);

        return response()->json([
            'ran_at' => now()->toIso8601String(),
            'results' => Automation::run($task, $task !== null),
        ]);
    }
}
