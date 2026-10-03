<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesBulk;

use App\Http\Controllers\Controller;
use App\Models\VehicleModel;
use App\Models\VehicleBrand;
use App\Models\VehicleBodyType;
use App\Models\VehicleFuel;
use App\Services\AiClient;
use App\Services\CarUpdater;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CarModelController extends Controller
{
    use HandlesBulk;

    /** Fields an editor can pin so automation never overwrites them. */
    public const LOCKABLE = [
        'status' => 'Status', 'price_min' => 'Price', 'specs' => 'Specs', 'tagline' => 'Tagline', 'overview' => 'Overview text',
        'highlights' => 'Highlights', 'faq' => 'FAQ', 'gallery' => 'Photo gallery', 'hero_image' => 'Main photo',
    ];

    public function index()
    {
        return view('admin.carmodels.index', [
            'brands' => VehicleBrand::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'bodies' => VehicleBodyType::where('is_active', true)->orderBy('name')->get(),
            'masterBrands' => VehicleBrand::where('is_active', true)->orderBy('name')->pluck('name'),
            'masterBodies' => VehicleBodyType::where('is_active', true)->orderBy('name')->pluck('name'),
            'masterFuels' => VehicleFuel::where('is_active', true)->orderBy('name')->pluck('name'),
        ]);
    }

    public function data(Request $r)
    {
        $q = VehicleModel::withCount('articles')
            ->when($r->type, fn ($x, $v) => $x->where('vehicle_type', $v))
            ->when($r->status, fn ($x, $v) => $x->where('status', $v))
            ->when($r->brand, fn ($x, $v) => $x->where('brand_id', $v))
            ->when($r->body, fn ($x, $v) => $x->where('body_type_id', $v))
            ->when($r->published !== null && $r->published !== '', fn ($x) => $x->where('is_published', (bool) $r->published));
        \App\Support\DataTable::dateRange($q, $r->range, 'latest_event_at');

        return \App\Support\DataTable::make($q, $r, ['name', 'status', 'price_min', null, null, 'latest_event_at', null], ['name', 'brand', 'body_type'],
            fn (VehicleModel $c) => [
                \App\Support\Ui::thumb($c->hero_url, $c->full_name, $c->body_type, route('admin.car-models.edit', $c)).($c->needs_refresh ? ' '.\App\Support\Ui::badge('refresh pending', 'warning') : '').($c->is_published ? '' : ' '.\App\Support\Ui::badge('hidden', 'secondary')),
                \App\Support\Ui::status($c->status), '<span class="small">'.e($c->price_label).'</span>', count($c->gallery ?? []), $c->articles_count,
                $c->latest_event ? '<span class="small">'.e($c->latest_event).' · '.e($c->latest_event_at?->diffForHumans()).'</span>' : '<span class="text-muted">—</span>',
                \App\Support\Ui::actions(['view' => $c->is_published ? $c->url : route('admin.car-models.preview', $c), 'view_title' => $c->is_published ? 'View on website' : 'Preview (hidden)', 'edit' => route('admin.car-models.edit', $c), 'delete' => route('admin.car-models.destroy', $c), 'delete_msg' => 'Delete this model page?']),
            ], ['id', 'desc']);
    }
    /** Build a model page from a product / launch link (text is rewritten, the link itself is never stored). */
    public function importUrl(Request $r)
    {
        $d = $r->validate([
            'url' => 'nullable|string|max:700',
            'urls' => 'nullable|string|max:8000',       // several links, one per line
            'merge' => 'nullable|boolean',              // all links are the SAME model: combine them into one richer page
            'vehicle_type' => 'required|in:'.implode(',', array_keys(config('vehicles'))),
        ]);
        $links = collect(preg_split('/[\s,]+/', trim(($d['urls'] ?? '')."\n".($d['url'] ?? '')), -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn ($u) => trim($u))->filter(fn ($u) => filter_var($u, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $u))->unique()->values();
        if ($links->isEmpty()) return $this->importReply($r, false, 'Paste at least one valid link (starting with http:// or https://).');
        if ($links->count() > 30) return $this->importReply($r, false, 'Please import at most 30 links at a time.');

        @set_time_limit(280);
        $importer = new \App\Services\VehicleImporter();
        $publish = ! $r->boolean('draft');

        // One model from one or several links.
        if ($links->count() === 1 || $r->boolean('merge')) {
            $res = $importer->importUrl($links->take(5)->all(), $d['vehicle_type'], $publish);
            if (! $res instanceof VehicleModel) return $this->importReply($r, false, $res);
            return $this->importReply($r, true, 'Model added'.($res->is_published ? '' : ' as hidden').'. Review the details below, then save.', $res);
        }

        // Several links = several models, processed one after another (the admin page sends them one per request to show progress).
        $made = []; $failed = []; $deadline = microtime(true) + 240;
        foreach ($links as $u) {
            if (microtime(true) > $deadline) { $failed[] = "$u — skipped (time limit; import it again)"; continue; }
            $res = $importer->importUrl($u, $d['vehicle_type'], $publish);
            $res instanceof VehicleModel ? $made[] = $res : $failed[] = "$u — $res";
        }
        $msg = count($made).' of '.$links->count().' models added'.($publish ? '' : ' as hidden').'.'.($failed ? ' Not imported: '.implode(' | ', $failed) : '');
        if (count($made) === 1 && ! $failed) return redirect()->route('admin.car-models.edit', $made[0])->with('success', $msg);
        return redirect()->route('admin.car-models.index')->with($made ? 'success' : 'error', $msg);
    }

    /** JSON for the page's progress loop, a normal redirect otherwise. */
    private function importReply(Request $r, bool $ok, string $message, ?VehicleModel $m = null)
    {
        if ($r->expectsJson()) {
            return response()->json(['ok' => $ok, 'message' => $message] + ($m ? ['name' => $m->full_name, 'specs' => count($m->specs ?? []), 'edit_url' => route('admin.car-models.edit', $m)] : []), $ok ? 200 : 422);
        }
        if (! $ok) return back()->withInput()->with('error', $message);
        return redirect()->route('admin.car-models.edit', $m)->with('success', $message);
    }

    /** Fill empty / thin specs: reads them from a link if one is given, otherwise from the official spec sheet. */
    public function fillSpecs(Request $r, VehicleModel $car_model)
    {
        $r->validate(['url' => 'nullable|url|max:700']);
        if ($car_model->isLocked('specs')) return back()->with('error', 'Specs are pinned (locked) for this model. Unpin them first.');
        if (! AiClient::configured()) return back()->with('error', 'AI provider is not configured (Settings → AI).');
        @set_time_limit(200);
        $start = count($car_model->specs ?? []);
        $text = '';
        if ($r->filled('url')) {
            $page = \App\Services\PageFetcher::article($r->input('url'));
            if (! $page) return back()->with('error', 'Could not open that page.');
            $car_model->specs = \App\Services\SpecFiller::merge(\App\Services\SpecFiller::normalize($car_model->specs ?? []), $page['specs'] ?? []) ?: null;
            $car_model->save();
            $text = $page['text'];
        }
        \App\Services\SpecFiller::fill($car_model, $text, true);
        $added = count($car_model->specs ?? []) - $start;
        return redirect()->route('admin.car-models.edit', $car_model)->with($added > 0 ? 'success' : 'error', $added > 0 ? "Added $added spec(s). Please check them before publishing." : 'No new specs could be found for this model.');
    }

    public function preview(VehicleModel $car_model)
    {
        $car = $car_model;
        return view('site.newcars.show', [
            'car' => $car, 'news' => $car->articles()->latest('published_at')->take(6)->get(), 'used' => collect(), 'videos' => collect(), 'preview' => true,
        ]);
    }

    /** All body types are sent; the form script shows only those of the chosen vehicle type. */
    private function masters(): array { return ['masterBrands'=>VehicleBrand::where('is_active',true)->orderBy('sort_order')->orderBy('name')->get(),'masterBodies'=>VehicleBodyType::where('is_active',true)->orderBy('sort_order')->orderBy('name')->get(),'masterFuels'=>VehicleFuel::where('is_active',true)->orderBy('sort_order')->orderBy('name')->get()]; }
    public function create(Request $r) { $type = array_key_exists($r->query('type'), config('vehicles')) ? $r->query('type') : 'car'; return view('admin.carmodels.form', array_merge(['car' => new VehicleModel(['vehicle_type' => $type, 'status' => 'launched', 'is_published' => true]), 'linked' => collect(), 'lockable' => self::LOCKABLE], $this->masters())); }

    public function edit(VehicleModel $car_model)
    {
        return view('admin.carmodels.form', array_merge(['car' => $car_model, 'linked' => $car_model->articles()->latest('published_at')->limit(8)->get(), 'lockable' => self::LOCKABLE], $this->masters()));
    }

    public function store(Request $r) { return $this->save($r, new VehicleModel()); }

    public function update(Request $r, VehicleModel $car_model) { return $this->save($r, $car_model); }

    private function save(Request $r, VehicleModel $car)
    {
        $d = $r->validate([
            'vehicle_type' => 'required|in:'.implode(',', array_keys(config('vehicles'))), 'brand_id' => 'required|exists:vehicle_brands,id', 'name' => 'required|string|max:80',
            'status' => 'required|in:upcoming,launched,facelift,discontinued', 'body_type_id' => 'nullable|exists:vehicle_body_types,id',
            'fuel_types' => 'nullable|array', 'fuel_types.*' => 'exists:vehicle_fuels,id', 'price_min_lakh' => 'nullable|numeric|min:0', 'price_max_lakh' => 'nullable|numeric|min:0',
            'launch_date' => 'nullable|date', 'tagline' => 'nullable|string|max:300', 'overview' => 'nullable|string',
            'highlights' => 'nullable|string', 'specs' => 'nullable|string', 'meta_title' => 'nullable|string|max:70', 'meta_description' => 'nullable|string|max:320',
            'locked' => 'nullable|array', 'photos.*' => 'nullable|image|max:6144', 'image_urls' => 'nullable|string', 'remove' => 'nullable|array',
        ]);

        $brandName = VehicleBrand::findOrFail($d['brand_id'])->name;
        // A body type only applies to its own vehicle type; drop a stale one if the type was changed.
        if (! empty($d['body_type_id']) && VehicleBodyType::whereKey($d['body_type_id'])->value('vehicle_type') !== $d['vehicle_type']) $d['body_type_id'] = null;
        $bodyName = isset($d['body_type_id']) ? VehicleBodyType::find($d['body_type_id'])?->name : null;
        $fuelNames = VehicleFuel::whereIn('id', $d['fuel_types'] ?? [])->pluck('name')->all();
        $brand = CarUpdater::canonicalBrand($brandName);
        $car->fill([
            'vehicle_type' => $d['vehicle_type'],
            'brand_id' => $d['brand_id'], 'name' => trim($d['name']), 'status' => $d['status'], 'body_type_id' => $d['body_type_id'] ?? null,
            'price_min' => isset($d['price_min_lakh']) ? (int) round($d['price_min_lakh'] * 100000) : null,
            'price_max' => isset($d['price_max_lakh']) ? (int) round($d['price_max_lakh'] * 100000) : null,
            'launch_date' => $d['launch_date'] ?? null, 'tagline' => $d['tagline'] ?? null,
            'overview' => isset($d['overview']) ? \App\Services\Editorial::cleanEditorHtml($d['overview']) : null,
            'highlights' => array_values(array_filter(array_map('trim', preg_split('/\R/', $d['highlights'] ?? '')))) ?: null,
            'meta_title' => $d['meta_title'] ?? null, 'meta_description' => $d['meta_description'] ?? null,
            'is_published' => $r->boolean('is_published'),
            'locked' => array_values(array_intersect(array_keys(self::LOCKABLE), $d['locked'] ?? [])) ?: null,
        ]);
        if (! $car->exists) $car->slug = $this->uniqueSlug("$brand ".$d['name']);

        $specs = [];
        foreach (preg_split('/\R/', $d['specs'] ?? '') as $line) if (str_contains($line, ':')) { [$k, $v] = array_map('trim', explode(':', $line, 2)); if ($k !== '' && $v !== '') $specs[$k] = $v; }
        $car->specs = $specs ?: null;
        $car->save();
        $car->fuels()->sync($d['fuel_types'] ?? []);

        // Gallery: removals, uploads, and URLs to fetch.
        $gallery = $car->gallery ?? [];
        foreach ($d['remove'] ?? [] as $p) { $gallery = array_values(array_diff($gallery, [$p])); if (! Str::startsWith($p, 'http')) Storage::disk('public')->delete($p); }
        foreach ($r->file('photos', []) as $f) $gallery[] = $this->storePublicUpload($f, 'car-catalog');
        $car->gallery = $gallery ?: null;
        if ($gallery && (! $car->hero_image || ! in_array($car->hero_image, $gallery))) $car->hero_image = $gallery[0];
        $car->save();
        if (! empty($d['image_urls'])) CarUpdater::refreshImages($car, array_filter(array_map('trim', preg_split('/\R/', $d['image_urls']))));
        CarUpdater::linkListings($car);

        return redirect()->route('admin.car-models.edit', $car)->with('success', 'Car model saved.');
    }

    private function storePublicUpload($file, string $module): string
    {
        $folder = public_path('uploads/'.$module.'/'.date('Y/m'));
        if (! is_dir($folder)) mkdir($folder, 0755, true);
        $name = uniqid($module.'_', true).'.'.$file->getClientOriginalExtension();
        $file->move($folder, $name);
        return asset('uploads/'.$module.'/'.date('Y/m').'/'.$name);
    }

    private function uniqueSlug(string $base): string
    {
        $slug = Str::slug($base); $i = 2; $orig = $slug;
        while (VehicleModel::where('slug', $slug)->exists()) $slug = $orig.'-'.$i++;
        return $slug;
    }

    /** Rewrite overview / highlights / FAQ / SEO text from everything we know (skips locked fields). */
    public function refresh(VehicleModel $car_model)
    {
        if (! AiClient::configured()) return back()->with('error', 'AI provider is not configured.');
        @set_time_limit(200);
        return CarUpdater::refreshContent($car_model, true)
            ? back()->with('success', 'Content regenerated from the latest coverage.')
            : back()->with('error', 'The AI did not return usable content. Try again.');
    }

    public function destroy(VehicleModel $car_model)
    {
        $car_model->delete();
        return redirect()->route('admin.car-models.index')->with('success', 'Car model deleted.');
    }
    protected function bulkBase(Request $r): \Illuminate\Database\Eloquent\Builder { return VehicleModel::query(); }

    protected function bulkActions(Request $r): array
    {
        $a = [
            'enable' => ['label' => 'Enable (publish)', 'do' => fn (VehicleModel $m) => $m->update(['is_published' => true]) || true],
            'disable' => ['label' => 'Disable (hide)', 'do' => fn (VehicleModel $m) => $m->update(['is_published' => false]) || true],
            'status' => ['label' => 'Change status', 'options' => [['upcoming', 'Upcoming'], ['launched', 'Launched'], ['facelift', 'Facelift'], ['discontinued', 'Discontinued']],
                'do' => fn (VehicleModel $m, Request $r) => in_array($r->value, ['upcoming', 'launched', 'facelift', 'discontinued'], true) && ! $m->isLocked('status') && $m->update(['status' => $r->value])],
        ];
        if (AiClient::configured()) {
            $a['refresh'] = ['label' => 'Regenerate text with AI', 'confirm' => 'Uses AI credits; locked fields are kept. Best for a handful of models at a time.', 'do' => fn (VehicleModel $m) => CarUpdater::refreshContent($m, true)];
        }
        $a['delete'] = ['label' => 'Delete permanently', 'danger' => true, 'confirm' => 'This cannot be undone.', 'do' => fn (VehicleModel $m) => (bool) $m->delete()];
        return $a;
    }
}
