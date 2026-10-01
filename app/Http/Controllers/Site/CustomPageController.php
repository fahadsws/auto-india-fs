<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\HomeSetting;
use App\Models\Page;

class CustomPageController extends Controller
{
    public function show(string $slug)
    {
        return $this->render(Page::published()->where('slug', $slug)->firstOrFail());
    }

    public function render(Page $page, bool $preview = false)
    {
        $hasSidebar = $page->template !== 'no-sidebar';

        return view('site.page', [
            'page' => $page,
            'preview' => $preview,
            'homeSettings' => $page->show_ads ? HomeSetting::current() : null,
            'latest' => $page->show_news ? Article::published()->latest('published_at')->take(5)->get() : collect(),
            'hasSidebar' => $hasSidebar,
        ]);
    }
}
