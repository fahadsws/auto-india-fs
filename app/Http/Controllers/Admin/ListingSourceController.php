<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesBulk;

use App\Http\Controllers\Controller;
use App\Models\ListingSource;
use App\Services\ListingImporter;
use Illuminate\Http\Request;

class ListingSourceController extends Controller
{
    use HandlesBulk;

    public function index() { return view('admin.listings.sources'); }

    public function data(Request $r)
    {
        $q = ListingSource::query()
            ->when($r->mode, fn ($x, $v) => $x->where('mode', $v))
            ->when($r->status === 'active', fn ($x) => $x->where('is_active', true))
            ->when($r->status === 'paused', fn ($x) => $x->where('is_active', false));

        return \App\Support\DataTable::make($q, $r, ['name', null, null, null, 'last_run_at', null, null], ['name', 'url'],
            fn (ListingSource $s) => [
                '<span class="fw-medium">'.e($s->name).'</span>',
                \App\Support\Ui::badge($s->mode === 'scrape' ? 'Scrape' : strtoupper($s->format), $s->mode === 'scrape' ? 'warning' : 'secondary'),
                '<span class="small text-muted" style="word-break:break-all">'.e($s->mode === 'scrape' ? count(array_filter(explode("\n", (string) $s->list_urls))).' listing page(s)' : \Illuminate\Support\Str::limit($s->url, 55)).'</span>',
                \App\Models\Listing::where('listing_source_id', $s->id)->count(),
                \App\Support\Ui::date($s->last_run_at),
                \App\Support\Ui::badge($s->is_active ? 'Active' : 'Paused', $s->is_active ? 'success' : 'secondary').'<br><small class="text-muted">'.e(\Illuminate\Support\Str::limit((string) $s->last_status, 50)).'</small>',
                \App\Support\Ui::actions([
                    'extra' => \App\Support\Ui::post(route('admin.listing-sources.run', $s), 'Run now'),
                    'view' => trim(strtok((string) ($s->mode === 'scrape' ? $s->list_urls : $s->url), "\n")), 'view_title' => 'Open source', 'edit' => route('admin.listing-sources.edit', $s), 'delete' => route('admin.listing-sources.destroy', $s), 'delete_msg' => 'Delete this source? Imported listings stay.',
                ]),
            ], ['name', 'asc']);
    }

    public function create() { return view('admin.listings.source-form', ['source' => new ListingSource(['format' => 'json', 'mode' => 'feed', 'is_active' => true, 'respect_robots' => true, 'max_per_run' => 20, 'delay_ms' => 1500, 'mapping' => []]), 'fields' => ListingImporter::FIELDS]); }

    public function edit(ListingSource $listing_source) { return view('admin.listings.source-form', ['source' => $listing_source, 'fields' => ListingImporter::FIELDS]); }

    public function store(Request $r)
    {
        ListingSource::create($this->payload($r));
        return redirect()->route('admin.listing-sources.index')->with('success', 'Listing source added.');
    }

    public function update(Request $r, ListingSource $listing_source)
    {
        $listing_source->update($this->payload($r));
        return redirect()->route('admin.listing-sources.index')->with('success', 'Listing source updated.');
    }

    public function destroy(ListingSource $listing_source)
    {
        $listing_source->delete();
        return back()->with('success', 'Source deleted.');
    }

    public function run(ListingSource $listing_source)
    {
        @set_time_limit(280);
        $msg = (new ListingImporter())->runOne($listing_source);
        return str_starts_with($msg, 'error') || str_starts_with($msg, 'no listings')
            ? back()->with('error', "{$listing_source->name}: $msg")
            : back()->with('success', "{$listing_source->name}: $msg");
    }

    /** Import a single vehicle page by link (any marketplace/dealer page with price or km on it). */
    public function importUrl(Request $r)
    {
        $d = $r->validate(['url' => 'required|url|max:700']);
        @set_time_limit(120);
        $res = (new \App\Services\ListingScraper())->importUrl($d['url']);
        return $res instanceof \App\Models\Listing
            ? back()->with('success', "Imported: {$res->title}")
            : back()->with('error', $res);
    }

    private function payload(Request $r): array
    {
        $d = $r->validate([
            'name' => 'required|string|max:100', 'url' => 'required_if:mode,feed|nullable|url|max:700',
            'format' => 'required|in:json,csv', 'items_path' => 'nullable|string|max:100',
            'mode' => 'required|in:feed,scrape', 'list_urls' => 'nullable|string|max:4000', 'detail_pattern' => 'nullable|string|max:120',
            'max_per_run' => 'nullable|integer|min:1|max:100', 'delay_ms' => 'nullable|integer|min:500|max:10000',
            'mapping' => 'nullable|array', 'mapping.*' => 'nullable|string|max:100',
        ]);
        $d['mapping'] = array_filter($d['mapping'] ?? []);
        $d['is_active'] = $r->boolean('is_active');
        $d['respect_robots'] = $r->boolean('respect_robots');
        if (empty($d["url"])) { $first = trim(strtok((string) ($d["list_urls"] ?? ""), "
")); $d["url"] = $first ?: "https://example.com"; }
        $d['max_per_run'] = $d['max_per_run'] ?? 20;
        $d['delay_ms'] = $d['delay_ms'] ?? 1500;
        return $d;
    }
    protected function bulkBase(Request $r): \Illuminate\Database\Eloquent\Builder { return ListingSource::query(); }

    protected function bulkActions(Request $r): array
    {
        return [
            'enable' => ['label' => 'Enable', 'do' => fn (ListingSource $m) => $m->update(['is_active' => true]) || true],
            'disable' => ['label' => 'Disable', 'do' => fn (ListingSource $m) => $m->update(['is_active' => false]) || true],
            'delete' => ['label' => 'Delete', 'danger' => true, 'confirm' => 'Imported listings stay.', 'do' => fn (ListingSource $m) => (bool) $m->delete()],
        ];
    }
}
