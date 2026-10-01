<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesBulk;

use App\Http\Controllers\Controller;
use App\Models\Video;
use App\Services\YouTubeService;
use Illuminate\Http\Request;

class VideoController extends Controller
{
    use HandlesBulk;

    public function index()
    {
        return view('admin.videos.index', ['channels' => Video::whereNotNull('channel')->distinct()->orderBy('channel')->pluck('channel')]);
    }

    public function data(Request $r)
    {
        $q = Video::withCount('articles')
            ->when($r->visible !== null && $r->visible !== '', fn ($x) => $x->where('is_active', (bool) $r->visible))
            ->when($r->channel, fn ($x, $c) => $x->where('channel', $c))
            ->when($r->usage === 'used', fn ($x) => $x->has('articles'))
            ->when($r->usage === 'unused', fn ($x) => $x->doesntHave('articles'));
        \App\Support\DataTable::dateRange($q, $r->range);

        return \App\Support\DataTable::make($q, $r, ['title', 'channel', null, 'created_at', null, null], ['title', 'channel', 'keyword'],
            fn (Video $v) => [
                \App\Support\Ui::thumb($v->thumbnail, \Illuminate\Support\Str::limit($v->title, 70), $v->keyword, route('admin.videos.edit', $v)),
                e($v->channel ?? '—'), $v->articles_count, \App\Support\Ui::date($v->created_at, 'd M Y'), \App\Support\Ui::yesNo($v->is_active),
                \App\Support\Ui::actions(['view' => $v->is_active ? $v->url : 'https://www.youtube.com/watch?v='.$v->youtube_id, 'view_title' => $v->is_active ? 'View on website' : 'Open on YouTube (hidden on site)', 'edit' => route('admin.videos.edit', $v), 'delete' => route('admin.videos.destroy', $v), 'delete_msg' => 'Remove this video from the library?']),
            ]);
    }

    public function create() { return view('admin.videos.create', ['apiReady' => YouTubeService::configured()]); }

    public function edit(Video $video) { return view('admin.videos.form', compact('video')); }
    public function store(Request $r)
    {
        $r->validate(['url' => 'required|string|max:300']);
        preg_match('~(?:v=|youtu\.be/|embed/|shorts/)([A-Za-z0-9_-]{11})|^([A-Za-z0-9_-]{11})$~', trim($r->url), $m);
        $id = $m[1] ?? ($m[2] ?? null);
        if (! $id) return back()->with('error', 'Could not find a YouTube video ID in that link.');

        $meta = \Illuminate\Support\Facades\Http::timeout(10)->get('https://www.youtube.com/oembed', ['url' => "https://www.youtube.com/watch?v=$id", 'format' => 'json']);
        if (! $meta->successful()) return back()->with('error', 'Video not found or embedding is disabled.');

        Video::updateOrCreate(['youtube_id' => $id], [
            'title' => $meta->json('title'), 'channel' => $meta->json('author_name'), 'thumbnail' => "https://i.ytimg.com/vi/$id/hqdefault.jpg",
            'keyword' => 'manual', 'published_at' => now(),
        ]);
        return redirect()->route('admin.videos.index')->with('success', 'Video added.');
    }

    /** Search YouTube by keyword and store the results in the library. */
    public function search(Request $r)
    {
        $r->validate(['keyword' => 'required|string|max:120']);
        try {
            $found = YouTubeService::search($r->keyword, 10);
            return redirect()->route('admin.videos.index')->with('success', count($found).' video(s) found and saved for "'.$r->keyword.'".');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function update(Request $r, Video $video)
    {
        $d = $r->validate(['title' => 'required|string|max:250', 'channel' => 'nullable|string|max:150']);
        $video->update($d + ['is_active' => $r->boolean('is_active')]);
        return redirect()->route('admin.videos.index')->with('success', 'Video updated.');
    }
    public function destroy(Video $video)
    {
        $video->delete();
        return redirect()->route('admin.videos.index')->with('success', 'Video removed.');
    }
    protected function bulkBase(Request $r): \Illuminate\Database\Eloquent\Builder { return Video::query(); }

    protected function bulkActions(Request $r): array
    {
        return [
            'enable' => ['label' => 'Enable (visible)', 'do' => fn (Video $m) => $m->update(['is_active' => true]) || true],
            'disable' => ['label' => 'Disable (hide)', 'do' => fn (Video $m) => $m->update(['is_active' => false]) || true],
            'delete' => ['label' => 'Remove from library', 'danger' => true, 'confirm' => 'Videos are only unlinked from the site; nothing is deleted on YouTube.', 'do' => fn (Video $m) => (bool) $m->delete()],
        ];
    }
}
