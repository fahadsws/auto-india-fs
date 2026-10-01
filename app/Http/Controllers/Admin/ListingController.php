<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesBulk;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Services\ListingImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ListingController extends Controller
{
    use HandlesBulk;

    public function index()
    {
        return view('admin.listings.index', [
            'brands' => \App\Models\VehicleBrand::whereIn('id', Listing::whereNotNull('brand_id')->select('brand_id'))->orderBy('name')->get(['id', 'name']),
            'fuels' => \App\Models\VehicleFuel::whereIn('id', Listing::whereNotNull('fuel_id')->select('fuel_id'))->orderBy('name')->get(['id', 'name']),
            'cities' => Listing::whereNotNull('city')->distinct()->orderBy('city')->pluck('city'),
            'sources' => \App\Models\ListingSource::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Current list filters (shared by the table and "select all matching" bulk actions). */
    private function filtered(Request $r)
    {
        $q = Listing::withCount('leads')
            ->when($r->status, fn ($x, $v) => $x->where('status', $v))
            ->when($r->brand, fn ($x, $v) => $x->where('brand_id', $v))
            ->when($r->fuel, fn ($x, $v) => $x->where('fuel_id', $v))
            ->when($r->city, fn ($x, $v) => $x->where('city', $v))
            ->when($r->source === 'manual', fn ($x) => $x->whereNull('listing_source_id')->whereNull('external_id'))
            ->when($r->source && $r->source !== 'manual', fn ($x) => $x->where('listing_source_id', $r->source))
            ->when($r->max_price, fn ($x, $v) => $x->where('price', '<=', (int) $v));
        \App\Support\DataTable::dateRange($q, $r->range);

        return $q;
    }

    public function data(Request $r)
    {
        $q = $this->filtered($r);

        return \App\Support\DataTable::make($q, $r, ['title', 'price', null, null, null, 'status', 'created_at', null], ['title', 'brandMaster.name', 'model', 'city'],
            fn (Listing $l) => [
                \App\Support\Ui::thumb($l->image_url, \Illuminate\Support\Str::limit($l->title, 55), $l->city, route('admin.listings.edit', $l)),
                e($l->price_label),
                '<span class="small">'.e(implode(' · ', array_filter([$l->year, $l->km_driven ? number_format($l->km_driven).' km' : null, $l->fuel]))).'</span>',
                '<span class="small text-muted">'.e($l->source_name ?? 'Manual').'</span>', $l->leads_count, \App\Support\Ui::status($l->status),
                \App\Support\Ui::date($l->created_at, 'd M Y'),
                \App\Support\Ui::actions(['view' => $l->status === 'active' ? $l->url : route('admin.listings.preview', $l), 'view_title' => $l->status === 'active' ? 'View on website' : 'Preview (not public)', 'edit' => route('admin.listings.edit', $l), 'delete' => route('admin.listings.destroy', $l), 'delete_msg' => 'Delete this listing?']),
            ]);
    }
    public function preview(Listing $listing)
    {
        return view('site.cars.show', ['listing' => $listing->load('vehicleModel'), 'similar' => collect(), 'preview' => true]);
    }

    public function create() { return view('admin.listings.form', ['listing' => new Listing(['status' => 'active'])]); }

    public function edit(Listing $listing) { return view('admin.listings.form', compact('listing')); }

    public function store(Request $r) { return $this->save($r, new Listing()); }

    public function update(Request $r, Listing $listing) { return $this->save($r, $listing); }

    private function save(Request $r, Listing $listing)
    {
        $d = $r->validate([
            'title' => 'required|string|max:200',
            'brand' => 'nullable|string|max:80', 'model' => 'nullable|string|max:80',
            'year' => 'nullable|integer|min:1980|max:'.(date('Y') + 1),
            'price' => 'nullable|integer|min:0', 'km_driven' => 'nullable|integer|min:0',
            'fuel' => 'nullable|string|max:30', 'transmission' => 'nullable|string|max:30', 'owner' => 'nullable|string|max:30',
            'city' => 'nullable|string|max:80', 'description' => 'nullable|string|max:5000',
            'status' => 'required|in:active,sold,hidden', 'source_url' => 'nullable|url|max:700',
            'image' => 'nullable|image|max:5120',
        ]);
        $d['slug'] = $listing->exists ? $listing->slug : Listing::uniqueSlug($d['title'].' '.($d['year'] ?? ''));
        if ($r->hasFile('image')) {
            if ($listing->image_path && ! Str::startsWith($listing->image_path, 'http')) Storage::disk('public')->delete($listing->image_path);
            $d['image_path'] = $this->storePublicUpload($r->file('image'), 'listings');
        }
        unset($d['image']);
        $listing->fill($d)->save();

        return redirect()->route('admin.listings.index')->with('success', 'Listing saved.');
    }

    private function storePublicUpload($file, string $module): string
    {
        $folder = public_path('uploads/'.$module.'/'.date('Y/m'));
        if (! is_dir($folder)) mkdir($folder, 0755, true);
        $name = uniqid($module.'_', true).'.'.$file->getClientOriginalExtension();
        $file->move($folder, $name);
        return asset('uploads/'.$module.'/'.date('Y/m').'/'.$name);
    }

    public function destroy(Listing $listing)
    {
        $listing->delete();
        return back()->with('success', 'Listing deleted.');
    }

    /** CSV upload with columns named like our fields (title, brand, model, year, price, km_driven, fuel, ...). */
    public function importCsv(Request $r)
    {
        $r->validate(['csv' => 'required|file|mimes:csv,txt|max:5120']);
        $imp = new ListingImporter();
        $rows = $imp->parseCsv(file_get_contents($r->file('csv')->getRealPath()));
        [$new, $upd] = $imp->importRows($rows, [], 'CSV upload');
        return back()->with('success', "CSV imported: $new new, $upd updated.");
    }
    protected function bulkSearchable(): array { return ['title', 'brandMaster.name', 'model', 'city']; }

    protected function bulkFiltered(Request $r): ?\Illuminate\Database\Eloquent\Builder { return $this->filtered($r); }

    protected function bulkBase(Request $r): \Illuminate\Database\Eloquent\Builder { return Listing::query(); }

    protected function bulkActions(Request $r): array
    {
        return [
            'enable' => ['label' => 'Enable (show on website)', 'do' => fn (Listing $m) => $m->update(['status' => 'active', 'sold_at' => null, 'status_reason' => null]) || true],
            'sold' => ['label' => 'Mark sold', 'do' => fn (Listing $m) => $m->update(['status' => 'sold', 'sold_at' => now(), 'status_reason' => 'marked sold manually']) || true],
            'disable' => ['label' => 'Disable (hide from website)', 'do' => fn (Listing $m) => $m->update(['status' => 'hidden']) || true],
            'delete' => ['label' => 'Delete permanently', 'danger' => true, 'confirm' => 'This cannot be undone.', 'do' => function (Listing $m) {
                if ($m->image_path && ! Str::startsWith($m->image_path, 'http')) Storage::disk('public')->delete($m->image_path);
                return (bool) $m->delete();
            }],
        ];
    }
}
