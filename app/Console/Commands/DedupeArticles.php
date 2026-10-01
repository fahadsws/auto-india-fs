<?php

namespace App\Console\Commands;

use App\Models\Article;
use Illuminate\Console\Command;

/**
 * Finds articles that cover the same car + event (e.g. three outlets' BMW 3 Series reveal), keeps the strongest one and
 * 301-redirects the rest to it (see NewsController@show). Dry run unless --apply is given. Undo: set articles.duplicate_of to NULL.
 */
class DedupeArticles extends Command
{
    protected $signature = 'articles:dedupe {--apply : write the changes (default is a dry run)}';
    protected $description = 'Merge already-published articles that report the same story into one, redirecting the others';

    public function handle(): int
    {
        // 1. Backfill story_key from the car + event the writer linked to each article.
        $filled = 0;
        foreach (Article::whereNull('story_key')->whereNull('duplicate_of')->with('vehicleModels.brandMaster')->get() as $a) {
            $m = $a->vehicleModels->first();
            $key = $m ? Article::storyKey($m->brandMaster?->name, $m->name, $m->pivot->event) : null;
            if ($key) { $filled++; if ($this->option('apply')) $a->forceFill(['story_key' => $key])->saveQuietly(); }
        }
        $this->line("story keys ".($this->option('apply') ? 'filled' : 'would be filled').": $filled");

        // 2. Group by story key, split into windows, keep one per group.
        $merged = 0;
        $all = Article::whereNull('duplicate_of')->whereIn('status', ['published', 'draft', 'scheduled'])->get(['id', 'title', 'status', 'body', 'story_key', 'source_links', 'created_at']);
        if (! $this->option('apply')) {      // a dry run must see the keys it would have written
            foreach (Article::whereNull('story_key')->whereNull('duplicate_of')->with('vehicleModels.brandMaster')->get() as $a) {
                $m = $a->vehicleModels->first();
                if ($m && ($k = Article::storyKey($m->brandMaster?->name, $m->name, $m->pivot->event))) { $all->firstWhere('id', $a->id)?->setAttribute('story_key', $k); }
            }
        }
        foreach ($all->filter(fn ($a) => $a->story_key)->groupBy(fn ($a) => str_replace('-', '', $a->story_key)) as $group) {
            if ($group->count() < 2) continue;
            $window = Article::storyWindowDays($group->first()->story_key);
            $pool = $group->sortBy('created_at')->values();
            while ($pool->isNotEmpty()) {
                $first = $pool->shift();
                $cluster = $pool->filter(fn ($a) => $a->created_at->lte($first->created_at->copy()->addDays($window)))->prepend($first);
                $pool = $pool->reject(fn ($a) => $cluster->contains('id', $a->id))->values();
                if ($cluster->count() < 2) continue;

                $keeper = $cluster->sortByDesc(fn ($a) => [$a->status === 'published', count((array) $a->source_links), mb_strlen(strip_tags($a->body)), -$a->id])->first();
                $links = collect((array) $keeper->source_links);
                $this->line("\n<info>keep #{$keeper->id}</info> {$keeper->title}");
                foreach ($cluster->where('id', '!=', $keeper->id) as $dup) {
                    $this->line("  <comment>redirect #{$dup->id}</comment> {$dup->title}");
                    $links = $links->merge((array) $dup->source_links)->unique('url');
                    if ($this->option('apply')) Article::whereKey($dup->id)->update(['duplicate_of' => $keeper->id]);
                    $merged++;
                }
                if ($this->option('apply')) Article::whereKey($keeper->id)->update(['source_links' => $links->values()->toJson()]);
            }
        }
        $this->info($this->option('apply') ? "Merged $merged duplicate article(s)." : "Dry run: $merged article(s) would be redirected. Re-run with --apply.");
        return self::SUCCESS;
    }
}
