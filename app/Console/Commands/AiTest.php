<?php

namespace App\Console\Commands;

use App\Services\AiClient;
use Illuminate\Console\Command;

class AiTest extends Command
{
    protected $signature = 'ai:test';
    protected $description = 'Send a tiny request to the configured AI provider and report whether it works';

    public function handle(): int
    {
        $r = AiClient::test();
        $this->line('Endpoint: '.$r['endpoint']);
        if ($r['ok']) { $this->info("OK in {$r['ms']} ms - model replied: ".$r['reply']); return self::SUCCESS; }
        $this->error('FAILED: '.$r['error']);
        return self::FAILURE;
    }
}
