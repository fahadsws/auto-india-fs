<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AutomationLog;
use App\Models\Setting;
use App\Services\Automation;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AutomationController extends Controller
{
    public function index()
    {
        $tasks = collect(Automation::TASKS)->map(fn ($t, $key) => [
            'key' => $key, 'label' => $t[0], 'every' => Automation::interval($key), 'last' => Automation::lastRun($key),
            'enabled' => Setting::bool("cron.enabled.$key", true),
        ]);

        return view('admin.automation', [
            'tasks' => $tasks,
            'cronUrl' => route('cron', ['token' => Setting::get('cron.token')]),
        ]);
    }

    public function run(Request $r)
    {
        if ($r->input('action') === 'token') {
            Setting::put('cron.token', Str::random(40));
            return back()->with('success', 'Cron token regenerated. Update your pinger with the new URL.');
        }

        @set_time_limit(280);
        $task = $r->input('task');
        abort_if($task && ! array_key_exists($task, Automation::TASKS), 422);
        $res = Automation::run($task ?: null, (bool) $task);

        $msg = collect($res)->map(fn ($v, $k) => "$k: {$v['message']}")->implode(' | ') ?: 'Nothing was due.';
        return back()->with('success', $msg);
    }
    public function logs(Request $r)
    {
        $q = AutomationLog::query()
            ->when($r->task, fn ($x, $v) => $x->where('task', $v))
            ->when($r->result, fn ($x, $v) => $x->where('status', $v));
        \App\Support\DataTable::dateRange($q, $r->range);

        return \App\Support\DataTable::make($q, $r, ['created_at', 'task', 'status', null, 'duration_ms'], ['task', 'message'],
            fn (AutomationLog $l) => [
                \App\Support\Ui::date($l->created_at), e(Automation::TASKS[$l->task][0] ?? $l->task), \App\Support\Ui::status($l->status),
                '<span class="small">'.e($l->message).'</span>', '<span class="small text-muted">'.round($l->duration_ms / 1000, 1).'s</span>',
            ]);
    }
}
