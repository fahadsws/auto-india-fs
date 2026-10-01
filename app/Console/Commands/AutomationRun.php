<?php

namespace App\Console\Commands;

use App\Services\Automation;
use Illuminate\Console\Command;

class AutomationRun extends Command
{
    protected $signature = 'automation:run {task? : news|publish|listings|videos|reindex|cleanup} {--force : Ignore the interval}';
    protected $description = 'Run due automation tasks (news, listings, videos, knowledge base)';

    public function handle(): int
    {
        $results = Automation::run($this->argument('task'), (bool) $this->option('force') || (bool) $this->argument('task'));
        foreach ($results as $task => $r) {
            $this->line(sprintf('[%s] %s: %s', $r['status'], $task, $r['message']));
        }
        if (! $results) $this->info('Nothing due.');
        return self::SUCCESS;
    }
}
