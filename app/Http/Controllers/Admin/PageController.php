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
        ] + \App\Support\SeoRules::rules(), ['slug.not_in' => 'That URL is used by the site already. Choose another slug.', 'slug.regex' => 'Slug may only contain lowercase letters, numbers and hyphens.']);

        if ($err = \App\Support\SeoRules::schemaError($d)) return back()->withInput()->withErrors(['schema_json' => $err]);

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

        $publishing = $d['status'] === 'published';
        $page->fill([
            'title' => $d['title'], 'slug' => $d['slug'], 'template' => $d['template'], 'status' => $d['status'],
            'published_at' => $d['published_at'] ?? null ?: ($publishing ? ($page->published_at ?? now()) : null),
            'excerpt' => $d['excerpt'] ?? null, 'body' => $d['body'] ?? null, 'featured_image' => $image,
            'show_lead' => $r->boolean('show_lead'), 'show_ads' => $r->boolean('show_ads'), 'show_news' => $r->boolean('show_news'),
        ] + \App\Support\SeoRules::attributes($d))->save();

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
