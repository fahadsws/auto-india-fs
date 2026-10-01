<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesBulk;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    use HandlesBulk;

    public function index() { return view('admin.categories.index'); }

    public function data(Request $r)
    {
        $q = Category::withCount('articles')
            ->when($r->usage === 'used', fn ($x) => $x->has('articles'))
            ->when($r->usage === 'empty', fn ($x) => $x->doesntHave('articles'))
            ->when($r->state === 'enabled', fn ($x) => $x->where('is_active', true))
            ->when($r->state === 'disabled', fn ($x) => $x->where('is_active', false));

        return \App\Support\DataTable::make($q, $r, ['name', 'is_active', 'articles_count', 'created_at', null], ['name'],
            fn (Category $c) => [
                '<span class="fw-medium">'.e($c->name).'</span>', \App\Support\Ui::badge($c->is_active ? 'Enabled' : 'Disabled', $c->is_active ? 'success' : 'secondary'), $c->articles_count,
                \App\Support\Ui::date($c->created_at, 'd M Y'),
                \App\Support\Ui::actions(['view' => route('news.category', $c), 'view_title' => 'View on website', 'edit' => route('admin.categories.edit', $c), 'delete' => route('admin.categories.destroy', $c), 'delete_msg' => 'Articles in this category become uncategorised.']),
            ], ['name', 'asc']);
    }

    public function create() { return view('admin.categories.form', ['category' => new Category()]); }

    public function edit(Category $category) { return view('admin.categories.form', compact('category')); }
    public function store(Request $r)
    {
        $d = $r->validate(['name' => 'required|string|max:80|unique:categories,name']);
        Category::create($d + ['slug' => Str::slug($d['name']), 'is_active' => $r->boolean('is_active')]);
        return redirect()->route('admin.categories.index')->with('success', 'Category added.');
    }
    public function update(Request $r, Category $category)
    {
        $d = $r->validate(['name' => 'required|string|max:80|unique:categories,name,'.$category->id]);
        $category->update($d + ['slug' => Str::slug($d['name']), 'is_active' => $r->boolean('is_active')]);
        return redirect()->route('admin.categories.index')->with('success', 'Category updated.');
    }
    public function destroy(Category $category)
    {
        $category->delete();
        return redirect()->route('admin.categories.index')->with('success', 'Category deleted.');
    }
    protected function bulkBase(Request $r): \Illuminate\Database\Eloquent\Builder { return Category::query(); }

    protected function bulkActions(Request $r): array
    {
        return [
            'enable' => ['label' => 'Enable (show on website)', 'do' => fn (Category $m) => $m->update(['is_active' => true]) || true],
            'disable' => ['label' => 'Disable (hide from menus)', 'confirm' => 'Articles stay published; the category just disappears from menus and filters.', 'do' => fn (Category $m) => $m->update(['is_active' => false]) || true],
            'delete' => ['label' => 'Delete', 'danger' => true, 'confirm' => 'Articles in these categories become uncategorised.', 'do' => fn (Category $m) => (bool) $m->delete()],
        ];
    }
}
