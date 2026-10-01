<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesBulk;
use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PageController extends Controller
{
    use HandlesBulk;

    public function index()
    {
        return view('admin.pages.index');
    }

    public function data(Request $r)
    {
        $q = Page::query()
            ->when($r->status, fn ($x, $s) => $x->where('status', $s))
            ->when($r->template, fn ($x, $t) => $x->where('template', $t));
        \App\Support\DataTable::dateRange($q, $r->range, 'updated_at');

        return \App\Support\DataTable::make($q, $r, ['title', 'slug', 'template', 'status', 'updated_at', null], ['title', 'slug'],
            fn (Page $p) => [
                '<a href="'.e(route('admin.pages.edit', $p)).'" class="fw-medium text-heading">'.e($p->title).'</a>',
                '<code>/'.e($p->slug).'</code>',
                e(Page::TEMPLATES[$p->template] ?? $p->template),
                \App\Support\Ui::status($p->is_live ? 'published' : ($p->status === 'published' ? 'scheduled' : 'draft')),
                \App\Support\Ui::date($p->updated_at),
                \App\Support\Ui::actions(['view' => $p->is_live ? $p->url : route('admin.pages.preview', $p), 'view_title' => $p->is_live ? 'View on website' : 'Preview draft',
                    'edit' => route('admin.pages.edit', $p), 'delete' => route('admin.pages.destroy', $p), 'delete_msg' => 'Delete this page? Its URL will stop working.']),
            ], ['updated_at', 'desc']);
    }

    public function create() { return view('admin.pages.form', ['page' => new Page(['template' => 'default', 'status' => 'draft', 'robots' => 'index,follow', 'schema_type' => 'WebPage', 'show_lead' => true, 'show_ads' => true, 'show_news' => true])]); }

    public function edit(Page $page) { return view('admin.pages.form', compact('page')); }

    public function store(Request $r)
    {
        $page = new Page(['author_id' => auth()->id()]);

        return $this->save($r, $page, 'Page created.');
    }

    public function update(Request $r, Page $page) { return $this->save($r, $page, 'Page saved.'); }

    public function destroy(Page $page)
    {
        $page->delete();

        return redirect()->route('admin.pages.index')->with('success', 'Page deleted.');
    }

    /** Admin-only render of a page regardless of its status (drafts, scheduled). */
    public function preview(Page $page)
    {
        return app(\App\Http\Controllers\Site\CustomPageController::class)->render($page, true);
    }

    private function save(Request $r, Page $page, string $msg)
    {
        $r->merge(['slug' => Str::slug((string) ($r->slug ?: $r->title))]);
        $d = $r->validate([
            'title' => 'required|string|max:200',
            'slug' => ['required', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('pages', 'slug')->ignore($page->id), Rule::notIn(Page::RESERVED)],
            'template' => ['required', Rule::in(array_keys(Page::TEMPLATES))],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'published_at' => 'nullable|date',
            'excerpt' => 'nullable|string|max:600',
            'body' => 'nullable|string',
            'featured_image_url' => 'nullable|string|max:500',
            'image' => 'nullable|image|max:5120',
            'meta_title' => 'nullable|string|max:120',
            'meta_description' => 'nullable|string|max:320',
            'meta_keywords' => 'nullable|string|max:300',
            'canonical_url' => 'nullable|url|max:500',
            'robots' => ['required', Rule::in(array_keys(Page::ROBOTS))],
            'og_title' => 'nullable|string|max:160',
            'og_description' => 'nullable|string|max:320',
            'og_image' => 'nullable|string|max:500',
            'schema_type' => ['required', Rule::in(array_keys(Page::SCHEMA_TYPES))],
            'schema_json' => 'nullable|string|max:20000',
            'faq' => 'nullable|array|max:50',
            'faq.*.q' => 'nullable|string|max:300',
            'faq.*.a' => 'nullable|string|max:3000',
        ], ['slug.not_in' => 'That URL is used by the site already. Choose another slug.', 'slug.regex' => 'Slug may only contain lowercase letters, numbers and hyphens.']);

        if (filled($d['schema_json'] ?? null)) {
            json_decode($d['schema_json']);
            if (json_last_error() !== JSON_ERROR_NONE) return back()->withInput()->withErrors(['schema_json' => 'Custom schema is not valid JSON: '.json_last_error_msg()]);
        }

        $image = $page->featured_image;
        if ($r->hasFile('image')) {
            $folder = public_path('uploads/pages/'.date('Y/m'));
            if (! is_dir($folder)) mkdir($folder, 0755, true);
            $name = uniqid('page_', true).'.'.$r->file('image')->getClientOriginalExtension();
            $r->file('image')->move($folder, $name);
            $image = '/uploads/pages/'.date('Y/m').'/'.$name;
        } elseif ($r->filled('featured_image_url')) {
            $image = $d['featured_image_url'];
        } elseif ($r->boolean('remove_image')) {
            $image = null;
        }

        $faq = collect($d['faq'] ?? [])->filter(fn ($f) => filled($f['q'] ?? null) && filled($f['a'] ?? null))
            ->map(fn ($f) => ['q' => trim($f['q']), 'a' => trim($f['a'])])->values()->all();

        $publishing = $d['status'] === 'published';
        $page->fill([
            'title' => $d['title'], 'slug' => $d['slug'], 'template' => $d['template'], 'status' => $d['status'],
            'published_at' => $d['published_at'] ?? null ?: ($publishing ? ($page->published_at ?? now()) : null),
            'excerpt' => $d['excerpt'] ?? null, 'body' => $d['body'] ?? null, 'featured_image' => $image,
            'meta_title' => $d['meta_title'] ?? null, 'meta_description' => $d['meta_description'] ?? null, 'meta_keywords' => $d['meta_keywords'] ?? null,
            'canonical_url' => $d['canonical_url'] ?? null, 'robots' => $d['robots'],
            'og_title' => $d['og_title'] ?? null, 'og_description' => $d['og_description'] ?? null, 'og_image' => $d['og_image'] ?? null,
            'schema_type' => $d['schema_type'], 'schema_json' => $d['schema_json'] ?? null, 'faq' => $faq,
            'show_lead' => $r->boolean('show_lead'), 'show_ads' => $r->boolean('show_ads'), 'show_news' => $r->boolean('show_news'),
        ])->save();

        return redirect()->route('admin.pages.edit', $page)->with('success', $msg);
    }

    protected function bulkBase(Request $r): \Illuminate\Database\Eloquent\Builder { return Page::query(); }

    protected function bulkActions(Request $r): array
    {
        return [
            'publish' => ['label' => 'Publish', 'do' => fn (Page $m) => $m->update(['status' => 'published', 'published_at' => $m->published_at ?? now()]) || true],
            'draft' => ['label' => 'Move to draft', 'do' => fn (Page $m) => $m->update(['status' => 'draft']) || true],
            'delete' => ['label' => 'Delete', 'danger' => true, 'confirm' => 'The selected pages will stop working on the website.', 'do' => fn (Page $m) => (bool) $m->delete()],
        ];
    }
}
