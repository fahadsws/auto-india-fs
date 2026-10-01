<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Category;

class NewsController extends Controller
{
    private function filtered($base)
    {
        $r = request();
        $since = ['today' => now()->startOfDay(), 'week' => now()->subWeek(), 'month' => now()->subMonth()][$r->period] ?? null;
        $ids = static fn ($value) => collect(is_array($value) ? $value : [$value])
            ->flatten()
            ->filter(fn ($id) => is_scalar($id) && (string) $id !== '')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();

        return $base
            ->when($r->query('category'), fn ($x, $c) => $x->whereIn('category_id', $ids($c)))
            ->when($r->query('brand'), fn ($x, $b) => $x->whereHas('vehicleModels', fn ($m) => $m->whereIn('brand_id', $ids($b))))
            ->when($since, fn ($x, $d) => $x->where('published_at', '>=', $d));
    }

    private function filterGroups(bool $withCategory = true): array
    {
        $cc = Article::published()->selectRaw('category_id, count(*) n')->groupBy('category_id')->pluck('n', 'category_id');
        $groups = [];
        if ($withCategory) {
            $groups[] = ['key' => 'category', 'title' => 'Category', 'options' => Category::orderBy('name')->get()->map(fn ($c) => ['value' => $c->id, 'label' => $c->name, 'count' => $cc[$c->id] ?? 0])->filter(fn ($o) => $o['count'])->values()->all()];
        }
        $groups[] = ['key' => 'brand', 'title' => 'Brands', 'options' => \App\Models\VehicleBrand::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get()->map(fn ($b) => ['value' => $b->id, 'label' => $b->name])->all()];
        $groups[] = ['key' => 'period', 'title' => 'Published', 'radio' => true, 'options' => [['value' => 'today', 'label' => 'Today'], ['value' => 'week', 'label' => 'This week'], ['value' => 'month', 'label' => 'This month']]];

        return $groups;
    }

    public function index()
    {
        $q = trim((string) request('q'));
        $articles = $this->filtered(Article::published()->with('category'))
            ->when($q, fn ($x) => $x->where(fn ($w) => $w->where('title', 'like', "%$q%")->orWhere('excerpt', 'like', "%$q%")))
            ->latest('published_at')->paginate(12)->withQueryString();

        return view('site.news.index', ['articles' => $articles, 'category' => null, 'q' => $q, 'filters' => $this->filterGroups()]);
    }

    public function category(Category $category)
    {
        $articles = $this->filtered(Article::published()->with('category')->where('category_id', $category->id))->latest('published_at')->paginate(12)->withQueryString();

        return view('site.news.index', ['articles' => $articles, 'category' => $category, 'q' => '', 'filters' => $this->filterGroups(false)]);
    }

    public function show(string $slug)
    {
        // A merged duplicate sends its readers and its search ranking to the article it was merged into.
        if (($dup = Article::where('slug', $slug)->whereNotNull('duplicate_of')->first()) && ($keeper = Article::published()->find($dup->duplicate_of))) {
            return redirect($keeper->url, 301);
        }
        $article = Article::published()->with(['category', 'author', 'vehicleModels' => fn ($c) => $c->published(), 'videos' => fn ($v) => $v->active()])->where('slug', $slug)->firstOrFail();
        $article->increment('views');

        $related = Article::published()->where('id', '!=', $article->id)
            ->when($article->category_id, fn ($q) => $q->where('category_id', $article->category_id))
            ->latest('published_at')->take(3)->get();

        return view('site.news.show', compact('article', 'related'));
    }
}
