<?php

namespace App\Console\Commands;

use App\Services\DataReset as Reset;
use Illuminate\Console\Command;

class DataReset extends Command
{
    protected $signature = 'data:reset {--keep=* : Also keep these tables (menu_items, home_settings, seo_entries, pages)} {--force : Run without asking (required in production)}';
    protected $description = 'Truncate every data table except users, settings, roles/permissions, sessions and migrations';

    public function handle(): int
    {
        $keep = (array) $this->option('keep');
        $counts = Reset::counts($keep);
        $this->table(['Table', 'Rows to delete'], collect($counts)->map(fn ($n, $t) => [$t, $n])->values()->all());

        if (! $this->option('force')) {
            if (app()->isProduction()) { $this->error('Refusing to run in production without --force.'); return self::FAILURE; }
            if (! $this->confirm('Delete all of the above? This cannot be undone.')) return self::FAILURE;
        }

        $removed = Reset::run($keep);
        $this->info('Cleared '.count($removed).' tables ('.number_format(array_sum($removed)).' rows). Kept: '.implode(', ', Reset::KEEP).'.');
        return self::SUCCESS;
    }
}
