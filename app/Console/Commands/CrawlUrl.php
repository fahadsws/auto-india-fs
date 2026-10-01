<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Listing;
use App\Services\ListingScraper;
use App\Services\NewsImporter;
use App\Services\PageFetcher;
use Illuminate\Console\Command;

class CrawlUrl extends Command
{
    protected $signature = 'crawl:url {url : Page to crawl} {--type=article : article | listing} {--preview : Only show what would be extracted; write nothing and call no AI}';
    protected $description = 'Import one article or used-car page from a link (or preview what the crawler can extract from it)';

    public function handle(): int
    {
        $url = (string) $this->argument('url');
        $type = $this->option('type');

        if ($this->option('preview')) {
            if ($type === 'listing') {
                $page = PageFetcher::get($url);
                if (! $page || $page['status'] >= 400) { $this->error('Fetch failed: '.($page['error'] ?? 'no response')); return self::FAILURE; }
                $this->line(json_encode((new ListingScraper())->extract($page['body'], $page['url']) ?? 'Not enough vehicle facts on this page', JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                return self::SUCCESS;
            }
            $a = PageFetcher::article($url);
            if (! $a) { $this->error('Could not fetch the page.'); return self::FAILURE; }
            $this->table(['Field', 'Value'], [
                ['title', $a['title']], ['published', (string) $a['published_at']], ['author', (string) $a['author']], ['site', (string) $a['site']],
                ['words', $a['words']], ['images', count($a['images'])], ['first image', (string) $a['image']],
            ]);
            $this->line("\n".mb_substr($a['text'], 0, 1500).($a['words'] > 250 ? "\n[...]" : ''));
            return $a['words'] >= 70 ? self::SUCCESS : self::FAILURE;
        }

        @set_time_limit(280);
        $res = $type === 'listing' ? (new ListingScraper())->importUrl($url) : (new NewsImporter())->importUrl($url);
        if ($res instanceof Article) { $this->info("Imported article #{$res->id} ({$res->status}): {$res->title}"); return self::SUCCESS; }
        if ($res instanceof Listing) { $this->info("Imported listing #{$res->id}: {$res->title}"); return self::SUCCESS; }
        $this->error($res);
        return self::FAILURE;
    }
}
