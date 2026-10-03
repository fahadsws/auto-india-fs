<?php

namespace App\Console\Commands;

use App\Models\VehicleModel;
use App\Services\AiClient;
use App\Services\SpecFiller;
use Illuminate\Console\Command;

class FillVehicleSpecs extends Command
{
    protected $signature = 'vehicles:fill-specs {--limit=25 : Models to process in this run} {--all : Also top up models that have some specs but fewer than the target}';
    protected $description = 'Fill empty (or thin) specs of catalog models from the official spec sheet';

    public function handle(): int
    {
        if (! AiClient::configured()) { $this->error('AI provider is not configured (Settings -> AI).'); return self::FAILURE; }
        $todo = VehicleModel::query()->where('status', '!=', 'upcoming')->get()
            ->filter(fn ($m) => ! $m->isLocked('specs') && count($m->specs ?? []) < ($this->option('all') ? SpecFiller::ENOUGH : 1))
            ->take((int) $this->option('limit'));

        foreach ($todo as $m) {
            $n = SpecFiller::fill($m, '', true);
            $this->line(($n ? "+$n  " : "0   ").$m->full_name);
        }
        $this->info($todo->count().' model(s) processed.');
        return self::SUCCESS;
    }
}
