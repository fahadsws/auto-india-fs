<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\VehicleModel;
use App\Models\Listing;
use App\Models\Video;
use App\Models\VehicleBodyType;
use App\Models\VehicleFuel;
use Illuminate\Http\Request;

class NewCarController extends Controller
{
    /** One controller serves cars, bikes and trucks; the route default `vehicle` picks the type. */
    public function index(Request $r, string $vehicle = 'car')
    {
        abort_unless(config("vehicles.$vehicle"), 404);
        $base = fn () => VehicleModel::published()->ofType($vehicle);

        $q = $base()
            ->when($r->status, fn ($x, $s) => $x->where('status', $s))
            ->when($r->brand, fn ($x, $b) => $x->whereIn('brand_id', (array) $b))
            ->when($r->body_type, fn ($x, $b) => $x->whereIn('body_type_id', (array) $b))
            ->when($r->fuel, fn ($x, $fuels) => $x->whereHas('fuels', fn ($f) => $f->whereIn('vehicle_fuels.id', (array) $fuels)))
            ->when($r->q, fn ($x, $s) => $x->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhereHas('brandMaster', fn ($b) => $b->where('name', 'like', "%$s%"))))
            ->tap(fn ($x) => \App\Support\Filters::applyBudget($x, 'price_min', $r->budget, $vehicle))
            ->when($r->filled('price_max'), fn ($x) => $x->where('price_min', '>', 0)->where('price_min', '<=', (int) $r->price_max))
            ->when($r->filled('price_min'), fn ($x) => $x->where(fn ($w) => $w->where('price_min', '>=', (int) $r->price_min)->orWhere('price_max', '>=', (int) $r->price_min)))
            ->orderByDesc('latest_event_at')->orderByDesc('updated_at');

        $bc = $base()->selectRaw('brand_id, count(*) n')->groupBy('brand_id')->pluck('n', 'brand_id');
        $tc = $base()->selectRaw('body_type_id, count(*) n')->groupBy('body_type_id')->pluck('n', 'body_type_id');
        $opt = fn ($rows, $counts) => $rows->map(fn ($m) => ['value' => $m->id, 'label' => $m->name, 'count' => $counts[$m->id] ?? null])->all();
        $brands = \App\Models\VehicleBrand::where('is_active', true)->whereIn('id', $bc->keys())->orderBy('sort_order')->orderBy('name')->get();
        $fuelIds = \DB::table('vehicle_model_fuel')->whereIn('vehicle_model_id', $base()->select('id'))->pluck('vehicle_fuel_id');

        return view('site.newcars.index', [
            'vehicle' => $vehicle,
            'v' => config("vehicles.$vehicle"),
            'cars' => $q->paginate(12)->withQueryString(),
            'filters' => [
                ['key' => 'brand', 'title' => 'Brands', 'options' => $opt($brands, $bc)],
                ['key' => 'body_type', 'title' => 'Body Type', 'options' => $opt(VehicleBodyType::ofType($vehicle)->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(), $tc)],
                ['key' => 'budget', 'title' => 'Budget', 'options' => \App\Support\Filters::budgetOptions($vehicle)],
                ['key' => 'fuel', 'title' => 'Fuel', 'options' => $opt(VehicleFuel::where('is_active', true)->whereIn('id', $fuelIds)->orderBy('sort_order')->orderBy('name')->get(), [])],
            ],
        ]);
    }

    public function show(string $slug, string $vehicle = 'car')
    {
        abort_unless(config("vehicles.$vehicle"), 404);
        $car = VehicleModel::published()->ofType($vehicle)->where('slug', $slug)->firstOrFail();

        return view('site.newcars.show', [
            'car' => $car,
            // Same body type, closest price first.
            'rivals' => VehicleModel::published()->ofType($vehicle)->with('brandMaster')->whereKeyNot($car->id)
                ->when($car->body_type_id, fn ($x, $t) => $x->where('body_type_id', $t))
                ->when($car->price_min, fn ($x, $p) => $x->orderByRaw('ABS(COALESCE(price_min, 0) - ?)', [$p]))
                ->orderByDesc('latest_event_at')->take(4)->get(),
            'news' => $car->articles()->published()->latest('published_at')->take(6)->get(),
            'used' => $vehicle === 'car' ? Listing::active()->where('vehicle_model_id', $car->id)->latest()->take(3)->get() : collect(),
            'videos' => Video::active()->where('title', 'like', '%'.$car->name.'%')->latest('published_at')->take(3)->get(),
        ]);
    }
}
