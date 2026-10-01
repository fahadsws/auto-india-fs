<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesBulk;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\NewsSource;
use App\Services\NewsImporter;
use Illuminate\Http\Request;

class NewsSourceController extends Controller
{
    use HandlesBulk;

    public function index() { return view('admin.sources.index'); }

    public function data(Request $r)
    {
        $q = NewsSource::query()
            ->when($r->type, fn ($x, $v) => $x->where('type', $v))
            ->when($r->scope, fn ($x, $v) => $x->where('scope', $v))
            ->when($r->status === 'active', fn ($x) => $x->where('is_active', true))
            ->when($r->status === 'paused', fn ($x) => $x->where('is_active', false))
            ->when($r->status === 'error', fn ($x) => $x->where('last_status', 'like', 'error%'));

        return \App\Support\DataTable::make($q, $r, ['name', null, null, null, 'last_fetched_at', null], ['name', 'feed_url'],
            fn (NewsSource $s) => [
                '<span class="fw-medium">'.e($s->name).'</span>',
                \App\Support\Ui::badge(strtoupper($s->type), 'secondary').' '.($s->scope === 'global' ? \App\Support\Ui::badge('GLOBAL', 'info') : ''),
                '<span class="small text-muted" style="word-break:break-all">'.e(\Illuminate\Support\Str::limit($s->feed_url, 60)).'</span>',
                \App\Support\Ui::badge($s->is_active ? 'Active' : 'Paused', $s->is_active ? 'success' : 'secondary').'<br><small class="text-muted">'.e(\Illuminate\Support\Str::limit((string) $s->last_status, 55)).'</small>',
                \App\Support\Ui::date($s->last_fetched_at),
                \App\Support\Ui::actions([
                    'extra' => \App\Support\Ui::post(route('admin.sources.fetch', $s), 'Fetch now', 'ti-download'),
                    'view' => $s->feed_url, 'view_title' => 'Open source', 'edit' => route('admin.sources.edit', $s), 'delete' => route('admin.sources.destroy', $s), 'delete_msg' => 'Delete this source?',
                ]),
            ], ['name', 'asc']);
    }
    public function create() { return view('admin.sources.form', ['source' => new NewsSource(['type' => 'rss', 'is_active' => true]), 'categories' => Category::orderBy('name')->get()]); }

    public function edit(NewsSource $source) { return view('admin.sources.form', ['source' => $source, 'categories' => Category::orderBy('name')->get()]); }

    public function store(Request $r)
    {
        NewsSource::create($this->payload($r));
        return redirect()->route('admin.sources.index')->with('success', 'Source added.');
    }

    public function update(Request $r, NewsSource $source)
    {
        $source->update($this->payload($r));
        return redirect()->route('admin.sources.index')->with('success', 'Source updated.');
    }

    public function destroy(NewsSource $source)
    {
        $source->delete();
        return back()->with('success', 'Source deleted.');
    }

    /** Import every new story this source has right now (up to 12; stops at the time budget). */
    public function fetch(NewsSource $source)
    {
        @set_time_limit(290);
        $msg = (new NewsImporter())->run(12, $source->id);
        return back()->with(str_starts_with($msg, 'Error') || str_starts_with($msg, 'Skipped') ? 'error' : 'success', $msg);
    }

    private function payload(Request $r): array
    {
        $d = $r->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|in:rss,page',
            'scope' => 'required|in:india,global',
            'feed_url' => 'required|url|max:500',
            'link_pattern' => 'nullable|string|max:120',
            'category_id' => 'nullable|exists:categories,id',
        ]);
        $d['is_active'] = $r->boolean('is_active');
        return $d;
    }
    protected string $bulkRouteName = 'admin.sources.bulk';

    protected function bulkBase(Request $r): \Illuminate\Database\Eloquent\Builder { return NewsSource::query(); }

    protected function bulkActions(Request $r): array
    {
        return [
            'enable' => ['label' => 'Enable', 'do' => fn (NewsSource $m) => $m->update(['is_active' => true]) || true],
            'disable' => ['label' => 'Disable', 'do' => fn (NewsSource $m) => $m->update(['is_active' => false]) || true],
            'delete' => ['label' => 'Delete', 'danger' => true, 'confirm' => 'Articles already imported stay.', 'do' => fn (NewsSource $m) => (bool) $m->delete()],
        ];
    }
}
