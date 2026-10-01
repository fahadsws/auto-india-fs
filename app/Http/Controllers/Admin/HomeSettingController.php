<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HomeSetting;
use App\Models\VehicleModel;
use Illuminate\Http\Request;

class HomeSettingController extends Controller
{
    /** Latest models offered in each trending picker besides the ones already selected. */
    private const RECENT = 10;

    public function edit()
    {
        $settings = HomeSetting::current();
        $pickers = [];
        foreach (config('vehicles') as $type => $v) {
            $saved = $settings->trending($type);
            $selected = $saved ? VehicleModel::published()->ofType($type)->with('brandMaster')->whereIn('id', $saved)->get()->sortBy(fn ($m) => array_search($m->id, $saved))->values() : collect();
            // Saved picks first (even when old), then the latest models not already picked.
            $recent = VehicleModel::published()->ofType($type)->with('brandMaster')->whereNotIn('id', $saved)
                ->orderByDesc('latest_event_at')->orderByDesc('updated_at')->take(self::RECENT)->get();
            $pickers[$type] = ['label' => $v['plural'], 'selected' => $selected, 'recent' => $recent];
        }

        return view('admin.home-settings', ['settings' => $settings, 'pickers' => $pickers]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'banners.*.title' => 'nullable|string|max:140', 'banners.*.tag' => 'nullable|string|max:40', 'banners.*.text' => 'nullable|string|max:240',
            'banners.*.image' => 'nullable|string|max:1000', 'banners.*.url' => 'nullable|string|max:1000', 'banner_upload.*' => 'nullable|image|max:6144',
            'collections.*.title' => 'nullable|string|max:120', 'collections.*.image' => 'nullable|string|max:1000', 'collections.*.url' => 'nullable|string|max:1000', 'collection_upload.*' => 'nullable|image|max:6144',
            'ads.*.title' => 'nullable|string|max:120', 'ads.*.image' => 'nullable|string|max:1000', 'ads.*.url' => 'nullable|string|max:1000',
            'ads.*.orientation' => 'nullable|in:horizontal,vertical', 'ads.*.pages' => 'nullable|array', 'ads.*.pages.*' => 'in:'.implode(',', array_keys(HomeSetting::AD_PAGES)), 'ad_upload.*' => 'nullable|image|max:6144',
            'trending' => 'nullable|array', 'trending.*' => 'nullable|array', 'trending.*.*' => 'integer|exists:vehicle_models,id',
        ]);

        $trending = [];
        foreach (array_keys(config('vehicles')) as $type) {
            $trending[$type] = array_values(array_unique(array_map('intval', $request->input("trending.$type", []))));
        }

        HomeSetting::current()->update([
            'hero_banners' => $this->rows($request, 'banners', 'banner_upload', ['title', 'tag', 'text', 'url']),
            'collections' => $this->rows($request, 'collections', 'collection_upload', ['title', 'url']),
            'ads' => $this->rows($request, 'ads', 'ad_upload', ['title', 'url', 'orientation'], function (array $item, $i) use ($request) {
                $item['orientation'] = $item['orientation'] === 'vertical' ? 'vertical' : 'horizontal';
                $item['pages'] = array_values((array) $request->input("ads.$i.pages", []));
                $item['active'] = $request->boolean("ads.$i.active");
                return $item;
            }),
            'trending_ids' => $trending,
        ]);

        return back()->with('success', 'Home settings saved.');
    }

    /**
     * Collect repeater rows keyed by their own index, so an uploaded file always lines up with its row.
     * A row is kept when it has a title, an image URL or an uploaded image.
     */
    private function rows(Request $request, string $key, string $fileKey, array $fields, ?\Closure $extra = null): array
    {
        $out = [];
        foreach ((array) $request->input($key, []) as $i => $row) {
            $upload = $request->file("$fileKey.$i");
            $image = trim((string) ($row['image'] ?? ''));
            if ($upload) $image = $this->moveToPublicUploads($upload);
            if (! trim((string) ($row['title'] ?? '')) && $image === '') continue;

            $item = ['image' => $image];
            foreach ($fields as $f) $item[$f] = trim((string) ($row[$f] ?? ''));
            $out[] = $extra ? $extra($item, $i) : $item;
        }
        return $out;
    }

    private function moveToPublicUploads($file): string
    {
        $folder = public_path('uploads/home/'.date('Y/m'));
        if (! is_dir($folder)) mkdir($folder, 0755, true);
        $name = uniqid('home_', true).'.'.$file->getClientOriginalExtension();
        $file->move($folder, $name);
        return asset('uploads/home/'.date('Y/m').'/'.$name);
    }
}
