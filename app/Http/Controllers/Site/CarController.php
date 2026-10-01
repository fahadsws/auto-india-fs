<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Listing;
use App\Services\LeadNotifier;
use Illuminate\Http\Request;

class CarController extends Controller
{
    public function index(Request $r)
    {
        $q = Listing::active();
        if ($s = trim((string) $r->q)) $q->where(fn ($w) => $w->where('title', 'like', "%$s%")->orWhereHas('brandMaster', fn ($b) => $b->where('name', 'like', "%$s%"))->orWhere('model', 'like', "%$s%")->orWhere('city', 'like', "%$s%"));
        foreach (['transmission', 'city'] as $f) {
            if ($r->filled($f)) $q->whereIn($f, (array) $r->$f);
        }
        // Brand and fuel are ids in the database; the URL keeps readable names.
        if ($r->filled('brand')) $q->whereHas('brandMaster', fn ($b) => $b->whereIn('name', (array) $r->brand));
        if ($r->filled('fuel')) $q->whereHas('fuelMaster', fn ($b) => $b->whereIn('name', (array) $r->fuel));
        \App\Support\Filters::applyBudget($q, 'price', $r->budget);
        // Exact filters (used by the AI assistant's "view all" links so the page shows the same cars it counted).
        if ($r->filled('price_min')) $q->where('price', '>=', (int) $r->price_min);
        if ($r->filled('price_max')) $q->where('price', '>', 0)->where('price', '<=', (int) $r->price_max);
        if ($r->filled('year')) $q->where('year', (int) $r->year);
        if ($r->filled('year_min')) $q->where('year', '>=', (int) $r->year_min);
        if ($r->filled('year_max')) $q->where('year', '<=', (int) $r->year_max);
        if ($r->filled('km_max')) $q->where('km_driven', '<=', (int) $r->km_max);
        if ($r->filled('owner')) $q->where(fn ($x) => $x->where('owner', 'like', '%1%')->orWhere('owner', 'like', '%first%'));
        if ($r->filled('body_type')) $q->whereHas('vehicleModel', fn ($x) => $x->whereIn('body_type_id', (array) $r->body_type));

        match ($r->sort) {
            'price_asc' => $q->orderBy('price'),
            'price_desc' => $q->orderByDesc('price'),
            'year_desc' => $q->orderByDesc('year'),
            'km_asc' => $q->orderBy('km_driven'),
            default => $q->latest(),
        };

        $count = fn ($col) => \App\Support\Filters::fromCounts(Listing::active()->whereNotNull($col)->selectRaw("$col v, count(*) n")->groupBy($col)->orderBy($col)->pluck('n', 'v'));

        $countMaster = fn (string $table, string $fk) => \App\Support\Filters::fromCounts(
            Listing::active()->join($table, "$table.id", '=', "listings.$fk")->selectRaw("$table.name v, count(*) n, $table.sort_order")->groupBy("$table.name", "$table.sort_order")->orderBy("$table.sort_order")->orderBy("$table.name")->pluck('n', 'v'));

        return view('site.cars.index', [
            'listings' => $q->paginate(12)->withQueryString(),
            'filters' => [
                ['key' => 'brand', 'title' => 'Brands', 'options' => $countMaster('vehicle_brands', 'brand_id')],
                ['key' => 'budget', 'title' => 'Budget', 'options' => \App\Support\Filters::budgetOptions()],
                ['key' => 'fuel', 'title' => 'Fuel', 'options' => $countMaster('vehicle_fuels', 'fuel_id')],
                ['key' => 'transmission', 'title' => 'Transmission', 'options' => $count('transmission')],
                ['key' => 'city', 'title' => 'City', 'options' => $count('city')],
            ],
        ]);
    }

    public function show(string $slug)
    {
        $listing = Listing::active()->where('slug', $slug)->firstOrFail();
        $similar = Listing::active()->where('id', '!=', $listing->id)
            ->when($listing->brand_id, fn ($q) => $q->where('brand_id', $listing->brand_id))->latest()->take(3)->get();

        return view('site.cars.show', compact('listing', 'similar'));
    }

    public function enquire(Request $r, Listing $listing)
    {
        if ($r->filled('website')) return back(); // honeypot

        $data = $r->validate([
            'name' => 'required|string|max:120',
            'phone' => 'required|string|min:8|max:20',
            'email' => 'nullable|email|max:150',
            'message' => 'nullable|string|max:1000',
        ]);

        $lead = Lead::create($data + ['listing_id' => $listing->id, 'type' => 'enquiry', 'ip' => $r->ip()]);
        LeadNotifier::notify($lead);

        return back()->with('success', "Thanks {$lead->name}! Our team will contact you shortly about the {$listing->title}.");
    }
}
