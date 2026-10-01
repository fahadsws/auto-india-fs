<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Listing;
use App\Models\Video;
use App\Models\HomeSetting;
use App\Models\VehicleModel;
use App\Models\VehicleBrand;
use App\Models\VehicleBodyType;
use App\Models\VehicleFuel;
use App\Models\Category;

class HomeController extends Controller
{
    public function index()
    {
        $home = HomeSetting::current();
        $latest = Article::published()->with(['category', 'author'])->latest('published_at')->take(12)->get();

        return view('site.home', [
            'hero' => $latest->first(),
            'heroBanners' => collect($home->hero_banners ?? [])->values(),
            'heroBannersJson' => json_encode(collect($home->hero_banners ?? [])->map(function ($banner) {
                return [
                    $banner['tag'] ?? 'Featured',
                    $banner['text'] ?? '',
                    $banner['title'] ?? '',
                    $banner['image'] ?? '',
                    $banner['url'] ?? '#',
                ];
            })->values(), JSON_UNESCAPED_SLASHES),
            'side' => $latest->slice(1, 4),
            'more' => $latest->slice(5),
            'vehicles' => $this->vehicleBlocks($home),
            'listings' => Listing::active()->latest()->take(6)->get(),
            'videos' => Video::active()->latest('published_at')->take(4)->get(),
            'expertReviews' => Video::active()->latest('published_at')->take(4)->get(),
            'advice' => Article::published()->with('category')->whereHas('category', fn ($q) => $q->where('name', 'like', '%advice%'))->latest('published_at')->take(3)->get(),
            'brands' => \App\Models\VehicleBrand::whereIn('id', Listing::active()->whereNotNull('brand_id')->select('brand_id'))->orderBy('name')->pluck('name')->take(12),
            'homeSettings' => $home,
            'suggestedComparisons' => \App\Models\CarComparison::live()->with(['carA.brandMaster', 'carB.brandMaster'])->ordered()->take(4)->get(),
            'collections' => collect($home->collections ?? [])->values(),
            'masterBrands' => VehicleBrand::where('is_active', true)->orderBy('sort_order')->orderBy('name')->take(12)->get(),
            'masterBodyTypes' => VehicleBodyType::where('is_active', true)->orderBy('sort_order')->orderBy('name')->take(8)->get(),
            'masterFuels' => VehicleFuel::where('is_active', true)->orderBy('sort_order')->orderBy('name')->take(8)->get(),
            'newsCategories' => Category::where('is_active', true)->withCount(['articles as published_articles_count' => fn ($q) => $q->published()])->having('published_articles_count', '>', 0)->orderBy('name')->get(),
        ]);
    }

    /** Trending, just-launched and upcoming models for each vehicle type that has any, keyed by type. */
    private function vehicleBlocks(HomeSetting $home): array
    {
        $blocks = [];
        foreach (config('vehicles') as $type => $v) {
            $base = fn () => VehicleModel::published()->ofType($type)->with(['brandMaster', 'bodyType', 'fuels']);
            $ids = $home->trending($type);
            $block = [
                'label' => $v['plural'], 'path' => $v['path'],
                'trending' => $ids ? $base()->whereIn('id', $ids)->get()->sortBy(fn ($c) => array_search($c->id, $ids))->values() : collect(),
                'launched' => $base()->whereIn('status', ['launched', 'facelift'])->orderByDesc('latest_event_at')->orderByDesc('updated_at')->take(6)->get(),
                'upcoming' => $base()->where('status', 'upcoming')->orderBy('launch_date')->orderBy('name')->take(6)->get(),
            ];
            if ($block['trending']->isNotEmpty() || $block['launched']->isNotEmpty() || $block['upcoming']->isNotEmpty()) $blocks[$type] = $block;
        }
        return $blocks;
    }
}
